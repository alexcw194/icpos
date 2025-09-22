<?php

namespace App\Http\Controllers;

use App\Models\Item;
use App\Models\ItemVariant;
use Illuminate\Http\Request;

class ItemVariantController extends Controller
{
    public function index(Item $item)
    {
        $item->load('variants');
        return view('items.variants.index', compact('item'));
    }

    public function create(Item $item)
    {
        return view('items.variants.create', compact('item'));
    }

    public function store(Request $request, Item $item)
    {
        [$price, $stock] = $this->normalizeNumbers($request->input('price'), $request->input('stock'));

        $request->validate([
            'sku'         => ['nullable', 'string', 'max:255', 'unique:item_variants,sku'],
            'is_active'   => ['nullable', 'boolean'],
            'barcode'     => ['nullable', 'string', 'max:64'],
            'min_stock'   => ['nullable', 'integer', 'min:0'],
            'attr_color'  => ['nullable', 'string', 'max:50'],
            'attr_size'   => ['nullable', 'string', 'max:50'],
            'attr_length' => ['nullable', 'numeric', 'min:0'],
        ]);

        $attrs = [];
        if ($request->filled('attr_color')) {
            $attrs['color'] = (string) $request->input('attr_color');
        }
        if ($request->filled('attr_size')) {
            $attrs['size'] = (string) $request->input('attr_size');
        }
        if ($request->filled('attr_length')) {
            $attrs['length'] = (float) $request->input('attr_length');
        }

        $variant = $item->variants()->create([
            'sku'        => $this->normalizeSku($request->input('sku')),
            'price'      => $price,
            'stock'      => $stock,
            'attributes' => count($attrs) ? $attrs : null,
            'is_active'  => $request->boolean('is_active', true),
            'barcode'    => $request->input('barcode'),
            'min_stock'  => (int) ($request->input('min_stock') ?? 0),
        ]);

        return redirect()->route('items.variants.index', $item)
            ->with('success', 'Variant created: ' . $variant->label);
    }

    public function edit(ItemVariant $variant)
    {
        $variant->load('item');
        $item = $variant->item;
        return view('items.variants.edit', compact('item', 'variant'));
    }

    public function update(Request $request, ItemVariant $variant)
    {
        $variant->load('item');
        [$price, $stock] = $this->normalizeNumbers($request->input('price'), $request->input('stock'));

        $request->validate([
            'sku'         => ['nullable', 'string', 'max:255', 'unique:item_variants,sku,' . $variant->id],
            'is_active'   => ['nullable', 'boolean'],
            'barcode'     => ['nullable', 'string', 'max:64'],
            'min_stock'   => ['nullable', 'integer', 'min:0'],
            'attr_color'  => ['nullable', 'string', 'max:50'],
            'attr_size'   => ['nullable', 'string', 'max:50'],
            'attr_length' => ['nullable', 'numeric', 'min:0'],
        ]);

        $attrs = [];
        if ($request->filled('attr_color')) {
            $attrs['color'] = (string) $request->input('attr_color');
        }
        if ($request->filled('attr_size')) {
            $attrs['size'] = (string) $request->input('attr_size');
        }
        if ($request->filled('attr_length')) {
            $attrs['length'] = (float) $request->input('attr_length');
        }

        $variant->update([
            'sku'        => $this->normalizeSku($request->input('sku')),
            'price'      => $price,
            'stock'      => $stock,
            'attributes' => count($attrs) ? $attrs : null,
            'is_active'  => $request->boolean('is_active', true),
            'barcode'    => $request->input('barcode'),
            'min_stock'  => (int) ($request->input('min_stock') ?? 0),
        ]);

        return redirect()->route('items.variants.index', $variant->item)
            ->with('success', 'Variant updated!');
    }

    public function destroy(ItemVariant $variant)
    {
        $item = $variant->item;
        $variant->delete();
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
    private function normalizeNumbers($priceInput, $stockInput): array
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
    }
}
