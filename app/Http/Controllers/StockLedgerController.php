<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Company;
use App\Models\StockLedger;
use App\Models\Warehouse;
use App\Models\Item;

class StockLedgerController extends Controller
{
    public function index(Request $request)
    {
        $companyId = (int) (
            $request->input('company_id')
            ?: auth()->user()?->company_id
            ?: Company::where('is_default', true)->value('id')
        );

        $query = StockLedger::with([
            'warehouse',
            'item' => fn ($q) => $q->withCount('variants'),
            'variant' => fn ($q) => $q->with('item:id,name,variant_type,name_template,sku'),
            'createdBy',
        ])
            ->orderByDesc('created_at');

        if ($companyId > 0) {
            $query->where('company_id', $companyId);
        }

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
        $warehouses = Warehouse::query()
            ->when($companyId > 0, fn ($q) => $q->where('company_id', $companyId))
            ->orderBy('name')
            ->get();
        $items = Item::query()
            ->when($companyId > 0, fn ($q) => $q->where('company_id', $companyId))
            ->orderBy('name')
            ->limit(200)
            ->get();

        return view('inventory.ledger', compact('ledgers', 'warehouses', 'items'));
    }
}
