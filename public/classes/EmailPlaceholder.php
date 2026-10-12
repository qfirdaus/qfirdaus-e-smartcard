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

final class EmailPlaceholder extends BaseModel
{
    protected string $table = 'tbl_m_email_placeholder';

    /**
     * @return array<int,array<string,mixed>>
     */
    public function getAllActive(): array
    {
        return $this->fetchAll(
            "SELECT
                f_placeholderID,
                f_placeholderKey,
                f_placeholderLabel,
                f_placeholderGroup,
                f_description,
                f_sampleValue,
                f_sourceType,
                f_isGeneral,
                f_isActive,
                f_insertdt,
                f_updatedt
             FROM {$this->table}
             WHERE f_isActive = 1
             ORDER BY f_placeholderGroup ASC, f_placeholderLabel ASC"
        );
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    public function getActiveGeneral(): array
    {
        return $this->fetchAll(
            "SELECT
                f_placeholderID,
                f_placeholderKey,
                f_placeholderLabel,
                f_placeholderGroup,
                f_description,
                f_sampleValue,
                f_sourceType,
                f_isGeneral,
                f_isActive
             FROM {$this->table}
             WHERE f_isActive = 1
               AND f_isGeneral = 1
             ORDER BY f_placeholderGroup ASC, f_placeholderLabel ASC"
        );
    }

    /**
     * @param array<int,string> $keys
     * @return array<int,array<string,mixed>>
     */
    public function findByKeys(array $keys): array
    {
        $normalized = array_values(array_filter(array_map(
            static fn($value): string => trim((string)$value),
            $keys
        )));

        if ($normalized === []) {
            return [];
        }

        [$inClause, $bind] = $this->inClause('placeholder_key', $normalized);

        return $this->fetchAll(
            "SELECT
                f_placeholderID,
                f_placeholderKey,
                f_placeholderLabel,
                f_placeholderGroup,
                f_description,
                f_sampleValue,
                f_sourceType,
                f_isGeneral,
                f_isActive
             FROM {$this->table}
             WHERE f_placeholderKey IN ({$inClause})",
            $bind
        );
    }

    public function existsByKey(string $key, ?int $excludeId = null): bool
    {
        $key = trim($key);
        if ($key === '') {
            return false;
        }

        $sql = "SELECT 1 FROM {$this->table} WHERE f_placeholderKey = :placeholder_key";
        $params = [':placeholder_key' => $key];

        if (($excludeId ?? 0) > 0) {
            $sql .= " AND f_placeholderID <> :exclude_id";
            $params[':exclude_id'] = (int)$excludeId;
        }

        $sql .= " LIMIT 1";

        return (bool)$this->fetchColumn($sql, $params);
    }

    /**
     * @param array<int,array<string,mixed>> $definitions
     */
    public function syncFromDefinitions(array $definitions): array
    {
        $summary = [
            'inserted' => 0,
            'updated' => 0,
            'skipped' => 0,
        ];

        foreach ($definitions as $definition) {
            if (!is_array($definition)) {
                $summary['skipped']++;
                continue;
            }

            $key = trim((string)($definition['key'] ?? ''));
            if ($key === '') {
                $summary['skipped']++;
                continue;
            }

            $existing = $this->fetchOne(
                "SELECT f_placeholderID FROM {$this->table} WHERE f_placeholderKey = :placeholder_key LIMIT 1",
                [':placeholder_key' => $key]
            );

            $payload = [
                'placeholder_key' => $key,
                'placeholder_label' => (string)($definition['label'] ?? $key),
                'placeholder_group' => (string)($definition['group'] ?? 'system'),
                'description' => (string)($definition['description'] ?? ''),
                'sample_value' => (string)($definition['sample_value'] ?? ''),
                'source_type' => (string)($definition['source_type'] ?? 'general'),
                'is_general' => (int)($definition['is_general'] ?? 1) === 1,
                'is_active' => (int)($definition['is_active'] ?? 1) === 1,
            ];

            if (is_array($existing) && !empty($existing['f_placeholderID'])) {
                $this->update((int)$existing['f_placeholderID'], $payload);
                $summary['updated']++;
                continue;
            }

            $this->create($payload);
            $summary['inserted']++;
        }

        return $summary;
    }

    /**
     * @param array<string,mixed> $data
     */
    public function create(array $data): int
    {
        $payload = $this->normalizePayload($data);

        $this->runQuery(
            "INSERT INTO {$this->table} (
                f_placeholderKey,
                f_placeholderLabel,
                f_placeholderGroup,
                f_description,
                f_sampleValue,
                f_sourceType,
                f_isGeneral,
                f_isActive,
                f_insertdt,
                f_updatedt
            ) VALUES (
                :placeholder_key,
                :placeholder_label,
                :placeholder_group,
                :description,
                :sample_value,
                :source_type,
                :is_general,
                :is_active,
                NOW(),
                NOW()
            )",
            $payload
        );

        return (int)$this->lastInsertId();
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

        return $this->execute(
            "UPDATE {$this->table}
             SET f_placeholderKey = :placeholder_key,
                 f_placeholderLabel = :placeholder_label,
                 f_placeholderGroup = :placeholder_group,
                 f_description = :description,
                 f_sampleValue = :sample_value,
                 f_sourceType = :source_type,
                 f_isGeneral = :is_general,
                 f_isActive = :is_active,
                 f_updatedt = NOW()
             WHERE f_placeholderID = :id",
            $payload
        ) >= 0;
    }

    /**
     * @param array<string,mixed> $data
     * @return array<string,mixed>
     */
    private function normalizePayload(array $data): array
    {
        return [
            ':placeholder_key' => trim((string)($data['placeholder_key'] ?? '')),
            ':placeholder_label' => trim((string)($data['placeholder_label'] ?? '')),
            ':placeholder_group' => trim((string)($data['placeholder_group'] ?? '')),
            ':description' => trim((string)($data['description'] ?? '')),
            ':sample_value' => trim((string)($data['sample_value'] ?? '')),
            ':source_type' => trim((string)($data['source_type'] ?? 'general')),
            ':is_general' => !empty($data['is_general']) ? 1 : 0,
            ':is_active' => !empty($data['is_active']) ? 1 : 0,
        ];
    }
}
