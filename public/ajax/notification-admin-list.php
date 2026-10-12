<?php
/** IQS FRAMEWORK CORE FILE */
declare(strict_types=1);
ob_start();
header('Content-Type: application/json; charset=utf-8');
try {
    require_once __DIR__ . '/../includes/init.php';
    require_login();
    require_once __DIR__ . '/_helpers.php';
    require_once __DIR__ . '/../classes/Database.php';
    require_once __DIR__ . '/../classes/NotificationAdminService.php';
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') jsonErrorResponse((string)(__('notification_invalid_method') ?: 'Invalid request method.'), 405);
    if (!isValidCsrfToken()) jsonErrorResponse((string)(__('userGroup_csrf_invalid') ?: 'Invalid CSRF token.'), 403);
    if (!checkRateLimit('notification_admin_list', 120, 60)) jsonErrorResponse((string)(__('notification_rate_limited') ?: 'Too many requests.'), 429);
    $pdo = Database::getInstance('mysql')->getConnection();
    ensureAjaxGroupManagePermission($pdo, (string)(__('notification_admin_forbidden') ?: 'Permission denied.'));
    $data = json_decode((string)file_get_contents('php://input'), true);
    if (!is_array($data)) $data = [];
    $service = new NotificationAdminService($pdo);
    $result = $service->getAdminList([
        'limit' => $data['length'] ?? 10,
        'offset' => $data['start'] ?? 0,
        'search' => $data['search'] ?? '',
        'type' => $data['type'] ?? '',
        'priority' => $data['priority'] ?? '',
        'status' => $data['status'] ?? 'all',
    ]);
    jsonSuccessResponse(['draw'=>(int)($data['draw'] ?? 0),'recordsTotal'=>$result['total'],'recordsFiltered'=>$result['filtered'],'items'=>$result['items']]);
} catch (Throwable $e) {
    error_log('[notification-admin-list] ' . $e->getMessage());
    jsonErrorResponse((string)(__('notification_admin_list_failed') ?: 'Unable to load notifications.'), 500);
}
