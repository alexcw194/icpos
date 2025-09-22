@extends('layouts.tabler')

@section('content')
<div class="container-xl">
  <div class="card">
    <div class="card-header">
      <div class="card-title">Detail Item</div>
      <div class="ms-auto btn-list">
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
@endsection
