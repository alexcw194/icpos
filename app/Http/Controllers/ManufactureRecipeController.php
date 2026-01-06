<?php

namespace App\Http\Controllers;

use App\Models\Item;
use App\Models\ManufactureRecipe;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\DB;

class ManufactureRecipeController extends Controller
{
    private function kitTypes(): array
    {
        // sesuaikan jika value item_type kamu beda
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
                    $r->with('componentItem')->orderBy('component_item_id');
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

        $componentItems = Item::query()
            ->orderBy('name')
            ->get();

        return view('manufacture_recipes.create', compact('parentItems', 'componentItems'));
    }

    public function manage(Item $parentItem)
    {
        abort_unless(in_array($parentItem->item_type, $this->kitTypes(), true), 404);

        $recipes = ManufactureRecipe::query()
            ->where('parent_item_id', $parentItem->id)
            ->with('componentItem')
            ->orderBy('component_item_id')
            ->get();

        $componentItems = Item::query()
            ->orderBy('name')
            ->get();

        return view('manufacture_recipes.manage', compact('parentItem', 'recipes', 'componentItems'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'parent_item_id' => [
                'required',
                Rule::exists('items', 'id')->whereIn('item_type', $this->kitTypes()),
            ],
            'components' => 'required|array|min:1',
            'components.*.component_item_id' => 'required|exists:items,id',
            'components.*.qty_required' => 'required|numeric|decimal:0,1|min:0.1',
            'components.*.unit_factor' => 'nullable|numeric|min:0',
            'components.*.notes' => 'nullable|string|max:255',
        ]);

        $parentId = (int) $validated['parent_item_id'];

        $componentIds = collect($validated['components'])
            ->pluck('component_item_id')
            ->map(fn($v) => (int) $v);

        if ($componentIds->contains($parentId)) {
            return back()->withInput()->withErrors([
                'components' => 'Komponen tidak boleh sama dengan Item Hasil.',
            ]);
        }

        if ($componentIds->count() !== $componentIds->unique()->count()) {
            return back()->withInput()->withErrors([
                'components' => 'Komponen tidak boleh duplikat dalam satu resep.',
            ]);
        }

        DB::transaction(function () use ($validated, $parentId) {
            foreach ($validated['components'] as $row) {
                ManufactureRecipe::updateOrCreate(
                    [
                        'parent_item_id' => $parentId,
                        'component_item_id' => $row['component_item_id'],
                    ],
                    [
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
            'components.*.component_item_id' => 'required|exists:items,id',
            'components.*.qty_required' => 'required|numeric|decimal:0,1|min:0.1',
            'components.*.unit_factor' => 'nullable|numeric|min:0',
            'components.*.notes' => 'nullable|string|max:255',
        ]);

        $componentIds = collect($validated['components'])
            ->pluck('component_item_id')
            ->map(fn($v) => (int) $v)
            ->values();

        if ($componentIds->contains((int) $parentItem->id)) {
            return back()->withInput()->withErrors([
                'components' => 'Komponen tidak boleh sama dengan Item Hasil.',
            ]);
        }

        if ($componentIds->count() !== $componentIds->unique()->count()) {
            return back()->withInput()->withErrors([
                'components' => 'Komponen tidak boleh duplikat dalam satu resep.',
            ]);
        }

        DB::transaction(function () use ($validated, $parentItem, $componentIds) {
            // delete yang tidak ada lagi di form (sync)
            ManufactureRecipe::query()
                ->where('parent_item_id', $parentItem->id)
                ->whereNotIn('component_item_id', $componentIds->all())
                ->delete();

            foreach ($validated['components'] as $row) {
                ManufactureRecipe::updateOrCreate(
                    [
                        'parent_item_id' => $parentItem->id,
                        'component_item_id' => $row['component_item_id'],
                    ],
                    [
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
