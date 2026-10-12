<?php
/**
 * IQS FRAMEWORK CORE FILE
 *
 * READ ONLY for downstream project programmers.
 * Do not modify this file directly in template or cloned projects.
 * Custom changes must be implemented in project-specific files
 * or approved extension points.
 */// ajax/user-search-staf.php
// Search staf from Sybase for Select2 dropdown
declare(strict_types=1);

error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');

// Clean output buffers
while (ob_get_level() > 0) {
    @ob_end_clean();
}

header('Content-Type: application/json; charset=utf-8');

try {
    require_once __DIR__ . '/../includes/init.php';
    require_login();
    require_once __DIR__ . '/_helpers.php';
    require_once __DIR__ . '/../classes/Database.php';

    $pdo = Database::getInstance('mysql')->getConnection();
    ensureAjaxGroupManagePermission($pdo);

    // Check CSRF
    if (!isValidCsrfToken((string)($_POST['csrf_token'] ?? ''))) {
        http_response_code(400);
        echo json_encode([
            'error' => true,
            'message' => (string)__('userGroup_csrf_invalid'),
            'results' => []
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // Get search query
    $q = trim((string)($_POST['q'] ?? ''));
    $page = max(1, (int)($_POST['page'] ?? 1));
    $perPage = 20;

    if (strlen($q) < 2) {
        echo json_encode([
            'error' => false,
            'results' => [],
            'pagination' => ['more' => false]
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // Connect to Sybase
    $pdoSybase = Database::pdoSybaseStaff();

    // Build WHERE clause
    $where = ["CONVERT(INT, kodstatus) = 1"];
    $params = [];

    if ($q !== '') {
        $where[] = "(gelar_nama LIKE :q OR nama LIKE :q OR nopekerja LIKE :q OR idpekerja LIKE :q)";
        $params[':q'] = "%{$q}%";
    }

    $whereSql = 'WHERE ' . implode(' AND ', $where);

    // Count total (for pagination)
    $countSql = "
        SELECT COUNT(*) as total
        FROM v630staf_service_skim_all
        {$whereSql}
    ";
    $countStmt = $pdoSybase->prepare($countSql);
    foreach ($params as $k => $v) {
        $countStmt->bindValue($k, $v);
    }
    $countStmt->execute();
    $total = (int)($countStmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0);

    // Get results (Sybase pagination: TOP N)
    // Note: Sybase doesn't support OFFSET, so for simplicity we limit to first page results
    // Select2 will handle pagination by making new requests
    $limit = $perPage * $page; // Get more results for pagination
    $sql = "
        SELECT TOP {$limit}
            nopekerja,
            idpekerja,
            gelar_nama,
            nama,
            jabatansemasa,
            jawatansemasa
        FROM v630staf_service_skim_all
        {$whereSql}
        ORDER BY gelar_nama ASC, nama ASC
    ";

    $stmt = $pdoSybase->prepare($sql);
    foreach ($params as $k => $v) {
        $stmt->bindValue($k, $v);
    }
    $stmt->execute();
    $allResults = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Slice results for current page (simulate pagination)
    $startIndex = ($page - 1) * $perPage;
    $results = array_slice($allResults, $startIndex, $perPage);

    // Format results for Select2
    $formattedResults = [];
    foreach ($results as $row) {
        $nopekerja = trim((string)($row['nopekerja'] ?? ''));
        $idpekerja = trim((string)($row['idpekerja'] ?? ''));
        $gelarNama = trim((string)($row['gelar_nama'] ?? ''));
        $nama = trim((string)($row['nama'] ?? ''));
        
        if ($nopekerja === '') continue;

        $displayText = $gelarNama ?: $nama;
        if ($nopekerja) {
            $displayText .= ' (' . $nopekerja . ')';
        }

        $formattedResults[] = [
            'id' => $nopekerja,
            'text' => $displayText,
            'nopekerja' => $nopekerja,
            'idpekerja' => $idpekerja,
            'gelar_nama' => $gelarNama,
            'nama' => $nama,
            'jabatansemasa' => trim((string)($row['jabatansemasa'] ?? '')),
            'jawatansemasa' => trim((string)($row['jawatansemasa'] ?? ''))
        ];
    }

    // Check if more results available
    $hasMore = ($startIndex + count($results)) < $total && count($allResults) >= $limit;

    echo json_encode([
        'error' => false,
        'results' => $formattedResults,
        'pagination' => [
            'more' => $hasMore
        ]
    ], JSON_UNESCAPED_UNICODE);

} catch (Throwable $e) {
    error_log('[user-search-staf] Error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'error' => true,
        'message' => (string)__('userList_ajax_search_staff_error'),
        'results' => []
    ], JSON_UNESCAPED_UNICODE);
}
