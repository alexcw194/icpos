@php
  $billingTermsData = $billingTermsData ?? [];
  if (empty($billingTermsData)) {
    $billingTermsData = [
      ['top_code' => '', 'percent' => 0, 'note' => ''],
    ];
  }
@endphp

<div class="card mb-3" id="billingTermsCard">
  <div class="card-header d-flex align-items-center">
    <h3 class="card-title mb-0">Billing Terms</h3>
    <button type="button" class="btn btn-sm btn-outline-primary ms-auto" id="btn-add-billing-term">+ Add Term</button>
  </div>
  <div class="table-responsive">
    <table class="table table-sm table-vcenter card-table" id="billing-terms-table">
      <thead>
        <tr>
          <th style="width:160px;">TOP Code</th>
          <th style="width:140px;" class="text-end">Percent</th>
          <th>Note (Milestone)</th>
          <th style="width:140px;">Status</th>
          <th style="width:1%"></th>
        </tr>
      </thead>
      <tbody>
        @foreach($billingTermsData as $i => $term)
          @php
            $status = $term['status'] ?? 'planned';
            $locked = $status !== 'planned';
          @endphp
          <tr data-term-row>
            <td>
              <select name="billing_terms[{{ $i }}][top_code]" class="form-select form-select-sm" @disabled($locked)>
                <option value="">-- pilih --</option>
                @foreach($topOptions as $opt)
                  @php
                    $label = $opt->code;
                    if (!empty($opt->description)) {
                      $label .= ' — '.$opt->description;
                    }
                    if (!$opt->is_active) {
                      $label .= ' (inactive)';
                    }
                  @endphp
                  <option value="{{ $opt->code }}" @selected(($term['top_code'] ?? '') === $opt->code)>{{ $label }}</option>
                @endforeach
              </select>
              @if($locked)
                <input type="hidden" name="billing_terms[{{ $i }}][top_code]" value="{{ $term['top_code'] ?? '' }}">
              @endif
            </td>
            <td>
              <input type="text" name="billing_terms[{{ $i }}][percent]" class="form-control form-control-sm text-end"
                     value="{{ $term['percent'] ?? 0 }}" @disabled($locked) @if($locked) data-skip-total="1" @endif>
              @if($locked)
                <input type="hidden" name="billing_terms[{{ $i }}][percent]" value="{{ $term['percent'] ?? 0 }}">
              @endif
            </td>
            <td>
              <input type="text" name="billing_terms[{{ $i }}][note]" class="form-control form-control-sm"
                     value="{{ $term['note'] ?? '' }}" @disabled($locked)>
              @if($locked)
                <input type="hidden" name="billing_terms[{{ $i }}][note]" value="{{ $term['note'] ?? '' }}">
              @endif
            </td>
            <td>{{ ucfirst($status) }}</td>
            <td>
              <button type="button" class="btn btn-sm btn-outline-danger btn-remove-billing-term" @disabled($locked)>Remove</button>
            </td>
          </tr>
        @endforeach
      </tbody>
    </table>
  </div>
  <div class="card-footer d-flex align-items-center">
    <div class="text-muted">Total percent harus 100%</div>
    <div class="ms-auto fw-semibold" id="billing-terms-total">0%</div>
  </div>
</div>

<template id="billing-term-row-tpl">
  <tr data-term-row>
    <td>
      <select name="billing_terms[__IDX__][top_code]" class="form-select form-select-sm">
        <option value="">-- pilih --</option>
        @foreach($topOptions as $opt)
          @php
            $label = $opt->code;
            if (!empty($opt->description)) {
              $label .= ' — '.$opt->description;
            }
            if (!$opt->is_active) {
              $label .= ' (inactive)';
            }
          @endphp
          <option value="{{ $opt->code }}">{{ $label }}</option>
        @endforeach
      </select>
    </td>
    <td>
      <input type="text" name="billing_terms[__IDX__][percent]" class="form-control form-control-sm text-end" value="0">
    </td>
    <td>
      <input type="text" name="billing_terms[__IDX__][note]" class="form-control form-control-sm" value="">
    </td>
    <td>Planned</td>
    <td>
      <button type="button" class="btn btn-sm btn-outline-danger btn-remove-billing-term">Remove</button>
    </td>
  </tr>
</template>

@push('scripts')
<script>
(function () {
  const table = document.getElementById('billing-terms-table');
  const addBtn = document.getElementById('btn-add-billing-term');
  const tpl = document.getElementById('billing-term-row-tpl');
  const totalEl = document.getElementById('billing-terms-total');
  if (!table || !addBtn || !tpl) return;

  const parseNum = (val) => {
    if (val == null) return 0;
    let s = String(val).trim();
    if (!s) return 0;
    s = s.replace(/\s/g, '');
    const hasComma = s.includes(',');
    const hasDot = s.includes('.');
    if (hasComma && hasDot) {
      s = s.replace(/\./g, '').replace(',', '.');
    } else {
      s = s.replace(',', '.');
    }
    const n = parseFloat(s);
    return isNaN(n) ? 0 : n;
  };

  const updateTotal = () => {
    let sum = 0;
    table.querySelectorAll('input[name*="[percent]"]:not([data-skip-total])').forEach((inp) => {
      sum += parseNum(inp.value);
    });
    if (totalEl) totalEl.textContent = sum.toFixed(2) + '%';
  };

  const reindex = () => {
    table.querySelectorAll('tbody tr[data-term-row]').forEach((row, idx) => {
      row.querySelectorAll('select,input').forEach((el) => {
        const name = el.getAttribute('name');
        if (!name) return;
        el.setAttribute('name', name.replace(/billing_terms\[\d+\]/, 'billing_terms[' + idx + ']'));
      });
    });
    updateTotal();
  };

  table.addEventListener('input', (e) => {
    if (e.target && e.target.name && e.target.name.includes('[percent]')) {
      updateTotal();
    }
  });

  table.addEventListener('click', (e) => {
    if (e.target && e.target.classList.contains('btn-remove-billing-term')) {
      e.preventDefault();
      const row = e.target.closest('tr');
      row?.remove();
      reindex();
    }
  });

  addBtn.addEventListener('click', () => {
    const idx = table.querySelectorAll('tbody tr[data-term-row]').length;
    const html = tpl.innerHTML.replace(/__IDX__/g, String(idx));
    const temp = document.createElement('tbody');
    temp.innerHTML = html.trim();
    const row = temp.firstElementChild;
    table.querySelector('tbody')?.appendChild(row);
    updateTotal();
  });

  updateTotal();
})();
</script>
@endpush
