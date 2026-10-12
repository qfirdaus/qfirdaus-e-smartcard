<?php
/**
 * IQS FRAMEWORK CORE FILE
 *
 * READ ONLY for downstream project programmers.
 * Do not modify this file directly in template or cloned projects.
 * Custom changes must be implemented in project-specific files
 * or approved extension points.
 */declare(strict_types=1);


// 🔐 Security Headers
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");
header("X-Content-Type-Options: nosniff");
header("Referrer-Policy: strict-origin-when-cross-origin");
header("Content-Security-Policy: default-src 'self'; script-src 'self'; style-src 'self'; object-src 'none';");

// 🧩 Init
require_once __DIR__ . '/includes/init.php';
require_once __DIR__ . '/controllers/LoginController.php';
require_once __DIR__ . '/classes/Database.php'; // untuk query f_jabatanKod
// SSO helper optional; login form will use direct credentials
define('SSO_SP_CLIENT_NOAUTO', true);
@include_once __DIR__ . '/sso_sp_client.php';

$requestMethod = strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET'));

// 🛑 POST only
// if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
//     http_response_code(405);
//     exit("Access denied");
// }

// ✅ CSRF Check
if ($requestMethod === 'POST') {
    $csrfToken = (string)($_POST['csrf_token'] ?? '');
    $sessionCsrfToken = (string)($_SESSION['csrf_token'] ?? '');
    if ($csrfToken === '' || $sessionCsrfToken === '' || !hash_equals($sessionCsrfToken, $csrfToken)) {
        set_alert([
            'type'  => 'sweet',
            'title' => 'login_form_validation_error',
            'text'  => 'config_login_error_message',
            'icon'  => 'warning',
            'confirm' => true,
        ]);
        redirect('index.php');
        exit;
    }
}

// ✅ Helper Function
function GET_REALIPADDRESS(): string {
    if (!empty($_SERVER['HTTP_CLIENT_IP'])) return $_SERVER['HTTP_CLIENT_IP'];
    if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) return explode(',', $_SERVER['HTTP_X_FORWARDED_FOR'])[0];
    return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
}

function login_client_ip(): ?string {
    if (class_exists('AuditLogger') && method_exists('AuditLogger', 'clientIp')) {
        try {
            $ip = AuditLogger::clientIp();
            return $ip !== '' ? $ip : null;
        } catch (Throwable $e) {
            return null;
        }
    }

    $ip = trim(GET_REALIPADDRESS());
    return $ip !== '' ? $ip : null;
}

function login_locked_until_to_ts($value): int {
    $lockedUntil = trim((string)$value);
    if ($lockedUntil === '') {
        return 0;
    }
    $ts = strtotime($lockedUntil);
    return $ts === false ? 0 : (int)$ts;
}

function login_build_identifier_ip_scope(string $loginID, ?string $ip): string {
    $normalizedLoginId = function_exists('auth_normalize_login_id')
        ? auth_normalize_login_id($loginID)
        : trim($loginID);
    $normalizedIp = trim((string)$ip);
    if ($normalizedLoginId === '' || $normalizedIp === '') {
        return '';
    }
    return $normalizedLoginId . '|' . $normalizedIp;
}

function normalize_auth_identifier(?string $val): string {
    return function_exists('auth_normalize_login_id')
        ? auth_normalize_login_id($val)
        : trim((string)$val);
}

function log_login_event(string $status, string $id): void {
    static $statusMap = [
        'BERJAYA' => 'SUCCESS',
        'GAGAL' => 'FAILED',
        'DISEKAT' => 'ACCESS_BLOCKED',
        'TERKUNCI_ID' => 'LOCKED',
    ];

    $id = normalize_auth_identifier($id);
    $ip = GET_REALIPADDRESS();
    $time = date('Y-m-d H:i:s');
    $log_path = __DIR__ . '/log/login_attempts.log';
    $normalizedStatus = $statusMap[$status] ?? strtoupper(trim($status));
    @file_put_contents($log_path, "[$time] [$normalizedStatus] $id - $ip\n", FILE_APPEND);
}

function login_guardrail_scope(bool $loginIdLocked, bool $identifierIpLocked, bool $ipLocked): string {
    if ($ipLocked) {
        return 'IP';
    }
    if ($identifierIpLocked) {
        return 'LOGIN_IP';
    }
    if ($loginIdLocked) {
        return 'LOGIN_ID';
    }
    return 'NONE';
}

function audit_login_guardrail_event(
    string $reasonCode,
    string $loginID,
    string $authMethod,
    string $guardrailScope,
    ?string $lockedUntil = null,
    ?int $attemptsRemaining = null
): void {
    if (!function_exists('audit_event')) {
        return;
    }

    $loginID = normalize_auth_identifier($loginID);
    $authMethod = strtoupper(trim($authMethod)) === 'SSO' ? 'SSO' : 'MANUAL';
    $clientIp = login_client_ip();
    $userAgent = trim((string)($_SERVER['HTTP_USER_AGENT'] ?? ''));
    $requestId = $GLOBALS['__AUDIT_REQUEST_ID'] ?? null;
    $actorLabel = function_exists('audit_format_actor_label')
        ? audit_format_actor_label(null, $loginID)
        : ($loginID !== '' ? $loginID : 'Unknown');
    $message = function_exists('audit_format_message')
        ? audit_format_message('Login blocked by security guardrail', $actorLabel)
        : ('Login blocked by security guardrail for ' . $actorLabel);

    try {
        audit_event([
            'event_type'  => 'LOGIN',
            'severity'    => 'SECURITY',
            'outcome'     => 'FAIL',
            'target_type' => 'auth',
            'target_id'   => 'login',
            'message'     => $message,
            'request_id'  => $requestId,
            'session_id'  => session_id(),
            'login_id'    => $loginID !== '' ? $loginID : null,
            'actor_label' => $actorLabel,
            'ip'          => $clientIp,
            'meta'        => [
                'login_id'           => $loginID !== '' ? $loginID : null,
                'attempted_login_id' => $loginID,
                'reason'             => $reasonCode,
                'reason_code'        => $reasonCode,
                'auth_method'        => $authMethod,
                'auth_flow'          => $authMethod === 'SSO' ? 'sso_login' : 'manual_login',
                'guardrail_scope'    => $guardrailScope,
                'locked_until'       => $lockedUntil,
                'attempts_remaining' => $attemptsRemaining,
                'ip_text'            => $clientIp,
                'user_agent'         => $userAgent !== '' ? $userAgent : null,
                'request_id'         => $requestId,
            ],
        ]);
    } catch (Throwable $e) {
        error_log('[login.php] Guardrail audit failed: ' . $e->getMessage());
    }
}

function login_guardrail_wait_message(string $guardrailScope, int $waitSeconds): string {
    $waitSeconds = max(1, $waitSeconds);
    $secondsLabel = (string)__('login_seconds');
    $scope = strtoupper(trim($guardrailScope));

    return match ($scope) {
        'IP' => (string)(__('login_locked_msg_ip') !== 'login_locked_msg_ip'
            ? __('login_locked_msg_ip')
            : 'Terlalu banyak cubaan dari IP semasa. Sila cuba lagi selepas') . ' ' . $waitSeconds . ' ' . $secondsLabel,
        'LOGIN_IP' => (string)(__('login_locked_msg_login_ip') !== 'login_locked_msg_login_ip'
            ? __('login_locked_msg_login_ip')
            : 'Terlalu banyak cubaan untuk Login ID ini dari IP semasa. Sila cuba lagi selepas') . ' ' . $waitSeconds . ' ' . $secondsLabel,
        default => (string)(__('login_locked_msg_login_id') !== 'login_locked_msg_login_id'
            ? __('login_locked_msg_login_id')
            : __('login_locked_msg')) . ' ' . $waitSeconds . ' ' . $secondsLabel,
    };
}

function clear_sso_auth_handoff(): void {
    unset($_SESSION['sso_auth_handoff']);
}

function consume_sso_auth_handoff(): void {
    if (!isset($_SESSION['sso_auth_handoff']) || !is_array($_SESSION['sso_auth_handoff'])) {
        return;
    }

    $_SESSION['sso_auth_handoff']['consumed_at'] = time();
}

function validate_sso_auth_handoff($handoff, int $now, int $ttlSeconds): array {
    if (!is_array($handoff) || $handoff === []) {
        return ['ok' => false, 'reason' => 'missing'];
    }

    $issuedAtRaw = $handoff['issued_at'] ?? null;
    $issuedAtTs = is_numeric($issuedAtRaw) ? (int)$issuedAtRaw : strtotime(trim((string)$issuedAtRaw));
    if ($issuedAtTs === false || $issuedAtTs <= 0 || ($now - (int)$issuedAtTs) > $ttlSeconds) {
        return ['ok' => false, 'reason' => 'expired'];
    }

    $resolvedLoginId = trim((string)($handoff['resolved_login_id'] ?? ''));
    $resolvedSource = trim((string)($handoff['resolved_source'] ?? ''));
    $validToken = !empty($handoff['valid_token']);
	$validSource = in_array($resolvedSource, ['data3', 'data4'], true);
	$nonce = trim((string)($handoff['nonce'] ?? ''));
	$correlationId = trim((string)($handoff['correlation_id'] ?? ''));
	$consumedAt = (int)($handoff['consumed_at'] ?? 0);
	$hasValidIdentifier = $resolvedLoginId !== ''
		&& empty($handoff['identity_conflict'])
		&& ($validSource || !empty($handoff['data3_valid']) || !empty($handoff['data4_valid']));

    if ($consumedAt > 0) {
        return ['ok' => false, 'reason' => 'consumed'];
    }

	if (!$validToken || !$hasValidIdentifier || $nonce === '' || $correlationId === '') {
        return ['ok' => false, 'reason' => 'invalid'];
    }

    return [
        'ok' => true,
        'reason' => 'ok',
        'login_id' => $resolvedLoginId,
        'source' => $resolvedSource,
		'nonce' => $nonce,
		'correlation_id' => $correlationId,
    ];
}

function login_sanitize_redirect_target(string $target): string {
    $target = trim($target);
    if ($target === '' || preg_match('/[\r\n]/', $target)) {
        return '';
    }

    if (preg_match('~^(?:https?:)?//~i', $target)) {
        $targetHost = strtolower((string)(parse_url($target, PHP_URL_HOST) ?? ''));
        $currentHost = strtolower((string)($_SERVER['HTTP_HOST'] ?? ''));
        if ($targetHost === '' || $currentHost === '' || $targetHost !== $currentHost) {
            return '';
        }
        return $target;
    }

    if (str_starts_with($target, '/')) {
        return $target;
    }

    return ltrim($target, '/');
}

function consume_post_login_redirect(): string {
    $target = trim((string)($_SESSION['auth_post_login_redirect'] ?? ''));
    unset($_SESSION['auth_post_login_redirect']);

    return login_sanitize_redirect_target($target);
}

function validate_bdr_self_confirm_sso_context($context, int $now, int $ttlSeconds = 600): array {
    if (!is_array($context) || empty($context['active'])) {
        return ['ok' => false, 'reason' => 'missing'];
    }

    $token = trim((string)($context['token'] ?? ''));
    $returnPath = login_sanitize_redirect_target((string)($context['return_path'] ?? ''));
    $issuedAt = (int)($context['issued_at'] ?? 0);
    if ($token === '' || $returnPath === '' || $issuedAt <= 0) {
        return ['ok' => false, 'reason' => 'invalid'];
    }

    $returnPathOnly = (string)(parse_url($returnPath, PHP_URL_PATH) ?: '');
    if (!str_ends_with($returnPathOnly, '/pages/bdr-notification-confirm.php')) {
        return ['ok' => false, 'reason' => 'invalid_return_path'];
    }

    $returnQuery = [];
    parse_str((string)(parse_url($returnPath, PHP_URL_QUERY) ?: ''), $returnQuery);
    if (trim((string)($returnQuery['token'] ?? '')) !== $token) {
        return ['ok' => false, 'reason' => 'token_mismatch'];
    }

    if (($now - $issuedAt) > $ttlSeconds) {
        return ['ok' => false, 'reason' => 'expired'];
    }

    return [
        'ok' => true,
        'reason' => 'ok',
        'token' => $token,
        'return_path' => $returnPath,
    ];
}

// 📥 Input
$f_loginID  = normalize_auth_identifier($_POST['f_loginID'] ?? ($_POST['f_stafID'] ?? ''));
$f_password = $_POST['f_password'] ?? '';
$now        = time();
$ssoHandoffTtlSeconds = 300;

$rawSsoHandoff = $_SESSION['sso_auth_handoff'] ?? null;
$ssoHandoffValidation = validate_sso_auth_handoff($rawSsoHandoff, $now, $ssoHandoffTtlSeconds);
if (!$ssoHandoffValidation['ok'] && $ssoHandoffValidation['reason'] !== 'missing') {
	    clear_sso_auth_handoff();
    $rawSsoHandoff = null;
}
$ssoHandoff = is_array($rawSsoHandoff) ? $rawSsoHandoff : [];
$isSsoAttempt = $requestMethod !== 'POST' && !empty($ssoHandoffValidation['ok']);

if ($isSsoAttempt) {
    $f_loginID = normalize_auth_identifier((string)($ssoHandoff['resolved_login_id'] ?? ''));
    $f_password = '';
}

if ($requestMethod !== 'POST' && !$isSsoAttempt && ($ssoHandoffValidation['reason'] ?? 'missing') === 'expired') {
    set_alert([
        'type'  => 'sweet',
        'title' => 'login_sso_session_expired_title',
        'text'  => 'login_sso_session_expired_msg',
        'icon'  => 'warning',
        'confirm' => true
    ]);
    log_login_event("SSO_HANDOFF_EXPIRED", 'sso');
    redirect('index.php');
    exit;
}

if ($requestMethod !== 'POST' && !$isSsoAttempt && ($ssoHandoffValidation['reason'] ?? 'missing') === 'invalid') {
    set_alert([
        'type'  => 'sweet',
        'title' => 'login_sso_payload_invalid_title',
        'text'  => 'login_sso_payload_invalid_msg',
        'icon'  => 'warning',
        'confirm' => true
    ]);
    log_login_event("SSO_HANDOFF_INVALID", 'sso');
    redirect('index.php');
    exit;
}

if (!$isSsoAttempt && $requestMethod !== 'POST') {
    redirect('index.php');
    exit;
}

// Tetapan kadar cuba & masa kunci
$loginSecurityPolicy = function_exists('get_auth_login_security_config') ? get_auth_login_security_config() : [];
$MAX_ATTEMPTS = max(1, (int)($loginSecurityPolicy['max_attempts'] ?? 3));
$LOCK_SECONDS = max(30, (int)($loginSecurityPolicy['lock_seconds'] ?? 60));
$IDENTIFIER_IP_MAX_ATTEMPTS = max(1, (int)($loginSecurityPolicy['identifier_ip_max_attempts'] ?? 5));
$IDENTIFIER_IP_LOCK_SECONDS = max(30, (int)($loginSecurityPolicy['identifier_ip_lock_seconds'] ?? 300));
$IP_MAX_ATTEMPTS = max(1, (int)($loginSecurityPolicy['ip_max_attempts'] ?? 10));
$IP_LOCK_SECONDS = max(30, (int)($loginSecurityPolicy['ip_lock_seconds'] ?? 300));

// ❌ Invalid / incomplete SSO handoff
if ($isSsoAttempt && $f_loginID === '') {
    clear_sso_auth_handoff();
    set_alert([
        'type'  => 'sweet',
        'title' => 'login_sso_payload_invalid_title',
        'text'  => 'login_sso_payload_invalid_msg',
        'icon'  => 'warning',
        'confirm' => true
    ]);
    log_login_event("SSO_HANDOFF_INVALID", 'sso');
    redirect('index.php');
    exit;
}

if ($isSsoAttempt) {
    $selfConfirmContext = validate_bdr_self_confirm_sso_context($_SESSION['bdr_self_confirm_sso'] ?? null, $now);
    if (!empty($selfConfirmContext['ok'])) {
        $_SESSION['bdr_self_confirm_sso_identity'] = [
            'staff_no' => $f_loginID,
            'source' => trim((string)($ssoHandoffValidation['source'] ?? '')),
            'token' => (string)$selfConfirmContext['token'],
            'verified_at' => date('c'),
        ];
        unset($_SESSION['auth_post_login_redirect']);
        clear_sso_auth_handoff();
        log_login_event('SSO_SELF_CONFIRM_VERIFIED', $f_loginID);
        redirect((string)$selfConfirmContext['return_path']);
        exit;
    }
}

// ❌ Empty input
//if ($f_stafID === '' || $f_password === '') {
if (!$isSsoAttempt && ($f_loginID === '' || $f_password === '')) {
    set_alert([
        'type'  => 'sweet',
        'title' => 'login_form_validation_error',
        'icon'  => 'warning',
        'confirm' => true
    ]);
    redirect('index.php');
    exit;
}

$lockoutModel = new User(Database::getInstance('mysql')->getConnection());
$loginClientIp = login_client_ip();
$loginUserAgent = trim((string)($_SERVER['HTTP_USER_AGENT'] ?? ''));
$identifierIpScope = login_build_identifier_ip_scope($f_loginID, $loginClientIp);
$attempts = $MAX_ATTEMPTS;
$attemptedAuthMethod = $isSsoAttempt ? 'SSO' : 'MANUAL';

$lockoutState = $lockoutModel->getLoginLockoutState($f_loginID, $MAX_ATTEMPTS);
$attempts = max(0, (int)($lockoutState['attempts_remaining'] ?? $MAX_ATTEMPTS));
$lockoutLockedUntilTs = login_locked_until_to_ts($lockoutState['locked_until'] ?? null);

$identifierIpState = ['is_locked' => false, 'locked_until' => null];
$identifierIpLockedUntilTs = 0;
$identifierIpAttemptsRemaining = $IDENTIFIER_IP_MAX_ATTEMPTS;
if ($identifierIpScope !== '') {
    $identifierIpState = $lockoutModel->getLoginThrottleState('LOGIN_IP', $identifierIpScope, $IDENTIFIER_IP_MAX_ATTEMPTS);
    $identifierIpLockedUntilTs = login_locked_until_to_ts($identifierIpState['locked_until'] ?? null);
    $identifierIpAttemptsRemaining = max(0, (int)($identifierIpState['attempts_remaining'] ?? $IDENTIFIER_IP_MAX_ATTEMPTS));
}

$ipState = ['is_locked' => false, 'locked_until' => null];
$ipLockedUntilTs = 0;
$ipAttemptsRemaining = $IP_MAX_ATTEMPTS;
if ($loginClientIp !== null && $loginClientIp !== '') {
    $ipState = $lockoutModel->getLoginThrottleState('IP', $loginClientIp, $IP_MAX_ATTEMPTS);
    $ipLockedUntilTs = login_locked_until_to_ts($ipState['locked_until'] ?? null);
    $ipAttemptsRemaining = max(0, (int)($ipState['attempts_remaining'] ?? $IP_MAX_ATTEMPTS));
}

$attempts = min(
    max(0, (int)($lockoutState['attempts_remaining'] ?? $MAX_ATTEMPTS)),
    $identifierIpAttemptsRemaining,
    $ipAttemptsRemaining
);

$activeLockUntilTs = max($lockoutLockedUntilTs, $identifierIpLockedUntilTs, $ipLockedUntilTs);
if ($activeLockUntilTs > $now) {
    $wait = $activeLockUntilTs - $now;
    $guardrailScope = login_guardrail_scope(
        $lockoutLockedUntilTs > $now,
        $identifierIpLockedUntilTs > $now,
        $ipLockedUntilTs > $now
    );
    set_alert([
        'type'  => 'sweet',
        'title' => 'login_locked_title',
        'text'  => login_guardrail_wait_message($guardrailScope, $wait),
        'icon'  => 'error',
        'confirm' => true
    ]);
    audit_login_guardrail_event(
        match ($guardrailScope) {
            'IP' => 'ip_locked',
            'LOGIN_IP' => 'login_ip_locked',
            default => 'login_id_locked',
        },
        $f_loginID,
        $attemptedAuthMethod,
        $guardrailScope,
        date('Y-m-d H:i:s', $activeLockUntilTs),
        $attempts
    );
    log_login_event("LOCKED_ID", $f_loginID);
    redirect('index.php');
    exit;
}

if ($lockoutLockedUntilTs > 0 && $lockoutLockedUntilTs <= $now) {
    $lockoutModel->clearLoginLockout($f_loginID, $loginClientIp, $loginUserAgent);
}
if ($identifierIpLockedUntilTs > 0 && $identifierIpLockedUntilTs <= $now && $identifierIpScope !== '') {
    $lockoutModel->clearLoginThrottle('LOGIN_IP', $identifierIpScope, $loginClientIp, $loginUserAgent);
}
if ($ipLockedUntilTs > 0 && $ipLockedUntilTs <= $now && $loginClientIp !== null && $loginClientIp !== '') {
    $lockoutModel->clearLoginThrottle('IP', $loginClientIp, $loginClientIp, $loginUserAgent);
}

// ==========================
// ✅ Proses login
// ==========================

$loginOk = false;
try {
    $loginController = new LoginController();
    if ($isSsoAttempt) {
        consume_sso_auth_handoff();
    }
    $loginOk = $loginController->authenticate($f_loginID, $isSsoAttempt ? null : $f_password);
} catch (Throwable $e) {
    clear_sso_auth_handoff();

    // Check jika exception adalah untuk access blocked
    if ($e->getMessage() === 'ACCESS_BLOCKED') {
        set_alert([
            'type'  => 'sweet',
            'title' => 'login_access_blocked_title',
            'text'  => 'login_access_blocked_msg',
            'icon'  => 'error',
            'confirm' => true
        ]);
         
        log_login_event("DISEKAT", $f_loginID);
        redirect('index.php');
        exit;
    }

    if ($e->getMessage() === 'ACCOUNT_NOT_VERIFIED') {
        set_alert([
            'type'  => 'sweet',
            'title' => 'login_account_not_verified_title',
            'text'  => 'login_account_not_verified_msg',
            'icon'  => 'warning',
            'confirm' => true
        ]);
        log_login_event("ACCOUNT_NOT_VERIFIED", $f_loginID);
        redirect('index.php');
        exit;
    }

    if ($e->getMessage() === 'PASSWORD_CHANGE_REQUIRED') {
        log_login_event("PASSWORD_CHANGE_REQUIRED", $f_loginID);
        if (!empty($_SESSION['pending_password_change'])) {
            redirect('change-password.php');
        }
        set_alert([
            'type'  => 'sweet',
            'title' => 'login_password_change_required_title',
            'text'  => 'login_password_change_required_msg',
            'icon'  => 'warning',
            'confirm' => true
        ]);
        redirect('index.php');
        exit;
    }

    if ($e->getMessage() === 'PASSWORD_EXPIRED') {
        log_login_event("PASSWORD_EXPIRED", $f_loginID);
        if (!empty($_SESSION['pending_password_change'])) {
            redirect('change-password.php');
        }
        set_alert([
            'type'  => 'sweet',
            'title' => 'login_password_expired_title',
            'text'  => 'login_password_expired_msg',
            'icon'  => 'warning',
            'confirm' => true
        ]);
        redirect('index.php');
        exit;
    }

    if ($e->getMessage() === 'MAINTENANCE_MODE') {
        set_alert([
            'type'  => 'sweet',
            'title' => 'login_maintenance_mode_title',
            'text'  => 'login_maintenance_mode_msg',
            'icon'  => 'warning',
            'confirm' => true
        ]);
        log_login_event("MAINTENANCE_ONLY", $f_loginID);
        redirect('index.php');
        exit;
    }

    if ($e->getMessage() === 'CATEGORY_DISABLED') {
        set_alert([
            'type'  => 'sweet',
            'title' => 'login_category_disabled_title',
            'text'  => 'login_category_disabled_msg',
            'icon'  => 'warning',
            'confirm' => true
        ]);
        log_login_event("CATEGORY_DISABLED", $f_loginID);
        redirect('index.php');
        exit;
    }

    if ($e->getMessage() === 'MANUAL_LOGIN_NOT_ALLOWED') {
        set_alert([
            'type'  => 'sweet',
            'title' => 'login_manual_not_allowed_title',
            'text'  => 'login_manual_not_allowed_msg',
            'icon'  => 'info',
            'confirm' => true
        ]);
        log_login_event("MANUAL_NOT_ALLOWED", $f_loginID);
        redirect('index.php');
        exit;
    }

    if ($e->getMessage() === 'SSO_FIRST_LOGIN_REQUIRED') {
        set_alert([
            'type'  => 'sweet',
            'title' => 'login_sso_first_login_required_title',
            'text'  => 'login_sso_first_login_required_msg',
            'icon'  => 'info',
            'confirm' => true,
            'close_on_confirm' => true,
        ]);
        log_login_event("SSO_FIRST_LOGIN_REQUIRED", $f_loginID);
        redirect('index.php');
        exit;
    }

    if ($e->getMessage() === 'MANUAL_ACCOUNT_NOT_READY') {
        set_alert([
            'type'  => 'sweet',
            'title' => 'login_manual_account_not_ready_title',
            'text'  => 'login_manual_account_not_ready_msg',
            'icon'  => 'warning',
            'confirm' => true
        ]);
        log_login_event("MANUAL_ACCOUNT_NOT_READY", $f_loginID);
        redirect('index.php');
        exit;
    }

    if ($e->getMessage() === 'SSO_LOGIN_NOT_ALLOWED') {
        set_alert([
            'type'  => 'sweet',
            'title' => 'login_sso_not_allowed_title',
            'text'  => 'login_sso_not_allowed_msg',
            'icon'  => 'info',
            'confirm' => true,
            'close_on_confirm' => true,
        ]);
        log_login_event("SSO_NOT_ALLOWED", $f_loginID);
        redirect('index.php');
        exit;
    }

    if ($e->getMessage() === 'SSO_ACCOUNT_NOT_PROVISIONED') {
        set_alert([
            'type'  => 'sweet',
            'title' => 'login_sso_account_not_provisioned_title',
            'text'  => 'login_sso_account_not_provisioned_msg',
            'icon'  => 'warning',
            'confirm' => true,
            'close_on_confirm' => true,
        ]);
        log_login_event("SSO_ACCOUNT_NOT_PROVISIONED", $f_loginID);
        redirect('index.php');
        exit;
    }

    if ($e->getMessage() === 'SSO_ACCOUNT_CATEGORY_MISMATCH') {
        set_alert([
            'type'  => 'sweet',
            'title' => 'login_sso_account_not_provisioned_title',
            'text'  => 'login_sso_account_not_provisioned_msg',
            'icon'  => 'warning',
            'confirm' => true,
            'close_on_confirm' => true,
        ]);
        log_login_event("SSO_ACCOUNT_CATEGORY_MISMATCH", $f_loginID);
        redirect('index.php');
        exit;
    }

    if ($e->getMessage() === 'SSO_DEFAULT_GROUP_INVALID') {
        set_alert([
            'type'  => 'sweet',
            'title' => 'login_sso_default_group_invalid_title',
            'text'  => 'login_sso_default_group_invalid_msg',
            'icon'  => 'error',
            'confirm' => true,
            'close_on_confirm' => true,
        ]);
        log_login_event("SSO_DEFAULT_GROUP_INVALID", $f_loginID);
        redirect('index.php');
        exit;
    }

    if ($e->getMessage() === 'SSO_SOURCE_UNAVAILABLE') {
        set_alert([
            'type'  => 'sweet',
            'title' => 'login_sso_source_unavailable_title',
            'text'  => 'login_sso_source_unavailable_msg',
            'icon'  => 'warning',
            'confirm' => true,
            'close_on_confirm' => true,
        ]);
        log_login_event("SSO_SOURCE_UNAVAILABLE", $f_loginID);
        redirect('index.php');
        exit;
    }

    if ($e->getMessage() === 'SSO_AUTO_PROVISION_FAILED') {
        set_alert([
            'type'  => 'sweet',
            'title' => 'login_sso_auto_provision_failed_title',
            'text'  => 'login_sso_auto_provision_failed_msg',
            'icon'  => 'error',
            'confirm' => true,
            'close_on_confirm' => true,
        ]);
        log_login_event("SSO_AUTO_PROVISION_FAILED", $f_loginID);
        redirect('index.php');
        exit;
    }
    
    // Exception masa authenticate (DB down, dsb.)
    error_log(sprintf('LOGIN ERROR: %s in %s:%d', $e->getMessage(), $e->getFile(), $e->getLine()));

    set_alert([
        'type'  => 'sweet',
        'title' => 'config_login_error_title',   // ikut standard prefix config_
        'text'  => 'config_login_error_message',
        'icon'  => 'error',
        'confirm' => true
    ]);
    redirect('index.php');
    exit;
}

// ==========================
// 🎯 Selepas try/catch (redirect di luar)
// ==========================
if ($loginOk) {
    // ✅ Berjaya login
    $lockoutModel->clearLoginLockout($f_loginID, $loginClientIp, $loginUserAgent);
    if ($identifierIpScope !== '') {
        $lockoutModel->clearLoginThrottle('LOGIN_IP', $identifierIpScope, $loginClientIp, $loginUserAgent);
    }
    if ($loginClientIp !== null && $loginClientIp !== '') {
        $lockoutModel->clearLoginThrottle('IP', $loginClientIp, $loginClientIp, $loginUserAgent);
    }
	    clear_sso_auth_handoff();
	    unset($_SESSION['sso_correlation_id'], $_SESSION['sso_last_failure_reference'], $_SESSION['sso_failure_count']);

	    // 🆕 SET session: kod jabatan dari MySQL (tbl_m_user.f_jabatanKod)
    try {
        $pdo = Database::getInstance('mysql')->getConnection();
        $lookupLoginID = trim((string)($_SESSION['f_loginID'] ?? $f_loginID));
        $lookupStafID = trim((string)($_SESSION['f_stafID'] ?? ''));
        $stmt = $pdo->prepare(
            "SELECT f_jabatanKod
             FROM tbl_m_user
             WHERE " . ($lookupLoginID !== '' ? "TRIM(COALESCE(f_loginID, '')) = :loginID" : "1=0") .
            ($lookupLoginID !== '' && $lookupStafID !== '' ? " OR " : "") .
            ($lookupStafID !== '' ? "f_stafID = :stafID" : "") . "
             LIMIT 1"
        );
        if ($lookupLoginID !== '') {
            $stmt->bindValue(':loginID', $lookupLoginID, PDO::PARAM_STR);
        }
        if ($lookupStafID !== '') {
            $stmt->bindValue(':stafID', $lookupStafID, PDO::PARAM_STR);
        }
        $stmt->execute();
        $_SESSION['f_jabatanKod'] = trim((string)($stmt->fetchColumn() ?: ''));
    } catch (Throwable $e) {
        $_SESSION['f_jabatanKod'] = '';
        error_log('LOGIN set f_jabatanKod: ' . $e->getMessage());
    }

    // (optional) simpan loginID dalam session — authenticate() pun dah set; ini sebagai kesinambungan
    $_SESSION['f_loginID'] = $_SESSION['f_loginID'] ?? $f_loginID;

    // Buang alert lama
    unset($_SESSION['alert']);

    log_login_event("BERJAYA", $f_loginID);

    set_alert([
        'type'  => 'sweet',
        'title' => 'login_welcome',
        'text'  => 'login_welcome',
        'icon'  => 'success',
        'confirm' => true
    ]);

    // 🔑 Lepaskan lock session sebelum redirect
    session_write_close();

    // ✅ Redirect ikut tetapan home aktif; kekalkan first=1 untuk dashboard jika tiada query
    $defaultHome = trim((string)app_config('site.default_home', 'pages/dashboard.php'));
    if ($defaultHome === '') {
        $defaultHome = 'pages/dashboard.php';
    }
    if (stripos(parse_url($defaultHome, PHP_URL_PATH) ?? '', 'dashboard.php') !== false
        && parse_url($defaultHome, PHP_URL_QUERY) === null) {
        $separator = str_contains($defaultHome, '?') ? '&' : '?';
        $defaultHome .= $separator . 'first=1';
    }
    $postLoginRedirect = consume_post_login_redirect();
    if ($postLoginRedirect !== '') {
        redirect($postLoginRedirect);
        exit;
    }
    redirect($defaultHome);
    exit;

} else {
    // ❌ Gagal login (ID/Password salah)
    clear_sso_auth_handoff();

    $failedState = $lockoutModel->recordFailedLoginAttempt(
        $f_loginID,
        $MAX_ATTEMPTS,
        $LOCK_SECONDS,
        $loginClientIp,
        $loginUserAgent
    );
    $identifierIpFailedState = ['is_locked' => false, 'locked_until' => null];
    if ($identifierIpScope !== '') {
        $identifierIpFailedState = $lockoutModel->recordFailedLoginThrottle(
            'LOGIN_IP',
            $identifierIpScope,
            $IDENTIFIER_IP_MAX_ATTEMPTS,
            $IDENTIFIER_IP_LOCK_SECONDS,
            $loginClientIp,
            $loginUserAgent
        );
    }
    $ipFailedState = ['is_locked' => false, 'locked_until' => null];
    if ($loginClientIp !== null && $loginClientIp !== '') {
        $ipFailedState = $lockoutModel->recordFailedLoginThrottle(
            'IP',
            $loginClientIp,
            $IP_MAX_ATTEMPTS,
            $IP_LOCK_SECONDS,
            $loginClientIp,
            $loginUserAgent
        );
    }
    $attempts = min(
        max(0, (int)($failedState['attempts_remaining'] ?? 0)),
        max(0, (int)($identifierIpFailedState['attempts_remaining'] ?? $IDENTIFIER_IP_MAX_ATTEMPTS)),
        max(0, (int)($ipFailedState['attempts_remaining'] ?? $IP_MAX_ATTEMPTS))
    );

    if (!empty($failedState['is_locked']) || !empty($identifierIpFailedState['is_locked']) || !empty($ipFailedState['is_locked'])) {
        $lockedUntilTs = max(
            login_locked_until_to_ts($failedState['locked_until'] ?? null),
            login_locked_until_to_ts($identifierIpFailedState['locked_until'] ?? null),
            login_locked_until_to_ts($ipFailedState['locked_until'] ?? null)
        );
        $fallbackLockSeconds = max($LOCK_SECONDS, $IDENTIFIER_IP_LOCK_SECONDS, $IP_LOCK_SECONDS);
        $effectiveLockedUntilTs = $lockedUntilTs > 0 ? $lockedUntilTs : ($now + $fallbackLockSeconds);
        $wait = max(1, $effectiveLockedUntilTs - $now);
        $guardrailScope = login_guardrail_scope(
            !empty($failedState['is_locked']),
            !empty($identifierIpFailedState['is_locked']),
            !empty($ipFailedState['is_locked'])
        );
        set_alert([
            'type'  => 'sweet',
            'title' => 'login_locked_title',
            'text'  => login_guardrail_wait_message($guardrailScope, $wait),
            'icon'  => 'error',
            'confirm' => true
        ]);
        audit_login_guardrail_event(
            match ($guardrailScope) {
                'IP' => 'ip_locked',
                'LOGIN_IP' => 'login_ip_locked',
                default => 'login_id_locked',
            },
            $f_loginID,
            $attemptedAuthMethod,
            $guardrailScope,
            date('Y-m-d H:i:s', $effectiveLockedUntilTs),
            $attempts
        );
        log_login_event("TERKUNCI_ID", $f_loginID);
    } else {
        if ($isSsoAttempt) {
            set_alert([
                'type'  => 'sweet',
                'title' => 'login_sso_user_not_found_title',
                'text'  => 'login_sso_user_not_found_msg',
                'icon'  => 'warning',
                'confirm' => true,
                'close_on_confirm' => true,
            ]);
            log_login_event("SSO_USER_NOT_FOUND", $f_loginID);
        } else {
            // Papar baki cubaan dalam mesej
            set_alert([
                'type'  => 'sweet',
                'title' => 'login_fail_title',
                'text'  => __('login_fail_msg') . ' ' . $attempts,
                'icon'  => 'warning',
                'confirm' => true
            ]);
            log_login_event("GAGAL", $f_loginID);
        }
    }

    redirect('index.php');
    exit;
}
