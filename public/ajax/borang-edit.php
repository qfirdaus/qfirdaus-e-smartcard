<?php
/**
 * IQS FRAMEWORK CORE FILE
 *
 * READ ONLY for downstream project programmers.
 * Do not modify this file directly in template or cloned projects.
 * Custom changes must be implemented in project-specific files
 * or approved extension points.
 */declare(strict_types=1);

ob_start();

require_once __DIR__ . '/../includes/init.php';
require_once __DIR__ . '/_helpers.php';
require_login();

$pdo = Database::getInstance('mysql')->getConnection();
ensureAjaxGroupManagePermission($pdo, (string) __('formList_error_no_permission'));

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    jsonErrorResponse((string) __('formList_error_invalid_method'), 405);
}

$id = (int) ($_GET['id'] ?? 0);
if ($id <= 0) {
    jsonErrorResponse((string) __('formList_error_invalid_id'), 400);
}

try {
    $stmt = $pdo->prepare(
        "
        SELECT
            f_borangID,
            f_nama_ms,
            f_nama_en,
            f_kategoriID,
            f_path,
            f_icon,
            f_flag
        FROM tbl_m_borang
        WHERE f_borangID = :id
        LIMIT 1
        "
    );
    $stmt->execute([':id' => $id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$row) {
        jsonErrorResponse((string) __('formList_error_not_found'), 404);
    }

    jsonSuccessResponse([
        'data' => [
            'id' => (int) $row['f_borangID'],
            'nama_ms' => (string) ($row['f_nama_ms'] ?? ''),
            'nama_en' => (string) ($row['f_nama_en'] ?? ''),
            'kategoriID' => (int) ($row['f_kategoriID'] ?? 0),
            'path' => (string) ($row['f_path'] ?? ''),
            'icon' => (string) ($row['f_icon'] ?? 'ri-file-line'),
            'flag' => (int) ($row['f_flag'] ?? 0),
        ],
    ]);
} catch (Throwable $e) {
    error_log('[borang-edit] ' . $e->getMessage());
    jsonErrorResponse((string) __('formList_error_generic'), 500);
}