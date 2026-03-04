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
