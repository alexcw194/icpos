@extends('layouts.tabler')

@section('content')
<div class="container-xl">
  @php
    $isDraft = $po->status === 'draft';
    $pdfViewUrl = route('po.pdf', $po);
    $pdfDownloadUrl = route('po.pdf-download', $po);
    $shareTitle = $isDraft ? ('PO Draft #' . $po->id) : ('PO ' . ($po->number ?? $po->id));
    $canReceive = in_array($po->status, ['approved','partial','partially_received'], true);
    $canEdit = in_array($po->status, ['draft','approved'], true)
      && $po->lines->sum(fn ($line) => (float) ($line->qty_received ?? 0)) <= 0
      && empty($hasGoodsReceipts);
  @endphp

  <div class="card">
    <div class="card-header d-flex">
      <div>
        <h3 class="card-title mb-1">{{ $isDraft ? 'PO Draft' : ('PO ' . $po->number) }}</h3>
        <div class="text-muted small">
          {{ $isDraft ? ('Draft ID: ' . $po->id) : ('PO Number: ' . $po->number) }}
        </div>
      </div>

      <div class="ms-auto d-flex align-items-center gap-2">
        <div class="dropdown">
          <button class="btn btn-outline-secondary dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false">
            PDF
          </button>
          <div class="dropdown-menu dropdown-menu-end">
            <a class="dropdown-item" href="{{ $pdfViewUrl }}" target="_blank" rel="noopener">Lihat PDF</a>
            <a class="dropdown-item" href="{{ $pdfDownloadUrl }}">Unduh PDF</a>
            <button type="button"
                    class="dropdown-item"
                    data-share-url="{{ $pdfViewUrl }}"
                    data-share-title="{{ $shareTitle }}"
                    onclick="return icposSharePdfFile(this)">
              Bagikan PDF...
            </button>
          </div>
        </div>

        @if($canEdit)
          <a href="{{ route('po.edit', $po) }}" class="btn btn-outline-primary">Edit</a>
        @endif

        @if($po->status === 'draft')
          <form action="{{ route('po.approve', $po) }}" method="POST" class="d-inline">@csrf
            <button class="btn btn-success">Approve</button>
          </form>
        @endif

        @if($canReceive)
          <a href="{{ route('po.receive', $po) }}" class="btn btn-primary">Receive</a>
        @endif
      </div>
    </div>

    <div class="card-body">
      <div class="row g-3">
        <div class="col-md-3"><strong>Supplier</strong><br>{{ $po->supplier->name ?? '-' }}</div>
        <div class="col-md-3"><strong>Company</strong><br>{{ $po->company->alias ?? $po->company->name ?? '-' }}</div>
        <div class="col-md-3"><strong>Warehouse</strong><br>{{ $po->warehouse->name ?? '-' }}</div>
        <div class="col-md-3"><strong>Status</strong><br><span class="badge bg-blue">{{ ucfirst($po->status) }}</span></div>
      </div>

      <hr class="my-3">

      <div class="table-responsive">
          <table class="table">
          <thead><tr>
            <th>Item</th><th>Variant</th><th>SO Line</th><th class="text-end">Ordered</th><th class="text-end">Received</th><th class="text-end">Remaining</th><th class="text-end">Price</th>
          </tr></thead>
          <tbody>
            @foreach($po->lines as $ln)
            <tr>
              <td>{{ $ln->sku_snapshot ?? ($ln->item->sku ?? '') }} - {{ $ln->item_name_snapshot ?? ($ln->item->name ?? '') }}</td>
              <td>{{ $ln->variant->sku ?? '-' }}</td>
              <td>
                @if($ln->salesOrderLine)
                  {{ $ln->salesOrderLine->salesOrder->so_number ?? 'SO' }} / Line {{ $ln->salesOrderLine->position ?? $ln->salesOrderLine->id }}
                @else
                  -
                @endif
              </td>
              <td class="text-end">{{ number_format((float)$ln->qty_ordered,2,'.',',') }} {{ $ln->uom ?? '' }}</td>
              <td class="text-end">{{ number_format((float)($ln->qty_received ?? 0),2,'.',',') }}</td>
              <td class="text-end">{{ number_format((float)($ln->qty_ordered - ($ln->qty_received ?? 0)),2,'.',',') }}</td>
              <td class="text-end">{{ number_format((float)($ln->unit_price ?? 0),2,'.',',') }}</td>
            </tr>
            @endforeach
          </tbody>
        </table>
      </div>

      <div class="row justify-content-end mt-3">
        <div class="col-md-4">
          <table class="table table-sm">
            <tr>
              <td>Subtotal</td>
              <td class="text-end">{{ number_format((float)($po->subtotal ?? 0), 2, '.', ',') }}</td>
            </tr>
            <tr>
              <td>Tax ({{ number_format((float)($po->tax_percent ?? 0), 2, '.', ',') }}%)</td>
              <td class="text-end">{{ number_format((float)($po->tax_amount ?? 0), 2, '.', ',') }}</td>
            </tr>
            <tr class="fw-bold">
              <td>Total</td>
              <td class="text-end">{{ number_format((float)($po->total ?? 0), 2, '.', ',') }}</td>
            </tr>
          </table>
        </div>
      </div>

      @if($po->notes)
      <div class="mt-3"><strong>Notes</strong><div class="text-muted">{{ $po->notes }}</div></div>
      @endif

      @if($po->billingTerms->isNotEmpty())
      <hr class="my-3">
      <div>
        <h4 class="mb-2">Payment Terms</h4>
        <div class="table-responsive">
          <table class="table table-sm">
            <thead>
              <tr>
                <th>Code</th>
                <th class="text-end">Percent</th>
                <th>Schedule</th>
                <th>Note</th>
              </tr>
            </thead>
            <tbody>
              @foreach($po->billingTerms as $term)
                <tr>
                  <td>{{ $term->top_code }}</td>
                  <td class="text-end">{{ number_format((float) $term->percent, 2, ',', '.') }}%</td>
                  <td>
                    {{ $term->due_trigger ?? '-' }}
                    @if($term->offset_days !== null)
                      ({{ $term->offset_days }}d)
                    @elseif($term->day_of_month !== null)
                      (day {{ $term->day_of_month }})
                    @endif
                  </td>
                  <td>{{ $term->note ?? '-' }}</td>
                </tr>
              @endforeach
            </tbody>
          </table>
        </div>
      </div>
      @endif
    </div>
  </div>
</div>
@endsection

@push('scripts')
<script>
window.icposSharePdfFile = async function (btn) {
  const url = btn.getAttribute('data-share-url');
  const title = btn.getAttribute('data-share-title') || 'Purchase Order';
  const safe = (title || 'Purchase-Order')
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

    if (navigator.share) {
      await navigator.share({ title, url });
      return false;
    }

    const objectUrl = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = objectUrl;
    a.download = filename;
    document.body.appendChild(a);
    a.click();
    a.remove();
    URL.revokeObjectURL(objectUrl);

    alert('Browser tidak mendukung share. PDF sudah diunduh.');
    return false;
  } catch (e) {
    return false;
  }
};
</script>
@endpush
