@php
  use Illuminate\Support\Facades\Route;

  $hasCustomers = Route::has('customers.index');
  $hasItems     = Route::has('items.index');
  $hasProjects  = Route::has('projects.index');

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

<style>
  .brand-text-fallback{
    font-weight:800;
    letter-spacing:.5px;
    text-transform:uppercase;
    background: linear-gradient(90deg,#22d3ee,#6366f1);
    -webkit-background-clip:text;
    background-clip:text;
    color:transparent;
  }
</style>

<aside class="navbar navbar-vertical navbar-expand-lg" data-bs-theme="light">
  <div class="container-fluid">

    {{-- Mobile toggler (ONLY mobile) --}}
    <button class="navbar-toggler d-lg-none" type="button"
            data-bs-toggle="offcanvas" data-bs-target="#sidebar-menu"
            aria-controls="sidebar-menu" aria-label="Toggle navigation">
      <span class="navbar-toggler-icon"></span>
    </button>

    {{-- Brand --}}
    <h1 class="navbar-brand navbar-brand-autodark px-2">
      <a href="{{ route('dashboard') }}" class="d-flex align-items-center gap-2 text-decoration-none w-100">
        @if($logoUrl)
          <img src="{{ $logoUrl }}" alt="logo" class="brand-logo">
        @else
          <span class="brand-text-fallback">{{ $appName }}</span>
        @endif
      </a>
    </h1>

    {{-- Offcanvas on mobile, static on desktop --}}
    <div class="offcanvas offcanvas-start offcanvas-lg" tabindex="-1"
         id="sidebar-menu" aria-labelledby="sidebar-menu-label">

      {{-- Mobile header (ONLY mobile) --}}
      <div class="offcanvas-header d-lg-none">
        <h2 class="offcanvas-title" id="sidebar-menu-label">{{ $appName }}</h2>
        <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Close"></button>
      </div>

      <div class="offcanvas-body p-0">
        <ul class="navbar-nav pt-lg-3">

          {{-- Dashboard --}}
          <li class="nav-item">
            <a class="nav-link {{ request()->routeIs('dashboard') ? 'active' : '' }}"
               href="{{ route('dashboard') }}">
              <span class="nav-link-icon ti ti-home"></span>
              <span class="nav-link-title">Dashboard</span>
            </a>
          </li>

          {{-- Customers --}}
          @if($hasCustomers)
            <li class="nav-item">
              <a class="nav-link {{ request()->is('customers*') ? 'active' : '' }}"
                 href="{{ route('customers.index') }}">
                <span class="nav-link-icon ti ti-users"></span>
                <span class="nav-link-title">Customers</span>
              </a>
            </li>
          @endif

          {{-- Items --}}
          @if($hasItems)
            <li class="nav-item">
              <a class="nav-link {{ request()->is('items*') ? 'active' : '' }}"
                 href="{{ route('items.index') }}">
                <span class="nav-link-icon ti ti-box"></span>
                <span class="nav-link-title">Items</span>
              </a>
            </li>
          @endif

          {{-- Projects --}}
          @if($hasProjects)
            <li class="nav-item">
              <a class="nav-link {{ request()->is('projects*') ? 'active' : '' }}"
                 href="{{ route('projects.index') }}">
                <span class="nav-link-icon ti ti-briefcase"></span>
                <span class="nav-link-title">Projects</span>
              </a>
            </li>
          @endif

          {{-- Sales (always open) --}}
          <li class="nav-item nav-group">
            <div class="nav-link {{ request()->is('quotations*') || request()->is('sales-orders*') || request()->is('deliveries*') || request()->is('invoices*') ? 'active' : '' }}">
              <span class="nav-link-icon ti ti-file-invoice"></span>
              <span class="nav-link-title">Sales</span>
            </div>

            <ul class="nav nav-pills sub-nav flex-column ms-4">
              @if(Route::has('quotations.index'))
                <li>
                  <a class="nav-link {{ request()->is('quotations*') ? 'active' : '' }}"
                     href="{{ route('quotations.index') }}">
                    Quotations
                  </a>
                </li>
              @endif

              @if(Route::has('sales-orders.index'))
                <li>
                  <a class="nav-link {{ request()->is('sales-orders*') ? 'active' : '' }}"
                     href="{{ route('sales-orders.index') }}">
                    Sales Orders
                  </a>
                </li>
              @endif

              @if(Route::has('deliveries.index'))
                <li>
                  <a class="nav-link {{ request()->is('deliveries*') ? 'active' : '' }}"
                     href="{{ route('deliveries.index') }}">
                    Delivery Orders
                  </a>
                </li>
              @endif

              @if(Route::has('invoices.index'))
                <li>
                  <a class="nav-link {{ request()->is('invoices*') ? 'active' : '' }}"
                     href="{{ route('invoices.index') }}">
                    Invoices
                  </a>
                </li>
              @endif
            </ul>
          </li>

          {{-- Purchase (Admin/SuperAdmin only) --}}
          @hasanyrole('Admin|SuperAdmin')
            @if(Route::has('po.index'))
              <li class="nav-item">
                <a class="nav-link {{ request()->routeIs('po.*') ? 'active' : '' }}"
                   href="{{ route('po.index') }}">
                  <span class="nav-link-icon ti ti-shopping-cart"></span>
                  <span class="nav-link-title">Purchase Orders</span>
                </a>
              </li>
            @endif
          @endhasanyrole

          {{-- Inventory (always open) --}}
          <li class="nav-item nav-group">
            <div class="nav-link {{ request()->is('inventory*') ? 'active' : '' }}">
              <span class="nav-link-icon">
                <svg xmlns="http://www.w3.org/2000/svg" class="icon icon-tabler icon-tabler-warehouse"
                     width="20" height="20" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"
                     fill="none" stroke-linecap="round" stroke-linejoin="round">
                  <path stroke="none" d="M0 0h24v24H0z" fill="none"/>
                  <path d="M3 21v-13l9 -4l9 4v13" />
                  <path d="M13 13h4v8h-10v-6h6v-2z" />
                </svg>
              </span>
              <span class="nav-link-title">Inventory</span>
            </div>

            <ul class="nav nav-pills sub-nav flex-column ms-4">
              @if(Route::has('inventory.ledger'))
                <li><a class="nav-link {{ request()->routeIs('inventory.ledger') ? 'active' : '' }}"
                       href="{{ route('inventory.ledger') }}">Stock Ledger</a></li>
              @endif
              @if(Route::has('inventory.summary'))
                <li><a class="nav-link {{ request()->routeIs('inventory.summary') ? 'active' : '' }}"
                       href="{{ route('inventory.summary') }}">Stock Summary</a></li>
              @endif
              @if(Route::has('inventory.adjustments.index'))
                <li><a class="nav-link {{ request()->routeIs('inventory.adjustments.*') ? 'active' : '' }}"
                       href="{{ route('inventory.adjustments.index') }}">Stock Adjustment</a></li>
              @endif
              @if(Route::has('inventory.reconciliation'))
                <li><a class="nav-link {{ request()->routeIs('inventory.reconciliation') ? 'active' : '' }}"
                       href="{{ route('inventory.reconciliation') }}">Reconciliation</a></li>
              @endif
            </ul>
          </li>

          {{-- Manufacture (always open) --}}
          <li class="nav-item nav-group">
            <div class="nav-link {{ request()->is('manufacture-*') || request()->is('manufacture-jobs*') || request()->is('manufacture-recipes*') ? 'active' : '' }}">
              <span class="nav-link-icon">
                <svg xmlns="http://www.w3.org/2000/svg" class="icon icon-tabler icon-tabler-tools"
                     width="20" height="20" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"
                     fill="none" stroke-linecap="round" stroke-linejoin="round">
                  <path stroke="none" d="M0 0h24v24H0z" fill="none"/>
                  <path d="M9 12l-1.5 1.5a2.12 2.12 0 0 0 0 3l4 4a2.12 2.12 0 0 0 3 0l1.5 -1.5" />
                  <path d="M15 12l2 -2a3 3 0 0 0 -4.24 -4.24l-2 2" />
                  <path d="M9 12l-2 -2a3 3 0 0 1 4.24 -4.24l2 2" />
                  <path d="M12 9l-2 2" />
                </svg>
              </span>
              <span class="nav-link-title">Manufacture</span>
            </div>

            <ul class="nav nav-pills sub-nav flex-column ms-4">
              @if(Route::has('manufacture-jobs.index'))
                <li><a class="nav-link {{ request()->is('manufacture-jobs*') ? 'active' : '' }}"
                       href="{{ route('manufacture-jobs.index') }}">Manufacture Jobs</a></li>
              @endif
              @if(Route::has('manufacture-recipes.index'))
                <li><a class="nav-link {{ request()->is('manufacture-recipes*') ? 'active' : '' }}"
                       href="{{ route('manufacture-recipes.index') }}">Manufacture Recipes</a></li>
              @endif
            </ul>
          </li>

        </ul>
      </div>
    </div>
  </div>
</aside>
