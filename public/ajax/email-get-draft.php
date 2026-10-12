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

$draftID = (int) ($_GET['id'] ?? 0);
$stafID = trim((string) ($_SESSION['f_stafID'] ?? ''));
if ($draftID <= 0 || $stafID === '') {
    jsonErrorResponse((string) __('email_error_invalid_draft'), 400);
}

try {
    $pdo = Database::getInstance('mysql')->getConnection();
    $stmt = $pdo->prepare(
        "
        SELECT *
        FROM tbl_permohonan_email
        WHERE f_permohonanID = :id
          AND f_stafID = :staf
        LIMIT 1
        "
    );
    $stmt->execute([
        ':id' => $draftID,
        ':staf' => $stafID,
    ]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$row) {
        jsonErrorResponse((string) __('email_error_draft_not_found'), 404);
    }

    jsonSuccessResponse(['data' => $row]);
} catch (Throwable $e) {
    error_log('[email-get-draft] ' . $e->getMessage());
    jsonErrorResponse((string) __('email_error_generic'), 500);
}