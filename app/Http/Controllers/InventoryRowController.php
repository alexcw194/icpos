<?php

namespace App\Http\Controllers;

use App\Models\Item;
use App\Services\InventoryRowBuilder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

class InventoryRowController extends Controller
{
    public function search(Request $request, InventoryRowBuilder $builder)
    {
        $q = trim((string) $request->input('q', ''));
        $allowEmpty = $request->boolean('allow_empty', false);
        if ($q === '' && !$allowEmpty) {
            return response()->json([]);
        }

        $limit = (int) $request->input('limit', 200);
        if ($limit < 1) $limit = 1;
        if ($limit > 200) $limit = 200;

        $entity = (string) $request->input('entity', 'variant');
        $entity = in_array($entity, ['variant','item','all'], true) ? $entity : 'variant';
        $itemType = (string) $request->input('item_type', '');
        $listType = (string) $request->input('list_type', '');
        $allowedTypes = ['standard','kit','cut_raw','cut_piece'];
        $itemType = in_array($itemType, $allowedTypes, true) ? $itemType : '';
        $allowedListTypes = ['retail','project'];
        $listType = in_array($listType, $allowedListTypes, true) ? $listType : '';

        $like = '%' . $q . '%';

        $hasItemMinStock = Schema::hasColumn('items', 'min_stock');
        $hasVariantMinStock = Schema::hasColumn('item_variants', 'min_stock');

        $variantSelect = ['id','item_id','sku','price','attributes','is_active','stock','created_at'];
        if ($hasVariantMinStock) {
            $variantSelect[] = 'min_stock';
        }

        $itemSelect = [
            'id','name','sku','price','unit_id','brand_id','size_id','color_id',
            'variant_type','stock','description','created_at'
        ];
        if ($hasItemMinStock) {
            $itemSelect[] = 'min_stock';
        }

        $items = Item::query()
            ->with([
                'unit:id,code,name',
                'brand:id,name',
                'size:id,name',
                'color:id,name',
                'variants:' . implode(',', $variantSelect),
            ])
            ->when($listType !== '', fn($q) => $q->where('list_type', $listType))
            ->when($itemType !== '', fn($q) => $q->where('item_type', $itemType))
            ->when($q !== '', function ($query) use ($like) {
                $query->where(function ($w) use ($like) {
                    $w->where('name', 'like', $like)
                      ->orWhere('sku',  'like', $like)
                      ->orWhereHas('variants', function ($v) use ($like) {
                          $v->where('sku', 'like', $like)
                            ->orWhereRaw("JSON_UNQUOTE(JSON_EXTRACT(attributes, '$.color')) LIKE ?", [$like])
                            ->orWhereRaw("JSON_UNQUOTE(JSON_EXTRACT(attributes, '$.size')) LIKE ?",  [$like])
                            ->orWhereRaw("JSON_UNQUOTE(JSON_EXTRACT(attributes, '$.length')) LIKE ?",[$like]);
                      });
                });
            })
            ->orderBy('name')
            ->limit($limit)
            ->get($itemSelect);

        $filters = [
            'q'                  => $q,
            'unit_id'            => null,
            'brand_id'           => null,
            'type'               => $entity === 'all' ? 'all' : $entity,
            'stock'              => 'all',
            'sizes'              => [],
            'colors'             => [],
            'length_min'         => null,
            'length_max'         => null,
            'sort'               => 'name_asc',
            'show_variant_parent'=> false,
        ];

        $rows = $builder->buildFlatRows($items, $filters);
        if ($entity !== 'all') {
            $rows = $rows->filter(fn($row) => ($row['entity'] ?? null) === $entity);
        }
        $rows = $rows->values();

        $itemDescriptions = $items->pluck('description', 'id');
        $itemUnits = $items->pluck('unit_id', 'id');

        $options = $rows->map(function ($row) use ($itemDescriptions, $itemUnits) {
            $priceLabel = $row['price_label'] ?? '';
            $displayName = $row['display_name'] ?? '';
            $label = trim(($priceLabel ? $priceLabel . ' ' : '') . $displayName);

            $isVariant = ($row['entity'] ?? null) === 'variant';
            $uidPrefix = $isVariant ? 'variant-' : 'item-';

            return [
                'uid'         => $uidPrefix . ($isVariant ? $row['variant_id'] : $row['item_id']),
                'item_id'     => $row['item_id'],
                'variant_id'  => $isVariant ? $row['variant_id'] : null,
                'name'        => $displayName,
                'label'       => $label ?: $displayName,
                'sku'         => $row['sku'] ?? null,
                'price'       => $row['price'] ?? 0,
                'unit_code'   => $row['unit'] ?? null,
                'unit_id'     => $itemUnits[$row['item_id']] ?? null,
                'description' => (string) ($itemDescriptions[$row['item_id']] ?? ''),
            ];
        })->values();

        return response()->json($options);
    }
}
