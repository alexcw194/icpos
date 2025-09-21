@php
  $contacts = $customer->contacts()->orderBy('first_name')->get();
@endphp

<div class="card" id="contacts">
  <div class="card-header">
    <div class="card-title">Contacts</div>
  </div>
  <div class="card-body">
    <form id="contactForm"
          action="{{ route('customers.contacts.store', $customer) }}"
          method="POST"
          class="row g-2 align-items-end">
      @csrf
      <div class="col-md-3">
        <label class="form-label">First name</label>
        <input name="first_name" class="form-control" required>
      </div>
      <div class="col-md-3">
        <label class="form-label">Last name</label>
        <input name="last_name" class="form-control">
      </div>
      <div class="col-md-3">
        <label class="form-label">Position</label>
        <input name="position" class="form-control">
      </div>
      <div class="col-md-3">
        <label class="form-label">Phone</label>
        <input name="phone" class="form-control">
      </div>
      <div class="col-md-4">
        <label class="form-label">Email</label>
        <input type="email" name="email" class="form-control">
      </div>
      <div class="col-md-6">
        <label class="form-label">Notes</label>
        <input name="notes" class="form-control">
      </div>
      <div class="col-md-2 d-grid">
        <button class="btn btn-primary" type="submit">+ Add Contact</button>
      </div>
    </form>

    <div class="table-responsive mt-3">
      <table class="table">
        <thead class="table-light">
          <tr>
            <th>Name</th><th>Position</th><th>Phone</th><th>Email</th>
          </tr>
        </thead>
        <tbody id="contactsRows">
          @forelse($contacts as $c)
            <tr>
              <td>{{ $c->first_name }} {{ $c->last_name }}</td>
              <td>{{ $c->position }}</td>
              <td>{{ $c->phone }}</td>
              <td>{{ $c->email }}</td>
            </tr>
          @empty
            <tr data-empty><td colspan="4" class="text-muted">Belum ada kontak.</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>

    <small class="text-muted">Anda bisa menambah kontak tanpa menyimpan perubahan di form atas.</small>
  </div>
</div>

@push('scripts')
<script>
(() => {
  const f = document.getElementById('contactForm');
  if (!f) return;
  const rows = document.getElementById('contactsRows');

  function escapeHTML(s){return String(s??'').replace(/[&<>"']/g,m=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[m]));}
  function rowHtml(c){
    const full = [c.first_name, c.last_name||''].join(' ').trim();
    return `<tr>
      <td>${escapeHTML(full)}</td>
      <td>${escapeHTML(c.position||'')}</td>
      <td>${escapeHTML(c.phone||'')}</td>
      <td>${escapeHTML(c.email||'')}</td>
    </tr>`;
  }

  f.addEventListener('submit', async (e) => {
    e.preventDefault();
    const fd = new FormData(f);
    try{
      const r = await fetch(f.action, {
        method: 'POST',
        headers: { 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': fd.get('_token') },
        body: fd
      });
      const j = await r.json();
      if (j && j.ok) {
        if (rows.querySelector('[data-empty]')) rows.innerHTML = '';
        rows.insertAdjacentHTML('afterbegin', rowHtml(j.contact));
        f.reset();
        f.querySelector('[name="first_name"]').focus();
        return;
      }
    }catch(_){}
    // fallback non-AJAX (kalau JSON gagal)
    f.submit();
  });
})();
</script>
@endpush
