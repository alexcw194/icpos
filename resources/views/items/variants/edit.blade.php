@extends('layouts.tabler')

@section('content')
<div class="container-xl">
  <form method="post" action="{{ route('variants.update', $variant) }}" class="card">
    @csrf @method('PUT')
    <div class="card-header">
      <div class="card-title">Edit Variant - {{ $item->name }}</div>
      <div class="ms-auto btn-list">
        <a href="{{ route('items.variants.index', $item) }}" class="btn btn-secondary">Back</a>
        <button class="btn btn-primary">Update</button>
      </div>
    </div>
    <div class="card-body">
      @include('items.variants._form')
    </div>
  </form>
</div>
@endsection
