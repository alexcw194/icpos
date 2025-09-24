@extends('layouts.tabler')

@section('content')
<div class="container-xl">
  <form method="POST" action="{{ route('deliveries.store') }}">
    @csrf
    <div class="card">
      <div class="card-header d-flex justify-content-between align-items-center">
        <h2 class="card-title mb-0">New Delivery Order</h2>
        <a href="{{ route('deliveries.index') }}" class="btn btn-outline-secondary">Cancel</a>
      </div>
      <div class="card-body">
        @include('deliveries._form')
      </div>
      <div class="card-footer d-flex justify-content-end gap-2">
        <button type="submit" class="btn btn-primary">Save Draft</button>
      </div>
    </div>
  </form>
</div>
@endsection
