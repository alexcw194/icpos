{{-- resources/views/documents/pdf.blade.php --}}
@php
  $signatures = $document->signatures ?? [];
  $salesSig = $signatures['sales'] ?? null;
  $approverSig = $signatures['approver'] ?? null;
  $directorSig = $signatures['director'] ?? null;

  $pdfNumber = $document->number ?: ('DRAFT-' . $document->id);
  $dateText = ($document->approved_at ?? $document->submitted_at ?? $document->created_at)?->format('d M Y') ?? '';

  $makeSrc = function ($path) {
      if (!$path) return null;
      return str_starts_with($path, 'http') ? $path : asset('storage/'.$path);
  };

  $signerIsDirector = empty($document->sales_signer_user_id);
  $signerName = $signerIsDirector
      ? 'Christian Widargo'
      : ($salesSig['name'] ?? $document->salesSigner?->name ?? $document->creator?->name);
  $signerPosition = $signerIsDirector
      ? 'DIREKTUR UTAMA'
      : ($salesSig['position'] ?? $document->sales_signature_position ?? '');
  $signerImage = null;
  if ($signerIsDirector) {
      $signerImage = $document->approved_at
          ? $makeSrc($directorSig['image_path'] ?? null)
          : null;
  } else {
      $signerImage = $document->admin_approved_at
          ? $makeSrc($salesSig['image_path'] ?? null)
          : null;
  }
@endphp
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>{{ $pdfNumber }}</title>
  <style>
    @page { margin: 0; }
    body {
      margin: 0;
      font-family: DejaVu Sans, Arial, sans-serif;
      color: #1f2937;
      font-size: 12px;
      line-height: 1.5;
    }
    .page {
      position: relative;
      padding: 120px 60px 80px;
      min-height: 100vh;
    }
    .letterhead {
      position: fixed;
      top: 0;
      left: 0;
      width: 100%;
      z-index: 0;
    }
    .content {
      position: relative;
      z-index: 1;
    }
    .meta {
      display: flex;
      justify-content: space-between;
      margin-bottom: 24px;
    }
    .text-muted {
      color: #6b7280;
    }
    .doc-header {
      text-align: center;
      margin-bottom: 18px;
    }
    .doc-title {
      font-weight: 700;
      font-size: 13px;
      line-height: 1.4;
      max-width: 70%;
      margin: 0 auto 6px;
    }
    .doc-number {
      font-weight: 700;
      font-size: 12px;
      margin-bottom: 2px;
    }
    .doc-date {
      font-size: 12px;
    }
    .recipient {
      margin: 18px 0 18px;
    }
    .recipient .line {
      margin: 0 0 2px;
    }
    .recipient .name {
      font-weight: 700;
      font-size: 12.5px;
    }
    .body {
      margin-bottom: 40px;
    }
    .closing-row {
      display: flex;
      justify-content: space-between;
      align-items: flex-end;
      gap: 16px;
      margin-top: 14px;
    }
    .signature-block {
      width: 65%;
    }
    .signature-space {
      height: 130px;
    }
    .signature-space img {
      max-height: 90px;
    }
    .signature-name {
      font-weight: 700;
      margin-top: 4px;
    }
    .stamp-block {
      width: 30%;
      text-align: right;
    }
    .stamp {
      display: inline-block;
      padding: 6px 10px;
      border: 2px solid #d32f2f;
      color: #d32f2f;
      font-weight: 700;
      border-radius: 999px;
      transform: rotate(-8deg);
    }
  </style>
</head>
<body>
  @if($letterheadPath)
    <img src="{{ $letterheadPath }}" class="letterhead" alt="Letterhead">
  @endif

  <div class="page">
    <div class="content">
      <div class="doc-header">
        <div class="doc-title">{{ $document->title }}</div>
        <div class="doc-number">{{ $pdfNumber }}</div>
        <div class="doc-date">{{ $dateText }}</div>
      </div>

      <div class="recipient">
        <div class="line">Kepada Yth.</div>
        <div class="line name">{{ data_get($document->customer_snapshot, 'name') }}</div>
        @if($document->contact_snapshot)
          <div class="line">{{ data_get($document->contact_snapshot, 'name') }}</div>
        @endif
      </div>

      <div class="body">
        {!! $document->body_html !!}
      </div>

      <div class="closing-row">
        <div class="signature-block">
          <div>Hormat Kami,</div>
          <div class="signature-space">
            @if($signerImage)
              <img src="{{ $signerImage }}" alt="Signature">
            @endif
          </div>
          <div class="signature-name">{{ $signerName }}</div>
          <div>{{ $signerPosition }}</div>
        </div>
        @if($document->approved_at)
          <div class="stamp-block">
            @if($stampPath)
              <img src="{{ $stampPath }}" alt="ICP Stamp" style="max-height: 80px;">
            @else
              <span class="stamp">ICP OFFICIAL</span>
            @endif
          </div>
        @endif
      </div>
    </div>
  </div>
</body>
</html>
