@extends('layouts.tabler')

@section('content')
<div class="container-xl">
  {{-- ========== FORM EDIT CUSTOMER ========== --}}
  <form action="{{ route('customers.update', $customer) }}" method="POST" class="card" id="custForm">
    @csrf
    @method('PUT')

    <div class="card-header">
      <div class="card-title">Edit Customer</div>
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

        {{-- ================= TAB: DETAILS ================= --}}
        <div class="tab-pane {{ $activeTab==='details'?'active show':'' }}" id="tab-details" role="tabpanel">
          <div class="row g-3">

            {{-- NAME + Places --}}
            <div class="col-md-8">
              <label class="form-label">Customer Name <span class="text-danger">*</span></label>
              <div class="input-group">
                <input type="text"
                       name="name"
                       id="cust_name"
                       class="form-control"
                       placeholder="Contoh: PT Ersindo Nusantara Tbk"
                       value="{{ old('name', $customer->name) }}"
                       data-original-name="{{ $customer->name }}"
                       required>
                <button type="button" class="btn btn-outline-primary" data-bs-toggle="modal" data-bs-target="#placesModal">
                  Cari di Google Places
                </button>
              </div>
              <small id="dup_hint" class="form-hint"></small>
            </div>

            <div class="col-md-4">
              <label class="form-label">Email</label>
              <input type="email" name="email" class="form-control" placeholder="opsional"
                     value="{{ old('email', $customer->email) }}">
            </div>

            {{-- JENIS (WAJIB) --}}
            <div class="col-md-4">
              <label class="form-label">Jenis <span class="text-danger">*</span></label>
              <select name="jenis_id" class="form-select @error('jenis_id') is-invalid @enderror" required>
                <option value="">— Pilih Jenis —</option>
                @foreach($jenises as $j)
                  <option value="{{ $j->id }}" @selected(old('jenis_id', $customer->jenis_id) == $j->id)>{{ $j->name }}</option>
                @endforeach
              </select>
              @error('jenis_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>

            <div class="col-md-4">
              <label class="form-label">Phone</label>
              <input type="text" name="phone" class="form-control" placeholder="opsional"
                     value="{{ old('phone', $customer->phone) }}">
            </div>

            <div class="col-md-4">
              <label class="form-label">Billing Terms (days)</label>
              <input type="number" name="billing_terms_days" class="form-control" min="0" max="3650"
                     placeholder="mis. 30 (opsional)"
                     value="{{ old('billing_terms_days', $customer->billing_terms_days) }}">
            </div>

            <div class="col-md-4">
              <label class="form-label">Website</label>
              <input type="text" name="website" id="website" class="form-control" placeholder="https:// … (opsional)"
                     value="{{ old('website', $customer->website) }}">
            </div>

            <div class="col-md-12">
              <label class="form-label">Address</label>
              <textarea name="address" id="address" class="form-control" rows="2" placeholder="Alamat lengkap (opsional)">{{ old('address', $customer->address) }}</textarea>
            </div>

            <div class="col-md-4">
              <label class="form-label">City</label>
              <input type="text" name="city" id="city" class="form-control"
                     value="{{ old('city', $customer->city) }}">
            </div>

            <div class="col-md-4">
              <label class="form-label">Province</label>
              <input type="text" name="province" id="province" class="form-control"
                     value="{{ old('province', $customer->province) }}">
            </div>

            <div class="col-md-4">
              <label class="form-label">Country</label>
              <input type="text" name="country" id="country" class="form-control"
                     value="{{ old('country', $customer->country) }}">
            </div>

            {{-- NPWP --}}
            <div class="col-md-4">
              <label class="form-label">NPWP</label>
              <input type="text" name="npwp" class="form-control"
                     value="{{ old('npwp', $customer->npwp) }}">
            </div>

            <div class="col-md-12">
              <label class="form-label">Notes</label>
              <textarea name="notes" class="form-control" rows="2" placeholder="Catatan internal (opsional)">{{ old('notes', $customer->notes) }}</textarea>
            </div>

          </div>
        </div>

        {{-- ================= TAB: BILLING & SHIPPING ================= --}}
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
                <textarea name="billing_street" id="billing_street" class="form-control" rows="3">{{ old('billing_street', $customer->billing_street) }}</textarea>
              </div>
              <div class="row g-2">
                <div class="col-md-6">
                  <label class="form-label">City</label>
                  <input type="text" name="billing_city" id="billing_city" class="form-control" value="{{ old('billing_city', $customer->billing_city) }}">
                </div>
                <div class="col-md-6">
                  <label class="form-label">State</label>
                  <input type="text" name="billing_state" id="billing_state" class="form-control" value="{{ old('billing_state', $customer->billing_state) }}">
                </div>
              </div>
              <div class="row g-2 mt-0">
                <div class="col-md-6">
                  <label class="form-label">Zip Code</label>
                  <input type="text" name="billing_zip" id="billing_zip" class="form-control" value="{{ old('billing_zip', $customer->billing_zip) }}">
                </div>
                <div class="col-md-6">
                  <label class="form-label">Country</label>
                  <input type="text" name="billing_country" id="billing_country" class="form-control" value="{{ old('billing_country', $customer->billing_country) }}">
                </div>
              </div>

              <div class="mt-2">
                <label class="form-label">Billing Notes</label>
                <textarea name="billing_notes" id="billing_notes" class="form-control" rows="2" placeholder="Contoh: penagihan via email, jatuh tempo, dokumen pendukung, dsb.">{{ old('billing_notes', $customer->billing_notes) }}</textarea>
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
                <textarea name="shipping_street" id="shipping_street" class="form-control" rows="3">{{ old('shipping_street', $customer->shipping_street) }}</textarea>
              </div>
              <div class="row g-2">
                <div class="col-md-6">
                  <label class="form-label">City</label>
                  <input type="text" name="shipping_city" id="shipping_city" class="form-control" value="{{ old('shipping_city', $customer->shipping_city) }}">
                </div>
                <div class="col-md-6">
                  <label class="form-label">State</label>
                  <input type="text" name="shipping_state" id="shipping_state" class="form-control" value="{{ old('shipping_state', $customer->shipping_state) }}">
                </div>
              </div>
              <div class="row g-2 mt-0">
                <div class="col-md-6">
                  <label class="form-label">Zip Code</label>
                  <input type="text" name="shipping_zip" id="shipping_zip" class="form-control" value="{{ old('shipping_zip', $customer->shipping_zip) }}">
                </div>
                <div class="col-md-6">
                  <label class="form-label">Country</label>
                  <input type="text" name="shipping_country" id="shipping_country" class="form-control" value="{{ old('shipping_country', $customer->shipping_country) }}">
                </div>
              </div>

              <div class="mt-2">
                <label class="form-label">Shipping Notes</label>
                <textarea name="shipping_notes" id="shipping_notes" class="form-control" rows="2" placeholder="Contoh: kurir langganan, packing, instruksi serah terima, jam operasional, dsb.">{{ old('shipping_notes', $customer->shipping_notes) }}</textarea>
              </div>
            </div>
          </div>
        </div>

      </div> {{-- /tab-content --}}
    </div> {{-- /card-body --}}

    @include('layouts.partials.form_footer', [
      'cancelUrl'    => route('customers.index'),
      'cancelLabel'  => 'Batal',
      'cancelInline' => true, // Batal muncul di kiri Simpan (sejajar)
      'buttons' => [
        ['type' => 'submit', 'label' => 'Simpan', 'class' => 'btn btn-primary'],
      ],
    ])
    </form>

  {{-- ===== CONTACTS (disimpan terpisah dari detail customer) ===== --}}
  <div class="card mt-3">
    <div class="card-header">
      <div class="card-title">Contacts</div>
      <div class="ms-auto small text-muted">Kontak disimpan terpisah dari detail customer.</div>
    </div>
    <div class="card-body">
      {{-- Flash inline untuk AJAX --}}
      <div id="flash-inline" class="d-none"></div>

      {{-- ADD CONTACT (AJAX; fallback POST biasa) --}}
      <form method="post"
            action="{{ route('customers.contacts.store',$customer) }}"
            id="addContactForm"
            class="row g-2 mb-3">
        @csrf
        <div class="col-6 col-md-3">
          <input class="form-control" name="first_name" placeholder="First name" required>
        </div>
        <div class="col-6 col-md-3">
          <input class="form-control" name="last_name" placeholder="Last name">
        </div>
        <div class="col-12 col-md-3">
          <input class="form-control" name="position" placeholder="Position">
        </div>
        <div class="col-12 col-md-3">
          <input class="form-control" name="phone" placeholder="Phone">
        </div>
        <div class="col-12 col-md-6">
          <input class="form-control" name="email" type="email" placeholder="Email">
        </div>
        <div class="col-12">
          <input class="form-control" name="notes" placeholder="Notes">
        </div>
        <div class="col-12">
          <button id="btnAddContact" class="btn btn-success">+ Add Contact</button>
        </div>
      </form>

      <div class="table-responsive">
        <table class="table table-vcenter">
          <thead>
            <tr>
              <th>Name</th>
              <th class="d-none d-md-table-cell">Position</th>
              <th class="d-none d-md-table-cell">Phone</th>
              <th class="d-none d-md-table-cell">Email</th>
              <th class="text-end"></th>
            </tr>
          </thead>
          <tbody id="contactsBody">
            @forelse($customer->contacts()->orderBy('first_name')->get() as $p)
              <tr data-id="{{ $p->id }}">
                <td class="text-wrap contact-name">{{ $p->first_name }} {{ $p->last_name }}</td>
                <td class="d-none d-md-table-cell contact-position">{{ $p->position ?? '-' }}</td>
                <td class="d-none d-md-table-cell contact-phone">{{ $p->phone ?? '-' }}</td>
                <td class="d-none d-md-table-cell contact-email">{{ $p->email ?? '-' }}</td>
                <td class="text-end">
                  <button
                    type="button"
                    class="btn btn-sm btn-warning me-1 btnEditContact"
                    data-bs-toggle="modal" data-bs-target="#editContactModal"
                    data-id="{{ $p->id }}"
                    data-first_name="{{ e($p->first_name) }}"
                    data-last_name="{{ e($p->last_name) }}"
                    data-position="{{ e($p->position) }}"
                    data-phone="{{ e($p->phone) }}"
                    data-email="{{ e($p->email) }}"
                    data-notes="{{ e($p->notes) }}"
                  >Edit</button>

                  <form method="post" action="{{ route('customers.contacts.destroy',[$customer,$p]) }}" onsubmit="return confirm('Hapus kontak ini?')" class="d-inline">
                    @csrf @method('DELETE')
                    <button class="btn btn-sm btn-danger">Delete</button>
                  </form>
                </td>
              </tr>
            @empty
              <tr class="empty-row">
                <td colspan="5" class="text-center text-muted">Belum ada kontak.</td>
              </tr>
            @endforelse
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>

{{-- ===== Modal Edit Contact ===== --}}
<div class="modal modal-blur fade" id="editContactModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Edit Contact</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <form id="editContactForm" method="post" action="#">
        @csrf
        @method('PATCH')
        <div class="modal-body">
          <div class="row g-2">
            <div class="col-6">
              <label class="form-label">First name</label>
              <input class="form-control" name="first_name" required>
            </div>
            <div class="col-6">
              <label class="form-label">Last name</label>
              <input class="form-control" name="last_name">
            </div>
            <div class="col-12">
              <label class="form-label">Position</label>
              <input class="form-control" name="position">
            </div>
            <div class="col-6">
              <label class="form-label">Phone</label>
              <input class="form-control" name="phone">
            </div>
            <div class="col-6">
              <label class="form-label">Email</label>
              <input class="form-control" name="email" type="email">
            </div>
            <div class="col-12">
              <label class="form-label">Notes</label>
              <input class="form-control" name="notes">
            </div>
          </div>
        </div>
        <div class="modal-footer d-flex">
          <button type="button" class="btn btn-outline-secondary me-auto" data-bs-dismiss="modal">Batal</button>
          <button class="btn btn-primary" id="btnSaveEdit" type="submit">Simpan</button>
        </div>
      </form>
    </div>
  </div>
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
/* =========================
   A) DUP-CHECK & GOOGLE PLACES
   ========================= */
(function(){
  // duplicate check (skip kalau nama tidak berubah)
  const nameInput = document.getElementById('cust_name');
  const dupHint   = document.getElementById('dup_hint');
  const original  = nameInput?.dataset.originalName || '';
  const dupUrl    = "{{ route('customers.dup-check') }}";
  let t = null;

  function dupCheck(v){
    if (!dupHint) return;
    if (!v || v.trim()===''){ dupHint.textContent=''; return; }
    if (original && v.trim() === original.trim()){ dupHint.textContent=''; return; }
    fetch(dupUrl + '?name=' + encodeURIComponent(v), { headers: { 'X-Requested-With':'XMLHttpRequest' }})
      .then(r => r.ok ? r.json() : {exists:false})
      .then(j => {
        dupHint.innerHTML = j.exists
          ? '<span class="text-warning">Nama ini sudah ada di database. Anda tetap bisa menyimpan.</span>'
          : '<span class="text-success">Nama belum terdaftar.</span>';
      })
      .catch(()=>{});
  }
  nameInput?.addEventListener('input', () => { clearTimeout(t); t=setTimeout(()=>dupCheck(nameInput.value), 500); });

  // Google Places proxy
  const btn   = document.getElementById('places_btn');
  const q     = document.getElementById('places_q');
  const list  = document.getElementById('places_results');
  const url   = "{{ route('places.search') }}";

  function escapeHtml(s){
    return String(s||'').replace(/[&<>"']/g, m => (
      {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[m]
    ));
  }
  function render(items){
    if (!list) return;
    list.innerHTML = '';
    if (!Array.isArray(items) || items.length === 0){
      list.innerHTML = '<div class="list-group-item text-muted">Tidak ada hasil.</div>'; return;
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
            ${it.phone   ? `<div class="small">Phone: ${escapeHtml(it.phone)}</div>`   : ''}
          </div>
          <div><button type="button" class="btn btn-sm btn-primary pickPlace">Pilih</button></div>
        </div>`;
      li.dataset.payload = JSON.stringify(it);
      list.appendChild(li);
    });
  }
  function search(){
    const term = q?.value.trim();
    if (!term){ q?.focus(); return; }
    if (list) list.innerHTML = '<div class="list-group-item">Mencari…</div>';
    fetch(url + '?q=' + encodeURIComponent(term), { headers: { 'X-Requested-With':'XMLHttpRequest' }})
      .then(r => r.ok ? r.json() : {items:[]})
      .then(j => render(j.items || []))
      .catch(()=>{ if(list) list.innerHTML = '<div class="list-group-item text-danger">Gagal memuat hasil.</div>'; });
  }
  btn?.addEventListener('click', search);
  q?.addEventListener('keydown', e => { if (e.key==='Enter'){ e.preventDefault(); search(); }});

  function splitAddress(addr){
    const parts = (addr || '').split(',').map(p => p.trim()).filter(Boolean);
    const n = parts.length;
    return { city: n>=3?parts[n-3]:'', province: n>=2?parts[n-2]:'', country: n>=1?parts[n-1]:'' };
  }

  list?.addEventListener('click', (e) => {
    const pick = e.target.closest('.pickPlace'); if (!pick) return;
    const row = e.target.closest('.list-group-item');
    let data = {}; try { data = JSON.parse(row.dataset.payload || '{}'); } catch(_){}
    const addr = data.address || data.formatted_address || '';

    if (data.name) document.getElementById('cust_name').value = data.name;
    if (addr)      document.getElementById('address').value  = addr;
    if (data.website) document.getElementById('website').value = data.website;

    const phoneField = document.querySelector('input[name="phone"]');
    if (data.phone && phoneField && !phoneField.value) phoneField.value = data.phone;

    const geo = splitAddress(addr);
    const cityF=document.getElementById('city'), provF=document.getElementById('province'), ctryF=document.getElementById('country');
    if (cityF && !cityF.value) cityF.value = geo.city;
    if (provF && !provF.value) provF.value = geo.province;
    if (ctryF && !ctryF.value) ctryF.value = geo.country;

    const modalEl = document.getElementById('placesModal');
    const m = modalEl && window.bootstrap ? bootstrap.Modal.getOrCreateInstance(modalEl) : null;
    m && m.hide();
    dupCheck(document.getElementById('cust_name').value);
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

<script>
/* =========================
   B) CONTACTS (Add + Edit + Delete) – Data-API friendly
   ========================= */
(function () {
  const formAdd   = document.getElementById('addContactForm');
  const btnAdd    = document.getElementById('btnAddContact');
  const body      = document.getElementById('contactsBody');
  const flashBox  = document.getElementById('flash-inline');

  const editModalEl = document.getElementById('editContactModal');
  const formEdit    = document.getElementById('editContactForm');
  const btnSaveEdit = document.getElementById('btnSaveEdit');

  if (!body) return;

  const destroyBase = "{{ route('customers.contacts.destroy', [$customer, 0]) }}"; // .../contacts/0
  const updateBase  = "{{ route('customers.contacts.update',  [$customer, 0]) }}"; // .../contacts/0
  let editingRow = null;

  function flash(msg, type='success'){
    if (!flashBox) { alert(msg); return; }
    flashBox.className = 'alert alert-' + type;
    flashBox.textContent = msg;
    flashBox.classList.remove('d-none');
    setTimeout(() => { flashBox.classList.add('d-none'); }, 3000);
  }
  function esc(s){ return String(s ?? '').replace(/[&<>"]/g, m => ({'&':'&amp;','<':'&lt;','>':'&gt;'}[m])); }
  function dash(s){ return s ? esc(s) : '-'; }

  // ------ ADD via AJAX ------
  if (formAdd) {
    formAdd.addEventListener('submit', function(e){
      e.preventDefault();
      const fd = new FormData(formAdd);
      if (btnAdd) btnAdd.disabled = true;

      fetch(formAdd.action, {
        method: 'POST',
        headers: {
          'X-Requested-With': 'XMLHttpRequest',
          'X-CSRF-TOKEN': '{{ csrf_token() }}',
          'Accept': 'application/json'
        },
        body: fd
      })
      .then(async r => {
        if (!r.ok) {
          let msg = 'Gagal menambah kontak. Periksa input.';
          try { const j = await r.json(); if (j.message) msg = j.message; } catch(_){}
          throw new Error(msg);
        }
        return r.json();
      })
      .then(j => {
        if (!j || j.ok !== true) throw new Error('Gagal menambah kontak.');
        const c = j.contact || {};
        const destroyUrl = destroyBase.replace(/\/0$/, '/' + c.id);

        const empty = body.querySelector('.empty-row'); if (empty) empty.remove();

        const tr = document.createElement('tr');
        tr.setAttribute('data-id', c.id);
        tr.innerHTML = `
          <td class="text-wrap contact-name">${esc(c.first_name)} ${esc(c.last_name ?? '')}</td>
          <td class="d-none d-md-table-cell contact-position">${dash(c.position)}</td>
          <td class="d-none d-md-table-cell contact-phone">${dash(c.phone)}</td>
          <td class="d-none d-md-table-cell contact-email">${dash(c.email)}</td>
          <td class="text-end">
            <button type="button" class="btn btn-sm btn-warning me-1 btnEditContact"
                    data-bs-toggle="modal" data-bs-target="#editContactModal"
                    data-id="${c.id}"
                    data-first_name="${esc(c.first_name)}"
                    data-last_name="${esc(c.last_name ?? '')}"
                    data-position="${esc(c.position ?? '')}"
                    data-phone="${esc(c.phone ?? '')}"
                    data-email="${esc(c.email ?? '')}"
                    data-notes="${esc(c.notes ?? '')}">Edit</button>
            <form method="post" action="${destroyUrl}" onsubmit="return confirm('Hapus kontak ini?')" class="d-inline">
              <input type="hidden" name="_token" value="{{ csrf_token() }}">
              <input type="hidden" name="_method" value="DELETE">
              <button class="btn btn-sm btn-danger">Delete</button>
            </form>
          </td>`;
        body.prepend(tr);
        formAdd.reset();
        flash('Contact added.');
      })
      .catch(err => flash(err.message || 'Terjadi kesalahan.', 'danger'))
      .finally(() => { if (btnAdd) btnAdd.disabled = false; });
    });
  }

  // ------ OPEN EDIT (Data-API) ------
  editModalEl?.addEventListener('show.bs.modal', function (ev) {
    const btn = ev.relatedTarget;  // tombol .btnEditContact
    if (!btn || !formEdit) return;

    editingRow = btn.closest('tr');

    formEdit.first_name.value = btn.getAttribute('data-first_name') || '';
    formEdit.last_name.value  = btn.getAttribute('data-last_name')  || '';
    formEdit.position.value   = btn.getAttribute('data-position')   || '';
    formEdit.phone.value      = btn.getAttribute('data-phone')      || '';
    formEdit.email.value      = btn.getAttribute('data-email')      || '';
    formEdit.notes.value      = btn.getAttribute('data-notes')      || '';

    formEdit.action = updateBase.replace(/\/0$/, '/' + btn.getAttribute('data-id'));
  });

  // ------ SUBMIT EDIT (AJAX) ------
  formEdit?.addEventListener('submit', async function(e){
    e.preventDefault();
    if (!editingRow) return;

    const fd = new FormData(formEdit);
    if (btnSaveEdit) btnSaveEdit.disabled = true;

    try {
      const resp = await fetch(formEdit.action, {
        method: 'POST', // override via @method('PATCH') di form
        headers: {
          'X-Requested-With': 'XMLHttpRequest',
          'X-CSRF-TOKEN': '{{ csrf_token() }}',
          'Accept': 'application/json'
        },
        body: fd
      });

      if (!resp.ok) {
        let msg = 'Gagal menyimpan perubahan.';
        try { const j = await resp.json(); if (j.message) msg = j.message; } catch(_){}
        throw new Error(msg);
      }

      const j = await resp.json();
      if (!j || j.ok !== true) throw new Error('Gagal menyimpan perubahan.');
      const c = j.contact || {};

      editingRow.querySelector('.contact-name').textContent     = `${c.first_name ?? ''} ${c.last_name ?? ''}`.trim();
      editingRow.querySelector('.contact-position').textContent = c.position ? c.position : '-';
      editingRow.querySelector('.contact-phone').textContent    = c.phone    ? c.phone    : '-';
      editingRow.querySelector('.contact-email').textContent    = c.email    ? c.email    : '-';

      const btn = editingRow.querySelector('.btnEditContact');
      if (btn){
        btn.setAttribute('data-first_name', c.first_name ?? '');
        btn.setAttribute('data_last_name',  c.last_name  ?? '');
        btn.setAttribute('data-position',   c.position   ?? '');
        btn.setAttribute('data-phone',      c.phone      ?? '');
        btn.setAttribute('data-email',      c.email      ?? '');
        btn.setAttribute('data-notes',      c.notes      ?? '');
      }

      try {
        const inst = (window.bootstrap?.Modal.getInstance(editModalEl))
                  || (window.bootstrap?.Modal?.getOrCreateInstance?.(editModalEl));
        inst ? inst.hide() : editModalEl.querySelector('[data-bs-dismiss="modal"]')?.click();
      } catch(_) {
        editModalEl.querySelector('[data-bs-dismiss="modal"]')?.click();
      }

      flash('Contact updated.');
    } catch (err) {
      flash(err.message || 'Terjadi kesalahan.', 'danger');
    } finally {
      if (btnSaveEdit) btnSaveEdit.disabled = false;
    }
  });
})();
</script>
@endpush

