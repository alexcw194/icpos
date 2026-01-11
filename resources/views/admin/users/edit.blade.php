{{-- resources/views/admin/users/edit.blade.php --}}
@extends('layouts.tabler')

@section('content')
<div class="container-xl">
  <form class="card" method="POST" action="{{ route('users.update', $user) }}" enctype="multipart/form-data">
    @csrf
    @method('PUT')

    <div class="card-header">
      <div class="card-title">Edit User: {{ $user->name }}</div>
    </div>

    <div class="card-body">
      @if ($errors->any())
        <div class="alert alert-danger">
          <div class="fw-bold mb-1">Periksa kembali input Anda:</div>
          <ul class="mb-0">
            @foreach ($errors->all() as $error)
              <li>{{ $error }}</li>
            @endforeach
          </ul>
        </div>
      @endif

      <div class="row g-3">
        <div class="col-md-6">
          <label class="form-label">Nama</label>
          <input class="form-control" name="name" value="{{ old('name', $user->name) }}" required>
        </div>

        <div class="col-md-6">
          <label class="form-label">Email</label>
          <input class="form-control" name="email" type="email" value="{{ old('email', $user->email) }}" required>
        </div>

        <div class="col-md-6">
          <label class="form-label">Role</label>
          <select class="form-select" name="role" required>
            @foreach($roles as $name)
              <option value="{{ $name }}" @selected(old('role', $currentRole) === $name)>{{ $name }}</option>
            @endforeach
          </select>
        </div>

        <div class="col-md-6 d-flex align-items-end">
          <label class="form-check mb-0">
            <input class="form-check-input" type="checkbox" name="is_active" value="1" @checked(old('is_active', $user->is_active))>
            <span class="form-check-label">Active</span>
          </label>
        </div>
      </div>
    </div>

    @include('layouts.partials.form_footer', [
      'cancelUrl'    => route('users.index'),
      'cancelLabel'  => 'Batal',
      'cancelInline' => true,
      'buttons'      => [
        ['type' => 'submit', 'label' => 'Simpan', 'class' => 'btn btn-primary'],
      ],
    ])
  </form>
</div>
@endsection
