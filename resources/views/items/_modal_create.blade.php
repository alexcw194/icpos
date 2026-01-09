{{-- resources/views/items/_modal_create.blade.php --}}
<div class="modal-dialog modal-lg modal-dialog-scrollable">
  <div class="modal-content">
    <div class="modal-header">
      <h5 class="modal-title">Add Item</h5>
      <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
    </div>

    <form
      id="itemModalForm"
      method="POST"
      action="{{ route('items.store', ['modal' => 1, 'r' => request('r')]) }}"
    >
      @csrf
      <input type="hidden" name="r" value="{{ request('r') }}">

      <div class="modal-body">
        @if ($errors->any())
          <div class="alert alert-danger">
            <div class="fw-bold mb-2">Validasi gagal</div>
            <ul class="mb-0">
              @foreach ($errors->all() as $msg)
                <li>{{ $msg }}</li>
              @endforeach
            </ul>
          </div>
        @endif

        {{-- Reuse form fields yang sama dengan create page --}}
        @include('items._form', ['item' => $item])
      </div>

      <div class="modal-footer">
        <button type="button" class="btn btn-link" data-bs-dismiss="modal">Batal</button>
        <button type="submit" name="action" value="save" class="btn btn-primary">Simpan</button>
      </div>
    </form>
  </div>
</div>
