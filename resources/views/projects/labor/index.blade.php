@extends('layouts.tabler')

@section('content')
<div class="container-xl">
  <div class="page-header mb-3">
    <div class="row align-items-center">
      <div class="col">
        <div class="page-pretitle">Projects</div>
        <h2 class="page-title">Master Labor</h2>
      </div>
    </div>
  </div>

  <div class="card">
    <div class="card-header d-flex align-items-center gap-2 flex-wrap">
      <div class="btn-group" role="group">
        <a href="{{ route('projects.labor.index', ['type' => 'item', 'q' => $q, 'sub_contractor_id' => $selectedSubContractorId]) }}" class="btn btn-outline-primary {{ $type === 'item' ? 'active' : '' }}">Item Labor</a>
        <a href="{{ route('projects.labor.index', ['type' => 'project', 'q' => $q, 'sub_contractor_id' => $selectedSubContractorId]) }}" class="btn btn-outline-primary {{ $type === 'project' ? 'active' : '' }}">Project Item Labor</a>
      </div>

      @if(!empty($canManageCost) && $subContractors->isNotEmpty())
        <form method="get" class="d-flex align-items-center gap-2">
          <input type="hidden" name="type" value="{{ $type }}">
          <input type="hidden" name="q" value="{{ $q }}">
          <select name="sub_contractor_id" id="labor-sub-contractor" class="form-select form-select-sm">
            @foreach($subContractors as $sub)
              <option value="{{ $sub->id }}" @selected((string) $selectedSubContractorId === (string) $sub->id)>{{ $sub->name }}</option>
            @endforeach
          </select>
        </form>
        <form method="post" action="{{ route('projects.labor.default-sub-contractor') }}" class="d-flex align-items-center gap-2">
          @csrf
          <input type="hidden" name="sub_contractor_id" id="labor-sub-contractor-default" value="{{ $selectedSubContractorId }}">
          <span class="text-muted small">Set as default</span>
          <button class="btn btn-sm btn-outline-secondary">Set</button>
        </form>
      @endif

      <form method="get" class="ms-auto d-flex align-items-center gap-2">
        <input type="hidden" name="type" value="{{ $type }}">
        <input type="search" name="q" class="form-control" placeholder="Cari item / SKU" value="{{ $q }}">
        @if(!empty($selectedSubContractorId))
          <input type="hidden" name="sub_contractor_id" value="{{ $selectedSubContractorId }}">
        @endif
        <button class="btn btn-primary">Cari</button>
      </form>
    </div>

    <div class="table-responsive">
      <table class="table table-sm table-vcenter card-table">
        <thead>
          <tr>
            <th>Item</th>
            <th style="width:140px;">SKU</th>
            <th style="width:160px;" class="text-end">Labor Unit</th>
            @if(!empty($canManageCost))
              <th style="width:160px;" class="text-end">Labor Cost</th>
            @endif
            <th>Notes</th>
            <th style="width:160px;">Updated By</th>
            <th style="width:140px;">Updated At</th>
            <th style="width:1%"></th>
          </tr>
        </thead>
        <tbody>
          @php $canUpdate = $type === 'project' ? $canUpdateProject : $canUpdateItem; @endphp
          @forelse($items as $item)
            @php
              $rateKey = ($item->item_id ?? $item->id) . ':' . ($item->variant_id ?? 0);
              $rate = $rates[$rateKey] ?? null;
              $laborValue = $rate?->labor_unit_cost ?? 0;
              $costRow = $laborCosts[$rateKey] ?? null;
              $laborCostValue = $costRow?->cost_amount ?? 0;
              $formItemId = $item->item_id ?? $item->id;
              $formId = 'labor-form-'.$formItemId.($item->variant_id ? '-v'.$item->variant_id : '');
            @endphp
            <tr>
              <td class="text-wrap">
                {{ $item->name }}
                @if($canUpdate)
                  <form id="{{ $formId }}" method="post" action="{{ route('projects.labor.update', $formItemId) }}" class="d-none">
                    @csrf
                    <input type="hidden" name="type" value="{{ $type }}">
                    <input type="hidden" name="q" value="{{ $q }}">
                    @if(!empty($selectedSubContractorId))
                      <input type="hidden" name="sub_contractor_id" value="{{ $selectedSubContractorId }}">
                    @endif
                    @if(!empty($item->variant_id))
                      <input type="hidden" name="variant_id" value="{{ $item->variant_id }}">
                    @endif
                  </form>
                @endif
              </td>
              <td class="text-muted">{{ $item->sku ?? '-' }}</td>
              <td class="text-end">
                @if($canUpdate)
                  <input type="text" name="labor_unit_cost" form="{{ $formId }}" class="form-control form-control-sm text-end" value="{{ number_format((float)$laborValue, 2, ',', '.') }}" required>
                @else
                  {{ number_format((float)$laborValue, 2, ',', '.') }}
                @endif
              </td>
              @if(!empty($canManageCost))
                <td class="text-end">
                  @if($canUpdate)
                    <input type="text" name="labor_cost_amount" form="{{ $formId }}" class="form-control form-control-sm text-end" value="{{ number_format((float)$laborCostValue, 2, ',', '.') }}">
                  @else
                    {{ number_format((float)$laborCostValue, 2, ',', '.') }}
                  @endif
                </td>
              @endif
              <td>
                @if($canUpdate)
                  <input type="text" name="notes" form="{{ $formId }}" class="form-control form-control-sm" value="{{ $rate?->notes }}">
                @else
                  {{ $rate?->notes ?? '-' }}
                @endif
              </td>
              <td class="text-muted">
                {{ $rate?->updatedBy?->name ?? '-' }}
              </td>
              <td class="text-muted">
                {{ $rate?->updated_at?->format('d M Y') ?? '-' }}
              </td>
              <td class="text-end">
                @if($canUpdate)
                  <button class="btn btn-sm btn-primary" form="{{ $formId }}">Simpan</button>
                @else
                  -
                @endif
              </td>
            </tr>
          @empty
            <tr>
              <td colspan="{{ !empty($canManageCost) ? 8 : 7 }}" class="text-center text-muted">Tidak ada item.</td>
            </tr>
          @endforelse
        </tbody>
      </table>
    </div>

    <div class="card-footer">
      {{ $items->links() }}
    </div>
  </div>
</div>

@if(!empty($canManageCost) && $subContractors->isNotEmpty())
  @push('scripts')
  <script>
  (function () {
    var filter = document.getElementById('labor-sub-contractor');
    var hidden = document.getElementById('labor-sub-contractor-default');
    if (!filter) return;
    filter.addEventListener('change', function () {
      if (hidden) {
        hidden.value = filter.value;
      }
      filter.form && filter.form.submit();
    });
  })();
  </script>
  @endpush
@endif
@endsection
