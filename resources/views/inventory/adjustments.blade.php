@extends('layouts.tabler')

@section('content')
<div class="container-xl">
  <div class="card">
    <div class="card-header d-flex">
      <h3 class="card-title">Stock Adjustments</h3>
      <a href="{{ route('inventory.adjustments.create') }}" class="btn btn-primary ms-auto">+ New Adjustment</a>
    </div>

    <div class="table-responsive">
      <table class="table card-table">
        <thead><tr>
          <th>Date</th><th>Item</th><th>Variant</th>
          <th>Warehouse</th><th class="text-end">Qty Adj.</th><th>Reason</th><th>By</th>
        </tr></thead>
        <tbody>
          @forelse($adjustments as $adj)
            <tr>
              <td>{{ $adj->created_at->format('d M Y H:i') }}</td>
              <td>{{ $adj->item->name ?? '—' }}</td>
              <td>{{ $adj->variant->sku ?? '—' }}</td>
              <td>{{ $adj->warehouse->name ?? '—' }}</td>
              <td class="text-end">{{ number_format($adj->qty_adjustment, 2, ',', '.') }}</td>
              <td>{{ $adj->reason }}</td>
              <td>{{ $adj->created_by }}</td>
            </tr>
          @empty
            <tr><td colspan="7" class="text-center text-muted">No adjustment history.</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>
    <div class="card-footer">{{ $adjustments->links() }}</div>
  </div>
</div>
@endsection
