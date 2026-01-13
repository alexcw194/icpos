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

  <div class="card-body p-0">
    {{-- Mobile: stacked rows (no horizontal scroll) --}}
    <div class="d-md-none p-3">
      @forelse($orders as $so)
        @php
          $cls = $badgeClass[$so->status] ?? 'badge bg-secondary';
          $statusLabel = $labelText[$so->status] ?? ucfirst(str_replace('_',' ',$so->status));
          $dateLabel = \Illuminate\Support\Carbon::parse($so->order_date)->format('d M Y');
          $totalLabel = 'Rp ' . number_format((float)$so->total, 2, ',', '.');
        @endphp

        <div class="border rounded p-2 mb-2">
          {{-- Row 1: ID + Status (scan-first) --}}
          <div class="d-flex justify-content-between align-items-start">
            <div class="fw-500 me-2" style="min-width:0;">
              <div class="text-truncate">{{ $so->so_number }}</div>
            </div>
            <span class="{{ $cls }}">{{ $statusLabel }}</span>
          </div>

          {{-- Row 2: Date --}}
          <div class="text-muted small mt-1">
            {{ $dateLabel }}
          </div>

          {{-- Row 3: Total + Action --}}
          <div class="d-flex justify-content-between align-items-center mt-1">
            <div class="fw-600">{{ $totalLabel }}</div>
            @if(Route::has('sales-orders.show'))
              <a href="{{ route('sales-orders.show', $so) }}" class="btn btn-sm btn-info">Lihat</a>
            @endif
          </div>
        </div>
      @empty
        <div class="text-muted text-center py-3">Belum ada Sales Order dari quotation ini.</div>
      @endforelse
    </div>

    {{-- Desktop: table (high-density) --}}
    <div class="d-none d-md-block table-responsive">
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
              <td>
                <span class="{{ $cls }}">
                  {{ $labelText[$so->status] ?? ucfirst(str_replace('_',' ',$so->status)) }}
                </span>
              </td>
              <td class="text-end">Rp {{ number_format((float)$so->total, 2, ',', '.') }}</td>
              <td class="text-end">
                @if(Route::has('sales-orders.show'))
                  <a href="{{ route('sales-orders.show', $so) }}" class="btn btn-sm btn-info">Lihat</a>
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
</div>
