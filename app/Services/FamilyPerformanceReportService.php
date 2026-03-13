<?php

namespace App\Services;

use App\Models\Item;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class FamilyPerformanceReportService
{
    public function __construct(
        private readonly RefillSizeParser $refillSizeParser
    ) {
    }

    public function normalizeFilters(array $filters): array
    {
        $startDate = !empty($filters['from'])
            ? Carbon::parse((string) $filters['from'])->startOfDay()
            : now()->startOfMonth()->startOfDay();

        $endDate = !empty($filters['to'])
            ? Carbon::parse((string) $filters['to'])->endOfDay()
            : now()->endOfMonth()->endOfDay();

        if ($endDate->lt($startDate)) {
            [$startDate, $endDate] = [$endDate->copy()->startOfDay(), $startDate->copy()->endOfDay()];
        }

        return [
            'from' => $startDate,
            'to' => $endDate,
            'family_code' => trim((string) ($filters['family_code'] ?? '')),
        ];
    }

    public function familyCodeOptions(): Collection
    {
        return Item::query()
            ->whereNotNull('family_code')
            ->where('family_code', '!=', '')
            ->distinct()
            ->orderBy('family_code')
            ->pluck('family_code');
    }

    public function buildReport(array $filters): array
    {
        $normalized = $this->normalizeFilters($filters);
        $revenueItems = $this->revenueItemRows($normalized);
        $revenueFamilies = $this->summarizeRevenueByFamily($revenueItems)->keyBy('family_code');
        $costFamilies = $this->costByFamily($normalized)->keyBy('family_code');

        $familyCodes = $revenueFamilies->keys()
            ->merge($costFamilies->keys())
            ->filter(fn ($code) => trim((string) $code) !== '')
            ->unique()
            ->sort()
            ->values();

        $summaryRows = $familyCodes->map(function (string $familyCode) use ($revenueFamilies, $costFamilies) {
            $revenueRow = $revenueFamilies->get($familyCode);
            $costRow = $costFamilies->get($familyCode);

            $revenue = (float) ($revenueRow->total_revenue ?? 0);
            $cost = (float) ($costRow->total_cost ?? 0);
            $margin = $revenue - $cost;

            return (object) [
                'family_code' => $familyCode,
                'total_qty_sold' => (float) ($revenueRow->total_qty_sold ?? 0),
                'total_qty_purchased' => (float) ($costRow->total_qty_purchased ?? 0),
                'total_revenue' => $revenue,
                'total_cost' => $cost,
                'margin' => $margin,
                'margin_percent' => $revenue > 0 ? ($margin / $revenue) * 100 : null,
            ];
        });

        return [
            'filters' => $normalized,
            'summary_rows' => $summaryRows,
            'totals' => [
                'revenue' => (float) $summaryRows->sum('total_revenue'),
                'cost' => (float) $summaryRows->sum('total_cost'),
                'margin' => (float) $summaryRows->sum('margin'),
                'qty_sold' => (float) $summaryRows->sum('total_qty_sold'),
                'qty_purchased' => (float) $summaryRows->sum('total_qty_purchased'),
            ],
            'refill' => $this->refillDetails($normalized, $familyCodes, $revenueItems),
        ];
    }

    private function revenueItemRows(array $filters): Collection
    {
        $mappedRows = DB::table('invoice_lines as line')
            ->join('invoices as invoice', 'invoice.id', '=', 'line.invoice_id')
            ->leftJoin('items as direct_item', 'direct_item.id', '=', 'line.item_id')
            ->leftJoin('sales_order_lines as so_line', 'so_line.id', '=', 'line.sales_order_line_id')
            ->leftJoin('items as so_item', 'so_item.id', '=', 'so_line.item_id')
            ->whereIn('invoice.status', ['posted', 'paid'])
            ->whereBetween(
                DB::raw('DATE(COALESCE(invoice.date, invoice.posted_at, invoice.created_at))'),
                [$filters['from']->toDateString(), $filters['to']->toDateString()]
            )
            ->selectRaw('
                invoice.id as invoice_id,
                invoice.sales_order_id,
                COALESCE(direct_item.id, so_item.id) as item_id,
                COALESCE(direct_item.name, so_item.name, line.description) as item_name,
                COALESCE(direct_item.family_code, so_item.family_code) as family_code,
                COALESCE(line.qty, 0) as qty_sold,
                COALESCE(line.line_total, 0) as revenue
            ')
            ->get()
            ->filter(function ($row) use ($filters) {
                $familyCode = trim((string) ($row->family_code ?? ''));

                if ($familyCode === '') {
                    return false;
                }

                return $filters['family_code'] === '' || $familyCode === $filters['family_code'];
            })
            ->values();

        $coveredInvoiceIds = $mappedRows
            ->pluck('invoice_id')
            ->unique()
            ->values();

        $headerFallbackRows = $this->headerFallbackRevenueRows($filters, $coveredInvoiceIds);

        return $mappedRows
            ->concat($headerFallbackRows)
            ->groupBy(fn ($row) => implode('|', [
                (string) $row->family_code,
                (string) ($row->item_id ?? 0),
                trim((string) ($row->item_name ?? '')),
            ]))
            ->map(function (Collection $rows) {
                $first = $rows->first();

                return (object) [
                    'family_code' => (string) $first->family_code,
                    'item_id' => $first->item_id !== null ? (int) $first->item_id : null,
                    'item_name' => (string) $first->item_name,
                    'qty_sold' => (float) $rows->sum(fn ($row) => (float) $row->qty_sold),
                    'revenue' => (float) $rows->sum(fn ($row) => (float) $row->revenue),
                ];
            })
            ->sortBy([
                fn ($row) => $row->family_code,
                fn ($row) => $row->item_name,
            ])
            ->values();
    }

    private function headerFallbackRevenueRows(array $filters, Collection $coveredInvoiceIds): Collection
    {
        $headerInvoices = DB::table('invoices as invoice')
            ->whereIn('invoice.status', ['posted', 'paid'])
            ->whereNotNull('invoice.sales_order_id')
            ->whereBetween(
                DB::raw('DATE(COALESCE(invoice.date, invoice.posted_at, invoice.created_at))'),
                [$filters['from']->toDateString(), $filters['to']->toDateString()]
            )
            ->when(
                $coveredInvoiceIds->isNotEmpty(),
                fn ($query) => $query->whereNotIn('invoice.id', $coveredInvoiceIds->all())
            )
            ->get([
                'invoice.id',
                'invoice.sales_order_id',
                'invoice.total',
            ]);

        if ($headerInvoices->isEmpty()) {
            return collect();
        }

        $salesOrderIds = $headerInvoices
            ->pluck('sales_order_id')
            ->filter()
            ->unique()
            ->values();

        $salesOrderLines = DB::table('sales_order_lines as line')
            ->join('items as item', 'item.id', '=', 'line.item_id')
            ->whereIn('line.sales_order_id', $salesOrderIds)
            ->whereNotNull('item.family_code')
            ->where('item.family_code', '!=', '')
            ->when(
                $filters['family_code'] !== '',
                fn ($query) => $query->where('item.family_code', $filters['family_code'])
            )
            ->orderBy('line.id')
            ->get([
                'line.sales_order_id',
                'line.item_id',
                'item.name as item_name',
                'item.family_code',
                'line.qty_ordered',
                'line.line_total',
            ])
            ->groupBy('sales_order_id');

        return $headerInvoices->flatMap(function ($invoice) use ($salesOrderLines) {
            $lines = $salesOrderLines->get($invoice->sales_order_id, collect());
            $denominator = (float) $lines->sum(fn ($line) => (float) $line->line_total);

            if ($lines->isEmpty() || $denominator <= 0) {
                return [];
            }

            return $lines->map(function ($line) use ($invoice, $denominator) {
                $share = ((float) $line->line_total) / $denominator;

                return (object) [
                    'invoice_id' => $invoice->id,
                    'family_code' => (string) $line->family_code,
                    'item_id' => (int) $line->item_id,
                    'item_name' => (string) $line->item_name,
                    'qty_sold' => (float) $line->qty_ordered,
                    'revenue' => (float) $invoice->total * $share,
                ];
            });
        })->values();
    }

    private function summarizeRevenueByFamily(Collection $rows): Collection
    {
        return $rows
            ->groupBy('family_code')
            ->map(function (Collection $familyRows, string $familyCode) {
                return (object) [
                    'family_code' => $familyCode,
                    'total_qty_sold' => (float) $familyRows->sum('qty_sold'),
                    'total_revenue' => (float) $familyRows->sum('revenue'),
                ];
            })
            ->sortBy('family_code')
            ->values();
    }

    private function costByFamily(array $filters): Collection
    {
        $primaryRows = DB::table('goods_receipt_lines as line')
            ->join('goods_receipts as receipt', 'receipt.id', '=', 'line.goods_receipt_id')
            ->join('items as item', 'item.id', '=', 'line.item_id')
            ->where('receipt.status', 'posted')
            ->whereNotNull('item.family_code')
            ->where('item.family_code', '!=', '')
            ->whereBetween(
                DB::raw('DATE(COALESCE(receipt.gr_date, receipt.posted_at, receipt.created_at))'),
                [$filters['from']->toDateString(), $filters['to']->toDateString()]
            )
            ->when(
                $filters['family_code'] !== '',
                fn ($query) => $query->where('item.family_code', $filters['family_code'])
            )
            ->get([
                'item.family_code',
                DB::raw('COALESCE(line.qty_received, 0) as qty_purchased'),
                DB::raw('COALESCE(line.line_total, 0) as cost'),
            ]);

        $fallbackRows = DB::table('purchase_order_lines as line')
            ->join('purchase_orders as po', 'po.id', '=', 'line.purchase_order_id')
            ->join('items as item', 'item.id', '=', 'line.item_id')
            ->whereIn('po.status', ['approved', 'partially_received', 'fully_received', 'closed'])
            ->whereNotNull('item.family_code')
            ->where('item.family_code', '!=', '')
            ->whereBetween(
                DB::raw('DATE(COALESCE(po.approved_at, po.order_date, po.created_at))'),
                [$filters['from']->toDateString(), $filters['to']->toDateString()]
            )
            ->whereNotExists(function ($query) {
                $query->selectRaw('1')
                    ->from('goods_receipts as receipt')
                    ->whereColumn('receipt.purchase_order_id', 'po.id')
                    ->where('receipt.status', 'posted');
            })
            ->when(
                $filters['family_code'] !== '',
                fn ($query) => $query->where('item.family_code', $filters['family_code'])
            )
            ->get([
                'item.family_code',
                DB::raw('COALESCE(line.qty_ordered, 0) as qty_purchased'),
                DB::raw('COALESCE(line.line_total, 0) as cost'),
            ]);

        return $primaryRows
            ->concat($fallbackRows)
            ->groupBy('family_code')
            ->map(function (Collection $rows, string $familyCode) {
                return (object) [
                    'family_code' => $familyCode,
                    'total_qty_purchased' => (float) $rows->sum(fn ($row) => (float) $row->qty_purchased),
                    'total_cost' => (float) $rows->sum(fn ($row) => (float) $row->cost),
                ];
            })
            ->sortBy('family_code')
            ->values();
    }

    private function refillDetails(array $filters, Collection $familyCodes, Collection $revenueItems): ?array
    {
        $shouldLoadRefill = $filters['family_code'] === 'REFILL'
            || ($filters['family_code'] === '' && $familyCodes->contains('REFILL'));

        if (!$shouldLoadRefill) {
            return null;
        }

        $rows = $revenueItems
            ->where('family_code', 'REFILL')
            ->map(function ($row) {
                $detectedSizeKg = $this->refillSizeParser->detectKg($row->item_name);

                return (object) [
                    'item_id' => $row->item_id,
                    'item_name' => $row->item_name,
                    'qty_sold' => (float) $row->qty_sold,
                    'revenue' => (float) $row->revenue,
                    'detected_size_kg' => $detectedSizeKg,
                    'estimated_powder_kg' => $detectedSizeKg !== null
                        ? (float) $row->qty_sold * $detectedSizeKg
                        : null,
                ];
            })
            ->sortBy([
                fn ($row) => $row->detected_size_kg ?? 999999,
                fn ($row) => $row->item_name,
            ])
            ->values();

        if ($rows->isEmpty()) {
            return null;
        }

        return [
            'rows' => $rows,
            'total_tubes' => (float) $rows->sum('qty_sold'),
            'estimated_powder_kg' => (float) $rows->sum(fn ($row) => (float) ($row->estimated_powder_kg ?? 0)),
        ];
    }
}
