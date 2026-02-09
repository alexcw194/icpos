<?php

namespace App\Console\Commands;

use App\Models\BillingDocument;
use App\Models\Invoice;
use App\Services\BillingInvoiceSyncService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class BackfillInvoicesFromBilling extends Command
{
    protected $signature = 'invoices:backfill-from-billing
        {--company_id= : Batasi ke company tertentu}
        {--sync-existing : Sinkron juga invoice yang sudah ada}
        {--refresh-lines : Paksa refresh invoice_lines untuk data yang disinkron}
        {--dry-run : Simulasi tanpa menulis data}
        {--chunk=200 : Ukuran chunk proses}';

    protected $description = 'Backfill invoices table from issued billing_documents (idempotent)';

    public function __construct(
        private readonly BillingInvoiceSyncService $billingInvoiceSync
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        if (!Schema::hasTable('billing_documents')) {
            $this->warn('Table billing_documents tidak ditemukan. Tidak ada yang diproses.');
            return self::SUCCESS;
        }

        if (!Schema::hasTable('invoices')) {
            $this->error('Table invoices tidak ditemukan.');
            return self::FAILURE;
        }

        $companyId = $this->option('company_id') ? (int) $this->option('company_id') : null;
        $syncExisting = (bool) $this->option('sync-existing');
        $refreshLines = (bool) $this->option('refresh-lines');
        $dryRun = (bool) $this->option('dry-run');
        $chunk = max((int) $this->option('chunk'), 1);

        $query = BillingDocument::query()
            ->with(['salesOrder', 'lines'])
            ->whereNotNull('inv_number')
            ->where('status', '!=', 'void')
            ->when($companyId, fn ($q) => $q->where('company_id', $companyId))
            ->orderBy('id');

        $total = (clone $query)->count();
        if ($total === 0) {
            $this->info('Tidak ada issued billing document untuk dibackfill.');
            return self::SUCCESS;
        }

        $this->info("Total billing documents kandidat: {$total}");
        $this->line('Mode: '.($dryRun ? 'DRY-RUN' : 'WRITE'));

        $stats = [
            'created' => 0,
            'updated' => 0,
            'skipped_existing' => 0,
            'failed' => 0,
        ];

        $query->chunkById($chunk, function ($rows) use (&$stats, $syncExisting, $refreshLines, $dryRun) {
            foreach ($rows as $billing) {
                $existing = Invoice::query()
                    ->where('company_id', $billing->company_id)
                    ->where('number', $billing->inv_number)
                    ->first();

                if ($existing && !$syncExisting) {
                    $stats['skipped_existing']++;
                    continue;
                }

                if ($dryRun) {
                    $existing ? $stats['updated']++ : $stats['created']++;
                    continue;
                }

                try {
                    DB::transaction(function () use ($billing, $existing, $refreshLines) {
                        $syncLines = !$existing || $refreshLines;
                        $this->billingInvoiceSync->sync($billing, [
                            'invoice_number' => $billing->inv_number,
                            'issue_date' => $billing->invoice_date ?? $billing->issued_at ?? now(),
                            'posted_at' => $billing->issued_at ?? $billing->locked_at ?? now(),
                            'sync_lines' => $syncLines,
                            'preserve_paid' => true,
                        ]);
                    });
                    $existing ? $stats['updated']++ : $stats['created']++;
                } catch (\Throwable $e) {
                    $stats['failed']++;
                    $this->error("Gagal sync billing #{$billing->id} ({$billing->inv_number}): {$e->getMessage()}");
                }
            }
        });

        $this->newLine();
        $this->info('Selesai backfill invoices dari billing.');
        $this->table(
            ['created', 'updated', 'skipped_existing', 'failed'],
            [[
                $stats['created'],
                $stats['updated'],
                $stats['skipped_existing'],
                $stats['failed'],
            ]]
        );

        return $stats['failed'] > 0 ? self::FAILURE : self::SUCCESS;
    }
}
