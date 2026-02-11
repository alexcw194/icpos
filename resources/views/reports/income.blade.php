@extends('layouts.tabler')

@section('content')
@php
  use Carbon\Carbon;

  $money = fn($n) => 'Rp ' . number_format((float) $n, 2, ',', '.');
  $companyLabel = fn($c) => $c ? ($c->alias ?: $c->name) : '-';
  $queryParams = request()->query();
@endphp

<div class="container-xl">
  <div class="page-header d-print-none">
    <div class="row align-items-center">
      <div class="col">
        <div class="page-pretitle">Admin Report</div>
        <h2 class="page-title">Income Report (Cash + Accrual)</h2>
      </div>
      <div class="col-auto ms-auto d-flex gap-2">
        <a href="{{ route('reports.income.export.csv', $queryParams) }}" class="btn btn-outline-primary btn-sm">Export CSV</a>
        <a href="{{ route('reports.income.export.pdf', $queryParams) }}" target="_blank" class="btn btn-outline-secondary btn-sm">Export PDF</a>
      </div>
    </div>
  </div>

  <div class="card mb-3">
    <div class="card-body">
      <form method="GET" class="row g-2 align-items-end">
        <div class="col-md-2">
          <label class="form-label">Start Date</label>
          <input type="date" name="start_date" class="form-control" value="{{ $filters['start_date']->toDateString() }}">
        </div>
        <div class="col-md-2">
          <label class="form-label">End Date</label>
          <input type="date" name="end_date" class="form-control" value="{{ $filters['end_date']->toDateString() }}">
        </div>
        <div class="col-md-2">
          <label class="form-label">Company</label>
          <select name="company_id" class="form-select">
            <option value="">All Companies</option>
            @foreach($companies as $co)
              <option value="{{ $co->id }}" @selected((int) ($filters['company_id'] ?? 0) === (int) $co->id)>
                {{ $co->alias ?: $co->name }}
              </option>
            @endforeach
          </select>
        </div>
        <div class="col-md-3">
          <label class="form-label">Customer</label>
          <select name="customer_id" class="form-select">
            <option value="">All Customers</option>
            @foreach($customers as $cu)
              <option value="{{ $cu->id }}" @selected((int) ($filters['customer_id'] ?? 0) === (int) $cu->id)>
                {{ $cu->name }}
              </option>
            @endforeach
          </select>
        </div>
        <div class="col-md-1">
          <label class="form-label">Curr</label>
          <select name="currency" class="form-select">
            <option value="">All</option>
            @foreach($currencies as $currency)
              <option value="{{ $currency }}" @selected(($filters['currency'] ?? '') === $currency)>{{ $currency }}</option>
            @endforeach
          </select>
        </div>
        <div class="col-md-1">
          <label class="form-label">Basis</label>
          <select name="basis" class="form-select">
            <option value="both" @selected(($filters['basis'] ?? 'both') === 'both')>Both</option>
            <option value="cash" @selected(($filters['basis'] ?? '') === 'cash')>Cash</option>
            <option value="accrual" @selected(($filters['basis'] ?? '') === 'accrual')>Accrual</option>
          </select>
        </div>
        <div class="col-md-1">
          <button class="btn btn-primary w-100">Apply</button>
        </div>
      </form>
    </div>
  </div>

  <div class="row row-deck row-cards mb-3">
    <div class="col-6 col-md-3">
      <div class="card card-sm">
        <div class="card-body">
          <div class="subheader">Cash Total</div>
          <div class="h2 m-0">{{ $money($summary['cash_total'] ?? 0) }}</div>
        </div>
      </div>
    </div>
    <div class="col-6 col-md-3">
      <div class="card card-sm">
        <div class="card-body">
          <div class="subheader">Accrual Total</div>
          <div class="h2 m-0">{{ $money($summary['accrual_total'] ?? 0) }}</div>
        </div>
      </div>
    </div>
    <div class="col-6 col-md-3">
      <div class="card card-sm">
        <div class="card-body">
          <div class="subheader">Gap (Accrual - Cash)</div>
          <div class="h2 m-0 {{ ($summary['delta'] ?? 0) < 0 ? 'text-danger' : 'text-success' }}">{{ $money($summary['delta'] ?? 0) }}</div>
        </div>
      </div>
    </div>
    <div class="col-6 col-md-3">
      <div class="card card-sm">
        <div class="card-body">
          <div class="subheader">Unpaid Balance</div>
          <div class="h2 m-0">{{ $money($summary['unpaid_balance'] ?? 0) }}</div>
        </div>
      </div>
    </div>
  </div>

  <div class="card mb-3">
    <div class="card-header">
      <h3 class="card-title">Daily Cash vs Accrual</h3>
    </div>
    <div class="table-responsive">
      <table class="table table-sm table-vcenter card-table">
        <thead>
          <tr>
            <th>Date</th>
            <th class="text-end">Cash</th>
            <th class="text-end">Accrual</th>
            <th class="text-end">Delta</th>
          </tr>
        </thead>
        <tbody>
          @forelse($dailyRows as $row)
            <tr>
              <td>{{ Carbon::parse($row->day)->format('d M Y') }}</td>
              <td class="text-end">{{ $money($row->cash_amount) }}</td>
              <td class="text-end">{{ $money($row->accrual_amount) }}</td>
              <td class="text-end {{ $row->delta < 0 ? 'text-danger' : 'text-success' }}">{{ $money($row->delta) }}</td>
            </tr>
          @empty
            <tr>
              <td colspan="4" class="text-center text-muted">No data.</td>
            </tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </div>

  <div class="card">
    <div class="card-header">
      <h3 class="card-title">Invoice Details</h3>
    </div>
    <div class="table-responsive">
      <table class="table table-sm table-vcenter card-table">
        <thead>
          <tr>
            <th>No</th>
            <th>Company</th>
            <th>Customer</th>
            <th>Invoice Date</th>
            <th>Paid Date</th>
            <th>Status</th>
            <th>Basis</th>
            <th class="text-end">Total</th>
            <th class="text-end">Paid Amount</th>
          </tr>
        </thead>
        <tbody>
          @forelse($invoices as $inv)
            @php
              $tags = [];
              if ((int) ($inv->in_cash ?? 0) === 1) {
                $tags[] = 'Cash';
              }
              if ((int) ($inv->in_accrual ?? 0) === 1) {
                $tags[] = 'Accrual';
              }
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
              <td>{{ optional($inv->paid_at)->format('d M Y') ?? '-' }}</td>
              <td>{{ strtoupper((string) $inv->status) }}</td>
              <td>
                @if($tags)
                  @foreach($tags as $tag)
                    <span class="badge bg-azure-lt text-azure me-1">{{ $tag }}</span>
                  @endforeach
                @else
                  -
                @endif
              </td>
              <td class="text-end">{{ $money($inv->total ?? 0) }}</td>
              <td class="text-end">{{ $money($inv->paid_amount ?? $inv->total ?? 0) }}</td>
            </tr>
          @empty
            <tr>
              <td colspan="9" class="text-center text-muted">No data.</td>
            </tr>
          @endforelse
        </tbody>
      </table>
    </div>
    <div class="card-footer">
      {{ $invoices->withQueryString()->links() }}
    </div>
  </div>
</div>
@endsection
