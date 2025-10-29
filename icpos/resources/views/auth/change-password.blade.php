@extends('layouts.tabler')

@section('content')
<div class="container-sm" style="max-width:560px">
  <form action="{{ route('password.update') }}" method="POST" class="card">
    @csrf
    <div class="card-header"><div class="card-title">Change Password</div></div>

    <div class="card-body">
      @if ($errors->updatePassword->any())
        <div class="alert alert-danger">
          <ul class="mb-0">
            @foreach ($errors->updatePassword->all() as $e)
              <li>{{ $e }}</li>
            @endforeach
          </ul>
        </div>
      @endif

      @if (session('ok'))
        <div class="alert alert-success">{{ session('ok') }}</div>
      @endif

      @unless (auth()->user()->must_change_password)
        <div class="mb-3">
          <label class="form-label">Current Password</label>
          <input type="password" name="current_password" class="form-control" required autocomplete="current-password">
        </div>
      @endunless

      <div class="mb-3">
        <label class="form-label">New Password</label>
        <input type="password" name="password" class="form-control" required autocomplete="new-password">
      </div>

      <div class="mb-3">
        <label class="form-label">Confirm New Password</label>
        <input type="password" name="password_confirmation" class="form-control" required autocomplete="new-password">
      </div>
    </div>

    <div class="card-footer text-end">
      <a href="{{ route('dashboard') }}" class="btn">Batal</a>
      <button class="btn btn-primary">Update</button>
    </div>
  </form>
</div>
@endsection
