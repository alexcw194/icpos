@extends('layouts.tabler')

@section('content')
<div class="container-xl">
  <div class="card">
    <div class="card-header">
      <h3 class="card-title">Colors</h3>
        <div class="ms-auto btn-list">
          <a href="{{ request('r', url()->previous()) }}" class="btn btn-light">← Back</a>
          <a href="{{ route('colors.create', ['modal'=>1]) }}" class="btn btn-primary ms-auto" data-modal>
            + Add Color
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
              <th class="d-none d-md-table-cell">Color</th>
              <th class="text-center w-1">Order</th>
              <th class="d-none d-md-table-cell">Active</th>
              <th>Description</th>
              <th class="w-1"></th>
            </tr>
          </thead>
          <tbody>
            @forelse($colors as $color)
              <tr>
                <td class="fw-medium">{{ $color->name }}</td>
                <td class="d-none d-md-table-cell text-muted">{{ $color->slug }}</td>
                <td class="d-none d-md-table-cell">
                  @if($color->hex)
                    <span class="d-inline-flex align-items-center">
                      <i style="display:inline-block;width:14px;height:14px;border-radius:50%;
                                border:1px solid #ddd;background:{{ $color->hex }}" class="me-2"></i>
                      {{ $color->hex }}
                    </span>
                  @else
                    —
                  @endif
                </td>
                <td class="text-center text-muted">{{ $color->sort_order ?? 0 }}</td>
                <td class="d-none d-md-table-cell">
                  @if($color->is_active)
                    <span class="badge bg-success">Active</span>
                  @else
                    <span class="badge bg-secondary">Inactive</span>
                  @endif
                </td>
                <td class="text-truncate" style="max-width: 360px;">
                  {{ $color->description ?: '—' }}
                </td>
                <td class="text-end">
                  <a href="{{ route('colors.edit', [$color, 'modal'=>1]) }}" class="btn btn-sm btn-warning" data-modal>
                    Edit
                  </a>
                  <form action="{{ route('colors.destroy', $color) }}" method="POST" class="d-inline"
                        onsubmit="return confirm('Delete this color?')">
                    @csrf @method('DELETE')
                    <button class="btn btn-sm btn-danger">Delete</button>
                  </form>
                </td>
              </tr>
            @empty
              <tr>
                <td colspan="7" class="text-center text-muted">Belum ada data.</td>
              </tr>
            @endforelse
          </tbody>
        </table>
      </div>

      <div class="mt-3">{{ $colors->links() }}</div>
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
    if (e.target && e.target.id === 'colorModalForm'){
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
