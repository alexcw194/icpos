{{-- resources/views/quotations/index.blade.php --}}
@extends('layouts.tabler')

@section('content')
@php
  // Jika server-side sudah diberi pilihan aktif, tampilkan split pada initial render
  $shown = $active ?? $preview ?? null;
@endphp
<div class="container-xl">
  <div class="d-flex align-items-center mb-3">
    <h2 class="page-title m-0">Quotations</h2>
    <a href="{{ route('quotations.create') }}" class="btn btn-primary ms-auto">+ New Quotation</a>
  </div>

  <div class="row g-3">
    {{-- LEFT: list --}}
    <div id="list-col" class="col-12 {{ $shown ? 'col-lg-5' : 'col-lg-12' }}">
      <div class="card">
        <div class="card-body border-bottom">
          <form id="quotation-filter-form" method="get" class="row g-2">
            <div class="col-12 col-md-5">
              {{-- Rulebook: type=search + icon + Enter-to-search --}}
              <div class="input-group">
                <input
                  id="qtnSearch"
                  type="search"
                  class="form-control"
                  name="q"
                  value="{{ $q }}"
                  placeholder="Search number / customer"
                  enterkeyhint="search"
                  inputmode="search"
                  autocomplete="off"
                >
                <button
                  type="button"
                  class="btn btn-icon"
                  id="qtnSearchBtn"
                  aria-label="Search"
                  title="Search"
                >
                  {{-- Tabler icon: search --}}
                  <svg xmlns="http://www.w3.org/2000/svg" class="icon" width="20" height="20" viewBox="0 0 24 24"
                       stroke-width="2" stroke="currentColor" fill="none" stroke-linecap="round" stroke-linejoin="round"
                       aria-hidden="true">
                    <path stroke="none" d="M0 0h24v24H0z" fill="none"></path>
                    <circle cx="10" cy="10" r="7"></circle>
                    <line x1="21" y1="21" x2="15" y2="15"></line>
                  </svg>
                </button>
              </div>
            </div>

            <div class="col-6 col-md-3">
              <select name="company_id" class="form-select" data-auto-submit="1">
                <option value="">All companies</option>
                @foreach($companies as $c)
                  <option value="{{ $c->id }}" @selected((string)$cid === (string)$c->id)>{{ $c->alias ?? $c->name }}</option>
                @endforeach
              </select>
            </div>

            {{-- Status dipendekkan: mobile col-4, desktop tetap rapi --}}
            <div class="col-4 col-md-2">
              <select name="status" class="form-select" data-auto-submit="1">
                <option value="">All</option>
                <option value="draft" @selected($status === 'draft')>Draft</option>
                <option value="sent"  @selected($status === 'sent')>Sent</option>
                <option value="won"   @selected($status === 'won')>Won</option>
              </select>
            </div>

            {{-- Reset sejajar status (mobile kanan) --}}
            <div class="col-2 col-md-2 d-flex align-items-center justify-content-end">
              <a class="btn btn-link px-0" href="{{ route('quotations.index') }}">Reset</a>
            </div>

            {{-- Desktop-only Filter button tetap ada tapi sekarang pindah ke baris atas (optional) --}}
            <div class="col-12 col-md-2 d-none d-md-flex gap-2 justify-content-end">
              <button class="btn btn-outline w-100">Filter</button>
            </div>
          </form>
        </div>

        <div class="table-responsive">
          <table class="table card-table table-vcenter">
            <thead>
              <tr>
                <th>Quotation #</th>
                <th>Date</th>
                <th>Customer</th>
                <th class="d-none d-md-table-cell">Sales Owner</th>
                <th class="text-end">Total</th>
                <th>Status</th>
              </tr>
            </thead>

            <tbody id="qtn-list">
              @forelse($quotations as $row)
                <tr class="{{ (int)request('preview') === (int)$row->id ? 'table-primary' : '' }}" data-id="{{ $row->id }}">
                  <td class="fw-bold">
                    {{-- MOBILE: Rulebook stacked rows --}}
                    <div class="qtn-mobile d-md-none">
                      {{-- Row 1: ID + Status (+ kebab inline) --}}
                      <div class="qtn-m-row1">
                        <a href="{{ route('quotations.index', array_merge(request()->except('page'), ['preview' => $row->id])) }}"
                           class="qtn-number text-decoration-none"
                           data-id="{{ $row->id }}">
                          {{ $row->number }}
                        </a>

                        <div class="qtn-m-row1-right">
                          <span class="badge {{ $row->status_badge_class }}">{{ $row->status_label }}</span>

                          {{-- Actions (mobile: kebab inline, di samping status) --}}
                          <div class="qtn-actions">
                            @include('layouts.partials.crud_actions', [
                              'view' => route('quotations.pdf', $row),
                              'viewTarget' => '_blank',
                              'viewRel' => 'noopener',
                              'edit' => route('quotations.edit', $row),
                              'delete' => null,
                              'size' => 'sm',
                            ])
                          </div>
                        </div>
                      </div>

                      {{-- Row 2: Entity --}}
                      <div class="qtn-m-row2">
                        {{ $row->customer->name ?? '-' }}
                        <div class="text-muted small">Sales by {{ $row->salesUser?->name ?? '-' }}</div>
                      </div>

                      {{-- Row 3: Date + Total (dense, no wasted space) --}}
                      <div class="qtn-m-row3">
                        <span class="qtn-m-date">{{ optional($row->date)->format('d M Y') }}</span>
                        <span class="qtn-m-total">{{ $row->total_idr ?? '-' }}</span>
                      </div>
                    </div>

                    {{-- DESKTOP: keep existing behavior --}}
                    <div class="qtn-desktop d-none d-md-block">
                      <a href="{{ route('quotations.index', array_merge(request()->except('page'), ['preview' => $row->id])) }}"
                         class="qtn-number text-decoration-none"
                         data-id="{{ $row->id }}">
                        {{ $row->number }}
                      </a>

                      <div class="qtn-actions mt-2">
                        @include('layouts.partials.crud_actions', [
                          'view' => route('quotations.pdf', $row),
                          'viewTarget' => '_blank',
                          'viewRel' => 'noopener',
                          'edit' => route('quotations.edit', $row),
                          'delete' => null,
                          'size' => 'sm',
                        ])
                      </div>
                    </div>
                  </td>

                  {{-- Desktop-only columns --}}
                  <td class="d-none d-md-table-cell">{{ optional($row->date)->format('d-m-Y') }}</td>
                  <td class="d-none d-md-table-cell text-wrap">{{ $row->customer->name ?? '-' }}</td>
                  <td class="d-none d-md-table-cell">{{ $row->salesUser?->name ?? '-' }}</td>
                  <td class="d-none d-md-table-cell text-end">{{ $row->total_idr ?? '-' }}</td>
                  <td class="d-none d-md-table-cell"><span class="badge {{ $row->status_badge_class }}">{{ $row->status_label }}</span></td>
                </tr>
              @empty
                <tr><td colspan="6" class="text-center text-muted">No data.</td></tr>
              @endforelse
            </tbody>
          </table>
        </div>

        <div class="card-footer">
          {{ $quotations->links() }}
        </div>
      </div>
    </div>

    {{-- RIGHT: preview (hidden until selected) --}}
    <div id="preview-col" class="col-12 col-lg-7 {{ $shown ? '' : 'd-none' }}">
      <div id="qtn-preview">
        @if($shown)
          @include('quotations._preview', ['quotation' => $shown])
        @else
          <div class="card"><div class="card-body text-muted">
            Pilih quotation di sebelah kiri untuk melihat preview.
          </div></div>
        @endif
      </div>
    </div>
  </div>
</div>

@push('styles')
<style>
  /* Desktop: actions appear on hover */
  #qtn-list .qtn-actions{ display:none; margin-top:.25rem; }
  #qtn-list tr:hover .qtn-desktop .qtn-actions{ display:block; }
  #qtn-list .qtn-number { cursor:pointer; }   /* hanya nomor yang klik-able */

  /* MOBILE: enterprise stacked list (dense, no wasted columns) */
  @media (max-width: 767.98px){
    /* Hide desktop header in mobile */
    .table thead { display:none; }

    /* Force row to behave like a list item and avoid column "ghost spacing" */
    #qtn-list tr { display:block; }
    #qtn-list td { display:block; width:100%; }
    #qtn-list td:first-child { width:100% !important; }

    /* Actions must be visible on mobile (no hover) */
    #qtn-list .qtn-mobile .qtn-actions{ display:block; }

    .qtn-mobile{
      display:flex;
      flex-direction:column;
      gap:.35rem;
      padding:.25rem 0;
    }

    .qtn-m-row1{
      display:flex;
      align-items:center;
      justify-content:space-between;
      gap:.5rem;
    }

    .qtn-m-row1-right{
      display:flex;
      align-items:center;
      gap:.5rem;
      margin-left:auto;
    }

    /* Kebab rapat, tidak nambah tinggi row */
    .qtn-m-row1-right .qtn-actions{ margin-top:0; }
    .qtn-m-row1-right .qtn-actions .btn-icon{ padding:.2rem .3rem; height:32px; width:32px; }
    .qtn-m-row1-right .qtn-actions .icon{ width:18px; height:18px; }

    .qtn-m-row2{
      font-weight:600;
      line-height:1.2;
    }

    .qtn-m-row3{
      display:flex;
      align-items:center;
      justify-content:space-between;
      gap:1rem;
    }

    .qtn-m-date{
      color: var(--tblr-muted);
      white-space: nowrap;
    }

    .qtn-m-total{
      font-weight:700;
      white-space: nowrap;
      text-align:right;
      margin-left:auto;
    }
  }
</style>
@endpush

@push('scripts')
<script>
(function(){
  const list       = document.getElementById('qtn-list');
  const box        = document.getElementById('qtn-preview');
  const listCol    = document.getElementById('list-col');
  const previewCol = document.getElementById('preview-col');

  const form = document.getElementById('quotation-filter-form');
  const isMobile = window.matchMedia('(max-width: 767.98px)').matches;
  const submit = () => (form?.requestSubmit ? form.requestSubmit() : form?.submit());

  function ensureSplit(){
    // Tampilkan kolom preview dan sempitkan kolom list
    if (previewCol.classList.contains('d-none')) {
      previewCol.classList.remove('d-none');
    }
    // Ubah col-lg-12 -> col-lg-5
    if (listCol.classList.contains('col-lg-12')) {
      listCol.classList.remove('col-lg-12');
    }
    if (!listCol.classList.contains('col-lg-5')) {
      listCol.classList.add('col-lg-5');
    }
    if (!listCol.classList.contains('col-12')) {
      listCol.classList.add('col-12');
    }
  }

  // Rulebook: Search only on Enter or click icon (no realtime)
  const q = document.getElementById('qtnSearch');
  if (q) {
    q.addEventListener('keydown', (e) => {
      if (e.key === 'Enter') {
        e.preventDefault();
        submit();
      }
    });
  }
  const btn = document.getElementById('qtnSearchBtn');
  if (btn) btn.addEventListener('click', () => submit());

  // Mobile quick filters: auto-apply on change
  if (form && isMobile) {
    form.querySelectorAll('select[data-auto-submit="1"]').forEach((el) => {
      el.addEventListener('change', () => submit());
    });
  }

  list?.addEventListener('click', async (e) => {
    // Klik area actions: biarkan default (Lihat/Ubah)
    if (e.target.closest('.qtn-actions')) return;

    // Hanya klik nomor yang memicu preview
    const a = e.target.closest('a.qtn-number');
    if (!a) return;
    e.preventDefault();

    const id = a.dataset.id;
    const tr = a.closest('tr');

    // Aktifkan split sebelum load
    ensureSplit();

    // highlight row
    document.querySelectorAll('#qtn-list tr').forEach(el => el.classList.remove('table-primary'));
    tr?.classList.add('table-primary');

    // load partial preview
    box.innerHTML = '<div class="card"><div class="card-body">Loading…</div></div>';
    try {
      const resp = await fetch(`{{ url('quotations') }}/${id}/preview`, {
        headers: {'X-Requested-With':'XMLHttpRequest'}
      });
      const html = await resp.text();
      box.innerHTML = html;
    } catch (err) {
      box.innerHTML = '<div class="card"><div class="card-body text-danger">Gagal memuat preview.</div></div>';
    }

    // update URL tanpa reload
    const params = new URLSearchParams(window.location.search);
    params.set('preview', id);
    const newUrl = `${window.location.pathname}?${params.toString()}`;
    history.replaceState({}, '', newUrl);
  });
})();
</script>
@endpush
@endsection
