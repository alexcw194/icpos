<?php

namespace App\Http\Controllers;

use App\Models\Item;
use App\Models\ItemLaborRate;
use App\Models\ItemVariant;
use App\Models\ProjectItemLaborRate;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

class LaborRateController extends Controller
{
    public function show(Request $request)
    {
        $source = (string) $request->input('source', 'item');
        $source = in_array($source, ['item', 'project'], true) ? $source : 'item';
        $itemId = (int) $request->input('item_id');
        $variantId = (int) $request->input('variant_id');

        if ($itemId < 1) {
            return response()->json(['ok' => false, 'unit_cost' => null, 'source' => null]);
        }

        if ($source === 'project') {
            $hasVariantColumn = Schema::hasColumn('project_item_labor_rates', 'item_variant_id');
            $rateQuery = ProjectItemLaborRate::query()->where('project_item_id', $itemId);
            if ($hasVariantColumn) {
                if ($variantId > 0) {
                    $rateQuery->where('item_variant_id', $variantId);
                } else {
                    $rateQuery->whereNull('item_variant_id');
                }
            }
            $rate = $rateQuery->first();
            return response()->json([
                'ok' => true,
                'unit_cost' => $rate?->labor_unit_cost ?? null,
                'source' => $rate ? 'master_project' : null,
                'notes' => $rate?->notes,
            ]);
        }

        $hasVariantColumn = Schema::hasColumn('item_labor_rates', 'item_variant_id');
        $rateQuery = ItemLaborRate::query()->where('item_id', $itemId);
        if ($hasVariantColumn) {
            if ($variantId > 0) {
                $rateQuery->where('item_variant_id', $variantId);
            } else {
                $rateQuery->whereNull('item_variant_id');
            }
        }
        $rate = $rateQuery->first();
        return response()->json([
            'ok' => true,
            'unit_cost' => $rate?->labor_unit_cost ?? null,
            'source' => $rate ? 'master_item' : null,
            'notes' => $rate?->notes,
        ]);
    }

    public function update(Request $request)
    {
        $source = (string) $request->input('source', 'item');
        $source = in_array($source, ['item', 'project'], true) ? $source : 'item';
        $itemId = (int) $request->input('item_id');
        $variantId = (int) $request->input('variant_id');

        $data = $request->validate([
            'item_id' => ['required', 'exists:items,id'],
            'variant_id' => ['nullable', 'integer', 'exists:item_variants,id'],
            'source' => ['required', 'in:item,project'],
            'labor_unit_cost' => ['required', 'numeric', 'min:0'],
            'reason' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:255'],
        ]);

        $user = $request->user();
        $canUpdateItem = $user?->hasAnyRole(['Admin', 'SuperAdmin', 'Finance']) ?? false;
        $canUpdateProject = $user?->hasAnyRole(['Admin', 'SuperAdmin', 'PM']) ?? false;

        if ($source === 'project' && !$canUpdateProject) {
            abort(403);
        }
        if ($source === 'item' && !$canUpdateItem) {
            abort(403);
        }

        $item = Item::find($itemId);
        if (!$item) {
            return response()->json(['ok' => false, 'message' => 'Item tidak ditemukan.'], 404);
        }
        $variantId = !empty($data['variant_id']) ? (int) $data['variant_id'] : null;
        if ($variantId) {
            $variantOk = ItemVariant::query()
                ->where('id', $data['variant_id'])
                ->where('item_id', $itemId)
                ->exists();
            if (!$variantOk) {
                return response()->json(['ok' => false, 'message' => 'Varian tidak sesuai dengan item.'], 422);
            }
        }
        if ($source === 'project' && $item->list_type !== 'project') {
            return response()->json(['ok' => false, 'message' => 'Item bukan Project Item.'], 422);
        }
        if ($source === 'item' && $item->list_type === 'project') {
            return response()->json(['ok' => false, 'message' => 'Gunakan Project Item Labor untuk item ini.'], 422);
        }

        $laborUnitCost = (float) $data['labor_unit_cost'];
        $reason = $data['reason'] ?? null;
        $variantId = !empty($data['variant_id']) ? (int) $data['variant_id'] : null;

        if ($source === 'project') {
            $hasVariantColumn = Schema::hasColumn('project_item_labor_rates', 'item_variant_id');
            if ($variantId && !$hasVariantColumn) {
                return response()->json(['ok' => false, 'message' => 'Labor varian belum bisa disimpan. Jalankan migration.'], 422);
            }
            $rateAttrs = ['project_item_id' => $itemId];
            if ($hasVariantColumn) {
                $rateAttrs['item_variant_id'] = $variantId;
            }
            $rate = ProjectItemLaborRate::firstOrNew($rateAttrs);
            if ($rate->exists && $laborUnitCost < (float) $rate->labor_unit_cost && empty($reason)) {
                return response()->json(['ok' => false, 'message' => 'Alasan wajib jika nilai turun.'], 422);
            }
            $rate->labor_unit_cost = $laborUnitCost;
            $rate->notes = $data['notes'] ?? $rate->notes;
            $rate->updated_by = $user?->id;
            $rate->save();

            return response()->json(['ok' => true, 'unit_cost' => $rate->labor_unit_cost, 'source' => 'master_project']);
        }

        $hasVariantColumn = Schema::hasColumn('item_labor_rates', 'item_variant_id');
        if ($variantId && !$hasVariantColumn) {
            return response()->json(['ok' => false, 'message' => 'Labor varian belum bisa disimpan. Jalankan migration.'], 422);
        }
        $rateAttrs = ['item_id' => $itemId];
        if ($hasVariantColumn) {
            $rateAttrs['item_variant_id'] = $variantId;
        }
        $rate = ItemLaborRate::firstOrNew($rateAttrs);
        if ($rate->exists && $laborUnitCost < (float) $rate->labor_unit_cost && empty($reason)) {
            return response()->json(['ok' => false, 'message' => 'Alasan wajib jika nilai turun.'], 422);
        }
        $rate->labor_unit_cost = $laborUnitCost;
        $rate->notes = $data['notes'] ?? $rate->notes;
        $rate->updated_by = $user?->id;
        $rate->save();

        return response()->json(['ok' => true, 'unit_cost' => $rate->labor_unit_cost, 'source' => 'master_item']);
    }
}
