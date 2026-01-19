{{-- resources/views/documents/index.blade.php --}}
@extends('layouts.tabler')

@section('content')
<div class="page-header d-print-none">
  <div class="row align-items-center">
    <div class="col">
      <h2 class="page-title">{{ $pageTitle ?? 'Documents' }}</h2>
      <div class="text-muted">Module dokumen resmi untuk Sales.</div>
    </div>
    <div class="col-auto ms-auto d-print-none">
      @if(!empty($showCreate))
        <a href="{{ route('documents.create') }}" class="btn btn-primary">
          <span class="ti ti-file-plus me-1"></span>
          New Document
        </a>
      @endif
    </div>
  </div>
</div>

@if(session('success'))
  <div class="alert alert-success">{{ session('success') }}</div>
@endif
@if(session('error'))
  <div class="alert alert-danger">{{ session('error') }}</div>
@endif

<div class="card">
  <div class="card-body">
    @if($documents->count() === 0)
      <div class="text-muted">Belum ada dokumen.</div>
    @else
      <div class="table-responsive">
        <table class="table table-vcenter">
          <thead>
            <tr>
              <th>Number</th>
              <th>Title</th>
              <th>Customer</th>
              @if(!empty($showOwner))
                <th>Owner</th>
              @endif
              <th>Status</th>
              <th class="text-end">Updated</th>
            </tr>
          </thead>
          <tbody>
            @foreach($documents as $document)
              <tr>
                <td>
                  <a href="{{ route('documents.show', $document) }}" class="fw-semibold">
                    {{ $document->number ?: 'DRAFT' }}
                  </a>
                </td>
                <td>{{ $document->title }}</td>
                <td>{{ $document->customer?->name ?? '-' }}</td>
                @if(!empty($showOwner))
                  <td>{{ $document->creator?->name ?? '-' }}</td>
                @endif
                <td>
                  <span class="badge {{ $document->status_badge_class }}">
                    {{ $document->status_label }}
                  </span>
                </td>
                <td class="text-end">
                  {{ $document->updated_at?->format('d M Y H:i') }}
                </td>
              </tr>
            @endforeach
          </tbody>
        </table>
      </div>
    @endif
  </div>
</div>

{{ $documents->links() }}
@endsection
