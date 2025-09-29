﻿{{-- resources/views/sales_orders/create_from_quotation.blade.php --}}
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
        class="card mode-{{ $discMode === 'per_item' ? 'per' : 'total' }}">
    @csrf
    <input type="hidden" name="draft_token" id="draft_token" value="{{ $draftToken }}">
    <input type="hidden" name="discount_mode" id="discount_mode_hidden" value="{{ $discMode }}">

    <div class="card-header">
      <div class="card-title">
        Create Sales Order from Quotation
        <span class="text-muted">— {{ $quotation->number }} · {{ $quotation->customer->name ?? '-' }}</span>
      </div>
    </div>

    <div class="card-body">
      {{-- Row 1: PO No, PO Date, Deadline --}}
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

      {{-- Row 2: Ship To / Bill To --}}
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

      {{-- Row 3: Sales Agent --}}
      <div class="row g-3 mt-2">
        <div class="col-md-6">
          <label class="form-label">Sales Agent</label>
          <input type="text" class="form-control" value="{{ $quotation->salesUser->name ?? auth()->user()->name }}" readonly>
        </div>
      </div>

      {{-- Attachments (draft upload) --}}
      <div class="mt-3">
        <label class="form-label">Attachments (PO Customer) — PDF/JPG/PNG</label>
        <input type="file" id="soUpload" class="form-control" multiple accept="application/pdf,image/jpeg,image/png">
        <div class="form-text">
          File yang diupload sebelum disimpan akan disimpan sebagai <em>draft</em> dan otomatis terhubung ke SO saat kamu klik “Create SO”.
        </div>
        <div id="soFiles" class="list-group list-group-flush mt-2"></div>
      </div>

      {{-- TABS: Items & More Info --}}
      <ul class="nav nav-tabs mt-4" role="tablist">
        <li class="nav-item"><a class="nav-link active" data-bs-toggle="tab" href="#tab-items" role="tab">Items</a></li>
        <li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#tab-moreinfo" role="tab">More Info</a></li>
      </ul>

      <div class="tab-content border-start border-end border-bottom p-3">
        {{-- ===== TAB: ITEMS ===== --}}
        <div class="tab-pane fade show active" id="tab-items" role="tabpanel">
          {{-- Discount mode selector --}}
          <div class="d-flex align-items-center gap-3 mb-3">
            <div class="fw-bold">Discount Mode</div>
            <div class="btn-group btn-group-sm" role="group">
              <input type="radio" class="btn-check" name="dm" id="dm-total" value="total" {{ $discMode === 'total' ? 'checked' : '' }}>
              <label class="btn btn-outline-primary" for="dm-total">Total</label>

              <input type="radio" class="btn-check" name="dm" id="dm-per" value="per_item" {{ $discMode === 'per_item' ? 'checked' : '' }}>
              <label class="btn btn-outline-primary" for="dm-per">Per Item</label>
            </div>
            <div class="form-hint">Ganti mode mempengaruhi cara hitung. Data tersembunyi tidak dipakai di total.</div>
          </div>

          {{-- STAGE ROW --}}
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

          {{-- TOTALS PREVIEW --}}
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
                    <tr><td>PPN (<span id="v_tax_percent">{{ number_format($taxDefault,2,'.','') }}</span>%)</td><td class="text-end" id="v_tax_amount">Rp 0</td></tr>
                    <tr class="fw-bold"><td>Grand Total</td><td class="text-end" id="v_total">Rp 0</td></tr>
                  </table>

                  {{-- hidden untuk server (opsional) --}}
                  <input type="hidden" name="lines_subtotal" id="i_subtotal" value="0">
                  <input type="hidden" name="total_discount_amount" id="i_total_dc" value="0">
                  <input type="hidden" name="taxable_base" id="i_dpp" value="0">
                  <input type="hidden" name="tax_amount" id="i_ppn" value="0">
                  <input type="hidden" name="total" id="i_grand" value="0">
                  <input type="hidden" id="tax_percent" name="tax_percent" value="{{ $isTaxable ? rtrim(rtrim(number_format($taxDefault, 2, '.', ''), '0'), '.') : 0 }}">
                </div>
              </div>
            </div>
          </div>
        </div>

        {{-- ===== TAB: MORE INFO ===== --}}
        <div class="tab-pane fade" id="tab-moreinfo" role="tabpanel">
          <div class="row g-3">
            <div class="col-md-8">
              <label class="form-label">Customer notes</label>
              <textarea name="notes" class="form-control" rows="4">{{ old('notes', $quotation->notes ?? '') }}</textarea>
              <small class="form-hint">Catatan ini terlihat oleh customer (SO / Invoice).</small>
            </div>
            <div class="col-md-4">
              <label class="form-label">Under</label>
              <div class="input-group">
                <span class="input-group-text">IDR</span>
                <input type="text" name="under_amount" class="form-control text-end" inputmode="decimal"
                       value="{{ old('under_amount', rtrim(rtrim(number_format((float)($quotation->under_amount ?? 0), 2, '.', ''), '0'), '.')) }}">
              </div>
              <small class="form-hint">Dipakai pada perhitungan net & komisi.</small>
            </div>
          </div>
        </div>
      </div>
    </div>

    <div class="card-footer d-flex justify-content-end gap-2">
      <button type="button" id="btnCancelDraft" class="btn btn-link text-danger">Cancel</button>
      <button type="submit" class="btn btn-primary">Create SO</button>
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
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/tom-select@2.3.1/dist/css/tom-select.bootstrap5.min.css">
<style>
  .nav-tabs .nav-link { cursor:pointer; }

  /* TomSelect: pastikan solid putih, tidak transparan */
  .ts-dropdown{ z-index:1060 !important; }
  .ts-wrapper .ts-control,
  .ts-wrapper.single.input-active .ts-control,
  .ts-wrapper.single.has-items .ts-control { background-color:#fff !important; }
  .ts-dropdown{
    background:#fff !important; border:1px solid rgba(0,0,0,.12) !important;
    box-shadow:0 10px 24px rgba(0,0,0,.12) !important; backdrop-filter:none !important;
  }
  .ts-dropdown .option,.ts-dropdown .create,.ts-dropdown .no-results,.ts-dropdown .optgroup-header{ background:#fff !important; }
  .ts-dropdown .active{ background:#f1f5f9 !important; }

  /* Tabel Items */
  #linesTable th, #linesTable td { vertical-align: middle; }
  #linesTable .col-item       { width:22%; }  #linesTable .col-desc{ width:20%; }
  #linesTable .col-qty        { width:6.5ch;} #linesTable .col-unit{ width:7ch; }
  #linesTable .col-price      { width:14%; }  #linesTable .col-disc{ width:16%; }
  #linesTable .col-subtotal   { width:9%; }   #linesTable .col-disc-amount{ width:9%; }
  #linesTable .col-total      { width:14%; }  #linesTable .col-actions{ width:4%; }
  #linesTable input.qty { max-width:6.5ch; }  #linesTable input.unit{ max-width:7ch; }
  #linesTable .disc-cell .form-select{ min-width:120px; }
  #linesTable .disc-cell .disc-value{ max-width:8ch; }
  #linesTable .disc-cell .input-group-text.disc-unit{ min-width:46px; justify-content:center; }
  #linesTable .line_total_view{ font-weight:700; font-size:1.06rem; }
  #linesTable .line_subtotal_view{ font-size:.92rem; }

  /* Sembunyikan kontrol diskon total saat mode per-item */
  .mode-per [data-section="discount-total-controls"]{ display:none!important; }
</style>
@endpush

@push('scripts')
{{-- Data item untuk TomSelect --}}
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
<script> (function(){ window.SO_ITEM_OPTIONS = @json($ITEM_OPTIONS); })(); </script>

<script>
(function () {
  'use strict';

  /* Helpers */
  const toNum = (v)=>{ if(v==null) return 0; v=String(v).trim(); if(!v) return 0; v=v.replace(/\s/g,''); const c=v.includes(','), d=v.includes('.'); if(c&&d){v=v.replace(/\./g,'').replace(',', '.')} else {v=v.replace(',', '.')} const n=parseFloat(v); return isNaN(n)?0:n; };
  const rupiah = (n)=>{ try{ return 'Rp '+new Intl.NumberFormat('id-ID',{minimumFractionDigits:2,maximumFractionDigits:2}).format(n) }catch(e){ const f=(Math.round(n*100)/100).toFixed(2); const [a,b]=f.split('.'); return 'Rp '+a.replace(/\B(?=(\d{3})+(?!\d))/g,'.')+','+b } };

  /* TomSelect loader (fallback) */
  function ensureTomSelect(){
    return new Promise((resolve,reject)=>{
      if (window.TomSelect) return resolve();
      const s=document.createElement('script'); s.src='https://cdn.jsdelivr.net/npm/tom-select@2.3.1/dist/js/tom-select.complete.min.js';
      s.onload=resolve; s.onerror=reject; document.head.appendChild(s);
    });
  }

  /* Init item picker */
  async function initStagePicker(){
    const input=document.getElementById('stage_name'); if(!input) return;
    try{ await ensureTomSelect(); }catch(e){ console.warn('TomSelect gagal dimuat',e); return; }
    const opts=(window.SO_ITEM_OPTIONS||[]).map(o=>({value:String(o.id),label:o.label,unit:o.unit||'pcs',price:Number(o.price||0)}));
    const ts=new TomSelect(input,{
      options:opts, valueField:'value', labelField:'label', searchField:['label'],
      maxOptions:100, create:false, persist:false, dropdownParent:'body', preload:true, openOnFocus:true,
      render:{ option(d,esc){ return `<div class="d-flex justify-content-between"><span>${esc(d.label||'')}</span><span class="text-muted small">${esc(d.unit||'')}</span></div>`; } },
      onChange(val){ const o=this.options[val];
        (document.getElementById('stage_item_id')||{}).value  = o ? o.value : '';
        (document.getElementById('stage_item_variant_id')||{}).value = '';
        (document.getElementById('stage_unit')||{}).value = o ? (o.unit||'pcs') : 'pcs';
        (document.getElementById('stage_price')||{}).value = o ? String(o.price||0) : '';
      }
    });
    input.__ts = ts;
  }

  /* Lines & Recalc */
  const form           = document.getElementById('soForm');
  const body           = document.getElementById('linesBody');
  const rowTpl         = document.getElementById('rowTpl');
  const totalTypeSel   = document.getElementById('total_discount_type');
  const totalValInp    = document.getElementById('total_discount_value');
  const vSub           = document.getElementById('v_lines_subtotal');
  const vTotalDcAmt    = document.getElementById('v_total_discount_amount');
  const vTotalDcHint   = document.getElementById('v_total_disc_hint');
  const vDpp           = document.getElementById('v_taxable_base');
  const vTaxPct        = document.getElementById('v_tax_percent');
  const vTaxAmt        = document.getElementById('v_tax_amount');
  const vTotal         = document.getElementById('v_total');
  const taxInput       = document.getElementById('tax_percent');
  let idx = 0;

  function recalc(){
    let sub = 0;
    body.querySelectorAll('tr[data-line-row]').forEach(tr=>{
      const qty   = toNum((tr.querySelector('.qty')||{}).value || '0');
      const price = toNum((tr.querySelector('.price')||{}).value || '0');
      const dt    = (tr.querySelector('.disc-type')||{}).value || 'amount';
      const dvRaw = toNum((tr.querySelector('.disc-value')||{}).value || '0');

      const lineSub = qty*price;
      const discAmt = dt==='percent' ? Math.min(Math.max(dvRaw,0),100)/100*lineSub : Math.min(Math.max(dvRaw,0),lineSub);
      const lineTot = Math.max(lineSub-discAmt,0);

      (tr.querySelector('.line_subtotal_view')||{}).textContent = rupiah(lineSub);
      (tr.querySelector('.line_disc_amount_view')||{}).textContent = rupiah(discAmt);
      (tr.querySelector('.line_total_view')||{}).textContent = rupiah(lineTot);

      sub += lineTot;
    });
    if (vSub) vSub.textContent = rupiah(sub);

    const mode = form.classList.contains('mode-per') ? 'per_item' : 'total';
    let tdt = (totalTypeSel||{}).value || 'amount';
    let tdv = toNum((totalValInp||{}).value || '0');
    if (mode==='per_item'){ tdt='amount'; tdv=0; }

    const tDisc = tdt==='percent' ? Math.min(Math.max(tdv,0),100)/100*sub : Math.min(Math.max(tdv,0),sub);
    if (vTotalDcAmt)  vTotalDcAmt.textContent = rupiah(tDisc);
    if (vTotalDcHint) vTotalDcHint.textContent = (tdt==='percent' && mode!=='per_item') ? '('+(Math.round(Math.min(Math.max(tdv,0),100)*100)/100).toFixed(2)+'%)' : '';

    const dpp = Math.max(sub - tDisc, 0);
    const tp  = toNum((taxInput||{}).value || '0');
    const ppn = dpp * Math.max(tp,0)/100;
    const tot = dpp + ppn;

    if (vDpp)   vDpp.textContent   = rupiah(dpp);
    if (vTaxPct)vTaxPct.textContent= (Math.round(tp*100)/100).toFixed(2);
    if (vTaxAmt)vTaxAmt.textContent= rupiah(ppn);
    if (vTotal) vTotal.textContent = rupiah(tot);
  }

  function addRow(values={}){
    const tr = document.createElement('tr');
    tr.setAttribute('data-line-row','');
    tr.className='qline';
    tr.innerHTML = rowTpl.innerHTML.replace(/__IDX__/g, idx);

    (tr.querySelector('.q-item-name')||{}).value = values.name || '';
    (tr.querySelector('.q-item-id')||{}).value   = values.item_id || '';
    (tr.querySelector('.q-item-variant-id')||{}).value = values.item_variant_id || '';
    (tr.querySelector('.q-item-desc')||{}).value = values.description || '';
    (tr.querySelector('.q-item-qty')||{}).value  = String(values.qty ?? 1);
    (tr.querySelector('.q-item-unit')||{}).value = values.unit || 'pcs';
    (tr.querySelector('.q-item-rate')||{}).value = String(values.unit_price ?? 0);
    if (values.discount_type)  (tr.querySelector('.disc-type')||{}).value = values.discount_type;
    if (values.discount_value != null) (tr.querySelector('.disc-value')||{}).value = String(values.discount_value);

    const rm = tr.querySelector('.removeRowBtn');
    if (rm) rm.addEventListener('click', ()=>{ tr.remove(); recalc(); });

    body.appendChild(tr); idx++; recalc();
  }

  function addFromStage(){
    const ts = document.getElementById('stage_name').__ts;
    const label = ts ? (ts.getItem(ts.items[0])?.innerText || '') : (document.getElementById('stage_name')?.value || '').trim();
    const itemId = (document.getElementById('stage_item_id')||{}).value || '';

    if (!itemId || !label){ alert('Pilih item terlebih dahulu.'); return; }

    addRow({
      item_id        : itemId,
      item_variant_id: (document.getElementById('stage_item_variant_id')||{}).value || '',
      name           : label, // gunakan label (nama), bukan id
      description    : (document.getElementById('stage_desc')||{}).value || '',
      qty            : toNum((document.getElementById('stage_qty')||{}).value || '1'),
      unit           : (document.getElementById('stage_unit')||{}).value || 'pcs',
      unit_price     : toNum((document.getElementById('stage_price')||{}).value || '0'),
      discount_type  : 'amount',
      discount_value : 0,
    });

    // reset stage
    ['stage_item_id','stage_item_variant_id','stage_desc','stage_qty','stage_unit','stage_price'].forEach(id=>{
      const el=document.getElementById(id); if(!el) return;
      if (id==='stage_qty') el.value='1'; else if (id==='stage_unit') el.value='pcs'; else el.value='';
    });
    if (ts){ ts.clear(); ts.setTextboxValue(''); }
  }

  document.getElementById('stage_add_btn')?.addEventListener('click', addFromStage);
  document.getElementById('stage_clear_btn')?.addEventListener('click', ()=>{
    ['stage_item_id','stage_item_variant_id','stage_desc','stage_qty','stage_unit','stage_price'].forEach(id=>{
      const el=document.getElementById(id); if(!el) return;
      if (id==='stage_qty') el.value='1'; else if (id==='stage_unit') el.value='pcs'; else el.value='';
    });
    const ts=document.getElementById('stage_name').__ts; if(ts){ ts.clear(); ts.setTextboxValue(''); }
  });

  // Delegasi event tabel
  body.addEventListener('input', e=>{
    if (e.target.classList.contains('qty') || e.target.classList.contains('price') || e.target.classList.contains('disc-value')) recalc();
  });
  body.addEventListener('change', e=>{
    if (e.target.classList.contains('disc-type')){
      const unitEl=e.target.closest('tr')?.querySelector('.disc-unit');
      if (unitEl) unitEl.textContent = (e.target.value==='percent') ? '%' : 'IDR';
      recalc();
    }
  });

  // Mode toggle
  function applyMode(mode){
    if (mode==='per_item'){ form.classList.add('mode-per'); form.classList.remove('mode-total'); }
    else { form.classList.add('mode-total'); form.classList.remove('mode-per'); }
    recalc();
  }
  document.getElementById('dm-total')?.addEventListener('change', ()=>applyMode('total'));
  document.getElementById('dm-per')?.addEventListener('change',  ()=>applyMode('per_item'));

  // Fallback aktivasi tab jika Bootstrap JS tidak termuat
  document.querySelectorAll('.nav-tabs [data-bs-toggle="tab"]').forEach(a=>{
    a.addEventListener('click', function(e){
      if (window.bootstrap?.Tab) return; // biar Bootstrap handle kalau ada
      e.preventDefault();
      const target = this.getAttribute('data-bs-target') || this.getAttribute('href');
      const tabs   = this.closest('.nav-tabs');
      const panes  = tabs.nextElementSibling; // .tab-content
      tabs.querySelectorAll('.nav-link').forEach(x=>x.classList.remove('active'));
      panes.querySelectorAll('.tab-pane').forEach(p=>p.classList.remove('show','active'));
      this.classList.add('active');
      const pane = document.querySelector(target);
      if (pane){ pane.classList.add('show','active'); }
    });
  });

  // Draft attachments
  (function(){
    const uploadInput=document.getElementById('soUpload');
    const listEl     =document.getElementById('soFiles');
    const draftToken =(document.getElementById('draft_token')||{}).value || '';
    const csrf       = document.querySelector('meta[name="csrf-token"]')?.content || '';

    function rowFile(file){
      return `<div class="list-group-item d-flex align-items-center gap-2" data-id="${file.id}">
        <a class="me-auto" href="${file.url}" target="_blank" rel="noopener">${file.name}</a>
        <span class="text-secondary small">${Math.round((file.size||0)/1024)} KB</span>
        <button type="button" class="btn btn-sm btn-outline-danger">Hapus</button>
      </div>`;
    }
    async function refreshList(){
      if (!draftToken) { listEl.innerHTML=''; return; }
      try{
        const url = @json(route('sales-orders.attachments.index')) + '?draft_token=' + encodeURIComponent(draftToken);
        const res = await fetch(url,{headers:{'X-Requested-With':'XMLHttpRequest','Accept':'application/json'}});
        const data= await res.json().catch(()=>[]);
        const files= Array.isArray(data)?data:[];
        listEl.innerHTML = files.map(rowFile).join('');
        listEl.querySelectorAll('button').forEach(btn=>{
          btn.addEventListener('click', async e=>{
            const row=e.target.closest('[data-id]'); const id=row?.dataset.id;
            if(!id) return;
            const delUrl=@json(route('sales-orders.attachments.destroy','__ID__')).replace('__ID__', id);
            await fetch(delUrl,{method:'DELETE',headers:{'X-CSRF-TOKEN':csrf,'X-Requested-With':'XMLHttpRequest','Accept':'application/json'}});
            row.remove();
          });
        });
      }catch{ listEl.innerHTML=''; }
    }
    uploadInput?.addEventListener('change', async (e)=>{
      for (const f of e.target.files){
        const fd=new FormData(); fd.append('file',f); fd.append('draft_token',draftToken);
        await fetch(@json(route('sales-orders.attachments.upload')),{method:'POST',headers:{'X-CSRF-TOKEN':csrf,'X-Requested-With':'XMLHttpRequest','Accept':'application/json'},body:fd});
      }
      uploadInput.value=''; refreshList();
    });
    document.getElementById('btnCancelDraft')?.addEventListener('click', async ()=>{
      await fetch(@json(route('sales-orders.create.cancel')),{method:'DELETE',headers:{'X-CSRF-TOKEN':@json(csrf_token()),'Content-Type':'application/json'},body:JSON.stringify({draft_token:draftToken})});
      history.back();
    });
    refreshList();
  })();

  // Preload lines dari quotation
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
  PRELOAD.forEach(addRow);

  // Init
  initStagePicker();
  applyMode(@json($discMode === 'per_item' ? 'per_item' : 'total'));
  recalc();
})();
</script>
@endpush
@endsection
