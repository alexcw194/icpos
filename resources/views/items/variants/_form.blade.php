@if ($errors->any())
  <div class="alert alert-danger m-3">
    <div class="fw-bold mb-1">Periksa input:</div>
    <ul class="mb-0">
      @foreach ($errors->all() as $error)
        <li>{{ $error }}</li>
      @endforeach
    </ul>
  </div>
@endif

@php
  $variant     = $variant ?? null;
  $attrSource  = isset($variant) && is_array($variant->attributes) ? $variant->attributes : [];
  $value       = fn (string $field, $default = '') => old($field, isset($variant) ? ($variant->{$field} ?? $default) : $default);
  $attrValue   = fn (string $key, $default = '') => old('attribute_' . $key, $attrSource[$key] ?? $default);
  $options     = is_array($item->variant_options) ? $item->variant_options : [];
  $variantType = $item->variant_type ?? 'none';
  $isActiveOld = old('is_active', isset($variant) ? (int) $variant->is_active : 1);
@endphp

<div class="card-body">
  <div class="row g-3">
    <div class="col-md-4">
      <label class="form-label">SKU (opsional)</label>
      <input type="text" name="sku" value="{{ $value('sku') }}" class="form-control" autocomplete="off">
      <div class="form-hint">Akan otomatis di-uppercase.</div>
    </div>

    <div class="col-md-4">
      <label class="form-label">Harga</label>
      <input type="text" name="price" value="{{ $value('price', $item->price) }}" class="form-control" inputmode="decimal" autocomplete="off" required>
      <div class="form-hint">Format Indonesia diterima (mis. <code>1.234,56</code>).</div>
    </div>

    <div class="col-md-4">
      <label class="form-label">Stok</label>
      <input type="number" name="stock" value="{{ $value('stock', 0) }}" class="form-control" min="0" step="1" required>
    </div>

    <div class="col-md-4">
      <label class="form-label">Min. Stok</label>
      <input type="number" name="min_stock" value="{{ $value('min_stock', 0) }}" class="form-control" min="0" step="1">
    </div>

    <div class="col-md-4">
      <label class="form-label">Barcode (opsional)</label>
      <input type="text" name="barcode" value="{{ $value('barcode') }}" class="form-control" maxlength="64" autocomplete="off">
    </div>

    <div class="col-md-4">
      <label class="form-label">Status</label>
      <div class="form-check form-switch">
        <input type="hidden" name="is_active" value="0">
        <input class="form-check-input" type="checkbox" name="is_active" value="1" {{ (int) $isActiveOld ? 'checked' : '' }}>
        <span class="form-check-label">Aktif</span>
      </div>
    </div>
  </div>

  <div class="card mt-3">
    <div class="card-header">
      <div class="card-title">Atribut Varian</div>
      @if($variantType === 'none')
        <div class="small text-muted">Item ini tidak memakai varian khusus, namun Anda dapat mengisi atribut tambahan jika diperlukan.</div>
      @else
        <div class="small text-muted">Isi atribut sesuai konfigurasi item (<code>{{ $variantType }}</code>).</div>
      @endif
    </div>
    <div class="card-body">
      <div class="row g-3">
        <div class="col-md-4">
          <label class="form-label">Color</label>
          <input type="text" name="attribute_color" value="{{ $attrValue('color') }}" class="form-control" list="variantColorOptions" autocomplete="off">
          @if(!empty($options['color']))
            <div class="form-hint">Pilih dari opsi yang tersedia atau ketik manual.</div>
          @endif
        </div>

        <div class="col-md-4">
          <label class="form-label">Size</label>
          <input type="text" name="attribute_size" value="{{ $attrValue('size') }}" class="form-control" list="variantSizeOptions" autocomplete="off">
        </div>

        <div class="col-md-4">
          <label class="form-label">Length</label>
          <input type="text" name="attribute_length" value="{{ $attrValue('length') }}" class="form-control" list="variantLengthOptions" autocomplete="off" placeholder="mis. 20">
        </div>
      </div>
    </div>
  </div>
</div>

@if(!empty($options['color']))
  <datalist id="variantColorOptions">
    @foreach($options['color'] as $opt)
      <option value="{{ $opt }}">
    @endforeach
  </datalist>
@endif

@if(!empty($options['size']))
  <datalist id="variantSizeOptions">
    @foreach($options['size'] as $opt)
      <option value="{{ $opt }}">
    @endforeach
  </datalist>
@endif

@if(!empty($options['length']))
  <datalist id="variantLengthOptions">
    @foreach($options['length'] as $opt)
      <option value="{{ $opt }}">
    @endforeach
  </datalist>
@endif
