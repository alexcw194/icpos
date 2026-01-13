@extends('layouts.tabler')

@section('content')
<div class="container-xl">
  <div class="card">
    <div class="card-header d-flex align-items-center">
      <div class="card-title m-0">{{ $quotation->number }}</div>

      <div class="ms-auto d-flex gap-2">
        <button type="button"
          class="btn btn-primary btn-sm"
          data-share-url="{{ $pdfUrl }}"
          data-share-title="{{ $quotation->number }}"
          onclick="return icposSharePdfFile(this)">
          Bagikan PDF
        </button>

        <a class="btn btn-outline-secondary btn-sm" href="{{ $downloadUrl }}">Unduh</a>
      </div>
    </div>

    <div class="card-body p-0" style="height: calc(100vh - 220px);">
      <iframe src="{{ $pdfUrl }}" style="width:100%; height:100%; border:0;"></iframe>
    </div>
  </div>
</div>

<script>
  // paste function icposSharePdfFile + filename sanitize (replace / -> -) di sini
</script>
@endsection
