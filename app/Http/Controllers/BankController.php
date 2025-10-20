<?php

namespace App\Http\Controllers;

use App\Models\Bank;
use App\Models\Company;
use Illuminate\Http\Request;

class BankController extends Controller
{
    public function index()
    {
        $banks = Bank::with('company')
            ->orderBy('company_id')
            ->orderBy('name')
            ->paginate(20);

        return view('banks.index', compact('banks'));
    }

    public function create()
    {
        $bank = new Bank();
        $companies = Company::orderByRaw('COALESCE(NULLIF(alias,""), name)')
            ->get(['id','alias','name']);

        return view('banks.create_edit', compact('bank','companies'));
    }

    public function edit(Bank $bank)
    {
        $companies = Company::orderByRaw('COALESCE(NULLIF(alias,""), name)')
            ->get(['id','alias','name']);

        return view('banks.create_edit', compact('bank','companies'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'company_id'   => ['required','exists:companies,id'],
            'code'         => ['nullable','string','max:30'],
            'name'         => ['required','string','max:100'],
            'account_name' => ['nullable','string','max:100'],
            'account_no'   => ['nullable','string','max:50'],
            'branch'       => ['nullable','string','max:100'],
            'tax_scope'    => ['nullable','in:ppn,non_ppn'], // kalau kamu pakai scope PPN/NON-PPN
            'notes'        => ['nullable','string'],
            'is_active'    => ['nullable','boolean'],
        ]);

        $data['is_active'] = $request->boolean('is_active');

        Bank::create($data);

        return redirect()->route('banks.index')->with('success','Bank created.');
    }

    public function update(Request $request, Bank $bank)
    {
        $data = $request->validate([
            'company_id'   => ['required','exists:companies,id'],
            'code'         => ['nullable','string','max:30'],
            'name'         => ['required','string','max:100'],
            'account_name' => ['nullable','string','max:100'],
            'account_no'   => ['nullable','string','max:50'],
            'branch'       => ['nullable','string','max:100'],
            'tax_scope'    => ['nullable','in:ppn,non_ppn'],
            'notes'        => ['nullable','string'],
            'is_active'    => ['nullable','boolean'],
        ]);

        $data['is_active'] = $request->boolean('is_active');

        $bank->update($data);

        return redirect()->route('banks.index')->with('success','Bank updated.');
    }
}