@extends('layouts.tabler')

@section('content')
<div class="container-xl">

  {{-- Header actions --}}
  <div class="d-flex justify-content-between align-items-center mb-3">
    <h2>Invoice {{ $invoice->number }}</h2>

    <div class="d-flex gap-2">
      {{-- PDF buttons per status --}}
      @if(strtolower((string)$invoice->status) !== 'posted')
        <a href="{{ route('invoices.pdf.proforma', $invoice) }}" target="_blank" class="btn btn-outline-secondary">
          PDF Proforma
        </a>
        <a href="{{ route('invoices.pdf', $invoice) }}" target="_blank" class="btn btn-outline-primary">
          PDF Invoice
        </a>
      @else
        <a href="{{ route('invoices.pdf', $invoice) }}" target="_blank" class="btn btn-outline-primary">
          PDF Invoice
        </a>
      @endif

      {{-- Existing action: Create Delivery (retained) --}}
      <form action="{{ route('invoices.create-delivery', $invoice) }}" method="POST">
        @csrf
        <button class="btn btn-primary">Create Delivery</button>
      </form>
    </div>
  </div>

  {{-- Header card --}}
  <div class="card mb-3">
    <div class="card-body">
      <div><strong>Company:</strong> {{ $invoice->company->alias ?? $invoice->company->name }}</div>
      <div><strong>Customer:</strong> {{ $invoice->customer->name ?? '-' }}</div>
      <div><strong>Date:</strong> {{ $invoice->date?->format('Y-m-d') }}</div>
      <div><strong>Total:</strong> {{ number_format($invoice->total, 2, ',', '.') }}</div>
    </div>
  </div>

  {{-- Quotation-derived lines (if any) --}}
  @if($invoice->quotation)
    <div class="card">
      <div class="card-header">
        <h3 class="card-title">Lines (dari Quotation {{ $invoice->quotation->number }})</h3>
      </div>
      <div class="table-responsive">
        <table class="table card-table">
          <thead>
            <tr>
              <th>Deskripsi</th>
              <th class="text-end">Qty</th>
              <th class="text-end">Harga</th>
              <th class="text-end">Total</th>
            </tr>
          </thead>
          <tbody>
            @foreach($invoice->quotation->items as $ln)
              <tr>
                <td>{{ $ln->name }}</td>
                <td class="text-end">{{ $ln->qty }} {{ $ln->unit }}</td>
                <td class="text-end">{{ number_format($ln->unit_price, 2, ',', '.') }}</td>
                <td class="text-end">{{ number_format($ln->line_total, 2, ',', '.') }}</td>
              </tr>
            @endforeach
          </tbody>
        </table>
      </div>
    </div>
  @endif

  {{-- Actual invoice lines --}}
  @if($invoice->relationLoaded('lines'))
    <div class="card mt-3">
      <div class="card-header">Invoice Lines</div>
      <div class="table-responsive">
        <table class="table table-sm">
          <thead>
            <tr>
              <th>#</th>
              <th>Description</th>
              <th class="text-end">Qty</th>
              <th>Unit</th>
              <th class="text-end">Price</th>
              <th class="text-end">Disc</th>
              <th class="text-end">Subtotal</th>
              <th class="text-end">Line Total</th>
            </tr>
          </thead>
          <tbody>
            @foreach($invoice->lines as $i => $ln)
              <tr>
                <td>{{ $i + 1 }}</td>
                <td>{{ $ln->description }}</td>
                <td class="text-end">{{ number_format((float)$ln->qty, 2) }}</td>
                <td>{{ strtoupper($ln->unit) }}</td>
                <td class="text-end">{{ number_format((float)$ln->unit_price, 2) }}</td>
                <td class="text-end">{{ number_format((float)$ln->discount_amount, 2) }}</td>
                <td class="text-end">{{ number_format((float)$ln->line_subtotal, 2) }}</td>
                <td class="text-end fw-bold">{{ number_format((float)$ln->line_total, 2) }}</td>
              </tr>
            @endforeach
          </tbody>
        </table>
      </div>
    </div>
  @endif

</div>
@endsection
