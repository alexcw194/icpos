{{-- resources/views/quotations/_preview.blade.php --}}
<div class="card">
  {{-- HEADER --}}
  <div class="card-header">
    <div class="d-flex align-items-start justify-content-between gap-2">
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

      @php
        $soCount = (method_exists($quotation, 'salesOrders'))
          ? ($quotation->relationLoaded('salesOrders') ? $quotation->salesOrders->count() : $quotation->salesOrders()->count())
          : 0;

        $soLabel = $soCount ? 'Repeat SO' : 'Create SO';

        $pdfViewUrl = route('quotations.pdf', $quotation);
        $pdfDownloadUrl = route('quotations.pdf-download', $quotation);
      @endphp

      <div class="d-flex align-items-center gap-2 flex-shrink-0">
        {{-- Primary CTA --}}
        @if(Route::has('sales-orders.create-from-quotation'))
          <a href="{{ route('sales-orders.create-from-quotation', $quotation) }}" class="btn btn-primary btn-sm">
            {{ $soLabel }}
          </a>
        @endif

        {{-- Secondary CTA --}}
        <a href="{{ route('quotations.edit', $quotation) }}" class="btn btn-warning btn-sm">Edit</a>

        {{-- Overflow (PDF + Status actions) --}}
        <div class="dropdown">
          <button class="btn btn-outline-secondary btn-icon btn-sm" data-bs-toggle="dropdown" aria-label="Menu">
            <svg xmlns="http://www.w3.org/2000/svg" class="icon" width="20" height="20" viewBox="0 0 24 24"
                 stroke-width="2" stroke="currentColor" fill="none" stroke-linecap="round" stroke-linejoin="round"
                 aria-hidden="true">
              <path stroke="none" d="M0 0h24v24H0z" fill="none"></path>
              <circle cx="5" cy="12" r="1"></circle>
              <circle cx="12" cy="12" r="1"></circle>
              <circle cx="19" cy="12" r="1"></circle>
            </svg>
          </button>

          <div class="dropdown-menu dropdown-menu-end">
            <a class="dropdown-item" target="_blank" href="{{ $pdfViewUrl }}">
              Lihat PDF
            </a>

            <a class="dropdown-item" href="{{ $pdfDownloadUrl }}">
              Unduh PDF
            </a>

            <button type="button"
              class="dropdown-item"
              data-share-url="{{ $pdfViewUrl }}"
              data-share-title="{{ $quotation->number }}"
              onclick="return icposSharePdfFile(this)">
              Bagikan PDF…
            </button>

            <div class="dropdown-divider"></div>

            @if($quotation->status === 'draft')
              <form method="post" action="{{ route('quotations.sent',$quotation) }}">
                @csrf
                <button class="dropdown-item" type="submit">Mark as Sent</button>
              </form>
            @elseif($quotation->status === 'sent')
              <form method="post" action="{{ route('quotations.draft',$quotation) }}">
                @csrf
                <button class="dropdown-item" type="submit">Back to Draft</button>
              </form>
            @endif
          </div>
        </div>
      </div>
    </div>
  </div>

  {{-- BODY --}}
  <div class="card-body">
    {{-- Customer / Company --}}
    <div class="row mb-3">
      <div class="col-md-6">
        <div class="text-muted">Customer</div>
        <div class="fw-medium">{{ $quotation->customer->name ?? '-' }}</div>
        @if(!empty($quotation->customer->address))
          <div class="text-muted small">{{ $quotation->customer->address }}</div>
        @endif
      </div>
      <div class="col-md-6">
        <div class="text-muted">Company</div>
        <div class="fw-medium">{{ $quotation->company->alias ?? $quotation->company->name ?? '-' }}</div>
      </div>
    </div>

    {{-- Lines (Desktop: table) --}}
    <div class="d-none d-md-block table-responsive mb-3">
      <table class="table table-sm">
        <thead>
          <tr>
            <th style="width:38%">Item</th>
            <th class="text-end" style="width:10%">Qty</th>
            <th style="width:10%">Unit</th>
            <th class="text-end" style="width:14%">Unit Price</th>
            <th class="text-end" style="width:14%">Discount</th>
            <th class="text-end" style="width:14%">Line Total</th>
          </tr>
        </thead>
        <tbody>
          @forelse($quotation->lines as $ln)
            <tr>
              <td>
                <div class="fw-medium">{{ $ln->name }}</div>
                @if($ln->description)
                  <div class="text-muted small">{{ $ln->description }}</div>
                @endif
              </td>
              <td class="text-end">
                {{ rtrim(rtrim(number_format((float)$ln->qty, 2, '.', ''), '0'), '.') }}
              </td>
              <td>{{ $ln->unit }}</td>
              <td class="text-end">{{ number_format((float)$ln->unit_price, 2, ',', '.') }}</td>
              <td class="text-end">
                @if(($ln->discount_type ?? 'amount') === 'percent')
                  {{ rtrim(rtrim(number_format((float)$ln->discount_value, 2, '.', ''), '0'), '.') }}%
                @else
                  {{ number_format((float)($ln->discount_amount ?? 0), 2, ',', '.') }}
                @endif
              </td>
              <td class="text-end">{{ number_format((float)$ln->line_total, 2, ',', '.') }}</td>
            </tr>
          @empty
            <tr><td colspan="6" class="text-center text-muted">No lines.</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>

    {{-- Lines (Mobile: stacked rows, NO ellipsis for name) --}}
    <div class="d-md-none mb-3">
      @forelse($quotation->lines as $ln)
        @php
          $qtyTxt = rtrim(rtrim(number_format((float)$ln->qty, 2, '.', ''), '0'), '.');
          $unitPriceTxt = number_format((float)$ln->unit_price, 2, ',', '.');
          $lineTotalTxt = number_format((float)$ln->line_total, 2, ',', '.');

          $discTxt = '';
          if (($ln->discount_type ?? 'amount') === 'percent') {
            $discTxt = rtrim(rtrim(number_format((float)$ln->discount_value, 2, '.', ''), '0'), '.') . '%';
          } else {
            $discTxt = number_format((float)($ln->discount_amount ?? 0), 2, ',', '.');
          }
        @endphp

        <div class="border rounded p-2 mb-2">
          {{-- Row 1: Item name FULL WIDTH (wrap allowed) --}}
          <div class="fw-medium">
            {{ $ln->name }}
          </div>

          @if($ln->description)
            <div class="text-muted small mt-1" style="white-space: normal;">
              {{ $ln->description }}
            </div>
          @endif

          {{-- Row 2: Qty×Price (left) + Line Total (right) --}}
          <div class="d-flex justify-content-between align-items-baseline mt-2">
            <div class="text-muted small">
              {{ $qtyTxt }} {{ $ln->unit }} × {{ $unitPriceTxt }}
            </div>
            <div class="fw-bold">
              {{ $lineTotalTxt }}
            </div>
          </div>

          {{-- Row 3: Discount below (secondary) --}}
          <div class="text-muted small mt-1">
            Disc {{ $discTxt }}
          </div>
        </div>
      @empty
        <div class="text-center text-muted">No lines.</div>
      @endforelse
    </div>

    {{-- Totals --}}
    <div class="d-flex justify-content-end">
      <div class="w-100" style="max-width:360px">
        <div class="d-flex justify-content-between">
          <span>Subtotal</span>
          <span>{{ number_format((float)$quotation->lines_subtotal, 2, ',', '.') }}</span>
        </div>

        @if(($quotation->total_discount_amount ?? 0) > 0)
          <div class="d-flex justify-content-between">
            <span>Discount</span>
            <span>-{{ number_format((float)$quotation->total_discount_amount, 2, ',', '.') }}</span>
          </div>
        @endif

        <div class="d-flex justify-content-between">
          <span>Taxable</span>
          <span>{{ number_format((float)$quotation->taxable_base, 2, ',', '.') }}</span>
        </div>

        <div class="d-flex justify-content-between">
          <span>Tax ({{ rtrim(rtrim(number_format((float)$quotation->tax_percent, 2, '.', ''), '0'), '.') }}%)</span>
          <span>{{ number_format((float)$quotation->tax_amount, 2, ',', '.') }}</span>
        </div>

        <div class="d-flex justify-content-between fw-bold border-top mt-2 pt-2">
          <span>Total</span>
          <span>{{ $quotation->total_idr ?? number_format((float)$quotation->total, 2, ',', '.') }}</span>
        </div>
      </div>
    </div>

    {{-- Related Sales Orders --}}
    @includeIf('quotations.partials._so_list', [
      'quotation' => $quotation,
      'showRepeatButton' => false,
    ])

    {{-- Notes --}}
    @if(!empty($quotation->notes))
      <div class="mt-3">
        <div class="fw-bold">Notes</div>
        <div class="text-muted" style="white-space: pre-line">{{ $quotation->notes }}</div>
      </div>
    @endif
  </div>
</div>

{{-- Bagikan PDF: download -> share file (no copy link) --}}
<script>
async function icposSharePdfFile(btn){
  const url = btn.getAttribute('data-share-url');
  const title = btn.getAttribute('data-share-title') || 'Quotation';
  const filename = `${title}.pdf`;

  try {
    const res = await fetch(url, { credentials: 'include' });
    if (!res.ok) throw new Error('PDF download failed: ' + res.status);

    const blob = await res.blob();
    const file = new File([blob], filename, { type: 'application/pdf' });

    // Best: share as FILE (WhatsApp sheet, etc.)
    if (navigator.canShare && navigator.canShare({ files: [file] }) && navigator.share) {
      await navigator.share({ title, files: [file] });
      return false;
    }

    // Fallback: just download (NO copy link)
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
    // user cancel / error → no-op
    return false;
  }
}
</script>
