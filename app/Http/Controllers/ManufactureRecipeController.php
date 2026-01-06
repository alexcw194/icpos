<?php

namespace App\Http\Controllers;

use App\Models\Item;
use App\Models\ManufactureRecipe;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\DB;


class ManufactureRecipeController extends Controller
{
    public function index()
    {
        $recipes = ManufactureRecipe::with(['parentItem', 'componentItem'])
            ->orderBy('parent_item_id')
            ->paginate(20);

        return view('manufacture_recipes.index', compact('recipes'));
    }

    public function create()
    {
        $kitTypes = ['kit', 'bundle']; // sesuaikan dengan value item_type kamu

        $parentItems = Item::query()
            ->whereIn('item_type', $kitTypes)
            ->orderBy('name')
            ->get();

        $componentItems = Item::query()
            ->orderBy('name')
            ->get();

        return view('manufacture_recipes.create', compact('parentItems', 'componentItems'));
    }

    public function store(Request $request)
    {
        // sesuaikan jika value item_type kamu beda
        $kitTypes = ['kit', 'bundle'];

        $validated = $request->validate([
            'parent_item_id' => [
                'required',
                Rule::exists('items', 'id')->whereIn('item_type', $kitTypes),
            ],

            // INI yang bikin bisa multi row
            'components' => 'required|array|min:1',
            'components.*.component_item_id' => 'required|exists:items,id',
            'components.*.qty_required' => 'required|numeric|min:0.001',
            'components.*.unit_factor' => 'nullable|numeric|min:0',
            'components.*.notes' => 'nullable|string|max:255',
        ]);

        $parentId = (int) $validated['parent_item_id'];

        // guard: komponen tidak boleh sama dengan item hasil
        $componentIds = collect($validated['components'])
            ->pluck('component_item_id')
            ->map(fn($v) => (int) $v);

        if ($componentIds->contains($parentId)) {
            return back()->withInput()->withErrors([
                'components' => 'Komponen tidak boleh sama dengan Item Hasil.',
            ]);
        }

        // guard: jangan ada duplikat komponen di 1 submit
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
            ->route('manufacture-recipes.index')
            ->with('success', 'Resep berhasil disimpan.');
    }

    public function destroy(ManufactureRecipe $manufactureRecipe)
    {
        $manufactureRecipe->delete();
        return back()->with('success', 'Resep dihapus.');
    }
}
