@extends('layouts.tabler')

@section('content')
<div class="container-xl">
  <div class="page-header d-print-none">
    <div class="row align-items-center">
      <div class="col">
        <div class="page-pretitle">{{ $project->code }}</div>
        <h2 class="page-title">Project Quotations (BQ)</h2>
      </div>
      <div class="col-auto ms-auto">
        <div class="btn-list">
          <div class="dropdown">
            <button class="btn btn-outline-primary dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false">
              Export CSV BQ
            </button>
            <div class="dropdown-menu dropdown-menu-end">
              @forelse($quotations as $bq)
                <a
                  class="dropdown-item"
                  href="{{ route('projects.quotations.show', ['project' => $project, 'quotation' => $bq, 'open_export_csv' => 1]) }}">
                  {{ $bq->number }}
                </a>
              @empty
                <span class="dropdown-item text-muted">Belum ada BQ</span>
              @endforelse
            </div>
          </div>
          @can('create', \App\Models\ProjectQuotation::class)
            <a href="{{ route('projects.quotations.create', $project) }}" class="btn btn-primary">
              + New BQ
            </a>
          @endcan
        </div>
      </div>
    </div>
  </div>

  <div class="card">
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
          @forelse($quotations as $bq)
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
    <div class="card-footer">
      {{ $quotations->links() }}
    </div>
  </div>
</div>
@endsection
