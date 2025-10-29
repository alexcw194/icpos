{{-- resources/views/customers/_billing_shipping.blade.php --}}
@php
  $c = $customer ?? null;
  $old = fn($k,$def='') => old($k, $c->$k ?? $def);
  $countries = [
    ''  => 'Nothing selected',
    'ID'=> 'Indonesia',
    'SG'=> 'Singapore',
    'MY'=> 'Malaysia',
  ];
@endphp

<div class="card">
  <div class="card-body">
    <div class="row g-4">
      {{-- Billing --}}
      <div class="col-12 col-lg-6">
        <div class="d-flex align-items-center justify-content-between mb-2">
          <div class="h4 m-0">Billing Address</div>
          <a href="#" class="small" id="copyFromCustomer">Same as Customer Info</a>
        </div>

        <div class="mb-2">
          <label class="form-label">Street</label>
          <textarea name="billing_street" class="form-control" rows="3">{{ $old('billing_street') }}</textarea>
        </div>

        <div class="row g-2">
          <div class="col-md-6">
            <label class="form-label">City</label>
            <input name="billing_city" class="form-control" value="{{ $old('billing_city') }}">
          </div>
          <div class="col-md-6">
            <label class="form-label">State</label>
            <input name="billing_state" class="form-control" value="{{ $old('billing_state') }}">
          </div>
          <div class="col-md-6">
            <label class="form-label">Zip Code</label>
            <input name="billing_zip" class="form-control" value="{{ $old('billing_zip') }}">
          </div>
          <div class="col-md-6">
            <label class="form-label">Country</label>
            <select name="billing_country" class="form-select">
              @foreach($countries as $code=>$name)
                <option value="{{ $code }}" @selected($old('billing_country')===$code)>{{ $name }}</option>
              @endforeach
            </select>
          </div>
        </div>

        <div class="mt-3">
          <label class="form-label">Billing Notes (cara penagihan)</label>
          <textarea name="billing_notes" class="form-control" rows="2"
            placeholder="Contoh: Transfer 30 hari, lampirkan PO, alamat penagihan khusus, dsb.">{{ $old('billing_notes') }}</textarea>
        </div>
      </div>

      {{-- Shipping --}}
      <div class="col-12 col-lg-6">
        <div class="d-flex align-items-center justify-content-between mb-2">
          <div class="h4 m-0">
            <i class="ti ti-question-circle me-1"></i> Shipping Address
          </div>
          <a href="#" class="small" id="copyFromBilling">Copy Billing Address</a>
        </div>

        <div class="mb-2">
          <label class="form-label">Street</label>
          <textarea name="shipping_street" class="form-control" rows="3">{{ $old('shipping_street') }}</textarea>
        </div>

        <div class="row g-2">
          <div class="col-md-6">
            <label class="form-label">City</label>
            <input name="shipping_city" class="form-control" value="{{ $old('shipping_city') }}">
          </div>
          <div class="col-md-6">
            <label class="form-label">State</label>
            <input name="shipping_state" class="form-control" value="{{ $old('shipping_state') }}">
          </div>
          <div class="col-md-6">
            <label class="form-label">Zip Code</label>
            <input name="shipping_zip" class="form-control" value="{{ $old('shipping_zip') }}">
          </div>
          <div class="col-md-6">
            <label class="form-label">Country</label>
            <select name="shipping_country" class="form-select">
              @foreach($countries as $code=>$name)
                <option value="{{ $code }}" @selected($old('shipping_country')===$code)>{{ $name }}</option>
              @endforeach
            </select>
          </div>
        </div>

        <div class="mt-3">
          <label class="form-label">Shipping Notes (cara pengiriman)</label>
          <textarea name="shipping_notes" class="form-control" rows="2"
            placeholder="Contoh: Kirim via JNE/Expedisi X, jam kerja, kebutuhan surat jalan, dsb.">{{ $old('shipping_notes') }}</textarea>
        </div>
      </div>
    </div>
  </div>
</div>

@push('scripts')
<script>
(function(){
  const Q = s => document.querySelector(s);
  const set = (name, val) => { const el = Q(`[name="${name}"]`); if (!el) return; el.tagName==='TEXTAREA' ? el.value = val : (el.value = val); };

  // Copy Billing -> Shipping
  Q('#copyFromBilling')?.addEventListener('click', (e)=>{
    e.preventDefault();
    set('shipping_street',  Q('[name="billing_street"]')?.value || '');
    set('shipping_city',    Q('[name="billing_city"]')?.value || '');
    set('shipping_state',   Q('[name="billing_state"]')?.value || '');
    set('shipping_zip',     Q('[name="billing_zip"]')?.value || '');
    set('shipping_country', Q('[name="billing_country"]')?.value || '');
    set('shipping_notes',   Q('[name="billing_notes"]')?.value || '');
  });

  // Same as Customer Info (pakai alamat utama customer jika ada)
  Q('#copyFromCustomer')?.addEventListener('click', (e)=>{
    e.preventDefault();
    // Sesuaikan selector berikut dengan field alamat utama di form kamu (contoh: name="address")
    const mainAddr = Q('[name="address"]')?.value || '';
    set('billing_street', mainAddr);
  });
})();
</script>
@endpush
