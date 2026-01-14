@php
  $selectedCompany = old('company_id', $project->company_id ?? ($defaultCompanyId ?? null));
  $selectedCustomer = old('customer_id', $project->customer_id ?? ($defaultCustomerId ?? null));
  $selectedSales = old('sales_owner_user_id', $project->sales_owner_user_id ?? auth()->id());
  $selectedSystems = old('systems', old('systems_json', $project->systems_json ?? []));
  $selectedSystems = is_array($selectedSystems) ? $selectedSystems : [];
  $systemsOptions = $systemsOptions ?? \App\Support\ProjectSystems::all();
@endphp

<div class="card mb-3">
  <div class="card-header">
    <h3 class="card-title">Project Profile</h3>
  </div>
  <div class="card-body">
    <div class="row g-3">
      <div class="col-md-4">
        <label class="form-label">Code</label>
        <input type="text" name="code" class="form-control" value="{{ old('code', $project->code ?? '') }}" placeholder="Auto if empty">
        <div class="form-hint">Kosongkan untuk nomor otomatis.</div>
      </div>
      <div class="col-md-4">
        <label class="form-label">Company</label>
        <select name="company_id" class="form-select">
          <option value="">Default Company</option>
          @foreach($companies as $co)
            <option value="{{ $co->id }}" @selected((string)$selectedCompany === (string)$co->id)>
              {{ $co->alias ?: $co->name }}
            </option>
          @endforeach
        </select>
      </div>
      <div class="col-md-4">
        <label class="form-label">Sales Owner</label>
        <select name="sales_owner_user_id" class="form-select" required>
          @foreach($salesUsers as $su)
            <option value="{{ $su->id }}" @selected((string)$selectedSales === (string)$su->id)>{{ $su->name }}</option>
          @endforeach
        </select>
      </div>

      <div class="col-md-6">
        <label class="form-label">Customer</label>
        <select name="customer_id" class="form-select" required>
          <option value="">Pilih customer</option>
          @foreach($customers as $cust)
            <option value="{{ $cust->id }}" @selected((string)$selectedCustomer === (string)$cust->id)>{{ $cust->name }}</option>
          @endforeach
        </select>
      </div>
      <div class="col-md-6">
        <label class="form-label">Project Name</label>
        <input type="text" name="name" class="form-control" value="{{ old('name', $project->name ?? '') }}" required>
      </div>

      <div class="col-md-6">
        <label class="form-label">Systems</label>
        <select name="systems[]" class="form-select" multiple required>
          @foreach($systemsOptions as $key => $label)
            <option value="{{ $key }}" @selected(in_array($key, $selectedSystems, true))>{{ $label }}</option>
          @endforeach
        </select>
      </div>
      <div class="col-md-3">
        <label class="form-label">Status</label>
        <select name="status" class="form-select">
          @foreach(['draft' => 'Draft', 'active' => 'Active', 'closed' => 'Closed', 'cancelled' => 'Cancelled'] as $val => $label)
            <option value="{{ $val }}" @selected(old('status', $project->status ?? 'draft') === $val)>{{ $label }}</option>
          @endforeach
        </select>
      </div>
      <div class="col-md-3">
        <label class="form-label">Start Date</label>
        <input type="date" name="start_date" class="form-control" value="{{ old('start_date', optional($project->start_date ?? null)->format('Y-m-d')) }}">
      </div>
      <div class="col-md-3">
        <label class="form-label">Target Finish</label>
        <input type="date" name="target_finish_date" class="form-control" value="{{ old('target_finish_date', optional($project->target_finish_date ?? null)->format('Y-m-d')) }}">
      </div>
    </div>
  </div>
</div>

<div class="card mb-3">
  <div class="card-header">
    <h3 class="card-title">Commercial</h3>
  </div>
  <div class="card-body">
    <div class="row g-3">
      <div class="col-md-6">
        <label class="form-label">Contract Baseline</label>
        <input type="text" name="contract_value_baseline" class="form-control text-end" value="{{ old('contract_value_baseline', $project->contract_value_baseline ?? 0) }}">
      </div>
      <div class="col-md-6">
        <label class="form-label">Contract Current</label>
        <input type="text" name="contract_value_current" class="form-control text-end" value="{{ old('contract_value_current', $project->contract_value_current ?? 0) }}">
      </div>
    </div>
  </div>
</div>

<div class="card">
  <div class="card-header">
    <h3 class="card-title">Notes</h3>
  </div>
  <div class="card-body">
    <textarea name="notes" class="form-control" rows="4" placeholder="Catatan internal">{{ old('notes', $project->notes ?? '') }}</textarea>
  </div>
</div>
