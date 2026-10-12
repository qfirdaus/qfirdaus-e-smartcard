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
require_once __DIR__ . '/_helpers.php';
require_once __DIR__ . '/../classes/Database.php';
require_once __DIR__ . '/../classes/EmailTemplate.php';
require_once __DIR__ . '/../classes/SystemConfigConstants.php';
require_once __DIR__ . '/../setting/helper/audit_helper.php';

header('Content-Type: application/json; charset=utf-8');

function emailTemplateActionH($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function emailTemplateActionT(string $key, string $fallback): string
{
    $value = __($key);
    return ($value === $key || $value === null || $value === '') ? $fallback : (string)$value;
}

/**
 * @return array<string,string>
 */
function emailTemplateActionRoleOptions(): array
{
    $options = [];
    foreach (SystemConfigConstants::ALLOWED_EMAIL_TEMPLATE_ROLES as $roleCode) {
        $options[$roleCode] = emailTemplateActionT('emailTemplate_role_' . $roleCode, ucfirst($roleCode));
    }

    return $options;
}

/**
 * @return array<string,string>
 */
function emailTemplateActionCategoryOptions(): array
{
    $options = [];
    foreach (SystemConfigConstants::ALLOWED_EMAIL_TEMPLATE_CATEGORIES as $categoryCode) {
        $options[$categoryCode] = emailTemplateActionT('emailTemplate_category_' . $categoryCode, ucfirst($categoryCode));
    }

    return $options;
}

/**
 * @return array<string,string>
 */
function emailTemplateActionStatusOptions(): array
{
    $options = [];
    foreach (SystemConfigConstants::ALLOWED_EMAIL_TEMPLATE_STATUSES as $statusCode) {
        $options[$statusCode] = emailTemplateActionT('emailTemplate_status_' . strtolower($statusCode), $statusCode);
    }

    return $options;
}

/**
 * @return array<string,mixed>
 */
function emailTemplateActionCollectFormData(): array
{
    return [
        'template_id' => (int)($_POST['template_id'] ?? 0),
        'template_code' => strtoupper(trim((string)($_POST['template_code'] ?? ''))),
        'template_name' => trim((string)($_POST['template_name'] ?? '')),
        'role_code' => strtolower(trim((string)($_POST['role_code'] ?? ''))),
        'category_code' => strtolower(trim((string)($_POST['category_code'] ?? ''))),
        'subject_template' => trim((string)($_POST['subject_template'] ?? '')),
        'body_html' => trim((string)($_POST['body_html'] ?? '')),
        'body_text' => trim((string)($_POST['body_text'] ?? '')),
        'status' => strtoupper(trim((string)($_POST['status'] ?? 'DRAFT'))),
        'is_default' => !empty($_POST['is_default']) ? 1 : 0,
        'description' => trim((string)($_POST['description'] ?? '')),
        'notes' => trim((string)($_POST['notes'] ?? '')),
    ];
}

/**
 * @return array<string,string>
 */
function emailTemplateActionValidate(array $form, EmailTemplate $model, array $roleOptions, array $categoryOptions, array $statusOptions): array
{
    $errors = [];
    $templateId = (int)($form['template_id'] ?? 0);
    $templateCode = trim((string)($form['template_code'] ?? ''));
    $templateName = trim((string)($form['template_name'] ?? ''));
    $roleCode = strtolower(trim((string)($form['role_code'] ?? '')));
    $categoryCode = strtolower(trim((string)($form['category_code'] ?? '')));
    $subjectTemplate = trim((string)($form['subject_template'] ?? ''));
    $bodyHtml = trim((string)($form['body_html'] ?? ''));
    $status = strtoupper(trim((string)($form['status'] ?? '')));

    if ($templateCode === '') {
        $errors['template_code'] = emailTemplateActionT('emailTemplate_error_template_code_required', 'Kod template adalah wajib.');
    } elseif (!preg_match('/^[A-Z0-9_\-]+$/', $templateCode)) {
        $errors['template_code'] = emailTemplateActionT('emailTemplate_error_template_code_format', 'Kod template hanya boleh mengandungi huruf besar, nombor, dash, dan underscore.');
    } elseif ($model->existsByCode($templateCode, $templateId > 0 ? $templateId : null)) {
        $errors['template_code'] = emailTemplateActionT('emailTemplate_error_template_code_exists', 'Kod template sudah digunakan.');
    }

    if ($templateName === '') {
        $errors['template_name'] = emailTemplateActionT('emailTemplate_error_template_name_required', 'Nama template adalah wajib.');
    }

    if ($roleCode === '' || !array_key_exists($roleCode, $roleOptions)) {
        $errors['role_code'] = emailTemplateActionT('emailTemplate_error_role_required', 'Peranan penerima adalah wajib.');
    }

    if ($categoryCode === '' || !array_key_exists($categoryCode, $categoryOptions)) {
        $errors['category_code'] = emailTemplateActionT('emailTemplate_error_category_required', 'Kategori emel adalah wajib.');
    }

    if ($subjectTemplate === '') {
        $errors['subject_template'] = emailTemplateActionT('emailTemplate_error_subject_required', 'Subjek template adalah wajib.');
    }

    if ($bodyHtml === '') {
        $errors['body_html'] = emailTemplateActionT('emailTemplate_error_body_html_required', 'Kandungan emel adalah wajib.');
    }

    if ($status === '' || !array_key_exists($status, $statusOptions)) {
        $errors['status'] = emailTemplateActionT('emailTemplate_error_status_required', 'Status template tidak sah.');
    }

    return $errors;
}

/**
 * @return array<string,mixed>
 */
function emailTemplateActionFilters(): array
{
    return [
        'role_code' => trim((string)($_POST['filter_role'] ?? '')),
        'category_code' => trim((string)($_POST['filter_category'] ?? '')),
        'status' => strtoupper(trim((string)($_POST['filter_status'] ?? ''))),
        'search' => trim((string)($_POST['filter_search'] ?? '')),
    ];
}

/**
 * @return array<int,array<string,mixed>>
 */
function emailTemplateActionSeedTemplates(): array
{
    $path = __DIR__ . '/../configuration/email_template_seeds.php';
    if (!is_file($path)) {
        return [];
    }

    $data = require $path;
    return is_array($data) ? array_values(array_filter($data, 'is_array')) : [];
}

function emailTemplateActionAudit(EmailTemplate $model, string $eventType, int $templateId, array $meta = [], ?array $templateRecord = null): void
{
    if ($templateId <= 0 || !function_exists('audit_event')) {
        return;
    }

    $template = $templateRecord ?: $model->findById($templateId);
    audit_event([
        'event_type' => $eventType,
        'severity' => 'INFO',
        'outcome' => 'SUCCESS',
        'target_type' => 'email_template',
        'target_id' => (string)$templateId,
        'target_label' => (string)($template['f_templateCode'] ?? $templateId),
        'message' => 'Email template action recorded.',
        'actor_label' => trim((string)($_SESSION['user']['f_nama'] ?? $_SESSION['f_nama'] ?? '')),
        'login_id' => trim((string)($_SESSION['f_loginID'] ?? $_SESSION['user']['f_loginID'] ?? '')),
        'meta' => array_merge([
            'module' => 'template-emel',
            'template_code' => (string)($template['f_templateCode'] ?? ''),
            'template_name' => (string)($template['f_templateName'] ?? ''),
        ], $meta),
    ]);
}

/**
 * @param array<string,string> $fieldErrors
 */
function emailTemplateActionValidationError(string $message, array $fieldErrors = []): never
{
    while (ob_get_level() > 0) {
        @ob_end_clean();
    }
    http_response_code(422);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'success' => false,
        'error' => true,
        'message' => $message,
        'field_errors' => $fieldErrors,
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

/**
 * @param array<int,array<string,mixed>> $records
 * @param array<int,int> $usageCounts
 */
function emailTemplateActionRenderRows(array $records, array $usageCounts, array $roleOptions, array $categoryOptions, array $statusOptions, string $csrf): string
{
    ob_start();
    foreach ($records as $index => $record) {
        $updatedAt = (string)($record['f_updatedt'] ?: $record['f_insertdt'] ?: '');
        $statusCode = strtoupper(trim((string)($record['f_status'] ?? 'DRAFT')));
        $statusLabel = $statusOptions[$statusCode] ?? $statusCode;
        $statusClass = match ($statusCode) {
            'ACTIVE' => 'bg-success-subtle text-success',
            'ARCHIVED' => 'bg-secondary-subtle text-secondary',
            default => 'bg-warning-subtle text-warning',
        };
        $templateId = (int)($record['f_templateID'] ?? 0);
        $usageCount = (int)($usageCounts[$templateId] ?? 0);
        $isDefault = !empty($record['f_isDefault']);
        $canDelete = !$isDefault && $usageCount === 0;
        $deleteDisabledReason = $isDefault
            ? emailTemplateActionT('emailTemplate_delete_default_tooltip', 'Tetapkan template lain sebagai default sebelum padam template ini.')
            : emailTemplateActionT('emailTemplate_delete_used_tooltip', 'Template yang pernah digunakan tidak boleh dipadam.');
        $editPayload = [
            'template_id' => $templateId,
            'template_code' => (string)($record['f_templateCode'] ?? ''),
            'template_name' => (string)($record['f_templateName'] ?? ''),
            'role_code' => (string)($record['f_roleCode'] ?? ''),
            'category_code' => (string)($record['f_categoryCode'] ?? ''),
            'subject_template' => (string)($record['f_subjectTemplate'] ?? ''),
            'body_html' => (string)($record['f_bodyHtml'] ?? ''),
            'body_text' => (string)($record['f_bodyText'] ?? ''),
            'status' => $statusCode,
            'is_default' => (int)$isDefault,
            'description' => (string)($record['f_description'] ?? ''),
            'notes' => (string)($record['f_notes'] ?? ''),
        ];
        ?>
        <tr data-template-id="<?= $templateId ?>">
            <td class="col-bil"><?= (int)$index + 1 ?></td>
            <td>
                <div class="et-template-cell">
                    <div class="fw-semibold truncate-1line"><?= emailTemplateActionH((string)($record['f_templateName'] ?? '')) ?></div>
                    <div class="small text-muted truncate-1line"><?= emailTemplateActionH((string)($record['f_templateCode'] ?? '')) ?></div>
                    <div class="et-template-meta">
                        <?php if ($isDefault): ?>
                            <span class="badge et-default-badge" data-bs-toggle="tooltip" data-bs-placement="top" title="<?= emailTemplateActionH(emailTemplateActionT('emailTemplate_default_note', 'Set another default first before archiving.')) ?>"><?= emailTemplateActionH(emailTemplateActionT('emailTemplate_badge_default_active', 'Active Default')) ?></span>
                        <?php endif; ?>
                    </div>
                </div>
            </td>
            <td class="et-col-usage"><span class="et-meta-chip"><?= $usageCount ?></span></td>
            <td><?= emailTemplateActionH($roleOptions[(string)($record['f_roleCode'] ?? '')] ?? (string)($record['f_roleCode'] ?? '-')) ?></td>
            <td><?= emailTemplateActionH($categoryOptions[(string)($record['f_categoryCode'] ?? '')] ?? (string)($record['f_categoryCode'] ?? '-')) ?></td>
            <td class="et-col-subject"><div class="truncate-2line"><?= emailTemplateActionH((string)($record['f_subjectTemplate'] ?? '')) ?></div></td>
            <td><span class="badge et-status-badge <?= emailTemplateActionH($statusClass) ?>"><?= emailTemplateActionH($statusLabel) ?></span></td>
            <td><div class="truncate-1line"><?= emailTemplateActionH($updatedAt !== '' ? $updatedAt : '-') ?></div><div class="small text-muted truncate-1line"><?= emailTemplateActionH((string)($record['f_updateby'] ?? '-')) ?></div></td>
            <td>
                <div class="et-action-group">
                    <button type="button" class="btn btn-outline-primary et-icon-btn" data-edit-template='<?= emailTemplateActionH(json_encode($editPayload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) ?>' onclick="window.EmailTemplateFallback && window.EmailTemplateFallback.openEdit(this);" title="<?= emailTemplateActionH(emailTemplateActionT('emailTemplate_btn_edit', 'Edit')) ?>" aria-label="<?= emailTemplateActionH(emailTemplateActionT('emailTemplate_btn_edit', 'Edit')) ?>">
                        <i class="ri-pencil-line"></i>
                    </button>
                    <form method="post" action="" class="d-inline-block" data-template-action-form="duplicate">
                        <input type="hidden" name="csrf_token" value="<?= emailTemplateActionH($csrf) ?>">
                        <input type="hidden" name="template_id" value="<?= $templateId ?>">
                        <input type="hidden" name="form_action" value="duplicate">
                        <button type="button" class="btn btn-outline-secondary et-icon-btn" title="<?= emailTemplateActionH(emailTemplateActionT('emailTemplate_btn_duplicate', 'Duplicate')) ?>" aria-label="<?= emailTemplateActionH(emailTemplateActionT('emailTemplate_btn_duplicate', 'Duplicate')) ?>" data-template-action-button>
                            <i class="ri-file-copy-line"></i>
                        </button>
                    </form>
                    <?php if ($statusCode !== 'ARCHIVED' && !$isDefault): ?>
                        <form method="post" action="" class="d-inline-block" data-template-action-form="archive" data-template-name="<?= emailTemplateActionH((string)($record['f_templateName'] ?? '')) ?>" data-template-code="<?= emailTemplateActionH((string)($record['f_templateCode'] ?? '')) ?>">
                            <input type="hidden" name="csrf_token" value="<?= emailTemplateActionH($csrf) ?>">
                            <input type="hidden" name="template_id" value="<?= $templateId ?>">
                            <input type="hidden" name="form_action" value="archive">
                            <button type="button" class="btn btn-outline-danger et-icon-btn" title="<?= emailTemplateActionH(emailTemplateActionT('emailTemplate_btn_archive', 'Archive')) ?>" aria-label="<?= emailTemplateActionH(emailTemplateActionT('emailTemplate_btn_archive', 'Archive')) ?>" data-template-action-button>
                                <i class="ri-archive-line"></i>
                            </button>
                        </form>
                    <?php elseif ($statusCode !== 'ARCHIVED' && $isDefault): ?>
                        <button type="button" class="btn btn-outline-secondary et-icon-btn" title="<?= emailTemplateActionH(emailTemplateActionT('emailTemplate_archive_default_tooltip', 'Set another template as default before archiving this one.')) ?>" aria-label="<?= emailTemplateActionH(emailTemplateActionT('emailTemplate_archive_default_tooltip', 'Set another template as default before archiving this one.')) ?>" disabled>
                            <i class="ri-archive-line"></i>
                        </button>
                    <?php endif; ?>
                    <?php if ($canDelete): ?>
                        <form method="post" action="" class="d-inline-block" data-template-action-form="delete" data-template-name="<?= emailTemplateActionH((string)($record['f_templateName'] ?? '')) ?>" data-template-code="<?= emailTemplateActionH((string)($record['f_templateCode'] ?? '')) ?>">
                            <input type="hidden" name="csrf_token" value="<?= emailTemplateActionH($csrf) ?>">
                            <input type="hidden" name="template_id" value="<?= $templateId ?>">
                            <input type="hidden" name="form_action" value="delete">
                            <button type="button" class="btn btn-outline-danger et-icon-btn" title="<?= emailTemplateActionH(emailTemplateActionT('emailTemplate_btn_delete', 'Padam')) ?>" aria-label="<?= emailTemplateActionH(emailTemplateActionT('emailTemplate_btn_delete', 'Padam')) ?>" data-template-action-button>
                                <i class="ri-delete-bin-line"></i>
                            </button>
                        </form>
                    <?php else: ?>
                        <button type="button" class="btn btn-outline-secondary et-icon-btn" title="<?= emailTemplateActionH($deleteDisabledReason) ?>" aria-label="<?= emailTemplateActionH($deleteDisabledReason) ?>" disabled>
                            <i class="ri-delete-bin-line"></i>
                        </button>
                    <?php endif; ?>
                </div>
            </td>
        </tr>
        <?php
    }

    return (string)ob_get_clean();
}

/**
 * @param array<int,array<string,mixed>> $seedTemplates
 */
function emailTemplateActionRenderEmptyState(array $seedTemplates, string $csrf): string
{
    ob_start();
    ?>
    <div class="et-empty-state">
        <div class="et-empty-icon"><i class="ri-mail-open-line"></i></div>
        <h6><?= emailTemplateActionH(emailTemplateActionT('emailTemplate_empty_title', 'Tiada template emel lagi')) ?></h6>
        <p class="text-muted mb-3"><?= emailTemplateActionH(emailTemplateActionT('emailTemplate_empty_subtitle', 'Mulakan dengan import seed template atau cipta template baharu secara manual.')) ?></p>
        <div class="d-flex flex-wrap justify-content-center gap-2">
            <?php if ($seedTemplates !== []): ?>
                <form method="post" action="" class="d-inline-block" data-template-action-form="seed_templates">
                    <input type="hidden" name="csrf_token" value="<?= emailTemplateActionH($csrf) ?>">
                    <input type="hidden" name="form_action" value="seed_templates">
                    <button type="button" class="btn btn-outline-primary" data-template-action-button><?= emailTemplateActionH(emailTemplateActionT('emailTemplate_btn_seed_templates', 'Import Seed Templates')) ?></button>
                </form>
            <?php endif; ?>
            <button type="button" class="btn btn-primary" data-create-template onclick="window.EmailTemplateFallback && window.EmailTemplateFallback.openCreate();"><?= emailTemplateActionH(emailTemplateActionT('emailTemplate_action_create', 'Tambah Template')) ?></button>
        </div>
    </div>
    <?php

    return (string)ob_get_clean();
}

/**
 * @param array<int,array<string,mixed>> $records
 * @return array<string,int>
 */
function emailTemplateActionBuildSummary(array $records): array
{
    $summary = [
        'total' => 0,
        'active' => 0,
        'draft' => 0,
        'archived' => 0,
    ];

    foreach ($records as $record) {
        $summary['total']++;
        $status = strtolower((string)($record['f_status'] ?? ''));
        if (array_key_exists($status, $summary)) {
            $summary[$status]++;
        }
    }

    return $summary;
}

try {
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
        jsonErrorResponse('Method not allowed', 405);
    }

    if (!isValidCsrfToken()) {
        jsonErrorResponse((string)(__('userGroup_csrf_invalid') ?: 'CSRF token tidak sah.'), 403);
    }

    if (!checkRateLimit('email_template_action', 30, 60)) {
        jsonErrorResponse(emailTemplateActionT('emailTemplate_error_rate_limited', 'Terlalu banyak permintaan. Sila tunggu sebentar dan cuba lagi.'), 429);
    }

    $pdo = Database::pdoMysql();
    ensureAjaxGroupManagePermission($pdo);

    $emailTemplateModel = new EmailTemplate($pdo);
    $roleOptions = emailTemplateActionRoleOptions();
    $categoryOptions = emailTemplateActionCategoryOptions();
    $statusOptions = emailTemplateActionStatusOptions();
    $seedTemplates = emailTemplateActionSeedTemplates();
    $formAction = strtolower(trim((string)($_POST['form_action'] ?? 'save')));
    $allowedActions = ['save', 'duplicate', 'archive', 'delete', 'seed_templates'];
    if (!in_array($formAction, $allowedActions, true)) {
        jsonErrorResponse(emailTemplateActionT('emailTemplate_error_invalid_action', 'Tindakan yang diminta tidak sah.'), 422);
    }

    $form = emailTemplateActionCollectFormData();
    $templateId = (int)($form['template_id'] ?? 0);
    $updateBy = (string)($_SESSION['f_stafID'] ?? $_SESSION['login_id'] ?? 'system');
    $message = '';

    if ($formAction === 'save') {
        $fieldErrors = emailTemplateActionValidate($form, $emailTemplateModel, $roleOptions, $categoryOptions, $statusOptions);
        if ($fieldErrors !== []) {
            emailTemplateActionValidationError(
                emailTemplateActionT('emailTemplate_error_validation', 'Sila semak semula maklumat template emel yang diisi.'),
                $fieldErrors
            );
        }

        $savePayload = $form;
        $savePayload['update_by'] = $updateBy;
        if ($templateId > 0) {
            $emailTemplateModel->update($templateId, $savePayload);
            emailTemplateActionAudit($emailTemplateModel, 'UPDATE', $templateId, ['action' => 'update']);
            $message = emailTemplateActionT('emailTemplate_save_success_update', 'Template emel berjaya dikemaskini.');
        } else {
            $newTemplateId = $emailTemplateModel->create($savePayload);
            emailTemplateActionAudit($emailTemplateModel, 'CREATE', $newTemplateId, ['action' => 'create']);
            $message = emailTemplateActionT('emailTemplate_save_success_create', 'Template emel berjaya dicipta.');
        }
    } elseif ($formAction === 'duplicate') {
        if ($templateId <= 0) {
            jsonErrorResponse(emailTemplateActionT('emailTemplate_error_template_not_found', 'Template emel tidak ditemui.'), 404);
        }
        $newTemplateId = $emailTemplateModel->duplicate($templateId, $updateBy);
        emailTemplateActionAudit($emailTemplateModel, 'CREATE', $newTemplateId, [
            'action' => 'duplicate',
            'source_template_id' => $templateId,
        ]);
        $message = emailTemplateActionT('emailTemplate_duplicate_success', 'Salinan template emel berjaya dicipta.');
    } elseif ($formAction === 'archive') {
        if ($templateId <= 0) {
            jsonErrorResponse(emailTemplateActionT('emailTemplate_error_template_not_found', 'Template emel tidak ditemui.'), 404);
        }
        $emailTemplateModel->archive($templateId, $updateBy);
        emailTemplateActionAudit($emailTemplateModel, 'ARCHIVE', $templateId, ['action' => 'archive']);
        $message = emailTemplateActionT('emailTemplate_archive_success', 'Template emel berjaya diarkibkan.');
    } elseif ($formAction === 'delete') {
        if ($templateId <= 0) {
            jsonErrorResponse(emailTemplateActionT('emailTemplate_error_template_not_found', 'Template emel tidak ditemui.'), 404);
        }
        $templateRecord = $emailTemplateModel->findById($templateId);
        if (!$templateRecord) {
            jsonErrorResponse(emailTemplateActionT('emailTemplate_error_template_not_found', 'Template emel tidak ditemui.'), 404);
        }
        $emailTemplateModel->delete($templateId);
        emailTemplateActionAudit($emailTemplateModel, 'DELETE', $templateId, ['action' => 'delete'], $templateRecord);
        $message = emailTemplateActionT('emailTemplate_delete_success', 'Template emel berjaya dipadam.');
    } else {
        $count = 0;
        foreach ($seedTemplates as $seedTemplate) {
            $templateCode = strtoupper(trim((string)($seedTemplate['template_code'] ?? '')));
            if ($templateCode === '' || $emailTemplateModel->existsByCode($templateCode)) {
                continue;
            }
            $newTemplateId = $emailTemplateModel->create(array_merge($seedTemplate, ['update_by' => $updateBy]));
            if ($newTemplateId > 0) {
                $count++;
                emailTemplateActionAudit($emailTemplateModel, 'CREATE', $newTemplateId, [
                    'action' => 'seed_import',
                    'seed_template' => true,
                ]);
            }
        }
        $message = emailTemplateActionT('emailTemplate_seed_success', 'Seed template berjaya diimport.') . ' (' . $count . ')';
    }

    $filters = emailTemplateActionFilters();
    $records = $emailTemplateModel->getAll($filters);
    $usageCounts = $emailTemplateModel->getUsageCounts(array_map(
        static fn (array $record): int => (int)($record['f_templateID'] ?? 0),
        $records
    ));
    $summary = emailTemplateActionBuildSummary($records);

    jsonSuccessResponse([
        'message' => $message,
        'table' => [
            'rows_html' => emailTemplateActionRenderRows($records, $usageCounts, $roleOptions, $categoryOptions, $statusOptions, (string)($_SESSION['csrf_token'] ?? '')),
            'empty_html' => $records === [] ? emailTemplateActionRenderEmptyState($seedTemplates, (string)($_SESSION['csrf_token'] ?? '')) : '',
            'summary' => $summary,
        ],
    ]);
} catch (RuntimeException $e) {
    $message = trim($e->getMessage());
    if ($message === 'Default email template cannot be archived until another template is set as default.') {
        $message = emailTemplateActionT('emailTemplate_error_archive_default_blocked', 'Template default tidak boleh diarkibkan selagi belum ada template lain dijadikan default bagi peranan dan kategori yang sama.');
    } elseif ($message === 'Unable to generate a unique duplicate template code.') {
        $message = emailTemplateActionT('emailTemplate_error_duplicate_failed', 'Salinan template tidak berjaya dijana. Sila cuba semula.');
    } elseif ($message === 'Default email template cannot be deleted until another template is set as default.') {
        $message = emailTemplateActionT('emailTemplate_error_delete_default_blocked', 'Template default tidak boleh dipadam selagi belum ada template lain dijadikan default bagi peranan dan kategori yang sama.');
    } elseif ($message === 'Email template cannot be deleted because it has been used.') {
        $message = emailTemplateActionT('emailTemplate_error_delete_used_blocked', 'Template emel yang pernah digunakan tidak boleh dipadam.');
    }
    jsonErrorResponse($message !== '' ? $message : emailTemplateActionT('emailTemplate_save_fail', 'Template emel tidak berjaya disimpan.'), 422);
} catch (Throwable $e) {
    error_log('[email-template-action] ' . $e->getMessage());
    jsonErrorResponse(emailTemplateActionT('emailTemplate_save_fail', 'Template emel tidak berjaya disimpan.'), 500);
}
