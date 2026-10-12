<?php
/**
 * IQS Framework Additional Database registry v2 integration smoke test.
 *
 * Safety:
 * - Refuses to run outside APP_ENV=development and MAIN_DB_ENVIRONMENT=development.
 * - Uses the main development database only.
 * - Inserts a uniquely named fixture inside one transaction.
 * - Always rolls the transaction back; no fixture row is retained.
 * - Does not open the configured external/additional target.
 * - Does not print keys, plaintext, or ciphertext.
 *
 * Run with a process-only key:
 *   DB_ADDITIONAL_CREDENTIAL_KEY='<base64-32-byte-key>' \
 *   DB_ADDITIONAL_CREDENTIAL_KEY_ID='staging' \
 *   php tools/additional-database-registry-v2-smoke.php
 */
declare(strict_types=1);

require_once __DIR__ . '/../public/classes/DatabaseCredentialCipher.php';
require_once __DIR__ . '/../public/classes/DatabaseConnectionRepository.php';
$configs = require __DIR__ . '/../public/configuration/db_config.php';

function registrySmokePass(string $message): void
{
    fwrite(STDOUT, "[PASS] {$message}\n");
}

function registrySmokeFail(string $message): never
{
    fwrite(STDERR, "[FAIL] {$message}\n");
    exit(1);
}

function registrySmokeAssert(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
    registrySmokePass($message);
}

$appEnv = strtolower(trim((string)($_ENV['APP_ENV'] ?? getenv('APP_ENV') ?: '')));
$mainEnvironment = strtolower(trim((string)($_ENV['MAIN_DB_ENVIRONMENT'] ?? getenv('MAIN_DB_ENVIRONMENT') ?: '')));
if ($appEnv !== 'development' || $mainEnvironment !== 'development') {
    registrySmokeFail('Smoke test hanya dibenarkan apabila APP_ENV dan MAIN_DB_ENVIRONMENT ialah development.');
}

$encodedKey = $_ENV[DatabaseCredentialCipher::ENV_KEY]
    ?? $_SERVER[DatabaseCredentialCipher::ENV_KEY]
    ?? getenv(DatabaseCredentialCipher::ENV_KEY);
$keyId = $_ENV[DatabaseCredentialCipher::ENV_KEY_ID]
    ?? $_SERVER[DatabaseCredentialCipher::ENV_KEY_ID]
    ?? getenv(DatabaseCredentialCipher::ENV_KEY_ID)
    ?: 'staging';
if (!is_string($encodedKey) || trim($encodedKey) === '') {
    registrySmokeFail(DatabaseCredentialCipher::ENV_KEY . ' tidak disediakan.');
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
    registrySmokeAssert((string)$pdo->query('SELECT DATABASE()')->fetchColumn() !== '', 'Main development database berjaya dibuka');

    $cipher = new DatabaseCredentialCipher(trim($encodedKey), (string)$keyId);
    registrySmokeAssert($cipher->isV2Configured(), 'Process-only v2 key berjaya dimuatkan');

    $fixtureCode = 'dbx_v2_smoke_' . strtolower(bin2hex(random_bytes(6)));
    $fixturePassword = 'fixture-' . bin2hex(random_bytes(16));
    $storedCredential = $cipher->encrypt($fixturePassword);

    $pdo->beginTransaction();

    $master = $pdo->prepare("
        INSERT INTO tbl_m_db_connection (
            f_code, f_name, f_family, f_category, f_purpose, f_driver_mode,
            f_is_enabled, f_supports_prod, f_supports_dev, f_notes,
            f_created_by, f_updated_by
        ) VALUES (
            :code, 'V2 Registry Smoke Fixture', 'mysql', 'additional', 'smoke-test', 'auto',
            1, 1, 0, 'Transaction rollback fixture', 'credential-smoke', 'credential-smoke'
        )
    ");
    $master->execute([':code' => $fixtureCode]);

    $environment = $pdo->prepare("
        INSERT INTO tbl_m_db_connection_env (
            f_connection_code, f_environment, f_os_family, f_driver,
            f_host, f_port, f_database_name, f_dsn_name, f_username,
            f_password_ciphertext, f_charset, f_extra_json, f_is_active
        ) VALUES (
            :code, 'production', 'any', 'mysql',
            '127.0.0.1', 3306, 'non_connecting_fixture', NULL, 'fixture_user',
            :credential, 'utf8mb4', NULL, 1
        )
    ");
    $environment->execute([':code' => $fixtureCode, ':credential' => $storedCredential]);
    registrySmokePass('Fixture registry v2 ditulis dalam transaction');

    $raw = $pdo->prepare('SELECT f_password_ciphertext FROM tbl_m_db_connection_env WHERE f_connection_code = :code');
    $raw->execute([':code' => $fixtureCode]);
    $rawStored = (string)$raw->fetchColumn();
    registrySmokeAssert(str_starts_with($rawStored, 'v2:' . $cipher->getKeyId() . ':'), 'Database menyimpan envelope v2');
    registrySmokeAssert(!str_contains($rawStored, $fixturePassword), 'Database tidak menyimpan plaintext credential');

    $repository = new DatabaseConnectionRepository($pdo, $cipher);
    $withSecret = $repository->findAdditionalByCode($fixtureCode, true);
    $envRow = is_array($withSecret['env_rows'][0] ?? null) ? $withSecret['env_rows'][0] : [];
    registrySmokeAssert(($envRow['f_password_ciphertext'] ?? null) === $fixturePassword, 'Repository dual-read memulangkan plaintext v2 kepada runtime');
    registrySmokeAssert(($envRow['f_credential_format'] ?? null) === 'v2', 'Repository mengenal pasti credential format v2');

    $withoutSecret = $repository->findAdditionalByCode($fixtureCode, false);
    $safeEnvRow = is_array($withoutSecret['env_rows'][0] ?? null) ? $withoutSecret['env_rows'][0] : [];
    registrySmokeAssert(
        array_key_exists('f_password_ciphertext', $safeEnvRow) && $safeEnvRow['f_password_ciphertext'] === null,
        'Respons biasa menyembunyikan credential'
    );
    registrySmokeAssert(($safeEnvRow['f_has_password'] ?? false) === true, 'Respons biasa hanya mendedahkan password presence');

    $pdo->rollBack();
    $exists = $pdo->prepare('SELECT 1 FROM tbl_m_db_connection WHERE f_code = :code');
    $exists->execute([':code' => $fixtureCode]);
    registrySmokeAssert($exists->fetchColumn() === false, 'Transaction rollback membuang fixture registry');

    fwrite(STDOUT, "\nResult: PASS (development registry, transaction rolled back)\n");
    exit(0);
} catch (Throwable $error) {
    if ($pdo instanceof PDO && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    registrySmokeFail($error->getMessage());
}
