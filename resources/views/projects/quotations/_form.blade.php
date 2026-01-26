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
          'catalog_id' => data_get($line, 'catalog_id'),
          'percent_value' => data_get($line, 'percent_value', 0),
          'percent_basis' => data_get($line, 'percent_basis', 'product_subtotal'),
          'computed_amount' => data_get($line, 'computed_amount', data_get($line, 'material_total', 0)),
          'cost_bucket' => data_get($line, 'cost_bucket', 'overhead'),
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

<div class="card mb-3">
  <div class="card-header d-flex align-items-center">
    <h3 class="card-title">BQ Sections & Lines</h3>
    <div class="ms-auto btn-list">
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
          <div class="btn-group">
            <button type="button" class="btn btn-sm btn-outline-secondary btn-move-section-up">Up</button>
            <button type="button" class="btn btn-sm btn-outline-secondary btn-move-section-down">Down</button>
          </div>
          <button type="button" class="btn btn-sm btn-outline-danger btn-remove-section">Remove</button>
        </div>

        <div class="table-responsive">
          <table class="table table-sm table-vcenter">
            <thead>
              <tr>
                <th style="width:110px;">No / Type</th>
                <th>Description</th>
                <th style="width:90px;" class="text-end">Qty</th>
                <th style="width:90px;">Unit</th>
                <th style="width:120px;" class="text-end">Unit Price</th>
                <th style="width:140px;" class="text-end">Material</th>
                <th style="width:160px;" class="text-end">Labor</th>
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
                @endphp
                <tr class="bq-line" data-line-index="{{ $lIndex }}">
                  <td>
                    <input type="text" name="sections[{{ $sIndex }}][lines][{{ $lIndex }}][line_no]" class="form-control mb-1" value="{{ $line['line_no'] ?? '' }}">
                    <select name="sections[{{ $sIndex }}][lines][{{ $lIndex }}][line_type]" class="form-select form-select-sm bq-line-type">
                      <option value="product" @selected($lineType === 'product')>Product</option>
                      <option value="charge" @selected($lineType === 'charge')>Charge</option>
                      <option value="percent" @selected($lineType === 'percent')>Percent</option>
                    </select>
                    <input type="hidden" name="sections[{{ $sIndex }}][lines][{{ $lIndex }}][catalog_id]" class="bq-line-catalog-id" value="{{ $line['catalog_id'] ?? '' }}">
                    <input type="hidden" name="sections[{{ $sIndex }}][lines][{{ $lIndex }}][cost_bucket]" class="bq-line-cost-bucket" value="{{ $line['cost_bucket'] ?? 'overhead' }}">
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
                    <div class="bq-catalog-controls d-none">
                      <div class="input-group input-group-sm mb-1">
                        <span class="input-group-text">Catalog</span>
                        <input type="text"
                               class="form-control form-control-sm bq-line-catalog"
                               placeholder="Cari catalog..."
                               value="{{ !empty($line['catalog_id']) ? ($line['description'] ?? '') : '' }}">
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
                      <input type="hidden" name="sections[{{ $sIndex }}][lines][{{ $lIndex }}][percent_basis]" class="bq-line-percent-basis" value="{{ $line['percent_basis'] ?? 'product_subtotal' }}">
                      <input type="hidden" name="sections[{{ $sIndex }}][lines][{{ $lIndex }}][computed_amount]" class="bq-line-computed" value="{{ $line['computed_amount'] ?? 0 }}">
                    </div>
                  </td>
                  <td><input type="text" name="sections[{{ $sIndex }}][lines][{{ $lIndex }}][material_total]" class="form-control text-end js-line-material" value="{{ $line['material_total'] ?? 0 }}" required></td>
                  <td>
                    <div class="d-flex flex-column gap-1">
                      <input type="text" name="sections[{{ $sIndex }}][lines][{{ $lIndex }}][labor_total]" class="form-control text-end js-line-labor" value="{{ $line['labor_total'] ?? 0 }}" required>
                      <div class="d-flex align-items-center gap-2">
                        <span class="badge {{ $laborBadge[1] }} text-dark js-labor-badge" title="Labor Source">{{ $laborBadge[0] }}</span>
                        <button type="button" class="btn btn-sm btn-outline-secondary js-update-labor-master d-none">Update</button>
                      </div>
                    </div>
                  </td>
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
      @php
        $signatureUsers = collect($signatureUsers ?? []);
        $directorName = 'Christian Widargo';
        $directorTitle = 'Direktur Utama';
        $signerName = old('signatory_name', $quotation->signatory_name ?? auth()->user()?->name ?? '');
        $signerTitle = old('signatory_title', $quotation->signatory_title ?? '');
        $selectedSigner = old('signatory_choice');

        if (!$selectedSigner) {
          if ($signerName && strcasecmp($signerName, $directorName) === 0) {
            $selectedSigner = 'director';
          } else {
            $match = $signatureUsers->first(function ($row) use ($signerName) {
              return $signerName && strcasecmp($row->name ?? '', $signerName) === 0;
            });
            $selectedSigner = $match?->id;
          }
        }

        if (!$selectedSigner) {
          $selectedSigner = auth()->id();
        }

        if ($selectedSigner && $selectedSigner !== 'director') {
          $selectedUser = $signatureUsers->firstWhere('id', (int) $selectedSigner);
          if (!$signerTitle && $selectedUser?->default_position) {
            $signerTitle = $selectedUser->default_position;
          }
        }

        if ($selectedSigner === 'director' && !$signerTitle) {
          $signerTitle = $directorTitle;
        }
      @endphp

      <div class="col-md-4">
        <label class="form-label">Signature</label>
        <select id="bq-signatory-choice" class="form-select">
          <option value="">Pilih Signature</option>
          <option value="director"
                  data-name="{{ $directorName }}"
                  data-position="{{ $directorTitle }}"
                  @selected($selectedSigner === 'director')>Direktur Utama</option>
          @foreach($signatureUsers as $user)
            <option value="{{ $user->id }}"
                    data-name="{{ $user->name }}"
                    data-position="{{ $user->default_position ?? '' }}"
                    @selected((string) $selectedSigner === (string) $user->id)>
              {{ $user->name }}
            </option>
          @endforeach
        </select>
        <input type="hidden" name="signatory_choice" id="signatory_choice" value="{{ $selectedSigner }}">
      </div>
      <div class="col-md-4">
        <label class="form-label">Signatory Name</label>
        <input type="text" name="signatory_name" id="bq-signatory-name" class="form-control" value="{{ $signerName }}" readonly>
      </div>
      <div class="col-md-4" id="bq-signatory-title-wrap">
        <label class="form-label">Signatory Title</label>
        <input type="text" name="signatory_title" id="bq-signatory-title" class="form-control" value="{{ $signerTitle }}">
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
  const LABOR_RATE_URL = @json(route('labor-rates.show', [], false));
  const LABOR_UPDATE_URL = @json(route('labor-rates.update', [], false));
  const CATALOG_SEARCH_URL = @json(route('bq-line-catalogs.search', [], false));
  const CAN_UPDATE_ITEM_LABOR = @json(auth()->user()?->hasAnyRole(['Admin','SuperAdmin','Finance']) ?? false);
  const CAN_UPDATE_PROJECT_LABOR = @json(auth()->user()?->hasAnyRole(['Admin','SuperAdmin','PM']) ?? false);

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

  const applyCatalogToRow = (row, data) => {
    if (!row || !data) return;

    const typeSel = row.querySelector('.bq-line-type');
    const lineType = data.type === 'percent' ? 'percent' : 'charge';
    if (typeSel) typeSel.value = lineType;
    syncLineTypeRow(row);

    const descEl = row.querySelector('.bq-line-desc');
    if (descEl) descEl.value = data.name || '';

    const catalogIdEl = row.querySelector('.bq-line-catalog-id');
    if (catalogIdEl) catalogIdEl.value = data.id || '';

    const costBucketEl = row.querySelector('.bq-line-cost-bucket');
    if (costBucketEl) costBucketEl.value = data.cost_bucket || 'overhead';

    const qtyEl = row.querySelector('input[name$="[qty]"]');
    const unitEl = row.querySelector('input[name$="[unit]"]');
    const unitPriceEl = row.querySelector('input[name$="[unit_price]"]');
    const materialEl = row.querySelector('.js-line-material');
    const laborEl = row.querySelector('.js-line-labor');
    const percentEl = row.querySelector('.js-line-percent');
    const basisEl = row.querySelector('.bq-line-percent-basis');

    if (lineType === 'charge') {
      const qty = data.default_qty != null ? Number(data.default_qty) : 1;
      const unit = data.default_unit || 'LS';
      const price = data.default_unit_price != null ? Number(data.default_unit_price) : 0;

      if (qtyEl) qtyEl.value = String(qty);
      if (unitEl) unitEl.value = unit;
      if (unitPriceEl) unitPriceEl.value = formatNumber(price);
      if (materialEl) materialEl.value = formatNumber(qty * price);
      if (laborEl) laborEl.value = formatNumber(0);
      if (percentEl) percentEl.value = formatPercent(0);
      if (basisEl) basisEl.value = 'product_subtotal';
    } else {
      const pct = data.default_percent != null ? Number(data.default_percent) : 0;
      const basis = data.percent_basis || 'product_subtotal';

      if (percentEl) percentEl.value = formatPercent(pct);
      if (basisEl) basisEl.value = basis;
      if (qtyEl) qtyEl.value = '1';
      if (unitEl) unitEl.value = '%';
      if (unitPriceEl) unitPriceEl.value = formatNumber(0);
      if (materialEl) materialEl.value = formatNumber(0);
      if (laborEl) laborEl.value = formatNumber(0);
    }

    recalcTotals();
  };

  const initCatalogPicker = (input) => {
    if (!input || !window.TomSelect) return;
    if (input._catalogTs) return;

    const ts = new TomSelect(input, {
      valueField: 'id',
      labelField: 'name',
      searchField: ['name', 'description'],
      maxOptions: 200,
      create: false,
      persist: false,
      dropdownParent: 'body',
      preload: 'focus',
      closeAfterSelect: true,
      load(query, cb){
        const row = input.closest('.bq-line');
        const lineType = row ? getLineType(row) : '';
        const params = new URLSearchParams();
        params.set('q', query || '');
        if (lineType === 'charge' || lineType === 'percent') {
          params.set('type', lineType);
        }
        fetch(`${CATALOG_SEARCH_URL}?${params.toString()}`, {
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
        if (!val) {
          const row = input.closest('.bq-line');
          if (row) {
            const catalogIdEl = row.querySelector('.bq-line-catalog-id');
            if (catalogIdEl) catalogIdEl.value = '';
          }
          return;
        }
        const data = this.options[val];
        if (!data) return;
        const row = input.closest('.bq-line');
        if (row) applyCatalogToRow(row, data);
      }
    });

    input._catalogTs = ts;
    ts.on('focus', () => {
      ts.load('');
      ts.open();
    });
  };

  const togglePercentFields = (row, isPercent) => {
    row.querySelector('.bq-percent-wrap')?.classList.toggle('d-none', !isPercent);
    row.querySelector('.bq-price-wrap')?.classList.toggle('d-none', isPercent);
  };

  const syncCatalogRow = (row) => {
    const catalogWrap = row.querySelector('.bq-catalog-controls');
    const catalogInput = row.querySelector('.bq-line-catalog');
    if (!catalogWrap || !catalogInput) return;

    const isProduct = getLineType(row) === 'product';
    catalogWrap.classList.toggle('d-none', isProduct);
    catalogInput.disabled = isProduct;

    if (isProduct) {
      const catalogIdEl = row.querySelector('.bq-line-catalog-id');
      if (catalogIdEl) catalogIdEl.value = '';
      catalogInput.value = '';
      if (catalogInput._catalogTs) {
        catalogInput._catalogTs.clear();
        catalogInput._catalogTs.disable();
      }
      return;
    }

    initCatalogPicker(catalogInput);
    catalogInput._catalogTs?.enable();
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
    const basisEl = row.querySelector('.bq-line-percent-basis');

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

    syncCatalogRow(row);

    if (lineType === 'percent') {
      if (basisEl && !basisEl.value) basisEl.value = 'product_subtotal';
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
        const basisType = row.querySelector('.bq-line-percent-basis')?.value || 'product_subtotal';
        const pct = parseNumber(percentEl?.value);
        const section = row.closest('.bq-section');
        let basis = basisType === 'section_product_subtotal'
          ? (section ? (sectionProductTotals.get(section) || 0) : 0)
          : productSubtotal;
        if (basis <= 0 && basisType === 'section_product_subtotal' && productSubtotal > 0) {
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

  const updateSectionSortOrder = () => {
    const sections = [...sectionsEl.querySelectorAll('.bq-section')];
    sections.forEach((section, idx) => {
      const input = section.querySelector('input[name$="[sort_order]"]');
      if (input) input.value = String(idx + 1);
    });
  };

  const findPrevSection = (section) => {
    let prev = section?.previousElementSibling;
    while (prev && !prev.classList.contains('bq-section')) {
      prev = prev.previousElementSibling;
    }
    return prev;
  };

  const findNextSection = (section) => {
    let next = section?.nextElementSibling;
    while (next && !next.classList.contains('bq-section')) {
      next = next.nextElementSibling;
    }
    return next;
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
    const percentBasis = data.percent_basis || 'product_subtotal';
    const computedAmount = data.computed_amount ?? 0;
    const catalogId = data.catalog_id || '';
    const catalogLabel = catalogId ? description : '';
    const costBucket = data.cost_bucket || 'overhead';
    const laborBadge = laborSource === 'master_item'
      ? ['I','bg-azure-lt']
      : (laborSource === 'master_project' ? ['P','bg-indigo-lt'] : ['M','bg-secondary-lt']);

    return `
      <tr class="bq-line" data-line-index="${lIndex}">
        <td>
          <input type="text" name="sections[${sIndex}][lines][${lIndex}][line_no]" class="form-control mb-1" value="${lineNo}">
          <select name="sections[${sIndex}][lines][${lIndex}][line_type]" class="form-select form-select-sm bq-line-type">
            <option value="product" ${lineType === 'product' ? 'selected' : ''}>Product</option>
            <option value="charge" ${lineType === 'charge' ? 'selected' : ''}>Charge</option>
            <option value="percent" ${lineType === 'percent' ? 'selected' : ''}>Percent</option>
          </select>
          <input type="hidden" name="sections[${sIndex}][lines][${lIndex}][catalog_id]" class="bq-line-catalog-id" value="${escapeHtml(catalogId)}">
          <input type="hidden" name="sections[${sIndex}][lines][${lIndex}][cost_bucket]" class="bq-line-cost-bucket" value="${escapeHtml(costBucket)}">
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
          <div class="bq-catalog-controls d-none">
            <div class="input-group input-group-sm mb-1">
              <span class="input-group-text">Catalog</span>
              <input type="text"
                     class="form-control form-control-sm bq-line-catalog"
                     placeholder="Cari catalog..."
                     value="${catalogLabel}">
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
            <input type="hidden" name="sections[${sIndex}][lines][${lIndex}][percent_basis]" class="bq-line-percent-basis" value="${escapeHtml(percentBasis)}">
            <input type="hidden" name="sections[${sIndex}][lines][${lIndex}][computed_amount]" class="bq-line-computed" value="${computedAmount}">
          </div>
        </td>
        <td><input type="text" name="sections[${sIndex}][lines][${lIndex}][material_total]" class="form-control text-end js-line-material" value="${materialTotal}" required></td>
        <td>
          <div class="d-flex flex-column gap-1">
            <input type="text" name="sections[${sIndex}][lines][${lIndex}][labor_total]" class="form-control text-end js-line-labor" value="${laborTotal}" required>
            <div class="d-flex align-items-center gap-2">
              <span class="badge ${laborBadge[1]} text-dark js-labor-badge" title="Labor Source">${laborBadge[0]}</span>
              <button type="button" class="btn btn-sm btn-outline-secondary js-update-labor-master d-none">Update</button>
            </div>
          </div>
        </td>
        <td class="text-end"><span class="js-line-total">0</span></td>
        <td><button type="button" class="btn btn-sm btn-outline-danger btn-remove-line">Remove</button></td>
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
          <div class="btn-group">
            <button type="button" class="btn btn-sm btn-outline-secondary btn-move-section-up">Up</button>
            <button type="button" class="btn btn-sm btn-outline-secondary btn-move-section-down">Down</button>
          </div>
          <button type="button" class="btn btn-sm btn-outline-danger btn-remove-section">Remove</button>
        </div>
        <div class="table-responsive">
          <table class="table table-sm table-vcenter">
            <thead>
              <tr>
                <th style="width:110px;">No / Type</th>
                <th>Description</th>
                <th style="width:90px;" class="text-end">Qty</th>
                <th style="width:90px;">Unit</th>
                <th style="width:120px;" class="text-end">Unit Price</th>
                <th style="width:140px;" class="text-end">Material</th>
                <th style="width:160px;" class="text-end">Labor</th>
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
      updateSectionSortOrder();
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
    if (e.target.classList.contains('btn-move-section-up')) {
      const section = e.target.closest('.bq-section');
      const prev = findPrevSection(section);
      if (section && prev) {
        sectionsEl.insertBefore(section, prev);
        updateSectionSortOrder();
      }
      return;
    }

    if (e.target.classList.contains('btn-move-section-down')) {
      const section = e.target.closest('.bq-section');
      const next = findNextSection(section);
      if (section && next) {
        sectionsEl.insertBefore(next, section);
        updateSectionSortOrder();
      }
      return;
    }

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
      updateSectionSortOrder();
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
      if (row) {
        syncLineTypeRow(row);
        const catalogIdEl = row.querySelector('.bq-line-catalog-id');
        const catalogInput = row.querySelector('.bq-line-catalog');
        if (catalogIdEl) catalogIdEl.value = '';
        if (catalogInput?._catalogTs) {
          catalogInput._catalogTs.clear(true);
          catalogInput._catalogTs.clearOptions();
        } else if (catalogInput) {
          catalogInput.value = '';
        }
      }
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
  updateSectionSortOrder();

  const signerSelect = document.getElementById('bq-signatory-choice');
  const signerNameInput = document.getElementById('bq-signatory-name');
  const signerTitleInput = document.getElementById('bq-signatory-title');
  const signerChoiceInput = document.getElementById('signatory_choice');
  const signerTitleWrap = document.getElementById('bq-signatory-title-wrap');

  const syncSigner = (opts = {}) => {
    if (!signerSelect) return;
    const selected = signerSelect.value || '';
    const option = signerSelect.selectedOptions[0];
    const name = option?.dataset?.name || '';
    const position = option?.dataset?.position || '';
    const isDirector = selected === 'director';

    if (signerChoiceInput) signerChoiceInput.value = selected;
    if (signerNameInput && (opts.force || !signerNameInput.value)) {
      signerNameInput.value = name;
    }

    if (signerTitleInput) {
      if (isDirector) {
        signerTitleInput.value = position || signerTitleInput.value;
      } else if (opts.force || !signerTitleInput.value) {
        signerTitleInput.value = position;
      }
    }

    if (signerTitleWrap) {
      signerTitleWrap.style.display = isDirector ? 'none' : '';
    }
  };

  if (signerSelect) {
    signerSelect.addEventListener('change', () => syncSigner({ force: true }));
    syncSigner();
  }
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
