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
          @php
            $st = strtolower((string) ($inv->status ?? 'draft'));
            if ($st === 'paid' || $inv->paid_at) {
              $statusLabel = 'Paid';
              $statusClass = 'bg-green-lt text-green';
            } elseif (in_array($st, ['posted','invoiced','sent'], true)) {
              $statusLabel = 'Unpaid';
              $statusClass = 'bg-yellow-lt text-dark';
            } else {
              $statusLabel = 'Draft';
              $statusClass = 'bg-secondary-lt text-dark';
            }
          @endphp
          <tr>
            <td>{{ $inv->number }}</td>
            <td>{{ $inv->date?->format('Y-m-d') }}</td>
            <td>{{ $inv->customer->name ?? '-' }}</td>
            <td class="text-end">{{ number_format($inv->total,2) }}</td>
            <td><span class="badge {{ $statusClass }}">{{ $statusLabel }}</span></td>
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

