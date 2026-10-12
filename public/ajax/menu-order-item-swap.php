<?php
/**
 * IQS FRAMEWORK CORE FILE
 *
 * READ ONLY for downstream project programmers.
 * Do not modify this file directly in template or cloned projects.
 * Custom changes must be implemented in project-specific files
 * or approved extension points.
 */// ajax/menu-order-item-swap.php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../includes/init.php';
require_login();
require_once __DIR__ . '/_helpers.php';
require_once __DIR__ . '/../classes/Database.php';

function menu_order_item_bad(string $message, int $code = 400): never {
    http_response_code($code);
    echo json_encode(['error' => true, 'message' => $message], JSON_UNESCAPED_UNICODE);
    exit;
}

function menu_order_item_fetch(PDO $pdo, string $type, int $id): ?array {
    if ($type === 'menu') {
        $stmt = $pdo->prepare(
            "SELECT 'menu' AS item_type,
                    f_menuID AS item_id,
                    f_modulID,
                    COALESCE(f_subgroupID, 0) AS subgroup_id,
                    COALESCE(f_order, 999999) AS sort_order
             FROM tbl_m_menu
             WHERE f_menuID = ?
             LIMIT 1"
        );
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
        if ($row && (int)($row['subgroup_id'] ?? 0) > 0) {
            return null;
        }
        return $row;
    }

    if ($type === 'subgroup') {
        $stmt = $pdo->prepare(
            "SELECT 'subgroup' AS item_type,
                    f_subgroupID AS item_id,
                    f_modulID,
                    0 AS subgroup_id,
                    COALESCE(f_order, 999999) AS sort_order
             FROM tbl_m_menu_subgroup
             WHERE f_subgroupID = ?
             LIMIT 1"
        );
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    return null;
}

function menu_order_item_update(PDO $pdo, string $type, int $id, int $order, string $actor): void {
    if ($type === 'menu') {
        $stmt = $pdo->prepare('UPDATE tbl_m_menu SET f_order = :sortOrder, f_updatedt = NOW(), f_updateby = :actor WHERE f_menuID = :id');
        $stmt->execute([':sortOrder' => $order, ':actor' => $actor, ':id' => $id]);
        return;
    }

    $stmt = $pdo->prepare('UPDATE tbl_m_menu_subgroup SET f_order = :sortOrder, f_updatedt = NOW(), f_updateby = :actor WHERE f_subgroupID = :id');
    $stmt->execute([':sortOrder' => $order, ':actor' => $actor, ':id' => $id]);
}

function menu_order_item_normalize(PDO $pdo, int $modulID, string $actor): void {
    $stmt = $pdo->prepare(
        "SELECT item_type, item_id
         FROM (
             SELECT 'menu' AS item_type,
                    f_menuID AS item_id,
                    COALESCE(f_order, 999999) AS sort_order
             FROM tbl_m_menu
             WHERE f_modulID = :mid_menu
               AND COALESCE(f_subgroupID, 0) = 0
             UNION ALL
             SELECT 'subgroup' AS item_type,
                    f_subgroupID AS item_id,
                    COALESCE(f_order, 999999) AS sort_order
             FROM tbl_m_menu_subgroup
             WHERE f_modulID = :mid_subgroup
               AND COALESCE(f_status, 1) = 1
         ) ordered_items
         ORDER BY sort_order ASC, item_type ASC, item_id ASC"
    );
    $stmt->execute([':mid_menu' => $modulID, ':mid_subgroup' => $modulID]);
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

    $order = 10;
    foreach ($items as $item) {
        menu_order_item_update($pdo, (string)$item['item_type'], (int)$item['item_id'], $order, $actor);
        $order += 10;
    }
}

try {
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
        menu_order_item_bad((string)__('userGroup_method_not_allowed'), 405);
    }
    if (!isValidCsrfToken()) {
        menu_order_item_bad((string)__('userGroup_csrf_invalid'), 403);
    }
    if (!checkRateLimit('menu_order_item_swap', 30, 60)) {
        menu_order_item_bad((string)__('userGroup_rate_limit_text'), 429);
    }

    $pdo = Database::getInstance('mysql')->getConnection();
    ensureAjaxGroupManagePermission($pdo, (string)__('userGroup_group_permissions_not_allowed'));

    $payload = json_decode(file_get_contents('php://input') ?: '[]', true) ?: [];
    $aType = (string)($payload['aType'] ?? '');
    $bType = (string)($payload['bType'] ?? '');
    $aID = (int)($payload['aID'] ?? 0);
    $bID = (int)($payload['bID'] ?? 0);
    $postedModulID = (int)($payload['modulID'] ?? 0);

    $allowedTypes = ['menu', 'subgroup'];
    if (!in_array($aType, $allowedTypes, true) || !in_array($bType, $allowedTypes, true) || $aID <= 0 || $bID <= 0) {
        menu_order_item_bad((string)__('userGroup_invalid_payload'));
    }
    if ($aType === $bType && $aID === $bID) {
        menu_order_item_bad((string)__('userGroup_invalid_payload'));
    }

    $actor = (string)($_SESSION['f_stafID'] ?? $_SESSION['f_nopekerja'] ?? '');
    $pdo->beginTransaction();

    $itemA = menu_order_item_fetch($pdo, $aType, $aID);
    $itemB = menu_order_item_fetch($pdo, $bType, $bID);
    if (!$itemA || !$itemB) {
        $pdo->rollBack();
        menu_order_item_bad((string)__('userGroup_menu_not_found'), 404);
    }

    $modulID = (int)$itemA['f_modulID'];
    if ($modulID !== (int)$itemB['f_modulID'] || ($postedModulID > 0 && $postedModulID !== $modulID)) {
        $pdo->rollBack();
        menu_order_item_bad((string)__('userGroup_menu_not_same_module'));
    }

    menu_order_item_normalize($pdo, $modulID, $actor);

    $itemA = menu_order_item_fetch($pdo, $aType, $aID);
    $itemB = menu_order_item_fetch($pdo, $bType, $bID);
    if (!$itemA || !$itemB) {
        $pdo->rollBack();
        menu_order_item_bad((string)__('userGroup_menu_not_found'), 404);
    }

    $orderA = (int)$itemA['sort_order'];
    $orderB = (int)$itemB['sort_order'];
    menu_order_item_update($pdo, $aType, $aID, $orderB, $actor);
    menu_order_item_update($pdo, $bType, $bID, $orderA, $actor);

    $pdo->commit();

    GroupDataCache::clear('group_access_');
    GroupDataCache::clear('group_perms_');
    GroupDataCache::clear('menu_list_');
    clearSidebarNavigationCaches();

    echo json_encode([
        'error' => false,
        'message' => (string)__('userGroup_ok'),
        'modulID' => $modulID,
        'swapped' => [
            'a' => ['type' => $aType, 'id' => $aID],
            'b' => ['type' => $bType, 'id' => $bID],
        ],
    ], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('[menu-order-item-swap] ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'error' => true,
        'message' => (string)__('userGroup_err_server'),
    ], JSON_UNESCAPED_UNICODE);
}
