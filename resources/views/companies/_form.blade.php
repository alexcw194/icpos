@php
  $co = $company ?? null;
  $bankRows = old('banks');
  if (!is_array($bankRows)) {
    $bankRows = ($co?->banks?->map(fn($b) => [
      'id' => $b->id,
      'name' => $b->name,
      'account_name' => $b->account_name,
      'account_no' => $b->account_no,
      'branch' => $b->branch,
      'is_active' => (bool) $b->is_active,
    ])->values()->toArray()) ?? [];
  }
  $bankRowKeys = array_keys($bankRows);
  $bankRowIndex = $bankRowKeys ? (max($bankRowKeys) + 1) : 0;
  $bankRowCount = count($bankRows);
@endphp

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
    <label class="form-label d-block">Default Company</label>
    <label class="form-check form-switch">
      <input class="form-check-input" type="checkbox" name="is_default" value="1"
             @checked(old('is_default', $co->is_default ?? false))>
      <span class="form-check-label">Jadikan default</span>
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

  <div class="col-12">
    <div class="d-flex align-items-center mb-2">
      <h4 class="mb-0">Bank Accounts</h4>
      <button type="button" class="btn btn-outline-primary btn-sm ms-auto" id="addBankRow">
        Tambah Bank
      </button>
    </div>
    <div class="table-responsive">
      <table class="table table-sm table-bordered align-middle" id="companyBanksTable">
        <thead class="bg-light">
          <tr>
            <th style="width:22%">Bank Name</th>
            <th style="width:22%">Account Name</th>
            <th style="width:18%">Account No</th>
            <th style="width:18%">Branch</th>
            <th style="width:10%" class="text-center">Active</th>
            <th style="width:10%" class="text-center">Action</th>
          </tr>
        </thead>
        <tbody>
          @if($bankRowCount === 0)
            <tr class="bank-empty-row">
              <td colspan="6" class="text-muted text-center">Belum ada bank. Klik "Tambah Bank".</td>
            </tr>
          @endif
          @foreach($bankRows as $i => $row)
            <tr data-bank-row>
              <td>
                <input type="hidden" name="banks[{{ $i }}][id]" value="{{ old("banks.$i.id", $row['id'] ?? '') }}">
                <input class="form-control" name="banks[{{ $i }}][name]" value="{{ old("banks.$i.name", $row['name'] ?? '') }}">
              </td>
              <td>
                <input class="form-control" name="banks[{{ $i }}][account_name]" value="{{ old("banks.$i.account_name", $row['account_name'] ?? '') }}">
              </td>
              <td>
                <input class="form-control" name="banks[{{ $i }}][account_no]" value="{{ old("banks.$i.account_no", $row['account_no'] ?? '') }}">
              </td>
              <td>
                <input class="form-control" name="banks[{{ $i }}][branch]" value="{{ old("banks.$i.branch", $row['branch'] ?? '') }}">
              </td>
              <td class="text-center">
                <input type="hidden" name="banks[{{ $i }}][is_active]" value="0">
                <input class="form-check-input" type="checkbox" name="banks[{{ $i }}][is_active]" value="1"
                       @checked(old("banks.$i.is_active", $row['is_active'] ?? false))>
              </td>
              <td class="text-center">
                <input type="hidden" name="banks[{{ $i }}][_delete]" value="0">
                <button type="button" class="btn btn-outline-danger btn-sm" data-remove-bank>Hapus</button>
              </td>
            </tr>
          @endforeach
        </tbody>
      </table>
    </div>
    <template id="bankRowTemplate">
      <tr data-bank-row>
        <td>
          <input type="hidden" name="banks[__INDEX__][id]" value="">
          <input class="form-control" name="banks[__INDEX__][name]" value="">
        </td>
        <td>
          <input class="form-control" name="banks[__INDEX__][account_name]" value="">
        </td>
        <td>
          <input class="form-control" name="banks[__INDEX__][account_no]" value="">
        </td>
        <td>
          <input class="form-control" name="banks[__INDEX__][branch]" value="">
        </td>
        <td class="text-center">
          <input type="hidden" name="banks[__INDEX__][is_active]" value="0">
          <input class="form-check-input" type="checkbox" name="banks[__INDEX__][is_active]" value="1">
        </td>
        <td class="text-center">
          <input type="hidden" name="banks[__INDEX__][_delete]" value="0">
          <button type="button" class="btn btn-outline-danger btn-sm" data-remove-bank>Hapus</button>
        </td>
      </tr>
    </template>
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

    const addBtn = document.getElementById('addBankRow');
    const table = document.getElementById('companyBanksTable');
    const template = document.getElementById('bankRowTemplate');
    let nextIndex = {{ $bankRowIndex }};

    function ensureEmptyRowHidden() {
      if (!table) return;
      const emptyRow = table.querySelector('.bank-empty-row');
      const hasRows = table.querySelectorAll('tr[data-bank-row]').length > 0;
      if (emptyRow) {
        emptyRow.style.display = hasRows ? 'none' : '';
      }
    }

    function addRow() {
      if (!template || !table) return;
      const html = template.innerHTML.replace(/__INDEX__/g, String(nextIndex++));
      const tbody = table.querySelector('tbody');
      tbody.insertAdjacentHTML('beforeend', html);
      ensureEmptyRowHidden();
    }

    function onRemove(e) {
      const btn = e.target.closest('[data-remove-bank]');
      if (!btn) return;
      const row = btn.closest('tr');
      if (!row) return;
      const idInput = row.querySelector('input[name*=\"[id]\"]');
      const deleteInput = row.querySelector('input[name*=\"[_delete]\"]');
      if (idInput && idInput.value) {
        if (deleteInput) deleteInput.value = '1';
        row.style.display = 'none';
      } else {
        row.remove();
      }
      ensureEmptyRowHidden();
    }

    if (addBtn) addBtn.addEventListener('click', addRow);
    if (table) table.addEventListener('click', onRemove);
    ensureEmptyRowHidden();
  })();
</script>
