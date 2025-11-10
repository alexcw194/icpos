@extends('layouts.tabler')

@section('content')
<div class="container-xl">
  <form method="post" action="{{ route('inventory.adjustments.store') }}" class="card">
    @csrf
    <div class="card-header">
      <h3 class="card-title">New Stock Adjustment</h3>
    </div>
    <div class="card-body">
      <div class="mb-3">
        <label class="form-label">Item</label>
        <div>{{ $item->name }}</div>
        <input type="hidden" name="item_id" value="{{ $item->id }}">
        <input type="hidden" name="company_id" value="{{ auth()->user()->company_id }}">
      </div>
      <div class="mb-3">
        <label class="form-label">Current Balance</label>
        <div>{{ number_format($summary->qty_balance ?? 0, 2, ',', '.') }}</div>
      </div>
      <div class="mb-3">
        <label class="form-label">Qty Adjustment</label>
        <input type="number" name="qty_adjustment" step="0.0001" class="form-control" required>
      </div>
      <div class="mb-3">
        <label class="form-label">Reason</label>
        <textarea name="reason" class="form-control"></textarea>
      </div>
    </div>
    @include('layouts.partials.form_footer', [
        'cancelUrl' => route('inventory.adjustments.index'),
        'cancelLabel' => 'Cancel',
        'cancelInline' => true,
        'buttons' => [['label' => 'Save Adjustment', 'type' => 'submit', 'class' => 'btn btn-primary']]
    ])
  </form>
</div>
@endsection
