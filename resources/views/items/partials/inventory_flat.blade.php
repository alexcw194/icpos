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
  $returnUrl = request()->fullUrl();
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

          $itemId = $row['item_id'];
          $variantId = $row['variant_id'] ?? null;

          $itemView = route('items.show', $itemId);

          // admin-only edit URLs keep return
          $itemEdit = route('items.edit', $itemId) . '?r=' . urlencode($returnUrl);

          $variantView = $isVariant
            ? (route('items.variants.index', $itemId) . '#variant-' . $variantId)
            : null;

          $variantEdit = $isVariant && $variantId
            ? (route('variants.edit', $variantId) . '?r=' . urlencode($returnUrl))
            : null;
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
                <a href="{{ route('items.show', $itemId) }}">{{ $row['parent_name'] }}</a>
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
                @include('layouts.partials.crud_actions', [
                  'view'   => $variantView,
                  'edit'   => auth()->user()?->hasAnyRole('SuperAdmin|Admin') ? $variantEdit : null,
                  'delete' => null, // enterprise safety: no delete in list
                  'size'   => 'sm',
                ])

                <a class="btn btn-outline-secondary btn-sm"
                   href="{{ $itemView }}">
                  Parent
                </a>
              @else
                @include('layouts.partials.crud_actions', [
                  'view'   => $itemView,
                  'edit'   => auth()->user()?->hasAnyRole('SuperAdmin|Admin') ? $itemEdit : null,
                  'delete' => null, // enterprise safety: no delete in list
                  'size'   => 'sm',
                ])

                @hasanyrole('SuperAdmin|Admin')
                  <a class="btn btn-outline-secondary btn-sm"
                     href="{{ route('items.variants.index', $itemId) }}">
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

      $itemId = $row['item_id'];
      $variantId = $row['variant_id'] ?? null;

      $itemView = route('items.show', $itemId);
      $itemEdit = route('items.edit', $itemId) . '?r=' . urlencode($returnUrl);

      $variantView = $isVariant
        ? (route('items.variants.index', $itemId) . '#variant-' . $variantId)
        : null;

      $variantEdit = $isVariant && $variantId
        ? (route('variants.edit', $variantId) . '?r=' . urlencode($returnUrl))
        : null;

      $detailId = 'inv-detail-' . ($row['entity'] ?? 'item') . '-' . ($variantId ?? $itemId);
    @endphp

    <div class="card mb-3 {{ $inactive ? 'text-muted' : '' }}">
      <div class="card-body">
        <div class="d-flex justify-content-between align-items-start gap-2">
          <div class="flex-grow-1">
            {{-- Row 1: Identity + badges --}}
            <div class="d-flex justify-content-between align-items-center">
              <div class="text-muted small">
                {{ $row['sku'] ?? '—' }}
              </div>
              <div class="d-flex gap-1">
                @if($isVariant)
                  <span class="badge bg-primary-subtle text-primary">V</span>
                @endif
                @if($lowStock)
                  <span class="badge bg-warning text-dark">Stok Rendah</span>
                @endif
                @if($inactive)
                  <span class="badge bg-secondary">Nonaktif</span>
                @endif
              </div>
            </div>

            {{-- Row 2: Name (primary) --}}
            <div class="fw-semibold mt-1">
              {{ $row['display_name'] ?? '-' }}
            </div>

            {{-- Row 3: Decision info --}}
            <div class="d-flex justify-content-between align-items-end mt-1">
              <div class="fw-semibold">
                {{ $row['price_label'] ?? '-' }}
              </div>

              <div class="d-flex align-items-center gap-2">
                <div class="text-muted small">
                  Stok {{ $row['stock_label'] ?? '-' }}
                </div>

                <a class="small text-decoration-none" data-bs-toggle="collapse"
                  href="#{{ $detailId }}" role="button"
                  aria-expanded="false" aria-controls="{{ $detailId }}">
                  Detail
                </a>
              </div>
            </div>

            <div class="collapse mt-2" id="{{ $detailId }}">
              <div class="text-muted small">
                Brand: {{ $row['brand'] ?? '-' }} • Size: {{ $size }} • Color: {{ $color }}
              </div>

              @if($isVariant && !empty($row['parent_name']))
                <div class="text-muted small mt-1">
                  Parent:
                  <a href="{{ route('items.show', $itemId) }}">{{ $row['parent_name'] }}</a>
                </div>
              @endif
            </div>
          </div>

          {{-- Actions --}}
          <div class="text-end">
            <div class="btn-list flex-column">
              @if($isVariant)
                @include('layouts.partials.crud_actions', [
                  'view'   => $variantView,
                  'edit'   => auth()->user()?->hasAnyRole('SuperAdmin|Admin') ? $variantEdit : null,
                  'delete' => null, // enterprise safety: no delete in list
                  'size'   => 'sm',
                ])

                <a class="btn btn-outline-secondary btn-sm"
                   href="{{ $itemView }}">
                  Parent
                </a>
              @else
                @include('layouts.partials.crud_actions', [
                  'view'   => $itemView,
                  'edit'   => auth()->user()?->hasAnyRole('SuperAdmin|Admin') ? $itemEdit : null,
                  'delete' => null, // enterprise safety: no delete in list
                  'size'   => 'sm',
                ])

                @hasanyrole('SuperAdmin|Admin')
                  <a class="btn btn-outline-secondary btn-sm"
                     href="{{ route('items.variants.index', $itemId) }}">
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
