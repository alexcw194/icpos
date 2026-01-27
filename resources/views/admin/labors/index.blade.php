@extends('layouts.tabler')

@section('content')
<div class="page-header d-print-none">
  <div class="container-xl d-flex align-items-center">
    <h2 class="page-title">Master Data - Labors</h2>
    <a href="{{ route('labors.create') }}" class="btn btn-primary ms-auto">Tambah</a>
  </div>
</div>

<div class="page-body">
  <div class="container-xl">
    @if(session('ok'))       <div class="alert alert-success">{{ session('ok') }}</div> @endif
    @if(session('success'))  <div class="alert alert-success">{{ session('success') }}</div> @endif
    @if(session('error'))    <div class="alert alert-danger">{{ session('error') }}</div> @endif

    <form class="mb-3" method="get" action="{{ route('labors.index') }}">
      <div class="row g-2">
        <div class="col-md-6">
          <div class="input-group">
            <input type="text" name="q" class="form-control" placeholder="Cari code/nama/unit..." value="{{ $q ?? '' }}">
            <button class="btn btn-outline" type="submit">Cari</button>
            @if(!empty($q) || !empty($status))
              <a href="{{ route('labors.index') }}" class="btn btn-link">Reset</a>
            @endif
          </div>
        </div>
        <div class="col-md-3">
          <select name="status" class="form-select" onchange="this.form.submit()">
            <option value="" @selected(($status ?? '') === '')>Semua Status</option>
            <option value="active" @selected(($status ?? '') === 'active')>Aktif</option>
            <option value="inactive" @selected(($status ?? '') === 'inactive')>Nonaktif</option>
          </select>
        </div>
      </div>
    </form>

    <div class="card">
      <div class="table-responsive">
        <table class="table card-table table-vcenter">
          <thead>
            <tr>
              <th class="w-1">#</th>
              <th>Code</th>
              <th>Name</th>
              <th class="w-1">Unit</th>
              <th>Default Sub-Contractor</th>
              <th class="w-1">Active</th>
              <th>Updated</th>
              <th class="w-1"></th>
            </tr>
          </thead>
          <tbody>
            @forelse($rows as $i => $row)
              <tr>
                <td>{{ $rows->firstItem() + $i }}</td>
                <td><a href="{{ route('labors.edit', $row) }}" class="fw-semibold">{{ $row->code }}</a></td>
                <td>{{ $row->name }}</td>
                <td class="text-muted">{{ $row->unit }}</td>
                <td class="text-muted">{{ $row->defaultSubContractor?->name ?? '-' }}</td>
                <td>
                  @if($row->is_active)
                    <span class="badge bg-green">Active</span>
                  @else
                    <span class="badge bg-secondary">Inactive</span>
                  @endif
                </td>
                <td>{{ $row->updated_at?->format('d M Y H:i') }}</td>
                <td class="text-nowrap">
                  <a href="{{ route('labors.edit', $row) }}" class="btn btn-sm">Edit</a>
                  <form action="{{ route('labors.destroy', $row) }}" method="post" class="d-inline" onsubmit="return confirm('Hapus labor ini?')">
                    @csrf @method('DELETE')
                    <button class="btn btn-sm btn-danger">Hapus</button>
                  </form>
                </td>
              </tr>
            @empty
              <tr><td colspan="8" class="text-center text-muted">Tidak ada data</td></tr>
            @endforelse
          </tbody>
        </table>
      </div>
      <div class="card-footer d-flex align-items-center">
        {{ $rows->links() }}
      </div>
    </div>
  </div>
</div>
@endsection
