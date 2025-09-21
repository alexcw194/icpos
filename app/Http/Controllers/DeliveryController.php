<?php

namespace App\Http\Controllers;

use App\Models\{Delivery, Invoice};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DeliveryController extends Controller
{
    public function index()
    {
        $query = Delivery::with(['company','invoice.customer','quotation'])->latest();

        if ($cid = request('company_id')) {
            $query->where('company_id', $cid);
        }

        $deliveries = $query->paginate(12)->withQueryString();
        return view('deliveries.index', compact('deliveries','cid'));
    }

    public function show(Delivery $delivery)
    {
        $delivery->load(['company','invoice.customer','quotation.items']);
        return view('deliveries.show', compact('delivery'));
    }

    /**
     * Buat Delivery dari Invoice dengan nomor YANG SAMA dengan invoice.
     * Route: POST /invoices/{invoice}/create-delivery
     */
    public function storeFromInvoice(Request $request, Invoice $invoice)
    {
        $request->validate([
            'date'      => 'nullable|date',
            'recipient' => 'nullable|string|max:255',
            'address'   => 'nullable|string',
            'notes'     => 'nullable|string',
        ]);

        // Cegah duplikasi DO untuk invoice yang sama
        if (Delivery::where('invoice_id', $invoice->id)->exists()) {
            $existing = Delivery::where('invoice_id', $invoice->id)->first();
            return redirect()->route('deliveries.show', $existing)
                ->with('info', 'Delivery untuk invoice ini sudah ada.');
        }

        $delivery = null;

        DB::transaction(function () use ($request, $invoice, &$delivery) {
            $delivery = Delivery::create([
                'company_id'   => $invoice->company_id,
                'invoice_id'   => $invoice->id,
                'quotation_id' => $invoice->quotation_id,
                'number'       => $invoice->number, // <— NOMOR SAMA
                'date'         => $request->date ? \Illuminate\Support\Carbon::parse($request->date) : now(),
                'recipient'    => $request->recipient ?? $invoice->customer->name ?? null,
                'address'      => $request->address ?? $invoice->customer->address ?? null,
                'notes'        => $request->notes,
                'brand_snapshot' => $invoice->brand_snapshot, // snapshot ikut invoice
            ]);
        });

        return redirect()->route('deliveries.show', $delivery)->with('success', 'Delivery created!');
    }

    public function destroy(Delivery $delivery)
    {
        $delivery->delete();
        return redirect()->route('deliveries.index')->with('success', 'Delivery deleted!');
    }
}
