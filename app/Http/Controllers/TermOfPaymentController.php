<?php

namespace App\Http\Controllers;

use App\Models\TermOfPayment;
use Illuminate\Http\Request;

class TermOfPaymentController extends Controller
{
    public function index(Request $request)
    {
        $q = $request->string('q')->toString();
        $status = $request->string('status')->toString();

        $rows = TermOfPayment::query()
            ->when($q !== '', function ($qq) use ($q) {
                $qq->where('code', 'like', "%{$q}%")
                    ->orWhere('description', 'like', "%{$q}%");
            })
            ->when($status === 'active', fn ($qq) => $qq->where('is_active', true))
            ->when($status === 'inactive', fn ($qq) => $qq->where('is_active', false))
            ->orderBy('code')
            ->paginate(20)
            ->withQueryString();

        return view('admin.term_of_payments.index', compact('rows', 'q', 'status'));
    }

    public function create()
    {
        $existing = TermOfPayment::pluck('code')->all();
        $availableCodes = array_values(array_diff(TermOfPayment::ALLOWED_CODES, $existing));

        $row = new TermOfPayment(['is_active' => true]);
        return view('admin.term_of_payments.form', compact('row', 'availableCodes'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'max:16', 'unique:term_of_payments,code'],
            'description' => ['nullable', 'string', 'max:255'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $code = strtoupper(trim($data['code']));
        if (!in_array($code, TermOfPayment::ALLOWED_CODES, true)) {
            return back()->withErrors(['code' => 'Kode TOP tidak valid.'])->withInput();
        }

        $data['code'] = $code;
        $data['is_active'] = $request->boolean('is_active');

        TermOfPayment::create($data);

        return redirect()->route('term-of-payments.index')
            ->with('success', 'Term of Payment created.');
    }

    public function show(TermOfPayment $termOfPayment)
    {
        return redirect()->route('term-of-payments.edit', $termOfPayment);
    }

    public function edit(TermOfPayment $termOfPayment)
    {
        $row = $termOfPayment;
        $availableCodes = TermOfPayment::ALLOWED_CODES;
        return view('admin.term_of_payments.form', compact('row', 'availableCodes'));
    }

    public function update(Request $request, TermOfPayment $termOfPayment)
    {
        $data = $request->validate([
            'description' => ['nullable', 'string', 'max:255'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $data['is_active'] = $request->boolean('is_active');

        $termOfPayment->update($data);

        return redirect()->route('term-of-payments.index')
            ->with('ok', 'Term of Payment updated.');
    }

    public function destroy(TermOfPayment $termOfPayment)
    {
        try {
            $termOfPayment->delete();
            return redirect()->route('term-of-payments.index')
                ->with('ok', 'Term of Payment deleted.');
        } catch (\Throwable $e) {
            return redirect()->route('term-of-payments.index')
                ->with('error', 'Term of Payment tidak bisa dihapus.');
        }
    }
}
