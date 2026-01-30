<?php

namespace App\Http\Controllers;

use App\Models\Item;
use App\Models\ItemLaborRate;
use App\Models\ItemVariant;
use App\Models\ProjectItemLaborRate;
use Illuminate\Http\Request;

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
            $rate = ProjectItemLaborRate::query()
                ->where('project_item_id', $itemId)
                ->when($variantId > 0, fn($q) => $q->where('item_variant_id', $variantId), fn($q) => $q->whereNull('item_variant_id'))
                ->first();
            return response()->json([
                'ok' => true,
                'unit_cost' => $rate?->labor_unit_cost ?? null,
                'source' => $rate ? 'master_project' : null,
                'notes' => $rate?->notes,
            ]);
        }

        $rate = ItemLaborRate::query()
            ->where('item_id', $itemId)
            ->when($variantId > 0, fn($q) => $q->where('item_variant_id', $variantId), fn($q) => $q->whereNull('item_variant_id'))
            ->first();
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
        if (!empty($data['variant_id'])) {
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
            $rate = ProjectItemLaborRate::firstOrNew([
                'project_item_id' => $itemId,
                'item_variant_id' => $variantId,
            ]);
            if ($rate->exists && $laborUnitCost < (float) $rate->labor_unit_cost && empty($reason)) {
                return response()->json(['ok' => false, 'message' => 'Alasan wajib jika nilai turun.'], 422);
            }
            $rate->labor_unit_cost = $laborUnitCost;
            $rate->notes = $data['notes'] ?? $rate->notes;
            $rate->updated_by = $user?->id;
            $rate->save();

            return response()->json(['ok' => true, 'unit_cost' => $rate->labor_unit_cost, 'source' => 'master_project']);
        }

        $rate = ItemLaborRate::firstOrNew([
            'item_id' => $itemId,
            'item_variant_id' => $variantId,
        ]);
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
