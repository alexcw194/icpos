@php
  use Illuminate\Support\Facades\Route;

  $hasCustomers = Route::has('customers.index');
  $hasLeadDiscovery = Route::has('lead-discovery.prospects.index');
  $hasLeadQueue = Route::has('lead-discovery.queue.index');
  $hasItems = Route::has('items.index');
  $hasProjects = Route::has('projects.index');
  $financeOnly = auth()->user()?->isFinanceOnly() ?? false;

  $appName = config('app.name','ICPOS');
  $logoUrl = null;

  try {
      if (class_exists(\App\Models\Setting::class)) {
          $appName  = \App\Models\Setting::get('company.name', $appName);
          $logoPath = \App\Models\Setting::get('company.logo_path');
          $logoUrl  = $logoPath ? asset('storage/'.$logoPath) : null;
      }
  } catch (\Throwable $e) {}

  $isCrmActive = request()->is('customers*') || request()->is('lead-discovery*');
  $isSalesActive = ((!$financeOnly && request()->is('quotations*')) || request()->is('sales-orders*') || request()->is('deliveries*') || request()->is('invoices*') || request()->routeIs('reports.income.*'));
  $isProjectsActive = request()->is('projects*') || request()->is('project-items*') || request()->is('project-stocks*') || request()->is('project-deliveries*') || request()->is('project-invoices*');
  $isDocumentsActive = request()->is('documents*');
  $isPurchaseActive = request()->routeIs('po.*') || request()->routeIs('suppliers.*');
  $isInventoryActive = request()->is('inventory*');
  $isManufactureActive = request()->is('manufacture-*') || request()->is('manufacture-jobs*') || request()->is('manufacture-recipes*');
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

  .nav-group-toggle {
    cursor: pointer;
  }

  .nav-group-caret {
    margin-left: auto;
    font-size: 1rem;
    transition: transform .2s ease;
  }

  .nav-group.is-expanded .nav-group-caret {
    transform: rotate(180deg);
  }
</style>

<aside class="navbar navbar-vertical navbar-expand-lg" data-bs-theme="light">
  <div class="container-fluid">
    <button class="navbar-toggler d-lg-none" type="button"
            data-bs-toggle="offcanvas" data-bs-target="#sidebar-menu"
            aria-controls="sidebar-menu" aria-label="Toggle navigation">
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

    <div class="offcanvas offcanvas-start offcanvas-lg" tabindex="-1"
         id="sidebar-menu" aria-labelledby="sidebar-menu-label">
      <div class="offcanvas-header d-lg-none">
        <h2 class="offcanvas-title" id="sidebar-menu-label">{{ $appName }}</h2>
        <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Close"></button>
      </div>

      <div class="offcanvas-body p-0">
        <ul class="navbar-nav pt-lg-3" id="sidebar-accordion">
          <li class="nav-item">
            <a class="nav-link {{ request()->routeIs('dashboard') ? 'active' : '' }}" href="{{ route('dashboard') }}">
              <span class="nav-link-icon ti ti-home"></span>
              <span class="nav-link-title">Dashboard</span>
            </a>
          </li>

          @if($hasCustomers || $hasLeadDiscovery || $hasLeadQueue)
            <li class="nav-item nav-group js-sidebar-group" data-group-key="crm" data-group-active="{{ $isCrmActive ? '1' : '0' }}">
              <a class="nav-link nav-group-toggle {{ $isCrmActive ? 'active' : '' }}"
                 data-bs-toggle="collapse" href="#sidebar-group-crm" role="button"
                 aria-expanded="false" aria-controls="sidebar-group-crm">
                <span class="nav-link-icon ti ti-users"></span>
                <span class="nav-link-title">CRM</span>
                <span class="nav-group-caret ti ti-chevron-down"></span>
              </a>
              <ul class="nav nav-pills sub-nav flex-column collapse" id="sidebar-group-crm" data-bs-parent="#sidebar-accordion">
                @if($hasCustomers)
                  <li>
                    <a class="nav-link {{ request()->is('customers*') ? 'active' : '' }}" href="{{ route('customers.index') }}">
                      Customers
                    </a>
                  </li>
                @endif
                @if($hasLeadDiscovery)
                  <li>
                    <a class="nav-link {{ request()->routeIs('lead-discovery.prospects.*') ? 'active' : '' }}" href="{{ route('lead-discovery.prospects.index') }}">
                      Lead Discovery
                    </a>
                  </li>
                @endif
                @if($hasLeadQueue)
                  <li>
                    <a class="nav-link {{ request()->routeIs('lead-discovery.queue.index') ? 'active' : '' }}" href="{{ route('lead-discovery.queue.index') }}">
                      Lead Queue
                    </a>
                  </li>
                @endif
              </ul>
            </li>
          @endif

          @if($hasItems)
            <li class="nav-item">
              <a class="nav-link {{ request()->is('items*') ? 'active' : '' }}" href="{{ route('items.index') }}">
                <span class="nav-link-icon ti ti-box"></span>
                <span class="nav-link-title">Items</span>
              </a>
            </li>
          @endif

          <li class="nav-item nav-group js-sidebar-group" data-group-key="sales" data-group-active="{{ $isSalesActive ? '1' : '0' }}">
            <a class="nav-link nav-group-toggle {{ $isSalesActive ? 'active' : '' }}"
               data-bs-toggle="collapse" href="#sidebar-group-sales" role="button"
               aria-expanded="false" aria-controls="sidebar-group-sales">
              <span class="nav-link-icon ti ti-file-invoice"></span>
              <span class="nav-link-title">Sales</span>
              <span class="nav-group-caret ti ti-chevron-down"></span>
            </a>
            <ul class="nav nav-pills sub-nav flex-column collapse" id="sidebar-group-sales" data-bs-parent="#sidebar-accordion">
              @if(!$financeOnly && Route::has('quotations.index'))
                <li><a class="nav-link {{ request()->is('quotations*') ? 'active' : '' }}" href="{{ route('quotations.index') }}">Quotations</a></li>
              @endif
              @if(Route::has('sales-orders.index'))
                <li><a class="nav-link {{ request()->is('sales-orders*') ? 'active' : '' }}" href="{{ route('sales-orders.index') }}">Sales Orders</a></li>
              @endif
              @if(Route::has('deliveries.index'))
                <li><a class="nav-link {{ request()->is('deliveries*') ? 'active' : '' }}" href="{{ route('deliveries.index') }}">Delivery Orders</a></li>
              @endif
              @if(Route::has('invoices.index'))
                <li><a class="nav-link {{ request()->is('invoices*') ? 'active' : '' }}" href="{{ route('invoices.index') }}">Invoices</a></li>
              @endif
              @hasanyrole('Admin|SuperAdmin')
                @if(Route::has('reports.income.index'))
                  <li><a class="nav-link {{ request()->routeIs('reports.income.*') ? 'active' : '' }}" href="{{ route('reports.income.index') }}">Income Report</a></li>
                @endif
              @endhasanyrole
            </ul>
          </li>

          @if($hasProjects)
            <li class="nav-item nav-group js-sidebar-group" data-group-key="projects" data-group-active="{{ $isProjectsActive ? '1' : '0' }}">
              <a class="nav-link nav-group-toggle {{ $isProjectsActive ? 'active' : '' }}"
                 data-bs-toggle="collapse" href="#sidebar-group-projects" role="button"
                 aria-expanded="false" aria-controls="sidebar-group-projects">
                <span class="nav-link-icon ti ti-briefcase"></span>
                <span class="nav-link-title">Projects</span>
                <span class="nav-group-caret ti ti-chevron-down"></span>
              </a>

              <ul class="nav nav-pills sub-nav flex-column collapse" id="sidebar-group-projects" data-bs-parent="#sidebar-accordion">
                <li>
                  <a class="nav-link {{ (request()->is('projects*') && !request()->is('projects/labor*')) ? 'active' : '' }}" href="{{ route('projects.index') }}">
                    Projects List
                  </a>
                </li>

                <li>
                  @if(Route::has('project-items.index'))
                    <a class="nav-link {{ request()->is('project-items*') ? 'active' : '' }}" href="{{ route('project-items.index') }}">
                      Project Items
                    </a>
                  @else
                    <a class="nav-link disabled" href="#" tabindex="-1" aria-disabled="true">Project Items</a>
                  @endif
                </li>

                @hasanyrole('SuperAdmin|Admin|Sales|Finance|PM')
                  <li>
                    @if(Route::has('projects.labor.index'))
                      <a class="nav-link {{ request()->is('projects/labor*') ? 'active' : '' }}" href="{{ route('projects.labor.index') }}">
                        Master Labor
                      </a>
                    @else
                      <a class="nav-link disabled" href="#" tabindex="-1" aria-disabled="true">Master Labor</a>
                    @endif
                  </li>
                @endhasanyrole

                <li>
                  @if(Route::has('project-stocks.index'))
                    <a class="nav-link {{ request()->is('project-stocks*') ? 'active' : '' }}" href="{{ route('project-stocks.index') }}">
                      Project Stocks
                    </a>
                  @else
                    <a class="nav-link disabled" href="#" tabindex="-1" aria-disabled="true">Project Stocks</a>
                  @endif
                </li>

                <li>
                  @if(Route::has('project-deliveries.index'))
                    <a class="nav-link {{ request()->is('project-deliveries*') ? 'active' : '' }}" href="{{ route('project-deliveries.index') }}">
                      Projects Delivery
                    </a>
                  @else
                    <a class="nav-link disabled" href="#" tabindex="-1" aria-disabled="true">Projects Delivery</a>
                  @endif
                </li>

                <li>
                  @if(Route::has('project-invoices.index'))
                    <a class="nav-link {{ request()->is('project-invoices*') ? 'active' : '' }}" href="{{ route('project-invoices.index') }}">
                      Projects Invoices
                    </a>
                  @else
                    <a class="nav-link disabled" href="#" tabindex="-1" aria-disabled="true">Projects Invoices</a>
                  @endif
                </li>
              </ul>
            </li>
          @endif

          @hasanyrole('Sales|Admin|SuperAdmin|Super Admin')
            <li class="nav-item nav-group js-sidebar-group" data-group-key="dokumen" data-group-active="{{ $isDocumentsActive ? '1' : '0' }}">
              <a class="nav-link nav-group-toggle {{ $isDocumentsActive ? 'active' : '' }}"
                 data-bs-toggle="collapse" href="#sidebar-group-documents" role="button"
                 aria-expanded="false" aria-controls="sidebar-group-documents">
                <span class="nav-link-icon ti ti-file-text"></span>
                <span class="nav-link-title">Dokumen</span>
                <span class="nav-group-caret ti ti-chevron-down"></span>
              </a>
              <ul class="nav nav-pills sub-nav flex-column collapse" id="sidebar-group-documents" data-bs-parent="#sidebar-accordion">
                @hasanyrole('Sales')
                  <li><a class="nav-link {{ request()->routeIs('documents.my') ? 'active' : '' }}" href="{{ route('documents.my') }}">My Documents</a></li>
                @endhasanyrole
                @hasanyrole('Admin|SuperAdmin|Super Admin')
                  <li><a class="nav-link {{ request()->routeIs('documents.index') ? 'active' : '' }}" href="{{ route('documents.index') }}">All Documents</a></li>
                  <li><a class="nav-link {{ request()->routeIs('documents.pending') ? 'active' : '' }}" href="{{ route('documents.pending') }}">Pending Approval</a></li>
                @endhasanyrole
              </ul>
            </li>
          @endhasanyrole

          @hasanyrole('Admin|SuperAdmin')
            @if(Route::has('po.index'))
              <li class="nav-item nav-group js-sidebar-group" data-group-key="purchase" data-group-active="{{ $isPurchaseActive ? '1' : '0' }}">
                <a class="nav-link nav-group-toggle {{ $isPurchaseActive ? 'active' : '' }}"
                   data-bs-toggle="collapse" href="#sidebar-group-purchase" role="button"
                   aria-expanded="false" aria-controls="sidebar-group-purchase">
                  <span class="nav-link-icon ti ti-shopping-cart"></span>
                  <span class="nav-link-title">Purchase</span>
                  <span class="nav-group-caret ti ti-chevron-down"></span>
                </a>
                <ul class="nav nav-pills sub-nav flex-column collapse" id="sidebar-group-purchase" data-bs-parent="#sidebar-accordion">
                  <li><a class="nav-link {{ request()->routeIs('po.*') ? 'active' : '' }}" href="{{ route('po.index') }}">Purchase Orders</a></li>
                  <li><a class="nav-link {{ request()->routeIs('suppliers.*') ? 'active' : '' }}" href="{{ route('suppliers.index') }}">Suppliers</a></li>
                </ul>
              </li>
            @endif
          @endhasanyrole

          <li class="nav-item nav-group js-sidebar-group" data-group-key="inventory" data-group-active="{{ $isInventoryActive ? '1' : '0' }}">
            <a class="nav-link nav-group-toggle {{ $isInventoryActive ? 'active' : '' }}"
               data-bs-toggle="collapse" href="#sidebar-group-inventory" role="button"
               aria-expanded="false" aria-controls="sidebar-group-inventory">
              <span class="nav-link-icon">
                <svg xmlns="http://www.w3.org/2000/svg" class="icon icon-tabler icon-tabler-warehouse" width="20" height="20" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none" stroke-linecap="round" stroke-linejoin="round">
                  <path stroke="none" d="M0 0h24v24H0z" fill="none"/>
                  <path d="M3 21v-13l9 -4l9 4v13" />
                  <path d="M13 13h4v8h-10v-6h6v-2z" />
                </svg>
              </span>
              <span class="nav-link-title">Inventory</span>
              <span class="nav-group-caret ti ti-chevron-down"></span>
            </a>
            <ul class="nav nav-pills sub-nav flex-column collapse" id="sidebar-group-inventory" data-bs-parent="#sidebar-accordion">
              @if(Route::has('inventory.ledger'))
                <li><a class="nav-link {{ request()->routeIs('inventory.ledger') ? 'active' : '' }}" href="{{ route('inventory.ledger') }}">Stock Ledger</a></li>
              @endif
              @if(Route::has('inventory.summary'))
                <li><a class="nav-link {{ request()->routeIs('inventory.summary') ? 'active' : '' }}" href="{{ route('inventory.summary') }}">Stock Summary</a></li>
              @endif
              @if(Route::has('inventory.adjustments.index'))
                <li><a class="nav-link {{ request()->routeIs('inventory.adjustments.*') ? 'active' : '' }}" href="{{ route('inventory.adjustments.index') }}">Stock Adjustment</a></li>
              @endif
              @if(Route::has('inventory.reconciliation'))
                <li><a class="nav-link {{ request()->routeIs('inventory.reconciliation') ? 'active' : '' }}" href="{{ route('inventory.reconciliation') }}">Reconciliation</a></li>
              @endif
            </ul>
          </li>

          <li class="nav-item nav-group js-sidebar-group" data-group-key="manufacture" data-group-active="{{ $isManufactureActive ? '1' : '0' }}">
            <a class="nav-link nav-group-toggle {{ $isManufactureActive ? 'active' : '' }}"
               data-bs-toggle="collapse" href="#sidebar-group-manufacture" role="button"
               aria-expanded="false" aria-controls="sidebar-group-manufacture">
              <span class="nav-link-icon">
                <svg xmlns="http://www.w3.org/2000/svg" class="icon icon-tabler icon-tabler-tools" width="20" height="20" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none" stroke-linecap="round" stroke-linejoin="round">
                  <path stroke="none" d="M0 0h24v24H0z" fill="none"/>
                  <path d="M9 12l-1.5 1.5a2.12 2.12 0 0 0 0 3l4 4a2.12 2.12 0 0 0 3 0l1.5 -1.5" />
                  <path d="M15 12l2 -2a3 3 0 0 0 -4.24 -4.24l-2 2" />
                  <path d="M9 12l-2 -2a3 3 0 0 1 4.24 -4.24l2 2" />
                  <path d="M12 9l-2 2" />
                </svg>
              </span>
              <span class="nav-link-title">Manufacture</span>
              <span class="nav-group-caret ti ti-chevron-down"></span>
            </a>

            <ul class="nav nav-pills sub-nav flex-column collapse" id="sidebar-group-manufacture" data-bs-parent="#sidebar-accordion">
              @if(Route::has('manufacture-jobs.index'))
                <li><a class="nav-link {{ request()->is('manufacture-jobs*') ? 'active' : '' }}" href="{{ route('manufacture-jobs.index') }}">Manufacture Jobs</a></li>
              @endif
              @if(Route::has('manufacture-recipes.index'))
                <li><a class="nav-link {{ request()->is('manufacture-recipes*') ? 'active' : '' }}" href="{{ route('manufacture-recipes.index') }}">Manufacture Recipes</a></li>
              @endif
            </ul>
          </li>
        </ul>
      </div>
    </div>
  </div>
</aside>

<script>
  document.addEventListener('DOMContentLoaded', function () {
    var groups = Array.prototype.slice.call(document.querySelectorAll('.js-sidebar-group[data-group-key]'));
    if (!groups.length) {
      return;
    }

    var setExpandedClass = function (group, expanded) {
      group.classList.toggle('is-expanded', !!expanded);
      var toggle = group.querySelector('.nav-group-toggle');
      if (toggle) {
        toggle.setAttribute('aria-expanded', expanded ? 'true' : 'false');
      }
    };

    var activeGroup = groups.find(function (group) {
      return group.getAttribute('data-group-active') === '1';
    });
    var defaultKey = activeGroup
      ? (activeGroup.getAttribute('data-group-key') || '')
      : 'sales';

    groups.forEach(function (group) {
      var key = group.getAttribute('data-group-key') || '';
      var collapseEl = group.querySelector('.collapse');
      if (!key || !collapseEl) {
        return;
      }

      collapseEl.addEventListener('shown.bs.collapse', function () {
        setExpandedClass(group, true);
      });

      collapseEl.addEventListener('hidden.bs.collapse', function () {
        setExpandedClass(group, false);
      });

      var open = key === defaultKey;

      if (window.bootstrap && window.bootstrap.Collapse) {
        var instance = window.bootstrap.Collapse.getOrCreateInstance(collapseEl, { toggle: false });
        if (open) {
          instance.show();
        } else {
          instance.hide();
        }
      } else if (open) {
        collapseEl.classList.add('show');
      } else {
        collapseEl.classList.remove('show');
      }

      setExpandedClass(group, open);
    });
  });
</script>
