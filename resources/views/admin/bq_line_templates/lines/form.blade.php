@extends('layouts.tabler')

@section('content')
<div class="page-header d-print-none">
  <div class="container-xl d-flex align-items-center">
    <div>
      <div class="page-pretitle">BQ Line Template</div>
      <h2 class="page-title">{{ $template->name }}</h2>
    </div>
    <div class="ms-auto">
      <a href="{{ route('bq-line-templates.lines.index', $template) }}" class="btn">Back</a>
    </div>
  </div>
</div>

<div class="page-body">
  <div class="container-xl">
    <form class="card" method="post" action="{{ $line->exists ? route('bq-line-templates.lines.update', [$template, $line]) : route('bq-line-templates.lines.store', $template) }}">
      @csrf
      @if($line->exists) @method('PUT') @endif

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
            <label class="form-label">Sort Order</label>
            <input type="number" name="sort_order" class="form-control" value="{{ old('sort_order', $line->sort_order ?? 0) }}" min="0">
          </div>
          <div class="col-md-3">
            <label class="form-label">Type <span class="text-danger">*</span></label>
            <select name="type" class="form-select" id="lineType" required>
              <option value="charge" @selected(old('type', $line->type) === 'charge')>Charge</option>
              <option value="percent" @selected(old('type', $line->type) === 'percent')>Percent</option>
            </select>
          </div>
          <div class="col-md-6">
            <label class="form-label">Label <span class="text-danger">*</span></label>
            <input type="text" name="label" class="form-control" value="{{ old('label', $line->label) }}" required>
          </div>
        </div>

        <div class="row g-3 mt-1" id="chargeFields">
          <div class="col-md-3">
            <label class="form-label">Default Qty</label>
            <input type="number" step="0.01" name="default_qty" class="form-control" value="{{ old('default_qty', $line->default_qty ?? 1) }}">
          </div>
          <div class="col-md-3">
            <label class="form-label">Default Unit</label>
            <input type="text" name="default_unit" class="form-control" value="{{ old('default_unit', $line->default_unit ?? 'LS') }}">
          </div>
          <div class="col-md-3">
            <label class="form-label">Default Unit Price</label>
            <input type="number" step="0.01" name="default_unit_price" class="form-control" value="{{ old('default_unit_price', $line->default_unit_price ?? '') }}">
          </div>
          <div class="col-md-3">
            <label class="form-label d-block">Editable Price</label>
            <input type="hidden" name="editable_price" value="0">
            <label class="form-check form-switch">
              <input class="form-check-input" type="checkbox" name="editable_price" value="1" @checked(old('editable_price', $line->editable_price ?? true))>
              <span class="form-check-label">Yes</span>
            </label>
          </div>
        </div>

        <div class="row g-3 mt-1" id="percentFields">
          <div class="col-md-3">
            <label class="form-label">Percent Value</label>
            <input type="number" step="0.0001" name="percent_value" class="form-control" value="{{ old('percent_value', $line->percent_value ?? '') }}">
          </div>
          <div class="col-md-4">
            <label class="form-label">Basis Type</label>
            <select name="basis_type" class="form-select">
              <option value="bq_product_total" @selected(old('basis_type', $line->basis_type) === 'bq_product_total')>BQ Product Total</option>
              <option value="section_product_total" @selected(old('basis_type', $line->basis_type) === 'section_product_total')>Section Product Total</option>
            </select>
          </div>
          <div class="col-md-3">
            <label class="form-label d-block">Editable Percent</label>
            <input type="hidden" name="editable_percent" value="0">
            <label class="form-check form-switch">
              <input class="form-check-input" type="checkbox" name="editable_percent" value="1" @checked(old('editable_percent', $line->editable_percent ?? true))>
              <span class="form-check-label">Yes</span>
            </label>
          </div>
        </div>

        <div class="row g-3 mt-1">
          <div class="col-md-4">
            <label class="form-label">Applies To</label>
            <select name="applies_to" class="form-select">
              <option value="both" @selected(old('applies_to', $line->applies_to) === 'both')>Material + Labor</option>
              <option value="material" @selected(old('applies_to', $line->applies_to) === 'material')>Material only</option>
              <option value="labor" @selected(old('applies_to', $line->applies_to) === 'labor')>Labor only</option>
            </select>
          </div>
          <div class="col-md-4">
            <label class="form-label d-block">Can Remove</label>
            <input type="hidden" name="can_remove" value="0">
            <label class="form-check form-switch">
              <input class="form-check-input" type="checkbox" name="can_remove" value="1" @checked(old('can_remove', $line->can_remove ?? true))>
              <span class="form-check-label">Yes</span>
            </label>
          </div>
        </div>
      </div>

      @include('layouts.partials.form_footer', [
        'cancelUrl'    => route('bq-line-templates.lines.index', $template),
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
  const typeSel = document.getElementById('lineType');
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
