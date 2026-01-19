{{-- resources/views/documents/form.blade.php --}}
@extends('layouts.tabler')

@push('styles')
<style>
  .doc-editor {
    min-height: 360px;
    border: 1px solid #e6e7e9;
    border-radius: 6px;
    padding: 14px;
    background: #fff;
    overflow: auto;
  }
  .doc-toolbar .btn { padding: .25rem .5rem; }
  .doc-toolbar select {
    height: 34px;
    line-height: 1.2;
    padding-top: 4px;
    padding-bottom: 4px;
  }
  .doc-block {
    margin: 0 0 12px;
    padding: 4px;
    border: 1px dashed transparent;
  }
  .doc-block.is-active {
    border-color: #cbd5f5;
    background: #f8fafc;
  }
  .block-heading {
    font-weight: 700;
    font-size: 14px;
  }
  .block-image {
    text-align: left;
  }
  .block-image.align-center { text-align: center; }
  .block-image.align-right { text-align: right; }
  .block-image img {
    display: inline-block;
    max-width: 100%;
    height: auto;
  }
  .block-image.size-25 img { width: 25%; }
  .block-image.size-50 img { width: 50%; }
  .block-image.size-100 img { width: 100%; }
  .image-grid {
    font-size: 0;
    margin: 0 0 12px;
  }
  .image-grid .grid-item {
    display: inline-block;
    width: 50%;
    padding: 4px;
    box-sizing: border-box;
    vertical-align: top;
  }
  .image-grid.cols-3 .grid-item {
    width: 33.3333%;
  }
  .image-grid img {
    width: 100%;
    height: auto;
    display: block;
  }
  .simple-table {
    width: 100%;
    border-collapse: collapse;
  }
  .simple-table td,
  .simple-table th {
    border: 1px solid #d1d5db;
    padding: 4px 6px;
  }
  .block-image figcaption {
    margin-top: 6px;
    font-size: 11px;
    color: #6b7280;
  }
  .toolbar-section {
    display: flex;
    flex-wrap: wrap;
    gap: 6px;
    align-items: center;
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
        <label class="form-label">Body Content</label>
        <div class="d-flex flex-wrap gap-2 mb-2 align-items-center">
          <div class="toolbar-section doc-toolbar">
            <div class="btn-group" role="group">
              <button type="button" class="btn btn-outline-secondary" data-block="paragraph">Paragraph</button>
              <button type="button" class="btn btn-outline-secondary" data-block="heading">Heading</button>
              <button type="button" class="btn btn-outline-secondary" data-block="table">Table</button>
            </div>
            <div class="btn-group doc-toolbar-report" role="group">
              <button type="button" class="btn btn-outline-secondary" data-block="image">Image</button>
              <button type="button" class="btn btn-outline-secondary" data-block="image-caption">Image + Caption</button>
              <button type="button" class="btn btn-outline-secondary" data-block="grid-2">Image Grid 2</button>
              <button type="button" class="btn btn-outline-secondary" data-block="grid-3">Image Grid 3</button>
            </div>
            <button type="button" class="btn btn-outline-danger" id="remove-block">Remove Block</button>
          </div>
          <div class="toolbar-section ms-auto">
            <div class="input-group">
              <span class="input-group-text">Mode</span>
              <select id="editor-mode" class="form-select w-auto">
                <option value="surat">Mode Surat</option>
                <option value="laporan">Mode Laporan</option>
              </select>
            </div>
            <div id="image-controls" class="d-flex gap-2 align-items-center" style="display:none;">
              <select id="image-size" class="form-select w-auto">
                <option value="">Size</option>
                <option value="size-25">25%</option>
                <option value="size-50">50%</option>
                <option value="size-100">100%</option>
              </select>
              <select id="image-align" class="form-select w-auto">
                <option value="">Align</option>
                <option value="align-left">Left</option>
                <option value="align-center">Center</option>
                <option value="align-right">Right</option>
              </select>
            </div>
          </div>
        </div>
        <div class="text-muted small mb-2">Gunakan blok untuk menulis dan menyusun foto (tanpa free-float).</div>
        <div id="doc-editor" class="doc-editor">{!! old('body_html', $document->body_html) !!}</div>
        <input type="hidden" name="body_html" id="doc_body_html">
        <input type="hidden" name="draft_token" id="draft_token" value="{{ $draftToken ?? '' }}">
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
<script>
  document.addEventListener('DOMContentLoaded', () => {
    const editor = document.getElementById('doc-editor');
    const hidden = document.getElementById('doc_body_html');
    const form = document.getElementById('docForm');

    const uploadUrl = @json(route('documents.images.upload'));
    const csrfToken = @json(csrf_token());
    const draftToken = document.getElementById('draft_token')?.value || '';
    const documentId = @json($document->id ?? null);

    const sync = () => {
      hidden.value = editor.innerHTML.trim();
    };
    sync();

    const setActiveBlock = (block) => {
      editor.querySelectorAll('.doc-block').forEach(el => el.classList.remove('is-active'));
      if (block) {
        block.classList.add('is-active');
      }
      toggleImageControls(block);
    };

    const toggleImageControls = (block) => {
      const controls = document.getElementById('image-controls');
      if (!controls) return;
      if (block && block.classList.contains('block-image')) {
        controls.style.display = 'flex';
        const sizeSelect = document.getElementById('image-size');
        const alignSelect = document.getElementById('image-align');
        const current = Array.from(block.classList);
        sizeSelect.value = current.find(c => c.startsWith('size-')) || '';
        alignSelect.value = current.find(c => c.startsWith('align-')) || '';
      } else {
        controls.style.display = 'none';
      }
    };

    editor.addEventListener('click', (e) => {
      const block = e.target.closest('.doc-block');
      if (block) {
        setActiveBlock(block);
      }
    });

    const ensureEditable = (el) => {
      if (el && el.getAttribute('contenteditable') === null) {
        el.setAttribute('contenteditable', 'true');
      }
    };

    editor.querySelectorAll('p, h2, h3, h4, figcaption, td, th').forEach(ensureEditable);

    const addBlock = (html) => {
      const wrapper = document.createElement('div');
      wrapper.innerHTML = html.trim();
      const block = wrapper.firstElementChild;
      if (!block) return;
      editor.appendChild(block);
      if (block.matches('p, h3, h4, figcaption, td, th')) {
        ensureEditable(block);
      }
      setActiveBlock(block);
      sync();
    };

    const createParagraph = () => addBlock('<p class="doc-block block-paragraph" contenteditable="true"><br></p>');
    const createHeading = () => addBlock('<h3 class="doc-block block-heading" contenteditable="true">Heading</h3>');
    const createTable = () => {
      addBlock('<table class="doc-block block-table simple-table"><tbody><tr><td contenteditable="true">&nbsp;</td><td contenteditable="true">&nbsp;</td></tr></tbody></table>');
    };

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

    const promptUpload = (multiple = false) => new Promise((resolve) => {
      const input = document.createElement('input');
      input.type = 'file';
      input.accept = 'image/png,image/jpeg';
      input.multiple = multiple;
      input.addEventListener('change', () => {
        resolve(Array.from(input.files || []));
      });
      input.click();
    });

    const createImage = async (withCaption = false) => {
      const files = await promptUpload(false);
      if (!files.length) return;
      const url = await uploadImage(files[0]);
      const caption = withCaption ? '<figcaption class="image-caption" contenteditable="true">Caption</figcaption>' : '';
      addBlock(`<figure class="doc-block block-image align-left size-50"><img src="${url}" alt="Document image">${caption}</figure>`);
    };

    const createImageGrid = async (cols) => {
      const files = await promptUpload(true);
      const limited = files.slice(0, cols);
      if (!limited.length) return;
      const urls = [];
      for (const f of limited) {
        urls.push(await uploadImage(f));
      }
      const items = urls.map(url => `<figure class="grid-item"><img src="${url}" alt="Grid image"></figure>`).join('');
      addBlock(`<div class="doc-block image-grid cols-${cols}">${items}</div>`);
    };

    document.querySelectorAll('[data-block]').forEach(btn => {
      btn.addEventListener('click', async () => {
        const type = btn.dataset.block;
        try {
          if (type === 'paragraph') return createParagraph();
          if (type === 'heading') return createHeading();
          if (type === 'table') return createTable();
          if (type === 'image') return createImage(false);
          if (type === 'image-caption') return createImage(true);
          if (type === 'grid-2') return createImageGrid(2);
          if (type === 'grid-3') return createImageGrid(3);
        } catch (err) {
          alert(err.message || 'Upload gagal.');
        }
      });
    });

    document.getElementById('remove-block')?.addEventListener('click', () => {
      const active = editor.querySelector('.doc-block.is-active');
      if (!active) return;
      active.remove();
      sync();
    });

    document.getElementById('image-size')?.addEventListener('change', (e) => {
      const active = editor.querySelector('.doc-block.is-active');
      if (!active || !active.classList.contains('block-image')) return;
      active.classList.remove('size-25', 'size-50', 'size-100');
      if (e.target.value) active.classList.add(e.target.value);
      sync();
    });

    document.getElementById('image-align')?.addEventListener('change', (e) => {
      const active = editor.querySelector('.doc-block.is-active');
      if (!active || !active.classList.contains('block-image')) return;
      active.classList.remove('align-left', 'align-center', 'align-right');
      if (e.target.value) active.classList.add(e.target.value);
      sync();
    });

    const modeSelect = document.getElementById('editor-mode');
    const reportToolbar = document.querySelectorAll('.doc-toolbar-report');
    const toggleMode = () => {
      const mode = modeSelect?.value || 'surat';
      reportToolbar.forEach(el => {
        el.style.display = mode === 'laporan' ? 'inline-flex' : 'none';
      });
    };
    modeSelect?.addEventListener('change', toggleMode);
    toggleMode();

    editor.addEventListener('input', sync);
    form.addEventListener('submit', sync);

    editor.addEventListener('paste', (e) => {
      const hasImage = Array.from(e.clipboardData?.items || []).some(item => item.type.startsWith('image/'));
      if (hasImage) {
        e.preventDefault();
        alert('Gunakan tombol upload untuk gambar.');
        return;
      }
      if (e.target.closest('[contenteditable="true"]')) {
        e.preventDefault();
        const text = e.clipboardData?.getData('text/plain') || '';
        document.execCommand('insertText', false, text);
      }
    });

    editor.addEventListener('drop', (e) => {
      if (e.dataTransfer?.files?.length) {
        e.preventDefault();
        alert('Gunakan tombol upload untuk gambar.');
      }
    });

    if (editor.innerHTML.trim() === '') {
      createParagraph();
    }

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
