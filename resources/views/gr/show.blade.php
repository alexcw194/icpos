@extends('layouts.tabler')

@section('content')
<div class="container-xl">
  <div class="card">
    <div class="card-header d-flex">
      <h3 class="card-title">GR {{ $gr->number }}</h3>
      <div class="ms-auto">
        @if($gr->status === 'draft')
        <form action="{{ route('gr.post', $gr) }}" method="POST" class="d-inline">@csrf
          <button class="btn btn-success"
                  onclick="return confirm('Post GR ini? Stok akan bertambah.');">Post GR</button>
        </form>
        @endif
      </div>
    </div>
    <div class="card-body">
      <div class="row g-3">
        <div class="col-md-3"><strong>Company</strong><br>{{ $gr->company->alias ?? $gr->company->name ?? '—' }}</div>
        <div class="col-md-3"><strong>Warehouse</strong><br>{{ $gr->warehouse->name ?? '—' }}</div>
        <div class="col-md-3"><strong>From PO</strong><br>
          @if($gr->purchaseOrder)
            <a href="{{ route('po.show', $gr->purchaseOrder) }}">{{ $gr->purchaseOrder->number }}</a>
          @else — @endif
        </div>
        <div class="col-md-3"><strong>Status</strong><br>
          <span class="badge bg-{{ $gr->status === 'posted' ? 'green' : 'yellow' }}">{{ ucfirst($gr->status) }}</span>
        </div>
        <div class="col-md-3"><strong>GR Date</strong><br>{{ $gr->gr_date ?? '—' }}</div>
      </div>

      <hr class="my-3">

      <div class="table-responsive">
        <table class="table">
          <thead><tr>
            <th>Item</th><th>Variant</th>
            <th class="text-end">Qty Received</th>
            <th class="text-end">UoM</th>
            <th class="text-end">Unit Cost</th>
            <th class="text-end">Line Total</th>
          </tr></thead>
          <tbody>
            @foreach($gr->lines as $ln)
            <tr>
              <td>{{ $ln->item->sku ?? '' }} — {{ $ln->item->name ?? '' }}</td>
              <td>{{ $ln->itemVariant->sku ?? '—' }}</td>
              <td class="text-end">{{ number_format($ln->qty_received,4,'.',',') }}</td>
              <td class="text-end">{{ $ln->uom ?? '—' }}</td>
              <td class="text-end">{{ number_format($ln->unit_cost ?? 0,2,'.',',') }}</td>
              <td class="text-end">{{ number_format($ln->line_total ?? ($ln->qty_received * ($ln->unit_cost ?? 0)),2,'.',',') }}</td>
            </tr>
            @endforeach
          </tbody>
        </table>
      </div>

      @if($gr->notes)
      <div class="mt-3"><strong>Notes</strong><div class="text-muted">{{ $gr->notes }}</div></div>
      @endif
    </div>
  </div>
</div>
@endsection
