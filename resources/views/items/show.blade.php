{{-- resources/views/items/show.blade.php --}}
@extends('layouts.tabler')

@section('content')
<div class="container-xl">
  <div class="card">
    <div class="card-header d-flex align-items-center">
      <div class="card-title mb-0">Detail Item</div>

      <div class="ms-auto d-flex align-items-center gap-2">
        {{-- Primary CTA --}}
        <a href="{{ route('items.edit', $item) }}" class="btn btn-warning btn-sm">Ubah</a>

        {{-- Overflow actions (enterprise) --}}
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

            <div class="dropdown-divider"></div>

            <a href="{{ route('items.index') }}" class="dropdown-item">
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

      {{-- ===================== Scan-first Summary (ERP) ===================== --}}
      <div class="mb-3">
        <div class="d-flex align-items-start justify-content-between gap-3">
          <div class="min-w-0">
            <div class="text-muted small">
              {{ $item->sku ?? '—' }}
            </div>

            <div class="h3 m-0 text-truncate">
              {{ $item->name }}
            </div>

            <div class="text-muted small mt-1">
              Unit:
              {{ $item->unit?->code ? $item->unit->code.' — '.$item->unit->name : ($item->unit?->name ?? '—') }}
              <span class="mx-1">•</span>
              Brand: {{ $item->brand?->name ?? '—' }}
            </div>
          </div>

          <div class="text-end flex-shrink-0">
            <div class="badge bg-secondary-lt text-secondary-9">{{ $typeLabel }}</div>

            <div class="mt-2 fw-bold">
              Rp {{ $item->price_id }}
            </div>
            <div class="text-muted small">
              Stok {{ $item->stock }}
            </div>
          </div>
        </div>
      </div>

      <hr class="my-3">

      {{-- ===================== Detail (compact, grouped) ===================== --}}
      <div class="row g-2">
        {{-- Identity --}}
        <div class="col-12">
          <div class="text-secondary fw-bold small mb-1">Identitas</div>
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

        {{-- Commerce --}}
        <div class="col-12 mt-2">
          <div class="text-secondary fw-bold small mb-1">Harga & Stok</div>
        </div>

        <div class="col-6 col-md-4">
          <div class="kv">
            <div class="kv-k">Harga (Rp)</div>
            <div class="kv-v fw-bold">Rp {{ $item->price_id }}</div>
          </div>
        </div>

        <div class="col-6 col-md-4">
          <div class="kv">
            <div class="kv-k">Stok</div>
            <div class="kv-v fw-bold">{{ $item->stock }}</div>
          </div>
        </div>

        <div class="col-6 col-md-4">
          <div class="kv">
            <div class="kv-k">Tipe Item</div>
            <div class="kv-v">{{ $typeLabel }}</div>
          </div>
        </div>

        {{-- Attributes --}}
        <div class="col-12 mt-2">
          <div class="text-secondary fw-bold small mb-1">Atribut</div>
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
                @if($item->color->hex)
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
                <a href="{{ route('items.show', $item->parent) }}">{{ $item->parent->name }}</a>
              @else
                <span class="text-muted">—</span>
              @endif
            </div>
          </div>
        </div>

        {{-- Flags --}}
        <div class="col-12 mt-2">
          <div class="text-secondary fw-bold small mb-1">Flags</div>
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

        @if($item->default_roll_length)
          <div class="col-6 col-md-4">
            <div class="kv">
              <div class="kv-k">Default Roll Length</div>
              <div class="kv-v">{{ $item->default_roll_length }}</div>
            </div>
          </div>
        @endif

        @if($item->length_per_piece)
          <div class="col-6 col-md-4">
            <div class="kv">
              <div class="kv-k">Length per Piece</div>
              <div class="kv-v">{{ $item->length_per_piece }}</div>
            </div>
          </div>
        @endif

        {{-- Description (hide noise when empty) --}}
        @if(!empty($item->description))
          <div class="col-12 mt-2">
            <div class="text-secondary fw-bold small mb-1">Deskripsi</div>
            <div class="kv-v" style="white-space: pre-line;">{{ $item->description }}</div>
          </div>
        @endif

        {{-- Metadata (low priority, collapsed) --}}
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

{{-- =======================  [ADD ONLY] Modal Penyesuaian Stok  ======================= --}}
@php
  // Resolve default company & default warehouse for initial preview only
  $__company    = $company ?? \App\Models\Company::where('is_default', true)->first();
  $__warehouses = \App\Models\Warehouse::orderBy('name')->get(['id','name']);
  $__warehouse  = $__warehouses->first(); // fallback default
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

{{-- Inline JS: preview + live on-hand when warehouse changes --}}
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

  async function refreshOnHand(){
    // Keep as-is for now (no API yet). If you add an endpoint later, fetch and update `awal` here.
    awal = parseFloat((document.getElementById('stockAwal').value || '0').replace(/,/g,'')) || awal;
    recalc();
  }

  tipeEl.addEventListener('change', recalc);
  qtyEl.addEventListener('input', recalc);
  whEl.addEventListener('change', refreshOnHand);

  // Accessibility fix: clear focus before hide, return to trigger after hidden
  const modalEl   = document.getElementById('modalAdjust');
  const triggerEl = document.querySelector('[data-bs-target="#modalAdjust"]');
  modalEl.addEventListener('hide.bs.modal',   () => document.activeElement?.blur());
  modalEl.addEventListener('hidden.bs.modal', () => triggerEl?.focus({ preventScroll: true }));
});
</script>

@push('styles')
<style>
  /* Compact label-value (ERP density) */
  .kv { padding: .25rem 0; }
  .kv-k { font-size: .75rem; color: var(--tblr-muted); line-height: 1.1; }
  .kv-v { margin-top: .15rem; line-height: 1.25; }

  /* Make summary tighter on mobile */
  @media (max-width: 767.98px){
    .card-body { padding-top: 1rem; padding-bottom: 1rem; }
    .h3 { font-size: 1.1rem; }
  }
</style>
@endpush
@endsection
