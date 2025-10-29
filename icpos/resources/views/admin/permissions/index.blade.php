@extends('layouts.tabler')

@section('content')
<div class="container-xl">
  <div class="card">
    <div class="card-header"><h3 class="card-title">Roles &amp; Permissions</h3></div>
    <div class="card-body">
      <div class="row g-3">
        <div class="col-md-6">
          <h4>Roles</h4>
          <ul class="list-group">
            @foreach($roles as $r)
              <li class="list-group-item">
                <div class="fw-bold">{{ $r->name }}</div>
                <div class="text-muted small">
                  {{ $r->permissions->pluck('name')->implode(', ') ?: '—' }}
                </div>
              </li>
            @endforeach
          </ul>
        </div>
        <div class="col-md-6">
          <h4>All Permissions</h4>
          <div class="card p-2">
            <div class="small">
              {{ $perms->pluck('name')->implode(', ') }}
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>
@endsection
