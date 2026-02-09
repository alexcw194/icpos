<?php

namespace App\Console\Commands;

use App\Models\Delivery;
use App\Models\SalesOrder;
use App\Services\AutoDeliveryDraftFromSoService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class BackfillDeliveryDraftForUnpaidInvoices extends Command
{
    protected $signature = 'deliveries:backfill-draft-for-unpaid-invoices
        {--company_id= : Batasi ke company tertentu}
        {--so_id= : Proses SO spesifik}
        {--limit=0 : Batasi jumlah SO yang diproses (0 = tanpa limit)}
        {--dry-run : Simulasi tanpa menulis data}';

    protected $description = 'Backfill Delivery Draft untuk SO goods yang sudah punya invoice posted unpaid dan belum closed';

    public function __construct(
        private readonly AutoDeliveryDraftFromSoService $autoDeliveryDraftFromSo
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        if (!Schema::hasTable('sales_orders') || !Schema::hasTable('sales_order_lines') || !Schema::hasTable('deliveries')) {
            $this->error('Tabel sales_orders/sales_order_lines/deliveries belum lengkap.');
            return self::FAILURE;
        }

        $hasInvoicesTable = Schema::hasTable('invoices');
        $hasBillingDocumentsTable = Schema::hasTable('billing_documents');

        if (!$hasInvoicesTable && !$hasBillingDocumentsTable) {
            $this->error('Tabel invoices atau billing_documents tidak ditemukan.');
            return self::FAILURE;
        }

        $companyId = $this->option('company_id') ? (int) $this->option('company_id') : null;
        $soId = $this->option('so_id') ? (int) $this->option('so_id') : null;
        $limit = max((int) $this->option('limit'), 0);
        $dryRun = (bool) $this->option('dry-run');

        $query = SalesOrder::query()
            ->with(['lines'])
            ->where('po_type', 'goods')
            ->whereNotIn('status', ['closed', 'cancelled'])
            ->when($companyId, fn ($q) => $q->where('company_id', $companyId))
            ->when($soId, fn ($q) => $q->where('id', $soId))
            ->where(function ($q) use ($hasInvoicesTable, $hasBillingDocumentsTable) {
                if ($hasInvoicesTable) {
                    $q->whereHas('invoices', function ($iq) {
                        $iq->whereIn('status', ['posted', 'sent'])
                            ->whereNull('paid_at');
                    });
                }

                if ($hasBillingDocumentsTable) {
                    $fallbackCondition = function ($fallback) {
                        $fallback
                            ->whereDoesntHave('invoices')
                            ->whereHas('billingDocuments', function ($bq) {
                                $bq->whereNotNull('inv_number')
                                    ->where('status', '!=', 'void');
                            });
                    };

                    if ($hasInvoicesTable) {
                        $q->orWhere($fallbackCondition);
                    } else {
                        $q->where($fallbackCondition);
                    }
                }
            })
            ->orderBy('id');

        if ($limit > 0) {
            $query->limit($limit);
        }

        $candidates = $query->get();
        if ($candidates->isEmpty()) {
            $this->warn('Tidak ada SO kandidat untuk backfill DO draft.');
            return self::SUCCESS;
        }

        $this->info('SO kandidat: '.$candidates->count());
        $this->line('Mode: '.($dryRun ? 'DRY-RUN' : 'WRITE'));

        $stats = [
            'created' => 0,
            'reused_existing_draft' => 0,
            'skipped_no_remaining' => 0,
            'failed' => 0,
        ];

        foreach ($candidates as $so) {
            try {
                if ($dryRun) {
                    $hasDraft = Delivery::query()
                        ->where('sales_order_id', $so->id)
                        ->where('status', Delivery::STATUS_DRAFT)
                        ->exists();

                    if ($hasDraft) {
                        $stats['reused_existing_draft']++;
                        continue;
                    }

                    $hasRemaining = $this->hasRemainingQty($so);
                    if ($hasRemaining) {
                        $stats['created']++;
                    } else {
                        $stats['skipped_no_remaining']++;
                    }
                    continue;
                }

                DB::transaction(function () use ($so, &$stats) {
                    $result = $this->autoDeliveryDraftFromSo->ensureForSalesOrder($so);
                    if (($result['created'] ?? false) === true) {
                        $stats['created']++;
                    } elseif (($result['reused'] ?? false) === true) {
                        $stats['reused_existing_draft']++;
                    } else {
                        $stats['skipped_no_remaining']++;
                    }
                });
            } catch (\Throwable $e) {
                $stats['failed']++;
                $this->error("SO #{$so->id} gagal: {$e->getMessage()}");
            }
        }

        $this->newLine();
        $this->info('Selesai backfill DO draft untuk invoice unpaid.');
        $this->table(
            ['created', 'reused_existing_draft', 'skipped_no_remaining', 'failed'],
            [[
                $stats['created'],
                $stats['reused_existing_draft'],
                $stats['skipped_no_remaining'],
                $stats['failed'],
            ]]
        );

        return $stats['failed'] > 0 ? self::FAILURE : self::SUCCESS;
    }

    private function hasRemainingQty(SalesOrder $so): bool
    {
        foreach ($so->lines as $line) {
            $ordered = (float) ($line->qty_ordered ?? 0);
            $delivered = (float) ($line->qty_delivered ?? 0);
            if (max(0.0, $ordered - $delivered) > 0) {
                return true;
            }
        }
        return false;
    }
}
