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
require_once __DIR__ . '/../classes/AiChatbotReviewDashboardService.php';

$pdo = Database::getInstance('mysql')->getConnection();
$permissionMessage = __('aiChatbotReview_permission_denied');
ensurePageGroupManagePermission(
    $pdo,
    ($permissionMessage === null || $permissionMessage === '' || $permissionMessage === 'aiChatbotReview_permission_denied')
        ? 'Anda tidak mempunyai kebenaran untuk melihat AI Chatbot Review Dashboard.'
        : (string)$permissionMessage
);

if (!function_exists('h')) {
    function h($value): string
    {
        return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
    }
}

function ai_review_badge_class(string $value, string $type = 'outcome'): string
{
    $value = strtolower(trim($value));
    if ($type === 'risk') {
        return match ($value) {
            'high' => 'bg-danger-subtle text-danger',
            'medium' => 'bg-warning-subtle text-warning',
            'low' => 'bg-success-subtle text-success',
            default => 'bg-secondary-subtle text-secondary',
        };
    }

    return match ($value) {
        'success', 'system_help', 'navigation_help' => 'bg-success-subtle text-success',
        'failed', 'timeout', 'sensitive_blocked' => 'bg-danger-subtle text-danger',
        'rate_limited', 'blocked', 'unknown' => 'bg-warning-subtle text-warning',
        default => 'bg-secondary-subtle text-secondary',
    };
}

function ai_review_label(string $key, string $fallback): string
{
    $value = __($key);
    return ($value === null || $value === '' || $value === $key) ? $fallback : (string)$value;
}

function ai_review_render_rows(array $rows, bool $showError = false): void
{
    foreach ($rows as $row): ?>
        <tr>
          <td>
            <div class="fw-semibold"><?= h((string)($row['created_at'] ?? '')) ?></div>
            <div class="small text-muted"><?= h((string)($row['login_id'] ?? '-')) ?></div>
          </td>
          <td>
            <span class="badge <?= h(ai_review_badge_class((string)($row['question_category'] ?? 'unknown'))) ?>"><?= h((string)($row['question_category'] ?? 'unknown')) ?></span>
            <span class="badge <?= h(ai_review_badge_class((string)($row['question_risk'] ?? 'unknown'), 'risk')) ?>"><?= h((string)($row['question_risk'] ?? 'unknown')) ?></span>
            <?php if (!empty($row['blocked_detail'])): ?><span class="badge bg-danger text-white">blocked detail</span><?php endif; ?>
          </td>
          <td>
            <div><?= h((string)($row['question_review_reason'] ?? '')) ?></div>
            <div class="small text-muted"><?= h((string)($row['current_page_path'] ?? '')) ?></div>
          </td>
          <td>
            <div><?= h((string)($row['provider'] ?? 'unknown')) ?></div>
            <div class="small text-muted"><?= h((string)($row['model'] ?? 'unknown')) ?></div>
          </td>
          <td>
            <span class="badge <?= h(ai_review_badge_class((string)($row['outcome'] ?? 'unknown'))) ?>"><?= h((string)($row['outcome'] ?? 'unknown')) ?></span>
            <div class="small text-muted"><?= h((string)((int)($row['latency_ms'] ?? 0))) ?> ms</div>
          </td>
          <td>
            <div>Knowledge: <?= h((string)((int)($row['knowledge_items_in_prompt'] ?? 0))) ?></div>
            <div class="small text-muted">Msg len: <?= h((string)((int)($row['message_length'] ?? 0))) ?></div>
            <?php if (!empty($row['project_provider_label']) || !empty($row['project_provider'])): ?>
              <div class="mt-1">Project: <span class="fw-semibold"><?= h((string)($row['project_provider_label'] ?: $row['project_provider'])) ?></span></div>
              <div class="small text-muted">Intent: <?= h((string)($row['project_intent'] ?? '')) ?> • Rows: <?= h((string)((int)($row['project_row_count'] ?? 0))) ?></div>
            <?php elseif (!empty($row['project_denied_reason'])): ?>
              <div class="mt-1"><span class="text-danger small">Project context denied: <?= h((string)($row['project_denied_reason'])) ?></span></div>
            <?php endif; ?>
          </td>
          <?php if ($showError): ?>
            <td>
              <div><?= h((string)($row['error_code'] ?? '')) ?></div>
              <div class="small text-muted"><?= h((string)($row['error_message'] ?? '')) ?></div>
            </td>
          <?php endif; ?>
        </tr>
    <?php endforeach;
}

$days = (int)($_GET['days'] ?? 30);
$limit = (int)($_GET['limit'] ?? 500);
$service = new AiChatbotReviewDashboardService($pdo);
$dashboard = $service->build($days, $limit);
$summary = $dashboard['summary'];
$lang = (string)($_SESSION['lang'] ?? 'ms');
$version = (string)($_ENV['APP_ASSET_VER'] ?? date('ymdHis'));
$PAGE_TITLE = ai_review_label('aiChatbotReview_page_title', 'AI Chatbot Review Dashboard');
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
  <link href="<?= h(base_url('assets/css/datatables-standard.css')) ?>?v=<?= h($version) ?>" rel="stylesheet">
  <script src="<?= h(base_url('assets/js/helpers/datatables-standard.js')) ?>?v=<?= h($version) ?>"></script>
  <style>
    .ai-review-page { width: 100%; }
    .ai-review-card {
      border: 1px solid rgba(15,23,42,.08);
      border-radius: 8px;
      box-shadow: 0 8px 24px rgba(15,23,42,.06);
      background: rgba(255,255,255,.98);
    }
    .ai-review-icon {
      width: 36px;
      height: 36px;
      border-radius: 8px;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      flex: 0 0 auto;
    }
    .ai-review-stat {
      border: 1px solid rgba(15,23,42,.08);
      border-radius: 8px;
      padding: .72rem .82rem;
      background: rgba(255,255,255,.98);
      min-height: 78px;
      box-shadow: 0 6px 18px rgba(15,23,42,.045);
    }
    .ai-review-stat-label {
      color: #64748b;
      font-size: .72rem;
      font-weight: 800;
      letter-spacing: .04em;
      line-height: 1.1;
      text-transform: uppercase;
    }
    .ai-review-stat-value {
      color: #0f172a;
      font-size: 1.38rem;
      font-weight: 800;
      line-height: 1.05;
      margin-top: .28rem;
    }
    .ai-review-page .btn {
      border-radius: 8px !important;
      font-weight: 700;
    }
    .ai-review-page .btn-sm {
      min-height: 38px;
      padding: .45rem .72rem;
      font-size: .875rem;
    }
    .ai-review-page .form-control,
    .ai-review-page .form-select {
      border-radius: 8px;
      border-color: #dbe4f0;
      min-height: 38px;
      font-size: .875rem;
    }
    .ai-review-page .form-control-sm,
    .ai-review-page .form-select-sm {
      min-height: 38px;
      padding: .45rem .72rem;
      font-size: .875rem;
    }
    .ai-review-page .form-label {
      margin-bottom: .35rem;
      color: #334155;
      font-size: .8rem;
    }
    .ai-review-card .card-header {
      padding: .9rem 1.15rem;
      border-bottom: 1px solid rgba(148, 163, 184, .16);
      background: linear-gradient(180deg, rgba(248,250,252,.95) 0%, rgba(255,255,255,.98) 100%);
    }
    .ai-review-card .card-body { padding: 1rem 1.15rem; }
    .ai-review-tabs {
      gap: .35rem;
      border-bottom: 1px solid rgba(148, 163, 184, .18);
    }
    .ai-review-tabs .nav-link {
      border: 0;
      border-radius: 8px 8px 0 0;
      color: #475569;
      font-size: .875rem;
      font-weight: 700;
      padding: .7rem .95rem;
    }
    .ai-review-tabs .nav-link.active {
      color: #1d4ed8;
      background: rgba(59, 130, 246, .1);
    }
    .ai-review-tab-pane { padding-top: 1rem; }
    .ai-review-tab-heading {
      display: flex;
      align-items: flex-start;
      justify-content: space-between;
      gap: 1rem;
      margin-bottom: .85rem;
    }
    .ai-review-tab-heading h5 { margin-bottom: .2rem; }
    .ai-review-table th,
    .ai-review-table td,
    .ai-review-table.table > :not(caption) > * > * { vertical-align: top !important; }
    .ai-review-table {
      width: 100%;
      border-radius: 8px;
      overflow: hidden;
      border: 1px solid rgba(148, 163, 184, .14);
      background: rgba(255, 255, 255, .96);
      margin-bottom: .15rem !important;
    }
    .ai-review-table thead th {
      font-weight: 700;
      font-size: .78rem;
      text-transform: uppercase;
      letter-spacing: .05em;
      padding: .82rem .78rem;
      border: 0;
      border-bottom: 1px solid rgba(148, 163, 184, .16);
      color: #334155;
      background: linear-gradient(180deg, rgba(248,250,252,.98) 0%, rgba(241,245,249,.95) 100%);
    }
    .ai-review-table tbody td {
      padding: .82rem .78rem;
      border-color: rgba(226,232,240,.9);
      font-size: .845rem;
    }
    .ai-review-table tbody tr {
      background-color: transparent !important;
      transition: background-color .18s ease, box-shadow .18s ease;
    }
    .ai-review-table tbody tr:hover {
      background: rgba(241,245,249,.88) !important;
      box-shadow: inset 0 0 0 999px rgba(241,245,249,.3);
    }
    .ai-review-table-wrap .dataTables_wrapper {
      padding: 0 .45rem .15rem;
    }
    .ai-review-table-wrap .dataTables_wrapper .row.mb-2 {
      align-items: center;
      margin-bottom: .55rem !important;
    }
    .ai-review-table-wrap .dataTables_wrapper .dt-top-left,
    .ai-review-table-wrap .dataTables_wrapper .dt-top-right {
      top: 0 !important;
      padding-left: .55rem !important;
      padding-right: .55rem !important;
      transform: translateY(8px);
    }
    .ai-review-table-wrap .dataTables_wrapper .dataTables_filter {
      text-align: right;
      padding-right: .3rem;
      transform: none;
    }
    .ai-review-table-wrap .dataTables_wrapper .dataTables_filter label {
      display: inline-flex;
      align-items: center;
      gap: .45rem;
      margin-bottom: 0;
      font-size: .875rem;
    }
    .ai-review-table-wrap .dataTables_wrapper .dataTables_filter input {
      border: 2px solid #e9ecef;
      border-radius: 8px;
      min-height: 36px;
      padding: .45rem .72rem;
      font-size: .875rem;
      max-width: calc(100vw - 5rem);
    }
    .ai-review-table-wrap .dataTables_wrapper .dataTables_length label {
      display: inline-flex !important;
      align-items: center;
      gap: .4rem;
      margin-bottom: 0;
      white-space: nowrap !important;
      font-size: .875rem !important;
    }
    .ai-review-table-wrap .dataTables_wrapper .dataTables_length select {
      height: 36px !important;
      min-height: 36px !important;
      min-width: 70px !important;
      margin: 0 .35rem !important;
      border: 2px solid #e9ecef !important;
      border-radius: 8px !important;
      font-size: .875rem !important;
    }
    .ai-review-table-wrap .dataTables_wrapper .dataTables_info {
      margin: 0;
      color: var(--bs-secondary-color);
      font-size: .875rem;
      line-height: 1.5;
      white-space: nowrap;
    }
    .ai-review-table-wrap .dataTables_wrapper .dt-bottom-row {
      align-items: center !important;
      margin-top: 0 !important;
      padding: 0 .65rem 0 !important;
      transform: translateY(7px);
    }
    .ai-review-table-wrap .dataTables_wrapper .dt-bottom-row > .dt-info-left {
      padding-left: .15rem !important;
      padding-right: .5rem !important;
    }
    .ai-review-table-wrap .dataTables_wrapper .dt-bottom-row > .dt-paging-right {
      top: 0 !important;
      padding-right: .15rem !important;
    }
    .ai-review-table-wrap .dataTables_wrapper .dataTables_paginate {
      margin-top: 0 !important;
      padding-top: 0 !important;
    }
    .ai-review-table-wrap .dataTables_wrapper .dataTables_paginate .pagination {
      margin: 0 !important;
    }
    .ai-review-list { display: grid; gap: .55rem; }
    .ai-review-list-item { display: flex; align-items: center; justify-content: space-between; gap: .75rem; border-bottom: 1px solid var(--bs-border-color); padding-bottom: .5rem; }
    .ai-review-list-item:last-child { border-bottom: 0; padding-bottom: 0; }
    html[data-bs-theme="dark"] .ai-review-card .card-header {
      background: linear-gradient(180deg, rgba(30,41,59,.96) 0%, rgba(15,23,42,.94) 100%);
    }
    html[data-bs-theme="dark"] .ai-review-card,
    html[data-bs-theme="dark"] .ai-review-stat {
      background: rgba(15,23,42,.96);
      border-color: rgba(148,163,184,.18);
    }
    html[data-bs-theme="dark"] .ai-review-stat-value {
      color: #f8fafc;
    }
    html[data-bs-theme="dark"] .ai-review-table {
      background: rgba(15,23,42,.96);
      border-color: rgba(148,163,184,.18);
    }
    html[data-bs-theme="dark"] .ai-review-table thead th {
      background: linear-gradient(180deg, rgba(30,41,59,.96) 0%, rgba(15,23,42,.94) 100%);
      color: #dbe4f0;
      border-bottom-color: rgba(148,163,184,.18);
    }
    html[data-bs-theme="dark"] .ai-review-table tbody tr:hover {
      background: rgba(30,41,59,.76) !important;
      box-shadow: inset 0 0 0 999px rgba(30,41,59,.18);
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
              <h4 class="page-title"><i class="ri-shield-search-line me-1"></i><?= h($PAGE_TITLE) ?></h4>
              <div class="page-title-right">
                <ol class="breadcrumb m-0">
                  <li class="breadcrumb-item"><a href="<?= h(base_url('pages/dashboard.php')) ?>"><?= h(ai_review_label('common_dashboard', 'Dashboard')) ?></a></li>
                  <li class="breadcrumb-item"><a href="<?= h(base_url('pages/tetapan-sistem.php?tab=ai-chatbot')) ?>"><?= h(ai_review_label('config_ai_chatbot_title', 'AI Chatbot')) ?></a></li>
                  <li class="breadcrumb-item active"><?= h(ai_review_label('aiChatbotReview_table_review', 'Review')) ?></li>
                </ol>
              </div>
            </div>
          </div>
        </div>

        <div class="ai-review-page">
        <div class="card ai-review-card mb-3">
          <div class="card-body d-flex justify-content-between align-items-end flex-wrap gap-3">
            <div>
              <h5 class="card-title mb-1">Governance Snapshot</h5>
              <p class="text-muted mb-0">Paparan ini menggunakan metadata review-safe sahaja dan tidak memerlukan raw message content.</p>
            </div>
            <form class="d-flex flex-wrap align-items-end gap-2" method="get">
              <div>
                <label class="form-label small fw-semibold" for="review_days"><?= h(ai_review_label('aiChatbotReview_filter_days', 'Days')) ?></label>
                <input class="form-control form-control-sm" type="number" min="1" max="365" id="review_days" name="days" value="<?= h((string)$dashboard['days']) ?>">
              </div>
              <div>
                <label class="form-label small fw-semibold" for="review_limit"><?= h(ai_review_label('aiChatbotReview_filter_rows', 'Rows')) ?></label>
                <input class="form-control form-control-sm" type="number" min="50" max="2000" id="review_limit" name="limit" value="<?= h((string)$dashboard['limit']) ?>">
              </div>
              <button class="btn btn-sm btn-primary" type="submit"><i class="ri-filter-3-line me-1"></i><?= h(ai_review_label('aiChatbotReview_filter_apply', 'Apply')) ?></button>
              <a class="btn btn-sm btn-outline-secondary" href="<?= h(base_url('pages/ai-chatbot-knowledge.php')) ?>"><i class="ri-chat-quote-line me-1"></i><?= h(ai_review_label('aiChatbotReview_knowledge_manager', 'Knowledge Manager')) ?></a>
            </form>
          </div>
        </div>

        <div class="row g-2 mb-3">
          <?php foreach ([
            'total' => [ai_review_label('aiChatbotReview_summary_total_requests', 'Total Requests'), 'ri-chat-3-line', 'text-primary', 'bg-primary-subtle'],
            'success' => ['Success', 'ri-checkbox-circle-line', 'text-success', 'bg-success-subtle'],
            'failed' => ['Failed/Blocked', 'ri-error-warning-line', 'text-danger', 'bg-danger-subtle'],
            'needs_review' => [ai_review_label('aiChatbotReview_summary_review_queue', 'Needs Review'), 'ri-search-eye-line', 'text-warning', 'bg-warning-subtle'],
            'no_knowledge' => [ai_review_label('aiChatbotReview_summary_no_knowledge', 'No Knowledge'), 'ri-question-line', 'text-info', 'bg-info-subtle'],
            'avg_latency_ms' => ['Avg Latency ms', 'ri-timer-flash-line', 'text-secondary', 'bg-secondary-subtle'],
          ] as $key => $meta): ?>
            <div class="col-sm-6 col-xl-2">
              <div class="ai-review-stat d-flex align-items-center justify-content-between gap-2">
                <div>
                  <div class="ai-review-stat-label"><?= h($meta[0]) ?></div>
                  <div class="ai-review-stat-value"><?= h((string)($summary[$key] ?? 0)) ?></div>
                </div>
                <span class="ai-review-icon <?= h($meta[3]) ?> <?= h($meta[2]) ?>">
                  <i class="<?= h($meta[1]) ?> fs-5"></i>
                </span>
              </div>
            </div>
          <?php endforeach; ?>
        </div>

        <div class="row g-3 mb-3">
          <div class="col-xl-4">
            <div class="card ai-review-card h-100">
              <div class="card-body">
                <h5 class="card-title">Question Categories</h5>
                <div class="ai-review-list">
                  <?php foreach ($dashboard['categories'] as $item): ?>
                    <div class="ai-review-list-item"><span><?= h($item['label']) ?></span><span class="badge <?= h(ai_review_badge_class((string)$item['label'])) ?>"><?= h((string)$item['total']) ?></span></div>
                  <?php endforeach; ?>
                  <?php if ($dashboard['categories'] === []): ?><div class="text-muted small">Tiada data.</div><?php endif; ?>
                </div>
              </div>
            </div>
          </div>
          <div class="col-xl-4">
            <div class="card ai-review-card h-100">
              <div class="card-body">
                <h5 class="card-title">Outcomes</h5>
                <div class="ai-review-list">
                  <?php foreach ($dashboard['outcomes'] as $item): ?>
                    <div class="ai-review-list-item"><span><?= h($item['label']) ?></span><span class="badge <?= h(ai_review_badge_class((string)$item['label'])) ?>"><?= h((string)$item['total']) ?></span></div>
                  <?php endforeach; ?>
                  <?php if ($dashboard['outcomes'] === []): ?><div class="text-muted small">Tiada data.</div><?php endif; ?>
                </div>
              </div>
            </div>
          </div>
          <div class="col-xl-4">
            <div class="card ai-review-card h-100">
              <div class="card-body">
                <h5 class="card-title">Provider Latency</h5>
                <div class="ai-review-list">
                  <?php foreach (array_slice($dashboard['provider_latency'], 0, 8) as $item): ?>
                    <div class="ai-review-list-item">
                      <span><?= h($item['label']) ?><span class="text-muted small d-block"><?= h((string)$item['total']) ?> requests, <?= h((string)$item['failed']) ?> failed</span></span>
                      <span class="badge bg-info-subtle text-info"><?= h((string)$item['avg_latency_ms']) ?> ms</span>
                    </div>
                  <?php endforeach; ?>
                  <?php if ($dashboard['provider_latency'] === []): ?><div class="text-muted small">Tiada data.</div><?php endif; ?>
                </div>
              </div>
            </div>
          </div>
        </div>

        <div class="card ai-review-card">
          <div class="card-header">
            <ul class="nav nav-tabs ai-review-tabs" id="aiReviewTableTabs" role="tablist">
              <li class="nav-item" role="presentation">
                <button class="nav-link active" id="review-queue-tab" data-bs-toggle="tab" data-bs-target="#review-queue-pane" type="button" role="tab" aria-controls="review-queue-pane" aria-selected="true">
                  <i class="ri-search-eye-line me-1"></i><?= h(ai_review_label('aiChatbotReview_tab_review_queue', 'Review Queue')) ?>
                </button>
              </li>
              <li class="nav-item" role="presentation">
                <button class="nav-link" id="no-knowledge-tab" data-bs-toggle="tab" data-bs-target="#no-knowledge-pane" type="button" role="tab" aria-controls="no-knowledge-pane" aria-selected="false">
                  <i class="ri-question-line me-1"></i><?= h(ai_review_label('aiChatbotReview_tab_no_knowledge', 'No Knowledge')) ?>
                </button>
              </li>
              <li class="nav-item" role="presentation">
                <button class="nav-link" id="provider-failures-tab" data-bs-toggle="tab" data-bs-target="#provider-failures-pane" type="button" role="tab" aria-controls="provider-failures-pane" aria-selected="false">
                  <i class="ri-error-warning-line me-1"></i><?= h(ai_review_label('aiChatbotReview_tab_provider_failures', 'Provider Failures')) ?>
                </button>
              </li>
            </ul>
          </div>
          <div class="card-body">
            <div class="tab-content" id="aiReviewTableTabsContent">
              <div class="tab-pane fade show active ai-review-tab-pane" id="review-queue-pane" role="tabpanel" aria-labelledby="review-queue-tab" tabindex="0">
                <div class="ai-review-tab-heading">
                  <div>
                    <h5 class="card-title"><?= h(ai_review_label('aiChatbotReview_tab_review_queue', 'Review Queue')) ?></h5>
                    <p class="text-muted mb-0">Unknown, sensitive blocked, blocked detail, atau item yang classifier tandakan perlu semakan.</p>
                  </div>
                </div>
                <div class="table-responsive ai-review-table-wrap">
                  <table class="table table-striped ai-review-table js-ai-review-table" id="aiReviewQueueTable">
                    <thead><tr><th><?= h(ai_review_label('aiChatbotReview_table_time', 'Time')) ?>/<?= h(ai_review_label('aiChatbotReview_table_user', 'User')) ?></th><th>Category/Risk</th><th>Reason/Page</th><th><?= h(ai_review_label('aiChatbotReview_table_provider', 'Provider')) ?></th><th><?= h(ai_review_label('aiChatbotReview_table_outcome', 'Outcome')) ?></th><th>Prompt Signals</th></tr></thead>
                    <tbody><?php ai_review_render_rows($dashboard['review_items']); ?></tbody>
                  </table>
                </div>
              </div>

              <div class="tab-pane fade ai-review-tab-pane" id="no-knowledge-pane" role="tabpanel" aria-labelledby="no-knowledge-tab" tabindex="0">
                <div class="ai-review-tab-heading">
                  <div>
                    <h5 class="card-title"><?= h(ai_review_label('aiChatbotReview_tab_no_knowledge', 'No Knowledge')) ?></h5>
                    <p class="text-muted mb-0">System-specific requests yang berjaya tetapi tiada curated knowledge item dalam prompt. Ini calon terbaik untuk ditukar menjadi artikel knowledge.</p>
                  </div>
                </div>
                <div class="table-responsive ai-review-table-wrap">
                  <table class="table table-striped ai-review-table js-ai-review-table" id="aiNoKnowledgeTable">
                    <thead><tr><th><?= h(ai_review_label('aiChatbotReview_table_time', 'Time')) ?>/<?= h(ai_review_label('aiChatbotReview_table_user', 'User')) ?></th><th>Category/Risk</th><th>Reason/Page</th><th><?= h(ai_review_label('aiChatbotReview_table_provider', 'Provider')) ?></th><th><?= h(ai_review_label('aiChatbotReview_table_outcome', 'Outcome')) ?></th><th>Prompt Signals</th></tr></thead>
                    <tbody><?php ai_review_render_rows($dashboard['no_knowledge']); ?></tbody>
                  </table>
                </div>
              </div>

              <div class="tab-pane fade ai-review-tab-pane" id="provider-failures-pane" role="tabpanel" aria-labelledby="provider-failures-tab" tabindex="0">
                <div class="ai-review-tab-heading">
                  <div>
                    <h5 class="card-title"><?= h(ai_review_label('aiChatbotReview_tab_provider_failures', 'Provider Failures')) ?></h5>
                    <p class="text-muted mb-0">Failed, timeout, rate-limited, dan blocked outcomes untuk semakan provider/model/limit.</p>
                  </div>
                </div>
                <div class="table-responsive ai-review-table-wrap">
                  <table class="table table-striped ai-review-table js-ai-review-table" id="aiProviderFailuresTable">
                    <thead><tr><th><?= h(ai_review_label('aiChatbotReview_table_time', 'Time')) ?>/<?= h(ai_review_label('aiChatbotReview_table_user', 'User')) ?></th><th>Category/Risk</th><th>Reason/Page</th><th><?= h(ai_review_label('aiChatbotReview_table_provider', 'Provider')) ?></th><th><?= h(ai_review_label('aiChatbotReview_table_outcome', 'Outcome')) ?></th><th>Prompt Signals</th><th>Error</th></tr></thead>
                    <tbody><?php ai_review_render_rows($dashboard['failures'], true); ?></tbody>
                  </table>
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
<script>
document.addEventListener('DOMContentLoaded', function () {
  if (window.jQuery && jQuery.fn && jQuery.fn.DataTable) {
    var reviewDom = '<"row mb-2"<"col-sm-12 col-md-6 dt-top-left"l><"col-sm-12 col-md-6 d-flex justify-content-md-end dt-top-right"f>>' +
      't' +
      '<"dt-bottom-row mt-1 d-flex justify-content-between align-items-center"<"dt-info-left"i><"dt-paging-right d-flex justify-content-end"p>>';
    var tableOptions = window.DataTableStandard
      ? window.DataTableStandard.options({
          dom: reviewDom,
          pageLength: 10,
          order: [],
          responsive: true,
          searchPlaceholder: <?= json_encode(ai_review_label('aiChatbotReview_search_placeholder', 'Search review...'), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>
        })
      : {
          dom: reviewDom,
          pageLength: 10,
          order: [],
          responsive: true
        };
    var reviewTables = [];

    jQuery('.js-ai-review-table').each(function () {
      var table = jQuery(this);
      var instance = table.DataTable(tableOptions);
      reviewTables.push(instance);

      if (window.DataTableStandard) {
        window.DataTableStandard.decorate(table, {
          searchPlaceholder: <?= json_encode(ai_review_label('aiChatbotReview_search_placeholder', 'Search review...'), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>
        });
      }
    });

    document.querySelectorAll('#aiReviewTableTabs [data-bs-toggle="tab"]').forEach(function (tab) {
      tab.addEventListener('shown.bs.tab', function () {
        reviewTables.forEach(function (table) {
          table.columns.adjust();
          if (table.responsive && typeof table.responsive.recalc === 'function') {
            table.responsive.recalc();
          }
        });
      });
    });
  }
});
</script>
</body>
</html>
