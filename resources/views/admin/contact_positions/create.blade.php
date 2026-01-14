@extends('layouts.tabler')

@section('content')
<div class="container-xl">
  <div class="page-header d-print-none">
    <div class="row align-items-center">
      <div class="col">
        <h2 class="page-title">Contact Positions</h2>
        <div class="text-muted">Create new position</div>
      </div>
    </div>
  </div>

  <form method="POST" action="{{ route('contact-positions.store') }}">
    @csrf
    @include('admin.contact_positions._form')

    @include('layouts.partials.form_footer', [
      'cancelUrl' => route('contact-positions.index'),
      'cancelLabel' => 'Batal',
      'cancelInline' => true,
      'buttons' => [
        ['type' => 'submit', 'label' => 'Simpan', 'class' => 'btn btn-primary']
      ]
    ])
  </form>
</div>
@endsection
