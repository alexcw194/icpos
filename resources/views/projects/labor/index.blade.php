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
    @php $canUpdate = $type === 'project' ? $canUpdateProject : $canUpdateItem; @endphp
    <div class="card-header d-flex align-items-center gap-2 flex-wrap">
      <div class="btn-group" role="group">
        <a href="{{ route('projects.labor.index', ['type' => 'item', 'q' => $q, 'sub_contractor_id' => $selectedSubContractorId, 'per_page' => $perPage]) }}" class="btn btn-outline-primary {{ $type === 'item' ? 'active' : '' }}">Item Labor</a>
        <a href="{{ route('projects.labor.index', ['type' => 'project', 'q' => $q, 'sub_contractor_id' => $selectedSubContractorId, 'per_page' => $perPage]) }}" class="btn btn-outline-primary {{ $type === 'project' ? 'active' : '' }}">Project Item Labor</a>
      </div>

      @if(!empty($canManageCost) && $subContractors->isNotEmpty())
        <form method="get" class="d-flex align-items-center gap-2">
          <input type="hidden" name="type" value="{{ $type }}">
          <input type="hidden" name="q" value="{{ $q }}">
          <input type="hidden" name="per_page" value="{{ $perPage }}">
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

      @if($canUpdate)
        <form id="labor-bulk-form" method="post" action="{{ route('projects.labor.bulk-adjust') }}" class="d-flex align-items-center gap-2 flex-wrap">
          @csrf
          <input type="hidden" name="type" value="{{ $type }}">
          <input type="hidden" name="q" value="{{ $q }}">
          <input type="hidden" name="page" value="{{ request('page') }}">
          <input type="hidden" name="per_page" value="{{ $perPage }}">
          @if(!empty($selectedSubContractorId))
            <input type="hidden" name="sub_contractor_id" value="{{ $selectedSubContractorId }}">
          @endif
          <span class="text-muted small">Perubahan Mass Edit</span>
          <select name="operation" class="form-select form-select-sm" style="width:120px;">
            <option value="increase" @selected(old('operation', 'increase') === 'increase')>Tambah</option>
            <option value="decrease" @selected(old('operation') === 'decrease')>Kurangi</option>
          </select>
          <div class="input-group input-group-sm" style="width:140px;">
            <input type="text" id="labor-bulk-percent" name="percent" class="form-control text-end" placeholder="0" value="{{ old('percent') }}">
            <span class="input-group-text">%</span>
          </div>
          <button id="labor-bulk-apply" class="btn btn-sm btn-primary" type="submit">Terapkan</button>
        </form>
      @endif

      <form method="get" class="ms-auto d-flex align-items-center gap-2">
        <input type="hidden" name="type" value="{{ $type }}">
        <input type="hidden" name="per_page" value="{{ $perPage }}">
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
            <th style="width:1%;">
              @if($canUpdate)
                <input type="checkbox" id="labor-select-all" class="form-check-input">
              @endif
            </th>
            <th>Item</th>
            <th style="width:147px;">SKU</th>
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
              <td>
                @if($canUpdate)
                  <input type="checkbox" class="form-check-input js-labor-row-check" name="selected[]" form="labor-bulk-form" value="{{ $formItemId }}:{{ (int) ($item->variant_id ?? 0) }}">
                @else
                  -
                @endif
              </td>
              <td class="text-wrap">
                {{ $item->name }}
                @if($canUpdate)
                  <form id="{{ $formId }}" method="post" action="{{ route('projects.labor.update', $formItemId) }}" class="d-none">
                    @csrf
                    <input type="hidden" name="type" value="{{ $type }}">
                    <input type="hidden" name="q" value="{{ $q }}">
                    <input type="hidden" name="page" value="{{ request('page') }}">
                    <input type="hidden" name="per_page" value="{{ $perPage }}">
                    @if(!empty($selectedSubContractorId))
                      <input type="hidden" name="sub_contractor_id" value="{{ $selectedSubContractorId }}">
                    @endif
                  </form>
                  @if(!empty($item->variant_id))
                    <input type="hidden" name="variant_id" form="{{ $formId }}" value="{{ $item->variant_id }}">
                  @endif
                @endif
              </td>
              <td class="text-muted text-nowrap" style="font-size: 13px;">{{ $item->sku ?? '-' }}</td>
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
              <td colspan="{{ !empty($canManageCost) ? 9 : 8 }}" class="text-center text-muted">Tidak ada item.</td>
            </tr>
          @endforelse
        </tbody>
      </table>
    </div>

    <div class="card-footer">
      {{ $items->links('vendor.pagination.items') }}
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

@if($canUpdate)
  @push('scripts')
  <script>
  (function () {
    var form = document.getElementById('labor-bulk-form');
    if (!form) return;

    var selectAll = document.getElementById('labor-select-all');
    var checks = Array.prototype.slice.call(document.querySelectorAll('.js-labor-row-check'));
    var applyBtn = document.getElementById('labor-bulk-apply');
    var percentInput = document.getElementById('labor-bulk-percent');

    var syncState = function () {
      var checkedCount = checks.filter(function (el) { return el.checked; }).length;
      if (applyBtn) {
        applyBtn.disabled = checkedCount === 0;
      }
      if (!selectAll) return;
      if (checkedCount === 0) {
        selectAll.checked = false;
        selectAll.indeterminate = false;
        return;
      }
      if (checkedCount === checks.length) {
        selectAll.checked = true;
        selectAll.indeterminate = false;
        return;
      }
      selectAll.checked = false;
      selectAll.indeterminate = true;
    };

    if (selectAll) {
      selectAll.addEventListener('change', function () {
        checks.forEach(function (el) {
          el.checked = selectAll.checked;
        });
        syncState();
      });
    }

    checks.forEach(function (el) {
      el.addEventListener('change', syncState);
    });

    form.addEventListener('submit', function (event) {
      var selectedCount = checks.filter(function (el) { return el.checked; }).length;
      if (selectedCount === 0) {
        event.preventDefault();
        window.alert('Pilih minimal 1 item.');
        return;
      }
      if (!percentInput || String(percentInput.value || '').trim() === '') {
        event.preventDefault();
        window.alert('Persen wajib diisi.');
      }
    });

    syncState();
  })();
  </script>
  @endpush
@endif
@endsection
