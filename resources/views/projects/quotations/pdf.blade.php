<!doctype html>
<html>
<head>
  <meta charset="utf-8">
  <title>BQ {{ $quotation->number }}</title>
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

    .logo-top{ width:144px; height:auto; margin:0 0 6px 0; }
    .co-name{ margin:0 0 4px; font-size:14px; font-weight:800; text-transform:uppercase; }
    .co-meta div{ line-height:1.35; }

    .quo-title{ margin:0; font-size:20px; font-weight:900; letter-spacing:.3px; }
    .quo-number{ margin:2px 0 6px; font-weight:700; }
    .quo-row{ margin-top:2px; }

    .grid { width:100%; border-collapse: collapse; }
    .grid th, .grid td { border:1px solid #999; padding:6px; font-size:11px; }
    .section-row td { background:#f3f3f3; font-weight:700; }
    .terms th, .terms td { padding:4px; font-size:11px; }
    .notes-box { font-size:11px; line-height:1.2; white-space:pre-line; }
    .notes-table { width:100%; border-collapse:collapse; margin:-2px 0 0; }
    .notes-table td { padding:0; vertical-align:top; }
    .notes-num { width:18px; }
    .notes-row { line-height:1.2; }
    .sign-wrap { position:relative; height:90px; margin:8px 0 6px; text-align:left; }
    .sign-layer { position:absolute; left:0; top:50%; transform:translateY(-50%); }
    .sign-layer.stamp { left:24px; }
    .sign-layer.signature { z-index:1; }
    .sign-layer.stamp { z-index:2; }
    h2.block { font-size:13px; margin:0 0 6px; text-transform:uppercase; letter-spacing:.4px; }
  </style>
</head>
<body>
@php
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

  $salesOwner = $quotation->salesOwner;
  $salesAgent = $salesOwner->name ?? '-';
  $salesOwnerPhone = $salesOwner->phone ?? '';
  $salesOwnerEmail = $salesOwner->email ?? '';
  $fmtDate = fn($d) => $d ? \Illuminate\Support\Carbon::parse($d)->format('d M Y') : '-';
  $validUntil = $quotation->quotation_date
    ? \Illuminate\Support\Carbon::parse($quotation->quotation_date)->addDays((int) ($quotation->validity_days ?? 0))
    : null;
  $workingTime = ($quotation->working_time_days ? $quotation->working_time_days.' hari' : '-')
    .' @ '.($quotation->working_time_hours_per_day ?? '-').' jam/hari';

  $directorName = 'Christian Widargo';
  $isDirector = $quotation->signatory_name
    && strcasecmp($quotation->signatory_name, $directorName) === 0;

  $stampPath = \App\Models\Setting::get('documents.stamp_path');
  $directorSignaturePath = \App\Models\Setting::get('documents.director_signature_path');

  $resolveImage = function (?string $path): ?string {
    if (!$path) return null;
    if (preg_match('~^https?://~', $path)) return $path;
    $rel = ltrim($path, '/');
    $candidates = [
      public_path($rel),
      substr($rel,0,8)==='storage/' ? storage_path('app/public/'.substr($rel,8)) : storage_path('app/public/'.$rel),
      base_path($rel),
    ];
    foreach ($candidates as $p) {
      if (is_file($p)) {
        $ext  = strtolower(pathinfo($p, PATHINFO_EXTENSION) ?: 'png');
        $mime = 'image/'.($ext === 'svg' ? 'svg+xml' : $ext);
        return 'data:'.$mime.';base64,'.base64_encode(@file_get_contents($p));
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
      <strong>{{ $quotation->customer->name }}</strong><br>
      {{ $quotation->customer->address }}
      <div class="small" style="margin-top:6px;">
        <div><strong>To:</strong> {{ $quotation->to_name }}</div>
        <div><strong>Attn:</strong> {{ $quotation->attn_name ?: '-' }}</div>
      </div>
    </td>

    <td class="hdr-right">
      <div class="quo-title">QUOTATION</div>
      <div class="quo-number"># {{ $quotation->number }}</div>
      <div class="quo-row"><span class="small">BQ Date:</span> {{ $fmtDate($quotation->quotation_date) }}</div>
      <div class="quo-row"><span class="small">Expiry Date:</span> {{ $fmtDate($validUntil) }}</div>
      <div class="quo-row"><span class="small">Sales Owner:</span> {{ $salesAgent }}</div>
      @if($salesOwnerPhone !== '' || $salesOwnerEmail !== '')
        <div class="quo-row">
          <span class="small">HP / Email:</span>
          {{ trim(($salesOwnerPhone ?: '-').' - '.($salesOwnerEmail ?: '-')) }}
        </div>
      @endif
      <div class="quo-row"><span class="small">Working Time:</span> {{ $workingTime }}</div>
    </td>
  </tr>
</table>

<div style="margin-bottom:10px;">
  <strong>Project Title:</strong> {{ $quotation->project_title }}
</div>

<table class="grid">
  <thead>
    <tr>
      <th style="width:32%">Description</th>
      <th style="width:8%" class="right">Qty</th>
      <th style="width:8%">Unit</th>
      <th style="width:14%" class="right">Unit Price</th>
      <th style="width:14%" class="right">Material</th>
      <th style="width:14%" class="right">Labor</th>
      <th style="width:10%" class="right">Line Total</th>
    </tr>
  </thead>
  <tbody>
    @forelse($quotation->sections as $section)
      <tr class="section-row">
        <td colspan="7">{{ $section->name }}</td>
      </tr>
      @foreach($section->lines as $ln)
        @php
          $lineTotal = $ln->line_total ?? ((float) $ln->material_total + (float) $ln->labor_total);
        @endphp
        <tr>
          <td>{{ $ln->description }}</td>
          <td class="right">{{ rtrim(rtrim(number_format((float)$ln->qty, 2, '.', ''), '0'), '.') }}</td>
          <td>{{ $ln->unit }}</td>
          <td class="right">{{ number_format((float)$ln->unit_price, 2, ',', '.') }}</td>
          <td class="right">{{ number_format((float)$ln->material_total, 2, ',', '.') }}</td>
          <td class="right">{{ number_format((float)$ln->labor_total, 2, ',', '.') }}</td>
          <td class="right">{{ number_format((float)$lineTotal, 2, ',', '.') }}</td>
        </tr>
      @endforeach
    @empty
      <tr><td colspan="7" class="right">No lines.</td></tr>
    @endforelse
  </tbody>
</table>

<table style="width:100%; margin-top:10px;">
  <tr>
    <td style="width:60%"></td>
    <td>
      <table style="width:100%;">
        <tr><td>Subtotal Material</td><td class="right">{{ number_format((float)$quotation->subtotal_material, 2, ',', '.') }}</td></tr>
        <tr><td>Subtotal Labor</td><td class="right">{{ number_format((float)$quotation->subtotal_labor, 2, ',', '.') }}</td></tr>
        <tr><td>Subtotal</td><td class="right">{{ number_format((float)$quotation->subtotal, 2, ',', '.') }}</td></tr>
        <tr>
          <td>Tax ({{ rtrim(rtrim(number_format((float)$quotation->tax_percent, 2, '.', ''), '0'), '.') }}%)</td>
          <td class="right">{{ number_format((float)$quotation->tax_amount, 2, ',', '.') }}</td>
        </tr>
        <tr>
          <td><strong>Grand Total</strong></td>
          <td class="right"><strong>{{ number_format((float)$quotation->grand_total, 2, ',', '.') }}</strong></td>
        </tr>
      </table>
    </td>
  </tr>
</table>

<table style="width:100%; margin-top:12px;">
  <tr>
    <td style="width:55%; vertical-align:top;">
      <h2 class="block">Notes</h2>
      @if($quotation->notes)
        @php
          $noteText = preg_replace("/\\r\\n|\\r/", "\n", trim((string) $quotation->notes));
          $noteText = preg_replace("/\\n\\s*\\n+/", "\n", $noteText);
          $noteLines = preg_split("/\\n/", $noteText);
          $noteLines = array_values(array_filter($noteLines, function ($line) {
            return trim($line) !== '';
          }));
          $isList = count($noteLines) > 0 && collect($noteLines)->every(function ($line) {
            return preg_match('/^\\s*\\d+[\\).]/', $line) === 1;
          });
        @endphp
        @if($isList)
          <table class="notes-table">
            @foreach($noteLines as $line)
              @php
                $num = '';
                $text = $line;
                if (preg_match('/^\\s*(\\d+)[\\).]\\s*(.*)$/', $line, $m)) {
                  $num = $m[1].'.';
                  $text = $m[2];
                }
                $text = trim((string) $text);
              @endphp
              <tr class="notes-row">
                <td class="notes-num">{{ $num }}</td>
                <td>{{ $text }}</td>
              </tr>
            @endforeach
          </table>
        @else
          <div class="notes-box">{!! nl2br(e($noteText)) !!}</div>
        @endif
      @else
        <div class="notes-box">-</div>
      @endif
    </td>
    <td style="width:45%; vertical-align:top;">
      @if($quotation->paymentTerms->isNotEmpty())
        <h2 class="block">Payment Terms</h2>
        <table class="grid terms">
          <thead>
            <tr>
              <th style="width:20%">Code</th>
              <th style="width:15%" class="right">Percent</th>
              <th>Schedule</th>
            </tr>
          </thead>
          <tbody>
            @foreach($quotation->paymentTerms as $term)
              <tr>
                <td>{{ $term->code }}</td>
                <td class="right">{{ number_format((float)$term->percent, 2, ',', '.') }}%</td>
                <td>{{ $term->trigger_note ?: ($term->due_trigger ?? '-') }}</td>
              </tr>
            @endforeach
          </tbody>
        </table>
      @endif

      <div style="margin-top:16px;">Hormat Kami,</div>
      @if($isDirector)
        <div class="sign-wrap">
          @if($directorSignatureSrc)
            <img src="{{ $directorSignatureSrc }}" alt="" class="sign-layer signature" style="height:60px;">
          @endif
          @if($stampSrc)
            <img src="{{ $stampSrc }}" alt="" class="sign-layer stamp" style="height:60px;">
          @endif
        </div>
      @endif

      @if($quotation->signatory_name)
        <div style="margin-top:10px;">
          <div>{{ $quotation->signatory_name }}</div>
          <div class="small">{{ $quotation->signatory_title }}</div>
        </div>
      @endif
    </td>
  </tr>
</table>
</body>
</html>
