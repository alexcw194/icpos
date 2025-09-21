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
          <form method="get" class="row g-2">
            <div class="col-12 col-md-5">
              <input class="form-control" name="q" value="{{ $q }}" placeholder="Search number / customer">
            </div>
            <div class="col-6 col-md-3">
              <select name="company_id" class="form-select">
                <option value="">All companies</option>
                @foreach($companies as $c)
                  <option value="{{ $c->id }}" @selected((string)$cid === (string)$c->id)>{{ $c->alias ?? $c->name }}</option>
                @endforeach
              </select>
            </div>
            <div class="col-6 col-md-2">
              <select name="status" class="form-select">
                <option value="">All</option>
                <option value="draft" @selected($status === 'draft')>Draft</option>
                <option value="sent"  @selected($status === 'sent')>Sent</option>
                <option value="won"   @selected($status === 'won')>Won</option> {{-- ← ganti ini --}}
              </select>
            </div>
            <div class="col-12 col-md-2 d-flex gap-2">
              <button class="btn btn-outline w-100">Filter</button>
              <a class="btn btn-link" href="{{ route('quotations.index') }}">Reset</a>
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
                <th class="text-end">Total</th>
                <th>Status</th>
              </tr>
            </thead>
            <tbody id="qtn-list">
              @forelse($quotations as $row)
                <tr class="{{ (int)request('preview') === (int)$row->id ? 'table-primary' : '' }}" data-id="{{ $row->id }}">
                  <td class="fw-bold">
                    <a href="{{ route('quotations.index', array_merge(request()->except('page'), ['preview' => $row->id])) }}"
                       class="qtn-number text-decoration-none"
                       data-id="{{ $row->id }}">
                      {{ $row->number }}
                    </a>

                    <div class="qtn-actions">
                      <a class="qtn-act" href="{{ route('quotations.pdf', $row) }}" target="_blank" rel="noopener">View</a>
                      <span class="text-muted"> | </span>
                      <a class="qtn-act" href="{{ route('quotations.edit', $row) }}">Edit</a>
                    </div>
                  </td>
                  <td>{{ optional($row->date)->format('d-m-Y') }}</td>
                  <td class="text-wrap">{{ $row->customer->name ?? '-' }}</td>
                  <td class="text-end">{{ $row->total_idr ?? '-' }}</td>
                  <td><span class="badge {{ $row->status_badge_class }}">{{ $row->status_label }}</span></td>
                </tr>
              @empty
                <tr><td colspan="5" class="text-center text-muted">No data.</td></tr>
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
  #qtn-list .qtn-actions{ display:none; margin-top:.25rem; font-size:.85rem; }
  #qtn-list tr:hover .qtn-actions{ display:block; }
  #qtn-list .qtn-actions a{ text-decoration:none; }
  #qtn-list .qtn-number { cursor:pointer; }   /* hanya nomor yang klik-able */
</style>
@endpush

@push('scripts')
<script>
(function(){
  const list      = document.getElementById('qtn-list');
  const box       = document.getElementById('qtn-preview');
  const listCol   = document.getElementById('list-col');
  const previewCol= document.getElementById('preview-col');

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

  list?.addEventListener('click', async (e) => {
    // Klik "View | Edit": biarkan default
    if (e.target.closest('.qtn-act')) return;

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
