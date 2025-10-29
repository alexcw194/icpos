{{-- resources/views/quotations/_form.blade.php --}}

{{-- ALERT ERROR --}}
@if ($errors->any())
  <div class="alert alert-danger">
    <div class="fw-bold mb-1">Periksa kembali input Anda:</div>
    <ul class="mb-0">
      @foreach ($errors->all() as $error)
        <li>{{ $error }}</li>
      @endforeach
    </ul>
  </div>
@endif

{{-- ===== HEADER: CUSTOMER / COMPANY / CURRENCY+TAX ===== --}}
<div class="row g-3 mb-3">
  <div class="col-md-6">
    <label class="form-label">Customer <span class="text-danger">*</span></label>
    <select name="customer_id" class="form-select" required>
      <option value="">— pilih customer —</option>
      @foreach($customers as $c)
        <option value="{{ $c->id }}" @selected(old('customer_id', $quotation->customer_id ?? null) == $c->id)>
          {{ $c->name }}
        </option>
      @endforeach
    </select>
  </div>

  {{-- COMPANY di kiri TAX --}}
  <div class="col-md-3">
    <label class="form-label">Company <span class="text-danger">*</span></label>
    <select name="company_id" id="company_id" class="form-select" required
            @if(isset($canChangeCompany) && !$canChangeCompany) disabled @endif>
      @foreach($companies as $co)
        <option
          value="{{ $co->id }}"
          data-taxable="{{ (int)$co->is_taxable }}"
          data-tax="{{ (float)$co->default_tax_percent }}"
          @selected( (old('company_id', $defaultCompanyId ?? null)) == $co->id )
        >
          {{ $co->alias ?? $co->name }} — {{ $co->name }}
        </option>
      @endforeach
    </select>
    @if(isset($canChangeCompany) && !$canChangeCompany)
      <input type="hidden" name="company_id" value="{{ $defaultCompanyId }}">
      <small class="text-muted">Company tidak bisa diubah saat status bukan draft.</small>
    @endif
  </div>

  {{-- CURRENCY + TAX di kanan --}}
  <div class="col-md-3">
    <label class="form-label">Currency</label>
    <div class="d-flex align-items-center gap-2">
      <span class="badge bg-primary">IDR</span>
      <input type="hidden" name="currency" value="IDR">
      <div class="ms-auto" style="min-width:120px">
        <div class="input-group">
          <span class="input-group-text">Tax %</span>
          <input type="text" inputmode="decimal" autocomplete="off"
                 class="form-control text-end" name="tax_percent" id="tax_percent"
                 value="{{ old('tax_percent', $quotation->tax_percent ?? null) }}">
        </div>
      </div>
    </div>
    <small class="form-hint">Tax mengikuti setting company (bisa diubah bila perlu).</small>
  </div>
</div>

{{-- ===== (opsional) blok lain: Tanggal, Valid Until, Terms, Discount Mode, dst — taruh di atas atau bawah sesuai layoutmu ===== --}}

{{-- ===== QUICK SEARCH (otomatis mengisi baris yang aktif) ===== --}}
<div class="mb-2">
  <label class="form-label">Cari & pilih item</label>
  <input id="itemQuickSearch" type="text" placeholder="Ketik nama/SKU...">
  <div class="form-hint">Pilih hasil untuk mengisi baris item yang aktif di bawah.</div>
</div>

{{-- ===== ITEMS TABLE ===== --}}
<div class="mt-2">
  <div class="d-flex align-items-center justify-content-between mb-2">
    <div class="form-label m-0">Items</div>
    <div class="btn-group">
      <button type="button" class="btn btn-primary btn-sm" id="btnOpenItemPicker">
        Add item
      </button>
      <button type="button" class="btn btn-outline-primary btn-sm" id="btnAddLine">
        Tambah Baris
      </button>
    </div>
  </div>

  {{-- PEMBUNGKUS LINES (WAJIB id ini) --}}
  <div id="quotation-lines" class="table-responsive">
    <table class="table table-sm align-middle mb-0">
      <thead>
        <tr>
          <th style="width:38%">Item</th>
          <th style="width:24%">Deskripsi</th>
          <th class="text-end" style="width:10%">Qty</th>
          <th class="text-end" style="width:14%">Rate</th>
          <th style="width:14%">Diskon</th>
          <th class="text-end">Subtotal</th>
          <th class="text-end">Disc</th>
          <th class="text-end">Total</th>
        </tr>
      </thead>
      <tbody id="linesBody">
        @php
          $__lines = old('lines', isset($quotation) ? ($quotation->lines->toArray() ?? []) : []);
          if (empty($__lines)) { $__lines = [ ['name'=>'','description'=>'','qty'=>1,'unit_price'=>0] ]; }
        @endphp

        @foreach($__lines as $i => $line)
          @include('quotations._line_row', ['i'=>$i,'line'=>(object)$line])
        @endforeach
      </tbody>
    </table>
  </div>
</div>

{{-- Template baris untuk tombol "Tambah Baris" --}}
<template id="lineRowTpl">
  @php $i='__INDEX__'; $line=(object)['name'=>'','description'=>'','qty'=>1,'unit_price'=>0]; @endphp
  @include('quotations._line_row', compact('i','line'))
</template>

@push('scripts')
{{-- Sinkron pajak default ketika company berubah (punyamu, tetap dipakai) --}}
<script>
(function(){
  function syncTax() {
    var sel = document.getElementById('company_id');
    if (!sel) return;
    var opt = sel.options[sel.selectedIndex];
    if (!opt) return;
    var taxable = Number(opt.getAttribute('data-taxable')) === 1;
    var tax = parseFloat(opt.getAttribute('data-tax') || '0');
    var taxInput = document.getElementById('tax_percent');
    if (!taxInput) return;

    if (taxable) {
      if (!taxInput.value) taxInput.value = tax.toString().replace('.', ',');
      taxInput.removeAttribute('readonly');
      taxInput.classList.remove('bg-light','text-muted');
    } else {
      taxInput.value = '0';
      taxInput.setAttribute('readonly','readonly');
      taxInput.classList.add('bg-light','text-muted');
    }
  }
  var sel = document.getElementById('company_id');
  if (sel) {
    sel.addEventListener('change', syncTax);
    syncTax();
  }
})();
</script>

{{-- Tambah baris sederhana dari <template> --}}
<script>
document.addEventListener('DOMContentLoaded', function(){
  const btn  = document.getElementById('btnAddLine');
  const body = document.getElementById('linesBody');
  const tpl  = document.getElementById('lineRowTpl');
  if (!btn || !body || !tpl) return;

  btn.addEventListener('click', function(){
    const idx  = body.querySelectorAll('tr.qline').length;
    const html = tpl.innerHTML.replaceAll('__INDEX__', idx);
    body.insertAdjacentHTML('beforeend', html);
  });
});
</script>

{{-- Quick-search (otomatis memuat TomSelect jika belum ada) --}}
@include('quotations._item_picker_modal')
@endpush
