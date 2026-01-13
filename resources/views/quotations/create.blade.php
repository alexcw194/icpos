{{-- resources/views/quotations/create.blade.php --}}
@extends('layouts.tabler')

@section('title','Create Quotation')

@section('content')
<div class="container-xl">
  <form action="{{ route('quotations.store') }}" method="POST" class="card" id="qoForm">
    @csrf

    <div class="card-header">
      <div class="card-title">Create Quotation</div>
    </div>

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
          <select id="company_id" name="company_id" class="form-select" required>
            @foreach($companies as $co)
              <option value="{{ $co->id }}"
                data-taxable="{{ $co->is_taxable ? 1 : 0 }}"
                data-tax="{{ (float)($co->default_tax_percent ?? 0) }}"
                data-valid-days="{{ (int)($co->default_valid_days ?? 30) }}"
                {{ (old('company_id', $defaultCompanyId ?? null) == $co->id) ? 'selected' : '' }}>
                {{ $co->alias ? $co->alias.' — ' : '' }}{{ $co->name }}
              </option>
            @endforeach
          </select>
          <div class="small mt-1 d-flex flex-column gap-1">
            <span id="companyTaxInfo"></span>
            <span id="companyValidInfo" class="text-muted"></span>
          </div>
        </div>

        {{-- CUSTOMER (TomSelect) --}}
        <div class="col-md-4">
          <label class="form-label">Customer <span class="text-danger">*</span></label>
          <input id="customerPicker" type="text" class="form-control" placeholder="Ketik nama perusahaan/kontak…">
          <input type="hidden" name="customer_id" id="customer_id" value="{{ old('customer_id', request('customer_id')) }}">
          <input type="hidden" name="contact_id"  id="contact_id"  value="{{ old('contact_id') }}">
          <small class="form-hint">Contoh: <em>Ersindo</em> atau <em>Ruru</em>.</small>
        </div>

        {{-- SALES NAME --}}
        <div class="col-md-4">
          <label class="form-label">Sales Name</label>
          <select name="sales_user_id" id="sales_user_id" class="form-select">
            @php $defaultSalesId = old('sales_user_id', $defaultSalesUserId ?? null); @endphp
            @foreach($sales as $s)
              <option value="{{ $s->id }}" {{ (string)$defaultSalesId === (string)$s->id ? 'selected' : '' }}>{{ $s->name }}</option>
            @endforeach
          </select>
          <small class="form-hint">Default mengikuti user yang sedang login.</small>
        </div>

        {{-- DATES --}}
        <div class="col-md-2">
          <label class="form-label">Tanggal <span class="text-danger">*</span></label>
          <input type="date" name="date" id="date" class="form-control" value="{{ old('date', now()->toDateString()) }}" required>
        </div>
        <div class="col-md-2">
          <label class="form-label">Valid Until</label>
          <input type="date" name="valid_until" id="valid_until" class="form-control" value="{{ old('valid_until') }}">
          <small class="form-hint">Auto: Tanggal + default hari dari Company.</small>
        </div>

        {{-- Currency --}}
        <input type="hidden" name="currency" value="IDR">

        {{-- TAX --}}
        <div class="col-md-2">
          <label class="form-label">PPN (%)</label>
          <input type="text" inputmode="decimal" class="form-control text-end" id="tax_percent" name="tax_percent" placeholder="0" value="{{ old('tax_percent') }}">
          <small class="form-hint">Otomatis mengikuti Company</small>
        </div>

        {{-- NOTES / TERMS --}}
        <div class="col-md-5">
          <label class="form-label">Notes</label>
          <textarea name="notes" class="form-control" rows="2">{{ old('notes') }}</textarea>
        </div>
        <div class="col-md-5">
          <label class="form-label">Terms</label>
          <textarea name="terms" class="form-control" rows="2">{{ old('terms') }}</textarea>
        </div>
      </div>

      {{-- DISCOUNT MODE --}}
      <div class="row g-3 align-items-center mt-3">
        <div class="col-md-6">
          <label class="form-label mb-1">Discount Mode</label>
          <div class="form-selectgroup">
            <label class="form-selectgroup-item">
              <input type="radio" name="discount_mode" value="total" class="form-selectgroup-input" @checked(old('discount_mode','total')==='total')>
              <span class="form-selectgroup-label">Total (global)</span>
            </label>
            <label class="form-selectgroup-item">
              <input type="radio" name="discount_mode" value="per_item" class="form-selectgroup-input" @checked(old('discount_mode')==='per_item')>
              <span class="form-selectgroup-label">Per Item (per-baris)</span>
            </label>
          </div>
          <small class="form-hint">Mode <b>Total</b>: satu diskon untuk seluruh quotation. Mode <b>Per Item</b>: tiap baris punya diskon sendiri.</small>
        </div>
      </div>

      <hr class="my-3">

      {{-- STAGING ROW (Mobile: 3 baris biar kebaca, tanpa scroll kanan) --}}
      <div id="stageWrap" class="card mb-3">
        <div class="card-body py-2">
          <div class="row g-2 align-items-center stage-row">
            {{-- Row 1: Nama Item --}}
            <div class="col-12 stage-r1">
              <input id="stage_name" type="text" class="form-control fw-semibold" placeholder="Ketik nama/SKU lalu pilih…">
              <input id="stage_item_id" type="hidden">
              <input id="stage_item_variant_id" type="hidden">
            </div>

            {{-- Row 2: Description --}}
            <div class="col-12 stage-r2">
              <textarea id="stage_desc" class="form-control" rows="1" placeholder="Deskripsi (opsional)"></textarea>
            </div>

            {{-- Row 3: Qty + Unit + Price + Actions --}}
            <div class="col-12 stage-r3">
              <div class="row g-2 align-items-center">
                <div class="col-4">
                  <input id="stage_qty" type="text" class="form-control text-end" inputmode="decimal" value="1">
                </div>
                <div class="col-3">
                  <input id="stage_unit" type="text" class="form-control" value="pcs" readonly>
                </div>
                <div class="col-5">
                  <input id="stage_price" type="text" class="form-control text-end" inputmode="decimal" placeholder="0">
                </div>

                <div class="col-12 d-flex gap-2 mt-1">
                  <button type="button" id="stage_add_btn" class="btn btn-primary flex-grow-1">Tambah</button>
                  <button type="button" id="stage_clear_btn" class="btn btn-outline-secondary">Kosongkan</button>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>

      {{-- ITEMS TABLE --}}
      <div class="fw-bold mb-2">Items</div>
      <div class="table-responsive">
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
                  @php $tdt = old('total_discount_type','amount'); @endphp
                  <select name="total_discount_type" id="total_discount_type" class="form-select" style="min-width:160px">
                    <option value="amount" {{ $tdt=='amount'?'selected':'' }}>Nominal (IDR)</option>
                    <option value="percent" {{ $tdt=='percent'?'selected':'' }}>Persen (%)</option>
                  </select>
                </div>
                <div class="col">
                  <div class="input-group">
                    <input type="text" name="total_discount_value" id="total_discount_value" class="form-control text-end" inputmode="decimal" value="{{ old('total_discount_value', '0') }}">
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
      'cancelUrl'    => route('quotations.index'),
      'cancelLabel'  => 'Batal',
      'cancelInline' => true,
      'buttons' => [['type' => 'submit', 'label' => 'Simpan', 'class' => 'btn btn-primary']],
    ])
  </form>
</div>

{{-- ===== Inline template row ===== --}}
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
    <td class="col-actions text-center"><button type="button" class="btn btn-link text-danger p-0 removeRowBtn" title="Hapus">&times;</button></td>
  </tr>
</template>

{{-- Modal Quick Customer --}}
@include('customers._quick_modal')
@endsection

@push('styles')
{{-- TomSelect CSS (fallback) --}}
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/tom-select@2.3.1/dist/css/tom-select.bootstrap5.min.css">
<style>
  #linesTable th, #linesTable td { vertical-align: middle; }
  #linesTable .col-item{ width:22% } .col-desc{ width:20% } .col-qty{ width:6.5ch } .col-unit{ width:7ch }
  #linesTable .col-price{ width:14% } .col-disc{ width:16% } .col-subtotal{ width:9% } .col-disc-amount{ width:9% } .col-total{ width:14% } .col-actions{ width:4% }
  #linesTable input.qty{ max-width:6.5ch } #linesTable input.unit{ max-width:7ch }
  #linesTable .disc-cell .form-select{ min-width:120px } .disc-cell .disc-value{ max-width:8ch }
  #linesTable .disc-cell .input-group-text.disc-unit{ min-width:46px; justify-content:center }
  #linesTable .line_total_view{ font-weight:700; font-size:1.06rem } .line_subtotal_view{ font-size:.92rem }

  /* Dropdown TomSelect selalu opaque & di atas */
  .ts-dropdown{ z-index:1060 !important; background:#fff !important; box-shadow:0 10px 24px rgba(0,0,0,.12) !important; }

  /* ===== Mobile staging optimization ===== */
  @media (max-width: 576px){
    #stageWrap .card-body{ padding: .75rem; }
    #stageWrap #stage_name{ font-size: 1rem; }
    #stageWrap .stage-r3 .btn{ white-space: nowrap; }
  }
</style>
@endpush

@php
  $CUSTOMER_SEARCH_URL = route('customers.search', [], false);
  // opsi item untuk TomSelect sampai halaman ini (bisa dari controller)
  $ITEM_OPTIONS = ($items ?? collect())->map(function($it){
    return [
      'id'    => (string) $it->id,
      'label' => $it->name,
      'unit'  => optional($it->unit)->code ?? 'pcs',
      'price' => (float)($it->price ?? 0),
    ];
  })->values();
@endphp

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/tom-select@2.3.1/dist/js/tom-select.complete.min.js"></script>
<script>
(() => {
  'use strict';

  /* ========= Helpers ========= */
  const toNum  = v => { if(v==null) return 0; v=(''+v).trim(); if(!v) return 0; v=v.replace(/\s/g,''); const c=v.includes(','), d=v.includes('.'); if(c&&d){v=v.replace(/\./g,'').replace(',', '.')} else {v=v.replace(',', '.')} const n=parseFloat(v); return isNaN(n)?0:n; };
  const rupiah = n => { try{ return 'Rp ' + new Intl.NumberFormat('id-ID',{minimumFractionDigits:2,maximumFractionDigits:2}).format(n) } catch(e){ const f=(Math.round(n*100)/100).toFixed(2); const [a,b]=f.split('.'); return 'Rp '+a.replace(/\B(?=(\d{3})+(?!\d))/g,'.')+','+b } };
  const clampQty = x => Math.min(Math.max(toNum(x),0),100);
  const clampPct = x => Math.min(Math.max(toNum(x),0),100);
  const addDaysISO = (s,d)=>{ if(!s) return ''; const t=new Date(s+'T00:00:00'); if(isNaN(+t)) return ''; t.setDate(t.getDate()+(parseInt(d,10)||0)); return `${t.getFullYear()}-${String(t.getMonth()+1).padStart(2,'0')}-${String(t.getDate()).padStart(2,'0')}`; };

  /* ========= DOM refs (DEFINISIKAN LEBIH DULU, supaya recalc aman) ========= */
  const form = document.getElementById('qoForm');
  const body = document.getElementById('linesBody');
  const tpl  = document.getElementById('rowTpl').innerHTML;

  const stageName  = document.getElementById('stage_name');
  const stageId    = document.getElementById('stage_item_id');
  const stageVarId = document.getElementById('stage_item_variant_id');
  const stageDesc  = document.getElementById('stage_desc');
  const stageQty   = document.getElementById('stage_qty');
  const stageUnit  = document.getElementById('stage_unit');
  const stagePrice = document.getElementById('stage_price');

  const btnAdd   = document.getElementById('stage_add_btn');
  const btnClear = document.getElementById('stage_clear_btn');

  const companySel = document.getElementById('company_id');
  const taxInfo    = document.getElementById('companyTaxInfo');
  const validInfo  = document.getElementById('companyValidInfo');
  const taxInput   = document.getElementById('tax_percent');
  const dateInput  = document.getElementById('date');
  const validInput = document.getElementById('valid_until');

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

  /* ========= Customer TomSelect ========= */
  const SEARCH_URL = {!! json_encode($CUSTOMER_SEARCH_URL, JSON_UNESCAPED_SLASHES) !!};
  (function initCustomerTS(){
    const input = document.getElementById('customerPicker');
    if (!input || !window.TomSelect) return;

    const hidCustomer = document.getElementById('customer_id');
    const hidContact  = document.getElementById('contact_id');

    const picker = new TomSelect(input, {
      valueField : 'uid',
      labelField : 'label',
      searchField: ['name','label'],
      maxOptions : 30,
      preload    : 'focus',
      create     : false,
      persist    : false,
      dropdownParent: 'body',
      load(query, cb){
        const url = `${SEARCH_URL}?q=${encodeURIComponent(query||'')}`;
        fetch(url,{credentials:'same-origin',headers:{'X-Requested-With':'XMLHttpRequest'}})
          .then(r => r.text())
          .then(t => { const s=t.replace(/^\uFEFF/, '').trim(); try{ cb(JSON.parse(s)) }catch{ cb([]) } })
          .catch(()=>cb());
      },
      render: { option(d,esc){ return `<div>${esc(d.label||'')}</div>`; } },
      onChange(val){
        const data = this.options[val];
        if (!data) return;
        hidCustomer.value = data.customer_id || '';
        hidContact.value  = data.contact_id  || '';
        this.setTextboxValue(data.label || '');
        this.close();
      }
    });
  })();

  /* ========= Item TomSelect (SATU kotak di staging) ========= */
  (function initItemTS(){
    const opts = @json($ITEM_OPTIONS ?? []);
    if (!stageName || !window.TomSelect) return;

    const ts = new TomSelect(stageName, {
      options: (opts||[]).map(o => ({ value:o.id, label:o.label, unit:o.unit||'pcs', price:Number(o.price||0) })),
      valueField:'value', labelField:'label', searchField:['label'],
      maxOptions:100, create:false, persist:false, dropdownParent:'body', openOnFocus:true, preload:true,
      render:{ option(d,esc){ return `<div class="d-flex justify-content-between"><span>${esc(d.label||'')}</span><span class="text-muted small">${esc(d.unit||'')}</span></div>`; } },
      onChange(val){
        const o = this.options[val];
        stageId.value    = o ? o.value : '';
        stageVarId.value = '';
        stageUnit.value  = o ? (o.unit||'pcs') : 'pcs';
        stagePrice.value = o ? String(o.price||0) : '';

        // UX mobile: anggap "next field" setelah pilih item
        this.close();
        requestAnimationFrame(() => {
          if (stageDesc) stageDesc.focus();
        });
      }
    });

    // supaya gampang dipakai ulang saat reset
    stageName.__ts = ts;
  })();

  /* ========= Rows ========= */
  let idx = 0;

  function addRow(values = {}) {
    const wrap = document.createElement('tbody');
    wrap.innerHTML = tpl.replaceAll('__IDX__', idx).trim();
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

    const syncDiscUnit = () => discUnitSp.textContent = (discTypeSel.value==='percent') ? '%' : 'IDR';
    discTypeSel.addEventListener('change', () => { syncDiscUnit(); recalc(); });
    syncDiscUnit();

    [qtyInput, priceInput, discValInp].forEach(el => { el.addEventListener('input', recalc); el.addEventListener('blur', recalc); });
    qtyInput.addEventListener('blur', () => { qtyInput.value = String(clampQty(qtyInput.value)); recalc(); });
    removeBtn.addEventListener('click', () => { row.remove(); recalc(); });

    // preset dari staging
    itemId.value    = values.item_id || '';
    variantId.value = values.item_variant_id || '';
    nameInput.value = values.name || '';
    descInput.value = values.description || '';
    qtyInput.value  = values.qty || 1;
    unitInput.value = values.unit || 'pcs';
    priceInput.value= values.unit_price || 0;

    body.appendChild(row);
    idx++;
    applyModeToRow(row);
    recalc();
  }

  /* ========= Stage handlers ========= */
  const clearStage = () => {
    stageId.value=''; stageVarId.value=''; stageDesc.value=''; stageQty.value='1'; stageUnit.value='pcs'; stagePrice.value='';
    if (stageName.__ts){ stageName.__ts.clear(); stageName.__ts.setTextboxValue(''); }
  };

  btnAdd.addEventListener('click', () => {
    const label = (stageName.__ts ? stageName.__ts.getItem(stageName.__ts.items[0])?.innerText : stageName.value) || '';
    const id    = (stageId.value||'').trim();
    if (!id || !label){ (stageName.focus && stageName.focus()); return; }

    addRow({
      item_id: id,
      item_variant_id: stageVarId.value || '',
      name: label, // <= tampilkan nama, bukan ID
      description: stageDesc.value || '',
      qty: stageQty.value || 1,
      unit: stageUnit.value || 'pcs',
      unit_price: stagePrice.value || 0,
    });

    clearStage(); // auto reset setelah Tambah
  });

  btnClear.addEventListener('click', clearStage);
  document.getElementById('stageWrap').addEventListener('keydown', (e)=>{
    if (e.key === 'Enter' && e.target.tagName !== 'TEXTAREA') { e.preventDefault(); btnAdd.click(); }
  });

  /* ========= Company & Tax ========= */
  function syncCompanyInfo() {
    const opt = companySel.selectedOptions[0];
    const taxable = Number(opt?.dataset.taxable || 0) === 1;
    const defTax  = parseFloat(opt?.dataset.tax || '0') || 0;
    const vdays   = parseInt(opt?.dataset.validDays || '30', 10) || 30;

    if (!taxable) {
      taxInput.value = '0';
      taxInput.readOnly = true;
      taxInput.classList.add('bg-light');
      taxInfo.innerHTML = '<span class="badge bg-gray">Non-Taxable</span>';
    } else {
      if (!taxInput.value || Number(String(taxInput.value).replace(',','.')) === 0) taxInput.value = defTax.toFixed(2);
      taxInput.readOnly = false;
      taxInput.classList.remove('bg-light');
      taxInfo.innerHTML = '<span class="badge bg-blue">Taxable</span> <span class="text-muted">Default '+ defTax.toFixed(2) + '%</span>';
    }

    validInfo.textContent = 'Default masa berlaku: ' + vdays + ' hari';
    if (!validInput.value) {
      const next = addDaysISO(dateInput.value || '', vdays);
      if (next) validInput.value = next;
    }

    recalc();
  }
  companySel.addEventListener('change', syncCompanyInfo);
  dateInput.addEventListener('change', () => {
    if (validInput.value) return;
    const opt = companySel.selectedOptions[0];
    const vdays = parseInt(opt?.dataset.validDays || '30', 10) || 30;
    const next = addDaysISO(dateInput.value, vdays);
    if (next) validInput.value = next;
  });

  /* ========= Discount Mode ========= */
  function resetTotalDiscountFields(){
    totalDiscTypeSel.value='amount'; totalDiscValInp.value='0'; totalDiscUnit.textContent='IDR';
  }
  function resetPerItemDiscountFields(root){
    (root?root:document).querySelectorAll('.disc-type').forEach(el=>el.value='amount');
    (root?root:document).querySelectorAll('.disc-value').forEach(el=>el.value='0');
    (root?root:document).querySelectorAll('.disc-unit').forEach(el=>el.textContent='IDR');
  }
  function applyModeToRow(row){
    const mode = (document.querySelector('input[name="discount_mode"]:checked')?.value) || 'total';
    const discCell = row.querySelector('.disc-cell');
    if (mode === 'total'){ discCell.classList.add('d-none'); resetPerItemDiscountFields(row); }
    else                 { discCell.classList.remove('d-none'); }
  }
  function applyDiscountMode(mode){
    const sec = document.querySelector('[data-section="discount-total-controls"]');
    const thDiscInput = document.querySelector('th[data-col="disc-input"]');
    if (sec){ if (mode === 'per_item'){ sec.classList.add('d-none'); resetTotalDiscountFields(); } else { sec.classList.remove('d-none'); } }
    if (thDiscInput){ thDiscInput.classList.toggle('d-none', mode === 'total'); }
    body.querySelectorAll('tr[data-line-row]').forEach(applyModeToRow);
    recalc();
  }
  document.querySelectorAll('input[name="discount_mode"]').forEach(r => r.addEventListener('change', (e)=>applyDiscountMode(e.target.value)));

  /* ========= Recalc ========= */
  function recalc(){
    let linesSubtotal = 0;

    body.querySelectorAll('tr[data-line-row]').forEach(tr => {
      const qty   = clampQty(tr.querySelector('.qty')?.value || '0');
      const price = toNum((tr.querySelector('.price')?.value || '0'));
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

    const mode = (document.querySelector('input[name="discount_mode"]:checked')?.value) || 'total';
    let tdt  = totalDiscTypeSel.value;
    let tdv  = toNum(totalDiscValInp.value);

    if (mode === 'per_item'){ tdt='amount'; tdv=0; totalDiscTypeSel.value='amount'; totalDiscValInp.value='0'; }

    const totalDiscAmount = (tdt==='percent') ? clampPct(tdv)/100 * linesSubtotal : Math.min(Math.max(tdv,0), linesSubtotal);

    vTotalDiscAmt.textContent  = rupiah(totalDiscAmount);
    vTotalDiscHint.textContent = (tdt==='percent' && mode!=='per_item') ? '('+(Math.round(clampPct(tdv)*100)/100).toFixed(2)+'%)' : '';

    const base   = Math.max(linesSubtotal - totalDiscAmount, 0);
    const taxPct = clampPct(taxInput.value);
    const taxAmt = base * (taxPct/100);
    const total  = base + taxAmt;

    vTaxableBase.textContent = rupiah(base);
    vTaxPct.textContent      = (Math.round(taxPct*100)/100).toFixed(2);
    vTaxAmt.textContent      = rupiah(taxAmt);
    vTotal.textContent       = rupiah(total);
  }

  /* ========= Prefill old('lines') jika ada ========= */
  const oldLines = @json(old('lines', []));
  if (Array.isArray(oldLines) && oldLines.length) oldLines.forEach(l => addRow(l));

  /* ========= Init ========= */
  syncCompanyInfo();
  applyDiscountMode((document.querySelector('input[name="discount_mode"]:checked')?.value) || 'total');

  /* ========= Unformat sebelum submit ========= */
  form.addEventListener('submit', () => {
    body.querySelectorAll('tr[data-line-row]').forEach((tr) => {
      const qty   = tr.querySelector('.qty');
      const price = tr.querySelector('.price');
      const dval  = tr.querySelector('.disc-value');
      if (qty)   qty.value   = String(clampQty(qty.value));
      if (price) price.value = String(toNum(price.value));
      if (dval)  dval.value  = String(toNum(dval.value));
    });
    totalDiscValInp.value = String(toNum(totalDiscValInp.value));
    taxInput.value        = String(clampPct(taxInput.value));
  });
})();
</script>
@endpush
