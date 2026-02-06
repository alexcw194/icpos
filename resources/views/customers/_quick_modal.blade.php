<div class="modal fade" id="quickCustomerModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">New Customer / Company</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>

      <div class="modal-body">
        {{-- Search helper (Google Places via /api/places/search) --}}
        <div class="mb-3">
          <label class="form-label">Cari di Google Places</label>
          <div class="input-group">
            <input type="text" id="qc_search" class="form-control" placeholder="mis. PT Ersindo Nusa..." autocomplete="off">
            <button class="btn btn-outline-secondary" type="button" id="qc_btn_search">Cari</button>
          </div>
          <div id="qc_results" class="list-group mt-2" style="max-height: 220px; overflow:auto;"></div>
          <small class="form-hint">Klik hasil untuk mengisi nama otomatis. Email/telepon tidak disediakan Google Places, isi manual jika perlu.</small>
        </div>

        {{-- Form minimal untuk disimpan --}}
        <div class="row g-2">
          <div class="col-12">
            <label class="form-label">Name <span class="text-danger">*</span></label>
            <input type="text" id="qc_name" class="form-control" placeholder="Nama customer / company">
          </div>

          {{-- Opsional: tampilkan field tambahan untuk referensi manual (tidak wajib tersimpan) --}}
          <div class="col-md-6">
            <label class="form-label">Phone (opsional)</label>
            <input type="text" id="qc_phone" class="form-control" placeholder="(Tidak selalu tersedia)">
          </div>
          <div class="col-md-6">
            <label class="form-label">Website (opsional)</label>
            <input type="text" id="qc_website" class="form-control" placeholder="https://...">
          </div>
          <div class="col-12">
            <label class="form-label">Address (opsional)</label>
            <textarea id="qc_address" class="form-control" rows="2"></textarea>
          </div>
        </div>

        <div id="qc_dup_box" class="alert alert-warning d-none mt-3 mb-2"></div>
        <label id="qc_confirm_wrap" class="form-check d-none mb-0">
          <input type="checkbox" class="form-check-input" id="qc_confirm_similar">
          <span class="form-check-label">
            Saya konfirmasi ini perusahaan berbeda, tetap buat data baru.
          </span>
        </label>

        <div id="qc_alert" class="alert alert-danger d-none mt-3"></div>
      </div>

      <div class="modal-footer">
        <button type="button" class="btn btn-link" data-bs-dismiss="modal">Tutup</button>
        <button type="button" class="btn btn-primary" id="qc_btn_save">Simpan & Pakai</button>
      </div>
    </div>
  </div>
</div>

@push('scripts')
<script>
(function(){
  if (window.__quickCustomerModalBound) return;
  window.__quickCustomerModalBound = true;

  const elSearch   = document.getElementById('qc_search');
  const elBtnSrch  = document.getElementById('qc_btn_search');
  const elResults  = document.getElementById('qc_results');
  const elName     = document.getElementById('qc_name');
  const elPhone    = document.getElementById('qc_phone');
  const elWebsite  = document.getElementById('qc_website');
  const elAddress  = document.getElementById('qc_address');
  const elAlert    = document.getElementById('qc_alert');
  const elDupBox   = document.getElementById('qc_dup_box');
  const elConfirmWrap = document.getElementById('qc_confirm_wrap');
  const elConfirmSimilar = document.getElementById('qc_confirm_similar');
  const elBtnSave  = document.getElementById('qc_btn_save');

  const QUICK_STORE_URL = @json(route('customers.quick-store'));
  const DUP_CHECK_URL = @json(route('customers.dupcheck'));
  const PLACES_URL = @json(route('places.search'));

  function showError(msg){
    elAlert.textContent = msg || 'Terjadi kesalahan.';
    elAlert.classList.remove('d-none');
  }
  function hideError(){
    elAlert.classList.add('d-none');
  }

  function escapeHtml(value){
    return String(value ?? '')
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#039;');
  }

  function hideDuplicateDecision(){
    if (elDupBox){
      elDupBox.classList.add('d-none');
      elDupBox.innerHTML = '';
    }
    if (elConfirmWrap){
      elConfirmWrap.classList.add('d-none');
    }
    if (elConfirmSimilar){
      elConfirmSimilar.checked = false;
    }
  }

  function renderDuplicateDecision(payload){
    if (!elDupBox) return;

    const exact = Array.isArray(payload?.exact) ? payload.exact : [];
    const similar = Array.isArray(payload?.similar) ? payload.similar : [];

    if (!exact.length && !similar.length){
      hideDuplicateDecision();
      return;
    }

    const exactItems = exact.map(c =>
      `<li class="mb-1">
        <span class="fw-semibold">${escapeHtml(c.name)}</span>
        <button type="button" class="btn btn-sm btn-outline-primary ms-2 js-qc-use-existing" data-id="${c.id}" data-name="${escapeHtml(c.name)}">Pakai ini</button>
      </li>`
    ).join('');

    const similarItems = similar.map(c =>
      `<li class="mb-1">
        <span class="fw-semibold">${escapeHtml(c.name)}</span>
        <span class="text-muted">(similarity ${escapeHtml(c.score)}%)</span>
        <button type="button" class="btn btn-sm btn-outline-primary ms-2 js-qc-use-existing" data-id="${c.id}" data-name="${escapeHtml(c.name)}">Pakai ini</button>
      </li>`
    ).join('');

    elDupBox.innerHTML = `
      ${exact.length ? '<div class="fw-semibold mb-1">Nama persis sudah ada:</div><ul class="mb-2 ps-3">' + exactItems + '</ul>' : ''}
      ${similar.length ? '<div class="fw-semibold mb-1">Nama mirip ditemukan:</div><ul class="mb-0 ps-3">' + similarItems + '</ul>' : ''}
    `;
    elDupBox.classList.remove('d-none');

    if (elConfirmWrap){
      elConfirmWrap.classList.toggle('d-none', !similar.length || !!exact.length);
    }
  }

  function applyCustomerSelection(customer){
    const customerId = String(customer.customer_id || customer.id || '');
    const customerName = String(customer.name || customer.label || '');
    const customerUid = String(customer.uid || ('customer-' + customerId));

    if (!customerId || !customerName) return;

    const hiddenCustomer = document.getElementById('customer_id');
    const hiddenContact = document.getElementById('contact_id');
    if (hiddenCustomer) hiddenCustomer.value = customerId;
    if (hiddenContact) hiddenContact.value = '';

    const hiddenSelect = document.getElementById('customer_id_select');
    if (hiddenSelect){
      let opt = Array.from(hiddenSelect.options).find(o => String(o.value) === customerId);
      if (!opt){
        opt = document.createElement('option');
        opt.value = customerId;
        opt.textContent = customerName;
        hiddenSelect.appendChild(opt);
      }
      hiddenSelect.value = customerId;
      hiddenSelect.dispatchEvent(new Event('change', { bubbles: true }));
    }

    const pickerInput = document.getElementById('customerPicker');
    if (pickerInput){
      const ts = pickerInput.tomselect || pickerInput.__ts;
      if (ts){
        if (!ts.options[customerUid]) {
          ts.addOption({
            uid: customerUid,
            type: 'customer',
            label: customerName,
            name: customerName,
            customer_id: Number(customerId),
            contact_id: null,
          });
        }
        ts.setValue(customerUid, true);
      } else {
        pickerInput.value = customerName;
        pickerInput.dispatchEvent(new Event('input', { bubbles: true }));
        pickerInput.dispatchEvent(new Event('change', { bubbles: true }));
      }
    }
  }

  function renderResults(items){
    elResults.innerHTML = '';
    if (!Array.isArray(items) || items.length === 0){
      elResults.innerHTML = '<div class="list-group-item text-muted">Tidak ada hasil.</div>';
      return;
    }
    items.forEach(it => {
      const div = document.createElement('button');
      div.type = 'button';
      div.className = 'list-group-item list-group-item-action';
      div.innerHTML = `
        <div class="fw-bold">${it.name ?? ''}</div>
        <div class="small text-muted">${(it.address ?? '')} ${(it.domain ? ' - ' + it.domain : '')}</div>
      `;
      div.addEventListener('click', () => {
        elName.value = it.name || '';
        elPhone.value = it.phone || '';
        elWebsite.value = it.website || (it.domain ? ('https://' + it.domain) : '');
        elAddress.value = it.address || '';
        hideDuplicateDecision();
      });
      elResults.appendChild(div);
    });
  }

  async function doSearch(){
    hideError();
    const q = (elSearch.value || '').trim();
    if (!q) { showError('Masukkan kata kunci.'); return; }
    elResults.innerHTML = '<div class="list-group-item">Mencari...</div>';
    try{
      const res = await fetch(`${PLACES_URL}?q=${encodeURIComponent(q)}`, {headers: {'Accept':'application/json'}});
      if (!res.ok) throw new Error('HTTP ' + res.status);
      const json = await res.json();
      renderResults(json.items || []);
    } catch(e){
      showError('Gagal memuat hasil. Coba lagi.');
      console.error(e);
    }
  }

  elBtnSrch.addEventListener('click', doSearch);
  elSearch.addEventListener('keydown', (ev) => {
    if (ev.key === 'Enter') { ev.preventDefault(); doSearch(); }
  });

  elName.addEventListener('input', () => {
    hideDuplicateDecision();
    hideError();
  });

  async function runDupCheck(name){
    const res = await fetch(`${DUP_CHECK_URL}?q=${encodeURIComponent(name)}`, {headers: {'Accept': 'application/json'}});
    if (!res.ok) throw new Error('HTTP ' + res.status);
    return await res.json();
  }

  async function saveAndUse(){
    hideError();
    hideDuplicateDecision();
    const name = (elName.value || '').trim();
    if (!name) { showError('Name wajib diisi.'); return; }

    try{
      const dup = await runDupCheck(name);
      renderDuplicateDecision(dup);

      const hasExact = Array.isArray(dup?.exact) && dup.exact.length > 0;
      const hasSimilar = Array.isArray(dup?.similar) && dup.similar.length > 0;
      const confirmSimilar = !!(elConfirmSimilar && elConfirmSimilar.checked);

      if (hasExact){
        showError('Nama persis sudah ada. Pilih data existing di daftar.');
        return;
      }
      if (hasSimilar && !confirmSimilar){
        showError('Ada nama mirip. Pilih data existing, atau centang konfirmasi jika tetap ingin membuat baru.');
        return;
      }

      const res = await fetch(QUICK_STORE_URL, {
        method: 'POST',
        headers: {
          'Accept': 'application/json',
          'Content-Type': 'application/json',
          'X-CSRF-TOKEN': '{{ csrf_token() }}',
        },
        body: JSON.stringify({
          name,
          confirm_similar_name: confirmSimilar ? 1 : 0,
        })
      });

      const json = await res.json();
      if (!res.ok) {
        if (json && (json.exact || json.similar)) {
          renderDuplicateDecision(json);
          showError(json.message || 'Gagal menyimpan customer.');
          return;
        }
        throw new Error('HTTP ' + res.status);
      }

      if (!json.ok || !json.customer) throw new Error('Invalid response');

      applyCustomerSelection(json.customer);

      const modalEl = document.getElementById('quickCustomerModal');
      const bsModal = bootstrap.Modal.getInstance(modalEl) || new bootstrap.Modal(modalEl);
      bsModal.hide();
    } catch(e){
      showError('Gagal menyimpan customer.');
      console.error(e);
    }
  }

  if (elDupBox){
    elDupBox.addEventListener('click', (ev) => {
      const btn = ev.target.closest('.js-qc-use-existing');
      if (!btn) return;

      const id = btn.getAttribute('data-id');
      const name = btn.getAttribute('data-name');
      if (!id || !name) return;

      applyCustomerSelection({
        id: Number(id),
        customer_id: Number(id),
        name: name,
        label: name,
        uid: 'customer-' + id,
      });

      const modalEl = document.getElementById('quickCustomerModal');
      const bsModal = bootstrap.Modal.getInstance(modalEl) || new bootstrap.Modal(modalEl);
      bsModal.hide();
    });
  }

  const modalEl = document.getElementById('quickCustomerModal');
  if (modalEl){
    modalEl.addEventListener('hidden.bs.modal', () => {
      hideError();
      hideDuplicateDecision();
      if (elSearch) elSearch.value = '';
      if (elResults) elResults.innerHTML = '';
      if (elName) elName.value = '';
      if (elPhone) elPhone.value = '';
      if (elWebsite) elWebsite.value = '';
      if (elAddress) elAddress.value = '';
    });
  }

  elBtnSave.addEventListener('click', saveAndUse);
})();
</script>
@endpush
