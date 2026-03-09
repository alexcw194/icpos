<?php

namespace App\Http\Controllers;

use App\Models\Item;
use App\Models\ItemVariant;
use App\Models\{GoodsReceipt, PurchaseOrder};
use App\Services\StockPostingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

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
            $gr->loadMissing('lines');
            $itemIdsWithActiveVariants = Item::query()
                ->whereIn('id', $gr->lines->pluck('item_id')->filter()->unique()->values()->all())
                ->whereHas('variants', function ($q) {
                    $q->where('is_active', true)->orWhereNull('is_active');
                })
                ->pluck('id')
                ->map(fn ($id) => (int) $id)
                ->all();
            $itemIdsWithActiveVariants = array_flip($itemIdsWithActiveVariants);

            $variantIds = $gr->lines->pluck('item_variant_id')->filter()->unique()->values()->all();
            $variants = ItemVariant::query()
                ->whereIn('id', $variantIds)
                ->get(['id', 'item_id', 'is_active'])
                ->keyBy('id');

            foreach ($gr->lines as $idx => $ln) {
                $itemId = (int) $ln->item_id;
                $variantId = (int) ($ln->item_variant_id ?? 0);
                $requiresVariant = isset($itemIdsWithActiveVariants[$itemId]);

                if ($requiresVariant && $variantId <= 0) {
                    throw ValidationException::withMessages([
                        'lines' => "Line #".($idx + 1).": item memiliki varian aktif, pilih varian.",
                    ]);
                }

                if ($variantId <= 0) {
                    continue;
                }

                $variant = $variants->get($variantId);
                if (!$variant || (int) $variant->item_id !== $itemId) {
                    throw ValidationException::withMessages([
                        'lines' => "Line #".($idx + 1).": varian tidak sesuai dengan item.",
                    ]);
                }

                if ($requiresVariant && !($variant->is_active === null || (bool) $variant->is_active)) {
                    throw ValidationException::withMessages([
                        'lines' => "Line #".($idx + 1).": varian tidak aktif untuk transaksi baru.",
                    ]);
                }
            }

            foreach ($gr->lines as $ln) {
                StockPostingService::postInbound(
                    companyId:      $gr->company_id,
                    warehouseId:    $gr->warehouse_id,
                    itemId:         $ln->item_id,
                    itemVariantId:  $ln->item_variant_id,
                    qty:            (float)$ln->qty_received,
                    refType:        'GR',
                    refId:          $gr->id,
                    note:           'Goods Receipt posted',
                    unitCost:       $ln->unit_cost !== null ? (float)$ln->unit_cost : null,
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
