<?php

namespace App\Services;

use App\Models\BillingDocument;
use App\Models\Invoice;
use Carbon\Carbon;
use Carbon\CarbonInterface;

class BillingInvoiceSyncService
{
    /**
     * Sync one issued billing document into invoices table.
     *
     * @param array{
     *   invoice_number?: string|null,
     *   issue_date?: \DateTimeInterface|string|null,
     *   posted_at?: \DateTimeInterface|string|null,
     *   sync_lines?: bool,
     *   preserve_paid?: bool,
     * } $options
     */
    public function sync(BillingDocument $billing, array $options = []): Invoice
    {
        $billing->loadMissing(['salesOrder', 'lines']);

        $invoiceNumber = trim((string) ($options['invoice_number'] ?? $billing->inv_number ?? ''));
        if ($invoiceNumber === '') {
            throw new \InvalidArgumentException('Invoice number is required for billing sync.');
        }

        $issueDate = $this->parseDate($options['issue_date'] ?? $billing->invoice_date ?? $billing->issued_at ?? now());
        $postedAt = $this->parseDateTime($options['posted_at'] ?? $billing->issued_at ?? $billing->locked_at ?? now());
        $syncLines = (bool) ($options['sync_lines'] ?? true);
        $preservePaid = (bool) ($options['preserve_paid'] ?? true);

        $invoice = Invoice::query()
            ->where('company_id', $billing->company_id)
            ->where('number', $invoiceNumber)
            ->first();

        $payload = [
            'company_id' => $billing->company_id,
            'customer_id' => $billing->customer_id,
            'quotation_id' => $billing->salesOrder?->quotation_id,
            'sales_order_id' => $billing->sales_order_id,
            'number' => $invoiceNumber,
            'date' => $issueDate->toDateString(),
            'status' => 'posted',
            'posted_at' => $postedAt,
            'subtotal' => (float) $billing->subtotal,
            'discount' => (float) $billing->discount_amount,
            'tax_percent' => (float) $billing->tax_percent,
            'tax_amount' => (float) $billing->tax_amount,
            'total' => (float) $billing->total,
            'currency' => $billing->currency ?: 'IDR',
            'brand_snapshot' => $billing->salesOrder?->brand_snapshot,
            'notes' => $billing->notes,
        ];

        if ($invoice) {
            if ($preservePaid && strtolower((string) $invoice->status) === 'paid') {
                unset($payload['status'], $payload['posted_at']);
            }
            $invoice->fill($payload);
            $invoice->save();
        } else {
            $invoice = Invoice::create($payload + [
                'created_by' => auth()->id(),
            ]);
        }

        if ($syncLines) {
            $invoice->lines()->delete();
            if ($billing->lines->isNotEmpty()) {
                $invoice->lines()->createMany($billing->lines->map(function ($line) use ($billing) {
                    return [
                        'sales_order_id' => $billing->sales_order_id,
                        'sales_order_line_id' => $line->sales_order_line_id,
                        'description' => $line->description ?: $line->name,
                        'unit' => $line->unit ?: 'pcs',
                        'qty' => (float) $line->qty,
                        'unit_price' => (float) $line->unit_price,
                        'discount_amount' => (float) $line->discount_amount,
                        'line_subtotal' => (float) $line->line_subtotal,
                        'line_total' => (float) $line->line_total,
                    ];
                })->all());
            }
        }

        return $invoice;
    }

    private function parseDate($value): CarbonInterface
    {
        if ($value instanceof CarbonInterface) {
            return $value;
        }

        return Carbon::parse($value);
    }

    private function parseDateTime($value): CarbonInterface
    {
        if ($value instanceof CarbonInterface) {
            return $value;
        }

        return Carbon::parse($value);
    }
}
