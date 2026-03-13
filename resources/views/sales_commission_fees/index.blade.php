@extends('layouts.tabler')

@section('title', 'Sales Commission')

@section('content')
@php
  $money = fn ($value) => 'Rp ' . number_format((float) $value, 2, ',', '.');
  $percent = fn ($value) => number_format((float) $value, 2, ',', '.') . '%';
@endphp

<div class="container-xl">
  <div class="page-header d-print-none">
    <div class="row align-items-center">
      <div class="col">
        <div class="page-pretitle">Sales</div>
        <h2 class="page-title">Sales Commission</h2>
        <div class="text-muted">Monthly commission fee per finalized sales order. Item-level calculation is available from the detail modal.</div>
      </div>
    </div>
  </div>

  @if($errors->any())
    <div class="alert alert-danger mb-3">
      <ul class="mb-0 ps-3">
        @foreach($errors->all() as $error)
          <li>{{ $error }}</li>
        @endforeach
      </ul>
    </div>
  @endif

  <div class="card mb-3">
    <div class="card-body">
      <form method="GET" class="row g-2 align-items-end">
        <div class="col-md-3">
          <label class="form-label">Month</label>
          <input type="month" name="month" class="form-control" value="{{ $filters['month']->format('Y-m') }}">
        </div>
        <div class="col-md-3">
          <label class="form-label">Salesperson</label>
          <select name="sales_user_id" class="form-select">
            <option value="">All Salesperson</option>
            @foreach($salesUsers as $salesUser)
              <option value="{{ $salesUser->id }}" @selected((int) ($filters['sales_user_id'] ?? 0) === (int) $salesUser->id)>{{ $salesUser->name }}</option>
            @endforeach
          </select>
        </div>
        <div class="col-md-3">
          <label class="form-label">Source Status</label>
          <select name="row_status" class="form-select">
            <option value="all" @selected($filters['row_status'] === 'all')>All</option>
            <option value="available" @selected($filters['row_status'] === 'available')>Available</option>
            <option value="in_unpaid_note" @selected($filters['row_status'] === 'in_unpaid_note')>In Unpaid Note</option>
            <option value="in_paid_note" @selected($filters['row_status'] === 'in_paid_note')>Paid</option>
          </select>
        </div>
        <div class="col-md-1">
          <button class="btn btn-primary w-100">Apply</button>
        </div>
        <div class="col-md-2">
          <a href="{{ route('sales-commission-fees.index') }}" class="btn btn-outline-secondary w-100">Reset</a>
        </div>
      </form>
    </div>
  </div>

  <div class="row row-cards mb-3">
    <div class="col-6 col-md-2">
      <div class="card card-sm"><div class="card-body"><div class="subheader">Revenue</div><div class="h3 m-0">{{ $money($summary['revenue_total']) }}</div></div></div>
    </div>
    <div class="col-6 col-md-2">
      <div class="card card-sm"><div class="card-body"><div class="subheader">Under Allocated</div><div class="h3 m-0">{{ $money($summary['under_total']) }}</div></div></div>
    </div>
    <div class="col-6 col-md-2">
      <div class="card card-sm"><div class="card-body"><div class="subheader">Commissionable</div><div class="h3 m-0">{{ $money($summary['commissionable_total']) }}</div></div></div>
    </div>
    <div class="col-6 col-md-2">
      <div class="card card-sm"><div class="card-body"><div class="subheader">Fee Total</div><div class="h3 m-0">{{ $money($summary['fee_total']) }}</div></div></div>
    </div>
    <div class="col-6 col-md-2">
      <div class="card card-sm"><div class="card-body"><div class="subheader">Rows / SO</div><div class="h3 m-0">{{ number_format($summary['row_count'], 0, ',', '.') }} / {{ number_format($summary['sales_order_count'], 0, ',', '.') }}</div></div></div>
    </div>
    <div class="col-6 col-md-2">
      <div class="card card-sm"><div class="card-body"><div class="subheader">Warnings</div><div class="h3 m-0">{{ number_format($summary['unresolved_count'] + $summary['unassigned_sales_count'], 0, ',', '.') }}</div></div></div>
    </div>
  </div>

  @if($summary['unresolved_count'] > 0 || $summary['unassigned_sales_count'] > 0)
    <div class="alert alert-warning mb-3">
      @if($summary['unresolved_count'] > 0)
        <div>{{ number_format($summary['unresolved_count'], 0, ',', '.') }} row project tidak bisa diklasifikasikan spesifik, jadi fallback ke rule generic.</div>
      @endif
      @if($summary['unassigned_sales_count'] > 0)
        <div>{{ number_format($summary['unassigned_sales_count'], 0, ',', '.') }} SO tidak punya salesperson, jadi tidak bisa dipilih ke commission note.</div>
      @endif
    </div>
  @endif

  @unless($features['commission_notes_ready'] ?? false)
    <div class="alert alert-warning mb-3">
      Sales Commission Notes belum aktif di server ini. Jalankan migration terbaru untuk mengaktifkan create note dan status paid/unpaid.
    </div>
  @endunless

  <form method="POST" action="{{ route('sales-commission-notes.store') }}" id="sales-commission-note-form">
    @csrf
    <input type="hidden" name="month" value="{{ $filters['month']->format('Y-m') }}">
    <input type="hidden" name="sales_user_id" value="{{ $filters['sales_user_id'] }}">
    <input type="hidden" name="row_status" value="{{ $filters['row_status'] }}">

    <div class="card mb-3" id="commission-note-action" style="display:none;">
      <div class="card-body">
        <div class="row g-2 align-items-end">
          <div class="col-md-2">
            <label class="form-label">Note Date</label>
            <input type="date" name="note_date" class="form-control" value="{{ old('note_date', now()->toDateString()) }}" required>
          </div>
          <div class="col-md-7">
            <label class="form-label">Notes</label>
            <input type="text" name="notes" class="form-control" value="{{ old('notes') }}" placeholder="Optional note for this commission batch">
          </div>
          <div class="col-md-3 text-end">
            <div class="text-muted small mb-1"><span id="selected-count">0</span> SO dipilih</div>
            <div class="text-muted small mb-2">Salesperson: <span id="selected-salesperson">-</span></div>
            <button type="submit" class="btn btn-primary w-100" @disabled(!($features['commission_notes_ready'] ?? false))>Create Commission Note</button>
          </div>
        </div>
      </div>
    </div>

    <div class="card">
      <div class="table-responsive">
        <table class="table table-sm table-vcenter card-table">
          <thead>
            <tr>
              <th style="width:36px;"><input type="checkbox" class="form-check-input" id="select-all-rows"></th>
              <th>Salesperson</th>
              <th>SO</th>
              <th>SO Type</th>
              <th>Customer</th>
              <th class="text-end">Items</th>
              <th>Project/System</th>
              <th class="text-end">Revenue</th>
              <th class="text-end">Under</th>
              <th class="text-end">Base</th>
              <th class="text-end">Fee</th>
              <th>Status</th>
              <th class="text-end">Detail</th>
            </tr>
          </thead>
          <tbody>
            @forelse($rows as $row)
              <tr>
                <td>
                  @if($row->selectable)
                    <input type="checkbox"
                           class="form-check-input js-source-checkbox"
                           name="source_keys[]"
                           value="{{ $row->source_key }}"
                           data-sales-user-id="{{ $row->sales_user_id }}"
                           data-sales-user-name="{{ $row->sales_user_name }}">
                  @endif
                </td>
                <td>
                  <div class="fw-semibold">{{ $row->sales_user_name ?: '-' }}</div>
                  @if(!$row->sales_user_id)
                    <div class="text-danger small">Salesperson missing</div>
                  @endif
                </td>
                <td>
                  @if($row->sales_order_id)
                    <a href="{{ route('sales-orders.show', $row->sales_order_id) }}" class="fw-semibold text-decoration-none">{{ $row->sales_order_number }}</a>
                  @else
                    <span class="fw-semibold">{{ $row->sales_order_number }}</span>
                  @endif
                  @if($row->finalized_date)
                    <div class="text-muted small">{{ \Carbon\Carbon::parse($row->finalized_date)->format('d M Y') }}</div>
                  @endif
                </td>
                <td>{{ ucfirst($row->po_type) }}</td>
                <td>{{ $row->customer_name }}</td>
                <td class="text-end">{{ number_format($row->item_count, 0, ',', '.') }}</td>
                <td>{{ $row->project_scope_label }}</td>
                <td class="text-end">{{ $money($row->revenue) }}</td>
                <td class="text-end">{{ $money($row->under_allocated) }}</td>
                <td class="text-end">{{ $money($row->commissionable_base) }}</td>
                <td class="text-end">{{ $money($row->fee_amount) }}</td>
                <td>
                  @if($row->note_id)
                    <a href="{{ route('sales-commission-notes.show', $row->note_id) }}" class="badge {{ $row->source_status === 'in_paid_note' ? 'bg-success-lt text-success' : 'bg-warning-lt text-warning' }}">
                      {{ $row->source_status_label }}: {{ $row->note_number }}
                    </a>
                  @else
                    <span class="badge bg-azure-lt text-azure">{{ $row->source_status_label }}</span>
                  @endif
                </td>
                <td class="text-end">
                  <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#so-detail-{{ $row->sales_order_id }}">
                    Detail
                  </button>
                </td>
              </tr>
            @empty
              <tr>
                <td colspan="13" class="text-center text-muted">No data.</td>
              </tr>
            @endforelse
          </tbody>
        </table>
      </div>
    </div>
  </form>
</div>

@foreach($rows as $row)
  <div class="modal modal-blur fade" id="so-detail-{{ $row->sales_order_id }}" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
      <div class="modal-content">
        <div class="modal-header">
          <div>
            <h5 class="modal-title mb-1">SO {{ $row->sales_order_number }}</h5>
            <div class="text-muted small">{{ $row->customer_name }} - {{ ucfirst($row->po_type) }}</div>
          </div>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
          <div class="row g-3 mb-3">
            <div class="col-md-3"><div class="text-muted small">Revenue</div><div class="fw-semibold">{{ $money($row->revenue) }}</div></div>
            <div class="col-md-3"><div class="text-muted small">Under</div><div class="fw-semibold">{{ $money($row->under_allocated) }}</div></div>
            <div class="col-md-3"><div class="text-muted small">Base</div><div class="fw-semibold">{{ $money($row->commissionable_base) }}</div></div>
            <div class="col-md-3"><div class="text-muted small">Fee</div><div class="fw-semibold">{{ $money($row->fee_amount) }}</div></div>
          </div>

          <div class="table-responsive">
            <table class="table table-sm table-vcenter card-table">
              <thead>
                <tr>
                  <th>Item</th>
                  <th>Brand</th>
                  <th>Family</th>
                  <th class="text-end">Qty</th>
                  <th class="text-end">Revenue</th>
                  <th class="text-end">Under</th>
                  <th class="text-end">Base</th>
                  <th class="text-end">Rate</th>
                  <th class="text-end">Fee</th>
                </tr>
              </thead>
              <tbody>
                @foreach($row->detail_rows as $detail)
                  <tr>
                    <td>
                      <div class="fw-semibold">{{ $detail->item_name }}</div>
                      <div class="text-muted small">{{ $detail->rate_label }}</div>
                    </td>
                    <td>{{ $detail->brand_name ?: '-' }}</td>
                    <td>{{ $detail->family_code ?: '-' }}</td>
                    <td class="text-end">{{ number_format((float) $detail->qty_sold, 2, ',', '.') }}</td>
                    <td class="text-end">{{ $money($detail->revenue) }}</td>
                    <td class="text-end">{{ $money($detail->under_allocated) }}</td>
                    <td class="text-end">{{ $money($detail->commissionable_base) }}</td>
                    <td class="text-end">{{ $percent($detail->rate_percent) }}</td>
                    <td class="text-end">{{ $money($detail->fee_amount) }}</td>
                  </tr>
                @endforeach
              </tbody>
            </table>
          </div>
        </div>
        <div class="modal-footer">
          @if($row->sales_order_id)
            <a href="{{ route('sales-orders.show', $row->sales_order_id) }}" class="btn btn-outline-secondary">Open SO</a>
          @endif
          <button type="button" class="btn btn-primary" data-bs-dismiss="modal">Close</button>
        </div>
      </div>
    </div>
  </div>
@endforeach

<script>
  document.addEventListener('DOMContentLoaded', function () {
    const actionCard = document.getElementById('commission-note-action');
    const countEl = document.getElementById('selected-count');
    const salespersonEl = document.getElementById('selected-salesperson');
    const selectAll = document.getElementById('select-all-rows');
    const checkboxes = Array.from(document.querySelectorAll('.js-source-checkbox'));
    const notesReady = @json((bool) ($features['commission_notes_ready'] ?? false));

    const refreshSelection = () => {
      const checked = checkboxes.filter((el) => el.checked);
      const salesUsers = [...new Set(checked.map((el) => el.dataset.salesUserId).filter(Boolean))];
      countEl.textContent = checked.length;
      salespersonEl.textContent = checked.length === 0 ? '-' : (salesUsers.length === 1 ? checked[0].dataset.salesUserName : 'Mixed selection');
      actionCard.style.display = notesReady && checked.length > 0 ? '' : 'none';
    };

    selectAll?.addEventListener('change', function () {
      checkboxes.forEach((checkbox) => { checkbox.checked = this.checked; });
      refreshSelection();
    });

    checkboxes.forEach((checkbox) => {
      checkbox.addEventListener('change', function () {
        const checked = checkboxes.filter((el) => el.checked);
        const salesUsers = [...new Set(checked.map((el) => el.dataset.salesUserId).filter(Boolean))];

        if (salesUsers.length > 1) {
          this.checked = false;
          alert('Satu commission note hanya boleh untuk satu salesperson.');
        }

        refreshSelection();
      });
    });

    refreshSelection();
  });
</script>
@endsection
