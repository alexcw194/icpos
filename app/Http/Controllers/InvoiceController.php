<?php

namespace App\Http\Controllers;

use App\Models\{Invoice, Quotation, Company};
use App\Services\DocNumberService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Carbon;

class InvoiceController extends Controller
{
    public function index()
    {
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
        $invoice->load(['company','customer','quotation.items']);
        return view('invoices.show', compact('invoice'));
    }

    /**
     * Buat Invoice dari Quotation.
     * Route: POST /quotations/{quotation}/create-invoice
     */
    public function storeFromQuotation(Request $request, Quotation $quotation)
    {
        $request->validate([
            'date' => 'nullable|date',
        ]);

        $company = $quotation->company;
        $invDate = $request->filled('date') ? Carbon::parse($request->date) : now();

        // Kalau sudah pernah ada invoice untuk quotation ini, opsional: tolak double
        if (Invoice::where('quotation_id', $quotation->id)->exists()) {
            return redirect()
                ->route('invoices.index')
                ->with('warning', 'Quotation ini sudah pernah dibuatkan invoice.');
        }

        $invoice = null;

        DB::transaction(function () use ($quotation, $company, $invDate, &$invoice) {
            $invoice = Invoice::create([
                'company_id'   => $company->id,
                'customer_id'  => $quotation->customer_id,
                'quotation_id' => $quotation->id,
                'number'       => 'TEMP',
                'date'         => $invDate,
                'status'       => 'draft',
                'subtotal'     => $quotation->subtotal,
                'discount'     => $quotation->discount,
                'tax_percent'  => $quotation->tax_percent,
                'tax_amount'   => $quotation->tax_amount,
                'total'        => $quotation->total,
                'currency'     => $quotation->currency,
                'brand_snapshot' => $quotation->brand_snapshot, // snapshot ikut quotation
            ]);

            $invoice->update([
                'number' => DocNumberService::next('invoice', $company, $invDate),
            ]);

            // Opsional: ubah status quotation → 'invoiced'
            try { $quotation->update(['status' => 'invoiced']); } catch (\Throwable $e) {}
        });

        return redirect()->route('invoices.show', $invoice)->with('success', 'Invoice created!');
    }

    public function destroy(Invoice $invoice)
    {
        $invoice->delete();
        return redirect()->route('invoices.index')->with('success', 'Invoice deleted!');
    }
}
