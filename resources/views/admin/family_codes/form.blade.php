@extends('layouts.tabler')

@section('content')
<div class="page-header d-print-none">
  <div class="container-xl">
    <h2 class="page-title">{{ $row->exists ? 'Edit Family Code' : 'Tambah Family Code' }}</h2>
  </div>
</div>

<div class="page-body">
  <div class="container-xl">
    <form class="card" method="post" action="{{ $row->exists ? route('family-codes.update', $row) : route('family-codes.store') }}">
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
          <label class="form-label">Code <span class="text-danger">*</span></label>
          <input type="text"
                 name="code"
                 maxlength="50"
                 class="form-control @error('code') is-invalid @enderror"
                 value="{{ old('code', $row->code) }}"
                 required>
          @error('code') <div class="invalid-feedback">{{ $message }}</div> @enderror
        </div>
      </div>

      @include('layouts.partials.form_footer', [
        'cancelUrl' => route('family-codes.index'),
        'cancelLabel' => 'Batal',
        'cancelInline' => true,
        'buttons' => [
          ['type' => 'submit', 'label' => 'Simpan', 'class' => 'btn btn-primary'],
        ],
      ])
    </form>
  </div>
</div>
@endsection

