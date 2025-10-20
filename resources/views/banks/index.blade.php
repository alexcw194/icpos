@extends('layouts.tabler')

@section('content')
<div class="container-xl">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <h2 class="mb-0">Banks</h2>
    <a href="{{ route('banks.create') }}" class="btn btn-primary">New Bank</a>
  </div>

  <div class="card">
    <div class="table-responsive">
      <table class="table card-table table-vcenter">
        <thead>
          <tr>
            <th>Name</th>
            <th>Account Name</th>
            <th>Account No</th>
            <th>Branch</th>
            <th>Status</th>
            <th class="w-1"></th>
          </tr>
        </thead>
        <tbody>
          @forelse($banks as $bank)
          <tr>
            <td class="fw-semibold">{{ $bank->name }}</td>
            <td>{{ $bank->account_name ?: '-' }}</td>
            <td>{{ $bank->account_no   ?: '-' }}</td>
            <td>{{ $bank->branch       ?: '-' }}</td>
            <td>
              @if($bank->is_active)
                <span class="badge bg-success">Active</span>
              @else
                <span class="badge bg-secondary">Inactive</span>
              @endif
            </td>
            <td class="text-end">
              <a href="{{ route('banks.edit', $bank) }}" class="btn btn-outline-primary btn-sm">Edit</a>
              <form action="{{ route('banks.destroy', $bank) }}" method="POST" class="d-inline"
                    onsubmit="return confirm('Delete this bank?')">
                @csrf @method('DELETE')
                <button class="btn btn-outline-danger btn-sm">Delete</button>
              </form>
            </td>
          </tr>
          @empty
          <tr><td colspan="6" class="text-center text-muted">No data.</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>
    @if(method_exists($banks, 'links'))
      <div class="card-footer">{{ $banks->links() }}</div>
    @endif
  </div>
</div>
@endsection
