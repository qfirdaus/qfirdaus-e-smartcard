(function (window, document) {
  'use strict';

  if (window.__AppLoaderInit) {
    return;
  }
  window.__AppLoaderInit = true;

  var activeTokens = [];
  var defaultI18n = window.IQS_LOADER_I18N || {};

  function hasIqsLoader() {
    return !!(window.IQSLoader && typeof window.IQSLoader.show === 'function');
  }

  function resolveMessage(message) {
    if (typeof message === 'string' && message.trim() !== '') {
      return message;
    }
    return defaultI18n.defaultMessage || 'Loading...';
  }

  function isSkippedElement(element) {
    return !!(element && (element.dataset.noLoader !== undefined || element.hasAttribute('data-no-loader')));
  }

  function show(message, options) {
    if (!hasIqsLoader()) {
      return null;
    }

    var token = window.IQSLoader.show(resolveMessage(message), options || {});
    activeTokens.push(token);
    return token;
  }

  function hide(token) {
    if (!hasIqsLoader()) {
      return;
    }

    if (token) {
      activeTokens = activeTokens.filter(function (item) {
        return String(item) !== String(token);
      });
      window.IQSLoader.hide(token);
      return;
    }

    activeTokens.splice(0).forEach(function (item) {
      window.IQSLoader.hide(item);
    });
    window.IQSLoader.hide();
  }

  function shouldSkipLink(event, anchor) {
    var href = anchor.getAttribute('href') || '';
    var target = anchor.getAttribute('target') || '';

    return (
      target === '_blank' ||
      href === '' ||
      href.charAt(0) === '#' ||
      href.indexOf('javascript:') === 0 ||
      anchor.hasAttribute('download') ||
      isSkippedElement(anchor) ||
      /download=excel/i.test(href) ||
      event.metaKey ||
      event.ctrlKey ||
      event.shiftKey ||
      event.altKey
    );
  }

  function isSidebarNavigationLink(anchor) {
    if (!anchor || !anchor.closest) {
      return false;
    }

    var sidebar = anchor.closest('#leftside-menu');
    if (!sidebar) {
      return false;
    }

    return !anchor.hasAttribute('data-sidebar-toggle');
  }

  function headerValue(headers, key) {
    if (!headers) {
      return null;
    }
    if (typeof headers.get === 'function') {
      return headers.get(key);
    }
    return headers[key] || headers[key.toLowerCase()] || null;
  }

  function shouldUseFetchLoader(init) {
    var settings = init || {};
    return settings.loader === true || headerValue(settings.headers || {}, 'X-Use-Loader') === '1';
  }

  function shouldSkipFetch(init) {
    var settings = init || {};
    return settings.noLoader === true || headerValue(settings.headers || {}, 'X-No-Loader') === '1' || !shouldUseFetchLoader(settings);
  }

  if (document.readyState !== 'loading') {
    setTimeout(function () { hide(); }, 0);
  } else {
    document.addEventListener('DOMContentLoaded', function () { hide(); }, { once: true });
  }

  window.addEventListener('pageshow', function () { hide(); });

  document.addEventListener('click', function (event) {
    var anchor = event.target.closest && event.target.closest('a[href]');
    if (!anchor || shouldSkipLink(event, anchor) || !isSidebarNavigationLink(anchor)) {
      return;
    }
    show(defaultI18n.navigation || defaultI18n.defaultMessage);
  }, true);

  if (typeof window.fetch === 'function' && !window.__AppLoaderFetchWrapped) {
    var originalFetch = window.fetch.bind(window);
    window.fetch = function (input, init) {
      var skip = shouldSkipFetch(init || {});
      var token = skip ? null : show(defaultI18n.defaultMessage);

      return originalFetch(input, init).finally(function () {
        if (!skip) {
          hide(token);
        }
      });
    };
    window.__AppLoaderFetchWrapped = true;
  }

  if (window.jQuery && !window.__AppLoaderAjaxBound) {
    window.jQuery(document).on('ajaxSend', function (_event, _xhr, settings) {
      if (shouldSkipFetch(settings || {})) {
        return;
      }
      settings.__iqsLoaderToken = show(defaultI18n.defaultMessage);
    });
    window.jQuery(document).on('ajaxComplete ajaxError', function (_event, _xhr, settings) {
      hide(settings && settings.__iqsLoaderToken);
    });
    window.__AppLoaderAjaxBound = true;
  }

  window.AppLoader = {
    show: function (message, options) {
      return show(message, options);
    },
    hide: function (token) {
      hide(token);
    },
    update: function (message) {
      if (window.IQSLoader && typeof window.IQSLoader.update === 'function') {
        window.IQSLoader.update(resolveMessage(message));
      }
    }
  };
})(window, document);
