<?php

namespace App\Console\Commands;

use App\Models\SalesOrder;
use App\Services\SalesOrderStatusSyncService;
use Illuminate\Console\Command;

class SyncSalesOrderStatus extends Command
{
    protected $signature = 'so:sync-status
        {--so_id= : Sync specific sales order id}
        {--company_id= : Batasi ke company tertentu}
        {--all-types : Sertakan po_type selain goods}
        {--dry-run : Simulasi tanpa update}
        {--chunk=200 : Ukuran chunk proses}';

    protected $description = 'Recompute Sales Order status using billing+delivery matrix (idempotent)';

    public function __construct(
        private readonly SalesOrderStatusSyncService $salesOrderStatusSync
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $soId = $this->option('so_id') ? (int) $this->option('so_id') : null;
        $companyId = $this->option('company_id') ? (int) $this->option('company_id') : null;
        $allTypes = (bool) $this->option('all-types');
        $dryRun = (bool) $this->option('dry-run');
        $chunk = max((int) $this->option('chunk'), 1);

        $query = SalesOrder::query()
            ->when(!$allTypes, fn ($q) => $q->where('po_type', 'goods'))
            ->when($companyId, fn ($q) => $q->where('company_id', $companyId))
            ->when($soId, fn ($q) => $q->where('id', $soId))
            ->orderBy('id');

        $total = (clone $query)->count();
        if ($total === 0) {
            $this->warn('Tidak ada Sales Order yang cocok untuk sinkronisasi.');
            return self::SUCCESS;
        }

        $this->info("Total SO kandidat: {$total}");
        $this->line('Mode: '.($dryRun ? 'DRY-RUN' : 'WRITE'));

        $stats = [
            'changed' => 0,
            'unchanged' => 0,
            'failed' => 0,
        ];

        $query->chunkById($chunk, function ($rows) use (&$stats, $dryRun) {
            foreach ($rows as $so) {
                try {
                    $before = (string) ($so->status ?? '');
                    $after = $dryRun
                        ? $this->salesOrderStatusSync->preview($so)
                        : $this->salesOrderStatusSync->sync($so);

                    if ($before !== $after) {
                        $stats['changed']++;
                    } else {
                        $stats['unchanged']++;
                    }
                } catch (\Throwable $e) {
                    $stats['failed']++;
                    $this->error("Gagal sync SO #{$so->id}: {$e->getMessage()}");
                }
            }
        });

        $this->newLine();
        $this->info('Selesai sinkron status SO.');
        $this->table(
            ['changed', 'unchanged', 'failed'],
            [[
                $stats['changed'],
                $stats['unchanged'],
                $stats['failed'],
            ]]
        );

        return $stats['failed'] > 0 ? self::FAILURE : self::SUCCESS;
    }
}
