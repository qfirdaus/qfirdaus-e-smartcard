<?php
/**
 * IQS FRAMEWORK CORE FILE
 *
 * READ ONLY for downstream project programmers.
 * Do not modify this file directly in template or cloned projects.
 * Custom changes must be implemented in project-specific files
 * or approved extension points.
 */// ajax/clear-dashcache.php
declare(strict_types=1);
require_once __DIR__ . '/../includes/init.php';
require_login();
require_once __DIR__ . '/_helpers.php';
require_once __DIR__ . '/../setting/constants/prestasi_constants.php';

header('Content-Type: application/json; charset=utf-8');

try {
    require_once __DIR__ . '/../controllers/DashboardController.php';
    require_once __DIR__ . '/../classes/Database.php';

    $pdo = Database::getInstance('mysql')->getConnection();
    ensureAjaxGroupManagePermission($pdo);

    // Clear specific keys in current session
    \DashCache::clear('staf_jppsm_all');
    \DashCache::clear('jppsm_by_'); // clears all jppsm_by_* keys

    // Also optionally clear any user-specific keys that might affect view
    \DashCache::clear('user_jab:');

    echo json_encode(['ok' => true, 'msg' => 'Dash cache cleared (current session)']);
    exit;
} catch (\Throwable $e) {
    http_response_code(500);
    error_log('[ajax/clear-dashcache.php] ' . $e->getMessage());
    echo json_encode(['ok' => false, 'msg' => 'Ralat semasa membersihkan cache']);
    exit;
}
