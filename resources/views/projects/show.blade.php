@extends('layouts.tabler')

@section('content')
@php
  $tab = request('tab', 'overview');
@endphp

<div class="container-xl">
  <div class="page-header d-print-none">
    <div class="row align-items-center">
      <div class="col">
        <div class="page-pretitle">{{ $project->code }}</div>
        <h2 class="page-title">{{ $project->name }}</h2>
      </div>
      @can('update', $project)
        <div class="col-auto ms-auto">
          <a href="{{ route('projects.edit', $project) }}" class="btn btn-warning">Edit Project</a>
        </div>
      @endcan
    </div>
  </div>

  <div class="row g-3">
    <div class="col-12 col-lg-3">
      <div class="card subrail-card position-sticky" style="top: 76px">
        <div class="list-group list-group-flush">
          <a href="{{ route('projects.show', [$project, 'tab' => 'overview']) }}"
             class="list-group-item subrail-link d-flex align-items-center {{ $tab==='overview' ? 'active' : '' }}">
            <i class="ti ti-layout-grid me-2"></i> <span>Overview</span>
          </a>
          <a href="{{ route('projects.show', [$project, 'tab' => 'quotations']) }}"
             class="list-group-item subrail-link d-flex align-items-center {{ $tab==='quotations' ? 'active' : '' }}">
            <i class="ti ti-file-description me-2"></i> <span>Quotations (BQ)</span>
          </a>
        </div>
      </div>
    </div>

    <div class="col-12 col-lg-9">
      @if($tab === 'overview')
        <div class="card">
          <div class="card-header">
            <div class="card-title">Project Overview</div>
          </div>
          <div class="card-body">
            <div class="row g-3">
              <div class="col-md-6">
                <div class="text-muted">Customer</div>
                <div>{{ $project->customer->name ?? '-' }}</div>
              </div>
              <div class="col-md-6">
                <div class="text-muted">Company</div>
                <div>{{ $project->company->alias ?? $project->company->name ?? '-' }}</div>
              </div>
              <div class="col-md-6">
                <div class="text-muted">Sales Owner</div>
                <div>{{ $project->salesOwner->name ?? '-' }}</div>
              </div>
              <div class="col-md-6">
                <div class="text-muted">Status</div>
                <div><span class="badge bg-blue-lt text-blue-9">{{ ucfirst($project->status) }}</span></div>
              </div>
              <div class="col-md-6">
                <div class="text-muted">Start Date</div>
                <div>{{ $project->start_date?->format('d M Y') ?? '-' }}</div>
              </div>
              <div class="col-md-6">
                <div class="text-muted">Target Finish</div>
                <div>{{ $project->target_finish_date?->format('d M Y') ?? '-' }}</div>
              </div>
              <div class="col-md-6">
                <div class="text-muted">Contract Baseline</div>
                <div>Rp {{ number_format((float)$project->contract_value_baseline, 2, ',', '.') }}</div>
              </div>
              <div class="col-md-6">
                <div class="text-muted">Contract Current</div>
                <div>Rp {{ number_format((float)$project->contract_value_current, 2, ',', '.') }}</div>
              </div>
              <div class="col-md-12">
                <div class="text-muted">Systems</div>
                <div>
                  @php
                    $systems = collect($project->systems_json ?? [])->map(fn($s) => ucwords(str_replace('_',' ', $s)))->implode(', ');
                  @endphp
                  {{ $systems ?: '-' }}
                </div>
              </div>
              <div class="col-md-12">
                <div class="text-muted">Notes</div>
                <div class="text-wrap">{{ $project->notes ?: '-' }}</div>
              </div>
            </div>
          </div>
        </div>
      @endif

      @if($tab === 'quotations')
        <div class="card">
          <div class="card-header">
            <div class="card-title">Project Quotations (BQ)</div>
            <div class="ms-auto btn-list">
              @can('create', \App\Models\ProjectQuotation::class)
                <a href="{{ route('projects.quotations.create', $project) }}" class="btn btn-primary">
                  + New BQ
                </a>
              @endcan
            </div>
          </div>
          <div class="table-responsive">
            <table class="table table-sm table-vcenter card-table">
              <thead>
                <tr>
                  <th>Number</th>
                  <th>Version</th>
                  <th>Status</th>
                  <th>Date</th>
                  <th class="text-end">Total</th>
                </tr>
              </thead>
              <tbody>
                @forelse($project->quotations as $bq)
                  <tr>
                    <td>
                      <a href="{{ route('projects.quotations.show', [$project, $bq]) }}" class="text-decoration-none fw-semibold">
                        {{ $bq->number }}
                      </a>
                    </td>
                    <td>v{{ $bq->version }}</td>
                    <td><span class="badge bg-blue-lt text-blue-9">{{ ucfirst($bq->status) }}</span></td>
                    <td>{{ optional($bq->quotation_date)->format('d M Y') ?? '-' }}</td>
                    <td class="text-end">Rp {{ number_format((float)$bq->grand_total, 2, ',', '.') }}</td>
                  </tr>
                @empty
                  <tr>
                    <td colspan="5" class="text-center text-muted">No quotations.</td>
                  </tr>
                @endforelse
              </tbody>
            </table>
          </div>
        </div>
      @endif
    </div>
  </div>
</div>
@endsection

@push('styles')
<style>
  .subrail-card{
    border:1px solid rgba(0,0,0,.06);
    box-shadow:0 2px 8px rgba(16,24,40,.04);
    overflow:hidden;
  }
  .subrail-link{
    border:0;
    padding:.65rem .75rem;
    border-left:3px solid transparent;
    transition:background-color .15s ease,border-color .15s ease;
  }
  .subrail-link:hover{ background-color:rgba(99,102,241,.06); }
  .subrail-link.active{
    background:linear-gradient(90deg, rgba(99,102,241,.10), transparent);
    border-left-color:#6366f1;
    color:#111827;
    font-weight:600;
  }
  @media (max-width: 991.98px){
    .subrail-card{ position:static !important; top:auto !important; }
  }
</style>
@endpush
