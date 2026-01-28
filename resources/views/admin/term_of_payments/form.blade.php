@extends('layouts.tabler')

@section('content')
<div class="page-header d-print-none">
  <div class="container-xl d-flex align-items-center">
    <h2 class="page-title">
      Master Data - Term of Payment
      <span class="text-muted">{{ $row->exists ? 'Edit' : 'Create' }}</span>
    </h2>
    <a href="{{ route('term-of-payments.index') }}" class="btn btn-link ms-auto">Kembali</a>
  </div>
</div>

<div class="page-body">
  <div class="container-xl">
    @if ($errors->any())
      <div class="alert alert-danger">
        <ul class="mb-0">
          @foreach ($errors->all() as $error)
            <li>{{ $error }}</li>
          @endforeach
        </ul>
      </div>
    @endif

    <div class="card">
      <form method="post" action="{{ $row->exists ? route('term-of-payments.update', $row) : route('term-of-payments.store') }}">
        @csrf
        @if($row->exists) @method('PUT') @endif
        <div class="card-body">
          <div class="row g-3">
            <div class="col-md-4">
              <label class="form-label">Code</label>
              @if($row->exists)
                <input type="text" class="form-control" value="{{ $row->code }}" readonly>
              @else
                <select name="code" class="form-select" required>
                  <option value="">— pilih kode —</option>
                  @foreach($availableCodes as $code)
                    <option value="{{ $code }}" @selected(old('code') === $code)>{{ $code }}</option>
                  @endforeach
                </select>
                <div class="form-hint">Kode hanya boleh dari whitelist sistem.</div>
              @endif
            </div>
            <div class="col-md-6">
              <label class="form-label">Description</label>
              <input type="text" name="description" class="form-control"
                     value="{{ old('description', $row->description) }}"
                     placeholder="Optional (mis: Down Payment)">
            </div>
            <div class="col-md-2">
              <label class="form-label">Active</label>
              <label class="form-check form-switch">
                <input class="form-check-input" type="checkbox" name="is_active" value="1"
                       @checked(old('is_active', $row->is_active))>
                <span class="form-check-label">Active</span>
              </label>
            </div>
            <div class="col-12">
              <label class="form-label">Applicable To</label>
              @php $applies = old('applicable_to', $row->applicable_to ?? ['goods','project','maintenance']); @endphp
              <div class="d-flex flex-wrap gap-3">
                <label class="form-check">
                  <input class="form-check-input" type="checkbox" name="applicable_to[]" value="goods" @checked(in_array('goods', $applies, true))>
                  <span class="form-check-label">Goods</span>
                </label>
                <label class="form-check">
                  <input class="form-check-input" type="checkbox" name="applicable_to[]" value="project" @checked(in_array('project', $applies, true))>
                  <span class="form-check-label">Project</span>
                </label>
                <label class="form-check">
                  <input class="form-check-input" type="checkbox" name="applicable_to[]" value="maintenance" @checked(in_array('maintenance', $applies, true))>
                  <span class="form-check-label">Maintenance</span>
                </label>
              </div>
              <div class="form-hint">Kosong = berlaku untuk semua.</div>
            </div>
          </div>
        </div>
        <div class="card-body border-top">
          <div class="d-flex align-items-center mb-2">
            <h3 class="card-title mb-0">Schedule Rows</h3>
            <button type="button" class="btn btn-sm btn-outline-primary ms-auto" id="btn-add-schedule">+ Add Row</button>
          </div>
          @php
            $schedulesData = old('schedules');
            if ($schedulesData === null) {
        $schedulesData = ($row->schedules ?? collect())->map(function ($s) {
          return [
            'portion_type' => $s->portion_type,
            'portion_value' => $s->portion_value,
            'due_trigger' => $s->due_trigger,
            'offset_days' => $s->offset_days,
            'specific_day' => $s->specific_day,
            'notes' => $s->notes,
          ];
        })->toArray();
      }
      $normalizeTrigger = function ($value) {
        $value = (string) ($value ?? '');
        if ($value === 'on_so') return 'on_invoice';
        if ($value === 'end_of_month') return 'next_month_day';
        return $value;
      };
      $schedulesData = array_map(function ($row) use ($normalizeTrigger) {
        $row['due_trigger'] = $normalizeTrigger($row['due_trigger'] ?? null);
        return $row;
      }, $schedulesData);
      $formatPortion = function ($value) {
        if ($value === null || $value === '') return '';
              $num = (float) $value;
              $str = number_format($num, 4, '.', '');
              return rtrim(rtrim($str, '0'), '.');
            };
          @endphp
          <div class="table-responsive">
            <table class="table table-sm table-vcenter" id="schedule-table">
              <thead>
                <tr>
                  <th style="width:120px;">Type</th>
                  <th style="width:160px;" class="text-end">Value</th>
                  <th style="width:180px;">Trigger</th>
                  <th style="width:120px;" class="text-end">Offset Days</th>
                  <th style="width:120px;" class="text-end">Day of Month</th>
                  <th>Notes</th>
                  <th style="width:1%"></th>
                </tr>
              </thead>
              <tbody>
                @forelse($schedulesData as $i => $sch)
                  <tr data-schedule-row>
                    <td>
                      <select name="schedules[{{ $i }}][portion_type]" class="form-select form-select-sm portion-type">
                        <option value="percent" @selected(($sch['portion_type'] ?? '') === 'percent')>Percent</option>
                        <option value="fixed" @selected(($sch['portion_type'] ?? '') === 'fixed')>Fixed</option>
                      </select>
                    </td>
                    <td>
                      <input type="text" name="schedules[{{ $i }}][portion_value]" class="form-control form-control-sm text-end portion-value"
                             value="{{ $formatPortion($sch['portion_value'] ?? 0) }}">
                    </td>
                    <td>
                      <select name="schedules[{{ $i }}][due_trigger]" class="form-select form-select-sm due-trigger">
                        @php $tr = $sch['due_trigger'] ?? 'on_invoice'; @endphp
                        <option value="on_invoice" @selected($tr === 'on_invoice')>On Invoice</option>
                        <option value="after_invoice_days" @selected($tr === 'after_invoice_days')>After Invoice Days</option>
                        <option value="on_delivery" @selected($tr === 'on_delivery')>On Delivery</option>
                        <option value="after_delivery_days" @selected($tr === 'after_delivery_days')>After Delivery Days</option>
                        <option value="eom_day" @selected($tr === 'eom_day')>EOM Day</option>
                        <option value="next_month_day" @selected($tr === 'next_month_day')>Next Month Day</option>
                      </select>
                    </td>
                    <td>
                      <input type="text" name="schedules[{{ $i }}][offset_days]" class="form-control form-control-sm text-end offset-days"
                             value="{{ $sch['offset_days'] ?? '' }}">
                    </td>
                    <td>
                      <input type="text" name="schedules[{{ $i }}][specific_day]" class="form-control form-control-sm text-end specific-day"
                             value="{{ $sch['specific_day'] ?? '' }}">
                    </td>
                    <td>
                      <input type="text" name="schedules[{{ $i }}][notes]" class="form-control form-control-sm"
                             value="{{ $sch['notes'] ?? '' }}">
                    </td>
                    <td>
                      <button type="button" class="btn btn-sm btn-outline-danger btn-remove-schedule">Remove</button>
                    </td>
                  </tr>
                @empty
                  <tr data-schedule-row>
                    <td>
                      <select name="schedules[0][portion_type]" class="form-select form-select-sm portion-type">
                        <option value="percent">Percent</option>
                        <option value="fixed">Fixed</option>
                      </select>
                    </td>
                    <td>
                      <input type="text" name="schedules[0][portion_value]" class="form-control form-control-sm text-end portion-value" value="100">
                    </td>
                    <td>
                      <select name="schedules[0][due_trigger]" class="form-select form-select-sm due-trigger">
                        <option value="on_invoice">On Invoice</option>
                        <option value="after_invoice_days">After Invoice Days</option>
                        <option value="on_delivery">On Delivery</option>
                        <option value="after_delivery_days">After Delivery Days</option>
                        <option value="eom_day">EOM Day</option>
                        <option value="next_month_day">Next Month Day</option>
                      </select>
                    </td>
                    <td><input type="text" name="schedules[0][offset_days]" class="form-control form-control-sm text-end offset-days" value=""></td>
                    <td><input type="text" name="schedules[0][specific_day]" class="form-control form-control-sm text-end specific-day" value=""></td>
                    <td><input type="text" name="schedules[0][notes]" class="form-control form-control-sm" value=""></td>
                    <td><button type="button" class="btn btn-sm btn-outline-danger btn-remove-schedule">Remove</button></td>
                  </tr>
                @endforelse
              </tbody>
            </table>
          </div>
          <div class="text-muted">Total percent (jika semua baris percent) harus 100%.</div>
        </div>
        <div class="card-footer text-end">
          <button type="submit" class="btn btn-primary">{{ $row->exists ? 'Update' : 'Create' }}</button>
        </div>
      </form>
    </div>
  </div>
</div>

@push('scripts')
<script>
(function(){
  const table = document.getElementById('schedule-table');
  const addBtn = document.getElementById('btn-add-schedule');
  if (!table || !addBtn) return;

  const reindex = () => {
    table.querySelectorAll('tbody tr[data-schedule-row]').forEach((row, idx) => {
      row.querySelectorAll('select,input').forEach((el) => {
        const name = el.getAttribute('name');
        if (!name) return;
        el.setAttribute('name', name.replace(/schedules\[\d+\]/, 'schedules[' + idx + ']'));
      });
    });
  };

  const updateScheduleVisibility = (row) => {
    if (!row) return;
    const trigger = row.querySelector('.due-trigger')?.value || '';
    const offsetTd = row.querySelector('.offset-days')?.closest('td');
    const dayTd = row.querySelector('.specific-day')?.closest('td');

    const showOffset = ['after_invoice_days', 'after_delivery_days'].includes(trigger);
    const showDay = ['eom_day', 'next_month_day'].includes(trigger);

    if (offsetTd) offsetTd.style.display = showOffset ? '' : 'none';
    if (dayTd) dayTd.style.display = showDay ? '' : 'none';
  };

  const applyScheduleVisibility = () => {
    table.querySelectorAll('tbody tr[data-schedule-row]').forEach(updateScheduleVisibility);
  };

  const formatPortion = (val) => {
    if (val == null) return '';
    let s = String(val).trim();
    if (!s) return '';
    s = s.replace(/\s/g, '');
    const hasComma = s.includes(',');
    const hasDot = s.includes('.');
    if (hasComma && hasDot) {
      s = s.replace(/\./g, '').replace(',', '.');
    } else {
      s = s.replace(',', '.');
    }
    const num = parseFloat(s);
    if (Number.isNaN(num)) return '';
    const fixed = num.toFixed(4);
    return fixed.replace(/\.?0+$/, '');
  };

  addBtn.addEventListener('click', () => {
    const idx = table.querySelectorAll('tbody tr[data-schedule-row]').length;
    const row = document.createElement('tr');
    row.setAttribute('data-schedule-row','');
    row.innerHTML = `
      <td>
        <select name="schedules[${idx}][portion_type]" class="form-select form-select-sm portion-type">
          <option value="percent">Percent</option>
          <option value="fixed">Fixed</option>
        </select>
      </td>
      <td><input type="text" name="schedules[${idx}][portion_value]" class="form-control form-control-sm text-end portion-value" value="0"></td>
      <td>
        <select name="schedules[${idx}][due_trigger]" class="form-select form-select-sm due-trigger">
          <option value="on_invoice">On Invoice</option>
          <option value="after_invoice_days">After Invoice Days</option>
          <option value="on_delivery">On Delivery</option>
          <option value="after_delivery_days">After Delivery Days</option>
          <option value="eom_day">EOM Day</option>
          <option value="next_month_day">Next Month Day</option>
        </select>
      </td>
      <td><input type="text" name="schedules[${idx}][offset_days]" class="form-control form-control-sm text-end offset-days" value=""></td>
      <td><input type="text" name="schedules[${idx}][specific_day]" class="form-control form-control-sm text-end specific-day" value=""></td>
      <td><input type="text" name="schedules[${idx}][notes]" class="form-control form-control-sm" value=""></td>
      <td><button type="button" class="btn btn-sm btn-outline-danger btn-remove-schedule">Remove</button></td>
    `;
    table.querySelector('tbody')?.appendChild(row);
    updateScheduleVisibility(row);
  });

  table.addEventListener('click', (e) => {
    if (!e.target.classList.contains('btn-remove-schedule')) return;
    e.preventDefault();
    e.target.closest('tr')?.remove();
    reindex();
  });

  table.addEventListener('change', (e) => {
    if (e.target.classList.contains('due-trigger')) {
      updateScheduleVisibility(e.target.closest('tr'));
    }
  });

  table.addEventListener('blur', (e) => {
    if (e.target.classList.contains('portion-value')) {
      e.target.value = formatPortion(e.target.value);
    }
  }, true);

  applyScheduleVisibility();
})();
</script>
@endpush
@endsection
