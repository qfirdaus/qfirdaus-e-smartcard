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
$templateGeneratorProfile = function_exists('userListResolveCurrentProfile')
    ? userListResolveCurrentProfile($pdoPerm)
    : [];
$isTemplateGeneratorAdmin = $templateGeneratorProfile
    && function_exists('is_user_super_admin')
    && is_user_super_admin($templateGeneratorProfile, $pdoPerm);
if (!$isTemplateGeneratorAdmin) {
    http_response_code(403);
    header('Content-Type: text/html; charset=utf-8');
    echo '<!doctype html><html lang="' . htmlspecialchars((string)($_SESSION['lang'] ?? 'ms'), ENT_QUOTES, 'UTF-8') . '"><head><meta charset="utf-8"><title>403</title></head><body>';
    echo htmlspecialchars((string)__('pageTemplateGenerator_error_permission'), ENT_QUOTES, 'UTF-8');
    echo '</body></html>';
    exit;
}

require_once __DIR__ . '/../controllers/SystemTemplateController.php';
$controller = null;
$bootstrapError = null;
try {
    $controller = new SystemTemplateController();
} catch (Throwable $e) {
    error_log('[template-generator] Bootstrap failed: ' . $e->getMessage());
    $bootstrapError = (string)__('pageTemplateGenerator_error_bootstrap');
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
$version = date('ymdHis');
$PAGE_TITLE = t('pageTemplateGenerator_page_title', 'System Template Generator');
$templates = $controller->templates ?? [];
$records = $controller->records ?? [];
$csrf = $controller->csrf ?? (string)($_SESSION['csrf_token'] ?? '');
$form = $controller->form ?? [
    'template_name' => '',
    'page_name' => '',
    'page_title_ms' => '',
    'page_title_en' => '',
    'page_icon' => '',
    'template_key' => '',
    'access_mode' => FileGenerationService::ACCESS_MODE_GROUP_MENU,
];
$fieldErrors = $controller->fieldErrors ?? [];
$previewResult = $controller->previewResult ?? null;
$generationResult = $controller->generationResult ?? null;
$errorMessage = $controller->errorMessage ?? null;
$successMessage = $controller->successMessage ?? null;
if ($bootstrapError !== null && $errorMessage === null) {
    $errorMessage = $bootstrapError;
}

$iconOptions = [
    'ri-file-list-line','ri-file-text-line','ri-file-paper-2-line','ri-table-line','ri-book-open-line',
    'ri-user-settings-line','ri-settings-3-line','ri-tools-line','ri-shield-user-line','ri-folder-chart-line',
    'ri-survey-line','ri-dashboard-line','ri-layout-grid-line','ri-bar-chart-box-line','ri-pie-chart-line',
    'ri-line-chart-line','ri-folder-line','ri-folder-settings-line','ri-article-line','ri-user-line',
    'ri-team-line','ri-search-line','ri-filter-3-line','ri-database-2-line','ri-file-search-line',
    'ri-list-check-3-line','ri-edit-2-line','ri-pages-line','ri-profile-line','ri-archive-line',
    'ri-stack-line','ri-booklet-line','ri-task-line','ri-slideshow-line','ri-service-line',
    'ri-calendar-check-line','ri-notification-3-line','ri-mail-send-line','ri-customer-service-2-line','ri-briefcase-4-line',
    'ri-building-line','ri-government-line','ri-graduation-cap-line','ri-award-line','ri-shield-check-line',
    'ri-lock-password-line','ri-route-line','ri-map-pin-line',
];
$selectedIcon = (string)($form['page_icon'] ?? '');
$result = $previewResult ?: [];
$existsMap = is_array($result['exists'] ?? null) ? $result['exists'] : [];
$existingSlugs = [];
$existingControllers = [];
foreach ($records as $record) {
    $slug = trim((string)($record['f_pageSlug'] ?? ''));
    $controllerClass = trim((string)($record['f_controllerClass'] ?? ''));
    if ($slug !== '') {
        $existingSlugs[$slug] = true;
    }
    if ($controllerClass !== '') {
        $existingControllers[$controllerClass] = true;
    }
}
$selectedAccessMode = (string)($form['access_mode'] ?? FileGenerationService::ACCESS_MODE_GROUP_MENU);
$dbSlugExists = isset($existingSlugs[(string)($result['page_slug'] ?? '')]);
$dbControllerExists = isset($existingControllers[(string)($result['controller_class'] ?? '')]);
$hasFileCollision = !empty($existsMap['page']) || !empty($existsMap['controller']) || !empty($existsMap['css']);
$hasDbCollision = $dbSlugExists || $dbControllerExists;
$hasCollision = $hasFileCollision || $hasDbCollision;
$shouldOpenModal = (($previewResult !== null || $errorMessage !== null) && !($generationResult && !$errorMessage));
$generatedCount = count(array_filter($records, static fn(array $record): bool => strtoupper((string)($record['f_status'] ?? 'GENERATED')) === 'GENERATED'));
$archivedCount = count(array_filter($records, static fn(array $record): bool => strtoupper((string)($record['f_status'] ?? '')) === 'ARCHIVED'));
?>
<!DOCTYPE html>
<html lang="<?= h($lang) ?>" data-bs-theme="<?= h($_SESSION['theme.layout'] ?? 'light') ?>">
<head>
    <?php include __DIR__ . '/../includes/head.php'; ?>
    <meta name="csrf-token" content="<?= h($csrf) ?>">
    <link href="<?= h(base_url('assets/css/datatables-standard.css')) ?>?v=<?= h($version) ?>" rel="stylesheet">
    <link href="<?= h(base_url('assets/css/pages/template-generator.css')) ?>?v=<?= h($version) ?>" rel="stylesheet">
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
                            <h4 class="mb-sm-0 template-generator-page-title"><i class="ri-layout-grid-line"></i><?= h(t('pageTemplateGenerator_header_title', 'System Template Generator')) ?></h4>
                            <div class="page-title-right">
                                <ol class="breadcrumb m-0">
                                    <li class="breadcrumb-item"><a href="<?= h(base_url('pages/dashboard.php')) ?>"><?= h(t('common_dashboard', 'Dashboard')) ?></a></li>
                                    <li class="breadcrumb-item active"><?= h(t('pageTemplateGenerator_breadcrumb_active', 'System Template Generator')) ?></li>
                                </ol>
                            </div>
                        </div>
                    </div>
                </div>

                <?php if ($errorMessage && !$shouldOpenModal): ?>
                    <div class="alert alert-danger"><?= h($errorMessage) ?></div>
                <?php endif; ?>

                <section class="tg-hero mb-4">
                    <div class="tg-hero-copy">
                        <span class="tg-eyebrow"><i class="ri-code-box-line"></i><?= h(t('pageTemplateGenerator_eyebrow', 'Developer Workspace')) ?></span>
                        <h1><?= h(t('pageTemplateGenerator_hero_title', 'Build governed pages with confidence')) ?></h1>
                        <p><?= h(t('pageTemplateGenerator_hero_subtitle', 'Create consistent page foundations, validate every output, and retain a clear generation record.')) ?></p>
                        <button type="button" class="btn btn-primary tg-primary-btn" data-bs-toggle="modal" data-bs-target="#createTemplateModal">
                            <i class="ri-add-line me-1"></i><?= h(t('pageTemplateGenerator_action_create', 'Create New Template')) ?>
                        </button>
                    </div>
                    <div class="tg-hero-metrics" aria-label="<?= h(t('pageTemplateGenerator_summary_label', 'Template summary')) ?>">
                        <div><span><?= count($records) ?></span><small><?= h(t('pageTemplateGenerator_metric_total', 'Total templates')) ?></small></div>
                        <div><span><?= $generatedCount ?></span><small><?= h(t('pageTemplateGenerator_metric_generated', 'Ready')) ?></small></div>
                        <div><span><?= $archivedCount ?></span><small><?= h(t('pageTemplateGenerator_metric_archived', 'Archived')) ?></small></div>
                    </div>
                </section>

                <div class="row g-4">
                    <div class="col-12">
                        <div class="card template-generator-shell">
                            <div class="card-body d-flex flex-wrap justify-content-between align-items-center gap-3">
                                <div>
                                    <h5 class="card-title mb-1"><?= h(t('pageTemplateGenerator_list_title', 'Generated Templates')) ?></h5>
                                    <p class="text-muted mb-0"><?= h(t('pageTemplateGenerator_list_subtitle', 'Manage generated page templates and review the output artifacts created by the system.')) ?></p>
                                </div>
                                <span class="tg-security-note"><i class="ri-shield-check-line"></i><?= h(t('pageTemplateGenerator_super_admin_note', 'Super Admin controlled')) ?></span>
                            </div>
                        </div>
                    </div>

                    <div class="col-12">
                        <div class="card template-generator-table-card">
                            <div class="card-body">
                                <table id="templateGeneratorDT" class="table table-striped table-bordered align-middle w-100">
                                    <thead>
                                    <tr>
                                        <th class="col-bil" data-orderable="false">#</th>
                                        <th class="col-template"><?= h(t('pageTemplateGenerator_col_template_name', 'Template Name')) ?></th>
                                        <th class="col-type"><?= h(t('pageTemplateGenerator_col_type', 'Type')) ?></th>
                                        <th class="col-page"><?= h(t('pageTemplateGenerator_col_page', 'Page')) ?></th>
                                        <th class="col-status"><?= h(t('pageTemplateGenerator_col_status', 'Status')) ?></th>
                                        <th class="col-updated"><?= h(t('pageTemplateGenerator_col_last_updated', 'Last Updated')) ?></th>
                                        <th class="col-actions" data-orderable="false"><?= h(t('pageTemplateGenerator_col_actions', 'Actions')) ?></th>
                                    </tr>
                                    </thead>
                                    <tbody>
                                    <?php foreach ($records as $index => $record): ?>
                                        <?php
                                        $updatedAt = (string)($record['f_updatedt'] ?: $record['f_insertdt'] ?: '');
                                        $status = strtoupper(trim((string)($record['f_status'] ?? 'GENERATED')));
                                        $statusClass = match ($status) {
                                            'FAILED' => 'bg-danger-subtle text-danger',
                                            'ARCHIVED' => 'bg-secondary-subtle text-secondary',
                                            default => 'bg-success-subtle text-success',
                                        };
                                        $generationSummary = json_decode((string)($record['f_generationSummary'] ?? ''), true);
                                        if (!is_array($generationSummary)) {
                                            $generationSummary = [];
                                        }
                                        $detailPayload = [
                                            'template_name' => (string)($record['f_templateName'] ?? ''),
                                            'template_type' => (string)($record['f_templateType'] ?? ''),
                                            'page_slug' => (string)($record['f_pageSlug'] ?? ''),
                                            'page_icon' => (string)($record['f_pageIcon'] ?? ''),
                                            'controller_class' => (string)($record['f_controllerClass'] ?? ''),
                                            'status' => $status,
                                            'updated_at' => $updatedAt,
                                            'update_by' => (string)($record['f_updateby'] ?? ''),
                                            'page_path' => (string)($record['f_outputPagePath'] ?? ''),
                                            'controller_path' => (string)($record['f_outputControllerPath'] ?? ''),
                                            'css_path' => (string)($record['f_outputCssPath'] ?? ''),
                                            'access_mode' => (string)($generationSummary['access_mode'] ?? FileGenerationService::ACCESS_MODE_GROUP_MENU),
                                        ];
                                        ?>
                                        <tr>
                                            <td class="col-bil"><?= (int)$index + 1 ?></td>
                                            <td class="col-template">
                                                <div class="tg-template-name">
                                                    <i class="<?= h((string)($record['f_pageIcon'] ?? 'ri-file-list-line')) ?>"></i>
                                                    <div>
                                                        <div class="fw-semibold truncate-1line"><?= h((string)($record['f_templateName'] ?? '')) ?></div>
                                                        <div class="small text-muted truncate-1line"><?= h((string)($record['f_controllerClass'] ?? '')) ?></div>
                                                    </div>
                                                </div>
                                            </td>
                                            <td class="col-type"><span class="badge tg-type-badge"><?= h(ucfirst((string)($record['f_templateType'] ?? ''))) ?></span></td>
                                            <td class="col-page">
                                                <div class="fw-semibold truncate-1line"><?= h((string)($record['f_pageSlug'] ?? '')) ?></div>
                                                <div class="small text-muted truncate-1line"><?= h('pages/' . (string)($record['f_pageSlug'] ?? '') . '.php') ?></div>
                                            </td>
                                            <td class="col-status"><span class="badge <?= h($statusClass) ?>"><?= h(t('pageTemplateGenerator_status_' . strtolower($status), $status)) ?></span></td>
                                            <td class="col-updated">
                                                <div class="truncate-1line"><?= h($updatedAt !== '' ? $updatedAt : '-') ?></div>
                                                <div class="small text-muted truncate-1line"><?= h((string)($record['f_updateby'] ?? '-')) ?></div>
                                            </td>
                                            <td class="col-actions">
                                                <div class="tg-action-group">
                                                    <button type="button"
                                                            class="btn btn-outline-primary tg-icon-btn"
                                                            data-bs-toggle="modal"
                                                            data-bs-target="#templateDetailModal"
                                                            data-template='<?= h(json_encode($detailPayload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) ?>'
                                                            title="<?= h(t('pageTemplateGenerator_btn_view', 'View')) ?>"
                                                            aria-label="<?= h(t('pageTemplateGenerator_btn_view', 'View')) ?>">
                                                        <i class="ri-eye-line"></i>
                                                    </button>
                                                    <a href="<?= h(base_url('pages/' . (string)($record['f_pageSlug'] ?? '') . '.php')) ?>"
                                                       target="_blank"
                                                       rel="noopener noreferrer"
                                                       class="btn btn-outline-success tg-icon-btn"
                                                       title="<?= h(t('pageTemplateGenerator_btn_visit', 'Visit Page')) ?>"
                                                       aria-label="<?= h(t('pageTemplateGenerator_btn_visit', 'Visit Page')) ?>">
                                                        <i class="ri-external-link-line"></i>
                                                    </a>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php include __DIR__ . '/../includes/footer.php'; ?>
    </div>
</div>

<div class="modal fade" id="createTemplateModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl tg-create-modal-dialog">
        <div class="modal-content tg-modal-shell">
            <div class="modal-header tg-modal-header">
                <div>
                    <h5 class="modal-title"><?= h(t('pageTemplateGenerator_modal_create_title', 'Create New Template')) ?></h5>
                    <p class="mb-0 text-white-50 small"><?= h(t('pageTemplateGenerator_modal_create_subtitle', 'Define the template identity, review the output, and create the generated files in one flow.')) ?></p>
                </div>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="<?= h(t('pageTemplateGenerator_btn_close', 'Close')) ?>"></button>
            </div>
            <div class="modal-body">
                <?php if ($errorMessage): ?>
                    <div class="alert alert-danger mb-3"><?= h($errorMessage) ?></div>
                <?php endif; ?>
                <form method="post" action="" id="templateGeneratorForm">
                    <input type="hidden" name="csrf_token" value="<?= h($csrf) ?>">
                    <div class="row g-3 tg-create-modal-grid">
                        <div class="col-xl-7">
                            <div class="tg-form-panel">
                                <div class="tg-required-note mb-3">
                                    <i class="ri-information-line me-1"></i><?= h(t('pageTemplateGenerator_required_note', 'Fields marked with * are required.')) ?>
                                </div>
                                <div class="alert alert-info py-2 mb-3">
                                    <div class="fw-semibold mb-1"><i class="ri-shield-check-line me-1"></i><?= h(t('pageTemplateGenerator_governance_title', 'Governance Checklist')) ?></div>
                                    <div class="small"><?= h(t('pageTemplateGenerator_governance_text', 'Generated pages must keep language keys, access registration, and audit hooks aligned before production use.')) ?></div>
                                    <div class="d-flex flex-wrap gap-2 mt-2">
                                        <span class="badge bg-info-subtle text-info-emphasis"><?= h(t('pageTemplateGenerator_governance_language', 'Language keys')) ?></span>
                                        <span class="badge bg-info-subtle text-info-emphasis"><?= h(t('pageTemplateGenerator_governance_audit', 'Audit hooks')) ?></span>
                                        <span class="badge bg-info-subtle text-info-emphasis"><?= h(t('pageTemplateGenerator_governance_access', 'Access control')) ?></span>
                                    </div>
                                </div>
                                <ul class="nav nav-pills tg-form-tabs tg-stepper mb-3" id="templateGeneratorCreateTabs" role="tablist">
                                    <li class="nav-item" role="presentation">
                                        <button class="nav-link active" id="template-generator-form-tab" data-bs-toggle="tab" data-bs-target="#template-generator-form-pane" type="button" role="tab" aria-controls="template-generator-form-pane" aria-selected="true"><span>1</span><?= h(t('pageTemplateGenerator_step_identity', 'Page information')) ?></button>
                                    </li>
                                    <li class="nav-item" role="presentation">
                                        <button class="nav-link" id="template-generator-icon-tab" data-bs-toggle="tab" data-bs-target="#template-generator-icon-pane" type="button" role="tab" aria-controls="template-generator-icon-pane" aria-selected="false"><span>2</span><?= h(t('pageTemplateGenerator_step_branding', 'Icon selection')) ?></button>
                                    </li>
                                    <li class="nav-item" role="presentation">
                                        <button class="nav-link" id="template-generator-access-tab" data-bs-toggle="tab" data-bs-target="#template-generator-access-pane" type="button" role="tab" aria-controls="template-generator-access-pane" aria-selected="false"><span>3</span><?= h(t('pageTemplateGenerator_step_access', 'Access policy')) ?></button>
                                    </li>
                                </ul>
                                <div class="tab-content tg-form-tab-content">
                                    <div class="tab-pane fade show active tg-form-tab-pane" id="template-generator-form-pane" role="tabpanel" aria-labelledby="template-generator-form-tab" tabindex="0">
                                        <div class="row g-3">
                                            <div class="col-md-6">
                                                <label for="template_name" class="form-label"><?= h(t('pageTemplateGenerator_field_template_name', 'Template Name')) ?> <span class="text-danger">*</span></label>
                                                <input type="text" maxlength="120" class="form-control <?= isset($fieldErrors['template_name']) ? 'is-invalid' : '' ?>" id="template_name" name="template_name" value="<?= h($form['template_name'] ?? '') ?>" placeholder="<?= h(t('pageTemplateGenerator_field_template_name_placeholder', 'Example: Student Listing Base')) ?>" required>
                                                <?php if (isset($fieldErrors['template_name'])): ?><div class="invalid-feedback d-block"><?= h((string)$fieldErrors['template_name']) ?></div><?php endif; ?>
                                            </div>
                                            <div class="col-md-6">
                                                <label for="template_key" class="form-label"><?= h(t('pageTemplateGenerator_field_template', 'Template Type')) ?> <span class="text-danger">*</span></label>
                                                <select class="form-select <?= isset($fieldErrors['template_key']) ? 'is-invalid' : '' ?>" id="template_key" name="template_key" required>
                                                    <option value="" <?= (($form['template_key'] ?? '') === '') ? 'selected' : '' ?> disabled><?= h(t('pageTemplateGenerator_field_template_placeholder', 'Please select a template type')) ?></option>
                                                    <?php foreach ($templates as $template): $templateKey = (string)($template['key'] ?? ''); ?>
                                                        <option value="<?= h($templateKey) ?>" <?= (($form['template_key'] ?? '') === $templateKey) ? 'selected' : '' ?>><?= h((string)($template['label'] ?? $templateKey)) ?></option>
                                                    <?php endforeach; ?>
                                                </select>
                                                <?php if (isset($fieldErrors['template_key'])): ?><div class="invalid-feedback d-block"><?= h((string)$fieldErrors['template_key']) ?></div><?php endif; ?>
                                            </div>
                                            <div class="col-md-6">
                                                <label for="page_title_ms" class="form-label"><?= h(t('pageTemplateGenerator_field_title_ms', 'Page Title (MS)')) ?> <span class="text-danger">*</span></label>
                                                <input type="text" maxlength="160" class="form-control <?= isset($fieldErrors['page_title_ms']) ? 'is-invalid' : '' ?>" id="page_title_ms" name="page_title_ms" value="<?= h($form['page_title_ms'] ?? '') ?>" placeholder="<?= h(t('pageTemplateGenerator_field_title_ms_placeholder', 'Contoh: Senarai Pelajar')) ?>" required>
                                                <?php if (isset($fieldErrors['page_title_ms'])): ?><div class="invalid-feedback d-block"><?= h((string)$fieldErrors['page_title_ms']) ?></div><?php endif; ?>
                                            </div>
                                            <div class="col-md-6">
                                                <label for="page_title_en" class="form-label"><?= h(t('pageTemplateGenerator_field_title_en', 'Page Title (EN)')) ?> <span class="text-danger">*</span></label>
                                                <input type="text" maxlength="160" class="form-control <?= isset($fieldErrors['page_title_en']) ? 'is-invalid' : '' ?>" id="page_title_en" name="page_title_en" value="<?= h($form['page_title_en'] ?? '') ?>" placeholder="<?= h(t('pageTemplateGenerator_field_title_en_placeholder', 'Example: Student List')) ?>" required>
                                                <?php if (isset($fieldErrors['page_title_en'])): ?><div class="invalid-feedback d-block"><?= h((string)$fieldErrors['page_title_en']) ?></div><?php endif; ?>
                                            </div>
                                            <div class="col-12">
                                                <label for="page_name" class="form-label"><?= h(t('pageTemplateGenerator_field_page_name', 'Page Name')) ?> <span class="text-danger">*</span></label>
                                                <input type="text" maxlength="80" class="form-control <?= isset($fieldErrors['page_name']) ? 'is-invalid' : '' ?>" id="page_name" name="page_name" value="<?= h($form['page_name'] ?? '') ?>" placeholder="<?= h(t('pageTemplateGenerator_field_page_name_placeholder', 'Example: senarai pelajar')) ?>" required>
                                                <?php if (isset($fieldErrors['page_name'])): ?><div class="invalid-feedback d-block"><?= h((string)$fieldErrors['page_name']) ?></div><?php endif; ?>
                                                <div class="form-text"><?= h(t('pageTemplateGenerator_field_page_name_help', 'The system will normalize this value into a page slug and controller class name.')) ?></div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="tab-pane fade tg-form-tab-pane" id="template-generator-icon-pane" role="tabpanel" aria-labelledby="template-generator-icon-tab" tabindex="0">
                                        <div class="tg-access-panel-copy">
                                            <h6 class="mb-1"><?= h(t('pageTemplateGenerator_field_icon', 'Page Icon')) ?></h6>
                                            <p class="text-muted small mb-0"><?= h(t('pageTemplateGenerator_tab_page_icon_help', 'Choose an icon that best represents the generated page in the sidebar and module listing.')) ?></p>
                                        </div>
                                        <label class="form-label d-block mb-2"><?= h(t('pageTemplateGenerator_field_icon', 'Page Icon')) ?> <span class="text-danger">*</span></label>
                                        <div class="icon-picker-grid">
                                            <?php foreach ($iconOptions as $index => $icon): ?>
                                                <label class="icon-picker-option">
                                                    <input type="radio" name="page_icon" value="<?= h($icon) ?>" <?= ($selectedIcon === $icon) ? 'checked' : '' ?> <?= $index === 0 ? 'required' : '' ?>>
                                                    <span class="icon-picker-card">
                                                        <i class="<?= h($icon) ?>"></i>
                                                    </span>
                                                </label>
                                            <?php endforeach; ?>
                                        </div>
                                        <?php if (isset($fieldErrors['page_icon'])): ?><div class="small text-danger mt-2"><?= h((string)$fieldErrors['page_icon']) ?></div><?php endif; ?>
                                    </div>
                                    <div class="tab-pane fade tg-form-tab-pane" id="template-generator-access-pane" role="tabpanel" aria-labelledby="template-generator-access-tab" tabindex="0">
                                        <div class="tg-access-panel-copy">
                                            <h6 class="mb-1"><?= h(t('pageTemplateGenerator_field_access_mode', 'Access Mode')) ?></h6>
                                            <p class="text-muted small mb-3"><?= h(t('pageTemplateGenerator_field_access_mode_help', 'Choose Super Admin Only for highly sensitive pages. Choose Group Menu Based for pages that will be granted through menu access and group assignment.')) ?></p>
                                        </div>
                                        <div class="vstack gap-2">
                                            <label class="tg-access-option">
                                                <div class="form-check mb-1">
                                                    <input class="form-check-input" type="radio" name="access_mode" id="access_mode_group_menu" value="<?= h(FileGenerationService::ACCESS_MODE_GROUP_MENU) ?>" <?= $selectedAccessMode === FileGenerationService::ACCESS_MODE_GROUP_MENU ? 'checked' : '' ?>>
                                                    <label class="form-check-label fw-semibold" for="access_mode_group_menu"><?= h(t('pageTemplateGenerator_access_group_menu_title', 'Group Menu Based')) ?></label>
                                                </div>
                                                <div class="small text-muted"><?= h(t('pageTemplateGenerator_access_group_menu_help', 'The page follows menu access. Only groups that are assigned to the menu path can open it.')) ?></div>
                                            </label>
                                            <label class="tg-access-option">
                                                <div class="form-check mb-1">
                                                    <input class="form-check-input" type="radio" name="access_mode" id="access_mode_super_admin" value="<?= h(FileGenerationService::ACCESS_MODE_SUPER_ADMIN_ONLY) ?>" <?= $selectedAccessMode === FileGenerationService::ACCESS_MODE_SUPER_ADMIN_ONLY ? 'checked' : '' ?>>
                                                    <label class="form-check-label fw-semibold" for="access_mode_super_admin"><?= h(t('pageTemplateGenerator_access_super_admin_title', 'Super Admin Only')) ?></label>
                                                </div>
                                                <div class="small text-muted"><?= h(t('pageTemplateGenerator_access_super_admin_help', 'The page is locked at policy level and can only be opened by Super Admin.')) ?></div>
                                            </label>
                                        </div>
                                        <?php if (isset($fieldErrors['access_mode'])): ?><div class="small text-danger mt-2"><?= h((string)$fieldErrors['access_mode']) ?></div><?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-xl-5">
                            <div class="tg-preview-panel">
                                <div class="d-flex align-items-center justify-content-between mb-3">
                                    <div>
                                        <h6 class="mb-1"><?= h(t('pageTemplateGenerator_preview_title', 'Preview Summary')) ?></h6>
                                        <p class="text-muted mb-0 small"><?= h(t('pageTemplateGenerator_preview_subtitle', 'Review file names and collision status before generation.')) ?></p>
                                    </div>
                                    <div class="tg-preview-icon"><i class="<?= h((string)($result['page_icon'] ?? $selectedIcon)) ?>" data-preview-icon></i></div>
                                </div>
                                <?php if (!$previewResult && !$generationResult): ?>
                                    <div class="tg-preview-empty"><?= h(t('pageTemplateGenerator_preview_empty', 'No preview yet. Fill in the form and click Preview Output.')) ?></div>
                                <?php else: ?>
                                    <div class="vstack gap-3">
                                        <div class="tg-preview-toggle-wrap">
                                            <button type="button" class="btn btn-sm btn-light tg-preview-toggle" data-preview-toggle="meta" aria-expanded="false">
                                                <i class="ri-eye-off-line me-1"></i><?= h(t('pageTemplateGenerator_preview_toggle_show', 'Show Preview Meta')) ?>
                                            </button>
                                        </div>
                                        <div class="tg-preview-meta d-none" data-preview-section="meta">
                                            <div><span class="tg-preview-label"><?= h(t('pageTemplateGenerator_preview_template', 'Template')) ?></span><strong><?= h((string)($result['template_label'] ?? '')) ?></strong></div>
                                            <div><span class="tg-preview-label"><?= h(t('pageTemplateGenerator_preview_slug', 'Page Slug')) ?></span><strong><?= h((string)($result['page_slug'] ?? '')) ?></strong></div>
                                            <div><span class="tg-preview-label"><?= h(t('pageTemplateGenerator_preview_controller', 'Controller Class')) ?></span><strong><?= h((string)($result['controller_class'] ?? '')) ?></strong></div>
                                            <div><span class="tg-preview-label"><?= h(t('pageTemplateGenerator_field_access_mode', 'Access Mode')) ?></span><strong><?= h(t('pageTemplateGenerator_access_mode_' . (string)($result['access_mode'] ?? $selectedAccessMode), (string)($result['access_mode'] ?? $selectedAccessMode))) ?></strong></div>
                                        </div>
                                        <div class="tg-preview-files">
                                            <div class="tg-preview-file-row">
                                                <div><div class="fw-semibold"><?= h(t('pageTemplateGenerator_preview_page_file', 'Page File')) ?></div><div class="small text-break"><?= h((string)($result['files']['page'] ?? '')) ?></div></div>
                                                <span class="badge <?= !empty($result['exists']['page']) ? 'bg-danger-subtle text-danger' : 'bg-success-subtle text-success' ?>"><?= h(!empty($result['exists']['page']) ? t('pageTemplateGenerator_exists_yes', 'Already exists') : t('pageTemplateGenerator_exists_no', 'Available')) ?></span>
                                            </div>
                                            <div class="tg-preview-file-row">
                                                <div><div class="fw-semibold"><?= h(t('pageTemplateGenerator_preview_controller_file', 'Controller File')) ?></div><div class="small text-break"><?= h((string)($result['files']['controller'] ?? '')) ?></div></div>
                                                <span class="badge <?= !empty($result['exists']['controller']) ? 'bg-danger-subtle text-danger' : 'bg-success-subtle text-success' ?>"><?= h(!empty($result['exists']['controller']) ? t('pageTemplateGenerator_exists_yes', 'Already exists') : t('pageTemplateGenerator_exists_no', 'Available')) ?></span>
                                            </div>
                                            <div class="tg-preview-file-row">
                                                <div><div class="fw-semibold"><?= h(t('pageTemplateGenerator_preview_css_file', 'CSS File')) ?></div><div class="small text-break"><?= h((string)($result['files']['css'] ?? '')) ?></div></div>
                                                <span class="badge <?= !empty($result['exists']['css']) ? 'bg-danger-subtle text-danger' : 'bg-success-subtle text-success' ?>"><?= h(!empty($result['exists']['css']) ? t('pageTemplateGenerator_exists_yes', 'Already exists') : t('pageTemplateGenerator_exists_no', 'Available')) ?></span>
                                            </div>
                                            <div class="tg-preview-file-row">
                                                <div><div class="fw-semibold"><?= h(t('pageTemplateGenerator_preview_db_slug', 'Database Slug')) ?></div><div class="small text-muted"><?= h(t('pageTemplateGenerator_preview_db_slug_help', 'Checks whether the page slug already exists in template records.')) ?></div></div>
                                                <span class="badge <?= $dbSlugExists ? 'bg-danger-subtle text-danger' : 'bg-success-subtle text-success' ?>"><?= h($dbSlugExists ? t('pageTemplateGenerator_exists_yes', 'Already exists') : t('pageTemplateGenerator_exists_no', 'Available')) ?></span>
                                            </div>
                                            <div class="tg-preview-file-row">
                                                <div><div class="fw-semibold"><?= h(t('pageTemplateGenerator_preview_db_controller', 'Database Controller')) ?></div><div class="small text-muted"><?= h(t('pageTemplateGenerator_preview_db_controller_help', 'Checks whether the controller class already exists in template records.')) ?></div></div>
                                                <span class="badge <?= $dbControllerExists ? 'bg-danger-subtle text-danger' : 'bg-success-subtle text-success' ?>"><?= h($dbControllerExists ? t('pageTemplateGenerator_exists_yes', 'Already exists') : t('pageTemplateGenerator_exists_no', 'Available')) ?></span>
                                            </div>
                                        </div>
                                        <div>
                                            <span class="tg-preview-label d-block"><?= h(t('pageTemplateGenerator_generation_status', 'Generation Status')) ?></span>
                                            <div class="tg-readiness <?= $hasCollision ? 'is-blocked' : 'is-ready' ?>"><i class="<?= $hasCollision ? 'ri-error-warning-line' : 'ri-checkbox-circle-line' ?>"></i><strong><?= h($hasCollision ? t('pageTemplateGenerator_generation_status_blocked', 'Blocked') : t('pageTemplateGenerator_generation_status_ready', 'Ready to generate')) ?></strong></div>
                                            <?php if ($hasCollision): ?><div class="small text-danger mt-2"><?= h(t('pageTemplateGenerator_generation_blocked', 'Generation disabled because one or more target files already exist.')) ?></div><?php endif; ?>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <div class="d-flex flex-wrap justify-content-end gap-2 mt-4">
                        <button type="button" class="btn btn-light tg-secondary-btn" data-bs-dismiss="modal"><?= h(t('pageTemplateGenerator_btn_close', 'Close')) ?></button>
                        <button type="submit" name="generator_action" value="preview" class="btn btn-outline-primary tg-secondary-btn"><?= h(t('pageTemplateGenerator_btn_preview', 'Preview Output')) ?></button>
                        <button type="submit" name="generator_action" value="generate" class="btn btn-primary tg-primary-btn" <?= (!$previewResult || $hasCollision) ? 'disabled' : '' ?>><?= h(t('pageTemplateGenerator_btn_generate', 'Generate Files')) ?></button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="templateDetailModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content tg-detail-shell">
            <div class="modal-header tg-detail-header">
                <div>
                    <h5 class="modal-title"><?= h(t('pageTemplateGenerator_detail_title', 'Template Details')) ?></h5>
                    <p class="mb-0 text-white-50 small"><?= h(t('pageTemplateGenerator_detail_subtitle', 'Review the generated metadata and output paths for this template record.')) ?></p>
                </div>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="<?= h(t('pageTemplateGenerator_btn_close', 'Close')) ?>"></button>
            </div>
            <div class="modal-body">
                <div class="tg-detail-overview mb-3"><div class="tg-detail-icon"><i class="ri-pages-line"></i></div><div><span class="tg-preview-label"><?= h(t('pageTemplateGenerator_detail_overview', 'Generated asset')) ?></span><h5 class="mb-0" data-detail-template-name>-</h5></div><span class="badge bg-success-subtle text-success ms-auto" data-detail-status>-</span></div>
                <div class="row g-3 tg-detail-grid">
                    <div class="col-md-6"><span class="tg-preview-label d-block"><?= h(t('pageTemplateGenerator_col_type', 'Type')) ?></span><div class="fw-semibold" data-detail-template-type>-</div></div>
                    <div class="col-md-6"><span class="tg-preview-label d-block"><?= h(t('pageTemplateGenerator_preview_slug', 'Page Slug')) ?></span><div class="fw-semibold" data-detail-page-slug>-</div></div>
                    <div class="col-md-6"><span class="tg-preview-label d-block"><?= h(t('pageTemplateGenerator_preview_controller', 'Controller Class')) ?></span><div class="fw-semibold" data-detail-controller-class>-</div></div>
                    <div class="col-md-6"><span class="tg-preview-label d-block"><?= h(t('pageTemplateGenerator_field_access_mode', 'Access Mode')) ?></span><div class="fw-semibold" data-detail-access-mode>-</div></div>
                    <div class="col-md-6"><span class="tg-preview-label d-block"><?= h(t('pageTemplateGenerator_detail_created_by', 'Generated by')) ?></span><div class="fw-semibold" data-detail-update-by>-</div></div>
                    <div class="col-md-6"><span class="tg-preview-label d-block"><?= h(t('pageTemplateGenerator_col_last_updated', 'Last Updated')) ?></span><div class="fw-semibold" data-detail-updated-at>-</div></div>
                    <div class="col-12"><span class="tg-preview-label d-block"><?= h(t('pageTemplateGenerator_detail_outputs', 'Output files')) ?></span><div class="tg-output-list"><div><i class="ri-pages-line"></i><code data-detail-page-path>-</code><button type="button" data-copy-detail="page_path" aria-label="<?= h(t('pageTemplateGenerator_btn_copy', 'Copy path')) ?>"><i class="ri-file-copy-line"></i></button></div><div><i class="ri-code-s-slash-line"></i><code data-detail-controller-path>-</code><button type="button" data-copy-detail="controller_path" aria-label="<?= h(t('pageTemplateGenerator_btn_copy', 'Copy path')) ?>"><i class="ri-file-copy-line"></i></button></div><div><i class="ri-palette-line"></i><code data-detail-css-path>-</code><button type="button" data-copy-detail="css_path" aria-label="<?= h(t('pageTemplateGenerator_btn_copy', 'Copy path')) ?>"><i class="ri-file-copy-line"></i></button></div></div></div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light tg-secondary-btn" data-bs-dismiss="modal"><?= h(t('pageTemplateGenerator_btn_close', 'Close')) ?></button>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../includes/script.php'; ?>
<script>
window.TemplateGeneratorPageData = <?= json_encode([
    'shouldOpenCreateModal' => $shouldOpenModal,
    'hasSuccess' => $generationResult && !$errorMessage,
    'successTitle' => t('pageTemplateGenerator_success_title', 'Files Generated Successfully'),
    'successText' => $successMessage ?: t('pageTemplateGenerator_success_generate', 'Files were generated successfully.'),
    'successFiles' => (array)($generationResult['files_created'] ?? []),
    'closeLabel' => t('pageTemplateGenerator_btn_ok', 'OK'),
    'previewToggleShow' => t('pageTemplateGenerator_preview_toggle_show', 'Show Preview Meta'),
    'previewToggleHide' => t('pageTemplateGenerator_preview_toggle_hide', 'Hide Preview Meta'),
    'accessModeLabels' => [
        FileGenerationService::ACCESS_MODE_GROUP_MENU => t('pageTemplateGenerator_access_mode_group_menu_based', 'Group Menu Based'),
        FileGenerationService::ACCESS_MODE_SUPER_ADMIN_ONLY => t('pageTemplateGenerator_access_mode_super_admin_only', 'Super Admin Only'),
    ],
    'copySuccess' => t('pageTemplateGenerator_copy_success', 'Path copied'),
    'copyFailed' => t('pageTemplateGenerator_copy_failed', 'Unable to copy path'),
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
</script>
<script src="<?= h(base_url('assets/js/pages/template-generator.js')) ?>?v=<?= h($version) ?>"></script>
</body>
</html>
