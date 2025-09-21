<?php

namespace App\Http\Controllers;

use App\Models\Item;
use App\Models\Unit;
use App\Models\Brand;
use App\Models\{Size, Color};
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ItemController extends Controller
{
    public function index(Request $request)
    {
        $q       = $request->string('q')->toString();
        $unitId  = $request->input('unit_id');
        $brandId = $request->input('brand_id');

        $items = Item::with(['unit','brand','size','color'])
            // NOTE: Jika Items adalah GLOBAL (sesuai keputusan proyek), hapus scope forCompany() bila ada.
            // ->forCompany()
            ->keyword($q)
            ->inUnit($unitId)
            ->inBrand($brandId)
            ->ordered()
            ->paginate(20)
            ->withQueryString();

        $units  = Unit::active()->orderBy('code')->get(['id','code','name','is_active']);
        $brands = Brand::orderBy('name')->get(['id','name']);

        return view('items.index', compact('items','units','brands','q','unitId','brandId'));
    }

    public function create()
    {
        $units  = Unit::active()->orderBy('code')->get(['id','code','name','is_active']);
        $brands = Brand::orderBy('name')->get(['id','name']);
        $defaultUnitId = Unit::whereRaw('LOWER(code) = ?', ['pcs'])->value('id');
        $sizes  = Size::active()->ordered()->get(['id','name']);
        $colors = Color::active()->ordered()->get(['id','name','hex']);

        // NEW
        $parents = Item::orderBy('name')
            ->when(isset($item), fn($q) => $q->whereKeyNot($item->id))
            ->get(['id','name']);
        $familyCodes = Item::whereNotNull('family_code')
            ->orderBy('family_code')->distinct()->pluck('family_code');

        return view('items.create', compact('sizes','colors','units','brands','defaultUnitId','parents','familyCodes'));
    }

    public function store(Request $request)
    {
        // Default unit ke PCS jika kosong
        if (!$request->filled('unit_id')) {
            $pcsId = Unit::whereRaw('LOWER(code) = ?', ['pcs'])->value('id');
            if ($pcsId) $request->merge(['unit_id' => $pcsId]);
        }

        // Normalisasi input (SKU uppercase; angka ID ke decimal)
        $request->merge([
            'sku'                  => $this->normalizeSku($request->input('sku')),
            'price'                => $this->normalizeIdNumber($request->input('price')),
            // NEW numeric fields
            'default_roll_length'  => $this->normalizeIdNumber($request->input('default_roll_length')),
            'length_per_piece'     => $this->normalizeIdNumber($request->input('length_per_piece')),
        ]);

        $data = $request->validate([
            'name'        => ['required','string','max:255'],
            'sku'         => ['nullable','string','max:255','unique:items,sku'],
            'description' => ['nullable','string'],
            'price'       => ['required','numeric'],
            'stock'       => ['required','integer','min:0'],
            'unit_id'     => ['required', Rule::exists('units','id')->where('is_active', 1)],
            'brand_id'    => ['nullable','exists:brands,id'],

            // NEW
            'item_type'           => ['required','in:standard,kit,cut_raw,cut_piece'],
            'parent_id'           => ['nullable','exists:items,id'],
            'family_code'         => ['nullable','string','max:50'],
            'sellable'            => ['nullable','boolean'],
            'purchasable'         => ['nullable','boolean'],
            'default_roll_length' => ['nullable','numeric','min:0','required_if:item_type,cut_raw'],
            'length_per_piece'    => ['nullable','numeric','min:0','required_if:item_type,cut_piece'],

            // Attributes (akan digabung ke JSON)
            'attr_size'  => ['nullable','string','max:50'],
            'attr_color' => ['nullable','string','max:50'],
            'size_id'  => ['nullable','exists:sizes,id'],
            'color_id' => ['nullable','exists:colors,id'],
        ]);

        // Build attributes JSON
        $attrs = [];
        if ($request->filled('attr_size'))  $attrs['size']  = $request->string('attr_size')->toString();
        if ($request->filled('attr_color')) $attrs['color'] = $request->string('attr_color')->toString();

        // Flag checkbox → boolean
        $data['sellable']    = $request->boolean('sellable', true);
        $data['purchasable'] = $request->boolean('purchasable', true);
        $data['attributes']  = count($attrs) ? $attrs : null;

        $item = Item::create($data);

        if ($request->input('action') === 'save_add') {
            return redirect()->route('items.create')
                ->with('success', 'Item created! Silakan tambah item baru.');
        }
        return redirect()->route('items.index')->with('success','Item created!');
    }


    public function show(Item $item)
    {
        $item->load(['unit','brand','size','color','parent']);
        return view('items.show', compact('item'));
    }

    public function edit(Item $item)
    {
        $unitsActive = Unit::active()->orderBy('code')->get(['id','code','name','is_active']);
        $currentUnit = $item->unit;
        $units = $unitsActive;
        if ($currentUnit && !$currentUnit->is_active) {
            $units = $units->prepend($currentUnit)->unique('id')->values();
        }
        $brands = Brand::orderBy('name')->get(['id','name']);
        $sizes  = Size::active()->ordered()->get(['id','name']);
        $colors = Color::active()->ordered()->get(['id','name','hex']);
        // NEW
        $parents = Item::orderBy('name')
            ->when(isset($item), fn($q) => $q->whereKeyNot($item->id))
            ->get(['id','name']);
        $familyCodes = Item::whereNotNull('family_code')
            ->orderBy('family_code')->distinct()->pluck('family_code');

        return view('items.edit', compact('item','sizes','colors','units','brands','parents','familyCodes'));
    }



    public function update(Request $request, Item $item)
    {
        // Normalisasi dulu agar validasi konsisten
        $request->merge([
            'sku'                  => $this->normalizeSku($request->input('sku')),
            'price'                => $this->normalizeIdNumber($request->input('price')),
            'default_roll_length'  => $this->normalizeIdNumber($request->input('default_roll_length')),
            'length_per_piece'     => $this->normalizeIdNumber($request->input('length_per_piece')),
        ]);

        // Rule unit: tetap izinkan unit lama walau sudah nonaktif
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

            // NEW
            'item_type'           => ['required','in:standard,kit,cut_raw,cut_piece'],
            'parent_id'           => ['nullable','exists:items,id'],
            'family_code'         => ['nullable','string','max:50'],
            'sellable'            => ['nullable','boolean'],
            'purchasable'         => ['nullable','boolean'],
            'default_roll_length' => ['nullable','numeric','min:0','required_if:item_type,cut_raw'],
            'length_per_piece'    => ['nullable','numeric','min:0','required_if:item_type,cut_piece'],

            // Attributes
            'attr_size'  => ['nullable','string','max:50'],
            'attr_color' => ['nullable','string','max:50'],
        ]);

        // Cegah parent set ke diri sendiri
        if ($request->filled('parent_id') && (int)$request->input('parent_id') === (int)$item->id) {
            return back()->withInput()->withErrors(['parent_id' => 'Parent tidak boleh item yang sama.']);
        }

        $attrs = [];
        if ($request->filled('attr_size'))  $attrs['size']  = $request->string('attr_size')->toString();
        if ($request->filled('attr_color')) $attrs['color'] = $request->string('attr_color')->toString();

        $data['sellable']    = $request->boolean('sellable', true);
        $data['purchasable'] = $request->boolean('purchasable', true);
        $data['attributes']  = count($attrs) ? $attrs : null;

        $item->update($data);

        if ($request->input('action') === 'save_add') {
            return redirect()->route('items.create')
                ->with('success', 'Perubahan disimpan. Silakan tambah item baru.');
        }
        return redirect()->route('items.index')->with('success','Item updated!');
    }


    public function destroy(Item $item)
    {
        $item->delete();
        return redirect()->route('items.index')->with('success','Item deleted!');
    }

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
            ->limit(30)
            ->get([
                'id','name','sku','price','unit_id','brand_id','description',
                'variant_type','name_template'
            ]);

        $fmt = fn($n) => number_format((float)$n, 2, ',', '.');

        $out = [];

        foreach ($items as $it) {
            $unitCode = optional($it->unit)->code ?? 'PCS';

            // Jika tidak pakai varian → kirim 1 baris "item"
            if ($it->variant_type === 'none' || $it->variants->isEmpty()) {
                $label = $it->name;
                $price = (float)$it->price;

                $out[] = [
                    'type'        => 'item',
                    'item_id'     => $it->id,
                    'variant_id'  => null,
                    'name'        => $label,                                // value untuk TomSelect
                    'label'       => '(' . $fmt($price) . ') ' . $label,    // tampilan dropdown
                    'sku'         => $it->sku,
                    'price'       => $price,
                    'unit_code'   => $unitCode,
                    'description' => (string) $it->description,
                    'attributes'  => null,
                ];
                continue;
            }

            // Pakai varian → kirim per varian
            foreach ($it->variants as $v) {
                if (!$v->is_active) continue;

                $attrs = is_array($v->attributes) ? $v->attributes : [];
                $label = $it->renderVariantLabel($attrs);
                $price = (float) (($v->price ?? null) !== null ? $v->price : $it->price);
                $sku   = $v->sku ?: $it->sku;

                $out[] = [
                    'type'        => 'variant',
                    'item_id'     => $it->id,
                    'variant_id'  => $v->id,
                    'name'        => $label,
                    'label'       => '(' . $fmt($price) . ') ' . $label,
                    'sku'         => $sku,
                    'price'       => $price,
                    'unit_code'   => $unitCode,
                    'description' => (string) $it->description,
                    'attributes'  => $attrs,
                ];
            }
        }

        return response()->json($out);
    }



    // ======================
    // Helpers
    // ======================

    /** Ubah "abc-123" => "ABC-123"; kosongkan jika null/whitespace */
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

        // buang karakter kecuali digit, koma, titik, minus
        $raw = preg_replace('/[^\d,\.\-]/', '', $raw);
        // hapus pemisah ribuan (titik), ganti koma jadi titik
        $raw = str_replace('.', '', $raw);
        $raw = str_replace(',', '.', $raw);

        // validasi numeric setelah normalisasi
        if (!is_numeric($raw)) return null;

        // simpan sebagai string decimal (biar tidak kena locale lagi)
        return $raw;
    }
}
