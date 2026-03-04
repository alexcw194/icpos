@extends('layouts.tabler')

@section('content')
<div class="container-xl">
  <div class="page-header mb-3">
    <div class="row align-items-center">
      <div class="col">
        <div class="page-pretitle">CRM / Lead Discovery</div>
        <h2 class="page-title">{{ $prospect->name }}</h2>
        <div class="text-muted small">{{ $prospect->place_id }}</div>
      </div>
      <div class="col-auto">
        @php
          $badge = match($prospect->status) {
            'assigned' => 'bg-azure',
            'converted' => 'bg-green',
            'ignored' => 'bg-secondary',
            default => 'bg-blue-lt',
          };
        @endphp
        <span class="badge {{ $badge }}">{{ ucfirst($prospect->status) }}</span>
      </div>
    </div>
  </div>

  <div class="row g-3">
    <div class="col-lg-8">
      <div class="card mb-3">
        <div class="card-header"><h3 class="card-title">Address</h3></div>
        <div class="card-body">
          <div>{{ $prospect->formatted_address ?: $prospect->short_address ?: '-' }}</div>
          @if($prospect->google_maps_url)
            <div class="mt-2">
              <a href="{{ $prospect->google_maps_url }}" target="_blank" rel="noopener">Open in Google Maps</a>
            </div>
          @endif
        </div>
      </div>

      <div class="card mb-3">
        <div class="card-header"><h3 class="card-title">Contact</h3></div>
        <div class="card-body">
          <div><strong>Phone:</strong> {{ $prospect->phone ?: '-' }}</div>
          <div><strong>Website:</strong>
            @if($prospect->website)
              <a href="{{ $prospect->website }}" target="_blank" rel="noopener">{{ $prospect->website }}</a>
            @else
              -
            @endif
          </div>
          <div><strong>City/Province:</strong> {{ $prospect->city ?: '-' }} / {{ $prospect->province ?: '-' }}</div>
        </div>
      </div>

      <div class="card mb-3">
        <div class="card-header"><h3 class="card-title">Discovery Context</h3></div>
        <div class="card-body">
          <div><strong>Keyword:</strong> {{ $prospect->keyword?->keyword ?: '-' }}</div>
          <div><strong>Grid Cell:</strong> {{ $prospect->gridCell?->name ?: '-' }}</div>
          <div><strong>Discovered At:</strong> {{ $prospect->discovered_at?->format('d M Y H:i:s') ?: '-' }}</div>
          <div><strong>Last Seen:</strong> {{ $prospect->last_seen_at?->format('d M Y H:i:s') ?: '-' }}</div>
          @if($prospect->converted_customer_id)
            <div><strong>Converted Customer:</strong>
              <a href="{{ route('customers.show', $prospect->converted_customer_id) }}">{{ $prospect->convertedCustomer?->name ?: 'Customer #' . $prospect->converted_customer_id }}</a>
            </div>
          @endif
        </div>
      </div>

      <div class="card">
        <div class="card-header">
          <h3 class="card-title">Raw JSON</h3>
          <div class="card-actions">
            <a href="#prospect-raw-json" data-bs-toggle="collapse" aria-expanded="false">Toggle</a>
          </div>
        </div>
        <div id="prospect-raw-json" class="collapse">
          <div class="card-body">
            <pre class="mb-0" style="white-space: pre-wrap;">{{ json_encode($prospect->raw_json, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) }}</pre>
          </div>
        </div>
      </div>
    </div>

    <div class="col-lg-4">
      <div class="card mb-3">
        <div class="card-header"><h3 class="card-title">Analysis</h3></div>
        <div class="card-body">
          <form method="post" action="{{ route('lead-discovery.prospects.analyze', $prospect) }}" class="d-grid gap-2 mb-3">
            @csrf
            <button class="btn btn-primary" @disabled($hasActiveAnalysis)>
              {{ $hasActiveAnalysis ? 'Analyze In Progress' : 'Analyze Now' }}
            </button>
          </form>

          @php
            $latest = $prospect->latestAnalysis;
            $statusBadge = match($latest?->status) {
              'queued' => 'bg-secondary-lt',
              'running' => 'bg-yellow-lt',
              'success' => 'bg-green-lt',
              'failed' => 'bg-red-lt',
              default => 'bg-muted-lt',
            };
            $checklist = is_array($latest?->checklist_json) ? $latest->checklist_json : [];
          @endphp

          @if($latest)
            <div class="mb-2 d-flex justify-content-between align-items-center">
              <span class="badge {{ $statusBadge }}">{{ ucfirst($latest->status) }}</span>
              <div class="text-muted small">{{ $latest->finished_at?->format('d M Y H:i') ?: $latest->created_at?->format('d M Y H:i') }}</div>
            </div>
            <div class="mb-2"><strong>Score:</strong> {{ $latest->score ?? 0 }}/100</div>
            <div class="mb-2"><strong>Business Type:</strong> {{ $latest->business_type ?: 'unknown' }}</div>
            <div class="mb-2"><strong>Address Clarity:</strong> {{ $latest->address_clarity ?: '-' }}</div>
            <div class="mb-2"><strong>Website:</strong> {{ $latest->website_url ?: '-' }}</div>
            <div class="mb-2"><strong>Website Status:</strong>
              @if(!is_null($latest->website_http_status))
                HTTP {{ $latest->website_http_status }} ({{ $latest->website_reachable ? 'reachable' : 'not reachable' }})
              @else
                {{ $latest->website_reachable ? 'reachable' : 'not reachable' }}
              @endif
            </div>
            <div class="mb-2"><strong>Email Found:</strong> {{ count($latest->emails_json ?? []) }}</div>
            <div class="mb-2"><strong>LinkedIn Company:</strong>
              @if($latest->linkedin_company_url)
                <a href="{{ $latest->linkedin_company_url }}" target="_blank" rel="noopener">Open</a>
              @else
                -
              @endif
            </div>
            <div class="mb-2"><strong>LinkedIn People:</strong> {{ count($latest->linkedin_people_json ?? []) }}</div>
            @if(!empty($latest->linkedin_people_json))
              <div class="small">
                @foreach(array_slice($latest->linkedin_people_json, 0, 3) as $peopleUrl)
                  <div><a href="{{ $peopleUrl }}" target="_blank" rel="noopener">{{ $peopleUrl }}</a></div>
                @endforeach
              </div>
            @endif
            @if($latest->error_message)
              <div class="alert alert-danger py-2 px-3 mt-2 mb-0 small">{{ $latest->error_message }}</div>
            @endif

            <div class="mt-3">
              <div class="fw-semibold mb-1">Checklist</div>
              <ul class="mb-0 small">
                <li>Website Present: {{ !empty($checklist['website_present']) ? 'Yes' : 'No' }}</li>
                <li>Website Reachable: {{ !empty($checklist['website_reachable']) ? 'Yes' : 'No' }}</li>
                <li>Email Found: {{ !empty($checklist['email_found']) ? 'Yes' : 'No' }}</li>
                <li>LinkedIn Company: {{ !empty($checklist['linkedin_company_found']) ? 'Yes' : 'No' }}</li>
                <li>LinkedIn People: {{ !empty($checklist['linkedin_people_found']) ? 'Yes' : 'No' }}</li>
                <li>Business Identified: {{ !empty($checklist['business_type_identified']) ? 'Yes' : 'No' }}</li>
                <li>Address Clear: {{ !empty($checklist['address_clear']) ? 'Yes' : 'No' }}</li>
              </ul>
            </div>
          @else
            <div class="text-muted">Belum ada hasil analyze.</div>
          @endif
        </div>
      </div>

      <div class="card mb-3">
        <div class="card-header">
          <h3 class="card-title">Analysis History</h3>
          <div class="card-actions">
            <a href="#analysis-history" data-bs-toggle="collapse" aria-expanded="false">Toggle</a>
          </div>
        </div>
        <div id="analysis-history" class="collapse">
          <div class="card-body p-0">
            <div class="table-responsive">
              <table class="table table-sm table-vcenter mb-0">
                <thead>
                  <tr>
                    <th>At</th>
                    <th>Status</th>
                    <th>Score</th>
                    <th>Error</th>
                  </tr>
                </thead>
                <tbody>
                  @forelse($prospect->analyses as $analysis)
                    <tr>
                      <td class="small">{{ $analysis->created_at?->format('d M Y H:i') }}</td>
                      <td class="small">{{ $analysis->status }}</td>
                      <td class="small">{{ $analysis->score ?? '-' }}</td>
                      <td class="small">{{ $analysis->error_message ? \Illuminate\Support\Str::limit($analysis->error_message, 60) : '-' }}</td>
                    </tr>
                  @empty
                    <tr><td colspan="4" class="text-center text-muted small">No analysis history.</td></tr>
                  @endforelse
                </tbody>
              </table>
            </div>
          </div>
        </div>
      </div>

      <div class="card mb-3">
        <div class="card-header"><h3 class="card-title">Assign Owner</h3></div>
        <div class="card-body">
          <form method="post" action="{{ route('lead-discovery.prospects.assign', $prospect) }}" class="d-grid gap-2">
            @csrf
            <select name="owner_user_id" class="form-select">
              <option value="">Unassigned</option>
              @foreach($owners as $owner)
                <option value="{{ $owner->id }}" @selected((int) $prospect->owner_user_id === (int) $owner->id)>{{ $owner->name }}</option>
              @endforeach
            </select>
            <button class="btn btn-outline-primary">Save Owner</button>
          </form>
        </div>
      </div>

      <div class="card mb-3">
        <div class="card-header"><h3 class="card-title">Set Status</h3></div>
        <div class="card-body">
          <form method="post" action="{{ route('lead-discovery.prospects.status', $prospect) }}" class="d-grid gap-2">
            @csrf
            <select name="status" class="form-select">
              @foreach($statusOptions as $status)
                <option value="{{ $status }}" @selected($prospect->status === $status)>{{ ucfirst($status) }}</option>
              @endforeach
            </select>
            <button class="btn btn-outline-secondary">Save Status</button>
          </form>
        </div>
      </div>

      <div class="card">
        <div class="card-header"><h3 class="card-title">Convert to Customer</h3></div>
        <div class="card-body">
          <form method="post" action="{{ route('lead-discovery.prospects.convert', $prospect) }}" class="d-grid gap-2">
            @csrf
            <div>
              <label class="form-label">Category</label>
              <select name="jenis_id" class="form-select" required>
                <option value="">-- Select Category --</option>
                @foreach($jenisList as $jenis)
                  <option value="{{ $jenis->id }}">{{ $jenis->name }}</option>
                @endforeach
              </select>
            </div>
            <div>
              <label class="form-label">Owner</label>
              <select name="sales_user_id" class="form-select" required>
                <option value="">-- Select Owner --</option>
                @foreach($owners as $owner)
                  <option value="{{ $owner->id }}" @selected((int) $prospect->owner_user_id === (int) $owner->id)>{{ $owner->name }}</option>
                @endforeach
              </select>
            </div>
            <button class="btn btn-primary" @disabled($prospect->status === 'converted')>
              {{ $prospect->status === 'converted' ? 'Already Converted' : 'Convert Now' }}
            </button>
          </form>
        </div>
      </div>
    </div>
  </div>
</div>
@endsection
