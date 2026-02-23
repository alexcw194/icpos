<!doctype html>
<html>
<head>
  <meta charset="utf-8">
  <title>{{ $po->status === 'draft' ? ('PO Draft #' . $po->id) : ('PO ' . $po->number) }}</title>
  <style>
    * { font-family: DejaVu Sans, sans-serif; font-size: 12px; color:#111; }
    body { margin: 0; }
    .right { text-align:right; }
    .small { font-size:11px; color:#555; }
    .title { font-size: 20px; font-weight: 800; letter-spacing: .3px; margin: 0; }
    .subtitle { margin: 2px 0 0; }

    .hdr { width: 100%; border-collapse: collapse; margin-bottom: 14px; }
    .hdr td { vertical-align: top; padding: 0; }

    .grid { width: 100%; border-collapse: collapse; }
    .grid th, .grid td { border: 1px solid #999; padding: 6px; }

    .totals { width: 100%; border-collapse: collapse; margin-top: 12px; }
    .totals td { padding: 4px 0; }

    .section-title { font-size: 13px; font-weight: 700; margin: 14px 0 6px; }

    .sign-wrap { width: 100%; margin-top: 30px; }
    .sign-box { width: 260px; margin-left: auto; text-align: left; }
    .sign-space { height: 60px; }
    .sign-name { font-weight: 700; text-decoration: underline; }

    .draft-badge {
      display: inline-block;
      padding: 4px 10px;
      border: 1px solid #d9534f;
      color: #d9534f;
      font-weight: 700;
      border-radius: 4px;
      margin-top: 4px;
    }
  </style>
</head>
<body>
@php
  $isDraft = $po->status === 'draft';
  $numberText = $isDraft ? ('DRAFT-' . $po->id) : ($po->number ?: ('PO-' . $po->id));
  $fmtDate = fn($d) => $d ? \Illuminate\Support\Carbon::parse($d)->format('d M Y') : '-';
@endphp

<table class="hdr">
  <tr>
    <td style="width:60%">
      <div style="font-size:14px; font-weight:700; text-transform:uppercase;">
        {{ $po->company->alias ?? $po->company->name ?? '-' }}
      </div>
      <div class="small">{{ $po->company->address ?? '-' }}</div>
      @if(!empty($po->company->phone))
        <div class="small">Telp: {{ $po->company->phone }}</div>
      @endif
      @if(!empty($po->company->email))
        <div class="small">Email: {{ $po->company->email }}</div>
      @endif
    </td>
    <td style="width:40%" class="right">
      <p class="title">PURCHASE ORDER</p>
      <div class="subtitle"><strong>No:</strong> {{ $numberText }}</div>
      <div class="subtitle"><strong>Tanggal:</strong> {{ $fmtDate($po->order_date) }}</div>
      @if($isDraft)
        <div class="draft-badge">DRAFT</div>
      @endif
    </td>
  </tr>
</table>

<table style="width:100%; border-collapse:collapse; margin-bottom:12px;">
  <tr>
    <td style="width:50%; vertical-align:top; padding-right:10px;">
      <div class="section-title" style="margin-top:0;">Supplier</div>
      <div><strong>{{ $po->supplier->name ?? '-' }}</strong></div>
      @if(!empty($po->supplier->address))
        <div class="small">{{ $po->supplier->address }}</div>
      @endif
      @if(!empty($po->supplier->phone))
        <div class="small">Telp: {{ $po->supplier->phone }}</div>
      @endif
      @if(!empty($po->supplier->email))
        <div class="small">Email: {{ $po->supplier->email }}</div>
      @endif
    </td>
    <td style="width:50%; vertical-align:top;">
      <div class="section-title" style="margin-top:0;">Info</div>
      <div><strong>Status:</strong> {{ ucfirst($po->status) }}</div>
      <div><strong>Warehouse:</strong> {{ $po->warehouse->name ?? '-' }}</div>
    </td>
  </tr>
</table>

<table class="grid">
  <thead>
    <tr>
      <th style="width:35%">Item</th>
      <th style="width:13%">Variant</th>
      <th style="width:11%" class="right">Ordered</th>
      <th style="width:11%" class="right">Received</th>
      <th style="width:11%" class="right">Remaining</th>
      <th style="width:19%" class="right">Unit Price</th>
    </tr>
  </thead>
  <tbody>
    @forelse($po->lines as $ln)
      @php $remaining = (float)$ln->qty_ordered - (float)($ln->qty_received ?? 0); @endphp
      <tr>
        <td>
          <strong>{{ $ln->sku_snapshot ?? ($ln->item->sku ?? '') }}</strong>
          <div class="small">{{ $ln->item_name_snapshot ?? ($ln->item->name ?? '-') }}</div>
        </td>
        <td>{{ $ln->variant->sku ?? '-' }}</td>
        <td class="right">{{ number_format((float)$ln->qty_ordered, 2, '.', ',') }} {{ $ln->uom ?? '' }}</td>
        <td class="right">{{ number_format((float)($ln->qty_received ?? 0), 2, '.', ',') }}</td>
        <td class="right">{{ number_format($remaining, 2, '.', ',') }}</td>
        <td class="right">{{ number_format((float)($ln->unit_price ?? 0), 2, '.', ',') }}</td>
      </tr>
    @empty
      <tr>
        <td colspan="6" class="right">No lines.</td>
      </tr>
    @endforelse
  </tbody>
</table>

<table class="totals">
  <tr>
    <td style="width:60%"></td>
    <td>
      <table style="width:100%; border-collapse:collapse;">
        <tr>
          <td>Subtotal</td>
          <td class="right">{{ number_format((float)($po->subtotal ?? 0), 2, '.', ',') }}</td>
        </tr>
        <tr>
          <td>Tax ({{ number_format((float)($po->tax_percent ?? 0), 2, '.', ',') }}%)</td>
          <td class="right">{{ number_format((float)($po->tax_amount ?? 0), 2, '.', ',') }}</td>
        </tr>
        <tr>
          <td><strong>Total</strong></td>
          <td class="right"><strong>{{ number_format((float)($po->total ?? 0), 2, '.', ',') }}</strong></td>
        </tr>
      </table>
    </td>
  </tr>
</table>

@if($po->billingTerms->isNotEmpty())
  <div class="section-title">Billing Terms</div>
  <table class="grid">
    <thead>
      <tr>
        <th style="width:20%">Code</th>
        <th style="width:15%" class="right">Percent</th>
        <th style="width:30%">Schedule</th>
        <th style="width:35%">Note</th>
      </tr>
    </thead>
    <tbody>
      @foreach($po->billingTerms as $term)
        <tr>
          <td>{{ $term->top_code }}</td>
          <td class="right">{{ number_format((float)$term->percent, 2, '.', ',') }}%</td>
          <td>
            {{ $term->due_trigger ?? '-' }}
            @if($term->offset_days !== null)
              ({{ $term->offset_days }}d)
            @elseif($term->day_of_month !== null)
              (day {{ $term->day_of_month }})
            @endif
          </td>
          <td>{{ $term->note ?? '-' }}</td>
        </tr>
      @endforeach
    </tbody>
  </table>
@endif

@if($po->notes)
  <div class="section-title">Notes</div>
  <div>{{ $po->notes }}</div>
@endif

<div class="sign-wrap">
  <div class="sign-box">
    <div>Hormat Kami,</div>
    <div class="sign-space"></div>
    <div class="sign-name">Christian Widargo</div>
    <div>Direktur Utama</div>
  </div>
</div>
</body>
</html>