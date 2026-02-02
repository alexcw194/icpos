@extends('layouts.tabler')

@section('content')
<div class="container-xl">
  <div class="page-header d-print-none mb-3">
    <div class="row align-items-center">
      <div class="col">
        <h2 class="page-title">Suppliers</h2>
      </div>
      <div class="col-auto ms-auto">
        <a href="{{ route('suppliers.create') }}" class="btn btn-primary">+ Supplier</a>
      </div>
    </div>
  </div>

  <div class="card">
    <div class="card-body border-bottom">
      <form method="GET" class="row g-2">
        <div class="col-md-4">
          <input type="text" name="q" value="{{ $q ?? '' }}" class="form-control" placeholder="Cari nama / telp / email">
        </div>
        <div class="col-md-auto">
          <button class="btn btn-outline-primary">Cari</button>
        </div>
      </form>
    </div>
    <div class="table-responsive">
      <table class="table card-table table-vcenter">
        <thead>
          <tr>
            <th>Nama</th>
            <th>Telp</th>
            <th>Email</th>
            <th>Status</th>
            <th class="text-end">Aksi</th>
          </tr>
        </thead>
        <tbody>
          @forelse($rows as $row)
            <tr>
              <td>{{ $row->name }}</td>
              <td>{{ $row->phone ?? '—' }}</td>
              <td>{{ $row->email ?? '—' }}</td>
              <td>
                <span class="badge bg-{{ $row->is_active ? 'green' : 'secondary' }}">
                  {{ $row->is_active ? 'Active' : 'Inactive' }}
                </span>
              </td>
              <td class="text-end">
                <a href="{{ route('suppliers.edit', $row) }}" class="btn btn-sm btn-outline-primary">Edit</a>
                <form method="POST" action="{{ route('suppliers.destroy', $row) }}" class="d-inline"
                      onsubmit="return confirm('Hapus supplier ini?')">
                  @csrf
                  @method('DELETE')
                  <button class="btn btn-sm btn-outline-danger" type="submit">Hapus</button>
                </form>
              </td>
            </tr>
          @empty
            <tr><td colspan="5" class="text-center text-muted">No data.</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>
    <div class="card-footer">{{ $rows->links() }}</div>
  </div>
</div>
@endsection
