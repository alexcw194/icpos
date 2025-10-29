@extends('layouts.tabler')

@section('content')
<div class="container-xl">
  <form action="{{ route('sizes.update', $size) }}" method="POST" class="card">
    @csrf @method('PUT')
    <div class="card-header"><div class="card-title">Edit Size</div></div>

    @include('sizes._form', ['size'=>$size])

    @include('layouts.partials.form_footer', [
      'cancelUrl'   => route('sizes.index'),
      'cancelLabel' => 'Batal',
      'cancelInline'=> true,
      'buttons'     => [
        ['type'=>'submit','label'=>'Update','class'=>'btn btn-primary']
      ],
    ])
  </form>
</div>
@endsection
