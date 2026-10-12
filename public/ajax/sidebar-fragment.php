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

header('Content-Type: application/json; charset=utf-8');

try {
    $requestedPath = '';
    if (isset($_GET['currentPath'])) {
        $requestedPath = prestasi_normalize_menu_path((string)$_GET['currentPath']);
    }
    if ($requestedPath === '' && isset($_GET['currentFile'])) {
        $requestedPath = prestasi_normalize_menu_path((string)$_GET['currentFile']);
    }
    if ($requestedPath === '') {
        $requestedPath = prestasi_normalize_menu_path((string)parse_url($_SERVER['HTTP_REFERER'] ?? '', PHP_URL_PATH));
    }

    $currentPagePath = $requestedPath !== '' ? $requestedPath : prestasi_current_page_relative_path();
    $currentFile = basename($currentPagePath !== '' ? $currentPagePath : ($_SERVER['PHP_SELF'] ?? ''));
    $pdo = Database::getInstance('mysql')->getConnection();
    $ui = buildAccessUiPayload($pdo, [
        'activeGroupId' => (int)($_SESSION['group_active_id'] ?? 0),
        'currentFile' => $currentFile,
        'currentPagePath' => $currentPagePath,
        'currentPageAllowed' => true,
        'includeSidebar' => true,
    ]);

    echo json_encode([
        'error' => false,
        'ui' => $ui,
        'html' => $ui['sidebar']['html'] ?? null,
        'activeGroupId' => $ui['activeGroupId'] ?? 0,
        'group_name' => $ui['role']['name'] ?? '',
    ], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'error' => true,
        'message' => $e->getMessage(),
    ], JSON_UNESCAPED_UNICODE);
}
