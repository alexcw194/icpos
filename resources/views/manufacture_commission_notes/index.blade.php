@extends('layouts.tabler')

@section('title', 'Manufacture Commission Notes')

@section('content')
@php
  $money = fn ($value) => 'Rp ' . number_format((float) $value, 2, ',', '.');
@endphp

<div class="container-xl">
  <div class="page-header d-print-none">
    <div class="row align-items-center">
      <div class="col">
        <div class="page-pretitle">Manufacture</div>
        <h2 class="page-title">Manufacture Commission Notes</h2>
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
          <label class="form-label">Status</label>
          <select name="status" class="form-select">
            <option value="all" @selected($filters['status'] === 'all')>All</option>
            <option value="unpaid" @selected($filters['status'] === 'unpaid')>Unpaid</option>
            <option value="paid" @selected($filters['status'] === 'paid')>Paid</option>
          </select>
        </div>
        <div class="col-md-2">
          <button class="btn btn-primary w-100">Apply</button>
        </div>
        <div class="col-md-2">
          <a href="{{ route('manufacture-commission-notes.index') }}" class="btn btn-outline-secondary w-100">Reset</a>
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
            <th>Note Date</th>
            <th>Status</th>
            <th>Paid Date</th>
            <th class="text-end">Qty</th>
            <th class="text-end">Fee</th>
            <th>Created By</th>
            <th class="text-end">Action</th>
          </tr>
        </thead>
        <tbody>
          @forelse($notes as $note)
            <tr>
              <td class="fw-semibold">
                <a href="{{ route('manufacture-commission-notes.show', $note) }}" class="text-decoration-none">
                  {{ $note->number }}
                </a>
              </td>
              <td>{{ optional($note->month)->format('M Y') }}</td>
              <td>{{ optional($note->note_date)->format('d M Y') }}</td>
              <td>
                <span class="badge {{ $note->status === 'paid' ? 'bg-success-lt text-success' : 'bg-warning-lt text-warning' }}">
                  {{ strtoupper($note->status) }}
                </span>
              </td>
              <td>{{ optional($note->paid_at)->format('d M Y') ?? '-' }}</td>
              <td class="text-end">{{ number_format((float) ($note->total_qty ?? 0), 2, ',', '.') }}</td>
              <td class="text-end">{{ $money($note->total_fee ?? 0) }}</td>
              <td>{{ $note->creator->name ?? '-' }}</td>
              <td class="text-end">
                <a href="{{ route('manufacture-commission-notes.show', $note) }}" class="btn btn-sm btn-outline-secondary">Detail</a>
              </td>
            </tr>
          @empty
            <tr>
              <td colspan="9" class="text-center text-muted">No commission note.</td>
            </tr>
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
