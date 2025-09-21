{{-- resources/views/quotations/print.blade.php --}}
@extends('layouts.tabler')

@section('content')
<div class="container-xl">
  <div class="card">
    <div class="card-body">
      <div class="d-flex">
        <div>
          <h3 class="m-0">{{ $quotation->number }}</h3>
          <div class="text-muted">Date: {{ $quotation->date?->format('d M Y') }}</div>
        </div>
        <div class="ms-auto">
          <a href="#" class="btn btn-outline" onclick="window.print()">Print</a>
          {{-- <a href="{{ route('quotations.pdf',$quotation) }}" class="btn btn-outline">Download PDF</a> --}}
        </div>
      </div>

      <hr>

      <div class="row mb-3">
        <div class="col-md-6">
          <div class="fw-bold">To</div>
          <div>{{ $quotation->customer->name ?? '-' }}</div>
          <div class="text-muted small">{{ $quotation->customer->address ?? '' }}</div>
        </div>
      </div>

      @include('quotations._items_table',['quotation'=>$quotation])

      <div class="mt-3 d-flex justify-content-end">
        <div class="w-50">
          <table class="table table-sm">
            <tr><th>Total</th><td class="text-end fw-bold">{{ $quotation->total_idr ?? '-' }}</td></tr>
          </table>
        </div>
      </div>

      @if(!empty($quotation->notes))
        <div class="mt-3"><strong>Note:</strong><br>{{ $quotation->notes }}</div>
      @endif
    </div>
  </div>
</div>
@endsection
