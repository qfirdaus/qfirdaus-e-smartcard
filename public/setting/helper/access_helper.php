<?php
/**
 * IQS FRAMEWORK CORE FILE
 *
 * READ ONLY for downstream project programmers.
 * Do not modify this file directly in template or cloned projects.
 * Custom changes must be implemented in project-specific files
 * or approved extension points.
 *///Update
declare(strict_types=1);

require_once __DIR__ . '/../constants/prestasi_constants.php';
require_once __DIR__ . '/../constants/access_policy_constants.php';

if (!function_exists('prestasi_normalize_group_code')) {
    function prestasi_normalize_group_code(?string $groupKod): string {
        $groupKod = strtoupper(trim((string)$groupKod));
        if ($groupKod === '') return '';
        return preg_replace('/[^A-Z0-9]+/', '', $groupKod) ?? '';
    }
}

if (!function_exists('prestasi_load_project_policy')) {
    function prestasi_load_project_policy(string $policyName): void {
        $policyName = preg_replace('/[^a-zA-Z0-9_-]+/', '', $policyName) ?? '';
        if ($policyName === '') {
            return;
        }

        $candidates = [
            __DIR__ . '/../../project/policies/' . $policyName . '_policy.php',
            __DIR__ . '/../../custom/policies/' . $policyName . '_policy.php',
        ];

        foreach ($candidates as $path) {
            if (is_file($path)) {
                require_once $path;
            }
        }
    }
}

if (!function_exists('prestasi_project_userlist_policy_decision')) {
    function prestasi_project_userlist_policy_decision(string $hook, bool $default, array $args = []): bool {
        prestasi_load_project_policy('userlist');
        if (!function_exists($hook)) {
            return $default;
        }

        try {
            return (bool)$hook(...$args);
        } catch (Throwable $e) {
            error_log('[prestasi_project_userlist_policy_decision] ' . $hook . ': ' . $e->getMessage());
            return $default;
        }
    }
}

if (!function_exists('prestasi_is_userlist_page_path')) {
    function prestasi_is_userlist_page_path(string $path): bool {
        $path = prestasi_normalize_menu_path($path);
        return $path === 'pages/senarai-pengguna.php' || basename($path) === 'senarai-pengguna.php';
    }
}

if (!function_exists('prestasi_is_userlist_ajax_path')) {
    function prestasi_is_userlist_ajax_path(string $path): bool {
        $path = prestasi_normalize_menu_path($path);
        $basename = basename($path);
        return str_starts_with($path, 'ajax/')
            && in_array($basename, [
                'user-add-public.php',
                'user-add-student.php',
                'user-add.php',
                'user-delete.php',
                'user-extra-roles.php',
                'user-list-rows.php',
                'user-list-staf-options.php',
                'user-list-student-options.php',
                'user-search-pelajar.php',
                'user-search-staf.php',
                'user-set-group.php',
                'user-student-detail.php',
                'user-sync-student.php',
                'user-sync-sybase.php',
                'user-update-public.php',
            ], true);
    }
}

if (!function_exists('prestasi_group_code_equals')) {
    function prestasi_group_code_equals(?string $left, ?string $right): bool {
        $a = prestasi_normalize_group_code($left);
        $b = prestasi_normalize_group_code($right);
        return ($a !== '' && $a === $b);
    }
}

if (!function_exists('prestasi_super_admin_code')) {
    function prestasi_super_admin_code(): string {
        if (defined('PRESTASI_ROLE_KOD_ADM_SA')) return (string)PRESTASI_ROLE_KOD_ADM_SA;
        if (defined('PRESTASI_ROLE_ADM_SA')) return (string)PRESTASI_ROLE_ADM_SA;
        return 'ADM-SA';
    }
}

if (!function_exists('prestasi_resolve_active_group')) {
    function prestasi_resolve_active_group(array $profile = [], ?PDO $pdo = null): array {
        $defaultGroupId = (int)($profile['f_groupID'] ?? 0);
        $defaultGroupKod = (string)($profile['f_groupKod'] ?? '');
        $activeGroupId = (int)($_SESSION['group_active_id'] ?? 0);
        if ($activeGroupId <= 0) $activeGroupId = $defaultGroupId;

        $activeGroupKod = '';
        if ($activeGroupId > 0 && $activeGroupId === $defaultGroupId && $defaultGroupKod !== '') {
            $activeGroupKod = $defaultGroupKod;
        } elseif ($activeGroupId > 0 && $pdo instanceof PDO) {
            try {
                static $cacheById = [];
                if (array_key_exists($activeGroupId, $cacheById)) {
                    $activeGroupKod = (string)$cacheById[$activeGroupId];
                } else {
                    $stmt = $pdo->prepare("SELECT f_groupKod FROM tbl_m_group WHERE f_groupID = :gid LIMIT 1");
                    $stmt->execute([':gid' => $activeGroupId]);
                    $activeGroupKod = (string)($stmt->fetchColumn() ?: '');
                    $cacheById[$activeGroupId] = $activeGroupKod;
                }
            } catch (Throwable $e) {
                $activeGroupKod = '';
            }
        }

        if ($activeGroupKod === '') $activeGroupKod = $defaultGroupKod;
        return ['id' => $activeGroupId, 'kod' => $activeGroupKod];
    }
}

if (!function_exists('is_user_super_admin')) {
    function is_user_super_admin(array $profile = [], ?PDO $pdo = null): bool {
        $legacyRoleId = defined('PRESTASI_ROLE_ID_ADM_SA') ? (int)PRESTASI_ROLE_ID_ADM_SA : 0;
        $activeRoleId = (int)($_SESSION['group_active_id'] ?? 0);
        if ($legacyRoleId > 0 && $activeRoleId > 0 && $activeRoleId === $legacyRoleId) return true;

        $resolved = prestasi_resolve_active_group($profile, $pdo);
        if ($legacyRoleId > 0 && (int)$resolved['id'] === $legacyRoleId) return true;

        $superAdminKod = prestasi_super_admin_code();
        return prestasi_group_code_equals((string)$resolved['kod'], $superAdminKod);
    }
}

if (!function_exists('prestasi_normalize_menu_path')) {
    function prestasi_normalize_menu_path(?string $path): string {
        $path = trim(str_replace('\\', '/', (string)$path));
        if ($path === '' || str_contains($path, '..') || str_contains($path, '//')) {
            return '';
        }

        $path = ltrim($path, '/');
        if (!preg_match('/^[A-Za-z0-9_\\-.\\/]+$/', $path)) {
            return '';
        }

        return strtolower($path);
    }
}

if (!function_exists('prestasi_current_page_relative_path')) {
    function prestasi_current_page_relative_path(): string {
        return prestasi_current_request_relative_path();
    }
}

if (!function_exists('prestasi_public_root_path')) {
    function prestasi_public_root_path(): string {
        return realpath(__DIR__ . '/../../') ?: (__DIR__ . '/../../');
    }
}

if (!function_exists('prestasi_current_request_relative_path')) {
    function prestasi_current_request_relative_path(): string {
        $scriptName = str_replace('\\', '/', (string)($_SERVER['SCRIPT_NAME'] ?? ''));
        $scriptName = ltrim($scriptName, '/');
        $requestUriPath = (string)(parse_url((string)($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH) ?: '');
        $requestUriPath = ltrim(str_replace('\\', '/', $requestUriPath), '/');

        $publicPos = stripos($scriptName, 'public/');
        if ($publicPos !== false) {
            $scriptName = substr($scriptName, $publicPos + 7);
        }

        $normalizedScript = prestasi_normalize_menu_path($scriptName);
        if ($normalizedScript !== '') {
            $inferred = prestasi_infer_request_namespace($normalizedScript);
            if ($inferred !== '') {
                return $inferred;
            }
        }

        $normalizedUri = prestasi_normalize_menu_path($requestUriPath);
        if ($normalizedUri !== '') {
            $inferred = prestasi_infer_request_namespace($normalizedUri);
            if ($inferred !== '') {
                return $inferred;
            }
        }

        return $normalizedScript !== '' ? $normalizedScript : $normalizedUri;
    }
}

if (!function_exists('prestasi_infer_request_namespace')) {
    function prestasi_infer_request_namespace(string $path): string {
        $path = prestasi_normalize_menu_path($path);
        if ($path === '') {
            return '';
        }

        foreach (['pages', 'ajax', 'actions'] as $bucket) {
            if (str_starts_with($path, $bucket . '/')) {
                return $path;
            }
        }

        $basename = basename($path);
        if ($basename === '' || $basename === '.' || $basename === '..') {
            return $path;
        }

        $publicRoot = realpath(__DIR__ . '/../../');
        if (!$publicRoot) {
            return $path;
        }

        foreach (['pages', 'ajax', 'actions'] as $bucket) {
            $candidate = $publicRoot . DIRECTORY_SEPARATOR . $bucket . DIRECTORY_SEPARATOR . $basename;
            if (is_file($candidate)) {
                return $bucket . '/' . strtolower($basename);
            }
        }

        return $path;
    }
}

if (!function_exists('prestasi_request_uri_relative_path')) {
    function prestasi_request_uri_relative_path(): string {
        $requestUriPath = (string)(parse_url((string)($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH) ?: '');
        $requestUriPath = ltrim(str_replace('\\', '/', $requestUriPath), '/');
        if ($requestUriPath === '') {
            return '';
        }

        $scriptName = str_replace('\\', '/', (string)($_SERVER['SCRIPT_NAME'] ?? ''));
        $scriptDir = trim(dirname($scriptName), '/');
        if ($scriptDir !== '' && $scriptDir !== '.' && str_starts_with($requestUriPath, $scriptDir . '/')) {
            $requestUriPath = substr($requestUriPath, strlen($scriptDir) + 1);
        }

        $publicPos = stripos($requestUriPath, 'public/');
        if ($publicPos !== false) {
            $requestUriPath = substr($requestUriPath, $publicPos + 7);
        }

        $normalizedUri = prestasi_normalize_menu_path($requestUriPath);
        if ($normalizedUri === '') {
            return '';
        }

        $inferred = prestasi_infer_request_namespace($normalizedUri);
        return $inferred !== '' ? $inferred : $normalizedUri;
    }
}

if (!function_exists('prestasi_requested_missing_page_from_uri')) {
    function prestasi_requested_missing_page_from_uri(): string {
        $requestedPath = prestasi_request_uri_relative_path();
        if ($requestedPath === '' || !str_starts_with($requestedPath, 'pages/')) {
            return '';
        }

        $basename = basename($requestedPath);
        if ($basename === '' || !str_ends_with($basename, '.php')) {
            return '';
        }

        $publicRoot = prestasi_public_root_path();
        $candidate = $publicRoot . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $requestedPath);
        return is_file($candidate) ? '' : $requestedPath;
    }
}

if (!function_exists('prestasi_public_page_allowlist')) {
    function prestasi_public_page_allowlist(): array {
        $list = defined('ACCESS_POLICY_PUBLIC_PAGE_ALLOWLIST') ? ACCESS_POLICY_PUBLIC_PAGE_ALLOWLIST : [];
        return is_array($list) ? $list : [];
    }
}

if (!function_exists('prestasi_is_ajax_like_request')) {
    function prestasi_is_ajax_like_request(): bool {
        if (function_exists('request_is_ajax_like')) {
            return request_is_ajax_like();
        }

        $requestedWith = strtolower(trim((string)($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '')));
        if ($requestedWith === 'xmlhttprequest') {
            return true;
        }

        $accept = strtolower(trim((string)($_SERVER['HTTP_ACCEPT'] ?? '')));
        return $accept !== '' && (str_contains($accept, 'application/json') || str_contains($accept, 'text/json'));
    }
}

if (!function_exists('prestasi_has_authenticated_session')) {
    function prestasi_has_authenticated_session(): bool {
        return !empty($_SESSION['f_stafID'])
            || !empty($_SESSION['f_loginID'])
            || !empty($_SESSION['user']);
    }
}

if (!function_exists('prestasi_is_modal_partial_path')) {
    function prestasi_is_modal_partial_path(string $currentPath): bool {
        $currentPath = prestasi_normalize_menu_path($currentPath);
        return $currentPath !== ''
            && str_starts_with($currentPath, 'pages/modal/')
            && str_ends_with($currentPath, '.php');
    }
}

if (!function_exists('prestasi_super_admin_only_pages')) {
    function prestasi_super_admin_only_pages(): array {
        $list = defined('ACCESS_POLICY_SUPER_ADMIN_ONLY_PAGES') ? ACCESS_POLICY_SUPER_ADMIN_ONLY_PAGES : [];
        return is_array($list) ? $list : [];
    }
}

if (!function_exists('prestasi_custom_guard_pages')) {
    function prestasi_custom_guard_pages(): array {
        $list = defined('ACCESS_POLICY_CUSTOM_GUARD_PAGES') ? ACCESS_POLICY_CUSTOM_GUARD_PAGES : [];
        return is_array($list) ? $list : [];
    }
}

if (!function_exists('prestasi_generated_page_access_mode')) {
    function prestasi_generated_page_access_mode(string $currentPath, ?PDO $pdo = null): ?string {
        if (!$pdo instanceof PDO || $currentPath === '' || !str_starts_with($currentPath, 'pages/')) {
            return null;
        }

        $basename = basename($currentPath);
        if ($basename === '' || !str_ends_with($basename, '.php')) {
            return null;
        }

        $slug = substr($basename, 0, -4);
        $slug = trim((string)$slug);
        if ($slug === '') {
            return null;
        }

        static $cache = [];
        if (array_key_exists($slug, $cache)) {
            return $cache[$slug];
        }

        try {
            $stmt = $pdo->prepare("SELECT f_generationSummary FROM tbl_m_system_template WHERE f_pageSlug = :slug LIMIT 1");
            $stmt->execute([':slug' => $slug]);
            $summaryJson = (string)($stmt->fetchColumn() ?: '');
            if ($summaryJson === '') {
                return $cache[$slug] = null;
            }

            $summary = json_decode($summaryJson, true);
            if (!is_array($summary)) {
                return $cache[$slug] = null;
            }

            $accessMode = trim((string)($summary['access_mode'] ?? ''));
            if (!in_array($accessMode, ['group_menu_based', 'super_admin_only'], true)) {
                return $cache[$slug] = null;
            }

            return $cache[$slug] = $accessMode;
        } catch (Throwable $e) {
            error_log('[prestasi_generated_page_access_mode] ' . $e->getMessage());
            return $cache[$slug] = null;
        }
    }
}

if (!function_exists('prestasi_resolve_page_access_policy')) {
    function prestasi_resolve_page_access_policy(string $currentPath, ?PDO $pdo = null): string {
        if ($currentPath === '' || !str_starts_with($currentPath, 'pages/')) {
            return 'non_page';
        }

        if (prestasi_is_modal_partial_path($currentPath)) {
            return 'modal_ajax_partial';
        }

        if (in_array($currentPath, prestasi_public_page_allowlist(), true)) {
            return 'public_logged_in';
        }

        if (in_array($currentPath, prestasi_super_admin_only_pages(), true)) {
            return 'super_admin_only';
        }

        if (in_array($currentPath, prestasi_custom_guard_pages(), true)) {
            return 'custom_guard';
        }

        $generatedAccessMode = prestasi_generated_page_access_mode($currentPath, $pdo);
        if ($generatedAccessMode === 'super_admin_only') {
            return 'super_admin_only';
        }
        if ($generatedAccessMode === 'group_menu_based') {
            return 'group_menu_based';
        }

        return 'group_menu_based';
    }
}

if (!function_exists('prestasi_public_logged_in_ajax')) {
    function prestasi_public_logged_in_ajax(): array {
        $list = defined('ACCESS_POLICY_PUBLIC_LOGGED_IN_AJAX') ? ACCESS_POLICY_PUBLIC_LOGGED_IN_AJAX : [];
        return is_array($list) ? $list : [];
    }
}

if (!function_exists('prestasi_super_admin_only_ajax')) {
    function prestasi_super_admin_only_ajax(): array {
        $list = defined('ACCESS_POLICY_SUPER_ADMIN_ONLY_AJAX') ? ACCESS_POLICY_SUPER_ADMIN_ONLY_AJAX : [];
        return is_array($list) ? $list : [];
    }
}

if (!function_exists('prestasi_custom_guard_ajax')) {
    function prestasi_custom_guard_ajax(): array {
        $list = defined('ACCESS_POLICY_CUSTOM_GUARD_AJAX') ? ACCESS_POLICY_CUSTOM_GUARD_AJAX : [];
        return is_array($list) ? $list : [];
    }
}

if (!function_exists('prestasi_authenticated_actions')) {
    function prestasi_authenticated_actions(): array {
        $list = defined('ACCESS_POLICY_AUTHENTICATED_ACTIONS') ? ACCESS_POLICY_AUTHENTICATED_ACTIONS : [];
        return is_array($list) ? $list : [];
    }
}

if (!function_exists('prestasi_resolve_request_access_policy')) {
    function prestasi_resolve_request_access_policy(string $currentPath, ?PDO $pdo = null): string {
        if ($currentPath === '') {
            return 'unknown';
        }

        $basename = basename($currentPath);

        foreach (prestasi_public_page_allowlist() as $path) {
            if ($currentPath === $path || $basename === basename($path)) {
                return 'public_logged_in';
            }
        }

        foreach (prestasi_super_admin_only_pages() as $path) {
            if ($currentPath === $path || $basename === basename($path)) {
                return 'super_admin_only';
            }
        }

        foreach (prestasi_custom_guard_pages() as $path) {
            if ($currentPath === $path || $basename === basename($path)) {
                return 'custom_guard';
            }
        }

        if (str_starts_with($currentPath, 'pages/')) {
            return prestasi_resolve_page_access_policy($currentPath, $pdo);
        }

        if (str_starts_with($currentPath, 'ajax/')) {
            foreach (prestasi_public_logged_in_ajax() as $path) {
                if ($currentPath === $path || $basename === basename($path)) {
                    return 'public_logged_in';
                }
            }
            foreach (prestasi_super_admin_only_ajax() as $path) {
                if ($currentPath === $path || $basename === basename($path)) {
                    return 'super_admin_only';
                }
            }
            foreach (prestasi_custom_guard_ajax() as $path) {
                if ($currentPath === $path || $basename === basename($path)) {
                    return 'custom_guard';
                }
            }
            return 'authenticated_default';
        }

        if (str_starts_with($currentPath, 'actions/')) {
            foreach (prestasi_authenticated_actions() as $path) {
                if ($currentPath === $path || $basename === basename($path)) {
                    return 'authenticated_default';
                }
            }
            return 'authenticated_default';
        }

        return 'unknown';
    }
}

if (!function_exists('prestasi_page_access_cache_key')) {
    function prestasi_page_access_cache_key(int $groupId): string {
        return 'page_access_map_' . $groupId;
    }
}

if (!function_exists('prestasi_load_allowed_page_paths')) {
    /**
     * @return array<string,bool>
     */
    function prestasi_load_allowed_page_paths(PDO $pdo, int $groupId): array {
        if ($groupId <= 0) {
            return [];
        }

        $cacheKey = prestasi_page_access_cache_key($groupId);
        if (isset($_SESSION[$cacheKey]) && is_array($_SESSION[$cacheKey])) {
            return $_SESSION[$cacheKey];
        }

        $paths = [];
        try {
            $stmtGroup = $pdo->prepare("SELECT f_menuAccess FROM tbl_m_group WHERE f_groupID = :gid LIMIT 1");
            $stmtGroup->execute([':gid' => $groupId]);
            $menuAccessCsv = trim((string)($stmtGroup->fetchColumn() ?: ''));
            if ($menuAccessCsv !== '') {
                $menuIds = array_values(array_unique(array_filter(array_map(static function ($value): int {
                    return is_numeric($value) ? (int)$value : 0;
                }, preg_split('/\\s*,\\s*/', $menuAccessCsv) ?: []))));

                if ($menuIds !== []) {
                    $placeholders = implode(',', array_fill(0, count($menuIds), '?'));
                    $stmtMenu = $pdo->prepare(
                        "SELECT f_path FROM tbl_m_menu WHERE f_flag = 1 AND f_menuID IN ($placeholders)"
                    );
                    $stmtMenu->execute($menuIds);
                    foreach (($stmtMenu->fetchAll(PDO::FETCH_COLUMN) ?: []) as $path) {
                        $normalized = prestasi_normalize_menu_path((string)$path);
                        if ($normalized === '') {
                            continue;
                        }
                        $paths[$normalized] = true;
                        $basename = basename($normalized);
                        if ($basename !== '') {
                            $paths[$basename] = true;
                            $paths['pages/' . $basename] = true;
                        }
                    }
                }
            }
        } catch (Throwable $e) {
            error_log('[prestasi_load_allowed_page_paths] ' . $e->getMessage());
        }

        $_SESSION[$cacheKey] = $paths;
        return $paths;
    }
}

if (!function_exists('prestasi_user_can_access_current_page')) {
    function prestasi_user_can_access_current_page(array $profile = [], ?PDO $pdo = null): bool {
        $currentPath = prestasi_current_page_relative_path();
        if ($currentPath === '' || !str_starts_with($currentPath, 'pages/')) {
            return true;
        }

        $policy = prestasi_resolve_page_access_policy($currentPath, $pdo);
        if ($policy === 'modal_ajax_partial') {
            return prestasi_has_authenticated_session() && prestasi_is_ajax_like_request();
        }

        if ($policy === 'public_logged_in') {
            return true;
        }

        $isSuperAdmin = $pdo instanceof PDO && is_user_super_admin($profile, $pdo);
        if ($policy === 'super_admin_only') {
            if (prestasi_is_userlist_page_path($currentPath)) {
                return prestasi_project_userlist_policy_decision(
                    'project_userlist_can_access_page',
                    $isSuperAdmin,
                    [$profile, $pdo, $currentPath, $isSuperAdmin]
                );
            }
            return $isSuperAdmin;
        }

        if ($isSuperAdmin) {
            return true;
        }

        $resolved = prestasi_resolve_active_group($profile, $pdo);
        $groupId = (int)($resolved['id'] ?? 0);
        if ($groupId <= 0) {
            return false;
        }

        $allowedPaths = ($pdo instanceof PDO) ? prestasi_load_allowed_page_paths($pdo, $groupId) : [];
        if ($allowedPaths === []) {
            return false;
        }

        if (isset($allowedPaths[$currentPath])) {
            return true;
        }

        $basename = basename($currentPath);
        return $basename !== '' && isset($allowedPaths[$basename]);
    }
}

if (!function_exists('prestasi_user_can_access_current_request')) {
    function prestasi_user_can_access_current_request(array $profile = [], ?PDO $pdo = null): bool {
        $currentPath = prestasi_current_request_relative_path();
        $policy = prestasi_resolve_request_access_policy($currentPath);
        $resolved = prestasi_resolve_active_group($profile, $pdo);
        $activeGroupId = (int)($resolved['id'] ?? 0);
        $activeGroupKod = (string)($resolved['kod'] ?? '');
        $isSuperAdmin = $pdo instanceof PDO && is_user_super_admin($profile, $pdo);

        prestasi_access_trace_log(sprintf(
            '[access_trace] script=%s uri=%s inferred=%s policy=%s active_group_id=%d active_group_kod=%s is_super_admin=%s',
            (string)($_SERVER['SCRIPT_NAME'] ?? ''),
            (string)($_SERVER['REQUEST_URI'] ?? ''),
            $currentPath,
            $policy,
            $activeGroupId,
            $activeGroupKod,
            $isSuperAdmin ? '1' : '0'
        ));

        if ($policy === 'modal_ajax_partial') {
            return prestasi_has_authenticated_session() && prestasi_is_ajax_like_request();
        }

        if (in_array($policy, ['unknown', 'public_logged_in', 'authenticated_default', 'custom_guard'], true)) {
            return true;
        }

        if ($policy === 'super_admin_only') {
            if (prestasi_is_userlist_page_path($currentPath)) {
                return prestasi_project_userlist_policy_decision(
                    'project_userlist_can_access_page',
                    $isSuperAdmin,
                    [$profile, $pdo, $currentPath, $isSuperAdmin]
                );
            }
            if (prestasi_is_userlist_ajax_path($currentPath)) {
                return prestasi_project_userlist_policy_decision(
                    'project_userlist_can_access_ajax',
                    $isSuperAdmin,
                    [$profile, $pdo, $currentPath, $isSuperAdmin]
                );
            }
            return $isSuperAdmin;
        }

        if ($currentPath !== '' && str_starts_with($currentPath, 'pages/')) {
            return prestasi_user_can_access_current_page($profile, $pdo);
        }

        return true;
    }
}

if (!function_exists('prestasi_render_page_forbidden')) {
    function prestasi_render_page_forbidden(string $message = ''): never {
        http_response_code(403);
        if (!headers_sent()) {
            header('Content-Type: text/html; charset=utf-8');
        }

        $lang = htmlspecialchars((string)($_SESSION['lang'] ?? 'ms'), ENT_QUOTES, 'UTF-8');
        $safeMessage = htmlspecialchars($message !== '' ? $message : (string)(__('manual_unauthorized_access') ?: 'Anda tidak dibenarkan mengakses halaman ini.'), ENT_QUOTES, 'UTF-8');
        echo '<!doctype html><html lang="' . $lang . '"><head><meta charset="utf-8"><title>403</title></head><body>' . $safeMessage . '</body></html>';
        exit;
    }
}

if (!function_exists('prestasi_forbidden_redirect_path')) {
    function prestasi_forbidden_redirect_path(): string {
        if (function_exists('base_path')) {
            return base_path('pages/dashboard.php');
        }

        return '/pages/dashboard.php';
    }
}

if (!function_exists('prestasi_redirect_with_access_notice')) {
    function prestasi_redirect_with_access_notice(string $textKey = 'access_notice_text', string $titleKey = 'access_notice_title'): never {
        if (function_exists('set_alert')) {
            set_alert([
                'type' => 'sweet',
                'icon' => 'warning',
                'title' => $titleKey,
                'text' => $textKey,
                'confirm' => true,
                'position' => 'center',
                'is_key' => true,
            ]);
        }

        $redirect = prestasi_forbidden_redirect_path();
        if (!headers_sent()) {
            header('Location: ' . $redirect, true, 302);
        }
        exit;
    }
}

if (!function_exists('prestasi_redirect_with_generic_access_notice')) {
    function prestasi_redirect_with_generic_access_notice(): never {
        prestasi_redirect_with_access_notice('access_notice_text');
    }
}

if (!function_exists('ensure_current_page_access')) {
    function ensure_current_page_access(array $profile = [], ?PDO $pdo = null, ?string $message = null): void {
        if (prestasi_user_can_access_current_page($profile, $pdo)) {
            return;
        }

        prestasi_render_page_forbidden($message ?? (string)(__('manual_unauthorized_access') ?: 'Anda tidak dibenarkan mengakses halaman ini.'));
    }
}

if (!function_exists('prestasi_user_can_access_page_path')) {
    function prestasi_user_can_access_page_path(string $pagePath, array $profile = [], ?PDO $pdo = null): bool {
        $pagePath = prestasi_normalize_menu_path($pagePath);
        if ($pagePath === '') {
            return false;
        }

        if (!str_starts_with($pagePath, 'pages/')) {
            $pagePath = 'pages/' . basename($pagePath);
        }

        $policy = prestasi_resolve_page_access_policy($pagePath, $pdo);
        if ($policy === 'modal_ajax_partial') {
            return false;
        }

        if ($policy === 'public_logged_in' || $policy === 'custom_guard') {
            return true;
        }

        $isSuperAdmin = $pdo instanceof PDO && is_user_super_admin($profile, $pdo);
        if ($policy === 'super_admin_only') {
            if (prestasi_is_userlist_page_path($pagePath)) {
                return prestasi_project_userlist_policy_decision(
                    'project_userlist_can_access_page',
                    $isSuperAdmin,
                    [$profile, $pdo, $pagePath, $isSuperAdmin]
                );
            }
            return $isSuperAdmin;
        }

        if ($isSuperAdmin) {
            return true;
        }

        $resolved = prestasi_resolve_active_group($profile, $pdo);
        $groupId = (int)($resolved['id'] ?? 0);
        if ($groupId <= 0 || !$pdo instanceof PDO) {
            return false;
        }

        $allowedPaths = prestasi_load_allowed_page_paths($pdo, $groupId);
        if (isset($allowedPaths[$pagePath])) {
            return true;
        }

        $basename = basename($pagePath);
        return $basename !== '' && isset($allowedPaths[$basename]);
    }
}

if (!function_exists('require_page_access')) {
    function require_page_access(string $pagePath, ?array $profile = null, ?PDO $pdo = null, ?string $message = null): void {
        $profile = $profile ?? ($GLOBALS['profile'] ?? []);
        $pdo = $pdo ?? ($GLOBALS['pdo_mysql'] ?? null);
        $profile = is_array($profile) ? $profile : [];
        $pdo = $pdo instanceof PDO ? $pdo : null;

        if (prestasi_user_can_access_page_path($pagePath, $profile, $pdo)) {
            return;
        }

        $defaultMessage = $message ?? (string)(__('manual_unauthorized_access') ?: 'Anda tidak dibenarkan mengakses halaman ini.');
        if (prestasi_is_ajax_like_request()) {
            while (ob_get_level() > 0) {
                @ob_end_clean();
            }
            http_response_code(403);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode([
                'success' => false,
                'error' => true,
                'message' => $defaultMessage,
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            exit;
        }

        prestasi_render_page_forbidden($defaultMessage);
    }
}

if (!function_exists('ensure_current_request_access')) {
    function ensure_current_request_access(array $profile = [], ?PDO $pdo = null, ?string $message = null): void {
        if (prestasi_user_can_access_current_request($profile, $pdo)) {
            prestasi_access_trace_log('[access_trace] decision=allow');
            return;
        }

        $currentPath = prestasi_current_request_relative_path();
        $defaultMessage = $message ?? (string)(__('manual_unauthorized_access') ?: 'Anda tidak dibenarkan mengakses halaman ini.');
        prestasi_access_trace_log(sprintf('[access_trace] decision=deny inferred=%s', $currentPath));

        $isAjaxLike = false;
        if (function_exists('request_is_ajax_like')) {
            $isAjaxLike = request_is_ajax_like();
        } else {
            $requestedWith = strtolower(trim((string)($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '')));
            $accept = strtolower(trim((string)($_SERVER['HTTP_ACCEPT'] ?? '')));
            $isAjaxLike = $requestedWith === 'xmlhttprequest'
                || ($accept !== '' && (str_contains($accept, 'application/json') || str_contains($accept, 'text/json')));
        }

        if ($isAjaxLike || str_starts_with($currentPath, 'ajax/') || str_starts_with($currentPath, 'actions/')) {
            while (ob_get_level() > 0) {
                @ob_end_clean();
            }
            http_response_code(403);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode([
                'success' => false,
                'error' => true,
                'message' => $defaultMessage,
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            exit;
        }

        $missingPagePath = prestasi_requested_missing_page_from_uri();
        if ($missingPagePath !== '') {
            prestasi_access_trace_log(sprintf('[access_trace] redirect=missing-page requested=%s', $missingPagePath));
            prestasi_redirect_with_access_notice('access_missing_page_text');
        }

        prestasi_redirect_with_access_notice('manual_unauthorized_access');
    }
}

if (!function_exists('prestasi_access_trace_log')) {
    function prestasi_access_trace_log(string $message): void {
        $enabled = $_ENV['ACCESS_TRACE_LOG_ENABLED'] ?? getenv('ACCESS_TRACE_LOG_ENABLED');
        if (!is_string($enabled) || !in_array(strtolower(trim($enabled)), ['1', 'true', 'yes', 'on'], true)) {
            return;
        }

        $logDir = realpath(__DIR__ . '/../../log') ?: (__DIR__ . '/../../log');
        if (!is_dir($logDir)) {
            @mkdir($logDir, 0777, true);
        }
        $traceFile = rtrim($logDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'access-trace.log';
        @file_put_contents($traceFile, '[' . date('Y-m-d H:i:s') . '] ' . trim($message) . PHP_EOL, FILE_APPEND | LOCK_EX);

        if (function_exists('app_log')) {
            app_log($message);
            return;
        }

        error_log($message);
    }
}
