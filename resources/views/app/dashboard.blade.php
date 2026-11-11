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
      <p>Your perimeter at a glance.</p>
    </div>
    <div class="page-actions">
      <a class="btn btn-ghost btn-sm" href="{{ route('reports.index') }}">Reports</a>
      <a class="btn btn-primary btn-sm" href="{{ route('scans.new') }}">New Scan</a>
    </div>
  </div>

  <a class="banner {{ $bannerClass }}" href="{{ route('issues.index') }}">
    {{ $bannerText }} — review now
  </a>

  <div class="kpi-row">
    <a class="kpi kpi-link" href="{{ route('groups.index') }}">
      <div class="k-label">Total Assets</div>
      <div class="k-value">{{ number_format($totalAssets) }}</div>
    </a>
    <a class="kpi kpi-link" href="{{ route('groups.index') }}">
      <div class="k-label">Asset Groups</div>
      <div class="k-value">{{ $groups->count() }}</div>
    </a>
    <a class="kpi kpi-link {{ $openVulns->isNotEmpty() ? 'sev-'.($worstVulnSev ?? 'low') : '' }}" href="{{ route('vulnerabilities.index') }}">
      <div class="k-label">Open Vulnerabilities</div>
      <div class="k-value">{{ $openVulns->count() }}</div>
      <div class="k-sub">{{ $worstVulnSev ? $openVulns->where('severity',$worstVulnSev)->count().' '.$worstVulnSev : 'nothing open' }}</div>
    </a>
    <a class="kpi kpi-link {{ $openIncidents->isNotEmpty() ? 'sev-'.($worstIncidentSev ?? 'low') : '' }}" href="{{ route('incidents.index') }}">
      <div class="k-label">Open Incidents</div>
      <div class="k-value">{{ $openIncidents->count() }}</div>
      <div class="k-sub">{{ $worstIncidentSev ? $openIncidents->where('severity',$worstIncidentSev)->count().' '.$worstIncidentSev : 'nothing open' }}</div>
    </a>
    <a class="kpi kpi-link" href="{{ route('activity.index') }}">
      <div class="k-label">Scans This Week</div>
      <div class="k-value">{{ $scansThisWeek }}</div>
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
                <tr>
                  <td><span class="badge sev-{{ $finding->severity }}"><span class="dot"></span>{{ ucfirst($finding->severity) }}</span></td>
                  <td><a href="{{ route('findings.show', $finding) }}" style="color: inherit; text-decoration: underline; text-underline-offset: 3px;">{{ $finding->title }}</a></td>
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
            <div class="incident-row">
              <span class="badge sev-{{ $finding->severity }}"><span class="dot"></span>{{ ucfirst($finding->severity) }}</span>
              <div class="incident-main">
                <div class="incident-title"><a href="{{ route('findings.show', $finding) }}" style="color: inherit; text-decoration: underline; text-underline-offset: 3px;">{{ $finding->title }}</a></div>
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
