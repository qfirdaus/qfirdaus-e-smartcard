(function () {
  'use strict';

  const cfg = window.IQS_AI_CHATBOT || {};
  const i18n = cfg.i18n || {};
  let openTracked = false;
  const launcher = document.querySelector('[data-ai-chatbot-launcher]');
  const panel = document.querySelector('[data-ai-chatbot-panel]');
  const closeBtn = document.querySelector('[data-ai-chatbot-close]');
  const form = document.querySelector('[data-ai-chatbot-form]');
  const input = document.querySelector('[data-ai-chatbot-input]');
  const messages = document.querySelector('[data-ai-chatbot-messages]');
  const status = document.querySelector('[data-ai-chatbot-status]');
  const sendBtn = document.querySelector('[data-ai-chatbot-send]');

  if (!launcher || !panel || !form || !input || !messages) {
    return;
  }

  function setOpen(open) {
    panel.classList.toggle('is-open', open);
    launcher.setAttribute('aria-expanded', open ? 'true' : 'false');
    if (open) {
      trackOpened();
      input.focus();
      scrollToBottom();
    }
  }

  function setBusy(busy) {
    input.disabled = busy;
    if (sendBtn) {
      sendBtn.disabled = busy;
    }
    if (status) {
      status.textContent = busy
        ? (i18n.busy || 'Sedang menjawab...')
        : (cfg.enabled ? (i18n.ready || 'Prototype aktif') : (i18n.disabled || 'Belum aktif'));
    }
  }

  function scrollToBottom() {
    messages.scrollTop = messages.scrollHeight;
  }

  function addMessage(role, text) {
    const item = document.createElement('div');
    item.className = 'ai-chatbot-message ai-chatbot-message--' + role;

    const bubble = document.createElement('div');
    bubble.className = 'ai-chatbot-message__bubble';
    bubble.textContent = text;

    item.appendChild(bubble);

    messages.appendChild(item);
    scrollToBottom();
  }

  function safeText(value, maxLength) {
    const text = String(value || '').replace(/[\u0000-\u001f\u007f]+/g, ' ').trim();
    return text.length > maxLength ? text.slice(0, maxLength) : text;
  }

  function isVisible(el) {
    if (!el || panel.contains(el)) {
      return false;
    }
    const style = window.getComputedStyle(el);
    const rect = el.getBoundingClientRect();
    return style.display !== 'none' && style.visibility !== 'hidden' && rect.width > 0 && rect.height > 0;
  }

  function uniqueList(items, maxItems, maxLength) {
    const seen = new Set();
    const out = [];
    items.forEach(function (item) {
      const text = safeText(item, maxLength);
      const key = text.toLowerCase();
      if (!text || seen.has(key)) {
        return;
      }
      seen.add(key);
      out.push(text);
    });
    return out.slice(0, maxItems);
  }

  function collectText(selector, maxItems, maxLength) {
    return uniqueList(Array.from(document.querySelectorAll(selector))
      .filter(isVisible)
      .map(function (el) { return el.textContent || ''; }), maxItems, maxLength);
  }

  function collectFormLabels() {
    return uniqueList(Array.from(document.querySelectorAll('label, .form-label'))
      .filter(isVisible)
      .filter(function (label) {
        const targetId = label.getAttribute('for') || '';
        const field = targetId ? document.getElementById(targetId) : null;
        const fieldType = field ? String(field.getAttribute('type') || '').toLowerCase() : '';
        const name = field ? String(field.getAttribute('name') || field.id || '').toLowerCase() : '';
        return fieldType !== 'password' && !/(csrf|token|secret|api[_-]?key|password|cookie)/i.test(name);
      })
      .map(function (label) { return label.textContent || ''; }), 18, 80);
  }

  function collectTableHeadings() {
    return uniqueList(Array.from(document.querySelectorAll('table'))
      .filter(isVisible)
      .flatMap(function (table) {
        return Array.from(table.querySelectorAll('thead th, thead td')).filter(isVisible).map(function (cell) {
          return cell.textContent || '';
        });
      }), 20, 80);
  }

  function buildPageUiContext() {
    const activeModal = Array.from(document.querySelectorAll('.modal.show')).find(isVisible);
    return {
      heading: collectText('main h1, main h2, .content-page h1, .content-page h2, .page-title', 3, 120)[0] || '',
      active_tab: collectText('.nav-link.active, .tab-pane.active .card-title', 4, 100)[0] || '',
      modal_title: activeModal ? safeText((activeModal.querySelector('.modal-title') || {}).textContent || '', 120) : '',
      form_labels: collectFormLabels(),
      validation_errors: collectText('.invalid-feedback, .is-invalid ~ .invalid-feedback, .alert-danger, [role="alert"].text-danger', 8, 160),
      table_headings: collectTableHeadings()
    };
  }

  function buildRuntimeContext() {
    return {
      page_path: safeText(window.location.pathname || '', 255),
      page_title: safeText(document.title || '', 160),
      page_ui: buildPageUiContext()
    };
  }

  async function sendMessage(text) {
    setBusy(true);
    try {
      const response = await fetch(cfg.endpoint, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'Accept': 'application/json',
          'X-CSRF-Token': cfg.csrf || '',
          'X-Requested-With': 'XMLHttpRequest'
        },
        body: JSON.stringify({ message: text, context: buildRuntimeContext() })
      });

      const payload = await response.json().catch(function () { return null; });
      if (!response.ok || !payload || payload.success !== true) {
        throw new Error((payload && payload.message) || (i18n.genericError || 'AI Chatbot tidak dapat menjawab buat masa ini.'));
      }

      addMessage('assistant', payload.message || '');
    } catch (error) {
      addMessage('assistant', error && error.message ? error.message : (i18n.genericError || 'AI Chatbot tidak dapat menjawab buat masa ini.'));
    } finally {
      setBusy(false);
    }
  }

  function trackOpened() {
    if (openTracked || !cfg.eventEndpoint) {
      return;
    }
    openTracked = true;

    fetch(cfg.eventEndpoint, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
        'X-CSRF-Token': cfg.csrf || '',
        'X-Requested-With': 'XMLHttpRequest'
      },
      body: JSON.stringify({ action: 'opened' })
    }).catch(function () {});
  }

  launcher.addEventListener('click', function () {
    setOpen(!panel.classList.contains('is-open'));
  });

  if (closeBtn) {
    closeBtn.addEventListener('click', function () {
      setOpen(false);
    });
  }

  input.addEventListener('keydown', function (event) {
    if (event.key === 'Enter' && !event.shiftKey) {
      event.preventDefault();
      form.requestSubmit();
    }
  });

  form.addEventListener('submit', function (event) {
    event.preventDefault();
    const text = input.value.trim();
    if (!text) {
      input.focus();
      return;
    }

    addMessage('user', text);
    input.value = '';
    void sendMessage(text);
  });

  if (cfg.welcomeMessage) {
    addMessage('assistant', cfg.welcomeMessage);
  }

  setBusy(false);
})();
