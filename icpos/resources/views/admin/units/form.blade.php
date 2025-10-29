{{-- resources/views/units/form.blade.php --}}
@extends('layouts.tabler')

@section('content')
<div class="page-header d-print-none">
  <div class="container-xl">
    <h2 class="page-title">{{ $row->exists ? 'Edit Unit' : 'Tambah Unit' }}</h2>
  </div>
</div>

<div class="page-body">
  <div class="container-xl">
    <form class="card" method="post" action="{{ $row->exists ? route('units.update', $row) : route('units.store') }}">
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
          <input type="text" name="code" maxlength="20"
                 class="form-control @error('code') is-invalid @enderror"
                 value="{{ old('code', $row->code) }}" required>
          <small class="form-hint">Contoh: <code>pcs</code>, <code>box</code>, <code>m</code> (boleh huruf kecil; akan dinormalisasi).</small>
          @error('code') <div class="invalid-feedback">{{ $message }}</div> @enderror
        </div>

        <div class="mb-3">
          <label class="form-label">Name <span class="text-danger">*</span></label>
          <input type="text" name="name" maxlength="100"
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

      {{-- Footer pakai partial, Batal + Simpan (inline, rata kanan) --}}
      @include('layouts.partials.form_footer', [
        'cancelUrl'    => route('units.index'),
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
