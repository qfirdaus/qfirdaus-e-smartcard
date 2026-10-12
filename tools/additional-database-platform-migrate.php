<?php
/**
 * Controlled Sybase platform-variant migration.
 *
 * Dry-run is the default and never changes the database.
 * Apply requires a Windows DSN, backup directory, confirmation token and an
 * explicit acknowledgement that the Windows smoke test must run on Windows.
 */
declare(strict_types=1);

require_once __DIR__ . '/../public/classes/DatabaseCredentialCipher.php';
require_once __DIR__ . '/../public/classes/DatabaseConnectionFactory.php';
require_once __DIR__ . '/../public/classes/DatabaseErrorRedactor.php';
$configs = require __DIR__ . '/../public/configuration/db_config.php';

$options = getopt('', [
    'code:', 'windows-dsn:', 'apply', 'backup-dir:', 'accept-windows-test-pending',
]);
$code = strtolower(trim((string)($options['code'] ?? '')));
$windowsDsn = trim((string)($options['windows-dsn'] ?? ''));
$apply = isset($options['apply']);
$backupDir = trim((string)($options['backup-dir'] ?? ''));
$acceptWindowsPending = isset($options['accept-windows-test-pending']);

function platformMigrationFail(string $message): never
{
    fwrite(STDERR, '[FAIL] ' . DatabaseErrorRedactor::redact($message) . "\n");
    exit(1);
}

$appEnv = strtolower(trim((string)($_ENV['APP_ENV'] ?? getenv('APP_ENV') ?: '')));
$mainEnv = strtolower(trim((string)($_ENV['MAIN_DB_ENVIRONMENT'] ?? getenv('MAIN_DB_ENVIRONMENT') ?: '')));
if ($appEnv !== 'development' || $mainEnv !== 'development') {
    platformMigrationFail('Migration hanya dibenarkan pada development runtime.');
}
if ($code === '' || preg_match('/^dbx_[a-z0-9_]+$/', $code) !== 1) {
    platformMigrationFail('--code yang tepat wajib disediakan.');
}

$pdo = null;
try {
    $config = $configs['mysql_dev'] ?? null;
    if (!is_array($config)) {
        throw new RuntimeException('Konfigurasi mysql_dev tidak tersedia.');
    }
    $pdo = new PDO(
        (string)$config['dsn'],
        $config['user'] ?? null,
        $config['pass'] ?? null,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
    );

    $masterStmt = $pdo->prepare("SELECT * FROM tbl_m_db_connection WHERE f_code = :code AND f_category = 'additional' AND f_family = 'sybase'");
    $masterStmt->execute([':code' => $code]);
    $master = $masterStmt->fetch();
    if (!is_array($master)) {
        throw new RuntimeException('Sybase additional connection tidak ditemui.');
    }
    $rowsStmt = $pdo->prepare('SELECT * FROM tbl_m_db_connection_env WHERE f_connection_code = :code ORDER BY f_environment, f_os_family, f_driver');
    $rowsStmt->execute([':code' => $code]);
    $rows = $rowsStmt->fetchAll();
    $primaryKeyStmt = $pdo->query("SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'tbl_m_db_connection_env' AND COLUMN_KEY = 'PRI' LIMIT 1");
    $primaryKey = (string)$primaryKeyStmt->fetchColumn();
    if (!in_array($primaryKey, ['id', 'f_db_connection_env_id'], true)) {
        throw new RuntimeException('Primary key tbl_m_db_connection_env tidak disokong oleh migration tool.');
    }

    $source = null;
    $windowsExisting = null;
    foreach ($rows as $row) {
        if (($row['f_environment'] ?? '') !== 'production' || empty($row['f_is_active'])) {
            continue;
        }
        if (($row['f_driver'] ?? '') === 'dblib' && in_array(($row['f_os_family'] ?? ''), ['any', 'linux'], true)) {
            $source = $row;
        }
        if (($row['f_driver'] ?? '') === 'odbc' && ($row['f_os_family'] ?? '') === 'windows') {
            $windowsExisting = $row;
        }
    }
    if (!is_array($source)) {
        throw new RuntimeException('Active production DBLIB source row untuk any/linux tidak ditemui.');
    }

    fwrite(STDOUT, "Platform migration plan for {$code}\n");
    fwrite(STDOUT, "  Linux : production / linux / dblib (reuse existing host/database/credential)\n");
    fwrite(STDOUT, "  Windows: production / windows / odbc (" . ($windowsExisting ? 'update existing variant' : 'insert new variant') . ")\n");
    fwrite(STDOUT, "  Driver mode: auto\n");
    fwrite(STDOUT, '  Windows DSN: ' . ($windowsDsn !== '' ? 'provided (value hidden)' : 'MISSING') . "\n");

    if (!$apply) {
        fwrite(STDOUT, "\nDry-run selesai. Tiada database row diubah.\n");
        if ($windowsDsn === '') {
            fwrite(STDOUT, "Apply blocked sehingga --windows-dsn disediakan dan disahkan pada runtime Windows.\n");
        }
        exit(0);
    }

    if ($windowsDsn === '') {
        throw new RuntimeException('--windows-dsn wajib untuk apply.');
    }
    if ($backupDir === '') {
        throw new RuntimeException('--backup-dir wajib untuk apply.');
    }
    if (!$acceptWindowsPending) {
        throw new RuntimeException('--accept-windows-test-pending wajib kerana ODBC tidak boleh disahkan dari Linux.');
    }
    $expectedConfirm = 'APPLY_PLATFORM_VARIANTS:' . $code;
    $actualConfirm = (string)($_ENV['DB_PLATFORM_MIGRATION_CONFIRM'] ?? getenv('DB_PLATFORM_MIGRATION_CONFIRM') ?: '');
    if (!hash_equals($expectedConfirm, $actualConfirm)) {
        throw new RuntimeException("DB_PLATFORM_MIGRATION_CONFIRM mesti tepat: {$expectedConfirm}");
    }
    if (!is_dir($backupDir) && !mkdir($backupDir, 0700, true) && !is_dir($backupDir)) {
        throw new RuntimeException('Backup directory gagal diwujudkan.');
    }
    if (!is_writable($backupDir)) {
        throw new RuntimeException('Backup directory tidak boleh ditulis.');
    }

    $backupPath = rtrim($backupDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR
        . 'additional-db-platform-backup-' . date('Ymd-His') . '-' . bin2hex(random_bytes(4)) . '.json';
    $backupPayload = json_encode(['master' => $master, 'env_rows' => $rows], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    if (!is_string($backupPayload) || file_put_contents($backupPath, $backupPayload, LOCK_EX) === false) {
        throw new RuntimeException('Ciphertext backup gagal ditulis.');
    }
    chmod($backupPath, 0600);

    $pdo->beginTransaction();
    $lock = $pdo->prepare('SELECT f_code FROM tbl_m_db_connection WHERE f_code = :code FOR UPDATE');
    $lock->execute([':code' => $code]);
    $lockRows = $pdo->prepare('SELECT f_connection_code FROM tbl_m_db_connection_env WHERE f_connection_code = :code FOR UPDATE');
    $lockRows->execute([':code' => $code]);

    if (($source['f_os_family'] ?? '') === 'any') {
        $linux = $pdo->prepare("UPDATE tbl_m_db_connection_env SET f_os_family = 'linux' WHERE `{$primaryKey}` = :id");
        $linux->execute([':id' => $source[$primaryKey]]);
    }

    if (is_array($windowsExisting)) {
        $windows = $pdo->prepare("UPDATE tbl_m_db_connection_env SET f_dsn_name = :dsn, f_username = :username, f_password_ciphertext = :credential, f_is_active = 1 WHERE `{$primaryKey}` = :id");
        $windows->execute([
            ':dsn' => $windowsDsn,
            ':username' => $source['f_username'],
            ':credential' => $source['f_password_ciphertext'],
            ':id' => $windowsExisting[$primaryKey],
        ]);
    } else {
        $windows = $pdo->prepare("INSERT INTO tbl_m_db_connection_env (
            f_connection_code, f_environment, f_os_family, f_driver, f_dsn_name,
            f_username, f_password_ciphertext, f_charset, f_extra_json, f_is_active
        ) VALUES (:code, 'production', 'windows', 'odbc', :dsn, :username, :credential, :charset, :extra, 1)");
        $windows->execute([
            ':code' => $code,
            ':dsn' => $windowsDsn,
            ':username' => $source['f_username'],
            ':credential' => $source['f_password_ciphertext'],
            ':charset' => $source['f_charset'],
            ':extra' => $source['f_extra_json'],
        ]);
    }
    $mode = $pdo->prepare("UPDATE tbl_m_db_connection SET f_driver_mode = 'auto' WHERE f_code = :code");
    $mode->execute([':code' => $code]);

    $cipher = new DatabaseCredentialCipher();
    $password = $cipher->decrypt((string)$source['f_password_ciphertext']);
    $linuxConfig = [
        'driver' => 'dblib',
        'dsn' => sprintf('dblib:host=%s:%s;dbname=%s', $source['f_host'], $source['f_port'] ?: '5000', $source['f_database_name']),
        'user' => (string)$source['f_username'],
        'pass' => $password,
    ];
    (new DatabaseConnectionFactory())->make($linuxConfig)->query('SELECT 1');
    fwrite(STDOUT, "[PASS] Linux DBLIB SELECT 1 before commit\n");

    $pdo->commit();
    fwrite(STDOUT, "[PASS] Platform variants committed\n");
    fwrite(STDOUT, "Backup: {$backupPath}\n");
    fwrite(STDOUT, "PENDING: jalankan Windows ODBC SELECT 1 sebelum rollout dianggap complete.\n");
} catch (Throwable $error) {
    if ($pdo instanceof PDO && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    platformMigrationFail($error->getMessage());
}
