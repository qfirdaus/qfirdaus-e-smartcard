<?php
/**
 * IQS FRAMEWORK CORE FILE
 *
 * READ ONLY for downstream project programmers.
 * Do not modify this file directly in template or cloned projects.
 * Custom changes must be implemented in project-specific files
 * or approved extension points.
 */// ajax/menu-subgroup-swap.php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../includes/init.php';
require_login();
require_once __DIR__ . '/_helpers.php';
require_once __DIR__ . '/../classes/Database.php';

function subgroup_swap_bad(string $msg, int $code = 400): never {
    http_response_code($code);
    echo json_encode(['error' => true, 'message' => $msg], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
        subgroup_swap_bad((string)__('userGroup_method_not_allowed'), 405);
    }
    if (!isValidCsrfToken()) {
        subgroup_swap_bad((string)__('userGroup_csrf_invalid'), 403);
    }
    if (!checkRateLimit('menu_subgroup_swap', 30, 60)) {
        subgroup_swap_bad((string)__('userGroup_rate_limit_text'), 429);
    }

    $pdo = Database::getInstance('mysql')->getConnection();
    ensureAjaxGroupManagePermission($pdo, (string)__('userGroup_group_permissions_not_allowed'));

    $payload = json_decode(file_get_contents('php://input') ?: '[]', true) ?: [];
    $aID = (int)($payload['aID'] ?? 0);
    $bID = (int)($payload['bID'] ?? 0);
    if ($aID <= 0 || $bID <= 0 || $aID === $bID) {
        subgroup_swap_bad((string)__('userGroup_invalid_payload'));
    }

    $pdo->beginTransaction();

    $stmt = $pdo->prepare(
        'SELECT f_subgroupID, f_modulID, COALESCE(f_order, 999999) AS f_order
         FROM tbl_m_menu_subgroup
         WHERE f_subgroupID IN (?, ?)
         FOR UPDATE'
    );
    $stmt->execute([$aID, $bID]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    if (count($rows) !== 2) {
        $pdo->rollBack();
        subgroup_swap_bad((string)__('userGroup_subgroup_not_found'), 404);
    }

    $rowA = null;
    $rowB = null;
    foreach ($rows as $row) {
        if ((int)$row['f_subgroupID'] === $aID) {
            $rowA = $row;
        }
        if ((int)$row['f_subgroupID'] === $bID) {
            $rowB = $row;
        }
    }

    if (!$rowA || !$rowB) {
        $pdo->rollBack();
        subgroup_swap_bad((string)__('userGroup_subgroup_not_found'), 404);
    }

    $modulID = (int)$rowA['f_modulID'];
    if ($modulID !== (int)$rowB['f_modulID']) {
        $pdo->rollBack();
        subgroup_swap_bad((string)__('userGroup_subgroup_not_same_module'));
    }

    $orderA = (int)$rowA['f_order'];
    $orderB = (int)$rowB['f_order'];

    $update = $pdo->prepare(
        'UPDATE tbl_m_menu_subgroup
         SET f_order = :sortOrder, f_updatedt = NOW(), f_updateby = :updateby
         WHERE f_subgroupID = :subgroupID'
    );
    $actor = (string)($_SESSION['f_stafID'] ?? $_SESSION['f_nopekerja'] ?? '');
    $update->execute([':sortOrder' => $orderB, ':updateby' => $actor, ':subgroupID' => $aID]);
    $update->execute([':sortOrder' => $orderA, ':updateby' => $actor, ':subgroupID' => $bID]);

    $pdo->commit();

    GroupDataCache::clear('group_access_');
    GroupDataCache::clear('group_perms_');
    clearSidebarNavigationCaches();

    echo json_encode([
        'error' => false,
        'message' => (string)__('userGroup_ok'),
        'modulID' => $modulID,
        'swapped' => ['a' => $aID, 'b' => $bID],
    ], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('[menu-subgroup-swap] ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'error' => true,
        'message' => (string)__('userGroup_err_server'),
    ], JSON_UNESCAPED_UNICODE);
}
