@extends('layouts.tabler')

@section('content')
<div class="page-header d-print-none">
  <div class="container-xl d-flex align-items-center">
    <h2 class="page-title">Master • Jenis</h2>
    <a href="{{ route('jenis.create') }}" class="btn btn-primary ms-auto">Tambah</a>
  </div>
</div>

<div class="page-body">
  <div class="container-xl">

    @if(session('success')) <div class="alert alert-success">{{ session('success') }}</div> @endif
    @if(session('error'))   <div class="alert alert-danger">{{ session('error') }}</div> @endif

    <form class="mb-3" method="get" action="{{ route('jenis.index') }}">
      <div class="input-group">
        <input type="text" name="q" class="form-control" placeholder="Cari nama…" value="{{ $q ?? '' }}">
        <button class="btn btn-outline" type="submit">Cari</button>
        @if(!empty($q)) <a href="{{ route('jenis.index') }}" class="btn btn-link">Reset</a> @endif
      </div>
    </form>

    <div class="card">
      <div class="table-responsive">
        <table class="table card-table table-vcenter">
          <thead>
            <tr>
              <th class="w-1">#</th>
              <th>Nama</th>
              <th class="w-1">Aktif</th>
              <th>Updated</th>
              <th class="w-1"></th>
            </tr>
          </thead>
          <tbody>
            @forelse($rows as $i => $row)
              <tr>
                <td>{{ $rows->firstItem() + $i }}</td>
                <td>{{ $row->name }}</td>
                <td>
                  @if($row->is_active)
                    <span class="badge bg-green">Ya</span>
                  @else
                    <span class="badge bg-secondary">Tidak</span>
                  @endif
                </td>
                <td>{{ $row->updated_at?->format('d M Y H:i') }}</td>
                <td class="text-nowrap">
                  <a href="{{ route('jenis.edit', $row) }}" class="btn btn-sm">Edit</a>
                  <form action="{{ route('jenis.destroy', $row) }}" method="post" class="d-inline" onsubmit="return confirm('Hapus Jenis ini?')">
                    @csrf @method('DELETE')
                    <button class="btn btn-sm btn-danger">Hapus</button>
                  </form>
                </td>
              </tr>
            @empty
              <tr><td colspan="5" class="text-center text-muted">Tidak ada data</td></tr>
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
