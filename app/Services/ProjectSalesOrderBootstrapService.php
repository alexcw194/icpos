<?php

namespace App\Services;

use App\Models\Project;
use App\Models\ProjectQuotation;
use App\Models\SalesOrder;
use App\Models\SalesOrderLine;
use App\Models\TermOfPayment;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class ProjectSalesOrderBootstrapService
{
    public function ensureForWonQuotation(Project $project, ProjectQuotation $quotation): SalesOrder
    {
        $quotation->loadMissing([
            'company',
            'customer',
            'sections.lines' => fn ($q) => $q->orderBy('id'),
            'paymentTerms' => fn ($q) => $q->orderBy('sequence'),
        ]);

        $salesOrder = SalesOrder::query()
            ->where('project_quotation_id', $quotation->id)
            ->first();
        if ($salesOrder) {
            $this->syncLinkedSalesOrder($salesOrder, $project, $quotation);
            return $salesOrder->fresh(['billingTerms', 'lines']);
        }

        $salesOrder = SalesOrder::query()
            ->where('project_id', $project->id)
            ->where('po_type', 'project')
            ->where('status', '!=', 'cancelled')
            ->orderByDesc('id')
            ->first();
        if ($salesOrder) {
            $this->syncLinkedSalesOrder($salesOrder, $project, $quotation);
            return $salesOrder->fresh(['billingTerms', 'lines']);
        }

        return DB::transaction(function () use ($project, $quotation) {
            $company = $quotation->company ?: $project->company;
            $customer = $quotation->customer ?: $project->customer;
            $orderDate = Carbon::now();

            $linePayloads = $this->buildLinePayloads($quotation);
            $lineSubtotal = collect($linePayloads)->sum('line_total');
            $taxPercent = (bool) $quotation->tax_enabled ? (float) ($quotation->tax_percent ?? 0) : 0.0;
            $taxableBase = round($lineSubtotal, 2);
            $taxAmount = round($taxableBase * ($taxPercent / 100), 2);
            $operationalTotal = round($taxableBase + $taxAmount, 2);
            $contractValue = (float) ($quotation->grand_total ?? 0);
            if ($contractValue <= 0) {
                $contractValue = $operationalTotal;
            }

            $salesOrder = SalesOrder::create([
                'company_id' => (int) $company->id,
                'customer_id' => (int) $customer->id,
                'project_id' => $project->id,
                'project_quotation_id' => $quotation->id,
                'project_name' => $project->name,
                'sales_user_id' => $quotation->sales_owner_user_id ?: $project->sales_owner_user_id,
                'so_number' => app(DocNumberService::class)->next('sales_order', $company, $orderDate),
                'order_date' => $orderDate->toDateString(),
                'customer_ref_type' => 'po',
                'customer_po_number' => null,
                'customer_po_date' => null,
                'po_type' => 'project',
                'payment_term_id' => null,
                'payment_term_snapshot' => null,
                'ship_to' => $customer->address ?? null,
                'bill_to' => $customer->address ?? null,
                'notes' => $quotation->notes,
                'private_notes' => null,
                'fee_amount' => 0,
                'under_amount' => 0,
                'discount_mode' => 'total',
                'lines_subtotal' => $lineSubtotal,
                'total_discount_type' => 'amount',
                'total_discount_value' => 0,
                'total_discount_amount' => 0,
                'taxable_base' => $taxableBase,
                'tax_percent' => $taxPercent,
                'tax_amount' => $taxAmount,
                'total' => $operationalTotal,
                'contract_value' => $contractValue,
                'status' => 'open',
                'currency' => 'IDR',
                'brand_snapshot' => $quotation->brand_snapshot,
            ]);

            foreach ($linePayloads as $payload) {
                $salesOrder->lines()->create($payload);
            }

            $this->syncBillingTerms($salesOrder, $quotation);

            return $salesOrder->fresh(['billingTerms', 'lines']);
        });
    }

    private function syncLinkedSalesOrder(SalesOrder $salesOrder, Project $project, ProjectQuotation $quotation): void
    {
        $updates = [];
        if ((int) ($salesOrder->project_quotation_id ?? 0) !== (int) $quotation->id) {
            $updates['project_quotation_id'] = $quotation->id;
        }
        if ((int) ($salesOrder->project_id ?? 0) !== (int) $project->id) {
            $updates['project_id'] = $project->id;
        }
        if (($salesOrder->po_type ?? '') !== 'project') {
            $updates['po_type'] = 'project';
        }
        if (empty($salesOrder->project_name)) {
            $updates['project_name'] = $project->name;
        }
        if (empty($salesOrder->customer_ref_type)) {
            $updates['customer_ref_type'] = 'po';
        }
        if ((float) ($salesOrder->contract_value ?? 0) <= 0) {
            $updates['contract_value'] = (float) ($quotation->grand_total ?? $salesOrder->total ?? 0);
        }
        if (empty($salesOrder->sales_user_id) && !empty($project->sales_owner_user_id)) {
            $updates['sales_user_id'] = $project->sales_owner_user_id;
        }
        if (!empty($updates)) {
            $salesOrder->update($updates);
        }

        if (!$salesOrder->relationLoaded('billingTerms')) {
            $salesOrder->load('billingTerms');
        }
        if ($salesOrder->billingTerms->isEmpty()) {
            $this->syncBillingTerms($salesOrder, $quotation);
        }

        if (!$salesOrder->relationLoaded('lines')) {
            $salesOrder->load('lines');
        }
        if ($salesOrder->lines->isEmpty()) {
            foreach ($this->buildLinePayloads($quotation) as $payload) {
                $salesOrder->lines()->create($payload);
            }
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function buildLinePayloads(ProjectQuotation $quotation): array
    {
        $rows = [];
        $position = 1;

        foreach ($quotation->sections->sortBy('sort_order') as $section) {
            foreach ($section->lines as $line) {
                $name = trim((string) ($line->item_label ?: $line->description ?: 'Project Scope'));
                if ($name === '') {
                    $name = 'Project Scope';
                }
                if (mb_strlen($name) > 255) {
                    $name = mb_substr($name, 0, 255);
                }

                $qty = (float) ($line->qty ?? 0);
                if ($qty <= 0) {
                    $qty = 1.0;
                }
                $qty = round($qty, 4);

                $lineTotal = (float) ($line->line_total ?? 0);
                if ($lineTotal <= 0) {
                    $lineTotal = (float) ($line->material_total ?? 0) + (float) ($line->labor_total ?? 0);
                }
                if ($lineTotal <= 0) {
                    $lineTotal = round($qty * (float) ($line->unit_price ?? 0), 2);
                }
                $lineTotal = round(max($lineTotal, 0), 2);

                $unitPrice = $qty > 0 ? round($lineTotal / $qty, 2) : round((float) ($line->unit_price ?? 0), 2);
                $unitPrice = max($unitPrice, 0);

                $rows[] = [
                    'position' => $position++,
                    'name' => $name,
                    'po_item_name' => null,
                    'description' => $line->description,
                    'unit' => (string) ($line->unit ?: 'LS'),
                    'qty_ordered' => $qty,
                    'unit_price' => $unitPrice,
                    'discount_type' => 'amount',
                    'discount_value' => 0,
                    'discount_amount' => 0,
                    'line_subtotal' => $lineTotal,
                    'line_total' => $lineTotal,
                    'item_id' => $line->item_id ?: null,
                    'item_variant_id' => $line->item_variant_id ?: null,
                    'baseline_project_quotation_line_id' => $line->id,
                    'baseline_name' => $name,
                    'baseline_description' => $line->description,
                    'baseline_item_id' => $line->item_id ?: null,
                    'baseline_item_variant_id' => $line->item_variant_id ?: null,
                    'baseline_qty' => $qty,
                    'baseline_unit' => (string) ($line->unit ?: 'LS'),
                    'baseline_unit_price' => $unitPrice,
                    'baseline_line_total' => $lineTotal,
                ];
            }
        }

        return $rows;
    }

    private function syncBillingTerms(SalesOrder $salesOrder, ProjectQuotation $quotation): void
    {
        $validTopCodes = TermOfPayment::query()
            ->whereIn('code', TermOfPayment::ALLOWED_CODES)
            ->pluck('code')
            ->map(fn ($code) => strtoupper((string) $code))
            ->all();
        $validTopCodeMap = array_flip($validTopCodes);

        $payloads = [];
        $seenCode = [];
        $seq = 1;
        foreach ($quotation->paymentTerms as $term) {
            $code = strtoupper(trim((string) ($term->code ?? '')));
            if ($code === '' || isset($seenCode[$code])) {
                continue;
            }
            if (!isset($validTopCodeMap[$code])) {
                continue;
            }

            $seenCode[$code] = true;
            $payloads[] = [
                'seq' => $seq++,
                'top_code' => $code,
                'percent' => (float) ($term->percent ?? 0),
                'due_trigger' => $term->due_trigger,
                'offset_days' => $term->offset_days,
                'day_of_month' => $term->day_of_month,
                'note' => $term->label ?: $term->trigger_note,
                'status' => 'planned',
            ];
        }

        if (empty($payloads)) {
            $payloads[] = [
                'seq' => 1,
                'top_code' => 'FINISH',
                'percent' => 100.0,
                'due_trigger' => 'on_invoice',
                'offset_days' => null,
                'day_of_month' => null,
                'note' => 'Finish',
                'status' => 'planned',
            ];
        }

        $salesOrder->billingTerms()->createMany($payloads);
    }
}

