<!DOCTYPE html>
<html>
<head>
  <meta charset="utf-8">
  <style>
    * { font-family: DejaVu Sans, sans-serif; font-size: 12px; color:#111; }
    .right { text-align:right; }
    .small { font-size:11px; color:#555; }

    /* ===== Header 3 kolom (ikuti quotation) ===== */
    .hdr{ width:100%; border-collapse:collapse; margin-bottom:14px; table-layout:fixed; }
    .hdr td{ vertical-align:top; padding:0; word-wrap:break-word; }
    .hdr-left{ width:40%; text-align:left !important; }
    .hdr-mid{  width:35%; padding-left:8px; text-align:left !important; }
    .hdr-right{ width:25%; text-align:right !important; }

    .logo-top{ width:144px; height:auto; margin:0 0 6px 0; }
    .co-name{ margin:0 0 4px; font-size:14px; font-weight:800; text-transform:uppercase; }
    .co-meta div{ line-height:1.35; }

    .quo-title{ margin:0; font-size:20px; font-weight:900; letter-spacing:.3px; }
    .quo-number{ margin:2px 0 6px; font-weight:700; }
    .quo-row{ margin-top:2px; }
    h2.block { font-size:13px; margin:0 0 6px; text-transform:uppercase; letter-spacing:.4px; }

    .meta-table { width:100%; margin-bottom:20px; border-collapse:collapse; }
    .meta-table th, .meta-table td { text-align:left; padding:4px 6px; border-bottom:1px solid #ddd; }
    .items { width:100%; border-collapse:collapse; }
    .items th { background:#f2f2f2; }
    .items th, .items td { border:1px solid #ccc; padding:6px; }
    .text-end { text-align:right; }
    .totals { width:40%; margin-left:auto; border-collapse:collapse; }
    .totals td { padding:4px 6px; }
    .bank-table { width:100%; margin-top:16px; border-collapse:collapse; }
    .bank-table th, .bank-table td { border:1px solid #ccc; padding:6px; }
    .bank-table th { background:#f2f2f2; text-align:left; }
    /* Watermark */
    .wm {
      position: fixed; top: 35%; left: 10%; right:10%; text-align:center;
      font-size: 64px; color: rgba(180,180,180,0.30); transform: rotate(-20deg);
    }
  </style>
</head>
<body>
@php
  $isPosted = strtolower((string)$invoice->status) === 'posted';
  $mode = $mode ?? 'invoice';
  $docTitle = $mode === 'proforma' ? 'PROFORMA INVOICE' : 'INVOICE';
  $docNo = $invoice->number ?? 'DRAFT-'.$invoice->id;
  $docDate = $invoice->date ?? $invoice->created_at;

  $brand = $invoice->brand_snapshot ?? [];
  if (is_string($brand)) $brand = json_decode($brand, true) ?: [];

  $company = $invoice->company;
  $activeBanks = collect();
  if ($company?->id) {
    $activeBanks = \App\Models\Bank::query()
      ->where('company_id', $company->id)
      ->where('is_active', true)
      ->orderBy('name')
      ->get();
  }
  $co = [
    'name'     => $brand['name'] ?? $brand['alias'] ?? ($company->name ?? ''),
    'address'  => $brand['address'] ?? ($company->address ?? ''),
    'email'    => $brand['email'] ?? ($company->email ?? ''),
    'phone'    => $brand['phone'] ?? ($company->phone ?? ''),
    'whatsapp' => $brand['whatsapp'] ?? ($company->whatsapp ?? ''),
    'logo'     => $brand['logo_path'] ?? ($company->logo_path ?? null),
  ];

  $logoSrc = null;
  if ($co['logo']) {
    if (preg_match('~^https?://~', $co['logo'])) {
      $logoSrc = $co['logo'];
    } else {
      $rel = ltrim($co['logo'], '/');
      $candidates = [
        public_path($rel),
        substr($rel,0,8)==='storage/' ? storage_path('app/public/'.substr($rel,8)) : storage_path('app/public/'.$rel),
        base_path($rel),
      ];
      foreach ($candidates as $p) {
        if (is_file($p)) {
          $ext  = strtolower(pathinfo($p, PATHINFO_EXTENSION) ?: 'png');
          $mime = 'image/'.($ext === 'svg' ? 'svg+xml' : $ext);
          $logoSrc = 'data:'.$mime.';base64,'.base64_encode(@file_get_contents($p));
          break;
        }
      }
    }
  }

  $fmtDate = fn($d) => $d ? \Illuminate\Support\Carbon::parse($d)->format('d M Y') : '-';
@endphp

{{-- Watermark rules:
   - mode=proforma  : tampil "PROFORMA"
   - mode=invoice   : jika posted => "COPY"; jika draft => tanpa watermark --}}
@if(($mode ?? 'invoice') === 'proforma')
  <div class="wm">PROFORMA</div>
@elseif(($mode ?? 'invoice') === 'invoice' && $isPosted)
  <div class="wm">COPY</div>
@endif

<table class="hdr">
  <tr>
    <td class="hdr-left">
      @if($logoSrc)
        <img class="logo-top" src="{{ $logoSrc }}" alt="">
      @endif
      <p class="co-name">{{ $co['name'] }}</p>
      <div class="co-meta">
        @if($co['address'])  <div>{{ $co['address'] }}</div>@endif
        @if($co['phone'])    <div>Telp: {{ $co['phone'] }}</div>@endif
        @if($co['whatsapp']) <div>WA: {{ $co['whatsapp'] }}</div>@endif
        @if($co['email'])    <div>Email: {{ $co['email'] }}</div>@endif
      </div>
    </td>

    <td class="hdr-mid">
      <h2 class="block">Customer</h2>
      <strong>{{ $invoice->customer->name ?? '-' }}</strong><br>
      {{ $invoice->customer->address ?? '' }}
    </td>

    <td class="hdr-right">
      <div class="quo-title">{{ $docTitle }}</div>
      <div class="quo-number"># {{ $docNo }}</div>
      <div class="quo-row"><span class="small">Date:</span> {{ $fmtDate($docDate) }}</div>
      @if(!empty($invoice->due_date))
        <div class="quo-row"><span class="small">Due Date:</span> {{ $fmtDate($invoice->due_date) }}</div>
      @endif
      @if($invoice->salesOrder?->so_number)
        <div class="quo-row"><span class="small">SO No:</span> {{ $invoice->salesOrder->so_number }}</div>
      @endif
    </td>
  </tr>
</table>

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

@if($activeBanks->count())
  <table class="bank-table">
    <thead>
      <tr>
        <th colspan="3">Rekening Bank (Aktif)</th>
      </tr>
      <tr>
        <th>Bank</th>
        <th>No Account</th>
        <th>Nama Account</th>
      </tr>
    </thead>
    <tbody>
      @foreach($activeBanks as $bank)
        <tr>
          <td>{{ $bank->name }}</td>
          <td>{{ $bank->account_no ?: '-' }}</td>
          <td>{{ $bank->account_name ?: '-' }}</td>
        </tr>
      @endforeach
    </tbody>
  </table>
@endif
</body>
</html>
