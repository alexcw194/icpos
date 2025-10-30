@extends('layouts.tabler')

@section('content')
<div class="container-xl">
  <div class="card">
    <div class="card-header d-flex">
      <h3 class="card-title">PO {{ $po->number }}</h3>
      <div class="ms-auto">
        @if($po->status === 'draft')
        <form action="{{ route('po.approve', $po) }}" method="POST" class="d-inline">@csrf
          <button class="btn btn-success">Approve</button>
        </form>
        @endif
        @if(in_array($po->status,['approved','partial']))
          <a href="{{ route('po.receive', $po) }}" class="btn btn-primary">Receive</a>
        @endif
      </div>
    </div>
    <div class="card-body">
      <div class="row g-3">
        <div class="col-md-3"><strong>Supplier</strong><br>{{ $po->supplier_name ?? '—' }}</div>
        <div class="col-md-3"><strong>Company</strong><br>{{ $po->company->alias ?? $po->company->name ?? '—' }}</div>
        <div class="col-md-3"><strong>Warehouse</strong><br>{{ $po->warehouse->name ?? '—' }}</div>
        <div class="col-md-3"><strong>Status</strong><br><span class="badge bg-blue">{{ ucfirst($po->status) }}</span></div>
      </div>

      <hr class="my-3">

      <div class="table-responsive">
        <table class="table">
          <thead><tr>
            <th>Item</th><th>Variant</th><th class="text-end">Ordered</th><th class="text-end">Received</th><th class="text-end">Remaining</th><th class="text-end">Price</th>
          </tr></thead>
          <tbody>
            @foreach($po->lines as $ln)
            <tr>
              <td>{{ $ln->item->sku ?? '' }} — {{ $ln->item->name ?? '' }}</td>
              <td>{{ $ln->itemVariant->sku ?? '—' }}</td>
              <td class="text-end">{{ number_format($ln->qty_ordered,4,'.',',') }} {{ $ln->uom ?? '' }}</td>
              <td class="text-end">{{ number_format($ln->qty_received ?? 0,4,'.',',') }}</td>
              <td class="text-end">{{ number_format(($ln->qty_ordered - ($ln->qty_received ?? 0)),4,'.',',') }}</td>
              <td class="text-end">{{ number_format($ln->unit_price ?? 0,2,'.',',') }}</td>
            </tr>
            @endforeach
          </tbody>
        </table>
      </div>

      @if($po->notes)
      <div class="mt-3"><strong>Notes</strong><div class="text-muted">{{ $po->notes }}</div></div>
      @endif
    </div>
  </div>
</div>
@endsection
