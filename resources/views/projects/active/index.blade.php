@extends('layouts.tabler')

@section('content')
<div class="container-xl">
  <div class="page-header d-print-none">
    <div class="row align-items-center">
      <div class="col">
        <h2 class="page-title">Project Active</h2>
        <div class="text-muted">Monitoring latest BQ won dan progress invoicing per payment term</div>
      </div>
    </div>
  </div>

  <div class="card">
    <div class="card-body border-bottom">
      <form method="get" class="row g-2">
        <div class="col-12 col-md-8">
          <div class="input-group">
            <input type="search" name="q" class="form-control" placeholder="Search project code or name" value="{{ $q }}">
            <button type="submit" class="btn btn-icon" aria-label="Search">
              <span class="ti ti-search"></span>
            </button>
          </div>
        </div>
        <div class="col-12 col-md-4">
          <button type="submit" class="btn btn-outline-secondary w-100">Filter</button>
        </div>
      </form>
    </div>

    <div class="table-responsive">
      <table class="table table-sm table-vcenter card-table">
        <thead>
          <tr>
            <th>Project</th>
            <th>Customer</th>
            <th>Company</th>
            <th>Sales Owner</th>
            <th>Latest Won BQ</th>
            <th class="text-end">Grand Total BQ</th>
            <th class="text-end">Action</th>
          </tr>
        </thead>
        <tbody>
          @forelse($projects as $project)
            @php $won = $project->latestWonQuotation; @endphp
            <tr>
              <td>
                <div class="fw-semibold">{{ $project->code }}</div>
                <div class="text-muted">{{ $project->name }}</div>
              </td>
              <td>{{ $project->customer->name ?? '-' }}</td>
              <td>{{ $project->company->alias ?? $project->company->name ?? '-' }}</td>
              <td>{{ $project->salesOwner->name ?? '-' }}</td>
              <td>
                @if($won)
                  <div class="fw-semibold">{{ $won->number }}</div>
                  <div class="text-muted">{{ optional($won->won_at)->format('d M Y H:i') ?: optional($won->quotation_date)->format('d M Y') }}</div>
                @else
                  <span class="text-muted">-</span>
                @endif
              </td>
              <td class="text-end">
                {{ $won ? 'Rp '.number_format((float) $won->grand_total, 2, ',', '.') : '-' }}
              </td>
              <td class="text-end">
                <a href="{{ route('projects.active.show', $project) }}" class="btn btn-sm btn-primary">Open</a>
              </td>
            </tr>
          @empty
            <tr>
              <td colspan="7" class="text-center text-muted">No active project with won BQ.</td>
            </tr>
          @endforelse
        </tbody>
      </table>
    </div>

    <div class="card-footer">
      {{ $projects->links() }}
    </div>
  </div>
</div>
@endsection
