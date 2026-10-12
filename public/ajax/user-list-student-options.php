<?php
/**
 * IQS FRAMEWORK CORE FILE
 *
 * READ ONLY for downstream project programmers.
 * Do not modify this file directly in template or cloned projects.
 * Custom changes must be implemented in project-specific files
 * or approved extension points.
 */// ajax/user-list-student-options.php
// Search active students from v210 for Add Pelajar modal (Select2 remote source)
declare(strict_types=1);

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
        studentManagementDiagnosticLog('student_list', 'csrf_invalid', [
            'post_has_csrf' => isset($_POST['csrf_token']),
            'post_csrf_length' => strlen((string)($_POST['csrf_token'] ?? '')),
            'header_csrf_length' => strlen((string)($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '')),
            'session_csrf_length' => strlen((string)($_SESSION['csrf_token'] ?? '')),
        ]);
        http_response_code(400);
        echo json_encode([
            'error' => true,
            'message' => (string)__('userGroup_csrf_invalid'),
            'results' => [],
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    if (!is_student_mode_enabled()) {
        http_response_code(403);
        echo json_encode([
            'error' => true,
            'message' => (string)__('studentSearch_mode_disabled'),
            'results' => [],
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    if (!checkRateLimit('student_options', 40, 60)) {
        http_response_code(429);
        echo json_encode([
            'error' => true,
            'message' => (string)__('userList_ajax_rate_limited'),
            'results' => [],
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $q = trim((string)($_POST['q'] ?? ''));
    $page = max(1, (int)($_POST['page'] ?? 1));
    $perPage = 20;
    $maxPage = 5;
    if ($page > $maxPage) {
        $page = $maxPage;
    }
    $requestStartedAt = microtime(true);
    studentManagementDiagnosticLog('student_list', 'request_received', [
        'query' => $q,
        'query_length' => mb_strlen($q),
        'page' => $page,
        'per_page' => $perPage,
    ]);

    if (mb_strlen($q) < 2) {
        studentManagementDiagnosticLog('student_list', 'request_short_query', [
            'query' => $q,
            'query_length' => mb_strlen($q),
        ]);
        echo json_encode([
            'error' => false,
            'results' => [],
            'pagination' => ['more' => false],
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $existingIdentifiers = [];
    try {
        $existingSql = "SELECT DISTINCT f_stafID FROM tbl_m_user WHERE TRIM(COALESCE(f_categoryUser, '')) = 'PELAJAR' AND f_stafID IS NOT NULL AND f_stafID <> ''";
        $existingStmt = $pdo->query($existingSql);
        $existingRows = $existingStmt->fetchAll(PDO::FETCH_COLUMN) ?: [];
        foreach ($existingRows as $identifier) {
            $identifier = trim((string)$identifier);
            if ($identifier !== '') {
                $existingIdentifiers[$identifier] = true;
            }
        }
    } catch (Throwable $e) {
        error_log('[user-list-student-options] Error loading existing student identifiers: ' . $e->getMessage());
    }

    $pdoSybase = Database::pdoSybaseStudent();
    if (!$pdoSybase) {
        throw new RuntimeException('Student database connection is not available.');
    }

    $where = ["upper(convert(varchar(20), statuskategori)) = 'AKTIF'"];
    $params = [];

    $where[] = "(
        upper(convert(varchar(50), matrik)) LIKE :q
        OR upper(convert(varchar(255), nama)) LIKE :q
    )";
    $params[':q'] = '%' . strtoupper($q) . '%';

    $whereSql = 'WHERE ' . implode(' AND ', $where);

    $limit = ($perPage * $page) + 1;
    $sql = "
        SELECT TOP {$limit}
            matrik,
            nama,
            program,
            fakulti,
            tahap_pengajian,
            statuskategori
        FROM v210
        {$whereSql}
        ORDER BY nama ASC
    ";

    $executeQuery = static function (PDO $pdoSybaseConnection, string $sqlToRun, array $sqlParams): array {
        $stmt = $pdoSybaseConnection->prepare($sqlToRun);
        foreach ($sqlParams as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    };

    try {
        $allResults = $executeQuery($pdoSybase, $sql, $params);
    } catch (Throwable $primaryQueryError) {
        studentManagementDiagnosticLog('student_list', 'primary_query_failed', [
            'query' => $q,
            'page' => $page,
            'error' => $primaryQueryError->getMessage(),
        ]);

        $fallbackWhere = [];
        $fallbackParams = [];
        $fallbackLimit = $perPage + 1;
        $normalizedQuery = strtoupper($q);

        $fallbackWhere[] = "upper(convert(varchar(20), statuskategori)) = 'AKTIF'";
        $fallbackWhere[] = "(
            upper(convert(varchar(50), matrik)) LIKE :q_prefix
            OR upper(convert(varchar(255), nama)) LIKE :q_name
        )";
        $fallbackParams[':q_prefix'] = $normalizedQuery . '%';
        $fallbackParams[':q_name'] = '%' . $normalizedQuery . '%';

        $fallbackSql = "
            SELECT TOP {$fallbackLimit}
                matrik,
                nama,
                '' AS program,
                '' AS fakulti,
                '' AS tahap_pengajian,
                statuskategori
            FROM v210
            WHERE " . implode(' AND ', $fallbackWhere) . "
            ORDER BY matrik ASC
        ";

        try {
            $fallbackPdo = $pdoSybase;
            try {
                if (function_exists('get_sybase_student_key')) {
                    Database::clearInstance(get_sybase_student_key());
                } else {
                    Database::clearInstance('sybase_student_prod');
                    Database::clearInstance('sybase_student_dev');
                }
                $fallbackPdo = Database::pdoSybaseStudent();
                studentManagementDiagnosticLog('student_list', 'fallback_reconnect_success', [
                    'query' => $q,
                ]);
            } catch (Throwable $reconnectError) {
                studentManagementDiagnosticLog('student_list', 'fallback_reconnect_failed', [
                    'query' => $q,
                    'error' => $reconnectError->getMessage(),
                ]);
            }

            $allResults = $executeQuery($fallbackPdo, $fallbackSql, $fallbackParams);
            $page = 1;
            studentManagementDiagnosticLog('student_list', 'fallback_query_success', [
                'query' => $q,
                'result_count' => count($allResults),
            ]);
        } catch (Throwable $fallbackQueryError) {
            studentManagementDiagnosticLog('student_list', 'fallback_query_failed', [
                'query' => $q,
                'error' => $fallbackQueryError->getMessage(),
            ]);
            throw $fallbackQueryError;
        }
    }

    $startIndex = ($page - 1) * $perPage;
    $hasMore = count($allResults) > ($perPage * $page);
    $results = array_slice($allResults, $startIndex, $perPage);

    $formattedResults = [];
    foreach ($results as $row) {
        $matrik = trim((string)($row['matrik'] ?? ''));
        $nama = trim((string)($row['nama'] ?? ''));
        $fakulti = trim((string)($row['fakulti'] ?? ''));
        $program = trim((string)($row['program'] ?? ''));
        $tahap = trim((string)($row['tahap_pengajian'] ?? ''));
        $statuskategori = trim((string)($row['statuskategori'] ?? ''));

        if ($matrik === '') {
            continue;
        }

        $display = $nama !== '' ? $nama : $matrik;
        $display .= ' (' . $matrik . ')';

        $isDisabled = isset($existingIdentifiers[$matrik]);
        if ($isDisabled) {
            $display .= ' [' . (string)__('userList_student_already_exists') . ']';
        }

        $formattedResults[] = [
            'id' => $matrik,
            'text' => $display,
            'matrik' => $matrik,
            'nama' => $nama,
            'program' => $program,
            'fakulti' => $fakulti,
            'tahap_pengajian' => $tahap,
            'statuskategori' => $statuskategori,
            'disabled' => $isDisabled,
        ];
    }

    echo json_encode([
        'error' => false,
        'results' => $formattedResults,
        'pagination' => ['more' => $hasMore],
    ], JSON_UNESCAPED_UNICODE);
    studentManagementDiagnosticLog('student_list', 'request_success', [
        'query' => $q,
        'page' => $page,
        'raw_result_count' => count($allResults),
        'formatted_result_count' => count($formattedResults),
        'has_more' => $hasMore,
        'duration_ms' => (int)round((microtime(true) - $requestStartedAt) * 1000),
    ]);
} catch (Throwable $e) {
    error_log('[user-list-student-options] Error: ' . $e->getMessage() . ' | q=' . json_encode($q ?? '', JSON_UNESCAPED_UNICODE) . ' | page=' . json_encode($page ?? 1));
    studentManagementDiagnosticLog('student_list', 'request_error', [
        'query' => $q ?? '',
        'page' => $page ?? 1,
        'error' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine(),
    ]);
    http_response_code(500);
    echo json_encode([
        'error' => true,
        'message' => (string)__('studentSearch_system_error'),
        'results' => [],
    ], JSON_UNESCAPED_UNICODE);
}
