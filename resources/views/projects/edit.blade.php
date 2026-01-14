@extends('layouts.tabler')

@section('content')
<div class="container-xl">
  <div class="page-header d-print-none">
    <div class="row align-items-center">
      <div class="col">
        <h2 class="page-title">Edit Project</h2>
        <div class="text-muted">{{ $project->code }}</div>
      </div>
    </div>
  </div>

  <form method="POST" action="{{ route('projects.update', $project) }}">
    @csrf
    @method('PUT')

    @include('projects._form')

    @include('layouts.partials.form_footer', [
      'cancelUrl' => route('projects.show', $project),
      'cancelLabel' => 'Batal',
      'cancelInline' => true,
      'buttons' => [
        ['type' => 'submit', 'label' => 'Simpan', 'class' => 'btn btn-primary']
      ]
    ])
  </form>
</div>
@endsection
