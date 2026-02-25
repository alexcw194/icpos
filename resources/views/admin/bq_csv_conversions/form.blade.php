@extends('layouts.tabler')

@section('content')
<div class="page-header d-print-none">
  <div class="container-xl">
    <h2 class="page-title">{{ $row->exists ? 'Edit' : 'Tambah' }} BQ CSV Conversion</h2>
  </div>
</div>

<div class="page-body">
  <div class="container-xl">
    <form class="card" method="post" action="{{ $row->exists ? route('bq-csv-conversions.update', $row) : route('bq-csv-conversions.store') }}">
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
          <div class="col-md-4">
            <label class="form-label">Source Category <span class="text-danger">*</span></label>
            <input type="text" name="source_category" maxlength="190"
                   class="form-control @error('source_category') is-invalid @enderror"
                   value="{{ old('source_category', $row->source_category) }}" required>
            @error('source_category') <div class="invalid-feedback">{{ $message }}</div> @enderror
          </div>
          <div class="col-md-4">
            <label class="form-label">Source Item <span class="text-danger">*</span></label>
            <input type="text" name="source_item" maxlength="255"
                   class="form-control @error('source_item') is-invalid @enderror"
                   value="{{ old('source_item', $row->source_item) }}" required>
            @error('source_item') <div class="invalid-feedback">{{ $message }}</div> @enderror
          </div>
          <div class="col-md-4">
            <label class="form-label">Mapped Item <span class="text-danger">*</span></label>
            <input type="text" name="mapped_item" maxlength="255"
                   class="form-control @error('mapped_item') is-invalid @enderror"
                   value="{{ old('mapped_item', $row->mapped_item) }}" required>
            @error('mapped_item') <div class="invalid-feedback">{{ $message }}</div> @enderror
          </div>
        </div>

        <div class="mt-3">
          <label class="form-label d-block">Status</label>
          <input type="hidden" name="is_active" value="0">
          <label class="form-check form-switch">
            <input class="form-check-input" type="checkbox" name="is_active" value="1"
                   @checked(old('is_active', $row->exists ? (int) $row->is_active : 1) == 1)>
            <span class="form-check-label">Active</span>
          </label>
        </div>
      </div>

      @include('layouts.partials.form_footer', [
        'cancelUrl'    => route('bq-csv-conversions.index'),
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
