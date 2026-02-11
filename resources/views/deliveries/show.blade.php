@extends('layouts.tabler')

@section('content')
<div class="container-xl">
  @if($errors->any())
    <div class="alert alert-danger mb-3">
      <ul class="mb-0">
        @foreach($errors->all() as $error)
          <li>{{ $error }}</li>
        @endforeach
      </ul>
    </div>
  @endif

  <div class="page-header mb-3">
    <div class="row align-items-end">
      <div class="col">
        <h2 class="page-title mb-1">Delivery {{ $delivery->number ?? 'Draft #'.$delivery->id }}</h2>
        <div class="text-muted">Status: <span class="badge {{ $delivery->status === \App\Models\Delivery::STATUS_POSTED ? 'badge-success' : ($delivery->status === \App\Models\Delivery::STATUS_CANCELLED ? 'badge-danger' : 'badge-secondary') }}">{{ ucfirst($delivery->status) }}</span></div>
      </div>
      <div class="col-auto d-flex gap-2">
        @can('deliveries.view')
          <a class="btn btn-outline-secondary" href="{{ route('deliveries.index') }}">Back to list</a>
        @endcan
        @if($delivery->is_editable)
          @can('deliveries.update')
            <a href="{{ route('deliveries.edit', $delivery) }}" class="btn btn-primary">Edit Draft</a>
          @endcan
          @can('deliveries.post')
            <form action="{{ route('deliveries.post', $delivery, false) }}" method="POST" onsubmit="return confirm('Post delivery dan kurangi stok?');">
              @csrf
              <button type="submit" class="btn btn-success">Post Delivery</button>
            </form>
          @endcan
        @elseif($delivery->status === \App\Models\Delivery::STATUS_POSTED)
          @can('deliveries.cancel')
            <button type="button" class="btn btn-danger" data-bs-toggle="modal" data-bs-target="#modal-cancel">Cancel Delivery</button>
          @endcan
          <a href="{{ route('deliveries.pdf', $delivery) }}" target="_blank" class="btn btn-outline-primary">PDF</a>
        @endif
      </div>
    </div>
  </div>

  <div class="row">
    <div class="col-lg-8">
      <div class="card mb-3">
        <div class="card-body">
          <div class="row g-3">
            <div class="col-md-6">
              <div class="text-muted">Delivery Date</div>
              <div class="fw-semibold">{{ optional($delivery->date)->format('d M Y') }}</div>
            </div>
            <div class="col-md-6">
              <div class="text-muted">Warehouse</div>
              <div class="fw-semibold">{{ $delivery->warehouse->name ?? '-' }}</div>
            </div>
            <div class="col-md-6">
              <div class="text-muted">Customer</div>
              <div class="fw-semibold">{{ $delivery->customer->name ?? '-' }}</div>
            </div>
            <div class="col-md-6">
              <div class="text-muted">Reference</div>
              <div class="fw-semibold">{{ $delivery->reference ?? '-' }}</div>
            </div>
            <div class="col-md-6">
              <div class="text-muted">Recipient</div>
              <div>{{ $delivery->recipient ?? '-' }}</div>
            </div>
            <div class="col-md-6">
              <div class="text-muted">Address</div>
              <div>{!! $delivery->address ? nl2br(e($delivery->address)) : '-' !!}</div>
            </div>
            <div class="col-md-6">
              <div class="text-muted">Posted</div>
              <div>{{ $delivery->posted_at ? $delivery->posted_at->format('d M Y H:i') : '-' }}</div>
            </div>
            <div class="col-md-6">
              <div class="text-muted">Cancelled</div>
              <div>{{ $delivery->cancelled_at ? $delivery->cancelled_at->format('d M Y H:i') : '-' }}</div>
              @if($delivery->cancel_reason)
                <div class="small text-muted">Reason: {{ $delivery->cancel_reason }}</div>
              @endif
            </div>
            <div class="col-12">
              <div class="text-muted">Notes</div>
              <div>{!! $delivery->notes ? nl2br(e($delivery->notes)) : '-' !!}</div>
            </div>
          </div>
        </div>
      </div>

      <div class="card">
        <div class="card-header"><h3 class="card-title">Items</h3></div>
        <div class="table-responsive">
          <table class="table card-table">
            <thead>
              <tr>
                <th>Item</th>
                <th>Variant</th>
                <th class="text-end">Qty</th>
                <th>Unit</th>
                <th class="text-end">Requested</th>
                <th class="text-end">Backorder</th>
                <th class="text-end">Stock After</th>
                <th>Notes</th>
              </tr>
            </thead>
            <tbody>
              @forelse($delivery->lines as $line)
                @php
                  $stockKey  = ($line->item_id ?? 'x').'-'.($line->item_variant_id ?? 0);
                  $stock     = (float) ($currentStocks[$stockKey]->qty_on_hand ?? 0); // default 0
                  $requested = (float) ($line->qty_requested ?? $line->qty ?? 0);

                  // NEW: compute "after" & class color (red < 0, amber == 0, neutral otherwise)
                  $after     = $stock - $requested;
                  $stockCls  = $after < 0 ? 'text-danger fw-semibold'
                             : ($after == 0 ? 'text-warning fw-semibold'
                             : 'text-muted');
                @endphp
                <tr>
                  <td>{{ $line->description ?: ($line->item->name ?? '-') }}</td>
                  <td>{{ $line->variant->name ?? '-' }}</td>
                  <td class="text-end">{{ number_format((float) $line->qty, 2) }}</td>
                  <td>{{ $line->unit ?? '-' }}</td>
                  <td class="text-end">{{ $line->qty_requested ? number_format((float) $line->qty_requested, 2) : '-' }}</td>
                  <td class="text-end">{{ $line->qty_backordered ? number_format((float) $line->qty_backordered, 2) : '-' }}</td>

                  {{-- CHANGED: show "stock / after" with color --}}
                  <td class="text-end {{ $stockCls }}">
                    {{ number_format($stock, 2) }} / {{ number_format($after, 2) }}
                  </td>

                  <td>{{ $line->line_notes ?? '-' }}</td>
                </tr>
              @empty
                <tr>
                  <td colspan="8" class="text-center text-muted">No items recorded.</td>
                </tr>
              @endforelse
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <div class="col-lg-4">
      <div class="card">
        <div class="card-header"><h3 class="card-title">Linked Documents</h3></div>
        <div class="list-group list-group-flush">
          <div class="list-group-item">
            <div class="text-muted small">Sales Order</div>
            @if($delivery->salesOrder)
              <a href="{{ route('sales-orders.show', $delivery->salesOrder) }}">{{ $delivery->salesOrder->so_number ?? ('#'.$delivery->salesOrder->id) }}</a>
            @else
              <span class="text-muted">-</span>
            @endif
          </div>
          <div class="list-group-item">
            <div class="text-muted small">Invoice</div>
            @if($delivery->invoice)
              <a href="{{ route('invoices.show', $delivery->invoice) }}">{{ $delivery->invoice->number }}</a>
            @else
              <span class="text-muted">-</span>
            @endif
          </div>
          <div class="list-group-item">
            <div class="text-muted small">Quotation</div>
            @if($delivery->quotation)
              <a href="{{ route('quotations.show', $delivery->quotation) }}">{{ $delivery->quotation->number ?? ('#'.$delivery->quotation->id) }}</a>
            @else
              <span class="text-muted">-</span>
            @endif
          </div>
        </div>
      </div>

      <div class="card mt-3">
        <div class="card-header"><h3 class="card-title">Stock Movements</h3></div>
        @if(isset($ledgerEntries) && $ledgerEntries->count())
          <div class="list-group list-group-flush">
            @foreach($ledgerEntries as $entry)
              <div class="list-group-item">
                <div class="d-flex justify-content-between">
                  <span class="fw-semibold">{{ optional($entry->ledger_date)->format('d M Y H:i') }}</span>
                  <span class="{{ $entry->qty_change < 0 ? 'text-danger' : 'text-success' }}">{{ number_format((float) $entry->qty_change, 2) }}</span>
                </div>
                <div class="text-muted small">{{ $entry->item->name ?? ('Item #'.$entry->item_id) }}@if($entry->variant) &bull; {{ $entry->variant->name }} @endif</div>
                <div class="text-muted small">Balance: {{ $entry->balance_after !== null ? number_format((float) $entry->balance_after, 2) : '-' }}</div>
              </div>
            @endforeach
          </div>
        @else
          <div class="card-body text-muted">Belum ada pergerakan stok.</div>
        @endif
      </div>
    </div>
  </div>
</div>

@can('deliveries.cancel')
  <div class="modal fade" id="modal-cancel" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog" role="document">
      <form class="modal-content" method="POST" action="{{ route('deliveries.cancel', $delivery, false) }}">
        @csrf
        <div class="modal-header">
          <h5 class="modal-title">Cancel Delivery</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
          <div class="mb-3">
            <label class="form-label">Reason (optional)</label>
            <textarea class="form-control" name="reason" rows="3"></textarea>
          </div>
          <div class="alert alert-warning mb-0">
            Membatalkan delivery akan mengembalikan stok ke gudang terkait.
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Close</button>
          <button type="submit" class="btn btn-danger">Confirm Cancel</button>
        </div>
      </form>
    </div>
  </div>
@endcan
@endsection
