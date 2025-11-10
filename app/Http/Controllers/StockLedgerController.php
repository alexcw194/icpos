<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\StockLedger;
use App\Models\Warehouse;
use App\Models\Item;
use App\Models\ItemVariant;

class StockLedgerController extends Controller
{
    public function index(Request $request)
    {
        $query = StockLedger::with(['warehouse', 'item', 'variant', 'createdBy'])
            ->orderByDesc('created_at');

        if ($request->filled('warehouse_id')) {
            $query->where('warehouse_id', $request->warehouse_id);
        }

        if ($request->filled('item_id')) {
            $query->where('item_id', $request->item_id);
        }

        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }

        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        $ledgers = $query->paginate(50);
        $warehouses = Warehouse::orderBy('name')->get();
        $items = Item::orderBy('name')->limit(200)->get();

        return view('inventory.ledger', compact('ledgers', 'warehouses', 'items'));
    }
}
