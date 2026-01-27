@extends('layouts.tabler')

@section('content')
<div class="page-header d-print-none">
  <div class="container-xl">
    <h2 class="page-title">{{ $row->exists ? 'Edit Sub-Contractor' : 'Tambah Sub-Contractor' }}</h2>
  </div>
</div>

<div class="page-body">
  <div class="container-xl">
    <form class="card" method="post" action="{{ $row->exists ? route('sub-contractors.update', $row) : route('sub-contractors.store') }}">
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

        <div class="mb-3">
          <label class="form-label">Name <span class="text-danger">*</span></label>
          <input type="text" name="name" maxlength="190"
                 class="form-control @error('name') is-invalid @enderror"
                 value="{{ old('name', $row->name) }}" required>
          @error('name') <div class="invalid-feedback">{{ $message }}</div> @enderror
        </div>

        <div class="mb-3">
          <label class="form-label d-block">Status</label>
          <input type="hidden" name="is_active" value="0">
          <label class="form-check form-switch">
            <input class="form-check-input" type="checkbox" name="is_active" value="1"
                   @checked(old('is_active', $row->exists ? (int)$row->is_active : 1) == 1)>
            <span class="form-check-label">Active</span>
          </label>
          <small class="form-hint">Uncheck untuk menonaktifkan.</small>
        </div>
      </div>

      @include('layouts.partials.form_footer', [
        'cancelUrl'    => route('sub-contractors.index'),
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
