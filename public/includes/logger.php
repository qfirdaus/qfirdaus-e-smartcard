<?php
/**
 * IQS FRAMEWORK CORE FILE
 *
 * READ ONLY for downstream project programmers.
 * Do not modify this file directly in template or cloned projects.
 * Custom changes must be implemented in project-specific files
 * or approved extension points.
 */// app/includes/logger.php
// Lightweight application logger writing to request-specific files in app/log

if (!function_exists('app_log_directory')) {
    function app_log_directory(): string {
        $dir = realpath(__DIR__ . '/../log') ?: (__DIR__ . '/../log');
        if (!is_dir($dir)) {
            @mkdir($dir, 0777, true);
        }
        return $dir;
    }
}

if (!function_exists('app_log_normalize_segment')) {
    function app_log_normalize_segment(string $value, string $fallback): string {
        $value = strtolower(trim($value));
        $value = preg_replace('/\.php$/', '', $value) ?? $value;
        $value = preg_replace('/[^a-z0-9_-]+/', '_', $value) ?? $value;
        $value = preg_replace('/_+/', '_', $value) ?? $value;
        $value = trim($value, '_');
        if ($value === '') {
            $value = $fallback;
        }
        if (strlen($value) > 80) {
            $value = substr($value, 0, 80);
            $value = rtrim($value, '_-');
        }
        return $value !== '' ? $value : $fallback;
    }
}

if (!function_exists('app_log_request_script_path')) {
    function app_log_request_script_path(): string {
        $candidates = [
            (string)($_SERVER['SCRIPT_NAME'] ?? ''),
            (string)($_SERVER['PHP_SELF'] ?? ''),
            (string)($_SERVER['REQUEST_URI'] ?? ''),
        ];

        foreach ($candidates as $candidate) {
            $candidate = trim($candidate);
            if ($candidate === '') {
                continue;
            }
            $path = (string)parse_url($candidate, PHP_URL_PATH);
            if ($path !== '') {
                return str_replace('\\', '/', $path);
            }
        }

        return '';
    }
}

if (!function_exists('app_log_channel')) {
    function app_log_channel(): string {
        if (PHP_SAPI === 'cli') {
            return 'cli';
        }

        $path = app_log_request_script_path();
        if ($path === '') {
            return 'system';
        }

        if (preg_match('#/ajax/[^/]+\.php$#i', $path)) {
            return 'ajax';
        }

        if (preg_match('#/api/[^/]+\.php$#i', $path)) {
            return 'api';
        }

        if (preg_match('#/[^/]+\.php$#', $path)) {
            return 'page';
        }

        return 'system';
    }
}

if (!function_exists('app_log_target')) {
    function app_log_target(): string {
        if (PHP_SAPI === 'cli') {
            $script = (string)($_SERVER['argv'][0] ?? 'runtime');
            return app_log_normalize_segment(pathinfo($script, PATHINFO_FILENAME), 'runtime');
        }

        $path = app_log_request_script_path();
        if ($path === '') {
            return 'unknown';
        }

        $base = basename($path);
        if ($base === '' || $base === '/' || $base === '.') {
            return 'unknown';
        }

        return app_log_normalize_segment($base, 'unknown');
    }
}

if (!function_exists('app_log_filename')) {
    function app_log_filename(): string {
        $channel = app_log_normalize_segment(app_log_channel(), 'system');
        $target = app_log_target();

        if ($channel === 'system' && $target === 'unknown') {
            return 'system_bootstrap.log';
        }

        return $channel . '_' . $target . '.log';
    }
}

if (!function_exists('app_log_file_path')) {
    function app_log_file_path(): string {
        return rtrim(app_log_directory(), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . app_log_filename();
    }
}

if (!function_exists('app_log')) {
    function app_log(string $message, string $level = 'INFO'): void {
        $file = app_log_file_path();
        $time = date('Y-m-d H:i:s');
        $pid = getmypid() ?: null;
        $entry = sprintf("[%s] [%s] [pid:%s] %s\n", $time, $level, $pid, trim($message));
        // Use LOCK_EX to avoid concurrent write races
        @file_put_contents($file, $entry, FILE_APPEND | LOCK_EX);
        // Restrict permissions where possible
        if (is_file($file)) {
            @chmod($file, 0660);
        }
    }
}

// Also provide a convenience wrapper to mirror error_log but with app_log
if (!function_exists('app_error_log')) {
    function app_error_log(string $message): void {
        app_log($message, 'ERROR');
    }
}

if (!function_exists('app_log_redact_external_value')) {
    function app_log_redact_external_value(string $value): string {
        $value = preg_replace('/(authorization:\s*bearer\s+)[^\s]+/i', '$1[redacted]', $value) ?? $value;
        $value = preg_replace('/([?&](?:key|api_key|token|access_token|password)=)[^&\s]+/i', '$1[redacted]', $value) ?? $value;
        return $value;
    }
}

if (!function_exists('app_log_external_service')) {
    /**
     * @param array<string,mixed> $extra
     */
    function app_log_external_service(ExternalServiceException $exception, array $extra = []): void {
        $parts = [
            '[ExternalService]',
            'Provider=' . app_log_redact_external_value($exception->provider()),
            'Category=' . app_log_redact_external_value($exception->category()),
            'Retryable=' . ($exception->retryable() ? 'true' : 'false'),
        ];

        if ($exception->endpoint() !== null && $exception->endpoint() !== '') {
            $parts[] = 'Endpoint=' . app_log_redact_external_value($exception->endpoint());
        }
        if ($exception->httpStatus() !== null) {
            $parts[] = 'HTTPStatus=' . (string)$exception->httpStatus();
        }

        foreach (array_merge($exception->context(), $extra) as $key => $value) {
            $key = preg_replace('/[^A-Za-z0-9_:-]+/', '_', (string)$key) ?? 'context';
            if (in_array(strtolower($key), ['authorization', 'api_key', 'token', 'access_token', 'password'], true)) {
                $value = '[redacted]';
            } elseif (is_scalar($value) || $value === null) {
                $value = app_log_redact_external_value((string)$value);
            } else {
                $encoded = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                $value = app_log_redact_external_value(is_string($encoded) ? $encoded : '[unserializable]');
            }
            $parts[] = $key . '=' . str_replace(["\r", "\n"], ' ', (string)$value);
        }

        $parts[] = 'Message=' . str_replace(["\r", "\n"], ' ', app_log_redact_external_value($exception->getMessage()));
        app_log(implode(' ', $parts), $exception->retryable() ? 'WARN' : 'ERROR');
    }
}
