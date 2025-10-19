@extends('layouts.tabler')

@section('content')
<div class="container-xl">
  <div class="page-header">
    <h2 class="page-title">Invoices – TT Pending</h2>
  </div>

  <div class="card">
    <div class="table-responsive">
      <table class="table table-hover align-middle">
        <thead>
          <tr>
            <th>No</th>
            <th>Date</th>
            <th>Customer</th>
            <th>Due Date</th>
            <th>Total</th>
            <th>Posted At</th>
            <th class="text-end">Actions</th>
          </tr>
        </thead>
        <tbody>
          @forelse($invoices as $inv)
            <tr>
              <td>{{ $inv->number ?? 'DRAFT-'.$inv->id }}</td>
              <td>{{ optional($inv->date)->format('Y-m-d') }}</td>
              <td>{{ $inv->customer->name ?? '-' }}</td>
              <td>{{ optional($inv->due_date)->format('Y-m-d') ?: '-' }}</td>
              <td>{{ number_format((float)$inv->total, 2, ',', '.') }}</td>
              <td>{{ optional($inv->posted_at)->format('Y-m-d H:i') }}</td>
              <td class="text-end">
                <a href="{{ route('invoices.show', $inv) }}" class="btn btn-outline-primary btn-sm">Open</a>
              </td>
            </tr>
          @empty
            <tr>
              <td colspan="7" class="text-center text-muted py-4">Semua posted invoice sudah memiliki TT.</td>
            </tr>
          @endforelse
        </tbody>
      </table>
    </div>
    <div class="card-footer">
      {{ $invoices->links() }}
    </div>
  </div>
</div>
@endsection
