@extends('layouts.tabler')

@section('content')
<div class="container-xl">
  <div class="card">
    <div class="card-header">
      <h3 class="card-title">Stock Reconciliation Report</h3>
    </div>

    <div class="table-responsive">
      <table class="table card-table table-striped">
        <thead>
          <tr>
            <th>Warehouse</th>
            <th>Item</th>
            <th>Variant</th>
            <th class="text-end">Ledger Balance</th>
            <th class="text-end">Summary Balance</th>
            <th class="text-end">Diff</th>
            <th>UOM</th>
          </tr>
        </thead>
        <tbody>
          @forelse($rows as $r)
            <tr @class(['bg-red-lt' => $r['diff'] != 0])>
              <td>{{ $r['warehouse'] }}</td>
              <td>{{ $r['item'] }}</td>
              <td>{{ $r['variant'] }}</td>
              <td class="text-end">{{ number_format($r['ledger'], 4, ',', '.') }}</td>
              <td class="text-end">{{ number_format($r['summary'], 4, ',', '.') }}</td>
              <td class="text-end fw-bold">{{ number_format($r['diff'], 4, ',', '.') }}</td>
              <td>{{ $r['uom'] }}</td>
            </tr>
          @empty
            <tr><td colspan="7" class="text-center text-success">✅ All balances are consistent.</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </div>
</div>
@endsection
