<?php

namespace App\Http\Controllers;

use App\Models\StockAdjustment;
use App\Models\StockSummary;
use App\Models\Item;
use App\Models\ItemVariant;
use App\Models\Warehouse;
use App\Models\Company;
use App\Services\StockService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class StockAdjustmentController extends Controller
{
    public function index(Request $request)
    {
        $listType = (string) $request->input('list_type', '');
        if (!in_array($listType, ['', 'retail', 'project'], true)) {
            $listType = '';
        }

        $adjustments = StockAdjustment::with(['item','variant','warehouse'])
            ->when($listType !== '', fn ($q) => $q->whereHas('item', fn ($w) => $w->where('list_type', $listType)))
            ->latest()
            ->paginate(50)
            ->withQueryString();

        return view('inventory.adjustments', compact('adjustments', 'listType'));
    }

    public function create(Request $r)
    {
        $listType = (string) $r->input('list_type', '');
        if (!in_array($listType, ['', 'retail', 'project'], true)) {
            $listType = '';
        }

        $companyId = (int) ($r->company_id ?? Company::where('is_default', true)->value('id'));

        $items = Item::query()
            ->with('unit:id,code')
            ->withCount('variants')
            ->when($listType !== '', fn ($q) => $q->where('list_type', $listType))
            ->orderBy('name')
            ->get(['id', 'name', 'sku', 'unit_id', 'variant_type']);

        $variants = ItemVariant::query()
            ->with(['item:id,name,unit_id,variant_type,name_template', 'item.unit:id,code'])
            ->when($listType !== '', fn ($q) => $q->whereHas('item', fn ($w) => $w->where('list_type', $listType)))
            ->orderBy('item_id')
            ->orderBy('id')
            ->get(['id', 'item_id', 'sku', 'attributes', 'is_active']);

        $warehouses = Warehouse::query()
            ->forCompany($companyId)
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name']);

        $selectedItemId = $r->input('item_id');
        $selectedVariantId = $r->input('variant_id');
        $selectedWarehouseId = $r->input('warehouse_id');
        if (!$selectedWarehouseId && $warehouses->isNotEmpty()) {
            $selectedWarehouseId = (string) $warehouses->first()->id;
        }

        $summary = null;
        if ($selectedItemId) {
            $summaryQuery = StockSummary::where('item_id', $selectedItemId)
                ->when($selectedWarehouseId, fn($q)=>$q->where('warehouse_id', $selectedWarehouseId))
                ->when($selectedVariantId, fn($q)=>$q->where('variant_id', $selectedVariantId));

            $summary = (object) [
                'qty_balance' => (float) (clone $summaryQuery)->sum('qty_balance'),
                'uom' => (clone $summaryQuery)->latest('id')->value('uom'),
            ];
        }

        $activeVariants = $variants->filter(fn ($variant) => $this->isVariantActiveValue($variant->is_active ?? null));
        $itemsWithActiveVariants = $activeVariants->pluck('item_id')->map(fn ($id) => (int) $id)->unique()->all();
        $itemsWithActiveVariants = array_flip($itemsWithActiveVariants);
        $itemsWithoutVariants = $items
            ->filter(fn ($it) => !isset($itemsWithActiveVariants[(int) $it->id]))
            ->values();
        $itemOptions = [];

        foreach ($itemsWithoutVariants as $it) {
            $itemOptions[] = [
                'value' => 'item:' . $it->id,
                'label' => $it->name,
                'item_id' => $it->id,
                'variant_id' => null,
                'unit' => optional($it->unit)->code ?? 'pcs',
                'sku' => $it->sku ?? '',
            ];
        }

        foreach ($activeVariants as $v) {
            $item = $v->item;
            if (!$item) {
                continue;
            }
            $variantLabel = trim((string) ($v->label ?? ""));
            if ($variantLabel === "") {
                $variantLabel = $item->renderVariantDisplayName(
                    is_array($v->attributes) ? $v->attributes : [],
                    $v->sku
                );
            }
            $itemOptions[] = [
                "value" => "variant:" . $v->id,
                "label" => $variantLabel,
                "item_id" => $item->id,
                "variant_id" => $v->id,
                "unit" => optional($item->unit)->code ?? "pcs",
                "sku" => $v->sku ?? $item->sku ?? "",
            ];
        }

        return view('inventory.adjustment_create', compact(
            'summary',
            'items',
            'itemOptions',
            'warehouses',
            'selectedItemId',
            'selectedVariantId',
            'companyId',
            'listType'
        ));
    }

    public function store(Request $r)
    {
        $companyId = $r->input('company_id')
            ?: auth()->user()?->company_id
            ?: Company::where('is_default', true)->value('id');

        if ($companyId) {
            $r->merge(['company_id' => $companyId]);
        }

        $data = $r->validate([
            'company_id' => 'required|exists:companies,id',
            'warehouse_id' => 'required|exists:warehouses,id',
            'item_id' => 'required|exists:items,id',
            'variant_id' => 'nullable|exists:item_variants,id',
            'qty_adjustment' => 'required|numeric',
            'reason' => 'nullable|string',
            'adjustment_date' => 'required|date',
        ]);

        $hasActiveVariants = ItemVariant::query()
            ->where('item_id', $data['item_id'])
            ->where(function ($q) {
                $q->where('is_active', true)->orWhereNull('is_active');
            })
            ->exists();

        if (!empty($data['variant_id'])) {
            $variant = ItemVariant::where('id', $data['variant_id'])
                ->where('item_id', $data['item_id'])
                ->first(['id', 'item_id', 'is_active']);
            if (!$variant) {
                return back()
                    ->withErrors(['variant_id' => 'Variant tidak sesuai dengan item yang dipilih.'])
                    ->withInput();
            }
            if ($hasActiveVariants && !$this->isVariantActiveValue($variant->is_active)) {
                return back()
                    ->withErrors(['variant_id' => 'Varian tidak aktif untuk transaksi baru.'])
                    ->withInput();
            }
        } elseif ($hasActiveVariants) {
            return back()
                ->withErrors(['variant_id' => 'Item ini memiliki varian aktif. Pilih varian yang sesuai.'])
                ->withInput();
        }

        if (!empty($data['warehouse_id'])) {
            $warehouseAllowed = Warehouse::query()
                ->forCompany((int) $data['company_id'])
                ->whereKey((int) $data['warehouse_id'])
                ->exists();
            if (!$warehouseAllowed) {
                return back()
                    ->withErrors(['warehouse_id' => 'Warehouse tidak terhubung ke company yang dipilih.'])
                    ->withInput();
            }
        }

        $adjustmentDate = Carbon::parse($data['adjustment_date']);
        unset($data['adjustment_date']);
        DB::transaction(function () use ($data, $adjustmentDate) {
            $data['created_by'] = auth()->id();

            $adjustment = StockAdjustment::create($data);
            $adjustment->created_at = $adjustmentDate->setTimeFrom(now());
            $adjustment->updated_at = $adjustment->created_at;
            $adjustment->save();

            app(StockService::class)->manualAdjust(
                companyId: (int) $data['company_id'],
                warehouseId: (int) $data['warehouse_id'],
                itemId: (int) $data['item_id'],
                variantId: $data['variant_id'] ? (int) $data['variant_id'] : null,
                qtyAdjustment: (float) $data['qty_adjustment'],
                reason: $data['reason'] ?? 'Manual adjustment',
                referenceId: (int) $adjustment->id,
                ledgerDate: $adjustment->created_at,
                actingUserId: $data['created_by'] ? (int) $data['created_by'] : null
            );
        });

        return redirect()->route('inventory.adjustments.index')->with('success', 'Adjustment recorded.');
    }

    public function summary(Request $r)
    {
        $data = $r->validate([
            'item_id' => 'required|exists:items,id',
            'variant_id' => 'nullable|exists:item_variants,id',
            'warehouse_id' => 'nullable|exists:warehouses,id',
        ]);

        $hasActiveVariants = ItemVariant::query()
            ->where('item_id', $data['item_id'])
            ->where(function ($q) {
                $q->where('is_active', true)->orWhereNull('is_active');
            })
            ->exists();

        if (!empty($data['variant_id'])) {
            $variant = ItemVariant::where('id', $data['variant_id'])
                ->where('item_id', $data['item_id'])
                ->first(['id', 'item_id', 'is_active']);
            if (!$variant) {
                return response()->json(['message' => 'Variant tidak sesuai dengan item yang dipilih.'], 422);
            }
            if ($hasActiveVariants && !$this->isVariantActiveValue($variant->is_active)) {
                return response()->json(['message' => 'Varian tidak aktif untuk transaksi baru.'], 422);
            }
        } elseif ($hasActiveVariants) {
            return response()->json(['message' => 'Item ini memiliki varian aktif. Pilih varian yang sesuai.'], 422);
        }

        $summaryQuery = StockSummary::where('item_id', $data['item_id'])
            ->when($data['warehouse_id'] ?? null, fn($q)=>$q->where('warehouse_id', $data['warehouse_id']))
            ->when($data['variant_id'] ?? null, fn($q)=>$q->where('variant_id', $data['variant_id']));

        $summary = (clone $summaryQuery)->latest('id')->first();
        $qtyBalance = (float) (clone $summaryQuery)->sum('qty_balance');

        return response()->json([
            'qty_balance' => $qtyBalance,
            'uom' => $summary->uom ?? null,
        ]);
    }

    private function isVariantActiveValue($isActive): bool
    {
        return $isActive === null || (bool) $isActive;
    }
}

