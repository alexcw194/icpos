@php
    use Illuminate\Support\Facades\Route;

    $hasCustomers    = Route::has('customers.index');
    $hasItems        = Route::has('items.index');
    $hasQuotations   = Route::has('quotations.index');
    $hasSalesOrders  = Route::has('sales-orders.index');

    // Current user & gate: hanya Super Admin yang boleh lihat master data
    $user = auth()->user();
    $canMaster = $user
        ? (method_exists($user, 'isSuperAdmin')
              ? $user->isSuperAdmin()
              : ($user->hasRole('Super Admin') ?? false))
        : false;

    // Brand (ambil dari settings; fallback ke config/app.name)
    $appName = config('app.name','ICPOS');
    $logoUrl = null;
    try {
        if (class_exists(\App\Models\Setting::class)) {
            $appName  = \App\Models\Setting::get('company.name', $appName);
            $logoPath = \App\Models\Setting::get('company.logo_path');
            $logoUrl  = $logoPath ? asset('storage/'.$logoPath) : null;
        }
    } catch (\Throwable $e) {}
@endphp

{{-- Fallback text style kalau belum ada logo --}}
<style>
  .brand-text-fallback{
    font-weight:800; letter-spacing:.5px; text-transform:uppercase;
    background:linear-gradient(90deg,#22d3ee,#6366f1);
    -webkit-background-clip:text; background-clip:text; color:transparent;
  }
</style>

<aside class="navbar navbar-vertical navbar-expand-lg" data-bs-theme="light">
  <div class="container-fluid">
    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#sidebar-menu"
            aria-controls="sidebar-menu" aria-expanded="false" aria-label="Toggle navigation">
      <span class="navbar-toggler-icon"></span>
    </button>

    <h1 class="navbar-brand navbar-brand-autodark px-2">
      <a href="{{ route('dashboard') }}" class="d-flex align-items-center gap-2 text-decoration-none w-100">
        @if($logoUrl)
          <img src="{{ $logoUrl }}" alt="logo" class="brand-logo">
        @else
          <span class="brand-text-fallback">{{ $appName }}</span>
        @endif
      </a>
    </h1>

    <div class="collapse navbar-collapse" id="sidebar-menu">
      <ul class="navbar-nav pt-lg-3">

        {{-- Dashboard --}}
        <li class="nav-item">
          <a class="nav-link {{ request()->routeIs('dashboard') ? 'active' : '' }}"
             href="{{ route('dashboard') }}">
            <span class="nav-link-icon d-md-none d-lg-inline-block">
              <svg xmlns="http://www.w3.org/2000/svg" class="icon" width="24" height="24"
                   viewBox="0 0 24 24" stroke="currentColor" fill="none" stroke-width="2">
                <path stroke="none" d="M0 0h24v24H0z"/>
                <path d="M5 12l-2 0l9 -9l9 9l-2 0"/>
                <path d="M5 12v7a2 2 0 0 0 2 2h10a2 2 0 0 0 2 -2v-7"/>
                <path d="M10 12h4v4h-4z"/>
              </svg>
            </span>
            <span class="nav-link-title">Dashboard</span>
          </a>
        </li>

        {{-- Customers (Master Data) --}}
        @if($hasCustomers)
          <li class="nav-item">
            <a class="nav-link {{ request()->is('customers*') ? 'active' : '' }}"
               href="{{ route('customers.index') }}">
              <span class="nav-link-icon d-md-none d-lg-inline-block">
                <svg xmlns="http://www.w3.org/2000/svg" class="icon" width="24" height="24"
                     viewBox="0 0 24 24" stroke="currentColor" fill="none" stroke-width="2">
                  <path stroke="none" d="M0 0h24v24H0z"/>
                  <path d="M8 7a4 4 0 1 0 8 0a4 4 0 0 0 -8 0"/>
                  <path d="M6 21v-2a4 4 0 0 1 4 -4h4a4 4 0 0 1 4 4v2"/>
                </svg>
              </span>
              <span class="nav-link-title">Customers</span>
            </a>
          </li>
        @endif

        {{-- Items / Inventory (Master Data) --}}
        @if($hasItems)
          <li class="nav-item">
            <a class="nav-link {{ request()->is('items*') ? 'active' : '' }}"
               href="{{ route('items.index') }}">
              <span class="nav-link-icon d-md-none d-lg-inline-block">
                <svg xmlns="http://www.w3.org/2000/svg" class="icon" width="24" height="24"
                     viewBox="0 0 24 24" stroke="currentColor" fill="none" stroke-width="2">
                  <path stroke="none" d="M0 0h24v24H0z"/>
                  <path d="M3 7l9 -4l9 4l-9 4l-9 -4"/>
                  <path d="M3 17l9 4l9 -4"/>
                  <path d="M3 12l9 4l9 -4"/>
                </svg>
              </span>
              <span class="nav-link-title">Items</span>
            </a>
          </li>
        @endif

        {{-- Quotations — tersedia untuk user operasional --}}
        @if($hasQuotations)
          <li class="nav-item">
            <a class="nav-link {{ request()->is('quotations*') ? 'active' : '' }}"
               href="{{ route('quotations.index') }}">
              <span class="nav-link-icon d-md-none d-lg-inline-block">
                <svg xmlns="http://www.w3.org/2000/svg" class="icon" width="24" height="24"
                     viewBox="0 0 24 24" stroke="currentColor" fill="none" stroke-width="2">
                  <path stroke="none" d="M0 0h24v24H0z"/>
                  <path d="M14 3v4a1 1 0 0 0 1 1h4"/>
                  <path d="M17 21h-10a2 2 0 0 1 -2 -2v-14a2 2 0 0 1 2 -2h7l5 5v11a2 2 0 0 1 -2 2"/>
                  <path d="M9 9h6"/><path d="M9 13h6"/><path d="M9 17h4"/>
                </svg>
              </span>
              <span class="nav-link-title">Quotations</span>
            </a>
          </li>
        @endif

        {{-- Sales Orders — tersedia untuk user operasional --}}
        @if($hasSalesOrders)
          <li class="nav-item">
            <a class="nav-link {{ request()->is('sales-orders*') ? 'active' : '' }}"
               href="{{ route('sales-orders.index') }}">
              <span class="nav-link-icon d-md-none d-lg-inline-block">
                <svg xmlns="http://www.w3.org/2000/svg" class="icon" width="24" height="24"
                     viewBox="0 0 24 24" stroke="currentColor" fill="none" stroke-width="2">
                  <path stroke="none" d="M0 0h24v24H0z"/>
                  <path d="M14 3v4a1 1 0 0 0 1 1h4"/>
                  <path d="M17 21h-10a2 2 0 0 1 -2 -2v-14a2 2 0 0 1 2 -2h7l5 5v11a2 2 0 0 1 -2 2"/>
                  <path d="M9 9h6"/><path d="M9 13h6"/><path d="M9 17h4"/>
                  <path d="M16 16l2 2l3 -3"/>
                </svg>
              </span>
              <span class="nav-link-title">Sales Orders</span>
            </a>
          </li>
        @endif

        {{-- Tidak ada menu Admin di sidebar. Admin hanya di top bar. --}}
      </ul>
    </div>
  </div>
</aside>
