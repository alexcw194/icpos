<div class="table-responsive d-none d-md-block">
  <table class="table table-hover align-middle inventory-table">
    <thead>
      <tr>
        <th class="w-1"></th>
        <th>Nama</th>
        <th>Size</th>
        <th>Color</th>
        <th>SKU</th>
        <th>Brand</th>
        <th class="text-end">Harga</th>
        <th class="text-end">Stok</th>
        <th class="w-1"></th>
      </tr>
    </thead>
    <tbody>
      @forelse($rows as $row)
        @php
          $isVariant = $row['entity'] === 'variant';
          $badgeText = $isVariant ? 'V' : '';
          $lowStock  = $row['low_stock'] ?? false;
          $inactive  = $row['inactive'] ?? false;

          // modal id must be unique per row (item + variant)
          $modalId   = 'modalAdjust-'.$row['entity'].'-'.$row['item_id'].'-'.($row['variant_id'] ?? '0');

          // Resolve context for initial on-hand preview
          $__company    = $company ?? \App\Models\Company::where('is_default', true)->first();
          $__warehouses = \App\Models\Warehouse::orderBy('name')->get(['id','name']);
          $__warehouse  = $__warehouses->first();
          $__variantId  = $isVariant ? ($row['variant_id'] ?? null) : null;

          $__onhand = 0.0;
          if ($__company && $__warehouse) {
            $__onhand = \App\Models\ItemStock::query()
              ->where('company_id', $__company->id)
              ->where('warehouse_id', $__warehouse->id)
              ->where('item_id', $row['item_id'])
              ->when($__variantId,
                fn($q)=>$q->where('item_variant_id',$__variantId),
                fn($q)=>$q->whereNull('item_variant_id'))
              ->value('qty_on_hand') ?? 0;
          }
        @endphp
        <tr class="inventory-row inventory-row--{{ $row['entity'] }} {{ $inactive ? 'is-inactive' : '' }}">
          <td class="text-center">
            @if($badgeText)
              <span class="badge bg-primary-subtle text-primary">{{ $badgeText }}</span>
            @endif
          </td>
          <td class="text-wrap">
            <div class="fw-semibold">{{ $row['display_name'] }}</div>
            @if($isVariant && !empty($row['parent_name']))
              <div class="text-muted small mt-1">Parent: <a href="{{ route('items.show', $row['item_id']) }}">{{ $row['parent_name'] }}</a></div>
            @endif
            <div class="d-flex gap-2 mt-1">
              @if($lowStock)
                <span class="badge bg-warning text-dark">Stok Rendah</span>
              @endif
              @if($inactive)
                <span class="badge bg-secondary">Nonaktif</span>
              @endif
            </div>
          </td>
          <td>{{ $row['attributes']['size'] ?? '-' }}</td>
          <td>{{ $row['attributes']['color'] ?? '-' }}</td>
          <td>{{ $row['sku'] ?: '-' }}</td>
          <td>{{ $row['brand'] ?: '-' }}</td>
          <td class="text-end">{{ $row['price_label'] }}</td>
          <td class="text-end">{{ $row['stock_label'] }}</td>
          <td class="text-end">
            <div class="row-actions">
              @if($isVariant)
                <a href="{{ route('items.variants.index', $row['item_id']) }}#variant-{{ $row['variant_id'] }}" class="me-2">Lihat</a>
                <a href="{{ route('variants.edit', $row['variant_id']) }}" class="me-2">Ubah</a>
                <a href="{{ route('items.show', $row['item_id']) }}" class="me-2">Ke Parent</a>
                <a href="#" class="me-2" data-bs-toggle="modal" data-bs-target="#{{ $modalId }}">Stock</a>
              @else
                <a href="{{ route('items.show', $row['item_id']) }}" class="me-2">Lihat</a>
                <a href="{{ route('items.edit', $row['item_id']) }}" class="me-2">Ubah</a>
                <a href="{{ route('items.variants.index', $row['item_id']) }}" class="me-2">Variant</a>
                <a href="#" class="me-2" data-bs-toggle="modal" data-bs-target="#{{ $modalId }}">Stock</a>
              @endif
            </div>
          </td>
        </tr>
      @empty
        <tr>
          <td colspan="9" class="text-center text-muted">Tidak ada data untuk filter saat ini.</td>
        </tr>
      @endforelse
    </tbody>
  </table>
</div>

{{-- ===== Render ALL modals AFTER the table (fix backdrop/z-index) ===== --}}
@foreach($rows as $row)
  @php
    $isVariant = $row['entity'] === 'variant';
    $modalId   = 'modalAdjust-'.$row['entity'].'-'.$row['item_id'].'-'.($row['variant_id'] ?? '0');

    $__company    = $company ?? \App\Models\Company::where('is_default', true)->first();
    $__warehouses = \App\Models\Warehouse::orderBy('name')->get(['id','name']);
    $__warehouse  = $__warehouses->first();
    $__variantId  = $isVariant ? ($row['variant_id'] ?? null) : null;

    $__onhand = 0.0;
    if ($__company && $__warehouse) {
      $__onhand = \App\Models\ItemStock::query()
        ->where('company_id', $__company->id)
        ->where('warehouse_id', $__warehouse->id)
        ->where('item_id', $row['item_id'])
        ->when($__variantId,
          fn($q)=>$q->where('item_variant_id',$__variantId),
          fn($q)=>$q->whereNull('item_variant_id'))
        ->value('qty_on_hand') ?? 0;
    }
  @endphp

  <div class="modal fade" id="{{ $modalId }}" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-sm modal-dialog-centered">
      <form method="POST" action="{{ route('stocks.adjust', $row['item_id']) }}" class="modal-content">
        @csrf
        <div class="modal-header">
          <h5 class="modal-title">
            Penyesuaian Stok
            <div class="text-secondary small fw-normal mt-1">
              {{ $row['display_name'] }} @if(!empty($row['sku'])) • <span class="text-muted">{{ $row['sku'] }}</span> @endif
            </div>
          </h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>

        <div class="modal-body">
          <input type="hidden" name="company_id" value="{{ $__company?->id }}">
          <input type="hidden" name="variant_id" value="{{ $__variantId }}">

          <div class="mb-2">
            <label class="form-label">Tanggal Posting</label>
            <input type="date" name="posted_at" class="form-control" value="{{ now()->toDateString() }}">
          </div>

          <div class="mb-2">
            <label class="form-label">Warehouse</label>
            <select class="form-select" name="warehouse_id" required id="wh-{{ $modalId }}">
              @foreach($__warehouses as $wh)
                <option value="{{ $wh->id }}" @selected($__warehouse && $wh->id === $__warehouse->id)>{{ $wh->name }}</option>
              @endforeach
            </select>
          </div>

          <div class="mb-2">
            <label class="form-label">Stock Awal</label>
            <input type="text" class="form-control" id="awal-{{ $modalId }}" value="{{ number_format($__onhand,2) }}" readonly>
          </div>

          <div class="row g-2">
            <div class="col-5">
              <label class="form-label">Tipe</label>
              <select name="type" class="form-select" id="type-{{ $modalId }}">
                <option value="in">IN (+)</option>
                <option value="out">OUT (−)</option>
              </select>
            </div>
            <div class="col-7">
              <label class="form-label">Qty</label>
              <input type="number" step="0.0001" min="0.0001" name="qty" class="form-control" id="qty-{{ $modalId }}" required>
            </div>
          </div>

          <div class="mt-3">
            <label class="form-label">Stock Akhir (preview)</label>
            <input type="text" class="form-control fw-bold" id="akhir-{{ $modalId }}" value="{{ number_format($__onhand,2) }}" readonly>
          </div>
        </div>

        <div class="modal-footer">
          <button type="button" class="btn btn-link" data-bs-dismiss="modal">Batal</button>
          <button type="submit" class="btn btn-primary">Konfirmasi & Simpan</button>
        </div>
      </form>
    </div>
  </div>

  <script>
  document.addEventListener('DOMContentLoaded', function(){
    const id   = '{{ $modalId }}';
    let awal   = parseFloat((document.getElementById('awal-'+id).value || '0').replace(/,/g,'')) || 0;
    const tipe = document.getElementById('type-'+id);
    const qty  = document.getElementById('qty-'+id);
    const akhir= document.getElementById('akhir-'+id);
    const wh   = document.getElementById('wh-'+id);
    const modal= document.getElementById(id);
    const trig = document.querySelector('[data-bs-target="#{{ $modalId }}"]');

    function recalc(){
      const q = parseFloat(qty.value || 0);
      const v = awal + (tipe.value === 'in' ? q : -q);
      akhir.value = (isFinite(v) ? v : 0).toFixed(2);
    }
    function refreshOnHand(){ awal = parseFloat((document.getElementById('awal-'+id).value || '0').replace(/,/g,'')) || awal; recalc(); }

    tipe.addEventListener('change', recalc);
    qty.addEventListener('input', recalc);
    wh.addEventListener('change', refreshOnHand);

    // A11y: clear focus on hide, return focus to trigger
    modal.addEventListener('hide.bs.modal',   () => document.activeElement?.blur());
    modal.addEventListener('hidden.bs.modal', () => trig?.focus({preventScroll:true}));
  });
  </script>
@endforeach
