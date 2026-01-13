@extends('layouts.tabler')

@section('content')
<div class="container-xl">
  @if(session('success')) <div class="alert alert-success">{{ session('success') }}</div> @endif

  <div class="card">
    <div class="card-header d-flex align-items-center flex-nowrap inventory-header">
      <h3 class="card-title mb-0 me-3 flex-shrink-0">Inventory</h3>
      <div class="ms-auto d-flex align-items-center gap-2 flex-nowrap">
        <div class="btn-group flex-nowrap" role="group" id="inventory-view-toggle">
          <button type="button" class="btn btn-outline-primary {{ $viewMode === 'flat' ? 'active' : '' }}" data-view="flat">List</button>
          <button type="button" class="btn btn-outline-primary {{ $viewMode === 'grouped' ? 'active' : '' }}" data-view="grouped">Group</button>
        </div>

        {{-- items/index.blade.php --}}
        @hasanyrole('SuperAdmin|Admin')
          <a
            href="{{ route('items.create', ['modal' => 1, 'r' => request()->fullUrl()]) }}"
            class="btn btn-primary"
            data-modal="#adminModal"
          >
            + Tambah Item
          </a>
        @endhasanyrole
      </div>
    </div>

    <div class="card-body">
      <form method="get" class="row g-2 g-md-3 mb-3 align-items-end" id="inventory-filter-form">
        <input type="hidden" name="view" value="{{ $viewMode }}">

        {{-- Tier 1 (mobile always visible) --}}
        <div class="col-12 col-md-4">
          <label class="form-label">Cari Item / SKU / Atribut</label>
          <div class="input-group">
            <input
              id="itemsSearch"
              type="search"
              name="q"
              value="{{ $filters['q'] }}"
              class="form-control"
              placeholder="Ketik nama, SKU, varian…"
              enterkeyhint="search"
              inputmode="search"
              autocomplete="off"
            >
            <button
              type="button"
              class="btn btn-icon"
              id="itemsSearchBtn"
              aria-label="Search"
              title="Search"
            >
              <!-- Tabler icon: search -->
              <svg xmlns="http://www.w3.org/2000/svg" class="icon" width="20" height="20" viewBox="0 0 24 24"
                   stroke-width="2" stroke="currentColor" fill="none" stroke-linecap="round" stroke-linejoin="round"
                   aria-hidden="true">
                <path stroke="none" d="M0 0h24v24H0z" fill="none"></path>
                <circle cx="10" cy="10" r="7"></circle>
                <line x1="21" y1="21" x2="15" y2="15"></line>
              </svg>
            </button>
          </div>
        </div>

        <div class="col-6 col-md-2">
          <label class="form-label">Tipe</label>
          <select name="type" class="form-select" data-auto-submit="1">
            <option value="all"     @selected($filters['type'] === 'all')>Item & Variant</option>
            <option value="item"    @selected($filters['type'] === 'item')>Item saja</option>
            <option value="variant" @selected($filters['type'] === 'variant')>Variant saja</option>
          </select>
        </div>

        <div class="col-6 col-md-2">
          <label class="form-label">Stock</label>
          <select name="stock" class="form-select" data-auto-submit="1">
            <option value="all" @selected($filters['stock'] === 'all')>Semua</option>
            <option value="gt0" @selected($filters['stock'] === 'gt0')>> 0</option>
            <option value="eq0" @selected($filters['stock'] === 'eq0')>= 0</option>
          </select>
        </div>

        <div class="col-7 col-md-2">
          <label class="form-label">Urutkan</label>
          <select name="sort" class="form-select" data-auto-submit="1">
            <option value="name_asc"       @selected($filters['sort'] === 'name_asc')>Nama A–Z</option>
            <option value="price_lowest"   @selected($filters['sort'] === 'price_lowest')>Harga Terendah</option>
            <option value="price_highest"  @selected($filters['sort'] === 'price_highest')>Harga Tertinggi</option>
            <option value="stock_highest"  @selected($filters['sort'] === 'stock_highest')>Stok Terbanyak</option>
            <option value="newest"         @selected($filters['sort'] === 'newest')>Terbaru</option>
          </select>
        </div>

        {{-- Mobile: Advanced + Reset (no scroll) --}}
        <div class="col-5 d-md-none d-flex align-items-end justify-content-end gap-2">
          <button
            type="button"
            class="btn btn-outline-secondary"
            data-bs-toggle="offcanvas"
            data-bs-target="#inventoryFiltersOffcanvas"
            aria-controls="inventoryFiltersOffcanvas"
          >
            Filter
          </button>

          <a href="{{ route('items.index', ['view' => $viewMode]) }}" class="btn btn-link px-0">Reset</a>
        </div>

        {{-- Tier 2 (desktop inline) --}}
        <div class="col-6 col-md-2 d-none d-md-block">
          <label class="form-label">Brand</label>
          <select name="brand_id" class="form-select">
            <option value="">Semua Brand</option>
            @foreach($brands as $brand)
              <option value="{{ $brand->id }}" @selected((string)$filters['brand_id'] === (string)$brand->id)>{{ $brand->name }}</option>
            @endforeach
          </select>
        </div>

        <div class="col-6 col-md-2 d-none d-md-block">
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

        <div class="col-12 col-md-4 d-none d-md-block">
          <label class="form-label">Size</label>
          <select name="sizes[]" class="form-select inventory-select" multiple>
            @foreach($sizesList as $size)
              <option value="{{ $size }}" @selected(in_array($size, $filters['sizes'], true))>{{ $size }}</option>
            @endforeach
          </select>
        </div>

        <div class="col-12 col-md-4 d-none d-md-block">
          <label class="form-label">Color</label>
          <select name="colors[]" class="form-select inventory-select" multiple>
            @foreach($colorsList as $color)
              <option value="{{ $color }}" @selected(in_array($color, $filters['colors'], true))>{{ $color }}</option>
            @endforeach
          </select>
        </div>

        <div class="col-6 col-md-2 d-none d-md-block">
          <label class="form-label">Length Min</label>
          <input type="number" step="0.01" name="length_min" value="{{ $filters['length_min'] }}" class="form-control" placeholder="Min">
        </div>

        <div class="col-6 col-md-2 d-none d-md-block">
          <label class="form-label">Length Max</label>
          <input type="number" step="0.01" name="length_max" value="{{ $filters['length_max'] }}" class="form-control" placeholder="Max">
        </div>

        <div class="col-12 col-md-auto d-none d-md-block">
          <label class="form-label">&nbsp;</label>
          <div class="form-check form-switch">
            <input class="form-check-input" type="checkbox" id="toggleVariantParent" name="show_variant_parent" value="1" @checked($filters['show_variant_parent'])>
            <label class="form-check-label" for="toggleVariantParent">Tampilkan Variant Parent</label>
          </div>
        </div>

        {{-- Desktop buttons --}}
        <div class="col-12 d-none d-md-flex gap-2 mt-2">
          <button class="btn btn-primary">Terapkan</button>
          <a href="{{ route('items.index', ['view' => $viewMode]) }}" class="btn btn-light">Reset</a>
        </div>

        {{-- Offcanvas: Advanced Filters (mobile) --}}
        <div class="offcanvas offcanvas-bottom d-md-none" tabindex="-1" id="inventoryFiltersOffcanvas" aria-labelledby="inventoryFiltersOffcanvasLabel">
          <div class="offcanvas-header">
            <h5 class="offcanvas-title" id="inventoryFiltersOffcanvasLabel">Filter Lanjutan</h5>
            <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Close"></button>
          </div>

          <div class="offcanvas-body">
            <div class="row g-2">
              <div class="col-6">
                <label class="form-label">Brand</label>
                <select name="brand_id" class="form-select">
                  <option value="">Semua Brand</option>
                  @foreach($brands as $brand)
                    <option value="{{ $brand->id }}" @selected((string)$filters['brand_id'] === (string)$brand->id)>{{ $brand->name }}</option>
                  @endforeach
                </select>
              </div>

              <div class="col-6">
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

              <div class="col-12">
                <label class="form-label">Size</label>
                <select name="sizes[]" class="form-select inventory-select" multiple>
                  @foreach($sizesList as $size)
                    <option value="{{ $size }}" @selected(in_array($size, $filters['sizes'], true))>{{ $size }}</option>
                  @endforeach
                </select>
              </div>

              <div class="col-12">
                <label class="form-label">Color</label>
                <select name="colors[]" class="form-select inventory-select" multiple>
                  @foreach($colorsList as $color)
                    <option value="{{ $color }}" @selected(in_array($color, $filters['colors'], true))>{{ $color }}</option>
                  @endforeach
                </select>
              </div>

              <div class="col-6">
                <label class="form-label">Length Min</label>
                <input type="number" step="0.01" name="length_min" value="{{ $filters['length_min'] }}" class="form-control" placeholder="Min">
              </div>

              <div class="col-6">
                <label class="form-label">Length Max</label>
                <input type="number" step="0.01" name="length_max" value="{{ $filters['length_max'] }}" class="form-control" placeholder="Max">
              </div>

              <div class="col-12">
                <div class="form-check form-switch">
                  <input class="form-check-input" type="checkbox" id="toggleVariantParentMobile" name="show_variant_parent" value="1" @checked($filters['show_variant_parent'])>
                  <label class="form-check-label" for="toggleVariantParentMobile">Tampilkan Variant Parent</label>
                </div>
              </div>
            </div>
          </div>

          <div class="border-top p-3 d-flex gap-2">
            <button class="btn btn-primary w-100">Terapkan</button>
            <a class="btn btn-light w-100" href="{{ route('items.index', ['view' => $viewMode]) }}">Reset</a>
          </div>
        </div>
      </form>

      @if($viewMode === 'flat')
        @include('items.partials.inventory_flat', ['rows' => $flatRows, 'filters' => $filters])
      @else
        @include('items.partials.inventory_grouped', ['rows' => $groupedRows])
      @endif

      <div class="card-footer d-flex justify-content-end">
        {{ $items->appends(request()->except('page'))->onEachSide(0)->links('vendor.pagination.items') }}
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
  @media (max-width: 767.98px){
    .items-sort{ width:auto; min-width:14.5rem; }
    .inventory-header .btn{ white-space:nowrap; }
    .inventory-header .btn-group .btn{ padding-left:.75rem; padding-right:.75rem; }
  }
</style>
@endpush

@push('scripts')
<script>
(function () {
  function ensureAdminModal() {
    let modal = document.getElementById('adminModal');
    if (modal) return modal;

    const wrapper = document.createElement('div');
    wrapper.innerHTML = `
      <div class="modal modal-blur fade" id="adminModal" tabindex="-1" aria-hidden="true">
        <div id="adminModalBody"></div>
      </div>
    `.trim();

    document.body.appendChild(wrapper.firstElementChild);
    return document.getElementById('adminModal');
  }

  async function openAdminModal(url) {
    const modalEl = ensureAdminModal();
    const bodyEl = document.getElementById('adminModalBody');

    bodyEl.innerHTML = `
      <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
          <div class="modal-body">
            <div class="d-flex align-items-center gap-2">
              <div class="spinner-border" role="status" aria-hidden="true"></div>
              <div>Loading...</div>
            </div>
          </div>
        </div>
      </div>
    `;

    const modal = bootstrap.Modal.getOrCreateInstance(modalEl);
    modal.show();

    const res = await fetch(url, {
      headers: {
        'X-Requested-With': 'XMLHttpRequest',
        'Accept': 'text/html'
      }
    });

    if (!res.ok) {
      bodyEl.innerHTML = `
        <div class="modal-dialog modal-lg modal-dialog-centered">
          <div class="modal-content">
            <div class="modal-body">
              <div class="text-danger">Gagal load form (${res.status}).</div>
            </div>
          </div>
        </div>
      `;
      return;
    }

    bodyEl.innerHTML = await res.text();
  }

  // 1) CLICK: buka modal dari link yang punya data-modal="#adminModal"
  document.addEventListener('click', function (e) {
    const trigger = e.target.closest('a[data-modal="#adminModal"]');
    if (!trigger) return;

    e.preventDefault();

    const url = trigger.getAttribute('href');
    if (!url) return;

    openAdminModal(url).catch(err => {
      console.error(err);
      const bodyEl = document.getElementById('adminModalBody');
      if (bodyEl) {
        bodyEl.innerHTML = `
          <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content">
              <div class="modal-body">
                <div class="text-danger">Error: ${String(err)}</div>
              </div>
            </div>
          </div>
        `;
      }
    });
  });

  // 2) SUBMIT: submit form modal via fetch JSON
  document.addEventListener('submit', async function (e) {
    const form = e.target.closest('#itemModalForm');
    if (!form) return;

    e.preventDefault();

    const submitBtn = form.querySelector('button[type="submit"]');
    if (submitBtn) {
      submitBtn.disabled = true;
      submitBtn.dataset.originalText = submitBtn.innerHTML;
      submitBtn.innerHTML = 'Menyimpan...';
    }

    try {
      const res = await fetch(form.getAttribute('action'), {
        method: form.method || 'POST',
        headers: {
          'X-Requested-With': 'XMLHttpRequest',
          'Accept': 'application/json'
        },
        body: new FormData(form)
      });

      const ct = res.headers.get('content-type') || '';

      // 422 dari controller modal => HTML form dengan errors
      if (res.status === 422 && !ct.includes('application/json')) {
        ensureAdminModal();
        document.getElementById('adminModalBody').innerHTML = await res.text();
        return;
      }

      // kalau balik JSON
      if (ct.includes('application/json')) {
        const data = await res.json();

        // close modal dulu biar backdrop bersih
        const modalEl = ensureAdminModal();
        const inst = bootstrap.Modal.getInstance(modalEl);
        if (inst) inst.hide();

        // redirect_url dari controller store()
        if (data && data.redirect_url) {
          window.location.href = data.redirect_url;
          return;
        }

        // fallback reload
        window.location.reload();
        return;
      }

      // fallback: kalau server balikin HTML normal
      if (!res.ok) {
        ensureAdminModal();
        document.getElementById('adminModalBody').innerHTML = await res.text();
        return;
      }

      window.location.reload();
    } catch (err) {
      console.error(err);
    } finally {
      if (submitBtn) {
        submitBtn.disabled = false;
        submitBtn.innerHTML = submitBtn.dataset.originalText || 'Simpan';
      }
    }
  });
})();
</script>

{{-- Mobile: auto-apply Quick Filters (Tier 1) --}}
<script>
(function () {
  const form = document.getElementById('inventory-filter-form');
  if (!form) return;

  const isMobile = window.matchMedia('(max-width: 767.98px)').matches;
  if (!isMobile) return;

  const submit = () => (form.requestSubmit ? form.requestSubmit() : form.submit());

  // Dropdown quick filters: auto apply (enterprise-safe)
  form.querySelectorAll('select[data-auto-submit="1"]').forEach((el) => {
    el.addEventListener('change', submit);
  });

  // Enter on keyboard triggers submit
  const q = form.querySelector('input[name="q"]');
  if (q) {
    q.addEventListener('keydown', (e) => {
      if (e.key === 'Enter') {
        e.preventDefault();
        submit();
      }
    });
  }

  // Click icon button triggers submit
  const btn = document.getElementById('itemsSearchBtn');
  if (btn) {
    btn.addEventListener('click', () => submit());
  }
})();
</script>
@endpush
