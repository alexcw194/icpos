<?php

namespace App\Services;

use App\Models\Delivery;
use App\Models\SalesOrder;
use Illuminate\Support\Facades\DB;

class AutoDeliveryDraftFromSoService
{
    /**
     * @return array{delivery: Delivery|null, created: bool, reused: bool}
     */
    public function ensureForSalesOrder(SalesOrder $salesOrder): array
    {
        $salesOrder->loadMissing(['company', 'customer', 'lines']);

        $existingDraft = Delivery::query()
            ->where('sales_order_id', $salesOrder->id)
            ->where('status', Delivery::STATUS_DRAFT)
            ->orderByDesc('id')
            ->first();

        if ($existingDraft) {
            return [
                'delivery' => $existingDraft,
                'created' => false,
                'reused' => true,
            ];
        }

        $linePayloads = [];
        foreach ($salesOrder->lines as $line) {
            $ordered = (float) ($line->qty_ordered ?? 0);
            $delivered = (float) ($line->qty_delivered ?? 0);
            $remaining = max(0.0, $ordered - $delivered);

            if ($remaining <= 0) {
                continue;
            }

            $linePayloads[] = [
                'quotation_line_id' => null,
                'sales_order_line_id' => $line->id,
                'item_id' => $line->item_id,
                'item_variant_id' => $line->item_variant_id,
                'description' => $line->po_item_name ?: ($line->description ?: $line->name),
                'unit' => $line->unit,
                'qty' => $remaining,
                'qty_requested' => $ordered,
                'price_snapshot' => (float) ($line->unit_price ?? 0),
                'qty_backordered' => 0,
                'line_notes' => null,
            ];
        }

        if (empty($linePayloads)) {
            return [
                'delivery' => null,
                'created' => false,
                'reused' => false,
            ];
        }

        $delivery = DB::transaction(function () use ($salesOrder, $linePayloads) {
            $delivery = Delivery::create([
                'company_id' => $salesOrder->company_id,
                'customer_id' => $salesOrder->customer_id,
                'warehouse_id' => null,
                'invoice_id' => null,
                'quotation_id' => $salesOrder->quotation_id,
                'sales_order_id' => $salesOrder->id,
                'number' => null,
                'status' => Delivery::STATUS_DRAFT,
                'date' => now()->toDateString(),
                'reference' => $salesOrder->so_number ?? $salesOrder->customer_po_number,
                'recipient' => $salesOrder->customer?->name,
                'address' => $salesOrder->ship_to ?: $salesOrder->customer?->address,
                'notes' => $salesOrder->notes,
                'brand_snapshot' => [
                    'name' => $salesOrder->company?->name,
                    'alias' => $salesOrder->company?->alias,
                    'address' => $salesOrder->company?->address,
                    'tax_id' => $salesOrder->company?->tax_id,
                    'phone' => $salesOrder->company?->phone,
                    'email' => $salesOrder->company?->email,
                    'logo' => $salesOrder->company?->logo_path,
                ],
                'created_by' => auth()->id(),
            ]);

            if (!empty($linePayloads)) {
                $delivery->lines()->createMany($linePayloads);
            }

            return $delivery;
        });

        return [
            'delivery' => $delivery,
            'created' => true,
            'reused' => false,
        ];
    }
}
