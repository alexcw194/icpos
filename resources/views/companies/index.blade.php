@extends('layouts.tabler')

@section('content')
<div class="container-xl">
  <div class="card">
    <div class="card-header d-flex">
      <h3 class="card-title">Companies</h3>
      <a href="{{ route('companies.create') }}" class="btn btn-primary ms-auto">+ Add Company</a>
    </div>

    <div class="table-responsive">
      <table class="table card-table">
        <thead>
          <tr>
            <th>Default</th>
            <th>Alias</th><th>Nama</th><th>Taxable</th><th>Default Tax %</th><th>Logo</th><th></th>
          </tr>
        </thead>
        <tbody>
          @forelse($companies as $co)
            <tr>
              <td style="width: 110px;">
                @if($co->is_default)
                  <span class="badge bg-green">Default</span>
                @else
                  <form method="POST" action="{{ route('companies.make-default', $co) }}">
                    @csrf
                    <button class="btn btn-sm btn-outline-primary" type="submit">Set Default</button>
                  </form>
                @endif
              </td>
              <td>{{ $co->alias ?? '—' }}</td>
              <td>{{ $co->name }}</td>
              <td>{!! $co->is_taxable ? '<span class="badge bg-green">Yes</span>' : '<span class="badge bg-gray">No</span>' !!}</td>
              <td>{{ number_format($co->default_tax_percent ?? 0, 2, ',', '.') }}</td>
              <td>
                @if($co->logo_path)
                  <img src="{{ asset('storage/'.$co->logo_path) }}" style="height:28px" alt="logo">
                @else
                  <span class="text-muted">—</span>
                @endif
              </td>
              <td class="text-end">
                <a href="{{ route('companies.edit',$co) }}" class="btn btn-sm btn-primary">Edit</a>
              </td>
            </tr>
          @empty
            <tr><td colspan="7" class="text-center text-muted">Belum ada data.</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>

    <div class="card-footer">{{ $companies->links() }}</div>
  </div>
</div>
@endsection
