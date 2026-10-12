(function () {
  'use strict';

  var i18n = window.DeveloperGuideI18n || {};
  var search = document.getElementById('dg-guide-search');
  var summary = document.getElementById('dg-search-summary');
  var resultText = document.getElementById('dg-search-result-text');
  var clearButton = document.getElementById('dg-search-clear');
  var mainTabs = Array.prototype.slice.call(document.querySelectorAll('#developerGuideTabs [data-bs-toggle="pill"]'));
  var uiSourceLabels = {
    Rules: 'rules', References: 'references', 'Core Boundary': 'core_boundary', 'Page File': 'page_file',
    Controller: 'controller', Separation: 'separation', Sample: 'sample', 'Main MySQL': 'main_mysql',
    'Additional DB': 'additional_db', Transaction: 'transaction', Endpoint: 'endpoint',
    'External Service': 'external_service', Event: 'event', Workflow: 'workflow', 'Admin Samples': 'admin_samples',
    'Custom Keys': 'custom_keys', 'Setup Flow': 'setup_flow', Reference: 'reference', 'Audit Event': 'audit_event',
    'View As': 'view_as', Template: 'template', Components: 'components', Boundary: 'boundary',
    'Before Coding': 'before_coding', 'Before Handover': 'before_handover', Fetch: 'fetch', DataTable: 'datatable'
  };

  document.querySelectorAll('.dg-subtabs .nav-link').forEach(function (button) {
    var key = uiSourceLabels[button.textContent.trim()];
    if (key && i18n.ui && i18n.ui[key]) button.textContent = i18n.ui[key];
  });

  function activateTab(button, updateHash) {
    if (!button || !window.bootstrap || !window.bootstrap.Tab) return;
    window.bootstrap.Tab.getOrCreateInstance(button).show();
    if (updateHash !== false) {
      var tabId = (button.getAttribute('data-bs-target') || '').replace('#dg-pane-', '');
      if (tabId) history.replaceState(null, '', '#guide=' + encodeURIComponent(tabId));
    }
  }

  function restoreTab() {
    var match = window.location.hash.match(/(?:^#|&)guide=([a-z0-9_-]+)/i);
    if (!match) return;
    var button = document.getElementById('dg-tab-' + decodeURIComponent(match[1]));
    if (button) activateTab(button, false);
  }

  function searchGuide() {
    var term = (search ? search.value : '').trim().toLocaleLowerCase();
    var matches = [];
    mainTabs.forEach(function (button) {
      var target = document.querySelector(button.getAttribute('data-bs-target'));
      var matched = !term || ((button.textContent + ' ' + (target ? target.textContent : '')).toLocaleLowerCase().indexOf(term) !== -1);
      button.classList.toggle('dg-search-hidden', !matched);
      if (matched) matches.push(button);
    });

    if (!term) {
      summary.classList.add('d-none');
      return;
    }
    summary.classList.remove('d-none');
    resultText.textContent = matches.length
      ? String(i18n.result || '%d topics match your search').replace('%d', String(matches.length))
      : String(i18n.noResult || 'No topics match your search.');

    var active = document.querySelector('#developerGuideTabs .nav-link.active');
    if (matches.length && (!active || active.classList.contains('dg-search-hidden'))) {
      activateTab(matches[0]);
    }
    var panel = document.querySelector('.tab-pane.active .dg-panel');
    if (panel && matches.length) {
      panel.classList.remove('dg-search-match');
      window.requestAnimationFrame(function () { panel.classList.add('dg-search-match'); });
    }
  }

  function setCopied(button) {
    if (!button) return;
    var original = button.innerHTML;
    button.classList.add('is-copied');
    button.innerHTML = '<i class="ri-check-line me-1"></i>' + String(i18n.copied || 'Copied');
    window.setTimeout(function () {
      button.innerHTML = original;
      button.classList.remove('is-copied');
    }, 1400);
  }

  function fallbackCopy(text, button) {
    var textarea = document.createElement('textarea');
    textarea.value = text;
    textarea.setAttribute('readonly', 'readonly');
    textarea.style.position = 'fixed';
    textarea.style.left = '-9999px';
    document.body.appendChild(textarea);
    textarea.select();
    try {
      if (document.execCommand('copy')) setCopied(button);
    } finally {
      textarea.remove();
    }
  }

  function copyText(text, button) {
    if (navigator.clipboard && typeof navigator.clipboard.writeText === 'function') {
      navigator.clipboard.writeText(text).then(function () { setCopied(button); }).catch(function () { fallbackCopy(text, button); });
    } else {
      fallbackCopy(text, button);
    }
  }

  document.addEventListener('click', function (event) {
    var copyCode = event.target.closest('.dg-copy-btn');
    if (copyCode) {
      var code = copyCode.closest('.dg-code-card');
      copyText(code && code.querySelector('pre code') ? code.querySelector('pre code').textContent : '', copyCode);
      return;
    }
    var copyTextButton = event.target.closest('.dg-copy-text[data-copy-text]');
    if (copyTextButton) copyText(copyTextButton.getAttribute('data-copy-text') || '', copyTextButton);
  });

  mainTabs.forEach(function (button) {
    button.addEventListener('shown.bs.tab', function () {
      var tabId = (button.getAttribute('data-bs-target') || '').replace('#dg-pane-', '');
      if (tabId) history.replaceState(null, '', '#guide=' + encodeURIComponent(tabId));
    });
  });
  if (search) search.addEventListener('input', searchGuide);
  if (clearButton) clearButton.addEventListener('click', function () { search.value = ''; searchGuide(); search.focus(); });
  document.addEventListener('keydown', function (event) {
    if (event.key === '/' && !event.ctrlKey && !event.metaKey && !event.altKey && !/^(INPUT|TEXTAREA|SELECT)$/.test(document.activeElement.tagName)) {
      event.preventDefault();
      if (search) search.focus();
    }
  });
  window.addEventListener('hashchange', restoreTab);
  restoreTab();
})();
