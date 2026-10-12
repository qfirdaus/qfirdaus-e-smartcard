<?php
/**
 * Controlled migration of Additional Database credentials from legacy to v2.
 *
 * Dry-run is the default. This tool never prints plaintext or ciphertext.
 */
declare(strict_types=1);

require_once __DIR__ . '/../public/classes/DatabaseCredentialCipher.php';
require_once __DIR__ . '/../public/classes/DatabaseConnectionDefinition.php';
require_once __DIR__ . '/../public/classes/DatabaseConnectionRegistry.php';
require_once __DIR__ . '/../public/classes/DatabaseRuntimeConfig.php';
require_once __DIR__ . '/../public/classes/DatabaseConnectionResolver.php';
require_once __DIR__ . '/../public/classes/DatabaseConnectionFactory.php';

const DB_CREDENTIAL_MIGRATION_CONFIRM = 'MIGRATE-ADDITIONAL-DB-CREDENTIALS-V2';

function migrationOutput(string $level, string $message): void
{
    fwrite($level === 'ERROR' ? STDERR : STDOUT, "[{$level}] {$message}\n");
}

function migrationFail(string $message, int $exitCode = 1): never
{
    migrationOutput('ERROR', $message);
    exit($exitCode);
}

function migrationHelp(): never
{
    fwrite(STDOUT, <<<'HELP'
Additional Database credential migration tool

Dry-run one connection:
  php tools/additional-database-credential-migrate.php --code=dbx_example

Dry-run all connections:
  php tools/additional-database-credential-migrate.php --all

Apply one connection after reviewing dry-run:
  php tools/additional-database-credential-migrate.php \
    --code=dbx_example \
    --apply \
    --confirm=MIGRATE-ADDITIONAL-DB-CREDENTIALS-V2 \
    --backup-dir=/secure/path

Options:
  --code=CODE             Limit migration to one connection code.
  --all                   Select every additional connection.
  --environment=ENV       Optional production/development row filter.
  --apply                 Perform transaction updates; default is dry-run.
  --confirm=TOKEN         Required with --apply.
  --backup-dir=PATH       Required with --apply; ciphertext backup is chmod 0600.
  --allow-production      Required if APP_ENV or MAIN_DB_ENVIRONMENT is production.
  --skip-connection-test  Bypass live SELECT 1; requires a stronger confirm token:
                          MIGRATE-ADDITIONAL-DB-CREDENTIALS-V2-WITHOUT-TEST
  --help                  Show this help.

Required process environment:
  DB_ADDITIONAL_CREDENTIAL_KEY       Base64-encoded 32-byte key.
  DB_ADDITIONAL_CREDENTIAL_KEY_ID    Key identifier, for example staging-2026.

The tool does not modify connection metadata and never deletes registry rows.
HELP
    );
    exit(0);
}

/** @return array<string, mixed> */
function migrationOptions(): array
{
    $options = getopt('', [
        'code:', 'all', 'environment:', 'apply', 'confirm:', 'backup-dir:',
        'allow-production', 'skip-connection-test', 'help',
    ]);
    if (isset($options['help'])) {
        migrationHelp();
    }

    $code = strtolower(trim((string)($options['code'] ?? '')));
    $all = isset($options['all']);
    if (($code === '' && !$all) || ($code !== '' && $all)) {
        migrationFail('Pilih tepat satu scope: --code=CODE atau --all.', 2);
    }
    if ($code !== '' && preg_match('/^[a-z][a-z0-9_]{2,99}$/', $code) !== 1) {
        migrationFail('Connection code tidak sah.', 2);
    }

    $environment = strtolower(trim((string)($options['environment'] ?? '')));
    if ($environment !== '' && !in_array($environment, ['production', 'development'], true)) {
        migrationFail('Environment mesti production atau development.', 2);
    }

    return [
        'code' => $code,
        'all' => $all,
        'environment' => $environment,
        'apply' => isset($options['apply']),
        'confirm' => (string)($options['confirm'] ?? ''),
        'backup_dir' => trim((string)($options['backup-dir'] ?? '')),
        'allow_production' => isset($options['allow-production']),
        'skip_connection_test' => isset($options['skip-connection-test']),
    ];
}

function migrationEnv(string $name): ?string
{
    $value = $_ENV[$name] ?? $_SERVER[$name] ?? getenv($name);
    return is_string($value) && trim($value) !== '' ? trim($value) : null;
}

/** @return array<int, array<string, mixed>> */
function migrationRows(PDO $pdo, array $options): array
{
    $where = ["c.f_category = 'additional'", 'e.f_password_ciphertext IS NOT NULL', "e.f_password_ciphertext <> ''"];
    $params = [];
    if ($options['code'] !== '') {
        $where[] = 'c.f_code = :code';
        $params[':code'] = $options['code'];
    }
    if ($options['environment'] !== '') {
        $where[] = 'e.f_environment = :environment';
        $params[':environment'] = $options['environment'];
    }

    $sql = "
        SELECT c.f_code, c.f_name, c.f_family, c.f_purpose, c.f_driver_mode,
               c.f_is_enabled, c.f_supports_prod, c.f_supports_dev,
               e.f_environment, e.f_os_family, e.f_driver, e.f_host, e.f_port,
               e.f_database_name, e.f_dsn_name, e.f_username,
               e.f_password_ciphertext, e.f_charset, e.f_extra_json, e.f_is_active
        FROM tbl_m_db_connection c
        INNER JOIN tbl_m_db_connection_env e ON e.f_connection_code = c.f_code
        WHERE " . implode(' AND ', $where) . "
        ORDER BY c.f_code, e.f_environment, e.f_os_family, e.f_driver
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

function migrationBackup(array $rows, string $directory): string
{
    if ($directory === '') {
        migrationFail('--backup-dir wajib apabila --apply digunakan.', 2);
    }
    if (!is_dir($directory) && !mkdir($directory, 0700, true) && !is_dir($directory)) {
        migrationFail('Backup directory gagal dicipta.');
    }
    if (!is_writable($directory)) {
        migrationFail('Backup directory tidak boleh ditulis.');
    }

    $backupRows = array_map(static fn(array $row): array => [
        'connection_code' => (string)$row['f_code'],
        'environment' => (string)$row['f_environment'],
        'os_family' => (string)$row['f_os_family'],
        'driver' => (string)$row['f_driver'],
        'ciphertext' => (string)$row['f_password_ciphertext'],
    ], $rows);
    $path = rtrim($directory, DIRECTORY_SEPARATOR)
        . DIRECTORY_SEPARATOR . 'additional-db-credential-backup-' . gmdate('Ymd-His') . '-' . bin2hex(random_bytes(4)) . '.json';
    $json = json_encode([
        'created_at_utc' => gmdate(DATE_ATOM),
        'row_count' => count($backupRows),
        'rows' => $backupRows,
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    if (!is_string($json) || file_put_contents($path, $json, LOCK_EX) === false) {
        migrationFail('Ciphertext backup gagal ditulis.');
    }
    chmod($path, 0600);
    return $path;
}

function migrationTestConnection(array $row, string $plainText): void
{
    $connectionRow = [
        'f_code' => $row['f_code'],
        'f_name' => $row['f_name'],
        'f_family' => $row['f_family'],
        'f_purpose' => $row['f_purpose'],
        'f_driver_mode' => $row['f_driver'],
        'f_is_enabled' => 1,
        'env_rows' => [[
            'f_environment' => $row['f_environment'],
            'f_os_family' => $row['f_os_family'],
            'f_driver' => $row['f_driver'],
            'f_host' => $row['f_host'],
            'f_port' => $row['f_port'],
            'f_database_name' => $row['f_database_name'],
            'f_dsn_name' => $row['f_dsn_name'],
            'f_username' => $row['f_username'],
            'f_password_ciphertext' => $plainText,
            'f_charset' => $row['f_charset'],
            'f_extra_json' => $row['f_extra_json'],
            'f_is_active' => 1,
        ]],
    ];
    $registry = (new DatabaseConnectionRegistry())->withAdditionalRows([$connectionRow]);
    $resolver = new DatabaseConnectionResolver(new DatabaseRuntimeConfig(), $registry);
    $resolved = $resolver->resolveAdditional((string)$row['f_code'], (string)$row['f_environment']);
    $pdo = (new DatabaseConnectionFactory())->make((array)$resolved['config']);
    $pdo->query('SELECT 1');
}

$options = migrationOptions();
$configs = require __DIR__ . '/../public/configuration/db_config.php';
$appEnvironment = strtolower(trim((string)(migrationEnv('APP_ENV') ?? '')));
$mainEnvironment = strtolower(trim((string)(migrationEnv('MAIN_DB_ENVIRONMENT') ?? '')));
$isProduction = $appEnvironment === 'production' || $mainEnvironment === 'production';
if ($isProduction && !$options['allow_production']) {
    migrationFail('Production runtime ditolak. Gunakan --allow-production hanya selepas change approval.', 2);
}

$requiredConfirmation = $options['skip_connection_test']
    ? DB_CREDENTIAL_MIGRATION_CONFIRM . '-WITHOUT-TEST'
    : DB_CREDENTIAL_MIGRATION_CONFIRM;
if ($options['apply'] && !hash_equals($requiredConfirmation, $options['confirm'])) {
    migrationFail("Confirmation token tidak sah. Diperlukan: {$requiredConfirmation}", 2);
}

try {
    $cipher = new DatabaseCredentialCipher(
        migrationEnv(DatabaseCredentialCipher::ENV_KEY),
        migrationEnv(DatabaseCredentialCipher::ENV_KEY_ID)
    );
    if (!$cipher->isV2Configured()) {
        migrationFail('V2 credential key wajib untuk dry-run dan apply.', 2);
    }

    $configKey = $mainEnvironment === 'development' ? 'mysql_dev' : 'mysql_prod';
    $config = $configs[$configKey] ?? $configs['mysql'] ?? null;
    if (!is_array($config)) {
        migrationFail("Main database config tidak tersedia: {$configKey}.");
    }
    $pdo = new PDO(
        (string)$config['dsn'],
        $config['user'] ?? null,
        $config['pass'] ?? null,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
    );
    $rows = migrationRows($pdo, $options);
    if ($rows === []) {
        migrationOutput('INFO', 'Tiada credential row ditemui untuk scope ini.');
        exit(0);
    }

    $legacyRows = array_values(array_filter(
        $rows,
        static fn(array $row): bool => !str_starts_with((string)$row['f_password_ciphertext'], 'v2:')
    ));
    $v2Count = count($rows) - count($legacyRows);
    migrationOutput('INFO', 'Mode: ' . ($options['apply'] ? 'APPLY' : 'DRY-RUN'));
    migrationOutput('INFO', 'Selected rows: ' . count($rows));
    migrationOutput('INFO', 'Legacy rows eligible: ' . count($legacyRows));
    migrationOutput('INFO', 'Already v2 rows skipped: ' . $v2Count);

    $prepared = [];
    foreach ($legacyRows as $row) {
        $plainText = $cipher->decrypt((string)$row['f_password_ciphertext']);
        $v2 = $cipher->encrypt($plainText);
        if ($cipher->decrypt($v2) !== $plainText) {
            throw new RuntimeException('V2 self-verification gagal untuk ' . $row['f_code'] . '.');
        }
        $prepared[] = ['row' => $row, 'plain' => $plainText, 'v2' => $v2];
        migrationOutput('READY', implode(' / ', [
            (string)$row['f_code'], (string)$row['f_environment'],
            (string)$row['f_os_family'], (string)$row['f_driver'],
        ]));
    }

    if (!$options['apply']) {
        migrationOutput('INFO', 'Dry-run selesai. Tiada database row diubah dan tiada backup ditulis.');
        exit(0);
    }
    if ($prepared === []) {
        migrationOutput('INFO', 'Semua selected credential sudah v2. Tiada perubahan diperlukan.');
        exit(0);
    }

    $backupPath = migrationBackup($legacyRows, $options['backup_dir']);
    migrationOutput('INFO', 'Ciphertext backup ditulis dengan permission 0600: ' . $backupPath);

    $pdo->beginTransaction();
    $update = $pdo->prepare("
        UPDATE tbl_m_db_connection_env
        SET f_password_ciphertext = :ciphertext
        WHERE f_connection_code = :code
          AND f_environment = :environment
          AND f_os_family = :os_family
          AND f_driver = :driver
          AND f_password_ciphertext = :legacy_ciphertext
    ");

    foreach ($prepared as $item) {
        $row = $item['row'];
        if (!$options['skip_connection_test']) {
            migrationTestConnection($row, $item['plain']);
            migrationOutput('TEST', $row['f_code'] . ' connection passed');
        }
        $update->execute([
            ':ciphertext' => $item['v2'],
            ':code' => $row['f_code'],
            ':environment' => $row['f_environment'],
            ':os_family' => $row['f_os_family'],
            ':driver' => $row['f_driver'],
            ':legacy_ciphertext' => $row['f_password_ciphertext'],
        ]);
        if ($update->rowCount() !== 1) {
            throw new RuntimeException('Concurrent change detected; row tidak dikemas kini untuk ' . $row['f_code'] . '.');
        }
    }

    $pdo->commit();
    migrationOutput('SUCCESS', count($prepared) . ' credential row berjaya dimigrasikan ke v2.');
    exit(0);
} catch (Throwable $error) {
    if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) {
        $pdo->rollBack();
        migrationOutput('ROLLBACK', 'Semua update dalam transaction telah dibatalkan.');
    }
    migrationFail($error->getMessage());
}
