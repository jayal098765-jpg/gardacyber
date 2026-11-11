(function () {
  'use strict';

  var sidebar = document.getElementById('consoleSidebar');
  var scrim = document.getElementById('sidebarScrim');
  var openBtn = document.getElementById('sidebarOpen');
  var closeBtn = document.getElementById('sidebarClose');
  var main = document.querySelector('.console-main');
  var mobile = window.matchMedia('(max-width: 1024px)');

  function setOpen(open, restoreFocus) {
    if (!sidebar) return;
    var isOpen = open && mobile.matches;
    sidebar.classList.toggle('open', isOpen);
    document.body.classList.toggle('sidebar-is-open', isOpen);
    sidebar.inert = mobile.matches && !isOpen;
    if (scrim) scrim.classList.toggle('open', isOpen);
    if (openBtn) openBtn.setAttribute('aria-expanded', String(isOpen));
    if (main) main.inert = isOpen;
    if (isOpen && closeBtn) closeBtn.focus();
    if (!isOpen && restoreFocus && openBtn) openBtn.focus();
  }

  if (openBtn) openBtn.addEventListener('click', function () { setOpen(true); });
  if (closeBtn) closeBtn.addEventListener('click', function () { setOpen(false, true); });
  if (scrim) scrim.addEventListener('click', function () { setOpen(false, true); });
  if (sidebar) {
    setOpen(false);
    mobile.addEventListener('change', function () { setOpen(false); });
    sidebar.querySelectorAll('a').forEach(function (link) {
      link.addEventListener('click', function () { setOpen(false); });
    });
    sidebar.querySelectorAll('.nav-link.active').forEach(function (link) {
      link.setAttribute('aria-current', 'page');
    });
    document.addEventListener('keydown', function (event) {
      if (!sidebar.classList.contains('open')) return;
      if (event.key === 'Escape') {
        event.preventDefault();
        setOpen(false, true);
      }
      if (event.key === 'Tab') {
        var items = sidebar.querySelectorAll('a[href], button:not([disabled])');
        var first = items[0];
        var last = items[items.length - 1];
        if (event.shiftKey && document.activeElement === first) {
          event.preventDefault();
          last.focus();
        } else if (!event.shiftKey && document.activeElement === last) {
          event.preventDefault();
          first.focus();
        }
      }
    });
  }

  var burger = document.getElementById('navBurger');
  var navigation = document.getElementById('navMobile');
  if (burger && navigation) {
    function closeNavigation(restoreFocus) {
      navigation.classList.remove('open');
      burger.setAttribute('aria-expanded', 'false');
      if (restoreFocus) burger.focus();
    }
    burger.addEventListener('click', function () {
      var open = navigation.classList.toggle('open');
      burger.setAttribute('aria-expanded', String(open));
    });
    navigation.querySelectorAll('a').forEach(function (link) {
      link.addEventListener('click', function () { closeNavigation(false); });
    });
    document.addEventListener('keydown', function (event) {
      if (event.key === 'Escape' && navigation.classList.contains('open')) closeNavigation(true);
    });
  }

  document.querySelectorAll('.table-wrap').forEach(function (table) {
    if (table.scrollWidth > table.clientWidth && !table.hasAttribute('tabindex')) {
      table.tabIndex = 0;
      table.setAttribute('role', 'region');
      table.setAttribute('aria-label', 'Data table. Scroll horizontally for all columns.');
    }
  });

  /* ==========================================================================
     GardaCyber Frontend Interactions (AI Advisor, Filtering, Triage, Copy)
     ========================================================================== */

  function getCsrfToken() {
    var meta = document.querySelector('meta[name="csrf-token"]');
    return meta ? meta.getAttribute('content') : '';
  }

  function escapeHtml(str) {
    if (str === null || str === undefined) return '';
    return String(str)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#039;');
  }

  function fallbackCopy(text, cb) {
    var ta = document.createElement('textarea');
    ta.value = text;
    ta.style.position = 'fixed';
    ta.style.left = '-9999px';
    ta.style.top = '-9999px';
    document.body.appendChild(ta);
    ta.select();
    try {
      document.execCommand('copy');
      if (cb) cb();
    } catch (err) {}
    document.body.removeChild(ta);
  }

  // Global Copy to Clipboard Handler
  document.addEventListener('click', function (e) {
    var btn = e.target.closest('.copy-btn, [data-copy]');
    if (!btn) return;
    var text = btn.getAttribute('data-copy');
    if (!text) return;

    var label = btn.querySelector('.copy-text, .copy-label');
    var originalText = label ? label.textContent : '';

    function onSuccess() {
      btn.classList.add('copied');
      if (label) label.textContent = 'Copied!';
      setTimeout(function () {
        btn.classList.remove('copied');
        if (label) label.textContent = originalText;
      }, 2000);
    }

    if (navigator.clipboard && navigator.clipboard.writeText) {
      navigator.clipboard.writeText(text).then(onSuccess).catch(function () {
        fallbackCopy(text, onSuccess);
      });
    } else {
      fallbackCopy(text, onSuccess);
    }
  });

  // 1. Finding Show: AI Remediation Analysis
  var analyzeBtn = document.getElementById('analyzeFindingBtn');
  if (analyzeBtn) {
    analyzeBtn.addEventListener('click', function () {
      var url = analyzeBtn.getAttribute('data-url');
      if (!url) return;

      var promptState = document.getElementById('aiPromptState');
      var loadingState = document.getElementById('aiLoadingState');
      var resultsState = document.getElementById('aiResultsState');
      var errorState = document.getElementById('aiErrorState');
      var btnLabel = analyzeBtn.querySelector('.btn-label');

      if (promptState) promptState.classList.add('hidden');
      if (resultsState) resultsState.classList.add('hidden');
      if (errorState) errorState.classList.add('hidden');
      if (loadingState) loadingState.classList.remove('hidden');

      analyzeBtn.disabled = true;
      if (btnLabel) btnLabel.textContent = 'Analyzing...';

      fetch(url, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'Accept': 'application/json',
          'X-CSRF-TOKEN': getCsrfToken()
        },
        body: JSON.stringify({})
      })
      .then(function (res) {
        if (!res.ok) {
          return res.json().catch(function () { return { message: 'HTTP ' + res.status + ': Server error' }; })
            .then(function (err) { throw err; });
        }
        return res.json();
      })
      .then(function (data) {
        if (loadingState) loadingState.classList.add('hidden');
        analyzeBtn.disabled = false;
        if (btnLabel) btnLabel.textContent = 'Re-analyze';

        var analysis = data.analysis || data.explanation || data.summary || data.message || 'Analysis complete.';
        var rootCause = data.root_cause || data.root_cause_details || data.cause || '';
        var remediationSteps = Array.isArray(data.remediation) ? data.remediation : [];
        var commandsText = Array.isArray(data.commands) ? data.commands.join('\n') : (data.commands || '');
        var snippet = data.config_snippet || commandsText || data.snippet || data.bash_snippet || data.nginx_snippet || data.code_snippet || '';
        var verification = data.verification || '';
        var sourceLabel = (data.source === 'ai') ? 'AI Advisor' : 'Local Heuristics';

        var html = '<div class="ai-analysis-block">';
        html += '<div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 6px;">';
        html += '<span style="font-size: 11px; text-transform: uppercase; letter-spacing: 0.08em; color: var(--fg-subtle, #94a3b8);">Advisor Remediation Verdict</span>';
        html += '<span class="pill ' + (data.source === 'ai' ? 'pill-status' : 'ok') + '" style="font-size: 10px;">Source: ' + escapeHtml(sourceLabel) + '</span>';
        html += '</div>';
        html += '<p class="ai-analysis-text">' + escapeHtml(analysis) + '</p>';
        html += '</div>';

        if (rootCause) {
          html += '<div class="ai-root-cause-card">';
          html += '<div class="ai-root-cause-title">';
          html += '<svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 9v4"/><path d="M12 17h.01"/><path d="M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0Z"/></svg>';
          html += 'Root-Cause Investigation';
          html += '</div>';
          html += '<p class="ai-root-cause-content">' + escapeHtml(rootCause) + '</p>';
          html += '</div>';
        }

        if (remediationSteps.length > 0) {
          html += '<div style="margin-bottom: 16px;">';
          html += '<div style="font-size: 11px; text-transform: uppercase; letter-spacing: 0.08em; color: var(--fg-subtle, #94a3b8); margin-bottom: 6px;">Targeted Remediation Steps</div>';
          html += '<ol style="margin: 0; padding-left: 18px; font-size: 13px; color: var(--fg-muted, #94a3b8); line-height: 1.6;">';
          remediationSteps.forEach(function (step) {
            html += '<li style="margin-bottom: 4px; color: #e2e8f0;">' + escapeHtml(step) + '</li>';
          });
          html += '</ol>';
          html += '</div>';
        }

        if (snippet) {
          html += '<div class="ai-snippet-card">';
          html += '<div class="ai-snippet-head">';
          html += '<span>Tailored Hardening Commands (bash / nginx)</span>';
          html += '<button type="button" class="btn btn-ghost btn-sm copy-btn" data-copy="' + escapeHtml(snippet) + '" style="padding: 2px 8px; min-height: 24px; font-size: 11px;">';
          html += '<svg viewBox="0 0 24 24" width="12" height="12" fill="none" stroke="currentColor" stroke-width="2"><rect width="14" height="14" x="8" y="8" rx="2" ry="2"/><path d="M4 16c-1.1 0-2-.9-2-2V4c0-1.1.9-2 2-2h10c1.1 0 2 .9 2 2"/></svg>';
          html += '<span class="copy-text">Copy Snippet</span>';
          html += '</button>';
          html += '</div>';
          html += '<pre class="ai-snippet-code"><code>' + escapeHtml(snippet) + '</code></pre>';
          html += '</div>';
        }

        if (verification) {
          html += '<div style="margin-top: 12px; font-size: 12px; color: var(--fg-subtle);">';
          html += '<strong>Verification Standard:</strong> <span class="mono">' + escapeHtml(verification) + '</span>';
          html += '</div>';
        }

        if (resultsState) {
          resultsState.innerHTML = html;
          resultsState.classList.remove('hidden');
        }
      })
      .catch(function (err) {
        if (loadingState) loadingState.classList.add('hidden');
        analyzeBtn.disabled = false;
        if (btnLabel) btnLabel.textContent = 'Analyze with AI Advisor';

        var msg = (err && (err.message || err.error)) ? (err.message || err.error) : 'Unable to complete AI analysis at this time.';
        if (errorState) {
          errorState.innerHTML = '<div class="test-feedback-box error" style="margin: 0;">' +
            '<strong>AI Advisor Unavailable:</strong> ' + escapeHtml(msg) +
            '<div style="margin-top: 6px; font-size: 12px; color: var(--fg-muted);">' +
            'You can refer to the verified local remediation playbook below, or copy the deterministic hardening snippet directly.' +
            '</div></div>';
          errorState.classList.remove('hidden');
        }
      });
    });
  }

  // 2. Group Show: AI Posture Summary
  var groupSummaryBtn = document.getElementById('generateGroupSummaryBtn');
  if (groupSummaryBtn) {
    groupSummaryBtn.addEventListener('click', function () {
      var url = groupSummaryBtn.getAttribute('data-url');
      if (!url) return;

      var promptState = document.getElementById('aiPosturePrompt');
      var loadingState = document.getElementById('aiPostureLoading');
      var resultsState = document.getElementById('aiPostureResults');
      var errorState = document.getElementById('aiPostureError');
      var btnLabel = groupSummaryBtn.querySelector('.btn-label');

      if (promptState) promptState.classList.add('hidden');
      if (resultsState) resultsState.classList.add('hidden');
      if (errorState) errorState.classList.add('hidden');
      if (loadingState) loadingState.classList.remove('hidden');

      groupSummaryBtn.disabled = true;
      if (btnLabel) btnLabel.textContent = 'Synthesizing...';

      fetch(url, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'Accept': 'application/json',
          'X-CSRF-TOKEN': getCsrfToken()
        },
        body: JSON.stringify({})
      })
      .then(function (res) {
        if (!res.ok) {
          return res.json().catch(function () { return { message: 'HTTP ' + res.status + ': Failed to generate posture summary' }; })
            .then(function (err) { throw err; });
        }
        return res.json();
      })
      .then(function (data) {
        if (loadingState) loadingState.classList.add('hidden');
        groupSummaryBtn.disabled = false;
        if (btnLabel) btnLabel.textContent = 'Update Summary';

        var summary = data.summary || data.executive_summary || data.content || 'Executive assessment compiled.';
        var riskAssessment = data.risk_assessment || '';
        var metrics = data.metrics || data.risk_metrics || null;
        var recommendations = data.action_items || data.recommendations || data.actions || data.top_actions || [];
        var density = data.density !== undefined ? data.density : null;
        var ratings = data.ratings || null;
        var source = data.source || 'ai';

        var html = '<div class="ai-analysis-block">';
        html += '<div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 6px;">';
        html += '<span style="font-size: 11px; text-transform: uppercase; letter-spacing: 0.08em; color: var(--accent, #00d2ff); font-weight: 700;">Executive Defense Summary</span>';
        html += '<span class="pill ' + (source === 'ai' ? 'pill-status' : 'ok') + '" style="font-size: 10px;">Source: ' + (source === 'ai' ? 'AI Cloud/Router' : 'Rule Heuristics') + '</span>';
        html += '</div>';
        html += '<p class="ai-analysis-text">' + escapeHtml(summary) + '</p>';
        html += '</div>';

        if (riskAssessment) {
          html += '<div class="ai-root-cause-card" style="border-left-color: #00d2ff; border-color: rgba(0, 210, 255, 0.25);">';
          html += '<div class="ai-root-cause-title" style="color: #00d2ff;">Risk &amp; Density Analysis</div>';
          html += '<p class="ai-root-cause-content">' + escapeHtml(riskAssessment) + '</p>';
          html += '</div>';
        }

        // Key Risk Metrics
        html += '<div class="posture-summary-grid">';
        if (density !== null) {
          html += '<div class="posture-metric-tile">';
          html += '<div class="posture-metric-val" style="color: #ff824d;">' + Number(density).toFixed(1) + '</div>';
          html += '<div class="posture-metric-lbl">Issues / Host</div>';
          html += '</div>';
        }
        if (ratings && ratings.vuln) {
          html += '<div class="posture-metric-tile">';
          html += '<div class="posture-metric-val" style="color: #00d2ff;">' + escapeHtml(ratings.vuln.grade) + ' (' + escapeHtml(ratings.vuln.score) + ')</div>';
          html += '<div class="posture-metric-lbl">Vuln Rating</div>';
          html += '</div>';
        }
        if (ratings && ratings.incident) {
          html += '<div class="posture-metric-tile">';
          html += '<div class="posture-metric-val" style="color: #10b981;">' + escapeHtml(ratings.incident.grade) + ' (' + escapeHtml(ratings.incident.score) + ')</div>';
          html += '<div class="posture-metric-lbl">Incident Rating</div>';
          html += '</div>';
        }
        if (metrics && typeof metrics === 'object') {
          Object.keys(metrics).forEach(function (k) {
            html += '<div class="posture-metric-tile">';
            html += '<div class="posture-metric-val" style="color: var(--accent, #00d2ff);">' + escapeHtml(metrics[k]) + '</div>';
            html += '<div class="posture-metric-lbl">' + escapeHtml(k.replace(/_/g, ' ')) + '</div>';
            html += '</div>';
          });
        }
        html += '</div>';

        // Top Recommended Actions
        if (Array.isArray(recommendations) && recommendations.length > 0) {
          html += '<div style="margin-top: 14px;">';
          html += '<div style="font-size: 11px; text-transform: uppercase; letter-spacing: 0.08em; color: var(--fg-subtle, #94a3b8); margin-bottom: 8px; font-weight: 700;">Top Recommended Defense Actions</div>';
          html += '<ul class="posture-actions-list">';
          recommendations.forEach(function (act, idx) {
            html += '<li class="posture-action-item">';
            html += '<span class="action-num-badge">' + (idx + 1) + '</span>';
            html += '<span>' + escapeHtml(act) + '</span>';
            html += '</li>';
          });
          html += '</ul>';
          html += '</div>';
        }

        if (resultsState) {
          resultsState.innerHTML = html;
          resultsState.classList.remove('hidden');
        }
      })
      .catch(function (err) {
        if (loadingState) loadingState.classList.add('hidden');
        groupSummaryBtn.disabled = false;
        if (btnLabel) btnLabel.textContent = 'Executive Summary';

        var msg = (err && (err.message || err.error)) ? (err.message || err.error) : 'Unable to generate posture summary.';
        if (errorState) {
          errorState.innerHTML = '<div class="test-feedback-box error" style="margin: 0;">' +
            '<strong>AI Summary Notice:</strong> ' + escapeHtml(msg) +
            '</div>';
          errorState.classList.remove('hidden');
        }
      });
    });
  }

  // 2b. Group Show: Asset Table Filtering & Search
  var filterTabs = document.getElementById('assetFilterTabs');
  var searchInput = document.getElementById('assetSearchFilter');
  var assetRows = document.querySelectorAll('#assetTable .asset-row');
  var noMatchRow = document.querySelector('#assetTable .asset-no-match-row');

  if (filterTabs && assetRows.length > 0) {
    var currentFilter = 'all';

    function applyAssetFilters() {
      var query = searchInput ? searchInput.value.toLowerCase().trim() : '';
      var visibleCount = 0;

      assetRows.forEach(function (row) {
        var isLive = row.getAttribute('data-live') === '1';
        var hasIssues = row.getAttribute('data-issues') === '1';
        var isClean = row.getAttribute('data-clean') === '1';
        var searchText = row.getAttribute('data-search') || '';

        var matchesFilter = true;
        if (currentFilter === 'live') matchesFilter = isLive;
        else if (currentFilter === 'issues') matchesFilter = hasIssues;
        else if (currentFilter === 'clean') matchesFilter = isClean;

        var matchesSearch = !query || searchText.indexOf(query) !== -1;

        if (matchesFilter && matchesSearch) {
          row.classList.remove('hidden');
          visibleCount++;
        } else {
          row.classList.add('hidden');
        }
      });

      if (noMatchRow) {
        noMatchRow.classList.toggle('hidden', visibleCount > 0);
      }
    }

    filterTabs.addEventListener('click', function (e) {
      var btn = e.target.closest('.filter-pill');
      if (!btn) return;

      filterTabs.querySelectorAll('.filter-pill').forEach(function (b) {
        b.classList.remove('active');
        b.setAttribute('aria-selected', 'false');
      });

      btn.classList.add('active');
      btn.setAttribute('aria-selected', 'true');
      currentFilter = btn.getAttribute('data-filter') || 'all';

      applyAssetFilters();
    });

    if (searchInput) {
      searchInput.addEventListener('input', applyAssetFilters);
    }
  }

  // 3. AI Settings: Provider Selector, Key Toggle, & Connection Test
  var providerGrid = document.getElementById('providerGrid');
  if (providerGrid) {
    var cards = providerGrid.querySelectorAll('.provider-card');
    var baseUrlInput = document.getElementById('ai_base_url');
    var modelInput = document.getElementById('ai_model');
    var localNotice = document.getElementById('localNoticeBanner');
    var activeBadge = document.getElementById('activeProviderBadge');

    cards.forEach(function (card) {
      card.addEventListener('click', function () {
        cards.forEach(function (c) { c.classList.remove('active'); });
        card.classList.add('active');

        var radio = card.querySelector('input[type="radio"]');
        if (radio) radio.checked = true;

        var prov = card.getAttribute('data-provider');
        var defUrl = card.getAttribute('data-default-url') || '';
        var defModel = card.getAttribute('data-default-model') || '';

        if (prov === 'local') {
          if (localNotice) localNotice.classList.remove('hidden');
          if (activeBadge) {
            activeBadge.textContent = 'Local Engine (Default)';
            activeBadge.className = 'pill ok';
          }
        } else {
          if (localNotice) localNotice.classList.add('hidden');
          if (baseUrlInput && (!baseUrlInput.value || baseUrlInput.value.indexOf('http') === 0)) {
            baseUrlInput.value = defUrl;
          }
          if (modelInput && !modelInput.value) {
            modelInput.value = defModel;
          }
          if (activeBadge) {
            activeBadge.textContent = card.querySelector('.provider-name').textContent + ' Active';
            activeBadge.className = 'pill run';
          }
        }
      });
    });

    // Password Show/Hide Toggle
    var toggleKeyBtn = document.getElementById('toggleApiKeyBtn');
    var apiKeyInput = document.getElementById('ai_api_key');
    if (toggleKeyBtn && apiKeyInput) {
      toggleKeyBtn.addEventListener('click', function () {
        if (apiKeyInput.type === 'password') {
          apiKeyInput.type = 'text';
          toggleKeyBtn.textContent = 'Hide';
        } else {
          apiKeyInput.type = 'password';
          toggleKeyBtn.textContent = 'Show';
        }
      });
    }

    // Test Connection Button
    var testBtn = document.getElementById('testAiConnectionBtn');
    var feedbackBox = document.getElementById('testFeedbackBox');
    if (testBtn) {
      testBtn.addEventListener('click', function () {
        var url = testBtn.getAttribute('data-url');
        if (!url) return;

        var selectedProvider = providerGrid.querySelector('input[name="ai_provider"]:checked, input[name="provider"]:checked');
        var providerVal = selectedProvider ? selectedProvider.value : 'local';
        var baseUrlVal = baseUrlInput ? baseUrlInput.value : '';
        var modelVal = modelInput ? modelInput.value : '';
        var apiKeyVal = apiKeyInput ? apiKeyInput.value : '';

        var spinnerIcon = testBtn.querySelector('.btn-spinner-icon');
        var regularIcon = testBtn.querySelector('.btn-regular-icon');
        var btnText = testBtn.querySelector('.btn-text');

        testBtn.disabled = true;
        if (spinnerIcon) spinnerIcon.classList.remove('hidden');
        if (regularIcon) regularIcon.classList.add('hidden');
        if (btnText) btnText.textContent = 'Testing...';
        if (feedbackBox) feedbackBox.classList.add('hidden');

        fetch(url, {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
            'X-CSRF-TOKEN': getCsrfToken()
          },
          body: JSON.stringify({
            ai_provider: providerVal,
            provider: providerVal,
            ai_base_url: baseUrlVal,
            base_url: baseUrlVal,
            ai_model: modelVal,
            model: modelVal,
            ai_api_key: apiKeyVal,
            api_key: apiKeyVal
          })
        })
        .then(function (res) {
          return res.json().then(function (json) {
            return { ok: res.ok, status: res.status, data: json };
          }).catch(function () {
            return { ok: res.ok, status: res.status, data: {} };
          });
        })
        .then(function (result) {
          testBtn.disabled = false;
          if (spinnerIcon) spinnerIcon.classList.add('hidden');
          if (regularIcon) regularIcon.classList.remove('hidden');
          if (btnText) btnText.textContent = 'Test Connection';

          if (feedbackBox) {
            feedbackBox.classList.remove('hidden');
            if (result.ok) {
              feedbackBox.className = 'test-feedback-box success';
              var latency = (result.data && result.data.latency_ms) ? ' (latency: ' + result.data.latency_ms + 'ms)' : '';
              feedbackBox.innerHTML = '<strong>✓ Provider reachable' + latency + '</strong>: Endpoint verified and ready for triage analysis.';
            } else {
              feedbackBox.className = 'test-feedback-box error';
              var errDetail = (result.data && (result.data.message || result.data.error)) ? (result.data.message || result.data.error) : ('HTTP ' + result.status + ' response');
              feedbackBox.innerHTML = '<strong>Connection test failed:</strong> ' + escapeHtml(errDetail);
            }
          }
        })
        .catch(function (err) {
          testBtn.disabled = false;
          if (spinnerIcon) spinnerIcon.classList.add('hidden');
          if (regularIcon) regularIcon.classList.remove('hidden');
          if (btnText) btnText.textContent = 'Test Connection';

          if (feedbackBox) {
            feedbackBox.classList.remove('hidden');
            feedbackBox.className = 'test-feedback-box error';
            feedbackBox.innerHTML = '<strong>Connection test failed:</strong> ' + escapeHtml(err.message || 'Network unreachable.');
          }
        });
      });
    }
  }

  // 4. Profile: Telegram Test Alert
  var tgTestBtn = document.getElementById('sendTelegramTestBtn');
  var tgFeedback = document.getElementById('telegramFeedbackBox');
  if (tgTestBtn) {
    tgTestBtn.addEventListener('click', function () {
      var url = tgTestBtn.getAttribute('data-url');
      if (!url) return;

      var spinner = tgTestBtn.querySelector('.btn-spinner-icon');
      var icon = tgTestBtn.querySelector('.btn-regular-icon');
      var text = tgTestBtn.querySelector('.btn-text');

      var tokenInput = document.getElementById('telegram_bot_token');
      var chatInput = document.getElementById('telegram_chat_id');
      var webhookInput = document.getElementById('telegram_webhook_url');

      var payload = {
        bot_token: tokenInput ? tokenInput.value : '',
        telegram_bot_token: tokenInput ? tokenInput.value : '',
        chat_id: chatInput ? chatInput.value : '',
        telegram_chat_id: chatInput ? chatInput.value : '',
        webhook_url: webhookInput ? webhookInput.value : '',
        telegram_webhook_url: webhookInput ? webhookInput.value : '',
        signal: 'Critical Signal Verification',
        asset: 'perimeter.gardacyber.test'
      };

      tgTestBtn.disabled = true;
      if (spinner) spinner.classList.remove('hidden');
      if (icon) icon.classList.add('hidden');
      if (text) text.textContent = 'Sending...';
      if (tgFeedback) tgFeedback.classList.add('hidden');

      fetch(url, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'Accept': 'application/json',
          'X-CSRF-TOKEN': getCsrfToken()
        },
        body: JSON.stringify(payload)
      })
      .then(function (res) {
        return res.json().then(function (json) {
          return { ok: res.ok, status: res.status, data: json };
        }).catch(function () {
          return { ok: res.ok, status: res.status, data: {} };
        });
      })
      .then(function (result) {
        tgTestBtn.disabled = false;
        if (spinner) spinner.classList.add('hidden');
        if (icon) icon.classList.remove('hidden');
        if (text) text.textContent = 'Send Test Alert';

        if (tgFeedback) {
          tgFeedback.classList.remove('hidden');
          if (result.ok) {
            tgFeedback.className = 'test-feedback-box success';
            var succMsg = (result.data && result.data.message) ? result.data.message : 'Test alert dispatched to Telegram successfully. Check your chat @GardaCyberBot for the simulated security notification.';
            var formatted = (result.data && result.data.formatted_text) ? ('<pre style="margin: 8px 0 0; padding: 10px; background: #060c18; border-radius: 6px; font-size: 11.5px; white-space: pre-wrap; color: #38bdf8; font-family: var(--font-mono);">' + escapeHtml(result.data.formatted_text) + '</pre>') : '';
            tgFeedback.innerHTML = '<div><strong>✓ ' + escapeHtml(succMsg) + '</strong></div>' + formatted;
          } else {
            tgFeedback.className = 'test-feedback-box error';
            var errMsg = (result.data && (result.data.message || result.data.error)) ? (result.data.message || result.data.error) : 'Failed to dispatch alert. Check bot connection.';
            var formatted = (result.data && result.data.formatted_text) ? ('<div style="margin-top: 8px; font-size: 11.5px; color: var(--fg-muted);">Simulated Telegram Payload:</div><pre style="margin: 4px 0 0; padding: 10px; background: #060c18; border-radius: 6px; font-size: 11.5px; white-space: pre-wrap; color: #ff824d; font-family: var(--font-mono);">' + escapeHtml(result.data.formatted_text) + '</pre>') : '';
            tgFeedback.innerHTML = '<div><strong>Telegram Dispatch Notice:</strong> ' + escapeHtml(errMsg) + '</div>' + formatted;
          }
        }
      })
      .catch(function (err) {
        tgTestBtn.disabled = false;
        if (spinner) spinner.classList.add('hidden');
        if (icon) icon.classList.remove('hidden');
        if (text) text.textContent = 'Send Test Alert';

        if (tgFeedback) {
          tgFeedback.classList.remove('hidden');
          tgFeedback.className = 'test-feedback-box error';
          tgFeedback.innerHTML = '<strong>Telegram Dispatch Error:</strong> ' + escapeHtml(err.message || 'Network error.');
        }
      });
    });
  }
})();
