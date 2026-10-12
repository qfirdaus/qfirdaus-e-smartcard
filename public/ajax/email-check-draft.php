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
require_once __DIR__ . '/_helpers.php';
require_login();

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    jsonErrorResponse((string) __('email_error_invalid_method'), 405);
}

$stafID = trim((string) ($_SESSION['f_stafID'] ?? ''));
if ($stafID === '') {
    jsonErrorResponse((string) __('email_error_invalid_staff'), 401);
}

try {
    $pdo = Database::getInstance('mysql')->getConnection();
    $stmt = $pdo->prepare(
        "
        SELECT f_permohonanID
        FROM tbl_permohonan_email
        WHERE f_stafID = :staf
          AND f_status = 'DRAFT'
        ORDER BY f_permohonanID DESC
        LIMIT 1
        "
    );
    $stmt->execute([':staf' => $stafID]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

    jsonSuccessResponse(['draft_id' => $row['f_permohonanID'] ?? null]);
} catch (Throwable $e) {
    error_log('[email-check-draft] ' . $e->getMessage());
    jsonErrorResponse((string) __('email_error_generic'), 500);
}