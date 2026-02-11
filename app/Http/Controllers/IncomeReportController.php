<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\Customer;
use App\Models\Invoice;
use App\Services\IncomeReportService;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class IncomeReportController extends Controller
{
    public function __construct(
        private readonly IncomeReportService $incomeReportService
    ) {
    }

    public function index(Request $request)
    {
        $this->authorizeAdmin($request);

        $filters = $this->filtersFromRequest($request);
        $normalized = $this->incomeReportService->normalizeFilters($filters);

        $summary = $this->incomeReportService->reportSummary($filters);
        $dailyRows = $this->incomeReportService->dailySummary($filters);
        $invoices = $this->incomeReportService->paginatedDetails($filters, 50);
        $salesItems = $this->incomeReportService->salesItemDetails($filters, 500);
        $salesSummary = $this->salesItemSummary($salesItems);

        $companies = Company::query()->orderBy('name')->get(['id', 'alias', 'name']);
        $customers = Customer::query()->orderBy('name')->limit(500)->get(['id', 'name']);
        $currencies = Invoice::query()
            ->whereNotNull('currency')
            ->where('currency', '!=', '')
            ->distinct()
            ->orderBy('currency')
            ->pluck('currency');

        return view('reports.income', [
            'filters' => $normalized,
            'summary' => $summary,
            'dailyRows' => $dailyRows,
            'invoices' => $invoices,
            'salesItems' => $salesItems,
            'salesSummary' => $salesSummary,
            'companies' => $companies,
            'customers' => $customers,
            'currencies' => $currencies,
        ]);
    }

    public function exportCsv(Request $request): StreamedResponse
    {
        $this->authorizeAdmin($request);

        $filters = $this->filtersFromRequest($request);
        $normalized = $this->incomeReportService->normalizeFilters($filters);
        $rows = $this->incomeReportService->allDetails($filters);
        $salesItems = $this->incomeReportService->salesItemDetails($filters, 2000);

        $filename = sprintf(
            'income-report-%s_%s.csv',
            $normalized['start_date']->toDateString(),
            $normalized['end_date']->toDateString()
        );

        return response()->streamDownload(function () use ($rows, $salesItems) {
            $out = fopen('php://output', 'w');
            if ($out === false) {
                return;
            }

            fputcsv($out, [
                'Invoice No',
                'Company',
                'Customer',
                'Invoice Date',
                'Paid Date',
                'Status',
                'Currency',
                'Total',
                'Paid Amount',
                'Basis',
            ]);

            foreach ($rows as $inv) {
                $tags = [];
                if ((int) ($inv->in_cash ?? 0) === 1) {
                    $tags[] = 'cash';
                }
                if ((int) ($inv->in_accrual ?? 0) === 1) {
                    $tags[] = 'accrual';
                }

                fputcsv($out, [
                    $inv->number ?? $inv->id,
                    $inv->company?->alias ?: ($inv->company?->name ?? '-'),
                    $inv->customer?->name ?? '-',
                    optional($inv->date)->format('Y-m-d'),
                    optional($inv->paid_at)->format('Y-m-d'),
                    strtoupper((string) $inv->status),
                    $inv->currency ?? 'IDR',
                    (float) ($inv->total ?? 0),
                    (float) ($inv->paid_amount ?? $inv->total ?? 0),
                    $tags ? implode('+', $tags) : '-',
                ]);
            }

            fputcsv($out, []);
            fputcsv($out, ['SO Item Sales (Cost by SO Date)']);
            fputcsv($out, [
                'SO No',
                'SO Date',
                'Company',
                'Customer',
                'Item',
                'Variant SKU',
                'Qty',
                'Revenue',
                'Cost Unit Used',
                'Cost Total',
                'Gross Profit',
                'Cost Source',
                'Cost Effective Date',
                'Cost Missing',
            ]);

            foreach ($salesItems as $row) {
                fputcsv($out, [
                    $row->so_number ?: $row->so_id,
                    $row->so_date,
                    $row->company_name,
                    $row->customer_name,
                    $row->item_name,
                    $row->variant_sku ?: '-',
                    (float) $row->qty,
                    (float) $row->revenue,
                    $row->cost_unit_used !== null ? (float) $row->cost_unit_used : '',
                    $row->cost_total !== null ? (float) $row->cost_total : '',
                    $row->gross_profit !== null ? (float) $row->gross_profit : '',
                    $row->cost_source,
                    $row->cost_effective_date ?: '',
                    $row->cost_missing ? 'yes' : 'no',
                ]);
            }

            fclose($out);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    public function exportPdf(Request $request)
    {
        $this->authorizeAdmin($request);

        $filters = $this->filtersFromRequest($request);
        $normalized = $this->incomeReportService->normalizeFilters($filters);
        $summary = $this->incomeReportService->reportSummary($filters);
        $dailyRows = $this->incomeReportService->dailySummary($filters)->take(31);
        $details = $this->incomeReportService->allDetails($filters, 500);
        $salesItems = $this->incomeReportService->salesItemDetails($filters, 400);
        $salesSummary = $this->salesItemSummary($salesItems);

        $options = new Options();
        $options->set('isRemoteEnabled', true);

        $pdf = new Dompdf($options);
        $html = view('reports.income_pdf', [
            'filters' => $normalized,
            'summary' => $summary,
            'dailyRows' => $dailyRows,
            'details' => $details,
            'salesItems' => $salesItems,
            'salesSummary' => $salesSummary,
        ])->render();

        $pdf->loadHtml($html);
        $pdf->setPaper('A4', 'portrait');
        $pdf->render();

        $filename = sprintf(
            'income-report-%s_%s.pdf',
            $normalized['start_date']->toDateString(),
            $normalized['end_date']->toDateString()
        );

        return response($pdf->output(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.$filename.'"',
        ]);
    }

    private function filtersFromRequest(Request $request): array
    {
        $validated = $request->validate([
            'company_id' => ['nullable', 'integer', 'exists:companies,id'],
            'customer_id' => ['nullable', 'integer', 'exists:customers,id'],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date'],
            'currency' => ['nullable', 'string', 'max:10'],
            'basis' => ['nullable', 'in:cash,accrual,both'],
        ]);

        return [
            'company_id' => $validated['company_id'] ?? null,
            'customer_id' => $validated['customer_id'] ?? null,
            'start_date' => $validated['start_date'] ?? null,
            'end_date' => $validated['end_date'] ?? null,
            'currency' => $validated['currency'] ?? null,
            'basis' => $validated['basis'] ?? 'both',
        ];
    }

    private function authorizeAdmin(Request $request): void
    {
        $user = $request->user();
        if (!$user) {
            abort(403, 'Admin only.');
        }

        if (method_exists($user, 'hasAnyRole')) {
            abort_unless($user->hasAnyRole(['Admin', 'SuperAdmin']), 403, 'Admin only.');
            return;
        }

        if (method_exists($user, 'hasRole')) {
            abort_unless($user->hasRole('Admin') || $user->hasRole('SuperAdmin'), 403, 'Admin only.');
            return;
        }

        abort(403, 'Admin only.');
    }

    private function salesItemSummary($salesItems): array
    {
        $revenue = (float) $salesItems->sum(fn ($row) => (float) ($row->revenue ?? 0));
        $cost = (float) $salesItems
            ->filter(fn ($row) => $row->cost_total !== null)
            ->sum(fn ($row) => (float) $row->cost_total);
        $grossProfit = $revenue - $cost;
        $missing = (int) $salesItems->where('cost_missing', true)->count();

        return [
            'revenue' => $revenue,
            'cost' => $cost,
            'gross_profit' => $grossProfit,
            'missing_count' => $missing,
            'line_count' => (int) $salesItems->count(),
        ];
    }
}
