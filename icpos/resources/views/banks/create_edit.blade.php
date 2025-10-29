@extends('layouts.tabler')

@section('content')
<div class="container-xl">
  <form method="POST" action="{{ $bank->exists ? route('banks.update', $bank) : route('banks.store') }}">
    @csrf
    @if($bank->exists) @method('PUT') @endif

    <div class="d-flex justify-content-between align-items-center mb-3">
      <h2 class="mb-0">{{ $bank->exists ? 'Edit Bank' : 'New Bank' }}</h2>
      <div class="btn-list">
        <a href="{{ route('banks.index') }}" class="btn btn-outline-secondary">Cancel</a>
        <button class="btn btn-primary">{{ $bank->exists ? 'Update' : 'Create' }}</button>
      </div>
    </div>

    <div class="card">
      <div class="card-body">
        <div class="row g-3">

          {{-- Company --}}
          <div class="col-md-4">
            <label class="form-label">Company</label>
            <select name="company_id" class="form-select" required>
              <option value="" disabled {{ old('company_id', $bank->company_id) ? '' : 'selected' }}>— Select company —</option>
              @foreach($companies as $c)
                <option value="{{ $c->id }}" @selected((int)old('company_id', $bank->company_id) === (int)$c->id)>
                  {{ $c->alias ?: $c->name }}
                </option>
              @endforeach
            </select>
            @error('company_id') <div class="text-danger small">{{ $message }}</div> @enderror
          </div>

          {{-- Bank name & code --}}
          <div class="col-md-4">
            <label class="form-label">Bank Name</label>
            <input type="text" name="name" class="form-control" required
                   value="{{ old('name', $bank->name) }}">
            @error('name') <div class="text-danger small">{{ $message }}</div> @enderror
          </div>

          <div class="col-md-4">
            <label class="form-label">Code (optional)</label>
            <input type="text" name="code" class="form-control"
                   value="{{ old('code', $bank->code) }}" placeholder="cth: BCA, BCA NON, BRI">
            @error('code') <div class="text-danger small">{{ $message }}</div> @enderror
          </div>

          {{-- Rekening --}}
          <div class="col-md-4">
            <label class="form-label">Account Name</label>
            <input type="text" name="account_name" class="form-control"
                   value="{{ old('account_name', $bank->account_name) }}">
          </div>
          <div class="col-md-4">
            <label class="form-label">Account No</label>
            <input type="text" name="account_no" class="form-control"
                   value="{{ old('account_no', $bank->account_no) }}">
          </div>
          <div class="col-md-4">
            <label class="form-label">Branch</label>
            <input type="text" name="branch" class="form-control"
                   value="{{ old('branch', $bank->branch) }}">
          </div>

          {{-- (Opsional) Scope PPN/NON-PPN bila dipakai untuk preferensi pembayaran --}}
          <div class="col-md-4">
            <label class="form-label">Tax Scope (optional)</label>
            <select name="tax_scope" class="form-select">
              <option value="" @selected(old('tax_scope', $bank->tax_scope) === null)>— None —</option>
              <option value="ppn" @selected(old('tax_scope', $bank->tax_scope) === 'ppn')>PPN</option>
              <option value="non_ppn" @selected(old('tax_scope', $bank->tax_scope) === 'non_ppn')>NON PPN</option>
            </select>
          </div>

          {{-- Active --}}
          <div class="col-md-2">
            <label class="form-label">Active</label>
            <label class="form-check form-switch mt-1">
              <input class="form-check-input" type="checkbox" name="is_active" value="1"
                     {{ old('is_active', (int)$bank->is_active) ? 'checked' : '' }}>
              <span class="form-check-label">Yes</span>
            </label>
          </div>

          {{-- Notes --}}
          <div class="col-12">
            <label class="form-label">Notes</label>
            <textarea name="notes" rows="3" class="form-control">{{ old('notes', $bank->notes) }}</textarea>
          </div>

        </div>
      </div>

      <div class="card-footer text-end">
        <button class="btn btn-primary">{{ $bank->exists ? 'Update' : 'Create' }}</button>
      </div>
    </div>
  </form>
</div>
@endsection
