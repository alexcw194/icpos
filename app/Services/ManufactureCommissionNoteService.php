<?php

namespace App\Services;

use App\Models\ManufactureCommissionNote;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ManufactureCommissionNoteService
{
    public function __construct(
        private readonly ManufactureFeeService $manufactureFeeService
    ) {
    }

    public function create(array $filters, array $sourceKeys, array $payload): ManufactureCommissionNote
    {
        $rows = $this->manufactureFeeService->availableSourceRowsForNote($filters, $sourceKeys);
        $uniqueSourceKeys = collect($sourceKeys)
            ->map(fn ($key) => trim((string) $key))
            ->filter(fn ($key) => $key !== '')
            ->unique()
            ->values();

        if ($uniqueSourceKeys->isEmpty()) {
            throw ValidationException::withMessages([
                'source_keys' => 'Pilih minimal satu pekerjaan komisi.',
            ]);
        }

        if ($rows->count() !== $uniqueSourceKeys->count()) {
            throw ValidationException::withMessages([
                'source_keys' => 'Sebagian pekerjaan sudah masuk note lain atau tidak lagi tersedia.',
            ]);
        }

        $noteDate = Carbon::parse((string) $payload['note_date'])->startOfDay();
        $month = Carbon::createFromFormat('Y-m', (string) $filters['month'])->startOfMonth();

        return DB::transaction(function () use ($rows, $payload, $noteDate, $month) {
            $note = ManufactureCommissionNote::create([
                'number' => $this->nextNumber($noteDate),
                'month' => $month->toDateString(),
                'status' => 'unpaid',
                'note_date' => $noteDate->toDateString(),
                'paid_at' => null,
                'notes' => $payload['notes'] ?? null,
                'created_by' => auth()->id(),
            ]);

            $note->lines()->createMany(
                $rows->map(fn ($row) => [
                    'category' => $row->category,
                    'item_id' => $row->item_id,
                    'customer_id' => $row->customer_id,
                    'month' => $month->toDateString(),
                    'source_key' => $row->source_key,
                    'qty' => $row->qty,
                    'fee_rate' => $row->fee_rate,
                    'fee_amount' => $row->fee_amount,
                    'item_name_snapshot' => $row->item_name,
                    'customer_name_snapshot' => $row->customer_name,
                ])->all()
            );

            return $note->load('lines', 'creator');
        });
    }

    public function markPaid(ManufactureCommissionNote $note, string $paidAt): void
    {
        $note->update([
            'status' => 'paid',
            'paid_at' => Carbon::parse($paidAt)->toDateString(),
        ]);
    }

    public function markUnpaid(ManufactureCommissionNote $note): void
    {
        $note->update([
            'status' => 'unpaid',
            'paid_at' => null,
        ]);
    }

    public function deleteUnpaid(ManufactureCommissionNote $note): void
    {
        if ($note->status !== 'unpaid') {
            throw ValidationException::withMessages([
                'note' => 'Hanya note unpaid yang bisa dihapus.',
            ]);
        }

        $note->delete();
    }

    private function nextNumber(Carbon $noteDate): string
    {
        $yearStart = $noteDate->copy()->startOfYear()->toDateString();
        $yearEnd = $noteDate->copy()->endOfYear()->toDateString();

        return DB::transaction(function () use ($noteDate, $yearStart, $yearEnd) {
            $lastNumber = ManufactureCommissionNote::query()
                ->whereBetween('note_date', [$yearStart, $yearEnd])
                ->lockForUpdate()
                ->orderByDesc('id')
                ->value('number');

            $lastSeq = 0;
            if (is_string($lastNumber) && preg_match('/(\d{5})$/', $lastNumber, $matches)) {
                $lastSeq = (int) $matches[1];
            }

            return sprintf('MCN/%d/%05d', (int) $noteDate->format('Y'), $lastSeq + 1);
        });
    }
}
