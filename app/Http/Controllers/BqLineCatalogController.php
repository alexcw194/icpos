<?php

namespace App\Http\Controllers;

use App\Models\BqLineCatalog;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class BqLineCatalogController extends Controller
{
    public function index(Request $request)
    {
        $q = $request->string('q')->toString();
        $status = $request->string('status')->toString();

        $rows = BqLineCatalog::query()
            ->when($q !== '', function ($qq) use ($q) {
                $qq->where(function ($w) use ($q) {
                    $w->where('name', 'like', "%{$q}%")
                      ->orWhere('description', 'like', "%{$q}%");
                });
            })
            ->when($status === 'active', fn ($qq) => $qq->where('is_active', true))
            ->when($status === 'inactive', fn ($qq) => $qq->where('is_active', false))
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString();

        return view('admin.bq_line_catalogs.index', compact('rows', 'q', 'status'));
    }

    public function create()
    {
        $row = new BqLineCatalog([
            'type' => 'charge',
            'default_qty' => 1,
            'default_unit' => 'LS',
            'percent_basis' => 'product_subtotal',
            'cost_bucket' => 'material',
            'is_active' => true,
        ]);

        return view('admin.bq_line_catalogs.form', compact('row'));
    }

    public function store(Request $request)
    {
        $data = $this->validateCatalog($request);
        BqLineCatalog::create($data);

        return redirect()->route('bq-line-catalogs.index')
            ->with('success', 'Catalog entry created.');
    }

    public function show(BqLineCatalog $bqLineCatalog)
    {
        return redirect()->route('bq-line-catalogs.edit', $bqLineCatalog);
    }

    public function edit(BqLineCatalog $bqLineCatalog)
    {
        $row = $bqLineCatalog;
        return view('admin.bq_line_catalogs.form', compact('row'));
    }

    public function update(Request $request, BqLineCatalog $bqLineCatalog)
    {
        $data = $this->validateCatalog($request);
        $bqLineCatalog->update($data);

        return redirect()->route('bq-line-catalogs.index')
            ->with('ok', 'Catalog entry updated.');
    }

    public function destroy(BqLineCatalog $bqLineCatalog)
    {
        $bqLineCatalog->delete();

        return redirect()->route('bq-line-catalogs.index')
            ->with('ok', 'Catalog entry deleted.');
    }

    public function search(Request $request)
    {
        $q = $request->string('q')->toString();
        $type = $request->string('type')->toString();
        $typeFilter = in_array($type, ['charge', 'percent'], true) ? $type : null;

        $rows = BqLineCatalog::query()
            ->where('is_active', true)
            ->when($typeFilter, fn ($qq) => $qq->where('type', $typeFilter))
            ->when($q !== '', function ($qq) use ($q) {
                $qq->where(function ($w) use ($q) {
                    $w->where('name', 'like', "%{$q}%")
                      ->orWhere('description', 'like', "%{$q}%");
                });
            })
            ->orderBy('name')
            ->limit(200)
            ->get()
            ->map(function (BqLineCatalog $row) {
                return [
                    'id' => $row->id,
                    'name' => $row->name,
                    'type' => $row->type,
                    'default_qty' => $row->default_qty !== null ? (float) $row->default_qty : null,
                    'default_unit' => $row->default_unit,
                    'default_unit_price' => $row->default_unit_price !== null ? (float) $row->default_unit_price : null,
                    'default_percent' => $row->default_percent !== null ? (float) $row->default_percent : null,
                    'percent_basis' => $row->percent_basis,
                    'cost_bucket' => $row->cost_bucket,
                    'description' => $row->description,
                ];
            })
            ->values();

        return response()->json($rows);
    }

    private function validateCatalog(Request $request): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:190'],
            'type' => ['required', Rule::in(['charge', 'percent'])],
            'default_qty' => ['nullable', 'numeric', 'min:0'],
            'default_unit' => ['nullable', 'string', 'max:20'],
            'default_unit_price' => ['nullable', 'numeric', 'min:0'],
            'default_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'percent_basis' => ['nullable', Rule::in(['product_subtotal', 'section_product_subtotal', 'material_subtotal', 'section_material_subtotal'])],
            'cost_bucket' => ['nullable', Rule::in(['material', 'labor'])],
            'is_active' => ['nullable', 'boolean'],
            'description' => ['nullable', 'string'],
        ]);

        $data['is_active'] = $request->boolean('is_active');
        $data['percent_basis'] = $data['percent_basis'] ?? 'product_subtotal';
        $data['cost_bucket'] = $data['cost_bucket'] ?? 'material';

        $type = $data['type'] ?? 'charge';
        if ($type === 'charge') {
            $data['default_percent'] = null;
        } else {
            $data['default_qty'] = null;
            $data['default_unit'] = null;
            $data['default_unit_price'] = null;
            if (!isset($data['default_percent'])) {
                $data['default_percent'] = 0;
            }
        }

        return $data;
    }
}
