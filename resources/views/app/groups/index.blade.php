@extends('layouts.app')

@section('title', 'Asset Groups')

@section('content')
  <div class="page-head">
    <h1>Asset Groups</h1>
    <p>One group per root domain. Assets and findings stay connected here.</p>
  </div>

  @if (session('status'))
    <div class="flash">{{ session('status') }}</div>
  @endif

  @if ($groups->isEmpty())
    <div class="empty-state">
      <div class="empty-icon">
        <svg viewBox="0 0 24 24" width="28" height="28" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="m12.83 2.18a2 2 0 0 0-1.66 0L2.6 6.08a1 1 0 0 0 0 1.83l8.58 3.91a2 2 0 0 0 1.66 0l8.58-3.9a1 1 0 0 0 0-1.83Z"/><path d="m22 17.65-9.17 4.16a2 2 0 0 1-1.66 0L2 17.65"/><path d="m22 12.65-9.17 4.16a2 2 0 0 1-1.66 0L2 12.65"/></svg>
      </div>
      <h2>No asset groups yet</h2>
      <p>Scan a domain and its group will appear here automatically.</p>
      <a class="btn btn-primary" href="{{ route('scans.new') }}">Start a scan</a>
    </div>
  @else
    <div class="group-cards">
      @foreach ($ratings as $row)
        <a class="group-card" href="{{ route('groups.show', $row['group']) }}">
          <div class="group-card-top">
            <span class="mono group-domain">{{ $row['group']->root_domain }}</span>
            @if ($row['group']->single_host)
              <span class="pill">single host</span>
            @endif
          </div>
          <div class="group-meta">{{ $row['group']->assets_count }} assets · last scan {{ $row['group']->last_scanned_at?->diffForHumans() ?? 'never' }}</div>
          <div class="tag-row">
            @php $worstV = $row['vuln']['grade']; @endphp
            <span class="badge sev-{{ strtolower($worstV) }}"><span class="dot"></span>Vuln {{ $worstV }}</span>
            <span class="badge sev-{{ strtolower($row['incident']['grade']) }}"><span class="dot"></span>Incident {{ $row['incident']['grade'] }}</span>
          </div>
        </a>
      @endforeach
    </div>
  @endif
@endsection
