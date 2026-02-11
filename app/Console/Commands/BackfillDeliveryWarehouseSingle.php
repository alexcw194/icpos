<?php

namespace App\Console\Commands;

use App\Models\Delivery;
use App\Models\Warehouse;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;

class BackfillDeliveryWarehouseSingle extends Command
{
    protected $signature = 'deliveries:backfill-warehouse-single
        {--company_id= : Batasi ke company tertentu}
        {--dry-run : Simulasi tanpa menulis data}
        {--chunk=200 : Ukuran chunk proses}';

    protected $description = 'Isi warehouse_id untuk delivery draft kosong jika company hanya punya 1 warehouse aktif';

    /** @var array<int, array{state: string, warehouse_id: int|null}> */
    private array $companyWarehouseState = [];

    public function handle(): int
    {
        if (!Schema::hasTable('deliveries') || !Schema::hasTable('warehouses')) {
            $this->error('Tabel deliveries/warehouses belum tersedia.');
            return self::FAILURE;
        }

        $companyId = $this->option('company_id') ? (int) $this->option('company_id') : null;
        $dryRun = (bool) $this->option('dry-run');
        $chunk = max((int) $this->option('chunk'), 1);

        $query = Delivery::query()
            ->where('status', Delivery::STATUS_DRAFT)
            ->whereNull('warehouse_id')
            ->when($companyId, fn ($q) => $q->where('company_id', $companyId))
            ->orderBy('id');

        $total = (clone $query)->count();
        if ($total === 0) {
            $this->warn('Tidak ada delivery draft tanpa warehouse.');
            return self::SUCCESS;
        }

        $this->info("Total delivery draft kandidat: {$total}");
        $this->line('Mode: '.($dryRun ? 'DRY-RUN' : 'WRITE'));

        $stats = [
            'updated' => 0,
            'skipped_multiple' => 0,
            'skipped_no_warehouse' => 0,
            'failed' => 0,
        ];

        $query->chunkById($chunk, function ($rows) use (&$stats, $dryRun) {
            foreach ($rows as $delivery) {
                try {
                    $companyId = (int) ($delivery->company_id ?? 0);
                    $state = $this->resolveCompanyWarehouseState($companyId);

                    if ($state['state'] === 'single' && $state['warehouse_id']) {
                        if (!$dryRun) {
                            $delivery->forceFill([
                                'warehouse_id' => $state['warehouse_id'],
                            ])->save();
                        }
                        $stats['updated']++;
                        continue;
                    }

                    if ($state['state'] === 'multiple') {
                        $stats['skipped_multiple']++;
                    } else {
                        $stats['skipped_no_warehouse']++;
                    }
                } catch (\Throwable $e) {
                    $stats['failed']++;
                    $this->error("Delivery #{$delivery->id} gagal: {$e->getMessage()}");
                }
            }
        });

        $this->newLine();
        $this->info('Selesai backfill warehouse delivery draft.');
        $this->table(
            ['updated', 'skipped_multiple', 'skipped_no_warehouse', 'failed'],
            [[
                $stats['updated'],
                $stats['skipped_multiple'],
                $stats['skipped_no_warehouse'],
                $stats['failed'],
            ]]
        );

        return $stats['failed'] > 0 ? self::FAILURE : self::SUCCESS;
    }

    /**
     * @return array{state: string, warehouse_id: int|null}
     */
    private function resolveCompanyWarehouseState(int $companyId): array
    {
        if ($companyId <= 0) {
            return ['state' => 'none', 'warehouse_id' => null];
        }

        if (isset($this->companyWarehouseState[$companyId])) {
            return $this->companyWarehouseState[$companyId];
        }

        $warehouseIds = Warehouse::query()
            ->forCompany($companyId)
            ->where('is_active', true)
            ->orderBy('id')
            ->limit(2)
            ->pluck('id');

        if ($warehouseIds->count() === 1) {
            return $this->companyWarehouseState[$companyId] = [
                'state' => 'single',
                'warehouse_id' => (int) $warehouseIds->first(),
            ];
        }

        if ($warehouseIds->isEmpty()) {
            return $this->companyWarehouseState[$companyId] = [
                'state' => 'none',
                'warehouse_id' => null,
            ];
        }

        return $this->companyWarehouseState[$companyId] = [
            'state' => 'multiple',
            'warehouse_id' => null,
        ];
    }
}
