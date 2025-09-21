{{-- resources/views/sales_orders/create_from_quotation.blade.php --}}
@extends('layouts.tabler')

@section('content')
@php
  $company     = $quotation->company;
  $isTaxable   = (bool) ($company->is_taxable ?? false);   // ICP = true, AMP = false
  $taxDefault  = (float) ($quotation->tax_percent ?? $company->default_tax_percent ?? 11);
  $discMode    = $quotation->discount_mode ?? 'total';     // 'per_item' | 'total'
  $lines       = $quotation->lines ?? collect();
  $validUntil  = optional($quotation->valid_until)->format('Y-m-d') ?? now()->addDays(30)->format('Y-m-d');
@endphp

<div class="container-xl">
  <form action="{{ route('sales-orders.store-from-quotation', $quotation) }}"
        method="POST" enctype="multipart/form-data" id="soForm"
        class="mode-{{ $discMode === 'per_item' ? 'per' : 'total' }}">
    @csrf

    <div class="card">
      <div class="card-header">
        <div class="card-title">
          Create Sales Order from Quotation
          <span class="text-muted">
            — {{ $quotation->number }} · {{ $quotation->customer->name ?? '-' }}
          </span>
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

        {{-- ===== Attachments ===== --}}
        <div class="mt-3">
          <label class="form-label">Attachments (PO Customer) — PDF/JPG/PNG</label>
          <input type="file" name="attachments[]" class="form-control" accept=".pdf,.jpg,.jpeg,.png" multiple>
        </div>

        {{-- ===== NPWP Panel (ICP only) ===== --}}
        @if($npwpRequired ?? false)
          <div class="card mt-3 is-sticky">
            <div class="card-body">
              <div class="d-flex justify-content-between align-items-start">
                <div>
                  <div class="fw-bold">Data NPWP Pelanggan (wajib untuk ICP)</div>
                  <div class="small text-muted">
                    SO boleh dibuat walau belum lengkap; nanti saat <strong>Invoice</strong> akan dikunci jika NPWP belum terisi.
                  </div>
                </div>
              </div>

              <div class="row g-3 mt-2">
                <div class="col-md-4">
                  <label class="form-label">No. NPWP</label>
                  <input type="text" name="npwp_number"
                        class="form-control @error('npwp_number') is-invalid @enderror"
                        value="{{ old('npwp_number', $npwp['number'] ?? '') }}">
                  <div class="form-hint">
                    Terima 15/16 digit. Boleh titik/strip, sistem akan menormalisasi.
                  </div>
                  @error('npwp_number') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>

                <div class="col-md-4">
                  <label class="form-label">Nama NPWP</label>
                  <input type="text" name="npwp_name"
                        class="form-control @error('npwp_name') is-invalid @enderror"
                        value="{{ old('npwp_name', $npwp['name'] ?? '') }}">
                  @error('npwp_name') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>

                <div class="col-md-4">
                  <label class="form-label">Alamat NPWP</label>
                  <textarea name="npwp_address" rows="2"
                            class="form-control @error('npwp_address') is-invalid @enderror">{{ old('npwp_address', $npwp['address'] ?? '') }}</textarea>
                  @error('npwp_address') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>
              </div>

              <label class="form-check mt-2">
                <input class="form-check-input" type="checkbox" name="npwp_save_to_customer" value="1"
                      {{ old('npwp_save_to_customer', true) ? 'checked' : '' }}>
                <span class="form-check-label">Simpan juga ke master Customer</span>
              </label>
            </div>
          </div>
        @endif

        {{-- ===== Discount mode selector (NEW) ===== --}}
        <div class="d-flex align-items-center gap-3 mt-4">
          <div class="fw-bold">Discount Mode</div>
          <div class="btn-group btn-group-sm" role="group" aria-label="Discount mode">
            <input type="radio" class="btn-check" name="dm" id="dm-total" value="total" {{ $discMode === 'total' ? 'checked' : '' }}>
            <label class="btn btn-outline-primary" for="dm-total">Total</label>

            <input type="radio" class="btn-check" name="dm" id="dm-per" value="per_item" {{ $discMode === 'per_item' ? 'checked' : '' }}>
            <label class="btn btn-outline-primary" for="dm-per">Per Item</label>
          </div>
          <div class="form-hint">Ganti mode akan berpengaruh ke cara hitung. Data yang disembunyikan tidak dipakai dalam total.</div>
        </div>
        <input type="hidden" name="discount_mode" id="discount_mode" value="{{ $discMode }}">

        {{-- ===== QUICK ADD (paritas Quotation) ===== --}}
        <div class="card mt-3 mb-2">
          <div class="card-body">
            <label class="form-label">Cari & pilih item</label>
            <div class="row g-2 align-items-center">
              <div class="col-md-4">
                <input id="qs_name" type="text" class="form-control" placeholder="Ketik nama/SKU…" autocomplete="off">
              </div>
              <div class="col-md-3">
                <input id="qs_desc" type="text" class="form-control" placeholder="Deskripsi (opsional)">
              </div>
              <div class="col-auto" style="width:100px">
                <input id="qs_qty" type="text" class="form-control text-end" value="1" aria-label="Qty">
              </div>
              <div class="col-auto" style="width:100px">
                <input id="qs_unit" type="text" class="form-control" value="pcs" aria-label="Unit">
              </div>
              <div class="col-auto" style="width:140px">
                <input id="qs_price" type="text" class="form-control text-end" value="0" aria-label="Unit Price">
              </div>

              {{-- disc per-item controls: show/hide by CSS --}}
              <div class="col-auto qs-per" style="width:120px">
                <select id="qs_dctype" class="form-select">
                  <option value="amount">Amount</option>
                  <option value="percent">%</option>
                </select>
              </div>
              <div class="col-auto qs-per" style="width:120px">
                <input id="qs_dcval" type="text" class="form-control text-end" value="0" aria-label="Disc Value">
              </div>

              <div class="col-auto">
                <button type="button" class="btn btn-primary" id="qs_add">Tambah</button>
              </div>
              <div class="col-auto">
                <button type="button" class="btn btn-link" id="qs_clear">Kosongkan</button>
              </div>
            </div>
            <div class="form-hint">Pilih hasil pada kotak pertama, nilai akan terisi; klik <strong>Tambah</strong> untuk masuk ke list items.</div>
          </div>
        </div>

        {{-- ===== ORDER LINES (paritas Quotation) ===== --}}
        <div class="mt-2">
          <div class="fw-bold mb-2">Items</div>

          <div class="table-responsive">
            <table class="table">
              <thead>
                <tr>
                  <th style="width:26%">Item</th>
                  <th style="width:26%">Deskripsi</th>
                  <th style="width:8%">Unit</th>
                  <th class="text-end" style="width:8%">Qty</th>
                  <th class="text-end" style="width:12%">Unit Price</th>

                  <th class="col-per" style="width:12%">Disc Type</th>
                  <th class="text-end col-per" style="width:12%">Disc Value</th>

                  <th class="text-end" style="width:12%">Line Total</th>
                  <th style="width:4%"></th>
                </tr>
              </thead>
              <tbody id="linesBody">
                @foreach($lines as $i => $ln)
                  @php
                    $dt = $ln->discount_type ?? 'amount';
                    $dv = (float)($ln->discount_value ?? 0);
                  @endphp
                  <tr class="line">
                    <td>
                      <input type="hidden" name="lines[{{ $i }}][item_id]" value="{{ $ln->item_id ?? '' }}">
                      <input type="hidden" name="lines[{{ $i }}][item_variant_id]" value="{{ $ln->item_variant_id ?? '' }}">
                      <input type="text" name="lines[{{ $i }}][name]" class="form-control" value="{{ $ln->name }}">
                    </td>
                    <td><input type="text" name="lines[{{ $i }}][description]" class="form-control" value="{{ $ln->description }}"></td>
                    <td><input type="text" name="lines[{{ $i }}][unit]" class="form-control" value="{{ $ln->unit ?? 'pcs' }}"></td>
                    <td><input type="text" name="lines[{{ $i }}][qty]" class="form-control text-end num qty" value="{{ rtrim(rtrim(number_format((float)$ln->qty, 2, '.', ''), '0'), '.') }}"></td>
                    <td><input type="text" name="lines[{{ $i }}][unit_price]" class="form-control text-end num price" value="{{ rtrim(rtrim(number_format((float)$ln->unit_price, 2, '.', ''), '0'), '.') }}"></td>

                    {{-- selalu render; sembunyikan via CSS saat mode total --}}
                    <td class="col-per">
                      <select name="lines[{{ $i }}][discount_type]" class="form-select dctype">
                        <option value="amount" {{ $dt==='amount'?'selected':'' }}>Amount</option>
                        <option value="percent" {{ $dt==='percent'?'selected':'' }}>%</option>
                      </select>
                    </td>
                    <td class="col-per">
                      <input type="text" name="lines[{{ $i }}][discount_value]" class="form-control text-end num dcval" value="{{ rtrim(rtrim(number_format($dv, 2, '.', ''), '0'), '.') }}">
                    </td>

                    <td class="text-end align-middle">
                      <span class="line-total">0</span>
                      <input type="hidden" name="lines[{{ $i }}][line_total]" class="line_total_input" value="0">
                      <input type="hidden" name="lines[{{ $i }}][discount_amount]" class="line_dcamt_input" value="0">
                      <input type="hidden" name="lines[{{ $i }}][line_subtotal]" class="line_sub_input" value="0">
                    </td>
                    <td class="text-center align-middle">
                      <button type="button" class="btn btn-link text-danger px-1 btn-del-line">&times;</button>
                    </td>
                  </tr>
                @endforeach
              </tbody>
            </table>
          </div>
        </div>

        {{-- ===== TOTALS (paritas Quotation) ===== --}}
        <div class="row justify-content-end mt-4">
          <div class="col-md-6">
            <div class="card">
              <div class="card-body">
                <div class="d-flex justify-content-between">
                  <div>Subtotal (setelah diskon per-baris)</div>
                  <div><span id="v_subtotal">0</span></div>
                </div>

                {{-- total discount controls: tampil hanya di mode total (CSS) --}}
                <div class="d-flex align-items-center justify-content-between mt-2 total-only">
                  <div class="d-flex align-items-center">
                    <span class="me-2">Diskon Total</span>
                    @php $tdt = $quotation->total_discount_type ?? 'amount'; @endphp
                    <select name="total_discount_type" id="total_discount_type" class="form-select form-select-sm" style="width:auto">
                      <option value="amount" {{ $tdt==='amount'?'selected':'' }}>Amount</option>
                      <option value="percent" {{ $tdt==='percent'?'selected':'' }}>%</option>
                    </select>
                  </div>
                  <div style="min-width:180px">
                    <input type="text" name="total_discount_value" id="total_discount_value" class="form-control text-end num"
                           value="{{ rtrim(rtrim(number_format((float)($quotation->total_discount_value ?? 0), 2, '.', ''), '0'), '.') }}">
                  </div>
                </div>
                <div class="d-flex justify-content-between total-only">
                  <div></div>
                  <div>- <span id="v_total_dc">0</span></div>
                </div>

                {{-- hidden when per_item --}}
                <input type="hidden" class="per-only" name="total_discount_type" value="amount">
                <input type="hidden" class="per-only" name="total_discount_value" value="0">

                <div class="d-flex justify-content-between">
                  <div>Dasar Pajak</div>
                  <div><span id="v_dpp">0</span></div>
                </div>

                @if($isTaxable)
                  <div class="d-flex align-items-center justify-content-between">
                    <div class="d-flex align-items-center">
                      <span class="me-2">PPN</span>
                      <input type="text" class="form-control form-control-sm text-end num" id="tax_percent"
                             name="tax_percent" value="{{ rtrim(rtrim(number_format($taxDefault, 2, '.', ''), '0'), '.') }}" style="width:80px">
                      <span class="ms-1">%</span>
                    </div>
                    <div><span id="v_ppn">0</span></div>
                  </div>
                @else
                  <input type="hidden" name="tax_percent" id="tax_percent" value="0">
                  <div class="d-flex justify-content-between text-muted">
                    <div>PPN</div>
                    <div>—</div>
                  </div>
                @endif

                <hr>
                <div class="d-flex justify-content-between fw-bold">
                  <div>Grand Total</div>
                  <div><span id="v_grand">0</span></div>
                </div>

                {{-- Hidden numeric fields --}}
                <input type="hidden" name="lines_subtotal" id="i_subtotal" value="0">
                <input type="hidden" name="total_discount_amount" id="i_total_dc" value="0">
                <input type="hidden" name="taxable_base" id="i_dpp" value="0">
                <input type="hidden" name="tax_amount" id="i_ppn" value="0">
                <input type="hidden" name="total" id="i_grand" value="0">
              </div>
            </div>
          </div>
        </div>
      </div>

      {{-- FOOTER --}}
      <div class="card-footer d-flex justify-content-end gap-2">
        <a href="{{ url()->previous() }}" class="btn btn-link">Cancel</a>
        <button type="submit" class="btn btn-primary">Create SO</button>
      </div>
    </div>
  </form>
</div>

{{-- ======= Row template ======= --}}
<template id="rowTpl">
  <tr class="line">
    <td>
      <input type="hidden" name="__NAME__[item_id]" class="item_id_input" value="">
      <input type="hidden" name="__NAME__[item_variant_id]" class="item_variant_id_input" value="">
      <input type="text" name="__NAME__[name]" class="form-control">
    </td>
    <td><input type="text" name="__NAME__[description]" class="form-control"></td>
    <td><input type="text" name="__NAME__[unit]" class="form-control" value="pcs"></td>
    <td><input type="text" name="__NAME__[qty]" class="form-control text-end num qty" value="1"></td>
    <td><input type="text" name="__NAME__[unit_price]" class="form-control text-end num price" value="0"></td>

    {{-- always render; hide by CSS in total mode --}}
    <td class="col-per">
      <select name="__NAME__[discount_type]" class="form-select dctype">
        <option value="amount">Amount</option>
        <option value="percent">%</option>
      </select>
    </td>
    <td class="col-per">
      <input type="text" name="__NAME__[discount_value]" class="form-control text-end num dcval" value="0">
    </td>

    <td class="text-end align-middle">
      <span class="line-total">0</span>
      <input type="hidden" name="__NAME__[line_total]" class="line_total_input" value="0">
      <input type="hidden" name="__NAME__[discount_amount]" class="line_dcamt_input" value="0">
      <input type="hidden" name="__NAME__[line_subtotal]" class="line_sub_input" value="0">
    </td>
    <td class="text-center align-middle">
      <button type="button" class="btn btn-link text-danger px-1 btn-del-line">&times;</button>
    </td>
  </tr>
</template>

@push('styles')
<style>
  /* dropdown di atas modal/komponen lain */
  .ts-dropdown{ z-index: 1060; }

  /* mode switching */
  .mode-total .col-per,
  .mode-total .qs-per,
  .mode-per  .total-only { display: none !important; }
</style>
@endpush

@push('scripts')
<script>
(function(){
  // ===== Locale helpers (ID style) =====
  const fmt = n => {
    const s = (isFinite(n) ? Number(n) : 0).toFixed(2);
    const [i,d] = s.split('.');
    return i.replace(/\B(?=(\d{3})+(?!\d))/g,'.') + ',' + d;
  };
  const parseID = s => {
    if (s == null) return 0;
    s = (''+s).replace(/\./g,'').replace(',', '.').replace(/[^\d\.\-]/g,'').trim();
    const x = parseFloat(s);
    return isFinite(x) ? x : 0;
  };
  const money = n => 'Rp ' + fmt(n);

  let mode = document.getElementById('discount_mode')?.value || 'total';
  const isTaxable = {{ $isTaxable ? 'true' : 'false' }};

  const form = document.getElementById('soForm');

  const clamp = (n, min, max) => Math.min(Math.max(Number(n||0), min), max);

  const el = {
    tbody: document.getElementById('linesBody'),
    tpl:   document.getElementById('rowTpl'),
    subtotal: document.getElementById('v_subtotal'),
    totalDc:  document.getElementById('v_total_dc'),
    dpp:      document.getElementById('v_dpp'),
    ppn:      document.getElementById('v_ppn'),
    grand:    document.getElementById('v_grand'),
    i_subtotal: document.getElementById('i_subtotal'),
    i_total_dc: document.getElementById('i_total_dc'),
    i_dpp:      document.getElementById('i_dpp'),
    i_ppn:      document.getElementById('i_ppn'),
    i_grand:    document.getElementById('i_grand'),
    tdType: document.getElementById('total_discount_type'),
    tdValue: document.getElementById('total_discount_value'),
    taxPct: document.getElementById('tax_percent'),
    modeInput: document.getElementById('discount_mode'),
    dmTotal: document.getElementById('dm-total'),
    dmPer:   document.getElementById('dm-per'),
  };

  // ===== Recalc all =====
  function recalc(){
    let sub = 0, perLineDc = 0;

    document.querySelectorAll('#linesBody tr.line').forEach(tr => {
      const qty   = parseID(tr.querySelector('.qty')?.value);
      const price = parseID(tr.querySelector('.price')?.value);
      const lineSub = qty * price;

      let dcAmt = 0;
      if (mode === 'per_item') {
        const tp  = tr.querySelector('.dctype')?.value || 'amount';
        let  val = parseID(tr.querySelector('.dcval')?.value);

        if (tp === 'percent') {
          val   = clamp(val, 0, 100);
          dcAmt = lineSub * (val/100);
        } else {
          val   = Math.max(val, 0);
          dcAmt = val;
        }
        if (dcAmt > lineSub) dcAmt = lineSub;
      }

      const lineTotal = Math.max(lineSub - dcAmt, 0);

      sub += lineSub;
      perLineDc += dcAmt;

      tr.querySelector('.line-total').textContent = money(lineTotal);
      tr.querySelector('.line_total_input').value = lineTotal.toFixed(2);
      tr.querySelector('.line_dcamt_input').value = dcAmt.toFixed(2);
      tr.querySelector('.line_sub_input').value = lineSub.toFixed(2);
    });

    let totalDc = 0;
    if (mode === 'total') {
      const ttype = el.tdType?.value || 'amount';
      let   tval  = parseID(el.tdValue?.value);

      if (ttype === 'percent') {
        tval    = clamp(tval, 0, 100);
        totalDc = sub * (tval/100);
      } else {
        tval    = Math.max(tval, 0);
        totalDc = tval;
      }
      if (totalDc > sub) totalDc = sub;
    } else {
      totalDc = perLineDc;
    }

    const dpp = Math.max(sub - totalDc, 0);
    const taxPct = isTaxable ? parseID(el.taxPct?.value) : 0;
    const ppn = isTaxable ? (dpp * (taxPct/100)) : 0;
    const grand = dpp + ppn;

    el.subtotal.textContent = money(sub);
    (el.totalDc || {}).textContent = money(totalDc);
    el.dpp.textContent = money(dpp);
    el.ppn.textContent = isTaxable ? money(ppn) : '—';
    el.grand.textContent = money(grand);

    el.i_subtotal.value = sub.toFixed(2);
    el.i_total_dc.value = totalDc.toFixed(2);
    el.i_dpp.value = dpp.toFixed(2);
    el.i_ppn.value = ppn.toFixed(2);
    el.i_grand.value = grand.toFixed(2);
  }

  // ===== Row helpers =====
  function bindRow(tr){
    tr.querySelectorAll('.num, .dctype').forEach(inp => {
      inp.addEventListener('input', recalc);
      inp.addEventListener('change', recalc);
    });
    const del = tr.querySelector('.btn-del-line');
    if (del) del.addEventListener('click', () => { tr.remove(); recalc(); });
  }

  document.querySelectorAll('#linesBody tr.line').forEach(bindRow);
  ['total_discount_type','total_discount_value','tax_percent'].forEach(id=>{
    const o = document.getElementById(id);
    if (o) { o.addEventListener('input', recalc); o.addEventListener('change', recalc); }
  });

  // ===== Quick add (TomSelect search) =====
  function ensureTomSelect(cb){
    if (window.TomSelect) return cb();
    const link = document.createElement('link');
    link.rel = 'stylesheet';
    link.href = 'https://cdn.jsdelivr.net/npm/tom-select@2.3.1/dist/css/tom-select.css';
    document.head.appendChild(link);
    const s = document.createElement('script');
    s.src = 'https://cdn.jsdelivr.net/npm/tom-select@2.3.1/dist/js/tom-select.complete.min.js';
    s.onload = cb; document.head.appendChild(s);
  }

  const qs = {
    name : document.getElementById('qs_name'),
    desc : document.getElementById('qs_desc'),
    qty  : document.getElementById('qs_qty'),
    unit : document.getElementById('qs_unit'),
    price: document.getElementById('qs_price'),
    dctype: document.getElementById('qs_dctype'),
    dcval : document.getElementById('qs_dcval'),
    add  : document.getElementById('qs_add'),
    clear: document.getElementById('qs_clear'),
  };

  function qsClear(){
    qs.name?.tomselect?.clear(true);
    if (qs.name) qs.name.value = '';
    if (qs.desc) qs.desc.value = '';
    if (qs.qty)  qs.qty.value  = '1';
    if (qs.unit) qs.unit.value = 'pcs';
    if (qs.price)qs.price.value= '0';
    if (qs.dcval)qs.dcval.value= '0';
    if (qs.dctype) qs.dctype.value = 'amount';
    qs.name?.focus();
  }

  function addFromQuick(){
    const ts  = qs.name?.tomselect;
    const val = ts?.getValue();
    const data = val ? ts.options[val] : null;

    const name = (qs.name?.value || '').trim();
    if (!name) { qs.name?.focus(); return; }

    const tbody = el.tbody;
    let idx = tbody.querySelectorAll('tr.line').length;
    const html = el.tpl.innerHTML.replace(/__NAME__/g, 'lines['+idx+']');
    const tmp = document.createElement('tbody'); tmp.innerHTML = html.trim();
    const tr = tmp.firstElementChild; tbody.appendChild(tr);

    // isi field tampil
    tr.querySelector('input[name$="[name]"]').value = name;
    tr.querySelector('input[name$="[description]"]').value = qs.desc?.value || (data?.description || '');
    tr.querySelector('input[name$="[unit]"]').value = qs.unit?.value || (data?.unit_code || 'pcs');
    tr.querySelector('.qty').value   = qs.qty?.value  || '1';
    tr.querySelector('.price').value = (qs.price?.value ?? (data?.price ?? 0));

    // isi hidden relasi item/varian
    const hidItem  = tr.querySelector('.item_id_input');
    const hidVar   = tr.querySelector('.item_variant_id_input');
    if (hidItem) hidItem.value = data?.item_id || '';
    if (hidVar)  hidVar.value  = data?.variant_id || '';

    bindRow(tr);
    recalc();
    qsClear();
  }


  if (qs.add)   qs.add.addEventListener('click', addFromQuick);
  if (qs.clear) qs.clear.addEventListener('click', qsClear);
  if (qs.name)  qs.name.addEventListener('keydown', e => { if (e.key==='Enter'){ e.preventDefault(); addFromQuick(); } });

  ensureTomSelect(() => {
    if (!qs.name) return;
    new TomSelect(qs.name, {
      valueField : 'name',
      labelField : 'label',
      searchField: ['name','sku'],
      maxOptions : 30,
      maxItems   : 1,
      preload    : 'focus',
      closeAfterSelect: true,
      selectOnTab: true,
      create     : false,
      persist    : false,
      load: (query, cb)=>{
        const url = `{{ route('items.search') }}?q=${encodeURIComponent(query||'')}`;
        fetch(url, {headers:{'X-Requested-With':'XMLHttpRequest'}})
          .then(r => r.json()).then(cb).catch(()=>cb());
      },
      render: {
        option: (data, esc)=>{
          const sku = data.sku ? `<small class="text-muted ms-2">${esc(data.sku)}</small>` : '';
          return `<div>${esc(data.label || data.name)} ${sku}</div>`;
        }
      },
      onChange: function(val){
        const data = this.options[val];
        if (!data) return;
        if (qs.desc && !qs.desc.value) qs.desc.value = data.description || '';
        if (qs.unit)  qs.unit.value  = (data.unit_code || 'PCS');
        if (qs.price) qs.price.value = (data.price ?? 0);
        if (qs.qty && (!qs.qty.value || +qs.qty.value === 0)) qs.qty.value = '1';
        this.setTextboxValue(''); this.close();
        recalc(); // <= tambahkan
      },

      onBlur: function(){ this.setTextboxValue(''); }
    });
  });

  document.addEventListener('input', function(e){
    if(e.target.name==='npwp_number'){
      const digits = (e.target.value||'').replace(/\D+/g,'');
      e.target.setCustomValidity((digits.length===0 || digits.length===15 || digits.length===16) ? '' : 'NPWP harus 15 atau 16 digit');
    }
  }, {passive:true});

  document.addEventListener('blur', function(e){
  const id = e.target?.id;

  if (id === 'total_discount_value') {
    const tp = el.tdType?.value || 'amount';
    let v = parseID(e.target.value);
    e.target.value = (tp==='percent') ? clamp(v,0,100) : Math.max(v,0);
    recalc(); // <= penting
  }

  if (e.target.classList?.contains('dcval')) {
    const tr = e.target.closest('tr');
    const tp = tr?.querySelector('.dctype')?.value || 'amount';
    let v = parseID(e.target.value);
    e.target.value = (tp==='percent') ? clamp(v,0,100) : Math.max(v,0);
    recalc(); // <= penting
  }
}, true);


  // ===== Mode switch handlers (NEW) =====
  function applyMode(newMode){
    mode = newMode;
    if (el.modeInput) el.modeInput.value = mode;
    if (form){
      form.classList.remove('mode-total','mode-per');
      form.classList.add(mode === 'per_item' ? 'mode-per' : 'mode-total');
    }
    recalc();
  }

  if (el.dmTotal) el.dmTotal.addEventListener('change', () => applyMode('total'));
  if (el.dmPer)   el.dmPer.addEventListener('change',   () => applyMode('per_item'));

  // initial calc
  recalc();
})();
</script>
@endpush
@endsection
