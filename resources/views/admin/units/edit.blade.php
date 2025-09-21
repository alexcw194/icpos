{{-- resources/views/units/edit.blade.php --}}
@extends('layouts.tabler')

@section('content')
@php
  $isPCS = strcasecmp($unit->code, 'PCS') === 0;
@endphp

<div class="page-header d-print-none">
  <div class="container-xl d-flex align-items-center">
    <h2 class="page-title">Edit Unit</h2>
    @if($isPCS)
      <span class="badge bg-indigo ms-2">Protected</span>
    @endif
  </div>
</div>

<div class="page-body">
  <div class="container-xl">

    @if(session('ok'))
      <div class="alert alert-success">{{ session('ok') }}</div>
    @endif

    <form action="{{ route('units.update', $unit) }}" method="POST" class="card">
      @csrf
      @method('PUT')

      <div class="card-header">
        <div class="card-title">Data Unit</div>
      </div>

      <div class="card-body">
        <div class="row g-3">
          {{-- Code --}}
          <div class="col-md-3">
            <label class="form-label">Code <span class="text-danger">*</span></label>
            <input type="text"
                   name="code"
                   value="{{ old('code', $unit->code) }}"
                   class="form-control @error('code') is-invalid @enderror"
                   required
                   {{ $isPCS ? 'readonly' : '' }}>
            @error('code') <div class="invalid-feedback">{{ $message }}</div> @enderror
            @if($isPCS)
              <div class="form-hint">Kode <b>PCS</b> dikunci dan tidak bisa diubah.</div>
            @endif
          </div>

          {{-- Name --}}
          <div class="col-md-6">
            <label class="form-label">Name <span class="text-danger">*</span></label>
            <input type="text"
                   name="name"
                   value="{{ old('name', $unit->name) }}"
                   class="form-control @error('name') is-invalid @enderror"
                   required>
            @error('name') <div class="invalid-feedback">{{ $message }}</div> @enderror
          </div>

          {{-- Aktif --}}
          <div class="col-md-3 d-flex align-items-end">
            <label class="form-check form-switch">
              <input class="form-check-input"
                     type="checkbox"
                     name="is_active"
                     value="1"
                     {{ old('is_active', $unit->is_active) ? 'checked' : '' }}
                     {{ $isPCS ? 'disabled' : '' }}>
              <span class="form-check-label">Aktif</span>
            </label>
            @if($isPCS)
              <div class="small text-muted ms-2">PCS selalu aktif.</div>
            @endif
          </div>
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

    {{-- Tombol Hapus (disembunyikan untuk PCS). Diletakkan di bawah kartu. --}}
    @if(!$isPCS)
      <form action="{{ route('units.destroy', $unit) }}"
            method="POST"
            class="mt-2 text-end"
            onsubmit="return confirm('Hapus unit ini?')">
        @csrf @method('DELETE')
        <button class="btn btn-danger">Hapus</button>
      </form>
    @endif
  </div>
</div>
@endsection
