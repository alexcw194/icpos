@extends('layouts.tabler')

@section('content')
<div class="container-xl">
  <div class="card">
    <div class="card-header"><h3 class="card-title">Manage Users</h3></div>
    <div class="table-responsive">
      <table class="table card-table">
        <thead><tr>
          <th>Nama</th><th>Email</th><th>Roles</th><th></th>
        </tr></thead>
        <tbody>
          @foreach($users as $u)
            <tr>
              <td>{{ $u->name }}</td>
              <td>{{ $u->email }}</td>
              <td>{{ $u->getRoleNames()->implode(', ') ?: '—' }}</td>
              <td class="text-end">
                <a href="{{ route('users.edit',$u) }}" class="btn btn-sm btn-primary">Edit</a>
              </td>
            </tr>
          @endforeach
        </tbody>
      </table>
    </div>
    <div class="card-footer">{{ $users->links() }}</div>
  </div>
</div>
@endsection
