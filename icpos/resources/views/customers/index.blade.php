{{-- resources/views/customers/index.blade.php --}}
@extends('layouts.tabler')

@section('content')
<div class="container-xl">
  @if(session('ok'))
    <div class="alert alert-success">{{ session('ok') }}</div>
  @endif

  <div class="card">
    <div class="card-body">

      {{-- FILTERS --}}
      <form class="row g-2 mb-3" method="get" action="{{ route('customers.index') }}">
        <div class="col-12 col-md">
          <input type="text"
                 name="q"
                 value="{{ request('q','') }}"
                 class="form-control"
                 placeholder="Cari nama / kota / email">
        </div>

        @isset($jenises)
          <div class="col-12 col-md-auto">
            <select name="jenis_id" class="form-select">
              <option value="">— Semua Jenis —</option>
              @foreach($jenises as $j)
                <option value="{{ $j->id }}" @selected((string)request('jenis_id') === (string)$j->id)>
                  {{ $j->name }}
                </option>
              @endforeach
            </select>
          </div>
        @endisset

        <div class="col-12 col-md-auto d-flex gap-2">
          <button class="btn btn-primary">Search</button>
          <a href="{{ route('customers.index') }}" class="btn btn-outline-secondary">Reset</a>
          <a href="{{ route('customers.create') }}" class="btn btn-success">+ Add Customer</a>
        </div>
      </form>

      {{-- RESULT COUNT --}}
      <div class="mb-2 small text-muted">
        Menampilkan {{ $customers->firstItem() ?? 0 }}–{{ $customers->lastItem() ?? 0 }}
        dari {{ $customers->total() }} pelanggan
      </div>

      {{-- TABLE --}}
      <div class="table-responsive">
        <table class="table table-vcenter">
          <thead>
            <tr>
              <th>Customer</th>
              <th class="d-none d-md-table-cell">City</th>
              <th class="d-none d-md-table-cell">Phone</th>
              <th class="d-none d-md-table-cell">Email</th>
              <th class="text-end"></th>
            </tr>
          </thead>
          <tbody>
            @forelse($customers as $c)
              <tr>
                <td class="text-wrap">
                  <a href="{{ route('customers.show', $c) }}" class="fw-bold text-decoration-none">
                    {{ $c->name }}
                  </a>
                  @if($c->jenis)
                    <div class="small mt-1">
                      <span class="badge bg-light text-dark border">{{ $c->jenis->name }}</span>
                    </div>
                  @endif
                  @if($c->address)
                    <div class="text-muted small mt-1">
                      {{ Str::limit($c->address, 120) }}
                    </div>
                  @endif
                </td>
                <td class="d-none d-md-table-cell">{{ $c->city ?? '-' }}</td>
                <td class="d-none d-md-table-cell">{{ $c->phone ?? '-' }}</td>
                <td class="d-none d-md-table-cell">
                  @if($c->email)
                    <a href="mailto:{{ $c->email }}">{{ $c->email }}</a>
                  @else
                    -
                  @endif
                </td>
                <td class="text-end">
                  @include('layouts.partials.crud_actions', [
                    'view'    => Route::has('customers.show') ? route('customers.show', $c) : null,
                    'edit'    => route('customers.edit', $c),
                    'delete'  => route('customers.destroy', $c),
                    'size'    => 'sm',
                    'confirm' => 'Delete this customer?'
                  ])
                </td>
              </tr>
            @empty
              <tr>
                <td colspan="5" class="text-center text-muted">Belum ada data.</td>
              </tr>
            @endforelse
          </tbody>
        </table>
      </div>

      {{-- PAGINATION --}}
      <div class="mt-3">
        {{ $customers->withQueryString()->links() }}
      </div>

    </div>
  </div>
</div>
@endsection
