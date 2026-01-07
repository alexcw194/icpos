@extends('layouts.tabler')

@section('title', 'Kelola Resep Produksi')

@section('content')
<div class="page-header d-print-none">
  <div class="container-xl">
    <div class="row g-2 align-items-center">
      <div class="col">
        <h2 class="page-title mb-0">Kelola Resep Produksi</h2>
        <div class="text-muted mt-1">
          Item Hasil: <b>{{ $parentItem->name }}</b>
        </div>
      </div>
      <div class="col-auto ms-auto d-print-none">
        <a href="{{ route('manufacture-recipes.index') }}" class="btn btn-outline-secondary">
          Kembali
        </a>
      </div>
    </div>
  </div>
</div>

<div class="page-body">
  <div class="container-xl">

    @if ($errors->any())
      <div class="alert alert-danger">
        <div class="fw-bold mb-1">Validasi gagal</div>
        <ul class="mb-0">
          @foreach ($errors->all() as $e)
            <li>{{ $e }}</li>
          @endforeach
        </ul>
      </div>
    @endif

    @if (session('success'))
      <div class="alert alert-success">{{ session('success') }}</div>
    @endif

    <form method="POST" action="{{ route('manufacture-recipes.bulk-update', $parentItem) }}">
      @csrf
      @method('PUT')

      <div class="card">
        <div class="card-header">
          <h3 class="card-title mb-0">Komponen Resep</h3>
          <div class="card-actions">
            <button type="button" class="btn btn-outline-primary btn-sm" id="btnAddComponent">
              + Tambah Komponen
            </button>
          </div>
        </div>

        <div class="card-body">

          @php
            // Normalisasi rows dari old() (kalau validation error) atau dari $recipes
            $oldComponents = old('components');

            if (is_array($oldComponents)) {
              $rows = $oldComponents;
            } else {
              $rows = collect($recipes ?? [])->map(function ($r) {
                // Prioritas:
                // - Kalau ada component_variant_id => kita treat sebagai variant-<id>
                // - Kalau tidak ada => item-<component_item_id>
                $uid = '';
                $text = '';

                if (!empty($r->component_variant_id)) {
                  $uid = 'variant-' . $r->component_variant_id;

                  // Label variant (fallback aman)
                  $text = '- ' . (
                    optional($r->componentVariant)->label
                    ?? optional(optional($r->componentVariant)->item)->name
                    ?? ('Variant #' . $r->component_variant_id)
                  );
                } else {
                  $uid = 'item-' . ($r->component_item_id ?? 0);
                  $text = optional($r->componentItem)->name ?? ('Item #' . ($r->component_item_id ?? 0));
                }

                return [
                  'component_variant_id' => $uid,
                  'qty_required' => $r->qty_required,
                  'notes' => $r->notes,
                  'unit_factor' => $r->unit_factor,
                  '_label' => $text, // dipakai untuk option selected
                ];
              })->values()->all();
            }

            if (empty($rows)) {
              $rows = [[
                'component_variant_id' => '',
                'qty_required' => '',
                'notes' => '',
                'unit_factor' => '',
                '_label' => '',
              ]];
            }
          @endphp

          <div class="table-responsive">
            <table class="table table-sm align-middle" id="componentsTable">
              <thead>
                <tr>
                  <th style="width:55%">Item/Variant</th>
                  <th class="text-end" style="width:15%">Qty</th>
                  <th style="width:25%">Catatan</th>
                  <th class="text-end" style="width:5%"></th>
                </tr>
              </thead>

              <tbody>
                @foreach ($rows as $i => $row)
                  @php
                    $selectedUid = $row['component_variant_id'] ?? '';
                    $selectedLabel = $row['_label'] ?? '';
                    $qty = $row['qty_required'] ?? '';
                    $notes = $row['notes'] ?? '';
                  @endphp

                  <tr class="component-row">
                    <td>
                      <select name="components[{{ $i }}][component_variant_id]"
                              class="form-select js-item-picker"
                              required>
                        <option value="">Pilih item…</option>

                        {{-- preselected option (supaya TomSelect kebaca saat edit) --}}
                        @if ($selectedUid)
                          <option value="{{ $selectedUid }}" selected>
                            {{ $selectedLabel ?: $selectedUid }}
                          </option>
                        @endif
                      </select>
                    </td>

                    <td>
                      <input type="number"
                             name="components[{{ $i }}][qty_required]"
                             step="0.1"
                             min="0.1"
                             class="form-control text-end"
                             value="{{ $qty }}"
                             required>
                    </td>

                    <td>
                      <input type="text"
                             name="components[{{ $i }}][notes]"
                             class="form-control"
                             maxlength="255"
                             value="{{ $notes }}">
                    </td>

                    <td class="text-end">
                      <button type="button"
                              class="btn btn-outline-danger btn-sm btnRemoveRow"
                              {{ count($rows) === 1 ? 'disabled' : '' }}>
                        Hapus
                      </button>
                    </td>
                  </tr>
                @endforeach
              </tbody>
            </table>
          </div>

          <div class="text-muted small mt-2">
            Komponen dipilih dari <b>list item seperti Quotation</b>.
            Variant akan tampil dengan prefix <b>-</b>. SKU tidak ditampilkan.
          </div>
        </div>

        @include('layouts.partials.form_footer', [
          'cancelUrl' => route('manufacture-recipes.index'),
          'cancelLabel' => 'Batal',
          'cancelInline' => true,
          'buttons' => [
            ['type' => 'submit', 'label' => 'Simpan Perubahan']
          ]
        ])
      </div>
    </form>

  </div>
</div>

{{-- Template row (kosong) --}}
<template id="componentRowTemplate">
  <tr class="component-row">
    <td>
      <select name="components[__INDEX__][component_variant_id]"
              class="form-select js-item-picker"
              required>
        <option value="">Pilih item…</option>
      </select>
    </td>
    <td>
      <input type="number"
             name="components[__INDEX__][qty_required]"
             step="0.1"
             min="0.1"
             class="form-control text-end"
             required>
    </td>
    <td>
      <input type="text"
             name="components[__INDEX__][notes]"
             class="form-control"
             maxlength="255">
    </td>
    <td class="text-end">
      <button type="button" class="btn btn-outline-danger btn-sm btnRemoveRow">
        Hapus
      </button>
    </td>
  </tr>
</template>

@push('scripts')
<script>
(function () {
  const tableBody = document.querySelector('#componentsTable tbody');
  const btnAdd = document.getElementById('btnAddComponent');
  const tpl = document.getElementById('componentRowTemplate');

  if (!tableBody || !btnAdd || !tpl) return;

  const parentItemId = {{ (int) $parentItem->id }};

  function stripBom(text) {
    if (!text) return text;
    return text.charCodeAt(0) === 0xFEFF ? text.slice(1) : text;
  }

  function initItemPicker(selectEl) {
    if (!selectEl || selectEl.tomselect) return;

    new TomSelect(selectEl, {
      valueField: 'uid',
      labelField: 'name',         // TAMPILAN CLEAN (tanpa SKU)
      searchField: ['name','sku','label'],
      maxItems: 1,
      create: false,

      preload: 'focus',
      openOnFocus: true,
      shouldLoad: () => true,
      minLength: 0,
      dropdownParent: 'body',

      load: function (query, callback) {
        const q = (query || '').trim();
        fetch(`/api/items/search?q=${encodeURIComponent(q)}`, { credentials: 'same-origin' })
          .then(async (res) => {
            if (!res.ok) throw new Error(`HTTP ${res.status}`);
            const t = stripBom(await res.text());
            return JSON.parse(t);
          })
          .then((data) => callback(data))
          .catch((err) => {
            console.error('Item search failed:', err);
            callback();
          });
      },

      // Render dropdown tanpa SKU
      render: {
        option: function (item, escape) {
          return `<div>${escape(item.name || item.label || '')}</div>`;
        },
        item: function (item, escape) {
          return `<div>${escape(item.name || item.label || '')}</div>`;
        }
      },

      onChange: function (val) {
        // Guard: jangan boleh ambil item hasil sebagai komponen
        if (val === `item-${parentItemId}`) {
          this.clear(true);
          alert('Komponen tidak boleh sama dengan Item Hasil.');
        }
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

  // init semua picker yang sudah ada (hasil render server)
  tableBody.querySelectorAll('.js-item-picker').forEach(initItemPicker);
  reindex();

  btnAdd.addEventListener('click', () => {
    const frag = tpl.content.cloneNode(true);
    tableBody.appendChild(frag);

    // reindex dulu biar name components[__] bener
    reindex();

    // init tomselect untuk row terakhir
    const lastRow = tableBody.querySelector('tr.component-row:last-child');
    const sel = lastRow ? lastRow.querySelector('.js-item-picker') : null;
    initItemPicker(sel);
  });

  tableBody.addEventListener('click', (e) => {
    const btn = e.target.closest('.btnRemoveRow');
    if (!btn) return;

    const rows = tableBody.querySelectorAll('tr.component-row');
    if (rows.length <= 1) return;

    const row = btn.closest('tr.component-row');
    if (!row) return;

    // destroy tomselect biar gak leak
    const sel = row.querySelector('.js-item-picker');
    if (sel && sel.tomselect) sel.tomselect.destroy();

    row.remove();
    reindex();
  });

})();
</script>
@endpush
@endsection