<?php
/**
 * IQS FRAMEWORK CORE FILE
 *
 * READ ONLY for downstream project programmers.
 * Do not modify this file directly in template or cloned projects.
 * Custom changes must be implemented in project-specific files
 * or approved extension points.
 */
declare(strict_types=1);

require_once __DIR__ . '/DatabaseCredentialCipher.php';

final class DatabaseConnectionDiagnostics
{
    /**
     * Build a read-only registry snapshot without opening any additional connection.
     *
     * @param array<int, array<string, mixed>> $connections
     * @param string[]|null $availableDrivers
     * @return array<string, mixed>
     */
    public function analyze(array $connections, ?array $availableDrivers = null, ?string $osFamily = null): array
    {
        $drivers = array_values(array_unique(array_map(
            static fn(mixed $driver): string => strtolower(trim((string)$driver)),
            $availableDrivers ?? PDO::getAvailableDrivers()
        )));
        sort($drivers);

        $runtimeOs = strtolower(trim((string)($osFamily ?? (PHP_OS_FAMILY === 'Windows' ? 'windows' : 'linux'))));
        $runtimeOs = in_array($runtimeOs, ['windows', 'linux'], true) ? $runtimeOs : 'linux';
        $warnings = [];
        $enabledCount = 0;
        $activeEnvCount = 0;
        $legacyCredentialCount = 0;
        $coverageChecks = 0;
        $coverageAvailable = 0;
        $coverageMissing = [];
        $credentialCipher = null;
        $credentialWriteFormat = 'unavailable';
        $credentialKeyId = '-';
        try {
            $credentialCipher = new DatabaseCredentialCipher();
            $credentialWriteFormat = $credentialCipher->getWriteFormat();
            $credentialKeyId = $credentialCipher->getKeyId();
        } catch (Throwable $error) {
            $warnings[] = $this->warning('-', 'error', 'Konfigurasi credential encryption tidak sah: ' . $error->getMessage());
        }

        foreach ($connections as $connection) {
            $code = strtolower(trim((string)($connection['f_code'] ?? '')));
            $enabled = !empty($connection['f_is_enabled']);
            $envRows = is_array($connection['env_rows'] ?? null) ? $connection['env_rows'] : [];
            $activeRows = array_values(array_filter(
                $envRows,
                static fn(mixed $row): bool => is_array($row) && !empty($row['f_is_active'])
            ));

            if ($enabled) {
                $enabledCount++;
            }
            $activeEnvCount += count($activeRows);

            if ($enabled && $activeRows === []) {
                $warnings[] = $this->warning($code, 'error', 'Tiada env row aktif untuk connection yang enabled.');
                continue;
            }

            foreach ($activeRows as $row) {
                $environment = strtolower(trim((string)($row['f_environment'] ?? '')));
                $rowOs = strtolower(trim((string)($row['f_os_family'] ?? 'any')));
                $driver = strtolower(trim((string)($row['f_driver'] ?? '')));
                $label = trim($environment . ' / ' . ($rowOs !== '' ? $rowOs : 'any') . ' / ' . $driver, ' /');

                $matchesRuntime = in_array($rowOs, ['', 'any', $runtimeOs], true);
                if ($matchesRuntime && $driver !== '' && !in_array($driver, $drivers, true)) {
                    $warnings[] = $this->warning($code, 'error', "PDO driver tidak tersedia: {$label}.");
                }
                if (array_key_exists('f_has_password', $row) && empty($row['f_has_password'])) {
                    $warnings[] = $this->warning($code, 'warning', "Credential password belum disediakan: {$label}.");
                }
                if (($row['f_credential_format'] ?? null) === 'legacy') {
                    $legacyCredentialCount++;
                }
            }

            if (!empty($connection['f_supports_prod']) && !$this->hasEnvironment($activeRows, 'production')) {
                $warnings[] = $this->warning($code, 'error', 'Production disokong tetapi tiada production env row aktif.');
            }
            if (!empty($connection['f_supports_dev']) && !$this->hasEnvironment($activeRows, 'development')) {
                $warnings[] = $this->warning($code, 'error', 'Development disokong tetapi tiada development env row aktif.');
            }

            $supportedEnvironments = [];
            if (!empty($connection['f_supports_prod'])) {
                $supportedEnvironments[] = 'production';
            }
            if (!empty($connection['f_supports_dev'])) {
                $supportedEnvironments[] = 'development';
            }
            foreach ($supportedEnvironments as $environment) {
                foreach (['windows', 'linux'] as $platform) {
                    $coverageChecks++;
                    if ($this->hasPlatformVariant($activeRows, $environment, $platform)) {
                        $coverageAvailable++;
                    } else {
                        $coverageMissing[] = [
                            'connection_code' => $code,
                            'environment' => $environment,
                            'os_family' => $platform,
                        ];
                    }
                }
            }

            $productionTargets = $this->targetSignatures($activeRows, 'production');
            $developmentTargets = $this->targetSignatures($activeRows, 'development');
            if (array_intersect($productionTargets, $developmentTargets) !== []) {
                $warnings[] = $this->warning($code, 'warning', 'Production dan development mempunyai target database yang sama.');
            }
        }

        if ($legacyCredentialCount > 0 && (!$credentialCipher instanceof DatabaseCredentialCipher || !$credentialCipher->isV2Configured())) {
            $warnings[] = $this->warning('-', 'warning', 'Credential legacy masih digunakan dan encryption key v2 belum dikonfigurasi.');
        }
        $errorCount = count(array_filter(
            $warnings,
            static fn(array $warning): bool => ($warning['severity'] ?? '') === 'error'
        ));

        $status = $errorCount > 0 ? 'attention' : ($warnings !== [] ? 'warning' : 'healthy');
        return [
            'runtime_os' => $runtimeOs,
            'available_drivers' => $drivers,
            'connection_count' => count($connections),
            'enabled_count' => $enabledCount,
            'disabled_count' => count($connections) - $enabledCount,
            'active_env_count' => $activeEnvCount,
            'legacy_credential_count' => $legacyCredentialCount,
            'credential_write_format' => $credentialWriteFormat,
            'credential_key_id' => $credentialKeyId,
            'warning_count' => count($warnings),
            'error_count' => $errorCount,
            'status' => $status,
            'warnings' => $warnings,
            'live_connection_tested' => false,
            'runtime_health' => [
                'os_family' => $runtimeOs,
                'status' => $status,
                'error_count' => $errorCount,
                'warning_count' => count($warnings) - $errorCount,
            ],
            'platform_coverage' => [
                'required_count' => $coverageChecks,
                'available_count' => $coverageAvailable,
                'missing_count' => count($coverageMissing),
                'missing' => $coverageMissing,
            ],
        ];
    }

    /** @param array<int, array<string, mixed>> $rows */
    private function hasEnvironment(array $rows, string $environment): bool
    {
        foreach ($rows as $row) {
            if (strtolower(trim((string)($row['f_environment'] ?? ''))) === $environment) {
                return true;
            }
        }
        return false;
    }

    /** @param array<int, array<string, mixed>> $rows */
    private function hasPlatformVariant(array $rows, string $environment, string $osFamily): bool
    {
        foreach ($rows as $row) {
            $rowEnvironment = strtolower(trim((string)($row['f_environment'] ?? '')));
            $rowOs = strtolower(trim((string)($row['f_os_family'] ?? 'any')));
            if ($rowEnvironment === $environment && in_array($rowOs, ['any', $osFamily], true)) {
                return true;
            }
        }
        return false;
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     * @return string[]
     */
    private function targetSignatures(array $rows, string $environment): array
    {
        $signatures = [];
        foreach ($rows as $row) {
            if (strtolower(trim((string)($row['f_environment'] ?? ''))) !== $environment) {
                continue;
            }
            $signatures[] = strtolower(implode('|', [
                trim((string)($row['f_driver'] ?? '')),
                trim((string)($row['f_host'] ?? '')),
                trim((string)($row['f_port'] ?? '')),
                trim((string)($row['f_database_name'] ?? '')),
                trim((string)($row['f_dsn_name'] ?? '')),
            ]));
        }
        return array_values(array_unique(array_filter($signatures, static fn(string $value): bool => $value !== '||||')));
    }

    /** @return array{connection_code: string, severity: string, message: string} */
    private function warning(string $code, string $severity, string $message): array
    {
        return [
            'connection_code' => $code !== '' ? $code : '-',
            'severity' => $severity,
            'message' => $message,
        ];
    }
}
