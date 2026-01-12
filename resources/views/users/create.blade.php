@extends('layouts.tabler')

@section('content')
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

        <div class="row g-3">
          <div class="col-md-8">
            <div class="mb-3">
              <label class="form-label">Role</label>
              <select name="role" class="form-select" required>
                @foreach(($roles ?? []) as $role)
                  <option value="{{ $role->name }}" @selected(old('role', 'Sales') === $role->name)>
                    {{ $role->name }}
                  </option>
                @endforeach
              </select>
              <small class="text-muted">Role diambil dari database (Spatie).</small>
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
          <small class="text-muted">Kosongkan untuk kirim link set password (jika fitur invite dipakai).</small>
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
