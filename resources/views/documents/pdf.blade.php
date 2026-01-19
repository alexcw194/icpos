{{-- resources/views/documents/pdf.blade.php --}}
@php
  $signatures = $document->signatures ?? [];
  $salesSig = $signatures['sales'] ?? null;
  $approverSig = $signatures['approver'] ?? null;
  $directorSig = $signatures['director'] ?? null;
  $hasSalesSig = !empty($salesSig['image_path']);
  $hasApproverSig = !empty($approverSig['image_path']);
  $hasDirectorSig = !empty($directorSig['image_path']);

  $pdfNumber = $document->number ?: ('DRAFT-' . $document->id);
  $dateText = ($document->approved_at ?? $document->submitted_at ?? $document->created_at)?->format('d M Y') ?? '';

  $makeSrc = function ($path) {
      if (!$path) return null;
      return str_starts_with($path, 'http') ? $path : asset('storage/'.$path);
  };
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
    .meta .number {
      font-weight: 700;
      font-size: 13px;
    }
    .text-muted {
      color: #6b7280;
    }
    .recipient {
      margin-bottom: 18px;
    }
    .recipient .name {
      font-weight: 700;
      font-size: 13px;
    }
    .body {
      margin-bottom: 40px;
    }
    .sign-row {
      display: flex;
      justify-content: space-between;
      align-items: flex-end;
      gap: 24px;
    }
    .sign-col {
      width: 45%;
      text-align: left;
    }
    .sign-col.right {
      text-align: right;
    }
    .sign-col img {
      max-height: 70px;
      margin-bottom: 6px;
    }
    .sign-name {
      font-weight: 700;
      margin-top: 6px;
    }
    .stamp {
      margin-top: 8px;
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
      <div class="meta">
        <div>
          <div class="number">{{ $pdfNumber }}</div>
          <div class="text-muted">{{ $document->title }}</div>
        </div>
        <div>{{ $dateText }}</div>
      </div>

      <div class="recipient">
        <div class="name">{{ data_get($document->customer_snapshot, 'name') }}</div>
        <div>{{ data_get($document->customer_snapshot, 'address') }}</div>
        <div>
          {{ data_get($document->customer_snapshot, 'city') }}
          @if(data_get($document->customer_snapshot, 'province'))
            , {{ data_get($document->customer_snapshot, 'province') }}
          @endif
        </div>
        @if($document->contact_snapshot)
          <div>Up. {{ data_get($document->contact_snapshot, 'name') }}</div>
        @endif
      </div>

      <div class="body">
        {!! $document->body_html !!}
      </div>

      @if($document->admin_approved_at && ($hasSalesSig || $hasApproverSig))
        <div class="sign-row">
          @if($hasSalesSig)
            <div class="sign-col">
              <img src="{{ $makeSrc($salesSig['image_path']) }}" alt="Sales Signature">
              <div class="sign-name">{{ $salesSig['name'] ?? $document->salesSigner?->name ?? $document->creator?->name }}</div>
              <div>{{ $salesSig['position'] ?? '' }}</div>
            </div>
          @endif
          @if($hasApproverSig)
            <div class="sign-col right">
              <img src="{{ $makeSrc($approverSig['image_path']) }}" alt="Approver Signature">
              <div class="sign-name">{{ $document->adminApprover?->name }}</div>
              <div>{{ $approverSig['position'] ?? '' }}</div>
            </div>
          @endif
        </div>
      @endif

      @if($document->approved_at)
        <div class="sign-row" style="margin-top: 30px;">
          @if($hasDirectorSig)
            <div class="sign-col">
              <img src="{{ $makeSrc($directorSig['image_path']) }}" alt="Director Signature">
              <div class="sign-name">{{ $directorSig['name'] ?? 'Christian Widargo' }}</div>
              <div>{{ $directorSig['position'] ?? 'Direktur Utama' }}</div>
            </div>
          @endif
          <div class="sign-col right">
            @if($stampPath)
              <img src="{{ $stampPath }}" alt="ICP Stamp" style="max-height: 80px;">
            @else
              <span class="stamp">ICP OFFICIAL</span>
            @endif
          </div>
        </div>
      @endif
    </div>
  </div>
</body>
</html>
