<?php

namespace App\Http\Controllers;

use App\Models\BqCsvConversion;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class BqCsvConversionController extends Controller
{
    public function index(Request $request)
    {
        $q = $request->string('q')->toString();
        $status = $request->string('status')->toString();

        $rows = BqCsvConversion::query()
            ->when($q !== '', function ($query) use ($q) {
                $query->where(function ($sub) use ($q) {
                    $sub->where('source_category', 'like', "%{$q}%")
                        ->orWhere('source_item', 'like', "%{$q}%")
                        ->orWhere('mapped_item', 'like', "%{$q}%");
                });
            })
            ->when($status === 'active', fn ($query) => $query->where('is_active', true))
            ->when($status === 'inactive', fn ($query) => $query->where('is_active', false))
            ->orderBy('source_category')
            ->orderBy('source_item')
            ->paginate(25)
            ->withQueryString();

        return view('admin.bq_csv_conversions.index', compact('rows', 'q', 'status'));
    }

    public function create()
    {
        $row = new BqCsvConversion(['is_active' => true]);
        return view('admin.bq_csv_conversions.form', compact('row'));
    }

    public function store(Request $request)
    {
        $data = $this->validateInput($request, null);
        $userId = (int) ($request->user()?->id ?? 0);

        BqCsvConversion::create(array_merge($data, [
            'created_by' => $userId > 0 ? $userId : null,
            'updated_by' => $userId > 0 ? $userId : null,
        ]));

        return redirect()->route('bq-csv-conversions.index')
            ->with('success', 'BQ CSV conversion created.');
    }

    public function edit(BqCsvConversion $bqCsvConversion)
    {
        $row = $bqCsvConversion;
        return view('admin.bq_csv_conversions.form', compact('row'));
    }

    public function update(Request $request, BqCsvConversion $bqCsvConversion)
    {
        $data = $this->validateInput($request, $bqCsvConversion->id);
        $userId = (int) ($request->user()?->id ?? 0);

        $bqCsvConversion->update(array_merge($data, [
            'updated_by' => $userId > 0 ? $userId : null,
        ]));

        return redirect()->route('bq-csv-conversions.index')
            ->with('ok', 'BQ CSV conversion updated.');
    }

    public function destroy(BqCsvConversion $bqCsvConversion)
    {
        $bqCsvConversion->delete();

        return redirect()->route('bq-csv-conversions.index')
            ->with('ok', 'BQ CSV conversion deleted.');
    }

    private function validateInput(Request $request, ?int $ignoreId): array
    {
        $data = $request->validate([
            'source_category' => ['required', 'string', 'max:190'],
            'source_item' => ['required', 'string', 'max:255'],
            'mapped_item' => ['required', 'string', 'max:255'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $sourceCategory = trim((string) $data['source_category']);
        $sourceItem = trim((string) $data['source_item']);
        $categoryNorm = BqCsvConversion::normalizeTerm($sourceCategory);
        $itemNorm = BqCsvConversion::normalizeTerm($sourceItem);

        if ($categoryNorm === '' || $itemNorm === '') {
            throw ValidationException::withMessages([
                'source_category' => 'Source category dan source item wajib diisi.',
            ]);
        }

        $duplicateQuery = BqCsvConversion::query()
            ->where('source_category_norm', $categoryNorm)
            ->where('source_item_norm', $itemNorm);
        if ($ignoreId) {
            $duplicateQuery->where('id', '!=', $ignoreId);
        }

        if ($duplicateQuery->exists()) {
            throw ValidationException::withMessages([
                'source_item' => 'Kombinasi source category + source item sudah ada.',
            ]);
        }

        return [
            'source_category' => $sourceCategory,
            'source_item' => $sourceItem,
            'mapped_item' => trim((string) $data['mapped_item']),
            'is_active' => $request->boolean('is_active'),
        ];
    }
}
