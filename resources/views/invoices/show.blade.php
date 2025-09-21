@extends('layouts.tabler')

@section('content')
<div class="container-xl">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <h2>Invoice {{ $invoice->number }}</h2>
    <form action="{{ route('invoices.create-delivery',$invoice) }}" method="POST">
      @csrf
      <button class="btn btn-primary">Create Delivery</button>
    </form>
  </div>

  <div class="card mb-3">
    <div class="card-body">
      <div><strong>Company:</strong> {{ $invoice->company->alias ?? $invoice->company->name }}</div>
      <div><strong>Customer:</strong> {{ $invoice->customer->name ?? '-' }}</div>
      <div><strong>Date:</strong> {{ $invoice->date?->format('Y-m-d') }}</div>
      <div><strong>Total:</strong> {{ number_format($invoice->total,2,',','.') }}</div>
    </div>
  </div>

  @if($invoice->quotation)
  <div class="card">
    <div class="card-header"><h3 class="card-title">Lines (dari Quotation {{ $invoice->quotation->number }})</h3></div>
    <div class="table-responsive">
      <table class="table card-table">
        <thead><tr><th>Deskripsi</th><th class="text-end">Qty</th><th class="text-end">Harga</th><th class="text-end">Total</th></tr></thead>
        <tbody>
        @foreach($invoice->quotation->items as $ln)
          <tr>
            <td>{{ $ln->name }}</td>
            <td class="text-end">{{ $ln->qty }} {{ $ln->unit }}</td>
            <td class="text-end">{{ number_format($ln->unit_price,2,',','.') }}</td>
            <td class="text-end">{{ number_format($ln->line_total,2,',','.') }}</td>
          </tr>
        @endforeach
        </tbody>
      </table>
    </div>
  </div>
  @endif
</div>
@endsection
