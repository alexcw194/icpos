@extends('layouts.tabler')

@section('title', 'Tambah Resep Produksi')

@section('content')
<form method="POST" action="{{ route('manufacture-recipes.store') }}">
  @csrf

  <div class="card">
    <div class="card-header">
      <h3 class="card-title mb-0">Tambah Resep Produksi</h3>
    </div>

    <div class="card-body">

      {{-- Item Hasil --}}
      <div class="mb-3">
        <label class="form-label">Item Hasil (Kit / Bundle)</label>
        <select name="parent_item_id" class="form-select" required>
          @foreach($parentItems as $item)
            <option value="{{ $item->id }}">
              {{ $item->name }}
            </option>
          @endforeach
        </select>
      </div>

      {{-- Header Komponen --}}
      <div class="d-flex justify-content-between align-items-center mb-2">
        <label class="form-label mb-0">Komponen Resep</label>
        <button type="button" class="btn btn-outline-primary btn-sm" id="btnAddComponent">
          + Tambah Komponen
        </button>
      </div>

      {{-- Table Komponen --}}
      <div class="table-responsive">
        <table class="table table-sm align-middle" id="componentsTable">
          <thead>
            <tr>
              <th style="width:55%">Variant</th>
              <th class="text-end" style="width:20%">Qty</th>
              <th style="width:20%">Catatan</th>
              <th class="text-end" style="width:5%"></th>
            </tr>
          </thead>
          <tbody>

            {{-- DEFAULT ROW (index 0) --}}
            <tr class="component-row">
              <td>
                <select name="components[0][component_variant_id]" class="form-select js-variant-picker" required>
                  <option value="">Pilih variant…</option>
                </select>
              </td>

              <td>
                <input
                  type="number"
                  name="components[0][qty_required]"
                  step="0.1"
                  min="0.1"
                  class="form-control text-end"
                  required
                >
              </td>

              <td>
                <input
                  type="text"
                  name="components[0][notes]"
                  class="form-control"
                  maxlength="255"
                >
              </td>

              <td class="text-end">
                <button
                  type="button"
                  class="btn btn-outline-danger btn-sm btnRemoveRow"
                  disabled
                >
                  Hapus
                </button>
              </td>
            </tr>

          </tbody>
        </table>
      </div>

      <div class="text-muted small mt-2">
        Qty minimal <b>0.1</b>. Komponen disimpan sebagai <b>Variant (unik)</b>.
      </div>
    </div>

    @include('layouts.partials.form_footer', [
      'cancelUrl' => route('manufacture-recipes.index'),
      'cancelLabel' => 'Batal',
      'cancelInline' => true,
      'buttons' => [
        ['type' => 'submit', 'label' => 'Simpan Resep']
      ]
    ])
  </div>
</form>

{{-- JS --}}
@push('scripts')
<script>
(function () {
  const tableBody = document.querySelector('#componentsTable tbody');
  const btnAdd = document.getElementById('btnAddComponent');
  if (!tableBody || !btnAdd) return;

  // Simpan template row MENTAH (sebelum TomSelect wrap DOM)
  const templateRow = tableBody.querySelector('tr.component-row')?.cloneNode(true);

  function initVariantPicker(selectEl) {
    if (!selectEl || selectEl.tomselect) return;

    new TomSelect(selectEl, {
      valueField: 'uid',      // <-- penting
      labelField: 'name',     // tampilkan nama
      searchField: 'name',
      maxItems: 1,
      create: false,

      preload: 'focus',
      openOnFocus: true,
      dropdownParent: 'body',

      // UI: variant ada "-" di depan, item biasa tidak
      render: {
        option: function(item, escape) {
          const name = escape(item.name || '');
          const prefix = item.type === 'variant' ? '- ' : '';
          return `<div>${prefix}${name}</div>`;
        },
        item: function(item, escape) {
          const name = escape(item.name || '');
          const prefix = item.type === 'variant' ? '- ' : '';
          return `<div>${prefix}${name}</div>`;
        }
      },

      load: function(query, callback) {
        const q = (query || '').trim();
        fetch(`/api/items/search?q=${encodeURIComponent(q)}`, {
          credentials: 'same-origin',
          headers: {
            'X-Requested-With': 'XMLHttpRequest',
            'Accept': 'application/json',
          },
          cache: 'no-store',
        })
          .then(r => r.text())
          .then(t => {
            const clean = t.replace(/^\uFEFF/, '').trimStart();
            callback(JSON.parse(clean));
          })
          .catch(() => callback());
      }
    });


  }

  function reindex() {
    const rows = tableBody.querySelectorAll('tr.component-row');
    rows.forEach((row, i) => {
      row.querySelectorAll('select, input').forEach(el => {
        if (!el.name) return;
        el.name = el.name.replace(/components\[\d+\]/, `components[${i}]`);
      });

      const btnRemove = row.querySelector('.btnRemoveRow');
      if (btnRemove) btnRemove.disabled = (rows.length === 1);
    });
  }

  // init row pertama
  tableBody.querySelectorAll('.js-variant-picker').forEach(initVariantPicker);
  reindex();

  btnAdd.addEventListener('click', () => {
    if (!templateRow) return;

    const newRow = templateRow.cloneNode(true);

    // reset fields
    newRow.querySelectorAll('input').forEach(i => i.value = '');
    newRow.querySelectorAll('select').forEach(s => {
      // pastikan select mentah
      s.innerHTML = '<option value="">Pilih variant…</option>';
    });

    tableBody.appendChild(newRow);

    // init tomselect untuk row baru
    newRow.querySelectorAll('.js-variant-picker').forEach(initVariantPicker);

    reindex();
  });

  tableBody.addEventListener('click', (e) => {
    if (!e.target.classList.contains('btnRemoveRow')) return;

    const rows = tableBody.querySelectorAll('tr.component-row');
    if (rows.length <= 1) return;

    const row = e.target.closest('tr.component-row');

    // destroy tomselect sebelum remove
    row.querySelectorAll('select').forEach(s => {
      if (s.tomselect) s.tomselect.destroy();
    });

    row.remove();
    reindex();
  });
})();
</script>
@endpush
@endsection
