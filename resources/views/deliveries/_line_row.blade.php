@php
  // Extract line parameters from the existing row or default values
  $itemId           = $row['item_id']           ?? null;
  $variantId        = $row['item_variant_id']   ?? null;
  $description      = $row['description']       ?? null;
  $unit             = $row['unit']              ?? null;
  $qty              = $row['qty']               ?? 1;
  $qtyRequested     = $row['qty_requested']     ?? null;
  $qtyBackordered   = $row['qty_backordered']   ?? null;
  $priceSnapshot    = $row['price_snapshot']    ?? null;
  $lineNotes        = $row['line_notes']        ?? null;
  $quotationLineId  = $row['quotation_line_id'] ?? null;
  $soLineId         = $row['sales_order_line_id'] ?? null;

  // Resolve the variant name for display
  $variantName = '';
  foreach ($variants as $v) {
    if ($v->id == $variantId) {
      $variantName = $v->name;
      break;
    }
  }

  // Resolve the item name for display
  $itemName = '';
  foreach ($items as $itm) {
    if ($itm->id == $itemId) {
      $itemName = $itm->name;
      break;
    }
  }

  // Combine item and variant names for a unified label
  $combinedName = trim($itemName . ($variantName ? ' - ' . $variantName : ''));

  // Fallback: jika combinedName kosong tetapi deskripsi ada (nama barang lama), gunakan deskripsi sebagai label item
  if (empty($combinedName) && !empty($description)) {
      $combinedName = trim($description);
      $description  = null; // kosongkan kolom deskripsi agar tidak rangkap
  }
@endphp

<tr data-index="{{ $index }}">
  <td>
    @if($soLineId)
      {{-- When the line originates from a Sales Order, simply display the combined label. --}}
      <input type="hidden" name="lines[{{ $index }}][item_id]" value="{{ $itemId }}">
      <input type="hidden" name="lines[{{ $index }}][item_variant_id]" value="{{ $variantId }}">
      <input type="hidden" name="lines[{{ $index }}][quotation_line_id]" value="{{ $quotationLineId }}">
      <input type="hidden" name="lines[{{ $index }}][sales_order_line_id]" value="{{ $soLineId }}">
      <div>{{ $combinedName ?: '—' }}</div>
      <div class="small text-muted mt-1">Stock: <span data-stock-label>&mdash;</span></div>
    @else
      {{-- For manual lines, present a single dropdown listing items (and variants) as a unified option. --}}
      <select name="lines[{{ $index }}][item_variant_id]" class="form-select line-item-variant" data-item-field="lines[{{ $index }}][item_id]">
        <option value="">-- pilih item --</option>
        {{-- Options for items without variants --}}
        @foreach($items as $itm)
          @php
            // Check if this item has any variants attached
            $hasVar = false;
            foreach ($variants as $vv) {
              if ($vv->item_id == $itm->id) { $hasVar = true; break; }
            }
          @endphp
          @if(!$hasVar)
            <option value="" data-item="{{ $itm->id }}" @if($itemId == $itm->id && $variantId == null) selected @endif>
              {{ $itm->name }}
            </option>
          @endif
        @endforeach
        {{-- Options for each variant, label is "Item - Variant" --}}
        @foreach($variants as $vv)
          @php
            $lbl = $vv->item->name . ($vv->name ? ' - ' . $vv->name : '');
          @endphp
          <option value="{{ $vv->id }}" data-item="{{ $vv->item_id }}" @if($variantId == $vv->id) selected @endif>
            {{ $lbl }}
          </option>
        @endforeach
      </select>
      {{-- Hidden fields to capture the selected item ID and keep other identifiers intact --}}
      <input type="hidden" name="lines[{{ $index }}][item_id]" value="{{ $itemId }}">
      <input type="hidden" name="lines[{{ $index }}][quotation_line_id]" value="{{ $quotationLineId }}">
      <input type="hidden" name="lines[{{ $index }}][sales_order_line_id]" value="{{ $soLineId }}">
      <div class="small text-muted mt-1">Stock: <span data-stock-label>&mdash;</span></div>
    @endif
  </td>
  <td>
    <input type="text" name="lines[{{ $index }}][description]" class="form-control" value="{{ $description }}" {{ $soLineId ? 'readonly' : '' }}>
  </td>
  <td>
    <input type="number" step="0.0001" min="0" name="lines[{{ $index }}][qty]" class="form-control text-end line-qty" value="{{ $qty }}">
  </td>
  <td>
    <input type="text" name="lines[{{ $index }}][unit]" class="form-control" value="{{ $unit }}">
  </td>
  <td>
    <input type="number" step="0.0001" min="0" name="lines[{{ $index }}][qty_requested]" class="form-control text-end line-requested" value="{{ $qtyRequested }}">
  </td>
  <td>
    <input type="number" step="0.0001" min="0" name="lines[{{ $index }}][qty_backordered]" class="form-control text-end" value="{{ $qtyBackordered }}">
  </td>
  <td>
    <input type="number" step="0.01" min="0" name="lines[{{ $index }}][price_snapshot]" class="form-control text-end" value="{{ $priceSnapshot }}">
  </td>
  <td>
    <input type="text" name="lines[{{ $index }}][line_notes]" class="form-control" value="{{ $lineNotes }}">
  </td>
  <td class="text-end">
    <button type="button" class="btn btn-sm btn-outline-danger remove-line"><i class="ti ti-x"></i></button>
  </td>
</tr>
