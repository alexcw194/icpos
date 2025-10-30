@extends('layouts.tabler')

@section('content')
<div class="container-xl">
  <form method="POST" action="{{ route('po.store') }}">
    @csrf
    <div class="card">
      <div class="card-header"><h3 class="card-title">Create Purchase Order</h3></div>
      <div class="card-body">
        <div class="row g-3">
          <div class="col-md-3">
            <label class="form-label">Company</label>
            <select name="company_id" class="form-select" required>
              @foreach($companies as $c)
              <option value="{{ $c->id }}">{{ $c->alias ?? $c->name }}</option>
              @endforeach
            </select>
          </div>
          <div class="col-md-3">
            <label class="form-label">Warehouse</label>
            <select name="warehouse_id" class="form-select">
              <option value="">—</option>
              @foreach($warehouses as $w)
              <option value="{{ $w->id }}">{{ $w->name }}</option>
              @endforeach
            </select>
          </div>
          <div class="col-md-3">
            <label class="form-label">Supplier Name</label>
            <input type="text" name="supplier_name" class="form-control" placeholder="Supplier…" required>
          </div>
          <div class="col-md-3">
            <label class="form-label">PO Number</label>
            <input type="text" name="number" class="form-control" placeholder="Auto/Manual">
          </div>
          <div class="col-md-3">
            <label class="form-label">PO Date</label>
            <input type="date" name="po_date" class="form-control" value="{{ now()->toDateString() }}">
          </div>
          <div class="col-12">
            <label class="form-label">Notes</label>
            <textarea name="notes" class="form-control" rows="2"></textarea>
          </div>
        </div>

        <hr class="my-4">

        <div class="table-responsive">
          <table class="table" id="po-lines">
            <thead>
              <tr>
                <th style="width:35%">Item</th>
                <th style="width:20%">Variant</th>
                <th style="width:10%">Qty</th>
                <th style="width:10%">UoM</th>
                <th style="width:15%">Unit Price</th>
                <th style="width:10%"></th>
              </tr>
            </thead>
            <tbody>
              <tr>
                <td>
                  <select name="lines[0][item_id]" class="form-select" required>
                    @foreach($items as $it)
                    <option value="{{ $it->id }}">{{ $it->sku }} — {{ $it->name }}</option>
                    @endforeach
                  </select>
                </td>
                <td>
                  <select name="lines[0][item_variant_id]" class="form-select">
                    <option value="">—</option>
                    {{-- optionally inject variants via JS later --}}
                  </select>
                </td>
                <td><input type="number" name="lines[0][qty]" class="form-control" step="0.0001" min="0" required></td>
                <td><input type="text" name="lines[0][uom]" class="form-control" value="PCS"></td>
                <td><input type="number" name="lines[0][unit_price]" class="form-control" step="0.01" min="0"></td>
                <td>
                  <button type="button" class="btn btn-sm btn-outline-secondary" onclick="addLine()">+</button>
                </td>
              </tr>
            </tbody>
          </table>
        </div>

      </div>
      <div class="card-footer d-flex">
        <a href="{{ route('po.index') }}" class="btn btn-link">Cancel</a>
        <button class="btn btn-primary ms-auto" type="submit">Save PO</button>
      </div>
    </div>
  </form>
</div>
<script>
let i = 1;
function addLine() {
  const tbody = document.querySelector('#po-lines tbody');
  const tr = document.createElement('tr');
  tr.innerHTML = `
    <td>
      <select name="lines[${i}][item_id]" class="form-select" required>
        @foreach($items as $it)
        <option value="{{ $it->id }}">{{ $it->sku }} — {{ $it->name }}</option>
        @endforeach
      </select>
    </td>
    <td>
      <select name="lines[${i}][item_variant_id]" class="form-select"><option value="">—</option></select>
    </td>
    <td><input type="number" name="lines[${i}][qty]" class="form-control" step="0.0001" min="0" required></td>
    <td><input type="text" name="lines[${i}][uom]" class="form-control" value="PCS"></td>
    <td><input type="number" name="lines[${i}][unit_price]" class="form-control" step="0.01" min="0"></td>
    <td><button type="button" class="btn btn-sm btn-outline-danger" onclick="this.closest('tr').remove()">−</button></td>`;
  tbody.appendChild(tr); i++;
}
</script>
@endsection
