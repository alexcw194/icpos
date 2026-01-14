@extends('layouts.tabler')

@section('content')
@php
  $statusOptions = [
    '' => 'All Status',
    'draft' => 'Draft',
    'active' => 'Active',
    'closed' => 'Closed',
    'cancelled' => 'Cancelled',
  ];
@endphp

<div class="container-xl">
  <div class="page-header d-print-none">
    <div class="row align-items-center">
      <div class="col">
        <h2 class="page-title">Projects</h2>
        <div class="text-muted">Project master & customer linkage</div>
      </div>
      @can('create', \App\Models\Project::class)
        <div class="col-auto ms-auto">
          <a href="{{ route('projects.create') }}" class="btn btn-primary">
            <span class="ti ti-plus"></span> New Project
          </a>
        </div>
      @endcan
    </div>
  </div>

  <div class="card">
    <div class="card-body border-bottom">
      <form class="row g-2" method="get">
        <div class="col-12 col-md-6">
          <div class="input-group">
            <input type="search" name="q" class="form-control" placeholder="Search project code or name" value="{{ $q }}">
            <button class="btn btn-icon" type="submit" aria-label="Search">
              <svg xmlns="http://www.w3.org/2000/svg" class="icon" width="20" height="20" viewBox="0 0 24 24"
                   stroke-width="2" stroke="currentColor" fill="none" stroke-linecap="round" stroke-linejoin="round">
                <path stroke="none" d="M0 0h24v24H0z" fill="none"></path>
                <circle cx="10" cy="10" r="7"></circle>
                <line x1="21" y1="21" x2="15" y2="15"></line>
              </svg>
            </button>
          </div>
        </div>
        <div class="col-12 col-md-3">
          <select name="status" class="form-select">
            @foreach($statusOptions as $val => $label)
              <option value="{{ $val }}" @selected($status === $val)>{{ $label }}</option>
            @endforeach
          </select>
        </div>
        <div class="col-12 col-md-3">
          <button class="btn btn-outline-secondary w-100" type="submit">Filter</button>
        </div>
      </form>
    </div>

    <div class="table-responsive">
      <table class="table table-sm table-vcenter card-table">
        <thead>
          <tr>
            <th>Code</th>
            <th>Customer</th>
            <th>Project</th>
            <th>Systems</th>
            <th>Status</th>
            <th>Sales Owner</th>
            <th class="text-end">Updated</th>
          </tr>
        </thead>
        <tbody>
          @forelse($projects as $project)
            @php
              $systems = \App\Support\ProjectSystems::labelsFor($project->systems_json ?? []);
              $systems = implode(', ', $systems);
              $statusLabel = ucfirst($project->status);
            @endphp
            <tr>
              <td>
                <a href="{{ route('projects.show', $project) }}" class="text-decoration-none fw-semibold">
                  {{ $project->code }}
                </a>
              </td>
              <td>{{ $project->customer->name ?? '-' }}</td>
              <td class="text-wrap">{{ $project->name }}</td>
              <td class="text-muted">{{ $systems ?: '-' }}</td>
              <td>
                <span class="badge bg-blue-lt text-blue-9">{{ $statusLabel }}</span>
              </td>
              <td>{{ $project->salesOwner->name ?? '-' }}</td>
              <td class="text-end text-muted">{{ $project->updated_at?->format('d M Y') }}</td>
            </tr>
          @empty
            <tr>
              <td colspan="7" class="text-center text-muted">No projects.</td>
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
