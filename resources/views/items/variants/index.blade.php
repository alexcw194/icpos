@extends('layouts.tabler')

@section('content')
<div class="container-xl">
  @if(session('success'))
    <div class="alert alert-success">{{ session('success') }}</div>
  @endif

  <div class="card">
    <div class="card-header">
      <div class="card-title">Varian Item - {{ $item->name }}</div>
      <div class="ms-auto btn-list">
        <a href="{{ route('items.show', $item) }}" class="btn btn-secondary">Kembali ke Item</a>
        <a href="{{ route('items.variants.create', $item) }}" class="btn btn-primary">+ Tambah Varian</a>
      </div>
    </div>

    <div class="card-body">
      <div class="row g-3 mb-3">
        <div class="col-md-6">
          <div class="text-secondary">Tipe Varian</div>
          <div class="fw-medium text-capitalize">{{ str_replace('_', ' / ', $item->variant_type ?? 'none') }}</div>
        </div>
        @if($item->name_template)
          <div class="col-md-6">
            <div class="text-secondary">Template Nama</div>
            <div class="fw-medium">{{ $item->name_template }}</div>
          </div>
        @endif
      </div>

      <div class="table-responsive">
        <table class="table card-table table-vcenter text-nowrap align-middle">
          <thead>
            <tr>
              <th>Label</th>
              <th>SKU</th>
              <th>Harga</th>
              <th>Stok</th>
              <th>Status</th>
              <th>Atribut</th>
              <th class="w-1"></th>
            </tr>
          </thead>
          <tbody>
            @forelse($variants as $variant)
              @php
                $attrs = collect($variant->attributes ?? [])
                  ->map(fn($val, $key) => ucfirst($key) . ': ' . $val)
                  ->join(', ');
                $label = $item->renderVariantLabel(is_array($variant->attributes) ? $variant->attributes : []);
              @endphp
              <tr>
                <td class="text-wrap">
                  <div class="fw-medium">{{ $label ?: ($variant->sku ?? '-') }}</div>
                </td>
                <td>{{ $variant->sku ?? '-' }}</td>
                <td>Rp {{ number_format((float) $variant->price, 2, ',', '.') }}</td>
                <td>{{ $variant->stock }}</td>
                <td>
                  @if($variant->is_active)
                    <span class="badge bg-success">Aktif</span>
                  @else
                    <span class="badge bg-secondary">Nonaktif</span>
                  @endif
                </td>
                <td class="text-wrap">{{ $attrs !== '' ? $attrs : '-' }}</td>
                <td class="text-end">
                  @include('layouts.partials.crud_actions', [
                    'view'    => null,
                    'edit'    => route('items.variants.edit', [$item, $variant]),
                    'delete'  => route('items.variants.destroy', [$item, $variant]),
                    'confirm' => 'Hapus varian ini?'
                  ])
                </td>
              </tr>
            @empty
              <tr>
                <td colspan="7" class="text-center text-muted">Belum ada varian.</td>
              </tr>
            @endforelse
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>
@endsection
