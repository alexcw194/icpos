@extends('layouts.tabler')

@section('content')
<div class="container-xl">
  <form action="{{ route('sales-orders.store') }}" method="POST" class="card" id="soForm">
    @csrf
    <div class="card-header">
      <div class="card-title">Create Sales Order</div>
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
          <select id="company_id" name="company_id" class="form-select" required>
            @foreach($companies as $co)
              <option value="{{ $co->id }}"
                      data-taxable="{{ $co->is_taxable ? 1 : 0 }}"
                      data-tax="{{ (float)($co->default_tax_percent ?? 0) }}"
                      {{ (old('company_id', $selectedCompanyId ?? $defaultCompanyId ?? null) == $co->id) ? 'selected' : '' }}>
                {{ $co->alias ? $co->alias.' — ' : '' }}{{ $co->name }}
              </option>
            @endforeach
          </select>
        </div>

        {{-- CUSTOMER --}}
        <div class="col-md-4">
          <label class="form-label">Customer <span class="text-danger">*</span></label>
          <input id="customerPicker" type="text" class="form-control" placeholder="Ketik nama perusahaan/kontak…">
          <input type="hidden" name="customer_id" id="customer_id" value="{{ old('customer_id') }}">
          <input type="hidden" name="contact_id" id="contact_id" value="{{ old('contact_id') }}">
          <select id="customer_id_select" class="form-select d-none"></select>
          <small class="form-hint">
            Bisa cari perusahaan atau kontak, contoh: <em>Ersindo</em> atau <em>Ruru</em>.
          </small>
        </div>

        {{-- SALES NAME --}}
        <div class="col-md-4">
          <label class="form-label">Sales Name</label>
          <select name="sales_user_id" class="form-select">
            @php $defaultSalesId = old('sales_user_id', $defaultSalesUserId ?? null); @endphp
            @foreach($sales as $s)
              <option value="{{ $s->id }}" {{ (string)$defaultSalesId === (string)$s->id ? 'selected' : '' }}>{{ $s->name }}</option>
            @endforeach
          </select>
        </div>

        {{-- PO NUMBER --}}
        <div class="col-md-4">
          <label class="form-label">Customer PO Number <span class="text-danger">*</span></label>
          <input type="text" name="customer_po_number" class="form-control" value="{{ old('customer_po_number') }}" required>
        </div>

        {{-- PO DATE --}}
        <div class="col-md-4">
          <label class="form-label">Customer PO Date <span class="text-danger">*</span></label>
          <input type="date" name="customer_po_date" class="form-control" value="{{ old('customer_po_date', now()->toDateString()) }}" required>
        </div>

        {{-- DEADLINE --}}
        <div class="col-md-4">
          <label class="form-label">Deadline</label>
          <input type="date" name="deadline" class="form-control" value="{{ old('deadline') }}">
        </div>

        {{-- SHIP TO --}}
        <div class="col-md-6">
          <label class="form-label">Ship To</label>
          <textarea name="ship_to" class="form-control" rows="2">{{ old('ship_to') }}</textarea>
        </div>

        {{-- BILL TO --}}
        <div class="col-md-6">
          <label class="form-label">Bill To</label>
          <textarea name="bill_to" class="form-control" rows="2">{{ old('bill_to') }}</textarea>
        </div>

        {{-- NOTES --}}
        <div class="col-12">
          <label class="form-label">Notes</label>
          <textarea name="notes" class="form-control" rows="2">{{ old('notes') }}</textarea>
        </div>

        {{-- TAX PERCENT --}}
        <div class="col-md-2">
          <label class="form-label">PPN (%)</label>
          <input type="text" inputmode="decimal" class="form-control text-end" id="tax_percent"
                 name="tax_percent" placeholder="0"
                 value="{{ old('tax_percent', $ppnDefault ?? 0) }}">
        </div>
      </div>

      {{-- DISCOUNT MODE --}}
      <div class="row g-3 align-items-center mt-3">
        <div class="col-md-6">
          <label class="form-label mb-1">Discount Mode</label>
          <div class="form-selectgroup">
            <label class="form-selectgroup-item">
              <input type="radio" name="discount_mode" value="total" class="form-selectgroup-input" @checked(old('discount_mode',$defaultDiscountMode)==='total')>
              <span class="form-selectgroup-label">Total (global)</span>
            </label>
            <label class="form-selectgroup-item">
              <input type="radio" name="discount_mode" value="per_item" class="form-selectgroup-input" @checked(old('discount_mode')==='per_item')>
              <span class="form-selectgroup-label">Per Item (per-baris)</span>
            </label>
          </div>
          <small class="form-hint">Mode <b>Total</b>: satu diskon untuk seluruh SO. Mode <b>Per Item</b>: tiap baris punya diskon sendiri.</small>
        </div>
      </div>

      <hr class="my-3">

      {{-- STAGING ROW (field nama sekalian untuk cari/pilih item) --}}
      <div id="stageWrap" class="card mb-3">
        <div class="card-body py-2">
          <div class="row g-2 align-items-center">
            <div class="col-xxl-4 col-lg-5">
              <input id="stage_name" type="text" class="form-control" placeholder="Ketik nama/SKU lalu pilih…">
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

      {{-- ITEMS TABLE --}}
      <div class="fw-bold mb-2">Items</div>
      <div id="quotation-lines" class="table-responsive">
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

      {{-- TOTALS PREVIEW --}}
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
      'cancelUrl'    => route('sales-orders.index'),
      'cancelLabel'  => 'Batal',
      'cancelInline' => true,
      'buttons' => [
        ['type' => 'submit', 'label' => 'Simpan', 'class' => 'btn btn-primary'],
      ],
    ])
  </form>
</div>
@endsection

{{-- Template row for dynamic lines --}}
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

@include('customers._quick_modal')

@php
  $CUSTOMER_SEARCH_URL = route('customers.search', [], false);
@endphp

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
{{-- Siapkan data item untuk picker stage_name --}}
@php
  $ITEM_OPTIONS = $items->map(function($it){
    return [
      'id'    => $it->id,
      'label' => $it->name,
      'unit'  => optional($it->unit)->code ?? 'pcs',
      'price' => (float)($it->price ?? 0),
    ];
  })->values();
@endphp

<script>
(function () {
  window.SO_ITEM_OPTIONS = @json($ITEM_OPTIONS);
  'use strict';

  /* ====== CUSTOMER PICKER (TomSelect) ====== */
  const SEARCH_URL = {!! json_encode($CUSTOMER_SEARCH_URL, JSON_UNESCAPED_SLASHES) !!};
  const inputCust  = document.getElementById('customerPicker');
  const hidCustomer = document.getElementById('customer_id');
  const hidContact  = document.getElementById('contact_id');

  if (inputCust && window.TomSelect) {
    const picker = new TomSelect(inputCust, {
      valueField : 'uid',
      labelField : 'label',
      searchField: ['name','label'],
      maxOptions : 30,
      preload    : 'focus',
      create     : false,
      persist    : false,
      dropdownParent: 'body',
      load(query, cb){
        fetch(`${SEARCH_URL}?q=${encodeURIComponent(query||'')}`, {
          credentials: 'same-origin',
          headers: { 'X-Requested-With':'XMLHttpRequest' }
        })
        .then(r => r.ok ? r.text() : '[]')
        .then(t => { try{ cb(JSON.parse(t.trim())); }catch{ cb([]); } })
        .catch(() => cb([]));
      },
      render: { option(d, esc){ return `<div>${esc(d.label || '')}</div>`; } },
      onChange(val){
        const data = this.options[val];
        if (!data) return;
        hidCustomer.value = data.customer_id || '';
        hidContact.value  = data.contact_id  || '';
        this.setTextboxValue(data.label || '');
        this.close();
      }
    });

    const cid = (hidCustomer.value || '').trim();
    if (cid) {
      fetch(`${SEARCH_URL}?q=`, { credentials:'same-origin', headers:{'X-Requested-With':'XMLHttpRequest'} })
        .then(r => r.ok ? r.json() : [])
        .then(rows => {
          const ct = (hidContact.value || '').trim();
          let found = null;
          for (const it of (Array.isArray(rows)?rows:[])) {
            if (String(it.customer_id) === String(cid)) {
              if (ct && String(it.contact_id||'') === String(ct)) { found = it; break; }
              if (!found && it.type === 'customer') found = it;
            }
          }
          if (found) picker.setTextboxValue(found.label);
        }).catch(()=>{});
    }

    const hiddenSelect = document.getElementById('customer_id_select');
    hiddenSelect?.addEventListener('change', () => {
      const opt = hiddenSelect.selectedOptions[0];
      if (!opt) return;
      const newId = opt.value, newLabel = opt.textContent;
      picker.addOption({ uid:`customer-${newId}`, label:newLabel, customer_id:newId, contact_id:'' });
      picker.addItem(`customer-${newId}`);
      hidCustomer.value = newId; hidContact.value='';
      picker.setTextboxValue(newLabel);
    });
  }

  /* ====== HELPER ====== */
  function toNum(v){ if(v==null) return 0; v=String(v).trim(); if(v==='') return 0; v=v.replace(/\s/g,''); const c=v.includes(','), d=v.includes('.'); if(c&&d){v=v.replace(/\./g,'').replace(',', '.')} else {v=v.replace(',', '.')} const n=parseFloat(v); return isNaN(n)?0:n; }
  function rupiah(n){ try{return 'Rp '+new Intl.NumberFormat('id-ID',{minimumFractionDigits:2,maximumFractionDigits:2}).format(n)}catch(e){const f=(Math.round(n*100)/100).toFixed(2); const [a,b]=f.split('.'); return 'Rp '+a.replace(/\B(?=(\d{3})+(?!\d))/g,'.')+','+b} }

  /* ====== ITEM PICKER DI #stage_name ====== */
  (function initStagePicker(){
    const input = document.getElementById('stage_name');
    if (!input || !window.TomSelect) return;

    const opts = (window.SO_ITEM_OPTIONS || []).map(o => ({
      value: String(o.id),
      label: o.label,
      unit : o.unit || 'pcs',
      price: Number(o.price || 0),
    }));

    const ts = new TomSelect(input, {
      options: opts,
      valueField : 'value',
      labelField : 'label',
      searchField: ['label'],
      maxOptions : 50,
      create     : false,
      persist    : false,
      allowEmptyOption: true,
      dropdownParent: 'body',
      render: {
        option(d, esc){
          return `<div class="d-flex justify-content-between">
                    <span>${esc(d.label||'')}</span>
                    <span class="text-muted small">${esc(d.unit||'')}</span>
                  </div>`;
        }
      },
      onChange(val){
        const o = this.options[val];
        document.getElementById('stage_item_id').value = o ? o.value : '';
        document.getElementById('stage_item_variant_id').value = '';
        document.getElementById('stage_unit').value  = o ? (o.unit||'pcs') : 'pcs';
        document.getElementById('stage_price').value = o ? String(o.price||0) : '';
      }
    });

    // simpan instance untuk baca label & reset saat "Tambah"
    input.__ts = ts;

    // Enter untuk langsung Tambah jika item sudah terpilih
    input.addEventListener('keydown', (e)=>{
      if (e.key === 'Enter') {
        e.preventDefault();
        const id = document.getElementById('stage_item_id').value;
        if (id) document.getElementById('stage_add_btn')?.click();
      }
    });
  })();

  /* ====== LINES: TAMBAH/HAPUS ====== */
  const body   = document.getElementById('linesBody');
  const rowTpl = document.getElementById('rowTpl');
  let   lineIdx = 0;

  function addLineFromStage(){
    const id    = (document.getElementById('stage_item_id').value||'').trim();
    const vid   = (document.getElementById('stage_item_variant_id').value||'').trim();

    // Ambil LABEL dari TomSelect (bukan value/id)
    const ts    = document.getElementById('stage_name').__ts;
    const name  = ts ? (ts.getItem(ts.items[0])?.innerText || '') 
                     : (document.getElementById('stage_name').value||'').trim();

    const desc  = document.getElementById('stage_desc').value||'';
    const qty   = toNum(document.getElementById('stage_qty').value||'1');
    const unit  = (document.getElementById('stage_unit').value||'pcs').trim();
    const price = toNum(document.getElementById('stage_price').value||'0');

    if (!id || !name) { alert('Pilih item dulu.'); return; }
    if (qty <= 0)     { alert('Qty minimal 1.'); return; }

    const tr = document.createElement('tr');
    tr.setAttribute('data-line-row','');
    tr.className = 'qline';
    tr.innerHTML = rowTpl.innerHTML.replace(/__IDX__/g, lineIdx);

    tr.querySelector('.q-item-name').value = name;     // <- pakai label
    tr.querySelector('.q-item-id').value   = id;
    tr.querySelector('.q-item-variant-id').value = vid;
    tr.querySelector('.q-item-desc').value = desc;
    tr.querySelector('.q-item-qty').value  = String(qty);
    tr.querySelector('.q-item-unit').value = unit;
    tr.querySelector('.q-item-rate').value = String(price);

    tr.querySelector('.removeRowBtn').addEventListener('click', () => { tr.remove(); recalc(); });

    body.appendChild(tr);
    lineIdx++;

    // Bersihkan staging + reset TomSelect
    document.getElementById('stage_item_id').value = '';
    document.getElementById('stage_item_variant_id').value = '';
    document.getElementById('stage_desc').value = '';
    document.getElementById('stage_qty').value = '1';
    document.getElementById('stage_unit').value = 'pcs';
    document.getElementById('stage_price').value = '';
    if (ts){ ts.clear(); ts.setTextboxValue(''); }

    recalc();
  }
  document.getElementById('stage_add_btn')?.addEventListener('click', addLineFromStage);
  document.getElementById('stage_clear_btn')?.addEventListener('click', () => {
    const ts = document.getElementById('stage_name').__ts;
    document.getElementById('stage_item_id').value = '';
    document.getElementById('stage_item_variant_id').value = '';
    document.getElementById('stage_desc').value = '';
    document.getElementById('stage_qty').value = '1';
    document.getElementById('stage_unit').value = 'pcs';
    document.getElementById('stage_price').value = '';
    if (ts){ ts.clear(); ts.setTextboxValue(''); }
  });

  /* ====== DISKON TOTAL SHOW/HIDE ====== */
  const totalControls    = document.querySelector('[data-section="discount-total-controls"]');
  const totalDiscTypeSel = document.getElementById('total_discount_type');
  const totalDiscValInp  = document.getElementById('total_discount_value');
  const totalDiscUnit    = document.getElementById('totalDiscUnit');

  function toggleTotalControls() {
    const mode = (document.querySelector('input[name="discount_mode"]:checked')?.value) || 'total';
    if (mode === 'per_item') {
      totalControls?.classList.add('d-none');
      totalDiscTypeSel.value = 'amount';
      totalDiscValInp.value  = '0';
      totalDiscUnit.textContent = 'IDR';
    } else {
      totalControls?.classList.remove('d-none');
    }
    recalc();
  }
  document.querySelectorAll('input[name="discount_mode"]').forEach(r => r.addEventListener('change', toggleTotalControls));
  totalDiscTypeSel?.addEventListener('change', () => { totalDiscUnit.textContent = (totalDiscTypeSel.value==='percent') ? '%' : 'IDR'; recalc(); });
  totalDiscValInp?.addEventListener('input', recalc);

  /* ====== RECALC TOTALS ====== */
  const vLinesSubtotal   = document.getElementById('v_lines_subtotal');
  const vTotalDiscAmt    = document.getElementById('v_total_discount_amount');
  const vTotalDiscHint   = document.getElementById('v_total_disc_hint');
  const vTaxableBase     = document.getElementById('v_taxable_base');
  const vTaxPct          = document.getElementById('v_tax_percent');
  const vTaxAmt          = document.getElementById('v_tax_amount');
  const vTotal           = document.getElementById('v_total');
  const taxInput         = document.getElementById('tax_percent');

  function recalc() {
    let linesSubtotal = 0;
    body.querySelectorAll('tr[data-line-row]').forEach(tr => {
      const qty   = toNum(tr.querySelector('.qty')?.value || '0');
      const price = toNum(tr.querySelector('.price')?.value || '0');
      const dtSel = tr.querySelector('.disc-type');
      const dvInp = tr.querySelector('.disc-value');
      const dt    = dtSel ? dtSel.value : 'amount';
      const dvRaw = toNum(dvInp?.value || '0');

      const lineSubtotal = qty * price;
      let discAmount = 0;
      if (dt === 'percent') discAmount = Math.min(Math.max(dvRaw,0),100) / 100 * lineSubtotal;
      else                  discAmount = Math.min(Math.max(dvRaw, 0), lineSubtotal);
      const lineTotal = Math.max(lineSubtotal - discAmount, 0);

      tr.querySelector('.line_subtotal_view').textContent    = rupiah(lineSubtotal);
      tr.querySelector('.line_disc_amount_view').textContent = rupiah(discAmount);
      tr.querySelector('.line_total_view').textContent       = rupiah(lineTotal);

      linesSubtotal += lineTotal;
    });

    vLinesSubtotal.textContent = rupiah(linesSubtotal);

    const mode = (document.querySelector('input[name="discount_mode"]:checked')?.value) || 'total';
    let tdt  = totalDiscTypeSel?.value || 'amount';
    let tdv  = toNum(totalDiscValInp?.value || '0');

    if (mode === 'per_item') { tdt='amount'; tdv=0; }

    const totalDiscAmount = (tdt === 'percent')
      ? Math.min(Math.max(tdv,0),100) / 100 * linesSubtotal
      : Math.min(Math.max(tdv,0), linesSubtotal);

    vTotalDiscAmt.textContent  = rupiah(totalDiscAmount);
    vTotalDiscHint.textContent = (tdt === 'percent' && mode !== 'per_item')
      ? '(' + (Math.round(Math.min(Math.max(tdv,0),100)*100)/100).toFixed(2) + '%)'
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

  // Delegasi event untuk perubahan pada baris
  body.addEventListener('input', e => {
    if (e.target.classList.contains('qty') || e.target.classList.contains('price') || e.target.classList.contains('disc-value')) {
      recalc();
    }
  });
  body.addEventListener('change', e => {
    if (e.target.classList.contains('disc-type')) {
      const unitEl = e.target.closest('tr')?.querySelector('.disc-unit');
      if (unitEl) unitEl.textContent = (e.target.value === 'percent') ? '%' : 'IDR';
      recalc();
    }
  });

  /* ====== PPN AUTO-SYNC DARI COMPANY ====== */
  const selCompany = document.getElementById('company_id');
  function syncTax() {
    const opt = selCompany?.selectedOptions?.[0];
    if (!opt) return;
    const taxable = Number(opt.dataset.taxable) === 1;
    const defTax  = Number(opt.dataset.tax || 0);
    taxInput.value   = taxable ? defTax : 0;
    taxInput.readOnly = !taxable;  // non-taxable → kunci & 0%
    recalc();
  }
  selCompany?.addEventListener('change', syncTax);
  syncTax();

  // Init UI states
  toggleTotalControls();
  recalc();
})();
</script>
@endpush
