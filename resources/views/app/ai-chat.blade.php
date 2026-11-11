@extends('layouts.app')

@section('title', 'Garda AI')

@section('content')
  <div class="page-head row">
    <div>
      <div style="display: flex; align-items: center; gap: 8px; margin-bottom: 8px;">
        <span class="badge ai-badge"><span class="signal-dot pulse"></span>Garda AI</span>
        <span class="pill ok">Bahasa Indonesia · konteks workspace</span>
      </div>
      <h1>Tanya apa saja tentang keamanan projectmu</h1>
      <p>Garda AI membaca data grup aset & temuanmu untuk menjawab pertanyaan secara kontekstual.</p>
    </div>
    <div class="page-actions">
      <button type="button" class="btn btn-ghost btn-sm" id="clearChatBtn" data-url="{{ route('ai-chat.clear') }}">Bersihkan percakapan</button>
    </div>
  </div>

  <div class="panel copilot-panel" id="copilotPanel">
    <div class="copilot-messages" id="copilotMessages" aria-live="polite">
      <div class="copilot-msg assistant">
        <div class="copilot-avatar">G</div>
        <div class="copilot-bubble">
          <p style="margin: 0 0 6px;"><strong>Halo, saya Garda AI! 👋</strong></p>
          <p style="margin: 0;">Saya bisa dibantu membaca data workspace kamu — coba tanya, misalnya:</p>
          <ul style="margin: 8px 0 0; padding-left: 18px;">
            <li>"Grup mana yang paling berisiko?"</li>
            <li>"Temuan kritis apa saja yang masih terbuka?"</li>
            <li>"Apa cara memperbaiki header HSTS yang hilang?"</li>
          </ul>
        </div>
      </div>
    </div>

    <div class="copilot-input-row">
      <textarea id="copilotInput" class="input" rows="2" maxlength="1000" placeholder="Ketik pertanyaanmu dalam Bahasa Indonesia..." aria-label="Pesan untuk Garda AI"></textarea>
      <button type="button" class="btn btn-primary" id="copilotSendBtn">
        <span class="copilot-send-label">Kirim</span>
      </button>
    </div>
    <div class="copilot-status" id="copilotStatus" role="status"></div>
  </div>
@endsection

@push('scripts')
<script>
(function () {
  var csrf = document.querySelector('meta[name="csrf-token"]').content;
  var messagesEl = document.getElementById('copilotMessages');
  var inputEl = document.getElementById('copilotInput');
  var sendBtn = document.getElementById('copilotSendBtn');
  var statusEl = document.getElementById('copilotStatus');

  function addMessage(role, text) {
    var msg = document.createElement('div');
    msg.className = 'copilot-msg ' + role;
    var avatar = document.createElement('div');
    avatar.className = 'copilot-avatar';
    avatar.textContent = role === 'assistant' ? 'G' : 'K';
    var bubble = document.createElement('div');
    bubble.className = 'copilot-bubble';
    bubble.textContent = text;
    msg.appendChild(avatar);
    msg.appendChild(bubble);
    messagesEl.appendChild(msg);
    messagesEl.scrollTop = messagesEl.scrollHeight;
  }

  function setStatus(text) {
    statusEl.textContent = text || '';
    statusEl.classList.toggle('visible', !!text);
  }

  async function send() {
    var message = inputEl.value.trim();
    if (!message) return;

    addMessage('user', message);
    inputEl.value = '';
    sendBtn.disabled = true;
    setStatus('Garda AI sedang mengetik…');

    try {
      var res = await fetch('{{ route('ai-chat.store') }}', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'Accept': 'application/json',
          'X-CSRF-TOKEN': csrf
        },
        body: JSON.stringify({ message: message })
      });

      var data = await res.json();
      if (!res.ok) {
        addMessage('assistant', 'Terjadi kesalahan saat memproses pertanyaan. Silakan coba lagi.');
        return;
      }

      addMessage('assistant', data.reply || '…');
    } catch (e) {
      addMessage('assistant', 'Tidak dapat terhubung ke server. Coba lagi nanti.');
    } finally {
      sendBtn.disabled = false;
      setStatus('');
      inputEl.focus();
    }
  }

  sendBtn.addEventListener('click', send);
  inputEl.addEventListener('keydown', function (e) {
    if (e.key === 'Enter' && !e.shiftKey) {
      e.preventDefault();
      send();
    }
  });

  document.getElementById('clearChatBtn').addEventListener('click', async function () {
    await fetch(this.dataset.url, {
      method: 'POST',
      headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' }
    });
    messagesEl.querySelectorAll('.copilot-msg:not(.assistant:first-child)').forEach(function (el) {
      if (el.classList.contains('assistant') && el === messagesEl.firstElementChild) return;
      el.remove();
    });
  });
})();
</script>
@endpush
