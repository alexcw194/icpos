@extends('layouts.tabler')

@section('content')
<div class="container-xl">
  <form action="{{ route('sizes.store') }}" method="POST" class="card">
    @csrf
    <div class="card-header"><div class="card-title">Add Size</div></div>

    @include('sizes._form')

    @include('layouts.partials.form_footer', [
      'cancelUrl'   => route('sizes.index'),
      'cancelLabel' => 'Batal',
      'cancelInline'=> true,
      'buttons'     => [
        ['type'=>'submit','label'=>'Simpan','class'=>'btn btn-primary']
      ],
    ])
  </form>
</div>
@endsection
