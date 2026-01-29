@extends('layouts.tabler')

@section('content')
@php
  $money = fn($n) => 'Rp ' . number_format((float)$n, 2, ',', '.');
@endphp

<div class="container-xl">
  <div class="page-header d-print-none">
    <div class="row align-items-center">
      <div class="col">
        <div class="page-pretitle">{{ $project->code }}</div>
        <h2 class="page-title">BQ {{ $quotation->number }}</h2>
        <div class="text-muted">
          <span class="badge bg-blue-lt text-blue-9">{{ ucfirst($quotation->status) }}</span>
          <span class="ms-2">{{ optional($quotation->quotation_date)->format('d M Y') }}</span>
        </div>
      </div>
      @php
        $pdfViewUrl = route('projects.quotations.pdf', [$project, $quotation]);
        $pdfDownloadUrl = route('projects.quotations.pdf-download', [$project, $quotation]);
      @endphp

      <div class="col-auto ms-auto btn-list">
        @can('update', $quotation)
          @if(!$quotation->isLocked())
            <a href="{{ route('projects.quotations.edit', [$project, $quotation]) }}" class="btn btn-warning">Edit</a>
          @endif
        @endcan
        <div class="dropdown">
          <button class="btn btn-outline-secondary dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false">
            PDF
          </button>
          <div class="dropdown-menu dropdown-menu-end">
            <a class="dropdown-item" target="_blank" href="{{ $pdfViewUrl }}">
              Lihat PDF
            </a>
            <a class="dropdown-item" href="{{ $pdfDownloadUrl }}">
              Simpan PDF
            </a>
            <button type="button"
              class="dropdown-item"
              data-share-url="{{ $pdfViewUrl }}"
              data-share-title="{{ $quotation->number }}"
              onclick="return icposSharePdfFile(this)">
              Bagikan PDF…
            </button>
          </div>
        </div>
        @can('markWon', $quotation)
          @if(!in_array($quotation->status, [\App\Models\ProjectQuotation::STATUS_WON, \App\Models\ProjectQuotation::STATUS_LOST], true))
            <form method="POST" action="{{ route('projects.quotations.won', [$project, $quotation]) }}" class="d-inline">
              @csrf
              <button class="btn btn-success" onclick="return confirm('Tandai BQ sebagai Won?')">Mark Won</button>
            </form>
          @endif
        @endcan
        @can('markLost', $quotation)
          @if(!in_array($quotation->status, [\App\Models\ProjectQuotation::STATUS_WON, \App\Models\ProjectQuotation::STATUS_LOST], true))
            <form method="POST" action="{{ route('projects.quotations.lost', [$project, $quotation]) }}" class="d-inline">
              @csrf
              <button class="btn btn-outline-danger" onclick="return confirm('Tandai BQ sebagai Lost?')">Mark Lost</button>
            </form>
          @endif
        @endcan
      </div>
    </div>
  </div>

  <div class="row g-3">
    <div class="col-lg-8">
      <div class="card mb-3">
        <div class="card-header">
          <h3 class="card-title">Header</h3>
        </div>
        <div class="card-body">
          <div class="row g-3">
            <div class="col-md-6">
              <div class="text-muted">To</div>
              <div>{{ $quotation->to_name }}</div>
            </div>
            <div class="col-md-6">
              <div class="text-muted">Attn</div>
              <div>{{ $quotation->attn_name ?: '-' }}</div>
            </div>
            <div class="col-md-6">
              <div class="text-muted">Project Title</div>
              <div>{{ $quotation->project_title }}</div>
            </div>
            <div class="col-md-6">
              <div class="text-muted">Working Time</div>
              <div>{{ $quotation->working_time_days ?: '-' }} hari @ {{ $quotation->working_time_hours_per_day }} jam/hari</div>
            </div>
            <div class="col-md-6">
              <div class="text-muted">Validity</div>
              <div>{{ $quotation->validity_days }} hari</div>
            </div>
            <div class="col-md-6">
              <div class="text-muted">Sales Owner</div>
              <div>{{ $quotation->salesOwner->name ?? '-' }}</div>
            </div>
          </div>
        </div>
      </div>

      <div class="card mb-3">
        <div class="card-header">
          <h3 class="card-title">Payment Terms</h3>
        </div>
        <div class="table-responsive">
          <table class="table table-sm table-vcenter card-table">
            <thead>
              <tr>
                <th>Code</th>
                <th>Label</th>
                <th class="text-end">Percent</th>
                <th>Trigger</th>
              </tr>
            </thead>
            <tbody>
              @forelse($quotation->paymentTerms as $term)
                <tr>
                  <td>{{ $term->code }}</td>
                  <td>{{ $term->label }}</td>
                  <td class="text-end">{{ number_format((float)$term->percent, 2, ',', '.') }}%</td>
                  <td>{{ $term->trigger_note ?: '-' }}</td>
                </tr>
              @empty
                <tr><td colspan="4" class="text-center text-muted">No payment terms.</td></tr>
              @endforelse
            </tbody>
          </table>
        </div>
      </div>

      <div class="card">
        <div class="card-header">
          <h3 class="card-title">BQ Lines</h3>
        </div>
        <div class="card-body">
          @forelse($quotation->sections as $section)
            <div class="mb-3">
              <div class="fw-semibold">{{ $section->name }}</div>
              <div class="table-responsive">
                <table class="table table-sm table-vcenter mt-2">
                  <thead>
                    <tr>
                      <th style="width:70px;">No</th>
                      <th>Description</th>
                      <th class="text-end">Qty</th>
                      <th>Unit</th>
                      <th class="text-end">Material</th>
                      <th class="text-end">Labor</th>
                      <th class="text-end">Line Total</th>
                    </tr>
                  </thead>
                  <tbody>
                    @foreach($section->lines as $line)
                      <tr>
                        <td>{{ $line->line_no }}</td>
                        <td class="text-wrap">
                          {{ $line->description }}
                          @if(($line->line_type ?? 'product') === 'percent')
                            <div class="text-muted small">
                              {{ number_format((float)($line->percent_value ?? 0), 4, ',', '.') }}%
                              ({{ $line->percent_basis ?? 'product_subtotal' }})
                            </div>
                          @elseif(($line->line_type ?? 'product') === 'charge')
                            <div class="text-muted small">Charge line</div>
                          @endif
                        </td>
                        <td class="text-end">{{ number_format((float)$line->qty, 2, ',', '.') }}</td>
                        <td>{{ $line->unit }}</td>
                        <td class="text-end">{{ $money($line->material_total) }}</td>
                        <td class="text-end">{{ $money($line->labor_total) }}</td>
                        <td class="text-end">{{ $money($line->line_total) }}</td>
                      </tr>
                    @endforeach
                  </tbody>
                </table>
              </div>
            </div>
          @empty
            <div class="text-muted">No sections.</div>
          @endforelse
        </div>
      </div>
    </div>

    <div class="col-lg-4">
      <div class="card mb-3">
        <div class="card-header">
          <h3 class="card-title">Totals</h3>
        </div>
        <div class="card-body">
          <div class="d-flex justify-content-between mb-2">
            <span class="text-muted">Material</span>
            <span class="fw-semibold">{{ $money($quotation->subtotal_material) }}</span>
          </div>
          <div class="d-flex justify-content-between mb-2">
            <span class="text-muted">Labor</span>
            <span class="fw-semibold">{{ $money($quotation->subtotal_labor) }}</span>
          </div>
          <div class="d-flex justify-content-between mb-2">
            <span class="text-muted">Subtotal</span>
            <span class="fw-semibold">{{ $money($quotation->subtotal) }}</span>
          </div>
          <div class="d-flex justify-content-between mb-2">
            <span class="text-muted">Tax ({{ number_format((float)$quotation->tax_percent, 2, ',', '.') }}%)</span>
            <span class="fw-semibold">{{ $money($quotation->tax_amount) }}</span>
          </div>
          <hr>
          <div class="d-flex justify-content-between">
            <span class="text-muted">Grand Total</span>
            <span class="h3 m-0">{{ $money($quotation->grand_total) }}</span>
          </div>
        </div>
      </div>

      <div class="card">
        <div class="card-header">
          <h3 class="card-title">Notes & Signatory</h3>
        </div>
        <div class="card-body">
          <div class="text-muted">Notes</div>
          <div class="text-wrap mb-3">{{ $quotation->notes ?: '-' }}</div>
          <div class="text-muted">Signatory</div>
          <div>{{ $quotation->signatory_name ?: '-' }}</div>
          <div class="text-muted">{{ $quotation->signatory_title ?: '' }}</div>
        </div>
      </div>
    </div>
  </div>
</div>
@endsection

@push('scripts')
<script>
window.icposSharePdfFile = window.icposSharePdfFile || async function (btn) {
  const url = btn.getAttribute('data-share-url');
  const title = btn.getAttribute('data-share-title') || 'BQ';
  const safe = (title || 'BQ')
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
