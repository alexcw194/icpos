@extends('layouts.tabler')

@section('content')
<div class="container-xl">
  <form method="post" action="{{ route('inventory.adjustments.store') }}" class="card">
    @csrf
    <div class="card-header">
      <h3 class="card-title">New Stock Adjustment</h3>
    </div>
    <div class="card-body">
      @php
        $selectedItemId = old('item_id', $item->id ?? null);
        $selectedVariantId = old('variant_id', $selectedVariantId ?? null);
        $selectedWarehouseId = old('warehouse_id', request('warehouse_id'));
        $variantsForSelect = $item ? $variants : $variantsAll;
      @endphp

      <div class="mb-3">
        <label class="form-label">Item</label>
        @if($item)
          <div class="fw-semibold">{{ $item->name }}</div>
          <input type="hidden" name="item_id" value="{{ $selectedItemId }}">
        @else
          <select name="item_id" class="form-select" id="adjustItemSelect" required>
            <option value="">-- pilih item --</option>
            @foreach($items as $it)
              <option value="{{ $it->id }}" @selected((string)$selectedItemId === (string)$it->id)>
                {{ $it->name }}
              </option>
            @endforeach
          </select>
          <div class="form-hint">Item master dibuat oleh SuperAdmin.</div>
        @endif
        <input type="hidden" name="company_id" value="{{ auth()->user()->company_id }}">
      </div>

      <div class="mb-3">
        <label class="form-label">Warehouse</label>
        <select name="warehouse_id" class="form-select" id="adjustWarehouseSelect">
          <option value="">Semua Warehouse</option>
          @foreach($warehouses as $w)
            <option value="{{ $w->id }}" @selected((string)$selectedWarehouseId === (string)$w->id)>
              {{ $w->name }}
            </option>
          @endforeach
        </select>
      </div>

      <div class="mb-3">
        <label class="form-label">Variant (opsional)</label>
        <select name="variant_id" class="form-select" id="adjustVariantSelect">
          <option value="">-- tanpa varian --</option>
          @foreach($variantsForSelect as $v)
            @php
              $variantLabel = $v->label ?? ($v->sku ?? ('Variant #'.$v->id));
              $itemLabel = $v->item->name ?? ('Item #'.$v->item_id);
              $label = $item ? $variantLabel : ($itemLabel.' • '.$variantLabel);
            @endphp
            <option value="{{ $v->id }}"
                    data-item-id="{{ $v->item_id }}"
                    @selected((string)$selectedVariantId === (string)$v->id)>
              {{ $label }}
            </option>
          @endforeach
        </select>
      </div>
      <div class="mb-3">
        <label class="form-label">Current Balance</label>
        <div>{{ number_format($summary->qty_balance ?? 0, 2, ',', '.') }}</div>
        @if(!$item)
          <div class="form-hint">Pilih item untuk melihat saldo stok.</div>
        @endif
      </div>
      <div class="mb-3">
        <label class="form-label">Qty Adjustment</label>
        <input type="number" name="qty_adjustment" step="0.0001" class="form-control" required>
      </div>
      <div class="mb-3">
        <label class="form-label">Reason</label>
        <textarea name="reason" class="form-control"></textarea>
      </div>
    </div>
    @include('layouts.partials.form_footer', [
        'cancelUrl' => route('inventory.adjustments.index'),
        'cancelLabel' => 'Cancel',
        'cancelInline' => true,
        'buttons' => [['label' => 'Save Adjustment', 'type' => 'submit', 'class' => 'btn btn-primary']]
    ])
  </form>
</div>
@push('scripts')
<script>
(() => {
  const itemSel = document.getElementById('adjustItemSelect');
  const variantSel = document.getElementById('adjustVariantSelect');
  if (!itemSel || !variantSel) return;

  const allOptions = Array.from(variantSel.options);
  const filterVariants = () => {
    const itemId = itemSel.value;
    allOptions.forEach((opt) => {
      if (!opt.value) return;
      const match = !itemId || opt.dataset.itemId === itemId;
      opt.hidden = !match;
      opt.disabled = !match;
    });

    const selected = variantSel.selectedOptions[0];
    if (selected && selected.disabled) {
      variantSel.value = '';
    }
  };

  itemSel.addEventListener('change', filterVariants);
  variantSel.addEventListener('change', () => {
    const selected = variantSel.selectedOptions[0];
    if (selected && selected.dataset.itemId && itemSel.value !== selected.dataset.itemId) {
      itemSel.value = selected.dataset.itemId;
      filterVariants();
    }
  });

  filterVariants();
})();
</script>
@endpush
@endsection
