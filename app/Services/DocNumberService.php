<?php

namespace App\Services;

use App\Models\Company;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Carbon;

class DocNumberService
{
    /** @param 'quotation'|'invoice'|'delivery'|'sales_order' $docType */
    public static function next(string $docType, Company $company, Carbon $docDate): string
    {
        $year   = (int)$docDate->format('Y');
        $counterType = match ($docType) {
            'project_quotation' => 'project_quot',
            default => $docType,
        };

        // Tambah dukungan 'sales_order' + fallback prefix 'SO'
        $prefix = match ($docType) {
            'quotation'        => $company->quotation_prefix ?? 'QO',
            'invoice'          => $company->invoice_prefix   ?? 'INV',
            'delivery'         => $company->delivery_prefix  ?? 'DO',
            'sales_order'      => $company->sales_order_prefix ?? 'SO',
            'project'          => 'PRJ',
            'project_quotation'=> 'BQ',
            default            => strtoupper(substr($docType, 0, 3)),
        };

        $alias  = strtoupper($company->alias ?? 'CO');

        $seq = DB::transaction(function () use ($company, $docType, $counterType, $year) {
            $row = DB::table('document_counters')
                ->where(['company_id' => $company->id, 'doc_type' => $counterType, 'year' => $year])
                ->lockForUpdate()
                ->first();

            // ===== Auto-seed untuk doc_type yang belum pernah dipakai
            $last = 0;
            if (!$row) {
                if ($docType === 'sales_order') {
                    // Ambil nomor terbesar YANG SUDAH ADA, apapun prefix/alias-nya, ambil 5 digit paling kanan
                    $max = DB::table('sales_orders')
                        ->where('company_id', $company->id)
                        ->whereYear('order_date', $year)
                        ->selectRaw('MAX(CAST(RIGHT(so_number, 5) AS UNSIGNED)) as m')
                        ->value('m');
                    $last = (int) ($max ?: 0);
                }

                DB::table('document_counters')->insert([
                    'company_id' => $company->id,
                    'doc_type'   => $counterType,
                    'year'       => $year,
                    'last_seq'   => $last,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            } else {
                $last = (int) $row->last_seq;
            }
            // ===== End auto-seed

            $next = $last + 1;

            DB::table('document_counters')
              ->where(['company_id' => $company->id, 'doc_type' => $counterType, 'year' => $year])
              ->update(['last_seq' => $next, 'updated_at' => now()]);

            return $next;
        });

        return sprintf('%s/%s/%d/%05d', $prefix, $alias, $year, $seq);
    }
}
