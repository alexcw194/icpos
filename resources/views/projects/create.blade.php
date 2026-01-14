@extends('layouts.tabler')

@section('content')
<div class="container-xl">
  <div class="page-header d-print-none">
    <div class="row align-items-center">
      <div class="col">
        <h2 class="page-title">Create Project</h2>
        <div class="text-muted">Project master setup</div>
      </div>
    </div>
  </div>

  <form method="POST" action="{{ route('projects.store') }}">
    @csrf

    @include('projects._form', ['project' => new \App\Models\Project()])

    @include('layouts.partials.form_footer', [
      'cancelUrl' => route('projects.index'),
      'cancelLabel' => 'Batal',
      'cancelInline' => true,
      'buttons' => [
        ['type' => 'submit', 'label' => 'Simpan', 'class' => 'btn btn-primary']
      ]
    ])
  </form>
</div>
@endsection
