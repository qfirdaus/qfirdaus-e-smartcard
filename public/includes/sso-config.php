<?php
/**
 * IQS FRAMEWORK CORE FILE
 *
 * READ ONLY for downstream project programmers.
 * Do not modify this file directly in template or cloned projects.
 * Custom changes must be implemented in project-specific files
 * or approved extension points.
 */
if (!function_exists('sso_shared_config')) {
    function sso_config_bootstrap_app_config(): void
    {
        if (function_exists('app_config')) {
            return;
        }

        try {
            require_once __DIR__ . '/../classes/Database.php';
            require_once __DIR__ . '/../classes/Config.php';
            require_once __DIR__ . '/../setting/helper/config_helper.php';
        } catch (Throwable $e) {
            error_log('[sso_config] Failed bootstrapping app_config: ' . $e->getMessage());
        }
    }

    function sso_config_first_non_empty(array $values, string $fallback = ''): string
    {
        foreach ($values as $value) {
            $value = trim((string)$value);
            if ($value !== '') {
                return $value;
            }
        }

        return $fallback;
    }

    function sso_config_normalize_site_id(string $value): string
    {
        $value = trim($value);
        if ($value === '' || preg_match('/^[A-Za-z0-9_-]+$/', $value) !== 1) {
            return '';
        }

        return $value;
    }

    function sso_config_normalize_idp_domain(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return '';
        }

        $scheme = strtolower((string)(parse_url($value, PHP_URL_SCHEME) ?: ''));
        if (!in_array($scheme, ['http', 'https'], true)) {
            return '';
        }

        if (filter_var($value, FILTER_VALIDATE_URL) === false) {
            return '';
        }

        return rtrim($value, '/') . '/';
    }

    function sso_config_normalize_local_path(string $value, string $fallback): string
    {
        $value = trim($value);
        if ($value === '') {
            return $fallback;
        }

        if (filter_var($value, FILTER_VALIDATE_URL) !== false) {
            return $fallback;
        }

        if ($value[0] !== '/') {
            $value = '/' . $value;
        }

        return $value;
    }

    function sso_shared_config(): array
    {
        static $config = null;

        if ($config !== null) {
            return $config;
        }

        sso_config_bootstrap_app_config();

        $siteId = sso_config_normalize_site_id(
            sso_config_first_non_empty([
                function_exists('app_config') ? app_config('auth.sso_site_id', '') : '',
                getenv('SSO_SITE_ID'),
                getenv('SSO_SITE'),
            ], 'V8LN57YMGZ')
        );
        if ($siteId === '') {
            $siteId = 'V8LN57YMGZ';
        }

        $idpDomain = sso_config_normalize_idp_domain(
            sso_config_first_non_empty([
                function_exists('app_config') ? app_config('auth.sso_idp_domain', '') : '',
                getenv('SSO_IDP_DOMAIN'),
                getenv('SSO_IDP_URL'),
            ], 'https://oneid.upnm.edu.my/')
        );
        if ($idpDomain === '') {
            $idpDomain = 'https://oneid.upnm.edu.my/';
        }

        $idpHost = strtolower((string)(parse_url($idpDomain, PHP_URL_HOST) ?: ''));
        $loginPagePath = '/sso_sp_client.php';
        $dashboardPath = sso_config_normalize_local_path((string)(getenv('SSO_SP_DASHBOARD_PATH') ?: ''), '/login.php');

        $config = [
            'site_id' => $siteId,
            'idp_domain' => $idpDomain,
            'idp_host' => $idpHost,
            'launcher_url' => $idpDomain . '?site_id=' . rawurlencode($siteId),
            'loginpage_path' => $loginPagePath,
            'dashboard_path' => $dashboardPath,
        ];

        return $config;
    }
}
