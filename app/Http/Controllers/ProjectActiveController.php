<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use App\Models\Project;
use App\Models\ProjectQuotationPaymentTerm;
use App\Services\DocNumberService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ProjectActiveController extends Controller
{
    public function index(Request $request)
    {
        $this->authorizeProjectActiveAccess();

        $user = $request->user();
        $q = trim((string) $request->query('q', ''));

        $projects = Project::query()
            ->visibleTo($user)
            ->where('status', 'active')
            ->whereHas('wonQuotations')
            ->with([
                'customer:id,name',
                'company:id,alias,name',
                'salesOwner:id,name',
                'latestWonQuotation:id,project_id,number,quotation_date,grand_total,won_at',
            ])
            ->when($q !== '', function ($query) use ($q) {
                $query->where(function ($w) use ($q) {
                    $w->where('code', 'like', "%{$q}%")
                        ->orWhere('name', 'like', "%{$q}%");
                });
            })
            ->orderByDesc('updated_at')
            ->paginate(15)
            ->withQueryString();

        return view('projects.active.index', compact('projects', 'q'));
    }

    public function show(Project $project)
    {
        $this->authorizeProjectActiveAccess();
        $this->authorize('view', $project);

        if ($project->status !== 'active') {
            abort(404);
        }

        $project->load([
            'customer:id,name',
            'company:id,alias,name',
            'salesOwner:id,name',
            'latestWonQuotation' => fn ($query) => $query
                ->select([
                    'id',
                    'project_id',
                    'company_id',
                    'customer_id',
                    'number',
                    'quotation_date',
                    'won_at',
                    'tax_enabled',
                    'tax_percent',
                    'grand_total',
                    'status',
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

        $termIds = $quotation->paymentTerms
            ->pluck('id')
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->values();

        $invoicesByTermId = collect();
        if ($termIds->isNotEmpty()) {
            $invoicesByTermId = Invoice::query()
                ->with(['projectPaymentTerm:id,code,label,percent,sequence'])
                ->whereIn('project_payment_term_id', $termIds->all())
                ->get()
                ->keyBy(fn (Invoice $invoice) => (int) $invoice->project_payment_term_id);
        }

        $termRows = $quotation->paymentTerms->map(function (ProjectQuotationPaymentTerm $term) use ($invoicesByTermId) {
            $invoice = $invoicesByTermId->get((int) $term->id);

            $status = 'Not Invoiced';
            $statusClass = 'bg-secondary-lt text-dark';
            if ($invoice) {
                $isPaid = strtolower((string) $invoice->status) === 'paid' || (bool) $invoice->paid_at;
                $status = $isPaid ? 'Paid' : 'Invoiced';
                $statusClass = $isPaid ? 'bg-green-lt text-green' : 'bg-blue-lt text-blue';
            }

            return (object) [
                'term' => $term,
                'invoice' => $invoice,
                'status' => $status,
                'status_class' => $statusClass,
                'can_create_invoice' => $invoice === null,
            ];
        });

        return view('projects.active.show', [
            'project' => $project,
            'quotation' => $quotation,
            'termRows' => $termRows,
        ]);
    }

    public function createInvoiceFromTerm(Project $project, ProjectQuotationPaymentTerm $term)
    {
        $this->authorizeProjectActiveAccess();
        $this->authorize('view', $project);
        $this->authorize('create', Invoice::class);

        if ($project->status !== 'active') {
            return back()->with('error', 'Project harus berstatus active.');
        }

        $project->load([
            'company:id,alias,name',
            'customer:id,name',
            'latestWonQuotation' => fn ($query) => $query->with([
                'paymentTerms' => fn ($terms) => $terms->orderBy('sequence'),
            ]),
        ]);

        $quotation = $project->latestWonQuotation;
        if (!$quotation) {
            return back()->with('error', 'Project tidak punya BQ won.');
        }

        if ((int) $term->project_quotation_id !== (int) $quotation->id) {
            return back()->with('error', 'Term harus berasal dari latest BQ won.');
        }

        $existing = Invoice::query()
            ->where('project_payment_term_id', $term->id)
            ->exists();
        if ($existing) {
            return back()->with('warning', 'Term ini sudah dibuatkan invoice.');
        }

        $percent = (float) ($term->percent ?? 0);
        if ($percent <= 0) {
            return back()->with('error', 'Percent payment term harus lebih besar dari 0%.');
        }

        $grandTotal = (float) ($quotation->grand_total ?? 0);
        if ($grandTotal <= 0) {
            return back()->with('error', 'Grand total BQ tidak valid.');
        }

        $total = round($grandTotal * $percent / 100, 2);
        if ($total <= 0) {
            return back()->with('error', 'Nilai invoice term tidak valid.');
        }

        $taxPercent = (bool) $quotation->tax_enabled ? (float) ($quotation->tax_percent ?? 0) : 0.0;
        if ($taxPercent > 0) {
            $subtotal = round($total / (1 + ($taxPercent / 100)), 2);
            $taxAmount = round($total - $subtotal, 2);
        } else {
            $subtotal = $total;
            $taxAmount = 0.0;
        }

        $company = $quotation->company ?? $project->company;
        $companyId = (int) ($company?->id ?? 0);
        $customerId = (int) ($quotation->customer_id ?? $project->customer_id ?? 0);
        if ($companyId <= 0 || $customerId <= 0) {
            return back()->with('error', 'Company atau customer project tidak valid.');
        }

        $invoiceDate = now();
        $dueDate = $this->resolveDueDate($term, $invoiceDate);

        $invoice = null;
        DB::transaction(function () use (
            $project,
            $quotation,
            $term,
            $company,
            $companyId,
            $customerId,
            $invoiceDate,
            $dueDate,
            $taxPercent,
            $subtotal,
            $taxAmount,
            $total,
            &$invoice
        ) {
            $invoice = Invoice::create([
                'company_id' => $companyId,
                'customer_id' => $customerId,
                'project_id' => $project->id,
                'project_quotation_id' => $quotation->id,
                'project_payment_term_id' => $term->id,
                'quotation_id' => null,
                'sales_order_id' => null,
                'so_billing_term_id' => null,
                'number' => 'TEMP',
                'date' => $invoiceDate,
                'due_date' => $dueDate,
                'status' => 'draft',
                'invoice_kind' => 'project_term',
                'subtotal' => $subtotal,
                'discount' => 0,
                'tax_percent' => $taxPercent,
                'tax_amount' => $taxAmount,
                'total' => $total,
                'currency' => 'IDR',
                'brand_snapshot' => $quotation->brand_snapshot,
                'notes' => sprintf(
                    'Project %s - BQ %s - Term %s (%s%%)',
                    $project->code ?: $project->name,
                    $quotation->number,
                    $term->code,
                    rtrim(rtrim(number_format((float) $term->percent, 2, '.', ''), '0'), '.')
                ),
                'created_by' => auth()->id(),
            ]);

            $invoice->update([
                'number' => app(DocNumberService::class)->next('invoice', $company, $invoiceDate),
            ]);

            $invoice->lines()->create([
                'description' => sprintf(
                    '%s - %s (%s%%)',
                    $quotation->number,
                    $term->label ?: $term->code,
                    rtrim(rtrim(number_format((float) $term->percent, 2, '.', ''), '0'), '.')
                ),
                'unit' => 'ls',
                'qty' => 1,
                'unit_price' => $subtotal,
                'discount_amount' => 0,
                'line_subtotal' => $subtotal,
                'line_total' => $subtotal,
            ]);
        });

        return redirect()
            ->route('invoices.show', $invoice)
            ->with('success', 'Invoice project berhasil dibuat dari payment term.');
    }

    private function authorizeProjectActiveAccess(): void
    {
        $user = auth()->user();
        abort_unless($user && $user->hasAnyRole(['Admin', 'SuperAdmin', 'Finance']), 403);
    }

    private function resolveDueDate(ProjectQuotationPaymentTerm $term, Carbon $invoiceDate): string
    {
        $trigger = strtolower((string) ($term->due_trigger ?? ''));
        $offsetDays = max(0, (int) ($term->offset_days ?? 0));
        $dayOfMonth = max(1, min(28, (int) ($term->day_of_month ?? 1)));

        return match ($trigger) {
            'after_invoice_days' => $invoiceDate->copy()->addDays($offsetDays)->toDateString(),
            'eom_day' => $invoiceDate->copy()->endOfMonth()->startOfMonth()->addDays($dayOfMonth - 1)->toDateString(),
            'next_month_day' => $invoiceDate->copy()->addMonthNoOverflow()->startOfMonth()->addDays($dayOfMonth - 1)->toDateString(),
            default => $invoiceDate->toDateString(),
        };
    }
}
