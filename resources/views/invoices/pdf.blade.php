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
    /* Watermark */
    .wm {
      position: fixed; top: 35%; left: 10%; right:10%; text-align:center;
      font-size: 64px; color: rgba(180,180,180,0.30); transform: rotate(-20deg);
    }
  </style>
</head>
<body>
@php
  // Snapshot & normalization
  $brandArr  = is_array($invoice->brand_snapshot ?? null)
                ? $invoice->brand_snapshot
                : (array)($invoice->brand_snapshot ?? []);
  $company   = $invoice->company;

  $headerName = $brandArr['alias'] ?? $brandArr['name'] ?? ($company->alias ?? $company->name ?? '');
  $addr       = $brandArr['address'] ?? $company->address ?? '';
  $phone      = $brandArr['phone']   ?? $company->phone   ?? '';
  $email      = $brandArr['email']   ?? ($company->email ?? '');
  $taxId      = $brandArr['tax_id']  ?? ($company->tax_id ?? '');

  $contactLine = implode(' | ', array_filter([$phone ?: null, $email ?: null]));

  $isPosted = strtolower((string)$invoice->status) === 'posted';
@endphp

{{-- Watermark rules:
   - mode=proforma  : tampil "PROFORMA"
   - mode=invoice   : jika posted => "COPY"; jika draft => tanpa watermark --}}
@if(($mode ?? 'invoice') === 'proforma')
  <div class="wm">PROFORMA</div>
@elseif(($mode ?? 'invoice') === 'invoice' && $isPosted)
  <div class="wm">COPY</div>
@endif

<div class="header">
  <div>
    <div class="company-name">{{ $headerName }}</div>
    @if($addr)<div>{{ $addr }}</div>@endif
    @if($contactLine)<div>{{ $contactLine }}</div>@endif
    @if($taxId)<div>NPWP: {{ $taxId }}</div>@endif
  </div>
  <div style="text-align:right;">
    <h2>{{ ($mode ?? 'invoice') === 'proforma' ? 'Proforma Invoice' : 'Invoice' }}</h2>
    <div>No: {{ $invoice->number ?? 'DRAFT-'.$invoice->id }}</div>
    <div>Tanggal: {{ optional($invoice->date)->format('d M Y') }}</div>
  </div>
</div>

<table class="meta-table">
  <tr>
    <th width="25%">Customer</th>
    <td>{{ $invoice->customer->name ?? '-' }}</td>
    <th width="25%">Status</th>
    <td>{{ ucfirst($invoice->status ?? 'draft') }}</td>
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
  @foreach(($invoice->lines ?? []) as $i => $ln)
    <tr>
      <td class="text-end">{{ $i+1 }}</td>
      <td>{{ $ln->description }}</td>
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
    <td class="text-end">{{ number_format((float)$invoice->subtotal, 2) }}</td>
  </tr>
  <tr>
    <td>Discount</td>
    <td class="text-end">{{ number_format((float)$invoice->discount, 2) }}</td>
  </tr>
  <tr>
    <td>Tax ({{ (float)$invoice->tax_percent }}%)</td>
    <td class="text-end">{{ number_format((float)$invoice->tax_amount, 2) }}</td>
  </tr>
  <tr>
    <td><strong>Total</strong></td>
    <td class="text-end"><strong>{{ number_format((float)$invoice->total, 2) }}</strong></td>
  </tr>
</table>
</body>
</html>
