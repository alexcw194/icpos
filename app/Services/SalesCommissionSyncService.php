<?php

namespace App\Services;

use App\Models\SalesOrder;
use Illuminate\Support\Facades\DB;

class SalesCommissionSyncService
{
    public function syncSalesOrders(iterable $salesOrderIds): void
    {
        $ids = collect($salesOrderIds)
            ->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => $id > 0)
            ->unique()
            ->values();

        if ($ids->isEmpty()) {
            return;
        }

        $totals = DB::table('sales_commission_note_lines as line')
            ->join('sales_commission_notes as note', 'note.id', '=', 'line.sales_commission_note_id')
            ->whereIn('line.sales_order_id', $ids)
            ->groupBy('line.sales_order_id')
            ->selectRaw('line.sales_order_id, SUM(line.fee_amount) as total_fee, COUNT(*) as total_lines, SUM(CASE WHEN note.status = "paid" THEN 1 ELSE 0 END) as paid_lines, MAX(note.paid_at) as last_paid_at')
            ->get()
            ->keyBy('sales_order_id');

        foreach ($ids as $salesOrderId) {
            $summary = $totals->get($salesOrderId);
            $feeAmount = (float) ($summary->total_fee ?? 0);
            $allPaid = $summary && (int) $summary->total_lines > 0 && (int) $summary->total_lines === (int) $summary->paid_lines;

            SalesOrder::query()
                ->whereKey($salesOrderId)
                ->update([
                    'fee_amount' => $feeAmount,
                    'fee_paid_at' => $allPaid ? $summary->last_paid_at : null,
                ]);
        }
    }
}
