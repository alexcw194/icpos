{{-- resources/views/items/_modal_create.blade.php --}}
@php
  $isProjectItems = request()->routeIs('project-items.*');
  $modalTitle = $isProjectItems ? 'Tambah Project Item' : 'Tambah Item';
  $formAction = $isProjectItems ? route('project-items.store') : route('items.store');
@endphp

<div class="modal-dialog modal-lg modal-dialog-scrollable">
  <div class="modal-content">
    <div class="modal-header">
      <h5 class="modal-title">{{ $modalTitle }}</h5>
      <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
    </div>

    <form
      id="itemModalForm"
      method="POST"
      action="{{ $formAction }}"
    >
      @csrf

      <input type="hidden" name="modal" value="1">

      {{-- r cukup sekali (pilih salah satu). Kalau _form sudah punya r hidden, HAPUS baris ini --}}
      <input type="hidden" name="r" value="{{ request('r') }}">

      <div class="modal-body">
        @include('items._form', ['item' => $item])
      </div>

      <div class="modal-footer">
        <button type="button" class="btn btn-link" data-bs-dismiss="modal">Batal</button>
        <button type="submit" name="action" value="save" class="btn btn-primary">Simpan</button>
      </div>
    </form>

  </div>
</div>
