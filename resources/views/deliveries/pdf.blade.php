<!doctype html>
<html>
<head>
  <meta charset="utf-8">
  <title>Delivery Order {{ $delivery->number ?? ('DRAFT-'.$delivery->id) }}</title>
  <style>
    * { font-family: DejaVu Sans, sans-serif; font-size: 12px; color:#111; }
    .right { text-align:right; }
    .small { font-size:11px; color:#555; }

    .hdr{ width:100%; border-collapse:collapse; margin-bottom:14px; table-layout:fixed; }
    .hdr td{ vertical-align:top; padding:0; word-wrap:break-word; }
    .hdr-left{ width:40%; text-align:left !important; }
    .hdr-mid{ width:35%; padding-left:8px; text-align:left !important; }
    .hdr-right{ width:25%; text-align:right !important; }

    .logo-top{ width:144px; height:auto; margin:0 0 6px 0; }
    .co-name{ margin:0 0 4px; font-size:14px; font-weight:800; text-transform:uppercase; }
    .co-meta div{ line-height:1.35; }

    .doc-title{ margin:0; font-size:20px; font-weight:900; letter-spacing:.3px; }
    .doc-number{ margin:2px 0 6px; font-weight:700; }
    .doc-row{ margin-top:2px; }
    h2.block { font-size:13px; margin:0 0 6px; text-transform:uppercase; letter-spacing:.4px; }

    .meta-table { width:100%; margin-bottom:18px; border-collapse:collapse; }
    .meta-table th, .meta-table td { text-align:left; padding:5px 6px; border-bottom:1px solid #ddd; vertical-align:top; }

    .items { width:100%; border-collapse:collapse; }
    .items th { background:#f2f2f2; }
    .items th, .items td { border:1px solid #ccc; padding:6px; vertical-align:top; }

    .signature-wrap { margin-top:26px; }
    .signature-table { width:100%; border-collapse:collapse; table-layout:fixed; }
    .signature-table td { padding:0; vertical-align:bottom; }
    .sig-left { width:38%; text-align:center; }
    .sig-gap { width:24%; }
    .sig-right { width:38%; text-align:center; }
    .sig-space { height:80px; }
    .sig-line { border-top:1px solid #111; height:0; }
    .sig-label { margin-top:6px; font-size:12px; }
  </style>
</head>
<body>
  @php
    $brand = $delivery->brand_snapshot ?? [];
    if (is_string($brand)) $brand = json_decode($brand, true) ?: [];

    $company = $delivery->company;
    $co = [
      'name' => $brand['name'] ?? $brand['alias'] ?? ($company->name ?? ''),
      'address' => $brand['address'] ?? ($company->address ?? ''),
      'email' => $brand['email'] ?? ($company->email ?? ''),
      'phone' => $brand['phone'] ?? ($company->phone ?? ''),
      'whatsapp' => $brand['whatsapp'] ?? ($company->whatsapp ?? ''),
      'logo' => $brand['logo_path'] ?? ($company->logo_path ?? null),
    ];

    $logoSrc = null;
    if ($co['logo']) {
      if (preg_match('~^https?://~', $co['logo'])) {
        $logoSrc = $co['logo'];
      } else {
        $rel = ltrim($co['logo'], '/');
        $candidates = [
          public_path($rel),
          substr($rel, 0, 8) === 'storage/' ? storage_path('app/public/'.substr($rel, 8)) : storage_path('app/public/'.$rel),
          base_path($rel),
        ];
        foreach ($candidates as $p) {
          if (is_file($p)) {
            $ext = strtolower(pathinfo($p, PATHINFO_EXTENSION) ?: 'png');
            $mime = 'image/'.($ext === 'svg' ? 'svg+xml' : $ext);
            $logoSrc = 'data:'.$mime.';base64,'.base64_encode((string) @file_get_contents($p));
            break;
          }
        }
      }
    }

    $docNo = $delivery->number ?? ('DRAFT-'.$delivery->id);
    $fmtDate = fn($d) => $d ? \Illuminate\Support\Carbon::parse($d)->format('d M Y') : '-';
  @endphp

  <table class="hdr">
    <tr>
      <td class="hdr-left">
        @if($logoSrc)
          <img class="logo-top" src="{{ $logoSrc }}" alt="">
        @endif
        <p class="co-name">{{ $co['name'] }}</p>
        <div class="co-meta">
          @if($co['address']) <div>{{ $co['address'] }}</div>@endif
          @if($co['phone']) <div>Telp: {{ $co['phone'] }}</div>@endif
          @if($co['whatsapp']) <div>WA: {{ $co['whatsapp'] }}</div>@endif
          @if($co['email']) <div>Email: {{ $co['email'] }}</div>@endif
        </div>
      </td>

      <td class="hdr-mid">
        <h2 class="block">Customer</h2>
        <strong>{{ $delivery->customer->name ?? '-' }}</strong><br>
        {{ $delivery->address ?? ($delivery->customer->address ?? '') }}
      </td>

      <td class="hdr-right">
        <div class="doc-title">DELIVERY ORDER</div>
        <div class="doc-number"># {{ $docNo }}</div>
        <div class="doc-row"><span class="small">Date:</span> {{ $fmtDate($delivery->date ?? $delivery->created_at) }}</div>
        @if($delivery->reference)
          <div class="doc-row"><span class="small">Reference:</span> {{ $delivery->reference }}</div>
        @endif
      </td>
    </tr>
  </table>

  <table class="meta-table">
    <tr>
      <th width="20%">Customer</th>
      <td width="30%">{{ $delivery->customer->name ?? '-' }}</td>
      <th width="20%">Warehouse</th>
      <td width="30%">{{ $delivery->warehouse->name ?? '-' }}</td>
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
        <th width="10%" class="right">Qty</th>
        <th width="10%">Unit</th>
        <th width="20%">Notes</th>
      </tr>
    </thead>
    <tbody>
      @foreach($delivery->lines as $index => $line)
        <tr>
          <td class="right">{{ $index + 1 }}</td>
          <td>{{ $line->description ?: ($line->item->name ?? '-') }}</td>
          <td>{{ $line->variant->name ?? '-' }}</td>
          <td class="right">{{ number_format((float) $line->qty, 2) }}</td>
          <td>{{ $line->unit ?? '-' }}</td>
          <td>{{ $line->line_notes ?? '-' }}</td>
        </tr>
      @endforeach
    </tbody>
  </table>

  <div class="signature-wrap">
    <table class="signature-table">
      <tr>
        <td class="sig-left">
          <div class="sig-space"></div>
          <div class="sig-line"></div>
          <div class="sig-label">Pengirim</div>
        </td>
        <td class="sig-gap"></td>
        <td class="sig-right">
          <div class="sig-space"></div>
          <div class="sig-line"></div>
          <div class="sig-label">Penerima</div>
        </td>
      </tr>
    </table>
  </div>
</body>
</html>
