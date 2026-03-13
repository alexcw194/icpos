<?php

namespace App\Http\Controllers;

use App\Models\SalesCommissionRule;
use App\Models\Brand;
use App\Models\FamilyCode;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;

class SalesCommissionRuleController extends Controller
{
    public function index(Request $request)
    {
        $q = trim((string) $request->get('q', ''));

        if (!$this->rulesTableReady()) {
            $rows = new LengthAwarePaginator(
                [],
                0,
                $this->resolvePerPage($request),
                1,
                [
                    'path' => route('sales-commission-rules.index'),
                    'query' => $request->query(),
                ]
            );

            return view('admin.sales_commission_rules.index', [
                'rows' => $rows,
                'q' => $q,
                'tableReady' => false,
            ]);
        }

        $rows = SalesCommissionRule::query()
            ->with('brand')
            ->when($q !== '', function ($query) use ($q) {
                $query->where(function ($inner) use ($q) {
                    $inner->where('family_code', 'like', "%{$q}%")
                        ->orWhereHas('brand', fn ($brand) => $brand->where('name', 'like', "%{$q}%"));
                });
            })
            ->orderBy('scope_type')
            ->orderBy('family_code')
            ->orderBy('brand_id')
            ->paginate($this->resolvePerPage())
            ->withQueryString();

        return view('admin.sales_commission_rules.index', [
            'rows' => $rows,
            'q' => $q,
            'tableReady' => true,
        ]);
    }

    public function create()
    {
        $this->ensureRulesTableReady();

        return view('admin.sales_commission_rules.form', [
            'row' => new SalesCommissionRule(['scope_type' => 'brand', 'is_active' => true, 'rate_percent' => 5]),
            'brands' => Brand::query()->orderBy('name')->get(['id', 'name']),
            'familyCodes' => FamilyCode::query()->orderBy('code')->get(['id', 'code']),
        ]);
    }

    public function store(Request $request)
    {
        $this->ensureRulesTableReady();

        $data = $this->validated($request);

        SalesCommissionRule::create($data);

        return redirect()->route('sales-commission-rules.index')->with('success', 'Sales commission rule dibuat.');
    }

    public function edit(SalesCommissionRule $salesCommissionRule)
    {
        $this->ensureRulesTableReady();

        return view('admin.sales_commission_rules.form', [
            'row' => $salesCommissionRule,
            'brands' => Brand::query()->orderBy('name')->get(['id', 'name']),
            'familyCodes' => FamilyCode::query()->orderBy('code')->get(['id', 'code']),
        ]);
    }

    public function update(Request $request, SalesCommissionRule $salesCommissionRule)
    {
        $this->ensureRulesTableReady();

        $data = $this->validated($request, $salesCommissionRule);

        $salesCommissionRule->update($data);

        return redirect()->route('sales-commission-rules.index')->with('success', 'Sales commission rule diperbarui.');
    }

    public function destroy(SalesCommissionRule $salesCommissionRule)
    {
        $this->ensureRulesTableReady();

        $salesCommissionRule->delete();

        return redirect()->route('sales-commission-rules.index')->with('success', 'Sales commission rule dihapus.');
    }

    private function validated(Request $request, ?SalesCommissionRule $rule = null): array
    {
        $scopeType = (string) $request->input('scope_type', 'brand');

        $data = $request->validate([
            'scope_type' => ['required', Rule::in(['brand', 'family'])],
            'brand_id' => [
                Rule::requiredIf($scopeType === 'brand'),
                'nullable',
                'exists:brands,id',
                Rule::unique('sales_commission_rules', 'brand_id')->ignore($rule?->id)->where(fn ($query) => $query->where('scope_type', 'brand')),
            ],
            'family_code' => [
                Rule::requiredIf($scopeType === 'family'),
                'nullable',
                'exists:family_codes,code',
                Rule::unique('sales_commission_rules', 'family_code')->ignore($rule?->id)->where(fn ($query) => $query->where('scope_type', 'family')),
            ],
            'rate_percent' => ['required', 'numeric', 'min:0'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $data['brand_id'] = $scopeType === 'brand' ? ($data['brand_id'] ?? null) : null;
        $data['family_code'] = $scopeType === 'family' ? strtoupper(trim((string) ($data['family_code'] ?? ''))) : null;
        $data['is_active'] = (bool) ($data['is_active'] ?? false);

        return $data;
    }

    private function rulesTableReady(): bool
    {
        return Schema::hasTable('sales_commission_rules');
    }

    private function ensureRulesTableReady(): void
    {
        abort_unless($this->rulesTableReady(), 503, 'Sales commission rules table belum tersedia. Jalankan migration terbaru.');
    }
}
