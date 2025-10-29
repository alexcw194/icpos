<?php

namespace App\Http\Controllers;

use App\Models\Brand;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class BrandController extends Controller
{
    public function index(Request $request)
    {
        $q = trim((string) $request->get('q', ''));
        $rows = Brand::query()
            ->when($q, fn($x) => $x->where('name', 'like', "%{$q}%"))
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString();

        return view('admin.brands.index', compact('rows','q'));
    }

    public function create()
    {
        $row = new \App\Models\Brand(['is_active' => true]); // atau Brand
        return view('admin.brands.form', compact('row')); 
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name'      => 'required|string|max:100|unique:brands,name',
            'is_active' => 'sometimes|boolean',
        ]);

        $slug = Str::slug($data['name']);
        if (Brand::withTrashed()->where('slug', $slug)->exists()) {
            $slug .= '-'.Str::random(4);
        }
        $data['slug'] = $slug;
        $data['is_active'] = (bool)($data['is_active'] ?? true);

        Brand::create($data);

        return redirect()->back()->with('success','Brand dibuat.');
    }

    public function edit(Brand $brand)
    {
        return view('admin.brands.form', ['row' => $brand]);
    }

    public function update(Request $request, Brand $brand)
    {
        $data = $request->validate([
            'name'      => 'required|string|max:100|unique:brands,name,'.$brand->id,
            'is_active' => 'sometimes|boolean',
        ]);

        $slug = Str::slug($data['name']);
        if ($slug !== $brand->slug && Brand::withTrashed()->where('slug', $slug)->exists()) {
            $slug .= '-'.Str::random(4);
        }
        $data['slug'] = $slug;
        $data['is_active'] = (bool)($data['is_active'] ?? $brand->is_active);

        $brand->update($data);

        return redirect()->back()->with('success','Brand diperbarui.');
    }

    public function destroy(Brand $brand)
    {
        if (method_exists($brand, 'items') && $brand->items()->exists()) {
            return back()->with('error','Tidak bisa menghapus: Brand dipakai Item.');
        }
        $brand->delete();

        return back()->with('success','Brand dihapus.');
    }
}
