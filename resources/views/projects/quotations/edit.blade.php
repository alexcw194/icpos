@extends('layouts.tabler')

@section('content')
<div class="container-xl">
  <div class="page-header d-print-none">
    <div class="row align-items-center">
      <div class="col">
        <div class="page-pretitle">{{ $project->code }}</div>
        <h2 class="page-title">Edit Project Quotation</h2>
        <div class="text-muted">{{ $quotation->number }}</div>
      </div>
    </div>
  </div>

  <form method="POST" action="{{ route('projects.quotations.update', [$project, $quotation]) }}">
    @csrf
    @method('PUT')

    @include('projects.quotations._form')

    @include('layouts.partials.form_footer', [
      'cancelUrl' => route('projects.quotations.show', [$project, $quotation]),
      'cancelLabel' => 'Batal',
      'cancelInline' => true,
      'buttons' => [
        ['type' => 'submit', 'label' => 'Simpan', 'class' => 'btn btn-primary']
      ]
    ])
  </form>
</div>
@endsection
