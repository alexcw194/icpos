{{-- resources/views/items/create.blade.php --}}
@extends('layouts.tabler')

@section('content')
<div class="container-xl">
  <form action="{{ route('items.store') }}" method="POST" class="card" id="itemCreateForm">
    @csrf

    <div class="card-header">
      <div class="card-title">Tambah Item</div>
      <div class="ms-auto text-muted small">
        Kelola varian tersedia setelah item disimpan.
      </div>
    </div>

    {{-- Teruskan $defaultUnitId ke _form supaya select Unit default ke PCS --}}
    @include('items._form', ['defaultUnitId' => $defaultUnitId ?? null])

    {{-- Footer global (Batal di kiri, aksi di kanan, inline group) --}}
    @include('layouts.partials.form_footer', [
      'cancelUrl'    => route('items.index'),
      'cancelLabel'  => 'Batal',
      'cancelInline' => true,
      'buttons' => [
        ['type'=>'submit','name'=>'action','value'=>'save',           'label'=>'Simpan',                'class'=>'btn btn-primary'],
        ['type'=>'submit','name'=>'action','value'=>'save_variants',  'label'=>'Simpan & Kelola Varian','class'=>'btn btn-primary'],
        ['type'=>'submit','name'=>'action','value'=>'save_add',       'label'=>'Simpan & Tambah',       'class'=>'btn btn-primary'],
      ],
    ])
  </form>
</div>
@endsection

@push('scripts')
<script>
(function () {
  const form  = document.getElementById('itemCreateForm');
  if (!form) return;
  const input = form.querySelector('input[name="price"]');
  if (!input) return;

  function toNum(v){
    if (v == null) return 0;
    v = String(v).trim().replace(/\s/g,'');
    if (v === '') return 0;
    const hasC = v.includes(','), hasD = v.includes('.');
    const thousandDot = /^\d{1,3}(\.\d{3})+$/;
    if (hasC && hasD) v = v.replace(/\./g,'').replace(',', '.');
    else if (hasC)    v = v.replace(',', '.');
    else if (hasD && thousandDot.test(v)) v = v.replace(/\./g,'');
    const n = parseFloat(v); return isNaN(n) ? 0 : n;
  }
  function formatMoney(n){
    try {
      return new Intl.NumberFormat('id-ID',{minimumFractionDigits:2,maximumFractionDigits:2}).format(n);
    } catch {
      const f=(Math.round(n*100)/100).toFixed(2);
      const [i,d]=f.split('.');
      return i.replace(/\B(?=(\d{3})+(?!\d))/g,'.')+','+d;
    }
  }
  function unformat(){ input.value = String(toNum(input.value)); }
  function format(){ if ((input.value||'').trim()==='') return; input.value = formatMoney(toNum(input.value)); }

  if ((input.value||'').trim()!=='') format();
  input.addEventListener('focus', unformat);
  input.addEventListener('blur',  format);
  form.addEventListener('submit', () => { input.value = String(toNum(input.value)); });
})();
</script>
@endpush
