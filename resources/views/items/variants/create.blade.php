@extends('layouts.tabler')

@section('content')
<div class="container-xl">
  <form method="POST" action="{{ route('items.variants.store', $item) }}">
    @csrf
    <div class="card">
      <div class="card-header">
        <div class="card-title">Tambah Varian - {{ $item->name }}</div>
        <div class="ms-auto btn-list">
          <a href="{{ route('items.variants.index', $item) }}" class="btn btn-secondary">Batal</a>
          <button type="submit" class="btn btn-primary">Simpan</button>
        </div>
      </div>

      @include('items.variants._form')
    </div>
  </form>
</div>
@endsection
