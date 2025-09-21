<!doctype html>
<html>
<head>
  <meta charset="utf-8">
  <title>Quotation {{ $quotation->number }}</title>
  <style>
    * { font-family: DejaVu Sans, sans-serif; font-size: 12px; color:#111; }
    .right { text-align:right; }
    .small { font-size:11px; color:#555; }

    /* ===== Header 3 kolom ===== */
    .hdr{ width:100%; border-collapse:collapse; margin-bottom:14px; table-layout:fixed; }
    .hdr td{ vertical-align:top; padding:0; word-wrap:break-word; }
    .hdr-left{ width:40%; text-align:left !important; }
    .hdr-mid{  width:35%; padding-left:8px; text-align:left !important; }
    .hdr-right{ width:25%; text-align:right !important; }

    /* logo 2x lebih besar */
    .logo-top{ width:144px; height:auto; margin:0 0 6px 0; }
    .co-name{ margin:0 0 4px; font-size:14px; font-weight:800; text-transform:uppercase; }
    .co-meta div{ line-height:1.35; }

    .quo-title{ margin:0; font-size:20px; font-weight:900; letter-spacing:.3px; }
    .quo-number{ margin:2px 0 6px; font-weight:700; }
    .quo-row{ margin-top:2px; }

    /* ===== Tabel item ===== */
    .grid { width:100%; border-collapse: collapse; }
    .grid th, .grid td { border:1px solid #999; padding:6px; }
    h2.block { font-size:13px; margin:0 0 6px; text-transform:uppercase; letter-spacing:.4px; }
  </style>
</head>
<body>
@php
  // ===== Branding snapshot (fallback ke relasi company) =====
  $brand = $quotation->brand_snapshot ?? [];
  if (is_string($brand)) $brand = json_decode($brand, true) ?: [];

  $co = [
    'name'     => $brand['name']     ?? ($quotation->company->name ?? ''),
    'address'  => $brand['address']  ?? ($quotation->company->address ?? ''),
    'email'    => $brand['email']    ?? ($quotation->company->email ?? ''),
    'phone'    => $brand['phone']    ?? ($quotation->company->phone ?? ''),
    'whatsapp' => $brand['whatsapp'] ?? ($quotation->company->whatsapp ?? ''),
    'logo'     => $brand['logo_path']?? ($quotation->company->logo_path ?? null),
  ];

  // ===== Pastikan logo tampil =====
  $logoSrc = null;
  if ($co['logo']) {
    if (preg_match('~^https?://~', $co['logo'])) {
      $logoSrc = $co['logo']; // butuh isRemoteEnabled=true di dompdf
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

  // ===== Sales Agent (sales_user_id / sales_name / fallback user) =====
  $salesAgent =
      ($quotation->sales_name ?? null)
   ?? optional($quotation->salesUser ?? null)->name
   ?? (isset($quotation->sales_user_id) ? optional(\App\Models\User::find($quotation->sales_user_id))->name : null)
   ?? optional($quotation->sales ?? null)->name
   ?? optional($quotation->user ?? null)->name
   ?? '-';

  $fmtDate = fn($d) => $d ? \Illuminate\Support\Carbon::parse($d)->format('d M Y') : '-';
@endphp

{{-- ===== HEADER: 3 kolom ===== --}}
<table class="hdr">
  <tr>
    {{-- Kiri: Company (tanpa judul "Company"; logo besar di atas nama) --}}
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

    {{-- Tengah: Customer --}}
    <td class="hdr-mid">
      <h2 class="block">Customer</h2>
      <strong>{{ $quotation->customer->name }}</strong><br>
      {{ $quotation->customer->address }}
    </td>

    {{-- Kanan: Quotation info --}}
    <td class="hdr-right">
      <div class="quo-title">QUOTATION</div>
      <div class="quo-number"># {{ $quotation->number }}</div>
      <div class="quo-row"><span class="small">Quotation Date:</span> {{ $fmtDate($quotation->date) }}</div>
      <div class="quo-row"><span class="small">Expiry Date:</span> {{ $fmtDate($quotation->valid_until) }}</div>
      <div class="quo-row"><span class="small">Sales Agent:</span> {{ $salesAgent }}</div>
    </td>
  </tr>
</table>

{{-- ===== GRID ITEM ===== --}}
<table class="grid">
  <thead>
    <tr>
      <th style="width:38%">Item</th>
      <th style="width:10%" class="right">Qty</th>
      <th style="width:10%">Unit</th>
      <th style="width:14%" class="right">Unit Price</th>
      <th style="width:14%" class="right">Discount</th>
      <th style="width:14%" class="right">Line Total</th>
    </tr>
  </thead>
  <tbody>
    @forelse($quotation->lines as $ln)
      <tr>
        <td>
          <strong>{{ $ln->name }}</strong>
          @if($ln->description)<div class="small">{{ $ln->description }}</div>@endif
        </td>
        <td class="right">{{ rtrim(rtrim(number_format((float)$ln->qty, 2, '.', ''), '0'), '.') }}</td>
        <td>{{ $ln->unit }}</td>
        <td class="right">{{ number_format((float)$ln->unit_price, 2, ',', '.') }}</td>
        <td class="right">
          @if(($ln->discount_type ?? 'amount') === 'percent')
            {{ rtrim(rtrim(number_format((float)$ln->discount_value, 2, '.', ''), '0'), '.') }}%
          @else
            {{ number_format((float)($ln->discount_amount ?? 0), 2, ',', '.') }}
          @endif
        </td>
        <td class="right">{{ number_format((float)$ln->line_total, 2, ',', '.') }}</td>
      </tr>
    @empty
      <tr><td colspan="6" class="right">No lines.</td></tr>
    @endforelse
  </tbody>
</table>

{{-- ===== TOTALS ===== --}}
<table style="width:100%; margin-top:10px;">
  <tr>
    <td style="width:60%"></td>
    <td>
      <table style="width:100%;">
        <tr><td>Subtotal</td><td class="right">{{ number_format((float)$quotation->lines_subtotal, 2, ',', '.') }}</td></tr>
        @if(($quotation->total_discount_amount ?? 0) > 0)
          <tr><td>Discount</td><td class="right">-{{ number_format((float)$quotation->total_discount_amount, 2, ',', '.') }}</td></tr>
        @endif
        <tr><td>Taxable</td><td class="right">{{ number_format((float)$quotation->taxable_base, 2, ',', '.') }}</td></tr>
        <tr>
          <td>Tax ({{ rtrim(rtrim(number_format((float)$quotation->tax_percent, 2, '.', ''), '0'), '.') }}%)</td>
          <td class="right">{{ number_format((float)$quotation->tax_amount, 2, ',', '.') }}</td>
        </tr>
        <tr>
          <td><strong>Total</strong></td>
          <td class="right"><strong>{{ $quotation->total_idr ?? number_format((float)$quotation->total, 2, ',', '.') }}</strong></td>
        </tr>
      </table>
    </td>
  </tr>
</table>

@if($quotation->notes)
  <p class="small" style="margin-top:12px"><strong>Notes:</strong><br>{{ $quotation->notes }}</p>
@endif
</body>
</html>
