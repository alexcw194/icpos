<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\SalesOrder;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class PaymentTermEngine
{
    public function handle(SalesOrder $salesOrder, string $eventType, array $baseDates = []): array
    {
        $snapshot = $salesOrder->payment_term_snapshot;
        if (!is_array($snapshot)) {
            return [];
        }

        $schedules = $snapshot['schedules'] ?? [];
        if (!is_array($schedules) || count($schedules) === 0) {
            return [];
        }

        $eventTriggers = [
            'so_confirmed' => ['on_so'],
            'delivery_posted' => ['on_delivery'],
            'invoice_issued' => ['on_invoice', 'after_invoice_days', 'end_of_month'],
        ];

        if (!isset($eventTriggers[$eventType])) {
            return [];
        }

        $allowedTriggers = $eventTriggers[$eventType];
        $soTotal = (float) ($salesOrder->contract_value ?? $salesOrder->total ?? 0);
        if ($soTotal <= 0) {
            return [];
        }

        $soDate = $baseDates['so_date'] ?? $salesOrder->order_date ?? now()->toDateString();
        $deliveryDate = $baseDates['delivery_date'] ?? null;
        $invoiceDate = $baseDates['invoice_date'] ?? now()->toDateString();

        $rows = collect($schedules)->sortBy('sequence')->values();
        $maxSeq = $rows->max('sequence') ?? $rows->count();

        $created = [];

        foreach ($rows as $idx => $row) {
            $trigger = $row['due_trigger'] ?? null;
            if (!in_array($trigger, $allowedTriggers, true)) {
                continue;
            }

            $seq = (int) ($row['sequence'] ?? ($idx + 1));
            if ($seq <= 0) {
                $seq = $idx + 1;
            }

            $exists = Invoice::query()
                ->where('sales_order_id', $salesOrder->id)
                ->where('payment_schedule_seq', $seq)
                ->exists();
            if ($exists) {
                continue;
            }

            $portionType = $row['portion_type'] ?? 'percent';
            $portionValue = (float) ($row['portion_value'] ?? 0);
            $amount = 0.0;
            if ($portionType === 'percent') {
                $amount = round($soTotal * $portionValue / 100, 2);
            } elseif ($portionType === 'fixed') {
                $amount = round($portionValue, 2);
            }
            if ($amount <= 0) {
                continue;
            }

            $dueDate = $this->resolveDueDate($trigger, $row, $soDate, $deliveryDate, $invoiceDate);
            $kind = ($seq === (int) $maxSeq) ? 'final' : 'dp';

            $taxPercent = (float) ($salesOrder->tax_percent ?? 0);
            if ($taxPercent > 0) {
                $subtotal = round($amount / (1 + ($taxPercent / 100)), 2);
                $taxAmount = round($amount - $subtotal, 2);
            } else {
                $subtotal = $amount;
                $taxAmount = 0.0;
            }

            $invoice = null;
            DB::transaction(function () use ($salesOrder, $seq, $row, $amount, $dueDate, $kind, $taxPercent, $subtotal, $taxAmount, $invoiceDate, &$invoice) {
                $company = $salesOrder->company;
                $invoice = Invoice::create([
                    'company_id' => $company->id,
                    'customer_id' => $salesOrder->customer_id,
                    'sales_order_id' => $salesOrder->id,
                    'quotation_id' => $salesOrder->quotation_id,
                    'so_billing_term_id' => null,
                    'payment_schedule_seq' => $seq,
                    'payment_schedule_meta' => [
                        'seq' => $seq,
                        'portion_type' => $row['portion_type'] ?? null,
                        'portion_value' => $row['portion_value'] ?? null,
                        'due_trigger' => $row['due_trigger'] ?? null,
                        'offset_days' => $row['offset_days'] ?? null,
                        'specific_day' => $row['specific_day'] ?? null,
                        'notes' => $row['notes'] ?? null,
                    ],
                    'number' => 'TEMP',
                    'date' => $invoiceDate,
                    'due_date' => $dueDate,
                    'status' => 'draft',
                    'invoice_kind' => $kind,
                    'subtotal' => $subtotal,
                    'discount' => 0,
                    'tax_percent' => $taxPercent,
                    'tax_amount' => $taxAmount,
                    'total' => $amount,
                    'currency' => $salesOrder->currency ?? 'IDR',
                    'brand_snapshot' => $salesOrder->brand_snapshot,
                    'notes' => sprintf('Payment Term %s (Schedule #%s)', $salesOrder->payment_term_snapshot['code'] ?? '-', $seq),
                    'created_by' => auth()->id(),
                ]);

                $invoice->update([
                    'number' => \App\Services\DocNumberService::next('invoice', $company, Carbon::parse($invoiceDate)),
                ]);

                $invoice->lines()->create([
                    'sales_order_id' => $salesOrder->id,
                    'description' => sprintf('Payment Term %s (Schedule #%s)', $salesOrder->payment_term_snapshot['code'] ?? '-', $seq),
                    'unit' => 'ls',
                    'qty' => 1,
                    'unit_price' => $subtotal,
                    'discount_amount' => 0,
                    'line_subtotal' => $subtotal,
                    'line_total' => $subtotal,
                ]);
            });

            if ($invoice) {
                $created[] = $invoice;
            }
        }

        return $created;
    }

    private function resolveDueDate(string $trigger, array $row, $soDate, $deliveryDate, $invoiceDate): string
    {
        switch ($trigger) {
            case 'on_so':
                return Carbon::parse($soDate)->toDateString();
            case 'on_delivery':
                return Carbon::parse($deliveryDate ?: now())->toDateString();
            case 'on_invoice':
                return Carbon::parse($invoiceDate)->toDateString();
            case 'after_invoice_days':
                $days = (int) ($row['offset_days'] ?? 0);
                return Carbon::parse($invoiceDate)->addDays($days)->toDateString();
            case 'end_of_month':
                $day = (int) ($row['specific_day'] ?? 1);
                $base = Carbon::parse($invoiceDate)->addMonthNoOverflow()->startOfMonth();
                return $base->addDays(max($day, 1) - 1)->toDateString();
            default:
                return Carbon::parse($invoiceDate)->toDateString();
        }
    }
}
