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
        </div>
        <input type="hidden" name="discount_mode" id="discount_mode" value="{{ $discMode }}">

        {{-- ===== Items table (dari quotation) ===== --}}
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

        {{-- ===== Totals ===== --}}
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
                    <div>&mdash;</div>
                  </div>
                @endif

                <hr>
                <div class="d-flex justify-content-between fw-bold">
                  <div>Grand Total</div>
                  <div><span id="v_grand">0</span></div>
                </div>

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

      <div class="card-footer d-flex justify-content-end gap-2">
        <button type="button" id="btnCancelDraft" class="btn btn-link text-danger">Cancel</button>
        <button type="submit" class="btn btn-primary">Create SO</button>
      </div>
    </div>
  </form>
</div>

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
  .ts-dropdown{ z-index: 1060; }
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
  const clamp = (n, min, max) => Math.min(Math.max(Number(n||0), min), max);

  // ===== Upload draft attachments =====
  const uploadInput = document.getElementById('soUpload');
  const listEl      = document.getElementById('soFiles');
  const draftToken  = document.getElementById('draft_token')?.value || '';
  const csrf        = document.querySelector('meta[name="csrf-token"]')?.content || '';

  function rowTpl(file){
    return `
      <div class="list-group-item d-flex align-items-center gap-2" data-id="${file.id}">
        <a class="me-auto" href="${file.url}" target="_blank" rel="noopener">${file.name}</a>
        <span class="text-secondary small">${Math.round((file.size||0)/1024)} KB</span>
        <button type="button" class="btn btn-sm btn-outline-danger">Hapus</button>
      </div>
    `;
  }

  // *** Hardened JSON loader: parse dari text, bersihkan BOM/single-quote ***
  async function refreshList(){
    if (!draftToken) { listEl.innerHTML = ''; return; }
    try {
      const url = @json(route('sales-orders.attachments.index')) + '?draft_token=' + encodeURIComponent(draftToken);
      const res = await fetch(url, {
        headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
        credentials: 'same-origin'
      });

      let text = await res.text();
      text = text.replace(/^\uFEFF/, '').trim();        // hapus BOM
      if (text.startsWith("'") && text.endsWith("'")) { // jika server mengembalikan `'[...]'`
        text = text.slice(1, -1);
      }

      let data;
      try { data = JSON.parse(text); }
      catch(e){ console.warn('attachments.index bukan JSON:', e, text); listEl.innerHTML=''; return; }

      const files = Array.isArray(data) ? data : [];
      listEl.innerHTML = files.map(rowTpl).join('');

      // bind tombol hapus
      listEl.querySelectorAll('button').forEach(btn => {
        btn.addEventListener('click', async (e) => {
          const row = e.target.closest('[data-id]'); const id = row.dataset.id;
          const delUrl = @json(route('sales-orders.attachments.destroy','__ID__')).replace('__ID__', id);
          await fetch(delUrl, {
            method: 'DELETE',
            headers: {
              'X-CSRF-TOKEN': csrf,
              'X-Requested-With': 'XMLHttpRequest',
              'Accept': 'application/json'
            },
            credentials: 'same-origin'
          });
          row.remove();
        });
      });
    } catch (err) {
      console.warn('attachments.index gagal:', err);
      listEl.innerHTML = '';
    }
  }

  uploadInput?.addEventListener('change', async (e) => {
    for (const f of e.target.files) {
      const fd = new FormData();
      fd.append('file', f);
      fd.append('draft_token', draftToken);

      const res = await fetch(@json(route('sales-orders.attachments.upload')), {
        method: 'POST',
        headers: {
          'X-CSRF-TOKEN': csrf,
          'X-Requested-With': 'XMLHttpRequest',
          'Accept': 'application/json'
        },
        body: fd,
        credentials: 'same-origin'
      });

      if (!res.ok) console.error('upload gagal', await res.text());
    }
    uploadInput.value = '';
    await refreshList();
  });

  // ===== Kalkulasi totals (tidak diubah) =====
  let mode = document.getElementById('discount_mode')?.value || 'total';
  const isTaxable = {{ $isTaxable ? 'true' : 'false' }};
  const form = document.getElementById('soForm');

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

  function recalc(){
    if (!el.subtotal || !el.tbody) return;

    const setText = (node, text) => { if (node) node.textContent = text; };
    const setVal  = (node, val)  => { if (node) node.value = val; };

    let sub = 0, perLineDc = 0;

    document.querySelectorAll('#linesBody tr.line').forEach(tr => {
      const qty     = parseID(tr.querySelector('.qty')?.value);
      const price   = parseID(tr.querySelector('.price')?.value);
      const lineSub = qty * price;

      let dcAmt = 0;
      if (mode === 'per_item') {
        const tp = tr.querySelector('.dctype')?.value || 'amount';
        let val  = parseID(tr.querySelector('.dcval')?.value);
        if (tp === 'percent') { val = clamp(val, 0, 100); dcAmt = lineSub * (val/100); }
        else { val = Math.max(val, 0); dcAmt = val; }
        if (dcAmt > lineSub) dcAmt = lineSub;
      }

      const lineTotal = Math.max(lineSub - dcAmt, 0);
      sub       += lineSub;
      perLineDc += dcAmt;

      setText(tr.querySelector('.line-total'), money(lineTotal));
      setVal (tr.querySelector('.line_total_input'), lineTotal.toFixed(2));
      setVal (tr.querySelector('.line_dcamt_input'), dcAmt.toFixed(2));
      setVal (tr.querySelector('.line_sub_input'),  lineSub.toFixed(2));
    });

    let totalDc = 0;
    if (mode === 'total') {
      const ttype = el.tdType?.value || 'amount';
      let   tval  = parseID(el.tdValue?.value);
      if (ttype === 'percent') { tval = clamp(tval, 0, 100); totalDc = sub * (tval/100); }
      else { tval = Math.max(tval, 0); totalDc = tval; }
      if (totalDc > sub) totalDc = sub;
    } else {
      totalDc = perLineDc;
    }

    const dpp    = Math.max(sub - totalDc, 0);
    const taxPct = isTaxable ? parseID(el.taxPct?.value) : 0;
    const ppn    = isTaxable ? (dpp * (taxPct/100)) : 0;
    const grand  = dpp + ppn;

    setText(el.subtotal, money(sub));
    setText(el.totalDc,  money(totalDc));
    setText(el.dpp,      money(dpp));
    setText(el.ppn,      isTaxable ? money(ppn) : '-');
    setText(el.grand,    money(grand));

    setVal(el.i_subtotal, sub.toFixed(2));
    setVal(el.i_total_dc, totalDc.toFixed(2));
    setVal(el.i_dpp,      dpp.toFixed(2));
    setVal(el.i_ppn,      ppn.toFixed(2));
    setVal(el.i_grand,    grand.toFixed(2));
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

  // Cancel draft: panggil route purge
  document.getElementById('btnCancelDraft')?.addEventListener('click', async () => {
    const token = draftToken;
    await fetch(@json(route('sales-orders.create.cancel')), {
      method: 'DELETE',
      headers: { 'X-CSRF-TOKEN': @json(csrf_token()), 'Content-Type': 'application/json' },
      body: JSON.stringify({ draft_token: token }),
      credentials: 'same-origin'
    });
    window.history.back();
  });

  // Mode switch
  function applyMode(newMode){
    mode = newMode;
    const form = document.getElementById('soForm');
    const hidden = document.getElementById('discount_mode');
    if (hidden) hidden.value = mode;
    if (form){
      form.classList.remove('mode-total','mode-per');
      form.classList.add(mode === 'per_item' ? 'mode-per' : 'mode-total');
    }
    recalc();
  }
  document.getElementById('dm-total')?.addEventListener('change', () => applyMode('total'));
  document.getElementById('dm-per')?.addEventListener('change',   () => applyMode('per_item'));

  // Init
  refreshList();
  recalc();
})();
</script>
@endpush
@endsection
