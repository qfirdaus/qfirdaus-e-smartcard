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
require_once __DIR__ . '/../controllers/ManualController.php';
require_once __DIR__ . '/_helpers.php';

header('Content-Type: application/json; charset=utf-8');

try {
    checkRateLimit('manual_upload', 15, 60);
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode(['error' => true, 'message' => __('manual_method_not_allowed')], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $postedCsrf = (string)($_POST['csrf_token'] ?? '');
    $csrfSession = (string)($_SESSION['csrf_token'] ?? '');
    if ($csrfSession === '' || $postedCsrf === '' || !hash_equals($csrfSession, $postedCsrf)) {
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

    $groupId = (int)($_POST['group_id'] ?? 0);
    $controller = new ManualController();
    $result = $controller->uploadManual($groupId, $_FILES['manual_file'] ?? []);

    if (!($result['success'] ?? false)) {
        http_response_code(422);
        echo json_encode([
            'error' => true,
            'message' => (string)($result['message'] ?? __('manual_upload_failed_generic'))
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $item = $controller->getManualListItem($groupId);
    $filePath = (string)($item['f_file_path'] ?? '');
    $updatedAt = !empty($item['f_updated_at'])
        ? date('d/m/Y h:i A', strtotime((string)$item['f_updated_at']))
        : '-';

    echo json_encode([
        'error' => false,
        'message' => (string)($result['message'] ?? ''),
        'data' => [
            'groupId' => (int)($item['f_groupID'] ?? $groupId),
            'groupName' => (string)($item['f_groupName'] ?? ''),
            'filePath' => $filePath,
            'fileUrl' => $filePath !== '' ? base_url('ajax/manual-view.php?group_id=' . (int)($item['f_groupID'] ?? $groupId)) : '',
            'hasManual' => ($filePath !== ''),
            'updatedAt' => $updatedAt,
            'statusLabel' => (string)__('manual_status_saved'),
        ],
    ], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    error_log('[manual-upload] ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => true, 'message' => __('manual_server_upload_error')], JSON_UNESCAPED_UNICODE);
}
