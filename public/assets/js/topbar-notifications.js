(function () {
  'use strict';

  const root = document.getElementById('topbarNotificationRoot');
  if (!root) return;

  const badge = document.getElementById('topbarNotificationBadge');
  const list = document.getElementById('topbarNotificationList');
  const toggle = document.getElementById('topbarNotificationToggle');
  const readAll = document.getElementById('topbarNotificationReadAll');
  const csrfMeta = document.querySelector('meta[name="csrf-token"]');
  const csrfToken = window.csrfToken || (csrfMeta ? csrfMeta.getAttribute('content') : '');
  const urls = {
    list: root.dataset.listUrl || '',
    read: root.dataset.readUrl || '',
    readAll: root.dataset.readAllUrl || '',
    viewAll: root.dataset.viewAllUrl || '',
    base: root.dataset.baseUrl || window.BASE_URL || ''
  };

  const text = {
    loading: root.dataset.loadingText || 'Loading...',
    empty: root.dataset.emptyText || 'No notifications.',
    failed: root.dataset.failedText || 'Unable to load notifications.',
    action: root.dataset.actionText || 'Action',
    overdue: root.dataset.overdueText || 'Overdue'
  };

  let lastFetchAt = 0;
  let isLoading = false;
  const topbarLimit = 5;

  function postJson(url, payload) {
    return fetch(url, {
      method: 'POST',
      headers: {
        'Accept': 'application/json',
        'Content-Type': 'application/json',
        'X-CSRF-Token': csrfToken || ''
      },
      body: JSON.stringify(payload || {}),
      credentials: 'same-origin'
    }).then(function (response) {
      return response.text().then(function (raw) {
        let data = {};
        try { data = raw ? JSON.parse(raw) : {}; } catch (e) { data = {}; }
        if (!response.ok || data.success === false) {
          throw new Error(data.message || data.error || text.failed);
        }
        return data.data || data;
      });
    });
  }

  function escapeHtml(value) {
    const div = document.createElement('div');
    div.textContent = value == null ? '' : String(value);
    return div.innerHTML;
  }

  function updateBadge(unread) {
    const count = Number(unread || 0);
    if (!badge) return;
    if (count <= 0) {
      badge.classList.add('d-none');
      badge.textContent = '';
      if (readAll) readAll.classList.add('d-none');
      return;
    }
    badge.classList.remove('d-none');
    badge.textContent = count > 99 ? '99+' : String(count);
    if (readAll) readAll.classList.remove('d-none');
  }

  function severityClass(severity) {
    switch (String(severity || '').toLowerCase()) {
      case 'success': return 'text-success bg-success-subtle';
      case 'warning': return 'text-warning bg-warning-subtle';
      case 'danger':
      case 'error': return 'text-danger bg-danger-subtle';
      default: return 'text-primary bg-primary-subtle';
    }
  }

  function normalizeUrl(actionUrl) {
    const value = String(actionUrl || '').trim();
    if (!value) return '';
    if (/^(https?:)?\/\//i.test(value) || /^javascript:/i.test(value) || /^data:/i.test(value)) return '';
    if (value.charAt(0) === '/') return value;
    const base = String(urls.base || '').replace(/\/+$/, '');
    return base ? base + '/' + value.replace(/^\/+/, '') : value;
  }

  function renderItems(items) {
    if (!list) return;
    if (!Array.isArray(items) || items.length === 0) {
      list.innerHTML = '<div class="p-3 text-center text-muted small">' + escapeHtml(text.empty) + '</div>';
      return;
    }

    list.innerHTML = items.slice(0, topbarLimit).map(function (item) {
      const unreadClass = item.is_read ? '' : ' is-unread';
      const status = item.requires_action && item.action_status === 'pending'
        ? '<span class="badge topbar-notification-status ' + (item.is_overdue ? 'bg-danger-subtle text-danger' : 'bg-warning-subtle text-warning') + '">' + escapeHtml(item.is_overdue ? text.overdue : text.action) + '</span>'
        : '';
      const dueLine = item.due_label
        ? '<span class="' + (item.is_overdue ? 'text-danger' : '') + '">' + escapeHtml(item.due_label) + '</span>'
        : '';
      return [
        '<a href="#" class="dropdown-item notify-item topbar-notification-item' + unreadClass + '" data-id="' + escapeHtml(item.id) + '" data-url="' + escapeHtml(item.action_url || '') + '">',
        '  <div class="d-flex align-items-start gap-2">',
        '    <span class="topbar-notification-icon rounded-circle d-inline-flex align-items-center justify-content-center ' + severityClass(item.severity) + '"><i class="' + escapeHtml(item.icon || 'ri-notification-3-line') + '"></i></span>',
        '    <span class="flex-grow-1 text-wrap">',
        '      <span class="topbar-notification-title"><span class="topbar-notification-title-text">' + escapeHtml(item.title || '-') + '</span>' + status + '</span>',
        '      <span class="topbar-notification-body">' + escapeHtml(item.body || '') + '</span>',
        '      <span class="topbar-notification-meta"><span>' + escapeHtml(item.time_ago || '') + '</span>' + dueLine + '</span>',
        '    </span>',
        '  </div>',
        '</a>'
      ].join('');
    }).join('');
  }

  function setLoading() {
    if (list) {
      list.innerHTML = '<div class="p-3 text-center text-muted small">' + escapeHtml(text.loading) + '</div>';
    }
  }

  function setError(message) {
    if (list) {
      list.innerHTML = '<div class="p-3 text-center text-danger small">' + escapeHtml(message || text.failed) + '</div>';
    }
  }

  function loadNotifications(force) {
    if (!urls.list || isLoading) return Promise.resolve();
    const now = Date.now();
    if (!force && now - lastFetchAt < 25000) return Promise.resolve();

    isLoading = true;
    setLoading();
    return postJson(urls.list, { mode: 'topbar', limit: topbarLimit, filter: 'all' })
      .then(function (data) {
        lastFetchAt = Date.now();
        updateBadge(data.unread || 0);
        renderItems(data.items || []);
      })
      .catch(function (error) {
        setError(error.message);
      })
      .finally(function () {
        isLoading = false;
      });
  }

  function markRead(id, clicked) {
    if (!urls.read || !id) return Promise.resolve({});
    return postJson(urls.read, { notification_id: Number(id), clicked: !!clicked })
      .then(function (data) {
        updateBadge(data.unread || 0);
        lastFetchAt = 0;
        return data;
      });
  }

  if (toggle) {
    toggle.addEventListener('click', function () {
      loadNotifications(true);
    });
  }

  if (list) {
    list.addEventListener('click', function (event) {
      const item = event.target.closest('.topbar-notification-item');
      if (!item) return;
      event.preventDefault();

      const id = item.dataset.id || '';
      const targetUrl = normalizeUrl(item.dataset.url || '');
      markRead(id, true).finally(function () {
        if (targetUrl) {
          window.location.href = targetUrl;
        } else if (urls.viewAll) {
          window.location.href = urls.viewAll;
        }
      });
    });
  }

  if (readAll) {
    readAll.addEventListener('click', function (event) {
      event.preventDefault();
      if (!urls.readAll) return;
      postJson(urls.readAll, { limit: 100 })
        .then(function (data) {
          updateBadge(data.unread || 0);
          lastFetchAt = 0;
          return loadNotifications(true);
        })
        .catch(function (error) {
          setError(error.message);
        });
    });
  }

  postJson(urls.list, { mode: 'topbar', limit: topbarLimit, filter: 'all' })
    .then(function (data) {
      updateBadge(data.unread || 0);
      renderItems(data.items || []);
      lastFetchAt = Date.now();
    })
    .catch(function () {
      updateBadge(0);
    });
})();
