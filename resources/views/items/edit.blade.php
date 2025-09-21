{{-- resources/views/items/edit.blade.php --}}
@extends('layouts.tabler')

@section('content')
<div class="container-xl">
  <form action="{{ route('items.update', $item) }}" method="POST" class="card" id="itemEditForm">
    @csrf
    @method('PUT')

    <div class="card-header">
      <div class="card-title">Edit Item: {{ $item->name }}</div>
    </div>

    @include('items._form', ['item' => $item])

    {{-- Footer global --}}
    @include('layouts.partials.form_footer', [
      'cancelUrl'    => route('items.index'),
      'cancelLabel'  => 'Batal',
      'cancelInline' => true,
      'buttons' => [
        ['type'=>'submit','label'=>'Simpan','class'=>'btn btn-primary'],
      ],
    ])
  </form>
</div>
@endsection

@push('scripts')
<script>
(function () {
  const form  = document.getElementById('itemEditForm');
  if (!form) return;

  const input = form.querySelector('input[name="price"]');
  if (!input) return;

  function toNum(v) {
    if (v == null) return 0;
    v = String(v).trim().replace(/\s/g, '');
    if (v === '') return 0;

    const hasComma = v.includes(',');
    const hasDot   = v.includes('.');
    const thousandDotPattern = /^\d{1,3}(\.\d{3})+$/; // 1.234 atau 12.345.678

    if (hasComma && hasDot) {
      v = v.replace(/\./g, '').replace(',', '.');
    } else if (hasComma && !hasDot) {
      v = v.replace(',', '.');
    } else if (!hasComma && hasDot && thousandDotPattern.test(v)) {
      v = v.replace(/\./g, '');
    }

    const n = parseFloat(v);
    return isNaN(n) ? 0 : n;
  }

  function formatMoney(n) {
    try {
      return new Intl.NumberFormat('id-ID', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2
      }).format(n);
    } catch (e) {
      const fixed = (Math.round(n * 100) / 100).toFixed(2);
      const [intPart, decPart] = fixed.split('.');
      const withSep = intPart.replace(/\B(?=(\d{3})+(?!\d))/g, '.');
      return withSep + ',' + decPart;
    }
  }

  function unformatInput(el) { el.value = String(toNum(el.value)); }
  function formatInput(el)   {
    const val = (el.value || '').trim();
    if (val === '') return;
    el.value = formatMoney(toNum(el.value));
  }

  if ((input.value || '').trim() !== '') {
    formatInput(input);
  }

  input.addEventListener('focus', () => unformatInput(input));
  input.addEventListener('blur',  () => formatInput(input));

  form.addEventListener('submit', () => {
    input.value = String(toNum(input.value));
  });
})();
</script>
@endpush
