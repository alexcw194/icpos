@extends('layouts.tabler')

@section('content')
<div class="page-header d-print-none">
  <div class="container-xl d-flex align-items-center">
    <h2 class="page-title">Master • Family Codes</h2>
    <a href="{{ route('family-codes.create') }}" class="btn btn-primary ms-auto">Tambah</a>
  </div>
</div>

<div class="page-body">
  <div class="container-xl">
    <form class="mb-3" method="get" action="{{ route('family-codes.index') }}">
      <div class="input-group">
        <input type="text" name="q" class="form-control" placeholder="Cari code..." value="{{ $q ?? '' }}">
        <button class="btn btn-outline" type="submit">Cari</button>
        @if(!empty($q)) <a href="{{ route('family-codes.index') }}" class="btn btn-link">Reset</a> @endif
      </div>
    </form>

    <div class="card">
      <div class="table-responsive">
        <table class="table card-table table-vcenter">
          <thead>
            <tr>
              <th class="w-1">#</th>
              <th>Code</th>
              <th>Updated</th>
              <th class="w-1"></th>
            </tr>
          </thead>
          <tbody>
            @forelse($rows as $i => $row)
              <tr>
                <td>{{ $rows->firstItem() + $i }}</td>
                <td>{{ $row->code }}</td>
                <td>{{ $row->updated_at?->format('d M Y H:i') }}</td>
                <td class="text-nowrap">
                  <a href="{{ route('family-codes.edit', $row) }}" class="btn btn-sm">Edit</a>
                  <form action="{{ route('family-codes.destroy', $row) }}" method="post" class="d-inline" onsubmit="return confirm('Hapus Family Code ini?')">
                    @csrf
                    @method('DELETE')
                    <button class="btn btn-sm btn-danger">Hapus</button>
                  </form>
                </td>
              </tr>
            @empty
              <tr><td colspan="4" class="text-center text-muted">Tidak ada data</td></tr>
            @endforelse
          </tbody>
        </table>
      </div>
      <div class="card-footer">
        {{ $rows->links() }}
      </div>
    </div>
  </div>
</div>
@endsection
