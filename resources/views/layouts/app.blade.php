<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'Library Management System')</title>

    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" rel="stylesheet">
    <link href="{{ asset('css/library-theme.css') }}?v={{ filemtime(public_path('css/library-theme.css')) }}" rel="stylesheet">
    @stack('styles')

</head>
<body>

    @php($isAuthPage = request()->routeIs('login', 'register', 'signup', 'password.*') || request()->is('login', 'register', 'signup'))
    @auth
        @php($unreadNotificationCount = auth()->user()->unreadNotifications()->count())
    @endauth
    @unless ($isAuthPage)
    <aside class="offcanvas offcanvas-xl offcanvas-start site-sidebar" tabindex="-1" id="appSidebar" aria-labelledby="sidebarTitle">
        <div class="sidebar-heading">
            <a href="{{ route('home') }}" class="sidebar-brand" id="sidebarTitle">
                <span class="sidebar-brand-mark"><i class="bi bi-book-half"></i></span>
                <span>Library<span class="sidebar-brand-subtitle">Management System</span></span>
            </a>
            <button type="button" class="sidebar-toggle d-none d-xl-grid" id="sidebarToggle" aria-label="Close navigation" aria-controls="appSidebar" aria-expanded="true">
                <i class="bi bi-chevron-double-left"></i>
            </button>
            <button type="button" class="btn-close d-xl-none" data-bs-dismiss="offcanvas" aria-label="Close navigation"></button>
        </div>
        <div class="sidebar-content">
            <nav class="sidebar-nav" aria-label="Main navigation">
                <div class="sidebar-section-label">Explore</div>
                <a href="{{ route('catalog.index') }}" class="sidebar-link {{ request()->routeIs('catalog.index', 'catalog.show') ? 'active' : '' }}">
                    <i class="bi bi-compass"></i><span>Browse catalog</span>
                </a>
                @auth
                    @if (auth()->user()->isAdmin())
                        <div class="sidebar-section-label mt-4">Workspace</div>
                        <a href="{{ route('dashboard') }}" class="sidebar-link {{ request()->routeIs('dashboard') ? 'active' : '' }}">
                            <i class="bi bi-grid-1x2"></i><span>Dashboard</span>
                        </a>
                        <div class="sidebar-section-label mt-4">Collection</div>
                        <a href="{{ route('catalog.admin.index') }}" class="sidebar-link {{ request()->routeIs('catalog.admin.*') ? 'active' : '' }}">
                            <i class="bi bi-journal-bookmark"></i><span>Manage books</span>
                        </a>
                        <a href="{{ route('catalog.admin.import-form') }}" class="sidebar-link {{ request()->routeIs('catalog.admin.import*') ? 'active' : '' }}">
                            <i class="bi bi-file-earmark-arrow-up"></i><span>Bulk CSV import</span>
                        </a>
                        <a href="{{ route('inventory-audits.index') }}" class="sidebar-link {{ request()->routeIs('inventory-audits.*') ? 'active' : '' }}">
                            <i class="bi bi-upc-scan"></i><span>Inventory audits</span>
                        </a>
                        <a href="{{ route('catalog.categories.index') }}" class="sidebar-link {{ request()->routeIs('catalog.categories.*') ? 'active' : '' }}">
                            <i class="bi bi-tags"></i><span>Categories</span>
                        </a>
                        <div class="sidebar-section-label mt-4">Circulation</div>
                        <a href="{{ route('loans.index') }}" class="sidebar-link {{ request()->routeIs('loans.index', 'loans.create') ? 'active' : '' }}">
                            <i class="bi bi-arrow-left-right"></i><span>Loans</span>
                        </a>
                        <a href="{{ route('reservations.index') }}" class="sidebar-link {{ request()->routeIs('reservations.index') ? 'active' : '' }}">
                            <i class="bi bi-bookmark-star"></i><span>Reservations</span>
                        </a>
                        <a href="{{ route('book-requests.admin') }}" class="sidebar-link {{ request()->routeIs('book-requests.admin') ? 'active' : '' }}">
                            <i class="bi bi-inbox"></i><span>Book requests</span>
                        </a>
                        <a href="{{ route('book-reviews.reports') }}" class="sidebar-link {{ request()->routeIs('book-reviews.reports*') ? 'active' : '' }}">
                            <i class="bi bi-chat-square-text"></i><span>Review moderation</span>
                        </a>
                        <a href="{{ route('members.index') }}" class="sidebar-link {{ request()->routeIs('members.*') ? 'active' : '' }}">
                            <i class="bi bi-people"></i><span>Members</span>
                        </a>
                        <div class="sidebar-section-label mt-4">Insights</div>
                        <a href="{{ route('reports.index') }}" class="sidebar-link {{ request()->routeIs('reports.index') ? 'active' : '' }}">
                            <i class="bi bi-bar-chart-line"></i><span>Operational reports</span>
                        </a>
                        <a href="{{ route('reports.purchase-planning') }}" class="sidebar-link {{ request()->routeIs('reports.purchase-planning') ? 'active' : '' }}">
                            <i class="bi bi-cart-check"></i><span>Purchase planning</span>
                        </a>
                        <a href="{{ route('reports.fines') }}" class="sidebar-link {{ request()->routeIs('reports.fines') ? 'active' : '' }}">
                            <i class="bi bi-cash-coin"></i><span>Fine report</span>
                        </a>
                        <div class="sidebar-section-label mt-4">System</div>
                        <a href="{{ route('audit-logs.index') }}" class="sidebar-link {{ request()->routeIs('audit-logs.*') ? 'active' : '' }}">
                            <i class="bi bi-shield-check"></i><span>Audit log</span>
                        </a>
                        <a href="{{ route('library-policies.edit') }}" class="sidebar-link {{ request()->routeIs('library-policies.*') ? 'active' : '' }}">
                            <i class="bi bi-sliders"></i><span>Library policies</span>
                        </a>
                    @else
                        <div class="sidebar-section-label mt-4">My library</div>
                        <a href="{{ route('dashboard.member') }}" class="sidebar-link {{ request()->routeIs('dashboard.member') ? 'active' : '' }}">
                            <i class="bi bi-grid-1x2"></i><span>My dashboard</span>
                        </a>
                        <a href="{{ route('reading-lists.index') }}" class="sidebar-link {{ request()->routeIs('reading-lists.*') ? 'active' : '' }}">
                            <i class="bi bi-journals"></i><span>Course reading lists</span>
                        </a>
                        <a href="{{ route('loans.mine') }}" class="sidebar-link {{ request()->routeIs('loans.mine') ? 'active' : '' }}">
                            <i class="bi bi-book-half"></i><span>My loans</span>
                        </a>
                        <a href="{{ route('reservations.mine') }}" class="sidebar-link {{ request()->routeIs('reservations.mine') ? 'active' : '' }}">
                            <i class="bi bi-bookmark-star"></i><span>My reservations</span>
                        </a>
                        <a href="{{ route('book-requests.mine') }}" class="sidebar-link {{ request()->routeIs('book-requests.mine') ? 'active' : '' }}">
                            <i class="bi bi-inbox"></i><span>Book requests</span>
                        </a>
                        <a href="{{ route('members.index') }}" class="sidebar-link {{ request()->routeIs('members.index') ? 'active' : '' }}">
                            <i class="bi bi-people"></i><span>Members</span>
                        </a>
                    @endif
                @endauth
            </nav>
        </div>
    </aside>
    @endunless

    <div class="app-main{{ $isAuthPage ? ' app-main-auth' : '' }}">
        @unless ($isAuthPage)
        <header class="app-topbar">
            @auth
                <a href="{{ route('notifications.index') }}" class="topbar-icon-link" aria-label="Notifications{{ $unreadNotificationCount ? ', '.$unreadNotificationCount.' unread' : '' }}">
                    <i class="bi bi-bell"></i>
                    @if ($unreadNotificationCount)
                        <span class="badge rounded-pill">{{ $unreadNotificationCount }}</span>
                    @endif
                </a>
                <div class="dropdown">
                    <button class="topbar-profile dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                        @if (auth()->user()->profile_photo_path)
                            <img src="{{ Storage::disk('public')->url(auth()->user()->profile_photo_path) }}" alt="" class="topbar-avatar">
                        @else
                            <span class="topbar-avatar topbar-avatar-placeholder"><i class="bi bi-person"></i></span>
                        @endif
                        <span class="topbar-profile-name">{{ auth()->user()->name }}</span>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li><a class="dropdown-item" href="{{ route('profile.edit') }}"><i class="bi bi-person-gear me-2"></i>My profile</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li>
                            <form action="{{ route('logout') }}" method="POST" class="px-2">
                                @csrf
                                <button class="btn btn-sm btn-outline-danger w-100" type="submit">Sign out</button>
                            </form>
                        </li>
                    </ul>
                </div>
            @else
                <a href="{{ route('login') }}" class="btn btn-sm btn-outline-primary">Sign in</a>
            @endauth
        </header>
        <header class="mobile-topbar">
            <button class="btn mobile-menu-button" type="button" data-bs-toggle="offcanvas" data-bs-target="#appSidebar" aria-controls="appSidebar" aria-label="Open navigation">
                <i class="bi bi-list"></i>
            </button>
            <a href="{{ route('home') }}" class="mobile-brand"><i class="bi bi-book-half me-2"></i>Library</a>
            @auth
                <a href="{{ route('notifications.index') }}" class="mobile-notifications" aria-label="Notifications">
                    <i class="bi bi-bell"></i>
                    @if ($unreadNotificationCount)<span class="badge rounded-pill">{{ $unreadNotificationCount }}</span>@endif
                </a>
                <a href="{{ route('profile.edit') }}" class="mobile-profile" aria-label="My profile">
                    @if (auth()->user()->profile_photo_path)
                        <img src="{{ Storage::disk('public')->url(auth()->user()->profile_photo_path) }}" alt="" class="topbar-avatar">
                    @else
                        <span class="topbar-avatar topbar-avatar-placeholder"><i class="bi bi-person"></i></span>
                    @endif
                </a>
            @else
                <a href="{{ route('login') }}" class="mobile-sign-in">Sign in</a>
            @endauth
        </header>
        @endunless
        <main class="container app-shell">
            @yield('content')
        </main>
        <footer class="site-footer">
            <p class="mb-0">Library Management System &mdash; CS619 Final Project &mdash; Usama Ali (BC230206398)</p>
        </footer>
    </div>

    <!-- Bootstrap 5 JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        var sidebarToggle = document.getElementById('sidebarToggle');

        if (sidebarToggle) {
            sidebarToggle.onclick = function () {
                var isCollapsed = document.body.classList.toggle('sidebar-collapsed');

                sidebarToggle.setAttribute('aria-expanded', String(!isCollapsed));
                sidebarToggle.setAttribute('aria-label', isCollapsed ? 'Open navigation' : 'Close navigation');
            };
        }

        var sidebarLinks = document.querySelectorAll('.site-sidebar .sidebar-link');

        for (var i = 0; i < sidebarLinks.length; i++) {
            var label = sidebarLinks[i].querySelector('span');

            if (label) {
                sidebarLinks[i].setAttribute('title', label.textContent.trim());
            }
        }
    </script>
    @yield('scripts')
</body>
</html>
