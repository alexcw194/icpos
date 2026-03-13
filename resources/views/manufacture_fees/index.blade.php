@extends('layouts.tabler')

@section('title', 'Manufacture Fee')

@section('content')
@php
  $money = fn ($value) => 'Rp ' . number_format((float) $value, 2, ',', '.');
  $qty = function ($value) {
    $number = (float) $value;
    $decimals = abs($number - round($number)) < 0.00001 ? 0 : 2;
    return number_format($number, $decimals, ',', '.');
  };
@endphp

<div class="container-xl">
  <div class="page-header d-print-none">
    <div class="row align-items-center">
      <div class="col">
        <div class="page-pretitle">Manufacture</div>
        <h2 class="page-title">Manufacture Fee</h2>
        <div class="text-muted">Monthly sales-based fee preview for APAR, Refill, and Firehose with Coupling.</div>
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
        <div class="col-md-2">
          <label class="form-label">Month</label>
          <input type="month" name="month" class="form-control" value="{{ $filters['month']->format('Y-m') }}">
        </div>
        <div class="col-md-2">
          <label class="form-label">APAR / Refill Fee Rate</label>
          <input type="number" step="0.01" min="0" name="apar_fee_rate" class="form-control" value="{{ old('apar_fee_rate', $filters['apar_fee_rate']) }}">
        </div>
        <div class="col-md-2">
          <label class="form-label">Firehose Fee Rate</label>
          <input type="number" step="0.01" min="0" name="firehose_fee_rate" class="form-control" value="{{ old('firehose_fee_rate', $filters['firehose_fee_rate']) }}">
        </div>
        <div class="col-md-2">
          <label class="form-label">Source Status</label>
          <select name="row_status" class="form-select">
            <option value="all" @selected($filters['row_status'] === 'all')>All</option>
            <option value="available" @selected($filters['row_status'] === 'available')>Available</option>
            <option value="in_unpaid_note" @selected($filters['row_status'] === 'in_unpaid_note')>In Unpaid Note</option>
            <option value="in_paid_note" @selected($filters['row_status'] === 'in_paid_note')>Paid</option>
          </select>
        </div>
        <div class="col-md-2">
          <button class="btn btn-primary w-100">Apply</button>
        </div>
        <div class="col-md-2">
          <a href="{{ route('manufacture-fees.index') }}" class="btn btn-outline-secondary w-100">Reset</a>
        </div>
      </form>
    </div>
  </div>

  <div class="row row-cards mb-3">
    <div class="col-6 col-md-2">
      <div class="card card-sm">
        <div class="card-body">
          <div class="subheader">APAR Baru</div>
          <div class="h2 m-0">{{ $qty($summary['apar_new_qty']) }}</div>
        </div>
      </div>
    </div>
    <div class="col-6 col-md-2">
      <div class="card card-sm">
        <div class="card-body">
          <div class="subheader">Refill Tabung</div>
          <div class="h2 m-0">{{ $qty($summary['refill_tube_qty']) }}</div>
        </div>
      </div>
    </div>
    <div class="col-6 col-md-2">
      <div class="card card-sm">
        <div class="card-body">
          <div class="subheader">Firehose Coupling</div>
          <div class="h2 m-0">{{ $qty($summary['firehose_coupling_qty']) }}</div>
        </div>
      </div>
    </div>
    <div class="col-6 col-md-2">
      <div class="card card-sm">
        <div class="card-body">
          <div class="subheader">Fee Tim APAR</div>
          <div class="h2 m-0">{{ $money($summary['apar_fee_total']) }}</div>
        </div>
      </div>
    </div>
    <div class="col-6 col-md-2">
      <div class="card card-sm">
        <div class="card-body">
          <div class="subheader">Fee Tim Selang</div>
          <div class="h2 m-0">{{ $money($summary['firehose_fee_total']) }}</div>
        </div>
      </div>
    </div>
    <div class="col-6 col-md-2">
      <div class="card card-sm">
        <div class="card-body">
          <div class="subheader">Grand Total</div>
          <div class="h2 m-0">{{ $money($summary['grand_total']) }}</div>
        </div>
      </div>
    </div>
  </div>

  <form method="POST" action="{{ route('manufacture-commission-notes.store') }}" id="commission-note-form">
    @csrf
    <input type="hidden" name="month" value="{{ $filters['month']->format('Y-m') }}">
    <input type="hidden" name="apar_fee_rate" value="{{ $filters['apar_fee_rate'] }}">
    <input type="hidden" name="firehose_fee_rate" value="{{ $filters['firehose_fee_rate'] }}">

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
            <div class="text-muted small mb-1"><span id="selected-count">0</span> pekerjaan dipilih</div>
            <button type="submit" class="btn btn-primary w-100">Create Commission Note</button>
          </div>
        </div>
      </div>
    </div>

    @foreach($categories as $category)
      <div class="card mb-3">
        <div class="card-header">
          <h3 class="card-title">{{ $category['label'] }}</h3>
          <div class="text-muted ms-auto">
            Qty {{ $qty($category['qty_total']) }} | Fee {{ $money($category['fee_total']) }}
          </div>
        </div>
        <div class="table-responsive">
          <table class="table table-sm table-vcenter card-table">
            <thead>
              <tr>
                <th style="width: 36px;">
                  <input type="checkbox" class="form-check-input js-select-all" data-category="{{ $category['key'] }}">
                </th>
                <th>Item</th>
                <th>Customer</th>
                <th class="text-end">Qty</th>
                <th class="text-end">Fee Rate</th>
                <th class="text-end">Fee Amount</th>
                <th>Status</th>
              </tr>
            </thead>
            <tbody>
              @forelse($category['rows'] as $row)
                <tr>
                  <td>
                    @if($row->selectable)
                      <input type="checkbox"
                             class="form-check-input js-source-checkbox"
                             name="source_keys[]"
                             value="{{ $row->source_key }}"
                             data-category="{{ $category['key'] }}">
                    @endif
                  </td>
                  <td class="fw-semibold">{{ $row->item_name }}</td>
                  <td>{{ $row->customer_name }}</td>
                  <td class="text-end">{{ $qty($row->qty) }}</td>
                  <td class="text-end">{{ $money($row->fee_rate) }}</td>
                  <td class="text-end">{{ $money($row->fee_amount) }}</td>
                  <td>
                    @if($row->note_id)
                      <a href="{{ route('manufacture-commission-notes.show', $row->note_id) }}" class="badge {{ $row->source_status === 'in_paid_note' ? 'bg-success-lt text-success' : 'bg-warning-lt text-warning' }}">
                        {{ $row->source_status_label }}: {{ $row->note_number }}
                      </a>
                    @else
                      <span class="badge bg-azure-lt text-azure">{{ $row->source_status_label }}</span>
                    @endif
                  </td>
                </tr>
              @empty
                <tr>
                  <td colspan="7" class="text-center text-muted">No data.</td>
                </tr>
              @endforelse
            </tbody>
          </table>
        </div>
      </div>
    @endforeach
  </form>

  <div class="card">
    <div class="card-header">
      <h3 class="card-title">Manufacture Activity</h3>
    </div>
    <div class="table-responsive">
      <table class="table table-sm table-vcenter card-table">
        <thead>
          <tr>
            <th>Team</th>
            <th class="text-end">Job Count</th>
            <th>Operators</th>
          </tr>
        </thead>
        <tbody>
          @foreach($activity['teams'] as $team)
            <tr>
              <td class="fw-semibold">{{ $team['label'] }}</td>
              <td class="text-end">{{ number_format($team['job_count'], 0, ',', '.') }}</td>
              <td>
                @if($team['operators']->isNotEmpty())
                  {{ $team['operators']->map(fn ($operator) => $operator->name . ' (' . number_format($operator->job_count, 0, ',', '.') . ')')->implode(', ') }}
                @else
                  <span class="text-muted">-</span>
                @endif
              </td>
            </tr>
          @endforeach
        </tbody>
      </table>
    </div>
  </div>
</div>

<script>
  document.addEventListener('DOMContentLoaded', function () {
    const actionCard = document.getElementById('commission-note-action');
    const countEl = document.getElementById('selected-count');
    const checkboxes = Array.from(document.querySelectorAll('.js-source-checkbox'));
    const selectAll = Array.from(document.querySelectorAll('.js-select-all'));

    const refreshSelection = () => {
      const checkedCount = checkboxes.filter((el) => el.checked).length;
      actionCard.style.display = checkedCount > 0 ? '' : 'none';
      countEl.textContent = checkedCount;
    };

    checkboxes.forEach((checkbox) => {
      checkbox.addEventListener('change', refreshSelection);
    });

    selectAll.forEach((toggle) => {
      toggle.addEventListener('change', function () {
        const category = this.dataset.category;
        checkboxes
          .filter((checkbox) => checkbox.dataset.category === category)
          .forEach((checkbox) => { checkbox.checked = this.checked; });
        refreshSelection();
      });
    });

    refreshSelection();
  });
</script>
@endsection
