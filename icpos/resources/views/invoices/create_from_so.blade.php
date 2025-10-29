@extends('layouts.tabler')

@section('content')
<div class="page-header">
  <h2>Create Invoice — From Sales Order #{{ $so->number ?? $so->id }}</h2>
</div>

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
      <input type="number" step="0.01" name="tax_percent" class="form-control"
             value="{{ $so->company->is_taxable ? ($so->company->default_tax_percent ?? 11) : 0 }}">
    </div>
  </div>

  {{-- ===== Lines ===== --}}
  <div class="card mt-3">
    <div class="card-header">Lines to Invoice</div>
    <div class="table-responsive">
      <table class="table table-sm align-middle">
        <thead>
          <tr>
            <th>Item</th>
            <th class="text-end">Ordered</th>
            <th class="text-end">Billed</th>
            <th class="text-end">Remaining</th>
            <th class="text-end">Qty to Bill</th>
            <th>Unit</th>
            <th>Unit Price</th>
          </tr>
        </thead>
        <tbody>
          @php
            // Billed so far per SO line, counting ONLY posted invoices
            $billedMap = DB::table('invoice_lines')
              ->join('invoices','invoices.id','=','invoice_lines.invoice_id')
              ->where('invoices.status','posted')
              ->whereIn('invoice_lines.sales_order_line_id', $so->lines->pluck('id'))
              ->select('invoice_lines.sales_order_line_id', DB::raw('SUM(invoice_lines.qty) as qty_invoiced'))
              ->groupBy('invoice_lines.sales_order_line_id')
              ->pluck('qty_invoiced','sales_order_line_id');
          @endphp

          @foreach($so->lines as $L)
            @php
              $ordered   = (float) ($L->qty_ordered   ?? 0);
              $delivered = (float) ($L->qty_delivered ?? 0);
              $invoiced  = (float) ($billedMap[$L->id] ?? 0);

              // Remaining = delivered - invoiced (floored at 0)
              $remaining = max($delivered - $invoiced, 0);

              // Default qty to bill = remaining
              $defaultQty = number_format($remaining, 2, '.', '');
            @endphp
            <tr>
              <td>
                <div class="fw-bold">
                  {{ $L->item->name ?? $L->description ?? 'Item' }}
                  @if($L->variant)
                    <span class="text-muted">
                      [{{ $L->variant->name ?? $L->variant->sku ?? $L->variant->id }}]
                    </span>
                  @endif
                </div>
                @if(!empty($L->description))
                  <div class="text-muted small">{{ $L->description }}</div>
                @endif
                <input type="hidden" name="lines[{{ $L->id }}][sales_order_line_id]" value="{{ $L->id }}">
              </td>

              <td class="text-end">{{ number_format($ordered, 2) }}</td>
              <td class="text-end">{{ number_format($invoiced, 2) }}</td>
              <td class="text-end {{ $remaining == 0 ? 'text-muted' : 'text-teal fw-bold' }}">
                {{ number_format($remaining, 2) }}
              </td>

              <td class="text-end" style="max-width:160px;">
                <input type="number"
                       name="lines[{{ $L->id }}][qty]"
                       class="form-control text-end"
                       step="0.01" min="0"
                       value="{{ $defaultQty }}"
                       max="{{ $defaultQty }}"
                       {{ $remaining > 0 ? '' : 'readonly' }}>
              </td>

              <td>{{ strtoupper($L->unit ?? $L->item->unit?->code ?? 'pcs') }}</td>

              <td style="max-width:160px;">
                <input type="number"
                       name="lines[{{ $L->id }}][unit_price]"
                       class="form-control text-end"
                       step="0.01" min="0"
                       value="{{ number_format((float)($L->unit_price ?? $L->price ?? 0), 2, '.', '') }}">
              </td>
            </tr>
          @endforeach
        </tbody>
      </table>
    </div>
  </div>

  @include('layouts.partials.form_footer', [
    'cancelUrl'   => route('sales-orders.show', $so),
    'cancelLabel' => 'Batal',
    'cancelInline'=> true,
    'buttons'     => [['type'=>'submit','label'=>'Create Invoice','class'=>'btn btn-primary']]
  ])
</form>
@endsection
