<?php
/**
 * IQS FRAMEWORK CORE FILE
 *
 * READ ONLY for downstream project programmers.
 * Do not modify this file directly in template or cloned projects.
 * Custom changes must be implemented in project-specific files
 * or approved extension points.
 */declare(strict_types=1);

ob_start();
header('Content-Type: application/json; charset=utf-8');

function notificationTemplateActionAudit(string $action, int $templateId, array $data, ?array $oldRecord = null, ?array $newRecord = null): void
{
    try {
        if (!function_exists('audit_event')) {
            return;
        }

        $eventMap = [
            'save' => 'UPDATE',
            'duplicate' => 'CREATE',
            'archive' => 'UPDATE',
            'restore' => 'UPDATE',
            'delete' => 'DELETE',
        ];
        $eventType = $eventMap[$action] ?? 'UPDATE';
        $actorLabel = function_exists('audit_format_actor_label')
            ? audit_format_actor_label()
            : ($_SESSION['user']['f_nama'] ?? $_SESSION['f_nama'] ?? null);
        $targetLabel = trim((string)($newRecord['f_templateCode'] ?? $oldRecord['f_templateCode'] ?? $data['template_code'] ?? $data['name'] ?? $data['template_name'] ?? 'Notification Template'));

        $eventId = audit_event([
            'event_type' => $eventType,
            'severity' => $action === 'delete' ? 'WARN' : 'INFO',
            'outcome' => 'SUCCESS',
            'target_type' => 'notification_template',
            'target_id' => (string)$templateId,
            'target_label' => $targetLabel,
            'message' => function_exists('audit_format_message')
                ? audit_format_message('Notification template ' . $action, $actorLabel)
                : 'Notification template ' . $action,
            'actor_label' => $actorLabel,
            'meta' => [
                'action' => $action,
                'template_id' => $templateId,
                'source_template_id' => isset($data['template_id']) ? (int)$data['template_id'] : null,
                'event_code' => $data['event_code'] ?? null,
                'status' => $data['status'] ?? null,
            ],
        ]);

        if (!$eventId || !function_exists('audit_begin_change') || !function_exists('audit_change')) {
            return;
        }

        $changeSetId = audit_begin_change($eventId, 'notification_template', (string)$templateId, 'Notification template ' . $action, [
            'source' => 'notification-template-action',
            'action' => $action,
        ]);
        if (!$changeSetId) {
            return;
        }

        $fields = [
            'f_templateCode' => 'string',
            'f_eventCode' => 'string',
            'f_moduleCode' => 'string',
            'f_type' => 'string',
            'f_category' => 'string',
            'f_severity' => 'string',
            'f_priority' => 'string',
            'f_title_ms' => 'string',
            'f_title_en' => 'string',
            'f_body_ms' => 'string',
            'f_body_en' => 'string',
            'f_actionLabel_ms' => 'string',
            'f_actionLabel_en' => 'string',
            'f_icon' => 'string',
            'f_requiresAction' => 'integer',
            'f_placeholders' => 'json',
            'f_status' => 'integer',
        ];
        foreach ($fields as $field => $type) {
            $oldValue = $oldRecord[$field] ?? null;
            $newValue = $newRecord[$field] ?? null;
            if ((string)$oldValue === (string)$newValue) {
                continue;
            }
            audit_change($changeSetId, $field, $oldValue, $newValue, $type, false);
        }
    } catch (Throwable $auditError) {
        error_log('[notification-template-action] Audit logging failed: ' . $auditError->getMessage());
    }
}

try {
    require_once __DIR__ . '/../includes/init.php';
    require_login();
    require_once __DIR__ . '/_helpers.php';
    require_once __DIR__ . '/../classes/Database.php';
    require_once __DIR__ . '/../classes/NotificationTemplateService.php';

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        jsonErrorResponse((string)(__('notification_invalid_method') ?: 'Invalid request method.'), 405);
    }

    if (!isValidCsrfToken()) {
        jsonErrorResponse((string)(__('userGroup_csrf_invalid') ?: 'Invalid CSRF token.'), 403);
    }

    $pdo = Database::getInstance('mysql')->getConnection();
    ensureAjaxGroupManagePermission($pdo, (string)(__('notification_template_forbidden') ?: 'You do not have permission to manage notification templates.'));
    if (!checkRateLimit('notification_template_action', 30, 60)) {
        jsonErrorResponse((string)(__('notification_rate_limited') ?: 'Too many requests. Please try again shortly.'), 429);
    }

    $raw = file_get_contents('php://input');
    $data = json_decode((string)$raw, true);
    if (!is_array($data)) {
        $data = $_POST;
    }

    $action = strtolower(trim((string)($data['action'] ?? 'save')));
    $service = new NotificationTemplateService($pdo);
    $updateBy = (string)($_SESSION['f_loginID'] ?? $_SESSION['f_stafID'] ?? 'admin');
    $message = '';
    $sourceTemplateId = (int)($data['template_id'] ?? $data['f_templateID'] ?? 0);
    $beforeTemplate = $sourceTemplateId > 0 ? $service->findById($sourceTemplateId) : null;

    if ($action === 'save') {
        $templateId = $service->save($data, $updateBy);
        $message = (string)(__('notification_template_save_success') ?: 'Notification template saved.');
    } elseif ($action === 'duplicate') {
        $templateId = $service->duplicate((int)($data['template_id'] ?? 0), $updateBy);
        $message = (string)(__('notification_template_duplicate_success') ?: 'Notification template duplicated.');
    } elseif ($action === 'archive') {
        $templateId = (int)($data['template_id'] ?? 0);
        $service->archive($templateId, $updateBy);
        $message = (string)(__('notification_template_archive_success') ?: 'Notification template archived.');
    } elseif ($action === 'restore') {
        $templateId = (int)($data['template_id'] ?? 0);
        $service->restore($templateId, $updateBy);
        $message = (string)(__('notification_template_restore_success') ?: 'Notification template restored.');
    } elseif ($action === 'delete') {
        $templateId = (int)($data['template_id'] ?? 0);
        $service->delete($templateId);
        $message = (string)(__('notification_template_delete_success') ?: 'Notification template deleted.');
    } else {
        jsonErrorResponse((string)(__('notification_template_invalid_action') ?: 'Invalid template action.'), 422);
    }

    $afterTemplate = $action === 'delete' ? null : $service->findById((int)($templateId ?? 0));
    notificationTemplateActionAudit($action, (int)($templateId ?? 0), $data, $beforeTemplate, $afterTemplate);

    jsonSuccessResponse([
        'message' => $message,
        'template_id' => $templateId ?? 0,
        'summary' => $service->summary(),
    ]);
} catch (InvalidArgumentException|RuntimeException $e) {
    jsonErrorResponse($e->getMessage(), 422);
} catch (Throwable $e) {
    error_log('[notification-template-action] ' . $e->getMessage());
    jsonErrorResponse((string)(__('notification_template_save_failed') ?: 'Unable to save notification template.'), 500);
}
