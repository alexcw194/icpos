<?php

namespace App\Http\Controllers;

use App\Models\{PurchaseOrder, PurchaseOrderLine, GoodsReceipt, GoodsReceiptLine, Item, ItemVariant};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\Company;
use App\Models\Warehouse;

class PurchaseOrderController extends Controller
{
    public function index(Request $request) {
        $type = $request->input('type', 'item');
        $type = in_array($type, ['item','project'], true) ? $type : 'item';

        $pos = PurchaseOrder::withCount('lines')
            ->where('purchase_type', $type)
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return view('po.index', compact('pos', 'type'));
    }

    public function create(Request $request) {
        $type = $request->input('type', 'item');
        $type = in_array($type, ['item','project'], true) ? $type : 'item';

        $companies = Company::orderBy('name')->get(['id','name','alias']);
        $warehouses = Warehouse::orderBy('name')->get(['id','name']);
        $items = Item::orderBy('name')->get(['id','sku','name']);

        return view('po.create', compact('companies', 'warehouses', 'items', 'type'));
    }

    public function store(Request $r) {
        // Minimal: validasi ringkas (sesuaikan)
        $po = DB::transaction(function () use ($r) {
            $po = PurchaseOrder::create([
                'company_id'  => $r->company_id,
                'supplier_id' => $r->supplier_id,
                'warehouse_id'=> $r->warehouse_id,
                'number'      => $r->number,
                'order_date'  => $r->order_date,
                'status'      => 'draft',
                'purchase_type' => $r->purchase_type ?? 'item',
                'notes'       => $r->notes,
            ]);
            foreach ($r->lines ?? [] as $ln) {
                $item = Item::findOrFail($ln['item_id']);
                $variantId = $ln['item_variant_id'] ?? null;
                PurchaseOrderLine::create([
                    'purchase_order_id'  => $po->id,
                    'item_id'            => $item->id,
                    'item_variant_id'    => $variantId,
                    'item_name_snapshot' => $item->name,
                    'sku_snapshot'       => $variantId ? optional(ItemVariant::find($variantId))->sku : $item->sku,
                    'qty_ordered'        => $ln['qty_ordered'],
                    'uom'                => $ln['uom'] ?? null,
                    'unit_price'         => $ln['unit_price'] ?? 0,
                    'line_total'         => ($ln['unit_price'] ?? 0) * $ln['qty_ordered'],
                ]);
            }
            return $po;
        });
        return redirect()->route('po.show', $po);
    }

    public function approve(PurchaseOrder $po) {
        abort_if($po->status !== 'draft', 400, 'Invalid state');
        $po->update(['status' => 'approved', 'approved_at' => now(), 'approved_by' => auth()->id()]);
        return back()->with('success', 'PO approved');
    }

    public function show(PurchaseOrder $po) {
        $po->load('lines.item','lines.variant');
        return view('po.show', compact('po'));
    }

    /** Receive entry point: create a GR draft from PO lines (remaining qty) */
    public function receive(PurchaseOrder $po) {
        $po->load('lines');
        return view('po.receive', compact('po'));
    }

    /** Persist GR draft (not posted) */
    public function receiveStore(Request $r, PurchaseOrder $po) {
        $gr = DB::transaction(function () use ($r, $po) {
            $gr = GoodsReceipt::create([
                'company_id'      => $po->company_id,
                'warehouse_id'    => $po->warehouse_id,
                'purchase_order_id'=> $po->id,
                'number'          => $r->number,
                'gr_date'         => $r->gr_date,
                'status'          => 'draft',
                'notes'           => $r->notes,
            ]);
            foreach ($r->lines ?? [] as $ln) {
                if (($ln['qty_received'] ?? 0) > 0) {
                    $line = $po->lines()->findOrFail($ln['po_line_id']);
                    GoodsReceiptLine::create([
                        'goods_receipt_id'  => $gr->id,
                        'item_id'           => $line->item_id,
                        'item_variant_id'   => $line->item_variant_id,
                        'item_name_snapshot'=> $line->item_name_snapshot,
                        'sku_snapshot'      => $line->sku_snapshot,
                        'qty_received'      => $ln['qty_received'],
                        'uom'               => $line->uom,
                        'unit_cost'         => $ln['unit_cost'] ?? $line->unit_price,
                        'line_total'        => ($ln['unit_cost'] ?? $line->unit_price) * $ln['qty_received'],
                    ]);
                }
            }
            return $gr;
        });
        return redirect()->route('gr.show', $gr);
    }
}
