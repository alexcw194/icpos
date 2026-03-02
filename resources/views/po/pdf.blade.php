<!doctype html>
<html>
<head>
  <meta charset="utf-8">
  <title>{{ $po->status === 'draft' ? ('PO Draft #' . $po->id) : ('PO ' . $po->number) }}</title>
  <style>
    * { font-family: DejaVu Sans, sans-serif; font-size: 12px; color:#111; }
    .right { text-align:right; }
    .small { font-size:11px; color:#555; }

    .hdr{ width:100%; border-collapse:collapse; margin-bottom:14px; table-layout:fixed; }
    .hdr td{ vertical-align:top; padding:0; word-wrap:break-word; }
    .hdr-left{ width:40%; text-align:left !important; }
    .hdr-mid{  width:35%; padding-left:8px; text-align:left !important; }
    .hdr-right{ width:25%; text-align:right !important; }

    .logo-top{ width:144px; height:auto; margin:0 0 6px 0; }
    .co-name{ margin:0 0 4px; font-size:14px; font-weight:800; text-transform:uppercase; }
    .co-meta div{ line-height:1.35; }

    .doc-title{ margin:0; font-size:20px; font-weight:900; letter-spacing:.3px; }
    .doc-number{ margin:2px 0 6px; font-weight:700; }
    .doc-row{ margin-top:2px; }

    .grid { width:100%; border-collapse: collapse; }
    .grid th, .grid td { border:1px solid #999; padding:6px; }
    .terms th, .terms td { padding:4px; }

    .sign-wrap { position:relative; height:90px; margin:8px 0 6px; text-align:left; }
    .sign-layer { position:absolute; left:0; top:50%; transform:translateY(-50%); }
    .sign-layer.stamp { left:24px; }
    .sign-layer.signature { z-index:1; }
    .sign-layer.stamp { z-index:2; }
    h2.block { font-size:13px; margin:0 0 6px; text-transform:uppercase; letter-spacing:.4px; }

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
  $isApproved = $po->status === 'approved';
  $numberText = $isDraft ? ('DRAFT-' . $po->id) : ($po->number ?: ('PO-' . $po->id));
  $fmtDate = fn($d) => $d ? \Illuminate\Support\Carbon::parse($d)->format('d M Y') : '-';

  $co = [
    'name' => $po->company->name ?? '',
    'address' => $po->company->address ?? '',
    'email' => $po->company->email ?? '',
    'phone' => $po->company->phone ?? '',
    'whatsapp' => $po->company->whatsapp ?? '',
    'logo' => $po->company->logo_path ?? null,
  ];

  $logoSrc = null;
  if (!empty($co['logo'])) {
    if (preg_match('~^https?://~', $co['logo'])) {
      $logoSrc = $co['logo'];
    } else {
      $rel = ltrim((string) $co['logo'], '/');
      $candidates = [
        public_path($rel),
        str_starts_with($rel, 'storage/') ? storage_path('app/public/' . substr($rel, 8)) : storage_path('app/public/' . $rel),
        base_path($rel),
      ];
      foreach ($candidates as $imgPath) {
        if (is_file($imgPath)) {
          $ext  = strtolower(pathinfo($imgPath, PATHINFO_EXTENSION) ?: 'png');
          $mime = 'image/' . ($ext === 'svg' ? 'svg+xml' : $ext);
          $logoSrc = 'data:' . $mime . ';base64,' . base64_encode((string) @file_get_contents($imgPath));
          break;
        }
      }
    }
  }

  $taxAmount = (float) ($po->tax_amount ?? 0);
  $subtotal = (float) ($po->subtotal ?? 0);
  $total = (float) ($po->total ?? 0);
  $showTaxPercentLabel = (bool) ($po->company->show_tax_percent_on_pdf ?? true);
  $isTaxIncluded = $taxAmount > 0 && abs($total - $subtotal) < 0.01;

  $stampPath = \App\Models\Setting::get('documents.stamp_path');
  $directorSignaturePath = \App\Models\Setting::get('documents.director_signature_path');
  $resolveImage = function (?string $path): ?string {
    if (!$path) return null;
    if (preg_match('~^https?://~', $path)) return $path;
    $rel = ltrim($path, '/');
    $candidates = [
      public_path($rel),
      str_starts_with($rel, 'storage/') ? storage_path('app/public/' . substr($rel, 8)) : storage_path('app/public/' . $rel),
      base_path($rel),
    ];
    foreach ($candidates as $imgPath) {
      if (is_file($imgPath)) {
        $ext  = strtolower(pathinfo($imgPath, PATHINFO_EXTENSION) ?: 'png');
        $mime = 'image/' . ($ext === 'svg' ? 'svg+xml' : $ext);
        return 'data:' . $mime . ';base64,' . base64_encode((string) @file_get_contents($imgPath));
      }
    }
    return null;
  };
  $stampSrc = $resolveImage($stampPath);
  $directorSignatureSrc = $resolveImage($directorSignaturePath);
@endphp

<table class="hdr">
  <tr>
    <td class="hdr-left">
      @if($logoSrc)
        <img class="logo-top" src="{{ $logoSrc }}" alt="">
      @endif
      <p class="co-name">{{ $co['name'] ?: '-' }}</p>
      <div class="co-meta">
        @if($co['address'])  <div>{{ $co['address'] }}</div>@endif
        @if($co['phone'])    <div>Telp: {{ $co['phone'] }}</div>@endif
        @if($co['whatsapp']) <div>WA: {{ $co['whatsapp'] }}</div>@endif
        @if($co['email'])    <div>Email: {{ $co['email'] }}</div>@endif
      </div>
    </td>

    <td class="hdr-mid">
      <h2 class="block">Supplier</h2>
      <strong>{{ $po->supplier->name ?? '-' }}</strong>
      @if(!empty($po->supplier->address))
        <div>{{ $po->supplier->address }}</div>
      @endif
      @if(!empty($po->supplier->phone) || !empty($po->supplier->email))
        <div class="small" style="margin-top:6px;">
          @if(!empty($po->supplier->phone))<div>Telp: {{ $po->supplier->phone }}</div>@endif
          @if(!empty($po->supplier->email))<div>Email: {{ $po->supplier->email }}</div>@endif
        </div>
      @endif
    </td>

    <td class="hdr-right">
      <div class="doc-title">PURCHASE ORDER</div>
      <div class="doc-number"># {{ $numberText }}</div>
      <div class="doc-row"><span class="small">PO Date:</span> {{ $fmtDate($po->order_date) }}</div>
      @if($isDraft)
        <div class="draft-badge">DRAFT</div>
      @endif
    </td>
  </tr>
</table>

<table class="grid">
  <thead>
    <tr>
      <th style="width:40%">Item</th>
      <th style="width:12%">Variant</th>
      <th style="width:10%" class="right">Qty</th>
      <th style="width:10%">UoM</th>
      <th style="width:14%" class="right">Unit Price</th>
      <th style="width:14%" class="right">Line Total</th>
    </tr>
  </thead>
  <tbody>
    @forelse($po->lines as $ln)
      @php
        $qty = (float) ($ln->qty_ordered ?? 0);
        $lineTotal = (float) ($ln->line_total ?? ($qty * (float) ($ln->unit_price ?? 0)));
      @endphp
      <tr>
        <td>
          <strong>{{ $ln->sku_snapshot ?? ($ln->item->sku ?? '') }}</strong>
          <div class="small">{{ $ln->item_name_snapshot ?? ($ln->item->name ?? '-') }}</div>
        </td>
        <td>{{ $ln->variant->sku ?? '-' }}</td>
        <td class="right">{{ rtrim(rtrim(number_format($qty, 2, '.', ''), '0'), '.') }}</td>
        <td>{{ $ln->uom ?? '-' }}</td>
        <td class="right">{{ number_format((float)($ln->unit_price ?? 0), 2, ',', '.') }}</td>
        <td class="right">{{ number_format($lineTotal, 2, ',', '.') }}</td>
      </tr>
    @empty
      <tr><td colspan="6" class="right">No lines.</td></tr>
    @endforelse
  </tbody>
</table>

<table style="width:100%; margin-top:10px;">
  <tr>
    <td style="width:60%"></td>
    <td>
      <table style="width:100%;">
        <tr>
          <td>Subtotal</td>
          <td class="right">{{ number_format($subtotal, 2, ',', '.') }}</td>
        </tr>
        @if(!$isTaxIncluded && $taxAmount > 0)
          <tr>
            <td>PPN@if($showTaxPercentLabel) ({{ rtrim(rtrim(number_format((float)($po->tax_percent ?? 0), 2, '.', ''), '0'), '.') }}%)@endif</td>
            <td class="right">{{ number_format($taxAmount, 2, ',', '.') }}</td>
          </tr>
        @endif
        <tr>
          <td><strong>{{ $isTaxIncluded ? 'Total (Termasuk Pajak)' : 'Total' }}</strong></td>
          <td class="right"><strong>{{ number_format($total, 2, ',', '.') }}</strong></td>
        </tr>
      </table>
    </td>
  </tr>
</table>

<table style="width:100%; margin-top:12px;">
  <tr>
    <td style="width:55%; vertical-align:top;">
      <h2 class="block">Notes</h2>
      @if(!empty($po->notes))
        <div class="small">{!! nl2br(e($po->notes)) !!}</div>
      @else
        <div class="small">-</div>
      @endif
    </td>
    <td style="width:45%; vertical-align:top;">
      @if($po->billingTerms->isNotEmpty())
        <h2 class="block">Billing Terms</h2>
        <table class="grid terms">
          <thead>
            <tr>
              <th style="width:20%">Code</th>
              <th style="width:15%" class="right">Percent</th>
              <th>Schedule</th>
            </tr>
          </thead>
          <tbody>
            @foreach($po->billingTerms as $term)
              @php
                $schedule = (string) ($term->due_trigger ?? '-');
                if ($term->offset_days !== null) {
                    $schedule .= ' (' . $term->offset_days . 'd)';
                } elseif ($term->day_of_month !== null) {
                    $schedule .= ' (day ' . $term->day_of_month . ')';
                }
                if (!empty($term->note)) {
                    $schedule .= ' - ' . $term->note;
                }
              @endphp
              <tr>
                <td>{{ $term->top_code }}</td>
                <td class="right">{{ number_format((float)$term->percent, 2, ',', '.') }}%</td>
                <td>{{ $schedule }}</td>
              </tr>
            @endforeach
          </tbody>
        </table>
      @endif

      <div style="margin-top:16px;">Hormat Kami,</div>
      <div class="sign-wrap">
        @if($isApproved && $directorSignatureSrc)
          <img src="{{ $directorSignatureSrc }}" alt="" class="sign-layer signature" style="height:60px;">
        @endif
        @if($isApproved && $stampSrc)
          <img src="{{ $stampSrc }}" alt="" class="sign-layer stamp" style="height:60px;">
        @endif
      </div>
      <div style="font-weight:700; text-decoration:underline;">Christian Widargo</div>
      <div class="small">Direktur Utama</div>
    </td>
  </tr>
</table>
</body>
</html>
