@php
  $v = fn($field, $default = '') => old($field, isset($variant) ? ($variant->{$field} ?? $default) : $default);
  $attrs = isset($variant) && is_array($variant->attributes) ? $variant->attributes : [];
@endphp

<div class="row g-3">
  <div class="col-md-4">
    <label class="form-label">SKU (opsional)</label>
    <input type="text" name="sku" class="form-control" value="{{ $v('sku') }}">
    @error('sku')<div class="text-danger small">{{ $message }}</div>@enderror
  </div>
  <div class="col-md-4">
    <label class="form-label">Harga</label>
    <input type="text" name="price" class="form-control" inputmode="decimal" value="{{ old('price', isset($variant) ? $variant->price : '') }}">
  </div>
  <div class="col-md-4">
    <label class="form-label">Stok</label>
    <input type="text" name="stock" class="form-control" inputmode="numeric" value="{{ old('stock', isset($variant) ? $variant->stock : '0') }}">
  </div>

  <div class="col-md-4">
    <label class="form-label">Active</label>
    <label class="form-check form-switch">
      <input type="hidden" name="is_active" value="0">
      <input class="form-check-input" type="checkbox" name="is_active" value="1" {{ old('is_active', isset($variant) ? (int) $variant->is_active : 1) ? 'checked' : '' }}>
      <span class="form-check-label">Yes</span>
    </label>
  </div>

  <div class="col-md-4">
    <label class="form-label">Barcode (opsional)</label>
    <input type="text" name="barcode" class="form-control" value="{{ $v('barcode') }}">
  </div>
  <div class="col-md-4">
    <label class="form-label">Min Stock</label>
    <input type="number" name="min_stock" class="form-control" value="{{ old('min_stock', isset($variant) ? $variant->min_stock : 0) }}">
  </div>

  <div class="col-md-4">
    <label class="form-label">Color</label>
    <input type="text" name="attr_color" class="form-control" value="{{ old('attr_color', $attrs['color'] ?? '') }}">
  </div>
  <div class="col-md-4">
    <label class="form-label">Size</label>
    <input type="text" name="attr_size" class="form-control" value="{{ old('attr_size', $attrs['size'] ?? '') }}">
  </div>
  <div class="col-md-4">
    <label class="form-label">Length (m)</label>
    <input type="text" name="attr_length" class="form-control" inputmode="decimal" value="{{ old('attr_length', $attrs['length'] ?? '') }}">
  </div>
</div>
