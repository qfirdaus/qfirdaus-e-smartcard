<?php
/**
 * IQS Framework Additional Database compatibility regression suite.
 *
 * This test is intentionally dependency-free and must not open a live database
 * connection. It protects the public API and resolver/registry behavior used by
 * downstream projects.
 *
 * Run: php tests/additional-database-regression.php
 */
declare(strict_types=1);

require_once __DIR__ . '/../public/classes/Database.php';
require_once __DIR__ . '/../public/classes/DatabaseConnectionDefinition.php';
require_once __DIR__ . '/../public/classes/DatabaseConnectionRegistry.php';
require_once __DIR__ . '/../public/classes/DatabaseConnectionResolver.php';
require_once __DIR__ . '/../public/classes/DatabaseConnectionValidator.php';
require_once __DIR__ . '/../public/classes/DatabaseConnectionDiagnostics.php';
require_once __DIR__ . '/../public/classes/DatabaseCredentialCipher.php';
require_once __DIR__ . '/../public/classes/DatabasePreviewSanitizer.php';
require_once __DIR__ . '/../public/classes/AdditionalDatabaseException.php';
require_once __DIR__ . '/../public/classes/DatabaseErrorRedactor.php';
require_once __DIR__ . '/../public/classes/DatabaseErrorClassifier.php';
require_once __DIR__ . '/../public/setting/helper/security_helper.php';

final class AdditionalDatabaseRegressionSuite
{
    /** @var array<int, array{name: string, test: Closure}> */
    private array $tests = [];
    private int $passed = 0;
    private int $failed = 0;

    public function add(string $name, Closure $test): void
    {
        $this->tests[] = ['name' => $name, 'test' => $test];
    }

    public function run(): int
    {
        foreach ($this->tests as $entry) {
            try {
                $entry['test']();
                $this->passed++;
                fwrite(STDOUT, "[PASS] {$entry['name']}\n");
            } catch (Throwable $error) {
                $this->failed++;
                fwrite(STDERR, "[FAIL] {$entry['name']}: {$error->getMessage()}\n");
            }
        }

        $total = $this->passed + $this->failed;
        fwrite(STDOUT, "\nResult: {$this->passed}/{$total} passed, {$this->failed} failed.\n");

        return $this->failed === 0 ? 0 : 1;
    }
}

function regressionAssertTrue(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function regressionAssertSame(mixed $expected, mixed $actual, string $message): void
{
    if ($expected !== $actual) {
        throw new RuntimeException(sprintf(
            '%s Expected %s, got %s.',
            $message,
            var_export($expected, true),
            var_export($actual, true)
        ));
    }
}

function regressionAssertContains(string $needle, string $haystack, string $message): void
{
    if (!str_contains($haystack, $needle)) {
        throw new RuntimeException("{$message} Missing: {$needle}");
    }
}

/**
 * @param class-string<Throwable> $exceptionClass
 */
function regressionAssertThrows(callable $callback, string $exceptionClass, string $messageContains = ''): void
{
    try {
        $callback();
    } catch (Throwable $error) {
        regressionAssertTrue(
            $error instanceof $exceptionClass,
            sprintf('Expected %s, got %s.', $exceptionClass, get_class($error))
        );
        if ($messageContains !== '') {
            regressionAssertContains($messageContains, $error->getMessage(), 'Unexpected exception message.');
        }
        return;
    }

    throw new RuntimeException("Expected {$exceptionClass}, but no exception was thrown.");
}

/**
 * @param array<string, array<string, mixed>> $environments
 */
function additionalDefinition(
    string $code,
    string $family,
    array $environments,
    bool $enabled = true,
    string $driverMode = 'auto'
): DatabaseConnectionDefinition {
    return new DatabaseConnectionDefinition(
        code: $code,
        name: strtoupper($code),
        family: $family,
        category: 'additional',
        purpose: 'regression-test',
        required: false,
        enabled: $enabled,
        driverMode: $driverMode,
        environments: $environments,
    );
}

/**
 * @param array<string, DatabaseConnectionDefinition> $definitions
 */
function additionalResolver(array $definitions, ?string $osFamily = null, ?array $availableDrivers = null): DatabaseConnectionResolver
{
    return new DatabaseConnectionResolver(
        new DatabaseRuntimeConfig(null, $osFamily),
        new DatabaseConnectionRegistry($definitions),
        $availableDrivers,
    );
}

function mysqlEnvironment(string $key, string $host): array
{
    return [
        'resolved_key' => $key,
        'config' => [
            'driver' => 'mysql',
            'dsn' => "mysql:host={$host};port=3306;dbname=fixture;charset=utf8mb4",
            'user' => 'fixture_user',
            'pass' => 'fixture_password',
        ],
    ];
}

function variantEnvironment(string $key, string $driver): array
{
    return [
        'resolved_key' => $key,
        'config' => [
            'driver' => $driver,
            'dsn' => "{$driver}:fixture",
            'user' => 'fixture_user',
            'pass' => 'fixture_password',
        ],
    ];
}

$suite = new AdditionalDatabaseRegressionSuite();

$suite->add('public API signature remains Database::pdoAdditional(string, ?string): PDO', static function (): void {
    $method = new ReflectionMethod(Database::class, 'pdoAdditional');
    regressionAssertTrue($method->isPublic(), 'pdoAdditional must remain public.');
    regressionAssertTrue($method->isStatic(), 'pdoAdditional must remain static.');

    $parameters = $method->getParameters();
    regressionAssertSame(2, count($parameters), 'pdoAdditional parameter count changed.');
    regressionAssertSame('code', $parameters[0]->getName(), 'First parameter name changed.');
    regressionAssertSame('string', (string)$parameters[0]->getType(), 'First parameter type changed.');
    regressionAssertSame('environment', $parameters[1]->getName(), 'Second parameter name changed.');
    regressionAssertSame('?string', (string)$parameters[1]->getType(), 'Second parameter type changed.');
    regressionAssertTrue($parameters[1]->isOptional(), 'Environment must remain optional.');
    regressionAssertSame(null, $parameters[1]->getDefaultValue(), 'Environment default must remain null.');
    regressionAssertSame('PDO', (string)$method->getReturnType(), 'Return type must remain PDO.');
});

$suite->add('explicit MySQL production environment resolves production target', static function (): void {
    $definition = additionalDefinition('dbx_mysql_fixture', 'mysql', [
        'production' => mysqlEnvironment('dbx_mysql_fixture_production_mysql', 'prod.fixture'),
        'development' => mysqlEnvironment('dbx_mysql_fixture_development_mysql', 'dev.fixture'),
    ]);
    $resolved = additionalResolver([$definition->code => $definition], 'linux', ['dblib'])
        ->resolveAdditional($definition->code, 'production');

    regressionAssertSame('dbx_mysql_fixture_production_mysql', $resolved['resolved_key'], 'Wrong production key.');
    regressionAssertContains('host=prod.fixture', $resolved['config']['dsn'], 'Wrong production DSN.');
});

$suite->add('explicit MySQL development environment resolves development target', static function (): void {
    $definition = additionalDefinition('dbx_mysql_fixture', 'mysql', [
        'production' => mysqlEnvironment('dbx_mysql_fixture_production_mysql', 'prod.fixture'),
        'development' => mysqlEnvironment('dbx_mysql_fixture_development_mysql', 'dev.fixture'),
    ]);
    $resolved = additionalResolver([$definition->code => $definition], 'windows', ['odbc'])
        ->resolveAdditional($definition->code, 'development');

    regressionAssertSame('dbx_mysql_fixture_development_mysql', $resolved['resolved_key'], 'Wrong development key.');
    regressionAssertContains('host=dev.fixture', $resolved['config']['dsn'], 'Wrong development DSN.');
});

$suite->add('default environment follows MAIN_DB_ENVIRONMENT runtime selection', static function (): void {
    $previousSession = $_SESSION['MAIN_DB_ENVIRONMENT'] ?? null;
    $hadSessionValue = array_key_exists('MAIN_DB_ENVIRONMENT', $_SESSION ?? []);
    $_SESSION['MAIN_DB_ENVIRONMENT'] = 'development';

    try {
        $definition = additionalDefinition('dbx_mysql_default_fixture', 'mysql', [
            'production' => mysqlEnvironment('dbx_mysql_default_fixture_production_mysql', 'prod.fixture'),
            'development' => mysqlEnvironment('dbx_mysql_default_fixture_development_mysql', 'dev.fixture'),
        ]);
        $resolved = additionalResolver([$definition->code => $definition])
            ->resolveAdditional($definition->code);

        regressionAssertSame(
            'dbx_mysql_default_fixture_development_mysql',
            $resolved['resolved_key'],
            'Default environment no longer follows MAIN_DB_ENVIRONMENT.'
        );
    } finally {
        if ($hadSessionValue) {
            $_SESSION['MAIN_DB_ENVIRONMENT'] = $previousSession;
        } else {
            unset($_SESSION['MAIN_DB_ENVIRONMENT']);
        }
    }
});

$suite->add('disabled additional connection is rejected', static function (): void {
    $definition = additionalDefinition(
        'dbx_disabled_fixture',
        'mysql',
        ['production' => mysqlEnvironment('dbx_disabled_fixture_production_mysql', 'disabled.fixture')],
        false
    );
    $resolver = additionalResolver([$definition->code => $definition]);

    regressionAssertThrows(
        static fn() => $resolver->resolveAdditional($definition->code, 'production'),
        RuntimeException::class,
        'is disabled'
    );
});

$suite->add('unknown additional connection is rejected', static function (): void {
    $resolver = additionalResolver([]);
    regressionAssertThrows(
        static fn() => $resolver->resolveAdditional('dbx_missing_fixture', 'production'),
        RuntimeException::class,
        'not found'
    );
});

$suite->add('missing requested environment is rejected', static function (): void {
    $definition = additionalDefinition('dbx_prod_only_fixture', 'mysql', [
        'production' => mysqlEnvironment('dbx_prod_only_fixture_production_mysql', 'prod.fixture'),
    ]);
    $resolver = additionalResolver([$definition->code => $definition]);

    regressionAssertThrows(
        static fn() => $resolver->resolveAdditional($definition->code, 'development'),
        RuntimeException::class,
        'environment not configured'
    );
});

$suite->add('registry maps active MySQL row to PDO configuration', static function (): void {
    $registry = (new DatabaseConnectionRegistry())->withAdditionalRows([[
        'f_code' => 'dbx_mysql_registry_fixture',
        'f_name' => 'MySQL Registry Fixture',
        'f_family' => 'mysql',
        'f_purpose' => 'lookup',
        'f_driver_mode' => 'auto',
        'f_is_enabled' => 1,
        'env_rows' => [[
            'f_environment' => 'production',
            'f_os_family' => 'any',
            'f_driver' => 'mysql',
            'f_host' => 'mysql.fixture',
            'f_port' => '3307',
            'f_database_name' => 'fixture_db',
            'f_username' => 'fixture_user',
            'f_password_ciphertext' => 'legacy-value',
            'f_charset' => 'utf8mb4',
            'f_is_active' => 1,
        ]],
    ]]);

    $definition = $registry->getAdditional('dbx_mysql_registry_fixture');
    regressionAssertTrue($definition instanceof DatabaseConnectionDefinition, 'Registry definition was not created.');
    $config = (new DatabaseConnectionResolver(new DatabaseRuntimeConfig(null, 'linux'), $registry, ['mysql']))
        ->resolveAdditional('dbx_mysql_registry_fixture', 'production')['config'] ?? [];
    regressionAssertSame('mysql', $config['driver'] ?? null, 'MySQL driver changed.');
    regressionAssertSame(
        'mysql:host=mysql.fixture;port=3307;dbname=fixture_db;charset=utf8mb4',
        $config['dsn'] ?? null,
        'MySQL DSN format changed.'
    );
    regressionAssertSame('legacy-value', $config['pass'] ?? null, 'Credential value was not preserved.');
});

$suite->add('registry ignores inactive environment rows', static function (): void {
    $registry = (new DatabaseConnectionRegistry())->withAdditionalRows([[
        'f_code' => 'dbx_inactive_fixture',
        'f_name' => 'Inactive Fixture',
        'f_family' => 'mysql',
        'f_is_enabled' => 1,
        'env_rows' => [[
            'f_environment' => 'production',
            'f_os_family' => 'any',
            'f_driver' => 'mysql',
            'f_host' => 'inactive.fixture',
            'f_database_name' => 'fixture_db',
            'f_username' => 'fixture_user',
            'f_is_active' => 0,
        ]],
    ]]);
    $definition = $registry->getAdditional('dbx_inactive_fixture');

    regressionAssertTrue($definition instanceof DatabaseConnectionDefinition, 'Definition should retain metadata.');
    regressionAssertSame([], $definition->environments, 'Inactive env row must not enter runtime environments.');
});

$suite->add('explicit Sybase dblib driver mode remains deterministic', static function (): void {
    $definition = additionalDefinition('dbx_sybase_fixture', 'sybase', [
        'production' => [
            'windows' => ['dsn' => variantEnvironment('dbx_sybase_fixture_production_odbc_windows', 'odbc')],
            'linux' => ['dblib' => variantEnvironment('dbx_sybase_fixture_production_dblib_linux', 'dblib')],
        ],
    ], true, 'dblib');
    $resolved = additionalResolver([$definition->code => $definition], 'linux', ['dblib'])
        ->resolveAdditional($definition->code, 'production');

    regressionAssertSame('dblib', $resolved['config']['driver'], 'Explicit Sybase dblib mode changed.');
});

$suite->add('explicit Sybase odbc driver mode remains deterministic', static function (): void {
    $definition = additionalDefinition('dbx_sybase_fixture', 'sybase', [
        'production' => [
            'windows' => ['odbc' => variantEnvironment('dbx_sybase_fixture_production_odbc_windows', 'odbc')],
            'linux' => ['dblib' => variantEnvironment('dbx_sybase_fixture_production_dblib_linux', 'dblib')],
        ],
    ], true, 'odbc');
    $resolved = additionalResolver([$definition->code => $definition], 'windows', ['odbc'])
        ->resolveAdditional($definition->code, 'production');

    regressionAssertSame('odbc', $resolved['config']['driver'], 'Explicit Sybase odbc mode changed.');
});

$suite->add('platform matrix selects Windows ODBC and Linux DBLIB without cross-OS fallback', static function (): void {
    $definition = additionalDefinition('dbx_sybase_platform_fixture', 'sybase', [
        'production' => [
            'windows' => ['odbc' => variantEnvironment('windows-odbc', 'odbc')],
            'linux' => ['dblib' => variantEnvironment('linux-dblib', 'dblib')],
        ],
    ]);

    $windows = additionalResolver([$definition->code => $definition], 'windows', ['odbc'])
        ->resolveAdditional($definition->code, 'production');
    $linux = additionalResolver([$definition->code => $definition], 'linux', ['dblib'])
        ->resolveAdditional($definition->code, 'production');

    regressionAssertSame('windows-odbc', $windows['resolved_key'], 'Windows did not select its ODBC variant.');
    regressionAssertSame('linux-dblib', $linux['resolved_key'], 'Linux did not select its DBLIB variant.');
    regressionAssertThrows(
        static fn() => additionalResolver([$definition->code => $definition], 'linux', ['odbc'])
            ->resolveAdditional($definition->code, 'production'),
        AdditionalDatabaseException::class,
        'No suitable Sybase driver variant'
    );
});

$suite->add('exact OS variant takes precedence over any variant independent of row order', static function (): void {
    $definition = additionalDefinition('dbx_sybase_precedence_fixture', 'sybase', [
        'production' => [
            'any' => ['dblib' => variantEnvironment('shared-dblib', 'dblib')],
            'linux' => ['dblib' => variantEnvironment('linux-dblib', 'dblib')],
        ],
    ]);
    $resolved = additionalResolver([$definition->code => $definition], 'linux', ['dblib'])
        ->resolveAdditional($definition->code, 'production');

    regressionAssertSame('linux-dblib', $resolved['resolved_key'], 'Shared any variant overrode exact Linux variant.');
});

$suite->add('explicit MSSQL sqlsrv driver mode remains deterministic', static function (): void {
    $definition = additionalDefinition('dbx_mssql_fixture', 'mssql', [
        'production' => [
            'windows' => ['sqlsrv' => variantEnvironment('dbx_mssql_fixture_production_sqlsrv_windows', 'sqlsrv')],
            'linux' => ['dblib' => variantEnvironment('dbx_mssql_fixture_production_dblib_linux', 'dblib')],
        ],
    ], true, 'sqlsrv');
    $resolved = additionalResolver([$definition->code => $definition], 'windows', ['sqlsrv'])
        ->resolveAdditional($definition->code, 'production');

    regressionAssertSame('sqlsrv', $resolved['config']['driver'], 'Explicit MSSQL sqlsrv mode changed.');
});

$suite->add('auto Sybase resolution returns a configured variant and fallback metadata', static function (): void {
    $definition = additionalDefinition('dbx_sybase_auto_fixture', 'sybase', [
        'production' => [
            'windows' => [
                'dsn' => variantEnvironment('dbx_sybase_auto_fixture_production_odbc_windows', 'odbc'),
                'dblib' => variantEnvironment('dbx_sybase_auto_fixture_production_dblib_windows', 'dblib'),
            ],
            'linux' => [
                'dsn' => variantEnvironment('dbx_sybase_auto_fixture_production_odbc_linux', 'odbc'),
                'dblib' => variantEnvironment('dbx_sybase_auto_fixture_production_dblib_linux', 'dblib'),
            ],
        ],
    ]);
    $resolved = additionalResolver([$definition->code => $definition], 'linux', ['dblib', 'odbc'])
        ->resolveAdditional($definition->code, 'production');

    regressionAssertTrue(
        in_array($resolved['config']['driver'] ?? '', ['odbc', 'dblib'], true),
        'Auto Sybase resolution returned an unsupported driver.'
    );
    regressionAssertTrue(isset($resolved['fallback_key']), 'Fallback metadata key must remain present.');
    regressionAssertTrue(array_key_exists('fallback_config', $resolved), 'Fallback config key must remain present.');
});

$suite->add('legacy flat dbx configuration remains resolvable', static function (): void {
    $legacyConfig = [
        'driver' => 'mysql',
        'dsn' => 'mysql:host=legacy.fixture;port=3306;dbname=legacy;charset=utf8mb4',
        'user' => 'legacy_user',
        'pass' => 'legacy_password',
    ];
    $registry = DatabaseConnectionRegistry::fromConfigArray(['dbx_legacy_fixture' => $legacyConfig]);
    $resolved = (new DatabaseConnectionResolver(new DatabaseRuntimeConfig(), $registry))
        ->resolveAdditional('dbx_legacy_fixture', 'production');

    regressionAssertSame('dbx_legacy_fixture', $resolved['resolved_key'], 'Legacy resolved key changed.');
    regressionAssertSame($legacyConfig, $resolved['config'], 'Legacy config changed during resolution.');
});

$suite->add('reserved main database code is rejected by additional payload validator', static function (): void {
    $validator = new DatabaseConnectionValidator();
    $errors = $validator->validateAdditionalPayload([
        'f_code' => 'mysql_main',
        'f_name' => 'Reserved Fixture',
        'f_family' => 'mysql',
        'f_purpose' => 'lookup',
        'f_driver_mode' => 'auto',
        'f_supports_prod' => 1,
    ], [[
        'f_environment' => 'production',
        'f_os_family' => 'any',
        'f_driver' => 'mysql',
        'f_host' => 'fixture',
        'f_database_name' => 'fixture',
        'f_username' => 'fixture',
        'f_is_active' => 1,
    ]]);

    regressionAssertTrue(
        count(array_filter($errors, static fn(string $error): bool => str_contains($error, 'dikhaskan'))) === 1,
        'Reserved code validation changed.'
    );
});

$suite->add('valid existing MySQL payload remains accepted', static function (): void {
    $validator = new DatabaseConnectionValidator();
    $errors = $validator->validateAdditionalPayload([
        'f_code' => 'dbx_mysql_valid_fixture',
        'f_name' => 'Valid Fixture',
        'f_family' => 'mysql',
        'f_purpose' => 'lookup',
        'f_driver_mode' => 'auto',
        'f_supports_prod' => 1,
        'f_supports_dev' => 0,
    ], [[
        'f_environment' => 'production',
        'f_os_family' => 'any',
        'f_driver' => 'mysql',
        'f_host' => 'fixture',
        'f_port' => '3306',
        'f_database_name' => 'fixture',
        'f_username' => 'fixture',
        'f_is_active' => 1,
    ]]);

    regressionAssertSame([], $errors, 'Previously valid MySQL payload is no longer accepted.');
});

$suite->add('legacy Encryption can still round-trip an existing-style credential', static function (): void {
    $encryption = new Encryption();
    $plainText = 'legacy-fixture-password';
    $cipherText = $encryption->encode($plainText);

    regressionAssertTrue(is_string($cipherText) && $cipherText !== '', 'Legacy encryption returned no ciphertext.');
    regressionAssertTrue($cipherText !== $plainText, 'Legacy encryption returned plaintext.');
    regressionAssertSame($plainText, $encryption->decode($cipherText), 'Legacy credential no longer decrypts.');
});

$suite->add('registry migration remains non-destructive and covers runtime columns', static function (): void {
    $migrationPath = __DIR__ . '/../docs/additional-database-registry-migration-2026-08-01.sql';
    $sql = file_get_contents($migrationPath);
    regressionAssertTrue(is_string($sql) && $sql !== '', 'Registry migration is missing or empty.');

    foreach (['tbl_m_db_connection', 'tbl_m_db_connection_env', 'tbl_a_db_connection_test'] as $table) {
        regressionAssertContains(
            "CREATE TABLE IF NOT EXISTS `{$table}`",
            $sql,
            "Migration does not create {$table} idempotently."
        );
    }

    foreach ([
        'f_code',
        'f_category',
        'f_driver_mode',
        'f_connection_code',
        'f_environment',
        'f_os_family',
        'f_driver',
        'f_password_ciphertext',
        'f_extra_json',
        'f_is_active',
        'f_last_test_status',
        'f_last_test_message',
        'f_last_tested_at',
        'f_status',
        'f_tested_by',
    ] as $column) {
        regressionAssertContains("`{$column}`", $sql, "Migration is missing runtime column {$column}.");
    }

    $sqlWithoutComments = preg_replace('/^\s*--.*$/m', '', $sql) ?? $sql;
    regressionAssertTrue(
        preg_match('/\b(DROP|TRUNCATE|DELETE\s+FROM|UPDATE\s+`?tbl_[ma]_db_connection)\b/i', $sqlWithoutComments) !== 1,
        'Registry migration contains a destructive or data-mutating statement.'
    );
});

$suite->add('read-only diagnostics detects unavailable driver and missing active environment', static function (): void {
    $diagnostics = (new DatabaseConnectionDiagnostics())->analyze([
        [
            'f_code' => 'dbx_driver_fixture',
            'f_is_enabled' => 1,
            'f_supports_prod' => 1,
            'env_rows' => [[
                'f_environment' => 'production',
                'f_os_family' => 'linux',
                'f_driver' => 'sqlsrv',
                'f_is_active' => 1,
                'f_has_password' => true,
                'f_credential_format' => 'legacy',
            ]],
        ],
        [
            'f_code' => 'dbx_empty_fixture',
            'f_is_enabled' => 1,
            'env_rows' => [],
        ],
    ], ['mysql', 'dblib'], 'linux');

    regressionAssertSame('attention', $diagnostics['status'], 'Diagnostics status changed.');
    regressionAssertSame(2, $diagnostics['enabled_count'], 'Enabled count changed.');
    regressionAssertSame(1, $diagnostics['active_env_count'], 'Active env count changed.');
    regressionAssertSame(1, $diagnostics['legacy_credential_count'], 'Legacy credential count changed.');
    regressionAssertTrue($diagnostics['error_count'] >= 2, 'Expected driver and env-row errors were not detected.');
    regressionAssertSame(false, $diagnostics['live_connection_tested'], 'Diagnostics must remain read-only.');
});

$suite->add('read-only diagnostics detects same production and development target', static function (): void {
    $row = [
        'f_os_family' => 'any',
        'f_driver' => 'mysql',
        'f_host' => 'same.fixture',
        'f_port' => '3306',
        'f_database_name' => 'same_db',
        'f_is_active' => 1,
        'f_has_password' => true,
        'f_credential_format' => 'v2',
    ];
    $diagnostics = (new DatabaseConnectionDiagnostics())->analyze([[
        'f_code' => 'dbx_same_target_fixture',
        'f_is_enabled' => 1,
        'f_supports_prod' => 1,
        'f_supports_dev' => 1,
        'env_rows' => [
            ['f_environment' => 'production'] + $row,
            ['f_environment' => 'development'] + $row,
        ],
    ]], ['mysql'], 'linux');

    regressionAssertSame('warning', $diagnostics['status'], 'Same-target warning status changed.');
    regressionAssertSame(1, $diagnostics['warning_count'], 'Same-target warning was not detected exactly once.');
});

$suite->add('credential cipher writes and reads authenticated v2 envelope', static function (): void {
    $key = base64_encode(str_repeat('K', SODIUM_CRYPTO_SECRETBOX_KEYBYTES));
    $cipher = new DatabaseCredentialCipher($key, 'test-key');
    $stored = $cipher->encrypt('fixture-password');

    regressionAssertTrue(str_starts_with($stored, 'v2:test-key:'), 'Credential was not written as v2.');
    regressionAssertSame('fixture-password', $cipher->decrypt($stored), 'Credential v2 round-trip failed.');
    regressionAssertSame('v2', $cipher->getWriteFormat(), 'Configured cipher must report v2 writes.');
});

$suite->add('credential cipher rejects tampered v2 ciphertext without plaintext fallback', static function (): void {
    $key = base64_encode(str_repeat('T', SODIUM_CRYPTO_SECRETBOX_KEYBYTES));
    $cipher = new DatabaseCredentialCipher($key, 'tamper-key');
    $stored = $cipher->encrypt('fixture-password');
    $parts = explode(':', $stored, 4);
    $encodedCiphertext = strtr($parts[3], '-_', '+/');
    $encodedCiphertext .= str_repeat('=', (4 - strlen($encodedCiphertext) % 4) % 4);
    $ciphertext = base64_decode($encodedCiphertext, true);
    regressionAssertTrue(is_string($ciphertext) && $ciphertext !== '', 'Fixture ciphertext is invalid.');
    $ciphertext[0] = chr(ord($ciphertext[0]) ^ 1);
    $parts[3] = rtrim(strtr(base64_encode($ciphertext), '+/', '-_'), '=');
    $tampered = implode(':', $parts);

    regressionAssertThrows(
        static fn() => $cipher->decrypt($tampered),
        RuntimeException::class,
        'gagal didekripsi'
    );
});

$suite->add('credential cipher retains legacy read and write when v2 key is absent', static function (): void {
    $cipher = new DatabaseCredentialCipher('', 'primary');
    $stored = $cipher->encrypt('legacy-fixture-password');

    regressionAssertTrue(!str_starts_with($stored, 'v2:'), 'Unconfigured cipher must retain legacy write compatibility.');
    regressionAssertSame('legacy-fixture-password', $cipher->decrypt($stored), 'Legacy dual-read failed.');
    regressionAssertSame('legacy', $cipher->getWriteFormat(), 'Unconfigured cipher must report legacy writes.');
});

$suite->add('credential cipher rejects v2 envelope when key is absent', static function (): void {
    $key = base64_encode(str_repeat('M', SODIUM_CRYPTO_SECRETBOX_KEYBYTES));
    $stored = (new DatabaseCredentialCipher($key, 'missing-key'))->encrypt('fixture-password');
    $cipherWithoutKey = new DatabaseCredentialCipher('', 'missing-key');

    regressionAssertThrows(
        static fn() => $cipherWithoutKey->decrypt($stored),
        RuntimeException::class,
        DatabaseCredentialCipher::ENV_KEY
    );
});

$suite->add('diagnostics reports invalid v2 key without breaking settings snapshot', static function (): void {
    $previous = $_ENV[DatabaseCredentialCipher::ENV_KEY] ?? null;
    $hadPrevious = array_key_exists(DatabaseCredentialCipher::ENV_KEY, $_ENV);
    $_ENV[DatabaseCredentialCipher::ENV_KEY] = 'invalid-base64-key';

    try {
        $diagnostics = (new DatabaseConnectionDiagnostics())->analyze([], ['mysql'], 'linux');
        regressionAssertSame('attention', $diagnostics['status'], 'Invalid key must produce attention status.');
        regressionAssertSame('unavailable', $diagnostics['credential_write_format'], 'Invalid key format was not isolated.');
        regressionAssertSame(1, $diagnostics['error_count'], 'Invalid key must produce one diagnostic error.');
    } finally {
        if ($hadPrevious) {
            $_ENV[DatabaseCredentialCipher::ENV_KEY] = $previous;
        } else {
            unset($_ENV[DatabaseCredentialCipher::ENV_KEY]);
        }
    }
});

$suite->add('credential migration tool retains mandatory safety guards', static function (): void {
    $toolPath = __DIR__ . '/../tools/additional-database-credential-migrate.php';
    $source = file_get_contents($toolPath);
    regressionAssertTrue(is_string($source) && $source !== '', 'Credential migration tool is missing.');

    foreach ([
        "'apply' => isset(\$options['apply'])",
        'DB_CREDENTIAL_MIGRATION_CONFIRM',
        '--backup-dir wajib apabila --apply digunakan.',
        'Production runtime ditolak.',
        'beginTransaction()',
        'rollBack()',
        "\$pdo->query('SELECT 1')",
        'f_password_ciphertext = :legacy_ciphertext',
        'Dry-run selesai. Tiada database row diubah',
    ] as $guard) {
        regressionAssertContains($guard, $source, 'Migration safety guard changed.');
    }
});

$suite->add('data preview sanitizer masks secrets and protects large or binary values', static function (): void {
    $sanitized = (new DatabasePreviewSanitizer())->sanitize([[
        'user_id' => 10,
        'username' => 'fixture-user',
        'password_hash' => 'must-not-leak',
        'api_token' => 'must-not-leak-either',
        'description' => str_repeat('x', DatabasePreviewSanitizer::MAX_STRING_LENGTH + 25),
        'payload' => "binary\0value",
        'nullable_secret' => null,
    ]]);
    $row = $sanitized['rows'][0];

    regressionAssertSame(10, $row['user_id'], 'Scalar preview value changed.');
    regressionAssertSame('fixture-user', $row['username'], 'Safe preview value changed.');
    regressionAssertSame(DatabasePreviewSanitizer::MASKED_VALUE, $row['password_hash'], 'Password column was not masked.');
    regressionAssertSame(DatabasePreviewSanitizer::MASKED_VALUE, $row['api_token'], 'Token column was not masked.');
    regressionAssertSame(null, $row['nullable_secret'], 'Null secret must remain null.');
    regressionAssertContains('[TRUNCATED', $row['description'], 'Long value was not truncated.');
    regressionAssertSame('[BINARY 12 bytes]', $row['payload'], 'Binary value was not summarized.');
    regressionAssertSame(['api_token', 'nullable_secret', 'password_hash'], $sanitized['masked_columns'], 'Masked column metadata changed.');
    regressionAssertSame(1, $sanitized['truncated_values'], 'Truncation count changed.');
    regressionAssertSame(1, $sanitized['binary_values'], 'Binary count changed.');
});

$suite->add('additional database exceptions preserve downstream RuntimeException contract and messages', static function (): void {
    $disabled = additionalDefinition('dbx_disabled_fixture', 'mysql', [
        'production' => mysqlEnvironment('dbx_disabled_fixture_production_mysql', 'fixture.local'),
    ], false);

    try {
        additionalResolver([$disabled->code => $disabled])->resolveAdditional($disabled->code, 'production');
        throw new RuntimeException('Expected disabled connection exception was not thrown.');
    } catch (AdditionalDatabaseException $error) {
        regressionAssertTrue($error instanceof RuntimeException, 'Exception no longer extends RuntimeException.');
        regressionAssertSame('disabled', $error->category, 'Exception category changed.');
        regressionAssertSame(
            'Additional database connection is disabled: dbx_disabled_fixture',
            $error->getMessage(),
            'Existing disabled-connection message changed.'
        );
    }

    regressionAssertThrows(
        static fn() => additionalResolver([])->resolveAdditional('dbx_missing_fixture'),
        AdditionalDatabaseException::class,
        'Additional database connection not found: dbx_missing_fixture'
    );
});

$suite->add('database error redactor removes credentials while retaining diagnostic context', static function (): void {
    $message = implode(' ', [
        'driver=mysql host=db.fixture.local',
        'password=super-secret;',
        'PWD=db-secret;',
        'uri=https://fixture-user:uri-secret@db.fixture.local/path',
        'DB_ADDITIONAL_CREDENTIAL_KEY=base64-secret',
        'credential=v2:key-2026:bm9uY2U=:Y2lwaGVydGV4dA==',
        '{"token":"json-secret"}',
    ]);
    $redacted = DatabaseErrorRedactor::redact($message);

    foreach (['super-secret', 'db-secret', 'uri-secret', 'base64-secret', 'key-2026', 'json-secret'] as $secret) {
        regressionAssertTrue(!str_contains($redacted, $secret), "Redactor leaked {$secret}.");
    }
    regressionAssertContains('driver=mysql', $redacted, 'Driver context was removed.');
    regressionAssertContains('db.fixture.local', $redacted, 'Host context was removed.');
    regressionAssertContains(DatabaseErrorRedactor::REDACTED, $redacted, 'Redaction marker is missing.');
    regressionAssertContains('[REDACTED_CREDENTIAL_V2]', $redacted, 'V2 envelope marker is missing.');
});

$suite->add('database error classifier provides stable audit categories without wrapping exceptions', static function (): void {
    $categorised = AdditionalDatabaseException::notFound('dbx_missing_fixture');

    regressionAssertSame('not_found', DatabaseErrorClassifier::classify($categorised), 'Additional category changed.');
    regressionAssertSame('connection_failed', DatabaseErrorClassifier::classify(new PDOException('fixture')), 'PDO category changed.');
    regressionAssertSame('validation_failed', DatabaseErrorClassifier::classify(new InvalidArgumentException('fixture')), 'Validation category changed.');
    regressionAssertSame('runtime_failed', DatabaseErrorClassifier::classify(new RuntimeException('fixture')), 'Runtime category changed.');
    regressionAssertSame('unexpected_failure', DatabaseErrorClassifier::classify(new Exception('fixture')), 'Fallback category changed.');
    regressionAssertTrue($categorised instanceof RuntimeException, 'Classifier work changed the downstream exception base type.');
});

$suite->add('validator accepts OS-specific MySQL variants and rejects ambiguous any overlap', static function (): void {
    $validator = new DatabaseConnectionValidator();
    $payload = [
        'f_code' => 'dbx_mysql_platform_fixture', 'f_name' => 'Fixture', 'f_family' => 'mysql',
        'f_purpose' => 'test', 'f_driver_mode' => 'auto', 'f_supports_prod' => 0, 'f_supports_dev' => 1,
    ];
    $linux = [
        'f_environment' => 'development', 'f_os_family' => 'linux', 'f_driver' => 'mysql',
        'f_host' => 'linux.fixture', 'f_port' => '3306', 'f_database_name' => 'fixture',
        'f_username' => 'fixture', 'f_is_active' => 1,
    ];
    regressionAssertSame([], $validator->validateAdditionalPayload($payload, [$linux]), 'OS-specific MySQL was rejected.');

    $any = $linux;
    $any['f_os_family'] = 'any';
    $errors = $validator->validateAdditionalPayload($payload, [$linux, $any]);
    regressionAssertTrue(
        count(array_filter($errors, static fn(string $error): bool => str_contains($error, 'tidak boleh mencampurkan'))) === 1,
        'Ambiguous any + OS-specific overlap was not rejected.'
    );
});

$suite->add('diagnostics separates current runtime health from cross-platform coverage', static function (): void {
    $diagnostics = (new DatabaseConnectionDiagnostics())->analyze([[
        'f_code' => 'dbx_platform_health_fixture',
        'f_is_enabled' => 1,
        'f_supports_prod' => 1,
        'env_rows' => [
            ['f_environment' => 'production', 'f_os_family' => 'windows', 'f_driver' => 'sqlsrv', 'f_is_active' => 1, 'f_has_password' => 1, 'f_credential_format' => 'v2'],
            ['f_environment' => 'production', 'f_os_family' => 'linux', 'f_driver' => 'dblib', 'f_is_active' => 1, 'f_has_password' => 1, 'f_credential_format' => 'v2'],
        ],
    ]], ['dblib'], 'linux');

    regressionAssertSame('healthy', $diagnostics['runtime_health']['status'], 'Windows-only missing driver polluted Linux health.');
    regressionAssertSame(2, $diagnostics['platform_coverage']['available_count'], 'Platform coverage count changed.');
    regressionAssertSame(0, $diagnostics['platform_coverage']['missing_count'], 'Complete platform matrix reported missing coverage.');
});

$suite->add('platform migration tooling remains dry-run by default with controlled apply guards', static function (): void {
    $auditSource = file_get_contents(__DIR__ . '/../tools/additional-database-platform-audit.php');
    $migrationSource = file_get_contents(__DIR__ . '/../tools/additional-database-platform-migrate.php');
    regressionAssertTrue(is_string($auditSource) && is_string($migrationSource), 'Platform tools are missing.');
    foreach ([
        "if (!\$apply)", '--windows-dsn wajib untuk apply.', '--backup-dir wajib untuk apply.',
        '--accept-windows-test-pending wajib', 'DB_PLATFORM_MIGRATION_CONFIRM', 'beginTransaction()',
        'rollBack()', "->query('SELECT 1')", 'PENDING: jalankan Windows ODBC SELECT 1',
    ] as $guard) {
        regressionAssertContains($guard, $migrationSource, 'Platform migration safety guard changed.');
    }
    regressionAssertContains('READ-ONLY', $auditSource, 'Platform audit no longer declares read-only behavior.');
});

$suite->add('controller and repository retain exact platform tuple and concurrency guards', static function (): void {
    $controllerSource = file_get_contents(__DIR__ . '/../public/controllers/TetapanSistemController.php');
    $repositorySource = file_get_contents(__DIR__ . '/../public/classes/DatabaseConnectionRepository.php');
    $frontendSource = file_get_contents(__DIR__ . '/../public/assets/js/pages/tetapan-sistem.js');
    regressionAssertTrue(
        is_string($controllerSource) && is_string($repositorySource) && is_string($frontendSource),
        'Platform runtime sources are missing.'
    );
    regressionAssertContains("!in_array(\$rowOsFamily, [\$targetOsFamily, 'any'], true)", $controllerSource, 'Controller OS guard changed.');
    regressionAssertContains("\$targetOsFamily = PHP_OS_FAMILY === 'Windows' ? 'windows' : 'linux';", $controllerSource, 'Controller no longer enforces the server runtime OS.');
    regressionAssertContains("\$targetDriver = '';", $controllerSource, 'Controller no longer resolves the runtime driver server-side.');
    foreach (['inspectEnv', 'schemaEnv', 'firstEnv'] as $targetVariable) {
        regressionAssertContains(
            "var {$targetVariable} = getPreferredAdditionalEnvRow(connection);",
            $frontendSource,
            "Frontend {$targetVariable} selection no longer follows runtime diagnostics."
        );
    }
    regressionAssertContains('f_registry_revision', $controllerSource, 'Controller concurrency token is missing.');
    regressionAssertContains('AND f_os_family = :os_family', $repositorySource, 'Test result OS tuple is not exact.');
    regressionAssertContains('AND f_driver = :driver', $repositorySource, 'Test result driver tuple is not exact.');
    regressionAssertContains('hash_equals($currentRevision', $repositorySource, 'Optimistic concurrency comparison is missing.');
});

exit($suite->run());
