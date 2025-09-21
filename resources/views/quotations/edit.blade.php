@extends('layouts.tabler')

@section('content')
<div class="container-xl">
  <form action="{{ route('quotations.update', $quotation) }}" method="POST" class="card" id="qoForm">
    @csrf
    @method('PUT')

    <div class="card-header">
      <div>
        <div class="card-title">Edit Quotation {{ $quotation->number }}</div>
        <div class="text-muted">Tanggal dokumen: {{ $quotation->date?->format('d M Y') }}</div>
      </div>
    </div>

    {{-- ALERT VALIDATION --}}
    @if ($errors->any())
      <div class="alert alert-danger m-3">
        <div class="text-danger fw-bold mb-1">Periksa kembali input Anda:</div>
        <ul class="mb-0">
          @foreach ($errors->all() as $error)
            <li>{{ $error }}</li>
          @endforeach
        </ul>
      </div>
    @endif

    <div class="card-body">
      <div class="row g-3">
        {{-- COMPANY --}}
        <div class="col-md-4">
          <label class="form-label">Company <span class="text-danger">*</span></label>
          <select id="company_id" name="company_id" class="form-select" {{ $canChangeCompany ? '' : 'disabled' }} required>
            @foreach($companies as $co)
              <option value="{{ $co->id }}"
                      data-taxable="{{ $co->is_taxable ? 1 : 0 }}"
                      data-tax="{{ (float)($co->default_tax_percent ?? 0) }}"
                      data-valid-days="{{ (int)($co->default_valid_days ?? 30) }}"
                      {{ (old('company_id', $quotation->company_id) == $co->id) ? 'selected' : '' }}>
                {{ $co->alias ? $co->alias.' — ' : '' }}{{ $co->name }}
              </option>
            @endforeach
          </select>
          @unless($canChangeCompany)
            <input type="hidden" name="company_id" value="{{ $quotation->company_id }}">
          @endunless
          <div class="small mt-1 d-flex flex-column gap-1">
            <span id="companyTaxInfo"></span>
            <span id="companyValidInfo" class="text-muted"></span>
          </div>
        </div>

        {{-- CUSTOMER --}}
        <div class="col-md-4">
          <label class="form-label">Customer <span class="text-danger">*</span></label>
          <div class="d-flex gap-2">
            <select id="customer_id_select" name="customer_id" class="form-select" required>
              <option value="">— pilih customer —</option>
              @foreach($customers as $c)
                <option value="{{ $c->id }}" {{ old('customer_id', $quotation->customer_id)==$c->id?'selected':'' }}>
                  {{ $c->name }}
                </option>
              @endforeach
            </select>
            <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-toggle="modal" data-bs-target="#quickCustomerModal">+ New</button>
          </div>
        </div>

        {{-- SALES NAME --}}
        <div class="col-md-4">
          <label class="form-label">Sales Name</label>
          <select name="sales_user_id" id="sales_user_id" class="form-select">
            @php $selectedSalesId = old('sales_user_id', $quotation->sales_user_id ?? $defaultSalesUserId ?? null); @endphp
            @foreach($sales as $s)
              <option value="{{ $s->id }}" {{ (string)$selectedSalesId === (string)$s->id ? 'selected' : '' }}>
                {{ $s->name }}
              </option>
            @endforeach
          </select>
          <small class="form-hint">Nama sales tampil di print.</small>
        </div>

        {{-- TANGGAL --}}
        <div class="col-md-2">
          <label class="form-label">Tanggal <span class="text-danger">*</span></label>
          <input type="date" name="date" id="date" class="form-control"
                 value="{{ old('date', optional($quotation->date)->toDateString()) }}" required>
        </div>

        {{-- VALID UNTIL --}}
        <div class="col-md-2">
          <label class="form-label">Valid Until</label>
          <input type="date" name="valid_until" id="valid_until" class="form-control"
                 value="{{ old('valid_until', optional($quotation->valid_until)->toDateString()) }}">
          <small class="form-hint">Jika kosong, otomatis dari Company.</small>
        </div>

        {{-- CURRENCY --}}
        <input type="hidden" name="currency" value="IDR">

        {{-- PPN --}}
        <div class="col-md-2">
          <label class="form-label">PPN (%)</label>
          <input type="text" inputmode="decimal" class="form-control text-end"
                 id="tax_percent" name="tax_percent" placeholder="0"
                 value="{{ old('tax_percent', $quotation->tax_percent) }}">
          <small class="form-hint">Otomatis mengikuti Company</small>
        </div>

        {{-- NOTES --}}
        <div class="col-md-5">
          <label class="form-label">Notes</label>
          <textarea name="notes" class="form-control" rows="2">{{ old('notes', $quotation->notes) }}</textarea>
        </div>

        {{-- TERMS --}}
        <div class="col-md-5">
          <label class="form-label">Terms</label>
          <textarea name="terms" class="form-control" rows="2">{{ old('terms', $quotation->terms) }}</textarea>
        </div>
      </div>

      {{-- DISCOUNT MODE --}}
      @php
        $modeGuess = ($quotation->total_discount_value ?? 0) > 0 ? 'total'
                    : (($quotation->lines?->sum('discount_value') ?? 0) > 0 ? 'per_item' : 'total');
        $defaultMode = old('discount_mode', $quotation->discount_mode ?? $modeGuess);
      @endphp
      <div class="row g-3 align-items-center mt-3">
        <div class="col-md-6">
          <label class="form-label mb-1">Discount Mode</label>
          <div class="form-selectgroup">
            <label class="form-selectgroup-item">
              <input type="radio" name="discount_mode" value="total" class="form-selectgroup-input" @checked($defaultMode==='total')>
              <span class="form-selectgroup-label">Total (global)</span>
            </label>
            <label class="form-selectgroup-item">
              <input type="radio" name="discount_mode" value="per_item" class="form-selectgroup-input" @checked($defaultMode==='per_item')>
              <span class="form-selectgroup-label">Per Item (per-baris)</span>
            </label>
          </div>
          <small class="form-hint">Mode <b>Total</b>: satu diskon untuk seluruh quotation. Mode <b>Per Item</b>: tiap baris punya diskon sendiri.</small>
        </div>
      </div>

      <hr class="my-3">

      {{-- QUICK SEARCH --}}
      <div class="mb-2">
        <label class="form-label">Cari & pilih item</label>
        <input id="itemQuickSearch" type="text" placeholder="Ketik nama/SKU...">
        <div class="form-hint">Pilih hasil untuk mengisi baris input sementara di bawah, lalu klik <b>Tambah</b> untuk masuk ke list items.</div>
      </div>

      {{-- STAGING ROW --}}
      <div id="stageWrap" class="card mb-3">
        <div class="card-body py-2">
          <div class="row g-2 align-items-center">
            <div class="col-xxl-4 col-lg-5">
              <input id="stage_name" type="text" class="form-control" placeholder="Pilih item lewat kotak di atas" readonly>
              <input id="stage_item_id" type="hidden">
              <input id="stage_item_variant_id" type="hidden">
            </div>
            <div class="col-xxl-3 col-lg-4">
              <textarea id="stage_desc" class="form-control" rows="1" placeholder="Deskripsi (opsional)"></textarea>
            </div>
            <div class="col-auto" style="width:8ch">
              <input id="stage_qty" type="text" class="form-control text-end" inputmode="decimal" value="1">
            </div>
            <div class="col-auto" style="width:7ch">
              <input id="stage_unit" type="text" class="form-control" value="pcs" readonly>
            </div>
            <div class="col-xxl-2 col-lg-2">
              <input id="stage_price" type="text" class="form-control text-end" inputmode="decimal" placeholder="0">
            </div>
            <div class="col-auto">
              <button type="button" id="stage_add_btn" class="btn btn-primary">Tambah</button>
              <button type="button" id="stage_clear_btn" class="btn btn-link">Kosongkan</button>
            </div>
          </div>
        </div>
      </div>

      {{-- ITEMS TABLE (tanpa Add Row manual) --}}
      <div class="fw-bold mb-2">Items</div>
      <div class="table-responsive" id="quotation-lines">
        <table class="table table-sm" id="linesTable">
          <thead class="table-light">
            <tr>
              <th class="col-item">Item</th>
              <th class="col-desc">Deskripsi</th>
              <th class="col-qty text-end">Qty</th>
              <th class="col-unit">Unit</th>
              <th class="col-price text-end">Unit Price</th>
              <th class="col-disc" data-col="disc-input">Diskon (tipe + nilai)</th>
              <th class="col-subtotal text-end">Subtotal</th>
              <th class="col-disc-amount text-end">Disc Rp</th>
              <th class="col-total text-end">Line Total</th>
              <th class="col-actions"></th>
            </tr>
          </thead>
          <tbody id="linesBody"></tbody>
        </table>
      </div>

      {{-- DISKON TOTAL + TOTALS PREVIEW --}}
      <div class="row justify-content-end mt-3">
        <div class="col-md-7">
          <div class="card">
            <div class="card-body">
              <div class="row g-2 align-items-center mb-2" data-section="discount-total-controls">
                <div class="col-auto"><label class="form-label mb-0">Diskon Total</label></div>
                <div class="col-auto">
                  @php $tdt = old('total_discount_type', $quotation->total_discount_type ?? 'amount'); @endphp
                  <select name="total_discount_type" id="total_discount_type" class="form-select" style="min-width:160px">
                    <option value="amount" {{ $tdt=='amount'?'selected':'' }}>Nominal (IDR)</option>
                    <option value="percent" {{ $tdt=='percent'?'selected':'' }}>Persen (%)</option>
                  </select>
                </div>
                <div class="col">
                  <div class="input-group">
                    <input type="text" name="total_discount_value" id="total_discount_value" class="form-control text-end" inputmode="decimal" value="{{ old('total_discount_value', (string)($quotation->total_discount_value ?? '0')) }}">
                    <span class="input-group-text" id="totalDiscUnit">IDR</span>
                  </div>
                </div>
              </div>

              <table class="table mb-0">
                <tr><td>Subtotal (setelah diskon per-baris)</td><td class="text-end" id="v_lines_subtotal">Rp 0</td></tr>
                <tr><td>Diskon Total <span class="text-muted" id="v_total_disc_hint"></span></td><td class="text-end" id="v_total_discount_amount">Rp 0</td></tr>
                <tr><td>Dasar Pajak</td><td class="text-end" id="v_taxable_base">Rp 0</td></tr>
                <tr><td>PPN (<span id="v_tax_percent">0</span>%)</td><td class="text-end" id="v_tax_amount">Rp 0</td></tr>
                <tr class="fw-bold"><td>Grand Total</td><td class="text-end" id="v_total">Rp 0</td></tr>
              </table>
              <small class="text-muted d-block mt-2">* Perhitungan final tetap dilakukan di server saat disimpan.</small>
            </div>
          </div>
        </div>
      </div>
    </div>

    @include('layouts.partials.form_footer', [
      'cancelUrl'    => route('quotations.index', request()->only('q','company_id','status')),
      'cancelLabel'  => 'Batal',
      'cancelInline' => true,
      'buttons' => [
        ['type' => 'submit', 'label' => 'Simpan', 'class' => 'btn btn-primary'],
      ],
    ])
  </form>
</div>

@php
  // Seed baris: utamakan old('lines'); kalau kosong, pakai $quotation->lines
  $linesOld = collect(old('lines', []));
  if ($linesOld->isNotEmpty()) {
      $lineSeed = $linesOld->map(function($l){
          return [
              'item_id'        => $l['item_id']   ?? null,
              'item_variant_id' => $l['item_variant_id'] ?? null,
              'name'           => $l['name']      ?? '',
              'description'    => $l['description'] ?? '',
              'qty'            => (float)($l['qty'] ?? 0),
              'unit'           => $l['unit'] ?? 'pcs',
              'unit_price'     => (float)($l['unit_price'] ?? 0),
              'discount_type'  => $l['discount_type'] ?? 'amount',
              'discount_value' => (float)($l['discount_value'] ?? 0),
          ];
      })->values();
  } else {
      $lineSeed = $quotation->lines->map(function($l) {
          return [
              'item_id'        => null, // biarkan null (nama bebas); user bisa pilih item master via staging bila perlu
              'item_variant_id' => $l->item_variant_id ?? null, 
              'name'           => $l->name,
              'description'    => $l->description,
              'qty'            => (float) $l->qty,
              'unit'           => $l->unit ?? 'pcs',
              'unit_price'     => (float) $l->unit_price,
              'discount_type'  => $l->discount_type ?? 'amount',
              'discount_value' => (float) ($l->discount_value ?? 0),
          ];
      })->values();
  }
@endphp

{{-- TEMPLATE ROW (readonly name; diisi via staging row) --}}
<template id="rowTpl">
  <tr data-line-row class="qline">
    <td class="col-item">
      <input type="text" name="lines[__IDX__][name]" class="form-control form-control-sm q-item-name" placeholder="pilih dari kotak atas" readonly>
      <input type="hidden" name="lines[__IDX__][item_id]" class="q-item-id">
      <input type="hidden" name="lines[__IDX__][item_variant_id]" class="q-item-variant-id">
    </td>
    <td class="col-desc">
      <textarea name="lines[__IDX__][description]" class="form-control form-control-sm line_desc q-item-desc" rows="1"></textarea>
    </td>
    <td class="col-qty">
      <input type="text" name="lines[__IDX__][qty]" class="form-control form-control-sm text-end qty q-item-qty" inputmode="decimal" placeholder="0" maxlength="6">
    </td>
    <td class="col-unit">
      <input type="text" name="lines[__IDX__][unit]" class="form-control form-control-sm unit q-item-unit" value="pcs" readonly tabindex="-1">
    </td>
    <td class="col-price text-end">
      <input type="text" name="lines[__IDX__][unit_price]" class="form-control form-control-sm text-end price q-item-rate" inputmode="decimal" placeholder="0">
    </td>
    <td class="col-disc disc-cell">
      <div class="row g-2 align-items-center">
        <div class="col-auto">
          <select name="lines[__IDX__][discount_type]" class="form-select form-select-sm disc-type">
            <option value="amount">Nominal (IDR)</option>
            <option value="percent">Persen (%)</option>
          </select>
        </div>
        <div class="col-auto">
          <div class="input-group input-group-sm">
            <input type="text" name="lines[__IDX__][discount_value]" class="form-control text-end disc-value" inputmode="decimal" value="0">
            <span class="input-group-text disc-unit">IDR</span>
          </div>
        </div>
      </div>
    </td>
    <td class="col-subtotal text-end"><span class="line_subtotal_view">Rp 0</span></td>
    <td class="col-disc-amount text-end"><span class="line_disc_amount_view">Rp 0</span></td>
    <td class="col-total text-end"><span class="line_total_view">Rp 0</span></td>
    <td class="col-actions text-center">
      <button type="button" class="btn btn-link text-danger p-0 removeRowBtn" title="Hapus">&times;</button>
    </td>
  </tr>
</template>

{{-- ⬇️ Modal Quick Customer --}}
@include('customers._quick_modal')

@push('styles')
<style>
  #linesTable th, #linesTable td { vertical-align: middle; }
  #linesTable .col-item       { width: 22%; }
  #linesTable .col-desc       { width: 20%; }
  #linesTable .col-qty        { width: 6.5ch; }
  #linesTable .col-unit       { width: 7ch; }
  #linesTable .col-price      { width: 14%; }
  #linesTable .col-disc       { width: 16%; }
  #linesTable .col-subtotal   { width: 9%; }
  #linesTable .col-disc-amount{ width: 9%; }
  #linesTable .col-total      { width: 14%; }
  #linesTable .col-actions    { width: 4%; }
  #linesTable input.qty { max-width: 6.5ch; }
  #linesTable input.unit{ max-width: 7ch; }
  #linesTable .disc-cell .form-select{ min-width:120px; }
  #linesTable .disc-cell .disc-value { max-width: 8ch; }
  #linesTable .disc-cell .input-group-text.disc-unit{ min-width:46px; justify-content:center; }
  #linesTable .line_total_view{ font-weight:700; font-size:1.06rem; }
  #linesTable .line_subtotal_view{ font-size:.92rem; }
</style>
@endpush

@push('scripts')
@include('quotations._item_quicksearch_js')
<script>
(function () {
  // ---------- Helpers ----------
  function toNum(v){ if(v==null) return 0; v=String(v).trim(); if(v==='') return 0; v=v.replace(/\s/g,''); const c=v.includes(','), d=v.includes('.'); if(c&&d){v=v.replace(/\./g,'').replace(',', '.')} else {v=v.replace(',', '.')} const n=parseFloat(v); return isNaN(n)?0:n; }
  function rupiah(n){ try{return 'Rp '+new Intl.NumberFormat('id-ID',{minimumFractionDigits:2,maximumFractionDigits:2}).format(n)}catch(e){const f=(Math.round(n*100)/100).toFixed(2); const [a,b]=f.split('.'); return 'Rp '+a.replace(/\B(?=(\d{3})+(?!\d))/g,'.')+','+b} }
  function clampQty(x){ const n=toNum(x); if(n<0) return 0; if(n>100) return 100; return n; }
  function clampPct(x){ return Math.min(Math.max(toNum(x),0),100); }
  function addDaysISO(s, d){ if(!s) return ''; const t=new Date(s+'T00:00:00'); if(isNaN(+t)) return ''; t.setDate(t.getDate()+(parseInt(d,10)||0)); return `${t.getFullYear()}-${String(t.getMonth()+1).padStart(2,'0')}-${String(t.getDate()).padStart(2,'0')}`; }

  // ---------- DOM ----------
  const form = document.getElementById('qoForm');
  const body = document.getElementById('linesBody');
  const tpl  = document.getElementById('rowTpl').innerHTML;

  // Staging elements
  const STAGE = {
    name: document.getElementById('stage_name'),
    id  : document.getElementById('stage_item_id'),
    variantId: document.getElementById('stage_item_variant_id'),
    desc: document.getElementById('stage_desc'),
    qty : document.getElementById('stage_qty'),
    unit: document.getElementById('stage_unit'),
    price:document.getElementById('stage_price'),
    addBtn:document.getElementById('stage_add_btn'),
    clearBtn:document.getElementById('stage_clear_btn'),
    search:document.getElementById('itemQuickSearch'),
    clear(){ this.id.value=''; this.variantId.value=''; this.name.value=''; this.desc.value=''; this.qty.value='1'; this.unit.value='pcs'; this.price.value=''; }
  };

  const companySel   = document.getElementById('company_id');
  const taxInfo      = document.getElementById('companyTaxInfo');
  const validInfo    = document.getElementById('companyValidInfo');
  const taxInput     = document.getElementById('tax_percent');
  const dateInput    = document.getElementById('date');
  const validInput   = document.getElementById('valid_until');

  const totalDiscTypeSel = document.getElementById('total_discount_type');
  const totalDiscValInp  = document.getElementById('total_discount_value');
  const totalDiscUnit    = document.getElementById('totalDiscUnit');

  const vLinesSubtotal   = document.getElementById('v_lines_subtotal');
  const vTotalDiscAmt    = document.getElementById('v_total_discount_amount');
  const vTotalDiscHint   = document.getElementById('v_total_disc_hint');
  const vTaxableBase     = document.getElementById('v_taxable_base');
  const vTaxPct          = document.getElementById('v_tax_percent');
  const vTaxAmt          = document.getElementById('v_tax_amount');
  const vTotal           = document.getElementById('v_total');

  const discountRadios   = document.querySelectorAll('input[name="discount_mode"]');
  const secTotalControls = document.querySelector('[data-section="discount-total-controls"]');
  const thDiscInput      = document.querySelector('th[data-col="disc-input"]');

  // ---------- Rows ----------
  let idx = 0;
  function addRow(values = {}) {
    let html = tpl.replaceAll('__IDX__', idx);
    const wrap = document.createElement('tbody');
    wrap.innerHTML = html.trim();
    const row = wrap.firstElementChild;

    const itemId     = row.querySelector('.q-item-id');
    const variantId  = row.querySelector('.q-item-variant-id');
    const nameInput  = row.querySelector('.q-item-name');
    const descInput  = row.querySelector('.q-item-desc');
    const qtyInput   = row.querySelector('.q-item-qty');
    const unitInput  = row.querySelector('.q-item-unit');
    const priceInput = row.querySelector('.q-item-rate');

    const discTypeSel= row.querySelector('.disc-type');
    const discValInp = row.querySelector('.disc-value');
    const discUnitSp = row.querySelector('.disc-unit');

    const removeBtn  = row.querySelector('.removeRowBtn');

    unitInput.readOnly = true;

    function syncDiscUnit(){ if(!discTypeSel) return; (discUnitSp||{}).textContent = (discTypeSel.value==='percent')?'%':'IDR'; }
    discTypeSel?.addEventListener('change', () => { syncDiscUnit(); recalc(); });
    syncDiscUnit();

    function unformatPrice(el){ el.value = String(toNum(el.value)); }
    function formatPrice(el){ el.value = new Intl.NumberFormat('id-ID',{minimumFractionDigits:2,maximumFractionDigits:2}).format(toNum(el.value)); }
    priceInput.addEventListener('focus', () => unformatPrice(priceInput));
    priceInput.addEventListener('blur',  () => formatPrice(priceInput));

    [qtyInput, priceInput, discValInp].forEach(el => {
      el?.addEventListener('input', recalc);
      el?.addEventListener('blur', recalc);
    });

    qtyInput.addEventListener('blur', () => { qtyInput.value = String(clampQty(qtyInput.value)); recalc(); });
    removeBtn.addEventListener('click', () => { row.parentNode.removeChild(row); recalc(); });

    // preset
    if (values.item_id) itemId.value = values.item_id;
    if (values.item_variant_id) variantId.value = values.item_variant_id;
    if (values.name)    nameInput.value = values.name;
    if (values.description) descInput.value = values.description;
    if (values.qty != null) qtyInput.value = values.qty;
    if (values.unit)    unitInput.value = values.unit;
    if (values.unit_price != null) priceInput.value = String(values.unit_price);
    if (values.discount_type)  discTypeSel.value = values.discount_type;
    if (values.discount_value != null) discValInp.value = String(values.discount_value);

    body.appendChild(row);
    idx++;

    applyModeToRow(row);
    recalc();
  }

  // Seed existing lines
  const existingLines = @json($lineSeed);
  if (Array.isArray(existingLines) && existingLines.length > 0) {
    existingLines.forEach(l => addRow(l));
  }
  // jika kosong, biarkan tabel kosong; user menambah via staging row

  // ======= Stage handlers =======
  STAGE.addBtn.addEventListener('click', () => {
    if (!STAGE.name.value.trim()) { STAGE.search?.focus(); return; }
    addRow({
      item_id: STAGE.id.value || '',
      item_variant_id: STAGE.variantId.value || '',
      name: STAGE.name.value,
      description: STAGE.desc.value,
      qty: STAGE.qty.value || 1,
      unit: STAGE.unit.value || 'pcs',
      unit_price: STAGE.price.value || 0,
    });
    STAGE.clear();
    STAGE.search?.focus();
  });
  STAGE.clearBtn.addEventListener('click', () => STAGE.clear());
  document.getElementById('stageWrap').addEventListener('keydown', (e)=>{
    if (e.key === 'Enter' && e.target.tagName !== 'TEXTAREA') { e.preventDefault(); STAGE.addBtn.click(); }
  });

  // ---------- Company & Tax & Valid Days sync ----------
  function syncCompanyInfo() {
    const opt = companySel?.selectedOptions ? companySel.selectedOptions[0] : null;
    const taxable = Number(opt?.dataset.taxable || 0) === 1;
    const defTax  = parseFloat(opt?.dataset.tax || '0') || 0;
    const vdays   = parseInt(opt?.dataset.validDays || '30', 10) || 30;

    if (!taxable) {
      taxInput.value = '0';
      taxInput.readOnly = true;
      taxInput.classList.add('bg-light');
      taxInfo && (taxInfo.innerHTML = '<span class="badge bg-gray">Non-Taxable</span>');
    } else {
      if (!taxInput.value || Number(toNum(taxInput.value)) === 0) taxInput.value = defTax.toFixed(2);
      taxInput.readOnly = false;
      taxInput.classList.remove('bg-light');
      taxInfo && (taxInfo.innerHTML = '<span class="badge bg-blue">Taxable</span> <span class="text-muted">Default '+ defTax.toFixed(2) + '%</span>');
    }

    validInfo && (validInfo.textContent = 'Default masa berlaku: ' + vdays + ' hari');

    if (validInput && !validInput.value) {
      const next = addDaysISO(dateInput.value || '', vdays);
      if (next) validInput.value = next;
    }

    recalc();
  }
  companySel && companySel.addEventListener('change', syncCompanyInfo);
  syncCompanyInfo();

  dateInput && dateInput.addEventListener('change', () => {
    if (validInput.value) return;
    const opt = companySel?.selectedOptions ? companySel.selectedOptions[0] : null;
    const vdays = parseInt(opt?.dataset.validDays || '30', 10) || 30;
    const next = addDaysISO(dateInput.value, vdays);
    if (next) validInput.value = next;
  });

  function syncTotalDiscUnit(){ if(!totalDiscTypeSel||!totalDiscUnit) return; totalDiscUnit.textContent = (totalDiscTypeSel.value==='percent') ? '%' : 'IDR'; recalc(); }
  totalDiscTypeSel?.addEventListener('change', syncTotalDiscUnit);
  totalDiscValInp?.addEventListener('input', recalc);
  syncTotalDiscUnit();
  taxInput.addEventListener('input', recalc);

  // ---------- Recalc ----------
  function recalc() {
    let linesSubtotal = 0;

    body.querySelectorAll('tr[data-line-row]').forEach(tr => {
      const qty   = clampQty(tr.querySelector('.qty')?.value || '0');
      const price = toNum(tr.querySelector('.price')?.value || '0');
      const dtSel = tr.querySelector('.disc-type');
      const dvInp = tr.querySelector('.disc-value');
      const dt    = dtSel ? dtSel.value : 'amount';
      const dvRaw = toNum(dvInp?.value || '0');

      const lineSubtotal = qty * price;
      let discAmount = 0;
      if (dt === 'percent') discAmount = clampPct(dvRaw) / 100 * lineSubtotal;
      else                  discAmount = Math.min(Math.max(dvRaw, 0), lineSubtotal);

      const lineTotal = Math.max(lineSubtotal - discAmount, 0);

      tr.querySelector('.line_subtotal_view').textContent   = rupiah(lineSubtotal);
      tr.querySelector('.line_disc_amount_view').textContent= rupiah(discAmount);
      tr.querySelector('.line_total_view').textContent      = rupiah(lineTotal);

      linesSubtotal += lineTotal;
    });

    vLinesSubtotal.textContent = rupiah(linesSubtotal);

    const mode = getDiscountMode();
    let tdt  = totalDiscTypeSel?.value || 'amount';
    let tdv  = toNum(totalDiscValInp?.value || '0');

    if (mode === 'per_item') { tdt='amount'; tdv=0; totalDiscTypeSel && (totalDiscTypeSel.value='amount'); totalDiscValInp && (totalDiscValInp.value='0'); }

    const totalDiscAmount = (tdt === 'percent')
      ? clampPct(tdv) / 100 * linesSubtotal
      : Math.min(Math.max(tdv, 0), linesSubtotal);

    vTotalDiscAmt.textContent = rupiah(totalDiscAmount);
    vTotalDiscHint.textContent = (tdt === 'percent' && mode !== 'per_item')
      ? '(' + (Math.round(clampPct(tdv)*100)/100).toFixed(2) + '%)'
      : '';

    const base   = Math.max(linesSubtotal - totalDiscAmount, 0);
    const taxPct = toNum(taxInput.value || '0');
    const taxAmt = base * Math.max(taxPct, 0) / 100;
    const total  = base + taxAmt;

    vTaxableBase.textContent = rupiah(base);
    vTaxPct.textContent      = (Math.round(taxPct * 100) / 100).toFixed(2);
    vTaxAmt.textContent      = rupiah(taxAmt);
    vTotal.textContent       = rupiah(total);
  }

  // ---------- Discount Mode Toggle ----------
  function getDiscountMode(){ return (document.querySelector('input[name="discount_mode"]:checked')?.value) || 'total'; }
  function resetTotalDiscountFields(){ totalDiscTypeSel && (totalDiscTypeSel.value='amount'); totalDiscValInp && (totalDiscValInp.value='0'); totalDiscUnit && (totalDiscUnit.textContent='IDR'); }
  function resetPerItemDiscountFields(root){
    (root ? root.querySelectorAll('.disc-type') : document.querySelectorAll('.disc-type')).forEach(el => { el.value='amount'; });
    (root ? root.querySelectorAll('.disc-value') : document.querySelectorAll('.disc-value')).forEach(el => { el.value='0'; });
    (root ? root.querySelectorAll('.disc-unit')  : document.querySelectorAll('.disc-unit')).forEach(el => { el.textContent='IDR'; });
  }
  function applyModeToRow(row){
    const mode = getDiscountMode();
    const discCell = row.querySelector('.disc-cell');
    if (!discCell) return;
    if (mode === 'total'){ discCell.classList.add('d-none'); resetPerItemDiscountFields(row); }
    else                 { discCell.classList.remove('d-none'); }
  }
  function applyDiscountMode(mode){
    if (secTotalControls){
      if (mode === 'per_item'){ secTotalControls.classList.add('d-none'); resetTotalDiscountFields(); }
      else { secTotalControls.classList.remove('d-none'); }
    }
    if (thDiscInput){
      if (mode === 'total') thDiscInput.classList.add('d-none');
      else                  thDiscInput.classList.remove('d-none');
    }
    body.querySelectorAll('tr[data-line-row]').forEach(applyModeToRow);
    recalc();
  }
  discountRadios.forEach(r => r.addEventListener('change', (e) => applyDiscountMode(e.target.value)));
  applyDiscountMode(getDiscountMode());

  // ---------- Unformat sebelum submit ----------
  form.addEventListener('submit', () => {
    body.querySelectorAll('tr[data-line-row]').forEach((tr) => {
      const qty   = tr.querySelector('.qty');
      const price = tr.querySelector('.price');
      const dval  = tr.querySelector('.disc-value');
      if (qty)   qty.value   = String(clampQty(qty.value));
      if (price) price.value = String(toNum(price.value));
      if (dval)  dval.value  = String(toNum(dval.value));
    });
    totalDiscValInp && (totalDiscValInp.value = String(toNum(totalDiscValInp.value)));
    taxInput.value = String(toNum(taxInput.value));
  });
})();
</script>
@endpush
@endsection
