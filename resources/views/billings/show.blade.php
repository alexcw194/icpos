@extends('layouts.tabler')

@section('content')
@php
  $isNew = !$billing->exists;
  $billingStatus = $billing->status ?? 'draft';
  $badgeMap = [
    'draft' => ['Draft','bg-secondary-lt text-dark'],
    'sent' => ['Sent','bg-blue-lt text-dark'],
    'void' => ['Void','bg-red-lt text-dark'],
  ];
  [$statusLabel, $statusClass] = $badgeMap[$billingStatus] ?? [$billingStatus,'bg-secondary-lt'];
  $isEditable = $billing->isEditable();
  $hasProforma = !empty($billing->pi_number);
  $hasInvoice = !empty($billing->inv_number);
  $canIssueProforma = !$isNew && !$billing->isLocked() && $billing->status !== 'void' && !$hasProforma && !$hasInvoice;
  $so = $billing->salesOrder;
  $isProjectSo = strtolower((string) ($so->po_type ?? 'goods')) === 'project';
  $npwpBlocked = $so && $so->npwp_required && ($so->npwp_status ?? 'missing') !== 'ok';
  $canIssueInvoice = !$isNew && !$billing->isLocked() && !$hasInvoice && $billing->status !== 'void' && !$npwpBlocked;
  $displayNumber = $billing->inv_number ?? $billing->pi_number ?? ($isNew ? 'DRAFT' : 'DRAFT-'.$billing->id);
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
      @if(!$isNew)
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

  @if($isEditable && !$isNew)
    <form id="billing-cancel" method="POST" action="{{ route('billings.cancel', $billing) }}" class="d-none">
      @csrf
    </form>
  @endif

  <form method="POST" action="{{ $isNew ? route('billings.store-from-so', $billing->salesOrder ?? $billing->sales_order_id) : route('billings.update', $billing) }}">
    @csrf
    @if(!$isNew)
      @method('PATCH')
    @endif
    <div class="card mb-3">
      <div class="card-header">
        <h3 class="card-title">Lines</h3>
        @if($isEditable)
          @if($isNew)
            <a class="btn btn-outline-secondary ms-auto me-2"
              href="{{ route('sales-orders.show', $billing->salesOrder ?? $billing->sales_order_id) }}">Cancel</a>
          @else
            <button class="btn btn-outline-secondary ms-auto me-2" type="submit" form="billing-cancel">Cancel</button>
          @endif
          <button class="btn btn-primary" type="submit">Save Draft</button>
        @endif
      </div>
      <div class="table-responsive">
        <table class="table table-vcenter">
          <thead>
            <tr>
              <th>#</th>
              <th>Item</th>
              @unless($isProjectSo)
                <th>Description</th>
              @endunless
              <th class="text-end">Qty</th>
              <th>Unit</th>
              <th class="text-end">{{ $isProjectSo ? 'Price' : 'Unit Price' }}</th>
              @if($isProjectSo)
                <th class="text-end">Material</th>
                <th class="text-end">Labor Unit</th>
                <th class="text-end">Labor</th>
              @endif
              <th class="text-end">Disc</th>
              @unless($isProjectSo)
                <th class="text-end">Subtotal</th>
              @endunless
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
                  @if(!$isNew)
                    <input type="hidden" name="lines[{{ $i }}][id]" value="{{ $ln->id }}">
                  @else
                    <input type="hidden" name="lines[{{ $i }}][sales_order_line_id]" value="{{ $ln->sales_order_line_id }}">
                  @endif
                  <input type="hidden" name="lines[{{ $i }}][name]" value="{{ $ln->name }}">
                </td>
                @unless($isProjectSo)
                  <td>
                    @if($isEditable)
                      <textarea name="lines[{{ $i }}][description]" class="form-control form-control-sm" rows="2">{{ $ln->description }}</textarea>
                    @else
                      {{ $ln->description ?? '-' }}
                    @endif
                  </td>
                @endunless
                <td class="text-end">
                  @if($isEditable)
                    <input type="number" step="0.01" min="0" name="lines[{{ $i }}][qty]" class="form-control form-control-sm text-end js-qty" value="{{ number_format((float) $ln->qty, 2, '.', '') }}">
                  @else
                    {{ number_format((float) $ln->qty, 2) }}
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
                    <input type="text" inputmode="decimal" name="lines[{{ $i }}][unit_price]" class="form-control form-control-sm text-end js-price" value="{{ number_format((float) $ln->unit_price, 2, ',', '.') }}">
                  @else
                    {{ number_format((float) $ln->unit_price, 2) }}
                  @endif
                </td>
                @if($isProjectSo)
                  <td class="text-end line-material">{{ number_format((float) ($ln->material_total ?? 0), 2) }}</td>
                  <td class="text-end">
                    @if($isEditable)
                      <input type="text" inputmode="decimal" name="lines[{{ $i }}][labor_unit]" class="form-control form-control-sm text-end js-labor-unit" value="{{ number_format((float) ($ln->labor_unit ?? 0), 2, ',', '.') }}">
                    @else
                      {{ number_format((float) ($ln->labor_unit ?? 0), 2) }}
                    @endif
                  </td>
                  <td class="text-end line-labor">{{ number_format((float) ($ln->labor_total ?? 0), 2) }}</td>
                @endif
                <td class="text-end">
                  <span class="text-muted">{{ $discLabel }}</span>
                  <input type="hidden" name="lines[{{ $i }}][discount_type]" value="{{ $ln->discount_type ?? 'amount' }}">
                  <input type="hidden" name="lines[{{ $i }}][discount_value]" value="{{ number_format((float) $ln->discount_value, 2, '.', '') }}">
                </td>
                @unless($isProjectSo)
                  <td class="text-end line-subtotal">{{ number_format((float) $ln->line_subtotal, 2) }}</td>
                @endunless
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

  @if(!$isNew && $billing->status !== 'void')
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
    const isProjectSo = @json($isProjectSo);
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
        const laborUnit = isProjectSo ? toNumber(row.querySelector('.js-labor-unit')?.value) : 0;
        const discType = row.querySelector('input[name*="[discount_type]"]')?.value || 'amount';
        const discValue = toNumber(row.querySelector('input[name*="[discount_value]"]')?.value);
        const materialTotal = qty * price;
        const laborTotal = qty * laborUnit;
        const lineSubtotal = isProjectSo ? (materialTotal + laborTotal) : materialTotal;
        const discAmt = discType === 'percent'
          ? Math.round(lineSubtotal * Math.min(Math.max(discValue, 0), 100) / 100 * 100) / 100
          : Math.min(Math.max(discValue, 0), lineSubtotal);
        const lineTotal = Math.max(lineSubtotal - discAmt, 0);
        if (isProjectSo) {
          const materialEl = row.querySelector('.line-material');
          const laborEl = row.querySelector('.line-labor');
          if (materialEl) materialEl.textContent = format(materialTotal);
          if (laborEl) laborEl.textContent = format(laborTotal);
        } else {
          const subtotalCell = row.querySelector('.line-subtotal');
          if (subtotalCell) subtotalCell.textContent = format(lineSubtotal);
        }
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
