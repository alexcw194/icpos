{{-- resources/views/documents/form.blade.php --}}
@extends('layouts.tabler')

@section('content')
@php
  $isEdit = ($mode ?? '') === 'edit';
  $action = $isEdit ? route('documents.update', $document) : route('documents.store');
@endphp

<div class="page-header d-print-none">
  <div class="row align-items-center">
    <div class="col">
      <h2 class="page-title">{{ $isEdit ? 'Edit Document' : 'New Document' }}</h2>
      <div class="text-muted">Draft bisa diedit hingga dikirim untuk approval.</div>
    </div>
    <div class="col-auto ms-auto d-print-none">
      @hasanyrole('Admin|SuperAdmin')
        <a href="{{ route('documents.index') }}" class="btn btn-outline-secondary">Back</a>
      @else
        <a href="{{ route('documents.my') }}" class="btn btn-outline-secondary">Back</a>
      @endhasanyrole
    </div>
  </div>
</div>

@if(session('success'))
  <div class="alert alert-success">{{ session('success') }}</div>
@endif
@if(session('error'))
  <div class="alert alert-danger">{{ session('error') }}</div>
@endif

<form id="docForm" method="post" action="{{ $action }}" enctype="multipart/form-data">
  @csrf
  @if($isEdit)
    @method('put')
  @endif

  <div class="card">
    <div class="card-body">
      <div class="row g-3 mb-3">
        <div class="col-md-8">
          <label class="form-label">Title</label>
          <input type="text" name="title" class="form-control" value="{{ old('title', $document->title) }}" required>
          @error('title')<div class="text-danger small">{{ $message }}</div>@enderror
        </div>
        <div class="col-md-4">
          <label class="form-label">Date</label>
          <input
            type="date"
            name="document_date"
            class="form-control"
            value="{{ old('document_date', optional($document->document_date)->toDateString() ?? now()->toDateString()) }}"
            required
          >
          @error('document_date')<div class="text-danger small">{{ $message }}</div>@enderror
        </div>
      </div>

      @php
        $selectedTemplateId = old('document_template_id', $document->document_template_id);
      @endphp
      <div class="mb-3">
        <label class="form-label">Template</label>
        <select name="document_template_id" id="document_template_id" class="form-select">
          <option value="">Tanpa Template (Free)</option>
          @foreach(($templates ?? []) as $tpl)
            <option value="{{ $tpl->id }}"
                    data-code="{{ $tpl->code }}"
                    @selected((string) $selectedTemplateId === (string) $tpl->id)>
              {{ $tpl->name }}
            </option>
          @endforeach
        </select>
      </div>

      @hasanyrole('Admin|SuperAdmin')
        @php
          $ownerSelected = old('created_by_user_id', $document->created_by_user_id ?? auth()->id());
        @endphp
        <div class="mb-3">
          <label class="form-label">Owner</label>
          <select name="created_by_user_id" class="form-select" required>
            @foreach(($owners ?? []) as $owner)
              <option value="{{ $owner->id }}" @selected((string) $ownerSelected === (string) $owner->id)>
                {{ $owner->name }}
              </option>
            @endforeach
          </select>
          @error('created_by_user_id')<div class="text-danger small">{{ $message }}</div>@enderror
        </div>
      @endhasanyrole

      <div class="row g-3">
        <div class="col-md-6">
          <label class="form-label">Customer</label>
          <select name="customer_id" id="customer_id" class="form-select" required>
            <option value="">Select customer</option>
            @foreach($customers as $customer)
              <option value="{{ $customer->id }}"
                @selected(old('customer_id', $document->customer_id) == $customer->id)>
                {{ $customer->name }}
              </option>
            @endforeach
          </select>
          @error('customer_id')<div class="text-danger small">{{ $message }}</div>@enderror
        </div>
        <div class="col-md-6">
          <label class="form-label">Contact (Up.)</label>
          <select name="contact_id" id="contact_id" class="form-select">
            <option value="">Optional</option>
            @if($document->contact)
              <option value="{{ $document->contact->id }}" selected>
                {{ $document->contact->full_name }}
              </option>
            @endif
          </select>
          @error('contact_id')<div class="text-danger small">{{ $message }}</div>@enderror
        </div>
      </div>

      @php
        $selectedSigner = old(
          'sales_signer_user_id',
          $document->sales_signer_user_id ?? 'director'
        );
      @endphp

      <div class="row g-3 mt-3">
        <div class="col-12 col-md-6 d-flex flex-column">
          <label class="form-label">Signature</label>
          <select name="sales_signer_user_id" id="sales_signer_user_id" class="form-select" required>
            <option value="">Pilih Signature</option>
            <option value="director" @selected($selectedSigner === 'director')>Direktur Utama</option>
            @foreach($salesUsers as $salesUser)
              <option value="{{ $salesUser->id }}"
                data-position="{{ $salesUser->default_position ?? '' }}"
                @selected((string) $selectedSigner === (string) $salesUser->id)>
                {{ $salesUser->name }}
              </option>
            @endforeach
          </select>
          <div class="form-hint mt-1">Pilih Direktur Utama atau user signer.</div>
          @error('sales_signer_user_id')<div class="text-danger small">{{ $message }}</div>@enderror
        </div>
        <div class="col-12 col-md-6 d-flex flex-column" id="sales-position-wrap" style="{{ ($selectedSigner && $selectedSigner !== 'director') ? '' : 'display:none' }}">
          <label class="form-label">Signature Position</label>
          @php
            $salesPosValue = old('sales_signature_position', $document->sales_signature_position);
            if ($salesPosValue === null && auth()->user()->hasRole('Sales')) {
                $salesPosValue = $signature?->default_position;
            }
          @endphp
          <input type="text" name="sales_signature_position" id="sales_signature_position" class="form-control"
                 value="{{ $salesPosValue ?? '' }}">
          <div class="form-hint mt-1">Contoh: Sales Executive</div>
          @error('sales_signature_position')<div class="text-danger small">{{ $message }}</div>@enderror
        </div>
      </div>

      @php
        $payload = old('template_payload', $templatePayload ?? []);
        $workPoints = $payload['work_points'] ?? [''];
        if (!is_array($workPoints) || count($workPoints) === 0) {
          $workPoints = [''];
        }
        $customerSigners = $payload['customer_signers'] ?? [['name' => '', 'title' => '']];
        if (!is_array($customerSigners) || count($customerSigners) === 0) {
          $customerSigners = [['name' => '', 'title' => '']];
        }
      @endphp

      <div id="bast-fields" class="mt-3 border rounded p-3" style="display:none">
        <h4 class="mb-3">Template BAST - Serah Terima Pekerjaan</h4>

        <div class="row g-3">
          <div class="col-md-4">
            <label class="form-label">Tanggal BA</label>
            <input type="date" name="template_payload[tanggal_ba]" class="form-control"
                   value="{{ $payload['tanggal_ba'] ?? now()->toDateString() }}">
          </div>
          <div class="col-md-4">
            <label class="form-label">Kota</label>
            <input type="text" name="template_payload[kota]" class="form-control"
                   value="{{ $payload['kota'] ?? 'Surabaya' }}">
          </div>
        </div>

        <div class="row g-3 mt-1">
          <div class="col-md-6">
            <label class="form-label">Nama Customer</label>
            <input type="text" name="template_payload[nama_customer]" class="form-control"
                   value="{{ $payload['nama_customer'] ?? '' }}">
          </div>
          <div class="col-md-6">
            <label class="form-label">Nama Pekerjaan</label>
            <input type="text" name="template_payload[nama_pekerjaan]" class="form-control"
                   value="{{ $payload['nama_pekerjaan'] ?? '' }}">
          </div>
          <div class="col-md-12">
            <label class="form-label">Lokasi Pekerjaan</label>
            <textarea name="template_payload[lokasi_pekerjaan]" class="form-control" rows="2">{{ $payload['lokasi_pekerjaan'] ?? '' }}</textarea>
          </div>
        </div>

        <div class="row g-3 mt-1">
          <div class="col-md-4">
            <label class="form-label">Jenis Kontrak</label>
            <select name="template_payload[jenis_kontrak]" class="form-select">
              @foreach(['SPK','PO','SO','BQ','Lainnya'] as $opt)
                <option value="{{ $opt }}" @selected(($payload['jenis_kontrak'] ?? '') === $opt)>{{ $opt }}</option>
              @endforeach
            </select>
          </div>
          <div class="col-md-8">
            <label class="form-label">Nomor Kontrak</label>
            <input type="text" name="template_payload[nomor_kontrak]" class="form-control"
                   value="{{ $payload['nomor_kontrak'] ?? '' }}">
          </div>
        </div>

        <div class="row g-3 mt-1">
          <div class="col-md-4">
            <label class="form-label">Tanggal Mulai</label>
            <input type="date" name="template_payload[tanggal_mulai]" class="form-control"
                   value="{{ $payload['tanggal_mulai'] ?? '' }}">
          </div>
          <div class="col-md-4">
            <label class="form-label">Status Pekerjaan</label>
            <input type="text" name="template_payload[status_pekerjaan]" class="form-control"
                   value="{{ $payload['status_pekerjaan'] ?? '' }}">
          </div>
          <div class="col-md-4">
            <label class="form-label">Tanggal Progress</label>
            <input type="date" name="template_payload[tanggal_progress]" class="form-control"
                   value="{{ $payload['tanggal_progress'] ?? '' }}">
          </div>
        </div>

        <div class="mt-3">
          <label class="form-label">Ruang Lingkup & Catatan</label>
          <div id="bast-work-points">
            @foreach($workPoints as $idx => $point)
              <div class="input-group mb-2">
                <input type="text" name="template_payload[work_points][]" class="form-control" value="{{ $point }}">
                <button type="button" class="btn btn-outline-danger btn-remove-point">Remove</button>
              </div>
            @endforeach
          </div>
          <button type="button" class="btn btn-sm btn-outline-primary" id="btn-add-point">+ Add Point</button>
        </div>

        <div class="row g-3 mt-3">
          <div class="col-md-6">
            <label class="form-label">Customer Signers</label>
            <div id="bast-customer-signers">
              @foreach($customerSigners as $idx => $row)
                <div class="row g-2 align-items-end mb-2 bast-signer-row">
                  <div class="col-6">
                    <input type="text" name="template_payload[customer_signers][{{ $idx }}][name]" class="form-control" placeholder="Nama" value="{{ $row['name'] ?? '' }}">
                  </div>
                  <div class="col-5">
                    <input type="text" name="template_payload[customer_signers][{{ $idx }}][title]" class="form-control" placeholder="Jabatan" value="{{ $row['title'] ?? '' }}">
                  </div>
                  <div class="col-1">
                    <button type="button" class="btn btn-outline-danger btn-remove-signer">x</button>
                  </div>
                </div>
              @endforeach
            </div>
            <button type="button" class="btn btn-sm btn-outline-primary" id="btn-add-customer-signer">+ Add Customer Signer</button>
          </div>
          <div class="col-md-6">
            <label class="form-label">ICP Auto Signature</label>
            <div class="form-check form-switch mt-1">
              <input type="hidden" name="template_payload[icp_auto_signature]" value="0">
              <input class="form-check-input" type="checkbox" role="switch" id="icp_auto_signature"
                     name="template_payload[icp_auto_signature]" value="1"
                     @checked(($payload['icp_auto_signature'] ?? true))>
              <label class="form-check-label" for="icp_auto_signature">Tampilkan stempel & tanda tangan ICP otomatis</label>
            </div>
            <label class="form-label mt-3">Masa Pemeliharaan</label>
            <div class="form-check form-switch mt-1">
              <input type="hidden" name="template_payload[maintenance_notice]" value="0">
              <input class="form-check-input" type="checkbox" role="switch" id="maintenance_notice"
                     name="template_payload[maintenance_notice]" value="1"
                     @checked(($payload['maintenance_notice'] ?? false))>
              <label class="form-check-label" for="maintenance_notice">Tampilkan klausul masa pemeliharaan</label>
            </div>
          </div>
        </div>
      </div>

      <div class="mt-3" id="doc-body-section">
        <div class="mb-3">
          <label class="form-label">Body Content</label>
          <textarea id="doc-editor" name="body" class="form-control" rows="18">{{ old('body', $document->body_html ?? $document->body ?? '') }}</textarea>
          <div class="form-hint">Gambar hanya via upload (PNG/JPG), tanpa URL eksternal.</div>
        </div>
        <input type="hidden" name="draft_token" id="draft_token" value="{{ $draftToken ?? '' }}">
        @error('body')<div class="text-danger small">{{ $message }}</div>@enderror
      </div>

    </div>

    <div class="card-footer text-end">
      <button type="submit" class="btn btn-primary">{{ $isEdit ? 'Save Changes' : 'Save Draft' }}</button>
    </div>
  </div>
</form>
@endsection

@push('scripts')
@php
  $tinymceVersion = @filemtime(public_path('vendor/tinymce/tinymce.min.js')) ?: time();
  $tinymceSrc = asset('vendor/tinymce/tinymce.min.js').'?v='.$tinymceVersion;
@endphp
<script src="{{ $tinymceSrc }}"></script>
<script>
  document.addEventListener('DOMContentLoaded', () => {
    const form = document.getElementById('docForm');
    const draftToken = document.getElementById('draft_token')?.value || '';
    const documentId = @json($document->id ?? null);
    const uploadUrl = @json(route('documents.images.upload'));
    const csrfToken = @json(csrf_token());

    const buildUploadUrl = () => {
      const params = new URLSearchParams();
      params.append('_token', csrfToken);
      if (documentId) {
        params.append('document_id', documentId);
      } else if (draftToken) {
        params.append('draft_token', draftToken);
      }
      return `${uploadUrl}?${params.toString()}`;
    };

    tinymce.init({
      license_key: 'gpl',
      selector: '#doc-editor',
      height: 520,
      menubar: false,
      branding: false,
      plugins: 'lists table link image fullscreen',
      toolbar: 'undo redo | bold italic underline | alignleft aligncenter alignright alignjustify | bullist numlist outdent indent | table image link | fullscreen',
      automatic_uploads: true,
      images_upload_url: buildUploadUrl(),
      images_upload_credentials: true,
      images_reuse_filename: true,
      image_caption: false,
      content_style: 'img{max-width:100%;height:auto;} table{width:100%;border-collapse:collapse;} table td,table th{border:1px solid #d1d5db;padding:4px 6px;}',
      setup: (editor) => {
        editor.on('Paste', (e) => {
          const data = e.clipboardData?.getData('text/html') || '';
          if (/src=["']data:image/i.test(data) || /<img[^>]+src=["']https?:/i.test(data)) {
            e.preventDefault();
            alert('Gunakan tombol upload untuk gambar.');
          }
        });
      },
    });

    form?.addEventListener('submit', () => {
      tinymce.triggerSave();
    });

    const customerSelect = document.getElementById('customer_id');
    const contactSelect = document.getElementById('contact_id');
    const salesSignerSelect = document.getElementById('sales_signer_user_id');
    const salesPositionWrap = document.getElementById('sales-position-wrap');
    const salesPositionInput = document.getElementById('sales_signature_position');
    const templateSelect = document.getElementById('document_template_id');
    const bastFields = document.getElementById('bast-fields');
    const bodySection = document.getElementById('doc-body-section');
    const workPointsWrap = document.getElementById('bast-work-points');
    const addPointBtn = document.getElementById('btn-add-point');
    const customerSignersWrap = document.getElementById('bast-customer-signers');
    const addCustomerSignerBtn = document.getElementById('btn-add-customer-signer');

    const loadContacts = async (customerId, selectedId = null) => {
      if (!customerId) {
        contactSelect.innerHTML = '<option value="">Optional</option>';
        return;
      }
      const res = await fetch(`/api/customers/${customerId}/contacts`, {
        headers: { 'Accept': 'application/json' }
      });
      if (!res.ok) return;
      const items = await res.json();
      contactSelect.innerHTML = '<option value="">Optional</option>';
      items.forEach(ct => {
        const opt = document.createElement('option');
        opt.value = ct.id;
        const label = ct.position ? `${ct.name} (${ct.position})` : ct.name;
        opt.textContent = label;
        if (String(ct.id) === String(selectedId)) {
          opt.selected = true;
        }
        contactSelect.appendChild(opt);
      });
    };

    customerSelect?.addEventListener('change', () => {
      loadContacts(customerSelect.value, null);
    });

    if (customerSelect?.value) {
      loadContacts(customerSelect.value, contactSelect.value || null);
    }

    const toggleSalesPosition = () => {
      if (!salesPositionWrap) return;
      const rawValue = salesSignerSelect ? String(salesSignerSelect.value || '') : '';
      const hasSalesSigner = rawValue !== '' && rawValue !== 'director';
      salesPositionWrap.style.display = hasSalesSigner ? 'flex' : 'none';
      if (hasSalesSigner && salesSignerSelect && salesSignerSelect.tagName === 'SELECT' && salesPositionInput) {
        const opt = salesSignerSelect.options[salesSignerSelect.selectedIndex];
        const pos = opt?.dataset?.position || '';
        if (pos && salesPositionInput.value.trim() === '') {
          salesPositionInput.value = pos;
        }
      }
      if (!hasSalesSigner && salesPositionInput) {
        salesPositionInput.value = '';
      }
    };

    salesSignerSelect?.addEventListener('change', toggleSalesPosition);
    toggleSalesPosition();

    const setBastEnabled = (enabled) => {
      if (!bastFields) return;
      bastFields.style.display = enabled ? '' : 'none';
      if (bodySection) {
        bodySection.style.display = enabled ? 'none' : '';
      }
      bastFields.querySelectorAll('input, select, textarea, button').forEach((el) => {
        if (el.tagName === 'BUTTON') return;
        el.disabled = !enabled;
      });
    };

    const currentTemplateCode = () => {
      if (!templateSelect) return '';
      const opt = templateSelect.options[templateSelect.selectedIndex];
      return opt?.dataset?.code || '';
    };

    const toggleTemplateFields = () => {
      const code = currentTemplateCode();
      setBastEnabled(code === 'ICP_BAST_STANDARD');
    };

    templateSelect?.addEventListener('change', toggleTemplateFields);
    toggleTemplateFields();

    const addWorkPoint = (value = '') => {
      if (!workPointsWrap) return;
      const group = document.createElement('div');
      group.className = 'input-group mb-2';
      group.innerHTML = `
        <input type="text" name="template_payload[work_points][]" class="form-control" value="${value}">
        <button type="button" class="btn btn-outline-danger btn-remove-point">Remove</button>
      `;
      workPointsWrap.appendChild(group);
    };

    addPointBtn?.addEventListener('click', () => addWorkPoint(''));
    workPointsWrap?.addEventListener('click', (e) => {
      const btn = e.target.closest('.btn-remove-point');
      if (!btn) return;
      const groups = workPointsWrap.querySelectorAll('.input-group');
      if (groups.length <= 1) {
        const input = btn.closest('.input-group')?.querySelector('input');
        if (input) input.value = '';
        return;
      }
      btn.closest('.input-group')?.remove();
    });

    const addSignerRow = (wrap, fieldKey) => {
      if (!wrap) return;
      const index = wrap.querySelectorAll('.bast-signer-row').length;
      const row = document.createElement('div');
      row.className = 'row g-2 align-items-end mb-2 bast-signer-row';
      row.innerHTML = `
        <div class="col-6">
          <input type="text" name="template_payload[${fieldKey}][${index}][name]" class="form-control" placeholder="Nama">
        </div>
        <div class="col-5">
          <input type="text" name="template_payload[${fieldKey}][${index}][title]" class="form-control" placeholder="Jabatan">
        </div>
        <div class="col-1">
          <button type="button" class="btn btn-outline-danger btn-remove-signer">x</button>
        </div>
      `;
      wrap.appendChild(row);
    };

    const handleSignerRemove = (wrap, e) => {
      const btn = e.target.closest('.btn-remove-signer');
      if (!btn) return;
      const rows = wrap.querySelectorAll('.bast-signer-row');
      if (rows.length <= 1) {
        rows[0]?.querySelectorAll('input').forEach((input) => { input.value = ''; });
        return;
      }
      btn.closest('.bast-signer-row')?.remove();
    };

    addCustomerSignerBtn?.addEventListener('click', () => addSignerRow(customerSignersWrap, 'customer_signers'));
    customerSignersWrap?.addEventListener('click', (e) => handleSignerRemove(customerSignersWrap, e));
  });
</script>
@endpush
