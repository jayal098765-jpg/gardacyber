@extends('layouts.app')

@section('title', $finding->title.' · GardaCyber')

@section('content')
  <div style="margin-bottom: 16px; font-size: 13px; color: var(--fg-muted, #94a3b8);">
    <a href="{{ route('groups.index') }}" style="color: var(--fg-muted, #94a3b8);">Asset Groups</a>
    <span style="margin: 0 6px;">/</span>
    <a href="{{ route('groups.show', $finding->group) }}" class="mono" style="color: var(--accent, #00d2ff);">{{ $finding->group->root_domain }}</a>
    <span style="margin: 0 6px;">/</span>
    <span>Finding #{{ $finding->id }}</span>
  </div>

  <div class="page-head row" style="align-items: flex-start;">
    <div>
      <div style="display: flex; align-items: center; gap: 8px; margin-bottom: 8px; flex-wrap: wrap;">
        <span class="badge sev-{{ $finding->severity }}">
          <span class="dot"></span>{{ ucfirst($finding->severity) }}
        </span>
        <span class="pill pill-kind" style="text-transform: uppercase; font-size: 11px; letter-spacing: 0.06em;">
          {{ $finding->kind }}
        </span>
        <span class="pill {{ $finding->status === 'open' ? 'warn' : ($finding->status === 'resolved' ? 'ok' : 'pill-status') }}">
          {{ $finding->status === 'false_positive' ? 'False Positive' : ucfirst($finding->status) }}
        </span>
        @if ($finding->cve_id)
          <a href="https://nvd.nist.gov/vuln/detail/{{ $finding->cve_id }}" target="_blank" rel="noopener" class="badge cve-badge">{{ $finding->cve_id }}</a>
        @endif
        @if ($finding->owasp && $owaspLabels->has($finding->owasp))
          <span class="badge owasp-badge">{{ $finding->owasp }} — {{ $owaspLabels->get($finding->owasp) }}</span>
        @endif
      </div>
      <h1 style="font-size: 24px; font-weight: 700; line-height: 1.3;">{{ $finding->title }}</h1>
      <p class="mono" style="font-size: 14px; margin-top: 6px; color: var(--fg-muted, #94a3b8);">
        Target: <strong style="color: var(--fg-default, #fff);">{{ $finding->asset?->hostname ?: $finding->group->root_domain }}</strong>
      </p>
    </div>

    <div class="page-actions">
      <a class="btn btn-ghost btn-sm" href="{{ route('groups.show', $finding->group) }}">View Asset Group</a>
    </div>
  </div>

  <div class="dash-grid" style="grid-template-columns: 2fr 1fr; gap: 20px; align-items: start; margin-top: 20px;">
    <div>
      {{-- EVIDENCE CARD --}}
      <div class="panel" style="margin-bottom: 20px;">
        <div class="panel-head">
          <span class="panel-title">Technical Observation &amp; Evidence</span>
          <span class="panel-total mono">{{ $finding->category }}</span>
        </div>

        @if ($finding->description)
          <p style="font-size: 14px; line-height: 1.6; margin-bottom: 16px; color: var(--fg-default, #f1f5f9);">
            {{ $finding->description }}
          </p>
        @endif

        <div style="background: var(--bg-surface-2, #0d1527); border: 1px solid var(--border-subtle, rgba(255,255,255,0.08)); border-radius: 8px; padding: 14px;">
          <div style="font-size: 11px; text-transform: uppercase; letter-spacing: 0.08em; color: var(--fg-subtle, #64748b); margin-bottom: 8px;">
            Raw Signal Fingerprint
          </div>
          <pre class="mono" style="margin: 0; font-size: 12px; line-height: 1.5; color: #38bdf8; white-space: pre-wrap; word-break: break-all;">{{ $finding->evidence ?: 'No raw text evidence recorded for this finding.' }}</pre>
        </div>
      </div>

      {{-- AI REMEDIATION ANALYSIS SECTION --}}
      <div class="panel ai-advisor-panel" id="aiRemediationCard" style="margin-bottom: 20px;">
        <div class="panel-head ai-panel-head">
          <div class="ai-head-brand">
            <span class="ai-spark-icon" aria-hidden="true">
              <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2"><path d="m12 3-1.9 5.8a2 2 0 0 1-1.3 1.3L3 12l5.8 1.9a2 2 0 0 1 1.3 1.3L12 21l1.9-5.8a2 2 0 0 1 1.3-1.3L21 12l-5.8-1.9a2 2 0 0 1-1.3-1.3Z"/></svg>
            </span>
            <div>
              <div class="panel-title" style="display: flex; align-items: center; gap: 8px;">
                <span>AI Remediation Analysis</span>
                <span class="badge ai-badge"><span class="signal-dot pulse"></span>AI Advisor</span>
              </div>
              <div class="panel-subtitle" style="font-size: 11.5px; color: var(--fg-subtle); margin-top: 2px;">
                Context-aware root-cause analysis and automated hardening commands
              </div>
            </div>
          </div>

          <div class="ai-head-actions">
            @if (!empty($guide['config_snippet']))
              <button type="button" class="btn btn-ghost btn-sm copy-btn" id="copyFallbackSnippetBtn" data-copy="{{ $guide['config_snippet'] }}" title="Copy local deterministic playbook snippet">
                <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2"><rect width="14" height="14" x="8" y="8" rx="2" ry="2"/><path d="M4 16c-1.1 0-2-.9-2-2V4c0-1.1.9-2 2-2h10c1.1 0 2 .9 2 2"/></svg>
                <span class="copy-text">Copy Local Playbook Snippet</span>
              </button>
            @endif

            <button type="button" class="btn btn-primary btn-sm ai-btn" id="analyzeFindingBtn" data-url="{{ Route::has('findings.ai-explain') ? route('findings.ai-explain', $finding) : url('/findings/' . $finding->id . '/ai-explain') }}">
              <svg class="ai-btn-icon" viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 2v4M12 18v4M4.93 4.93l2.83 2.83M16.24 16.24l2.83 2.83M2 12h4M18 12h4M4.93 19.07l2.83-2.83M16.24 7.76l2.83-2.83"/></svg>
              <span class="btn-label">Analyze with AI Advisor</span>
            </button>
          </div>
        </div>

        <div class="ai-card-body" id="aiAdvisorContent">
          <div class="ai-prompt-state" id="aiPromptState">
            <div class="ai-callout">
              <div class="ai-callout-icon">
                <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>
              </div>
              <div class="ai-callout-text">
                <p style="margin: 0; font-size: 13.5px; color: var(--fg-default); line-height: 1.5;">
                  Request instant AI analysis for this <strong style="color: var(--accent, #00d2ff);">{{ $finding->category }}</strong> finding on <strong class="mono" style="color: var(--fg-default);">{{ $finding->asset?->hostname ?: $finding->group->root_domain }}</strong>.
                </p>
                <p style="margin: 4px 0 0; font-size: 12px; color: var(--fg-subtle);">
                  Evaluates target server header, observed evidence payload, and root-cause factors to generate actionable hardening commands.
                </p>
              </div>
            </div>
          </div>

          <div class="ai-loading-state hidden" id="aiLoadingState" aria-live="polite">
            <div class="garda-spinner-wrap">
              <span class="garda-spinner" aria-hidden="true"></span>
              <div class="ai-loading-text">
                <div class="ai-loading-title">Analyzing signal with AI Advisor...</div>
                <div class="ai-loading-sub mono">Correlating target fingerprint &amp; root cause vectors</div>
              </div>
            </div>
          </div>

          <div class="ai-results-state hidden" id="aiResultsState" aria-live="polite"></div>

          <div class="ai-error-state hidden" id="aiErrorState" role="alert"></div>
        </div>
      </div>

      {{-- REMEDIATION PLAYBOOK --}}
      <div class="panel">
        <div class="panel-head">
          <span class="panel-title">Remediation &amp; Hardening Playbook</span>
          <span class="pill ok" style="font-size: 11px;">GardaCyber Guidance</span>
        </div>

        <div style="margin-bottom: 16px;">
          <h4 style="font-size: 13.5px; font-weight: 600; margin-bottom: 6px; color: var(--fg-default, #f1f5f9);">Overview</h4>
          <p style="font-size: 13.5px; color: var(--fg-muted, #94a3b8); margin: 0; line-height: 1.5;">{{ $guide['summary'] }}</p>
        </div>

        <div style="margin-bottom: 16px;">
          <h4 style="font-size: 13.5px; font-weight: 600; margin-bottom: 6px; color: var(--fg-default, #f1f5f9);">Risk &amp; Impact Analysis</h4>
          <p style="font-size: 13.5px; color: var(--fg-muted, #94a3b8); margin: 0; line-height: 1.5;">{{ $guide['impact'] }}</p>
        </div>

        <div style="margin-bottom: 18px;">
          <h4 style="font-size: 13.5px; font-weight: 600; margin-bottom: 8px; color: var(--fg-default, #f1f5f9);">Actionable Step-by-Step Remediation</h4>
          <ol style="margin: 0; padding-left: 20px; font-size: 13.5px; color: var(--fg-muted, #94a3b8); line-height: 1.6;">
            @foreach ($guide['steps'] as $step)
              <li style="margin-bottom: 6px;">{{ $step }}</li>
            @endforeach
          </ol>
        </div>

        @if ($guide['config_snippet'])
          <div style="margin-bottom: 18px;">
            <details style="background: var(--bg-surface-2, #0d1527); border: 1px solid var(--border-subtle, rgba(255,255,255,0.08)); border-radius: 8px; padding: 12px; position: relative;">
              <summary style="cursor: pointer; display: flex; align-items: center; justify-content: space-between; list-style: none;">
                <span style="font-size: 13px; color: var(--fg-muted, #94a3b8);">Show snippet</span>
                <button type="button" class="btn btn-ghost btn-sm copy-btn" data-copy="{{ $guide['config_snippet'] }}" style="padding: 4px 10px; min-height: 28px; font-size: 11.5px;">
                  <svg viewBox="0 0 24 24" width="13" height="13" fill="none" stroke="currentColor" stroke-width="2"><rect width="14" height="14" x="8" y="8" rx="2" ry="2"/><path d="M4 16c-1.1 0-2-.9-2-2V4c0-1.1.9-2 2-2h10c1.1 0 2 .9 2 2"/></svg>
                  <span class="copy-text">Copy</span>
                </button>
              </summary>
              <pre class="mono" style="margin: 12px 0 0; font-size: 12px; line-height: 1.45; color: #a5f3fc; white-space: pre-wrap;">{{ $guide['config_snippet'] }}</pre>
            </details>
          </div>
        @endif

        <div>
          <h4 style="font-size: 13.5px; font-weight: 600; margin-bottom: 4px; color: var(--fg-default, #f1f5f9);">Verification Standard</h4>
          <p style="font-size: 13px; color: var(--fg-subtle, #64748b); margin: 0;">{{ $guide['verification'] }}</p>
        </div>
      </div>
    </div>

    {{-- SIDEBAR TRIAGE & DETAILS --}}
    <div>
      <div class="panel" style="margin-bottom: 20px;">
        <div class="panel-head">
          <span class="panel-title">Triage Decision</span>
        </div>

        <form method="POST" action="{{ route('findings.update', $finding) }}" style="display: flex; flex-direction: column; gap: 10px;">
          @csrf
          @method('PATCH')

          @if ($finding->status === 'open')
            <button class="btn btn-primary" type="submit" name="status" value="resolved" style="width: 100%; justify-content: center;">
              Mark as Resolved
            </button>
            <button class="btn btn-ghost" type="submit" name="status" value="false_positive" style="width: 100%; justify-content: center;">
              Mark as False Positive
            </button>
          @else
            <button class="btn btn-primary" type="submit" name="status" value="open" style="width: 100%; justify-content: center;">
              Reopen Finding
            </button>
          @endif
        </form>

        <div style="height: 1px; background: var(--border-subtle, rgba(255,255,255,0.08)); margin: 16px 0;"></div>

        <form method="POST" action="{{ route('findings.destroy', $finding) }}" onsubmit="return confirm('Permanently remove this finding record?')">
          @csrf
          @method('DELETE')
          <button class="btn btn-ghost danger btn-sm" type="submit" style="width: 100%; justify-content: center;">
            Delete Record
          </button>
        </form>
      </div>

      <div class="panel">
        <div class="panel-head">
          <span class="panel-title">Metadata</span>
        </div>

        <dl style="margin: 0; font-size: 13px; display: grid; gap: 12px;">
          <div>
            <dt style="color: var(--fg-subtle, #64748b); font-size: 11px; text-transform: uppercase;">Category</dt>
            <dd class="mono" style="margin: 2px 0 0; color: var(--fg-default, #fff);">{{ $finding->category }}</dd>
          </div>
          @if ($finding->cve_id)
            <div>
              <dt style="color: var(--fg-subtle, #64748b); font-size: 11px; text-transform: uppercase;">CVE Reference</dt>
              <dd style="margin: 2px 0 0;"><a class="mono" href="https://nvd.nist.gov/vuln/detail/{{ $finding->cve_id }}" target="_blank" rel="noopener" style="color: var(--accent, #00d2ff);">{{ $finding->cve_id }}</a></dd>
            </div>
          @endif
          @if ($finding->owasp && $owaspLabels->has($finding->owasp))
            <div>
              <dt style="color: var(--fg-subtle, #64748b); font-size: 11px; text-transform: uppercase;">OWASP Top 10</dt>
              <dd style="margin: 2px 0 0; color: var(--fg-default, #fff);">{{ $finding->owasp }} — {{ $owaspLabels->get($finding->owasp) }}</dd>
            </div>
          @endif
          <div>
            <dt style="color: var(--fg-subtle, #64748b); font-size: 11px; text-transform: uppercase;">Asset Hostname</dt>
            <dd class="mono" style="margin: 2px 0 0; color: var(--fg-default, #fff);">{{ $finding->asset?->hostname ?: 'Root domain' }}</dd>
          </div>
          <div>
            <dt style="color: var(--fg-subtle, #64748b); font-size: 11px; text-transform: uppercase;">Asset Group</dt>
            <dd class="mono" style="margin: 2px 0 0;"><a href="{{ route('groups.show', $finding->group) }}">{{ $finding->group->root_domain }}</a></dd>
          </div>
          <div>
            <dt style="color: var(--fg-subtle, #64748b); font-size: 11px; text-transform: uppercase;">First Observed</dt>
            <dd style="margin: 2px 0 0; color: var(--fg-default, #fff);">{{ $finding->first_seen_at ? $finding->first_seen_at->toDayDateTimeString() : '—' }}</dd>
          </div>
          <div>
            <dt style="color: var(--fg-subtle, #64748b); font-size: 11px; text-transform: uppercase;">Last Observed</dt>
            <dd style="margin: 2px 0 0; color: var(--fg-default, #fff);">{{ $finding->last_seen_at ? $finding->last_seen_at->toDayDateTimeString() : '—' }}</dd>
          </div>
        </dl>
      </div>
    </div>
  </div>
@endsection
