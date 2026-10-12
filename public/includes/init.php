<?php
/**
 * IQS FRAMEWORK CORE FILE
 *
 * READ ONLY for downstream project programmers.
 * Do not modify this file directly in template or cloned projects.
 * Custom changes must be implemented in project-specific files
 * or approved extension points.
 */// ===============================================
// ✅ INIT Sistem e-Prestasi (header-safe & no-legacy echo)
// ===============================================

// (Opsyen debug sementara; tukar ke false bila siap)
// define('AUDIT_DEBUG', true);

// 1) Session + (optional) output buffering
// Sanitize incoming session id from cookie to avoid PHP warnings when clients
// supply an invalid or malformed session id (e.g., pasted values). Only allow
// characters A-Z a-z 0-9 - and , which are permitted by PHP session id rules.
if (isset($_COOKIE[session_name()])) {
    $rawSid = (string)$_COOKIE[session_name()];
    if (!preg_match('/^[A-Za-z0-9\-,]+$/', $rawSid)) {
        // Remove invalid cookie to allow PHP to generate a fresh session id
        unset($_COOKIE[session_name()]);
        // Also clear from $_REQUEST/$_SERVER to be safe
        if (isset($_REQUEST[session_name()])) unset($_REQUEST[session_name()]);
        if (isset($_GET[session_name()])) unset($_GET[session_name()]);
        if (isset($_POST[session_name()])) unset($_POST[session_name()]);
    }
}
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_samesite', 'Lax');
    ini_set('session.cookie_secure', isset($_SERVER['HTTPS']) ? '1' : '0');
    ini_set('session.cookie_httponly', '1');
    ini_set('session.use_strict_mode', '1');
    session_start();
}
if (!headers_sent()) {
    ob_start(); // elak "headers already sent" semasa redirect lang
}

// Force a temp directory inside project to satisfy open_basedir (avoid C:\Windows\TEMP)
$__safeTemp = realpath(__DIR__ . '/../cache/tmp') ?: (__DIR__ . '/../cache/tmp');
if (!is_dir($__safeTemp)) {
    @mkdir($__safeTemp, 0777, true);
}
ini_set('sys_temp_dir', $__safeTemp);

// Pastikan semua halaman dihantar sebagai UTF-8 supaya pelayar tidak
// salah tafsirkan bait -- ini juga membantu mencegah mojibake pada teks
// yang mempunyai watak seperti en-dash dan ellipsis.
if (!headers_sent()) {
    header('Content-Type: text/html; charset=utf-8');
}

// 2) LANG: tangkap ?lang=... awal, proses penuh selepas dependency/DB siap
function __current_url_without_lang(): string {
    $uri   = $_SERVER['REQUEST_URI'] ?? '/';
    $parts = parse_url($uri);
    $path  = $parts['path'] ?? '/';
    $qsArr = [];
    if (!empty($parts['query'])) parse_str($parts['query'], $qsArr);
    unset($qsArr['lang']);
    $q = http_build_query($qsArr);
    return $path . ($q ? ('?' . $q) : '');
}
$__requestedLang = isset($_GET['lang']) ? trim((string)$_GET['lang']) : null;

// 3) Requires (tiada output)
require_once __DIR__ . '/../setting/function.php';
require_once __DIR__ . '/../classes/HelperLoader.php';
require_once __DIR__ . '/../classes/Database.php';
require_once __DIR__ . '/../classes/User.php';
require_once __DIR__ . '/../classes/Config.php';
require_once __DIR__ . '/../classes/SystemConfigConstants.php';
require_once __DIR__ . '/../classes/FrameworkException.php';
require_once __DIR__ . '/../classes/ApplicationException.php';
require_once __DIR__ . '/../classes/ValidationException.php';
require_once __DIR__ . '/../classes/ExternalServiceException.php';
require_once __DIR__ . '/../classes/FrameworkExceptionHandler.php';
require_once __DIR__ . '/../classes/ExternalHttpResponse.php';
require_once __DIR__ . '/../classes/ExternalHttpClient.php';

// 4) Autoload helpers dalam setting/helper
$loader = new HelperLoader(__DIR__ . '/../setting/helper');
$loader->loadAll();

/* -------------------------------------------------------
   4.1) AUDIT REQUEST START (hook awal request)
   - Guna helper audit_* yang di-autoload dari setting/helper/audit_helper.php
   - Simpan $__REQUEST_ID & $__REQ_START untuk tamatkan di shutdown.
   - Isi 'route' awal (nama skrip/path) supaya audit_request.route tak kosong.
-------------------------------------------------------- */
$__REQ_START   = microtime(true);
$__REQUEST_ID  = null;

// Tentukan routeName auto (nama skrip atau path)
$__routeName = (function (): string {
    $script = $_SERVER['SCRIPT_NAME'] ?? '';
    if ($script !== '') return ltrim($script, '/');
    $uri  = $_SERVER['REQUEST_URI'] ?? '/';
    $path = parse_url($uri, PHP_URL_PATH) ?: '/';
    return ltrim($path, '/');
})();

// user_id mungkin belum wujud di awal init — isi null dulu
audit_safe(function() use (&$__REQUEST_ID, $__routeName) {
    $ctx = [
        'session_id' => session_id() ?: null,
        'user_id'    => $_SESSION['f_nopekerja'] ?? $_SESSION['user']['f_nopekerja'] ?? null,
        'login_id'   => $_SESSION['f_loginID'] ?? $_SESSION['user']['f_loginID'] ?? null,
        'route'      => $__routeName,                           // SET ROUTE AWAL
    ];
    $__REQUEST_ID = audit_logger()->logRequestStart($ctx);

    // Simpan global utk helper bind (fallback kuat)
    $GLOBALS['__AUDIT_REQUEST_ID'] = $__REQUEST_ID;

    if (defined('AUDIT_DEBUG') && AUDIT_DEBUG) {
        error_log("[AUDIT] START rid=" . ($__REQUEST_ID ?? 'null') . " route=" . ($__routeName ?? 'null') . " sid=" . session_id());
    }
});

if (!function_exists('request_is_ajax_like')) {
    function request_is_ajax_like(): bool {
        $requestedWith = strtolower(trim((string)($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '')));
        if ($requestedWith === 'xmlhttprequest') {
            return true;
        }

        $accept = strtolower(trim((string)($_SERVER['HTTP_ACCEPT'] ?? '')));
        return $accept !== '' && (str_contains($accept, 'application/json') || str_contains($accept, 'text/json'));
    }
}

if (function_exists('impersonation_write_guard')) {
    impersonation_write_guard();
}

if (!function_exists('auth_normalize_login_id')) {
    function auth_normalize_login_id(?string $loginID): string
    {
        $value = trim((string)$loginID);
        if ($value === '') {
            return '';
        }

        if (filter_var($value, FILTER_VALIDATE_EMAIL)) {
            return strtolower($value);
        }

        return $value;
    }
}

if (!function_exists('render_bootstrap_db_failure_response')) {
    function render_bootstrap_db_failure_response(string $publicMessage = ''): never
    {
        $lang = strtolower(trim((string)($_SESSION['lang'] ?? 'ms')));
        $title = $lang === 'en' ? 'Service Temporarily Unavailable' : 'Perkhidmatan Tidak Tersedia Buat Sementara';
        $message = trim($publicMessage);
        if ($message === '') {
            $message = $lang === 'en'
                ? 'The system is temporarily unavailable. Please try again again in a moment.'
                : 'Sistem tidak dapat diakses buat sementara waktu. Sila cuba semula sebentar lagi.';
        }

        while (ob_get_level() > 0) {
            @ob_end_clean();
        }

        http_response_code(503);
        if (!headers_sent()) {
            header('Retry-After: 60');
        }

        if (request_is_ajax_like()) {
            if (!headers_sent()) {
                header('Content-Type: application/json; charset=utf-8');
            }
            echo json_encode([
                'success' => false,
                'error' => true,
                'message' => $message,
                'service_unavailable' => true,
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            exit;
        }

        if (!headers_sent()) {
            header('Content-Type: text/html; charset=utf-8');
        }

        $safeTitle = htmlspecialchars($title, ENT_QUOTES, 'UTF-8');
        $safeMessage = htmlspecialchars($message, ENT_QUOTES, 'UTF-8');
        echo '<!doctype html><html lang="' . htmlspecialchars($lang, ENT_QUOTES, 'UTF-8') . '"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>' . $safeTitle . '</title><style>body{margin:0;font-family:Segoe UI,Arial,sans-serif;background:#f8fafc;color:#0f172a;display:flex;min-height:100vh;align-items:center;justify-content:center;padding:24px}.panel{max-width:560px;width:100%;background:#fff;border:1px solid #e2e8f0;border-radius:16px;padding:32px;box-shadow:0 12px 40px rgba(15,23,42,.08)}h1{margin:0 0 12px;font-size:1.4rem}p{margin:0;color:#475569;line-height:1.6}</style></head><body><main class="panel"><h1>' . $safeTitle . '</h1><p>' . $safeMessage . '</p></main></body></html>';
        exit;
    }
}

// 5) MySQL + User
// Resolve explicit runtime selection before the first MySQL singleton is built.
// If no explicit session/env value exists, config DB is read below and the
// initial bootstrap connection is refreshed when it points to a different env.
$__mainDbEnvironmentPreselected = false;
if (!defined('MAIN_DB_ENVIRONMENT')) {
    $candidateMainDbEnvironment = strtolower(trim((string)($_SESSION['MAIN_DB_ENVIRONMENT'] ?? '')));
    if ($candidateMainDbEnvironment === '') {
        $candidateMainDbEnvironment = strtolower(trim((string)($_ENV['MAIN_DB_ENVIRONMENT'] ?? $_SERVER['MAIN_DB_ENVIRONMENT'] ?? getenv('MAIN_DB_ENVIRONMENT') ?? '')));
    }

    if ($candidateMainDbEnvironment !== '' && in_array($candidateMainDbEnvironment, SystemConfigConstants::ALLOWED_MAIN_DB_ENVIRONMENTS, true)) {
        define('MAIN_DB_ENVIRONMENT', $candidateMainDbEnvironment);
        $__mainDbEnvironmentPreselected = true;
    }
}

try {
    $pdo_mysql = Database::getInstance('mysql')->getConnection();
    $user      = new User($pdo_mysql);
    $config    = new Config($pdo_mysql);
} catch (Throwable $e) {
    error_log('[init] bootstrap database failure: ' . $e->getMessage());
    render_bootstrap_db_failure_response();
}

if (function_exists('impersonation_enforce_timeout')) {
    impersonation_enforce_timeout($pdo_mysql);
}

if (!defined('MAIN_DB_ENVIRONMENT')) {
    $mainDbEnvironment = SystemConfigConstants::DEFAULT_MAIN_DB_ENVIRONMENT;
    try {
        $cfgMainDbEnvironment = strtolower(trim((string)$config->getMainDbEnvironment(SystemConfigConstants::DEFAULT_MAIN_DB_ENVIRONMENT)));
        if ($cfgMainDbEnvironment !== '' && in_array($cfgMainDbEnvironment, SystemConfigConstants::ALLOWED_MAIN_DB_ENVIRONMENTS, true)) {
            $mainDbEnvironment = $cfgMainDbEnvironment;
        }
    } catch (Throwable $e) {
        // ignore, fallback below
    }
    define('MAIN_DB_ENVIRONMENT', $mainDbEnvironment);
}

if (
    !$__mainDbEnvironmentPreselected
    && defined('MAIN_DB_ENVIRONMENT')
    && MAIN_DB_ENVIRONMENT !== SystemConfigConstants::DEFAULT_MAIN_DB_ENVIRONMENT
) {
    Database::clearInstance('mysql');
    Database::clearInstance('mysql_prod');
    Database::clearInstance('mysql_dev');
    try {
        $pdo_mysql = Database::getInstance('mysql')->getConnection();
        $user      = new User($pdo_mysql);
        $config    = new Config($pdo_mysql);
    } catch (Throwable $e) {
        error_log('[init] runtime MySQL environment reconnect failure: ' . $e->getMessage());
        render_bootstrap_db_failure_response();
    }
}

if (!function_exists('tetapan_sistem_ajax_debug_log')) {
    function tetapan_sistem_ajax_debug_log(string $stage, array $context = []): void
    {
        $enabled = $_ENV['TETAPAN_AJAX_DEBUG_LOG_ENABLED'] ?? getenv('TETAPAN_AJAX_DEBUG_LOG_ENABLED');
        if (!is_string($enabled) || !in_array(strtolower(trim($enabled)), ['1', 'true', 'yes', 'on'], true)) {
            return;
        }

        $uri = (string)($_SERVER['REQUEST_URI'] ?? '');
        if (
            stripos($uri, '/pages/tetapan-sistem.php') === false &&
            stripos($uri, '/ajax/uji-emel.php') === false
        ) {
            return;
        }

        $logPath = __DIR__ . '/../log/tetapan-sistem-ajax-debug.log';
        $payload = array_merge([
            'timestamp' => date('c'),
            'stage' => $stage,
            'uri' => $uri,
            'method' => (string)($_SERVER['REQUEST_METHOD'] ?? ''),
            'ajax_like' => request_is_ajax_like(),
            'session_id' => session_id(),
            'login_id' => (string)($_SESSION['f_loginID'] ?? $_SESSION['user']['f_loginID'] ?? ''),
            'remote_addr' => (string)($_SERVER['REMOTE_ADDR'] ?? ''),
            'x_requested_with' => (string)($_SERVER['HTTP_X_REQUESTED_WITH'] ?? ''),
            'accept' => (string)($_SERVER['HTTP_ACCEPT'] ?? ''),
        ], $context);

        @file_put_contents(
            $logPath,
            json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL,
            FILE_APPEND | LOCK_EX
        );
    }
}

if (!function_exists('terminated_session_login_url')) {
    function terminated_session_login_url(): string {
        $scriptName = str_replace('\\', '/', (string)($_SERVER['SCRIPT_NAME'] ?? '/'));
        $dir = rtrim(dirname($scriptName), '/');
        if ($dir === '' || $dir === '.') {
            return '/index.php';
        }

        $baseDir = preg_replace('#/(pages|ajax|controllers|includes)$#', '', $dir);
        $baseDir = is_string($baseDir) ? rtrim($baseDir, '/') : $dir;

        return ($baseDir === '' ? '' : $baseDir) . '/index.php';
    }
}

if (!function_exists('force_end_terminated_session')) {
    function force_end_terminated_session(PDO $pdo, bool $redirectOnEndedSession = true): void {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            return;
        }

        static $checked = false;
        if ($checked) {
            return;
        }
        $checked = true;

        $sessionId = session_id();
        if ($sessionId === '') {
            return;
        }

        $isAuthenticated = !empty($_SESSION['f_stafID'])
            || !empty($_SESSION['f_loginID'])
            || !empty($_SESSION['user']);
        if (!$isAuthenticated) {
            return;
        }

        try {
            $stmt = $pdo->prepare("
                SELECT ended_at
                FROM audit_session
                WHERE session_id = :session_id
                LIMIT 1
            ");
            $stmt->execute([':session_id' => $sessionId]);
            $sessionRow = $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (Throwable $e) {
            error_log('[init] terminated-session check failed: ' . $e->getMessage());
            return;
        }

        if (empty($sessionRow['ended_at'])) {
            return;
        }

        $_SESSION = [];

        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', [
                'expires'  => time() - 3600,
                'path'     => $params['path'] ?? '/',
                'domain'   => $params['domain'] ?? '',
                'secure'   => (bool)($params['secure'] ?? false),
                'httponly' => (bool)($params['httponly'] ?? true),
                'samesite' => 'Lax',
            ]);
        }

        session_destroy();

        if (!$redirectOnEndedSession) {
            if (session_status() !== PHP_SESSION_ACTIVE) {
                session_start();
            }
            return;
        }

        $title = (string)(__('session_terminated_title') ?: 'Sesi Ditamatkan');
        $text = (string)(__('session_terminated_text') ?: 'Sesi anda telah ditamatkan oleh pentadbir. Sila log masuk semula.');
        $redirect = terminated_session_login_url();

        if (request_is_ajax_like()) {
            tetapan_sistem_ajax_debug_log('bootstrap_session_terminated', [
                'redirect' => $redirect,
            ]);
            while (ob_get_level() > 0) {
                @ob_end_clean();
            }
            http_response_code(401);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode([
                'success' => false,
                'error' => true,
                'session_terminated' => true,
                'title' => $title,
                'message' => $text,
                'redirect' => $redirect,
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }

        session_start();
        if (function_exists('set_alert')) {
            set_alert([
                'type' => 'sweet',
                'title' => $title,
                'text' => $text,
                'icon' => 'warning',
            ]);
        }
        session_write_close();

        header('Location: ' . $redirect);
        exit;
    }
}

force_end_terminated_session($pdo_mysql, !defined('BDR_NOTIFICATION_CONFIRM_PUBLIC_PAGE'));

$f_loginID = $_SESSION['f_loginID'] ?? null;
$f_stafID  = $_SESSION['f_stafID'] ?? null;
$profile   = [];
if ($f_loginID) {
    $profile = $user->getProfileByLoginID((string)$f_loginID) ?: [];
}
if (!$profile && $f_stafID) {
    $profile = $user->getProfile((string)$f_stafID) ?: [];
}
if (!empty($profile['f_loginID']) && empty($_SESSION['f_loginID'])) {
    $_SESSION['f_loginID'] = (string)$profile['f_loginID'];
    $f_loginID = $_SESSION['f_loginID'];
}

// Ensure active/default role are initialized for the session (role switch safety)
if (!isset($_SESSION['group_default_id']) && !empty($profile['f_groupID'])) {
    $_SESSION['group_default_id'] = (int)$profile['f_groupID'];
}
if (!isset($_SESSION['group_active_id']) && !empty($profile['f_groupID'])) {
    $_SESSION['group_active_id'] = (int)$profile['f_groupID'];
}

/* -------------------------------------------------------
   5.1) KEMASKINI audit_request DENGAN user_id & route SELEPAS PROFILE SIAP
   - Kalau permulaan tadi user_id null, dan sekarang kita dah tahu f_nopekerja,
     bind semula audit_request supaya rekod terikat pada actor & route tepat.
-------------------------------------------------------- */
// Bind request ownership to the real actor during View As. The effective target
// remains available in audit event metadata via impersonation context.
$auditOwnerProfile = $profile;
if (function_exists('impersonation_is_active') && impersonation_is_active() && function_exists('impersonation_actor')) {
    $impersonationActorContext = impersonation_actor();
    if (!empty($impersonationActorContext)) {
        $auditOwnerProfile = [
            'f_userID' => $impersonationActorContext['user_id'] ?? null,
            'f_loginID' => $impersonationActorContext['login_id'] ?? null,
            'f_nopekerja' => $impersonationActorContext['nopekerja'] ?? $impersonationActorContext['f_nopekerja'] ?? null,
        ];
    }
}

// Bind user_id for audit. audit_event.user_id and audit_request.user_id store
// the staff number (`f_nopekerja`), not the MySQL primary key (`f_userID`).
$userIdToBind = null;
$candidate = $auditOwnerProfile['f_nopekerja'] ?? $_SESSION['f_nopekerja'] ?? $_SESSION['user']['f_nopekerja'] ?? $_SESSION['f_stafID'] ?? $_SESSION['user']['f_stafID'] ?? null;
if ($candidate) {
    if (is_numeric($candidate)) {
        $userIdToBind = (int)$candidate;
    } elseif (preg_match('/^(\d+)/', (string)$candidate, $m)) {
        $userIdToBind = (int)$m[1];
    }
}
if ($userIdToBind !== null) {
    $loginIdToBind = trim((string)($auditOwnerProfile['f_loginID'] ?? $_SESSION['f_loginID'] ?? $_SESSION['user']['f_loginID'] ?? ''));
    if (function_exists('audit_request_bind_identity')) {
        audit_request_bind_identity($userIdToBind, $loginIdToBind !== '' ? $loginIdToBind : null, $__REQUEST_ID ?? null);
    } else {
        audit_request_bind_user($userIdToBind, $__REQUEST_ID ?? null);
    }
}

// Pastikan route juga direkod (kalau belum)
if (!empty($__routeName)) {
    audit_request_set_route($__routeName, $__REQUEST_ID ?? null);
}

// Enforce centralized authorization for pages and selected ajax/actions.
if (
    PHP_SAPI !== 'cli'
    && function_exists('ensure_current_request_access')
    && !defined('BDR_NOTIFICATION_CONFIRM_PUBLIC_PAGE')
) {
    ensure_current_request_access($profile, $pdo_mysql);
}

if (defined('AUDIT_DEBUG') && AUDIT_DEBUG) {
    error_log("[AUDIT] BIND uid=" . (($userIdToBind ?? null) !== null ? (string)$userIdToBind : 'null') . " rid=" . ($__REQUEST_ID ?? 'null') . " route=" . ($__routeName ?? 'null'));
}

// 6) Bahasa: default ikut profil jika belum ada, else 'ms'
if ($__requestedLang !== null) {
    $fallbackAllowed = ['ms', 'en', 'zh', 'ta'];
    $activeLanguages = [];
    try {
        $activeLanguages = array_values(array_filter($config->getBahasaAktif() ?: []));
    } catch (\Throwable $e) {
        error_log('[init] Failed reading active languages for lang switch: ' . $e->getMessage());
    }

    $allowedLanguages = $activeLanguages ?: $fallbackAllowed;
    if (in_array($__requestedLang, $allowedLanguages, true)) {
        $_SESSION['lang'] = $__requestedLang;

        if (($f_loginID || $f_stafID) && !(function_exists('impersonation_is_active') && impersonation_is_active())) {
            try {
                if ($f_loginID) {
                    $user->updateLanguagePreferenceByLoginID((string)$f_loginID, $__requestedLang);
                } else {
                    $user->updateLanguagePreference((string)$f_stafID, $__requestedLang);
                }
                if (is_array($profile)) {
                    $profile['f_lang'] = $__requestedLang;
                }
            } catch (\Throwable $e) {
                error_log('[init] Failed syncing topbar language preference: ' . $e->getMessage());
            }
        }
    }

    header('Location: ' . __current_url_without_lang(), true, 302);
    exit;
}

if (!isset($_SESSION['lang'])) {
    $configDefaultLanguage = null;
    $activeLanguages = ['ms', 'en'];
    try {
        $configDefaultLanguage = $config->getDefaultBahasa(SystemConfigConstants::DEFAULT_LANGUAGE);
        $activeLanguages = $config->getBahasaAktif();
    } catch (\Throwable $e) {
        error_log('[init] Failed reading default language: ' . $e->getMessage());
    }
    $pref = $profile['f_lang'] ?? null;
    $_SESSION['lang'] = in_array($pref, $activeLanguages, true)
        ? $pref
        : ($configDefaultLanguage ?: 'ms');
}
$lang = $_SESSION['lang'];

// 7) translations_js (optional untuk front-end) – hanya jika belum diset oleh page
if (!isset($translations_js)) {
    $all = function_exists('lang_lines') ? lang_lines((string)$lang) : [];
    $whitelistFile = __DIR__ . '/../includes/js_keys_whitelist.php';
    if (file_exists($whitelistFile)) {
        $allow = require $whitelistFile; // array of keys
        $translations_js = array_intersect_key($all, array_flip((array)$allow));
    } else {
        $translations_js = []; // fallback selamat
    }
}

// 8) Environment + Error reporting
// SECURITY CRITICAL – DO NOT MODIFY: centralized environment detection & debug exposure control
// Centralized environment detection (dev/staging/production)
if (!function_exists('app_env')) {
    function app_env(): string {
        static $env = null;
        if ($env !== null) return $env;
        $raw = $_ENV['APP_ENV'] ?? getenv('APP_ENV') ?? ($_ENV['ENVIRONMENT'] ?? getenv('ENVIRONMENT') ?? '');
        $raw = strtolower(trim((string)$raw));
        if ($raw === '') {
            // Fallback: derive from existing dev indicator (safe if helper not yet defined)
            $raw = (function_exists('is_development_mode') && is_development_mode()) ? 'development' : 'production';
        }
        $map = [
            'dev' => 'development',
            'development' => 'development',
            'staging' => 'staging',
            'stage' => 'staging',
            'prod' => 'production',
            'production' => 'production',
        ];
        $env = $map[$raw] ?? 'production'; // STABLE: environment detection
        return $env;
    }
}

// SECURITY CRITICAL – DO NOT MODIFY: environment flags drive production safety
$__APP_ENV = app_env();
$__IS_DEV = ($__APP_ENV === 'development');

// SECURITY CRITICAL – DO NOT MODIFY: production must not leak debug output
// Error reporting: ON for dev, OFF for production/staging
ini_set('display_errors', $__IS_DEV ? '1' : '0');
ini_set('display_startup_errors', $__IS_DEV ? '1' : '0');
error_reporting($__IS_DEV ? E_ALL : E_ALL & ~E_DEPRECATED);

// 8.1) Ensure application log directory exists and route PHP error_log there
// Provide a simple helper for application code to write structured logs
require_once __DIR__ . '/logger.php';
$__LOG_DIR = app_log_directory();
$__APP_LOG_FILE = app_log_file_path();
// Prefer app-level error log so error_log() writes end up in project folder
@ini_set('error_log', $__APP_LOG_FILE);

// 9) CSRF token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// 10) Theme (ikut user, fallback Config default)
$themeSetting = [];
if (!empty($profile['f_themeSetting'])) {
    $dec = json_decode((string)$profile['f_themeSetting'], true);
    if (is_array($dec)) $themeSetting = $dec;
}
if (!$themeSetting) {
    // ambil default dari tbl_m_config (group=system, key=default_theme)
    $themeSetting = $config->getTema();
}
$_SESSION['theme.menu']   = $themeSetting['sidebarColor'] ?? 'light';
$_SESSION['theme.topbar'] = $themeSetting['topbarColor']  ?? 'light';
$_SESSION['theme.layout'] = $themeSetting['layoutMode']   ?? 'light';
$_SESSION['theme.sidebar'] = $_SESSION['theme.menu'];
$_SESSION['theme.topbar_custom_seed'] = $themeSetting['topbarCustomSeed'] ?? SystemConfigConstants::DEFAULT_THEME_TOPBAR_CUSTOM_SEED;
$_SESSION['theme.sidebar_custom_seed'] = $themeSetting['sidebarCustomSeed'] ?? SystemConfigConstants::DEFAULT_THEME_SIDEBAR_CUSTOM_SEED;

// 11) Compatibility flags derived from environment.
$GLOBALS['sybase_active'] = ['ehrmdb'=>false,'ehrmdb_dev'=>false];

// 12) Runtime Sybase baharu
// Source of truth:
// - SYBASE_ENVIRONMENT
// - SYBASE_OPERATIONAL_MODE
if (!defined('SYBASE_ENVIRONMENT')) {
    $sybaseEnvironment = SystemConfigConstants::DEFAULT_SYBASE_ENVIRONMENT;
    $sessionSybaseEnvironment = strtolower(trim((string)($_SESSION['SYBASE_ENVIRONMENT'] ?? '')));
    if ($sessionSybaseEnvironment !== '' && in_array($sessionSybaseEnvironment, SystemConfigConstants::ALLOWED_SYBASE_ENVIRONMENTS, true)) {
        $sybaseEnvironment = $sessionSybaseEnvironment;
    } else {
        try {
            $cfgEnvironment = strtolower(trim((string)$config->getSybaseEnvironment(SystemConfigConstants::DEFAULT_SYBASE_ENVIRONMENT)));
            if ($cfgEnvironment !== '' && in_array($cfgEnvironment, SystemConfigConstants::ALLOWED_SYBASE_ENVIRONMENTS, true)) {
                $sybaseEnvironment = $cfgEnvironment;
            }
        } catch (Throwable $e) {
            // ignore, fallback below
        }
    }
    define('SYBASE_ENVIRONMENT', $sybaseEnvironment);
}

if (!defined('MAIN_DB_ENVIRONMENT')) {
    $mainDbEnvironment = SystemConfigConstants::DEFAULT_MAIN_DB_ENVIRONMENT;
    $sessionMainDbEnvironment = strtolower(trim((string)($_SESSION['MAIN_DB_ENVIRONMENT'] ?? '')));
    if ($sessionMainDbEnvironment !== '' && in_array($sessionMainDbEnvironment, SystemConfigConstants::ALLOWED_MAIN_DB_ENVIRONMENTS, true)) {
        $mainDbEnvironment = $sessionMainDbEnvironment;
    } else {
        try {
            $cfgMainDbEnvironment = strtolower(trim((string)$config->getMainDbEnvironment(SystemConfigConstants::DEFAULT_MAIN_DB_ENVIRONMENT)));
            if ($cfgMainDbEnvironment !== '' && in_array($cfgMainDbEnvironment, SystemConfigConstants::ALLOWED_MAIN_DB_ENVIRONMENTS, true)) {
                $mainDbEnvironment = $cfgMainDbEnvironment;
            }
        } catch (Throwable $e) {
            // ignore, fallback below
        }
    }
    define('MAIN_DB_ENVIRONMENT', $mainDbEnvironment);
}

if (!defined('SYBASE_OPERATIONAL_MODE')) {
    $sybaseOperationalMode = SystemConfigConstants::DEFAULT_SYBASE_OPERATIONAL_MODE;
    $sessionSybaseOperationalMode = strtolower(trim((string)($_SESSION['SYBASE_OPERATIONAL_MODE'] ?? '')));
    if ($sessionSybaseOperationalMode !== '' && in_array($sessionSybaseOperationalMode, SystemConfigConstants::ALLOWED_SYBASE_OPERATIONAL_MODES, true)) {
        $sybaseOperationalMode = $sessionSybaseOperationalMode;
    } else {
        try {
            $cfgMode = strtolower(trim((string)$config->getSybaseOperationalMode(SystemConfigConstants::DEFAULT_SYBASE_OPERATIONAL_MODE)));
            if ($cfgMode !== '' && in_array($cfgMode, SystemConfigConstants::ALLOWED_SYBASE_OPERATIONAL_MODES, true)) {
                $sybaseOperationalMode = $cfgMode;
            }
        } catch (Throwable $e) {
            // ignore, fallback below
        }
    }
    define('SYBASE_OPERATIONAL_MODE', $sybaseOperationalMode);
}

if (defined('SYBASE_ENVIRONMENT')) {
    $GLOBALS['sybase_active'] = [
        'ehrmdb' => ((string)SYBASE_ENVIRONMENT === 'production'),
        'ehrmdb_dev' => ((string)SYBASE_ENVIRONMENT === 'development'),
    ];
}

if (!function_exists('is_development_mode')) {
    /**
     * Check if system is running in development mode
     * Runtime baharu utamakan SYBASE_ENVIRONMENT.
     * 
     * @return bool True if development mode, false if production
     */
    function is_development_mode(): bool {
        static $cached = null;
        if ($cached !== null) return $cached;

        if (defined('SYBASE_ENVIRONMENT')) {
            $cached = ((string)SYBASE_ENVIRONMENT === 'development');
            return $cached;
        }

        $flags = $GLOBALS['sybase_active'] ?? [];
        $cached = !empty($flags['ehrmdb_dev']);
        
        return $cached;
    }
}

// 13) Helper cepat untuk dapatkan PDO Sybase domain staff
if (!function_exists('sybase_pdo')) {
    function sybase_pdo(): PDO {
        return Database::pdoSybaseStaff();
    }
}

// 14) Nama & avatar (berguna untuk topbar)
$nama_pengguna    = $profile['f_nama'] ?? ($profile['f_nickname'] ?? 'Pengguna');
$peranan_pengguna = $profile['f_groupName'] ?? 'Pengguna';
$avatarUrl        = $profile['avatar_url'] ?? $profile['avatar'] ?? base_url('assets/images/no-image.jpg');

// 15) Auto BASE_URL (jika diperlukan oleh view)
if (!defined('BASE_URL')) {
    $scriptName = $_SERVER['SCRIPT_NAME'] ?? '';
    $basePath   = rtrim(str_replace('\\', '/', dirname($scriptName)), '/');
    define('BASE_URL', ($basePath === '' ? '/' : $basePath . '/'));
}

// 16) Gate helpers
if (!function_exists('require_login')) {
    function require_login(string $redirectTo = '../index.php'): void {
        // Public accounts authenticate with a login ID and may have no staff ID.
        if (empty($_SESSION['f_loginID']) && empty($_SESSION['f_stafID'])) {
            if (request_is_ajax_like()) {
                tetapan_sistem_ajax_debug_log('require_login_unauthorized', [
                    'redirect' => terminated_session_login_url(),
                ]);
                while (ob_get_level() > 0) {
                    @ob_end_clean();
                }
                http_response_code(401);
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode([
                    'success' => false,
                    'error' => true,
                    'message' => (string)(__('unauthorized_access') ?: 'Sila log masuk terlebih dahulu.'),
                    'redirect' => terminated_session_login_url(),
                ], JSON_UNESCAPED_UNICODE);
                exit;
            }
            header('Location: ' . $redirectTo);
            exit;
        }
    }
}
if (!function_exists('require_role')) {
    function require_role(string $requiredRole, string $redirectTo = '../index.php'): void {
        if (!isset($_SESSION['role']) || $_SESSION['role'] !== $requiredRole) {
            header('Location: ' . $redirectTo);
            exit;
        }
    }
}

/* -------------------------------------------------------
   17) AUDIT REQUEST END (shutdown hook)
   - Pastikan sentiasa log status code & latency walaupun page error.
-------------------------------------------------------- */
register_shutdown_function(function() use ($__REQ_START, $__REQUEST_ID) {
    audit_safe(function() use ($__REQ_START, $__REQUEST_ID) {
        if (!$__REQUEST_ID) return;
        $lat = (int) round((microtime(true) - $__REQ_START) * 1000);
        $status = http_response_code() ?: 200;
        audit_logger()->logRequestEnd($__REQUEST_ID, $status, $lat);
    });
});

// 17.1) PENTING: JANGAN render alert/HTML dalam init.php.
//       (Render SweetAlert/confirm dsb. dalam footer.php)
