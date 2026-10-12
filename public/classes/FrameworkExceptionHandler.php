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

require_once __DIR__ . '/ExternalServiceException.php';
require_once __DIR__ . '/ValidationException.php';

final class FrameworkExceptionHandler
{
    private function __construct()
    {
    }

    public static function httpStatusFor(Throwable $exception): int
    {
        if ($exception instanceof ValidationException || $exception instanceof InvalidArgumentException) {
            return 422;
        }

        if (!$exception instanceof ExternalServiceException) {
            return 500;
        }

        if ($exception instanceof ExternalServiceRateLimitException) {
            return 429;
        }

        if ($exception instanceof ExternalServiceAuthenticationException) {
            return 424;
        }

        if ($exception instanceof ExternalServiceTimeoutException) {
            return 504;
        }

        if ($exception instanceof ExternalServiceInvalidResponseException) {
            return 502;
        }

        $providerStatus = (int)($exception->httpStatus() ?? 0);
        if ($providerStatus === 429) {
            return 429;
        }
        if ($providerStatus >= 500) {
            return 503;
        }
        if (in_array($providerStatus, [401, 403], true)) {
            return 424;
        }

        return 503;
    }

    public static function publicMessageFor(Throwable $exception, ?string $fallback = null): string
    {
        if ($exception instanceof ExternalServiceException) {
            return $fallback ?: 'Perkhidmatan luaran tidak tersedia buat masa ini. Sila cuba semula sebentar lagi.';
        }

        if ($exception instanceof ValidationException || $exception instanceof InvalidArgumentException) {
            return $exception->getMessage();
        }

        return $fallback ?: 'Ralat sistem semasa memproses permintaan.';
    }

    /**
     * @param array<string,mixed> $extra
     */
    public static function log(Throwable $exception, array $extra = []): void
    {
        if ($exception instanceof ExternalServiceException) {
            if (function_exists('app_log_external_service')) {
                app_log_external_service($exception, $extra);
                return;
            }

            error_log('[ExternalService] Provider=' . $exception->provider() . ' Message=' . $exception->getMessage());
            return;
        }

        error_log('[' . get_class($exception) . '] ' . $exception->getMessage());
    }

    /**
     * @param array<string,mixed> $extra
     */
    public static function json(Throwable $exception, ?string $fallback = null, array $extra = []): never
    {
        self::log($exception, $extra);

        while (ob_get_level() > 0) {
            @ob_end_clean();
        }

        $status = self::httpStatusFor($exception);
        http_response_code($status);
        if (!headers_sent()) {
            header('Content-Type: application/json; charset=utf-8');
        }

        $payload = [
            'success' => false,
            'error' => true,
            'message' => self::publicMessageFor($exception, $fallback),
        ];

        if ($exception instanceof ExternalServiceException) {
            $payload['external_service_error'] = true;
            $payload['provider'] = $exception->provider();
            $payload['category'] = $exception->category();
            $payload['retryable'] = $exception->retryable();
        }

        echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }
}
