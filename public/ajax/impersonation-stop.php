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
    logAjaxUnexpectedOutput('impersonation-stop:init.php', $initOutput);

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        jsonErrorResponse('Method not allowed.', 405);
    }
    if (!isValidCsrfToken()) {
        jsonErrorResponse((string)(__('userGroup_csrf_invalid') ?: 'CSRF token tidak sah.'), 403);
    }
    if (function_exists('check_rate_limit')) {
        $rateKey = 'impersonation_stop_' . (string)($_SESSION['f_loginID'] ?? session_id());
        if (!check_rate_limit($rateKey, 10, 60)) {
            jsonErrorResponse((string)(__('impersonation_rate_limited') ?: 'Too many View As attempts. Please try again later.'), 429);
        }
    }

    $pdo = Database::getInstance('mysql')->getConnection();
    impersonation_stop($pdo, 'manual_stop');

    jsonSuccessResponse([
        'message' => (string)(__('impersonation_stop_success') ?: 'View As mode stopped.'),
        'redirect' => base_url('pages/senarai-pengguna.php'),
    ]);
} catch (Throwable $e) {
    $status = $e->getMessage() === 'IMPERSONATION_NOT_ACTIVE' ? 409 : 500;
    $fallback = $status === 409 ? 'View As mode is not active.' : 'Unable to stop View As mode.';
    $key = $status === 409 ? 'impersonation_not_active' : 'impersonation_stop_failed';
    error_log('[impersonation-stop] ' . $e->getMessage());
    jsonErrorResponse((string)(__($key) ?: $fallback), $status);
}
