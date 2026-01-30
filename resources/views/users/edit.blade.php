@extends('layouts.tabler')

@section('content')
@php
  $avatarUrl = $user->profile_image_path ? asset('storage/'.$user->profile_image_path) : null;
@endphp

<div class="container-xl">
  <form action="{{ route('users.update', $user) }}" method="POST" enctype="multipart/form-data" class="card" id="userEditForm">
    @csrf
    @method('PUT')

    <div class="card-header d-flex align-items-center">
      <span class="avatar avatar-sm rounded-circle me-2"
            @if($avatarUrl) style="background-image:url('{{ $avatarUrl }}')" @endif>
        @unless($avatarUrl)
          {{-- fallback silhouette --}}
          <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20"
               viewBox="0 0 24 24" fill="currentColor" class="opacity-50">
            <path d="M12 12c2.761 0 5-2.239 5-5S14.761 2 12 2 7 4.239 7 7s2.239 5 5 5zm0 2c-3.866 0-7 3.134-7 7h14c0-3.866-3.134-7-7-7z"/>
          </svg>
        @endunless
      </span>
      <div class="card-title mb-0">Edit User: {{ $user->name }}</div>
    </div>

    <div class="card-body">
      <div class="row g-3">

        <div class="col-md-6">
          <label class="form-label">Nama</label>
          <input type="text" name="name" value="{{ old('name',$user->name) }}" class="form-control" required>
          @error('name')<div class="text-danger small">{{ $message }}</div>@enderror
        </div>

        <div class="col-md-6">
          <label class="form-label">Email</label>
          <input type="email" name="email" value="{{ old('email',$user->email) }}" class="form-control" required>
          @error('email')<div class="text-danger small">{{ $message }}</div>@enderror
        </div>

        <div class="col-md-6">
          <label class="form-label">No. HP (opsional)</label>
          <input type="text" name="phone" value="{{ old('phone',$user->phone) }}" class="form-control" maxlength="30">
          @error('phone')<div class="text-danger small">{{ $message }}</div>@enderror
        </div>

        <div class="col-md-4">
          <label class="form-label">Role</label>
          <select name="role" class="form-select" required>
            @foreach($roles as $r)
              <option value="{{ $r }}" @selected(old('role',$user->getRoleNames()->first())===$r)>{{ $r }}</option>
            @endforeach
          </select>
          @error('role')<div class="text-danger small">{{ $message }}</div>@enderror
        </div>

        <div class="col-md-4 d-flex align-items-end">
          <label class="form-check form-switch">
            <input class="form-check-input" type="checkbox" name="is_active" value="1" {{ old('is_active',$user->is_active) ? 'checked' : '' }}>
            <span class="form-check-label">Active</span>
          </label>
        </div>

        <div class="col-md-4">
          <label class="form-label">Profile Image</label>
          <input type="file" name="avatar" class="form-control" accept="image/*">
          @if($avatarUrl)
            <div class="small text-muted mt-1">Saat ini: <a href="{{ $avatarUrl }}" target="_blank">lihat</a></div>
          @endif
          @error('avatar')<div class="text-danger small">{{ $message }}</div>@enderror
        </div>

        <div class="col-12">
          <label class="form-label">Email Signature (opsional)</label>
          <textarea name="email_signature" class="form-control" rows="3">{{ old('email_signature',$user->email_signature) }}</textarea>
          @error('email_signature')<div class="text-danger small">{{ $message }}</div>@enderror
        </div>

        <div class="col-md-6">
          <label class="form-label">Set Password Baru (opsional)</label>
          <input type="password" name="password" class="form-control" autocomplete="new-password">
          <small class="form-hint">Jika diisi, user akan diminta ganti password saat login.</small>
          @error('password')<div class="text-danger small">{{ $message }}</div>@enderror
        </div>

        <div class="col-md-6 d-flex align-items-end">
          <label class="form-check">
            <input class="form-check-input" type="checkbox" name="send_invite" value="1">
            <span class="form-check-label">Kirim link set password ke email</span>
          </label>
        </div>

      </div>
    </div>

    <div class="card-footer text-end">
      <a href="{{ route('users.index') }}" class="btn">Batal</a>
      <button class="btn btn-primary">Simpan</button>
    </div>
  </form>
</div>
@endsection

@push('scripts')
<script>
  // Preview avatar langsung saat pilih file
  (function(){
    const form = document.getElementById('userEditForm');
    if(!form) return;
    const file = form.querySelector('input[name="avatar"]');
    const avatar = form.closest('.card').querySelector('.avatar');
    if(!file || !avatar) return;

    file.addEventListener('change', (e) => {
      const f = e.target.files?.[0];
      if(!f) return;
      const reader = new FileReader();
      reader.onload = ev => { avatar.style.backgroundImage = `url('${ev.target.result}')`; };
      reader.readAsDataURL(f);
    });
  })();
</script>
@endpush
