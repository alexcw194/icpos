@if ($paginator->hasPages())
  <div class="d-flex align-items-center justify-content-between gap-3 flex-wrap">
    {{-- Left info --}}
    <div class="d-flex align-items-center gap-3 flex-wrap">
      <div class="text-secondary">
        Showing {{ $paginator->firstItem() }} to {{ $paginator->lastItem() }} of {{ $paginator->total() }} entries
      </div>
      @if(request()->routeIs('projects.labor.index'))
        @php
          $laborPerPageOptions = [25, 50, 75, 100, 150];
          $laborPerPage = (int) request('per_page', 25);
          if (!in_array($laborPerPage, $laborPerPageOptions, true)) {
              $laborPerPage = 25;
          }
        @endphp
        <form method="get" class="d-flex align-items-center gap-2">
          <input type="hidden" name="type" value="{{ request('type', 'item') }}">
          <input type="hidden" name="q" value="{{ request('q') }}">
          @if(request()->filled('sub_contractor_id'))
            <input type="hidden" name="sub_contractor_id" value="{{ request('sub_contractor_id') }}">
          @endif
          <span class="text-secondary">Per page</span>
          <select name="per_page" class="form-select form-select-sm" style="width: 90px" onchange="this.form.submit()">
            @foreach($laborPerPageOptions as $option)
              <option value="{{ $option }}" @selected($laborPerPage === $option)>{{ $option }}</option>
            @endforeach
          </select>
        </form>
      @endif
    </div>

    {{-- Middle: condensed page numbers --}}
    <nav aria-label="Pagination">
      <ul class="pagination mb-0">
        {{-- Previous --}}
        @if ($paginator->onFirstPage())
          <li class="page-item disabled"><span class="page-link">Previous</span></li>
        @else
          <li class="page-item"><a class="page-link" href="{{ $paginator->previousPageUrl() }}" rel="prev">Previous</a></li>
        @endif

        @php
          $current = $paginator->currentPage();
          $last    = $paginator->lastPage();
          $window  = 2; // range kiri/kanan current. 1 => current-1, current, current+1
          $start   = max(2, $current - $window);
          $end     = min($last - 1, $current + $window);
        @endphp

        {{-- First page --}}
        <li class="page-item {{ $current === 1 ? 'active' : '' }}">
          <a class="page-link" href="{{ $paginator->url(1) }}">1</a>
        </li>

        {{-- Left ellipsis --}}
        @if ($start > 2)
          <li class="page-item disabled"><span class="page-link">…</span></li>
        @endif

        {{-- Middle window --}}
        @for ($i = $start; $i <= $end; $i++)
          <li class="page-item {{ $i === $current ? 'active' : '' }}">
            <a class="page-link" href="{{ $paginator->url($i) }}">{{ $i }}</a>
          </li>
        @endfor

        {{-- Right ellipsis --}}
        @if ($end < $last - 1)
          <li class="page-item disabled"><span class="page-link">…</span></li>
        @endif

        {{-- Last page (kalau > 1) --}}
        @if ($last > 1)
          <li class="page-item {{ $current === $last ? 'active' : '' }}">
            <a class="page-link" href="{{ $paginator->url($last) }}">{{ $last }}</a>
          </li>
        @endif

        {{-- Next --}}
        @if ($paginator->hasMorePages())
          <li class="page-item"><a class="page-link" href="{{ $paginator->nextPageUrl() }}" rel="next">Next</a></li>
        @else
          <li class="page-item disabled"><span class="page-link">Next</span></li>
        @endif
      </ul>
    </nav>

    {{-- Right: Go to page dropdown --}}
    <div class="d-flex align-items-center gap-2">
      <span class="text-secondary">Page</span>

      <select class="form-select form-select-sm" style="width: 90px"
              onchange="if(this.value) window.location.href=this.value">
        @for ($i = 1; $i <= $paginator->lastPage(); $i++)
          <option value="{{ $paginator->url($i) }}" {{ $i === $paginator->currentPage() ? 'selected' : '' }}>
            {{ $i }}
          </option>
        @endfor
      </select>

      <span class="text-secondary">of {{ $paginator->lastPage() }}</span>
    </div>
  </div>
@endif
