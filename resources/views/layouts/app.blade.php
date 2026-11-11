<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=5" />
  <meta name="csrf-token" content="{{ csrf_token() }}" />
  <title>@yield('title', 'Workspace') · GardaCyber</title>
  <meta name="description" content="GardaCyber attack surface workspace" />
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link href="https://fonts.googleapis.com/css2?family=JetBrains+Mono:wght@400;500;600;700&family=Manrope:wght@400;500;600;700;800&family=Space+Grotesk:wght@400;500;600;700&display=swap" rel="stylesheet" />
  <link rel="stylesheet" href="{{ asset('css/app.css') }}" />
  <link rel="stylesheet" href="{{ asset('css/console.css') }}" />
</head>
<body class="console-body">
  <a class="skip-link" href="#main-content">Skip to content</a>

  <div class="console">
    <aside class="console-sidebar" id="consoleSidebar">
      <div class="sidebar-top">
        <a class="brand" href="{{ route('dashboard') }}">
          <span class="garda-mark" aria-hidden="true">G</span>
          <span class="brand-name">GardaCyber<span class="brand-period">.</span></span>
        </a>
        <button class="sidebar-close" id="sidebarClose" type="button" aria-label="Close menu">
          <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M18 6 6 18M6 6l12 12"/></svg>
        </button>
      </div>

      <div class="workspace-chip">
        <div class="ws-name">{{ Auth::user()->workspaceName() }}</div>
        <div class="ws-role">● personal-workspace</div>
      </div>

      <nav class="sidebar-nav" aria-label="Console">
        <div class="nav-group-label">Overview</div>
        <a class="nav-link {{ request()->routeIs('dashboard') ? 'active' : '' }}" href="{{ route('dashboard') }}">
          <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="7" height="9" rx="1"/><rect x="14" y="3" width="7" height="5" rx="1"/><rect x="14" y="12" width="7" height="9" rx="1"/><rect x="3" y="16" width="7" height="5" rx="1"/></svg>
          Dashboard
        </a>

        <div class="nav-group-label">Core workflow</div>
        <a class="nav-link {{ request()->routeIs('groups.*') ? 'active' : '' }}" href="{{ route('groups.index') }}">
          <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m12.83 2.18a2 2 0 0 0-1.66 0L2.6 6.08a1 1 0 0 0 0 1.83l8.58 3.91a2 2 0 0 0 1.66 0l8.58-3.9a1 1 0 0 0 0-1.83Z"/><path d="m22 17.65-9.17 4.16a2 2 0 0 1-1.66 0L2 17.65"/><path d="m22 12.65-9.17 4.16a2 2 0 0 1-1.66 0L2 12.65"/></svg>
          Asset Groups
        </a>
        <a class="nav-link {{ request()->routeIs('scans.new') ? 'active' : '' }}" href="{{ route('scans.new') }}">
          <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 7V5a2 2 0 0 1 2-2h2"/><path d="M17 3h2a2 2 0 0 1 2 2v2"/><path d="M21 17v2a2 2 0 0 1-2 2h-2"/><path d="M7 21H5a2 2 0 0 1-2-2v-2"/><circle cx="12" cy="12" r="3"/></svg>
          New Scan
        </a>

        <div class="nav-group-label">Findings</div>
        <a class="nav-link {{ request()->routeIs('vulnerabilities.index') ? 'active' : '' }}" href="{{ route('vulnerabilities.index') }}">
          <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0Z"/><path d="M12 9v4"/><path d="M12 17h.01"/></svg>
          Vulnerabilities
        </a>
        <a class="nav-link {{ request()->routeIs('incidents.index') ? 'active' : '' }}" href="{{ route('incidents.index') }}">
          <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2 4 5v6c0 5 3.4 8.6 8 11 4.6-2.4 8-6 8-11V5l-8-3Z"/><path d="M12 8v4"/><path d="M12 16h.01"/></svg>
          Incidents
        </a>
        <a class="nav-link {{ request()->routeIs('issues.index') ? 'active' : '' }}" href="{{ route('issues.index') }}">
          <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 20h9"/><path d="M16.5 3.5a2.12 2.12 0 0 1 3 3L7 19l-4 1 1-4Z"/></svg>
          All Findings
        </a>
        <a class="nav-link {{ request()->routeIs('resolved.index') ? 'active' : '' }}" href="{{ route('resolved.index') }}">
          <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><path d="m9 11 3 3L22 4"/></svg>
          Resolved
        </a>
        <a class="nav-link {{ request()->routeIs('activity.index') ? 'active' : '' }}" href="{{ route('activity.index') }}">
          <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 12h-4l-3 9L9 3l-3 9H2"/></svg>
          Activity
        </a>

        <div class="nav-group-label">Intelligence</div>
        <a class="nav-link {{ request()->routeIs('ai-chat') ? 'active' : '' }}" href="{{ route('ai-chat') }}">
          <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
          Garda Copilot
        </a>
        <a class="nav-link {{ request()->routeIs('reports.index') ? 'active' : '' }}" href="{{ route('reports.index') }}">
          <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14.5 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7.5Z"/><path d="M14 2v6h6"/></svg>
          Reports
        </a>
        <a class="nav-link {{ request()->routeIs('ai-settings') ? 'active' : '' }}" href="{{ route('ai-settings') }}">
          <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="4" x2="20" y1="21" y2="21"/><line x1="4" x2="20" y1="14" y2="14"/><line x1="4" x2="20" y1="7" y2="7"/><line x1="4" x2="20" y1="3" y2="3"/></svg>
          Triage settings
        </a>

        <div class="nav-group-label">Account</div>
        <a class="nav-link {{ request()->routeIs('profile.*') ? 'active' : '' }}" href="{{ route('profile.edit') }}">
          <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="8" r="5"/><path d="M20 21a8 8 0 0 0-16 0"/></svg>
          Profile
        </a>
        <form method="POST" action="{{ route('logout') }}">
          @csrf
          <button type="submit" class="nav-link nav-link-button">
            <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><path d="m16 17 5-5-5-5"/><path d="M21 12H9"/></svg>
            Sign out
          </button>
        </form>
      </nav>
    </aside>

    <div class="sidebar-scrim" id="sidebarScrim"></div>

    <div class="console-main">
      <header class="console-topbar">
        <button class="sidebar-open" id="sidebarOpen" type="button" aria-label="Open menu" aria-controls="consoleSidebar" aria-expanded="false">
          <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M4 7h16M4 12h16M4 17h16"/></svg>
        </button>
        <div class="topbar-title"><span class="topbar-context">workspace /</span> @yield('title', 'overview')</div>
        <a class="btn btn-ghost btn-sm topbar-scan" href="{{ route('scans.new') }}">New Scan</a>
        <span class="topbar-user">{{ Auth::user()->displayName() }}</span>
      </header>

      <main class="console-content" id="main-content">
        @if (session('status'))
          <div class="flash" role="status">{{ session('status') }}</div>
        @endif
        @if ($errors->any())
          <div class="flash flash-error" role="alert">
            <ul>
              @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
              @endforeach
            </ul>
          </div>
        @endif

        @yield('content')
      </main>
    </div>
  </div>

  <script src="{{ asset('js/console.js') }}"></script>
  @stack('scripts')
</body>
</html>
