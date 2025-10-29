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
          <small class="form-hint">Klik hasil untuk mengisi nama otomatis. Email/telepon tidak disediakan Google Places—isi manual jika perlu.</small>
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
  const elSearch   = document.getElementById('qc_search');
  const elBtnSrch  = document.getElementById('qc_btn_search');
  const elResults  = document.getElementById('qc_results');
  const elName     = document.getElementById('qc_name');
  const elPhone    = document.getElementById('qc_phone');
  const elWebsite  = document.getElementById('qc_website');
  const elAddress  = document.getElementById('qc_address');
  const elAlert    = document.getElementById('qc_alert');
  const elBtnSave  = document.getElementById('qc_btn_save');

  // Ganti ini sesuai id select di halaman (akan kita pasang di Langkah 6.4)
  const CUSTOMER_SELECT_ID = 'customer_id_select';

  function showError(msg){
    elAlert.textContent = msg || 'Terjadi kesalahan.';
    elAlert.classList.remove('d-none');
  }
  function hideError(){ elAlert.classList.add('d-none'); }

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
        <div class="small text-muted">${(it.address ?? '')} ${(it.domain ? ' • ' + it.domain : '')}</div>
      `;
      div.addEventListener('click', () => {
        elName.value = it.name || '';
        // Phone/website/address hanya untuk referensi pengguna
        elPhone.value = it.phone || '';
        elWebsite.value = it.website || (it.domain ? ('https://' + it.domain) : '');
        elAddress.value = it.address || '';
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
      const res = await fetch(`/api/places/search?q=${encodeURIComponent(q)}`, {headers: {'Accept':'application/json'}});
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

  async function saveAndUse(){
    hideError();
    const name = (elName.value || '').trim();
    if (!name) { showError('Name wajib diisi.'); return; }

    try{
      const res = await fetch(`{{ route('customers.quick-store') }}`, {
        method: 'POST',
        headers: {
          'Accept': 'application/json',
          'Content-Type': 'application/json',
          'X-CSRF-TOKEN': '{{ csrf_token() }}',
        },
        body: JSON.stringify({ name })
      });
      if (!res.ok) throw new Error('HTTP ' + res.status);
      const json = await res.json();
      if (!json.ok || !json.customer) throw new Error('Invalid response');

      // Sisipkan ke select customer di halaman & pilih
      const sel = document.getElementById(CUSTOMER_SELECT_ID);
      if (sel){
        const opt = document.createElement('option');
        opt.value = json.customer.id;
        opt.textContent = json.customer.name;
        opt.selected = true;
        sel.appendChild(opt);
        sel.dispatchEvent(new Event('change'));
      }

      // Tutup modal
      const modalEl = document.getElementById('quickCustomerModal');
      const bsModal = bootstrap.Modal.getInstance(modalEl) || new bootstrap.Modal(modalEl);
      bsModal.hide();
    } catch(e){
      showError('Gagal menyimpan customer.');
      console.error(e);
    }
  }

  elBtnSave.addEventListener('click', saveAndUse);
})();
</script>
@endpush
