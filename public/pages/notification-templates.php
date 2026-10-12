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
require_once __DIR__ . '/../classes/NotificationTemplateService.php';

$pdo = Database::getInstance('mysql')->getConnection();
ensurePageGroupManagePermission($pdo, (string)(__('notification_template_forbidden') ?: 'You do not have permission to manage notification templates.'));

if (!function_exists('h')) {
    function h($value): string
    {
        return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
    }
}

function ntpl(string $key, string $fallback): string
{
    $value = __($key);
    return ($value === $key || $value === null || $value === '') ? $fallback : (string)$value;
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(16));
}

$service = new NotificationTemplateService($pdo);
$records = [];
$lang = (string)($_SESSION['lang'] ?? 'ms');
$version = (string)($_ENV['APP_ASSET_VER'] ?? filemtime(__FILE__) ?: '1');
$PAGE_TITLE = ntpl('notification_template_page_title', 'Notification Templates');
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
  <link href="<?= h(base_url('assets/css/pages/notification-templates.css')) ?>?v=<?= h($version) ?>" rel="stylesheet">
  <style>
    .notification-template-shell { width: 100%; }
    .notification-template-card { border: 1px solid rgba(15,23,42,.08); border-radius: 8px; box-shadow: 0 8px 24px rgba(15,23,42,.06); }
    #notificationTemplateTable th,
    #notificationTemplateTable td,
    #notificationTemplateTable.table > :not(caption) > * > * { vertical-align: top !important; }
    .notification-template-preview { border: 1px dashed rgba(var(--bs-primary-rgb), .35); border-radius: 8px; background: rgba(var(--bs-primary-rgb), .04); }
    .notification-template-help { display: none; }
    .notification-template-info {
      color: var(--bs-secondary-color);
      cursor: help;
      font-size: .95rem;
      line-height: 1;
      opacity: .78;
      vertical-align: -1px;
    }
    .notification-template-info:hover,
    .notification-template-info:focus {
      color: var(--bs-primary);
      opacity: 1;
      outline: 0;
    }
    #notificationTemplateModal .modal-header {
      background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
      color: #fff;
      border-bottom: 0;
      padding: 1rem 1.35rem;
    }
    #notificationTemplateModal .modal-title { color: #fff; font-weight: 600; }
    #notificationTemplateModal .modal-header .text-muted { color: rgba(255,255,255,.78) !important; }
    #notificationTemplateModal .btn-close { filter: invert(1); opacity: .9; }
    #notificationTemplateModal .modal-body { background: #f8fafc; padding: 1.1rem 1.35rem; }
    #notificationTemplateModal .modal-footer { background: #fff; border-top: 1px solid rgba(15,23,42,.08); }
    #notificationTemplateModal {
      z-index: 11020 !important;
    }
    #notificationTemplateModal,
    #notificationTemplateModal .modal-dialog,
    #notificationTemplateModal .modal-content,
    #notificationTemplateModal .modal-content::before,
    #notificationTemplateModal .modal-content::after {
      box-shadow: none !important;
      outline: 0 !important;
      filter: none !important;
    }
    #notificationTemplateModal.fade {
      transition: none !important;
    }
    #notificationTemplateModal.fade .modal-dialog,
    #notificationTemplateModal.show .modal-dialog {
      transition: none !important;
      transform: none !important;
    }
    #notificationTemplateModal .modal-dialog {
      border: 0 !important;
      background: transparent !important;
      position: relative;
      z-index: 1;
    }
    #notificationTemplateModal .modal-content {
      border: 0;
      border-radius: 8px;
      overflow: hidden;
    }
    .notification-template-surface {
      background: #fff;
      border: 1px solid rgba(15,23,42,.08);
      border-radius: 8px;
      padding: 1rem;
    }
    .notification-template-tabs {
      background: #fff;
      border: 1px solid rgba(15,23,42,.08);
      border-radius: 8px;
      gap: .35rem;
      padding: .35rem;
    }
    .notification-template-tabs .nav-link {
      border-radius: 7px;
      color: #475569;
      font-weight: 600;
      padding: .58rem .85rem;
    }
    .notification-template-tabs .nav-link.active {
      background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
      color: #fff;
    }
    #notificationTemplateModal .form-label {
      display: inline-flex;
      align-items: center;
      gap: .15rem;
      margin-bottom: .45rem;
      color: #334155;
      font-weight: 600;
      font-size: .875rem;
    }
    #notificationTemplateModal .form-control,
    #notificationTemplateModal .form-select {
      border: 1px solid #dbe4f0;
      border-radius: 8px;
      min-height: 38px;
      transition: border-color .15s ease-in-out, box-shadow .15s ease-in-out;
    }
    #notificationTemplateModal .form-control:focus,
    #notificationTemplateModal .form-select:focus {
      border-color: rgba(32,201,151,.65);
      box-shadow: 0 0 0 .2rem rgba(32,201,151,.14);
    }
    .notification-template-icon-preview {
      width: 38px;
      height: 38px;
      flex: 0 0 38px;
    }
    .tooltip { z-index: 11080 !important; }
    .tooltip .tooltip-inner {
      max-width: 460px;
      padding: .45rem .65rem;
      text-align: left;
      line-height: 1.35;
      white-space: normal;
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
              <h4 class="page-title"><i class="ri-file-list-3-line me-1"></i><?= h(ntpl('notification_template_page_title', 'Notification Templates')) ?></h4>
              <div class="page-title-right">
                <ol class="breadcrumb m-0">
                  <li class="breadcrumb-item"><a href="<?= h(base_url('pages/dashboard.php')) ?>"><?= h(ntpl('common_dashboard', 'Dashboard')) ?></a></li>
                  <li class="breadcrumb-item"><a href="<?= h(base_url('pages/notification-admin.php')) ?>"><?= h(ntpl('notification_admin_page_title', 'Notification Admin')) ?></a></li>
                  <li class="breadcrumb-item active"><?= h(ntpl('notification_template_page_title', 'Notification Templates')) ?></li>
                </ol>
              </div>
            </div>
          </div>
        </div>

        <div class="notification-template-shell">
          <section class="ntpl-hero">
            <div><span class="ntpl-eyebrow"><i class="ri-stack-line"></i><?= h(ntpl('notification_template_hero_eyebrow', 'REUSABLE COMMUNICATION')) ?></span><h1><?= h(ntpl('notification_template_hero_title', 'Build consistent notifications once, reuse them everywhere')) ?></h1><p><?= h(ntpl('notification_template_hero_text', 'Maintain governed bilingual content for modules, schedulers and workflow escalations.')) ?></p></div>
            <div class="ntpl-hero-note"><i class="ri-code-box-line"></i><div><strong><?= h(ntpl('notification_template_hero_note_title', 'Developer-ready templates')) ?></strong><span><?= h(ntpl('notification_template_hero_note_text', 'Template and event codes connect application workflows to reusable messages.')) ?></span></div></div>
          </section>
          <div class="card notification-template-card mb-3">
            <div class="card-body d-flex flex-wrap align-items-center justify-content-between gap-3">
              <div>
                <h5 class="card-title mb-1"><?= h(ntpl('notification_template_list_title', 'Template Registry')) ?></h5>
                <p class="text-muted mb-0"><?= h(ntpl('notification_template_list_subtitle', 'Maintain reusable notification wording for modules, scheduler, and escalation flows.')) ?></p>
              </div>
              <button type="button" class="btn btn-primary" id="notificationTemplateCreateBtn">
                <i class="ri-add-line me-1"></i><?= h(ntpl('notification_template_create', 'Create Template')) ?>
              </button>
            </div>
          </div>

          <div class="card notification-template-card">
            <div class="card-body">
              <div class="alert d-none" id="notificationTemplateAlert"></div>
              <div class="ntpl-filters">
                <label><span><?= h(ntpl('notification_template_field_type','Type')) ?></span><select id="ntplTypeFilter" class="form-select form-select-sm"><option value=""><?= h(ntpl('notification_filter_all','All')) ?></option><option value="event">event</option><option value="announcement">announcement</option><option value="reminder">reminder</option><option value="workflow">workflow</option></select></label>
                <label><span><?= h(ntpl('notification_template_field_priority','Priority')) ?></span><select id="ntplPriorityFilter" class="form-select form-select-sm"><option value=""><?= h(ntpl('notification_filter_all','All')) ?></option><option value="low">low</option><option value="normal">normal</option><option value="high">high</option><option value="urgent">urgent</option></select></label>
                <label><span><?= h(ntpl('notification_template_col_status','Status')) ?></span><select id="ntplStatusFilter" class="form-select form-select-sm"><option value="all"><?= h(ntpl('notification_filter_all','All')) ?></option><option value="active"><?= h(ntpl('notification_template_status_active','Active')) ?></option><option value="archived"><?= h(ntpl('notification_template_status_archived','Archived')) ?></option></select></label>
              </div>
              <div class="table-responsive">
                <table class="table table-striped notification-template-table" id="notificationTemplateTable" data-list-url="<?= h(base_url('ajax/notification-template-list.php')) ?>">
                  <thead>
                    <tr>
                      <th><?= h(ntpl('notification_template_col_code', 'Template Code')) ?></th>
                      <th><?= h(ntpl('notification_template_col_event', 'Event Code')) ?></th>
                      <th><?= h(ntpl('notification_template_col_title', 'Title')) ?></th>
                      <th><?= h(ntpl('notification_template_col_meta', 'Meta')) ?></th>
                      <th><?= h(ntpl('notification_template_col_status', 'Status')) ?></th>
                      <th class="text-end"><?= h(ntpl('notification_template_col_actions', 'Actions')) ?></th>
                    </tr>
                  </thead>
                  <tbody id="notificationTemplateBody"></tbody>
                </table>
              </div>
            </div>
          </div>
        </div>

        <div class="modal fade" id="notificationTemplateModal" tabindex="-1" aria-hidden="true">
          <div class="modal-dialog modal-xl modal-dialog-scrollable">
            <div class="modal-content">
              <form id="notificationTemplateForm" data-action-url="<?= h(base_url('ajax/notification-template-action.php')) ?>">
                <div class="modal-header">
                  <div>
                    <h5 class="modal-title" id="notificationTemplateModalTitle"><?= h(ntpl('notification_template_modal_title', 'Notification Template')) ?></h5>
                    <div class="text-muted small"><?= h(ntpl('notification_template_modal_subtitle', 'Define reusable MS/EN notification content and placeholders.')) ?></div>
                  </div>
                  <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?= h(ntpl('common_close','Close')) ?>"></button>
                </div>
                <div class="modal-body">
                  <input type="hidden" name="template_id" id="nt_template_id" value="0">
                  <ul class="nav nav-pills notification-template-tabs mb-3" role="tablist">
                    <li class="nav-item" role="presentation"><button class="nav-link active" data-bs-toggle="tab" data-bs-target="#ntTabBasic" type="button" role="tab"><i class="ri-settings-3-line me-1"></i><?= h(ntpl('notification_template_tab_basic','Basic')) ?></button></li>
                    <li class="nav-item" role="presentation"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#ntTabContent" type="button" role="tab"><i class="ri-file-text-line me-1"></i><?= h(ntpl('notification_template_tab_content','Content')) ?></button></li>
                    <li class="nav-item" role="presentation"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#ntTabAction" type="button" role="tab"><i class="ri-cursor-line me-1"></i><?= h(ntpl('notification_template_tab_action','Action & Icon')) ?></button></li>
                    <li class="nav-item" role="presentation"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#ntTabAdvanced" type="button" role="tab"><i class="ri-braces-line me-1"></i><?= h(ntpl('notification_template_tab_advanced','Advanced')) ?></button></li>
                  </ul>

                  <div class="tab-content notification-template-surface">
                    <div class="tab-pane fade show active" id="ntTabBasic" role="tabpanel">
                  <div class="row g-3">
                    <div class="col-md-4">
                      <label class="form-label" for="nt_template_code"><?= h(ntpl('notification_template_field_template_code', 'Template Code')) ?> <span class="text-danger">*</span></label>
                      <input type="text" class="form-control" id="nt_template_code" name="template_code" maxlength="100" required placeholder="CORE_PASSWORD_REMINDER">
                    </div>
                    <div class="col-md-4">
                      <label class="form-label" for="nt_event_code"><?= h(ntpl('notification_template_field_event_code', 'Event Code')) ?> <span class="text-danger">*</span></label>
                      <input type="text" class="form-control" id="nt_event_code" name="event_code" maxlength="100" required placeholder="core.password.reminder">
                    </div>
                    <div class="col-md-4">
                      <label class="form-label" for="nt_module_code"><?= h(ntpl('notification_template_field_module_code', 'Module Code')) ?></label>
                      <input type="text" class="form-control" id="nt_module_code" name="module_code" placeholder="CORE">
                    </div>

                    <div class="col-md-3">
                      <label class="form-label" for="nt_type"><?= h(ntpl('notification_template_field_type', 'Type')) ?></label>
                      <select class="form-select" id="nt_type" name="type">
                        <option value="event">event</option>
                        <option value="announcement">announcement</option>
                        <option value="reminder">reminder</option>
                        <option value="workflow">workflow</option>
                      </select>
                    </div>
                    <div class="col-md-3">
                      <label class="form-label" for="nt_category"><?= h(ntpl('notification_template_field_category', 'Category')) ?></label>
                      <input type="text" class="form-control" id="nt_category" name="category" value="system">
                    </div>
                    <div class="col-md-3">
                      <label class="form-label" for="nt_severity"><?= h(ntpl('notification_template_field_severity', 'Severity')) ?></label>
                      <select class="form-select" id="nt_severity" name="severity">
                        <option value="info">info</option>
                        <option value="success">success</option>
                        <option value="warning">warning</option>
                        <option value="danger">danger</option>
                      </select>
                    </div>
                    <div class="col-md-3">
                      <label class="form-label" for="nt_priority"><?= h(ntpl('notification_template_field_priority', 'Priority')) ?></label>
                      <select class="form-select" id="nt_priority" name="priority">
                        <option value="normal">normal</option>
                        <option value="low">low</option>
                        <option value="high">high</option>
                        <option value="urgent">urgent</option>
                      </select>
                    </div>
                  </div>
                    </div>

                    <div class="tab-pane fade" id="ntTabContent" role="tabpanel">
                  <div class="row g-3">
                    <div class="col-md-6">
                      <label class="form-label" for="nt_title_ms"><?= h(ntpl('notification_template_field_title_ms', 'Title MS')) ?> <span class="text-danger">*</span></label>
                      <input type="text" class="form-control" id="nt_title_ms" name="title_ms" maxlength="255" required>
                    </div>
                    <div class="col-md-6">
                      <label class="form-label" for="nt_title_en"><?= h(ntpl('notification_template_field_title_en', 'Title EN')) ?></label>
                      <input type="text" class="form-control" id="nt_title_en" name="title_en" maxlength="255">
                    </div>
                    <div class="col-md-6">
                      <label class="form-label" for="nt_body_ms"><?= h(ntpl('notification_template_field_body_ms', 'Body MS')) ?></label>
                      <textarea class="form-control" id="nt_body_ms" name="body_ms" rows="5" maxlength="10000"></textarea>
                    </div>
                    <div class="col-md-6">
                      <label class="form-label" for="nt_body_en"><?= h(ntpl('notification_template_field_body_en', 'Body EN')) ?></label>
                      <textarea class="form-control" id="nt_body_en" name="body_en" rows="5" maxlength="10000"></textarea>
                    </div>
                  </div>
                    </div>

                    <div class="tab-pane fade" id="ntTabAction" role="tabpanel">
                  <div class="row g-3">
                    <div class="col-md-4">
                      <label class="form-label" for="nt_action_label_ms"><?= h(ntpl('notification_template_field_action_label_ms', 'Action Label MS')) ?></label>
                      <input type="text" class="form-control" id="nt_action_label_ms" name="action_label_ms">
                    </div>
                    <div class="col-md-4">
                      <label class="form-label" for="nt_action_label_en"><?= h(ntpl('notification_template_field_action_label_en', 'Action Label EN')) ?></label>
                      <input type="text" class="form-control" id="nt_action_label_en" name="action_label_en">
                    </div>
                    <div class="col-md-4">
                      <label class="form-label" for="nt_icon"><?= h(ntpl('notification_template_field_icon', 'Icon')) ?></label>
                      <div class="d-flex gap-2 align-items-start">
                        <span class="notification-template-icon-preview rounded bg-primary-subtle text-primary d-inline-flex align-items-center justify-content-center">
                          <i class="ri-notification-3-line fs-18" id="nt_icon_preview"></i>
                        </span>
                        <select class="form-select" id="nt_icon" name="icon">
                          <option value="ri-notification-3-line"><?= h(ntpl('notification_icon_general','General notification')) ?></option>
                          <option value="ri-megaphone-line"><?= h(ntpl('notification_icon_announcement','Announcement')) ?></option>
                          <option value="ri-alarm-warning-line"><?= h(ntpl('notification_icon_warning','Important warning')) ?></option>
                          <option value="ri-time-line"><?= h(ntpl('notification_icon_reminder','Reminder / deadline')) ?></option>
                          <option value="ri-task-line"><?= h(ntpl('notification_icon_workflow','Workflow task')) ?></option>
                          <option value="ri-checkbox-circle-line"><?= h(ntpl('notification_icon_success','Successful / approved')) ?></option>
                          <option value="ri-error-warning-line"><?= h(ntpl('notification_icon_issue','Issue / rejected')) ?></option>
                          <option value="ri-shield-check-line"><?= h(ntpl('notification_icon_security','Security')) ?></option>
                          <option value="ri-user-settings-line"><?= h(ntpl('notification_icon_account','User account')) ?></option>
                          <option value="ri-file-list-3-line"><?= h(ntpl('notification_icon_document','Document / form')) ?></option>
                        </select>
                      </div>
                    </div>
                  </div>
                    </div>

                    <div class="tab-pane fade" id="ntTabAdvanced" role="tabpanel">
                  <div class="row g-3">
                    <div class="col-md-8">
                      <label class="form-label" for="nt_placeholders"><?= h(ntpl('notification_template_field_placeholders', 'Placeholders JSON')) ?></label>
                      <textarea class="form-control font-monospace" id="nt_placeholders" name="placeholders" rows="5" maxlength="10000" placeholder='{"recipient_name":"User display name"}'></textarea>
                    </div>
                    <div class="col-md-4">
                      <div class="notification-template-preview p-3 h-100">
                        <div class="fw-semibold mb-2"><?= h(ntpl('notification_template_preview_title', 'Preview')) ?></div>
                        <div class="small text-muted" id="nt_preview_title"><?= h(ntpl('notification_template_preview_empty', 'Template title preview')) ?></div>
                        <div class="small mt-2" id="nt_preview_body"></div>
                      </div>
                    </div>
                    <div class="col-md-6">
                      <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" role="switch" id="nt_requires_action" name="requires_action" value="1">
                        <label class="form-check-label" for="nt_requires_action"><?= h(ntpl('notification_template_field_requires_action', 'Requires action by default')) ?></label>
                      </div>
                    </div>
                    <div class="col-md-6">
                      <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" role="switch" id="nt_status" name="status" value="1" checked>
                        <label class="form-check-label" for="nt_status"><?= h(ntpl('notification_template_field_status', 'Active')) ?></label>
                      </div>
                    </div>
                  </div>
                    </div>
                  </div>
                </div>
                <div class="modal-footer">
                  <button type="button" class="btn btn-light" data-bs-dismiss="modal"><?= h(ntpl('notification_template_close', 'Close')) ?></button>
                  <button type="submit" class="btn btn-primary" id="notificationTemplateSaveBtn">
                    <i class="ri-save-line me-1"></i><?= h(ntpl('notification_template_save', 'Save Template')) ?>
                  </button>
                </div>
              </form>
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
<script>
(function () {
  'use strict';

  function initNotificationTemplatePage() {
  let records = <?= json_encode($records, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
  const csrfToken = window.csrfToken || document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
  const modalEl = document.getElementById('notificationTemplateModal');
  const modal = new bootstrap.Modal(modalEl);
  const form = document.getElementById('notificationTemplateForm');
  const alertEl = document.getElementById('notificationTemplateAlert');
  const bodyEl = document.getElementById('notificationTemplateBody');
  const saveBtn = document.getElementById('notificationTemplateSaveBtn');
  const iconPreview = document.getElementById('nt_icon_preview');
  let templateDt = null;

  const labels = {
    save: <?= json_encode(ntpl('notification_template_save', 'Save Template'), JSON_UNESCAPED_UNICODE) ?>,
    saving: <?= json_encode(ntpl('notification_template_saving', 'Saving...'), JSON_UNESCAPED_UNICODE) ?>,
    active: <?= json_encode(ntpl('notification_template_status_active', 'Active'), JSON_UNESCAPED_UNICODE) ?>,
    archived: <?= json_encode(ntpl('notification_template_status_archived', 'Archived'), JSON_UNESCAPED_UNICODE) ?>,
    edit: <?= json_encode(ntpl('notification_template_action_edit', 'Edit'), JSON_UNESCAPED_UNICODE) ?>,
    duplicate: <?= json_encode(ntpl('notification_template_action_duplicate', 'Duplicate'), JSON_UNESCAPED_UNICODE) ?>,
    archive: <?= json_encode(ntpl('notification_template_action_archive', 'Archive'), JSON_UNESCAPED_UNICODE) ?>,
    restore: <?= json_encode(ntpl('notification_template_action_restore', 'Restore'), JSON_UNESCAPED_UNICODE) ?>,
    deleteText: <?= json_encode(ntpl('notification_template_action_delete', 'Delete'), JSON_UNESCAPED_UNICODE) ?>,
    confirmDelete: <?= json_encode(ntpl('notification_template_delete_confirm', 'Delete this notification template?'), JSON_UNESCAPED_UNICODE) ?>
  };

  const helpText = {
    nt_template_code: <?= json_encode(ntpl('notification_template_help_template_code', 'Kod unik template. Guna format ringkas tanpa space, contoh CORE_PASSWORD_REMINDER.'), JSON_UNESCAPED_UNICODE) ?>,
    nt_event_code: <?= json_encode(ntpl('notification_template_help_event_code', 'Kod event yang akan dipanggil oleh programmer semasa publish notification.'), JSON_UNESCAPED_UNICODE) ?>,
    nt_module_code: <?= json_encode(ntpl('notification_template_help_module_code', 'Kod modul pemilik template, contoh CORE, HR, FACILITY.'), JSON_UNESCAPED_UNICODE) ?>,
    nt_type: <?= json_encode(ntpl('notification_template_help_type', 'Jenis default notification yang akan digunakan apabila template ini dipilih.'), JSON_UNESCAPED_UNICODE) ?>,
    nt_category: <?= json_encode(ntpl('notification_template_help_category', 'Kategori ringkas untuk grouping notification, contoh system atau approval.'), JSON_UNESCAPED_UNICODE) ?>,
    nt_severity: <?= json_encode(ntpl('notification_template_help_severity', 'Severity menentukan warna/urgency paparan notifikasi.'), JSON_UNESCAPED_UNICODE) ?>,
    nt_priority: <?= json_encode(ntpl('notification_template_help_priority', 'Priority membantu susunan dan penekanan notification kepada pengguna.'), JSON_UNESCAPED_UNICODE) ?>,
    nt_title_ms: <?= json_encode(ntpl('notification_template_help_title_ms', 'Tajuk bahasa Melayu yang akan dipaparkan kepada pengguna.'), JSON_UNESCAPED_UNICODE) ?>,
    nt_title_en: <?= json_encode(ntpl('notification_template_help_title_en', 'Tajuk versi English jika sistem menggunakan pilihan bahasa English.'), JSON_UNESCAPED_UNICODE) ?>,
    nt_body_ms: <?= json_encode(ntpl('notification_template_help_body_ms', 'Isi mesej bahasa Melayu. Boleh gunakan placeholder seperti {recipient_name}.'), JSON_UNESCAPED_UNICODE) ?>,
    nt_body_en: <?= json_encode(ntpl('notification_template_help_body_en', 'Isi mesej English jika diperlukan.'), JSON_UNESCAPED_UNICODE) ?>,
    nt_action_label_ms: <?= json_encode(ntpl('notification_template_help_action_label_ms', 'Label butang tindakan bahasa Melayu, contoh Semak Sekarang.'), JSON_UNESCAPED_UNICODE) ?>,
    nt_action_label_en: <?= json_encode(ntpl('notification_template_help_action_label_en', 'Label butang tindakan English, contoh Review Now.'), JSON_UNESCAPED_UNICODE) ?>,
    nt_icon: <?= json_encode(ntpl('notification_template_help_icon', 'Pilih ikon default yang akan digunakan oleh template ini.'), JSON_UNESCAPED_UNICODE) ?>,
    nt_placeholders: <?= json_encode(ntpl('notification_template_help_placeholders', 'Senarai placeholder JSON untuk programmer tahu variable yang boleh dihantar ke template.'), JSON_UNESCAPED_UNICODE) ?>,
    nt_requires_action: <?= json_encode(ntpl('notification_template_help_requires_action', 'Aktifkan jika template ini biasanya memerlukan tindakan pengguna.'), JSON_UNESCAPED_UNICODE) ?>,
    nt_status: <?= json_encode(ntpl('notification_template_help_status', 'Template aktif boleh digunakan oleh admin dan proses sistem.'), JSON_UNESCAPED_UNICODE) ?>
  };

  function escapeHtml(value) {
    const div = document.createElement('div');
    div.textContent = value == null ? '' : String(value);
    return div.innerHTML;
  }

  function setAlert(type, message) {
    alertEl.className = 'alert alert-' + type;
    alertEl.textContent = message;
    alertEl.classList.remove('d-none');
  }

  function clearAlert() {
    alertEl.classList.add('d-none');
    alertEl.textContent = '';
  }

  function renderRows() {
    bodyEl.innerHTML = records.map(function (row) {
      const active = Number(row.f_status || 0) === 1;
      const used = Number(row.usage_count || 0) > 0;
      return '<tr data-type="' + escapeHtml(row.f_type || '') + '" data-priority="' + escapeHtml(row.f_priority || '') + '" data-status="' + (active ? 'active' : 'archived') + '">' +
        '<td><div class="fw-semibold">' + escapeHtml(row.f_templateCode || '') + '</div><small class="text-muted">' + escapeHtml(row.f_moduleCode || '') + '</small></td>' +
        '<td><code>' + escapeHtml(row.f_eventCode || '') + '</code></td>' +
        '<td><div>' + escapeHtml(row.f_title_ms || '') + '</div><small class="text-muted">' + escapeHtml(row.f_title_en || '') + '</small></td>' +
        '<td><span class="badge bg-light text-dark">' + escapeHtml(row.f_type || '') + '</span> <span class="badge bg-info-subtle text-info">' + escapeHtml(row.f_severity || '') + '</span> <span class="badge bg-secondary-subtle text-secondary">' + escapeHtml(row.f_priority || '') + '</span>' + (Number(row.f_requiresAction || 0) === 1 ? ' <span class="badge bg-warning-subtle text-warning">' + <?= json_encode(ntpl('notification_template_action_badge','Action'),JSON_UNESCAPED_UNICODE) ?> + '</span>' : '') + '</td>' +
        '<td><span class="badge ' + (active ? 'bg-success-subtle text-success' : 'bg-secondary-subtle text-secondary') + '">' + escapeHtml(active ? labels.active : labels.archived) + '</span>' + (used ? '<div class="small text-muted mt-1">' + <?= json_encode(ntpl('notification_template_used_by','Used by {count} notifications'),JSON_UNESCAPED_UNICODE) ?>.replace('{count}',Number(row.usage_count || 0)) + '</div>' : '') + '</td>' +
        '<td class="text-end">' +
          '<div class="btn-group btn-group-sm" data-template-id="' + Number(row.f_templateID || 0) + '">' +
            '<button type="button" class="btn btn-outline-primary" data-action="edit">' + escapeHtml(labels.edit) + '</button>' +
            '<button type="button" class="btn btn-outline-secondary" data-action="duplicate">' + escapeHtml(labels.duplicate) + '</button>' +
            '<button type="button" class="btn btn-outline-warning" data-action="' + (active ? 'archive' : 'restore') + '">' + escapeHtml(active ? labels.archive : labels.restore) + '</button>' +
            '<button type="button" class="btn btn-outline-danger" data-action="delete" ' + (used ? 'disabled title="' + escapeHtml(<?= json_encode(ntpl('notification_template_in_use_help','Template is in use. Archive it instead.'),JSON_UNESCAPED_UNICODE) ?>) + '"' : '') + '>' + escapeHtml(labels.deleteText) + '</button>' +
          '</div>' +
        '</td>' +
      '</tr>';
    }).join('');
    if (window.jQuery && jQuery.fn && jQuery.fn.DataTable) {
      if (templateDt) { templateDt.destroy(); templateDt = null; }
      const options={pageLength:10,lengthMenu:[10,25,50,100],order:[[0,'asc']],language:{lengthMenu:"<?= h(__('userList_dt_length_menu')) ?>",search:"",info:"<?= h(__('userList_dt_info')) ?>",infoEmpty:"<?= h(__('userList_dt_info_empty')) ?>",emptyTable:"<?= h(__('userList_no_records')) ?>",paginate:{previous:"<?= h(__('userList_dt_paginate_prev')) ?>",next:"<?= h(__('userList_dt_paginate_next')) ?>"},zeroRecords:"<?= h(__('userList_dt_zero_records')) ?>"}};
      templateDt=jQuery('#notificationTemplateTable').DataTable(window.DataTableStandard&&typeof window.DataTableStandard.options==='function'?window.DataTableStandard.options(options):options);
      if(window.DataTableStandard)window.DataTableStandard.decorate('#notificationTemplateTable',{searchPlaceholder:<?= json_encode(ntpl('userList_dt_search_label','Search'),JSON_UNESCAPED_UNICODE) ?>});
      applyTemplateFilters();
    }
  }

  function findRecord(templateId) {
    templateId = Number(templateId || 0);
    return records.find(function (row) {
      return Number(row.f_templateID || 0) === templateId;
    }) || null;
  }

  function initServerTable() {
    if (!window.jQuery || !jQuery.fn || !jQuery.fn.DataTable) return;
    const table=document.getElementById('notificationTemplateTable');
    const options={processing:true,serverSide:true,searching:true,ordering:false,pageLength:10,lengthMenu:[10,25,50,100],ajax:function(request,callback){fetch(table.dataset.listUrl,{method:'POST',noLoader:true,credentials:'same-origin',headers:{Accept:'application/json','Content-Type':'application/json','X-CSRF-Token':csrfToken,'X-No-Loader':'1'},body:JSON.stringify({draw:request.draw,start:request.start,length:request.length,search:(request.search||{}).value||'',type:document.getElementById('ntplTypeFilter').value,priority:document.getElementById('ntplPriorityFilter').value,status:document.getElementById('ntplStatusFilter').value})}).then(function(r){return r.json().then(function(b){if(!r.ok||b.success===false)throw new Error(b.message||<?= json_encode(ntpl('notification_template_list_failed','Unable to load templates.'),JSON_UNESCAPED_UNICODE) ?>);return b.data||b;});}).then(function(data){records=data.items||[];callback({draw:data.draw,recordsTotal:data.recordsTotal,recordsFiltered:data.recordsFiltered,data:records});}).catch(function(error){callback({draw:request.draw,recordsTotal:0,recordsFiltered:0,data:[]});setAlert('danger',error.message);});},columns:[
      {data:null,render:function(_d,_t,r){return '<div class="fw-semibold">'+escapeHtml(r.f_templateCode||'')+'</div><small class="text-muted">'+escapeHtml(r.f_moduleCode||'')+'</small>'; }},
      {data:'f_eventCode',render:function(v){return '<code>'+escapeHtml(v||'')+'</code>'; }},
      {data:null,render:function(_d,_t,r){return '<div>'+escapeHtml(r.f_title_ms||'')+'</div><small class="text-muted">'+escapeHtml(r.f_title_en||'')+'</small>'; }},
      {data:null,render:function(_d,_t,r){return '<span class="badge bg-light text-dark">'+escapeHtml(r.f_type||'')+'</span> <span class="badge bg-info-subtle text-info">'+escapeHtml(r.f_severity||'')+'</span> <span class="badge bg-secondary-subtle text-secondary">'+escapeHtml(r.f_priority||'')+'</span>'+(Number(r.f_requiresAction||0)===1?' <span class="badge bg-warning-subtle text-warning">'+<?= json_encode(ntpl('notification_template_action_badge','Action'),JSON_UNESCAPED_UNICODE) ?>+'</span>':'');}},
      {data:null,render:function(_d,_t,r){const active=Number(r.f_status||0)===1,used=Number(r.usage_count||0)>0;return '<span class="badge '+(active?'bg-success-subtle text-success':'bg-secondary-subtle text-secondary')+'">'+escapeHtml(active?labels.active:labels.archived)+'</span>'+(used?'<div class="small text-muted mt-1">'+<?= json_encode(ntpl('notification_template_used_by','Used by {count} notifications'),JSON_UNESCAPED_UNICODE) ?>.replace('{count}',Number(r.usage_count||0))+'</div>':'');}},
      {data:null,className:'text-end',render:function(_d,_t,r){const active=Number(r.f_status||0)===1,used=Number(r.usage_count||0)>0;return '<div class="btn-group btn-group-sm" data-template-id="'+Number(r.f_templateID||0)+'"><button type="button" class="btn btn-outline-primary" data-action="edit">'+escapeHtml(labels.edit)+'</button><button type="button" class="btn btn-outline-secondary" data-action="duplicate">'+escapeHtml(labels.duplicate)+'</button><button type="button" class="btn btn-outline-warning" data-action="'+(active?'archive':'restore')+'">'+escapeHtml(active?labels.archive:labels.restore)+'</button><button type="button" class="btn btn-outline-danger" data-action="delete" '+(used?'disabled title="'+escapeHtml(<?= json_encode(ntpl('notification_template_in_use_help','Template is in use. Archive it instead.'),JSON_UNESCAPED_UNICODE) ?>)+'"':'')+'>'+escapeHtml(labels.deleteText)+'</button></div>';}}
    ],language:{lengthMenu:"<?= h(__('userList_dt_length_menu')) ?>",search:"",info:"<?= h(__('userList_dt_info')) ?>",infoEmpty:"<?= h(__('userList_dt_info_empty')) ?>",emptyTable:"<?= h(__('userList_no_records')) ?>",paginate:{previous:"<?= h(__('userList_dt_paginate_prev')) ?>",next:"<?= h(__('userList_dt_paginate_next')) ?>"},zeroRecords:"<?= h(__('userList_dt_zero_records')) ?>"}};
    templateDt=jQuery(table).DataTable(window.DataTableStandard&&typeof window.DataTableStandard.options==='function'?window.DataTableStandard.options(options):options);
    if(window.DataTableStandard)window.DataTableStandard.decorate('#notificationTemplateTable',{searchPlaceholder:<?= json_encode(ntpl('userList_dt_search_label','Search'),JSON_UNESCAPED_UNICODE) ?>});
  }

  function setIconValue(value) {
    const iconSelect = document.getElementById('nt_icon');
    const iconValue = value || 'ri-notification-3-line';
    if (!Array.from(iconSelect.options).some(function (option) { return option.value === iconValue; })) {
      iconSelect.add(new Option(iconValue, iconValue));
    }
    iconSelect.value = iconValue;
    if (iconPreview) {
      iconPreview.className = iconValue + ' fs-18';
    }
  }

  function upgradeFieldHelp() {
    Object.keys(helpText).forEach(function (id) {
      const field = document.getElementById(id);
      const label = field ? form.querySelector('label[for="' + id + '"]') : null;
      if (!label || label.querySelector('.notification-template-info')) return;
      const icon = document.createElement('i');
      icon.className = 'ri-information-line notification-template-info ms-1';
      icon.setAttribute('tabindex', '0');
      icon.setAttribute('role', 'button');
      icon.setAttribute('aria-label', helpText[id]);
      icon.setAttribute('data-bs-toggle', 'tooltip');
      icon.setAttribute('data-bs-placement', 'top');
      icon.setAttribute('data-bs-title', helpText[id]);
      icon.setAttribute('title', helpText[id]);
      label.appendChild(icon);
    });

    if (window.bootstrap && window.bootstrap.Tooltip) {
      form.querySelectorAll('[data-bs-toggle="tooltip"]').forEach(function (el) {
        window.bootstrap.Tooltip.getOrCreateInstance(el, {
          container: 'body',
          trigger: 'hover focus'
        });
      });
    }
  }


  function fillForm(row) {
    form.reset();
    document.getElementById('nt_template_id').value = row ? Number(row.f_templateID || 0) : 0;
    document.getElementById('nt_template_code').value = row ? (row.f_templateCode || '') : '';
    document.getElementById('nt_event_code').value = row ? (row.f_eventCode || '') : '';
    document.getElementById('nt_module_code').value = row ? (row.f_moduleCode || '') : '';
    document.getElementById('nt_type').value = row ? (row.f_type || 'event') : 'event';
    document.getElementById('nt_category').value = row ? (row.f_category || 'system') : 'system';
    document.getElementById('nt_severity').value = row ? (row.f_severity || 'info') : 'info';
    document.getElementById('nt_priority').value = row ? (row.f_priority || 'normal') : 'normal';
    document.getElementById('nt_title_ms').value = row ? (row.f_title_ms || '') : '';
    document.getElementById('nt_title_en').value = row ? (row.f_title_en || '') : '';
    document.getElementById('nt_body_ms').value = row ? (row.f_body_ms || '') : '';
    document.getElementById('nt_body_en').value = row ? (row.f_body_en || '') : '';
    document.getElementById('nt_action_label_ms').value = row ? (row.f_actionLabel_ms || '') : '';
    document.getElementById('nt_action_label_en').value = row ? (row.f_actionLabel_en || '') : '';
    setIconValue(row ? (row.f_icon || 'ri-notification-3-line') : 'ri-notification-3-line');
    document.getElementById('nt_placeholders').value = row ? (row.f_placeholders || '') : '';
    document.getElementById('nt_requires_action').checked = row ? Number(row.f_requiresAction || 0) === 1 : false;
    document.getElementById('nt_status').checked = row ? Number(row.f_status || 0) === 1 : true;
    updatePreview();
    upgradeFieldHelp();
  }

  function readForm() {
    const data = {};
    new FormData(form).forEach(function (value, key) {
      data[key] = value;
    });
    data.template_id = Number(document.getElementById('nt_template_id').value || 0);
    data.requires_action = document.getElementById('nt_requires_action').checked ? 1 : 0;
    data.status = document.getElementById('nt_status').checked ? 1 : 0;
    data.action = 'save';
    return data;
  }

  function post(payload) {
    return fetch(form.dataset.actionUrl, {
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
          throw new Error(data.message || data.error || <?= json_encode(ntpl('notification_template_action_failed','Template action failed.'),JSON_UNESCAPED_UNICODE) ?>);
        }
        return data;
      });
    });
  }

  function updateFromResponse(data) {
    if(templateDt&&templateDt.ajax)templateDt.ajax.reload(null,false);
    setAlert('success', data.message || <?= json_encode(ntpl('notification_template_done','Done.'),JSON_UNESCAPED_UNICODE) ?>);
  }

  function applyTemplateFilters() { if(templateDt&&templateDt.ajax)templateDt.ajax.reload(); }

  function updatePreview() {
    document.getElementById('nt_preview_title').textContent = document.getElementById('nt_title_ms').value || <?= json_encode(ntpl('notification_template_preview_empty', 'Template title preview'), JSON_UNESCAPED_UNICODE) ?>;
    document.getElementById('nt_preview_body').textContent = document.getElementById('nt_body_ms').value || '';
    setIconValue(document.getElementById('nt_icon').value || 'ri-notification-3-line');
  }

  document.getElementById('notificationTemplateCreateBtn').addEventListener('click', function () {
    clearAlert();
    fillForm(null);
    modal.show();
  });

  bodyEl.addEventListener('click', function (event) {
    const button = event.target.closest('button[data-action]');
    if (!button) return;
    const group = button.closest('[data-template-id]');
    if (!group) return;
    const row = findRecord(group.dataset.templateId);
    if (!row) {
      setAlert('danger', <?= json_encode(ntpl('notification_template_not_found','Template record not found.'),JSON_UNESCAPED_UNICODE) ?>);
      return;
    }
    const action = button.dataset.action;

    if (action === 'edit') {
      fillForm(row);
      modal.show();
      return;
    }

    if (action === 'delete') {
      if (Number(row.usage_count || 0) > 0) {
        setAlert('warning', <?= json_encode(ntpl('notification_template_in_use_help','Template is in use. Archive it instead.'),JSON_UNESCAPED_UNICODE) ?>);
        return;
      }
    }
    const confirmText=action==='delete'?labels.confirmDelete:<?= json_encode(ntpl('notification_template_action_confirm','Confirm this template action?'),JSON_UNESCAPED_UNICODE) ?>;
    const confirmation=window.Swal?window.Swal.fire({icon:action==='delete'?'warning':'question',title:confirmText,text:row.f_templateCode||'',showCancelButton:true,confirmButtonText:<?= json_encode(ntpl('notification_template_confirm','Confirm'),JSON_UNESCAPED_UNICODE) ?>,cancelButtonText:<?= json_encode(ntpl('common_cancel','Cancel'),JSON_UNESCAPED_UNICODE) ?>,focusCancel:action==='delete'}).then(function(result){return result.isConfirmed;}):Promise.resolve(window.confirm(confirmText));
    confirmation.then(function(confirmed){if(!confirmed)return;button.disabled=true;post({action:action,template_id:Number(row.f_templateID||0)}).then(updateFromResponse).catch(function(error){setAlert('danger',error.message);}).finally(function(){button.disabled=false;});});
  });

  form.addEventListener('input', updatePreview);
  form.addEventListener('change', updatePreview);
  modalEl.addEventListener('shown.bs.modal', upgradeFieldHelp);
  ['ntplTypeFilter','ntplPriorityFilter','ntplStatusFilter'].forEach(function(id){document.getElementById(id).addEventListener('change',applyTemplateFilters);});

  form.addEventListener('submit', function (event) {
    event.preventDefault();
    clearAlert();
    saveBtn.disabled = true;
    saveBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>' + labels.saving;
    post(readForm())
      .then(function (data) {
        updateFromResponse(data);
        modal.hide();
      })
      .catch(function (error) {
        setAlert('danger', error.message);
      })
      .finally(function () {
        saveBtn.disabled = false;
        saveBtn.innerHTML = '<i class="ri-save-line me-1"></i>' + labels.save;
      });
  });

  initServerTable();
  upgradeFieldHelp();
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initNotificationTemplatePage);
  } else {
    initNotificationTemplatePage();
  }
})();
</script>
</body>
</html>
