@extends('layouts.app')

@section('title', 'Attack Storyboard · ' . $group->root_domain)

@section('content')
  <div class="page-head row">
    <div>
      <div style="display: flex; align-items: center; gap: 8px; margin-bottom: 8px;">
        <span class="badge sb-badge"><span class="signal-dot pulse"></span>Attack Storyboard</span>
        <span class="pill ok">Visual reconstruction</span>
      </div>
      <h1 class="mono">{{ $group->root_domain }}</h1>
      <p>{{ $assetsCount }} assets · {{ $storyboard['totalFindings'] }} open findings</p>
    </div>
    <div class="page-actions">
      <a class="btn btn-ghost btn-sm" href="{{ route('groups.show', $group) }}">Back to group</a>
      <a class="btn btn-primary btn-sm" href="{{ route('scans.new') }}">New Scan</a>
    </div>
  </div>

  {{-- NARRATIVE — the story of the attack --}}
  <div class="panel sb-narrative" style="margin-bottom: 24px;">
    <div class="panel-head">
      <span class="panel-title">How an attacker would move against this surface</span>
    </div>
    <ol class="sb-narrative-list">
      @foreach ($storyboard['narrative'] as $line)
        <li>{{ $line }}</li>
      @endforeach
    </ol>
  </div>

  {{-- TIMELINE — five phases --}}
  <div class="sb-timeline">
    @foreach ($storyboard['phases'] as $phase)
      <div class="sb-phase {{ $phase['count'] > 0 ? 'has-findings' : '' }}">
        <div class="sb-phase-head">
          <div class="sb-phase-icon" data-phase="{{ $phase['icon'] }}">
            @if ($phase['icon'] === 'radar')
              <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="9"/><circle cx="12" cy="12" r="5"/><circle cx="12" cy="12" r="1"/><line x1="12" y1="12" x2="19" y2="5"/></svg>
            @elseif ($phase['icon'] === 'probe')
              <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2"><path d="m3 3 7.5 18 3.5-8 8-3.5Z"/></svg>
            @elseif ($phase['icon'] === 'exploit')
              <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>
            @elseif ($phase['icon'] === 'compromise')
              <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 2 4 5v6c0 5 3.4 8.6 8 11 4.6-2.4 8-6 8-11V5l-8-3Z"/><path d="M12 8v4"/><path d="M12 16h.01"/></svg>
            @else
              <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 12h-4l-3 9L9 3l-3 9H2"/></svg>
            @endif
          </div>
          <div class="sb-phase-meta">
            <div class="sb-phase-label">{{ $phase['label'] }}</div>
            <div class="sb-phase-count">{{ $phase['count'] }} finding{{ $phase['count'] !== 1 ? 's' : '' }}</div>
          </div>
        </div>

        <div class="sb-phase-hint">{{ $phase['hint'] }}</div>

        <div class="sb-phase-findings">
          @forelse ($phase['findings'] as $finding)
            <a class="sb-finding sev-{{ $finding->severity }}" href="{{ route('findings.show', $finding) }}">
              <span class="badge sev-{{ $finding->severity }}"><span class="dot"></span>{{ ucfirst($finding->severity) }}</span>
              <span class="sb-finding-title">{{ $finding->title }}</span>
              <span class="sb-finding-host mono">{{ $finding->asset?->hostname ?: $group->root_domain }}</span>
            </a>
          @empty
            <div class="sb-phase-empty">No open findings</div>
          @endforelse
        </div>
      </div>
    @endforeach
  </div>

  <div class="panel" style="margin-top: 24px;">
    <div class="panel-head">
      <span class="panel-title">What this means</span>
    </div>
    <p style="font-size: 13px; color: var(--fg-muted); line-height: 1.6; margin: 0;">
      The storyboard reconstructs the most plausible order of operations against this perimeter using open findings —
      from how an attacker learns the surface (reconnaissance), to what they can touch (probing), what they could
      exploit, whether compromise signals exist, and the overall impact on your rating. Findings are linkable for full
      evidence and remediation playbooks.
    </p>
  </div>
@endsection
