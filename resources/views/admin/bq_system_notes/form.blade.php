@extends('layouts.tabler')

@section('content')
<div class="page-header d-print-none">
  <div class="container-xl">
    <h2 class="page-title">{{ $row->exists ? 'Edit' : 'Tambah' }} BQ Systems Notes</h2>
  </div>
</div>

<div class="page-body">
  <div class="container-xl">
    <form class="card" method="post" action="{{ $row->exists ? route('bq-system-notes.update', $row) : route('bq-system-notes.store') }}">
      @csrf
      @if($row->exists) @method('PUT') @endif

      <div class="card-body">
        @if ($errors->any())
          <div class="alert alert-danger">
            <div class="fw-bold mb-1">Periksa kembali input Anda:</div>
            <ul class="mb-0">
              @foreach ($errors->all() as $error) <li>{{ $error }}</li> @endforeach
            </ul>
          </div>
        @endif

        <div class="row g-3">
          <div class="col-md-6">
            <label class="form-label">System <span class="text-danger">*</span></label>
            <select name="system_key" class="form-select @error('system_key') is-invalid @enderror" required>
              <option value="">-- pilih --</option>
              @foreach($systems as $key => $label)
                <option value="{{ $key }}" @selected(old('system_key', $row->system_key) === $key)>
                  {{ $label }}
                </option>
              @endforeach
            </select>
            @error('system_key') <div class="invalid-feedback">{{ $message }}</div> @enderror
          </div>
          <div class="col-md-6">
            <label class="form-label d-block">Status</label>
            <input type="hidden" name="is_active" value="0">
            <label class="form-check form-switch">
              <input class="form-check-input" type="checkbox" name="is_active" value="1"
                     @checked(old('is_active', $row->exists ? (int)$row->is_active : 1) == 1)>
              <span class="form-check-label">Active</span>
            </label>
          </div>
        </div>

        <div class="mt-3">
          <label class="form-label">Notes Template</label>
          <textarea name="notes_template" class="form-control" rows="6" placeholder="Tulis template notes per system...">{{ old('notes_template', $row->notes_template) }}</textarea>
          <div class="form-hint">Notes ini akan dipakai sebagai template awal di BQ (tetap bisa diedit).</div>
        </div>
      </div>

      @include('layouts.partials.form_footer', [
        'cancelUrl'    => route('bq-system-notes.index'),
        'cancelLabel'  => 'Batal',
        'cancelInline' => true,
        'buttons'      => [
          ['type' => 'submit', 'label' => 'Simpan', 'class' => 'btn btn-primary'],
        ],
      ])
    </form>
  </div>
</div>
@endsection
