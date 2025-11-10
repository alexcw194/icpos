<?php

namespace App\Http\Controllers;

use App\Models\Item;
use App\Models\ManufactureRecipe;
use Illuminate\Http\Request;

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
        $items = Item::orderBy('name')->get();
        return view('manufacture_recipes.create', compact('items'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'parent_item_id' => 'required|exists:items,id',
            'component_item_id' => 'required|exists:items,id|different:parent_item_id',
            'qty_required' => 'required|numeric|min:0.001',
            'unit_factor' => 'nullable|numeric|min:0',
            'notes' => 'nullable|string|max:255',
        ]);

        ManufactureRecipe::create($data);
        return redirect()->route('manufacture-recipes.index')->with('success', 'Resep berhasil ditambahkan.');
    }

    public function destroy(ManufactureRecipe $manufactureRecipe)
    {
        $manufactureRecipe->delete();
        return back()->with('success', 'Resep dihapus.');
    }
}
