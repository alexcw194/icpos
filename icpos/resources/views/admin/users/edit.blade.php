{{-- resources/views/admin/users/edit.blade.php --}}
@extends('layouts.tabler')

@section('content')
<div class="container-xl">
  <form class="card" method="POST" action="{{ route('users.update', $user) }}">
    @csrf
    @method('PUT')

    <div class="card-header">
      <div class="card-title">Edit Roles: {{ $user->name }}</div>
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

      <div class="mb-3"><strong>Email:</strong> {{ $user->email }}</div>

      <div class="row g-2">
        @foreach($roles as $id => $name)
          <div class="col-md-3">
            <label class="form-check">
              <input
                class="form-check-input"
                type="checkbox"
                name="roles[]"
                value="{{ $id }}"
                @checked(in_array($name, $userRoles))
              >
              <span class="form-check-label">{{ $name }}</span>
            </label>
          </div>
        @endforeach
      </div>
    </div>

    {{-- Footer standar ICPOS --}}
    @include('layouts.partials.form_footer', [
      'cancelUrl'    => route('users.index'),
      'cancelLabel'  => 'Batal',
      'cancelInline' => true, // tampil: Batal | Simpan di sisi kanan
      'buttons'      => [
        ['type' => 'submit', 'label' => 'Simpan', 'class' => 'btn btn-primary'],
      ],
    ])
  </form>
</div>
@endsection
