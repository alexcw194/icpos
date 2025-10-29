@php
  // Peta session key → tipe alert
  $map = [
    'ok'      => 'success',
    'success' => 'success',
    'status'  => 'success',
    'error'   => 'danger',
    'warning' => 'warning',
    'info'    => 'info',
  ];
  // delay default 5 detik (boleh override via session('flash_delay'))
  $defaultDelay = session('flash_delay', 5000);
@endphp

@foreach ($map as $key => $type)
  @if (session($key))
    {{-- Tambahkan class .js-flash agar hanya alert ini yang auto-hide --}}
    <div class="alert alert-{{ $type }} alert-dismissible fade show js-flash mb-3"
         role="alert"
         data-delay="{{ $defaultDelay }}">
      <div class="d-flex align-items-center gap-2">
        <span>{{ session($key) }}</span>
        <button type="button" class="btn-close ms-auto" data-bs-dismiss="alert" aria-label="Close"></button>
      </div>
    </div>
  @endif
@endforeach

<style>
  /* Pastikan transisi halus saat ditutup */
  .alert.fade { transition: opacity .2s ease; }
</style>

<script>
  // Auto-hide HANYA untuk .alert.js-flash (bukan panel penting)
  document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('.alert.js-flash:not(.is-sticky)').forEach(el => {
      const delay = parseInt(el.dataset.delay || '5000', 10);
      setTimeout(() => {
        // pakai mekanisme bootstrap fade/show agar mulus
        el.classList.remove('show');
        setTimeout(() => el.remove(), 220);
      }, delay);
    });
  });
</script>
