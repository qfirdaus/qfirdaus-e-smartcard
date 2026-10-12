<?php
/**
 * IQS FRAMEWORK CORE FILE
 *
 * READ ONLY for downstream project programmers.
 * Do not modify this file directly in template or cloned projects.
 * Custom changes must be implemented in project-specific files
 * or approved extension points.
 */declare(strict_types=1);

require_once __DIR__ . '/../includes/init.php';
require_login();
require_once __DIR__ . '/../controllers/AuditCenterController.php';
require_once __DIR__ . '/../setting/helper/access_helper.php';

header('Content-Type: application/json; charset=utf-8');

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

function fmt_dt($value): string
{
    if ($value === null || $value === '') {
        return '—';
    }
    $ts = strtotime((string)$value);
    return $ts ? date('d/m/Y h:i A', $ts) : (string)$value;
}

function fmt_num($value): string
{
    return number_format((int)$value);
}

function fmt_duration_ms($value): string
{
    if ($value === null || $value === '') {
        return '—';
    }
    $ms = (float)$value;
    return $ms >= 1000 ? number_format($ms / 1000, 2) . ' s' : number_format($ms, 0) . ' ms';
}

function fmt_duration_seconds($value): string
{
    if ($value === null || $value === '') {
        return '—';
    }
    $seconds = (int)$value;
    if ($seconds < 60) {
        return $seconds . ' s';
    }
    if ($seconds < 3600) {
        return floor($seconds / 60) . ' m';
    }
    return floor($seconds / 3600) . ' h ' . floor(($seconds % 3600) / 60) . ' m';
}

function audit_badge_class(string $value, array $success = [], array $danger = [], array $warning = [], array $info = []): string
{
    if (in_array($value, $success, true)) {
        return 'audit-center-badge audit-center-badge--success';
    }
    if (in_array($value, $danger, true)) {
        return 'audit-center-badge audit-center-badge--danger';
    }
    if (in_array($value, $warning, true)) {
        return 'audit-center-badge audit-center-badge--warning';
    }
    if (in_array($value, $info, true)) {
        return 'audit-center-badge audit-center-badge--info';
    }
    return 'audit-center-badge audit-center-badge--muted';
}

function render_pager(array $payload, string $scope): string
{
    $page = (int)($payload['page'] ?? 1);
    $pages = (int)($payload['pages'] ?? 1);
    $total = (int)($payload['total'] ?? 0);
    if ($pages <= 1 && $total <= 0) {
        return '';
    }

    $start = max(1, $page - 2);
    $end = min($pages, $start + 4);
    $start = max(1, $end - 4);

    ob_start();
    ?>
    <div class="audit-center-pager" data-scope="<?= h($scope) ?>">
      <div class="audit-center-pager__meta"><?= h(ac('pager_total', 'Total')) ?> <?= h(fmt_num($total)) ?> <?= h(ac('pager_records', 'rekod')) ?></div>
      <div class="audit-center-pager__controls">
        <button type="button" class="btn btn-sm btn-outline-secondary audit-center-page-btn" data-scope="<?= h($scope) ?>" data-page="<?= max(1, $page - 1) ?>" <?= $page <= 1 ? 'disabled' : '' ?>><?= h(ac('pager_prev', 'Prev')) ?></button>
        <?php for ($i = $start; $i <= $end; $i++): ?>
          <button type="button" class="btn btn-sm <?= $i === $page ? 'btn-primary' : 'btn-outline-secondary' ?> audit-center-page-btn" data-scope="<?= h($scope) ?>" data-page="<?= $i ?>"><?= $i ?></button>
        <?php endfor; ?>
        <button type="button" class="btn btn-sm btn-outline-secondary audit-center-page-btn" data-scope="<?= h($scope) ?>" data-page="<?= min($pages, $page + 1) ?>" <?= $page >= $pages ? 'disabled' : '' ?>><?= h(ac('pager_next', 'Next')) ?></button>
      </div>
    </div>
    <?php
    return (string)ob_get_clean();
}

/**
 * @param array<string,mixed> $items
 */
function render_detail_grid(array $items): string
{
    $pairs = [];
    foreach ($items as $label => $value) {
        $rawValue = '';
        $allowHtml = false;
        if (is_array($value)) {
            $rawValue = (string)($value['content'] ?? '');
            $allowHtml = !empty($value['html']);
        } else {
            $rawValue = (string)$value;
        }
        if (trim($rawValue) === '') {
            continue;
        }
        $pairs[] = '<div class="audit-center-detail__item"><div class="audit-center-detail__label">' . h($label) . '</div><div class="audit-center-detail__value">' . ($allowHtml ? $rawValue : h($rawValue)) . '</div></div>';
    }
    if ($pairs === []) {
        return '<div class="audit-center-detail__empty">' . h(ac('detail_empty', 'Tiada detail tambahan.')) . '</div>';
    }
    return '<div class="audit-center-detail">' . implode('', $pairs) . '</div>';
}

/**
 * @param array<int,array<string,mixed>> $rows
 */
function render_change_fields_table(array $rows): string
{
    if ($rows === []) {
        return '<div class="audit-center-detail__empty">' . h(ac('changes_no_fields', 'Tiada field change direkodkan untuk perubahan ini.')) . '</div>';
    }

    ob_start();
    ?>
    <div class="table-responsive mt-3">
      <table class="table audit-center-table audit-center-table--nested">
        <thead>
          <tr>
            <th style="width:180px;"><?= h(ac('detail_field', 'Field')) ?></th>
            <th style="width:140px;"><?= h(ac('detail_data_type', 'Data Type')) ?></th>
            <th style="width:110px;"><?= h(ac('detail_sensitive', 'Sensitive')) ?></th>
            <th><?= h(ac('detail_old_value', 'Old Value')) ?></th>
            <th><?= h(ac('detail_new_value', 'New Value')) ?></th>
          </tr>
        </thead>
        <tbody>
        <?php foreach ($rows as $row): ?>
          <?php
            $isSensitive = !empty($row['is_sensitive']);
            $oldValue = $isSensitive ? ac('detail_masked', '[MASKED]') : (string)($row['old_value'] ?? '—');
            $newValue = $isSensitive ? ac('detail_masked', '[MASKED]') : (string)($row['new_value'] ?? '—');
            $diffHint = trim((string)($row['diff_hint'] ?? ''));
          ?>
          <tr>
            <td><span class="audit-center-code"><?= h((string)($row['field'] ?? '—')) ?></span></td>
            <td><?= h((string)($row['data_type'] ?? '—')) ?></td>
            <td><span class="<?= h(audit_badge_class($isSensitive ? 'YES' : 'NO', ['NO'], ['YES'])) ?>"><?= h($isSensitive ? ac('detail_yes', 'Yes') : ac('detail_no', 'No')) ?></span></td>
            <td><div class="audit-center-multiline"><?= h($oldValue !== '' ? $oldValue : '—') ?></div></td>
            <td>
              <div class="audit-center-multiline"><?= h($newValue !== '' ? $newValue : '—') ?></div>
              <?php if ($diffHint !== ''): ?>
                <div class="audit-center-muted small mt-1"><?= h(ac('detail_diff_hint', 'Diff Hint')) ?>: <?= h($diffHint) ?></div>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php
    return (string)ob_get_clean();
}

function render_jump_link(string $label, string $tab, string $field, string $value): string
{
    $value = trim($value);
    if ($value === '') {
        return '—';
    }

    return '<button type="button" class="audit-center-jump-link" data-audit-jump-tab="' . h($tab) . '" data-audit-jump-field="' . h($field) . '" data-audit-jump-value="' . h($value) . '">' . h($label) . '</button>';
}

function render_action_button(string $action, string $label, array $data = [], string $variant = 'outline-secondary', string $icon = 'ri-settings-3-line'): string
{
    $attrs = [
        'type="button"',
        'class="btn btn-sm ' . h($variant) . ' audit-center-action-btn"',
        'data-audit-action="' . h($action) . '"',
    ];

    foreach ($data as $key => $value) {
        $attrs[] = 'data-' . h($key) . '="' . h((string)$value) . '"';
    }

    return '<button ' . implode(' ', $attrs) . '><i class="' . h($icon) . ' me-1"></i>' . h($label) . '</button>';
}

function render_meta_button(string $kind, array $data = [], ?string $label = null): string
{
    $attrs = [
        'type="button"',
        'class="btn btn-sm btn-outline-secondary audit-center-meta-btn"',
        'data-audit-meta-kind="' . h($kind) . '"',
    ];

    foreach ($data as $key => $value) {
        $attrs[] = 'data-' . h($key) . '="' . h((string)$value) . '"';
    }

    return '<button ' . implode(' ', $attrs) . '><i class="ri-file-search-line me-1"></i>' . h($label ?: ac('action_view_metadata', 'View Metadata')) . '</button>';
}

$controller = new AuditCenterController();
if (!$controller->isSuperAdmin()) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'message' => ac('access_denied_text', 'Audit Center is available to Super Admin only.')], JSON_UNESCAPED_UNICODE);
    exit;
}

if (function_exists('checkRateLimit') && !checkRateLimit('audit_center_panel', 120, 60)) {
    http_response_code(429);
    echo json_encode(['ok' => false, 'message' => ac('panel_rate_limited', 'Terlalu banyak permintaan Audit Center.')], JSON_UNESCAPED_UNICODE);
    exit;
}

$tab = strtolower(trim((string)($_GET['tab'] ?? 'events')));
$allowedTabs = ['events', 'requests', 'sessions', 'changes', 'security'];
if (!in_array($tab, $allowedTabs, true)) {
    $tab = 'events';
}

$search = mb_substr(trim((string)($_GET['q'] ?? '')), 0, 120);
$limit = (int)($_GET['limit'] ?? 10);
$filters = $controller->normalizeFilters($_GET);
$advancedOpen = (string)($_GET['advanced_open'] ?? '') === '1';
foreach ($filters as $filterValue) {
    if (!$advancedOpen && trim((string)$filterValue) !== '') {
        $advancedOpen = true;
        break;
    }
}
$page = max(1, (int)($_GET['page'] ?? 1));
$securitySubtab = strtolower(trim((string)($_GET['security_subtab'] ?? 'events')));
$allowedSecurityTabs = ['events', 'lockouts', 'throttles'];
if (!in_array($securitySubtab, $allowedSecurityTabs, true)) {
    $securitySubtab = 'events';
}
$securityEventsPage = max(1, (int)($_GET['security_events_page'] ?? 1));
$securityLockoutsPage = max(1, (int)($_GET['security_lockouts_page'] ?? 1));
$securityThrottlesPage = max(1, (int)($_GET['security_throttles_page'] ?? 1));

$tabMeta = [
    'events' => ['icon' => 'ri-file-list-3-line', 'title' => ac('tab_events', 'Events'), 'text' => ac('tab_events_text', 'Jejak event terkini merentas transaksi utama sistem, termasuk outcome, severity, actor, dan sasaran transaksi.')],
    'requests' => ['icon' => 'ri-route-line', 'title' => ac('tab_requests', 'Requests'), 'text' => ac('tab_requests_text', 'Ringkasan request-level trace untuk semakan route, status code, dan tempoh proses pada lapisan permintaan aplikasi.')],
    'sessions' => ['icon' => 'ri-fingerprint-line', 'title' => ac('tab_sessions', 'Sessions'), 'text' => ac('tab_sessions_text', 'Paparan sesi audit semasa dan sejarah ringkas untuk bantu semakan kesinambungan login, tempoh sesi, dan peranti.')],
    'changes' => ['icon' => 'ri-git-commit-line', 'title' => ac('tab_changes', 'Changes'), 'text' => ac('tab_changes_text', 'Viewer perubahan terperinci untuk rekod audit yang mempunyai change set dan field-level diff bagi memudahkan semakan forensik.')],
    'security' => ['icon' => 'ri-shield-keyhole-line', 'title' => ac('tab_security', 'Security'), 'text' => ac('tab_security_text', 'Tumpuan kepada event keselamatan, lockout aktif, dan throttle aktif supaya respon insiden lebih cepat dan teratur.')],
];

$summary = $controller->getSummary();

ob_start();
?>
<div class="row g-3">
  <div class="col-md-6 col-xl"><div class="audit-center-card audit-center-stat audit-center-stat--sky"><div class="audit-center-stat__label"><i class="ri-file-list-3-line"></i></div><div class="audit-center-stat__content"><div class="audit-center-stat__eyebrow"><?= h(ac('summary_events_today', 'Events Today')) ?></div><div class="audit-center-stat__value"><?= h(fmt_num($summary['events_today'] ?? 0)) ?></div><div class="audit-center-stat__meta"><?= h(ac('summary_events_today_text', 'Jumlah event audit yang direkodkan hari ini.')) ?></div></div></div></div>
  <div class="col-md-6 col-xl"><div class="audit-center-card audit-center-stat audit-center-stat--amber"><div class="audit-center-stat__label"><i class="ri-alarm-warning-line"></i></div><div class="audit-center-stat__content"><div class="audit-center-stat__eyebrow"><?= h(ac('summary_security_today', 'Security Today')) ?></div><div class="audit-center-stat__value"><?= h(fmt_num($summary['security_events_today'] ?? 0)) ?></div><div class="audit-center-stat__meta"><?= h(ac('summary_security_today_text', 'Signal auth dan keselamatan yang dikesan hari ini.')) ?></div></div></div></div>
  <div class="col-md-6 col-xl"><div class="audit-center-card audit-center-stat audit-center-stat--indigo"><div class="audit-center-stat__label"><i class="ri-fingerprint-line"></i></div><div class="audit-center-stat__content"><div class="audit-center-stat__eyebrow"><?= h(ac('summary_active_sessions', 'Active Sessions')) ?></div><div class="audit-center-stat__value"><?= h(fmt_num($summary['active_sessions'] ?? 0)) ?></div><div class="audit-center-stat__meta"><?= h(ac('summary_active_sessions_text', 'Sesi audit yang masih aktif pada masa semasa.')) ?></div></div></div></div>
  <div class="col-md-6 col-xl"><div class="audit-center-card audit-center-stat audit-center-stat--rose"><div class="audit-center-stat__label"><i class="ri-lock-password-line"></i></div><div class="audit-center-stat__content"><div class="audit-center-stat__eyebrow"><?= h(ac('summary_active_lockouts', 'Active Lockouts')) ?></div><div class="audit-center-stat__value"><?= h(fmt_num($summary['active_lockouts'] ?? 0)) ?></div><div class="audit-center-stat__meta"><?= h(ac('summary_active_lockouts_text', 'Lockout aktif yang berlaku pada lapisan login ID.')) ?></div></div></div></div>
  <div class="col-md-6 col-xl"><div class="audit-center-card audit-center-stat audit-center-stat--emerald"><div class="audit-center-stat__label"><i class="ri-radar-line"></i></div><div class="audit-center-stat__content"><div class="audit-center-stat__eyebrow"><?= h(ac('summary_active_throttles', 'Active Throttles')) ?></div><div class="audit-center-stat__value"><?= h(fmt_num($summary['active_throttles'] ?? 0)) ?></div><div class="audit-center-stat__meta"><?= h(ac('summary_active_throttles_text', 'Throttle aktif untuk pasangan login ID + IP atau IP.')) ?></div></div></div></div>
</div>
<?php
$summaryHtml = (string)ob_get_clean();

ob_start();
?>
<div class="audit-center-panel__header">
  <div class="audit-center-panel__header-main">
    <h5 class="audit-center-panel__title"><i class="<?= h($tabMeta[$tab]['icon']) ?> me-1"></i><?= h($tabMeta[$tab]['title']) ?></h5>
    <p class="audit-center-panel__text"><?= h($tabMeta[$tab]['text']) ?></p>
  </div>
  <div class="audit-center-panel__toolbar">
    <button type="button" class="btn btn-outline-secondary audit-center-export-btn" data-audit-export-format="csv"><i class="ri-file-excel-2-line me-1"></i><?= h(ac('export_csv', 'Export CSV')) ?></button>
    <button type="button" class="btn btn-outline-secondary audit-center-export-btn" data-audit-export-format="json"><i class="ri-code-s-slash-line me-1"></i><?= h(ac('export_json', 'Export JSON')) ?></button>
  </div>
</div>

<form class="audit-center-filter" id="audit-center-filter-form">
  <div>
    <label class="form-label" for="audit-center-q"><?= h(ac('filter_search', 'Search')) ?></label>
    <input type="text" class="form-control audit-center-filter__compact-control" id="audit-center-q" name="q" value="<?= h($search) ?>" placeholder="<?= h(ac('filter_search_placeholder', 'Cari login ID, route, request ID, IP, atau actor label')) ?>">
  </div>
  <div>
    <label class="form-label" for="audit-center-limit"><?= h(ac('filter_limit', 'Limit')) ?></label>
    <select class="form-select audit-center-filter__compact-control" id="audit-center-limit" name="limit">
      <?php foreach ([10, 25, 50, 100, 200] as $option): ?>
        <option value="<?= $option ?>" <?= $limit === $option ? 'selected' : '' ?>><?= $option ?></option>
      <?php endforeach; ?>
    </select>
  </div>
  <div class="audit-center-filter__actions">
    <button type="button" class="btn btn-outline-secondary px-3 <?= $advancedOpen ? 'active' : '' ?>" id="audit-center-advanced-toggle" aria-expanded="<?= $advancedOpen ? 'true' : 'false' ?>"><i class="ri-search-eye-line me-1"></i><?= h(ac('filter_advanced_toggle', 'Advanced')) ?></button>
    <button type="button" class="btn btn-light px-4" id="audit-center-reset"><i class="ri-refresh-line me-1"></i><?= h(ac('filter_reset', 'Reset')) ?></button>
  </div>
</form>

<div class="audit-center-advanced-filter <?= $advancedOpen ? '' : 'd-none' ?>" id="audit-center-advanced-panel">
  <div class="audit-center-advanced-filter__grid">
    <?php if (in_array($tab, ['events', 'security'], true)): ?>
      <div class="audit-center-advanced-filter__inline-row audit-center-advanced-filter__inline-row--7">
        <input type="text" class="form-control" id="audit-center-login-id" value="<?= h($filters['login_id'] ?? '') ?>" data-audit-filter-key="login_id" placeholder="<?= h(ac('detail_login_id', 'Login ID')) ?>">
        <input type="text" class="form-control" id="audit-center-ip" value="<?= h($filters['ip'] ?? '') ?>" data-audit-filter-key="ip" placeholder="<?= h(ac('detail_ip', 'IP')) ?>">
        <input type="text" class="form-control" id="audit-center-actor" value="<?= h($filters['actor'] ?? '') ?>" data-audit-filter-key="actor" placeholder="<?= h(ac('col_actor', 'Actor')) ?>">
        <input type="text" class="form-control" id="audit-center-event-type" value="<?= h($filters['event_type'] ?? '') ?>" data-audit-filter-key="event_type" placeholder="<?= h(ac('filter_event_type', 'Event Type')) ?>">
        <input type="text" class="form-control" id="audit-center-outcome" value="<?= h($filters['outcome'] ?? '') ?>" data-audit-filter-key="outcome" placeholder="<?= h(ac('col_outcome', 'Outcome')) ?>">
        <input type="text" class="form-control" id="audit-center-severity" value="<?= h($filters['severity'] ?? '') ?>" data-audit-filter-key="severity" placeholder="<?= h(ac('detail_severity', 'Severity')) ?>">
        <input type="text" class="form-control" id="audit-center-target-type" value="<?= h($filters['target_type'] ?? '') ?>" data-audit-filter-key="target_type" placeholder="<?= h(ac('detail_target_type', 'Target Type')) ?>">
      </div>
    <?php else: ?>
      <?php
        $inlineCount = 2;
        if ($tab === 'changes') {
            $inlineCount = 6;
        } elseif ($tab === 'requests') {
            $inlineCount = 6;
        } elseif ($tab === 'sessions') {
            $inlineCount = 3;
        }
      ?>
      <div class="audit-center-advanced-filter__inline-row audit-center-advanced-filter__inline-row--<?= (int)$inlineCount ?>">
        <input type="text" class="form-control" id="audit-center-login-id" value="<?= h($filters['login_id'] ?? '') ?>" data-audit-filter-key="login_id" placeholder="<?= h(ac('detail_login_id', 'Login ID')) ?>">
        <input type="text" class="form-control" id="audit-center-ip" value="<?= h($filters['ip'] ?? '') ?>" data-audit-filter-key="ip" placeholder="<?= h(ac('detail_ip', 'IP')) ?>">
        <?php if (in_array($tab, ['changes'], true)): ?>
          <input type="text" class="form-control" id="audit-center-actor" value="<?= h($filters['actor'] ?? '') ?>" data-audit-filter-key="actor" placeholder="<?= h(ac('col_actor', 'Actor')) ?>">
          <input type="text" class="form-control" id="audit-center-event-type" value="<?= h($filters['event_type'] ?? '') ?>" data-audit-filter-key="event_type" placeholder="<?= h(ac('filter_event_type', 'Event Type')) ?>">
          <input type="text" class="form-control" id="audit-center-target-type" value="<?= h($filters['target_type'] ?? '') ?>" data-audit-filter-key="target_type" placeholder="<?= h(ac('detail_target_type', 'Target Type')) ?>">
          <input type="text" class="form-control" id="audit-center-change-reason" value="<?= h($filters['change_reason'] ?? '') ?>" data-audit-filter-key="change_reason" placeholder="<?= h(ac('col_reason', 'Reason')) ?>">
        <?php elseif ($tab === 'requests'): ?>
          <input type="text" class="form-control" id="audit-center-route" value="<?= h($filters['route'] ?? '') ?>" data-audit-filter-key="route" placeholder="<?= h(ac('col_route', 'Route')) ?>">
          <input type="text" class="form-control" id="audit-center-method" value="<?= h($filters['method'] ?? '') ?>" data-audit-filter-key="method" placeholder="<?= h(ac('col_method', 'Method')) ?>">
          <input type="text" class="form-control" id="audit-center-status-code" value="<?= h($filters['status_code'] ?? '') ?>" data-audit-filter-key="status_code" placeholder="<?= h(ac('col_status', 'Status')) ?>">
          <input type="text" class="form-control" id="audit-center-session-id" value="<?= h($filters['session_id'] ?? '') ?>" data-audit-filter-key="session_id" placeholder="<?= h(ac('col_session_id', 'Session ID')) ?>">
        <?php elseif ($tab === 'sessions'): ?>
          <input type="text" class="form-control" id="audit-center-session-id" value="<?= h($filters['session_id'] ?? '') ?>" data-audit-filter-key="session_id" placeholder="<?= h(ac('col_session_id', 'Session ID')) ?>">
        <?php endif; ?>
      </div>
    <?php endif; ?>
    <div class="audit-center-advanced-filter__date-row<?= $tab === 'security' ? ' audit-center-advanced-filter__date-row--security' : '' ?>">
      <div class="audit-center-advanced-filter__date-field">
        <span class="audit-center-advanced-filter__date-hint"><?= h(ac('filter_date_from', 'Date From')) ?></span>
        <input type="date" class="form-control audit-center-advanced-filter__date-input" id="audit-center-date-from" value="<?= h($filters['date_from'] ?? '') ?>" data-audit-filter-key="date_from" placeholder="<?= h(ac('filter_date_from', 'Date From')) ?>" aria-label="<?= h(ac('filter_date_from', 'Date From')) ?>">
      </div>
      <div class="audit-center-advanced-filter__date-field">
        <span class="audit-center-advanced-filter__date-hint"><?= h(ac('filter_date_to', 'Date To')) ?></span>
        <input type="date" class="form-control audit-center-advanced-filter__date-input" id="audit-center-date-to" value="<?= h($filters['date_to'] ?? '') ?>" data-audit-filter-key="date_to" placeholder="<?= h(ac('filter_date_to', 'Date To')) ?>" aria-label="<?= h(ac('filter_date_to', 'Date To')) ?>">
      </div>
      <?php if ($tab === 'security'): ?>
        <input type="text" class="form-control" id="audit-center-scope-type" value="<?= h($filters['scope_type'] ?? '') ?>" data-audit-filter-key="scope_type" placeholder="<?= h(ac('detail_scope_type', 'Scope Type')) ?>">
      <?php endif; ?>
      <button type="button" class="btn btn-primary px-4 audit-center-advanced-filter__apply-btn" id="audit-center-apply-filters"><i class="ri-filter-3-line me-1"></i><?= h(ac('filter_apply', 'Filter')) ?></button>
    </div>
  </div>
</div>

<div class="audit-center-table-wrap">
<?php if ($tab === 'events'): ?>
  <?php $payload = $controller->getEventsPage($page, $limit, $search, $filters); $rows = $payload['rows']; ?>
  <div class="audit-center-table-header"><div class="audit-center-table-header__title"><?= h(ac('events_title', 'Recent Events')) ?></div><div class="audit-center-table-header__meta"><?= h(ac('page_label', 'Page')) ?> <?= h($payload['page']) ?> <?= h(ac('page_of', 'of')) ?> <?= h($payload['pages']) ?></div></div>
  <?php if ($rows === []): ?>
    <div class="audit-center-empty"><i class="ri-inbox-archive-line"></i><div><?= h(ac('events_empty', 'Tiada event audit ditemui untuk paparan semasa.')) ?></div></div>
  <?php else: ?>
    <div class="table-responsive"><table class="table audit-center-table"><thead><tr><th style="width:44px;"></th><th class="audit-center-col-datetime"><?= h(ac('col_occurred', 'Occurred')) ?></th><th style="width:280px;"><?= h(ac('col_event', 'Event')) ?></th><th style="width:120px;"><?= h(ac('col_outcome', 'Outcome')) ?></th><th style="width:180px;"><?= h(ac('col_actor', 'Actor')) ?></th><th style="width:180px;"><?= h(ac('col_target', 'Target')) ?></th></tr></thead><tbody>
    <?php foreach ($rows as $index => $row): $outcome = strtoupper(trim((string)($row['outcome'] ?? ''))); $severity = strtoupper(trim((string)($row['severity'] ?? ''))); $target = trim((string)($row['target_label'] ?? $row['target_type'] ?? '')); $detailId = 'event-detail-' . ($row['id'] ?? $index); ?>
      <tr>
        <td><button type="button" class="btn btn-sm btn-outline-secondary audit-center-expand-btn" data-target="<?= h($detailId) ?>" aria-expanded="false">+</button></td>
        <td class="audit-center-cell-datetime"><?= h(fmt_dt($row['occurred_at'] ?? null)) ?></td>
        <td><div class="fw-semibold"><?= h((string)($row['event_type'] ?? '—')) ?></div><div class="audit-center-muted small audit-center-line-clamp-1"><?= h((string)($row['message'] ?? '—')) ?></div></td>
        <td><span class="<?= h(audit_badge_class($outcome, ['SUCCESS'], ['FAIL', 'FAILURE', 'ERROR'], ['BLOCKED', 'LOCKED'], ['INFO'])) ?>"><?= h($outcome !== '' ? $outcome : '—') ?></span></td>
        <td><div class="audit-center-line-clamp-1" title="<?= h((string)($row['actor_label'] ?? '—')) ?>"><?= h((string)($row['actor_label'] ?? '—')) ?></div></td>
        <td><div class="audit-center-line-clamp-1" title="<?= h($target !== '' ? $target : '—') ?>"><?= $target !== '' ? h($target) : '—' ?></div></td>
      </tr>
      <tr id="<?= h($detailId) ?>" class="audit-center-detail-row d-none">
        <td colspan="6"><?= render_detail_grid([
            ac('detail_severity', 'Severity') => $severity !== '' ? $severity : '—',
            ac('detail_ip', 'IP') => (string)($row['ip_text'] ?? '—'),
            ac('detail_user_id', 'User ID') => (string)($row['user_id'] ?? '—'),
            ac('detail_request_id', 'Request ID') => trim((string)($row['request_id'] ?? '')) !== '' ? ['content' => render_jump_link((string)$row['request_id'], 'requests', 'q', (string)$row['request_id']), 'html' => true] : '—',
            ac('detail_session_id', 'Session ID') => trim((string)($row['session_id'] ?? '')) !== '' ? ['content' => render_jump_link((string)$row['session_id'], 'sessions', 'session_id', (string)$row['session_id']), 'html' => true] : '—',
            ac('detail_target_type', 'Target Type') => (string)($row['target_type'] ?? '—'),
            ac('detail_target_id', 'Target ID') => (string)($row['target_id'] ?? '—'),
            ac('detail_target_label', 'Target Label') => (string)($row['target_label'] ?? '—'),
            ac('detail_message', 'Message') => (string)($row['message'] ?? '—'),
        ]) ?>
        <div class="audit-center-row-actions">
          <?= render_meta_button('event', ['id' => (string)($row['id'] ?? '')]) ?>
        </div></td>
      </tr>
    <?php endforeach; ?>
    </tbody></table></div>
    <?= render_pager($payload, 'page') ?>
  <?php endif; ?>
<?php elseif ($tab === 'requests'): ?>
  <?php $payload = $controller->getRequestsPage($page, $limit, $search, $filters); $rows = $payload['rows']; ?>
  <div class="audit-center-table-header"><div class="audit-center-table-header__title"><?= h(ac('requests_title', 'Request Trace')) ?></div><div class="audit-center-table-header__meta"><?= h(ac('page_label', 'Page')) ?> <?= h($payload['page']) ?> <?= h(ac('page_of', 'of')) ?> <?= h($payload['pages']) ?></div></div>
  <?php if ($rows === []): ?>
    <div class="audit-center-empty"><i class="ri-route-line"></i><div><?= h(ac('requests_empty', 'Tiada request trace ditemui untuk paparan semasa.')) ?></div></div>
  <?php else: ?>
    <div class="table-responsive"><table class="table audit-center-table"><thead><tr><th style="width:44px;"></th><th class="audit-center-col-datetime"><?= h(ac('col_started', 'Started')) ?></th><th style="width:320px;"><?= h(ac('col_route', 'Route')) ?></th><th style="width:110px;"><?= h(ac('col_method', 'Method')) ?></th><th style="width:110px;"><?= h(ac('col_status', 'Status')) ?></th><th style="width:110px;"><?= h(ac('col_duration', 'Duration')) ?></th></tr></thead><tbody>
    <?php foreach ($rows as $index => $row): $statusCode = trim((string)($row['status_code'] ?? '')); $statusClass = 'audit-center-badge audit-center-badge--muted'; if ($statusCode !== '') { $statusInt = (int)$statusCode; $statusClass = $statusInt >= 500 ? 'audit-center-badge audit-center-badge--danger' : ($statusInt >= 400 ? 'audit-center-badge audit-center-badge--warning' : ($statusInt >= 200 ? 'audit-center-badge audit-center-badge--info' : $statusClass)); } $detailId = 'request-detail-' . ($row['id'] ?? $index); ?>
      <tr>
        <td><button type="button" class="btn btn-sm btn-outline-secondary audit-center-expand-btn" data-target="<?= h($detailId) ?>" aria-expanded="false">+</button></td>
        <td class="audit-center-cell-datetime"><?= h(fmt_dt($row['started_at'] ?? null)) ?></td>
        <td><div class="fw-semibold audit-center-line-clamp-1" title="<?= h((string)($row['route'] ?? '—')) ?>"><?= h((string)($row['route'] ?? '—')) ?></div></td>
        <td><span class="audit-center-badge audit-center-badge--muted"><?= h((string)($row['method'] ?? '—')) ?></span></td>
        <td><span class="<?= h($statusClass) ?>"><?= h($statusCode !== '' ? $statusCode : '—') ?></span></td>
        <td><?= h(fmt_duration_ms($row['duration_ms'] ?? null)) ?></td>
      </tr>
      <tr id="<?= h($detailId) ?>" class="audit-center-detail-row d-none">
        <td colspan="6"><?= render_detail_grid([
            ac('detail_request_id', 'Request ID') => trim((string)($row['request_id'] ?? '')) !== '' ? ['content' => render_jump_link((string)$row['request_id'], 'requests', 'q', (string)$row['request_id']), 'html' => true] : '—',
            ac('detail_session_id', 'Session ID') => trim((string)($row['session_id'] ?? '')) !== '' ? ['content' => render_jump_link((string)$row['session_id'], 'sessions', 'session_id', (string)$row['session_id']), 'html' => true] : '—',
            ac('detail_login_id', 'Login ID') => trim((string)($row['login_id'] ?? '')) !== '' ? ['content' => render_jump_link((string)$row['login_id'], 'events', 'login_id', (string)$row['login_id']), 'html' => true] : '—',
            ac('detail_user_id', 'User ID') => (string)($row['user_id'] ?? '—'),
            ac('detail_ip', 'IP') => (string)($row['ip_text'] ?? '—'),
            ac('detail_ended_at', 'Ended At') => fmt_dt($row['ended_at'] ?? null),
            ac('detail_route', 'Route') => (string)($row['route'] ?? '—'),
        ]) ?>
        <div class="audit-center-row-actions">
          <?= render_meta_button('request', ['request-id' => (string)($row['request_id'] ?? '')]) ?>
        </div></td>
      </tr>
    <?php endforeach; ?>
    </tbody></table></div>
    <?= render_pager($payload, 'page') ?>
  <?php endif; ?>
<?php elseif ($tab === 'sessions'): ?>
  <?php $payload = $controller->getSessionsPage($page, $limit, $search, $filters); $rows = $payload['rows']; ?>
  <div class="audit-center-table-header"><div class="audit-center-table-header__title"><?= h(ac('sessions_title', 'Session Activity')) ?></div><div class="audit-center-table-header__meta"><?= h(ac('page_label', 'Page')) ?> <?= h($payload['page']) ?> <?= h(ac('page_of', 'of')) ?> <?= h($payload['pages']) ?></div></div>
  <?php if ($rows === []): ?>
    <div class="audit-center-empty"><i class="ri-fingerprint-line"></i><div><?= h(ac('sessions_empty', 'Tiada sesi audit ditemui untuk paparan semasa.')) ?></div></div>
  <?php else: ?>
    <div class="table-responsive"><table class="table audit-center-table"><thead><tr><th style="width:44px;"></th><th class="audit-center-col-datetime"><?= h(ac('col_started', 'Started')) ?></th><th style="width:300px;"><?= h(ac('col_user', 'User')) ?></th><th style="width:220px;"><?= h(ac('col_session_id', 'Session ID')) ?></th><th style="width:110px;"><?= h(ac('col_duration', 'Duration')) ?></th><th style="width:150px;"><?= h(ac('detail_ip', 'IP')) ?></th></tr></thead><tbody>
    <?php foreach ($rows as $index => $row): $detailId = 'session-detail-' . ($row['id'] ?? $index); ?>
      <?php
        $displayName = trim((string)($row['display_name'] ?? ''));
        $displayLoginId = trim((string)($row['login_id'] ?? $row['user_nopekerja'] ?? ''));
        $userSummary = $displayName !== ''
          ? ($displayName . ($displayLoginId !== '' ? ' (' . $displayLoginId . ')' : ''))
          : ($displayLoginId !== '' ? $displayLoginId : '—');
      ?>
      <tr>
        <td><button type="button" class="btn btn-sm btn-outline-secondary audit-center-expand-btn" data-target="<?= h($detailId) ?>" aria-expanded="false">+</button></td>
        <td class="audit-center-cell-datetime"><?= h(fmt_dt($row['started_at'] ?? null)) ?></td>
        <td><div class="audit-center-line-clamp-1" title="<?= h($userSummary) ?>"><?= h($userSummary) ?></div></td>
        <td><?php if (!empty($row['session_id'])): ?><span class="audit-center-code"><?= h($row['session_id']) ?></span><?php else: ?>—<?php endif; ?></td>
        <td><?= h(fmt_duration_seconds($row['duration_seconds'] ?? null)) ?></td>
        <td><?php if (!empty($row['ip_text'])): ?><span class="audit-center-code"><?= h($row['ip_text']) ?></span><?php else: ?>—<?php endif; ?></td>
      </tr>
      <tr id="<?= h($detailId) ?>" class="audit-center-detail-row d-none">
        <td colspan="6"><?= render_detail_grid([
            ac('detail_user', 'User') => $userSummary,
            ac('detail_ended_at', 'Ended At') => fmt_dt($row['ended_at'] ?? null),
            ac('detail_user_agent', 'User Agent') => (string)($row['user_agent'] ?? '—'),
            ac('detail_session_id', 'Session ID') => trim((string)($row['session_id'] ?? '')) !== '' ? ['content' => render_jump_link((string)$row['session_id'], 'sessions', 'session_id', (string)$row['session_id']), 'html' => true] : '—',
            ac('detail_login_id', 'Login ID') => trim((string)($row['login_id'] ?? '')) !== '' ? ['content' => render_jump_link((string)$row['login_id'], 'events', 'login_id', (string)$row['login_id']), 'html' => true] : '—',
            ac('detail_legacy_identifier', 'Legacy Identifier') => (string)($row['user_nopekerja'] ?? '—'),
            ac('detail_user_id', 'User ID') => (string)($row['user_id'] ?? '—'),
            ac('detail_ip', 'IP') => (string)($row['ip_text'] ?? '—'),
        ]) ?>
        <div class="audit-center-row-actions">
          <?= render_meta_button('session', ['session-id' => (string)($row['session_id'] ?? '')]) ?>
          <?= render_action_button(
              'terminate_session',
              ac('action_terminate_session', 'Terminate Session'),
              ['session-id' => (string)($row['session_id'] ?? '')],
              'btn-outline-danger',
              'ri-shut-down-line'
          ) ?>
        </div></td>
      </tr>
    <?php endforeach; ?>
    </tbody></table></div>
    <?= render_pager($payload, 'page') ?>
  <?php endif; ?>
<?php elseif ($tab === 'changes'): ?>
  <?php $payload = $controller->getChangesPage($page, $limit, $search, $filters); $rows = $payload['rows']; ?>
  <div class="audit-center-table-header"><div class="audit-center-table-header__title"><?= h(ac('changes_title', 'Change Tracking')) ?></div><div class="audit-center-table-header__meta"><?= h(ac('page_label', 'Page')) ?> <?= h($payload['page']) ?> <?= h(ac('page_of', 'of')) ?> <?= h($payload['pages']) ?></div></div>
  <?php if ($rows === []): ?>
    <div class="audit-center-empty"><i class="ri-git-commit-line"></i><div><?= h(ac('changes_empty', 'Tiada change set ditemui untuk paparan semasa.')) ?></div></div>
  <?php else: ?>
    <div class="table-responsive"><table class="table audit-center-table"><thead><tr><th style="width:44px;"></th><th class="audit-center-col-datetime"><?= h(ac('col_changed_at', 'Changed At')) ?></th><th style="width:170px;"><?= h(ac('col_target', 'Target')) ?></th><th style="width:230px;"><?= h(ac('col_event', 'Event')) ?></th><th style="width:150px;"><?= h(ac('col_actor', 'Actor')) ?></th><th style="width:88px;"><?= h(ac('col_fields', 'Fields')) ?></th><th style="width:220px;"><?= h(ac('col_reason', 'Reason')) ?></th></tr></thead><tbody>
    <?php foreach ($rows as $index => $row): $detailId = 'change-detail-' . ($row['id'] ?? $index); ?>
      <?php
        $targetType = trim((string)($row['target_type'] ?? ''));
        $targetId = trim((string)($row['target_id'] ?? ''));
        $targetSummary = trim($targetType . ($targetId !== '' ? ' • ' . $targetId : ''));
        $changeReason = trim((string)($row['change_reason'] ?? ''));
        $changeReasonSummary = $changeReason !== '' ? $changeReason : '—';
      ?>
      <tr>
        <td><button type="button" class="btn btn-sm btn-outline-secondary audit-center-expand-btn" data-target="<?= h($detailId) ?>" aria-expanded="false">+</button></td>
        <td class="audit-center-cell-datetime"><?= h(fmt_dt($row['occurred_at'] ?? null)) ?></td>
        <td><div class="fw-semibold audit-center-line-clamp-1" title="<?= h($targetSummary !== '' ? $targetSummary : '—') ?>"><?= h($targetSummary !== '' ? $targetSummary : '—') ?></div></td>
        <td><div class="fw-semibold audit-center-line-clamp-1" title="<?= h((string)($row['event_type'] ?? '—')) ?>"><?= h((string)($row['event_type'] ?? '—')) ?></div><div class="audit-center-muted small audit-center-line-clamp-1" title="<?= h((string)($row['message'] ?? '—')) ?>"><?= h((string)($row['message'] ?? '—')) ?></div></td>
        <td><div class="audit-center-line-clamp-1" title="<?= h((string)($row['actor_label'] ?? '—')) ?>"><?= h((string)($row['actor_label'] ?? '—')) ?></div></td>
        <td><span class="audit-center-badge audit-center-badge--info"><?= h((string)($row['field_count'] ?? '0')) ?></span></td>
        <td><div class="audit-center-line-clamp-1" title="<?= h($changeReasonSummary) ?>"><?= h($changeReasonSummary) ?></div></td>
      </tr>
      <tr id="<?= h($detailId) ?>" class="audit-center-detail-row d-none">
        <td colspan="7">
          <?= render_detail_grid([
              ac('detail_change_set_id', 'Change Set ID') => (string)($row['id'] ?? '—'),
              ac('detail_event_id', 'Event ID') => (string)($row['event_id'] ?? '—'),
              ac('detail_target_type', 'Target Type') => (string)($row['target_type'] ?? '—'),
              ac('detail_target_id', 'Target ID') => (string)($row['target_id'] ?? '—'),
              ac('detail_login_id', 'Login ID') => trim((string)($row['login_id'] ?? '')) !== '' ? ['content' => render_jump_link((string)$row['login_id'], 'events', 'login_id', (string)$row['login_id']), 'html' => true] : '—',
              ac('detail_actor', 'Actor') => (string)($row['actor_label'] ?? '—'),
              ac('detail_event_type', 'Event Type') => trim((string)($row['event_type'] ?? '')) !== '' ? ['content' => render_jump_link((string)$row['event_type'], 'events', 'event_type', (string)$row['event_type']), 'html' => true] : '—',
              ac('detail_change_reason', 'Change Reason') => $changeReasonSummary,
              ac('detail_message', 'Message') => (string)($row['message'] ?? '—'),
          ]) ?>
          <?= render_change_fields_table(is_array($row['field_changes'] ?? null) ? $row['field_changes'] : []) ?>
          <div class="audit-center-row-actions">
            <?= render_meta_button('change', ['change-set-id' => (string)($row['id'] ?? '')]) ?>
          </div>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody></table></div>
    <?= render_pager($payload, 'page') ?>
  <?php endif; ?>
<?php else: ?>
  <?php $payload = $controller->getSecurityPage($limit, $search, $securityEventsPage, $securityLockoutsPage, $securityThrottlesPage, $filters); ?>
  <div class="audit-center-security-tabs mb-3">
    <button type="button" class="audit-center-security-tab <?= $securitySubtab === 'events' ? 'active' : '' ?>" data-audit-security-tab="events"><?= h(ac('security_events_title', 'Recent Security Events')) ?></button>
    <button type="button" class="audit-center-security-tab <?= $securitySubtab === 'lockouts' ? 'active' : '' ?>" data-audit-security-tab="lockouts"><?= h(ac('security_lockouts_title', 'Active Lockouts')) ?></button>
    <button type="button" class="audit-center-security-tab <?= $securitySubtab === 'throttles' ? 'active' : '' ?>" data-audit-security-tab="throttles"><?= h(ac('security_throttles_title', 'Active Throttles')) ?></button>
  </div>
  <?php if ($securitySubtab === 'events'): ?>
    <div class="audit-center-subpanel">
        <div class="audit-center-table-header"><div class="audit-center-table-header__title"><?= h(ac('security_events_title', 'Recent Security Events')) ?></div><div class="audit-center-table-header__meta"><?= h(ac('page_label', 'Page')) ?> <?= h($payload['events']['page']) ?> <?= h(ac('page_of', 'of')) ?> <?= h($payload['events']['pages']) ?></div></div>
        <?php if ($payload['events']['rows'] === []): ?>
          <div class="audit-center-empty"><i class="ri-alarm-warning-line"></i><div><?= h(ac('security_events_empty', 'Tiada event keselamatan ditemui.')) ?></div></div>
        <?php else: ?>
          <div class="table-responsive"><table class="table audit-center-table"><thead><tr><th style="width:44px;"></th><th class="audit-center-col-datetime"><?= h(ac('col_occurred', 'Occurred')) ?></th><th style="width:260px;"><?= h(ac('col_event', 'Event')) ?></th><th style="width:120px;"><?= h(ac('col_outcome', 'Outcome')) ?></th><th style="width:180px;"><?= h(ac('col_actor', 'Actor')) ?></th></tr></thead><tbody>
          <?php foreach ($payload['events']['rows'] as $index => $row): $outcome = strtoupper(trim((string)($row['outcome'] ?? ''))); $detailId = 'security-event-detail-' . ($row['id'] ?? $index); ?>
            <tr>
              <td><button type="button" class="btn btn-sm btn-outline-secondary audit-center-expand-btn" data-target="<?= h($detailId) ?>" aria-expanded="false">+</button></td>
              <td class="audit-center-cell-datetime"><?= h(fmt_dt($row['occurred_at'] ?? null)) ?></td>
              <td><div class="fw-semibold audit-center-line-clamp-1" title="<?= h((string)($row['event_type'] ?? '—')) ?>"><?= h((string)($row['event_type'] ?? '—')) ?></div></td>
              <td><span class="<?= h(audit_badge_class($outcome, ['SUCCESS'], ['FAIL', 'FAILURE', 'ERROR'], ['BLOCKED', 'LOCKED'])) ?>"><?= h($outcome !== '' ? $outcome : '—') ?></span></td>
              <td><div class="audit-center-line-clamp-1" title="<?= h((string)($row['actor_label'] ?? '—')) ?>"><?= h((string)($row['actor_label'] ?? '—')) ?></div></td>
            </tr>
            <tr id="<?= h($detailId) ?>" class="audit-center-detail-row d-none">
              <td colspan="5"><?= render_detail_grid([
                  ac('detail_login_id', 'Login ID') => (string)($row['login_id'] ?? '—'),
                  ac('detail_severity', 'Severity') => (string)($row['severity'] ?? '—'),
                  ac('detail_ip', 'IP') => (string)($row['ip_text'] ?? '—'),
                  ac('detail_message', 'Message') => (string)($row['message'] ?? '—'),
              ]) ?>
              <div class="audit-center-row-actions">
                <?= render_meta_button('event', ['id' => (string)($row['id'] ?? '')]) ?>
              </div></td>
            </tr>
          <?php endforeach; ?>
          </tbody></table></div>
          <?= render_pager($payload['events'], 'security_events_page') ?>
        <?php endif; ?>
    </div>
  <?php elseif ($securitySubtab === 'lockouts'): ?>
    <div class="audit-center-subpanel">
        <div class="audit-center-table-header"><div class="audit-center-table-header__title"><?= h(ac('security_lockouts_title', 'Active Lockouts')) ?></div><div class="audit-center-table-header__meta"><?= h(ac('page_label', 'Page')) ?> <?= h($payload['lockouts']['page']) ?> <?= h(ac('page_of', 'of')) ?> <?= h($payload['lockouts']['pages']) ?></div></div>
        <?php if ($payload['lockouts']['rows'] === []): ?>
          <div class="audit-center-empty"><i class="ri-lock-password-line"></i><div><?= h(ac('security_lockouts_empty', 'Tiada lockout aktif ditemui.')) ?></div></div>
        <?php else: ?>
          <div class="table-responsive"><table class="table audit-center-table"><thead><tr><th style="width:44px;"></th><th style="width:180px;"><?= h(ac('detail_login_id', 'Login ID')) ?></th><th style="width:100px;"><?= h(ac('col_attempts', 'Attempts')) ?></th><th class="audit-center-col-datetime"><?= h(ac('col_locked_until', 'Locked Until')) ?></th><th style="width:150px;"><?= h(ac('detail_ip', 'IP')) ?></th></tr></thead><tbody>
          <?php foreach ($payload['lockouts']['rows'] as $index => $row): $detailId = 'lockout-detail-' . ($row['id'] ?? $index); ?>
            <tr>
              <td><button type="button" class="btn btn-sm btn-outline-secondary audit-center-expand-btn" data-target="<?= h($detailId) ?>" aria-expanded="false">+</button></td>
              <td><?php if (!empty($row['f_loginID'])): ?><span class="audit-center-code"><?= h($row['f_loginID']) ?></span><?php else: ?>—<?php endif; ?></td>
              <td><?= h((string)($row['f_failed_attempts'] ?? '0')) ?></td>
              <td class="audit-center-cell-datetime"><?= h(fmt_dt($row['f_locked_until'] ?? null)) ?></td>
              <td><?php if (!empty($row['f_last_ip'])): ?><span class="audit-center-code"><?= h($row['f_last_ip']) ?></span><?php else: ?>—<?php endif; ?></td>
            </tr>
            <tr id="<?= h($detailId) ?>" class="audit-center-detail-row d-none">
              <td colspan="5"><?= render_detail_grid([
                  ac('detail_last_failed_at', 'Last Failed At') => fmt_dt($row['f_last_failed_at'] ?? null),
                  ac('detail_login_id', 'Login ID') => (string)($row['f_loginID'] ?? '—'),
                  ac('col_attempts', 'Attempts') => (string)($row['f_failed_attempts'] ?? '0'),
              ]) ?>
              <div class="audit-center-row-actions">
                <?= render_meta_button('lockout', ['login-id' => (string)($row['f_loginID'] ?? '')]) ?>
                <?= render_action_button(
                    'clear_lockout',
                    ac('action_clear_lockout', 'Clear Lockout'),
                    ['login-id' => (string)($row['f_loginID'] ?? '')],
                    'btn-outline-warning',
                    'ri-lock-unlock-line'
                ) ?>
              </div></td>
            </tr>
          <?php endforeach; ?>
          </tbody></table></div>
          <?= render_pager($payload['lockouts'], 'security_lockouts_page') ?>
        <?php endif; ?>
    </div>
  <?php else: ?>
    <div class="audit-center-subpanel">
        <div class="audit-center-table-header"><div class="audit-center-table-header__title"><?= h(ac('security_throttles_title', 'Active Throttles')) ?></div><div class="audit-center-table-header__meta"><?= h(ac('page_label', 'Page')) ?> <?= h($payload['throttles']['page']) ?> <?= h(ac('page_of', 'of')) ?> <?= h($payload['throttles']['pages']) ?></div></div>
        <?php if ($payload['throttles']['rows'] === []): ?>
          <div class="audit-center-empty"><i class="ri-radar-line"></i><div><?= h(ac('security_throttles_empty', 'Tiada throttle aktif ditemui.')) ?></div></div>
        <?php else: ?>
          <div class="table-responsive"><table class="table audit-center-table"><thead><tr><th style="width:44px;"></th><th style="width:120px;"><?= h(ac('col_scope', 'Scope')) ?></th><th style="width:260px;"><?= h(ac('col_scope_key', 'Scope Key')) ?></th><th style="width:100px;"><?= h(ac('col_attempts', 'Attempts')) ?></th><th class="audit-center-col-datetime"><?= h(ac('col_locked_until', 'Locked Until')) ?></th></tr></thead><tbody>
          <?php foreach ($payload['throttles']['rows'] as $index => $row): $scopeType = strtoupper(trim((string)($row['f_scope_type'] ?? ''))); $detailId = 'throttle-detail-' . ($row['id'] ?? $index); ?>
            <tr>
              <td><button type="button" class="btn btn-sm btn-outline-secondary audit-center-expand-btn" data-target="<?= h($detailId) ?>" aria-expanded="false">+</button></td>
              <td><span class="<?= h(audit_badge_class($scopeType, [], ['IP'], ['LOGIN_IP'])) ?>"><?= h($scopeType !== '' ? $scopeType : '—') ?></span></td>
              <td><?php if (!empty($row['f_scope_key'])): ?><span class="audit-center-code"><?= h($row['f_scope_key']) ?></span><?php else: ?>—<?php endif; ?></td>
              <td><?= h((string)($row['f_failed_attempts'] ?? '0')) ?></td>
              <td class="audit-center-cell-datetime"><?= h(fmt_dt($row['f_locked_until'] ?? null)) ?></td>
            </tr>
            <tr id="<?= h($detailId) ?>" class="audit-center-detail-row d-none">
              <td colspan="5"><?= render_detail_grid([
                  ac('detail_last_failed_at', 'Last Failed At') => fmt_dt($row['f_last_failed_at'] ?? null),
                  ac('detail_ip', 'IP') => (string)($row['f_last_ip'] ?? '—'),
                  ac('col_scope_key', 'Scope Key') => (string)($row['f_scope_key'] ?? '—'),
                  ac('detail_scope_type', 'Scope Type') => $scopeType !== '' ? $scopeType : '—',
              ]) ?>
              <div class="audit-center-row-actions">
                <?= render_meta_button(
                    'throttle',
                    [
                        'scope-type' => (string)($row['f_scope_type'] ?? ''),
                        'scope-key' => (string)($row['f_scope_key'] ?? ''),
                    ]
                ) ?>
                <?= render_action_button(
                    'clear_throttle',
                    ac('action_clear_throttle', 'Clear Throttle'),
                    [
                        'scope-type' => (string)($row['f_scope_type'] ?? ''),
                        'scope-key' => (string)($row['f_scope_key'] ?? ''),
                    ],
                    'btn-outline-warning',
                    'ri-radar-line'
                ) ?>
              </div></td>
            </tr>
          <?php endforeach; ?>
          </tbody></table></div>
          <?= render_pager($payload['throttles'], 'security_throttles_page') ?>
        <?php endif; ?>
    </div>
  <?php endif; ?>
<?php endif; ?>
</div>
<?php
$panelHtml = (string)ob_get_clean();

echo json_encode([
    'ok' => true,
    'summary_html' => $summaryHtml,
    'panel_html' => $panelHtml,
    'state' => [
        'tab' => $tab,
        'q' => $search,
        'limit' => $limit,
        'date_from' => $filters['date_from'] ?? '',
        'date_to' => $filters['date_to'] ?? '',
        'login_id' => $filters['login_id'] ?? '',
        'actor' => $filters['actor'] ?? '',
        'ip' => $filters['ip'] ?? '',
        'event_type' => $filters['event_type'] ?? '',
        'outcome' => $filters['outcome'] ?? '',
        'severity' => $filters['severity'] ?? '',
        'target_type' => $filters['target_type'] ?? '',
        'route' => $filters['route'] ?? '',
        'method' => $filters['method'] ?? '',
        'status_code' => $filters['status_code'] ?? '',
        'session_id' => $filters['session_id'] ?? '',
        'change_reason' => $filters['change_reason'] ?? '',
        'scope_type' => $filters['scope_type'] ?? '',
        'advanced_open' => $advancedOpen,
        'page' => $page,
        'security_subtab' => $securitySubtab,
        'security_events_page' => $securityEventsPage,
        'security_lockouts_page' => $securityLockoutsPage,
        'security_throttles_page' => $securityThrottlesPage,
    ],
], JSON_UNESCAPED_UNICODE);
