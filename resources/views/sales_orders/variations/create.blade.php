@extends('layouts.tabler')

@section('content')
@php
  $o = $salesOrder;
@endphp

<div class="container-xl">
  <div class="d-flex align-items-center justify-content-between mb-3">
    <div>
      <h2 class="page-title mb-1">Create Variation Order (VO)</h2>
      <div class="text-muted">Sales Order {{ $o->so_number }}</div>
    </div>
    <a href="{{ route('sales-orders.show', $o) }}" class="btn btn-outline-secondary">Back</a>
  </div>

  <div class="card">
    <div class="card-body">
      <form action="{{ route('sales-orders.variations.store', $o) }}" method="POST">
        @csrf

        <div class="row g-3">
          <div class="col-md-3">
            <label class="form-label required">VO Date</label>
            <input type="date" name="vo_date" class="form-control"
                   value="{{ old('vo_date', now()->toDateString()) }}" required>
          </div>
          <div class="col-md-3">
            <label class="form-label required">Delta Amount</label>
            <input type="text" name="delta_amount" class="form-control"
                   value="{{ old('delta_amount') }}" required placeholder="Ex: 1.000.000,00">
            <div class="form-text">Isi minus untuk deduct (contoh: -500.000).</div>
          </div>
          <div class="col-12">
            <label class="form-label">Reason</label>
            <textarea name="reason" class="form-control" rows="3">{{ old('reason') }}</textarea>
          </div>
        </div>

        <div class="mt-4 d-flex gap-2">
          <button type="submit" class="btn btn-primary">Save VO</button>
          <a href="{{ route('sales-orders.show', $o) }}" class="btn btn-outline-secondary">Cancel</a>
        </div>
      </form>
    </div>
  </div>
</div>
@endsection
