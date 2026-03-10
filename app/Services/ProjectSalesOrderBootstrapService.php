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
                'project_billing_mode' => SalesOrder::PROJECT_BILLING_MODE_COMBINED,
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
        $previousQuotationId = (int) ($salesOrder->project_quotation_id ?? 0);
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
        if (empty($salesOrder->project_billing_mode)) {
            $updates['project_billing_mode'] = SalesOrder::PROJECT_BILLING_MODE_COMBINED;
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
        } elseif ($previousQuotationId !== (int) $quotation->id) {
            $this->syncProjectBaselineFromRevision($salesOrder, $quotation);
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
                $snapshot = $this->buildLineSnapshot($line);

                $rows[] = [
                    'position' => $position++,
                    'name' => $snapshot['name'],
                    'po_item_name' => null,
                    'description' => $snapshot['description'],
                    'unit' => $snapshot['unit'],
                    'qty_ordered' => $snapshot['qty'],
                    'unit_price' => $snapshot['unit_price'],
                    'material_total' => $snapshot['material_total'],
                    'labor_total' => $snapshot['labor_total'],
                    'discount_type' => 'amount',
                    'discount_value' => 0,
                    'discount_amount' => 0,
                    'line_subtotal' => $snapshot['line_total'],
                    'line_total' => $snapshot['line_total'],
                    'item_id' => $snapshot['item_id'],
                    'item_variant_id' => $snapshot['item_variant_id'],
                    'baseline_project_quotation_line_id' => $line->id,
                    'baseline_name' => $snapshot['name'],
                    'baseline_description' => $snapshot['description'],
                    'baseline_item_id' => $snapshot['item_id'],
                    'baseline_item_variant_id' => $snapshot['item_variant_id'],
                    'baseline_qty' => $snapshot['qty'],
                    'baseline_unit' => $snapshot['unit'],
                    'baseline_unit_price' => $snapshot['unit_price'],
                    'baseline_material_total' => $snapshot['material_total'],
                    'baseline_labor_total' => $snapshot['labor_total'],
                    'baseline_line_total' => $snapshot['line_total'],
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

    /**
     * @return array{name:string,description:?string,unit:string,qty:float,unit_price:float,line_total:float,material_total:float,labor_total:float,item_id:int|null,item_variant_id:int|null}
     */
    private function buildLineSnapshot($line): array
    {
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

        $materialTotal = round(max((float) ($line->material_total ?? 0), 0), 2);
        $laborTotal = round(max((float) ($line->labor_total ?? 0), 0), 2);

        $lineTotal = (float) ($line->line_total ?? 0);
        if ($lineTotal <= 0) {
            $lineTotal = $materialTotal + $laborTotal;
        }
        if ($lineTotal <= 0) {
            $lineTotal = round($qty * (float) ($line->unit_price ?? 0), 2);
        }
        $lineTotal = round(max($lineTotal, 0), 2);

        $componentTotal = round($materialTotal + $laborTotal, 2);
        if ($componentTotal <= 0 && $lineTotal > 0) {
            $materialTotal = $lineTotal;
            $laborTotal = 0.0;
        } elseif (abs($componentTotal - $lineTotal) > 0.01) {
            $materialTotal = round(max($lineTotal - $laborTotal, 0), 2);
            $componentTotal = round($materialTotal + $laborTotal, 2);
            if (abs($componentTotal - $lineTotal) > 0.01) {
                $lineTotal = $componentTotal;
            }
        }

        $unitPrice = $qty > 0 ? round($lineTotal / $qty, 2) : round((float) ($line->unit_price ?? 0), 2);
        $unitPrice = max($unitPrice, 0);

        return [
            'name' => $name,
            'description' => $line->description,
            'unit' => (string) ($line->unit ?: 'LS'),
            'qty' => $qty,
            'unit_price' => $unitPrice,
            'line_total' => $lineTotal,
            'material_total' => $materialTotal,
            'labor_total' => $laborTotal,
            'item_id' => $line->item_id ?: null,
            'item_variant_id' => $line->item_variant_id ?: null,
        ];
    }

    private function syncProjectBaselineFromRevision(SalesOrder $salesOrder, ProjectQuotation $quotation): void
    {
        $quotation->loadMissing([
            'sections.lines' => fn ($query) => $query->orderBy('id'),
        ]);
        $salesOrder->loadMissing('lines');

        $lineBySourceId = [];
        $lineById = [];
        foreach ($quotation->sections as $section) {
            foreach ($section->lines as $line) {
                $lineById[(int) $line->id] = $line;
                $sourceId = (int) ($line->revision_source_line_id ?? 0);
                if ($sourceId > 0 && !isset($lineBySourceId[$sourceId])) {
                    $lineBySourceId[$sourceId] = $line;
                }
            }
        }

        foreach ($salesOrder->lines as $salesLine) {
            $baselineLineId = (int) ($salesLine->baseline_project_quotation_line_id ?? 0);
            if ($baselineLineId <= 0) {
                continue;
            }

            $targetLine = $lineBySourceId[$baselineLineId] ?? $lineById[$baselineLineId] ?? null;
            if (!$targetLine) {
                continue;
            }

            $snapshot = $this->buildLineSnapshot($targetLine);
            $salesLine->update([
                'baseline_project_quotation_line_id' => $targetLine->id,
                'baseline_name' => $snapshot['name'],
                'baseline_description' => $snapshot['description'],
                'baseline_item_id' => $snapshot['item_id'],
                'baseline_item_variant_id' => $snapshot['item_variant_id'],
                'baseline_qty' => $snapshot['qty'],
                'baseline_unit' => $snapshot['unit'],
                'baseline_unit_price' => $snapshot['unit_price'],
                'baseline_material_total' => $snapshot['material_total'],
                'baseline_labor_total' => $snapshot['labor_total'],
                'baseline_line_total' => $snapshot['line_total'],
            ]);
        }
    }
}
