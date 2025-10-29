<?php

namespace App\Http\Controllers;

use App\Models\Warehouse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use App\Models\Company;

class WarehouseController extends Controller
{
    /**
     * Tampilkan daftar gudang dengan pencarian dan filter status.
     */
    public function index(Request $request)
    {
        $q      = $request->string('q')->toString();
        $status = $request->string('status')->toString(); // '', 'active', 'inactive'

        $rows = Warehouse::query()
            ->when($q !== '', function ($qq) use ($q) {
                $qq->where(function ($w) use ($q) {
                    $w->where('code', 'like', "%{$q}%")
                      ->orWhere('name', 'like', "%{$q}%")
                      ->orWhere('address', 'like', "%{$q}%");
                });
            })
            ->when($status === 'active',   fn($qw) => $qw->where('is_active', true))
            ->when($status === 'inactive', fn($qw) => $qw->where('is_active', false))
            ->orderBy('code')
            ->paginate(20)
            ->withQueryString();

        return view('admin.warehouses.index', compact('rows', 'q', 'status'));
    }

    /**
     * Tampilkan form pembuatan gudang.
     */
    public function create()
    {
        $companies = Company::orderBy('name')->get();
        $row = new Warehouse([
            'is_active' => true,
            'allow_negative_stock' => false,
        ]);
        return view('admin.warehouses.form', compact('row', 'companies'));
    }

    /**
     * Simpan gudang baru.
     */
    public function store(Request $request)
    {
        $data = $request->validate([
            'company_id'            => ['required','exists:companies,id'],
            'code'                 => ['required','string','max:50','unique:warehouses,code'],
            'name'                 => ['required','string','max:150'],
            'address'              => ['nullable','string'],
            'allow_negative_stock' => ['nullable','boolean'],
            'is_active'            => ['nullable','boolean'],
        ]);
        $data['allow_negative_stock'] = $request->boolean('allow_negative_stock');
        $data['is_active']            = $request->boolean('is_active');

        Warehouse::create($data);

        return redirect()->route('warehouses.index')->with('success','Warehouse created.');
    }

    /**
     * Arahkan show ke edit (tidak ada halaman show terpisah).
     */
    public function show(Warehouse $warehouse)
    {
        return redirect()->route('warehouses.edit', $warehouse);
    }

    /**
     * Tampilkan form edit.
     */
    public function edit(Warehouse $warehouse)
    {
        $row = $warehouse;
        $companies = Company::orderBy('name')->get();
        return view('admin.warehouses.form', compact('row', 'companies'));
    }

    /**
     * Update gudang.
     */
    public function update(Request $request, Warehouse $warehouse)
    {
        $data = $request->validate([
            'company_id'            => ['required','exists:companies,id'],
            'code'                 => ['required','string','max:50', Rule::unique('warehouses','code')->ignore($warehouse->id)],
            'name'                 => ['required','string','max:150'],
            'address'              => ['nullable','string'],
            'allow_negative_stock' => ['nullable','boolean'],
            'is_active'            => ['nullable','boolean'],
        ]);
        $data['allow_negative_stock'] = $request->boolean('allow_negative_stock');
        $data['is_active']            = $request->boolean('is_active');

        $warehouse->update($data);

        return redirect()->route('warehouses.index')->with('ok','Warehouse updated.');
    }

    /**
     * Hapus gudang. Beri perlindungan jika masih ada relasi.
     */
    public function destroy(Warehouse $warehouse)
    {
        // Cegah hapus jika masih dipakai di stocks atau deliveries
        if ($warehouse->stocks()->exists() || $warehouse->deliveries()->exists()) {
            return redirect()->route('warehouses.index')
                ->with('ok', 'Warehouse tidak bisa dihapus karena sudah dipakai. Nonaktifkan saja.');
        }

        $warehouse->delete();

        return redirect()->route('warehouses.index')
            ->with('ok', 'Warehouse deleted.');
    }
}
