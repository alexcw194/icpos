@extends('layouts.tabler')

@section('content')
<div class="page-header d-print-none">
  <div class="container-xl d-flex align-items-center">
    <h2 class="page-title">Master • Units</h2>
    <a href="{{ route('units.create') }}" class="btn btn-primary ms-auto">Tambah</a>
  </div>
</div>

<div class="page-body">
  <div class="container-xl">

    {{-- Flash messages (kompatibel lama & baru) --}}
    @if(session('ok'))       <div class="alert alert-success">{{ session('ok') }}</div> @endif
    @if(session('success'))  <div class="alert alert-success">{{ session('success') }}</div> @endif
    @if(session('error'))    <div class="alert alert-danger">{{ session('error') }}</div> @endif

    {{-- Pencarian --}}
    <form class="mb-3" method="get" action="{{ route('units.index') }}">
      <div class="input-group">
        <input type="text" name="q" class="form-control" placeholder="Cari code atau name…" value="{{ $q ?? '' }}">
        <button class="btn btn-outline" type="submit">Cari</button>
        @if(!empty($q)) <a href="{{ route('units.index') }}" class="btn btn-link">Reset</a> @endif
      </div>
    </form>

    <div class="card">
      <div class="table-responsive">
        <table class="table card-table table-vcenter">
          <thead>
            <tr>
              <th class="w-1">#</th>
              <th class="w-1">Code</th>
              <th>Name</th>
              <th class="w-1">Aktif</th>
              <th>Updated</th>
              <th class="w-1"></th>
            </tr>
          </thead>
          <tbody>
            @forelse($rows as $i => $row)
              @php
                $isPCS = strcasecmp($row->code, 'PCS') === 0;
                $used  = isset($row->items_count) ? ($row->items_count > 0) : false; // kalau controller pakai withCount
              @endphp
              <tr>
                <td>{{ $rows->firstItem() + $i }}</td>
                <td class="text-muted">
                  {{ $row->code }}
                  @if($isPCS)
                    <span class="badge bg-indigo ms-1" title="Unit ini dilindungi">Protected</span>
                  @endif
                </td>
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
                  <a href="{{ route('units.edit', $row) }}" class="btn btn-sm">Edit</a>

                  {{-- Hapus: sembunyikan untuk PCS; kalau ada items_count>0 tampilkan tombol disabled --}}
                  @if($isPCS)
                    <button class="btn btn-sm btn-danger" disabled title="Unit PCS tidak boleh dihapus">Hapus</button>
                  @elseif($used)
                    <button class="btn btn-sm btn-danger" disabled title="Unit dipakai item. Nonaktifkan saja.">Hapus</button>
                  @else
                    <form action="{{ route('units.destroy', $row) }}" method="post" class="d-inline" onsubmit="return confirm('Hapus Unit ini?')">
                      @csrf @method('DELETE')
                      <button class="btn btn-sm btn-danger">Hapus</button>
                    </form>
                  @endif
                </td>
              </tr>
            @empty
              <tr><td colspan="6" class="text-center text-muted">Tidak ada data</td></tr>
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
