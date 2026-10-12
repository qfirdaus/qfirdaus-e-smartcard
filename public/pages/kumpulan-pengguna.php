<?php
/**
 * IQS FRAMEWORK CORE FILE
 *
 * READ ONLY for downstream project programmers.
 * Do not modify this file directly in template or cloned projects.
 * Custom changes must be implemented in project-specific files
 * or approved extension points.
 */
// pages/kumpulan-pengguna.php
declare(strict_types=1);

require_once __DIR__ . '/../includes/init.php';
require_login();
require_once __DIR__ . '/../ajax/_helpers.php';
require_once __DIR__ . '/../classes/Database.php';
require_once __DIR__ . '/../classes/SystemConfigConstants.php';

$pdoPerm = Database::getInstance('mysql')->getConnection();
ensurePageGroupManagePermission($pdoPerm);

require_once __DIR__ . '/../controllers/GroupController.php';
$controller    = new GroupController();

$lang          = $controller->lang ?? 'ms';
$profile       = $controller->profile ?? [];
$senaraiGroup  = $controller->senaraiGroup ?? [];
$version       = (string)($_ENV['APP_ASSET_VER'] ?? date('ymdHis'));

// UI permission guard (use existing backend helper)
$canManageGroups = false;
try {
  $canManageGroups = hasGroupManagePermission($pdoPerm);
} catch (Throwable $e) {
  $canManageGroups = false;
}
$permDisabledAttr = $canManageGroups ? '' : 'disabled aria-disabled="true"';
$permDisabledClass = $canManageGroups ? '' : ' disabled';

// helper escape
if (!function_exists('h')) {
  function h($v){ return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
}

$PAGE_TITLE = (string)__('userGroup_page_title');

// CSRF
if (empty($_SESSION['csrf_token'])) {
  $_SESSION['csrf_token'] = bin2hex(random_bytes(16));
}
$csrf = $_SESSION['csrf_token'];

$menuModalModuleOptions = [];
try {
  $modStmt = $pdoPerm->query("
    SELECT
      f_modulID AS id,
      COALESCE(NULLIF(f_modulName_ms,''), NULLIF(f_modulName_en,''), CONCAT('Modul ', f_modulID)) AS nama
    FROM tbl_m_modul
    ORDER BY COALESCE(f_order, 99999), f_modulID ASC
  ");
  $menuModalModuleOptions = $modStmt ? ($modStmt->fetchAll(PDO::FETCH_ASSOC) ?: []) : [];
} catch (Throwable $e) {
  $menuModalModuleOptions = [];
}

// Default order for new module: max(f_order) + 1
$nextModuleOrder = 1;
try {
  $pdoOrder = Database::getInstance('mysql')->getConnection();
  $nextStmt = $pdoOrder->query("SELECT COALESCE(MAX(f_order), 0) + 1 AS next_order FROM tbl_m_modul");
  $nextVal = (int)($nextStmt->fetchColumn() ?: 1);
  $nextModuleOrder = ($nextVal > 0) ? $nextVal : 1;
} catch (Throwable $e) {
  $nextModuleOrder = 1;
}

$moduleIconOptions = SystemConfigConstants::ALLOWED_SIDEBAR_ICONS;
$moduleIconOptions = array_values(array_unique(array_filter(array_map(
  static fn($icon): string => trim((string)$icon),
  $moduleIconOptions
))));
$moduleIconCategoryMap = [
  'modul_icon_group_general' => [
    'ri-folder-fill',
    'ri-folder-line',
    'ri-apps-2-fill',
    'ri-apps-2-line',
    'ri-layout-grid-fill',
    'ri-layout-grid-line',
    'ri-home-fill',
    'ri-home-line',
    'ri-home-office-fill',
    'ri-home-office-line',
    'ri-function-fill',
    'ri-function-line',
  ],
  'modul_icon_group_users' => [
    'ri-user-fill',
    'ri-user-line',
    'ri-user-settings-fill',
    'ri-user-settings-line',
    'ri-user-star-fill',
    'ri-user-star-line',
    'ri-group-fill',
    'ri-group-line',
    'ri-team-fill',
    'ri-team-line',
    'ri-admin-fill',
    'ri-admin-line',
  ],
  'modul_icon_group_system' => [
    'ri-dashboard-fill',
    'ri-dashboard-line',
    'ri-settings-fill',
    'ri-settings-line',
    'ri-tools-fill',
    'ri-tools-line',
    'ri-sliders-fill',
    'ri-sliders-line',
    'ri-server-fill',
    'ri-server-line',
    'ri-hard-drive-fill',
    'ri-hard-drive-line',
  ],
  'modul_icon_group_files' => [
    'ri-file-list-fill',
    'ri-file-list-line',
    'ri-file-settings-fill',
    'ri-file-settings-line',
    'ri-file-copy-fill',
    'ri-file-copy-line',
    'ri-file-paper-2-fill',
    'ri-file-paper-2-line',
    'ri-file-text-fill',
    'ri-file-text-line',
    'ri-article-fill',
    'ri-article-line',
    'ri-book-fill',
    'ri-book-line',
    'ri-book-open-fill',
    'ri-book-open-line',
    'ri-book-2-fill',
    'ri-book-2-line',
    'ri-questionnaire-fill',
    'ri-questionnaire-line',
    'ri-survey-fill',
    'ri-survey-line',
    'ri-task-fill',
    'ri-task-line',
    'ri-todo-fill',
    'ri-todo-line',
    'ri-list-check',
    'ri-list-check-2',
  ],
  'modul_icon_group_data' => [
    'ri-database-fill',
    'ri-database-line',
    'ri-folder-chart-fill',
    'ri-folder-chart-line',
    'ri-folder-user-fill',
    'ri-folder-user-line',
    'ri-chart-fill',
    'ri-chart-line',
    'ri-bar-chart-fill',
    'ri-bar-chart-line',
    'ri-bar-chart-box-fill',
    'ri-bar-chart-box-line',
    'ri-line-chart-fill',
    'ri-line-chart-line',
    'ri-pie-chart-fill',
    'ri-pie-chart-line',
    'ri-calendar-fill',
    'ri-calendar-line',
    'ri-calendar-check-fill',
    'ri-calendar-check-line',
    'ri-time-fill',
    'ri-time-line',
  ],
  'modul_icon_group_security' => [
    'ri-shield-fill',
    'ri-shield-line',
    'ri-shield-user-fill',
    'ri-shield-user-line',
    'ri-lock-fill',
    'ri-lock-line',
    'ri-lock-password-fill',
    'ri-lock-password-line',
    'ri-key-fill',
    'ri-key-line',
    'ri-notification-fill',
    'ri-notification-line',
    'ri-notification-badge-fill',
    'ri-notification-badge-line',
  ],
  'modul_icon_group_communication' => [
    'ri-mail-fill',
    'ri-mail-line',
    'ri-inbox-fill',
    'ri-inbox-line',
    'ri-send-plane-fill',
    'ri-send-plane-line',
    'ri-links-fill',
    'ri-links-line',
    'ri-link-unlink-m',
    'ri-global-fill',
    'ri-global-line',
    'ri-earth-fill',
    'ri-earth-line',
    'ri-building-fill',
    'ri-building-line',
    'ri-building-2-fill',
    'ri-building-2-line',
    'ri-briefcase-fill',
    'ri-briefcase-line',
    'ri-award-fill',
    'ri-award-line',
    'ri-medal-fill',
    'ri-medal-line',
    'ri-wallet-3-fill',
    'ri-wallet-3-line',
    'ri-bank-card-fill',
    'ri-bank-card-line',
    'ri-price-tag-3-fill',
    'ri-price-tag-3-line',
    'ri-logout-box-r-fill',
    'ri-logout-box-r-line',
    'ri-arrow-right-s-fill',
    'ri-arrow-right-s-line',
  ],
];
$moduleIconGroups = [];
$usedModuleIcons = [];
foreach ($moduleIconCategoryMap as $groupKey => $icons) {
  $filtered = [];
  foreach ($icons as $iconClass) {
    if (!in_array($iconClass, $moduleIconOptions, true)) {
      continue;
    }
    $filtered[] = $iconClass;
    $usedModuleIcons[$iconClass] = true;
  }
  if ($filtered !== []) {
    $moduleIconGroups[] = [
      'label' => (string)__($groupKey),
      'icons' => array_values(array_unique($filtered)),
    ];
  }
}
$remainingModuleIcons = array_values(array_filter(
  $moduleIconOptions,
  static fn(string $iconClass): bool => !isset($usedModuleIcons[$iconClass])
));
if ($remainingModuleIcons !== []) {
  $moduleIconGroups[] = [
    'label' => (string)__('modul_icon_group_more'),
    'icons' => $remainingModuleIcons,
  ];
}

// Add Module (AJAX only; audited through public/ajax/module-create.php)
$moduleFormData = [
  'modulNameMs' => '',
  'modulNameEn' => '',
  'icon' => '',
  'order' => (string)$nextModuleOrder,
];
$defaultModuleIcon = 'ri-folder-fill';
$moduleFormOpen = false;
$moduleSwal = null;

if (!empty($_SESSION['module_add_flash']) && is_array($_SESSION['module_add_flash'])) {
  $flash = $_SESSION['module_add_flash'];
  unset($_SESSION['module_add_flash']);
  $moduleSwal = [
    'icon' => (string)($flash['icon'] ?? 'success'),
    'title' => (string)($flash['title'] ?? ''),
    'text' => (string)($flash['text'] ?? ''),
  ];
}

$defaultModuleIcon = in_array($moduleFormData['icon'], $moduleIconOptions, true)
  ? (string)$moduleFormData['icon']
  : 'ri-folder-fill';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && (string)($_POST['action'] ?? '') === 'add_module') {
  $moduleFormOpen = true;
  $moduleFormData['modulNameMs'] = trim((string)($_POST['modulNameMs'] ?? ''));
  $moduleFormData['modulNameEn'] = trim((string)($_POST['modulNameEn'] ?? ''));
  $moduleFormData['icon'] = trim((string)($_POST['icon'] ?? ''));
  $moduleFormData['order'] = trim((string)($_POST['order'] ?? ''));

  $moduleSwal = [
    'icon' => 'error',
    'title' => (string)__('modul_ralat_title'),
    'text' => (string)__('modul_ajax_only_guard'),
  ];
}
?>
<!DOCTYPE html>
<html lang="<?= h($lang) ?>" data-bs-theme="<?= h($_SESSION['theme.layout'] ?? 'light') ?>">
<head>
  <?php include __DIR__ . '/../includes/head.php'; ?>

  <meta name="csrf-token" content="<?= h($csrf) ?>">
  <!-- ✅ Standard DataTables CSS (shared) -->
  <link href="<?= base_url('assets/css/datatables-standard.css') ?>?v=<?= h($version ?? date('ymdHis')) ?>" rel="stylesheet">
  <style>
    .icon-btn { line-height:1; }

    /* Kolum jadual kumpulan - use percentages to match requested layout */
    table.table th.th-nowrap { white-space: nowrap; }
    table.table th.th-color, table.table td.td-color { width: 4%; min-width: 46px; max-width: 56px; }
    table.table th.th-mod,  table.table td.td-mod  { width: 10%; }
    table.table th.th-menu, table.table td.td-menu { width: 10%; }
    table.table th.th-grp,  table.table td.td-grp  { width: 10%; }
    .group-color-cell { display: flex; justify-content: flex-end; }
    .group-color-bar {
      display: inline-block;
      width: 22px;
      height: 22px;
      border-radius: 5px;
      border: 1px solid rgba(15, 23, 42, 0.22);
      box-shadow: inset 0 0 0 1px rgba(255, 255, 255, 0.25);
    }
    html[data-bs-theme="dark"] .group-color-bar { border-color: rgba(148, 163, 184, 0.55); }

    /* Reorder view */
    .modul-badge { font-size:.75rem; }
    .menu-path { font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, "Liberation Mono", monospace; font-size:.775rem; opacity:.8; }
    .menu-row { display:grid; grid-template-columns: 1fr auto; gap:.75rem; padding:.6rem 0; border-bottom:1px dashed var(--bs-border-color); }
    .menu-row:last-child { border-bottom:0; }
    .subgroup-order-row {
      margin-top: .25rem;
      padding: .72rem .8rem;
      border: 1px solid rgba(20, 184, 166, .18);
      border-radius: 8px;
      background: rgba(20, 184, 166, .06);
    }
    .subgroup-menu-child {
      margin-left: 1.35rem;
      padding-left: .8rem;
      border-left: 2px solid rgba(20, 184, 166, .2);
    }
    .subgroup-order-title i {
      color: #0f766e;
    }
    html[data-bs-theme="dark"] .subgroup-order-row {
      border-color: rgba(45, 212, 191, .2);
      background: rgba(45, 212, 191, .08);
    }
    html[data-bs-theme="dark"] .subgroup-menu-child {
      border-left-color: rgba(45, 212, 191, .22);
    }
    .subgroup-manager-form {
      border: 1px solid rgba(148, 163, 184, .18);
      border-radius: 8px;
      background: linear-gradient(180deg, rgba(255,255,255,.98), rgba(248,250,252,.92));
      box-shadow: 0 14px 28px rgba(15, 23, 42, .06);
    }
    .subgroup-form-section {
      padding: 1rem;
      border-bottom: 1px solid rgba(148, 163, 184, .14);
    }
    .subgroup-form-section:last-child {
      border-bottom: 0;
    }
    .subgroup-section-title {
      display: flex;
      align-items: center;
      gap: .45rem;
      margin-bottom: .85rem;
      font-size: .78rem;
      font-weight: 700;
      color: #64748b;
      text-transform: uppercase;
      letter-spacing: .04em;
    }
    .subgroup-icon-picker {
      display: grid;
      grid-template-columns: repeat(4, minmax(0, 1fr));
      gap: .5rem;
    }
    .subgroup-icon-option {
      height: 42px;
      border: 1px solid rgba(148, 163, 184, .28);
      border-radius: 8px;
      background: #fff;
      color: #475569;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      font-size: 1.12rem;
      transition: border-color .16s ease, color .16s ease, background-color .16s ease, box-shadow .16s ease, transform .16s ease;
    }
    .subgroup-icon-option:hover {
      color: #0f766e;
      border-color: rgba(20, 184, 166, .45);
      background: rgba(20, 184, 166, .06);
      transform: translateY(-1px);
    }
    .subgroup-icon-option.active {
      color: #0f766e;
      border-color: rgba(20, 184, 166, .7);
      background: rgba(20, 184, 166, .1);
      box-shadow: 0 0 0 3px rgba(20, 184, 166, .12);
    }
    .subgroup-order-chip {
      display: inline-flex;
      align-items: center;
      gap: .4rem;
      min-height: 38px;
      padding: .45rem .72rem;
      border: 1px solid rgba(148, 163, 184, .24);
      border-radius: 8px;
      background: rgba(15, 23, 42, .03);
      color: #475569;
      font-weight: 600;
    }
    .subgroup-table-card {
      border: 1px solid rgba(148, 163, 184, .18);
      border-radius: 8px;
      overflow: hidden;
      box-shadow: 0 14px 28px rgba(15, 23, 42, .05);
    }
    #menuSubgroupTable thead th {
      font-size: .72rem;
      text-transform: uppercase;
      letter-spacing: .04em;
      color: #64748b;
    }
    #menuSubgroupError {
      margin-left: 1rem;
      margin-right: 1rem;
    }
    #menuDT th.col-status,
    #menuDT td.col-status,
    #menuDT th.col-actions,
    #menuDT td.col-actions {
      white-space: nowrap;
      vertical-align: top;
    }
    #menuDT td.col-status,
    #menuDT td.col-actions {
      padding-left: .45rem;
      padding-right: .45rem;
    }
    .menu-status-toggle {
      display: inline-flex;
      align-items: center;
      gap: .28rem;
      white-space: nowrap;
    }
    .menu-status-toggle .btn {
      min-width: 42px;
      padding: .24rem .42rem;
      font-size: .72rem;
      line-height: 1.2;
    }
    .menu-action-group {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      gap: .35rem;
      white-space: nowrap;
    }
    .menu-action-group .icon-btn {
      width: 30px;
      height: 30px;
      padding: 0;
      display: inline-flex;
      align-items: center;
      justify-content: center;
    }
    html[data-bs-theme="dark"] .subgroup-manager-form,
    html[data-bs-theme="dark"] .subgroup-icon-option {
      background: rgba(15, 23, 42, .92);
      border-color: rgba(148, 163, 184, .22);
    }
    html[data-bs-theme="dark"] .subgroup-form-section {
      border-bottom-color: rgba(148, 163, 184, .16);
    }
    html[data-bs-theme="dark"] .subgroup-order-chip {
      background: rgba(148, 163, 184, .1);
      border-color: rgba(148, 163, 184, .2);
      color: #cbd5e1;
    }
    .reorder-group .btn { padding:.25rem .55rem; }
    .menu-row.saving { opacity:.6; pointer-events:none; }
    .module-reorder-note {
      font-size: .82rem;
      color: #64748b;
      margin-bottom: .9rem;
    }
    .module-drag-handle {
      width: 2rem;
      height: 2rem;
      border-radius: 8px;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      color: #64748b;
      background: rgba(148, 163, 184, 0.12);
      cursor: grab;
      flex: 0 0 auto;
      margin-right: .75rem;
      touch-action: none;
      user-select: none;
    }
    .module-drag-handle:active { cursor: grabbing; }
    .module-ordering .accordion-button {
      display: flex;
      align-items: center;
    }
    .module-title-row {
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 1rem;
      width: 100%;
    }
    .module-title-main {
      min-width: 0;
    }
    .module-inline-actions {
      display: inline-flex;
      align-items: center;
      gap: .5rem;
      margin-right: 2.25rem;
      position: relative;
      z-index: 2;
    }
    .module-delete-chip {
      display: inline-flex;
      align-items: center;
      gap: .4rem;
      padding: .32rem .62rem;
      border-radius: 999px;
      border: 1px solid rgba(239, 68, 68, 0.18);
      background: rgba(239, 68, 68, 0.08);
      color: #dc2626;
      font-size: .76rem;
      font-weight: 600;
      line-height: 1;
      cursor: pointer;
      transition: background-color .18s ease, border-color .18s ease, color .18s ease, transform .18s ease;
      user-select: none;
    }
    .module-edit-chip {
      display: inline-flex;
      align-items: center;
      gap: .4rem;
      padding: .32rem .62rem;
      border-radius: 999px;
      border: 1px solid rgba(37, 99, 235, 0.18);
      background: rgba(37, 99, 235, 0.08);
      color: #1d4ed8;
      font-size: .76rem;
      font-weight: 600;
      line-height: 1;
      cursor: pointer;
      transition: background-color .18s ease, border-color .18s ease, color .18s ease, transform .18s ease;
      user-select: none;
    }
    .module-edit-chip:hover {
      background: rgba(37, 99, 235, 0.14);
      border-color: rgba(37, 99, 235, 0.3);
      color: #1e40af;
      transform: translateY(-1px);
    }
    .module-edit-chip:focus-visible {
      outline: 2px solid rgba(37, 99, 235, 0.18);
      outline-offset: 2px;
    }
    .module-edit-chip i {
      font-size: .92rem;
    }
    .module-edit-text {
      letter-spacing: .01em;
    }
    .module-delete-chip:hover {
      background: rgba(239, 68, 68, 0.14);
      border-color: rgba(239, 68, 68, 0.3);
      color: #b91c1c;
      transform: translateY(-1px);
    }
    .module-delete-chip:focus-visible {
      outline: 2px solid rgba(220, 38, 38, 0.18);
      outline-offset: 2px;
    }
    .module-delete-chip i {
      font-size: .92rem;
    }
    .module-delete-text {
      letter-spacing: .01em;
    }
    .module-ordering .accordion-button::after {
      position: relative;
      z-index: 1;
      margin-left: .85rem;
    }
    .module-ordering .accordion-button.collapsed .module-inline-actions,
    .module-ordering .accordion-button .module-inline-actions {
      pointer-events: auto;
    }
    html[data-bs-theme="dark"] .module-delete-chip {
      background: rgba(248, 113, 113, 0.12);
      border-color: rgba(248, 113, 113, 0.18);
      color: #fca5a5;
    }
    html[data-bs-theme="dark"] .module-delete-chip:hover {
      background: rgba(248, 113, 113, 0.18);
      border-color: rgba(248, 113, 113, 0.3);
      color: #fecaca;
    }
    html[data-bs-theme="dark"] .module-edit-chip {
      background: rgba(96, 165, 250, 0.14);
      border-color: rgba(96, 165, 250, 0.2);
      color: #bfdbfe;
    }
    html[data-bs-theme="dark"] .module-edit-chip:hover {
      background: rgba(96, 165, 250, 0.2);
      border-color: rgba(96, 165, 250, 0.34);
      color: #dbeafe;
    }
    @media (max-width: 767.98px) {
      .module-title-row {
        gap: .7rem;
      }
      .module-inline-actions {
        margin-right: 1.5rem;
      }
      .module-delete-text {
        display: none;
      }
      .module-edit-text {
        display: none;
      }
    }
    .module-delete-btn {
      flex: 0 0 auto;
      align-self: center;
      width: 2.2rem;
      height: 2.2rem;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      border-radius: 8px;
    }
    .module-ordering .accordion-item {
      transition: transform .18s ease, border-color .18s ease, box-shadow .18s ease;
    }
    .module-ordering .accordion-item.is-dragging {
      opacity: .65;
      transform: scale(.985);
    }
    .module-ordering .accordion-item.drop-before {
      border-top: 3px solid #2563eb;
    }
    .module-ordering .accordion-item.drop-after {
      border-bottom: 3px solid #2563eb;
    }
    .module-ordering.is-saving {
      pointer-events: none;
      opacity: .7;
    }
    body.module-dragging {
      user-select: none;
      cursor: grabbing;
    }
    html[data-bs-theme="dark"] .module-drag-handle {
      background: rgba(148, 163, 184, 0.18);
      color: #cbd5e1;
    }

    /* DataTable: top & bottom bars */
    .dt-topbar{
      display:flex; align-items:center; justify-content:space-between;
      gap:.75rem; border-bottom:1px solid var(--bs-border-color); padding-bottom:.5rem; margin-bottom:.75rem;
    }
    .dt-topbar .left, .dt-topbar .right{ display:flex; align-items:center; gap:.5rem; }
    .dt-topbar .right .form-control{ width:260px; max-width:100%; }
    .dt-bottom-row { display:flex; align-items:center; justify-content:space-between; flex-wrap:nowrap; gap:.5rem; }
    @media (max-width:575.98px){ .dt-topbar{ flex-wrap:wrap; } .dt-topbar .right{ margin-left:auto; } }

    /* Fix DataTables layout untuk groupTable - prevent horizontal scroll */
    #groupTable_wrapper {
      overflow-x: hidden;
      width: 100%;
      max-width: 100%;
    }
    
    /* ✅ Table styling sama seperti senarai-pengguna.php */
    #groupTable {
      border-radius: 8px;
      overflow: hidden;
      box-shadow: 0 14px 26px rgba(15,23,42,.08);
      table-layout: fixed;
      border-collapse: separate !important;
      border-spacing: 0 !important;
    }
    #groupTable thead th {
      background: linear-gradient(180deg, rgba(248,250,252,.96), rgba(241,245,249,.92));
      border-bottom: 1px solid rgba(148,163,184,.16) !important;
      font-size: .78rem;
      text-transform: uppercase;
      letter-spacing: .05em;
      color: #64748b;
      font-weight: 700;
      padding: 1rem 0.75rem;
      border-top: none !important;
      border-left: none !important;
      border-right: none !important;
    }
    #groupTable thead th:first-child,
    #groupTable tbody td:first-child {
      text-align: center;
    }
    #groupTable tbody tr {
      transition: background-color .16s ease, transform .16s ease;
    }
    #groupTable tbody tr:hover {
      background: rgba(15,23,42,.018) !important;
    }
    #groupTable tbody td {
        padding: .62rem .78rem;
        border-top-color: rgba(148,163,184,.1) !important;
        vertical-align: middle;
        line-height: 1.28;
      }
    /* Dark theme support */
    html[data-bs-theme="dark"] #groupTable thead th {
      background: linear-gradient(180deg, rgba(30,41,59,.92), rgba(15,23,42,.95));
      color: #cbd5e1;
      border-bottom-color: rgba(255,255,255,.08) !important;
    }
    html[data-bs-theme="dark"] #groupTable tbody td {
      border-top-color: rgba(255,255,255,.06) !important;
    }
    html[data-bs-theme="dark"] #groupTable tbody tr:hover {
      background: rgba(148,163,184,.08) !important;
    }
    /* Remove stripline effect */
    #groupTable tbody tr,
    #groupTable tbody tr:nth-of-type(odd),
    #groupTable tbody tr:nth-of-type(even) {
      background-color: transparent !important;
    }
    #groupTable_wrapper .dataTables_length {
      display: flex;
      align-items: center;
      white-space: nowrap;
      overflow: hidden;
      flex-wrap: nowrap;
      max-width: 100%;
    }
    #groupTable_wrapper .dataTables_length label {
      display: flex;
      align-items: center;
      white-space: nowrap;
      margin-bottom: 0;
      flex-wrap: nowrap;
      gap: 0.5rem;
      max-width: 100%;
      overflow: hidden;
    }
    #groupTable_wrapper .dataTables_length select {
      margin: 0;
      width: auto;
      min-width: 70px;
      max-width: 100px;
      display: inline-block;
    }
    #groupTable_wrapper .dataTables_filter {
      text-align: right;
      margin-left: auto;
      max-width: 100%;
      overflow: hidden;
    }
    #groupTable_wrapper .dataTables_filter label {
      display: flex;
      align-items: center;
      justify-content: flex-end;
      margin-bottom: 0;
      width: 100%;
      white-space: nowrap;
      max-width: 100%;
      overflow: hidden;
    }
    #groupTable_wrapper .dataTables_filter input {
        margin-left: 0.5rem;
        width: auto;
        min-width: 150px;
        max-width: 250px;
        height: 36px !important;
        min-height: 36px !important;
        line-height: 1.4 !important;
      }
    #groupTable_wrapper .dataTables_info {
      display: flex;
      align-items: center;
      white-space: nowrap;
      overflow: hidden;
      text-overflow: ellipsis;
      max-width: 100%;
    }
    #groupTable_wrapper .dataTables_paginate {
      text-align: right;
      margin-left: auto;
      max-width: 100%;
      overflow: hidden;
    }
    #groupTable_wrapper .dataTables_paginate .pagination {
      justify-content: flex-end;
      margin-bottom: 0;
      flex-wrap: nowrap;
    }
    #groupTable_wrapper .row {
      margin-left: -0.75rem;
      margin-right: -0.75rem;
      max-width: 100%;
      overflow-x: hidden;
    }
    #groupTable_wrapper .row > [class*="col-"] {
        padding-left: 0.75rem;
        padding-right: 0.75rem;
        max-width: 100%;
        overflow: hidden;
      }
    #groupTable_wrapper .row.mb-2 > .dt-top-left {
        padding-left: 1.55rem !important;
        position: relative !important;
        top: 7px !important;
      }
    #groupTable_wrapper .dt-top-left {
        padding-left: 1.55rem !important;
      }
    #groupTable_length {
        margin-left: 0 !important;
      }
    #groupTable_length .dataTables_length,
    #groupTable_length label {
        padding-left: 0 !important;
        margin-left: 0 !important;
      }
    #groupTable_wrapper .dt-top-left .dataTables_length,
    #groupTable_wrapper .dt-top-left .dataTables_length label {
        margin-left: 0 !important;
        padding-left: 0 !important;
      }
    #groupTable_wrapper .dt-bottom-row .dt-info-left,
    #groupTable_wrapper .dt-bottom-row .dataTables_info {
        padding-left: 1.55rem;
      }
    #groupTable_wrapper .dt-bottom-row > .dt-info-left {
        padding-left: 1.2rem !important;
        margin-left: 0 !important;
      }
    #groupTable_wrapper .dt-bottom-row > .dt-info-left .dataTables_info {
        padding-left: 0 !important;
        margin-left: 0 !important;
      }
    /* Fix bottom row layout */
    #groupTable_wrapper .row.mt-3 {
        display: flex;
      align-items: center;
      justify-content: space-between;
      max-width: 100%;
      overflow-x: hidden;
    }
    #groupTable_wrapper .row.mt-3 > [class*="col-md-5"] {
      display: flex;
      align-items: center;
      max-width: 100%;
      overflow: hidden;
    }
    #groupTable_wrapper .row.mt-3 > [class*="col-md-7"] {
      display: flex;
      align-items: center;
      justify-content: flex-end;
      max-width: 100%;
      overflow: hidden;
    }
    /* Ensure table container doesn't overflow */
    .table-responsive {
      overflow-x: auto;
      -webkit-overflow-scrolling: touch;
    }
    @media (max-width: 991.98px) {
      .table-responsive {
        overflow-x: auto;
      }
    }
    @media (max-width: 767.98px) {
      #groupTable_wrapper .dataTables_length,
      #groupTable_wrapper .dataTables_filter {
        margin-bottom: 0.75rem;
      }
      #groupTable_wrapper .dataTables_filter {
        text-align: left;
        margin-left: 0;
      }
      #groupTable_wrapper .dataTables_filter label {
        justify-content: flex-start;
      }
      #groupTable_wrapper .dataTables_info,
      #groupTable_wrapper .dataTables_paginate {
        text-align: center;
        margin-top: 0.5rem;
      }
      #groupTable_wrapper .row.mt-3 {
        flex-direction: column;
        align-items: stretch;
      }
      #groupTable_wrapper .row.mt-3 > [class*="col-md-5"],
      #groupTable_wrapper .row.mt-3 > [class*="col-md-7"] {
        justify-content: center;
        margin-top: 0.5rem;
      }
    }

    /* Buang class lama yang tak digunakan */
    #menuDT_wrapper .dt-top-right, #grpCnt .dt-top-right { display:none!important; }

    /* Akses Kumpulan – kolum “Menu”: kecil & 1 baris */
    #groupPermsDT th.col-menu, #groupPermsDT td.col-menu {
      width: 140px; white-space: nowrap;
    }

    /* Akses Menu */
    #aksesMenuModal .modal-dialog { max-width: 1180px; }
    #aksesMenuModal .modal-content {
      border: 1px solid rgba(148,163,184,.16);
      overflow: hidden;
    }
    #aksesMenuModal .modal-header {
      background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
      color: white;
      border-bottom: none;
    }
    #aksesMenuModal .modal-subtitle {
      background: rgba(102, 126, 234, 0.08);
      border-bottom: 1px solid rgba(102, 126, 234, 0.12);
      color: #4c63d2;
    }
    #aksesMenuModal .modal-body {
      padding-top: 1rem;
    }
    #menuDT {
      table-layout: fixed;
    }
    #menuDT thead th,
    #menuDT tbody td {
      vertical-align: top !important;
    }
    #menuDT thead th {
      padding: .78rem .8rem;
      font-size: .79rem;
      letter-spacing: .04em;
    }
    #menuDT tbody td {
      padding: .62rem .8rem;
      font-size: .92rem;
      line-height: 1.3;
    }
    #menuDT tbody .fw-semibold {
      line-height: 1.25;
    }
    #menuDT th:nth-child(1), #menuDT td:nth-child(1) { width: 20%; text-align: left; }
    #menuDT th:nth-child(2), #menuDT td:nth-child(2) { width: 20%; text-align: left; }
    #menuDT th:nth-child(3), #menuDT td:nth-child(3) { width: 30%; text-align: left; }
    #menuDT th.col-status, #menuDT td.col-status { width: 15%; white-space: nowrap; text-align: center; }
    #menuDT th.col-actions, #menuDT td.col-actions { width: 15%; white-space: nowrap; text-align: center; }
    #menuDT td:nth-child(3) .d-flex {
      flex-wrap: nowrap !important;
      overflow: hidden;
      white-space: nowrap;
      gap: .35rem !important;
    }
    #menuDT td:nth-child(3) .badge {
      max-width: 100%;
      overflow: hidden;
      text-overflow: ellipsis;
      white-space: nowrap;
      font-size: .72rem;
      padding: .32rem .58rem;
    }
    #menuDT .menu-domain-badge {
      border: 1px solid rgba(148,163,184,.24);
      font-weight: 700;
      letter-spacing: .03em;
    }
    #menuDT .menu-domain-badge[data-domain="STAF"] {
      background: rgba(59,130,246,.12);
      color: #1d4ed8;
      border-color: rgba(59,130,246,.28);
    }
    #menuDT .menu-domain-badge[data-domain="PELAJAR"] {
      background: rgba(168,85,247,.12);
      color: #7e22ce;
      border-color: rgba(168,85,247,.28);
    }
    #menuDT .menu-domain-badge[data-domain="UMUM"] {
      background: rgba(245,158,11,.14);
      color: #b45309;
      border-color: rgba(245,158,11,.28);
    }
    #menuDT .menu-domain-badge[data-domain="SHARED"] {
      background: rgba(71,85,105,.1);
      color: #334155;
      border-color: rgba(100,116,139,.24);
    }
    #menuDT .menu-path-info {
      text-decoration: none !important;
      line-height: 1;
      top: -1px;
      position: relative;
    }
    #menuDT .menu-path-info i {
      font-size: .95rem;
    }
    #menuDT .btn-sm {
      padding: .24rem .46rem;
      font-size: .69rem;
    }
    #menuDT .icon-btn {
      width: 28px;
      height: 28px;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      padding: 0;
    }
    #menuDT td.col-status .btn {
      min-width: 48px;
      margin-right: .2rem !important;
    }
    #menuDT td.col-actions .icon-btn + .icon-btn {
      margin-left: .18rem !important;
    }

    /* Multi-modal stacking */
    .modal-backdrop.show + .modal-backdrop.show { z-index: 1065; }
    .modal.show { z-index: 1055; }
    .modal.show + .modal.show { z-index: 1070; }
    .modal-backdrop.show {
      opacity: .32;
    }
    .modal.show + .modal-backdrop.show,
    .modal-backdrop.show + .modal-backdrop.show {
      opacity: .16;
    }

    /* Professional SweetAlert */
      .group-swal-popup {
        border: 1px solid rgba(148, 163, 184, 0.18) !important;
        border-radius: 8px !important;
        box-shadow: 0 30px 80px rgba(15, 23, 42, 0.22), 0 10px 30px rgba(15, 23, 42, 0.08) !important;
        padding: 1.1rem 1.1rem 1rem !important;
      }
      .group-swal-container {
        z-index: 20000 !important;
      }
      .group-swal-title {
        font-size: 1.35rem !important;
        font-weight: 800 !important;
        letter-spacing: -0.02em !important;
      color: #0f172a !important;
      margin-bottom: .35rem !important;
    }
    .group-swal-html,
    .group-swal-text {
      font-size: .96rem !important;
      line-height: 1.65 !important;
      color: #475569 !important;
    }
    .group-swal-confirm,
    .group-swal-cancel {
      min-width: 132px !important;
      border-radius: 8px !important;
      font-weight: 700 !important;
      font-size: .94rem !important;
      padding: .72rem 1.1rem !important;
      box-shadow: none !important;
    }
    .group-swal-confirm {
      background: linear-gradient(135deg, #2563eb 0%, #1d4ed8 100%) !important;
    }
    .group-swal-confirm:hover {
      background: linear-gradient(135deg, #1d4ed8 0%, #1e40af 100%) !important;
    }
    .group-swal-cancel {
      background: #fff !important;
      color: #334155 !important;
      border: 1px solid rgba(148, 163, 184, 0.45) !important;
    }
    .group-swal-actions {
      gap: .65rem !important;
      margin-top: 1.2rem !important;
    }
    .group-swal-icon.swal2-success { border-color: rgba(34, 197, 94, 0.22) !important; }
    .group-swal-icon.swal2-error { border-color: rgba(239, 68, 68, 0.22) !important; }
    .group-swal-icon.swal2-warning { border-color: rgba(245, 158, 11, 0.24) !important; }
    .group-swal-icon.swal2-info { border-color: rgba(59, 130, 246, 0.24) !important; }

    /* Professional Modal Styling */
      .modal-content {
        border: none;
        border-radius: 8px;
        box-shadow: none !important;
        outline: 0 !important;
        filter: none !important;
        overflow: hidden;
      }
      .modal,
      .modal-dialog,
      .modal-content,
      .modal-content::before,
      .modal-content::after {
        box-shadow: none !important;
        outline: 0 !important;
        filter: none !important;
      }
      .modal-dialog {
        border: 0 !important;
        background: transparent !important;
      }
      .modal.fade {
        transition: none !important;
      }
      .modal.fade .modal-dialog {
        transition: none !important;
        transform: none !important;
      }
      .modal.show .modal-dialog {
        transform: none !important;
      }
      .modal.show .modal-content {
        box-shadow: none !important;
      }
    .modal.show + .modal.show .modal-content,
    body .modal.show[style*="display: block"] .modal-content {
      box-shadow: none !important;
    }

    .modal-header {
      background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
      color: white;
      padding: 1.1rem 1.35rem;
      border-bottom: none;
      position: relative;
    }

    .modal-header.bg-body-tertiary {
      background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
      color: white;
    }

    .modal-header .modal-title {
      color: white;
      font-weight: 600;
      font-size: 1.25rem;
      display: flex;
      align-items: center;
      gap: 0.75rem;
    }

    .modal-header .modal-title i {
      font-size: 1.5rem;
      opacity: 0.95;
    }

    .modal-header .btn-close {
      filter: brightness(0) invert(1);
      opacity: 0.9;
      transition: opacity 0.2s;
    }

    .modal-header .btn-close:hover {
      opacity: 1;
    }

    .modal-subtitle {
      padding: 0.65rem 1.35rem;
      background: rgba(102, 126, 234, 0.08);
      border-bottom: 1px solid rgba(0, 0, 0, 0.05);
      font-size: 0.875rem;
      color: #6c757d;
      font-weight: 500;
    }

    .modal-body {
      padding: 1.35rem;
      background: #fff;
    }

    .modal-footer {
      padding: 1rem 1.35rem;
      background: #f8f9fa;
      border-top: 1px solid rgba(0, 0, 0, 0.08);
      border-radius: 0 0 8px 8px;
    }

    .modal-footer .btn {
      padding: 0.5rem 1.15rem;
      font-weight: 600;
      border-radius: 8px;
      transition: all 0.2s ease;
    }

    .modal-footer .btn-primary {
      background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
      border: none;
      box-shadow: 0 4px 12px rgba(102, 126, 234, 0.3);
    }

    .modal-footer .btn-primary:hover {
      transform: translateY(-1px);
      box-shadow: 0 6px 16px rgba(102, 126, 234, 0.4);
    }

    /* Loading States */
    .modal-loading {
      display: flex;
      flex-direction: column;
      align-items: center;
      justify-content: center;
      padding: 3rem 2rem;
      text-align: center;
    }

    .modal-loading .spinner-border {
      width: 3rem;
      height: 3rem;
      border-width: 3px;
      color: #667eea;
      margin-bottom: 1rem;
    }

    .modal-loading span {
      color: #6c757d;
      font-size: 0.95rem;
      font-weight: 500;
    }

    /* Error States */
    .modal-error {
      padding: 1rem 1.25rem;
      border-radius: 8px;
      background: #fee;
      border-left: 4px solid #dc3545;
      margin-bottom: 1rem;
    }

    /* Form Styling in Modals */
    .modal-body .form-label {
      font-weight: 600;
      color: #495057;
      margin-bottom: 0.5rem;
      font-size: 0.9rem;
    }

    .modal-body .form-control,
    .modal-body .form-select {
      border-radius: 8px;
      border: 1.5px solid #e0e0e0;
      padding: 0.625rem 0.875rem;
      transition: all 0.2s ease;
    }

    .modal-body .form-control:focus,
    .modal-body .form-select:focus {
      border-color: #667eea;
      box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.15);
    }

    /* Make module/menu multi-selects in the create modal taller for easier selection */
    #gc_moduls, #gc_menus {
      min-height: 200px;
      height: 200px;
      overflow: auto;
    }
    @media (max-width: 767.98px) {
      #gc_moduls, #gc_menus { min-height: 140px; height: 140px; }
    }
    .group-create-section-title {
      display:flex;
      align-items:center;
      gap:.5rem;
      font-weight:700;
      font-size:.9rem;
      color:#334155;
      margin-bottom:.7rem;
    }
    .group-create-section-title i {
      color:#2563eb;
    }
    .group-create-preview {
      display:flex;
      align-items:center;
      justify-content:space-between;
      gap:1rem;
      padding:.95rem 1rem;
      border:1px solid rgba(148,163,184,.22);
      border-radius:10px;
      background:linear-gradient(180deg,#ffffff,#f8fafc);
      min-height:60px;
    }
    .group-create-preview-meta {
      min-width:0;
    }
    .group-create-preview-label {
      font-size:.72rem;
      font-weight:700;
      letter-spacing:.08em;
      text-transform:uppercase;
      color:#64748b;
      margin-bottom:.35rem;
    }
    .group-create-preview-main {
      display:flex;
      align-items:center;
      gap:.6rem;
      flex-wrap:wrap;
    }
    .group-create-preview-code {
      font-weight:700;
      color:#0f172a;
    }
    .group-create-preview-chip {
      display:inline-flex;
      align-items:center;
      gap:.4rem;
      padding:.38rem .7rem;
      border-radius:999px;
      font-size:.8rem;
      font-weight:700;
      color:#0f172a;
      background:#e2e8f0;
      border:1px solid rgba(148,163,184,.24);
      white-space:nowrap;
    }
    .group-category-chip[data-category="STAF"] {
      background:rgba(59,130,246,.12);
      color:#1d4ed8;
      border-color:rgba(59,130,246,.24);
    }
    .group-category-chip[data-category="PELAJAR"] {
      background:rgba(168,85,247,.12);
      color:#7e22ce;
      border-color:rgba(168,85,247,.26);
    }
    .group-category-chip[data-category="UMUM"] {
      background:rgba(245,158,11,.14);
      color:#b45309;
      border-color:rgba(245,158,11,.28);
    }
    #groupTable .group-table-category-chip {
      gap: .28rem;
      padding: .24rem .58rem;
      font-size: .69rem;
      font-weight: 600;
      letter-spacing: .02em;
      line-height: 1.15;
      text-transform: uppercase;
      box-shadow: inset 0 1px 0 rgba(255,255,255,.28);
    }
    html[data-bs-theme="dark"] #groupTable .group-table-category-chip {
      box-shadow: inset 0 1px 0 rgba(255,255,255,.08);
    }
    html[data-bs-theme="dark"] .group-create-section-title {
      color:#e2e8f0;
    }
    html[data-bs-theme="dark"] .group-create-preview {
      background:linear-gradient(180deg,rgba(15,23,42,.92),rgba(15,23,42,.86));
      border-color:rgba(148,163,184,.22);
    }
    html[data-bs-theme="dark"] .group-create-preview-code {
      color:#f8fafc;
    }
    html[data-bs-theme="dark"] .group-create-preview-label {
      color:#94a3b8;
    }
    #groupCreateForm .row.g-3 {
      --bs-gutter-y: .75rem;
    }
    #groupCreateForm .mt-4 {
      margin-top: 1rem !important;
    }
    #groupCreateForm .mt-1 {
      margin-top: .15rem !important;
    }

    /* Search Input in Modals */
    .modal-body input[type="search"] {
      border-radius: 8px;
      padding: 0.75rem 1rem;
      border: 2px solid #e9ecef;
      transition: all 0.2s ease;
    }

    .modal-body input[type="search"]:focus {
      border-color: #667eea;
      box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.15);
    }

    /* Themed Modal Accent */
    .modal-themed .modal-content {
      position: relative;
    }

    .modal-themed .modal-content::before {
      content: none;
      position: absolute;
      inset: 0 0 auto 0;
      height: 3px;
      background: linear-gradient(90deg, #667eea 0%, #764ba2 100%);
      border-top-left-radius: 12px;
      border-top-right-radius: 12px;
      pointer-events: none;
    }

    /* Child Modal Standard */
    .modal-child-accent .modal-header {
      background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
    }
    #menuEditModal .modal-dialog {
      max-width: 1040px;
    }

    .modal-child-accent .modal-footer {
      gap: .75rem;
      flex-wrap: wrap;
    }
    .modal-child-accent .modal-footer .btn {
      min-width: 140px;
      justify-content: center;
    }
    .modal-child-accent .modal-footer .btn-primary {
      background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
      box-shadow: 0 4px 12px rgba(245, 87, 108, 0.3);
    }
    .modal-child-accent .modal-footer .btn-primary,
    .modal-child-accent .modal-footer .btn-primary span,
    .modal-child-accent .modal-footer .btn-primary i {
      color: #fff !important;
    }

    .modal-child-accent .modal-footer .btn-primary:hover {
      box-shadow: 0 6px 16px rgba(245, 87, 108, 0.4);
    }

    .tooltip.menu-path-tooltip .tooltip-inner {
      max-width: 520px;
      text-align: left;
      white-space: normal;
      overflow: hidden;
      display: -webkit-box;
      -webkit-line-clamp: 2;
      -webkit-box-orient: vertical;
      line-height: 1.35;
      padding: .65rem .8rem;
    }
    html[data-bs-theme="dark"] #aksesMenuModal .modal-content {
      border-color: rgba(148,163,184,.22);
    }
    html[data-bs-theme="dark"] #aksesMenuModal .modal-header {
      background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    }
    html[data-bs-theme="dark"] #aksesMenuModal .modal-subtitle {
      background: rgba(102, 126, 234, 0.14);
      border-bottom-color: rgba(102, 126, 234, 0.24);
      color: #c7d2fe;
    }
    html[data-bs-theme="dark"] #menuDT .menu-domain-badge[data-domain="STAF"] {
      background: rgba(59,130,246,.18);
      color: #bfdbfe;
      border-color: rgba(96,165,250,.3);
    }
    html[data-bs-theme="dark"] #menuDT .menu-domain-badge[data-domain="PELAJAR"] {
      background: rgba(168,85,247,.18);
      color: #e9d5ff;
      border-color: rgba(192,132,252,.3);
    }
    html[data-bs-theme="dark"] #menuDT .menu-domain-badge[data-domain="UMUM"] {
      background: rgba(245,158,11,.2);
      color: #fde68a;
      border-color: rgba(251,191,36,.32);
    }
    html[data-bs-theme="dark"] #menuDT .menu-domain-badge[data-domain="SHARED"] {
      background: rgba(71,85,105,.32);
      color: #e2e8f0;
      border-color: rgba(148,163,184,.28);
    }

    .modal-add-accent .modal-header {
      background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
      color: #fff;
      border-bottom: none;
    }

    .modal-add-accent .modal-header .modal-title,
    .modal-add-accent .modal-header .btn-close {
      color: #fff;
    }

    .modal-add-accent .modal-header .btn-close {
      filter: brightness(0) invert(1);
      opacity: 0.9;
    }

    .modal-add-accent .modal-header .btn-close:hover {
      opacity: 1;
      transform: scale(1.04);
    }

    .modal-add-accent .modal-footer {
      gap: .75rem;
      flex-wrap: wrap;
    }

    .modal-add-accent .modal-footer .btn {
      min-width: 140px;
      justify-content: center;
    }

    .modal-add-accent .modal-footer .btn-primary {
      background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
      border-color: transparent;
      box-shadow: 0 4px 12px rgba(32, 201, 151, 0.28);
    }

    .modal-add-accent .modal-footer .btn-primary:hover {
      box-shadow: 0 6px 16px rgba(32, 201, 151, 0.38);
    }

    /* Accordion in Modals */
    .modal-body .accordion {
      border-radius: 8px;
      overflow: hidden;
    }

    .modal-body .accordion-item {
      border: 1px solid #e9ecef;
      margin-bottom: 0.5rem;
      border-radius: 8px;
    }

    .modal-body .accordion-button {
      background: #f8f9fa;
      font-weight: 600;
      padding: 1rem 1.25rem;
      border-radius: 8px;
    }

    .modal-body .accordion-button:not(.collapsed) {
      background: linear-gradient(135deg, rgba(102, 126, 234, 0.1) 0%, rgba(118, 75, 162, 0.1) 100%);
      color: #667eea;
    }

    /* Table in Modals */
    .modal-body .table {
      border-radius: 8px;
      overflow: hidden;
    }

    .modal-body .table thead {
      background: linear-gradient(135deg, rgba(102, 126, 234, 0.1) 0%, rgba(118, 75, 162, 0.1) 100%);
    }

    /* Badge Styling */
    .modal-body .badge {
      padding: 0.4rem 0.75rem;
      border-radius: 8px;
      font-weight: 500;
    }

    /* Input Group Styling */
    .modal-body .input-group-text {
      border-radius: 8px 0 0 8px;
      background: #f8f9fa;
      border-color: #e0e0e0;
    }

    .modal-body .input-group .form-control {
      border-left: none;
      border-radius: 0 8px 8px 0;
    }

    /* Button Group in Forms */
    .modal-body .btn-group .btn {
      border-radius: 8px;
      font-weight: 500;
      transition: all 0.2s ease;
    }

    .modal-body .btn-group .btn:hover {
      transform: translateY(-1px);
    }

    /* List Group in Modals */
    .modal-body .list-group-item {
      border-radius: 8px;
      margin-bottom: 0.5rem;
      border: 1.5px solid #e9ecef;
      transition: all 0.2s ease;
    }

    .modal-body .list-group-item:hover {
      border-color: #667eea;
      background: rgba(102, 126, 234, 0.05);
      transform: translateX(4px);
    }

    /* Smooth Transitions */
    .modal.fade .modal-dialog {
      transition: transform 0.3s ease-out, opacity 0.3s ease-out;
    }

    .modal.show .modal-dialog {
      transform: none;
    }

    /* Content Animation */
    .modal-body > div:not(.d-none) {
      animation: fadeInUp 0.3s ease-out;
    }

    @keyframes fadeInUp {
      from {
        opacity: 0;
        transform: translateY(10px);
      }
      to {
        opacity: 1;
        transform: translateY(0);
      }
    }

    #groupPermsDT { table-layout: fixed; }
    #groupPermsDT th.col-check, #groupPermsDT td.col-check { width: 60px; }
    #groupPermsDT th.col-menu,  #groupPermsDT td.col-menu  { width: 140px; white-space: nowrap; }

    /* Loading indicators */
    .btn-loading {
      position: relative;
      pointer-events: none;
      opacity: 0.7;
    }
    .btn-loading::after {
      content: "";
      position: absolute;
      width: 16px;
      height: 16px;
      top: 50%;
      left: 50%;
      margin-left: -8px;
      margin-top: -8px;
      border: 2px solid transparent;
      border-top-color: currentColor;
      border-radius: 50%;
      animation: spin 0.6s linear infinite;
    }
    @keyframes spin {
      to { transform: rotate(360deg); }
    }
    .btn-loading .btn-text {
      opacity: 0;
    }

    /* Controls & buttons: align with senarai-pengguna.php styles */
    .dt-bottom-row { display:flex; align-items:center; justify-content:space-between; flex-wrap:nowrap; gap:.5rem; }
    #groupTable_wrapper .dt-bottom-row {
      display: flex !important;
      align-items: center !important;
      justify-content: space-between !important;
      flex-wrap: nowrap !important;
      width: 100%;
      gap: .5rem !important;
      margin-top: 0 !important;
      padding-top: .15rem !important;
    }
    #groupTable_wrapper .dt-bottom-row.mt-2 {
      margin-top: 0 !important;
    }
    #groupTable_wrapper .dt-bottom-row > .dt-info-left {
      flex: 0 1 auto !important;
      min-width: 0 !important;
      overflow: hidden !important;
      display: flex !important;
      justify-content: flex-start !important;
      align-items: center !important;
      text-align: left !important;
      margin-right: auto !important;
    }
    #groupTable_wrapper .dt-bottom-row > .dt-paging-right {
      flex: 0 0 auto !important;
      margin-left: auto !important;
      display: flex !important;
      justify-content: flex-end !important;
      align-items: center !important;
      position: relative !important;
      top: -7px !important;
    }
    #groupTable_wrapper .dataTables_paginate {
      margin-top: 0 !important;
      margin-left: auto !important;
      white-space: nowrap !important;
      display: flex !important;
      align-items: center !important;
      justify-content: flex-end !important;
    }
    #groupTable_wrapper .dataTables_info {
      text-align: left !important;
      justify-content: flex-start !important;
      width: auto !important;
    }
    .dataTables_length, #groupTable_wrapper .dataTables_length {
      white-space: nowrap !important;
      line-height: 1.4;
      display: inline-block;
    }
    .dataTables_length label, #groupTable_wrapper .dataTables_length label {
      white-space: nowrap !important;
      display: inline-flex !important;
      align-items: center;
      gap: 0.4rem;
      margin-bottom: 0;
      flex-wrap: nowrap !important;
      font-size: 0.875rem !important;
    }
    .dataTables_length select, #groupTable_wrapper .dataTables_length select {
      display: inline-block !important;
      margin: 0 0.4rem !important;
      flex-shrink: 0 !important;
      height: 36px !important;
      min-height: 36px !important;
      padding: 0.5rem 0.75rem !important;
      font-size: 0.875rem !important;
      line-height: 1.4 !important;
      border: 2px solid #e9ecef !important;
      border-radius: 8px !important;
      min-width: 70px !important;
      transition: border-color 0.15s ease-in-out, box-shadow 0.15s ease-in-out !important;
    }
    .dataTables_length select:hover, #groupTable_wrapper .dataTables_length select:hover { border-color: #ced4da !important; }
    .dataTables_length select:focus, #groupTable_wrapper .dataTables_length select:focus {
      border-color: #86b7fe !important;
      box-shadow: 0 0 0 0.25rem rgba(13,110,253,0.25) !important;
      outline: none !important;
    }
    .dt-top-left { white-space: nowrap !important; flex-wrap: nowrap !important; }
    #groupTable_wrapper .row.mb-2 {
      display: flex !important;
      align-items: center !important;
      justify-content: space-between !important;
      flex-wrap: wrap !important;
      row-gap: 0.75rem !important;
      overflow: visible !important;
    }
    #groupTable_wrapper .row.mb-2 > .dt-top-left {
      flex: 0 1 auto !important;
      width: auto !important;
      max-width: 100% !important;
      overflow: visible !important;
    }
    #groupTable_wrapper .row.mb-2 > .dt-top-right {
      flex: 1 1 auto !important;
      width: auto !important;
      max-width: 100% !important;
      overflow: visible !important;
    }
    /* Buttons in top-right: sizing, borders and gap consistent with senarai-pengguna */
    .dt-top-right button, #groupTable_wrapper .dt-top-right button {
      height: 36px !important;
      min-height: 36px !important;
      border: 2px solid #e9ecef !important;
      border-radius: 8px !important;
      padding: 0.5rem 0.75rem !important;
      font-size: 0.875rem !important;
      line-height: 1.4 !important;
      display: inline-flex !important;
      align-items: center !important;
      justify-content: center !important;
      transition: border-color 0.15s ease-in-out, box-shadow 0.15s ease-in-out !important;
      margin-left: 0 !important;
      margin-right: 0 !important;
      white-space: nowrap !important;
    }
    .dt-top-right button:hover, #groupTable_wrapper .dt-top-right button:hover { border-color: #ced4da !important; }
    .dt-top-right button:focus, #groupTable_wrapper .dt-top-right button:focus {
      border-color: #86b7fe !important;
      box-shadow: 0 0 0 0.25rem rgba(13,110,253,0.25) !important;
    }
    .dt-top-right {
      gap: 0.5rem !important;
      flex-wrap: wrap !important;
      overflow: visible !important;
      scrollbar-width: none;
    }
    .dt-top-right::-webkit-scrollbar {
      display: none;
    }
    .dt-top-right button + button { margin-left: 0 !important; }
    #groupTable_wrapper .dt-top-right,
    #groupTable_wrapper .dt-top-right .dataTables_filter,
    #groupTable_wrapper .dt-top-right .dataTables_filter label {
      align-items: center !important;
    }
    #groupTable_wrapper .dt-top-right .dataTables_filter {
      display: flex !important;
      margin-bottom: 0 !important;
      flex: 0 1 auto !important;
      min-width: 0 !important;
      overflow: visible !important;
    }
    #groupTable_wrapper .dt-top-right .dataTables_filter label {
      justify-content: flex-end !important;
      min-width: 0 !important;
      overflow: visible !important;
    }
    #groupTable_wrapper .dt-top-right .btn {
      align-self: center !important;
      flex: 0 0 auto !important;
    }

    .module-icon-picker {
      display: flex;
      align-items: flex-start;
      gap: .9rem;
    }
    .module-create-layout {
      display: grid;
      grid-template-columns: minmax(0, 0.8fr) minmax(0, 1.2fr);
      gap: 1.25rem;
      align-items: start;
    }
    .module-create-panel {
      background: linear-gradient(135deg, rgba(248, 250, 252, 0.94) 0%, rgba(255, 255, 255, 0.98) 100%);
      border: 1px solid rgba(148, 163, 184, 0.18);
      border-radius: 8px;
      padding: 1.15rem 1.1rem;
    }
    .module-create-panel-title {
      display: flex;
      align-items: center;
      gap: .55rem;
      margin-bottom: 1rem;
      font-size: .95rem;
      font-weight: 700;
      color: #0f172a;
      letter-spacing: .01em;
    }
    .module-create-panel-title i {
      font-size: 1.05rem;
      color: #2563eb;
    }
    .module-icon-preview {
      width: 3rem;
      height: 3rem;
      border-radius: 8px;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      background: linear-gradient(135deg, rgba(37, 99, 235, 0.12) 0%, rgba(102, 126, 234, 0.18) 100%);
      color: #1d4ed8;
      border: 1px solid rgba(37, 99, 235, 0.16);
      flex: 0 0 auto;
    }
    .module-icon-preview i {
      font-size: 1.35rem;
      line-height: 1;
    }
    .module-icon-grid {
      display: grid;
      grid-template-columns: repeat(auto-fill, minmax(52px, 1fr));
      gap: .6rem;
      padding: .15rem;
    }
    .module-icon-groups {
      margin-top: .8rem;
      max-height: 360px;
      overflow-y: auto;
      padding-right: .25rem;
    }
    .module-icon-group + .module-icon-group {
      margin-top: 1rem;
    }
    .module-icon-group-title {
      font-size: .78rem;
      font-weight: 700;
      letter-spacing: .04em;
      text-transform: uppercase;
      color: #475569;
      margin-bottom: .55rem;
      display: flex;
      align-items: center;
      gap: .45rem;
    }
    .module-icon-group-title::before {
      content: "";
      width: 10px;
      height: 10px;
      border-radius: 999px;
      background: linear-gradient(135deg, #60a5fa 0%, #6366f1 100%);
      flex: 0 0 auto;
      opacity: .9;
    }
    .module-icon-option {
      width: 100%;
      min-height: 52px;
      border-radius: 8px;
      border: 1px solid rgba(148, 163, 184, 0.3);
      background: #fff;
      color: #475569;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      transition: all .18s ease;
      cursor: pointer;
    }
    .module-icon-option i {
      font-size: 1.2rem;
      line-height: 1;
    }
    .module-icon-option:hover {
      border-color: rgba(37, 99, 235, 0.35);
      color: #2563eb;
      background: rgba(37, 99, 235, 0.05);
      transform: translateY(-1px);
    }
    .module-icon-option.is-active {
      border-color: rgba(37, 99, 235, 0.5);
      color: #1d4ed8;
      background: linear-gradient(135deg, rgba(37, 99, 235, 0.12) 0%, rgba(102, 126, 234, 0.18) 100%);
      box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.12);
    }
    .module-order-readonly {
      background: #f8fafc;
      color: #334155;
      font-weight: 600;
      cursor: not-allowed;
    }
    .module-field-help {
      display: block;
      margin-top: .45rem;
      color: #64748b;
      font-size: .82rem;
      line-height: 1.45;
    }
    html[data-bs-theme="dark"] .module-icon-preview {
      background: linear-gradient(135deg, rgba(96, 165, 250, 0.18) 0%, rgba(129, 140, 248, 0.18) 100%);
      color: #bfdbfe;
      border-color: rgba(96, 165, 250, 0.18);
    }
    html[data-bs-theme="dark"] .module-order-readonly {
      background: rgba(30, 41, 59, 0.9);
      color: #e2e8f0;
      border-color: rgba(148, 163, 184, 0.24);
    }
    html[data-bs-theme="dark"] .module-create-panel {
      background: linear-gradient(135deg, rgba(15, 23, 42, 0.96) 0%, rgba(30, 41, 59, 0.98) 100%);
      border-color: rgba(148, 163, 184, 0.16);
    }
    html[data-bs-theme="dark"] .module-create-panel-title {
      color: #e2e8f0;
    }
    html[data-bs-theme="dark"] .module-create-panel-title i {
      color: #93c5fd;
    }
    html[data-bs-theme="dark"] .module-icon-option {
      background: rgba(15, 23, 42, 0.82);
      color: #cbd5e1;
      border-color: rgba(148, 163, 184, 0.22);
    }
    html[data-bs-theme="dark"] .module-icon-option:hover {
      color: #bfdbfe;
      border-color: rgba(96, 165, 250, 0.35);
      background: rgba(59, 130, 246, 0.12);
    }
    html[data-bs-theme="dark"] .module-icon-option.is-active {
      color: #dbeafe;
      border-color: rgba(96, 165, 250, 0.45);
      background: linear-gradient(135deg, rgba(37, 99, 235, 0.22) 0%, rgba(99, 102, 241, 0.2) 100%);
      box-shadow: 0 0 0 3px rgba(96, 165, 250, 0.12);
    }
    html[data-bs-theme="dark"] .module-icon-group-title {
      color: #cbd5e1;
    }
    @media (max-width: 991.98px) {
      .module-create-layout {
        grid-template-columns: 1fr;
      }
    }

    /* Match senarai-pengguna table shell */
    .content-page .card {
      border-radius: 8px;
      border: 1px solid rgba(148, 163, 184, 0.14);
      box-shadow: 0 16px 38px rgba(15, 23, 42, 0.07);
      overflow: hidden;
      backdrop-filter: blur(10px);
    }
    .content-page .card > .card-body {
      padding: 1.15rem 1.15rem 1rem;
    }
    #groupTable {
      border-radius: 8px;
      overflow: hidden;
      box-shadow: none;
      border: 1px solid rgba(148, 163, 184, 0.14);
      background: rgba(255, 255, 255, 0.96);
    }
    #groupTable thead {
      background: transparent;
      color: inherit;
    }
    #groupTable thead th {
      padding: 0.9rem 0.85rem;
      border: 0;
      border-bottom: 1px solid rgba(148, 163, 184, 0.16);
      background: linear-gradient(180deg, rgba(248, 250, 252, 0.98) 0%, rgba(241, 245, 249, 0.95) 100%);
      color: #334155;
      font-size: 0.8rem;
      font-weight: 700;
      letter-spacing: 0.06em;
      text-transform: uppercase;
    }
    #groupTable thead th:first-child,
    #groupTable tbody td:first-child {
      text-align: center;
    }
    #groupTable tbody td {
        padding: 0.62rem 0.78rem;
        border-color: rgba(226, 232, 240, 0.9);
        font-size: 0.88rem;
        line-height: 1.28;
        vertical-align: middle;
      }
    #groupTable tbody tr {
      transition: background-color 0.18s ease, box-shadow 0.18s ease, transform 0.18s ease;
    }
    #groupTable tbody tr:hover {
      background: rgba(241, 245, 249, 0.88) !important;
      transform: none;
      box-shadow: inset 0 0 0 999px rgba(241, 245, 249, 0.3);
    }
    #groupTable_wrapper .dataTables_filter input,
    #groupTable_wrapper .dataTables_length select,
    #groupTable_wrapper #dtGroupFilter {
      border-radius: 8px !important;
      border: 1px solid rgba(148, 163, 184, 0.24) !important;
      background: rgba(255, 255, 255, 0.98) !important;
      box-shadow: 0 10px 24px rgba(15, 23, 42, 0.05);
    }
    #groupTable_wrapper .dataTables_filter input:focus,
    #groupTable_wrapper .dataTables_length select:focus,
    #groupTable_wrapper #dtGroupFilter:focus {
      border-color: rgba(59, 130, 246, 0.45) !important;
      box-shadow: 0 0 0 0.2rem rgba(59, 130, 246, 0.14), 0 12px 24px rgba(15, 23, 42, 0.06) !important;
    }
    #groupTable_wrapper .dt-top-right .btn {
      border-radius: 8px !important;
      box-shadow: 0 10px 24px rgba(15, 23, 42, 0.07);
    }
    #groupTable_wrapper .dt-top-right .btn-primary {
      box-shadow: 0 12px 26px rgba(37, 99, 235, 0.18);
    }
    #groupTable_wrapper .paginate_button .page-link {
      border-radius: 8px !important;
    }
    html[data-bs-theme="dark"] #groupTable {
      background: rgba(15, 23, 42, 0.92);
      border-color: rgba(148, 163, 184, 0.22);
    }
    html[data-bs-theme="dark"] #groupTable thead th {
      background: linear-gradient(180deg, rgba(30, 41, 59, 0.96) 0%, rgba(15, 23, 42, 0.94) 100%);
      color: #dbe4f0;
      border-bottom-color: rgba(148, 163, 184, 0.18);
    }
    html[data-bs-theme="dark"] #groupTable tbody td {
      border-color: rgba(51, 65, 85, 0.95);
    }
    html[data-bs-theme="dark"] #groupTable tbody tr:hover {
      background: rgba(30, 41, 59, 0.76) !important;
      box-shadow: inset 0 0 0 999px rgba(30, 41, 59, 0.18);
    }
    html[data-bs-theme="dark"] #groupTable_wrapper .dataTables_filter input,
    html[data-bs-theme="dark"] #groupTable_wrapper .dataTables_length select,
    html[data-bs-theme="dark"] #groupTable_wrapper #dtGroupFilter {
      background: rgba(15, 23, 42, 0.96) !important;
      border-color: rgba(148, 163, 184, 0.24) !important;
      color: #e2e8f0 !important;
    }

    /* Professional group-management workspace */
    .user-group-page {
      --ug-accent: var(--ct-accent, var(--ct-primary));
      --ug-accent-start: var(--ct-primary-start, var(--ct-primary));
      --ug-accent-end: var(--ct-primary-end, var(--ct-primary));
      --ug-accent-soft: var(--ct-accent-soft, rgba(var(--ct-primary-rgb), .08));
      --ug-border: rgba(148, 163, 184, .17);
      --ug-surface: rgba(255, 255, 255, .97);
      --ug-surface-soft: rgba(248, 250, 252, .9);
      --ug-ink: #172033;
      --ug-muted: #64748b;
    }
    .user-group-page .page-title-box {
      min-height: 82px;
      padding: 1rem 1.15rem;
      border: 1px solid var(--ct-page-strip-border, var(--ug-border));
      border-radius: 12px;
      background: var(--ct-page-strip-bg, var(--ug-surface));
      box-shadow: 0 12px 28px rgba(15, 23, 42, .045);
    }
    .user-group-page .page-title-main { min-width: 0; }
    .user-group-page .page-title {
      display: flex;
      align-items: center;
      gap: .55rem;
      margin: 0;
      color: var(--ug-ink);
      font-size: 1.08rem;
      font-weight: 750;
    }
    .user-group-page .page-title > i {
      display: inline-flex;
      width: 36px;
      height: 36px;
      align-items: center;
      justify-content: center;
      border-radius: 9px;
      color: var(--ug-accent-end);
      background: var(--ug-accent-soft);
    }
    .user-group-page .page-title-subtitle {
      margin: .4rem 0 0 2.95rem;
      color: var(--ug-muted);
      font-size: .82rem;
      line-height: 1.5;
    }
    .user-group-page .group-workspace {
      overflow: hidden;
      border: 1px solid var(--ug-border);
      border-radius: 14px;
      background: var(--ug-surface);
      box-shadow: 0 18px 44px rgba(15, 23, 42, .06);
    }
    .user-group-page .group-workspace-toolbar {
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 1rem;
      padding: .85rem 1rem;
      border-bottom: 1px solid var(--ug-border);
      background: linear-gradient(180deg, var(--ug-surface), var(--ug-surface-soft));
    }
    .user-group-page .group-workspace-intro {
      margin: 0;
      color: var(--ug-muted);
      font-size: .84rem;
    }
    .user-group-page .group-primary-actions {
      display: flex;
      flex-wrap: wrap;
      gap: .45rem;
    }
    .user-group-page .group-primary-actions .btn {
      min-height: 38px;
      padding: .5rem .78rem;
      border: 1px solid var(--ug-border);
      border-radius: 8px;
      color: var(--ug-ink);
      background: var(--ug-surface);
      font-weight: 700;
      box-shadow: 0 7px 16px rgba(15, 23, 42, .05);
    }
    .user-group-page .group-primary-actions .btn:hover {
      color: var(--ug-accent-end);
      border-color: rgba(var(--ct-primary-rgb), .25);
      transform: translateY(-1px);
    }
    .user-group-page .group-primary-actions .btn-primary {
      color: #fff;
      border-color: transparent;
      background: linear-gradient(135deg, var(--ug-accent-start), var(--ug-accent-end));
    }
    .user-group-page .group-workspace-body { padding: 1rem; }
    .user-group-page .group-workspace-body .dt-standard {
      overflow: hidden;
      border: 1px solid var(--ug-border);
      border-radius: 10px;
    }
    .user-group-page #groupTable { margin-bottom: 0 !important; border: 0; }
    .user-group-page #groupTable .icon-btn {
      display: inline-flex;
      width: 34px;
      height: 34px;
      align-items: center;
      justify-content: center;
      border-radius: 8px;
    }
    .user-group-page #groupTable .group-table-category-chip { border-radius: 7px; }
    .user-group-page #groupTable .group-color-bar {
      width: 22px;
      min-width: 22px;
      height: 22px;
      border-radius: 5px;
    }
    /* One visual standard for every group-management modal. */
    .user-group-page .modal-content {
      overflow: hidden;
      border: 1px solid var(--ug-border) !important;
      border-radius: 13px !important;
      background: var(--ug-surface);
      box-shadow: 0 28px 72px rgba(15, 23, 42, .24) !important;
    }
    .user-group-page .modal-content::before,
    .user-group-page .modal-content::after { display: none !important; }
    .user-group-page .modal-header,
    .user-group-page .modal-header.bg-body-tertiary,
    .user-group-page .modal-themed .modal-header,
    .user-group-page .modal-child-accent .modal-header,
    .user-group-page .modal-add-accent .modal-header {
      min-height: 60px;
      padding: .9rem 1.15rem;
      border: 0 !important;
      color: #fff !important;
      background: linear-gradient(135deg, var(--ug-accent-start), var(--ug-accent-end)) !important;
    }
    .user-group-page .modal-header .modal-title {
      display: flex;
      align-items: center;
      gap: .45rem;
      margin: 0;
      color: #fff !important;
      font-size: 1rem;
      font-weight: 750;
    }
    .user-group-page .modal-header .modal-title i {
      display: inline-flex;
      width: 29px;
      height: 29px;
      align-items: center;
      justify-content: center;
      border-radius: 8px;
      background: rgba(255, 255, 255, .14);
    }
    .user-group-page .modal-header .btn-close {
      filter: brightness(0) invert(1);
      opacity: .9;
    }
    .user-group-page .modal-subtitle {
      min-height: 42px;
      padding: .7rem 1.15rem;
      border-bottom: 1px solid var(--ug-border);
      color: var(--ug-muted);
      background: var(--ug-surface-soft);
      font-size: .82rem;
    }
    .user-group-page .modal-body {
      padding: 1.15rem;
      background: var(--ug-surface);
    }
    .user-group-page .modal-footer,
    .user-group-page .modal-child-accent .modal-footer,
    .user-group-page .modal-add-accent .modal-footer {
      gap: .45rem;
      min-height: 64px;
      padding: .8rem 1.15rem;
      border-top: 1px solid var(--ug-border) !important;
      background: var(--ug-surface-soft) !important;
    }
    .user-group-page .modal-footer .btn {
      min-height: 38px;
      padding: .48rem .82rem;
      border-radius: 8px !important;
      font-weight: 700;
    }
    .user-group-page .modal-footer .btn-primary,
    .user-group-page .modal-footer .btn-success {
      border-color: transparent !important;
      color: #fff !important;
      background: linear-gradient(135deg, var(--ug-accent-start), var(--ug-accent-end)) !important;
      box-shadow: 0 8px 18px rgba(var(--ct-primary-rgb), .17);
    }
    .user-group-page .modal-body .form-control,
    .user-group-page .modal-body .form-select,
    .user-group-page .modal-body .input-group-text {
      min-height: 40px;
      border-color: var(--ug-border);
      border-radius: 8px;
      box-shadow: none;
    }
    .user-group-page .modal-body .form-control:focus,
    .user-group-page .modal-body .form-select:focus {
      border-color: rgba(var(--ct-primary-rgb), .5);
      box-shadow: 0 0 0 3px rgba(var(--ct-primary-rgb), .12);
    }
    .user-group-page .modal-body .form-label {
      margin-bottom: .45rem;
      color: var(--ug-ink);
      font-size: .8rem;
      font-weight: 750;
    }
    .user-group-page .group-create-section-title,
    .user-group-page .subgroup-section-title,
    .user-group-page .module-create-panel-title {
      color: var(--ug-ink);
      font-weight: 750;
    }
    .user-group-page .group-create-preview,
    .user-group-page .subgroup-form-section,
    .user-group-page .module-create-panel,
    .user-group-page .subgroup-table-card {
      border: 1px solid var(--ug-border) !important;
      border-radius: 10px !important;
      background: var(--ug-surface-soft) !important;
      box-shadow: none !important;
    }
    .user-group-page .modal-loading {
      border: 1px dashed var(--ug-border);
      border-radius: 10px;
      background: var(--ug-surface-soft);
    }
    [data-bs-theme="dark"] .user-group-page {
      --ug-surface: rgba(20, 27, 39, .96);
      --ug-surface-soft: rgba(30, 41, 59, .72);
      --ug-border: rgba(148, 163, 184, .16);
      --ug-ink: #f1f5f9;
      --ug-muted: #9aa9bc;
    }
    @media (max-width: 767.98px) {
      .user-group-page .page-title-box,
      .user-group-page .group-workspace-toolbar { align-items: flex-start !important; }
      .user-group-page .page-title-subtitle { margin-left: 0; }
      .user-group-page .group-workspace-toolbar { flex-direction: column; }
      .user-group-page .group-primary-actions { width: 100%; }
      .user-group-page .group-primary-actions .btn { flex: 1 1 auto; }
      .user-group-page .group-workspace-body { padding: .7rem; }
    }


  </style>
</head>

<body
  data-topbar-color="<?= h($_SESSION['theme.topbar'] ?? 'light') ?>"
  data-menu-color="<?= h($_SESSION['theme.menu'] ?? 'light') ?>"
  data-layout="vertical"
  data-sidebar-size="default"
  class="loading user-group-page">

<div class="wrapper">
  <?php include __DIR__ . '/../includes/topbar.php'; ?>
  <?php include __DIR__ . '/../includes/sidebar.php'; ?>

  <div class="content-page">
    <div class="content">
      <div class="container-fluid">

        <!-- Tajuk + breadcrumb -->
        <div class="row mb-3">
          <div class="col-12">
            <div class="page-title-box d-flex justify-content-between align-items-center flex-wrap">
              <div class="page-title-main">
                <h4 class="page-title"><i class="ri-team-line"></i> <?= h(__('userGroup_page_title')) ?></h4>
                <p class="page-title-subtitle"><?= h(__('userGroup_intro')) ?></p>
              </div>
              <div class="page-title-right">
                  <ol class="breadcrumb m-0">
                  <li class="breadcrumb-item">
                    <a href="dashboard.php">
                      <i class="ri-home-4-line align-middle me-1"></i> <?= __('breadcrumb_home') ?? 'Home' ?>
                    </a>
                  </li>
                  <li class="breadcrumb-item active"><?= __('userGroup_page_title') ?></li>
                </ol>
              </div>
            </div>
          </div>
        </div>

        <!-- Jadual Kumpulan -->
        <section class="group-workspace">
          <div class="group-workspace-toolbar">
            <p class="group-workspace-intro"><?= h(__('userGroup_intro')) ?></p>
            <?php if ($canManageGroups): ?>
              <div class="group-primary-actions" aria-label="<?= h(__('userGroup_col_actions')) ?>">
                <button type="button" id="btnAddMenuPage" class="btn btn-light">
                  <i class="ri-menu-add-line me-1"></i><?= h(__('userGroup_btn_menu_label')) ?>
                </button>
                <button type="button" id="btnAddModule" class="btn btn-light">
                  <i class="ri-stack-line me-1"></i><?= h(__('userGroup_btn_module_label')) ?>
                </button>
                <button type="button" id="btnAddGroup" class="btn btn-light">
                  <i class="ri-group-line me-1"></i><?= h(__('userGroup_btn_group_label')) ?>
                </button>
              </div>
            <?php endif; ?>
          </div>
          <div class="group-workspace-body">

                <div class="table-responsive dt-standard">
                  <table class="table table-bordered align-middle" id="groupTable">
                    <thead>
                        <tr>
                          <th style="width:5%" class="th-nowrap">#</th>
                          <th style="width:12%" class="th-nowrap"><?= __('userGroup_col_code') ?></th>
                          <th style="width:24%" class="th-nowrap"><?= __('userGroup_col_name') ?></th>
                          <th style="width:10%" class="th-nowrap"><?= __('userGroup_col_category') ?></th>
                          <th style="width:4%" class="text-end th-nowrap th-color">&nbsp;</th>
                          <th style="width:15%" class="text-start th-nowrap th-grp"><?= __('userGroup_col_group_access') ?></th>
                          <th style="width:15%" class="text-center th-nowrap th-mod"><?= __('userGroup_col_module_access') ?></th>
                          <th style="width:16%" class="text-center th-nowrap th-menu"><?= __('userGroup_col_menu_access') ?></th>
                        </tr>
                    </thead>
                    <tbody>
                      <?php if (!empty($senaraiGroup)): ?>
                        <?php foreach ($senaraiGroup as $i => $g): ?>
                          <?php
                            $groupID = (int)($g['f_groupID'] ?? 0);
                            $kod     = (string)($g['f_groupKod'] ?? '');
                            $nama    = (string)($g['f_groupName'] ?? '');
                            $categoryUser = (string)($g['f_categoryUser'] ?? '');
                            $modAks  = (string)($g['f_modulAccess'] ?? '');
                            $menuAks = (string)($g['f_menuAccess'] ?? '');
                            $rawColor = trim((string)($g['f_color'] ?? ''));
                            $barColor = '#94a3b8';
                            if (
                              $rawColor !== '' &&
                              (preg_match('/^#(?:[0-9a-f]{3}|[0-9a-f]{6})$/i', $rawColor) || preg_match('/^[a-zA-Z]+$/', $rawColor))
                            ) {
                              $barColor = $rawColor;
                            }
                            $hasAccess = (trim($modAks) !== '' || trim($menuAks) !== '');
                            $userCount = (int)($g['userCount'] ?? 0);
                            $saId = defined('PRESTASI_ROLE_ID_ADM_SA') ? (int)PRESTASI_ROLE_ID_ADM_SA : 0;
                            $saCode = defined('PRESTASI_ROLE_KOD_ADM_SA')
                              ? strtoupper(trim((string)PRESTASI_ROLE_KOD_ADM_SA))
                              : (defined('PRESTASI_ROLE_ADM_SA') ? strtoupper(trim((string)PRESTASI_ROLE_ADM_SA)) : 'ADM-SA');
                            $isProtectedGroup = ($saId > 0 && $groupID === $saId) || (strtoupper(trim($kod)) === $saCode);
                            $canDeleteGroup = !$hasAccess && $userCount === 0 && !$isProtectedGroup;
                          ?>
                          <tr data-group-id="<?= $groupID ?>">
                            <td><?= $i + 1 ?></td>
                            <td><?= h($kod) ?></td>
                            <td><?= h($nama) ?></td>
                            <td>
                              <span class="group-create-preview-chip group-category-chip group-table-category-chip" data-category="<?= h($categoryUser) ?>">
                                <?= h($categoryUser !== '' ? $categoryUser : 'STAF') ?>
                              </span>
                            </td>
                            <td class="text-end td-color">
                              <div class="group-color-cell">
                                <span class="group-color-bar" style="background-color: <?= h($barColor) ?>;" title="<?= h($barColor) ?>"></span>
                              </div>
                            </td>

                            <!-- Akses Kumpulan -->
                            <td class="text-start td-grp">
                              <div class="group-access-actions d-inline-flex align-items-center gap-2">
                              <button
                                type="button"
                                class="btn btn-sm btn-outline-secondary icon-btn view-group-perms<?= $permDisabledClass ?>"
                                <?= $permDisabledAttr ?>
                                data-group-id="<?= $groupID ?>"
                                data-group-kod="<?= h($kod) ?>"
                                data-group-nama="<?= h($nama) ?>"
                                title="<?= h(__('userGroup_col_group_access')) ?>">
                                <i class="ri-user-settings-line"></i>
                              </button>
                              <?php if ($canManageGroups): ?>
                                <button
                                  type="button"
                                  class="btn btn-sm btn-outline-warning icon-btn btn-edit-group-meta<?= $permDisabledClass ?>"
                                  <?= $permDisabledAttr ?>
                                  data-group-id="<?= $groupID ?>"
                                  data-group-kod="<?= h($kod) ?>"
                                  data-group-nama="<?= h($nama) ?>"
                                  data-group-category="<?= h($categoryUser) ?>"
                                  data-group-priority="<?= h((string)($g['f_priority'] ?? 0)) ?>"
                                  data-group-mod="<?= h((string)($g['f_mod'] ?? 0)) ?>"
                                  data-group-color="<?= h((string)($g['f_color'] ?? '')) ?>"
                                  title="<?= h(__('userGroup_edit_group')) ?>">
                                  <i class="ri-pencil-line"></i>
                                </button>
                                <?php if ($canDeleteGroup): ?>
                                  <button
                                    type="button"
                                    class="btn btn-sm btn-outline-danger icon-btn btn-delete-group<?= $permDisabledClass ?>"
                                    <?= $permDisabledAttr ?>
                                    data-group-id="<?= $groupID ?>"
                                    data-group-kod="<?= h($kod) ?>"
                                    data-group-nama="<?= h($nama) ?>"
                                    title="<?= h(__('userGroup_delete_group')) ?>">
                                    <i class="ri-delete-bin-line"></i>
                                  </button>
                                <?php endif; ?>
                              <?php endif; ?>
                              </div>
                            </td>

                            <!-- Akses Modul -->
                            <td class="text-center td-mod">
                              <?php if ($groupID > 0): ?>
                                <button
                                  type="button"
                                  class="btn btn-sm btn-outline-primary icon-btn view-access<?= $permDisabledClass ?>"
                                  <?= $permDisabledAttr ?>
                                  data-group-id="<?= $groupID ?>"
                                  data-group-kod="<?= h($kod) ?>"
                                  data-group-nama="<?= h($nama) ?>"
                                  title="<?= h(__('userGroup_col_module_access')) ?>">
                                  <i class="ri-links-line"></i>
                                </button>
                              <?php else: ?>
                                <span class="text-muted"><i class="ri-link-unlink-m"></i></span>
                              <?php endif; ?>
                            </td>

                            <!-- Akses Menu -->
                            <td class="text-center td-menu">
                              <button
                                type="button"
                                class="btn btn-sm btn-outline-success icon-btn view-menu<?= $permDisabledClass ?>"
                                <?= $permDisabledAttr ?>
                                data-group-id="<?= $groupID ?>"
                                data-group-kod="<?= h($kod) ?>"
                                data-group-nama="<?= h($nama) ?>"
                                title="<?= h(__('userGroup_col_menu_access')) ?>">
                                <i class="ri-menu-2-line"></i>
                              </button>
                            </td>

                          </tr>
                        <?php endforeach; ?>
                      <?php else: ?>
                        <tr>
                          <td colspan="8" class="text-center text-muted"><?= h(__('userGroup_no_records')) ?></td>
                        </tr>
                      <?php endif; ?>
                    </tbody>
                  </table>
                </div>

          </div>
        </section>

      </div><!-- /.container-fluid -->
    </div><!-- /.content -->
    <?php include __DIR__ . '/../includes/footer.php'; ?>
  </div><!-- /.content-page -->
</div><!-- /.wrapper -->

<?php include __DIR__ . '/../includes/script.php'; ?>
<script>
window.GroupPageRuntime = Object.assign({}, window.GroupPageRuntime || {}, {
  activeGroupId: <?= json_encode((int)($_SESSION['group_active_id'] ?? ($profile['f_groupID'] ?? 0))) ?>,
  defaultGroupId: <?= json_encode((int)($_SESSION['group_default_id'] ?? ($profile['f_groupID'] ?? 0))) ?>,
  canManageGroups: <?= json_encode((bool)$canManageGroups) ?>
});
</script>
<!-- Extracted JavaScript Modules -->
<script src="<?= base_url('assets/js/helpers/datatables-standard.js') ?>?v=<?= $version ?? date('ymdHis') ?>"></script>
<script src="<?= base_url('assets/js/group-utils.js') ?>?v=<?= $version ?? date('ymdHis') ?>"></script>
<script src="<?= base_url('assets/js/group-state.js') ?>?v=<?= $version ?? date('ymdHis') ?>"></script>
<script src="<?= base_url('assets/js/group-menu-refresh.js') ?>?v=<?= $version ?? date('ymdHis') ?>"></script>
<script src="<?= base_url('assets/js/group-module-access.js') ?>?v=<?= $version ?? date('ymdHis') ?>"></script>
<script src="<?= base_url('assets/js/group-menu-access.js') ?>?v=<?= $version ?? date('ymdHis') ?>"></script>
<script src="<?= base_url('assets/js/group-permissions.js') ?>?v=<?= $version ?? date('ymdHis') ?>"></script>
<!-- MODAL: Akses Modul (REORDER) -->
<div class="modal fade modal-themed" id="aksesModal" tabindex="-1" aria-hidden="true" aria-labelledby="aksesModalTitle">
  <div class="modal-dialog modal-lg modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="aksesModalTitle">
          <i class="ri-shield-keyhole-line"></i>
          <span><?= h(__('userGroup_col_module_access')) ?></span>
        </h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?= h(__('userGroup_btn_close')) ?>"></button>
      </div>
      <div class="modal-subtitle" id="aksesModalSub"></div>
      <div class="modal-body">
        <div id="aksesLoading" class="modal-loading">
          <div class="spinner-border" role="status" aria-hidden="true"></div>
          <span><?= h(__('userGroup_loading')) ?>…</span>
        </div>
        <div id="aksesError" class="modal-error alert alert-danger d-none"></div>
        <div id="aksesContent" class="d-none"></div>
      </div>
      <div class="modal-footer">
        <button class="btn btn-secondary" data-bs-dismiss="modal">
          <i class="ri-close-line me-1"></i> <?= h(__('userGroup_btn_close')) ?>
        </button>
      </div>
    </div>
  </div>
</div>

<!-- MODAL: Akses Menu (DataTable) -->
<div class="modal fade modal-themed" id="aksesMenuModal" tabindex="-1" aria-hidden="true" aria-labelledby="aksesMenuTitle">
  <div class="modal-dialog modal-xl modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="aksesMenuTitle">
          <i class="ri-list-settings-line"></i>
          <span><?= h(__('userGroup_col_menu_access')) ?></span>
        </h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?= h(__('userGroup_btn_close')) ?>"></button>
      </div>
      <div class="modal-subtitle" id="aksesMenuSub"></div>
      <div class="modal-body">
        <div id="menuLoading" class="modal-loading">
          <div class="spinner-border" role="status" aria-hidden="true"></div>
          <span><?= h(__('userGroup_loading')) ?>…</span>
        </div>
        <div id="menuError" class="modal-error alert alert-danger d-none"></div>
        <div id="menuContent" class="d-none"></div>
      </div>
      <div class="modal-footer">
        <button class="btn btn-secondary" data-bs-dismiss="modal">
          <i class="ri-close-line me-1"></i> <?= h(__('userGroup_btn_close')) ?>
        </button>
      </div>
    </div>
  </div>
</div>

<!-- MODAL: Edit Menu -->
<div class="modal fade modal-child-accent" id="menuEditModal" tabindex="-1" aria-hidden="true" aria-labelledby="menuEditTitle" data-bs-backdrop="static">
  <div class="modal-dialog modal-xl">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="menuEditTitle">
          <i class="ri-pencil-line"></i>
          <span id="menuEditTitleText" data-title-create="<?= h(__('userGroup_modal_add_menu_title')) ?>" data-title-edit="<?= h(__('userGroup_modal_edit_menu_title')) ?>"><?= h(__('userGroup_modal_edit_menu_title')) ?></span>
        </h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?= h(__('userGroup_btn_close')) ?>"></button>
      </div>
      <div class="modal-body">
        <form id="menuEditForm" autocomplete="off">
          <input type="hidden" name="menuID" id="em_menuID">
          <input type="hidden" name="groupID" id="em_groupID">
          <div class="alert alert-light border mb-3 small d-none" id="em_groupInfoWrap">
            <strong><?= h(__('userGroup_label_group')) ?>:</strong>
            <span id="em_groupInfo"></span>
          </div>
          <div class="row g-3">
            <div class="col-md-6">
              <label class="form-label"><?= h(__('userGroup_field_modul')) ?></label>
              <select class="form-select" name="modulID" id="em_modulID"></select>
            </div>
            <div class="col-md-6">
              <label class="form-label"><?= h(__('userGroup_field_subgroup')) ?></label>
              <select class="form-select" name="subgroupID" id="em_subgroupID">
                <option value="0"><?= h(__('userGroup_subgroup_none')) ?></option>
              </select>
              <div class="form-text"><?= h(__('userGroup_field_subgroup_help')) ?></div>
            </div>
            <div class="col-md-6">
              <label class="form-label"><?= h(__('userGroup_field_path')) ?></label>
              <input type="text" class="form-control" name="path" id="em_path" placeholder="<?= h(__('userGroup_field_path_placeholder')) ?>" required>
            </div>
            <div class="col-md-6">
              <label class="form-label"><?= h(__('userGroup_field_name_ms')) ?></label>
              <input type="text" class="form-control" name="name_ms" id="em_name_ms">
            </div>
            <div class="col-md-6">
              <label class="form-label"><?= h(__('userGroup_field_name_en')) ?></label>
              <input type="text" class="form-control" name="name_en" id="em_name_en">
            </div>
            <!-- Removed zh/ta language fields: menu names now only use ms + en -->
            <div class="col-md-6">
              <label class="form-label"><?= h(__('userGroup_field_menu_domain')) ?></label>
              <select class="form-select" name="domain" id="em_domain">
                <option value="SHARED"><?= h(__('userGroup_menu_domain_shared')) ?></option>
                <option value="STAF"><?= h(__('userGroup_menu_domain_staff')) ?></option>
                <option value="PELAJAR"><?= h(__('userGroup_menu_domain_student')) ?></option>
                <option value="UMUM"><?= h(__('userGroup_menu_domain_public')) ?></option>
              </select>
              <div class="form-text"><?= h(__('userGroup_field_menu_domain_help')) ?></div>
            </div>
            <div class="col-md-6">
              <label class="form-label d-block"><?= h(__('userGroup_field_menu_staff_only_visibility')) ?></label>
              <div class="btn-group mt-1" role="group" aria-label="<?= h(__('userGroup_field_menu_staff_only_visibility')) ?>">
                <input type="radio" class="btn-check" name="show_staff_only" id="em_show_staff_only_yes" value="1" checked>
                <label class="btn btn-outline-primary" for="em_show_staff_only_yes"><?= h(__('userGroup_menu_staff_only_show')) ?></label>
                <input type="radio" class="btn-check" name="show_staff_only" id="em_show_staff_only_no" value="0">
                <label class="btn btn-outline-secondary" for="em_show_staff_only_no"><?= h(__('userGroup_menu_staff_only_hide')) ?></label>
              </div>
              <div class="form-text"><?= h(__('userGroup_field_menu_staff_only_visibility_help')) ?></div>
            </div>
            <div class="col-md-6">
              <label class="form-label d-block"><?= h(__('userGroup_field_status')) ?></label>
              <div class="btn-group mt-1" role="group" aria-label="<?= h(__('userGroup_field_status')) ?>">
                <input type="radio" class="btn-check" name="flag" id="em_flag_on" value="1">
                <label class="btn btn-outline-success" for="em_flag_on"><?= h(__('userGroup_status_on')) ?></label>
                <input type="radio" class="btn-check" name="flag" id="em_flag_off" value="0">
                <label class="btn btn-outline-secondary" for="em_flag_off"><?= h(__('userGroup_status_off')) ?></label>
              </div>
            </div>
            <div class="col-md-6">
              <label class="form-label"><?= h(__('userGroup_field_position_label')) ?></label>
              <select class="form-select" id="em_position">
                <option value="bottom" selected><?= h(__('userGroup_position_bottom')) ?></option>
                <option value="top"><?= h(__('userGroup_position_top')) ?></option>
              </select>
            </div>
          </div>
        </form>
        <div id="menuEditError" class="modal-error alert alert-danger d-none mt-3"></div>
      </div>
      <div class="modal-footer">
        <button class="btn btn-secondary" data-bs-dismiss="modal">
          <i class="ri-close-line me-1"></i> <?= h(__('userGroup_btn_close')) ?>
        </button>
        <button type="button" class="btn btn-primary" id="menuEditSaveBtn" <?= $permDisabledAttr ?>>
          <i class="ri-save-3-line me-1"></i> <span id="menuEditSaveBtnText"><?= h(__('userGroup_btn_save')) ?></span>
        </button>
      </div>
    </div>
  </div>
</div>

<!-- MODAL: Subgroup Menu -->
<div class="modal fade modal-child-accent" id="menuSubgroupModal" tabindex="-1" aria-hidden="true" aria-labelledby="menuSubgroupTitle" data-bs-backdrop="static">
  <div class="modal-dialog modal-xl modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="menuSubgroupTitle">
          <i class="ri-folder-settings-line"></i>
          <span><?= h(__('userGroup_subgroup_modal_title')) ?></span>
        </h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?= h(__('userGroup_btn_close')) ?>"></button>
      </div>
      <div class="modal-body">
        <div class="row g-3">
          <div class="col-lg-5">
            <form id="menuSubgroupForm" autocomplete="off" class="subgroup-manager-form h-100">
              <input type="hidden" id="sg_subgroupID" value="0">
              <input type="hidden" id="sg_icon" value="ri-folder-2-line">
              <input type="hidden" id="sg_order" value="0">

              <div class="subgroup-form-section">
                <div class="subgroup-section-title"><i class="ri-layout-4-line"></i><span><?= h(__('userGroup_field_modul')) ?></span></div>
                <div class="row g-3">
                  <div class="col-md-8">
                    <label class="form-label"><?= h(__('userGroup_field_modul')) ?></label>
                    <select class="form-select" id="sg_modulID"></select>
                  </div>
                  <div class="col-md-4">
                    <label class="form-label"><?= h(__('userGroup_subgroup_order')) ?></label>
                    <div class="subgroup-order-chip" title="Auto">
                      <i class="ri-sort-asc"></i>
                      <span id="sg_orderPreview">Auto</span>
                    </div>
                  </div>
                </div>
              </div>

              <div class="subgroup-form-section">
                <div class="subgroup-section-title"><i class="ri-text"></i><span><?= h(__('userGroup_field_subgroup')) ?></span></div>
                <div class="mb-3">
                  <label class="form-label"><?= h(__('userGroup_subgroup_code')) ?></label>
                  <input type="text" class="form-control" id="sg_code" placeholder="<?= h(__('userGroup_subgroup_code_placeholder')) ?>">
                </div>
                <div class="row g-3">
                  <div class="col-md-6">
                    <label class="form-label"><?= h(__('userGroup_field_name_ms')) ?></label>
                    <input type="text" class="form-control" id="sg_name_ms" required>
                  </div>
                  <div class="col-md-6">
                    <label class="form-label"><?= h(__('userGroup_field_name_en')) ?></label>
                    <input type="text" class="form-control" id="sg_name_en">
                  </div>
                </div>
              </div>

              <div class="subgroup-form-section">
                <div class="subgroup-section-title"><i class="ri-palette-line"></i><span><?= h(__('userGroup_subgroup_icon')) ?></span></div>
                <div class="subgroup-icon-picker" id="sg_iconPicker" aria-label="<?= h(__('userGroup_subgroup_icon')) ?>">
                  <?php
                    $subgroupIcons = [
                      'ri-folder-2-line' => 'Folder',
                      'ri-settings-3-line' => 'Settings',
                      'ri-shield-user-line' => 'Access',
                      'ri-file-list-3-line' => 'List',
                      'ri-tools-line' => 'Tools',
                      'ri-database-2-line' => 'Database',
                      'ri-user-settings-line' => 'User',
                      'ri-apps-2-line' => 'Apps',
                      'ri-stack-line' => 'Stack',
                      'ri-lock-password-line' => 'Security',
                      'ri-notification-3-line' => 'Notification',
                      'ri-window-line' => 'Page',
                    ];
                  ?>
                  <?php foreach ($subgroupIcons as $iconClass => $iconLabel): ?>
                    <button type="button" class="subgroup-icon-option<?= $iconClass === 'ri-folder-2-line' ? ' active' : '' ?>" data-icon="<?= h($iconClass) ?>" title="<?= h($iconLabel) ?>" aria-label="<?= h($iconLabel) ?>">
                      <i class="<?= h($iconClass) ?>"></i>
                    </button>
                  <?php endforeach; ?>
                </div>
              </div>

              <div class="subgroup-form-section">
                <div class="row g-3 align-items-end">
                  <div class="col-md-6">
                    <label class="form-label"><?= h(__('userGroup_field_status')) ?></label>
                    <select class="form-select" id="sg_status">
                      <option value="1"><?= h(__('userGroup_status_on')) ?></option>
                      <option value="0"><?= h(__('userGroup_status_off')) ?></option>
                    </select>
                  </div>
                  <div class="col-md-6 text-md-end">
                    <button type="button" class="btn btn-light me-1" id="menuSubgroupResetBtn"><?= h(__('userGroup_btn_reset')) ?></button>
                    <button type="button" class="btn btn-primary" id="menuSubgroupSaveBtn" <?= $permDisabledAttr ?>>
                      <i class="ri-save-3-line me-1"></i><?= h(__('userGroup_btn_save')) ?>
                    </button>
                  </div>
                </div>
              </div>
              <div id="menuSubgroupError" class="alert alert-danger d-none mt-3"></div>
            </form>
          </div>
          <div class="col-lg-7">
            <div class="table-responsive subgroup-table-card">
              <table class="table table-sm table-hover mb-0 align-top" id="menuSubgroupTable">
                <thead class="table-light">
                  <tr>
                    <th><?= h(__('userGroup_field_modul')) ?></th>
                    <th><?= h(__('userGroup_field_subgroup')) ?></th>
                    <th class="text-center"><?= h(__('userGroup_subgroup_order')) ?></th>
                    <th class="text-center"><?= h(__('userGroup_col_actions')) ?></th>
                  </tr>
                </thead>
                <tbody>
                  <tr><td colspan="4" class="text-center text-muted py-4"><?= h(__('userGroup_loading')) ?>...</td></tr>
                </tbody>
              </table>
            </div>
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button class="btn btn-secondary" data-bs-dismiss="modal">
          <i class="ri-close-line me-1"></i> <?= h(__('userGroup_btn_close')) ?>
        </button>
      </div>
    </div>
  </div>
</div>
<!-- MODAL: Akses Kumpulan -->
<div class="modal fade modal-themed" id="aksesGroupModal" tabindex="-1" aria-hidden="true" aria-labelledby="aksesGroupTitle">
  <div class="modal-dialog modal-xl modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="aksesGroupTitle">
          <i class="ri-user-settings-line"></i>
          <span><?= h(__('userGroup_modal_group_access_title')) ?></span>
        </h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?= h(__('userGroup_btn_close')) ?>"></button>
      </div>
      <div class="modal-subtitle" id="aksesGroupSub"></div>
      <div class="modal-body">
        <div id="grpLoading" class="modal-loading">
          <div class="spinner-border" role="status" aria-hidden="true"></div>
          <span><?= h(__('userGroup_loading')) ?>…</span>
        </div>
        <div id="grpError" class="modal-error alert alert-danger d-none"></div>
        <div id="grpCnt" class="d-none"></div>
      </div>
      <div class="modal-footer">
        <button class="btn btn-secondary" data-bs-dismiss="modal">
          <i class="ri-close-line me-1"></i> <?= h(__('userGroup_btn_close')) ?>
        </button>
      </div>
    </div>
  </div>
</div>

<!-- MODAL: Ringkasan (SEPARATE) -->
<div class="modal fade modal-themed modal-child-accent" id="ringkasanModal" tabindex="-1" aria-hidden="true" aria-labelledby="ringkasanTitle">
  <div class="modal-dialog modal-lg modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="ringkasanTitle">
          <i class="ri-file-list-3-line"></i>
          <span><?= h(__('userGroup_modal_summary_title')) ?></span>
        </h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?= h(__('userGroup_btn_close')) ?>"></button>
      </div>
      <div class="modal-body">
        <div id="ringkasanLoading" class="modal-loading">
          <div class="spinner-border" role="status" aria-hidden="true"></div>
          <span><?= h(__('userGroup_loading')) ?>…</span>
        </div>
        <div id="ringkasanError" class="modal-error alert alert-danger d-none"></div>
        <div id="ringkasanContent" class="d-none"></div>
      </div>
      <div class="modal-footer">
        <button class="btn btn-secondary" data-bs-dismiss="modal">
          <i class="ri-close-line me-1"></i> <?= h(__('userGroup_btn_close')) ?>
        </button>
      </div>
    </div>
  </div>
</div>

<!-- MODAL: Pemilih Menu (SEPARATE) -->
<div class="modal fade modal-themed modal-child-accent" id="menuPickModal" tabindex="-1" aria-hidden="true" aria-labelledby="menuPickTitle">
  <div class="modal-dialog modal-lg modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="menuPickTitle">
          <i class="ri-list-check-2"></i>
          <span><?= h(__('userGroup_modal_pick_menu_title')) ?></span>
        </h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?= h(__('userGroup_btn_close')) ?>"></button>
      </div>
      <div class="modal-subtitle" id="menuPickSub"></div>
      <div class="modal-body">
        <div id="menuPickLoading" class="modal-loading">
          <div class="spinner-border" role="status" aria-hidden="true"></div>
          <span><?= h(__('userGroup_loading')) ?>…</span>
        </div>
        <div id="menuPickError" class="modal-error alert alert-danger d-none"></div>
        <div id="menuPickContent" class="d-none"></div>
      </div>
      <div class="modal-footer">
        <button class="btn btn-secondary" data-bs-dismiss="modal">
          <i class="ri-close-line me-1"></i> <?= h(__('userGroup_btn_close')) ?>
        </button>
      </div>
    </div>
  </div>
</div>

      <!-- MODAL: Tambah Kumpulan -->
      <div class="modal fade modal-themed" id="groupCreateModal" tabindex="-1" aria-hidden="true" aria-labelledby="groupCreateTitle">
        <div class="modal-dialog modal-xl">
          <div class="modal-content">
            <div class="modal-header">
              <h5 class="modal-title" id="groupCreateTitle"><i class="ri-add-line"></i> <span><?= h(__('userGroup_modal_group_create_title')) ?></span></h5>
              <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?= h(__('userGroup_btn_close')) ?>"></button>
            </div>
            <div class="modal-body">
              <form id="groupCreateForm" autocomplete="off">
                <input type="hidden" id="gc_groupID" name="groupID" value="">
                <div class="group-create-section-title">
                  <i class="ri-shield-user-line"></i>
                  <span><?= h(__('userGroup_field_group_identity')) ?></span>
                </div>
                <div class="row g-3">
                  <div class="col-md-4">
                    <label class="form-label"><?= h(__('userGroup_field_group_code')) ?></label>
                    <input type="text" class="form-control" id="gc_groupKod" name="groupKod" placeholder="e.g. ADM-XX" required>
                  </div>
                  <div class="col-md-4">
                    <label class="form-label"><?= h(__('userGroup_field_group_name')) ?></label>
                    <input type="text" class="form-control" id="gc_groupName" name="groupName" required>
                  </div>
                  <div class="col-md-4">
                    <label class="form-label"><?= h(__('userGroup_field_group_category')) ?></label>
                    <select class="form-select" id="gc_categoryUser" name="categoryUser" required>
                      <option value="STAF">STAF</option>
                      <option value="PELAJAR">PELAJAR</option>
                      <option value="UMUM">UMUM</option>
                    </select>
                    <div class="form-text"><?= h(__('userGroup_field_group_category_help')) ?></div>
                  </div>
                  <input type="hidden" id="gc_priority" name="priority" value="0">
                  <input type="hidden" id="gc_mod" name="mod" value="0">
                </div>

                <div class="group-create-section-title mt-4">
                  <i class="ri-palette-line"></i>
                  <span><?= h(__('userGroup_field_group_presentation')) ?></span>
                </div>
                <div class="row g-3">
                  <div class="col-md-6">
                    <label class="form-label"><?= h(__('userGroup_field_color')) ?></label>
                    <div class="input-group">
                      <input type="color" class="form-control form-control-color" id="gc_color_picker" value="#50a4c1" title="<?= h(__('userGroup_color_picker_title')) ?>">
                      <input type="text" class="form-control" id="gc_color" name="color" placeholder="#50a4c1" readonly>
                    </div>
                    <div class="form-text"><?= h(__('userGroup_field_color_help')) ?></div>
                  </div>
                  <div class="col-md-6">
                    <label class="form-label"><?= h(__('userGroup_field_group_preview')) ?></label>
                    <div class="group-create-preview">
                      <div class="group-create-preview-meta">
                        <div class="group-create-preview-label"><?= h(__('userGroup_field_group_preview')) ?></div>
                        <div class="group-create-preview-main">
                          <span class="group-create-preview-code" id="gc_previewCode">ADM-XX</span>
                          <span class="group-create-preview-chip" id="gc_previewName">Nama Kumpulan</span>
                        </div>
                      </div>
                      <span class="group-create-preview-chip group-category-chip" id="gc_previewCategory" data-category="STAF">STAF</span>
                    </div>
                  </div>
                </div>

                <div class="group-create-section-title mt-4">
                  <i class="ri-links-line"></i>
                  <span><?= h(__('userGroup_field_group_access_setup')) ?></span>
                </div>
                <div class="row g-3 mt-1">
                  <div class="col-md-6">
                    <label class="form-label"><?= h(__('userGroup_field_pick_module')) ?></label>
                    <select class="form-select" id="gc_moduls" name="modulAccess" multiple size="6">
                      <!-- options populated dynamically -->
                    </select>
                    <div class="form-text"><?= h(__('userGroup_field_pick_module_help')) ?></div>
                  </div>
                  <div class="col-md-6">
                    <label class="form-label"><?= h(__('userGroup_field_pick_menu')) ?></label>
                    <select class="form-select" id="gc_menus" name="menuAccess" multiple size="6">
                      <!-- options populated dynamically based on selected modul -->
                    </select>
                    <div class="form-text"><?= h(__('userGroup_field_pick_menu_help')) ?></div>
                  </div>
                </div>
              </form>
              <div id="groupCreateError" class="modal-error alert alert-danger d-none mt-3"></div>
            </div>
            <div class="modal-footer">
              <button class="btn btn-secondary" data-bs-dismiss="modal"><i class="ri-close-line me-1"></i> <?= __('btn_close') ?></button>
              <button class="btn btn-primary" id="groupCreateSaveBtn" <?= $permDisabledAttr ?>><i class="ri-save-3-line me-1"></i> <span id="groupCreateSaveBtnText"><?= __('btn_save') ?></span></button>
            </div>
          </div>
        </div>
      </div>

      <!-- MODAL: Tambah Modul -->
<div class="modal fade modal-themed" id="moduleCreateModal" tabindex="-1" aria-hidden="true" aria-labelledby="moduleCreateTitle">
        <div class="modal-dialog modal-lg">
          <div class="modal-content">
            <div class="modal-header">
              <h5 class="modal-title" id="moduleCreateTitle"><i class="ri-stack-line"></i> <span><?= h(__('modul_tambah_title')) ?></span></h5>
              <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?= h(__('modul_batal')) ?>"></button>
            </div>
            <div class="modal-body">
              <form id="moduleCreateForm" method="post" autocomplete="off">
                <input type="hidden" name="action" value="add_module">
                <input type="hidden" name="csrf_token" value="<?= h($csrf) ?>">
                <input type="hidden" name="moduleID" id="mc_moduleID" value="">
                <div class="module-create-layout">
                  <div class="module-create-panel">
                    <div class="module-create-panel-title">
                      <i class="ri-file-text-line"></i>
                      <span id="moduleCreatePanelTitleText"><?= h(__('modul_tambah_title')) ?></span>
                    </div>
                    <div class="row g-3">
                      <div class="col-12">
                        <label class="form-label"><?= h(__('modul_nama_ms')) ?></label>
                        <input type="text" class="form-control" id="mc_modulNameMs" name="modulNameMs" value="<?= h($moduleFormData['modulNameMs']) ?>" required>
                      </div>
                      <div class="col-12">
                        <label class="form-label"><?= h(__('modul_nama_en')) ?></label>
                        <input type="text" class="form-control" id="mc_modulNameEn" name="modulNameEn" value="<?= h($moduleFormData['modulNameEn']) ?>">
                      </div>
                      <div class="col-12">
                        <label class="form-label"><?= h(__('modul_susunan')) ?></label>
                        <input type="text" class="form-control module-order-readonly" id="mc_order" value="<?= h((string)$nextModuleOrder) ?>" readonly aria-readonly="true">
                        <small class="module-field-help"><?= h(__('modul_order_auto_help')) ?></small>
                      </div>
                    </div>
                  </div>
                  <div class="module-create-panel">
                    <div class="module-create-panel-title">
                      <i class="ri-apps-2-line"></i>
                      <span><?= h(__('modul_icon')) ?></span>
                    </div>
                    <div class="module-icon-picker">
                      <div class="module-icon-preview" id="mc_iconPreview" aria-hidden="true">
                        <i class="<?= h($defaultModuleIcon) ?>"></i>
                      </div>
                      <div class="flex-grow-1">
                        <input type="text" class="form-control" id="mc_icon" name="icon" value="<?= h($defaultModuleIcon) ?>" autocomplete="off">
                        <small class="module-field-help"><?= h(__('modul_icon_help')) ?></small>
                      </div>
                    </div>
                    <div class="module-icon-groups" id="mc_iconGrid" aria-label="<?= h(__('modul_icon')) ?>">
                      <?php foreach ($moduleIconGroups as $iconGroup): ?>
                        <div class="module-icon-group">
                          <div class="module-icon-group-title"><?= h((string)($iconGroup['label'] ?? '')) ?></div>
                          <div class="module-icon-grid">
                            <?php foreach (($iconGroup['icons'] ?? []) as $iconClass): ?>
                              <button
                                type="button"
                                class="module-icon-option<?= ((string)$defaultModuleIcon === (string)$iconClass) ? ' is-active' : '' ?>"
                                data-icon="<?= h($iconClass) ?>"
                                title="<?= h($iconClass) ?>"
                                aria-label="<?= h($iconClass) ?>">
                                <i class="<?= h($iconClass) ?>"></i>
                              </button>
                            <?php endforeach; ?>
                          </div>
                        </div>
                      <?php endforeach; ?>
                    </div>
                  </div>
                </div>
              </form>
            </div>
            <div class="modal-footer">
              <button class="btn btn-secondary" data-bs-dismiss="modal"><i class="ri-close-line me-1"></i> <?= h(__('modul_batal')) ?></button>
              <button class="btn btn-primary" id="moduleCreateSaveBtn" <?= $permDisabledAttr ?>><i class="ri-save-3-line me-1"></i> <span id="moduleCreateSaveBtnText"><?= h(__('modul_simpan')) ?></span></button>
            </div>
          </div>
        </div>
      </div>

<script>
// ✅ Global utility function untuk check DataTables availability
window.hasDT = function() {
  return !!(window.jQuery && window.jQuery.fn && window.jQuery.fn.DataTable);
};

(function(){
  const canManageGroups = <?= $canManageGroups ? 'true' : 'false' ?>;
  window.GroupModuleOptions = <?= json_encode($menuModalModuleOptions, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;

  // ===================== I18N ringkas ======================
  const T = {
    label_module: <?= json_encode(__('userGroup_label_module')) ?>,
    label_menu: <?= json_encode(__('userGroup_label_menu')) ?>,
    modul_fallback: <?= json_encode(__('userGroup_label_modul_fallback')) ?>,
    no_records: <?= json_encode(__('userGroup_no_records')) ?>,
    col_menu: <?= json_encode(__('userGroup_col_menu')) ?>,
    col_reorder: <?= json_encode(__('userGroup_col_reorder')) ?>,
    move_up: <?= json_encode(__('userGroup_move_up')) ?>,
    move_down: <?= json_encode(__('userGroup_move_down')) ?>,
    btn_add_menu: <?= json_encode(__('userGroup_btn_add_menu')) ?>,
    btn_add_module: <?= json_encode(__('modul_tambah')) ?>,
    col_status: <?= json_encode(__('userGroup_col_status')) ?>,
    col_actions: <?= json_encode(__('userGroup_col_actions')) ?>,
    status_on: <?= json_encode(__('userGroup_status_on')) ?>,
    status_off: <?= json_encode(__('userGroup_status_off')) ?>,
    loading: <?= json_encode(__('userGroup_loading')) ?>,
    saving: <?= json_encode(__('globalLoader_saving')) ?>,
    error_network: <?= json_encode(__('userGroup_error_network')) ?>,
    error_unknown: <?= json_encode(__('userGroup_error_unknown')) ?>,
    error_reorder: <?= json_encode(__('userGroup_error_reorder')) ?>,
    error_load_access: <?= json_encode(__('userGroup_error_load_access')) ?>,
    error_load_menu: <?= json_encode(__('userGroup_error_load_menu')) ?>,
    error_get_menu: <?= json_encode(__('userGroup_error_get_menu')) ?>,
    err_path_required: <?= json_encode(__('userGroup_err_path_required')) ?>,
    err_modul_required: <?= json_encode(__('userGroup_err_modul_required')) ?>,
    err_group_modul_path_required: <?= json_encode(__('userGroup_err_group_modul_path_required')) ?>,
    err_add_menu: <?= json_encode(__('userGroup_err_add_menu')) ?>,
    err_save_menu: <?= json_encode(__('userGroup_err_save_menu')) ?>,
    delete_fail: <?= json_encode(__('userGroup_delete_fail')) ?>,
    delete_group_success: <?= json_encode(__('userGroup_delete_group_success')) ?>,
    delete_group_fail: <?= json_encode(__('userGroup_delete_group_fail')) ?>,
    delete_group_network_fail: <?= json_encode(__('userGroup_delete_group_network_fail')) ?>,
    delete_menu_cleanup_success: <?= json_encode(__('userGroup_delete_menu_cleanup_success')) ?>,
    deleted_title: <?= json_encode(__('userGroup_deleted_title')) ?>,
    delete_failed_title: <?= json_encode(__('userGroup_delete_failed_title')) ?>,
    not_allowed_title: <?= json_encode(__('userGroup_not_allowed_title')) ?>,
    field_modul: <?= json_encode(__('userGroup_field_modul')) ?>,
    field_subgroup: <?= json_encode(__('userGroup_field_subgroup')) ?>,
    subgroup_none: <?= json_encode(__('userGroup_subgroup_none')) ?>,
    subgroup_manage: <?= json_encode(__('userGroup_subgroup_manage')) ?>,
    subgroup_modal_title: <?= json_encode(__('userGroup_subgroup_modal_title')) ?>,
    subgroup_required: <?= json_encode(__('userGroup_subgroup_required')) ?>,
    subgroup_not_found: <?= json_encode(__('userGroup_subgroup_not_found')) ?>,
    subgroup_in_use: <?= json_encode(__('userGroup_subgroup_in_use')) ?>,
    subgroup_save_success: <?= json_encode(__('userGroup_subgroup_save_success')) ?>,
    subgroup_delete_success: <?= json_encode(__('userGroup_subgroup_delete_success')) ?>,
    subgroup_confirm_delete: <?= json_encode(__('userGroup_subgroup_confirm_delete')) ?>,
    subgroup_confirm_delete_title: <?= json_encode(__('userGroup_subgroup_confirm_delete_title')) ?>,
    subgroup_confirm_delete_text: <?= json_encode(__('userGroup_subgroup_confirm_delete_text')) ?>,
    subgroup_load_fail: <?= json_encode(__('userGroup_subgroup_load_fail')) ?>,
    edit: <?= json_encode(__('userGroup_edit')) ?>,
    delete: <?= json_encode(__('userGroup_delete')) ?>,
    loading_modules: <?= json_encode(__('userGroup_loading_modules')) ?>,
    loading_short: <?= json_encode(__('userGroup_loading_short')) ?>,
    load_modules_fail: <?= json_encode(__('userGroup_load_modules_fail')) ?>,
    no_modules_found: <?= json_encode(__('userGroup_no_modules_found')) ?>,
    search_group_placeholder: <?= json_encode(__('userGroup_search_group_placeholder')) ?>,
    search_menu_placeholder: <?= json_encode(__('userGroup_search_menu_placeholder')) ?>,
    module_reorder_note: <?= json_encode(__('userGroup_module_reorder_note')) ?>,
    module_drag_label: <?= json_encode(__('userGroup_module_drag_label')) ?>,
    module_edit_label: <?= json_encode(__('modul_edit_label')) ?>,
    module_delete_label: <?= json_encode(__('userGroup_module_delete_label')) ?>,
    module_edit_success: <?= json_encode(__('modul_kemaskini_msg')) ?>,
    module_update_title: <?= json_encode(__('modul_kemaskini_title')) ?>,
    delete_module_confirm_title: <?= json_encode(__('userGroup_delete_module_confirm_title')) ?>,
    delete_module_confirm_text: <?= json_encode(__('userGroup_delete_module_confirm_text')) ?>,
    delete_module_confirm_fallback: <?= json_encode(__('userGroup_delete_module_confirm_fallback')) ?>,
    delete_module_fail: <?= json_encode(__('userGroup_delete_module_fail')) ?>,
    delete_module_success: <?= json_encode(__('userGroup_delete_module_success')) ?>,
    delete_module_network_fail: <?= json_encode(__('userGroup_delete_module_network_fail')) ?>,
    delete_module_label: <?= json_encode(__('userGroup_module_delete_label')) ?>,
    success_title: <?= json_encode(__('config_js_berjaya')) ?>,
    btn_ok: <?= json_encode(__('config_js_btn_ok')) ?>,
    btn_cancel: <?= json_encode(__('config_js_btn_cancel')) ?>,
    dt_length_menu: <?= json_encode(__('userGroup_dt_length_menu')) ?>,
    dt_info: <?= json_encode(__('userGroup_dt_info')) ?>,
    dt_info_empty: <?= json_encode(__('userGroup_dt_info_empty')) ?>,
    dt_info_filtered: <?= json_encode(__('userGroup_dt_info_filtered')) ?>,
    dt_paginate_first: <?= json_encode(__('userGroup_dt_paginate_first')) ?>,
    dt_paginate_last: <?= json_encode(__('userGroup_dt_paginate_last')) ?>,
    dt_paginate_next: <?= json_encode(__('userGroup_dt_paginate_next')) ?>,
    dt_paginate_previous: <?= json_encode(__('userGroup_dt_paginate_previous')) ?>,
    modal_group_create_title: <?= json_encode(__('userGroup_modal_group_create_title')) ?>,
    modal_group_edit_title: <?= json_encode(__('userGroup_modal_group_edit_title')) ?>,
    modal_add_menu_title: <?= json_encode(__('userGroup_modal_add_menu_title')) ?>,
    modal_edit_menu_title: <?= json_encode(__('userGroup_modal_edit_menu_title')) ?>,
    field_group_preview: <?= json_encode(__('userGroup_field_group_preview')) ?>,
    btn_save: <?= json_encode(__('btn_save')) ?>,
    btn_update: <?= json_encode(__('btn_update')) ?>,
    btn_close: <?= json_encode(__('btn_close')) ?>,
    done: <?= json_encode(__('userGroup_done')) ?>,
    error: <?= json_encode(__('userGroup_error')) ?>,
    group_create_success: <?= json_encode(__('userGroup_group_create_success')) ?>,
    group_update_success: <?= json_encode(__('userGroup_group_update_success')) ?>,
    loading_access: <?= json_encode(__('userGroup_loading_access')) ?>,
    loading_menu: <?= json_encode(__('userGroup_loading_menu')) ?>,
    err_group_code_name_required: <?= json_encode(__('userGroup_err_group_code_name_required')) ?>,
    confirm_title: <?= json_encode(__('userGroup_confirm_title')) ?>,
    confirm_delete_group_text: <?= json_encode(__('userGroup_confirm_delete_group_text')) ?>,
    confirm_delete_menu_title: <?= json_encode(__('userGroup_confirm_delete_menu_title')) ?>,
    confirm_delete_menu_intro: <?= json_encode(__('userGroup_confirm_delete_menu_intro')) ?>,
    confirm_delete_menu_cleanup: <?= json_encode(__('userGroup_confirm_delete_menu_cleanup')) ?>,
    confirm_delete_menu_irreversible: <?= json_encode(__('userGroup_confirm_delete_menu_irreversible')) ?>,
    confirm_delete_menu_fallback: <?= json_encode(__('userGroup_confirm_delete_menu_fallback')) ?>,
    confirm_yes_delete: <?= json_encode(__('userGroup_confirm_yes_delete')) ?>,
    confirm_cancel: <?= json_encode(__('userGroup_confirm_cancel')) ?>,
    info_title: <?= json_encode(__('userGroup_info_title')) ?>,
    info_select_group_first: <?= json_encode(__('userGroup_info_select_group_first')) ?>,
    btn_menu_label: <?= json_encode(__('userGroup_btn_menu_label')) ?>,
    btn_module_label: <?= json_encode(__('userGroup_btn_module_label')) ?>,
    btn_group_label: <?= json_encode(__('userGroup_btn_group_label')) ?>,
    userGroup_col_group_access: <?= json_encode(__('userGroup_col_group_access')) ?>,
    userGroup_col_module_access: <?= json_encode(__('userGroup_col_module_access')) ?>,
    userGroup_col_menu_access: <?= json_encode(__('userGroup_col_menu_access')) ?>,
    userGroup_edit_group: <?= json_encode(__('userGroup_edit_group')) ?>,
    userGroup_delete_group: <?= json_encode(__('userGroup_delete_group')) ?>,
    label_group: <?= json_encode(__('userGroup_label_group')) ?>,
    col_visibility: <?= json_encode(__('userGroup_col_visibility')) ?>,
    menu_path_info: <?= json_encode(__('userGroup_menu_path_info')) ?>,
    menu_staff_only_show: <?= json_encode(__('userGroup_menu_staff_only_show')) ?>,
    menu_staff_only_hide: <?= json_encode(__('userGroup_menu_staff_only_hide')) ?>,
    menu_staff_only_show_full: <?= json_encode(__('userGroup_menu_staff_only_show_full')) ?>,
    menu_staff_only_hide_full: <?= json_encode(__('userGroup_menu_staff_only_hide_full')) ?>,
    error_update_status: <?= json_encode(__('userGroup_error_update_status')) ?>,
    modal_summary_title: <?= json_encode(__('userGroup_modal_summary_title')) ?>,
    pick_module_aria: <?= json_encode(__('userGroup_pick_module_aria')) ?>,
    pick_menu_button: <?= json_encode(__('userGroup_pick_menu_button')) ?>,
    pick_menu_none: <?= json_encode(__('userGroup_pick_menu_none')) ?>,
    pick_menu_on: <?= json_encode(__('userGroup_pick_menu_on')) ?>,
    pick_menu_off: <?= json_encode(__('userGroup_pick_menu_off')) ?>,
    summary_load_fail: <?= json_encode(__('userGroup_summary_load_fail')) ?>,
    summary_empty: <?= json_encode(__('userGroup_summary_empty')) ?>,
    summary_no_menu: <?= json_encode(__('userGroup_summary_no_menu')) ?>,
    summary_col_module: <?= json_encode(__('userGroup_summary_col_module')) ?>,
    summary_col_menu: <?= json_encode(__('userGroup_summary_col_menu')) ?>,
    error_save: <?= json_encode(__('userGroup_error_save')) ?>,
    error_load: <?= json_encode(__('userGroup_error_load')) ?>,
    reorder_label: <?= json_encode(__('userGroup_reorder_label')) ?>,
    rate_limit_text: <?= json_encode(__('userGroup_rate_limit_text')) ?>,
    err_server: <?= json_encode(__('userGroup_err_server')) ?>,
    server_error_prefix: <?= json_encode(__('userGroup_server_error_prefix')) ?>,
    group_invalid_id: <?= json_encode(__('userGroup_group_invalid_id')) ?>,
    menu_invalid_id: <?= json_encode(__('userGroup_menu_invalid_id')) ?>,
    group_not_found: <?= json_encode(__('userGroup_group_not_found')) ?>,
    menu_not_found: <?= json_encode(__('userGroup_menu_not_found')) ?>,
    target_module_not_found: <?= json_encode(__('userGroup_target_module_not_found')) ?>,
    menu_path_duplicate: <?= json_encode(__('userGroup_menu_path_duplicate')) ?>,
    group_code_duplicate: <?= json_encode(__('userGroup_group_code_duplicate')) ?>,
    group_code_conflict: <?= json_encode(__('userGroup_group_code_conflict')) ?>,
    group_create_required: <?= json_encode(__('userGroup_group_create_required')) ?>,
    group_create_permission_denied: <?= json_encode(__('userGroup_group_create_permission_denied')) ?>,
    group_delete_permission_denied: <?= json_encode(__('userGroup_group_delete_permission_denied')) ?>,
    group_permissions_not_allowed: <?= json_encode(__('userGroup_group_permissions_not_allowed')) ?>,
    menu_create_permission_denied: <?= json_encode(__('userGroup_menu_create_permission_denied')) ?>,
    menu_update_permission_denied: <?= json_encode(__('userGroup_menu_update_permission_denied')) ?>,
    menu_delete_permission_denied: <?= json_encode(__('userGroup_menu_delete_permission_denied')) ?>,
    menu_status_permission_denied: <?= json_encode(__('userGroup_menu_status_permission_denied')) ?>,
    invalid_payload: <?= json_encode(__('userGroup_invalid_payload')) ?>,
    menu_not_same_module: <?= json_encode(__('userGroup_menu_not_same_module')) ?>,
    menu_read_order_error: <?= json_encode(__('userGroup_menu_read_order_error')) ?>,
    group_system_protected: <?= json_encode(__('userGroup_group_system_protected')) ?>,
    group_users_assigned: <?= json_encode(__('userGroup_group_users_assigned')) ?>,
    ok: <?= json_encode(__('userGroup_ok')) ?>,
    confirm_yes: <?= json_encode(__('userGroup_confirm_yes')) ?>,
    non_json_response: <?= json_encode(__('userGroup_non_json_response')) ?>,
    menu_save_success_create: <?= json_encode(__('userGroup_menu_save_success_create')) ?>,
    menu_save_success_update: <?= json_encode(__('userGroup_menu_save_success_update')) ?>
  };
  // Expose page translations for other script scopes (e.g. second IIFE / DataTable init)
  window.GroupPageT = T;
  window.T = T;
  window.GroupSwal = window.GroupSwal || {
    fire(options) {
      if (!window.Swal || typeof Swal.fire !== 'function') return Promise.resolve(null);
      const opts = options && typeof options === 'object' ? options : {};
      const defaultCustomClass = {
        container: 'group-swal-container',
        popup: 'group-swal-popup',
        title: 'group-swal-title',
        htmlContainer: 'group-swal-html',
        confirmButton: 'group-swal-confirm',
        cancelButton: 'group-swal-cancel',
        actions: 'group-swal-actions',
        icon: 'group-swal-icon'
      };
      return Swal.fire(Object.assign({
        confirmButtonText: T.btn_ok || 'OK',
        buttonsStyling: false,
        reverseButtons: true,
        customClass: Object.assign({}, defaultCustomClass, (opts.customClass && typeof opts.customClass === 'object') ? opts.customClass : {})
      }, opts));
    }
  };

  // Initialize modules dan setup event handlers
  function initializeModules() {
    if (typeof ModuleAccess !== 'undefined') {
      try { 
        ModuleAccess.init(T); 
      } catch(e) { 
        console.error('ModuleAccess init error:', e); 
      }
    }
    if (typeof MenuAccess !== 'undefined') {
      try { 
        MenuAccess.init(T); 
      } catch(e) { 
        console.error('MenuAccess init error:', e); 
      }
    }
    if (typeof GroupPermissions !== 'undefined') {
      try { 
        GroupPermissions.init(); 
      } catch(e) { 
        console.error('GroupPermissions init error:', e); 
      }
    }
    
    // Setup event handlers after modules are initialized
    setupEventHandlers();
  }
  
  function setupEventHandlers() {
    // Hook menu order dirty flag untuk extracted modules
    (function hookModalClose() {
      const aksesModalEl = document.getElementById('aksesModal');
      aksesModalEl?.addEventListener('hidden.bs.modal', () => {
        if (typeof GroupState !== 'undefined' && !GroupState.isMenuOrderDirty()) return;
        if (typeof GroupState !== 'undefined') GroupState.setMenuOrderDirty(false);
        if (window.ModuleAccess && typeof window.ModuleAccess.syncSidebarAfterNavigationChange === 'function') {
          window.ModuleAccess.syncSidebarAfterNavigationChange();
          return;
        }
        if (window.AccessUiSync && typeof window.AccessUiSync.syncSidebarSilently === 'function') {
          window.AccessUiSync.syncSidebarSilently({ redirectOnDenied: false }).catch(console.warn);
          return;
        }
        if (typeof MenuRefresh !== 'undefined') MenuRefresh.refreshMainMenu().catch(console.warn);
      });
    })();

    // View access button handler (delegated to ModuleAccess) - using event delegation
    document.body.addEventListener('click', function(e) {
      const btn = e.target.closest('.view-access');
      if (btn && btn.classList.contains('view-access')) {
        if (!canManageGroups) return;
        e.preventDefault();
        e.stopImmediatePropagation();
        if (typeof ModuleAccess !== 'undefined' && typeof ModuleAccess.openAccess === 'function') {
          ModuleAccess.openAccess(btn);
    } else {
          console.error('ModuleAccess.openAccess is not available');
        }
        return false;
      }
    }, true);

    // View menu button handler (delegated to MenuAccess) - using event delegation
    document.body.addEventListener('click', function(e) {
      const btn = e.target.closest('.view-menu');
      if (btn && btn.classList.contains('view-menu')) {
        if (!canManageGroups) return;
        e.preventDefault();
        e.stopImmediatePropagation();
        if (typeof MenuAccess !== 'undefined' && typeof MenuAccess.openMenuFromBtn === 'function') {
          if (typeof GroupState !== 'undefined' && typeof GroupState.setLastMenuBtn === 'function') {
            GroupState.setLastMenuBtn(btn);
          }
          MenuAccess.openMenuFromBtn(btn);
        } else {
          // fallback: try to show modal directly and call editor if available
          try {
            const el = document.getElementById('aksesMenuModal');
            GroupState && typeof GroupState.setLastMenuBtn === 'function' && GroupState.setLastMenuBtn(btn);
            if (el && window.bootstrap && bootstrap.Modal) {
              GroupUtils.ensureInBody(el);
              bootstrap.Modal.getOrCreateInstance(el, { backdrop: true, focus: true, keyboard: true }).show();
            }
            const gid = btn.getAttribute('data-group-id');
            if (typeof MenuAccess !== 'undefined' && typeof MenuAccess.openMenuEditor === 'function') {
              MenuAccess.openMenuEditor(gid);
            }
          } catch (e) {
            console.error('Menu fallback failed', e);
          }
        }
        return false;
      }
    }, true);

    // View group perms button handler (delegated to GroupPermissions)
    document.addEventListener('click', function(e) {
      const btn = e.target.closest('.view-group-perms');
      if (btn) {
        if (!canManageGroups) return;
        e.preventDefault();
        if (typeof GroupPermissions !== 'undefined' && GroupPermissions.openGroupPermsFromBtn) {
          GroupPermissions.openGroupPermsFromBtn(btn);
        }
      }
    }, true);
  }
  
  // Initialize immediately if DOM is ready, otherwise wait
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initializeModules);
    } else {
    // Use setTimeout to ensure all scripts are loaded
    setTimeout(initializeModules, 100);
  }

  // Tooltip ringan
  document.querySelectorAll('[title]').forEach(el => {
    if (window.bootstrap && bootstrap.Tooltip) {
      new bootstrap.Tooltip(el, { container: 'body' });
    }
  });
})();

// =========================================================
// Priority 3: UX Improvements
// =========================================================

(function() {
  'use strict';
  const canManageGroups = <?= $canManageGroups ? 'true' : 'false' ?>;
  const T = (window.GroupPageT && typeof window.GroupPageT === 'object') ? window.GroupPageT : {};

  // =========================================================
  // 1. Search/Filter untuk Group List (DataTable)
  // =========================================================
  let groupTableDT = null;
  function initGroupTable() {
    const table = document.getElementById('groupTable');
    if (!table || !window.hasDT()) return;
    
    // Get translations from T object (defined in main script)
    // Get translations from T object (defined in main script)
    const dtLang = (typeof T !== 'undefined') ? {
      searchPlaceholder: T.search_group_placeholder || "Search...",
      lengthMenu: T.dt_length_menu || "Show _MENU_ entries",
      info: T.dt_info || "Showing _START_ to _END_ of _TOTAL_ entries",
      infoEmpty: T.dt_info_empty || "No entries",
      infoFiltered: T.dt_info_filtered || "(filtered from _MAX_ total entries)",
      paginate: {
        first: T.dt_paginate_first || "First",
        last: T.dt_paginate_last || "Last",
        next: T.dt_paginate_next || "Next",
        previous: T.dt_paginate_previous || "Previous"
      }
    } : {
      searchPlaceholder: "Search...",
      lengthMenu: "Show _MENU_ entries",
      info: "Showing _START_ to _END_ of _TOTAL_ entries",
      infoEmpty: "No entries",
      infoFiltered: "(filtered from _MAX_ total entries)",
      paginate: {
        first: "First",
        last: "Last",
        next: "Next",
        previous: "Previous"
      }
    };
    // canManageGroups already defined in parent scope

    const dtStandardOptions = (window.DataTableStandard && typeof window.DataTableStandard.options === 'function')
      ? window.DataTableStandard.options({
          order: [[2, 'asc']],
          columnDefs: [
            { targets: [0], orderable: false },
            { targets: [3, 4, 5, 6, 7], orderable: false, searchable: false },
            { targets: [4], className: 'text-end td-color' },
            { targets: [5], className: 'text-start td-grp' },
            { targets: [6], className: 'text-center td-mod' },
            { targets: [7], className: 'text-center td-menu' }
          ],
          language: {
            search: "",
            searchPlaceholder: dtLang.searchPlaceholder,
            lengthMenu: dtLang.lengthMenu,
            info: dtLang.info,
            infoEmpty: dtLang.infoEmpty,
            infoFiltered: dtLang.infoFiltered,
            paginate: dtLang.paginate
          },
          stateSave: false,
          processing: false,
          dom: '<"row mb-2"<"col-sm-12 col-md-6 dt-top-left"l><"col-sm-12 col-md-6 d-flex justify-content-md-end dt-top-right"f>>' +
            't' +
            '<"dt-bottom-row mt-2 d-flex justify-content-between align-items-center"<"dt-info-left"i><"dt-paging-right d-flex justify-content-end"p>>'
        })
      : {
          pageLength: 10,
          lengthChange: true,
          ordering: true,
          order: [[2, 'asc']],
          columnDefs: [
            { targets: [0], orderable: false },
            { targets: [3, 4, 5, 6, 7], orderable: false, searchable: false },
            { targets: [4], className: 'text-end td-color' },
            { targets: [5], className: 'text-start td-grp' },
            { targets: [6], className: 'text-center td-mod' },
            { targets: [7], className: 'text-center td-menu' }
          ],
          language: {
            search: "",
            searchPlaceholder: dtLang.searchPlaceholder,
            lengthMenu: dtLang.lengthMenu,
            info: dtLang.info,
            infoEmpty: dtLang.infoEmpty,
            infoFiltered: dtLang.infoFiltered,
            paginate: dtLang.paginate
          },
          stateSave: false,
          processing: false,
          dom: '<"row mb-2"<"col-sm-12 col-md-6 dt-top-left"l><"col-sm-12 col-md-6 d-flex justify-content-md-end dt-top-right"f>>' +
            't' +
            '<"dt-bottom-row mt-2 d-flex justify-content-between align-items-center"<"dt-info-left"i><"dt-paging-right d-flex justify-content-end"p>>'
        };

    dtStandardOptions.drawCallback = function(settings) {
        try {
          const api = this.api();
          if (!api || typeof api.page !== 'function') return;
          const pageInfo = api.page.info();
          if (!pageInfo || typeof pageInfo.start === 'undefined') return;
          
          // Update nombor Bil untuk setiap row pada current page
          let rowIndex = 0;
          api.rows({page: 'current'}).every(function() {
            const bilNumber = pageInfo.start + rowIndex + 1;
            const $row = jQuery(this.node());
            $row.find('td:first').text(bilNumber);
            rowIndex++;
            return true;
          });
        } catch (e) {
          // Silent fail
        }
      };
    dtStandardOptions.initComplete = function() {
        if (window.DataTableStandard && typeof window.DataTableStandard.decorate === 'function') {
          window.DataTableStandard.decorate('#groupTable', {
            searchPlaceholder: dtLang.searchPlaceholder
          });
        }
        const $topRightContainer = jQuery('#groupTable_wrapper .dt-top-right');
        const $filterBlock = jQuery('#groupTable_filter');
        if ($topRightContainer.length && $filterBlock.length) {
          $filterBlock.appendTo($topRightContainer);
        }
        // Butang tindakan kekal dalam toolbar halaman supaya tidak berubah kedudukan
        // apabila DataTable dimuat semula atau dilukis semula.
        if (canManageGroups) {
          const $btnMenu = jQuery('#btnAddMenuPage');
          const $btnModule = jQuery('#btnAddModule');
          const $btn = jQuery('#btnAddGroup');

          // Page-level Add Menu handler: delegate to MenuAccess.handleAddMenu when available
          $btnMenu.off('click').on('click', function(){
            try {
              if (window.MenuAccess && typeof MenuAccess.handleAddMenu === 'function') {
                // Attempt to call handler which will resolve group context or show warnings
                MenuAccess.handleAddMenu();
                return;
              }
            } catch (e) { /* ignore and fallback */ }
            // Fallback: show guidance (no modal) to avoid overlay-only state
            if (window.Swal && typeof Swal.fire === 'function') {
              (window.GroupSwal ? GroupSwal.fire({
                icon: 'info',
                title: T.info_title || 'Makluman',
                text: T.info_select_group_first || 'Sila pilih kumpulan dahulu melalui butang Akses Menu.',
                confirmButtonText: T.btn_ok
              }) : Swal.fire({
                icon: 'info',
                title: T.info_title || 'Makluman',
                text: T.info_select_group_first || 'Sila pilih kumpulan dahulu melalui butang Akses Menu.',
                confirmButtonText: T.btn_ok
              }));
            } else {
              alert(T.info_select_group_first || 'Sila pilih kumpulan dahulu melalui butang Akses Menu.');
            }
          });

          $btnModule.off('click').on('click', function(){
            if (typeof window.openModuleFormModal === 'function') {
              window.openModuleFormModal({ mode: 'create' });
              return;
            }
            const modal = new bootstrap.Modal(document.getElementById('moduleCreateModal'));
            const modalEl = document.getElementById('moduleCreateModal');
            if (modalEl) {
              modalEl.classList.remove('modal-child-accent');
              modalEl.classList.add('modal-add-accent');
            }
            const form = document.getElementById('moduleCreateForm');
            if (form && !form.dataset.keepValues) {
              form.reset();
            }
            if (form) delete form.dataset.keepValues;
            modal.show();
          });

          // Existing Add Group handler
          $btn.off('click').on('click', function(){
            if (typeof GroupUtils !== 'undefined' && typeof GroupUtils.setButtonBusy === 'function') {
              GroupUtils.setButtonBusy(document.getElementById('groupCreateSaveBtn'), false);
            }
            const modal = new bootstrap.Modal(document.getElementById('groupCreateModal'));
            const groupModalEl = document.getElementById('groupCreateModal');
            if (groupModalEl) {
              groupModalEl.classList.remove('modal-child-accent');
              groupModalEl.classList.add('modal-add-accent');
            }
            // reset form
            document.getElementById('groupCreateTitle').innerHTML = '<i class="ri-add-line"></i> <span>' + String(T.modal_group_create_title || '') + '</span>';
            const saveTxt = document.getElementById('groupCreateSaveBtnText');
            if (saveTxt) saveTxt.textContent = String(T.btn_save || '');
            document.getElementById('gc_groupID').value = '';
            document.getElementById('gc_groupKod').value = '';
            document.getElementById('gc_groupName').value = '';
            document.getElementById('gc_categoryUser').value = 'STAF';
            document.getElementById('gc_color_picker').value = '#50a4c1';
            document.getElementById('gc_color').value = '#50a4c1';
            document.getElementById('gc_priority').value = '0';
            document.getElementById('gc_mod').value = '0';
            if (window.MenuAccess && typeof window.MenuAccess.syncGroupPreview === 'function') {
              window.MenuAccess.syncGroupPreview();
            }
            modal.show();
            try {
              if (window.MenuAccess && typeof window.MenuAccess.populateCreateModal === 'function') {
                window.MenuAccess.populateCreateModal().catch(()=>{});
              }
            } catch(e){ /* ignore */ }
          });
        }
      };

    groupTableDT = jQuery('#groupTable').DataTable(dtStandardOptions);
  }

  // =========================================================
  // 2. Loading Indicators untuk Button Clicks
  // =========================================================
  function setButtonLoading(btn, isLoading) {
    if (!btn) return;
    
    // For icon-only buttons, just add/remove loading class without changing content
    const isIconButton = btn.classList.contains('icon-btn') || 
                         (btn.querySelector('i') && !btn.textContent.trim());
    
    if (isLoading) {
      btn.classList.add('btn-loading');
      btn.disabled = true;
      
      // Only modify content for buttons with text, not icon-only buttons
      if (!isIconButton) {
        const text = btn.querySelector('.btn-text') || btn.childNodes[0];
        if (text && text.nodeType === 3) {
          btn.dataset.originalText = text.textContent;
          text.textContent = '';
        } else if (text && btn.innerHTML) {
          btn.dataset.originalText = btn.innerHTML;
          btn.innerHTML = '<span class="btn-text" style="opacity:0">' + btn.innerHTML + '</span>';
        }
      }
    } else {
      btn.classList.remove('btn-loading');
      btn.disabled = false;
      
      // Only restore content for buttons with text
      if (!isIconButton && btn.dataset.originalText) {
        btn.innerHTML = btn.dataset.originalText;
        delete btn.dataset.originalText;
      }
    }
  }

  // Add loading to action buttons (skip icon-only buttons to preserve icons)
  document.addEventListener('click', function(e) {
    const btn = e.target.closest('.view-access, .view-menu, .view-group-perms');
    if (btn && !btn.disabled) {
      const isIconButton = btn.classList.contains('icon-btn');
      
      // Skip loading indicator for icon-only buttons to preserve icon appearance
      if (isIconButton) {
        // Just briefly disable to prevent double-clicks, no visual change
        btn.disabled = true;
        const reEnable = () => {
          btn.disabled = false;
        };
        // Re-enable after modal opens or after timeout
        const timeout = setTimeout(reEnable, 2000);
        document.addEventListener('shown.bs.modal', () => {
          clearTimeout(timeout);
          reEnable();
        }, { once: true });
        return; // Don't apply loading indicator
      }
      
      // For buttons with text, apply loading indicator
      if (!btn.classList.contains('btn-loading')) {
        const originalDisabled = btn.disabled;
        setButtonLoading(btn, true);
        
        const cleanup = () => {
          setButtonLoading(btn, false);
          btn.disabled = originalDisabled;
        };
        
        const timeout = setTimeout(cleanup, 3000);
        document.addEventListener('shown.bs.modal', () => {
          clearTimeout(timeout);
          cleanup();
        }, { once: true });
      }
    }
    }, true);

  // Initialize on DOM ready
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', function() {
      setTimeout(initGroupTable, 100);
    });
      } else {
    setTimeout(initGroupTable, 100);
  }
})();

document.addEventListener('DOMContentLoaded', function(){
  const saveBtn = document.getElementById('moduleCreateSaveBtn');
  const form = document.getElementById('moduleCreateForm');
  const modalEl = document.getElementById('moduleCreateModal');
  const parentAccessModalEl = document.getElementById('aksesModal');
  const iconInput = document.getElementById('mc_icon');
  const iconPreview = document.getElementById('mc_iconPreview');
  const iconGrid = document.getElementById('mc_iconGrid');
  const actionInput = form ? form.querySelector('input[name="action"]') : null;
  const moduleIdInput = document.getElementById('mc_moduleID');
  const modalTitleText = document.getElementById('moduleCreateTitle')?.querySelector('span');
  const panelTitleText = document.getElementById('moduleCreatePanelTitleText');
  const orderInput = document.getElementById('mc_order');
  const nameMsInput = document.getElementById('mc_modulNameMs');
  const nameEnInput = document.getElementById('mc_modulNameEn');
  const defaultOrderValue = <?= json_encode((string)$nextModuleOrder) ?>;
  const defaultIconValue = <?= json_encode((string)$defaultModuleIcon) ?>;
  let restoreParentAccessModal = false;

  const syncSidebarAfterModuleChange = async () => {
    if (window.ModuleAccess && typeof window.ModuleAccess.syncSidebarAfterNavigationChange === 'function') {
      return window.ModuleAccess.syncSidebarAfterNavigationChange();
    }
    if (window.AccessUiSync && typeof window.AccessUiSync.syncNavigationSilently === 'function') {
      return window.AccessUiSync.syncNavigationSilently({ redirectOnDenied: false }).catch(console.warn);
    }
    if (window.AccessUiSync && typeof window.AccessUiSync.syncSidebarSilently === 'function') {
      return window.AccessUiSync.syncSidebarSilently({ redirectOnDenied: false }).catch(console.warn);
    }
    if (window.SidebarSync && typeof window.SidebarSync.refreshCurrentSidebar === 'function') {
      return window.SidebarSync.refreshCurrentSidebar().catch(console.warn);
    }
    if (window.MenuRefresh && typeof window.MenuRefresh.refreshMainMenu === 'function') {
      return window.MenuRefresh.refreshMainMenu().catch(console.warn);
    }
    return Promise.resolve(false);
  };

  const syncModuleIconPreview = (iconClass) => {
    if (!iconPreview) return;
    const safeIcon = iconClass || 'ri-folder-fill';
    iconPreview.innerHTML = '<i class="' + safeIcon.replace(/"/g, '&quot;') + '"></i>';
  };

  const syncModuleIconSelection = () => {
    if (!iconInput) return;
    const currentValue = (iconInput.value || '').trim();
    if (iconGrid) {
      iconGrid.querySelectorAll('.module-icon-option').forEach(btn => {
        btn.classList.toggle('is-active', btn.getAttribute('data-icon') === currentValue);
      });
    }
    syncModuleIconPreview(currentValue);
  };

  const setModuleFormMode = (mode, data = {}) => {
    if (!form) return;
    GroupUtils.setButtonBusy(saveBtn, false);
    const isEdit = mode === 'edit';
    form.dataset.mode = isEdit ? 'edit' : 'create';
    if (actionInput) actionInput.value = isEdit ? 'update_module' : 'add_module';
    if (moduleIdInput) moduleIdInput.value = isEdit ? String(data.moduleID || '') : '';
    if (modalTitleText) modalTitleText.textContent = isEdit ? <?= json_encode((string)__('modul_kemaskini_title')) ?> : <?= json_encode((string)__('modul_tambah_title')) ?>;
    if (panelTitleText) panelTitleText.textContent = isEdit ? <?= json_encode((string)__('modul_kemaskini_title')) ?> : <?= json_encode((string)__('modul_tambah_title')) ?>;
    const currentSaveBtnText = document.getElementById('moduleCreateSaveBtnText');
    if (currentSaveBtnText) currentSaveBtnText.textContent = isEdit ? <?= json_encode((string)__('btn_update')) ?> : <?= json_encode((string)__('modul_simpan')) ?>;
    if (nameMsInput) nameMsInput.value = isEdit ? (data.modulNameMs || '') : '';
    if (nameEnInput) nameEnInput.value = isEdit ? (data.modulNameEn || '') : '';
    if (orderInput) orderInput.value = isEdit ? String(data.order || '') : defaultOrderValue;
    if (iconInput) iconInput.value = isEdit ? (data.icon || defaultIconValue) : defaultIconValue;
    syncModuleIconSelection();
  };

  window.openModuleFormModal = function(data = {}) {
    GroupUtils.setButtonBusy(saveBtn, false);
    setModuleFormMode(data.mode || 'create', data);
    if (modalEl && window.bootstrap && bootstrap.Modal) {
      modalEl.classList.toggle('modal-add-accent', (data.mode || 'create') === 'create');
      modalEl.classList.toggle('modal-child-accent', (data.mode || 'create') !== 'create');
      const parentModal = parentAccessModalEl && bootstrap.Modal ? bootstrap.Modal.getOrCreateInstance(parentAccessModalEl) : null;
      restoreParentAccessModal = !!(parentModal && parentAccessModalEl.classList.contains('show'));
      if (restoreParentAccessModal) parentModal.hide();
      bootstrap.Modal.getOrCreateInstance(modalEl).show();
    }
  };

  if (modalEl) {
    modalEl.addEventListener('show.bs.modal', () => {
      GroupUtils.setButtonBusy(saveBtn, false);
    });
    modalEl.addEventListener('hidden.bs.modal', () => {
      GroupUtils.setButtonBusy(saveBtn, false);
    });
  }

  if (iconInput) {
    iconInput.addEventListener('input', syncModuleIconSelection);
    syncModuleIconSelection();
  }

  if (iconGrid && iconInput) {
    iconGrid.addEventListener('click', function(e) {
      const btn = e.target.closest('.module-icon-option');
      if (!btn) return;
      const iconClass = btn.getAttribute('data-icon') || 'ri-folder-fill';
      iconInput.value = iconClass;
      syncModuleIconSelection();
      iconInput.focus();
      iconInput.setSelectionRange(iconInput.value.length, iconInput.value.length);
    });
  }

  if (saveBtn && form) {
    form.addEventListener('submit', function(e) {
      e.preventDefault();
      if (saveBtn.disabled) return;
      saveBtn.click();
    });

    saveBtn.addEventListener('click', async function(e){
      e.preventDefault();
      const isEditMode = (form.dataset.mode || 'create') === 'edit';

      const payload = {
        modulNameMs: nameMsInput ? nameMsInput.value : '',
        modulNameEn: nameEnInput ? nameEnInput.value : '',
        icon: iconInput ? iconInput.value : '',
      };
      if (isEditMode) {
        payload.moduleID = moduleIdInput ? moduleIdInput.value : '';
      }

      GroupUtils.setButtonBusy(saveBtn, true, T.saving || 'Saving...');

      try {
        const endpoint = isEditMode ? 'module-update.php' : 'module-create.php';
        const resp = await fetch(GroupUtils.apiUrl(endpoint), {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'X-CSRF-Token': GroupUtils.getCSRF()
          },
          body: JSON.stringify(payload)
        }).then(async (response) => {
          const data = await response.json();
          if (!response.ok) {
            throw new Error(data?.message || <?= json_encode((string)__('userGroup_error_unknown')) ?>);
          }
          return data;
        });

        if (modalEl && window.bootstrap && bootstrap.Modal) {
          bootstrap.Modal.getOrCreateInstance(modalEl).hide();
        }
        const successAlert = GroupUtils.fireAlert({
          icon: 'success',
          title: <?= json_encode((string)__('config_js_berjaya')) ?>,
          text: resp.message || (isEditMode
            ? <?= json_encode((string)__('modul_kemaskini_msg')) ?>
            : <?= json_encode((string)__('modul_berjaya_msg')) ?>),
          confirmButtonText: <?= json_encode((string)__('config_js_btn_ok')) ?>
        });
        if (window.ModuleAccess && typeof ModuleAccess.reloadCurrentAccess === 'function') {
          await ModuleAccess.reloadCurrentAccess();
        }
        if (window.MenuAccess && typeof MenuAccess.refreshVisibleGroupTableRows === 'function') {
          await MenuAccess.refreshVisibleGroupTableRows();
        }
        syncSidebarAfterModuleChange();
        GroupUtils.setButtonBusy(saveBtn, false);
        setModuleFormMode('create');
        await successAlert;
      } catch (err) {
        GroupUtils.fireAlert({
          icon: 'error',
          title: <?= json_encode((string)__('modul_ralat_title')) ?>,
          text: err.message || <?= json_encode((string)__('userGroup_error_unknown')) ?>,
          confirmButtonText: <?= json_encode((string)__('config_js_btn_ok')) ?>
        });
      } finally {
        GroupUtils.setButtonBusy(saveBtn, false);
      }
    });
  }

  <?php if (!empty($moduleSwal) && is_array($moduleSwal)): ?>
  if (window.Swal && typeof Swal.fire === 'function') {
    (window.GroupSwal ? GroupSwal.fire({
      icon: <?= json_encode((string)($moduleSwal['icon'] ?? 'info')) ?>,
      title: <?= json_encode((string)($moduleSwal['title'] ?? '')) ?>,
      text: <?= json_encode((string)($moduleSwal['text'] ?? '')) ?>,
      confirmButtonText: <?= json_encode((string)__('config_js_btn_ok')) ?>
    }) : Swal.fire({
      icon: <?= json_encode((string)($moduleSwal['icon'] ?? 'info')) ?>,
      title: <?= json_encode((string)($moduleSwal['title'] ?? '')) ?>,
      text: <?= json_encode((string)($moduleSwal['text'] ?? '')) ?>,
      confirmButtonText: <?= json_encode((string)__('config_js_btn_ok')) ?>
    }));
  }
  <?php endif; ?>

  <?php if ($moduleFormOpen): ?>
  if (modalEl && window.bootstrap && bootstrap.Modal) {
    const formEl = document.getElementById('moduleCreateForm');
    if (formEl) formEl.dataset.keepValues = '1';
    bootstrap.Modal.getOrCreateInstance(modalEl).show();
  }
  <?php endif; ?>

  if (modalEl) {
    modalEl.addEventListener('hidden.bs.modal', function() {
      if ((form?.dataset.mode || 'create') === 'edit') {
        setModuleFormMode('create');
      }
      if (restoreParentAccessModal && parentAccessModalEl && window.bootstrap && bootstrap.Modal) {
        restoreParentAccessModal = false;
        bootstrap.Modal.getOrCreateInstance(parentAccessModalEl).show();
      } else {
        restoreParentAccessModal = false;
      }
    });
  }
});
</script>



</body>
</html>
