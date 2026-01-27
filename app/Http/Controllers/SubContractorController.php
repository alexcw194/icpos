<?php

namespace App\Http\Controllers;

use App\Models\SubContractor;
use Illuminate\Http\Request;

class SubContractorController extends Controller
{
    public function index(Request $request)
    {
        $q = $request->string('q')->toString();
        $status = $request->string('status')->toString();

        $rows = SubContractor::query()
            ->when($q !== '', function ($qq) use ($q) {
                $qq->where('name', 'like', "%{$q}%")
                   ->orWhere('notes', 'like', "%{$q}%");
            })
            ->when($status === 'active', fn ($qq) => $qq->where('is_active', true))
            ->when($status === 'inactive', fn ($qq) => $qq->where('is_active', false))
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString();

        return view('admin.sub_contractors.index', compact('rows', 'q', 'status'));
    }

    public function create()
    {
        $row = new SubContractor(['is_active' => true]);
        return view('admin.sub_contractors.form', compact('row'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:190'],
            'notes' => ['nullable', 'string'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $data['is_active'] = $request->boolean('is_active');

        SubContractor::create($data);

        return redirect()->route('sub-contractors.index')
            ->with('success', 'Sub-Contractor created.');
    }

    public function show(SubContractor $subContractor)
    {
        return redirect()->route('sub-contractors.edit', $subContractor);
    }

    public function edit(SubContractor $subContractor)
    {
        $row = $subContractor;
        return view('admin.sub_contractors.form', compact('row'));
    }

    public function update(Request $request, SubContractor $subContractor)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:190'],
            'notes' => ['nullable', 'string'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $data['is_active'] = $request->boolean('is_active');

        $subContractor->update($data);

        return redirect()->route('sub-contractors.index')
            ->with('ok', 'Sub-Contractor updated.');
    }

    public function destroy(SubContractor $subContractor)
    {
        try {
            $subContractor->delete();
            return redirect()->route('sub-contractors.index')
                ->with('ok', 'Sub-Contractor deleted.');
        } catch (\Throwable $e) {
            return redirect()->route('sub-contractors.index')
                ->with('error', 'Sub-Contractor tidak bisa dihapus.');
        }
    }
}
