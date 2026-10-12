(function () {
  'use strict';

  var config = window.notificationPageConfig || {};
  var endpoints = config.endpoints || {};
  var labels = config.labels || {};
  var elements = {
    wrap: document.getElementById('notificationPageListWrap'), list: document.getElementById('notificationPageList'),
    readAll: document.getElementById('notificationPageReadAll'), search: document.getElementById('notificationSearch'),
    filters: Array.prototype.slice.call(document.querySelectorAll('.notification-filter')),
    pagination: document.getElementById('notificationPagination'), summary: document.getElementById('notificationPageSummary'),
    pageNumber: document.getElementById('notificationPageNumber'), previous: document.getElementById('notificationPrevious'), next: document.getElementById('notificationNext')
  };
  if (!elements.wrap || !elements.list) return;

  var params = new URLSearchParams(window.location.search);
  var allowedFilters = ['all', 'unread', 'read', 'action_required', 'overdue'];
  var initialFilter = params.get('notification_filter') || 'all';
  var state = { filter: allowedFilters.indexOf(initialFilter) >= 0 ? initialFilter : 'all', search: (params.get('notification_search') || '').slice(0, 100), page: Math.max(1, parseInt(params.get('notification_page') || '1', 10) || 1), perPage: 20 };
  var activeController = null;
  var requestSequence = 0;
  var searchTimer = null;
  var csrfToken = window.csrfToken || (document.querySelector('meta[name="csrf-token"]') || {}).content || '';
  var baseUrl = String(window.BASE_URL || '').replace(/\/+$/, '');

  function escapeHtml(value) { var node = document.createElement('div'); node.textContent = value == null ? '' : String(value); return node.innerHTML; }
  function replaceTokens(template, values) { return String(template || '').replace(/\{(\w+)\}/g, function (_, key) { return Object.prototype.hasOwnProperty.call(values, key) ? values[key] : ''; }); }
  function validIcon(value) { value = String(value || ''); return /^ri-[a-z0-9-]+$/.test(value) ? value : 'ri-notification-3-line'; }
  function safeUrl(value) {
    value = String(value || '').trim();
    if (!value || /^(https?:)?\/\//i.test(value) || /^(javascript|data):/i.test(value) || value.indexOf('..') >= 0 || value.indexOf('\\') >= 0 || /[\x00-\x1f\x7f]/.test(value)) return '';
    value = value.replace(/^\/+/, '');
    if (!/^[A-Za-z0-9_.\/-]+(?:\?[A-Za-z0-9_=&%+.,:@\/-]*)?(?:#[A-Za-z0-9_.-]*)?$/.test(value)) return '';
    return baseUrl ? baseUrl + '/' + value : value;
  }
  function postJson(url, payload, signal) {
    return fetch(url, { method: 'POST', headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-CSRF-Token': csrfToken, 'X-No-Loader': '1' }, credentials: 'same-origin', body: JSON.stringify(payload || {}), signal: signal }).then(function (response) {
      return response.text().then(function (raw) {
        var body = {}; try { body = raw ? JSON.parse(raw) : {}; } catch (ignore) {}
        if (!response.ok || body.success === false) throw new Error(body.message || body.error || labels.failed);
        return body.data || body;
      });
    });
  }
  function setLoading(active) { elements.wrap.classList.toggle('is-loading', active); elements.wrap.setAttribute('aria-busy', active ? 'true' : 'false'); }
  function updateUrl() {
    var url = new URL(window.location.href);
    state.filter === 'all' ? url.searchParams.delete('notification_filter') : url.searchParams.set('notification_filter', state.filter);
    state.search ? url.searchParams.set('notification_search', state.search) : url.searchParams.delete('notification_search');
    state.page > 1 ? url.searchParams.set('notification_page', String(state.page)) : url.searchParams.delete('notification_page');
    window.history.replaceState({}, '', url.pathname + url.search + url.hash);
  }
  function syncControls() {
    elements.filters.forEach(function (button) { var active = button.dataset.filter === state.filter; button.classList.toggle('is-active', active); button.setAttribute('aria-selected', active ? 'true' : 'false'); });
    if (elements.search && elements.search.value !== state.search) elements.search.value = state.search;
  }
  function updateBadge(unread) {
    var badge = document.getElementById('topbarNotificationBadge');
    if (!badge) return;
    badge.textContent = unread > 99 ? '99+' : String(unread || 0);
    badge.classList.toggle('d-none', !unread);
  }
  function updateKpis(summary) {
    var totals = {
      all: Number(summary.total || 0),
      unread: Number(summary.unread || 0),
      read: Math.max(0, Number(summary.total || 0) - Number(summary.unread || 0)),
      action_required: Number(summary.action_required || 0),
      overdue: Number(summary.overdue || 0)
    };
    Object.keys(totals).forEach(function (key) {
      var el = document.querySelector('[data-notification-filter-count="' + key + '"]');
      if (el) el.textContent = String(totals[key].toLocaleString());
    });
    if (elements.readAll) elements.readAll.disabled = Number(summary.unread || 0) === 0;
  }
  function severityClass(value) { value = String(value || '').toLowerCase(); return value === 'success' ? ' is-success' : (value === 'warning' ? ' is-warning' : (value === 'danger' || value === 'error' ? ' is-danger' : '')); }
  function render(items) {
    if (!Array.isArray(items) || !items.length) {
      elements.list.innerHTML = '<div class="notification-state"><div><span class="notification-state-icon"><i class="ri-inbox-2-line"></i></span><h3>' + escapeHtml(labels.emptyTitle) + '</h3><p>' + escapeHtml(labels.emptyText) + '</p></div></div>';
      return;
    }
    elements.list.innerHTML = items.map(function (item) {
      var unread = !Number(item.is_read);
      var actionUrl = safeUrl(item.action_url);
      var pending = Number(item.requires_action) && item.action_status === 'pending';
      var badge = pending ? '<span class="notification-badge' + (item.is_overdue ? ' is-overdue' : '') + '">' + escapeHtml(item.is_overdue ? labels.overdue : labels.action) + '</span>' : '';
      var due = item.due_label ? '<span class="' + (item.is_overdue ? 'text-danger' : '') + '"><i class="ri-calendar-event-line"></i> ' + escapeHtml(item.due_label) + '</span>' : '';
      var readButton = unread ? '<button type="button" data-mark-read="' + Number(item.id) + '" title="' + escapeHtml(labels.markRead) + '"><i class="ri-check-line"></i><span class="d-none d-lg-inline">' + escapeHtml(labels.markRead) + '</span></button>' : '';
      var actionButton = actionUrl ? '<a href="' + escapeHtml(actionUrl) + '" data-notification-action="' + Number(item.id) + '">' + escapeHtml(item.action_label || labels.open) + '<i class="ri-arrow-right-up-line"></i></a>' : '';
      return '<article class="notification-item' + (unread ? ' is-unread' : '') + '" data-id="' + Number(item.id) + '"><span class="notification-item-icon' + severityClass(item.severity) + '"><i class="' + validIcon(item.icon) + '"></i></span><div class="notification-item-main"><div class="notification-item-head"><div><div class="notification-item-title">' + escapeHtml(item.title || '-') + (unread ? '<span class="notification-unread-dot"></span>' : '') + '</div><p class="notification-item-body">' + escapeHtml(item.body || '') + '</p><div class="notification-meta"><span><i class="ri-time-line"></i> ' + escapeHtml(item.time_ago || '') + '</span>' + due + badge + '</div></div><div class="notification-actions">' + readButton + actionButton + '</div></div></div></article>';
    }).join('');
  }
  function renderError(error) {
    elements.list.innerHTML = '<div class="notification-state"><div><span class="notification-state-icon text-danger"><i class="ri-wifi-off-line"></i></span><h3>' + escapeHtml(labels.failedTitle) + '</h3><p>' + escapeHtml(error.message || labels.failed) + '</p><button type="button" class="btn btn-primary" data-notification-retry><i class="ri-refresh-line me-1"></i>' + escapeHtml(labels.retry) + '</button></div></div>';
    elements.pagination.hidden = true;
  }
  function updatePagination(pagination) {
    var total = Number(pagination.total || 0), page = Number(pagination.page || 1), pages = Number(pagination.total_pages || 1), perPage = Number(pagination.per_page || state.perPage);
    state.page = page;
    elements.pagination.hidden = total === 0;
    elements.previous.disabled = page <= 1; elements.next.disabled = page >= pages;
    elements.pageNumber.textContent = replaceTokens(labels.page, { page: page, pages: pages });
    elements.summary.textContent = replaceTokens(labels.results, { start: total ? ((page - 1) * perPage + 1) : 0, end: Math.min(page * perPage, total), total: total });
  }
  function load() {
    if (activeController) activeController.abort();
    activeController = new AbortController();
    var sequence = ++requestSequence;
    setLoading(true); syncControls(); updateUrl();
    return postJson(endpoints.list, { mode: 'page', limit: state.perPage, page: state.page, filter: state.filter, search: state.search }, activeController.signal).then(function (data) {
      if (sequence !== requestSequence) return;
      render(data.items || []); updateKpis(data.summary || {}); updatePagination(data.pagination || {}); updateBadge(Number(data.unread || 0));
    }).catch(function (error) {
      if (error.name !== 'AbortError' && sequence === requestSequence) renderError(error);
    }).finally(function () { if (sequence === requestSequence) setLoading(false); });
  }
  function toast(icon, message) {
    if (window.Swal) window.Swal.fire({ toast: true, position: 'top-end', icon: icon, title: message, showConfirmButton: false, timer: 2600, timerProgressBar: true });
  }
  function markRead(id, clicked) { return postJson(endpoints.read, { notification_id: Number(id), clicked: !!clicked }).then(function (data) { updateBadge(Number(data.unread || 0)); return data; }); }

  elements.filters.forEach(function (button) { button.addEventListener('click', function () { if (state.filter === button.dataset.filter) return; state.filter = button.dataset.filter || 'all'; state.page = 1; load(); }); });
  if (elements.search) elements.search.addEventListener('input', function () { window.clearTimeout(searchTimer); searchTimer = window.setTimeout(function () { state.search = elements.search.value.trim().slice(0, 100); state.page = 1; load(); }, 350); });
  elements.previous.addEventListener('click', function () { if (state.page > 1) { state.page -= 1; load(); } });
  elements.next.addEventListener('click', function () { state.page += 1; load(); });
  elements.list.addEventListener('click', function (event) {
    var retry = event.target.closest('[data-notification-retry]'); if (retry) { load(); return; }
    var read = event.target.closest('[data-mark-read]');
    if (read) { read.disabled = true; markRead(read.dataset.markRead, false).then(function () { toast('success', labels.readSuccess); load(); }).catch(function (error) { read.disabled = false; toast('error', error.message || labels.readFailed); }); return; }
    var action = event.target.closest('[data-notification-action]');
    if (!action) return;
    event.preventDefault(); var href = action.href;
    markRead(action.dataset.notificationAction, true).then(function () { window.location.assign(href); }).catch(function (error) { toast('error', error.message || labels.readFailed); window.setTimeout(function () { window.location.assign(href); }, 900); });
  });
  if (elements.readAll) elements.readAll.addEventListener('click', function () {
    var original = elements.readAll.innerHTML; elements.readAll.disabled = true; elements.readAll.innerHTML = '<span class="spinner-border spinner-border-sm"></span><span>' + escapeHtml(labels.readAllBusy) + '</span>';
    postJson(endpoints.readAll, {}).then(function () { toast('success', labels.readAllSuccess); state.page = 1; return load(); }).catch(function (error) { toast('error', error.message || labels.readAllFailed); }).finally(function () { elements.readAll.innerHTML = original; });
  });
  window.addEventListener('popstate', function () { var current = new URLSearchParams(window.location.search); var filter = current.get('notification_filter') || 'all'; state.filter = allowedFilters.indexOf(filter) >= 0 ? filter : 'all'; state.search = (current.get('notification_search') || '').slice(0, 100); state.page = Math.max(1, parseInt(current.get('notification_page') || '1', 10) || 1); load(); });
  syncControls(); load();
})();
