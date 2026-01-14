<div class="card">
  <div class="card-body">
    <div class="row g-3">
      <div class="col-md-6">
        <label class="form-label">Name</label>
        <input type="text" name="name" class="form-control" value="{{ old('name', $position->name ?? '') }}" required>
      </div>
      <div class="col-md-3">
        <label class="form-label">Sort Order</label>
        <input type="number" name="sort_order" class="form-control" value="{{ old('sort_order', $position->sort_order ?? 0) }}">
      </div>
      <div class="col-md-3">
        <label class="form-label">Active</label>
        <select name="is_active" class="form-select">
          <option value="1" @selected(old('is_active', $position->is_active ?? true))>Yes</option>
          <option value="0" @selected(!old('is_active', $position->is_active ?? true))>No</option>
        </select>
      </div>
    </div>
  </div>
</div>
