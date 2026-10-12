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
require_once __DIR__ . '/../setting/constants/manual_constants.php';
require_once __DIR__ . '/_helpers.php';

header('Content-Type: application/json; charset=utf-8');

try {
    checkRateLimit('manual_sync_groups', 5, 60);
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode(['error' => true, 'message' => __('manual_method_not_allowed')], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $headers = function_exists('getallheaders') ? (getallheaders() ?: []) : [];
    $csrfHdr = $headers['X-CSRF-Token'] ?? $headers['x-csrf-token'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
    $csrfSession = (string)($_SESSION['csrf_token'] ?? '');
    if ($csrfSession === '' || !hash_equals($csrfSession, (string)$csrfHdr)) {
        http_response_code(400);
        echo json_encode(['error' => true, 'message' => __('manual_csrf_invalid')], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $db = Database::getInstance()->getConnection();
    if (!manual_can_manage($db)) {
        http_response_code(403);
        echo json_encode(['error' => true, 'message' => __('manual_action_forbidden')], JSON_UNESCAPED_UNICODE);
        exit;
    }

    http_response_code(410);
    echo json_encode(['error' => true, 'message' => __('manual_sync_retired')], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    error_log('[manual-sync-groups] ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => true, 'message' => __('manual_server_sync_error')], JSON_UNESCAPED_UNICODE);
}
