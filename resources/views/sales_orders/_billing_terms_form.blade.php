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
          <th style="width:170px;">Schedule</th>
          <th style="width:120px; display:none;" class="text-end" data-col="offset">Offset Days</th>
          <th style="width:120px; display:none;" class="text-end" data-col="day">Day of Month</th>
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
                  <option value="{{ $opt->code }}" data-applicable="{{ is_array($opt->applicable_to ?? null) ? implode(',', $opt->applicable_to) : '' }}" @selected(($term['top_code'] ?? '') === $opt->code)>{{ $label }}</option>
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
              <select name="billing_terms[{{ $i }}][due_trigger]" class="form-select form-select-sm js-term-trigger" @disabled($locked)>
                @php $tr = $term['due_trigger'] ?? ''; @endphp
                <option value="">--</option>
                <option value="on_invoice" @selected($tr === 'on_invoice')>On Invoice</option>
                <option value="after_invoice_days" @selected($tr === 'after_invoice_days')>After Invoice Days</option>
                <option value="on_delivery" @selected($tr === 'on_delivery')>On Delivery</option>
                <option value="after_delivery_days" @selected($tr === 'after_delivery_days')>After Delivery Days</option>
                <option value="eom_day" @selected($tr === 'eom_day')>EOM Day</option>
                <option value="next_month_day" @selected($tr === 'next_month_day')>Next Month Day</option>
              </select>
              @if($locked)
                <input type="hidden" name="billing_terms[{{ $i }}][due_trigger]" value="{{ $term['due_trigger'] ?? '' }}">
              @endif
            </td>
            <td style="display:none;">
              <input type="number" name="billing_terms[{{ $i }}][offset_days]" class="form-control form-control-sm text-end js-term-offset"
                     value="{{ $term['offset_days'] ?? '' }}" min="0" inputmode="numeric" placeholder="e.g. 14" @disabled($locked)>
              @if($locked)
                <input type="hidden" name="billing_terms[{{ $i }}][offset_days]" value="{{ $term['offset_days'] ?? '' }}">
              @endif
            </td>
            <td style="display:none;">
              <input type="number" name="billing_terms[{{ $i }}][day_of_month]" class="form-control form-control-sm text-end js-term-day"
                     value="{{ $term['day_of_month'] ?? '' }}" min="1" max="28" inputmode="numeric" placeholder="e.g. 20" @disabled($locked)>
              @if($locked)
                <input type="hidden" name="billing_terms[{{ $i }}][day_of_month]" value="{{ $term['day_of_month'] ?? '' }}">
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
          <option value="{{ $opt->code }}" data-applicable="{{ is_array($opt->applicable_to ?? null) ? implode(',', $opt->applicable_to) : '' }}">{{ $label }}</option>
        @endforeach
      </select>
    </td>
    <td>
      <input type="text" name="billing_terms[__IDX__][percent]" class="form-control form-control-sm text-end" value="0">
    </td>
    <td>
      <select name="billing_terms[__IDX__][due_trigger]" class="form-select form-select-sm js-term-trigger">
        <option value="">--</option>
        <option value="on_invoice">On Invoice</option>
        <option value="after_invoice_days">After Invoice Days</option>
        <option value="on_delivery">On Delivery</option>
        <option value="after_delivery_days">After Delivery Days</option>
        <option value="eom_day">EOM Day</option>
        <option value="next_month_day">Next Month Day</option>
      </select>
    </td>
    <td style="display:none;">
      <input type="number" name="billing_terms[__IDX__][offset_days]" class="form-control form-control-sm text-end js-term-offset" value="" min="0" inputmode="numeric" placeholder="e.g. 14">
    </td>
    <td style="display:none;">
      <input type="number" name="billing_terms[__IDX__][day_of_month]" class="form-control form-control-sm text-end js-term-day" value="" min="1" max="28" inputmode="numeric" placeholder="e.g. 20">
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

  const updateScheduleVisibility = (row) => {
    if (!row) return;
    const trigger = row.querySelector('.js-term-trigger')?.value || '';
    const offsetInput = row.querySelector('.js-term-offset');
    const dayInput = row.querySelector('.js-term-day');
    const offsetTd = offsetInput?.closest('td');
    const dayTd = dayInput?.closest('td');

    const showOffset = ['after_invoice_days', 'after_delivery_days'].includes(trigger);
    const showDay = ['eom_day', 'next_month_day'].includes(trigger);

    if (offsetTd) offsetTd.style.display = showOffset ? '' : 'none';
    if (dayTd) dayTd.style.display = showDay ? '' : 'none';

    if (offsetInput) {
      if (showOffset) {
        offsetInput.disabled = false;
      } else {
        offsetInput.value = '';
        offsetInput.disabled = true;
      }
    }
    if (dayInput) {
      if (showDay) {
        dayInput.disabled = false;
      } else {
        dayInput.value = '';
        dayInput.disabled = true;
      }
    }

    row.dataset.showOffset = showOffset ? '1' : '0';
    row.dataset.showDay = showDay ? '1' : '0';
  };

  const applyScheduleVisibility = () => {
    table.querySelectorAll('tbody tr[data-term-row]').forEach(updateScheduleVisibility);
    updateScheduleColumns();
  };

  const updateScheduleColumns = () => {
    const rows = Array.from(table.querySelectorAll('tbody tr[data-term-row]'));
    const anyOffset = rows.some((row) => row.dataset.showOffset === '1');
    const anyDay = rows.some((row) => row.dataset.showDay === '1');
    const offsetTh = table.querySelector('th[data-col="offset"]');
    const dayTh = table.querySelector('th[data-col="day"]');
    if (offsetTh) offsetTh.style.display = anyOffset ? '' : 'none';
    if (dayTh) dayTh.style.display = anyDay ? '' : 'none';

    if (!anyOffset) {
      rows.forEach((row) => {
        const offsetTd = row.querySelector('.js-term-offset')?.closest('td');
        if (offsetTd) offsetTd.style.display = 'none';
      });
    }
    if (!anyDay) {
      rows.forEach((row) => {
        const dayTd = row.querySelector('.js-term-day')?.closest('td');
        if (dayTd) dayTd.style.display = 'none';
      });
    }
  };

  table.addEventListener('input', (e) => {
    if (e.target && e.target.name && e.target.name.includes('[percent]')) {
      updateTotal();
    }
  });
  table.addEventListener('change', (e) => {
    if (e.target && e.target.classList.contains('js-term-trigger')) {
      updateScheduleVisibility(e.target.closest('tr'));
      updateScheduleColumns();
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
    applyTopFilter();
    applyScheduleVisibility();
  });

  function applyTopFilter() {
    const poTypeSelect = document.querySelector('select[name="po_type"]');
    const type = poTypeSelect?.value || 'goods';
    table.querySelectorAll('select[name*="[top_code]"]').forEach((sel) => {
      Array.from(sel.options).forEach((opt) => {
        if (!opt.value) return;
        const raw = (opt.dataset.applicable || '').trim();
        if (!raw) {
          opt.disabled = false;
          opt.hidden = false;
          return;
        }
        const allowed = raw.split(',').map((v) => v.trim()).filter(Boolean);
        const ok = allowed.includes(type);
        opt.disabled = !ok;
        opt.hidden = !ok;
        if (!ok && opt.selected) {
          opt.selected = false;
        }
      });
    });
  }

  document.querySelector('select[name="po_type"]')?.addEventListener('change', applyTopFilter);

  updateTotal();
  applyTopFilter();
  applyScheduleVisibility();

  window.setBillingTermsRows = function(rows) {
    const body = table.querySelector('tbody');
    if (!body) return;
    body.innerHTML = '';
    (rows || []).forEach((row, idx) => {
      const html = tpl.innerHTML.replace(/__IDX__/g, String(idx));
      const temp = document.createElement('tbody');
      temp.innerHTML = html.trim();
      const tr = temp.firstElementChild;
      if (!tr) return;
      tr.querySelector('select[name*="[top_code]"]')?.value = row.top_code || '';
      tr.querySelector('input[name*="[percent]"]')?.value = row.percent ?? 0;
      tr.querySelector('select[name*="[due_trigger]"]')?.value = row.due_trigger || '';
      tr.querySelector('input[name*="[offset_days]"]')?.value = row.offset_days ?? '';
      tr.querySelector('input[name*="[day_of_month]"]')?.value = row.day_of_month ?? '';
      tr.querySelector('input[name*="[note]"]')?.value = row.note ?? '';
      body.appendChild(tr);
    });
    reindex();
    applyTopFilter();
    applyScheduleVisibility();
  };
})();
</script>
@endpush
