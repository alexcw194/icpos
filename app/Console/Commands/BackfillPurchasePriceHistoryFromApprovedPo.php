<?php

namespace App\Console\Commands;

use App\Models\PurchaseOrder;
use App\Services\PurchasePriceSyncService;
use Illuminate\Console\Command;

class BackfillPurchasePriceHistoryFromApprovedPo extends Command
{
    protected $signature = 'purchase-price:backfill-history-from-approved-po
        {--dry-run : Simulasi tanpa menulis data}
        {--chunk=200 : Ukuran chunk}
        {--company_id= : Batasi ke company tertentu}';

    protected $description = 'Backfill purchase price history and current last_cost from approved purchase orders';

    public function __construct(
        private readonly PurchasePriceSyncService $purchasePriceSyncService
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $chunk = max((int) $this->option('chunk'), 1);
        $dryRun = (bool) $this->option('dry-run');
        $companyId = $this->option('company_id') ? (int) $this->option('company_id') : null;

        $query = PurchaseOrder::query()
            ->with(['lines:id,purchase_order_id,item_id,item_variant_id,unit_price'])
            ->whereIn('status', ['approved', 'partially_received', 'fully_received', 'closed'])
            ->whereNotNull('approved_at')
            ->when($companyId, fn ($q) => $q->where('company_id', $companyId))
            ->orderBy('approved_at')
            ->orderBy('id');

        $total = (clone $query)->count();
        if ($total === 0) {
            $this->info('Tidak ada PO approved untuk dibackfill.');
            return self::SUCCESS;
        }

        $this->info("Total PO kandidat: {$total}");
        $this->line('Mode: '.($dryRun ? 'DRY-RUN' : 'WRITE'));

        $stats = [
            'processed_po' => 0,
            'processed_lines' => 0,
            'updated_items' => 0,
            'updated_variants' => 0,
            'history_created' => 0,
            'history_updated' => 0,
            'skipped' => 0,
            'failed' => 0,
        ];

        $query->chunkById($chunk, function ($rows) use (&$stats, $dryRun) {
            foreach ($rows as $po) {
                try {
                    $sync = $this->purchasePriceSyncService->syncFromApprovedPurchaseOrder($po, $dryRun);
                    $stats['processed_po']++;
                    $stats['processed_lines'] += $sync['processed_lines'];
                    $stats['updated_items'] += $sync['updated_items'];
                    $stats['updated_variants'] += $sync['updated_variants'];
                    $stats['history_created'] += $sync['history_created'];
                    $stats['history_updated'] += $sync['history_updated'];
                    $stats['skipped'] += $sync['skipped'];
                } catch (\Throwable $e) {
                    $stats['failed']++;
                    $this->error("Gagal proses PO #{$po->id}: {$e->getMessage()}");
                }
            }
        });

        $this->newLine();
        $this->info('Selesai backfill purchase price history.');
        $this->table(
            ['processed_po', 'processed_lines', 'updated_items', 'updated_variants', 'history_created', 'history_updated', 'skipped', 'failed'],
            [[
                $stats['processed_po'],
                $stats['processed_lines'],
                $stats['updated_items'],
                $stats['updated_variants'],
                $stats['history_created'],
                $stats['history_updated'],
                $stats['skipped'],
                $stats['failed'],
            ]]
        );

        return $stats['failed'] > 0 ? self::FAILURE : self::SUCCESS;
    }
}
