<?php

namespace App\Http\Controllers;

use App\Models\SalesCommissionNote;
use App\Models\User;
use App\Services\SalesCommissionNoteService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class SalesCommissionNoteController extends Controller
{
    public function __construct(
        private readonly SalesCommissionNoteService $salesCommissionNoteService
    ) {
    }

    public function index(Request $request)
    {
        $filters = $request->validate([
            'month' => ['nullable', 'date_format:Y-m'],
            'status' => ['nullable', Rule::in(['all', 'unpaid', 'paid'])],
            'sales_user_id' => ['nullable', 'integer', 'exists:users,id'],
        ]);

        $month = !empty($filters['month']) ? $filters['month'] : now()->format('Y-m');
        $status = $filters['status'] ?? 'all';
        $salesUserId = !empty($filters['sales_user_id']) ? (int) $filters['sales_user_id'] : null;
        $monthDate = Carbon::createFromFormat('Y-m', $month)->startOfMonth();

        $notes = SalesCommissionNote::query()
            ->with(['creator', 'salesUser'])
            ->withSum('lines as total_fee', 'fee_amount')
            ->withSum('lines as total_revenue', 'revenue')
            ->whereDate('month', $monthDate->toDateString())
            ->when($status !== 'all', fn ($query) => $query->where('status', $status))
            ->when($salesUserId, fn ($query) => $query->where('sales_user_id', $salesUserId))
            ->orderByDesc('note_date')
            ->orderByDesc('id')
            ->paginate($this->resolvePerPage())
            ->withQueryString();

        $salesUserIds = SalesCommissionNote::query()
            ->whereDate('month', $monthDate->toDateString())
            ->whereNotNull('sales_user_id')
            ->distinct()
            ->pluck('sales_user_id');

        $salesUsers = User::query()
            ->whereIn('id', $salesUserIds)
            ->orderBy('name')
            ->get(['id', 'name']);

        return view('sales_commission_notes.index', [
            'notes' => $notes,
            'filters' => [
                'month' => $monthDate,
                'status' => $status,
                'sales_user_id' => $salesUserId,
            ],
            'salesUsers' => $salesUsers,
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'month' => ['required', 'date_format:Y-m'],
            'sales_user_id' => ['nullable', 'integer', 'exists:users,id'],
            'row_status' => ['nullable', Rule::in(['all', 'available', 'in_unpaid_note', 'in_paid_note'])],
            'note_date' => ['required', 'date'],
            'notes' => ['nullable', 'string'],
            'source_keys' => ['required', 'array', 'min:1'],
            'source_keys.*' => ['required', 'string'],
        ]);

        $note = $this->salesCommissionNoteService->create(
            [
                'month' => $data['month'],
                'sales_user_id' => $data['sales_user_id'] ?? null,
                'row_status' => $data['row_status'] ?? 'all',
            ],
            $data['source_keys'],
            [
                'note_date' => $data['note_date'],
                'notes' => $data['notes'] ?? null,
            ]
        );

        return redirect()
            ->route('sales-commission-notes.show', $note)
            ->with('success', 'Sales commission note berhasil dibuat.');
    }

    public function show(SalesCommissionNote $salesCommissionNote)
    {
        $salesCommissionNote->load(['creator', 'salesUser', 'lines']);

        return view('sales_commission_notes.show', [
            'note' => $salesCommissionNote,
            'totals' => [
                'revenue' => (float) $salesCommissionNote->lines->sum('revenue'),
                'under' => (float) $salesCommissionNote->lines->sum('under_allocated'),
                'base' => (float) $salesCommissionNote->lines->sum('commissionable_base'),
                'fee' => (float) $salesCommissionNote->lines->sum('fee_amount'),
            ],
        ]);
    }

    public function markPaid(Request $request, SalesCommissionNote $salesCommissionNote)
    {
        $data = $request->validate([
            'paid_at' => ['required', 'date'],
        ]);

        $this->salesCommissionNoteService->markPaid($salesCommissionNote, (string) $data['paid_at']);

        return redirect()
            ->route('sales-commission-notes.show', $salesCommissionNote)
            ->with('success', 'Sales commission note ditandai paid.');
    }

    public function markUnpaid(SalesCommissionNote $salesCommissionNote)
    {
        $this->salesCommissionNoteService->markUnpaid($salesCommissionNote);

        return redirect()
            ->route('sales-commission-notes.show', $salesCommissionNote)
            ->with('success', 'Sales commission note dikembalikan ke unpaid.');
    }

    public function destroy(SalesCommissionNote $salesCommissionNote)
    {
        $this->salesCommissionNoteService->deleteUnpaid($salesCommissionNote);

        return redirect()
            ->route('sales-commission-notes.index', [
                'month' => optional($salesCommissionNote->month)->format('Y-m'),
                'status' => 'unpaid',
                'sales_user_id' => $salesCommissionNote->sales_user_id,
            ])
            ->with('success', 'Sales commission note dihapus.');
    }
}
