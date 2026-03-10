<?php

namespace App\Http\Controllers;

use App\Models\ItemVariant;
use App\Models\StockSummary;
use App\Models\Warehouse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class StockSummaryController extends Controller
{
    public function index(Request $request)
    {
        $listType = (string) $request->input('list_type', '');
        if (!in_array($listType, ['', 'retail', 'project'], true)) {
            $listType = '';
        }

        $query = StockSummary::query()
            ->from('stock_summaries as ss')
            ->leftJoin('warehouses as w', 'w.id', '=', 'ss.warehouse_id')
            ->leftJoin('items as i', 'i.id', '=', 'ss.item_id')
            ->leftJoin('item_variants as v', 'v.id', '=', 'ss.variant_id')
            ->select([
                'ss.warehouse_id',
                'ss.item_id',
                'ss.variant_id',
                'w.name as warehouse_name',
                'i.name as item_name',
                'i.variant_type as item_variant_type',
                'v.sku as variant_sku',
                DB::raw('(select count(*) from item_variants iv where iv.item_id = ss.item_id) as item_variants_count'),
                DB::raw('SUM(ss.qty_balance) as qty_balance'),
                DB::raw('MAX(ss.uom) as uom'),
                DB::raw('MAX(ss.updated_at) as updated_at'),
            ])
            ->groupBy(
                'ss.warehouse_id',
                'ss.item_id',
                'ss.variant_id',
                'w.name',
                'i.name',
                'i.variant_type',
                'v.sku'
            )
            ->orderBy('ss.warehouse_id')
            ->orderBy('ss.item_id')
            ->orderBy('ss.variant_id');

        if ($request->filled('warehouse_id')) {
            $query->where('ss.warehouse_id', $request->warehouse_id);
        }
        if ($listType !== '') {
            $query->where('i.list_type', $listType);
        }

        $summaries = $query->paginate(50);
        $warehouses = Warehouse::query()
            ->orderBy('name')
            ->get();
        $variantIds = $summaries->getCollection()
            ->pluck('variant_id')
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();
        $variantLabels = [];

        if ($variantIds->isNotEmpty()) {
            $variantLabels = ItemVariant::query()
                ->with('item:id,name,variant_type,name_template,sku')
                ->whereIn('id', $variantIds->all())
                ->get()
                ->mapWithKeys(function (ItemVariant $variant) {
                    $label = trim((string) ($variant->label ?? ''));
                    if ($label === '') {
                        $label = trim((string) ($variant->sku ?? ''));
                    }
                    return [(int) $variant->id => $label];
                })
                ->all();
        }

        return view('inventory.summary', compact('summaries', 'warehouses', 'variantLabels', 'listType'));
    }
}
