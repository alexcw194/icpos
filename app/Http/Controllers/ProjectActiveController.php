<?php

namespace App\Http\Controllers;

use App\Models\BillingDocument;
use App\Models\Invoice;
use App\Models\Project;
use App\Models\SalesOrder;
use App\Models\SalesOrderBillingTerm;
use App\Services\ProjectBillingTermStatusService;
use App\Services\ProjectSalesOrderBootstrapService;
use App\Services\SalesOrderExecutionVarianceService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ProjectActiveController extends Controller
{
    public function __construct(
        private readonly ProjectSalesOrderBootstrapService $projectSoBootstrap,
        private readonly SalesOrderExecutionVarianceService $executionVariance,
        private readonly ProjectBillingTermStatusService $projectBillingTermStatus
    ) {
    }

    public function index(Request $request)
    {
        $this->authorizeProjectActiveAccess();

        $user = $request->user();
        $q = trim((string) $request->query('q', ''));

        $projects = Project::query()
            ->select('projects.*')
            ->visibleTo($user)
            ->where('projects.status', 'active')
            ->whereHas('wonQuotations')
            ->with([
                'customer:id,name',
                'company:id,alias,name',
                'salesOwner:id,name',
                'latestWonQuotation' => fn ($query) => $query
                    ->select([
                        'project_quotations.id',
                        'project_quotations.project_id',
                        'project_quotations.number',
                        'project_quotations.quotation_date',
                        'project_quotations.grand_total',
                        'project_quotations.won_at',
                    ]),
                'latestProjectSalesOrder' => fn ($query) => $query
                    ->select([
                        'sales_orders.id',
                        'sales_orders.project_id',
                        'sales_orders.project_quotation_id',
                        'sales_orders.so_number',
                        'sales_orders.contract_value',
                        'sales_orders.total',
                        'sales_orders.status',
                    ]),
            ])
            ->when($q !== '', function ($query) use ($q) {
                $query->where(function ($w) use ($q) {
                    $w->where('projects.code', 'like', "%{$q}%")
                        ->orWhere('projects.name', 'like', "%{$q}%");
                });
            })
            ->orderByDesc('projects.updated_at')
            ->paginate(15)
            ->withQueryString();

        return view('projects.active.index', compact('projects', 'q'));
    }

    public function show(Project $project)
    {
        $this->authorizeProjectActiveAccess();
        $this->authorize('view', $project);

        $project->load([
            'customer:id,name',
            'company:id,alias,name',
            'salesOwner:id,name',
            'latestWonQuotation' => fn ($query) => $query
                ->select([
                    'project_quotations.id',
                    'project_quotations.project_id',
                    'project_quotations.company_id',
                    'project_quotations.customer_id',
                    'project_quotations.number',
                    'project_quotations.quotation_date',
                    'project_quotations.won_at',
                    'project_quotations.tax_enabled',
                    'project_quotations.tax_percent',
                    'project_quotations.grand_total',
                    'project_quotations.status',
                ])
                ->with([
                    'paymentTerms' => fn ($terms) => $terms->orderBy('sequence'),
                ]),
        ]);

        $quotation = $project->latestWonQuotation;
        if (!$quotation) {
            return redirect()
                ->route('projects.active.index')
                ->with('warning', 'Project tidak punya BQ won.');
        }
        if (strtolower((string) ($project->status ?? '')) !== 'active') {
            return redirect()
                ->route('projects.active.index')
                ->with('warning', 'Project belum berstatus active.');
        }

        $salesOrder = $this->projectSoBootstrap->ensureForWonQuotation($project, $quotation);
        $salesOrder->load([
            'billingTerms.term',
            'billingTerms.invoice',
        ]);

        $termIds = $salesOrder->billingTerms
            ->pluck('id')
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->values();

        $billingByTerm = collect();
        if ($termIds->isNotEmpty()) {
            $billingByTerm = BillingDocument::query()
                ->where('sales_order_id', $salesOrder->id)
                ->whereIn('so_billing_term_id', $termIds->all())
                ->where('status', '!=', 'void')
                ->orderByDesc('id')
                ->get()
                ->groupBy(fn (BillingDocument $doc) => (int) $doc->so_billing_term_id)
                ->map(function ($group) {
                    return $group
                        ->groupBy(fn (BillingDocument $doc) => $this->normalizeBillingComponent($doc->billing_component))
                        ->map(fn ($docs) => $docs->first());
                });
        }

        $invoicesByTerm = collect();
        if ($termIds->isNotEmpty()) {
            $invoicesByTerm = Invoice::query()
                ->where('sales_order_id', $salesOrder->id)
                ->whereIn('so_billing_term_id', $termIds->all())
                ->orderByDesc('id')
                ->get()
                ->groupBy(fn (Invoice $invoice) => (int) $invoice->so_billing_term_id)
                ->map(function ($group) {
                    return $group
                        ->groupBy(fn (Invoice $invoice) => $this->normalizeBillingComponent($invoice->billing_component))
                        ->map(fn ($invoices) => $invoices->first());
                });
        }

        $termRows = $salesOrder->billingTerms->map(function (SalesOrderBillingTerm $term) use ($billingByTerm, $invoicesByTerm, $salesOrder) {
            $progress = $this->projectBillingTermStatus->progressForTerm($term);
            $requiredComponents = $progress['required_components'];
            $requiredCount = count($requiredComponents);
            $isSplitMode = $progress['mode'] === SalesOrder::PROJECT_BILLING_MODE_SPLIT && $requiredCount > 1;

            $billingComponents = collect($billingByTerm->get((int) $term->id, []));
            $invoiceComponents = collect($invoicesByTerm->get((int) $term->id, []));

            $status = 'Not Billed';
            $statusClass = 'bg-secondary-lt text-dark';
            $progressLabel = null;

            if ($progress['status'] === 'paid') {
                $status = 'Paid';
                $statusClass = 'bg-green-lt text-green';
            } elseif ($progress['status'] === 'invoiced') {
                $status = 'Invoiced';
                $statusClass = 'bg-blue-lt text-blue';
            } else {
                $hasProforma = $billingComponents
                    ->whereIn('status', ['sent'])
                    ->contains(function (BillingDocument $doc) {
                        return strtolower((string) ($doc->mode ?? '')) === 'proforma'
                            && empty($doc->inv_number);
                    });
                $hasDraft = $billingComponents
                    ->whereIn('status', ['draft', 'sent'])
                    ->isNotEmpty();

                if ($hasProforma) {
                    $status = 'Proforma';
                    $statusClass = 'bg-purple-lt text-purple';
                } elseif ($hasDraft) {
                    $status = 'Draft';
                    $statusClass = 'bg-yellow-lt text-dark';
                }
            }

            $hasActiveBilling = collect($requiredComponents)->contains(function (string $component) use ($billingComponents) {
                $doc = $billingComponents->get($component);
                if (!$doc) {
                    return false;
                }

                return in_array((string) $doc->status, ['draft', 'sent'], true) && empty($doc->inv_number);
            });

            if ($isSplitMode) {
                $progressLabel = sprintf(
                    'Invoiced %d/%d | Paid %d/%d',
                    (int) $progress['invoiced_count'],
                    $requiredCount,
                    (int) $progress['paid_count'],
                    $requiredCount
                );
            }

            $primaryBilling = $billingComponents->get('combined') ?? $billingComponents->first();
            $primaryInvoice = $invoiceComponents->get('combined') ?? $invoiceComponents->first();
            $termTotal = round(((float) ($salesOrder->contract_value ?? $salesOrder->total ?? 0)) * ((float) ($term->percent ?? 0)) / 100, 2);

            return (object) [
                'term' => $term,
                'billing' => $primaryBilling,
                'invoice' => $primaryInvoice,
                'billing_components' => $billingComponents,
                'invoice_components' => $invoiceComponents,
                'status' => $status,
                'status_class' => $statusClass,
                'progress_label' => $progressLabel,
                'is_split_mode' => $isSplitMode,
                'required_components' => $requiredComponents,
                'term_total' => $termTotal,
                'can_create_billing_draft' => !$hasActiveBilling && ((int) $progress['invoiced_count'] < $requiredCount),
            ];
        });

        $variance = $this->executionVariance->build($salesOrder);

        return view('projects.active.show', [
            'project' => $project,
            'quotation' => $quotation,
            'salesOrder' => $salesOrder,
            'termRows' => $termRows,
            'executionRows' => $variance['rows'],
            'executionTotals' => $variance['totals'],
        ]);
    }

    public function createBillingDraftFromTerm(Request $request, Project $project, SalesOrderBillingTerm $term)
    {
        $this->authorizeProjectActiveAccess();
        $this->authorize('view', $project);
        $this->authorizePermission('invoices.create');
        if (strtolower((string) ($project->status ?? '')) !== 'active') {
            return back()->with('warning', 'Project harus berstatus active.');
        }

        $salesOrder = SalesOrder::query()
            ->with(['project', 'billingTerms', 'projectQuotation'])
            ->findOrFail((int) $term->sales_order_id);

        if ((int) ($salesOrder->project_id ?? 0) !== (int) $project->id) {
            abort(404);
        }

        if (strtolower((string) ($salesOrder->po_type ?? '')) !== 'project') {
            return back()->with('error', 'Billing term ini bukan milik SO Project.');
        }

        if ((string) ($term->status ?? 'planned') === 'paid') {
            return back()->with('warning', 'Billing term sudah paid.');
        }

        $percent = (float) ($term->percent ?? 0);
        if ($percent <= 0) {
            return back()->with('error', 'Percent billing term harus lebih besar dari 0%.');
        }

        $contractValue = (float) ($salesOrder->contract_value ?? $salesOrder->total ?? 0);
        if ($contractValue <= 0) {
            return back()->with('error', 'Contract value SO Project tidak valid.');
        }

        $total = round($contractValue * $percent / 100, 2);
        if ($total <= 0) {
            return back()->with('error', 'Nilai billing term tidak valid.');
        }

        $componentBreakdown = $this->projectBillingTermStatus->componentBreakdownForSalesOrder($salesOrder);
        $components = (array) ($componentBreakdown['required_components'] ?? ['combined']);
        $hasMultiComponents = count($components) > 1;

        $existingDrafts = BillingDocument::query()
            ->where('sales_order_id', $salesOrder->id)
            ->where('so_billing_term_id', $term->id)
            ->whereIn('status', ['draft', 'sent'])
            ->whereNull('inv_number')
            ->orderByDesc('id')
            ->get()
            ->groupBy(fn (BillingDocument $doc) => $this->normalizeBillingComponent($doc->billing_component))
            ->map(fn ($docs) => $docs->first());

        if ($components === ['combined'] && $existingDrafts->isNotEmpty()) {
            /** @var BillingDocument $doc */
            $doc = $existingDrafts->first();
            return redirect()
                ->route('billings.show', $doc)
                ->with('ok', 'Billing draft untuk term ini sudah ada.');
        }
        if ($hasMultiComponents && $existingDrafts->has('combined')) {
            /** @var BillingDocument $doc */
            $doc = $existingDrafts->get('combined');
            return redirect()
                ->route('billings.show', $doc)
                ->with('warning', 'Term ini sudah punya billing draft combined.');
        }
        if ($components === ['combined'] && ($existingDrafts->has('material') || $existingDrafts->has('labor'))) {
            /** @var BillingDocument $doc */
            $doc = $existingDrafts->get('material') ?? $existingDrafts->get('labor');
            return redirect()
                ->route('billings.show', $doc)
                ->with('warning', 'Term ini sudah punya billing draft component.');
        }
        foreach ($components as $component) {
            $activeDraft = $existingDrafts->get($component);
            if ($activeDraft) {
                return redirect()
                    ->route('billings.show', $activeDraft)
                    ->with('ok', 'Billing draft untuk term ini sudah ada.');
            }
        }

        $invoiceByComponent = Invoice::query()
            ->where('sales_order_id', $salesOrder->id)
            ->where('so_billing_term_id', $term->id)
            ->orderByDesc('id')
            ->get()
            ->groupBy(fn (Invoice $invoice) => $this->normalizeBillingComponent($invoice->billing_component))
            ->map(fn ($invoices) => $invoices->first());

        if ($components === ['combined'] && $invoiceByComponent->isNotEmpty()) {
            return back()->with('warning', 'Term ini sudah memiliki invoice.');
        }
        if ($hasMultiComponents && $invoiceByComponent->has('combined')) {
            return back()->with('warning', 'Term ini sudah memiliki invoice combined.');
        }
        if ($components === ['combined'] && ($invoiceByComponent->has('material') || $invoiceByComponent->has('labor'))) {
            return back()->with('warning', 'Term ini sudah memiliki invoice component.');
        }
        foreach ($components as $component) {
            if ($invoiceByComponent->has($component)) {
                return back()->with('warning', 'Term ini sudah memiliki invoice untuk salah satu komponen.');
            }
        }

        $progress = $this->projectBillingTermStatus->progressForTerm($term);
        if ((int) $progress['invoiced_count'] >= count($components)) {
            return back()->with('warning', 'Term ini sudah complete invoiced.');
        }

        $amountByComponent = [];
        if ($components === ['combined']) {
            $amountByComponent = ['combined' => round($total, 2)];
        } elseif ($components === ['material']) {
            $amountByComponent = ['material' => round($total, 2)];
        } elseif ($components === ['labor']) {
            $amountByComponent = ['labor' => round($total, 2)];
        } else {
            $materialPool = max((float) ($componentBreakdown['material_total'] ?? 0), 0);
            $laborPool = max((float) ($componentBreakdown['labor_total'] ?? 0), 0);
            $poolTotal = $materialPool + $laborPool;
            if ($poolTotal <= 0) {
                $materialAmount = round($total / 2, 2);
            } else {
                $materialAmount = round($total * ($materialPool / $poolTotal), 2);
            }
            $laborAmount = round($total - $materialAmount, 2);
            $amountByComponent = [
                'material' => $materialAmount,
                'labor' => $laborAmount,
            ];
        }

        $lineComposition = [];
        if ($components === ['combined']) {
            $materialPool = max((float) ($componentBreakdown['material_total'] ?? 0), 0);
            $laborPool = max((float) ($componentBreakdown['labor_total'] ?? 0), 0);
            $poolTotal = $materialPool + $laborPool;
            if ($poolTotal > 0) {
                $lineMaterial = round($amountByComponent['combined'] * ($materialPool / $poolTotal), 2);
                $lineLabor = round($amountByComponent['combined'] - $lineMaterial, 2);
            } else {
                $lineMaterial = $amountByComponent['combined'];
                $lineLabor = 0.0;
            }
            $lineComposition['combined'] = ['material' => $lineMaterial, 'labor' => $lineLabor];
        } elseif ($components === ['material']) {
            $lineComposition['material'] = ['material' => $amountByComponent['material'], 'labor' => 0.0];
        } elseif ($components === ['labor']) {
            $lineComposition['labor'] = ['material' => 0.0, 'labor' => $amountByComponent['labor']];
        } else {
            $lineComposition['material'] = ['material' => $amountByComponent['material'], 'labor' => 0.0];
            $lineComposition['labor'] = ['material' => 0.0, 'labor' => $amountByComponent['labor']];
        }

        $createdBillings = DB::transaction(function () use ($salesOrder, $term, $project, $amountByComponent, $lineComposition) {
            $taxPercent = (float) ($salesOrder->tax_percent ?? 0);
            $created = collect();

            foreach ($amountByComponent as $component => $componentTotal) {
                if ($componentTotal <= 0) {
                    continue;
                }

                if ($taxPercent > 0) {
                    $subtotal = round($componentTotal / (1 + ($taxPercent / 100)), 2);
                    $taxAmount = round($componentTotal - $subtotal, 2);
                } else {
                    $subtotal = $componentTotal;
                    $taxAmount = 0.0;
                }

                $billing = BillingDocument::create([
                    'sales_order_id' => $salesOrder->id,
                    'so_billing_term_id' => $term->id,
                    'company_id' => $salesOrder->company_id,
                    'customer_id' => $salesOrder->customer_id,
                    'status' => 'draft',
                    'mode' => null,
                    'billing_component' => $component,
                    'subtotal' => $subtotal,
                    'discount_amount' => 0,
                    'tax_percent' => $taxPercent,
                    'tax_amount' => $taxAmount,
                    'total' => $componentTotal,
                    'currency' => $salesOrder->currency ?? 'IDR',
                    'notes' => sprintf(
                        'Project %s - Term %s (%s%%) - %s',
                        $project->code ?: $project->name,
                        $term->top_code,
                        rtrim(rtrim(number_format((float) $term->percent, 2, '.', ''), '0'), '.'),
                        $this->componentLabel($component)
                    ),
                    'created_by' => auth()->id(),
                    'updated_by' => auth()->id(),
                ]);

                $componentLine = $lineComposition[$component] ?? ['material' => $subtotal, 'labor' => 0.0];
                $lineMaterialTotal = round(max((float) ($componentLine['material'] ?? 0), 0), 2);
                $lineLaborTotal = round(max((float) ($componentLine['labor'] ?? 0), 0), 2);
                $lineMaterialUnit = $lineMaterialTotal;
                $lineLaborUnit = $lineLaborTotal;

                $billing->lines()->create([
                    'sales_order_line_id' => null,
                    'position' => 1,
                    'name' => sprintf('Billing Term %s - %s', $term->top_code, $this->componentLabel($component)),
                    'description' => sprintf(
                        '%s - %s',
                        $salesOrder->so_number,
                        trim((string) ($term->note ?: $term->top_code))
                    ),
                    'unit' => 'ls',
                    'qty' => 1,
                    'unit_price' => $lineMaterialUnit,
                    'labor_unit' => $lineLaborUnit,
                    'material_total' => $lineMaterialTotal,
                    'labor_total' => $lineLaborTotal,
                    'discount_type' => 'amount',
                    'discount_value' => 0,
                    'discount_amount' => 0,
                    'line_subtotal' => $subtotal,
                    'line_total' => $subtotal,
                ]);

                $created->push($billing);
            }

            return $created;
        });

        if ($createdBillings->isEmpty()) {
            return back()->with('error', 'Tidak ada billing draft yang dibuat.');
        }

        $message = $createdBillings->count() > 1
            ? 'Billing draft project (material + labor) berhasil dibuat dari payment term.'
            : 'Billing draft project berhasil dibuat dari payment term.';

        return redirect()
            ->route('billings.show', $createdBillings->first())
            ->with('success', $message);
    }

    private function normalizeBillingComponent(?string $component): string
    {
        $component = strtolower(trim((string) $component));
        if (in_array($component, ['material', 'labor'], true)) {
            return $component;
        }

        return 'combined';
    }

    private function componentLabel(string $component): string
    {
        return match ($this->normalizeBillingComponent($component)) {
            'material' => 'Material',
            'labor' => 'Labor',
            default => 'Combined',
        };
    }

    private function authorizeProjectActiveAccess(): void
    {
        $user = auth()->user();
        abort_unless($user && $user->hasAnyRole(['Admin', 'SuperAdmin', 'Finance']), 403);
    }

    private function authorizePermission(string $permission): void
    {
        $user = auth()->user();
        abort_unless($user, 403, 'This action is unauthorized.');
        if ($user->hasAnyRole(['Admin', 'SuperAdmin'])) {
            return;
        }
        abort_unless($user->can($permission), 403, 'This action is unauthorized.');
    }
}
