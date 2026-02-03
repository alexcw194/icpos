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
        <div class="col-md-3"><strong>Supplier</strong><br>{{ $po->supplier->name ?? '—' }}</div>
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
              <td>{{ $ln->sku_snapshot ?? ($ln->item->sku ?? '') }} — {{ $ln->item_name_snapshot ?? ($ln->item->name ?? '') }}</td>
              <td>{{ $ln->variant->sku ?? '—' }}</td>
              <td class="text-end">{{ number_format($ln->qty_ordered,4,'.',',') }} {{ $ln->uom ?? '' }}</td>
              <td class="text-end">{{ number_format($ln->qty_received ?? 0,4,'.',',') }}</td>
              <td class="text-end">{{ number_format(($ln->qty_ordered - ($ln->qty_received ?? 0)),4,'.',',') }}</td>
              <td class="text-end">{{ number_format($ln->unit_price ?? 0,2,'.',',') }}</td>
            </tr>
            @endforeach
          </tbody>
        </table>
      </div>

      <div class="row justify-content-end mt-3">
        <div class="col-md-4">
          <table class="table table-sm">
            <tr>
              <td>Subtotal</td>
              <td class="text-end">{{ number_format($po->subtotal ?? 0, 2, '.', ',') }}</td>
            </tr>
            <tr>
              <td>Tax ({{ number_format($po->tax_percent ?? 0, 2, '.', ',') }}%)</td>
              <td class="text-end">{{ number_format($po->tax_amount ?? 0, 2, '.', ',') }}</td>
            </tr>
            <tr class="fw-bold">
              <td>Total</td>
              <td class="text-end">{{ number_format($po->total ?? 0, 2, '.', ',') }}</td>
            </tr>
          </table>
        </div>
      </div>

      @if($po->notes)
      <div class="mt-3"><strong>Notes</strong><div class="text-muted">{{ $po->notes }}</div></div>
      @endif

      @if($po->billingTerms->isNotEmpty())
      <hr class="my-3">
      <div>
        <h4 class="mb-2">Payment Terms</h4>
        <div class="table-responsive">
          <table class="table table-sm">
            <thead>
              <tr>
                <th>Code</th>
                <th class="text-end">Percent</th>
                <th>Schedule</th>
                <th>Note</th>
              </tr>
            </thead>
            <tbody>
              @foreach($po->billingTerms as $term)
                <tr>
                  <td>{{ $term->top_code }}</td>
                  <td class="text-end">{{ number_format((float) $term->percent, 2, ',', '.') }}%</td>
                  <td>
                    {{ $term->due_trigger ?? '—' }}
                    @if($term->offset_days !== null)
                      ({{ $term->offset_days }}d)
                    @elseif($term->day_of_month !== null)
                      (day {{ $term->day_of_month }})
                    @endif
                  </td>
                  <td>{{ $term->note ?? '—' }}</td>
                </tr>
              @endforeach
            </tbody>
          </table>
        </div>
      </div>
      @endif
    </div>
  </div>
</div>
@endsection
