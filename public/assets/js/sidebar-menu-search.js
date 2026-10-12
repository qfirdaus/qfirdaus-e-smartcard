(function () {
  'use strict';

  function initSidebarMenuSearch() {
    const root = document.getElementById('sidebar-menu-search');
    const input = document.getElementById('sidebar-menu-search-input');
    const clearButton = document.getElementById('sidebar-menu-search-clear');
    const compactButton = document.getElementById('sidebar-menu-search-compact');
    const results = document.getElementById('sidebar-menu-search-results');
    if (!root || !input || !clearButton || !compactButton || !results) return;

    const config = window.sidebarMenuSearchConfig || {};
    const i18n = config.i18n || {};
    let debounceTimer = null;
    let requestController = null;
    let activeIndex = -1;

    function closeResults() {
      results.hidden = true;
      results.innerHTML = '';
      activeIndex = -1;
      input.setAttribute('aria-expanded', 'false');
    }

    function setMessage(message, icon, cssClass) {
      results.innerHTML = '';
      const item = document.createElement('div');
      item.className = 'sidebar-search-message ' + (cssClass || '');
      const iconElement = document.createElement('i');
      iconElement.className = icon || 'ri-information-line';
      const text = document.createElement('span');
      text.textContent = message;
      item.append(iconElement, text);
      results.appendChild(item);
      results.hidden = false;
      input.setAttribute('aria-expanded', 'true');
    }

    function renderItems(items) {
      results.innerHTML = '';
      activeIndex = -1;
      if (!Array.isArray(items) || items.length === 0) {
        setMessage(i18n.empty || 'No matching menu found.', 'ri-search-eye-line', 'is-empty');
        return;
      }

      items.forEach(function (item, index) {
        const link = document.createElement('a');
        link.className = 'sidebar-search-result';
        link.href = String(item.url || '#');
        link.setAttribute('role', 'option');
        link.setAttribute('data-result-index', String(index));

        const icon = document.createElement('i');
        icon.className = String(item.icon || 'ri-folder-fill');
        icon.setAttribute('aria-hidden', 'true');

        const content = document.createElement('span');
        content.className = 'sidebar-search-result-copy';
        const name = document.createElement('strong');
        name.textContent = String(item.name || '');
        const module = document.createElement('small');
        const context = [item.module, item.subgroup].filter(function (value) {
          return String(value || '').trim() !== '';
        });
        module.textContent = context.join(' \u203a ');
        content.append(name, module);

        const arrow = document.createElement('i');
        arrow.className = 'ri-arrow-right-s-line sidebar-search-result-arrow';
        arrow.setAttribute('aria-hidden', 'true');
        link.append(icon, content, arrow);
        results.appendChild(link);
      });

      results.hidden = false;
      input.setAttribute('aria-expanded', 'true');
    }

    async function search(query) {
      if (requestController) requestController.abort();
      requestController = new AbortController();
      root.classList.add('is-loading');
      setMessage(i18n.loading || 'Searching...', 'ri-loader-4-line', 'is-loading');

      try {
        const response = await fetch(config.url + '?q=' + encodeURIComponent(query), {
          headers: {
            'Accept': 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            'X-No-Loader': '1'
          },
          signal: requestController.signal
        });
        const payload = await response.json();
        if (!response.ok || !payload.success) {
          throw new Error(payload.message || i18n.error || 'Unable to search menus.');
        }
        renderItems(payload.data);
      } catch (error) {
        if (error.name !== 'AbortError') {
          setMessage(error.message || i18n.error || 'Unable to search menus.', 'ri-error-warning-line', 'is-error');
        }
      } finally {
        root.classList.remove('is-loading');
      }
    }

    function selectResult(nextIndex) {
      const links = Array.from(results.querySelectorAll('.sidebar-search-result'));
      if (links.length === 0) return;
      activeIndex = (nextIndex + links.length) % links.length;
      links.forEach(function (link, index) {
        link.classList.toggle('is-active', index === activeIndex);
        link.setAttribute('aria-selected', index === activeIndex ? 'true' : 'false');
      });
      links[activeIndex].scrollIntoView({ block: 'nearest' });
    }

    input.addEventListener('input', function () {
      const query = input.value.trim();
      clearButton.hidden = query === '';
      window.clearTimeout(debounceTimer);
      if (query.length < 2) {
        if (requestController) requestController.abort();
        closeResults();
        return;
      }
      debounceTimer = window.setTimeout(function () { search(query); }, 275);
    });

    input.addEventListener('keydown', function (event) {
      if (event.key === 'ArrowDown') {
        event.preventDefault();
        selectResult(activeIndex + 1);
      } else if (event.key === 'ArrowUp') {
        event.preventDefault();
        selectResult(activeIndex - 1);
      } else if (event.key === 'Enter' && activeIndex >= 0) {
        const active = results.querySelector('.sidebar-search-result.is-active');
        if (active) {
          event.preventDefault();
          active.click();
        }
      } else if (event.key === 'Escape') {
        closeResults();
        input.blur();
      }
    });

    clearButton.addEventListener('click', function () {
      input.value = '';
      clearButton.hidden = true;
      if (requestController) requestController.abort();
      closeResults();
      input.focus();
    });

    compactButton.addEventListener('click', function () {
      const toggle = document.querySelector('.button-toggle-menu');
      if (toggle) toggle.click();
      window.setTimeout(function () { input.focus(); }, 180);
    });

    document.addEventListener('click', function (event) {
      if (!root.contains(event.target)) closeResults();
    });
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initSidebarMenuSearch, { once: true });
  } else {
    initSidebarMenuSearch();
  }
})();
