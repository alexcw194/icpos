<div class="modal-header">
  <h5 class="modal-title">Edit Color</h5>
  <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
</div>
<form action="{{ route('colors.update', $color) }}" method="POST" id="colorModalForm">
  @csrf @method('PUT')
  @include('colors._form', ['color' => $color])
  <div class="modal-footer">
    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Batal</button>
    <button type="submit" class="btn btn-primary">Update</button>
  </div>
</form>
