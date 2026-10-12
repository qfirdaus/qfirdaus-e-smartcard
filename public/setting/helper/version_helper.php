<?php
/**
 * IQS FRAMEWORK CORE FILE
 *
 * READ ONLY for downstream project programmers.
 * Do not modify this file directly in template or cloned projects.
 * Custom changes must be implemented in project-specific files
 * or approved extension points.
 */declare(strict_types=1);

if (defined('VERSION_HELPER_INCLUDED')) {
    return;
}
define('VERSION_HELPER_INCLUDED', true);

if (!function_exists('app_current_version')) {
    function app_current_version(string $fallback = 'dev'): string
    {
        static $cached = null;

        if ($cached !== null) {
            return $cached;
        }

        $candidates = [
            trim((string)($_ENV['APP_VERSION_FILE'] ?? $_SERVER['APP_VERSION_FILE'] ?? '')),
            dirname(__DIR__, 3) . DIRECTORY_SEPARATOR . 'VERSION',
        ];

        $value = '';
        foreach ($candidates as $versionFile) {
            $versionFile = trim((string)$versionFile);
            if ($versionFile === '' || !is_file($versionFile)) {
                continue;
            }

            $readValue = trim((string)@file_get_contents($versionFile));
            if ($readValue !== '') {
                $value = $readValue;
                break;
            }
        }

        if ($value === '' && function_exists('app_config')) {
            $configVersion = trim((string)app_config('system.version', ''));
            if ($configVersion !== '') {
                $value = $configVersion;
            }
        }

        $cached = $value !== '' ? $value : $fallback;

        return $cached;
    }
}

if (!function_exists('app_current_version_label')) {
    function app_current_version_label(?string $prefix = null): string
    {
        $version = app_current_version();
        $resolvedPrefix = $prefix;

        if ($resolvedPrefix === null) {
            $translated = function_exists('__') ? (string)__('common_version') : 'Version';
            $resolvedPrefix = ($translated !== '' && $translated !== 'common_version') ? $translated : 'Version';
        }

        return trim($resolvedPrefix) . ' ' . $version;
    }
}
