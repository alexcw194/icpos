@extends('layouts.tabler')

@section('content')
@php
  $o = $salesOrder;
  $contractValue = (float) ($o->contract_value ?? $o->total ?? 0);
  $voAppliedTotal = ($o->relationLoaded('variations') ? $o->variations->where('status', 'applied')->sum('delta_amount') : 0);
  $statusMap = [
    'open'               => ['Open','bg-yellow-lt text-dark'],
    'partial_delivered'  => ['Partial Delivered','bg-cyan-lt text-dark'],
    'delivered'          => ['Delivered','bg-green-lt text-dark'],
    'invoiced'           => ['Invoiced','bg-purple-lt text-dark'],
    'partially_billed'   => ['Partially Billed','bg-orange-lt text-dark'],
    'fully_billed'       => ['Fully Billed','bg-teal-lt text-dark'],
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
  $poTypeMap = [
    'goods' => ['Goods','bg-azure-lt text-dark'],
    'project' => ['Project','bg-orange-lt text-dark'],
    'maintenance' => ['Maintenance','bg-teal-lt text-dark'],
  ];
  [$poLabel, $poClass] = $poTypeMap[$o->po_type ?? 'goods'] ?? ['Goods','bg-azure-lt text-dark'];
@endphp

<div class="container-xl">
  <div class="d-flex align-items-start justify-content-between">
    <div>
      <h2 class="page-title mb-1">
        Sales Order
        <span class="text-muted">{{ $o->so_number }}</span>
        @if ($o->quotation) 
          <span class="ms-2 text-muted">· From Quotation:
            <a href="{{ route('quotations.show', $o->quotation) }}">
              {{ $o->quotation->number }}
            </a>
          </span>
        @endif
      </h2>

      <div class="text-muted">
        {{ $o->company->alias ?? $o->company->name }} — {{ $o->customer->name }}
      </div>

      <div class="mt-2">
        <span class="badge {{ $stClass }}">{{ $stLabel }}</span>
        <span class="badge {{ $poClass }}">{{ $poLabel }}</span>
        {!! $npwpBadge !!}
      </div>
    </div>
    <div class="btn-list">
      {{-- Delivery & Invoice actions --}}

      @can('deliveries.create')
        @if(($o->po_type ?? 'goods') === 'maintenance')
          <span class="btn btn-secondary disabled" title="Maintenance tidak menggunakan Delivery Note">Create Delivery Note</span>
        @elseif($o->status === 'delivered')
          <span class="btn btn-secondary disabled" title="Sales order sudah terkirim penuh">Create Delivery Note</span>
        @else
          <a href="{{ route('deliveries.create', ['sales_order_id' => $o->id]) }}" class="btn btn-secondary">Create Delivery Note</a>
        @endif
      @else
        <span class="btn btn-secondary disabled" title="Anda tidak memiliki akses">Create Delivery Note</span>
      @endcan

      {{-- NEW: actions --}}
      @can('update', $o)
        <a href="{{ route('sales-orders.edit', $o) }}" class="btn">Edit</a>
      @endcan

      @can('amend', $o)
        <a href="{{ route('sales-orders.variations.create', $o) }}" class="btn btn-outline-primary">Create VO</a>
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
        <div class="mb-2"><strong>Customer PO:</strong> {{ $o->customer_po_number }}@if($o->customer_po_date) ({{ $o->customer_po_date }})@endif</div>
        <div class="mb-2"><strong>PO Type:</strong> {{ ucfirst($o->po_type ?? 'goods') }}</div>
        @php
          $projectLabel = null;
          if ($o->project) {
            $projectLabel = ($o->project->code ? $o->project->code.' — ' : '').$o->project->name;
          } elseif (!empty($o->project_name)) {
            $projectLabel = $o->project_name;
          }
        @endphp
        @if($projectLabel)
          <div class="mb-2"><strong>Project:</strong> {{ $projectLabel }}</div>
        @endif
        <div class="mb-2"><strong>Deadline:</strong> {{ $o->deadline ?? '—' }}</div>
        <div class="mb-2"><strong>Salesperson:</strong> {{ $o->salesUser->name ?? '-' }}</div>
        <div class="mb-2"><strong>Original Value:</strong> {{ number_format((float) $o->total, 2) }}</div>
        <div class="mb-2"><strong>VO Total:</strong> {{ number_format((float) $voAppliedTotal, 2) }}</div>
        <div class="mb-2"><strong>Current Contract Value:</strong> {{ number_format((float) $contractValue, 2) }}</div>
        <div class="bg-white border rounded p-2" style="white-space: pre-wrap;">
          {{ $o->notes ?: '—' }}
        </div>
      </div></div>
    </div>
    <div class="col-md-6">
      <div class="card"><div class="card-body">
        <div class="bg-white border rounded p-2" style="white-space: pre-wrap;">
          {{ $o->ship_to ?: '—' }}
        </div>
        <div class="bg-white border rounded p-2" style="white-space: pre-wrap;">
          {{ $o->bill_to ?: '—' }}
        </div>

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
      <div class="card mb-3">
        <div class="card-header">
          <h3 class="card-title">Variations (VO)</h3>
        </div>
        <div class="table-responsive">
          <table class="table table-vcenter card-table">
            <thead>
              <tr>
                <th style="width:140px;">VO No</th>
                <th style="width:120px;">Date</th>
                <th>Reason</th>
                <th style="width:140px;" class="text-end">Delta</th>
                <th style="width:120px;">Status</th>
                <th style="width:180px;"></th>
              </tr>
            </thead>
            <tbody>
              @forelse($o->variations ?? [] as $vo)
                <tr>
                  <td>{{ $vo->vo_number }}</td>
                  <td>{{ optional($vo->vo_date)->format('Y-m-d') }}</td>
                  <td>{{ $vo->reason ?: '-' }}</td>
                  <td class="text-end">{{ number_format((float) $vo->delta_amount, 2) }}</td>
                  <td>{{ ucfirst($vo->status) }}</td>
                  <td class="text-end">
                    @can('amend', $o)
                      @if($vo->status === 'draft')
                        <form action="{{ route('sales-orders.variations.approve', [$o, $vo]) }}" method="POST" class="d-inline"
                              onsubmit="return confirm('Approve VO ini?')">
                          @csrf
                          <button class="btn btn-sm btn-outline-primary">Approve</button>
                        </form>
                      @endif
                      @if($vo->status === 'approved')
                        <form action="{{ route('sales-orders.variations.apply', [$o, $vo]) }}" method="POST" class="d-inline"
                              onsubmit="return confirm('Apply VO ini ke nilai kontrak?')">
                          @csrf
                          <button class="btn btn-sm btn-primary">Apply</button>
                        </form>
                      @endif
                    @endcan
                  </td>
                </tr>
              @empty
                <tr>
                  <td colspan="6" class="text-center text-muted">Belum ada VO.</td>
                </tr>
              @endforelse
            </tbody>
          </table>
        </div>
      </div>

      <div class="card mb-3">
        <div class="card-header">
          <h3 class="card-title">Billing Terms</h3>
        </div>
        <div class="table-responsive">
          <table class="table table-vcenter card-table">
            <thead>
              <tr>
                <th style="width:120px;">TOP Code</th>
                <th style="width:120px;" class="text-end">Percent</th>
                <th style="width:160px;" class="text-end">Amount</th>
                <th>Note</th>
                <th style="width:140px;">Status</th>
                <th style="width:160px;"></th>
              </tr>
            </thead>
            <tbody>
              @forelse($o->billingTerms as $term)
                @php
                  $termAmount = round(((float) $contractValue) * ((float) $term->percent) / 100, 2);
                  $status = $term->status ?? 'planned';
                @endphp
                <tr>
                  <td>{{ $term->top_code }}</td>
                  <td class="text-end">{{ number_format((float) $term->percent, 2) }}%</td>
                  <td class="text-end">{{ number_format($termAmount, 2) }}</td>
                  <td>{{ $term->note ?? '-' }}</td>
                  <td>{{ ucfirst($status) }}</td>
                  <td class="text-end">
                    @if($status === 'planned')
                      <form action="{{ route('sales-orders.billing-terms.create-invoice', [$o, $term]) }}" method="POST" class="d-inline"
                            onsubmit="return confirm('Create invoice untuk TOP {{ $term->top_code }}?')">
                        @csrf
                        <button class="btn btn-sm btn-primary">Create Invoice</button>
                      </form>
                    @elseif(!empty($term->invoice_id))
                      <a href="{{ route('invoices.show', $term->invoice_id) }}" class="btn btn-sm btn-outline-primary">View Invoice</a>
                    @else
                      <span class="text-muted">—</span>
                    @endif
                  </td>
                </tr>
              @empty
                <tr>
                  <td colspan="6" class="text-center text-muted">Belum ada billing term.</td>
                </tr>
              @endforelse
            </tbody>
          </table>
        </div>
      </div>

      <div class="card">
        <div class="card-header">
          <ul class="nav nav-tabs card-header-tabs" data-bs-toggle="tabs">
            <li class="nav-item">
              <a href="#tab-items" class="nav-link active" data-bs-toggle="tab">Items</a>
            </li>
            <li class="nav-item">
              <a href="#tab-more" class="nav-link" data-bs-toggle="tab">More Info</a>
            </li>
          </ul>
          <div class="ms-auto text-muted small d-none d-md-block">
            Discount Mode: {{ $o->discount_mode === 'per_item' ? 'Per Item' : 'Total' }}
          </div>
        </div>

        <div class="card-body tab-content">
              {{-- TAB 1: ITEMS --}}
              <div class="tab-pane active" id="tab-items">
                <div class="table-responsive">
                  <table class="table table-vcenter card-table">
                    <thead>
                      <tr>
                        <th>#</th>
                        <th>Item</th>
                        <th>Desc</th>
                        <th class="text-end">Qty</th>
                        <th>Unit</th>
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
                          <td class="text-end">{{ number_format($ln->qty_ordered,0) }}</td>
                          <td>{{ $ln->unit }}</td>
                          <td class="text-end">{{ number_format($ln->unit_price,2) }}</td>
                          <td class="text-end">
                            @if($ln->discount_amount>0)
                              {{ $ln->discount_type==='percent'
                                  ? rtrim(rtrim(number_format($ln->discount_value,2,'.',''), '0'), '.') .'%'
                                  : number_format($ln->discount_amount,2) }}
                            @else — @endif
                          </td>
                          <td class="text-end">{{ number_format($ln->line_total,2) }}</td>
                        </tr>
                      @endforeach
                    </tbody>
                  </table>
                </div>

                <div class="row mt-3">
                  <div class="col-md-6">
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

              {{-- TAB 2: MORE INFO (Private Notes dulu, lalu Under) --}}
              <div class="tab-pane" id="tab-more">
                <div class="row g-4">
                  <div class="col-md-8">
                    <div class="mb-2 text-muted">Private Notes</div>
                    <div class="bg-white border rounded p-2" style="white-space: pre-wrap;">
                      {{ $o->private_notes ?: '—' }}
                    </div>
                  </div>
                  <div class="col-md-4">
                    <div class="mb-2 text-muted">Under Amount</div>
                    <div>{{ number_format((float)($o->under_amount ?? 0), 0, ',', '.') }}</div>
                  </div>
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
              <form action="{{ route('sales-orders.attachments.store', $o) }}" method="POST" enctype="multipart/form-data" class="d-inline">
                @csrf
                <input type="file" name="attachments[]" multiple style="display:none" id="so-upload-{{ $o->id }}">
                <label for="so-upload-{{ $o->id }}" class="btn btn-sm btn-outline-primary">Upload</label>
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



