{{-- resources/views/layouts/partials/crud_actions.blade.php --}}
{{-- Seragam: View / Edit / Delete (Enterprise: desktop tombol, mobile overflow) --}}
@php
  $view    = $view    ?? null;   // url
  $edit    = $edit    ?? null;   // url
  $delete  = $delete  ?? null;   // url (route destroy)
  $size    = ($size ?? 'sm') === 'sm' ? 'btn-sm' : '';
  $confirm = $confirm ?? 'Hapus data ini?';

  $uid = 'act_' . uniqid();
@endphp

{{-- DESKTOP: tombol --}}
<div class="btn-list d-none d-md-inline-flex">
  @if($view)
    <a href="{{ $view }}" class="btn {{ $size }} btn-outline-secondary">Lihat</a>
  @endif

  @if($edit)
    <a href="{{ $edit }}" class="btn {{ $size }} btn-outline-primary">Ubah</a>
  @endif

  @if($delete)
    <form action="{{ $delete }}" method="POST" class="d-inline"
          onsubmit="return confirm(@js($confirm));">
      @csrf
      @method('DELETE')
      <button type="submit" class="btn {{ $size }} btn-outline-danger">Hapus</button>
    </form>
  @endif
</div>

{{-- MOBILE: 1 icon overflow --}}
<div class="d-md-none">
  <div class="dropdown">
    <button class="btn btn-icon btn-sm btn-outline-secondary" type="button"
            data-bs-toggle="dropdown" aria-expanded="false" aria-label="Aksi">
      <svg xmlns="http://www.w3.org/2000/svg" class="icon" width="20" height="20" viewBox="0 0 24 24"
           stroke-width="2" stroke="currentColor" fill="none" stroke-linecap="round" stroke-linejoin="round"
           aria-hidden="true">
        <path stroke="none" d="M0 0h24v24H0z" fill="none"></path>
        <circle cx="5" cy="12" r="1"></circle>
        <circle cx="12" cy="12" r="1"></circle>
        <circle cx="19" cy="12" r="1"></circle>
      </svg>
    </button>

    <div class="dropdown-menu dropdown-menu-end">
      @if($view)
        <a href="{{ $view }}" class="dropdown-item">Lihat</a>
      @endif

      @if($edit)
        <a href="{{ $edit }}" class="dropdown-item">Ubah</a>
      @endif

      @if($delete)
        <form action="{{ $delete }}" method="POST"
              onsubmit="return confirm(@js($confirm));">
          @csrf
          @method('DELETE')
          <button type="submit" class="dropdown-item text-danger">Hapus</button>
        </form>
      @endif
    </div>
  </div>
</div>
