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
require_once __DIR__ . '/../controllers/SidebarController.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, max-age=0');

$query = trim((string)($_GET['q'] ?? ''));
if ($query === '' || strlen($query) < 2) {
    echo json_encode(['success' => true, 'data' => []], JSON_UNESCAPED_UNICODE);
    exit;
}
if (strlen($query) > 80) {
    http_response_code(422);
    echo json_encode([
        'success' => false,
        'message' => __('sidebar_search_query_too_long') ?: 'Search text is too long.',
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    $controller = new SidebarController();
    $controller->loadSidebarData('');
    $results = $controller->searchAccessibleMenus($query, 12);

    $dashboardLabel = (string)(__('sidebar_dashboard') ?: 'Dashboard');
    $queryLower = function_exists('mb_strtolower')
        ? mb_strtolower($query, 'UTF-8')
        : strtolower($query);
    $dashboardLower = function_exists('mb_strtolower')
        ? mb_strtolower($dashboardLabel, 'UTF-8')
        : strtolower($dashboardLabel);
    if (str_contains($dashboardLower, $queryLower)) {
        array_unshift($results, [
            'name' => $dashboardLabel,
            'module' => (string)(__('sidebar_main') ?: 'Main'),
            'subgroup' => '',
            'icon' => 'ri-dashboard-fill',
            'url' => base_path((string)app_config('site.default_home', 'pages/dashboard.php')),
        ]);
    }

    foreach ($results as &$result) {
        if (isset($result['url'])) {
            continue;
        }
        $menuPath = ltrim((string)($result['path'] ?? ''), '/');
        $result['url'] = base_path(
            str_starts_with($menuPath, 'pages/') ? $menuPath : 'pages/' . $menuPath
        );
        unset($result['path']);
    }
    unset($result);
    $results = array_slice($results, 0, 12);

    echo json_encode(['success' => true, 'data' => $results], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    error_log('[sidebar-menu-search] Search failed: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => __('sidebar_search_error') ?: 'Unable to search menus.',
    ], JSON_UNESCAPED_UNICODE);
}
exit;
