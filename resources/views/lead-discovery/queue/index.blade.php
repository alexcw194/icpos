@extends('layouts.tabler')

@section('content')
<div class="container-xl">
  <div class="page-header mb-3">
    <div class="row align-items-center">
      <div class="col">
        <div class="page-pretitle">CRM</div>
        <h2 class="page-title">Lead Queue</h2>
      </div>
    </div>
  </div>

  <div class="row g-3 mb-3">
    <div class="col-sm-6 col-lg-3">
      <div class="card">
        <div class="card-body py-3">
          <div class="text-muted small">Analyze Processing</div>
          <div class="h2 mb-0">{{ number_format($summary['analysis_processing']) }}</div>
        </div>
      </div>
    </div>
    <div class="col-sm-6 col-lg-3">
      <div class="card">
        <div class="card-body py-3">
          <div class="text-muted small">Analyze Completed</div>
          <div class="h2 mb-0">{{ number_format($summary['analysis_completed']) }}</div>
        </div>
      </div>
    </div>
    <div class="col-sm-6 col-lg-3">
      <div class="card">
        <div class="card-body py-3">
          <div class="text-muted small">Scan Processing</div>
          <div class="h2 mb-0">{{ number_format($summary['scan_processing']) }}</div>
        </div>
      </div>
    </div>
    <div class="col-sm-6 col-lg-3">
      <div class="card">
        <div class="card-body py-3">
          <div class="text-muted small">Scan Completed</div>
          <div class="h2 mb-0">{{ number_format($summary['scan_completed']) }}</div>
        </div>
      </div>
    </div>
  </div>

  <div class="card mb-3">
    <div class="card-body">
      <div class="row g-2 align-items-end">
        <form method="get" class="col-12 col-xl d-flex flex-wrap gap-2 align-items-end">
          <div style="min-width: 220px;">
          <label class="form-label">Scope</label>
          <select class="form-select" name="scope">
            <option value="processing" @selected($scope === 'processing')>Processing</option>
            <option value="completed" @selected($scope === 'completed')>Completed</option>
            <option value="all" @selected($scope === 'all')>All</option>
          </select>
          </div>
          <div>
            <button class="btn btn-primary" type="submit">Filter</button>
          </div>
          <div>
            <a href="{{ route('lead-discovery.queue.index', ['scope' => $scope]) }}" class="btn btn-outline-secondary">Refresh</a>
          </div>
        </form>
        <form method="post" class="col-auto ms-xl-auto" action="{{ route('lead-discovery.queue.cleanup-stuck') }}" onsubmit="return confirm('Cleanup queue analyze yang nyangkut?');">
          @csrf
          <button type="submit" class="btn btn-outline-danger">Cleanup Stuck Analyze</button>
        </form>
      </div>
    </div>
  </div>

  <div class="card mb-3">
    <div class="card-header">
      <h3 class="card-title">Analyze Queue</h3>
    </div>
    <div class="table-responsive">
      <table class="table table-vcenter card-table">
        <thead>
          <tr>
            <th>ID</th>
            <th>Prospect</th>
            <th>Status</th>
            <th>AI Status</th>
            <th>Requested By</th>
            <th>Started</th>
            <th>Finished</th>
            <th>Score</th>
          </tr>
        </thead>
        <tbody>
          @forelse($analyses as $analysis)
            @php
              $badgeClass = match($analysis->status) {
                'queued' => 'bg-secondary-lt',
                'running' => 'bg-yellow-lt',
                'success' => 'bg-green-lt',
                'failed' => 'bg-red-lt',
                default => 'bg-muted-lt',
              };
            @endphp
            <tr>
              <td>#{{ $analysis->id }}</td>
              <td>
                @if($analysis->prospect)
                  <a class="text-decoration-none fw-semibold" href="{{ route('lead-discovery.prospects.show', $analysis->prospect) }}">
                    {{ $analysis->prospect->name }}
                  </a>
                  <div class="text-muted small">{{ $analysis->prospect->place_id }}</div>
                @else
                  <span class="text-muted">-</span>
                @endif
              </td>
              <td><span class="badge {{ $badgeClass }}">{{ ucfirst($analysis->status) }}</span></td>
              <td>{{ $analysis->ai_status ?: '-' }}</td>
              <td>{{ $analysis->requestedBy?->name ?: '-' }}</td>
              <td>{{ $analysis->started_at?->format('d M Y H:i:s') ?: '-' }}</td>
              <td>{{ $analysis->finished_at?->format('d M Y H:i:s') ?: '-' }}</td>
              <td>{{ is_null($analysis->score) ? '-' : $analysis->score }}</td>
            </tr>
          @empty
            <tr><td colspan="8" class="text-center text-muted">No analyze queue data.</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>
    @if($analyses->hasPages())
      <div class="card-footer">{{ $analyses->links() }}</div>
    @endif
  </div>

  <div class="card">
    <div class="card-header">
      <h3 class="card-title">Lead Discovery Scan Queue</h3>
    </div>
    <div class="table-responsive">
      <table class="table table-vcenter card-table">
        <thead>
          <tr>
            <th>ID</th>
            <th>Mode</th>
            <th>Status</th>
            <th>Started</th>
            <th>Finished</th>
            <th>By</th>
            <th>Totals</th>
          </tr>
        </thead>
        <tbody>
          @forelse($scanRuns as $run)
            @php
              $scanBadgeClass = match($run->status) {
                'running' => 'bg-yellow-lt',
                'success' => 'bg-green-lt',
                'failed' => 'bg-red-lt',
                default => 'bg-muted-lt',
              };
            @endphp
            <tr>
              <td>#{{ $run->id }}</td>
              <td>{{ ucfirst($run->mode) }}</td>
              <td><span class="badge {{ $scanBadgeClass }}">{{ ucfirst($run->status) }}</span></td>
              <td>{{ $run->started_at?->format('d M Y H:i:s') ?: '-' }}</td>
              <td>{{ $run->finished_at?->format('d M Y H:i:s') ?: '-' }}</td>
              <td>{{ $run->creator?->name ?: '-' }}</td>
              <td>
                @if(!empty($run->totals_json))
                  <pre class="mb-0 small">{{ json_encode($run->totals_json, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) }}</pre>
                @else
                  -
                @endif
              </td>
            </tr>
          @empty
            <tr><td colspan="7" class="text-center text-muted">No scan queue data.</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>
    @if($scanRuns->hasPages())
      <div class="card-footer">{{ $scanRuns->links() }}</div>
    @endif
  </div>
</div>

@if($scope === 'processing')
  @push('scripts')
    <script>
      setTimeout(function () {
        window.location.reload();
      }, 15000);
    </script>
  @endpush
@endif
@endsection
