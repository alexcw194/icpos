<tr class="qline" data-line-row>
  <td>
    {{-- Nama item (diisi oleh quick-search) --}}
    <input
      type="text"
      name="lines[{{ $i }}][name]"
      class="form-control q-item-name"
      value="{{ old("lines.$i.name", $line->name ?? '') }}"
      required
    >

    {{-- Unit: tetap hidden seperti desainmu (JANGAN beri class .q-item-unit agar script tidak mencari <select>) --}}
    <input
      type="hidden"
      name="lines[{{ $i }}][unit]"
      value="{{ old("lines.$i.unit", $line->unit ?? '') }}"
    >

    {{-- Deskripsi: quick-search akan mengisi jika kosong --}}
    <input
      type="hidden"
      name="lines[{{ $i }}][description]"
      class="q-item-desc"
      value="{{ old("lines.$i.description", $line->description ?? '') }}"
    >
  </td>

  <td style="width:110px">
    {{-- Qty --}}
    <input
      type="text"
      name="lines[{{ $i }}][qty]"
      class="form-control text-end q-item-qty"
      value="{{ old("lines.$i.qty", $line->qty ?? 1) }}"
    >
  </td>

  <td style="width:150px">
    {{-- Harga (unit_price) --}}
    <input
      type="text"
      name="lines[{{ $i }}][unit_price]"
      class="form-control text-end q-item-rate"
      value="{{ old("lines.$i.unit_price", $line->unit_price ?? 0) }}"
    >
  </td>

  <td class="w-25">
    <div class="row g-2">
      <div class="col-auto">
        @php $oldType = old("lines.$i.discount_type", $line->discount_type ?? 'amount'); @endphp
        <select name="lines[{{ $i }}][discount_type]" class="form-select disc-type">
          <option value="amount"  {{ $oldType=='amount'  ? 'selected' : '' }}>Nominal (IDR)</option>
          <option value="percent" {{ $oldType=='percent' ? 'selected' : '' }}>Persen (%)</option>
        </select>
      </div>
      <div class="col">
        <div class="input-group">
          <input
            type="text"
            name="lines[{{ $i }}][discount_value]"
            class="form-control text-end disc-value"
            value="{{ old("lines.$i.discount_value", $line->discount_value ?? 0) }}"
          >
          <span class="input-group-text disc-unit">IDR</span>
        </div>
      </div>
    </div>
  </td>

  <td class="text-end">
    <span data-line-subtotal>
      {{ number_format($line->line_subtotal ?? 0, 0, ',', '.') }}
    </span>
  </td>
  <td class="text-end">
    <span data-line-disc-amount>
      {{ number_format($line->discount_amount ?? 0, 0, ',', '.') }}
    </span>
  </td>
  <td class="text-end">
    <span data-line-total>
      {{ number_format($line->line_total ?? 0, 0, ',', '.') }}
    </span>
  </td>
</tr>
