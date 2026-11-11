@extends('layouts.marketing')

@section('title', 'GardaCyber — Perimeter Attack Surface Terminal')

@section('content')
  {{-- ======================= HERO / TERMINAL ======================= --}}
  <section class="perimeter-hero" id="top">
    <div class="hero-grid-bg" aria-hidden="true"></div>
    <div class="hero-scanlines" aria-hidden="true"></div>
    <div class="container hero-layout">
      <div class="hero-copy" data-reveal>
        <p class="product-label"><span class="signal-dot"></span> <span data-i18n="hero_label">external-attack-surface.terminal</span></p>
        <h1>
          <span data-i18n="hero_t1">Know your</span><br />
          <span data-i18n="hero_t2">perimeter.</span><br />
          <span class="accent-word" data-i18n="hero_t3">Own the next move.</span>
        </h1>
        <p class="hero-description" data-i18n="hero_desc">GardaCyber maps what you expose to the internet — assets, open ports, weak headers, and live compromise signals. Evidence in, decisions out, in one workspace.</p>
        <div class="hero-actions">
          <a class="btn btn-primary btn-lg" href="{{ route('signup') }}" data-i18n="hero_cta_launch">Launch scan workspace</a>
          <a class="text-link" href="#workspace-preview"><span data-i18n="hero_cta_see">See the console</span> <span aria-hidden="true">?</span></a>
        </div>
        <p class="hero-note" data-i18n="hero_cmd">$ ./gardacyber --scan perimeter --no-credit-card</p>
      </div>

      <div class="terminal-window" data-reveal aria-label="GardaCyber live demo terminal output">
        <div class="terminal-bar">
          <span class="traffic" aria-hidden="true"><i></i><i></i><i></i></span>
          <span class="terminal-title"><span data-i18n="hero_term_title">root@gardacyber: ~/scans</span> <span class="live-badge">● LIVE PROBE</span></span>
        </div>
        <div class="terminal-body">
          <div><span class="cmd">$</span> gardacyber scan --domain example.org --full</div>
          <div><span class="ok">[+]</span> <span data-i18n="hero_term_l1">resolving example.org → 103.x.x.x (VERIFIED PUBLIC)</span></div>
          <div><span class="ok">[+]</span> <span data-i18n="hero_term_l2">subdomain discovery → 12 live assets</span></div>
          <div><span class="ok">[+]</span> <span data-i18n="hero_term_l3">open ports → 80 443 8080</span></div>
          <div><span class="warn">[!]</span> <span data-i18n="hero_term_l4">HSTS header missing (vulnerability)</span></div>
          <div><span class="warn">[!]</span> <span data-i18n="hero_term_l5">/phpinfo.php exposed (exposure)</span></div>
          <div><span class="err">[×]</span> <span data-i18n="hero_term_l6">gambling-injection signature (incident)</span></div>
          <div><span class="ok">[+]</span> <span data-i18n="hero_term_l7">A–F rating computed → grade D · 64/100</span></div>
          <div><span class="cmd">$</span> <span data-i18n="hero_term_l8">cat report.csv | head -5</span><span class="cursor-blink" aria-hidden="true"></span></div>
        </div>
      </div>
    </div>

    <div class="partners" aria-hidden="true">
      <div class="partners-track">
        @for ($i = 0; $i < 2; $i++)
          <span class="partner-chip">nmap</span><span class="partner-chip">nuclei</span><span class="partner-chip">subfinder</span><span class="partner-chip">curl -v</span><span class="partner-chip">netcat</span><span class="partner-chip">dig</span><span class="partner-chip">whois</span><span class="partner-chip">crt.sh</span><span class="partner-chip">gobuster</span><span class="partner-chip">ffuf</span><span class="partner-chip">python3</span><span class="partner-chip">grep -r</span>
        @endfor
      </div>
    </div>
  </section>

  {{-- ======================= COVERAGE ======================= --}}
  <section class="section" id="coverage">
    <div class="container">
      <div class="section-head">
        <p class="section-kicker" data-i18n="cov_kicker">Reconnaissance coverage</p>
        <h2 data-i18n="cov_h2">Checks that run like an attacker's checklist.</h2>
        <p class="section-sub" data-i18n="cov_sub">Asset inventory, exposure probes, weak-header audit, and incident-signature sweeps — surfaced into one clear queue.</p>
      </div>

      <div class="stat-grid" data-reveal>
        <div class="stat-card">
          <div class="stat-value" data-count="14">0</div>
          <div class="stat-label" data-i18n="cov_stat1_l">Probe categories</div>
          <div class="stat-sub" data-i18n="cov_stat1_s">DNS, ports, headers, paths, incident keywords, and more.</div>
        </div>
        <div class="stat-card">
          <div class="stat-value" data-count="28">0</div>
          <div class="stat-label" data-i18n="cov_stat2_l">Detection signatures</div>
          <div class="stat-sub" data-i18n="cov_stat2_s">Defacement, gambling injection, SEO spam, exposure fingerprints.</div>
        </div>
        <div class="stat-card">
          <div class="stat-value">A–F</div>
          <div class="stat-label" data-i18n="cov_stat3_l">Security rating</div>
          <div class="stat-sub" data-i18n="cov_stat3_s">Density-aware grading that reflects worst open risk.</div>
        </div>
      </div>

      <div class="split">
        <div class="split-main">
          <h3 data-i18n="vuln_h3">Vulnerability probes</h3>
          <p class="muted" data-i18n="vuln_sub">Non-destructive checks of publicly visible configuration and response behavior.</p>
          <div class="rule-list">
            <div class="rule-row"><div class="rule-name" data-i18n="vuln_row1">Security headers</div><div class="rule-track"><span class="rule-fill" style="--w:100%"></span></div><div class="rule-count"><b>4</b> <span data-i18n="vuln_row1c">HSTS · CSP · nosniff · referrer</span></div></div>
            <div class="rule-row"><div class="rule-name" data-i18n="vuln_row2">Exposure paths</div><div class="rule-track"><span class="rule-fill" style="--w:100%"></span></div><div class="rule-count"><b>5</b> <span data-i18n="vuln_row2c">.git · .env · phpinfo · ignition · swagger</span></div></div>
            <div class="rule-row"><div class="rule-name" data-i18n="vuln_row3">Web injection hints</div><div class="rule-track"><span class="rule-fill" style="--w:70%"></span></div><div class="rule-count"><b>2</b> <span data-i18n="vuln_row3c">SQL error leak · reflected param</span></div></div>
            <div class="rule-row"><div class="rule-name" data-i18n="vuln_row4">Port fingerprint</div><div class="rule-track"><span class="rule-fill" style="--w:60%"></span></div><div class="rule-count"><b>4</b> <span data-i18n="vuln_row4c">80 · 443 · 8080 · 8443</span></div></div>
          </div>
          <div class="divider"></div>
          <h3 data-i18n="inc_h3">Incident signatures</h3>
          <p class="muted" data-i18n="inc_sub">Heuristic content signals tuned for SEA web compromise patterns.</p>
          <div class="rule-list">
            <div class="rule-row"><div class="rule-name" data-i18n="inc_row1">defacement</div><div class="rule-track"><span class="rule-fill fill-amber" style="--w:55%"></span></div><div class="rule-count"><b>hacked-by</b> <span data-i18n="inc_row1c">pwned · greetz</span></div></div>
            <div class="rule-row"><div class="rule-name" data-i18n="inc_row2">gambling-injection</div><div class="rule-track"><span class="rule-fill fill-amber" style="--w:85%"></span></div><div class="rule-count"><b>slot gacor</b> <span data-i18n="inc_row2c">maxwin · judi online</span></div></div>
            <div class="rule-row"><div class="rule-name" data-i18n="inc_row3">seo-spam</div><div class="rule-track"><span class="rule-fill fill-amber" style="--w:40%"></span></div><div class="rule-count"><b>hidden backlinks</b> <span data-i18n="inc_row3c">keyword stuffing</span></div></div>
          </div>
        </div>

        <aside class="split-side">
          <div class="panel">
            <div class="panel-head"><span class="panel-title" data-i18n="triage_title">Signal triage flow</span></div>
            <div class="terminal-body" style="padding:0;">
              <div><span class="ok">[+]</span> <span data-i18n="triage_l1">discovery → assets</span></div>
              <div><span class="ok">[+]</span> <span data-i18n="triage_l2">probes → vulnerabilities</span></div>
              <div><span class="warn">[!]</span> <span data-i18n="triage_l3">content sweep → incidents</span></div>
              <div><span class="ok">[+]</span> <span data-i18n="triage_l4">rating → A–F</span></div>
              <div><span class="ok">[+]</span> <span data-i18n="triage_l5">triage → resolved / false positive</span></div>
            </div>
            <div class="divider"></div>
            <p class="note" data-i18n="triage_note">Every finding keeps its evidence. Nothing is auto-dismissed.</p>
          </div>
        </aside>
      </div>
    </div>
  </section>

  {{-- ======================= WORKSPACE PREVIEW ======================= --}}
  <section class="section section-alt workspace-section" id="features">
    <div class="container">
      <div class="preview-heading">
        <div class="section-head">
          <p class="section-kicker" data-i18n="ws_kicker">From signal to decision</p>
          <h2 data-i18n="ws_h2">One console. All the evidence.</h2>
        </div>
        <p data-i18n="ws_side">Assets, findings, decisions —<br />organized and inspectable.</p>
      </div>

      <div class="workspace-preview browser-frame" data-reveal id="workspace-preview">
        <div class="browser-bar">
          <span class="traffic" aria-hidden="true"><i></i><i></i><i></i></span>
          <span class="browser-url">console.gardacyber.test/overview</span>
          <span class="live-badge">● live</span>
        </div>
        <div class="browser-body">
          <div class="preview-summary">
            <div><span data-i18n="ws_preview_1l">Assets in scope</span><strong>12</strong></div>
            <div><span data-i18n="ws_preview_2l">Open vulnerabilities</span><strong class="sev-high">3</strong></div>
            <div><span data-i18n="ws_preview_3l">Open incidents</span><strong class="sev-critical">1</strong></div>
            <div><span data-i18n="ws_preview_4l">Asset group</span><strong class="preview-domain mono">example.org</strong></div>
          </div>
          <div class="table-wrap" tabindex="0" role="region" aria-label="Sample findings table">
            <table class="tbl">
              <thead><tr><th data-i18n="ws_tbl_sev">Severity</th><th data-i18n="ws_tbl_find">Finding</th><th data-i18n="ws_tbl_asset">Asset</th><th data-i18n="ws_tbl_ev">Evidence</th></tr></thead>
              <tbody>
                <tr><td><span class="badge sev-critical"><span class="dot"></span>Critical</span></td><td data-i18n="ws_tbl_r1">Exposed .git repository</td><td class="mono">legacy.example.org</td><td class="mono" data-i18n="ws_tbl_r1e">HTTP 200 · ref: refs/</td></tr>
                <tr><td><span class="badge sev-high"><span class="dot"></span>High</span></td><td data-i18n="ws_tbl_r2">Gambling content injection</td><td class="mono">news.example.org</td><td class="mono" data-i18n="ws_tbl_r2e">matches "slot gacor"</td></tr>
                <tr><td><span class="badge sev-medium"><span class="dot"></span>Medium</span></td><td data-i18n="ws_tbl_r3">HSTS header missing</td><td class="mono">www.example.org</td><td class="mono" data-i18n="ws_tbl_r3e">response header absent</td></tr>
              </tbody>
            </table>
          </div>
          <p class="note" data-i18n="ws_note">Illustrative findings — sample workspace data, not a live target.</p>
        </div>
      </div>
    </div>
  </section>

  {{-- ======================= INCIDENT CHECK ======================= --}}
  <section class="section" id="incident-check">
    <div class="container">
      <div class="section-head">
        <p class="section-kicker" data-i18n="inc_kicker">Unique to GardaCyber</p>
        <h2 data-i18n="inc_h2">Incident Check.<br />What a regular scanner skips.</h2>
        <p class="section-sub" data-i18n="inc_sub2">Beyond CVEs, GardaCyber sweeps indexed content for signs a live page has already been compromised.</p>
      </div>

      <div class="incident-grid" data-reveal>
        <div class="incident-card">
          <div class="incident-icon" aria-hidden="true">!</div>
          <h3 data-i18n="inc_card1_t">Defacement</h3>
          <p data-i18n="inc_card1_d">Hacked-by pages, pwned messages, and attacker greetz markers.</p>
        </div>
        <div class="incident-card">
          <div class="incident-icon" aria-hidden="true">$</div>
          <h3 data-i18n="inc_card2_t">Gambling injection</h3>
          <p data-i18n="inc_card2_d">Slot gacor, maxwin, judi online keywords injected into your pages.</p>
        </div>
        <div class="incident-card">
          <div class="incident-icon" aria-hidden="true">@</div>
          <h3 data-i18n="inc_card3_t">SEO spam</h3>
          <p data-i18n="inc_card3_d">Hidden backlinks and keyword-stuffed doorway content.</p>
        </div>
      </div>

      <div class="browser-frame">
        <div class="browser-bar">
          <span class="traffic" aria-hidden="true"><i></i><i></i><i></i></span>
          <span class="browser-url">console.gardacyber.test/incidents</span>
          <span class="live-badge">● live</span>
        </div>
        <div class="browser-body">
          <div class="panel-head"><span class="panel-title" data-i18n="inc_queue">Incident queue — evidence first</span></div>
          <div class="incident-feed">
            <div class="incident-row">
              <span class="badge sev-critical"><span class="dot"></span>Critical</span>
              <div class="incident-main">
                <div class="incident-title" data-i18n="inc_row1_t">Defacement signature: hacked-by</div>
                <div class="incident-sub"><span class="mono">news.example.org/post-3421</span> · defacement</div>
              </div>
              <span class="incident-time">2m ago</span>
            </div>
            <div class="incident-row">
              <span class="badge sev-high"><span class="dot"></span>High</span>
              <div class="incident-main">
                <div class="incident-title" data-i18n="inc_row2_t">Gambling injection: slot gacor</div>
                <div class="incident-sub"><span class="mono">example.org/archive/2019/</span> · gambling-injection</div>
              </div>
              <span class="incident-time">14m ago</span>
            </div>
            <div class="incident-row">
              <span class="badge sev-medium"><span class="dot"></span>Medium</span>
              <div class="incident-main">
                <div class="incident-title" data-i18n="inc_row3_t">Hidden SEO spam backlinks</div>
                <div class="incident-sub"><span class="mono">legacy.example.org</span> · seo-spam</div>
              </div>
              <span class="incident-time">1h ago</span>
            </div>
          </div>
          <div class="table-foot" data-i18n="inc_foot">5 open · 12 resolved this week · last check 4m ago</div>
        </div>
      </div>
    </div>
  </section>

  {{-- ======================= FEATURES ======================= --}}
  <section class="section section-alt" id="capabilities">
    <div class="container">
      <div class="section-head">
        <p class="section-kicker" data-i18n="cap_kicker">Capabilities</p>
        <h2 data-i18n="cap_h2">Built for focused security work.</h2>
        <p class="section-sub" data-i18n="cap_sub">Everything in one attacking surface workspace — no agents, no noise.</p>
      </div>

      <div class="feature-grid" data-reveal>
        <div class="feature-card"><div class="fc-icon" aria-hidden="true">◎</div><h3 data-i18n="cap_c1_t">Asset inventory</h3><p data-i18n="cap_c1_d">Discovered hosts grouped by root domain with HTTP status, server, ports, and technologies.</p></div>
        <div class="feature-card"><div class="fc-icon" aria-hidden="true">⌁</div><h3 data-i18n="cap_c2_t">Exposure probes</h3><p data-i18n="cap_c2_d">Non-destructive checks for sensitive paths, weak headers, and web injection hints.</p></div>
        <div class="feature-card"><div class="fc-icon orange" aria-hidden="true">!</div><h3 data-i18n="cap_c3_t">Incident signatures</h3><p data-i18n="cap_c3_d">Defacement, gambling injection, and SEO spam sweeps with matched evidence.</p></div>
        <div class="feature-card"><div class="fc-icon" aria-hidden="true">A</div><h3 data-i18n="cap_c4_t">A–F security rating</h3><p data-i18n="cap_c4_d">Density-aware grading driven by your worst open severity, not averages.</p></div>
        <div class="feature-card"><div class="fc-icon" aria-hidden="true">▮</div><h3 data-i18n="cap_c5_t">Live scan console</h3><p data-i18n="cap_c5_d">Watch each probe phase run with progress, counts, and final status.</p></div>
        <div class="feature-card"><div class="fc-icon" aria-hidden="true">›</div><h3 data-i18n="cap_c6_t">Remediation playbooks</h3><p data-i18n="cap_c6_d">Per-finding guidance, hardening snippets, and verification standards.</p></div>
      </div>
    </div>
  </section>

  {{-- ======================= WORKFLOW ======================= --}}
  <section class="section" id="how">
    <div class="container">
      <div class="section-head">
        <p class="section-kicker" data-i18n="wf_kicker">Workflow</p>
        <h2 data-i18n="wf_h2">One scan. Five stages.</h2>
        <p class="section-sub" data-i18n="wf_sub">The same chain, in the same order, every time.</p>
      </div>

      <div class="stage-grid" data-reveal>
        <div class="stage-card"><span class="stage-num">01</span><h3 data-i18n="wf_s1_t">Discover</h3><p data-i18n="wf_s1_d">Resolve DNS and enumerate live subdomains.</p><span class="stage-tag" data-i18n="wf_s1_tag">dig · DNS A/AAAA</span></div>
        <div class="stage-card"><span class="stage-num">02</span><h3 data-i18n="wf_s2_t">Probe</h3><p data-i18n="wf_s2_d">Fingerprint HTTP state and open web ports.</p><span class="stage-tag" data-i18n="wf_s2_tag">80 · 443 · 8080 · 8443</span></div>
        <div class="stage-card"><span class="stage-num">03</span><h3 data-i18n="wf_s3_t">Analyze</h3><p data-i18n="wf_s3_d">Check headers, exposure paths, and injection hints.</p><span class="stage-tag" data-i18n="wf_s3_tag">HSTS · .git · SQL-error</span></div>
        <div class="stage-card"><span class="stage-num">04</span><h3 data-i18n="wf_s4_t">Sweep</h3><p data-i18n="wf_s4_d">Scan visible content for compromise signatures.</p><span class="stage-tag" data-i18n="wf_s4_tag">defacement · gambling · spam</span></div>
        <div class="stage-card"><span class="stage-num">05</span><h3 data-i18n="wf_s5_t">Grade</h3><p data-i18n="wf_s5_d">Compute A–F rating and queue findings for triage.</p><span class="stage-tag" data-i18n="wf_s5_tag">worst-open-severity</span></div>
      </div>

      <div class="stage-legend">
        <p data-i18n="wf_legend">Stages 03–04 produce <b>vulnerabilities</b> and <b>incidents</b> in separate lanes.</p>
      </div>

      <div class="rating-panel">
        <div class="rating-panel-left">
          <div class="mini-title" data-i18n="wf_rating">Security rating</div>
          <div class="grade-row">
            <div class="grade-card lg"><div class="grade-letter grade-d">D</div><div class="grade-label">example.org</div></div>
          </div>
        </div>
        <div class="rating-panel-right">
          <div class="rating-stat"><span class="rs-label" data-i18n="wf_rating_crit">Critical</span><span class="rs-value sev-critical">1</span></div>
          <div class="rating-stat"><span class="rs-label" data-i18n="wf_rating_high">High</span><span class="rs-value sev-high">3</span></div>
          <div class="rating-stat"><span class="rs-label" data-i18n="wf_rating_med">Medium</span><span class="rs-value sev-medium">6</span></div>
          <div class="rating-stat"><span class="rs-label" data-i18n="wf_rating_low">Low</span><span class="rs-value sev-low">4</span></div>
          <div class="rating-stat"><span class="rs-label" data-i18n="wf_rating_assets">Assets</span><span class="rs-value">142</span></div>
          <div class="rating-stat"><span class="rs-label" data-i18n="wf_rating_vulns">Open vulns</span><span class="rs-value">27</span></div>
          <div class="rating-stat"><span class="rs-label" data-i18n="wf_rating_incs">Open incidents</span><span class="rs-value">4</span></div>
          <div class="rating-stat"><span class="rs-label" data-i18n="wf_rating_res">Resolved</span><span class="rs-value">63</span></div>
        </div>
      </div>
    </div>
  </section>
@endsection
