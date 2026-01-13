{{-- resources/views/layouts/partials/actions_menu.blade.php --}}
@php
  /**
   * actions_menu partial
   *
   * $actions = [
   *   ['label' => 'Lihat', 'url' => '...', 'type' => 'link', 'variant' => 'secondary', 'target' => '_blank'],
   *   ['label' => 'Ubah',  'url' => '...', 'type' => 'link', 'variant' => 'primary'],
   *   ['label' => 'Hapus', 'url' => '...', 'type' => 'delete', 'variant' => 'danger', 'confirm' => 'Hapus data ini?'],
   * ];
   *
   * Desktop: buttons
   * Mobile: overflow menu (⋯)
   */
  $actions = $actions ?? [];
  $size = ($size ?? 'sm') === 'sm' ? 'btn-sm' : '';
@endphp

{{-- DESKTOP: tombol --}}
<div class="btn-list d-none d-md-inline-flex">
  @foreach($actions as $a)
    @php
      $variant = $a['variant'] ?? 'secondary';
      $btnClass = match($variant) {
        'primary' => 'btn-outline-primary',
        'danger'  => 'btn-outline-danger',
        default   => 'btn-outline-secondary',
      };
      $type = $a['type'] ?? 'link';
    @endphp

    @if($type === 'delete')
      <form action="{{ $a['url'] }}" method="POST" class="d-inline"
            onsubmit="return confirm(@js($a['confirm'] ?? 'Hapus data ini?'));">

        @csrf
        @method('DELETE')
        <button type="submit" class="btn {{ $size }} {{ $btnClass }}">
          {{ $a['label'] }}
        </button>
      </form>
    @else
      <a href="{{ $a['url'] }}"
         class="btn {{ $size }} {{ $btnClass }}"
         @if(!empty($a['target'])) target="{{ $a['target'] }}" rel="noopener" @endif>
        {{ $a['label'] }}
      </a>
    @endif
  @endforeach
</div>

{{-- MOBILE: overflow --}}
<div class="d-md-none">
  <div class="dropdown">
    <button class="btn btn-icon btn-sm btn-outline-secondary" type="button"
            data-bs-toggle="dropdown" aria-expanded="false" aria-label="Aksi">
      <svg xmlns="http://www.w3.org/2000/svg" class="icon" width="20" height="20" viewBox="0 0 24 24"
           stroke-width="2" stroke="currentColor" fill="none" stroke-linecap="round" stroke-linejoin="round"
           aria-hidden="true">
        <path stroke="none" d="M0 0h24v24H0z" fill="none"></path>
        <circle cx="5" cy="12" r="1"></circle>
        <circle cx="12" cy="12" r="1"></circle>
        <circle cx="19" cy="12" r="1"></circle>
      </svg>
    </button>

    <div class="dropdown-menu dropdown-menu-end">
      @foreach($actions as $a)
        @php
          $type = $a['type'] ?? 'link';
          $isDanger = ($a['variant'] ?? '') === 'danger';
        @endphp

        @if($type === 'delete')
          <form action="{{ $a['url'] }}" method="POST"
                onsubmit="return confirm(@js($a['confirm'] ?? 'Hapus data ini?'));">

            @csrf
            @method('DELETE')
            <button type="submit" class="dropdown-item {{ $isDanger ? 'text-danger' : '' }}">
              {{ $a['label'] }}
            </button>
          </form>
        @else
          <a href="{{ $a['url'] }}"
             class="dropdown-item {{ $isDanger ? 'text-danger' : '' }}"
             @if(!empty($a['target'])) target="{{ $a['target'] }}" rel="noopener" @endif>
            {{ $a['label'] }}
          </a>
        @endif
      @endforeach
    </div>
  </div>
</div>
