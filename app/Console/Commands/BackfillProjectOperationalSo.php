<?php

namespace App\Console\Commands;

use App\Models\Invoice;
use App\Models\Project;
use App\Models\ProjectQuotation;
use App\Models\SalesOrder;
use App\Models\SalesOrderBillingTerm;
use App\Services\ProjectSalesOrderBootstrapService;
use App\Services\SalesOrderStatusSyncService;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class BackfillProjectOperationalSo extends Command
{
    protected $signature = 'projects:backfill-operational-so
        {--project_id= : Batasi ke 1 project}
        {--chunk=100 : Ukuran chunk proses}
        {--dry-run : Simulasi tanpa menulis data}';

    protected $description = 'Backfill SO Project dari latest won BQ + map invoice project lama ke SO billing terms';

    public function __construct(
        private readonly ProjectSalesOrderBootstrapService $bootstrapService,
        private readonly SalesOrderStatusSyncService $salesOrderStatusSync
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        if (!Schema::hasTable('projects') || !Schema::hasTable('project_quotations') || !Schema::hasTable('sales_orders')) {
            $this->warn('Table project/project_quotation/sales_orders belum lengkap.');
            return self::SUCCESS;
        }

        $dryRun = (bool) $this->option('dry-run');
        $projectId = $this->option('project_id') ? (int) $this->option('project_id') : null;
        $chunk = max((int) $this->option('chunk'), 1);

        $query = Project::query()
            ->select('projects.*')
            ->whereHas('wonQuotations')
            ->when($projectId, fn ($q) => $q->where('projects.id', $projectId))
            ->orderBy('projects.id');

        $total = (clone $query)->count();
        if ($total === 0) {
            $this->info('Tidak ada project dengan BQ won yang perlu dibackfill.');
            return self::SUCCESS;
        }

        $stats = [
            'projects_scanned' => 0,
            'so_bootstrapped' => 0,
            'invoice_linked' => 0,
            'term_linked' => 0,
            'term_paid' => 0,
            'term_invoiced' => 0,
            'failed' => 0,
        ];

        $this->info('Mode: '.($dryRun ? 'DRY-RUN' : 'WRITE'));
        $this->info("Total kandidat: {$total}");

        $query->chunkById($chunk, function ($projects) use (&$stats, $dryRun) {
            foreach ($projects as $project) {
                $stats['projects_scanned']++;

                try {
                    $quotation = ProjectQuotation::query()
                        ->where('project_id', $project->id)
                        ->where('status', ProjectQuotation::STATUS_WON)
                        ->orderByDesc('id')
                        ->first();
                    if (!$quotation) {
                        continue;
                    }

                    if ($dryRun) {
                        $stats['so_bootstrapped']++;
                        continue;
                    }

                    DB::transaction(function () use ($project, $quotation, &$stats) {
                        $projectStatus = strtolower((string) ($project->status ?? ''));
                        if (!in_array($projectStatus, ['active', 'closed', 'cancelled'], true)) {
                            $project->update(['status' => 'active']);
                        }

                        $salesOrder = $this->bootstrapService->ensureForWonQuotation($project, $quotation);
                        $stats['so_bootstrapped']++;

                        $linkStats = $this->mapLegacyProjectInvoices($project, $quotation, $salesOrder);
                        $stats['invoice_linked'] += $linkStats['invoice_linked'];
                        $stats['term_linked'] += $linkStats['term_linked'];
                        $stats['term_paid'] += $linkStats['term_paid'];
                        $stats['term_invoiced'] += $linkStats['term_invoiced'];

                        $this->salesOrderStatusSync->sync($salesOrder->fresh());
                    });
                } catch (\Throwable $e) {
                    $stats['failed']++;
                    $this->error("Project #{$project->id} gagal: {$e->getMessage()}");
                }
            }
        });

        $this->newLine();
        $this->table(
            ['projects_scanned', 'so_bootstrapped', 'invoice_linked', 'term_linked', 'term_paid', 'term_invoiced', 'failed'],
            [[
                $stats['projects_scanned'],
                $stats['so_bootstrapped'],
                $stats['invoice_linked'],
                $stats['term_linked'],
                $stats['term_paid'],
                $stats['term_invoiced'],
                $stats['failed'],
            ]]
        );

        return $stats['failed'] > 0 ? self::FAILURE : self::SUCCESS;
    }

    /**
     * @return array{invoice_linked:int,term_linked:int,term_paid:int,term_invoiced:int}
     */
    private function mapLegacyProjectInvoices(Project $project, ProjectQuotation $quotation, SalesOrder $salesOrder): array
    {
        $stats = [
            'invoice_linked' => 0,
            'term_linked' => 0,
            'term_paid' => 0,
            'term_invoiced' => 0,
        ];

        if (!Schema::hasTable('invoices')) {
            return $stats;
        }

        $quotation->loadMissing('paymentTerms');
        $salesOrder->loadMissing('billingTerms');
        $hasSalesOrderIdColumn = Schema::hasColumn('invoices', 'sales_order_id');
        $hasProjectIdColumn = Schema::hasColumn('invoices', 'project_id');
        $hasProjectQuotationIdColumn = Schema::hasColumn('invoices', 'project_quotation_id');
        $hasProjectPaymentTermIdColumn = Schema::hasColumn('invoices', 'project_payment_term_id');
        $hasSoBillingTermIdColumn = Schema::hasColumn('invoices', 'so_billing_term_id');
        if (!$hasProjectIdColumn && !$hasProjectQuotationIdColumn && !$hasProjectPaymentTermIdColumn) {
            return $stats;
        }

        $quotationTermById = $quotation->paymentTerms
            ->keyBy('id');
        $soTermsByCode = $salesOrder->billingTerms
            ->groupBy(fn (SalesOrderBillingTerm $term) => strtoupper((string) $term->top_code));

        $quotationTermIds = [];
        if ($hasProjectPaymentTermIdColumn) {
            $quotationTermIds = $quotation->paymentTerms
                ->pluck('id')
                ->filter()
                ->map(fn ($id) => (int) $id)
                ->values()
                ->all();
        }

        $invoicesQuery = Invoice::query();
        $invoicesQuery->where(function ($q) use ($project, $quotation, $quotationTermIds, $hasProjectIdColumn, $hasProjectQuotationIdColumn, $hasProjectPaymentTermIdColumn) {
            $hasAnyCondition = false;
            if ($hasProjectIdColumn) {
                $q->where('project_id', $project->id);
                $hasAnyCondition = true;
            }
            if ($hasProjectQuotationIdColumn) {
                $hasAnyCondition ? $q->orWhere('project_quotation_id', $quotation->id) : $q->where('project_quotation_id', $quotation->id);
                $hasAnyCondition = true;
            }
            if ($hasProjectPaymentTermIdColumn && !empty($quotationTermIds)) {
                $hasAnyCondition ? $q->orWhereIn('project_payment_term_id', $quotationTermIds) : $q->whereIn('project_payment_term_id', $quotationTermIds);
            }
        });

        $invoices = $invoicesQuery
            ->orderBy('id')
            ->get();

        /** @var Collection<int, Invoice> $invoices */
        foreach ($invoices as $invoice) {
            $updates = [];
            if ($hasProjectIdColumn && (int) ($invoice->project_id ?? 0) !== (int) $project->id) {
                $updates['project_id'] = $project->id;
            }
            if ($hasProjectQuotationIdColumn && (int) ($invoice->project_quotation_id ?? 0) !== (int) $quotation->id) {
                $updates['project_quotation_id'] = $quotation->id;
            }
            if ($hasSalesOrderIdColumn && (int) ($invoice->sales_order_id ?? 0) !== (int) $salesOrder->id) {
                $updates['sales_order_id'] = $salesOrder->id;
            }

            $mappedTermId = $hasSoBillingTermIdColumn
                ? $this->resolveSoBillingTermId($invoice, $quotationTermById, $soTermsByCode)
                : null;
            if ($hasSoBillingTermIdColumn && $mappedTermId !== null && (int) ($invoice->so_billing_term_id ?? 0) !== $mappedTermId) {
                $termAlreadyLinked = Invoice::query()
                    ->where('so_billing_term_id', $mappedTermId)
                    ->where('id', '!=', $invoice->id)
                    ->exists();
                if (!$termAlreadyLinked) {
                    $updates['so_billing_term_id'] = $mappedTermId;
                }
            }

            if (!empty($updates)) {
                $invoice->update($updates);
                if (isset($updates['sales_order_id'])) {
                    $stats['invoice_linked']++;
                }
                if (isset($updates['so_billing_term_id'])) {
                    $stats['term_linked']++;
                }
            }

            $termId = $hasSoBillingTermIdColumn
                ? (int) ($updates['so_billing_term_id'] ?? $invoice->so_billing_term_id ?? 0)
                : 0;
            if ($termId <= 0) {
                continue;
            }

            $term = SalesOrderBillingTerm::query()
                ->where('id', $termId)
                ->where('sales_order_id', $salesOrder->id)
                ->first();
            if (!$term) {
                continue;
            }

            $invoiceStatus = strtolower((string) ($invoice->status ?? ''));
            if ($invoiceStatus === 'paid' || !empty($invoice->paid_at)) {
                if ($term->status !== 'paid' || (int) ($term->invoice_id ?? 0) !== (int) $invoice->id) {
                    $term->update([
                        'status' => 'paid',
                        'invoice_id' => $invoice->id,
                    ]);
                    $stats['term_paid']++;
                }
            } elseif ($invoiceStatus !== 'void' && $invoiceStatus !== 'cancelled') {
                if (!in_array($term->status, ['invoiced', 'paid'], true) || (int) ($term->invoice_id ?? 0) !== (int) $invoice->id) {
                    $term->update([
                        'status' => 'invoiced',
                        'invoice_id' => $invoice->id,
                    ]);
                    $stats['term_invoiced']++;
                }
            }
        }

        return $stats;
    }

    /**
     * @param Collection<int,\App\Models\ProjectQuotationPaymentTerm> $quotationTermById
     * @param Collection<string,Collection<int,SalesOrderBillingTerm>> $soTermsByCode
     */
    private function resolveSoBillingTermId(Invoice $invoice, Collection $quotationTermById, Collection $soTermsByCode): ?int
    {
        $projectPaymentTermId = (int) ($invoice->project_payment_term_id ?? 0);
        if ($projectPaymentTermId <= 0) {
            return null;
        }

        $quotationTerm = $quotationTermById->get($projectPaymentTermId);
        if (!$quotationTerm) {
            return null;
        }

        $code = strtoupper((string) ($quotationTerm->code ?? ''));
        if ($code === '' || !$soTermsByCode->has($code)) {
            return null;
        }

        $candidates = $soTermsByCode->get($code) ?: collect();
        if ($candidates->isEmpty()) {
            return null;
        }

        $targetPercent = (float) ($quotationTerm->percent ?? 0);
        $exact = $candidates->first(function (SalesOrderBillingTerm $term) use ($targetPercent) {
            return abs(((float) $term->percent) - $targetPercent) <= 0.01;
        });
        if ($exact) {
            return (int) $exact->id;
        }

        if ($candidates->count() === 1) {
            return (int) $candidates->first()->id;
        }

        return null;
    }
}
