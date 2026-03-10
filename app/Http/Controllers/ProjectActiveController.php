<?php

namespace App\Http\Controllers;

use App\Models\BillingDocument;
use App\Models\Invoice;
use App\Models\Project;
use App\Models\SalesOrder;
use App\Models\SalesOrderBillingTerm;
use App\Services\ProjectSalesOrderBootstrapService;
use App\Services\SalesOrderExecutionVarianceService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ProjectActiveController extends Controller
{
    public function __construct(
        private readonly ProjectSalesOrderBootstrapService $projectSoBootstrap,
        private readonly SalesOrderExecutionVarianceService $executionVariance
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
                ->map(fn ($group) => $group->first());
        }

        $invoicesByTerm = collect();
        if ($termIds->isNotEmpty()) {
            $invoicesByTerm = Invoice::query()
                ->where('sales_order_id', $salesOrder->id)
                ->whereIn('so_billing_term_id', $termIds->all())
                ->orderByDesc('id')
                ->get()
                ->groupBy(fn (Invoice $invoice) => (int) $invoice->so_billing_term_id)
                ->map(fn ($group) => $group->first());
        }

        $termRows = $salesOrder->billingTerms->map(function (SalesOrderBillingTerm $term) use ($billingByTerm, $invoicesByTerm) {
            $billing = $billingByTerm->get((int) $term->id);
            $invoice = $invoicesByTerm->get((int) $term->id);

            $status = 'Not Billed';
            $statusClass = 'bg-secondary-lt text-dark';

            if ($invoice) {
                $isPaid = strtolower((string) $invoice->status) === 'paid' || (bool) $invoice->paid_at;
                $status = $isPaid ? 'Paid' : 'Invoiced';
                $statusClass = $isPaid ? 'bg-green-lt text-green' : 'bg-blue-lt text-blue';
            } elseif ($billing) {
                $isProforma = strtolower((string) ($billing->mode ?? '')) === 'proforma'
                    && strtolower((string) ($billing->status ?? '')) === 'sent'
                    && empty($billing->inv_number);
                if ($isProforma) {
                    $status = 'Proforma';
                    $statusClass = 'bg-purple-lt text-purple';
                } else {
                    $status = 'Draft';
                    $statusClass = 'bg-yellow-lt text-dark';
                }
            }

            $hasActiveBilling = $billing && in_array((string) $billing->status, ['draft', 'sent'], true);

            return (object) [
                'term' => $term,
                'billing' => $billing,
                'invoice' => $invoice,
                'status' => $status,
                'status_class' => $statusClass,
                'can_create_billing_draft' => !$hasActiveBilling && $invoice === null,
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

    public function createBillingDraftFromTerm(Project $project, SalesOrderBillingTerm $term)
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

        $activeDraft = BillingDocument::query()
            ->where('sales_order_id', $salesOrder->id)
            ->where('so_billing_term_id', $term->id)
            ->whereIn('status', ['draft', 'sent'])
            ->whereNull('inv_number')
            ->orderByDesc('id')
            ->first();
        if ($activeDraft) {
            return redirect()
                ->route('billings.show', $activeDraft)
                ->with('ok', 'Billing draft untuk term ini sudah ada.');
        }

        $invoiceExists = Invoice::query()
            ->where('sales_order_id', $salesOrder->id)
            ->where('so_billing_term_id', $term->id)
            ->exists();
        if ($invoiceExists) {
            return back()->with('warning', 'Term ini sudah memiliki invoice.');
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

        $taxPercent = (float) ($salesOrder->tax_percent ?? 0);
        if ($taxPercent > 0) {
            $subtotal = round($total / (1 + ($taxPercent / 100)), 2);
            $taxAmount = round($total - $subtotal, 2);
        } else {
            $subtotal = $total;
            $taxAmount = 0.0;
        }

        $billing = DB::transaction(function () use ($salesOrder, $term, $subtotal, $taxPercent, $taxAmount, $total, $project) {
            $billing = BillingDocument::create([
                'sales_order_id' => $salesOrder->id,
                'so_billing_term_id' => $term->id,
                'company_id' => $salesOrder->company_id,
                'customer_id' => $salesOrder->customer_id,
                'status' => 'draft',
                'mode' => null,
                'subtotal' => $subtotal,
                'discount_amount' => 0,
                'tax_percent' => $taxPercent,
                'tax_amount' => $taxAmount,
                'total' => $total,
                'currency' => $salesOrder->currency ?? 'IDR',
                'notes' => sprintf(
                    'Project %s - Term %s (%s%%)',
                    $project->code ?: $project->name,
                    $term->top_code,
                    rtrim(rtrim(number_format((float) $term->percent, 2, '.', ''), '0'), '.')
                ),
                'created_by' => auth()->id(),
                'updated_by' => auth()->id(),
            ]);

            $billing->lines()->create([
                'sales_order_line_id' => null,
                'position' => 1,
                'name' => sprintf('Billing Term %s', $term->top_code),
                'description' => sprintf(
                    '%s - %s',
                    $salesOrder->so_number,
                    trim((string) ($term->note ?: $term->top_code))
                ),
                'unit' => 'ls',
                'qty' => 1,
                'unit_price' => $subtotal,
                'discount_type' => 'amount',
                'discount_value' => 0,
                'discount_amount' => 0,
                'line_subtotal' => $subtotal,
                'line_total' => $subtotal,
            ]);

            return $billing;
        });

        return redirect()
            ->route('billings.show', $billing)
            ->with('success', 'Billing draft project berhasil dibuat dari payment term.');
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
