@extends('layouts.tabler')

@section('content')
<div class="container-xl">
  <form method="post" action="{{ route('items.variants.store', $item) }}" class="card">
    @csrf
    <div class="card-header">
      <div class="card-title">Add Variant - {{ $item->name }}</div>
      <div class="ms-auto btn-list">
        <a href="{{ route('items.variants.index', $item) }}" class="btn btn-secondary">Cancel</a>
        <button class="btn btn-primary">Save</button>
      </div>
    </div>
    <div class="card-body">
      @include('items.variants._form')
    </div>
  </form>
</div>
@endsection
