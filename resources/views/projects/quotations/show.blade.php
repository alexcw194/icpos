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
        $bqCsvUploadUrl = route('projects.quotations.bq-csv.upload', [$project, $quotation]);
        $bqCsvMappingsUrl = route('projects.quotations.bq-csv.mappings', [$project, $quotation]);
        $bqCsvExportBaseUrl = route('projects.quotations.bq-csv.export', [$project, $quotation]);
        $canManageCsvMappings = auth()->user()?->hasAnyRole(['Admin', 'SuperAdmin']) ?? false;
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
        <button type="button" class="btn btn-outline-primary" data-bs-toggle="modal" data-bs-target="#modalBqCsvExport">
          Export CSV BQ
        </button>
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

<div class="modal fade" id="modalBqCsvExport" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Export CSV BQ</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <div id="bqCsvAlert" class="alert d-none"></div>

        <div class="mb-3">
          <label class="form-label">Upload CSV Sumber</label>
          <input type="file" class="form-control" id="bqCsvFile" accept=".csv,text/csv">
          <div class="form-text">Header wajib: Sheet, Category, Item, Quantity, Unit, Specification, LJR.</div>
        </div>

        <div id="bqCsvMeta" class="small text-muted d-none mb-2"></div>

        <div class="form-check form-switch mb-3 d-none" id="bqCsvBreakdownWrap">
          <input class="form-check-input" type="checkbox" id="bqCsvBreakdown">
          <label class="form-check-label" for="bqCsvBreakdown">Breakdown per sheet</label>
        </div>

        <div id="bqCsvMappingWrap" class="d-none">
          <div class="fw-semibold mb-2">Mapping Konversi yang Belum Tersedia</div>
          <div class="table-responsive border rounded">
            <table class="table table-sm mb-0">
              <thead>
                <tr>
                  <th style="width: 30%">Source Category</th>
                  <th style="width: 35%">Source Item</th>
                  <th style="width: 35%">Mapped Item</th>
                </tr>
              </thead>
              <tbody id="bqCsvMissingBody"></tbody>
            </table>
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Tutup</button>
        <button type="button" class="btn btn-primary" id="btnBqCsvUpload">Upload & Cek</button>
        <button type="button" class="btn btn-warning d-none" id="btnBqCsvSaveMappings">Simpan Mapping</button>
        <button type="button" class="btn btn-success d-none" id="btnBqCsvDownload">Download CSV</button>
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

<script>
(() => {
  const modalEl = document.getElementById('modalBqCsvExport');
  if (!modalEl) return;

  const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
  const uploadUrl = @json($bqCsvUploadUrl);
  const mappingsUrl = @json($bqCsvMappingsUrl);
  const exportBaseUrl = @json($bqCsvExportBaseUrl);
  const canManageMappings = @json((bool) $canManageCsvMappings);

  const alertEl = document.getElementById('bqCsvAlert');
  const fileEl = document.getElementById('bqCsvFile');
  const metaEl = document.getElementById('bqCsvMeta');
  const breakdownWrap = document.getElementById('bqCsvBreakdownWrap');
  const breakdownInput = document.getElementById('bqCsvBreakdown');
  const mappingWrap = document.getElementById('bqCsvMappingWrap');
  const missingBody = document.getElementById('bqCsvMissingBody');
  const btnUpload = document.getElementById('btnBqCsvUpload');
  const btnSaveMappings = document.getElementById('btnBqCsvSaveMappings');
  const btnDownload = document.getElementById('btnBqCsvDownload');

  let exportToken = '';
  let missingMappings = [];

  const escapeHtml = (value) => {
    return String(value ?? '')
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#39;');
  };

  const setAlert = (type, message) => {
    if (!message) {
      alertEl.className = 'alert d-none';
      alertEl.textContent = '';
      return;
    }

    alertEl.className = `alert alert-${type}`;
    alertEl.textContent = message;
  };

  const resetState = () => {
    exportToken = '';
    missingMappings = [];
    setAlert('', '');
    metaEl.textContent = '';
    metaEl.classList.add('d-none');
    breakdownInput.checked = false;
    breakdownWrap.classList.add('d-none');
    mappingWrap.classList.add('d-none');
    missingBody.innerHTML = '';
    btnSaveMappings.classList.add('d-none');
    btnDownload.classList.add('d-none');
  };

  const renderMissingRows = (rows) => {
    missingBody.innerHTML = rows.map((row, idx) => {
      const sourceCategory = escapeHtml(row.source_category || '');
      const sourceItem = escapeHtml(row.source_item || '');
      const defaultMapped = escapeHtml(row.source_item || '');

      return `
        <tr data-row-index="${idx}">
          <td>${sourceCategory}</td>
          <td>${sourceItem}</td>
          <td>
            <input
              type="text"
              class="form-control form-control-sm bq-csv-mapped-item"
              data-source-category="${sourceCategory}"
              data-source-item="${sourceItem}"
              value="${defaultMapped}">
          </td>
        </tr>
      `;
    }).join('');
  };

  modalEl.addEventListener('show.bs.modal', resetState);

  btnUpload?.addEventListener('click', async () => {
    const file = fileEl?.files?.[0];
    if (!file) {
      setAlert('danger', 'Silakan pilih file CSV terlebih dahulu.');
      return;
    }

    btnUpload.disabled = true;
    setAlert('', '');

    try {
      const fd = new FormData();
      fd.append('file', file);

      const resp = await fetch(uploadUrl, {
        method: 'POST',
        headers: {
          'X-CSRF-TOKEN': csrf,
          'Accept': 'application/json',
          'X-Requested-With': 'XMLHttpRequest',
        },
        body: fd,
        credentials: 'same-origin',
      });

      const data = await resp.json().catch(() => ({}));
      if (!resp.ok) {
        const msg = data?.message || 'Upload CSV gagal.';
        setAlert('danger', msg);
        return;
      }

      exportToken = data.token || '';
      missingMappings = Array.isArray(data.missing_mappings) ? data.missing_mappings : [];
      const sheetCount = Number(data.sheet_count || 0);
      const canBreakdown = Boolean(data.can_breakdown);

      metaEl.textContent = `Sheet terdeteksi: ${sheetCount}.`;
      metaEl.classList.remove('d-none');
      breakdownWrap.classList.toggle('d-none', !canBreakdown);
      breakdownInput.checked = false;

      if (missingMappings.length > 0) {
        mappingWrap.classList.remove('d-none');
        renderMissingRows(missingMappings);

        if (canManageMappings) {
          btnSaveMappings.classList.remove('d-none');
          btnDownload.classList.add('d-none');
          setAlert('warning', `${missingMappings.length} mapping belum tersedia. Lengkapi dulu lalu simpan mapping.`);
        } else {
          btnSaveMappings.classList.add('d-none');
          btnDownload.classList.add('d-none');
          setAlert('warning', `${missingMappings.length} mapping belum tersedia. Hubungi Admin/SuperAdmin untuk melengkapi mapping.`);
        }
      } else {
        mappingWrap.classList.add('d-none');
        missingBody.innerHTML = '';
        btnSaveMappings.classList.add('d-none');
        btnDownload.classList.remove('d-none');
        setAlert('success', 'CSV valid dan mapping lengkap. Siap diunduh.');
      }
    } catch (error) {
      setAlert('danger', 'Terjadi kesalahan saat upload CSV.');
    } finally {
      btnUpload.disabled = false;
    }
  });

  btnSaveMappings?.addEventListener('click', async () => {
    const inputs = [...document.querySelectorAll('.bq-csv-mapped-item')];
    if (inputs.length === 0) {
      setAlert('danger', 'Tidak ada mapping yang perlu disimpan.');
      return;
    }

    const mappings = [];
    for (const input of inputs) {
      const mappedItem = String(input.value || '').trim();
      const sourceCategory = String(input.dataset.sourceCategory || '').trim();
      const sourceItem = String(input.dataset.sourceItem || '').trim();
      if (!mappedItem || !sourceCategory || !sourceItem) {
        setAlert('danger', 'Semua mapped item wajib diisi.');
        return;
      }

      mappings.push({
        source_category: sourceCategory,
        source_item: sourceItem,
        mapped_item: mappedItem,
      });
    }

    btnSaveMappings.disabled = true;
    setAlert('', '');

    try {
      const resp = await fetch(mappingsUrl, {
        method: 'POST',
        headers: {
          'X-CSRF-TOKEN': csrf,
          'Accept': 'application/json',
          'Content-Type': 'application/json',
          'X-Requested-With': 'XMLHttpRequest',
        },
        credentials: 'same-origin',
        body: JSON.stringify({ mappings }),
      });

      const data = await resp.json().catch(() => ({}));
      if (!resp.ok) {
        setAlert('danger', data?.message || 'Simpan mapping gagal.');
        return;
      }

      mappingWrap.classList.add('d-none');
      btnSaveMappings.classList.add('d-none');
      btnDownload.classList.remove('d-none');
      setAlert('success', 'Mapping berhasil disimpan. CSV siap diunduh.');
    } catch (error) {
      setAlert('danger', 'Terjadi kesalahan saat menyimpan mapping.');
    } finally {
      btnSaveMappings.disabled = false;
    }
  });

  btnDownload?.addEventListener('click', () => {
    if (!exportToken) {
      setAlert('danger', 'Token export tidak ditemukan. Upload ulang CSV.');
      return;
    }

    const params = new URLSearchParams();
    params.set('token', exportToken);
    params.set('breakdown', breakdownInput.checked ? '1' : '0');
    window.location.href = `${exportBaseUrl}?${params.toString()}`;
  });
})();
</script>
@endpush
