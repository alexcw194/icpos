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

        @php
            $selectedCompanyIds = collect(old('company_ids', $selectedCompanyIds ?? []))
                ->map(fn ($id) => (int) $id)
                ->unique()
                ->values()
                ->all();
            if (empty($selectedCompanyIds) && !empty(old('company_id'))) {
                $selectedCompanyIds = [(int) old('company_id')];
            }
            if (empty($selectedCompanyIds) && $row->company_id) {
                $selectedCompanyIds = [(int) $row->company_id];
            }
        @endphp

        <div class="mb-3">
            <label class="form-label">Companies *</label>
            <div class="row g-2">
                @foreach($companies as $company)
                    <div class="col-md-4">
                        <label class="form-check">
                            <input class="form-check-input"
                                   type="checkbox"
                                   name="company_ids[]"
                                   value="{{ $company->id }}"
                                   @checked(in_array((int) $company->id, $selectedCompanyIds, true))>
                            <span class="form-check-label">{{ $company->alias ?? $company->name }}</span>
                        </label>
                    </div>
                @endforeach
            </div>
            @error('company_ids') <small class="text-danger">{{ $message }}</small> @enderror
            @error('company_ids.*') <small class="text-danger d-block">{{ $message }}</small> @enderror
            <div class="form-text">Warehouse bisa dipakai oleh beberapa company sekaligus.</div>
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
