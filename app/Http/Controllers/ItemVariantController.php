<?php

namespace App\Http\Controllers;

use App\Models\Item;
use App\Models\ItemVariant;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ItemVariantController extends Controller
{
    public function index(Item $item)
    {
        $item->load([
            'variants' => fn ($q) => $q->orderByDesc('is_active')->orderBy('id'),
        ]);

        return view('items.variants.index', [
            'item'     => $item,
            'variants' => $item->variants,
        ]);
    }

    public function create(Item $item)
    {
        return view('items.variants.create', [
            'item' => $item,
        ]);
    }

    public function store(Request $request, Item $item)
    {
        $data = $this->validateVariant($request, $item);

        $item->variants()->create($data);

        return redirect()
            ->route('items.variants.index', $item)
            ->with('success', 'Variant created!');
    }

    public function edit(Item $item, ItemVariant $variant)
    {
        $this->ensureVariantBelongsToItem($item, $variant);

        return view('items.variants.edit', [
            'item'    => $item,
            'variant' => $variant,
        ]);
    }

    public function update(Request $request, Item $item, ItemVariant $variant)
    {
        $this->ensureVariantBelongsToItem($item, $variant);

        $data = $this->validateVariant($request, $item, $variant);

        $variant->update($data);

        return redirect()
            ->route('items.variants.index', $item)
            ->with('success', 'Variant updated!');
    }

    public function destroy(Item $item, ItemVariant $variant)
    {
        $this->ensureVariantBelongsToItem($item, $variant);

        $variant->delete();

        return redirect()
            ->route('items.variants.index', $item)
            ->with('success', 'Variant deleted!');
    }

    private function ensureVariantBelongsToItem(Item $item, ItemVariant $variant): void
    {
        if ($variant->item_id !== $item->id) {
            abort(404);
        }
    }

    private function validateVariant(Request $request, Item $item, ?ItemVariant $variant = null): array
    {
        $request->merge([
            'sku'   => $this->normalizeSku($request->input('sku')),
            'price' => $this->normalizeIdNumber($request->input('price')),
        ]);

        $data = $request->validate([
            'sku'               => [
                'nullable',
                'string',
                'max:255',
                Rule::unique('item_variants', 'sku')->ignore($variant?->id),
            ],
            'price'             => ['required', 'numeric'],
            'stock'             => ['required', 'integer', 'min:0'],
            'min_stock'         => ['nullable', 'integer', 'min:0'],
            'barcode'           => ['nullable', 'string', 'max:64'],
            'is_active'         => ['nullable', 'boolean'],
            'attribute_color'   => ['nullable', 'string', 'max:100'],
            'attribute_size'    => ['nullable', 'string', 'max:100'],
            'attribute_length'  => ['nullable', 'string', 'max:100'],
        ]);

        $attributes = [];
        foreach (['color', 'size', 'length'] as $key) {
            $value = trim((string) ($data["attribute_{$key}"] ?? ''));
            if ($value !== '') {
                $attributes[$key] = $value;
            }
            unset($data["attribute_{$key}"]);
        }

        if (array_key_exists('barcode', $data)) {
            $data['barcode'] = trim((string) $data['barcode']);
        }

        return [
            'sku'        => $data['sku'] ?? null,
            'price'      => $data['price'],
            'stock'      => (int) $data['stock'],
            'min_stock'  => array_key_exists('min_stock', $data) ? (int) $data['min_stock'] : 0,
            'barcode'    => $data['barcode'] ?? null,
            'is_active'  => $request->boolean('is_active', true),
            'attributes' => $attributes ?: null,
        ];
    }

    private function normalizeSku(?string $sku): ?string
    {
        $sku = trim((string) $sku);
        return $sku === '' ? null : mb_strtoupper($sku, 'UTF-8');
    }

    private function normalizeIdNumber($value): ?string
    {
        if ($value === null) {
            return null;
        }

        $raw = trim((string) $value);
        if ($raw === '') {
            return null;
        }

        $raw = preg_replace('/[^\d,\.\-]/', '', $raw) ?? '';
        $raw = str_replace('.', '', $raw);
        $raw = str_replace(',', '.', $raw);

        return is_numeric($raw) ? $raw : null;
    }
}
