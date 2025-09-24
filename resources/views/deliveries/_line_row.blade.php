@php
  $itemId = $row['item_id'] ?? null;
  $variantId = $row['item_variant_id'] ?? null;
  $description = $row['description'] ?? null;
  $unit = $row['unit'] ?? null;
  $qty = $row['qty'] ?? 1;
  $qtyRequested = $row['qty_requested'] ?? null;
  $qtyBackordered = $row['qty_backordered'] ?? null;
  $priceSnapshot = $row['price_snapshot'] ?? null;
  $lineNotes = $row['line_notes'] ?? null;
  $quotationLineId = $row['quotation_line_id'] ?? null;
@endphp
<tr data-index="{{ $index }}">
  <td>
    <select name="lines[{{ $index }}][item_id]" class="form-select line-item">
      <option value="">-- pilih --</option>
      @foreach($items as $item)
        <option value="{{ $item->id }}" @selected($itemId == $item->id)> {{ $item->name }} </option>
      @endforeach
    </select>
    <div class="small text-muted mt-1">Stock: <span data-stock-label>&mdash;</span></div>
    <input type="hidden" name="lines[{{ $index }}][quotation_line_id]" value="{{ $quotationLineId }}">
    <input type="hidden" name="lines[{{ $index }}][sales_order_line_id]" value="{{ $row['sales_order_line_id'] ?? null }}">
  </td>
  <td>
    <select name="lines[{{ $index }}][item_variant_id]" class="form-select line-variant">
      <option value="">--</option>
      @foreach($variants as $variant)
        <option value="{{ $variant->id }}" data-item="{{ $variant->item_id }}" @selected($variantId == $variant->id)>
          {{ $variant->name }}
        </option>
      @endforeach
    </select>
  </td>
  <td><input type="text" name="lines[{{ $index }}][description]" class="form-control" value="{{ $description }}"></td>
  <td><input type="number" step="0.0001" min="0" name="lines[{{ $index }}][qty]" class="form-control text-end line-qty" value="{{ $qty }}"></td>
  <td><input type="text" name="lines[{{ $index }}][unit]" class="form-control" value="{{ $unit }}"></td>
  <td><input type="number" step="0.0001" min="0" name="lines[{{ $index }}][qty_requested]" class="form-control text-end line-requested" value="{{ $qtyRequested }}"></td>
  <td><input type="number" step="0.0001" min="0" name="lines[{{ $index }}][qty_backordered]" class="form-control text-end" value="{{ $qtyBackordered }}"></td>
  <td><input type="number" step="0.01" min="0" name="lines[{{ $index }}][price_snapshot]" class="form-control text-end" value="{{ $priceSnapshot }}"></td>
  <td><input type="text" name="lines[{{ $index }}][line_notes]" class="form-control" value="{{ $lineNotes }}"></td>
  <td class="text-end"><button type="button" class="btn btn-sm btn-outline-danger remove-line"><i class="ti ti-x"></i></button></td>
</tr>

