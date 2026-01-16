{{-- resources/views/items/edit.blade.php --}}
@extends('layouts.tabler')

@section('content')
<div class="container-xl">
  @php
    $isProjectItems = request()->routeIs('project-items.*');
    $formAction = $isProjectItems ? route('project-items.update', $item) : route('items.update', $item);
    $cancelUrl = request('r', $isProjectItems ? route('project-items.index') : route('items.index'));
    $pageTitle = $isProjectItems ? 'Edit Project Item' : 'Edit Item';
    $forceItemType = $forceItemType ?? ($isProjectItems ? 'project' : null);
  @endphp

  <form action="{{ $formAction }}" method="POST" class="card" id="itemEditForm">
    @csrf
    @method('PUT')

    <div class="card-header">
      <div class="card-title">{{ $pageTitle }}: {{ $item->name }}</div>
      <div class="ms-auto btn-list">
        <a href="{{ route('items.variants.index', $item) }}" class="btn btn-primary">Kelola Varian</a>
      </div>
    </div>

    @include('items._form', ['item' => $item, 'forceItemType' => $forceItemType])

    {{-- Footer global --}}
    @include('layouts.partials.form_footer', [
      'cancelUrl'    => $cancelUrl,
      'cancelLabel'  => 'Batal',
      'cancelInline' => true,
      'buttons' => [
        ['type'=>'submit','name'=>'action','value'=>'save',          'label'=>'Simpan','class'=>'btn btn-primary'],
        ['type'=>'submit','name'=>'action','value'=>'save_variants', 'label'=>'Simpan & Kelola Varian','class'=>'btn btn-primary'],
      ],
    ])
  </form>

  {{-- Enterprise safety: Delete only here (Danger Zone) --}}
  @hasanyrole('SuperAdmin|Admin')
    <div class="card mt-3 border-danger">
      <div class="card-header">
        <div class="card-title text-danger">Danger Zone</div>
      </div>
      <div class="card-body">
        <div class="text-muted mb-3">
          Aksi ini bersifat permanen dan berdampak ke data terkait. Pastikan item sudah benar untuk dihapus.
        </div>

        <form action="{{ $isProjectItems ? route('project-items.destroy', $item) : route('items.destroy', $item) }}" method="POST"
              onsubmit="return confirm('Hapus item ini? Tindakan ini tidak bisa dibatalkan.');">
          @csrf
          @method('DELETE')
          <button type="submit" class="btn btn-danger">
            Hapus Item
          </button>
        </form>
      </div>
    </div>
  @endhasanyrole
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
