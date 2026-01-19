<?php

namespace App\Services;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class DocumentNumberService
{
    public static function next(?Carbon $docDate = null): array
    {
        $date = $docDate ?: now();
        $year = (int) $date->format('Y');

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
