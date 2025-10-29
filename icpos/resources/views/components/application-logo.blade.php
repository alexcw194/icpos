@props(['class' => 'mx-auto h-16 w-auto'])

<img
  src="{{ $brandLogoUrl ?? asset('images/logo-default.svg') }}"
  alt="{{ config('app.name') }}"
  {{ $attributes->merge(['class' => $class]) }}
  style="max-height:64px; width:auto;"
/>
