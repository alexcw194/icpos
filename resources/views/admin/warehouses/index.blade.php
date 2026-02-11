{{-- resources/views/admin/warehouses/index.blade.php --}}
@extends('layouts.tabler')

@section('content')
<div class="container-xl">
    <h2 class="page-title">Master • Warehouses</h2>

    <a class="btn btn-primary mb-3" href="{{ route('warehouses.create') }}">Tambah</a>

    {{-- Flash messages --}}
    @if(session('ok'))      <div class="alert alert-success">{{ session('ok') }}</div> @endif
    @if(session('success')) <div class="alert alert-success">{{ session('success') }}</div> @endif
    @if(session('error'))   <div class="alert alert-danger">{{ session('error') }}</div> @endif

    {{-- Form pencarian dan filter --}}
    <form method="GET" action="{{ route('warehouses.index') }}" class="row g-2 mb-3">
        <div class="col-auto">
            <input type="text" name="q" value="{{ $q }}" class="form-control" placeholder="Cari">
        </div>
        <div class="col-auto">
            <select name="status" class="form-select">
                <option value="">Semua</option>
                <option value="active"   {{ $status === 'active'   ? 'selected' : '' }}>Aktif</option>
                <option value="inactive" {{ $status === 'inactive' ? 'selected' : '' }}>Tidak Aktif</option>
            </select>
        </div>
        <div class="col-auto">
            <button type="submit" class="btn btn-secondary">Cari</button>
            @if(!empty($q) || !empty($status))
                <a href="{{ route('warehouses.index') }}" class="btn btn-link">Reset</a>
            @endif
        </div>
    </form>

    <div class="table-responsive">
        <table class="table table-striped">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Kode</th>
                    <th>Nama</th>
                    <th>Companies</th>
                    <th>Alamat</th>
                    <th>Allow -</th>
                    <th>Aktif</th>
                    <th>Updated</th>
                    <th>Aksi</th>
                </tr>
            </thead>
            <tbody>
                @forelse($rows as $i => $row)
                <tr>
                    <td>{{ $rows->firstItem() + $i }}</td>
                    <td>{{ $row->code }}</td>
                    <td>{{ $row->name }}</td>
                    <td>
                        @php
                            $companyNames = collect();
                            if (!empty($supportsCompanyWarehouse)) {
                                $companyNames = $row->companies->map(fn($c) => $c->alias ?? $c->name)->filter()->values();
                            }
                            if ($companyNames->isEmpty() && $row->company) {
                                $companyNames = collect([$row->company->alias ?? $row->company->name])->filter()->values();
                            }
                        @endphp
                        @if($companyNames->isNotEmpty())
                            {{ $companyNames->implode(', ') }}
                        @else
                            -
                        @endif
                    </td>
                    <td>{{ $row->address }}</td>
                    <td>{{ $row->allow_negative_stock ? 'Ya' : 'Tidak' }}</td>
                    <td>{{ $row->is_active ? 'Ya' : 'Tidak' }}</td>
                    <td>{{ $row->updated_at?->format('d M Y H:i') }}</td>
                    <td>
                        <a href="{{ route('warehouses.edit', $row) }}" class="btn btn-sm btn-warning">Edit</a>
                        <form action="{{ route('warehouses.destroy', $row) }}" method="POST" class="d-inline" onsubmit="return confirm('Hapus warehouse?')">
                            @csrf @method('DELETE')
                            <button class="btn btn-sm btn-danger">Hapus</button>
                        </form>
                    </td>
                </tr>
                @empty
                <tr><td colspan="9">Tidak ada data</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{ $rows->links() }}
</div>
@endsection
