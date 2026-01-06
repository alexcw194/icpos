@extends('layouts.tabler')

@section('content')
  <div class="card">
    <div class="card-body">
      <h3>{{ $job->parentItem->name }}</h3>
      <p class="text-muted mb-2">
        <strong>Tipe:</strong> {{ ucfirst($job->job_type) }} <br>
        <strong>Tanggal:</strong> {{ $job->produced_at->format('d M Y H:i') }} <br>
        <strong>Jumlah:</strong> {{ number_format($job->qty_produced, 3) }} <br>
        <strong>Dibuat Oleh:</strong> {{ $job->producedBy->name ?? '-' }}
      </p>

      <h4>Komponen</h4>
      <table class="table table-sm">
        <thead>
          <tr>
            <th>Item</th>
            <th class="text-end">Qty Digunakan</th>
          </tr>
        </thead>
        <tbody>
          @foreach($job->json_components as $c)
            @php $item = \App\Models\Item::find($c['item_id']); @endphp
            <tr>
              <td>{{ $item?->name ?? 'Item #' . $c['item_id'] }}</td>
              <td class="text-end">{{ number_format($c['qty_used'], 3) }}</td>
            </tr>
          @endforeach
        </tbody>
      </table>

      @if($job->notes)
        <div class="mt-3">
          <h4>Catatan</h4>
          <p>{{ $job->notes }}</p>
        </div>
      @endif
    </div>

    <div class="card-footer text-end">
      <a href="{{ route('manufacture-jobs.index') }}" class="btn btn-secondary">Kembali</a>
    </div>
  </div>
@endsection
