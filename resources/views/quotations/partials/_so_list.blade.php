@php
  // Biar efisien: pakai yang sudah di-eager load; kalau belum, ambil latest()
  $orders = $quotation->relationLoaded('salesOrders')
      ? $quotation->salesOrders
      : $quotation->salesOrders()->latest()->get();

  // Boleh disembunyikan kalau tombol Repeat SO sudah ada di header luar
  $showRepeatButton = $showRepeatButton ?? true;

  $badgeClass = [
    'open'               => 'badge bg-blue',
    'partial_delivered'  => 'badge bg-yellow',
    'delivered'          => 'badge bg-teal',
    'invoiced'           => 'badge bg-purple',
    'closed'             => 'badge bg-green',
    'cancelled'          => 'badge bg-red',
  ];
  $labelText = [
    'open'               => 'Open',
    'partial_delivered'  => 'Partial Delivered',
    'delivered'          => 'Delivered',
    'invoiced'           => 'Invoiced',
    'closed'             => 'Closed',
    'cancelled'          => 'Cancelled',
  ];
@endphp

<div class="card mt-3">
  <div class="card-header d-flex align-items-center">
    <div class="card-title m-0">Related Sales Orders</div>
    @if($showRepeatButton && Route::has('sales-orders.create-from-quotation'))
      <a href="{{ route('sales-orders.create-from-quotation', $quotation) }}"
         class="btn btn-primary ms-auto">
        Repeat SO
      </a>
    @endif
  </div>

  <div class="table-responsive">
    <table class="table card-table table-vcenter">
      <thead>
        <tr>
          <th>SO #</th>
          <th>Date</th>
          <th>Status</th>
          <th class="text-end">Total</th>
          <th class="text-end" style="width:1%"></th>
        </tr>
      </thead>
      <tbody>
        @forelse($orders as $so)
          @php $cls = $badgeClass[$so->status] ?? 'badge bg-secondary'; @endphp
          <tr>
            <td class="fw-500">{{ $so->so_number }}</td>
            <td>{{ \Illuminate\Support\Carbon::parse($so->order_date)->format('d M Y') }}</td>
            <td><span class="{{ $cls }}">{{ $labelText[$so->status] ?? ucfirst(str_replace('_',' ',$so->status)) }}</span></td>
            <td class="text-end">Rp {{ number_format((float)$so->total, 2, ',', '.') }}</td>
            <td class="text-end">
              @if(Route::has('sales-orders.show'))
                <a href="{{ route('sales-orders.show', $so) }}" class="btn btn-sm btn-info">View</a>
              @endif
            </td>
          </tr>
        @empty
          <tr>
            <td colspan="5" class="text-muted text-center">Belum ada Sales Order dari quotation ini.</td>
          </tr>
        @endforelse
      </tbody>
    </table>
  </div>
</div>
