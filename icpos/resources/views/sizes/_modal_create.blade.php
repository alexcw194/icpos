<div class="modal-header">
  <h5 class="modal-title">Add Size</h5>
  <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
</div>
<form action="{{ route('sizes.store') }}" method="POST" id="sizeModalForm">
  @csrf
  @include('sizes._form')
  <div class="modal-footer">
    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Batal</button>
    <button type="submit" class="btn btn-primary">Simpan</button>
  </div>
</form>
