@extends('layouts.app')

@section('title', 'Scan Console · '.$scan->group->root_domain.' · GardaCyber')

@section('content')
  <div class="page-head row">
    <div>
      <h1 class="mono" style="word-break: break-all;">{{ $scan->group->root_domain }}</h1>
      <p style="margin-top: 6px;">
        <span class="mode-badge" style="background: var(--accent-soft, rgba(0,210,255,0.12)); color: var(--accent, #00d2ff); border-color: rgba(0,210,255,0.3);">
          {{ strtoupper($initial['mode'] ?? 'DEMO') }} MODE
        </span>
        <span style="margin: 0 6px;">·</span>
        <span>Scope: {{ $scan->modeBadge() }}</span>
        <span style="margin: 0 6px;">·</span>
        <span>Started: {{ $scan->started_at ? $scan->started_at->diffForHumans() : 'queued' }}</span>
      </p>
    </div>
    <div class="page-actions">
      @if ($scan->isLive())
        <form method="POST" action="{{ route('scans.stop', $scan) }}">
          @csrf
          <button class="btn btn-ghost btn-sm danger" type="submit">Stop Assessment</button>
        </form>
      @endif
      <a class="btn btn-ghost btn-sm" href="{{ route('groups.show', $scan->group) }}">View Asset Group</a>
    </div>
  </div>

  <div class="scan-console" data-scan-id="{{ $scan->id }}" data-progress-url="{{ route('scans.progress', $scan) }}" style="background: var(--bg-surface-1, #0a1120); border: 1px solid var(--border-subtle, rgba(255,255,255,0.08)); border-radius: 14px; padding: 24px;">
    <div class="scan-header" style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 16px;">
      <div style="display: flex; align-items: center; gap: 10px;">
        <span class="pill {{ $initial['finished'] ? ($initial['status'] === 'completed' ? 'ok' : 'warn') : 'run' }}" id="scanStatus">
          @if (!$initial['finished'])
            <span class="spin-dot"></span>
          @endif
          <span id="scanStatusText">{{ ucfirst($initial['status']) }}</span>
        </span>
        <span class="mono" id="scanMessage" style="font-size: 13px; color: var(--fg-muted, #94a3b8);">
          {{ $initial['message'] }}
        </span>
      </div>
      <span class="scan-elapsed mono" id="scanElapsed" style="font-size: 12px; color: var(--fg-subtle, #64748b);">
        {{ $initial['elapsed_seconds'] }}s elapsed
      </span>
    </div>

    <div class="pg-track-lg" style="height: 8px; background: var(--bg-surface-3, #1e293b); border-radius: 999px; overflow: hidden;">
      <div class="pg-fill" id="scanBar" style="height: 100%; width: {{ $initial['percent'] }}%; background: linear-gradient(90deg, #0284c7, #00d2ff); transition: width 0.4s ease;"></div>
    </div>
    <div class="scan-percent mono" id="scanPercent" style="font-size: 13px; margin-top: 8px; color: var(--fg-muted, #94a3b8);">
      {{ $initial['percent'] }}% Complete
    </div>

    <div class="pg-stages" id="scanStages" style="margin-top: 24px; display: grid; gap: 10px;">
      @foreach ($initial['stages'] as $stage)
        <div class="pg-stage {{ $stage['state'] }}" style="display: flex; align-items: center; justify-content: space-between; padding: 10px 14px; background: var(--bg-surface-2, #0d1527); border: 1px solid var(--border-subtle, rgba(255,255,255,0.06)); border-radius: 8px;">
          <div style="display: flex; align-items: center; gap: 10px;">
            <span class="pg-dot" style="width: 8px; height: 8px; border-radius: 50%; display: inline-block; background: {{ $stage['state'] === 'complete' ? '#10b981' : ($stage['state'] === 'running' ? '#00d2ff' : '#475569') }};"></span>
            <span class="pg-stage-name" style="font-weight: 500; font-size: 13.5px;">{{ $stage['name'] }}</span>
          </div>
          <span class="pg-stage-state mono" style="font-size: 11px; text-transform: uppercase; color: {{ $stage['state'] === 'complete' ? '#10b981' : ($stage['state'] === 'running' ? '#00d2ff' : '#64748b') }};">
            {{ $stage['state'] }}
          </span>
        </div>
      @endforeach
    </div>

    <div class="scan-summary" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(130px, 1fr)); gap: 12px; margin-top: 24px;">
      <div class="scan-kpi" style="padding: 12px; background: var(--bg-surface-2, #0d1527); border-radius: 8px; text-align: center;">
        <div class="k-value mono" id="scanAssets" style="font-size: 20px; font-weight: 700;">{{ $initial['assets'] }}</div>
        <div class="k-label" style="font-size: 11px; text-transform: uppercase; color: var(--fg-subtle, #64748b);">Assets Profiled</div>
      </div>
      <div class="scan-kpi" style="padding: 12px; background: var(--bg-surface-2, #0d1527); border-radius: 8px; text-align: center;">
        <div class="k-value mono" id="scanVulns" style="font-size: 20px; font-weight: 700; color: #f59e0b;">{{ $initial['vulnerabilities'] }}</div>
        <div class="k-label" style="font-size: 11px; text-transform: uppercase; color: var(--fg-subtle, #64748b);">Vulnerabilities</div>
      </div>
      <div class="scan-kpi" style="padding: 12px; background: var(--bg-surface-2, #0d1527); border-radius: 8px; text-align: center;">
        <div class="k-value mono" id="scanIncidents" style="font-size: 20px; font-weight: 700; color: #ef4444;">{{ $initial['incidents'] }}</div>
        <div class="k-label" style="font-size: 11px; text-transform: uppercase; color: var(--fg-subtle, #64748b);">Incidents</div>
      </div>
    </div>

    <div class="scan-done {{ $initial['finished'] ? '' : 'hidden' }}" id="scanDone" style="margin-top: 24px; padding: 18px; border-radius: 10px; background: rgba(16, 185, 129, 0.08); border: 1px solid rgba(16, 185, 129, 0.25);">
      <div class="done-callout" id="doneCallout" style="font-size: 14px; font-weight: 600; color: #10b981; margin-bottom: 12px;">
        {{ $initial['message'] ?: 'Assessment finalized. New evidence has been attributed to the asset record.' }}
      </div>
      <div class="page-actions">
        <a class="btn btn-primary btn-sm" id="doneGroupLink" href="{{ $initial['group_url'] }}">View Updated Group Findings</a>
        <a class="btn btn-ghost btn-sm" href="{{ route('dashboard') }}">Console Dashboard</a>
      </div>
    </div>
  </div>
@endsection

@push('scripts')
<script>
(function () {
  'use strict';
  const box = document.querySelector('.scan-console');
  if (!box) return;

  const url = box.dataset.progressUrl;
  const bar = document.getElementById('scanBar');
  const pct = document.getElementById('scanPercent');
  const stages = document.getElementById('scanStages');
  const status = document.getElementById('scanStatus');
  const statusText = document.getElementById('scanStatusText');
  const messageEl = document.getElementById('scanMessage');
  const elapsed = document.getElementById('scanElapsed');
  const done = document.getElementById('scanDone');
  const callout = document.getElementById('doneCallout');
  const groupLink = document.getElementById('doneGroupLink');
  const assetsEl = document.getElementById('scanAssets');
  const vulnsEl = document.getElementById('scanVulns');
  const incidentsEl = document.getElementById('scanIncidents');

  let poll = null;

  function renderStage(name, state) {
    const color = state === 'complete' ? '#10b981' : (state === 'running' ? '#00d2ff' : '#475569');
    return '<div class="pg-stage ' + state + '" style="display: flex; align-items: center; justify-content: space-between; padding: 10px 14px; background: var(--bg-surface-2, #0d1527); border: 1px solid var(--border-subtle, rgba(255,255,255,0.06)); border-radius: 8px;">' +
      '<div style="display: flex; align-items: center; gap: 10px;">' +
      '<span class="pg-dot" style="width: 8px; height: 8px; border-radius: 50%; display: inline-block; background: ' + color + ';"></span>' +
      '<span class="pg-stage-name" style="font-weight: 500; font-size: 13.5px;">' + escapeHtml(name) + '</span>' +
      '</div>' +
      '<span class="pg-stage-state mono" style="font-size: 11px; text-transform: uppercase; color: ' + color + ';">' + escapeHtml(state) + '</span>' +
      '</div>';
  }

  function escapeHtml(str) {
    const div = document.createElement('div');
    div.textContent = str || '';
    return div.innerHTML;
  }

  function render(data) {
    bar.style.width = data.percent + '%';
    pct.textContent = data.percent + '% Complete';
    elapsed.textContent = data.elapsed_seconds + 's elapsed';
    if (data.message) {
      messageEl.textContent = data.message;
    }
    assetsEl.textContent = data.assets;
    vulnsEl.textContent = data.vulnerabilities;
    incidentsEl.textContent = data.incidents;

    if (data.stages && data.stages.length) {
      stages.innerHTML = data.stages.map(s => renderStage(s.name, s.state)).join('');
    }

    if (data.finished) {
      if (poll) clearInterval(poll);
      statusText.textContent = data.status.charAt(0).toUpperCase() + data.status.slice(1);
      status.className = 'pill ' + (data.status === 'completed' ? 'ok' : 'warn');
      const dot = status.querySelector('.spin-dot');
      if (dot) dot.remove();

      done.classList.remove('hidden');
      if (data.message) {
        callout.textContent = data.message;
      }
      if (data.group_url) {
        groupLink.href = data.group_url;
      }
    }
  }

  async function tick() {
    try {
      const res = await fetch(url, { headers: { 'Accept': 'application/json' }, credentials: 'same-origin' });
      if (!res.ok) return;
      const data = await res.json();
      render(data);
    } catch (e) {
      // transient network retry
    }
  }

  @if (!$initial['finished'])
    poll = setInterval(tick, 2000);
    tick();
  @endif
})();
</script>
@endpush
