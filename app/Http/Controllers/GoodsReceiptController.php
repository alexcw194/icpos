<?php

namespace App\Http\Controllers;

use App\Models\{GoodsReceipt, PurchaseOrder};
use App\Services\StockPostingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class GoodsReceiptController extends Controller
{
    public function show(GoodsReceipt $gr) {
        $gr->load('lines.item','lines.variant','po');
        return view('gr.show', compact('gr'));
    }

    /** POST = naikkan stok + update PO progress */
    public function post(GoodsReceipt $gr) {
        abort_if($gr->status !== 'draft', 400, 'Already posted');

        DB::transaction(function () use ($gr) {
            foreach ($gr->lines as $ln) {
                StockPostingService::postInbound(
                    companyId:      $gr->company_id,
                    warehouseId:    $gr->warehouse_id,
                    itemId:         $ln->item_id,
                    itemVariantId:  $ln->item_variant_id,
                    qty:            (float)$ln->qty_received,
                    refType:        'GR',
                    refId:          $gr->id,
                    note:           'Goods Receipt posted'
                );
            }

            // Update PO progress (qty_received)
            if ($gr->purchase_order_id) {
                /** @var PurchaseOrder $po */
                $po = $gr->po()->lockForUpdate()->first();
                foreach ($gr->lines as $ln) {
                    $poLine = $po->lines()->where('item_id', $ln->item_id)
                                          ->when($ln->item_variant_id, fn($q)=>$q->where('item_variant_id',$ln->item_variant_id))
                                          ->orderBy('id')->first();
                    if ($poLine) {
                        $poLine->increment('qty_received', $ln->qty_received);
                    }
                }
                $po->refresh();
                $po->markReceivedStats();
            }

            $gr->update([
                'status'    => 'posted',
                'posted_at' => now(),
                'posted_by' => auth()->id(),
            ]);
        });

        return back()->with('success', 'Goods Receipt posted. Stock increased.');
    }
}
