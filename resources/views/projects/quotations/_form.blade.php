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
          'item_id' => data_get($line, 'item_id'),
          'item_label' => data_get($line, 'item_label', ''),
          'line_type' => data_get($line, 'line_type', 'product'),
          'source_template_id' => data_get($line, 'source_template_id'),
          'source_template_line_id' => data_get($line, 'source_template_line_id'),
          'percent_value' => data_get($line, 'percent_value', 0),
          'basis_type' => data_get($line, 'basis_type', 'bq_product_total'),
          'computed_amount' => data_get($line, 'computed_amount', data_get($line, 'material_total', 0)),
          'editable_price' => data_get($line, 'editable_price', true),
          'editable_percent' => data_get($line, 'editable_percent', true),
          'can_remove' => data_get($line, 'can_remove', true),
          'labor_source' => data_get($line, 'labor_source', 'manual'),
          'labor_unit_cost_snapshot' => data_get($line, 'labor_unit_cost_snapshot', 0),
          'labor_override_reason' => data_get($line, 'labor_override_reason', ''),
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

@php $hasBqTemplates = !empty($bqTemplatesData ?? []); @endphp
<div class="card mb-3">
  <div class="card-header d-flex align-items-center">
    <h3 class="card-title">BQ Sections & Lines</h3>
    <div class="ms-auto btn-list">
      <button type="button" class="btn btn-sm btn-outline-secondary" id="btn-apply-template" @disabled(!$hasBqTemplates)>
        Apply Template
      </button>
      <button type="button" class="btn btn-sm btn-outline-primary" id="btn-add-section">
        + Add Section
      </button>
    </div>
  </div>
  <div class="card-body" id="bq-sections">
    @foreach($sectionsData as $sIndex => $section)
      <div class="bq-section border rounded p-3 mb-3" data-section-index="{{ $sIndex }}">
        <div class="d-flex align-items-center mb-2 gap-2 flex-wrap">
          <input type="text" name="sections[{{ $sIndex }}][name]" class="form-control me-2 section-name" value="{{ $section['name'] ?? '' }}" placeholder="Section name" required>
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
                <th style="width:110px;">Type</th>
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
                @php
                  $laborSource = $line['labor_source'] ?? 'manual';
                  $laborBadge = $laborSource === 'master_item' ? ['I','bg-azure-lt'] : ($laborSource === 'master_project' ? ['P','bg-indigo-lt'] : ['M','bg-secondary-lt']);
                  $lineType = $line['line_type'] ?? 'product';
                  $canRemove = (bool) ($line['can_remove'] ?? true);
                  $editablePrice = (bool) ($line['editable_price'] ?? true);
                  $editablePercent = (bool) ($line['editable_percent'] ?? true);
                @endphp
                <tr class="bq-line" data-line-index="{{ $lIndex }}">
                  <td><input type="text" name="sections[{{ $sIndex }}][lines][{{ $lIndex }}][line_no]" class="form-control" value="{{ $line['line_no'] ?? '' }}"></td>
                  <td>
                    <select name="sections[{{ $sIndex }}][lines][{{ $lIndex }}][line_type]" class="form-select form-select-sm bq-line-type">
                      <option value="product" @selected($lineType === 'product')>Product</option>
                      <option value="charge" @selected($lineType === 'charge')>Charge</option>
                      <option value="percent" @selected($lineType === 'percent')>Percent</option>
                    </select>
                    <input type="hidden" name="sections[{{ $sIndex }}][lines][{{ $lIndex }}][source_template_id]" class="bq-line-template-id" value="{{ $line['source_template_id'] ?? '' }}">
                    <input type="hidden" name="sections[{{ $sIndex }}][lines][{{ $lIndex }}][source_template_line_id]" class="bq-line-template-line-id" value="{{ $line['source_template_line_id'] ?? '' }}">
                    <input type="hidden" name="sections[{{ $sIndex }}][lines][{{ $lIndex }}][editable_price]" class="bq-line-editable-price" value="{{ $editablePrice ? 1 : 0 }}">
                    <input type="hidden" name="sections[{{ $sIndex }}][lines][{{ $lIndex }}][editable_percent]" class="bq-line-editable-percent" value="{{ $editablePercent ? 1 : 0 }}">
                    <input type="hidden" name="sections[{{ $sIndex }}][lines][{{ $lIndex }}][can_remove]" class="bq-line-can-remove" value="{{ $canRemove ? 1 : 0 }}">
                  </td>
                  <td>
                    <div class="bq-item-controls">
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
                          <input type="hidden" name="sections[{{ $sIndex }}][lines][{{ $lIndex }}][item_id]" class="bq-line-item-id" value="{{ $line['item_id'] ?? '' }}">
                          <input type="hidden" name="sections[{{ $sIndex }}][lines][{{ $lIndex }}][labor_source]" class="bq-line-labor-source" value="{{ $line['labor_source'] ?? 'manual' }}">
                          <input type="hidden" name="sections[{{ $sIndex }}][lines][{{ $lIndex }}][labor_unit_cost_snapshot]" class="bq-line-labor-unit" value="{{ $line['labor_unit_cost_snapshot'] ?? 0 }}">
                          <input type="hidden" name="sections[{{ $sIndex }}][lines][{{ $lIndex }}][labor_override_reason]" class="bq-line-labor-reason" value="{{ $line['labor_override_reason'] ?? '' }}">
                        </div>
                      </div>
                    </div>
                    <textarea name="sections[{{ $sIndex }}][lines][{{ $lIndex }}][description]" class="form-control bq-line-desc" rows="2" required>{{ $line['description'] ?? '' }}</textarea>
                  </td>
                  <td><input type="text" name="sections[{{ $sIndex }}][lines][{{ $lIndex }}][qty]" class="form-control text-end" value="{{ $line['qty'] ?? 0 }}" required></td>
                  <td><input type="text" name="sections[{{ $sIndex }}][lines][{{ $lIndex }}][unit]" class="form-control" value="{{ $line['unit'] ?? 'LS' }}" required></td>
                  <td>
                    <div class="bq-price-wrap">
                      <input type="text" name="sections[{{ $sIndex }}][lines][{{ $lIndex }}][unit_price]" class="form-control text-end js-line-unit-price" value="{{ $line['unit_price'] ?? 0 }}">
                    </div>
                    <div class="bq-percent-wrap d-none">
                      <div class="input-group input-group-sm">
                        <input type="text" name="sections[{{ $sIndex }}][lines][{{ $lIndex }}][percent_value]" class="form-control text-end js-line-percent" value="{{ $line['percent_value'] ?? 0 }}">
                        <span class="input-group-text">%</span>
                      </div>
                      <input type="hidden" name="sections[{{ $sIndex }}][lines][{{ $lIndex }}][basis_type]" class="bq-line-basis" value="{{ $line['basis_type'] ?? 'bq_product_total' }}">
                      <input type="hidden" name="sections[{{ $sIndex }}][lines][{{ $lIndex }}][computed_amount]" class="bq-line-computed" value="{{ $line['computed_amount'] ?? 0 }}">
                    </div>
                  </td>
                  <td><input type="text" name="sections[{{ $sIndex }}][lines][{{ $lIndex }}][material_total]" class="form-control text-end js-line-material" value="{{ $line['material_total'] ?? 0 }}" required></td>
                  <td>
                    <div class="d-flex align-items-center gap-2">
                      <input type="text" name="sections[{{ $sIndex }}][lines][{{ $lIndex }}][labor_total]" class="form-control text-end js-line-labor" value="{{ $line['labor_total'] ?? 0 }}" required>
                      <span class="badge {{ $laborBadge[1] }} text-dark js-labor-badge" title="Labor Source">{{ $laborBadge[0] }}</span>
                      <button type="button" class="btn btn-sm btn-outline-secondary js-update-labor-master d-none">Update</button>
                    </div>
                  </td>
                  <td class="text-end"><span class="js-line-total">0</span></td>
                  <td><button type="button" class="btn btn-sm btn-outline-danger btn-remove-line" @disabled(!$canRemove)>Remove</button></td>
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

<div class="modal fade" id="bqTemplateModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Apply BQ Line Template</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <div class="mb-3">
          <label class="form-label">Template</label>
          <select id="bq-template-select" class="form-select">
            <option value="">Pilih template</option>
            @foreach(($bqTemplatesData ?? []) as $tpl)
              <option value="{{ $tpl['id'] }}">{{ $tpl['name'] }}</option>
            @endforeach
          </select>
          <div class="form-hint">Template akan menambahkan baris ke section Add-ons.</div>
        </div>
        <div class="text-muted small" id="bq-template-desc"></div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn me-auto" data-bs-dismiss="modal">Cancel</button>
        <button type="button" class="btn btn-primary" id="btn-confirm-apply-template">Apply</button>
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
  const LABOR_RATE_URL = @json(route('labor-rates.show', [], false));
  const LABOR_UPDATE_URL = @json(route('labor-rates.update', [], false));
  const CAN_UPDATE_ITEM_LABOR = @json(auth()->user()?->hasAnyRole(['Admin','SuperAdmin','Finance']) ?? false);
  const CAN_UPDATE_PROJECT_LABOR = @json(auth()->user()?->hasAnyRole(['Admin','SuperAdmin','PM']) ?? false);
  const BQ_TEMPLATES = @json($bqTemplatesData ?? []);

  const termTable = document.getElementById('terms-table');
  const btnAddTerm = document.getElementById('btn-add-term');
  const btnAddSection = document.getElementById('btn-add-section');
  const btnApplyTemplate = document.getElementById('btn-apply-template');
  const templateSelect = document.getElementById('bq-template-select');
  const templateDesc = document.getElementById('bq-template-desc');
  const btnConfirmApplyTemplate = document.getElementById('btn-confirm-apply-template');

  const parseNumber = (val) => {
    if (val === null || val === undefined) return 0;
    const s = String(val).replace(/[^0-9,.-]/g, '').replace(/\./g, '').replace(',', '.');
    const n = parseFloat(s);
    return Number.isNaN(n) ? 0 : n;
  };

  const formatNumber = (val) => {
    return Number(val || 0).toLocaleString('id-ID', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
  };

  const formatPercent = (val) => {
    return Number(val || 0).toLocaleString('id-ID', { minimumFractionDigits: 4, maximumFractionDigits: 4 });
  };

  const roundMoney = (val) => {
    return Math.round((Number(val || 0) + Number.EPSILON) * 100) / 100;
  };

  const escapeHtml = (val) => {
    return String(val ?? '')
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/\"/g, '&quot;')
      .replace(/'/g, '&#39;');
  };

  const getLineType = (row) => {
    const typeSel = row.querySelector('.bq-line-type');
    return typeSel?.value || 'product';
  };

  const getSourceType = (row) => {
    const sourceSel = row.querySelector('.bq-line-source');
    return sourceSel?.value === 'project' ? 'project' : 'item';
  };

  const getLaborSource = (row) => {
    return row.querySelector('.bq-line-labor-source')?.value || 'manual';
  };

  const setLaborBadge = (row, source, opts = {}) => {
    const badge = row.querySelector('.js-labor-badge');
    if (!badge) return;

    if (opts.missing) {
      badge.textContent = 'Labor Missing';
      badge.className = 'badge bg-yellow-lt text-dark js-labor-badge';
      return;
    }

    const map = {
      master_item: { text: 'I', cls: 'bg-azure-lt' },
      master_project: { text: 'P', cls: 'bg-indigo-lt' },
      manual: { text: 'M', cls: 'bg-secondary-lt' },
    };
    const cfg = map[source] || map.manual;
    badge.textContent = cfg.text;
    badge.className = `badge ${cfg.cls} text-dark js-labor-badge`;
  };

  const setLaborSource = (row, source, opts = {}) => {
    const sourceEl = row.querySelector('.bq-line-labor-source');
    if (sourceEl) sourceEl.value = source;
    setLaborBadge(row, source, opts);
  };

  const setReadOnly = (input, value) => {
    if (!input) return;
    input.readOnly = value;
    input.classList.toggle('bg-light', value);
  };

  const togglePercentFields = (row, isPercent) => {
    row.querySelector('.bq-percent-wrap')?.classList.toggle('d-none', !isPercent);
    row.querySelector('.bq-price-wrap')?.classList.toggle('d-none', isPercent);
  };

  const applyEditableFlags = (row) => {
    const lineType = getLineType(row);
    const editablePrice = row.querySelector('.bq-line-editable-price')?.value === '1';
    const editablePercent = row.querySelector('.bq-line-editable-percent')?.value === '1';
    const unitPriceEl = row.querySelector('.js-line-unit-price');
    const materialEl = row.querySelector('.js-line-material');
    const percentEl = row.querySelector('.js-line-percent');

    if (lineType === 'charge') {
      setReadOnly(unitPriceEl, !editablePrice);
      setReadOnly(materialEl, !editablePrice);
    } else {
      setReadOnly(unitPriceEl, false);
      setReadOnly(materialEl, lineType === 'percent');
    }

    if (lineType === 'percent') {
      setReadOnly(percentEl, !editablePercent);
    } else if (percentEl) {
      setReadOnly(percentEl, false);
    }
  };

  const syncLineTypeRow = (row) => {
    const lineType = getLineType(row);
    const itemControls = row.querySelector('.bq-item-controls');
    const sourceSel = row.querySelector('.bq-line-source');
    const searchInput = row.querySelector('.bq-item-search');
    const itemIdEl = row.querySelector('.bq-line-item-id');
    const qtyEl = row.querySelector('input[name$="[qty]"]');
    const unitEl = row.querySelector('input[name$="[unit]"]');
    const laborEl = row.querySelector('.js-line-labor');
    const materialEl = row.querySelector('.js-line-material');

    const isPercent = lineType === 'percent';
    togglePercentFields(row, isPercent);

    if (lineType === 'product') {
      itemControls?.classList.remove('d-none');
      if (sourceSel) sourceSel.disabled = false;
      if (searchInput) searchInput.disabled = false;
      updateMasterButtonVisibility(row);
      syncSourceRow(row);
    } else {
      itemControls?.classList.add('d-none');
      if (sourceSel) sourceSel.disabled = true;
      if (searchInput) {
        if (searchInput._ts) {
          searchInput._ts.destroy();
          searchInput._ts = null;
        }
        searchInput.value = '';
        searchInput.disabled = true;
      }
      if (itemIdEl) itemIdEl.value = '';
      setLaborSource(row, 'manual');
      updateMasterButtonVisibility(row);
    }

    if (lineType === 'percent') {
      if (qtyEl) qtyEl.value = '1';
      if (unitEl) unitEl.value = '%';
      if (laborEl) laborEl.value = formatNumber(0);
      if (materialEl) setReadOnly(materialEl, true);
      if (laborEl) setReadOnly(laborEl, true);
      if (qtyEl) setReadOnly(qtyEl, true);
      if (unitEl) setReadOnly(unitEl, true);
    } else if (lineType === 'charge') {
      if (laborEl) laborEl.value = formatNumber(0);
      if (laborEl) setReadOnly(laborEl, true);
      if (materialEl) setReadOnly(materialEl, false);
      if (qtyEl) setReadOnly(qtyEl, false);
      if (unitEl) setReadOnly(unitEl, false);
    } else {
      if (laborEl) setReadOnly(laborEl, false);
      if (materialEl) setReadOnly(materialEl, false);
      if (qtyEl) setReadOnly(qtyEl, false);
      if (unitEl) setReadOnly(unitEl, false);
    }

    applyEditableFlags(row);
  };

  const updateMasterButtonVisibility = (row) => {
    const btn = row.querySelector('.js-update-labor-master');
    if (!btn) return;
    if (getLineType(row) !== 'product') {
      btn.classList.add('d-none');
      return;
    }
    const sourceType = getSourceType(row);
    const allowed = sourceType === 'project' ? CAN_UPDATE_PROJECT_LABOR : CAN_UPDATE_ITEM_LABOR;
    btn.classList.toggle('d-none', !allowed);
  };

  const syncLaborBadgeFromRow = (row) => {
    const reason = row.querySelector('.bq-line-labor-reason')?.value || '';
    const source = getLaborSource(row);
    if (reason === 'Labor Missing') {
      setLaborBadge(row, source, { missing: true });
    } else {
      setLaborBadge(row, source);
    }
    updateMasterButtonVisibility(row);
  };

  const updateLaborSnapshot = (row, laborTotal) => {
    const qty = parseNumber(row.querySelector('input[name$="[qty]"]')?.value);
    const unitCost = qty > 0 ? (laborTotal / qty) : laborTotal;
    const unitEl = row.querySelector('.bq-line-labor-unit');
    if (unitEl) unitEl.value = unitCost.toFixed(2);
  };

  const fetchLaborRate = (sourceType, itemId) => {
    const params = new URLSearchParams();
    params.set('source', sourceType);
    params.set('item_id', itemId);
    return fetch(`${LABOR_RATE_URL}?${params.toString()}`, {
      credentials: 'same-origin',
      headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
      cache: 'no-store',
    })
      .then(r => r.ok ? r.json() : null)
      .catch(() => null);
  };

  const applyLaborRate = (row, sourceType, rateData) => {
    const laborInput = row.querySelector('.js-line-labor');
    const reasonEl = row.querySelector('.bq-line-labor-reason');
    const unitCost = parseNumber(rateData?.unit_cost);
    const hasMaster = rateData?.unit_cost != null;
    const qty = parseNumber(row.querySelector('input[name$="[qty]"]')?.value);

    if (!laborInput) return;

    if (!hasMaster) {
      laborInput.value = formatNumber(0);
      updateLaborSnapshot(row, 0);
      setLaborSource(row, 'manual', { missing: true });
      if (reasonEl && !reasonEl.value) reasonEl.value = 'Labor Missing';
      row.dataset.laborMasterRate = '';
      recalcTotals();
      return;
    }

    const laborTotal = qty * unitCost;
    laborInput.value = formatNumber(laborTotal);
    updateLaborSnapshot(row, laborTotal);
    setLaborSource(row, sourceType === 'project' ? 'master_project' : 'master_item');
    if (reasonEl && reasonEl.value === 'Labor Missing') reasonEl.value = '';
    row.dataset.laborMasterRate = String(unitCost);
    recalcTotals();
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
    let chargeTotal = 0;
    let percentTotal = 0;
    const sectionTotals = new Map();
    const sectionProductTotals = new Map();

    document.querySelectorAll('.bq-line').forEach((row) => {
      const lineType = getLineType(row);
      if (lineType !== 'product') return;
      const mat = parseNumber(row.querySelector('.js-line-material')?.value);
      const lab = parseNumber(row.querySelector('.js-line-labor')?.value);
      const total = mat + lab;
      subMat += mat;
      subLab += lab;

      const section = row.closest('.bq-section');
      if (section) {
        const current = sectionTotals.get(section) || { mat: 0, lab: 0 };
        current.mat += mat;
        current.lab += lab;
        sectionTotals.set(section, current);

        const currentTotal = sectionProductTotals.get(section) || 0;
        sectionProductTotals.set(section, currentTotal + total);
      }
    });

    const productSubtotal = subMat + subLab;

    document.querySelectorAll('.bq-line').forEach((row) => {
      const lineType = getLineType(row);
      const matEl = row.querySelector('.js-line-material');
      const labEl = row.querySelector('.js-line-labor');
      const totalEl = row.querySelector('.js-line-total');

      let mat = parseNumber(matEl?.value);
      let lab = parseNumber(labEl?.value);
      let total = 0;

      if (lineType === 'percent') {
        const percentEl = row.querySelector('.js-line-percent');
        const basisType = row.querySelector('.bq-line-basis')?.value || 'bq_product_total';
        const pct = parseNumber(percentEl?.value);
        const section = row.closest('.bq-section');
        let basis = basisType === 'section_product_total'
          ? (section ? (sectionProductTotals.get(section) || 0) : 0)
          : productSubtotal;
        if (basis <= 0 && basisType === 'section_product_total' && productSubtotal > 0) {
          basis = productSubtotal;
        }
        const computed = roundMoney(basis * (pct / 100));
        total = computed;
        percentTotal += computed;
        if (matEl) matEl.value = formatNumber(computed);
        if (labEl) labEl.value = formatNumber(0);
        const computedEl = row.querySelector('.bq-line-computed');
        if (computedEl) computedEl.value = computed.toFixed(2);
      } else if (lineType === 'charge') {
        total = mat + lab;
        chargeTotal += total;
      } else {
        total = mat + lab;
      }

      if (totalEl) totalEl.textContent = formatNumber(total);
    });

    document.querySelectorAll('.bq-section').forEach((section) => {
      const sums = sectionTotals.get(section) || { mat: 0, lab: 0 };
      const matEl = section.querySelector('.js-section-material');
      const labEl = section.querySelector('.js-section-labor');
      if (matEl) matEl.value = formatNumber(sums.mat);
      if (labEl) labEl.value = formatNumber(sums.lab);
      renumberLines(section);
    });

    const subtotal = productSubtotal + chargeTotal + percentTotal;
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

  const makeLine = (sIndex, lIndex, data = {}) => {
    const lineNo = escapeHtml(data.line_no || '');
    const description = escapeHtml(data.description || '');
    const sourceType = data.source_type === 'project' ? 'project' : 'item';
    const itemLabel = escapeHtml(data.item_label || '');
    const itemId = escapeHtml(data.item_id || '');
    const qty = data.qty ?? 1;
    const unit = escapeHtml(data.unit || 'LS');
    const unitPrice = data.unit_price ?? 0;
    const materialTotal = data.material_total ?? 0;
    const laborTotal = data.labor_total ?? 0;
    const laborSource = data.labor_source || 'manual';
    const laborUnit = data.labor_unit_cost_snapshot ?? 0;
    const laborReason = escapeHtml(data.labor_override_reason || '');
    const lineType = data.line_type || 'product';
    const percentValue = data.percent_value ?? 0;
    const basisType = data.basis_type || 'bq_product_total';
    const computedAmount = data.computed_amount ?? 0;
    const sourceTemplateId = data.source_template_id || '';
    const sourceTemplateLineId = data.source_template_line_id || '';
    const editablePrice = data.editable_price !== undefined ? data.editable_price : true;
    const editablePercent = data.editable_percent !== undefined ? data.editable_percent : true;
    const canRemove = data.can_remove !== undefined ? data.can_remove : true;
    const laborBadge = laborSource === 'master_item'
      ? ['I','bg-azure-lt']
      : (laborSource === 'master_project' ? ['P','bg-indigo-lt'] : ['M','bg-secondary-lt']);

    return `
      <tr class="bq-line" data-line-index="${lIndex}">
        <td><input type="text" name="sections[${sIndex}][lines][${lIndex}][line_no]" class="form-control" value="${lineNo}"></td>
        <td>
          <select name="sections[${sIndex}][lines][${lIndex}][line_type]" class="form-select form-select-sm bq-line-type">
            <option value="product" ${lineType === 'product' ? 'selected' : ''}>Product</option>
            <option value="charge" ${lineType === 'charge' ? 'selected' : ''}>Charge</option>
            <option value="percent" ${lineType === 'percent' ? 'selected' : ''}>Percent</option>
          </select>
          <input type="hidden" name="sections[${sIndex}][lines][${lIndex}][source_template_id]" class="bq-line-template-id" value="${escapeHtml(sourceTemplateId)}">
          <input type="hidden" name="sections[${sIndex}][lines][${lIndex}][source_template_line_id]" class="bq-line-template-line-id" value="${escapeHtml(sourceTemplateLineId)}">
          <input type="hidden" name="sections[${sIndex}][lines][${lIndex}][editable_price]" class="bq-line-editable-price" value="${editablePrice ? 1 : 0}">
          <input type="hidden" name="sections[${sIndex}][lines][${lIndex}][editable_percent]" class="bq-line-editable-percent" value="${editablePercent ? 1 : 0}">
          <input type="hidden" name="sections[${sIndex}][lines][${lIndex}][can_remove]" class="bq-line-can-remove" value="${canRemove ? 1 : 0}">
        </td>
        <td>
          <div class="bq-item-controls">
            <div class="row g-2 align-items-center mb-1">
              <div class="col-4">
                <select name="sections[${sIndex}][lines][${lIndex}][source_type]" class="form-select form-select-sm bq-line-source">
                  <option value="item" ${sourceType === 'item' ? 'selected' : ''}>Item</option>
                  <option value="project" ${sourceType === 'project' ? 'selected' : ''}>Project</option>
                </select>
              </div>
              <div class="col-8">
                <input type="text"
                       name="sections[${sIndex}][lines][${lIndex}][item_label]"
                       class="form-control form-control-sm bq-item-search"
                       placeholder="Cari item..."
                       value="${itemLabel}">
                <input type="hidden" name="sections[${sIndex}][lines][${lIndex}][item_id]" class="bq-line-item-id" value="${itemId}">
                <input type="hidden" name="sections[${sIndex}][lines][${lIndex}][labor_source]" class="bq-line-labor-source" value="${laborSource}">
                <input type="hidden" name="sections[${sIndex}][lines][${lIndex}][labor_unit_cost_snapshot]" class="bq-line-labor-unit" value="${laborUnit}">
                <input type="hidden" name="sections[${sIndex}][lines][${lIndex}][labor_override_reason]" class="bq-line-labor-reason" value="${laborReason}">
              </div>
            </div>
          </div>
          <textarea name="sections[${sIndex}][lines][${lIndex}][description]" class="form-control bq-line-desc" rows="2" required>${description}</textarea>
        </td>
        <td><input type="text" name="sections[${sIndex}][lines][${lIndex}][qty]" class="form-control text-end" value="${qty}" required></td>
        <td><input type="text" name="sections[${sIndex}][lines][${lIndex}][unit]" class="form-control" value="${unit}" required></td>
        <td>
          <div class="bq-price-wrap">
            <input type="text" name="sections[${sIndex}][lines][${lIndex}][unit_price]" class="form-control text-end js-line-unit-price" value="${unitPrice}">
          </div>
          <div class="bq-percent-wrap d-none">
            <div class="input-group input-group-sm">
              <input type="text" name="sections[${sIndex}][lines][${lIndex}][percent_value]" class="form-control text-end js-line-percent" value="${percentValue}">
              <span class="input-group-text">%</span>
            </div>
            <input type="hidden" name="sections[${sIndex}][lines][${lIndex}][basis_type]" class="bq-line-basis" value="${escapeHtml(basisType)}">
            <input type="hidden" name="sections[${sIndex}][lines][${lIndex}][computed_amount]" class="bq-line-computed" value="${computedAmount}">
          </div>
        </td>
        <td><input type="text" name="sections[${sIndex}][lines][${lIndex}][material_total]" class="form-control text-end js-line-material" value="${materialTotal}" required></td>
        <td>
          <div class="d-flex align-items-center gap-2">
            <input type="text" name="sections[${sIndex}][lines][${lIndex}][labor_total]" class="form-control text-end js-line-labor" value="${laborTotal}" required>
            <span class="badge ${laborBadge[1]} text-dark js-labor-badge" title="Labor Source">${laborBadge[0]}</span>
            <button type="button" class="btn btn-sm btn-outline-secondary js-update-labor-master d-none">Update</button>
          </div>
        </td>
        <td class="text-end"><span class="js-line-total">0</span></td>
        <td><button type="button" class="btn btn-sm btn-outline-danger btn-remove-line" ${canRemove ? '' : 'disabled'}>Remove</button></td>
      </tr>
    `;
  };

  const makeSection = (sIndex, name = '') => {
    return `
      <div class="bq-section border rounded p-3 mb-3" data-section-index="${sIndex}">
        <div class="d-flex align-items-center mb-2 gap-2 flex-wrap">
          <input type="text" name="sections[${sIndex}][name]" class="form-control me-2 section-name" placeholder="Section name" value="${escapeHtml(name)}" required>
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
                <th style="width:110px;">Type</th>
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

  const normalizeLabel = (val) => String(val ?? '').trim().toLowerCase();

  const normalizeSectionName = (val) => {
    return normalizeLabel(val).replace(/[-_]+/g, ' ').replace(/\s+/g, ' ').trim();
  };

  const makeSignature = (type, label, percentValue, basisType) => {
    const base = `${type}|${normalizeLabel(label)}`;
    if (type !== 'percent') return base;
    const pct = parseNumber(percentValue || 0).toFixed(4);
    return `${base}|${pct}|${basisType || 'bq_product_total'}`;
  };

  const findAddonsSection = () => {
    const targets = new Set(['add-ons', 'add ons', 'biaya tambahan']);
    return [...sectionsEl.querySelectorAll('.bq-section')].find((section) => {
      const name = section.querySelector('.section-name')?.value || '';
      return targets.has(normalizeSectionName(name));
    });
  };

  const ensureAddonsSection = () => {
    let section = findAddonsSection();
    if (section) return section;

    const idx = nextSectionIndex();
    sectionsEl.insertAdjacentHTML('beforeend', makeSection(idx, 'Add-ons'));
    section = sectionsEl.lastElementChild;
    initItemPickers(section);
    const defaultRow = section.querySelector('.bq-line');
    if (defaultRow) {
      const desc = defaultRow.querySelector('.bq-line-desc')?.value || '';
      const itemId = defaultRow.querySelector('.bq-line-item-id')?.value || '';
      const mat = parseNumber(defaultRow.querySelector('.js-line-material')?.value);
      const lab = parseNumber(defaultRow.querySelector('.js-line-labor')?.value);
      if (!desc && !itemId && mat === 0 && lab === 0) {
        defaultRow.remove();
      }
    }
    return section;
  };

  const applyTemplateToForm = (template) => {
    if (!template) return;
    const section = ensureAddonsSection();
    const body = section.querySelector('tbody');
    if (!body) return;

    const existing = new Set();
    section.querySelectorAll('.bq-line').forEach((row) => {
      const type = getLineType(row);
      const label = row.querySelector('.bq-line-desc')?.value || '';
      const percentValue = row.querySelector('.js-line-percent')?.value || 0;
      const basisType = row.querySelector('.bq-line-basis')?.value || 'bq_product_total';
      existing.add(makeSignature(type, label, percentValue, basisType));
    });

    const lines = [...(template.lines || [])].sort((a, b) => {
      const order = (a.sort_order ?? 0) - (b.sort_order ?? 0);
      return order !== 0 ? order : (a.id ?? 0) - (b.id ?? 0);
    });

    lines.forEach((line) => {
      const signature = makeSignature(line.type, line.label, line.percent_value, line.basis_type);
      if (existing.has(signature)) return;

      const isCharge = line.type === 'charge';
      const qty = isCharge ? (line.default_qty ?? 1) : 1;
      const unit = isCharge ? (line.default_unit || 'LS') : '%';
      const unitPrice = isCharge ? (line.default_unit_price ?? 0) : 0;
      const materialTotal = isCharge ? (qty * unitPrice) : 0;

      const lineData = {
        line_type: line.type,
        description: line.label || '',
        source_type: 'item',
        item_id: '',
        item_label: '',
        qty: qty,
        unit: unit,
        unit_price: unitPrice,
        material_total: materialTotal,
        labor_total: 0,
        percent_value: line.type === 'percent' ? (line.percent_value ?? 0) : 0,
        basis_type: line.type === 'percent' ? (line.basis_type || 'bq_product_total') : 'bq_product_total',
        computed_amount: 0,
        source_template_id: template.id,
        source_template_line_id: line.id,
        editable_price: line.editable_price !== false,
        editable_percent: line.editable_percent !== false,
        can_remove: line.can_remove !== false,
      };

      const lIndex = nextLineIndex(section);
      body.insertAdjacentHTML('beforeend', makeLine(section.dataset.sectionIndex, lIndex, lineData));
      const newRow = body.lastElementChild;
      if (newRow) {
        syncLineTypeRow(newRow);
      }
      existing.add(signature);
    });

    recalcTotals();
  };

  const templateModalEl = document.getElementById('bqTemplateModal');
  const templateModal = templateModalEl && window.bootstrap ? new bootstrap.Modal(templateModalEl) : null;

  if (btnApplyTemplate && templateModal) {
    btnApplyTemplate.addEventListener('click', () => {
      templateModal.show();
    });
  }

  if (templateSelect) {
    templateSelect.addEventListener('change', () => {
      const id = parseInt(templateSelect.value || '0', 10);
      const tpl = (BQ_TEMPLATES || []).find((row) => row.id === id);
      if (templateDesc) templateDesc.textContent = tpl?.description || '';
    });
  }

  if (btnConfirmApplyTemplate && templateSelect) {
    btnConfirmApplyTemplate.addEventListener('click', () => {
      const id = parseInt(templateSelect.value || '0', 10);
      const tpl = (BQ_TEMPLATES || []).find((row) => row.id === id);
      if (!tpl) {
        alert('Pilih template terlebih dulu.');
        return;
      }
      applyTemplateToForm(tpl);
      templateModal?.hide();
    });
  }

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

  document.addEventListener('click', (e) => {
    const target = e.target;
    if (!(target instanceof HTMLElement)) return;
    if (!target.classList.contains('js-update-labor-master')) return;

    const row = target.closest('.bq-line');
    if (!row) return;

    const sourceType = getSourceType(row);
    const itemId = row.querySelector('.bq-line-item-id')?.value;
    if (!itemId) {
      alert('Item belum dipilih.');
      return;
    }

    const qty = parseNumber(row.querySelector('input[name$="[qty]"]')?.value);
    const laborTotal = parseNumber(row.querySelector('.js-line-labor')?.value);
    const unitCost = qty > 0 ? (laborTotal / qty) : laborTotal;
    const prevMaster = parseNumber(row.dataset.laborMasterRate || 0);
    let reason = '';

    if (unitCost < prevMaster) {
      reason = window.prompt('Nilai turun. Alasan wajib:') || '';
      if (!reason) return;
    }

    const token = document.querySelector('meta[name=\"csrf-token\"]')?.getAttribute('content');
    fetch(LABOR_UPDATE_URL, {
      method: 'POST',
      credentials: 'same-origin',
      headers: {
        'Content-Type': 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
        'Accept': 'application/json',
        ...(token ? { 'X-CSRF-TOKEN': token } : {}),
      },
      body: JSON.stringify({
        source: sourceType,
        item_id: itemId,
        labor_unit_cost: unitCost,
        reason: reason || null,
      }),
    })
      .then(r => r.ok ? r.json() : Promise.reject(r))
      .then((data) => {
        if (!data || !data.ok) throw new Error(data?.message || 'Update gagal.');
        row.dataset.laborMasterRate = String(unitCost);
        updateLaborSnapshot(row, laborTotal);
        setLaborSource(row, sourceType === 'project' ? 'master_project' : 'master_item');
        const reasonEl = row.querySelector('.bq-line-labor-reason');
        if (reasonEl && reason) reasonEl.value = reason;
        recalcTotals();
      })
      .catch(async (err) => {
        if (err?.json) {
          const res = await err.json().catch(() => null);
          alert(res?.message || 'Update master gagal.');
          return;
        }
        alert('Update master gagal.');
      });
  });

  document.addEventListener('input', (e) => {
    const target = e.target;
    if (!(target instanceof HTMLElement)) return;

    if (target.matches('input[name$="[qty]"]')) {
      const row = target.closest('.bq-line');
      if (row) {
        const source = getLaborSource(row);
        const unitSnapshot = parseNumber(row.querySelector('.bq-line-labor-unit')?.value);
        if (source !== 'manual' && unitSnapshot > 0) {
          const laborTotal = parseNumber(target.value) * unitSnapshot;
          const laborEl = row.querySelector('.js-line-labor');
          if (laborEl) laborEl.value = formatNumber(laborTotal);
        }
      }
      recalcTotals();
      return;
    }

    if (
      target.classList.contains('js-line-material') ||
      target.classList.contains('js-line-labor') ||
      target.classList.contains('js-line-percent') ||
      target.id === 'tax_percent'
    ) {
      recalcTotals();
    }
  });

  const taxEnabled = document.getElementById('tax_enabled');
  if (taxEnabled) {
    taxEnabled.addEventListener('change', recalcTotals);
  }

  document.addEventListener('focusin', (e) => {
    const target = e.target;
    if (!(target instanceof HTMLInputElement)) return;
    if (target.classList.contains('js-line-unit-price') || target.classList.contains('js-line-labor')) {
      target.dataset.prevValue = target.value;
    }
  });

  document.addEventListener('focusout', (e) => {
    const target = e.target;
    if (!(target instanceof HTMLInputElement)) return;
    if (
      !target.classList.contains('js-line-unit-price') &&
      !target.classList.contains('js-line-labor') &&
      !target.classList.contains('js-line-percent')
    ) return;

    const val = parseNumber(target.value);
    if (target.classList.contains('js-line-percent')) {
      target.value = formatPercent(val);
      recalcTotals();
      return;
    }

    target.value = formatNumber(val);

    const row = target.closest('.bq-line');
    if (row && target.classList.contains('js-line-unit-price')) {
      const qty = parseNumber(row.querySelector('input[name$="[qty]"]')?.value);
      const materialEl = row.querySelector('.js-line-material');
      if (materialEl) materialEl.value = formatNumber(qty * val);
    }

    if (row && target.classList.contains('js-line-labor')) {
      const prevVal = parseNumber(target.dataset.prevValue || 0);
      if (prevVal !== val) {
        const source = getLaborSource(row);
        const reasonEl = row.querySelector('.bq-line-labor-reason');
        if (source !== 'manual') {
          const reason = window.prompt('Alasan override labor (wajib):');
          if (!reason) {
            target.value = formatNumber(prevVal);
            return;
          }
          if (reasonEl) reasonEl.value = reason;
          setLaborSource(row, 'manual');
        }
        updateLaborSnapshot(row, val);
      }
    }
    recalcTotals();
  });

  const buildSearchUrl = (sourceType, query) => {
    const params = new URLSearchParams();
    params.set('q', query || '');
    if (sourceType === 'project') {
      params.set('list_type', 'project');
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
        const itemIdEl = row.querySelector('.bq-line-item-id');

        if (descEl && !descEl.value) descEl.value = data.name || '';
        if (unitEl) unitEl.value = (data.unit_code || 'LS').toString().toLowerCase();
        const price = parseNumber(data.price);
        if (unitPriceEl) unitPriceEl.value = formatNumber(price);
        if (laborEl && !laborEl.value) laborEl.value = 0;
        if (itemIdEl) itemIdEl.value = data.item_id || '';

        const qty = parseNumber(qtyEl?.value);
        if (materialEl) materialEl.value = formatNumber(qty * price);
        updateMasterButtonVisibility(row);
        const sourceType = getSourceType(row);
        if (data.item_id) {
          fetchLaborRate(sourceType, data.item_id).then((rateData) => applyLaborRate(row, sourceType, rateData));
        }
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

    if (getLineType(row) !== 'product') {
      searchInput.disabled = true;
      return;
    }

    const sourceType = sourceSel.value === 'project' ? 'project' : 'item';
    if (searchInput.dataset.sourceType && searchInput.dataset.sourceType !== sourceType) {
      searchInput.value = '';
      const itemIdEl = row.querySelector('.bq-line-item-id');
      if (itemIdEl) itemIdEl.value = '';
      setLaborSource(row, 'manual');
    }
    searchInput.disabled = false;
    searchInput.placeholder = sourceType === 'item' ? 'Cari item...' : 'Cari project item...';
    initItemPicker(searchInput, sourceType);
    syncLaborBadgeFromRow(row);
  };

  const initItemPickers = (scope) => {
    const root = scope || sectionsEl;
    const rows = [];
    if (root.classList?.contains('bq-line')) {
      rows.push(root);
    }
    rows.push(...root.querySelectorAll('.bq-line'));
    rows.forEach((row) => syncLineTypeRow(row));
  };

  sectionsEl.addEventListener('change', (e) => {
    if (e.target.classList.contains('bq-line-source')) {
      const row = e.target.closest('.bq-line');
      if (row) syncSourceRow(row);
    }
    if (e.target.classList.contains('bq-line-type')) {
      const row = e.target.closest('.bq-line');
      if (row) syncLineTypeRow(row);
      recalcTotals();
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

@push('styles')
<style>
  .bq-section .section-name{
    flex: 1 1 380px;
    max-width: 520px;
  }
</style>
@endpush
