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

try {
    checkRateLimit('manual_view', 60, 60);
    $groupId = (int)($_GET['group_id'] ?? 0);
    if ($groupId <= 0) {
        http_response_code(400);
        exit((string)__('manual_view_invalid_request'));
    }

    $activeGroupId = (int)($_SESSION['group_active_id'] ?? 0);
    $db = Database::getInstance()->getConnection();
    $isAdmin = manual_can_manage($db);

    if (!$isAdmin && $groupId !== $activeGroupId) {
        http_response_code(403);
        exit((string)__('manual_action_forbidden'));
    }

    $controller = new ManualController();
    $manual = $controller->getManualByGroupId($groupId);
    $relativePath = (string)($manual['f_file_path'] ?? '');
    if ($relativePath === '') {
        http_response_code(404);
        exit((string)__('manual_not_found'));
    }

    $fullPath = $controller->resolveManualFilePath($relativePath);
    if ($fullPath === null) {
        http_response_code(404);
        exit((string)__('manual_not_found'));
    }

    header('Content-Type: application/pdf');
    header('Content-Length: ' . (string)filesize($fullPath));
    header('Content-Disposition: inline; filename="user-manual-group-' . $groupId . '.pdf"');
    header('X-Content-Type-Options: nosniff');
    header('Content-Security-Policy: sandbox; default-src \'none\'; object-src \'self\'');
    header('X-Frame-Options: SAMEORIGIN');
    header('Referrer-Policy: no-referrer');
    header('Cache-Control: private, no-store, no-cache, must-revalidate');
    header('Pragma: no-cache');

    readfile($fullPath);
    exit;
} catch (Throwable $e) {
    error_log('[manual-view] ' . $e->getMessage());
    http_response_code(500);
    exit((string)__('manual_view_load_failed'));
}
