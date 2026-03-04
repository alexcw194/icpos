<?php

namespace App\Console\Commands;

use App\Models\Prospect;
use Illuminate\Console\Command;

class LeadDiscoveryBackfillLocationsCommand extends Command
{
    protected $signature = 'lead-discovery:backfill-locations
        {--dry-run : Show candidates only without updating city/province}';

    protected $description = 'Backfill prospect city/province from linked grid cell when missing.';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $checked = 0;
        $candidates = 0;
        $updated = 0;

        Prospect::query()
            ->with('gridCell:id,city,province')
            ->whereNotNull('grid_cell_id')
            ->where(function ($query) {
                $query->whereNull('city')->orWhere('city', '')
                    ->orWhereNull('province')->orWhere('province', '');
            })
            ->orderBy('id')
            ->chunkById(500, function ($rows) use ($dryRun, &$checked, &$candidates, &$updated): void {
                foreach ($rows as $prospect) {
                    $checked++;
                    $cell = $prospect->gridCell;
                    if (!$cell) {
                        continue;
                    }

                    $newCity = $prospect->city ?: ($cell->city ?: null);
                    $newProvince = $prospect->province ?: ($cell->province ?: null);
                    if (($prospect->city ?: null) === $newCity && ($prospect->province ?: null) === $newProvince) {
                        continue;
                    }

                    $candidates++;
                    if ($dryRun) {
                        continue;
                    }

                    $prospect->city = $newCity;
                    $prospect->province = $newProvince;
                    $prospect->save();
                    $updated++;
                }
            });

        $this->info('Lead Discovery backfill locations finished.');
        $this->line("Checked: {$checked}");
        $this->line("Candidates: {$candidates}");
        if ($dryRun) {
            $this->line('Dry-run mode: no data updated.');
        } else {
            $this->line("Updated: {$updated}");
        }

        return self::SUCCESS;
    }
}
