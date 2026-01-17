@php
  $sectionsData = old('sections');
  if (!$sectionsData) {
    $sectionsData = collect($sections)->map(function ($section) {
      $lines = collect(data_get($section, 'lines', []))->map(function ($line) {
        return [
          'line_no' => data_get($line, 'line_no'),
          'description' => data_get($line, 'description'),
          'qty' => data_get($line, 'qty', 1),
          'unit' => data_get($line, 'unit', 'LS'),
          'unit_price' => data_get($line, 'unit_price', 0),
          'material_total' => data_get($line, 'material_total', 0),
          'labor_total' => data_get($line, 'labor_total', 0),
          'source_type' => data_get($line, 'source_type', 'item'),
          'item_label' => data_get($line, 'item_label', ''),
        ];
      })->toArray();
      return [
        'name' => data_get($section, 'name'),
        'sort_order' => data_get($section, 'sort_order', 0),
        'lines' => $lines,
      ];
    })->toArray();
  }

  $paymentTermsData = old('payment_terms');
  if (!$paymentTermsData) {
    $paymentTermsData = collect($paymentTerms)->map(function ($term, $idx) {
      return [
        'code' => data_get($term, 'code', 'DP'),
        'label' => data_get($term, 'label', data_get($term, 'code', 'DP')),
        'percent' => data_get($term, 'percent', 0),
        'sequence' => data_get($term, 'sequence', $idx + 1),
        'trigger_note' => data_get($term, 'trigger_note'),
      ];
    })->toArray();
  }

  $companyId = old('company_id', $quotation->company_id ?? $project->company_id ?? ($companies->first()->id ?? null));
  $customerId = old('customer_id', $quotation->customer_id ?? $project->customer_id ?? null);
  $salesOwnerId = old('sales_owner_user_id', $quotation->sales_owner_user_id ?? $project->sales_owner_user_id ?? auth()->id());
  $contacts = $contacts ?? collect();
@endphp

<div class="card mb-3">
  <div class="card-header">
    <h3 class="card-title">BQ Header</h3>
  </div>
  <div class="card-body">
    <div class="row g-3">
      <div class="col-md-4">
        <label class="form-label">Company</label>
        <select name="company_id" class="form-select" required>
          @foreach($companies as $co)
            <option value="{{ $co->id }}"
                    data-taxable="{{ (int) $co->is_taxable }}"
                    data-tax="{{ (float) ($co->default_tax_percent ?? 0) }}"
                    @selected((string)$companyId === (string)$co->id)>
              {{ $co->alias ?: $co->name }}
            </option>
          @endforeach
        </select>
      </div>
      <div class="col-md-4">
        <label class="form-label">Customer</label>
        <select name="customer_id" class="form-select" required>
          @foreach($project->customer ? [$project->customer] : [] as $cust)
            <option value="{{ $cust->id }}" @selected((string)$customerId === (string)$cust->id)>
              {{ $cust->name }}
            </option>
          @endforeach
          @if(!$project->customer)
            <option value="">Pilih Customer</option>
          @endif
        </select>
      </div>
      <div class="col-md-4">
        <label class="form-label">Sales Owner</label>
        <select name="sales_owner_user_id" class="form-select" required>
          @foreach($salesUsers as $su)
            <option value="{{ $su->id }}" @selected((string)$salesOwnerId === (string)$su->id)>{{ $su->name }}</option>
          @endforeach
        </select>
      </div>
      <div class="col-md-4">
        <label class="form-label">Quotation Date</label>
        <input type="date" name="quotation_date" class="form-control" value="{{ old('quotation_date', optional($quotation->quotation_date)->format('Y-m-d') ?? now()->toDateString()) }}" required>
      </div>
      <div class="col-md-4">
        <label class="form-label">To</label>
        <input type="text" name="to_name" class="form-control" value="{{ old('to_name', $quotation->to_name ?? $project->customer->name ?? '') }}" required>
      </div>
      <div class="col-md-4">
        <label class="form-label">Attn</label>
        <input type="text"
               name="attn_name"
               class="form-control"
               list="bqAttnContacts"
               value="{{ old('attn_name', $quotation->attn_name ?? '') }}">
        <datalist id="bqAttnContacts">
          @foreach($contacts as $contact)
            @php $label = $contact->full_name ?: $contact->name; @endphp
            <option value="{{ $label }}"></option>
          @endforeach
        </datalist>
      </div>
      <div class="col-md-12">
        <label class="form-label">Project Title</label>
        <input type="text" name="project_title" class="form-control" value="{{ old('project_title', $quotation->project_title ?? $project->name ?? '') }}" required>
      </div>
      <div class="col-md-3">
        <label class="form-label">Working Days</label>
        <input type="number" name="working_time_days" class="form-control" value="{{ old('working_time_days', $quotation->working_time_days ?? '') }}">
      </div>
      <div class="col-md-3">
        <label class="form-label">Hours / Day</label>
        <input type="number" name="working_time_hours_per_day" class="form-control" value="{{ old('working_time_hours_per_day', $quotation->working_time_hours_per_day ?? 8) }}" required>
      </div>
      <div class="col-md-3">
        <label class="form-label">Validity (days)</label>
        <input type="number" name="validity_days" class="form-control" value="{{ old('validity_days', $quotation->validity_days ?? 15) }}" required>
      </div>
      <div class="col-md-3">
        <label class="form-label">Tax (PPN)</label>
        <div class="input-group">
          <span class="input-group-text">
            <input type="checkbox" id="tax_enabled" name="tax_enabled" value="1" @checked(old('tax_enabled', $quotation->tax_enabled ?? false))>
          </span>
          <input type="number" step="0.01" id="tax_percent" name="tax_percent" class="form-control text-end" value="{{ old('tax_percent', $quotation->tax_percent ?? 0) }}">
          <span class="input-group-text">%</span>
        </div>
      </div>
    </div>
  </div>
</div>

<div class="card mb-3">
  <div class="card-header d-flex align-items-center">
    <h3 class="card-title">Payment Terms</h3>
    <button type="button" class="btn btn-sm btn-outline-primary ms-auto" id="btn-add-term">
      + Add Term
    </button>
  </div>
  <div class="table-responsive">
    <table class="table table-sm table-vcenter card-table" id="terms-table">
      <thead>
        <tr>
          <th style="width:140px;">Code</th>
          <th>Label</th>
          <th style="width:120px;" class="text-end">Percent</th>
          <th>Trigger Note</th>
          <th style="width:1%"></th>
        </tr>
      </thead>
      <tbody>
        @foreach($paymentTermsData as $i => $term)
          <tr class="term-row" data-term-index="{{ $i }}">
            <td>
              <select name="payment_terms[{{ $i }}][code]" class="form-select">
                @foreach(['DP','T1','T2','T3','T4','T5','FINISH','R1','R2','R3'] as $code)
                  <option value="{{ $code }}" @selected(($term['code'] ?? 'DP') === $code)>{{ $code }}</option>
                @endforeach
              </select>
              <input type="hidden" name="payment_terms[{{ $i }}][sequence]" value="{{ $term['sequence'] ?? ($i + 1) }}">
            </td>
            <td>
              <input type="text" name="payment_terms[{{ $i }}][label]" class="form-control" value="{{ $term['label'] ?? '' }}">
            </td>
            <td>
              <input type="text" name="payment_terms[{{ $i }}][percent]" class="form-control text-end" value="{{ $term['percent'] ?? 0 }}">
            </td>
            <td>
              <input type="text" name="payment_terms[{{ $i }}][trigger_note]" class="form-control" value="{{ $term['trigger_note'] ?? '' }}">
            </td>
            <td>
              <button type="button" class="btn btn-sm btn-outline-danger btn-remove-term">Remove</button>
            </td>
          </tr>
        @endforeach
      </tbody>
    </table>
  </div>
</div>

<div class="card mb-3">
  <div class="card-header d-flex align-items-center">
    <h3 class="card-title">BQ Sections & Lines</h3>
    <button type="button" class="btn btn-sm btn-outline-primary ms-auto" id="btn-add-section">
      + Add Section
    </button>
  </div>
  <div class="card-body" id="bq-sections">
    @foreach($sectionsData as $sIndex => $section)
      <div class="bq-section border rounded p-3 mb-3" data-section-index="{{ $sIndex }}">
        <div class="d-flex align-items-center mb-2 gap-2 flex-wrap">
          <input type="text" name="sections[{{ $sIndex }}][name]" class="form-control me-2 flex-grow-1" value="{{ $section['name'] ?? '' }}" placeholder="Section name" required>
          <input type="hidden" name="sections[{{ $sIndex }}][sort_order]" value="{{ $section['sort_order'] ?? $sIndex }}">
          <div class="d-flex align-items-center gap-2 ms-auto">
            <div>
              <div class="text-muted small">Material</div>
              <input type="text" class="form-control form-control-sm text-end js-section-material" value="0" readonly>
            </div>
            <div>
              <div class="text-muted small">Labor</div>
              <input type="text" class="form-control form-control-sm text-end js-section-labor" value="0" readonly>
            </div>
          </div>
          <button type="button" class="btn btn-sm btn-outline-danger btn-remove-section">Remove</button>
        </div>

        <div class="table-responsive">
          <table class="table table-sm table-vcenter">
            <thead>
              <tr>
                <th style="width:70px;">No</th>
                <th>Description</th>
                <th style="width:90px;" class="text-end">Qty</th>
                <th style="width:90px;">Unit</th>
                <th style="width:120px;" class="text-end">Unit Price</th>
                <th style="width:140px;" class="text-end">Material</th>
                <th style="width:140px;" class="text-end">Labor</th>
                <th style="width:140px;" class="text-end">Line Total</th>
                <th style="width:1%"></th>
              </tr>
            </thead>
            <tbody>
              @foreach($section['lines'] ?? [] as $lIndex => $line)
                <tr class="bq-line" data-line-index="{{ $lIndex }}">
                  <td><input type="text" name="sections[{{ $sIndex }}][lines][{{ $lIndex }}][line_no]" class="form-control" value="{{ $line['line_no'] ?? '' }}"></td>
                  <td>
                    <div class="row g-2 align-items-center mb-1">
                      <div class="col-4">
                        <select name="sections[{{ $sIndex }}][lines][{{ $lIndex }}][source_type]" class="form-select form-select-sm bq-line-source">
                          <option value="item" @selected(($line['source_type'] ?? 'item') === 'item')>Item</option>
                          <option value="project" @selected(($line['source_type'] ?? 'item') === 'project')>Project</option>
                        </select>
                      </div>
                      <div class="col-8">
                        <input type="text"
                               name="sections[{{ $sIndex }}][lines][{{ $lIndex }}][item_label]"
                               class="form-control form-control-sm bq-item-search"
                               placeholder="Cari item..."
                               value="{{ $line['item_label'] ?? '' }}">
                      </div>
                    </div>
                    <textarea name="sections[{{ $sIndex }}][lines][{{ $lIndex }}][description]" class="form-control bq-line-desc" rows="2" required>{{ $line['description'] ?? '' }}</textarea>
                  </td>
                  <td><input type="text" name="sections[{{ $sIndex }}][lines][{{ $lIndex }}][qty]" class="form-control text-end" value="{{ $line['qty'] ?? 0 }}" required></td>
                  <td><input type="text" name="sections[{{ $sIndex }}][lines][{{ $lIndex }}][unit]" class="form-control" value="{{ $line['unit'] ?? 'LS' }}" required></td>
                  <td><input type="text" name="sections[{{ $sIndex }}][lines][{{ $lIndex }}][unit_price]" class="form-control text-end" value="{{ $line['unit_price'] ?? 0 }}"></td>
                  <td><input type="text" name="sections[{{ $sIndex }}][lines][{{ $lIndex }}][material_total]" class="form-control text-end js-line-material" value="{{ $line['material_total'] ?? 0 }}" required></td>
                  <td><input type="text" name="sections[{{ $sIndex }}][lines][{{ $lIndex }}][labor_total]" class="form-control text-end js-line-labor" value="{{ $line['labor_total'] ?? 0 }}" required></td>
                  <td class="text-end"><span class="js-line-total">0</span></td>
                  <td><button type="button" class="btn btn-sm btn-outline-danger btn-remove-line">Remove</button></td>
                </tr>
              @endforeach
            </tbody>
          </table>
        </div>
        <button type="button" class="btn btn-sm btn-outline-primary btn-add-line">+ Add Line</button>
      </div>
    @endforeach
  </div>
</div>

<div class="card mb-3">
  <div class="card-header">
    <h3 class="card-title">Notes & Signatory</h3>
  </div>
  <div class="card-body">
    <div class="row g-3">
      <div class="col-md-12">
        <label class="form-label">Notes</label>
        <textarea name="notes" class="form-control" rows="5">{{ old('notes', $quotation->notes ?? '') }}</textarea>
      </div>
      <div class="col-md-6">
        <label class="form-label">Signatory Name</label>
        <input type="text" name="signatory_name" class="form-control" value="{{ old('signatory_name', $quotation->signatory_name ?? auth()->user()?->name) }}">
      </div>
      <div class="col-md-6">
        <label class="form-label">Signatory Title</label>
        <input type="text" name="signatory_title" class="form-control" value="{{ old('signatory_title', $quotation->signatory_title ?? '') }}">
      </div>
    </div>
  </div>
</div>

<div class="card">
  <div class="card-header">
    <h3 class="card-title">Totals Preview</h3>
  </div>
  <div class="card-body">
    <div class="row g-3">
      <div class="col-md-3">
        <div class="text-muted">Material</div>
        <div class="fw-semibold" id="bq-subtotal-material">Rp 0,00</div>
      </div>
      <div class="col-md-3">
        <div class="text-muted">Labor</div>
        <div class="fw-semibold" id="bq-subtotal-labor">Rp 0,00</div>
      </div>
      <div class="col-md-3">
        <div class="text-muted">Subtotal</div>
        <div class="fw-semibold" id="bq-subtotal">Rp 0,00</div>
      </div>
      <div class="col-md-3">
        <div class="text-muted">Tax</div>
        <div class="fw-semibold" id="bq-tax-amount">Rp 0,00</div>
      </div>
      <div class="col-md-12 text-end">
        <div class="text-muted">Grand Total</div>
        <div class="h2 m-0" id="bq-grand-total">Rp 0,00</div>
      </div>
    </div>
  </div>
</div>

@push('scripts')
<script>
(() => {
  const sectionsEl = document.getElementById('bq-sections');
  if (!sectionsEl) return;

  const ITEM_SEARCH_URL = @json(route('items.search', [], false));

  const termTable = document.getElementById('terms-table');
  const btnAddTerm = document.getElementById('btn-add-term');
  const btnAddSection = document.getElementById('btn-add-section');

  const parseNumber = (val) => {
    if (val === null || val === undefined) return 0;
    const s = String(val).replace(/[^0-9,.-]/g, '').replace(/\./g, '').replace(',', '.');
    const n = parseFloat(s);
    return Number.isNaN(n) ? 0 : n;
  };

  const formatNumber = (val) => {
    return Number(val || 0).toLocaleString('id-ID', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
  };

  const renumberLines = (section) => {
    const rows = section.querySelectorAll('.bq-line');
    rows.forEach((row, idx) => {
      const input = row.querySelector('input[name$="[line_no]"]');
      if (input) input.value = String(idx + 1);
    });
  };

  const recalcTotals = () => {
    let subMat = 0;
    let subLab = 0;
    const sectionTotals = new Map();

    document.querySelectorAll('.bq-line').forEach((row) => {
      const mat = parseNumber(row.querySelector('.js-line-material')?.value);
      const lab = parseNumber(row.querySelector('.js-line-labor')?.value);
      const total = mat + lab;
      subMat += mat;
      subLab += lab;
      const totalEl = row.querySelector('.js-line-total');
      if (totalEl) totalEl.textContent = formatNumber(total);

      const section = row.closest('.bq-section');
      if (section) {
        const current = sectionTotals.get(section) || { mat: 0, lab: 0 };
        current.mat += mat;
        current.lab += lab;
        sectionTotals.set(section, current);
      }
    });

    document.querySelectorAll('.bq-section').forEach((section) => {
      const sums = sectionTotals.get(section) || { mat: 0, lab: 0 };
      const matEl = section.querySelector('.js-section-material');
      const labEl = section.querySelector('.js-section-labor');
      if (matEl) matEl.value = formatNumber(sums.mat);
      if (labEl) labEl.value = formatNumber(sums.lab);
      renumberLines(section);
    });

    const subtotal = subMat + subLab;
    const taxEnabled = document.getElementById('tax_enabled');
    const taxPercent = document.getElementById('tax_percent');
    const taxPct = taxEnabled?.checked ? parseNumber(taxPercent?.value) : 0;
    const taxAmt = subtotal * (taxPct / 100);
    const grand = subtotal + taxAmt;

    const setText = (id, val) => {
      const el = document.getElementById(id);
      if (el) el.textContent = `Rp ${formatNumber(val)}`;
    };

    setText('bq-subtotal-material', subMat);
    setText('bq-subtotal-labor', subLab);
    setText('bq-subtotal', subtotal);
    setText('bq-tax-amount', taxAmt);
    setText('bq-grand-total', grand);
  };

  const syncCompanyTax = () => {
    const companySel = document.querySelector('select[name="company_id"]');
    const taxEnabled = document.getElementById('tax_enabled');
    const taxPercent = document.getElementById('tax_percent');
    if (!companySel || !taxEnabled || !taxPercent) return;

    const opt = companySel.selectedOptions[0];
    if (!opt) return;

    const taxable = opt.getAttribute('data-taxable') === '1';
    const defTax = opt.getAttribute('data-tax') || '0';

    taxEnabled.checked = taxable;
    taxPercent.value = taxable ? defTax : '0';
    taxPercent.readOnly = !taxable;
  };

  const nextSectionIndex = () => {
    const indices = [...sectionsEl.querySelectorAll('.bq-section')].map((el) => parseInt(el.dataset.sectionIndex || '0', 10));
    return indices.length ? Math.max(...indices) + 1 : 0;
  };

  const nextLineIndex = (section) => {
    const indices = [...section.querySelectorAll('.bq-line')].map((el) => parseInt(el.dataset.lineIndex || '0', 10));
    return indices.length ? Math.max(...indices) + 1 : 0;
  };

  const makeLine = (sIndex, lIndex) => {
    return `
      <tr class="bq-line" data-line-index="${lIndex}">
        <td><input type="text" name="sections[${sIndex}][lines][${lIndex}][line_no]" class="form-control"></td>
        <td>
          <div class="row g-2 align-items-center mb-1">
            <div class="col-4">
              <select name="sections[${sIndex}][lines][${lIndex}][source_type]" class="form-select form-select-sm bq-line-source">
                <option value="item">Item</option>
                <option value="project">Project</option>
              </select>
            </div>
            <div class="col-8">
              <input type="text"
                     name="sections[${sIndex}][lines][${lIndex}][item_label]"
                     class="form-control form-control-sm bq-item-search"
                     placeholder="Cari item...">
            </div>
          </div>
          <textarea name="sections[${sIndex}][lines][${lIndex}][description]" class="form-control bq-line-desc" rows="2" required></textarea>
        </td>
        <td><input type="text" name="sections[${sIndex}][lines][${lIndex}][qty]" class="form-control text-end" value="1" required></td>
        <td><input type="text" name="sections[${sIndex}][lines][${lIndex}][unit]" class="form-control" value="LS" required></td>
        <td><input type="text" name="sections[${sIndex}][lines][${lIndex}][unit_price]" class="form-control text-end" value="0"></td>
        <td><input type="text" name="sections[${sIndex}][lines][${lIndex}][material_total]" class="form-control text-end js-line-material" value="0" required></td>
        <td><input type="text" name="sections[${sIndex}][lines][${lIndex}][labor_total]" class="form-control text-end js-line-labor" value="0" required></td>
        <td class="text-end"><span class="js-line-total">0</span></td>
        <td><button type="button" class="btn btn-sm btn-outline-danger btn-remove-line">Remove</button></td>
      </tr>
    `;
  };

  const makeSection = (sIndex) => {
    return `
      <div class="bq-section border rounded p-3 mb-3" data-section-index="${sIndex}">
        <div class="d-flex align-items-center mb-2 gap-2 flex-wrap">
          <input type="text" name="sections[${sIndex}][name]" class="form-control me-2 flex-grow-1" placeholder="Section name" required>
          <input type="hidden" name="sections[${sIndex}][sort_order]" value="${sIndex}">
          <div class="d-flex align-items-center gap-2 ms-auto">
            <div>
              <div class="text-muted small">Material</div>
              <input type="text" class="form-control form-control-sm text-end js-section-material" value="0" readonly>
            </div>
            <div>
              <div class="text-muted small">Labor</div>
              <input type="text" class="form-control form-control-sm text-end js-section-labor" value="0" readonly>
            </div>
          </div>
          <button type="button" class="btn btn-sm btn-outline-danger btn-remove-section">Remove</button>
        </div>
        <div class="table-responsive">
          <table class="table table-sm table-vcenter">
            <thead>
              <tr>
                <th style="width:70px;">No</th>
                <th>Description</th>
                <th style="width:90px;" class="text-end">Qty</th>
                <th style="width:90px;">Unit</th>
                <th style="width:120px;" class="text-end">Unit Price</th>
                <th style="width:140px;" class="text-end">Material</th>
                <th style="width:140px;" class="text-end">Labor</th>
                <th style="width:140px;" class="text-end">Line Total</th>
                <th style="width:1%"></th>
              </tr>
            </thead>
            <tbody>
              ${makeLine(sIndex, 0)}
            </tbody>
          </table>
        </div>
        <button type="button" class="btn btn-sm btn-outline-primary btn-add-line">+ Add Line</button>
      </div>
    `;
  };

  const makeTerm = (idx) => {
    return `
      <tr class="term-row" data-term-index="${idx}">
        <td>
          <select name="payment_terms[${idx}][code]" class="form-select">
            ${['DP','T1','T2','T3','T4','T5','FINISH','R1','R2','R3'].map(code => `<option value="${code}">${code}</option>`).join('')}
          </select>
          <input type="hidden" name="payment_terms[${idx}][sequence]" value="${idx + 1}">
        </td>
        <td><input type="text" name="payment_terms[${idx}][label]" class="form-control"></td>
        <td><input type="text" name="payment_terms[${idx}][percent]" class="form-control text-end" value="0"></td>
        <td><input type="text" name="payment_terms[${idx}][trigger_note]" class="form-control"></td>
        <td><button type="button" class="btn btn-sm btn-outline-danger btn-remove-term">Remove</button></td>
      </tr>
    `;
  };

  if (btnAddSection) {
    btnAddSection.addEventListener('click', () => {
      const idx = nextSectionIndex();
      sectionsEl.insertAdjacentHTML('beforeend', makeSection(idx));
      initItemPickers(sectionsEl.lastElementChild);
      recalcTotals();
    });
  }

  if (btnAddTerm && termTable) {
    btnAddTerm.addEventListener('click', () => {
      const body = termTable.querySelector('tbody');
      const idx = body.querySelectorAll('.term-row').length;
      body.insertAdjacentHTML('beforeend', makeTerm(idx));
    });
  }

  sectionsEl.addEventListener('click', (e) => {
    if (e.target.classList.contains('btn-add-line')) {
      const section = e.target.closest('.bq-section');
      const sIndex = parseInt(section.dataset.sectionIndex || '0', 10);
      const body = section.querySelector('tbody');
      const lIndex = nextLineIndex(section);
      body.insertAdjacentHTML('beforeend', makeLine(sIndex, lIndex));
      initItemPickers(body.lastElementChild);
      recalcTotals();
    }

    if (e.target.classList.contains('btn-remove-line')) {
      const row = e.target.closest('.bq-line');
      row?.remove();
      recalcTotals();
    }

    if (e.target.classList.contains('btn-remove-section')) {
      e.target.closest('.bq-section')?.remove();
      recalcTotals();
    }
  });

  if (termTable) {
    termTable.addEventListener('click', (e) => {
      if (e.target.classList.contains('btn-remove-term')) {
        e.target.closest('.term-row')?.remove();
      }
    });
  }

  document.addEventListener('input', (e) => {
    if (
      e.target.classList.contains('js-line-material') ||
      e.target.classList.contains('js-line-labor') ||
      e.target.id === 'tax_percent'
    ) {
      recalcTotals();
    }
  });

  const taxEnabled = document.getElementById('tax_enabled');
  if (taxEnabled) {
    taxEnabled.addEventListener('change', recalcTotals);
  }

  const buildSearchUrl = (sourceType, query) => {
    const params = new URLSearchParams();
    params.set('q', query || '');
    if (sourceType === 'project') {
      params.set('item_type', 'project');
    }
    return `${ITEM_SEARCH_URL}?${params.toString()}`;
  };

  const initItemPicker = (input, sourceType) => {
    if (!input || !window.TomSelect) return;
    const nextType = sourceType || 'item';
    const currentType = input.dataset.sourceType || '';
    if (input._ts && currentType === nextType) return;

    if (input._ts) {
      input._ts.destroy();
      input._ts = null;
    }

    input.dataset.sourceType = nextType;

    const ts = new TomSelect(input, {
      valueField: 'uid',
      labelField: 'name',
      searchField: ['name','sku','label'],
      maxOptions: 200,
      create: false,
      persist: false,
      dropdownParent: 'body',
      preload: 'focus',
      closeAfterSelect: true,
      load(query, cb){
        const url = buildSearchUrl(input.dataset.sourceType || 'item', query);
        fetch(url, {
          credentials: 'same-origin',
          headers: {
            'X-Requested-With': 'XMLHttpRequest',
            'Accept': 'application/json',
          },
          cache: 'no-store',
        })
          .then(r => r.ok ? r.text() : '[]')
          .then(t => {
            const s = t.replace(/^\uFEFF/, '').trimStart();
            let data = [];
            try { data = JSON.parse(s); } catch (e) { cb([]); return; }
            cb(Array.isArray(data) ? data : []);
          })
          .catch(() => cb([]));
      },
      render: {
        option(d, esc) { return `<div>${esc(d.name || '')}</div>`; }
      },
      onChange(val){
        const data = this.options[val];
        if (!data) return;
        const row = input.closest('.bq-line');
        if (!row) return;

        const descEl = row.querySelector('.bq-line-desc');
        const qtyEl = row.querySelector('input[name$="[qty]"]');
        const unitEl = row.querySelector('input[name$="[unit]"]');
        const unitPriceEl = row.querySelector('input[name$="[unit_price]"]');
        const materialEl = row.querySelector('.js-line-material');
        const laborEl = row.querySelector('.js-line-labor');

        if (descEl && !descEl.value) descEl.value = data.name || '';
        if (unitEl) unitEl.value = (data.unit_code || 'LS').toString().toLowerCase();
        if (unitPriceEl) unitPriceEl.value = data.price != null ? data.price : 0;
        if (laborEl && !laborEl.value) laborEl.value = 0;

        const qty = parseNumber(qtyEl?.value);
        const price = parseNumber(data.price);
        if (materialEl) materialEl.value = formatNumber(qty * price);
        recalcTotals();

        if (input._ts) input._ts.close();
        qtyEl?.focus();
      }
    });
    input._ts = ts;
    ts.on('focus', () => {
      ts.load('');
      ts.open();
    });
  };

  const syncSourceRow = (row) => {
    const sourceSel = row.querySelector('.bq-line-source');
    const searchInput = row.querySelector('.bq-item-search');
    if (!sourceSel || !searchInput) return;

    const sourceType = sourceSel.value === 'project' ? 'project' : 'item';
    if (searchInput.dataset.sourceType && searchInput.dataset.sourceType !== sourceType) {
      searchInput.value = '';
    }
    searchInput.disabled = false;
    searchInput.placeholder = sourceType === 'item' ? 'Cari item...' : 'Cari project item...';
    initItemPicker(searchInput, sourceType);
  };

  const initItemPickers = (scope) => {
    const root = scope || sectionsEl;
    root.querySelectorAll('.bq-line').forEach((row) => syncSourceRow(row));
  };

  sectionsEl.addEventListener('change', (e) => {
    if (e.target.classList.contains('bq-line-source')) {
      const row = e.target.closest('.bq-line');
      if (row) syncSourceRow(row);
    }
  });

  syncCompanyTax();
  document.querySelector('select[name="company_id"]')?.addEventListener('change', () => {
    syncCompanyTax();
    recalcTotals();
  });

  initItemPickers();
  recalcTotals();
})();
</script>
@endpush
