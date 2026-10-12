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

$pdoPerm = Database::getInstance('mysql')->getConnection();
ensurePageGroupManagePermission($pdoPerm);

require_once __DIR__ . '/../controllers/EmailTemplateController.php';
$controller = null;
$bootstrapError = null;
try {
    $controller = new EmailTemplateController();
} catch (Throwable $e) {
    error_log('[template-emel] Bootstrap failure: ' . $e->getMessage());
    $bootstrapError = (string)__('emailTemplate_error_bootstrap');
    if ($bootstrapError === 'emailTemplate_error_bootstrap' || $bootstrapError === '') {
        $bootstrapError = 'Unable to load email template management at this time.';
    }
}

if (!function_exists('h')) {
    function h($value): string
    {
        return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('t')) {
    function t(string $key, string $fallback): string
    {
        $value = __($key);
        return ($value === $key || $value === null || $value === '') ? $fallback : (string)$value;
    }
}

$lang = (string)($_SESSION['lang'] ?? 'ms');
$version = (string)($_ENV['APP_ASSET_VER'] ?? filemtime(__FILE__) ?: '1');
$PAGE_TITLE = t('emailTemplate_page_title', 'Template Emel');
$records = $controller->records ?? [];
$csrf = $controller->csrf ?? (string)($_SESSION['csrf_token'] ?? '');
$form = $controller->form ?? [];
$fieldErrors = $controller->fieldErrors ?? [];
$errorMessage = $controller->errorMessage ?? null;
$successMessage = $controller->successMessage ?? null;
$filters = $controller->filters ?? [];
$summary = $controller->summary ?? ['total' => 0, 'active' => 0, 'draft' => 0, 'archived' => 0];
$usageCounts = $controller->usageCounts ?? [];
$roleOptions = $controller->roleOptions ?? [];
$categoryOptions = $controller->categoryOptions ?? [];
$statusOptions = $controller->statusOptions ?? [];
$generalPlaceholders = $controller->generalPlaceholders ?? [];
$seedTemplates = $controller->seedTemplates ?? [];
$shouldOpenModal = (bool)($controller->shouldOpenModal ?? false);
$hasActiveFilters = trim((string)($filters['role_code'] ?? '')) !== ''
    || trim((string)($filters['category_code'] ?? '')) !== ''
    || trim((string)($filters['status'] ?? '')) !== ''
    || trim((string)($filters['search'] ?? '')) !== '';

if ($bootstrapError !== null && $errorMessage === null) {
    $errorMessage = $bootstrapError;
}

$groupedPlaceholders = [];
foreach ($generalPlaceholders as $placeholder) {
    $groupKey = (string)($placeholder['f_placeholderGroup'] ?? 'system');
    $groupedPlaceholders[$groupKey][] = $placeholder;
}
$sampleVariablesDefault = [
    'recipient_name' => 'Ali bin Abu',
    'recipient_email' => 'ali@example.com',
    'recipient_role' => 'student',
    'recipient_position' => 'Pelajar',
    'recipient_department' => 'Fakulti Kejuruteraan',
    'organization_name' => 'Universiti Contoh',
    'organization_short' => 'UC',
    'system_name' => 'Sistem HEPA',
    'support_email' => 'support@example.com',
    'sender_name' => 'Pentadbir Sistem',
    'current_date' => date('d F Y'),
    'current_datetime' => date('d F Y h:i A'),
    'current_year' => date('Y'),
    'program_name' => 'Bantuan Pelajar',
    'application_no' => 'APP-2026-001',
    'approval_date' => date('d F Y'),
    'semester' => 'Semester 2',
];
foreach ($generalPlaceholders as $placeholder) {
    $placeholderKey = trim((string)($placeholder['f_placeholderKey'] ?? ''));
    if ($placeholderKey === '') {
        continue;
    }
    $sampleValue = trim((string)($placeholder['f_sampleValue'] ?? ''));
    if ($sampleValue !== '') {
        $sampleVariablesDefault[$placeholderKey] = $sampleValue;
        continue;
    }
    if (!array_key_exists($placeholderKey, $sampleVariablesDefault)) {
        $sampleVariablesDefault[$placeholderKey] = 'Sample ' . $placeholderKey;
    }
}
$sampleVariablesJson = json_encode($sampleVariablesDefault, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
if ($sampleVariablesJson === false) {
    $sampleVariablesJson = '{}';
}
$defaultPlaceholderDocs = [
    ['key' => 'recipient_name', 'source' => 'context'],
    ['key' => 'recipient_email', 'source' => 'context'],
    ['key' => 'recipient_role', 'source' => 'context'],
    ['key' => 'recipient_position', 'source' => 'context'],
    ['key' => 'recipient_department', 'source' => 'context'],
    ['key' => 'organization_name', 'source' => 'system'],
    ['key' => 'organization_short', 'source' => 'system'],
    ['key' => 'system_name', 'source' => 'system'],
    ['key' => 'support_email', 'source' => 'system'],
    ['key' => 'sender_name', 'source' => 'system'],
    ['key' => 'current_date', 'source' => 'system'],
    ['key' => 'current_datetime', 'source' => 'system'],
    ['key' => 'current_year', 'source' => 'system'],
];
$generalPlaceholderDocs = [];
foreach ($generalPlaceholders as $placeholder) {
    $placeholderKey = trim((string)($placeholder['f_placeholderKey'] ?? ''));
    if ($placeholderKey === '') {
        continue;
    }
    $generalPlaceholderDocs[$placeholderKey] = [
        'description' => (string)($placeholder['f_description'] ?? ''),
        'sample' => (string)($placeholder['f_sampleValue'] ?? ''),
        'group' => (string)($placeholder['f_placeholderGroup'] ?? 'system'),
    ];
}
?>
<!DOCTYPE html>
<html lang="<?= h($lang) ?>" data-bs-theme="<?= h($_SESSION['theme.layout'] ?? 'light') ?>">
<head>
    <?php include __DIR__ . '/../includes/head.php'; ?>
    <meta name="csrf-token" content="<?= h($csrf) ?>">
    <meta name="referrer" content="same-origin">
    <link href="<?= h(base_url('assets/css/datatables-standard.css')) ?>?v=<?= h($version) ?>" rel="stylesheet">
    <link href="<?= h(base_url('assets/css/pages/template-emel.css')) ?>?v=<?= h($version) ?>" rel="stylesheet">
    <link href="<?= h(base_url('assets/css/pages/template-emel-refresh.css')) ?>?v=<?= h($version) ?>" rel="stylesheet">
    <script src="<?= h(base_url('assets/js/helpers/datatables-standard.js')) ?>?v=<?= h($version) ?>"></script>
</head>
<body data-topbar-color="<?= h($_SESSION['theme.topbar'] ?? 'light') ?>"
      data-menu-color="<?= h($_SESSION['theme.menu'] ?? 'light') ?>"
      data-layout="vertical"
      data-sidebar-size="default"
      class="loading">
<div class="wrapper">
    <?php include __DIR__ . '/../includes/topbar.php'; ?>
    <?php include __DIR__ . '/../includes/sidebar.php'; ?>
    <div class="content-page">
        <div class="content">
            <div class="container-fluid">
                <div class="row">
                    <div class="col-12">
                        <div class="page-title-box d-sm-flex align-items-center justify-content-between">
                            <h4 class="mb-sm-0 email-template-page-title"><i class="ri-mail-settings-line"></i><?= h(t('emailTemplate_page_title', 'Template Emel')) ?></h4>
                            <div class="page-title-right">
                                <ol class="breadcrumb m-0">
                                    <li class="breadcrumb-item"><a href="<?= h(base_url('pages/dashboard.php')) ?>"><?= h(t('common_dashboard', 'Dashboard')) ?></a></li>
                                    <li class="breadcrumb-item active"><?= h(t('emailTemplate_page_title', 'Template Emel')) ?></li>
                                </ol>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card et-hero-card mb-2">
                    <div class="card-body d-flex flex-wrap align-items-center justify-content-between gap-3">
                        <div class="et-hero-copy">
                            <span class="et-hero-kicker"><?= h(t('emailTemplate_hero_kicker', 'Email Template Workspace')) ?></span>
                            <h5 class="mb-1"><?= h(t('emailTemplate_hero_title', 'Satu modul umum untuk urus template emel, preview render, dan serah terus kepada developer.')) ?></h5>
                            <p class="text-muted mb-0"><?= h(t('emailTemplate_hero_subtitle', 'Gunakan seed template sebagai titik mula, kemudian laras placeholder dan kandungan ikut flow sistem anda.')) ?></p>
                        </div>
                        <div class="et-hero-actions">
                            <div class="et-hero-governance"><i class="ri-shield-check-line"></i><div><strong><?= h(t('emailTemplate_governance_title', 'Controlled email content')) ?></strong><span><?= h(t('emailTemplate_governance_text', 'Preview placeholders and verify recipients before sending.')) ?></span></div></div>
                            <?php if ($seedTemplates !== []): ?>
                                <form method="post" action="" class="d-inline-block" data-template-action-form="seed_templates">
                                    <input type="hidden" name="csrf_token" value="<?= h($csrf) ?>">
                                    <input type="hidden" name="form_action" value="seed_templates">
                                    <button type="button" class="btn btn-outline-primary et-seed-btn" data-template-action-button>
                                        <i class="ri-seedling-line me-1"></i><?= h(t('emailTemplate_btn_seed_templates', 'Import Seed Templates')) ?>
                                    </button>
                                </form>
                            <?php endif; ?>
                            <button type="button" class="btn btn-primary et-primary-btn" data-create-template onclick="window.EmailTemplateFallback && window.EmailTemplateFallback.openCreate();">
                                <i class="ri-add-line me-1"></i><?= h(t('emailTemplate_action_create', 'Tambah Template')) ?>
                            </button>
                        </div>
                    </div>
                </div>

                <div class="row g-3 et-summary-grid">
                    <div class="col-12 col-md-6 col-xl-3">
                        <div class="card et-summary-card et-summary-total h-100">
                            <div class="card-body">
                                <span class="et-summary-label"><?= h(t('emailTemplate_summary_total', 'Jumlah Template')) ?></span>
                                <div class="et-summary-value" data-summary-value="total"><?= (int)($summary['total'] ?? 0) ?></div>
                            </div>
                        </div>
                    </div>
                    <div class="col-12 col-md-6 col-xl-3">
                        <div class="card et-summary-card et-summary-active h-100">
                            <div class="card-body">
                                <span class="et-summary-label"><?= h(t('emailTemplate_summary_active', 'Aktif')) ?></span>
                                <div class="et-summary-value" data-summary-value="active"><?= (int)($summary['active'] ?? 0) ?></div>
                            </div>
                        </div>
                    </div>
                    <div class="col-12 col-md-6 col-xl-3">
                        <div class="card et-summary-card et-summary-draft h-100">
                            <div class="card-body">
                                <span class="et-summary-label"><?= h(t('emailTemplate_summary_draft', 'Draf')) ?></span>
                                <div class="et-summary-value" data-summary-value="draft"><?= (int)($summary['draft'] ?? 0) ?></div>
                            </div>
                        </div>
                    </div>
                    <div class="col-12 col-md-6 col-xl-3">
                        <div class="card et-summary-card et-summary-archived h-100">
                            <div class="card-body">
                                <span class="et-summary-label"><?= h(t('emailTemplate_summary_archived', 'Arkib')) ?></span>
                                <div class="et-summary-value" data-summary-value="archived"><?= (int)($summary['archived'] ?? 0) ?></div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row g-0 et-content-grid">
                    <div class="col-12">
                        <div class="card et-shell-card">
                            <div class="card-body d-flex flex-wrap justify-content-between align-items-center gap-3">
                                <div>
                                    <h5 class="card-title mb-1"><?= h(t('emailTemplate_list_title', 'Senarai Template Emel')) ?></h5>
                                    <p class="text-muted mb-0"><?= h(t('emailTemplate_list_subtitle', 'Urus template emel umum mengikut peranan dan kategori.')) ?></p>
                                </div>
                                <div class="et-inline-metrics">
                                    <span class="et-inline-chip"><?= h(t('emailTemplate_inline_general_placeholders', 'General placeholders')) ?>: <?= count($generalPlaceholders) ?></span>
                                    <span class="et-inline-chip"><?= h(t('emailTemplate_inline_seed_templates', 'Seed templates')) ?>: <?= count($seedTemplates) ?></span>
                                    <button type="button" class="et-inline-chip et-inline-chip-button <?= $hasActiveFilters ? 'is-active' : '' ?>" data-filter-toggle="<?= $hasActiveFilters ? 'open' : 'closed' ?>" aria-expanded="<?= $hasActiveFilters ? 'true' : 'false' ?>">
                                        <i class="ri-equalizer-line"></i><span><?= h(t('emailTemplate_action_filter', 'Filter')) ?></span>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-12 <?= $hasActiveFilters ? '' : 'd-none' ?>" data-filter-panel>
                        <div class="card et-filter-card">
                            <div class="card-body">
                                <form method="get" action="" class="row g-3 align-items-end">
                                    <div class="col-md-6 col-xl-3">
                                        <label class="form-label"><?= h(t('emailTemplate_filter_role', 'Peranan')) ?></label>
                                        <select name="role" class="form-select">
                                            <option value=""><?= h(t('emailTemplate_filter_all_roles', 'Semua Peranan')) ?></option>
                                            <?php foreach ($roleOptions as $optionValue => $optionLabel): ?>
                                                <option value="<?= h($optionValue) ?>" <?= (string)($filters['role_code'] ?? '') === (string)$optionValue ? 'selected' : '' ?>><?= h($optionLabel) ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="col-md-6 col-xl-3">
                                        <label class="form-label"><?= h(t('emailTemplate_filter_category', 'Kategori')) ?></label>
                                        <select name="category" class="form-select">
                                            <option value=""><?= h(t('emailTemplate_filter_all_categories', 'Semua Kategori')) ?></option>
                                            <?php foreach ($categoryOptions as $optionValue => $optionLabel): ?>
                                                <option value="<?= h($optionValue) ?>" <?= (string)($filters['category_code'] ?? '') === (string)$optionValue ? 'selected' : '' ?>><?= h($optionLabel) ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="col-md-6 col-xl-2">
                                        <label class="form-label"><?= h(t('emailTemplate_filter_status', 'Status')) ?></label>
                                        <select name="status" class="form-select">
                                            <option value=""><?= h(t('emailTemplate_filter_all_statuses', 'Semua Status')) ?></option>
                                            <?php foreach ($statusOptions as $optionValue => $optionLabel): ?>
                                                <option value="<?= h($optionValue) ?>" <?= strtoupper((string)($filters['status'] ?? '')) === (string)$optionValue ? 'selected' : '' ?>><?= h($optionLabel) ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="col-md-6 col-xl-3">
                                        <label class="form-label"><?= h(t('emailTemplate_filter_search', 'Carian')) ?></label>
                                        <input type="text" name="search" class="form-control" value="<?= h((string)($filters['search'] ?? '')) ?>" placeholder="<?= h(t('emailTemplate_filter_search_placeholder', 'Cari nama, kod, atau subjek')) ?>">
                                    </div>
                                    <div class="col-md-12 col-xl-1 d-grid">
                                        <button type="submit" class="btn btn-outline-primary"><?= h(t('emailTemplate_action_filter', 'Tapis')) ?></button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>

                    <div class="col-12">
                        <div class="card et-table-card">
                            <div class="card-body">
                                <table id="emailTemplateDT" class="table table-striped table-bordered align-top w-100">
                                    <thead>
                                    <tr>
                                        <th class="col-bil" data-orderable="false">#</th>
                                        <th><?= h(t('emailTemplate_col_template', 'Template')) ?></th>
                                        <th class="et-col-usage"><?= h(t('emailTemplate_col_usage', 'Usage')) ?></th>
                                        <th><?= h(t('emailTemplate_col_role', 'Peranan')) ?></th>
                                        <th><?= h(t('emailTemplate_col_category', 'Kategori')) ?></th>
                                        <th class="et-col-subject"><?= h(t('emailTemplate_col_subject', 'Subjek')) ?></th>
                                        <th><?= h(t('emailTemplate_col_status', 'Status')) ?></th>
                                        <th><?= h(t('emailTemplate_col_updated', 'Kemaskini')) ?></th>
                                        <th class="col-actions" data-orderable="false"><?= h(t('emailTemplate_col_actions', 'Tindakan')) ?></th>
                                    </tr>
                                    </thead>
                                    <tbody>
                                    <?php foreach ($records as $index => $record): ?>
                                        <?php
                                        $updatedAt = (string)($record['f_updatedt'] ?: $record['f_insertdt'] ?: '');
                                        $statusCode = strtoupper(trim((string)($record['f_status'] ?? 'DRAFT')));
                                        $statusLabel = $statusOptions[$statusCode] ?? $statusCode;
                                        $statusClass = match ($statusCode) {
                                            'ACTIVE' => 'bg-success-subtle text-success',
                                            'ARCHIVED' => 'bg-secondary-subtle text-secondary',
                                            default => 'bg-warning-subtle text-warning',
                                        };
                                        $editPayload = [
                                            'template_id' => (int)($record['f_templateID'] ?? 0),
                                            'template_code' => (string)($record['f_templateCode'] ?? ''),
                                            'template_name' => (string)($record['f_templateName'] ?? ''),
                                            'role_code' => (string)($record['f_roleCode'] ?? ''),
                                            'category_code' => (string)($record['f_categoryCode'] ?? ''),
                                            'subject_template' => (string)($record['f_subjectTemplate'] ?? ''),
                                            'body_html' => (string)($record['f_bodyHtml'] ?? ''),
                                            'body_text' => (string)($record['f_bodyText'] ?? ''),
                                            'status' => $statusCode,
                                            'is_default' => (int)($record['f_isDefault'] ?? 0),
                                            'description' => (string)($record['f_description'] ?? ''),
                                            'notes' => (string)($record['f_notes'] ?? ''),
                                        ];
                                        $usageCount = (int)($usageCounts[(int)($record['f_templateID'] ?? 0)] ?? 0);
                                        $canDelete = empty($record['f_isDefault']) && $usageCount === 0;
                                        $deleteDisabledReason = !empty($record['f_isDefault'])
                                            ? t('emailTemplate_delete_default_tooltip', 'Tetapkan template lain sebagai default sebelum padam template ini.')
                                            : t('emailTemplate_delete_used_tooltip', 'Template yang pernah digunakan tidak boleh dipadam.');
                                        ?>
                                        <tr data-template-id="<?= (int)($record['f_templateID'] ?? 0) ?>">
                                            <td class="col-bil"><?= (int)$index + 1 ?></td>
                                            <td>
                                                <div class="et-template-cell">
                                                    <div class="fw-semibold truncate-1line"><?= h((string)($record['f_templateName'] ?? '')) ?></div>
                                                    <div class="small text-muted truncate-1line"><?= h((string)($record['f_templateCode'] ?? '')) ?></div>
                                                    <div class="et-template-meta">
                                                        <?php if (!empty($record['f_isDefault'])): ?>
                                                            <span class="badge et-default-badge" data-bs-toggle="tooltip" data-bs-placement="top" title="<?= h(t('emailTemplate_default_note', 'Set another default first before archiving.')) ?>"><?= h(t('emailTemplate_badge_default_active', 'Active Default')) ?></span>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                            </td>
                                            <td class="et-col-usage"><span class="et-meta-chip"><?= $usageCount ?></span></td>
                                            <td><?= h($roleOptions[(string)($record['f_roleCode'] ?? '')] ?? (string)($record['f_roleCode'] ?? '-')) ?></td>
                                            <td><?= h($categoryOptions[(string)($record['f_categoryCode'] ?? '')] ?? (string)($record['f_categoryCode'] ?? '-')) ?></td>
                                            <td class="et-col-subject"><div class="truncate-2line"><?= h((string)($record['f_subjectTemplate'] ?? '')) ?></div></td>
                                            <td><span class="badge et-status-badge <?= h($statusClass) ?>"><?= h($statusLabel) ?></span></td>
                                            <td><div class="truncate-1line"><?= h($updatedAt !== '' ? $updatedAt : '-') ?></div><div class="small text-muted truncate-1line"><?= h((string)($record['f_updateby'] ?? '-')) ?></div></td>
                                            <td>
                                                <div class="et-action-group">
                                                    <button type="button" class="btn btn-outline-primary et-icon-btn" data-edit-template='<?= h(json_encode($editPayload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) ?>' onclick="window.EmailTemplateFallback && window.EmailTemplateFallback.openEdit(this);" title="<?= h(t('emailTemplate_btn_edit', 'Edit')) ?>" aria-label="<?= h(t('emailTemplate_btn_edit', 'Edit')) ?>">
                                                        <i class="ri-pencil-line"></i>
                                                    </button>
                                                    <form method="post" action="" class="d-inline-block" data-template-action-form="duplicate">
                                                        <input type="hidden" name="csrf_token" value="<?= h($csrf) ?>">
                                                        <input type="hidden" name="template_id" value="<?= (int)($record['f_templateID'] ?? 0) ?>">
                                                        <input type="hidden" name="form_action" value="duplicate">
                                                        <button type="button" class="btn btn-outline-secondary et-icon-btn" title="<?= h(t('emailTemplate_btn_duplicate', 'Duplicate')) ?>" aria-label="<?= h(t('emailTemplate_btn_duplicate', 'Duplicate')) ?>" data-template-action-button>
                                                            <i class="ri-file-copy-line"></i>
                                                        </button>
                                                    </form>
                                                    <?php if ($statusCode !== 'ARCHIVED' && empty($record['f_isDefault'])): ?>
                                                        <form method="post" action="" class="d-inline-block" data-template-action-form="archive" data-template-name="<?= h((string)($record['f_templateName'] ?? '')) ?>" data-template-code="<?= h((string)($record['f_templateCode'] ?? '')) ?>">
                                                            <input type="hidden" name="csrf_token" value="<?= h($csrf) ?>">
                                                            <input type="hidden" name="template_id" value="<?= (int)($record['f_templateID'] ?? 0) ?>">
                                                            <input type="hidden" name="form_action" value="archive">
                                                            <button type="button" class="btn btn-outline-danger et-icon-btn" title="<?= h(t('emailTemplate_btn_archive', 'Archive')) ?>" aria-label="<?= h(t('emailTemplate_btn_archive', 'Archive')) ?>" data-template-action-button>
                                                                <i class="ri-archive-line"></i>
                                                            </button>
                                                        </form>
                                                    <?php elseif ($statusCode !== 'ARCHIVED' && !empty($record['f_isDefault'])): ?>
                                                        <button type="button" class="btn btn-outline-secondary et-icon-btn" title="<?= h(t('emailTemplate_archive_default_tooltip', 'Set another template as default before archiving this one.')) ?>" aria-label="<?= h(t('emailTemplate_archive_default_tooltip', 'Set another template as default before archiving this one.')) ?>" disabled>
                                                            <i class="ri-archive-line"></i>
                                                        </button>
                                                    <?php endif; ?>
                                                    <?php if ($canDelete): ?>
                                                        <form method="post" action="" class="d-inline-block" data-template-action-form="delete" data-template-name="<?= h((string)($record['f_templateName'] ?? '')) ?>" data-template-code="<?= h((string)($record['f_templateCode'] ?? '')) ?>">
                                                            <input type="hidden" name="csrf_token" value="<?= h($csrf) ?>">
                                                            <input type="hidden" name="template_id" value="<?= (int)($record['f_templateID'] ?? 0) ?>">
                                                            <input type="hidden" name="form_action" value="delete">
                                                            <button type="button" class="btn btn-outline-danger et-icon-btn" title="<?= h(t('emailTemplate_btn_delete', 'Padam')) ?>" aria-label="<?= h(t('emailTemplate_btn_delete', 'Padam')) ?>" data-template-action-button>
                                                                <i class="ri-delete-bin-line"></i>
                                                            </button>
                                                        </form>
                                                    <?php else: ?>
                                                        <button type="button" class="btn btn-outline-secondary et-icon-btn" title="<?= h($deleteDisabledReason) ?>" aria-label="<?= h($deleteDisabledReason) ?>" disabled>
                                                            <i class="ri-delete-bin-line"></i>
                                                        </button>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                    </tbody>
                                </table>
                                <div id="emailTemplateEmptyStateContainer">
                                <?php if ($records === []): ?>
                                    <div class="et-empty-state">
                                        <div class="et-empty-icon"><i class="ri-mail-open-line"></i></div>
                                        <h6><?= h(t('emailTemplate_empty_title', 'Tiada template emel lagi')) ?></h6>
                                        <p class="text-muted mb-3"><?= h(t('emailTemplate_empty_subtitle', 'Mulakan dengan import seed template atau cipta template baharu secara manual.')) ?></p>
                                        <div class="d-flex flex-wrap justify-content-center gap-2">
                                            <?php if ($seedTemplates !== []): ?>
                                                <form method="post" action="" class="d-inline-block" data-template-action-form="seed_templates">
                                                    <input type="hidden" name="csrf_token" value="<?= h($csrf) ?>">
                                                    <input type="hidden" name="form_action" value="seed_templates">
                                                    <button type="button" class="btn btn-outline-primary" data-template-action-button><?= h(t('emailTemplate_btn_seed_templates', 'Import Seed Templates')) ?></button>
                                                </form>
                                            <?php endif; ?>
                                            <button type="button" class="btn btn-primary" data-create-template onclick="window.EmailTemplateFallback && window.EmailTemplateFallback.openCreate();"><?= h(t('emailTemplate_action_create', 'Tambah Template')) ?></button>
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
        <?php include __DIR__ . '/../includes/footer.php'; ?>
    </div>
</div>

<div class="modal fade" id="emailTemplateModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content et-modal-shell">
            <div class="modal-header et-modal-header">
                <div>
                    <h5 class="modal-title" data-modal-title><?= h(t('emailTemplate_modal_create_title', 'Tambah Template Emel')) ?></h5>
                    <p class="mb-0 text-white-50 small"><?= h(t('emailTemplate_modal_subtitle', 'Sediakan maklumat utama template, kandungan emel, dan placeholder umum yang diperlukan.')) ?></p>
                    <span class="et-unsaved-indicator" id="emailTemplateUnsaved"><i class="ri-edit-circle-line"></i><?= h(t('emailTemplate_unsaved_indicator', 'Unsaved changes')) ?></span>
                </div>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="<?= h(t('emailTemplate_modal_close_aria', 'Tutup')) ?>"></button>
            </div>
            <div class="modal-body">
                <form method="post" action="" id="emailTemplateForm">
                    <input type="hidden" name="csrf_token" value="<?= h($csrf) ?>">
                    <input type="hidden" name="form_action" value="save" data-field="form_action">
                    <input type="hidden" name="template_id" value="<?= (int)($form['template_id'] ?? 0) ?>" data-field="template_id">
                    <div class="et-modal-tabs" role="tablist" aria-label="<?= h(t('emailTemplate_page_title', 'Template Emel')) ?>">
                        <button type="button" class="et-modal-tab is-active" data-template-tab="editor" aria-selected="true">
                            <i class="ri-file-edit-line"></i><span><?= h(t('emailTemplate_tab_editor', 'Maklumat & Editor')) ?></span>
                        </button>
                        <button type="button" class="et-modal-tab" data-template-tab="placeholder" aria-selected="false">
                            <i class="ri-braces-line"></i><span><?= h(t('emailTemplate_tab_placeholders', 'Placeholder')) ?></span>
                        </button>
                        <button type="button" class="et-modal-tab" data-template-tab="preview" aria-selected="false">
                            <i class="ri-eye-line"></i><span><?= h(t('emailTemplate_tab_preview', 'Preview & Test')) ?></span>
                        </button>
                        <button type="button" class="et-modal-tab" data-template-tab="developer" aria-selected="false">
                            <i class="ri-code-s-slash-line"></i><span><?= h(t('emailTemplate_tab_developer', 'Developer')) ?></span>
                        </button>
                    </div>
                    <div class="et-modal-tab-content">
                    <div class="et-modal-tab-pane is-active" data-tab-pane="editor">
                    <div class="row g-4">
                        <div class="col-12">
                            <div class="et-form-panel">
                                <div class="row g-3">
                                    <div class="col-md-6"><label class="form-label"><?= h(t('emailTemplate_field_template_name', 'Nama Template')) ?> <span class="text-danger">*</span></label><input type="text" name="template_name" class="form-control <?= isset($fieldErrors['template_name']) ? 'is-invalid' : '' ?>" value="<?= h((string)($form['template_name'] ?? '')) ?>" data-field="template_name" required><?php if (isset($fieldErrors['template_name'])): ?><div class="invalid-feedback"><?= h((string)$fieldErrors['template_name']) ?></div><?php endif; ?></div>
                                    <div class="col-md-6"><label class="form-label"><?= h(t('emailTemplate_field_template_code', 'Kod Template')) ?> <span class="text-danger">*</span></label><input type="text" name="template_code" class="form-control <?= isset($fieldErrors['template_code']) ? 'is-invalid' : '' ?>" value="<?= h((string)($form['template_code'] ?? '')) ?>" data-field="template_code" placeholder="<?= h(t('emailTemplate_field_template_code_example', 'STAFF_REMINDER_APPROVAL')) ?>" required><?php if (isset($fieldErrors['template_code'])): ?><div class="invalid-feedback"><?= h((string)$fieldErrors['template_code']) ?></div><?php endif; ?></div>
                                    <div class="col-md-4"><label class="form-label"><?= h(t('emailTemplate_field_role', 'Peranan')) ?> <span class="text-danger">*</span></label><select name="role_code" class="form-select <?= isset($fieldErrors['role_code']) ? 'is-invalid' : '' ?>" data-field="role_code" required><option value=""><?= h(t('emailTemplate_select_role', 'Pilih peranan')) ?></option><?php foreach ($roleOptions as $optionValue => $optionLabel): ?><option value="<?= h($optionValue) ?>" <?= (string)($form['role_code'] ?? '') === (string)$optionValue ? 'selected' : '' ?>><?= h($optionLabel) ?></option><?php endforeach; ?></select><?php if (isset($fieldErrors['role_code'])): ?><div class="invalid-feedback"><?= h((string)$fieldErrors['role_code']) ?></div><?php endif; ?></div>
                                    <div class="col-md-4"><label class="form-label"><?= h(t('emailTemplate_field_category', 'Kategori')) ?> <span class="text-danger">*</span></label><select name="category_code" class="form-select <?= isset($fieldErrors['category_code']) ? 'is-invalid' : '' ?>" data-field="category_code" required><option value=""><?= h(t('emailTemplate_select_category', 'Pilih kategori')) ?></option><?php foreach ($categoryOptions as $optionValue => $optionLabel): ?><option value="<?= h($optionValue) ?>" <?= (string)($form['category_code'] ?? '') === (string)$optionValue ? 'selected' : '' ?>><?= h($optionLabel) ?></option><?php endforeach; ?></select><?php if (isset($fieldErrors['category_code'])): ?><div class="invalid-feedback"><?= h((string)$fieldErrors['category_code']) ?></div><?php endif; ?></div>
                                    <div class="col-md-4"><label class="form-label"><?= h(t('emailTemplate_field_status', 'Status')) ?> <span class="text-danger">*</span></label><select name="status" class="form-select <?= isset($fieldErrors['status']) ? 'is-invalid' : '' ?>" data-field="status" required><?php foreach ($statusOptions as $optionValue => $optionLabel): ?><option value="<?= h($optionValue) ?>" <?= strtoupper((string)($form['status'] ?? 'DRAFT')) === (string)$optionValue ? 'selected' : '' ?>><?= h($optionLabel) ?></option><?php endforeach; ?></select><?php if (isset($fieldErrors['status'])): ?><div class="invalid-feedback"><?= h((string)$fieldErrors['status']) ?></div><?php endif; ?></div>
                                    <div class="col-12"><label class="form-label"><?= h(t('emailTemplate_field_description', 'Penerangan Ringkas')) ?></label><input type="text" name="description" class="form-control" value="<?= h((string)($form['description'] ?? '')) ?>" data-field="description" placeholder="<?= h(t('emailTemplate_field_description_placeholder', 'Ringkaskan tujuan template ini')) ?>"></div>
                                    <div class="col-12"><label class="form-label"><?= h(t('emailTemplate_field_subject', 'Subjek Emel')) ?> <span class="text-danger">*</span></label><input type="text" name="subject_template" class="form-control <?= isset($fieldErrors['subject_template']) ? 'is-invalid' : '' ?>" value="<?= h((string)($form['subject_template'] ?? '')) ?>" data-field="subject_template" data-placeholder-target required><?php if (isset($fieldErrors['subject_template'])): ?><div class="invalid-feedback"><?= h((string)$fieldErrors['subject_template']) ?></div><?php endif; ?></div>
                                    <div class="col-12"><label class="form-label"><?= h(t('emailTemplate_field_body_html', 'Kandungan HTML')) ?> <span class="text-danger">*</span></label><textarea name="body_html" rows="11" class="form-control et-code-field <?= isset($fieldErrors['body_html']) ? 'is-invalid' : '' ?>" data-field="body_html" data-placeholder-target required><?= h((string)($form['body_html'] ?? '')) ?></textarea><?php if (isset($fieldErrors['body_html'])): ?><div class="invalid-feedback"><?= h((string)$fieldErrors['body_html']) ?></div><?php endif; ?><div class="form-text"><?= h(t('emailTemplate_hint_body_html', 'Gunakan HTML biasa di sini. Semak hasil akhir melalui tab Preview & Test.')) ?></div><div class="form-text text-warning-emphasis"><i class="ri-shield-check-line me-1"></i><?= h(t('emailTemplate_external_resource_warning', 'External images or links may contact third-party servers when recipients open the email.')) ?></div></div>
                                    <div class="col-12"><label class="form-label"><?= h(t('emailTemplate_field_body_text', 'Kandungan Text')) ?></label><textarea name="body_text" rows="6" class="form-control et-code-field" data-field="body_text" data-placeholder-target><?= h((string)($form['body_text'] ?? '')) ?></textarea></div>
                                    <div class="col-12"><label class="form-label"><?= h(t('emailTemplate_field_notes', 'Nota Dalaman')) ?></label><textarea name="notes" rows="3" class="form-control" data-field="notes"><?= h((string)($form['notes'] ?? '')) ?></textarea></div>
                                    <div class="col-12"><div class="form-check form-switch et-default-switch"><input class="form-check-input" type="checkbox" role="switch" name="is_default" value="1" id="emailTemplateIsDefault" data-field="is_default" <?= !empty($form['is_default']) ? 'checked' : '' ?>><label class="form-check-label" for="emailTemplateIsDefault"><?= h(t('emailTemplate_field_is_default', 'Tetapkan sebagai template default untuk role dan kategori ini')) ?></label></div></div>
                                </div>
                            </div>
                        </div>
                    </div>
                    </div>
                    <div class="et-modal-tab-pane" data-tab-pane="placeholder">
                    <div class="row g-4">
                        <div class="col-12">
                            <div class="et-sidebar-panel">
                                <div class="et-placeholder-panel">
                                    <div class="d-flex align-items-center justify-content-between gap-2 mb-3"><div><h6 class="mb-1"><?= h(t('emailTemplate_placeholder_title', 'Placeholder Umum')) ?></h6><p class="text-muted mb-0 small"><?= h(t('emailTemplate_placeholder_subtitle', 'Klik placeholder untuk masukkan terus ke field yang sedang aktif.')) ?></p></div></div>
                                    <div class="vstack gap-3">
                                        <?php foreach ($groupedPlaceholders as $groupKey => $items): ?>
                                            <div class="et-placeholder-group">
                                                <div class="et-placeholder-group-title"><?= h(t('emailTemplate_placeholder_group_' . $groupKey, ucfirst($groupKey))) ?></div>
                                                <div class="et-placeholder-list">
                                                    <?php foreach ($items as $placeholder): ?>
                                                        <?php $placeholderKey = '{{' . (string)($placeholder['f_placeholderKey'] ?? '') . '}}'; ?>
                                                        <button type="button" class="btn btn-light et-placeholder-chip" data-insert-placeholder="<?= h($placeholderKey) ?>" title="<?= h((string)($placeholder['f_description'] ?? '')) ?>"><?= h($placeholderKey) ?></button>
                                                    <?php endforeach; ?>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                                <div class="et-placeholder-panel mt-3">
                                    <h6 class="mb-1"><?= h(t('emailTemplate_guideline_title', 'Panduan Ringkas')) ?></h6>
                                    <ul class="et-guideline-list mb-0">
                                        <li><?= h(t('emailTemplate_guideline_1', 'Gunakan kod template yang stabil kerana developer akan panggil berdasarkan kod ini.')) ?></li>
                                        <li><?= h(t('emailTemplate_guideline_2', 'Template default sesuai untuk flow send email yang hanya pilih role + kategori.')) ?></li>
                                        <li><?= h(t('emailTemplate_guideline_3', 'Placeholder khusus page akan diinject oleh developer dalam coding page berkenaan.')) ?></li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                    </div>
                    </div>
                    <div class="et-modal-tab-pane" data-tab-pane="preview">
                    <div class="row g-3">
                        <div class="col-xl-4">
                            <div class="et-placeholder-panel h-100">
                                    <div class="d-flex align-items-center justify-content-between gap-2 mb-2">
                                        <div>
                                            <h6 class="mb-1"><?= h(t('emailTemplate_preview_title', 'Preview & Test Send')) ?></h6>
                                            <p class="text-muted mb-0 small"><?= h(t('emailTemplate_preview_subtitle', 'Gunakan sample JSON untuk render placeholder sebelum simpan atau hantar emel ujian.')) ?></p>
                                        </div>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label"><?= h(t('emailTemplate_field_sample_variables', 'Sample Variables JSON')) ?></label>
                                        <textarea class="form-control et-code-field et-sample-json" rows="10" id="emailTemplateSampleVariables"><?= h($sampleVariablesJson) ?></textarea>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label"><?= h(t('emailTemplate_field_test_email', 'Emel Ujian')) ?></label>
                                        <input type="email" class="form-control" id="emailTemplateTestEmail" value="<?= h((string)($_SESSION['user']['f_email'] ?? $_SESSION['f_email'] ?? '')) ?>" placeholder="<?= h(t('emailTemplate_field_test_email_placeholder', 'admin@example.com')) ?>">
                                    </div>
                                    <div class="d-grid gap-2">
                                        <button type="button" class="btn btn-outline-primary" id="btnEmailTemplatePreview">
                                            <i class="ri-eye-line me-1"></i><?= h(t('emailTemplate_btn_preview', 'Preview Render')) ?>
                                        </button>
                                        <button type="button" class="btn btn-outline-success" id="btnEmailTemplateTestSend">
                                            <i class="ri-mail-send-line me-1"></i><?= h(t('emailTemplate_btn_test_send', 'Hantar Emel Ujian')) ?>
                                        </button>
                                    </div>
                            </div>
                        </div>
                        <div class="col-xl-8">
                    <div class="accordion et-preview-accordion" id="emailTemplatePreviewAccordion">
                        <div class="accordion-item et-accordion-item">
                            <h2 class="accordion-header" id="emailTemplatePreviewResultHeading">
                                <button class="accordion-button et-accordion-button collapsed" type="button" data-preview-toggle="emailTemplatePreviewResultCollapse" aria-expanded="false" aria-controls="emailTemplatePreviewResultCollapse">
                                    <span>
                                        <span class="d-block fw-semibold"><?= h(t('emailTemplate_preview_subject_title', 'Hasil Preview')) ?></span>
                                        <span class="d-block small text-muted"><?= h(t('emailTemplate_preview_subject_subtitle', 'Subjek, status placeholder, dan text output akan dipaparkan di sini.')) ?></span>
                                    </span>
                                </button>
                            </h2>
                            <div id="emailTemplatePreviewResultCollapse" class="accordion-collapse collapse" aria-labelledby="emailTemplatePreviewResultHeading">
                                <div class="accordion-body et-accordion-body">
                                    <div class="et-preview-meta mb-3">
                                        <div class="et-preview-meta-label"><?= h(t('emailTemplate_field_subject', 'Subjek Emel')) ?></div>
                                        <div class="fw-semibold" id="emailTemplatePreviewSubject"><?= h(t('emailTemplate_preview_empty_subject', 'Belum dijana')) ?></div>
                                    </div>
                                    <div class="row g-3">
                                        <div class="col-md-6">
                                            <div class="et-preview-list-shell">
                                                <div class="et-preview-list-title"><?= h(t('emailTemplate_preview_used_placeholders', 'Placeholder Digunakan')) ?></div>
                                                <div class="et-preview-badges" id="emailTemplatePreviewUsed"></div>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="et-preview-list-shell">
                                                <div class="et-preview-list-title"><?= h(t('emailTemplate_preview_missing_placeholders', 'Placeholder Tiada Nilai')) ?></div>
                                                <div class="et-preview-badges" id="emailTemplatePreviewMissing"></div>
                                            </div>
                                        </div>
                                        <div class="col-12">
                                            <div class="et-preview-list-shell">
                                                <div class="et-preview-list-title"><?= h(t('emailTemplate_preview_invalid_placeholders', 'Placeholder Tidak Sah')) ?></div>
                                                <div class="et-preview-badges" id="emailTemplatePreviewInvalid"></div>
                                            </div>
                                        </div>
                                        <div class="col-12">
                                            <div class="et-preview-list-shell">
                                                <div class="et-preview-list-title"><?= h(t('emailTemplate_preview_text_output', 'Text Output')) ?></div>
                                                <pre class="et-preview-text-output" id="emailTemplatePreviewText"><?= h(t('emailTemplate_preview_empty_text', 'Klik Preview Render untuk melihat output text template.')) ?></pre>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="accordion-item et-accordion-item">
                            <h2 class="accordion-header" id="emailTemplatePreviewHtmlHeading">
                                <button class="accordion-button et-accordion-button collapsed" type="button" data-preview-toggle="emailTemplatePreviewHtmlCollapse" aria-expanded="false" aria-controls="emailTemplatePreviewHtmlCollapse">
                                    <span>
                                        <span class="d-block fw-semibold"><?= h(t('emailTemplate_preview_html_title', 'HTML Preview')) ?></span>
                                        <span class="d-block small text-muted"><?= h(t('emailTemplate_preview_html_subtitle', 'Paparan akhir email selepas dibungkus dengan layout standard sistem.')) ?></span>
                                    </span>
                                </button>
                            </h2>
                            <div id="emailTemplatePreviewHtmlCollapse" class="accordion-collapse collapse" aria-labelledby="emailTemplatePreviewHtmlHeading">
                                <div class="accordion-body et-accordion-body">
                                    <iframe id="emailTemplatePreviewFrame" class="et-preview-frame" title="<?= h(t('emailTemplate_preview_html_title', 'HTML Preview')) ?>" sandbox="" referrerpolicy="no-referrer"></iframe>
                                </div>
                            </div>
                        </div>
                    </div>
                        </div>
                    </div>
                    </div>
                    <div class="et-modal-tab-pane" data-tab-pane="developer">
                    <div class="row g-3">
                        <div class="col-xl-5">
                            <div class="et-placeholder-panel h-100">
                                <h6 class="mb-1"><?= h(t('emailTemplate_dev_title', 'Panduan Integrasi Programmer')) ?></h6>
                                <p class="text-muted small mb-3"><?= h(t('emailTemplate_dev_subtitle', 'Gunakan seksyen ini untuk lihat placeholder yang digunakan, placeholder default sistem, dan contoh code panggilan template.')) ?></p>
                                <div class="et-dev-section">
                                    <div class="et-preview-list-title"><?= h(t('emailTemplate_dev_used_placeholders', 'Placeholder Digunakan Dalam Template')) ?></div>
                                    <div class="et-preview-badges" id="emailTemplateDeveloperUsed"></div>
                                </div>
                                <div class="et-dev-section">
                                    <div class="et-preview-list-title"><?= h(t('emailTemplate_dev_default_placeholders', 'Default Placeholder Tersedia')) ?></div>
                                    <div class="et-preview-badges" id="emailTemplateDeveloperDefault"></div>
                                </div>
                                <div class="et-dev-section">
                                    <div class="et-preview-list-title"><?= h(t('emailTemplate_dev_programmer_values', 'Nilai Yang Programmer Perlu Hantar')) ?></div>
                                    <div class="et-preview-badges" id="emailTemplateDeveloperProgrammer"></div>
                                </div>
                                <div class="et-dev-section">
                                    <div class="et-preview-list-title"><?= h(t('emailTemplate_dev_reference_notes', 'Nota Ringkas')) ?></div>
                                    <ul class="et-guideline-list mb-0">
                                        <li><?= h(t('emailTemplate_dev_note_1', 'Placeholder default datang daripada context atau setting sistem semasa render template.')) ?></li>
                                        <li><?= h(t('emailTemplate_dev_note_2', 'Placeholder selain default perlu dihantar oleh programmer melalui array variables.')) ?></li>
                                        <li><?= h(t('emailTemplate_dev_note_3', 'Gunakan template code sebagai rujukan stabil dalam coding modul.')) ?></li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                        <div class="col-xl-7">
                            <div class="et-placeholder-panel h-100">
                                <div class="d-flex align-items-center justify-content-between gap-2 mb-2">
                                    <div>
                                        <h6 class="mb-1"><?= h(t('emailTemplate_dev_snippet_title', 'Sample Code')) ?></h6>
                                        <p class="text-muted small mb-0"><?= h(t('emailTemplate_dev_snippet_subtitle', 'Code ini dijana berdasarkan template code dan placeholder semasa.')) ?></p>
                                    </div>
                                    <button type="button" class="btn btn-outline-primary btn-sm" id="btnEmailTemplateCopySnippet">
                                        <i class="ri-file-copy-line me-1"></i><?= h(t('emailTemplate_dev_copy_snippet', 'Copy Code')) ?>
                                    </button>
                                </div>
                                <pre class="et-preview-text-output et-dev-code-output" id="emailTemplateDeveloperSnippet"></pre>
                            </div>
                        </div>
                    </div>
                    </div>
                    </div>
                    <div class="d-flex flex-wrap justify-content-end gap-2 mt-4">
                        <button type="button" class="btn btn-light" data-bs-dismiss="modal"><?= h(t('emailTemplate_btn_close', 'Tutup')) ?></button>
                        <button type="button" class="btn btn-primary et-primary-btn" data-submit-label data-template-save-button><?= h(!empty($form['template_id']) ? t('emailTemplate_btn_update', 'Kemaskini Template') : t('emailTemplate_btn_save', 'Simpan Template')) ?></button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../includes/script.php'; ?>
<script>
window.EmailTemplatePageData = <?= json_encode([
    'shouldOpenModal' => $shouldOpenModal,
    'modalCreateTitle' => t('emailTemplate_modal_create_title', 'Tambah Template Emel'),
    'modalEditTitle' => t('emailTemplate_modal_edit_title', 'Kemaskini Template Emel'),
    'submitCreateLabel' => t('emailTemplate_btn_save', 'Simpan Template'),
    'submitEditLabel' => t('emailTemplate_btn_update', 'Kemaskini Template'),
    'defaultSampleVariablesJson' => $sampleVariablesJson,
    'defaultTestEmail' => (string)($_SESSION['user']['f_email'] ?? $_SESSION['f_email'] ?? ''),
    'defaultPlaceholders' => $defaultPlaceholderDocs,
    'generalPlaceholderDocs' => $generalPlaceholderDocs,
    'actionUrl' => base_url('ajax/email-template-action.php'),
    'filters' => [
        'role' => (string)($filters['role_code'] ?? ''),
        'category' => (string)($filters['category_code'] ?? ''),
        'status' => (string)($filters['status'] ?? ''),
        'search' => (string)($filters['search'] ?? ''),
    ],
    'previewUrl' => base_url('ajax/email-template-preview.php'),
    'testSendUrl' => base_url('ajax/email-template-test-send.php'),
    'unsavedConfirmTitle' => t('emailTemplate_unsaved_confirm_title', 'Discard unsaved changes?'),
    'unsavedConfirmText' => t('emailTemplate_unsaved_confirm_text', 'Changes made in this template have not been saved.'),
    'unsavedConfirmDiscard' => t('emailTemplate_unsaved_confirm_discard', 'Discard changes'),
    'testSendConfirmTitle' => t('emailTemplate_test_confirm_title', 'Send test email?'),
    'testSendConfirmText' => t('emailTemplate_test_confirm_text', 'Confirm the recipient and subject before sending.'),
    'testSendConfirmButton' => t('emailTemplate_test_confirm_button', 'Send test email'),
    'testSendRecipientLabel' => t('emailTemplate_test_recipient_label', 'Recipient'),
    'testSendSubjectLabel' => t('emailTemplate_test_subject_label', 'Subject'),
    'commonCancel' => t('common_cancel', 'Cancel'),
    'previewFailedTitle' => t('emailTemplate_preview_failed_title', 'Preview Gagal'),
    'testSendSuccessTitle' => t('emailTemplate_test_send_success_title', 'Emel Ujian Berjaya'),
    'testSendFailedTitle' => t('emailTemplate_test_send_failed_title', 'Emel Ujian Gagal'),
    'networkErrorText' => t('emailTemplate_network_error', 'Ralat rangkaian semasa memproses permintaan.'),
    'invalidJsonText' => t('emailTemplate_error_sample_json_invalid', 'Sample variables mesti dalam format JSON yang sah.'),
    'invalidJsonShortText' => t('emailTemplate_error_invalid_json', 'JSON tidak sah.'),
    'swalOkText' => t('emailTemplate_swal_ok', 'OK'),
    'loadingProcessingText' => t('emailTemplate_loading_processing', 'Memproses...'),
    'loadingPreviewText' => t('emailTemplate_loading_preview', 'Preview...'),
    'loadingSendingText' => t('emailTemplate_loading_sending', 'Menghantar...'),
    'previewEmptyUsedText' => t('emailTemplate_preview_empty_used', 'Tiada'),
    'previewEmptyMissingText' => t('emailTemplate_preview_empty_missing', 'Lengkap'),
    'previewEmptyInvalidText' => t('emailTemplate_preview_empty_invalid', 'Tiada'),
    'previewEmptySubjectText' => t('emailTemplate_preview_empty_subject', 'Belum dijana'),
    'previewEmptyTextText' => t('emailTemplate_preview_empty_text', 'Klik Preview Render untuk melihat output text template.'),
    'testEmailRequiredText' => t('emailTemplate_error_test_email_required', 'Alamat emel ujian diperlukan.'),
    'flashSuccessTitle' => t('emailTemplate_flash_success_title', 'Berjaya'),
    'flashErrorTitle' => t('emailTemplate_flash_error_title', 'Ralat'),
    'flashSuccessMessage' => $successMessage,
    'flashErrorMessage' => (!$shouldOpenModal && $errorMessage) ? $errorMessage : null,
    'modalErrorMessage' => $shouldOpenModal ? $errorMessage : null,
    'archiveConfirmTitle' => t('emailTemplate_archive_confirm', 'Arkibkan template ini?'),
    'archiveConfirmText' => t('emailTemplate_archive_confirm_text', 'Template ini akan dipindahkan ke status arkib.'),
    'archiveConfirmButtonText' => t('emailTemplate_btn_archive_confirm', 'Ya, Arkib'),
    'deleteConfirmTitle' => t('emailTemplate_delete_confirm', 'Padam template ini?'),
    'deleteConfirmText' => t('emailTemplate_delete_confirm_text', 'Template ini akan dipadam secara kekal jika belum pernah digunakan.'),
    'deleteConfirmButtonText' => t('emailTemplate_btn_delete_confirm', 'Ya, Padam'),
    'cancelButtonText' => t('emailTemplate_btn_cancel', 'Batal'),
    'confirmButtonText' => t('emailTemplate_btn_confirm', 'OK'),
    'developerNoPlaceholdersText' => t('emailTemplate_dev_no_placeholders', 'Tiada placeholder digunakan.'),
    'developerNoProgrammerValuesText' => t('emailTemplate_dev_no_programmer_values', 'Tiada nilai custom diperlukan.'),
    'developerDefaultBadgeText' => t('emailTemplate_dev_badge_default', 'Default'),
    'developerProgrammerBadgeText' => t('emailTemplate_dev_badge_programmer', 'Programmer'),
    'developerGeneralBadgeText' => t('emailTemplate_dev_badge_general', 'General'),
    'developerSnippetCopiedText' => t('emailTemplate_dev_snippet_copied', 'Sample code berjaya disalin.'),
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
</script>
<script>
window.EmailTemplateFallback = window.EmailTemplateFallback || (function () {
    function safeJsonParse(value) {
        try {
            return JSON.parse(String(value || '').trim());
        } catch (error) {
            return null;
        }
    }

    function setFieldValue(form, field, value) {
        var input = form.querySelector('[data-field="' + field + '"]');
        if (!input) {
            return;
        }

        if (input.type === 'checkbox') {
            input.checked = !!Number(value) || value === true;
            return;
        }

        input.value = value == null ? '' : String(value);
    }

    function resetForm(form) {
        form.reset();
        setFieldValue(form, 'template_id', 0);
        setFieldValue(form, 'status', 'DRAFT');
        setFieldValue(form, 'is_default', 0);
        form.classList.remove('was-validated');
        form.querySelectorAll('.is-invalid').forEach(function (field) {
            field.classList.remove('is-invalid');
        });
    }

    function fillForm(form, payload) {
        Object.keys(payload || {}).forEach(function (key) {
            setFieldValue(form, key, payload[key]);
        });
    }

    function getModal() {
        return document.getElementById('emailTemplateModal');
    }

    function getForm() {
        return document.getElementById('emailTemplateForm');
    }

    function showModal() {
        var modalEl = getModal();
        if (!modalEl) {
            return;
        }

        if (window.bootstrap && window.bootstrap.Modal) {
            window.bootstrap.Modal.getOrCreateInstance(modalEl).show();
            return;
        }

        modalEl.classList.add('show');
        modalEl.style.display = 'block';
        modalEl.removeAttribute('aria-hidden');
        document.body.classList.add('modal-open');
    }

    function openCreate() {
        var form = getForm();
        if (!form) {
            return false;
        }

        resetForm(form);
        setFieldValue(form, 'form_action', 'save');

        var titleNode = document.querySelector('#emailTemplateModal [data-modal-title]');
        var submitNode = document.querySelector('#emailTemplateModal [data-submit-label]');
        var pageData = window.EmailTemplatePageData || {};
        if (titleNode) {
            titleNode.textContent = pageData.modalCreateTitle || 'Tambah Template Emel';
        }
        if (submitNode) {
            submitNode.textContent = pageData.submitCreateLabel || 'Simpan Template';
            submitNode.disabled = false;
        }

        showModal();
        return false;
    }

    function openEdit(button) {
        var form = getForm();
        if (!form || !button) {
            return false;
        }

        var payload = safeJsonParse(button.getAttribute('data-edit-template') || '{}');
        if (!payload) {
            return false;
        }

        resetForm(form);
        setFieldValue(form, 'form_action', 'save');
        fillForm(form, payload);

        var titleNode = document.querySelector('#emailTemplateModal [data-modal-title]');
        var submitNode = document.querySelector('#emailTemplateModal [data-submit-label]');
        var pageData = window.EmailTemplatePageData || {};
        if (titleNode) {
            titleNode.textContent = pageData.modalEditTitle || 'Kemaskini Template Emel';
        }
        if (submitNode) {
            submitNode.textContent = pageData.submitEditLabel || 'Kemaskini Template';
            submitNode.disabled = false;
        }

        showModal();
        return false;
    }

    return {
        openCreate: openCreate,
        openEdit: openEdit
    };
})();
</script>
<script src="<?= h(base_url('assets/js/pages/template-emel.js')) ?>?v=<?= h($version) ?>"></script>
</body>
</html>
