<?php
/**
 * IQS FRAMEWORK CORE FILE
 *
 * READ ONLY for downstream project programmers.
 * Do not modify this file directly in template or cloned projects.
 * Custom changes must be implemented in project-specific files
 * or approved extension points.
 */
// app/controllers/ManualController.php
declare(strict_types=1);

require_once __DIR__ . '/../classes/Database.php';
require_once __DIR__ . '/../setting/helper/config_helper.php';

class ManualController
{
    private PDO $db;
    private bool $manualTableReady = false;
    private int $manualMaxBytes;

    public function __construct()
    {
        $this->db = Database::getInstance()->getConnection();
        $manualMaxMb = (int)app_config('upload.manual_max_mb', 10);
        if ($manualMaxMb <= 0) {
            $manualMaxMb = 10;
        }
        $this->manualMaxBytes = $manualMaxMb * 1024 * 1024;
        $this->ensureManualTable();
    }

    private function ensureManualTable(): void
    {
        if ($this->manualTableReady) {
            return;
        }

        try {
            $stmt = $this->db->query("SHOW TABLES LIKE 'tbl_m_usermanual'");
            if (!$stmt->fetchColumn()) {
                throw new RuntimeException((string)__('manual_table_unavailable'));
            }
            $this->manualTableReady = true;
        } catch (Throwable $e) {
            error_log('[ManualController] ensureManualTable failed: ' . $e->getMessage());
            throw $e;
        }
    }

    private function groupExists(int $groupId): bool
    {
        $stmt = $this->db->prepare("SELECT 1 FROM tbl_m_group WHERE f_groupID = :gid LIMIT 1");
        $stmt->execute([':gid' => $groupId]);
        return (bool)$stmt->fetchColumn();
    }

    public function resolveManualFilePath(string $relativePath): ?string
    {
        $relativePath = str_replace('\\', '/', trim($relativePath));
        if ($relativePath === '' || str_contains($relativePath, '..')) {
            return null;
        }

        $candidates = [];
        if (str_starts_with($relativePath, 'storage/manuals/')) {
            $candidates[] = dirname(__DIR__, 2) . '/' . $relativePath;
        } elseif (str_starts_with($relativePath, 'uploads/manuals/')) {
            $candidates[] = __DIR__ . '/../' . $relativePath;
        } else {
            return null;
        }

        foreach ($candidates as $candidate) {
            $resolved = realpath($candidate);
            $base = str_starts_with($relativePath, 'storage/')
                ? realpath(dirname(__DIR__, 2) . '/storage/manuals')
                : realpath(__DIR__ . '/../uploads/manuals');
            if ($resolved !== false && $base !== false
                && str_starts_with($resolved, rtrim($base, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR)
                && is_file($resolved)) {
                return $resolved;
            }
        }
        return null;
    }

    public function manualFileExists(string $relativePath): bool
    {
        return $this->resolveManualFilePath($relativePath) !== null;
    }

    private function logManualAudit(string $eventType, string $action, int $groupId, string $outcome = 'SUCCESS', array $meta = [], array $changes = []): void
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
                'severity' => str_contains($eventType, 'DELETE') || $outcome === 'FAILURE' ? 'WARN' : 'INFO',
                'outcome' => $outcome,
                'target_type' => 'user_manual',
                'target_id' => (string)$groupId,
                'target_label' => 'Manual Group ' . $groupId,
                'message' => function_exists('audit_format_message')
                    ? audit_format_message($action, $actorLabel)
                    : $action,
                'actor_label' => $actorLabel,
                'meta' => array_merge([
                    'group_id' => $groupId,
                ], $meta),
            ]);

            if (!$eventId || !function_exists('audit_begin_change') || !function_exists('audit_change')) {
                return;
            }

            $changeSetId = audit_begin_change($eventId, 'user_manual', (string)$groupId, $action, [
                'source' => 'ManualController',
            ]);
            if (!$changeSetId) {
                return;
            }

            foreach ($changes as $field => $change) {
                if (!is_array($change)) {
                    continue;
                }
                $oldValue = $change['old'] ?? null;
                $newValue = $change['new'] ?? null;
                if ((string)$oldValue === (string)$newValue) {
                    continue;
                }
                audit_change(
                    $changeSetId,
                    (string)$field,
                    $oldValue,
                    $newValue,
                    (string)($change['type'] ?? 'string'),
                    (bool)($change['sensitive'] ?? false)
                );
            }
        } catch (Throwable $auditError) {
            error_log('[ManualController] Audit logging failed: ' . $auditError->getMessage());
        }
    }

    /**
     * Get manual for a specific group ID
     */
    public function getManualByGroupId(int $groupId): ?array
    {
        $stmt = $this->db->prepare("SELECT * FROM tbl_m_usermanual WHERE f_groupID = :gid LIMIT 1");
        $stmt->execute([':gid' => $groupId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ?: null;
    }

    /**
     * Get all manuals with their associated group names
     */
    public function getAllManuals(): array
    {
        $sql = "SELECT g.f_groupID, g.f_groupName, m.f_file_path, m.f_updated_at 
                FROM tbl_m_group g
                LEFT JOIN tbl_m_usermanual m ON g.f_groupID = m.f_groupID
                ORDER BY g.f_groupID ASC";
        $stmt = $this->db->query($sql);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getManualListItem(int $groupId): ?array
    {
        $stmt = $this->db->prepare(
            "SELECT g.f_groupID, g.f_groupName, m.f_file_path, m.f_updated_at
             FROM tbl_m_group g
             LEFT JOIN tbl_m_usermanual m ON g.f_groupID = m.f_groupID
             WHERE g.f_groupID = :gid
             LIMIT 1"
        );
        $stmt->execute([':gid' => $groupId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    /**
     * Ensure every group exists in manual table.
     */
    public function syncManualGroups(): array
    {
        $userId = (string)($_SESSION['user_id'] ?? ($_SESSION['username'] ?? 'admin'));

        try {
            $groups = $this->db->query("SELECT f_groupID FROM tbl_m_group ORDER BY f_groupID ASC")->fetchAll(PDO::FETCH_COLUMN) ?: [];
            if (!$groups) {
                return ['success' => true, 'message' => (string)__('manual_sync_no_groups')];
            }

            $existingStmt = $this->db->query("SELECT f_groupID FROM tbl_m_usermanual");
            $existing = array_map('intval', $existingStmt->fetchAll(PDO::FETCH_COLUMN) ?: []);
            $existingMap = array_fill_keys($existing, true);

            $inserted = 0;
            $updated = 0;

            $this->db->beginTransaction();
            $stmt = $this->db->prepare(
                "INSERT INTO tbl_m_usermanual (f_groupID, f_file_path, f_updated_by)
                 VALUES (:gid, '', :user)
                 ON DUPLICATE KEY UPDATE
                    f_groupID = VALUES(f_groupID),
                    f_updated_by = VALUES(f_updated_by)"
            );

            foreach ($groups as $gid) {
                $gid = (int)$gid;
                if ($gid <= 0) {
                    continue;
                }
                $stmt->execute([
                    ':gid' => $gid,
                    ':user' => $userId
                ]);

                if (isset($existingMap[$gid])) {
                    $updated++;
                } else {
                    $inserted++;
                }
            }

            $this->db->commit();

            $this->logManualAudit('UPDATE', 'Manual groups synced', 0, 'SUCCESS', [
                'inserted' => $inserted,
                'updated' => $updated,
                'total_groups' => count($groups),
            ], [
                'inserted' => ['old' => null, 'new' => $inserted, 'type' => 'integer'],
                'updated' => ['old' => null, 'new' => $updated, 'type' => 'integer'],
                'total_groups' => ['old' => null, 'new' => count($groups), 'type' => 'integer'],
            ]);

            return [
                'success' => true,
                'message' => sprintf((string)__('manual_sync_result'), $inserted, $updated)
            ];
        } catch (Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            error_log('[ManualController] syncManualGroups failure: ' . $e->getMessage());
            return ['success' => false, 'message' => (string)__('manual_sync_failed')];
        }
    }

    /**
     * Upload a new manual for a specific role
     */
    public function uploadManual(int $groupId, array $file): array
    {
        if ($groupId <= 0 || !$this->groupExists($groupId)) {
            return ['success' => false, 'message' => (string)__('manual_group_invalid')];
        }

        if (!isset($file['error'], $file['name'], $file['size'], $file['tmp_name'])) {
            return ['success' => false, 'message' => (string)__('manual_file_incomplete')];
        }

        if ($file['error'] !== UPLOAD_ERR_OK) {
            return ['success' => false, 'message' => (string)__('manual_upload_error')];
        }

        if (!is_uploaded_file((string)$file['tmp_name'])) {
            return ['success' => false, 'message' => (string)__('manual_upload_invalid')];
        }

        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if ($ext !== 'pdf') {
            return ['success' => false, 'message' => (string)__('manual_upload_pdf_only')];
        }

        $mime = null;
        if (class_exists('finfo')) {
            $finfo = new finfo(FILEINFO_MIME_TYPE);
            $mime = $finfo->file((string)$file['tmp_name']) ?: null;
        }
        $allowedMime = ['application/pdf', 'application/x-pdf'];
        if ($mime !== null && !in_array(strtolower((string)$mime), $allowedMime, true)) {
            return ['success' => false, 'message' => (string)__('manual_upload_invalid_pdf')];
        }

        $fh = @fopen((string)$file['tmp_name'], 'rb');
        $signature = $fh ? (string)fread($fh, 4) : '';
        if (is_resource($fh)) {
            fclose($fh);
        }
        if ($signature !== '%PDF') {
            return ['success' => false, 'message' => (string)__('manual_upload_invalid_pdf')];
        }

        if ((int)$file['size'] <= 0) {
            return ['success' => false, 'message' => (string)__('manual_upload_invalid_pdf')];
        }

        if ($file['size'] > $this->manualMaxBytes) {
            return ['success' => false, 'message' => sprintf((string)__('manual_upload_max_size'), (int)app_config('upload.manual_max_mb', 10))];
        }

        // Store new manuals outside the public web root. Legacy public paths remain readable
        // only through the guarded manual-view endpoint.
        $uploadDir = dirname(__DIR__, 2) . '/storage/manuals/';
        if (!is_dir($uploadDir)) {
            if (!mkdir($uploadDir, 0750, true) && !is_dir($uploadDir)) {
                return ['success' => false, 'message' => (string)__('manual_upload_store_failed')];
            }
        }

        // Generate safe filename
        $newFilename = 'manual_role_' . $groupId . '_' . bin2hex(random_bytes(12)) . '.pdf';
        $destPath = $uploadDir . $newFilename;

        if (move_uploaded_file($file['tmp_name'], $destPath)) {
            $relativePath = 'storage/manuals/' . $newFilename;

            $oldManual = $this->getManualByGroupId($groupId);
            $oldPath = null;
            if ($oldManual && !empty($oldManual['f_file_path'])) {
                $oldPath = $this->resolveManualFilePath((string)$oldManual['f_file_path']);
            }

            $stmt = $this->db->prepare("SELECT f_id FROM tbl_m_usermanual WHERE f_groupID = :gid");
            $stmt->execute([':gid' => $groupId]);
            $exists = $stmt->fetch();

            $userId = (string)($_SESSION['user_id'] ?? ($_SESSION['username'] ?? 'admin'));

            try {
                $this->db->beginTransaction();

                if ($exists) {
                    $sql = "UPDATE tbl_m_usermanual SET f_file_path = :path, f_updated_by = :user WHERE f_groupID = :gid";
                } else {
                    $sql = "INSERT INTO tbl_m_usermanual (f_groupID, f_file_path, f_updated_by) VALUES (:gid, :path, :user)";
                }

                $stmt = $this->db->prepare($sql);
                $stmt->execute([
                    ':gid' => $groupId,
                    ':path' => $relativePath,
                    ':user' => $userId
                ]);

                $this->db->commit();
            } catch (Throwable $e) {
                if ($this->db->inTransaction()) {
                    $this->db->rollBack();
                }
                if (file_exists($destPath)) {
                    if (!unlink($destPath)) {
                        error_log('[ManualController] Unable to clean up failed upload: ' . basename($destPath));
                    }
                }
                error_log('[ManualController] uploadManual DB failure: ' . $e->getMessage());
                return ['success' => false, 'message' => (string)__('manual_record_update_failed')];
            }

            if ($oldPath && $oldPath !== $destPath && file_exists($oldPath)) {
                if (!unlink($oldPath)) {
                    error_log('[ManualController] Unable to remove replaced manual: ' . basename($oldPath));
                }
            }

            $this->logManualAudit($exists ? 'MANUAL_REPLACE' : 'MANUAL_UPLOAD', 'Manual uploaded', $groupId, 'SUCCESS', [
                'file_path' => $relativePath,
                'old_file_path' => (string)($oldManual['f_file_path'] ?? ''),
                'file_name' => $newFilename,
                'file_size' => (int)($file['size'] ?? 0),
            ], [
                'f_file_path' => ['old' => (string)($oldManual['f_file_path'] ?? ''), 'new' => $relativePath],
                'file_name' => ['old' => null, 'new' => $newFilename],
                'file_size' => ['old' => null, 'new' => (int)($file['size'] ?? 0), 'type' => 'integer'],
            ]);

            return ['success' => true, 'message' => (string)__('manual_upload_success')];
        }

        return ['success' => false, 'message' => (string)__('manual_upload_store_failed')];
    }

    /**
     * Delete a manual
     */
    public function deleteManual(int $groupId): array
    {
        if ($groupId <= 0 || !$this->groupExists($groupId)) {
            return ['success' => false, 'message' => (string)__('manual_group_invalid')];
        }

        $oldManual = $this->getManualByGroupId($groupId);
        if ($oldManual) {
            $oldPath = !empty($oldManual['f_file_path'])
                ? $this->resolveManualFilePath((string)$oldManual['f_file_path'])
                : null;

            try {
                $this->db->beginTransaction();
                $stmt = $this->db->prepare("DELETE FROM tbl_m_usermanual WHERE f_groupID = :gid");
                $stmt->execute([':gid' => $groupId]);
                $this->db->commit();
            } catch (Throwable $e) {
                if ($this->db->inTransaction()) {
                    $this->db->rollBack();
                }
                error_log('[ManualController] deleteManual DB failure: ' . $e->getMessage());
                return ['success' => false, 'message' => (string)__('manual_delete_record_failed')];
            }

            if ($oldPath && file_exists($oldPath)) {
                if (!unlink($oldPath)) {
                    error_log('[ManualController] Unable to remove deleted manual: ' . basename($oldPath));
                    $this->logManualAudit('MANUAL_FILE_CLEANUP_FAILED', 'Manual file cleanup failed', $groupId, 'FAILURE', [
                        'file_name' => basename($oldPath),
                    ]);
                }
            }

            $this->logManualAudit('MANUAL_DELETE', 'Manual deleted', $groupId, 'SUCCESS', [
                'file_path' => (string)($oldManual['f_file_path'] ?? ''),
            ], [
                'f_file_path' => ['old' => (string)($oldManual['f_file_path'] ?? ''), 'new' => null],
            ]);

            return ['success' => true, 'message' => (string)__('manual_delete_success')];
        }
        
        return ['success' => false, 'message' => (string)__('manual_not_found')];
    }
}
