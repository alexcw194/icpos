<?php

namespace App\Http\Controllers;

use App\Models\FamilyCode;
use App\Models\Item;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class FamilyCodeController extends Controller
{
    public function index(Request $request)
    {
        $q = trim((string) $request->get('q', ''));

        $rows = FamilyCode::query()
            ->when($q !== '', fn ($query) => $query->where('code', 'like', "%{$q}%"))
            ->orderBy('code')
            ->paginate(20)
            ->withQueryString();

        return view('admin.family_codes.index', compact('rows', 'q'));
    }

    public function create()
    {
        $row = new FamilyCode();

        return view('admin.family_codes.form', compact('row'));
    }

    public function store(Request $request)
    {
        $request->merge([
            'code' => trim((string) $request->input('code')),
        ]);

        $data = $request->validate([
            'code' => ['required', 'string', 'max:50', 'unique:family_codes,code'],
        ]);

        FamilyCode::create($data);

        return redirect()->route('family-codes.index')->with('success', 'Family Code created.');
    }

    public function edit(FamilyCode $familyCode)
    {
        $row = $familyCode;

        return view('admin.family_codes.form', compact('row'));
    }

    public function update(Request $request, FamilyCode $familyCode)
    {
        $request->merge([
            'code' => trim((string) $request->input('code')),
        ]);

        $data = $request->validate([
            'code' => ['required', 'string', 'max:50', Rule::unique('family_codes', 'code')->ignore($familyCode->id)],
        ]);

        DB::transaction(function () use ($familyCode, $data) {
            $oldCode = (string) $familyCode->code;
            $newCode = (string) $data['code'];

            $familyCode->update(['code' => $newCode]);

            if ($oldCode !== $newCode) {
                Item::where('family_code', $oldCode)->update(['family_code' => $newCode]);
            }
        });

        return redirect()->route('family-codes.index')->with('success', 'Family Code updated.');
    }

    public function destroy(FamilyCode $familyCode)
    {
        if (Item::where('family_code', $familyCode->code)->exists()) {
            return redirect()
                ->route('family-codes.index')
                ->with('error', 'Tidak bisa menghapus: Family Code dipakai Item.');
        }

        $familyCode->delete();

        return redirect()->route('family-codes.index')->with('success', 'Family Code deleted.');
    }
}

