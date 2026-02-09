<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\SalesOrder;
use Illuminate\Support\Facades\DB;

class SalesOrderStatusSyncService
{
    public function syncById(int $salesOrderId): ?string
    {
        $so = SalesOrder::query()->find($salesOrderId);
        if (!$so) {
            return null;
        }

        return $this->sync($so);
    }

    public function sync(SalesOrder $salesOrder): string
    {
        $nextStatus = $this->preview($salesOrder);

        if ($salesOrder->status !== $nextStatus) {
            $salesOrder->status = $nextStatus;
            $salesOrder->save();
        }

        return $nextStatus;
    }

    public function preview(SalesOrder $salesOrder): string
    {
        if ($salesOrder->status === 'cancelled') {
            return $salesOrder->status;
        }

        $delivery = $this->deliveryProgress((int) $salesOrder->id);
        $billing = $this->billingProgress($salesOrder, $delivery);

        return $this->resolveNextStatus(
            poType: (string) ($salesOrder->po_type ?? 'goods'),
            allPaid: $billing['allPaid'],
            anyBilled: $billing['anyBilled'],
            allDelivered: $delivery['allDelivered'],
            anyDelivered: $delivery['anyDelivered'],
            currentStatus: (string) ($salesOrder->status ?? 'open')
        );
    }

    /**
     * @return array{allDelivered: bool, anyDelivered: bool}
     */
    public function deliveryProgress(int $salesOrderId): array
    {
        $stats = DB::table('sales_order_lines')
            ->where('sales_order_id', $salesOrderId)
            ->selectRaw(
                'COUNT(*) as total_lines, ' .
                'SUM(CASE WHEN COALESCE(qty_delivered,0) > 0 THEN 1 ELSE 0 END) as delivered_any_lines, ' .
                'SUM(CASE WHEN COALESCE(qty_delivered,0) >= COALESCE(qty_ordered,0) THEN 1 ELSE 0 END) as delivered_full_lines'
            )
            ->first();

        $totalLines = (int) ($stats->total_lines ?? 0);
        $anyDelivered = ((int) ($stats->delivered_any_lines ?? 0)) > 0;
        $allDelivered = $totalLines > 0 && ((int) ($stats->delivered_full_lines ?? 0)) === $totalLines;

        return [
            'allDelivered' => $allDelivered,
            'anyDelivered' => $anyDelivered,
        ];
    }

    /**
     * @param array{allDelivered: bool, anyDelivered: bool} $deliveryProgress
     * @return array{allPaid: bool, anyBilled: bool}
     */
    private function billingProgress(SalesOrder $salesOrder, array $deliveryProgress): array
    {
        $activeTerms = $salesOrder->billingTerms()
            ->where('status', '!=', 'cancelled')
            ->get(['status']);

        $hasTerms = $activeTerms->isNotEmpty();
        $hasTermLinkedInvoices = Invoice::query()
            ->where('sales_order_id', $salesOrder->id)
            ->whereNotNull('so_billing_term_id')
            ->exists();

        if ($hasTerms && $hasTermLinkedInvoices) {
            $anyBilled = $activeTerms->contains(fn ($t) => in_array((string) $t->status, ['invoiced', 'paid'], true));
            $allPaid = $activeTerms->every(fn ($t) => (string) $t->status === 'paid');
            return ['allPaid' => $allPaid, 'anyBilled' => $anyBilled];
        }

        $invoiceStatuses = Invoice::query()
            ->where('sales_order_id', $salesOrder->id)
            ->pluck('status')
            ->map(fn ($s) => strtolower((string) $s));

        $anyBilled = $invoiceStatuses->contains(fn ($s) => in_array($s, ['posted', 'paid'], true));
        $allPaid = $anyBilled && $invoiceStatuses
            ->filter(fn ($s) => in_array($s, ['posted', 'paid'], true))
            ->every(fn ($s) => $s === 'paid');

        // Keep existing fully billed semantics for goods without any issued invoice.
        if (!$anyBilled && ($deliveryProgress['anyDelivered'] ?? false)) {
            return ['allPaid' => false, 'anyBilled' => false];
        }

        return ['allPaid' => $allPaid, 'anyBilled' => $anyBilled];
    }

    private function resolveNextStatus(
        string $poType,
        bool $allPaid,
        bool $anyBilled,
        bool $allDelivered,
        bool $anyDelivered,
        string $currentStatus
    ): string {
        $isGoods = strtolower($poType) === 'goods';

        if ($isGoods) {
            if ($allPaid && $allDelivered) return 'closed';
            if ($allPaid && !$allDelivered) return 'fully_billed';
            if ($anyBilled && !$allPaid) return 'partially_billed';
            if (!$anyBilled && $allDelivered) return 'delivered';
            if (!$anyBilled && $anyDelivered) return 'partial_delivered';
            return 'open';
        }

        if ($allPaid) return 'fully_billed';
        if ($anyBilled) return 'partially_billed';

        return $currentStatus ?: 'open';
    }
}
