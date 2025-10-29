@extends('layouts.tabler')
@section('content')
<div class="container-xl">
  <div class="d-flex justify-content-between align-items-center mb-2">
    <h2 class="mb-0">Manage Users</h2>
    <a href="{{ route('users.create') }}" class="btn btn-primary">Add User</a>
  </div>
  @if(session('ok')) <div class="alert alert-success">{{ session('ok') }}</div> @endif

  <div class="card">
    <div class="table-responsive">
      <table class="table table-vcenter">
        <thead><tr><th>Nama</th><th>Email</th><th>Roles</th><th>Last Login</th><th class="w-1">Active</th><th class="w-1"></th></tr></thead>
        <tbody>
          @forelse($users as $u)
            <tr>
              <td class="d-flex align-items-center gap-2">
                @php $av = $u->profile_image_path ? asset('storage/'.$u->profile_image_path) : null; @endphp
                <span class="avatar" @if($av) style="background-image:url('{{ $av }}')" @endif>
                  @unless($av) {{ strtoupper(substr($u->name,0,1)) }} @endunless
                </span>
                <span>{{ $u->name }}</span>
              </td>
              <td><a href="mailto:{{ $u->email }}">{{ $u->email }}</a></td>
              <td>{{ $u->getRoleNames()->implode(', ') }}</td>
              <td>{{ optional($u->last_login_at)->diffForHumans() ?? '—' }}</td>
              <td><label class="form-check form-switch mb-0"><input class="form-check-input" type="checkbox" disabled @checked($u->is_active)></label></td>
              <td class="text-nowrap">
                <a href="{{ route('users.edit',$u) }}" class="btn btn-sm">Edit</a>
              </td>
            </tr>
          @empty
            <tr><td colspan="6" class="text-muted">Belum ada user.</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>
    <div class="card-footer">{{ $users->links() }}</div>
  </div>
</div>
@endsection
