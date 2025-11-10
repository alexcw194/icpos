@extends('layouts.tabler')

@section('content')
<div class="container-xl">
  <div class="card">
    <div class="card-header d-flex align-items-center">
      <h3 class="card-title">Stock Ledger</h3>
    </div>

    <div class="card-body border-bottom pb-3">
      <form method="GET" class="row g-2 align-items-end">
        <div class="col-md-3">
          <label class="form-label">Warehouse</label>
          <select name="warehouse_id" class="form-select">
            <option value="">All Warehouses</option>
            @foreach($warehouses as $w)
              <option value="{{ $w->id }}" {{ request('warehouse_id')==$w->id ? 'selected':'' }}>
                {{ $w->name }}
              </option>
            @endforeach
          </select>
        </div>
        <div class="col-md-4">
          <label class="form-label">Item</label>
          <select name="item_id" class="form-select">
            <option value="">All Items</option>
            @foreach($items as $it)
              <option value="{{ $it->id }}" {{ request('item_id')==$it->id ? 'selected':'' }}>
                {{ $it->name }}
              </option>
            @endforeach
          </select>
        </div>
        <div class="col-md-2">
          <label class="form-label">From</label>
          <input type="date" name="date_from" class="form-control" value="{{ request('date_from') }}">
        </div>
        <div class="col-md-2">
          <label class="form-label">To</label>
          <input type="date" name="date_to" class="form-control" value="{{ request('date_to') }}">
        </div>
        <div class="col-md-1">
          <button class="btn btn-primary w-100">Filter</button>
        </div>
      </form>
    </div>

    <div class="table-responsive">
      <table class="table card-table table-vcenter">
        <thead>
          <tr>
            <th>Date</th>
            <th>Warehouse</th>
            <th>Item</th>
            <th>Variant</th>
            <th>Type</th>
            <th class="text-end">Qty In</th>
            <th class="text-end">Qty Out</th>
            <th class="text-end">Balance</th>
            <th>Reference</th>
            <th>Created By</th>
          </tr>
        </thead>
        <tbody>
          @forelse($ledgers as $lg)
            <tr>
              <td>{{ $lg->created_at->format('Y-m-d H:i') }}</td>
              <td>{{ $lg->warehouse->name ?? '—' }}</td>
              <td>{{ $lg->item->name ?? '—' }}</td>
              <td>{{ $lg->variant->sku ?? '—' }}</td>
              <td>{{ strtoupper($lg->trx_type) }}</td>
              <td class="text-end text-success">{{ number_format($lg->qty_in, 2, ',', '.') }}</td>
              <td class="text-end text-danger">{{ number_format($lg->qty_out, 2, ',', '.') }}</td>
              <td class="text-end fw-bold">{{ number_format($lg->balance, 2, ',', '.') }}</td>
              <td>{{ $lg->reference ?? '—' }}</td>
              <td>{{ $lg->createdBy->name ?? '—' }}</td>
            </tr>
          @empty
            <tr><td colspan="10" class="text-center text-muted">No ledger entries found.</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>

    <div class="card-footer">
      {{ $ledgers->withQueryString()->links() }}
    </div>
  </div>
</div>
@endsection
