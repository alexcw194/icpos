<?php

namespace App\Http\Controllers;

use App\Models\StockSummary;
use App\Models\Warehouse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class StockSummaryController extends Controller
{
    public function index(Request $request)
    {
        $query = StockSummary::query()
            ->from('stock_summaries as ss')
            ->leftJoin('warehouses as w', 'w.id', '=', 'ss.warehouse_id')
            ->leftJoin('items as i', 'i.id', '=', 'ss.item_id')
            ->leftJoin('item_variants as v', 'v.id', '=', 'ss.variant_id')
            ->select([
                'ss.company_id',
                'ss.warehouse_id',
                'ss.item_id',
                'ss.variant_id',
                'w.name as warehouse_name',
                'i.name as item_name',
                'v.sku as variant_sku',
                DB::raw('SUM(ss.qty_balance) as qty_balance'),
                DB::raw('MAX(ss.uom) as uom'),
                DB::raw('MAX(ss.updated_at) as updated_at'),
            ])
            ->groupBy(
                'ss.company_id',
                'ss.warehouse_id',
                'ss.item_id',
                'ss.variant_id',
                'w.name',
                'i.name',
                'v.sku'
            )
            ->orderBy('ss.warehouse_id')
            ->orderBy('ss.item_id')
            ->orderBy('ss.variant_id');

        if ($request->filled('warehouse_id')) {
            $query->where('ss.warehouse_id', $request->warehouse_id);
        }

        $summaries = $query->paginate(50);
        $warehouses = Warehouse::orderBy('name')->get();

        return view('inventory.summary', compact('summaries', 'warehouses'));
    }
}
