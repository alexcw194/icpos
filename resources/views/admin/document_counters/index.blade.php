@extends('layouts.tabler')

@section('content')
@php
  $typeLabels = [
    'quotation' => 'Quotation',
    'invoice'   => 'Invoice',
    'delivery'  => 'Delivery',
    'document'  => 'Document',
  ];
@endphp

<div class="container-xl">
  <div class="d-flex align-items-center mb-3">
    <h2 class="page-title m-0">Document Counters</h2>
  </div>

  @if(session('success'))
    <div class="alert alert-success alert-dismissible mb-3" role="alert">
      <div class="d-flex">
        <div>{{ session('success') }}</div>
        <a class="ms-auto btn-close" data-bs-dismiss="alert" aria-label="Close"></a>
      </div>
    </div>
  @endif

  <div class="card mb-3">
    <div class="card-body">
      <form class="row g-2" method="get">
        <div class="col-12 col-md-4">
          <select name="company_id" class="form-select">
            <option value="">All companies</option>
            @foreach($companies as $co)
              <option value="{{ $co->id }}" @selected((string) $companyId === (string) $co->id)>
                {{ $co->alias ?? $co->name }}
              </option>
            @endforeach
          </select>
        </div>
        <div class="col-6 col-md-2">
          <input type="number" name="year" class="form-control" placeholder="Year"
                 value="{{ $year }}" min="2000" max="{{ now()->year + 1 }}">
        </div>
        <div class="col-6 col-md-2">
          <button type="submit" class="btn btn-outline w-100">Filter</button>
        </div>
        <div class="col-12 col-md-2">
          <a href="{{ route('document-counters.index') }}" class="btn btn-link w-100">Reset</a>
        </div>
      </form>
    </div>
  </div>

  <div class="card">
    <div class="card-body">
      <div class="text-muted small mb-3">
        Counter menentukan nomor berikutnya. Ubah <strong>Last Seq</strong> untuk
        mengatur nomor selanjutnya (next = last + 1).
      </div>

      <div class="table-responsive">
        <table class="table table-sm table-vcenter">
          <thead>
            <tr>
              <th>Company</th>
              <th>Doc Type</th>
              <th class="text-center">Year</th>
              <th class="text-end">Last Seq</th>
              <th class="text-end">Next Seq</th>
              <th class="text-end">Actions</th>
            </tr>
          </thead>
          <tbody>
            @forelse($counters as $row)
              <tr>
                <td>{{ $row->company?->alias ?? $row->company?->name ?? '-' }}</td>
                <td>{{ $typeLabels[$row->doc_type] ?? $row->doc_type }}</td>
                <td class="text-center">{{ $row->year }}</td>
                <td class="text-end">
                  <input type="number"
                         name="last_seq"
                         form="counter-{{ $row->id }}"
                         class="form-control form-control-sm text-end"
                         value="{{ $row->last_seq }}"
                         min="0"
                         style="width:120px">
                </td>
                <td class="text-end">{{ $row->last_seq + 1 }}</td>
                <td class="text-end">
                  <button type="submit" form="counter-{{ $row->id }}" class="btn btn-sm btn-outline-primary">
                    Update
                  </button>
                  <form id="counter-{{ $row->id }}" method="post" action="{{ route('document-counters.update', $row) }}">
                    @csrf
                    @method('PATCH')
                  </form>
                </td>
              </tr>
            @empty
              <tr>
                <td colspan="6" class="text-center text-muted">No data.</td>
              </tr>
            @endforelse
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>
@endsection
