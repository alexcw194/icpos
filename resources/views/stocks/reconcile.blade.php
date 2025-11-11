{{-- resources/views/stocks/reconcile.blade.php --}}
@extends('layouts.tabler')

@section('title', 'Stock Reconciliation')

@section('content')
<div class="page-header mb-3">
  <h2 class="page-title">Stock Reconciliation</h2>
</div>

<div class="card">
  <div class="card-body">
    <p>Halaman ini akan menampilkan hasil perbandingan antara <strong>Stock Ledger</strong> dan <strong>Item Stock Summary</strong>.</p>

    @if(isset($rows) && count($rows))
      <div class="table-responsive mt-3">
        <table class="table table-bordered table-sm align-middle">
          <thead>
            <tr>
              <th>Company</th>
              <th>Warehouse</th>
              <th>Item</th>
              <th>Variant</th>
              <th class="text-end">Ledger Balance</th>
              <th class="text-end">System Stock</th>
              <th class="text-end text-danger">Difference</th>
            </tr>
          </thead>
          <tbody>
            @foreach($rows as $r)
              <tr>
                <td>{{ $r['company_name'] ?? $r['company_id'] }}</td>
                <td>{{ $r['warehouse_name'] ?? $r['warehouse_id'] }}</td>
                <td>{{ $r['item_name'] ?? $r['item_id'] }}</td>
                <td>{{ $r['variant_name'] ?? '-' }}</td>
                <td class="text-end">{{ number_format($r['ledger_balance'], 2) }}</td>
                <td class="text-end">{{ number_format($r['stock_balance'], 2) }}</td>
                <td class="text-end text-danger fw-bold">{{ number_format($r['difference'], 2) }}</td>
              </tr>
            @endforeach
          </tbody>
        </table>
      </div>
    @else
      <p class="text-muted">Tidak ada data perbandingan stok saat ini.</p>
    @endif
  </div>
</div>
@endsection
