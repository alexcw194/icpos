@extends('layouts.tabler')

@section('content')
<div class="container-xl">
  @php $scope = $listType ?? request('list_type', ''); @endphp
  @php $canDeleteAdjustment = auth()->user()?->hasAnyRole(['Admin','SuperAdmin']) ?? false; @endphp
  @if(session('success'))
    <div class="alert alert-success mb-3">
      {{ session('success') }}
    </div>
  @endif
  @if(session('error'))
    <div class="alert alert-danger mb-3">
      {{ session('error') }}
    </div>
  @endif
  <div class="card">
    <div class="card-header d-flex">
      <h3 class="card-title">Stock Adjustments</h3>
      <a href="{{ route('inventory.adjustments.create', request()->only('list_type')) }}" class="btn btn-primary ms-auto">+ New Adjustment</a>
    </div>

    <div class="card-body border-bottom pb-3">
      <form method="GET" class="row g-2 align-items-end">
        <div class="col-md-3">
          <label class="form-label">Scope</label>
          <select name="list_type" class="form-select">
            <option value="" @selected($scope === '')>All</option>
            <option value="retail" @selected($scope === 'retail')>Item</option>
            <option value="project" @selected($scope === 'project')>Project</option>
          </select>
        </div>
        <div class="col-md-2">
          <button class="btn btn-primary w-100">Filter</button>
        </div>
      </form>
    </div>

    <div class="table-responsive">
      <table class="table card-table">
        <thead><tr>
          <th>Date</th><th>Item</th>
          <th>Warehouse</th><th class="text-end">Qty Adj.</th><th>Reason</th><th>By</th>
          @if($canDeleteAdjustment)
            <th class="text-end">Action</th>
          @endif
        </tr></thead>
        <tbody>
          @forelse($adjustments as $adj)
            @php
              $itemLabel = $adj->item->name ?? '-';
              $variantLabel = $adj->variant?->label ?? $adj->variant?->sku ?? ($adj->variant_id ? ('#'.$adj->variant_id.' (deleted)') : null);
              if ($variantLabel) {
                $itemLabel .= ' - ' . $variantLabel;
              }
            @endphp
            <tr>
              <td>{{ $adj->created_at->format('d M Y H:i') }}</td>
              <td>{{ $itemLabel }}</td>
              <td>{{ $adj->warehouse->name ?? '-' }}</td>
              <td class="text-end">{{ number_format($adj->qty_adjustment, 2, ',', '.') }}</td>
              <td>{{ $adj->reason }}</td>
              <td>{{ $adj->created_by }}</td>
              @if($canDeleteAdjustment)
                <td class="text-end">
                  <form action="{{ route('inventory.adjustments.destroy', ['adjustment' => $adj->id] + request()->only('list_type')) }}" method="POST" class="d-inline" onsubmit="return confirm('Hapus adjustment ini? Stok dan summary akan ikut terkoreksi.');">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="btn btn-sm btn-outline-danger">Delete</button>
                  </form>
                </td>
              @endif
            </tr>
          @empty
            <tr><td colspan="{{ $canDeleteAdjustment ? 7 : 6 }}" class="text-center text-muted">No adjustment history.</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>
    <div class="card-footer">{{ $adjustments->links() }}</div>
  </div>
</div>
@endsection


