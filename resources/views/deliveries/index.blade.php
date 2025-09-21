@extends('layouts.tabler')

@section('content')
<div class="container-xl">
  <div class="card">
    <div class="card-header"><h3 class="card-title">Delivery Orders</h3></div>
    <div class="table-responsive">
      <table class="table card-table">
        <thead><tr><th>No</th><th>Tanggal</th><th>Company</th><th>Invoice</th><th></th></tr></thead>
        <tbody>
        @forelse($deliveries as $d)
          <tr>
            <td>{{ $d->number }}</td>
            <td>{{ $d->date?->format('Y-m-d') }}</td>
            <td>{{ $d->company->alias ?? $d->company->name }}</td>
            <td>
              @if($d->invoice_id)
                <a href="{{ route('invoices.show',$d->invoice_id) }}">{{ $d->number }}</a>
              @else
                -
              @endif
            </td>
            <td><a class="btn btn-sm btn-primary" href="{{ route('deliveries.show',$d) }}">Detail</a></td>
          </tr>
        @empty
          <tr><td colspan="5" class="text-center text-muted">Belum ada DO.</td></tr>
        @endforelse
        </tbody>
      </table>
    </div>
    <div class="card-footer">{{ $deliveries->links() }}</div>
  </div>
</div>
@endsection
