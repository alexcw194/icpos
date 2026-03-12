@if ($paginator->hasPages())
  @php
    $pageName = $paginator->getPageName();
    $current = $paginator->currentPage();
    $last = $paginator->lastPage();
    $window = 2;
    $start = max(2, $current - $window);
    $end = min($last - 1, $current + $window);

    $baseQuery = request()->query();
    $basePath = $paginator->path();
    $makePageUrl = function (int $page) use ($baseQuery, $basePath, $pageName) {
        $query = $baseQuery;
        $query[$pageName] = $page;
        $qs = http_build_query($query);
        return $basePath . ($qs !== '' ? ('?' . $qs) : '');
    };

    $perPageOptions = [20, 40, 80, 160];
    $currentPerPage = (int) request('per_page', 20);
    if (!in_array($currentPerPage, $perPageOptions, true)) {
        $currentPerPage = 20;
    }

    $basePerPageQuery = collect($baseQuery)
        ->reject(function ($value, $key) use ($pageName) {
            return $key === 'per_page'
                || $key === 'page'
                || $key === $pageName
                || str_ends_with((string) $key, '_page');
        })
        ->all();

    $makePerPageUrl = function (int $perPage) use ($basePath, $basePerPageQuery) {
        $query = $basePerPageQuery;
        $query['per_page'] = $perPage;
        $qs = http_build_query($query);
        return $basePath . ($qs !== '' ? ('?' . $qs) : '');
    };
  @endphp

  <div class="d-flex align-items-center justify-content-between gap-3 flex-wrap">
    <div class="d-flex align-items-center gap-3 flex-wrap">
      <div class="text-secondary">
        Showing {{ $paginator->firstItem() }} to {{ $paginator->lastItem() }} of {{ $paginator->total() }} entries
      </div>
    </div>

    <nav aria-label="Pagination">
      <ul class="pagination mb-0">
        @if ($paginator->onFirstPage())
          <li class="page-item disabled"><span class="page-link">Previous</span></li>
        @else
          <li class="page-item"><a class="page-link" href="{{ $makePageUrl($current - 1) }}" rel="prev">Previous</a></li>
        @endif

        <li class="page-item {{ $current === 1 ? 'active' : '' }}">
          <a class="page-link" href="{{ $makePageUrl(1) }}">1</a>
        </li>

        @if ($start > 2)
          <li class="page-item disabled"><span class="page-link">...</span></li>
        @endif

        @for ($i = $start; $i <= $end; $i++)
          <li class="page-item {{ $i === $current ? 'active' : '' }}">
            <a class="page-link" href="{{ $makePageUrl($i) }}">{{ $i }}</a>
          </li>
        @endfor

        @if ($end < $last - 1)
          <li class="page-item disabled"><span class="page-link">...</span></li>
        @endif

        @if ($last > 1)
          <li class="page-item {{ $current === $last ? 'active' : '' }}">
            <a class="page-link" href="{{ $makePageUrl($last) }}">{{ $last }}</a>
          </li>
        @endif

        @if ($paginator->hasMorePages())
          <li class="page-item"><a class="page-link" href="{{ $makePageUrl($current + 1) }}" rel="next">Next</a></li>
        @else
          <li class="page-item disabled"><span class="page-link">Next</span></li>
        @endif
      </ul>
    </nav>

    <div class="d-flex align-items-center gap-2 flex-wrap">
      <span class="text-secondary">Page</span>
      <select class="form-select form-select-sm" style="width: 90px" onchange="if(this.value) window.location.href=this.value">
        @for ($i = 1; $i <= $last; $i++)
          <option value="{{ $makePageUrl($i) }}" @selected($i === $current)>{{ $i }}</option>
        @endfor
      </select>
      <span class="text-secondary">of {{ $last }}</span>

      <span class="text-secondary ms-2">Show</span>
      <select class="form-select form-select-sm" style="width: 90px" onchange="if(this.value) window.location.href=this.value">
        @foreach($perPageOptions as $option)
          <option value="{{ $makePerPageUrl($option) }}" @selected($currentPerPage === $option)>{{ $option }}</option>
        @endforeach
      </select>
    </div>
  </div>
@endif
