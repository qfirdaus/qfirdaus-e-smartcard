<?php
/**
 * IQS FRAMEWORK CORE FILE
 *
 * READ ONLY for downstream project programmers.
 * Do not modify this file directly in template or cloned projects.
 * Custom changes must be implemented in project-specific files
 * or approved extension points.
 */declare(strict_types=1);

require_once __DIR__ . '/DatabaseConnectionDefinition.php';

final class DatabaseConnectionRegistry
{
    /** @var array<string, DatabaseConnectionDefinition> */
    private array $definitions = [];
    /** @var array<string, array<string, mixed>> */
    private array $flatConfigs = [];

    /**
     * @param array<string, DatabaseConnectionDefinition> $definitions
     * @param array<string, array<string, mixed>> $flatConfigs
     */
    public function __construct(array $definitions = [], array $flatConfigs = [])
    {
        $this->definitions = $definitions;
        $this->flatConfigs = $flatConfigs;
    }

    public static function fromConfigArray(array $flatConfigs): self
    {
        $definitions = [];

        if (isset($flatConfigs['mysql_prod']) || isset($flatConfigs['mysql_dev']) || isset($flatConfigs['mysql'])) {
            $definitions['mysql_main'] = new DatabaseConnectionDefinition(
                code: 'mysql_main',
                name: 'MySQL Main',
                family: 'mysql',
                category: 'main',
                purpose: 'core',
                required: true,
                enabled: true,
                driverMode: 'auto',
                environments: [
                    'production' => [
                        'resolved_key' => isset($flatConfigs['mysql_prod']) ? 'mysql_prod' : 'mysql',
                        'config' => $flatConfigs['mysql_prod'] ?? $flatConfigs['mysql'] ?? [],
                    ],
                    'development' => [
                        'resolved_key' => isset($flatConfigs['mysql_dev']) ? 'mysql_dev' : (isset($flatConfigs['mysql_prod']) ? 'mysql_prod' : 'mysql'),
                        'config' => $flatConfigs['mysql_dev'] ?? $flatConfigs['mysql_prod'] ?? $flatConfigs['mysql'] ?? [],
                    ],
                ],
            );
        }

        $definitions['sybase_staff'] = new DatabaseConnectionDefinition(
            code: 'sybase_staff',
            name: 'Sybase Staff',
            family: 'sybase',
            category: 'main',
            purpose: 'core',
            required: true,
            enabled: true,
            driverMode: 'auto',
            environments: [
                'production' => self::buildSybaseEnvironmentMap($flatConfigs, 'sybase_staff_prod'),
                'development' => self::buildSybaseEnvironmentMap($flatConfigs, 'sybase_staff_dev'),
            ],
        );

        $definitions['sybase_student'] = new DatabaseConnectionDefinition(
            code: 'sybase_student',
            name: 'Sybase Student',
            family: 'sybase',
            category: 'main',
            purpose: 'core',
            required: true,
            enabled: true,
            driverMode: 'auto',
            environments: [
                'production' => self::buildSybaseEnvironmentMap($flatConfigs, 'sybase_student_prod'),
                'development' => self::buildSybaseEnvironmentMap($flatConfigs, 'sybase_student_dev'),
            ],
        );

        foreach ($flatConfigs as $key => $config) {
            if (!str_starts_with($key, 'dbx_')) {
                continue;
            }
            if (preg_match('/_(dsn|dblib|odbc|sqlsrv)$/', $key) === 1) {
                continue;
            }

            $definitions[$key] = new DatabaseConnectionDefinition(
                code: $key,
                name: strtoupper($key),
                family: str_contains($key, 'sybase') ? 'sybase' : (str_contains($key, 'mssql') ? 'mssql' : 'mysql'),
                category: 'additional',
                purpose: 'reference',
                required: false,
                enabled: true,
                driverMode: 'auto',
                environments: [
                    'production' => [
                        'resolved_key' => $key,
                        'config' => $config,
                    ],
                    'development' => [
                        'resolved_key' => $key,
                        'config' => $config,
                    ],
                ],
            );
        }

        return new self($definitions, $flatConfigs);
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     */
    public function withAdditionalRows(array $rows): self
    {
        $definitions = $this->definitions;

        foreach ($rows as $row) {
            $code = strtolower(trim((string)($row['f_code'] ?? '')));
            if ($code === '') {
                continue;
            }

            $definition = self::buildAdditionalDefinitionFromRow($row);
            if ($definition instanceof DatabaseConnectionDefinition) {
                $definitions[$code] = $definition;
            }
        }

        return new self($definitions, $this->flatConfigs);
    }

    /**
     * @return array<string, mixed>
     */
    private static function buildSybaseEnvironmentMap(array $flatConfigs, string $baseKey): array
    {
        $map = [];

        if (isset($flatConfigs[$baseKey . '_dsn'])) {
            $map['windows']['dsn'] = [
                'resolved_key' => $baseKey . '_dsn',
                'config' => $flatConfigs[$baseKey . '_dsn'],
            ];
            $map['linux']['dsn'] = [
                'resolved_key' => $baseKey . '_dsn',
                'config' => $flatConfigs[$baseKey . '_dsn'],
            ];
        }

        if (isset($flatConfigs[$baseKey . '_dblib'])) {
            $map['windows']['dblib'] = [
                'resolved_key' => $baseKey . '_dblib',
                'config' => $flatConfigs[$baseKey . '_dblib'],
            ];
            $map['linux']['dblib'] = [
                'resolved_key' => $baseKey . '_dblib',
                'config' => $flatConfigs[$baseKey . '_dblib'],
            ];
        }

        return $map;
    }

    /**
     * @param array<string, mixed> $row
     */
    private static function buildAdditionalDefinitionFromRow(array $row): ?DatabaseConnectionDefinition
    {
        $code = strtolower(trim((string)($row['f_code'] ?? '')));
        $family = strtolower(trim((string)($row['f_family'] ?? '')));
        if ($code === '' || $family === '') {
            return null;
        }

        $envRows = is_array($row['env_rows'] ?? null) ? $row['env_rows'] : [];
        $environments = [];

        foreach ($envRows as $envRow) {
            if (!is_array($envRow) || empty($envRow['f_is_active'])) {
                continue;
            }

            $environment = strtolower(trim((string)($envRow['f_environment'] ?? '')));
            if (!in_array($environment, ['production', 'development'], true)) {
                continue;
            }

            if ($family === 'mysql') {
                $config = self::buildMysqlConfigFromEnvRow($envRow);
                if ($config !== null) {
                    $osFamily = strtolower(trim((string)($envRow['f_os_family'] ?? 'any')));
                    $osFamily = in_array($osFamily, ['windows', 'linux'], true) ? $osFamily : 'any';
                    $environments[$environment][$osFamily]['mysql'] = [
                        'resolved_key' => self::buildResolvedKey($code, $environment, (string)($envRow['f_driver'] ?? 'mysql'), (string)($envRow['f_os_family'] ?? 'any')),
                        'config' => $config,
                    ];
                }
                continue;
            }

            $osFamily = strtolower(trim((string)($envRow['f_os_family'] ?? 'any')));
            $driver = strtolower(trim((string)($envRow['f_driver'] ?? '')));
            $config = self::buildVariantConfigFromEnvRow($family, $envRow);
            if ($config === null || $driver === '') {
                continue;
            }

            if (!isset($environments[$environment]) || !is_array($environments[$environment])) {
                $environments[$environment] = [];
            }

            $targetOsFamily = in_array($osFamily, ['windows', 'linux'], true) ? $osFamily : 'any';
            if (!isset($environments[$environment][$targetOsFamily]) || !is_array($environments[$environment][$targetOsFamily])) {
                $environments[$environment][$targetOsFamily] = [];
            }
            $environments[$environment][$targetOsFamily][$driver] = [
                'resolved_key' => self::buildResolvedKey($code, $environment, $driver, $targetOsFamily),
                'config' => $config,
            ];
        }

        return new DatabaseConnectionDefinition(
            code: $code,
            name: trim((string)($row['f_name'] ?? strtoupper($code))),
            family: $family,
            category: 'additional',
            purpose: trim((string)($row['f_purpose'] ?? 'reference')),
            required: false,
            enabled: !empty($row['f_is_enabled']),
            driverMode: strtolower(trim((string)($row['f_driver_mode'] ?? 'auto'))),
            environments: $environments,
        );
    }

    /**
     * @param array<string, mixed> $envRow
     * @return array<string, mixed>|null
     */
    private static function buildMysqlConfigFromEnvRow(array $envRow): ?array
    {
        $host = trim((string)($envRow['f_host'] ?? ''));
        $databaseName = trim((string)($envRow['f_database_name'] ?? ''));
        $username = (string)($envRow['f_username'] ?? '');
        if ($host === '' || $databaseName === '' || $username === '') {
            return null;
        }

        $port = trim((string)($envRow['f_port'] ?? ''));
        $charset = trim((string)($envRow['f_charset'] ?? 'utf8mb4'));

        return [
            'driver' => 'mysql',
            'dsn' => sprintf(
                'mysql:host=%s;port=%s;dbname=%s;charset=%s',
                $host,
                $port !== '' ? $port : '3306',
                $databaseName,
                $charset !== '' ? $charset : 'utf8mb4'
            ),
            'user' => $username,
            'pass' => (string)($envRow['f_password_ciphertext'] ?? ''),
        ];
    }

    /**
     * @param array<string, mixed> $envRow
     * @return array<string, mixed>|null
     */
    private static function buildVariantConfigFromEnvRow(string $family, array $envRow): ?array
    {
        $driver = strtolower(trim((string)($envRow['f_driver'] ?? '')));
        $host = trim((string)($envRow['f_host'] ?? ''));
        $port = trim((string)($envRow['f_port'] ?? ''));
        $databaseName = trim((string)($envRow['f_database_name'] ?? ''));
        $dsnName = trim((string)($envRow['f_dsn_name'] ?? ''));
        $username = (string)($envRow['f_username'] ?? '');
        $password = (string)($envRow['f_password_ciphertext'] ?? '');
        $extra = self::decodeExtraOptions($envRow['f_extra_json'] ?? null);

        if ($driver === 'odbc') {
            if ($dsnName === '' || $username === '') {
                return null;
            }

            return [
                'driver' => 'odbc',
                'dsn' => 'odbc:' . self::appendOdbcSqlServerOptions($dsnName, $extra),
                'user' => $username,
                'pass' => $password,
            ];
        }

        if ($driver === 'dblib') {
            if ($host === '' || $databaseName === '' || $username === '') {
                return null;
            }

            return [
                'driver' => 'dblib',
                'dsn' => sprintf(
                    'dblib:host=%s:%s;dbname=%s',
                    $host,
                    $port !== '' ? $port : ($family === 'mssql' ? '1433' : '5000'),
                    $databaseName
                ),
                'user' => $username,
                'pass' => $password,
            ];
        }

        if ($family === 'mssql' && $driver === 'sqlsrv') {
            if ($host === '' || $databaseName === '' || $username === '') {
                return null;
            }

            return [
                'driver' => 'sqlsrv',
                'dsn' => sprintf(
                    'sqlsrv:Server=%s%s;Database=%s%s',
                    $host,
                    $port !== '' ? ',' . $port : '',
                    $databaseName,
                    self::buildSqlServerDsnOptions($extra)
                ),
                'user' => $username,
                'pass' => $password,
            ];
        }

        return null;
    }

    private static function decodeExtraOptions(mixed $value): array
    {
        if (is_array($value)) {
            return $value;
        }
        if (!is_string($value) || trim($value) === '') {
            return [];
        }
        $decoded = json_decode($value, true);
        return is_array($decoded) ? $decoded : [];
    }

    private static function extraBool(array $extra, array $keys, bool $default = false): bool
    {
        foreach ($keys as $key) {
            if (!array_key_exists($key, $extra)) {
                continue;
            }
            $value = $extra[$key];
            if (is_bool($value)) {
                return $value;
            }
            return in_array(strtolower(trim((string)$value)), ['1', 'true', 'yes', 'on'], true);
        }

        return $default;
    }

    private static function buildSqlServerDsnOptions(array $extra): string
    {
        $parts = [];
        if (self::extraBool($extra, ['encrypt', 'Encrypt'], false)) {
            $parts[] = 'Encrypt=yes';
        }
        if (self::extraBool($extra, ['trust_server_certificate', 'TrustServerCertificate'], false)) {
            $parts[] = 'TrustServerCertificate=yes';
        }

        return $parts !== [] ? ';' . implode(';', $parts) : '';
    }

    private static function appendOdbcSqlServerOptions(string $dsnName, array $extra): string
    {
        $options = ltrim(self::buildSqlServerDsnOptions($extra), ';');
        if ($options === '') {
            return $dsnName;
        }

        return rtrim($dsnName, ';') . ';' . $options;
    }

    private static function buildResolvedKey(string $code, string $environment, string $driver, string $osFamily): string
    {
        $suffix = trim($osFamily) !== '' && $osFamily !== 'any'
            ? '_' . strtolower(trim($osFamily))
            : '';

        return strtolower(trim($code)) . '_' . strtolower(trim($environment)) . '_' . strtolower(trim($driver)) . $suffix;
    }

    /**
     * @return array<string, DatabaseConnectionDefinition>
     */
    public function all(): array
    {
        return $this->definitions;
    }

    public function get(string $code): ?DatabaseConnectionDefinition
    {
        return $this->definitions[$code] ?? null;
    }

    public function getMain(string $code): DatabaseConnectionDefinition
    {
        $definition = $this->get($code);
        if (!$definition || !$definition->isMain()) {
            throw new RuntimeException("Main database connection not found: {$code}");
        }
        return $definition;
    }

    public function getAdditional(string $code): ?DatabaseConnectionDefinition
    {
        $definition = $this->get($code);
        if (!$definition || !$definition->isAdditional()) {
            return null;
        }
        return $definition;
    }

    /**
     * @return array<string, DatabaseConnectionDefinition>
     */
    public function listAdditional(): array
    {
        return array_filter(
            $this->definitions,
            static fn(DatabaseConnectionDefinition $definition): bool => $definition->isAdditional()
        );
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getFlatConfig(string $key): ?array
    {
        return $this->flatConfigs[$key] ?? null;
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public function getFlatConfigs(): array
    {
        return $this->flatConfigs;
    }
}
