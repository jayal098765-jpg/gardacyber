<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <meta name="csrf-token" content="{{ csrf_token() }}" />
  <title>@yield('title', 'GardaCyber — Perimeter Attack Surface Terminal')</title>
  <meta name="description" content="GardaCyber is a terminal-grade attack surface workspace: asset discovery, exposure checks, and evidence-led incident triage in one command-line-inspired workspace." />
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link href="https://fonts.googleapis.com/css2?family=JetBrains+Mono:wght@400;500;600;700&family=Manrope:wght@400;500;600;700;800&family=Space+Grotesk:wght@400;500;600;700&display=swap" rel="stylesheet" />
  <link rel="stylesheet" href="{{ asset('css/app.css') }}" />
  <link rel="stylesheet" href="{{ asset('css/console.css') }}" />
</head>
<body class="marketing-body">
  <a href="#main-content" class="skip-link">Skip to content</a>
  <header class="site-header" id="siteHeader">
    <div class="nav-inner">
      <a class="brand" href="{{ route('landing') }}"><span class="garda-mark" aria-hidden="true">G</span><span class="brand-name">GardaCyber<span class="brand-period">.</span></span></a>
      <nav class="nav-links" aria-label="Primary">
        <a href="{{ route('landing') }}#coverage" data-i18n="nav_perimeter">Perimeter</a>
        <a href="{{ route('landing') }}#features" data-i18n="nav_workspace">Workspace</a>
        <a href="{{ route('landing') }}#incident-check" data-i18n="nav_incidents">Incidents</a>
        <a href="{{ route('landing') }}#how" data-i18n="nav_workflow">Workflow</a>
        <a href="{{ route('landing') }}#pricing" data-i18n="nav_plans">Plans</a>
      </nav>
      <div class="nav-actions">
        <div class="lang-toggle" role="group" aria-label="Language">
          <button type="button" class="lang-btn" data-lang-btn="en" aria-pressed="false">EN</button>
          <button type="button" class="lang-btn" data-lang-btn="id" aria-pressed="false">ID</button>
        </div>
        @auth
          <a class="btn btn-primary btn-sm" href="{{ route('dashboard') }}" data-i18n="nav_openterminal">Open terminal</a>
        @else
          <a class="btn btn-ghost btn-sm" href="{{ route('login') }}" data-i18n="nav_signin">Sign in</a>
          <a class="btn btn-primary btn-sm" href="{{ route('signup') }}" data-i18n="nav_getstarted">Get started</a>
        @endauth
        <button class="nav-burger" id="navBurger" type="button" aria-label="Toggle navigation" aria-controls="navMobile" aria-expanded="false">Menu</button>
      </div>
    </div>
    <nav class="nav-mobile" id="navMobile" aria-label="Mobile">
      <a href="{{ route('landing') }}#coverage" data-i18n="nav_perimeter">Perimeter</a>
      <a href="{{ route('landing') }}#features" data-i18n="nav_workspace">Workspace</a>
      <a href="{{ route('landing') }}#incident-check" data-i18n="nav_incidents">Incidents</a>
      <a href="{{ route('landing') }}#how" data-i18n="nav_workflow">Workflow</a>
      <a href="{{ route('landing') }}#pricing" data-i18n="nav_plans">Plans</a>
      <a href="{{ route('login') }}" data-i18n="nav_signin">Sign in</a>
      <a href="{{ route('signup') }}" class="btn btn-primary" data-i18n="nav_getstarted">Get started</a>
    </nav>
  </header>
  <main id="main-content">
    @if ($errors->any())
      <div class="container"><div class="flash flash-error auth-errors" role="alert"><strong>Check your details</strong><ul>@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div></div>
    @endif
    @if (session('status'))
      <div class="container"><div class="flash auth-errors" role="status">{{ session('status') }}</div></div>
    @endif
    @yield('content')
  </main>
  <footer class="site-footer">
    <div class="container footer-grid">
      <div class="footer-brand"><a class="brand" href="{{ route('landing') }}"><span class="garda-mark" aria-hidden="true">G</span><span class="brand-name">GardaCyber.</span></a><p data-i18n="foot_brand">Attack surface analysis engineered for defenders who think like attackers.</p></div>
      <div class="footer-col"><h4 data-i18n="foot_explore">Explore</h4><a href="{{ route('landing') }}#coverage" data-i18n="foot_perimeter">Perimeter coverage</a><a href="{{ route('landing') }}#incident-check" data-i18n="foot_incidents">Incident signals</a><a href="{{ route('landing') }}#how" data-i18n="foot_workflow">Workflow</a></div>
      <div class="footer-col"><h4 data-i18n="foot_ws">Workspace</h4><a href="{{ route('login') }}" data-i18n="foot_signin">Sign in</a><a href="{{ route('signup') }}" data-i18n="foot_create">Create account</a><p class="note" data-i18n="foot_note">Hackathon build — not a substitute for a professional security audit.</p></div>
    </div>
    <div class="container footer-bottom"><span>© {{ date('Y') }} GardaCyber</span><span data-i18n="foot_right">scan only what you own or have permission to test</span></div>
  </footer>
  <script src="{{ asset('js/app.js') }}" defer></script>
  <script src="{{ asset('js/i18n.js') }}" defer></script>
  <script src="{{ asset('js/landing.js') }}" defer></script>
  <script src="{{ asset('js/console.js') }}" defer></script>
  @stack('scripts')
</body>
</html>
