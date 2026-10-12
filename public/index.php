<?php
/**
 * IQS FRAMEWORK CORE FILE
 *
 * READ ONLY for downstream project programmers.
 * Do not modify this file directly in template or cloned projects.
 * Custom changes must be implemented in project-specific files
 * or approved extension points.
 */$__ssoDebugLog = static function (string $message, array $context = []): void {
    $enabled = $_ENV['SSO_DEBUG_LOG_ENABLED'] ?? getenv('SSO_DEBUG_LOG_ENABLED');
    if (!is_string($enabled) || !in_array(strtolower(trim($enabled)), ['1', 'true', 'yes', 'on'], true)) {
        return;
    }

    $dir = __DIR__ . '/log';
    if (!is_dir($dir)) {
        @mkdir($dir, 0777, true);
    }
    $line = '[' . date('Y-m-d H:i:s') . '] ' . $message;
    if ($context !== []) {
        $line .= ' ' . json_encode($context, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }
    $line .= PHP_EOL;
    @file_put_contents($dir . '/sso-debug.log', $line, FILE_APPEND | LOCK_EX);
};

header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: strict-origin-when-cross-origin');
header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net; style-src 'self' 'unsafe-inline' https://fonts.googleapis.com; font-src 'self' https://fonts.gstatic.com data:; img-src 'self' data:; connect-src 'self'; object-src 'none'; base-uri 'self'; frame-ancestors 'self';");

$isHttps = isset($_SERVER['HTTPS']) && strtolower((string)$_SERVER['HTTPS']) !== 'off';
$hasIncomingSsoToken = isset($_GET['new_sso_cre']) && trim((string)$_GET['new_sso_cre']) !== '';
if (!$hasIncomingSsoToken && isset($_COOKIE['sso_cre'])) {
    setcookie('sso_cre', '', [
        'expires' => time() - 3600,
        'path' => '/',
        'secure' => $isHttps,
        'httponly' => false,
        'samesite' => 'Lax',
    ]);
}

$version = time();

// ✅ Include & Init
require_once __DIR__ . '/includes/init.php';
require_once __DIR__ . '/includes/functions-db.php';
require_once __DIR__ . '/includes/sso-config.php';
require_once __DIR__ . '/classes/Config.php';

$normalizeLocalAssetPath = static function ($value, string $fallback): string {
    $path = trim((string)$value);
    if ($path === '') {
        return $fallback;
    }

    if (preg_match('#^(?:[a-z][a-z0-9+\-.]*:)?//#i', $path) === 1) {
        return $fallback;
    }

    $path = ltrim(str_replace('\\', '/', $path), '/');
    if ($path === '' || str_contains($path, '..')) {
        return $fallback;
    }

    return $path;
};

$normalizeExternalUrl = static function ($value): string {
    $url = trim((string)$value);
    if ($url === '' || $url === '#') {
        return '';
    }

    if (!filter_var($url, FILTER_VALIDATE_URL)) {
        return '';
    }

    $scheme = strtolower((string)(parse_url($url, PHP_URL_SCHEME) ?? ''));
    return in_array($scheme, ['http', 'https'], true) ? $url : '';
};

$normalizeEmail = static function ($value): string {
    $email = trim((string)$value);
    return filter_var($email, FILTER_VALIDATE_EMAIL) ? $email : '';
};

$requestedMissingPage = function_exists('prestasi_requested_missing_page_from_uri')
    ? prestasi_requested_missing_page_from_uri()
    : '';

if ($requestedMissingPage !== '' && function_exists('set_alert')) {
    set_alert([
        'type' => 'sweet',
        'icon' => 'warning',
        'title' => 'access_notice_title',
        'text' => 'access_missing_page_text',
        'confirm' => true,
        'position' => 'center',
        'is_key' => true,
    ]);
}

if ($hasIncomingSsoToken) {
    $__ssoDebugLog('INDEX_CALLBACK_ENTRY', [
        'request_uri' => $_SERVER['REQUEST_URI'] ?? null,
        'session_id' => session_id(),
        'has_cookie' => isset($_COOKIE['sso_cre']),
    ]);
    redirect('sso_sp_client.php?new_sso_cre=' . rawurlencode((string)$_GET['new_sso_cre']));
}

$authPolicy = function_exists('get_auth_policy_config') ? get_auth_policy_config() : [];
$ssoEnabled = !empty($authPolicy['sso']['enabled']);
$ssoMode = strtoupper(trim((string)($authPolicy['sso']['mode'] ?? 'MANUAL')));
$ssoHybrid = is_array($authPolicy['sso']['hybrid'] ?? null) ? $authPolicy['sso']['hybrid'] : [];
$hasActiveSsoRoute = false;
if ($ssoEnabled) {
    if ($ssoMode === 'ALL') {
        $hasActiveSsoRoute = true;
    } elseif ($ssoMode === 'HYBRID') {
        foreach (['staf', 'pelajar', 'umum'] as $hybridCategory) {
            if (strtoupper(trim((string)($ssoHybrid[$hybridCategory] ?? 'MANUAL'))) === 'SSO') {
                $hasActiveSsoRoute = true;
                break;
            }
        }
    }
}
$ssoConfig = function_exists('sso_shared_config') ? sso_shared_config() : [
    'idp_host' => 'oneid.upnm.edu.my',
    'launcher_url' => 'https://oneid.upnm.edu.my/?site_id=V8LN57YMGZ',
];
$oneIdLauncherUrl = (string)($ssoConfig['launcher_url'] ?? '');
$oneIdLoginUrl = base_url('sso_sp_client.php');
$showOneIdButton = $hasActiveSsoRoute && $oneIdLoginUrl !== '';
$referer = trim((string)($_SERVER['HTTP_REFERER'] ?? ''));
$refererHost = strtolower((string)(parse_url($referer, PHP_URL_HOST) ?? ''));
$trustedSsoHost = strtolower(trim((string)($ssoConfig['idp_host'] ?? '')));
$isTrustedSsoReferer = $refererHost !== '' && $trustedSsoHost !== '' && $refererHost === $trustedSsoHost;

$loginManualCategories = [];
$authCategories = is_array($authPolicy['categories'] ?? null) ? $authPolicy['categories'] : [];
$isManualLoginEnabledForCategory = static function (string $category) use ($authCategories, $ssoEnabled, $ssoMode, $ssoHybrid): bool {
    if (empty($authCategories[$category])) {
        return false;
    }

    if (!$ssoEnabled) {
        return true;
    }

    if ($ssoMode === 'ALL') {
        return $category === 'umum';
    }

    if ($ssoMode === 'HYBRID') {
        return strtoupper(trim((string)($ssoHybrid[$category] ?? 'MANUAL'))) === 'MANUAL';
    }

    return true;
};

foreach (['staf', 'pelajar', 'umum'] as $loginCategory) {
    if ($isManualLoginEnabledForCategory($loginCategory)) {
        $loginManualCategories[] = $loginCategory;
    }
}

$loginPlaceholderParts = [];
foreach ($loginManualCategories as $loginCategory) {
    if ($loginCategory === 'staf') {
        $loginPlaceholderParts[] = (string)__('login_userid_placeholder_staff');
        continue;
    }
    if ($loginCategory === 'pelajar') {
        $loginPlaceholderParts[] = (string)__('login_userid_placeholder_student');
        continue;
    }
    if ($loginCategory === 'umum') {
        $loginPlaceholderParts[] = (string)__('login_userid_placeholder_public');
    }
}

$loginPlaceholderList = '';
$loginPlaceholderCount = count($loginPlaceholderParts);
if ($loginPlaceholderCount === 1) {
    $loginPlaceholderList = $loginPlaceholderParts[0];
} elseif ($loginPlaceholderCount === 2) {
    $loginPlaceholderList = $loginPlaceholderParts[0] . (string)__('login_userid_placeholder_joiner_last') . $loginPlaceholderParts[1];
} elseif ($loginPlaceholderCount > 2) {
    $lastPart = array_pop($loginPlaceholderParts);
    $loginPlaceholderList = implode((string)__('login_userid_placeholder_joiner'), $loginPlaceholderParts)
        . (string)__('login_userid_placeholder_joiner_last')
        . $lastPart;
}

$loginIdPlaceholder = $loginPlaceholderList !== ''
    ? sprintf((string)__('login_userid_placeholder_format'), $loginPlaceholderList)
    : (string)__('login_userid_placeholder_unavailable');

$hasPendingLoginAlert = !empty($_SESSION['alert']);
if (
    $showOneIdButton
    && $isTrustedSsoReferer
    && empty($_SESSION['f_loginID'])
    && !$hasPendingLoginAlert
    && ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET'
) {
    $__ssoDebugLog('INDEX_ONEID_REFERER_HANDOFF_RESTORED', [
        'referer' => $referer,
        'session_id' => session_id(),
        'target' => $oneIdLoginUrl,
    ]);
    redirect('sso_sp_client.php');
}

// ✅ Language Detection
$uri  = $_SERVER['REQUEST_URI'];
$configModel = class_exists('Database') ? new Config(Database::getInstance('mysql')->getConnection()) : null;
$activeLanguages = $configModel ? $configModel->getBahasaAktif() : ['ms', 'en'];
$defaultLanguage = $configModel ? ($configModel->getDefaultBahasa($activeLanguages[0] ?? 'ms') ?? 'ms') : 'ms';
$globalThemeSettings = $configModel ? ($configModel->getTema() ?: []) : [];
if (!in_array($defaultLanguage, $activeLanguages, true)) {
    $defaultLanguage = $activeLanguages[0] ?? 'ms';
}
$lang = $_SESSION['lang'] ?? $defaultLanguage;

if (isset($_GET['lang']) && in_array($_GET['lang'], $activeLanguages, true)) {
    if ($lang !== $_GET['lang']) {
        $_SESSION['lang'] = $_GET['lang'];
        header("Location: " . strtok($uri, '?'));
        exit;
    }
}
$lang = $_SESSION['lang'] ?? $defaultLanguage;

$loginLanguageOptions = array_values(array_intersect([$defaultLanguage, 'ms', 'en'], ['ms', 'en']));
$loginLanguageOptions = array_values(array_unique($loginLanguageOptions));
$remainingLanguageOptions = array_values(array_diff(['ms', 'en'], $loginLanguageOptions));
$loginLanguageOptions = array_merge($loginLanguageOptions, $remainingLanguageOptions);

// ✅ CSRF Token
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['csrf_token'];

// ✅ Redirect if already logged in
if (!empty($_SESSION['f_loginID'])) {
    redirect(app_config('site.default_home', 'pages/dashboard.php'));
}

// ✅ Optional login flags
$login_failed   = $login_failed ?? false;
$locked_seconds = $locked_seconds ?? 0;
$attempts_left  = $attempts_left ?? 3;

$defaultHome      = app_config('site.default_home', 'pages/dashboard.php');
$loginHeaderLogo  = $normalizeLocalAssetPath(app_config('branding.login_header_logo', 'assets/images/logo-upnm.png'), 'assets/images/logo-upnm.png');
$loginPanelLogo   = $normalizeLocalAssetPath(app_config('branding.login_panel_logo', 'assets/images/upnm30-logo.png'), 'assets/images/upnm30-logo.png');
$supportEmail     = $normalizeEmail(app_config('system.support', ''));
$systemName       = trim((string)app_config('system.name', 'IQS Framework'));
$organizationName = trim((string)app_config('organization.name', app_config('system.name', 'IQS Framework')));
$organizationWebsite = $normalizeExternalUrl(app_config('organization.website', ''));
$faviconPath = $normalizeLocalAssetPath(app_config('site.favicon', 'assets/images/default.ico'), 'assets/images/default.ico');
$loginBannerImages = ['banner1.jpg', 'banner2.jpg', 'banner3.jpg', 'banner4.jpg'];
require_once __DIR__ . '/includes/theme_palette_helper.php';
$activeThemeStyle = iqs_theme_resolve_public_palette($globalThemeSettings);
$contactNote = __('login_contact');
$contactParts = [];
if ($supportEmail !== '') {
    $contactParts[] = '<a href="mailto:' . htmlspecialchars($supportEmail, ENT_QUOTES, 'UTF-8') . '" class="text-blue-600 hover:underline">' . htmlspecialchars($supportEmail, ENT_QUOTES, 'UTF-8') . '</a>';
}
if ($organizationWebsite !== '' && $organizationWebsite !== '#') {
    $contactParts[] = '<a href="' . htmlspecialchars($organizationWebsite, ENT_QUOTES, 'UTF-8') . '" target="_blank" rel="noopener noreferrer" class="text-blue-600 hover:underline">' . htmlspecialchars($organizationWebsite, ENT_QUOTES, 'UTF-8') . '</a>';
}
?>
<!DOCTYPE html>
<html lang="<?= htmlspecialchars($lang) ?>">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= __('login_title') ?> | <?= htmlspecialchars(app_config('site.title', 'IQS Framework')) ?></title>
  <link rel="icon" href="<?= base_url($faviconPath) ?>" type="image/x-icon">

  <link rel="stylesheet" href="<?= base_url('assets/css/icons.min.css?v=' . $version) ?>">
  <link rel="stylesheet" href="<?= base_url('assets/css/app.min.css?v=' . $version) ?>">
  <link rel="stylesheet" href="<?= base_url('assets/css/output.css?v=' . $version) ?>">
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11?v=<?= $version ?>"></script>

  <style>
    :root {
      --facility-bg: linear-gradient(180deg, #f4f7fb 0%, #e9eff6 100%);
      --facility-card: #ffffff;
      --facility-card-soft: #f6f9fc;
      --facility-card-ink: #0f1e33;
      --facility-line: rgba(148, 163, 184, 0.24);
      --facility-line-strong: rgba(100, 116, 139, 0.22);
      --facility-text: #10213a;
      --facility-muted: #5b6b82;
      --facility-primary: <?= htmlspecialchars($activeThemeStyle['primary'], ENT_QUOTES, 'UTF-8') ?>;
      --facility-primary-strong: <?= htmlspecialchars($activeThemeStyle['primaryStrong'], ENT_QUOTES, 'UTF-8') ?>;
      --facility-accent: <?= htmlspecialchars($activeThemeStyle['accent'], ENT_QUOTES, 'UTF-8') ?>;
      --facility-primary-rgb: <?= htmlspecialchars($activeThemeStyle['primaryRgb'], ENT_QUOTES, 'UTF-8') ?>;
      --facility-accent-rgb: <?= htmlspecialchars($activeThemeStyle['accentRgb'], ENT_QUOTES, 'UTF-8') ?>;
      --facility-navy: #0c1b32;
      --facility-navy-soft: #12355f;
      --facility-shadow: 0 24px 60px rgba(15, 23, 42, 0.11);
      --facility-soft-shadow: 0 14px 30px rgba(15, 23, 42, 0.07);
      --facility-header-start: <?= htmlspecialchars($activeThemeStyle['start'], ENT_QUOTES, 'UTF-8') ?>;
      --facility-header-end: <?= htmlspecialchars($activeThemeStyle['end'], ENT_QUOTES, 'UTF-8') ?>;
    }

    body.facility-login-page {
      font-family: 'Plus Jakarta Sans', sans-serif;
      min-height: 100vh;
      margin: 0;
      background:
        radial-gradient(circle at top left, rgba(var(--facility-primary-rgb), 0.12), transparent 28%),
        radial-gradient(circle at 100% 0%, rgba(var(--facility-accent-rgb), 0.12), transparent 26%),
        var(--facility-bg);
      color: var(--facility-text);
      overflow-x: hidden;
    }

    .facility-auth-shell {
      min-height: 100vh;
      padding: 28px;
      position: relative;
    }

    .facility-auth-shell::before {
      content: "";
      position: absolute;
      inset: 0 0 auto 0;
      height: 300px;
      background:
        linear-gradient(115deg, rgba(8, 18, 34, 0.18), transparent 52%),
        linear-gradient(135deg, var(--facility-header-start), var(--facility-header-end));
      z-index: 0;
      border-bottom-left-radius: 18px;
      border-bottom-right-radius: 18px;
    }

    .facility-workspace {
      position: relative;
      z-index: 1;
      width: min(1360px, 100%);
      margin: 0 auto;
      display: flex;
      flex-direction: column;
      gap: 22px;
    }

    .facility-masthead {
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 20px;
      padding: 10px 4px 0;
      color: #ffffff;
    }

    .facility-brand-lockup {
      display: flex;
      align-items: center;
      gap: 18px;
      min-width: 0;
    }

    .facility-brand-mark {
      width: 156px;
      max-width: 100%;
      display: block;
      flex: 0 0 auto;
      filter: drop-shadow(0 12px 24px rgba(2, 6, 23, 0.18));
    }

    .facility-brand-meta {
      min-width: 0;
      display: flex;
      flex-direction: column;
      gap: 8px;
    }

    .facility-brand-meta strong {
      font-size: 14px;
      font-weight: 800;
      letter-spacing: 0.12em;
      text-transform: uppercase;
      color: rgba(255,255,255,0.9);
    }

    .facility-brand-meta span {
      font-size: 13px;
      line-height: 1.7;
      color: rgba(226, 232, 240, 0.88);
      max-width: 540px;
    }

    .facility-masthead-actions {
      display: flex;
      flex-wrap: wrap;
      justify-content: flex-end;
      gap: 12px;
      align-items: center;
    }

    .facility-version-chip,
    .facility-top-link,
    .facility-top-button {
      display: inline-flex;
      align-items: center;
      gap: 8px;
      min-height: 44px;
      padding: 0 16px;
      border-radius: 10px;
      border: 1px solid rgba(255,255,255,0.14);
      background: rgba(255,255,255,0.08);
      color: #ffffff;
      font-size: 11px;
      font-weight: 800;
      letter-spacing: 0.1em;
      text-transform: uppercase;
      backdrop-filter: blur(16px);
      transition: transform .18s ease, background-color .18s ease, border-color .18s ease;
    }

    .facility-top-link:hover,
    .facility-top-button:hover {
      background: rgba(255,255,255,0.14);
      border-color: rgba(255,255,255,0.24);
      transform: translateY(-1px);
    }

    .facility-auth-board {
      display: grid;
      grid-template-columns: minmax(0, 1.25fr) minmax(360px, 430px);
      gap: 24px;
      align-items: start;
    }

    .facility-overview-panel,
    .facility-auth-panel {
      border-radius: 18px;
      background: var(--facility-card);
      box-shadow: var(--facility-shadow);
      border: 1px solid rgba(255,255,255,0.78);
      overflow: hidden;
    }

    .facility-overview-panel {
      position: relative;
      display: flex;
      flex-direction: column;
      padding: 20px;
      gap: 18px;
      align-self: stretch;
    }

    .facility-overview-panel::before {
      content: "";
      position: absolute;
      top: 20px;
      left: 20px;
      right: 20px;
      height: 380px;
      background: linear-gradient(135deg, var(--facility-primary-strong), var(--facility-primary) 60%, var(--facility-accent));
      border-radius: 14px;
      pointer-events: none;
    }

    .facility-visual-panel {
      position: relative;
      z-index: 1;
      min-height: 380px;
      border-radius: 14px;
      overflow: hidden;
      background: linear-gradient(135deg, var(--facility-primary-strong), var(--facility-primary) 60%, var(--facility-accent));
      border: 1px solid rgba(255, 255, 255, 0.08);
    }

    .facility-visual-panel-inner {
      position: absolute;
      inset: 0;
      border-radius: 14px;
      overflow: hidden;
      background: transparent;
    }

    .facility-showcase-media,
    .facility-showcase-media::after {
      position: absolute;
      inset: 0;
      border-radius: 14px;
    }

    .facility-showcase-media {
      border-radius: 14px;
      overflow: hidden;
    }

    .facility-showcase-media::after {
      content: "";
      background:
        linear-gradient(90deg, rgba(12,27,50,0.8), rgba(12,27,50,0.42)),
        linear-gradient(180deg, rgba(var(--facility-primary-rgb), 0.2), rgba(var(--facility-accent-rgb), 0.34));
      z-index: 2;
      pointer-events: none;
    }

    .facility-showcase-media img {
      position: absolute;
      inset: 0;
      width: 100%;
      height: 100%;
      object-fit: cover;
      border-radius: 14px;
      will-change: opacity;
    }

    .facility-visual-content {
      position: relative;
      z-index: 2;
      min-height: 380px;
      border-radius: 14px;
      overflow: hidden;
      padding: 30px 30px 114px;
      display: flex;
      flex-direction: column;
      justify-content: space-between;
      color: #ffffff;
    }


    .facility-visual-top {
      display: flex;
      justify-content: space-between;
      gap: 12px;
      align-items: flex-start;
      flex-wrap: wrap;
    }

    .facility-system-chip {
      display: inline-flex;
      align-items: center;
      gap: 8px;
      padding: 11px 14px;
      border-radius: 8px;
      background: rgba(255,255,255,0.12);
      border: 1px solid rgba(255,255,255,0.16);
      backdrop-filter: blur(14px);
      font-size: 11px;
      font-weight: 800;
      letter-spacing: 0.1em;
      text-transform: uppercase;
    }

    .facility-visual-body {
      display: flex;
      align-items: flex-end;
    }

    .facility-visual-copy {
      display: flex;
      flex-direction: column;
      gap: 16px;
      max-width: 520px;
    }

    .facility-visual-copy h2 {
      margin: 0;
      font-size: clamp(28px, 3vw, 42px);
      line-height: 1.05;
      letter-spacing: -0.05em;
      font-weight: 800;
      color: #ffffff;
    }

    .facility-visual-copy p {
      margin: 0;
      font-size: 14px;
      line-height: 1.8;
      color: rgba(241, 245, 249, 0.9);
      max-width: 440px;
    }

    .facility-info-grid {
      position: relative;
      z-index: 1;
      display: grid;
      grid-template-columns: repeat(3, minmax(0, 1fr));
      gap: 16px;
      margin-top: 8px;
    }

    .facility-info-card {
      padding: 20px;
      border-radius: 12px;
      background: linear-gradient(145deg, rgba(var(--facility-primary-rgb), 0.055), rgba(var(--facility-accent-rgb), 0.025) 65%, #ffffff);
      border: 1px solid var(--facility-line);
      box-shadow: inset 0 1px 0 rgba(255,255,255,0.8);
    }

    .facility-info-card .facility-card-label {
      display: inline-flex;
      align-items: center;
      gap: 8px;
      margin-bottom: 12px;
      font-size: 11px;
      font-weight: 800;
      letter-spacing: 0.1em;
      text-transform: uppercase;
      color: var(--facility-primary-strong);
    }

    .facility-info-card h3 {
      margin: 0 0 8px;
      font-size: 18px;
      line-height: 1.4;
      color: var(--facility-card-ink);
    }

    .facility-info-card p {
      margin: 0;
      font-size: 13px;
      line-height: 1.75;
      color: var(--facility-muted);
    }

    .facility-info-card a {
      color: var(--facility-primary-strong);
      font-weight: 700;
      text-decoration: none;
    }

    .facility-info-card a:hover {
      text-decoration: underline;
    }

    .facility-auth-panel {
      padding: 20px;
      display: flex;
      flex-direction: column;
      gap: 22px;
      align-self: start;
      height: fit-content;
      background:
        linear-gradient(180deg, rgba(255,255,255,0.99), rgba(var(--facility-primary-rgb), 0.035));
      position: relative;
    }

    .facility-auth-panel--compact {
      align-self: start;
      background: #ffffff;
    }

    .facility-auth-card {
      padding: 24px;
      border-radius: 14px;
      background: rgba(255,255,255,0.92);
      border: 1px solid rgba(226, 232, 240, 0.92);
      display: flex;
      flex-direction: column;
      gap: 20px;
      overflow: hidden;
    }

    .facility-form-head {
      display: flex;
      align-items: flex-start;
      gap: 16px;
    }

    .facility-panel-logo {
      width: 68px;
      height: 68px;
      border-radius: 12px;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      background: linear-gradient(135deg, rgba(var(--facility-primary-rgb), 0.16), rgba(var(--facility-accent-rgb), 0.1));
      border: 1px solid rgba(var(--facility-primary-rgb), 0.12);
      flex: 0 0 auto;
    }

    .facility-panel-logo img {
      max-width: 46px;
      max-height: 46px;
    }

    .facility-eyebrow {
      display: inline-flex;
      align-items: center;
      gap: 8px;
      margin-bottom: 10px;
      color: var(--facility-primary-strong);
      font-size: 11px;
      font-weight: 800;
      letter-spacing: 0.14em;
      text-transform: uppercase;
    }

    .facility-form-title {
      margin: 0 0 8px;
      font-size: 30px;
      line-height: 1.04;
      letter-spacing: -0.04em;
      font-weight: 800;
      color: var(--facility-card-ink);
    }

    .facility-form-subcopy {
      margin: 0;
      font-size: 13px;
      line-height: 1.75;
      color: var(--facility-muted);
    }

    .facility-auth-form {
      display: flex;
      flex-direction: column;
      gap: 16px;
    }

    .facility-field {
      display: flex;
      flex-direction: column;
      gap: 8px;
    }

    .facility-field label {
      font-size: 11px;
      font-weight: 800;
      color: #334155;
      letter-spacing: 0.12em;
      text-transform: uppercase;
    }

    .facility-field-control {
      position: relative;
    }

    .facility-field-control i {
      position: absolute;
      left: 16px;
      top: 50%;
      transform: translateY(-50%);
      color: #64748b;
      font-size: 16px;
      pointer-events: none;
    }

    .facility-field-control input {
      width: 100%;
      border-radius: 10px;
      border: 1px solid rgba(148, 163, 184, 0.2);
      background: rgba(var(--facility-primary-rgb), 0.035);
      padding: 15px 16px 15px 46px;
      font-size: 14px;
      color: #0f172a;
      transition: border-color .18s ease, box-shadow .18s ease, background-color .18s ease;
    }

    .facility-field-control input:focus {
      border-color: rgba(var(--facility-primary-rgb), 0.4);
      background: #ffffff;
      box-shadow: 0 0 0 4px rgba(var(--facility-primary-rgb), 0.12);
      outline: none;
    }

    .facility-form-meta {
      display: flex;
      justify-content: space-between;
      align-items: center;
      gap: 12px;
      font-size: 12px;
      color: var(--facility-muted);
    }

    .facility-form-meta a {
      color: var(--facility-primary-strong);
      font-weight: 700;
      text-decoration: none;
    }

    .facility-form-meta a:hover {
      text-decoration: underline;
    }

    .facility-submit-btn {
      border: 0;
      border-radius: 10px;
      background: linear-gradient(135deg, var(--facility-primary-strong), var(--facility-primary) 58%, var(--facility-accent));
      color: #ffffff;
      font-size: 13px;
      font-weight: 800;
      letter-spacing: 0.12em;
      text-transform: uppercase;
      padding: 16px 18px;
      box-shadow: 0 18px 36px rgba(var(--facility-primary-rgb), 0.22);
      transition: transform .18s ease, box-shadow .18s ease;
    }

    .facility-submit-btn:hover {
      transform: translateY(-1px);
      box-shadow: 0 20px 40px rgba(var(--facility-primary-rgb), 0.28);
    }

    .facility-oneid-block {
      display: flex;
      flex-direction: column;
      gap: 12px;
      padding-top: 4px;
      border-top: 1px dashed rgba(148, 163, 184, 0.28);
    }

    .facility-oneid-label {
      color: var(--facility-muted);
      font-size: 11px;
      font-weight: 800;
      letter-spacing: 0.12em;
      text-transform: uppercase;
    }

    .facility-oneid-btn {
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 14px;
      border-radius: 10px;
      padding: 15px 16px;
      background: linear-gradient(135deg, rgba(var(--facility-primary-rgb), 0.08), rgba(var(--facility-accent-rgb), 0.07));
      border: 1px solid rgba(var(--facility-primary-rgb), 0.16);
      color: #0f172a;
      box-shadow: 0 12px 26px rgba(15, 23, 42, 0.07);
      transition: transform .18s ease, box-shadow .18s ease, border-color .18s ease;
    }

    .facility-oneid-btn:hover {
      transform: translateY(-1px);
      box-shadow: 0 18px 32px rgba(15, 23, 42, 0.1);
      border-color: rgba(var(--facility-primary-rgb), 0.24);
    }

    .facility-oneid-main {
      display: flex;
      align-items: center;
      gap: 12px;
    }

    .facility-oneid-badge {
      width: 42px;
      height: 42px;
      border-radius: 9px;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      background: linear-gradient(135deg, var(--facility-primary), var(--facility-accent));
      color: #fff;
      font-size: 12px;
      font-weight: 800;
      letter-spacing: 0.08em;
      box-shadow: 0 10px 20px rgba(var(--facility-primary-rgb), 0.18);
    }

    .facility-oneid-copy strong,
    .facility-oneid-copy span {
      display: block;
    }

    .facility-oneid-copy strong {
      font-size: 13px;
      font-weight: 800;
      color: #0f172a;
    }

    .facility-oneid-copy span {
      margin-top: 4px;
      color: #64748b;
      font-size: 11px;
      line-height: 1.55;
    }

    .facility-support-panel {
      padding: 20px 22px;
      border-radius: 12px;
      background: linear-gradient(135deg, rgba(var(--facility-primary-rgb), 0.07), rgba(var(--facility-accent-rgb), 0.035));
      border: 1px solid var(--facility-line);
      display: flex;
      flex-direction: column;
      gap: 12px;
    }

    .facility-support-panel h3 {
      margin: 0;
      font-size: 13px;
      font-weight: 800;
      letter-spacing: 0.1em;
      text-transform: uppercase;
      color: var(--facility-primary-strong);
    }

    .facility-support-panel p {
      margin: 0;
      font-size: 13px;
      line-height: 1.75;
      color: var(--facility-muted);
    }

    .facility-support-links {
      display: flex;
      flex-wrap: wrap;
      gap: 10px;
    }

    .facility-support-links a {
      display: inline-flex;
      align-items: center;
      gap: 8px;
      min-height: 42px;
      padding: 0 14px;
      border-radius: 9px;
      background: #ffffff;
      border: 1px solid rgba(148, 163, 184, 0.22);
      color: var(--facility-card-ink);
      font-size: 12px;
      font-weight: 700;
      text-decoration: none;
    }

    .facility-support-links a:hover {
      border-color: rgba(var(--facility-primary-rgb), 0.24);
    }

    @media (max-width: 1200px) {
      .facility-auth-board {
        grid-template-columns: minmax(0, 1fr) minmax(340px, 400px);
      }

      .facility-visual-body {
        grid-template-columns: 1fr;
      }

      .facility-info-grid {
        grid-template-columns: 1fr;
      }
    }

    @media (max-width: 1024px) {
      .facility-auth-shell {
        padding: 18px;
      }

      .facility-masthead,
      .facility-auth-board,
      .facility-visual-body {
        grid-template-columns: 1fr;
      }

      .facility-masthead {
        flex-direction: column;
        align-items: flex-start;
      }

      .facility-masthead-actions {
        justify-content: flex-start;
      }

      .facility-auth-board {
        display: grid;
      }
    }

    @media (max-width: 768px) {
      .facility-auth-shell {
        padding: 0;
      }

      .facility-auth-shell::before {
        border-radius: 0;
        height: 260px;
      }

      .facility-workspace {
        gap: 16px;
      }

      .facility-masthead,
      .facility-overview-panel,
      .facility-auth-panel {
        border-radius: 0;
      }

      .facility-masthead,
      .facility-overview-panel,
      .facility-auth-panel,
      .facility-visual-content {
        padding-left: 18px;
        padding-right: 18px;
      }

      .facility-brand-lockup,
      .facility-form-head,
      .facility-form-meta {
        flex-direction: column;
        align-items: flex-start;
      }

      .facility-brand-mark {
        width: 132px;
      }

      .facility-overview-panel,
      .facility-auth-panel {
        padding-top: 22px;
        padding-bottom: 22px;
      }

      .facility-auth-card,
      .facility-visual-panel,
      .facility-overview-sidecard,
      .facility-info-card,
      .facility-support-panel {
        border-radius: 12px;
      }

      .facility-info-grid,
      .facility-visual-body {
        grid-template-columns: 1fr;
      }
    }

  </style>
</head>
<body class="authentication-bg facility-login-page">
<div class="facility-auth-shell">
  <div class="facility-workspace">
    <header class="facility-masthead">
      <div class="facility-brand-lockup">
        <img class="facility-brand-mark" src="<?= base_url($loginHeaderLogo) ?>" alt="UPNM Logo">
        <div class="facility-brand-meta">
          <strong><?= htmlspecialchars($systemName !== '' ? $systemName : 'IQS Framework', ENT_QUOTES, 'UTF-8') ?></strong>
          <span><?= htmlspecialchars($organizationName !== '' ? $organizationName : ($lang === 'en' ? 'Reusable core platform for building future systems with the same governance, security, and application foundation.' : 'Platform teras boleh guna semula untuk membina sistem-sistem akan datang dengan tadbir urus, keselamatan, dan asas aplikasi yang sama.'), ENT_QUOTES, 'UTF-8') ?></span>
        </div>
      </div>

      <div class="facility-masthead-actions">
        <div class="facility-version-chip">
          <i class="ri-shield-check-line"></i>
          <span><?= htmlspecialchars(app_current_version_label(), ENT_QUOTES, 'UTF-8') ?></span>
        </div>
        <a class="facility-top-link" href="<?= h(base_url('index.php')) ?>"><i class="ri-home-5-line"></i> <?= __('login_nav.home') ?></a>
        <a class="facility-top-link" href="<?= h(base_url('pages/soalan-lazim.php')) ?>"><i class="ri-question-line"></i> <?= __('login_nav.faq') ?></a>
        <a class="facility-top-link" href="https://directory.upnm.edu.my" target="_blank" rel="noopener noreferrer"><i class="ri-building-line"></i> <?= __('login_nav.directory') ?></a>
      </div>
    </header>

    <div class="facility-auth-board">
      <section class="facility-overview-panel">
        <div class="facility-visual-panel">
          <div class="facility-visual-panel-inner">
            <div class="facility-showcase-media">
              <?php foreach ($loginBannerImages as $bannerIndex => $bannerImage): ?>
                <img
                  src="<?= h(base_url('assets/images/' . $bannerImage)) ?>"
                  alt="Banner"
                  class="transition-opacity duration-700 ease-in-out<?= $bannerIndex === 0 ? ' opacity-100' : ' opacity-0' ?>"
                  data-login-banner="<?= h((string)$bannerIndex) ?>">
              <?php endforeach; ?>
            </div>

            <div class="facility-visual-content">
              <div class="facility-visual-body">
                <div class="facility-visual-copy">
                  <h2><?= htmlspecialchars($lang === 'en' ? 'One core framework for consistent governance across every future system.' : 'Satu framework teras untuk tadbir urus yang konsisten merentas setiap sistem akan datang.', ENT_QUOTES, 'UTF-8') ?></h2>
                  <p><?= htmlspecialchars($lang === 'en' ? 'IQS Framework is developed as the shared base so teams can move straight into business features while common capabilities stay standardized.' : 'IQS Framework dibangunkan sebagai asas bersama supaya pasukan boleh terus fokus pada ciri kerja sebenar sementara keupayaan umum kekal standard.', ENT_QUOTES, 'UTF-8') ?></p>
                </div>
              </div>
            </div>
          </div>
        </div>

        <div class="facility-info-grid">
          <article class="facility-info-card">
            <span class="facility-card-label"><i class="ri-layout-masonry-line"></i> <?= $lang === 'en' ? 'Platform' : 'Platform' ?></span>
            <h3><?= htmlspecialchars($systemName !== '' ? $systemName : 'IQS Framework', ENT_QUOTES, 'UTF-8') ?></h3>
            <p><?= htmlspecialchars($lang === 'en' ? 'Built as the core project layer that provides shared modules, conventions, and reusable flows for major application development.' : 'Dibina sebagai lapisan projek teras yang menyediakan modul bersama, konvensyen, dan aliran boleh guna semula untuk pembangunan aplikasi utama.', ENT_QUOTES, 'UTF-8') ?></p>
          </article>

          <article class="facility-info-card">
            <span class="facility-card-label"><i class="ri-customer-service-2-line"></i> <?= __('login_contact_title') ?></span>
            <h3><?= htmlspecialchars($lang === 'en' ? 'Support and framework reference' : 'Sokongan dan rujukan framework', ENT_QUOTES, 'UTF-8') ?></h3>
            <p>
              <?php if (!empty($contactParts)): ?>
                <?= implode('<br>', $contactParts) ?>
              <?php else: ?>
                <?= htmlspecialchars($contactNote, ENT_QUOTES, 'UTF-8') ?>
              <?php endif; ?>
            </p>
          </article>

          <article class="facility-info-card">
            <span class="facility-card-label"><i class="ri-shield-keyhole-line"></i> <?= $lang === 'en' ? 'Security' : 'Keselamatan' ?></span>
            <h3><?= htmlspecialchars($lang === 'en' ? 'Shared security foundation' : 'Asas keselamatan bersama', ENT_QUOTES, 'UTF-8') ?></h3>
            <p><?= htmlspecialchars($lang === 'en' ? 'Authentication, session protection, CSRF handling, and identity routing stay centralized here so future systems inherit the same trusted core.' : 'Autentikasi, perlindungan sesi, pengendalian CSRF, dan routing identiti dipusatkan di sini supaya sistem akan datang mewarisi teras yang sama dan dipercayai.', ENT_QUOTES, 'UTF-8') ?></p>
          </article>
        </div>
      </section>

      <aside class="facility-auth-panel<?= $showOneIdButton ? ' facility-auth-panel--compact' : '' ?>">
        <div class="facility-auth-card">
          <div class="facility-form-head">
            <div class="facility-panel-logo">
              <img src="<?= base_url($loginPanelLogo) ?>" alt="Logo">
            </div>
            <div>
              <span class="facility-eyebrow"><i class="ri-login-circle-line"></i> <?= __('login_heading') ?></span>
              <h2 class="facility-form-title"><?= __('login_title') ?></h2>
              <p class="facility-form-subcopy"><?= htmlspecialchars($lang === 'en' ? 'Enter your credentials to access the IQS Framework core workspace and continue to shared platform services.' : 'Masukkan kelayakan anda untuk mengakses ruang kerja teras IQS Framework dan meneruskan ke perkhidmatan platform bersama.', ENT_QUOTES, 'UTF-8') ?></p>
            </div>
          </div>

          <form method="POST" action="<?= base_url('login.php') ?>" autocomplete="off" class="facility-auth-form">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">

            <div class="facility-field">
              <label for="f_loginID"><?= h((__('login_userid_label') ?: 'Login ID')) ?></label>
              <div class="facility-field-control">
                <i class="ri-user-3-line"></i>
                <input id="f_loginID" name="f_loginID" type="text" required
                       placeholder="<?= htmlspecialchars($loginIdPlaceholder, ENT_QUOTES, 'UTF-8') ?>"
                       autocomplete="username">
              </div>
            </div>

            <div class="facility-field">
              <label for="f_password"><?= __('login_password') ?></label>
              <div class="facility-field-control">
                <i class="ri-lock-password-line"></i>
                <input id="f_password" name="f_password" type="password" required
                       placeholder="******"
                       autocomplete="current-password">
              </div>
            </div>

            <div class="facility-form-meta">
              <span><?= htmlspecialchars($lang === 'en' ? 'Protected access session' : 'Sesi akses dilindungi', ENT_QUOTES, 'UTF-8') ?></span>
              <a href="<?= h(base_url('forgot-password.php')) ?>"><?= __('login_forgot') ?></a>
            </div>

            <button type="submit" class="facility-submit-btn">
              <?= __('login_btnLogin') ?>
            </button>
          </form>

          <?php if ($showOneIdButton): ?>
            <div class="facility-oneid-block">
              <span class="facility-oneid-label"><?= htmlspecialchars($lang === 'en' ? 'Alternative Access' : 'Akses Alternatif', ENT_QUOTES, 'UTF-8') ?></span>
              <a href="<?= h($oneIdLoginUrl) ?>" class="facility-oneid-btn">
                <span class="facility-oneid-main">
                  <span class="facility-oneid-badge">ID</span>
                  <span class="facility-oneid-copy">
                    <strong><?= h(__('login_btnOneId') ?: 'OneID Login') ?></strong>
                    <span><?= htmlspecialchars($lang === 'en' ? 'Use OneID UPNM for SSO authentication' : 'Gunakan OneID UPNM untuk pengesahan SSO.', ENT_QUOTES, 'UTF-8') ?></span>
                  </span>
                </span>
                <i class="ri-arrow-right-up-line"></i>
              </a>
            </div>
          <?php endif; ?>
        </div>

        <?php if (!$showOneIdButton): ?>
          <div class="facility-support-panel">
            <h3><?= htmlspecialchars($lang === 'en' ? 'Core platform support channels' : 'Saluran sokongan platform teras', ENT_QUOTES, 'UTF-8') ?></h3>
            <p><?= htmlspecialchars($lang === 'en' ? 'Use the channels below if you need help with framework access, shared modules, or core account services used by connected systems.' : 'Gunakan saluran di bawah jika anda memerlukan bantuan berkaitan akses framework, modul bersama, atau servis akaun teras yang digunakan oleh sistem-sistem berkaitan.', ENT_QUOTES, 'UTF-8') ?></p>
            <div class="facility-support-links">
              <?php if ($supportEmail !== ''): ?>
                <a href="mailto:<?= htmlspecialchars($supportEmail, ENT_QUOTES, 'UTF-8') ?>"><i class="ri-mail-line"></i> <?= htmlspecialchars($supportEmail, ENT_QUOTES, 'UTF-8') ?></a>
              <?php endif; ?>
            </div>
          </div>
        <?php endif; ?>

      </aside>
    </div>
  </div>
</div>

<!-- ✅ Alert rendering -->
<?php if (function_exists('render_alert')) render_alert(); ?>

<script>
document.addEventListener('DOMContentLoaded', function () {
  const banners = Array.from(document.querySelectorAll('[data-login-banner]'));

  if (banners.length < 2) {
    return;
  }

  let activeIndex = 0;
  window.setInterval(function () {
    banners[activeIndex].classList.remove('opacity-100');
    banners[activeIndex].classList.add('opacity-0');
    activeIndex = (activeIndex + 1) % banners.length;
    banners[activeIndex].classList.remove('opacity-0');
    banners[activeIndex].classList.add('opacity-100');
  }, 5200);
});
</script>

</body>
</html>
