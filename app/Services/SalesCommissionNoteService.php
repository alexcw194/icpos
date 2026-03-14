<?php

namespace App\Services;

use App\Models\SalesCommissionNote;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class SalesCommissionNoteService
{
    public function __construct(
        private readonly SalesCommissionFeeService $salesCommissionFeeService,
        private readonly SalesCommissionSyncService $salesCommissionSyncService,
    ) {
    }

    public function create(array $filters, array $sourceKeys, array $payload): SalesCommissionNote
    {
        if (!Schema::hasTable('sales_commission_notes') || !Schema::hasTable('sales_commission_note_lines')) {
            throw ValidationException::withMessages([
                'source_keys' => 'Sales Commission Notes belum aktif. Jalankan migration terbaru terlebih dahulu.',
            ]);
        }

        $rows = $this->salesCommissionFeeService->availableSourceRowsForNote($filters, $sourceKeys);
        $uniqueSourceKeys = collect($sourceKeys)
            ->map(fn ($key) => trim((string) $key))
            ->filter(fn ($key) => $key !== '')
            ->unique()
            ->values();

        if ($uniqueSourceKeys->isEmpty()) {
            throw ValidationException::withMessages([
                'source_keys' => 'Pilih minimal satu row komisi.',
            ]);
        }

        $resolvedSourceKeys = $rows
            ->map(function ($row) {
                if ($row->sales_order_id) {
                    return 'sales-order|'.$row->sales_order_id;
                }

                return $row->source_key;
            })
            ->filter()
            ->unique()
            ->values();

        if ($resolvedSourceKeys->count() !== $uniqueSourceKeys->count()) {
            throw ValidationException::withMessages([
                'source_keys' => 'Sebagian row sudah masuk note lain atau tidak bisa dipilih.',
            ]);
        }

        $salesUserIds = $rows->pluck('sales_user_id')->filter()->unique()->values();
        if ($salesUserIds->count() !== 1) {
            throw ValidationException::withMessages([
                'source_keys' => 'Commission note hanya boleh berisi 1 salesperson.',
            ]);
        }

        $month = Carbon::createFromFormat('Y-m', (string) $filters['month'])->startOfMonth();
        $noteDate = Carbon::parse((string) $payload['note_date'])->startOfDay();
        $supportsFreelanceSnapshot = $this->supportsFreelanceSnapshotColumns();

        $note = DB::transaction(function () use ($rows, $month, $noteDate, $payload, $salesUserIds, $supportsFreelanceSnapshot) {
            $note = SalesCommissionNote::create([
                'number' => $this->nextNumber($noteDate),
                'month' => $month->toDateString(),
                'sales_user_id' => (int) $salesUserIds->first(),
                'status' => 'unpaid',
                'note_date' => $noteDate->toDateString(),
                'paid_at' => null,
                'notes' => $payload['notes'] ?? null,
                'created_by' => auth()->id(),
            ]);

            $note->lines()->createMany(
                $rows->map(function ($row) use ($month, $supportsFreelanceSnapshot) {
                    $payload = [
                    'source_key' => $row->source_key,
                    'invoice_id' => $row->invoice_id,
                    'invoice_line_id' => $row->invoice_line_id,
                    'sales_order_id' => $row->sales_order_id,
                    'sales_user_id' => $row->sales_user_id,
                    'item_id' => $row->item_id,
                    'customer_id' => $row->customer_id,
                    'project_scope' => $row->project_scope,
                    'month' => $month->toDateString(),
                    'revenue' => $row->revenue,
                    'under_allocated' => $row->under_allocated,
                    'commissionable_base' => $row->commissionable_base,
                    'rate_percent' => $row->rate_percent,
                    'fee_amount' => $row->fee_amount,
                    'invoice_number_snapshot' => null,
                    'sales_order_number_snapshot' => $row->sales_order_number,
                    'salesperson_name_snapshot' => $row->sales_user_name,
                    'item_name_snapshot' => $row->item_name,
                    'customer_name_snapshot' => $row->customer_name,
                    ];

                    if ($supportsFreelanceSnapshot) {
                        $payload['commission_mode'] = $row->commission_mode ?? 'percentage';
                        $payload['basis_unit_price_snapshot'] = $row->basis_unit_price_snapshot;
                        $payload['basis_net_amount'] = $row->basis_net_amount;
                        $payload['actual_net_amount'] = $row->actual_net_amount;
                        $payload['formula_label_snapshot'] = $row->formula_label ?? $row->rate_label;
                    }

                    return $payload;
                })->all()
            );

            $this->salesCommissionSyncService->syncSalesOrders($rows->pluck('sales_order_id')->all());

            return $note->load(['creator', 'salesUser', 'lines']);
        });

        return $note;
    }

    public function markPaid(SalesCommissionNote $note, string $paidAt): void
    {
        DB::transaction(function () use ($note, $paidAt) {
            $note->update([
                'status' => 'paid',
                'paid_at' => Carbon::parse($paidAt)->toDateString(),
            ]);

            $this->salesCommissionSyncService->syncSalesOrders($note->lines()->pluck('sales_order_id')->all());
        });
    }

    public function markUnpaid(SalesCommissionNote $note): void
    {
        DB::transaction(function () use ($note) {
            $note->update([
                'status' => 'unpaid',
                'paid_at' => null,
            ]);

            $this->salesCommissionSyncService->syncSalesOrders($note->lines()->pluck('sales_order_id')->all());
        });
    }

    public function deleteUnpaid(SalesCommissionNote $note): void
    {
        if ($note->status !== 'unpaid') {
            throw ValidationException::withMessages([
                'note' => 'Hanya note unpaid yang bisa dihapus.',
            ]);
        }

        $salesOrderIds = $note->lines()->pluck('sales_order_id')->all();

        DB::transaction(function () use ($note, $salesOrderIds) {
            $note->delete();
            $this->salesCommissionSyncService->syncSalesOrders($salesOrderIds);
        });
    }

    private function nextNumber(Carbon $noteDate): string
    {
        $yearStart = $noteDate->copy()->startOfYear()->toDateString();
        $yearEnd = $noteDate->copy()->endOfYear()->toDateString();

        return DB::transaction(function () use ($yearStart, $yearEnd, $noteDate) {
            $lastNumber = SalesCommissionNote::query()
                ->whereBetween('note_date', [$yearStart, $yearEnd])
                ->lockForUpdate()
                ->orderByDesc('id')
                ->value('number');

            $lastSeq = 0;
            if (is_string($lastNumber) && preg_match('/(\d{5})$/', $lastNumber, $matches)) {
                $lastSeq = (int) $matches[1];
            }

            return sprintf('SCN/%d/%05d', (int) $noteDate->format('Y'), $lastSeq + 1);
        });
    }

    private function supportsFreelanceSnapshotColumns(): bool
    {
        if (!Schema::hasTable('sales_commission_note_lines')) {
            return false;
        }

        foreach ([
            'commission_mode',
            'basis_unit_price_snapshot',
            'basis_net_amount',
            'actual_net_amount',
            'formula_label_snapshot',
        ] as $column) {
            if (!Schema::hasColumn('sales_commission_note_lines', $column)) {
                return false;
            }
        }

        return true;
    }
}
