<?php

namespace App\Services;

use App\Models\{Invoice, InvoiceLine, SalesOrder};
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;

class InvoiceBuilderFromSO
{
    /**
     * Build & persist Invoice from a Sales Order + validated request payload.
     *
     * @param  \App\Models\SalesOrder  $salesOrder   SO sumber
     * @param  array                   $data         payload ter-validasi dari form:
     *   - date (Y-m-d), due_date?, tax_percent?, notes?
     *   - lines: [
     *       ['sales_order_line_id','qty','unit_price'?, 'discount_amount'?],
     *     ]
     * @return \App\Models\Invoice
     */
    public function build(SalesOrder $salesOrder, array $data): Invoice
    {
        return DB::transaction(function () use ($salesOrder, $data) {
            $company = $salesOrder->company;
            $invDate = Carbon::parse($data['date']);

            // 1) Buat header dulu (pakai number sementara)
            $invoice = Invoice::create([
                'company_id'   => $company->id,
                'customer_id'  => $salesOrder->customer_id,
                'sales_order_id' => $salesOrder->id,
                'quotation_id' => $salesOrder->quotation_id,
                'number'       => 'TEMP',
                'date'         => $invDate,
                'due_date'     => Arr::get($data, 'due_date'),
                'status'       => 'draft',
                'notes'        => Arr::get($data, 'notes'),
                'tax_percent'  => (float) Arr::get($data, 'tax_percent', 0),
                // nilai uang akan dihitung setelah lines dibuat
                'subtotal'     => 0,
                'discount'     => 0,
                'tax_amount'   => 0,
                'total'        => 0,
                'currency'     => $salesOrder->currency ?? 'IDR',
                'brand_snapshot' => $salesOrder->brand_snapshot,
                'created_by'   => auth()->id(),
            ]);

            // 2) Generate nomor final
            $invoice->update([
                'number' => DocNumberService::next('invoice', $company, $invDate),
            ]);

            // 3) Materialize lines dari payload (hanya yang qty > 0)
            $subtotal = 0.0;

            // Petakan SO lines supaya mudah ambil unit/item/variant
            $soLines = $salesOrder->lines()->get()->keyBy('id');

            foreach ((array) $data['lines'] as $line) {
                $soLineId = (int) $line['sales_order_line_id'];
                $qty      = (float) $line['qty'];
                if ($qty <= 0) {
                    continue;
                }

                $unitPrice = isset($line['unit_price'])
                    ? (float) $line['unit_price']
                    : (float) ($soLines[$soLineId]->unit_price ?? 0);

                $discAmount = (float) Arr::get($line, 'discount_amount', 0);
                $lineSubtotal = $qty * $unitPrice;
                $lineTotal    = max($lineSubtotal - $discAmount, 0);

                $src = $soLines[$soLineId] ?? null;

                InvoiceLine::create([
                    'invoice_id'          => $invoice->id,
                    'sales_order_id'      => $salesOrder->id,
                    'sales_order_line_id' => $soLineId,
                    'item_id'             => $src?->item_id,
                    'item_variant_id'     => $src?->item_variant_id,
                    'description'         => trim(($src?->name ?? 'Item').' '.($src?->description ?? '')),
                    'unit'                => $src?->unit ?? 'pcs',
                    'qty'                 => $qty,
                    'unit_price'          => $unitPrice,
                    'discount_amount'     => $discAmount,
                    'line_subtotal'       => $lineSubtotal,
                    'line_total'          => $lineTotal,
                ]);

                $subtotal += $lineTotal;
            }

            // 4) Hitung pajak & total
            $taxPercent = max((float) Arr::get($data, 'tax_percent', 0), 0);
            $taxAmount  = round($subtotal * $taxPercent / 100, 2);
            $grand      = $subtotal + $taxAmount;

            $invoice->forceFill([
                'subtotal'   => $subtotal,
                'discount'   => 0,          // tidak pakai diskon total di flow ini
                'tax_percent'=> $taxPercent,
                'tax_amount' => $taxAmount,
                'total'      => $grand,
            ])->save();

            return $invoice;
        });
    }
}
