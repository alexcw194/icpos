@php
  // (opsional) relasi lama untuk kompatibilitas
  $invoice  = $quotation->invoice ?? null;
  $delivery = $quotation->delivery ?? ($invoice->delivery ?? null);

  // Hitung SO terkait → ubah label jadi "Repeat SO" bila sudah pernah ada
  $soCount = method_exists($quotation, 'salesOrders')
    ? $quotation->salesOrders()->count()
    : 0;

  $createSoLabel = $soCount ? 'Repeat SO' : 'Create SO';
@endphp

<div class="d-flex flex-wrap gap-2">
  {{-- Edit --}}
  <a href="{{ route('quotations.edit', $quotation) }}" class="btn btn-outline-secondary">Edit</a>

  {{-- PDF (pakai dropdownmu sendiri kalau ada) --}}
  @if(Route::has('quotations.print'))
    <a href="{{ route('quotations.print', $quotation) }}" class="btn btn-outline-secondary" target="_blank" rel="noopener">
      PDF
    </a>
  @endif

  {{-- Create / Repeat SO → SELALU tampil --}}
  @if(Route::has('sales-orders.create-from-quotation'))
    <a href="{{ route('sales-orders.create-from-quotation', $quotation) }}" class="btn btn-primary">
      {{ $createSoLabel }}
    </a>
  @endif
</div>
