@extends('layouts.tabler')

@section('content')
<div class="container-xl">
  @php
    $defaultTaxMode = old('tax_mode');
    if ($defaultTaxMode === null) {
      $taxPercent = (float) ($po->tax_percent ?? 0);
      if ($taxPercent <= 0) {
        $defaultTaxMode = 'none';
      } else {
        $defaultTaxMode = ((float) ($po->total ?? 0) <= (float) ($po->subtotal ?? 0)) ? 'include' : 'exclude';
      }
    }

    $renderLines = (is_array($linesData) && count($linesData) > 0)
      ? $linesData
      : [[
          'item_id' => '',
          'item_variant_id' => '',
          'item_label' => '',
          'qty_ordered' => '',
          'uom' => 'PCS',
          'unit_price' => '',
        ]];
  @endphp
  <form method="POST" action="{{ route('po.update', $po) }}">
    @method('PUT')
    @csrf
    <div class="card">
      <div class="card-header">
        <h3 class="card-title">
          Edit Purchase Order
        </h3>
      </div>
      <div class="card-body">
        <div class="row g-3">
          <div class="col-md-3">
            <label class="form-label">Company</label>
            <select name="company_id" class="form-select" required>
              @foreach($companies as $c)
              <option value="{{ $c->id }}"
                data-taxable="{{ $c->is_taxable ? 1 : 0 }}"
                data-tax="{{ (float)($c->default_tax_percent ?? 0) }}"
                @selected((string)old('company_id', $defaultCompanyId) === (string)$c->id)>
                {{ $c->alias ?? $c->name }}
              </option>
              @endforeach
            </select>
          </div>
          <div class="col-md-3">
            <label class="form-label">Warehouse</label>
            <select name="warehouse_id" class="form-select">
              <option value="">-</option>
              @foreach($warehouses as $w)
              <option value="{{ $w->id }}" @selected((string)old('warehouse_id', $po->warehouse_id) === (string)$w->id)>{{ $w->name }}</option>
              @endforeach
            </select>
          </div>
          <div class="col-md-3">
            <label class="form-label">Supplier</label>
            <select name="supplier_id" class="form-select" required>
              <option value="">- pilih -</option>
              @foreach($suppliers as $s)
                <option value="{{ $s->id }}"
                        data-billing-terms="{{ e(json_encode($s->default_billing_terms ?? [])) }}"
                        @selected((string)old('supplier_id', $po->supplier_id) === (string)$s->id)>
                  {{ $s->name }}@if(!$s->is_active) (inactive)@endif
                </option>
              @endforeach
            </select>
          </div>
          <div class="col-md-3">
            <label class="form-label">PO Number</label>
            <input type="text" class="form-control" value="{{ $po->number }}" disabled>
          </div>
          <div class="col-md-3">
            <label class="form-label">PO Date</label>
            <input type="date" name="order_date" class="form-control" value="{{ old('order_date', $po->order_date ? \Illuminate\Support\Carbon::parse($po->order_date)->toDateString() : now()->toDateString()) }}">
          </div>
          <div class="col-12">
            <label class="form-label">Notes</label>
            <textarea name="notes" class="form-control" rows="2">{{ old('notes', $po->notes) }}</textarea>
          </div>
        </div>

        <hr class="my-4">

        @include('sales_orders._billing_terms_form', [
          'billingTermsData' => $billingTermsData,
          'topOptions' => $topOptions,
        ])

        <hr class="my-4">

        <div class="table-responsive">
          <table class="table" id="po-lines">
            <thead>
              <tr>
                <th style="width:40%">Item</th>
                <th style="width:10%">Qty</th>
                <th style="width:10%">UoM</th>
                <th style="width:18%">Unit Price</th>
                <th style="width:17%" class="text-end">Total Price</th>
                <th style="width:5%"></th>
              </tr>
            </thead>
            <tbody>
              @foreach($renderLines as $idx => $line)
              <tr>
                <td>
                  <input type="text" class="form-control po-item-search" placeholder="Cari item..." autocomplete="off" value="{{ old('lines.' . $idx . '.item_label', $line['item_label'] ?? '') }}">
                  <input type="hidden" name="lines[{{ $idx }}][item_id]" class="po-item-id" value="{{ old('lines.' . $idx . '.item_id', $line['item_id'] ?? '') }}">
                  <input type="hidden" name="lines[{{ $idx }}][item_variant_id]" class="po-variant-id" value="{{ old('lines.' . $idx . '.item_variant_id', $line['item_variant_id'] ?? '') }}">
                  <input type="hidden" name="lines[{{ $idx }}][sales_order_line_id]" class="po-so-line-id" value="{{ old('lines.' . $idx . '.sales_order_line_id', $line['sales_order_line_id'] ?? '') }}">
                </td>
                <td><input type="number" name="lines[{{ $idx }}][qty_ordered]" class="form-control text-end po-qty" step="0.0001" min="0" required value="{{ old('lines.' . $idx . '.qty_ordered', $line['qty_ordered'] ?? '') }}"></td>
                <td><input type="text" name="lines[{{ $idx }}][uom]" class="form-control po-uom" value="{{ old('lines.' . $idx . '.uom', $line['uom'] ?? 'PCS') }}"></td>
                <td>
                  <div class="input-group">
                    <span class="input-group-text">Rp</span>
                    <input type="number" name="lines[{{ $idx }}][unit_price]" class="form-control text-end po-unit-price" step="0.01" min="0" value="{{ old('lines.' . $idx . '.unit_price', $line['unit_price'] ?? '') }}">
                  </div>
                  <div class="text-muted small mt-1 po-last-buy"></div>
                </td>
                <td class="text-end">
                  <div class="fw-semibold po-line-total">Rp 0</div>
                </td>
                <td>
                  @if($idx === 0)
                    <button type="button" class="btn btn-sm btn-outline-secondary" onclick="addLine()">+</button>
                  @else
                    <button type="button" class="btn btn-sm btn-outline-danger" onclick="removeLine(this)">-</button>
                  @endif
                </td>
              </tr>
              @endforeach
            </tbody>
          </table>
        </div>

        <div class="row justify-content-end mt-3">
          <div class="col-md-6 col-lg-5">
            <div class="card">
              <div class="card-body">
                <table class="table mb-0">
                  <tr>
                    <td>Subtotal</td>
                    <td class="text-end" id="po_subtotal">Rp 0</td>
                  </tr>
                  <tr>
                    <td>
                      <div class="d-flex align-items-center gap-2 flex-wrap">
                        <span>Tax</span>
                        <select name="tax_mode" id="po_tax_mode" class="form-select form-select-sm" style="min-width:160px">
                          <option value="none" @selected($defaultTaxMode === 'none')>Tanpa Pajak</option>
                          <option value="exclude" @selected($defaultTaxMode === 'exclude')>Tambah Pajak</option>
                          <option value="include" @selected($defaultTaxMode === 'include')>Harga termasuk pajak</option>
                        </select>
                      </div>
                    </td>
                    <td class="text-end">
                      <div class="d-flex justify-content-end align-items-center gap-2 flex-wrap">
                        <div class="input-group input-group-sm" style="max-width:140px">
                          <input type="number" name="tax_percent" id="po_tax_percent" class="form-control text-end"
                            step="0.01" min="0" max="100" value="{{ old('tax_percent', (float)($po->tax_percent ?? 0)) }}">
                          <span class="input-group-text">%</span>
                        </div>
                        <span id="po_tax_amount">Rp 0</span>
                      </div>
                    </td>
                  </tr>
                  <tr class="fw-bold">
                    <td>Total</td>
                    <td class="text-end" id="po_total">Rp 0</td>
                  </tr>
                </table>
                <small class="text-muted d-block mt-2">* Perhitungan final tetap dilakukan di server saat disimpan.</small>
              </div>
            </div>
          </div>
        </div>
      </div>
      <div class="card-footer d-flex">
        <a href="{{ route('po.index') }}" class="btn btn-link">Cancel</a>
        <button class="btn btn-primary ms-auto" type="submit">Update PO</button>
      </div>
    </div>
  </form>
</div>
@push('scripts')
<script>
const ITEM_SEARCH_URL = @json(route('items.search', [], false));
const HAS_OLD_BILLING_TERMS = true;
const FALLBACK_BILLING_TERMS = [{ top_code: 'FINISH', percent: 100, due_trigger: 'on_invoice' }];
let lineIdx = {{ max(count($linesData), 1) }};
const moneyFormatter = new Intl.NumberFormat('id-ID', {
  minimumFractionDigits: 2,
  maximumFractionDigits: 2
});

const formatMoney = (val) => `Rp ${moneyFormatter.format(Number.isFinite(val) ? val : 0)}`;

function toNumber(val) {
  if (val === null || val === undefined) return 0;
  if (typeof val === 'number') return Number.isFinite(val) ? val : 0;
  let s = String(val).trim();
  if (!s) return 0;
  s = s.replace(/\s+/g, '');
  const hasComma = s.includes(',');
  const hasDot = s.includes('.');
  if (hasComma && hasDot) {
    if (s.lastIndexOf(',') > s.lastIndexOf('.')) {
      s = s.replace(/\./g, '').replace(',', '.');
    } else {
      s = s.replace(/,/g, '');
    }
  } else if (hasComma) {
    const parts = s.split(',');
    s = (parts.length === 2 && parts[1].length <= 2) ? s.replace(',', '.') : s.replace(/,/g, '');
  } else if (hasDot) {
    const parts = s.split('.');
    s = (parts.length === 2 && parts[1].length <= 2) ? s : s.replace(/\./g, '');
  }
  const num = parseFloat(s);
  return Number.isFinite(num) ? num : 0;
}

const clampPct = (val) => Math.min(Math.max(val, 0), 100);

function updateRowTotal(row) {
  if (!row) return 0;
  const qty = toNumber(row.querySelector('.po-qty')?.value);
  const price = toNumber(row.querySelector('.po-unit-price')?.value);
  const total = qty * price;
  const totalEl = row.querySelector('.po-line-total');
  if (totalEl) totalEl.textContent = formatMoney(total);
  return total;
}

function recalcTotals() {
  const subtotalEl = document.getElementById('po_subtotal');
  const taxAmountEl = document.getElementById('po_tax_amount');
  const totalEl = document.getElementById('po_total');
  if (!subtotalEl || !taxAmountEl || !totalEl) return;

  let subtotal = 0;
  document.querySelectorAll('#po-lines tbody tr').forEach((row) => {
    subtotal += updateRowTotal(row);
  });

  const taxModeEl = document.getElementById('po_tax_mode');
  const taxPctEl = document.getElementById('po_tax_percent');
  const mode = taxModeEl?.value || 'none';
  const taxPct = clampPct(toNumber(taxPctEl?.value));
  let taxAmount = 0;
  let total = subtotal;

  if (mode === 'exclude' && taxPct > 0) {
    taxAmount = subtotal * (taxPct / 100);
    total = subtotal + taxAmount;
  } else if (mode === 'include' && taxPct > 0) {
    taxAmount = subtotal * (taxPct / (100 + taxPct));
    total = subtotal;
  }

  subtotalEl.textContent = formatMoney(subtotal);
  taxAmountEl.textContent = formatMoney(taxAmount);
  totalEl.textContent = formatMoney(total);
}

function syncTaxMode() {
  const taxModeEl = document.getElementById('po_tax_mode');
  const taxPctEl = document.getElementById('po_tax_percent');
  if (!taxModeEl || !taxPctEl) return;
  if (taxModeEl.value === 'none') {
    taxPctEl.value = '0';
    taxPctEl.readOnly = true;
    taxPctEl.classList.add('bg-light');
  } else {
    taxPctEl.readOnly = false;
    taxPctEl.classList.remove('bg-light');
    if (toNumber(taxPctEl.value) === 0) {
      const companySelect = document.querySelector('select[name="company_id"]');
      const opt = companySelect?.selectedOptions?.[0];
      const taxable = Number(opt?.dataset.taxable || 0) === 1;
      const defTax = parseFloat(opt?.dataset.tax || '0') || 0;
      if (taxable && defTax > 0) {
        taxPctEl.value = defTax.toFixed(2);
      }
    }
  }
  recalcTotals();
}

function syncCompanyTax() {
  const companySelect = document.querySelector('select[name="company_id"]');
  const taxModeEl = document.getElementById('po_tax_mode');
  const taxPctEl = document.getElementById('po_tax_percent');
  if (!companySelect || !taxModeEl || !taxPctEl) return;
  const opt = companySelect.selectedOptions?.[0];
  const taxable = Number(opt?.dataset.taxable || 0) === 1;
  const defTax = parseFloat(opt?.dataset.tax || '0') || 0;

  if (!taxable) {
    taxModeEl.value = 'none';
    taxPctEl.value = '0';
  } else if (taxModeEl.value !== 'none' && (!taxPctEl.value || toNumber(taxPctEl.value) === 0)) {
    taxPctEl.value = defTax.toFixed(2);
  }

  syncTaxMode();
}

function getFallbackBillingTerms() {
  return (FALLBACK_BILLING_TERMS || []).map((row) => ({
    top_code: row.top_code || '',
    percent: row.percent ?? 0,
    due_trigger: row.due_trigger || '',
    offset_days: row.offset_days ?? '',
    day_of_month: row.day_of_month ?? '',
    note: row.note || '',
  }));
}

function normalizeBillingTermsRows(rows) {
  if (!Array.isArray(rows) || rows.length === 0) {
    return getFallbackBillingTerms();
  }

  const normalized = rows
    .map((row) => ({
      top_code: row?.top_code || '',
      percent: row?.percent ?? 0,
      due_trigger: row?.due_trigger || '',
      offset_days: row?.offset_days ?? '',
      day_of_month: row?.day_of_month ?? '',
      note: row?.note || '',
    }))
    .filter((row) => row.top_code !== '');

  return normalized.length > 0 ? normalized : getFallbackBillingTerms();
}

function parseSupplierBillingTerms(option) {
  if (!option) return getFallbackBillingTerms();
  const raw = option.dataset?.billingTerms || '[]';
  try {
    const parsed = JSON.parse(raw);
    return normalizeBillingTermsRows(parsed);
  } catch (e) {
    return getFallbackBillingTerms();
  }
}

function applySupplierBillingTerms({ force = false } = {}) {
  if (typeof window.setBillingTermsRows !== 'function') return;
  if (HAS_OLD_BILLING_TERMS && !force) return;

  const supplierSelect = document.querySelector('select[name="supplier_id"]');
  if (!supplierSelect) return;

  const option = supplierSelect.selectedOptions?.[0] || null;
  if (!option || !option.value) return;

  const terms = parseSupplierBillingTerms(option);
  window.setBillingTermsRows(terms);
}

function removeLine(btn) {
  const row = btn?.closest('tr');
  if (row) row.remove();
  recalcTotals();
}

function initItemPicker(input) {
  if (!input || input._ts) return;
  if (!window.TomSelect) return;

  const ts = new TomSelect(input, {
    valueField: 'uid',
    labelField: 'label',
    searchField: ['name','sku','label'],
    maxOptions: 30,
    minLength: 0,
    preload: 'focus',
    shouldLoad: () => true,
    create: false,
    persist: false,
    dropdownParent: 'body',
    load(query, cb) {
      const url = `${ITEM_SEARCH_URL}?purpose=purchase&q=${encodeURIComponent(query || '')}`;
      fetch(url, {credentials:'same-origin', headers:{'X-Requested-With':'XMLHttpRequest'}})
        .then(r => r.ok ? r.json() : [])
        .then(data => cb(Array.isArray(data) ? data : []))
        .catch(() => cb());
    },
    render: {
      option(d, esc) {
        const sku = d.sku ? `<small class="text-muted ms-2">${esc(d.sku)}</small>` : '';
        return `<div>${esc(d.label || d.name)} ${sku}</div>`;
      }
    },
    onChange(val) {
      const data = this.options[val];
      if (!data) return;
      const row = input.closest('tr');
      if (!row) return;
      const itemIdEl = row.querySelector('.po-item-id');
      const variantIdEl = row.querySelector('.po-variant-id');
      const lastBuyEl = row.querySelector('.po-last-buy');
      const uomEl = row.querySelector('.po-uom');
      const priceEl = row.querySelector('.po-unit-price');
      const qtyEl = row.querySelector('.po-qty');

      if (itemIdEl) itemIdEl.value = data.item_id || '';
      if (variantIdEl) variantIdEl.value = data.variant_id || '';
      if (uomEl) uomEl.value = (data.unit_code || 'PCS');
      if (priceEl) priceEl.value = (data.purchase_price ?? '');
      if (qtyEl && (!qtyEl.value || qtyEl.value === '0')) qtyEl.value = '1';
      const purchasePrice = parseFloat((data.purchase_price ?? '').toString().replace(',', '.')) || 0;
      const source = (data.purchase_price_source || '').toString().toLowerCase();
      if (lastBuyEl) {
        if (purchasePrice > 0 && data.purchase_price_date && (source === 'variant_last' || source === 'item_last')) {
          lastBuyEl.textContent = `Last Approved PO: ${data.purchase_price_date}`;
        } else if (source === 'variant_default' || source === 'item_default') {
          lastBuyEl.textContent = 'Base Cost';
        } else {
          lastBuyEl.textContent = '';
        }
      }
      recalcTotals();
    }
  });
  input._ts = ts;
}

function addLine() {
  const tbody = document.querySelector('#po-lines tbody');
  const tr = document.createElement('tr');
  tr.innerHTML = `
    <td>
      <input type="text" class="form-control po-item-search" placeholder="Cari item..." autocomplete="off">
      <input type="hidden" name="lines[${lineIdx}][item_id]" class="po-item-id">
      <input type="hidden" name="lines[${lineIdx}][item_variant_id]" class="po-variant-id">
      <input type="hidden" name="lines[${lineIdx}][sales_order_line_id]" class="po-so-line-id">
    </td>
    <td><input type="number" name="lines[${lineIdx}][qty_ordered]" class="form-control text-end po-qty" step="0.0001" min="0" required></td>
    <td><input type="text" name="lines[${lineIdx}][uom]" class="form-control po-uom" value="PCS"></td>
    <td>
      <div class="input-group">
        <span class="input-group-text">Rp</span>
        <input type="number" name="lines[${lineIdx}][unit_price]" class="form-control text-end po-unit-price" step="0.01" min="0">
      </div>
      <div class="text-muted small mt-1 po-last-buy"></div>
    </td>
    <td class="text-end">
      <div class="fw-semibold po-line-total">Rp 0</div>
    </td>
    <td><button type="button" class="btn btn-sm btn-outline-danger" onclick="removeLine(this)">-</button></td>`;
  tbody.appendChild(tr);
  initItemPicker(tr.querySelector('.po-item-search'));
  lineIdx++;
  recalcTotals();
}

document.querySelectorAll('.po-item-search').forEach(initItemPicker);

const linesTable = document.getElementById('po-lines');
if (linesTable) {
  linesTable.addEventListener('input', (e) => {
    if (e.target.classList.contains('po-qty') || e.target.classList.contains('po-unit-price')) {
      recalcTotals();
    }
  });
}

document.getElementById('po_tax_mode')?.addEventListener('change', syncTaxMode);
document.getElementById('po_tax_percent')?.addEventListener('input', recalcTotals);
document.querySelector('select[name="company_id"]')?.addEventListener('change', syncCompanyTax);
document.querySelector('select[name="supplier_id"]')?.addEventListener('change', () => {
  applySupplierBillingTerms({ force: true });
});

if (!HAS_OLD_BILLING_TERMS) {
  applySupplierBillingTerms({ force: true });
}

syncCompanyTax();
recalcTotals();
</script>
@endpush
@endsection
