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
    @stack('styles')

    <style>
        body {
            background-color: #f0f4f8;
            font-family: 'Segoe UI', sans-serif;
        }

        /* Top navbar */
        .navbar-brand {
            font-weight: 700;
            font-size: 1.3rem;
            letter-spacing: 0.5px;
        }

        /* Page card */
        .form-card {
            border: none;
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
        }

        .form-card .card-header {
            background: linear-gradient(135deg, #1F618D, #2E86AB);
            border-radius: 12px 12px 0 0 !important;
            padding: 1.4rem 1.6rem;
        }

        .form-label {
            font-weight: 600;
            font-size: 0.875rem;
            color: #374151;
        }

        .form-control:focus,
        .form-select:focus {
            border-color: #1F618D;
            box-shadow: 0 0 0 0.2rem rgba(31,97,141,0.15);
        }

        .btn-submit {
            background: linear-gradient(135deg, #1F618D, #2E86AB);
            border: none;
            padding: 0.6rem 2rem;
            font-weight: 600;
            letter-spacing: 0.3px;
        }

        .btn-submit:hover {
            background: linear-gradient(135deg, #185282, #236d8f);
        }

        .btn-reset {
            border: 2px solid #6B7280;
            color: #6B7280;
            padding: 0.6rem 2rem;
            font-weight: 600;
        }

        .btn-reset:hover {
            background: #6B7280;
            color: white;
        }

        .required-star { color: #DC2626; }

        footer {
            font-size: 0.82rem;
            color: #9CA3AF;
        }

        .site-navbar {
            background: #1F618D;
            box-shadow: 0 2px 12px rgba(15, 23, 42, 0.15);
        }

        .site-navbar .navbar-brand {
            white-space: nowrap;
        }

        .site-navbar .nav-link,
        .site-navbar .dropdown-toggle {
            color: rgba(255, 255, 255, 0.88);
            font-size: 0.9rem;
            font-weight: 500;
            white-space: nowrap;
        }

        .site-navbar .nav-link:hover,
        .site-navbar .nav-link:focus,
        .site-navbar .dropdown-toggle:hover,
        .site-navbar .dropdown-toggle:focus {
            color: #fff;
        }

        .site-navbar .dropdown-menu {
            border: 0;
            border-radius: 0.75rem;
            box-shadow: 0 12px 30px rgba(15, 23, 42, 0.16);
        }

        @media (max-width: 1399.98px) {
            .site-navbar .navbar-collapse {
                padding: 0.75rem 0 0.25rem;
            }

            .site-navbar .navbar-nav {
                align-items: flex-start !important;
            }
        }
    </style>
</head>
<body>

    <!-- Navbar -->
    <nav class="navbar navbar-expand-xxl navbar-dark site-navbar">
        <div class="container flex-wrap">
            <a href="{{ route('home') }}" class="navbar-brand">
                <i class="bi bi-book me-2"></i>Library Management System
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#mainNavigation" aria-controls="mainNavigation" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="mainNavigation">
                <ul class="navbar-nav me-auto mb-2 mb-xxl-0 align-items-xxl-center gap-xxl-1">
                    <li class="nav-item">
                        <a href="{{ route('catalog.index') }}" class="nav-link">Browse</a>
                    </li>
                @auth
                    @php($unreadNotificationCount = auth()->user()->unreadNotifications()->count())
                    @if (auth()->user()->isAdmin())
                        <li class="nav-item">
                            <a href="{{ route('dashboard') }}" class="nav-link"><i class="bi bi-grid-1x2-fill me-1"></i>Dashboard</a>
                        </li>
                        <li class="nav-item dropdown">
                            <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false">Catalog</a>
                            <ul class="dropdown-menu">
                                <li><a class="dropdown-item" href="{{ route('catalog.admin.index') }}">Manage books</a></li>
                                <li><a class="dropdown-item" href="{{ route('catalog.categories.index') }}">Categories</a></li>
                            </ul>
                        </li>
                        <li class="nav-item dropdown">
                            <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false">Circulation</a>
                            <ul class="dropdown-menu">
                                <li><a class="dropdown-item" href="{{ route('loans.index') }}">Loans</a></li>
                                <li><a class="dropdown-item" href="{{ route('reservations.index') }}">Reservations</a></li>
                                <li><a class="dropdown-item" href="{{ route('members.index') }}">Members</a></li>
                            </ul>
                        </li>
                        <li class="nav-item dropdown">
                            <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false">Reports</a>
                            <ul class="dropdown-menu">
                                <li><a class="dropdown-item" href="{{ route('reports.index') }}">Operational reports</a></li>
                                <li><a class="dropdown-item" href="{{ route('reports.fines') }}">Fine report</a></li>
                            </ul>
                        </li>
                        <li class="nav-item dropdown">
                            <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false">Administration</a>
                            <ul class="dropdown-menu">
                                <li><a class="dropdown-item" href="{{ route('audit-logs.index') }}">Audit log</a></li>
                                <li><a class="dropdown-item" href="{{ route('library-policies.edit') }}">Library policies</a></li>
                            </ul>
                        </li>
                    @else
                        <li class="nav-item"><a href="{{ route('dashboard.member') }}" class="nav-link"><i class="bi bi-grid-1x2-fill me-1"></i>My dashboard</a></li>
                        <li class="nav-item"><a href="{{ route('loans.mine') }}" class="nav-link">My loans</a></li>
                        <li class="nav-item"><a href="{{ route('reservations.mine') }}" class="nav-link">My reservations</a></li>
                        <li class="nav-item"><a href="{{ route('members.index') }}" class="nav-link">Members</a></li>
                    @endif
                @endauth
                </ul>
                <div class="d-flex flex-column flex-xxl-row align-items-stretch align-items-xxl-center gap-2 gap-xxl-3 pb-2 pb-xxl-0">
                    @auth
                        <a href="{{ route('notifications.index') }}" class="nav-link px-0">
                            <i class="bi bi-bell me-1"></i>Notifications
                            @if ($unreadNotificationCount)
                                <span class="badge rounded-pill text-bg-danger">{{ $unreadNotificationCount }}</span>
                            @endif
                        </a>
                        <div class="dropdown">
                            <button class="btn btn-sm btn-outline-light dropdown-toggle d-flex align-items-center gap-2" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                                @if (auth()->user()->profile_photo_path)
                                    <img src="{{ Storage::disk('public')->url(auth()->user()->profile_photo_path) }}" alt="" class="rounded-circle" style="width: 28px; height: 28px; object-fit: cover;">
                                @else
                                    <i class="bi bi-person-circle"></i>
                                @endif
                                <span>{{ auth()->user()->name }}</span>
                            </button>
                            <ul class="dropdown-menu dropdown-menu-end">
                                <li><span class="dropdown-item-text small text-muted">{{ auth()->user()->isAdmin() ? 'Administrator' : 'Library member' }}</span></li>
                                <li><a class="dropdown-item" href="{{ route('profile.edit') }}">My profile</a></li>
                                <li><hr class="dropdown-divider"></li>
                                <li>
                                    <form action="{{ route('logout') }}" method="POST" class="px-3">
                                        @csrf
                                        <button class="btn btn-sm btn-outline-danger w-100" type="submit">Sign out</button>
                                    </form>
                                </li>
                            </ul>
                        </div>
                    @else
                        <a href="{{ route('login') }}" class="btn btn-sm btn-outline-light">Sign in</a>
                    @endauth
                </div>
            </div>
        </div>
    </nav>

    <!-- Page Content -->
    <div class="container py-4">
        @yield('content')
    </div>

    <!-- Footer -->
    <footer class="text-center pb-4">
        <p class="mb-0">Library Management System &mdash; CS619 Final Project &mdash; Usama Ali (BC230206398)</p>
    </footer>

    <!-- Bootstrap 5 JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    @yield('scripts')
</body>
</html>
