<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\SalesOrder;
use App\Models\SalesOrderBillingTerm;

class ProjectBillingTermStatusService
{
    private const COMPONENT_COMBINED = 'combined';
    private const COMPONENT_MATERIAL = 'material';
    private const COMPONENT_LABOR = 'labor';
    private array $componentBreakdownCache = [];

    /**
     * @return array{
     *   mode:string,
     *   required_components:array<int,string>,
     *   invoices:array<string,\App\Models\Invoice>,
     *   invoiced_count:int,
     *   paid_count:int,
     *   status:string,
     *   invoice_id:int|null
     * }
     */
    public function progressForTerm(SalesOrderBillingTerm $term): array
    {
        $term->loadMissing('salesOrder');
        $salesOrder = $term->salesOrder;

        $breakdown = $salesOrder
            ? $this->componentBreakdownForSalesOrder($salesOrder)
            : [
                'mode' => SalesOrder::PROJECT_BILLING_MODE_COMBINED,
                'required_components' => [self::COMPONENT_COMBINED],
            ];
        $mode = (string) ($breakdown['mode'] ?? SalesOrder::PROJECT_BILLING_MODE_COMBINED);
        $requiredComponents = (array) ($breakdown['required_components'] ?? [self::COMPONENT_COMBINED]);

        $invoices = Invoice::query()
            ->where('sales_order_id', (int) $term->sales_order_id)
            ->where('so_billing_term_id', (int) $term->id)
            ->orderByDesc('id')
            ->get()
            ->groupBy(function (Invoice $invoice) {
                return $this->normalizeComponent($invoice->billing_component);
            })
            ->map(fn ($group) => $group->first());
        $combinedInvoice = $invoices->get(self::COMPONENT_COMBINED);

        $componentInvoices = [];
        $invoicedCount = 0;
        $paidCount = 0;
        foreach ($requiredComponents as $component) {
            /** @var Invoice|null $invoice */
            $invoice = $invoices->get($component);
            if (!$invoice && $mode === SalesOrder::PROJECT_BILLING_MODE_SPLIT && $combinedInvoice) {
                $invoice = $combinedInvoice;
            }
            if ($invoice) {
                $componentInvoices[$component] = $invoice;
                $invoicedCount++;
                if ($this->isPaid($invoice)) {
                    $paidCount++;
                }
            }
        }

        if ($paidCount === count($requiredComponents) && $paidCount > 0) {
            $status = 'paid';
        } elseif ($invoicedCount === count($requiredComponents) && $invoicedCount > 0) {
            $status = 'invoiced';
        } else {
            $status = 'planned';
        }

        $invoiceId = null;
        if ($status !== 'planned' && !empty($componentInvoices)) {
            $invoiceId = collect($componentInvoices)
                ->sortByDesc('id')
                ->first()?->id;
        }

        return [
            'mode' => $mode,
            'required_components' => $requiredComponents,
            'invoices' => $componentInvoices,
            'invoiced_count' => $invoicedCount,
            'paid_count' => $paidCount,
            'status' => $status,
            'invoice_id' => $invoiceId ? (int) $invoiceId : null,
        ];
    }

    public function syncTermStatus(SalesOrderBillingTerm $term): void
    {
        $progress = $this->progressForTerm($term);
        $nextStatus = $progress['status'];
        $nextInvoiceId = $progress['invoice_id'];

        $updates = [];
        if ((string) $term->status !== $nextStatus) {
            $updates['status'] = $nextStatus;
        }
        if ((int) ($term->invoice_id ?? 0) !== (int) ($nextInvoiceId ?? 0)) {
            $updates['invoice_id'] = $nextInvoiceId;
        }

        if (!empty($updates)) {
            $term->update($updates);
        }
    }

    public function syncByTermId(?int $termId): void
    {
        $termId = (int) ($termId ?? 0);
        if ($termId <= 0) {
            return;
        }

        $term = SalesOrderBillingTerm::query()->find($termId);
        if (!$term) {
            return;
        }

        $this->syncTermStatus($term);
    }

    public function componentsForMode(?string $mode): array
    {
        $mode = $this->normalizeMode($mode);
        if ($mode === SalesOrder::PROJECT_BILLING_MODE_SPLIT) {
            return [self::COMPONENT_MATERIAL, self::COMPONENT_LABOR];
        }

        return [self::COMPONENT_COMBINED];
    }

    /**
     * @return array{
     *   mode:string,
     *   material_total:float,
     *   labor_total:float,
     *   has_material:bool,
     *   has_labor:bool,
     *   required_components:array<int,string>
     * }
     */
    public function componentBreakdownForSalesOrder(SalesOrder $salesOrder): array
    {
        $cacheKey = (int) $salesOrder->id;
        if ($cacheKey > 0 && isset($this->componentBreakdownCache[$cacheKey])) {
            return $this->componentBreakdownCache[$cacheKey];
        }

        $mode = $this->normalizeMode($salesOrder->project_billing_mode);
        $poType = strtolower((string) ($salesOrder->po_type ?? 'goods'));
        if ($poType !== 'project') {
            $result = [
                'mode' => SalesOrder::PROJECT_BILLING_MODE_COMBINED,
                'material_total' => 0.0,
                'labor_total' => 0.0,
                'has_material' => false,
                'has_labor' => false,
                'required_components' => [self::COMPONENT_COMBINED],
            ];
            if ($cacheKey > 0) {
                $this->componentBreakdownCache[$cacheKey] = $result;
            }
            return $result;
        }

        if ($salesOrder->relationLoaded('lines')) {
            $materialTotal = (float) $salesOrder->lines->sum(fn ($line) => (float) ($line->material_total ?? 0));
            $laborTotal = (float) $salesOrder->lines->sum(fn ($line) => (float) ($line->labor_total ?? 0));
        } else {
            $totals = $salesOrder->lines()
                ->selectRaw('COALESCE(SUM(material_total),0) as material_total, COALESCE(SUM(labor_total),0) as labor_total')
                ->first();
            $materialTotal = (float) ($totals->material_total ?? 0);
            $laborTotal = (float) ($totals->labor_total ?? 0);
        }

        $materialTotal = round(max($materialTotal, 0), 2);
        $laborTotal = round(max($laborTotal, 0), 2);

        $hasMaterial = $materialTotal > 0.0001;
        $hasLabor = $laborTotal > 0.0001;

        if ($hasMaterial && $hasLabor) {
            $requiredComponents = $this->componentsForMode($mode);
        } elseif ($hasMaterial) {
            $requiredComponents = [self::COMPONENT_MATERIAL];
        } elseif ($hasLabor) {
            $requiredComponents = [self::COMPONENT_LABOR];
        } else {
            $requiredComponents = [self::COMPONENT_COMBINED];
        }

        $result = [
            'mode' => $mode,
            'material_total' => $materialTotal,
            'labor_total' => $laborTotal,
            'has_material' => $hasMaterial,
            'has_labor' => $hasLabor,
            'required_components' => $requiredComponents,
        ];

        if ($cacheKey > 0) {
            $this->componentBreakdownCache[$cacheKey] = $result;
        }

        return $result;
    }

    public function componentsForSalesOrder(SalesOrder $salesOrder): array
    {
        $breakdown = $this->componentBreakdownForSalesOrder($salesOrder);
        return (array) ($breakdown['required_components'] ?? [self::COMPONENT_COMBINED]);
    }

    public function normalizeMode(?string $mode): string
    {
        $mode = strtolower(trim((string) $mode));
        if ($mode === SalesOrder::PROJECT_BILLING_MODE_SPLIT) {
            return SalesOrder::PROJECT_BILLING_MODE_SPLIT;
        }

        return SalesOrder::PROJECT_BILLING_MODE_COMBINED;
    }

    private function normalizeComponent(?string $component): string
    {
        $component = strtolower(trim((string) $component));
        if ($component === self::COMPONENT_MATERIAL) {
            return self::COMPONENT_MATERIAL;
        }
        if ($component === self::COMPONENT_LABOR) {
            return self::COMPONENT_LABOR;
        }

        return self::COMPONENT_COMBINED;
    }

    private function isPaid(Invoice $invoice): bool
    {
        return strtolower((string) $invoice->status) === 'paid' || (bool) $invoice->paid_at;
    }
}
