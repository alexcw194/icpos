{{-- resources/views/quotations/_items_table.blade.php --}}
<div id="quotation-lines" class="table-responsive">
  <table class="table table-sm">
    <thead>
      <tr>
        <th class="w-1">#</th>
        <th>Item</th>
        <th class="text-end">Qty</th>
        <th class="text-end">Rate</th>
        <th class="text-end">Amount</th>
      </tr>
    </thead>
    <tbody>
      @forelse(($quotation->items ?? []) as $i => $it)
        <tr class="qline">
          <td>{{ $i + 1 }}</td>
          <td class="text-wrap">
            {{ $it->description ?? ($it->item->name ?? '-') }}
          </td>
          <td class="text-end">
            {{-- qty: tampilkan tanpa .00 jika tidak perlu --}}
            {{ rtrim(rtrim(number_format((float)($it->qty ?? 0), 2, ',', '.'), '0'), ',') }}
          </td>
          <td class="text-end">
            {{ number_format((float)($it->rate ?? 0), 2, ',', '.') }}
          </td>
          <td class="text-end">
            {{ number_format((float)($it->amount ?? 0), 2, ',', '.') }}
          </td>
        </tr>
      @empty
        <tr>
          <td colspan="5" class="text-center text-muted">Belum ada item.</td>
        </tr>
      @endforelse
    </tbody>
  </table>
</div>
