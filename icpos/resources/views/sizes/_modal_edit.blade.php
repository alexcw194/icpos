<div class="modal-header">
  <h5 class="modal-title">Edit Size</h5>
  <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
</div>
<form action="{{ route('sizes.update', $size) }}" method="POST" id="sizeModalForm">
  @csrf @method('PUT')
  @include('sizes._form', ['size' => $size])
  <div class="modal-footer">
    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Batal</button>
    <button type="submit" class="btn btn-primary">Update</button>
  </div>
</form>
