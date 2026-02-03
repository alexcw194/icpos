@extends('layouts.tabler')

@section('content')
@php
  $billingStatus = $billing->status ?? 'draft';
  $badgeMap = [
    'draft' => ['Draft','bg-secondary-lt text-dark'],
    'sent' => ['Sent','bg-blue-lt text-dark'],
    'void' => ['Void','bg-red-lt text-dark'],
  ];
  [$statusLabel, $statusClass] = $badgeMap[$billingStatus] ?? [$billingStatus,'bg-secondary-lt'];
  $isEditable = $billing->isEditable();
  $canIssueProforma = !$billing->isLocked() && $billing->status !== 'void';
  $so = $billing->salesOrder;
  $npwpBlocked = $so && $so->npwp_required && ($so->npwp_status ?? 'missing') !== 'ok';
  $canIssueInvoice = $canIssueProforma && !$npwpBlocked;
  $displayNumber = $billing->inv_number ?? $billing->pi_number ?? ('DRAFT-'.$billing->id);
@endphp

<div class="container-xl">
  @if(session('success') || session('ok'))
    <div class="alert alert-success mb-3">
      {{ session('success') ?? session('ok') }}
    </div>
  @endif
  @if($errors->any())
    <div class="alert alert-danger mb-3">
      <ul class="mb-0">
        @foreach ($errors->all() as $error)
          <li>{{ $error }}</li>
        @endforeach
      </ul>
    </div>
  @endif

  <div class="d-flex justify-content-between align-items-start mb-3">
    <div>
      <h2 class="page-title mb-1">Billing Document {{ $displayNumber }}</h2>
      <div class="text-muted">SO: <a href="{{ route('sales-orders.show', $billing->salesOrder) }}">{{ $billing->salesOrder->so_number ?? ('#'.$billing->salesOrder_id) }}</a></div>
    </div>
    <div class="btn-list">
      @if($canIssueProforma)
        <form action="{{ route('billings.issue-proforma', $billing) }}" method="POST" class="d-inline"
              onsubmit="return confirm('Issue Proforma Invoice?')">
          @csrf
          <button class="btn btn-outline-secondary">Issue Proforma</button>
        </form>
      @endif

      @if(!empty($billing->pi_number))
        <a href="{{ route('billings.pdf.proforma', $billing) }}" target="_blank" class="btn btn-outline-secondary">View/Print Proforma</a>
      @endif

      @if(!$billing->isLocked() && $billing->status !== 'void')
        @if($canIssueInvoice)
          <button class="btn btn-outline-success" data-bs-toggle="modal" data-bs-target="#modalIssueInvoice">Issue Invoice</button>
        @else
          <button class="btn btn-outline-success disabled" title="NPWP wajib diisi sebelum issue invoice">Issue Invoice</button>
        @endif
      @endif

      @if(!empty($billing->inv_number))
        <a href="{{ route('billings.pdf.invoice', $billing) }}" target="_blank" class="btn btn-outline-primary">View/Print Invoice</a>
      @endif

      @if($billing->status !== 'void')
        <button class="btn btn-outline-danger" data-bs-toggle="modal" data-bs-target="#modalVoidBilling">Void</button>
      @endif
    </div>
  </div>

  <div class="card mb-3">
    <div class="card-body">
      <div class="row g-3">
        <div class="col-md-3">
          <div class="text-muted">Company</div>
          <div class="fw-semibold">{{ $billing->company->alias ?? $billing->company->name ?? '-' }}</div>
        </div>
        <div class="col-md-3">
          <div class="text-muted">Customer</div>
          <div class="fw-semibold">{{ $billing->customer->name ?? '-' }}</div>
        </div>
        <div class="col-md-3">
          <div class="text-muted">Status</div>
          <div><span class="badge {{ $statusClass }}">{{ $statusLabel }}</span></div>
        </div>
        <div class="col-md-3">
          <div class="text-muted">Mode</div>
          <div class="fw-semibold text-uppercase">{{ $billing->mode ?? '-' }}</div>
        </div>
        <div class="col-md-3">
          <div class="text-muted">PI Number</div>
          <div class="fw-semibold">{{ $billing->pi_number ?? '-' }}</div>
        </div>
        <div class="col-md-3">
          <div class="text-muted">INV Number</div>
          <div class="fw-semibold">{{ $billing->inv_number ?? '-' }}</div>
        </div>
        <div class="col-md-3">
          <div class="text-muted">Invoice Date</div>
          <div class="fw-semibold">{{ $billing->invoice_date?->format('Y-m-d') ?? '-' }}</div>
        </div>
        <div class="col-md-3">
          <div class="text-muted">Lock</div>
          <div class="fw-semibold">{{ $billing->locked_at ? 'Locked' : 'Editable' }}</div>
        </div>
        <div class="col-md-3">
          <div class="text-muted">Total</div>
          <div class="fw-bold">{{ number_format((float) $billing->total, 2) }}</div>
        </div>
        @if($billing->status === 'void')
          <div class="col-md-9">
            <div class="text-muted">Void Reason</div>
            <div>{{ $billing->void_reason ?? '-' }}</div>
          </div>
        @endif
      </div>
      @if($npwpBlocked)
        <div class="alert alert-danger mt-3 mb-0">
          NPWP wajib diisi sebelum issue invoice.
        </div>
      @endif
    </div>
  </div>

  <form method="POST" action="{{ route('billings.update', $billing) }}">
    @csrf
    @method('PATCH')
    <div class="card mb-3">
      <div class="card-header">
        <h3 class="card-title">Lines</h3>
        @if($isEditable)
          <button class="btn btn-primary ms-auto" type="submit">Save Draft</button>
        @endif
      </div>
      <div class="table-responsive">
        <table class="table table-vcenter">
          <thead>
            <tr>
              <th>#</th>
              <th>Item</th>
              <th>Description</th>
              <th class="text-end">Qty</th>
              <th>Unit</th>
              <th class="text-end">Unit Price</th>
              <th class="text-end">Disc</th>
              <th class="text-end">Subtotal</th>
              <th class="text-end">Line Total</th>
            </tr>
          </thead>
          <tbody>
            @foreach($billing->lines as $i => $ln)
              @php
                $discLabel = ($ln->discount_type === 'percent')
                  ? rtrim(rtrim(number_format((float) $ln->discount_value, 2, '.', ''), '0'), '.') . '%'
                  : number_format((float) $ln->discount_amount, 2);
              @endphp
              <tr data-line-row>
                <td>{{ $i + 1 }}</td>
                <td>
                  <div class="fw-semibold">{{ $ln->name }}</div>
                  <input type="hidden" name="lines[{{ $i }}][id]" value="{{ $ln->id }}">
                  <input type="hidden" name="lines[{{ $i }}][name]" value="{{ $ln->name }}">
                </td>
                <td>
                  @if($isEditable)
                    <textarea name="lines[{{ $i }}][description]" class="form-control form-control-sm" rows="2">{{ $ln->description }}</textarea>
                  @else
                    {{ $ln->description ?? '-' }}
                  @endif
                </td>
                <td class="text-end">
                  @if($isEditable)
                    <input type="number" step="0.0001" min="0" name="lines[{{ $i }}][qty]" class="form-control form-control-sm text-end js-qty" value="{{ number_format((float) $ln->qty, 4, '.', '') }}">
                  @else
                    {{ number_format((float) $ln->qty, 4) }}
                  @endif
                </td>
                <td>
                  @if($isEditable)
                    <input type="text" name="lines[{{ $i }}][unit]" class="form-control form-control-sm" value="{{ $ln->unit }}">
                  @else
                    {{ $ln->unit ?? '-' }}
                  @endif
                </td>
                <td class="text-end">
                  @if($isEditable)
                    <input type="number" step="0.01" min="0" name="lines[{{ $i }}][unit_price]" class="form-control form-control-sm text-end js-price" value="{{ number_format((float) $ln->unit_price, 2, '.', '') }}">
                  @else
                    {{ number_format((float) $ln->unit_price, 2) }}
                  @endif
                </td>
                <td class="text-end">
                  <span class="text-muted">{{ $discLabel }}</span>
                  <input type="hidden" name="lines[{{ $i }}][discount_type]" value="{{ $ln->discount_type ?? 'amount' }}">
                  <input type="hidden" name="lines[{{ $i }}][discount_value]" value="{{ number_format((float) $ln->discount_value, 2, '.', '') }}">
                </td>
                <td class="text-end line-subtotal">{{ number_format((float) $ln->line_subtotal, 2) }}</td>
                <td class="text-end fw-semibold line-total">{{ number_format((float) $ln->line_total, 2) }}</td>
              </tr>
            @endforeach
          </tbody>
        </table>
      </div>
      <div class="card-body">
        <div class="row g-3">
          <div class="col-md-6">
            <label class="form-label">Notes</label>
            @if($isEditable)
              <textarea name="notes" class="form-control" rows="3">{{ $billing->notes }}</textarea>
            @else
              <div class="text-muted">{{ $billing->notes ?? '-' }}</div>
            @endif
          </div>
          <div class="col-md-6">
            <table class="table table-sm mb-0">
              <tr>
                <td>Subtotal</td>
                <td class="text-end" id="billingSubtotal">{{ number_format((float) $billing->subtotal, 2) }}</td>
              </tr>
              <tr>
                <td>Discount</td>
                <td class="text-end">
                  @if($isEditable)
                    <input type="number" step="0.01" min="0" name="discount_amount" class="form-control form-control-sm text-end" id="billingDiscount"
                      value="{{ number_format((float) $billing->discount_amount, 2, '.', '') }}">
                  @else
                    {{ number_format((float) $billing->discount_amount, 2) }}
                  @endif
                </td>
              </tr>
              <tr>
                <td>Tax</td>
                <td class="text-end">
                  <div class="d-flex justify-content-end align-items-center gap-2">
                    @if($isEditable)
                      <input type="number" step="0.01" min="0" max="100" name="tax_percent" class="form-control form-control-sm text-end" id="billingTaxPercent"
                        value="{{ number_format((float) $billing->tax_percent, 2, '.', '') }}">
                    @else
                      {{ number_format((float) $billing->tax_percent, 2) }}
                    @endif
                    <span class="text-muted">%</span>
                    <span id="billingTaxAmount">{{ number_format((float) $billing->tax_amount, 2) }}</span>
                  </div>
                </td>
              </tr>
              <tr class="fw-bold">
                <td>Total</td>
                <td class="text-end" id="billingTotal">{{ number_format((float) $billing->total, 2) }}</td>
              </tr>
            </table>
            <small class="text-muted d-block mt-2">* Perhitungan final disimpan di server.</small>
          </div>
        </div>
      </div>
    </div>
  </form>

  @if($canIssueInvoice)
    <div class="modal fade" id="modalIssueInvoice" tabindex="-1" aria-hidden="true">
      <div class="modal-dialog">
        <form class="modal-content" method="POST" action="{{ route('billings.issue-invoice', $billing) }}">
          @csrf
          <div class="modal-header">
            <h5 class="modal-title">Issue Invoice</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>
          <div class="modal-body">
            <div class="mb-3">
              <label class="form-label">Invoice Date</label>
              <input type="date" name="invoice_date" class="form-control" value="{{ now()->toDateString() }}">
            </div>
            <div class="text-muted small">
              Setelah issued, angka/lines akan terkunci dan AR aktif.
            </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Batal</button>
            <button type="submit" class="btn btn-success">Issue Invoice</button>
          </div>
        </form>
      </div>
    </div>
  @endif

  @if($billing->status !== 'void')
    <div class="modal fade" id="modalVoidBilling" tabindex="-1" aria-hidden="true">
      <div class="modal-dialog">
        <form class="modal-content" method="POST" action="{{ route('billings.void', $billing) }}">
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
</div>

@push('scripts')
@if($isEditable)
<script>
  (function () {
    const toNumber = (val) => {
      if (val === null || val === undefined) return 0;
      if (typeof val === 'number') return Number.isFinite(val) ? val : 0;
      let s = String(val).trim();
      if (!s) return 0;
      s = s.replace(/\s+/g, '');
      const commaCount = (s.match(/,/g) || []).length;
      const dotCount = (s.match(/\./g) || []).length;
      if (commaCount && dotCount) {
        if (s.lastIndexOf(',') > s.lastIndexOf('.')) {
          s = s.replace(/\./g, '').replace(',', '.');
        } else {
          s = s.replace(/,/g, '');
        }
      } else if (commaCount) {
        s = commaCount === 1 ? s.replace(',', '.') : s.replace(/,/g, '');
      } else if (dotCount > 1) {
        s = s.replace(/\./g, '');
      }
      const num = parseFloat(s);
      return Number.isFinite(num) ? num : 0;
    };

    const format = (val) => Number(val || 0).toLocaleString('id-ID', {minimumFractionDigits: 2, maximumFractionDigits: 2});

    const rows = Array.from(document.querySelectorAll('[data-line-row]'));
    const discountInput = document.getElementById('billingDiscount');
    const taxInput = document.getElementById('billingTaxPercent');
    const subtotalEl = document.getElementById('billingSubtotal');
    const taxAmountEl = document.getElementById('billingTaxAmount');
    const totalEl = document.getElementById('billingTotal');

    const recalc = () => {
      let subtotal = 0;
      rows.forEach((row) => {
        const qty = toNumber(row.querySelector('.js-qty')?.value);
        const price = toNumber(row.querySelector('.js-price')?.value);
        const discType = row.querySelector('input[name*="[discount_type]"]')?.value || 'amount';
        const discValue = toNumber(row.querySelector('input[name*="[discount_value]"]')?.value);
        const lineSubtotal = qty * price;
        const discAmt = discType === 'percent'
          ? Math.round(lineSubtotal * Math.min(Math.max(discValue, 0), 100) / 100 * 100) / 100
          : Math.min(Math.max(discValue, 0), lineSubtotal);
        const lineTotal = Math.max(lineSubtotal - discAmt, 0);
        row.querySelector('.line-subtotal').textContent = format(lineSubtotal);
        row.querySelector('.line-total').textContent = format(lineTotal);
        subtotal += lineTotal;
      });

      const discount = toNumber(discountInput?.value);
      const taxPct = Math.min(Math.max(toNumber(taxInput?.value), 0), 100);
      const taxBase = Math.max(subtotal - discount, 0);
      const taxAmount = Math.round(taxBase * (taxPct / 100) * 100) / 100;
      const total = taxBase + taxAmount;

      if (subtotalEl) subtotalEl.textContent = format(subtotal);
      if (taxAmountEl) taxAmountEl.textContent = format(taxAmount);
      if (totalEl) totalEl.textContent = format(total);
    };

    document.addEventListener('input', (e) => {
      if (e.target.closest('[data-line-row]') || e.target === discountInput || e.target === taxInput) {
        recalc();
      }
    });

    recalc();
  })();
</script>
@endif
@endpush
@endsection
