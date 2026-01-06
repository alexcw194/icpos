@extends('layouts.tabler')

@section('content')
  <form method="POST" action="{{ route('manufacture-jobs.store') }}">
    @csrf

    <div class="mb-3">
      <label class="form-label">Item Hasil</label>
      <select name="parent_item_id" class="form-select" required>
        @foreach($items as $item)
          <option value="{{ $item->id }}">{{ $item->name }}</option>
        @endforeach
      </select>
    </div>

    <div class="mb-3">
      <label class="form-label">Jumlah Produksi</label>
      <input type="number" step="0.001" name="qty_produced" class="form-control" required>
    </div>

    <div class="mb-3">
      <label class="form-label">Tipe Job</label>
      <select name="job_type" class="form-select" required>
        <option value="assembly">Assembly</option>
        <option value="cut">Cut</option>
        <option value="fill">Fill</option>
        <option value="bundle">Bundle</option>
      </select>
    </div>

    <div class="mb-3">
      <label class="form-label">Catatan</label>
      <textarea name="notes" class="form-control"></textarea>
    </div>

    @include('layouts.partials.form_footer', [
      'cancelUrl' => route('manufacture-jobs.index'),
      'cancelLabel' => 'Batal',
      'cancelInline' => true,
      'buttons' => [['type' => 'submit', 'label' => 'Proses Produksi']]
    ])
  </form>
@endsection
