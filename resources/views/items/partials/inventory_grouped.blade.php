<div class="table-responsive d-none d-md-block">
  <table class="table table-hover align-middle inventory-table">
    <thead>
      <tr>
        <th class="w-1"></th>
        <th>Item</th>
        <th class="text-end">Harga & Stok</th>
        <th class="w-1"></th>
      </tr>
    </thead>
    <tbody>
      @forelse($rows as $row)
        @php
          $item = $row['item'];
          $hasVariants = $row['has_variants'];
          $variantCount = $row['variant_count'];
          $badgeText = $hasVariants ? 'Varian (' . $variantCount . ')' : 'Single';
          $badgeClass = $hasVariants ? 'bg-primary text-white' : 'bg-light text-muted';
          $chipSize  = $row['chips']['size']  ?? collect();
          $chipColor = $row['chips']['color'] ?? collect();

          $formatChips = function ($collection, $label) {
            if ($collection->isEmpty()) {
              return null;
            }
            $display = $collection->take(3)->implode(', ');
            $extra   = $collection->count() > 3 ? ' (+' . ($collection->count() - 3) . ')' : '';
            return $label . ': ' . $display . $extra;
          };

          $sizeSummary  = $formatChips($chipSize, 'Size');
          $colorSummary = $formatChips($chipColor, 'Color');
        @endphp
        <tr class="inventory-row inventory-row--grouped">
          <td class="text-center">
            @if($hasVariants)
              <button class="btn btn-link p-0 text-decoration-none variant-expand" type="button" data-bs-toggle="collapse" data-bs-target="#item-{{ $item->id }}" aria-expanded="false" aria-controls="item-{{ $item->id }}">▸</button>
            @endif
          </td>
          <td class="text-wrap">
            <div class="d-flex align-items-center gap-2">
              <span class="badge {{ $badgeClass }} align-self-start">{{ $badgeText }}</span>
              <div>
                <div class="fw-semibold">{{ $item->name }}</div>
                <div class="text-muted small mt-1">SKU: {{ $item->sku ?: '-' }} • {{ optional($item->brand)->name ?: '-' }} • {{ optional($item->unit)->code ?: optional($item->unit)->name ?: '-' }}</div>
                <div class="text-muted small d-flex flex-wrap gap-2 mt-1">
                  @if($sizeSummary)
                    <span>{{ $sizeSummary }}</span>
                  @endif
                  @if($colorSummary)
                    <span>{{ $colorSummary }}</span>
                  @endif
                </div>
              </div>
            </div>
          </td>
          <td class="text-end">
            <div class="fw-semibold">{{ $row['price_label'] }}</div>
            <div class="text-muted small">Stok: {{ $row['stock_label'] }}</div>
          </td>
          <td class="text-end">
            <div class="row-actions d-inline-flex align-items-center gap-2">
              {{-- Enterprise safety: NO delete in list --}}
              @include('layouts.partials.crud_actions', [
                'view' => route('items.show', $item),
                'edit' => route('items.edit', $item),
                'delete' => null,
                'size' => 'sm',
              ])

              @if($hasVariants)
                <a href="{{ route('items.variants.index', $item) }}" class="btn btn-sm btn-outline-secondary d-none d-md-inline-flex">Kelola Varian</a>
                <a href="{{ route('items.variants.index', $item) }}" class="btn btn-icon btn-sm btn-outline-secondary d-md-none" aria-label="Kelola Varian" title="Kelola Varian">
                  <svg xmlns="http://www.w3.org/2000/svg" class="icon" width="20" height="20" viewBox="0 0 24 24"
                       stroke-width="2" stroke="currentColor" fill="none" stroke-linecap="round" stroke-linejoin="round"
                       aria-hidden="true">
                    <path stroke="none" d="M0 0h24v24H0z" fill="none"></path>
                    <path d="M4 7h16"></path>
                    <path d="M4 12h16"></path>
                    <path d="M4 17h16"></path>
                  </svg>
                </a>
              @endif
            </div>
          </td>
        </tr>
        @if($hasVariants)
          <tr class="collapse" id="item-{{ $item->id }}">
            <td colspan="4">
              <div class="variant-preview-table table-responsive">
                <table class="table table-sm mb-0">
                  <thead>
                    <tr>
                      <th>Label</th>
                      <th>SKU</th>
                      <th>Harga</th>
                      <th>Stok</th>
                      <th>Status</th>
                    </tr>
                  </thead>
                  <tbody>
                    @foreach($row['preview'] as $preview)
                      <tr>
                        <td>{{ $preview['label'] }}</td>
                        <td>{{ $preview['sku'] ?: '-' }}</td>
                        <td>Rp {{ $preview['price'] }}</td>
                        <td>{{ $preview['stock'] }}</td>
                        <td>
                          @if($preview['active'])
                            <span class="badge bg-success">Aktif</span>
                          @else
                            <span class="badge bg-secondary">Nonaktif</span>
                          @endif
                        </td>
                      </tr>
                    @endforeach
                  </tbody>
                </table>
              </div>
              <div class="mt-2">
                <a href="{{ route('items.variants.index', $item) }}">Lihat semua varian</a>
              </div>
            </td>
          </tr>
        @endif
      @empty
        <tr>
          <td colspan="4" class="text-center text-muted">Tidak ada data.</td>
        </tr>
      @endforelse
    </tbody>
  </table>
</div>

<div class="d-md-none">
  @forelse($rows as $row)
    @php
      $item = $row['item'];
    @endphp
    <div class="card inventory-card mb-3">
      <div class="card-body">
        <div class="d-flex justify-content-between align-items-start">
          <div>
            <div class="fw-semibold">{{ $item->name }}</div>
            <div class="text-muted small mt-1">SKU: {{ $item->sku ?: '-' }} • {{ optional($item->brand)->name ?: '-' }} • {{ optional($item->unit)->code ?: optional($item->unit)->name ?: '-' }}</div>
            <div class="text-muted small mt-1">{{ $row['price_label'] }} • Stok {{ $row['stock_label'] }}</div>
          </div>
          <span class="badge {{ $row['has_variants'] ? 'bg-primary text-white' : 'bg-light text-muted' }}">{{ $row['has_variants'] ? 'Varian (' . $row['variant_count'] . ')' : 'Single' }}</span>
        </div>

        @if($row['preview']->isNotEmpty())
          <div class="mt-3">
            @foreach($row['preview'] as $preview)
              <div class="d-flex justify-content-between small border-top py-1">
                <div>
                  <div>{{ $preview['label'] }}</div>
                  <div class="text-muted">SKU {{ $preview['sku'] ?: '-' }}</div>
                </div>
                <div class="text-end">
                  <div>Rp {{ $preview['price'] }}</div>
                  <div class="text-muted">Stok {{ $preview['stock'] }}</div>
                </div>
              </div>
            @endforeach
            <a href="{{ route('items.variants.index', $item) }}" class="small">Lihat semua varian</a>
          </div>
        @endif

        <div class="row-actions mt-3 d-flex align-items-center gap-2">
          {{-- Enterprise safety: NO delete in list --}}
          @include('layouts.partials.crud_actions', [
            'view' => route('items.show', $item),
            'edit' => route('items.edit', $item),
            'delete' => null,
            'size' => 'sm',
          ])

          @if($row['has_variants'])
            <a href="{{ route('items.variants.index', $item) }}" class="btn btn-outline-secondary btn-sm">Kelola Varian</a>
          @endif
        </div>
      </div>
    </div>
  @empty
    <div class="text-center text-muted">Tidak ada data.</div>
  @endforelse
</div>
