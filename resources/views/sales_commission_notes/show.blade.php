@extends('layouts.tabler')

@section('title', 'Sales Commission Note')

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
        <h2 class="page-title">Commission Note {{ $note->number }}</h2>
      </div>
      <div class="col-auto ms-auto d-flex gap-2">
        <a href="{{ route('sales-commission-notes.index', ['month' => optional($note->month)->format('Y-m'), 'sales_user_id' => $note->sales_user_id]) }}" class="btn btn-outline-secondary">Back</a>
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

  <div class="row row-cards mb-3">
    <div class="col-md-8">
      <div class="card">
        <div class="card-header"><h3 class="card-title">Note Summary</h3></div>
        <div class="card-body">
          <div class="row g-3">
            <div class="col-md-3"><div class="text-muted">Month</div><div class="fw-semibold">{{ optional($note->month)->format('M Y') }}</div></div>
            <div class="col-md-3"><div class="text-muted">Salesperson</div><div class="fw-semibold">{{ $note->salesUser->name ?? '-' }}</div></div>
            <div class="col-md-3"><div class="text-muted">Note Date</div><div class="fw-semibold">{{ optional($note->note_date)->format('d M Y') }}</div></div>
            <div class="col-md-3"><div class="text-muted">Status</div><div class="fw-semibold"><span class="badge {{ $note->status === 'paid' ? 'bg-success-lt text-success' : 'bg-warning-lt text-warning' }}">{{ strtoupper($note->status) }}</span></div></div>
            <div class="col-md-3"><div class="text-muted">Paid Date</div><div class="fw-semibold">{{ optional($note->paid_at)->format('d M Y') ?? '-' }}</div></div>
            <div class="col-md-3"><div class="text-muted">Revenue</div><div class="fw-semibold">{{ $money($totals['revenue']) }}</div></div>
            <div class="col-md-3"><div class="text-muted">Under</div><div class="fw-semibold">{{ $money($totals['under']) }}</div></div>
            <div class="col-md-3"><div class="text-muted">Commissionable</div><div class="fw-semibold">{{ $money($totals['base']) }}</div></div>
            <div class="col-md-3"><div class="text-muted">Fee</div><div class="fw-semibold">{{ $money($totals['fee']) }}</div></div>
            <div class="col-md-12"><div class="text-muted">Notes</div><div class="fw-semibold">{{ $note->notes ?: '-' }}</div></div>
          </div>
        </div>
      </div>
    </div>
    <div class="col-md-4">
      <div class="card">
        <div class="card-header"><h3 class="card-title">Actions</h3></div>
        <div class="card-body">
          @if($note->status === 'unpaid')
            <form method="POST" action="{{ route('sales-commission-notes.mark-paid', $note) }}" class="mb-3">
              @csrf
              @method('PATCH')
              <label class="form-label">Paid Date</label>
              <input type="date" name="paid_at" class="form-control mb-2" value="{{ old('paid_at', now()->toDateString()) }}" required>
              <button class="btn btn-success w-100">Mark Paid</button>
            </form>

            <form method="POST" action="{{ route('sales-commission-notes.destroy', $note) }}" onsubmit="return confirm('Hapus note unpaid ini?');">
              @csrf
              @method('DELETE')
              <button class="btn btn-outline-danger w-100">Delete Unpaid Note</button>
            </form>
          @else
            <form method="POST" action="{{ route('sales-commission-notes.mark-unpaid', $note) }}">
              @csrf
              @method('PATCH')
              <button class="btn btn-warning w-100">Mark Unpaid</button>
            </form>
          @endif
        </div>
      </div>
    </div>
  </div>

  <div class="card">
    <div class="table-responsive">
      <table class="table table-sm table-vcenter card-table">
        <thead>
          <tr>
            <th>SO</th>
            <th>Customer</th>
            <th>Item</th>
            <th>Project/System</th>
            <th class="text-end">Revenue</th>
            <th class="text-end">Under</th>
            <th class="text-end">Base</th>
            <th class="text-end">Rate</th>
            <th class="text-end">Fee</th>
          </tr>
        </thead>
        <tbody>
          @foreach($note->lines as $line)
            <tr>
              <td>
                @if($line->sales_order_id)
                  <a href="{{ route('sales-orders.show', $line->sales_order_id) }}" class="text-decoration-none fw-semibold">{{ $line->sales_order_number_snapshot ?: '-' }}</a>
                @else
                  <span class="fw-semibold">{{ $line->sales_order_number_snapshot ?: '-' }}</span>
                @endif
              </td>
              <td>{{ $line->customer_name_snapshot }}</td>
              <td class="fw-semibold">{{ $line->item_name_snapshot }}</td>
              <td>{{ $line->project_scope ? str_replace('_', ' ', ucfirst($line->project_scope)) : '-' }}</td>
              <td class="text-end">{{ $money($line->revenue) }}</td>
              <td class="text-end">{{ $money($line->under_allocated) }}</td>
              <td class="text-end">{{ $money($line->commissionable_base) }}</td>
              <td class="text-end">{{ $percent($line->rate_percent) }}</td>
              <td class="text-end">{{ $money($line->fee_amount) }}</td>
            </tr>
          @endforeach
        </tbody>
        <tfoot>
          <tr>
            <th colspan="4" class="text-end">Total</th>
            <th class="text-end">{{ $money($totals['revenue']) }}</th>
            <th class="text-end">{{ $money($totals['under']) }}</th>
            <th class="text-end">{{ $money($totals['base']) }}</th>
            <th></th>
            <th class="text-end">{{ $money($totals['fee']) }}</th>
          </tr>
        </tfoot>
      </table>
    </div>
  </div>
</div>
@endsection
