@extends('layouts.tabler')

@section('content')
<div class="container-xl">
  @if(session('success')) <div class="alert alert-success">{{ session('success') }}</div> @endif

  <div class="card">
    <div class="card-header">
      <div class="card-title">Variants - {{ $item->name }}</div>
      <div class="ms-auto btn-list">
        <a href="{{ route($item->list_type === 'project' ? 'project-items.edit' : 'items.edit', $item) }}" class="btn btn-secondary">Back to Item</a>

        @hasanyrole('SuperAdmin|Admin')
          <a href="{{ route('items.variants.create', $item) }}" class="btn btn-primary">+ Add Variant</a>
        @endhasanyrole
      </div>
    </div>

    <div class="card-body table-responsive">
      <table class="table table-sm">
        <thead>
          <tr>
            <th>#</th><th>SKU</th><th>Label</th><th>Price</th><th>Stock</th><th>Active</th><th></th>
          </tr>
        </thead>
        <tbody>
          @forelse ($item->variants as $v)
            <tr>
              <td>{{ $v->id }}</td>
              <td>{{ $v->sku ?? '-' }}</td>
              <td>{{ $v->label }}</td>
              <td>Rp {{ $v->priceId }}</td>
              <td>{{ $v->stock }}</td>
              <td>
                @if($v->is_active)
                  <span class="badge bg-success">Yes</span>
                @else
                  <span class="badge bg-secondary">No</span>
                @endif
              </td>
              <td class="text-end">
                @hasanyrole('SuperAdmin|Admin')
                  <a href="{{ route('variants.edit', $v) }}" class="btn btn-sm btn-warning">Edit</a>
                  <form action="{{ route('variants.destroy', $v) }}" method="POST" class="d-inline" onsubmit="return confirm('Delete this variant?')">
                    @csrf @method('DELETE')
                    <button class="btn btn-sm btn-danger">Delete</button>
                  </form>
                @else
                  <span class="text-muted small">Read-only</span>
                @endhasanyrole
              </td>
            </tr>
          @empty
            <tr><td colspan="7" class="text-center text-muted">Belum ada varian.</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </div>
</div>
@endsection
