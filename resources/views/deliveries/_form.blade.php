@php
  /**
   * Build an array of line rows from the old input or the existing delivery lines.
   * If there are no lines yet, push a single empty row with default values.
   */
  $lineRows = collect(old('lines', $lines->map(function ($line) {
    if (is_array($line)) {
      return $line;
    }
    return [
      'item_id'           => $line->item_id,
      'item_variant_id'   => $line->item_variant_id,
      'description'       => $line->description,
      'unit'              => $line->unit,
      'qty'               => (float) $line->qty,
      'qty_requested'     => $line->qty_requested,
      'price_snapshot'    => $line->price_snapshot,
      'qty_backordered'   => $line->qty_backordered,
      'line_notes'        => $line->line_notes,
      'quotation_line_id' => $line->quotation_line_id,
      'sales_order_line_id' => $line->sales_order_line_id,
    ];
  })->toArray()))->values();
  if ($lineRows->isEmpty()) {
    $lineRows->push([
      'item_id' => null,
      'item_variant_id' => null,
      'description' => null,
      'unit' => null,
      'qty' => 1,
      'qty_requested' => null,
      'price_snapshot' => null,
      'qty_backordered' => null,
      'line_notes' => null,
      'quotation_line_id' => null,
      'sales_order_line_id' => null,
    ]);
  }

  // determine if only one warehouse is available
  $singleWarehouse = $warehouses && $warehouses->count() === 1;
@endphp

<!-- hidden input for the related sales order id -->
<input type="hidden" name="sales_order_id" value="{{ old('sales_order_id', $delivery->sales_order_id) }}">
@if($delivery->sales_order_id && $delivery->salesOrder)
  <div class="alert alert-info d-flex align-items-center gap-2">
    <span class="badge bg-blue-lt">SO</span>
    <div>Based on Sales Order <a href="{{ route('sales-orders.show', $delivery->salesOrder) }}" target="_blank">{{ $delivery->salesOrder->so_number ?? ('#'.$delivery->sales_order_id) }}</a></div>
  </div>
@endif

<div class="row g-3 mb-3">
  <div class="col-md-3">
    <label class="form-label">Company</label>
    <select name="company_id" class="form-select" required>
      @foreach($companies as $company)
        <option value="{{ $company->id }}" @selected(old('company_id', $delivery->company_id) == $company->id)>
          {{ $company->alias ?? $company->name }}
        </option>
      @endforeach
    </select>
  </div>
  <div class="col-md-3">
    <label class="form-label">Customer</label>
    <select name="customer_id" class="form-select" required>
      <option value="">-- pilih --</option>
      @foreach($customers as $customer)
        <option value="{{ $customer->id }}" @selected(old('customer_id', $delivery->customer_id) == $customer->id)>
          {{ $customer->name }}
        </option>
      @endforeach
    </select>
  </div>
  <div class="col-md-3">
    <label class="form-label">Warehouse</label>
    @if($singleWarehouse)
      <!-- When only one warehouse exists, prefill the value and show it as read only -->
      <input type="hidden" name="warehouse_id" value="{{ $warehouses->first()->id }}">
      <input type="text" class="form-control" value="{{ $warehouses->first()->name }}{{ $warehouses->first()->allow_negative_stock ? ' (allow -)' : '' }}" readonly disabled>
    @else
      <select name="warehouse_id" class="form-select">
        <option value="">-- pilih --</option>
        @foreach($warehouses as $warehouse)
          <option value="{{ $warehouse->id }}" @selected(old('warehouse_id', $delivery->warehouse_id) == $warehouse->id)>
            {{ $warehouse->name }}{{ $warehouse->allow_negative_stock ? ' (allow -)' : '' }}
          </option>
        @endforeach
      </select>
    @endif
  </div>
  <div class="col-md-3">
    <label class="form-label">Delivery Date</label>
    <input type="date" name="date" class="form-control" value="{{ old('date', optional($delivery->date)->format('Y-m-d')) }}" required>
  </div>
  <div class="col-md-3">
    <label class="form-label">Reference</label>
    <input type="text" name="reference" class="form-control" value="{{ old('reference', $delivery->reference) }}">
  </div>
  <div class="col-md-3">
    <label class="form-label">Recipient</label>
    <input type="text" name="recipient" class="form-control" value="{{ old('recipient', $delivery->recipient) }}">
  </div>
  <div class="col-md-6">
    <label class="form-label">Address</label>
    <textarea name="address" class="form-control" rows="2">{{ old('address', $delivery->address) }}</textarea>
  </div>
  <div class="col-12">
    <label class="form-label">Notes</label>
    <textarea name="notes" class="form-control" rows="2">{{ old('notes', $delivery->notes) }}</textarea>
  </div>
</div>

<div class="card mb-3" id="delivery-lines-card">
  <div class="card-header d-flex justify-content-between align-items-center">
    <h3 class="card-title mb-0">Delivery Lines</h3>
    @if(!$delivery->sales_order_id)
      <button type="button" class="btn btn-sm btn-outline-primary" id="add-line">
        <i class="ti ti-plus"></i> Item
      </button>
    @endif
  </div>
  <div class="table-responsive">
    <table class="table card-table" id="lines-table">
      <thead>
        <tr>
          <th style="width:25%">Item</th>
          <th>Description</th>
          <th style="width:10%" class="text-end">Qty</th>
          <th style="width:8%">Unit</th>
          <th style="width:10%" class="text-end">Requested</th>
          <th style="width:10%" class="text-end">Backorder</th>
          <th style="width:10%" class="text-end">Price</th>
          <th style="width:16%">Notes</th>
          <th></th>
        </tr>
      </thead>
      <tbody>
        @foreach($lineRows as $idx => $row)
          @include('deliveries._line_row', ['index' => $idx, 'row' => $row, 'items' => $items, 'variants' => $variants])
        @endforeach
      </tbody>
    </table>
  </div>
  <div class="card-footer text-muted small">
    Stok gudang akan divalidasi saat posting. Periksa kembali agar tidak minus.
  </div>
</div>

@push('scripts')
<script>
  (function () {
    const tableBody = document.querySelector('#lines-table tbody');
    const addBtn = document.getElementById('add-line');
    const template = document.getElementById('line-row-template');
    const warehouseSelect = document.querySelector('select[name="warehouse_id"]');
    const stockMap = Object.assign({}, @json($stocks ?? []));
    const stockFormatter = new Intl.NumberFormat('id-ID', { minimumFractionDigits: 0, maximumFractionDigits: 2 });

    function makeKey(warehouseId, itemId, variantId) {
      return [warehouseId || 0, itemId || 0, variantId || 0].join('::');
    }

    function lookupStock(warehouseId, itemId, variantId) {
      if (!warehouseId || !itemId) {
        return null;
      }
      const value = stockMap[makeKey(warehouseId, itemId, variantId)];
      return typeof value === 'number' ? value : null;
    }

    function updateRowStock(row) {
      const badge = row.querySelector('[data-stock-label]');
      if (!badge) return;
      const whSelect = warehouseSelect;
      const warehouseId = whSelect ? whSelect.value : '';
      const itemSelect = row.querySelector('.line-item');
      const variantSelect = row.querySelector('.line-variant');
      const itemId = itemSelect ? itemSelect.value : '';
      const variantId = variantSelect ? variantSelect.value : '';
      const stock = lookupStock(warehouseId, itemId, variantId);
      badge.textContent = stock === null ? '—' : stockFormatter.format(stock);
    }

    function updateAllRowStocks() {
      tableBody.querySelectorAll('tr[data-index]').forEach(updateRowStock);
    }

    function reindexRows() {
      Array.from(tableBody.querySelectorAll('tr[data-index]')).forEach((row, newIndex) => {
        row.dataset.index = newIndex;
        row.querySelectorAll('input, select, textarea').forEach(input => {
          const name = input.getAttribute('name');
          if (!name) return;
          input.setAttribute('name', name.replace(/lines\[\d+\]/, 'lines[' + newIndex + ']'));
        });
      });
    }

    function bindRowEvents(row) {
      const removeBtn = row.querySelector('.remove-line');
      if (removeBtn) {
        removeBtn.addEventListener('click', () => {
          if (tableBody.children.length === 1) {
            row.querySelectorAll('input, select, textarea').forEach(el => el.value = '');
            const qtyInput = row.querySelector('.line-qty');
            if (qtyInput) qtyInput.value = 1;
            updateRowStock(row);
            return;
          }
          row.remove();
          reindexRows();
          updateAllRowStocks();
        });
      }

      const qtyInput = row.querySelector('.line-qty');
      const requestedInput = row.querySelector('.line-requested');
      const backorderInput = row.querySelector('.line-backorder');
      if (qtyInput && requestedInput && !requestedInput.value) {
        requestedInput.value = qtyInput.value;
      }

      // === Auto-hitung backorder = max(requested - qty, 0)
      const recalcBackorder = () => {
        if (!qtyInput || !requestedInput || !backorderInput) return;
        const q = parseInt(qtyInput.value || '0', 10);
        const r = parseInt(requestedInput.value || '0', 10);
        backorderInput.value = Math.max(r - q, 0);
      };
      qtyInput && qtyInput.addEventListener('input', recalcBackorder);
      recalcBackorder();

      const itemSelect = row.querySelector('.line-item');
      const variantSelect = row.querySelector('.line-variant');
      if (itemSelect && variantSelect) {
        const allOptions = Array.from(variantSelect.querySelectorAll('option[data-item]'));
        const filterVariants = () => {
          const itemId = itemSelect.value;
          const currentVariant = variantSelect.value;
          let variantStillValid = false;
          allOptions.forEach(opt => {
            const match = !itemId || opt.dataset.item === itemId;
            opt.hidden = !match;
            if (match && opt.value === currentVariant) {
              variantStillValid = true;
            }
          });
          if (!variantStillValid) {
            variantSelect.value = '';
          }
          updateRowStock(row);
        };
        itemSelect.addEventListener('change', filterVariants);
        variantSelect.addEventListener('change', () => updateRowStock(row));
        filterVariants();
      } else {
        updateRowStock(row);
      }
    }

    addBtn && addBtn.addEventListener('click', () => {
      const idx = tableBody.children.length;
      const clone = template.content.cloneNode(true);
      const row = clone.querySelector('tr');
      row.dataset.index = idx;
      row.querySelectorAll('input, select, textarea').forEach(input => {
        const name = input.getAttribute('data-name');
        if (name) {
          input.setAttribute('name', 'lines[' + idx + '][' + name + ']');
        }
      });
      tableBody.appendChild(clone);
      const appendedRow = tableBody.lastElementChild;
      bindRowEvents(appendedRow);
      updateRowStock(appendedRow);
    });

    warehouseSelect && warehouseSelect.addEventListener('change', updateAllRowStocks);

    Array.from(tableBody.querySelectorAll('tr[data-index]')).forEach(row => {
      bindRowEvents(row);
      updateRowStock(row);
    });
    updateAllRowStocks();
  })();
</script>
@endpush

{{-- ========== ADDON: PRE-FLIGHT VALIDATOR (nomor 4) — tidak mengubah script di atas ========== --}}
@push('scripts')
<script>
(function(){
  // Duplikasi peta stok agar terisolasi dari IIFE sebelumnya
  const stockMap = Object.assign({}, @json($stocks ?? []));
  const stockFmt = new Intl.NumberFormat('id-ID', { maximumFractionDigits: 2 });
  const tableBody = document.querySelector('#lines-table tbody');
  const whSelect  = document.querySelector('select[name="warehouse_id"]');

  function key(wh, item, variant){ return [wh||0, item||0, variant||0].join('::'); }
  function lookup(wh, item, variant){
    if(!wh || !item) return null;
    const val = stockMap[key(wh,item,variant)];
    return (typeof val === 'number') ? val : null;
  }

  function readIds(row){
    // Prefer dataset (dari _line_row untuk SO lines), fallback ke select di template
    const dsItem   = row.dataset.itemId || '';
    const dsVar    = row.dataset.variantId || '';
    const itemSel  = row.querySelector('.line-item');
    const varSel   = row.querySelector('.line-variant');
    const itemId   = dsItem || (itemSel ? itemSel.value : '');
    const variantId= dsVar  || (varSel  ? varSel.value  : '');
    return { itemId, variantId };
  }

  function validateRow(row){
    const qtyEl  = row.querySelector('.line-qty');
    const hintEl = row.querySelector('[data-stock-hint]') || row.querySelector('[data-stock-label]');
    const q = parseFloat(qtyEl?.value || '0');
    const wh = whSelect ? whSelect.value : '';
    const { itemId, variantId } = readIds(row);
    const avail = lookup(wh, itemId, variantId);

    // Reset state
    qtyEl && qtyEl.classList.remove('is-invalid');
    if(hintEl) hintEl.textContent = (avail === null) ? '—' : stockFmt.format(avail);

    // If we know stock and qty exceeds available, mark invalid + show deficit
    if(avail !== null && q > avail + 1e-9){
      const deficit = Math.max(0, +(q - avail).toFixed(3));
      qtyEl && qtyEl.classList.add('is-invalid');
      if(hintEl) hintEl.textContent = `Kurang ${deficit} (tersedia ${stockFmt.format(avail)})`;
      return false;
    }
    return true;
  }

  function validateAll(){
    const rows = [...tableBody.querySelectorAll('tr[data-index]')];
    let ok = true;
    rows.forEach(row => { ok = validateRow(row) && ok; });
    // Opsional: disable tombol Post jika tersedia (tidak mengubah markup yang ada)
    const btnPost = document.getElementById('btnPost') || document.querySelector('[data-btn-post]');
    if(btnPost) btnPost.disabled = !ok;
  }

  // Bind events non-invasively
  document.addEventListener('input', (e)=>{
    if(e.target && e.target.classList.contains('line-qty')) validateAll();
  });
  whSelect && whSelect.addEventListener('change', validateAll);
  document.addEventListener('DOMContentLoaded', validateAll);
  // Initial kick
  validateAll();
})();
</script>
@endpush

<template id="line-row-template">
  <tr data-index="__INDEX__">
    <td>
      <!-- Combined item + variant select: value is the item id, variant will be selected in secondary select for internal use if necessary -->
      <select class="form-select line-item" data-name="item_id">
        <option value="">-- pilih --</option>
        @foreach($items as $item)
          <option value="{{ $item->id }}">{{ $item->name }}</option>
        @endforeach
      </select>
      <!-- stock indicator -->
      <div class="small text-muted mt-1">Stock: <span data-stock-label>-</span></div>
      <input type="hidden" data-name="quotation_line_id">
      <input type="hidden" data-name="sales_order_line_id">
    </td>
    <td><input type="text" class="form-control" data-name="description"></td>
    <td><input type="number" step="1" min="0" class="form-control text-end line-qty" value="1" data-name="qty"></td>
    <td><input type="text" class="form-control" data-name="unit"></td>
    <td><input type="number" step="1" min="0" class="form-control text-end line-requested" data-name="qty_requested" readonly></td>
    <td><input type="number" step="1" min="0" class="form-control text-end line-backorder" data-name="qty_backordered" readonly></td>
    <td><input type="number" step="0.01" min="0" class="form-control text-end" data-name="price_snapshot"></td>
    <td><input type="text" class="form-control" data-name="line_notes"></td>
    <td class="text-end"><button type="button" class="btn btn-sm btn-outline-danger remove-line"><i class="ti ti-x"></i></button></td>
  </tr>
</template>
