@extends('layouts.tabler')

@section('content')
<div class="page-header d-print-none">
  <div class="container-xl d-flex align-items-center">
    <h2 class="page-title">Master - Sales Commission Rules</h2>
    <a href="{{ route('sales-commission-rules.create') }}" class="btn btn-primary ms-auto" @if(!($tableReady ?? true)) aria-disabled="true" onclick="return false;" @endif>Tambah</a>
  </div>
</div>

<div class="page-body">
  <div class="container-xl">
    @unless($tableReady ?? true)
      <div class="alert alert-warning">
        Sales Commission Rules belum aktif di server ini. Jalankan migration terbaru terlebih dahulu.
      </div>
    @endunless

    <form class="mb-3" method="get" action="{{ route('sales-commission-rules.index') }}">
      <div class="input-group">
        <input type="text" name="q" class="form-control" placeholder="Cari brand atau family..." value="{{ $q ?? '' }}">
        <button class="btn btn-outline" type="submit">Cari</button>
        @if(!empty($q)) <a href="{{ route('sales-commission-rules.index') }}" class="btn btn-link">Reset</a> @endif
      </div>
    </form>

    <div class="card">
      <div class="table-responsive">
        <table class="table card-table table-vcenter">
          <thead>
            <tr>
              <th class="w-1">#</th>
              <th>Scope</th>
              <th>Target</th>
              <th class="text-end">Rate %</th>
              <th>Status</th>
              <th>Updated</th>
              <th class="w-1"></th>
            </tr>
          </thead>
          <tbody>
            @forelse($rows as $i => $row)
              <tr>
                <td>{{ $rows->firstItem() + $i }}</td>
                <td>{{ strtoupper($row->scope_type) }}</td>
                <td>{{ $row->scope_type === 'brand' ? ($row->brand->name ?? '-') : ($row->family_code ?? '-') }}</td>
                <td class="text-end">{{ number_format((float) $row->rate_percent, 2, ',', '.') }}</td>
                <td>
                  <span class="badge {{ $row->is_active ? 'bg-success-lt text-success' : 'bg-secondary-lt text-secondary' }}">
                    {{ $row->is_active ? 'Active' : 'Inactive' }}
                  </span>
                </td>
                <td>{{ $row->updated_at?->format('d M Y H:i') }}</td>
                <td class="text-nowrap">
                  <a href="{{ route('sales-commission-rules.edit', $row) }}" class="btn btn-sm">Edit</a>
                  <form action="{{ route('sales-commission-rules.destroy', $row) }}" method="post" class="d-inline" onsubmit="return confirm('Hapus rule ini?')">
                    @csrf
                    @method('DELETE')
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
      <div class="card-footer">
        {{ $rows->links() }}
      </div>
    </div>
  </div>
</div>
@endsection
