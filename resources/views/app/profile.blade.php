@extends('layouts.app')

@section('title', 'Profile & Settings')

@section('content')
  <div class="page-head row" style="align-items: flex-start;">
    <div>
      <div style="display: flex; align-items: center; gap: 8px; margin-bottom: 8px;">
        <span class="badge" style="background: rgba(0, 82, 204, 0.12); color: #00d2ff; border-color: rgba(0, 210, 255, 0.3);">
          <span class="signal-dot"></span>Account Control
        </span>
      </div>
      <h1>Profile &amp; Settings</h1>
      <p style="margin-top: 6px;">Manage your operator identity, credentials, and real-time security alert channels.</p>
    </div>
  </div>

  <div class="profile-grid" style="margin-bottom: 24px;">
    {{-- PROFILE IDENTITY CARD --}}
    <div class="scan-card">
      <div class="panel-head" style="margin-bottom: 16px;">
        <span class="panel-title" style="font-size: 15px; font-weight: 700;">Operator Identity</span>
        <span class="pill" style="font-size: 11px;">Active</span>
      </div>

      <form method="POST" action="{{ route('profile.update') }}">
        @csrf
        @method('PATCH')
        <div class="field">
          <label for="display_name">Display name</label>
          <input id="display_name" class="input" type="text" name="display_name" value="{{ $user->display_name }}" required />
        </div>
        <div class="field">
          <label for="workspace_name">Workspace name</label>
          <input id="workspace_name" class="input" type="text" name="workspace_name" value="{{ $user->workspace_name }}" required />
        </div>
        <div class="field">
          <label for="email">Email address</label>
          <input id="email" class="input mono" type="email" name="email" value="{{ $user->email }}" required />
        </div>
        <button class="btn btn-primary btn-sm" type="submit">Save Profile</button>
      </form>
    </div>

    {{-- PASSWORD UPDATE CARD --}}
    <div class="scan-card">
      <div class="panel-head" style="margin-bottom: 16px;">
        <span class="panel-title" style="font-size: 15px; font-weight: 700;">Password &amp; Security</span>
        <span class="pill" style="font-size: 11px;">Credentials</span>
      </div>

      <form method="POST" action="{{ route('profile.password') }}">
        @csrf
        @method('PATCH')
        <div class="field">
          <label for="current_password">Current password</label>
          <input id="current_password" class="input" type="password" name="current_password" required autocomplete="current-password" />
        </div>
        <div class="field">
          <label for="password">New password</label>
          <input id="password" class="input" type="password" name="password" required minlength="8" autocomplete="new-password" />
        </div>
        <div class="field">
          <label for="password_confirmation">Confirm new password</label>
          <input id="password_confirmation" class="input" type="password" name="password_confirmation" required autocomplete="new-password" />
        </div>
        <button class="btn btn-primary btn-sm" type="submit">Update Password</button>
      </form>
    </div>
  </div>

  {{-- TELEGRAM & WEBHOOK NOTIFICATION CARD --}}
  <div class="panel notification-card" style="max-width: 100%; margin-top: 10px;">
    <div class="panel-head" style="justify-content: space-between; align-items: flex-start; flex-wrap: wrap; gap: 12px; margin-bottom: 20px;">
      <div>
        <div style="display: flex; align-items: center; gap: 8px;">
          <span class="panel-title" style="font-size: 16px; font-weight: 700;">Telegram &amp; Webhook Alerts</span>
          <span class="badge" style="background: rgba(16, 185, 129, 0.12); color: #10b981; border-color: rgba(16, 185, 129, 0.3);">
            <span class="dot" style="background: #10b981;"></span>Real-Time Dispatch
          </span>
        </div>
        <div style="font-size: 12.5px; color: var(--fg-subtle); margin-top: 4px;">
          Receive critical vulnerability alerts and incident signals immediately on mobile and external security feeds.
        </div>
      </div>

      <div style="display: flex; gap: 10px; align-items: center; flex-wrap: wrap;">
        <a href="https://t.me/GardaCyberBot" target="_blank" rel="noopener noreferrer" class="btn btn-ghost btn-sm" style="color: #00d2ff; border-color: rgba(0, 210, 255, 0.3);">
          <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2"><path d="m22 2-7 20-4-9-9-4Z"/><path d="M22 2 11 13"/></svg>
          Open @GardaCyberBot
        </a>

        <button type="button" class="btn btn-primary btn-sm" id="sendTelegramTestBtn" data-url="{{ Route::has('profile.telegram-test') ? route('profile.telegram-test') : url('/profile/telegram-test') }}">
          <svg class="btn-spinner-icon hidden" viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 12a9 9 0 1 1-6.219-8.56"/></svg>
          <svg class="btn-regular-icon" viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2"><line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/></svg>
          <span class="btn-text">Send Test Alert</span>
        </button>
      </div>
    </div>

    {{-- FEEDBACK BANNER --}}
    <div class="test-feedback-box hidden" id="telegramFeedbackBox" style="margin-bottom: 20px;" role="status"></div>

    <div class="dash-grid" style="grid-template-columns: 240px 1fr; gap: 24px; align-items: start;">
      {{-- QR CODE & QUICK CONNECT --}}
      <div class="telegram-qr-card" style="background: var(--bg-surface-2, #0d1527); border: 1px solid var(--border-subtle, rgba(255,255,255,0.08)); border-radius: 12px; padding: 20px; text-align: center;">
        <div class="qr-frame" style="background: #060c18; padding: 12px; border-radius: 10px; display: inline-block; border: 1px solid rgba(0, 210, 255, 0.25); box-shadow: 0 4px 16px rgba(0,0,0,0.4);">
          {{-- Sample Crisp Vector QR Code --}}
          <svg width="150" height="150" viewBox="0 0 150 150" fill="none" xmlns="http://www.w3.org/2000/svg" role="img" aria-label="Telegram Bot Pairing QR Code">
            <rect width="150" height="150" fill="#060C18" rx="6"/>
            {{-- Finder Pattern Top-Left --}}
            <rect x="15" y="15" width="34" height="34" rx="4" fill="#00D2FF"/>
            <rect x="21" y="21" width="22" height="22" rx="2" fill="#060C18"/>
            <rect x="26" y="26" width="12" height="12" rx="1" fill="#00D2FF"/>
            {{-- Finder Pattern Top-Right --}}
            <rect x="101" y="15" width="34" height="34" rx="4" fill="#00D2FF"/>
            <rect x="107" y="21" width="22" height="22" rx="2" fill="#060C18"/>
            <rect x="112" y="26" width="12" height="12" rx="1" fill="#00D2FF"/>
            {{-- Finder Pattern Bottom-Left --}}
            <rect x="15" y="101" width="34" height="34" rx="4" fill="#00D2FF"/>
            <rect x="21" y="107" width="22" height="22" rx="2" fill="#060C18"/>
            <rect x="26" y="112" width="12" height="12" rx="1" fill="#00D2FF"/>
            {{-- Data modules grid --}}
            <rect x="57" y="18" width="6" height="6" fill="#FFFFFF"/>
            <rect x="69" y="18" width="6" height="6" fill="#00D2FF"/>
            <rect x="81" y="18" width="6" height="6" fill="#FFFFFF"/>
            <rect x="57" y="30" width="6" height="6" fill="#00D2FF"/>
            <rect x="69" y="30" width="6" height="6" fill="#FFFFFF"/>
            <rect x="81" y="30" width="6" height="6" fill="#00D2FF"/>
            <rect x="57" y="42" width="6" height="6" fill="#FFFFFF"/>
            <rect x="81" y="42" width="6" height="6" fill="#FFFFFF"/>
            {{-- Timing patterns --}}
            <rect x="18" y="57" width="6" height="6" fill="#00D2FF"/>
            <rect x="30" y="57" width="6" height="6" fill="#FFFFFF"/>
            <rect x="42" y="57" width="6" height="6" fill="#00D2FF"/>
            <rect x="54" y="57" width="6" height="6" fill="#FFFFFF"/>
            <rect x="66" y="57" width="6" height="6" fill="#00D2FF"/>
            <rect x="78" y="57" width="6" height="6" fill="#FFFFFF"/>
            <rect x="90" y="57" width="6" height="6" fill="#00D2FF"/>
            <rect x="102" y="57" width="6" height="6" fill="#FFFFFF"/>
            <rect x="114" y="57" width="6" height="6" fill="#00D2FF"/>
            <rect x="126" y="57" width="6" height="6" fill="#FFFFFF"/>
            {{-- Lower data blocks --}}
            <rect x="57" y="69" width="6" height="6" fill="#FFFFFF"/>
            <rect x="69" y="69" width="6" height="6" fill="#00D2FF"/>
            <rect x="81" y="69" width="6" height="6" fill="#FFFFFF"/>
            <rect x="93" y="69" width="6" height="6" fill="#00D2FF"/>
            <rect x="105" y="69" width="6" height="6" fill="#FFFFFF"/>
            <rect x="117" y="69" width="6" height="6" fill="#00D2FF"/>
            <rect x="57" y="81" width="6" height="6" fill="#00D2FF"/>
            <rect x="69" y="81" width="6" height="6" fill="#FFFFFF"/>
            <rect x="93" y="81" width="6" height="6" fill="#FFFFFF"/>
            <rect x="105" y="81" width="6" height="6" fill="#00D2FF"/>
            <rect x="117" y="81" width="6" height="6" fill="#FFFFFF"/>
            <rect x="57" y="93" width="6" height="6" fill="#FFFFFF"/>
            <rect x="81" y="93" width="6" height="6" fill="#00D2FF"/>
            <rect x="93" y="93" width="6" height="6" fill="#00D2FF"/>
            <rect x="117" y="93" width="6" height="6" fill="#FFFFFF"/>
            <rect x="57" y="105" width="6" height="6" fill="#00D2FF"/>
            <rect x="69" y="105" width="6" height="6" fill="#FFFFFF"/>
            <rect x="81" y="105" width="6" height="6" fill="#FFFFFF"/>
            <rect x="93" y="105" width="6" height="6" fill="#00D2FF"/>
            <rect x="105" y="105" width="6" height="6" fill="#FFFFFF"/>
            <rect x="117" y="105" width="6" height="6" fill="#00D2FF"/>
            <rect x="57" y="117" width="6" height="6" fill="#FFFFFF"/>
            <rect x="69" y="117" width="6" height="6" fill="#00D2FF"/>
            <rect x="81" y="117" width="6" height="6" fill="#FFFFFF"/>
            <rect x="93" y="117" width="6" height="6" fill="#FFFFFF"/>
            <rect x="105" y="117" width="6" height="6" fill="#00D2FF"/>
            <rect x="117" y="117" width="6" height="6" fill="#FFFFFF"/>
            <rect x="57" y="129" width="6" height="6" fill="#00D2FF"/>
            <rect x="81" y="129" width="6" height="6" fill="#00D2FF"/>
            <rect x="105" y="129" width="6" height="6" fill="#FFFFFF"/>
            <rect x="117" y="129" width="6" height="6" fill="#00D2FF"/>
            <rect x="129" y="129" width="6" height="6" fill="#FFFFFF"/>
            {{-- Center Badge Shield --}}
            <circle cx="75" cy="75" r="10" fill="#060C18"/>
            <circle cx="75" cy="75" r="7" fill="#0052CC"/>
            <path d="M75 71v8M71 75h8" stroke="#FFFFFF" stroke-width="1.8" stroke-linecap="round"/>
          </svg>
        </div>
        <div style="font-size: 11px; color: var(--fg-subtle); margin-top: 10px;">
          Scan with mobile to start <strong style="color: var(--accent, #00d2ff);">@GardaCyberBot</strong>
        </div>
      </div>

      {{-- TELEGRAM & WEBHOOK DETAILS --}}
      <div>
        <div style="background: var(--bg-surface-2, #0d1527); border: 1px solid var(--border-subtle, rgba(255,255,255,0.08)); border-radius: 12px; padding: 18px; margin-bottom: 18px;">
          <h4 style="font-size: 13.5px; font-weight: 700; margin: 0 0 10px; color: var(--fg-default);">Step 1: Connect your Telegram Chat</h4>
          <p style="font-size: 13px; color: var(--fg-muted); margin: 0 0 12px; line-height: 1.5;">
            Start a conversation with <strong class="mono" style="color: var(--accent, #00d2ff);">@GardaCyberBot</strong> and send your secure workspace verification token:
          </p>
          <div style="display: flex; gap: 8px; align-items: center; max-width: 480px;">
            <input class="input mono" type="text" readonly value="/connect GARDA-{{ strtoupper(substr(md5($user->id . $user->email), 0, 10)) }}" id="tgTokenInput" style="font-size: 12px; background: #060c18; color: #38bdf8;" />
            <button type="button" class="btn btn-ghost btn-sm copy-btn" data-copy="/connect GARDA-{{ strtoupper(substr(md5($user->id . $user->email), 0, 10)) }}">
              <svg viewBox="0 0 24 24" width="13" height="13" fill="none" stroke="currentColor" stroke-width="2"><rect width="14" height="14" x="8" y="8" rx="2" ry="2"/><path d="M4 16c-1.1 0-2-.9-2-2V4c0-1.1.9-2 2-2h10c1.1 0 2 .9 2 2"/></svg>
              <span class="copy-text">Copy</span>
            </button>
          </div>
        </div>

        <form method="POST" action="{{ route('profile.update') }}">
          @csrf
          @method('PATCH')

          <div style="background: var(--bg-surface-2, #0d1527); border: 1px solid var(--border-subtle, rgba(255,255,255,0.08)); border-radius: 12px; padding: 18px;">
            <h4 style="font-size: 13.5px; font-weight: 700; margin: 0 0 10px; color: var(--fg-default);">Step 2: Notification Channel Preferences</h4>
            <div class="dash-grid" style="grid-template-columns: 1fr 1fr; gap: 14px; margin-bottom: 14px;">
              <div>
                <label for="telegram_chat_id" style="display: block; font-size: 11.5px; font-weight: 600; color: var(--fg-muted); margin-bottom: 6px;">Telegram Chat ID / Group ID</label>
                <input class="input mono" id="telegram_chat_id" name="telegram_chat_id" type="text" placeholder="e.g. 198273645 or -100123456789" value="{{ old('telegram_chat_id', $user->telegram_chat_id) }}" style="font-size: 12.5px;" />
              </div>
              <div>
                <label for="telegram_bot_token" style="display: block; font-size: 11.5px; font-weight: 600; color: var(--fg-muted); margin-bottom: 6px;">Telegram Bot Token (Optional Custom Bot)</label>
                <input class="input mono" id="telegram_bot_token" name="telegram_bot_token" type="password" placeholder="123456:ABC-DEF..." value="{{ old('telegram_bot_token', $user->telegram_bot_token) }}" style="font-size: 12.5px;" />
              </div>
            </div>

            <div style="margin-bottom: 14px;">
              <label for="telegram_webhook_url" style="display: block; font-size: 11.5px; font-weight: 600; color: var(--fg-muted); margin-bottom: 6px;">Custom Inbound Webhook (Discord / Slack / SIEM)</label>
              <input class="input mono" id="telegram_webhook_url" name="telegram_webhook_url" type="url" placeholder="https://hooks.slack.com/services/..." value="{{ old('telegram_webhook_url', $user->telegram_webhook_url) }}" style="font-size: 12.5px;" />
            </div>

            <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 12px; pt: 8px;">
              <div style="display: flex; gap: 16px; align-items: center; flex-wrap: wrap;">
                <label class="chk-row" style="margin: 0; font-size: 12px;">
                  <input type="checkbox" checked /> Critical signals
                </label>
                <label class="chk-row" style="margin: 0; font-size: 12px;">
                  <input type="checkbox" checked /> Defacement alerts
                </label>
              </div>

              <button class="btn btn-ghost btn-sm" type="submit">Save Channel Settings</button>
            </div>
          </div>
        </form>
      </div>
    </div>
  </div>
@endsection