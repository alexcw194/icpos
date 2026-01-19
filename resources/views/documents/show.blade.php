{{-- resources/views/documents/show.blade.php --}}
@extends('layouts.tabler')

@section('content')
<div class="page-header d-print-none">
  <div class="row align-items-center">
    <div class="col">
      <h2 class="page-title">
        {{ $document->number ?: 'DRAFT' }}
      </h2>
      <div class="text-muted">
        <span class="badge {{ $document->status_badge_class }}">{{ $document->status_label }}</span>
        <span class="ms-2">{{ $document->title }}</span>
      </div>
    </div>
    <div class="col-auto ms-auto d-print-none d-flex flex-wrap gap-2">
      <a href="{{ route('documents.pdf', $document) }}" class="btn btn-outline-secondary">Preview PDF</a>
      <a href="{{ route('documents.pdf-download', $document) }}" class="btn btn-outline-secondary">Download</a>

      @can('update', $document)
        <a href="{{ route('documents.edit', $document) }}" class="btn btn-primary">Edit</a>
      @endcan

      @can('submit', $document)
        <form method="post" action="{{ route('documents.submit', $document) }}">
          @csrf
          <button type="submit" class="btn btn-success">Submit for Approval</button>
        </form>
      @endcan

      @can('approve', $document)
        <form method="post" action="{{ route('documents.approve', $document) }}">
          @csrf
          <button type="submit" class="btn btn-success">Approve</button>
        </form>
      @endcan

      @can('reject', $document)
        <button type="button" class="btn btn-outline-danger" data-bs-toggle="modal" data-bs-target="#rejectModal">
          Reject
        </button>
      @endcan

      @can('delete', $document)
        <form method="post" action="{{ route('documents.destroy', $document) }}"
              onsubmit="return confirm('Hapus dokumen ini? Tindakan ini tidak bisa dibatalkan.');">
          @csrf
          @method('DELETE')
          <button type="submit" class="btn btn-outline-danger">Delete</button>
        </form>
      @endcan
    </div>
  </div>
</div>

@if(session('success'))
  <div class="alert alert-success">{{ session('success') }}</div>
@endif
@if(session('error'))
  <div class="alert alert-danger">{{ session('error') }}</div>
@endif

<div class="row g-3">
  <div class="col-lg-8">
    <div class="card">
      <div class="card-body">
        <h3 class="card-title">Recipient</h3>
        <div class="mb-3">
          <div class="fw-semibold">{{ data_get($document->customer_snapshot, 'name') }}</div>
          <div class="text-muted">
            {{ data_get($document->customer_snapshot, 'address') }}
            @if(data_get($document->customer_snapshot, 'city')) , {{ data_get($document->customer_snapshot, 'city') }} @endif
          </div>
          <div class="text-muted">
            {{ data_get($document->customer_snapshot, 'phone') }}
            @if(data_get($document->customer_snapshot, 'email')) &middot; {{ data_get($document->customer_snapshot, 'email') }} @endif
          </div>
        </div>
        @if($document->contact_snapshot)
          <div class="mb-4">
            <div class="fw-semibold">Up. {{ data_get($document->contact_snapshot, 'name') }}</div>
            <div class="text-muted">
              {{ data_get($document->contact_snapshot, 'position') }}
              @if(data_get($document->contact_snapshot, 'email')) &middot; {{ data_get($document->contact_snapshot, 'email') }} @endif
            </div>
          </div>
        @endif

        <h3 class="card-title">Body</h3>
        <div class="border rounded p-3">
          {!! $document->body_html !!}
        </div>
      </div>
    </div>

    @if($document->rejection_note)
      <div class="alert alert-danger mt-3">
        <strong>Rejection note:</strong> {{ $document->rejection_note }}
      </div>
    @endif
  </div>

  <div class="col-lg-4">
    <div class="card">
      <div class="card-body">
        <h3 class="card-title">Audit</h3>
        <dl class="row">
          <dt class="col-5">Created By</dt>
          <dd class="col-7">{{ $document->creator?->name ?? '-' }}</dd>

          <dt class="col-5">Signature</dt>
          <dd class="col-7">
            {{ $document->salesSigner?->name ?? 'Direktur Utama' }}
          </dd>

          <dt class="col-5">Submitted</dt>
          <dd class="col-7">{{ $document->submitted_at?->format('d M Y H:i') ?? '-' }}</dd>

          <dt class="col-5">Approval</dt>
          <dd class="col-7">
            {{ $document->approver?->name ?? '-' }}
            @if($document->approved_at)
              <div class="text-muted small">{{ $document->approved_at->format('d M Y H:i') }}</div>
            @endif
          </dd>

          <dt class="col-5">Number</dt>
          <dd class="col-7">{{ $document->number ?? '-' }}</dd>
        </dl>
      </div>
    </div>
  </div>
</div>

@can('reject', $document)
  <div class="modal fade" id="rejectModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
      <form method="post" action="{{ route('documents.reject', $document) }}" class="modal-content">
        @csrf
        <div class="modal-header">
          <h5 class="modal-title">Reject Document</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
          <label class="form-label">Rejection note</label>
          <textarea name="rejection_note" class="form-control" rows="4" required></textarea>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-danger">Reject</button>
        </div>
      </form>
    </div>
  </div>
@endcan
@endsection
