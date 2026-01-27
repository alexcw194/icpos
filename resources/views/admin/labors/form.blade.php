@extends('layouts.tabler')

@section('content')
<div class="page-header d-print-none">
  <div class="container-xl">
    <h2 class="page-title">{{ $row->exists ? 'Edit Labor' : 'Tambah Labor' }}</h2>
  </div>
</div>

<div class="page-body">
  <div class="container-xl">
    <form class="card mb-3" method="post" action="{{ $row->exists ? route('labors.update', $row) : route('labors.store') }}">
      @csrf
      @if($row->exists) @method('PUT') @endif

      <div class="card-body">
        @if ($errors->any())
          <div class="alert alert-danger">
            <div class="fw-bold mb-1">Periksa kembali input Anda:</div>
            <ul class="mb-0">
              @foreach ($errors->all() as $error) <li>{{ $error }}</li> @endforeach
            </ul>
          </div>
        @endif

        <div class="row g-3">
          <div class="col-md-4">
            <label class="form-label">Code <span class="text-danger">*</span></label>
            <input type="text" name="code" maxlength="32"
                   class="form-control @error('code') is-invalid @enderror"
                   value="{{ old('code', $row->code) }}" required>
            @error('code') <div class="invalid-feedback">{{ $message }}</div> @enderror
          </div>
          <div class="col-md-5">
            <label class="form-label">Name <span class="text-danger">*</span></label>
            <input type="text" name="name" maxlength="190"
                   class="form-control @error('name') is-invalid @enderror"
                   value="{{ old('name', $row->name) }}" required>
            @error('name') <div class="invalid-feedback">{{ $message }}</div> @enderror
          </div>
          <div class="col-md-3">
            <label class="form-label">Unit</label>
            <input type="text" name="unit" maxlength="20"
                   class="form-control @error('unit') is-invalid @enderror"
                   value="{{ old('unit', $row->unit) }}" placeholder="LS">
            @error('unit') <div class="invalid-feedback">{{ $message }}</div> @enderror
          </div>
          <div class="col-md-4">
            <label class="form-label d-block">Status</label>
            <input type="hidden" name="is_active" value="0">
            <label class="form-check form-switch">
              <input class="form-check-input" type="checkbox" name="is_active" value="1"
                     @checked(old('is_active', $row->exists ? (int)$row->is_active : 1) == 1)>
              <span class="form-check-label">Active</span>
            </label>
          </div>
        </div>
      </div>

      @include('layouts.partials.form_footer', [
        'cancelUrl'    => route('labors.index'),
        'cancelLabel'  => 'Batal',
        'cancelInline' => true,
        'buttons'      => [
          ['type' => 'submit', 'label' => 'Simpan', 'class' => 'btn btn-primary'],
        ],
      ])
    </form>

    @if($row->exists)
      <form class="card" method="post" action="{{ route('labors.cost.store', $row) }}">
        @csrf
        <div class="card-header">
          <h3 class="card-title">Cost Profile</h3>
        </div>
        <div class="card-body">
          @if(session('success'))
            <div class="alert alert-success">{{ session('success') }}</div>
          @endif
          @if(session('error'))
            <div class="alert alert-danger">{{ session('error') }}</div>
          @endif

          @if($subContractors->isEmpty())
            <div class="alert alert-warning">Belum ada Sub-Contractor aktif. Tambahkan dahulu.</div>
          @else
            <div class="row g-3">
              <div class="col-md-6">
                <label class="form-label">Sub-Contractor</label>
                <select name="sub_contractor_id" id="labor-sub-contractor" class="form-select">
                  @foreach($subContractors as $sc)
                    <option value="{{ $sc->id }}" @selected((int)$selectedSubContractorId === (int)$sc->id)>{{ $sc->name }}</option>
                  @endforeach
                </select>
                <div class="text-muted small mt-1" id="labor-default-note">
                  Default: {{ $defaultSubContractorName ?? '-' }}
                </div>
              </div>
              <div class="col-md-4">
                <label class="form-label">Cost Amount (IDR)</label>
                <input type="text" name="cost_amount" id="labor-cost-amount" class="form-control text-end" value="">
                <div class="text-muted small mt-1 d-none" id="labor-cost-missing">Belum ada cost untuk sub-contractor ini.</div>
              </div>
              <div class="col-md-2">
                <label class="form-label d-block">Active</label>
                <input type="hidden" name="is_active" value="0">
                <label class="form-check form-switch">
                  <input class="form-check-input" type="checkbox" name="is_active" id="labor-cost-active" value="1" checked>
                  <span class="form-check-label">Active</span>
                </label>
              </div>
              <div class="col-md-6">
                <label class="form-label d-block">Set as Default</label>
                <input type="hidden" name="set_default" value="0">
                <label class="form-check">
                  <input class="form-check-input" type="checkbox" name="set_default" value="1">
                  <span class="form-check-label">Gunakan sub-contractor ini sebagai default labor</span>
                </label>
              </div>
            </div>
          @endif
        </div>
        @if($subContractors->isNotEmpty())
          @include('layouts.partials.form_footer', [
            'cancelUrl'    => route('labors.index'),
            'cancelLabel'  => 'Kembali',
            'cancelInline' => true,
            'buttons'      => [
              ['type' => 'submit', 'label' => 'Simpan Cost', 'class' => 'btn btn-primary'],
            ],
          ])
        @endif
      </form>
    @endif
  </div>
</div>
@endsection

@push('scripts')
@if($row->exists && $subContractors->isNotEmpty())
<script>
(() => {
  const select = document.getElementById('labor-sub-contractor');
  const amountInput = document.getElementById('labor-cost-amount');
  const activeToggle = document.getElementById('labor-cost-active');
  const missingNote = document.getElementById('labor-cost-missing');
  const fetchUrl = @json(route('labors.cost.show', $row, false));

  const formatNumber = (val) => {
    return Number(val || 0).toLocaleString('id-ID', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
  };

  const loadCost = (subId) => {
    if (!subId) return;
    fetch(`${fetchUrl}?sub_contractor_id=${encodeURIComponent(subId)}`, {
      credentials: 'same-origin',
      headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
      cache: 'no-store',
    })
      .then(r => r.ok ? r.json() : null)
      .then(data => {
        if (!data) return;
        if (data.exists) {
          amountInput.value = formatNumber(data.cost_amount || 0);
          missingNote?.classList.add('d-none');
        } else {
          amountInput.value = '';
          missingNote?.classList.remove('d-none');
        }
        if (activeToggle) activeToggle.checked = !!data.is_active;
      })
      .catch(() => {});
  };

  if (select) {
    loadCost(select.value);
    select.addEventListener('change', (e) => {
      loadCost(e.target.value);
    });
  }
})();
</script>
@endif
@endpush
