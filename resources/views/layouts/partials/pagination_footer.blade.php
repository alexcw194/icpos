@if ($paginator->hasPages())
  <div class="card-footer d-flex align-items-center">
    <p class="m-0 text-muted">
      Menampilkan <span>{{ $paginator->firstItem() ?? 0 }}</span>–<span>{{ $paginator->lastItem() ?? 0 }}</span>
      dari <span>{{ $paginator->total() }}</span> data
    </p>

    <div class="ms-auto">
      {{ $paginator->appends(request()->except('page'))->links() }}
    </div>
  </div>
@endif
