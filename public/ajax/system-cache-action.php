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
require_once __DIR__ . '/../classes/Database.php';
require_once __DIR__ . '/../classes/SystemCacheMaintenanceService.php';

header('Content-Type: application/json; charset=utf-8');

$auditCacheFailure = static function (string $outcome, string $reason, array $meta = []): void {
    if (!function_exists('audit_event')) {
        return;
    }
    try {
        audit_event([
            'event_type' => 'DELETE', 'severity' => 'WARNING', 'outcome' => $outcome,
            'target_type' => 'system_cache', 'target_id' => 'system-cache',
            'target_label' => 'System Cache Maintenance', 'message' => 'System cache maintenance failed',
            'meta' => array_merge(['reason' => $reason], $meta),
        ]);
    } catch (Throwable $auditError) {
        error_log('[system-cache-action] Failure audit error: ' . $auditError->getMessage());
    }
};

try {
    $pdo = Database::getInstance('mysql')->getConnection();
    $profile = function_exists('userListResolveCurrentProfile') ? userListResolveCurrentProfile($pdo) : [];
    $isSuperAdmin = $profile && function_exists('is_user_super_admin') && is_user_super_admin($profile, $pdo);
    if (!$isSuperAdmin) {
        $auditCacheFailure('DENIED', 'permission_denied');
        jsonErrorResponse((string)(__('systemCache_forbidden') ?: 'You do not have permission to clear system cache.'), 403);
    }

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        $auditCacheFailure('FAILURE', 'invalid_method');
        jsonErrorResponse((string)(__('systemCache_error_invalid_method') ?: 'Invalid request method.'), 405);
    }

    if (!isValidCsrfToken()) {
        $auditCacheFailure('DENIED', 'invalid_csrf');
        jsonErrorResponse((string)(__('userGroup_csrf_invalid') ?: 'Invalid CSRF token.'), 403);
    }

    $action = trim((string)($_POST['action'] ?? ''));
    if ($action !== 'clear') {
        $auditCacheFailure('FAILURE', 'invalid_action');
        jsonErrorResponse((string)(__('systemCache_error_invalid_action') ?: 'Invalid cache action.'), 422);
    }

    $scope = trim((string)($_POST['scope'] ?? 'selected'));
    if (!in_array($scope, ['selected', 'all'], true)) {
        $auditCacheFailure('FAILURE', 'invalid_scope');
        jsonErrorResponse((string)(__('systemCache_error_invalid_scope') ?: 'Invalid cache scope.'), 422);
    }
    $clearAll = $scope === 'all';
    $selectedIds = $_POST['locations'] ?? [];
    if (!is_array($selectedIds)) {
        $selectedIds = [];
    }
    $selectedIds = array_values(array_unique(array_filter(array_map(
        static fn($value): string => preg_match('/^[a-f0-9]{64}$/', trim((string)$value)) ? trim((string)$value) : '',
        $selectedIds
    ))));

    if (!$clearAll && $selectedIds === []) {
        $auditCacheFailure('FAILURE', 'empty_selection');
        jsonErrorResponse((string)(__('systemCache_error_no_selection') ?: 'Select at least one cache location.'), 422);
    }

    $service = new SystemCacheMaintenanceService();
    $result = $service->clear($selectedIds, $clearAll);
    $hasRuntimeFailure = (!empty($result['opcache']['available']) && empty($result['opcache']['success']))
        || (!empty($result['apcu']['available']) && empty($result['apcu']['success']));

    if (function_exists('audit_event')) {
        audit_event([
            'event_type' => 'DELETE',
            'severity' => 'WARN',
            'outcome' => empty($result['errors']) && !$hasRuntimeFailure ? 'SUCCESS' : 'PARTIAL',
            'target_type' => 'system_cache',
            'target_id' => 'system-cache',
            'target_label' => 'System Cache Maintenance',
            'message' => 'System cache cleared',
            'user_id' => $_SESSION['user']['f_userID'] ?? $_SESSION['f_userID'] ?? $_SESSION['f_stafID'] ?? null,
            'actor_label' => $_SESSION['user']['f_nama'] ?? $_SESSION['f_nama'] ?? null,
            'meta' => [
                'scope' => $clearAll ? 'all' : 'selected',
                'locations_cleared' => $result['locations_cleared'],
                'files_removed' => $result['files_removed'],
                'freed_bytes' => $result['freed_bytes'],
                'freed_size' => $result['freed_size'],
                'opcache' => $result['opcache'],
                'apcu' => $result['apcu'],
                'errors' => $result['errors'],
            ],
        ]);
    }

    jsonSuccessResponse([
        'message' => (string)(__('systemCache_success_message') ?: 'System cache cleared successfully.'),
        'result' => $result,
        'note' => (string)(__('systemCache_success_note') ?: 'Users do not need to logout/login after clearing cache. A page refresh is normally sufficient.'),
    ]);
} catch (InvalidArgumentException $e) {
    $auditCacheFailure('FAILURE', 'invalid_location');
    error_log('[system-cache-action] Invalid request: ' . $e->getMessage());
    jsonErrorResponse((string)(__('systemCache_error_invalid_location') ?: 'No valid cache locations were selected.'), 422);
} catch (RuntimeException $e) {
    error_log('[system-cache-action] Runtime failure: ' . $e->getMessage());
    $isLocked = $e->getMessage() === 'Cache maintenance is already running.';
    $auditCacheFailure('FAILURE', $isLocked ? 'operation_busy' : 'runtime_error');
    jsonErrorResponse((string)__($isLocked ? 'systemCache_error_busy' : 'systemCache_error_generic'), $isLocked ? 409 : 500);
} catch (Throwable $e) {
    $auditCacheFailure('FAILURE', 'unexpected_error');
    error_log('[system-cache-action] ' . $e->getMessage());
    jsonErrorResponse((string)(__('systemCache_error_generic') ?: 'Unable to clear system cache.'), 500);
}
