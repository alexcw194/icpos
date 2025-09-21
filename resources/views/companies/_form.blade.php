@php($co = $company ?? null)

<div class="row g-3">
  <div class="col-md-6">
    <label class="form-label">Nama <span class="text-danger">*</span></label>
    <input class="form-control" name="name" value="{{ old('name', $co->name ?? '') }}" required>
  </div>

  <div class="col-md-3">
    <label class="form-label">Alias</label>
    <input class="form-control" name="alias" value="{{ old('alias', $co->alias ?? '') }}" placeholder="ICP / AMP">
  </div>

  <div class="col-md-3">
    <label class="form-label d-block">Taxable</label>
    <label class="form-check form-switch">
      <input id="isTaxable" class="form-check-input" type="checkbox" name="is_taxable" value="1"
             @checked(old('is_taxable', $co->is_taxable ?? false))>
      <span class="form-check-label">Pakai pajak</span>
    </label>
  </div>

  <div class="col-md-3">
    <label class="form-label">Default Tax %</label>
    <input id="defaultTax" class="form-control text-end" name="default_tax_percent" inputmode="decimal"
           value="{{ old('default_tax_percent', $co->default_tax_percent ?? 0) }}">
    <small class="form-hint">Jika non-taxable, kolom ini otomatis 0.</small>
  </div>

  {{-- NEW: Require NPWP on SO (ICP) --}}
  <div class="col-md-3">
    <label class="form-label d-block">Require NPWP on SO</label>
    {{-- hidden 0 agar nilai terkirim saat switch OFF --}}
    <input type="hidden" name="require_npwp_on_so" value="0">
    <label class="form-check form-switch">
      <input id="requireNpwp" class="form-check-input" type="checkbox" name="require_npwp_on_so" value="1"
             @checked(old('require_npwp_on_so', $co->require_npwp_on_so ?? false))>
      <span class="form-check-label">Wajibkan NPWP saat Sales Order (ICP)</span>
    </label>
    <small class="form-hint">Jika perusahaan non-taxable, opsi ini akan dimatikan.</small>
  </div>

  <div class="col-md-3">
    <label class="form-label">Quotation Prefix</label>
    <input class="form-control" name="quotation_prefix" value="{{ old('quotation_prefix', $co->quotation_prefix ?? '') }}">
  </div>

  <div class="col-md-3">
    <label class="form-label">Invoice Prefix</label>
    <input class="form-control" name="invoice_prefix" value="{{ old('invoice_prefix', $co->invoice_prefix ?? '') }}">
  </div>

  <div class="col-md-3">
    <label class="form-label">Delivery Prefix</label>
    <input class="form-control" name="delivery_prefix" value="{{ old('delivery_prefix', $co->delivery_prefix ?? '') }}">
  </div>

  {{-- NEW: Default Valid Days (masa berlaku quotation) --}}
  <div class="col-md-3">
    <label class="form-label">Default Valid Days</label>
    <div class="input-group">
      <input class="form-control text-end" name="default_valid_days" id="defaultValidDays"
             inputmode="numeric" pattern="\d*"
             value="{{ old('default_valid_days', $co->default_valid_days ?? '') }}"
             placeholder="30">
      <span class="input-group-text">hari</span>
    </div>
    <small class="form-hint">Biarkan kosong untuk fallback 30 hari. Rentang 1–365.</small>
  </div>

  <div class="col-12"><hr></div>

  <div class="col-md-6">
    <label class="form-label">Alamat</label>
    <textarea class="form-control" rows="4" name="address">{{ old('address', $co->address ?? '') }}</textarea>
  </div>

  <div class="col-md-6">
    <div class="row g-3">
      <div class="col-md-6">
        <label class="form-label">NPWP / Tax ID</label>
        <input class="form-control" name="tax_id" value="{{ old('tax_id', $co->tax_id ?? '') }}">
      </div>
      <div class="col-md-6">
        <label class="form-label">Email</label>
        <input class="form-control" name="email" value="{{ old('email', $co->email ?? '') }}">
      </div>
      <div class="col-md-6">
        <label class="form-label">Phone</label>
        <input class="form-control" name="phone" value="{{ old('phone', $co->phone ?? '') }}">
      </div>
      <div class="col-md-6">
        <label class="form-label">Logo</label>
        <input type="file" class="form-control" name="logo" accept="image/*">
        @if(($co->logo_path ?? null))
          <img class="mt-2 rounded" src="{{ asset('storage/'.$co->logo_path) }}" style="height:48px" alt="logo">
        @endif
      </div>
    </div>
  </div>

  <div class="col-12"><hr></div>

  <div class="col-md-4">
    <label class="form-label">Bank Name</label>
    <input class="form-control" name="bank_name" value="{{ old('bank_name', $co->bank_name ?? '') }}">
  </div>

  <div class="col-md-4">
    <label class="form-label">Account Name</label>
    <input class="form-control" name="bank_account_name" value="{{ old('bank_account_name', $co->bank_account_name ?? '') }}">
  </div>

  <div class="col-md-4">
    <label class="form-label">Account No</label>
    <input class="form-control" name="bank_account_no" value="{{ old('bank_account_no', $co->bank_account_no ?? '') }}">
  </div>

  <div class="col-md-6">
    <label class="form-label">Branch</label>
    <input class="form-control" name="bank_account_branch" value="{{ old('bank_account_branch', $co->bank_account_branch ?? '') }}">
  </div>

  <div class="col-md-3">
    <label class="form-label d-block">Set as Default</label>
    <label class="form-check form-switch">
      <input class="form-check-input" type="checkbox" name="is_default" value="1"
             @checked(old('is_default', $co->is_default ?? false))>
      <span class="form-check-label">Jadikan default</span>
    </label>
  </div>
</div>

{{-- Auto lock/unlock tax & NPWP requirement + clamp default_valid_days --}}
<script>
  (function () {
    const taxable    = document.getElementById('isTaxable');
    const taxInput   = document.getElementById('defaultTax');
    const dvdInput   = document.getElementById('defaultValidDays');
    const reqNpwp    = document.getElementById('requireNpwp');

    function syncTaxField() {
      if (!taxable) return;

      if (!taxable.checked) {
        if (taxInput) {
          taxInput.value = '0';
          taxInput.setAttribute('readonly', 'readonly');
          taxInput.classList.add('bg-light');
        }
        if (reqNpwp) {
          reqNpwp.checked = false;
          reqNpwp.setAttribute('disabled', 'disabled');
        }
      } else {
        if (taxInput) {
          taxInput.removeAttribute('readonly');
          taxInput.classList.remove('bg-light');
          if (!taxInput.value || taxInput.value === '0') {
            taxInput.value = taxInput.value || '11.00';
          }
        }
        if (reqNpwp) {
          reqNpwp.removeAttribute('disabled');
        }
      }
    }

    function clampDays() {
      if (!dvdInput) return;
      let v = (dvdInput.value || '').replace(/\D/g, '');
      if (v === '') { dvdInput.value = ''; return; } // allow empty → fallback 30
      let n = parseInt(v, 10);
      if (isNaN(n)) { dvdInput.value = ''; return; }
      if (n < 1) n = 1;
      if (n > 365) n = 365;
      dvdInput.value = String(n);
    }

    if (taxable) {
      taxable.addEventListener('change', syncTaxField);
      syncTaxField(); // initial
    }

    if (dvdInput) {
      dvdInput.addEventListener('input', clampDays);
      dvdInput.addEventListener('blur', clampDays);
    }
  })();
</script>
