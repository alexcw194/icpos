@extends('layouts.tabler')

@section('content')
<div class="container-xl">
  <form method="POST" action="{{ route('deliveries.update', $delivery) }}">
    @csrf
    @method('PUT')
    <div class="card">
      <div class="card-header d-flex justify-content-between align-items-center">
        <h2 class="card-title mb-0">Edit Delivery Draft</h2>
        <a href="{{ route('deliveries.show', $delivery) }}" class="btn btn-outline-secondary">Cancel</a>
      </div>
      <div class="card-body">
        @include('deliveries._form')
      </div>
      <div class="card-footer d-flex justify-content-between align-items-center">
        <div>
          @can('deliveries.delete')
            <button type="submit" form="delete-delivery" class="btn btn-outline-danger" onclick="return confirm('Hapus draft delivery ini?');">Delete Draft</button>
          @endcan
        </div>
        <div class="d-flex gap-2">
          <button type="submit" class="btn btn-primary">Save Changes</button>
          @can('deliveries.post')
            <button type="button"
                    class="btn btn-success"
                    onclick="return confirm('Post delivery dan kurangi stok?') && document.getElementById('post-delivery').submit();">
              Post Delivery
            </button>
          @endcan
        </div>
      </div>
    </div>
  </form>

  @can('deliveries.delete')
    <form id="delete-delivery" method="POST" action="{{ route('deliveries.destroy', $delivery) }}" class="d-none">
      @csrf
      @method('DELETE')
    </form>
  @endcan

  @can('deliveries.post')
    {{-- KEEP: hidden form remains untouched (not used by the button above) --}}
    <form id="post-delivery" method="POST" action="{{ route('deliveries.post', $delivery) }}" class="d-none">
      @csrf
    </form>
  @endcan
</div>
@endsection
