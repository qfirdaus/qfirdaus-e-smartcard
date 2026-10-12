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

try {
    require_once __DIR__ . '/../includes/init.php';
    require_login();
    require_once __DIR__ . '/_helpers.php';
    require_once __DIR__ . '/../classes/Database.php';
    require_once __DIR__ . '/../classes/NotificationAdminService.php';

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        jsonErrorResponse((string)(__('notification_invalid_method') ?: 'Invalid request method.'), 405);
    }

    if (!isValidCsrfToken()) {
        jsonErrorResponse((string)(__('userGroup_csrf_invalid') ?: 'Invalid CSRF token.'), 403);
    }

    $pdo = Database::getInstance('mysql')->getConnection();
    ensureAjaxGroupManagePermission($pdo, (string)(__('notification_admin_forbidden') ?: 'You do not have permission to publish notifications.'));
    if (!checkRateLimit('notification_admin_publish', 10, 60)) {
        jsonErrorResponse((string)(__('notification_rate_limited') ?: 'Too many requests. Please try again shortly.'), 429);
    }

    $raw = file_get_contents('php://input');
    $data = json_decode((string)$raw, true);
    if (!is_array($data)) {
        $data = $_POST;
    }

    $service = new NotificationAdminService($pdo);
    $result = $service->publishFromAdminInput($data, (string)($_SESSION['f_loginID'] ?? $_SESSION['f_stafID'] ?? 'admin'));

    jsonSuccessResponse([
        'message' => (string)(__('notification_admin_publish_success') ?: 'Notification published successfully.'),
        'notification_id' => $result['notification_id'],
        'summary' => $service->getSummary(),
    ]);
} catch (InvalidArgumentException $e) {
    jsonErrorResponse($e->getMessage(), 422);
} catch (Throwable $e) {
    error_log('[notification-admin-publish] ' . $e->getMessage());
    jsonErrorResponse((string)(__('notification_admin_publish_failed') ?: 'Unable to publish notification.'), 500);
}
