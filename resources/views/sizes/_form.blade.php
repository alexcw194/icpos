{{-- fields --}}
@if ($errors->any())
  <div class="alert alert-danger m-3">
    <div class="fw-bold mb-1">Periksa input:</div>
    <ul class="mb-0">
      @foreach ($errors->all() as $e) <li>{{ $e }}</li> @endforeach
    </ul>
  </div>
@endif

@php
  $v = fn($f,$d='') => old($f, isset($size)?($size->{$f} ?? $d):$d);
@endphp

<div class="card-body">
  <div class="row g-3">
    <div class="col-md-6">
      <label class="form-label">Name</label>
      <input type="text" name="name" value="{{ $v('name') }}" class="form-control" required>
      @error('name')<div class="text-danger small">{{ $message }}</div>@enderror
      <div class="form-hint">Contoh: S, M, L, 20M, 30M, 1/2"</div>
    </div>

    <div class="col-md-3">
      <label class="form-label">Sort Order</label>
      <input type="number" name="sort_order" value="{{ $v('sort_order', 0) }}" class="form-control" step="1" min="0">
      @error('sort_order')<div class="text-danger small">{{ $message }}</div>@enderror
      <div class="form-hint">Angka kecil tampil lebih awal. Rekomendasi: 0, 10, 20… supaya mudah sisipkan nanti.</div>
    </div>

    <div class="col-md-3">
      <label class="form-label">Active</label>
      <label class="form-check form-switch">
        <input class="form-check-input" type="checkbox" name="is_active" value="1"
               {{ old('is_active', isset($size)?(int)$size->is_active:1) ? 'checked' : '' }}>
        <span class="form-check-label">Tandai aktif</span>
      </label>
    </div>

    <div class="col-12">
      <label class="form-label">Description (opsional)</label>
      <textarea name="description" class="form-control" rows="2">{{ $v('description') }}</textarea>
    </div>
  </div>
</div>
