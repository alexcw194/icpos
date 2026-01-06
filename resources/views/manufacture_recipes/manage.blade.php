@extends('layouts.tabler')

@section('title', 'Kelola Resep')

@section('content')
  <form method="POST" action="{{ route('manufacture-recipes.bulk-update', $parentItem) }}">
    @csrf
    @method('PUT')

    <div class="card">
      <div class="card-header d-flex justify-content-between align-items-center">
        <div>
          <h3 class="card-title mb-0">Kelola Resep: {{ $parentItem->name }}</h3>
          <div class="text-muted small">
            SKU: {{ $parentItem->sku ?? '—' }}
          </div>
        </div>

        <button type="button" class="btn btn-outline-primary btn-sm" id="btnAddComponent">
          + Tambah Komponen
        </button>
      </div>

      <div class="card-body">
        <div class="table-responsive">
          <table class="table table-sm align-middle" id="componentsTable">
            <thead>
              <tr>
                <th style="width:55%">Item</th>
                <th class="text-end" style="width:20%">Qty</th>
                <th style="width:20%">Catatan</th>
                <th class="text-end" style="width:5%"></th>
              </tr>
            </thead>
            <tbody>
              @php
                $rows = $recipes->count() ? $recipes : collect([null]);
              @endphp

              @foreach($rows as $i => $row)
                <tr class="component-row">
                  <td>
                    <select name="components[{{ $i }}][component_item_id]" class="form-select component-select" required>
                      @foreach($componentItems as $item)
                        <option
                          value="{{ $item->id }}"
                          @if($row && (int)$row->component_item_id === (int)$item->id) selected @endif
                        >
                          {{ $item->name }}
                        </option>
                      @endforeach
                    </select>
                  </td>

                  <td>
                    <input
                      type="number"
                      name="components[{{ $i }}][qty_required]"
                      step="0.1"
                      min="0.1"
                      class="form-control text-end"
                      value="{{ $row ? number_format((float) $row->qty_required, 1, '.', '') : '' }}"
                      required
                    >
                  </td>

                  <td>
                    <input
                      type="text"
                      name="components[{{ $i }}][notes]"
                      class="form-control"
                      maxlength="255"
                      value="{{ $row?->notes }}"
                    >
                  </td>

                  <td class="text-end">
                    <button type="button" class="btn btn-outline-danger btn-sm btnRemoveRow">
                      Hapus
                    </button>
                  </td>
                </tr>
              @endforeach
            </tbody>
          </table>
        </div>

        <div class="text-muted small">
          Penghapusan komponen dilakukan via <b>bulk save</b> untuk menjaga safety dan mengurangi risiko salah hapus.
        </div>
      </div>

      @include('layouts.partials.form_footer', [
        'cancelUrl' => route('manufacture-recipes.index'),
        'cancelLabel' => 'Kembali',
        'cancelInline' => true,
        'buttons' => [
          ['type' => 'submit', 'label' => 'Simpan Perubahan']
        ]
      ])
    </div>

    {{-- Modal confirm remove row --}}
    <div class="modal modal-blur fade" id="confirmRemoveModal" tabindex="-1" aria-hidden="true">
      <div class="modal-dialog modal-sm modal-dialog-centered" role="document">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title">Konfirmasi</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>
          <div class="modal-body">
            Hapus komponen ini dari resep <b>{{ $parentItem->name }}</b>?
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Batal</button>
            <button type="button" class="btn btn-danger" id="btnConfirmRemove">Hapus</button>
          </div>
        </div>
      </div>
    </div>

  </form>

  @push('scripts')
  <script>
    (function () {
      const parentId = {{ (int) $parentItem->id }};
      const tableBody = document.querySelector('#componentsTable tbody');
      const btnAdd = document.getElementById('btnAddComponent');

      const modalEl = document.getElementById('confirmRemoveModal');
      const modal = modalEl ? new bootstrap.Modal(modalEl) : null;
      const btnConfirmRemove = document.getElementById('btnConfirmRemove');
      let rowToRemove = null;

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

      btnAdd?.addEventListener('click', () => {
        const firstRow = tableBody.querySelector('tr.component-row');
        if (!firstRow) return;

        const newRow = firstRow.cloneNode(true);

        newRow.querySelectorAll('input').forEach(i => i.value = '');
        newRow.querySelectorAll('select').forEach(s => s.selectedIndex = 0);

        tableBody.appendChild(newRow);
        reindex();
      });

      tableBody?.addEventListener('click', (e) => {
        if (!e.target.classList.contains('btnRemoveRow')) return;

        const rows = tableBody.querySelectorAll('tr.component-row');
        if (rows.length <= 1) return;

        rowToRemove = e.target.closest('tr.component-row');
        if (!modal) {
          // fallback kalau bootstrap modal tidak available
          if (confirm('Hapus komponen ini dari resep?')) {
            rowToRemove.remove();
            rowToRemove = null;
            reindex();
          }
          return;
        }
        modal.show();
      });

      btnConfirmRemove?.addEventListener('click', () => {
        if (!rowToRemove) return;
        rowToRemove.remove();
        rowToRemove = null;
        modal?.hide();
        reindex();
      });

      document.querySelector('form')?.addEventListener('submit', (e) => {
        const rows = tableBody.querySelectorAll('tr.component-row');
        const ids = [];

        for (const row of rows) {
          const sel = row.querySelector('select.component-select');
          const qty = row.querySelector('input[name*="[qty_required]"]');
          if (!sel || !qty) continue;

          const id = parseInt(sel.value, 10);

          if (id === parentId) {
            alert('Komponen tidak boleh sama dengan Item Hasil.');
            e.preventDefault(); return;
          }

          const q = parseFloat(qty.value || '0');
          if (!(q > 0)) {
            alert('Qty harus lebih dari 0.');
            e.preventDefault(); return;
          }

          ids.push(id);
        }

        const unique = new Set(ids);
        if (unique.size !== ids.length) {
          alert('Komponen tidak boleh duplikat.');
          e.preventDefault(); return;
        }
      });

      reindex();
    })();
  </script>
  @endpush

@endsection
