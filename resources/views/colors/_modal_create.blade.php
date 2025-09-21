<div class="modal-header">
  <h5 class="modal-title">Add Color</h5>
  <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
</div>
<form action="{{ route('colors.store') }}" method="POST" id="colorModalForm">
  @csrf
  @include('colors._form')
  <div class="modal-footer">
    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Batal</button>
    <button type="submit" class="btn btn-primary">Simpan</button>
  </div>
</form>
