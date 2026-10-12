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
require_login();
require_once __DIR__ . '/_helpers.php';
require_once __DIR__ . '/../classes/Database.php';
require_once __DIR__ . '/../classes/SystemConfigConstants.php';

header('Content-Type: application/json; charset=UTF-8');

try {
    $pdo = Database::getInstance('mysql')->getConnection();
    ensureAjaxGroupManagePermission($pdo);

    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
        http_response_code(405);
        echo json_encode(['error' => true, 'message' => __('manual_method_not_allowed')], JSON_UNESCAPED_UNICODE);
        exit;
    }

    if (!isValidCsrfToken()) {
        http_response_code(419);
        echo json_encode(['error' => true, 'message' => __('manual_csrf_invalid')], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $payload = json_decode((string)file_get_contents('php://input'), true);
    if (!is_array($payload)) {
        $payload = $_POST;
    }

    $moduleID = (int)($payload['moduleID'] ?? 0);
    $nameMs = trim((string)($payload['modulNameMs'] ?? ''));
    $nameEn = trim((string)($payload['modulNameEn'] ?? ''));
    $icon = trim((string)($payload['icon'] ?? ''));

    if ($moduleID <= 0) {
        http_response_code(422);
        echo json_encode(['error' => true, 'message' => __('modul_ralat_tidak_sah')], JSON_UNESCAPED_UNICODE);
        exit;
    }

    if ($nameMs === '') {
        http_response_code(422);
        echo json_encode(['error' => true, 'message' => __('modul_ralat_wajib')], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $stmtCheck = $pdo->prepare("SELECT f_modulID FROM tbl_m_modul WHERE f_modulID = :id LIMIT 1");
    $stmtCheck->execute([':id' => $moduleID]);
    if (!$stmtCheck->fetchColumn()) {
        http_response_code(404);
        echo json_encode(['error' => true, 'message' => __('modul_ralat_tidak_jumpa')], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $dupSql = "
      SELECT 1
      FROM tbl_m_modul
      WHERE f_modulID <> :module_id
        AND (
          LOWER(TRIM(f_modulName_ms)) = LOWER(TRIM(:name_ms_1))
          OR LOWER(TRIM(f_modulName_en)) = LOWER(TRIM(:name_ms_2))
    ";
    $dupParams = [
        ':module_id' => $moduleID,
        ':name_ms_1' => $nameMs,
        ':name_ms_2' => $nameMs,
    ];
    if ($nameEn !== '') {
        $dupSql .= "
          OR LOWER(TRIM(f_modulName_ms)) = LOWER(TRIM(:name_en_1))
          OR LOWER(TRIM(f_modulName_en)) = LOWER(TRIM(:name_en_2))
        ";
        $dupParams[':name_en_1'] = $nameEn;
        $dupParams[':name_en_2'] = $nameEn;
    }
    $dupSql .= ") LIMIT 1";

    $dupStmt = $pdo->prepare($dupSql);
    $dupStmt->execute($dupParams);
    if ($dupStmt->fetchColumn()) {
        http_response_code(422);
        echo json_encode(['error' => true, 'message' => __('modul_ralat_duplikat')], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $allowedIcons = array_values(array_unique(array_filter(array_map(
        static fn($value): string => trim((string)$value),
        SystemConfigConstants::ALLOWED_SIDEBAR_ICONS
    ))));
    if (!in_array($icon, $allowedIcons, true)) {
        $icon = 'ri-folder-fill';
    }

    $stmtUpdate = $pdo->prepare("
      UPDATE tbl_m_modul
      SET f_modulName_ms = :name_ms,
          f_modulName_en = :name_en,
          f_icon = :icon
      WHERE f_modulID = :id
    ");
    $stmtUpdate->execute([
        ':name_ms' => $nameMs,
        ':name_en' => ($nameEn !== '' ? $nameEn : null),
        ':icon' => $icon,
        ':id' => $moduleID,
    ]);

    clearGroupUiCaches();
    GroupDataCache::clear('modul_list_');
    clearSidebarNavigationCaches();

    echo json_encode([
        'success' => true,
        'message' => __('modul_kemaskini_msg'),
        'module' => [
            'id' => $moduleID,
            'modulNameMs' => $nameMs,
            'modulNameEn' => $nameEn,
            'icon' => $icon,
        ],
    ], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    error_log('[module-update] ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'error' => true,
        'message' => __('userGroup_error_unknown'),
    ], JSON_UNESCAPED_UNICODE);
}
