@extends('layouts.app')

@section('title', 'AI Advisor & Triage Engine')

@section('content')
  <div class="page-head row" style="align-items: flex-start;">
    <div>
      <div style="display: flex; align-items: center; gap: 8px; margin-bottom: 8px;">
        <span class="badge ai-badge"><span class="signal-dot pulse"></span>Intelligence Configuration</span>
        <span class="pill ok" id="activeProviderBadge">Local Engine (Default)</span>
      </div>
      <h1>AI Advisor &amp; Triage Engine</h1>
      <p style="margin-top: 6px;">
        Configure external model providers for remediation guidance and posture summaries, or operate fully offline with deterministic local rules.
      </p>
    </div>

    <div class="page-actions">
      <a class="btn btn-ghost btn-sm" href="{{ route('issues.index') }}">Review Findings</a>
    </div>
  </div>

  <div class="dash-grid" style="grid-template-columns: 1.4fr 0.6fr; gap: 24px; align-items: start;">
    <div>
      {{-- MAIN CONFIGURATION FORM --}}
      <div class="panel" style="margin-bottom: 24px;">
        <div class="panel-head" style="margin-bottom: 20px;">
          <div>
            <span class="panel-title" style="font-size: 15px; font-weight: 700;">Provider Architecture</span>
            <div style="font-size: 12px; color: var(--fg-subtle); margin-top: 2px;">
              Select inference runtime for findings analysis and group synthesis
            </div>
          </div>
          <span class="pill" style="font-size: 11px;">Encrypted at Rest</span>
        </div>

        <form id="aiSettingsForm" method="POST" action="{{ route('ai-settings.store') }}">
          @csrf

          @php
            $currentProvider = old('ai_provider', $user->ai_provider ?? 'local');
            if (!$currentProvider) { $currentProvider = 'local'; }
          @endphp

          {{-- PROVIDER SELECTION TILES --}}
          <div style="margin-bottom: 22px;">
            <label class="field-label" style="display: block; font-size: 12px; font-weight: 700; color: var(--fg-default); margin-bottom: 10px;">
              SELECT REASONING PROVIDER
            </label>

            <div class="ai-provider-grid" id="providerGrid">
              {{-- 1. Local Rule Engine --}}
              <label class="provider-card {{ $currentProvider === 'local' ? 'active' : '' }}" data-provider="local">
                <input type="radio" name="ai_provider" value="local" class="sr-only" {{ $currentProvider === 'local' ? 'checked' : '' }} />
                <div class="provider-card-head">
                  <span class="provider-icon">
                    <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2"><rect width="18" height="18" x="3" y="3" rx="2"/><path d="m9 9 3 3-3 3"/><path d="M13 15h2"/></svg>
                  </span>
                  <span class="badge" style="background: rgba(16, 185, 129, 0.12); color: #10b981; border-color: rgba(16, 185, 129, 0.3);">Offline Default</span>
                </div>
                <div class="provider-name">Local Rule Engine</div>
                <div class="provider-desc">Deterministic security heuristics. Zero external data transmission. 100% air-gapped.</div>
              </label>

              {{-- 2. OpenAI --}}
              <label class="provider-card {{ $currentProvider === 'openai' ? 'active' : '' }}" data-provider="openai" data-default-url="https://api.openai.com/v1" data-default-model="gpt-4o-mini">
                <input type="radio" name="ai_provider" value="openai" class="sr-only" {{ $currentProvider === 'openai' ? 'checked' : '' }} />
                <div class="provider-card-head">
                  <span class="provider-icon">
                    <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 2a10 10 0 0 0-7.07 17.07l1.41-1.41A8 8 0 1 1 12 20a7.94 7.94 0 0 1-5.66-2.34l-1.41 1.41A10 10 0 1 0 12 2Z"/></svg>
                  </span>
                  <span class="badge" style="background: var(--bg-surface-3); color: var(--fg-muted);">Cloud API</span>
                </div>
                <div class="provider-name">OpenAI</div>
                <div class="provider-desc">Direct endpoint via GPT-4o, GPT-4o-mini, or o3-mini models.</div>
              </label>

              {{-- 3. Anthropic --}}
              <label class="provider-card {{ $currentProvider === 'anthropic' ? 'active' : '' }}" data-provider="anthropic" data-default-url="https://api.anthropic.com/v1" data-default-model="claude-3-5-sonnet-latest">
                <input type="radio" name="ai_provider" value="anthropic" class="sr-only" {{ $currentProvider === 'anthropic' ? 'checked' : '' }} />
                <div class="provider-card-head">
                  <span class="provider-icon">
                    <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2"><path d="m3 3 7.5 18 3.5-8 8-3.5Z"/></svg>
                  </span>
                  <span class="badge" style="background: var(--bg-surface-3); color: var(--fg-muted);">Cloud API</span>
                </div>
                <div class="provider-name">Anthropic</div>
                <div class="provider-desc">Claude 3.5 Sonnet / Haiku for security remediation logic.</div>
              </label>

              {{-- 4. OpenRouter --}}
              <label class="provider-card {{ $currentProvider === 'openrouter' ? 'active' : '' }}" data-provider="openrouter" data-default-url="https://openrouter.ai/api/v1" data-default-model="anthropic/claude-3.5-sonnet">
                <input type="radio" name="ai_provider" value="openrouter" class="sr-only" {{ $currentProvider === 'openrouter' ? 'checked' : '' }} />
                <div class="provider-card-head">
                  <span class="provider-icon">
                    <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="3"/><circle cx="19" cy="5" r="2"/><circle cx="5" cy="19" r="2"/><path d="m7 17 3-3M14 10l3-3"/></svg>
                  </span>
                  <span class="badge" style="background: var(--bg-surface-3); color: var(--fg-muted);">Multi-Model</span>
                </div>
                <div class="provider-name">OpenRouter</div>
                <div class="provider-desc">Unified routing to DeepSeek, Llama 3, Claude, and Gemini with single key.</div>
              </label>

              {{-- 5. Other / AI Router --}}
              <label class="provider-card {{ in_array($currentProvider, ['other', '9router', 'litellm', 'ollama'], true) ? 'active' : '' }}" data-provider="other" data-default-url="http://localhost:11434/v1" data-default-model="deepseek-r1:latest">
                <input type="radio" name="ai_provider" value="other" class="sr-only" {{ in_array($currentProvider, ['other', '9router', 'litellm', 'ollama'], true) ? 'checked' : '' }} />
                <div class="provider-card-head">
                  <span class="provider-icon">
                    <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2"><rect width="20" height="8" x="2" y="2" rx="2" ry="2"/><rect width="20" height="8" x="2" y="14" rx="2" ry="2"/><line x1="6" y1="6" x2="6.01" y2="6"/><line x1="6" y1="18" x2="6.01" y2="18"/></svg>
                  </span>
                  <span class="badge" style="background: rgba(0, 82, 204, 0.12); color: #00d2ff; border-color: rgba(0, 210, 255, 0.3);">Self-Hosted / Router</span>
                </div>
                <div class="provider-name">Other / AI Router</div>
                <div class="provider-desc">Connect 9router, LiteLLM, vLLM, Ollama, or local inference cluster.</div>
              </label>
            </div>
          </div>

          {{-- CREDENTIALS & ENDPOINT FIELDS --}}
          <div class="provider-fields-container" id="providerFieldsContainer">
            <div class="field" id="baseUrlGroup">
              <label for="ai_base_url">Base URL</label>
              <input id="ai_base_url" class="input mono" type="url" name="ai_base_url" placeholder="https://api.openai.com/v1" value="{{ old('ai_base_url', $user->ai_base_url ?? '') }}" />
              <div class="field-hint" style="font-size: 11.5px; color: var(--fg-subtle); margin-top: 4px;">
                Leave empty to use provider default, or supply custom proxy / 9router URL.
              </div>
            </div>

            <div class="field" id="modelGroup">
              <label for="ai_model">Model Identifier</label>
              <input id="ai_model" class="input mono" type="text" name="ai_model" placeholder="gpt-4o-mini" value="{{ old('ai_model', $user->ai_model ?? '') }}" />
              <div class="field-hint" style="font-size: 11.5px; color: var(--fg-subtle); margin-top: 4px;">
                E.g. <span class="mono">claude-3-5-sonnet-latest</span>, <span class="mono">gpt-4o-mini</span>, or <span class="mono">deepseek-r1:latest</span>.
              </div>
            </div>

            <div class="field" id="apiKeyGroup">
              <label for="ai_api_key">API Key / Secret Token</label>
              <div style="position: relative; display: flex; align-items: center;">
                <input id="ai_api_key" class="input mono" type="password" name="ai_api_key" placeholder="sk-..." autocomplete="off" style="padding-right: 70px;" />
                <button type="button" class="btn btn-ghost btn-sm" id="toggleApiKeyBtn" style="position: absolute; right: 6px; height: 32px; padding: 0 10px; font-size: 11.5px;" aria-label="Toggle password visibility">
                  Show
                </button>
              </div>
              <div class="field-hint" style="font-size: 11.5px; color: var(--fg-subtle); margin-top: 4px;">
                Encrypted with AES-256 before persisting. Never transmitted to client-side scripts after save.
              </div>
            </div>
          </div>

          {{-- LOCAL NOTIFICATION BANNER --}}
          <div class="ai-local-notice {{ $currentProvider !== 'local' ? 'hidden' : '' }}" id="localNoticeBanner" style="background: rgba(16, 185, 129, 0.08); border: 1px solid rgba(16, 185, 129, 0.25); border-radius: 10px; padding: 14px 16px; margin-bottom: 20px;">
            <div style="display: flex; gap: 10px; align-items: flex-start;">
              <span style="color: #10b981; margin-top: 2px;">
                <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
              </span>
              <div>
                <div style="font-size: 13.5px; font-weight: 700; color: var(--fg-default);">Local Rule Engine Active</div>
                <div style="font-size: 12.5px; color: var(--fg-muted); line-height: 1.5; margin-top: 2px;">
                  All findings triage runs locally via deterministic signature catalogs. No API keys required; no requests exit your network perimeter.
                </div>
              </div>
            </div>
          </div>

          {{-- CONNECTION TEST RESULT BOX --}}
          <div class="test-feedback-box hidden" id="testFeedbackBox" style="margin-bottom: 20px;" role="status"></div>

          {{-- ACTIONS --}}
          <div style="display: flex; gap: 12px; align-items: center; flex-wrap: wrap; pt: 10px;">
            <button type="button" class="btn btn-ghost" id="testAiConnectionBtn" data-url="{{ Route::has('ai-settings.test') ? route('ai-settings.test') : url('/ai-settings/test') }}">
              <svg class="btn-spinner-icon hidden" viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 12a9 9 0 1 1-6.219-8.56"/></svg>
              <svg class="btn-regular-icon" viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 12h-4l-3 9L9 3l-3 9H2"/></svg>
              <span class="btn-text">Test Connection</span>
            </button>

            <button type="submit" class="btn btn-primary">
              Save Configuration
            </button>
          </div>
        </form>
      </div>
    </div>

    {{-- RIGHT SIDEBAR: PRIVACY & ARCHITECTURE --}}
    <div>
      {{-- CONFIDENTIALITY GUARANTEE --}}
      <div class="panel" style="margin-bottom: 20px; border-left: 3px solid var(--accent, #00d2ff);">
        <div class="panel-head">
          <span class="panel-title" style="font-size: 14px; font-weight: 700; display: flex; align-items: center; gap: 6px;">
            <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2"><rect width="18" height="11" x="3" y="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
            Confidentiality Guarantee
          </span>
        </div>
        <p style="font-size: 13px; color: var(--fg-muted); line-height: 1.6; margin: 0 0 12px;">
          API keys are encrypted using <strong style="color: var(--fg-default);">AES-256-GCM</strong>. Your raw signals, hostnames, and asset inventories are <strong>never</strong> logged or shared for public model retraining.
        </p>
        <div style="font-size: 12px; color: var(--fg-subtle); display: flex; flex-direction: column; gap: 8px;">
          <div style="display: flex; gap: 8px; align-items: center;">
            <span style="color: #10b981;">✓</span> Zero data retention on proxy routes
          </div>
          <div style="display: flex; gap: 8px; align-items: center;">
            <span style="color: #10b981;">✓</span> Fallback to local rule engine if provider is offline
          </div>
          <div style="display: flex; gap: 8px; align-items: center;">
            <span style="color: #10b981;">✓</span> Strict token limits to prevent quota overruns
          </div>
        </div>
      </div>

      {{-- COMPARISON METRICS --}}
      <div class="panel">
        <div class="panel-head">
          <span class="panel-title" style="font-size: 14px; font-weight: 700;">Triage Engine Matrix</span>
        </div>
        <dl class="triage-facts" style="margin: 0;">
          <div style="padding: 12px 0;">
            <dt style="font-size: 12px; font-weight: 600;">Offline Safety</dt>
            <dd style="font-size: 12px; color: var(--fg-muted);">100% with Local Engine. Signal analysis stays air-gapped.</dd>
          </div>
          <div style="padding: 12px 0;">
            <dt style="font-size: 12px; font-weight: 600;">Custom Fix Snippets</dt>
            <dd style="font-size: 12px; color: var(--fg-muted);">Generates tailored nginx, apache, and bash commands matching observed server headers.</dd>
          </div>
          <div style="padding: 12px 0;">
            <dt style="font-size: 12px; font-weight: 600;">Router Support</dt>
            <dd style="font-size: 12px; color: var(--fg-muted);">Full compatibility with 9router, LiteLLM, and Ollama local daemon endpoints.</dd>
          </div>
        </dl>
      </div>
    </div>
  </div>
@endsection