@extends('layouts.tabler')

@section('content')
<div class="container-xl">
  <h2>Delivery {{ $delivery->number }}</h2>

  <div class="card">
    <div class="card-body">
      <div><strong>Company:</strong> {{ $delivery->company->alias ?? $delivery->company->name }}</div>
      <div><strong>Date:</strong> {{ $delivery->date?->format('Y-m-d') }}</div>
      <div><strong>Invoice:</strong>
        @if($delivery->invoice)
          <a href="{{ route('invoices.show',$delivery->invoice) }}">{{ $delivery->invoice->number }}</a>
        @else
          -
        @endif
      </div>
      <div class="mt-2"><strong>Recipient:</strong> {{ $delivery->recipient ?? '-' }}</div>
      <div><strong>Address:</strong> {!! nl2br(e($delivery->address)) !!}</div>
      <div><strong>Notes:</strong> {!! nl2br(e($delivery->notes)) !!}</div>
    </div>
  </div>
</div>
@endsection
