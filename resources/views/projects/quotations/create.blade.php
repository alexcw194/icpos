@extends('layouts.tabler')

@section('content')
<div class="container-xl">
  <div class="page-header d-print-none">
    <div class="row align-items-center">
      <div class="col">
        <div class="page-pretitle">{{ $project->code }}</div>
        <h2 class="page-title">Create Project Quotation (BQ)</h2>
      </div>
    </div>
  </div>

  <form method="POST" action="{{ route('projects.quotations.store', $project) }}">
    @csrf

    @include('projects.quotations._form')

    @include('layouts.partials.form_footer', [
      'cancelUrl' => route('projects.show', [$project, 'tab' => 'quotations']),
      'cancelLabel' => 'Batal',
      'cancelInline' => true,
      'buttons' => [
        ['type' => 'submit', 'label' => 'Simpan', 'class' => 'btn btn-primary']
      ]
    ])
  </form>
</div>
@endsection
