<?php

namespace App\Http\Controllers;

use App\Models\Warehouse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use App\Models\Company;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class WarehouseController extends Controller
{
    /**
     * Tampilkan daftar gudang dengan pencarian dan filter status.
     */
    public function index(Request $request)
    {
        $q      = $request->string('q')->toString();
        $status = $request->string('status')->toString(); // '', 'active', 'inactive'
        $supportsCompanyWarehouse = Schema::hasTable('company_warehouse');

        $rowsQuery = Warehouse::query()
            ->with(['company:id,name,alias'])
            ->when($q !== '', function ($qq) use ($q) {
                $qq->where(function ($w) use ($q) {
                    $w->where('code', 'like', "%{$q}%")
                      ->orWhere('name', 'like', "%{$q}%")
                      ->orWhere('address', 'like', "%{$q}%");
                });
            })
            ->when($status === 'active',   fn($qw) => $qw->where('is_active', true))
            ->when($status === 'inactive', fn($qw) => $qw->where('is_active', false))
            ->orderBy('code');

        if ($supportsCompanyWarehouse) {
            $rowsQuery->with(['companies:id,name,alias']);
        }

        $rows = $rowsQuery
            ->paginate(20)
            ->withQueryString();

        return view('admin.warehouses.index', compact('rows', 'q', 'status', 'supportsCompanyWarehouse'));
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
        $selectedCompanyIds = [];
        return view('admin.warehouses.form', compact('row', 'companies', 'selectedCompanyIds'));
    }

    /**
     * Simpan gudang baru.
     */
    public function store(Request $request)
    {
        if (!$request->filled('company_ids') && $request->filled('company_id')) {
            $request->merge([
                'company_ids' => [(int) $request->input('company_id')],
            ]);
        }

        $data = $request->validate([
            'company_ids'          => ['required','array','min:1'],
            'company_ids.*'        => ['integer','exists:companies,id'],
            'code'                 => ['required','string','max:50','unique:warehouses,code'],
            'name'                 => ['required','string','max:150'],
            'address'              => ['nullable','string'],
            'allow_negative_stock' => ['nullable','boolean'],
            'is_active'            => ['nullable','boolean'],
        ]);
        $data['allow_negative_stock'] = $request->boolean('allow_negative_stock');
        $data['is_active']            = $request->boolean('is_active');
        $companyIds = collect($data['company_ids'])->map(fn ($id) => (int) $id)->unique()->values()->all();
        $primaryCompanyId = $companyIds[0] ?? null;

        DB::transaction(function () use ($data, $companyIds, $primaryCompanyId) {
            $warehouse = Warehouse::create([
                'company_id' => $primaryCompanyId,
                'code' => $data['code'],
                'name' => $data['name'],
                'address' => $data['address'] ?? null,
                'allow_negative_stock' => $data['allow_negative_stock'],
                'is_active' => $data['is_active'],
            ]);

            if (Schema::hasTable('company_warehouse')) {
                $warehouse->companies()->sync($companyIds);
            }
        });

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
        $selectedCompanyIds = Schema::hasTable('company_warehouse')
            ? $warehouse->companies()->pluck('companies.id')->map(fn ($id) => (int) $id)->all()
            : [];
        if (empty($selectedCompanyIds) && $warehouse->company_id) {
            $selectedCompanyIds = [(int) $warehouse->company_id];
        }

        return view('admin.warehouses.form', compact('row', 'companies', 'selectedCompanyIds'));
    }

    /**
     * Update gudang.
     */
    public function update(Request $request, Warehouse $warehouse)
    {
        if (!$request->filled('company_ids') && $request->filled('company_id')) {
            $request->merge([
                'company_ids' => [(int) $request->input('company_id')],
            ]);
        }

        $data = $request->validate([
            'company_ids'          => ['required','array','min:1'],
            'company_ids.*'        => ['integer','exists:companies,id'],
            'code'                 => ['required','string','max:50', Rule::unique('warehouses','code')->ignore($warehouse->id)],
            'name'                 => ['required','string','max:150'],
            'address'              => ['nullable','string'],
            'allow_negative_stock' => ['nullable','boolean'],
            'is_active'            => ['nullable','boolean'],
        ]);
        $data['allow_negative_stock'] = $request->boolean('allow_negative_stock');
        $data['is_active']            = $request->boolean('is_active');
        $companyIds = collect($data['company_ids'])->map(fn ($id) => (int) $id)->unique()->values()->all();
        $primaryCompanyId = $companyIds[0] ?? null;

        DB::transaction(function () use ($warehouse, $data, $companyIds, $primaryCompanyId) {
            $warehouse->update([
                'company_id' => $primaryCompanyId,
                'code' => $data['code'],
                'name' => $data['name'],
                'address' => $data['address'] ?? null,
                'allow_negative_stock' => $data['allow_negative_stock'],
                'is_active' => $data['is_active'],
            ]);

            if (Schema::hasTable('company_warehouse')) {
                $warehouse->companies()->sync($companyIds);
            }
        });

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
