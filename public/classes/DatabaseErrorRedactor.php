<?php
/**
 * IQS FRAMEWORK CORE FILE
 *
 * READ ONLY for downstream project programmers.
 */
declare(strict_types=1);

final class DatabaseErrorRedactor
{
    public const REDACTED = '[REDACTED]';

    public static function redact(string $message): string
    {
        if ($message === '') {
            return '';
        }

        $patterns = [
            // Authenticated credential envelope. Keep no key id, nonce or ciphertext.
            '~\bv2:[A-Za-z0-9._-]+:[A-Za-z0-9+/_=-]+:[A-Za-z0-9+/_=-]+(?=\s|[;,}\]]|$)~',
            // Credentials embedded in a URI.
            '/\b([a-z][a-z0-9+.-]*:\/\/)[^\s:\/@]+:[^\s@\/]+@/i',
            // Common key/value forms in DSNs, environment output and exception text.
            '/\b(password|passwd|pwd|pass|secret|token|api[_-]?key|private[_-]?key|credential|DB_ADDITIONAL_CREDENTIAL_KEY)\b(\s*[=:]\s*)(?!\[REDACTED)(["\']?)[^\s;,}\]\"\']+\3/i',
            // JSON-like quoted values.
            '/(["\'](?:password|passwd|pwd|pass|secret|token|api[_-]?key|private[_-]?key|credential)["\']\s*:\s*)["\'][^"\']*["\']/i',
        ];

        $message = preg_replace($patterns[0], '[REDACTED_CREDENTIAL_V2]', $message) ?? $message;
        $message = preg_replace($patterns[1], '$1' . self::REDACTED . '@', $message) ?? $message;
        $message = preg_replace($patterns[2], '$1$2' . self::REDACTED, $message) ?? $message;
        $message = preg_replace($patterns[3], '$1"' . self::REDACTED . '"', $message) ?? $message;

        return $message;
    }
}
