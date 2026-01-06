<?php

namespace App\Http\Controllers;

use App\Models\Item;
use App\Models\ManufactureRecipe;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

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
        $kitTypes = ['kit', 'bundle']; // SESUAIKAN dengan value item_type kamu

        $data = $request->validate([
            'parent_item_id' => [
                'required',
                Rule::exists('items', 'id')->whereIn('item_type', $kitTypes),
            ],
            'component_item_id' => [
                'required',
                'different:parent_item_id',
                Rule::exists('items', 'id'),
            ],
            'qty_required' => 'required|numeric|min:0.001',
            'unit_factor' => 'nullable|numeric|min:0',
            'notes' => 'nullable|string|max:255',
        ]);

        ManufactureRecipe::create($data);

        return redirect()
            ->route('manufacture-recipes.index')
            ->with('success', 'Resep berhasil ditambahkan.');
    }

    public function destroy(ManufactureRecipe $manufactureRecipe)
    {
        $manufactureRecipe->delete();
        return back()->with('success', 'Resep dihapus.');
    }
}
