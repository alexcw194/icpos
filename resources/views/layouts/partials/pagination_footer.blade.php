@if ($paginator->hasPages())
  <div class="card-footer">
    {{ $paginator->links() }}
  </div>
@endif
