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
                <a href="{{ route('items.show', $row['item_id']) }}">Ke Parent</a>
              @else
                <a href="{{ route('items.show', $row['item_id']) }}" class="me-2">Lihat</a>
                <a href="{{ route('items.edit', $row['item_id']) }}" class="me-2">Ubah</a>
                <a href="{{ route('items.variants.index', $row['item_id']) }}">Kelola Varian</a>
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

<div class="d-md-none">
  @forelse($rows as $row)
    @php
      $isVariant = $row['entity'] === 'variant';
      $sizeValue = $row['attributes']['size'] ?? '-';
      $colorValue = $row['attributes']['color'] ?? '-';
      $skuValue   = $row['sku'] ?: '-';
      $brandValue = $row['brand'] ?: '-';
    @endphp
    <div class="card inventory-card mb-3 {{ $row['inactive'] ? 'is-inactive' : '' }}">
      <div class="card-body">
        <div class="d-flex justify-content-between">
          <div>
            <div class="fw-semibold">{{ $row['display_name'] }}</div>
            <div class="text-muted small mt-1">SKU: {{ $skuValue }}</div>
            <div class="text-muted small">Brand: {{ $brandValue }}</div>
            <div class="text-muted small">Size: {{ $sizeValue }} • Color: {{ $colorValue }}</div>
            @if($isVariant && !empty($row['parent_name']))
              <div class="text-muted small">Parent: <a href="{{ route('items.show', $row['item_id']) }}">{{ $row['parent_name'] }}</a></div>
            @endif
          </div>
          @if($isVariant)
            <span class="badge bg-primary-subtle text-primary">V</span>
          @endif
        </div>
        <div class="d-flex flex-wrap gap-2 mt-2">
          <span class="fw-semibold">Harga: {{ $row['price_label'] }}</span>
          <span class="fw-semibold">Stok: {{ $row['stock_label'] }}</span>
        </div>
        <div class="d-flex gap-2 mt-2">
          @if($row['low_stock'])
            <span class="badge bg-warning text-dark">Stok Rendah</span>
          @endif
          @if($row['inactive'])
            <span class="badge bg-secondary">Nonaktif</span>
          @endif
        </div>
        <div class="d-flex flex-wrap gap-2 mt-2 text-muted small">
          <span>{{ $row['unit'] ?: '-' }}</span>
        </div>
        <div class="row-actions mt-3">
          @if($isVariant)
            <a href="{{ route('items.variants.index', $row['item_id']) }}#variant-{{ $row['variant_id'] }}" class="btn btn-outline-primary btn-sm">Lihat</a>
            <a href="{{ route('variants.edit', $row['variant_id']) }}" class="btn btn-outline-primary btn-sm">Ubah</a>
            <a href="{{ route('items.show', $row['item_id']) }}" class="btn btn-outline-secondary btn-sm">Ke Parent</a>
          @else
            <a href="{{ route('items.show', $row['item_id']) }}" class="btn btn-outline-primary btn-sm">Lihat</a>
            <a href="{{ route('items.edit', $row['item_id']) }}" class="btn btn-outline-primary btn-sm">Ubah</a>
            <a href="{{ route('items.variants.index', $row['item_id']) }}" class="btn btn-outline-secondary btn-sm">Kelola Varian</a>
          @endif
        </div>
      </div>
    </div>
  @empty
    <div class="text-center text-muted">Tidak ada data.</div>
  @endforelse
</div>
