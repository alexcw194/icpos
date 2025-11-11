@extends('layouts.tabler')

@section('title', 'Riwayat Produksi')

@section('content')

  <div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
      <h3 class="card-title">Daftar Produksi</h3>
      <a href="{{ route('manufacture-jobs.create') }}" class="btn btn-primary">+ Produksi Baru</a>
    </div>
    <div class="table-responsive">
      <table class="table card-table table-vcenter">
        <thead>
          <tr>
            <th>Tanggal</th>
            <th>Item Hasil</th>
            <th>Jumlah</th>
            <th>Tipe</th>
            <th>Dibuat Oleh</th>
            <th></th>
          </tr>
        </thead>
        <tbody>
          @foreach($jobs as $job)
          <tr>
            <td>{{ $job->produced_at->format('d M Y H:i') }}</td>
            <td>{{ $job->parentItem->name }}</td>
            <td>{{ number_format($job->qty_produced, 3) }}</td>
            <td>
              <span class="badge bg-{{ match($job->job_type) {
                'cut' => 'yellow',
                'fill' => 'blue',
                'assembly' => 'green',
                'bundle' => 'purple',
                default => 'gray',
              } }}">
                {{ ucfirst($job->job_type) }}
              </span>
            </td>
            <td>{{ $job->producedBy->name ?? '-' }}</td>
            <td class="text-end">
              <a href="{{ route('manufacture-jobs.show', $job) }}" class="btn btn-sm btn-outline-secondary">
                Detail
              </a>
            </td>
          </tr>
          @endforeach
        </tbody>
      </table>
    </div>
    <div class="card-footer">
      {{ $jobs->links() }}
    </div>
  </div>
@endsection
