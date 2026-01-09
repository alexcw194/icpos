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
       {{-- items/index.blade.php --}}
        <a
          href="{{ route('items.create', ['modal' => 1, 'r' => request()->fullUrl()]) }}"
          class="btn btn-primary"
          data-modal="#adminModal"
        >
          Add Item
        </a>
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
(function () {
  const MODAL_ID = 'adminModal';

  function ensureAdminModal() {
    if (document.getElementById(MODAL_ID)) return;

    document.body.insertAdjacentHTML('beforeend', `
      <div class="modal modal-blur fade" id="${MODAL_ID}" tabindex="-1" aria-hidden="true">
        <div id="adminModalBody"></div>
      </div>
    `);
  }

  async function openAdminModal(url) {
      if (!url || typeof url !== 'string') {
      console.warn('openAdminModal called with invalid url', url);
      return;
    }
    ensureAdminModal();

    const body = document.getElementById('adminModalBody');
    body.innerHTML = `
      <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
          <div class="modal-body text-center py-6">
            <div class="spinner-border text-muted" role="status"></div>
          </div>
        </div>
      </div>
    `;

    const el = document.getElementById(MODAL_ID);
    const modal = bootstrap.Modal.getOrCreateInstance(el);
    modal.show();

    const res = await fetch(url, {
      headers: {
        'X-Requested-With': 'XMLHttpRequest',
        'Accept': 'text/html'
      }
    });

    body.innerHTML = await res.text();
    initItemModalEnhancements();
  }

  // Optional: formatting price agar konsisten dengan create page yang sudah punya formatter :contentReference[oaicite:5]{index=5}
  function initItemModalEnhancements() {
    const price = document.querySelector('#itemModalForm input[name="price"]');
    if (price && !price.dataset.bound) {
      price.dataset.bound = '1';
      price.addEventListener('input', () => {
        const raw = String(price.value || '').replace(/[^\d]/g, '');
        price.value = raw ? Number(raw).toLocaleString('id-ID') : '';
      });
    }
  }

  // Open modal on click
  document.addEventListener('click', async (e) => {
    const trigger = e.target.closest('[data-modal="#adminModal"]');
    if (!trigger) return;

    e.preventDefault();

    const url = trigger.getAttribute('href') || trigger.dataset.url; // <- string
    if (!url || typeof url !== 'string') {
      console.warn('Modal URL invalid', { url, trigger });
      return;
    }

    openAdminModal(url);
  });

  // Submit modal form via AJAX; handle 422 (validation) by re-rendering modal HTML
  document.addEventListener('submit', async (e) => {
    const form = e.target;
    if (form.id !== 'itemModalForm') return;

    e.preventDefault();

    const submitBtn = form.querySelector('button[type="submit"]');
    if (submitBtn) submitBtn.disabled = true;

    const res = await fetch(form.action, {
      method: (form.method || 'POST').toUpperCase(),
      body: new FormData(form),
      headers: {
        'X-Requested-With': 'XMLHttpRequest',
        'Accept': 'text/html'
      }
    });

    if (res.redirected) {
      window.location.href = res.url;
      return;
    }

    const html = await res.text();
    document.getElementById('adminModalBody').innerHTML = html;
    initItemModalEnhancements();

    if (submitBtn) submitBtn.disabled = false;
  });
})();
</script>
@endpush
