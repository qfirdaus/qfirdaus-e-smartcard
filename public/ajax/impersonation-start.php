<?php
/**
 * IQS FRAMEWORK CORE FILE
 *
 * READ ONLY for downstream project programmers.
 * Do not modify this file directly in template or cloned projects.
 * Custom changes must be implemented in project-specific files
 * or approved extension points.
 */declare(strict_types=1);

try {
    ob_start();
    require_once __DIR__ . '/../includes/init.php';
    $initOutput = ob_get_clean();
    require_once __DIR__ . '/_helpers.php';
    logAjaxUnexpectedOutput('impersonation-start:init.php', $initOutput);

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        jsonErrorResponse('Method not allowed.', 405);
    }
    if (!isValidCsrfToken()) {
        jsonErrorResponse((string)(__('userGroup_csrf_invalid') ?: 'CSRF token tidak sah.'), 403);
    }
    if (function_exists('check_rate_limit')) {
        $rateKey = 'impersonation_start_' . (string)($_SESSION['f_loginID'] ?? session_id());
        if (!check_rate_limit($rateKey, 5, 60)) {
            jsonErrorResponse((string)(__('impersonation_rate_limited') ?: 'Too many View As attempts. Please try again later.'), 429);
        }
    }

    $targetLoginId = trim((string)($_POST['target_login_id'] ?? ''));
    $reason = trim((string)($_POST['reason'] ?? ''));
    $mode = trim((string)($_POST['mode'] ?? 'view_only'));
    $pdo = Database::getInstance('mysql')->getConnection();

    $result = impersonation_start($pdo, $targetLoginId, $reason, $mode);

    jsonSuccessResponse([
        'message' => (string)(__('impersonation_start_success') ?: 'View As mode started.'),
        'target' => $result['target'] ?? [],
        'redirect' => base_url('pages/dashboard.php'),
    ]);
} catch (Throwable $e) {
    $map = [
        'IMPERSONATION_ALREADY_ACTIVE' => ['impersonation_already_active', 'View As mode is already active.', 409],
        'IMPERSONATION_REQUIRED' => ['impersonation_required', 'Target user and reason are required.', 422],
        'IMPERSONATION_FORBIDDEN' => ['impersonation_forbidden', 'You do not have permission to use View As.', 403],
        'IMPERSONATION_TARGET_NOT_FOUND' => ['impersonation_target_not_found', 'Target user was not found.', 404],
        'IMPERSONATION_TARGET_DISABLED' => ['impersonation_target_disabled', 'Target user account is disabled.', 422],
        'IMPERSONATION_SELF_DENIED' => ['impersonation_self_denied', 'You cannot View As your own account.', 422],
        'IMPERSONATION_SUPER_ADMIN_DENIED' => ['impersonation_super_admin_denied', 'View As is not allowed for Super Admin accounts.', 403],
    ];
    $code = $e->getMessage();
    [$langKey, $fallback, $status] = $map[$code] ?? ['impersonation_start_failed', 'Unable to start View As mode.', 500];
    error_log('[impersonation-start] ' . $e->getMessage());
    jsonErrorResponse((string)(__($langKey) ?: $fallback), (int)$status);
}
