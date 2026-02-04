<?php

namespace App\Http\Controllers;

use App\Models\StockAdjustment;
use App\Models\StockSummary;
use App\Models\Item;
use App\Models\ItemVariant;
use App\Models\Warehouse;
use App\Models\Company;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

class StockAdjustmentController extends Controller
{
    public function index()
    {
        $adjustments = StockAdjustment::with(['item','variant','warehouse'])
            ->latest()->paginate(50);

        return view('inventory.adjustments', compact('adjustments'));
    }

    public function create(Request $r)
    {
        $companyId = (int) ($r->company_id ?? Company::where('is_default', true)->value('id'));

        $items = Item::query()
            ->with('unit:id,code')
            ->withCount('variants')
            ->orderBy('name')
            ->get(['id', 'name', 'sku', 'unit_id', 'variant_type']);

        $variants = ItemVariant::query()
            ->with(['item:id,name,unit_id,variant_type,name_template', 'item.unit:id,code'])
            ->orderBy('item_id')
            ->orderBy('id')
            ->get(['id', 'item_id', 'sku', 'attributes']);

        $warehouses = Warehouse::query()
            ->when(Schema::hasColumn('warehouses', 'company_id') && $companyId, fn($q) => $q->where('company_id', $companyId))
            ->orderBy('name')
            ->get(['id', 'name']);

        $selectedItemId = $r->input('item_id');
        $selectedVariantId = $r->input('variant_id');
        $selectedWarehouseId = $r->input('warehouse_id');

        $summary = null;
        if ($selectedItemId) {
            $summary = StockSummary::where('item_id', $selectedItemId)
                ->when($selectedWarehouseId, fn($q)=>$q->where('warehouse_id', $selectedWarehouseId))
                ->when($selectedVariantId, fn($q)=>$q->where('variant_id', $selectedVariantId))
                ->first();
        }

        $itemsWithoutVariants = $items->filter(function ($it) {
            $variantType = $it->variant_type ?? 'none';
            return (int) $it->variants_count === 0 && $variantType === 'none';
        })->values();
        $itemOptions = [];

        foreach ($itemsWithoutVariants as $it) {
            $itemOptions[] = [
                'value' => 'item:' . $it->id,
                'label' => $it->name,
                'item_id' => $it->id,
                'variant_id' => null,
                'unit' => optional($it->unit)->code ?? 'pcs',
                'sku' => $it->sku ?? '',
            ];
        }

        foreach ($variants as $v) {
            $item = $v->item;
            if (!$item) {
                continue;
            }
            $variantLabel = trim((string) ($v->label ?? ''));
            if ($variantLabel === '') {
                $variantLabel = trim((string) ($v->sku ?? ''));
            }
            if ($variantLabel === '') {
                $variantLabel = 'Variant #' . $v->id;
            }
            if ($item->name && str_contains($variantLabel, $item->name)) {
                $variantLabel = trim(str_replace($item->name, '', $variantLabel));
                $variantLabel = ltrim($variantLabel, "-/— \t");
                if ($variantLabel === '') {
                    $variantLabel = $item->sku ?? 'Variant #' . $v->id;
                }
            }
            $itemOptions[] = [
                'value' => 'variant:' . $v->id,
                'label' => $item->name . ' - ' . $variantLabel,
                'item_id' => $item->id,
                'variant_id' => $v->id,
                'unit' => optional($item->unit)->code ?? 'pcs',
                'sku' => $v->sku ?? $item->sku ?? '',
            ];
        }

        return view('inventory.adjustment_create', compact(
            'summary',
            'items',
            'itemOptions',
            'warehouses',
            'selectedItemId',
            'selectedVariantId',
            'companyId'
        ));
    }

    public function store(Request $r)
    {
        $companyId = $r->input('company_id')
            ?: auth()->user()?->company_id
            ?: Company::where('is_default', true)->value('id');

        if ($companyId) {
            $r->merge(['company_id' => $companyId]);
        }

        $data = $r->validate([
            'company_id' => 'required|exists:companies,id',
            'warehouse_id' => 'nullable|exists:warehouses,id',
            'item_id' => 'required|exists:items,id',
            'variant_id' => 'nullable|exists:item_variants,id',
            'qty_adjustment' => 'required|numeric',
            'reason' => 'nullable|string',
            'adjustment_date' => 'required|date',
        ]);

        if (!empty($data['variant_id'])) {
            $variantMatches = ItemVariant::where('id', $data['variant_id'])
                ->where('item_id', $data['item_id'])
                ->exists();
            if (!$variantMatches) {
                return back()
                    ->withErrors(['variant_id' => 'Variant tidak sesuai dengan item yang dipilih.'])
                    ->withInput();
            }
        } else {
            $hasVariants = ItemVariant::where('item_id', $data['item_id'])->exists();
            if ($hasVariants) {
                return back()
                    ->withErrors(['item_id' => 'Item ini memiliki varian. Pilih varian yang sesuai.'])
                    ->withInput();
            }
        }

        $adjustmentDate = Carbon::parse($data['adjustment_date']);
        unset($data['adjustment_date']);

        $data['created_by'] = auth()->id();
        $adjustment = StockAdjustment::create($data);
        $adjustment->created_at = $adjustmentDate->setTimeFrom(now());
        $adjustment->updated_at = $adjustment->created_at;
        $adjustment->save();

        // Update ledger & summary
        app('App\\Services\\StockService')->manualAdjust(
            $data['company_id'],
            $data['warehouse_id'],
            $data['item_id'],
            $data['variant_id'],
            $data['qty_adjustment'],
            $data['reason'] ?? 'Manual adjustment'
        );

        return redirect()->route('inventory.adjustments.index')->with('success', 'Adjustment recorded.');
    }

    public function summary(Request $r)
    {
        $data = $r->validate([
            'item_id' => 'required|exists:items,id',
            'variant_id' => 'nullable|exists:item_variants,id',
            'warehouse_id' => 'nullable|exists:warehouses,id',
        ]);

        if (!empty($data['variant_id'])) {
            $variantMatches = ItemVariant::where('id', $data['variant_id'])
                ->where('item_id', $data['item_id'])
                ->exists();
            if (!$variantMatches) {
                return response()->json(['message' => 'Variant tidak sesuai dengan item yang dipilih.'], 422);
            }
        }

        $summary = StockSummary::where('item_id', $data['item_id'])
            ->when($data['warehouse_id'] ?? null, fn($q)=>$q->where('warehouse_id', $data['warehouse_id']))
            ->when($data['variant_id'] ?? null, fn($q)=>$q->where('variant_id', $data['variant_id']))
            ->first();

        return response()->json([
            'qty_balance' => (float) ($summary->qty_balance ?? 0),
            'uom' => $summary->uom ?? null,
        ]);
    }
}

