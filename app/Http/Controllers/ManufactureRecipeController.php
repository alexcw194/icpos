<?php

namespace App\Http\Controllers;

use App\Models\Item;
use App\Models\ItemVariant;
use App\Models\ManufactureRecipe;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\DB;

class ManufactureRecipeController extends Controller
{
    private function kitTypes(): array
    {
        return ['kit', 'bundle'];
    }

    public function index(Request $request)
    {
        $q = trim((string) $request->query('q', ''));

        $kits = Item::query()
            ->whereIn('item_type', $this->kitTypes())
            ->whereHas('manufactureRecipes')
            ->when($q !== '', function ($query) use ($q) {
                $query->where(function ($sub) use ($q) {
                    $sub->where('name', 'like', "%{$q}%")
                        ->orWhere('sku', 'like', "%{$q}%");
                });
            })
            ->withCount('manufactureRecipes')
            ->withMax('manufactureRecipes', 'updated_at')
            ->with([
                'manufactureRecipes' => function ($r) {
                    $r->with(['componentVariant.item'])
                      ->orderByRaw('COALESCE(component_variant_id, 0) asc');
                }
            ])
            ->orderBy('name')
            ->paginate(12)
            ->withQueryString();

        return view('manufacture_recipes.index', compact('kits', 'q'));
    }

    public function create()
    {
        $parentItems = Item::query()
            ->whereIn('item_type', $this->kitTypes())
            ->orderBy('name')
            ->get();

        // Komponen = variant (SKU unik)
        $componentVariants = ItemVariant::query()
            ->with('item')
            ->orderByRaw('COALESCE(sku, "") asc')
            ->get();

        return view('manufacture_recipes.create', compact('parentItems', 'componentVariants'));
    }

    public function manage(Item $parentItem)
    {
        abort_unless(in_array($parentItem->item_type, $this->kitTypes(), true), 404);

        $recipes = ManufactureRecipe::query()
            ->where('parent_item_id', $parentItem->id)
            ->with(['componentVariant.item'])
            ->orderByRaw('COALESCE(component_variant_id, 0) asc')
            ->get();

        $componentVariants = ItemVariant::query()
            ->with('item')
            ->orderByRaw('COALESCE(sku, "") asc')
            ->get();

        return view('manufacture_recipes.manage', compact('parentItem', 'recipes', 'componentVariants'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'parent_item_id' => [
                'required',
                Rule::exists('items', 'id')->whereIn('item_type', $this->kitTypes()),
            ],
            'components' => 'required|array|min:1',
            'components.*.component_variant_id' => 'required|exists:item_variants,id',
            'components.*.qty_required' => 'required|numeric|decimal:0,1|min:0.1',
            'components.*.unit_factor' => 'nullable|numeric|min:0',
            'components.*.notes' => 'nullable|string|max:255',
        ]);

        $parentId = (int) $validated['parent_item_id'];

        $variantIds = collect($validated['components'])
            ->pluck('component_variant_id')
            ->map(fn ($v) => (int) $v)
            ->values();

        // duplikat variant (SKU unik)
        if ($variantIds->count() !== $variantIds->unique()->count()) {
            return back()->withInput()->withErrors([
                'components' => 'Komponen (SKU/Variant) tidak boleh duplikat dalam satu resep.',
            ]);
        }

        // map variant -> item_id untuk guard parent
        $variantItemMap = ItemVariant::query()
            ->whereIn('id', $variantIds->all())
            ->pluck('item_id', 'id'); // [variant_id => item_id]

        if ($variantItemMap->values()->contains($parentId)) {
            return back()->withInput()->withErrors([
                'components' => 'Komponen tidak boleh berasal dari Item Hasil.',
            ]);
        }

        DB::transaction(function () use ($validated, $parentId, $variantItemMap) {
            foreach ($validated['components'] as $row) {
                $vid = (int) $row['component_variant_id'];

                ManufactureRecipe::updateOrCreate(
                    [
                        'parent_item_id' => $parentId,
                        'component_variant_id' => $vid,
                    ],
                    [
                        // derived field (optional tapi berguna untuk reporting/compat)
                        'component_item_id' => (int) ($variantItemMap[$vid] ?? 0),
                        'qty_required' => $row['qty_required'],
                        'unit_factor'  => $row['unit_factor'] ?? null,
                        'notes'        => $row['notes'] ?? null,
                    ]
                );
            }
        });

        return redirect()
            ->route('manufacture-recipes.manage', $parentId)
            ->with('success', 'Resep berhasil disimpan.');
    }

    public function bulkUpdate(Request $request, Item $parentItem)
    {
        abort_unless(in_array($parentItem->item_type, $this->kitTypes(), true), 404);

        $validated = $request->validate([
            'components' => 'required|array|min:1',
            'components.*.component_variant_id' => 'required|exists:item_variants,id',
            'components.*.qty_required' => 'required|numeric|decimal:0,1|min:0.1',
            'components.*.unit_factor' => 'nullable|numeric|min:0',
            'components.*.notes' => 'nullable|string|max:255',
        ]);

        $variantIds = collect($validated['components'])
            ->pluck('component_variant_id')
            ->map(fn ($v) => (int) $v)
            ->values();

        if ($variantIds->count() !== $variantIds->unique()->count()) {
            return back()->withInput()->withErrors([
                'components' => 'Komponen (SKU/Variant) tidak boleh duplikat dalam satu resep.',
            ]);
        }

        $variantItemMap = ItemVariant::query()
            ->whereIn('id', $variantIds->all())
            ->pluck('item_id', 'id');

        if ($variantItemMap->values()->contains((int) $parentItem->id)) {
            return back()->withInput()->withErrors([
                'components' => 'Komponen tidak boleh berasal dari Item Hasil.',
            ]);
        }

        DB::transaction(function () use ($validated, $parentItem, $variantIds, $variantItemMap) {
            // sync delete by variant_id
            ManufactureRecipe::query()
                ->where('parent_item_id', $parentItem->id)
                ->whereNotIn('component_variant_id', $variantIds->all())
                ->delete();

            foreach ($validated['components'] as $row) {
                $vid = (int) $row['component_variant_id'];

                ManufactureRecipe::updateOrCreate(
                    [
                        'parent_item_id' => $parentItem->id,
                        'component_variant_id' => $vid,
                    ],
                    [
                        'component_item_id' => (int) ($variantItemMap[$vid] ?? 0),
                        'qty_required' => $row['qty_required'],
                        'unit_factor'  => $row['unit_factor'] ?? null,
                        'notes'        => $row['notes'] ?? null,
                    ]
                );
            }
        });

        return redirect()
            ->route('manufacture-recipes.manage', $parentItem)
            ->with('success', 'Resep berhasil diperbarui.');
    }

    public function destroy(ManufactureRecipe $manufactureRecipe)
    {
        $manufactureRecipe->delete();
        return back()->with('success', 'Resep dihapus.');
    }
}
