<?php

namespace App\Services;

use App\Support\Number;

class ProjectQuotationTotalsService
{
    public function compute(array $data): array
    {
        $sections = $data['sections'] ?? [];
        $taxEnabled = !empty($data['tax_enabled']);
        $taxPercent = isset($data['tax_percent']) ? (float) $data['tax_percent'] : 0.0;

        $subtotalMaterial = 0.0;
        $subtotalLabor = 0.0;
        $productSubtotal = 0.0;
        $chargeTotal = 0.0;
        $percentTotal = 0.0;

        $normalizedSections = [];
        $sectionProductTotals = [];

        foreach ($sections as $sIndex => $section) {
            $lines = $section['lines'] ?? [];
            $normalizedLines = [];
            $sectionProductTotal = 0.0;

            foreach ($lines as $lIndex => $line) {
                $lineType = $line['line_type'] ?? 'product';
                $qty = Number::idToFloat($line['qty'] ?? 0);
                $unitPrice = Number::idToFloat($line['unit_price'] ?? 0);
                $materialTotal = Number::idToFloat($line['material_total'] ?? 0);
                $laborTotal = Number::idToFloat($line['labor_total'] ?? 0);
                $percentValue = $lineType === 'percent'
                    ? Number::idToFloat($line['percent_value'] ?? 0)
                    : null;
                $percentBasis = $lineType === 'percent'
                    ? ($line['percent_basis'] ?? 'product_subtotal')
                    : null;
                $computedAmount = $lineType === 'percent'
                    ? Number::idToFloat($line['computed_amount'] ?? 0)
                    : null;
                $laborUnitSnapshot = Number::idToFloat($line['labor_unit_cost_snapshot'] ?? 0);
                if ($laborUnitSnapshot <= 0 && $qty > 0 && $laborTotal > 0) {
                    $laborUnitSnapshot = $laborTotal / $qty;
                }
                $lineTotal = 0.0;

                if ($lineType === 'product') {
                    $lineTotal = $materialTotal + $laborTotal;
                    $subtotalMaterial += $materialTotal;
                    $subtotalLabor += $laborTotal;
                    $productSubtotal += $lineTotal;
                    $sectionProductTotal += $lineTotal;
                } elseif ($lineType === 'charge') {
                    $lineTotal = $materialTotal + $laborTotal;
                    $chargeTotal += $lineTotal;
                }

                $normalizedLines[] = [
                    'line_no' => $line['line_no'] ?? null,
                    'description' => $line['description'] ?? '',
                    'source_type' => $line['source_type'] ?? 'item',
                    'item_id' => $line['item_id'] ?? null,
                    'item_label' => $line['item_label'] ?? null,
                    'line_type' => $lineType,
                    'catalog_id' => $line['catalog_id'] ?? null,
                    'percent_value' => $percentValue,
                    'percent_basis' => $percentBasis,
                    'computed_amount' => $computedAmount,
                    'cost_bucket' => $line['cost_bucket'] ?? 'overhead',
                    'qty' => $qty,
                    'unit' => $line['unit'] ?? 'PCS',
                    'unit_price' => $unitPrice,
                    'material_total' => $materialTotal,
                    'labor_total' => $laborTotal,
                    'labor_source' => $line['labor_source'] ?? 'manual',
                    'labor_unit_cost_snapshot' => $laborUnitSnapshot,
                    'labor_override_reason' => $line['labor_override_reason'] ?? null,
                    'line_total' => $lineTotal,
                ];
            }

            $normalizedSections[] = [
                'name' => $section['name'] ?? 'Section',
                'sort_order' => (int) ($section['sort_order'] ?? $sIndex),
                'lines' => $normalizedLines,
            ];
            $sectionProductTotals[$sIndex] = $sectionProductTotal;
        }

        foreach ($normalizedSections as $sIndex => &$section) {
            foreach ($section['lines'] as &$line) {
                if (($line['line_type'] ?? 'product') !== 'percent') {
                    continue;
                }

                $basis = ($line['percent_basis'] ?? 'product_subtotal') === 'section_product_subtotal'
                    ? ($sectionProductTotals[$sIndex] ?? 0.0)
                    : $productSubtotal;

                if ($basis <= 0 && ($line['percent_basis'] ?? '') === 'section_product_subtotal' && $productSubtotal > 0) {
                    $basis = $productSubtotal;
                }

                $computedAmount = round($basis * ((float) ($line['percent_value'] ?? 0) / 100), 2);
                $line['computed_amount'] = $computedAmount;
                $line['material_total'] = $computedAmount;
                $line['labor_total'] = 0.0;
                $line['line_total'] = $computedAmount;
                $percentTotal += $computedAmount;
            }
        }
        unset($section, $line);

        $subtotal = $productSubtotal + $chargeTotal + $percentTotal;
        $taxAmount = $taxEnabled ? ($subtotal * ($taxPercent / 100)) : 0.0;
        $grandTotal = $subtotal + $taxAmount;

        return [
            'sections' => $normalizedSections,
            'subtotal_material' => $subtotalMaterial,
            'subtotal_labor' => $subtotalLabor,
            'subtotal' => $subtotal,
            'tax_percent' => $taxPercent,
            'tax_amount' => $taxAmount,
            'grand_total' => $grandTotal,
        ];
    }
}
