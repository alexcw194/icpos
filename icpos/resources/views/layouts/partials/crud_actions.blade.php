{{-- Seragam: View / Edit / Delete --}}
@php
  $view    = $view    ?? null;   // url
  $edit    = $edit    ?? null;   // url
  $delete  = $delete  ?? null;   // url (route destroy)
  $size    = ($size ?? 'sm') === 'sm' ? 'btn-sm' : '';
  $confirm = $confirm ?? 'Are you sure to delete this record?';
@endphp

<div class="btn-list">
  @if($view)
    <a href="{{ $view }}" class="btn {{ $size }} btn-primary">View</a>
  @endif

  @if($edit)
    <a href="{{ $edit }}" class="btn {{ $size }} btn-warning">Edit</a>
  @endif

  @if($delete)
    <form action="{{ $delete }}" method="POST" class="d-inline"
          onsubmit="return confirm(@js($confirm));">
      @csrf
      @method('DELETE')
      <button type="submit" class="btn {{ $size }} btn-danger">Delete</button>
    </form>
  @endif
</div>
