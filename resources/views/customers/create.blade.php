@extends('layouts.tabler')

@section('content')
<div class="page-wrapper">
  <div class="page-header d-print-none">
    <div class="container-xl">
      <div class="row g-2 align-items-center">
        <div class="col">
          <h2 class="page-title">Add Customer</h2>
        </div>
      </div>
    </div>
  </div>

  <div class="page-body">
    <div class="container-xl">

      @if ($errors->any())
        <div class="alert alert-danger">
          <div class="fw-bold mb-1">Validasi gagal</div>
          <ul class="mb-0">
            @foreach ($errors->all() as $e)
              <li>{{ $e }}</li>
            @endforeach
          </ul>
        </div>
      @endif

      <form method="POST" action="{{ route('customers.store') }}">
        @csrf

        <div class="card">
          <div class="card-body">

            <ul class="nav nav-tabs" data-bs-toggle="tabs">
              <li class="nav-item">
                <a href="#tab-profile" class="nav-link active" data-bs-toggle="tab">Customer Details</a>
              </li>
              <li class="nav-item">
                <a href="#tab-billing" class="nav-link" data-bs-toggle="tab">Billing &amp; Shipping</a>
              </li>
            </ul>

            <div class="tab-content pt-3">
              <div class="tab-pane active show" id="tab-profile">

                <div class="row g-3">

                  <div class="col-md-6">
                    <label class="form-label">Customer Name <span class="text-danger">*</span></label>
                    <input type="text" name="name" value="{{ old('name') }}" class="form-control" required>
                  </div>

                  <div class="col-md-6 d-flex align-items-end justify-content-end">
                    <button type="button" class="btn btn-outline-primary" id="btnPlaces">
                      Cari di Google Places
                    </button>
                  </div>

                  <div class="col-md-6">
                    <label class="form-label">Jenis <span class="text-danger">*</span></label>
                    <select name="jenis_id" class="form-select" required>
                      <option value="">-- pilih --</option>
                      @foreach ($jenisList as $j)
                        <option value="{{ $j->id }}" @selected(old('jenis_id') == $j->id)>
                          {{ $j->name }}
                        </option>
                      @endforeach
                    </select>
                  </div>

                  {{-- ✅ Sales Owner --}}
                  <div class="col-md-6">
                    <label class="form-label">Sales (Owner) <span class="text-danger">*</span></label>

                    @if(auth()->user()->hasAnyRole(['Admin','SuperAdmin','Finance']))
                      <select name="sales_user_id" class="form-select" required>
                        <option value="">-- pilih sales --</option>
                        @foreach($salesUsers as $u)
                          <option value="{{ $u->id }}" @selected(old('sales_user_id') == $u->id)>
                            {{ $u->name }}
                          </option>
                        @endforeach
                      </select>
                    @else
                      <input type="text" class="form-control" value="{{ auth()->user()->name }}" readonly>
                      <input type="hidden" name="sales_user_id" value="{{ auth()->id() }}">
                    @endif
                  </div>

                  <div class="col-md-6">
                    <label class="form-label">Phone</label>
                    <input type="text" name="phone" value="{{ old('phone') }}" class="form-control">
                  </div>

                  <div class="col-md-6">
                    <label class="form-label">Email</label>
                    <input type="email" name="email" value="{{ old('email') }}" class="form-control">
                  </div>

                  <div class="col-md-6">
                    <label class="form-label">Website</label>
                    <input type="text" name="website" value="{{ old('website') }}" class="form-control" placeholder="https://... (optional)">
                  </div>

                  <div class="col-md-6">
                    <label class="form-label">Billing Terms (days)</label>
                    <input type="number" name="billing_terms_days" value="{{ old('billing_terms_days') }}" class="form-control" placeholder="mis. 30 (optional)">
                  </div>

                  <div class="col-12">
                    <label class="form-label">Address</label>
                    <textarea name="address" class="form-control" rows="2">{{ old('address') }}</textarea>
                  </div>

                  <div class="col-md-4">
                    <label class="form-label">City</label>
                    <input type="text" name="city" value="{{ old('city') }}" class="form-control">
                  </div>

                  <div class="col-md-4">
                    <label class="form-label">Province</label>
                    <input type="text" name="province" value="{{ old('province') }}" class="form-control">
                  </div>

                  <div class="col-md-4">
                    <label class="form-label">Country</label>
                    <input type="text" name="country" value="{{ old('country') }}" class="form-control">
                  </div>

                  <div class="col-md-6">
                    <label class="form-label">NPWP</label>
                    <input type="text" name="npwp" value="{{ old('npwp') }}" class="form-control">
                  </div>

                  <div class="col-12">
                    <label class="form-label">Notes</label>
                    <textarea name="notes" class="form-control" rows="3">{{ old('notes') }}</textarea>
                  </div>

                </div>
              </div>

              <div class="tab-pane" id="tab-billing">
                <div class="row g-3">

                  <div class="col-12">
                    <div class="fw-bold mb-1">Billing</div>
                  </div>

                  <div class="col-12">
                    <label class="form-label">Street</label>
                    <textarea name="billing_street" class="form-control" rows="2">{{ old('billing_street') }}</textarea>
                  </div>

                  <div class="col-md-4">
                    <label class="form-label">City</label>
                    <input name="billing_city" class="form-control" value="{{ old('billing_city') }}">
                  </div>

                  <div class="col-md-4">
                    <label class="form-label">State/Province</label>
                    <input name="billing_state" class="form-control" value="{{ old('billing_state') }}">
                  </div>

                  <div class="col-md-4">
                    <label class="form-label">Zip</label>
                    <input name="billing_zip" class="form-control" value="{{ old('billing_zip') }}">
                  </div>

                  <div class="col-md-4">
                    <label class="form-label">Country (ISO2)</label>
                    <input name="billing_country" class="form-control" value="{{ old('billing_country') }}" placeholder="ID">
                  </div>

                  <div class="col-12">
                    <label class="form-label">Notes</label>
                    <textarea name="billing_notes" class="form-control" rows="2">{{ old('billing_notes') }}</textarea>
                  </div>

                  <div class="col-12 mt-3">
                    <div class="fw-bold mb-1">Shipping</div>
                  </div>

                  <div class="col-12">
                    <label class="form-label">Street</label>
                    <textarea name="shipping_street" class="form-control" rows="2">{{ old('shipping_street') }}</textarea>
                  </div>

                  <div class="col-md-4">
                    <label class="form-label">City</label>
                    <input name="shipping_city" class="form-control" value="{{ old('shipping_city') }}">
                  </div>

                  <div class="col-md-4">
                    <label class="form-label">State/Province</label>
                    <input name="shipping_state" class="form-control" value="{{ old('shipping_state') }}">
                  </div>

                  <div class="col-md-4">
                    <label class="form-label">Zip</label>
                    <input name="shipping_zip" class="form-control" value="{{ old('shipping_zip') }}">
                  </div>

                  <div class="col-md-4">
                    <label class="form-label">Country (ISO2)</label>
                    <input name="shipping_country" class="form-control" value="{{ old('shipping_country') }}" placeholder="ID">
                  </div>

                  <div class="col-12">
                    <label class="form-label">Notes</label>
                    <textarea name="shipping_notes" class="form-control" rows="2">{{ old('shipping_notes') }}</textarea>
                  </div>

                </div>
              </div>
            </div>

          </div>

          <div class="card-footer">
            <div class="d-flex justify-content-between">
              <a href="{{ route('customers.index') }}" class="btn btn-ghost-secondary">Batal</a>
              <button type="submit" class="btn btn-primary">Simpan</button>
            </div>
          </div>
        </div>

      </form>

    </div>
  </div>
</div>

@include('customers.partials.places_modal')
@endsection
