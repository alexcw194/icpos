{{-- resources/views/sales_orders/edit.blade.php --}}
@extends('layouts.tabler')

@section('title', 'Edit Sales Order')

@section('content')
<div class="page-body">
  <div class="container-xl">

    <div class="page-header d-print-none">
      <div class="row align-items-center">
        <div class="col">
          <h2 class="page-title">Edit Sales Order</h2>
          <div class="text-secondary">
            {{ $salesOrder->number }} · {{ $salesOrder->customer->name ?? '-' }}
          </div>
        </div>
        <div class="col-auto ms-auto d-print-none">
          <a href="{{ route('sales-orders.show', $salesOrder) }}" class="btn btn-link">Kembali</a>
        </div>
      </div>
    </div>

    <form method="POST" action="{{ route('sales-orders.update', $salesOrder) }}" id="soEditForm" autocomplete="off">
      @csrf
      @method('PUT')

      <div class="row g-3">
        {{-- kiri --}}
        <div class="col-md-8">
          <div class="card">
            <div class="card-header"><h3 class="card-title">Informasi SO</h3></div>
            <div class="card-body">
              <div class="row g-3">
                <div class="col-md-4">
                  <label class="form-label">Tanggal</label>
                  <input type="date" name="date" class="form-control" required
                         value="{{ old('date', optional($salesOrder->date)->format('Y-m-d')) }}">
                </div>
                <div class="col-md-4">
                  <label class="form-label">Deadline</label>
                  <input type="date" name="deadline" class="form-control"
                         value="{{ old('deadline', optional($salesOrder->deadline)->format('Y-m-d')) }}">
                </div>
                <div class="col-md-4">
                  <label class="form-label">Pajak (%)</label>
                  <input type="text" name="tax_percent" class="form-control text-end"
                         value="{{ rtrim(rtrim(number_format(old('tax_percent', $salesOrder->tax_percent ?? 11), 2, ',', '.'), '0'), ',') }}"
                         inputmode="decimal" placeholder="11">
                </div>
                <div class="col-12">
                  <label class="form-label">Catatan (terlihat customer)</label>
                  <textarea name="notes" rows="3" class="form-control">{{ old('notes', $salesOrder->notes) }}</textarea>
                </div>
              </div>
            </div>
          </div>

          <div class="card">
            <div class="card-header"><h3 class="card-title">Detail Lainnya</h3></div>
            <div class="card-body">
              <ul class="nav nav-tabs" role="tablist">
                <li class="nav-item">
                  <a class="nav-link active" data-bs-toggle="tab" href="#tab-private" role="tab">Catatan Pribadi</a>
                </li>
                <li class="nav-item">
                  <a class="nav-link" data-bs-toggle="tab" href="#tab-attachments" role="tab">Lampiran</a>
                </li>
              </ul>

              <div class="tab-content pt-3">
                {{-- Catatan Pribadi --}}
                <div class="tab-pane active" id="tab-private" role="tabpanel">
                  <div class="mb-3">
                    <label class="form-label">Catatan Pribadi (internal)</label>
                    <textarea name="private_notes" class="form-control" rows="4">{{ old('private_notes', $salesOrder->private_notes) }}</textarea>
                  </div>
                  <div class="mb-3 col-md-6">
                    <label class="form-label">Under (Komisi untuk Customer)</label>
                    <input type="text" name="under_amount" class="form-control text-end"
                           value="{{ rtrim(rtrim(number_format(old('under_amount', $salesOrder->under_amount ?? 0), 2, ',', '.'), '0'), ',') }}"
                           inputmode="decimal" placeholder="0">
                    <small class="text-muted">Dipakai pada perhitungan net & komisi sales.</small>
                  </div>
                </div>

                {{-- Lampiran --}}
                <div class="tab-pane" id="tab-attachments" role="tabpanel">
                  <div class="mb-2">
                    <label class="form-label">Upload Lampiran (PDF/JPG/PNG)</label>
                    <input type="file" id="soUpload" class="form-control" multiple
                           accept="application/pdf,image/jpeg,image/png">
                    <div class="form-text">File langsung terhubung ke SO ini setelah diupload.</div>
                  </div>
                  <div id="soFiles" class="list-group list-group-flush"></div>
                  <div class="text-muted small mt-2">Klik nama file untuk melihat. Gunakan tombol Hapus untuk menghapus lampiran.</div>
                </div>
              </div>
            </div>
          </div>
        </div>

        {{-- kanan --}}
        <div class="col-md-4">
          <div class="card">
            <div class="card-header"><h3 class="card-title">Customer</h3></div>
            <div class="card-body">
              <div class="mb-1 fw-bold">{{ $salesOrder->customer->name ?? '-' }}</div>
              <div class="text-secondary small">{{ $salesOrder->customer->address ?? '' }}</div>
              <div class="text-secondary small">{{ $salesOrder->customer->city ?? '' }} {{ $salesOrder->customer->province ?? '' }}</div>
            </div>
          </div>

          <div class="d-grid gap-2 mt-3">
            <button type="submit" class="btn btn-primary">Simpan Perubahan</button>
          </div>
        </div>
      </div>
    </form>

  </div>
</div>
@endsection

@push('scripts')
<script>
(function(){
  // ====== Helpers ======
  const soId = @json($salesOrder->id);
  const uploadInput = document.getElementById('soUpload');
  const listEl      = document.getElementById('soFiles');

  const moneyKB = (n)=> Math.round((n||0)/1024) + ' KB';

  function rowTpl(file){
    // Expects {id, url, name, size}
    const name = file.name || file.original_name || 'file';
    const url  = file.url  || file.path_url || '#';
    const size = file.size || 0;
    return `
      <div class="list-group-item d-flex align-items-center gap-2" data-id="${file.id}">
        <a class="me-auto" href="${url}" target="_blank" rel="noopener">${name}</a>
        <span class="text-secondary small">${moneyKB(size)}</span>
        <button type="button" class="btn btn-sm btn-outline-danger" data-role="del">Hapus</button>
      </div>
    `;
  }

  async function refreshList(){
    try{
      const u = new URL(@json(route('sales-orders.attachments.index')), window.location.origin);
      u.searchParams.set('sales_order_id', soId);
      const res   = await fetch(u, {headers:{'X-Requested-With':'XMLHttpRequest'}});
      const data  = await res.json();
      const files = Array.isArray(data) ? data : (data.data || []);
      listEl.innerHTML = files.map(rowTpl).join('');

      listEl.querySelectorAll('[data-role="del"]').forEach(btn=>{
        btn.addEventListener('click', async (e)=>{
          const row = e.target.closest('[data-id"]') || e.target.closest('[data-id]');
          const id  = row?.dataset.id;
          if(!id) return;
          await fetch(@json(route('sales-orders.attachments.destroy','__ID__')).replace('__ID__', id), {
            method:'DELETE', headers:{'X-CSRF-TOKEN': @json(csrf_token())}
          });
          row.remove();
        });
      });
    }catch(e){ /* silent */ }
  }

  uploadInput?.addEventListener('change', async (e)=>{
    const files = Array.from(e.target.files || []);
    for(const f of files){
      const fd = new FormData();
      fd.append('file', f);
      fd.append('sales_order_id', soId);
      const res  = await fetch(@json(route('sales-orders.attachments.upload')), {
        method:'POST', headers:{'X-CSRF-TOKEN': @json(csrf_token())}, body: fd
      });
      // Response bisa {attachment: {...}} atau {...}
      try{
        const json = await res.json();
        const a = json?.attachment || json;
        if(a?.id){
          listEl.insertAdjacentHTML('afterbegin', rowTpl(a));
        }
      }catch(_){}
    }
    uploadInput.value = '';
    if(!listEl.children.length) refreshList();
  });

  // Initial load
  refreshList();
})();
</script>
@endpush
