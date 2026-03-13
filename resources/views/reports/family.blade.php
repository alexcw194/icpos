@extends('layouts.tabler')

@section('content')
@php
  $money = fn($value) => 'Rp ' . number_format((float) $value, 2, ',', '.');
  $qty = function ($value) {
    $number = (float) $value;
    $decimals = abs($number - round($number)) < 0.00001 ? 0 : 2;

    return number_format($number, $decimals, ',', '.');
  };
  $percent = fn($value) => $value === null ? '-' : number_format((float) $value, 2, ',', '.') . '%';
@endphp

<div class="container-xl">
  <div class="page-header d-print-none mb-3">
    <div class="row align-items-center">
      <div class="col">
        <div class="page-pretitle">Family Performance</div>
        <h2 class="page-title">Family Report</h2>
        <div class="text-muted">Revenue, Cost, Margin by Family Code</div>
      </div>
    </div>
  </div>

  <div class="card mb-3">
    <div class="card-body">
      <form method="GET" class="row g-2 align-items-end">
        <div class="col-md-2">
          <label class="form-label">From</label>
          <input type="date" name="from" class="form-control" value="{{ $filters['from']->toDateString() }}">
        </div>
        <div class="col-md-2">
          <label class="form-label">To</label>
          <input type="date" name="to" class="form-control" value="{{ $filters['to']->toDateString() }}">
        </div>
        <div class="col-md-3">
          <label class="form-label">Family Code</label>
          <select name="family_code" class="form-select">
            <option value="">All Family Code</option>
            @foreach($familyCodes as $familyCode)
              <option value="{{ $familyCode }}" @selected($filters['family_code'] === $familyCode)>{{ $familyCode }}</option>
            @endforeach
          </select>
        </div>
        <div class="col-md-2">
          <button class="btn btn-primary w-100">Apply</button>
        </div>
        <div class="col-md-2">
          <a href="{{ route('reports.family') }}" class="btn btn-outline-secondary w-100">Reset</a>
        </div>
      </form>
    </div>
  </div>

  <div class="card mb-3">
    <div class="card-header">
      <h3 class="card-title">Family Summary</h3>
      <div class="text-muted ms-auto">
        Revenue {{ $money($totals['revenue']) }} | Cost {{ $money($totals['cost']) }} | Margin {{ $money($totals['margin']) }}
      </div>
    </div>
    <div class="table-responsive">
      <table class="table table-sm table-vcenter card-table">
        <thead>
          <tr>
            <th>Family Code</th>
            <th class="text-end">Qty Sold</th>
            <th class="text-end">Qty Purchased</th>
            <th class="text-end">Revenue</th>
            <th class="text-end">Cost</th>
            <th class="text-end">Margin</th>
            <th class="text-end">Margin %</th>
          </tr>
        </thead>
        <tbody>
          @forelse($summaryRows as $row)
            <tr>
              <td class="fw-semibold">{{ $row->family_code }}</td>
              <td class="text-end">{{ $qty($row->total_qty_sold) }}</td>
              <td class="text-end">{{ $qty($row->total_qty_purchased) }}</td>
              <td class="text-end">{{ $money($row->total_revenue) }}</td>
              <td class="text-end">{{ $money($row->total_cost) }}</td>
              <td class="text-end {{ $row->margin < 0 ? 'text-danger' : 'text-success' }}">{{ $money($row->margin) }}</td>
              <td class="text-end {{ ($row->margin_percent ?? 0) < 0 ? 'text-danger' : 'text-muted' }}">{{ $percent($row->margin_percent) }}</td>
            </tr>
          @empty
            <tr>
              <td colspan="7" class="text-center text-muted">No data for selected period.</td>
            </tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </div>

  @if($refill)
    <div class="card">
      <div class="card-header">
        <h3 class="card-title">Refill Volume by Item</h3>
        <div class="text-muted ms-auto">
          Total tabung {{ $qty($refill['total_tubes']) }} | Estimated powder {{ $qty($refill['estimated_powder_kg']) }} kg
        </div>
      </div>
      <div class="table-responsive">
        <table class="table table-sm table-vcenter card-table">
          <thead>
            <tr>
              <th>Item</th>
              <th class="text-end">Qty Refill</th>
              <th class="text-end">Revenue</th>
              <th class="text-end">Detected Size</th>
              <th class="text-end">Estimated Powder</th>
            </tr>
          </thead>
          <tbody>
            @foreach($refill['rows'] as $row)
              <tr>
                <td>{{ $row->item_name }}</td>
                <td class="text-end">{{ $qty($row->qty_sold) }}</td>
                <td class="text-end">{{ $money($row->revenue) }}</td>
                <td class="text-end">
                  @if($row->detected_size_kg !== null)
                    {{ $qty($row->detected_size_kg) }} kg
                  @else
                    <span class="text-muted">-</span>
                  @endif
                </td>
                <td class="text-end">
                  @if($row->estimated_powder_kg !== null)
                    {{ $qty($row->estimated_powder_kg) }} kg
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
  @endif
</div>
@endsection
