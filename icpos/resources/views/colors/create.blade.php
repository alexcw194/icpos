@extends('layouts.tabler')

@section('content')
<div class="container-xl">
  <form action="{{ route('colors.store') }}" method="POST" class="card">
    @csrf
    <div class="card-header"><div class="card-title">Add Color</div></div>

    @include('colors._form')

    @include('layouts.partials.form_footer', [
      'cancelUrl'   => route('colors.index'),
      'cancelLabel' => 'Batal',
      'cancelInline'=> true,
      'buttons'     => [
        ['type'=>'submit','label'=>'Simpan','class'=>'btn btn-primary']
      ],
    ])
  </form>
</div>
@endsection
