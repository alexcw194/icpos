<?php

namespace App\Http\Controllers;

use App\Models\BqCsvConversion;
use App\Models\Item;
use App\Models\ItemVariant;
use App\Models\Project;
use App\Models\ProjectQuotation;
use App\Services\BqCsvImportService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class ProjectBqCsvImportController extends Controller
{
    public function __construct(
        private readonly BqCsvImportService $bqCsvImportService
    ) {
    }

    public function upload(Request $request, Project $project)
    {
        $this->authorize('view', $project);
        $this->authorize('create', ProjectQuotation::class);

        $data = $request->validate([
            'file' => ['required', 'file', 'mimes:csv,txt', 'max:10240'],
        ]);

        $parsed = $this->bqCsvImportService->parseUploadedCsv($data['file']);
        $token = $this->bqCsvImportService->storeUploadPayload(
            $request,
            $project,
            (array) ($parsed['rows'] ?? []),
            (int) ($parsed['sheet_count'] ?? 0)
        );

        return response()->json([
            'token' => $token,
            'sheet_count' => (int) ($parsed['sheet_count'] ?? 0),
            'missing_mappings' => array_values((array) ($parsed['missing_mappings'] ?? [])),
        ]);
    }

    public function storeMappings(Request $request, Project $project)
    {
        $this->authorize('view', $project);
        $this->authorize('create', ProjectQuotation::class);
        if (!($request->user()?->hasAnyRole(['Admin', 'SuperAdmin']) ?? false)) {
            abort(403);
        }

        $data = $request->validate([
            'mappings' => ['required', 'array', 'min:1'],
            'mappings.*.source_category' => ['required', 'string', 'max:190'],
            'mappings.*.source_item' => ['required', 'string', 'max:255'],
            'mappings.*.mapped_item' => ['required', 'string', 'max:255'],
            'mappings.*.target_item_id' => ['required', 'integer', 'exists:items,id'],
            'mappings.*.target_item_variant_id' => ['nullable', 'integer', 'exists:item_variants,id'],
        ]);

        $itemIds = collect($data['mappings'])->pluck('target_item_id')->filter()->map(fn ($v) => (int) $v)->unique()->values()->all();
        $variantIds = collect($data['mappings'])->pluck('target_item_variant_id')->filter()->map(fn ($v) => (int) $v)->unique()->values()->all();

        $items = Item::query()
            ->whereIn('id', $itemIds)
            ->get(['id', 'list_type'])
            ->keyBy('id');
        $variants = ItemVariant::query()
            ->whereIn('id', $variantIds)
            ->get(['id', 'item_id'])
            ->keyBy('id');

        $errors = [];
        foreach ($data['mappings'] as $index => $mapping) {
            $targetItemId = (int) ($mapping['target_item_id'] ?? 0);
            $targetVariantId = (int) ($mapping['target_item_variant_id'] ?? 0);

            $item = $items->get($targetItemId);
            if (!$item) {
                $errors["mappings.$index.target_item_id"] = 'Item target tidak ditemukan.';
                continue;
            }

            if ($targetVariantId > 0) {
                $variant = $variants->get($targetVariantId);
                if (!$variant || (int) $variant->item_id !== $targetItemId) {
                    $errors["mappings.$index.target_item_variant_id"] = 'Variant tidak sesuai dengan item.';
                }
            }
        }
        if (!empty($errors)) {
            throw ValidationException::withMessages($errors);
        }

        $userId = (int) ($request->user()?->id ?? 0);
        foreach ($data['mappings'] as $mapping) {
            $sourceCategory = trim((string) $mapping['source_category']);
            $sourceItem = trim((string) $mapping['source_item']);
            $mappedItem = trim((string) $mapping['mapped_item']);
            $targetItemId = (int) $mapping['target_item_id'];
            $targetVariantId = (int) ($mapping['target_item_variant_id'] ?? 0);

            $categoryNorm = BqCsvConversion::normalizeTerm($sourceCategory);
            $itemNorm = BqCsvConversion::normalizeTerm($sourceItem);
            if ($categoryNorm === '' || $itemNorm === '') {
                continue;
            }

            $targetItem = $items->get($targetItemId);
            if (!$targetItem) {
                continue;
            }

            $targetSourceType = BqCsvConversion::sourceTypeFromItemListType((string) $targetItem->list_type);

            $row = BqCsvConversion::query()->firstOrNew([
                'source_category_norm' => $categoryNorm,
                'source_item_norm' => $itemNorm,
            ]);
            if (!$row->exists) {
                $row->created_by = $userId > 0 ? $userId : null;
            }

            $row->source_category = $sourceCategory;
            $row->source_item = $sourceItem;
            $row->mapped_item = $mappedItem;
            $row->target_source_type = $targetSourceType;
            $row->target_item_id = $targetItemId;
            $row->target_item_variant_id = $targetVariantId > 0 ? $targetVariantId : null;
            $row->is_active = true;
            $row->updated_by = $userId > 0 ? $userId : null;
            $row->save();
        }

        return response()->json(['ok' => true]);
    }

    public function prepare(Request $request, Project $project)
    {
        $this->authorize('view', $project);
        $this->authorize('create', ProjectQuotation::class);

        $data = $request->validate([
            'token' => ['required', 'string', 'max:100'],
        ]);

        $payload = $this->bqCsvImportService->loadUploadPayload($request, $project, (string) $data['token']);
        $built = $this->bqCsvImportService->buildPrefillSections((array) ($payload['rows'] ?? []));
        $missing = (array) ($built['missing_mappings'] ?? []);
        if (!empty($missing)) {
            throw ValidationException::withMessages([
                'mappings' => 'Mapping konversi belum lengkap. Lengkapi link item terlebih dahulu.',
            ]);
        }

        $sections = (array) ($built['sections'] ?? []);
        if (empty($sections)) {
            throw ValidationException::withMessages([
                'file' => 'Tidak ada data yang bisa dipakai untuk prefill New BQ.',
            ]);
        }

        $importToken = $this->bqCsvImportService->storePreparedPayload($request, $project, $sections);

        return response()->json([
            'ok' => true,
            'import_token' => $importToken,
            'redirect_url' => route('projects.quotations.create', [
                'project' => $project,
                'import_token' => $importToken,
            ]),
        ]);
    }
}

