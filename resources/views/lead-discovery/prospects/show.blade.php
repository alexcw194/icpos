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
            'rejected' => 'bg-red-lt',
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
          <div class="mt-2">
            <label class="form-label mb-1"><strong>Website</strong></label>
            <form method="post" action="{{ route('lead-discovery.prospects.website.update', $prospect) }}" class="d-flex gap-2">
              @csrf
              <input type="text" name="website" class="form-control @error('website') is-invalid @enderror" value="{{ old('website', $prospect->website) }}" placeholder="https://example.com">
              <button type="submit" class="btn btn-outline-primary text-nowrap">Save Website</button>
            </form>
            @error('website')
              <div class="invalid-feedback d-block">{{ $message }}</div>
            @enderror
            <div class="small text-muted mt-1">
              @if($prospect->website)
                Link: <a href="{{ $prospect->website }}" target="_blank" rel="noopener">{{ $prospect->website }}</a>
              @else
                Belum ada website.
              @endif
            </div>
          </div>
          <div><strong>City/Province:</strong> {{ $prospect->city ?: '-' }} / {{ $prospect->province ?: '-' }}</div>
        </div>
      </div>

      <div class="card mb-3">
        <div class="card-header"><h3 class="card-title">Manual Lead Profile</h3></div>
        <div class="card-body">
          <form method="post" action="{{ route('lead-discovery.prospects.manual-profile.update', $prospect) }}" class="row g-2">
            @csrf
            <div class="col-md-6">
              <label class="form-label">Sub Industry (Manual)</label>
              <input type="text" name="manual_sub_industry" class="form-control @error('manual_sub_industry') is-invalid @enderror"
                     value="{{ old('manual_sub_industry', $prospect->manual_sub_industry) }}"
                     placeholder="Contoh: Tempered Glass">
              @error('manual_sub_industry')
                <div class="invalid-feedback">{{ $message }}</div>
              @enderror
            </div>
            <div class="col-md-6">
              <label class="form-label">Employee Range (Manual)</label>
              <input type="text" name="manual_employee_range" class="form-control @error('manual_employee_range') is-invalid @enderror"
                     value="{{ old('manual_employee_range', $prospect->manual_employee_range) }}"
                     placeholder="Contoh: 51-200">
              @error('manual_employee_range')
                <div class="invalid-feedback">{{ $message }}</div>
              @enderror
            </div>
            <div class="col-md-12">
              <label class="form-label">LinkedIn URL (Manual)</label>
              <input type="text" name="manual_linkedin_url" class="form-control @error('manual_linkedin_url') is-invalid @enderror"
                     value="{{ old('manual_linkedin_url', $prospect->manual_linkedin_url) }}"
                     placeholder="https://www.linkedin.com/company/...">
              @error('manual_linkedin_url')
                <div class="invalid-feedback">{{ $message }}</div>
              @enderror
            </div>
            <div class="col-12 d-flex justify-content-between align-items-center">
              <div class="small text-muted">
                @if($prospect->manual_linkedin_url)
                  Link: <a href="{{ $prospect->manual_linkedin_url }}" target="_blank" rel="noopener">{{ $prospect->manual_linkedin_url }}</a>
                @else
                  Isi manual jika data dari Apollo/Analyze belum lengkap.
                @endif
              </div>
              <button type="submit" class="btn btn-outline-primary">Save Manual Profile</button>
            </div>
          </form>
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
            <div class="mb-2"><strong>AI Status:</strong> {{ $latest->ai_status ?: 'not_run' }}</div>
            <div class="mb-2"><strong>AI Source:</strong> {{ $latest->ai_provider ?: '-' }}{{ $latest->ai_model ? (' / ' . $latest->ai_model) : '' }}</div>
            <div class="mb-2"><strong>AI Industry:</strong> {{ $latest->ai_industry_label ?: '-' }}</div>
            <div class="mb-2"><strong>AI Sub Industry:</strong> {{ $latest->ai_sub_industry ?: '-' }}</div>
            <div class="mb-2"><strong>AI Employee Range:</strong> {{ $latest->ai_employee_range ? ($latest->ai_employee_range . ' karyawan') : '-' }}</div>
            <div class="mb-2"><strong>AI Hotel Star:</strong> {{ $latest->ai_hotel_star ? ($latest->ai_hotel_star . ' star') : '-' }}</div>
            <div class="mb-2"><strong>AI Business Output:</strong> {{ $latest->ai_business_output ?: '-' }}</div>
            <div class="mb-2"><strong>AI Confidence:</strong> {{ is_null($latest->ai_confidence) ? '-' : (number_format((float) $latest->ai_confidence, 2) . '%') }}</div>
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
            @if($latest->ai_error_message)
              <div class="alert alert-warning py-2 px-3 mt-2 mb-0 small">{{ $latest->ai_error_message }}</div>
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
        <div class="card-header"><h3 class="card-title">Apollo Enrichment</h3></div>
        <div class="card-body">
          <form method="post" action="{{ route('lead-discovery.prospects.enrich-apollo', $prospect) }}" class="d-grid gap-2 mb-3">
            @csrf
            <button class="btn btn-outline-primary" @disabled($hasActiveApollo)>
              {{ $hasActiveApollo ? 'Enrichment In Progress' : 'Enrich Apollo' }}
            </button>
          </form>

          @php
            $latestApollo = $prospect->latestApolloEnrichment;
            $apolloStatusBadge = match($latestApollo?->status) {
              'queued' => 'bg-secondary-lt',
              'running' => 'bg-yellow-lt',
              'success' => 'bg-green-lt',
              'failed' => 'bg-red-lt',
              default => 'bg-muted-lt',
            };
          @endphp

          @if($latestApollo)
            <div class="mb-2 d-flex justify-content-between align-items-center">
              <span class="badge {{ $apolloStatusBadge }}">{{ ucfirst($latestApollo->status) }}</span>
              <div class="text-muted small">{{ $latestApollo->finished_at?->format('d M Y H:i') ?: $latestApollo->created_at?->format('d M Y H:i') }}</div>
            </div>
            <div class="mb-2"><strong>Matched By:</strong> {{ $latestApollo->matched_by ?: '-' }}</div>
            <div class="mb-2"><strong>Organization:</strong> {{ $latestApollo->apollo_org_name ?: '-' }}</div>
            <div class="mb-2"><strong>Domain:</strong> {{ $latestApollo->apollo_domain ?: '-' }}</div>
            <div class="mb-2"><strong>Website:</strong> {{ $latestApollo->apollo_website_url ?: '-' }}</div>
            <div class="mb-2"><strong>LinkedIn:</strong>
              @php
                $effectiveLinkedin = $prospect->manual_linkedin_url ?: $latestApollo->apollo_linkedin_url;
                $effectiveSubIndustry = $prospect->manual_sub_industry ?: $latestApollo->apollo_sub_industry;
                $effectiveEmployeeRange = $prospect->manual_employee_range ?: $latestApollo->apollo_employee_range;
              @endphp
              @if($effectiveLinkedin)
                <a href="{{ $effectiveLinkedin }}" target="_blank" rel="noopener">Open</a>
                @if($prospect->manual_linkedin_url)
                  <span class="text-muted small">(manual)</span>
                @endif
              @else
                -
              @endif
            </div>
            <div class="mb-2"><strong>Industry:</strong> {{ $latestApollo->apollo_industry ?: '-' }}</div>
            <div class="mb-2"><strong>Sub Industry:</strong> {{ $effectiveSubIndustry ?: '-' }}</div>
            <div class="mb-2"><strong>Business Output:</strong> {{ $latestApollo->apollo_business_output ?: '-' }}</div>
            <div class="mb-2"><strong>Employee Range:</strong> {{ $effectiveEmployeeRange ? ($effectiveEmployeeRange . ' karyawan') : '-' }}</div>
            <div class="mb-2"><strong>Location:</strong> {{ $latestApollo->apollo_city ?: '-' }} / {{ $latestApollo->apollo_state ?: '-' }} / {{ $latestApollo->apollo_country ?: '-' }}</div>

            <div class="mt-2">
              <div class="fw-semibold mb-1">Top People</div>
              @if(!empty($latestApollo->apollo_people_json))
                <ul class="small mb-0">
                  @foreach($latestApollo->apollo_people_json as $person)
                    <li>
                      {{ $person['name'] ?? '-' }}
                      @if(!empty($person['title'])) - {{ $person['title'] }} @endif
                      @if(!empty($person['linkedin_url'])) (<a href="{{ $person['linkedin_url'] }}" target="_blank" rel="noopener">LinkedIn</a>) @endif
                    </li>
                  @endforeach
                </ul>
              @else
                <div class="text-muted small">Tidak ada data people.</div>
              @endif
            </div>

            @if($latestApollo->error_message)
              <div class="alert alert-danger py-2 px-3 mt-2 mb-0 small">{{ $latestApollo->error_message }}</div>
            @endif
          @else
            <div class="text-muted">Belum ada hasil Apollo enrichment.</div>
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
        <div class="card-header">
          <h3 class="card-title">Apollo Enrichment History</h3>
          <div class="card-actions">
            <a href="#apollo-history" data-bs-toggle="collapse" aria-expanded="false">Toggle</a>
          </div>
        </div>
        <div id="apollo-history" class="collapse">
          <div class="card-body p-0">
            <div class="table-responsive">
              <table class="table table-sm table-vcenter mb-0">
                <thead>
                  <tr>
                    <th>At</th>
                    <th>Status</th>
                    <th>Match</th>
                    <th>Org</th>
                    <th>Error</th>
                  </tr>
                </thead>
                <tbody>
                  @forelse($prospect->apolloEnrichments as $apollo)
                    <tr>
                      <td class="small">{{ $apollo->created_at?->format('d M Y H:i') }}</td>
                      <td class="small">{{ $apollo->status }}</td>
                      <td class="small">{{ $apollo->matched_by ?: '-' }}</td>
                      <td class="small">{{ \Illuminate\Support\Str::limit($apollo->apollo_org_name ?: '-', 40) }}</td>
                      <td class="small">{{ $apollo->error_message ? \Illuminate\Support\Str::limit($apollo->error_message, 60) : '-' }}</td>
                    </tr>
                  @empty
                    <tr><td colspan="5" class="text-center text-muted small">No enrichment history.</td></tr>
                  @endforelse
                </tbody>
              </table>
            </div>
          </div>
        </div>
      </div>

      <div class="card mb-3">
        <div class="card-header"><h3 class="card-title">Send to New Leads</h3></div>
        <div class="card-body">
          <form method="post" action="{{ route('lead-discovery.prospects.assign', $prospect) }}" class="d-grid gap-2">
            @csrf
            <select name="owner_user_id" class="form-select">
              <option value="">Unassigned</option>
              @foreach($owners as $owner)
                <option value="{{ $owner->id }}" @selected((int) $prospect->owner_user_id === (int) $owner->id)>{{ $owner->name }}</option>
              @endforeach
            </select>
            <button class="btn btn-outline-primary">Send to New Leads</button>
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
