@extends('layouts.tabler')
@section('content')
<div class="page-header"><h2>Create Invoice — From Sales Order #{{ $so->number ?? $so->id }}</h2></div>
<form method="POST" action="{{ route('invoices.store-from-so', $so) }}">
@csrf
<div class="row g-3">
<div class="col-md-3">
<label class="form-label">Invoice Date</label>
<input type="date" name="date" class="form-control" value="{{ now()->toDateString() }}" required>
</div>
<div class="col-md-3">
<label class="form-label">Due Date</label>
<input type="date" name="due_date" class="form-control" value="{{ now()->addDays(30)->toDateString() }}">
</div>
<div class="col-md-3">
<label class="form-label">Tax %</label>
<input type="number" step="0.01" name="tax_percent" class="form-control" value="{{ $so->company->is_taxable ? ($so->company->default_tax_percent ?? 11) : 0 }}">
</div>
</div>


<div class="card mt-3">
<div class="card-header">Lines to Invoice</div>
<div class="table-responsive">
<table class="table table-sm align-middle">
<thead><tr>
<th>Item</th><th>Ordered</th><th>Billed</th><th>Remaining</th><th>Qty to Bill</th><th>Unit</th><th>Unit Price</th>
</tr></thead>
<tbody>
@php
$billed = DB::table('invoice_lines')->select('sales_order_line_id', DB::raw('SUM(qty) as qty'))->where('sales_order_id',$so->id)->groupBy('sales_order_line_id')->pluck('qty','sales_order_line_id');
@endphp
@foreach($so->lines as $L)
@php
$ordered = (float)($L->qty ?? 0);
$already = (float)($billed[$L->id] ?? 0);
$remain = max(0, $ordered - $already);
@endphp
<tr>
<td>
<div class="fw-bold">{{ $L->name ?? 'Item' }} @if($L->variant) <span class="text-muted">[{{ $L->variant->label ?? $L->variant->sku }}]</span>@endif</div>
<div class="text-muted small">{{ $L->description }}</div>
<input type="hidden" name="lines[{{ $L->id }}][sales_order_line_id]" value="{{ $L->id }}">
</td>
<td>{{ number_format($ordered,2) }}</td>
<td>{{ number_format($already,2) }}</td>
<td class="text-teal fw-bold">{{ number_format($remain,2) }}</td>
<td><input type="number" step="0.0001" class="form-control" name="lines[{{ $L->id }}][qty]" value="{{ $remain }}" max="{{ $remain }}" {{ $remain>0 ? '' : 'readonly' }}></td>
<td>{{ strtoupper($L->unit ?? 'pcs') }}</td>
<td><input type="number" step="0.01" class="form-control" name="lines[{{ $L->id }}][unit_price]" value="{{ $L->unit_price ?? 0 }}"></td>
</tr>
@endforeach
</tbody>
</table>
</div>
</div>


@include('layouts.partials.form_footer', [
'cancelUrl' => route('sales-orders.show', $so),
'cancelLabel' => 'Batal',
'cancelInline' => true,
'buttons' => [['type'=>'submit','label'=>'Create Invoice','class'=>'btn btn-primary']]
])
</form>
@endsection