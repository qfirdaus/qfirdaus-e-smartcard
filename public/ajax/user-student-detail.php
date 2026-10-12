<?php
/**
 * IQS FRAMEWORK CORE FILE
 *
 * READ ONLY for downstream project programmers.
 * Do not modify this file directly in template or cloned projects.
 * Custom changes must be implemented in project-specific files
 * or approved extension points.
 */declare(strict_types=1);

error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');

while (ob_get_level() > 0) {
    @ob_end_clean();
}

header('Content-Type: application/json; charset=utf-8');

try {
    require_once __DIR__ . '/../includes/init.php';
    require_login();
    require_once __DIR__ . '/_helpers.php';
    require_once __DIR__ . '/../includes/functions-db.php';
    require_once __DIR__ . '/../classes/Database.php';

    $pdo = Database::getInstance('mysql')->getConnection();
    ensureAjaxGroupManagePermission($pdo);

    if (!isValidCsrfToken()) {
        http_response_code(400);
        echo json_encode([
            'error' => true,
            'message' => (string)__('userGroup_csrf_invalid'),
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    if (!is_student_mode_enabled()) {
        http_response_code(403);
        echo json_encode([
            'error' => true,
            'message' => (string)__('studentSearch_mode_disabled'),
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $matrik = trim((string)($_POST['matrik'] ?? $_GET['matrik'] ?? ''));
    studentManagementDiagnosticLog('student_detail', 'request_received', [
        'matrik' => $matrik,
    ]);

    if ($matrik === '') {
        http_response_code(400);
        echo json_encode([
            'error' => true,
            'message' => (string)__('userList_ajax_invalid_matric'),
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $fetchDetail = static function (PDO $pdoSybase, string $matrikValue): array {
        $sql = "
            SELECT TOP 1
                matrik,
                nama,
                kdprogram,
                program,
                kdfakulti,
                fakulti,
                kdtahap,
                tahap_pengajian,
                statuskategori
            FROM v210
            WHERE convert(varchar(50), matrik) = :matrik
              AND upper(convert(varchar(20), statuskategori)) = 'AKTIF'
        ";

        $stmt = $pdoSybase->prepare($sql);
        $stmt->bindValue(':matrik', $matrikValue);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
    };

    $pdoSybase = Database::pdoSybaseStudent();
    if (!$pdoSybase) {
        throw new RuntimeException('Student database connection is not available.');
    }

    try {
        $row = $fetchDetail($pdoSybase, $matrik);
    } catch (Throwable $primaryError) {
        studentManagementDiagnosticLog('student_detail', 'primary_query_failed', [
            'matrik' => $matrik,
            'error' => $primaryError->getMessage(),
        ]);

        if (function_exists('get_sybase_student_key')) {
            Database::clearInstance(get_sybase_student_key());
        }
        $pdoSybase = Database::pdoSybaseStudent();
        $row = $fetchDetail($pdoSybase, $matrik);
    }

    if ($row === []) {
        http_response_code(404);
        echo json_encode([
            'error' => true,
            'message' => (string)__('userList_ajax_student_not_found'),
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $payload = [
        'matrik' => trim((string)($row['matrik'] ?? '')),
        'nama' => trim((string)($row['nama'] ?? '')),
        'kdprogram' => trim((string)($row['kdprogram'] ?? '')),
        'program' => trim((string)($row['program'] ?? '')),
        'kdfakulti' => trim((string)($row['kdfakulti'] ?? '')),
        'fakulti' => trim((string)($row['fakulti'] ?? '')),
        'kdtahap' => trim((string)($row['kdtahap'] ?? '')),
        'tahap_pengajian' => trim((string)($row['tahap_pengajian'] ?? '')),
        'statuskategori' => trim((string)($row['statuskategori'] ?? '')),
    ];

    studentManagementDiagnosticLog('student_detail', 'request_success', [
        'matrik' => $matrik,
        'has_program' => $payload['program'] !== '',
        'has_fakulti' => $payload['fakulti'] !== '',
        'has_tahap' => $payload['tahap_pengajian'] !== '',
        'has_status' => $payload['statuskategori'] !== '',
    ]);

    echo json_encode([
        'error' => false,
        'student' => $payload,
    ], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    studentManagementDiagnosticLog('student_detail', 'request_error', [
        'matrik' => $matrik ?? '',
        'error' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine(),
    ]);
    http_response_code(500);
    echo json_encode([
        'error' => true,
        'message' => (string)__('studentSearch_system_error'),
    ], JSON_UNESCAPED_UNICODE);
}
