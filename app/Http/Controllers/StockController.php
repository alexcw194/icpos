<?php

namespace App\Http\Controllers;

use App\Models\Item;
use App\Models\ItemVariant;
use App\Services\StockService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Carbon\Carbon;
use RuntimeException;

class StockController extends Controller
{
    public function adjust(Request $r, Item $item)
    {
        $data = $r->validate([
            'company_id'  => ['required','integer'],
            'warehouse_id'=> ['required','integer'],
            'variant_id'  => ['nullable','integer','exists:item_variants,id'],
            'type'        => ['required','in:in,out'],
            'qty'         => ['required','numeric','min:0.0001'],
            'reason'      => ['nullable','string','max:100'],
            'posted_at'   => ['nullable','date'],
        ]);

        $variantId = isset($data['variant_id']) && $data['variant_id'] !== ''
            ? (int) $data['variant_id']
            : null;
        $hasActiveVariants = $item->activeVariants()->exists();

        if ($hasActiveVariants && !$variantId) {
            throw ValidationException::withMessages([
                'variant_id' => 'Item ini memiliki varian aktif. Pilih varian yang sesuai.',
            ]);
        }

        if ($variantId) {
            $variant = ItemVariant::query()
                ->where('id', $variantId)
                ->where('item_id', (int) $item->id)
                ->first(['id', 'item_id', 'is_active']);

            if (!$variant) {
                throw ValidationException::withMessages([
                    'variant_id' => 'Variant tidak sesuai dengan item yang dipilih.',
                ]);
            }

            if ($hasActiveVariants && !($variant->is_active === null || (bool) $variant->is_active)) {
                throw ValidationException::withMessages([
                    'variant_id' => 'Varian tidak aktif untuk transaksi baru.',
                ]);
            }
        }

        $qty = (float) $data['qty'];
        $delta = $data['type'] === 'in' ? $qty : -1 * $qty;

        try {
            app(StockService::class)->manualAdjust(
                companyId: (int) $data['company_id'],
                warehouseId: (int) $data['warehouse_id'],
                itemId: (int) $item->id,
                variantId: $variantId,
                qtyAdjustment: $delta,
                reason: $data['reason'] ?? null,
                referenceId: null,
                ledgerDate: !empty($data['posted_at']) ? Carbon::parse((string) $data['posted_at']) : null,
                actingUserId: auth()->id()
            );
        } catch (RuntimeException $e) {
            throw ValidationException::withMessages([
                'qty' => $e->getMessage(),
            ]);
        }

        return back()->with('success','Penyesuaian stok tercatat.');
    }
}
