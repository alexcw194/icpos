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
                'invoice_count' => $rows->pluck('invoice_id')->filter()->unique()->count(),
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
        $mappedRows = DB::table('invoice_lines as line')
            ->join('invoices as invoice', 'invoice.id', '=', 'line.invoice_id')
            ->join('customers as customer', 'customer.id', '=', 'invoice.customer_id')
            ->leftJoin('sales_orders as so', 'so.id', '=', DB::raw('COALESCE(line.sales_order_id, invoice.sales_order_id)'))
            ->leftJoin('projects as project', 'project.id', '=', 'so.project_id')
            ->leftJoin('sales_order_lines as so_line', 'so_line.id', '=', 'line.sales_order_line_id')
            ->leftJoin('items as direct_item', 'direct_item.id', '=', 'line.item_id')
            ->leftJoin('items as so_item', 'so_item.id', '=', 'so_line.item_id')
            ->leftJoin('brands as direct_brand', 'direct_brand.id', '=', 'direct_item.brand_id')
            ->leftJoin('brands as so_brand', 'so_brand.id', '=', 'so_item.brand_id')
            ->leftJoin('users as sales_user', 'sales_user.id', '=', 'so.sales_user_id')
            ->whereIn('invoice.status', ['posted', 'paid'])
            ->whereBetween(
                DB::raw('DATE(COALESCE(invoice.date, invoice.posted_at, invoice.created_at))'),
                [$filters['from']->toDateString(), $filters['to']->toDateString()]
            )
            ->selectRaw('
                invoice.id as invoice_id,
                line.id as invoice_line_id,
                invoice.number as invoice_number,
                DATE(COALESCE(invoice.date, invoice.posted_at, invoice.created_at)) as invoice_date,
                COALESCE(line.sales_order_id, invoice.sales_order_id) as sales_order_id,
                so.so_number as sales_order_number,
                so.po_type,
                so.project_id,
                so.taxable_base as sales_order_taxable_base,
                so.sales_user_id,
                sales_user.name as sales_user_name,
                invoice.customer_id,
                customer.name as customer_name,
                COALESCE(direct_item.id, so_item.id) as item_id,
                COALESCE(so_line.po_item_name, direct_item.name, so_item.name, line.description) as item_name,
                COALESCE(direct_item.brand_id, so_item.brand_id) as brand_id,
                COALESCE(direct_brand.name, so_brand.name) as brand_name,
                COALESCE(direct_item.family_code, so_item.family_code) as family_code,
                COALESCE(line.qty, 0) as qty_sold,
                COALESCE(line.line_total, 0) as gross_revenue,
                COALESCE(invoice.discount, 0) as invoice_discount,
                project.systems_json as project_systems_json
            ')
            ->get()
            ->filter(fn ($row) => (int) ($row->item_id ?? 0) > 0)
            ->values();

        $coveredInvoiceIds = $mappedRows->pluck('invoice_id')->unique()->values();
        $headerFallbackRows = $this->headerFallbackRows($filters, $coveredInvoiceIds);

        $rows = $this->applyInvoiceHeaderDiscount($mappedRows)
            ->concat($headerFallbackRows)
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
                    'source_key' => (string) $row->source_key,
                    'invoice_id' => (int) $row->invoice_id,
                    'invoice_line_id' => $row->invoice_line_id ? (int) $row->invoice_line_id : null,
                    'invoice_number' => (string) ($row->invoice_number ?? '-'),
                    'invoice_date' => $row->invoice_date,
                    'sales_order_id' => $row->sales_order_id ? (int) $row->sales_order_id : null,
                    'sales_order_number' => (string) ($row->sales_order_number ?? '-'),
                    'po_type' => (string) ($row->po_type ?? 'goods'),
                    'project_id' => $row->project_id ? (int) $row->project_id : null,
                    'sales_order_taxable_base' => (float) ($row->sales_order_taxable_base ?? 0),
                    'project_scope' => $resolvedRate['project_scope'],
                    'project_scope_label' => $this->projectScopeLabel($resolvedRate['project_scope']),
                    'sales_user_id' => $row->sales_user_id ? (int) $row->sales_user_id : null,
                    'sales_user_name' => (string) ($row->sales_user_name ?? '-'),
                    'customer_id' => (int) $row->customer_id,
                    'customer_name' => (string) ($row->customer_name ?? '-'),
                    'item_id' => (int) $row->item_id,
                    'item_name' => (string) ($row->item_name ?? '-'),
                    'brand_id' => $row->brand_id ? (int) $row->brand_id : null,
                    'brand_name' => (string) ($row->brand_name ?? '-'),
                    'family_code' => $familyCode,
                    'qty_sold' => (float) ($row->qty_sold ?? 0),
                    'revenue' => round((float) ($row->revenue ?? 0), 2),
                    'rate_percent' => (float) $resolvedRate['rate_percent'],
                    'rate_label' => $resolvedRate['rate_label'],
                    'rate_source' => $resolvedRate['rate_source'],
                    'is_unresolved' => (bool) $resolvedRate['is_unresolved'],
                ];
            })
            ->values();

        $rows = $this->allocateUnder($rows);

        return $this->attachNoteStatuses($rows)
            ->sortBy([
                fn ($row) => mb_strtolower((string) $row->sales_user_name, 'UTF-8'),
                fn ($row) => (string) $row->invoice_date,
                fn ($row) => mb_strtolower((string) $row->invoice_number, 'UTF-8'),
                fn ($row) => mb_strtolower((string) $row->item_name, 'UTF-8'),
            ])
            ->values();
    }

    private function headerFallbackRows(array $filters, Collection $coveredInvoiceIds): Collection
    {
        $headerInvoices = DB::table('invoices as invoice')
            ->join('customers as customer', 'customer.id', '=', 'invoice.customer_id')
            ->leftJoin('sales_orders as so', 'so.id', '=', 'invoice.sales_order_id')
            ->leftJoin('projects as project', 'project.id', '=', 'so.project_id')
            ->leftJoin('users as sales_user', 'sales_user.id', '=', 'so.sales_user_id')
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
                'invoice.id as invoice_id',
                'invoice.number as invoice_number',
                DB::raw('DATE(COALESCE(invoice.date, invoice.posted_at, invoice.created_at)) as invoice_date'),
                'invoice.sales_order_id',
                'invoice.customer_id',
                'customer.name as customer_name',
                'so.so_number as sales_order_number',
                'so.po_type',
                'so.project_id',
                'so.taxable_base as sales_order_taxable_base',
                'so.sales_user_id',
                'sales_user.name as sales_user_name',
                'project.systems_json as project_systems_json',
                'invoice.subtotal',
                'invoice.discount',
            ]);

        if ($headerInvoices->isEmpty()) {
            return collect();
        }

        $salesOrderIds = $headerInvoices->pluck('sales_order_id')->filter()->unique()->values();

        $salesOrderLines = DB::table('sales_order_lines as line')
            ->join('items as item', 'item.id', '=', 'line.item_id')
            ->leftJoin('brands as brand', 'brand.id', '=', 'item.brand_id')
            ->whereIn('line.sales_order_id', $salesOrderIds)
            ->get([
                'line.id',
                'line.sales_order_id',
                'line.qty_ordered',
                'line.line_total',
                'line.po_item_name',
                'line.name',
                'item.id as item_id',
                'item.name as item_name',
                'item.brand_id',
                'brand.name as brand_name',
                'item.family_code',
            ])
            ->groupBy('sales_order_id');

        return $headerInvoices->flatMap(function ($invoice) use ($salesOrderLines) {
            $lines = $salesOrderLines->get($invoice->sales_order_id, collect())
                ->filter(fn ($line) => (int) ($line->item_id ?? 0) > 0)
                ->values();
            $denominator = (float) $lines->sum(fn ($line) => (float) $line->line_total);
            $netRevenue = max((float) $invoice->subtotal - (float) $invoice->discount, 0);

            if ($lines->isEmpty() || $denominator <= 0 || $netRevenue <= 0) {
                return [];
            }

            return $lines->map(function ($line) use ($invoice, $denominator, $netRevenue) {
                $share = (float) $line->line_total / $denominator;

                return (object) [
                    'source_key' => sprintf('invoice-header|%d|so-line|%d', (int) $invoice->invoice_id, (int) $line->id),
                    'invoice_id' => (int) $invoice->invoice_id,
                    'invoice_line_id' => null,
                    'invoice_number' => $invoice->invoice_number,
                    'invoice_date' => $invoice->invoice_date,
                    'sales_order_id' => (int) $invoice->sales_order_id,
                    'sales_order_number' => $invoice->sales_order_number,
                    'po_type' => $invoice->po_type,
                    'project_id' => $invoice->project_id,
                    'sales_order_taxable_base' => (float) ($invoice->sales_order_taxable_base ?? 0),
                    'project_systems_json' => $invoice->project_systems_json,
                    'sales_user_id' => $invoice->sales_user_id,
                    'sales_user_name' => $invoice->sales_user_name,
                    'customer_id' => (int) $invoice->customer_id,
                    'customer_name' => $invoice->customer_name,
                    'item_id' => (int) $line->item_id,
                    'item_name' => $line->po_item_name ?: ($line->item_name ?: $line->name),
                    'brand_id' => $line->brand_id ? (int) $line->brand_id : null,
                    'brand_name' => $line->brand_name,
                    'family_code' => $line->family_code,
                    'qty_sold' => (float) $line->qty_ordered,
                    'revenue' => round($netRevenue * $share, 2),
                ];
            })->all();
        })->values();
    }

    private function applyInvoiceHeaderDiscount(Collection $rows): Collection
    {
        return $rows->groupBy('invoice_id')->flatMap(function (Collection $invoiceRows) {
            $invoiceDiscount = (float) ($invoiceRows->first()->invoice_discount ?? 0);
            $denominator = (float) $invoiceRows->sum(fn ($row) => max((float) ($row->gross_revenue ?? 0), 0));

            return $invoiceRows->map(function ($row) use ($invoiceDiscount, $denominator) {
                $grossRevenue = max((float) ($row->gross_revenue ?? 0), 0);
                $shareDiscount = $invoiceDiscount > 0 && $denominator > 0
                    ? $invoiceDiscount * ($grossRevenue / $denominator)
                    : 0;
                $projectSystems = $this->normalizeJsonArray($row->project_systems_json ?? []);

                return (object) [
                    'source_key' => sprintf('invoice-line|%d', (int) $row->invoice_line_id),
                    'invoice_id' => (int) $row->invoice_id,
                    'invoice_line_id' => (int) $row->invoice_line_id,
                    'invoice_number' => $row->invoice_number,
                    'invoice_date' => $row->invoice_date,
                    'sales_order_id' => $row->sales_order_id ? (int) $row->sales_order_id : null,
                    'sales_order_number' => $row->sales_order_number,
                    'po_type' => $row->po_type,
                    'project_id' => $row->project_id ? (int) $row->project_id : null,
                    'sales_order_taxable_base' => (float) ($row->sales_order_taxable_base ?? 0),
                    'project_systems_json' => $projectSystems,
                    'sales_user_id' => $row->sales_user_id ? (int) $row->sales_user_id : null,
                    'sales_user_name' => $row->sales_user_name,
                    'customer_id' => (int) $row->customer_id,
                    'customer_name' => $row->customer_name,
                    'item_id' => (int) $row->item_id,
                    'item_name' => $row->item_name,
                    'brand_id' => $row->brand_id ? (int) $row->brand_id : null,
                    'brand_name' => $row->brand_name,
                    'family_code' => $row->family_code,
                    'qty_sold' => (float) $row->qty_sold,
                    'revenue' => round(max($grossRevenue - $shareDiscount, 0), 2),
                ];
            })->all();
        })->values();
    }

    private function allocateUnder(Collection $rows): Collection
    {
        $rowsBySo = $rows->filter(fn ($row) => $row->sales_order_id)->groupBy('sales_order_id');
        $soIds = $rowsBySo->keys()->map(fn ($id) => (int) $id)->values();
        $underMap = $soIds->isEmpty()
            ? collect()
            : DB::table('sales_orders')
                ->whereIn('id', $soIds)
                ->get(['id', 'under_amount', 'taxable_base'])
                ->keyBy('id');

        return $rows->map(function ($row) use ($rowsBySo, $underMap) {
            $underAllocated = 0.0;
            if ($row->sales_order_id) {
                $soRows = $rowsBySo->get($row->sales_order_id, collect());
                $soSummary = $underMap->get($row->sales_order_id);
                $soRevenueTotal = max((float) ($soSummary->taxable_base ?? 0), (float) $soRows->sum('revenue'));
                $soUnderAmount = (float) ($soSummary->under_amount ?? 0);

                if ($soUnderAmount > 0 && $soRevenueTotal > 0) {
                    $underAllocated = round($soUnderAmount * (((float) $row->revenue) / $soRevenueTotal), 2);
                }
            }

            $commissionableBase = max((float) $row->revenue - $underAllocated, 0);
            $feeAmount = round($commissionableBase * ((float) $row->rate_percent / 100), 2);

            return (object) array_merge((array) $row, [
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
