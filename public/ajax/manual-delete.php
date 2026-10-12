<?php
/** Delete a user manual through the guarded management workflow. */
declare(strict_types=1);

require_once __DIR__ . '/../includes/init.php';
require_login();
require_once __DIR__ . '/../setting/constants/manual_constants.php';
require_once __DIR__ . '/../controllers/ManualController.php';
require_once __DIR__ . '/_helpers.php';

header('Content-Type: application/json; charset=utf-8');

try {
    checkRateLimit('manual_delete', 20, 60);
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        jsonErrorResponse((string)__('manual_method_not_allowed'), 405);
    }
    $headers = function_exists('getallheaders') ? (getallheaders() ?: []) : [];
    $csrf = (string)($headers['X-CSRF-Token'] ?? $headers['x-csrf-token'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? ''));
    $sessionCsrf = (string)($_SESSION['csrf_token'] ?? '');
    if ($sessionCsrf === '' || $csrf === '' || !hash_equals($sessionCsrf, $csrf)) {
        jsonErrorResponse((string)__('manual_csrf_invalid'), 400);
    }

    $pdo = Database::getInstance()->getConnection();
    if (!manual_can_manage($pdo)) {
        jsonErrorResponse((string)__('manual_action_forbidden'), 403);
    }

    $payload = json_decode((string)file_get_contents('php://input'), true);
    $groupId = (int)(is_array($payload) ? ($payload['group_id'] ?? 0) : 0);
    $controller = new ManualController();
    $result = $controller->deleteManual($groupId);
    if (!($result['success'] ?? false)) {
        jsonErrorResponse((string)($result['message'] ?? __('manual_delete_record_failed')), 422);
    }
    jsonSuccessResponse([
        'groupId' => $groupId,
        'message' => (string)($result['message'] ?? __('manual_delete_success')),
    ]);
} catch (Throwable $exception) {
    error_log('[manual-delete] ' . $exception->getMessage());
    jsonErrorResponse((string)__('manual_delete_record_failed'), 500);
}
