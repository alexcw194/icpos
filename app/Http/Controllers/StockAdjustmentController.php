<?php

namespace App\Http\Controllers;

use App\Models\StockAdjustment;
use App\Models\StockSummary;
use App\Models\Item;
use Illuminate\Http\Request;

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
        $item = Item::find($r->item_id);
        $summary = StockSummary::where('item_id', $item->id)
            ->when($r->warehouse_id, fn($q)=>$q->where('warehouse_id',$r->warehouse_id))
            ->first();

        return view('inventory.adjustment_create', compact('item','summary'));
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
