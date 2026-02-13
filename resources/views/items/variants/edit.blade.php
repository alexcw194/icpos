@extends('layouts.tabler')

@section('content')
<div class="container-xl">
  <form method="post" action="{{ route('variants.update', $variant) }}" class="card" id="variantEditForm">
    @csrf @method('PUT')
    <div class="card-header">
      <div class="card-title">Edit Variant - {{ $item->name }}</div>
      <div class="ms-auto btn-list">
        <a href="{{ route('items.variants.index', $item) }}" class="btn btn-secondary">Back</a>
        <button class="btn btn-primary">Update</button>
      </div>
    </div>
    <div class="card-body">
      @include('items.variants._form', [
        'colorOptions'  => $colorOptions ?? [],
        'sizeOptions'   => $sizeOptions ?? [],
        'lengthOptions' => $lengthOptions ?? [],
      ])
    </div>
  </form>
</div>
@endsection

@push('scripts')
<script>
(function () {
  const form = document.getElementById('variantEditForm');
  if (!form) return;

  const fields = ['price', 'default_cost']
    .map((name) => form.querySelector(`input[name="${name}"]`))
    .filter(Boolean);

  if (fields.length === 0) return;

  function parseFlexibleDecimal(raw) {
    if (raw == null) return null;

    let value = String(raw).trim();
    if (value === '') return null;

    value = value.replace(/\s/g, '').replace(/[^\d,.\-]/g, '');
    if (value === '' || value === '-' || value === ',' || value === '.') {
      return null;
    }

    const hasComma = value.includes(',');
    const hasDot = value.includes('.');

    if (hasComma && hasDot) {
      if (value.lastIndexOf(',') > value.lastIndexOf('.')) {
        value = value.replace(/\./g, '').replace(',', '.');
      } else {
        value = value.replace(/,/g, '');
      }
    } else if (hasComma) {
      if (/^-?\d{1,3}(,\d{3})+$/.test(value)) {
        value = value.replace(/,/g, '');
      } else {
        value = value.replace(',', '.');
      }
    } else if (hasDot && /^-?\d{1,3}(\.\d{3})+$/.test(value)) {
      value = value.replace(/\./g, '');
    }

    const number = Number(value);
    return Number.isFinite(number) ? number : null;
  }

  function formatMoney(number) {
    try {
      return new Intl.NumberFormat('id-ID', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
      }).format(number);
    } catch (_) {
      const fixed = (Math.round(number * 100) / 100).toFixed(2);
      const parts = fixed.split('.');
      return parts[0].replace(/\B(?=(\d{3})+(?!\d))/g, '.') + ',' + parts[1];
    }
  }

  function formatField(field) {
    const parsed = parseFlexibleDecimal(field.value);
    if (parsed === null) return;
    field.value = formatMoney(parsed);
  }

  function unformatField(field) {
    const parsed = parseFlexibleDecimal(field.value);
    if (parsed === null) return;
    field.value = String(parsed);
  }

  fields.forEach((field) => {
    if ((field.value || '').trim() !== '') {
      formatField(field);
    }

    field.addEventListener('focus', () => unformatField(field));
    field.addEventListener('blur', () => formatField(field));
  });

  form.addEventListener('submit', () => {
    fields.forEach((field) => {
      const parsed = parseFlexibleDecimal(field.value);
      if (parsed === null) {
        if ((field.value || '').trim() === '') {
          field.value = '';
        }
        return;
      }

      field.value = String(parsed);
    });
  });
})();
</script>
@endpush
