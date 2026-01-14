@extends('layouts.tabler')

@section('content')
@php
  use Carbon\Carbon;

  $money = fn($n) => 'Rp ' . number_format((float) $n, 2, ',', '.');
  $companyLabel = fn($c) => $c ? ($c->alias ?: $c->name) : '-';
  $today = Carbon::now()->startOfDay();
@endphp

<div class="container-xl">
  <div class="page-header d-print-none">
    <div class="row align-items-center">
      <div class="col">
        <div class="page-pretitle">Finance Overview</div>
        <h2 class="page-title">Dashboard</h2>
      </div>
    </div>
  </div>

  <div class="row row-deck row-cards mb-3">
    <div class="col-6 col-md-2">
      <div class="card card-sm">
        <div class="card-body">
          <div class="subheader">AR Outstanding</div>
          <div class="h2 m-0">{{ $money($arOutstandingAmount) }}</div>
          <div class="text-muted small">{{ number_format($arOutstandingCount) }} invoices</div>
        </div>
      </div>
    </div>
    <div class="col-6 col-md-2">
      <div class="card card-sm">
        <div class="card-body">
          <div class="subheader">Overdue</div>
          <div class="h2 m-0">{{ number_format($overdueCount) }}</div>
        </div>
      </div>
    </div>
    <div class="col-6 col-md-2">
      <div class="card card-sm">
        <div class="card-body">
          <div class="subheader">Due 7 Days</div>
          <div class="h2 m-0">{{ number_format($dueSoonCount) }}</div>
        </div>
      </div>
    </div>
    <div class="col-6 col-md-2">
      <div class="card card-sm">
        <div class="card-body">
          <div class="subheader">TT Pending</div>
          <div class="h2 m-0">{{ number_format($ttPendingCount) }}</div>
        </div>
      </div>
    </div>
    <div class="col-6 col-md-2">
      <div class="card card-sm">
        <div class="card-body">
          <div class="subheader">MTD Collected</div>
          <div class="h2 m-0">{{ $money($mtdCollectedAmount) }}</div>
        </div>
      </div>
    </div>
    <div class="col-6 col-md-2">
      <div class="card card-sm">
        <div class="card-body">
          <div class="subheader">NPWP Locked SO</div>
          <div class="h2 m-0">{{ $npwpLockedSoCount === null ? '—' : number_format($npwpLockedSoCount) }}</div>
        </div>
      </div>
    </div>
  </div>

  <div class="card mb-3">
    <div class="card-header">
      <h3 class="card-title">Overdue (Posted, Unpaid)</h3>
    </div>
    <div class="table-responsive">
      <table class="table table-sm table-vcenter card-table">
        <thead>
          <tr>
            <th>Number</th>
            <th>Company</th>
            <th>Customer</th>
            <th>Invoice Date</th>
            <th>Due Date</th>
            <th class="text-end">Days</th>
            <th>Status</th>
            <th class="text-end">Total</th>
          </tr>
        </thead>
        <tbody>
          @forelse($overdueInvoices as $inv)
            @php
              $due = $inv->due_date ? Carbon::parse($inv->due_date)->startOfDay() : null;
              $days = $due ? $due->diffInDays($today, false) : null;
            @endphp
            <tr>
              <td>
                <a href="{{ route('invoices.show', $inv) }}" class="text-decoration-none">
                  {{ $inv->number ?? $inv->id }}
                </a>
              </td>
              <td>{{ $companyLabel($inv->company) }}</td>
              <td>{{ $inv->customer->name ?? '-' }}</td>
              <td>{{ optional($inv->date)->format('d M Y') ?? '-' }}</td>
              <td>{{ $inv->due_date ? Carbon::parse($inv->due_date)->format('d M Y') : '-' }}</td>
              <td class="text-end">{{ $days === null ? '-' : $days }}</td>
              <td><span class="badge bg-orange-lt text-orange-9">Overdue</span></td>
              <td class="text-end">{{ $money($inv->total ?? 0) }}</td>
            </tr>
          @empty
            <tr>
              <td colspan="8" class="text-center text-muted">Tidak ada invoice overdue.</td>
            </tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </div>

  <div class="card mb-3">
    <div class="card-header">
      <h3 class="card-title">Due in Next 7 Days</h3>
    </div>
    <div class="table-responsive">
      <table class="table table-sm table-vcenter card-table">
        <thead>
          <tr>
            <th>Number</th>
            <th>Company</th>
            <th>Customer</th>
            <th>Invoice Date</th>
            <th>Due Date</th>
            <th class="text-end">Days</th>
            <th>Status</th>
            <th class="text-end">Total</th>
          </tr>
        </thead>
        <tbody>
          @forelse($dueSoonInvoices as $inv)
            @php
              $due = $inv->due_date ? Carbon::parse($inv->due_date)->startOfDay() : null;
              $days = $due ? $today->diffInDays($due, false) : null;
            @endphp
            <tr>
              <td>
                <a href="{{ route('invoices.show', $inv) }}" class="text-decoration-none">
                  {{ $inv->number ?? $inv->id }}
                </a>
              </td>
              <td>{{ $companyLabel($inv->company) }}</td>
              <td>{{ $inv->customer->name ?? '-' }}</td>
              <td>{{ optional($inv->date)->format('d M Y') ?? '-' }}</td>
              <td>{{ $inv->due_date ? Carbon::parse($inv->due_date)->format('d M Y') : '-' }}</td>
              <td class="text-end">{{ $days === null ? '-' : $days }}</td>
              <td><span class="badge bg-blue-lt text-blue-9">Posted</span></td>
              <td class="text-end">{{ $money($inv->total ?? 0) }}</td>
            </tr>
          @empty
            <tr>
              <td colspan="8" class="text-center text-muted">Tidak ada invoice jatuh tempo 7 hari.</td>
            </tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </div>

  <div class="card mb-3">
    <div class="card-header d-flex align-items-center">
      <h3 class="card-title">TT Pending (Posted w/o Receipt)</h3>
      <a href="{{ route('invoices.tt-pending') }}" class="ms-auto text-decoration-none small">View TT Pending</a>
    </div>
    <div class="table-responsive">
      <table class="table table-sm table-vcenter card-table">
        <thead>
          <tr>
            <th>Number</th>
            <th>Company</th>
            <th>Customer</th>
            <th>Invoice Date</th>
            <th>Status</th>
            <th>Receipt</th>
            <th class="text-end">Total</th>
          </tr>
        </thead>
        <tbody>
          @forelse($ttPendingInvoices as $inv)
            <tr>
              <td>
                <a href="{{ route('invoices.show', $inv) }}" class="text-decoration-none">
                  {{ $inv->number ?? $inv->id }}
                </a>
              </td>
              <td>{{ $companyLabel($inv->company) }}</td>
              <td>{{ $inv->customer->name ?? '-' }}</td>
              <td>{{ optional($inv->date)->format('d M Y') ?? '-' }}</td>
              <td><span class="badge bg-blue-lt text-blue-9">Posted</span></td>
              <td><span class="badge bg-orange-lt text-orange-9">Missing</span></td>
              <td class="text-end">{{ $money($inv->total ?? 0) }}</td>
            </tr>
          @empty
            <tr>
              <td colspan="7" class="text-center text-muted">Tidak ada TT pending.</td>
            </tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </div>

  <div class="card">
    <div class="card-header">
      <h3 class="card-title">Recently Paid (MTD)</h3>
    </div>
    <div class="table-responsive">
      <table class="table table-sm table-vcenter card-table">
        <thead>
          <tr>
            <th>Number</th>
            <th>Company</th>
            <th>Customer</th>
            <th>Invoice Date</th>
            <th>Paid At</th>
            <th class="text-end">Paid Amount</th>
          </tr>
        </thead>
        <tbody>
          @forelse($mtdPaidInvoices as $inv)
            <tr>
              <td>
                <a href="{{ route('invoices.show', $inv) }}" class="text-decoration-none">
                  {{ $inv->number ?? $inv->id }}
                </a>
              </td>
              <td>{{ $companyLabel($inv->company) }}</td>
              <td>{{ $inv->customer->name ?? '-' }}</td>
              <td>{{ optional($inv->date)->format('d M Y') ?? '-' }}</td>
              <td>{{ $inv->paid_at ? Carbon::parse($inv->paid_at)->format('d M Y') : '-' }}</td>
              <td class="text-end">{{ $money($inv->paid_amount ?? $inv->total ?? 0) }}</td>
            </tr>
          @empty
            <tr>
              <td colspan="6" class="text-center text-muted">Belum ada pembayaran bulan ini.</td>
            </tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </div>
</div>
@endsection
