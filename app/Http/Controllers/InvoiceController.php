<?php

namespace App\Http\Controllers;

use App\Models\{Invoice, Quotation, Company, InvoiceLine, SalesOrder};
use App\Services\DocNumberService;
use App\Services\InvoiceBuilderFromSO;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Carbon;

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
        $invoice->load(['company','customer','quotation.items','lines']);
        return view('invoices.show', compact('invoice'));
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
}
