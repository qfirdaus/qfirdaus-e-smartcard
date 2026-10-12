<?php
/**
 * IQS FRAMEWORK CORE FILE
 *
 * READ ONLY for downstream project programmers.
 * Do not modify this file directly in template or cloned projects.
 * Custom changes must be implemented in project-specific files
 * or approved extension points.
 */// ajax/user-sync-student.php
declare(strict_types=1);

error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');

function userSyncStudentAudit(int $inserted, int $updated, int $skipped, int $errors, int $total, int $groupID, string $groupKod): void
{
    try {
        if (!function_exists('audit_event')) {
            return;
        }

        $actorLabel = function_exists('audit_format_actor_label')
            ? audit_format_actor_label()
            : ($_SESSION['user']['f_nama'] ?? $_SESSION['f_nama'] ?? null);

        $eventId = audit_event([
            'event_type' => 'UPDATE',
            'severity' => 'INFO',
            'outcome' => $errors > 0 ? 'PARTIAL' : 'SUCCESS',
            'target_type' => 'user_sync',
            'target_id' => 'student',
            'target_label' => 'Student Sync',
            'message' => function_exists('audit_format_message')
                ? audit_format_message('Student users synced', $actorLabel)
                : 'Student users synced',
            'actor_label' => $actorLabel,
            'meta' => [
                'source' => 'sybase.v210',
                'category' => 'PELAJAR',
                'group_id' => $groupID,
                'group_code' => $groupKod,
                'inserted' => $inserted,
                'updated' => $updated,
                'skipped' => $skipped,
                'errors' => $errors,
                'total' => $total,
            ],
        ]);

        if (!$eventId || !function_exists('audit_begin_change') || !function_exists('audit_change')) {
            return;
        }

        $changeSetId = audit_begin_change($eventId, 'user_sync', 'student', 'Student sync summary', [
            'source' => 'user-sync-student',
        ]);
        if (!$changeSetId) {
            return;
        }

        foreach ([
            'inserted' => $inserted,
            'updated' => $updated,
            'skipped' => $skipped,
            'errors' => $errors,
            'total' => $total,
        ] as $field => $value) {
            audit_change($changeSetId, $field, null, $value, 'integer', false);
        }
        audit_change($changeSetId, 'group_id', null, $groupID, 'integer', false);
        audit_change($changeSetId, 'group_code', null, $groupKod, 'string', false);
    } catch (Throwable $auditError) {
        error_log('[user-sync-student] Audit logging failed: ' . $auditError->getMessage());
    }
}

try {
    ob_start();
    require_once __DIR__ . '/../includes/init.php';
    $initOutput = ob_get_clean();
    require_once __DIR__ . '/_helpers.php';
    require_once __DIR__ . '/../classes/Database.php';
    require_once __DIR__ . '/../includes/functions-db.php';
    logAjaxUnexpectedOutput('user-sync-student:init.php', $initOutput);

    if (empty($_SESSION['f_stafID'])) {
        jsonErrorResponse((string)(__('unauthorized_access') ?: 'Sila log masuk terlebih dahulu.'), 401);
    }

    $pdo = Database::getInstance('mysql')->getConnection();
    ensureAjaxGroupManagePermission($pdo);

    if (!isValidCsrfToken()) {
        $rawInput = file_get_contents('php://input');
        $data = json_decode($rawInput, true);
        if (!is_array($data) || !isValidCsrfToken((string)($data['csrf_token'] ?? ''))) {
            jsonErrorResponse((string)__('userGroup_csrf_invalid'), 400);
        }
    }

    if (!is_student_mode_enabled()) {
        jsonErrorResponse((string)__('studentSearch_mode_disabled'), 403);
    }

    if (!checkRateLimit('user_sync_student', 5, 60)) {
        jsonErrorResponse((string)__('userList_rate_limit_text'), 429);
    }

    $pdoSybase = Database::pdoSybaseStudent();
    if (!$pdoSybase) {
        jsonErrorResponse((string)__('studentSearch_mode_disabled'), 403);
    }

    $groupStmt = $pdo->prepare("
        SELECT f_groupID, f_groupKod
        FROM tbl_m_group
        WHERE TRIM(COALESCE(f_categoryUser, '')) = 'PELAJAR'
        ORDER BY CASE WHEN UPPER(TRIM(COALESCE(f_groupKod, ''))) = 'APPLICANT' THEN 0 ELSE 1 END,
                 f_groupID ASC
        LIMIT 1
    ");
    $groupStmt->execute();
    $group = $groupStmt->fetch(PDO::FETCH_ASSOC);
    if (!$group) {
        jsonErrorResponse((string)__('userList_sync_student_group_missing'), 400);
    }

    $groupID = (int)($group['f_groupID'] ?? 0);
    $groupKod = (string)($group['f_groupKod'] ?? '');
    $loggedInStafID = $_SESSION['f_stafID'] ?? null;

    $studentSql = "
        SELECT
            matrik,
            nama,
            nokp,
            email,
            hpno,
            telno,
            telno_terkini,
            notel_terkini,
            kdprogram,
            program,
            kdfakulti,
            fakulti,
            kdtahap,
            tahap_pengajian,
            kadet,
            statuskategori
        FROM v210
        WHERE upper(convert(varchar(20), statuskategori)) = 'AKTIF'
    ";
    $stmtSybase = $pdoSybase->prepare($studentSql);
    $stmtSybase->execute();
    $students = $stmtSybase->fetchAll(PDO::FETCH_ASSOC) ?: [];

    if (!$students) {
        jsonSuccessResponse([
            'message' => (string)__('userList_sync_student_no_data'),
            'inserted' => 0,
            'updated' => 0,
            'skipped' => 0,
            'errors' => 0,
            'total' => 0,
        ]);
    }

    $normalize = static fn($value): string => preg_replace('/[^a-zA-Z0-9]/', '', strtoupper(trim((string)$value))) ?? '';
    $existing = [];
    $existingStmt = $pdo->query("
        SELECT f_loginID, f_stafID
        FROM tbl_m_user
        WHERE TRIM(COALESCE(f_categoryUser, '')) = 'PELAJAR'
    ");
    while ($row = $existingStmt->fetch(PDO::FETCH_ASSOC)) {
        foreach (['f_loginID', 'f_stafID'] as $key) {
            $id = $normalize($row[$key] ?? '');
            if ($id !== '') {
                $existing[$id] = true;
            }
        }
    }

    $insertSql = "
        INSERT INTO tbl_m_user (
            f_loginID,
            f_stafID,
            f_categoryUser,
            f_nopekerja,
            f_nama,
            f_nickname,
            f_nokp,
            f_password,
            f_email,
            f_handphone,
            f_jawatanKod,
            f_jawatan,
            f_jenisID,
            f_jenis,
            f_jabatanKod,
            f_namajabatan,
            f_kumpjawatan,
            f_verified_at,
            f_must_change_password,
            f_statusID,
            f_status,
            f_groupID,
            f_groupKod,
            f_flag,
            f_insertdt,
            f_updatedt,
            f_updateby,
            f_remarks
        ) VALUES %s
        ON DUPLICATE KEY UPDATE
            f_nama = VALUES(f_nama),
            f_nickname = VALUES(f_nickname),
            f_nokp = VALUES(f_nokp),
            f_email = VALUES(f_email),
            f_handphone = VALUES(f_handphone),
            f_jawatanKod = VALUES(f_jawatanKod),
            f_jawatan = VALUES(f_jawatan),
            f_jenisID = VALUES(f_jenisID),
            f_jenis = VALUES(f_jenis),
            f_jabatanKod = VALUES(f_jabatanKod),
            f_namajabatan = VALUES(f_namajabatan),
            f_kumpjawatan = VALUES(f_kumpjawatan),
            f_status = VALUES(f_status),
            f_updatedt = NOW(),
            f_updateby = VALUES(f_updateby),
            f_remarks = VALUES(f_remarks)
    ";

    $inserted = 0;
    $updated = 0;
    $skipped = 0;
    $errors = 0;
    $values = [];
    $params = [];
    $columnsPerRow = 28;

    $phoneOf = static function(array $student): ?string {
        foreach (['notel_terkini', 'hpno', 'telno_terkini', 'telno'] as $key) {
            $value = trim((string)($student[$key] ?? ''));
            if ($value !== '') {
                return $value;
            }
        }
        return null;
    };

    foreach ($students as $student) {
        $matrik = trim((string)($student['matrik'] ?? ''));
        if ($matrik === '') {
            $skipped++;
            continue;
        }

        $normalizedMatrik = $normalize($matrik);
        if (isset($existing[$normalizedMatrik])) {
            $updated++;
        } else {
            $inserted++;
            $existing[$normalizedMatrik] = true;
        }

        $nokp = trim((string)($student['nokp'] ?? ''));
        $values[] = '(' . implode(',', array_fill(0, $columnsPerRow, '?')) . ')';
        array_push(
            $params,
            $matrik,
            $matrik,
            'PELAJAR',
            null,
            trim((string)($student['nama'] ?? '')) ?: null,
            trim((string)($student['nama'] ?? '')) ?: null,
            $nokp !== '' ? $nokp : null,
            $nokp !== '' ? password_hash($nokp, PASSWORD_DEFAULT) : '',
            trim((string)($student['email'] ?? '')) ?: null,
            $phoneOf($student),
            trim((string)($student['kdprogram'] ?? '')) ?: null,
            trim((string)($student['program'] ?? '')) ?: null,
            trim((string)($student['kdtahap'] ?? '')) ?: null,
            trim((string)($student['tahap_pengajian'] ?? '')) ?: null,
            trim((string)($student['kdfakulti'] ?? '')) ?: null,
            trim((string)($student['fakulti'] ?? '')) ?: null,
            trim((string)($student['kadet'] ?? '')) ?: null,
            date('Y-m-d H:i:s'),
            1,
            null,
            trim((string)($student['statuskategori'] ?? '')) ?: null,
            $groupID,
            $groupKod,
            1,
            date('Y-m-d H:i:s'),
            date('Y-m-d H:i:s'),
            $loggedInStafID,
            'Sync Pelajar from Sybase (v210)'
        );
    }

    if ($values) {
        $pdo->beginTransaction();
        try {
            $chunkSize = 200;
            for ($i = 0, $count = count($values); $i < $count; $i += $chunkSize) {
                $chunkValues = array_slice($values, $i, $chunkSize);
                $chunkParams = array_slice($params, $i * $columnsPerRow, count($chunkValues) * $columnsPerRow);
                $sql = sprintf($insertSql, implode(',', $chunkValues));
                $stmt = $pdo->prepare($sql);
                $stmt->execute($chunkParams);
            }
            $pdo->commit();
        } catch (Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    if (isset($_SESSION['userlist_cache']) && is_array($_SESSION['userlist_cache'])) {
        foreach (array_keys($_SESSION['userlist_cache']) as $key) {
            if (str_starts_with((string)$key, 'student_options')) {
                unset($_SESSION['userlist_cache'][$key]);
            }
        }
    }

    userSyncStudentAudit($inserted, $updated, $skipped, $errors, count($students), $groupID, $groupKod);

    jsonSuccessResponse([
        'message' => sprintf((string)__('userList_sync_student_result_message'), $inserted, $updated, $skipped, $errors),
        'inserted' => $inserted,
        'updated' => $updated,
        'skipped' => $skipped,
        'errors' => $errors,
        'total' => count($students),
    ]);
} catch (Throwable $e) {
    error_log('[user-sync-student] Error: ' . $e->getMessage() . "\n" . $e->getTraceAsString());
    jsonErrorResponse((string)__('userList_sync_student_error'), 500);
}
