<?php
/**
 * IQS FRAMEWORK CORE FILE
 *
 * READ ONLY for downstream project programmers.
 * Do not modify this file directly in template or cloned projects.
 * Custom changes must be implemented in project-specific files
 * or approved extension points.
 */
declare(strict_types=1);

require_once __DIR__ . '/../includes/init.php';
require_login();

require_once __DIR__ . '/../controllers/AuditCenterController.php';
require_once __DIR__ . '/../setting/helper/alert_helper.php';

if (!function_exists('h')) {
    function h($value): string
    {
        return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
    }
}

function ac(string $key, string $fallback): string
{
    $value = __('audit_center_' . $key);
    return ($value === 'audit_center_' . $key || $value === null || $value === '') ? $fallback : (string)$value;
}

$controller = new AuditCenterController();
if (!$controller->isSuperAdmin()) {
    set_alert([
        'title' => 'audit_center_access_denied_title',
        'text' => 'audit_center_access_denied_text',
        'icon' => 'error',
        'confirm' => true,
        'is_key' => true,
    ]);
    header('Location: ' . base_path(app_config('site.default_home', 'pages/dashboard.php')));
    exit;
}

$tab = strtolower(trim((string)($_GET['tab'] ?? 'events')));
$allowedTabs = ['events', 'requests', 'sessions', 'changes', 'security'];
if (!in_array($tab, $allowedTabs, true)) {
    $tab = 'events';
}

$search = trim((string)($_GET['q'] ?? ''));
$limit = (int)($_GET['limit'] ?? 10);
$limit = max(5, min(100, $limit > 0 ? $limit : 10));
$filters = $controller->normalizeFilters($_GET);
$hasAdvancedFilters = false;
foreach ($filters as $filterValue) {
    if (trim((string)$filterValue) !== '') {
        $hasAdvancedFilters = true;
        break;
    }
}

$PAGE_TITLE = ac('page_title', 'Audit Center');
$lang = $_SESSION['lang'] ?? 'ms';
$version = (string)($_ENV['APP_ASSET_VER'] ?? date('ymdHis'));
$defaultHome = (string)app_config('site.default_home', 'pages/dashboard.php');
$tabMeta = [
    'events' => ['icon' => 'ri-file-list-3-line', 'title' => ac('tab_events', 'Events')],
    'requests' => ['icon' => 'ri-route-line', 'title' => ac('tab_requests', 'Requests')],
    'sessions' => ['icon' => 'ri-fingerprint-line', 'title' => ac('tab_sessions', 'Sessions')],
    'changes' => ['icon' => 'ri-git-commit-line', 'title' => ac('tab_changes', 'Changes')],
    'security' => ['icon' => 'ri-shield-keyhole-line', 'title' => ac('tab_security', 'Security')],
];
?>
<!DOCTYPE html>
<html lang="<?= h($lang) ?>" data-bs-theme="<?= h($_SESSION['theme.layout'] ?? 'light') ?>">
<head>
  <?php
    $NEED_DATERANGE = false;
    $NEED_VECTORMAP = false;
    $NEED_DATATABLES = false;
    $NEED_SELECT2 = false;
    include __DIR__ . '/../includes/head.php';
  ?>
  <meta name="referrer" content="no-referrer">
  <link href="<?= base_url('assets/css/pages/audit-center.css') ?>?v=<?= h($version) ?>" rel="stylesheet">
</head>
<body data-topbar-color="<?= h($_SESSION['theme.topbar'] ?? 'light') ?>" data-menu-color="<?= h($_SESSION['theme.menu'] ?? $_SESSION['theme.sidebar'] ?? 'dark') ?>" data-layout="vertical" data-sidebar-size="default" class="loading">
<div class="wrapper audit-center-page">
  <?php include __DIR__ . '/../includes/topbar.php'; ?>
  <?php include __DIR__ . '/../includes/sidebar.php'; ?>

  <div class="content-page">
    <div class="content">
      <div class="container-fluid">
        <div class="row mb-3">
          <div class="col-12">
            <div class="page-title-box d-flex justify-content-between align-items-center flex-wrap">
              <h4 class="page-title"><i class="ri-shield-star-line me-1"></i><?= h(ac('page_title', 'Audit Center')) ?></h4>
              <div class="page-title-right">
                <ol class="breadcrumb m-0">
                  <li class="breadcrumb-item"><a href="<?= base_path($defaultHome) ?>"><?= h(ac('breadcrumb_dashboard', 'Dashboard')) ?></a></li>
                  <li class="breadcrumb-item active"><?= h(ac('page_title', 'Audit Center')) ?></li>
                </ol>
              </div>
            </div>
          </div>
        </div>

        <section class="audit-center-command mb-4">
          <div class="audit-center-command__left">
            <span class="audit-center-command__eyebrow"><?= h(ac('hero_eyebrow', 'Live View')) ?></span>
            <h3 class="audit-center-command__title"><?= h(ac('hero_title', 'Operational Audit Workspace')) ?></h3>
            <p class="audit-center-command__text"><?= h(ac('hero_text', 'Navigasi, carian, had rekod, dan pagination semuanya berjalan melalui AJAX supaya semakan audit lebih lancar dan cepat.')) ?></p>
          </div>
          <div class="audit-center-command__right">
            <div class="audit-center-command__chip"><i class="ri-flashlight-line"></i> <?= h(ac('chip_ajax', 'AJAX Driven')) ?></div>
            <div class="audit-center-command__chip"><i class="ri-pulse-line"></i> <?= h(ac('chip_live_paging', 'Live Paging')) ?></div>
            <div class="audit-center-command__chip"><i class="ri-shield-user-line"></i> <?= h(ac('chip_super_admin', 'Super Admin')) ?></div>
          </div>
        </section>

        <div class="audit-center-summary mb-4" id="audit-center-summary">
          <div class="audit-center-loading-card">
            <div class="audit-center-spinner"></div>
            <div><?= h(ac('loading_summary', 'Memuatkan ringkasan audit...')) ?></div>
          </div>
        </div>

        <div class="audit-center-shell">
          <aside class="audit-center-nav">
            <div class="audit-center-nav__title"><?= h(ac('nav_title', 'Audit Views')) ?></div>
            <div class="nav flex-column nav-pills" id="audit-center-nav">
              <?php foreach ($tabMeta as $key => $meta): ?>
                <button type="button" class="nav-link <?= $tab === $key ? 'active' : '' ?>" data-audit-tab="<?= h($key) ?>">
                  <i class="<?= h($meta['icon']) ?>"></i>
                  <span><?= h($meta['title']) ?></span>
                </button>
              <?php endforeach; ?>
            </div>
          </aside>

          <section class="audit-center-panel">
            <div class="audit-center-panel__body" id="audit-center-panel-body">
              <div class="audit-center-loading-card audit-center-loading-card--panel">
                <div class="audit-center-spinner"></div>
                <div><?= h(ac('loading_panel', 'Memuatkan data audit...')) ?></div>
              </div>
            </div>
          </section>
        </div>
      </div>
    </div>
    <?php include __DIR__ . '/../includes/footer.php'; ?>
  </div>
</div>

<?php include __DIR__ . '/../includes/script.php'; ?>
<script>
(function () {
  if (!window.jQuery) { return; }
  const $ = window.jQuery;
  const endpoint = <?= json_encode(base_url('ajax/audit-center-panel.php'), JSON_UNESCAPED_UNICODE) ?>;
  const exportEndpoint = <?= json_encode(base_url('ajax/audit-center-export.php'), JSON_UNESCAPED_UNICODE) ?>;
  const actionEndpoint = <?= json_encode(base_url('ajax/audit-center-action.php'), JSON_UNESCAPED_UNICODE) ?>;
  const metaEndpoint = <?= json_encode(base_url('ajax/audit-center-meta.php'), JSON_UNESCAPED_UNICODE) ?>;
  const state = {
    tab: <?= json_encode($tab, JSON_UNESCAPED_UNICODE) ?>,
    security_subtab: <?= json_encode((string)($_GET['security_subtab'] ?? 'events'), JSON_UNESCAPED_UNICODE) ?>,
    q: <?= json_encode($search, JSON_UNESCAPED_UNICODE) ?>,
    limit: <?= (int)$limit ?>,
    date_from: <?= json_encode($filters['date_from'] ?? '', JSON_UNESCAPED_UNICODE) ?>,
    date_to: <?= json_encode($filters['date_to'] ?? '', JSON_UNESCAPED_UNICODE) ?>,
    login_id: <?= json_encode($filters['login_id'] ?? '', JSON_UNESCAPED_UNICODE) ?>,
    actor: <?= json_encode($filters['actor'] ?? '', JSON_UNESCAPED_UNICODE) ?>,
    ip: <?= json_encode($filters['ip'] ?? '', JSON_UNESCAPED_UNICODE) ?>,
    event_type: <?= json_encode($filters['event_type'] ?? '', JSON_UNESCAPED_UNICODE) ?>,
    outcome: <?= json_encode($filters['outcome'] ?? '', JSON_UNESCAPED_UNICODE) ?>,
    severity: <?= json_encode($filters['severity'] ?? '', JSON_UNESCAPED_UNICODE) ?>,
    target_type: <?= json_encode($filters['target_type'] ?? '', JSON_UNESCAPED_UNICODE) ?>,
    route: <?= json_encode($filters['route'] ?? '', JSON_UNESCAPED_UNICODE) ?>,
    method: <?= json_encode($filters['method'] ?? '', JSON_UNESCAPED_UNICODE) ?>,
    status_code: <?= json_encode($filters['status_code'] ?? '', JSON_UNESCAPED_UNICODE) ?>,
    session_id: <?= json_encode($filters['session_id'] ?? '', JSON_UNESCAPED_UNICODE) ?>,
    change_reason: <?= json_encode($filters['change_reason'] ?? '', JSON_UNESCAPED_UNICODE) ?>,
    scope_type: <?= json_encode($filters['scope_type'] ?? '', JSON_UNESCAPED_UNICODE) ?>,
    advanced_open: <?= $hasAdvancedFilters ? 'true' : 'false' ?>,
    page: <?= (int)($_GET['page'] ?? 1) ?>,
    security_events_page: <?= (int)($_GET['security_events_page'] ?? 1) ?>,
    security_lockouts_page: <?= (int)($_GET['security_lockouts_page'] ?? 1) ?>,
    security_throttles_page: <?= (int)($_GET['security_throttles_page'] ?? 1) ?>,
  };

  const summaryEl = document.getElementById('audit-center-summary');
  const panelEl = document.getElementById('audit-center-panel-body');
  const navEl = document.getElementById('audit-center-nav');
  const i18n = {
    loadingPanel: <?= json_encode(ac('loading_panel', 'Memuatkan data audit...'), JSON_UNESCAPED_UNICODE) ?>,
    loadFailed: <?= json_encode(ac('load_failed', 'Gagal memuatkan data Audit Center. Sila cuba semula.'), JSON_UNESCAPED_UNICODE) ?>,
    confirmButton: <?= json_encode(ac('action_confirm_button', 'Teruskan'), JSON_UNESCAPED_UNICODE) ?>,
    cancelButton: <?= json_encode(ac('action_cancel_button', 'Batal'), JSON_UNESCAPED_UNICODE) ?>,
    actionFailed: <?= json_encode(ac('action_server_error', 'Ralat sistem semasa memproses tindakan Audit Center.'), JSON_UNESCAPED_UNICODE) ?>,
    actionClearLockoutTitle: <?= json_encode(ac('action_clear_lockout_title', 'Clear lockout ini?'), JSON_UNESCAPED_UNICODE) ?>,
    actionClearLockoutText: <?= json_encode(ac('action_clear_lockout_text', 'Lockout aktif untuk login ID ini akan dibersihkan serta-merta.'), JSON_UNESCAPED_UNICODE) ?>,
    actionClearThrottleTitle: <?= json_encode(ac('action_clear_throttle_title', 'Clear throttle ini?'), JSON_UNESCAPED_UNICODE) ?>,
    actionClearThrottleText: <?= json_encode(ac('action_clear_throttle_text', 'Throttle aktif untuk scope ini akan dibersihkan serta-merta.'), JSON_UNESCAPED_UNICODE) ?>,
    actionTerminateSessionTitle: <?= json_encode(ac('action_terminate_session_title', 'Tamatkan sesi ini?'), JSON_UNESCAPED_UNICODE) ?>,
    actionTerminateSessionText: <?= json_encode(ac('action_terminate_session_text', 'Sesi audit ini akan ditutup serta-merta dan pengguna perlu log masuk semula jika masih aktif.'), JSON_UNESCAPED_UNICODE) ?>,
    actionSuccessTitle: <?= json_encode(ac('action_success_title', 'Tindakan Berjaya'), JSON_UNESCAPED_UNICODE) ?>,
    actionErrorTitle: <?= json_encode(ac('action_error_title', 'Tindakan Gagal'), JSON_UNESCAPED_UNICODE) ?>,
    okButton: <?= json_encode(ac('action_ok_button', 'OK'), JSON_UNESCAPED_UNICODE) ?>,
    metaLoadingTitle: <?= json_encode(ac('meta_loading_title', 'Memuatkan Metadata'), JSON_UNESCAPED_UNICODE) ?>,
    metaLoadingText: <?= json_encode(ac('meta_loading_text', 'Sila tunggu sebentar sementara metadata audit dimuatkan.'), JSON_UNESCAPED_UNICODE) ?>,
    metaRawRecord: <?= json_encode(ac('meta_section_raw_record', 'Raw Record'), JSON_UNESCAPED_UNICODE) ?>,
    metaEmpty: <?= json_encode(ac('meta_empty', 'Tiada metadata tambahan direkodkan untuk item ini.'), JSON_UNESCAPED_UNICODE) ?>,
    metaSection: <?= json_encode(ac('meta_section_fallback', 'Section'), JSON_UNESCAPED_UNICODE) ?>,
    metaCopy: <?= json_encode(ac('meta_copy_button', 'Copy JSON'), JSON_UNESCAPED_UNICODE) ?>,
    metaCopied: <?= json_encode(ac('meta_copy_success', 'JSON copied'), JSON_UNESCAPED_UNICODE) ?>,
  };
  let lastSuccessfulPanelHtml = '';
  let loading = false;
  let searchTimer = null;
  let auditCenterLoaderToken = null;
  let panelAbortController = null;
  let panelRequestSequence = 0;

  function showAuditCenterLoader(message) {
    if (auditCenterLoaderToken) return;
    const loaderMessage = message || i18n.loadingPanel;
    if (window.AppLoader && typeof window.AppLoader.show === 'function') {
      auditCenterLoaderToken = window.AppLoader.show(loaderMessage, { timeout: 60000 });
    } else if (window.IQSLoader && typeof window.IQSLoader.show === 'function') {
      auditCenterLoaderToken = window.IQSLoader.show(loaderMessage, { timeout: 60000 });
    }
  }

  function hideAuditCenterLoader() {
    if (!auditCenterLoaderToken) return;
    if (window.AppLoader && typeof window.AppLoader.hide === 'function') {
      window.AppLoader.hide(auditCenterLoaderToken);
    } else if (window.IQSLoader && typeof window.IQSLoader.hide === 'function') {
      window.IQSLoader.hide(auditCenterLoaderToken);
    }
    auditCenterLoaderToken = null;
  }

  function setLoading(flag, mode) {
    if (summaryEl) {
      summaryEl.classList.toggle('audit-center-summary--loading', flag);
    }
    if (panelEl) {
      panelEl.classList.toggle('audit-center-panel__body--loading', flag);
      panelEl.setAttribute('aria-busy', flag ? 'true' : 'false');
    }
  }

  function updateNav() {
    if (!navEl) return;
    navEl.querySelectorAll('[data-audit-tab]').forEach((link) => {
      link.classList.toggle('active', link.getAttribute('data-audit-tab') === state.tab);
    });
  }

  function buildParams() {
    const params = new URLSearchParams();
    params.set('tab', state.tab);
    params.set('security_subtab', state.security_subtab || 'events');
    params.set('q', state.q || '');
    params.set('limit', String(state.limit || 10));
    ['date_from','date_to','login_id','actor','ip','event_type','outcome','severity','target_type','route','method','status_code','session_id','change_reason','scope_type'].forEach(function (key) {
      params.set(key, state[key] || '');
    });
    params.set('advanced_open', state.advanced_open ? '1' : '0');
    params.set('page', String(state.page || 1));
    params.set('security_events_page', String(state.security_events_page || 1));
    params.set('security_lockouts_page', String(state.security_lockouts_page || 1));
    params.set('security_throttles_page', String(state.security_throttles_page || 1));
    return params;
  }

  function triggerExport(format) {
    const params = buildParams();
    params.set('format', format || 'csv');
    params.set('export_limit', '2000');
    const anchor = document.createElement('a');
    anchor.href = exportEndpoint + '?' + params.toString();
    anchor.style.display = 'none';
    document.body.appendChild(anchor);
    anchor.click();
    anchor.remove();
  }

  function escapeHtml(value) {
    return String(value == null ? '' : value)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#039;');
  }

  function prettyJson(value) {
    try {
      return JSON.stringify(value, null, 2);
    } catch (err) {
      return String(value == null ? '' : value);
    }
  }

  function getMetaModal() {
    const modalEl = document.getElementById('audit-center-meta-modal');
    if (!modalEl || !window.bootstrap) {
      return null;
    }
    return bootstrap.Modal.getOrCreateInstance(modalEl, { backdrop: true, keyboard: true, focus: true });
  }

  function renderMetaSections(data) {
    const titleEl = document.getElementById('audit-center-meta-title');
    const subtitleEl = document.getElementById('audit-center-meta-subtitle');
    const bodyEl = document.getElementById('audit-center-meta-body');
    if (!bodyEl) return;

    if (titleEl) titleEl.textContent = data.title || '';
    if (subtitleEl) subtitleEl.textContent = data.subtitle || '';

    const tabs = [];

    const sections = Array.isArray(data.sections) ? data.sections : [];
    sections.forEach(function (section, index) {
      tabs.push({
        key: 'section-' + (index + 1),
        label: section.label || (i18n.metaSection + ' ' + (index + 1)),
        content: '<pre class="audit-center-meta-pre">' + escapeHtml(prettyJson(section.data)) + '</pre>'
      });
    });

    tabs.push({
      key: 'raw-record',
      label: i18n.metaRawRecord,
      content: '<pre class="audit-center-meta-pre">' + escapeHtml(prettyJson(data.record || {})) + '</pre>'
    });

    if (tabs.length === 1 && sections.length === 0) {
      tabs[0].content += '<div class="audit-center-meta-empty">' + escapeHtml(i18n.metaEmpty) + '</div>';
    }

    const navParts = [];
    const paneParts = [];
    tabs.forEach(function (tab, index) {
      const isActive = index === 0;
      const tabId = 'audit-center-meta-tab-' + tab.key;
      const paneId = 'audit-center-meta-pane-' + tab.key;
      navParts.push(
        '<li class="nav-item" role="presentation">' +
        '<button class="nav-link' + (isActive ? ' active' : '') + '" id="' + escapeHtml(tabId) + '" data-bs-toggle="tab" data-bs-target="#' + escapeHtml(paneId) + '" type="button" role="tab" aria-controls="' + escapeHtml(paneId) + '" aria-selected="' + (isActive ? 'true' : 'false') + '">' +
        escapeHtml(tab.label) +
        '</button>' +
        '</li>'
      );
      paneParts.push(
        '<div class="tab-pane fade' + (isActive ? ' show active' : '') + '" id="' + escapeHtml(paneId) + '" role="tabpanel" aria-labelledby="' + escapeHtml(tabId) + '">' +
        '<section class="audit-center-meta-section"><div class="audit-center-meta-section__toolbar"><span>' + escapeHtml(tab.label) + '</span><button type="button" class="btn btn-sm btn-outline-secondary" data-audit-copy-json><i class="ri-file-copy-line me-1"></i>' + escapeHtml(i18n.metaCopy) + '</button></div>' + tab.content + '</section>' +
        '</div>'
      );
    });

    bodyEl.innerHTML =
      '<ul class="nav nav-tabs audit-center-meta-tabs" role="tablist">' + navParts.join('') + '</ul>' +
      '<div class="tab-content audit-center-meta-tab-content">' + paneParts.join('') + '</div>';
  }

  async function confirmAction(title, text) {
    if (window.Swal && typeof window.Swal.fire === 'function') {
      const result = await window.Swal.fire({
        icon: 'warning',
        title: title,
        text: text,
        showCancelButton: true,
        confirmButtonText: i18n.confirmButton,
        cancelButtonText: i18n.cancelButton,
      });
      return !!(result && result.isConfirmed);
    }
    return window.confirm(title + '\n\n' + text);
  }

  function syncHistory(usePush) {
    const params = buildParams();
    const method = usePush ? 'pushState' : 'replaceState';
    history[method](Object.assign({}, state), '', '<?= base_path('pages/audit-center.php') ?>?' + params.toString());
  }

  async function handleTerminatedSession(data) {
    if (!data || data.session_terminated !== true) {
      return false;
    }

    const title = data.title || i18n.actionErrorTitle;
    const text = data.message || i18n.actionFailed;

    if (window.Swal && typeof window.Swal.fire === 'function') {
      await window.Swal.fire({
        icon: 'warning',
        title: title,
        text: text,
        confirmButtonText: i18n.okButton
      });
    }

    window.location.href = '<?= base_path('index.php') ?>';
    return true;
  }

  async function loadPanel(pushHistory = true, mode = 'default') {
    if (panelAbortController) panelAbortController.abort();
    panelAbortController = new AbortController();
    const requestSequence = ++panelRequestSequence;
    loading = true;
    setLoading(true, mode);
    try {
      const response = await fetch(endpoint + '?' + buildParams().toString(), {
        credentials: 'same-origin',
        noLoader: true,
        headers: { 'X-Requested-With': 'XMLHttpRequest', 'X-No-Loader': '1' },
        cache: 'no-store',
        signal: panelAbortController.signal
      });
      const data = await response.json();
      if (requestSequence !== panelRequestSequence) return;
      if (await handleTerminatedSession(data)) {
        return;
      }
      if (!response.ok || !data || data.ok !== true) {
        throw new Error((data && data.message) || 'Load failed');
      }

      summaryEl.innerHTML = data.summary_html || '';
      panelEl.innerHTML = data.panel_html || '';
      lastSuccessfulPanelHtml = data.panel_html || '';
      Object.assign(state, data.state || {});
      updateNav();
      syncHistory(pushHistory);
    } catch (error) {
      if (error && error.name === 'AbortError') return;
      if (lastSuccessfulPanelHtml) {
        panelEl.innerHTML = lastSuccessfulPanelHtml;
        const tableWrap = panelEl.querySelector('.audit-center-table-wrap');
        if (tableWrap) {
          tableWrap.innerHTML = '<div class="audit-center-empty"><i class="ri-error-warning-line"></i><div>' + i18n.loadFailed + '</div></div>';
        }
      } else {
        panelEl.innerHTML = '<div class="audit-center-empty"><i class="ri-error-warning-line"></i><div>' + i18n.loadFailed + '</div></div>';
      }
    } finally {
      if (requestSequence === panelRequestSequence) {
        loading = false;
        panelAbortController = null;
        setLoading(false, mode);
      }
    }
  }

  $(document).on('click', '[data-audit-tab]', function (event) {
    event.preventDefault();
    event.stopPropagation();
    const tabLink = event.currentTarget;
    if (tabLink) {
      state.tab = tabLink.getAttribute('data-audit-tab') || 'events';
      state.page = 1;
      state.security_events_page = 1;
      state.security_lockouts_page = 1;
      state.security_throttles_page = 1;
      state.security_subtab = 'events';
      loadPanel(true, 'tab');
    }
    return false;
  });

  $(document).on('click', '.audit-center-page-btn', function (event) {
    event.preventDefault();
    event.stopPropagation();
    const pageButton = event.currentTarget;
    if (pageButton) {
      const scope = pageButton.getAttribute('data-scope');
      const nextPage = parseInt(pageButton.getAttribute('data-page') || '1', 10) || 1;
      if (scope) {
        state[scope] = nextPage;
        if (scope !== 'page' && state.tab !== 'security') {
          state.page = nextPage;
        }
        loadPanel(true, 'pagination');
      }
    }
    return false;
  });

  $(document).on('click', '[data-audit-security-tab]', function (event) {
    event.preventDefault();
    event.stopPropagation();
    const subtab = this.getAttribute('data-audit-security-tab') || 'events';
    state.security_subtab = subtab;
    loadPanel(true, 'security-tab');
    return false;
  });

  $(document).on('click', '#audit-center-reset', function (event) {
    event.preventDefault();
    event.stopPropagation();
    const resetButton = event.currentTarget;
    if (resetButton) {
      state.q = '';
      state.limit = 10;
      ['date_from','date_to','login_id','actor','ip','event_type','outcome','severity','target_type','route','method','status_code','session_id','change_reason','scope_type'].forEach(function (key) {
        state[key] = '';
      });
      state.advanced_open = false;
      state.page = 1;
      state.security_events_page = 1;
      state.security_lockouts_page = 1;
      state.security_throttles_page = 1;
      loadPanel(true, 'reset');
    }
    return false;
  });

  $(document).on('input', '#audit-center-q', function () {
    const searchInput = this;
    if (searchTimer) {
      clearTimeout(searchTimer);
    }
    searchTimer = setTimeout(function () {
      state.q = searchInput ? searchInput.value.trim() : '';
      state.page = 1;
      state.security_events_page = 1;
      state.security_lockouts_page = 1;
      state.security_throttles_page = 1;
      loadPanel(true, 'search');
    }, 250);
  });

  $(document).on('change', '#audit-center-limit', function () {
    const limitInput = this;
      state.limit = limitInput ? parseInt(limitInput.value || '10', 10) || 10 : 10;
    state.page = 1;
    state.security_events_page = 1;
    state.security_lockouts_page = 1;
    state.security_throttles_page = 1;
    loadPanel(true, 'limit');
  });

  $(document).on('click', '#audit-center-advanced-toggle', function (event) {
    event.preventDefault();
    state.advanced_open = !state.advanced_open;
    const panel = document.getElementById('audit-center-advanced-panel');
    const button = document.getElementById('audit-center-advanced-toggle');
    if (panel) {
      panel.classList.toggle('d-none', !state.advanced_open);
    }
    if (button) {
      button.setAttribute('aria-expanded', state.advanced_open ? 'true' : 'false');
      button.classList.toggle('active', state.advanced_open);
    }
    syncHistory(false);
    return false;
  });

  $(document).on('click', '#audit-center-apply-filters', function (event) {
    event.preventDefault();
    ['date_from','date_to','login_id','actor','ip','event_type','outcome','severity','target_type','route','method','status_code','session_id','change_reason','scope_type'].forEach(function (key) {
      const input = document.querySelector('[data-audit-filter-key="' + key + '"]');
      state[key] = input ? (input.value || '').trim() : '';
    });
    state.advanced_open = true;
    state.page = 1;
    state.security_events_page = 1;
    state.security_lockouts_page = 1;
    state.security_throttles_page = 1;
    loadPanel(true, 'filter');
    return false;
  });

  $(document).on('click', '[data-audit-export-format]', function (event) {
    event.preventDefault();
    const format = (event.currentTarget && event.currentTarget.getAttribute('data-audit-export-format')) || 'csv';
    triggerExport(format);
    return false;
  });

  $(document).on('click', '[data-audit-action]', async function (event) {
    event.preventDefault();
    const button = event.currentTarget;
    if (!button) return false;

    const action = button.getAttribute('data-audit-action') || '';
    const payload = { action: action };
    let title = '';
    let text = '';

    if (action === 'clear_lockout') {
      payload.login_id = button.getAttribute('data-login-id') || '';
      title = i18n.actionClearLockoutTitle;
      text = i18n.actionClearLockoutText;
    } else if (action === 'clear_throttle') {
      payload.scope_type = button.getAttribute('data-scope-type') || '';
      payload.scope_key = button.getAttribute('data-scope-key') || '';
      title = i18n.actionClearThrottleTitle;
      text = i18n.actionClearThrottleText;
    } else if (action === 'terminate_session') {
      payload.session_id = button.getAttribute('data-session-id') || '';
      title = i18n.actionTerminateSessionTitle;
      text = i18n.actionTerminateSessionText;
    } else {
      return false;
    }

    const confirmed = await confirmAction(title, text);
    if (!confirmed) return false;

    button.disabled = true;
    showAuditCenterLoader(i18n.loadingPanel);
    try {
      const response = await fetch(actionEndpoint, {
        method: 'POST',
        credentials: 'same-origin',
        noLoader: true,
        headers: {
          'Content-Type': 'application/json',
          'Accept': 'application/json',
          'X-CSRF-Token': window.csrfToken || '',
          'X-No-Loader': '1'
        },
        body: JSON.stringify(payload)
      });
      const data = await response.json();
      if (await handleTerminatedSession(data)) {
        return false;
      }
      if (!response.ok || !data || data.success !== true) {
        throw new Error((data && data.message) || i18n.actionFailed);
      }

      hideAuditCenterLoader();
      if (window.Swal && typeof window.Swal.fire === 'function') {
        await window.Swal.fire({
          icon: 'success',
          title: i18n.actionSuccessTitle,
          text: data.message || '',
          confirmButtonText: i18n.okButton
        });
      }
      await loadPanel(false, 'action');
    } catch (error) {
      hideAuditCenterLoader();
      if (window.Swal && typeof window.Swal.fire === 'function') {
        await window.Swal.fire({
          icon: 'error',
          title: i18n.actionErrorTitle,
          text: (error && error.message) ? error.message : i18n.actionFailed,
          confirmButtonText: i18n.okButton
        });
      }
    } finally {
      button.disabled = false;
      hideAuditCenterLoader();
    }
    return false;
  });

  $(document).on('click', '[data-audit-meta-kind]', async function (event) {
    event.preventDefault();
    const button = event.currentTarget;
    if (!button) return false;

    const params = new URLSearchParams();
    params.set('kind', button.getAttribute('data-audit-meta-kind') || '');
    ['id', 'request-id', 'session-id', 'change-set-id', 'login-id', 'scope-type', 'scope-key'].forEach(function (key) {
      const attr = button.getAttribute('data-' + key);
      if (!attr) return;
      params.set(key.replace(/-/g, '_'), attr);
    });

    showAuditCenterLoader(i18n.metaLoadingText || i18n.metaLoadingTitle);

    try {
      const response = await fetch(metaEndpoint + '?' + params.toString(), {
        credentials: 'same-origin',
        noLoader: true,
        headers: { 'X-Requested-With': 'XMLHttpRequest', 'X-No-Loader': '1' },
        cache: 'no-store'
      });
      const data = await response.json();
      hideAuditCenterLoader();
      if (await handleTerminatedSession(data)) {
        return false;
      }
      if (!response.ok || !data || data.success !== true) {
        throw new Error((data && data.message) || i18n.actionFailed);
      }

      renderMetaSections(data);
      const modal = getMetaModal();
      if (modal) {
        modal.show();
      }
    } catch (error) {
      hideAuditCenterLoader();
      if (window.Swal && typeof window.Swal.fire === 'function') {
        await window.Swal.fire({
          icon: 'error',
          title: i18n.actionErrorTitle,
          text: (error && error.message) ? error.message : i18n.actionFailed,
          confirmButtonText: i18n.okButton
        });
      }
    }

    return false;
  });

  $(document).on('click', '[data-audit-jump-tab]', function (event) {
    event.preventDefault();
    const button = event.currentTarget;
    const targetTab = button.getAttribute('data-audit-jump-tab') || 'events';
    const targetField = button.getAttribute('data-audit-jump-field') || 'q';
    const targetValue = button.getAttribute('data-audit-jump-value') || '';
    state.tab = targetTab;
    state.page = 1;
    state.security_events_page = 1;
    state.security_lockouts_page = 1;
    state.security_throttles_page = 1;
    if (targetTab === 'security') {
      state.security_subtab = 'events';
    }
    if (targetField === 'q') {
      state.q = targetValue;
    } else {
      state[targetField] = targetValue;
    }
    loadPanel(true, 'jump');
    return false;
  });

  $(document).on('click', '[data-audit-copy-json]', async function (event) {
    event.preventDefault();
    const pre = event.currentTarget.closest('.audit-center-meta-section')?.querySelector('.audit-center-meta-pre');
    if (!pre || !navigator.clipboard) return false;
    await navigator.clipboard.writeText(pre.textContent || '');
    if (window.Swal) window.Swal.fire({ toast: true, position: 'top-end', timer: 1300, showConfirmButton: false, icon: 'success', title: i18n.metaCopied });
    return false;
  });

  $(document).on('submit', '#audit-center-filter-form', function (event) {
    event.preventDefault();
    event.stopPropagation();
    return false;
  });

  $(document).on('click', '.audit-center-expand-btn', function (event) {
    event.preventDefault();
    const targetId = this.getAttribute('data-target');
    if (!targetId) return false;
    const row = document.getElementById(targetId);
    if (!row) return false;
    const expanded = !row.classList.contains('d-none');
    row.classList.toggle('d-none', expanded);
    this.setAttribute('aria-expanded', expanded ? 'false' : 'true');
    this.textContent = expanded ? '+' : '−';
    return false;
  });

  window.addEventListener('popstate', function (event) {
    if (event.state) {
      Object.assign(state, event.state);
      loadPanel(false, 'history');
    }
  });

  loadPanel(false, 'initial');
})();
</script>

<div class="modal fade" id="audit-center-meta-modal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-xl modal-dialog-scrollable">
    <div class="modal-content audit-center-meta-modal">
      <div class="modal-header">
        <div>
          <h5 class="modal-title" id="audit-center-meta-title"><?= h(ac('meta_modal_title', 'Audit Metadata Viewer')) ?></h5>
          <div class="audit-center-meta-modal__subtitle" id="audit-center-meta-subtitle"></div>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?= h(ac('meta_close_button', 'Close')) ?>"></button>
      </div>
      <div class="modal-body" id="audit-center-meta-body"></div>
      <div class="modal-footer">
        <button type="button" class="btn btn-light audit-center-meta-close-btn" data-bs-dismiss="modal"><?= h(ac('meta_close_button', 'Tutup')) ?></button>
      </div>
    </div>
  </div>
</div>
</body>
</html>
