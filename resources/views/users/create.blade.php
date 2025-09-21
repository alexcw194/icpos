@extends('layouts.tabler')
@section('content')
<div class="container-xl">
  <form action="{{ route('users.store') }}" method="POST" enctype="multipart/form-data" class="card">
    @csrf
    <div class="card-header"><div class="card-title">Add User</div></div>
    <div class="card-body">
      <div class="row g-3">
        <div class="col-md-6"><label class="form-label">Nama</label><input type="text" name="name" value="{{ old('name') }}" class="form-control" required>@error('name')<div class="text-danger small">{{ $message }}</div>@enderror</div>
        <div class="col-md-6"><label class="form-label">Email</label><input type="email" name="email" value="{{ old('email') }}" class="form-control" required>@error('email')<div class="text-danger small">{{ $message }}</div>@enderror</div>
        <div class="col-md-4"><label class="form-label">Role</label><select name="role" class="form-select" required>@foreach(['Admin','Sales','Finance'] as $r)<option value="{{ $r }}" @selected(old('role')===$r)>{{ $r }}</option>@endforeach</select></div>
        <div class="col-md-4 d-flex align-items-end"><label class="form-check form-switch"><input class="form-check-input" type="checkbox" name="is_active" value="1" {{ old('is_active',1) ? 'checked' : '' }}><span class="form-check-label">Active</span></label></div>
        <div class="col-md-4"><label class="form-label">Profile Image</label><input type="file" name="avatar" class="form-control" accept="image/*">@error('avatar')<div class="text-danger small">{{ $message }}</div>@enderror</div>
        <div class="col-12"><label class="form-label">Email Signature (opsional)</label><textarea name="email_signature" class="form-control" rows="3">{{ old('email_signature') }}</textarea></div>
        <div class="col-md-6"><label class="form-label">Password (opsional)</label><input type="password" name="password" class="form-control" autocomplete="new-password"><small class="form-hint">Kosongkan untuk kirim link set password.</small></div>
        <div class="col-md-6 d-flex align-items-end"><label class="form-check"><input class="form-check-input" type="checkbox" name="send_invite" value="1" {{ old('send_invite',1) ? 'checked' : '' }}><span class="form-check-label">Kirim link set password</span></label></div>
      </div>
    </div>
    <div class="card-footer text-end"><a href="{{ route('users.index') }}" class="btn">Batal</a><button class="btn btn-primary">Simpan</button></div>
  </form>
</div>
@endsection
