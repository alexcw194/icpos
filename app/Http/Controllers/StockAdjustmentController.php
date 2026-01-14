<?php

namespace App\Http\Controllers;

use App\Models\StockAdjustment;
use App\Models\StockSummary;
use App\Models\Item;
use App\Models\ItemVariant;
use App\Models\Warehouse;
use App\Models\Company;
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
            ->orderBy('name')
            ->get(['id', 'name', 'sku']);

        $warehouses = Warehouse::query()
            ->when(Schema::hasColumn('warehouses', 'company_id') && $companyId, fn($q) => $q->where('company_id', $companyId))
            ->orderBy('name')
            ->get(['id', 'name']);

        $item = null;
        $summary = null;
        $variants = collect();
        $selectedVariantId = null;

        if ($r->filled('item_id')) {
            $item = Item::find($r->item_id);
        }

        $variantsAll = ItemVariant::query()
            ->with(['item:id,name,variant_type,name_template'])
            ->orderBy('id')
            ->get(['id', 'item_id', 'sku', 'attributes']);

        if ($item) {
            $variants = $variantsAll->where('item_id', $item->id)->values();
            if ($r->filled('variant_id') && $variants->contains('id', (int) $r->variant_id)) {
                $selectedVariantId = (int) $r->variant_id;
            }

            $summary = StockSummary::where('item_id', $item->id)
                ->when($r->warehouse_id, fn($q)=>$q->where('warehouse_id', $r->warehouse_id))
                ->when($selectedVariantId, fn($q)=>$q->where('variant_id', $selectedVariantId))
                ->first();
        }

        return view('inventory.adjustment_create', compact(
            'item',
            'summary',
            'items',
            'warehouses',
            'variants',
            'variantsAll',
            'selectedVariantId'
        ));
    }

    public function store(Request $r)
    {
        $data = $r->validate([
            'company_id' => 'required|exists:companies,id',
            'warehouse_id' => 'nullable|exists:warehouses,id',
            'item_id' => 'required|exists:items,id',
            'variant_id' => 'nullable|exists:item_variants,id',
            'qty_adjustment' => 'required|numeric',
            'reason' => 'nullable|string'
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
        }

        $data['created_by'] = auth()->id();
        StockAdjustment::create($data);

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
}
