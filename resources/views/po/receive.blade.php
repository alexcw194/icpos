@extends('layouts.tabler')

@section('content')
<div class="container-xl">
  <form method="POST" action="{{ route('po.receive.store', $po) }}">
    @csrf
    <div class="card">
      <div class="card-header"><h3 class="card-title">Receive PO {{ $po->number }}</h3></div>
      <div class="card-body">
        <div class="row g-3">
          <div class="col-md-3">
            <label class="form-label">GR Number</label>
            <input type="text" name="gr[number]" class="form-control" placeholder="Auto/Manual">
          </div>
          <div class="col-md-3">
            <label class="form-label">GR Date</label>
            <input type="date" name="gr[gr_date]" class="form-control" value="{{ now()->toDateString() }}">
          </div>
          <div class="col-12">
            <label class="form-label">Notes</label>
            <textarea name="gr[notes]" class="form-control" rows="2"></textarea>
          </div>
        </div>

        <hr class="my-3">

        <div class="table-responsive">
          <table class="table">
            <thead><tr>
              <th>Item</th><th>Variant</th>
              <th class="text-end">Remaining</th>
              <th class="text-end" style="width:140px">Qty Receive</th>
              <th class="text-end" style="width:160px">Unit Cost</th>
            </tr></thead>
            <tbody>
              @foreach($po->lines as $ln)
              @php $remaining = ($ln->qty_ordered - ($ln->qty_received ?? 0)); @endphp
              @if($remaining > 0)
              <tr>
                <td>{{ $ln->item->sku ?? '' }} — {{ $ln->item->name ?? '' }}</td>
                <td>{{ $ln->variant->sku ?? '—' }}</td>
                <td class="text-end">{{ number_format($remaining,4,'.',',') }} {{ $ln->uom ?? '' }}</td>
                <td class="text-end">
                  <input type="number" name="lines[{{ $ln->id }}][qty_received]" step="0.0001" min="0" max="{{ $remaining }}" class="form-control text-end">
                </td>
                <td class="text-end">
                  <input type="number" name="lines[{{ $ln->id }}][unit_cost]" step="0.01" min="0" class="form-control text-end">
                </td>
              </tr>
              @endif
              @endforeach
            </tbody>
          </table>
        </div>

      </div>
      <div class="card-footer d-flex">
        <a href="{{ route('po.show', $po) }}" class="btn btn-link">Cancel</a>
        <button class="btn btn-primary ms-auto" type="submit">Create GR Draft</button>
      </div>
    </div>
  </form>
</div>
@endsection
