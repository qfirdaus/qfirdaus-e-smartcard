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

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonErrorResponse((string) __('formList_error_invalid_method'), 405);
}

if (!isValidCsrfToken()) {
    jsonErrorResponse((string) __('formList_error_invalid_csrf'), 419);
}

$id = (int) ($_POST['borangID'] ?? 0);
$namaMs = trim((string) ($_POST['nama_ms'] ?? ''));
$namaEn = trim((string) ($_POST['nama_en'] ?? ''));
$kategoriID = (int) ($_POST['kategoriID'] ?? 0);
$path = ltrim(str_replace('\\', '/', trim((string) ($_POST['path'] ?? ''))), '/');
$icon = trim((string) ($_POST['icon'] ?? 'ri-file-line')) ?: 'ri-file-line';
$flag = (int) ($_POST['flag'] ?? 1);

if ($namaMs === '' || $path === '' || $kategoriID <= 0) {
    jsonErrorResponse((string) __('formList_error_required_fields'), 422);
}

function borangSaveAudit(string $eventType, int $borangId, string $namaMs, array $meta = [], ?array $oldValues = null, array $newValues = []): void
{
    try {
        if (!function_exists('audit_event')) {
            return;
        }

        $actorLabel = function_exists('audit_format_actor_label')
            ? audit_format_actor_label()
            : ($_SESSION['user']['f_nama'] ?? $_SESSION['f_nama'] ?? null);

        $eventId = audit_event([
            'event_type' => $eventType,
            'severity' => 'INFO',
            'outcome' => 'SUCCESS',
            'target_type' => 'form',
            'target_id' => (string)$borangId,
            'target_label' => $namaMs,
            'message' => function_exists('audit_format_message')
                ? audit_format_message($eventType === 'CREATE' ? 'Form created' : 'Form updated', $actorLabel)
                : ($eventType === 'CREATE' ? 'Form created' : 'Form updated'),
            'actor_label' => $actorLabel,
            'meta' => $meta,
        ]);

        if (!$eventId || !function_exists('audit_begin_change') || !function_exists('audit_change')) {
            return;
        }

        $changeSetId = audit_begin_change($eventId, 'form', (string)$borangId, $eventType === 'CREATE' ? 'Form creation' : 'Form update', [
            'source' => 'borang-save',
        ]);
        if (!$changeSetId) {
            return;
        }

        $fieldTypes = [
            'f_kategoriID' => 'integer',
            'f_flag' => 'integer',
            'f_order' => 'integer',
        ];
        foreach ($newValues as $field => $newValue) {
            $oldValue = $oldValues[$field] ?? null;
            if ($eventType !== 'CREATE' && (string)$oldValue === (string)$newValue) {
                continue;
            }
            audit_change($changeSetId, (string)$field, $oldValue, $newValue, $fieldTypes[$field] ?? 'string', false);
        }
    } catch (Throwable $auditError) {
        error_log('[borang-save] Audit logging failed: ' . $auditError->getMessage());
    }
}

try {
    $dupSql = "
        SELECT 1
        FROM tbl_m_borang
        WHERE LOWER(TRIM(f_nama_ms)) = LOWER(TRIM(:nama))
    ";
    $params = [':nama' => $namaMs];
    if ($id > 0) {
        $dupSql .= ' AND f_borangID != :id';
        $params[':id'] = $id;
    }
    $dupSql .= ' LIMIT 1';

    $dupStmt = $pdo->prepare($dupSql);
    $dupStmt->execute($params);
    if ($dupStmt->fetch()) {
        jsonErrorResponse((string) __('formList_error_duplicate_name'), 409);
    }

    if ($id <= 0) {
        $orderStmt = $pdo->query('SELECT COALESCE(MAX(f_order), 0) + 1 FROM tbl_m_borang');
        $nextOrder = (int) $orderStmt->fetchColumn();

        $stmt = $pdo->prepare(
            "
            INSERT INTO tbl_m_borang
                (f_nama_ms, f_nama_en, f_kategoriID, f_path, f_icon, f_flag, f_order)
            VALUES
                (:nama_ms, :nama_en, :kategoriID, :path, :icon, :flag, :order)
            "
        );
        $stmt->execute([
            ':nama_ms' => $namaMs,
            ':nama_en' => $namaEn !== '' ? $namaEn : null,
            ':kategoriID' => $kategoriID,
            ':path' => $path,
            ':icon' => $icon,
            ':flag' => $flag,
            ':order' => max(1, $nextOrder),
        ]);

        $newId = (int)$pdo->lastInsertId();
        $newValues = [
            'f_nama_ms' => $namaMs,
            'f_nama_en' => $namaEn !== '' ? $namaEn : null,
            'f_kategoriID' => $kategoriID,
            'f_path' => $path,
            'f_icon' => $icon,
            'f_flag' => $flag,
            'f_order' => max(1, $nextOrder),
        ];
        borangSaveAudit('CREATE', $newId, $namaMs, [
            'borang_id' => $newId,
            'name_ms' => $namaMs,
            'name_en' => $namaEn,
            'kategori_id' => $kategoriID,
            'path' => $path,
            'icon' => $icon,
            'flag' => $flag,
            'order' => max(1, $nextOrder),
        ], null, $newValues);

        jsonSuccessResponse(['message' => (string) __('formList_success_created')]);
    }

    $currentStmt = $pdo->prepare('SELECT f_nama_ms, f_nama_en, f_kategoriID, f_path, f_icon, f_flag, f_order FROM tbl_m_borang WHERE f_borangID = :id LIMIT 1');
    $currentStmt->execute([':id' => $id]);
    $currentRow = $currentStmt->fetch(PDO::FETCH_ASSOC) ?: [];
    $currentOrder = (int)($currentRow['f_order'] ?? 1);

    $stmt = $pdo->prepare(
        "
        UPDATE tbl_m_borang
        SET
            f_nama_ms = :nama_ms,
            f_nama_en = :nama_en,
            f_kategoriID = :kategoriID,
            f_path = :path,
            f_icon = :icon,
            f_flag = :flag,
            f_order = :order
        WHERE f_borangID = :id
        LIMIT 1
        "
    );
    $stmt->execute([
        ':nama_ms' => $namaMs,
        ':nama_en' => $namaEn !== '' ? $namaEn : null,
        ':kategoriID' => $kategoriID,
        ':path' => $path,
        ':icon' => $icon,
        ':flag' => $flag,
        ':order' => max(1, $currentOrder),
        ':id' => $id,
    ]);

    $newValues = [
        'f_nama_ms' => $namaMs,
        'f_nama_en' => $namaEn !== '' ? $namaEn : null,
        'f_kategoriID' => $kategoriID,
        'f_path' => $path,
        'f_icon' => $icon,
        'f_flag' => $flag,
        'f_order' => max(1, $currentOrder),
    ];
    borangSaveAudit('UPDATE', $id, $namaMs, [
        'borang_id' => $id,
        'name_ms' => $namaMs,
        'name_en' => $namaEn,
        'kategori_id' => $kategoriID,
        'path' => $path,
        'icon' => $icon,
        'flag' => $flag,
        'order' => max(1, $currentOrder),
    ], $currentRow, $newValues);

    jsonSuccessResponse(['message' => (string) __('formList_success_updated')]);
} catch (Throwable $e) {
    error_log('[borang-save] ' . $e->getMessage());
    jsonErrorResponse((string) __('formList_error_generic'), 500);
}
