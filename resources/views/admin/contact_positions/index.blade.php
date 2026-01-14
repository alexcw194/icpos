@extends('layouts.tabler')

@section('content')
<div class="page-header d-print-none">
  <div class="container-xl d-flex align-items-center">
    <h2 class="page-title">Contact Positions</h2>
    <a href="{{ route('contact-positions.create') }}" class="btn btn-primary ms-auto">Tambah</a>
  </div>
</div>

<div class="page-body">
  <div class="container-xl">
    @if(session('success')) <div class="alert alert-success">{{ session('success') }}</div> @endif
    @if(session('error')) <div class="alert alert-danger">{{ session('error') }}</div> @endif

    <div class="card">
      <div class="table-responsive">
        <table class="table table-sm table-vcenter card-table">
          <thead>
            <tr>
              <th class="w-1">#</th>
              <th>Name</th>
              <th class="w-1">Active</th>
              <th class="w-1">Order</th>
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
                    <span class="badge bg-green">Yes</span>
                  @else
                    <span class="badge bg-secondary">No</span>
                  @endif
                </td>
                <td>{{ $row->sort_order }}</td>
                <td class="text-nowrap">
                  <a href="{{ route('contact-positions.edit', $row) }}" class="btn btn-sm">Edit</a>
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
