@extends('layouts.tabler')

@section('content')
<div class="container-xl">
  <form method="POST" action="{{ route('po.store') }}">
    @csrf
    <div class="card">
      <div class="card-header">
        <h3 class="card-title">
          Create Purchase Order
        </h3>
      </div>
      <div class="card-body">
        <div class="row g-3">
          <div class="col-md-3">
            <label class="form-label">Company</label>
            <select name="company_id" class="form-select" required>
              @foreach($companies as $c)
              <option value="{{ $c->id }}" @selected((string)old('company_id', $defaultCompanyId) === (string)$c->id)>
                {{ $c->alias ?? $c->name }}
              </option>
              @endforeach
            </select>
          </div>
          <div class="col-md-3">
            <label class="form-label">Warehouse</label>
            <select name="warehouse_id" class="form-select">
              <option value="">—</option>
              @foreach($warehouses as $w)
              <option value="{{ $w->id }}">{{ $w->name }}</option>
              @endforeach
            </select>
          </div>
          <div class="col-md-3">
            <label class="form-label">Supplier</label>
            <select name="supplier_id" class="form-select" required>
              <option value="">— pilih —</option>
              @foreach($suppliers as $s)
                <option value="{{ $s->id }}" @selected((string)old('supplier_id') === (string)$s->id)>
                  {{ $s->name }}@if(!$s->is_active) (inactive)@endif
                </option>
              @endforeach
            </select>
          </div>
          <div class="col-md-3">
            <label class="form-label">PO Number</label>
            <input type="text" class="form-control" placeholder="Auto" disabled>
          </div>
          <div class="col-md-3">
            <label class="form-label">PO Date</label>
            <input type="date" name="order_date" class="form-control" value="{{ old('order_date', now()->toDateString()) }}">
          </div>
          <div class="col-12">
            <label class="form-label">Notes</label>
            <textarea name="notes" class="form-control" rows="2"></textarea>
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
                <th style="width:35%">Item</th>
                <th style="width:20%">Variant</th>
                <th style="width:10%">Qty</th>
                <th style="width:10%">UoM</th>
                <th style="width:15%">Unit Price</th>
                <th style="width:10%"></th>
              </tr>
            </thead>
            <tbody>
              <tr>
                <td>
                  <input type="text" class="form-control po-item-search" placeholder="Cari item..." autocomplete="off">
                  <input type="hidden" name="lines[0][item_id]" class="po-item-id">
                  <input type="hidden" name="lines[0][item_variant_id]" class="po-variant-id">
                </td>
                <td>
                  <input type="text" class="form-control po-variant-label" placeholder="—" readonly>
                </td>
                <td><input type="number" name="lines[0][qty_ordered]" class="form-control po-qty" step="0.0001" min="0" required></td>
                <td><input type="text" name="lines[0][uom]" class="form-control po-uom" value="PCS"></td>
                <td><input type="number" name="lines[0][unit_price]" class="form-control po-unit-price" step="0.01" min="0"></td>
                <td>
                  <button type="button" class="btn btn-sm btn-outline-secondary" onclick="addLine()">+</button>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
      <div class="card-footer d-flex">
        <a href="{{ route('po.index') }}" class="btn btn-link">Cancel</a>
        <button class="btn btn-primary ms-auto" type="submit">Save PO</button>
      </div>
    </div>
  </form>
</div>
@push('scripts')
<script>
const ITEM_SEARCH_URL = @json(route('items.search', [], false));
let lineIdx = 1;

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
      const variantLabelEl = row.querySelector('.po-variant-label');
      const uomEl = row.querySelector('.po-uom');
      const priceEl = row.querySelector('.po-unit-price');
      const qtyEl = row.querySelector('.po-qty');

      if (itemIdEl) itemIdEl.value = data.item_id || '';
      if (variantIdEl) variantIdEl.value = data.variant_id || '';
      if (variantLabelEl) variantLabelEl.value = data.variant_id ? (data.name || '') : '';
      if (uomEl) uomEl.value = (data.unit_code || 'PCS');
      if (priceEl) priceEl.value = (data.purchase_price ?? data.price ?? '');
      if (qtyEl && (!qtyEl.value || qtyEl.value === '0')) qtyEl.value = '1';
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
    </td>
    <td>
      <input type="text" class="form-control po-variant-label" placeholder="—" readonly>
    </td>
    <td><input type="number" name="lines[${lineIdx}][qty_ordered]" class="form-control po-qty" step="0.0001" min="0" required></td>
    <td><input type="text" name="lines[${lineIdx}][uom]" class="form-control po-uom" value="PCS"></td>
    <td><input type="number" name="lines[${lineIdx}][unit_price]" class="form-control po-unit-price" step="0.01" min="0"></td>
    <td><button type="button" class="btn btn-sm btn-outline-danger" onclick="this.closest('tr').remove()">−</button></td>`;
  tbody.appendChild(tr);
  initItemPicker(tr.querySelector('.po-item-search'));
  lineIdx++;
}

document.querySelectorAll('.po-item-search').forEach(initItemPicker);
</script>
@endpush
@endsection
