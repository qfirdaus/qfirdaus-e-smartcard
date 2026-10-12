(function (window) {
  'use strict';

  function setButtonLoading(button, loading, options) {
    if (!button) {
      return;
    }

    const settings = options || {};
    const loadingText = typeof settings.loadingText === 'string' ? settings.loadingText : 'Loading...';
    const loadingHtml = typeof settings.loadingHtml === 'string'
      ? settings.loadingHtml
      : '<span class="spinner-border spinner-border-sm me-1"></span> ' + loadingText;

    if (loading) {
      button.disabled = true;
      if (!button.dataset.originalHtml) {
        button.dataset.originalHtml = button.innerHTML;
      }
      button.innerHTML = loadingHtml;
      return;
    }

    button.disabled = false;
    if (button.dataset.originalHtml) {
      button.innerHTML = button.dataset.originalHtml;
      delete button.dataset.originalHtml;
    }
  }

  function persistBootstrapTabs(options) {
    const settings = options || {};
    const tabSelector = typeof settings.tabSelector === 'string'
      ? settings.tabSelector
      : 'a[data-bs-toggle="tab"]';
    const storageKey = typeof settings.storageKey === 'string'
      ? settings.storageKey
      : 'lastActiveTab';
    const defaultTab = typeof settings.defaultTab === 'string'
      ? settings.defaultTab
      : '#general-tab';

    const urlTab = new URLSearchParams(window.location.search).get('tab');
    let storedTab = null;
    try {
      storedTab = window.localStorage.getItem(storageKey);
    } catch (error) {
      storedTab = null;
    }

    const wanted = urlTab
      ? ('#' + urlTab + '-tab')
      : (window.location.hash || storedTab || defaultTab);

    const targetTab = document.querySelector('a[href="' + wanted + '"]');
    if (targetTab && window.bootstrap && window.bootstrap.Tab) {
      window.bootstrap.Tab.getOrCreateInstance(targetTab).show();
    }

    function syncUrlForTab(target) {
      if (!target) {
        return;
      }

      const href = target.getAttribute('href') || '';
      if (!href || href.charAt(0) !== '#') {
        return;
      }

      const tabName = href.replace(/^#/, '').replace(/-tab$/, '');
      if (!tabName) {
        return;
      }

      try {
        const url = new window.URL(window.location.href);
        url.searchParams.set('tab', tabName);
        window.history.replaceState({}, '', url.toString());
      } catch (error) {
        // ignore URL sync errors
      }
    }

    document.querySelectorAll(tabSelector).forEach(function (tab) {
      tab.addEventListener('shown.bs.tab', function (event) {
        try {
          window.localStorage.setItem(storageKey, event.target.getAttribute('href'));
        } catch (error) {
          // ignore storage errors
        }
        syncUrlForTab(event.target);
      });
    });
  }

  window.PageUiHelper = window.PageUiHelper || {
    setButtonLoading: setButtonLoading,
    persistBootstrapTabs: persistBootstrapTabs
  };
})(window);
