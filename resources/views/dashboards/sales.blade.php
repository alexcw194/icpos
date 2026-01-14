@extends('layouts.tabler')

@section('content')
@php
  $money = fn($n) => 'Rp ' . number_format((float) $n, 2, ',', '.');
  $companyLabel = fn($c) => $c ? ($c->alias ?: $c->name) : '-';
@endphp

<div class="container-xl">
  <div class="page-header d-print-none">
    <div class="row align-items-center">
      <div class="col">
        <div class="page-pretitle">Sales Overview</div>
        <h2 class="page-title">Dashboard</h2>
      </div>
    </div>
  </div>

  <div class="row row-deck row-cards mb-3">
    <div class="col-6 col-md-2">
      <div class="card card-sm">
        <div class="card-body">
          <div class="subheader">Draft (MTD)</div>
          <div class="h2 m-0">{{ number_format($draftCount) }}</div>
        </div>
      </div>
    </div>
    <div class="col-6 col-md-2">
      <div class="card card-sm">
        <div class="card-body">
          <div class="subheader">Sent (MTD)</div>
          <div class="h2 m-0">{{ number_format($sentCount) }}</div>
        </div>
      </div>
    </div>
    <div class="col-6 col-md-2">
      <div class="card card-sm">
        <div class="card-body">
          <div class="subheader">Won (MTD)</div>
          <div class="h2 m-0">{{ number_format($wonCount) }}</div>
        </div>
      </div>
    </div>
    <div class="col-6 col-md-2">
      <div class="card card-sm">
        <div class="card-body">
          <div class="subheader">Won Revenue (MTD)</div>
          <div class="h2 m-0">{{ $money($wonRevenue) }}</div>
        </div>
      </div>
    </div>
    <div class="col-6 col-md-2">
      <div class="card card-sm">
        <div class="card-body">
          <div class="subheader">Sent Pipeline (MTD)</div>
          <div class="h2 m-0">{{ $money($sentPipeline) }}</div>
        </div>
      </div>
    </div>
    <div class="col-6 col-md-2">
      <div class="card card-sm">
        <div class="card-body">
          <div class="subheader">Sales Orders</div>
          <div class="h2 m-0">{{ number_format($soTotalCount) }}</div>
        </div>
      </div>
    </div>
  </div>

  <div class="card mb-3">
    <div class="card-header d-flex align-items-center">
      <h3 class="card-title">My Work Queue</h3>
      <div class="ms-auto text-muted small">Sent &gt; 7 days</div>
    </div>
    <div class="table-responsive">
      <table class="table table-sm table-vcenter card-table">
        <thead>
          <tr>
            <th>Number</th>
            <th>Customer</th>
            <th>Company</th>
            <th>Sent At</th>
            <th class="text-end">Age (days)</th>
            <th class="text-end">Total</th>
          </tr>
        </thead>
        <tbody>
          @forelse($workQueue as $q)
            <tr>
              <td>
                <a href="{{ route('quotations.show', $q) }}" class="text-decoration-none">
                  {{ $q->number }}
                </a>
              </td>
              <td>{{ $q->customer->name ?? '-' }}</td>
              <td>{{ $companyLabel($q->company) }}</td>
              <td>{{ $q->sent_at ? $q->sent_at->format('d M Y') : '-' }}</td>
              <td class="text-end">{{ $q->age_days ?? '-' }}</td>
              <td class="text-end">{{ $money($q->total ?? 0) }}</td>
            </tr>
          @empty
            <tr>
              <td colspan="6" class="text-center text-muted">Tidak ada antrian.</td>
            </tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </div>

  <div class="card">
    <div class="card-header">
      <h3 class="card-title">Recent Activity</h3>
    </div>
    <div class="table-responsive">
      <table class="table table-sm table-vcenter card-table">
        <thead>
          <tr>
            <th>Number</th>
            <th>Status</th>
            <th>Customer</th>
            <th>Company</th>
            <th>Date</th>
            <th class="text-end">Total</th>
          </tr>
        </thead>
        <tbody>
          @forelse($recent as $q)
            <tr>
              <td>
                <a href="{{ route('quotations.show', $q) }}" class="text-decoration-none">
                  {{ $q->number }}
                </a>
              </td>
              <td>
                <span class="badge {{ $q->status_badge_class }}">
                  {{ $q->status_label }}
                </span>
              </td>
              <td>{{ $q->customer->name ?? '-' }}</td>
              <td>{{ $companyLabel($q->company) }}</td>
              <td>{{ optional($q->date)->format('d M Y') ?? '-' }}</td>
              <td class="text-end">{{ $money($q->total ?? 0) }}</td>
            </tr>
          @empty
            <tr>
              <td colspan="6" class="text-center text-muted">Belum ada aktivitas.</td>
            </tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </div>
</div>
@endsection
