<?php

namespace App\Http\Controllers;

use App\Models\Jenis;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class JenisController extends Controller
{
    public function index(Request $request)
    {
        $q = trim((string) $request->get('q', ''));
        $rows = Jenis::query()
            ->when($q, fn($x) => $x->where('name', 'like', "%{$q}%"))
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString();

        return view('admin.jenis.index', compact('rows','q'));
    }

    public function create()
    {
        $row = new \App\Models\Jenis(['is_active' => true]); // atau Brand
        return view('admin.jenis.form', compact('row')); 
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name'        => 'required|string|max:100|unique:jenis,name',
            'description' => 'nullable|string',
            'is_active'   => 'sometimes|boolean',
        ]);

        $slug = Str::slug($data['name']);
        if (Jenis::withTrashed()->where('slug', $slug)->exists()) {
            $slug .= '-'.Str::random(4);
        }
        $data['slug'] = $slug;
        $data['is_active'] = (bool)($data['is_active'] ?? true);

        Jenis::create($data);

        return redirect()->back()->with('success','Jenis dibuat.');
    }

    public function edit(Jenis $jenis)
    {
        return view('admin.jenis.form', ['row' => $jenis]);
    }

    public function update(Request $request, Jenis $jenis)
    {
        $data = $request->validate([
            'name'        => 'required|string|max:100|unique:jenis,name,'.$jenis->id,
            'description' => 'nullable|string',
            'is_active'   => 'sometimes|boolean',
        ]);

        $slug = Str::slug($data['name']);
        if ($slug !== $jenis->slug && Jenis::withTrashed()->where('slug', $slug)->exists()) {
            $slug .= '-'.Str::random(4);
        }
        $data['slug'] = $slug;
        $data['is_active'] = (bool)($data['is_active'] ?? $jenis->is_active);

        $jenis->update($data);

        return redirect()->back()->with('success','Jenis diperbarui.');
    }

    public function destroy(Jenis $jenis)
    {
        if (method_exists($jenis, 'customers') && $jenis->customers()->exists()) {
            return back()->with('error','Tidak bisa menghapus: Jenis dipakai Customer.');
        }
        $jenis->delete();

        return back()->with('success','Jenis dihapus.');
    }
}
