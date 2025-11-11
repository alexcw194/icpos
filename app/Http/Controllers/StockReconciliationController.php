<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\StockLedger;
use App\Models\ItemStock;

class StockReconciliationController extends Controller
{
    public function index()
    {
        $ledgerBalances = StockLedger::select(
                'company_id', 'warehouse_id', 'item_id', 'item_variant_id',
                DB::raw('SUM(qty_change) as calc_balance')
            )
            ->groupBy('company_id', 'warehouse_id', 'item_id', 'item_variant_id')
            ->get();

        $stockBalances = ItemStock::select(
            'company_id', 'warehouse_id', 'item_id', 'item_variant_id', 'qty_on_hand as qty_balance'
        )->get();

        $rows = [];
        foreach ($ledgerBalances as $ledger) {
            $match = $stockBalances
                ->where('company_id', $ledger->company_id)
                ->where('warehouse_id', $ledger->warehouse_id)
                ->where('item_id', $ledger->item_id)
                ->where('item_variant_id', $ledger->item_variant_id)
                ->first();

            $rows[] = [
                'company_id' => $ledger->company_id,
                'warehouse_id' => $ledger->warehouse_id,
                'item_id' => $ledger->item_id,
                'item_variant_id' => $ledger->item_variant_id,
                'ledger_balance' => (float) $ledger->calc_balance,
                'stock_balance' => $match ? (float) $match->qty_balance : 0,
                'difference' => (float) $ledger->calc_balance - ($match ? (float) $match->qty_balance : 0),
            ];
        }

        return view('stocks.reconcile', compact('rows'));
    }
}
