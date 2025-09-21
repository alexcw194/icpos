@extends('layouts.tabler')
@section('content')
<div class="container-xl">
  <form class="card" method="POST" action="{{ route('companies.update',$company) }}" enctype="multipart/form-data">
    @csrf @method('PUT')
    <div class="card-header">
      <div class="card-title">Edit Company: {{ $company->alias ?? $company->name }}</div>
      <a href="{{ route('companies.index') }}" class="btn btn-secondary ms-auto">Kembali</a>
    </div>
    <div class="card-body">
      @include('companies._form', ['company' => $company])
    </div>
    @include('layouts.partials.form_footer', [
      'cancelUrl'    => route('companies.index'),
      'cancelLabel'  => 'Batal',
      'cancelInline' => true,
      'buttons'      => [
        ['type' => 'submit', 'label' => 'Simpan', 'class' => 'btn btn-primary'],
      ],
    ])
  </form>
</div>
@endsection
