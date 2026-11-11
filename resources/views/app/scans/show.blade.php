@extends('layouts.app')

@section('title', 'Scan Console · '.$scan->group->root_domain.' · GardaCyber')

@section('content')
  <div class="page-head row">
    <div>
      <h1 class="mono" style="word-break: break-all;">{{ $scan->group->root_domain }}</h1>
      <p style="margin-top: 6px;">
        <span>Scope: {{ $scan->modeBadge() }}</span>
        <span style="margin: 0 6px;">·</span>
        <span>Started: {{ $scan->started_at ? $scan->started_at->diffForHumans() : 'queued' }}</span>
      </p>
    </div>
    <div class="page-actions">
      @if ($scan->isLive())
        <form method="POST" action="{{ route('scans.stop', $scan) }}">
          @csrf
          <button class="btn btn-ghost btn-sm danger" type="submit">Stop Scan</button>
        </form>
      @endif
      <a class="btn btn-ghost btn-sm" href="{{ route('groups.show', $scan->group) }}">View Asset Group</a>
    </div>
  </div>

  <div class="scan-console" data-scan-id="{{ $scan->id }}" data-progress-url="{{ route('scans.progress', $scan) }}">
    <div class="scan-header">
      <div class="scan-status-group">
        <span class="pill {{ $initial['finished'] ? ($initial['status'] === 'completed' ? 'ok' : 'warn') : 'run' }}" id="scanStatus">
          @if (!$initial['finished'])
            <span class="spin-dot"></span>
          @endif
          <span id="scanStatusText">{{ ucfirst($initial['status']) }}</span>
        </span>
        <span class="mono scan-message" id="scanMessage">{{ $initial['message'] }}</span>
      </div>
      <span class="scan-elapsed mono" id="scanElapsed">{{ $initial['elapsed_seconds'] }}s elapsed</span>
    </div>

    <div class="scan-progress">
      <div class="pg-track-lg">
        <div class="pg-fill" id="scanBar" style="width: {{ $initial['percent'] }}%;"></div>
      </div>
      <div class="scan-progress-meta">
        <span class="scan-percent mono" id="scanPercent">{{ $initial['percent'] }}% Complete</span>
        <span class="scan-eta mono" id="scanEta">
          @if ($initial['finished'])
            Selesai
          @elseif ($initial['percent'] > 0)
            Estimasi sisa: ~{{ ceil(($initial['elapsed_seconds'] * (100 - $initial['percent'])) / max($initial['percent'], 1)) }}s
          @else
            Menyelesaikan…
          @endif
        </span>
      </div>
    </div>

    <div class="pg-stages" id="scanStages">
      @foreach ($initial['stages'] as $stage)
        <div class="pg-stage {{ $stage['state'] }}">
          <span class="pg-dot"></span>
          <span class="pg-stage-name">{{ $stage['name'] }}</span>
          <span class="pg-stage-state mono">{{ $stage['state'] }}</span>
        </div>
      @endforeach
    </div>

    <div class="scan-summary">
      <div class="scan-kpi">
        <div class="k-value mono" id="scanAssets">{{ $initial['assets'] }}</div>
        <div class="k-label">Assets Profiled</div>
      </div>
      <div class="scan-kpi">
        <div class="k-value mono vuln-count" id="scanVulns">{{ $initial['vulnerabilities'] }}</div>
        <div class="k-label">Vulnerabilities</div>
      </div>
      <div class="scan-kpi">
        <div class="k-value mono incident-count" id="scanIncidents">{{ $initial['incidents'] }}</div>
        <div class="k-label">Incidents</div>
      </div>
    </div>

    <div class="scan-done {{ $initial['finished'] ? '' : 'hidden' }}" id="scanDone">
      <div class="done-callout" id="doneCallout">
        {{ $initial['message'] ?: 'Scan completed. New evidence has been attributed to the asset record.' }}
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
  const eta = document.getElementById('scanEta');
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

  const STATUS_CLASS = {
    completed: 'ok',
    running: 'run',
    queued: 'run',
    stopped: 'warn',
    failed: 'warn'
  };

  let poll = null;

  function escapeHtml(str) {
    const div = document.createElement('div');
    div.textContent = str || '';
    return div.innerHTML;
  }

  function renderStage(name, state) {
    return '<div class="pg-stage ' + escapeHtml(state) + '">' +
      '<span class="pg-dot"></span>' +
      '<span class="pg-stage-name">' + escapeHtml(name) + '</span>' +
      '<span class="pg-stage-state mono">' + escapeHtml(state) + '</span>' +
      '</div>';
  }

  function formatEta(data) {
    if (data.finished) return '';
    const p = Number(data.percent) || 0;
    const e = Number(data.elapsed_seconds) || 0;
    if (p <= 0) return 'Menyelesaikan…';
    const remaining = Math.max(1, Math.round(e * (100 - p) / p));
    return 'Estimasi sisa: ~' + remaining + 's';
  }

  function setStatus(value) {
    statusText.textContent = value.charAt(0).toUpperCase() + value.slice(1);
    status.className = 'pill ' + (STATUS_CLASS[value] || 'run');
  }

  function render(data) {
    const p = Math.min(100, Math.max(0, Number(data.percent) || 0));
    bar.style.width = p + '%';
    pct.textContent = p + '% Complete';
    eta.textContent = formatEta(data);
    elapsed.textContent = data.elapsed_seconds + 's elapsed';

    if (data.message) {
      messageEl.textContent = data.message;
    }
    assetsEl.textContent = data.assets;
    vulnsEl.textContent = data.vulnerabilities;
    incidentsEl.textContent = data.incidents;

    if (data.stages && data.stages.length) {
      stages.innerHTML = data.stages.map(function (s) { return renderStage(s.name, s.state); }).join('');
    }

    if (data.finished) {
      if (poll) { clearInterval(poll); poll = null; }
      setStatus(data.status);
      const dot = status.querySelector('.spin-dot');
      if (dot) dot.remove();

      done.classList.remove('hidden');
      done.classList.toggle('scan-done-warn', data.status !== 'completed');
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
