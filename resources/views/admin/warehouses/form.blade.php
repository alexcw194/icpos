{{-- resources/views/admin/warehouses/form.blade.php --}}
@extends('layouts.tabler')

@section('content')
<div class="container-xl">
    <h2 class="page-title">{{ $row->exists ? 'Edit Warehouse' : 'Tambah Warehouse' }}</h2>

    <form method="POST" action="{{ $row->exists ? route('warehouses.update', $row) : route('warehouses.store') }}">
        @csrf
        @if($row->exists)
            @method('PUT')
        @endif

        @if ($errors->any())
            <div class="alert alert-danger">
                Periksa kembali input Anda:
                <ul class="mb-0">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <div class="mb-3">
            <label for="company_id" class="form-label">Company *</label>
            <select id="company_id" name="company_id" class="form-select" required>
                @foreach($companies as $company)
                    <option value="{{ $company->id }}"
                        @selected(old('company_id', $row->company_id) == $company->id)>
                        {{ $company->alias ?? $company->name }}
                    </option>
                @endforeach
            </select>
            @error('company_id') <small class="text-danger">{{ $message }}</small> @enderror
        </div>

        <div class="mb-3">
            <label for="code" class="form-label">Kode *</label>
            <input type="text" id="code" name="code" value="{{ old('code', $row->code) }}" class="form-control" required>
            @error('code') <small class="text-danger">{{ $message }}</small> @enderror
        </div>

        <div class="mb-3">
            <label for="name" class="form-label">Nama *</label>
            <input type="text" id="name" name="name" value="{{ old('name', $row->name) }}" class="form-control" required>
            @error('name') <small class="text-danger">{{ $message }}</small> @enderror
        </div>

        <div class="mb-3">
            <label for="address" class="form-label">Alamat</label>
            <textarea id="address" name="address" class="form-control">{{ old('address', $row->address) }}</textarea>
            @error('address') <small class="text-danger">{{ $message }}</small> @enderror
        </div>

        <div class="form-check form-switch mb-3">
            <input class="form-check-input" type="checkbox" name="allow_negative_stock" id="allow_negative_stock"
                value="1" {{ old('allow_negative_stock', $row->exists ? (int)$row->allow_negative_stock : 0) ? 'checked' : '' }}>
            <label class="form-check-label" for="allow_negative_stock">Allow Negative Stock</label>
        </div>

        <div class="form-check form-switch mb-3">
            <input class="form-check-input" type="checkbox" name="is_active" id="is_active"
                value="1" {{ old('is_active', $row->exists ? (int)$row->is_active : 1) ? 'checked' : '' }}>
            <label class="form-check-label" for="is_active">Aktif</label>
            <div class="form-text">Uncheck untuk menonaktifkan.</div>
        </div>

        @include('layouts.partials.form_footer', [
            'cancelUrl'    => route('warehouses.index'),
            'cancelLabel'  => 'Batal',
            'cancelInline' => true,
            'buttons'      => [
                ['type' => 'submit', 'label' => 'Simpan', 'class' => 'btn btn-primary'],
            ],
        ])
    </form>
</div>
@endsection
