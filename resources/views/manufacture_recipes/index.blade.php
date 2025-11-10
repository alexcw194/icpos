<x-layouts.tabler title="Resep Produksi">
  <div class="card">
    <div class="card-header d-flex justify-content-between">
      <h3 class="card-title">Daftar Resep</h3>
      <a href="{{ route('manufacture-recipes.create') }}" class="btn btn-primary">+ Tambah Resep</a>
    </div>

    <div class="table-responsive">
      <table class="table card-table table-vcenter">
        <thead>
          <tr>
            <th>Item Hasil</th>
            <th>Komponen</th>
            <th>Qty</th>
            <th>Catatan</th>
            <th></th>
          </tr>
        </thead>
        <tbody>
          @foreach($recipes as $r)
          <tr>
            <td>{{ $r->parentItem->name }}</td>
            <td>{{ $r->componentItem->name }}</td>
            <td>{{ number_format($r->qty_required, 3) }}</td>
            <td>{{ $r->notes }}</td>
            <td class="text-end">
              <form method="POST" action="{{ route('manufacture-recipes.destroy', $r) }}">
                @csrf @method('DELETE')
                <button class="btn btn-sm btn-danger">Hapus</button>
              </form>
            </td>
          </tr>
          @endforeach
        </tbody>
      </table>
    </div>
    <div class="card-footer">{{ $recipes->links() }}</div>
  </div>
</x-layouts.tabler>
