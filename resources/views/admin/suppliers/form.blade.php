@extends('layouts.tabler')

@section('content')
<div class="container-xl">
  <div class="page-header d-print-none mb-3">
    <div class="row align-items-center">
      <div class="col">
        <h2 class="page-title">{{ $row->exists ? 'Edit Supplier' : 'New Supplier' }}</h2>
      </div>
    </div>
  </div>

  <form method="POST" action="{{ $row->exists ? route('suppliers.update', $row) : route('suppliers.store') }}">
    @csrf
    @if($row->exists) @method('PUT') @endif
    <div class="card">
      <div class="card-body">
        <div class="row g-3">
          <div class="col-md-6">
            <label class="form-label">Nama</label>
            <input type="text" name="name" class="form-control" value="{{ old('name', $row->name) }}" required>
          </div>
          <div class="col-md-3">
            <label class="form-label">Telp</label>
            <input type="text" name="phone" class="form-control" value="{{ old('phone', $row->phone) }}">
          </div>
          <div class="col-md-3">
            <label class="form-label">Email</label>
            <input type="email" name="email" class="form-control" value="{{ old('email', $row->email) }}">
          </div>
          <div class="col-12">
            <label class="form-label">Alamat</label>
            <textarea name="address" class="form-control" rows="2">{{ old('address', $row->address) }}</textarea>
          </div>
          <div class="col-12">
            <label class="form-label">Notes</label>
            <textarea name="notes" class="form-control" rows="2">{{ old('notes', $row->notes) }}</textarea>
          </div>
          <div class="col-12">
            <label class="form-check">
              <input type="checkbox" name="is_active" class="form-check-input" value="1"
                     @checked(old('is_active', $row->is_active))>
              <span class="form-check-label">Active</span>
            </label>
          </div>
        </div>
      </div>
      <div class="card-footer d-flex">
        <a href="{{ route('suppliers.index') }}" class="btn btn-link">Back</a>
        <button class="btn btn-primary ms-auto" type="submit">Save</button>
      </div>
    </div>
  </form>
</div>
@endsection
