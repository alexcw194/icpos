@extends('layouts.tabler')

@section('content')
<div class="container-xl">
  <form method="post" action="{{ route('inventory.adjustments.store') }}" class="card">
    @csrf
    <div class="card-header">
      <h3 class="card-title">New Stock Adjustment</h3>
    </div>
    @if ($errors->any())
      <div class="alert alert-danger m-3">
        <div class="text-danger fw-bold mb-1">Periksa kembali input Anda:</div>
        <ul class="mb-0">
          @foreach ($errors->all() as $error)
            <li>{{ $error }}</li>
          @endforeach
        </ul>
      </div>
    @endif
    <div class="card-body">
      @php
        $selectedItemId = old('item_id', $selectedItemId ?? null);
        $selectedVariantId = old('variant_id', $selectedVariantId ?? null);
        $selectedWarehouseId = old('warehouse_id', request('warehouse_id'));
        $selectedDate = old('adjustment_date', now()->toDateString());
      @endphp

      <div class="mb-3">
        <label class="form-label">Item</label>
        <input id="adjustItemSearch" type="text" class="form-control" placeholder="Ketik nama/SKU lalu pilih...">
        <input type="hidden" name="item_id" id="adjust_item_id" value="{{ $selectedItemId }}">
        <input type="hidden" name="variant_id" id="adjust_variant_id" value="{{ $selectedVariantId }}">
        <div class="form-hint">Item master dibuat oleh SuperAdmin.</div>
        <input type="hidden" name="company_id" value="{{ auth()->user()->company_id }}">
      </div>

      <div class="mb-3">
        <label class="form-label">Warehouse</label>
        <select name="warehouse_id" class="form-select" id="adjustWarehouseSelect">
          <option value="">Semua Warehouse</option>
          @foreach($warehouses as $w)
            <option value="{{ $w->id }}" @selected((string)$selectedWarehouseId === (string)$w->id)>
              {{ $w->name }}
            </option>
          @endforeach
        </select>
      </div>

      <div class="mb-3">
        <label class="form-label">Adjustment Date</label>
        <input type="date" name="adjustment_date" class="form-control" value="{{ $selectedDate }}" required>
      </div>
      <div class="mb-3">
        <label class="form-label">Current Balance</label>
        <div id="adjustBalanceValue">{{ number_format($summary->qty_balance ?? 0, 2, ',', '.') }}</div>
        <div class="form-hint" id="adjustBalanceHint">
          {{ ($selectedItemId ? '' : 'Pilih item untuk melihat saldo stok.') }}
        </div>
      </div>
      <div class="mb-3">
        <label class="form-label">Qty Adjustment</label>
        <input type="number" name="qty_adjustment" step="0.0001" class="form-control" required>
      </div>
      <div class="mb-3">
        <label class="form-label">Reason</label>
        <textarea name="reason" class="form-control"></textarea>
      </div>
    </div>
    @include('layouts.partials.form_footer', [
        'cancelUrl' => route('inventory.adjustments.index'),
        'cancelLabel' => 'Cancel',
        'cancelInline' => true,
        'buttons' => [['label' => 'Save Adjustment', 'type' => 'submit', 'class' => 'btn btn-primary']]
    ])
  </form>
</div>
@push('styles')
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/tom-select@2.3.1/dist/css/tom-select.bootstrap5.min.css">
<style>
  .ts-wrapper .ts-control{ background:#fff !important; opacity:1 !important; }
  .ts-dropdown{
    background:#fff !important; border:1px solid rgba(0,0,0,.12) !important;
    box-shadow:0 10px 24px rgba(0,0,0,.12) !important; z-index:1060 !important;
  }
  .ts-dropdown .option,.ts-dropdown .create,.ts-dropdown .no-results,.ts-dropdown .optgroup-header{ background:#fff !important; }
  .ts-dropdown .active{ background:#f1f5f9 !important; }
</style>
@endpush
@push('scripts')
<script>
(() => {
  const input = document.getElementById('adjustItemSearch');
  const itemIdInput = document.getElementById('adjust_item_id');
  const variantIdInput = document.getElementById('adjust_variant_id');
  const warehouseSel = document.getElementById('adjustWarehouseSelect');
  const balanceEl = document.getElementById('adjustBalanceValue');
  const balanceHint = document.getElementById('adjustBalanceHint');
  if (!input) return;

  window.ADJ_ITEM_OPTIONS = @json($itemOptions ?? []);

  function toNumber(val) {
    if (val === null || val === undefined) return 0;
    const s = String(val).trim();
    if (!s) return 0;
    const v = s.replace(/\./g, '').replace(',', '.');
    const n = parseFloat(v);
    return Number.isFinite(n) ? n : 0;
  }

  function ensureTomSelect(){
    return new Promise((resolve, reject)=>{
      if (window.TomSelect) return resolve(true);
      const s=document.createElement('script');
      s.src='https://cdn.jsdelivr.net/npm/tom-select@2.3.1/dist/js/tom-select.complete.min.js';
      s.onload=()=>resolve(true);
      s.onerror=reject;
      document.head.appendChild(s);
      if (!document.querySelector('link[href*=\"tom-select\"]')) {
        const l=document.createElement('link');
        l.rel='stylesheet';
        l.href='https://cdn.jsdelivr.net/npm/tom-select@2.3.1/dist/css/tom-select.bootstrap5.min.css';
        document.head.appendChild(l);
      }
    });
  }

  const renderBalance = (qty, uom) => {
    if (!balanceEl) return;
    const num = toNumber(qty);
    balanceEl.textContent = num.toLocaleString('id-ID', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + (uom ? ' ' + uom : '');
  };

  const fetchBalance = async () => {
    if (!itemIdInput?.value) {
      renderBalance(0);
      if (balanceHint) balanceHint.textContent = 'Pilih item untuk melihat saldo stok.';
      return;
    }
    if (balanceHint) balanceHint.textContent = '';

    const params = new URLSearchParams({ item_id: itemIdInput.value });
    if (variantIdInput?.value) params.append('variant_id', variantIdInput.value);
    if (warehouseSel?.value) params.append('warehouse_id', warehouseSel.value);

    try {
      const res = await fetch(@json(route('inventory.adjustments.summary')) + '?' + params.toString(), {
        headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
        credentials: 'same-origin'
      });
      if (!res.ok) throw new Error('failed');
      const data = await res.json();
      renderBalance(data.qty_balance || 0, data.uom || '');
    } catch (e) {
      renderBalance(0);
    }
  };

  ensureTomSelect().then(() => {
    const opts = (window.ADJ_ITEM_OPTIONS || []).map(o => ({
      value: String(o.value),
      label: o.label,
      item_id: o.item_id,
      variant_id: o.variant_id,
      sku: o.sku || '',
      unit: o.unit || 'pcs',
    }));

    const ts = new TomSelect(input, {
      options: opts,
      valueField: 'value',
      labelField: 'label',
      searchField: ['label','sku'],
      maxOptions: 50,
      create: false,
      persist: false,
      allowEmptyOption: true,
      dropdownParent: 'body',
      render: {
        option(d, esc){
          return `<div class=\"d-flex justify-content-between\"><span>${esc(d.label || '')}</span><span class=\"text-muted small\">${esc(d.unit || '')}</span></div>`;
        }
      },
      onChange(val){
        const opt = this.options[val];
        itemIdInput.value = opt ? (opt.item_id || '') : '';
        variantIdInput.value = opt ? (opt.variant_id || '') : '';
        fetchBalance();
      }
    });
    input.__ts = ts;

    const presetItem = itemIdInput?.value || '';
    const presetVariant = variantIdInput?.value || '';
    if (presetItem) {
      const target = opts.find(o => String(o.item_id) === String(presetItem)
        && String(o.variant_id || '') === String(presetVariant || ''));
      if (target) {
        ts.setValue(target.value, true);
      }
    }
    fetchBalance();
  });

  warehouseSel?.addEventListener('change', fetchBalance);
})();
</script>
@endpush
@endsection


