<!DOCTYPE html>
<html>
<head>
  <meta charset="utf-8">
  <style>
    body { font-family: DejaVu Sans, sans-serif; font-size: 12px; color: #222; }
    .header { display:flex; justify-content:space-between; align-items:flex-start; margin-bottom:20px; }
    .company-name { font-size:18px; font-weight:bold; }
    .meta-table { width:100%; margin-bottom:20px; border-collapse:collapse; }
    .meta-table th, .meta-table td { text-align:left; padding:4px 6px; border-bottom:1px solid #ddd; }
    .items { width:100%; border-collapse:collapse; }
    .items th { background:#f2f2f2; }
    .items th, .items td { border:1px solid #ccc; padding:6px; }
    .text-end { text-align:right; }
    .totals { width:40%; margin-left:auto; border-collapse:collapse; }
    .totals td { padding:4px 6px; }
    .wm {
      position: fixed; top: 35%; left: 10%; right:10%; text-align:center;
      font-size: 64px; color: rgba(180,180,180,0.30); transform: rotate(-20deg);
    }
  </style>
</head>
<body>
@php
  $company   = $billing->company;
  $headerName = $company->alias ?? $company->name ?? '';
  $addr       = $company->address ?? '';
  $phone      = $company->phone ?? '';
  $email      = $company->email ?? '';
  $taxId      = $company->tax_id ?? '';

  $contactLine = implode(' | ', array_filter([$phone ?: null, $email ?: null]));

  $mode = $mode ?? 'invoice';
  $docNo = $mode === 'proforma'
    ? ($billing->pi_number ?: 'DRAFT-'.$billing->id)
    : ($billing->inv_number ?: 'DRAFT-'.$billing->id);
  $docDate = $mode === 'invoice'
    ? ($billing->invoice_date ?? $billing->issued_at ?? $billing->created_at)
    : ($billing->pi_issued_at ?? $billing->created_at);
@endphp

@if($mode === 'proforma')
  <div class="wm">PROFORMA</div>
@endif

<div class="header">
  <div>
    <div class="company-name">{{ $headerName }}</div>
    @if($addr)<div>{{ $addr }}</div>@endif
    @if($contactLine)<div>{{ $contactLine }}</div>@endif
    @if($taxId)<div>NPWP: {{ $taxId }}</div>@endif
  </div>
  <div style="text-align:right;">
    <h2>{{ $mode === 'proforma' ? 'Proforma Invoice' : 'Invoice' }}</h2>
    <div>No: {{ $docNo }}</div>
    <div>Tanggal: {{ optional($docDate)->format('d M Y') }}</div>
  </div>
</div>

<table class="meta-table">
  <tr>
    <th width="25%">Customer</th>
    <td>{{ $billing->customer->name ?? '-' }}</td>
    <th width="25%">Status</th>
    <td>{{ ucfirst($billing->status ?? 'draft') }}</td>
  </tr>
  <tr>
    <th>Sales Order</th>
    <td>{{ $billing->salesOrder->so_number ?? '-' }}</td>
    <th>Currency</th>
    <td>{{ $billing->currency ?? 'IDR' }}</td>
  </tr>
</table>

<table class="items">
  <thead>
    <tr>
      <th width="5%">#</th>
      <th>Deskripsi</th>
      <th width="12%" class="text-end">Qty</th>
      <th width="10%">Unit</th>
      <th width="15%" class="text-end">Harga</th>
      <th width="15%" class="text-end">Subtotal</th>
      <th width="15%" class="text-end">Total</th>
    </tr>
  </thead>
  <tbody>
  @foreach(($billing->lines ?? []) as $i => $ln)
    <tr>
      <td class="text-end">{{ $i+1 }}</td>
      <td>{{ $ln->description ?: $ln->name }}</td>
      <td class="text-end">{{ number_format((float)$ln->qty, 2) }}</td>
      <td>{{ strtoupper($ln->unit ?? '-') }}</td>
      <td class="text-end">{{ number_format((float)$ln->unit_price, 2) }}</td>
      <td class="text-end">{{ number_format((float)$ln->line_subtotal, 2) }}</td>
      <td class="text-end">{{ number_format((float)$ln->line_total, 2) }}</td>
    </tr>
  @endforeach
  </tbody>
</table>

<table class="totals" style="margin-top:16px;">
  <tr>
    <td>Subtotal</td>
    <td class="text-end">{{ number_format((float)$billing->subtotal, 2) }}</td>
  </tr>
  <tr>
    <td>Discount</td>
    <td class="text-end">{{ number_format((float)$billing->discount_amount, 2) }}</td>
  </tr>
  <tr>
    <td>Tax ({{ (float)$billing->tax_percent }}%)</td>
    <td class="text-end">{{ number_format((float)$billing->tax_amount, 2) }}</td>
  </tr>
  <tr>
    <td><strong>Total</strong></td>
    <td class="text-end"><strong>{{ number_format((float)$billing->total, 2) }}</strong></td>
  </tr>
</table>
</body>
</html>
