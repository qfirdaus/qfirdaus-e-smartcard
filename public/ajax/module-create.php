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

function moduleCreateActorLabel(): ?string
{
    if (function_exists('audit_format_actor_label')) {
        return audit_format_actor_label();
    }

    return $_SESSION['user']['f_nama'] ?? $_SESSION['f_nama'] ?? null;
}

function moduleCreateAuditChanges(?int $eventId, int $moduleId, array $changes): void
{
    if (!$eventId || !function_exists('audit_begin_change') || !function_exists('audit_change')) {
        return;
    }

    $changeSetId = audit_begin_change($eventId, 'module', (string)$moduleId, 'Module creation', [
        'source' => 'module-create',
    ]);
    if (!$changeSetId) {
        return;
    }

    $fieldTypes = [
        'f_order' => 'integer',
        'groups_granted' => 'integer',
    ];
    foreach ($changes as $field => $newValue) {
        audit_change($changeSetId, (string)$field, null, $newValue, $fieldTypes[$field] ?? 'string', false);
    }
}

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

    $nameMs = trim((string)($payload['modulNameMs'] ?? ''));
    $nameEn = trim((string)($payload['modulNameEn'] ?? ''));
    $icon = trim((string)($payload['icon'] ?? ''));

    if ($nameMs === '') {
        http_response_code(422);
        echo json_encode(['error' => true, 'message' => __('modul_ralat_wajib')], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $allowedIcons = array_values(array_unique(array_filter(array_map(
        static fn($value): string => trim((string)$value),
        SystemConfigConstants::ALLOWED_SIDEBAR_ICONS
    ))));
    if (!in_array($icon, $allowedIcons, true)) {
        $icon = 'ri-folder-fill';
    }

    $dupSql = "
      SELECT 1
      FROM tbl_m_modul
      WHERE LOWER(TRIM(f_modulName_ms)) = LOWER(TRIM(:name_ms_1))
         OR LOWER(TRIM(f_modulName_en)) = LOWER(TRIM(:name_ms_2))
    ";
    $dupParams = [
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
    $dupSql .= " LIMIT 1";

    $dupStmt = $pdo->prepare($dupSql);
    $dupStmt->execute($dupParams);
    if ($dupStmt->fetchColumn()) {
        http_response_code(422);
        echo json_encode(['error' => true, 'message' => __('modul_ralat_duplikat')], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $pdo->beginTransaction();

    $orderStmt = $pdo->query("SELECT COALESCE(MAX(f_order), 0) + 1 AS next_order FROM tbl_m_modul");
    $orderVal = (int)($orderStmt->fetchColumn() ?: 1);
    if ($orderVal <= 0) {
        $orderVal = 1;
    }

    $ins = $pdo->prepare("
      INSERT INTO tbl_m_modul (f_modulName_ms, f_modulName_en, f_icon, f_order)
      VALUES (:name_ms, :name_en, :icon, :f_order)
    ");
    $ins->execute([
        ':name_ms' => $nameMs,
        ':name_en' => ($nameEn !== '' ? $nameEn : null),
        ':icon' => $icon,
        ':f_order' => $orderVal,
    ]);

    $newModuleId = (int)$pdo->lastInsertId();
    if ($newModuleId <= 0) {
        throw new RuntimeException((string)__('userGroup_error_unknown'));
    }

    $groups = $pdo->query("SELECT f_groupID, COALESCE(f_modulAccess, '') AS f_modulAccess FROM tbl_m_group FOR UPDATE")
        ->fetchAll(PDO::FETCH_ASSOC) ?: [];
    $updateGroupAccess = $pdo->prepare("UPDATE tbl_m_group SET f_modulAccess = :access WHERE f_groupID = :gid");

    foreach ($groups as $groupRow) {
        $groupId = (int)($groupRow['f_groupID'] ?? 0);
        if ($groupId <= 0) {
            continue;
        }

        $ids = array_values(array_filter(array_map(static function ($value): ?int {
            $value = trim((string)$value);
            return ctype_digit($value) ? (int)$value : null;
        }, explode(',', (string)($groupRow['f_modulAccess'] ?? ''))), static fn($value) => $value !== null));

        if (!in_array($newModuleId, $ids, true)) {
            $ids[] = $newModuleId;
        }

        $updateGroupAccess->execute([
            ':access' => implode(',', array_values(array_unique($ids))),
            ':gid' => $groupId,
        ]);
    }

    $pdo->commit();

    clearGroupUiCaches();
    GroupDataCache::clear('modul_list_');
    clearSidebarNavigationCaches();

    try {
        if (function_exists('audit_event')) {
            $actorLabel = moduleCreateActorLabel();
            $eventId = audit_event([
                'event_type' => 'CREATE',
                'severity' => 'INFO',
                'outcome' => 'SUCCESS',
                'target_type' => 'module',
                'target_id' => (string)$newModuleId,
                'target_label' => $nameMs,
                'message' => function_exists('audit_format_message')
                    ? audit_format_message('Module created', $actorLabel)
                    : 'Module created',
                'actor_label' => $actorLabel,
                'meta' => [
                    'module_id' => $newModuleId,
                    'name_ms' => $nameMs,
                    'name_en' => $nameEn,
                    'icon' => $icon,
                    'order' => $orderVal,
                    'groups_granted' => count($groups),
                ],
            ]);
            moduleCreateAuditChanges($eventId, $newModuleId, [
                'f_modulName_ms' => $nameMs,
                'f_modulName_en' => $nameEn !== '' ? $nameEn : null,
                'f_icon' => $icon,
                'f_order' => $orderVal,
                'groups_granted' => count($groups),
            ]);
        }
    } catch (Throwable $auditError) {
        error_log('[module-create] Audit logging failed: ' . $auditError->getMessage());
    }

    echo json_encode([
        'success' => true,
        'message' => __('modul_berjaya_msg'),
        'module' => [
            'id' => $newModuleId,
            'modulNameMs' => $nameMs,
            'modulNameEn' => $nameEn,
            'icon' => $icon,
            'order' => $orderVal,
        ],
    ], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('[module-create] ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'error' => true,
        'message' => __('userGroup_error_unknown'),
    ], JSON_UNESCAPED_UNICODE);
}
