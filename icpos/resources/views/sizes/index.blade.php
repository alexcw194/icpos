@extends('layouts.tabler')

@section('content')
<div class="container-xl">
  <div class="card">
    <div class="card-header">
      <h3 class="card-title">Sizes</h3>
      <div class="ms-auto btn-list">
        <a href="{{ request('r', url()->previous()) }}" class="btn btn-light">← Back</a>
        <a href="{{ route('sizes.create', ['modal'=>1]) }}{{ request()->has('r') ? '&r='.urlencode(request('r')) : '' }}" class="btn btn-primary" data-modal>
          + Add Size
        </a>
      </div>
    </div>

    <div class="card-body">
      @if(session('ok'))    <div class="alert alert-success js-flash">{{ session('ok') }}</div> @endif
      @if(session('error')) <div class="alert alert-danger js-flash">{{ session('error') }}</div> @endif

      <div class="table-responsive">
        <table class="table card-table table-vcenter text-nowrap">
          <thead>
            <tr>
              <th>Name</th>
              <th class="d-none d-md-table-cell">Slug</th>
              <th class="text-center w-1">Order</th>
              <th class="d-none d-md-table-cell">Active</th>
              <th>Description</th>
              <th class="w-1"></th>
            </tr>
          </thead>
          <tbody>
            @forelse($sizes as $size)
              <tr>
                <td class="fw-medium">{{ $size->name }}</td>
                <td class="d-none d-md-table-cell text-muted">{{ $size->slug }}</td>
                <td class="text-center text-muted">{{ $size->sort_order ?? 0 }}</td>
                <td class="d-none d-md-table-cell">
                  @if($size->is_active)
                    <span class="badge bg-success">Active</span>
                  @else
                    <span class="badge bg-secondary">Inactive</span>
                  @endif
                </td>
                <td class="text-truncate" style="max-width: 360px;">
                  {{ $size->description ?: '—' }}
                </td>
                <td class="text-end">
                  <a href="{{ route('sizes.edit', [$size, 'modal'=>1]) }}" class="btn btn-sm btn-warning" data-modal>Edit</a>
                  <form action="{{ route('sizes.destroy', $size) }}" method="POST" class="d-inline"
                        onsubmit="return confirm('Delete this size?')">
                    @csrf @method('DELETE')
                    <button class="btn btn-sm btn-danger">Delete</button>
                  </form>
                </td>
              </tr>
            @empty
              <tr>
                <td colspan="6" class="text-center text-muted">Belum ada data.</td>
              </tr>
            @endforelse
          </tbody>
        </table>
      </div>

      <div class="mt-3">{{ $sizes->links() }}</div>
    </div>
  </div>
</div>

@push('scripts')
<script>
(function(){
  let modalEl, modal;
  function ensureModal(){
    if (!modalEl){
      modalEl = document.createElement('div');
      modalEl.className = 'modal fade';
      modalEl.id = 'adminModal';
      modalEl.tabIndex = -1;
      modalEl.innerHTML =
        '<div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">' +
          '<div class="modal-content" id="adminModalContent"></div>' +
        '</div>';
      document.body.appendChild(modalEl);
      modal = new bootstrap.Modal(modalEl);
    }
  }
  async function openModal(url){
    ensureModal();
    const res = await fetch(url, {headers:{'X-Requested-With':'XMLHttpRequest'}});
    const html = await res.text();
    document.getElementById('adminModalContent').innerHTML = html;
    modal.show();
  }
  document.addEventListener('click', function(e){
    const a = e.target.closest('[data-modal]');
    if (a){
      e.preventDefault();
      openModal(a.getAttribute('href'));
    }
  });
  document.addEventListener('submit', async function(e){
    if (e.target && e.target.id === 'sizeModalForm'){
      e.preventDefault();
      const form = e.target;
      const res = await fetch(form.action, {method:'POST', body:new FormData(form), headers:{'X-Requested-With':'XMLHttpRequest'}});
      if (res.redirected) { window.location = res.url; return; }
      window.location.reload();
    }
  });
})();
</script>
@endpush
@endsection
