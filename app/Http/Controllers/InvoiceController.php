<?php

namespace App\Http\Controllers;

use App\Models\{Invoice, Quotation, Company, SalesOrder, SalesOrderBillingTerm, Bank};
use App\Services\DocNumberService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Dompdf\Dompdf;
use Dompdf\Options;
use Carbon\Carbon;

class InvoiceController extends Controller
{
    public function index()
    {
        $this->authorize('viewAny', \App\Models\Invoice::class);
        $query = Invoice::with(['customer','company','quotation'])->latest();

        if ($cid = request('company_id')) {
            $query->where('company_id', $cid);
        }

        $invoices  = $query->paginate(12)->withQueryString();
        $companies = Company::orderBy('name')->get(['id','alias','name']);

        return view('invoices.index', compact('invoices','companies','cid'));
    }

    public function show(Invoice $invoice)
    {
        $this->authorize('view', $invoice);
        $invoice->load(['company','customer','quotation.items','lines','salesOrder','billingTerm']);

        $banks = Bank::active()
            ->forCompany($invoice->company_id)
            ->orderBy('code')->orderBy('name')
            ->get();

        $prefScope = ($invoice->tax_percent ?? 0) > 0 ? 'ppn' : 'non_ppn'; // hint preselect

        return view('invoices.show', compact('invoice','banks','prefScope'));
    }

    /**
     * Buat Invoice dari Quotation.
     * Route: POST /quotations/{quotation}/create-invoice
     */
    public function storeFromQuotation(Request $request, Quotation $quotation)
    {
        abort(403, 'Invoice must be created from Sales Order.');
    }

    public function destroy(Invoice $invoice)
    {
        $invoice->delete();
        return redirect()->route('invoices.index')->with('success', 'Invoice deleted!');
    }

    public function createFromSo(SalesOrder $salesOrder)
    {
        abort(403, 'Invoice must be created from Billing Term.');
    }


    public function storeFromSo(Request $request, SalesOrder $salesOrder)
    {
        abort(403, 'Invoice must be created from Billing Term.');
    }

    public function storeFromBillingTerm(Request $request, SalesOrder $salesOrder, SalesOrderBillingTerm $term)
    {
        $this->authorize('create', Invoice::class);

        if ((int) $term->sales_order_id !== (int) $salesOrder->id) {
            abort(404);
        }

        if ($salesOrder->status === 'cancelled') {
            abort(422, 'SO sudah cancelled.');
        }

        if (($term->status ?? 'planned') !== 'planned') {
            abort(422, 'Billing Term sudah dibuatkan invoice.');
        }

        $salesOrder->load(['company', 'customer', 'billingTerms']);

        $topCode = strtoupper((string) $term->top_code);
        if (str_starts_with($topCode, 'R')) {
            $prevTerms = $salesOrder->billingTerms()
                ->where('seq', '<', $term->seq)
                ->get();

            $hasUnpaid = $prevTerms->contains(function ($t) {
                return ($t->status ?? 'planned') !== 'paid';
            });

            if ($hasUnpaid) {
                abort(422, 'Retention hanya dapat ditagih setelah semua term sebelumnya PAID.');
            }
        }

        $percent = (float) $term->percent;
        if ($percent < 0) {
            abort(422, 'Percent tidak boleh negatif.');
        }

        $soTotal = (float) ($salesOrder->contract_value ?? $salesOrder->total ?? 0);
        $amount = round($soTotal * $percent / 100, 2);
        if ($amount < 0) {
            abort(422, 'Invoice amount tidak valid.');
        }

        $billedPct = (float) $salesOrder->billingTerms()
            ->whereIn('status', ['invoiced', 'paid'])
            ->sum('percent');
        $remaining = round($soTotal * max(0, (100 - $billedPct)) / 100, 2);
        if ($amount - $remaining > 0.01) {
            abort(422, 'Invoice amount melebihi sisa nilai SO.');
        }

        $invDate = now();
        $taxPercent = (float) ($salesOrder->tax_percent ?? 0);
        if ($taxPercent > 0) {
            $subtotal = round($amount / (1 + ($taxPercent / 100)), 2);
            $taxAmount = round($amount - $subtotal, 2);
        } else {
            $subtotal = $amount;
            $taxAmount = 0.0;
        }

        $invoice = null;
        DB::transaction(function () use ($salesOrder, $term, $percent, $invDate, $subtotal, $taxPercent, $taxAmount, $amount, &$invoice) {
            $company = $salesOrder->company;
            $invoice = Invoice::create([
                'company_id' => $company->id,
                'customer_id' => $salesOrder->customer_id,
                'sales_order_id' => $salesOrder->id,
                'so_billing_term_id' => $term->id,
                'quotation_id' => $salesOrder->quotation_id,
                'number' => 'TEMP',
                'date' => $invDate,
                'status' => 'draft',
                'subtotal' => $subtotal,
                'discount' => 0,
                'tax_percent' => $taxPercent,
                'tax_amount' => $taxAmount,
                'total' => $amount,
                'currency' => $salesOrder->currency ?? 'IDR',
                'brand_snapshot' => $salesOrder->brand_snapshot,
                'notes' => sprintf('Billing Term %s (%s%%) dari SO %s', $term->top_code, $percent, $salesOrder->so_number),
                'created_by' => auth()->id(),
            ]);

            $invoice->update([
                'number' => DocNumberService::next('invoice', $company, $invDate),
            ]);

            $invoice->lines()->create([
                'sales_order_id' => $salesOrder->id,
                'description' => sprintf('Billing Term %s (%s%%)', $term->top_code, $percent),
                'unit' => 'ls',
                'qty' => 1,
                'unit_price' => $subtotal,
                'discount_amount' => 0,
                'line_subtotal' => $subtotal,
                'line_total' => $subtotal,
            ]);

            $term->update([
                'status' => 'invoiced',
                'invoice_id' => $invoice->id,
            ]);

            $this->syncSoBillingStatus($salesOrder);
        });

        return redirect()->route('invoices.show', $invoice)->with('success', 'Invoice created from Billing Term.');
    }

    protected function authorizePermission(string $permission): void
    {
        abort_unless(auth()->user()?->can($permission), 403, 'This action is unauthorized.');
    }

    public function pdfProforma(\App\Models\Invoice $invoice)
    {
        $this->authorizePermission('invoices.view'); // selaras dengan pola delivery
        $invoice->load(['company','customer','lines','quotation']);

        $options = new Options();
        $options->set('isRemoteEnabled', true);
        $pdf = new Dompdf($options);

        $html = view('invoices.pdf', [
            'invoice' => $invoice,
            'mode'    => 'proforma', // flag
        ])->render();

        $pdf->loadHtml($html);
        $pdf->setPaper('A4', 'portrait');
        $pdf->render();

        $filename = 'proforma-'.($invoice->number ?: 'DRAFT-'.$invoice->id).'.pdf';

        return response($pdf->output(), 200, [
            'Content-Type'        => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.$filename.'"',
        ]);
    }

    public function pdfInvoice(\App\Models\Invoice $invoice)
    {
        $this->authorizePermission('invoices.view');
        $invoice->load(['company','customer','lines','quotation']);

        $options = new Options();
        $options->set('isRemoteEnabled', true);
        $pdf = new Dompdf($options);

        $html = view('invoices.pdf', [
            'invoice' => $invoice,
            'mode'    => 'invoice', // flag
        ])->render();

        $pdf->loadHtml($html);
        $pdf->setPaper('A4', 'portrait');
        $pdf->render();

        $filename = 'invoice-'.($invoice->number ?: 'DRAFT-'.$invoice->id).'.pdf';

        return response($pdf->output(), 200, [
            'Content-Type'        => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.$filename.'"',
        ]);
    }

    public function post(\App\Models\Invoice $invoice, Request $request)
    {
        $this->authorizePermission('invoices.post'); // keep your policy style

        abort_if(strtolower((string)$invoice->status) === 'posted', 422, 'Already posted.');

        $data = $request->validate([
            'due_date' => ['nullable','date'],
            'receipt'  => ['nullable','file','mimes:pdf,jpg,jpeg,png','max:4096'],
            'note'     => ['nullable','string'],
        ]);

        DB::transaction(function () use ($invoice, $data, $request) {
            if (Schema::hasColumn('invoices', 'due_date') && !empty($data['due_date'])) {
                $invoice->due_date = $data['due_date'];
            }
            if ($request->hasFile('receipt') && Schema::hasColumn('invoices', 'receipt_path')) {
                $path = $request->file('receipt')->store('invoice-receipts', 'public');
                $invoice->receipt_path = $path;
            }

            $invoice->status    = 'posted';
            $invoice->posted_at = now();

            // optional: append note into internal_note if you have that column
            if (!empty($data['note']) && Schema::hasColumn('invoices', 'internal_note')) {
                $invoice->internal_note = trim(($invoice->internal_note ? $invoice->internal_note."\n" : '').$data['note']);
            }

            $invoice->save();
        });

        return back()->with('success', 'Invoice posted.');
    }

    /**
     * After posting: update due date or upload/replace TT (tanda terima)
     */
    public function updateReceipt(\App\Models\Invoice $invoice, Request $request)
    {
        $this->authorizePermission('invoices.update');
        abort_unless(strtolower((string)$invoice->status) === 'posted', 422, 'Only posted invoices can be updated here.');

        $data = $request->validate([
            'due_date' => ['nullable','date'],
            'receipt'  => ['nullable','file','mimes:pdf,jpg,jpeg,png','max:4096'],
        ]);

        DB::transaction(function () use ($invoice, $data, $request) {
            if (!empty($data['due_date'])) {
                $invoice->due_date = $data['due_date'];
            }
            if ($request->hasFile('receipt')) {
                $path = $request->file('receipt')->store('invoice-receipts', 'public');
                $invoice->receipt_path = $path;
            }
            $invoice->save();
        });

        return back()->with('success', 'Due date / tanda terima updated.');
    }

    /**
     * List of posted invoices without TT (for follow-up).
     */
    public function ttPendingIndex(Request $request)
    {
        $this->authorizePermission('invoices.view');

        $q = \App\Models\Invoice::query()
            ->where('status', 'posted')
            ->whereNull('receipt_path')
            ->orderByDesc('date');

        $invoices = $q->paginate(25);
        return view('invoices.tt-pending', compact('invoices'));
    }

    public function markPaid(Invoice $invoice, Request $request)
    {
        // Guardrail: permission khusus pembayaran
        $this->authorizePermission('invoices.pay');

        // Hanya boleh close dari posted/paid; block draft/cancelled
        abort_unless(in_array(strtolower((string) $invoice->status), ['posted', 'paid']), 422, 'Invoice must be posted.');

        // Validation baseline: paid_at, amount wajib; bank opsional via master
        $data = $request->validate([
            'paid_at'       => ['required', 'date'],
            'paid_amount'   => ['required', 'numeric', 'min:0.01'],
            // gunakan salah satu: paid_bank_id dari master OR paid_bank (free text)
            'paid_bank_id'  => [
                'required',
                'integer',
                Rule::exists('banks', 'id')->where(function ($query) use ($invoice) {
                    $query->where('company_id', $invoice->company_id)
                        ->where('is_active', true);
                }),
            ],
            'paid_bank'     => ['nullable', 'string', 'max:100'], // fallback jika belum pakai master
            'paid_ref'      => ['nullable', 'string', 'max:150'],
            'payment_notes' => ['nullable', 'string'],
        ]);

        DB::transaction(function () use ($invoice, $data) {
            // Normalisasi tanggal
            $paidAt = Carbon::parse($data['paid_at']);
            $bank = Bank::query()
                ->where('id', $data['paid_bank_id'])
                ->where('company_id', $invoice->company_id)
                ->where('is_active', true)
                ->firstOrFail();

            // Persist; tidak menyentuh due_date/receipt
            $invoice->paid_at       = $paidAt;
            $invoice->paid_amount   = $data['paid_amount'];
            $invoice->paid_ref      = $data['paid_ref'] ?? null;
            $invoice->payment_notes = $data['payment_notes'] ?? null;

            // Support kedua skenario bank (master / free text)
            if (schema()->hasColumn('invoices', 'paid_bank_id')) {
                $invoice->paid_bank_id = $bank->id;
            }
            if (schema()->hasColumn('invoices', 'paid_bank')) {
                $invoice->paid_bank = $bank->display_label ?: $bank->name;
            }

            // Idempotent: set status ke PAID (boleh update data bayar saat status sudah PAID)
            $invoice->status = 'paid';

            $invoice->save();

            if ($invoice->so_billing_term_id) {
                $term = SalesOrderBillingTerm::find($invoice->so_billing_term_id);
                if ($term) {
                    $term->status = 'paid';
                    $term->save();
                    if ($term->salesOrder) {
                        $this->syncSoBillingStatus($term->salesOrder);
                    }
                }
            }
        });

        return back()->with('success', 'Invoice marked as PAID.');
    }

    private function syncSoBillingStatus(SalesOrder $salesOrder): void
    {
        if (in_array($salesOrder->status, ['cancelled', 'closed'], true)) {
            return;
        }

        $terms = $salesOrder->billingTerms()->get(['status']);
        if ($terms->isEmpty()) {
            return;
        }

        $allPaid = $terms->every(fn ($t) => $t->status === 'paid');
        if ($allPaid) {
            $salesOrder->status = 'fully_billed';
            $salesOrder->save();
            return;
        }

        $anyBilled = $terms->contains(fn ($t) => in_array($t->status, ['invoiced', 'paid'], true));
        if ($anyBilled) {
            $salesOrder->status = 'partially_billed';
            $salesOrder->save();
            return;
        }
    }
}

/**
 * Mini helper agar aman panggil pengecekan kolom tanpa import Schema di atas.
 */
if (!function_exists('schema')) {
    function schema() {
        return \Illuminate\Support\Facades\Schema::getFacadeRoot();
    }
}
