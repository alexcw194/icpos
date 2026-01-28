@extends('layouts.tabler')

@section('content')
<div class="container-xl">

  <div class="d-flex align-items-center justify-content-between mb-3">
    <div>
      <h2 class="page-title mb-1">Sales Orders</h2>
      <div class="text-muted">Ringkasan semua Sales Order</div>
    </div>
    <div class="btn-list">
      {{-- placeholder aksi global kalau perlu --}}
      @can('create', \App\Models\SalesOrder::class)
        <a href="{{ route('sales-orders.create') }}" class="btn btn-primary">
          <i class="ti ti-plus"></i> New Sales Order
        </a>
      @endcan
    </div>
  </div>

  {{-- Filter status --}}
  @php
    $st = $status ?? null;
    $btn = function($key, $label) use ($st) {
      $active = $st === $key ? 'active' : '';
      $url = request()->fullUrlWithQuery(['status' => $key]);
      return "<a href=\"{$url}\" class=\"btn btn-sm btn-outline-secondary {$active}\">{$label}</a>";
    };
  @endphp
  <div class="mb-3">
    <a href="{{ route('sales-orders.index') }}" class="btn btn-sm btn-outline-secondary {{ $st ? '' : 'active' }}">All</a>
    {!! $btn('open','Open') !!}
    {!! $btn('partial_delivered','Partial Delivered') !!}
    {!! $btn('delivered','Delivered') !!}
    {!! $btn('invoiced','Invoiced') !!}
    {!! $btn('closed','Closed') !!}
    {!! $btn('cancelled','Cancelled') !!}
  </div>

  <div class="card">
    <div class="table-responsive">
      <table class="table table-vcenter card-table">
        <thead>
          <tr>
            <th style="width:1%">#</th>
            <th>SO Number</th>
            <th>Customer</th>
            <th>Company</th>
            <th class="text-end">Total</th>
            <th>Status</th>
            <th>Tax</th>
          </tr>
        </thead>
        <tbody>
          @forelse($orders as $o)
            @php
              $statusMap = [
                'open'               => ['Open','bg-yellow-lt text-dark'],
                'partial_delivered'  => ['Partial Delivered','bg-cyan-lt text-dark'],
                'delivered'          => ['Delivered','bg-green-lt text-dark'],
                'invoiced'           => ['Invoiced','bg-purple-lt text-dark'],
                'closed'             => ['Closed','bg-secondary-lt text-dark'],
                'cancelled'          => ['Cancelled','bg-red-lt text-dark'],
              ];
              [$stLabel,$stClass] = $statusMap[$o->status] ?? [$o->status,'bg-secondary-lt'];

              $npwpBadge = '';
              if ($o->npwp_required) {
                $npwpBadge = $o->npwp_status==='ok'
                  ? '<span class="badge bg-green-lt">NPWP OK</span>'
                  : '<span class="badge bg-red-lt">NPWP Missing — Billing Locked</span>';
              }
            @endphp

            <tr>
              <td>{{ $orders->firstItem() + $loop->index }}</td>

              {{-- SO number + PO (aksi muncul saat hover DI BAWAH PO) --}}
              <td class="align-middle">
                <a href="{{ route('sales-orders.show',$o) }}" class="fw-bold">{{ $o->so_number }}</a>
                <div class="text-muted small">PO: {{ $o->customer_po_number }}@if($o->customer_po_date) ({{ $o->customer_po_date }})@endif</div>

                {{-- ACTIONS (hidden by default, visible on row hover) --}}
                <div class="so-actions small mt-1">
                  <a href="{{ route('sales-orders.show',$o) }}" class="link-primary">View</a>

                  @can('update', $o)
                    <span class="text-muted">|</span>
                    <a href="{{ route('sales-orders.edit',$o) }}" class="link-warning">Edit</a>
                  @endcan

                  @can('delete', $o)
                    <span class="text-muted">|</span>
                    <form action="{{ route('sales-orders.destroy',$o) }}" method="POST" class="d-inline"
                          onsubmit="return confirm('Delete this Sales Order?')">
                      @csrf @method('DELETE')
                      <button type="submit" class="btn btn-link link-danger p-0 m-0 align-baseline">Delete</button>
                    </form>
                  @endcan
                </div>
              </td>

              <td class="align-middle">{{ $o->customer->name ?? '—' }}</td>
              <td class="align-middle">{{ $o->company->alias ?? $o->company->name }}</td>
              <td class="text-end align-middle">{{ number_format($o->total,2) }}</td>
              <td class="align-middle"><span class="badge {{ $stClass }}">{{ $stLabel }}</span></td>
              <td class="align-middle">{!! $npwpBadge !!}</td>
            </tr>
          @empty
            <tr>
              <td colspan="7" class="text-center text-muted">Belum ada Sales Order.</td>
            </tr>
          @endforelse
        </tbody>
      </table>
    </div>

    <div class="card-footer d-flex justify-content-end">
      {{ $orders->links() }}
    </div>
  </div>

</div>
@endsection

@push('styles')
<style>
  /* Aksi di bawah PO: sembunyikan sampai baris di-hover */
  .table .so-actions { display: none; }
  .table tr:hover .so-actions { display: block; }

  /* Rapikan tampilan link tombol */
  .table .btn-link { text-decoration: none; }
  .table .btn-link:hover { text-decoration: underline; }
</style>
@endpush
