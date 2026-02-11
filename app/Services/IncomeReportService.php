<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\SalesOrderLine;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class IncomeReportService
{
    public function __construct(
        private readonly SalesCostAsOfDateService $salesCostAsOfDateService
    ) {
    }

    /**
     * Build dashboard KPI snapshot (cash + accrual + unpaid) for Admin/SuperAdmin.
     */
    public function dashboardSnapshot(?int $companyId = null): array
    {
        $today = Carbon::now()->startOfDay();
        $mtdStart = Carbon::now()->startOfMonth()->startOfDay();
        $ytdStart = Carbon::now()->startOfYear()->startOfDay();

        $cashToday = $this->cashTotal([
            'company_id' => $companyId,
            'start_date' => $today->toDateString(),
            'end_date' => $today->toDateString(),
        ]);

        $cashMtd = $this->cashTotal([
            'company_id' => $companyId,
            'start_date' => $mtdStart->toDateString(),
            'end_date' => $today->toDateString(),
        ]);

        $cashYtd = $this->cashTotal([
            'company_id' => $companyId,
            'start_date' => $ytdStart->toDateString(),
            'end_date' => $today->toDateString(),
        ]);

        $accrualMtd = $this->accrualTotal([
            'company_id' => $companyId,
            'start_date' => $mtdStart->toDateString(),
            'end_date' => $today->toDateString(),
        ]);

        $accrualYtd = $this->accrualTotal([
            'company_id' => $companyId,
            'start_date' => $ytdStart->toDateString(),
            'end_date' => $today->toDateString(),
        ]);

        $unpaidBalance = (float) $this->baseQuery([
            'company_id' => $companyId,
        ])
            ->where('status', 'posted')
            ->whereNull('paid_at')
            ->sum('total');

        return [
            'cash_today' => $cashToday,
            'cash_mtd' => $cashMtd,
            'cash_ytd' => $cashYtd,
            'accrual_mtd' => $accrualMtd,
            'accrual_ytd' => $accrualYtd,
            'unpaid_balance' => $unpaidBalance,
        ];
    }

    /**
     * Summary card values for report period.
     */
    public function reportSummary(array $filters): array
    {
        $cash = $this->cashTotal($filters);
        $accrual = $this->accrualTotal($filters);

        $unpaid = (float) $this->baseQuery($filters)
            ->where('status', 'posted')
            ->whereNull('paid_at')
            ->sum('total');

        return [
            'cash_total' => $cash,
            'accrual_total' => $accrual,
            'delta' => $accrual - $cash,
            'unpaid_balance' => $unpaid,
        ];
    }

    /**
     * Daily aggregation table (cash vs accrual).
     */
    public function dailySummary(array $filters): Collection
    {
        $normalized = $this->normalizeFilters($filters);
        $start = $normalized['start_date']->toDateString();
        $end = $normalized['end_date']->toDateString();

        $cashRows = $this->baseQuery($normalized)
            ->selectRaw('DATE(paid_at) as day')
            ->selectRaw('SUM(COALESCE(paid_amount, total, 0)) as cash_amount')
            ->whereNotNull('paid_at')
            ->whereRaw('DATE(paid_at) BETWEEN ? AND ?', [$start, $end])
            ->groupBy(DB::raw('DATE(paid_at)'))
            ->pluck('cash_amount', 'day');

        $accrualRows = $this->baseQuery($normalized)
            ->selectRaw('DATE(COALESCE(invoices.date, invoices.posted_at, invoices.created_at)) as day')
            ->selectRaw('SUM(total) as accrual_amount')
            ->whereIn('status', ['posted', 'paid'])
            ->whereRaw('DATE(COALESCE(invoices.date, invoices.posted_at, invoices.created_at)) BETWEEN ? AND ?', [$start, $end])
            ->groupBy(DB::raw('DATE(COALESCE(invoices.date, invoices.posted_at, invoices.created_at))'))
            ->pluck('accrual_amount', 'day');

        $days = $cashRows->keys()->merge($accrualRows->keys())->unique()->sortDesc()->values();

        return $days->map(function ($day) use ($cashRows, $accrualRows) {
            $cash = (float) ($cashRows[$day] ?? 0);
            $accrual = (float) ($accrualRows[$day] ?? 0);

            return (object) [
                'day' => $day,
                'cash_amount' => $cash,
                'accrual_amount' => $accrual,
                'delta' => $accrual - $cash,
            ];
        });
    }

    public function paginatedDetails(array $filters, int $perPage = 50): LengthAwarePaginator
    {
        return $this->detailsQuery($filters)->paginate($perPage)->withQueryString();
    }

    public function allDetails(array $filters, int $limit = 5000): Collection
    {
        return $this->detailsQuery($filters)->limit($limit)->get();
    }

    public function salesItemDetails(array $filters, int $limit = 500): Collection
    {
        $normalized = $this->normalizeFilters($filters);
        $start = $normalized['start_date']->toDateString();
        $end = $normalized['end_date']->toDateString();

        $query = SalesOrderLine::query()
            ->with([
                'salesOrder:id,company_id,customer_id,so_number,order_date,created_at,currency,status,po_type',
                'salesOrder.company:id,alias,name',
                'salesOrder.customer:id,name',
                'item:id,name,default_cost',
                'variant:id,item_id,sku,attributes,default_cost',
            ])
            ->whereNotNull('item_id')
            ->whereHas('salesOrder', function ($q) use ($normalized, $start, $end) {
                $q->where('po_type', 'goods')
                    ->whereRaw('DATE(COALESCE(order_date, created_at)) BETWEEN ? AND ?', [$start, $end])
                    ->when(
                        Schema::hasColumn('sales_orders', 'status'),
                        fn ($sq) => $sq->where('status', '!=', 'cancelled')
                    )
                    ->when($normalized['company_id'], fn ($sq, $companyId) => $sq->where('company_id', $companyId))
                    ->when($normalized['customer_id'], fn ($sq, $customerId) => $sq->where('customer_id', $customerId))
                    ->when($normalized['currency'] !== '', fn ($sq) => $sq->where('currency', $normalized['currency']));
            })
            ->orderByDesc('sales_order_id')
            ->orderBy('position')
            ->limit($limit);

        $rows = $query->get();

        return $rows->map(function (SalesOrderLine $line) {
            $so = $line->salesOrder;
            $soDate = $so?->order_date
                ? Carbon::parse($so->order_date)->startOfDay()
                : Carbon::parse($so?->created_at ?? now())->startOfDay();

            $cost = $this->salesCostAsOfDateService->resolve(
                itemId: (int) $line->item_id,
                variantId: $line->item_variant_id ? (int) $line->item_variant_id : null,
                soDate: $soDate,
            );

            $qty = (float) ($line->qty_ordered ?? 0);
            $revenue = (float) ($line->line_total ?? 0);
            $costUnit = $cost['cost_unit'] !== null ? (float) $cost['cost_unit'] : null;
            $costTotal = $costUnit !== null ? round($costUnit * $qty, 2) : null;
            $grossProfit = $costTotal !== null ? round($revenue - $costTotal, 2) : null;

            return (object) [
                'so_id' => $so?->id,
                'so_number' => $so?->so_number,
                'so_date' => $soDate->toDateString(),
                'company_name' => $so?->company?->alias ?: ($so?->company?->name ?? '-'),
                'customer_name' => $so?->customer?->name ?? '-',
                'item_name' => $line->item?->name ?: ($line->name ?? '-'),
                'variant_sku' => $line->variant?->sku,
                'qty' => $qty,
                'revenue' => $revenue,
                'cost_unit_used' => $costUnit,
                'cost_total' => $costTotal,
                'gross_profit' => $grossProfit,
                'cost_source' => $cost['source'],
                'cost_effective_date' => $cost['effective_date'],
                'cost_missing' => (bool) $cost['cost_missing'],
            ];
        });
    }

    protected function detailsQuery(array $filters): Builder
    {
        $normalized = $this->normalizeFilters($filters);
        $start = $normalized['start_date']->toDateString();
        $end = $normalized['end_date']->toDateString();
        $basis = $normalized['basis'];

        $query = $this->baseQuery($normalized)
            ->with(['company:id,alias,name', 'customer:id,name'])
            ->select('invoices.*')
            ->selectRaw(
                "CASE WHEN paid_at IS NOT NULL AND DATE(paid_at) BETWEEN ? AND ? THEN 1 ELSE 0 END AS in_cash",
                [$start, $end]
            )
            ->selectRaw(
                "CASE WHEN status IN ('posted', 'paid') AND DATE(COALESCE(invoices.date, invoices.posted_at, invoices.created_at)) BETWEEN ? AND ? THEN 1 ELSE 0 END AS in_accrual",
                [$start, $end]
            )
            ->selectRaw('DATE(COALESCE(invoices.date, invoices.posted_at, invoices.created_at)) AS accrual_date');

        if ($basis === 'cash') {
            $query
                ->whereNotNull('paid_at')
                ->whereRaw('DATE(paid_at) BETWEEN ? AND ?', [$start, $end]);
        } elseif ($basis === 'accrual') {
            $query
                ->whereIn('status', ['posted', 'paid'])
                ->whereRaw('DATE(COALESCE(invoices.date, invoices.posted_at, invoices.created_at)) BETWEEN ? AND ?', [$start, $end]);
        } else {
            $query->whereRaw(
                "(paid_at IS NOT NULL AND DATE(paid_at) BETWEEN ? AND ?)
                 OR
                 (status IN ('posted', 'paid') AND DATE(COALESCE(invoices.date, invoices.posted_at, invoices.created_at)) BETWEEN ? AND ?)",
                [$start, $end, $start, $end]
            );
        }

        return $query
            ->orderByRaw('COALESCE(invoices.paid_at, invoices.date, invoices.posted_at, invoices.created_at) DESC')
            ->orderByDesc('id');
    }

    protected function cashTotal(array $filters): float
    {
        $normalized = $this->normalizeFilters($filters);
        $start = $normalized['start_date']->toDateString();
        $end = $normalized['end_date']->toDateString();

        return (float) $this->baseQuery($normalized)
            ->whereNotNull('paid_at')
            ->whereRaw('DATE(paid_at) BETWEEN ? AND ?', [$start, $end])
            ->sum(DB::raw('COALESCE(paid_amount, total, 0)'));
    }

    protected function accrualTotal(array $filters): float
    {
        $normalized = $this->normalizeFilters($filters);
        $start = $normalized['start_date']->toDateString();
        $end = $normalized['end_date']->toDateString();

        return (float) $this->baseQuery($normalized)
            ->whereIn('status', ['posted', 'paid'])
            ->whereRaw('DATE(COALESCE(invoices.date, invoices.posted_at, invoices.created_at)) BETWEEN ? AND ?', [$start, $end])
            ->sum('total');
    }

    protected function baseQuery(array $filters): Builder
    {
        $normalized = $this->normalizeFilters($filters);

        return Invoice::query()
            ->when($normalized['company_id'], fn (Builder $q, int $companyId) => $q->where('company_id', $companyId))
            ->when($normalized['customer_id'], fn (Builder $q, int $customerId) => $q->where('customer_id', $customerId))
            ->when($normalized['currency'] !== '', fn (Builder $q) => $q->where('currency', $normalized['currency']));
    }

    /**
     * @return array{
     *   company_id:int|null,
     *   customer_id:int|null,
     *   currency:string,
     *   basis:string,
     *   start_date:Carbon,
     *   end_date:Carbon
     * }
     */
    public function normalizeFilters(array $filters): array
    {
        $today = Carbon::now()->startOfDay();
        $mtdStart = Carbon::now()->startOfMonth()->startOfDay();

        $startDate = !empty($filters['start_date'])
            ? Carbon::parse((string) $filters['start_date'])->startOfDay()
            : $mtdStart;

        $endDate = !empty($filters['end_date'])
            ? Carbon::parse((string) $filters['end_date'])->startOfDay()
            : $today;

        if ($endDate->lt($startDate)) {
            [$startDate, $endDate] = [$endDate, $startDate];
        }

        $basis = (string) ($filters['basis'] ?? 'both');
        if (!in_array($basis, ['cash', 'accrual', 'both'], true)) {
            $basis = 'both';
        }

        return [
            'company_id' => !empty($filters['company_id']) ? (int) $filters['company_id'] : null,
            'customer_id' => !empty($filters['customer_id']) ? (int) $filters['customer_id'] : null,
            'currency' => strtoupper(trim((string) ($filters['currency'] ?? ''))),
            'basis' => $basis,
            'start_date' => $startDate,
            'end_date' => $endDate,
        ];
    }
}
