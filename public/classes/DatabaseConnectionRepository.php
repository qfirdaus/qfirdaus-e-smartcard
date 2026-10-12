<?php
/**
 * IQS FRAMEWORK CORE FILE
 *
 * READ ONLY for downstream project programmers.
 * Do not modify this file directly in template or cloned projects.
 * Custom changes must be implemented in project-specific files
 * or approved extension points.
 */declare(strict_types=1);

require_once __DIR__ . '/../setting/helper/security_helper.php';
require_once __DIR__ . '/DatabaseCredentialCipher.php';

final class DatabaseConnectionRepository
{
    private bool $tableChecked = false;

    public function __construct(
        private readonly PDO $pdo,
        private readonly ?DatabaseCredentialCipher $credentialCipher = null,
    )
    {
    }

    public function findAllAdditional(bool $includeSecrets = false): array
    {
        $this->assertTablesReady();

        $stmt = $this->pdo->prepare("
            SELECT *
            FROM tbl_m_db_connection
            WHERE f_category = 'additional'
            ORDER BY f_family ASC, f_code ASC
        ");
        $stmt->execute();
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        if ($rows === []) {
            return [];
        }

        $envMap = $this->findEnvRowsByCodes(array_column($rows, 'f_code'), $includeSecrets);
        foreach ($rows as &$row) {
            $code = (string)($row['f_code'] ?? '');
            $row['env_rows'] = $envMap[$code] ?? [];
            $row['f_registry_revision'] = $this->buildRegistryRevision($row);
        }

        return $rows;
    }

    public function findAdditionalByCode(string $code, bool $includeSecrets = false): ?array
    {
        $this->assertTablesReady();

        $stmt = $this->pdo->prepare("
            SELECT *
            FROM tbl_m_db_connection
            WHERE f_code = :code
              AND f_category = 'additional'
            LIMIT 1
        ");
        $stmt->execute([':code' => trim($code)]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            return null;
        }

        $row['env_rows'] = $this->findEnvRowsByCodes([(string)$row['f_code']], $includeSecrets)[(string)$row['f_code']] ?? [];
        $row['f_registry_revision'] = $this->buildRegistryRevision($row);
        return $row;
    }

    public function createAdditional(array $payload, array $envRows): string
    {
        $this->assertTablesReady();

        $code = trim((string)($payload['f_code'] ?? ''));
        $this->pdo->beginTransaction();
        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO tbl_m_db_connection (
                    f_code, f_name, f_family, f_category, f_purpose, f_driver_mode,
                    f_is_enabled, f_supports_prod, f_supports_dev, f_notes,
                    f_created_by, f_updated_by
                ) VALUES (
                    :code, :name, :family, 'additional', :purpose, :driver_mode,
                    :is_enabled, :supports_prod, :supports_dev, :notes,
                    :created_by, :updated_by
                )
            ");
            $stmt->execute([
                ':code' => $code,
                ':name' => trim((string)($payload['f_name'] ?? $code)),
                ':family' => trim((string)($payload['f_family'] ?? '')),
                ':purpose' => trim((string)($payload['f_purpose'] ?? 'reference')),
                ':driver_mode' => trim((string)($payload['f_driver_mode'] ?? 'auto')),
                ':is_enabled' => !empty($payload['f_is_enabled']) ? 1 : 0,
                ':supports_prod' => !empty($payload['f_supports_prod']) ? 1 : 0,
                ':supports_dev' => !empty($payload['f_supports_dev']) ? 1 : 0,
                ':notes' => $this->normalizeNullableString($payload['f_notes'] ?? null),
                ':created_by' => $this->normalizeNullableString($payload['f_created_by'] ?? null),
                ':updated_by' => $this->normalizeNullableString($payload['f_updated_by'] ?? null),
            ]);

            $this->replaceEnvRows($code, $envRows);
            $this->pdo->commit();
            return $code;
        } catch (Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    public function updateAdditional(string $code, array $payload, array $envRows, ?string $expectedRevision = null): bool
    {
        $this->assertTablesReady();

        $code = trim($code);
        $this->pdo->beginTransaction();
        try {
            $lockMaster = $this->pdo->prepare("SELECT f_code FROM tbl_m_db_connection WHERE f_code = :code AND f_category = 'additional' FOR UPDATE");
            $lockMaster->execute([':code' => $code]);
            if ($lockMaster->fetchColumn() === false) {
                throw new RuntimeException('Sambungan tambahan tidak ditemui.');
            }
            $lockRows = $this->pdo->prepare('SELECT f_connection_code FROM tbl_m_db_connection_env WHERE f_connection_code = :code FOR UPDATE');
            $lockRows->execute([':code' => $code]);

            if (is_string($expectedRevision) && trim($expectedRevision) !== '') {
                $current = $this->findAdditionalByCode($code, false);
                $currentRevision = is_array($current) ? (string)($current['f_registry_revision'] ?? '') : '';
                if (!hash_equals($currentRevision, trim($expectedRevision))) {
                    throw new RuntimeException('Konfigurasi sambungan telah berubah sejak borang dibuka. Muat semula sebelum menyimpan supaya variant platform lain tidak ditimpa.');
                }
            }

            $stmt = $this->pdo->prepare("
                UPDATE tbl_m_db_connection
                SET f_name = :name,
                    f_family = :family,
                    f_purpose = :purpose,
                    f_driver_mode = :driver_mode,
                    f_is_enabled = :is_enabled,
                    f_supports_prod = :supports_prod,
                    f_supports_dev = :supports_dev,
                    f_notes = :notes,
                    f_updated_by = :updated_by
                WHERE f_code = :code
                  AND f_category = 'additional'
            ");
            $stmt->execute([
                ':code' => $code,
                ':name' => trim((string)($payload['f_name'] ?? $code)),
                ':family' => trim((string)($payload['f_family'] ?? '')),
                ':purpose' => trim((string)($payload['f_purpose'] ?? 'reference')),
                ':driver_mode' => trim((string)($payload['f_driver_mode'] ?? 'auto')),
                ':is_enabled' => !empty($payload['f_is_enabled']) ? 1 : 0,
                ':supports_prod' => !empty($payload['f_supports_prod']) ? 1 : 0,
                ':supports_dev' => !empty($payload['f_supports_dev']) ? 1 : 0,
                ':notes' => $this->normalizeNullableString($payload['f_notes'] ?? null),
                ':updated_by' => $this->normalizeNullableString($payload['f_updated_by'] ?? null),
            ]);

            $this->replaceEnvRows($code, $envRows);
            $this->pdo->commit();
            return true;
        } catch (Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    public function setEnabled(string $code, bool $enabled): bool
    {
        $this->assertTablesReady();

        $stmt = $this->pdo->prepare("
            UPDATE tbl_m_db_connection
            SET f_is_enabled = :enabled
            WHERE f_code = :code
              AND f_category = 'additional'
        ");
        return $stmt->execute([
            ':code' => trim($code),
            ':enabled' => $enabled ? 1 : 0,
        ]) !== false;
    }

    public function saveTestResult(string $code, string $environment, string $osFamily, string $status, string $message, ?string $driver = null): void
    {
        $this->assertTablesReady();

        $update = $this->pdo->prepare("
            UPDATE tbl_m_db_connection_env
            SET f_last_test_status = :status,
                f_last_test_message = :message,
                f_last_tested_at = NOW()
            WHERE f_connection_code = :code
              AND f_environment = :environment
              AND f_os_family = :os_family
              AND f_driver = :driver
        ");

        try {
            $update->execute([
                ':status' => strtoupper(trim($status)),
                ':message' => $message,
                ':code' => trim($code),
                ':environment' => trim($environment),
                ':os_family' => trim($osFamily),
                ':driver' => trim((string)($driver ?? 'auto')),
            ]);
        } catch (Throwable $e) {
            // ignore update errors and still attempt audit test insert
        }

        try {
            $insert = $this->pdo->prepare("
                INSERT INTO tbl_a_db_connection_test (
                    f_connection_code, f_environment, f_os_family, f_driver, f_status, f_message, f_tested_by
                ) VALUES (
                    :code, :environment, :os_family, :driver, :status, :message, :tested_by
                )
            ");
            $insert->execute([
                ':code' => trim($code),
                ':environment' => trim($environment),
                ':os_family' => trim($osFamily),
                ':driver' => trim((string)($driver ?? 'auto')),
                ':status' => strtoupper(trim($status)),
                ':message' => $message,
                ':tested_by' => null,
            ]);
        } catch (Throwable $e) {
            // optional audit table; ignore if not available
        }
    }

    public function connectionCodeExists(string $code): bool
    {
        $this->assertTablesReady();

        $stmt = $this->pdo->prepare("
            SELECT 1
            FROM tbl_m_db_connection
            WHERE f_code = :code
            LIMIT 1
        ");
        $stmt->execute([':code' => trim($code)]);
        return $stmt->fetchColumn() !== false;
    }

    private function assertTablesReady(): void
    {
        if ($this->tableChecked) {
            return;
        }

        $databaseName = (string)$this->pdo->query('SELECT DATABASE()')->fetchColumn();
        if ($databaseName === '') {
            throw new RuntimeException('Tidak dapat mengesan nama database aktif untuk semakan jadual sambungan tambahan.');
        }

        $tables = ['tbl_m_db_connection', 'tbl_m_db_connection_env'];
        foreach ($tables as $table) {
            $stmt = $this->pdo->prepare("
                SELECT 1
                FROM information_schema.TABLES
                WHERE TABLE_SCHEMA = :schema_name
                  AND TABLE_NAME = :table_name
                LIMIT 1
            ");
            $stmt->execute([
                ':schema_name' => $databaseName,
                ':table_name' => $table,
            ]);
            if ($stmt->fetchColumn() === false) {
                throw new RuntimeException("Jadual {$table} belum wujud. Sila jalankan migration database connections terlebih dahulu.");
            }
        }

        $this->tableChecked = true;
    }

    /**
     * @param string[] $codes
     * @return array<string, array<int, array<string, mixed>>>
     */
    private function findEnvRowsByCodes(array $codes, bool $includeSecrets = false): array
    {
        if ($codes === []) {
            return [];
        }

        $placeholders = implode(', ', array_fill(0, count($codes), '?'));
        $stmt = $this->pdo->prepare("
            SELECT *
            FROM tbl_m_db_connection_env
            WHERE f_connection_code IN ({$placeholders})
            ORDER BY f_connection_code ASC, f_environment ASC, f_os_family ASC, f_driver ASC
        ");
        $stmt->execute(array_values($codes));
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $map = [];
        foreach ($rows as $row) {
            $code = (string)($row['f_connection_code'] ?? '');
            $storedSecret = $this->normalizeNullableString($row['f_password_ciphertext'] ?? null);
            $row['f_has_password'] = $storedSecret !== null;
            $row['f_credential_format'] = $storedSecret === null
                ? 'none'
                : (str_starts_with($storedSecret, 'v2:') ? 'v2' : 'legacy');
            $row['f_password_ciphertext'] = $includeSecrets
                ? $this->decryptSecret($row['f_password_ciphertext'] ?? null)
                : null;
            $map[$code][] = $row;
        }

        return $map;
    }

    /**
     * @param array<int, array<string, mixed>> $envRows
     */
    private function replaceEnvRows(string $code, array $envRows): void
    {
        $existingRows = $this->findEnvRowsByCodes([$code], true)[$code] ?? [];
        $existingPasswordMap = [];
        foreach ($existingRows as $existingRow) {
            $mapKey = strtolower(trim((string)($existingRow['f_environment'] ?? '')))
                . '|' . strtolower(trim((string)($existingRow['f_os_family'] ?? 'any')))
                . '|' . strtolower(trim((string)($existingRow['f_driver'] ?? '')));
            $existingPasswordMap[$mapKey] = $existingRow['f_password_ciphertext'] ?? null;
        }

        $delete = $this->pdo->prepare("DELETE FROM tbl_m_db_connection_env WHERE f_connection_code = :code");
        $delete->execute([':code' => $code]);

        $insert = $this->pdo->prepare("
            INSERT INTO tbl_m_db_connection_env (
                f_connection_code, f_environment, f_os_family, f_driver,
                f_host, f_port, f_database_name, f_dsn_name, f_username,
                f_password_ciphertext, f_charset, f_extra_json, f_is_active
            ) VALUES (
                :connection_code, :environment, :os_family, :driver,
                :host, :port, :database_name, :dsn_name, :username,
                :password_ciphertext, :charset, :extra_json, :is_active
            )
        ");

        foreach ($envRows as $row) {
            $mapKey = strtolower(trim((string)($row['f_environment'] ?? '')))
                . '|' . strtolower(trim((string)($row['f_os_family'] ?? 'any')))
                . '|' . strtolower(trim((string)($row['f_driver'] ?? '')));
            $incomingPassword = $this->normalizeNullableString($row['f_password_ciphertext'] ?? null);
            if ($incomingPassword === null && array_key_exists($mapKey, $existingPasswordMap)) {
                $incomingPassword = $this->normalizeNullableString($existingPasswordMap[$mapKey]);
            }

            $insert->execute([
                ':connection_code' => $code,
                ':environment' => trim((string)($row['f_environment'] ?? '')),
                ':os_family' => trim((string)($row['f_os_family'] ?? 'any')),
                ':driver' => trim((string)($row['f_driver'] ?? '')),
                ':host' => $this->normalizeNullableString($row['f_host'] ?? null),
                ':port' => $this->normalizeNullableString($row['f_port'] ?? null),
                ':database_name' => $this->normalizeNullableString($row['f_database_name'] ?? null),
                ':dsn_name' => $this->normalizeNullableString($row['f_dsn_name'] ?? null),
                ':username' => $this->normalizeNullableString($row['f_username'] ?? null),
                ':password_ciphertext' => $this->encryptSecret($incomingPassword),
                ':charset' => $this->normalizeNullableString($row['f_charset'] ?? null),
                ':extra_json' => $this->normalizeJson($row['f_extra_json'] ?? null),
                ':is_active' => !empty($row['f_is_active']) ? 1 : 0,
            ]);
        }
    }

    private function encryptSecret(mixed $value): ?string
    {
        $stringValue = $this->normalizeNullableString($value);
        if ($stringValue === null) {
            return null;
        }

        return $this->cipher()->encrypt($stringValue);
    }

    private function decryptSecret(mixed $value): ?string
    {
        $stringValue = $this->normalizeNullableString($value);
        if ($stringValue === null) {
            return null;
        }

        return $this->cipher()->decrypt($stringValue);
    }

    private function cipher(): DatabaseCredentialCipher
    {
        return $this->credentialCipher ?? new DatabaseCredentialCipher();
    }

    private function normalizeNullableString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $normalized = trim((string)$value);
        return $normalized === '' ? null : $normalized;
    }

    private function normalizeJson(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_array($value)) {
            return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }

        $stringValue = trim((string)$value);
        return $stringValue === '' ? null : $stringValue;
    }

    /** @param array<string, mixed> $connection */
    private function buildRegistryRevision(array $connection): string
    {
        $master = [];
        foreach (['f_code', 'f_name', 'f_family', 'f_purpose', 'f_driver_mode', 'f_is_enabled', 'f_supports_prod', 'f_supports_dev', 'f_notes', 'f_updated_at'] as $key) {
            $master[$key] = $connection[$key] ?? null;
        }

        $variants = [];
        foreach (($connection['env_rows'] ?? []) as $row) {
            if (!is_array($row)) {
                continue;
            }
            $variant = [];
            foreach (['id', 'f_db_connection_env_id', 'f_environment', 'f_os_family', 'f_driver', 'f_host', 'f_port', 'f_database_name', 'f_dsn_name', 'f_username', 'f_charset', 'f_extra_json', 'f_is_active', 'f_credential_format', 'f_has_password', 'f_updated_at'] as $key) {
                $variant[$key] = $row[$key] ?? null;
            }
            $variants[] = $variant;
        }

        return hash('sha256', json_encode([$master, $variants], JSON_UNESCAPED_SLASHES) ?: '');
    }
}
