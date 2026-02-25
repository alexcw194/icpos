<?php

namespace App\Services;

use App\Models\BqCsvConversion;
use App\Models\Item;
use App\Models\ItemLaborRate;
use App\Models\ItemVariant;
use App\Models\Project;
use App\Models\ProjectItemLaborRate;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class BqCsvImportService
{
    private const REQUIRED_HEADERS = [
        'sheet',
        'category',
        'item',
        'quantity',
        'unit',
        'specification',
        'ljr',
    ];

    private const GROUP_ORDER = [
        'Pump Room' => 0,
        'Pipeline' => 1,
        'Equipment' => 2,
        'Others' => 3,
    ];

    public function parseUploadedCsv(UploadedFile $file): array
    {
        $handle = fopen($file->getRealPath(), 'rb');
        if ($handle === false) {
            throw ValidationException::withMessages([
                'file' => 'File CSV tidak bisa dibaca.',
            ]);
        }

        $header = fgetcsv($handle);
        if (!is_array($header) || count($header) === 0) {
            fclose($handle);
            throw ValidationException::withMessages([
                'file' => 'Header CSV tidak ditemukan.',
            ]);
        }

        $headerMap = $this->resolveHeaderMap($header);
        $rows = [];
        $sheetSet = [];

        while (($cols = fgetcsv($handle)) !== false) {
            if ($this->rowIsBlank($cols)) {
                continue;
            }

            $sheet = trim((string) ($cols[$headerMap['sheet']] ?? ''));
            if (BqCsvConversion::normalizeTerm($sheet) === 'total all sheets') {
                continue;
            }

            $category = trim((string) ($cols[$headerMap['category']] ?? ''));
            $item = trim((string) ($cols[$headerMap['item']] ?? ''));
            $quantityRaw = trim((string) ($cols[$headerMap['quantity']] ?? ''));
            $unit = trim((string) ($cols[$headerMap['unit']] ?? ''));
            $specification = trim((string) ($cols[$headerMap['specification']] ?? ''));
            $ljrRaw = trim((string) ($cols[$headerMap['ljr']] ?? ''));

            if ($sheet === '' && $category === '' && $item === '') {
                continue;
            }

            $sheetNorm = BqCsvConversion::normalizeTerm($sheet);
            if ($sheetNorm !== '') {
                $sheetSet[$sheetNorm] = true;
            }

            $rows[] = [
                'sheet' => $sheet !== '' ? $sheet : '-',
                'sheet_norm' => $sheetNorm,
                'category' => $category,
                'category_norm' => BqCsvConversion::normalizeTerm($category),
                'item' => $item,
                'item_norm' => BqCsvConversion::normalizeTerm($item),
                'quantity_raw' => $quantityRaw,
                'quantity_value' => $this->toFloat($quantityRaw),
                'unit' => $unit,
                'unit_norm' => BqCsvConversion::normalizeTerm($unit),
                'specification' => $specification,
                'specification_norm' => BqCsvConversion::normalizeTerm($specification),
                'ljr_raw' => $ljrRaw,
                'ljr_value' => $this->toFloat($ljrRaw),
            ];
        }

        fclose($handle);

        if (count($rows) === 0) {
            throw ValidationException::withMessages([
                'file' => 'CSV tidak memiliki baris data yang valid.',
            ]);
        }

        $mappingResult = $this->applyConversions($rows);

        return [
            'rows' => $rows,
            'sheet_count' => count($sheetSet),
            'missing_mappings' => $mappingResult['missing_mappings'],
        ];
    }

    public function storeUploadPayload(
        Request $request,
        Project $project,
        array $rows,
        int $sheetCount
    ): string {
        $token = Str::random(48);
        $dir = storage_path('app/bq_csv_imports/upload');
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $path = $dir.DIRECTORY_SEPARATOR.$token.'.json';
        $payload = [
            'project_id' => (int) $project->id,
            'user_id' => (int) ($request->user()?->id ?? 0),
            'sheet_count' => (int) $sheetCount,
            'rows' => array_values($rows),
            'created_at' => now()->toIso8601String(),
        ];
        file_put_contents($path, json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

        $sessionKey = 'bq_csv_import_upload_tokens';
        $tokens = (array) $request->session()->get($sessionKey, []);
        $tokens = $this->pruneExpiredTokens($tokens);
        $tokens[$token] = [
            'path' => $path,
            'project_id' => (int) $project->id,
            'user_id' => (int) ($request->user()?->id ?? 0),
            'created_at' => time(),
        ];
        $request->session()->put($sessionKey, $tokens);

        return $token;
    }

    public function loadUploadPayload(Request $request, Project $project, string $token): array
    {
        return $this->loadPayloadByToken(
            $request,
            $project,
            trim($token),
            'bq_csv_import_upload_tokens',
            'Token upload CSV tidak valid atau sudah kedaluwarsa.'
        );
    }

    public function storePreparedPayload(Request $request, Project $project, array $sections): string
    {
        $token = Str::random(48);
        $dir = storage_path('app/bq_csv_imports/prefill');
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $path = $dir.DIRECTORY_SEPARATOR.$token.'.json';
        $payload = [
            'project_id' => (int) $project->id,
            'user_id' => (int) ($request->user()?->id ?? 0),
            'sections' => array_values($sections),
            'created_at' => now()->toIso8601String(),
        ];
        file_put_contents($path, json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

        $sessionKey = 'bq_csv_import_prefill_tokens';
        $tokens = (array) $request->session()->get($sessionKey, []);
        $tokens = $this->pruneExpiredTokens($tokens);
        $tokens[$token] = [
            'path' => $path,
            'project_id' => (int) $project->id,
            'user_id' => (int) ($request->user()?->id ?? 0),
            'created_at' => time(),
        ];
        $request->session()->put($sessionKey, $tokens);

        return $token;
    }

    public function loadPreparedPayload(Request $request, Project $project, string $token): array
    {
        return $this->loadPayloadByToken(
            $request,
            $project,
            trim($token),
            'bq_csv_import_prefill_tokens',
            'Token import CSV tidak valid atau sudah kedaluwarsa.'
        );
    }

    public function buildPrefillSections(array $rawRows): array
    {
        $mappingResult = $this->applyConversions($rawRows);
        if (!empty($mappingResult['missing_mappings'])) {
            return [
                'sections' => [],
                'missing_mappings' => $mappingResult['missing_mappings'],
            ];
        }

        $aggregated = $this->aggregateRowsForPrefill($mappingResult['rows']);
        if (empty($aggregated)) {
            throw ValidationException::withMessages([
                'file' => 'Tidak ada baris yang bisa dipakai untuk prefill BQ.',
            ]);
        }

        $lines = $this->enrichAggregatesWithPriceAndLabor($aggregated);
        $sections = $this->buildSectionsFromLines($lines);

        if (empty($sections)) {
            throw ValidationException::withMessages([
                'file' => 'Tidak ada section yang berhasil dibentuk dari file CSV.',
            ]);
        }

        return [
            'sections' => $sections,
            'missing_mappings' => [],
        ];
    }

    private function loadPayloadByToken(
        Request $request,
        Project $project,
        string $token,
        string $sessionKey,
        string $invalidMessage
    ): array {
        $tokens = (array) $request->session()->get($sessionKey, []);
        $tokens = $this->pruneExpiredTokens($tokens);
        $request->session()->put($sessionKey, $tokens);

        $meta = $tokens[$token] ?? null;
        if (!$meta) {
            throw ValidationException::withMessages([
                'token' => $invalidMessage,
            ]);
        }

        $userId = (int) ($request->user()?->id ?? 0);
        if (
            (int) ($meta['project_id'] ?? 0) !== (int) $project->id
            || (int) ($meta['user_id'] ?? 0) !== $userId
        ) {
            throw ValidationException::withMessages([
                'token' => 'Token tidak cocok dengan project ini.',
            ]);
        }

        $path = (string) ($meta['path'] ?? '');
        if ($path === '' || !is_file($path)) {
            throw ValidationException::withMessages([
                'token' => 'Data sementara import CSV tidak ditemukan.',
            ]);
        }

        $decoded = json_decode((string) file_get_contents($path), true);
        if (!is_array($decoded)) {
            throw ValidationException::withMessages([
                'token' => 'Data sementara import CSV rusak.',
            ]);
        }

        return $decoded;
    }

    private function resolveHeaderMap(array $header): array
    {
        $normalizedToIndex = [];
        foreach ($header as $index => $name) {
            $norm = $this->normalizeHeader((string) $name);
            if ($norm !== '' && !isset($normalizedToIndex[$norm])) {
                $normalizedToIndex[$norm] = (int) $index;
            }
        }

        $missing = [];
        foreach (self::REQUIRED_HEADERS as $required) {
            if (!array_key_exists($required, $normalizedToIndex)) {
                $missing[] = $required;
            }
        }

        if ($missing) {
            throw ValidationException::withMessages([
                'file' => 'Header CSV wajib: Sheet, Category, Item, Quantity, Unit, Specification, LJR.',
            ]);
        }

        return $normalizedToIndex;
    }

    private function normalizeHeader(string $value): string
    {
        $value = trim($value);
        $value = preg_replace('/^\x{FEFF}/u', '', $value) ?? $value;
        if ($value === '') {
            return '';
        }

        $value = preg_replace('/\s+/', '', $value) ?? '';
        return mb_strtolower($value);
    }

    private function rowIsBlank(array $cols): bool
    {
        foreach ($cols as $col) {
            if (trim((string) $col) !== '') {
                return false;
            }
        }

        return true;
    }

    private function toFloat($value): float
    {
        if ($value === null) {
            return 0.0;
        }

        $value = trim((string) $value);
        if ($value === '') {
            return 0.0;
        }

        $value = preg_replace('/\s+/', '', $value) ?? '';
        if (str_contains($value, ',') && str_contains($value, '.')) {
            $value = str_replace('.', '', $value);
            $value = str_replace(',', '.', $value);
        } elseif (str_contains($value, ',')) {
            $value = str_replace(',', '.', $value);
        }

        $value = preg_replace('/[^0-9.\-]/', '', $value) ?? '0';
        $number = (float) $value;
        if (!is_finite($number)) {
            return 0.0;
        }

        return $number;
    }

    private function applyConversions(array $rows): array
    {
        $pairMap = [];
        foreach ($rows as $row) {
            $categoryNorm = (string) ($row['category_norm'] ?? '');
            $itemNorm = (string) ($row['item_norm'] ?? '');
            if ($categoryNorm === '' || $itemNorm === '') {
                continue;
            }

            $pairMap[$categoryNorm.'|'.$itemNorm] = [
                'source_category' => (string) ($row['category'] ?? ''),
                'source_item' => (string) ($row['item'] ?? ''),
                'source_category_norm' => $categoryNorm,
                'source_item_norm' => $itemNorm,
            ];
        }

        $categoryNorms = [];
        $itemNorms = [];
        foreach ($pairMap as $pair) {
            $categoryNorms[$pair['source_category_norm']] = true;
            $itemNorms[$pair['source_item_norm']] = true;
        }

        $conversionMap = [];
        if (!empty($pairMap)) {
            $conversionRows = BqCsvConversion::query()
                ->where('is_active', true)
                ->whereIn('source_category_norm', array_keys($categoryNorms))
                ->whereIn('source_item_norm', array_keys($itemNorms))
                ->get([
                    'source_category_norm',
                    'source_item_norm',
                    'mapped_item',
                    'target_source_type',
                    'target_item_id',
                    'target_item_variant_id',
                ]);

            foreach ($conversionRows as $row) {
                $key = $row->source_category_norm.'|'.$row->source_item_norm;
                $conversionMap[$key] = [
                    'mapped_item' => trim((string) $row->mapped_item),
                    'target_source_type' => (string) ($row->target_source_type ?? ''),
                    'target_item_id' => $row->target_item_id ? (int) $row->target_item_id : null,
                    'target_item_variant_id' => $row->target_item_variant_id ? (int) $row->target_item_variant_id : null,
                ];
            }
        }

        $targetItemIds = [];
        $targetVariantIds = [];
        foreach ($conversionMap as $conversion) {
            $itemId = (int) ($conversion['target_item_id'] ?? 0);
            $variantId = (int) ($conversion['target_item_variant_id'] ?? 0);
            if ($itemId > 0) {
                $targetItemIds[$itemId] = true;
            }
            if ($variantId > 0) {
                $targetVariantIds[$variantId] = true;
            }
        }

        $items = Item::query()
            ->whereIn('id', array_keys($targetItemIds))
            ->get(['id', 'name', 'list_type', 'price'])
            ->keyBy('id');
        $variants = ItemVariant::query()
            ->whereIn('id', array_keys($targetVariantIds))
            ->get(['id', 'item_id', 'price', 'attributes', 'sku'])
            ->keyBy('id');

        $missing = [];
        $mappedRows = [];
        foreach ($rows as $row) {
            $categoryNorm = (string) ($row['category_norm'] ?? '');
            $itemNorm = (string) ($row['item_norm'] ?? '');
            $key = $categoryNorm.'|'.$itemNorm;
            $conversion = $conversionMap[$key] ?? null;

            $mappedItem = trim((string) ($conversion['mapped_item'] ?? ''));
            $targetSourceType = (string) ($conversion['target_source_type'] ?? '');
            $targetItemId = (int) ($conversion['target_item_id'] ?? 0);
            $targetVariantId = (int) ($conversion['target_item_variant_id'] ?? 0);
            $targetItem = $targetItemId > 0 ? $items->get($targetItemId) : null;
            $targetVariant = $targetVariantId > 0 ? $variants->get($targetVariantId) : null;

            if ($targetVariant && (int) $targetVariant->item_id !== $targetItemId) {
                $targetVariant = null;
                $targetVariantId = 0;
            }

            $isComplete = $mappedItem !== ''
                && $targetItemId > 0
                && in_array($targetSourceType, ['item', 'project'], true)
                && $targetItem !== null;

            if (!$isComplete) {
                if (!isset($missing[$key])) {
                    $missing[$key] = [
                        'source_category' => (string) ($row['category'] ?? ''),
                        'source_item' => (string) ($row['item'] ?? ''),
                        'mapped_item' => $mappedItem,
                        'target_source_type' => $targetSourceType !== '' ? $targetSourceType : 'item',
                        'target_item_id' => $targetItemId > 0 ? $targetItemId : null,
                        'target_item_variant_id' => $targetVariantId > 0 ? $targetVariantId : null,
                        'target_item_label' => $this->formatTargetItemLabel($targetItem, $targetVariant),
                    ];
                }
            }

            $row['mapped_item'] = $mappedItem !== '' ? $mappedItem : null;
            $row['target_source_type'] = $targetSourceType !== '' ? $targetSourceType : null;
            $row['target_item_id'] = $targetItemId > 0 ? $targetItemId : null;
            $row['target_item_variant_id'] = $targetVariantId > 0 ? $targetVariantId : null;
            $mappedRows[] = $row;
        }

        usort($missing, function (array $a, array $b) {
            $cmpCategory = strcasecmp((string) ($a['source_category'] ?? ''), (string) ($b['source_category'] ?? ''));
            if ($cmpCategory !== 0) {
                return $cmpCategory;
            }

            return strcasecmp((string) ($a['source_item'] ?? ''), (string) ($b['source_item'] ?? ''));
        });

        return [
            'rows' => $mappedRows,
            'missing_mappings' => array_values($missing),
        ];
    }

    private function aggregateRowsForPrefill(array $rows): array
    {
        $bucket = [];
        foreach ($rows as $row) {
            $mappedItem = trim((string) ($row['mapped_item'] ?? ''));
            $targetSourceType = (string) ($row['target_source_type'] ?? '');
            $targetItemId = (int) ($row['target_item_id'] ?? 0);
            if ($mappedItem === '' || $targetItemId <= 0 || !in_array($targetSourceType, ['item', 'project'], true)) {
                continue;
            }

            $categoryNorm = (string) ($row['category_norm'] ?? '');
            $quantity = $this->isPipeCategory($categoryNorm)
                ? (float) ($row['ljr_value'] ?? 0)
                : (float) ($row['quantity_value'] ?? 0);
            if (!is_finite($quantity)) {
                $quantity = 0.0;
            }

            $targetVariantId = (int) ($row['target_item_variant_id'] ?? 0);
            $group = $this->resolveGroupName(
                $categoryNorm,
                $mappedItem,
                (string) ($row['item'] ?? '')
            );

            $key = implode('|', [
                BqCsvConversion::normalizeTerm($group),
                $categoryNorm,
                $targetSourceType,
                $targetItemId,
                $targetVariantId > 0 ? $targetVariantId : 0,
                BqCsvConversion::normalizeTerm((string) ($row['unit'] ?? '')),
                BqCsvConversion::normalizeTerm((string) ($row['specification'] ?? '')),
            ]);

            if (!isset($bucket[$key])) {
                $bucket[$key] = [
                    'group' => $group,
                    'group_order' => self::GROUP_ORDER[$group] ?? 99,
                    'category' => (string) ($row['category'] ?? ''),
                    'mapped_item' => $mappedItem,
                    'source_type' => $targetSourceType,
                    'item_id' => $targetItemId,
                    'item_variant_id' => $targetVariantId > 0 ? $targetVariantId : null,
                    'unit' => (string) ($row['unit'] ?? ''),
                    'specification' => (string) ($row['specification'] ?? ''),
                    'qty' => 0.0,
                ];
            }

            $bucket[$key]['qty'] += $quantity;
        }

        $result = array_values($bucket);
        usort($result, function (array $a, array $b) {
            $groupCmp = ((int) $a['group_order']) <=> ((int) $b['group_order']);
            if ($groupCmp !== 0) {
                return $groupCmp;
            }

            $categoryCmp = strnatcasecmp((string) $a['category'], (string) $b['category']);
            if ($categoryCmp !== 0) {
                return $categoryCmp;
            }

            return strnatcasecmp((string) $a['mapped_item'], (string) $b['mapped_item']);
        });

        return array_values(array_filter($result, function (array $row) {
            return abs((float) ($row['qty'] ?? 0)) > 0;
        }));
    }

    private function enrichAggregatesWithPriceAndLabor(array $aggregates): array
    {
        $itemIds = [];
        $variantIds = [];
        foreach ($aggregates as $row) {
            $itemId = (int) ($row['item_id'] ?? 0);
            $variantId = (int) ($row['item_variant_id'] ?? 0);
            if ($itemId > 0) {
                $itemIds[$itemId] = true;
            }
            if ($variantId > 0) {
                $variantIds[$variantId] = true;
            }
        }

        $items = Item::query()
            ->whereIn('id', array_keys($itemIds))
            ->get(['id', 'name', 'list_type', 'price'])
            ->keyBy('id');
        $variants = ItemVariant::query()
            ->whereIn('id', array_keys($variantIds))
            ->get(['id', 'item_id', 'price', 'attributes', 'sku'])
            ->keyBy('id');

        $itemLaborRates = $this->loadItemLaborRates(array_keys($itemIds));
        $projectLaborRates = $this->loadProjectLaborRates(array_keys($itemIds));

        $lines = [];
        foreach ($aggregates as $row) {
            $itemId = (int) ($row['item_id'] ?? 0);
            $variantId = (int) ($row['item_variant_id'] ?? 0);
            $sourceType = (string) ($row['source_type'] ?? 'item');
            $qty = (float) ($row['qty'] ?? 0);
            $unit = trim((string) ($row['unit'] ?? ''));
            $specification = trim((string) ($row['specification'] ?? ''));

            $item = $items->get($itemId);
            if (!$item) {
                continue;
            }

            $variant = $variantId > 0 ? $variants->get($variantId) : null;
            if ($variant && (int) $variant->item_id !== $itemId) {
                $variant = null;
                $variantId = 0;
            }

            $unitPrice = $variant && $variant->price !== null
                ? (float) $variant->price
                : (float) ($item->price ?? 0);
            $unitPrice = is_finite($unitPrice) ? $unitPrice : 0.0;

            [$laborUnitCost, $laborSource] = $this->resolveLaborRate(
                $sourceType,
                $itemId,
                $variantId,
                $itemLaborRates,
                $projectLaborRates
            );

            $materialTotal = $qty * $unitPrice;
            $laborTotal = $qty * $laborUnitCost;
            $description = trim((string) ($row['mapped_item'] ?? ''));
            if ($specification !== '') {
                $description .= ' - '.$specification;
            }

            $lines[] = [
                'group' => (string) ($row['group'] ?? 'Others'),
                'line_type' => 'product',
                'line_no' => null,
                'description' => $description,
                'source_type' => $sourceType,
                'item_id' => $itemId,
                'item_variant_id' => $variantId > 0 ? $variantId : null,
                'item_label' => $this->formatTargetItemLabel($item, $variant),
                'catalog_id' => null,
                'percent_value' => null,
                'percent_basis' => null,
                'computed_amount' => null,
                'cost_bucket' => 'material',
                'qty' => $qty,
                'unit' => $unit !== '' ? $unit : 'PCS',
                'unit_price' => $unitPrice,
                'material_total' => $materialTotal,
                'labor_total' => $laborTotal,
                'labor_source' => $laborSource,
                'labor_unit_cost_snapshot' => $laborUnitCost,
                'labor_cost_amount' => null,
                'labor_margin_amount' => null,
                'labor_cost_missing' => false,
            ];
        }

        return $lines;
    }

    private function buildSectionsFromLines(array $lines): array
    {
        $sectionMap = [
            'Pump Room' => [],
            'Pipeline' => [],
            'Equipment' => [],
        ];

        foreach ($lines as $line) {
            $group = (string) ($line['group'] ?? '');
            if (!array_key_exists($group, $sectionMap)) {
                continue;
            }
            $sectionMap[$group][] = $line;
        }

        $sections = [];
        $sectionOrder = 1;
        foreach ($sectionMap as $sectionName => $sectionLines) {
            if (empty($sectionLines)) {
                continue;
            }

            foreach ($sectionLines as $index => &$line) {
                $line['line_no'] = (string) ($index + 1);
                unset($line['group']);
            }
            unset($line);

            $sections[] = [
                'name' => $sectionName,
                'sort_order' => $sectionOrder++,
                'lines' => array_values($sectionLines),
            ];
        }

        return $sections;
    }

    /**
     * @return array{0: float, 1: string}
     */
    private function resolveLaborRate(
        string $sourceType,
        int $itemId,
        int $variantId,
        array $itemLaborRates,
        array $projectLaborRates
    ): array {
        $rate = null;
        if ($sourceType === 'project') {
            $rate = $projectLaborRates[$itemId.'|'.$variantId] ?? null;
            if ($rate === null && $variantId > 0) {
                $rate = $projectLaborRates[$itemId.'|0'] ?? null;
            }
            if ($rate !== null) {
                return [(float) $rate, 'master_project'];
            }

            return [0.0, 'manual'];
        }

        $rate = $itemLaborRates[$itemId.'|'.$variantId] ?? null;
        if ($rate === null && $variantId > 0) {
            $rate = $itemLaborRates[$itemId.'|0'] ?? null;
        }
        if ($rate !== null) {
            return [(float) $rate, 'master_item'];
        }

        return [0.0, 'manual'];
    }

    private function loadItemLaborRates(array $itemIds): array
    {
        if (
            empty($itemIds)
            || !Schema::hasTable('item_labor_rates')
            || !Schema::hasColumn('item_labor_rates', 'item_id')
            || !Schema::hasColumn('item_labor_rates', 'labor_unit_cost')
        ) {
            return [];
        }

        $hasVariantColumn = Schema::hasColumn('item_labor_rates', 'item_variant_id');
        $select = ['item_id', 'labor_unit_cost'];
        if ($hasVariantColumn) {
            $select[] = 'item_variant_id';
        }

        $rows = ItemLaborRate::query()
            ->whereIn('item_id', $itemIds)
            ->get($select);

        $map = [];
        foreach ($rows as $row) {
            $variantId = $hasVariantColumn ? (int) ($row->item_variant_id ?? 0) : 0;
            $map[((int) $row->item_id).'|'.$variantId] = (float) $row->labor_unit_cost;
        }

        return $map;
    }

    private function loadProjectLaborRates(array $itemIds): array
    {
        if (
            empty($itemIds)
            || !Schema::hasTable('project_item_labor_rates')
            || !Schema::hasColumn('project_item_labor_rates', 'project_item_id')
            || !Schema::hasColumn('project_item_labor_rates', 'labor_unit_cost')
        ) {
            return [];
        }

        $hasVariantColumn = Schema::hasColumn('project_item_labor_rates', 'item_variant_id');
        $select = ['project_item_id', 'labor_unit_cost'];
        if ($hasVariantColumn) {
            $select[] = 'item_variant_id';
        }

        $rows = ProjectItemLaborRate::query()
            ->whereIn('project_item_id', $itemIds)
            ->get($select);

        $map = [];
        foreach ($rows as $row) {
            $variantId = $hasVariantColumn ? (int) ($row->item_variant_id ?? 0) : 0;
            $map[((int) $row->project_item_id).'|'.$variantId] = (float) $row->labor_unit_cost;
        }

        return $map;
    }

    private function formatTargetItemLabel(?Item $item, ?ItemVariant $variant): ?string
    {
        if (!$item) {
            return null;
        }

        if ($variant) {
            $attrs = is_array($variant->attributes) ? $variant->attributes : [];
            $label = trim((string) $item->renderVariantLabel($attrs));
            if ($label !== '') {
                return $label;
            }
        }

        return trim((string) $item->name);
    }

    private function resolveGroupName(string $categoryNorm, string $mappedItem, string $rawItem): string
    {
        $itemLookup = mb_strtolower(trim($mappedItem !== '' ? $mappedItem : $rawItem));
        if (
            str_contains($itemLookup, 'diesel')
            || str_contains($itemLookup, 'electric')
            || str_contains($itemLookup, 'jockey')
        ) {
            return 'Pump Room';
        }

        if (in_array($categoryNorm, ['pipe', 'fitting', 'valve', 'valves'], true)) {
            return 'Pipeline';
        }

        if ($categoryNorm === 'device') {
            return 'Equipment';
        }

        return 'Others';
    }

    private function isPipeCategory(string $categoryNorm): bool
    {
        return $categoryNorm === 'pipe';
    }

    private function pruneExpiredTokens(array $tokens): array
    {
        $now = time();
        $ttl = 2 * 60 * 60;
        $pruned = [];

        foreach ($tokens as $token => $meta) {
            $createdAt = (int) ($meta['created_at'] ?? 0);
            $path = (string) ($meta['path'] ?? '');
            if ($createdAt <= 0 || ($now - $createdAt) > $ttl) {
                if ($path !== '' && is_file($path)) {
                    @unlink($path);
                }
                continue;
            }
            $pruned[$token] = $meta;
        }

        return $pruned;
    }
}
