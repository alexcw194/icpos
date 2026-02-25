<?php

namespace App\Http\Controllers;

use App\Models\BqCsvConversion;
use App\Models\Project;
use App\Models\ProjectQuotation;
use App\Services\BqCsvExportService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ProjectQuotationBqCsvController extends Controller
{
    public function __construct(
        private readonly BqCsvExportService $bqCsvExportService
    ) {
    }

    public function upload(Request $request, Project $project, ProjectQuotation $quotation)
    {
        $this->authorize('view', $quotation);
        $this->ensureProjectMatch($project, $quotation);

        $data = $request->validate([
            'file' => ['required', 'file', 'mimes:csv,txt', 'max:10240'],
        ]);

        $parsed = $this->bqCsvExportService->parseUploadedCsv($data['file']);
        $token = $this->bqCsvExportService->storePayload(
            $request,
            $project,
            $quotation,
            $parsed['rows'],
            (int) $parsed['sheet_count']
        );

        return response()->json([
            'token' => $token,
            'sheet_count' => (int) $parsed['sheet_count'],
            'can_breakdown' => (bool) $parsed['can_breakdown'],
            'missing_mappings' => $parsed['missing_mappings'],
        ]);
    }

    public function storeMappings(Request $request, Project $project, ProjectQuotation $quotation)
    {
        $this->authorize('manageBqCsvMappings', $quotation);
        $this->ensureProjectMatch($project, $quotation);

        $data = $request->validate([
            'mappings' => ['required', 'array', 'min:1'],
            'mappings.*.source_category' => ['required', 'string', 'max:190'],
            'mappings.*.source_item' => ['required', 'string', 'max:255'],
            'mappings.*.mapped_item' => ['required', 'string', 'max:255'],
        ]);

        $userId = (int) ($request->user()?->id ?? 0);
        foreach ($data['mappings'] as $mapping) {
            $sourceCategory = trim((string) $mapping['source_category']);
            $sourceItem = trim((string) $mapping['source_item']);
            $mappedItem = trim((string) $mapping['mapped_item']);
            $categoryNorm = BqCsvConversion::normalizeTerm($sourceCategory);
            $itemNorm = BqCsvConversion::normalizeTerm($sourceItem);

            if ($categoryNorm === '' || $itemNorm === '') {
                continue;
            }

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
            $row->is_active = true;
            $row->updated_by = $userId > 0 ? $userId : null;
            $row->save();
        }

        return response()->json(['ok' => true]);
    }

    public function export(Request $request, Project $project, ProjectQuotation $quotation): StreamedResponse
    {
        $this->authorize('view', $quotation);
        $this->ensureProjectMatch($project, $quotation);

        $validated = $request->validate([
            'token' => ['required', 'string', 'max:100'],
            'breakdown' => ['nullable', 'in:0,1,true,false'],
        ]);

        $token = trim((string) $validated['token']);
        $breakdownRaw = (string) ($validated['breakdown'] ?? '0');
        $breakdown = in_array($breakdownRaw, ['1', 'true'], true);

        $payload = $this->bqCsvExportService->loadPayload($request, $project, $quotation, $token);
        $sheetCount = (int) ($payload['sheet_count'] ?? 0);
        $rowsData = $this->bqCsvExportService->buildExportRows(
            (array) ($payload['rows'] ?? []),
            $breakdown,
            $sheetCount
        );

        $missing = (array) ($rowsData['missing_mappings'] ?? []);
        if (!empty($missing)) {
            $message = 'Mapping konversi belum lengkap. Hubungi Admin/SuperAdmin untuk melengkapi mapping.';
            if ($request->expectsJson()) {
                throw ValidationException::withMessages([
                    'mappings' => $message,
                ]);
            }

            abort(422, $message);
        }

        $rows = (array) ($rowsData['rows'] ?? []);
        $filenameBase = trim((string) ($quotation->number ?: 'bq'));
        $filenameBase = preg_replace('/[^A-Za-z0-9._-]+/', '-', $filenameBase) ?: 'bq';
        $filename = $filenameBase.'-materials.csv';

        return response()->streamDownload(function () use ($rows) {
            $out = fopen('php://output', 'w');
            if ($out === false) {
                return;
            }

            fputcsv($out, ['Sheet', 'Category', 'Item', 'Quantity', 'Unit', 'Specification', 'LJR']);
            foreach ($rows as $row) {
                fputcsv($out, [
                    (string) ($row['Sheet'] ?? ''),
                    (string) ($row['Category'] ?? ''),
                    (string) ($row['Item'] ?? ''),
                    (string) ($row['Quantity'] ?? '0'),
                    (string) ($row['Unit'] ?? ''),
                    (string) ($row['Specification'] ?? ''),
                    (string) ($row['LJR'] ?? ''),
                ]);
            }

            fclose($out);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    private function ensureProjectMatch(Project $project, ProjectQuotation $quotation): void
    {
        if ((int) $quotation->project_id !== (int) $project->id) {
            abort(404);
        }
    }
}
