@extends('layouts.tabler')

@section('content')
<div class="page-header d-print-none">
  <div class="container-xl d-flex align-items-center">
    <h2 class="page-title">Master Data - BQ Line Catalog</h2>
    <a href="{{ route('bq-line-catalogs.create') }}" class="btn btn-primary ms-auto">Tambah</a>
  </div>
</div>

<div class="page-body">
  <div class="container-xl">
    @if(session('ok'))       <div class="alert alert-success">{{ session('ok') }}</div> @endif
    @if(session('success'))  <div class="alert alert-success">{{ session('success') }}</div> @endif
    @if(session('error'))    <div class="alert alert-danger">{{ session('error') }}</div> @endif

    <form class="mb-3" method="get" action="{{ route('bq-line-catalogs.index') }}">
      <div class="row g-2">
        <div class="col-md-6">
          <div class="input-group">
            <input type="text" name="q" class="form-control" placeholder="Cari nama/desc..." value="{{ $q ?? '' }}">
            <button class="btn btn-outline" type="submit">Cari</button>
            @if(!empty($q) || !empty($status))
              <a href="{{ route('bq-line-catalogs.index') }}" class="btn btn-link">Reset</a>
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
              <th>Name</th>
              <th class="w-1">Type</th>
              <th class="w-1">Active</th>
              <th>Defaults</th>
              <th>Updated</th>
              <th class="w-1"></th>
            </tr>
          </thead>
          <tbody>
            @forelse($rows as $i => $row)
              <tr>
                <td>{{ $rows->firstItem() + $i }}</td>
                <td>
                  <a href="{{ route('bq-line-catalogs.edit', $row) }}" class="fw-semibold">
                    {{ $row->name }}
                  </a>
                  @if($row->description)
                    <div class="text-muted small">{{ $row->description }}</div>
                  @endif
                </td>
                <td class="text-muted">{{ ucfirst($row->type) }}</td>
                <td>
                  @if($row->is_active)
                    <span class="badge bg-green">Active</span>
                  @else
                    <span class="badge bg-secondary">Inactive</span>
                  @endif
                </td>
                <td>
                  @if($row->type === 'charge')
                    Qty {{ number_format((float)($row->default_qty ?? 0), 2, ',', '.') }}
                    {{ $row->default_unit ?? 'LS' }}
                    @if($row->default_unit_price !== null)
                      &middot; Harga {{ number_format((float)$row->default_unit_price, 2, ',', '.') }}
                    @endif
                  @else
                    {{ number_format((float)($row->default_percent ?? 0), 4, ',', '.') }}%
                    <span class="text-muted">({{ $row->percent_basis }})</span>
                  @endif
                  <div class="text-muted small">Bucket: {{ $row->cost_bucket }}</div>
                </td>
                <td>{{ $row->updated_at?->format('d M Y H:i') }}</td>
                <td class="text-nowrap">
                  <a href="{{ route('bq-line-catalogs.edit', $row) }}" class="btn btn-sm">Edit</a>
                  <form action="{{ route('bq-line-catalogs.destroy', $row) }}" method="post" class="d-inline" onsubmit="return confirm('Hapus catalog ini?')">
                    @csrf @method('DELETE')
                    <button class="btn btn-sm btn-danger">Hapus</button>
                  </form>
                </td>
              </tr>
            @empty
              <tr><td colspan="7" class="text-center text-muted">Tidak ada data</td></tr>
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
