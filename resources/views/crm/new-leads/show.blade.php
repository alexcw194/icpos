@extends('layouts.tabler')

@section('content')
<div class="container-xl">
  <div class="page-header mb-3">
    <div class="row align-items-center">
      <div class="col">
        <div class="page-pretitle">CRM / New Leads</div>
        <h2 class="page-title">{{ $prospect->name }}</h2>
        <div class="text-muted small">{{ $prospect->place_id }}</div>
      </div>
      <div class="col-auto">
        @php
          $statusBadge = match($prospect->status) {
            'assigned' => 'bg-azure-lt',
            'rejected' => 'bg-red-lt',
            'converted' => 'bg-green-lt',
            default => 'bg-muted-lt',
          };
        @endphp
        <span class="badge {{ $statusBadge }}">{{ ucfirst($prospect->status) }}</span>
      </div>
    </div>
  </div>

  <div class="row g-3">
    <div class="col-lg-8">
      <div class="card mb-3">
        <div class="card-header"><h3 class="card-title">Lead Summary</h3></div>
        <div class="card-body">
          <div><strong>Address:</strong> {{ $prospect->formatted_address ?: $prospect->short_address ?: '-' }}</div>
          <div><strong>City/Province:</strong> {{ $prospect->city ?: '-' }} / {{ $prospect->province ?: '-' }}</div>
          <div><strong>Phone:</strong> {{ $prospect->phone ?: '-' }}</div>
          <div><strong>Website:</strong>
            @if($prospect->website)
              <a href="{{ $prospect->website }}" target="_blank" rel="noopener">{{ $prospect->website }}</a>
            @else
              -
            @endif
          </div>
          <div><strong>Sub Industry:</strong> {{ $prospect->manual_sub_industry ?: '-' }}</div>
          <div><strong>Employee Range:</strong> {{ $prospect->manual_employee_range ?: '-' }}</div>
          <div><strong>LinkedIn:</strong>
            @if($prospect->manual_linkedin_url)
              <a href="{{ $prospect->manual_linkedin_url }}" target="_blank" rel="noopener">{{ $prospect->manual_linkedin_url }}</a>
            @else
              -
            @endif
          </div>
          <div><strong>Assigned To:</strong> {{ $prospect->owner?->name ?: '-' }}</div>
          <div><strong>Assigned At:</strong> {{ $prospect->assigned_at?->format('d M Y H:i:s') ?: '-' }}</div>
          <div><strong>Assigned By:</strong> {{ $prospect->assignedBy?->name ?: '-' }}</div>
          @if($prospect->status === 'rejected')
            <div><strong>Rejected At:</strong> {{ $prospect->rejected_at?->format('d M Y H:i:s') ?: '-' }}</div>
            <div><strong>Rejected By:</strong> {{ $prospect->rejectedBy?->name ?: '-' }}</div>
            <div><strong>Reject Reason:</strong> {{ $prospect->reject_reason ?: '-' }}</div>
          @endif
          @if($prospect->converted_customer_id)
            <div><strong>Converted Customer:</strong> <a href="{{ route('customers.show', $prospect->converted_customer_id) }}">{{ $prospect->convertedCustomer?->name ?: ('Customer #' . $prospect->converted_customer_id) }}</a></div>
          @endif
        </div>
      </div>

      <div class="card mb-3">
        <div class="card-header"><h3 class="card-title">Latest Analysis</h3></div>
        <div class="card-body">
          @php
            $latest = $prospect->latestAnalysis;
            $checklist = is_array($latest?->checklist_json) ? $latest->checklist_json : [];
          @endphp

          @if($latest)
            <div><strong>Status:</strong> {{ $latest->status }}</div>
            <div><strong>Finished:</strong> {{ $latest->finished_at?->format('d M Y H:i:s') ?: '-' }}</div>
            <div><strong>Score:</strong> {{ $latest->score ?? 0 }}/100</div>
            <div><strong>Heuristic Type:</strong> {{ $latest->business_type ?: 'unknown' }}</div>
            <div><strong>AI Status:</strong> {{ $latest->ai_status ?: 'not_run' }}</div>
            <div><strong>AI Source:</strong> {{ $latest->ai_provider ?: '-' }}{{ $latest->ai_model ? (' / ' . $latest->ai_model) : '' }}</div>
            <div><strong>AI Industry:</strong> {{ $latest->ai_industry_label ?: '-' }}</div>
            <div><strong>AI Sub Industry:</strong> {{ $latest->ai_sub_industry ?: '-' }}</div>
            <div><strong>AI Employee Range:</strong> {{ $latest->ai_employee_range ? ($latest->ai_employee_range . ' karyawan') : '-' }}</div>
            <div><strong>AI Hotel Star:</strong> {{ $latest->ai_hotel_star ? ($latest->ai_hotel_star . ' star') : '-' }}</div>
            <div><strong>AI Output:</strong> {{ $latest->ai_business_output ?: '-' }}</div>
            <div><strong>AI Confidence:</strong> {{ is_null($latest->ai_confidence) ? '-' : (number_format((float) $latest->ai_confidence, 2) . '%') }}</div>
            <div><strong>Email Found:</strong> {{ count($latest->emails_json ?? []) }}</div>
            <div><strong>LinkedIn Company:</strong>
              @if($latest->linkedin_company_url)
                <a href="{{ $latest->linkedin_company_url }}" target="_blank" rel="noopener">Open</a>
              @else
                -
              @endif
            </div>

            @if($latest->ai_error_message)
              <div class="alert alert-warning py-2 px-3 mt-2 mb-0 small">{{ $latest->ai_error_message }}</div>
            @endif

            <hr>
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
          @else
            <div class="text-muted">Belum ada analysis untuk lead ini.</div>
          @endif
        </div>
      </div>

      <div class="card">
        <div class="card-header">
          <h3 class="card-title">Analysis History</h3>
          <div class="card-actions">
            <a href="#new-lead-analysis-history" data-bs-toggle="collapse" aria-expanded="false">Toggle</a>
          </div>
        </div>
        <div id="new-lead-analysis-history" class="collapse">
          <div class="table-responsive">
            <table class="table table-sm table-vcenter mb-0">
              <thead>
                <tr>
                  <th>At</th>
                  <th>Status</th>
                  <th>AI</th>
                  <th>Score</th>
                </tr>
              </thead>
              <tbody>
                @forelse($prospect->analyses as $analysis)
                  <tr>
                    <td>{{ $analysis->created_at?->format('d M Y H:i') ?: '-' }}</td>
                    <td>{{ $analysis->status }}</td>
                    <td>{{ $analysis->ai_status ?: '-' }}</td>
                    <td>{{ $analysis->score ?? '-' }}</td>
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

    <div class="col-lg-4">
      <div class="card mb-3">
        <div class="card-header"><h3 class="card-title">Add as Customer</h3></div>
        <div class="card-body">
          <form method="post" action="{{ route('crm.new-leads.add-customer', $prospect) }}" class="d-grid gap-2">
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

            @if($isAdmin)
              <div>
                <label class="form-label">Sales Owner</label>
                <select name="sales_user_id" class="form-select">
                  <option value="">Use current lead owner</option>
                  @foreach($ownerOptions as $owner)
                    <option value="{{ $owner->id }}" @selected((int) $prospect->owner_user_id === (int) $owner->id)>{{ $owner->name }}</option>
                  @endforeach
                </select>
              </div>
            @endif

            <button class="btn btn-primary" @disabled($prospect->status === 'converted')>
              {{ $prospect->status === 'converted' ? 'Already Converted' : 'Add as Customer' }}
            </button>
          </form>
        </div>
      </div>

      @if($prospect->status !== 'converted')
        <div class="card mb-3">
          <div class="card-header"><h3 class="card-title">Reject Lead</h3></div>
          <div class="card-body">
            <form method="post" action="{{ route('crm.new-leads.reject', $prospect) }}" class="d-grid gap-2">
              @csrf
              <textarea name="reason" rows="3" class="form-control" placeholder="Opsional alasan reject"></textarea>
              <button class="btn btn-outline-danger">Reject</button>
            </form>
          </div>
        </div>
      @endif

      @if($isAdmin)
        <div class="card">
          <div class="card-header"><h3 class="card-title">Reassign</h3></div>
          <div class="card-body">
            <form method="post" action="{{ route('crm.new-leads.reassign', $prospect) }}" class="d-grid gap-2">
              @csrf
              <select name="owner_user_id" class="form-select" required>
                <option value="">-- Select Sales --</option>
                @foreach($ownerOptions as $owner)
                  <option value="{{ $owner->id }}" @selected((int) $prospect->owner_user_id === (int) $owner->id)>{{ $owner->name }}</option>
                @endforeach
              </select>
              <button class="btn btn-outline-primary">Reassign</button>
            </form>
          </div>
        </div>
      @endif
    </div>
  </div>
</div>
@endsection
