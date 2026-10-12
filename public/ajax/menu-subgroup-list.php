<?php
/**
 * IQS FRAMEWORK CORE FILE
 *
 * READ ONLY for downstream project programmers.
 * Do not modify this file directly in template or cloned projects.
 * Custom changes must be implemented in project-specific files
 * or approved extension points.
 */declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../includes/init.php';
require_login();
require_once __DIR__ . '/_helpers.php';
require_once __DIR__ . '/../classes/Database.php';

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        http_response_code(405);
        echo json_encode(['error' => true, 'message' => (string)__('userGroup_method_not_allowed')], JSON_UNESCAPED_UNICODE);
        exit;
    }

    if (!checkRateLimit('menu_subgroup_list', 30, 60)) {
        http_response_code(429);
        echo json_encode(['error' => true, 'message' => (string)__('userGroup_rate_limit_text')], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $pdo = Database::pdoMysql();
    ensureAjaxGroupManagePermission($pdo);

    $lang = preg_replace('/[^a-z_]/i', '', (string)($_SESSION['lang'] ?? 'ms')) ?: 'ms';
    $nameField = $lang === 'en' ? 'f_subgroupName_en' : 'f_subgroupName_ms';
    $modulID = isset($_GET['modulID']) ? (int)$_GET['modulID'] : 0;
    $active = isset($_GET['active']) ? (int)$_GET['active'] : 1;

    $sql = "
        SELECT
            sg.f_subgroupID AS id,
            sg.f_modulID AS modulID,
            sg.f_subgroupCode AS code,
            COALESCE(NULLIF(sg.$nameField, ''), NULLIF(sg.f_subgroupName_ms, ''), NULLIF(sg.f_subgroupName_en, ''), CONCAT('Subgroup ', sg.f_subgroupID)) AS name,
            sg.f_subgroupName_ms AS name_ms,
            sg.f_subgroupName_en AS name_en,
            COALESCE(sg.f_icon, 'ri-folder-2-line') AS icon,
            COALESCE(sg.f_order, 1) AS sortOrder,
            CAST(sg.f_status AS UNSIGNED) AS status,
            COALESCE(m.f_modulName_$lang, m.f_modulName_ms, m.f_modulName_en, CONCAT('Modul ', sg.f_modulID)) AS modulName,
            (SELECT COUNT(*) FROM tbl_m_menu menu WHERE menu.f_subgroupID = sg.f_subgroupID) AS menuCount
        FROM tbl_m_menu_subgroup sg
        LEFT JOIN tbl_m_modul m ON m.f_modulID = sg.f_modulID
    ";
    $conds = [];
    $params = [];
    if ($modulID > 0) {
        $conds[] = 'sg.f_modulID = ?';
        $params[] = $modulID;
    }
    if ($active === 1) {
        $conds[] = 'sg.f_status = 1';
    }
    if ($conds) {
        $sql .= ' WHERE ' . implode(' AND ', $conds);
    }
    $sql .= ' ORDER BY sg.f_modulID ASC, COALESCE(sg.f_order, 99999) ASC, sg.f_subgroupID ASC';

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $subgroups = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(['error' => false, 'subgroups' => $subgroups, 'count' => count($subgroups)], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    http_response_code(500);
    error_log('[menu-subgroup-list] ' . $e->getMessage());
    echo json_encode(['error' => true, 'message' => (string)__('userGroup_err_server')], JSON_UNESCAPED_UNICODE);
}
