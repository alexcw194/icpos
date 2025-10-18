{{-- resources/views/invoices/index.blade.php --}}
@extends('layouts.tabler')
@section('content')
  <div class="page-header"><h2>Invoices</h2></div>
  <div class="card">
    <div class="table-responsive">
      <table class="table table-sm">
        <thead><tr>
          <th>No.</th><th>Date</th><th>Customer</th><th class="text-end">Total</th><th>Status</th><th></th>
        </tr></thead>
        <tbody>
        @forelse($invoices as $inv)
          <tr>
            <td>{{ $inv->number }}</td>
            <td>{{ $inv->date?->format('Y-m-d') }}</td>
            <td>{{ $inv->customer->name ?? '-' }}</td>
            <td class="text-end">{{ number_format($inv->total,2) }}</td>
            <td><span class="badge">{{ strtoupper($inv->status) }}</span></td>
            <td class="text-end">
              <a href="{{ route('invoices.show',$inv) }}" class="btn btn-sm btn-outline-primary">Open</a>
            </td>
          </tr>
        @empty
          <tr><td colspan="6" class="text-center text-muted">No invoices yet.</td></tr>
        @endforelse
        </tbody>
      </table>
    </div>
    <div class="card-footer">{{ $invoices->links() }}</div>
  </div>
@endsection

