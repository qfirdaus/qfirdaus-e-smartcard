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

if (!function_exists('validate_pending_password_change_session')) {
    /**
     * @return array{valid:bool,pending:?array<string,mixed>,reason:string}
     */
    function validate_pending_password_change_session(): array
    {
        $pending = $_SESSION['pending_password_change'] ?? null;
        if (!is_array($pending) || $pending === []) {
            return ['valid' => false, 'pending' => null, 'reason' => 'missing'];
        }

        $loginId = trim((string)($pending['login_id'] ?? ''));
        $issuedAt = trim((string)($pending['issued_at'] ?? ''));
        $issuedAtTs = $issuedAt !== '' ? strtotime($issuedAt) : false;

        if ($loginId === '' || $issuedAtTs === false) {
            return ['valid' => false, 'pending' => null, 'reason' => 'invalid'];
        }

        if ((time() - (int)$issuedAtTs) > 900) {
            return ['valid' => false, 'pending' => null, 'reason' => 'expired'];
        }

        return ['valid' => true, 'pending' => $pending, 'reason' => 'ok'];
    }
}

if (!function_exists('clear_pending_password_change_session')) {
    function clear_pending_password_change_session(): void
    {
        unset($_SESSION['pending_password_change']);
    }
}

if (!function_exists('change_password_client_ip')) {
    function change_password_client_ip(): ?string
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

if (!function_exists('password_policy_errors')) {
    function change_password_runtime_policy(): array
    {
        $defaults = [
            'min_length' => 8,
            'expiry_days' => 90,
            'history_count' => 5,
            'require_uppercase' => true,
            'require_lowercase' => true,
            'require_number' => true,
            'require_symbol' => false,
            'block_loginid_variants' => true,
        ];
        if (!function_exists('get_auth_password_policy_config')) {
            return $defaults;
        }

        $policy = get_auth_password_policy_config();
        return [
            'min_length' => (int)($policy['min_length'] ?? $defaults['min_length']),
            'expiry_days' => (int)($policy['expiry_days'] ?? $defaults['expiry_days']),
            'history_count' => (int)($policy['history_count'] ?? $defaults['history_count']),
            'require_uppercase' => !empty($policy['require_uppercase']),
            'require_lowercase' => !empty($policy['require_lowercase']),
            'require_number' => !empty($policy['require_number']),
            'require_symbol' => !empty($policy['require_symbol']),
            'block_loginid_variants' => !empty($policy['block_loginid_variants']),
        ];
    }

    function change_password_min_length_message(int $minLength): string
    {
        $template = (string)(__('password_change_error_min_length_template') ?: 'Kata laluan mesti sekurang-kurangnya %d aksara.');
        return sprintf($template, $minLength);
    }

    /**
     * @return string[]
     */
    function password_login_id_tokens(string $loginId): array
    {
        $tokens = [];
        $normalizedLoginId = strtolower(trim($loginId));
        if ($normalizedLoginId === '') {
            return [];
        }

        $tokens[] = $normalizedLoginId;

        $collapsed = preg_replace('/[^a-z0-9]/i', '', $normalizedLoginId);
        if (is_string($collapsed) && $collapsed !== '') {
            $tokens[] = $collapsed;
        }

        $parts = preg_split('/[^a-z0-9]+/i', $normalizedLoginId) ?: [];
        foreach ($parts as $part) {
            $part = strtolower(trim((string)$part));
            if ($part === '' || strlen($part) < 4) {
                continue;
            }
            $tokens[] = $part;
            $trimmedPart = ltrim($part, '0');
            if ($trimmedPart !== '' && strlen($trimmedPart) >= 3) {
                $tokens[] = $trimmedPart;
            }
        }

        $tokens = array_values(array_unique(array_filter($tokens, static fn($token) => is_string($token) && $token !== '')));
        usort($tokens, static fn($a, $b) => strlen((string)$b) <=> strlen((string)$a));

        return $tokens;
    }

    /**
     * @return string[]
     */
    function password_policy_errors(string $password, string $confirmPassword, string $loginId): array
    {
        $runtimePolicy = change_password_runtime_policy();
        $minLength = max(8, (int)($runtimePolicy['min_length'] ?? 8));
        $errors = [];
        if ($password === '' || $confirmPassword === '') {
            $errors[] = __('password_change_error_required');
            return $errors;
        }
        if ($password !== $confirmPassword) {
            $errors[] = __('password_change_error_mismatch');
        }
        if (strlen($password) < $minLength) {
            $errors[] = change_password_min_length_message($minLength);
        }
        if (!empty($runtimePolicy['require_uppercase']) && !preg_match('/[A-Z]/', $password)) {
            $errors[] = __('password_change_error_uppercase');
        }
        if (!empty($runtimePolicy['require_lowercase']) && !preg_match('/[a-z]/', $password)) {
            $errors[] = __('password_change_error_lowercase');
        }
        if (!empty($runtimePolicy['require_number']) && !preg_match('/\d/', $password)) {
            $errors[] = __('password_change_error_number');
        }
        if (!empty($runtimePolicy['require_symbol']) && !preg_match('/[^A-Za-z0-9]/', $password)) {
            $errors[] = __('password_change_error_symbol');
        }

        if (!empty($runtimePolicy['block_loginid_variants'])) {
            $normalizedPassword = strtolower($password);
            foreach (password_login_id_tokens($loginId) as $token) {
                if ($token !== '' && str_contains($normalizedPassword, $token)) {
                    $errors[] = __('password_change_error_contains_login');
                    break;
                }
            }
        }

        return array_values(array_unique(array_filter($errors)));
    }
}

$validation = validate_pending_password_change_session();
if (!$validation['valid']) {
    clear_pending_password_change_session();
    set_alert([
        'type' => 'sweet',
        'title' => 'password_change_session_invalid_title',
        'text' => $validation['reason'] === 'expired'
            ? 'password_change_session_expired_msg'
            : 'password_change_session_invalid_msg',
        'icon' => 'warning',
        'confirm' => true,
    ]);
    redirect('index.php');
}

$pending = $validation['pending'] ?? [];
$pendingLoginId = trim((string)($pending['login_id'] ?? ''));
$pendingReason = trim((string)($pending['reason'] ?? 'password_change_required'));
$reasonTextKey = $pendingReason === 'password_expired'
    ? 'password_change_reason_expired'
    : 'password_change_reason_required';
$runtimePasswordPolicy = change_password_runtime_policy();
$requestMethod = strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET'));
$errors = [];

if ($requestMethod === 'POST') {
    $csrfToken = (string)($_POST['csrf_token'] ?? '');
    if ($csrfToken === '' || !hash_equals((string)($_SESSION['csrf_token'] ?? ''), $csrfToken)) {
        $errors[] = __('password_change_error_csrf');
    } else {
        $newPassword = (string)($_POST['new_password'] ?? '');
        $confirmPassword = (string)($_POST['confirm_password'] ?? '');
        $errors = password_policy_errors($newPassword, $confirmPassword, $pendingLoginId);

        if ($errors === []) {
            $pdo = Database::getInstance('mysql')->getConnection();
            $userModel = new User($pdo);
            $user = $userModel->findByLoginID($pendingLoginId);

            if (!$user) {
                $errors[] = __('password_change_error_user_not_found');
            } elseif (password_verify($newPassword, (string)($user['f_password'] ?? ''))) {
                $errors[] = __('password_change_error_reuse_current');
            } elseif ($userModel->isPasswordReusedInHistory($pendingLoginId, $newPassword, (int)($runtimePasswordPolicy['history_count'] ?? 5))) {
                $errors[] = __('password_change_error_reuse_history');
            } else {
                $passwordHash = password_hash($newPassword, PASSWORD_DEFAULT);
                $expiresAt = (new DateTimeImmutable('now', new DateTimeZone('Asia/Kuala_Lumpur')))
                    ->modify('+' . (int)($runtimePasswordPolicy['expiry_days'] ?? 90) . ' days')
                    ->format('Y-m-d H:i:s');
                $updated = $userModel->updateManualPasswordByLoginID($pendingLoginId, $passwordHash, null, (int)($runtimePasswordPolicy['expiry_days'] ?? 90), 'forced_change');

                if (!$updated) {
                    $errors[] = __('password_change_error_update_failed');
                } else {
                    $notificationSent = false;
                    $notificationEmail = strtolower(trim((string)($user['f_email'] ?? $user['f_loginID'] ?? '')));
                    if ($notificationEmail !== '' && filter_var($notificationEmail, FILTER_VALIDATE_EMAIL)) {
                        try {
                            [$mailHtml, $mailText] = Mailer::render('password-change-notification', [
                                'displayName' => trim((string)($user['f_nama'] ?? $user['f_nickname'] ?? $pendingLoginId)),
                                'loginId' => trim((string)($user['f_loginID'] ?? $pendingLoginId)),
                                'changedAt' => (new DateTimeImmutable('now', new DateTimeZone('Asia/Kuala_Lumpur')))->format('Y-m-d H:i:s'),
                                'siteTitle' => app_config('site.title', 'Sistem Pengurusan Fasiliti (e-Facility)'),
                            ]);
                            $mailer = Mailer::fromConfig($pdo);
                            $notificationSent = $mailer->send(
                                $notificationEmail,
                                (string)(__('password_change_notification_subject') ?: 'Kata laluan anda telah dikemas kini'),
                                $mailHtml,
                                $mailText
                            );
                            if (!$notificationSent) {
                                $mailError = trim($mailer->getLastError());
                                error_log('[change-password] Notification mail failed for ' . $notificationEmail . ($mailError !== '' ? ' | ' . $mailError : ''));
                            }
                        } catch (Throwable $mailError) {
                            error_log('[change-password] Notification mail failed: ' . $mailError->getMessage());
                        }
                    }

                    if (function_exists('audit_event')) {
                        try {
                            $actorName = trim((string)($user['f_nama'] ?? $user['f_nickname'] ?? ''));
                            $actorLoginId = trim((string)($user['f_loginID'] ?? $pendingLoginId));
                            $actorLabel = function_exists('audit_format_actor_label')
                                ? audit_format_actor_label($actorName !== '' ? $actorName : null, $actorLoginId)
                                : $actorLoginId;

                            audit_event([
                                'event_type' => 'UPDATE',
                                'severity' => 'SECURITY',
                                'outcome' => 'SUCCESS',
                                'target_type' => 'auth',
                                'target_id' => 'password',
                                'target_label' => $actorLoginId,
                                'message' => function_exists('audit_format_message')
                                    ? audit_format_message('Password changed through forced password update flow', $actorLabel)
                                    : 'Password changed through forced password update flow',
                                'request_id' => $GLOBALS['__AUDIT_REQUEST_ID'] ?? null,
                                'session_id' => session_id() ?: null,
                                'user_id' => !empty($user['f_userID']) && is_numeric($user['f_userID']) ? (int)$user['f_userID'] : null,
                                'actor_label' => $actorLabel,
                                'meta' => [
                                    'login_id' => $actorLoginId,
                                    'reason_code' => 'forced_password_change_completed',
                                    'auth_method' => 'MANUAL',
                                    'auth_flow' => 'change_password',
                                    'category' => strtoupper(trim((string)($user['f_categoryUser'] ?? 'UMUM'))),
                                    'client_ip' => change_password_client_ip(),
                                    'user_agent' => trim((string)($_SERVER['HTTP_USER_AGENT'] ?? '')) ?: null,
                                    'reason' => $pendingReason,
                                    'forced_flow' => true,
                                    'password_expiry_at' => $expiresAt,
                                    'notification_email' => $notificationEmail !== '' ? $notificationEmail : null,
                                    'notification_sent' => $notificationSent,
                                ],
                            ]);
                        } catch (Throwable $auditError) {
                            error_log('[change-password] Audit logging failed: ' . $auditError->getMessage());
                        }
                    }

                    clear_pending_password_change_session();
                    set_alert([
                        'type' => 'sweet',
                        'title' => 'password_change_success_title',
                        'text' => 'password_change_success_msg',
                        'icon' => 'success',
                        'confirm' => true,
                    ]);
                    redirect('index.php');
                }
            }
        }
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
  <title><?= h(__('password_change_page_title')) ?> | <?= h(app_config('site.title', 'Sistem Pengurusan Fasiliti (e-Facility)')) ?></title>
  <link rel="icon" href="<?= base_url(app_config('site.favicon', 'assets/images/default.ico')) ?>" type="image/x-icon">
  <link rel="stylesheet" href="<?= base_url('assets/css/icons.min.css?v=' . $version) ?>">
  <link rel="stylesheet" href="<?= base_url('assets/css/app.min.css?v=' . $version) ?>">
  <link rel="stylesheet" href="<?= base_url('assets/css/output.css?v=' . $version) ?>">
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11?v=<?= $version ?>"></script>
  <style>
    :root {
      --cp-bg: linear-gradient(180deg, #edf2f8 0%, #e3ebf5 100%);
      --cp-card: #ffffff;
      --cp-card-soft: #f6f9fc;
      --cp-ink: #0f1e33;
      --cp-text: #10213a;
      --cp-muted: #5b6b82;
      --cp-line: rgba(148, 163, 184, 0.24);
      --cp-primary: <?= h($activeThemeStyle['primary']) ?>;
      --cp-primary-strong: <?= h($activeThemeStyle['primaryStrong']) ?>;
      --cp-accent: <?= h($activeThemeStyle['accent']) ?>;
      --cp-primary-rgb: <?= h($activeThemeStyle['primaryRgb']) ?>;
      --cp-accent-rgb: <?= h($activeThemeStyle['accentRgb']) ?>;
      --cp-shadow: 0 28px 70px rgba(15, 23, 42, 0.12);
      --cp-soft-shadow: 0 18px 36px rgba(15, 23, 42, 0.08);
      --cp-danger: #b91c1c;
      --cp-danger-bg: rgba(254, 242, 242, 0.92);
      --cp-danger-border: rgba(185, 28, 28, 0.18);
      --cp-header-start: <?= h($activeThemeStyle['start']) ?>;
      --cp-header-end: <?= h($activeThemeStyle['end']) ?>;
    }

    body {
      min-height: 100vh;
      margin: 0;
      font-family: 'Plus Jakarta Sans', sans-serif;
      background:
        radial-gradient(circle at top left, rgba(var(--cp-primary-rgb), 0.12), transparent 28%),
        radial-gradient(circle at 100% 0%, rgba(var(--cp-accent-rgb), 0.12), transparent 26%),
        var(--cp-bg);
      color: var(--cp-text);
      overflow-x: hidden;
    }

    .cp-shell {
      min-height: 100vh;
      padding: 28px;
      position: relative;
    }

    .cp-shell::before {
      content: "";
      position: absolute;
      inset: 0 0 auto 0;
      height: 300px;
      background: linear-gradient(135deg, var(--cp-header-start), var(--cp-header-end));
      border-bottom-left-radius: 36px;
      border-bottom-right-radius: 36px;
      z-index: 0;
    }

    .cp-workspace {
      position: relative;
      z-index: 1;
      width: min(1280px, 100%);
      margin: 0 auto;
      display: flex;
      flex-direction: column;
      gap: 22px;
    }

    .cp-masthead {
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 18px;
      padding: 8px 4px 0;
      color: #ffffff;
    }

    .cp-brand-lockup {
      display: flex;
      align-items: center;
      gap: 18px;
      min-width: 0;
    }

    .cp-brand-lockup img {
      width: 150px;
      max-width: 100%;
      display: block;
      flex: 0 0 auto;
      filter: drop-shadow(0 12px 24px rgba(2, 6, 23, 0.18));
    }

    .cp-brand-copy {
      display: flex;
      flex-direction: column;
      gap: 8px;
      min-width: 0;
    }

    .cp-brand-copy strong {
      font-size: 14px;
      font-weight: 800;
      letter-spacing: 0.12em;
      text-transform: uppercase;
      color: rgba(255,255,255,0.92);
    }

    .cp-brand-copy span {
      font-size: 13px;
      line-height: 1.7;
      color: rgba(226, 232, 240, 0.88);
      max-width: 560px;
    }

    .cp-masthead-actions {
      display: flex;
      flex-wrap: wrap;
      justify-content: flex-end;
      gap: 12px;
      align-items: center;
    }

    .cp-version,
    .cp-home {
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

    .cp-board {
      display: grid;
      grid-template-columns: minmax(0, 1.08fr) minmax(380px, 460px);
      gap: 24px;
      align-items: stretch;
    }

    .cp-overview,
    .cp-panel {
      border-radius: 30px;
      background: var(--cp-card);
      border: 1px solid rgba(255,255,255,0.78);
      box-shadow: var(--cp-shadow);
      overflow: hidden;
    }

    .cp-overview {
      padding: 28px;
      display: flex;
      flex-direction: column;
      gap: 18px;
    }

    .cp-tag {
      display: inline-flex;
      align-items: center;
      gap: 8px;
      width: max-content;
      max-width: 100%;
      padding: 10px 14px;
      border-radius: 999px;
      background: rgba(var(--cp-primary-rgb), 0.08);
      border: 1px solid rgba(var(--cp-primary-rgb), 0.12);
      color: var(--cp-primary-strong);
      font-size: 11px;
      font-weight: 800;
      letter-spacing: 0.1em;
      text-transform: uppercase;
    }

    .cp-title {
      margin: 0;
      font-size: clamp(32px, 4vw, 48px);
      line-height: 1.02;
      letter-spacing: -0.05em;
      font-weight: 800;
      color: var(--cp-ink);
    }

    .cp-subtitle {
      margin: 0;
      font-size: 14px;
      line-height: 1.8;
      color: var(--cp-muted);
      max-width: 720px;
    }

    .cp-overview-grid {
      display: grid;
      grid-template-columns: minmax(0, 1fr) minmax(230px, 290px);
      gap: 18px;
    }

    .cp-hero,
    .cp-note {
      border-radius: 26px;
      overflow: hidden;
      box-shadow: var(--cp-soft-shadow);
    }

    .cp-hero {
      min-height: 340px;
      padding: 28px;
      display: flex;
      flex-direction: column;
      justify-content: flex-end;
      color: #ffffff;
      background:
        linear-gradient(135deg, rgba(12,27,50,0.95), rgba(18,53,95,0.82)),
        url('<?= base_url('assets/images/banner3.jpg') ?>') center/cover no-repeat;
    }

    .cp-hero h2 {
      margin: 0 0 12px;
      font-size: clamp(24px, 2.8vw, 38px);
      line-height: 1.08;
      letter-spacing: -0.04em;
      font-weight: 800;
    }

    .cp-hero p {
      margin: 0;
      max-width: 460px;
      font-size: 13px;
      line-height: 1.8;
      color: rgba(241, 245, 249, 0.9);
    }

    .cp-note {
      padding: 20px;
      background: linear-gradient(145deg, #f7fafc, #eef4fa);
      border: 1px solid var(--cp-line);
    }

    .cp-note span,
    .cp-note strong {
      display: block;
    }

    .cp-note span {
      margin-bottom: 10px;
      font-size: 11px;
      font-weight: 800;
      letter-spacing: 0.1em;
      text-transform: uppercase;
      color: var(--cp-primary-strong);
    }

    .cp-note strong {
      font-size: 18px;
      line-height: 1.45;
      color: var(--cp-ink);
    }

    .cp-note p {
      margin: 10px 0 0;
      font-size: 13px;
      line-height: 1.75;
      color: var(--cp-muted);
    }

    .cp-panel {
      padding: 28px;
      display: flex;
      flex-direction: column;
      gap: 18px;
      background: linear-gradient(180deg, rgba(255,255,255,0.98), rgba(248,250,252,0.98));
    }

    .cp-auth-card {
      padding: 24px;
      border-radius: 26px;
      background: #ffffff;
      border: 1px solid rgba(226, 232, 240, 0.92);
      box-shadow: var(--cp-soft-shadow);
      display: flex;
      flex-direction: column;
      gap: 18px;
    }

    .cp-form-head {
      display: flex;
      gap: 16px;
      align-items: flex-start;
    }

    .cp-panel-logo {
      width: 68px;
      height: 68px;
      border-radius: 22px;
      flex: 0 0 auto;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      background: linear-gradient(135deg, rgba(var(--cp-primary-rgb), 0.12), rgba(var(--cp-accent-rgb), 0.12));
      box-shadow: inset 0 0 0 1px rgba(255,255,255,0.8);
    }

    .cp-panel-logo img {
      max-width: 46px;
      max-height: 46px;
    }

    .cp-kicker {
      display: inline-flex;
      align-items: center;
      gap: 8px;
      margin-bottom: 10px;
      color: var(--cp-primary-strong);
      font-size: 11px;
      font-weight: 800;
      letter-spacing: 0.14em;
      text-transform: uppercase;
    }

    .cp-panel-title {
      margin: 0 0 8px;
      font-size: 30px;
      line-height: 1.04;
      letter-spacing: -0.04em;
      font-weight: 800;
      color: var(--cp-ink);
    }

    .cp-panel-copy,
    .cp-meta {
      margin: 0;
      font-size: 13px;
      line-height: 1.75;
      color: var(--cp-muted);
    }

    .cp-meta {
      padding: 14px 16px;
      border-radius: 14px;
      border: 1px solid rgba(148, 163, 184, 0.18);
      background: rgba(248, 250, 252, 0.92);
    }

    .cp-errors {
      padding: 14px 16px;
      border-radius: 14px;
      border: 1px solid var(--cp-danger-border);
      background: var(--cp-danger-bg);
      color: var(--cp-danger);
      font-size: 13px;
      line-height: 1.7;
    }

    .cp-errors ul {
      margin: 0;
      padding-left: 18px;
    }

    .cp-form {
      display: flex;
      flex-direction: column;
      gap: 16px;
    }

    .cp-field {
      display: flex;
      flex-direction: column;
      gap: 8px;
    }

    .cp-field label {
      font-size: 11px;
      font-weight: 800;
      color: #334155;
      letter-spacing: .12em;
      text-transform: uppercase;
    }

    .cp-field input {
      width: 100%;
      border-radius: 18px;
      border: 1px solid rgba(148, 163, 184, 0.22);
      background: rgba(248, 250, 252, 0.95);
      padding: 15px 16px;
      font-size: 14px;
      outline: none;
      transition: border-color .18s ease, box-shadow .18s ease, background-color .18s ease;
    }

    .cp-field input:focus {
      border-color: rgba(var(--cp-primary-rgb), 0.4);
      box-shadow: 0 0 0 4px rgba(var(--cp-primary-rgb), 0.12);
      background: #fff;
    }

    .cp-password-wrap {
      position: relative;
    }

    .cp-password-wrap input {
      padding-right: 52px;
    }

    .cp-password-toggle {
      position: absolute;
      top: 50%;
      right: 10px;
      transform: translateY(-50%);
      width: 34px;
      height: 34px;
      border: 0;
      border-radius: 10px;
      background: transparent;
      color: #64748b;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      cursor: pointer;
      transition: background-color .18s ease, color .18s ease;
    }

    .cp-password-toggle:hover {
      background: rgba(148, 163, 184, 0.12);
      color: #0f172a;
    }

    .cp-password-toggle:focus {
      outline: none;
      background: rgba(var(--cp-primary-rgb), 0.12);
      color: var(--cp-primary-strong);
    }

    .cp-password-toggle svg {
      width: 18px;
      height: 18px;
      pointer-events: none;
    }

    .cp-rules {
      padding: 14px 16px;
      border-radius: 14px;
      border: 1px solid rgba(148, 163, 184, 0.18);
      background: rgba(248, 250, 252, 0.94);
    }

    .cp-rules-title {
      margin: 0 0 10px;
      font-size: 12px;
      font-weight: 700;
      color: #0f172a;
    }

    .cp-rules-list {
      list-style: none;
      margin: 0;
      padding: 0;
      display: grid;
      gap: 8px;
    }

    .cp-rule {
      display: flex;
      align-items: center;
      gap: 10px;
      font-size: 12px;
      line-height: 1.6;
      color: var(--cp-muted);
    }

    .cp-rule-dot {
      width: 18px;
      height: 18px;
      border-radius: 999px;
      border: 1px solid #cbd5e1;
      background: #fff;
      flex: 0 0 auto;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      transition: background-color .18s ease, transform .18s ease, border-color .18s ease;
      font-size: 11px;
      font-weight: 700;
      color: #94a3b8;
    }

    .cp-rule.is-valid {
      color: #0f766e;
    }

    .cp-rule.is-valid .cp-rule-dot {
      background: #0f766e;
      border-color: #0f766e;
      transform: scale(1.08);
      color: #fff;
    }

    .cp-submit {
      width: 100%;
      border: 0;
      border-radius: 18px;
      padding: 15px 18px;
      background: linear-gradient(135deg, var(--cp-primary-strong), var(--cp-primary) 55%, var(--cp-accent));
      color: #fff;
      font-size: 13px;
      font-weight: 800;
      letter-spacing: .12em;
      text-transform: uppercase;
      box-shadow: 0 18px 36px rgba(var(--cp-primary-rgb), 0.24);
      transition: transform .18s ease, box-shadow .18s ease, opacity .18s ease;
    }

    .cp-submit:disabled {
      opacity: .55;
      cursor: not-allowed;
      box-shadow: none;
    }

    .cp-foot {
      padding: 20px 22px;
      border-radius: 24px;
      background: var(--cp-card-soft);
      border: 1px solid var(--cp-line);
      color: var(--cp-muted);
      font-size: 13px;
      line-height: 1.75;
    }

    @media (max-width: 1024px) {
      .cp-shell {
        padding: 18px;
      }

      .cp-masthead,
      .cp-board,
      .cp-overview-grid {
        display: grid;
        grid-template-columns: 1fr;
      }

      .cp-masthead {
        align-items: flex-start;
      }

      .cp-masthead-actions {
        justify-content: flex-start;
      }
    }

    @media (max-width: 768px) {
      .cp-shell {
        padding: 0;
      }

      .cp-shell::before {
        border-radius: 0;
        height: 240px;
      }

      .cp-overview,
      .cp-panel {
        border-radius: 0;
        padding: 22px 18px;
      }

      .cp-masthead {
        padding: 18px;
      }

      .cp-brand-lockup,
      .cp-form-head {
        flex-direction: column;
        align-items: flex-start;
      }

      .cp-hero,
      .cp-note,
      .cp-auth-card,
      .cp-foot {
        border-radius: 22px;
      }
    }
  </style>
</head>
<body>
  <div class="cp-shell">
    <div class="cp-workspace">
      <header class="cp-masthead">
        <div class="cp-brand-lockup">
          <img src="<?= base_url($loginHeaderLogo) ?>" alt="UPNM Logo">
          <div class="cp-brand-copy">
            <strong><?= h($systemName !== '' ? $systemName : $siteTitle) ?></strong>
            <span><?= h($organizationName !== '' ? $organizationName : ($pageLang === 'en' ? 'Official digital access for facility operations and support services.' : 'Akses digital rasmi untuk operasi fasiliti dan perkhidmatan sokongan.')) ?></span>
          </div>
        </div>
        <div class="cp-masthead-actions">
          <div class="cp-version">
            <i class="ri-shield-check-line"></i>
            <span><?= h(app_current_version_label()) ?></span>
          </div>
          <a class="cp-home" href="<?= h(base_path('index.php')) ?>"><i class="ri-arrow-left-line"></i> <?= h(__('forgot_password_back_to_login')) ?></a>
        </div>
      </header>

      <div class="cp-board">
        <section class="cp-overview">
          <span class="cp-tag"><i class="ri-lock-password-line"></i> <?= h(__('password_change_kicker')) ?></span>
          <h1 class="cp-title"><?= h($pageLang === 'en' ? 'Forced password renewal within an institutional security control flow.' : 'Pembaruan kata laluan wajib dalam aliran kawalan keselamatan institusi.') ?></h1>
          <p class="cp-subtitle"><?= h(__($reasonTextKey)) ?></p>

          <div class="cp-overview-grid">
            <div class="cp-hero">
              <h2><?= h($pageLang === 'en' ? 'Restore compliant account access before continuing to the main workspace.' : 'Pulihkan akses akaun yang patuh sebelum meneruskan ke ruang kerja utama.') ?></h2>
              <p><?= h($pageLang === 'en' ? 'This enforced password update protects ongoing access by requiring a new password that meets current institutional policy.' : 'Kemaskini kata laluan wajib ini melindungi akses berterusan dengan mewajibkan kata laluan baharu yang mematuhi polisi institusi semasa.') ?></p>
            </div>

            <aside class="cp-note">
              <span><?= h($pageLang === 'en' ? 'Access Requirement' : 'Keperluan Akses') ?></span>
              <strong><?= h($pageLang === 'en' ? 'Completion of this step is required before the session can continue.' : 'Langkah ini perlu diselesaikan sebelum sesi boleh diteruskan.') ?></strong>
              <p><?= h($pageLang === 'en' ? 'Password policy, reuse protection, and audit controls remain active throughout this process.' : 'Polisi kata laluan, perlindungan guna semula, dan kawalan audit kekal aktif sepanjang proses ini.') ?></p>
            </aside>
          </div>
        </section>

        <aside class="cp-panel">
          <div class="cp-auth-card">
            <div class="cp-form-head">
              <div class="cp-panel-logo">
                <img src="<?= base_url($loginPanelLogo) ?>" alt="Portal Logo">
              </div>
              <div>
                <span class="cp-kicker"><i class="ri-lock-password-line"></i> <?= h(__('password_change_kicker')) ?></span>
                <h2 class="cp-panel-title"><?= h(__('password_change_heading')) ?></h2>
                <p class="cp-panel-copy"><?= h($pageLang === 'en' ? 'Set a compliant password before re-entering the official facility workspace.' : 'Tetapkan kata laluan yang patuh sebelum memasuki semula ruang kerja rasmi fasiliti.') ?></p>
              </div>
            </div>

            <div class="cp-meta">
              <strong><?= h(__('password_change_login_id_label')) ?>:</strong> <?= h($pendingLoginId) ?>
            </div>

            <?php if ($errors !== []): ?>
              <div class="cp-errors" role="alert">
                <ul>
                  <?php foreach ($errors as $error): ?>
                    <li><?= h($error) ?></li>
                  <?php endforeach; ?>
                </ul>
              </div>
            <?php endif; ?>

            <form method="POST" action="<?= h(base_path('change-password.php')) ?>" class="cp-form" autocomplete="off">
              <input type="hidden" name="csrf_token" value="<?= h((string)($_SESSION['csrf_token'] ?? '')) ?>">

              <div class="cp-field">
                <label for="new_password"><?= h(__('password_change_new_password_label')) ?></label>
                <div class="cp-password-wrap">
                  <input id="new_password" name="new_password" type="password" autocomplete="new-password" required>
                  <button type="button" class="cp-password-toggle" data-password-toggle="new_password" aria-label="Show password">
                    <svg viewBox="0 0 24 24" fill="none" aria-hidden="true">
                      <path d="M2 12C4.8 7.6 8.1 5.4 12 5.4C15.9 5.4 19.2 7.6 22 12C19.2 16.4 15.9 18.6 12 18.6C8.1 18.6 4.8 16.4 2 12Z" stroke="currentColor" stroke-width="1.7"/>
                      <circle cx="12" cy="12" r="3.2" stroke="currentColor" stroke-width="1.7"/>
                    </svg>
                  </button>
                </div>
              </div>

              <div class="cp-field">
                <label for="confirm_password"><?= h(__('password_change_confirm_password_label')) ?></label>
                <div class="cp-password-wrap">
                  <input id="confirm_password" name="confirm_password" type="password" autocomplete="new-password" required>
                  <button type="button" class="cp-password-toggle" data-password-toggle="confirm_password" aria-label="Show password confirmation">
                    <svg viewBox="0 0 24 24" fill="none" aria-hidden="true">
                      <path d="M2 12C4.8 7.6 8.1 5.4 12 5.4C15.9 5.4 19.2 7.6 22 12C19.2 16.4 15.9 18.6 12 18.6C8.1 18.6 4.8 16.4 2 12Z" stroke="currentColor" stroke-width="1.7"/>
                      <circle cx="12" cy="12" r="3.2" stroke="currentColor" stroke-width="1.7"/>
                    </svg>
                  </button>
                </div>
              </div>

              <div class="cp-rules" id="password-rules" data-login-id="<?= h($pendingLoginId) ?>">
                <div class="cp-rules-title"><?= h(__('password_change_live_title')) ?></div>
                <ul class="cp-rules-list">
                      <li class="cp-rule" data-rule="length"><span class="cp-rule-dot" aria-hidden="true">&bull;</span><span><?= h(change_password_min_length_message((int)($runtimePasswordPolicy['min_length'] ?? 8))) ?></span></li>
                  <?php if (!empty($runtimePasswordPolicy['require_uppercase'])): ?><li class="cp-rule" data-rule="uppercase"><span class="cp-rule-dot" aria-hidden="true">&bull;</span><span><?= h(__('password_change_error_uppercase')) ?></span></li><?php endif; ?>
                  <?php if (!empty($runtimePasswordPolicy['require_lowercase'])): ?><li class="cp-rule" data-rule="lowercase"><span class="cp-rule-dot" aria-hidden="true">&bull;</span><span><?= h(__('password_change_error_lowercase')) ?></span></li><?php endif; ?>
                  <?php if (!empty($runtimePasswordPolicy['require_number'])): ?><li class="cp-rule" data-rule="number"><span class="cp-rule-dot" aria-hidden="true">&bull;</span><span><?= h(__('password_change_error_number')) ?></span></li><?php endif; ?>
                  <?php if (!empty($runtimePasswordPolicy['require_symbol'])): ?><li class="cp-rule" data-rule="symbol"><span class="cp-rule-dot" aria-hidden="true">&bull;</span><span><?= h(__('password_change_error_symbol')) ?></span></li><?php endif; ?>
                  <?php if (!empty($runtimePasswordPolicy['block_loginid_variants'])): ?><li class="cp-rule" data-rule="no-login"><span class="cp-rule-dot" aria-hidden="true">&bull;</span><span><?= h(__('password_change_rule_no_login_id')) ?></span></li><?php endif; ?>
                  <li class="cp-rule" data-rule="match"><span class="cp-rule-dot" aria-hidden="true">&bull;</span><span><?= h(__('password_change_rule_confirm_match')) ?></span></li>
                </ul>
              </div>

              <button type="submit" class="cp-submit" id="cp-submit" disabled><?= h(__('password_change_submit_btn')) ?></button>
            </form>
          </div>

          <div class="cp-foot">
            <?= h(__('password_change_footer_note')) ?>
          </div>
        </aside>
      </div>
    </div>
  </div>

  <?php if (function_exists('render_alert')) render_alert(); ?>
  <script>
    (function () {
      var newPasswordEl = document.getElementById('new_password');
      var confirmPasswordEl = document.getElementById('confirm_password');
      var rulesRoot = document.getElementById('password-rules');
      if (!newPasswordEl || !confirmPasswordEl || !rulesRoot) {
        return;
      }

      var loginId = String((rulesRoot.getAttribute('data-login-id') || '')).trim().toLowerCase();
      var loginTokens = buildLoginTokens(loginId);
      var ruleEls = {
        length: rulesRoot.querySelector('[data-rule="length"]'),
        uppercase: rulesRoot.querySelector('[data-rule="uppercase"]'),
        lowercase: rulesRoot.querySelector('[data-rule="lowercase"]'),
        number: rulesRoot.querySelector('[data-rule="number"]'),
        symbol: rulesRoot.querySelector('[data-rule="symbol"]'),
        noLogin: rulesRoot.querySelector('[data-rule="no-login"]'),
        match: rulesRoot.querySelector('[data-rule="match"]'),
      };
      var submitBtn = document.getElementById('cp-submit');
      var requireUppercase = <?= !empty($runtimePasswordPolicy['require_uppercase']) ? 'true' : 'false' ?>;
      var requireLowercase = <?= !empty($runtimePasswordPolicy['require_lowercase']) ? 'true' : 'false' ?>;
      var requireNumber = <?= !empty($runtimePasswordPolicy['require_number']) ? 'true' : 'false' ?>;
      var requireSymbol = <?= !empty($runtimePasswordPolicy['require_symbol']) ? 'true' : 'false' ?>;
      var blockLoginVariants = <?= !empty($runtimePasswordPolicy['block_loginid_variants']) ? 'true' : 'false' ?>;

      function setRuleState(el, valid) {
        if (!el) return;
        el.classList.toggle('is-valid', Boolean(valid));
        var dot = el.querySelector('.cp-rule-dot');
        if (dot) {
          dot.innerHTML = valid ? '&#10003;' : '&bull;';
        }
      }

      function buildLoginTokens(rawLoginId) {
        var normalized = String(rawLoginId || '').trim().toLowerCase();
        if (!normalized) {
          return [];
        }

        var tokens = [normalized];
        var collapsed = normalized.replace(/[^a-z0-9]/g, '');
        if (collapsed) {
          tokens.push(collapsed);
          var trimmedCollapsed = collapsed.replace(/^0+/, '');
          if (trimmedCollapsed && trimmedCollapsed.length >= 3) {
            tokens.push(trimmedCollapsed);
          }
        }

        var parts = normalized.split(/[^a-z0-9]+/);
        for (var i = 0; i < parts.length; i += 1) {
          var part = String(parts[i] || '').trim().toLowerCase();
          if (part && part.length >= 4) {
            tokens.push(part);
            var trimmedPart = part.replace(/^0+/, '');
            if (trimmedPart && trimmedPart.length >= 3) {
              tokens.push(trimmedPart);
            }
          }
        }

        var seen = {};
        var unique = [];
        for (var j = 0; j < tokens.length; j += 1) {
          var token = tokens[j];
          if (!token || seen[token]) {
            continue;
          }
          seen[token] = true;
          unique.push(token);
        }

        unique.sort(function (a, b) {
          return b.length - a.length;
        });

        return unique;
      }

      function updateRules() {
        var password = String(newPasswordEl.value || '');
        var confirmPassword = String(confirmPasswordEl.value || '');
        var normalizedPassword = password.toLowerCase();

        var states = {
          length: password.length >= <?= (int)($runtimePasswordPolicy['min_length'] ?? 8) ?>,
          uppercase: !requireUppercase || /[A-Z]/.test(password),
          lowercase: !requireLowercase || /[a-z]/.test(password),
          number: !requireNumber || /\d/.test(password),
          symbol: !requireSymbol || /[^A-Za-z0-9]/.test(password),
          noLogin: !blockLoginVariants || (password.length > 0 ? loginTokens.every(function (token) {
            return normalizedPassword.indexOf(token) === -1;
          }) : false),
          match: password !== '' && confirmPassword !== '' && password === confirmPassword,
        };

        setRuleState(ruleEls.length, states.length);
        setRuleState(ruleEls.uppercase, states.uppercase);
        setRuleState(ruleEls.lowercase, states.lowercase);
        setRuleState(ruleEls.number, states.number);
        setRuleState(ruleEls.symbol, states.symbol);
        setRuleState(ruleEls.noLogin, states.noLogin);
        setRuleState(ruleEls.match, states.match);

        if (submitBtn) {
          var allValid =
            states.length &&
            states.uppercase &&
            states.lowercase &&
            states.number &&
            states.symbol &&
            states.noLogin &&
            states.match;
          submitBtn.disabled = !allValid;
        }
      }

      newPasswordEl.addEventListener('input', updateRules);
      confirmPasswordEl.addEventListener('input', updateRules);
      updateRules();

      var toggles = document.querySelectorAll('[data-password-toggle]');
      function revealInput(inputEl) {
        if (inputEl) inputEl.setAttribute('type', 'text');
      }
      function maskInput(inputEl) {
        if (inputEl) inputEl.setAttribute('type', 'password');
      }

      for (var k = 0; k < toggles.length; k += 1) {
        (function (toggleBtn) {
          var targetId = toggleBtn.getAttribute('data-password-toggle');
          var targetInput = targetId ? document.getElementById(targetId) : null;
          if (!targetInput) return;

          toggleBtn.addEventListener('mousedown', function () { revealInput(targetInput); });
          toggleBtn.addEventListener('mouseup', function () { maskInput(targetInput); });
          toggleBtn.addEventListener('mouseleave', function () { maskInput(targetInput); });
          toggleBtn.addEventListener('touchstart', function () { revealInput(targetInput); }, { passive: true });
          toggleBtn.addEventListener('touchend', function () { maskInput(targetInput); });
          toggleBtn.addEventListener('touchcancel', function () { maskInput(targetInput); });
          toggleBtn.addEventListener('blur', function () { maskInput(targetInput); });
        })(toggles[k]);
      }
    })();
  </script>
</body>
</html>
