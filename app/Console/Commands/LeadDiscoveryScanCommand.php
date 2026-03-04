<?php

namespace App\Console\Commands;

use App\Models\LdScanRun;
use App\Services\LeadDiscovery\ScanOrchestrator;
use Illuminate\Console\Command;

class LeadDiscoveryScanCommand extends Command
{
    protected $signature = 'lead-discovery:scan
        {--manual : Run in manual mode}
        {--cells= : Comma-separated grid cell IDs}
        {--keywords= : Comma-separated keyword IDs}
        {--force : Force run even when scheduled run exists}';

    protected $description = 'Run Lead Discovery scan and dispatch scan jobs by grid-cell and keyword.';

    public function __construct(
        private readonly ScanOrchestrator $orchestrator
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $mode = $this->option('manual')
            ? LdScanRun::MODE_MANUAL
            : LdScanRun::MODE_SCHEDULED;

        $run = $this->orchestrator->runScan([
            'mode' => $mode,
            'force' => (bool) $this->option('force'),
            'cell_ids' => $this->option('cells'),
            'keyword_ids' => $this->option('keywords'),
        ]);

        $totals = $run->totals_json ?? [];
        $pairs = (int) ($totals['pairs_dispatched'] ?? 0);

        $this->info("Lead discovery scan run #{$run->id} ({$run->mode}) created. Pairs dispatched: {$pairs}.");

        return self::SUCCESS;
    }
}
