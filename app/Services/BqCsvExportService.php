<?php

namespace App\Services;

use App\Models\BqCsvConversion;
use App\Models\Project;
use App\Models\ProjectQuotation;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class BqCsvExportService
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
                $sheetSet[$sheetNorm] = $sheet;
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
                'specification' => $specification,
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

        $sheetCount = count($sheetSet);
        $missing = $this->findMissingMappings($rows);

        return [
            'rows' => $rows,
            'sheet_count' => $sheetCount,
            'can_breakdown' => $sheetCount > 1,
            'missing_mappings' => $missing,
        ];
    }

    public function storePayload(
        Request $request,
        Project $project,
        ProjectQuotation $quotation,
        array $rows,
        int $sheetCount
    ): string {
        $token = Str::random(48);
        $dir = storage_path('app/bq_csv_exports');
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $path = $dir.DIRECTORY_SEPARATOR.$token.'.json';
        $payload = [
            'project_id' => (int) $project->id,
            'quotation_id' => (int) $quotation->id,
            'user_id' => (int) ($request->user()?->id ?? 0),
            'sheet_count' => (int) $sheetCount,
            'rows' => array_values($rows),
            'created_at' => now()->toIso8601String(),
        ];

        file_put_contents($path, json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

        $tokens = (array) $request->session()->get('bq_csv_export_tokens', []);
        $tokens = $this->pruneExpiredTokens($tokens);
        $tokens[$token] = [
            'path' => $path,
            'project_id' => (int) $project->id,
            'quotation_id' => (int) $quotation->id,
            'user_id' => (int) ($request->user()?->id ?? 0),
            'created_at' => time(),
        ];
        $request->session()->put('bq_csv_export_tokens', $tokens);

        return $token;
    }

    public function loadPayload(
        Request $request,
        Project $project,
        ProjectQuotation $quotation,
        string $token
    ): array {
        $token = trim($token);
        $tokens = (array) $request->session()->get('bq_csv_export_tokens', []);
        $tokens = $this->pruneExpiredTokens($tokens);
        $request->session()->put('bq_csv_export_tokens', $tokens);

        $meta = $tokens[$token] ?? null;
        if (!$meta) {
            throw ValidationException::withMessages([
                'token' => 'Token export CSV tidak valid atau sudah kedaluwarsa.',
            ]);
        }

        $userId = (int) ($request->user()?->id ?? 0);
        if (
            (int) ($meta['project_id'] ?? 0) !== (int) $project->id
            || (int) ($meta['quotation_id'] ?? 0) !== (int) $quotation->id
            || (int) ($meta['user_id'] ?? 0) !== $userId
        ) {
            throw ValidationException::withMessages([
                'token' => 'Token export CSV tidak cocok dengan data BQ ini.',
            ]);
        }

        $path = (string) ($meta['path'] ?? '');
        if ($path === '' || !is_file($path)) {
            throw ValidationException::withMessages([
                'token' => 'Data sementara CSV tidak ditemukan.',
            ]);
        }

        $decoded = json_decode((string) file_get_contents($path), true);
        if (!is_array($decoded) || !isset($decoded['rows']) || !is_array($decoded['rows'])) {
            throw ValidationException::withMessages([
                'token' => 'Data sementara CSV rusak.',
            ]);
        }

        return $decoded;
    }

    public function buildExportRows(array $rawRows, bool $breakdown, int $sheetCount): array
    {
        $mappingResult = $this->applyMappings($rawRows);
        if (!empty($mappingResult['missing_mappings'])) {
            return [
                'rows' => [],
                'missing_mappings' => $mappingResult['missing_mappings'],
                'can_breakdown' => $sheetCount > 1,
            ];
        }

        $rows = $mappingResult['rows'];
        $canBreakdown = $sheetCount > 1;
        if (!$canBreakdown) {
            $breakdown = false;
        }

        if ($breakdown) {
            $perSheet = $this->aggregateRows($rows, true);
            $totalRows = $this->aggregateRows($rows, false);

            return [
                'rows' => array_values(array_merge($perSheet, $totalRows)),
                'missing_mappings' => [],
                'can_breakdown' => $canBreakdown,
            ];
        }

        return [
            'rows' => $this->aggregateRows($rows, false),
            'missing_mappings' => [],
            'can_breakdown' => $canBreakdown,
        ];
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

    private function findMissingMappings(array $rows): array
    {
        $result = $this->applyMappings($rows);
        return $result['missing_mappings'];
    }

    private function applyMappings(array $rows): array
    {
        $pairMap = [];
        foreach ($rows as $row) {
            $catNorm = (string) ($row['category_norm'] ?? '');
            $itemNorm = (string) ($row['item_norm'] ?? '');
            if ($catNorm === '' || $itemNorm === '') {
                continue;
            }
            $pairMap[$catNorm.'|'.$itemNorm] = [
                'source_category' => (string) ($row['category'] ?? ''),
                'source_item' => (string) ($row['item'] ?? ''),
                'source_category_norm' => $catNorm,
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
            $rowsMap = BqCsvConversion::query()
                ->where('is_active', true)
                ->whereIn('source_category_norm', array_keys($categoryNorms))
                ->whereIn('source_item_norm', array_keys($itemNorms))
                ->get(['source_category_norm', 'source_item_norm', 'mapped_item']);

            foreach ($rowsMap as $map) {
                $key = $map->source_category_norm.'|'.$map->source_item_norm;
                $conversionMap[$key] = trim((string) $map->mapped_item);
            }
        }

        $missing = [];
        $mappedRows = [];
        foreach ($rows as $row) {
            $catNorm = (string) ($row['category_norm'] ?? '');
            $itemNorm = (string) ($row['item_norm'] ?? '');
            $key = $catNorm.'|'.$itemNorm;

            $mappedItem = $conversionMap[$key] ?? null;
            if (!$mappedItem) {
                if (!isset($missing[$key])) {
                    $missing[$key] = [
                        'source_category' => (string) ($row['category'] ?? ''),
                        'source_item' => (string) ($row['item'] ?? ''),
                    ];
                }
            }

            $row['mapped_item'] = $mappedItem ?: null;
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

    private function aggregateRows(array $rows, bool $includeSheet): array
    {
        $bucket = [];
        foreach ($rows as $row) {
            $sheet = $includeSheet
                ? (string) ($row['sheet'] ?? '-')
                : 'TOTAL ALL SHEETS';
            $category = trim((string) ($row['category'] ?? ''));
            $categoryNorm = (string) ($row['category_norm'] ?? '');
            $item = trim((string) ($row['mapped_item'] ?? ''));
            $unit = trim((string) ($row['unit'] ?? ''));
            $specification = trim((string) ($row['specification'] ?? ''));
            $ljrRaw = trim((string) ($row['ljr_raw'] ?? ''));
            $quantity = $this->isPipeCategory($categoryNorm)
                ? (float) ($row['ljr_value'] ?? 0)
                : (float) ($row['quantity_value'] ?? 0);

            $key = implode('|', [
                BqCsvConversion::normalizeTerm($sheet),
                $categoryNorm,
                BqCsvConversion::normalizeTerm($item),
                BqCsvConversion::normalizeTerm($unit),
                BqCsvConversion::normalizeTerm($specification),
                BqCsvConversion::normalizeTerm($ljrRaw),
            ]);

            if (!isset($bucket[$key])) {
                $bucket[$key] = [
                    'Sheet' => $sheet,
                    'Category' => $category,
                    'Item' => $item,
                    'Quantity' => 0.0,
                    'Unit' => $unit,
                    'Specification' => $specification,
                    'LJR' => $ljrRaw,
                    '_group_rank' => $this->resolveGroupRank($categoryNorm, $item, (string) ($row['item'] ?? '')),
                    '_sheet_sort' => $sheet,
                    '_category_sort' => $category,
                    '_item_sort' => $item,
                ];
            }

            $bucket[$key]['Quantity'] += $quantity;
        }

        $result = array_values($bucket);
        usort($result, function (array $a, array $b) {
            $groupCmp = ((int) $a['_group_rank']) <=> ((int) $b['_group_rank']);
            if ($groupCmp !== 0) {
                return $groupCmp;
            }

            $sheetCmp = strnatcasecmp((string) $a['_sheet_sort'], (string) $b['_sheet_sort']);
            if ($sheetCmp !== 0) {
                return $sheetCmp;
            }

            $catCmp = strnatcasecmp((string) $a['_category_sort'], (string) $b['_category_sort']);
            if ($catCmp !== 0) {
                return $catCmp;
            }

            return strnatcasecmp((string) $a['_item_sort'], (string) $b['_item_sort']);
        });

        return array_map(function (array $row) {
            return [
                'Sheet' => $row['Sheet'],
                'Category' => $row['Category'],
                'Item' => $row['Item'],
                'Quantity' => $this->formatNumber((float) $row['Quantity']),
                'Unit' => $row['Unit'],
                'Specification' => $row['Specification'],
                'LJR' => $row['LJR'],
            ];
        }, $result);
    }

    private function resolveGroupRank(string $categoryNorm, string $mappedItem, string $rawItem): int
    {
        $itemLookup = mb_strtolower(trim($mappedItem !== '' ? $mappedItem : $rawItem));
        if (
            str_contains($itemLookup, 'diesel')
            || str_contains($itemLookup, 'electric')
            || str_contains($itemLookup, 'jockey')
        ) {
            return 0;
        }

        if (in_array($categoryNorm, ['pipe', 'fitting', 'valve', 'valves'], true)) {
            return 1;
        }

        if ($categoryNorm === 'device') {
            return 2;
        }

        return 3;
    }

    private function isPipeCategory(string $categoryNorm): bool
    {
        return $categoryNorm === 'pipe';
    }

    private function formatNumber(float $value): string
    {
        $formatted = number_format($value, 4, '.', '');
        $formatted = rtrim(rtrim($formatted, '0'), '.');

        return $formatted !== '' ? $formatted : '0';
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
