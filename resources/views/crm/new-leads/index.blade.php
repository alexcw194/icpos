@extends('layouts.tabler')

@section('content')
<div class="container-xl">
  <div class="page-header mb-3">
    <div class="row align-items-center">
      <div class="col">
        <div class="page-pretitle">CRM</div>
        <h2 class="page-title">New Leads</h2>
      </div>
    </div>
  </div>

  <div class="card mb-3">
    <div class="card-body">
      <form method="get" class="row g-2 align-items-end">
        <div class="col-md-4">
          <label class="form-label">Search</label>
          <input type="search" name="q" class="form-control" placeholder="Name/place/address" value="{{ request('q') }}">
        </div>

        @if($isAdmin)
          <div class="col-md-2">
            <label class="form-label">Status</label>
            <select name="status" class="form-select">
              <option value="">All</option>
              <option value="assigned" @selected($selectedStatus === 'assigned')>Assigned</option>
              <option value="rejected" @selected($selectedStatus === 'rejected')>Rejected</option>
              <option value="converted" @selected($selectedStatus === 'converted')>Converted</option>
            </select>
          </div>
          <div class="col-md-2">
            <label class="form-label">Owner</label>
            <select name="owner_user_id" class="form-select">
              <option value="">All Owners</option>
              @foreach($ownerOptions as $owner)
                <option value="{{ $owner->id }}" @selected((int) $selectedOwnerId === (int) $owner->id)>{{ $owner->name }}</option>
              @endforeach
            </select>
          </div>
        @endif

        <div class="col-md-2">
          <label class="form-label">Per Page</label>
          <select name="per_page" class="form-select">
            @foreach([25, 50, 75, 100, 150] as $option)
              <option value="{{ $option }}" @selected((int) $perPage === $option)>{{ $option }} / page</option>
            @endforeach
          </select>
        </div>

        <div class="col-md-2 d-flex gap-2">
          <button class="btn btn-primary w-100">Filter</button>
          <a href="{{ route('crm.new-leads.index') }}" class="btn btn-outline-secondary">Reset</a>
        </div>
      </form>
    </div>
  </div>

  <div class="card">
    <div class="table-responsive">
      <table class="table table-vcenter card-table">
        <thead>
          <tr>
            <th>Name</th>
            <th>City/Province</th>
            <th>Phone</th>
            <th>Website</th>
            <th>Last Analysis</th>
            <th>Owner</th>
            <th>Assigned At</th>
            <th>Status</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
          @forelse($rows as $row)
            @php
              $statusBadge = match($row->status) {
                'assigned' => 'bg-azure-lt',
                'rejected' => 'bg-red-lt',
                'converted' => 'bg-green-lt',
                default => 'bg-muted-lt',
              };
              $analysisLabel = $row->latestAnalysis?->ai_industry_label ?: $row->latestAnalysis?->business_type;
            @endphp
            <tr>
              <td>
                <a class="text-decoration-none fw-semibold" href="{{ route('crm.new-leads.show', $row) }}">{{ $row->name }}</a>
                <div class="text-muted small">{{ $row->place_id }}</div>
              </td>
              <td>{{ ($row->city ?: '-') . ' / ' . ($row->province ?: '-') }}</td>
              <td>{{ $row->phone ?: '-' }}</td>
              <td>
                @if($row->website)
                  <a href="{{ $row->website }}" target="_blank" rel="noopener">{{ \Illuminate\Support\Str::limit($row->website, 28) }}</a>
                @else
                  -
                @endif
              </td>
              <td>{{ $analysisLabel ?: '-' }}</td>
              <td>{{ $row->owner?->name ?: '-' }}</td>
              <td>{{ $row->assigned_at?->format('d M Y H:i') ?: '-' }}</td>
              <td><span class="badge {{ $statusBadge }}">{{ ucfirst($row->status) }}</span></td>
              <td>
                <a href="{{ route('crm.new-leads.show', $row) }}" class="btn btn-sm btn-primary">Open</a>
              </td>
            </tr>
          @empty
            <tr><td colspan="9" class="text-center text-muted">No leads found.</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>
    <div class="card-footer">
      {{ $rows->links() }}
    </div>
  </div>
</div>
@endsection
