@extends('layouts.tabler')

@section('content')
<div class="container-xl">
  <div class="card">
    <div class="card-header">
      <div class="card-title">Detail Item</div>
      <div class="ms-auto btn-list">
        <button class="btn btn-outline-primary" data-bs-toggle="modal" data-bs-target="#modalAdjust">
          Penyesuaian Stok
        </button>
        <a href="{{ route('items.edit', $item) }}" class="btn btn-warning">Edit</a>
        <form action="{{ route('items.destroy', $item) }}" method="POST" class="d-inline" onsubmit="return confirm('Hapus item ini?')">
          @csrf @method('DELETE')
          <button class="btn btn-danger">Delete</button>
        </form>
        <a href="{{ route('items.variants.index', $item) }}" class="btn btn-primary">Kelola Varian</a>
        <a href="{{ route('items.index') }}" class="btn btn-secondary">Kembali</a>
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
      @endphp

      <div class="row g-3">
        <div class="col-md-6">
          <label class="form-label">Nama</label>
          <div class="form-control-plaintext fw-bold">{{ $item->name }}</div>
        </div>
        <div class="col-md-6">
          <label class="form-label">SKU / Kode</label>
          <div class="form-control-plaintext">{{ $item->sku ?? '—' }}</div>
        </div>

        <div class="col-md-6">
          <label class="form-label">Unit</label>
          <div class="form-control-plaintext">
            {{ $item->unit?->code ? $item->unit->code.' — '.$item->unit->name : ($item->unit?->name ?? '—') }}
          </div>
        </div>
        <div class="col-md-6">
          <label class="form-label">Brand</label>
          <div class="form-control-plaintext">{{ $item->brand?->name ?? '—' }}</div>
        </div>

        <div class="col-md-6">
          <label class="form-label">Harga (Rp)</label>
          <div class="form-control-plaintext">Rp {{ $item->price_id }}</div>
        </div>
        <div class="col-md-6">
          <label class="form-label">Stok</label>
          <div class="form-control-plaintext">{{ $item->stock }}</div>
        </div>

        {{-- ===== Atribut Item: Size & Color ===== --}}
        <div class="col-12"><hr></div>
        <div class="col-12">
          <div class="text-secondary fw-bold small mb-1">Atribut Item</div>
        </div>

        <div class="col-md-6">
          <label class="form-label">Size</label>
          <div class="form-control-plaintext">
            {{ $item->size?->name ?? '—' }}
          </div>
        </div>
        <div class="col-md-6">
          <label class="form-label">Color</label>
          <div class="form-control-plaintext d-inline-flex align-items-center">
            @if($item->color)
              @if($item->color->hex)
                <i class="me-2" style="display:inline-block;width:14px;height:14px;border-radius:50%;border:1px solid #ddd;background:{{ $item->color->hex }}"></i>
              @endif
              {{ $item->color->name }}
            @else
              —
            @endif
          </div>
        </div>

        {{-- ===== Info Varian & Cutting ===== --}}
        <div class="col-md-4">
          <label class="form-label">Tipe Item</label>
          <div class="form-control-plaintext">{{ $typeLabels[$item->item_type] ?? ucfirst($item->item_type ?? 'standard') }}</div>
        </div>
        <div class="col-md-4">
          <label class="form-label">Family Code</label>
          <div class="form-control-plaintext">{{ $item->family_code ?: '—' }}</div>
        </div>
        <div class="col-md-4">
          <label class="form-label">Parent</label>
          <div class="form-control-plaintext">
            @if($item->parent)
              <a href="{{ route('items.show', $item->parent) }}">{{ $item->parent->name }}</a>
            @else
              —
            @endif
          </div>
        </div>

        <div class="col-md-4">
          <label class="form-label">Sellable</label>
          <div class="form-control-plaintext">
            @if($item->sellable)
              <span class="badge bg-success">Ya</span>
            @else
              <span class="badge bg-secondary">Tidak</span>
            @endif
          </div>
        </div>
        <div class="col-md-4">
          <label class="form-label">Purchasable</label>
          <div class="form-control-plaintext">
            @if($item->purchasable)
              <span class="badge bg-success">Ya</span>
            @else
              <span class="badge bg-secondary">Tidak</span>
            @endif
          </div>
        </div>

        @if($item->default_roll_length)
          <div class="col-md-4">
            <label class="form-label">Default Roll Length</label>
            <div class="form-control-plaintext">{{ $item->default_roll_length }}</div>
          </div>
        @endif

        @if($item->length_per_piece)
          <div class="col-md-4">
            <label class="form-label">Length per Piece</label>
            <div class="form-control-plaintext">{{ $item->length_per_piece }}</div>
          </div>
        @endif

        <div class="col-12">
          <label class="form-label">Deskripsi</label>
          <div class="form-control-plaintext" style="white-space: pre-line;">
            {{ $item->description ?: '—' }}
          </div>
        </div>

        <div class="col-md-6">
          <label class="form-label">Dibuat</label>
          <div class="form-control-plaintext">{{ optional($item->created_at)->format('d M Y H:i') ?? '—' }}</div>
        </div>
        <div class="col-md-6">
          <label class="form-label">Diubah</label>
          <div class="form-control-plaintext">{{ optional($item->updated_at)->format('d M Y H:i') ?? '—' }}</div>
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
{{-- =======================  [END ADD] Modal Penyesuaian Stok  ======================= --}}
@endsection
