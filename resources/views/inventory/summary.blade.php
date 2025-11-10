@extends('layouts.tabler')

@section('content')
<div class="container-xl">
  <div class="card">
    <div class="card-header d-flex align-items-center">
      <h3 class="card-title">Stock Summary</h3>
      <form class="ms-auto d-flex" method="get">
        <select name="warehouse_id" class="form-select form-select-sm me-2" onchange="this.form.submit()">
          <option value="">All Warehouses</option>
          @foreach($warehouses as $w)
            <option value="{{ $w->id }}" @selected(request('warehouse_id') == $w->id)>
              {{ $w->name }}
            </option>
          @endforeach
        </select>
      </form>
    </div>

    <div class="table-responsive">
      <table class="table card-table table-vcenter">
        <thead>
          <tr>
            <th>Warehouse</th>
            <th>Item</th>
            <th>Variant</th>
            <th class="text-end">Balance</th>
            <th>UOM</th>
            <th>Updated</th>
          </tr>
        </thead>
        <tbody>
          @forelse($summaries as $row)
            <tr>
              <td>{{ $row->warehouse->name ?? '—' }}</td>
              <td>{{ $row->item->name ?? '—' }}</td>
              <td>{{ $row->variant->sku ?? '—' }}</td>
              <td class="text-end">{{ number_format($row->qty_balance, 2, ',', '.') }}</td>
              <td>{{ $row->uom ?? '—' }}</td>
              <td>{{ $row->updated_at?->format('d M Y H:i') ?? '—' }}</td>
            </tr>
          @empty
            <tr><td colspan="6" class="text-center text-muted">No stock summary available.</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>

    <div class="card-footer">
      {{ $summaries->withQueryString()->links() }}
    </div>
  </div>
</div>
@endsection
