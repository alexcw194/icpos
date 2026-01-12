{{-- resources/views/items/partials/inventory_flat.blade.php --}}
@php
  /**
   * Expected:
   * - $rows: array of normalized rows (item or variant)
   *   keys (recommended):
   *   - entity: 'item' | 'variant'
   *   - item_id
   *   - variant_id (nullable)
   *   - display_name
   *   - parent_name (nullable)
   *   - sku (nullable)
   *   - brand (nullable)
   *   - attributes: ['size'=>..., 'color'=>...] (optional)
   *   - price_label
   *   - stock_label
   *   - inactive (bool)
   *   - low_stock (bool)
   */
@endphp

{{-- ===================== Desktop table ===================== --}}
<div class="table-responsive d-none d-md-block">
  <table class="table table-vcenter table-striped">
    <thead>
      <tr>
        <th>Nama</th>
        <th class="w-1">SKU</th>
        <th class="w-1">Brand</th>
        <th class="w-1">Size</th>
        <th class="w-1">Color</th>
        <th class="text-end w-1">Harga</th>
        <th class="text-end w-1">Stok</th>
        <th class="text-end w-1">Aksi</th>
      </tr>
    </thead>
    <tbody>
      @forelse($rows as $row)
        @php
          $isVariant = ($row['entity'] ?? 'item') === 'variant';
          $inactive  = $row['inactive'] ?? false;
          $lowStock  = $row['low_stock'] ?? false;

          $size = $row['attributes']['size'] ?? '-';
          $color = $row['attributes']['color'] ?? '-';
        @endphp

        <tr class="{{ $inactive ? 'text-muted' : '' }}">
          <td>
            <div class="d-flex align-items-center gap-2">
              <div class="fw-semibold">
                {{ $row['display_name'] ?? '-' }}
                @if($isVariant)
                  <span class="badge bg-primary-subtle text-primary ms-1">V</span>
                @endif
              </div>
            </div>

            @if($isVariant && !empty($row['parent_name']))
              <div class="small text-muted">
                Parent:
                <a href="{{ route('items.show', $row['item_id']) }}">{{ $row['parent_name'] }}</a>
              </div>
            @endif

            <div class="mt-1 d-flex gap-2">
              @if($lowStock)
                <span class="badge bg-warning text-dark">Stok Rendah</span>
              @endif
              @if($inactive)
                <span class="badge bg-secondary">Nonaktif</span>
              @endif
            </div>
          </td>
          <td class="text-muted">{{ $row['sku'] ?? '-' }}</td>
          <td class="text-muted">{{ $row['brand'] ?? '-' }}</td>
          <td class="text-muted">{{ $size }}</td>
          <td class="text-muted">{{ $color }}</td>
          <td class="text-end">{{ $row['price_label'] ?? '-' }}</td>
          <td class="text-end">{{ $row['stock_label'] ?? '-' }}</td>
          <td class="text-end">
            <div class="btn-list justify-content-end">
              @if($isVariant)
                <a class="btn btn-outline-primary btn-sm"
                   href="{{ route('items.variants.index', $row['item_id']) }}#variant-{{ $row['variant_id'] }}">
                  Lihat
                </a>

                @hasanyrole('SuperAdmin|Admin')
                  <a class="btn btn-outline-primary btn-sm"
                     href="{{ route('variants.edit', $row['variant_id']) }}?r={{ urlencode(request()->fullUrl()) }}">
                    Ubah
                  </a>
                @endhasanyrole

                <a class="btn btn-outline-secondary btn-sm"
                   href="{{ route('items.show', $row['item_id']) }}">
                  Parent
                </a>
              @else
                <a class="btn btn-outline-primary btn-sm"
                   href="{{ route('items.show', $row['item_id']) }}">
                  Lihat
                </a>

                @hasanyrole('SuperAdmin|Admin')
                  <a class="btn btn-outline-primary btn-sm"
                     href="{{ route('items.edit', $row['item_id']) }}?r={{ urlencode(request()->fullUrl()) }}">
                    Ubah
                  </a>
                  <a class="btn btn-outline-secondary btn-sm"
                     href="{{ route('items.variants.index', $row['item_id']) }}">
                    Variant
                  </a>
                @endhasanyrole
              @endif
            </div>
          </td>
        </tr>
      @empty
        <tr>
          <td colspan="8" class="text-center text-muted">Tidak ada data untuk filter saat ini.</td>
        </tr>
      @endforelse
    </tbody>
  </table>
</div>

{{-- ===================== Mobile cards ===================== --}}
<div class="d-md-none">
  @forelse($rows as $row)
    @php
      $isVariant = ($row['entity'] ?? 'item') === 'variant';
      $inactive  = $row['inactive'] ?? false;
      $lowStock  = $row['low_stock'] ?? false;

      $size = $row['attributes']['size'] ?? '-';
      $color = $row['attributes']['color'] ?? '-';
    @endphp

    <div class="card mb-3 {{ $inactive ? 'text-muted' : '' }}">
      <div class="card-body">
        <div class="d-flex justify-content-between align-items-start gap-2">
          <div class="flex-grow-1">
            <div class="fw-semibold">
              {{ $row['display_name'] ?? '-' }}
              @if($isVariant)
                <span class="badge bg-primary-subtle text-primary ms-1">V</span>
              @endif
            </div>

            @if($isVariant && !empty($row['parent_name']))
              <div class="text-muted small mt-1">
                Parent:
                <a href="{{ route('items.show', $row['item_id']) }}">{{ $row['parent_name'] }}</a>
              </div>
            @endif

            <div class="text-muted small mt-2">
              SKU: {{ $row['sku'] ?? '-' }}
              • Brand: {{ $row['brand'] ?? '-' }}
            </div>

            <div class="text-muted small mt-1">
              Size: {{ $size }} • Color: {{ $color }}
            </div>

            <div class="text-muted small mt-1">
              {{ $row['price_label'] ?? '-' }} • Stok {{ $row['stock_label'] ?? '-' }}
            </div>

            <div class="d-flex gap-2 mt-2">
              @if($lowStock)
                <span class="badge bg-warning text-dark">Stok Rendah</span>
              @endif
              @if($inactive)
                <span class="badge bg-secondary">Nonaktif</span>
              @endif
            </div>
          </div>

          <div class="text-end">
            <div class="btn-list flex-column">
              @if($isVariant)
                <a class="btn btn-outline-primary btn-sm"
                   href="{{ route('items.variants.index', $row['item_id']) }}#variant-{{ $row['variant_id'] }}">
                  Lihat
                </a>

                @hasanyrole('SuperAdmin|Admin')
                  <a class="btn btn-outline-primary btn-sm"
                     href="{{ route('variants.edit', $row['variant_id']) }}?r={{ urlencode(request()->fullUrl()) }}">
                    Ubah
                  </a>
                @endhasanyrole

                <a class="btn btn-outline-secondary btn-sm"
                   href="{{ route('items.show', $row['item_id']) }}">
                  Parent
                </a>
              @else
                <a class="btn btn-outline-primary btn-sm"
                   href="{{ route('items.show', $row['item_id']) }}">
                  Lihat
                </a>

                @hasanyrole('SuperAdmin|Admin')
                  <a class="btn btn-outline-primary btn-sm"
                     href="{{ route('items.edit', $row['item_id']) }}?r={{ urlencode(request()->fullUrl()) }}">
                    Ubah
                  </a>
                  <a class="btn btn-outline-secondary btn-sm"
                     href="{{ route('items.variants.index', $row['item_id']) }}">
                    Variant
                  </a>
                @endhasanyrole
              @endif
            </div>
          </div>
        </div>
      </div>
    </div>
  @empty
    <div class="text-center text-muted">Tidak ada data untuk filter saat ini.</div>
  @endforelse
</div>
