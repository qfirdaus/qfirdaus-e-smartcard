<?php
/**
 * IQS FRAMEWORK CORE FILE
 *
 * READ ONLY for downstream project programmers.
 * Do not modify this file directly in template or cloned projects.
 * Custom changes must be implemented in project-specific files
 * or approved extension points.
 */// ajax/user-search-pelajar.php
// Search pelajar from Sybase Student for Select2 dropdown
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

    if (!isValidCsrfToken((string)($_POST['csrf_token'] ?? ''))) {
        http_response_code(400);
        echo json_encode([
            'error' => true,
            'message' => (string)__('userGroup_csrf_invalid'),
            'results' => []
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    if (!is_student_mode_enabled()) {
        http_response_code(403);
        echo json_encode([
            'error' => true,
            'message' => (string)__('studentSearch_mode_disabled'),
            'results' => []
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $q = trim((string)($_POST['q'] ?? ''));
    $page = max(1, (int)($_POST['page'] ?? 1));
    $loadAll = (string)($_POST['all'] ?? '') === '1';
    $perPage = 20;

    if (mb_strlen($q) < 2) {
        echo json_encode([
            'error' => false,
            'results' => [],
            'pagination' => ['more' => false]
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $pdoSybase = Database::pdoSybaseStudent();

    $where = ["statuskategori = 'AKTIF'"];
    $params = [];

    if ($q !== '') {
        $where[] = "(
            upper(convert(varchar(50), matrik)) LIKE :q
            OR upper(convert(varchar(255), nama)) LIKE :q
            OR upper(convert(varchar(255), fakulti)) LIKE :q
        )";
        $params[':q'] = '%' . strtoupper($q) . '%';
    }

    $whereSql = 'WHERE ' . implode(' AND ', $where);

    $total = 0;
    $limit = $perPage * $page;
    if ($loadAll) {
        $sql = "
            SELECT
                matrik,
                nama,
                fakulti
            FROM v210
            {$whereSql}
            ORDER BY nama ASC
        ";
    } else {
        $countSql = "
            SELECT COUNT(*) AS total
            FROM v210
            {$whereSql}
        ";
        $countStmt = $pdoSybase->prepare($countSql);
        foreach ($params as $key => $value) {
            $countStmt->bindValue($key, $value);
        }
        $countStmt->execute();
        $total = (int)($countStmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0);

        $sql = "
            SELECT TOP {$limit}
                matrik,
                nama,
                fakulti
            FROM v210
            {$whereSql}
            ORDER BY nama ASC
        ";
    }

    $stmt = $pdoSybase->prepare($sql);
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    $stmt->execute();
    $allResults = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $startIndex = ($page - 1) * $perPage;
    $results = $loadAll ? $allResults : array_slice($allResults, $startIndex, $perPage);

    $formattedResults = [];
    foreach ($results as $row) {
        $matrik = trim((string)($row['matrik'] ?? ''));
        $nama = trim((string)($row['nama'] ?? ''));
        $fakulti = trim((string)($row['fakulti'] ?? ''));

        if ($matrik === '') {
            continue;
        }

        $text = $nama !== '' ? $nama : $matrik;
        if ($matrik !== '') {
            $text .= ' (' . $matrik . ')';
        }
        if ($fakulti !== '') {
            $text .= ' - ' . $fakulti;
        }

        $formattedResults[] = [
            'id' => $matrik,
            'text' => $text,
            'matrik' => $matrik,
            'nama' => $nama,
            'fakulti' => $fakulti,
        ];
    }

    $hasMore = !$loadAll && ($startIndex + count($results)) < $total && count($allResults) >= $limit;

    echo json_encode([
        'error' => false,
        'results' => $formattedResults,
        'pagination' => [
            'more' => $hasMore,
        ],
    ], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    error_log('[user-search-pelajar] Error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'error' => true,
        'message' => (string)__('studentSearch_system_error'),
        'results' => []
    ], JSON_UNESCAPED_UNICODE);
}
