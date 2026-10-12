<?php
/**
 * IQS FRAMEWORK CORE FILE
 *
 * READ ONLY for downstream project programmers.
 * Do not modify this file directly in template or cloned projects.
 * Custom changes must be implemented in project-specific files
 * or approved extension points.
 */require_once __DIR__ . '/../includes/init.php';
header('Content-Type: application/json');

if (!function_exists('tetapan_uji_emel_debug')) {
    function tetapan_uji_emel_debug(string $stage, array $context = []): void
    {
        if (function_exists('tetapan_sistem_ajax_debug_log')) {
            tetapan_sistem_ajax_debug_log('uji_emel_' . $stage, $context);
            return;
        }

        $enabled = $_ENV['TETAPAN_AJAX_DEBUG_LOG_ENABLED'] ?? getenv('TETAPAN_AJAX_DEBUG_LOG_ENABLED');
        if (!is_string($enabled) || !in_array(strtolower(trim($enabled)), ['1', 'true', 'yes', 'on'], true)) {
            return;
        }

        $logPath = __DIR__ . '/../log/tetapan-sistem-ajax-debug.log';
        $payload = array_merge([
            'timestamp' => date('c'),
            'stage' => 'uji_emel_' . $stage,
            'uri' => (string)($_SERVER['REQUEST_URI'] ?? ''),
            'method' => (string)($_SERVER['REQUEST_METHOD'] ?? ''),
            'session_id' => session_id(),
            'login_id' => (string)($_SESSION['f_loginID'] ?? $_SESSION['user']['f_loginID'] ?? ''),
        ], $context);
        @file_put_contents($logPath, json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL, FILE_APPEND | LOCK_EX);
    }
}

tetapan_uji_emel_debug('entry');

// ================= Authentication Check =================
require_login();
tetapan_uji_emel_debug('after_require_login');

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'message' => (string)__('config_method_not_allowed'),
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// ================= Authorization Check =================
require_once __DIR__ . '/../classes/Database.php';
require_once __DIR__ . '/../classes/User.php';
require_once __DIR__ . '/../classes/Mailer.php';
require_once __DIR__ . '/../setting/constants/prestasi_constants.php';
$pdo_mysql = Database::getInstance('mysql')->getConnection();
$userModel = new User($pdo_mysql);
$f_stafID = $_SESSION['f_stafID'] ?? null;
$profile = $f_stafID ? $userModel->getProfile($f_stafID) : [];
$userGroupId = (int)($profile['f_groupID'] ?? 0);

if ($userGroupId !== PRESTASI_ROLE_ID_ADM_SA) {
    tetapan_uji_emel_debug('authorization_denied', [
        'group_id' => $userGroupId,
    ]);
    http_response_code(403);
    echo json_encode([
        'success' => false,
        'message' => (string)__('config_super_admin_only_text')
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// ================= CSRF Protection =================
$csrfHeader = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? $_POST['csrf_token'] ?? '';
$sessionToken = $_SESSION['csrf_token'] ?? '';

if (empty($csrfHeader) || empty($sessionToken) || !hash_equals($sessionToken, $csrfHeader)) {
    tetapan_uji_emel_debug('csrf_invalid', [
        'has_header' => $csrfHeader !== '',
        'has_session_token' => $sessionToken !== '',
    ]);
    http_response_code(419);
    echo json_encode([
        'success' => false,
        'message' => (string)__('config_csrf_invalid_text')
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// ================= Rate Limiting =================
require_once __DIR__ . '/_helpers.php';
if (!checkRateLimit('test_email', 5, 60)) {
    tetapan_uji_emel_debug('rate_limited');
    http_response_code(429);
    echo json_encode([
        'success' => false,
        'message' => (string)__('config_rate_limit_text')
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

require_once __DIR__ . '/../assets/vendor/PHPMailer/src/PHPMailer.php';
require_once __DIR__ . '/../assets/vendor/PHPMailer/src/SMTP.php';
require_once __DIR__ . '/../assets/vendor/PHPMailer/src/Exception.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

if (!function_exists('app_config')) {
    require_once __DIR__ . '/../setting/helper/config_helper.php';
}

// Ambil nilai dari POST
$driver     = $_POST['mail_driver'] ?? '';
$host       = $_POST['mail_host'] ?? '';
$port       = $_POST['mail_port'] ?? '';

$username   = $_POST['mail_username'] ?? '';
$password   = trim($_POST['mail_password'] ?? '');

$encryption = $_POST['mail_encryption'] ?? '';
$fromAddr   = $_POST['mail_from_address'] ?? '';
$fromName   = $_POST['mail_from_name'] ?? '';
$to         = $_POST['uji_email'] ?? $username;

if (!filter_var((string)$to, FILTER_VALIDATE_EMAIL)) {
    http_response_code(422);
    echo json_encode([
        'success' => false,
        'message' => (string)__('config_js_valid_email_full'),
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// Jika password kosong, ambil dari existing settings
if ($password === '') {
    require_once __DIR__ . '/../classes/Config.php';
    $configModel = new Config($pdo_mysql);
    $existingSettings = $configModel->getGroup('email');
    $password = $existingSettings['mail_password'] ?? '';
}

$mail = new PHPMailer(true);

try {
    tetapan_uji_emel_debug('mailer_attempt', [
        'mail_host' => (string)$host,
        'mail_port' => (string)$port,
        'mail_username' => (string)$username,
        'target_email' => (string)$to,
    ]);
    $siteTitle = trim((string)app_config('site.title', app_config('system.name', 'Base System')));
    $systemName = trim((string)app_config('system.name', $siteTitle));
    $organizationName = trim((string)app_config('organization.name', ''));
    $supportEmail = trim((string)app_config('system.support', ''));
    $footerNote = trim((string)app_config('mail.footer_note', ''));
    $senderDisplayName = trim((string)$fromName) !== '' ? trim((string)$fromName) : $systemName;
    $mailHostDisplay = trim((string)$host) !== '' ? trim((string)$host) : '-';
    $mailPortDisplay = trim((string)$port) !== '' ? trim((string)$port) : '-';
    $mailEncryptionDisplay = trim((string)$encryption) !== '' ? strtoupper(trim((string)$encryption)) : 'AUTO';
    $testedAt = date('d/m/Y H:i:s');

    $logoPath = trim((string)app_config('branding.topbar_logo_dark', app_config('branding.topbar_logo_light', '')));
    $logoUrl = '';
    if ($logoPath !== '') {
        $base = rtrim((string)base_url(), '/');
        $logoUrl = preg_match('~^https?://~i', $logoPath) ? $logoPath : ($base . '/' . ltrim($logoPath, '/'));
    }
    $referenceCode = 'MAIL-TEST-' . strtoupper(substr(sha1($to . '|' . $testedAt), 0, 8));
    [$mailBodyHtml, $mailBodyText] = Mailer::render('test-connection', [
        'subject' => sprintf('Ujian Sambungan Emel | %s', $siteTitle !== '' ? $siteTitle : 'System Mail Test'),
        'siteTitle' => $siteTitle,
        'systemName' => $systemName !== '' ? $systemName : $siteTitle,
        'organizationName' => $organizationName,
        'supportEmail' => $supportEmail,
        'footerNote' => $footerNote,
        'senderDisplayName' => $senderDisplayName,
        'fromAddr' => $fromAddr,
        'to' => $to,
        'mailHostDisplay' => $mailHostDisplay,
        'mailPortDisplay' => $mailPortDisplay,
        'mailEncryptionDisplay' => $mailEncryptionDisplay,
        'testedAt' => $testedAt,
        'logoUrl' => $logoUrl,
        'referenceCode' => $referenceCode,
    ]);

    $mail->isSMTP();
    $mail->Host       = $host;
    $mail->SMTPAuth   = true;
    $mail->Username   = $username;
    $mail->Password   = $password;
    $mail->Port       = (int)$port;
    $mail->SMTPSecure = $encryption ?: PHPMailer::ENCRYPTION_STARTTLS;

    $mail->setFrom($fromAddr, $fromName);
    $mail->addAddress($to);
    $mail->isHTML(true);
    $mail->Subject = sprintf('Ujian Sambungan Emel | %s', $siteTitle !== '' ? $siteTitle : 'System Mail Test');
    $mail->Body    = $mailBodyHtml;
    $mail->AltBody = $mailBodyText;

    $mail->send();
    
    // Get translation for success message (lang_helper.php should be loaded via init.php)
    if (!function_exists('__')) {
        require_once __DIR__ . '/../setting/helper/lang_helper.php';
    }
    
    $successKey = 'config_js_emel_uji_berjaya';
    $successMsg = __($successKey);
    
    // If translation not found, use default
    if ($successMsg === $successKey) {
        $successMsg = 'Emel ujian berjaya dihantar ke <strong>' . htmlspecialchars((string)$to, ENT_QUOTES, 'UTF-8') . '</strong>.';
    } else {
        // Replace placeholder
        $successMsg = str_replace(':email', '<strong>' . htmlspecialchars((string)$to, ENT_QUOTES, 'UTF-8') . '</strong>', $successMsg);
    }
    
    echo json_encode([
        'success' => true,
        'message' => $successMsg
    ], JSON_UNESCAPED_UNICODE);
    tetapan_uji_emel_debug('success');
} catch (Exception $e) {
    if (class_exists(FrameworkExceptionHandler::class) && class_exists(Mailer::class)) {
        FrameworkExceptionHandler::log(
            Mailer::externalServiceExceptionForSmtpFailure(
                $e->getMessage(),
                'smtp://' . trim((string)$host) . ((int)$port > 0 ? ':' . (int)$port : ''),
                $e
            ),
            ['endpoint' => 'uji-emel']
        );
    }
    tetapan_uji_emel_debug('mailer_exception', [
        'error' => $e->getMessage(),
    ]);
    // Get translation for error message (lang_helper.php should be loaded via init.php)
    if (!function_exists('__')) {
        require_once __DIR__ . '/../setting/helper/lang_helper.php';
    }
    
    $errorKey = 'config_js_emel_uji_gagal';
    $errorTemplate = __($errorKey);
    
    // If translation not found, use default
    if ($errorTemplate === $errorKey) {
        $errorTemplate = "❌ Gagal hantar emel: :error";
    }
    
    $errorMsg = str_replace(':error', (string)__('config_js_emel_uji_error_safe'), $errorTemplate);
    http_response_code(502);
    echo json_encode([
        'success' => false,
        'message' => $errorMsg
    ], JSON_UNESCAPED_UNICODE);
}
