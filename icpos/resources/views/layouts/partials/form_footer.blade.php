@php
  $cancelUrl    = $cancelUrl    ?? url()->previous();
  $cancelLabel  = $cancelLabel  ?? 'Batal';
  $cancelInline = $cancelInline ?? false; // kalau true, Batal dirender sejajar di sebelah kiri tombol2 aksi
  $buttons      = $buttons      ?? [];    // contoh: [['type'=>'submit','label'=>'Simpan','class'=>'btn btn-primary']]
@endphp

<div class="card-footer d-flex sticky-footer">
  @if(!$cancelInline)
    {{-- Mode default: Batal di kiri, tombol aksi di kanan --}}
    <a href="{{ $cancelUrl }}" class="btn btn-outline-secondary">{{ $cancelLabel }}</a>
    <div class="ms-auto btn-list">
      @foreach($buttons as $b)
        @php
          $type  = $b['type']  ?? 'button';
          $class = $b['class'] ?? 'btn btn-primary';
          $name  = $b['name']  ?? null;
          $value = $b['value'] ?? null;
          $label = $b['label'] ?? 'Submit';
        @endphp
        <button type="{{ $type }}" class="{{ $class }}"
                @if($name) name="{{ $name }}" @endif
                @if($value) value="{{ $value }}" @endif>
          {{ $label }}
        </button>
      @endforeach
    </div>
  @else
    {{-- Mode inline: semua di kanan, urutannya Batal | tombol2 aksi --}}
    <div class="ms-auto btn-list">
      <a href="{{ $cancelUrl }}" class="btn btn-outline-secondary">{{ $cancelLabel }}</a>
      @foreach($buttons as $b)
        @php
          $type  = $b['type']  ?? 'button';
          $class = $b['class'] ?? 'btn btn-primary';
          $name  = $b['name']  ?? null;
          $value = $b['value'] ?? null;
          $label = $b['label'] ?? 'Submit';
        @endphp
        <button type="{{ $type }}" class="{{ $class }}"
                @if($name) name="{{ $name }}" @endif
                @if($value) value="{{ $value }}" @endif>
          {{ $label }}
        </button>
      @endforeach
    </div>
  @endif
</div>