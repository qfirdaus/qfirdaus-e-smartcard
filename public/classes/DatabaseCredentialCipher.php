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

require_once __DIR__ . '/../setting/helper/security_helper.php';

final class DatabaseCredentialCipher
{
    public const ENV_KEY = 'DB_ADDITIONAL_CREDENTIAL_KEY';
    public const ENV_KEY_ID = 'DB_ADDITIONAL_CREDENTIAL_KEY_ID';
    private const VERSION = 'v2';

    private readonly ?string $key;
    private readonly string $keyId;
    private readonly Encryption $legacyCipher;

    public function __construct(
        ?string $encodedKey = null,
        ?string $keyId = null,
        ?Encryption $legacyCipher = null,
    ) {
        $encodedKey ??= $this->env(self::ENV_KEY);
        $keyId ??= $this->env(self::ENV_KEY_ID) ?: 'primary';
        $this->key = $this->decodeKey($encodedKey);
        $this->keyId = $this->normalizeKeyId($keyId);
        $this->legacyCipher = $legacyCipher ?? new Encryption();
    }

    public function encrypt(string $plainText): string
    {
        if ($plainText === '') {
            throw new InvalidArgumentException('Credential plaintext tidak boleh kosong.');
        }

        if (!$this->isV2Configured()) {
            $legacy = $this->legacyCipher->encode($plainText);
            if (!is_string($legacy) || $legacy === '') {
                throw new RuntimeException('Credential legacy gagal dienkripsi.');
            }
            return $legacy;
        }

        if (!function_exists('sodium_crypto_secretbox')) {
            throw new RuntimeException('Sodium extension diperlukan untuk credential encryption v2.');
        }

        $nonce = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        $cipherText = sodium_crypto_secretbox($plainText, $nonce, $this->key);

        return implode(':', [
            self::VERSION,
            $this->keyId,
            $this->base64UrlEncode($nonce),
            $this->base64UrlEncode($cipherText),
        ]);
    }

    public function decrypt(string $storedValue): string
    {
        if ($storedValue === '') {
            throw new InvalidArgumentException('Credential ciphertext tidak boleh kosong.');
        }

        if (!str_starts_with($storedValue, self::VERSION . ':')) {
            $legacy = $this->legacyCipher->decode($storedValue);
            if (!is_string($legacy) || $legacy === '') {
                throw new RuntimeException('Credential legacy gagal didekripsi.');
            }
            return $legacy;
        }

        if (!$this->isV2Configured()) {
            throw new RuntimeException(self::ENV_KEY . ' diperlukan untuk membaca credential v2.');
        }
        if (!function_exists('sodium_crypto_secretbox_open')) {
            throw new RuntimeException('Sodium extension diperlukan untuk membaca credential v2.');
        }

        $parts = explode(':', $storedValue, 4);
        if (count($parts) !== 4 || $parts[1] === '' || $parts[2] === '' || $parts[3] === '') {
            throw new RuntimeException('Format credential v2 tidak sah.');
        }
        if (!hash_equals($this->keyId, $parts[1])) {
            throw new RuntimeException("Credential v2 menggunakan key ID yang tidak tersedia: {$parts[1]}.");
        }

        $nonce = $this->base64UrlDecode($parts[2]);
        $cipherText = $this->base64UrlDecode($parts[3]);
        if (strlen($nonce) !== SODIUM_CRYPTO_SECRETBOX_NONCEBYTES) {
            throw new RuntimeException('Nonce credential v2 tidak sah.');
        }

        $plainText = sodium_crypto_secretbox_open($cipherText, $nonce, $this->key);
        if (!is_string($plainText) || $plainText === '') {
            throw new RuntimeException('Credential v2 gagal didekripsi atau telah diubah.');
        }
        return $plainText;
    }

    public function isV2Configured(): bool
    {
        return is_string($this->key) && strlen($this->key) === SODIUM_CRYPTO_SECRETBOX_KEYBYTES;
    }

    public function getWriteFormat(): string
    {
        return $this->isV2Configured() ? self::VERSION : 'legacy';
    }

    public function getKeyId(): string
    {
        return $this->keyId;
    }

    private function decodeKey(?string $encodedKey): ?string
    {
        $encodedKey = trim((string)$encodedKey);
        if ($encodedKey === '') {
            return null;
        }
        if (!function_exists('sodium_base642bin')) {
            throw new RuntimeException('Sodium extension diperlukan untuk memuatkan credential encryption key.');
        }

        try {
            $decoded = sodium_base642bin($encodedKey, SODIUM_BASE64_VARIANT_ORIGINAL);
        } catch (SodiumException $error) {
            throw new RuntimeException(self::ENV_KEY . ' mesti Base64 yang sah.', 0, $error);
        }
        if (strlen($decoded) !== SODIUM_CRYPTO_SECRETBOX_KEYBYTES) {
            throw new RuntimeException(self::ENV_KEY . ' mesti mengandungi tepat 32 byte selepas Base64 decode.');
        }
        return $decoded;
    }

    private function normalizeKeyId(string $keyId): string
    {
        $keyId = strtolower(trim($keyId));
        if (preg_match('/^[a-z0-9][a-z0-9_-]{0,63}$/', $keyId) !== 1) {
            throw new RuntimeException(self::ENV_KEY_ID . ' tidak sah.');
        }
        return $keyId;
    }

    private function env(string $name): ?string
    {
        $value = $_ENV[$name] ?? $_SERVER[$name] ?? getenv($name);
        return is_string($value) && trim($value) !== '' ? trim($value) : null;
    }

    private function base64UrlEncode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }

    private function base64UrlDecode(string $value): string
    {
        $padding = strlen($value) % 4;
        if ($padding > 0) {
            $value .= str_repeat('=', 4 - $padding);
        }
        $decoded = base64_decode(strtr($value, '-_', '+/'), true);
        if (!is_string($decoded)) {
            throw new RuntimeException('Base64 credential v2 tidak sah.');
        }
        return $decoded;
    }
}
