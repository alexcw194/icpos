@extends('layouts.tabler')

@section('content')
<div class="container-xl">
  <div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
      <h2 class="card-title mb-0">Delivery Orders</h2>
      {{--@can('deliveries.create')
        <a href="{{ route('deliveries.create') }}" class="btn btn-primary">
          <i class="ti ti-plus"></i> New Delivery
        </a>
      @endcan--}}
    </div>

    <div class="card-body border-bottom py-3">
      <form method="GET" class="row g-2">
        <div class="col-md-2">
          <label class="form-label">Number</label>
          <input type="text" name="number" value="{{ $filters['number'] ?? '' }}" class="form-control">
        </div>
        <div class="col-md-2">
          <label class="form-label">Reference</label>
          <input type="text" name="reference" value="{{ $filters['reference'] ?? '' }}" class="form-control">
        </div>
        <div class="col-md-2">
          <label class="form-label">Customer</label>
          <select class="form-select" name="customer_id">
            <option value="">All</option>
            @foreach($customers as $customer)
              <option value="{{ $customer->id }}" @selected(($filters['customer_id'] ?? '') == $customer->id)>
                {{ $customer->name }}
              </option>
            @endforeach
          </select>
        </div>
        <div class="col-md-2">
          <label class="form-label">Warehouse</label>
          <select class="form-select" name="warehouse_id">
            <option value="">All</option>
            @foreach($warehouses as $warehouse)
              <option value="{{ $warehouse->id }}" @selected(($filters['warehouse_id'] ?? '') == $warehouse->id)>
                {{ $warehouse->name }}
              </option>
            @endforeach
          </select>
        </div>
        <div class="col-md-2">
          <label class="form-label">Status</label>
          <select class="form-select" name="status">
            <option value="">All</option>
            @foreach($statuses as $status)
              <option value="{{ $status }}" @selected(($filters['status'] ?? '') === $status)>
                {{ ucfirst($status) }}
              </option>
            @endforeach
          </select>
        </div>
        <div class="col-md-2">
          <label class="form-label">Date From</label>
          <input type="date" name="date_from" value="{{ $filters['date_from'] ?? '' }}" class="form-control">
        </div>
        <div class="col-md-2">
          <label class="form-label">Date To</label>
          <input type="date" name="date_to" value="{{ $filters['date_to'] ?? '' }}" class="form-control">
        </div>
        <div class="col-md-2 align-self-end">
          <div class="d-flex gap-2">
            <button type="submit" class="btn btn-outline-primary w-100">Filter</button>
            <a href="{{ route('deliveries.index') }}" class="btn btn-outline-secondary" title="Reset">
              <i class="ti ti-restore"></i>
            </a>
          </div>
        </div>
      </form>
    </div>

    <div class="table-responsive">
      <table class="table card-table table-striped">
        <thead>
          <tr>
            <th>Number</th>
            <th>Date</th>
            <th>Customer</th>
            <th>Warehouse</th>
            <th class="text-center">Items</th>
            <th>Status</th>
            <th></th>
          </tr>
        </thead>
        <tbody>
          @forelse($deliveries as $delivery)
            <tr>
              <td>{{ $delivery->number ?? '&mdash;' }}</td>
              <td>{{ optional($delivery->date)->format('Y-m-d') }}</td>
              <td>{{ $delivery->customer->name ?? '-' }}</td>
              <td>{{ $delivery->warehouse->name ?? '-' }}</td>
              <td class="text-center">{{ $delivery->lines_count }}</td>
              <td>
                @php
                  $badgeClass = match($delivery->status) {
                    \App\Models\Delivery::STATUS_POSTED    => 'badge-success',
                    \App\Models\Delivery::STATUS_CANCELLED => 'badge-danger',
                    default => 'badge-secondary',
                  };
                @endphp
                <span class="badge {{ $badgeClass }}">{{ ucfirst($delivery->status) }}</span>
              </td>
              <td class="text-end">
                <a href="{{ route('deliveries.show', $delivery) }}" class="btn btn-sm btn-outline-primary">View</a>
              </td>
            </tr>
          @empty
            <tr>
              <td colspan="7" class="text-center text-muted py-4">Belum ada delivery order.</td>
            </tr>
          @endforelse
        </tbody>
      </table>
    </div>

    <div class="card-footer d-flex justify-content-center">
      {{ $deliveries->links() }}
    </div>
  </div>
</div>
@endsection
