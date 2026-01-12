{{-- resources/views/layouts/tabler.blade.php --}}
<!doctype html>
<html lang="id">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="csrf-token" content="{{ csrf_token() }}">
  <title>@yield('title', $title ?? config('app.name', 'ICPOS'))</title>

  <link href="https://cdn.jsdelivr.net/npm/@tabler/icons-webfont@2.47.0/tabler-icons.min.css" rel="stylesheet"/>

  {{-- Tabler CSS (pinned version) --}}
  <link href="https://cdn.jsdelivr.net/npm/@tabler/core@1.0.0-beta20/dist/css/tabler.min.css" rel="stylesheet"/>
  {{-- (opsional) paket tambahan --}}
  <link href="https://cdn.jsdelivr.net/npm/@tabler/core@1.0.0-beta20/dist/css/tabler-flags.min.css" rel="stylesheet"/>
  <link href="https://cdn.jsdelivr.net/npm/@tabler/core@1.0.0-beta20/dist/css/tabler-payments.min.css" rel="stylesheet"/>
  <link href="https://cdn.jsdelivr.net/npm/@tabler/core@1.0.0-beta20/dist/css/tabler-vendors.min.css" rel="stylesheet"/>

  {{-- TomSelect CSS (untuk kotak pencarian item) --}}
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/tom-select/dist/css/tom-select.css">

  <style>
    .page-wrapper { margin-left: 0; }
    @media (min-width: 992px) {
      .navbar-vertical + .page-wrapper { margin-left: 280px; } /* lebar sidebar */
    }

    .modal { z-index: 1055; }
    .modal-backdrop { z-index: 1050; }

    .avatar.rounded-circle { border-radius: 50% }
    .avatar-ring { box-shadow: 0 0 0 2px rgba(0,0,0,.05), 0 0 0 4px rgba(59,130,246,.25) }

    /* Submenu di dalam dropdown (collapse) */
    .dropdown-menu .dropdown-submenu { padding: .25rem 0 .5rem; }
    .dropdown-menu .dropdown-submenu .dropdown-item { padding-left: 1.5rem; }

    /* caret animasi pada tombol Master Data */
    .caret { transition: transform .2s ease; }
    .caret.rotated { transform: rotate(180deg); }

    /* Sidebar brand helpers (logo di partial sidebar) */
    .navbar-vertical .navbar-brand{ padding:.5rem .75rem; }
    .brand-logo{ display:block; width:100%; height:auto; max-height:72px; object-fit:contain; }
    .brand-text-fallback{
      font-weight:800; letter-spacing:.5px; text-transform:uppercase;
      background:linear-gradient(90deg,#22d3ee,#6366f1);
      -webkit-background-clip:text; background-clip:text; color:transparent;
    }

    /* Sidebar: force left alignment */
    .navbar-vertical .navbar-nav {
      align-items: stretch;        /* jangan center kolomnya */
    }

    .navbar-vertical .nav-link,
    .navbar-vertical .nav-link-title {
      text-align: left;
    }

    .navbar-vertical .nav-link {
      justify-content: flex-start; /* ikon + teks rata kiri */
    }

    .navbar-vertical .nav-link-icon {
      margin-right: .5rem;         /* jarak ikon ke teks */
    }

    /* Sub menu (pills) juga rata kiri */
    .navbar-vertical .sub-nav .nav-link {
      justify-content: flex-start;
      text-align: left;
    }

      aside.navbar-vertical .navbar-nav .nav-link{
    justify-content: flex-start !important; /* stop centering */
    text-align: left !important;
  }

    /* icon jangan bikin center */
    aside.navbar-vertical .navbar-nav .nav-link .nav-link-icon{
      margin-right: .5rem;
      justify-content: flex-start !important;
    }

    /* title selalu rata kiri */
    aside.navbar-vertical .navbar-nav .nav-link .nav-link-title{
      text-align: left !important;
      flex: 1 1 auto;
    }

    /* submenu list jangan ketengah */
    aside.navbar-vertical .sub-nav{
      margin-left: 0 !important;       /* buang offset ms-4 kalau bikin aneh */
      padding-left: 2rem !important;   /* indent konsisten untuk submenu */
    }

    aside.navbar-vertical .sub-nav .nav-link{
      justify-content: flex-start !important;
      text-align: left !important;
    }

    /* ===== Topbar layering & alignment ===== */
    .navbar-vertical { z-index: 1030; }        /* sidebar di atas */
    .navbar.sticky-top { z-index: 1020; }      /* topbar di bawah sidebar */
    @media (min-width: 992px){
      .navbar.sticky-top .container-xl { padding-left: 280px; } /* sejajarkan brand dengan konten */
    }

    /* Mobile fallback: biar nested menu tidak kepotong */
    @media (max-width: 576px) {
      .dropdown-menu .dropdown-menu {
        position: static !important;
        float: none;
        margin: .25rem 0 .5rem 1rem;
      }
    }

    /* Pastikan dropdown TomSelect tidak ketutup komponen lain */
    .ts-dropdown{ z-index:1060; }

    .card-footer {
      position: sticky;
      bottom: 0;
      z-index: 5;
      background: var(--tblr-body-bg, #fff);
      border-top: 1px solid var(--tblr-border-color, #e9ecef);
    }

    /* Global spacing utk semua modal admin yang kita buat lewat JS */
    #adminModal{
      /* jarak atas/bawah responsif */
      --bs-modal-margin: clamp(56px, 9vh, 140px);
    }
    #adminModal .modal-dialog{
      /* pastikan margin top/bottom benar2 pakai var dan tak ditimpa */
      margin-top: var(--bs-modal-margin) !important;
      margin-bottom: var(--bs-modal-margin) !important;

      /* ruang kiri/kanan */
      max-width: min(720px, 92vw);
      margin-left: auto !important;
      margin-right: auto !important;
    }
    #adminModal .modal-content{ border-radius: 16px; }

    /* (opsional) tambahkan padding konten biar terasa lebih lega */
    #adminModal .modal-header,
    #adminModal .modal-footer{ padding: .875rem 1.25rem; }
    #adminModal .card-body{ padding: 1rem 1.25rem; }
  </style>

  @stack('styles')
  @stack('head')
</head>
<body class="layout-fluid theme-light">
  <!-- Top Navbar -->
  <header class="navbar navbar-expand-md bg-white sticky-top border-bottom">
    <div class="container-xl">

      {{-- Brand topbar: teks saja --}}
      @php
        $appName = config('app.name', 'ICPOS');
        try {
          if (class_exists(\App\Models\Setting::class)) {
            $appName = \App\Models\Setting::get('company.name', $appName);
          }
        } catch (\Throwable $e) { /* ignore */ }
      @endphp
      <a class="navbar-brand fw-semibold" href="{{ route('dashboard') }}">
        <span>{{ $appName }}</span>
      </a>

      <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#topnav">
        <span class="navbar-toggler-icon"></span>
      </button>

      <div class="collapse navbar-collapse" id="topnav">
        <ul class="navbar-nav ms-auto">
          @auth
            <li class="nav-item">
              <a class="nav-link {{ request()->routeIs('dashboard') ? 'active' : '' }}" href="{{ route('dashboard') }}">Dashboard</a>
            </li>

            {{-- ============ ADMIN MENU — hanya Super Admin ============ --}}
            @hasanyrole('SuperAdmin|Super Admin')
              @php
                $isAdminArea = request()->routeIs('users.*')
                              || request()->routeIs('permissions.*')
                              || request()->routeIs('companies.*')
                              || request()->routeIs('units.*')
                              || request()->routeIs('jenis.*')
                              || request()->routeIs('brands.*')
                              || request()->routeIs('po.*')
                              || request()->routeIs('gr.*')
                              || request()->routeIs('settings.*');
              @endphp

              <li class="nav-item dropdown">
                <a class="nav-link dropdown-toggle {{ $isAdminArea ? 'active' : '' }}"
                  data-bs-toggle="dropdown"
                  data-bs-auto-close="outside"
                  href="#" role="button">
                  Admin
                </a>

                <ul class="dropdown-menu dropdown-menu-end" id="admin-menu">
                  {{-- Hanya Super Admin --}}
                  <li><a class="dropdown-item {{ request()->routeIs('users.*') ? 'active' : '' }}" href="{{ route('users.index') }}">Manage Users</a></li>
                  <li><a class="dropdown-item {{ request()->routeIs('companies.*') ? 'active' : '' }}" href="{{ route('companies.index') }}">Companies</a></li>
                  <li><a class="dropdown-item {{ request()->routeIs('permissions.*') ? 'active' : '' }}" href="{{ route('permissions.index') }}">Roles &amp; Permissions</a></li>

                  <li><hr class="dropdown-divider"></li>

                  {{-- Master Data (collapse di dalam dropdown) --}}
                  <li>
                    <button type="button"
                            class="dropdown-item d-flex align-items-center justify-content-between"
                            data-bs-toggle="collapse"
                            data-bs-target="#md-collapse"
                            aria-expanded="false"
                            aria-controls="md-collapse">
                      Master Data
                      <span class="ms-2 caret">▾</span>
                    </button>

                    <div class="collapse" id="md-collapse" data-bs-parent="#admin-menu">
                      <ul class="list-unstyled dropdown-submenu m-0">
                        <li><a href="{{ route('units.index')  }}"  class="dropdown-item {{ request()->routeIs('units.*')  ? 'active' : '' }}">Units</a></li>
                        <li><a href="{{ route('jenis.index')  }}"  class="dropdown-item {{ request()->routeIs('jenis.*')  ? 'active' : '' }}">Jenis</a></li>
                        <li><a href="{{ route('brands.index') }}" class="dropdown-item {{ request()->routeIs('brands.*') ? 'active' : '' }}">Brands</a></li>
                        <li><a href="{{ route('sizes.index')  }}"  class="dropdown-item {{ request()->routeIs('sizes.*')  ? 'active' : '' }}">Sizes</a></li>
                        <li><a href="{{ route('colors.index') }}" class="dropdown-item {{ request()->routeIs('colors.*') ? 'active' : '' }}">Colors</a></li>
                        <li><a href="{{ route('warehouses.index') }}" class="dropdown-item {{ request()->routeIs('warehouses.*') ? 'active' : '' }}">Warehouses</a></li>
                        <li><a href="{{ route('banks.index') }}" class="dropdown-item {{ request()->routeIs('banks.*') ? 'active' : '' }}">Banks</a></li>
                      </ul>
                    </div>
                  </li>

                  <li><hr class="dropdown-divider"></li>

                  {{-- Global Settings --}}
                  <li>
                    <a class="dropdown-item {{ request()->routeIs('settings.*') ? 'active' : '' }}"
                      href="{{ route('settings.edit') }}">
                      Global Settings
                    </a>
                  </li>
                </ul>
              </li>
            @endhasanyrole


            {{-- User dropdown --}}
            <li class="nav-item dropdown">
              @php
                $avatarUrl = auth()->user()->profile_image_path
                  ? asset('storage/'.auth()->user()->profile_image_path)
                  : null;
              @endphp

              <a class="nav-link dropdown-toggle d-flex align-items-center" data-bs-toggle="dropdown" href="#">
                <span class="avatar avatar-sm rounded-circle avatar-ring me-2"
                      @if($avatarUrl) style="background-image:url('{{ $avatarUrl }}')" @endif>
                  @unless($avatarUrl)
                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20"
                         viewBox="0 0 24 24" fill="currentColor" class="opacity-50">
                      <path d="M12 12c2.761 0 5-2.239 5-5S14.761 2 12 2 7 4.239 7 7s2.239 5 5 5zm0 2c-3.866 0-7 3.134-7 7h14c0-3.866-3.134-7-7-7z"/>
                    </svg>
                  @endunless
                </span>
                {{ Auth::user()->name }}
              </a>

              <div class="dropdown-menu dropdown-menu-end">
                <div class="dropdown-item text-muted">
                  Roles: {{ auth()->user()->getRoleNames()->implode(', ') ?: '-' }}
                </div>
                <a class="dropdown-item" href="{{ route('profile.edit') }}">Profil</a>
                <form class="m-0" method="POST" action="{{ route('logout') }}">
                  @csrf
                  <button class="dropdown-item" type="submit">Logout</button>
                </form>
              </div>
            </li>
          @endauth

          @guest
            <li class="nav-item"><a class="nav-link" href="{{ route('login') }}">Login</a></li>
          @endguest
        </ul>
      </div>
    </div>
  </header>

  <div class="page">
    @includeIf('layouts.partials.sidebar')

    <div class="page-wrapper">
      <div class="container-xl py-3">
        @include('layouts.partials.flash')
        {{ $slot ?? '' }}
        @yield('content')
      </div>
    </div>
  </div>

  {{-- Tabler JS --}}
  <script src="https://cdn.jsdelivr.net/npm/@tabler/core@1.0.0-beta20/dist/js/tabler.min.js" defer></script>

  {{-- TomSelect JS (harus diletakkan SEBELUM @stack('scripts')) --}}
  <script src="https://cdn.jsdelivr.net/npm/tom-select/dist/js/tom-select.complete.min.js"></script>

  <script>
    document.addEventListener('DOMContentLoaded', () => {
      document.querySelectorAll('.alert.js-flash:not(.is-sticky)').forEach(el => {
        const delay = parseInt(el.dataset.delay || '5000', 10);
        setTimeout(() => {
          el.style.transition = 'opacity .25s';
          el.style.opacity = '0';
          setTimeout(() => el.remove(), 300);
        }, delay);
      });
    });
    </script>

  {{-- Backdrop cleanup (opsional) --}}
  <script>
  (function () {
    function cleanupBackdrops() {
      document.querySelectorAll('.modal-backdrop').forEach(el => el.remove());
      document.body.classList.remove('modal-open');
      document.body.style.overflow = '';
      document.body.style.paddingRight = '';
    }
    cleanupBackdrops();
    document.addEventListener('hidden.bs.modal', cleanupBackdrops);
    document.addEventListener('shown.bs.modal', function () {
      const backs = document.querySelectorAll('.modal-backdrop.show');
      if (backs.length > 1) backs.forEach((b, i) => { if (i < backs.length - 1) b.remove(); });
    });
    function pageTapFailsafe() {
      const anyOpen = document.querySelector('.modal.show');
      const leftOver = document.querySelector('.modal-backdrop');
      if (!anyOpen && leftOver) cleanupBackdrops();
    }
    document.addEventListener('click', pageTapFailsafe, true);
    document.addEventListener('touchstart', pageTapFailsafe, true);
    document.addEventListener('keydown', e => { if (e.key === 'Escape') cleanupBackdrops(); });
    window.addEventListener('load', cleanupBackdrops);
  })();
  </script>

  {{-- Helper collapse submenu + caret --}}
  <script>
  document.addEventListener('DOMContentLoaded', function () {
    const collapseEl = document.getElementById('md-collapse');
    const caret = document.querySelector('[data-bs-target="#md-collapse"] .caret');

    if (collapseEl && caret) {
      collapseEl.addEventListener('show.bs.collapse', () => caret.classList.add('rotated'));
      collapseEl.addEventListener('hide.bs.collapse', () => caret.classList.remove('rotated'));
    }

    // Saat dropdown Admin ditutup, pastikan collapse juga ikut tertutup
    document.querySelectorAll('.dropdown').forEach(function (dd) {
      dd.addEventListener('hide.bs.dropdown', function () {
        const shown = this.querySelector('.collapse.show');
        if (shown) new bootstrap.Collapse(shown, { toggle: false }).hide();
      });
    });
  });
  </script>

  <script>
    (function () {
      function storageAvailable() {
        try {
          const s = window.localStorage;
          const k = "__t__";
          s.setItem(k, "1");
          s.removeItem(k);
          return true;
        } catch (e) {
          return false;
        }
      }

      window.safeStorage = storageAvailable()
        ? window.localStorage
        : { getItem: () => null, setItem: () => {}, removeItem: () => {}, clear: () => {} };
    })();
    </script>

  {{-- Mobile: auto-close sidebar offcanvas tanpa blok navigasi --}}
  <script>
    document.addEventListener('click', function (e) {
      const link = e.target.closest('#sidebar-menu a[href]');
      if (!link) return;

      const el = document.getElementById('sidebar-menu');
      if (!el) return;

      // Kalau sedang offcanvas (mobile), close setelah klik. Jangan preventDefault.
      const instance = bootstrap.Offcanvas.getInstance(el) || new bootstrap.Offcanvas(el);
      instance.hide();
    });
  </script>

  {{-- Tempat script halaman (termasuk _item_quicksearch_js.blade.php) --}}
  @stack('scripts')
</body>
</html>
