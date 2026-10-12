<?php
/**
 * IQS FRAMEWORK CORE FILE
 *
 * READ ONLY for downstream project programmers.
 * Do not modify this file directly in template or cloned projects.
 * Custom changes must be implemented in project-specific files
 * or approved extension points.
 */declare(strict_types=1);

require_once __DIR__ . '/BaseModel.php';
require_once __DIR__ . '/SystemConfigConstants.php';

final class EmailTemplate extends BaseModel
{
    protected string $table = 'tbl_m_email_template';

    /**
     * @param array<string,mixed> $filters
     * @return array<int,array<string,mixed>>
     */
    public function getAll(array $filters = []): array
    {
        $sql = "
            SELECT
                f_templateID,
                f_templateCode,
                f_templateName,
                f_roleCode,
                f_categoryCode,
                f_subjectTemplate,
                f_bodyHtml,
                f_bodyText,
                f_status,
                f_isDefault,
                f_description,
                f_notes,
                f_insertdt,
                f_updatedt,
                f_updateby
            FROM {$this->table}
            WHERE 1 = 1
        ";

        $params = [];

        $roleCode = trim((string)($filters['role_code'] ?? ''));
        if ($roleCode !== '') {
            $sql .= " AND f_roleCode = :role_code";
            $params[':role_code'] = $roleCode;
        }

        $categoryCode = trim((string)($filters['category_code'] ?? ''));
        if ($categoryCode !== '') {
            $sql .= " AND f_categoryCode = :category_code";
            $params[':category_code'] = $categoryCode;
        }

        $status = strtoupper(trim((string)($filters['status'] ?? '')));
        if ($status !== '') {
            $sql .= " AND f_status = :status";
            $params[':status'] = $status;
        }

        $search = trim((string)($filters['search'] ?? ''));
        if ($search !== '') {
            $sql .= " AND (
                f_templateCode LIKE :search
                OR f_templateName LIKE :search
                OR f_subjectTemplate LIKE :search
            )";
            $params[':search'] = $this->like($search);
        }

        $sql .= " ORDER BY f_isDefault DESC, COALESCE(f_updatedt, f_insertdt) DESC, f_templateID DESC";

        return $this->fetchAll($sql, $params);
    }

    public function findById(int $id): ?array
    {
        if ($id <= 0) {
            return null;
        }

        return $this->fetchOne(
            "SELECT * FROM {$this->table} WHERE f_templateID = :id LIMIT 1",
            [':id' => $id]
        );
    }

    public function findByCode(string $templateCode): ?array
    {
        $templateCode = trim($templateCode);
        if ($templateCode === '') {
            return null;
        }

        return $this->fetchOne(
            "SELECT * FROM {$this->table} WHERE f_templateCode = :template_code LIMIT 1",
            [':template_code' => $templateCode]
        );
    }

    public function findDefault(string $roleCode, string $categoryCode): ?array
    {
        $roleCode = trim($roleCode);
        $categoryCode = trim($categoryCode);
        if ($roleCode === '' || $categoryCode === '') {
            return null;
        }

        return $this->fetchOne(
            "SELECT * FROM {$this->table}
             WHERE f_roleCode = :role_code
               AND f_categoryCode = :category_code
               AND f_isDefault = 1
               AND f_status = 'ACTIVE'
             LIMIT 1",
            [
                ':role_code' => $roleCode,
                ':category_code' => $categoryCode,
            ]
        );
    }

    public function existsByCode(string $templateCode, ?int $excludeId = null): bool
    {
        $templateCode = trim($templateCode);
        if ($templateCode === '') {
            return false;
        }

        $sql = "SELECT 1 FROM {$this->table} WHERE f_templateCode = :template_code";
        $params = [':template_code' => $templateCode];

        if (($excludeId ?? 0) > 0) {
            $sql .= " AND f_templateID <> :exclude_id";
            $params[':exclude_id'] = (int)$excludeId;
        }

        $sql .= " LIMIT 1";

        return (bool)$this->fetchColumn($sql, $params);
    }

    /**
     * @param array<string,mixed> $data
     */
    public function create(array $data): int
    {
        $payload = $this->normalizePayload($data);

        $sql = "
            INSERT INTO {$this->table} (
                f_templateCode,
                f_templateName,
                f_roleCode,
                f_categoryCode,
                f_subjectTemplate,
                f_bodyHtml,
                f_bodyText,
                f_status,
                f_isDefault,
                f_description,
                f_notes,
                f_insertdt,
                f_updatedt,
                f_updateby
            ) VALUES (
                :template_code,
                :template_name,
                :role_code,
                :category_code,
                :subject_template,
                :body_html,
                :body_text,
                :status,
                :is_default,
                :description,
                :notes,
                NOW(),
                NOW(),
                :update_by
            )
        ";

        $this->runQuery($sql, $payload);
        $templateId = (int)$this->lastInsertId();

        if ($templateId > 0 && (int)$payload[':is_default'] === 1) {
            $this->setDefault(
                $templateId,
                (string)$payload[':role_code'],
                (string)$payload[':category_code'],
                (string)$payload[':update_by']
            );
        }

        return $templateId;
    }

    /**
     * @param array<string,mixed> $data
     */
    public function update(int $id, array $data): bool
    {
        if ($id <= 0) {
            return false;
        }

        $payload = $this->normalizePayload($data);
        $payload[':id'] = $id;

        $sql = "
            UPDATE {$this->table}
            SET
                f_templateCode = :template_code,
                f_templateName = :template_name,
                f_roleCode = :role_code,
                f_categoryCode = :category_code,
                f_subjectTemplate = :subject_template,
                f_bodyHtml = :body_html,
                f_bodyText = :body_text,
                f_status = :status,
                f_isDefault = :is_default,
                f_description = :description,
                f_notes = :notes,
                f_updatedt = NOW(),
                f_updateby = :update_by
            WHERE f_templateID = :id
        ";

        $affected = $this->execute($sql, $payload);

        if ((int)$payload[':is_default'] === 1) {
            $this->setDefault(
                $id,
                (string)$payload[':role_code'],
                (string)$payload[':category_code'],
                (string)$payload[':update_by']
            );
        }

        return $affected >= 0;
    }

    public function archive(int $id, string $updateBy): bool
    {
        if ($id <= 0) {
            return false;
        }

        $record = $this->findById($id);
        if (!$record) {
            return false;
        }

        if ((int)($record['f_isDefault'] ?? 0) === 1) {
            throw new RuntimeException('Default email template cannot be archived until another template is set as default.');
        }

        return $this->execute(
            "UPDATE {$this->table}
             SET f_status = 'ARCHIVED',
                 f_isDefault = 0,
                 f_updatedt = NOW(),
                 f_updateby = :update_by
             WHERE f_templateID = :id",
            [
                ':id' => $id,
                ':update_by' => trim($updateBy),
            ]
        ) >= 0;
    }

    public function delete(int $id): bool
    {
        if ($id <= 0) {
            return false;
        }

        $record = $this->findById($id);
        if (!$record) {
            return false;
        }

        if ((int)($record['f_isDefault'] ?? 0) === 1) {
            throw new RuntimeException('Default email template cannot be deleted until another template is set as default.');
        }

        $usageCounts = $this->getUsageCounts([$id]);
        if ((int)($usageCounts[$id] ?? 0) > 0) {
            throw new RuntimeException('Email template cannot be deleted because it has been used.');
        }

        return $this->execute(
            "DELETE FROM {$this->table} WHERE f_templateID = :id LIMIT 1",
            [':id' => $id]
        ) > 0;
    }

    public function duplicate(int $id, string $updateBy): int
    {
        $record = $this->findById($id);
        if (!$record) {
            return 0;
        }

        $baseCode = strtoupper(trim((string)($record['f_templateCode'] ?? '')));
        $copyCode = '';
        for ($attempt = 0; $attempt < 10; $attempt++) {
            $suffix = '_COPY_' . date('YmdHis') . '_' . strtoupper(substr(bin2hex(random_bytes(2)), 0, 4));
            $candidate = $baseCode . $suffix;
            if (strlen($candidate) > 100) {
                $candidate = substr($candidate, 0, 100);
            }

            if (!$this->existsByCode($candidate)) {
                $copyCode = $candidate;
                break;
            }
        }

        if ($copyCode === '') {
            throw new RuntimeException('Unable to generate a unique duplicate template code.');
        }

        return $this->create([
            'template_code' => $copyCode,
            'template_name' => trim((string)($record['f_templateName'] ?? '')) . ' (Copy)',
            'role_code' => (string)($record['f_roleCode'] ?? ''),
            'category_code' => (string)($record['f_categoryCode'] ?? ''),
            'subject_template' => (string)($record['f_subjectTemplate'] ?? ''),
            'body_html' => (string)($record['f_bodyHtml'] ?? ''),
            'body_text' => (string)($record['f_bodyText'] ?? ''),
            'status' => 'DRAFT',
            'is_default' => 0,
            'description' => (string)($record['f_description'] ?? ''),
            'notes' => (string)($record['f_notes'] ?? ''),
            'update_by' => $updateBy,
        ]);
    }

    /**
     * @param array<int,int> $templateIds
     * @return array<int,int>
     */
    public function getUsageCounts(array $templateIds): array
    {
        $templateIds = array_values(array_filter(array_map('intval', $templateIds), static fn (int $value): bool => $value > 0));
        if ($templateIds === []) {
            return [];
        }

        try {
            [$inClause, $bind] = $this->inClause('template_id', $templateIds);
            $sql = "
                SELECT target_id, COUNT(*) AS total_usage
                FROM audit_event
                WHERE target_type = 'email_template'
                  AND target_id IN ($inClause)
                  AND (
                    message LIKE :send_message
                    OR JSON_UNQUOTE(JSON_EXTRACT(meta, '$.usage_action')) = 'send'
                  )
                GROUP BY target_id
            ";
            $bind[':send_message'] = '%sent%';

            $rows = $this->fetchAll($sql, $bind);
            $usageMap = [];
            foreach ($rows as $row) {
                $usageMap[(int)($row['target_id'] ?? 0)] = (int)($row['total_usage'] ?? 0);
            }

            return $usageMap;
        } catch (Throwable $e) {
            return [];
        }
    }

    public function setDefault(int $id, string $roleCode, string $categoryCode, string $updateBy): bool
    {
        if ($id <= 0 || trim($roleCode) === '' || trim($categoryCode) === '') {
            return false;
        }

        return (bool)$this->transaction(function () use ($id, $roleCode, $categoryCode, $updateBy) {
            $this->execute(
                "UPDATE {$this->table}
                 SET f_isDefault = 0,
                     f_updatedt = NOW(),
                     f_updateby = :update_by
                 WHERE f_roleCode = :role_code
                   AND f_categoryCode = :category_code",
                [
                    ':role_code' => trim($roleCode),
                    ':category_code' => trim($categoryCode),
                    ':update_by' => trim($updateBy),
                ]
            );

            $this->execute(
                "UPDATE {$this->table}
                 SET f_isDefault = 1,
                     f_updatedt = NOW(),
                     f_updateby = :update_by
                 WHERE f_templateID = :id",
                [
                    ':id' => $id,
                    ':update_by' => trim($updateBy),
                ]
            );

            return true;
        });
    }

    /**
     * @param array<string,mixed> $data
     * @return array<string,mixed>
     */
    private function normalizePayload(array $data): array
    {
        $status = strtoupper(trim((string)($data['status'] ?? 'DRAFT')));
        if (!in_array($status, SystemConfigConstants::ALLOWED_EMAIL_TEMPLATE_STATUSES, true)) {
            $status = 'DRAFT';
        }

        $roleCode = strtolower(trim((string)($data['role_code'] ?? '')));
        $categoryCode = strtolower(trim((string)($data['category_code'] ?? '')));

        return [
            ':template_code' => trim((string)($data['template_code'] ?? '')),
            ':template_name' => trim((string)($data['template_name'] ?? '')),
            ':role_code' => $roleCode,
            ':category_code' => $categoryCode,
            ':subject_template' => trim((string)($data['subject_template'] ?? '')),
            ':body_html' => (string)($data['body_html'] ?? ''),
            ':body_text' => (string)($data['body_text'] ?? ''),
            ':status' => $status,
            ':is_default' => !empty($data['is_default']) ? 1 : 0,
            ':description' => trim((string)($data['description'] ?? '')),
            ':notes' => trim((string)($data['notes'] ?? '')),
            ':update_by' => trim((string)($data['update_by'] ?? '')),
        ];
    }
}
