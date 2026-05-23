<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Gemilang') | License Server</title>
    <link rel="shortcut icon" type="image/png" href="{{ asset('favicon.png') }}">
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        :root {
            --primary: #1a3a6b;
            --primary-mid: #29abe2;
            --primary-light: #eff6ff;
            --sidebar-w: 240px;
            --header-h: 56px;
            --text: #1e293b;
            --text-muted: #64748b;
            --border: #e2e8f0;
            --bg: #f1f5f9;
            --card-bg: #ffffff;
            --success: #16a34a;
            --danger: #dc2626;
            --warning: #d97706;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: var(--bg);
            color: var(--text);
            font-size: 14px;
            line-height: 1.5;
        }

        /* ── Sidebar ── */
        .sidebar {
            position: fixed;
            top: 0; left: 0;
            width: var(--sidebar-w);
            height: 100vh;
            background: var(--primary);
            display: flex;
            flex-direction: column;
            z-index: 100;
            overflow-y: auto;
        }

        .sidebar-brand {
            display: flex;
            align-items: center;
            gap: .6rem;
            padding: 1rem 1.25rem;
            background: #fff;
            border-bottom: 3px solid var(--primary-mid);
            flex-shrink: 0;
        }

        .sidebar-brand-icon {
            width: 32px; height: 32px;
            background: var(--primary);
            border-radius: 8px;
            display: flex; align-items: center; justify-content: center;
            color: #fff; font-size: 1rem; font-weight: 800;
            flex-shrink: 0;
        }

        .sidebar-brand-text {
            font-size: .85rem;
            font-weight: 800;
            color: var(--primary);
            letter-spacing: .02em;
            line-height: 1.2;
        }

        .sidebar-brand-sub {
            font-size: .6rem;
            color: var(--text-muted);
            font-weight: 500;
            letter-spacing: .04em;
            text-transform: uppercase;
        }

        .sidebar-nav {
            flex: 1;
            padding: 1rem 0;
            list-style: none;
        }

        .sidebar-nav-title {
            padding: .5rem 1.25rem .25rem;
            font-size: .6rem;
            font-weight: 700;
            color: rgba(255,255,255,.35);
            text-transform: uppercase;
            letter-spacing: .1em;
        }

        .sidebar-nav a {
            display: flex;
            align-items: center;
            gap: .65rem;
            padding: .6rem 1.25rem;
            color: rgba(255,255,255,.75);
            text-decoration: none;
            font-size: .82rem;
            font-weight: 500;
            border-left: 3px solid transparent;
            transition: all .15s ease;
        }

        .sidebar-nav a:hover {
            background: rgba(255,255,255,.1);
            color: #fff;
        }

        .sidebar-nav a.active {
            background: rgba(41,171,226,.2);
            color: #fff;
            border-left-color: var(--primary-mid);
            font-weight: 600;
        }

        .sidebar-nav a .nav-icon {
            width: 18px; height: 18px;
            display: flex; align-items: center; justify-content: center;
            flex-shrink: 0;
            opacity: .8;
        }

        .sidebar-footer {
            padding: 1rem 1.25rem;
            border-top: 1px solid rgba(255,255,255,.1);
            flex-shrink: 0;
        }

        .sidebar-user {
            display: flex;
            align-items: center;
            gap: .6rem;
        }

        .sidebar-user-avatar {
            width: 32px; height: 32px;
            border-radius: 50%;
            background: var(--primary-mid);
            display: flex; align-items: center; justify-content: center;
            color: #fff; font-size: .75rem; font-weight: 700;
            flex-shrink: 0;
        }

        .sidebar-user-name {
            font-size: .78rem;
            font-weight: 600;
            color: #fff;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .sidebar-user-role {
            font-size: .65rem;
            color: rgba(255,255,255,.5);
        }

        .sidebar-logout {
            display: flex;
            align-items: center;
            gap: .4rem;
            margin-top: .6rem;
            padding: .4rem .6rem;
            background: rgba(255,255,255,.08);
            border: 1px solid rgba(255,255,255,.12);
            border-radius: 7px;
            color: rgba(255,255,255,.65);
            font-size: .72rem;
            font-weight: 500;
            cursor: pointer;
            text-decoration: none;
            transition: all .15s;
            width: 100%;
            justify-content: center;
        }

        .sidebar-logout:hover {
            background: rgba(255,255,255,.15);
            color: #fff;
        }

        /* ── Main ── */
        .main-wrapper {
            margin-left: var(--sidebar-w);
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }

        .main-header {
            height: var(--header-h);
            background: var(--card-bg);
            border-bottom: 1px solid var(--border);
            display: flex;
            align-items: center;
            padding: 0 1.5rem;
            gap: 1rem;
            position: sticky;
            top: 0;
            z-index: 50;
        }

        .main-header h1 {
            font-size: 1rem;
            font-weight: 700;
            color: var(--text);
            flex: 1;
        }

        .breadcrumb {
            display: flex;
            align-items: center;
            gap: .35rem;
            font-size: .72rem;
            color: var(--text-muted);
        }

        .breadcrumb a { color: var(--primary); text-decoration: none; }
        .breadcrumb a:hover { text-decoration: underline; }
        .breadcrumb-sep { color: var(--border); }

        .main-content {
            flex: 1;
            padding: 1.5rem;
        }

        /* ── Cards ── */
        .card {
            background: var(--card-bg);
            border-radius: 12px;
            border: 1px solid var(--border);
            box-shadow: 0 1px 3px rgba(0,0,0,.04);
        }

        .card-header {
            padding: 1rem 1.25rem;
            border-bottom: 1px solid var(--border);
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 1rem;
        }

        .card-title {
            font-size: .9rem;
            font-weight: 700;
            color: var(--text);
        }

        .card-body { padding: 1.25rem; }

        /* ── Alerts ── */
        .alert {
            padding: .75rem 1rem;
            border-radius: 8px;
            font-size: .82rem;
            margin-bottom: 1rem;
            display: flex;
            align-items: flex-start;
            gap: .5rem;
        }

        .alert-success { background: #f0fdf4; color: #166534; border: 1px solid #bbf7d0; }
        .alert-danger  { background: #fef2f2; color: #991b1b; border: 1px solid #fecaca; }
        .alert-warning { background: #fffbeb; color: #92400e; border: 1px solid #fde68a; }
        .alert-info    { background: #eff6ff; color: #1e40af; border: 1px solid #bfdbfe; }

        /* ── Buttons ── */
        .btn {
            display: inline-flex;
            align-items: center;
            gap: .35rem;
            padding: .45rem .9rem;
            border-radius: 8px;
            font-size: .78rem;
            font-weight: 600;
            cursor: pointer;
            border: 1.5px solid transparent;
            text-decoration: none;
            transition: all .15s ease;
            white-space: nowrap;
        }

        .btn-primary {
            background: var(--primary);
            color: #fff;
            border-color: var(--primary);
        }
        .btn-primary:hover { background: #1e4a8a; border-color: #1e4a8a; }

        .btn-secondary {
            background: #f8fafc;
            color: var(--text);
            border-color: var(--border);
        }
        .btn-secondary:hover { background: #f1f5f9; }

        .btn-success { background: var(--success); color: #fff; border-color: var(--success); }
        .btn-success:hover { background: #15803d; }

        .btn-danger { background: var(--danger); color: #fff; border-color: var(--danger); }
        .btn-danger:hover { background: #b91c1c; }

        .btn-warning { background: var(--warning); color: #fff; border-color: var(--warning); }
        .btn-warning:hover { background: #b45309; }

        .btn-sm { padding: .3rem .65rem; font-size: .72rem; }

        /* ── Badges ── */
        .badge {
            display: inline-flex;
            align-items: center;
            padding: .2rem .55rem;
            border-radius: 20px;
            font-size: .65rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: .04em;
        }

        .badge-success { background: #dcfce7; color: #166534; }
        .badge-danger  { background: #fee2e2; color: #991b1b; }
        .badge-warning { background: #fef3c7; color: #92400e; }
        .badge-secondary { background: #f1f5f9; color: #475569; }
        .badge-info    { background: #dbeafe; color: #1e40af; }

        /* ── Tables ── */
        .table-wrap { overflow-x: auto; }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        table thead th {
            background: var(--primary);
            color: #fff;
            font-size: .68rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: .05em;
            padding: .6rem .85rem;
            text-align: left;
            white-space: nowrap;
        }

        table thead th:first-child { border-radius: 6px 0 0 0; }
        table thead th:last-child  { border-radius: 0 6px 0 0; }

        table tbody td {
            padding: .65rem .85rem;
            border-bottom: 1px solid var(--border);
            font-size: .8rem;
            color: var(--text);
            vertical-align: middle;
        }

        table tbody tr:last-child td { border-bottom: none; }
        table tbody tr:hover td { background: #f8fafc; }

        /* ── Forms ── */
        .form-group { margin-bottom: 1rem; }

        .form-label {
            display: block;
            font-size: .72rem;
            font-weight: 700;
            color: #475569;
            margin-bottom: .35rem;
            text-transform: uppercase;
            letter-spacing: .03em;
        }

        .form-control {
            width: 100%;
            padding: .55rem .8rem;
            border: 1.5px solid var(--border);
            border-radius: 8px;
            font-size: .82rem;
            color: var(--text);
            background: #f8fafc;
            transition: all .2s;
            outline: none;
        }

        .form-control:focus {
            border-color: var(--primary-mid);
            background: #fff;
            box-shadow: 0 0 0 3px rgba(41,171,226,.12);
        }

        .form-control.is-invalid { border-color: var(--danger); }
        .invalid-feedback { font-size: .7rem; color: var(--danger); margin-top: .25rem; }

        /* ── Stat cards ── */
        .stat-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 1rem;
            margin-bottom: 1.5rem;
        }

        .stat-card {
            background: var(--card-bg);
            border-radius: 12px;
            border: 1px solid var(--border);
            padding: 1.25rem;
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .stat-icon {
            width: 44px; height: 44px;
            border-radius: 10px;
            display: flex; align-items: center; justify-content: center;
            font-size: 1.2rem;
            flex-shrink: 0;
        }

        .stat-icon-blue   { background: #eff6ff; color: var(--primary); }
        .stat-icon-green  { background: #f0fdf4; color: var(--success); }
        .stat-icon-red    { background: #fef2f2; color: var(--danger); }
        .stat-icon-yellow { background: #fffbeb; color: var(--warning); }

        .stat-value { font-size: 1.5rem; font-weight: 800; color: var(--text); line-height: 1; }
        .stat-label { font-size: .72rem; color: var(--text-muted); margin-top: .2rem; }

        /* ── Pagination ── */
        .pagination {
            display: flex;
            align-items: center;
            gap: .25rem;
            list-style: none;
            padding: 1rem 0 0;
            justify-content: flex-end;
        }

        .pagination a, .pagination span {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 32px; height: 32px;
            border-radius: 7px;
            font-size: .75rem;
            font-weight: 600;
            border: 1.5px solid var(--border);
            color: var(--text);
            text-decoration: none;
            transition: all .15s;
        }

        .pagination a:hover { border-color: var(--primary); color: var(--primary); }
        .pagination .active span { background: var(--primary); border-color: var(--primary); color: #fff; }
        .pagination .disabled span { opacity: .4; cursor: not-allowed; }

        /* ── Responsive ── */
        @media (max-width: 768px) {
            .sidebar { transform: translateX(-100%); }
            .main-wrapper { margin-left: 0; }
        }
    </style>
    @stack('styles')
</head><body>
    <!-- Sidebar -->
    <aside class="sidebar">
        <div class="sidebar-brand">
            <div class="sidebar-brand-icon">G</div>
            <div>
                <div class="sidebar-brand-text">Gemilang</div>
                <div class="sidebar-brand-sub">License Server</div>
            </div>
        </div>

        <ul class="sidebar-nav">
            <li class="sidebar-nav-title">Main</li>
            <li>
                <a href="{{ route('dashboard') }}" class="{{ request()->routeIs('dashboard') ? 'active' : '' }}">
                    <span class="nav-icon"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/></svg></span>
                    Home
                </a>
            </li>

            <li class="sidebar-nav-title">Master Data</li>
            <li>
                <a href="{{ route('master.companies.index') }}" class="{{ request()->routeIs('master.companies.*') ? 'active' : '' }}">
                    <span class="nav-icon"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg></span>
                    Companies
                </a>
            </li>
            <li>
                <a href="{{ route('master.apps.index') }}" class="{{ request()->routeIs('master.apps.*') ? 'active' : '' }}">
                    <span class="nav-icon"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="3" width="20" height="14" rx="2"/><line x1="8" y1="21" x2="16" y2="21"/><line x1="12" y1="17" x2="12" y2="21"/></svg></span>
                    Apps
                </a>
            </li>
            <li>
                <a href="{{ route('master.configs.index') }}" class="{{ request()->routeIs('master.configs.*') ? 'active' : '' }}">
                    <span class="nav-icon"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="3"/><path d="M19.07 4.93a10 10 0 0 1 0 14.14M4.93 4.93a10 10 0 0 0 0 14.14"/></svg></span>
                    Configs
                </a>
            </li>
            <li>
                <a href="{{ route('master.flags.index') }}" class="{{ request()->routeIs('master.flags.*') ? 'active' : '' }}">
                    <span class="nav-icon"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 15s1-1 4-1 5 2 8 2 4-1 4-1V3s-1 1-4 1-5-2-8-2-4 1-4 1z"/><line x1="4" y1="22" x2="4" y2="15"/></svg></span>
                    Feature Flags
                </a>
            </li>

            <li class="sidebar-nav-title">Licensing</li>
            <li>
                <a href="{{ route('license.companies.index') }}" class="{{ request()->routeIs('license.companies.*') ? 'active' : '' }}">
                    <span class="nav-icon"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg></span>
                    Licenses
                </a>
            </li>
            <li>
                <a href="{{ route('license.installations.index') }}" class="{{ request()->routeIs('license.installations.*') ? 'active' : '' }}">
                    <span class="nav-icon"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="2" width="20" height="8" rx="2"/><rect x="2" y="14" width="20" height="8" rx="2"/><line x1="6" y1="6" x2="6.01" y2="6"/><line x1="6" y1="18" x2="6.01" y2="18"/></svg></span>
                    Installations
                </a>
            </li>

            <li class="sidebar-nav-title">Admin</li>
            <li>
                <a href="{{ route('users.index') }}" class="{{ request()->routeIs('users.*') ? 'active' : '' }}">
                    <span class="nav-icon"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg></span>
                    Users
                </a>
            </li>
        </ul>

        <div class="sidebar-footer">
            <div class="sidebar-user">
                <div class="sidebar-user-avatar">{{ strtoupper(substr(auth()->user()->name ?? 'A', 0, 1)) }}</div>
                <div style="flex:1;min-width:0;">
                    <div class="sidebar-user-name">{{ auth()->user()->name ?? 'Admin' }}</div>
                    <div class="sidebar-user-role">Administrator</div>
                </div>
            </div>
            <form method="POST" action="{{ route('logout') }}" style="margin-top:.5rem;">
                @csrf
                <button type="submit" class="sidebar-logout">
                    <svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
                    Sign Out
                </button>
            </form>
        </div>
    </aside>

    <!-- Main -->
    <div class="main-wrapper">
        <header class="main-header">
            <h1>@yield('page-title', 'Dashboard')</h1>
            @hasSection('breadcrumb')
            <nav class="breadcrumb">
                @yield('breadcrumb')
            </nav>
            @endif
        </header>

        <main class="main-content">
            @if(session('success'))
            <div class="alert alert-success">
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
                {{ session('success') }}
            </div>
            @endif

            @if(session('error') || $errors->any())
            <div class="alert alert-danger">
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                <div>
                    @if(session('error')){{ session('error') }}@endif
                    @foreach($errors->all() as $e)<div>{{ $e }}</div>@endforeach
                </div>
            </div>
            @endif

            @yield('content')
        </main>
    </div>

    @stack('scripts')
</body>
</html>