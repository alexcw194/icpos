@extends('layouts.tabler')

@section('content')
<div class="container-xl">
  @if(session('success'))
    <div class="alert alert-success">{{ session('success') }}</div>
  @endif

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
        <a href="{{ route('projects.labor.index', ['type' => 'item', 'q' => $q]) }}" class="btn btn-outline-primary {{ $type === 'item' ? 'active' : '' }}">Item Labor</a>
        <a href="{{ route('projects.labor.index', ['type' => 'project', 'q' => $q]) }}" class="btn btn-outline-primary {{ $type === 'project' ? 'active' : '' }}">Project Item Labor</a>
      </div>

      <form method="get" class="ms-auto d-flex align-items-center gap-2">
        <input type="hidden" name="type" value="{{ $type }}">
        <input type="search" name="q" class="form-control" placeholder="Cari item / SKU" value="{{ $q }}">
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
              $rate = $rates[$item->id] ?? null;
              $laborValue = $rate?->labor_unit_cost ?? 0;
              $formId = 'labor-form-'.$item->id;
            @endphp
            <tr>
              <td class="text-wrap">
                {{ $item->name }}
                @if($canUpdate)
                  <form id="{{ $formId }}" method="post" action="{{ route('projects.labor.update', $item) }}" class="d-none">
                    @csrf
                    <input type="hidden" name="type" value="{{ $type }}">
                    <input type="hidden" name="q" value="{{ $q }}">
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
              <td colspan="7" class="text-center text-muted">Tidak ada item.</td>
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
@endsection
