{{-- resources/views/sales_orders/create_from_quotation.blade.php --}}
@extends('layouts.tabler')

@section('title', 'Create Sales Order from Quotation')

@section('content')
@php
  use Illuminate\Support\Str;

  /** @var \App\Models\Quotation $quotation */
  $company     = $quotation->company;
  $isTaxable   = (bool) ($company->is_taxable ?? false);
  $taxDefault  = (float) ($quotation->tax_percent ?? $company->default_tax_percent ?? 11);
  $discMode    = $quotation->discount_mode ?? 'total';
  $lines       = $quotation->lines ?? collect();
  $validUntil  = optional($quotation->valid_until)->format('Y-m-d') ?? now()->addDays(30)->format('Y-m-d');

  // Token draft untuk lampiran & cancel (dipakai juga di JS)
  $draftToken  = Str::ulid()->toBase32();
@endphp

<div class="container-xl">
  <form action="{{ route('sales-orders.store-from-quotation', $quotation) }}"
        method="POST" enctype="multipart/form-data" id="soForm"
        class="mode-{{ $discMode === 'per_item' ? 'per' : 'total' }}">
    @csrf
    <input type="hidden" name="draft_token" id="draft_token" value="{{ $draftToken }}">
    <input type="hidden" name="discount_mode" id="discount_mode" value="{{ $discMode }}">

    <div class="card">
      <div class="card-header">
        <div class="card-title">
          Create Sales Order from Quotation
          <span class="text-muted">— {{ $quotation->number }} · {{ $quotation->customer->name ?? '-' }}</span>
        </div>
      </div>

      <div class="card-body">
        {{-- ===== Row 1: PO No, PO Date, Deadline ===== --}}
        <div class="row g-3">
          <div class="col-md-4">
            <label class="form-label required">Customer PO No</label>
            <input type="text" name="po_number" class="form-control" required>
          </div>
          <div class="col-md-4">
            <label class="form-label required">Customer PO Date</label>
            <input type="date" name="po_date" class="form-control" value="{{ now()->format('Y-m-d') }}" required>
          </div>
          <div class="col-md-4">
            <label class="form-label">Deadline</label>
            <input type="date" name="deadline" class="form-control" value="{{ $validUntil }}">
          </div>
        </div>

        {{-- ===== Row 2: Ship To / Bill To ===== --}}
        <div class="row g-3 mt-2">
          <div class="col-md-6">
            <label class="form-label">Ship To</label>
            <textarea name="ship_to" class="form-control" rows="3">{{ $quotation->customer->address ?? '' }}</textarea>
          </div>
          <div class="col-md-6">
            <label class="form-label">Bill To</label>
            <textarea name="bill_to" class="form-control" rows="3">{{ $quotation->customer->address ?? '' }}</textarea>
          </div>
        </div>

        {{-- ===== Row 3: Sales Agent / Notes ===== --}}
        <div class="row g-3 mt-2">
          <div class="col-md-6">
            <label class="form-label">Sales Agent</label>
            <input type="text" class="form-control" value="{{ $quotation->salesUser->name ?? auth()->user()->name }}" readonly>
          </div>
          <div class="col-md-6">
            <label class="form-label">Terms / Notes</label>
            <textarea name="notes" class="form-control" rows="3">{{ $quotation->notes ?? '' }}</textarea>
          </div>
        </div>

        {{-- ===== Attachments (draft upload) ===== --}}
        <div class="mt-3">
          <label class="form-label">Attachments (PO Customer) — PDF/JPG/PNG</label>
          <input type="file" id="soUpload" class="form-control" multiple accept="application/pdf,image/jpeg,image/png">
          <div class="form-text">
            File yang diupload sebelum disimpan akan disimpan sebagai <em>draft</em> dan otomatis terhubung ke SO saat kamu klik “Create SO”.
          </div>
          <div id="soFiles" class="list-group list-group-flush mt-2"></div>
        </div>

        {{-- ===== Discount mode selector ===== --}}
        <div class="d-flex align-items-center gap-3 mt-4">
          <div class="fw-bold">Discount Mode</div>
          <div class="btn-group btn-group-sm" role="group">
            <input type="radio" class="btn-check" name="dm" id="dm-total" value="total" {{ $discMode === 'total' ? 'checked' : '' }}>
            <label class="btn btn-outline-primary" for="dm-total">Total</label>

            <input type="radio" class="btn-check" name="dm" id="dm-per" value="per_item" {{ $discMode === 'per_item' ? 'checked' : '' }}>
            <label class="btn btn-outline-primary" for="dm-per">Per Item</label>
          </div>
          <div class="form-hint">Ganti mode akan berpengaruh ke cara hitung. Data yang disembunyikan tidak dipakai dalam total.</div>
          <input type="hidden" name="discount_mode" id="discount_mode_hidden" value="{{ $discMode }}">
        </div>

        <hr class="my-3">

        {{-- ===== STAGE ROW: ketik & pilih item (TomSelect) ===== --}}
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

        {{-- ===== ITEMS TABLE (sama struktur dengan create SO) ===== --}}
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

        {{-- ===== TOTALS PREVIEW ===== --}}
        <div class="row justify-content-end mt-4">
          <div class="col-md-6">
            <div class="card">
              <div class="card-body">
                <div class="row g-2 align-items-center mb-2 mode-total" data-section="discount-total-controls">
                  <div class="col-auto"><label class="form-label mb-0">Diskon Total</label></div>
                  <div class="col-auto">
                    @php $tdt = $quotation->total_discount_type ?? 'amount'; @endphp
                    <select name="total_discount_type" id="total_discount_type" class="form-select form-select-sm" style="min-width:140px">
                      <option value="amount" {{ $tdt==='amount'?'selected':'' }}>Nominal (IDR)</option>
                      <option value="percent" {{ $tdt==='percent'?'selected':'' }}>Persen (%)</option>
                    </select>
                  </div>
                  <div class="col">
                    <div class="input-group input-group-sm">
                      <input type="text" name="total_discount_value" id="total_discount_value" class="form-control text-end"
                             value="{{ rtrim(rtrim(number_format((float)($quotation->total_discount_value ?? 0), 2, '.', ''), '0'), '.') }}">
                      <span class="input-group-text" id="totalDiscUnit">IDR</span>
                    </div>
                  </div>
                </div>

                <table class="table mb-0">
                  <tr><td>Subtotal (setelah diskon per-baris)</td><td class="text-end" id="v_lines_subtotal">Rp 0</td></tr>
                  <tr class="mode-total"><td>Diskon Total <span class="text-muted" id="v_total_disc_hint"></span></td><td class="text-end" id="v_total_discount_amount">Rp 0</td></tr>
                  <tr><td>Dasar Pajak</td><td class="text-end" id="v_taxable_base">Rp 0</td></tr>
                  <tr>
                    <td>PPN (<span id="v_tax_percent">{{ number_format($taxDefault,2,'.','') }}</span>%)</td>
                    <td class="text-end" id="v_tax_amount">Rp 0</td>
                  </tr>
                  <tr class="fw-bold"><td>Grand Total</td><td class="text-end" id="v_total">Rp 0</td></tr>
                </table>

                {{-- Hidden values untuk server (opsional) --}}
                <input type="hidden" name="lines_subtotal" id="i_subtotal" value="0">
                <input type="hidden" name="total_discount_amount" id="i_total_dc" value="0">
                <input type="hidden" name="taxable_base" id="i_dpp" value="0">
                <input type="hidden" name="tax_amount" id="i_ppn" value="0">
                <input type="hidden" name="total" id="i_grand" value="0">

                {{-- Tax percent input (readonly jika non-taxable) --}}
                <input type="hidden" id="tax_percent" name="tax_percent" value="{{ $isTaxable ? rtrim(rtrim(number_format($taxDefault, 2, '.', ''), '0'), '.') : 0 }}">
              </div>
            </div>
          </div>
        </div>
      </div>

      <div class="card-footer d-flex justify-content-end gap-2">
        <button type="button" id="btnCancelDraft" class="btn btn-link text-danger">Cancel</button>
        <button type="submit" class="btn btn-primary">Create SO</button>
      </div>
    </div>
  </form>
</div>

{{-- Template row untuk lines table --}}
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

@push('styles')
{{-- TomSelect CSS (fallback CDN) --}}
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/tom-select@2.3.1/dist/css/tom-select.bootstrap5.min.css">
<style>
  /* Pastikan dropdown berada di atas elemen lain */
  .ts-dropdown{ z-index:1060 !important; }

  /* >>> Paksa background TomSelect solid putih (tidak transparan) */
  .ts-wrapper .ts-control,
  .ts-wrapper.single.input-active .ts-control,
  .ts-wrapper.single.has-items .ts-control {
    background-color:#fff !important;
  }
  .ts-dropdown {
    background-color:#fff !important;
    border:1px solid rgba(0,0,0,.12) !important;
    box-shadow:0 10px 24px rgba(0,0,0,.12) !important;
    backdrop-filter:none !important;
  }
  .ts-dropdown .option,
  .ts-dropdown .create,
  .ts-dropdown .no-results,
  .ts-dropdown .optgroup-header {
    background-color:#fff !important;
  }
  .ts-dropdown .active {
    background-color:#f1f5f9 !important; /* slate-100 */
  }

  /* ===== Tabel Items ===== */
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

  /* Sembunyikan kontrol diskon total saat mode per-item */
  .mode-per [data-section="discount-total-controls"]{ display:none!important; }
</style>
@endpush

@push('scripts')
{{-- Siapkan data item untuk picker stage_name --}}
@php
  /** @var \Illuminate\Support\Collection $items */
  $ITEM_OPTIONS = ($items ?? collect())->map(function($it){
    return [
      'id'    => $it->id,
      'label' => $it->name,
      'unit'  => optional($it->unit)->code ?? 'pcs',
      'price' => (float)($it->price ?? 0),
    ];
  })->values();
@endphp
<script>
  (function(){ window.SO_ITEM_OPTIONS = @json($ITEM_OPTIONS); })();
</script>

<script>
(function () {
  'use strict';

  /* ===== Helpers ===== */
  function toNum(v){ if(v==null) return 0; v=String(v).trim(); if(v==='') return 0; v=v.replace(/\s/g,''); const c=v.includes(','), d=v.includes('.'); if(c&&d){v=v.replace(/\./g,'').replace(',', '.')} else {v=v.replace(',', '.')} const n=parseFloat(v); return isNaN(n)?0:n; }
  function rupiah(n){ try{return 'Rp '+new Intl.NumberFormat('id-ID',{minimumFractionDigits:2,maximumFractionDigits:2}).format(n)}catch(e){const f=(Math.round(n*100)/100).toFixed(2); const [a,b]=f.split('.'); return 'Rp '+a.replace(/\B(?=(\d{3})+(?!\d))/g,'.')+','+b} }

  /* ===== Pastikan TomSelect tersedia (fallback CDN jika belum ada) ===== */
  function ensureTomSelect(){
    return new Promise((resolve, reject) => {
      if (window.TomSelect) return resolve(true);
      const s = document.createElement('script');
      s.src = 'https://cdn.jsdelivr.net/npm/tom-select@2.3.1/dist/js/tom-select.complete.min.js';
      s.onload = () => resolve(true);
      s.onerror = reject;
      document.head.appendChild(s);
      if (!document.querySelector('link[data-ts]')) {
        const l = document.createElement('link');
        l.rel = 'stylesheet';
        l.href = 'https://cdn.jsdelivr.net/npm/tom-select@2.3.1/dist/css/tom-select.bootstrap5.min.css';
        l.setAttribute('data-ts','');
        document.head.appendChild(l);
      }
    });
  }

  /* ===== Item picker di #stage_name ===== */
  async function initStagePicker(){
    const input = document.getElementById('stage_name');
    if (!input) return;

    try { await ensureTomSelect(); } catch(e){ console.warn('[SO] Gagal load TomSelect', e); return; }

    const opts = (window.SO_ITEM_OPTIONS || []).map(o => ({
      value: String(o.id),
      label: o.label,
      unit:  o.unit || 'pcs',
      price: Number(o.price || 0),
    }));

    input.setAttribute('autocomplete','off');

    const ts = new TomSelect(input, {
      options: opts,
      valueField: 'value',
      labelField: 'label',
      searchField: ['label'],
      maxOptions: 100,
      create: false,
      persist: false,
      dropdownParent: 'body',
      preload: true,
      openOnFocus: true,
      render:{
        option(d,esc){
          return `<div class="d-flex justify-content-between">
                    <span>${esc(d.label || '')}</span>
                    <span class="text-muted small">${esc(d.unit || '')}</span>
                  </div>`;
        }
      },
      onChange(val){
        const o = this.options[val];
        const hidId   = document.getElementById('stage_item_id');
        const hidVar  = document.getElementById('stage_item_variant_id');
        const unitInp = document.getElementById('stage_unit');
        const priceInp= document.getElementById('stage_price');
        if (hidId)   hidId.value  = o ? o.value : '';
        if (hidVar)  hidVar.value = '';
        if (unitInp) unitInp.value= o ? (o.unit || 'pcs') : 'pcs';
        if (priceInp)priceInp.value= o ? String(o.price || 0) : '';
      }
    });

    // >>> simpan instance untuk dipakai saat Tambah & reset
    input.__ts = ts;

    input.addEventListener('focus', () => ts.open());
    input.addEventListener('keydown', (e)=>{
      if (e.key==='Enter'){
        e.preventDefault();
        const id=(document.getElementById('stage_item_id')||{}).value || '';
        if(id){
          const btn = document.getElementById('stage_add_btn');
          if (btn) btn.click();
        }
      }
    });
  }

  /* ===== Lines + recalc ===== */
  const body   = document.getElementById('linesBody');
  const rowTpl = document.getElementById('rowTpl');
  let   lineIdx = 0;

  const totalDiscTypeSel = document.getElementById('total_discount_type');
  const totalDiscValInp  = document.getElementById('total_discount_value');
  const vLinesSubtotal   = document.getElementById('v_lines_subtotal');
  const vTotalDiscAmt    = document.getElementById('v_total_discount_amount');
  const vTotalDiscHint   = document.getElementById('v_total_disc_hint');
  const vTaxableBase     = document.getElementById('v_taxable_base');
  const vTaxPct          = document.getElementById('v_tax_percent');
  const vTaxAmt          = document.getElementById('v_tax_amount');
  const vTotal           = document.getElementById('v_total');
  const taxInput         = document.getElementById('tax_percent');
  const form             = document.getElementById('soForm');

  function recalc() {
    let linesSubtotal = 0;
    body.querySelectorAll('tr[data-line-row]').forEach(tr => {
      const qty   = toNum((tr.querySelector('.qty')||{}).value || '0');
      const price = toNum((tr.querySelector('.price')||{}).value || '0');
      const dtSel = tr.querySelector('.disc-type');
      const dvInp = tr.querySelector('.disc-value');
      const dt    = dtSel ? dtSel.value : 'amount';
      const dvRaw = toNum((dvInp||{}).value || '0');

      const lineSubtotal = qty * price;
      let discAmount = 0;
      if (dt === 'percent') discAmount = Math.min(Math.max(dvRaw,0),100) / 100 * lineSubtotal;
      else                  discAmount = Math.min(Math.max(dvRaw, 0), lineSubtotal);
      const lineTotal = Math.max(lineSubtotal - discAmount, 0);

      const sv = tr.querySelector('.line_subtotal_view');
      const dv = tr.querySelector('.line_disc_amount_view');
      const tv = tr.querySelector('.line_total_view');
      if (sv) sv.textContent = rupiah(lineSubtotal);
      if (dv) dv.textContent = rupiah(discAmount);
      if (tv) tv.textContent = rupiah(lineTotal);

      linesSubtotal += lineTotal;
    });

    if (vLinesSubtotal) vLinesSubtotal.textContent = rupiah(linesSubtotal);

    const mode = form.classList.contains('mode-per') ? 'per_item' : 'total';
    let tdt  = (totalDiscTypeSel||{}).value || 'amount';
    let tdv  = toNum((totalDiscValInp||{}).value || '0');
    if (mode === 'per_item') { tdt='amount'; tdv=0; }

    const totalDiscAmount = (tdt === 'percent')
      ? Math.min(Math.max(tdv,0),100) / 100 * linesSubtotal
      : Math.min(Math.max(tdv,0), linesSubtotal);

    if (vTotalDiscAmt)  vTotalDiscAmt.textContent  = rupiah(totalDiscAmount);
    if (vTotalDiscHint) vTotalDiscHint.textContent = (tdt === 'percent' && mode !== 'per_item')
      ? '(' + (Math.round(Math.min(Math.max(tdv,0),100)*100)/100).toFixed(2) + '%)'
      : '';

    const base   = Math.max(linesSubtotal - totalDiscAmount, 0);
    const taxPct = toNum((taxInput||{}).value || '0');
    const taxAmt = base * Math.max(taxPct, 0) / 100;
    const total  = base + taxAmt;

    if (vTaxableBase) vTaxableBase.textContent = rupiah(base);
    if (vTaxPct)      vTaxPct.textContent      = (Math.round(taxPct * 100) / 100).toFixed(2);
    if (vTaxAmt)      vTaxAmt.textContent      = rupiah(taxAmt);
    if (vTotal)       vTotal.textContent       = rupiah(total);

    const iSub = document.getElementById('i_subtotal');
    if (iSub) iSub.setAttribute('value', base.toFixed(2));
  }

  function addLineFromData(d){
    const tr = document.createElement('tr');
    tr.setAttribute('data-line-row','');
    tr.className = 'qline';
    tr.innerHTML = rowTpl.innerHTML.replace(/__IDX__/g, lineIdx);

    (tr.querySelector('.q-item-name')||{}).value = d.name || '';
    (tr.querySelector('.q-item-id')||{}).value   = d.item_id || '';
    (tr.querySelector('.q-item-variant-id')||{}).value = d.item_variant_id || '';
    (tr.querySelector('.q-item-desc')||{}).value = d.description || '';
    (tr.querySelector('.q-item-qty')||{}).value  = String(d.qty || 1);
    (tr.querySelector('.q-item-unit')||{}).value = d.unit || 'pcs';
    (tr.querySelector('.q-item-rate')||{}).value = String(d.unit_price || 0);

    const dtSel = tr.querySelector('.disc-type');
    const dvInp = tr.querySelector('.disc-value');
    if (dtSel && d.discount_type) dtSel.value = d.discount_type;
    if (dvInp && typeof d.discount_value !== 'undefined') dvInp.value = String(d.discount_value);

    const rm = tr.querySelector('.removeRowBtn');
    if (rm) rm.addEventListener('click', () => { tr.remove(); recalc(); });

    body.appendChild(tr);
    lineIdx++;
  }

  function addLineFromStage(){
    const ts    = document.getElementById('stage_name').__ts; // instance TomSelect
    const label = ts ? (ts.getItem(ts.items[0])?.innerText || '') 
                     : (document.getElementById('stage_name')?.value || '').trim();

    const d = {
      item_id        : ((document.getElementById('stage_item_id')||{}).value || '').trim(),
      item_variant_id: ((document.getElementById('stage_item_variant_id')||{}).value || '').trim(),
      name           : label, // >>> pakai LABEL (nama lengkap), bukan id
      description    : (document.getElementById('stage_desc')||{}).value || '',
      qty            : toNum((document.getElementById('stage_qty')||{}).value || '1'),
      unit           : (((document.getElementById('stage_unit')||{}).value || 'pcs').trim()),
      unit_price     : toNum((document.getElementById('stage_price')||{}).value || '0'),
      discount_type  : 'amount',
      discount_value : 0,
    };
    if (!d.item_id || !d.name) { alert('Pilih item dulu.'); return; }
    if (d.qty <= 0) { alert('Qty minimal 1.'); return; }

    addLineFromData(d);

    // >>> reset stage + TomSelect
    ['stage_item_id','stage_item_variant_id','stage_desc','stage_qty','stage_unit','stage_price']
      .forEach(id=>{
        const el = document.getElementById(id);
        if (!el) return;
        if (id==='stage_qty') el.value = '1';
        else if (id==='stage_unit') el.value = 'pcs';
        else el.value = '';
      });
    if (ts){ ts.clear(); ts.setTextboxValue(''); }

    recalc();
  }

  const addBtn = document.getElementById('stage_add_btn');
  if (addBtn) addBtn.addEventListener('click', addLineFromStage);

  const clrBtn = document.getElementById('stage_clear_btn');
  if (clrBtn) clrBtn.addEventListener('click', () => {
    ['stage_item_id','stage_item_variant_id','stage_desc','stage_qty','stage_unit','stage_price']
      .forEach(id=>{
        const el = document.getElementById(id);
        if (!el) return;
        if (id==='stage_qty') el.value = '1';
        else if (id==='stage_unit') el.value = 'pcs';
        else el.value = '';
      });
    const ts = document.getElementById('stage_name').__ts;
    if (ts){ ts.clear(); ts.setTextboxValue(''); } // reset picker
  });

  // Delegasi event pada tabel
  const bodyEl = document.getElementById('linesBody');
  bodyEl.addEventListener('input', e => {
    if (e.target.classList.contains('qty') || e.target.classList.contains('price') || e.target.classList.contains('disc-value')) recalc();
  });
  bodyEl.addEventListener('change', e => {
    if (e.target.classList.contains('disc-type')) {
      const unitEl = e.target.closest('tr') && e.target.closest('tr').querySelector('.disc-unit');
      if (unitEl) unitEl.textContent = (e.target.value==='percent') ? '%' : 'IDR';
      recalc();
    }
  });

  // Mode switch
  function applyMode(mode){
    const f = document.getElementById('soForm');
    if (!f) return;
    if (mode === 'per_item') {
      f.classList.add('mode-per'); f.classList.remove('mode-total');
    } else {
      f.classList.add('mode-total'); f.classList.remove('mode-per');
    }
    recalc();
  }
  const dmTot = document.getElementById('dm-total');
  if (dmTot) dmTot.addEventListener('change', ()=>applyMode('total'));
  const dmPer = document.getElementById('dm-per');
  if (dmPer) dmPer.addEventListener('change',  ()=>applyMode('per_item'));

  // ===== Upload draft attachments =====
  (function(){
    const uploadInput = document.getElementById('soUpload');
    const listEl      = document.getElementById('soFiles');
    const draftToken  = (document.getElementById('draft_token')||{}).value || '';
    const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';

    function rowFile(file){
      return `<div class="list-group-item d-flex align-items-center gap-2" data-id="${file.id}">
        <a class="me-auto" href="${file.url}" target="_blank" rel="noopener">${file.name}</a>
        <span class="text-secondary small">${Math.round((file.size||0)/1024)} KB</span>
        <button type="button" class="btn btn-sm btn-outline-danger">Hapus</button>
      </div>`;
    }
    async function refreshList(){
      if (!draftToken) { if(listEl) listEl.innerHTML=''; return; }
      try{
        const url = @json(route('sales-orders.attachments.index')) + '?draft_token=' + encodeURIComponent(draftToken);
        const res = await fetch(url,{headers:{'X-Requested-With':'XMLHttpRequest','Accept':'application/json'},credentials:'same-origin'});
        let text = (await res.text()).replace(/^\uFEFF/,'').trim();
        if (text.startsWith("'") && text.endsWith("'")) text=text.slice(1,-1);
        let data; try{ data=JSON.parse(text); }catch{ if(listEl) listEl.innerHTML=''; return; }
        const files = Array.isArray(data)?data:[];
        if (listEl) listEl.innerHTML = files.map(rowFile).join('');
        (listEl||document).querySelectorAll('#soFiles button').forEach(btn=>{
          btn.addEventListener('click', async (e)=>{
            const row = e.target.closest('[data-id]'); const id=row && row.dataset.id;
            const delUrl=@json(route('sales-orders.attachments.destroy','__ID__')).replace('__ID__', id);
            await fetch(delUrl,{method:'DELETE',headers:{'X-CSRF-TOKEN':csrf,'X-Requested-With':'XMLHttpRequest','Accept':'application/json'},credentials:'same-origin'});
            if (row) row.remove();
          });
        });
      }catch{ if(listEl) listEl.innerHTML=''; }
    }
    if (uploadInput) uploadInput.addEventListener('change', async (e)=>{
      for (const f of e.target.files){
        const fd=new FormData(); fd.append('file',f); fd.append('draft_token',draftToken);
        const res=await fetch(@json(route('sales-orders.attachments.upload')),{method:'POST',headers:{'X-CSRF-TOKEN':csrf,'X-Requested-With':'XMLHttpRequest','Accept':'application/json'},body:fd,credentials:'same-origin'});
        if (!res.ok) console.error('upload gagal', await res.text());
      }
      uploadInput.value=''; refreshList();
    });
    const cancelBtn = document.getElementById('btnCancelDraft');
    if (cancelBtn) cancelBtn.addEventListener('click', async ()=>{
      await fetch(@json(route('sales-orders.create.cancel')),{method:'DELETE',headers:{'X-CSRF-TOKEN':@json(csrf_token()),'Content-Type':'application/json'},body:JSON.stringify({draft_token:draftToken}),credentials:'same-origin'});
      history.back();
    });
    refreshList();
  })();

  // ===== Preload lines dari quotation =====
  @php
    $PRELOAD = ($lines ?? collect())->map(function($ln){
      return [
        'item_id'         => $ln->item_id,
        'item_variant_id' => $ln->item_variant_id,
        'name'            => $ln->name,
        'description'     => $ln->description,
        'qty'             => (float) ($ln->qty),
        'unit'            => $ln->unit ?? 'pcs',
        'unit_price'      => (float) ($ln->unit_price),
        'discount_type'   => $ln->discount_type ?? 'amount',
        'discount_value'  => (float) ($ln->discount_value ?? 0),
      ];
    })->values();
  @endphp
  const PRELOAD = @json($PRELOAD);
  PRELOAD.forEach(addLineFromData);

  // Init
  initStagePicker();                          // inisialisasi TomSelect
  applyMode(@json($discMode === 'per_item' ? 'per_item' : 'total'));  // set mode awal
  recalc();
})();
</script>
@endpush
@endsection
