@extends('layouts.tabler')

@section('title', 'Manufacture Commission Note')

@section('content')
@php
  $money = fn ($value) => 'Rp ' . number_format((float) $value, 2, ',', '.');
  $qty = function ($value) {
    $number = (float) $value;
    $decimals = abs($number - round($number)) < 0.00001 ? 0 : 2;
    return number_format($number, $decimals, ',', '.');
  };
  $categoryLabel = fn ($value) => match ($value) {
    'apar_new' => 'APAR Baru',
    'refill_tube' => 'Refill Tabung',
    'firehose_coupling' => 'Firehose with Coupling',
    default => $value,
  };
@endphp

<div class="container-xl">
  <div class="page-header d-print-none">
    <div class="row align-items-center">
      <div class="col">
        <div class="page-pretitle">Manufacture</div>
        <h2 class="page-title">Commission Note {{ $note->number }}</h2>
      </div>
      <div class="col-auto ms-auto d-flex gap-2">
        <a href="{{ route('manufacture-commission-notes.index', ['month' => optional($note->month)->format('Y-m')]) }}" class="btn btn-outline-secondary">Back</a>
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
        <div class="card-header">
          <h3 class="card-title">Note Summary</h3>
        </div>
        <div class="card-body">
          <div class="row g-3">
            <div class="col-md-3">
              <div class="text-muted">Month</div>
              <div class="fw-semibold">{{ optional($note->month)->format('M Y') }}</div>
            </div>
            <div class="col-md-3">
              <div class="text-muted">Note Date</div>
              <div class="fw-semibold">{{ optional($note->note_date)->format('d M Y') }}</div>
            </div>
            <div class="col-md-3">
              <div class="text-muted">Status</div>
              <div class="fw-semibold">
                <span class="badge {{ $note->status === 'paid' ? 'bg-success-lt text-success' : 'bg-warning-lt text-warning' }}">
                  {{ strtoupper($note->status) }}
                </span>
              </div>
            </div>
            <div class="col-md-3">
              <div class="text-muted">Paid Date</div>
              <div class="fw-semibold">{{ optional($note->paid_at)->format('d M Y') ?? '-' }}</div>
            </div>
            <div class="col-md-3">
              <div class="text-muted">Total Qty</div>
              <div class="fw-semibold">{{ $qty($totals['qty']) }}</div>
            </div>
            <div class="col-md-3">
              <div class="text-muted">Total Fee</div>
              <div class="fw-semibold">{{ $money($totals['fee']) }}</div>
            </div>
            <div class="col-md-3">
              <div class="text-muted">Created By</div>
              <div class="fw-semibold">{{ $note->creator->name ?? '-' }}</div>
            </div>
            <div class="col-md-12">
              <div class="text-muted">Notes</div>
              <div class="fw-semibold">{{ $note->notes ?: '-' }}</div>
            </div>
          </div>
        </div>
      </div>
    </div>
    <div class="col-md-4">
      <div class="card">
        <div class="card-header">
          <h3 class="card-title">Actions</h3>
        </div>
        <div class="card-body">
          @if($note->status === 'unpaid')
            <form method="POST" action="{{ route('manufacture-commission-notes.mark-paid', $note) }}" class="mb-3">
              @csrf
              @method('PATCH')
              <label class="form-label">Paid Date</label>
              <input type="date" name="paid_at" class="form-control mb-2" value="{{ old('paid_at', now()->toDateString()) }}" required>
              <button class="btn btn-success w-100">Mark Paid</button>
            </form>

            <form method="POST" action="{{ route('manufacture-commission-notes.destroy', $note) }}" onsubmit="return confirm('Hapus note unpaid ini?');">
              @csrf
              @method('DELETE')
              <button class="btn btn-outline-danger w-100">Delete Unpaid Note</button>
            </form>
          @else
            <form method="POST" action="{{ route('manufacture-commission-notes.mark-unpaid', $note) }}">
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
            <th>Category</th>
            <th>Item</th>
            <th>Customer</th>
            <th class="text-end">Qty</th>
            <th class="text-end">Fee Rate</th>
            <th class="text-end">Fee Amount</th>
          </tr>
        </thead>
        <tbody>
          @foreach($note->lines as $line)
            <tr>
              <td>{{ $categoryLabel($line->category) }}</td>
              <td class="fw-semibold">{{ $line->item_name_snapshot }}</td>
              <td>{{ $line->customer_name_snapshot }}</td>
              <td class="text-end">{{ $qty($line->qty) }}</td>
              <td class="text-end">{{ $money($line->fee_rate) }}</td>
              <td class="text-end">{{ $money($line->fee_amount) }}</td>
            </tr>
          @endforeach
        </tbody>
        <tfoot>
          <tr>
            <th colspan="3" class="text-end">Total</th>
            <th class="text-end">{{ $qty($totals['qty']) }}</th>
            <th></th>
            <th class="text-end">{{ $money($totals['fee']) }}</th>
          </tr>
        </tfoot>
      </table>
    </div>
  </div>
</div>
@endsection
