<?php
/**
 * IQS FRAMEWORK CORE FILE
 *
 * READ ONLY for downstream project programmers.
 */
declare(strict_types=1);

/**
 * Categorised runtime failure for Additional Database operations.
 *
 * This deliberately extends RuntimeException so existing downstream catch
 * blocks and the public Database::pdoAdditional() exception contract remain
 * backward compatible.
 */
final class AdditionalDatabaseException extends RuntimeException
{
    public function __construct(
        public readonly string $category,
        string $message,
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }

    public static function disabled(string $code): self
    {
        return new self('disabled', "Additional database connection is disabled: {$code}");
    }

    public static function notFound(string $code): self
    {
        return new self('not_found', "Additional database connection not found: {$code}");
    }

    public static function environmentNotConfigured(string $family, string $code, string $environment): self
    {
        return new self(
            'environment_not_configured',
            sprintf('%s environment not configured for %s: %s', strtoupper($family), $code, $environment),
        );
    }

    public static function driverUnavailable(string $family, string $code): self
    {
        return new self(
            'driver_unavailable',
            sprintf('No suitable %s driver variant found for %s', $family, $code),
        );
    }
}
