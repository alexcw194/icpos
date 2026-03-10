@extends('layouts.tabler')

@section('content')
@php
  $o = $salesOrder;
  $contractValue = (float) ($o->contract_value ?? $o->total ?? 0);
  $voAppliedTotal = ($o->relationLoaded('variations') ? $o->variations->where('status', 'applied')->sum('delta_amount') : 0);
  $isCancelled = $o->status === 'cancelled';
  $financeOnly = auth()->user()?->isFinanceOnly() ?? false;
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
  $customerRefType = strtolower((string) ($o->customer_ref_type ?? 'po')) === 'spk' ? 'spk' : 'po';
  $customerRefLabel = $customerRefType === 'spk' ? 'Customer SPK' : 'Customer PO';
  $customerRefDateLabel = $customerRefType === 'spk' ? 'SPK Date' : 'PO Date';
  $feeAmount = (float) ($o->fee_amount ?? 0);
  $underAmount = (float) ($o->under_amount ?? 0);
  $commissionTotal = $feeAmount + $underAmount;
  $grossProfit = (float) ($commissionSummary['gross_profit'] ?? 0);
  $netProfit = (float) ($commissionSummary['net_profit'] ?? ($grossProfit - $commissionTotal));
@endphp

<div class="container-xl">
  @if(session('success') || session('ok'))
    <div class="alert alert-success mb-3">
      {{ session('success') ?? session('ok') }}
    </div>
  @endif
  <div class="d-flex align-items-start justify-content-between">
    <div>
      <h2 class="page-title mb-1">
        Sales Order
        <span class="text-muted">{{ $o->so_number }}</span>
        @if ($o->quotation && !$financeOnly) 
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
      @if(!$isCancelled)
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
      @endif
    </div>
  </div>

  <div class="row row-cards mt-3">
    <div class="col-md-6">
      <div class="card"><div class="card-body">
        <div class="mb-2"><strong>Order Date:</strong> {{ $o->order_date }}</div>
        @php
          $poDate = $o->customer_po_date
            ? \Illuminate\Support\Carbon::parse($o->customer_po_date)->format('d-m-Y')
            : null;
        @endphp
        <div class="mb-2"><strong>{{ $customerRefLabel }}:</strong> {{ $o->customer_po_number ?: '—' }}@if($poDate) ({{ $poDate }})@endif</div>
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
        <div class="mb-2"><strong>Operational Scope Total:</strong> {{ number_format((float) $o->total, 2) }}</div>
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

    @if(($o->po_type ?? 'goods') === 'project')
      <div class="col-12">
        <div class="row g-3 mb-3">
          <div class="col-12 col-md-6">
            <div class="card">
              <div class="card-body">
                <div class="text-muted">Contract Value (Billing Base)</div>
                <div class="h2 mb-0">{{ number_format((float) $contractValue, 2) }}</div>
              </div>
            </div>
          </div>
          <div class="col-12 col-md-6">
            <div class="card">
              <div class="card-body">
                <div class="text-muted">Operational Scope Total</div>
                <div class="h2 mb-0">{{ number_format((float) ($o->total ?? 0), 2) }}</div>
              </div>
            </div>
          </div>
        </div>
      </div>
    @endif

    <div class="col-12">
      <div class="card mb-3">
        <div class="card-header">
          <h3 class="card-title">Komisi</h3>
          <div class="ms-auto">
            @can('manageCommission', $o)
              <button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#modalCommissionSo">
                Update Komisi
              </button>
            @endcan
          </div>
        </div>
        <div class="card-body">
          <div class="row g-3">
            <div class="col-md-3">
              <div class="text-muted">Fee</div>
              <div class="fw-semibold">{{ number_format($feeAmount, 2, ',', '.') }}</div>
              <div class="small {{ $o->fee_paid_at ? 'text-success' : 'text-muted' }}">
                {{ $o->fee_paid_at ? ('Paid: ' . $o->fee_paid_at->format('d-m-Y')) : 'Belum dibayar' }}
              </div>
            </div>
            <div class="col-md-3">
              <div class="text-muted">Under</div>
              <div class="fw-semibold">{{ number_format($underAmount, 2, ',', '.') }}</div>
              <div class="small {{ $o->under_paid_at ? 'text-success' : 'text-muted' }}">
                {{ $o->under_paid_at ? ('Paid: ' . $o->under_paid_at->format('d-m-Y')) : 'Belum dibayar' }}
              </div>
            </div>
            <div class="col-md-2">
              <div class="text-muted">Total Komisi</div>
              <div class="fw-semibold">{{ number_format($commissionTotal, 2, ',', '.') }}</div>
            </div>
            <div class="col-md-2">
              <div class="text-muted">Gross Margin</div>
              <div class="fw-semibold {{ $grossProfit < 0 ? 'text-danger' : 'text-success' }}">
                {{ number_format($grossProfit, 2, ',', '.') }}
              </div>
            </div>
            <div class="col-md-2">
              <div class="text-muted">Net Income</div>
              <div class="fw-bold {{ $netProfit < 0 ? 'text-danger' : 'text-success' }}">
                {{ number_format($netProfit, 2, ',', '.') }}
              </div>
            </div>
            @if(($commissionSummary['missing_cost_lines'] ?? 0) > 0)
              <div class="col-12">
                <div class="text-muted small">
                  Ada {{ number_format((int) ($commissionSummary['missing_cost_lines'] ?? 0), 0, ',', '.') }} line tanpa cost; gross/net memakai cost yang tersedia.
                </div>
              </div>
            @endif
          </div>
        </div>
      </div>
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
        @php
          $billingDoc = $billingDoc ?? null;
          $billingStatus = $billingDoc->status ?? null;
          $billingBadgeMap = [
            'draft' => ['Draft','bg-secondary-lt text-dark'],
            'sent' => ['Sent','bg-blue-lt text-dark'],
            'void' => ['Void','bg-red-lt text-dark'],
          ];
          [$billingLabel, $billingClass] = $billingBadgeMap[$billingStatus] ?? ['—','bg-secondary-lt'];
          $canIssueProforma = $billingDoc && !$billingDoc->isLocked() && $billingDoc->status !== 'void';
          $canIssueInvoice = $canIssueProforma && (!$o->npwp_required || $o->npwp_status === 'ok');
        @endphp
        <div class="card-header">
          <h3 class="card-title">Billing</h3>
          <div class="ms-auto btn-list">
            @if(!$billingDoc || $billingDoc->status === 'void')
              <a href="{{ route('billings.create-from-so', $o) }}" class="btn btn-sm btn-primary">Create Billing Draft</a>
            @endif

            @if($billingDoc)
              <a href="{{ route('billings.show', $billingDoc) }}" class="btn btn-sm btn-outline-primary">View Billing</a>

              @if($canIssueProforma)
                <form action="{{ route('billings.issue-proforma', $billingDoc) }}" method="POST" class="d-inline"
                      onsubmit="return confirm('Issue Proforma Invoice?')">
                  @csrf
                  <button class="btn btn-sm btn-outline-secondary">Issue Proforma</button>
                </form>
              @endif

              @if(!empty($billingDoc->pi_number))
                <a href="{{ route('billings.pdf.proforma', $billingDoc) }}" target="_blank" class="btn btn-sm btn-outline-secondary">
                  View/Print Proforma
                </a>
              @endif

              @if($billingDoc->status !== 'void' && !$billingDoc->locked_at)
                @if($canIssueInvoice)
                  <button class="btn btn-sm btn-outline-success" data-bs-toggle="modal" data-bs-target="#modalIssueInvoiceSo">
                    Issue Invoice
                  </button>
                @else
                  <button class="btn btn-sm btn-outline-success disabled" title="NPWP wajib diisi sebelum issue invoice">
                    Issue Invoice
                  </button>
                @endif
              @endif

              @if(!empty($billingDoc->inv_number))
                <a href="{{ route('billings.pdf.invoice', $billingDoc) }}" target="_blank" class="btn btn-sm btn-outline-primary">
                  View/Print Invoice
                </a>
              @endif

              @if($billingDoc->status !== 'void')
                <button class="btn btn-sm btn-outline-danger" data-bs-toggle="modal" data-bs-target="#modalVoidBillingSo">
                  Void
                </button>
              @endif
            @endif
          </div>
        </div>
        <div class="card-body">
          @if($billingDoc)
            <div class="row g-3">
              <div class="col-md-3">
                <div class="text-muted">Status</div>
                <div><span class="badge {{ $billingClass }}">{{ $billingLabel }}</span></div>
              </div>
              <div class="col-md-3">
                <div class="text-muted">PI Number</div>
                <div class="fw-semibold">{{ $billingDoc->pi_number ?? '—' }}</div>
              </div>
              <div class="col-md-3">
                <div class="text-muted">INV Number</div>
                <div class="fw-semibold">{{ $billingDoc->inv_number ?? '—' }}</div>
              </div>
              <div class="col-md-3">
                <div class="text-muted">Invoice Date</div>
                <div class="fw-semibold">{{ $billingDoc->invoice_date?->format('Y-m-d') ?? '—' }}</div>
              </div>
              <div class="col-md-3">
                <div class="text-muted">Mode</div>
                <div class="fw-semibold text-uppercase">{{ $billingDoc->mode ?? '—' }}</div>
              </div>
              <div class="col-md-3">
                <div class="text-muted">Total</div>
                <div class="fw-bold">{{ number_format((float) $billingDoc->total, 2) }}</div>
              </div>
              <div class="col-md-6">
                <div class="text-muted">Lock</div>
                <div class="fw-semibold">{{ $billingDoc->locked_at ? 'Locked' : 'Editable' }}</div>
              </div>
              @if($billingDoc->status === 'void')
                <div class="col-md-12">
                  <div class="text-muted">Void Reason</div>
                  <div>{{ $billingDoc->void_reason ?? '—' }}</div>
                </div>
              @endif
            </div>
          @else
            <div class="text-muted">Belum ada billing document.</div>
          @endif
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
                        <th>PO Item Name</th>
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
                          <td>
                            <div>{{ $ln->name }}</div>
                            @if(filled($ln->description))
                              <div class="text-muted small mt-1">{{ $ln->description }}</div>
                            @endif
                          </td>
                          <td>{{ $ln->po_item_name ?: '-' }}</td>
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

                @if(($o->po_type ?? 'goods') === 'project')
                  <div class="mt-4">
                    <h4 class="mb-2">Operational Variance (Planned vs PO vs GR vs Delivery)</h4>
                    <div class="table-responsive">
                      <table class="table table-sm table-vcenter">
                        <thead>
                          <tr>
                            <th>Line</th>
                            <th class="text-end">Planned</th>
                            <th class="text-end">PO</th>
                            <th class="text-end">GR</th>
                            <th class="text-end">Delivered</th>
                            <th class="text-end">Variance PO</th>
                            <th class="text-end">Variance Delivery</th>
                          </tr>
                        </thead>
                        <tbody>
                          @forelse(($executionRows ?? []) as $row)
                            @php
                              $poVarianceClass = ($row['ordered_variance_state'] ?? '') === 'balanced'
                                ? 'text-muted'
                                : (($row['ordered_variance_state'] ?? '') === 'excess' ? 'text-success' : 'text-danger');
                              $deliveryVarianceClass = ($row['delivered_variance_state'] ?? '') === 'balanced'
                                ? 'text-muted'
                                : (($row['delivered_variance_state'] ?? '') === 'excess' ? 'text-success' : 'text-danger');
                            @endphp
                            <tr>
                              <td>
                                <div class="fw-semibold">{{ $row['line_name'] ?? '-' }}</div>
                                @if(!empty($row['line_description']))
                                  <div class="text-muted small">{{ $row['line_description'] }}</div>
                                @endif
                              </td>
                              <td class="text-end">{{ number_format((float) ($row['planned_qty'] ?? 0), 2) }}</td>
                              <td class="text-end">{{ number_format((float) ($row['po_ordered_qty'] ?? 0), 2) }}</td>
                              <td class="text-end">{{ number_format((float) ($row['gr_received_qty'] ?? 0), 2) }}</td>
                              <td class="text-end">{{ number_format((float) ($row['delivered_qty'] ?? 0), 2) }}</td>
                              <td class="text-end {{ $poVarianceClass }}">{{ number_format((float) ($row['ordered_variance_qty'] ?? 0), 2) }}</td>
                              <td class="text-end {{ $deliveryVarianceClass }}">{{ number_format((float) ($row['delivered_variance_qty'] ?? 0), 2) }}</td>
                            </tr>
                          @empty
                            <tr>
                              <td colspan="7" class="text-center text-muted">No execution variance data.</td>
                            </tr>
                          @endforelse
                        </tbody>
                        @if(!empty($executionRows))
                          <tfoot>
                            <tr class="fw-semibold">
                              <td>Total</td>
                              <td class="text-end">{{ number_format((float) ($executionTotals['planned_qty'] ?? 0), 2) }}</td>
                              <td class="text-end">{{ number_format((float) ($executionTotals['po_ordered_qty'] ?? 0), 2) }}</td>
                              <td class="text-end">{{ number_format((float) ($executionTotals['gr_received_qty'] ?? 0), 2) }}</td>
                              <td class="text-end">{{ number_format((float) ($executionTotals['delivered_qty'] ?? 0), 2) }}</td>
                              <td class="text-end">{{ number_format((float) ($executionTotals['ordered_variance_qty'] ?? 0), 2) }}</td>
                              <td class="text-end">{{ number_format((float) ($executionTotals['delivered_variance_qty'] ?? 0), 2) }}</td>
                            </tr>
                          </tfoot>
                        @endif
                      </table>
                    </div>
                  </div>
                @endif
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
                  <div class="col-md-2">
                    <div class="mb-2 text-muted">Fee Amount</div>
                    <div>{{ number_format((float)($o->fee_amount ?? 0), 2, ',', '.') }}</div>
                  </div>
                  <div class="col-md-2">
                    <div class="mb-2 text-muted">Under Amount</div>
                    <div>{{ number_format((float)($o->under_amount ?? 0), 2, ',', '.') }}</div>
                  </div>
                  <div class="col-md-2">
                    <div class="mb-2 text-muted">Total Komisi</div>
                    <div class="fw-semibold">{{ number_format((float) (($o->fee_amount ?? 0) + ($o->under_amount ?? 0)), 2, ',', '.') }}</div>
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
              <form action="{{ route('sales-orders.attachments.store', $o) }}" method="POST" enctype="multipart/form-data" class="d-inline-flex align-items-center gap-2">
                @csrf
                <select name="category" class="form-select form-select-sm">
                  <option value="other">Other</option>
                  <option value="po_spk">PO/SPK</option>
                  <option value="agreement">Agreement</option>
                </select>
                <input type="file" name="attachments[]" class="form-control form-control-sm" multiple>
                <button type="submit" class="btn btn-sm btn-outline-primary">Upload</button>
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
                  @php
                    $attCategory = match((string) ($att->category ?? 'other')) {
                      'po_spk' => 'PO/SPK',
                      'agreement' => 'Agreement',
                      default => 'Other',
                    };
                  @endphp
                  <span class="badge bg-secondary-lt text-dark ms-2">{{ $attCategory }}</span>
                </div>
                @can('deleteAttachment', [$o, $att])
                  <form action="{{ route('sales-orders.attachments.destroy_legacy', [$o, $att]) }}" method="POST"
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

@if($billingDoc)
  {{-- Modal Issue Invoice --}}
  <div class="modal fade" id="modalIssueInvoiceSo" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
      <form class="modal-content" method="POST" action="{{ route('billings.issue-invoice', $billingDoc) }}">
        @csrf
        <div class="modal-header">
          <h5 class="modal-title">Issue Invoice</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
          @if($o->npwp_required && $o->npwp_status !== 'ok')
            <div class="alert alert-danger">
              NPWP wajib diisi sebelum issue invoice.
            </div>
          @endif
          <div class="mb-3">
            <label class="form-label">Invoice Date</label>
            <input type="date" name="invoice_date" class="form-control" value="{{ now()->toDateString() }}">
          </div>
          <div class="text-muted small">
            Setelah issued, angka/lines akan terkunci.
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Batal</button>
          <button type="submit" class="btn btn-success" @disabled($o->npwp_required && $o->npwp_status !== 'ok')>Issue Invoice</button>
        </div>
      </form>
    </div>
  </div>

  {{-- Modal Void Billing --}}
  <div class="modal fade" id="modalVoidBillingSo" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
      <form class="modal-content" method="POST" action="{{ route('billings.void', $billingDoc) }}">
        @csrf
        <div class="modal-header">
          <h5 class="modal-title">Void Billing Document</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
          <div class="mb-3">
            <label class="form-label">Reason</label>
            <textarea name="void_reason" class="form-control" rows="3"></textarea>
          </div>
          <label class="form-check">
            <input class="form-check-input" type="checkbox" name="create_replacement" value="1">
            <span class="form-check-label">Create replacement draft</span>
          </label>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Batal</button>
          <button type="submit" class="btn btn-danger">Void</button>
        </div>
      </form>
    </div>
  </div>
@endif

@can('manageCommission', $o)
  <div class="modal fade" id="modalCommissionSo" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
      <form class="modal-content" method="POST" action="{{ route('sales-orders.commission.update', $o) }}">
        @csrf
        @method('PATCH')
        <div class="modal-header">
          <h5 class="modal-title">Update Komisi SO</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
          <div class="mb-3">
            <label class="form-label">Fee (Rp)</label>
            <input type="number" step="0.01" min="0" name="fee_amount" class="form-control"
                   value="{{ old('fee_amount', (float) ($o->fee_amount ?? 0)) }}">
          </div>
          <div class="mb-3">
            <label class="form-label">Tanggal Bayar Fee</label>
            <input type="date" name="fee_paid_at" class="form-control"
                   value="{{ old('fee_paid_at', optional($o->fee_paid_at)->toDateString()) }}">
            <div class="form-text">Akan dikosongkan otomatis bila nilai fee = 0.</div>
          </div>
          <hr>
          <div class="mb-3">
            <label class="form-label">Under (Rp)</label>
            <input type="number" step="0.01" min="0" name="under_amount" class="form-control"
                   value="{{ old('under_amount', (float) ($o->under_amount ?? 0)) }}">
          </div>
          <div class="mb-0">
            <label class="form-label">Tanggal Bayar Under</label>
            <input type="date" name="under_paid_at" class="form-control"
                   value="{{ old('under_paid_at', optional($o->under_paid_at)->toDateString()) }}">
            <div class="form-text">Akan dikosongkan otomatis bila nilai under = 0.</div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Batal</button>
          <button type="submit" class="btn btn-primary">Simpan</button>
        </div>
      </form>
    </div>
  </div>
@endcan

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

