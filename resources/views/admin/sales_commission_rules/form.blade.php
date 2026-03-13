@extends('layouts.tabler')

@section('content')
<div class="page-header d-print-none">
  <div class="container-xl">
    <h2 class="page-title">{{ $row->exists ? 'Edit Sales Commission Rule' : 'Tambah Sales Commission Rule' }}</h2>
  </div>
</div>

<div class="page-body">
  <div class="container-xl">
    <form class="card" method="post" action="{{ $row->exists ? route('sales-commission-rules.update', $row) : route('sales-commission-rules.store') }}">
      @csrf
      @if($row->exists) @method('PUT') @endif

      <div class="card-body">
        @if ($errors->any())
          <div class="alert alert-danger">
            <div class="fw-bold mb-1">Periksa kembali input Anda:</div>
            <ul class="mb-0">
              @foreach ($errors->all() as $error) <li>{{ $error }}</li> @endforeach
            </ul>
          </div>
        @endif

        <div class="row g-3">
          <div class="col-md-3">
            <label class="form-label">Scope <span class="text-danger">*</span></label>
            <select name="scope_type" id="scope_type" class="form-select" required>
              <option value="brand" @selected(old('scope_type', $row->scope_type) === 'brand')>Brand</option>
              <option value="family" @selected(old('scope_type', $row->scope_type) === 'family')>Family Code</option>
            </select>
          </div>
          <div class="col-md-5" data-scope-block="brand">
            <label class="form-label">Brand <span class="text-danger">*</span></label>
            <select name="brand_id" class="form-select">
              <option value="">-- Pilih Brand --</option>
              @foreach($brands as $brand)
                <option value="{{ $brand->id }}" @selected((string) old('brand_id', $row->brand_id) === (string) $brand->id)>{{ $brand->name }}</option>
              @endforeach
            </select>
          </div>
          <div class="col-md-5" data-scope-block="family">
            <label class="form-label">Family Code <span class="text-danger">*</span></label>
            <select name="family_code" class="form-select">
              <option value="">-- Pilih Family Code --</option>
              @foreach($familyCodes as $familyCode)
                <option value="{{ $familyCode->code }}" @selected((string) old('family_code', $row->family_code) === (string) $familyCode->code)>{{ $familyCode->code }}</option>
              @endforeach
            </select>
          </div>
          <div class="col-md-2">
            <label class="form-label">Rate % <span class="text-danger">*</span></label>
            <input type="number" step="0.01" min="0" name="rate_percent" class="form-control" value="{{ old('rate_percent', (float) ($row->rate_percent ?? 0)) }}" required>
          </div>
          <div class="col-md-2 d-flex align-items-end">
            <label class="form-check form-switch mb-2">
              <input class="form-check-input" type="checkbox" name="is_active" value="1" @checked(old('is_active', $row->is_active ?? true))>
              <span class="form-check-label">Active</span>
            </label>
          </div>
        </div>
      </div>

      @include('layouts.partials.form_footer', [
        'cancelUrl' => route('sales-commission-rules.index'),
        'cancelLabel' => 'Batal',
        'cancelInline' => true,
        'buttons' => [
          ['type' => 'submit', 'label' => 'Simpan', 'class' => 'btn btn-primary'],
        ],
      ])
    </form>
  </div>
</div>

<script>
  document.addEventListener('DOMContentLoaded', function () {
    const scopeType = document.getElementById('scope_type');
    const brandBlock = document.querySelector('[data-scope-block="brand"]');
    const familyBlock = document.querySelector('[data-scope-block="family"]');

    const syncScope = () => {
      const value = scopeType.value;
      brandBlock.style.display = value === 'brand' ? '' : 'none';
      familyBlock.style.display = value === 'family' ? '' : 'none';
    };

    scopeType.addEventListener('change', syncScope);
    syncScope();
  });
</script>
@endsection
