@extends('layouts.tabler')

@section('title', 'Resep Produksi')

@section('content')
  <div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
      <div>
        <h3 class="card-title mb-0">Daftar Resep Produksi</h3>
        <div class="text-muted small">Tampilan per Kit (bundle-level)</div>
      </div>
      <a href="{{ route('manufacture-recipes.create') }}" class="btn btn-primary">+ Tambah Resep</a>
    </div>

    <div class="card-body border-bottom">
      <form method="GET" class="d-flex gap-2">
        <input
          type="text"
          name="q"
          value="{{ $q ?? '' }}"
          class="form-control"
          placeholder="Cari kit / SKU…"
        />
        <button class="btn btn-outline-secondary" type="submit">Search</button>
        @if(!empty($q))
          <a class="btn btn-outline-secondary" href="{{ route('manufacture-recipes.index') }}">Reset</a>
        @endif
      </form>
    </div>

    <div class="card-body">
      @if($kits->count() === 0)
        <div class="text-muted">Belum ada resep.</div>
      @endif

      <div class="accordion" id="recipeKitsAccordion">
        @foreach($kits as $kit)
          @php
            $collapseId = 'kitCollapse'.$kit->id;
            $headingId  = 'kitHeading'.$kit->id;
            $lastUpdated = $kit->manufacture_recipes_max_updated_at ?? null;
          @endphp

          <div class="accordion-item mb-2">
            <h2 class="accordion-header" id="{{ $headingId }}">
              <div class="d-flex align-items-center gap-2">
                <button
                  class="accordion-button collapsed flex-grow-1"
                  type="button"
                  data-bs-toggle="collapse"
                  data-bs-target="#{{ $collapseId }}"
                  aria-expanded="false"
                  aria-controls="{{ $collapseId }}"
                >
                  <div class="w-100 d-flex justify-content-between align-items-center gap-3">
                    <div class="d-flex flex-column">
                      <div class="fw-bold">{{ $kit->name }}</div>
                      <div class="text-muted small">
                        SKU: {{ $kit->sku ?? '—' }}
                        · Unit: {{ $kit->unit->name ?? '—' }}
                        · Brand: {{ $kit->brand->name ?? '—' }}
                      </div>
                    </div>

                    <div class="d-flex align-items-center gap-2">
                      <span class="badge bg-azure-lt">{{ $kit->manufacture_recipes_count }} komponen</span>
                      <span class="text-muted small">
                        Last updated: {{ $lastUpdated ? \Illuminate\Support\Carbon::parse($lastUpdated)->diffForHumans() : '—' }}
                      </span>
                    </div>
                  </div>
                </button>

                <a
                  href="{{ route('manufacture-recipes.manage', $kit) }}"
                  class="btn btn-outline-primary btn-sm flex-shrink-0"
                >
                  Kelola Resep
                </a>
              </div>
            </h2>

            <div id="{{ $collapseId }}" class="accordion-collapse collapse" aria-labelledby="{{ $headingId }}" data-bs-parent="#recipeKitsAccordion">
              <div class="accordion-body pt-2">
                <div class="table-responsive">
                  <table class="table table-sm table-vcenter">
                    <thead>
                      <tr>
                        <th>Komponen</th>
                        <th class="text-end">Qty</th>
                        <th>Catatan</th>
                      </tr>
                    </thead>
                    <tbody>
                      @foreach($kit->manufactureRecipes as $r)
                        <tr>
                          <td>
                            {{ $r->componentVariant?->label ?? '—' }}
                            <div class="text-muted small">SKU: {{ $r->componentVariant?->sku ?? '—' }}</div>
                          </td>
                          <td class="text-end">{{ number_format((float) $r->qty_required, 1) }}</td>
                          <td>{{ $r->notes ?: '—' }}</td>
                        </tr>
                      @endforeach
                    </tbody>
                  </table>
                </div>
                <div class="text-muted small">
                  Perubahan dilakukan dari halaman <b>Kelola Resep</b> untuk menjaga kontrol dan safety.
                </div>
              </div>
            </div>
          </div>
        @endforeach
      </div>
    </div>

    <div class="card-footer">
      {{ $kits->links() }}
    </div>
  </div>
@endsection
