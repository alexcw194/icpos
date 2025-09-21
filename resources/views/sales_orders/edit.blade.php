@extends('layouts.tabler')

@section('content')
@php
  $o = $salesOrder;
  $company    = $o->company;
  $isTaxable  = (bool)($company->is_taxable ?? false);
  $taxDefault = (float)($o->tax_percent ?? $company->default_tax_percent ?? 11);
  $discMode   = $o->discount_mode ?? 'total';
  $lines      = $o->lines ?? collect();
@endphp

<div class="container-xl">
  <form action="{{ route('sales-orders.update', $o) }}" method="POST" id="soEditForm" enctype="multipart/form-data"
        class="mode-{{ $discMode === 'per_item' ? 'per' : 'total' }}">
    @csrf
    @method('PUT')

    <div class="card">
      <div class="card-header">
        <div class="card-title">
          Edit Sales Order <span class="text-muted">{{ $o->so_number }}</span>
          <span class="text-muted"> — {{ $o->company->alias ?? $o->company->name }} · {{ $o->customer->name ?? '-' }}</span>
        </div>
      </div>

      <div class="card-body">
        {{-- Row 1: PO No, PO Date, Deadline --}}
        <div class="row g-3">
          <div class="col-md-4">
            <label class="form-label required">Customer PO No</label>
            <input type="text" name="customer_po_number" class="form-control"
                   value="{{ old('customer_po_number', $o->customer_po_number) }}" required>
          </div>
          <div class="col-md-4">
            <label class="form-label required">Customer PO Date</label>
            <input type="date" name="customer_po_date" class="form-control"
                   value="{{ old('customer_po_date', optional($o->customer_po_date)->format('Y-m-d') ?? $o->customer_po_date) }}" required>
          </div>
          <div class="col-md-4">
            <label class="form-label">Deadline</label>
            <input type="date" name="deadline" class="form-control"
                   value="{{ old('deadline', optional($o->deadline)->format('Y-m-d')) }}">
          </div>
        </div>

        {{-- Row 2: Ship To / Bill To --}}
        <div class="row g-3 mt-2">
          <div class="col-md-6">
            <label class="form-label">Ship To</label>
            <textarea name="ship_to" class="form-control" rows="3">{{ old('ship_to', $o->ship_to) }}</textarea>
          </div>
          <div class="col-md-6">
            <label class="form-label">Bill To</label>
            <textarea name="bill_to" class="form-control" rows="3">{{ old('bill_to', $o->bill_to) }}</textarea>
          </div>
        </div>

        {{-- Row 3: Notes --}}
        <div class="row g-3 mt-2">
          <div class="col-md-12">
            <label class="form-label">Notes</label>
            <textarea name="notes" class="form-control" rows="3">{{ old('notes', $o->notes) }}</textarea>
          </div>
        </div>

        {{-- === Attachments (posisi sebelum Discount Mode, seperti Create SO) === --}}
        <div class="mt-3">
          <label class="form-label">Attachments (PO Customer) — PDF/JPG/PNG</label>
          <input type="file" name="attachments[]" class="form-control"
                 accept=".pdf,.jpg,.jpeg,.png" multiple>

          @if($o->attachments->count())
            <div class="list-group list-group-flush mt-2">
              @foreach($o->attachments as $att)
                <div class="list-group-item d-flex justify-content-between align-items-center">
                  <a href="{{ asset('storage/'.$att->path) }}" target="_blank">
                    {{ $att->original_name ?? basename($att->path) }}
                  </a>
                  @can('deleteAttachment', [$o, $att])
                    {{-- Tombol submit ke hidden form di bawah (hindari nested form) --}}
                    <button type="submit" class="btn btn-sm btn-outline-danger"
                            form="att-del-{{ $att->id }}"
                            onclick="return confirm('Delete this attachment?')">
                      Delete
                    </button>
                  @endcan
                </div>
              @endforeach
            </div>
          @endif
        </div>

        {{-- Discount mode selector --}}
        <div class="d-flex align-items-center gap-3 mt-4">
          <div class="fw-bold">Discount Mode</div>
          <div class="btn-group btn-group-sm" role="group" aria-label="Discount mode">
            <input type="radio" class="btn-check" name="dm" id="dm-total" value="total" {{ $discMode === 'total' ? 'checked' : '' }}>
            <label class="btn btn-outline-primary" for="dm-total">Total</label>

            <input type="radio" class="btn-check" name="dm" id="dm-per" value="per_item" {{ $discMode === 'per_item' ? 'checked' : '' }}>
            <label class="btn btn-outline-primary" for="dm-per">Per Item</label>
          </div>
          <div class="form-hint">Ganti mode akan berpengaruh ke cara hitung.</div>
        </div>
        <input type="hidden" name="discount_mode" id="discount_mode" value="{{ $discMode }}">

        {{-- QUICK ADD --}}
        <div class="card mt-3 mb-2">
          <div class="card-body">
            <label class="form-label">Cari & pilih item</label>
            <div class="row g-2 align-items-center">
              <div class="col-md-4">
                <input id="qs_name" type="text" class="form-control" placeholder="Ketik nama/SKU…" autocomplete="off">
                <input id="qs_item_id" type="hidden">
                <input id="qs_item_variant_id" type="hidden">
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
          </div>
        </div>

        {{-- ORDER LINES --}}
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
                      <input type="hidden" name="lines[{{ $i }}][id]" value="{{ $ln->id }}">
                      <input type="hidden" name="lines[{{ $i }}][item_id]" value="{{ $ln->item_id ?? '' }}">
                      <input type="hidden" name="lines[{{ $i }}][item_variant_id]" value="{{ $ln->item_variant_id ?? '' }}">
                      <input type="text" name="lines[{{ $i }}][name]" class="form-control" value="{{ $ln->name }}">
                    </td>
                    <td><input type="text" name="lines[{{ $i }}][description]" class="form-control" value="{{ $ln->description }}"></td>
                    <td><input type="text" name="lines[{{ $i }}][unit]" class="form-control" value="{{ $ln->unit ?? 'pcs' }}"></td>
                    <td><input type="text" name="lines[{{ $i }}][qty]" class="form-control text-end num qty" value="{{ rtrim(rtrim(number_format((float)$ln->qty_ordered, 2, '.', ''), '0'), '.') }}"></td>
                    <td><input type="text" name="lines[{{ $i }}][unit_price]" class="form-control text-end num price" value="{{ rtrim(rtrim(number_format((float)$ln->unit_price, 2, '.', ''), '0'), '.') }}"></td>
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

        {{-- TOTALS --}}
        <div class="row justify-content-end mt-4">
          <div class="col-md-6">
            <div class="card">
              <div class="card-body">
                <div class="d-flex justify-content-between">
                  <div>Subtotal (setelah diskon per-baris)</div>
                  <div><span id="v_subtotal">0</span></div>
                </div>

                <div class="d-flex align-items-center justify-content-between mt-2 total-only">
                  <div class="d-flex align-items-center">
                    <span class="me-2">Diskon Total</span>
                    <select name="total_discount_type" id="total_discount_type" class="form-select form-select-sm" style="width:auto">
                      <option value="amount" {{ $o->total_discount_type==='amount'?'selected':'' }}>Amount</option>
                      <option value="percent" {{ $o->total_discount_type==='percent'?'selected':'' }}>%</option>
                    </select>
                  </div>
                  <div style="min-width:180px">
                    <input type="text" name="total_discount_value" id="total_discount_value" class="form-control text-end num"
                           value="{{ rtrim(rtrim(number_format((float)($o->total_discount_value ?? 0), 2, '.', ''), '0'), '.') }}">
                  </div>
                </div>
                <div class="d-flex justify-content-between total-only">
                  <div></div>
                  <div>- <span id="v_total_dc">0</span></div>
                </div>

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
              </div>
            </div>
          </div>
        </div>

      </div>

      {{-- FOOTER --}}
      <div class="card-footer d-flex justify-content-end gap-2">
        <a href="{{ route('sales-orders.index') }}" class="btn btn-link">Cancel</a>
        <button type="submit" class="btn btn-primary">Save</button>
      </div>
    </div>
  </form>

  {{-- Hidden delete forms for attachments --}}
  @foreach($o->attachments as $att)
    @can('deleteAttachment', [$o, $att])
      <form id="att-del-{{ $att->id }}" class="d-none"
            action="{{ route('sales-orders.attachments.destroy', [$o, $att]) }}"
            method="POST">
        @csrf @method('DELETE')
      </form>
    @endcan
  @endforeach
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
    <td class="col-per">
      <select name="__NAME__[discount_type]" class="form-select dctype">
        <option value="amount">Amount</option>
        <option value="percent">%</option>
      </select>
    </td>
    <td class="col-per">
      <input type="text" name="__NAME__[discount_value]" class="form-control text-end num dcval" value="0">
    </td>
    <td class="text-end align-middle"><span class="line-total">0</span></td>
    <td class="text-center align-middle">
      <button type="button" class="btn btn-link text-danger px-1 btn-del-line">&times;</button>
    </td>
  </tr>
</template>

@push('styles')
<style>
  .mode-total .col-per,
  .mode-total .qs-per,
  .mode-per  .total-only { display: none !important; }
</style>
@endpush

@push('scripts')
<script>
(function(){
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

  const form = document.getElementById('soEditForm');

  const el = {
    tbody: document.getElementById('linesBody'),
    tpl:   document.getElementById('rowTpl'),
    subtotal: document.getElementById('v_subtotal'),
    totalDc:  document.getElementById('v_total_dc'),
    dpp:      document.getElementById('v_dpp'),
    ppn:      document.getElementById('v_ppn'),
    grand:    document.getElementById('v_grand'),
    tdType: document.getElementById('total_discount_type'),
    tdValue: document.getElementById('total_discount_value'),
    taxPct: document.getElementById('tax_percent'),
    modeInput: document.getElementById('discount_mode'),
    dmTotal: document.getElementById('dm-total'),
    dmPer:   document.getElementById('dm-per'),
  };

  function recalc(){
    let sub = 0, perLineDc = 0;

    document.querySelectorAll('#linesBody tr.line').forEach(tr => {
      const qty   = parseID(tr.querySelector('.qty')?.value);
      const price = parseID(tr.querySelector('.price')?.value);
      const lineSub = qty * price;

      let dcAmt = 0;
      if (mode === 'per_item') {
        const tp  = tr.querySelector('.dctype')?.value || 'amount';
        const val = parseID(tr.querySelector('.dcval')?.value);
        dcAmt = (tp === 'percent') ? (lineSub * (Math.max(0, Math.min(val, 100))/100)) : Math.max(val, 0);
        if (dcAmt > lineSub) dcAmt = lineSub;
      }

      const lineTotal = Math.max(lineSub - dcAmt, 0);
      sub += lineSub; perLineDc += dcAmt;

      tr.querySelector('.line-total').textContent = money(lineTotal);
    });

    let totalDc = 0;
    if (mode === 'total') {
      const ttype = el.tdType?.value || 'amount';
      const tval  = parseID(el.tdValue?.value);
      totalDc = (ttype === 'percent') ? (sub * (Math.max(0, Math.min(tval, 100))/100)) : Math.max(tval, 0);
      if (totalDc > sub) totalDc = sub;
    } else {
      totalDc = perLineDc;
    }

    const dpp = Math.max(sub - totalDc, 0);
    const taxPct = isTaxable ? Math.max(0, Math.min(parseID(el.taxPct?.value), 100)) : 0;
    const ppn = isTaxable ? (dpp * (taxPct/100)) : 0;
    const grand = dpp + ppn;

    el.subtotal.textContent = money(sub);
    (el.totalDc || {}).textContent = money(totalDc);
    el.dpp.textContent = money(dpp);
    el.ppn.textContent = isTaxable ? money(ppn) : '—';
    el.grand.textContent = money(grand);
  }

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

  // Mode switch
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

  // Quick add
  const qs = {
    name : document.getElementById('qs_name'),
    item_id: document.getElementById('qs_item_id'),
    item_variant_id: document.getElementById('qs_item_variant_id'),
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
    if (qs.name) qs.name.value = '';
    if (qs.item_id) qs.item_id.value = '';
    if (qs.item_variant_id) qs.item_variant_id.value = '';
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

    // diskon
    const dcTypeEl = tr.querySelector('.dctype');
    const dcValEl  = tr.querySelector('.dcval');
    if (dcTypeEl && qs.dctype) dcTypeEl.value = qs.dctype.value;
    if (dcValEl  && qs.dcval)  dcValEl.value  = qs.dcval.value;

    bindRow(tr);
    recalc();
    qsClear();
  }
)();
</script>
@endpush
@endsection
