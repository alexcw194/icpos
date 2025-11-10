@php
    use Illuminate\Support\Facades\Route;

    $hasCustomers    = Route::has('customers.index');
    $hasItems        = Route::has('items.index');
    $hasQuotations   = Route::has('quotations.index');
    $hasSalesOrders  = Route::has('sales-orders.index');
    $hasDeliveries   = Route::has('deliveries.index');

    $user = auth()->user();
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
  .brand-text-fallback {
    font-weight: 800;
    letter-spacing: .5px;
    text-transform: uppercase;
    background: linear-gradient(90deg,#22d3ee,#6366f1);
    -webkit-background-clip: text;
    background-clip: text;
    color: transparent;
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
          <a class="nav-link {{ request()->routeIs('dashboard') ? 'active' : '' }}" href="{{ route('dashboard') }}">
            <span class="nav-link-icon ti ti-home"></span>
            <span class="nav-link-title">Dashboard</span>
          </a>
        </li>

        {{-- Customers --}}
        @if($hasCustomers)
          <li class="nav-item">
            <a class="nav-link {{ request()->is('customers*') ? 'active' : '' }}" href="{{ route('customers.index') }}">
              <span class="nav-link-icon ti ti-users"></span>
              <span class="nav-link-title">Customers</span>
            </a>
          </li>
        @endif

        {{-- Items --}}
        @if($hasItems)
          <li class="nav-item">
            <a class="nav-link {{ request()->is('items*') ? 'active' : '' }}" href="{{ route('items.index') }}">
              <span class="nav-link-icon ti ti-box"></span>
              <span class="nav-link-title">Items</span>
            </a>
          </li>
        @endif

        {{-- Sales --}}
        <li class="nav-item nav-group">
          <a class="nav-link" data-bs-toggle="collapse" href="#sales-collapse" role="button"
             aria-expanded="{{ request()->is('quotations*') || request()->is('sales-orders*') || request()->is('deliveries*') || request()->is('invoices*') ? 'true' : 'false' }}"
             aria-controls="sales-collapse">
            <span class="nav-link-icon ti ti-file-invoice"></span>
            <span class="nav-link-title">Sales</span>
          </a>
          <div class="collapse {{ request()->is('quotations*') || request()->is('sales-orders*') || request()->is('deliveries*') || request()->is('invoices*') ? 'show' : '' }}" id="sales-collapse">
            <ul class="nav nav-pills sub-nav flex-column ms-4">
              <li><a class="nav-link {{ request()->is('quotations*') ? 'active' : '' }}" href="{{ route('quotations.index') }}">Quotations</a></li>
              <li><a class="nav-link {{ request()->is('sales-orders*') ? 'active' : '' }}" href="{{ route('sales-orders.index') }}">Sales Orders</a></li>
              <li><a class="nav-link {{ request()->is('deliveries*') ? 'active' : '' }}" href="{{ route('deliveries.index') }}">Delivery Orders</a></li>
              <li><a class="nav-link {{ request()->is('invoices*') ? 'active' : '' }}" href="{{ route('invoices.index') }}">Invoices</a></li>
            </ul>
          </div>
        </li>

        {{-- Purchase --}}
        @hasanyrole('Admin|SuperAdmin')
        <li class="nav-item">
          <a class="nav-link {{ request()->routeIs('po.*') ? 'active' : '' }}" href="{{ route('po.index') }}">
            <span class="nav-link-icon ti ti-shopping-cart"></span>
            <span class="nav-link-title">Purchase Orders</span>
          </a>
        </li>
        @endhasanyrole

        {{-- Inventory Category --}}
        <li class="nav-item nav-group">
          <a class="nav-link" data-bs-toggle="collapse" href="#inventory-collapse" role="button"
             aria-expanded="{{ request()->is('inventory*') ? 'true' : 'false' }}" aria-controls="inventory-collapse">
            <span class="nav-link-icon ti ti-warehouse"></span>
            <span class="nav-link-title">Inventory</span>
          </a>
          <div class="collapse {{ request()->is('inventory*') ? 'show' : '' }}" id="inventory-collapse">
            <ul class="nav nav-pills sub-nav flex-column ms-4">
              <li><a class="nav-link {{ request()->routeIs('inventory.ledger') ? 'active' : '' }}" href="{{ route('inventory.ledger') }}">Stock Ledger</a></li>
              <li><a class="nav-link {{ request()->routeIs('inventory.summary') ? 'active' : '' }}" href="{{ route('inventory.summary') }}">Stock Summary</a></li>
              <li><a class="nav-link {{ request()->routeIs('inventory.adjustments.*') ? 'active' : '' }}" href="{{ route('inventory.adjustments.index') }}">Stock Adjustment</a></li>
              <li><a class="nav-link {{ request()->routeIs('inventory.reconciliation') ? 'active' : '' }}" href="{{ route('inventory.reconciliation') }}">Reconciliation</a></li>
            </ul>
          </div>
        </li>

        {{-- Manufacture Category --}}
        <li class="nav-item nav-group">
          <a class="nav-link" data-bs-toggle="collapse" href="#manufacture-collapse" role="button"
             aria-expanded="{{ request()->is('manufacture-*') ? 'true' : 'false' }}" aria-controls="manufacture-collapse">
            <span class="nav-link-icon ti ti-tools"></span>
            <span class="nav-link-title">Manufacture</span>
          </a>
          <div class="collapse {{ request()->is('manufacture-*') ? 'show' : '' }}" id="manufacture-collapse">
            <ul class="nav nav-pills sub-nav flex-column ms-4">
              <li><a class="nav-link {{ request()->is('manufacture-jobs*') ? 'active' : '' }}" href="{{ route('manufacture-jobs.index') }}">Manufacture Jobs</a></li>
              <li><a class="nav-link {{ request()->is('manufacture-recipes*') ? 'active' : '' }}" href="{{ route('manufacture-recipes.index') }}">Manufacture Recipes</a></li>
            </ul>
          </div>
        </li>

      </ul>
    </div>
  </div>
</aside>
