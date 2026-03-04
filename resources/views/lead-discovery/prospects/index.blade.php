@extends('layouts.tabler')

@section('content')
<div class="container-xl">
  <div class="page-header mb-3">
    <div class="row align-items-center">
      <div class="col">
        <div class="page-pretitle">CRM</div>
        <h2 class="page-title">Lead Discovery - Prospects</h2>
      </div>
    </div>
  </div>

  <div class="card mb-3">
    <div class="card-body">
      <form method="get" class="row g-2">
        <div class="col-md-3">
          <input type="search" name="q" class="form-control" placeholder="Search name/place/address" value="{{ request('q') }}">
        </div>
        <div class="col-md-2">
          <select name="keyword_id" class="form-select">
            <option value="">All Keywords</option>
            @foreach($keywords as $keyword)
              <option value="{{ $keyword->id }}" @selected((string) request('keyword_id') === (string) $keyword->id)>
                {{ $keyword->keyword }}
              </option>
            @endforeach
          </select>
        </div>
        <div class="col-md-2">
          <select name="status" class="form-select">
            <option value="">All Status</option>
            @foreach($statuses as $rowStatus)
              <option value="{{ $rowStatus }}" @selected((string) request('status') === (string) $rowStatus)>{{ ucfirst($rowStatus) }}</option>
            @endforeach
          </select>
        </div>
        <div class="col-md-2">
          <select name="owner_user_id" class="form-select">
            <option value="">All Owners</option>
            @foreach($owners as $owner)
              <option value="{{ $owner->id }}" @selected((string) request('owner_user_id') === (string) $owner->id)>{{ $owner->name }}</option>
            @endforeach
          </select>
        </div>
        <div class="col-md-1">
          <input type="text" name="province" class="form-control" placeholder="Province" value="{{ request('province') }}">
        </div>
        <div class="col-md-1">
          <input type="text" name="city" class="form-control" placeholder="City" value="{{ request('city') }}">
        </div>
        <div class="col-md-1">
          <select name="has_phone" class="form-select">
            <option value="">Phone</option>
            <option value="1" @selected(request('has_phone') === '1')>Yes</option>
            <option value="0" @selected(request('has_phone') === '0')>No</option>
          </select>
        </div>
        <div class="col-md-1">
          <select name="has_website" class="form-select">
            <option value="">Website</option>
            <option value="1" @selected(request('has_website') === '1')>Yes</option>
            <option value="0" @selected(request('has_website') === '0')>No</option>
          </select>
        </div>
        <div class="col-md-2">
          <input type="date" name="discovered_from" class="form-control" value="{{ request('discovered_from') }}">
        </div>
        <div class="col-md-2">
          <input type="date" name="discovered_to" class="form-control" value="{{ request('discovered_to') }}">
        </div>
        <div class="col-md-2">
          <select name="per_page" class="form-select">
            @foreach($perPageOptions as $option)
              <option value="{{ $option }}" @selected((int) request('per_page', $perPage) === $option)>{{ $option }} / page</option>
            @endforeach
          </select>
        </div>
        <div class="col-md-2 d-flex gap-2">
          <button class="btn btn-primary w-100">Filter</button>
          <a href="{{ route('lead-discovery.prospects.index') }}" class="btn btn-outline-secondary">Reset</a>
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
            <th>Primary Category</th>
            <th>City</th>
            <th>Phone</th>
            <th>Website</th>
            <th>Discovered</th>
            <th>Status</th>
            <th>Owner</th>
            <th style="width: 260px;">Actions</th>
          </tr>
        </thead>
        <tbody>
          @forelse($rows as $row)
            @php
              $badge = match($row->status) {
                'assigned' => 'bg-azure',
                'converted' => 'bg-green',
                'ignored' => 'bg-secondary',
                default => 'bg-blue-lt',
              };
            @endphp
            <tr>
              <td>
                <a href="{{ route('lead-discovery.prospects.show', $row) }}" class="fw-semibold text-decoration-none">
                  {{ $row->name }}
                </a>
                <div class="text-muted small">{{ $row->place_id }}</div>
              </td>
              <td>{{ $row->primary_type ?: ($row->keyword?->category_label ?: '-') }}</td>
              <td>{{ $row->city ?: '-' }}</td>
              <td>{{ $row->phone ?: '-' }}</td>
              <td>
                @if($row->website)
                  <a href="{{ $row->website }}" target="_blank" rel="noopener">{{ \Illuminate\Support\Str::limit($row->website, 32) }}</a>
                @else
                  -
                @endif
              </td>
              <td>{{ $row->discovered_at?->format('d M Y H:i') ?: '-' }}</td>
              <td><span class="badge {{ $badge }}">{{ ucfirst($row->status) }}</span></td>
              <td>{{ $row->owner?->name ?: '-' }}</td>
              <td>
                <div class="d-flex flex-column gap-1">
                  <form method="post" action="{{ route('lead-discovery.prospects.assign', $row) }}" class="d-flex gap-1">
                    @csrf
                    <select name="owner_user_id" class="form-select form-select-sm">
                      <option value="">Unassigned</option>
                      @foreach($owners as $owner)
                        <option value="{{ $owner->id }}" @selected((int) $row->owner_user_id === (int) $owner->id)>{{ $owner->name }}</option>
                      @endforeach
                    </select>
                    <button class="btn btn-sm btn-outline-primary">Assign</button>
                  </form>
                  <div class="d-flex gap-1">
                    <form method="post" action="{{ route('lead-discovery.prospects.status', $row) }}" class="d-flex gap-1 flex-grow-1">
                      @csrf
                      <select name="status" class="form-select form-select-sm">
                        <option value="new" @selected($row->status === 'new')>New</option>
                        <option value="assigned" @selected($row->status === 'assigned')>Assigned</option>
                        <option value="ignored" @selected($row->status === 'ignored')>Ignored</option>
                      </select>
                      <button class="btn btn-sm btn-outline-secondary">Set</button>
                    </form>
                    <a href="{{ route('lead-discovery.prospects.show', $row) }}" class="btn btn-sm btn-primary">Convert</a>
                  </div>
                </div>
              </td>
            </tr>
          @empty
            <tr>
              <td colspan="9" class="text-center text-muted">No prospects found.</td>
            </tr>
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
