{{-- resources/views/documents/form.blade.php --}}
@extends('layouts.tabler')

@push('styles')
<style>
  .doc-editor {
    min-height: 360px;
    border: 1px solid #e6e7e9;
    border-radius: 6px;
    background: #fff;
    overflow: auto;
  }
</style>
@endpush

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
      <div class="mb-3">
        <label class="form-label">Title</label>
        <input type="text" name="title" class="form-control" value="{{ old('title', $document->title) }}" required>
        @error('title')<div class="text-danger small">{{ $message }}</div>@enderror
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
          $document->sales_signer_user_id ?? ($isEdit ? 'director' : '')
        );
      @endphp

      <div class="row g-3 mt-3">
        <div class="col-12 col-md-8 col-lg-6">
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
          <div class="text-muted small">Pilih Direktur Utama atau user signer.</div>
          @error('sales_signer_user_id')<div class="text-danger small">{{ $message }}</div>@enderror
        </div>
      </div>

      <div class="mt-3">
        <div class="d-flex flex-wrap gap-2 align-items-center mb-2">
          <label class="form-label mb-0">Body Content</label>
          <div class="ms-auto d-flex align-items-center gap-2">
            <span class="text-muted small">Mode</span>
            <select id="editor-mode" class="form-select form-select-sm w-auto">
              <option value="surat">Mode Surat</option>
              <option value="laporan">Mode Laporan</option>
            </select>
          </div>
        </div>
        <textarea id="doc-editor" name="body_html" class="doc-editor">{{ old('body_html', $document->body_html) }}</textarea>
        <input type="hidden" name="draft_token" id="draft_token" value="{{ $draftToken ?? '' }}">
        <div class="text-muted small mt-1">Gambar hanya via upload (PNG/JPG), tanpa URL eksternal.</div>
        @error('body_html')<div class="text-danger small">{{ $message }}</div>@enderror
      </div>

      <div class="row g-3 mt-3" id="sales-position-wrap" style="{{ ($selectedSigner && $selectedSigner !== 'director') ? '' : 'display:none' }}">
        <div class="col-md-6">
          <label class="form-label">Signature Position</label>
          @php
            $salesPosValue = old('sales_signature_position', $document->sales_signature_position);
            if ($salesPosValue === null && auth()->user()->hasRole('Sales')) {
                $salesPosValue = $signature?->default_position;
            }
          @endphp
          <input type="text" name="sales_signature_position" id="sales_signature_position" class="form-control"
                 value="{{ $salesPosValue ?? '' }}">
          <div class="text-muted small">Contoh: Sales Executive</div>
          @error('sales_signature_position')<div class="text-danger small">{{ $message }}</div>@enderror
        </div>
      </div>
    </div>

    <div class="card-footer text-end">
      <button type="submit" class="btn btn-primary">{{ $isEdit ? 'Save Changes' : 'Save Draft' }}</button>
    </div>
  </div>
</form>
@endsection

@push('scripts')
<script src="https://cdn.ckeditor.com/4.22.1/full/ckeditor.js"></script>
<script>
  document.addEventListener('DOMContentLoaded', () => {
    const form = document.getElementById('docForm');
    const modeSelect = document.getElementById('editor-mode');
    const draftToken = document.getElementById('draft_token')?.value || '';
    const documentId = @json($document->id ?? null);
    const uploadBaseUrl = @json(route('documents.images.upload'));
    const csrfToken = @json(csrf_token());
    let dialogHooked = false;

    const buildUploadUrl = () => {
      const params = new URLSearchParams();
      params.append('_token', csrfToken);
      if (documentId) {
        params.append('document_id', documentId);
      } else if (draftToken) {
        params.append('draft_token', draftToken);
      }
      return `${uploadBaseUrl}?${params.toString()}`;
    };

    const hookImageDialog = () => {
      if (dialogHooked) return;
      CKEDITOR.on('dialogDefinition', (evt) => {
        if (evt.data.name !== 'image') return;
        const dialog = evt.data.definition;
        const infoTab = dialog.getContents('info');
        if (infoTab) {
          infoTab.remove('txtUrl');
          infoTab.remove('browse');
        }
      });
      dialogHooked = true;
    };

    const initEditor = (mode) => {
      const existing = CKEDITOR.instances['doc-editor'];
      if (existing) {
        existing.updateElement();
        existing.destroy(true);
      }
      hookImageDialog();

      const config = {
        height: 420,
        removePlugins: 'easyimage,cloudservices,exportpdf',
        extraPlugins: 'uploadimage,image2',
        uploadUrl: buildUploadUrl(),
        filebrowserUploadUrl: buildUploadUrl(),
        filebrowserUploadMethod: 'xhr',
        removeDialogTabs: 'image:advanced;image:Link',
        allowedContent: true,
        fontSize_sizes: '12/12px;14/14px;16/16px;18/18px;20/20px;22/22px',
        image2_alignClasses: ['doc-img-left', 'doc-img-center', 'doc-img-right'],
        image2_disableResizer: false,
        toolbar: mode === 'laporan'
          ? [
              { name: 'clipboard', items: ['Undo', 'Redo'] },
              { name: 'basicstyles', items: ['Bold', 'Italic', 'Underline'] },
              { name: 'paragraph', items: ['JustifyLeft', 'JustifyCenter', 'JustifyRight', 'NumberedList', 'BulletedList'] },
              { name: 'styles', items: ['FontSize'] },
              { name: 'insert', items: ['Image', 'Table'] },
            ]
          : [
              { name: 'clipboard', items: ['Undo', 'Redo'] },
              { name: 'basicstyles', items: ['Bold', 'Italic', 'Underline'] },
              { name: 'paragraph', items: ['JustifyLeft', 'JustifyCenter', 'JustifyRight', 'NumberedList', 'BulletedList'] },
              { name: 'styles', items: ['FontSize'] },
              { name: 'insert', items: ['Image'] },
            ],
      };

      const editor = CKEDITOR.replace('doc-editor', config);
      editor.on('paste', (evt) => {
        const data = evt.data.dataValue || '';
        if (/src=[\"']data:image/i.test(data)) {
          evt.cancel();
          alert('Gunakan tombol upload untuk gambar.');
        }
        if (/src=[\"']https?:/i.test(data)) {
          evt.cancel();
          alert('Gambar harus diupload, bukan URL.');
        }
      });
      editor.on('fileUploadRequest', (evt) => {
        const xhr = evt.data.fileLoader.xhr;
        if (xhr) {
          xhr.setRequestHeader('X-CSRF-TOKEN', csrfToken);
        }
      });
    };

    const initialValue = document.getElementById('doc-editor')?.value || '';
    if (modeSelect && /<img|<table/i.test(initialValue)) {
      modeSelect.value = 'laporan';
    }
    initEditor(modeSelect?.value || 'surat');
    modeSelect?.addEventListener('change', () => {
      initEditor(modeSelect.value);
    });

    form.addEventListener('submit', () => {
      if (CKEDITOR.instances['doc-editor']) {
        CKEDITOR.instances['doc-editor'].updateElement();
      }
    });

    const customerSelect = document.getElementById('customer_id');
    const contactSelect = document.getElementById('contact_id');
    const salesSignerSelect = document.getElementById('sales_signer_user_id');
    const salesPositionWrap = document.getElementById('sales-position-wrap');
    const salesPositionInput = document.getElementById('sales_signature_position');

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
  });
</script>
@endpush
