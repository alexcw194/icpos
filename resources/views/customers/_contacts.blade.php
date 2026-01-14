@php
  $contacts = $customer->contacts()->orderBy('first_name')->get();
  $contactTitles = $contactTitles ?? \App\Models\ContactTitle::active()->ordered()->get(['id','name']);
  $contactPositions = $contactPositions ?? \App\Models\ContactPosition::active()->ordered()->get(['id','name']);
@endphp

<div class="card" id="contacts">
  <div class="card-header">
    <div class="card-title">Kontak</div>
  </div>
  <div class="card-body">
    <div id="contactsAlert" class="alert alert-success d-none"></div>

    <form id="contactForm"
          action="{{ route('customers.contacts.store', $customer) }}"
          method="POST"
          class="row g-2 align-items-end">
      @csrf
      <div class="col-md-2">
        <label class="form-label">Sapaan</label>
        <select name="contact_title_id" class="form-select">
          <option value="">-</option>
          @foreach($contactTitles as $title)
            <option value="{{ $title->id }}">{{ $title->name }}</option>
          @endforeach
        </select>
      </div>
      <div class="col-md-3">
        <label class="form-label">Nama Depan</label>
        <input name="first_name" class="form-control" required>
      </div>
      <div class="col-md-3">
        <label class="form-label">Nama Belakang</label>
        <input name="last_name" class="form-control">
      </div>
      <div class="col-md-3">
        <label class="form-label">Jabatan</label>
        <select name="contact_position_id" class="form-select">
          <option value="">-</option>
          @foreach($contactPositions as $pos)
            <option value="{{ $pos->id }}">{{ $pos->name }}</option>
          @endforeach
        </select>
      </div>
      <div class="col-md-3">
        <label class="form-label">Telepon</label>
        <input name="phone" class="form-control">
      </div>
      <div class="col-md-4">
        <label class="form-label">Email</label>
        <input type="email" name="email" class="form-control">
      </div>
      <div class="col-md-6">
        <label class="form-label">Catatan</label>
        <input name="notes" class="form-control">
      </div>
      <div class="col-md-2 d-grid">
        <button class="btn btn-primary" type="submit">+ Tambah Kontak</button>
      </div>
    </form>

    <div class="table-responsive mt-3">
      <table class="table table-sm table-vcenter">
        <thead class="table-light">
          <tr>
            <th>SAPAAN</th>
            <th>NAMA</th>
            <th>JABATAN</th>
            <th>TELEPON</th>
            <th>EMAIL</th>
            <th>CATATAN</th>
            <th class="text-end">AKSI</th>
          </tr>
        </thead>
        <tbody id="contactsRows">
          @forelse($contacts as $c)
            @php
              $fullName = trim(($c->first_name ?? '') . ' ' . ($c->last_name ?? ''));
            @endphp
            <tr data-contact-id="{{ $c->id }}">
              <td class="contact-title">{{ $c->title_label ?: '-' }}</td>
              <td class="contact-name">{{ $fullName }}</td>
              <td class="contact-position">{{ $c->position_label ?: '-' }}</td>
              <td class="contact-phone">{{ $c->phone }}</td>
              <td class="contact-email">{{ $c->email }}</td>
              <td class="contact-notes">{{ $c->notes }}</td>
              <td class="text-end">
                <div class="btn-group btn-group-sm">
                  <button type="button"
                          class="btn btn-outline-primary btn-edit-contact"
                          data-id="{{ $c->id }}"
                          data-title-id="{{ $c->contact_title_id }}"
                          data-position-id="{{ $c->contact_position_id }}"
                          data-first-name="{{ $c->first_name }}"
                          data-last-name="{{ $c->last_name }}"
                          data-title-label="{{ $c->title_label }}"
                          data-position-label="{{ $c->position_label }}"
                          data-phone="{{ $c->phone }}"
                          data-email="{{ $c->email }}"
                          data-notes="{{ $c->notes }}"
                          data-update-url="{{ route('customers.contacts.update', [$customer, $c]) }}">
                    Ubah
                  </button>
                  <button type="button"
                          class="btn btn-outline-danger btn-delete-contact"
                          data-delete-url="{{ route('customers.contacts.destroy', [$customer, $c]) }}">
                    Hapus
                  </button>
                </div>
              </td>
            </tr>
          @empty
            <tr data-empty><td colspan="7" class="text-muted">Belum ada kontak.</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>

    <small class="text-muted">Anda bisa menambah kontak tanpa menyimpan perubahan di form atas.</small>
  </div>
</div>

<div class="modal modal-blur fade" id="contactEditModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <form id="contactEditForm" method="POST">
        @csrf
        <div class="modal-header">
          <h5 class="modal-title">Ubah Kontak</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
          <div class="row g-3">
            <div class="col-md-6">
              <label class="form-label">Sapaan</label>
              <select name="contact_title_id" class="form-select">
                <option value="">-</option>
                @foreach($contactTitles as $title)
                  <option value="{{ $title->id }}">{{ $title->name }}</option>
                @endforeach
              </select>
            </div>
            <div class="col-md-6">
              <label class="form-label">Nama Depan</label>
              <input type="text" name="first_name" class="form-control" required>
            </div>
            <div class="col-md-6">
              <label class="form-label">Nama Belakang</label>
              <input type="text" name="last_name" class="form-control">
            </div>
            <div class="col-md-6">
              <label class="form-label">Jabatan</label>
              <select name="contact_position_id" class="form-select">
                <option value="">-</option>
                @foreach($contactPositions as $pos)
                  <option value="{{ $pos->id }}">{{ $pos->name }}</option>
                @endforeach
              </select>
            </div>
            <div class="col-md-6">
              <label class="form-label">Telepon</label>
              <input type="text" name="phone" class="form-control">
            </div>
            <div class="col-md-6">
              <label class="form-label">Email</label>
              <input type="email" name="email" class="form-control">
            </div>
            <div class="col-md-6">
              <label class="form-label">Catatan</label>
              <input type="text" name="notes" class="form-control">
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Batal</button>
          <button type="submit" class="btn btn-primary">Simpan</button>
        </div>
      </form>
    </div>
  </div>
</div>

@push('scripts')
<script>
(() => {
  const addForm = document.getElementById('contactForm');
  const rows = document.getElementById('contactsRows');
  const alertBox = document.getElementById('contactsAlert');
  const modalEl = document.getElementById('contactEditModal');
  const editForm = document.getElementById('contactEditForm');

  if (!addForm || !rows || !modalEl || !editForm) return;

  const modal = window.bootstrap ? bootstrap.Modal.getOrCreateInstance(modalEl) : null;

  function escapeHTML(s){
    return String(s ?? '').replace(/[&<>"']/g, m => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[m]));
  }

  function showAlert(message, type = 'success') {
    if (!alertBox) return;
    alertBox.textContent = message;
    alertBox.className = `alert alert-${type}`;
    alertBox.classList.remove('d-none');
    setTimeout(() => alertBox.classList.add('d-none'), 2500);
  }

  function rowHtml(c, urls) {
    const full = [c.first_name, c.last_name || ''].join(' ').trim();
    const titleLabel = c.title_label || '';
    const positionLabel = c.position_label || '';
    return `<tr data-contact-id="${escapeHTML(c.id)}">
      <td class="contact-title">${escapeHTML(titleLabel || '-') }</td>
      <td class="contact-name">${escapeHTML(full)}</td>
      <td class="contact-position">${escapeHTML(positionLabel || '-')}</td>
      <td class="contact-phone">${escapeHTML(c.phone || '')}</td>
      <td class="contact-email">${escapeHTML(c.email || '')}</td>
      <td class="contact-notes">${escapeHTML(c.notes || '')}</td>
      <td class="text-end">
        <div class="btn-group btn-group-sm">
          <button type="button"
                  class="btn btn-outline-primary btn-edit-contact"
                  data-id="${escapeHTML(c.id)}"
                  data-title-id="${escapeHTML(c.contact_title_id || '')}"
                  data-position-id="${escapeHTML(c.contact_position_id || '')}"
                  data-title-label="${escapeHTML(titleLabel)}"
                  data-position-label="${escapeHTML(positionLabel)}"
                  data-first-name="${escapeHTML(c.first_name || '')}"
                  data-last-name="${escapeHTML(c.last_name || '')}"
                  data-phone="${escapeHTML(c.phone || '')}"
                  data-email="${escapeHTML(c.email || '')}"
                  data-notes="${escapeHTML(c.notes || '')}"
                  data-update-url="${escapeHTML(urls.update_url)}">Ubah</button>
          <button type="button"
                  class="btn btn-outline-danger btn-delete-contact"
                  data-delete-url="${escapeHTML(urls.delete_url)}">Hapus</button>
        </div>
      </td>
    </tr>`;
  }

  function updateRow(contact) {
    const row = rows.querySelector(`[data-contact-id="${contact.id}"]`);
    if (!row) return;
    const full = [contact.first_name, contact.last_name || ''].join(' ').trim();
    row.querySelector('.contact-title').textContent = contact.title_label || '-';
    row.querySelector('.contact-name').textContent = full;
    row.querySelector('.contact-position').textContent = contact.position_label || '-';
    row.querySelector('.contact-phone').textContent = contact.phone || '';
    row.querySelector('.contact-email').textContent = contact.email || '';
    row.querySelector('.contact-notes').textContent = contact.notes || '';
    const editBtn = row.querySelector('.btn-edit-contact');
    if (editBtn) {
      editBtn.dataset.titleId = contact.contact_title_id || '';
      editBtn.dataset.positionId = contact.contact_position_id || '';
      editBtn.dataset.titleLabel = contact.title_label || '';
      editBtn.dataset.positionLabel = contact.position_label || '';
      editBtn.dataset.firstName = contact.first_name || '';
      editBtn.dataset.lastName = contact.last_name || '';
      editBtn.dataset.phone = contact.phone || '';
      editBtn.dataset.email = contact.email || '';
      editBtn.dataset.notes = contact.notes || '';
    }
  }

  addForm.addEventListener('submit', async (e) => {
    e.preventDefault();
    const fd = new FormData(addForm);
    try {
      const r = await fetch(addForm.action, {
        method: 'POST',
        headers: { 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': fd.get('_token') },
        body: fd
      });
      const j = await r.json();
      if (j && j.ok) {
        if (rows.querySelector('[data-empty]')) rows.innerHTML = '';
        rows.insertAdjacentHTML('afterbegin', rowHtml(j.contact, j.urls));
        addForm.reset();
        addForm.querySelector('[name="first_name"]').focus();
        showAlert('Kontak berhasil ditambahkan.');
        return;
      }
    } catch (_) {}
    addForm.submit();
  });

  document.addEventListener('click', (e) => {
    const editBtn = e.target.closest('.btn-edit-contact');
    if (editBtn) {
      editForm.action = editBtn.dataset.updateUrl || '';
      editForm.querySelector('[name="contact_title_id"]').value = editBtn.dataset.titleId || '';
      editForm.querySelector('[name="first_name"]').value = editBtn.dataset.firstName || '';
      editForm.querySelector('[name="last_name"]').value = editBtn.dataset.lastName || '';
      editForm.querySelector('[name="contact_position_id"]').value = editBtn.dataset.positionId || '';
      editForm.querySelector('[name="phone"]').value = editBtn.dataset.phone || '';
      editForm.querySelector('[name="email"]').value = editBtn.dataset.email || '';
      editForm.querySelector('[name="notes"]').value = editBtn.dataset.notes || '';
      if (modal) modal.show();
      return;
    }

    const deleteBtn = e.target.closest('.btn-delete-contact');
    if (deleteBtn) {
      const url = deleteBtn.dataset.deleteUrl;
      if (!url) return;
      if (!confirm('Hapus kontak ini?')) return;
      fetch(url, {
        method: 'POST',
        headers: {
          'X-Requested-With': 'XMLHttpRequest',
          'X-CSRF-TOKEN': addForm.querySelector('[name="_token"]').value
        },
        body: new URLSearchParams({ _method: 'DELETE' })
      })
        .then((r) => r.json())
        .then((j) => {
          if (j && j.ok) {
            const row = deleteBtn.closest('tr');
            row?.remove();
            if (!rows.querySelector('tr')) {
              rows.innerHTML = '<tr data-empty><td colspan="7" class="text-muted">Belum ada kontak.</td></tr>';
            }
            showAlert('Kontak berhasil dihapus.');
          } else {
            showAlert('Gagal menghapus kontak.', 'danger');
          }
        })
        .catch(() => showAlert('Gagal menghapus kontak.', 'danger'));
    }
  });

  editForm.addEventListener('submit', async (e) => {
    e.preventDefault();
    const fd = new FormData(editForm);
    fd.append('_method', 'PATCH');
    try {
      const r = await fetch(editForm.action, {
        method: 'POST',
        headers: { 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': fd.get('_token') },
        body: fd
      });
      const j = await r.json();
      if (j && j.ok) {
        updateRow(j.contact);
        if (modal) modal.hide();
        showAlert('Kontak berhasil diperbarui.');
        return;
      }
      showAlert('Gagal menyimpan perubahan.', 'danger');
    } catch (_) {
      showAlert('Gagal menyimpan perubahan.', 'danger');
    }
  });
})();
</script>
@endpush
