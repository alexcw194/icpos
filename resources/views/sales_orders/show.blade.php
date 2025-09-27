@extends('layouts.tabler')

@section('content')
@php
  $o = $salesOrder;
  $statusMap = [
    'open'               => ['Open','bg-yellow-lt text-dark'],
    'partial_delivered'  => ['Partial Delivered','bg-cyan-lt text-dark'],
    'delivered'          => ['Delivered','bg-green-lt text-dark'],
    'invoiced'           => ['Invoiced','bg-purple-lt text-dark'],
    'closed'             => ['Closed','bg-secondary-lt text-dark'],
    'cancelled'          => ['Cancelled','bg-red-lt text-dark'], // NEW
  ];

  [$stLabel,$stClass] = $statusMap[$o->status] ?? [$o->status,'bg-secondary-lt'];

  $npwpBadge = '';
  if ($o->npwp_required) {
    $npwpBadge = $o->npwp_status==='ok'
      ? '<span class="badge bg-green-lt">NPWP OK</span>'
      : '<span class="badge bg-red-lt">NPWP Missing — Billing Locked</span>';
  }
@endphp

<div class="container-xl">
  <div class="d-flex align-items-start justify-content-between">
    <div>
      <h2 class="page-title mb-1">Sales Order <span class="text-muted">{{ $o->so_number }}</span></h2>
      <div class="text-muted">
        {{ $o->company->alias ?? $o->company->name }} — {{ $o->customer->name }}
      </div>
      <div class="mt-2">
        <span class="badge {{ $stClass }}">{{ $stLabel }}</span>
        {!! $npwpBadge !!}
      </div>
    </div>
    <div class="btn-list">
      {{-- Delivery & Invoice actions --}}

      @can('deliveries.create')
        @if($o->status === 'delivered')
          <span class="btn btn-secondary disabled" title="Sales order sudah terkirim penuh">Create Delivery Note</span>
        @else
          <a href="{{ route('deliveries.create', ['sales_order_id' => $o->id]) }}" class="btn btn-secondary">Create Delivery Note</a>
        @endif
      @else
        <span class="btn btn-secondary disabled" title="Anda tidak memiliki akses">Create Delivery Note</span>
      @endcan

      @if($o->npwp_required && $o->npwp_status === 'missing')
        <button type="button" class="btn btn-primary disabled" title="Lengkapi NPWP untuk membuat Invoice">Create Invoice</button>
      @else
        <a href="javascript:void(0)" class="btn btn-primary disabled" title="Coming soon">Create Invoice</a>
      @endif

      {{-- NEW: actions --}}
      @can('update', $o)
        <a href="{{ route('sales-orders.edit', $o) }}" class="btn">Edit</a>
      @endcan

      @can('cancel', $o)
        <button type="button" class="btn btn-warning" data-bs-toggle="modal" data-bs-target="#modalCancelSo">Cancel SO</button>
      @endcan

      @can('delete', $o)
        <form action="{{ route('sales-orders.destroy', $o) }}" method="POST" class="d-inline"
              onsubmit="return confirm('Delete this Sales Order? This cannot be undone.')">
          @csrf @method('DELETE')
          <button type="submit" class="btn btn-danger">Delete</button>
        </form>
      @endcan
    </div>
  </div>

  <div class="row row-cards mt-3">
    <div class="col-md-6">
      <div class="card"><div class="card-body">
        <div class="mb-2"><strong>Order Date:</strong> {{ $o->order_date }}</div>
        <div class="mb-2"><strong>Customer PO:</strong> {{ $o->customer_po_number }} ({{ $o->customer_po_date }})</div>
        <div class="mb-2"><strong>Deadline:</strong> {{ $o->deadline ?? '—' }}</div>
        <div class="mb-2"><strong>Salesperson:</strong> {{ $o->salesUser->name ?? '-' }}</div>
        <div class="mb-2"><strong>Notes:</strong><br><pre class="mb-0">{{ $o->notes }}</pre></div>
      </div></div>
    </div>
    <div class="col-md-6">
      <div class="card"><div class="card-body">
        <div class="mb-2"><strong>Ship To</strong><br><pre class="mb-0">{{ $o->ship_to }}</pre></div>
        <div class="mb-2"><strong>Bill To</strong><br><pre class="mb-0">{{ $o->bill_to }}</pre></div>

        @if($o->npwp_required)
          <hr>
          <div class="mb-1 fw-bold">NPWP</div>
          <div class="mb-1"><strong>No:</strong> {{ $o->tax_npwp_number ?? '—' }}</div>
          <div class="mb-1"><strong>Nama:</strong> {{ $o->tax_npwp_name ?? '—' }}</div>
          <div class="mb-0"><strong>Alamat:</strong><br><pre class="mb-0">{{ $o->tax_npwp_address }}</pre></div>
        @endif
      </div></div>
    </div>

    <div class="col-12">
      <div class="card">
        <div class="card-header">
          <div class="card-title">Items</div>
          <div class="ms-auto text-muted small">
            Discount Mode: {{ $o->discount_mode === 'per_item' ? 'Per Item' : 'Total' }}
          </div>
        </div>
        <div class="table-responsive">
          <table class="table table-vcenter card-table">
            <thead>
              <tr>
                <th>#</th>
                <th>Item</th>
                <th>Desc</th>
                <th>Unit</th>
                <th class="text-end">Qty</th>
                <th class="text-end">Price</th>
                <th class="text-end">Disc</th>
                <th class="text-end">Line Total</th>
              </tr>
            </thead>
            <tbody>
              @foreach($o->lines as $i => $ln)
                <tr>
                  <td>{{ $i+1 }}</td>
                  <td>{{ $ln->name }}</td>
                  <td>{{ $ln->description }}</td>
                  <td>{{ $ln->unit }}</td>
                  <td class="text-end">{{ number_format($ln->qty_ordered,2) }}</td>
                  <td class="text-end">{{ number_format($ln->unit_price,2) }}</td>
                  <td class="text-end">
                    @if($ln->discount_amount>0)
                      {{ $ln->discount_type==='percent' ? rtrim(rtrim(number_format($ln->discount_value,2,'.',''), '0'), '.') .'%' : number_format($ln->discount_amount,2) }}
                    @else — @endif
                  </td>
                  <td class="text-end">{{ number_format($ln->line_total,2) }}</td>
                </tr>
              @endforeach
            </tbody>
          </table>
        </div>

        <div class="card-footer">
          <div class="row">
            <div class="col-md-6">
              @if($o->quotation)
                <div class="small">
                  From Quotation:
                  <a href="{{ route('quotations.show',$o->quotation) }}">{{ $o->quotation->number }}</a>
                  {{-- opsional: link PDF jika ada route --}}
                </div>
              @endif
            </div>
            <div class="col-md-6">
              <div class="d-flex justify-content-between"><div>Subtotal</div><div>{{ number_format($o->lines_subtotal,2) }}</div></div>
              <div class="d-flex justify-content-between"><div>Discount</div><div>- {{ number_format($o->total_discount_amount,2) }}</div></div>
              <div class="d-flex justify-content-between"><div>Dasar Pajak</div><div>{{ number_format($o->taxable_base,2) }}</div></div>
              <div class="d-flex justify-content-between"><div>PPN ({{ rtrim(rtrim(number_format($o->tax_percent,2,'.',''), '0'), '.') }}%)</div><div>{{ number_format($o->tax_amount,2) }}</div></div>
              <hr class="my-2">
              <div class="d-flex justify-content-between fw-bold"><div>Grand Total</div><div>{{ number_format($o->total,2) }}</div></div>
            </div>
          </div>
        </div>

      </div>
    </div>

    <div class="col-12">
      <div class="card">
        <div class="card-header">
          <div class="card-title">Attachments</div>
          @can('uploadAttachment', $o)
            <form action="{{ route('sales-orders.attachments.upload') }}" method="POST" enctype="multipart/form-data">
                @csrf
                <input type="hidden" name="draft_token" value="{{ old('draft_token', $draftToken) }}">
                <input type="file" name="file" style="display:none" id="so-upload-{{ $salesOrder->id }}" onchange="this.form.submit()">
                <label for="so-upload-{{ $salesOrder->id }}" class="btn btn-sm btn-outline-primary">Upload</label>
            </form>
          @endcan
        </div>

        @if($o->attachments->count())
          <div class="list-group list-group-flush">
            @foreach($o->attachments as $att)
              <div class="list-group-item d-flex align-items-center justify-content-between">
                <div class="me-3">
                  <a href="{{ asset('storage/'.$att->path) }}" target="_blank">{{ $att->original_name ?? basename($att->path) }}</a>
                  <span class="text-muted small">({{ $att->mime }}, {{ number_format($att->size/1024,0) }} KB)</span>
                </div>
                @can('deleteAttachment', [$o, $att])
                  <form action="{{ route('sales-orders.attachments.destroy', [$o, $att]) }}" method="POST"
                        onsubmit="return confirm('Delete this attachment?')">
                    @csrf @method('DELETE')
                    <button type="submit" class="btn btn-sm btn-outline-danger">Delete</button>
                  </form>
                @endcan
              </div>
            @endforeach
          </div>
        @else
          <div class="card-body text-muted">Belum ada lampiran.</div>
        @endif
      </div>
    </div>

  </div>
</div>

{{-- Modal Cancel SO --}}
<div class="modal fade" id="modalCancelSo" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <form action="{{ route('sales-orders.cancel', $o) }}" method="POST" class="modal-content">
      @csrf
      <div class="modal-header">
        <h5 class="modal-title">Cancel Sales Order {{ $o->so_number }}</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <label class="form-label required">Reason</label>
        <textarea name="cancel_reason" class="form-control" rows="4" required minlength="5"
                  placeholder="Tuliskan alasan pembatalan..."></textarea>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-link" data-bs-dismiss="modal">Close</button>
        <button type="submit" class="btn btn-warning">Confirm Cancel</button>
      </div>
    </form>
  </div>
</div>

@endsection



