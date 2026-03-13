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

        $familyCode = trim((string) ($filters['family_code'] ?? ''));

        return [
            'from' => $startDate,
            'to' => $endDate,
            'family_code' => $familyCode,
        ];
    }

    public function familyCodeOptions(): Collection
    {
        return Item::query()
            ->whereNotNull('family_code')
            ->where('family_code', '!=', '')
            ->orderBy('family_code')
            ->distinct()
            ->pluck('family_code');
    }

    public function buildReport(array $filters): array
    {
        $normalized = $this->normalizeFilters($filters);
        $revenueRows = $this->revenueByFamily($normalized)->keyBy('family_code');
        $costRows = $this->costByFamily($normalized)->keyBy('family_code');

        $familyCodes = $revenueRows->keys()
            ->merge($costRows->keys())
            ->unique()
            ->filter(fn ($code) => trim((string) $code) !== '')
            ->sort()
            ->values();

        $summaryRows = $familyCodes->map(function (string $familyCode) use ($revenueRows, $costRows) {
            $revenueRow = $revenueRows->get($familyCode);
            $costRow = $costRows->get($familyCode);

            $qtySold = (float) ($revenueRow->total_qty_sold ?? 0);
            $revenue = (float) ($revenueRow->total_revenue ?? 0);
            $qtyPurchased = (float) ($costRow->total_qty_purchased ?? 0);
            $cost = (float) ($costRow->total_cost ?? 0);
            $margin = $revenue - $cost;

            return (object) [
                'family_code' => $familyCode,
                'total_qty_sold' => $qtySold,
                'total_revenue' => $revenue,
                'total_qty_purchased' => $qtyPurchased,
                'total_cost' => $cost,
                'margin' => $margin,
                'margin_percent' => $revenue > 0 ? ($margin / $revenue) * 100 : null,
            ];
        });

        $refillDetails = $this->refillDetails($normalized, $familyCodes);

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
            'refill' => $refillDetails,
        ];
    }

    private function revenueByFamily(array $filters): Collection
    {
        return DB::table('invoice_lines as line')
            ->join('invoices as invoice', 'invoice.id', '=', 'line.invoice_id')
            ->join('items as item', 'item.id', '=', 'line.item_id')
            ->whereIn('invoice.status', ['posted', 'paid'])
            ->whereNotNull('item.family_code')
            ->where('item.family_code', '!=', '')
            ->whereBetween(
                DB::raw('DATE(COALESCE(invoice.date, invoice.posted_at, invoice.created_at))'),
                [$filters['from']->toDateString(), $filters['to']->toDateString()]
            )
            ->when(
                $filters['family_code'] !== '',
                fn ($query) => $query->where('item.family_code', $filters['family_code'])
            )
            ->groupBy('item.family_code')
            ->orderBy('item.family_code')
            ->get([
                'item.family_code',
                DB::raw('SUM(COALESCE(line.qty, 0)) as total_qty_sold'),
                DB::raw('SUM(COALESCE(line.line_total, 0)) as total_revenue'),
            ]);
    }

    private function costByFamily(array $filters): Collection
    {
        return DB::table('goods_receipt_lines as line')
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
            ->groupBy('item.family_code')
            ->orderBy('item.family_code')
            ->get([
                'item.family_code',
                DB::raw('SUM(COALESCE(line.qty_received, 0)) as total_qty_purchased'),
                DB::raw('SUM(COALESCE(line.line_total, 0)) as total_cost'),
            ]);
    }

    private function refillDetails(array $filters, Collection $familyCodes): ?array
    {
        $shouldLoadRefill = $filters['family_code'] === 'REFILL'
            || ($filters['family_code'] === '' && $familyCodes->contains('REFILL'));

        if (!$shouldLoadRefill) {
            return null;
        }

        $rows = DB::table('invoice_lines as line')
            ->join('invoices as invoice', 'invoice.id', '=', 'line.invoice_id')
            ->join('items as item', 'item.id', '=', 'line.item_id')
            ->whereIn('invoice.status', ['posted', 'paid'])
            ->where('item.family_code', 'REFILL')
            ->whereBetween(
                DB::raw('DATE(COALESCE(invoice.date, invoice.posted_at, invoice.created_at))'),
                [$filters['from']->toDateString(), $filters['to']->toDateString()]
            )
            ->groupBy('line.item_id', 'item.name')
            ->orderBy('item.name')
            ->get([
                'line.item_id',
                'item.name as item_name',
                DB::raw('SUM(COALESCE(line.qty, 0)) as qty_sold'),
                DB::raw('SUM(COALESCE(line.line_total, 0)) as revenue'),
            ])
            ->map(function ($row) {
                $detectedSizeKg = $this->refillSizeParser->detectKg($row->item_name);
                $qtySold = (float) $row->qty_sold;

                return (object) [
                    'item_id' => $row->item_id,
                    'item_name' => $row->item_name,
                    'qty_sold' => $qtySold,
                    'revenue' => (float) $row->revenue,
                    'detected_size_kg' => $detectedSizeKg,
                    'estimated_powder_kg' => $detectedSizeKg !== null ? $qtySold * $detectedSizeKg : null,
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
