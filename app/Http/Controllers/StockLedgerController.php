<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\StockLedger;
use App\Models\Warehouse;
use App\Models\Item;

class StockLedgerController extends Controller
{
    public function index(Request $request)
    {
        $listType = (string) $request->input('list_type', '');
        if (!in_array($listType, ['', 'retail', 'project'], true)) {
            $listType = '';
        }

        $query = StockLedger::with([
            'warehouse',
            'item' => fn ($q) => $q->withCount('variants'),
            'variant' => fn ($q) => $q->with('item:id,name,variant_type,name_template,sku'),
            'createdBy',
        ])
            ->orderByDesc('created_at');

        if ($request->filled('warehouse_id')) {
            $query->where('warehouse_id', $request->warehouse_id);
        }

        if ($listType !== '') {
            $query->whereHas('item', fn ($q) => $q->where('list_type', $listType));
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
        $warehouses = Warehouse::query()
            ->orderBy('name')
            ->get();
        $items = Item::query()
            ->when($listType !== '', fn ($q) => $q->where('list_type', $listType))
            ->orderBy('name')
            ->limit(200)
            ->get();

        return view('inventory.ledger', compact('ledgers', 'warehouses', 'items', 'listType'));
    }
}
