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
        <div class="mb-3">
          <label class="form-label">Body Content</label>
          <textarea id="doc-editor" name="body" class="form-control" rows="18">{{ old('body', $document->body_html ?? $document->body ?? '') }}</textarea>
          <div class="form-hint">Gambar hanya via upload (PNG/JPG), tanpa URL eksternal.</div>
        </div>
        <input type="hidden" name="draft_token" id="draft_token" value="{{ $draftToken ?? '' }}">
        @error('body')<div class="text-danger small">{{ $message }}</div>@enderror
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

    const uploadImage = async (file) => {
      const formData = new FormData();
      formData.append('image', file);
      if (documentId) {
        formData.append('document_id', documentId);
      } else if (draftToken) {
        formData.append('draft_token', draftToken);
      }

      const res = await fetch(uploadUrl, {
        method: 'POST',
        headers: {
          'X-CSRF-TOKEN': csrfToken,
          'Accept': 'application/json',
        },
        body: formData,
      });

      if (!res.ok) {
        throw new Error('Upload gagal.');
      }

      const data = await res.json();
      return data.url;
    };

    tinymce.init({
      license_key: 'gpl',
      selector: '#doc-editor',
      height: 520,
      menubar: false,
      branding: false,
      plugins: 'lists advlist link table image paste hr fullscreen',
      toolbar: [
        'undo redo | paste pastetext',
        'bold italic underline removeformat',
        'numlist bullist outdent indent',
        'alignleft aligncenter alignright alignjustify',
        'formatselect fontsizeselect',
        'image table hr',
        'link unlink',
        'fullscreen',
      ].join(' | '),
      paste_data_images: false,
      paste_as_text: false,
      paste_webkit_styles: 'none',
      paste_remove_styles_if_webkit: true,
      paste_enable_default_filters: true,
      file_picker_types: 'image',
      images_upload_handler: (blobInfo) => new Promise((resolve, reject) => {
        uploadImage(blobInfo.blob())
          .then(resolve)
          .catch(() => reject('Upload gagal.'));
      }),
      file_picker_callback: (callback, value, meta) => {
        if (meta.filetype !== 'image') return;
        const input = document.createElement('input');
        input.type = 'file';
        input.accept = 'image/png,image/jpeg';
        input.onchange = async () => {
          const file = input.files?.[0];
          if (!file) return;
          try {
            const url = await uploadImage(file);
            callback(url, { alt: file.name });
          } catch (err) {
            alert('Upload gagal.');
          }
        };
        input.click();
      },
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
