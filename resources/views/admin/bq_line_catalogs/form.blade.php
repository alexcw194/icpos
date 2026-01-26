@extends('layouts.tabler')

@section('content')
<div class="page-header d-print-none">
  <div class="container-xl">
    <h2 class="page-title">{{ $row->exists ? 'Edit' : 'Tambah' }} BQ Line Catalog</h2>
  </div>
</div>

<div class="page-body">
  <div class="container-xl">
    <form class="card" method="post" action="{{ $row->exists ? route('bq-line-catalogs.update', $row) : route('bq-line-catalogs.store') }}">
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
          <div class="col-md-6">
            <label class="form-label">Name <span class="text-danger">*</span></label>
            <input type="text" name="name" maxlength="190"
                   class="form-control @error('name') is-invalid @enderror"
                   value="{{ old('name', $row->name) }}" required>
            @error('name') <div class="invalid-feedback">{{ $message }}</div> @enderror
          </div>
          <div class="col-md-3">
            <label class="form-label">Type <span class="text-danger">*</span></label>
            <select name="type" class="form-select" id="catalogType" required>
              <option value="charge" @selected(old('type', $row->type) === 'charge')>Charge</option>
              <option value="percent" @selected(old('type', $row->type) === 'percent')>Percent</option>
            </select>
          </div>
          <div class="col-md-3">
            <label class="form-label">Cost Bucket</label>
            <select name="cost_bucket" class="form-select">
              <option value="material" @selected(old('cost_bucket', $row->cost_bucket) === 'material')>Material</option>
              <option value="labor" @selected(old('cost_bucket', $row->cost_bucket) === 'labor')>Labor</option>
              <option value="overhead" @selected(old('cost_bucket', $row->cost_bucket) === 'overhead')>Overhead</option>
              <option value="other" @selected(old('cost_bucket', $row->cost_bucket) === 'other')>Other</option>
            </select>
          </div>
        </div>

        <div class="row g-3 mt-1" id="chargeFields">
          <div class="col-md-3">
            <label class="form-label">Default Qty</label>
            <input type="number" step="0.01" name="default_qty" class="form-control" value="{{ old('default_qty', $row->default_qty ?? 1) }}">
          </div>
          <div class="col-md-3">
            <label class="form-label">Default Unit</label>
            <input type="text" name="default_unit" class="form-control" value="{{ old('default_unit', $row->default_unit ?? 'LS') }}">
          </div>
          <div class="col-md-3">
            <label class="form-label">Default Unit Price</label>
            <input type="number" step="0.01" name="default_unit_price" class="form-control" value="{{ old('default_unit_price', $row->default_unit_price ?? '') }}">
          </div>
        </div>

        <div class="row g-3 mt-1" id="percentFields">
          <div class="col-md-3">
            <label class="form-label">Default Percent</label>
            <input type="number" step="0.0001" name="default_percent" class="form-control" value="{{ old('default_percent', $row->default_percent ?? '') }}">
          </div>
          <div class="col-md-4">
            <label class="form-label">Percent Basis</label>
            <select name="percent_basis" class="form-select">
              <option value="product_subtotal" @selected(old('percent_basis', $row->percent_basis) === 'product_subtotal')>Product Subtotal</option>
              <option value="section_product_subtotal" @selected(old('percent_basis', $row->percent_basis) === 'section_product_subtotal')>Section Product Subtotal</option>
            </select>
          </div>
        </div>

        <div class="mb-3 mt-3">
          <label class="form-label">Description</label>
          <textarea name="description" class="form-control" rows="3">{{ old('description', $row->description) }}</textarea>
        </div>

        <div class="mb-3">
          <label class="form-label d-block">Status</label>
          <input type="hidden" name="is_active" value="0">
          <label class="form-check form-switch">
            <input class="form-check-input" type="checkbox" name="is_active" value="1"
                   @checked(old('is_active', $row->exists ? (int)$row->is_active : 1) == 1)>
            <span class="form-check-label">Active</span>
          </label>
        </div>
      </div>

      @include('layouts.partials.form_footer', [
        'cancelUrl'    => route('bq-line-catalogs.index'),
        'cancelLabel'  => 'Batal',
        'cancelInline' => true,
        'buttons'      => [
          ['type' => 'submit', 'label' => 'Simpan', 'class' => 'btn btn-primary'],
        ],
      ])
    </form>
  </div>
</div>
@endsection

@push('scripts')
<script>
(() => {
  const typeSel = document.getElementById('catalogType');
  const chargeFields = document.getElementById('chargeFields');
  const percentFields = document.getElementById('percentFields');

  if (!typeSel) return;

  const sync = () => {
    const type = typeSel.value === 'percent' ? 'percent' : 'charge';
    chargeFields?.classList.toggle('d-none', type !== 'charge');
    percentFields?.classList.toggle('d-none', type !== 'percent');
  };

  typeSel.addEventListener('change', sync);
  sync();
})();
</script>
@endpush
