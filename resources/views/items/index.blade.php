@extends('layouts.tabler')

@section('content')
<div class="container-xl">
  @if(session('success')) <div class="alert alert-success">{{ session('success') }}</div> @endif

  <div class="card">
    <div class="card-header align-items-center">
      <h3 class="card-title mb-0">Inventory</h3>
      <div class="ms-auto d-flex flex-wrap gap-2">
        <div class="btn-group" role="group" id="inventory-view-toggle">
          <button type="button" class="btn btn-outline-primary {{ $viewMode === 'flat' ? 'active' : '' }}" data-view="flat">Flat List</button>
          <button type="button" class="btn btn-outline-primary {{ $viewMode === 'grouped' ? 'active' : '' }}" data-view="grouped">Grouped</button>
        </div>
        <a href="{{ route('items.create') }}" class="btn btn-primary">+ Add Item</a>
      </div>
    </div>

    <div class="card-body">
      <form method="get" class="row g-2 g-md-3 mb-4 align-items-end" id="inventory-filter-form">
        <input type="hidden" name="view" value="{{ $viewMode }}">

        <div class="col-12 col-md-4">
          <label class="form-label">Cari Item / SKU / Atribut</label>
          <input type="text" name="q" value="{{ $filters['q'] }}" class="form-control" placeholder="Ketik nama, SKU, varian…">
        </div>

        <div class="col-6 col-md-2">
          <label class="form-label">Tipe</label>
          <select name="type" class="form-select">
            <option value="all"     @selected($filters['type'] === 'all')>Item & Variant</option>
            <option value="item"    @selected($filters['type'] === 'item')>Item saja</option>
            <option value="variant" @selected($filters['type'] === 'variant')>Variant saja</option>
          </select>
        </div>

        <div class="col-6 col-md-2">
          <label class="form-label">Stock</label>
          <select name="stock" class="form-select">
            <option value="all" @selected($filters['stock'] === 'all')>Semua</option>
            <option value="gt0" @selected($filters['stock'] === 'gt0')>> 0</option>
            <option value="eq0" @selected($filters['stock'] === 'eq0')>= 0</option>
          </select>
        </div>

        <div class="col-6 col-md-2">
          <label class="form-label">Brand</label>
          <select name="brand_id" class="form-select">
            <option value="">Semua Brand</option>
            @foreach($brands as $brand)
              <option value="{{ $brand->id }}" @selected((string)$filters['brand_id'] === (string)$brand->id)>{{ $brand->name }}</option>
            @endforeach
          </select>
        </div>

        <div class="col-6 col-md-2">
          <label class="form-label">Unit</label>
          <select name="unit_id" class="form-select">
            <option value="">Semua Unit</option>
            @foreach($units as $unit)
              <option value="{{ $unit->id }}" @selected((string)$filters['unit_id'] === (string)$unit->id)>
                {{ $unit->code ? $unit->code.' — ' : '' }}{{ $unit->name }}
              </option>
            @endforeach
          </select>
        </div>

        <div class="col-12 col-md-4">
          <label class="form-label">Size</label>
          <select name="sizes[]" class="form-select inventory-select" multiple>
            @foreach($sizesList as $size)
              <option value="{{ $size }}" @selected(in_array($size, $filters['sizes'], true))>{{ $size }}</option>
            @endforeach
          </select>
        </div>

        <div class="col-12 col-md-4">
          <label class="form-label">Color</label>
          <select name="colors[]" class="form-select inventory-select" multiple>
            @foreach($colorsList as $color)
              <option value="{{ $color }}" @selected(in_array($color, $filters['colors'], true))>{{ $color }}</option>
            @endforeach
          </select>
        </div>

        <div class="col-6 col-md-2">
          <label class="form-label">Length Min</label>
          <input type="number" step="0.01" name="length_min" value="{{ $filters['length_min'] }}" class="form-control" placeholder="Min">
        </div>

        <div class="col-6 col-md-2">
          <label class="form-label">Length Max</label>
          <input type="number" step="0.01" name="length_max" value="{{ $filters['length_max'] }}" class="form-control" placeholder="Max">
        </div>

        <div class="col-12 col-md-2">
          <label class="form-label">Urutkan</label>
          <select name="sort" class="form-select">
            <option value="name_asc"       @selected($filters['sort'] === 'name_asc')>Nama A–Z</option>
            <option value="price_lowest"   @selected($filters['sort'] === 'price_lowest')>Harga Terendah</option>
            <option value="price_highest"  @selected($filters['sort'] === 'price_highest')>Harga Tertinggi</option>
            <option value="stock_highest"  @selected($filters['sort'] === 'stock_highest')>Stok Terbanyak</option>
            <option value="newest"         @selected($filters['sort'] === 'newest')>Terbaru</option>
          </select>
        </div>

        <div class="col-12 col-md-auto">
          <label class="form-label">&nbsp;</label>
          <div class="form-check form-switch">
            <input class="form-check-input" type="checkbox" id="toggleVariantParent" name="show_variant_parent" value="1" @checked($filters['show_variant_parent'])>
            <label class="form-check-label" for="toggleVariantParent">Tampilkan Variant Parent</label>
          </div>
        </div>

        <div class="col-12 d-flex gap-2 mt-2">
          <button class="btn btn-primary">Terapkan</button>
          <a href="{{ route('items.index', ['view' => $viewMode]) }}" class="btn btn-light">Reset</a>
        </div>
      </form>

      @if($viewMode === 'flat')
        @include('items.partials.inventory_flat', ['rows' => $flatRows, 'filters' => $filters])
      @else
        @include('items.partials.inventory_grouped', ['rows' => $groupedRows])
      @endif

      <div class="card-footer d-flex justify-content-end">
        {{ $items->appends(request()->except('page'))->links() }}
      </div>
    </div>
  </div>
</div>
@endsection

@push('styles')
<style>
  .inventory-table .row-actions {
    opacity: 0;
    transition: opacity .15s ease;
    white-space: nowrap;
  }
  .inventory-table tr:hover .row-actions,
  .inventory-card .row-actions {
    opacity: 1;
  }
  .inventory-card .row-actions .btn,
  .inventory-card .row-actions a,
  .inventory-card .row-actions button {
    margin-right: .25rem;
  }
  .inventory-card.is-inactive,
  .inventory-row.is-inactive {
    opacity: 0.75;
  }
  #inventory-view-toggle .btn.active {
    color: #fff;
  }
  .variant-expand[aria-expanded="true"] {
    transform: rotate(90deg);
  }
</style>
@endpush

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
  const toggleWrap = document.getElementById('inventory-view-toggle');
  const filterForm = document.getElementById('inventory-filter-form');
  const hiddenView = filterForm ? filterForm.querySelector('input[name="view"]') : null;

  if (toggleWrap) {
    toggleWrap.querySelectorAll('button[data-view]').forEach(btn => {
      btn.addEventListener('click', function () {
        const mode = this.dataset.view;
        if (!mode) return;
        try { localStorage.setItem('inventoryViewMode', mode); } catch (e) {}
        if (hiddenView) hiddenView.value = mode;
        const url = new URL(window.location.href);
        url.searchParams.set('view', mode);
        window.location = url.toString();
      });
    });
  }

  try {
    const storedMode = localStorage.getItem('inventoryViewMode');
    if (storedMode && storedMode !== '{{ $viewMode }}') {
      const url = new URL(window.location.href);
      if (url.searchParams.get('view') !== storedMode) {
        url.searchParams.set('view', storedMode);
        window.location.replace(url.toString());
        return;
      }
    }
  } catch (e) {}

  if (window.TomSelect) {
    document.querySelectorAll('.inventory-select').forEach(el => {
      new TomSelect(el, {
        plugins: ['remove_button'],
        persist: false,
        create: false,
        sortField: { field: '$order' }
      });
    });
  }

  const variantToggle = document.getElementById('toggleVariantParent');
  if (variantToggle && filterForm) {
    variantToggle.addEventListener('change', () => {
      const data = new FormData(filterForm);
      if (variantToggle.checked) {
        data.set('show_variant_parent', '1');
      } else {
        data.delete('show_variant_parent');
      }
      data.set('view', '{{ $viewMode }}');
      const params = new URLSearchParams(data);
      window.location = `${filterForm.action || window.location.pathname}?${params.toString()}`;
    });
  }
});
</script>
@endpush
