<?php

namespace App\Http\Controllers;

use App\Models\{Invoice, Quotation, Company, InvoiceLine, SalesOrder, Bank};
use App\Services\DocNumberService;
use App\Services\InvoiceBuilderFromSO;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Validation\Rule;
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
        $invoice->load(['company','customer','quotation.items','lines']);
        $banks = Bank::active()->orderBy('name')->get(['id','code','name','account_no']);
        return view('invoices.show', compact('invoice','banks'));
    }

    /**
     * Buat Invoice dari Quotation.
     * Route: POST /quotations/{quotation}/create-invoice
     */
    public function storeFromQuotation(Request $request, Quotation $quotation)
    {
        $request->validate(['date' => 'nullable|date']);
        $company = $quotation->company;
        $invDate = $request->filled('date') ? Carbon::parse($request->date) : now();


        if (Invoice::where('quotation_id', $quotation->id)->exists()) {
        return redirect()->route('invoices.index')->with('warning', 'Quotation ini sudah pernah dibuatkan invoice.');
        }


        $invoice = null;
        DB::transaction(function () use ($quotation, $company, $invDate, &$invoice) {
            $invoice = Invoice::create([
            'company_id' => $company->id,
            'customer_id' => $quotation->customer_id,
            'quotation_id' => $quotation->id,
            'number' => 'TEMP',
            'date' => $invDate,
            'status' => 'draft',
            'subtotal' => $quotation->lines_subtotal ?? $quotation->subtotal ?? 0,
            'discount' => $quotation->total_discount_amount ?? $quotation->discount ?? 0,
            'tax_percent' => $quotation->tax_percent,
            'tax_amount' => $quotation->tax_amount,
            'total' => $quotation->total,
            'currency' => $quotation->currency ?? 'IDR',
            'brand_snapshot' => $quotation->brand_snapshot,
        ]);


        $invoice->update(['number' => DocNumberService::next('invoice', $company, $invDate)]);


        // === Materialize lines from Quotation ===
        foreach ($quotation->lines as $ql) {
            $lineSubtotal = ($ql->qty ?? 0) * ($ql->unit_price ?? 0);
            $lineTotal = $lineSubtotal - ($ql->discount_amount ?? 0);
            InvoiceLine::create([
            'invoice_id' => $invoice->id,
            'quotation_id' => $quotation->id,
            'quotation_line_id' => $ql->id,
            'item_id' => $ql->item_id,
            'item_variant_id' => $ql->item_variant_id,
            'description' => trim(($ql->name ?? 'Item').' '.($ql->description ?? '')),
            'unit' => $ql->unit ?? 'pcs',
            'qty' => $ql->qty ?? 0,
            'unit_price' => $ql->unit_price ?? 0,
            'discount_amount' => $ql->discount_amount ?? 0,
            'line_subtotal' => $lineSubtotal,
            'line_total' => $lineTotal,
            'snapshot_json' => null,
            ]);
        }
        });


        return redirect()->route('invoices.show', $invoice)->with('success', 'Invoice created!');
    }

    public function destroy(Invoice $invoice)
    {
        $invoice->delete();
        return redirect()->route('invoices.index')->with('success', 'Invoice deleted!');
    }

    public function createFromSo(SalesOrder $salesOrder)
    {
        $this->authorize('create', Invoice::class);
        $salesOrder->load(['company','customer','lines.item','lines.variant']);
        // return view with matrix of remaining billable qty per line
        return view('invoices.create_from_so', ['so' => $salesOrder]);
    }


    public function storeFromSo(Request $request, SalesOrder $salesOrder, InvoiceBuilderFromSO $builder)
    {
        $this->authorize('create', Invoice::class);
        $validated = $request->validate([
        'date' => ['required','date'],
        'due_date' => ['nullable','date','after_or_equal:date'],
        'tax_percent' => ['nullable','numeric','min:0'],
        'notes' => ['nullable','string'],
        'lines' => ['required','array','min:1'],
        'lines.*.sales_order_line_id' => ['required','integer'],
        'lines.*.qty' => ['required','numeric','min:0.0001'],
        'lines.*.unit_price' => ['nullable','numeric','min:0'],
        'lines.*.discount_amount' => ['nullable','numeric','min:0'],
        ]);


        $invoice = $builder->build($salesOrder, $validated);
        return redirect()->route('invoices.show', $invoice)->with('success', 'Invoice created from Sales Order.');
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
            'paid_bank_id'  => ['nullable', 'integer', 'exists:banks,id'],
            'paid_bank'     => ['nullable', 'string', 'max:100'], // fallback jika belum pakai master
            'paid_ref'      => ['nullable', 'string', 'max:150'],
            'payment_notes' => ['nullable', 'string'],
        ]);

        DB::transaction(function () use ($invoice, $data) {
            // Normalisasi tanggal
            $paidAt = Carbon::parse($data['paid_at']);

            // Persist; tidak menyentuh due_date/receipt
            $invoice->paid_at       = $paidAt;
            $invoice->paid_amount   = $data['paid_amount'];
            $invoice->paid_ref      = $data['paid_ref'] ?? null;
            $invoice->payment_notes = $data['payment_notes'] ?? null;

            // Support kedua skenario bank (master / free text)
            if (array_key_exists('paid_bank_id', $data)) {
                // jika kolom tersedia di schema
                if (schema()->hasColumn('invoices', 'paid_bank_id')) {
                    $invoice->paid_bank_id = $data['paid_bank_id'];
                }
            }
            if (!empty($data['paid_bank']) && schema()->hasColumn('invoices', 'paid_bank')) {
                $invoice->paid_bank = $data['paid_bank'];
            }

            // Idempotent: set status ke PAID (boleh update data bayar saat status sudah PAID)
            $invoice->status = 'paid';

            $invoice->save();
        });

        return back()->with('success', 'Invoice marked as PAID.');
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