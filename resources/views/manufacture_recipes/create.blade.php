<x-layouts.tabler title="Tambah Resep Produksi">
  <form method="POST" action="{{ route('manufacture-recipes.store') }}">
    @csrf
    <div class="mb-3">
      <label class="form-label">Item Hasil</label>
      <select name="parent_item_id" class="form-select" required>
        @foreach($items as $item)
          <option value="{{ $item->id }}">{{ $item->name }}</option>
        @endforeach
      </select>
    </div>

    <div class="mb-3">
      <label class="form-label">Komponen</label>
      <select name="component_item_id" class="form-select" required>
        @foreach($items as $item)
          <option value="{{ $item->id }}">{{ $item->name }}</option>
        @endforeach
      </select>
    </div>

    <div class="mb-3">
      <label class="form-label">Qty Diperlukan</label>
      <input type="number" name="qty_required" step="0.001" class="form-control" required>
    </div>

    <div class="mb-3">
      <label class="form-label">Catatan</label>
      <input type="text" name="notes" class="form-control">
    </div>

    @include('layouts.partials.form_footer', [
      'cancelUrl' => route('manufacture-recipes.index'),
      'cancelLabel' => 'Batal',
      'cancelInline' => true,
      'buttons' => [['type' => 'submit', 'label' => 'Simpan']]
    ])
  </form>
</x-layouts.tabler>
