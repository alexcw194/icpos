<?php

namespace App\Http\Controllers;

use App\Models\Company;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class CompanyController extends Controller
{
    public function index(): View
    {
        $companies = Company::orderBy('name')->paginate(12);
        return view('companies.index', compact('companies'));
    }

    public function create(): View
    {
        return view('companies.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name'                => 'required|string|max:255',
            'alias'               => 'nullable|string|max:16',
            // is_taxable diambil via $request->boolean() di bawah
            'default_tax_percent' => 'nullable|numeric|min:0|max:100',
            'quotation_prefix'    => 'nullable|string|max:10',
            'invoice_prefix'      => 'nullable|string|max:10',
            'delivery_prefix'     => 'nullable|string|max:10',
            'address'             => 'nullable|string',
            'tax_id'              => 'nullable|string|max:64',
            'email'               => 'nullable|email|max:128',
            'phone'               => 'nullable|string|max:64',
            'logo'                => 'nullable|image|max:2048',
            'is_default'          => 'nullable|boolean',
            // NEW: default masa berlaku quotation (hari). Boleh kosong → fallback 30 di sisi Quotation.
            'default_valid_days'  => 'nullable|integer|min:1|max:365',
            'banks'               => 'nullable|array',
            'banks.*.id'          => 'nullable|integer',
            'banks.*.name'        => 'nullable|string|max:128',
            'banks.*.account_name'=> 'nullable|string|max:128',
            'banks.*.account_no'  => 'nullable|string|max:64',
            'banks.*.branch'      => 'nullable|string|max:128',
            'banks.*.is_active'   => 'nullable|boolean',
            'banks.*._delete'     => 'nullable|boolean',
        ]);

        // Normalisasi flag boolean
        $data['is_taxable'] = $request->boolean('is_taxable');

        // ENFORCE: jika non-taxable, pajak harus 0
        $data['default_tax_percent'] = $data['is_taxable']
            ? ($data['default_tax_percent'] ?? 0)
            : 0;

        // Normalisasi default_valid_days (kosong → null)
        $data['default_valid_days'] = $request->filled('default_valid_days')
            ? (int) $request->input('default_valid_days')
            : null;

        // Simpan logo bila ada
        if ($request->hasFile('logo')) {
            $data['logo_path'] = $request->file('logo')->store('logos', 'public');
        }

        $setDefault = $request->boolean('is_default');
        $bankRows = $request->input('banks', []);

        DB::transaction(function () use ($data, $setDefault, $bankRows) {
            if ($setDefault) {
                Company::where('is_default', true)->update(['is_default' => false]);
                $data['is_default'] = true;
            } else {
                $data['is_default'] = false;
            }
            $company = Company::create($data);
            $this->syncCompanyBanks($company, $bankRows);
        });

        return redirect()->route('companies.index')->with('success', 'Company created');
    }

    public function edit(Company $company): View
    {
        return view('companies.edit', compact('company'));
    }

    public function update(Request $request, Company $company): RedirectResponse
    {
        $data = $request->validate([
            'name'                => 'required|string|max:255',
            'alias'               => 'nullable|string|max:16',
            // is_taxable diambil via $request->boolean() di bawah
            'default_tax_percent' => 'nullable|numeric|min:0|max:100',
            'quotation_prefix'    => 'nullable|string|max:10',
            'invoice_prefix'      => 'nullable|string|max:10',
            'delivery_prefix'     => 'nullable|string|max:10',
            'address'             => 'nullable|string',
            'tax_id'              => 'nullable|string|max:64',
            'email'               => 'nullable|email|max:128',
            'phone'               => 'nullable|string|max:64',
            'logo'                => 'nullable|image|max:2048',
            'is_default'          => 'nullable|boolean',
            // NEW
            'default_valid_days'  => 'nullable|integer|min:1|max:365',
            'banks'               => 'nullable|array',
            'banks.*.id'          => 'nullable|integer',
            'banks.*.name'        => 'nullable|string|max:128',
            'banks.*.account_name'=> 'nullable|string|max:128',
            'banks.*.account_no'  => 'nullable|string|max:64',
            'banks.*.branch'      => 'nullable|string|max:128',
            'banks.*.is_active'   => 'nullable|boolean',
            'banks.*._delete'     => 'nullable|boolean',
        ]);

        // Normalisasi flag boolean
        $data['is_taxable'] = $request->boolean('is_taxable');

        // ENFORCE: jika non-taxable, pajak harus 0
        $data['default_tax_percent'] = $data['is_taxable']
            ? ($data['default_tax_percent'] ?? 0)
            : 0;

        // Normalisasi default_valid_days (kosong → null)
        $data['default_valid_days'] = $request->filled('default_valid_days')
            ? (int) $request->input('default_valid_days')
            : null;

        // Ganti logo bila ada unggahan baru
        if ($request->hasFile('logo')) {
            $data['logo_path'] = $request->file('logo')->store('logos', 'public');

            // (opsional) hapus logo lama:
            // if ($company->logo_path && Storage::disk('public')->exists($company->logo_path)) {
            //     Storage::disk('public')->delete($company->logo_path);
            // }
        }

        $setDefault = $request->boolean('is_default');
        $bankRows = $request->input('banks', []);

        DB::transaction(function () use ($company, $data, $setDefault, $bankRows) {
            if ($setDefault) {
                Company::where('is_default', true)
                    ->where('id', '!=', $company->id)
                    ->update(['is_default' => false]);
                $data['is_default'] = true;
            } else {
                // Pertahankan status default sebelumnya bila user tidak set default
                $data['is_default'] = $company->is_default;
            }

            $company->update($data);
            $this->syncCompanyBanks($company, $bankRows);
        });

        return redirect()->route('companies.index')->with('success', 'Company updated');
    }

    public function makeDefault(Company $company): RedirectResponse
    {
        DB::transaction(function () use ($company) {
            Company::where('is_default', true)->update(['is_default' => false]);
            $company->update(['is_default' => true]);
        });

        return back()->with('success', 'Default company updated');
    }

    public function destroy(\App\Models\Company $company)
    {
        // Block default company
        if ($company->is_default) {
            return back()->with('error', 'Cannot delete the default company.');
        }

        // Referential integrity checks (no new columns)
        $hasRelations =
            \App\Models\User::where('company_id',$company->id)->exists() ||
            \App\Models\Quotation::where('company_id',$company->id)->exists() ||
            \App\Models\SalesOrder::where('company_id',$company->id)->exists() ||
            \App\Models\Delivery::where('company_id',$company->id)->exists() ||
            \App\Models\Invoice::where('company_id',$company->id)->exists();

        if ($hasRelations) {
            return back()->with('error', 'Company has linked records (users/docs). Set another default and migrate data before deletion.');
        }

        $company->delete(); // hard delete (table already enforced by FKs)
        return redirect()->route('companies.index')->with('success', 'Company deleted.');
    }

    private function syncCompanyBanks(Company $company, array $rows): void
    {
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }

            $id = $row['id'] ?? null;
            $isDelete = !empty($row['_delete']);

            $payload = [
                'name'         => trim((string) ($row['name'] ?? '')),
                'account_name' => trim((string) ($row['account_name'] ?? '')),
                'account_no'   => trim((string) ($row['account_no'] ?? '')),
                'branch'       => trim((string) ($row['branch'] ?? '')),
                'is_active'    => !empty($row['is_active']),
            ];

            $hasData = $payload['name'] !== ''
                || $payload['account_name'] !== ''
                || $payload['account_no'] !== ''
                || $payload['branch'] !== '';

            if ($isDelete) {
                if ($id) {
                    $company->banks()->where('company_id', $company->id)->whereKey($id)->delete();
                }
                continue;
            }

            if (!$hasData || $payload['name'] === '') {
                continue;
            }

            if ($id) {
                $company->banks()->where('company_id', $company->id)->whereKey($id)->update($payload);
            } else {
                $company->banks()->create($payload);
            }
        }
    }
}
