@extends('layouts.app')

@section('title', 'Dashboard')

@php
  $sevRank = ['critical' => 4, 'high' => 3, 'medium' => 2, 'low' => 1];
  $worstVulnSev = $worstVuln?->severity;
  $worstIncidentSev = $worstIncident?->severity;
  $worstOverall = ($sevRank[$worstVulnSev] ?? 0) >= ($sevRank[$worstIncidentSev] ?? 0)
      ? $worstVulnSev
      : $worstIncidentSev;
  $bannerClass = match ($worstOverall) { 'critical' => 'banner-critical', 'high' => 'banner-high', default => 'banner-clear' };
  $bannerText = match ($worstOverall) {
      'critical' => ($openVulns->where('severity','critical')->count() + $openIncidents->where('severity','critical')->count()).' critical exposures need attention',
      'high' => ($openVulns->where('severity','high')->count() + $openIncidents->where('severity','high')->count()).' high-severity exposures need attention',
      default => 'No critical or high-severity findings open',
  };
  $vulnBySev = $openVulns->groupBy('severity')->map->count();
  $incidentBySev = $openIncidents->groupBy('severity')->map->count();
@endphp

@section('content')
  <div class="page-head row">
    <div>
      <h1>Attack Surface Overview</h1>
      <p>Your perimeter at a glance — assets, exposures, and live compromise signals.</p>
    </div>
    <div class="page-actions">
      @if ($groups->isNotEmpty())
        <a class="btn btn-ghost btn-sm" href="{{ route('groups.storyboard', $groups->first()) }}">
          <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" style="margin-right: 6px;"><path d="M22 12h-4l-3 9L9 3l-3 9H2"/></svg>
          Storyboard
        </a>
      @endif
      <a class="btn btn-ghost btn-sm" href="{{ route('reports.index') }}">Reports</a>
      <a class="btn btn-primary btn-sm" href="{{ route('scans.new') }}">New Scan</a>
    </div>
  </div>

  <a class="banner {{ $bannerClass }}" href="{{ route('issues.index') }}" aria-label="{{ $bannerText }} — review now">
    <span>{{ $bannerText }}</span>
    <span class="banner-action">review now <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14"/><path d="m12 5 7 7-7 7"/></svg></span>
  </a>

  <div class="kpi-row">
    <a class="kpi kpi-link" href="{{ route('groups.index') }}">
      <div class="k-label">Total Assets</div>
      <div class="k-value" data-count="{{ $totalAssets }}">{{ number_format($totalAssets) }}</div>
      <div class="k-arrow" aria-hidden="true"><svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14"/><path d="m12 5 7 7-7 7"/></svg></div>
    </a>
    <a class="kpi kpi-link" href="{{ route('groups.index') }}">
      <div class="k-label">Asset Groups</div>
      <div class="k-value" data-count="{{ $groups->count() }}">{{ $groups->count() }}</div>
      <div class="k-arrow" aria-hidden="true"><svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14"/><path d="m12 5 7 7-7 7"/></svg></div>
    </a>
    <a class="kpi kpi-link {{ $openVulns->isNotEmpty() ? 'sev-'.($worstVulnSev ?? 'low') : '' }}" href="{{ route('vulnerabilities.index') }}">
      <div class="k-label">Open Vulnerabilities</div>
      <div class="k-value" data-count="{{ $openVulns->count() }}">{{ $openVulns->count() }}</div>
      <div class="k-sub">{{ $worstVulnSev ? $openVulns->where('severity',$worstVulnSev)->count().' '.$worstVulnSev : 'nothing open' }}</div>
      <div class="k-arrow" aria-hidden="true"><svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14"/><path d="m12 5 7 7-7 7"/></svg></div>
    </a>
    <a class="kpi kpi-link {{ $openIncidents->isNotEmpty() ? 'sev-'.($worstIncidentSev ?? 'low') : '' }}" href="{{ route('incidents.index') }}">
      <div class="k-label">Open Incidents</div>
      <div class="k-value" data-count="{{ $openIncidents->count() }}">{{ $openIncidents->count() }}</div>
      <div class="k-sub">{{ $worstIncidentSev ? $openIncidents->where('severity',$worstIncidentSev)->count().' '.$worstIncidentSev : 'nothing open' }}</div>
      <div class="k-arrow" aria-hidden="true"><svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14"/><path d="m12 5 7 7-7 7"/></svg></div>
    </a>
    <a class="kpi kpi-link" href="{{ route('activity.index') }}">
      <div class="k-label">Scans This Week</div>
      <div class="k-value" data-count="{{ $scansThisWeek }}">{{ $scansThisWeek }}</div>
      <div class="k-arrow" aria-hidden="true"><svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14"/><path d="m12 5 7 7-7 7"/></svg></div>
    </a>
  </div>

  <div class="dash-grid">
    <div class="panel">
      <div class="panel-head">
        <span class="panel-title">Open Vulnerabilities</span>
        <span class="panel-total">{{ $openVulns->count() }} total</span>
      </div>
      <div class="sev-bars">
        @foreach (['critical','high','medium','low'] as $sev)
          <div class="sev-bar-row">
            <span class="sev-name">{{ $sev }}</span>
            <div class="sev-track">
              <span class="sev-fill fill-{{ $sev }}" style="--w:{{ $openVulns->isEmpty() ? 0 : ($vulnBySev[$sev] ?? 0) / $openVulns->count() * 100 }}%"></span>
            </div>
            <span class="sev-n">{{ $vulnBySev[$sev] ?? 0 }}</span>
          </div>
        @endforeach
      </div>

      <div class="panel-sep"></div>

      <div class="panel-head">
        <span class="panel-title">Open Incidents</span>
        <span class="panel-total">{{ $openIncidents->count() }} total</span>
      </div>
      <div class="sev-bars">
        @foreach (['critical','high','medium','low'] as $sev)
          <div class="sev-bar-row">
            <span class="sev-name">{{ $sev }}</span>
            <div class="sev-track">
              <span class="sev-fill fill-{{ $sev }}" style="--w:{{ $openIncidents->isEmpty() ? 0 : ($incidentBySev[$sev] ?? 0) / $openIncidents->count() * 100 }}%"></span>
            </div>
            <span class="sev-n">{{ $incidentBySev[$sev] ?? 0 }}</span>
          </div>
        @endforeach
      </div>
    </div>

    <div class="panel">
      <div class="panel-head">
        <span class="panel-title">Security Rating · org-wide</span>
      </div>
      <div class="grade-row">
        <div class="grade-card">
          <div class="grade-letter grade-{{ strtolower($rating['vuln']['grade']) }}">{{ $rating['vuln']['grade'] }}</div>
          <div class="grade-label">Vulnerability</div>
          <div class="grade-score">{{ $rating['vuln']['score'] }}/100</div>
        </div>
        <div class="grade-card">
          <div class="grade-letter grade-{{ strtolower($rating['incident']['grade']) }}">{{ $rating['incident']['grade'] }}</div>
          <div class="grade-label">Incident</div>
          <div class="grade-score">{{ $rating['incident']['score'] }}/100</div>
        </div>
      </div>
      <div class="assets-covered">Assets covered <b>{{ number_format($totalAssets) }}</b></div>

      <div class="panel-sep"></div>

      <div class="panel-head">
        <span class="panel-title">Groups by Security Rating</span>
      </div>
      @if ($groupRatings)
        <div class="rating-list">
          @foreach ($groupRatings as $row)
            <a class="rating-row" href="{{ route('groups.show', $row['group']) }}">
              <span class="grade-mini grade-{{ strtolower($row['vuln']['grade']) }}">{{ $row['vuln']['grade'] }}</span>
              <span class="rating-domain mono">{{ $row['group']->root_domain }}</span>
              <span class="rating-assets">{{ $row['group']->assets_count }} assets</span>
            </a>
          @endforeach
        </div>
      @else
        <p class="muted">No asset groups yet.</p>
        <a class="btn btn-primary btn-sm" href="{{ route('scans.new') }}">Scan a domain</a>
      @endif
    </div>
  </div>

  <div class="dash-grid">
    <div class="panel">
      <div class="panel-head">
        <span class="panel-title">Recent Vulnerabilities</span>
        <a class="view-all" href="{{ route('vulnerabilities.index') }}">View all →</a>
      </div>
      @if ($recentVulnerabilities->isNotEmpty())
        <div class="table-wrap">
          <table class="tbl">
            <thead><tr><th>Severity</th><th>Title</th><th>Host</th><th>Age</th></tr></thead>
            <tbody>
              @foreach ($recentVulnerabilities as $finding)
                <tr class="tbl-row-link" data-href="{{ route('findings.show', $finding) }}" tabindex="0" role="link" aria-label="Buka temuan: {{ $finding->title }}">
                  <td><span class="badge sev-{{ $finding->severity }}"><span class="dot"></span>{{ ucfirst($finding->severity) }}</span></td>
                  <td><a href="{{ route('findings.show', $finding) }}" class="finding-link">{{ $finding->title }}</a></td>
                  <td class="mono">{{ $finding->asset?->hostname ?: $finding->group->root_domain }}</td>
                  <td>{{ $finding->last_seen_at?->diffForHumans() }}</td>
                </tr>
              @endforeach
            </tbody>
          </table>
        </div>
      @else
        <p class="muted">No open vulnerabilities.</p>
      @endif
    </div>

    <div class="panel">
      <div class="panel-head">
        <span class="panel-title">Recent Incidents</span>
        <a class="view-all" href="{{ route('incidents.index') }}">View all →</a>
      </div>
      @if ($recentIncidents->isNotEmpty())
        <div class="incident-feed">
          @foreach ($recentIncidents as $finding)
            <div class="incident-row incident-row-link" data-href="{{ route('findings.show', $finding) }}" tabindex="0" role="link" aria-label="Buka temuan: {{ $finding->title }}">
              <span class="badge sev-{{ $finding->severity }}"><span class="dot"></span>{{ ucfirst($finding->severity) }}</span>
              <div class="incident-main">
                <div class="incident-title"><a href="{{ route('findings.show', $finding) }}" class="finding-link">{{ $finding->title }}</a></div>
                <div class="incident-sub"><span class="mono">{{ $finding->asset?->hostname ?: $finding->group->root_domain }}</span> · {{ $finding->category }}</div>
              </div>
              <span class="incident-time">{{ $finding->last_seen_at?->diffForHumans() }}</span>
            </div>
          @endforeach
        </div>
      @else
        <p class="muted">No open incident findings.</p>
      @endif
    </div>
  </div>
@endsection

@push('scripts')
<script>
(function () {
  'use strict';

  // Respect reduced-motion preference
  var prefersReduced = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;

  // Row/entry click navigation (keyboard + pointer)
  function bindRowLinks() {
    document.querySelectorAll('.tbl-row-link, .incident-row-link').forEach(function (row) {
      var href = row.dataset.href;
      if (!href) return;

      row.addEventListener('click', function (e) {
        if (e.target.closest('a')) return; // let inner links behave normally
        window.location.href = href;
      });

      row.addEventListener('keydown', function (e) {
        if (e.key === 'Enter' || e.key === ' ') {
          e.preventDefault();
          window.location.href = href;
        }
      });
    });
  }

  // Animated count-up for KPI values
  function runCountUps() {
    if (prefersReduced) return;
    document.querySelectorAll('.k-value[data-count]').forEach(function (el) {
      var target = parseInt(el.dataset.count, 10) || 0;
      var duration = 600;
      var start = null;

      function tick(ts) {
        if (!start) start = ts;
        var progress = Math.min((ts - start) / duration, 1);
        var eased = 1 - Math.pow(1 - progress, 3);
        el.textContent = Math.round(target * eased).toLocaleString('id-ID');
        if (progress < 1) requestAnimationFrame(tick);
        else el.textContent = target.toLocaleString('id-ID');
      }
      requestAnimationFrame(tick);
    });
  }

  // Entrance reveal with stagger for panels/KPIs
  function runReveal() {
    if (prefersReduced) return;
    var els = document.querySelectorAll('.kpi-row .kpi, .dash-grid .panel');
    els.forEach(function (el, i) {
      el.style.opacity = '0';
      el.style.transform = 'translateY(8px)';
      setTimeout(function () {
        el.style.transition = 'opacity 300ms ease, transform 300ms ease';
        el.style.opacity = '1';
        el.style.transform = 'translateY(0)';
      }, 40 + i * 45);
    });
  }

  document.addEventListener('DOMContentLoaded', function () {
    bindRowLinks();
    runCountUps();
    runReveal();
  });
})();
</script>
@endpush
