@extends('layouts.app')

@section('title', 'Papan Cerita Serangan & Simulator Pelanggaran · ' . $group->root_domain)

@section('content')
  <div class="page-head row">
    <div>
      <div style="display: flex; align-items: center; gap: 8px; margin-bottom: 8px; flex-wrap: wrap;">
        <span class="badge sb-badge"><span class="signal-dot pulse"></span>Papan Cerita Serangan · Simulator Pelanggaran AI</span>
        <span class="pill sev-{{ strtolower($storyboard['blastRadius']['level']) === 'critical' ? 'critical' : (strtolower($storyboard['blastRadius']['level']) === 'high' ? 'high' : 'ok') }}">
          Radius Luluhlantak: {{ $storyboard['blastRadius']['level'] }}
        </span>
        <span class="pill info">Analisis Rantai Kill-Chain</span>
      </div>
      <h1 class="mono">{{ $group->root_domain }}</h1>
      <p>{{ $assetsCount }} aset · {{ $storyboard['totalFindings'] }} temuan terbuka · {{ count($storyboard['attackChain']) }} langkah kill-chain terpetakan</p>
    </div>
    <div class="page-actions">
      <button type="button" class="btn btn-primary btn-sm" onclick="startAttackSimulation()">
        <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="margin-right: 4px;"><polygon points="5 3 19 12 5 21 5 3"/></svg>
        Mainkan Simulasi
      </button>
      <a class="btn btn-ghost btn-sm" href="#shield-box">
        <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="margin-right: 4px;"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
        Perisai Remediasi
      </a>
      <button type="button" class="btn btn-ghost btn-sm" onclick="window.print()">
        <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="margin-right: 4px;"><polyline points="6 9 6 2 18 2 18 9"/><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><rect x="6" y="14" width="12" height="8"/></svg>
        Ekspor PDF
      </button>
      <a class="btn btn-ghost btn-sm" href="{{ route('groups.show', $group) }}">Kembali ke grup</a>
    </div>
  </div>

  {{-- SECTION 1: BLAST RADIUS & THREAT PROFILE --}}
  <div class="sb-blast-grid">
    <div class="sb-blast-card">
      <div class="sb-blast-card-head">
        <span class="sb-blast-label">Tingkat Risiko &amp; Ancaman</span>
        <div class="sb-blast-icon">
          <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
        </div>
      </div>
      <div class="sb-blast-val level-{{ strtolower($storyboard['blastRadius']['level']) }}">
        {{ $storyboard['blastRadius']['level'] }}
      </div>
      <div class="sb-blast-sub">
        <strong>Aktor:</strong> {{ $storyboard['blastRadius']['threat_actor'] }}
      </div>
    </div>

    <div class="sb-blast-card">
      <div class="sb-blast-card-head">
        <span class="sb-blast-label">Estimasi Kerugian Finansial</span>
        <div class="sb-blast-icon" style="background: rgba(245, 158, 11, 0.12); color: #fbbf24;">
          <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>
        </div>
      </div>
      <div class="sb-blast-val" style="font-size: 16px;">
        {{ $storyboard['blastRadius']['financial_impact'] }}
      </div>
      <div class="sb-blast-sub">
        Potensi denda insiden, audit forensik, &amp; pemulihan reputasi
      </div>
    </div>

    <div class="sb-blast-card">
      <div class="sb-blast-card-head">
        <span class="sb-blast-label">Risiko Regulasi &amp; Kepatuhan</span>
        <div class="sb-blast-icon" style="background: rgba(239, 68, 68, 0.12); color: #f87171;">
          <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>
        </div>
      </div>
      <div class="sb-blast-val" style="font-size: 14.5px; font-weight: 600;">
        {{ $storyboard['blastRadius']['compliance_risk'] }}
      </div>
      <div class="sb-blast-sub">
        Mandat pelaporan insiden 3x24 jam ke otoritas data
      </div>
    </div>

    <div class="sb-blast-card">
      <div class="sb-blast-card-head">
        <span class="sb-blast-label">Estimasi Waktu Pemulihan</span>
        <div class="sb-blast-icon" style="background: rgba(16, 185, 129, 0.12); color: #34d399;">
          <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
        </div>
      </div>
      <div class="sb-blast-val" style="font-size: 16px;">
        {{ $storyboard['blastRadius']['downtime_est'] }}
      </div>
      <div class="sb-blast-sub">
        Mean Time to Remediation (MTTR) bila rantai serangan terpicu
      </div>
    </div>
  </div>

  {{-- NARRATIVE STORY --}}
  <div class="panel sb-narrative" style="margin-bottom: 24px;">
    <div class="panel-head">
      <span class="panel-title">Skenario Rantai Serangan (Narasi Ancaman)</span>
    </div>
    <ol class="sb-narrative-list">
      @foreach ($storyboard['narrative'] as $line)
        <li>{{ $line }}</li>
      @endforeach
    </ol>
  </div>

  {{-- SECTION 2: VISUAL ATTACK GRAPH PIPELINE --}}
  <div class="panel sb-graph-container">
    <div class="panel-head" style="margin-bottom: 16px;">
      <div>
        <span class="panel-title">Visual Pipeline Graf Serangan (Rantai Kill-Chain 5 Tahap)</span>
        <p style="font-size: 12.5px; color: var(--fg-muted); margin: 4px 0 0;">
          Alur pergerakan ancaman dari penemuan target hingga eskalasi dampak bisnis
        </p>
      </div>
    </div>

    <div class="sb-graph-pipeline">
      @foreach ($storyboard['phases'] as $idx => $phase)
        <div class="sb-graph-col">
          <div class="sb-phase {{ $phase['count'] > 0 ? 'has-findings' : '' }} status-{{ $phase['status'] }}">
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
                <div class="sb-phase-empty">No open findings · Safe</div>
              @endforelse
            </div>
          </div>
        </div>

        @if (!$loop->last)
          <div class="sb-flow-arrow {{ $storyboard['phases'][$idx + 1]['count'] > 0 ? 'pulse' : '' }}">
            <svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m9 18 6-6-6-6"/></svg>
          </div>
        @endif
      @endforeach
    </div>
  </div>

  {{-- SECTION 3: INTERACTIVE AI BREACH SIMULATOR --}}
  <div class="sb-sim-panel" id="sim-player">
    <div class="sb-sim-head">
      <div class="sb-sim-title-wrap">
        <span class="sb-sim-badge">
          <svg viewBox="0 0 24 24" width="12" height="12" fill="none" stroke="currentColor" stroke-width="2" style="margin-right: 2px;"><circle cx="12" cy="12" r="10"/><polygon points="10 8 16 12 10 16 10 8"/></svg>
          AI Breach Simulator
        </span>
        <div class="sb-sim-title">Interactive Attack Chain Replayer</div>
        <div class="sb-sim-subtitle">Simulasikan urutan langkah penyerang mengeksploitasi perimeter Anda &amp; tindakan defensif untuk memutusnya</div>
      </div>

      <div class="sb-sim-controls">
        <span class="pill info" id="simStepTracker">Step 1 dari {{ count($storyboard['attackChain']) }}</span>
        <button type="button" class="btn btn-primary btn-sm" id="simPlayBtn" onclick="toggleSimulation()">
          <svg viewBox="0 0 24 24" width="13" height="13" fill="none" stroke="currentColor" stroke-width="2" style="margin-right: 4px;" id="simPlayIcon"><polygon points="5 3 19 12 5 21 5 3"/></svg>
          <span id="simPlayText">Mulai Simulasi</span>
        </button>
        <button type="button" class="btn btn-ghost btn-sm" id="simNextBtn" onclick="nextSimulationStep()">
          <svg viewBox="0 0 24 24" width="13" height="13" fill="none" stroke="currentColor" stroke-width="2" style="margin-right: 4px;"><polygon points="5 4 15 12 5 20 5 4"/><line x1="19" y1="5" x2="19" y2="19"/></svg>
          Langkah Berikutnya
        </button>
        <button type="button" class="btn btn-ghost btn-sm" id="simResetBtn" onclick="resetSimulation()">
          <svg viewBox="0 0 24 24" width="13" height="13" fill="none" stroke="currentColor" stroke-width="2" style="margin-right: 4px;"><polyline points="1 4 1 10 7 10"/><path d="M3.51 15a9 9 0 1 0 2.13-9.36L1 10"/></svg>
          Reset
        </button>
      </div>
    </div>

    <div class="sb-sim-progress-wrap">
      <div class="sb-sim-progress-bar" id="simProgressBar" style="width: {{ count($storyboard['attackChain']) > 0 ? (100 / count($storyboard['attackChain'])) : 100 }}%;"></div>
    </div>

    <div class="sb-sim-track" id="simTrack">
      @foreach ($storyboard['attackChain'] as $idx => $step)
        <div class="sb-sim-step {{ $idx === 0 ? 'active' : '' }}" data-step="{{ $loop->iteration }}" onclick="goToStep({{ $loop->iteration }})">
          <div class="sb-sim-step-meta">
            <span class="sb-sim-step-num">
              <span>STEP 0{{ $step['step'] }}</span>
              <span style="color: var(--fg-subtle);">·</span>
              <span>{{ $step['phase'] }}</span>
            </span>
            <div style="display: flex; align-items: center; gap: 8px;">
              <span class="sb-sim-time mono">{{ $step['time_offset'] }}</span>
              <span class="badge sev-{{ $step['severity'] }}"><span class="dot"></span>{{ ucfirst($step['severity']) }}</span>
            </div>
          </div>

          <div class="sb-sim-target">
            <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="22" y1="12" x2="18" y2="12"/><line x1="6" y1="12" x2="2" y2="12"/><line x1="12" y1="6" x2="12" y2="2"/><line x1="12" y1="22" x2="12" y2="18"/></svg>
            <strong>Target Vector:</strong> <span class="mono">{{ $step['target_vector'] }}</span>
          </div>

          <div class="sb-sim-grid">
            <div class="sb-sim-action attacker">
              <span class="sb-role-tag red">RED TEAM · AKSI PENYERANG</span>
              <p style="margin: 0;">{{ $step['actor_action'] }}</p>
            </div>
            <div class="sb-sim-action defender">
              <span class="sb-role-tag blue">BLUE TEAM · RESPON DEFENSE</span>
              <p style="margin: 0;">{{ $step['defense_action'] }}</p>
            </div>
          </div>
        </div>
      @endforeach
    </div>
  </div>

  {{-- SECTION 4: 1-CLICK AUTO-REMEDIATION SHIELD --}}
  <div class="sb-code-box" id="shield-box">
    <div class="sb-code-head">
      <div>
        <div style="display: flex; align-items: center; gap: 8px; margin-bottom: 4px;">
          <span class="sb-sim-badge" style="background: rgba(16, 185, 129, 0.15); color: #34d399; border-color: rgba(16, 185, 129, 0.3);">
            1-Click Virtual Patching
          </span>
          <span style="font-size: 15px; font-weight: 700; color: var(--fg-default);">Auto-Remediation Shield</span>
        </div>
        <p style="font-size: 12px; color: var(--fg-muted); margin: 0;">
          Script konfigurasi siap pakai yang di-generate otomatis berdasarkan kerentanan riil domain {{ $group->root_domain }}
        </p>
      </div>

      <div class="sb-code-actions">
        <div class="sb-code-tabs">
          <button type="button" class="sb-code-tab active" id="tab-nginx" onclick="switchCodeTab('nginx')">
            <svg viewBox="0 0 24 24" width="13" height="13" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="2" width="20" height="8" rx="2"/><rect x="2" y="14" width="20" height="8" rx="2"/><line x1="6" y1="6" x2="6.01" y2="6"/><line x1="6" y1="18" x2="6.01" y2="18"/></svg>
            Nginx (.conf)
          </button>
          <button type="button" class="sb-code-tab" id="tab-cloudflare" onclick="switchCodeTab('cloudflare')">
            <svg viewBox="0 0 24 24" width="13" height="13" fill="none" stroke="currentColor" stroke-width="2"><path d="M17.5 19H9a7 7 0 1 1 6.71-9h1.79a4.5 4.5 0 1 1 0 9Z"/></svg>
            Cloudflare WAF
          </button>
          <button type="button" class="sb-code-tab" id="tab-bash" onclick="switchCodeTab('bash')">
            <svg viewBox="0 0 24 24" width="13" height="13" fill="none" stroke="currentColor" stroke-width="2"><polyline points="4 17 10 11 4 5"/><line x1="12" y1="19" x2="20" y2="19"/></svg>
            Linux Shield (.sh)
          </button>
        </div>

        <button type="button" class="btn btn-primary btn-sm" id="copyCodeBtn" onclick="copyCurrentCode()">
          <svg viewBox="0 0 24 24" width="13" height="13" fill="none" stroke="currentColor" stroke-width="2" style="margin-right: 4px;"><rect x="9" y="9" width="13" height="13" rx="2" ry="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/></svg>
          <span id="copyCodeText">Salin Script</span>
        </button>
        <button type="button" class="btn btn-ghost btn-sm" onclick="downloadShieldScript()">
          <svg viewBox="0 0 24 24" width="13" height="13" fill="none" stroke="currentColor" stroke-width="2" style="margin-right: 4px;"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
          Unduh .sh
        </button>
      </div>
    </div>

    <div id="panel-nginx" class="sb-code-panel">
      <pre class="sb-code-pre"><code id="code-nginx">{{ $storyboard['remediationScripts']['nginx'] }}</code></pre>
    </div>

    <div id="panel-cloudflare" class="sb-code-panel" style="display: none;">
      <pre class="sb-code-pre"><code id="code-cloudflare">{{ $storyboard['remediationScripts']['cloudflare'] }}</code></pre>
    </div>

    <div id="panel-bash" class="sb-code-panel" style="display: none;">
      <pre class="sb-code-pre"><code id="code-bash">{{ $storyboard['remediationScripts']['bash'] }}</code></pre>
    </div>
  </div>

  {{-- FOOTER EXPLANATION --}}
  <div class="panel" style="margin-top: 24px;">
    <div class="panel-head">
      <span class="panel-title">Tentang Metodologi Papan Cerita Serangan &amp; Kill-Chain</span>
    </div>
    <p style="font-size: 13px; color: var(--fg-muted); line-height: 1.6; margin: 0;">
      Garda OS Attack Storyboard merekonstruksi urutan probabilitas serangan nyata terhadap perimeter <strong>{{ $group->root_domain }}</strong> berdasarkan model Lockheed Martin Cyber Kill Chain dan MITRE ATT&amp;CK. Mulai dari fase pengintaian (Reconnaissance), probing celah tersembunyi, eksploitasi kelemahan, kompromi sistem (Compromise), hingga kalkulasi dampak nyata bisnis (Blast Radius). Solusi 1-Click Shield memberikan remedi instan tanpa perlu menunggu audit manual yang memakan waktu berhari-hari.
    </p>
  </div>

  <script>
    let currentSimStep = 1;
    const totalSimSteps = {{ count($storyboard['attackChain']) }};
    let simTimer = null;
    let isPlaying = false;
    let currentTab = 'nginx';

    function updateSimulationUI() {
      const steps = document.querySelectorAll('.sb-sim-step');
      steps.forEach((el, index) => {
        if (index + 1 === currentSimStep) {
          el.classList.add('active');
          el.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        } else {
          el.classList.remove('active');
        }
      });

      const tracker = document.getElementById('simStepTracker');
      if (tracker) {
        tracker.textContent = `Step ${currentSimStep} dari ${totalSimSteps}`;
      }

      const progressBar = document.getElementById('simProgressBar');
      if (progressBar && totalSimSteps > 0) {
        const pct = Math.round((currentSimStep / totalSimSteps) * 100);
        progressBar.style.width = `${pct}%`;
      }
    }

    function goToStep(step) {
      currentSimStep = Math.max(1, Math.min(step, totalSimSteps));
      updateSimulationUI();
    }

    function nextSimulationStep() {
      if (currentSimStep < totalSimSteps) {
        currentSimStep++;
      } else {
        currentSimStep = 1;
      }
      updateSimulationUI();
    }

    function resetSimulation() {
      pauseSimulation();
      currentSimStep = 1;
      updateSimulationUI();
    }

    function toggleSimulation() {
      if (isPlaying) {
        pauseSimulation();
      } else {
        startAttackSimulation();
      }
    }

    function startAttackSimulation() {
      const el = document.getElementById('sim-player');
      if (el) {
        el.scrollIntoView({ behavior: 'smooth' });
      }

      isPlaying = true;
      const btnText = document.getElementById('simPlayText');
      if (btnText) btnText.textContent = 'Jeda';

      if (simTimer) clearInterval(simTimer);
      simTimer = setInterval(() => {
        if (currentSimStep < totalSimSteps) {
          currentSimStep++;
          updateSimulationUI();
        } else {
          pauseSimulation();
        }
      }, 2600);
    }

    function pauseSimulation() {
      isPlaying = false;
      if (simTimer) {
        clearInterval(simTimer);
        simTimer = null;
      }
      const btnText = document.getElementById('simPlayText');
      if (btnText) btnText.textContent = 'Mulai Simulasi';
    }

    function switchCodeTab(tab) {
      currentTab = tab;
      ['nginx', 'cloudflare', 'bash'].forEach(t => {
        const tabBtn = document.getElementById('tab-' + t);
        const panel = document.getElementById('panel-' + t);
        if (tabBtn) {
          if (t === tab) tabBtn.classList.add('active');
          else tabBtn.classList.remove('active');
        }
        if (panel) {
          panel.style.display = (t === tab) ? 'block' : 'none';
        }
      });
    }

    function copyCurrentCode() {
      const codeEl = document.getElementById('code-' + currentTab);
      if (!codeEl) return;

      const text = codeEl.innerText || codeEl.textContent;
      navigator.clipboard.writeText(text).then(() => {
        const copyBtnText = document.getElementById('copyCodeText');
        if (copyBtnText) {
          const original = copyBtnText.textContent;
          copyBtnText.textContent = 'Tersalin!';
          setTimeout(() => {
            copyBtnText.textContent = original;
          }, 2000);
        }
      }).catch(err => {
        console.error('Clipboard copy failed:', err);
      });
    }

    function downloadShieldScript() {
      const codeEl = document.getElementById('code-bash');
      if (!codeEl) return;

      const text = codeEl.innerText || codeEl.textContent;
      const blob = new Blob([text], { type: 'text/x-shellscript' });
      const url = URL.createObjectURL(blob);
      const a = document.createElement('a');
      a.href = url;
      a.download = 'garda-shield-{{ Str::slug($group->root_domain) }}.sh';
      document.body.appendChild(a);
      a.click();
      document.body.removeChild(a);
      URL.revokeObjectURL(url);
    }
  </script>
@endsection
