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
  .doc-toolbar select { height: 30px; }
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
        <div class="doc-toolbar d-flex flex-wrap gap-1 mb-2">
          <div class="btn-group" role="group">
            <button type="button" class="btn btn-outline-secondary" data-cmd="bold"><strong>B</strong></button>
            <button type="button" class="btn btn-outline-secondary" data-cmd="italic"><em>I</em></button>
            <button type="button" class="btn btn-outline-secondary" data-cmd="underline"><u>U</u></button>
          </div>
          <div class="btn-group" role="group">
            <button type="button" class="btn btn-outline-secondary" data-cmd="justifyLeft">Left</button>
            <button type="button" class="btn btn-outline-secondary" data-cmd="justifyCenter">Center</button>
            <button type="button" class="btn btn-outline-secondary" data-cmd="justifyRight">Right</button>
            <button type="button" class="btn btn-outline-secondary" data-cmd="justifyFull">Justify</button>
          </div>
          <select id="doc-font-size" class="form-select w-auto">
            <option value="">Font size</option>
            <option value="12">12</option>
            <option value="14">14</option>
            <option value="16">16</option>
            <option value="18">18</option>
            <option value="20">20</option>
          </select>
          <select id="doc-line-height" class="form-select w-auto">
            <option value="">Spacing</option>
            <option value="1">1.0</option>
            <option value="1.25">1.25</option>
            <option value="1.5">1.5</option>
            <option value="1.75">1.75</option>
            <option value="2">2.0</option>
          </select>
        </div>
        <div id="doc-editor" class="doc-editor" contenteditable="true">{!! old('body_html', $document->body_html) !!}</div>
        <input type="hidden" name="body_html" id="doc_body_html">
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

    const sync = () => {
      hidden.value = editor.innerHTML.trim();
    };
    sync();

    document.querySelectorAll('[data-cmd]').forEach(btn => {
      btn.addEventListener('click', () => {
        document.execCommand(btn.dataset.cmd, false, null);
        editor.focus();
        sync();
      });
    });

    const fontSelect = document.getElementById('doc-font-size');
    fontSelect?.addEventListener('change', (e) => {
      const size = e.target.value;
      if (!size) return;
      document.execCommand('fontSize', false, '7');
      editor.querySelectorAll('font[size="7"]').forEach(el => {
        const span = document.createElement('span');
        span.style.fontSize = size + 'px';
        span.innerHTML = el.innerHTML;
        el.parentNode.replaceChild(span, el);
      });
      editor.focus();
      sync();
      fontSelect.value = '';
    });

    const lineSelect = document.getElementById('doc-line-height');
    lineSelect?.addEventListener('change', (e) => {
      const val = e.target.value;
      if (!val) return;
      const sel = window.getSelection();
      if (!sel || sel.rangeCount === 0) return;
      let node = sel.anchorNode;
      if (node && node.nodeType === Node.TEXT_NODE) node = node.parentNode;
      while (node && node !== editor) {
        if (node.nodeType === Node.ELEMENT_NODE && /^(P|DIV|LI|H1|H2|H3|H4|H5|H6)$/.test(node.tagName)) {
          node.style.lineHeight = val;
          break;
        }
        node = node.parentNode;
      }
      editor.focus();
      sync();
      lineSelect.value = '';
    });

    form.addEventListener('submit', sync);

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
