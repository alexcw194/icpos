<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class SalesCommissionFeeService
{
    public function __construct(
        private readonly SalesCommissionRateResolver $rateResolver
    ) {
    }

    public function normalizeFilters(array $filters): array
    {
        $month = !empty($filters['month'])
            ? Carbon::createFromFormat('Y-m', (string) $filters['month'])->startOfMonth()
            : now()->startOfMonth();
        $rowStatus = (string) ($filters['row_status'] ?? 'all');

        return [
            'month' => $month,
            'from' => $month->copy()->startOfMonth(),
            'to' => $month->copy()->endOfMonth(),
            'sales_user_id' => !empty($filters['sales_user_id']) ? (int) $filters['sales_user_id'] : null,
            'row_status' => in_array($rowStatus, ['all', 'available', 'in_unpaid_note', 'in_paid_note'], true)
                ? $rowStatus
                : 'all',
        ];
    }

    public function buildReport(array $filters): array
    {
        $normalized = $this->normalizeFilters($filters);
        $rows = $this->sourceRows($normalized);
        $rows = $this->filterRowsByStatus($rows, $normalized['row_status']);

        if ($normalized['sales_user_id']) {
            $rows = $rows->where('sales_user_id', $normalized['sales_user_id'])->values();
        }

        return [
            'filters' => $normalized,
            'features' => [
                'commission_notes_ready' => Schema::hasTable('sales_commission_notes')
                    && Schema::hasTable('sales_commission_note_lines'),
            ],
            'summary' => [
                'revenue_total' => (float) $rows->sum('revenue'),
                'under_total' => (float) $rows->sum('under_allocated'),
                'commissionable_total' => (float) $rows->sum('commissionable_base'),
                'fee_total' => (float) $rows->sum('fee_amount'),
                'sales_order_count' => $rows->pluck('sales_order_id')->filter()->unique()->count(),
                'row_count' => $rows->count(),
                'unresolved_count' => $rows->where('is_unresolved', true)->count(),
                'unassigned_sales_count' => $rows->filter(fn ($row) => !$row->sales_user_id)->count(),
                'available_count' => $rows->where('source_status', 'available')->count(),
            ],
            'rows' => $rows,
            'salesUsers' => $this->salesUsersFromRows($rows),
        ];
    }

    public function availableSourceRowsForNote(array $filters, array $sourceKeys): Collection
    {
        $normalized = $this->normalizeFilters($filters);
        $allRows = $this->sourceRows($normalized)->keyBy('source_key');

        return collect($sourceKeys)
            ->map(fn ($key) => trim((string) $key))
            ->filter(fn ($key) => $key !== '')
            ->unique()
            ->map(function (string $key) use ($allRows) {
                $row = $allRows->get($key);
                if (!$row || !$row->selectable || $row->source_status !== 'available') {
                    return null;
                }

                return $row;
            })
            ->filter()
            ->values();
    }

    private function sourceRows(array $filters): Collection
    {
        $finalizedSalesOrders = DB::table('invoices as invoice')
            ->whereIn('invoice.status', ['posted', 'paid'])
            ->whereNotNull('invoice.sales_order_id')
            ->selectRaw('invoice.sales_order_id, MIN(DATE(COALESCE(invoice.date, invoice.posted_at, invoice.created_at))) as finalized_date')
            ->groupBy('invoice.sales_order_id');

        $rows = DB::table('sales_order_lines as line')
            ->join('sales_orders as so', 'so.id', '=', 'line.sales_order_id')
            ->joinSub($finalizedSalesOrders, 'finalized_so', function ($join) {
                $join->on('finalized_so.sales_order_id', '=', 'so.id');
            })
            ->join('customers as customer', 'customer.id', '=', 'so.customer_id')
            ->leftJoin('projects as project', 'project.id', '=', 'so.project_id')
            ->leftJoin('items as item', 'item.id', '=', 'line.item_id')
            ->leftJoin('brands as brand', 'brand.id', '=', 'item.brand_id')
            ->leftJoin('users as sales_user', 'sales_user.id', '=', 'so.sales_user_id')
            ->whereBetween('finalized_so.finalized_date', [$filters['from']->toDateString(), $filters['to']->toDateString()])
            ->selectRaw('
                line.id as sales_order_line_id,
                so.id as sales_order_id,
                so.so_number as sales_order_number,
                DATE(finalized_so.finalized_date) as finalized_date,
                so.po_type,
                so.project_id,
                so.taxable_base as sales_order_taxable_base,
                so.under_amount as sales_order_under_amount,
                so.sales_user_id,
                sales_user.name as sales_user_name,
                so.customer_id,
                customer.name as customer_name,
                item.id as item_id,
                COALESCE(NULLIF(line.po_item_name, ""), NULLIF(line.name, ""), item.name, "-") as item_name,
                item.brand_id as brand_id,
                brand.name as brand_name,
                item.family_code as family_code,
                COALESCE(line.qty_ordered, 0) as qty_sold,
                COALESCE(line.line_total, 0) as line_total,
                project.systems_json as project_systems_json
            ')
            ->get()
            ->map(function ($row) {
                $familyCode = strtoupper(trim((string) ($row->family_code ?? '')));
                $projectSystems = $this->normalizeJsonArray($row->project_systems_json ?? []);
                $resolvedRate = $this->rateResolver->resolve((object) [
                    'brand_id' => $row->brand_id,
                    'brand_name' => $row->brand_name,
                    'family_code' => $familyCode,
                    'po_type' => $row->po_type,
                    'project_systems' => $projectSystems,
                ]);

                return (object) [
                    'source_key' => sprintf('so-line|%d', (int) $row->sales_order_line_id),
                    'invoice_id' => null,
                    'invoice_line_id' => null,
                    'invoice_number' => null,
                    'invoice_date' => null,
                    'finalized_date' => $row->finalized_date,
                    'sales_order_line_id' => (int) $row->sales_order_line_id,
                    'sales_order_id' => $row->sales_order_id ? (int) $row->sales_order_id : null,
                    'sales_order_number' => (string) ($row->sales_order_number ?? '-'),
                    'po_type' => (string) ($row->po_type ?? 'goods'),
                    'project_id' => $row->project_id ? (int) $row->project_id : null,
                    'sales_order_taxable_base' => (float) ($row->sales_order_taxable_base ?? 0),
                    'sales_order_under_amount' => (float) ($row->sales_order_under_amount ?? 0),
                    'project_scope' => $resolvedRate['project_scope'],
                    'project_scope_label' => $this->projectScopeLabel($resolvedRate['project_scope']),
                    'sales_user_id' => $row->sales_user_id ? (int) $row->sales_user_id : null,
                    'sales_user_name' => (string) ($row->sales_user_name ?? '-'),
                    'customer_id' => (int) $row->customer_id,
                    'customer_name' => (string) ($row->customer_name ?? '-'),
                    'item_id' => $row->item_id ? (int) $row->item_id : null,
                    'item_name' => (string) ($row->item_name ?? '-'),
                    'brand_id' => $row->brand_id ? (int) $row->brand_id : null,
                    'brand_name' => (string) ($row->brand_name ?? '-'),
                    'family_code' => $familyCode,
                    'qty_sold' => (float) ($row->qty_sold ?? 0),
                    'line_total' => (float) ($row->line_total ?? 0),
                    'rate_percent' => (float) $resolvedRate['rate_percent'],
                    'rate_label' => $resolvedRate['rate_label'],
                    'rate_source' => $resolvedRate['rate_source'],
                    'is_unresolved' => (bool) $resolvedRate['is_unresolved'],
                ];
            })
            ->values();

        $rows = $this->allocateRevenueAndUnder($rows);

        return $this->attachNoteStatuses($rows)
            ->sortBy([
                fn ($row) => mb_strtolower((string) $row->sales_user_name, 'UTF-8'),
                fn ($row) => (string) $row->finalized_date,
                fn ($row) => mb_strtolower((string) $row->sales_order_number, 'UTF-8'),
                fn ($row) => mb_strtolower((string) $row->item_name, 'UTF-8'),
            ])
            ->values();
    }

    private function allocateRevenueAndUnder(Collection $rows): Collection
    {
        $rowsBySo = $rows->filter(fn ($row) => $row->sales_order_id)->groupBy('sales_order_id');

        return $rows->map(function ($row) use ($rowsBySo) {
            $revenue = 0.0;
            $underAllocated = 0.0;
            if ($row->sales_order_id) {
                $soRows = $rowsBySo->get($row->sales_order_id, collect());
                $lineTotalSum = (float) $soRows->sum('line_total');
                $taxableBase = max((float) ($row->sales_order_taxable_base ?? 0), 0);
                $underAmount = max((float) ($row->sales_order_under_amount ?? 0), 0);
                $share = $lineTotalSum > 0
                    ? max((float) $row->line_total, 0) / $lineTotalSum
                    : 0;

                if ($taxableBase > 0 && $lineTotalSum > 0) {
                    $revenue = round($taxableBase * $share, 2);
                } else {
                    $revenue = round(max((float) $row->line_total, 0), 2);
                }

                if ($underAmount > 0 && $share > 0) {
                    $underAllocated = round($underAmount * $share, 2);
                }
            } else {
                $revenue = round(max((float) $row->line_total, 0), 2);
            }

            $commissionableBase = max($revenue - $underAllocated, 0);
            $feeAmount = round($commissionableBase * ((float) $row->rate_percent / 100), 2);

            return (object) array_merge((array) $row, [
                'revenue' => $revenue,
                'under_allocated' => $underAllocated,
                'commissionable_base' => $commissionableBase,
                'fee_amount' => $feeAmount,
            ]);
        })->values();
    }

    private function attachNoteStatuses(Collection $rows): Collection
    {
        if (!Schema::hasTable('sales_commission_note_lines') || !Schema::hasTable('sales_commission_notes')) {
            return $rows->map(function ($row) {
                $row->source_status = 'available';
                $row->source_status_label = 'Available';
                $row->note_id = null;
                $row->note_number = null;
                $row->selectable = (bool) $row->sales_user_id;

                return $row;
            });
        }

        $noteMap = DB::table('sales_commission_note_lines as line')
            ->join('sales_commission_notes as note', 'note.id', '=', 'line.sales_commission_note_id')
            ->whereIn('line.source_key', $rows->pluck('source_key')->values())
            ->select('line.source_key', 'note.id as note_id', 'note.number as note_number', 'note.status as note_status')
            ->get()
            ->keyBy('source_key');

        return $rows->map(function ($row) use ($noteMap) {
            $note = $noteMap->get($row->source_key);
            $status = $note?->note_status === 'paid' ? 'in_paid_note' : ($note ? 'in_unpaid_note' : 'available');
            $row->source_status = $status;
            $row->source_status_label = match ($status) {
                'in_paid_note' => 'Paid',
                'in_unpaid_note' => 'In Unpaid Note',
                default => 'Available',
            };
            $row->note_id = $note->note_id ?? null;
            $row->note_number = $note->note_number ?? null;
            $row->selectable = $status === 'available' && (bool) $row->sales_user_id;

            return $row;
        })->values();
    }

    private function filterRowsByStatus(Collection $rows, string $status): Collection
    {
        if ($status === 'all') {
            return $rows;
        }

        return $rows->where('source_status', $status)->values();
    }

    private function salesUsersFromRows(Collection $rows): Collection
    {
        return $rows
            ->filter(fn ($row) => $row->sales_user_id)
            ->groupBy('sales_user_id')
            ->map(function (Collection $group) {
                $first = $group->first();

                return (object) [
                    'id' => $first->sales_user_id,
                    'name' => $first->sales_user_name,
                ];
            })
            ->sortBy(fn ($user) => mb_strtolower((string) $user->name, 'UTF-8'))
            ->values();
    }

    private function normalizeJsonArray(mixed $value): array
    {
        if (is_array($value)) {
            return array_values(array_filter(array_map(fn ($v) => trim((string) $v), $value)));
        }

        if (is_string($value) && $value !== '') {
            $decoded = json_decode($value, true);
            if (is_array($decoded)) {
                return array_values(array_filter(array_map(fn ($v) => trim((string) $v), $decoded)));
            }
        }

        return [];
    }

    private function projectScopeLabel(?string $scope): string
    {
        return match ($scope) {
            'fire_alarm' => 'Fire Alarm',
            'fire_hydrant' => 'Fire Hydrant',
            'maintenance' => 'Maintenance',
            default => '-',
        };
    }
}
