@extends('layouts.tabler')

@section('content')
<div class="page-header d-print-none">
  <div class="container-xl d-flex align-items-center">
    <h2 class="page-title">
      Master Data - Term of Payment
      <span class="text-muted">{{ $row->exists ? 'Edit' : 'Create' }}</span>
    </h2>
    <a href="{{ route('term-of-payments.index') }}" class="btn btn-link ms-auto">Kembali</a>
  </div>
</div>

<div class="page-body">
  <div class="container-xl">
    @if ($errors->any())
      <div class="alert alert-danger">
        <ul class="mb-0">
          @foreach ($errors->all() as $error)
            <li>{{ $error }}</li>
          @endforeach
        </ul>
      </div>
    @endif

    <div class="card">
      <form method="post" action="{{ $row->exists ? route('term-of-payments.update', $row) : route('term-of-payments.store') }}">
        @csrf
        @if($row->exists) @method('PUT') @endif
        <div class="card-body">
          <div class="row g-3">
            <div class="col-md-4">
              <label class="form-label">Code</label>
              @if($row->exists)
                <input type="text" class="form-control" value="{{ $row->code }}" readonly>
              @else
                <select name="code" class="form-select" required>
                  <option value="">— pilih kode —</option>
                  @foreach($availableCodes as $code)
                    <option value="{{ $code }}" @selected(old('code') === $code)>{{ $code }}</option>
                  @endforeach
                </select>
                <div class="form-hint">Kode hanya boleh dari whitelist sistem.</div>
              @endif
            </div>
            <div class="col-md-6">
              <label class="form-label">Description</label>
              <input type="text" name="description" class="form-control"
                     value="{{ old('description', $row->description) }}"
                     placeholder="Optional (mis: Down Payment)">
            </div>
            <div class="col-md-2">
              <label class="form-label">Active</label>
              <label class="form-check form-switch">
                <input class="form-check-input" type="checkbox" name="is_active" value="1"
                       @checked(old('is_active', $row->is_active))>
                <span class="form-check-label">Active</span>
              </label>
            </div>
          </div>
        </div>
        <div class="card-footer text-end">
          <button type="submit" class="btn btn-primary">{{ $row->exists ? 'Update' : 'Create' }}</button>
        </div>
      </form>
    </div>
  </div>
</div>
@endsection
