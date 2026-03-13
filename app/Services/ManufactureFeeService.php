<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ManufactureFeeService
{
    public function __construct(
        private readonly FirehoseCouplingDetector $firehoseCouplingDetector
    ) {
    }

    public function normalizeFilters(array $filters): array
    {
        $month = !empty($filters['month'])
            ? Carbon::createFromFormat('Y-m', (string) $filters['month'])->startOfMonth()
            : now()->startOfMonth();

        return [
            'month' => $month,
            'from' => $month->copy()->startOfMonth(),
            'to' => $month->copy()->endOfMonth(),
            'apar_fee_rate' => max(0, (float) ($filters['apar_fee_rate'] ?? 10000)),
            'firehose_fee_rate' => max(0, (float) ($filters['firehose_fee_rate'] ?? 15000)),
            'row_status' => in_array(($filters['row_status'] ?? 'all'), ['all', 'available', 'in_unpaid_note', 'in_paid_note'], true)
                ? (string) $filters['row_status']
                : 'all',
        ];
    }

    public function buildReport(array $filters): array
    {
        $normalized = $this->normalizeFilters($filters);
        $rows = $this->sourceRows($normalized);
        $filteredRows = $this->filterRowsByStatus($rows, $normalized['row_status']);

        $categories = collect([
            'apar_new' => 'APAR Baru by Item + Customer',
            'refill_tube' => 'Refill by Item + Customer',
            'firehose_coupling' => 'Firehose with Coupling by Item + Customer',
        ])->map(function (string $label, string $key) use ($filteredRows) {
            $categoryRows = $filteredRows->where('category', $key)->values();

            return [
                'key' => $key,
                'label' => $label,
                'rows' => $categoryRows,
                'qty_total' => (float) $categoryRows->sum('qty'),
                'fee_total' => (float) $categoryRows->sum('fee_amount'),
            ];
        })->values();

        $qtyApar = (float) $filteredRows->where('category', 'apar_new')->sum('qty');
        $qtyRefill = (float) $filteredRows->where('category', 'refill_tube')->sum('qty');
        $qtyFirehose = (float) $filteredRows->where('category', 'firehose_coupling')->sum('qty');
        $aparFee = (float) (($qtyApar + $qtyRefill) * $normalized['apar_fee_rate']);
        $firehoseFee = (float) ($qtyFirehose * $normalized['firehose_fee_rate']);

        return [
            'filters' => $normalized,
            'summary' => [
                'apar_new_qty' => $qtyApar,
                'refill_tube_qty' => $qtyRefill,
                'firehose_coupling_qty' => $qtyFirehose,
                'apar_fee_total' => $aparFee,
                'firehose_fee_total' => $firehoseFee,
                'grand_total' => $aparFee + $firehoseFee,
                'available_count' => $filteredRows->where('source_status', 'available')->count(),
            ],
            'categories' => $categories,
            'activity' => $this->manufactureActivity($normalized),
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
                if (!$row || $row->source_status !== 'available') {
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
                invoice.customer_id,
                customer.name as customer_name,
                COALESCE(direct_item.id, so_item.id) as item_id,
                COALESCE(direct_item.name, so_item.name, line.description) as item_name,
                COALESCE(direct_item.family_code, so_item.family_code) as family_code,
                COALESCE(line.qty, 0) as qty_sold,
                COALESCE(line.line_total, 0) as revenue
            ')
            ->get()
            ->filter(fn ($row) => trim((string) ($row->family_code ?? '')) !== '')
            ->values();

        $coveredInvoiceIds = $mappedRows->pluck('invoice_id')->unique()->values();

        $headerFallbackRows = $this->headerFallbackRows($filters, $coveredInvoiceIds);

        $mergedRows = $mappedRows->concat($headerFallbackRows)->values();
        $firehoseEligibleMap = $this->firehoseCouplingDetector->eligibleParentItemMap(
            $mergedRows->where('family_code', 'FIREHOSE')->pluck('item_id')->filter()->unique()->values()
        );

        $rows = $mergedRows
            ->map(function ($row) use ($filters, $firehoseEligibleMap) {
                $category = $this->resolveCategory((string) $row->family_code, (int) $row->item_id, $firehoseEligibleMap);
                if ($category === null) {
                    return null;
                }

                $feeRate = in_array($category, ['apar_new', 'refill_tube'], true)
                    ? $filters['apar_fee_rate']
                    : $filters['firehose_fee_rate'];

                $monthKey = $filters['month']->format('Y-m');
                $sourceKey = implode('|', [
                    $monthKey,
                    $category,
                    (int) $row->item_id,
                    (int) $row->customer_id,
                ]);

                return (object) [
                    'source_key' => $sourceKey,
                    'month_key' => $monthKey,
                    'category' => $category,
                    'item_id' => (int) $row->item_id,
                    'customer_id' => (int) $row->customer_id,
                    'item_name' => (string) $row->item_name,
                    'customer_name' => (string) $row->customer_name,
                    'qty' => (float) $row->qty_sold,
                    'revenue' => (float) $row->revenue,
                    'fee_rate' => (float) $feeRate,
                    'fee_amount' => (float) $row->qty_sold * (float) $feeRate,
                ];
            })
            ->filter()
            ->groupBy('source_key')
            ->map(function (Collection $groupedRows) {
                $first = $groupedRows->first();

                return (object) [
                    'source_key' => $first->source_key,
                    'month_key' => $first->month_key,
                    'category' => $first->category,
                    'category_label' => $this->categoryLabel($first->category),
                    'item_id' => $first->item_id,
                    'customer_id' => $first->customer_id,
                    'item_name' => $first->item_name,
                    'customer_name' => $first->customer_name,
                    'qty' => (float) $groupedRows->sum('qty'),
                    'revenue' => (float) $groupedRows->sum('revenue'),
                    'fee_rate' => (float) $first->fee_rate,
                    'fee_amount' => (float) $groupedRows->sum('fee_amount'),
                ];
            })
            ->values();

        return $this->attachNoteStatuses($rows)
            ->sortBy([
                fn ($row) => $this->categorySortOrder($row->category),
                fn ($row) => mb_strtolower($row->item_name, 'UTF-8'),
                fn ($row) => mb_strtolower($row->customer_name, 'UTF-8'),
            ])
            ->values();
    }

    private function headerFallbackRows(array $filters, Collection $coveredInvoiceIds): Collection
    {
        $headerInvoices = DB::table('invoices as invoice')
            ->join('customers as customer', 'customer.id', '=', 'invoice.customer_id')
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
                'invoice.customer_id',
                'customer.name as customer_name',
                'invoice.total',
            ]);

        if ($headerInvoices->isEmpty()) {
            return collect();
        }

        $salesOrderIds = $headerInvoices->pluck('sales_order_id')->filter()->unique()->values();

        $salesOrderLines = DB::table('sales_order_lines as line')
            ->join('items as item', 'item.id', '=', 'line.item_id')
            ->whereIn('line.sales_order_id', $salesOrderIds)
            ->whereNotNull('item.family_code')
            ->where('item.family_code', '!=', '')
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
                    'sales_order_id' => $invoice->sales_order_id,
                    'customer_id' => $invoice->customer_id,
                    'customer_name' => $invoice->customer_name,
                    'item_id' => (int) $line->item_id,
                    'item_name' => (string) $line->item_name,
                    'family_code' => (string) $line->family_code,
                    'qty_sold' => (float) $line->qty_ordered,
                    'revenue' => (float) $invoice->total * $share,
                ];
            });
        })->values();
    }

    private function attachNoteStatuses(Collection $rows): Collection
    {
        if ($rows->isEmpty()) {
            return collect();
        }

        $refs = DB::table('manufacture_commission_note_lines as line')
            ->join('manufacture_commission_notes as note', 'note.id', '=', 'line.manufacture_commission_note_id')
            ->whereIn('line.source_key', $rows->pluck('source_key')->all())
            ->get([
                'line.source_key',
                'note.id as note_id',
                'note.number as note_number',
                'note.status as note_status',
            ])
            ->keyBy('source_key');

        return $rows->map(function ($row) use ($refs) {
            $ref = $refs->get($row->source_key);
            $sourceStatus = match ($ref->note_status ?? null) {
                'paid' => 'in_paid_note',
                'unpaid' => 'in_unpaid_note',
                default => 'available',
            };

            $row->source_status = $sourceStatus;
            $row->source_status_label = match ($sourceStatus) {
                'in_paid_note' => 'Paid',
                'in_unpaid_note' => 'In Unpaid Note',
                default => 'Available',
            };
            $row->note_id = $ref->note_id ?? null;
            $row->note_number = $ref->note_number ?? null;
            $row->selectable = $sourceStatus === 'available';

            return $row;
        });
    }

    private function filterRowsByStatus(Collection $rows, string $rowStatus): Collection
    {
        if ($rowStatus === 'all') {
            return $rows->values();
        }

        return $rows->where('source_status', $rowStatus)->values();
    }

    private function manufactureActivity(array $filters): array
    {
        $jobs = DB::table('manufacture_jobs as job')
            ->join('items as item', 'item.id', '=', 'job.parent_item_id')
            ->leftJoin('users as operator', 'operator.id', '=', 'job.produced_by')
            ->whereBetween(
                DB::raw('DATE(COALESCE(job.produced_at, job.created_at))'),
                [$filters['from']->toDateString(), $filters['to']->toDateString()]
            )
            ->get([
                'job.id',
                'job.parent_item_id',
                'item.family_code',
                'operator.name as operator_name',
            ]);

        $firehoseEligibleMap = $this->firehoseCouplingDetector->eligibleParentItemMap(
            $jobs->where('family_code', 'FIREHOSE')->pluck('parent_item_id')->filter()->unique()->values()
        );

        $classifiedJobs = $jobs->map(function ($job) use ($firehoseEligibleMap) {
            $team = null;

            if (in_array((string) $job->family_code, ['APAR', 'REFILL'], true)) {
                $team = 'apar';
            } elseif ((string) $job->family_code === 'FIREHOSE' && isset($firehoseEligibleMap[(int) $job->parent_item_id])) {
                $team = 'selang';
            }

            if ($team === null) {
                return null;
            }

            return (object) [
                'team' => $team,
                'operator_name' => (string) ($job->operator_name ?? '-'),
            ];
        })->filter()->values();

        return [
            'teams' => [
                [
                    'key' => 'apar',
                    'label' => 'Tim APAR',
                    'job_count' => $classifiedJobs->where('team', 'apar')->count(),
                    'operators' => $this->operatorCounts($classifiedJobs->where('team', 'apar')),
                ],
                [
                    'key' => 'selang',
                    'label' => 'Tim Selang',
                    'job_count' => $classifiedJobs->where('team', 'selang')->count(),
                    'operators' => $this->operatorCounts($classifiedJobs->where('team', 'selang')),
                ],
            ],
        ];
    }

    private function operatorCounts(Collection $jobs): Collection
    {
        return $jobs
            ->groupBy('operator_name')
            ->map(fn (Collection $group, string $name) => (object) [
                'name' => $name,
                'job_count' => $group->count(),
            ])
            ->sortByDesc('job_count')
            ->values();
    }

    private function resolveCategory(string $familyCode, int $itemId, array $firehoseEligibleMap): ?string
    {
        return match ($familyCode) {
            'APAR' => 'apar_new',
            'REFILL' => 'refill_tube',
            'FIREHOSE' => isset($firehoseEligibleMap[$itemId]) ? 'firehose_coupling' : null,
            default => null,
        };
    }

    private function categoryLabel(string $category): string
    {
        return match ($category) {
            'apar_new' => 'APAR Baru',
            'refill_tube' => 'Refill Tabung',
            'firehose_coupling' => 'Firehose with Coupling',
            default => $category,
        };
    }

    private function categorySortOrder(string $category): int
    {
        return match ($category) {
            'apar_new' => 1,
            'refill_tube' => 2,
            'firehose_coupling' => 3,
            default => 99,
        };
    }
}
