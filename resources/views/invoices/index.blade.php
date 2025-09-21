@extends('layouts.tabler')

@section('content')
<div class="container-xl">
  <div class="card">
    <div class="card-header"><h3 class="card-title">Invoices</h3></div>
    <div class="table-responsive">
      <table class="table card-table table-vcenter">
        <thead><tr><th>No</th><th>Tanggal</th><th>Customer</th><th>Company</th><th>Total</th><th></th></tr></thead>
        <tbody>
        @forelse($invoices as $inv)
          <tr>
            <td>{{ $inv->number }}</td>
            <td>{{ $inv->date?->format('Y-m-d') }}</td>
            <td>{{ $inv->customer->name ?? '-' }}</td>
            <td>{{ $inv->company->alias ?? $inv->company->name }}</td>
            <td>{{ number_format($inv->total,2,',','.') }}</td>
            <td><a class="btn btn-sm btn-primary" href="{{ route('invoices.show',$inv) }}">Detail</a></td>
          </tr>
        @empty
          <tr><td colspan="6" class="text-center text-muted">Belum ada invoice.</td></tr>
        @endforelse
        </tbody>
      </table>
    </div>
    <div class="card-footer">{{ $invoices->links() }}</div>
  </div>
</div>
@endsection
