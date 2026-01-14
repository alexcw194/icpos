@extends('layouts.tabler')

@section('content')
@php
  use Carbon\Carbon;

  $today = Carbon::now()->startOfDay();
  $canSeeSummary = auth()->user() && auth()->user()->hasAnyRole(['Admin', 'SuperAdmin']);

  $soStatusBadge = function ($status) {
    return match ($status) {
      'partial_delivered' => 'bg-yellow-lt text-yellow-9',
      'delivered' => 'bg-green-lt text-green-9',
      default => 'bg-blue-lt text-blue-9',
    };
  };
@endphp

<div class="container-xl">
  <div class="page-header d-print-none">
    <div class="row align-items-center">
      <div class="col">
        <div class="page-pretitle">Logistic Overview</div>
        <h2 class="page-title">Dashboard - Logistic</h2>
      </div>
      <div class="col-auto ms-auto d-none d-md-flex gap-2">
        <a class="btn btn-primary btn-sm" href="{{ route('inventory.adjustments.create') }}">Buat Stock Adjustment</a>
        <a class="btn btn-outline-secondary btn-sm" href="{{ route('inventory.ledger') }}">Inventory Ledger</a>
        <a class="btn btn-outline-secondary btn-sm" href="{{ route('inventory.reconciliation') }}">Reconciliation</a>
        @if($canSeeSummary)
          <a class="btn btn-outline-secondary btn-sm" href="{{ route('inventory.summary') }}">Stock Summary</a>
        @else
          <span class="btn btn-outline-secondary btn-sm disabled" title="Admin only">Stock Summary</span>
        @endif
      </div>
    </div>
    @if($companies->count() > 1)
      <form method="GET" class="row g-2 align-items-center mt-2">
        <div class="col-auto text-muted small">Company</div>
        <div class="col-auto">
          <select name="company_id" class="form-select form-select-sm" onchange="this.form.submit()">
            @foreach($companies as $co)
              <option value="{{ $co->id }}" @selected((string)$companyId === (string)$co->id)>
                {{ $co->alias ?: $co->name }}
              </option>
            @endforeach
          </select>
        </div>
      </form>
    @endif
  </div>

  <div class="row row-deck row-cards mb-3">
    <div class="col-6 col-md-3 col-lg-2">
      <div class="card card-sm">
        <div class="card-body">
          <div class="subheader">SO Open</div>
          <div class="h2 m-0">{{ number_format($soOpenCount) }}</div>
        </div>
      </div>
    </div>
    <div class="col-6 col-md-3 col-lg-2">
      <div class="card card-sm">
        <div class="card-body">
          <div class="subheader">SO Due 7d</div>
          <div class="h2 m-0">{{ number_format($soDue7Count) }}</div>
        </div>
      </div>
    </div>
    <div class="col-6 col-md-3 col-lg-2">
      <div class="card card-sm">
        <div class="card-body">
          <div class="subheader">SO Overdue</div>
          <div class="h2 m-0">{{ number_format($soOverdueCount) }}</div>
        </div>
      </div>
    </div>
    <div class="col-6 col-md-3 col-lg-2">
      <div class="card card-sm">
        <div class="card-body">
          <div class="subheader">SO Partial</div>
          <div class="h2 m-0">{{ number_format($soPartialCount) }}</div>
        </div>
      </div>
    </div>
    <div class="col-6 col-md-3 col-lg-2">
      <div class="card card-sm">
        <div class="card-body">
          <div class="subheader">Delivery Draft</div>
          <div class="h2 m-0">{{ number_format($deliveryDraftCount) }}</div>
        </div>
      </div>
    </div>
    <div class="col-6 col-md-3 col-lg-2">
      <div class="card card-sm">
        <div class="card-body">
          <div class="subheader">Posted Today</div>
          <div class="h2 m-0">{{ number_format($deliveryPostedTodayCount) }}</div>
        </div>
      </div>
    </div>
    <div class="col-6 col-md-3 col-lg-2">
      <div class="card card-sm">
        <div class="card-body">
          <div class="subheader">Cancelled 30d</div>
          <div class="h2 m-0">{{ number_format($deliveryCancelled30dCount) }}</div>
        </div>
      </div>
    </div>
    <div class="col-6 col-md-3 col-lg-2">
      <div class="card card-sm">
        <div class="card-body">
          <div class="subheader">Negative Stock</div>
          <div class="h2 m-0 text-danger">{{ number_format($negativeStockCount) }}</div>
        </div>
      </div>
    </div>
    <div class="col-6 col-md-3 col-lg-2">
      <div class="card card-sm">
        <div class="card-body">
          <div class="subheader">Low/Zero Stock</div>
          <div class="h2 m-0">{{ number_format($lowStockCount) }}</div>
        </div>
      </div>
    </div>
  </div>

  <div class="card mb-3">
    <div class="card-header">
      <h3 class="card-title">SO Due Soon (Next 7 Days)</h3>
    </div>
    <div class="table-responsive d-none d-md-block">
      <table class="table table-sm table-vcenter card-table">
        <thead>
          <tr>
            <th>SO No</th>
            <th>Customer</th>
            <th>Deadline</th>
            <th>Status</th>
            <th class="text-end">Action</th>
          </tr>
        </thead>
        <tbody>
          @forelse($soDueSoon as $so)
            <tr>
              <td>
                <a href="{{ route('sales-orders.show', $so) }}" class="text-decoration-none">
                  {{ $so->number ?? $so->so_number ?? $so->id }}
                </a>
              </td>
              <td>{{ $so->customer->name ?? '-' }}</td>
              <td>{{ $soHasDeadline && $so->deadline ? $so->deadline->format('d M Y') : '-' }}</td>
              <td>
                <span class="badge {{ $soStatusBadge($so->status) }}">
                  {{ ucfirst(str_replace('_', ' ', $so->status)) }}
                </span>
              </td>
              <td class="text-end">
                <a class="btn btn-outline-secondary btn-sm" href="{{ route('sales-orders.show', $so) }}">Open</a>
              </td>
            </tr>
          @empty
            <tr>
              <td colspan="5" class="text-center text-muted">No data.</td>
            </tr>
          @endforelse
        </tbody>
      </table>
    </div>
    <div class="d-md-none">
      @forelse($soDueSoon as $so)
        <div class="border rounded p-2 mb-2">
          <div class="d-flex justify-content-between align-items-center">
            <a href="{{ route('sales-orders.show', $so) }}" class="text-decoration-none fw-semibold">
              {{ $so->number ?? $so->so_number ?? $so->id }}
            </a>
            <span class="badge {{ $soStatusBadge($so->status) }}">
              {{ ucfirst(str_replace('_', ' ', $so->status)) }}
            </span>
          </div>
          <div class="text-muted small mt-1">{{ $so->customer->name ?? '-' }}</div>
          <div class="text-muted small mt-1">Deadline: {{ $soHasDeadline && $so->deadline ? $so->deadline->format('d M Y') : '-' }}</div>
        </div>
      @empty
        <div class="text-center text-muted">No data.</div>
      @endforelse
    </div>
  </div>

  <div class="card mb-3">
    <div class="card-header">
      <h3 class="card-title">Delivery Queue (Draft)</h3>
    </div>
    <div class="table-responsive d-none d-md-block">
      <table class="table table-sm table-vcenter card-table">
        <thead>
          <tr>
            <th>Delivery No</th>
            <th>Related</th>
            <th>Date</th>
            <th>Warehouse</th>
            <th>Status</th>
            <th class="text-end">Action</th>
          </tr>
        </thead>
        <tbody>
          @forelse($deliveryQueue as $dlv)
            @php
              $related = $dlv->invoice?->number ?? $dlv->salesOrder?->number ?? '-';
            @endphp
            <tr>
              <td>
                <a href="{{ route('deliveries.show', $dlv) }}" class="text-decoration-none">
                  {{ $dlv->number ?: '(Draft)' }}
                </a>
              </td>
              <td>{{ $related }}</td>
              <td>{{ $dlv->date ? $dlv->date->format('d M Y') : '-' }}</td>
              <td>{{ $dlv->warehouse->name ?? '-' }}</td>
              <td><span class="badge bg-yellow-lt text-yellow-9">Draft</span></td>
              <td class="text-end">
                <a class="btn btn-outline-secondary btn-sm" href="{{ route('deliveries.show', $dlv) }}">Open</a>
                @can('post', $dlv)
                  <form method="POST" action="{{ route('deliveries.post', $dlv) }}" class="d-inline">
                    @csrf
                    <button type="submit" class="btn btn-outline-success btn-sm">Post</button>
                  </form>
                @endcan
              </td>
            </tr>
          @empty
            <tr>
              <td colspan="6" class="text-center text-muted">No data.</td>
            </tr>
          @endforelse
        </tbody>
      </table>
    </div>
    <div class="d-md-none">
      @forelse($deliveryQueue as $dlv)
        @php
          $related = $dlv->invoice?->number ?? $dlv->salesOrder?->number ?? '-';
        @endphp
        <div class="border rounded p-2 mb-2">
          <div class="d-flex justify-content-between align-items-center">
            <a href="{{ route('deliveries.show', $dlv) }}" class="text-decoration-none fw-semibold">
              {{ $dlv->number ?: '(Draft)' }}
            </a>
            <span class="badge bg-yellow-lt text-yellow-9">Draft</span>
          </div>
          <div class="text-muted small mt-1">{{ $related }}</div>
          <div class="text-muted small mt-1">{{ $dlv->warehouse->name ?? '-' }} | {{ $dlv->date ? $dlv->date->format('d M Y') : '-' }}</div>
        </div>
      @empty
        <div class="text-center text-muted">No data.</div>
      @endforelse
    </div>
  </div>

  <div class="card mb-3">
    <div class="card-header">
      <h3 class="card-title">Inventory Exceptions</h3>
    </div>
    <div class="table-responsive d-none d-md-block">
      <table class="table table-sm table-vcenter card-table">
        <thead>
          <tr>
            <th>Warehouse</th>
            <th>SKU</th>
            <th>Item / Variant</th>
            <th class="text-end">Qty Balance</th>
            <th>UoM</th>
            <th class="text-end">Action</th>
          </tr>
        </thead>
        <tbody>
          @forelse($inventoryExceptions as $row)
            @php
              $sku = $row->variant->sku ?? $row->item->sku ?? '-';
              $variantLabel = $row->variant ? ($row->variant->label ?? $row->variant->sku) : null;
              $name = $row->item->name ?? '-';
              if ($variantLabel) $name .= ' - '.$variantLabel;
            @endphp
            <tr>
              <td>{{ $row->warehouse->name ?? '-' }}</td>
              <td>{{ $sku }}</td>
              <td>{{ $name }}</td>
              <td class="text-end text-danger">{{ number_format((float) $row->qty_balance, 2, ',', '.') }}</td>
              <td>{{ $row->uom ?? '-' }}</td>
              <td class="text-end">
                @if($canSeeSummary)
                  <a class="btn btn-outline-secondary btn-sm" href="{{ route('inventory.summary', ['warehouse_id' => $row->warehouse_id]) }}">Open</a>
                @else
                  <span class="btn btn-outline-secondary btn-sm disabled">Open</span>
                @endif
              </td>
            </tr>
          @empty
            <tr>
              <td colspan="6" class="text-center text-muted">No data.</td>
            </tr>
          @endforelse
        </tbody>
      </table>
    </div>
    <div class="d-md-none">
      @forelse($inventoryExceptions as $row)
        @php
          $sku = $row->variant->sku ?? $row->item->sku ?? '-';
          $variantLabel = $row->variant ? ($row->variant->label ?? $row->variant->sku) : null;
          $name = $row->item->name ?? '-';
          if ($variantLabel) $name .= ' - '.$variantLabel;
        @endphp
        <div class="border rounded p-2 mb-2">
          <div class="d-flex justify-content-between align-items-center">
            <div class="fw-semibold">{{ $sku }}</div>
            <div class="text-danger fw-semibold">{{ number_format((float) $row->qty_balance, 2, ',', '.') }}</div>
          </div>
          <div class="text-muted small mt-1">{{ $name }}</div>
          <div class="text-muted small mt-1">{{ $row->warehouse->name ?? '-' }}</div>
        </div>
      @empty
        <div class="text-center text-muted">No data.</div>
      @endforelse
    </div>
  </div>

  <div class="card">
    <div class="card-header d-flex align-items-center">
      <h3 class="card-title">Recent Stock Adjustments (Last 7 Days)</h3>
      <a href="{{ route('inventory.adjustments.index') }}" class="ms-auto text-decoration-none small">View All</a>
    </div>
    <div class="table-responsive d-none d-md-block">
      <table class="table table-sm table-vcenter card-table">
        <thead>
          <tr>
            <th>Date</th>
            <th>Item / Variant</th>
            <th>Warehouse</th>
            <th class="text-end">Qty Change</th>
            <th>Reason</th>
            <th>By</th>
          </tr>
        </thead>
        <tbody>
          @forelse($recentAdjustments as $adj)
            @php
              $variantLabel = $adj->variant ? ($adj->variant->label ?? $adj->variant->sku) : null;
              $name = $adj->item->name ?? '-';
              if ($variantLabel) $name .= ' - '.$variantLabel;
            @endphp
            <tr>
              <td>{{ $adj->created_at->format('d M Y') }}</td>
              <td>{{ $name }}</td>
              <td>{{ $adj->warehouse->name ?? '-' }}</td>
              <td class="text-end">{{ number_format((float) $adj->qty_adjustment, 2, ',', '.') }}</td>
              <td>{{ $adj->reason ?? '-' }}</td>
              <td>{{ $adj->created_by ?? '-' }}</td>
            </tr>
          @empty
            <tr>
              <td colspan="6" class="text-center text-muted">No data.</td>
            </tr>
          @endforelse
        </tbody>
      </table>
    </div>
    <div class="d-md-none">
      @forelse($recentAdjustments as $adj)
        @php
          $variantLabel = $adj->variant ? ($adj->variant->label ?? $adj->variant->sku) : null;
          $name = $adj->item->name ?? '-';
          if ($variantLabel) $name .= ' - '.$variantLabel;
        @endphp
        <div class="border rounded p-2 mb-2">
          <div class="d-flex justify-content-between align-items-center">
            <div class="fw-semibold">{{ $name }}</div>
            <div class="text-muted small">{{ $adj->created_at->format('d M Y') }}</div>
          </div>
          <div class="text-muted small mt-1">{{ $adj->warehouse->name ?? '-' }}</div>
          <div class="text-muted small mt-1">Qty: {{ number_format((float) $adj->qty_adjustment, 2, ',', '.') }}</div>
        </div>
      @empty
        <div class="text-center text-muted">No data.</div>
      @endforelse
    </div>
  </div>
</div>
@endsection
