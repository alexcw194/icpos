<?php

namespace App\Http\Controllers;

use App\Models\ManufactureCommissionNote;
use App\Services\ManufactureCommissionNoteService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ManufactureCommissionNoteController extends Controller
{
    public function __construct(
        private readonly ManufactureCommissionNoteService $manufactureCommissionNoteService
    ) {
    }

    public function index(Request $request)
    {
        $filters = $request->validate([
            'month' => ['nullable', 'date_format:Y-m'],
            'status' => ['nullable', Rule::in(['all', 'unpaid', 'paid'])],
        ]);

        $month = !empty($filters['month']) ? $filters['month'] : now()->format('Y-m');
        $status = $filters['status'] ?? 'all';
        $monthDate = Carbon::createFromFormat('Y-m', $month)->startOfMonth();

        $notes = ManufactureCommissionNote::query()
            ->with('creator')
            ->withSum('lines as total_qty', 'qty')
            ->withSum('lines as total_fee', 'fee_amount')
            ->whereDate('month', $monthDate->toDateString())
            ->when($status !== 'all', fn ($query) => $query->where('status', $status))
            ->orderByDesc('note_date')
            ->orderByDesc('id')
            ->paginate($this->resolvePerPage())
            ->withQueryString();

        return view('manufacture_commission_notes.index', [
            'notes' => $notes,
            'filters' => [
                'month' => $monthDate,
                'status' => $status,
            ],
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'month' => ['required', 'date_format:Y-m'],
            'apar_fee_rate' => ['required', 'numeric', 'min:0'],
            'firehose_fee_rate' => ['required', 'numeric', 'min:0'],
            'note_date' => ['required', 'date'],
            'notes' => ['nullable', 'string'],
            'source_keys' => ['required', 'array', 'min:1'],
            'source_keys.*' => ['required', 'string'],
        ]);

        $note = $this->manufactureCommissionNoteService->create(
            [
                'month' => $data['month'],
                'apar_fee_rate' => $data['apar_fee_rate'],
                'firehose_fee_rate' => $data['firehose_fee_rate'],
            ],
            $data['source_keys'],
            [
                'note_date' => $data['note_date'],
                'notes' => $data['notes'] ?? null,
            ]
        );

        return redirect()
            ->route('manufacture-commission-notes.show', $note)
            ->with('success', 'Manufacture commission note berhasil dibuat.');
    }

    public function show(ManufactureCommissionNote $manufactureCommissionNote)
    {
        $manufactureCommissionNote->load(['creator', 'lines']);

        return view('manufacture_commission_notes.show', [
            'note' => $manufactureCommissionNote,
            'totals' => [
                'qty' => (float) $manufactureCommissionNote->lines->sum('qty'),
                'fee' => (float) $manufactureCommissionNote->lines->sum('fee_amount'),
            ],
        ]);
    }

    public function markPaid(Request $request, ManufactureCommissionNote $manufactureCommissionNote)
    {
        $data = $request->validate([
            'paid_at' => ['required', 'date'],
        ]);

        $this->manufactureCommissionNoteService->markPaid($manufactureCommissionNote, (string) $data['paid_at']);

        return redirect()
            ->route('manufacture-commission-notes.show', $manufactureCommissionNote)
            ->with('success', 'Manufacture commission note ditandai paid.');
    }

    public function markUnpaid(ManufactureCommissionNote $manufactureCommissionNote)
    {
        $this->manufactureCommissionNoteService->markUnpaid($manufactureCommissionNote);

        return redirect()
            ->route('manufacture-commission-notes.show', $manufactureCommissionNote)
            ->with('success', 'Manufacture commission note dikembalikan ke unpaid.');
    }

    public function destroy(ManufactureCommissionNote $manufactureCommissionNote)
    {
        $this->manufactureCommissionNoteService->deleteUnpaid($manufactureCommissionNote);

        return redirect()
            ->route('manufacture-commission-notes.index', [
                'month' => optional($manufactureCommissionNote->month)->format('Y-m'),
                'status' => 'unpaid',
            ])
            ->with('success', 'Manufacture commission note dihapus.');
    }
}
