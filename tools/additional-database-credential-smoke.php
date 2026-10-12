<?php
/**
 * IQS Framework Additional Database credential v2 smoke test.
 *
 * This command is offline: it does not bootstrap the application, open a PDO
 * connection, read the registry, or modify a database. Supply the v2 key only
 * through the process environment.
 *
 * Run:
 *   DB_ADDITIONAL_CREDENTIAL_KEY='<base64-32-byte-key>' \
 *   DB_ADDITIONAL_CREDENTIAL_KEY_ID='staging' \
 *   php tools/additional-database-credential-smoke.php
 */
declare(strict_types=1);

require_once __DIR__ . '/../public/classes/DatabaseCredentialCipher.php';

function smokePass(string $message): void
{
    fwrite(STDOUT, "[PASS] {$message}\n");
}

function smokeFail(string $message): never
{
    fwrite(STDERR, "[FAIL] {$message}\n");
    exit(1);
}

function smokeAssert(bool $condition, string $message): void
{
    if (!$condition) {
        smokeFail($message);
    }
    smokePass($message);
}

$encodedKey = $_ENV[DatabaseCredentialCipher::ENV_KEY]
    ?? $_SERVER[DatabaseCredentialCipher::ENV_KEY]
    ?? getenv(DatabaseCredentialCipher::ENV_KEY);
$keyId = $_ENV[DatabaseCredentialCipher::ENV_KEY_ID]
    ?? $_SERVER[DatabaseCredentialCipher::ENV_KEY_ID]
    ?? getenv(DatabaseCredentialCipher::ENV_KEY_ID)
    ?: 'staging';

if (!is_string($encodedKey) || trim($encodedKey) === '') {
    smokeFail(DatabaseCredentialCipher::ENV_KEY . ' tidak disediakan. Smoke test dihentikan tanpa fallback.');
}

try {
    $cipher = new DatabaseCredentialCipher(trim($encodedKey), (string)$keyId);
    smokeAssert($cipher->isV2Configured(), 'V2 key berjaya dimuatkan');
    smokeAssert($cipher->getWriteFormat() === 'v2', 'Write format ialah v2');

    $fixture = 'smoke-' . bin2hex(random_bytes(16));
    $stored = $cipher->encrypt($fixture);
    smokeAssert(str_starts_with($stored, 'v2:' . $cipher->getKeyId() . ':'), 'Envelope v2 menggunakan key ID staging');
    smokeAssert($stored !== $fixture, 'Ciphertext tidak sama dengan plaintext');
    smokeAssert(!str_contains($stored, $fixture), 'Envelope tidak mengandungi plaintext');
    smokeAssert($cipher->decrypt($stored) === $fixture, 'V2 encrypt/decrypt round-trip berjaya');

    $legacyCipher = new DatabaseCredentialCipher('', 'primary');
    $legacyStored = $legacyCipher->encrypt($fixture);
    smokeAssert(!str_starts_with($legacyStored, 'v2:'), 'Legacy fixture berjaya dihasilkan tanpa v2 key');
    smokeAssert($cipher->decrypt($legacyStored) === $fixture, 'V2 runtime masih boleh membaca credential legacy');

    $parts = explode(':', $stored, 4);
    $cipherPart = $parts[3] ?? '';
    $tamperedFirst = substr($cipherPart, 0, 1) === 'A' ? 'B' : 'A';
    $parts[3] = $tamperedFirst . substr($cipherPart, 1);
    $tampered = implode(':', $parts);
    try {
        $cipher->decrypt($tampered);
        smokeFail('Ciphertext yang diubah sepatutnya ditolak');
    } catch (RuntimeException $error) {
        smokePass('Ciphertext yang diubah berjaya ditolak');
    }

    try {
        (new DatabaseCredentialCipher('', $cipher->getKeyId()))->decrypt($stored);
        smokeFail('Credential v2 tanpa key sepatutnya ditolak');
    } catch (RuntimeException $error) {
        smokePass('Credential v2 tanpa key berjaya ditolak');
    }

    $otherKey = base64_encode(random_bytes(SODIUM_CRYPTO_SECRETBOX_KEYBYTES));
    try {
        (new DatabaseCredentialCipher($otherKey, 'other-key'))->decrypt($stored);
        smokeFail('Credential v2 dengan key ID lain sepatutnya ditolak');
    } catch (RuntimeException $error) {
        smokePass('Credential v2 dengan key ID lain berjaya ditolak');
    }

    fwrite(STDOUT, "\nResult: PASS (offline, no database connection)\n");
    exit(0);
} catch (Throwable $error) {
    smokeFail($error->getMessage());
}
