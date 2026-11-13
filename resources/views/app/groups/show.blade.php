@extends('layouts.app')

@section('title', $group->root_domain)

@section('content')
  <div class="page-head row">
    <div>
      <h1 class="mono">{{ $group->root_domain }}</h1>
      <p>{{ $group->assets_count ?? $assets->count() }} aset · scan terakhir {{ $group->last_scanned_at?->diffForHumans() ?? 'tidak pernah' }}</p>
    </div>
    <div class="page-actions">
      <a class="btn btn-ghost btn-sm" href="{{ route('groups.storyboard', $group) }}">
        <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" style="margin-right: 6px;"><path d="M22 12h-4l-3 9L9 3l-3 9H2"/></svg>
        Papan Cerita
      </a>
      <a class="btn btn-ghost btn-sm" href="{{ route('scans.new') }}">Scan Ulang</a>
      <form method="POST" action="{{ route('groups.destroy', $group) }}" onsubmit="return confirm('Delete this group and all its data?')">
        @csrf
        @method('DELETE')
        <button type="submit" class="btn btn-ghost btn-sm danger">Hapus grup</button>
      </form>
    </div>
  </div>

  {{-- AI POSTURE SUMMARY WIDGET --}}
  <div class="panel ai-posture-panel" id="aiPosturePanel" style="margin-bottom: 24px;">
    <div class="panel-head ai-panel-head" style="align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 12px;">
      <div class="ai-head-brand" style="display: flex; align-items: center; gap: 10px;">
        <span class="ai-spark-icon" aria-hidden="true">
          <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2"><path d="m12 3-1.9 5.8a2 2 0 0 1-1.3 1.3L3 12l5.8 1.9a2 2 0 0 1 1.3 1.3L12 21l1.9-5.8a2 2 0 0 1 1.3-1.3L21 12l-5.8-1.9a2 2 0 0 1-1.3-1.3Z"/></svg>
        </span>
        <div>
          <div class="panel-title" style="display: flex; align-items: center; gap: 8px;">
            <span>Ringkasan Postur AI</span>
            <span class="badge ai-badge"><span class="signal-dot pulse"></span>Intelijen Eksekutif</span>
          </div>
          <div class="panel-subtitle" style="font-size: 11.5px; color: var(--fg-subtle); margin-top: 2px;">
            Korelasi vulnerabilitas lintas-aset, rating eksposur, dan peta jalan remediasi
          </div>
        </div>
      </div>

      <div class="ai-head-actions">
        <button type="button" class="btn btn-primary btn-sm ai-btn" id="generateGroupSummaryBtn" data-url="{{ Route::has('groups.ai-summary') ? route('groups.ai-summary', $group) : url('/groups/' . $group->id . '/ai-summary') }}">
          <svg class="ai-btn-icon" viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/><polyline points="10 9 9 9 8 9"/></svg>
          <span class="btn-label">Ringkasan Eksekutif</span>
        </button>
      </div>
    </div>

    <div class="ai-card-body" id="aiPostureBody">
      <div class="ai-prompt-state" id="aiPosturePrompt">
        <div class="ai-callout">
          <div class="ai-callout-icon">
            <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>
          </div>
          <div class="ai-callout-text">
            <p style="margin: 0; font-size: 13.5px; color: var(--fg-default); line-height: 1.5;">
              Sintesiskan postur pertahanan perimeter untuk <strong class="mono" style="color: var(--accent, #00d2ff);">{{ $group->root_domain }}</strong>: <span class="mono" style="font-weight: 600;">{{ $assets->count() }}</span> aset perimeter, <span class="mono" style="color: var(--sev-critical); font-weight: 600;">{{ $openVulnerabilities->count() }}</span> vulnerabilitas terbuka, dan sinyal keamanan aktif.
            </p>
            <p style="margin: 4px 0 0; font-size: 12px; color: var(--fg-subtle);">
              Klik <strong>Ringkasan Eksekutif</strong> untuk memicu penilaian AI dengan metrik risiko utama dan tindakan teratas yang direkomendasikan.
            </p>
          </div>
        </div>
      </div>

      <div class="ai-loading-state hidden" id="aiPostureLoading" aria-live="polite">
        <div class="garda-spinner-wrap">
          <span class="garda-spinner" aria-hidden="true"></span>
          <div class="ai-loading-text">
            <div class="ai-loading-title">Mensintesiskan postur grup dengan AI Advisor...</div>
            <div class="ai-loading-sub mono">Mengevaluasi rating risiko (Vuln: {{ $ratings['vuln']['grade'] }}, Insiden: {{ $ratings['incident']['grade'] }}) &amp; jalur serangan aset</div>
          </div>
        </div>
      </div>

      <div class="ai-results-state hidden" id="aiPostureResults" aria-live="polite"></div>

      <div class="ai-error-state hidden" id="aiPostureError" role="alert"></div>
    </div>
  </div>

  <div class="grade-row group-grades">
    <div class="grade-card">
      <div class="grade-letter grade-{{ strtolower($ratings['vuln']['grade']) }}">{{ $ratings['vuln']['grade'] }}</div>
      <div class="grade-label">Rating Vulnerabilitas</div>
      <div class="grade-score">{{ $ratings['vuln']['score'] }}/100</div>
    </div>
    <div class="grade-card">
      <div class="grade-letter grade-{{ strtolower($ratings['incident']['grade']) }}">{{ $ratings['incident']['grade'] }}</div>
      <div class="grade-label">Rating Insiden</div>
      <div class="grade-score">{{ $ratings['incident']['score'] }}/100</div>
    </div>
  </div>

  <div class="tabs">
    @php $tabs = ['assets' => 'Aset', 'vulnerabilities' => 'Vulnerabilitas', 'incidents' => 'Insiden', 'history' => 'Riwayat']; @endphp
    @foreach ($tabs as $key => $label)
      <a class="tab {{ $tab === $key ? 'active' : '' }}" href="{{ route('groups.show', ['group' => $group, 'tab' => $key]) }}">{{ $label }}</a>
    @endforeach
  </div>

  @if ($tab === 'assets')
    @php
      $totalCount = $assets->count();
      $liveCount = $assets->filter(fn ($a) => (int)$a->http_status === 200)->count();
      $issuesCount = $assets->filter(fn ($a) => (int)$a->open_findings_count > 0)->count();
      $cleanCount = $assets->filter(fn ($a) => (int)$a->open_findings_count === 0)->count();
    @endphp

    <div class="asset-filter-bar" style="display: flex; align-items: center; justify-content: space-between; gap: 12px; flex-wrap: wrap; margin-bottom: 14px;">
      <div class="filter-pills-group" role="tablist" aria-label="Asset filters" id="assetFilterTabs">
        <button type="button" class="filter-pill active" data-filter="all" role="tab" aria-selected="true">
          All <span class="filter-count">{{ $totalCount }}</span>
        </button>
        <button type="button" class="filter-pill" data-filter="live" role="tab" aria-selected="false">
          Live (200) <span class="filter-count">{{ $liveCount }}</span>
        </button>
        <button type="button" class="filter-pill" data-filter="issues" role="tab" aria-selected="false">
          Issues (Findings &gt; 0) <span class="filter-count">{{ $issuesCount }}</span>
        </button>
        <button type="button" class="filter-pill" data-filter="clean" role="tab" aria-selected="false">
          Clean <span class="filter-count">{{ $cleanCount }}</span>
        </button>
      </div>

      <div class="asset-search-wrap" style="position: relative;">
        <input type="text" id="assetSearchFilter" class="input input-sm" placeholder="Search host, tech, ports..." style="padding-left: 30px; width: 220px; font-size: 12.5px; min-height: 34px;" aria-label="Search assets in group" />
        <svg style="position: absolute; left: 10px; top: 10px; color: var(--fg-subtle); pointer-events: none;" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
      </div>
    </div>

    <div class="table-wrap">
      <table class="tbl" id="assetTable">
        <thead>
          <tr><th>Host</th><th>HTTP</th><th>Server</th><th>Tech</th><th>Ports</th><th>Open findings</th></tr>
        </thead>
        <tbody>
          @forelse ($assets as $asset)
            <tr class="asset-row"
                data-http="{{ $asset->http_status }}"
                data-live="{{ (int)$asset->http_status === 200 ? '1' : '0' }}"
                data-issues="{{ (int)$asset->open_findings_count > 0 ? '1' : '0' }}"
                data-clean="{{ (int)$asset->open_findings_count === 0 ? '1' : '0' }}"
                data-search="{{ strtolower($asset->hostname . ' ' . $asset->server_header . ' ' . ($asset->technologies ? implode(' ', $asset->technologies) : '') . ' ' . ($asset->ports ? implode(' ', $asset->ports) : '')) }}">
              <td class="mono">{{ $asset->hostname }}</td>
              <td><span class="pill pill-status {{ (int)$asset->http_status === 200 ? 'ok' : '' }}">{{ $asset->http_status ?: '—' }}</span></td>
              <td class="mono">{{ $asset->server_header ?: '—' }}</td>
              <td class="mono">{{ $asset->technologies ? implode(', ', $asset->technologies) : '—' }}</td>
              <td class="mono">{{ $asset->ports ? implode(', ', $asset->ports) : '—' }}</td>
              <td>
                @if ($asset->open_findings_count > 0)
                  <span class="badge sev-critical"><span class="dot"></span>{{ $asset->open_findings_count }}</span>
                @else
                  <span class="pill ok">0 open</span>
                @endif
              </td>
            </tr>
          @empty
            <tr class="asset-empty-row"><td colspan="6" class="muted">No assets discovered yet. Run a scan for this group.</td></tr>
          @endforelse
          <tr class="asset-no-match-row hidden"><td colspan="6" class="muted" style="text-align: center; padding: 28px;">No assets match selected filter.</td></tr>
        </tbody>
      </table>
    </div>
  @elseif ($tab === 'vulnerabilities')
    <div class="table-wrap">
      <table class="tbl">
        <thead><tr><th>Severity</th><th>Title</th><th>Host</th><th>Category</th><th>Last seen</th></tr></thead>
        <tbody>
          @forelse ($openVulnerabilities->sortBy(fn ($f) => array_search($f->severity, \App\Models\Finding::SEVERITIES, true)) as $finding)
            <tr>
              <td><span class="badge sev-{{ $finding->severity }}"><span class="dot"></span>{{ ucfirst($finding->severity) }}</span></td>
              <td><a href="{{ route('findings.show', $finding) }}" style="color: inherit; text-decoration: underline; text-underline-offset: 3px;">{{ $finding->title }}</a></td>
              <td class="mono">{{ $finding->asset?->hostname ?: $group->root_domain }}</td>
              <td>{{ $finding->category }}</td>
              <td>{{ $finding->last_seen_at?->diffForHumans() }}</td>
            </tr>
          @empty
            <tr><td colspan="5" class="muted">No open vulnerabilities in this group.</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>
  @elseif ($tab === 'incidents')
    @if ($openIncidents->isEmpty())
      <div class="empty-state compact">
        <p class="muted">No open incident findings for this group. Review scan scope and completion before drawing conclusions.</p>
      </div>
    @else
      <div class="incident-cards">
        @foreach ($openIncidents as $finding)
          <div class="incident-tile">
            <div class="incident-evidence"><span class="evidence-label">Recorded evidence</span><pre>{{ $finding->evidence ?: 'No text evidence recorded. Review scan details before confirming this signal.' }}</pre></div>
            <div class="incident-tile-body">
              <span class="badge sev-{{ $finding->severity }}"><span class="dot"></span>{{ ucfirst($finding->severity) }}</span>
              <div class="incident-title"><a href="{{ route('findings.show', $finding) }}" style="color: inherit; text-decoration: underline; text-underline-offset: 3px;">{{ $finding->title }}</a></div>
              <div class="incident-sub"><span class="mono">{{ $finding->asset?->hostname ?: $group->root_domain }}</span> · {{ $finding->category }}</div>
              <div class="incident-sub">first seen {{ $finding->first_seen_at?->diffForHumans() }}</div>
              <a class="text-link" href="{{ route('findings.show', $finding) }}">Inspect playbook →</a>
            </div>
          </div>
        @endforeach
      </div>
    @endif
  @else
    <div class="table-wrap">
      <table class="tbl">
        <thead><tr><th>Scan</th><th>Status</th><th>Finished</th><th>Duration</th><th>Details</th></tr></thead>
        <tbody>
          @forelse ($scans as $scan)
            <tr>
              <td><span class="mode-badge">{{ $scan->modeBadge() }}</span> <span class="mono">{{ $group->root_domain }}</span></td>
              <td><span class="pill status-{{ $scan->status }}">{{ ucfirst($scan->status) }}</span></td>
              <td>{{ $scan->finished_at?->diffForHumans() ?? ($scan->started_at?->diffForHumans()) }}</td>
              <td class="mono">
                @if ($scan->started_at && $scan->finished_at)
                  {{ $scan->started_at->diffInMinutes($scan->finished_at) }}m
                @else
                  —
                @endif
              </td>
              <td><a class="view-all" href="{{ route('scans.show', $scan) }}">View scan</a></td>
            </tr>
          @empty
            <tr><td colspan="5" class="muted">No scans for this group yet.</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>
  @endif
@endsection
