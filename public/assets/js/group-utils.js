/**
 * Shared utilities untuk kumpulan-pengguna.php
 */

// =========================================================
// 🔧 KONFIG: Base path detection
// =========================================================
const GroupUtils = {
  getTranslations() {
    return window.GroupPageT || window.T || {};
  },

  t(key, fallback = '') {
    const translations = this.getTranslations();
    const value = translations && Object.prototype.hasOwnProperty.call(translations, key)
      ? translations[key]
      : undefined;
    return value !== undefined && value !== null && value !== '' ? value : fallback;
  },

  // Base path projek yang betul di semua environment
  getBasePath() {
    const rawBase = document.querySelector('meta[name="base-path"]')?.getAttribute('content') ||
           (location.pathname.replace(/\/(pages|ajax)(\/.*)?$/, '') || '');
    const base = String(rawBase || '').replace(/\/+$/, '');
    return base === '/' ? '' : base;
  },
  
  getAjaxBase() {
    const base = this.getBasePath();
    return (base || '') + '/ajax/';
  },
  
  getCSRF() {
    return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
  },

  _loaderTokens: {},

  showLoader(key, message) {
    const loaderKey = String(key || 'group');
    this._loaderTokens[loaderKey] = message || this.t('loading', 'Loading...');
    return this._loaderTokens[loaderKey];
  },

  hideLoader(key) {
    const loaderKey = String(key || 'group');
    delete this._loaderTokens[loaderKey];
  },

  setButtonBusy(button, busy, label = '') {
    if (!button) return;
    if (busy) {
      if (button.dataset.busy === '1') return;
      button.dataset.busy = '1';
      button.dataset.busyOriginalHtml = button.innerHTML;
      button.dataset.busyOriginalDisabled = button.disabled ? '1' : '0';
      button.disabled = true;
      button.setAttribute('aria-busy', 'true');
      button.innerHTML = '<span class="spinner-border spinner-border-sm me-2" aria-hidden="true"></span>'
        + '<span>' + this.esc(label || this.t('saving', 'Saving...')) + '</span>';
      return;
    }

    if (button.dataset.busy !== '1') return;
    button.innerHTML = button.dataset.busyOriginalHtml || button.innerHTML;
    button.disabled = button.dataset.busyOriginalDisabled === '1';
    button.removeAttribute('aria-busy');
    delete button.dataset.busy;
    delete button.dataset.busyOriginalHtml;
    delete button.dataset.busyOriginalDisabled;
  },
  
  hasDataTable() {
    return !!(window.jQuery && jQuery.fn && jQuery.fn.DataTable);
  },
  
  // Escape HTML
  esc(s) {
    return (s || '').toString()
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#39;');
  },
  
  // Build API URL
  apiUrl(file, params) {
    const u = new URL(this.getAjaxBase() + file, window.location.origin);
    if (params && typeof params === 'object') {
      Object.entries(params).forEach(([k, v]) => u.searchParams.set(k, String(v)));
    }
    u.searchParams.set('_', Date.now()); // cache-bust
    return u.toString();
  },
  
  // Safe JSON fetch
  async fetchJSONSafe(url, opts) {
    const requestOpts = Object.assign({}, opts || {});
    requestOpts.noLoader = requestOpts.noLoader !== false;
    requestOpts.headers = Object.assign(
      { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-No-Loader': '1' },
      (opts && opts.headers) || {}
    );
    requestOpts.credentials = requestOpts.credentials || 'same-origin';

    const r = await fetch(url, requestOpts);
    const txt = await r.text();
    try {
      return JSON.parse(txt);
    } catch (e) {
      const snippet = txt.slice(0, 240).replace(/\s+/g, ' ').trim();
      console.error('[group-utils] Non-JSON response:', snippet);
      throw new Error(this.t('non_json_response', 'Server did not return a valid response.'));
    }
  },
  
  // Modal helpers
  ensureInBody(el) {
    if (!el) return;
    if (el.parentElement !== document.body) {
      document.body.appendChild(el);
    }
  },
  
  getModal(el) {
    if (!el) return null;
    this.ensureInBody(el);
    const B = window.bootstrap && bootstrap.Modal ? bootstrap.Modal : null;
    return B ? B.getOrCreateInstance(el, { backdrop: true, focus: true, keyboard: true }) : null;
  },
  
  showModalSafe(el) {
    const M = window.bootstrap && bootstrap.Modal ? bootstrap.Modal : null;
    if (!M || !el) return false;
    this.ensureInBody(el);
    M.getOrCreateInstance(el, { backdrop: true, focus: true, keyboard: true }).show();
    return true;
  },
  
  // Normalize path
  normalizePath(href) {
    if (!href) return '';
    try {
      const a = document.createElement('a');
      a.href = href;
      const p = (a.pathname || href).toLowerCase();
      const seg = p.split('/').filter(Boolean);
      const base = seg.length ? seg[seg.length - 1] : p;
      return base.split('?')[0].split('#')[0];
    } catch (_) {
      const s = String(href).toLowerCase();
      return s.split('/').pop().split('?')[0].split('#')[0];
    }
  },

  fireAlert(options = {}) {
    if (window.GroupSwal && typeof window.GroupSwal.fire === 'function') {
      return window.GroupSwal.fire(options);
    }
    if (window.Swal && typeof window.Swal.fire === 'function') {
      return window.Swal.fire(options);
    }
    return Promise.resolve(null);
  }
};

// Setup modal stacking
document.addEventListener('show.bs.modal', function (e) {
  const openCount = document.querySelectorAll('.modal.show').length;
  const zBase = 1060 + (openCount * 20);
  e.target.style.zIndex = zBase;
  setTimeout(() => {
    document.querySelectorAll('.modal-backdrop:not(.modal-stack)').forEach(bd => {
      bd.style.zIndex = zBase - 1;
      bd.classList.add('modal-stack');
    });
  }, 0);
});

// Export untuk global access
window.GroupUtils = GroupUtils;





