<?php
/**
 * IQS FRAMEWORK CORE FILE
 *
 * READ ONLY for downstream project programmers.
 * Do not modify this file directly in template or cloned projects.
 * Custom changes must be implemented in project-specific files
 * or approved extension points.
 */declare(strict_types=1);

require_once __DIR__ . '/SystemConfigConstants.php';

final class DatabaseConnectionValidator
{
    private const ALLOWED_ENVIRONMENTS = ['production', 'development'];
    private const ALLOWED_OS_FAMILIES = ['any', 'windows', 'linux'];

    public function validateMainMysqlEnvironment(string $environment): array
    {
        return in_array($environment, SystemConfigConstants::ALLOWED_MAIN_DB_ENVIRONMENTS, true)
            ? []
            : ['Main MySQL environment tidak sah.'];
    }

    public function validateSybaseRuntime(string $environment, string $mode): array
    {
        $errors = [];
        if (!in_array($environment, SystemConfigConstants::ALLOWED_SYBASE_ENVIRONMENTS, true)) {
            $errors[] = 'Sybase environment tidak sah.';
        }
        if (!in_array($mode, SystemConfigConstants::ALLOWED_SYBASE_OPERATIONAL_MODES, true)) {
            $errors[] = 'Sybase operational mode tidak sah.';
        }
        return $errors;
    }

    public function validateAdditionalPayload(array $payload, array $envRows = [], bool $isUpdate = false): array
    {
        $errors = [];

        $code = strtolower(trim((string)($payload['f_code'] ?? '')));
        $name = trim((string)($payload['f_name'] ?? ''));
        $family = strtolower(trim((string)($payload['f_family'] ?? '')));
        $purpose = trim((string)($payload['f_purpose'] ?? ''));
        $driverMode = strtolower(trim((string)($payload['f_driver_mode'] ?? 'auto')));
        $supportsProd = !empty($payload['f_supports_prod']);
        $supportsDev = !empty($payload['f_supports_dev']);

        if ($code === '') {
            $errors[] = 'Kod sambungan tambahan wajib diisi.';
        } elseif (preg_match('/^[a-z][a-z0-9_]{2,99}$/', $code) !== 1) {
            $errors[] = 'Kod sambungan tambahan mesti menggunakan huruf kecil, nombor, dan underscore sahaja.';
        }

        if (in_array($code, SystemConfigConstants::RESERVED_DATABASE_CODES, true)) {
            $errors[] = 'Kod sambungan ini dikhaskan untuk sistem utama dan tidak boleh digunakan.';
        }

        if ($name === '') {
            $errors[] = 'Nama sambungan tambahan wajib diisi.';
        }

        if (!in_array($family, SystemConfigConstants::ALLOWED_DATABASE_FAMILIES, true)) {
            $errors[] = 'Jenis database tambahan tidak sah.';
        }

        if ($purpose === '') {
            $errors[] = 'Tujuan sambungan tambahan wajib diisi.';
        }

        if (!in_array($driverMode, SystemConfigConstants::ALLOWED_DATABASE_DRIVER_MODES, true)) {
            $errors[] = 'Driver mode sambungan tambahan tidak sah.';
        }

        if (!$supportsProd && !$supportsDev) {
            $errors[] = 'Sekurang-kurangnya satu environment mesti diaktifkan untuk sambungan tambahan.';
        }

        if ($envRows === []) {
            $errors[] = 'Sekurang-kurangnya satu konfigurasi environment sambungan tambahan diperlukan.';
            return $errors;
        }

        $seenEnvKeys = [];
        $hasActiveRow = false;
        $hasProductionRow = false;
        $hasDevelopmentRow = false;

        foreach ($envRows as $index => $row) {
            $label = 'Konfigurasi #' . ($index + 1);
            $environment = strtolower(trim((string)($row['f_environment'] ?? '')));
            $osFamily = strtolower(trim((string)($row['f_os_family'] ?? 'any')));
            $driver = strtolower(trim((string)($row['f_driver'] ?? '')));
            $host = trim((string)($row['f_host'] ?? ''));
            $port = trim((string)($row['f_port'] ?? ''));
            $databaseName = trim((string)($row['f_database_name'] ?? ''));
            $dsnName = trim((string)($row['f_dsn_name'] ?? ''));
            $username = trim((string)($row['f_username'] ?? ''));
            $isActive = !empty($row['f_is_active']);

            if (!in_array($environment, self::ALLOWED_ENVIRONMENTS, true)) {
                $errors[] = "{$label}: environment tidak sah.";
            } elseif ($environment === 'production') {
                $hasProductionRow = true;
            } elseif ($environment === 'development') {
                $hasDevelopmentRow = true;
            }

            if (!in_array($osFamily, self::ALLOWED_OS_FAMILIES, true)) {
                $errors[] = "{$label}: OS family tidak sah.";
            }

            if ($driver === '') {
                $errors[] = "{$label}: driver wajib diisi.";
            }

            if ($family === 'mysql' && $driver !== 'mysql') {
                $errors[] = "{$label}: MySQL tambahan mesti menggunakan driver mysql.";
            }

            if ($family === 'sybase' && !in_array($driver, ['odbc', 'dblib'], true)) {
                $errors[] = "{$label}: Sybase tambahan hanya menyokong driver odbc atau dblib.";
            }

            if ($family === 'mssql' && !in_array($driver, ['sqlsrv', 'odbc', 'dblib'], true)) {
                $errors[] = "{$label}: MSSQL tambahan hanya menyokong driver sqlsrv, odbc, atau dblib.";
            }

            if (in_array($driver, ['mysql', 'dblib', 'sqlsrv'], true)) {
                if ($host === '') {
                    $errors[] = "{$label}: host wajib diisi untuk driver {$driver}.";
                }
                if ($databaseName === '') {
                    $errors[] = "{$label}: nama database wajib diisi untuk driver {$driver}.";
                }
            }

            if ($port !== '' && (!ctype_digit($port) || (int)$port < 1 || (int)$port > SystemConfigConstants::MAX_PORT)) {
                $errors[] = "{$label}: port mesti nombor antara 1 hingga " . SystemConfigConstants::MAX_PORT . '.';
            }

            if ($driver === 'odbc' && $dsnName === '') {
                $errors[] = "{$label}: DSN wajib diisi untuk driver odbc.";
            }

            if ($username === '') {
                $errors[] = "{$label}: username wajib diisi.";
            }

            if ($isActive) {
                $hasActiveRow = true;
            }

            $envKey = $environment . '|' . $osFamily . '|' . $driver;
            if ($environment !== '' && $driver !== '') {
                if (isset($seenEnvKeys[$envKey])) {
                    $errors[] = "{$label}: kombinasi environment, OS family, dan driver ini sudah wujud.";
                } else {
                    $seenEnvKeys[$envKey] = true;
                }
            }
        }

        foreach (self::ALLOWED_ENVIRONMENTS as $environment) {
            foreach (['mysql', 'odbc', 'dblib', 'sqlsrv'] as $driver) {
                $hasAny = isset($seenEnvKeys[$environment . '|any|' . $driver]);
                $hasSpecific = isset($seenEnvKeys[$environment . '|windows|' . $driver])
                    || isset($seenEnvKeys[$environment . '|linux|' . $driver]);
                if ($hasAny && $hasSpecific) {
                    $errors[] = "Kombinasi {$environment}/{$driver} tidak boleh mencampurkan OS family any dengan variant OS-specific.";
                }
            }
        }

        if (!$hasActiveRow) {
            $errors[] = 'Sekurang-kurangnya satu env row aktif diperlukan untuk sambungan tambahan.';
        }

        if ($supportsProd && !$hasProductionRow) {
            $errors[] = 'Supports production diaktifkan tetapi tiada env row production disediakan.';
        }

        if ($supportsDev && !$hasDevelopmentRow) {
            $errors[] = 'Supports development diaktifkan tetapi tiada env row development disediakan.';
        }

        if (!$supportsProd && $hasProductionRow) {
            $errors[] = 'Env row production wujud tetapi pilihan supports production dimatikan.';
        }

        if (!$supportsDev && $hasDevelopmentRow) {
            $errors[] = 'Env row development wujud tetapi pilihan supports development dimatikan.';
        }

        return array_values(array_unique($errors));
    }
}
