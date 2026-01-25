{{-- resources/views/items/show.blade.php --}}
@extends('layouts.tabler')

@section('content')
<div class="container-xl">
  @php
    $isProjectItems = request()->routeIs('project-items.*');
    $editUrl = $isProjectItems ? route('project-items.edit', $item) : route('items.edit', $item);
    $backUrl = $isProjectItems ? route('project-items.index') : route('items.index');
    $parentShowRoute = $isProjectItems ? 'project-items.show' : 'items.show';
    $transferLabel = $isProjectItems ? 'Transfer ke Retail List' : 'Transfer ke Project List';
    $transferAction = $isProjectItems
      ? route('project-items.transfer-to-retail', $item)
      : route('items.transfer-to-project', $item);
    $transferConfirm = $isProjectItems
      ? 'Pindahkan item ini ke Retail List?'
      : 'Pindahkan item ini ke Project List?';
  @endphp

  <div class="card">
    {{-- ===================== Header (enterprise action hierarchy) ===================== --}}
    <div class="card-header d-flex align-items-center">
      <div class="card-title mb-0">{{ $isProjectItems ? 'Detail Project Item' : 'Detail Item' }}</div>

      <div class="ms-auto d-flex align-items-center gap-2">
        {{-- Primary CTA --}}
        <a href="{{ $editUrl }}" class="btn btn-warning btn-sm">Ubah</a>

        {{-- Overflow actions --}}
        <div class="dropdown">
          <button class="btn btn-outline-secondary btn-icon btn-sm" data-bs-toggle="dropdown" aria-label="Menu">
            <svg xmlns="http://www.w3.org/2000/svg" class="icon" width="20" height="20" viewBox="0 0 24 24"
                 stroke-width="2" stroke="currentColor" fill="none" stroke-linecap="round" stroke-linejoin="round"
                 aria-hidden="true">
              <path stroke="none" d="M0 0h24v24H0z" fill="none"></path>
              <circle cx="5" cy="12" r="1"></circle>
              <circle cx="12" cy="12" r="1"></circle>
              <circle cx="19" cy="12" r="1"></circle>
            </svg>
          </button>

          <div class="dropdown-menu dropdown-menu-end">
            <button class="dropdown-item" type="button" data-bs-toggle="modal" data-bs-target="#modalAdjust">
              Penyesuaian Stok
            </button>

            <a href="{{ route('items.variants.index', $item) }}" class="dropdown-item">
              Kelola Varian
            </a>

            <form method="POST" action="{{ $transferAction }}" onsubmit="return confirm('{{ $transferConfirm }}');">
              @csrf
              <button type="submit" class="dropdown-item">{{ $transferLabel }}</button>
            </form>

            <div class="dropdown-divider"></div>

            <a href="{{ $backUrl }}" class="dropdown-item">
              Kembali
            </a>
          </div>
        </div>
      </div>
    </div>

    <div class="card-body">
      @php
        $typeLabels = [
          'standard'   => 'Standard',
          'kit'        => 'Kit/Bundel',
          'cut_raw'    => 'Raw Roll (dipotong)',
          'cut_piece'  => 'Finished Piece (hasil potong)',
        ];
        $typeLabel = $typeLabels[$item->item_type] ?? ucfirst($item->item_type ?? 'standard');
      @endphp

      {{-- ===================== Scan-first Summary (NO price/stock here) ===================== --}}
      <div class="mb-3">
        <div class="min-w-0">
          {{-- Row 1: SKU + Type badge (ONLY ONE badge here) --}}
          <div class="d-flex align-items-center justify-content-between gap-2">
            <div class="text-muted small text-truncate">
              {{ $item->sku ?? '—' }}
            </div>
            <span class="badge bg-secondary-lt text-secondary-9 flex-shrink-0">
              {{ $typeLabel }}
            </span>
          </div>

          {{-- Row 2: Name --}}
          <div class="h3 m-0 text-truncate mt-1">
            {{ $item->name }}
          </div>

          {{-- Row 3: Micro meta --}}
          <div class="text-muted small mt-1">
            Unit:
            {{ $item->unit?->code ? $item->unit->code.' — '.$item->unit->name : ($item->unit?->name ?? '—') }}
            <span class="mx-1">•</span>
            Brand: {{ $item->brand?->name ?? '—' }}
          </div>
        </div>
      </div>

      <hr class="my-3">

      {{-- ===================== Detail (compact, grouped) ===================== --}}
      <div class="row g-2">

        {{-- Identitas --}}
        <div class="col-12">
          <div class="section-h">Identitas</div>
        </div>

        <div class="col-6 col-md-4">
          <div class="kv">
            <div class="kv-k">SKU / Kode</div>
            <div class="kv-v">{{ $item->sku ?? '—' }}</div>
          </div>
        </div>

        <div class="col-6 col-md-4">
          <div class="kv">
            <div class="kv-k">Unit</div>
            <div class="kv-v">
              {{ $item->unit?->code ? $item->unit->code.' — '.$item->unit->name : ($item->unit?->name ?? '—') }}
            </div>
          </div>
        </div>

        <div class="col-6 col-md-4">
          <div class="kv">
            <div class="kv-k">Brand</div>
            <div class="kv-v">{{ $item->brand?->name ?? '—' }}</div>
          </div>
        </div>

        {{-- Harga & Stok (VISUAL STRONG) --}}
        <div class="col-12 mt-2">
          <div class="section-h">Harga & Stok</div>
        </div>

        <div class="col-6 col-md-4">
          <div class="metric-box">
            <div class="metric-k">Harga (Rp)</div>
            <div class="metric-v">Rp {{ $item->price_id }}</div>
          </div>
        </div>

        <div class="col-6 col-md-4">
          <div class="metric-box">
            <div class="metric-k">Stok</div>
            <div class="metric-v">{{ $item->stock }}</div>
          </div>
        </div>

        <div class="col-6 col-md-4">
          <div class="kv">
            <div class="kv-k">Tipe Item</div>
            <div class="kv-v">{{ $typeLabel }}</div>
          </div>
        </div>

        {{-- Atribut --}}
        <div class="col-12 mt-2">
          <div class="section-h">Atribut</div>
        </div>

        <div class="col-6 col-md-4">
          <div class="kv">
            <div class="kv-k">Size</div>
            <div class="kv-v">{{ $item->size?->name ?? '—' }}</div>
          </div>
        </div>

        <div class="col-6 col-md-4">
          <div class="kv">
            <div class="kv-k">Color</div>
            <div class="kv-v d-inline-flex align-items-center">
              @if($item->color)
                @if(!empty($item->color->hex))
                  <i class="me-2" style="display:inline-block;width:12px;height:12px;border-radius:50%;border:1px solid #ddd;background:{{ $item->color->hex }}"></i>
                @endif
                {{ $item->color->name }}
              @else
                <span class="text-muted">—</span>
              @endif
            </div>
          </div>
        </div>

        <div class="col-6 col-md-4">
          <div class="kv">
            <div class="kv-k">Family Code</div>
            <div class="kv-v">{{ $item->family_code ?: '—' }}</div>
          </div>
        </div>

        <div class="col-12 col-md-6">
          <div class="kv">
            <div class="kv-k">Parent</div>
            <div class="kv-v">
              @if($item->parent)
                <a href="{{ route($parentShowRoute, $item->parent) }}">{{ $item->parent->name }}</a>
              @else
                <span class="text-muted">—</span>
              @endif
            </div>
          </div>
        </div>

        {{-- Flags --}}
        <div class="col-12 mt-2">
          <div class="section-h">Flags</div>
        </div>

        <div class="col-6 col-md-4">
          <div class="kv">
            <div class="kv-k">Sellable</div>
            <div class="kv-v">
              @if($item->sellable)
                <span class="text-success d-inline-flex align-items-center gap-1">
                  <i class="ti ti-check"></i><span class="small fw-semibold">Ya</span>
                </span>
              @else
                <span class="text-muted d-inline-flex align-items-center gap-1">
                  <i class="ti ti-x"></i><span class="small">Tidak</span>
                </span>
              @endif
            </div>
          </div>
        </div>

        <div class="col-6 col-md-4">
          <div class="kv">
            <div class="kv-k">Purchasable</div>
            <div class="kv-v">
              @if($item->purchasable)
                <span class="text-success d-inline-flex align-items-center gap-1">
                  <i class="ti ti-check"></i><span class="small fw-semibold">Ya</span>
                </span>
              @else
                <span class="text-muted d-inline-flex align-items-center gap-1">
                  <i class="ti ti-x"></i><span class="small">Tidak</span>
                </span>
              @endif
            </div>
          </div>
        </div>

        @if(!empty($item->default_roll_length))
          <div class="col-6 col-md-4">
            <div class="kv">
              <div class="kv-k">Default Roll Length</div>
              <div class="kv-v">{{ $item->default_roll_length }}</div>
            </div>
          </div>
        @endif

        @if(!empty($item->length_per_piece))
          <div class="col-6 col-md-4">
            <div class="kv">
              <div class="kv-k">Length per Piece</div>
              <div class="kv-v">{{ $item->length_per_piece }}</div>
            </div>
          </div>
        @endif

        {{-- Deskripsi (jangan makan tempat kalau kosong) --}}
        @if(!empty($item->description))
          <div class="col-12 mt-2">
            <div class="section-h">Deskripsi</div>
            <div class="kv-v" style="white-space: pre-line;">{{ $item->description }}</div>
          </div>
        @endif

        {{-- Metadata collapsed --}}
        <div class="col-12 mt-2">
          <details class="mt-1">
            <summary class="text-muted small">Metadata</summary>
            <div class="row g-2 mt-2">
              <div class="col-6 col-md-4">
                <div class="kv">
                  <div class="kv-k">Dibuat</div>
                  <div class="kv-v">{{ optional($item->created_at)->format('d M Y H:i') ?? '—' }}</div>
                </div>
              </div>
              <div class="col-6 col-md-4">
                <div class="kv">
                  <div class="kv-k">Diubah</div>
                  <div class="kv-v">{{ optional($item->updated_at)->format('d M Y H:i') ?? '—' }}</div>
                </div>
              </div>
            </div>
          </details>
        </div>
      </div>
    </div>
  </div>
</div>

{{-- ======================= Modal Penyesuaian Stok (logic dipertahankan) ======================= --}}
@php
  $__company    = $company ?? \App\Models\Company::where('is_default', true)->first();
  $__warehouses = \App\Models\Warehouse::orderBy('name')->get(['id','name']);
  $__warehouse  = $__warehouses->first();
  $__variantId  = $currentVariant->id ?? null;

  $__onhand = 0.0;
  if ($__company && $__warehouse) {
    $__onhand = \App\Models\ItemStock::query()
      ->where('company_id', $__company->id)
      ->where('warehouse_id', $__warehouse->id)
      ->where('item_id', $item->id)
      ->when($__variantId,
        fn($q) => $q->where('item_variant_id', $__variantId),
        fn($q) => $q->whereNull('item_variant_id'))
      ->value('qty_on_hand') ?? 0;
  }
@endphp

<div class="modal fade" id="modalAdjust" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-sm modal-dialog-centered">
    <form method="POST" action="{{ route('stocks.adjust', $item) }}" id="stockAdjustForm" class="modal-content">
      @csrf
      <div class="modal-header">
        <div>
          <h5 class="modal-title mb-0">Penyesuaian Stok</h5>
          <div class="text-secondary small fw-normal mt-1">
            {{ $item->name }}
            @if(!empty($item->sku)) • <span class="text-muted">{{ $item->sku }}</span> @endif
          </div>
        </div>
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
          <select class="form-select" id="warehouseId" name="warehouse_id" required>
            @foreach($__warehouses as $wh)
              <option value="{{ $wh->id }}" @selected($__warehouse && $wh->id === $__warehouse->id)>{{ $wh->name }}</option>
            @endforeach
          </select>
        </div>

        <div class="mb-2">
          <label class="form-label">Stock Awal</label>
          <input type="text" class="form-control" id="stockAwal" value="{{ number_format($__onhand,2) }}" readonly>
        </div>

        <div class="row g-2">
          <div class="col-5">
            <label class="form-label">Tipe</label>
            <select name="type" id="adjType" class="form-select">
              <option value="in">IN (+)</option>
              <option value="out">OUT (−)</option>
            </select>
          </div>
          <div class="col-7">
            <label class="form-label">Qty</label>
            <input type="number" step="0.0001" min="0.0001" name="qty" id="adjQty" class="form-control" required>
          </div>
        </div>

        <div class="mt-3">
          <label class="form-label">Stock Akhir (preview)</label>
          <input type="text" class="form-control fw-bold" id="stockAkhir" value="{{ number_format($__onhand,2) }}" readonly>
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
  let awal    = parseFloat((document.getElementById('stockAwal').value || '0').replace(/,/g,'')) || 0;
  const tipeEl= document.getElementById('adjType');
  const qtyEl = document.getElementById('adjQty');
  const akhir = document.getElementById('stockAkhir');
  const whEl  = document.getElementById('warehouseId');

  function recalc(){
    const t = tipeEl.value;
    const q = parseFloat(qtyEl.value || 0);
    const val = awal + (t === 'in' ? q : -q);
    akhir.value = (isFinite(val) ? val : 0).toFixed(2);
  }

  tipeEl.addEventListener('change', recalc);
  qtyEl.addEventListener('input', recalc);
});
</script>

@push('styles')
<style>
  /* Compact sections */
  .section-h{
    font-weight: 700;
    font-size: .85rem;
    color: var(--tblr-muted);
    margin-bottom: .25rem;
  }

  /* Compact label-value */
  .kv { padding: .20rem 0; }
  .kv-k { font-size: .75rem; color: var(--tblr-muted); line-height: 1.1; }
  .kv-v { margin-top: .12rem; line-height: 1.25; }

  /* Strong metrics (Price/Stock) */
  .metric-box{
    border: 1px solid rgba(0,0,0,.08);
    border-radius: .5rem;
    padding: .5rem .6rem;
    background: rgba(0,0,0,.015);
  }
  .metric-k{
    font-size: .75rem;
    color: var(--tblr-muted);
    line-height: 1.1;
  }
  .metric-v{
    margin-top: .2rem;
    font-weight: 800;
    font-size: 1.15rem;
    line-height: 1.2;
    letter-spacing: .2px;
    white-space: nowrap;
  }

  @media (max-width: 767.98px){
    .h3 { font-size: 1.1rem; }
  }
</style>
@endpush
@endsection
