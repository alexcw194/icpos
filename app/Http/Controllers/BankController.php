<?php

namespace App\Http\Controllers;

use App\Models\Bank;
use Illuminate\Http\Request;

class BankController extends Controller
{
    public function index()
    {
        $this->authorize('viewAny', auth()->user()); // optional; atau pastikan ini hanya di EnsureAdmin group
        $banks = Bank::orderBy('is_active', 'desc')->orderBy('name')->paginate(20);
        return view('banks.index', compact('banks'));
    }

    public function create()
    {
        return view('banks.create_edit', ['bank' => new Bank()]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name'         => ['required','string','max:100'],
            'account_name' => ['nullable','string','max:120'],
            'account_no'   => ['nullable','string','max:60'],
            'branch'       => ['nullable','string','max:120'],
            'is_active'    => ['nullable','boolean'],
            'notes'        => ['nullable','string'],
        ]);
        $data['is_active'] = (bool)($data['is_active'] ?? true);

        Bank::create($data);
        return redirect()->route('banks.index')->with('success','Bank saved.');
    }

    public function edit(Bank $bank)
    {
        return view('banks.create_edit', compact('bank'));
    }

    public function update(Request $request, Bank $bank)
    {
        $data = $request->validate([
            'name'         => ['required','string','max:100'],
            'account_name' => ['nullable','string','max:120'],
            'account_no'   => ['nullable','string','max:60'],
            'branch'       => ['nullable','string','max:120'],
            'is_active'    => ['nullable','boolean'],
            'notes'        => ['nullable','string'],
        ]);
        $data['is_active'] = (bool)($data['is_active'] ?? false);

        $bank->update($data);
        return redirect()->route('banks.index')->with('success','Bank updated.');
    }

    public function destroy(Bank $bank)
    {
        $bank->delete();
        return redirect()->route('banks.index')->with('success','Bank deleted.');
    }
}
