<?php

namespace App\Http\Controllers;

use App\Models\StockSummary;
use App\Models\Warehouse;
use Illuminate\Http\Request;

class StockSummaryController extends Controller
{
    public function index(Request $request)
    {
        $query = StockSummary::with(['item','variant','warehouse'])
            ->orderBy('warehouse_id')
            ->orderBy('item_id');

        if ($request->filled('warehouse_id')) {
            $query->where('warehouse_id', $request->warehouse_id);
        }

        $summaries = $query->paginate(50);
        $warehouses = Warehouse::orderBy('name')->get();

        return view('inventory.summary', compact('summaries', 'warehouses'));
    }
}
