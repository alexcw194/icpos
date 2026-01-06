@extends('layouts.tabler')

@section('content')
  <form method="POST" action="{{ route('manufacture-recipes.store') }}">
    @csrf

    <div class="mb-3">
      <label class="form-label">Item Hasil</label>
      <select name="parent_item_id" class="form-select" required>
        @foreach($parentItems as $item)
          <option value="{{ $item->id }}">{{ $item->name }}</option>
        @endforeach
      </select>
    </div>

    <div class="d-flex justify-content-between align-items-center mb-2">
      <label class="form-label mb-0">Komponen</label>
      <button type="button" class="btn btn-outline-primary btn-sm" id="btnAddComponent">
        + Tambah Komponen
      </button>
    </div>

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
          <tr class="component-row">
            <td>
              <select name="components[0][component_item_id]" class="form-select" required>
                @foreach($componentItems as $item)
                  <option value="{{ $item->id }}">{{ $item->name }}</option>
                @endforeach
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
              <button type="button" class="btn btn-outline-danger btn-sm btnRemoveRow" disabled>
                Hapus
              </button>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    @include('layouts.partials.form_footer', [
      'cancelUrl' => route('manufacture-recipes.index'),
      'cancelLabel' => 'Batal',
      'cancelInline' => true,
      'buttons' => [['type' => 'submit', 'label' => 'Simpan']]
    ])
  </form>

  <script>
    (function () {
      const tableBody = document.querySelector('#componentsTable tbody');
      const btnAdd = document.getElementById('btnAddComponent');

      function reindex() {
        const rows = tableBody.querySelectorAll('tr.component-row');
        rows.forEach((row, i) => {
          row.querySelectorAll('select, input').forEach(el => {
            el.name = el.name.replace(/components\[\d+\]/, `components[${i}]`);
          });

          const btnRemove = row.querySelector('.btnRemoveRow');
          btnRemove.disabled = (rows.length === 1);
        });
      }

      btnAdd.addEventListener('click', () => {
        const firstRow = tableBody.querySelector('tr.component-row');
        const newRow = firstRow.cloneNode(true);

        // reset values
        newRow.querySelectorAll('input').forEach(i => i.value = '');
        newRow.querySelectorAll('select').forEach(s => s.selectedIndex = 0);

        tableBody.appendChild(newRow);
        reindex();
      });

      tableBody.addEventListener('click', (e) => {
        if (!e.target.classList.contains('btnRemoveRow')) return;

        const rows = tableBody.querySelectorAll('tr.component-row');
        if (rows.length <= 1) return;

        e.target.closest('tr.component-row').remove();
        reindex();
      });

      reindex();
    })();
  </script>
@endsection
