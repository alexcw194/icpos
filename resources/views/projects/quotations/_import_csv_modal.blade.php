@php
  $bqCsvImportUploadUrl = route('projects.bq-csv.import.upload', $project);
  $bqCsvImportMappingsUrl = route('projects.bq-csv.import.mappings', $project);
  $bqCsvImportPrepareUrl = route('projects.bq-csv.import.prepare', $project);
  $canManageBqCsvImportMappings = auth()->user()?->hasAnyRole(['Admin', 'SuperAdmin']) ?? false;
@endphp

<div class="modal fade" id="modalImportBqCsv" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-xl">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Import CSV BQ</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <div id="bqImportAlert" class="alert d-none"></div>

        <div class="mb-3">
          <label class="form-label">Upload CSV Sumber</label>
          <input type="file" class="form-control" id="bqImportFile" accept=".csv,text/csv">
          <div class="form-text">Header wajib: Sheet, Category, Item, Quantity, Unit, Specification, LJR.</div>
        </div>

        <div id="bqImportMeta" class="small text-muted d-none mb-2"></div>

        <div id="bqImportMappingWrap" class="d-none">
          <div class="fw-semibold mb-2">Mapping Konversi yang Harus Dilengkapi</div>
          <div class="table-responsive border rounded">
            <table class="table table-sm mb-0">
              <thead>
                <tr>
                  <th style="width:16%">Source Category</th>
                  <th style="width:20%">Source Item</th>
                  <th style="width:20%">Mapped Item</th>
                  <th style="width:14%">Source Type</th>
                  <th style="width:30%">Link Item / Variant</th>
                </tr>
              </thead>
              <tbody id="bqImportMissingBody"></tbody>
            </table>
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Tutup</button>
        <button type="button" class="btn btn-primary" id="btnBqImportUpload">Upload & Cek</button>
        <button type="button" class="btn btn-warning d-none" id="btnBqImportSaveMappings">Simpan Mapping</button>
        <button type="button" class="btn btn-success d-none" id="btnBqImportContinue">Lanjut ke New BQ</button>
      </div>
    </div>
  </div>
</div>

@push('scripts')
<script>
(() => {
  const modalEl = document.getElementById('modalImportBqCsv');
  if (!modalEl) return;

  const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
  const uploadUrl = @json($bqCsvImportUploadUrl);
  const mappingsUrl = @json($bqCsvImportMappingsUrl);
  const prepareUrl = @json($bqCsvImportPrepareUrl);
  const canManageMappings = @json((bool) $canManageBqCsvImportMappings);
  const itemSearchUrl = @json(route('items.search'));

  const alertEl = document.getElementById('bqImportAlert');
  const fileEl = document.getElementById('bqImportFile');
  const metaEl = document.getElementById('bqImportMeta');
  const mappingWrap = document.getElementById('bqImportMappingWrap');
  const missingBody = document.getElementById('bqImportMissingBody');
  const btnUpload = document.getElementById('btnBqImportUpload');
  const btnSaveMappings = document.getElementById('btnBqImportSaveMappings');
  const btnContinue = document.getElementById('btnBqImportContinue');

  let uploadToken = '';
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
    uploadToken = '';
    missingMappings = [];
    setAlert('', '');
    metaEl.textContent = '';
    metaEl.classList.add('d-none');
    mappingWrap.classList.add('d-none');
    missingBody.innerHTML = '';
    btnSaveMappings.classList.add('d-none');
    btnContinue.classList.add('d-none');
  };

  const getPickerSelectedUid = (row) => {
    const itemId = Number(row.querySelector('.bq-import-item-id')?.value || 0);
    const variantId = Number(row.querySelector('.bq-import-variant-id')?.value || 0);
    if (variantId > 0) return `variant-${variantId}`;
    if (itemId > 0) return `item-${itemId}`;
    return '';
  };

  const syncTargetFromOption = (row, option) => {
    const itemIdEl = row.querySelector('.bq-import-item-id');
    const variantIdEl = row.querySelector('.bq-import-variant-id');
    if (!itemIdEl || !variantIdEl) return;

    const itemId = Number(option?.item_id || 0);
    const variantId = Number(option?.variant_id || 0);
    itemIdEl.value = itemId > 0 ? String(itemId) : '';
    variantIdEl.value = variantId > 0 ? String(variantId) : '';
  };

  const initItemPicker = (row) => {
    const picker = row.querySelector('.bq-import-item-picker');
    if (!picker) return;
    if (!window.TomSelect) return;
    if (picker.tomselect) return;

    const sourceTypeEl = row.querySelector('.bq-import-source-type');
    const selectedUid = row.getAttribute('data-selected-uid') || '';
    const selectedLabel = row.getAttribute('data-selected-label') || '';

    const ts = new TomSelect(picker, {
      valueField: 'uid',
      labelField: 'label',
      searchField: ['name', 'sku', 'label'],
      maxOptions: 30,
      create: false,
      persist: false,
      preload: 'focus',
      closeAfterSelect: true,
      dropdownParent: 'body',
      options: selectedUid && selectedLabel ? [{ uid: selectedUid, label: selectedLabel, name: selectedLabel }] : [],
      items: selectedUid ? [selectedUid] : [],
      load(query, cb) {
        const source = sourceTypeEl?.value === 'project' ? 'project' : 'item';
        const params = new URLSearchParams();
        params.set('q', query || '');
        params.set('list_type', source === 'project' ? 'project' : 'retail');
        fetch(`${itemSearchUrl}?${params.toString()}`, {
          credentials: 'same-origin',
          headers: {
            'X-Requested-With': 'XMLHttpRequest',
            'Accept': 'application/json',
          },
          cache: 'no-store',
        })
          .then((resp) => resp.ok ? resp.text() : '[]')
          .then((text) => {
            const normalized = text.replace(/^\uFEFF/, '').trimStart();
            let parsed = [];
            try {
              parsed = JSON.parse(normalized);
            } catch (e) {
              cb([]);
              return;
            }
            cb(Array.isArray(parsed) ? parsed : []);
          })
          .catch(() => cb([]));
      },
      render: {
        option(data, escape) {
          return `<div>${escape(data.label || data.name || '')}</div>`;
        },
        item(data, escape) {
          return `<div>${escape(data.label || data.name || '')}</div>`;
        },
      },
      onChange(value) {
        if (!value) {
          syncTargetFromOption(row, null);
          return;
        }
        const option = this.options[value] || null;
        syncTargetFromOption(row, option);
      },
    });

    sourceTypeEl?.addEventListener('change', () => {
      ts.clear(true);
      ts.clearOptions();
      syncTargetFromOption(row, null);
    });
  };

  const renderMissingRows = (rows) => {
    missingBody.innerHTML = rows.map((row, idx) => {
      const sourceCategory = escapeHtml(row.source_category || '');
      const sourceItem = escapeHtml(row.source_item || '');
      const mappedItem = escapeHtml(row.mapped_item || row.source_item || '');
      const sourceType = row.target_source_type === 'project' ? 'project' : 'item';
      const targetItemId = Number(row.target_item_id || 0);
      const targetVariantId = Number(row.target_item_variant_id || 0);
      const selectedUid = targetVariantId > 0 ? `variant-${targetVariantId}` : (targetItemId > 0 ? `item-${targetItemId}` : '');
      const selectedLabel = escapeHtml(row.target_item_label || '');

      return `
        <tr class="bq-import-row"
            data-row-index="${idx}"
            data-selected-uid="${escapeHtml(selectedUid)}"
            data-selected-label="${selectedLabel}">
          <td>${sourceCategory}</td>
          <td>${sourceItem}</td>
          <td>
            <input type="text"
              class="form-control form-control-sm bq-import-mapped-item"
              value="${mappedItem}">
          </td>
          <td>
            <select class="form-select form-select-sm bq-import-source-type">
              <option value="item" ${sourceType === 'item' ? 'selected' : ''}>item</option>
              <option value="project" ${sourceType === 'project' ? 'selected' : ''}>project</option>
            </select>
          </td>
          <td>
            <select class="form-select form-select-sm bq-import-item-picker"></select>
            <input type="hidden" class="bq-import-item-id" value="${targetItemId > 0 ? targetItemId : ''}">
            <input type="hidden" class="bq-import-variant-id" value="${targetVariantId > 0 ? targetVariantId : ''}">
          </td>
        </tr>
      `;
    }).join('');

    missingBody.querySelectorAll('.bq-import-row').forEach((row) => initItemPicker(row));
  };

  const parseErrorMessage = (data, fallback) => {
    if (data?.errors && typeof data.errors === 'object') {
      const firstField = Object.keys(data.errors)[0];
      if (firstField && Array.isArray(data.errors[firstField]) && data.errors[firstField][0]) {
        return String(data.errors[firstField][0]);
      }
    }
    if (data?.message) return String(data.message);
    return fallback;
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
        setAlert('danger', parseErrorMessage(data, 'Upload CSV gagal.'));
        return;
      }

      uploadToken = String(data.token || '');
      missingMappings = Array.isArray(data.missing_mappings) ? data.missing_mappings : [];
      const sheetCount = Number(data.sheet_count || 0);

      metaEl.textContent = `Sheet terdeteksi: ${sheetCount}.`;
      metaEl.classList.remove('d-none');

      if (missingMappings.length > 0) {
        mappingWrap.classList.remove('d-none');
        renderMissingRows(missingMappings);
        btnContinue.classList.add('d-none');

        if (canManageMappings) {
          btnSaveMappings.classList.remove('d-none');
          setAlert('warning', `${missingMappings.length} mapping belum lengkap. Isi mapping dan link item dulu.`);
        } else {
          btnSaveMappings.classList.add('d-none');
          setAlert('warning', `${missingMappings.length} mapping belum lengkap. Hubungi Admin/SuperAdmin.`);
        }
      } else {
        mappingWrap.classList.add('d-none');
        missingBody.innerHTML = '';
        btnSaveMappings.classList.add('d-none');
        btnContinue.classList.remove('d-none');
        setAlert('success', 'CSV valid. Klik "Lanjut ke New BQ".');
      }
    } catch (e) {
      setAlert('danger', 'Terjadi kesalahan saat upload CSV.');
    } finally {
      btnUpload.disabled = false;
    }
  });

  btnSaveMappings?.addEventListener('click', async () => {
    const rows = [...missingBody.querySelectorAll('.bq-import-row')];
    if (rows.length === 0) {
      setAlert('danger', 'Tidak ada mapping yang perlu disimpan.');
      return;
    }

    const mappings = [];
    for (const row of rows) {
      const sourceCategory = row.children[0]?.textContent?.trim() || '';
      const sourceItem = row.children[1]?.textContent?.trim() || '';
      const mappedItem = String(row.querySelector('.bq-import-mapped-item')?.value || '').trim();
      const targetItemId = Number(row.querySelector('.bq-import-item-id')?.value || 0);
      const targetVariantId = Number(row.querySelector('.bq-import-variant-id')?.value || 0);

      if (!sourceCategory || !sourceItem || !mappedItem) {
        setAlert('danger', 'Source category, source item, dan mapped item wajib diisi.');
        return;
      }
      if (targetItemId <= 0) {
        setAlert('danger', `Link item wajib dipilih untuk "${sourceItem}".`);
        return;
      }

      mappings.push({
        source_category: sourceCategory,
        source_item: sourceItem,
        mapped_item: mappedItem,
        target_item_id: targetItemId,
        target_item_variant_id: targetVariantId > 0 ? targetVariantId : null,
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
        body: JSON.stringify({ mappings }),
        credentials: 'same-origin',
      });

      const data = await resp.json().catch(() => ({}));
      if (!resp.ok) {
        setAlert('danger', parseErrorMessage(data, 'Simpan mapping gagal.'));
        return;
      }

      mappingWrap.classList.add('d-none');
      btnSaveMappings.classList.add('d-none');
      btnContinue.classList.remove('d-none');
      setAlert('success', 'Mapping berhasil disimpan. Lanjutkan ke New BQ.');
    } catch (e) {
      setAlert('danger', 'Terjadi kesalahan saat menyimpan mapping.');
    } finally {
      btnSaveMappings.disabled = false;
    }
  });

  btnContinue?.addEventListener('click', async () => {
    if (!uploadToken) {
      setAlert('danger', 'Token upload tidak ditemukan. Upload ulang CSV.');
      return;
    }

    btnContinue.disabled = true;
    setAlert('', '');

    try {
      const resp = await fetch(prepareUrl, {
        method: 'POST',
        headers: {
          'X-CSRF-TOKEN': csrf,
          'Accept': 'application/json',
          'Content-Type': 'application/json',
          'X-Requested-With': 'XMLHttpRequest',
        },
        body: JSON.stringify({ token: uploadToken }),
        credentials: 'same-origin',
      });

      const data = await resp.json().catch(() => ({}));
      if (!resp.ok) {
        setAlert('danger', parseErrorMessage(data, 'Tidak bisa menyiapkan New BQ dari CSV.'));
        return;
      }

      const redirectUrl = String(data.redirect_url || '');
      if (!redirectUrl) {
        setAlert('danger', 'Redirect URL New BQ tidak ditemukan.');
        return;
      }

      window.location.href = redirectUrl;
    } catch (e) {
      setAlert('danger', 'Terjadi kesalahan saat menyiapkan New BQ.');
    } finally {
      btnContinue.disabled = false;
    }
  });
})();
</script>
@endpush
