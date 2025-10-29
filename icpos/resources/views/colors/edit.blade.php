@extends('layouts.tabler')

@section('content')
<div class="container-xl">
  <form action="{{ route('colors.update', $color) }}" method="POST" class="card">
    @csrf @method('PUT')
    <div class="card-header"><div class="card-title">Edit Color</div></div>

    @include('colors._form', ['color'=>$color])

    @include('layouts.partials.form_footer', [
      'cancelUrl'   => route('colors.index'),
      'cancelLabel' => 'Batal',
      'cancelInline'=> true,
      'buttons'     => [
        ['type'=>'submit','label'=>'Update','class'=>'btn btn-primary']
      ],
    ])
  </form>
</div>
@endsection
