<?php

namespace App\Http\Controllers;

use App\Models\Size;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Support\Str;

class SizeController extends Controller
{
    public function index()
    {
        $sizes = Size::orderBy('sort_order')->orderBy('name')->paginate(20);
        return view('sizes.index', compact('sizes'));
    }

    public function create(Request $r)
    {
        if ($r->boolean('modal')) {
            return view('sizes._modal_create');
        }

        return view('sizes.create');
    }

    public function store(Request $r)
    {
        $data = $r->validate([
            'name'        => ['required', 'max:50', Rule::unique('sizes', 'name')],
            'description' => ['nullable', 'max:255'],
            'is_active'   => ['sometimes', 'boolean'],
            'sort_order'  => ['nullable', 'integer'],
        ]);

        $data['is_active'] = $r->boolean('is_active', true);
        $data['slug'] = $this->makeUniqueSlug($data['name']);

        Size::create($data);

        return redirect()->route('sizes.index')->with('ok', 'Size ditambahkan.');
    }

    public function edit(Request $r, Size $size)
    {
        if ($r->boolean('modal')) {
            return view('sizes._modal_edit', compact('size'));
        }

        return view('sizes.edit', compact('size'));
    }

    public function update(Request $r, Size $size)
    {
        $data = $r->validate([
            'name'        => ['required', 'max:50', Rule::unique('sizes', 'name')->ignore($size->id)],
            'description' => ['nullable', 'max:255'],
            'is_active'   => ['sometimes', 'boolean'],
            'sort_order'  => ['nullable', 'integer'],
        ]);

        $data['is_active'] = $r->boolean('is_active', true);
        $data['slug'] = $this->makeUniqueSlug($data['name'], $size->id);

        $size->update($data);

        return back()->with('ok', 'Size diperbarui.');
    }

    public function destroy(Size $size)
    {
        if ($size->items()->exists()) {
            return back()->with('error', 'Tidak bisa dihapus karena sudah digunakan item. Nonaktifkan saja.');
        }

        $size->delete();

        return back()->with('ok', 'Size dihapus.');
    }

    /**
     * Generate slug yang aman untuk desimal dan dijamin unik.
     * Contoh: "2.5kg" => "2-5kg" (bukan "25kg")
     */
    private function makeUniqueSlug(string $name, ?int $ignoreId = null): string
    {
        // jaga desimal: titik jadi dash sebelum slugify
        $normalized = str_replace('.', '-', trim($name));
        $base = Str::slug($normalized);

        // fallback kalau hasil slug kosong
        if ($base === '') {
            $base = 'size';
        }

        $slug = $base;
        $i = 2;

        $query = Size::query();

        if ($ignoreId) {
            $query->where('id', '!=', $ignoreId);
        }

        while ((clone $query)->where('slug', $slug)->exists()) {
            $slug = $base . '-' . $i++;
        }

        return $slug;
    }
}
