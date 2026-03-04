<?php

namespace App\Console\Commands;

use App\Models\Prospect;
use App\Services\LeadDiscovery\ProspectResultFilter;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class LeadDiscoveryCleanupRetailCommand extends Command
{
    protected $signature = 'lead-discovery:cleanup-retail
        {--dry-run : Show candidates only without updating status}';

    protected $description = 'Mark old retail tenant/store prospects as ignored (skip converted).';

    public function __construct(
        private readonly ProspectResultFilter $resultFilter
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $checked = 0;
        $matched = 0;
        $updated = 0;
        $alreadyIgnored = 0;

        Prospect::query()
            ->where('status', '!=', Prospect::STATUS_CONVERTED)
            ->orderBy('id')
            ->chunkById(500, function ($rows) use ($dryRun, &$checked, &$matched, &$updated, &$alreadyIgnored): void {
                foreach ($rows as $prospect) {
                    $checked++;
                    $types = is_array($prospect->types_json) ? $prospect->types_json : [];
                    if ($types === [] && is_array($prospect->raw_json ?? null) && is_array(($prospect->raw_json['types'] ?? null))) {
                        $types = $prospect->raw_json['types'];
                    }
                    $place = [
                        'name' => $prospect->name,
                        'formatted_address' => $prospect->formatted_address,
                        'short_address' => $prospect->short_address,
                        'primary_type' => $prospect->primary_type,
                        'types' => $types,
                    ];

                    if (!$this->resultFilter->shouldIgnore($place)) {
                        continue;
                    }

                    $matched++;
                    if ($prospect->status === Prospect::STATUS_IGNORED) {
                        $alreadyIgnored++;
                    }

                    if ($dryRun) {
                        continue;
                    }

                    $reason = $this->resultFilter->reason($place);
                    $rawJson = is_array($prospect->raw_json) ? $prospect->raw_json : [];
                    $rawJson['_lead_discovery_filter'] = [
                        'ignored' => true,
                        'reason' => $reason,
                        'scanned_at' => Carbon::now()->toIso8601String(),
                    ];

                    $changed = false;
                    if ($prospect->status !== Prospect::STATUS_IGNORED) {
                        $prospect->status = Prospect::STATUS_IGNORED;
                        $changed = true;
                    }

                    if (($prospect->raw_json ?? null) !== $rawJson) {
                        $prospect->raw_json = $rawJson;
                        $changed = true;
                    }

                    if ($changed) {
                        $prospect->save();
                        $updated++;
                    }
                }
            });

        $this->info('Lead Discovery cleanup retail finished.');
        $this->line("Checked: {$checked}");
        $this->line("Matched retail candidates: {$matched}");
        $this->line("Already ignored: {$alreadyIgnored}");
        if ($dryRun) {
            $this->line('Dry-run mode: no data updated.');
        } else {
            $this->line("Updated to ignored: {$updated}");
        }

        return self::SUCCESS;
    }
}
