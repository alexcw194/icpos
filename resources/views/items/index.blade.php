@extends('layouts.tabler')

@section('content')
<div class="container-xl">
  @if(session('success')) <div class="alert alert-success">{{ session('success') }}</div> @endif

  <div class="card">
    <div class="card-header">
      <h3 class="card-title">Inventory</h3>
      <a href="{{ route('items.create') }}" class="btn btn-primary ms-auto">+ Add Item</a>
    </div>

    <div class="card-body">
      <form method="get" class="row g-2 mb-3">
        <div class="col-12 col-md">
          <input type="text" name="q" value="{{ $q ?? '' }}" class="form-control" placeholder="Cari nama / SKU">
        </div>
        <div class="col-6 col-md-auto">
          <select name="unit_id" class="form-select">
            <option value="">— Semua Unit —</option>
            @foreach($units as $u)
              <option value="{{ $u->id }}" @selected(($unitId ?? '') == $u->id)>
                {{ $u->code ? $u->code.' — ' : '' }}{{ $u->name }}
              </option>
            @endforeach
          </select>
        </div>
        <div class="col-6 col-md-auto">
          <select name="brand_id" class="form-select">
            <option value="">— Semua Brand —</option>
            @foreach($brands as $b)
              <option value="{{ $b->id }}" @selected(($brandId ?? '') == $b->id)>{{ $b->name }}</option>
            @endforeach
          </select>
        </div>
        <div class="col-12 col-md-auto">
          <button class="btn btn-primary">Search</button>
          @if(($q ?? '') !== '' || ($unitId ?? '') !== '' || ($brandId ?? '') !== '')
            <a href="{{ route('items.index') }}" class="btn btn-light">Reset</a>
          @endif
        </div>
      </form>

      <div class="table-responsive">
        <table class="table card-table table-vcenter text-nowrap">
          <thead>
            <tr>
              <th>Name</th>
              <th>SKU</th>
              <th class="d-none d-md-table-cell">Unit</th>
              <th class="d-none d-md-table-cell">Brand</th>
              <th>Price</th>
              <th>Stock</th>
              <th></th>
            </tr>
          </thead>
          <tbody>
            @forelse($items as $item)
              <tr>
                <td class="text-wrap">
                  <div class="fw-medium">{{ $item->name }}</div>

                  @if($item->size || $item->color)
                    <div class="mt-1">
                      @if($item->size)
                        <span class="badge">{{ $item->size->name }}</span>
                      @endif

                      @if($item->color)
                        <span class="badge d-inline-flex align-items-center">
                          @if($item->color->hex)
                            <i class="me-1" style="display:inline-block;width:10px;height:10px;border-radius:50%;background:{{ $item->color->hex }}"></i>
                          @endif
                          {{ $item->color->name }}
                        </span>
                      @endif
                    </div>
                  @endif
                </td>

                <td>{{ $item->sku ?? '-' }}</td>

                <td class="d-none d-md-table-cell">
                  {{ $item->unit?->code ?: $item->unit?->name ?: '-' }}
                </td>

                <td class="d-none d-md-table-cell">
                  {{ $item->brand?->name ?? '-' }}
                </td>

                <td>{{ $item->price_id }}</td>
                <td>{{ $item->stock }}</td>

                <td class="text-end">
                  @include('layouts.partials.crud_actions', [
                    'view'    => route('items.show', $item),
                    'edit'    => route('items.edit', $item),
                    'delete'  => route('items.destroy', $item),
                    'size'    => 'sm',
                    'confirm' => 'Delete this item?'
                  ])
                </td>
              </tr>
            @empty
              <tr>
                <td colspan="7" class="text-center text-muted">Belum ada data.</td>
              </tr>
            @endforelse
          </tbody>
        </table>
      </div>

      <div class="mt-3">
        {{ $items->links() }}
      </div>
    </div>
  </div>
</div>
@endsection
