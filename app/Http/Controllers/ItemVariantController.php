<?php

namespace App\Http\Controllers;

use App\Models\Item;
use App\Models\ItemVariant;
use App\Models\Size;
use App\Models\Color;
use Illuminate\Http\Request;

class ItemVariantController extends Controller
{
    public function index(Item $item)
    {
        $item->load(['variants' => fn($q) => $q->with('item')]);
        return view('items.variants.index', compact('item'));
    }


    public function create(Item $item)
    {
        $attrOptions = $this->attributeOptions($item);

        return view('items.variants.create', [
            'item'           => $item,
            'colorOptions'   => $attrOptions['color'],
            'sizeOptions'    => $attrOptions['size'],
            'lengthOptions'  => $attrOptions['length'],
        ]);
    }

    public function store(Request $request, Item $item)
    {
        $data = $request->validate([
            'sku'         => ['nullable', 'string', 'max:255', 'unique:item_variants,sku'],
            'price'       => ['nullable', 'string', 'max:50'],   // string biar bisa "1.234.567,89"
            'stock'       => ['nullable', 'string', 'max:50'],   // string biar bisa "1.000"
            'is_active'   => ['nullable', 'boolean'],
            'barcode'     => ['nullable', 'string', 'max:64'],
            'min_stock'   => ['nullable', 'integer', 'min:0'],
            'attr_color'  => ['nullable', 'string', 'max:50'],
            'attr_size'   => ['nullable', 'string', 'max:50'],
            'attr_length' => ['nullable', 'string', 'max:50'],
        ]);

        $attrs = array_filter([
            'color'  => $data['attr_color']  ?? null,
            'size'   => $data['attr_size']   ?? null,
            'length' => $data['attr_length'] ?? null,
        ], fn ($v) => trim((string) $v) !== '');

        $variant = $item->variants()->create([
            'sku'        => $data['sku'] ?? null,        // mutator handle uppercase/null
            'price'      => $data['price'] ?? null,      // mutator parse ID/EN format
            'stock'      => $data['stock'] ?? null,      // mutator parse int & clamp >= 0
            'attributes' => $attrs ?: null,
            'is_active'  => $request->boolean('is_active', true),
            'barcode'    => $data['barcode'] ?? null,
            'min_stock'  => (int) ($data['min_stock'] ?? 0),
        ]);

        $this->syncVariantMeta($item);

        return redirect()
            ->route('items.variants.index', $item)
            ->with('success', 'Variant created: ' . $variant->label);
    }

    public function quickSearch(Request $request)
    {
        $q = trim((string) $request->get('q'));

        $variants = ItemVariant::query()
            ->with(['item','size','color'])
            ->when($q !== '', function ($qq) use ($q) {
                $qq->whereHas('item', fn($i) =>
                    $i->where('name', 'like', "%{$q}%")
                );
            })
            ->orderBy('label')
            ->limit(20)
            ->get();

        return $variants->map(fn ($v) => [
            'id'   => $v->id,
            'text' => $v->label, // ✅ CLEAN, NO SKU
        ]);
    }

    public function edit(ItemVariant $variant)
    {
        $variant->load('item');
        $item = $variant->item;
        $attrOptions = $this->attributeOptions($item);

        return view('items.variants.edit', [
            'item'           => $item,
            'variant'        => $variant,
            'colorOptions'   => $attrOptions['color'],
            'sizeOptions'    => $attrOptions['size'],
            'lengthOptions'  => $attrOptions['length'],
        ]);
    }

    public function update(Request $request, ItemVariant $variant)
    {
        $variant->load('item');

        $data = $request->validate([
            'sku'         => ['nullable', 'string', 'max:255', 'unique:item_variants,sku,' . $variant->id],
            'price'       => ['nullable', 'string', 'max:50'], // string: "1.234.567,89"
            'stock'       => ['nullable', 'string', 'max:50'], // string: "1.000"
            'is_active'   => ['nullable', 'boolean'],
            'barcode'     => ['nullable', 'string', 'max:64'],
            'min_stock'   => ['nullable', 'integer', 'min:0'],
            'attr_color'  => ['nullable', 'string', 'max:50'],
            'attr_size'   => ['nullable', 'string', 'max:50'],
            'attr_length' => ['nullable', 'string', 'max:50'],
        ]);

        $attrs = array_filter([
            'color'  => $data['attr_color']  ?? null,
            'size'   => $data['attr_size']   ?? null,
            'length' => $data['attr_length'] ?? null,
        ], fn ($v) => trim((string) $v) !== '');

        $variant->update([
            'sku'        => $data['sku'] ?? null,        // mutator: uppercase/null
            'price'      => $data['price'] ?? null,      // mutator: parse ID format
            'stock'      => $data['stock'] ?? null,      // mutator: int + clamp >=0
            'attributes' => $attrs ?: null,
            'is_active'  => $request->boolean('is_active', true),
            'barcode'    => $data['barcode'] ?? null,
            'min_stock'  => (int) ($data['min_stock'] ?? 0),
        ]);

        $this->syncVariantMeta($variant->item);

        return redirect()
            ->route('items.variants.index', $variant->item)
            ->with('success', 'Variant updated!');
    }


    public function destroy(ItemVariant $variant)
    {
        $item = $variant->item;
        $variant->delete();
        $this->syncVariantMeta($item);

        return redirect()->route('items.variants.index', $item)
            ->with('success', 'Variant deleted!');
    }

    private function normalizeSku(?string $sku): ?string
    {
        $sku = trim((string) $sku);
        return $sku === '' ? null : mb_strtoupper($sku, 'UTF-8');
    }

    /**
     * "1.234,56" => 1234.56; kosong => 0; stok negatif => 0
     */
   /*  private function normalizeNumbers($priceInput, $stockInput): array
    {
        $toDecimal = function ($s) {
            if ($s === null) {
                return 0;
            }
            $s = preg_replace('/[^\\d,\\.\\-]/', '', (string) $s);
            $s = str_replace('.', '', $s);
            $s = str_replace(',', '.', $s);
            $f = (float) $s;
            return is_finite($f) ? $f : 0;
        };
        $price = $toDecimal($priceInput);
        $stock = (int) $toDecimal($stockInput);
        if ($stock < 0) {
            $stock = 0;
        }
        return [$price, $stock];
    } */

    private function attributeOptions(Item $item): array
    {
        $colors = Color::active()->ordered()->pluck('name')->all();
        $sizes  = Size::active()->ordered()->pluck('name')->all();
        $variantOptions = is_array($item->variant_options) ? $item->variant_options : [];

        $merge = fn($master, $custom) => collect($master)
            ->merge(is_array($custom) ? $custom : [])
            ->filter(fn($value) => (string) $value !== '')
            ->unique()
            ->values()
            ->all();

        return [
            'color'  => $merge($colors, $variantOptions['color'] ?? []),
            'size'   => $merge($sizes, $variantOptions['size'] ?? []),
            'length' => collect($variantOptions['length'] ?? [])
                ->filter(fn($value) => (string) $value !== '')
                ->unique()
                ->values()
                ->all(),
        ];
    }

    private function syncVariantMeta(Item $item): void
    {
        $item->load(['variants' => fn($q) => $q->orderBy('id')]);

        $type = $this->determineVariantType($item->variants);

        if ($item->variant_type !== $type) {
            $item->forceFill(['variant_type' => $type])->save();
        }
    }

    private function determineVariantType($variants): string
    {
        if ($variants->isEmpty()) {
            return 'none';
        }

        $hasColor  = $variants->contains(fn($v) => !empty(($v->attributes['color'] ?? null)));
        $hasSize   = $variants->contains(fn($v) => !empty(($v->attributes['size'] ?? null)));
        $hasLength = $variants->contains(fn($v) => !empty(($v->attributes['length'] ?? null)));

        if ($hasColor && $hasSize) {
            return 'color_size';
        }
        if ($hasColor) {
            return 'color';
        }
        if ($hasSize) {
            return 'size';
        }
        if ($hasLength) {
            return 'length';
        }

        return 'none';
    }
}
