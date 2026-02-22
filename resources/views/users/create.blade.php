@extends('layouts.tabler')

@section('content')
@php
  $selectedRoles = collect(old('roles', old('role') ? [old('role')] : ['Sales']))
    ->filter(fn ($role) => is_string($role) && trim($role) !== '')
    ->map(fn ($role) => trim($role))
    ->values()
    ->all();
@endphp
<div class="container-xl">
  <div class="card">
    <div class="card-header">
      <h3 class="card-title">Add User</h3>
    </div>

    <form method="POST" action="{{ route('users.store') }}" enctype="multipart/form-data">
      @csrf

      <div class="card-body">
        @if ($errors->any())
          <div class="alert alert-danger">
            <ul class="mb-0">
              @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
              @endforeach
            </ul>
          </div>
        @endif

        <div class="mb-3">
          <label class="form-label">Nama</label>
          <input type="text" name="name" value="{{ old('name') }}" class="form-control" required>
        </div>

        <div class="mb-3">
          <label class="form-label">Email</label>
          <input type="email" name="email" value="{{ old('email') }}" class="form-control" required>
        </div>

        <div class="mb-3">
          <label class="form-label">No. HP (opsional)</label>
          <input type="text" name="phone" value="{{ old('phone') }}" class="form-control" maxlength="30">
        </div>

        <div class="row g-3">
          <div class="col-md-8">
            <div class="mb-3">
              <label class="form-label">Roles</label>
              <div class="row g-2">
                @foreach(($roles ?? []) as $roleName)
                  <div class="col-md-4 col-sm-6">
                    <label class="form-check">
                      <input
                        class="form-check-input role-checkbox"
                        type="checkbox"
                        name="roles[]"
                        value="{{ $roleName }}"
                        data-role-name="{{ $roleName }}"
                        @checked(in_array($roleName, $selectedRoles, true))
                      >
                      <span class="form-check-label">{{ $roleName }}</span>
                    </label>
                  </div>
                @endforeach
              </div>
              @error('roles')<div class="text-danger small">{{ $message }}</div>@enderror
              @error('roles.*')<div class="text-danger small">{{ $message }}</div>@enderror
              <small class="text-muted d-block">Role diambil dari database (Spatie). Admin bersifat eksklusif.</small>
            </div>
          </div>

          <div class="col-md-4 d-flex align-items-center">
            <label class="form-check form-switch mt-4">
              <input class="form-check-input" type="checkbox" name="is_active" value="1" @checked(old('is_active', 1))>
              <span class="form-check-label">Active</span>
            </label>
          </div>
        </div>

        <div class="mb-3">
          <label class="form-label">Password (opsional)</label>
          <input type="password" name="password" class="form-control" autocomplete="new-password">
          <small class="text-muted">Kosongkan untuk kirim link set password (jika invite dipakai).</small>
        </div>

        <div class="mb-3">
          <label class="form-label">Avatar (opsional)</label>
          <input type="file" name="avatar" class="form-control" accept=".jpg,.jpeg,.png,.webp">
        </div>

        <div class="mb-3">
          <label class="form-label">Email Signature (opsional)</label>
          <textarea name="email_signature" class="form-control" rows="4">{{ old('email_signature') }}</textarea>
        </div>

        <label class="form-check">
          <input class="form-check-input" type="checkbox" name="send_invite" value="1" @checked(old('send_invite'))>
          <span class="form-check-label">Kirim undangan (reset password link)</span>
        </label>
      </div>

      <div class="card-footer d-flex justify-content-between">
        <a href="{{ route('users.index') }}" class="btn btn-light">Batal</a>
        <button type="submit" class="btn btn-primary">Simpan</button>
      </div>
    </form>
  </div>
</div>
@endsection

@push('scripts')
<script>
  (function () {
    const boxes = Array.from(document.querySelectorAll('.role-checkbox'));
    if (!boxes.length) return;

    const adminBox = boxes.find((el) => (el.dataset.roleName || '').toLowerCase() === 'admin');
    if (!adminBox) return;

    const syncAdminExclusivity = () => {
      const adminChecked = adminBox.checked;
      boxes.forEach((box) => {
        if (box === adminBox) return;
        if (adminChecked) {
          box.checked = false;
          box.disabled = true;
        } else {
          box.disabled = false;
        }
      });
    };

    boxes.forEach((box) => {
      box.addEventListener('change', () => {
        if (box === adminBox && adminBox.checked) {
          boxes.forEach((other) => {
            if (other !== adminBox) other.checked = false;
          });
        }
        syncAdminExclusivity();
      });
    });

    syncAdminExclusivity();
  })();
</script>
@endpush
