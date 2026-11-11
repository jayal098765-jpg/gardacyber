/* ============================================================
   GARDACYBER — Garda AI Floating Widget
   Floating chat bubble (bottom-right) for the console layout.
   ============================================================ */
(function () {
  'use strict';

  var csrf = document.querySelector('meta[name="csrf-token"]');
  if (!csrf) return;
  csrf = csrf.content;

  var openBtn = document.getElementById('copilotFab');
  var panel = document.getElementById('copilotWidget');
  var messagesEl = document.getElementById('copilotWidgetMessages');
  var inputEl = document.getElementById('copilotWidgetInput');
  var sendBtn = document.getElementById('copilotWidgetSend');
  var closeBtn = document.getElementById('copilotWidgetClose');
  var statusEl = document.getElementById('copilotWidgetStatus');

  if (!openBtn || !panel || !messagesEl || !inputEl || !sendBtn) return;

  var storeUrl = openBtn.dataset.storeUrl || '/ai-chat';

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

  function toggle(open) {
    panel.classList.toggle('open', open);
    openBtn.classList.toggle('hidden', open);
    if (open) {
      setTimeout(function () { inputEl.focus(); }, 80);
    }
  }

  openBtn.addEventListener('click', function () { toggle(true); });

  if (closeBtn) {
    closeBtn.addEventListener('click', function () { toggle(false); });
  }

  async function send() {
    var message = inputEl.value.trim();
    if (!message) return;

    addMessage('user', message);
    inputEl.value = '';
    sendBtn.disabled = true;
    setStatus('Garda AI sedang mengetik…');

    try {
      var res = await fetch(storeUrl, {
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
})();
