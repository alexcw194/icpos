<?php

namespace App\Services;

use App\Models\Company;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class DocumentNumberService
{
    public static function next(?Carbon $docDate = null): array
    {
        $date = $docDate ?: now();
        $year = (int) $date->format('Y');
        $companyId = Company::where('is_default', true)->value('id')
            ?: Company::query()->value('id');

        if (!$companyId) {
            return self::nextFromSequence($year);
        }

        $seq = DB::transaction(function () use ($year, $companyId) {
            DB::table('document_counters')->insertOrIgnore([
                'company_id' => $companyId,
                'doc_type' => 'document',
                'year' => $year,
                'last_seq' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $row = DB::table('document_counters')
                ->where([
                    'company_id' => $companyId,
                    'doc_type' => 'document',
                    'year' => $year,
                ])
                ->lockForUpdate()
                ->first();

            $last = (int) ($row->last_seq ?? 0);
            $next = $last + 1;

            DB::table('document_counters')
                ->where([
                    'company_id' => $companyId,
                    'doc_type' => 'document',
                    'year' => $year,
                ])
                ->update([
                    'last_seq' => $next,
                    'updated_at' => now(),
                ]);

            return $next;
        });

        return [
            'number' => sprintf('DOC/ICP/%d/%05d', $year, $seq),
            'year' => $year,
            'sequence' => $seq,
        ];
    }

    private static function nextFromSequence(int $year): array
    {
        $seq = DB::transaction(function () use ($year) {
            DB::table('document_sequences')->insertOrIgnore([
                'year' => $year,
                'last_seq' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $row = DB::table('document_sequences')
                ->where('year', $year)
                ->lockForUpdate()
                ->first();

            $last = (int) ($row->last_seq ?? 0);
            $next = $last + 1;

            DB::table('document_sequences')
                ->where('year', $year)
                ->update([
                    'last_seq' => $next,
                    'updated_at' => now(),
                ]);

            return $next;
        });

        return [
            'number' => sprintf('DOC/ICP/%d/%05d', $year, $seq),
            'year' => $year,
            'sequence' => $seq,
        ];
    }
}
