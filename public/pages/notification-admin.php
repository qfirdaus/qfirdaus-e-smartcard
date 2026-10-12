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
require_once __DIR__ . '/../ajax/_helpers.php';
require_once __DIR__ . '/../classes/Database.php';
require_once __DIR__ . '/../classes/NotificationAdminService.php';

$pdo = Database::getInstance('mysql')->getConnection();
ensurePageGroupManagePermission($pdo, (string)(__('notification_admin_forbidden') ?: 'You do not have permission to manage notifications.'));

if (!function_exists('h')) {
    function h($value): string
    {
        return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
    }
}

function na(string $key, string $fallback): string
{
    $value = __($key);
    return ($value === $key || $value === null || $value === '') ? $fallback : (string)$value;
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(16));
}

$service = new NotificationAdminService($pdo);
$groups = $service->getGroups();
$templates = $service->getTemplates();
$recent = [];
$firstGroupId = (string)($groups[0]['f_groupID'] ?? '1');
$currentLoginId = (string)($_SESSION['f_loginID'] ?? $_SESSION['f_stafID'] ?? 'user01');
$lang = (string)($_SESSION['lang'] ?? 'ms');
$version = (string)($_ENV['APP_ASSET_VER'] ?? filemtime(__FILE__) ?: '1');
$PAGE_TITLE = na('notification_admin_page_title', 'Notification Admin');
$notificationDeveloperSamples = require __DIR__ . '/../includes/notification-developer-samples.php';
?>
<!doctype html>
<html lang="<?= h($lang) ?>" data-bs-theme="<?= h($_SESSION['theme.layout'] ?? 'light') ?>">
<head>
  <?php
    $NEED_DATERANGE = false;
    $NEED_VECTORMAP = false;
    $NEED_DATATABLES = true;
    $NEED_SELECT2 = false;
    include __DIR__ . '/../includes/head.php';
  ?>
  <meta name="csrf-token" content="<?= h((string)$_SESSION['csrf_token']) ?>">
  <meta name="referrer" content="same-origin">
  <link href="<?= h(base_url('assets/css/datatables-standard.css')) ?>?v=<?= h($version) ?>" rel="stylesheet">
  <link href="<?= h(base_url('assets/vendor/flatpickr/flatpickr.min.css')) ?>?v=<?= h($version) ?>" rel="stylesheet">
  <link href="<?= h(base_url('assets/css/pages/notification-admin.css')) ?>?v=<?= h($version) ?>" rel="stylesheet">
  <style>
    .notification-admin-filters { display:flex; flex-wrap:wrap; gap:.75rem; margin-top:1rem; }
    .notification-admin-filters label { display:flex; align-items:center; gap:.5rem; color:var(--bs-secondary-color); font-size:.78rem; font-weight:650; }
    .notification-admin-filters select { min-width:140px; }
    .notification-admin-shell { width: 100%; }
    .notification-admin-card { border: 1px solid rgba(15,23,42,.08); border-radius: 8px; box-shadow: 0 8px 24px rgba(15,23,42,.06); }
    .notification-admin-toolbar { gap: .5rem; }
    .notification-admin-preview { border: 1px dashed rgba(var(--bs-primary-rgb), .35); border-radius: 8px; background: rgba(var(--bs-primary-rgb), .04); }
    .notification-admin-preview__icon { width: 42px; height: 42px; }
    .notification-admin-help { font-size: .82rem; color: var(--bs-secondary-color); }
    .notification-admin-field-help {
      display: none;
    }
    .notification-field-info {
      color: var(--bs-secondary-color);
      cursor: help;
      font-size: .95rem;
      line-height: 1;
      vertical-align: -1px;
      opacity: .78;
    }
    .notification-field-info:hover,
    .notification-field-info:focus {
      color: var(--bs-primary);
      opacity: 1;
      outline: 0;
    }
    #notificationSetupModal .modal-body {
      background: #f8fafc;
      padding: 1.1rem 1.35rem;
    }
    #notificationSetupModal .modal-footer {
      background: #fff;
      border-top: 1px solid rgba(15, 23, 42, .08);
      padding: .95rem 1.35rem;
    }
    .notification-setup-main-tabs {
      background: #fff;
      border: 1px solid rgba(15, 23, 42, .08);
      border-radius: 8px;
      padding: .35rem;
    }
    .notification-setup-main-tabs .nav-link {
      border-radius: 7px !important;
      color: #475569;
      padding: .62rem .9rem;
    }
    .notification-setup-main-tabs .nav-link.active {
      background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
      color: #fff;
    }
    .notification-form-surface {
      background: #fff;
      border: 1px solid rgba(15, 23, 42, .08);
      border-radius: 8px;
      padding: 1rem;
    }
    .notification-setup-subtabs {
      gap: .45rem;
      border-bottom: 0 !important;
      margin-bottom: .85rem !important;
    }
    .notification-setup-subtabs .nav-link {
      border: 1px solid #dbe4f0 !important;
      background: #f8fafc;
      color: #475569;
      padding: .55rem .85rem;
    }
    .notification-setup-subtabs .nav-link:hover {
      background: #eef2f7;
      color: #334155;
    }
    .notification-setup-subtabs .nav-link.active {
      background: rgba(40, 167, 69, .1);
      border-color: rgba(40, 167, 69, .35) !important;
      color: #198754;
    }
    #notificationSetupModal .form-label {
      display: inline-flex;
      align-items: center;
      gap: .15rem;
      margin-bottom: .45rem;
      color: #334155;
      font-weight: 600;
      font-size: .875rem;
    }
    #notificationSetupModal .form-control,
    #notificationSetupModal .form-select {
      border: 1px solid #dbe4f0;
      border-radius: 8px;
      min-height: 38px;
      background-color: #fff;
      transition: border-color .15s ease-in-out, box-shadow .15s ease-in-out;
    }
    #notificationSetupModal .form-control:focus,
    #notificationSetupModal .form-select:focus {
      border-color: rgba(32, 201, 151, .65);
      box-shadow: 0 0 0 .2rem rgba(32, 201, 151, .14);
    }
    #notificationSetupModal textarea.form-control {
      min-height: 140px;
    }
    .notification-icon-preview {
      width: 38px;
      height: 38px;
      flex: 0 0 38px;
    }
    #notificationAdminRecentTable th,
    #notificationAdminRecentTable td,
    #notificationAdminRecentTable.table > :not(caption) > * > * {
      vertical-align: top !important;
    }
    #notificationAdminRecentTable { width: 100%; }
    #notificationAdminRecentTable thead th {
      font-weight: 700;
      font-size: .8rem;
      text-transform: uppercase;
      letter-spacing: .06em;
      padding: .9rem .85rem;
      border: 0;
      border-bottom: 1px solid rgba(148, 163, 184, .16);
      color: #334155;
      background: linear-gradient(180deg, rgba(248, 250, 252, .98) 0%, rgba(241, 245, 249, .95) 100%);
    }
    #notificationAdminRecentTable tbody td {
      padding: .9rem .85rem;
      border-color: rgba(226, 232, 240, .9);
    }
    #notificationAdminRecentTable tbody tr,
    #notificationAdminRecentTable tbody tr:nth-of-type(odd),
    #notificationAdminRecentTable tbody tr:nth-of-type(even) {
      background-color: transparent !important;
    }
    #notificationAdminRecentTable tbody tr:hover {
      background: rgba(241, 245, 249, .88) !important;
      box-shadow: inset 0 0 0 999px rgba(241, 245, 249, .3);
    }
    #notificationAdminRecentTable_wrapper .dataTables_filter { text-align: right; }
    #notificationAdminRecentTable_wrapper .dt-top-right {
      padding-top: .35rem;
    }
    #notificationAdminRecentTable_wrapper .dt-bottom-row,
    #notificationAdminRecentTable_wrapper .row.mt-3 {
      margin-top: .45rem !important;
      padding: 0 .25rem;
    }
    #notificationAdminRecentTable_wrapper .dataTables_info {
      margin: 0;
      padding: .15rem 0 0 .25rem;
    }
    #notificationAdminRecentTable_wrapper .dataTables_paginate {
      margin: 0;
    }
    #notificationAdminRecentTable_wrapper .dataTables_filter label,
    #notificationAdminRecentTable_wrapper .dataTables_length label {
      margin: 0 !important;
      display: inline-flex !important;
      align-items: center !important;
      gap: .5rem !important;
    }
    #notificationAdminRecentTable_wrapper .dataTables_filter input,
    #notificationAdminRecentTable_wrapper .dataTables_length select {
      min-height: 36px !important;
      border: 2px solid #e9ecef !important;
      border-radius: 8px !important;
      font-size: .875rem !important;
    }
    #notificationSetupModal .modal-header {
      background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
      color: #fff;
      border-bottom: 0;
      padding: 1rem 1.35rem;
    }
    #notificationSetupModal {
      z-index: 11020 !important;
    }
    #notificationSetupModal,
    #notificationSetupModal .modal-dialog,
    #notificationSetupModal .modal-content,
    #notificationSetupModal .modal-content::before,
    #notificationSetupModal .modal-content::after {
      box-shadow: none !important;
      outline: 0 !important;
      filter: none !important;
    }
    #notificationSetupModal.fade {
      transition: none !important;
    }
    #notificationSetupModal.fade .modal-dialog,
    #notificationSetupModal.show .modal-dialog {
      transition: none !important;
      transform: none !important;
    }
    #notificationSetupModal .modal-dialog {
      border: 0 !important;
      background: transparent !important;
      position: relative;
      z-index: 1;
    }
    #notificationSetupModal .modal-content {
      border: 0;
      border-radius: 8px;
      box-shadow: none !important;
      outline: 0 !important;
      filter: none !important;
      overflow: hidden;
    }
    #notificationSetupModal .modal-title { color: #fff; font-weight: 600; }
    #notificationSetupModal .btn-close { filter: invert(1); opacity: .9; }
    #notificationSetupModal .nav-pills .nav-link,
    #notificationSetupModal .nav-tabs .nav-link {
      border-radius: 8px;
      font-weight: 600;
    }
    #notificationSamplesModal {
      z-index: 11020 !important;
    }
    #notificationSamplesModal,
    #notificationSamplesModal .modal-dialog,
    #notificationSamplesModal .modal-content,
    #notificationSamplesModal .modal-content::before,
    #notificationSamplesModal .modal-content::after {
      box-shadow: none !important;
      outline: 0 !important;
      filter: none !important;
    }
    #notificationSamplesModal .modal-content {
      border: 0;
      border-radius: 8px;
      overflow: hidden;
    }
    #notificationSamplesModal .modal-header {
      background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
      color: #fff;
      border-bottom: 0;
      padding: 1rem 1.35rem;
    }
    #notificationSamplesModal .modal-title {
      color: #fff;
      font-weight: 600;
    }
    #notificationSamplesModal .btn-close {
      filter: invert(1);
      opacity: .9;
    }
    #notificationSamplesModal .modal-body {
      background: #f8fafc;
      padding: 1.1rem 1.35rem;
    }
    .notification-samples-layout {
      display: grid;
      grid-template-columns: 245px minmax(0, 1fr);
      gap: 1rem;
    }
    .notification-samples-tabs {
      background: #fff;
      border: 1px solid rgba(15, 23, 42, .08);
      border-radius: 8px;
      padding: .45rem;
    }
    .notification-samples-tabs .nav-link {
      display: flex;
      align-items: center;
      gap: .55rem;
      width: 100%;
      border-radius: 7px;
      color: #475569;
      font-weight: 600;
      text-align: left;
      padding: .72rem .78rem;
    }
    .notification-samples-tabs .nav-link.active {
      background: rgba(40, 167, 69, .1);
      color: #198754;
    }
    .notification-sample-panel {
      background: #fff;
      border: 1px solid rgba(15, 23, 42, .08);
      border-radius: 8px;
      overflow: hidden;
    }
    .notification-sample-header {
      display: flex;
      align-items: flex-start;
      justify-content: space-between;
      gap: 1rem;
      padding: .95rem 1rem;
      border-bottom: 1px solid rgba(148, 163, 184, .18);
      background: linear-gradient(180deg, rgba(248, 250, 252, .98), rgba(255, 255, 255, .98));
    }
    .notification-sample-header h6 {
      margin: 0 0 .18rem;
      color: #0f172a;
      font-size: .95rem;
      font-weight: 700;
    }
    .notification-sample-header p {
      margin: 0;
      color: #64748b;
      font-size: .78rem;
      line-height: 1.35;
    }
    .notification-code-block {
      margin: 0;
      max-height: 540px;
      overflow: auto;
      border: 0;
      border-radius: 0;
      background: #0f172a;
      color: #e2e8f0;
      font-size: .76rem;
      line-height: 1.55;
      padding: 1rem;
      tab-size: 4;
      white-space: pre;
    }
    .flatpickr-calendar {
      z-index: 11060 !important;
    }
    .tooltip {
      z-index: 11080 !important;
    }
    .swal2-container {
      z-index: 20000 !important;
    }
    .tooltip .tooltip-inner {
      max-width: 460px;
      padding: .45rem .65rem;
      text-align: left;
      line-height: 1.35;
      white-space: normal;
    }
    [data-bs-theme="dark"] #notificationAdminRecentTable thead th {
      background: linear-gradient(180deg, rgba(30, 41, 59, .96) 0%, rgba(15, 23, 42, .94) 100%);
      color: #dbe4f0;
      border-bottom-color: rgba(148, 163, 184, .18);
    }
    [data-bs-theme="dark"] #notificationAdminRecentTable tbody tr:hover {
      background: rgba(30, 41, 59, .76) !important;
      box-shadow: inset 0 0 0 999px rgba(30, 41, 59, .18);
    }
    [data-bs-theme="dark"] #notificationAdminRecentTable_wrapper .dataTables_filter input,
    [data-bs-theme="dark"] #notificationAdminRecentTable_wrapper .dataTables_length select {
      background: rgba(15,23,42,.96) !important;
      border-color: rgba(148,163,184,.24) !important;
      color: #e2e8f0 !important;
    }
    [data-bs-theme="dark"] #notificationSetupModal .modal-body,
    [data-bs-theme="dark"] .notification-setup-main-tabs,
    [data-bs-theme="dark"] .notification-form-surface {
      background: rgba(15, 23, 42, .96);
      border-color: rgba(148, 163, 184, .18);
    }
    [data-bs-theme="dark"] #notificationSetupModal .modal-footer {
      background: rgba(15, 23, 42, .98);
      border-top-color: rgba(148, 163, 184, .18);
    }
    [data-bs-theme="dark"] .notification-setup-main-tabs .nav-link,
    [data-bs-theme="dark"] .notification-setup-subtabs .nav-link {
      background: rgba(30, 41, 59, .72);
      border-color: rgba(148, 163, 184, .2) !important;
      color: #dbe4f0;
    }
    [data-bs-theme="dark"] #notificationSetupModal .form-label {
      color: #dbe4f0;
    }
    [data-bs-theme="dark"] #notificationSamplesModal .modal-body {
      background: #1e293b;
    }
    [data-bs-theme="dark"] .notification-samples-tabs,
    [data-bs-theme="dark"] .notification-sample-panel,
    [data-bs-theme="dark"] .notification-sample-header {
      background: #111827;
      border-color: rgba(148, 163, 184, .18);
    }
    [data-bs-theme="dark"] .notification-sample-header h6 {
      color: #f8fafc;
    }
    @media (max-width: 991.98px) {
      .notification-samples-layout {
        grid-template-columns: 1fr;
      }
      .notification-samples-tabs {
        display: flex;
        overflow-x: auto;
      }
      .notification-samples-tabs .nav-link {
        white-space: nowrap;
      }
    }
    /* Keep modal labels readable when theme/global modal layers override gradients. */
    #notificationSetupModal .modal-header,
    #notificationSamplesModal .modal-header {
      position: relative !important;
      z-index: 2 !important;
      display: flex !important;
      visibility: visible !important;
      min-height: 72px;
      background-color: rgb(var(--bs-primary-rgb, 98, 126, 220)) !important;
      background-image: linear-gradient(135deg, rgb(var(--bs-primary-rgb, 98, 126, 220)) 0%, #20a58a 100%) !important;
      color: #fff !important;
    }
    #notificationSetupModal .modal-header > *,
    #notificationSamplesModal .modal-header > * {
      position: relative !important;
      z-index: 3 !important;
      visibility: visible !important;
      opacity: 1 !important;
    }
    #notificationSetupModal .modal-header .modal-title,
    #notificationSetupModal .modal-header .small,
    #notificationSamplesModal .modal-header .modal-title,
    #notificationSamplesModal .modal-header .small {
      color: #fff !important;
    }
    #notificationSetupModal .notification-setup-main-tabs .nav-link {
      color: var(--bs-secondary-color) !important;
      background: transparent !important;
    }
    #notificationSetupModal .notification-setup-main-tabs {
      display: inline-flex !important;
      flex-wrap: nowrap !important;
      justify-content: flex-start !important;
      width: auto !important;
      max-width: 100%;
    }
    #notificationSetupModal .notification-setup-main-tabs .nav-item {
      flex: 0 0 auto !important;
      width: auto !important;
    }
    #notificationSetupModal .notification-setup-main-tabs .nav-link {
      display: inline-flex !important;
      align-items: center;
      width: auto !important;
      min-width: 0 !important;
      padding: .62rem .9rem !important;
    }
    #notificationSetupModal .notification-setup-main-tabs .nav-link.active {
      color: #fff !important;
      background-color: rgb(var(--bs-primary-rgb, 98, 126, 220)) !important;
      background-image: linear-gradient(135deg, rgb(var(--bs-primary-rgb, 98, 126, 220)), #20a58a) !important;
    }
  </style>
</head>
<body data-topbar-color="<?= h($_SESSION['theme.topbar'] ?? 'light') ?>"
      data-menu-color="<?= h($_SESSION['theme.menu'] ?? $_SESSION['theme.sidebar'] ?? 'dark') ?>"
      data-layout="vertical" data-sidebar-size="default" class="loading">
<div class="wrapper">
  <?php include __DIR__ . '/../includes/topbar.php'; ?>
  <?php include __DIR__ . '/../includes/sidebar.php'; ?>

  <div class="content-page">
    <div class="content">
      <div class="container-fluid">
        <div class="row mb-3">
          <div class="col-12">
            <div class="page-title-box d-flex justify-content-between align-items-center flex-wrap">
              <h4 class="page-title"><i class="ri-notification-badge-line me-1"></i><?= h(na('notification_admin_page_title', 'Notification Admin')) ?></h4>
              <div class="page-title-right">
                <ol class="breadcrumb m-0">
                  <li class="breadcrumb-item"><a href="<?= h(base_url('pages/dashboard.php')) ?>"><?= h(na('common_dashboard', 'Dashboard')) ?></a></li>
                  <li class="breadcrumb-item active"><?= h(na('notification_admin_page_title', 'Notification Admin')) ?></li>
                </ol>
              </div>
            </div>
          </div>
        </div>

        <div class="notification-admin-shell">
          <section class="notification-admin-hero">
            <div>
              <span class="notification-admin-eyebrow"><i class="ri-shield-keyhole-line"></i><?= h(na('notification_admin_hero_eyebrow', 'CONTROLLED COMMUNICATION')) ?></span>
              <h1><?= h(na('notification_admin_hero_title', 'Publish the right message to the right audience')) ?></h1>
              <p><?= h(na('notification_admin_hero_text', 'Create, target and review system notifications from one governed administration workspace.')) ?></p>
            </div>
            <div class="notification-admin-hero-note"><i class="ri-error-warning-line"></i><div><strong><?= h(na('notification_admin_hero_note_title', 'Publication affects real users')) ?></strong><span><?= h(na('notification_admin_hero_note_text', 'Review the audience and preview before confirming a publication.')) ?></span></div></div>
          </section>

          <div class="card notification-admin-card">
            <div class="card-body">
              <div class="d-flex flex-wrap justify-content-between align-items-start notification-admin-toolbar mb-3">
                <div>
                  <h5 class="card-title mb-1"><?= h(na('notification_admin_recent_title', 'Recent Notifications')) ?></h5>
                  <p class="text-muted mb-0"><?= h(na('notification_admin_recent_subtitle', 'Default view for published notifications and audience delivery records.')) ?></p>
                </div>
                <div class="d-flex flex-wrap gap-2">
                  <a href="<?= h(base_url('pages/notification-templates.php')) ?>" class="btn btn-outline-primary">
                    <i class="ri-file-list-3-line me-1"></i><?= h(na('notification_admin_add_template', 'Add Template')) ?>
                  </a>
                  <button type="button" class="btn btn-outline-success" id="notificationSamplesBtn" data-bs-toggle="modal" data-bs-target="#notificationSamplesModal">
                    <i class="ri-code-box-line me-1"></i><?= h(na('notification_admin_samples_button', 'Samples')) ?>
                  </button>
                  <button type="button" class="btn btn-primary" id="notificationSetupBtn">
                    <i class="ri-settings-3-line me-1"></i><?= h(na('notification_admin_setup_button', 'Setup Notification')) ?>
                  </button>
                </div>
              </div>

              <div class="notification-admin-filters" aria-label="<?= h(na('notification_admin_filter_label', 'Notification filters')) ?>">
                <label><span><?= h(na('notification_admin_type', 'Type')) ?></span><select class="form-select form-select-sm" id="notificationAdminTypeFilter"><option value=""><?= h(na('notification_filter_all', 'All')) ?></option><option value="announcement">announcement</option><option value="reminder">reminder</option><option value="event">event</option><option value="workflow">workflow</option></select></label>
                <label><span><?= h(na('notification_admin_priority', 'Priority')) ?></span><select class="form-select form-select-sm" id="notificationAdminPriorityFilter"><option value=""><?= h(na('notification_filter_all', 'All')) ?></option><option value="low">low</option><option value="normal">normal</option><option value="high">high</option><option value="urgent">urgent</option></select></label>
                <label><span><?= h(na('notification_admin_status', 'Status')) ?></span><select class="form-select form-select-sm" id="notificationAdminStatusFilter"><option value="all"><?= h(na('notification_filter_all', 'All')) ?></option><option value="active"><?= h(na('notification_admin_stat_active', 'Active')) ?></option><option value="inactive"><?= h(na('notification_admin_inactive', 'Inactive')) ?></option></select></label>
              </div>

              <div class="table-responsive">
                <table class="table table-striped notification-admin-table" id="notificationAdminRecentTable" data-list-url="<?= h(base_url('ajax/notification-admin-list.php')) ?>">
                  <thead>
                    <tr>
                      <th class="text-center" style="width:56px;">#</th>
                      <th><?= h(na('notification_admin_col_title', 'Title')) ?></th>
                      <th><?= h(na('notification_admin_col_type', 'Type')) ?></th>
                      <th><?= h(na('notification_admin_priority', 'Priority')) ?></th>
                      <th><?= h(na('notification_admin_col_audience', 'Audience')) ?></th>
                      <th><?= h(na('notification_admin_requires_action', 'Requires user action')) ?></th>
                      <th><?= h(na('notification_admin_col_date', 'Date')) ?></th>
                    </tr>
                  </thead>
                  <tbody id="notificationAdminRecentBody">
                    <?php foreach ($recent as $index => $row): ?>
                      <tr>
                        <td class="text-center"><?= $index + 1 ?></td>
                        <td>
                          <div class="fw-semibold"><?= h((string)$row['f_title_ms']) ?></div>
                          <small class="text-muted"><?= h((string)$row['f_eventCode']) ?></small>
                        </td>
                        <td><span class="badge bg-light text-dark"><?= h((string)$row['f_type']) ?></span></td>
                        <td><span class="badge bg-secondary-subtle text-secondary"><?= h((string)$row['f_priority']) ?></span></td>
                        <td>
                          <span class="fw-semibold"><?= h((string)($row['audience_summary'] ?? 'No audience')) ?></span>
                          <div class="text-muted small"><?= h(na('notification_admin_target_count', 'Target count')) ?>: <?= (int)$row['audience_count'] ?></div>
                        </td>
                        <td><?= ((int)$row['f_requiresAction'] === 1) ? '<span class="badge bg-warning-subtle text-warning">' . h(na('common_yes', 'Yes')) . '</span>' : '<span class="badge bg-light text-dark">' . h(na('common_no', 'No')) . '</span>' ?></td>
                        <td><small><?= h((string)$row['f_insertdt']) ?></small></td>
                      </tr>
                    <?php endforeach; ?>
                  </tbody>
                </table>
              </div>
            </div>
          </div>
        </div>

        <div class="modal fade" id="notificationSetupModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
            <div class="modal-content">
              <form id="notificationAdminForm" data-publish-url="<?= h(base_url('ajax/notification-admin-publish.php')) ?>">
                <div class="modal-header">
                  <div>
                    <h5 class="modal-title"><?= h(na('notification_admin_composer_title', 'Publish Notification')) ?></h5>
                    <div class="small opacity-75"><?= h(na('notification_admin_composer_subtitle', 'Create in-app announcements, reminders, or workflow notifications for selected audiences.')) ?></div>
                  </div>
                  <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?= h(na('common_close', 'Close')) ?>"></button>
                </div>
                <div class="modal-body">
                  <div class="alert d-none mb-3" id="notificationAdminAlert"></div>
                  <ul class="nav nav-pills notification-setup-main-tabs mb-3" id="notificationSetupTabs" role="tablist">
                    <li class="nav-item" role="presentation">
                      <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#notificationSetupTab" type="button" role="tab">
                        <i class="ri-settings-3-line me-1"></i><?= h(na('notification_admin_tab_setup', 'Setup Notification')) ?>
                      </button>
                    </li>
                    <li class="nav-item" role="presentation">
                      <button class="nav-link" data-bs-toggle="tab" data-bs-target="#notificationPreviewTab" type="button" role="tab">
                        <i class="ri-eye-line me-1"></i><?= h(na('notification_admin_preview_title', 'Preview')) ?>
                      </button>
                    </li>
                  </ul>

                  <div class="tab-content">
                    <div class="tab-pane fade show active" id="notificationSetupTab" role="tabpanel">
                      <div class="notification-form-surface">
                      <ul class="nav nav-tabs notification-setup-subtabs" role="tablist">
                        <li class="nav-item" role="presentation">
                          <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#naBasicTab" type="button" role="tab"><?= h(na('notification_admin_tab_basic', 'Basic')) ?></button>
                        </li>
                        <li class="nav-item" role="presentation">
                          <button class="nav-link" data-bs-toggle="tab" data-bs-target="#naContentTab" type="button" role="tab"><?= h(na('notification_admin_tab_content', 'Content')) ?></button>
                        </li>
                        <li class="nav-item" role="presentation">
                          <button class="nav-link" data-bs-toggle="tab" data-bs-target="#naAudienceTab" type="button" role="tab"><?= h(na('notification_admin_tab_audience', 'Audience')) ?></button>
                        </li>
                        <li class="nav-item" role="presentation">
                          <button class="nav-link" data-bs-toggle="tab" data-bs-target="#naActionTab" type="button" role="tab"><?= h(na('notification_admin_tab_action', 'Action & Schedule')) ?></button>
                        </li>
                      </ul>

                      <div class="tab-content">
                        <div class="tab-pane fade show active" id="naBasicTab" role="tabpanel">
                          <div class="row g-3">
                            <div class="col-md-6">
                              <label class="form-label" for="na_event_code"><?= h(na('notification_admin_event_code', 'Event Code')) ?></label>
                              <input type="text" class="form-control" id="na_event_code" name="event_code" maxlength="100" placeholder="admin.announcement">
                              <span class="notification-admin-field-help"><?= h(na('notification_admin_help_event_code', 'Kod rujukan dalaman untuk jejak notifikasi. Jika kosong, sistem akan jana automatik.')) ?></span>
                            </div>
                            <div class="col-md-6">
                              <label class="form-label" for="na_template_code"><?= h(na('notification_admin_template', 'Template')) ?></label>
                              <select class="form-select" id="na_template_code" name="template_code">
                                <option value=""><?= h(na('notification_admin_no_template', 'No template')) ?></option>
                                <?php foreach ($templates as $template): ?>
                                  <option value="<?= h((string)$template['f_templateCode']) ?>"
                                    data-template='<?= h(json_encode($template, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '{}') ?>'>
                                    <?= h((string)$template['f_templateCode']) ?>
                                  </option>
                                <?php endforeach; ?>
                              </select>
                              <span class="notification-admin-field-help"><?= h(na('notification_admin_help_template', 'Pilih template jika mahu isi tajuk, kandungan, ikon, dan tetapan asas secara automatik.')) ?></span>
                            </div>
                            <div class="col-md-4">
                              <label class="form-label" for="na_type"><?= h(na('notification_admin_type', 'Type')) ?></label>
                              <select class="form-select" id="na_type" name="type">
                                <option value="announcement">announcement</option>
                                <option value="reminder">reminder</option>
                                <option value="event">event</option>
                                <option value="workflow">workflow</option>
                              </select>
                              <span class="notification-admin-field-help"><?= h(na('notification_admin_help_type', 'Pilih kategori fungsi notifikasi: hebahan, peringatan, event, atau tugasan workflow.')) ?></span>
                            </div>
                            <div class="col-md-4">
                              <label class="form-label" for="na_severity"><?= h(na('notification_admin_severity', 'Severity')) ?></label>
                              <select class="form-select" id="na_severity" name="severity">
                                <option value="info">info</option>
                                <option value="success">success</option>
                                <option value="warning">warning</option>
                                <option value="danger">danger</option>
                              </select>
                              <span class="notification-admin-field-help"><?= h(na('notification_admin_help_severity', 'Tentukan warna/urgency paparan kepada pengguna.')) ?></span>
                            </div>
                            <div class="col-md-4">
                              <label class="form-label" for="na_priority"><?= h(na('notification_admin_priority', 'Priority')) ?></label>
                              <select class="form-select" id="na_priority" name="priority">
                                <option value="normal">normal</option>
                                <option value="low">low</option>
                                <option value="high">high</option>
                                <option value="urgent">urgent</option>
                              </select>
                              <span class="notification-admin-field-help"><?= h(na('notification_admin_help_priority', 'Priority digunakan untuk susunan dan penekanan notifikasi.')) ?></span>
                            </div>
                            <div class="col-md-8">
                              <label class="form-label" for="na_dedupe_key"><?= h(na('notification_admin_dedupe_key', 'Dedupe Key')) ?></label>
                              <input type="text" class="form-control" id="na_dedupe_key" name="dedupe_key" maxlength="190" placeholder="admin.announcement.2026-05">
                              <span class="notification-admin-field-help"><?= h(na('notification_admin_help_dedupe_key', 'Guna kunci unik jika notifikasi yang sama tidak patut digandakan. Contoh: password.reminder.2026-05.')) ?></span>
                            </div>
                            <div class="col-md-4">
                              <label class="form-label" for="na_dedupe_behavior"><?= h(na('notification_admin_dedupe_behavior', 'Dedupe Behavior')) ?></label>
                              <select class="form-select" id="na_dedupe_behavior" name="dedupe_behavior">
                                <option value="update">update</option>
                                <option value="skip">skip</option>
                                <option value="republish">republish</option>
                              </select>
                              <span class="notification-admin-field-help"><?= h(na('notification_admin_help_dedupe_behavior', 'Update akan kemas kini notifikasi sedia ada, skip abaikan duplikasi, republish cipta semula.')) ?></span>
                            </div>
                          </div>
                        </div>

                        <div class="tab-pane fade" id="naContentTab" role="tabpanel">
                          <div class="row g-3">
                            <div class="col-md-6">
                              <label class="form-label" for="na_title_ms"><?= h(na('notification_admin_title_ms', 'Title MS')) ?> <span class="text-danger">*</span></label>
                              <input type="text" class="form-control" id="na_title_ms" name="title_ms" maxlength="255" required>
                              <span class="notification-admin-field-help"><?= h(na('notification_admin_help_title_ms', 'Tajuk utama yang akan dilihat oleh pengguna dalam bahasa Melayu. Field ini wajib diisi.')) ?></span>
                            </div>
                            <div class="col-md-6">
                              <label class="form-label" for="na_title_en"><?= h(na('notification_admin_title_en', 'Title EN')) ?></label>
                              <input type="text" class="form-control" id="na_title_en" name="title_en" maxlength="255">
                              <span class="notification-admin-field-help"><?= h(na('notification_admin_help_title_en', 'Tajuk versi English. Jika kosong, sistem masih boleh papar tajuk Melayu.')) ?></span>
                            </div>
                            <div class="col-md-6">
                              <label class="form-label" for="na_body_ms"><?= h(na('notification_admin_body_ms', 'Body MS')) ?></label>
                              <textarea class="form-control" id="na_body_ms" name="body_ms" rows="6" maxlength="10000"></textarea>
                              <span class="notification-admin-field-help"><?= h(na('notification_admin_help_body_ms', 'Isi mesej ringkas dan jelas. Elakkan arahan terlalu panjang supaya mudah dibaca dalam panel notifikasi.')) ?></span>
                            </div>
                            <div class="col-md-6">
                              <label class="form-label" for="na_body_en"><?= h(na('notification_admin_body_en', 'Body EN')) ?></label>
                              <textarea class="form-control" id="na_body_en" name="body_en" rows="6" maxlength="10000"></textarea>
                              <span class="notification-admin-field-help"><?= h(na('notification_admin_help_body_en', 'Isi mesej versi English jika sistem digunakan dalam mod English.')) ?></span>
                            </div>
                          </div>
                        </div>

                        <div class="tab-pane fade" id="naAudienceTab" role="tabpanel">
                          <div class="row g-3">
                            <div class="col-md-4">
                              <label class="form-label" for="na_audience_type"><?= h(na('notification_admin_audience_type', 'Audience')) ?></label>
                              <select class="form-select" id="na_audience_type" name="audience_type">
                                <option value="ALL">ALL</option>
                                <option value="CATEGORY_USER">CATEGORY_USER</option>
                                <option value="GROUP_ID">GROUP_ID</option>
                                <option value="LOGIN_ID">LOGIN_ID</option>
                                <option value="RESOLVED_LOGIN_ID">RESOLVED_LOGIN_ID</option>
                                <option value="ROLE_ID">ROLE_ID</option>
                                <option value="DEPARTMENT_ID">DEPARTMENT_ID</option>
                                <option value="PERMISSION">PERMISSION</option>
                              </select>
                              <span class="notification-admin-field-help"><?= h(na('notification_admin_help_audience_type', 'Pilih siapa yang akan terima notifikasi. Guna ALL untuk semua pengguna.')) ?></span>
                            </div>
                            <div class="col-md-8">
                              <label class="form-label" for="na_audience_value"><?= h(na('notification_admin_audience_value', 'Audience Value')) ?></label>
                              <textarea class="form-control" id="na_audience_value" name="audience_value" rows="3" maxlength="75500" placeholder="<?= h(na('notification_admin_audience_help', 'Use comma or new line for multiple values. Leave blank for ALL.')) ?>" data-sample-owned="1"></textarea>
                              <div class="notification-admin-field-help" id="naAudienceHelp"><?= h(na('notification_admin_audience_all_help', 'ALL does not require a value.')) ?></div>
                            </div>
                            <div class="col-md-6">
                              <label class="form-label" for="na_group_picker"><?= h(na('notification_admin_group_picker', 'Group Quick Pick')) ?></label>
                              <select class="form-select" id="na_group_picker">
                                <option value=""><?= h(na('notification_admin_select_group', 'Select group')) ?></option>
                                <?php foreach ($groups as $group): ?>
                                  <option value="<?= h((string)$group['f_groupID']) ?>" data-kod="<?= h((string)$group['f_groupKod']) ?>">
                                    <?= h((string)($group['f_groupName'] ?: $group['f_groupKod'])) ?>
                                  </option>
                                <?php endforeach; ?>
                              </select>
                              <span class="notification-admin-field-help"><?= h(na('notification_admin_help_group_picker', 'Shortcut untuk isi Audience Type sebagai GROUP_ID dan masukkan ID kumpulan yang dipilih.')) ?></span>
                            </div>
                            <div class="col-md-6">
                              <label class="form-label" for="na_category_picker"><?= h(na('notification_admin_category_picker', 'Category Quick Pick')) ?></label>
                              <select class="form-select" id="na_category_picker">
                                <option value=""><?= h(na('notification_admin_select_category', 'Select category')) ?></option>
                                <option value="STAF">STAF</option>
                                <option value="PELAJAR">PELAJAR</option>
                                <option value="UMUM">UMUM</option>
                              </select>
                              <span class="notification-admin-field-help"><?= h(na('notification_admin_help_category_picker', 'Shortcut untuk hantar kepada kategori pengguna seperti STAF, PELAJAR, atau UMUM.')) ?></span>
                            </div>
                          </div>
                        </div>

                        <div class="tab-pane fade" id="naActionTab" role="tabpanel">
                          <div class="row g-3">
                            <div class="col-md-6">
                              <label class="form-label" for="na_action_url"><?= h(na('notification_admin_action_url', 'Action URL')) ?></label>
                              <input type="text" class="form-control" id="na_action_url" name="action_url" maxlength="500" placeholder="pages/notifications.php">
                              <span class="notification-admin-field-help"><?= h(na('notification_admin_help_action_url', 'Halaman yang akan dibuka bila pengguna klik tindakan. Guna path dalaman sahaja, contoh pages/profile.php.')) ?></span>
                            </div>
                            <div class="col-md-3">
                              <label class="form-label" for="na_action_label_ms"><?= h(na('notification_admin_action_label_ms', 'Action Label MS')) ?></label>
                              <input type="text" class="form-control" id="na_action_label_ms" name="action_label_ms">
                              <span class="notification-admin-field-help"><?= h(na('notification_admin_help_action_label_ms', 'Teks butang tindakan dalam bahasa Melayu, contoh Semak Sekarang.')) ?></span>
                            </div>
                            <div class="col-md-3">
                              <label class="form-label" for="na_action_label_en"><?= h(na('notification_admin_action_label_en', 'Action Label EN')) ?></label>
                              <input type="text" class="form-control" id="na_action_label_en" name="action_label_en">
                              <span class="notification-admin-field-help"><?= h(na('notification_admin_help_action_label_en', 'Teks butang tindakan dalam English, contoh Review Now.')) ?></span>
                            </div>
                            <div class="col-md-4">
                              <label class="form-label" for="na_due_at"><?= h(na('notification_admin_due_at', 'Due At')) ?></label>
                              <input type="text" class="form-control notification-datetime-picker" id="na_due_at" name="due_at" placeholder="YYYY-MM-DD HH:mm">
                              <span class="notification-admin-field-help"><?= h(na('notification_admin_help_due_at', 'Tarikh dan masa akhir untuk tindakan pengguna. Biarkan kosong jika tiada tarikh akhir.')) ?></span>
                            </div>
                            <div class="col-md-4">
                              <label class="form-label" for="na_expires_at"><?= h(na('notification_admin_expires_at', 'Expires At')) ?></label>
                              <input type="text" class="form-control notification-datetime-picker" id="na_expires_at" name="expires_at" placeholder="YYYY-MM-DD HH:mm">
                              <span class="notification-admin-field-help"><?= h(na('notification_admin_help_expires_at', 'Tarikh dan masa notifikasi berhenti dipaparkan. Biarkan kosong jika notifikasi tidak perlu luput.')) ?></span>
                            </div>
                            <div class="col-md-4">
                              <label class="form-label" for="na_icon"><?= h(na('notification_admin_icon', 'Icon')) ?></label>
                              <div class="d-flex gap-2 align-items-start">
                                <span class="notification-icon-preview rounded bg-primary-subtle text-primary d-inline-flex align-items-center justify-content-center">
                                  <i class="ri-notification-3-line fs-18" id="naIconPickerPreview"></i>
                                </span>
                                <select class="form-select" id="na_icon" name="icon">
                                  <option value="ri-notification-3-line">Notifikasi umum</option>
                                  <option value="ri-megaphone-line">Hebahan</option>
                                  <option value="ri-alarm-warning-line">Amaran penting</option>
                                  <option value="ri-time-line">Peringatan / tarikh akhir</option>
                                  <option value="ri-task-line">Tugasan workflow</option>
                                  <option value="ri-checkbox-circle-line">Berjaya / diluluskan</option>
                                  <option value="ri-error-warning-line">Isu / ditolak</option>
                                  <option value="ri-shield-check-line">Keselamatan</option>
                                  <option value="ri-user-settings-line">Akaun pengguna</option>
                                  <option value="ri-file-list-3-line">Dokumen / borang</option>
                                </select>
                              </div>
                              <span class="notification-admin-field-help"><?= h(na('notification_admin_help_icon', 'Pilih ikon mengikut jenis mesej. Ikon ini akan dipaparkan di topbar dan halaman notifikasi.')) ?></span>
                            </div>
                            <div class="col-12">
                              <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" role="switch" id="na_requires_action" name="requires_action" value="1">
                                <label class="form-check-label" for="na_requires_action"><?= h(na('notification_admin_requires_action', 'Requires user action')) ?></label>
                              </div>
                              <span class="notification-admin-field-help"><?= h(na('notification_admin_help_requires_action', 'Aktifkan jika notifikasi ini adalah tugasan yang perlu diselesaikan atau disahkan oleh pengguna.')) ?></span>
                            </div>
                          </div>
                        </div>
                      </div>
                      </div>
                    </div>

                    <div class="tab-pane fade" id="notificationPreviewTab" role="tabpanel">
                      <div class="notification-admin-preview p-3">
                        <div class="d-flex gap-3">
                          <span class="notification-admin-preview__icon rounded-circle bg-primary-subtle text-primary d-inline-flex align-items-center justify-content-center">
                            <i class="ri-notification-3-line fs-20" id="naPreviewIcon"></i>
                          </span>
                          <div class="flex-grow-1">
                            <div class="fw-semibold" id="naPreviewTitle"><?= h(na('notification_admin_preview_empty_title', 'Notification title')) ?></div>
                            <div class="text-muted small mt-1" id="naPreviewBody"><?= h(na('notification_admin_preview_empty_body', 'Notification body preview will appear here.')) ?></div>
                            <div class="mt-2">
                              <span class="badge bg-primary-subtle text-primary" id="naPreviewType">announcement</span>
                              <span class="badge bg-info-subtle text-info" id="naPreviewSeverity">info</span>
                              <span class="badge bg-secondary-subtle text-secondary" id="naPreviewAudience">ALL</span>
                            </div>
                          </div>
                        </div>
                      </div>
                    </div>
                  </div>
                </div>
                <div class="modal-footer">
                  <button type="reset" class="btn btn-light"><?= h(na('notification_admin_reset', 'Reset')) ?></button>
                  <button type="submit" class="btn btn-primary" id="notificationAdminSubmit">
                    <i class="ri-send-plane-line me-1"></i><?= h(na('notification_admin_publish', 'Publish')) ?>
                  </button>
                </div>
              </form>
            </div>
          </div>
        </div>

        <div class="modal fade" id="notificationSamplesModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
            <div class="modal-content">
              <div class="modal-header">
                <div>
                  <h5 class="modal-title"><?= h(na('notification_admin_samples_title', 'Notification Developer Samples')) ?></h5>
                  <div class="small opacity-75"><?= h(na('notification_admin_samples_subtitle', 'Copy standard workflow notification snippets for module integration.')) ?></div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?= h(na('common_close', 'Close')) ?>"></button>
              </div>
              <div class="modal-body">
                <div class="notification-samples-layout">
                  <div class="nav flex-column nav-pills notification-samples-tabs" role="tablist" aria-orientation="vertical">
                    <?php
                      $notificationSampleIcons = [
                          'submit' => 'ri-send-plane-line',
                          'next-step' => 'ri-arrow-right-circle-line',
                          'approved' => 'ri-checkbox-circle-line',
                          'rejected' => 'ri-close-circle-line',
                          'parallel' => 'ri-team-line',
                          'wrapper' => 'ri-file-code-line',
                      ];
                      $sampleIndex = 0;
                    ?>
                    <?php foreach ($notificationDeveloperSamples as $sampleKey => $sample): ?>
                      <?php $samplePaneId = 'sample' . str_replace(' ', '', ucwords(str_replace('-', ' ', (string)$sampleKey))); ?>
                      <button class="nav-link <?= $sampleIndex === 0 ? 'active' : '' ?>" data-bs-toggle="pill" data-bs-target="#<?= h($samplePaneId) ?>" type="button" role="tab">
                        <i class="<?= h($notificationSampleIcons[$sampleKey] ?? 'ri-file-code-line') ?>"></i><?= h((string)($sample['tab'] ?? $sample['title'] ?? na('notification_admin_sample', 'Sample'))) ?>
                      </button>
                      <?php $sampleIndex++; ?>
                    <?php endforeach; ?>
                  </div>

                  <div class="tab-content">
                    <?php $sampleIndex = 0; ?>
                    <?php foreach ($notificationDeveloperSamples as $sampleKey => $sample): ?>
                      <?php $samplePaneId = 'sample' . str_replace(' ', '', ucwords(str_replace('-', ' ', (string)$sampleKey))); ?>
                      <?php $codeId = (string)($sample['code_id'] ?? ('codeSample' . $samplePaneId)); ?>
                      <div class="tab-pane fade <?= $sampleIndex === 0 ? 'show active' : '' ?>" id="<?= h($samplePaneId) ?>" role="tabpanel">
                        <div class="notification-sample-panel">
                          <div class="notification-sample-header">
                            <div>
                              <h6><?= h((string)($sample['title'] ?? na('notification_admin_sample', 'Notification Sample'))) ?></h6>
                              <?php if (!empty($sample['description'])): ?>
                                <p><?= h((string)$sample['description']) ?></p>
                              <?php endif; ?>
                            </div>
                            <button type="button" class="btn btn-sm btn-outline-success notification-copy-sample" data-copy-target="<?= h($codeId) ?>">
                              <i class="ri-file-copy-line me-1"></i><?= h(na('notification_admin_copy', 'Copy')) ?>
                            </button>
                          </div>
<pre class="notification-code-block"><code id="<?= h($codeId) ?>"><?= h((string)($sample['code'] ?? '')) ?></code></pre>
                        </div>
                      </div>
                      <?php $sampleIndex++; ?>
                    <?php endforeach; ?>
                    <?php if (false): ?>
                    <div class="tab-pane fade show active" id="sampleSubmit" role="tabpanel">
                      <div class="notification-sample-panel">
                        <div class="notification-sample-header">
                          <div>
                            <h6>Submit Request</h6>
                            <p>Guna selepas rekod permohonan berjaya disimpan dan perlu dihantar kepada pegawai pertama.</p>
                          </div>
                          <button type="button" class="btn btn-sm btn-outline-success notification-copy-sample" data-copy-target="codeSampleSubmit">
                            <i class="ri-file-copy-line me-1"></i>Copy
                          </button>
                        </div>
<pre class="notification-code-block"><code id="codeSampleSubmit">&lt;?php
require_once __DIR__ . '/../classes/NotificationWorkflowService.php';

function notifyPermohonanSubmitted(
    int $permohonanId,
    string $noRujukan,
    array $officerLoginIds
): void {
    NotificationWorkflowService::default()-&gt;publishTask([
        'event_code' =&gt; 'permohonan.submitted.pending_officer',
        'module_code' =&gt; 'PERMOHONAN',
        'source_type' =&gt; 'permohonan',
        'source_id' =&gt; (string)$permohonanId,
        'title_ms' =&gt; 'Permohonan Baru Menunggu Semakan',
        'body_ms' =&gt; 'Permohonan ' . $noRujukan . ' memerlukan semakan pegawai.',
        'action_url' =&gt; 'pages/permohonan-review.php?id=' . urlencode((string)$permohonanId),
        'action_label_ms' =&gt; 'Semak Permohonan',
        'due_at' =&gt; date('Y-m-d H:i:s', strtotime('+3 days')),
        'dedupe_key' =&gt; 'permohonan:' . $permohonanId . ':officer_review',
        'audience' =&gt; [
            'resolved_login_ids' =&gt; $officerLoginIds,
        ],
    ], [
        'dedupe' =&gt; 'update',
    ]);
}</code></pre>
                      </div>
                    </div>

                    <div class="tab-pane fade" id="sampleNextStep" role="tabpanel">
                      <div class="notification-sample-panel">
                        <div class="notification-sample-header">
                          <div>
                            <h6>Move To Next Approval</h6>
                            <p>Tutup task lama dahulu, kemudian publish task baru kepada role/pegawai seterusnya.</p>
                          </div>
                          <button type="button" class="btn btn-sm btn-outline-success notification-copy-sample" data-copy-target="codeSampleNextStep">
                            <i class="ri-file-copy-line me-1"></i>Copy
                          </button>
                        </div>
<pre class="notification-code-block"><code id="codeSampleNextStep">&lt;?php
require_once __DIR__ . '/../classes/NotificationWorkflowService.php';

function notifyPermohonanMoveToHod(
    int $permohonanId,
    string $noRujukan,
    array $hodLoginIds
): void {
    $notification = NotificationWorkflowService::default();

    $notification-&gt;completeSourceStep(
        'permohonan',
        (string)$permohonanId,
        'permohonan.submitted.pending_officer'
    );

    $notification-&gt;publishTask([
        'event_code' =&gt; 'permohonan.reviewed.pending_hod',
        'module_code' =&gt; 'PERMOHONAN',
        'source_type' =&gt; 'permohonan',
        'source_id' =&gt; (string)$permohonanId,
        'title_ms' =&gt; 'Permohonan Menunggu Pengesahan Ketua Jabatan',
        'body_ms' =&gt; 'Permohonan ' . $noRujukan . ' memerlukan pengesahan Ketua Jabatan.',
        'action_url' =&gt; 'pages/permohonan-hod.php?id=' . urlencode((string)$permohonanId),
        'action_label_ms' =&gt; 'Sahkan Permohonan',
        'due_at' =&gt; date('Y-m-d H:i:s', strtotime('+3 days')),
        'dedupe_key' =&gt; 'permohonan:' . $permohonanId . ':hod_approval',
        'audience' =&gt; [
            'resolved_login_ids' =&gt; $hodLoginIds,
        ],
    ], [
        'dedupe' =&gt; 'update',
    ]);
}</code></pre>
                      </div>
                    </div>

                    <div class="tab-pane fade" id="sampleApproved" role="tabpanel">
                      <div class="notification-sample-panel">
                        <div class="notification-sample-header">
                          <div>
                            <h6>Final Approved</h6>
                            <p>Tutup task approval terakhir dan hantar info notification kepada pemohon.</p>
                          </div>
                          <button type="button" class="btn btn-sm btn-outline-success notification-copy-sample" data-copy-target="codeSampleApproved">
                            <i class="ri-file-copy-line me-1"></i>Copy
                          </button>
                        </div>
<pre class="notification-code-block"><code id="codeSampleApproved">&lt;?php
require_once __DIR__ . '/../classes/NotificationWorkflowService.php';

function notifyPermohonanApproved(
    int $permohonanId,
    string $noRujukan,
    string $pemohonLoginId
): void {
    $notification = NotificationWorkflowService::default();

    $notification-&gt;completeSourceStep(
        'permohonan',
        (string)$permohonanId,
        'permohonan.reviewed.pending_hod'
    );

    $notification-&gt;publishInfo([
        'event_code' =&gt; 'permohonan.approved.final',
        'module_code' =&gt; 'PERMOHONAN',
        'source_type' =&gt; 'permohonan',
        'source_id' =&gt; (string)$permohonanId,
        'title_ms' =&gt; 'Permohonan Diluluskan',
        'body_ms' =&gt; 'Permohonan ' . $noRujukan . ' telah diluluskan.',
        'action_url' =&gt; 'pages/permohonan-view.php?id=' . urlencode((string)$permohonanId),
        'action_label_ms' =&gt; 'Lihat Permohonan',
        'dedupe_key' =&gt; 'permohonan:' . $permohonanId . ':approved',
        'audience' =&gt; [
            'resolved_login_ids' =&gt; [$pemohonLoginId],
        ],
    ], [
        'dedupe' =&gt; 'skip',
    ]);
}</code></pre>
                      </div>
                    </div>

                    <div class="tab-pane fade" id="sampleRejected" role="tabpanel">
                      <div class="notification-sample-panel">
                        <div class="notification-sample-header">
                          <div>
                            <h6>Rejected / Cancelled</h6>
                            <p>Cancel semua task pending untuk source yang sama dan maklumkan keputusan kepada pemohon.</p>
                          </div>
                          <button type="button" class="btn btn-sm btn-outline-success notification-copy-sample" data-copy-target="codeSampleRejected">
                            <i class="ri-file-copy-line me-1"></i>Copy
                          </button>
                        </div>
<pre class="notification-code-block"><code id="codeSampleRejected">&lt;?php
require_once __DIR__ . '/../classes/NotificationWorkflowService.php';

function notifyPermohonanRejected(
    int $permohonanId,
    string $noRujukan,
    string $pemohonLoginId
): void {
    $notification = NotificationWorkflowService::default();

    $notification-&gt;cancelSource('permohonan', (string)$permohonanId);

    $notification-&gt;publishInfo([
        'event_code' =&gt; 'permohonan.rejected.final',
        'module_code' =&gt; 'PERMOHONAN',
        'source_type' =&gt; 'permohonan',
        'source_id' =&gt; (string)$permohonanId,
        'severity' =&gt; 'danger',
        'priority' =&gt; 'normal',
        'title_ms' =&gt; 'Permohonan Tidak Diluluskan',
        'body_ms' =&gt; 'Permohonan ' . $noRujukan . ' tidak diluluskan.',
        'action_url' =&gt; 'pages/permohonan-view.php?id=' . urlencode((string)$permohonanId),
        'action_label_ms' =&gt; 'Lihat Permohonan',
        'dedupe_key' =&gt; 'permohonan:' . $permohonanId . ':rejected',
        'audience' =&gt; [
            'resolved_login_ids' =&gt; [$pemohonLoginId],
        ],
    ], [
        'dedupe' =&gt; 'skip',
    ]);
}</code></pre>
                      </div>
                    </div>

                    <div class="tab-pane fade" id="sampleParallel" role="tabpanel">
                      <div class="notification-sample-panel">
                        <div class="notification-sample-header">
                          <div>
                            <h6>Parallel Approval</h6>
                            <p>Guna bila beberapa approver perlu terima task serentak. Business rule tetap ditentukan module.</p>
                          </div>
                          <button type="button" class="btn btn-sm btn-outline-success notification-copy-sample" data-copy-target="codeSampleParallel">
                            <i class="ri-file-copy-line me-1"></i>Copy
                          </button>
                        </div>
<pre class="notification-code-block"><code id="codeSampleParallel">&lt;?php
require_once __DIR__ . '/../classes/NotificationWorkflowService.php';

NotificationWorkflowService::default()-&gt;publishTask([
    'event_code' =&gt; 'permohonan.pending.parallel_review',
    'module_code' =&gt; 'PERMOHONAN',
    'source_type' =&gt; 'permohonan',
    'source_id' =&gt; (string)$permohonanId,
    'title_ms' =&gt; 'Permohonan Memerlukan Semakan Bersama',
    'body_ms' =&gt; 'Permohonan ' . $noRujukan . ' memerlukan semakan beberapa pegawai.',
    'action_url' =&gt; 'pages/permohonan-review.php?id=' . urlencode((string)$permohonanId),
    'action_label_ms' =&gt; 'Semak',
    'dedupe_key' =&gt; 'permohonan:' . $permohonanId . ':parallel_review',
    'audience' =&gt; [
        'resolved_login_ids' =&gt; $approverLoginIds,
    ],
], [
    'dedupe' =&gt; 'update',
]);

// Bila business condition selesai, tutup task ini.
NotificationWorkflowService::default()-&gt;completeSourceStep(
    'permohonan',
    (string)$permohonanId,
    'permohonan.pending.parallel_review'
);</code></pre>
                      </div>
                    </div>

                    <div class="tab-pane fade" id="sampleWrapper" role="tabpanel">
                      <div class="notification-sample-panel">
                        <div class="notification-sample-header">
                          <div>
                            <h6>Module Wrapper</h6>
                            <p>Recommended: controller panggil wrapper module, bukan bina payload notification panjang di banyak tempat.</p>
                          </div>
                          <button type="button" class="btn btn-sm btn-outline-success notification-copy-sample" data-copy-target="codeSampleWrapper">
                            <i class="ri-file-copy-line me-1"></i>Copy
                          </button>
                        </div>
<pre class="notification-code-block"><code id="codeSampleWrapper">&lt;?php
require_once __DIR__ . '/NotificationWorkflowService.php';

final class PermohonanNotification
{
    public static function submitted(int $id, string $refNo, array $officerLoginIds): void
    {
        NotificationWorkflowService::default()-&gt;publishTask([
            'event_code' =&gt; 'permohonan.submitted.pending_officer',
            'module_code' =&gt; 'PERMOHONAN',
            'source_type' =&gt; 'permohonan',
            'source_id' =&gt; (string)$id,
            'title_ms' =&gt; 'Permohonan Baru Menunggu Semakan',
            'body_ms' =&gt; 'Permohonan ' . $refNo . ' memerlukan semakan pegawai.',
            'action_url' =&gt; 'pages/permohonan-review.php?id=' . urlencode((string)$id),
            'action_label_ms' =&gt; 'Semak Permohonan',
            'dedupe_key' =&gt; 'permohonan:' . $id . ':officer_review',
            'audience' =&gt; ['resolved_login_ids' =&gt; $officerLoginIds],
        ], ['dedupe' =&gt; 'update']);
    }

    public static function approved(int $id, string $refNo, string $pemohonLoginId): void
    {
        $notification = NotificationWorkflowService::default();
        $notification-&gt;cancelSource('permohonan', (string)$id);
        $notification-&gt;publishInfo([
            'event_code' =&gt; 'permohonan.approved.final',
            'module_code' =&gt; 'PERMOHONAN',
            'source_type' =&gt; 'permohonan',
            'source_id' =&gt; (string)$id,
            'title_ms' =&gt; 'Permohonan Diluluskan',
            'body_ms' =&gt; 'Permohonan ' . $refNo . ' telah diluluskan.',
            'action_url' =&gt; 'pages/permohonan-view.php?id=' . urlencode((string)$id),
            'dedupe_key' =&gt; 'permohonan:' . $id . ':approved',
            'audience' =&gt; ['resolved_login_ids' =&gt; [$pemohonLoginId]],
        ], ['dedupe' =&gt; 'skip']);
    }
}</code></pre>
                      </div>
                    </div>
                    <?php endif; ?>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
    <?php include __DIR__ . '/../includes/footer.php'; ?>
  </div>
</div>

<?php include __DIR__ . '/../includes/script.php'; ?>
<script src="<?= h(base_url('assets/js/helpers/datatables-standard.js')) ?>?v=<?= h($version) ?>"></script>
<script src="<?= h(base_url('assets/vendor/flatpickr/flatpickr.min.js')) ?>?v=<?= h($version) ?>"></script>
<script>
(function () {
  'use strict';

  document.addEventListener('DOMContentLoaded', function () {
    const form = document.getElementById('notificationAdminForm');
    const submitBtn = document.getElementById('notificationAdminSubmit');
    const alertEl = document.getElementById('notificationAdminAlert');
    const setupBtn = document.getElementById('notificationSetupBtn');
    const setupModalEl = document.getElementById('notificationSetupModal');
    const audienceType = document.getElementById('na_audience_type');
    const audienceValue = document.getElementById('na_audience_value');
    const groupPicker = document.getElementById('na_group_picker');
    const categoryPicker = document.getElementById('na_category_picker');
    const templateSelect = document.getElementById('na_template_code');
    const previewTitle = document.getElementById('naPreviewTitle');
    const previewBody = document.getElementById('naPreviewBody');
    const previewIcon = document.getElementById('naPreviewIcon');
    const previewType = document.getElementById('naPreviewType');
    const previewSeverity = document.getElementById('naPreviewSeverity');
    const previewAudience = document.getElementById('naPreviewAudience');
    const iconPickerPreview = document.getElementById('naIconPickerPreview');
    const csrfToken = window.csrfToken || document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
    const audienceSamples = {
      ALL: '',
      CATEGORY_USER: 'STAF',
      GROUP_ID: <?= json_encode($firstGroupId, JSON_UNESCAPED_UNICODE) ?>,
      LOGIN_ID: <?= json_encode($currentLoginId, JSON_UNESCAPED_UNICODE) ?>,
      RESOLVED_LOGIN_ID: <?= json_encode($currentLoginId . "\nuser02", JSON_UNESCAPED_UNICODE) ?>,
      ROLE_ID: <?= json_encode($firstGroupId, JSON_UNESCAPED_UNICODE) ?>,
      DEPARTMENT_ID: 'JTMK',
      PERMISSION: 'notification.manage'
    };
    const audiencePlaceholders = {
      ALL: <?= json_encode(na('notification_admin_audience_all_help', 'ALL does not require a value.'), JSON_UNESCAPED_UNICODE) ?>,
      CATEGORY_USER: <?= json_encode(na('notification_admin_example_prefix', 'Example:') . ' STAF', JSON_UNESCAPED_UNICODE) ?>,
      GROUP_ID: <?= json_encode(na('notification_admin_example_prefix', 'Example:') . ' ' . $firstGroupId, JSON_UNESCAPED_UNICODE) ?>,
      LOGIN_ID: <?= json_encode(na('notification_admin_example_prefix', 'Example:') . ' ' . $currentLoginId, JSON_UNESCAPED_UNICODE) ?>,
      RESOLVED_LOGIN_ID: <?= json_encode(na('notification_admin_example_prefix', 'Example:') . ' ' . $currentLoginId . ', user02', JSON_UNESCAPED_UNICODE) ?>,
      ROLE_ID: <?= json_encode(na('notification_admin_example_prefix', 'Example:') . ' ' . $firstGroupId, JSON_UNESCAPED_UNICODE) ?>,
      DEPARTMENT_ID: <?= json_encode(na('notification_admin_example_prefix', 'Example:') . ' JTMK', JSON_UNESCAPED_UNICODE) ?>,
      PERMISSION: <?= json_encode(na('notification_admin_example_prefix', 'Example:') . ' notification.manage', JSON_UNESCAPED_UNICODE) ?>
    };
    let recentDt = null;

    function setAlert(type, text) {
      alertEl.className = 'alert mb-3 alert-' + type;
      alertEl.textContent = text;
      alertEl.classList.remove('d-none');
    }

    function clearAlert() {
      alertEl.classList.add('d-none');
      alertEl.textContent = '';
    }

    function escapeHtml(value) {
      const div = document.createElement('div');
      div.textContent = value == null ? '' : String(value);
      return div.innerHTML;
    }

    function fallbackCopy(text) {
      const textarea = document.createElement('textarea');
      textarea.value = text;
      textarea.setAttribute('readonly', 'readonly');
      textarea.style.position = 'fixed';
      textarea.style.left = '-9999px';
      document.body.appendChild(textarea);
      textarea.select();
      try {
        document.execCommand('copy');
      } finally {
        textarea.remove();
      }
    }

    function copySampleCode(button) {
      const targetId = button ? button.getAttribute('data-copy-target') : '';
      const codeEl = targetId ? document.getElementById(targetId) : null;
      const code = codeEl ? (codeEl.textContent || '').trim() : '';
      if (!code) return;

      const original = button.innerHTML;
      const markCopied = function () {
        button.innerHTML = '<i class="ri-check-line me-1"></i>' + <?= json_encode(na('notification_admin_copied', 'Copied'), JSON_UNESCAPED_UNICODE) ?>;
        button.disabled = true;
        setTimeout(function () {
          button.innerHTML = original;
          button.disabled = false;
        }, 1300);
      };

      if (navigator.clipboard && navigator.clipboard.writeText) {
        navigator.clipboard.writeText(code).then(markCopied).catch(function () {
          fallbackCopy(code);
          markCopied();
        });
        return;
      }

      fallbackCopy(code);
      markCopied();
    }

    function readForm() {
      const data = {};
      new FormData(form).forEach(function (value, key) {
        data[key] = value;
      });
      data.requires_action = document.getElementById('na_requires_action').checked ? 1 : 0;
      data.resolve_to_login_ids = ['ROLE_ID', 'DEPARTMENT_ID', 'GROUP_ID'].includes(data.audience_type || '') && data.type === 'workflow' ? 1 : 0;
      return data;
    }

    function updatePreview() {
      const data = readForm();
      previewTitle.textContent = data.title_ms || <?= json_encode(na('notification_admin_preview_empty_title', 'Notification title'), JSON_UNESCAPED_UNICODE) ?>;
      previewBody.textContent = data.body_ms || <?= json_encode(na('notification_admin_preview_empty_body', 'Notification body preview will appear here.'), JSON_UNESCAPED_UNICODE) ?>;
      previewIcon.className = (data.icon || 'ri-notification-3-line') + ' fs-20';
      if (iconPickerPreview) {
        iconPickerPreview.className = (data.icon || 'ri-notification-3-line') + ' fs-18';
      }
      previewType.textContent = data.type || 'announcement';
      previewSeverity.textContent = data.severity || 'info';
      previewAudience.textContent = data.audience_type === 'ALL' ? 'ALL' : ((data.audience_type || '') + ': ' + (data.audience_value || '-'));
    }

    function syncAudienceSample(force) {
      const type = audienceType.value || 'ALL';
      const sample = audienceSamples[type] || '';
      const current = audienceValue.value.trim();
      const isSampleOwned = audienceValue.dataset.sampleOwned === '1';

      audienceValue.placeholder = audiencePlaceholders[type] || <?= json_encode(na('notification_admin_audience_help', 'Use comma or new line for multiple values. Leave blank for ALL.'), JSON_UNESCAPED_UNICODE) ?>;
      if (document.getElementById('naAudienceHelp')) {
        document.getElementById('naAudienceHelp').textContent = audienceValue.placeholder;
      }

      if (type === 'ALL') {
        audienceValue.value = '';
        audienceValue.dataset.sampleOwned = '1';
        return;
      }

      if (force || current === '' || isSampleOwned) {
        audienceValue.value = sample;
        audienceValue.dataset.sampleOwned = '1';
      }
    }

    function setIconValue(value) {
      const iconSelect = document.getElementById('na_icon');
      const iconValue = value || 'ri-notification-3-line';
      if (!iconSelect) return;

      if (!Array.from(iconSelect.options).some(function (option) { return option.value === iconValue; })) {
        const option = new Option(iconValue, iconValue);
        iconSelect.add(option);
      }

      iconSelect.value = iconValue;
      updatePreview();
    }

    function upgradeFieldHelp() {
      document.querySelectorAll('#notificationSetupModal .notification-admin-field-help').forEach(function (helpEl) {
        const text = (helpEl.textContent || '').trim();
        if (!text) return;

        const fieldWrap = helpEl.closest('.col-12, .col-md-3, .col-md-4, .col-md-6, .col-md-8');
        const label = fieldWrap ? fieldWrap.querySelector('label.form-label, label.form-check-label') : null;
        if (!label || label.querySelector('.notification-field-info')) return;

        const icon = document.createElement('i');
        icon.className = 'ri-information-line notification-field-info ms-1';
        icon.setAttribute('tabindex', '0');
        icon.setAttribute('role', 'button');
        icon.setAttribute('aria-label', text);
        icon.setAttribute('data-bs-toggle', 'tooltip');
        icon.setAttribute('data-bs-placement', 'top');
        icon.setAttribute('data-bs-title', text);
        icon.setAttribute('title', text);
        label.appendChild(icon);
      });

      if (window.bootstrap && window.bootstrap.Tooltip) {
        document.querySelectorAll('#notificationSetupModal [data-bs-toggle="tooltip"]').forEach(function (el) {
          window.bootstrap.Tooltip.getOrCreateInstance(el, {
            container: 'body',
            trigger: 'hover focus'
          });
        });
      }
    }

    function initRecentTable() {
      if (!window.jQuery || !jQuery.fn || !jQuery.fn.DataTable) return;
      if (jQuery.fn.dataTable.isDataTable('#notificationAdminRecentTable')) return;
      const table = document.getElementById('notificationAdminRecentTable');
      const options = {
        processing: true,
        serverSide: true,
        searching: true,
        ordering: false,
        pageLength: 10,
        lengthMenu: [10, 25, 50, 100],
        ajax: function (request, callback) {
          fetch(table.dataset.listUrl, {
            method: 'POST', noLoader: true, credentials: 'same-origin',
            headers: { 'Accept':'application/json','Content-Type':'application/json','X-CSRF-Token':csrfToken,'X-No-Loader':'1' },
            body: JSON.stringify({ draw:request.draw,start:request.start,length:request.length,search:(request.search || {}).value || '',type:document.getElementById('notificationAdminTypeFilter').value,priority:document.getElementById('notificationAdminPriorityFilter').value,status:document.getElementById('notificationAdminStatusFilter').value })
          }).then(function (response) { return response.json().then(function (body) { if (!response.ok || body.success === false) throw new Error(body.message || <?= json_encode(na('notification_admin_list_failed', 'Unable to load notifications.'), JSON_UNESCAPED_UNICODE) ?>); return body.data || body; }); })
            .then(function (data) { callback({ draw:data.draw,recordsTotal:data.recordsTotal,recordsFiltered:data.recordsFiltered,data:data.items || [] }); })
            .catch(function (error) { callback({ draw:request.draw,recordsTotal:0,recordsFiltered:0,data:[] }); if (window.Swal) window.Swal.fire({toast:true,position:'top-end',icon:'error',title:error.message,showConfirmButton:false,timer:3000}); });
        },
        columns: [
          { data:null,render:function (_data,_type,_row,meta) { return meta.settings._iDisplayStart + meta.row + 1; },className:'text-center' },
          { data:null,render:function (_data,_type,row) { return '<div class="fw-semibold">'+escapeHtml(row.f_title_ms || '')+'</div><small class="text-muted">'+escapeHtml(row.f_eventCode || '')+'</small>'; } },
          { data:'f_type',render:function (value) { return '<span class="badge bg-light text-dark">'+escapeHtml(value || '')+'</span>'; } },
          { data:'f_priority',render:function (value) { return '<span class="badge bg-secondary-subtle text-secondary">'+escapeHtml(value || '')+'</span>'; } },
          { data:null,render:function (_data,_type,row) { return '<span class="fw-semibold">'+escapeHtml(row.audience_summary || <?= json_encode(na('notification_admin_no_audience', 'No audience'), JSON_UNESCAPED_UNICODE) ?>)+'</span><div class="text-muted small">'+<?= json_encode(na('notification_admin_target_count', 'Target count'), JSON_UNESCAPED_UNICODE) ?>+': '+Number(row.audience_count || 0)+'</div>'; } },
          { data:'f_requiresAction',render:function (value) { return Number(value) === 1 ? '<span class="badge bg-warning-subtle text-warning">'+<?= json_encode(na('common_yes', 'Yes'), JSON_UNESCAPED_UNICODE) ?>+'</span>' : '<span class="badge bg-light text-dark">'+<?= json_encode(na('common_no', 'No'), JSON_UNESCAPED_UNICODE) ?>+'</span>'; } },
          { data:'f_insertdt',render:function (value) { return '<small>'+escapeHtml(value || '')+'</small>'; } }
        ],
        language: { lengthMenu:"<?= h(__('userList_dt_length_menu')) ?>",search:"",info:"<?= h(__('userList_dt_info')) ?>",infoEmpty:"<?= h(__('userList_dt_info_empty')) ?>",emptyTable:"<?= h(__('userList_no_records')) ?>",paginate:{previous:"<?= h(__('userList_dt_paginate_prev')) ?>",next:"<?= h(__('userList_dt_paginate_next')) ?>"},zeroRecords:"<?= h(__('userList_dt_zero_records')) ?>" }
      };
      recentDt = jQuery('#notificationAdminRecentTable').DataTable(window.DataTableStandard && typeof window.DataTableStandard.options === 'function' ? window.DataTableStandard.options(options) : options);
      if (window.DataTableStandard && typeof window.DataTableStandard.decorate === 'function') {
        window.DataTableStandard.decorate('#notificationAdminRecentTable', {
          searchPlaceholder: <?= json_encode(na('userList_dt_search_label', 'Search'), JSON_UNESCAPED_UNICODE) ?>
        });
      }
      jQuery('#notificationAdminRecentTable_length select').addClass('form-select w-auto');
      jQuery('#notificationAdminRecentTable_length label').addClass('mb-0');
      jQuery('#notificationAdminRecentTable_wrapper .dt-top-left').addClass('d-flex align-items-center gap-2 flex-nowrap');
      jQuery('#notificationAdminRecentTable_wrapper .dt-top-right').addClass('align-items-center gap-2 flex-nowrap');
      ['notificationAdminTypeFilter','notificationAdminPriorityFilter','notificationAdminStatusFilter'].forEach(function (id) { document.getElementById(id).addEventListener('change', function () { recentDt.ajax.reload(); }); });
    }

    function renderRecent(rows) {
      if (recentDt && recentDt.ajax) { recentDt.ajax.reload(null, false); return; }
      const body = document.getElementById('notificationAdminRecentBody');
      if (recentDt && window.jQuery && jQuery.fn.dataTable.isDataTable('#notificationAdminRecentTable')) {
        recentDt.destroy();
        recentDt = null;
      }
      body.innerHTML = (rows || []).map(function (row, index) {
        const requiresAction = Number(row.f_requiresAction || 0) === 1
          ? '<span class="badge bg-warning-subtle text-warning">' + <?= json_encode(na('common_yes', 'Yes'), JSON_UNESCAPED_UNICODE) ?> + '</span>'
          : '<span class="badge bg-light text-dark">' + <?= json_encode(na('common_no', 'No'), JSON_UNESCAPED_UNICODE) ?> + '</span>';

        return '<tr>' +
          '<td class="text-center">' + (index + 1) + '</td>' +
          '<td><div class="fw-semibold">' + escapeHtml(row.f_title_ms || '') + '</div><small class="text-muted">' + escapeHtml(row.f_eventCode || '') + '</small></td>' +
          '<td><span class="badge bg-light text-dark">' + escapeHtml(row.f_type || '') + '</span></td>' +
          '<td><span class="badge bg-secondary-subtle text-secondary">' + escapeHtml(row.f_priority || '') + '</span></td>' +
          '<td><span class="fw-semibold">' + escapeHtml(row.audience_summary || <?= json_encode(na('notification_admin_no_audience', 'No audience'), JSON_UNESCAPED_UNICODE) ?>) + '</span><div class="text-muted small">' + <?= json_encode(na('notification_admin_target_count', 'Target count'), JSON_UNESCAPED_UNICODE) ?> + ': ' + Number(row.audience_count || 0) + '</div></td>' +
          '<td>' + requiresAction + '</td>' +
          '<td><small>' + escapeHtml(row.f_insertdt || '') + '</small></td>' +
          '</tr>';
      }).join('');
      initRecentTable();
    }

    function confirmPublication(payload) {
      const isBroadcast = payload.audience_type === 'ALL';
      const audience = isBroadcast ? <?= json_encode(na('notification_admin_audience_all_users', 'All users'), JSON_UNESCAPED_UNICODE) ?> : ((payload.audience_type || '-') + ': ' + (payload.audience_value || '-'));
      const html = '<div class="notification-admin-confirm-grid">' +
        '<div><small>' + escapeHtml(<?= json_encode(na('notification_admin_confirm_title_label', 'Title'), JSON_UNESCAPED_UNICODE) ?>) + '</small><strong>' + escapeHtml(payload.title_ms || '-') + '</strong></div>' +
        '<div><small>' + escapeHtml(<?= json_encode(na('notification_admin_confirm_audience_label', 'Audience'), JSON_UNESCAPED_UNICODE) ?>) + '</small><strong>' + escapeHtml(audience) + '</strong></div>' +
        '<div><small>' + escapeHtml(<?= json_encode(na('notification_admin_confirm_type_label', 'Type / Priority'), JSON_UNESCAPED_UNICODE) ?>) + '</small><strong>' + escapeHtml((payload.type || '-') + ' / ' + (payload.priority || '-')) + '</strong></div>' +
        '<div><small>' + escapeHtml(<?= json_encode(na('notification_admin_confirm_schedule_label', 'Schedule'), JSON_UNESCAPED_UNICODE) ?>) + '</small><strong>' + escapeHtml(payload.expires_at || <?= json_encode(na('notification_admin_no_expiry', 'No expiry'), JSON_UNESCAPED_UNICODE) ?>) + '</strong></div></div>';
      if (!window.Swal) return Promise.resolve(window.confirm(<?= json_encode(na('notification_admin_confirm_text', 'Confirm this notification publication?'), JSON_UNESCAPED_UNICODE) ?>));
      return window.Swal.fire({
        icon: isBroadcast ? 'warning' : 'question',
        title: isBroadcast ? <?= json_encode(na('notification_admin_confirm_broadcast_title', 'Confirm broadcast to all users'), JSON_UNESCAPED_UNICODE) ?> : <?= json_encode(na('notification_admin_confirm_title', 'Confirm notification publication'), JSON_UNESCAPED_UNICODE) ?>,
        text: isBroadcast ? <?= json_encode(na('notification_admin_confirm_broadcast_text', 'This notification will be visible to every eligible user.'), JSON_UNESCAPED_UNICODE) ?> : <?= json_encode(na('notification_admin_confirm_text', 'Review the publication details before continuing.'), JSON_UNESCAPED_UNICODE) ?>,
        html: html,
        showCancelButton: true,
        confirmButtonText: <?= json_encode(na('notification_admin_confirm_publish', 'Yes, publish'), JSON_UNESCAPED_UNICODE) ?>,
        cancelButtonText: <?= json_encode(na('common_cancel', 'Cancel'), JSON_UNESCAPED_UNICODE) ?>,
        confirmButtonColor: isBroadcast ? '#dc3545' : undefined,
        focusCancel: isBroadcast
      }).then(function (result) { return !!result.isConfirmed; });
    }

    setupBtn.addEventListener('click', function () {
      clearAlert();
      updatePreview();
      upgradeFieldHelp();
      if (window.bootstrap && setupModalEl) {
        window.bootstrap.Modal.getOrCreateInstance(setupModalEl).show();
      }
    });

    if (setupModalEl) {
      setupModalEl.addEventListener('shown.bs.modal', upgradeFieldHelp);
    }

    document.querySelectorAll('.notification-copy-sample').forEach(function (button) {
      button.addEventListener('click', function () {
        copySampleCode(button);
      });
    });

    audienceType.addEventListener('change', function () {
      syncAudienceSample(false);
      updatePreview();
    });

    audienceValue.addEventListener('input', function () {
      audienceValue.dataset.sampleOwned = '0';
    });

    groupPicker.addEventListener('change', function () {
      if (!groupPicker.value) return;
      audienceType.value = 'GROUP_ID';
      audienceValue.value = groupPicker.value;
      audienceValue.dataset.sampleOwned = '0';
      updatePreview();
    });

    categoryPicker.addEventListener('change', function () {
      if (!categoryPicker.value) return;
      audienceType.value = 'CATEGORY_USER';
      audienceValue.value = categoryPicker.value;
      audienceValue.dataset.sampleOwned = '0';
      updatePreview();
    });

    templateSelect.addEventListener('change', function () {
      const selected = templateSelect.selectedOptions[0];
      if (!selected || !selected.dataset.template) return;
      let template = {};
      try { template = JSON.parse(selected.dataset.template); } catch (e) { template = {}; }
      const map = {
        na_event_code: template.f_eventCode,
        na_type: template.f_type,
        na_severity: template.f_severity,
        na_priority: template.f_priority,
        na_title_ms: template.f_title_ms,
        na_title_en: template.f_title_en,
        na_body_ms: template.f_body_ms,
        na_body_en: template.f_body_en,
        na_action_label_ms: template.f_actionLabel_ms,
        na_action_label_en: template.f_actionLabel_en,
        na_icon: template.f_icon
      };
      Object.keys(map).forEach(function (id) {
        const el = document.getElementById(id);
        if (id === 'na_icon' && map[id] != null && String(map[id]) !== '') {
          setIconValue(String(map[id]));
          return;
        }
        if (el && map[id] != null && String(map[id]) !== '') {
          el.value = map[id];
        }
      });
      document.getElementById('na_requires_action').checked = Number(template.f_requiresAction || 0) === 1;
      updatePreview();
    });

    form.addEventListener('input', updatePreview);
    form.addEventListener('change', updatePreview);
    form.addEventListener('reset', function () {
      setTimeout(function () {
        clearAlert();
        syncAudienceSample(true);
        updatePreview();
      }, 0);
    });

    form.addEventListener('submit', async function (event) {
      event.preventDefault();
      clearAlert();
      const payload = readForm();
      if (!form.reportValidity()) return;
      if (!(await confirmPublication(payload))) return;
      submitBtn.disabled = true;
      submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>' + <?= json_encode(na('notification_admin_publishing', 'Publishing...'), JSON_UNESCAPED_UNICODE) ?>;

      fetch(form.dataset.publishUrl, {
        method: 'POST',
        noLoader: true,
        headers: {
          'Accept': 'application/json',
          'Content-Type': 'application/json',
          'X-CSRF-Token': csrfToken,
          'X-No-Loader': '1'
        },
        credentials: 'same-origin',
        body: JSON.stringify(payload)
      }).then(function (response) {
        return response.text().then(function (raw) {
          let data = {};
          try { data = raw ? JSON.parse(raw) : {}; } catch (e) { data = {}; }
          if (!response.ok || data.success === false) {
            throw new Error(data.message || data.error || <?= json_encode(na('notification_admin_publish_failed', 'Publish failed.'), JSON_UNESCAPED_UNICODE) ?>);
          }
          return data;
        });
      }).then(function (data) {
        setAlert('success', data.message || <?= json_encode(na('notification_admin_publish_success', 'Notification published successfully.'), JSON_UNESCAPED_UNICODE) ?>);
        renderRecent(data.recent || []);
      }).catch(function (error) {
        setAlert('danger', error.message || <?= json_encode(na('notification_admin_publish_failed', 'Unable to publish notification.'), JSON_UNESCAPED_UNICODE) ?>);
      }).finally(function () {
        submitBtn.disabled = false;
      submitBtn.innerHTML = '<i class="ri-send-plane-line me-1"></i>' + <?= json_encode(na('notification_admin_publish', 'Publish'), JSON_UNESCAPED_UNICODE) ?>;
      });
    });

    if (window.flatpickr) {
      window.flatpickr('.notification-datetime-picker', {
        enableTime: true,
        time_24hr: true,
        minuteIncrement: 5,
        dateFormat: 'Y-m-d H:i',
        allowInput: true
      });
    }

    syncAudienceSample(true);
    upgradeFieldHelp();
    initRecentTable();
    updatePreview();
  });
})();
</script>
</body>
</html>
