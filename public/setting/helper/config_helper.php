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

// ===============================================
// CONFIG HELPER
// settings.php = base defaults
// tbl_m_config (group: app_settings) = safe runtime overrides
// ===============================================

function app_config_array_get(array $config, string $key, $default = null) {
    $segments = explode('.', $key);
    $value = $config;

    foreach ($segments as $segment) {
        if (!is_array($value) || !array_key_exists($segment, $value)) {
            return $default;
        }
        $value = $value[$segment];
    }

    return $value ?? $default;
}

function app_config_db_overrides(bool $refresh = false): array {
    static $overrides = null;

    if ($refresh) {
        $overrides = null;
    }

    if (is_array($overrides)) {
        return $overrides;
    }

    $overrides = [];

    try {
        if (!class_exists('Database') || !class_exists('Config')) {
            return $overrides;
        }

        $pdo = Database::getInstance('mysql')->getConnection();
        $configModel = new Config($pdo);
        $groupData = $configModel->getGroup('app_settings');

        if (is_array($groupData)) {
            foreach ($groupData as $key => $value) {
                if (is_string($key) && $key !== '') {
                    $overrides[$key] = $value;
                }
            }
        }
    } catch (\Throwable $e) {
        error_log('[app_config] Failed loading DB overrides: ' . $e->getMessage());
    }

    return $overrides;
}

/**
 * Buang cache konfigurasi DB untuk request PHP semasa.
 *
 * Ini perlu dipanggil selepas tbl_m_config dikemas kini supaya bacaan semula
 * dalam respons AJAX tidak memulangkan snapshot sebelum proses simpan.
 */
function app_config_reset_runtime_cache(): void {
    app_config_db_overrides(true);
}

/**
 * Ambil konfigurasi aplikasi.
 * Keutamaan:
 * 1. override dari DB (tbl_m_config, group = app_settings)
 * 2. fallback dari settings.php
 */
function app_config($key, $default = null) {
    static $config;

    if ($config === null) {
        $file = realpath(__DIR__ . '/../../configuration/settings.php');
        if (!is_string($file) || !file_exists($file)) {
            return $default;
        }

        $loaded = include $file;
        if (!is_array($loaded)) {
            return $default;
        }

        $config = $loaded;
    }

    $overrides = app_config_db_overrides();
    if (array_key_exists($key, $overrides) && $overrides[$key] !== null && $overrides[$key] !== '') {
        return $overrides[$key];
    }

    return app_config_array_get($config, (string)$key, $default);
}

function app_config_localized(string $key, $default = null, ?string $lang = null) {
    $lang = trim((string)($lang ?? ($_SESSION['lang'] ?? '')));
    if ($lang === '') {
        $lang = 'ms';
    }

    $localizedKey = $key . '.' . $lang;
    $localizedValue = app_config($localizedKey, null);
    if ($localizedValue !== null && $localizedValue !== '') {
        return $localizedValue;
    }

    return app_config($key, $default);
}
