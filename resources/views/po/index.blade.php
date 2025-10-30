@extends('layouts.tabler')

@section('content')
<div class="container-xl">
  <div class="card">
    <div class="card-header d-flex">
      <h3 class="card-title">Purchase Orders</h3>
      <a href="{{ route('po.create') }}" class="btn btn-primary ms-auto">+ New PO</a>
    </div>

    <div class="table-responsive">
      <table class="table card-table">
        <thead>
          <tr>
            <th>No</th>
            <th>Supplier</th>
            <th>Company</th>
            <th>Warehouse</th>
            <th>Status</th>
            <th class="text-end">Actions</th>
          </tr>
        </thead>
        <tbody>
          @forelse($pos as $po)
          <tr>
            <td>{{ $po->number }}</td>
            <td>{{ $po->supplier_name ?? '—' }}</td>
            <td>{{ $po->company->alias ?? $po->company->name ?? '—' }}</td>
            <td>{{ $po->warehouse->name ?? '—' }}</td>
            <td><span class="badge bg-{{ $po->status === 'approved' ? 'blue' : ($po->status === 'closed' ? 'green' : 'yellow') }}">
              {{ ucfirst($po->status) }}</span></td>
            <td class="text-end">
              <a href="{{ route('po.show', $po) }}" class="btn btn-sm btn-primary">View</a>
              @if($po->status === 'draft')
              <form action="{{ route('po.approve', $po) }}" method="POST" class="d-inline">
                @csrf
                <button class="btn btn-sm btn-outline-success" type="submit">Approve</button>
              </form>
              @endif
              @if(in_array($po->status, ['approved','partial']))
              <a href="{{ route('po.receive', $po) }}" class="btn btn-sm btn-outline-primary">Receive</a>
              @endif
            </td>
          </tr>
          @empty
          <tr><td colspan="6" class="text-center text-muted">No data.</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>

    <div class="card-footer">{{ $pos->links() }}</div>
  </div>
</div>
@endsection
