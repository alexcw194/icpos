<?php

namespace App\Http\Controllers;

use App\Models\Item;
use App\Models\ItemStock;
use App\Models\StockLedger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Carbon\Carbon;

class StockController extends Controller
{
    public function adjust(Request $r, Item $item)
    {
        $data = $r->validate([
            'company_id'  => ['required','integer'],
            'warehouse_id'=> ['required','integer'],
            'variant_id'  => ['nullable','integer'],
            'type'        => ['required','in:in,out'],
            'qty'         => ['required','numeric','min:0.0001'],
            'reason'      => ['nullable','string','max:100'],
            'posted_at'   => ['nullable','date'],
        ]);

        DB::transaction(function () use ($item, $data) {
            // lock row (or create) for tuple company+warehouse+item(+variant)
            $stock = ItemStock::query()
                ->where('company_id',   $data['company_id'])
                ->where('warehouse_id', $data['warehouse_id'])
                ->where('item_id',      $item->id)
                ->when($data['variant_id'] ?? null,
                    fn($q)=>$q->where('item_variant_id', $data['variant_id']),
                    fn($q)=>$q->whereNull('item_variant_id'))
                ->lockForUpdate()
                ->first();

            if (!$stock) {
                $stock = new ItemStock([
                    'company_id'      => $data['company_id'],
                    'warehouse_id'    => $data['warehouse_id'],
                    'item_id'         => $item->id,
                    'item_variant_id' => $data['variant_id'] ?? null,
                ]);
                $stock->qty_on_hand = 0;
            }

            $before = (float) $stock->qty_on_hand;
            $delta  = $data['type'] === 'in' ? (float)$data['qty'] : -1 * (float)$data['qty'];
            $after  = $before + $delta;

            if ($after < -1e-9) {
                throw ValidationException::withMessages(['qty' => 'Stok tidak boleh minus.']);
            }

            // persist balance
            $stock->qty_on_hand = $after;
            $stock->save();

            // write ledger
            StockLedger::create([
                'company_id'      => $data['company_id'],
                'warehouse_id'    => $data['warehouse_id'],
                'item_id'         => $item->id,
                'item_variant_id' => $data['variant_id'] ?? null,
                'ledger_date'   => isset($data['posted_at']) && $data['posted_at']
                                    ? Carbon::parse($data['posted_at'])
                                    : now(),
                'qty_change'      => $delta,
                'balance_after'   => $after,
                'reference_type'  => 'manual_adjustment',
                'reference_id'    => null,
                'created_by'      => auth()->id(),
            ]);
        });

        return back()->with('success','Penyesuaian stok tercatat.');
    }
}
