<?php

namespace App\Http\Controllers;

use App\Models\Item;
use App\Models\Unit;
use App\Models\Brand;
use App\Models\{Size, Color};
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class ItemController extends Controller
{
    public function index(Request $request)
    {
        $viewMode = $this->resolveInventoryViewMode($request);
        $filters  = $this->extractInventoryFilters($request);

        $itemsQuery = Item::query()
            ->with([
                'unit',
                'brand',
                'variants' => fn($q) => $q->orderBy('id'),
            ])
            ->inUnit($filters['unit_id'])
            ->inBrand($filters['brand_id']);

        if ($filters['q'] !== '') {
            $term = $filters['q'];
            $itemsQuery->where(function ($query) use ($term) {
                $like = '%' . $term . '%';
                $query->where('name', 'like', $like)
                    ->orWhere('sku', 'like', $like)
                    ->orWhereHas('brand', fn($b) => $b->where('name', 'like', $like))
                    ->orWhereHas('variants', function ($v) use ($like) {
                        $v->where('sku', 'like', $like)
                          ->orWhereRaw("JSON_UNQUOTE(JSON_EXTRACT(attributes, '$.color')) LIKE ?", [$like])
                          ->orWhereRaw("JSON_UNQUOTE(JSON_EXTRACT(attributes, '$.size')) LIKE ?",  [$like])
                          ->orWhereRaw("JSON_UNQUOTE(JSON_EXTRACT(attributes, '$.length')) LIKE ?",[$like]);
                    });
            });
        }

        if ($filters['stock'] === 'gt0') {
            $itemsQuery->where(function ($query) {
                $query->where('stock', '>', 0)
                      ->orWhereHas('variants', fn($v) => $v->where('stock', '>', 0));
            });
        } elseif ($filters['stock'] === 'eq0') {
            $itemsQuery->where(function ($query) {
                $query->where('stock', '=', 0)
                      ->whereDoesntHave('variants', fn($v) => $v->where('stock', '>', 0));
            });
        }

        $items = $itemsQuery
            ->ordered()
            ->paginate(20)
            ->withQueryString();

        $units      = Unit::active()->orderBy('code')->get(['id','code','name','is_active']);
        $brands     = Brand::orderBy('name')->get(['id','name']);
        $sizesList  = Size::active()->ordered()->pluck('name')->filter()->values();
        $colorsList = Color::active()->ordered()->pluck('name')->filter()->values();

        $items->getCollection()->transform(function ($item) {
            $item->setRelation('variants', $item->variants->map(function ($variant) {
                $variant->computed_attributes = $variant->attributes ?? [];
                return $variant;
            }));
            return $item;
        });

        $flatRows    = $this->buildFlatInventoryRows($items->getCollection(), $filters);
        $groupedRows = $this->buildGroupedInventoryRows($items->getCollection(), $filters);

        return view('items.index', [
            'items'       => $items,
            'units'       => $units,
            'brands'      => $brands,
            'sizesList'   => $sizesList,
            'colorsList'  => $colorsList,
            'filters'     => $filters,
            'viewMode'    => $viewMode,
            'flatRows'    => $flatRows,
            'groupedRows' => $groupedRows,
        ]);
    }

    public function create(Request $request)
    {
        $units  = Unit::active()->orderBy('code')->get(['id','code','name','is_active']);
        $brands = Brand::orderBy('name')->get(['id','name']);
        $defaultUnitId = Unit::whereRaw('LOWER(code) = ?', ['pcs'])->value('id');
        $sizes  = Size::active()->ordered()->get(['id','name']);
        $colors = Color::active()->ordered()->get(['id','name','hex']);

        $parents = Item::orderBy('name')->get(['id','name']);
        $familyCodes = Item::whereNotNull('family_code')
            ->orderBy('family_code')->distinct()->pluck('family_code');

        $item = new Item([
        'sellable' => true,
        'purchasable' => true,
        ]);

        if ($request->ajax() && $request->boolean('modal')) {
            return view('items._modal_create', compact(
                'item','sizes','colors','units','brands','defaultUnitId','parents','familyCodes'
            ));
        }

        return view('items.create', compact(
            'sizes','colors','units','brands','defaultUnitId','parents','familyCodes'
        ));
    }

    public function store(Request $request)
    {
        // Detect modal AJAX submit
        $isModal = $request->ajax() && ($request->boolean('modal') || $request->query('modal'));

        // r bisa dobel (query + hidden + _form). Normalize supaya tidak jadi array.
        $rawR = $request->input('r');
        $r = is_array($rawR) ? ($rawR[0] ?? null) : $rawR;
        $returnUrl = $this->safeReturnUrl(is_string($r) ? $r : null) ?? route('items.index');

        // Helper: render ulang modal (422) dengan dataset yang sama seperti create()
        $renderModal422 = function (array $errors = []) use ($request) {
            $units  = Unit::active()->orderBy('code')->get(['id','code','name','is_active']);
            $brands = Brand::orderBy('name')->get(['id','name']);
            $defaultUnitId = Unit::whereRaw('LOWER(code) = ?', ['pcs'])->value('id');

            $sizes  = Size::active()->ordered()->get(['id','name']);
            $colors = Color::active()->ordered()->get(['id','name','hex']);

            $parents = Item::orderBy('name')->get(['id','name']);
            $familyCodes = Item::whereNotNull('family_code')
                ->orderBy('family_code')
                ->distinct()
                ->pluck('family_code');

            // supaya default checkbox tetap ON saat pertama kali / rerender
            $item = new Item();
            $item->sellable = 1;
            $item->purchasable = 1;

            session()->flashInput($request->input());

            return response()
                ->view('items._modal_create', compact(
                    'item',
                    'sizes',
                    'colors',
                    'units',
                    'brands',
                    'defaultUnitId',
                    'parents',
                    'familyCodes'
                ))
                ->withErrors($errors)
                ->setStatusCode(422);
        };

        // Helper: response sukses (modal => JSON redirect_url, normal => redirect)
        $respondSuccess = function (string $url, string $message) use ($isModal) {
            if ($isModal) {
                session()->flash('success', $message); // <— TAMBAH INI
                return response()->json([
                    'redirect_url' => $url,
                    'message'      => $message,
                ], 201);
            }

            return redirect()->to($url)->with('success', $message);
        };

        // Default unit ke PCS jika kosong
        if (!$request->filled('unit_id')) {
            $pcsId = Unit::whereRaw('LOWER(code) = ?', ['pcs'])->value('id');
            if ($pcsId) $request->merge(['unit_id' => $pcsId]);
        }

        // Normalisasi input
        $request->merge([
            'sku'                 => $this->normalizeSku($request->input('sku')),
            'price'               => $this->normalizeIdNumber($request->input('price')),
            'default_roll_length' => $this->normalizeLengthValue($request->input('default_roll_length')),
            'length_per_piece'    => $this->normalizeLengthValue($request->input('length_per_piece')),
        ]);

        // Validasi (modal harus balik 422 HTML, bukan redirect 302)
        $rules = [
            'name'        => ['required','string','max:255'],
            'sku'         => ['nullable','string','max:255','unique:items,sku'],
            'description' => ['nullable','string'],
            'price'       => ['required','numeric'],
            'stock'       => ['required','integer','min:0'],
            'unit_id'     => ['required', Rule::exists('units','id')->where('is_active', 1)],
            'brand_id'    => ['nullable','exists:brands,id'],

            'item_type'           => ['required','in:standard,kit,cut_raw,cut_piece'],
            'parent_id'           => ['nullable','exists:items,id'],
            'family_code'         => ['nullable','string','max:50'],
            'sellable'            => ['nullable','boolean'],
            'purchasable'         => ['nullable','boolean'],
            'default_roll_length' => ['nullable','numeric','min:0','required_if:item_type,cut_raw'],
            'length_per_piece'    => ['nullable','numeric','min:0','required_if:item_type,cut_piece'],

            'attr_size'  => ['nullable','string','max:50'],
            'attr_color' => ['nullable','string','max:50'],
            'size_id'    => ['nullable','exists:sizes,id'],
            'color_id'   => ['nullable','exists:colors,id'],
        ];

        $validator = \Illuminate\Support\Facades\Validator::make($request->all(), $rules);

        if ($validator->fails()) {
            if ($isModal) {
                return $renderModal422($validator->errors()->toArray());
            }
            throw new \Illuminate\Validation\ValidationException($validator);
        }

        $data = $validator->validated();

        // Attributes JSON
        $attrs = [];
        if ($request->filled('attr_size'))  $attrs['size']  = $request->string('attr_size')->toString();
        if ($request->filled('attr_color')) $attrs['color'] = $request->string('attr_color')->toString();

        // Default ON kalau field tidak ikut terkirim (modal/partial mismatch)
        $data['sellable']    = $request->has('sellable') ? $request->boolean('sellable') : true;
        $data['purchasable'] = $request->has('purchasable') ? $request->boolean('purchasable') : true;

        $data['attributes'] = count($attrs) ? $attrs : null;

        $skuProvided = isset($data['sku']) && $data['sku'] !== null && $data['sku'] !== '';

        try {
            $item = $this->runWithAutoGeneratedSkuRetry($skuProvided, function () use ($data) {
                return Item::create($data);
            });
        } catch (QueryException $e) {
            if ($this->isDuplicateSkuException($e)) {
                if ($isModal) {
                    return $renderModal422(['sku' => ['SKU sudah digunakan, silakan gunakan kode lain.']]);
                }
                return back()->withInput()->withErrors(['sku' => 'SKU sudah digunakan, silakan gunakan kode lain.']);
            }
            throw $e;
        }

        // Flow setelah item dibuat (tetap support save_add & save_variants)
        $action = $request->input('action');

        if ($action === 'save_add') {
            return $respondSuccess(
                route('items.create', ['r' => $returnUrl]),
                'Item created! Silakan tambah item baru.'
            );
        }

        if ($action === 'save_variants') {
            $url = route('items.variants.index', $item) . '?r=' . urlencode($returnUrl);
            return $respondSuccess($url, 'Item created! Silakan kelola varian.');
        }

        return $respondSuccess($returnUrl, 'Item created!');
    }



    public function show(Item $item)
    {
        $item->load(['unit','brand','size','color','parent']);
        return view('items.show', compact('item'));
    }

    public function edit(Item $item)
    {
        $item->load(['variants' => fn($q) => $q->orderBy('id')]);

        $unitsActive = Unit::active()->orderBy('code')->get(['id','code','name','is_active']);
        $currentUnit = $item->unit;
        $units = $unitsActive;
        if ($currentUnit && !$currentUnit->is_active) {
            $units = $units->prepend($currentUnit)->unique('id')->values();
        }

        $brands = Brand::orderBy('name')->get(['id','name']);
        $sizes  = Size::active()->ordered()->get(['id','name']);
        $colors = Color::active()->ordered()->get(['id','name','hex']);

        $parents = Item::orderBy('name')
            ->when(isset($item), fn($q) => $q->whereKeyNot($item->id))
            ->get(['id','name']);
        $familyCodes = Item::whereNotNull('family_code')
            ->orderBy('family_code')->distinct()->pluck('family_code');

        return view('items.edit', compact('item','sizes','colors','units','brands','parents','familyCodes'));
    }

    public function update(Request $request, Item $item)
    {
        $returnUrl = $this->safeReturnUrl($request->input('r')) ?? route('items.index');

        // Normalisasi
        $request->merge([
            'sku'                 => $this->normalizeSku($request->input('sku')),
            'price'               => $this->normalizeIdNumber($request->input('price')),
            'default_roll_length' => $this->normalizeLengthValue($request->input('default_roll_length')),
            'length_per_piece'    => $this->normalizeLengthValue($request->input('length_per_piece')),
        ]);

        // Rule unit: izinkan unit lama walau non-aktif
        $unitRule = Rule::exists('units','id')->where('is_active', 1);
        if ((int)$request->input('unit_id') === (int)$item->unit_id) {
            $unitRule = Rule::exists('units','id');
        }

        $data = $request->validate([
            'name'        => ['required','string','max:255'],
            'sku'         => ['nullable','string','max:255','unique:items,sku,'.$item->id],
            'description' => ['nullable','string'],
            'price'       => ['required','numeric'],
            'stock'       => ['required','integer','min:0'],
            'unit_id'     => ['required', $unitRule],
            'brand_id'    => ['nullable','exists:brands,id'],

            'item_type'           => ['required','in:standard,kit,cut_raw,cut_piece'],
            'parent_id'           => ['nullable','exists:items,id'],
            'family_code'         => ['nullable','string','max:50'],
            'sellable'            => ['nullable','boolean'],
            'purchasable'         => ['nullable','boolean'],
            'default_roll_length' => ['nullable','numeric','min:0','required_if:item_type,cut_raw'],
            'length_per_piece'    => ['nullable','numeric','min:0','required_if:item_type,cut_piece'],

            'attr_size'  => ['nullable','string','max:50'],
            'attr_color' => ['nullable','string','max:50'],
        ]);

        // Cegah parent ke diri sendiri
        if ($request->filled('parent_id') && (int)$request->input('parent_id') === (int)$item->id) {
            return back()->withInput()->withErrors(['parent_id' => 'Parent tidak boleh item yang sama.']);
        }

        // Attributes
        $attrs = [];
        if ($request->filled('attr_size'))  $attrs['size']  = $request->string('attr_size')->toString();
        if ($request->filled('attr_color')) $attrs['color'] = $request->string('attr_color')->toString();

        $data['sellable']    = $request->boolean('sellable');
        $data['purchasable'] = $request->boolean('purchasable');
        $data['attributes']  = count($attrs) ? $attrs : null;

        $skuProvided = isset($data['sku']) && $data['sku'] !== null && $data['sku'] !== '';

        try {
            $this->runWithAutoGeneratedSkuRetry($skuProvided, function () use ($item, $data) {
                $item->update($data);
                return $item;
            });
        } catch (QueryException $e) {
            if ($this->isDuplicateSkuException($e)) {
                return back()->withInput()->withErrors(['sku' => 'SKU sudah digunakan, silakan gunakan kode lain.']);
            }
            throw $e;
        }

        $action = $request->input('action');

        if ($action === 'save_add') {
            return redirect()
                ->route('items.create', ['r' => $returnUrl])
                ->with('success', 'Perubahan disimpan. Silakan tambah item baru.');
        }

        if ($action === 'save_variants') {
            return redirect()
                ->to(route('items.variants.index', $item) . '?r=' . urlencode($returnUrl))
                ->with('success', 'Perubahan disimpan. Silakan kelola varian.');
        }

        return redirect()->to($returnUrl)->with('success', 'Item updated!');

    }

    public function destroy(Request $request, Item $item)
    {
        $returnUrl = $this->safeReturnUrl($request->input('r')) ?? route('items.index');

        $item->delete();

        return redirect()->to($returnUrl)->with('success','Item deleted!');
    }

    /**
     * Endpoint untuk item quick-search (TomSelect).
     * - q kosong: kirim Top-N item saja (tanpa varian) agar ringan.
     * - q terisi: boleh kirim varian yang aktif & relevan.
     * - total hasil dipotong max 30 agar dropdown responsif.
     */
    public function quickSearch(Request $req)
    {
        $q = trim($req->get('q', ''));

        $items = Item::query()
            ->with([
                'unit:id,code',
                'variants:id,item_id,sku,price,attributes,is_active',
            ])
            ->when($q !== '', function ($qq) use ($q) {
                $qq->where(function ($w) use ($q) {
                    $w->where('name', 'like', "%{$q}%")
                      ->orWhere('sku',  'like', "%{$q}%")
                      ->orWhereHas('variants', function ($v) use ($q) {
                          $v->where('sku', 'like', "%{$q}%");
                      });
                });
            })
            ->orderBy('name')
            ->limit(40) // ambil agak longgar, nanti dipotong lagi di output
            ->get([
                'id','name','sku','price','unit_id','brand_id','description',
                'variant_type','name_template'
            ]);

        $fmt = fn($n) => number_format((float)$n, 2, ',', '.');

        $out = [];
        $qIsEmpty = ($q === '');

        foreach ($items as $it) {
            $unitCode = optional($it->unit)->code ?? 'PCS';
            $variants = $it->variants ?? collect();
            $activeVariants = $variants->filter(fn($v) => (bool) $v->is_active);

            // RULE:
            // - q kosong: tetap ringan, jangan tampilkan varian
            // - q terisi: jika ada varian aktif (meski 1) => tampilkan varian saja (parent disembunyikan)
            $displayVariants = (!$qIsEmpty) && $activeVariants->isNotEmpty();

            // Tidak pakai varian → kirim 1 baris item
            if (!$displayVariants) {
                $label = $it->name;
                $price = (float)$it->price;

                $out[] = [
                    'uid'        => 'item-'.$it->id,
                    'type'       => 'item',
                    'item_id'    => $it->id,
                    'variant_id' => null,
                    'name'       => $label,
                    'label'      => '(' . $fmt($price) . ') ' . $label,
                    'sku'        => $it->sku,
                    'price'      => $price,
                    'unit_code'  => $unitCode,
                    'description'=> (string) $it->description,
                    'attributes' => null,
                ];
                continue;
            }

            // Pakai varian → petakan varian aktif
            foreach ($activeVariants as $v) {

                $attrs = is_array($v->attributes) ? $v->attributes : [];
                $label = $it->renderVariantLabel($attrs);
                $attrsText = collect($attrs)->map(fn($val, $key) => ucfirst($key).': '.$val)->implode(' · ');

                $displayLabel = $label;
                $price = (float) (($v->price ?? null) !== null ? $v->price : $it->price);
                $sku   = $v->sku ?: $it->sku;

                $out[] = [
                    'uid'        => 'variant-'.$v->id,
                    'type'       => 'variant',
                    'item_id'    => $it->id,
                    'variant_id' => $v->id,
                    'name'       => $displayLabel,
                    'label'      => '(' . $fmt($price) . ') ' . $displayLabel,
                    'sku'        => $sku,
                    'price'      => $price,
                    'unit_code'  => $unitCode,
                    'description'=> (string) $it->description,
                    'attributes' => $attrs,
                ];
            }
        }

        // Potong total output biar enteng
        if (count($out) > 30) {
            $out = array_slice($out, 0, 30);
        }

        return response()->json($out);
    }

    // ======================
    // Helpers
    // ======================

    private function runWithAutoGeneratedSkuRetry(bool $userProvidedSku, callable $callback)
    {
        $attempts = $userProvidedSku ? 1 : 2;

        for ($attempt = 0; $attempt < $attempts; $attempt++) {
            try {
                return DB::transaction($callback);
            } catch (QueryException $e) {
                $isLastAttempt = $attempt === $attempts - 1;

                if (!$this->isDuplicateSkuException($e) || $isLastAttempt) {
                    throw $e;
                }
            }
        }

        return null;
    }

    private function isDuplicateSkuException(QueryException $e): bool
    {
        $info = $e->errorInfo;
        $sqlState = $info[0] ?? null;
        $errorCode = (int) ($info[1] ?? 0);

        if ($sqlState === '23000' && $errorCode === 1062) {
            return true;
        }

        if ($sqlState !== '23000') {
            return false;
        }

        $message = $e->getMessage();
        return str_contains($message, 'items') && str_contains($message, 'sku');
    }

    /** "abc-123" => "ABC-123"; kosongkan jika null/whitespace */
    private function normalizeSku(?string $sku): ?string
    {
        $sku = trim((string)$sku);
        return $sku === '' ? null : mb_strtoupper($sku, 'UTF-8');
    }

    /**
     * Terima angka format Indonesia (1.234,56) → "1234.56"
     * Kembalikan null jika kosong.
     */
    private function normalizeIdNumber($value): ?string
    {
        if ($value === null) return null;
        $raw = trim((string)$value);
        if ($raw === '') return null;

        // buang non-digit kecuali koma, titik, minus
        $raw = preg_replace('/[^\d,\.\-]/', '', $raw);
        // hapus pemisah ribuan (titik), ganti koma jadi titik
        $raw = str_replace('.', '', $raw);
        $raw = str_replace(',', '.', $raw);

        if (!is_numeric($raw)) return null;
        return $raw;
    }

    private function resolveInventoryViewMode(Request $request): string
    {
        $mode = $request->input('view');
        $allowed = ['flat', 'grouped'];
        if ($mode && in_array($mode, $allowed, true)) {
            session(['inventory_view_mode' => $mode]);
        } else {
            $mode = session('inventory_view_mode', 'flat');
            if (!in_array($mode, $allowed, true)) {
                $mode = 'flat';
                session(['inventory_view_mode' => $mode]);
            }
        }
        return $mode;
    }

    private function extractInventoryFilters(Request $request): array
    {
        $term = trim((string) $request->input('q', ''));

        $type  = $request->input('type', 'all');
        $stock = $request->input('stock', 'all');
        $sort  = $request->input('sort', 'name_asc');

        $allowedType  = ['all','item','variant'];
        $allowedStock = ['all','gt0','eq0'];
        $allowedSort  = ['name_asc','price_lowest','price_highest','stock_highest','newest'];

        $type  = in_array($type, $allowedType, true)   ? $type  : 'all';
        $stock = in_array($stock, $allowedStock, true) ? $stock : 'all';
        $sort  = in_array($sort, $allowedSort, true)   ? $sort  : 'name_asc';

        $sizes  = array_values(array_filter((array) $request->input('sizes', []), fn($v) => $v !== null && $v !== ''));
        $colors = array_values(array_filter((array) $request->input('colors', []), fn($v) => $v !== null && $v !== ''));
        $lengthMin = $request->input('length_min');
        $lengthMax = $request->input('length_max');

        $showVariantParent = filter_var($request->input('show_variant_parent', '0'), FILTER_VALIDATE_BOOLEAN);

        return [
            'q'                  => $term,
            'unit_id'            => $request->input('unit_id'),
            'brand_id'           => $request->input('brand_id'),
            'type'               => $type,
            'stock'              => $stock,
            'sizes'              => $sizes,
            'colors'             => $colors,
            'length_min'         => $lengthMin !== null && $lengthMin !== '' ? (float) $lengthMin : null,
            'length_max'         => $lengthMax !== null && $lengthMax !== '' ? (float) $lengthMax : null,
            'sort'               => $sort,
            'show_variant_parent'=> $showVariantParent,
        ];
    }

    private function buildFlatInventoryRows(Collection $items, array $filters): Collection
    {
        $rows = collect();
        $term = Str::lower($filters['q']);
        $sizeFilters  = array_map(fn($v) => Str::lower($v), $filters['sizes']);
        $colorFilters = array_map(fn($v) => Str::lower($v), $filters['colors']);
        $lengthMin = $filters['length_min'];
        $lengthMax = $filters['length_max'];

        $showVariantParent = $filters['show_variant_parent'];

        $variantMaxRelevance = [];
        $itemRelevance       = [];

        foreach ($items as $item) {
            $variants = $item->variants ?? collect();
            $displayVariants = $this->shouldDisplayVariants($item, $variants);
            $activeVariants = $displayVariants ? $variants->where('is_active', true) : collect();
            $totalVariantStock = (int) $activeVariants->sum('stock');
            $minVariantPrice   = $displayVariants ? $activeVariants->min('price') : null;
            $maxVariantPrice   = $displayVariants ? $activeVariants->max('price') : null;

            $itemPriceValue = $minVariantPrice ?? $item->price;
            $itemStockValue = $displayVariants ? $totalVariantStock : (int) $item->stock;

            $itemRelevanceScore = $this->computeInventoryRelevance($item, null, $term);
            $itemRelevance[$item->id] = $itemRelevanceScore;

            $itemMatchesAttributes = $this->itemMatchesAttributeFilters($item, $filters, $activeVariants);

            $shouldHideParent = !$showVariantParent && $displayVariants && $filters['type'] !== 'item';

            if ($filters['type'] !== 'variant' && $itemMatchesAttributes && !$shouldHideParent) {
                $rows->push([
                    'entity'        => 'item',
                    'item_id'       => $item->id,
                    'variant_id'    => null,
                    'display_name'  => $item->name,
                    'brand'         => optional($item->brand)->name,
                    'unit'          => optional($item->unit)->code ?: optional($item->unit)->name,
                    'sku'           => $item->sku,
                    'price'         => (float) ($itemPriceValue ?? 0),
                    'price_label'   => $this->formatPriceRange($minVariantPrice, $maxVariantPrice, $item->price),
                    'stock'         => $itemStockValue,
                    'stock_label'   => number_format($itemStockValue, 0, ',', '.'),
                    'low_stock'     => ($item->min_stock ?? 0) > 0 && $itemStockValue < $item->min_stock,
                    'inactive'      => false,
                    'attributes'    => [
                        'size'  => optional($item->size)->name,
                        'color' => optional($item->color)->name,
                    ],
                    'parent_name'   => null,
                    'relevance'     => $itemRelevanceScore,
                    'created_at'    => $item->created_at,
                    'variant_count' => $variants->count(),
                    'variants'      => $variants,
                ]);
            }

            if ($filters['type'] === 'item') {
                continue;
            }
            if (!$displayVariants) {
                continue;
            }

            foreach ($variants as $variant) {
                if (!$this->variantMatchesFilters($variant, $item, $filters, $sizeFilters, $colorFilters, $lengthMin, $lengthMax)) {
                    continue;
                }
                if ($filters['stock'] === 'gt0' && (int) $variant->stock <= 0) {
                    continue;
                }
                if ($filters['stock'] === 'eq0' && (int) $variant->stock > 0) {
                    continue;
                }

                $attrs = $variant->computed_attributes ?? ($variant->attributes ?? []);
                $label = $item->renderVariantLabel(is_array($attrs) ? $attrs : []);
                $relevance = $this->computeInventoryRelevance($item, $variant, $term);
                $variantMaxRelevance[$item->id] = max($variantMaxRelevance[$item->id] ?? 0, $relevance);

                $rows->push([
                    'entity'        => 'variant',
                    'item_id'       => $item->id,
                    'variant_id'    => $variant->id,
                    'display_name'  => $label,
                    'brand'         => optional($item->brand)->name,
                    'unit'          => optional($item->unit)->code ?: optional($item->unit)->name,
                    'sku'           => $variant->sku ?: $item->sku,
                    'price'         => (float) ($variant->price ?? $item->price ?? 0),
                    'price_label'   => 'Rp ' . number_format((float) ($variant->price ?? 0), 2, ',', '.'),
                    'stock'         => (int) $variant->stock,
                    'stock_label'   => number_format((int) $variant->stock, 0, ',', '.'),
                    'low_stock'     => ($variant->min_stock ?? 0) > 0 && $variant->stock < $variant->min_stock,
                    'inactive'      => !$variant->is_active,
                    'attributes'    => [
                        'size'  => $attrs['size'] ?? null,
                        'color' => $attrs['color'] ?? null,
                    ],
                    'parent_name'   => $item->name,
                    'relevance'     => $relevance,
                    'created_at'    => $variant->created_at,
                    'variant_count' => $variants->count(),
                    'variants'      => $variants,
                ]);
            }
        }

        if ($term !== '') {
            $rows = $rows->filter(function ($row) use ($term) {
                if ($row['relevance'] > 0) return true;
                if ($row['entity'] === 'item') {
                    return Str::contains(Str::lower($row['display_name']), $term);
                }
                return false;
            });

            $rows = $rows->filter(function ($row) use ($variantMaxRelevance, $itemRelevance) {
                if ($row['entity'] !== 'item') return true;
                $maxVariant = $variantMaxRelevance[$row['item_id']] ?? null;
                if ($maxVariant === null) return true;
                $itemScore = $itemRelevance[$row['item_id']] ?? 0;
                return $itemScore >= $maxVariant;
            });
        }

        return $this->sortInventoryRows($rows, $filters)->values();
    }

    private function buildGroupedInventoryRows(Collection $items, array $filters): Collection
    {
        $term = Str::lower($filters['q']);
        $sizeFilters  = array_map(fn($v) => Str::lower($v), $filters['sizes']);
        $colorFilters = array_map(fn($v) => Str::lower($v), $filters['colors']);
        $lengthMin = $filters['length_min'];
        $lengthMax = $filters['length_max'];
        $showVariantParent = $filters['show_variant_parent'];

        $rows = collect();

        foreach ($items as $item) {
            $variants = $item->variants ?? collect();
            $displayVariants = $this->shouldDisplayVariants($item, $variants);
            $activeVariants  = $displayVariants ? $variants->where('is_active', true) : collect();

            if ($filters['type'] === 'variant') {
                if (!$displayVariants) continue;
                $matchingVariants = $variants->filter(fn($variant) => $this->variantMatchesFilters($variant, $item, $filters, $sizeFilters, $colorFilters, $lengthMin, $lengthMax));
                if ($matchingVariants->isEmpty()) continue;
            } elseif (!$this->itemMatchesAttributeFilters($item, $filters, $activeVariants)) {
                continue;
            }

            $totalVariantStock = (int) $activeVariants->sum('stock');
            $minVariantPrice = $displayVariants ? $activeVariants->min('price') : null;
            $maxVariantPrice = $displayVariants ? $activeVariants->max('price') : null;
            $priceLabel = $this->formatPriceRange($minVariantPrice, $maxVariantPrice, $item->price);
            $stockLabel = $activeVariants->isNotEmpty()
                ? number_format($totalVariantStock, 0, ',', '.')
                : number_format((int) $item->stock, 0, ',', '.');

            $chipData = $this->buildAttributeChipData($activeVariants);

            $preview = $activeVariants->sortBy('id')->take(5)->map(function ($variant) use ($item) {
                $attrs = $variant->attributes ?? [];
                return [
                    'label'  => $item->renderVariantLabel(is_array($attrs) ? $attrs : []),
                    'sku'    => $variant->sku ?: $item->sku,
                    'price'  => number_format((float) ($variant->price ?? 0), 2, ',', '.'),
                    'stock'  => (int) $variant->stock,
                    'active' => (bool) $variant->is_active,
                ];
            });

            if (!$showVariantParent && $displayVariants && $filters['type'] !== 'item') {
                continue;
            }

            $rows->push([
                'item'          => $item,
                'variant_count' => $variants->count(),
                'price_label'   => $priceLabel,
                'stock_label'   => $stockLabel,
                'chips'         => $chipData,
                'preview'       => $preview,
                'has_variants'  => $displayVariants,
                'relevance'     => $this->computeInventoryRelevance($item, null, $term),
            ]);
        }

        return $rows->sortBy('item.name')->values();
    }

    private function sortInventoryRows(Collection $rows, array $filters): Collection
    {
        return $rows->sort(function ($a, $b) use ($filters) {
            if ($filters['q'] !== '') {
                if (($b['relevance'] ?? 0) !== ($a['relevance'] ?? 0)) {
                    return ($b['relevance'] ?? 0) <=> ($a['relevance'] ?? 0);
                }
            }

            switch ($filters['sort']) {
                case 'price_lowest':
                    return ($a['price'] ?? 0) <=> ($b['price'] ?? 0);
                case 'price_highest':
                    return ($b['price'] ?? 0) <=> ($a['price'] ?? 0);
                case 'stock_highest':
                    return ($b['stock'] ?? 0) <=> ($a['stock'] ?? 0);
                case 'newest':
                    return ($b['created_at'] ?? now()) <=> ($a['created_at'] ?? now());
                case 'name_asc':
                default:
                    return Str::lower($a['display_name'] ?? '') <=> Str::lower($b['display_name'] ?? '');
            }
        });
    }

    private function computeInventoryRelevance(Item $item, $variant, string $term): int
    {
        if ($term === '') return 0;

        $score = 0;
        $term = Str::lower($term);

        if (Str::contains(Str::lower($item->name), $term)) {
            $score += 200;
        }
        if ($item->sku && Str::contains(Str::lower($item->sku), $term)) {
            $score += 150;
        }
        if ($item->brand && Str::contains(Str::lower($item->brand->name), $term)) {
            $score += 80;
        }

        if ($variant) {
            if ($variant->sku && Str::contains(Str::lower($variant->sku), $term)) {
                $score += 1000;
            }
            $attrs = $variant->attributes ?? [];
            foreach ($attrs as $value) {
                if (is_string($value) && Str::contains(Str::lower($value), $term)) {
                    $score += 500;
                }
            }
        }

        return $score;
    }

    private function variantMatchesFilters($variant, Item $item, array $filters, array $sizeFilters, array $colorFilters, ?float $lengthMin, ?float $lengthMax): bool
    {
        $attrs = $variant->attributes ?? [];
        $size  = Str::lower($attrs['size'] ?? '');
        $color = Str::lower($attrs['color'] ?? '');
        $lengthValue = $this->normalizeLengthValue($attrs['length'] ?? null);

        if ($sizeFilters && !in_array($size, $sizeFilters, true))  return false;
        if ($colorFilters && !in_array($color, $colorFilters, true)) return false;
        if ($lengthMin !== null && ($lengthValue === null || $lengthValue < $lengthMin)) return false;
        if ($lengthMax !== null && ($lengthValue === null || $lengthValue > $lengthMax)) return false;

        return true;
    }

    private function itemMatchesAttributeFilters(Item $item, array $filters, Collection $activeVariants): bool
    {
        if (empty($filters['sizes']) && empty($filters['colors']) && $filters['length_min'] === null && $filters['length_max'] === null) {
            return true;
        }

        $sizeFilters  = array_map(fn($v) => Str::lower($v), $filters['sizes']);
        $colorFilters = array_map(fn($v) => Str::lower($v), $filters['colors']);

        foreach ($activeVariants as $variant) {
            if ($this->variantMatchesFilters($variant, $item, $filters, $sizeFilters, $colorFilters, $filters['length_min'], $filters['length_max'])) {
                return true;
            }
        }

        return false;
    }

    private function shouldDisplayVariants(Item $item, Collection $variants): bool
    {
        if ($variants->isEmpty()) return false;

        if (($item->variant_type ?? 'none') !== 'none') return true;
        if ($variants->count() > 1) return true;

        $variant = $variants->first();
        if (!$variant) return false;

        $attrs = $variant->attributes ?? [];
        $hasAttributes = collect($attrs)
            ->filter(fn($v) => $v !== null && $v !== '')
            ->isNotEmpty();

        if ($hasAttributes) return true;

        $hasDifferentSku   = $variant->sku && $variant->sku !== $item->sku;
        $hasDifferentPrice = $variant->price !== null && (float) $variant->price !== (float) $item->price;

        return $hasDifferentSku || $hasDifferentPrice;
    }

    private function normalizeLengthValue($value): ?float
    {
        if ($value === null || $value === '') return null;
        if (is_numeric($value)) return (float) $value;

        $clean = preg_replace('/[^0-9.,-]/', '', (string) $value);
        $clean = str_replace(',', '.', $clean);
        return is_numeric($clean) ? (float) $clean : null;
    }

    private function formatPriceRange($minVariantPrice, $maxVariantPrice, $itemPrice): string
    {
        if ($minVariantPrice !== null && $maxVariantPrice !== null) {
            if ($minVariantPrice == $maxVariantPrice) {
                return 'Rp ' . number_format((float) $minVariantPrice, 2, ',', '.');
            }
            return 'Rp ' . number_format((float) $minVariantPrice, 2, ',', '.')
                 . ' — ' . number_format((float) $maxVariantPrice, 2, ',', '.');
        }
        return 'Rp ' . number_format((float) ($itemPrice ?? 0), 2, ',', '.');
    }

    private function buildAttributeChipData(Collection $variants): array
    {
        $sizes  = [];
        $colors = [];

        foreach ($variants as $variant) {
            $attrs = $variant->attributes ?? [];
            if (!empty($attrs['size']))  $sizes[]  = (string) $attrs['size'];
            if (!empty($attrs['color'])) $colors[] = (string) $attrs['color'];
        }

        $sizes  = collect($sizes)->unique()->values();
        $colors = collect($colors)->unique()->values();

        return [
            'size'  => $sizes,
            'color' => $colors,
        ];
    }

    private function safeReturnUrl(?string $r): ?string
    {
        if (!$r) return null;

        // allow relative path "/items?..."; block "//evil.com"
        if (str_starts_with($r, '/') && !str_starts_with($r, '//')) return $r;

        // allow same-host absolute URL
        $appHost = parse_url(config('app.url'), PHP_URL_HOST);
        $host    = parse_url($r, PHP_URL_HOST);

        return ($host && $appHost && $host === $appHost) ? $r : null;
    }

}
