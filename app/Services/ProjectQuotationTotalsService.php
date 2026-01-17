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
        $subtotal = 0.0;

        $normalizedSections = [];

        foreach ($sections as $sIndex => $section) {
            $lines = $section['lines'] ?? [];
            $normalizedLines = [];

            foreach ($lines as $lIndex => $line) {
                $qty = Number::idToFloat($line['qty'] ?? 0);
                $unitPrice = Number::idToFloat($line['unit_price'] ?? 0);
                $materialTotal = Number::idToFloat($line['material_total'] ?? 0);
                $laborTotal = Number::idToFloat($line['labor_total'] ?? 0);
                $laborUnitSnapshot = Number::idToFloat($line['labor_unit_cost_snapshot'] ?? 0);
                if ($laborUnitSnapshot <= 0 && $qty > 0 && $laborTotal > 0) {
                    $laborUnitSnapshot = $laborTotal / $qty;
                }
                $lineTotal = $materialTotal + $laborTotal;

                $subtotalMaterial += $materialTotal;
                $subtotalLabor += $laborTotal;
                $subtotal += $lineTotal;

                $normalizedLines[] = [
                    'line_no' => $line['line_no'] ?? null,
                    'description' => $line['description'] ?? '',
                    'source_type' => $line['source_type'] ?? 'item',
                    'item_id' => $line['item_id'] ?? null,
                    'item_label' => $line['item_label'] ?? null,
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
        }

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
