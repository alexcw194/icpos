@extends('layouts.tabler')

@section('title', 'Sales Commission Notes')

@section('content')
@php
  $money = fn ($value) => 'Rp ' . number_format((float) $value, 2, ',', '.');
@endphp

<div class="container-xl">
  <div class="page-header d-print-none">
    <div class="row align-items-center">
      <div class="col">
        <div class="page-pretitle">Sales</div>
        <h2 class="page-title">Sales Commission Notes</h2>
      </div>
    </div>
  </div>

  @unless($tableReady ?? true)
    <div class="alert alert-warning mb-3">
      Sales Commission Notes belum aktif di server ini. Jalankan migration terbaru terlebih dahulu.
    </div>
  @endunless

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
          <label class="form-label">Status</label>
          <select name="status" class="form-select">
            <option value="all" @selected($filters['status'] === 'all')>All</option>
            <option value="unpaid" @selected($filters['status'] === 'unpaid')>Unpaid</option>
            <option value="paid" @selected($filters['status'] === 'paid')>Paid</option>
          </select>
        </div>
        <div class="col-md-1">
          <button class="btn btn-primary w-100">Apply</button>
        </div>
        <div class="col-md-2">
          <a href="{{ route('sales-commission-notes.index') }}" class="btn btn-outline-secondary w-100">Reset</a>
        </div>
      </form>
    </div>
  </div>

  <div class="card">
    <div class="table-responsive">
      <table class="table table-sm table-vcenter card-table">
        <thead>
          <tr>
            <th>Number</th>
            <th>Month</th>
            <th>Salesperson</th>
            <th>Note Date</th>
            <th>Status</th>
            <th>Paid Date</th>
            <th class="text-end">Revenue</th>
            <th class="text-end">Fee</th>
            <th>Created By</th>
            <th class="text-end">Action</th>
          </tr>
        </thead>
        <tbody>
          @forelse($notes as $note)
            <tr>
              <td class="fw-semibold"><a href="{{ route('sales-commission-notes.show', $note) }}" class="text-decoration-none">{{ $note->number }}</a></td>
              <td>{{ optional($note->month)->format('M Y') }}</td>
              <td>{{ $note->salesUser->name ?? '-' }}</td>
              <td>{{ optional($note->note_date)->format('d M Y') }}</td>
              <td><span class="badge {{ $note->status === 'paid' ? 'bg-success-lt text-success' : 'bg-warning-lt text-warning' }}">{{ strtoupper($note->status) }}</span></td>
              <td>{{ optional($note->paid_at)->format('d M Y') ?? '-' }}</td>
              <td class="text-end">{{ $money($note->total_revenue ?? 0) }}</td>
              <td class="text-end">{{ $money($note->total_fee ?? 0) }}</td>
              <td>{{ $note->creator->name ?? '-' }}</td>
              <td class="text-end"><a href="{{ route('sales-commission-notes.show', $note) }}" class="btn btn-sm btn-outline-secondary">Detail</a></td>
            </tr>
          @empty
            <tr><td colspan="10" class="text-center text-muted">No commission note.</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>
    <div class="card-footer">
      {{ $notes->links() }}
    </div>
  </div>
</div>
@endsection
