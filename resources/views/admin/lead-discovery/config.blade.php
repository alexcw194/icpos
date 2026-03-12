@extends('layouts.tabler')

@section('content')
<div class="container-xl">
  <div class="page-header mb-3">
    <div class="row align-items-center">
      <div class="col">
        <div class="page-pretitle">Admin</div>
        <h2 class="page-title">Lead Discovery Config</h2>
      </div>
    </div>
  </div>

  <ul class="nav nav-tabs mb-3">
    <li class="nav-item">
      <a class="nav-link {{ $tab === 'runtime' ? 'active' : '' }}" href="{{ route('admin.lead-discovery.config', ['tab' => 'runtime', 'per_page' => request('per_page', $perPage ?? 20)]) }}">Runtime/Scheduler</a>
    </li>
    <li class="nav-item">
      <a class="nav-link {{ $tab === 'keywords' ? 'active' : '' }}" href="{{ route('admin.lead-discovery.config', ['tab' => 'keywords', 'per_page' => request('per_page', $perPage ?? 20)]) }}">Keywords</a>
    </li>
    <li class="nav-item">
      <a class="nav-link {{ $tab === 'cells' ? 'active' : '' }}" href="{{ route('admin.lead-discovery.config', ['tab' => 'cells', 'per_page' => request('per_page', $perPage ?? 20)]) }}">Grid Cells</a>
    </li>
  </ul>

  @if($tab === 'runtime')
    <div class="card mb-3">
      <div class="card-header"><h3 class="card-title">Runtime Settings</h3></div>
      <div class="card-body">
        <form method="post" action="{{ route('admin.lead-discovery.config.update') }}" class="row g-3">
          @csrf
          <div class="col-md-3">
            <label class="form-label">Enable Scheduler</label>
            <label class="form-check form-switch">
              <input class="form-check-input" type="checkbox" name="enabled" value="1" @checked((int) $settings['enabled'] === 1)>
              <span class="form-check-label">Enabled</span>
            </label>
          </div>
          <div class="col-md-3">
            <label class="form-label">Max Cells / Run</label>
            <input type="number" min="1" class="form-control" name="max_cells_per_run" value="{{ old('max_cells_per_run', $settings['max_cells_per_run']) }}" required>
          </div>
          <div class="col-md-3">
            <label class="form-label">Max Keywords / Cell</label>
            <input type="number" min="1" class="form-control" name="max_keywords_per_cell" value="{{ old('max_keywords_per_cell', $settings['max_keywords_per_cell']) }}" required>
          </div>
          <div class="col-md-3">
            <label class="form-label">Max Pages / Query</label>
            <input type="number" min="1" max="3" class="form-control" name="max_pages_per_query" value="{{ old('max_pages_per_query', $settings['max_pages_per_query']) }}" required>
          </div>
          <div class="col-md-3">
            <label class="form-label">Page Token Delay (ms)</label>
            <input type="number" min="0" class="form-control" name="page_token_delay_ms" value="{{ old('page_token_delay_ms', $settings['page_token_delay_ms']) }}" required>
          </div>
          <div class="col-md-3">
            <label class="form-label">Request Timeout (sec)</label>
            <input type="number" min="5" class="form-control" name="request_timeout_sec" value="{{ old('request_timeout_sec', $settings['request_timeout_sec']) }}" required>
          </div>
          <div class="col-md-3">
            <label class="form-label">Retry Max</label>
            <input type="number" min="0" class="form-control" name="retry_max" value="{{ old('retry_max', $settings['retry_max']) }}" required>
          </div>
          <div class="col-12">
            <button class="btn btn-primary">Save Runtime Settings</button>
          </div>
        </form>
      </div>
    </div>

    <div class="card mb-3">
      <div class="card-header"><h3 class="card-title">Manual Scan Trigger</h3></div>
      <div class="card-body">
        <form method="post" action="{{ route('admin.lead-discovery.scan.run') }}" class="row g-3">
          @csrf
          <div class="col-md-8">
            <label class="form-label">Note</label>
            <input type="text" class="form-control" name="note" placeholder="Optional note for this scan run">
          </div>
          <div class="col-md-2">
            <label class="form-label">Force</label>
            <label class="form-check form-switch">
              <input class="form-check-input" type="checkbox" name="force" value="1">
              <span class="form-check-label">Yes</span>
            </label>
          </div>
          <div class="col-md-2 d-flex align-items-end">
            <button class="btn btn-primary w-100">Run Manual Scan</button>
          </div>
        </form>
      </div>
    </div>

    <div class="card">
      <div class="card-header"><h3 class="card-title">Last Scan Runs</h3></div>
      <div class="table-responsive">
        <table class="table table-vcenter card-table">
          <thead>
            <tr>
              <th>ID</th>
              <th>Mode</th>
              <th>Status</th>
              <th>Started</th>
              <th>Finished</th>
              <th>By</th>
              <th>Totals</th>
            </tr>
          </thead>
          <tbody>
            @forelse($scanRuns as $run)
              <tr>
                <td>#{{ $run->id }}</td>
                <td>{{ $run->mode }}</td>
                <td>{{ $run->status }}</td>
                <td>{{ $run->started_at?->format('d M Y H:i:s') ?: '-' }}</td>
                <td>{{ $run->finished_at?->format('d M Y H:i:s') ?: '-' }}</td>
                <td>{{ $run->creator?->name ?: '-' }}</td>
                <td>
                  <pre class="mb-0 small">{{ json_encode($run->totals_json, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) }}</pre>
                </td>
              </tr>
            @empty
              <tr><td colspan="7" class="text-center text-muted">No scan run yet.</td></tr>
            @endforelse
          </tbody>
        </table>
      </div>
    </div>
  @endif

  @if($tab === 'keywords')
    <div class="card mb-3">
      <div class="card-header"><h3 class="card-title">Add Keyword</h3></div>
      <div class="card-body">
        <form method="post" action="{{ route('admin.lead-discovery.keywords.store') }}" class="row g-2">
          @csrf
          <div class="col-md-4">
            <input type="text" name="keyword" class="form-control" placeholder="Keyword" required>
          </div>
          <div class="col-md-3">
            <input type="text" name="category_label" class="form-control" placeholder="Category Label">
          </div>
          <div class="col-md-2">
            <input type="number" min="1" name="priority" class="form-control" placeholder="Priority" value="100" required>
          </div>
          <div class="col-md-2">
            <label class="form-check mt-2">
              <input type="checkbox" class="form-check-input" name="is_active" value="1" checked>
              <span class="form-check-label">Active</span>
            </label>
          </div>
          <div class="col-md-1">
            <button class="btn btn-primary w-100">Add</button>
          </div>
        </form>
      </div>
    </div>

    <div class="card">
      <div class="table-responsive">
        <table class="table table-vcenter card-table">
          <thead>
            <tr>
              <th>Keyword</th>
              <th>Category</th>
              <th>Priority</th>
              <th>Active</th>
              <th style="width: 360px;">Actions</th>
            </tr>
          </thead>
          <tbody>
            @foreach($keywords as $keyword)
              <tr>
                <td>{{ $keyword->keyword }}</td>
                <td>{{ $keyword->category_label ?: '-' }}</td>
                <td>{{ $keyword->priority }}</td>
                <td>{{ $keyword->is_active ? 'Yes' : 'No' }}</td>
                <td>
                  <div class="d-flex gap-1">
                    <form method="post" action="{{ route('admin.lead-discovery.keywords.update', $keyword) }}" class="d-flex gap-1 flex-grow-1">
                      @csrf
                      @method('PUT')
                      <input type="text" name="keyword" class="form-control form-control-sm" value="{{ $keyword->keyword }}" required>
                      <input type="text" name="category_label" class="form-control form-control-sm" value="{{ $keyword->category_label }}">
                      <input type="number" min="1" name="priority" class="form-control form-control-sm" value="{{ $keyword->priority }}" style="max-width:90px;" required>
                      <label class="form-check mt-1">
                        <input type="checkbox" class="form-check-input" name="is_active" value="1" @checked($keyword->is_active)>
                      </label>
                      <button class="btn btn-sm btn-outline-primary">Save</button>
                    </form>
                    <form method="post" action="{{ route('admin.lead-discovery.keywords.toggle-active', $keyword) }}">
                      @csrf
                      <button class="btn btn-sm btn-outline-secondary">{{ $keyword->is_active ? 'Disable' : 'Enable' }}</button>
                    </form>
                    <form method="post" action="{{ route('admin.lead-discovery.keywords.destroy', $keyword) }}" onsubmit="return confirm('Delete keyword ini?')">
                      @csrf
                      @method('DELETE')
                      <button class="btn btn-sm btn-danger">Delete</button>
                    </form>
                  </div>
                </td>
              </tr>
            @endforeach
          </tbody>
        </table>
      </div>
      <div class="card-footer">
        {{ $keywords->links() }}
      </div>
    </div>
  @endif

  @if($tab === 'cells')
    @php
      $addSelectedProvince = (string) old('province', '');
      $addSelectedCity = (string) old('city', '');
      $addCityOptions = $addSelectedProvince !== ''
        ? ($gridCityOptionsByProvince[$addSelectedProvince] ?? [])
        : $gridCityOptionsAll;
    @endphp
    <div class="card mb-3">
      <div class="card-header"><h3 class="card-title">Add Grid Cell</h3></div>
      <div class="card-body">
        <form method="post" action="{{ route('admin.lead-discovery.grid-cells.store') }}" class="row g-2">
          @csrf
          <div class="col-md-2"><input type="text" name="name" class="form-control" placeholder="Name" required></div>
          <div class="col-md-2"><input type="number" step="0.0000001" name="center_lat" class="form-control" placeholder="Latitude" required></div>
          <div class="col-md-2"><input type="number" step="0.0000001" name="center_lng" class="form-control" placeholder="Longitude" required></div>
          <div class="col-md-1"><input type="number" name="radius_m" class="form-control" placeholder="Radius m" value="12000" required></div>
          <div class="col-md-1"><input type="text" name="region_code" class="form-control" placeholder="Region"></div>
          <div class="col-md-2">
            <select name="province" class="form-select ld-grid-province" id="ld-grid-add-province" data-city-target="ld-grid-add-city">
              <option value="">Province (optional)</option>
              @foreach($gridProvinceOptions as $provinceOption)
                <option value="{{ $provinceOption }}" @selected($addSelectedProvince === $provinceOption)>{{ $provinceOption }}</option>
              @endforeach
            </select>
          </div>
          <div class="col-md-2">
            <select name="city" class="form-select ld-grid-city" id="ld-grid-add-city" data-selected="{{ $addSelectedCity }}">
              <option value="">City (optional)</option>
              @foreach($addCityOptions as $cityOption)
                <option value="{{ $cityOption }}" @selected($addSelectedCity === $cityOption)>{{ $cityOption }}</option>
              @endforeach
            </select>
          </div>
          <div class="col-md-1 d-flex align-items-center gap-2">
            <label class="form-check">
              <input type="checkbox" class="form-check-input" name="is_active" value="1" checked>
            </label>
            <button class="btn btn-primary">Add</button>
          </div>
        </form>
      </div>
    </div>

    <div class="card">
      <div class="table-responsive">
        <table class="table table-vcenter card-table">
          <thead>
            <tr>
              <th>Name</th>
              <th>Lat</th>
              <th>Lng</th>
              <th>Radius</th>
              <th>City</th>
              <th>Province</th>
              <th>Last Scanned</th>
              <th>Active</th>
              <th style="width: 460px;">Actions</th>
            </tr>
          </thead>
          <tbody>
            @foreach($gridCells as $cell)
              @php
                $editSelectedProvince = (string) ($cell->province ?? '');
                $editSelectedCity = (string) ($cell->city ?? '');
                $editCityOptions = $editSelectedProvince !== ''
                  ? ($gridCityOptionsByProvince[$editSelectedProvince] ?? [])
                  : $gridCityOptionsAll;
              @endphp
              <tr>
                <td>{{ $cell->name }}</td>
                <td>{{ $cell->center_lat }}</td>
                <td>{{ $cell->center_lng }}</td>
                <td>{{ $cell->radius_m }}</td>
                <td>{{ $cell->city ?: '-' }}</td>
                <td>{{ $cell->province ?: '-' }}</td>
                <td>{{ $cell->last_scanned_at?->format('d M Y H:i') ?: '-' }}</td>
                <td>{{ $cell->is_active ? 'Yes' : 'No' }}</td>
                <td>
                  <div class="d-flex gap-1">
                    <form method="post" action="{{ route('admin.lead-discovery.grid-cells.update', $cell) }}" class="d-flex gap-1 flex-grow-1">
                      @csrf
                      @method('PUT')
                      <input type="text" name="name" class="form-control form-control-sm" value="{{ $cell->name }}" required>
                      <input type="number" step="0.0000001" name="center_lat" class="form-control form-control-sm" value="{{ $cell->center_lat }}" required>
                      <input type="number" step="0.0000001" name="center_lng" class="form-control form-control-sm" value="{{ $cell->center_lng }}" required>
                      <input type="number" name="radius_m" class="form-control form-control-sm" value="{{ $cell->radius_m }}" required>
                      <select name="province" class="form-select form-select-sm ld-grid-province" data-city-target="ld-grid-city-{{ $cell->id }}">
                        <option value="">Province (optional)</option>
                        @foreach($gridProvinceOptions as $provinceOption)
                          <option value="{{ $provinceOption }}" @selected($editSelectedProvince === $provinceOption)>{{ $provinceOption }}</option>
                        @endforeach
                      </select>
                      <select name="city" id="ld-grid-city-{{ $cell->id }}" class="form-select form-select-sm ld-grid-city" data-selected="{{ $editSelectedCity }}">
                        <option value="">City (optional)</option>
                        @foreach($editCityOptions as $cityOption)
                          <option value="{{ $cityOption }}" @selected($editSelectedCity === $cityOption)>{{ $cityOption }}</option>
                        @endforeach
                      </select>
                      <input type="text" name="region_code" class="form-control form-control-sm" value="{{ $cell->region_code }}">
                      <label class="form-check mt-1">
                        <input type="checkbox" class="form-check-input" name="is_active" value="1" @checked($cell->is_active)>
                      </label>
                      <button class="btn btn-sm btn-outline-primary">Save</button>
                    </form>
                    <form method="post" action="{{ route('admin.lead-discovery.grid-cells.toggle-active', $cell) }}">
                      @csrf
                      <button class="btn btn-sm btn-outline-secondary">{{ $cell->is_active ? 'Disable' : 'Enable' }}</button>
                    </form>
                    <form method="post" action="{{ route('admin.lead-discovery.grid-cells.destroy', $cell) }}" onsubmit="return confirm('Delete grid cell ini?')">
                      @csrf
                      @method('DELETE')
                      <button class="btn btn-sm btn-danger">Delete</button>
                    </form>
                  </div>
                </td>
              </tr>
            @endforeach
          </tbody>
        </table>
      </div>
      <div class="card-footer">
        {{ $gridCells->links() }}
      </div>
    </div>
  @endif
</div>
@endsection

@push('scripts')
  <script id="ld-grid-city-map" type="application/json">@json(['__all' => $gridCityOptionsAll] + $gridCityOptionsByProvince)</script>
  <script>
    document.addEventListener('DOMContentLoaded', function () {
      const cityMapNode = document.getElementById('ld-grid-city-map');
      if (!cityMapNode) {
        return;
      }

      let cityMap = {};
      try {
        cityMap = JSON.parse(cityMapNode.textContent || '{}');
      } catch (e) {
        cityMap = {};
      }

      function rebuildCityOptions(citySelect, provinceValue, selectedCity) {
        const source = provinceValue && cityMap[provinceValue]
          ? cityMap[provinceValue]
          : (cityMap.__all || []);
        const allowed = new Set(source);
        const cityValue = allowed.has(selectedCity) ? selectedCity : '';

        citySelect.innerHTML = '';

        const defaultOption = document.createElement('option');
        defaultOption.value = '';
        defaultOption.textContent = 'City (optional)';
        citySelect.appendChild(defaultOption);

        source.forEach(function (city) {
          const option = document.createElement('option');
          option.value = city;
          option.textContent = city;
          if (city === cityValue) {
            option.selected = true;
          }
          citySelect.appendChild(option);
        });
      }

      document.querySelectorAll('.ld-grid-province').forEach(function (provinceSelect) {
        const cityTargetId = provinceSelect.dataset.cityTarget;
        if (!cityTargetId) {
          return;
        }

        const citySelect = document.getElementById(cityTargetId);
        if (!citySelect) {
          return;
        }

        rebuildCityOptions(citySelect, provinceSelect.value, citySelect.dataset.selected || '');

        provinceSelect.addEventListener('change', function () {
          rebuildCityOptions(citySelect, provinceSelect.value, '');
        });
      });
    });
  </script>
@endpush
