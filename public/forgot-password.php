<?php
/**
 * IQS FRAMEWORK CORE FILE
 *
 * READ ONLY for downstream project programmers.
 * Do not modify this file directly in template or cloned projects.
 * Custom changes must be implemented in project-specific files
 * or approved extension points.
 */declare(strict_types=1);

header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");
header("Content-Security-Policy: default-src 'self'; script-src 'self' https://cdn.jsdelivr.net 'unsafe-inline'; style-src 'self' https://fonts.googleapis.com 'unsafe-inline'; font-src 'self' https://fonts.gstatic.com; img-src 'self' data:; object-src 'none'; base-uri 'self'; frame-ancestors 'self';");

ini_set('session.cookie_httponly', '1');
ini_set('session.cookie_secure', isset($_SERVER['HTTPS']) ? '1' : '0');
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

define('ALLOW_ANON_AJAX', true);
require_once __DIR__ . '/includes/init.php';
require_once __DIR__ . '/includes/functions-db.php';
require_once __DIR__ . '/classes/Database.php';
require_once __DIR__ . '/classes/Config.php';
require_once __DIR__ . '/classes/User.php';
require_once __DIR__ . '/classes/Mailer.php';
require_once __DIR__ . '/setting/helper/access_helper.php';

if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

if (!function_exists('forgot_password_check_rate_limit')) {
    function forgot_password_check_rate_limit(string $key, int $maxRequests = 5, int $windowSeconds = 900): bool
    {
        $cacheKey = 'forgot_password_rate:' . hash('sha256', $key);
        if (function_exists('apcu_add') && function_exists('apcu_inc') && filter_var(ini_get('apc.enabled'), FILTER_VALIDATE_BOOL)) {
            try {
                if (apcu_add($cacheKey, 1, $windowSeconds)) {
                    return true;
                }
                $count = apcu_inc($cacheKey, 1, $incremented);
                if ($incremented) {
                    return (int)$count <= $maxRequests;
                }
            } catch (Throwable $e) {
                // Gunakan session fallback jika APCu tidak tersedia pada runtime ini.
            }
        }

        $now = time();
        $sessionKey = 'forgot_password_rate_' . hash('sha256', $key);
        $state = $_SESSION[$sessionKey] ?? ['count' => 0, 'reset_at' => $now + $windowSeconds];

        if ($now >= (int)($state['reset_at'] ?? 0)) {
            $state = ['count' => 0, 'reset_at' => $now + $windowSeconds];
        }

        if ((int)($state['count'] ?? 0) >= $maxRequests) {
            $_SESSION[$sessionKey] = $state;
            return false;
        }

        $state['count'] = (int)($state['count'] ?? 0) + 1;
        $_SESSION[$sessionKey] = $state;
        return true;
    }
}

if (!function_exists('forgot_password_normalize_category')) {
    function forgot_password_normalize_category(?string $category): string
    {
        return function_exists('password_reset_normalize_category')
            ? password_reset_normalize_category($category)
            : 'UNKNOWN';
    }
}

if (!function_exists('forgot_password_manual_login_allowed')) {
    function forgot_password_manual_login_allowed(array $user, ?PDO $pdo = null): bool
    {
        $evaluation = password_reset_evaluate_eligibility($user, $pdo);
        return !empty($evaluation['eligible']);
    }
}

if (!function_exists('forgot_password_resolve_email')) {
    function forgot_password_resolve_email(array $user): ?string
    {
        $candidates = [
            $user['f_email'] ?? null,
            $user['f_loginID'] ?? null,
        ];

        foreach ($candidates as $candidate) {
            $candidate = strtolower(trim((string)$candidate));
            if ($candidate !== '' && filter_var($candidate, FILTER_VALIDATE_EMAIL)) {
                return $candidate;
            }
        }

        return null;
    }
}

if (!function_exists('forgot_password_client_ip')) {
    function forgot_password_client_ip(): ?string
    {
        if (class_exists('AuditLogger') && method_exists('AuditLogger', 'clientIp')) {
            try {
                $ip = AuditLogger::clientIp();
                return $ip !== '' ? $ip : null;
            } catch (Throwable $e) {
                return null;
            }
        }

        $ip = trim((string)($_SERVER['REMOTE_ADDR'] ?? ''));
        return $ip !== '' ? $ip : null;
    }
}

if (!function_exists('forgot_password_display_timezone')) {
    function forgot_password_display_timezone(): DateTimeZone
    {
        try {
            return new DateTimeZone('Asia/Kuala_Lumpur');
        } catch (Throwable $e) {
            return new DateTimeZone(date_default_timezone_get() ?: 'UTC');
        }
    }
}

$pdo = Database::getInstance('mysql')->getConnection();
$userModel = new User($pdo);
$featureAvailable = $userModel->passwordResetTableExists();
$runtimePasswordPolicy = function_exists('get_auth_password_policy_config') ? get_auth_password_policy_config() : [];
$resetTokenMinutes = max(5, (int)($runtimePasswordPolicy['reset_token_minutes'] ?? 30));
$requestMethod = strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET'));
$isDevelopment = app_env() === 'development';
$errors = [];
$submitted = false;
$loginIdValue = trim((string)($_POST['login_id'] ?? ''));
$requestStatus = trim((string)($_GET['status'] ?? ''));
$showSuccessAlert = $requestStatus === 'sent';
$showReviewAlert = $requestStatus === 'review';

if (!function_exists('forgot_password_debug_log')) {
    function forgot_password_debug_log(string $message, array $context = []): void
    {
        $line = '[forgot-password] ' . $message;
        if ($context !== []) {
            $encoded = json_encode($context, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            if ($encoded !== false) {
                $line .= ' | ' . $encoded;
            }
        }
        error_log($line);
    }
}

if ($requestMethod === 'POST') {
    $submitted = true;
    $csrfToken = (string)($_POST['csrf_token'] ?? '');

    if ($csrfToken === '' || !hash_equals((string)($_SESSION['csrf_token'] ?? ''), $csrfToken)) {
        $errors[] = __('forgot_password_error_csrf');
    } elseif (!$featureAvailable) {
        $errors[] = __('forgot_password_feature_unavailable');
    } elseif (!forgot_password_check_rate_limit('request', 5, 900)
        || !forgot_password_check_rate_limit('ip_' . hash('sha256', (string)(forgot_password_client_ip() ?? 'unknown')), 10, 900)) {
        $errors[] = __('forgot_password_error_rate_limited');
    } else {
        $loginId = trim($loginIdValue);
        if ($loginId === '') {
            $errors[] = __('forgot_password_error_required');
        } else {
            $identifierRateKey = 'identifier_' . hash('sha256', strtolower($loginId));
            if (!forgot_password_check_rate_limit($identifierRateKey, 5, 900)) {
                $errors[] = __('forgot_password_error_rate_limited');
                $candidate = null;
                $candidateResolution = ['candidate' => null, 'ambiguous' => false, 'match_type' => 'none'];
            } else {
                $candidateResolution = $userModel->resolvePasswordResetCandidate($loginId);
                $candidate = is_array($candidateResolution['candidate'] ?? null) ? $candidateResolution['candidate'] : null;
            }
            $canIssueReset = false;
            $recipientEmail = null;
            $created = false;
            $sent = false;
            $eligibility = [
                'eligible' => false,
                'reason' => !empty($candidateResolution['ambiguous'])
                    ? 'password_reset_ambiguous_identifier'
                    : 'password_reset_account_not_found',
                'category' => 'UNKNOWN',
                'is_super_admin' => false,
            ];

            if ($candidate) {
                $recipientEmail = forgot_password_resolve_email($candidate);
                $eligibility = password_reset_evaluate_eligibility($candidate, $pdo);
                if ($recipientEmail === null) {
                    $eligibility['eligible'] = false;
                    $eligibility['reason'] = 'password_reset_email_missing';
                }
                $canIssueReset = !empty($eligibility['eligible']) && $recipientEmail !== null;
            }

            if ($isDevelopment) {
                forgot_password_debug_log('Eligibility evaluated', [
                    'login_id' => $loginId,
                    'candidate_found' => $candidate !== null,
                    'recipient_email' => $recipientEmail,
                    'can_issue_reset' => $canIssueReset,
                    'reason_code' => (string)($eligibility['reason'] ?? 'password_reset_ineligible'),
                    'match_type' => (string)($candidateResolution['match_type'] ?? 'none'),
                    'feature_available' => $featureAvailable,
                ]);
            }

            if ($canIssueReset && $recipientEmail !== null) {
                $token = bin2hex(random_bytes(32));
                $tokenHash = hash('sha256', $token);
                $expiresAt = (new DateTimeImmutable('now', forgot_password_display_timezone()))
                    ->modify('+' . $resetTokenMinutes . ' minutes')
                    ->format('Y-m-d H:i:s');
                $resetUrl = base_url('reset-password.php') . '?token=' . rawurlencode($token);
                $displayName = trim((string)($candidate['f_nama'] ?? $candidate['f_nickname'] ?? $candidate['f_loginID'] ?? ''));

                $created = $userModel->createPasswordResetToken(
                    (string)$candidate['f_loginID'],
                    $recipientEmail,
                    $tokenHash,
                    null,
                    forgot_password_client_ip(),
                    substr(trim((string)($_SERVER['HTTP_USER_AGENT'] ?? '')), 0, 255),
                    $resetTokenMinutes,
                    false
                );

                if ($isDevelopment) {
                    forgot_password_debug_log('Reset token creation attempted', [
                        'login_id' => (string)$candidate['f_loginID'],
                        'recipient_email' => $recipientEmail,
                        'token_created' => $created,
                    ]);
                }

                if ($created) {
                    $mailError = '';
                    try {
                        [$mailHtml, $mailText] = Mailer::render('password-reset-request', [
                            'displayName' => $displayName !== '' ? $displayName : (string)$candidate['f_loginID'],
                            'loginId' => (string)$candidate['f_loginID'],
                            'resetUrl' => $resetUrl,
                            'expiresAt' => $expiresAt,
                            'expiresInMinutes' => $resetTokenMinutes,
                            'siteTitle' => app_config('site.title', 'Sistem Pengurusan Fasiliti (e-Facility)'),
                        ]);

                        $subject = (string)(__('forgot_password_mail_subject') ?: 'Reset kata laluan akaun anda');
                        $mailer = Mailer::fromConfig($pdo);
                        $sent = $mailer->send($recipientEmail, $subject, $mailHtml, $mailText);
                        $mailError = $sent ? '' : trim($mailer->getLastError());
                    } catch (Throwable $mailException) {
                        $sent = false;
                        $mailError = trim($mailException->getMessage());
                    }

                    if (!$sent) {
                        $userModel->invalidatePasswordResetTokenByHash($tokenHash);
                        error_log('[forgot-password] Failed sending reset mail to ' . $recipientEmail . ($mailError !== '' ? ' | ' . $mailError : ''));
                        if ($isDevelopment) {
                            $errors[] = sprintf(
                                (string)(__('forgot_password_error_mail_failed') ?: 'Emel reset tidak berjaya dihantar. %s'),
                                $mailError !== '' ? $mailError : (string)(__('forgot_password_error_mail_failed_reason_unknown') ?: 'Sebab kegagalan tidak direkodkan.')
                            );
                        }
                    } else {
                        $userModel->invalidatePasswordResetTokensByLoginID((string)$candidate['f_loginID'], $tokenHash);
                        if ($isDevelopment) {
                            forgot_password_debug_log('Reset mail sent successfully', [
                                'login_id' => (string)$candidate['f_loginID'],
                                'recipient_email' => $recipientEmail,
                            ]);
                        }
                    }

                    if (function_exists('audit_event')) {
                        try {
                            audit_event([
                                'event_type' => 'UPDATE',
                                'severity' => 'SECURITY',
                                'outcome' => $sent ? 'SUCCESS' : 'PENDING',
                                'target_type' => 'auth',
                                'target_id' => (string)$candidate['f_loginID'],
                                'target_label' => 'Password reset request',
                                'message' => 'Password reset link requested',
                                'request_id' => $GLOBALS['__AUDIT_REQUEST_ID'] ?? null,
                                'session_id' => session_id() ?: null,
                                'user_id' => !empty($candidate['f_userID']) && is_numeric($candidate['f_userID']) ? (int)$candidate['f_userID'] : null,
                            'meta' => [
                                'login_id' => (string)$candidate['f_loginID'],
                                'reason_code' => $sent ? 'password_reset_requested' : 'password_reset_mail_pending',
                                'auth_method' => 'MANUAL',
                                'auth_flow' => 'forgot_password',
                                'category' => forgot_password_normalize_category((string)($candidate['f_categoryUser'] ?? 'UMUM')),
                                'client_ip' => forgot_password_client_ip(),
                                'user_agent' => trim((string)($_SERVER['HTTP_USER_AGENT'] ?? '')) ?: null,
                                'email' => $recipientEmail,
                                'mail_sent' => $sent,
                                'source' => 'forgot_password_public_page',
                                ],
                            ]);
                        } catch (Throwable $auditError) {
                            error_log('[forgot-password] Audit logging failed: ' . $auditError->getMessage());
                        }
                    }
                } elseif ($isDevelopment) {
                    $errors[] = (string)(__('forgot_password_error_token_create_failed') ?: 'Token reset berjaya dijana tidak dapat direkodkan. Semak struktur jadual reset kata laluan.');
                    forgot_password_debug_log('Reset token creation failed before SMTP send', [
                        'login_id' => (string)$candidate['f_loginID'],
                        'recipient_email' => $recipientEmail,
                    ]);
                }
            } elseif (function_exists('audit_event')) {
                try {
                    audit_event([
                        'event_type' => 'UPDATE',
                        'severity' => 'SECURITY',
                        'outcome' => 'IGNORED',
                        'target_type' => 'auth',
                        'target_id' => $loginId,
                        'target_label' => 'Password reset request',
                        'message' => 'Password reset link requested for ineligible account',
                        'request_id' => $GLOBALS['__AUDIT_REQUEST_ID'] ?? null,
                        'session_id' => session_id() ?: null,
                        'meta' => [
                            'login_id' => $loginId,
                            'reason_code' => (string)($eligibility['reason'] ?? 'password_reset_ineligible'),
                            'match_type' => (string)($candidateResolution['match_type'] ?? 'none'),
                            'category' => (string)($eligibility['category'] ?? 'UNKNOWN'),
                            'is_super_admin' => !empty($eligibility['is_super_admin']),
                            'auth_method' => 'MANUAL',
                            'auth_flow' => 'forgot_password',
                            'client_ip' => forgot_password_client_ip(),
                            'user_agent' => trim((string)($_SERVER['HTTP_USER_AGENT'] ?? '')) ?: null,
                            'source' => 'forgot_password_public_page',
                        ],
                    ]);
                } catch (Throwable $auditError) {
                    error_log('[forgot-password] Audit logging failed: ' . $auditError->getMessage());
                }
            }

            if ($isDevelopment && !$canIssueReset) {
                forgot_password_debug_log('Reset request marked ineligible', [
                    'login_id' => $loginId,
                    'candidate_found' => $candidate !== null,
                    'recipient_email' => $recipientEmail,
                ]);
            }
        }
    }

    if ($errors === []) {
        $successMessage = trim((string)__('forgot_password_success_msg'));
        $redirectStatus = ($candidate !== null && !$canIssueReset) ? 'review' : 'sent';
        $redirectTarget = 'forgot-password.php?status=' . rawurlencode($redirectStatus);
        redirect($redirectTarget);
    }
}

$version = time();
$configModel = class_exists('Config') ? new Config(Database::getInstance('mysql')->getConnection()) : null;
$globalThemeSettings = $configModel ? ($configModel->getTema() ?: []) : [];
$loginHeaderLogo = app_config('branding.login_header_logo', 'assets/images/logo-upnm.png');
$loginPanelLogo = app_config('branding.login_panel_logo', 'assets/images/upnm30-logo.png');
$systemName = trim((string)app_config('system.name', 'Sistem Pengurusan Fasiliti (e-Facility)'));
$siteTitle = trim((string)app_config('site.title', 'Sistem Pengurusan Fasiliti (e-Facility)'));
$organizationName = trim((string)app_config('organization.name', $systemName !== '' ? $systemName : $siteTitle));
$pageLang = (string)($_SESSION['lang'] ?? 'ms');
require_once __DIR__ . '/includes/theme_palette_helper.php';
$activeThemeStyle = iqs_theme_resolve_public_palette($globalThemeSettings);
?>
<!DOCTYPE html>
<html lang="<?= h($_SESSION['lang'] ?? 'ms') ?>">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= h(__('forgot_password_page_title')) ?> | <?= h(app_config('site.title', 'Sistem Pengurusan Fasiliti (e-Facility)')) ?></title>
  <link rel="icon" href="<?= base_url(app_config('site.favicon', 'assets/images/default.ico')) ?>" type="image/x-icon">
  <link rel="stylesheet" href="<?= base_url('assets/css/icons.min.css?v=' . $version) ?>">
  <link rel="stylesheet" href="<?= base_url('assets/css/app.min.css?v=' . $version) ?>">
  <link rel="stylesheet" href="<?= base_url('assets/css/output.css?v=' . $version) ?>">
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11?v=<?= $version ?>"></script>
  <style>
    :root {
      --fp-bg: linear-gradient(180deg, #edf2f8 0%, #e3ebf5 100%);
      --fp-card: #ffffff;
      --fp-card-soft: #f6f9fc;
      --fp-ink: #0f1e33;
      --fp-text: #10213a;
      --fp-muted: #5b6b82;
      --fp-line: rgba(148, 163, 184, 0.24);
      --fp-primary: <?= h($activeThemeStyle['primary']) ?>;
      --fp-primary-strong: <?= h($activeThemeStyle['primaryStrong']) ?>;
      --fp-accent: <?= h($activeThemeStyle['accent']) ?>;
      --fp-primary-rgb: <?= h($activeThemeStyle['primaryRgb']) ?>;
      --fp-accent-rgb: <?= h($activeThemeStyle['accentRgb']) ?>;
      --fp-navy: #0c1b32;
      --fp-shadow: 0 28px 70px rgba(15, 23, 42, 0.12);
      --fp-soft-shadow: 0 18px 36px rgba(15, 23, 42, 0.08);
      --fp-danger-bg: rgba(254, 242, 242, 0.95);
      --fp-danger-border: rgba(185, 28, 28, 0.18);
      --fp-danger-text: #991b1b;
      --fp-success-bg: rgba(240, 253, 250, 0.95);
      --fp-success-border: rgba(15, 118, 110, 0.18);
      --fp-success-text: #0f766e;
      --fp-warning-bg: rgba(255, 247, 237, 0.95);
      --fp-warning-border: rgba(249, 115, 22, 0.22);
      --fp-warning-text: #9a3412;
      --fp-header-start: <?= h($activeThemeStyle['start']) ?>;
      --fp-header-end: <?= h($activeThemeStyle['end']) ?>;
    }

    body {
      min-height: 100vh;
      margin: 0;
      font-family: 'Plus Jakarta Sans', sans-serif;
      background:
        radial-gradient(circle at top left, rgba(var(--fp-primary-rgb), 0.12), transparent 28%),
        radial-gradient(circle at 100% 0%, rgba(var(--fp-accent-rgb), 0.12), transparent 26%),
        var(--fp-bg);
      color: var(--fp-text);
      overflow-x: hidden;
    }

    .fp-shell {
      min-height: 100vh;
      padding: 28px;
      position: relative;
    }

    .fp-shell::before {
      content: "";
      position: absolute;
      inset: 0 0 auto 0;
      height: 320px;
      background: linear-gradient(135deg, var(--fp-header-start), var(--fp-header-end));
      border-bottom-left-radius: 36px;
      border-bottom-right-radius: 36px;
      z-index: 0;
    }

    .fp-workspace {
      position: relative;
      z-index: 1;
      width: min(1360px, 100%);
      margin: 0 auto;
      display: flex;
      flex-direction: column;
      gap: 22px;
    }

    .fp-masthead {
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 20px;
      padding: 10px 4px 0;
      color: #ffffff;
    }

    .fp-brand-lockup {
      display: flex;
      align-items: center;
      gap: 18px;
      min-width: 0;
    }

    .fp-brand-lockup img {
      width: 150px;
      max-width: 100%;
      display: block;
      flex: 0 0 auto;
      filter: drop-shadow(0 12px 24px rgba(2, 6, 23, 0.18));
    }

    .fp-brand-copy {
      display: flex;
      flex-direction: column;
      gap: 8px;
      min-width: 0;
    }

    .fp-brand-copy span {
      font-size: 13px;
      line-height: 1.7;
      color: rgba(226, 232, 240, 0.88);
      max-width: 560px;
    }

    .fp-brand-copy strong {
      font-size: 14px;
      font-weight: 800;
      letter-spacing: 0.12em;
      text-transform: uppercase;
      color: rgba(255,255,255,0.92);
    }

    .fp-masthead-actions {
      display: flex;
      flex-wrap: wrap;
      justify-content: flex-end;
      gap: 12px;
      align-items: center;
    }

    .fp-version,
    .fp-home {
      display: inline-flex;
      align-items: center;
      gap: 8px;
      min-height: 44px;
      padding: 0 16px;
      border-radius: 999px;
      border: 1px solid rgba(255,255,255,0.14);
      background: rgba(255,255,255,0.08);
      color: #ffffff;
      text-decoration: none;
      font-size: 11px;
      font-weight: 800;
      letter-spacing: 0.1em;
      text-transform: uppercase;
      backdrop-filter: blur(16px);
    }

    .fp-board {
      display: grid;
      grid-template-columns: minmax(0, 1.25fr) minmax(360px, 430px);
      gap: 24px;
      align-items: stretch;
    }

    .fp-overview,
    .fp-panel {
      border-radius: 30px;
      background: var(--fp-card);
      border: 1px solid rgba(255,255,255,0.78);
      box-shadow: var(--fp-shadow);
      overflow: hidden;
    }

    .fp-overview {
      padding: 28px;
      display: flex;
      flex-direction: column;
      gap: 22px;
    }

    .fp-tag {
      display: inline-flex;
      align-items: center;
      gap: 8px;
      width: max-content;
      max-width: 100%;
      padding: 10px 14px;
      border-radius: 999px;
      background: rgba(var(--fp-primary-rgb), 0.08);
      border: 1px solid rgba(var(--fp-primary-rgb), 0.12);
      color: var(--fp-primary-strong);
      font-size: 11px;
      font-weight: 800;
      letter-spacing: 0.1em;
      text-transform: uppercase;
    }

    .fp-title {
      margin: 0;
      font-size: clamp(26px, 3.2vw, 38px);
      line-height: 1.1;
      letter-spacing: -0.03em;
      font-weight: 800;
      color: var(--fp-ink);
    }

    .fp-copy {
      margin: 0;
      font-size: 14px;
      line-height: 1.85;
      color: var(--fp-muted);
      max-width: 720px;
    }

    .fp-overview-grid {
      display: grid;
      grid-template-columns: minmax(0, 1fr);
      gap: 18px;
    }

    .fp-hero,
    .fp-note {
      border-radius: 26px;
      overflow: hidden;
      box-shadow: var(--fp-soft-shadow);
    }

    .fp-hero {
      position: relative;
      min-height: 380px;
      background:
        linear-gradient(135deg, rgba(12,27,50,0.95), rgba(18,53,95,0.82)),
        url('<?= base_url('assets/images/banner1.jpg') ?>') center/cover no-repeat;
      padding: 30px;
      display: flex;
      flex-direction: column;
      justify-content: flex-end;
      color: #ffffff;
    }

    .fp-hero::before {
      content: "";
      position: absolute;
      inset: 0;
      background: linear-gradient(180deg, rgba(var(--fp-primary-rgb), 0.08), rgba(12,27,50,0.18));
      pointer-events: none;
    }

    .fp-hero > * {
      position: relative;
      z-index: 1;
    }

    .fp-hero h2 {
      margin: 0 0 12px;
      font-size: clamp(24px, 2.8vw, 38px);
      line-height: 1.08;
      letter-spacing: -0.04em;
      font-weight: 800;
    }

    .fp-hero p {
      margin: 0;
      max-width: 460px;
      font-size: 13px;
      line-height: 1.8;
      color: rgba(241, 245, 249, 0.9);
    }

    .fp-note {
      width: 100%;
      padding: 20px;
      background: linear-gradient(145deg, #f7fafc, #eef4fa);
      border: 1px solid var(--fp-line);
    }

    .fp-note span,
    .fp-note strong {
      display: block;
    }

    .fp-note span {
      margin-bottom: 10px;
      font-size: 11px;
      font-weight: 800;
      letter-spacing: 0.1em;
      text-transform: uppercase;
      color: var(--fp-primary-strong);
    }

    .fp-note strong {
      font-size: 18px;
      line-height: 1.45;
      color: var(--fp-ink);
    }

    .fp-note p {
      margin: 10px 0 0;
      font-size: 13px;
      line-height: 1.75;
      color: var(--fp-muted);
    }

    .fp-panel {
      padding: 28px;
      display: flex;
      flex-direction: column;
      gap: 22px;
      background: linear-gradient(180deg, rgba(255,255,255,0.98), rgba(248,250,252,0.98));
    }

    .fp-auth-card {
      padding: 26px;
      border-radius: 26px;
      background: #ffffff;
      border: 1px solid rgba(226, 232, 240, 0.92);
      box-shadow: var(--fp-soft-shadow);
      display: flex;
      flex-direction: column;
      gap: 20px;
    }

    .fp-form-head {
      display: flex;
      gap: 16px;
      align-items: flex-start;
    }

    .fp-panel-logo {
      width: 68px;
      height: 68px;
      border-radius: 22px;
      flex: 0 0 auto;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      background: linear-gradient(135deg, rgba(var(--fp-primary-rgb), 0.12), rgba(var(--fp-accent-rgb), 0.12));
      box-shadow: inset 0 0 0 1px rgba(255,255,255,0.8);
    }

    .fp-panel-logo img {
      max-width: 46px;
      max-height: 46px;
    }

    .fp-kicker {
      display: inline-flex;
      align-items: center;
      gap: 8px;
      margin-bottom: 10px;
      color: var(--fp-primary-strong);
      font-size: 11px;
      font-weight: 800;
      letter-spacing: 0.14em;
      text-transform: uppercase;
    }

    .fp-panel-title {
      margin: 0 0 8px;
      font-size: 30px;
      line-height: 1.04;
      letter-spacing: -0.04em;
      font-weight: 800;
      color: var(--fp-ink);
    }

    .fp-panel-copy {
      margin: 0;
      font-size: 13px;
      line-height: 1.8;
      color: var(--fp-muted);
    }

    .fp-warning,
    .fp-success,
    .fp-errors {
      padding: 14px 16px;
      border-radius: 16px;
      font-size: 12px;
      line-height: 1.75;
    }

    .fp-warning {
      border: 1px solid var(--fp-warning-border);
      background: var(--fp-warning-bg);
      color: var(--fp-warning-text);
    }

    .fp-success {
      border: 1px solid var(--fp-success-border);
      background: var(--fp-success-bg);
      color: var(--fp-success-text);
    }

    .fp-errors {
      border: 1px solid var(--fp-danger-border);
      background: var(--fp-danger-bg);
      color: var(--fp-danger-text);
    }

    .fp-errors ul {
      margin: 0;
      padding-left: 18px;
    }

    .fp-form {
      display: flex;
      flex-direction: column;
      gap: 16px;
    }

    .fp-field {
      display: flex;
      flex-direction: column;
      gap: 8px;
    }

    .fp-field label {
      font-size: 11px;
      font-weight: 800;
      color: #334155;
      letter-spacing: .12em;
      text-transform: uppercase;
    }

    .fp-field input {
      width: 100%;
      border-radius: 18px;
      border: 1px solid rgba(148, 163, 184, 0.22);
      background: rgba(248, 250, 252, 0.92);
      padding: 15px 16px;
      font-size: 14px;
      outline: none;
      transition: border-color .18s ease, box-shadow .18s ease, background-color .18s ease;
    }

    .fp-field input:focus {
      border-color: rgba(var(--fp-primary-rgb), 0.4);
      box-shadow: 0 0 0 4px rgba(var(--fp-primary-rgb), 0.12);
      background: #fff;
    }

    .fp-hint {
      color: var(--fp-muted);
      font-size: 11px;
      line-height: 1.7;
    }

    .fp-submit {
      width: 100%;
      border: 0;
      border-radius: 18px;
      background: linear-gradient(135deg, var(--fp-primary-strong), var(--fp-primary) 55%, var(--fp-accent));
      color: #fff;
      padding: 15px 18px;
      font-weight: 800;
      letter-spacing: .12em;
      text-transform: uppercase;
      box-shadow: 0 18px 36px rgba(var(--fp-primary-rgb), 0.24);
      transition: transform .18s ease, box-shadow .18s ease, opacity .18s ease;
      cursor: pointer;
    }

    .fp-submit:hover {
      transform: translateY(-1px);
      box-shadow: 0 22px 40px rgba(var(--fp-primary-rgb), 0.3);
    }

    .fp-submit:disabled {
      opacity: .55;
      cursor: not-allowed;
      transform: none;
      box-shadow: none;
    }

    .fp-panel-footer {
      padding: 20px 22px;
      border-radius: 24px;
      background: var(--fp-card-soft);
      border: 1px solid var(--fp-line);
      display: flex;
      flex-direction: column;
      gap: 12px;
    }

    .fp-panel-footer p {
      margin: 0;
      font-size: 13px;
      line-height: 1.75;
      color: var(--fp-muted);
    }

    .fp-footer-row {
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 12px;
      flex-wrap: wrap;
    }

    .fp-back {
      color: var(--fp-primary-strong);
      text-decoration: none;
      font-size: 12px;
      font-weight: 700;
    }

    .fp-back:hover {
      text-decoration: underline;
    }

    .fp-meta {
      color: #94a3b8;
      font-size: 11px;
      line-height: 1.7;
      text-align: right;
    }

    @media (max-width: 1200px) {
      .fp-board {
        grid-template-columns: minmax(0, 1fr) minmax(340px, 400px);
      }
    }

    @media (max-width: 1024px) {
      .fp-shell {
        padding: 18px;
      }

      .fp-masthead,
      .fp-board,
      .fp-overview-grid {
        display: grid;
        grid-template-columns: 1fr;
      }

      .fp-masthead {
        align-items: flex-start;
      }

      .fp-masthead-actions {
        justify-content: flex-start;
      }

      .fp-board {
        display: grid;
      }
    }

    @media (max-width: 768px) {
      .fp-shell {
        padding: 0;
      }

      .fp-shell::before {
        border-radius: 0;
        height: 260px;
      }

      .fp-workspace {
        gap: 16px;
      }

      .fp-masthead,
      .fp-overview,
      .fp-panel {
        border-radius: 0;
      }

      .fp-masthead,
      .fp-overview,
      .fp-panel,
      .fp-hero {
        padding-left: 18px;
        padding-right: 18px;
      }

      .fp-brand-lockup,
      .fp-form-head,
      .fp-footer-row {
        flex-direction: column;
        align-items: flex-start;
      }

      .fp-brand-lockup img {
        width: 132px;
      }

      .fp-overview,
      .fp-panel {
        padding-top: 22px;
        padding-bottom: 22px;
      }

      .fp-hero,
      .fp-note,
      .fp-auth-card,
      .fp-panel-footer {
        border-radius: 22px;
      }
    }
  </style>
</head>
<body>
  <div class="fp-shell">
    <div class="fp-workspace">
      <header class="fp-masthead">
        <div class="fp-brand-lockup">
          <img src="<?= base_url($loginHeaderLogo) ?>" alt="UPNM Logo">
          <div class="fp-brand-copy">
            <strong><?= h($systemName !== '' ? $systemName : $siteTitle) ?></strong>
            <span><?= h($organizationName !== '' ? $organizationName : ($pageLang === 'en' ? 'Official digital access for facility operations and support services.' : 'Akses digital rasmi untuk operasi fasiliti dan perkhidmatan sokongan.')) ?></span>
          </div>
        </div>
        <div class="fp-masthead-actions">
          <div class="fp-version">
            <i class="ri-shield-check-line"></i>
            <span><?= h(app_current_version_label()) ?></span>
          </div>
          <a class="fp-home" href="<?= h(base_path('index.php')) ?>"><i class="ri-arrow-left-line"></i> <?= h(__('forgot_password_back_to_login')) ?></a>
        </div>
      </header>

      <div class="fp-board">
        <section class="fp-overview">
          <span class="fp-tag"><i class="ri-government-line"></i> <?= h(__('forgot_password_kicker')) ?></span>
          <h1 class="fp-title"><?= h($pageLang === 'en' ? 'Password recovery through an official and controlled workflow.' : 'Pemulihan kata laluan melalui aliran kerja rasmi dan terkawal.') ?></h1>
          <p class="fp-copy"><?= h(__('forgot_password_intro')) ?></p>

          <div class="fp-overview-grid">
            <div class="fp-hero">
              <h2><?= h($pageLang === 'en' ? 'Maintain secure access without breaking institutional controls.' : 'Kekalkan akses yang selamat tanpa menjejaskan kawalan institusi.') ?></h2>
              <p><?= h($pageLang === 'en' ? 'Submit your login identifier to receive a controlled reset link using the registered communication channel for your account.' : 'Hantar pengenal log masuk anda untuk menerima pautan tetapan semula terkawal melalui saluran komunikasi berdaftar bagi akaun anda.') ?></p>
            </div>

            <aside class="fp-note">
              <span><?= h($pageLang === 'en' ? 'Recovery Note' : 'Nota Pemulihan') ?></span>
              <strong><?= h($pageLang === 'en' ? 'Only eligible manual-login accounts can receive reset instructions.' : 'Hanya akaun log masuk manual yang layak akan menerima arahan tetapan semula.') ?></strong>
              <p><?= h($pageLang === 'en' ? 'Availability follows the current authentication policy and the registered account profile.' : 'Ketersediaan tertakluk kepada polisi autentikasi semasa dan profil akaun berdaftar.') ?></p>
            </aside>
          </div>
        </section>

        <aside class="fp-panel">
          <div class="fp-auth-card">
            <div class="fp-form-head">
              <div class="fp-panel-logo">
                <img src="<?= base_url($loginPanelLogo) ?>" alt="Portal Logo">
              </div>
              <div>
                <span class="fp-kicker"><i class="ri-key-2-line"></i> <?= h(__('forgot_password_kicker')) ?></span>
                <p class="fp-panel-copy"><?= h($pageLang === 'en' ? 'Enter your login ID, registered email, staff ID, or employee number to continue.' : 'Masukkan ID log masuk, emel berdaftar, ID staf atau nombor pekerja untuk meneruskan proses pemulihan.') ?></p>
              </div>
            </div>

            <?php if ($showSuccessAlert): ?>
              <div class="fp-success">
                <?= h(__('forgot_password_success_msg')) ?>
              </div>
            <?php endif; ?>

            <?php if ($errors !== []): ?>
              <div class="fp-errors">
                <ul>
                  <?php foreach ($errors as $error): ?>
                    <li><?= h((string)$error) ?></li>
                  <?php endforeach; ?>
                </ul>
              </div>
            <?php endif; ?>

            <form method="POST" action="<?= h(base_path('forgot-password.php')) ?>" class="fp-form" autocomplete="off">
              <input type="hidden" name="csrf_token" value="<?= h((string)($_SESSION['csrf_token'] ?? '')) ?>">

              <div class="fp-field">
                <label for="login_id"><?= h(__('forgot_password_login_id_label')) ?></label>
                <input
                  id="login_id"
                  name="login_id"
                  type="text"
                  required
                  maxlength="150"
                  value="<?= h($loginIdValue) ?>"
                  placeholder="<?= h(__('forgot_password_login_id_placeholder')) ?>"
                  autocomplete="username"
                >
                <div class="fp-hint"><?= h(__('forgot_password_login_id_hint')) ?></div>
              </div>

              <button type="submit" class="fp-submit" <?= $featureAvailable ? '' : 'disabled' ?>>
                <?= h(__('forgot_password_submit_btn')) ?>
              </button>
            </form>
          </div>

          <div class="fp-panel-footer">
            <p><?= h($pageLang === 'en' ? 'If you do not receive a reset message, verify your login identifier and the registered communication channel for the account.' : 'Jika anda tidak menerima mesej tetapan semula, semak semula ID log masuk dan saluran komunikasi berdaftar bagi akaun tersebut.') ?></p>
            <div class="fp-footer-row">
              <a class="fp-back" href="<?= h(base_path('index.php')) ?>"><?= h(__('forgot_password_back_to_login')) ?></a>
            </div>
          </div>
        </aside>
      </div>
    </div>
  </div>

  <?php if ($showReviewAlert): ?>
    <script>
      document.addEventListener('DOMContentLoaded', function () {
        if (!(window.Swal && typeof window.Swal.fire === 'function')) {
          return;
        }

        window.Swal.fire({
          icon: 'info',
          title: <?= json_encode((string)(__('forgot_password_review_title') ?: 'Semakan Diterima'), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>,
          text: <?= json_encode((string)(__('forgot_password_review_msg') ?: 'Permintaan anda telah diterima. Tindakan susulan tertakluk kepada kawalan akaun dan polisi akses semasa.'), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>,
          confirmButtonText: <?= json_encode((string)(__('forgot_password_review_ok') ?: 'Faham'), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>,
          confirmButtonColor: '#2563eb'
        });
      });
    </script>
  <?php endif; ?>

</body>
</html>
