@extends('layouts.tabler')

@section('content')
<div class="container-xl">
  <form action="{{ route('customers.store') }}" method="POST" class="card" id="custForm">
    @csrf

    <div class="card-header">
      <div class="card-title">Add Customer</div>
    </div>

    {{-- ALERT VALIDATION --}}
    @if ($errors->any())
      <div class="alert alert-danger m-3">
        <div class="text-danger fw-bold mb-1">Periksa kembali input Anda:</div>
        <ul class="mb-0">
          @foreach ($errors->all() as $error)
            <li>{{ $error }}</li>
          @endforeach
        </ul>
      </div>
    @endif

    <div class="card-body">

      {{-- Tabs header --}}
      @php
        $hasBSerr = collect($errors->keys())->first(fn($k)=>str_starts_with($k,'billing_')||str_starts_with($k,'shipping_'));
        $activeTab = $hasBSerr ? 'bs' : 'details';
      @endphp
      <ul class="nav nav-tabs mb-3" role="tablist">
        <li class="nav-item" role="presentation">
          <a class="nav-link {{ $activeTab==='details'?'active':'' }}" data-bs-toggle="tab" href="#tab-details" role="tab">Customer Details</a>
        </li>
        <li class="nav-item" role="presentation">
          <a class="nav-link {{ $activeTab==='bs'?'active':'' }}" data-bs-toggle="tab" href="#tab-bs" role="tab">Billing & Shipping</a>
        </li>
      </ul>

      <div class="tab-content">

        {{-- ================ TAB 1: DETAILS ================ --}}
        <div class="tab-pane {{ $activeTab==='details'?'active show':'' }}" id="tab-details" role="tabpanel">
          <div class="row g-3">

            {{-- NAME + Places --}}
            <div class="col-md-8">
              <label class="form-label">Customer Name <span class="text-danger">*</span></label>
              <div class="input-group">
                <input type="text" name="name" id="cust_name" class="form-control"
                      placeholder="Contoh: PT Ersindo Nusantara Tbk"
                      value="{{ old('name') }}" required>
                <button type="button" class="btn btn-outline-primary" data-bs-toggle="modal" data-bs-target="#placesModal">
                  Cari di Google Places
                </button>
              </div>
              <small id="dup_hint" class="form-hint"></small>
            </div>

            <div class="col-md-4">
              <label class="form-label">Email</label>
              <input type="email" name="email" class="form-control" placeholder="opsional" value="{{ old('email') }}">
            </div>

            {{-- JENIS (WAJIB) --}}
            <div class="col-md-4">
              <label class="form-label">Jenis <span class="text-danger">*</span></label>
              <select name="jenis_id" class="form-select @error('jenis_id') is-invalid @enderror" required>
                <option value="">— Pilih Jenis —</option>
                @foreach($jenises as $j)
                  <option value="{{ $j->id }}" @selected(old('jenis_id') == $j->id)>{{ $j->name }}</option>
                @endforeach
              </select>
              @error('jenis_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>

            <div class="col-md-4">
              <label class="form-label">Phone</label>
              <input type="text" name="phone" class="form-control" placeholder="opsional" value="{{ old('phone') }}">
            </div>

            <div class="col-md-4">
              <label class="form-label">Billing Terms (days)</label>
              <input type="number" name="billing_terms_days" class="form-control" min="0" max="3650"
                    placeholder="mis. 30 (opsional)" value="{{ old('billing_terms_days') }}">
            </div>

            <div class="col-md-4">
              <label class="form-label">Website</label>
              <input type="text" name="website" id="website" class="form-control" placeholder="https:// … (opsional)"
                    value="{{ old('website') }}">
            </div>

            <div class="col-md-12">
              <label class="form-label">Address</label>
              <textarea name="address" id="address" class="form-control" rows="2" placeholder="Alamat lengkap (opsional)">{{ old('address') }}</textarea>
            </div>

            <div class="col-md-4">
              <label class="form-label">City</label>
              <input type="text" name="city" id="city" class="form-control" value="{{ old('city') }}">
            </div>

            <div class="col-md-4">
              <label class="form-label">Province</label>
              <input type="text" name="province" id="province" class="form-control" value="{{ old('province') }}">
            </div>

            <div class="col-md-4">
              <label class="form-label">Country</label>
              <input type="text" name="country" id="country" class="form-control" value="{{ old('country') }}">
            </div>

            {{-- NPWP --}}
            <div class="col-md-4">
              <label class="form-label">NPWP</label>
              <input type="text" name="npwp" class="form-control" value="{{ old('npwp') }}">
            </div>

            <div class="col-md-12">
              <label class="form-label">Notes</label>
              <textarea name="notes" class="form-control" rows="2" placeholder="Catatan internal (opsional)">{{ old('notes') }}</textarea>
            </div>

          </div>
        </div>

        {{-- ================ TAB 2: BILLING & SHIPPING ================ --}}
        <div class="tab-pane {{ $activeTab==='bs'?'active show':'' }}" id="tab-bs" role="tabpanel">
          <div class="row">
            {{-- Billing --}}
            <div class="col-lg-6">
              <div class="d-flex justify-content-between align-items-center mb-2">
                <h4 class="m-0 fs-5">Billing Address</h4>
                <button class="btn btn-link p-0" type="button" id="btnCopyFromCustomer">Same as Customer Info</button>
              </div>

              <div class="mb-2">
                <label class="form-label">Street</label>
                <textarea name="billing_street" id="billing_street" class="form-control" rows="3">{{ old('billing_street') }}</textarea>
              </div>
              <div class="row g-2">
                <div class="col-md-6">
                  <label class="form-label">City</label>
                  <input type="text" name="billing_city" id="billing_city" class="form-control" value="{{ old('billing_city') }}">
                </div>
                <div class="col-md-6">
                  <label class="form-label">State</label>
                  <input type="text" name="billing_state" id="billing_state" class="form-control" value="{{ old('billing_state') }}">
                </div>
              </div>
              <div class="row g-2 mt-0">
                <div class="col-md-6">
                  <label class="form-label">Zip Code</label>
                  <input type="text" name="billing_zip" id="billing_zip" class="form-control" value="{{ old('billing_zip') }}">
                </div>
                <div class="col-md-6">
                  <label class="form-label">Country</label>
                  <input type="text" name="billing_country" id="billing_country" class="form-control" value="{{ old('billing_country') }}">
                </div>
              </div>

              <div class="mt-2">
                <label class="form-label">Billing Notes</label>
                <textarea name="billing_notes" id="billing_notes" class="form-control" rows="2" placeholder="Contoh: penagihan via email, jatuh tempo, dokumen pendukung, dsb.">{{ old('billing_notes') }}</textarea>
              </div>
            </div>

            {{-- Shipping --}}
            <div class="col-lg-6">
              <div class="d-flex justify-content-between align-items-center mb-2">
                <h4 class="m-0 fs-5">Shipping Address</h4>
                <button class="btn btn-link p-0" type="button" id="btnCopyBillingToShipping">Copy Billing Address</button>
              </div>

              <div class="mb-2">
                <label class="form-label">Street</label>
                <textarea name="shipping_street" id="shipping_street" class="form-control" rows="3">{{ old('shipping_street') }}</textarea>
              </div>
              <div class="row g-2">
                <div class="col-md-6">
                  <label class="form-label">City</label>
                  <input type="text" name="shipping_city" id="shipping_city" class="form-control" value="{{ old('shipping_city') }}">
                </div>
                <div class="col-md-6">
                  <label class="form-label">State</label>
                  <input type="text" name="shipping_state" id="shipping_state" class="form-control" value="{{ old('shipping_state') }}">
                </div>
              </div>
              <div class="row g-2 mt-0">
                <div class="col-md-6">
                  <label class="form-label">Zip Code</label>
                  <input type="text" name="shipping_zip" id="shipping_zip" class="form-control" value="{{ old('shipping_zip') }}">
                </div>
                <div class="col-md-6">
                  <label class="form-label">Country</label>
                  <input type="text" name="shipping_country" id="shipping_country" class="form-control" value="{{ old('shipping_country') }}">
                </div>
              </div>

              <div class="mt-2">
                <label class="form-label">Shipping Notes</label>
                <textarea name="shipping_notes" id="shipping_notes" class="form-control" rows="2" placeholder="Contoh: kurir langganan, packing, instruksi serah terima, jam operasional, dsb.">{{ old('shipping_notes') }}</textarea>
              </div>
            </div>
          </div>
        </div>

      </div> {{-- /tab-content --}}
    </div> {{-- /card-body --}}

    @include('layouts.partials.form_footer', [
      'cancelUrl'    => route('customers.index'),
      'cancelLabel'  => 'Batal',
      'cancelInline' => true, // <-- kunci supaya menempel
      'buttons' => [
        ['type'=>'submit','name'=>'after','value'=>'index',    'label'=>'Simpan',            'class'=>'btn btn-primary'],
        ['type'=>'submit','name'=>'after','value'=>'contacts', 'label'=>'Simpan & Tambah',   'class'=>'btn btn-primary'],
      ],
    ])
  </form>
</div>

{{-- ===== Modal Google Places ===== --}}
<div class="modal modal-blur fade" id="placesModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Cari di Google Places</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">

        <div class="input-group mb-2">
          <input type="text" id="places_q" class="form-control" placeholder="Ketik nama perusahaan…">
          <button class="btn btn-primary" id="places_btn">Cari</button>
        </div>

        <div id="places_results" class="list-group" style="max-height: 50vh; overflow:auto">
          <div class="list-group-item text-muted">Belum ada pencarian.</div>
        </div>

        <small class="text-muted d-block mt-2">
          Hasil pencarian hanya membantu isi otomatis. Semua field tetap bisa Anda ubah manual.
        </small>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn me-auto" data-bs-dismiss="modal">Tutup</button>
      </div>
    </div>
  </div>
</div>
@endsection

@push('scripts')
<script>
(function(){
  // ===== Soft duplicate check (info saja) =====
  const nameInput = document.getElementById('cust_name');
  const dupHint   = document.getElementById('dup_hint');
  const dupUrl    = "{{ route('customers.dup-check') }}";
  let t = null;

  function dupCheck(v){
    if (!v || v.trim()===''){ dupHint.textContent=''; return; }
    fetch(dupUrl + '?name=' + encodeURIComponent(v), { headers: { 'X-Requested-With':'XMLHttpRequest' }})
      .then(r => r.ok ? r.json() : {exists:false})
      .then(j => {
        if (j.exists) dupHint.innerHTML = '<span class="text-warning">Nama ini sudah ada di database. Anda tetap bisa menyimpan.</span>';
        else          dupHint.innerHTML = '<span class="text-success">Nama belum terdaftar.</span>';
      })
      .catch(()=>{});
  }
  nameInput?.addEventListener('input', () => { clearTimeout(t); t=setTimeout(()=>dupCheck(nameInput.value), 500); });
  dupCheck(nameInput?.value || '');

  // ===== Google Places (server proxy) =====
  const btn   = document.getElementById('places_btn');
  const q     = document.getElementById('places_q');
  const list  = document.getElementById('places_results');
  const url   = "{{ route('places.search') }}";

  function escapeHtml(s){ return String(s || '').replace(/[&<>"']/g, m => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[m])); }

  function render(items){
    list.innerHTML = '';
    if (!Array.isArray(items) || items.length === 0){
      list.innerHTML = '<div class="list-group-item text-muted">Tidak ada hasil.</div>';
      return;
    }
    items.forEach(it => {
      const li = document.createElement('div');
      li.className = 'list-group-item';
      li.innerHTML = `
        <div class="d-flex justify-content-between align-items-start">
          <div>
            <div class="fw-bold">${escapeHtml(it.name || '')}</div>
            <div class="text-muted small">${escapeHtml(it.address || it.formatted_address || '')}</div>
            ${it.website ? `<div class="small">Website: ${escapeHtml(it.website)}</div>` : ''}
            ${it.phone   ? `<div class="small">Phone: ${escapeHtml(it.phone)}</div>` : ''}
          </div>
          <div>
            <button type="button" class="btn btn-sm btn-primary pickPlace">Pilih</button>
          </div>
        </div>
      `;
      li.dataset.payload = JSON.stringify(it);
      list.appendChild(li);
    });
  }

  function search(){
    const term = q.value.trim();
    if (!term){ q.focus(); return; }
    list.innerHTML = '<div class="list-group-item">Mencari…</div>';
    fetch(url + '?q=' + encodeURIComponent(term), { headers: { 'X-Requested-With':'XMLHttpRequest' }})
      .then(r => r.ok ? r.json() : {items:[]})
      .then(j => render(j.items || []))
      .catch(()=>{ list.innerHTML = '<div class="list-group-item text-danger">Gagal memuat hasil.</div>'; });
  }

  btn?.addEventListener('click', search);
  q?.addEventListener('keydown', e => { if (e.key==='Enter'){ e.preventDefault(); search(); }});

  function splitAddress(addr){
    const parts = (addr || '').split(',').map(p => p.trim()).filter(Boolean);
    const n = parts.length;
    return { city: n>=3?parts[n-3]:'', province: n>=2?parts[n-2]:'', country: n>=1?parts[n-1]:'' };
  }

  list?.addEventListener('click', (e) => {
    const btn = e.target.closest('.pickPlace'); if (!btn) return;
    const row = e.target.closest('.list-group-item');
    let data = {};
    try { data = JSON.parse(row.dataset.payload || '{}'); } catch(_){}

    const addr = data.address || data.formatted_address || '';

    if (data.name)     nameInput.value = data.name;
    if (addr)          document.getElementById('address').value = addr;
    if (data.website)  document.getElementById('website').value = data.website;

    const phoneField = document.querySelector('input[name="phone"]');
    if (data.phone && phoneField && !phoneField.value) phoneField.value = data.phone;

    const geo = splitAddress(addr);
    const cityF = document.getElementById('city');
    const provF = document.getElementById('province');
    const ctryF = document.getElementById('country');
    if (cityF  && !cityF.value)  cityF.value  = geo.city;
    if (provF  && !provF.value)  provF.value  = geo.province;
    if (ctryF  && !ctryF.value)  ctryF.value  = geo.country;

    const modalEl = document.getElementById('placesModal');
    const m = bootstrap.Modal.getInstance(modalEl) || new bootstrap.Modal(modalEl);
    m.hide();

    dupCheck(nameInput.value);
  });

  // ===== Helpers: copy address =====
  function copyCustToBilling(){
    document.getElementById('billing_street').value   = document.getElementById('address').value || '';
    document.getElementById('billing_city').value     = document.getElementById('city').value || '';
    document.getElementById('billing_state').value    = document.getElementById('province').value || '';
    document.getElementById('billing_country').value  = document.getElementById('country').value || '';
  }
  function copyBillingToShipping(){
    document.getElementById('shipping_street').value  = document.getElementById('billing_street').value || '';
    document.getElementById('shipping_city').value    = document.getElementById('billing_city').value || '';
    document.getElementById('shipping_state').value   = document.getElementById('billing_state').value || '';
    document.getElementById('shipping_zip').value     = document.getElementById('billing_zip').value || '';
    document.getElementById('shipping_country').value = document.getElementById('billing_country').value || '';
  }
  document.getElementById('btnCopyFromCustomer')?.addEventListener('click', copyCustToBilling);
  document.getElementById('btnCopyBillingToShipping')?.addEventListener('click', copyBillingToShipping);
})();
</script>
@endpush

@push('styles')
<style>
  .sticky-footer{
    position: sticky;
    bottom: 0;
    z-index: 2;
    background: var(--tblr-card-bg, #fff);
    border-top: 1px solid rgba(0,0,0,.05);
  }
</style>
@endpush
