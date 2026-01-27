<?php

namespace App\Http\Controllers;

use App\Models\Labor;
use App\Models\LaborCost;
use App\Models\SubContractor;
use App\Support\Number;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class LaborController extends Controller
{
    public function index(Request $request)
    {
        $q = $request->string('q')->toString();
        $status = $request->string('status')->toString();

        $rows = Labor::query()
            ->with('defaultSubContractor:id,name')
            ->when($q !== '', function ($qq) use ($q) {
                $qq->where(function ($w) use ($q) {
                    $w->where('code', 'like', "%{$q}%")
                      ->orWhere('name', 'like', "%{$q}%")
                      ->orWhere('unit', 'like', "%{$q}%");
                });
            })
            ->when($status === 'active', fn ($qq) => $qq->where('is_active', true))
            ->when($status === 'inactive', fn ($qq) => $qq->where('is_active', false))
            ->orderBy('code')
            ->paginate(20)
            ->withQueryString();

        return view('admin.labors.index', compact('rows', 'q', 'status'));
    }

    public function create()
    {
        $row = new Labor([
            'unit' => 'LS',
            'is_active' => true,
        ]);

        return view('admin.labors.form', [
            'row' => $row,
            'subContractors' => collect(),
            'selectedSubContractorId' => null,
            'defaultSubContractorName' => null,
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validateLabor($request);
        Labor::create($data);

        return redirect()->route('labors.index')
            ->with('success', 'Labor created.');
    }

    public function show(Labor $labor)
    {
        return redirect()->route('labors.edit', $labor);
    }

    public function edit(Request $request, Labor $labor)
    {
        $row = $labor;
        $subContractors = SubContractor::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name']);

        $selectedSubContractorId = $labor->default_sub_contractor_id;
        if (!$selectedSubContractorId && $subContractors->isNotEmpty()) {
            $selectedSubContractorId = $subContractors->first()->id;
        }

        if ($request->filled('sub_contractor_id')) {
            $selectedSubContractorId = (int) $request->input('sub_contractor_id');
        }

        $defaultSubContractorName = $labor->defaultSubContractor?->name;

        return view('admin.labors.form', compact(
            'row',
            'subContractors',
            'selectedSubContractorId',
            'defaultSubContractorName'
        ));
    }

    public function update(Request $request, Labor $labor)
    {
        $data = $this->validateLabor($request, $labor->id);
        $labor->update($data);

        return redirect()->route('labors.index')
            ->with('ok', 'Labor updated.');
    }

    public function destroy(Labor $labor)
    {
        try {
            $labor->delete();
            return redirect()->route('labors.index')
                ->with('ok', 'Labor deleted.');
        } catch (\Throwable $e) {
            return redirect()->route('labors.index')
                ->with('error', 'Labor tidak bisa dihapus.');
        }
    }

    public function cost(Request $request, Labor $labor)
    {
        $data = $request->validate([
            'sub_contractor_id' => ['required', 'exists:sub_contractors,id'],
        ]);

        $cost = LaborCost::query()
            ->where('labor_id', $labor->id)
            ->where('sub_contractor_id', $data['sub_contractor_id'])
            ->first();

        return response()->json([
            'exists' => (bool) $cost,
            'cost_amount' => $cost ? (float) $cost->cost_amount : null,
            'is_active' => $cost ? (bool) $cost->is_active : true,
            'default_sub_contractor_id' => $labor->default_sub_contractor_id,
        ]);
    }

    public function storeCost(Request $request, Labor $labor)
    {
        $normalizedCost = Number::idToFloat($request->input('cost_amount'));
        $request->merge(['cost_amount' => $normalizedCost]);

        $data = $request->validate([
            'sub_contractor_id' => ['required', 'exists:sub_contractors,id'],
            'cost_amount' => ['required', 'numeric', 'min:0'],
            'is_active' => ['nullable', 'boolean'],
            'set_default' => ['nullable', 'boolean'],
        ]);

        $cost = LaborCost::updateOrCreate(
            [
                'labor_id' => $labor->id,
                'sub_contractor_id' => $data['sub_contractor_id'],
            ],
            [
                'cost_amount' => $data['cost_amount'],
                'is_active' => $request->boolean('is_active'),
            ]
        );

        if ($request->boolean('set_default')) {
            $labor->update(['default_sub_contractor_id' => $data['sub_contractor_id']]);
        }

        return redirect()
            ->route('labors.edit', ['labor' => $labor->id, 'sub_contractor_id' => $data['sub_contractor_id']])
            ->with('success', 'Labor cost tersimpan.');
    }

    public function search(Request $request)
    {
        $q = $request->string('q')->toString();

        $rows = Labor::query()
            ->where('is_active', true)
            ->when($q !== '', function ($qq) use ($q) {
                $qq->where(function ($w) use ($q) {
                    $w->where('name', 'like', "%{$q}%")
                      ->orWhere('code', 'like', "%{$q}%");
                });
            })
            ->orderBy('name')
            ->limit(200)
            ->get(['id', 'code', 'name', 'unit']);

        $payload = $rows->map(function (Labor $row) {
            return [
                'id' => $row->id,
                'code' => $row->code,
                'name' => $row->name,
                'unit' => $row->unit,
            ];
        });

        return response()->json($payload);
    }

    private function validateLabor(Request $request, ?int $ignoreId = null): array
    {
        $unique = Rule::unique('labors', 'code');
        if ($ignoreId) {
            $unique = $unique->ignore($ignoreId);
        }

        $data = $request->validate([
            'code' => ['required', 'string', 'max:32', $unique],
            'name' => ['required', 'string', 'max:190'],
            'unit' => ['nullable', 'string', 'max:20'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $data['code'] = strtoupper(trim((string) $data['code']));
        $data['unit'] = $data['unit'] ?? 'LS';
        $data['is_active'] = $request->boolean('is_active');

        return $data;
    }
}
