<!DOCTYPE html>
<html>
<head>
  <meta charset="utf-8">
  <style>
    body { font-family: DejaVu Sans, sans-serif; font-size: 12px; color: #222; }
    .header { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 20px; }
    .company-name { font-size: 18px; font-weight: bold; }
    .meta-table { width: 100%; margin-bottom: 20px; border-collapse: collapse; }
    .meta-table th, .meta-table td { text-align: left; padding: 4px 6px; border-bottom: 1px solid #ddd; }
    .items { width: 100%; border-collapse: collapse; }
    .items th { background: #f2f2f2; }
    .items th, .items td { border: 1px solid #ccc; padding: 6px; }
    .text-end { text-align: right; }
    .mt-40 { margin-top: 40px; }
    .sign-box { border-top: 1px solid #000; width: 200px; padding-top: 50px; text-align: center; }
  </style>
</head>
<body>
  @php
    $brand = $delivery->brand_snapshot ?? [];
    $company = $delivery->company;
    $headerName = $brand['alias'] ?? $brand['name'] ?? ($company->alias ?? $company->name ?? '');
  @endphp

  <div class="header">
    <div>
      <div class="company-name">{{ $headerName }}</div>
      <div>{{ $brand['address'] ?? $company->address }}</div>
      <div>{{ $brand['phone'] ?? $company->phone }} {{ $brand['email'] ? ' | '.$brand['email'] : '' }}</div>
      <div>{{ $brand['tax_id'] ? 'NPWP: '.$brand['tax_id'] : '' }}</div>
    </div>
    <div style="text-align:right;">
      <h2>Delivery Order</h2>
      <div>No: {{ $delivery->number ?? 'DRAFT-'.$delivery->id }}</div>
      <div>Tanggal: {{ optional($delivery->date)->format('d M Y') }}</div>
    </div>
  </div>

  <table class="meta-table">
    <tr>
      <th width="25%">Customer</th>
      <td>{{ $delivery->customer->name ?? '-' }}</td>
      <th width="25%">Warehouse</th>
      <td>{{ $delivery->warehouse->name ?? '-' }}</td>
    </tr>
    <tr>
      <th>Recipient</th>
      <td>{{ $delivery->recipient ?? '-' }}</td>
      <th>Reference</th>
      <td>{{ $delivery->reference ?? '-' }}</td>
    </tr>
    <tr>
      <th>Address</th>
      <td colspan="3">{!! $delivery->address ? nl2br(e($delivery->address)) : '-' !!}</td>
    </tr>
  </table>

  <table class="items">
    <thead>
      <tr>
        <th width="5%">#</th>
        <th>Item</th>
        <th width="15%">Variant</th>
        <th width="10%">Qty</th>
        <th width="10%">Unit</th>
        <th width="20%">Notes</th>
      </tr>
    </thead>
    <tbody>
      @foreach($delivery->lines as $index => $line)
        <tr>
          <td class="text-end">{{ $index + 1 }}</td>
          <td>{{ $line->description ?: ($line->item->name ?? '-') }}</td>
          <td>{{ $line->variant->name ?? '-' }}</td>
          <td class="text-end">{{ number_format((float) $line->qty, 2) }}</td>
          <td>{{ $line->unit ?? '-' }}</td>
          <td>{{ $line->line_notes ?? '-' }}</td>
        </tr>
      @endforeach
    </tbody>
  </table>

  <div class="mt-40" style="display:flex; justify-content:space-between;">
    <div class="sign-box">Pengirim</div>
    <div class="sign-box">Penerima</div>
  </div>
</body>
</html>
