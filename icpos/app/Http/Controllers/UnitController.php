<?php

namespace App\Http\Controllers;

use App\Models\Unit;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class UnitController extends Controller
{
    public function index(Request $request)
    {
        $q      = $request->string('q')->toString();
        $status = $request->string('status')->toString(); // '', 'active', 'inactive'

        $rows = Unit::query()
            // kalau kamu punya scopeKeyword, boleh pakai ->keyword($q) saja
            ->when($q !== '', function ($qq) use ($q) {
                $qq->where(function ($w) use ($q) {
                    $w->where('code', 'like', "%{$q}%")
                      ->orWhere('name', 'like', "%{$q}%");
                });
            })
            ->when($status === 'active',   fn($qw) => $qw->where('is_active', true))
            ->when($status === 'inactive', fn($qw) => $qw->where('is_active', false))
            ->orderBy('code')
            ->paginate(20)
            ->withQueryString();

        // ⬇️ render ke admin view, bukan resources/views/units/*
        return view('admin.units.index', compact('rows', 'q', 'status'));
    }

    public function create()
    {
        $row = new Unit(['is_active' => true]);
        return view('admin.units.form', compact('row'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'code'      => ['required','string','max:20','unique:units,code'],
            'name'      => ['required','string','max:80'],
            'is_active' => ['nullable','boolean'],
        ]);
        $data['is_active'] = $request->boolean('is_active');

        Unit::create($data);

        // konsisten dengan admin/jenis -> pakai 'success'
        return redirect()->route('units.index')->with('success', 'Unit created.');
    }

    // opsional: arahkan show ke edit (karena tidak ada view show terpisah di admin)
    public function show(Unit $unit)
    {
        return redirect()->route('units.edit', $unit);
    }

    public function edit(Unit $unit)
    {
        $row = $unit;
        return view('admin.units.form', compact('row'));
    }

    public function update(Request $request, Unit $unit)
    {
        $data = $request->validate([
            'code'      => ['required','string','max:20', Rule::unique('units','code')->ignore($unit->id)],
            'name'      => ['required','string','max:100'], // selaras dengan model/request
            'is_active' => ['nullable','boolean'],
        ]);
        $data['is_active'] = $request->boolean('is_active');

        // Lindungi UNIT PCS
        $isPcs = strcasecmp($unit->code, 'PCS') === 0;
        if ($isPcs) {
            // Kode tak boleh berubah dan harus selalu aktif
            $data['code']      = 'PCS';
            $data['is_active'] = true;
        }

        $unit->update($data);

        return redirect()->route('units.index')->with('ok', 'Unit updated.');
    }

    public function destroy(Unit $unit)
    {
        // Lindungi UNIT PCS
        if (strcasecmp($unit->code, 'PCS') === 0) {
            return redirect()->route('units.index')
                ->with('ok', 'Unit "PCS" tidak boleh dihapus.');
        }

        // Cegah hapus bila masih dipakai item
        if (method_exists($unit, 'items') && $unit->items()->exists()) {
            return redirect()->route('units.index')
                ->with('ok', 'Unit tidak bisa dihapus karena sudah dipakai item. Nonaktifkan saja.');
        }

        try {
            $unit->delete();
            return redirect()->route('units.index')->with('ok', 'Unit deleted.');
        } catch (\Throwable $e) {
            // fallback jika ada constraint di DB
            return redirect()->route('units.index')
                ->with('ok', 'Unit tidak bisa dihapus karena sudah dipakai item. Nonaktifkan saja.');
        }
    }

}
