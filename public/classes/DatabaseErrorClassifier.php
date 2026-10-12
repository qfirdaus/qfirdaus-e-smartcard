<?php
/**
 * IQS FRAMEWORK CORE FILE
 *
 * READ ONLY for downstream project programmers.
 */
declare(strict_types=1);

require_once __DIR__ . '/AdditionalDatabaseException.php';

/**
 * Maps database failures to low-cardinality observability categories.
 *
 * Classification is additive metadata only. It does not wrap, replace, or
 * otherwise alter the exception exposed to existing downstream callers.
 */
final class DatabaseErrorClassifier
{
    public static function classify(Throwable $error): string
    {
        if ($error instanceof AdditionalDatabaseException) {
            return $error->category;
        }

        return match (true) {
            $error instanceof PDOException => 'connection_failed',
            $error instanceof InvalidArgumentException => 'validation_failed',
            $error instanceof RuntimeException => 'runtime_failed',
            default => 'unexpected_failure',
        };
    }
}
