{{-- resources/views/quotations/pdf_viewer.blade.php --}}
@extends('layouts.tabler')

@section('content')
<div class="container-xl">
  <div class="card">
    <div class="card-header d-flex align-items-center">
      <div class="min-w-0">
        <div class="card-title m-0">{{ $quotation->number }}</div>
        <div class="text-muted small">
          {{ optional($quotation->date)->format('d M Y') }}
          •
          <span class="badge {{ $quotation->status_badge_class ?? '' }}">
            {{ $quotation->status_label ?? strtoupper($quotation->status) }}
          </span>
        </div>
      </div>

      <div class="ms-auto d-flex align-items-center gap-2">
        <button type="button"
          class="btn btn-primary btn-sm"
          data-share-url="{{ $pdfUrl }}"
          data-share-title="{{ $quotation->number }}"
          onclick="return icposSharePdfFile(this)">
          Bagikan PDF
        </button>

        <a class="btn btn-outline-secondary btn-sm" href="{{ $downloadUrl }}">
          Unduh
        </a>
      </div>
    </div>

    {{-- PDF Area --}}
    <div class="card-body p-0" style="height: calc(100vh - 220px);" id="pdfArea">
      <iframe
        id="pdfFrame"
        src="{{ $pdfUrl }}"
        style="width:100%; height:100%; border:0;"
        loading="eager">
      </iframe>
    </div>
  </div>
</div>

<script>
/**
 * Android Chrome sering tidak render PDF di <iframe> (muncul placeholder + tombol "Open").
 * Solusi: tetap pakai halaman viewer ini (biar tombol Bagikan/Unduh tetap ada),
 * tapi area viewer diganti jadi tombol "Buka PDF" yang buka viewer native di tab baru.
 */
(function () {
  const ua = navigator.userAgent || '';
  const isAndroid = /Android/i.test(ua);

  if (isAndroid) {
    const area = document.getElementById('pdfArea');
    if (!area) return;

    area.innerHTML = `
      <div class="p-4">
        <div class="text-muted mb-3">PDF akan dibuka di viewer bawaan perangkat (tab baru).</div>
        <a class="btn btn-primary" href="{{ $pdfUrl }}" target="_blank" rel="noopener">
          Buka PDF
        </a>
      </div>
    `;
  }
})();

/**
 * Bagikan PDF: download -> share file (WhatsApp sheet, dll)
 * Tanpa "salin link".
 */
async function icposSharePdfFile(btn){
  const url = btn.getAttribute('data-share-url');
  const title = btn.getAttribute('data-share-title') || 'Quotation';

  // Filename: QO/ICP/2026/00003 -> QO-ICP-2026-00003.pdf
  const safe = (title || 'Quotation')
    .trim()
    .replaceAll('/', '-')
    .replaceAll('\\', '-')
    .replace(/[^A-Za-z0-9._-]+/g, '-')
    .replace(/-+/g, '-')
    .replace(/^-|-$/g, '');

  const filename = `${safe}.pdf`;

  try {
    const res = await fetch(url, { credentials: 'include' });
    if (!res.ok) throw new Error('PDF download failed: ' + res.status);

    const blob = await res.blob();
    const file = new File([blob], filename, { type: 'application/pdf' });

    if (navigator.canShare && navigator.canShare({ files: [file] }) && navigator.share) {
      await navigator.share({ title, files: [file] });
      return false;
    }

    // fallback: download saja (tanpa copy link)
    const a = document.createElement('a');
    a.href = URL.createObjectURL(blob);
    a.download = filename;
    document.body.appendChild(a);
    a.click();
    a.remove();
    URL.revokeObjectURL(a.href);

    alert('Browser tidak mendukung share. PDF sudah diunduh.');
    return false;

  } catch (e) {
    return false;
  }
}

/**
 * Autoload helper:
 * - Minta browser fokus ke iframe setelah load agar PDF renderer lebih cepat aktif di sebagian device.
 * - Tidak akan mengalahkan "attachment" behavior (kalau PDF route masih download).
 */
window.addEventListener('load', () => {
  const f = document.getElementById('pdfFrame');
  if (f) f.focus();
});
</script>
@endsection
