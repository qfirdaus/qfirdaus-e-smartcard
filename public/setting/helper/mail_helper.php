<?php
/**
 * IQS FRAMEWORK CORE FILE
 *
 * READ ONLY for downstream project programmers.
 * Do not modify this file directly in template or cloned projects.
 * Custom changes must be implemented in project-specific files
 * or approved extension points.
 */// setting/helper/mail_helper.php
declare(strict_types=1);

// Naik 2 level dari setting/helper → /var/www/html
$ROOT = dirname(__DIR__, 2); // /var/www/html

require_once $ROOT . '/classes/Mailer.php';
require_once $ROOT . '/classes/Database.php';
require_once $ROOT . '/classes/EmailTemplateDeliveryService.php';
require_once $ROOT . '/setting/helper/config_helper.php';

/**
 * Hantar emel generic
 */
function mail_send(array|string $to, string $subject, string $html, ?string $text=null, array $opts=[]): bool {
    $pdo = Database::pdoMysql();
    return Mailer::quickSend($pdo, $to, $subject, $html, $text, $opts);
}

/**
 * Contoh helper khusus reminder LPPT
 */
function mail_send_reminder(string $to, string $role, string $stafNama, string $stafNopek, string $tahun, ?string $targetNama=null): bool {
    $mailActionUrl = (string)app_config('mail.default_action_url', '#');
    $mailSystemName = (string)app_config('mail.system_name', app_config('system.name', 'Base System'));
    $mailFooterNote = (string)app_config_localized('mail.footer_note', 'Emel ini dijana secara automatik. Sila jangan balas emel ini.');

    [$html, $text] = Mailer::render('reminder', [
        'role'       => $role,
        'targetNama' => $targetNama ?? $role,
        'stafNama'   => $stafNama,
        'stafNopek'  => $stafNopek,
        'tahun'      => $tahun,
        'actionUrl'  => $mailActionUrl,
        'systemName' => $mailSystemName,
        'footerNote' => $mailFooterNote,
    ]);
    $subject = "[LPPT {$tahun}] Peringatan penilaian untuk {$stafNama}" . ($stafNopek ? " ({$stafNopek})" : "");
    $pdo = Database::pdoMysql();
    return Mailer::quickSend($pdo, $to, $subject, $html, $text);
}

/**
 * Hantar emel berdasarkan template code aktif.
 *
 * @param array<string,mixed> $variables
 * @param array<string,mixed> $opts
 * @param array<string,mixed> $context
 */
function mail_send_template_by_code(string $templateCode, array|string $to, array $variables = [], array $opts = [], array $context = []): bool {
    $pdo = Database::pdoMysql();
    $service = new EmailTemplateDeliveryService($pdo);
    $service->sendByCode($templateCode, $to, $variables, $opts, $context);
    return true;
}

/**
 * Hantar emel berdasarkan template default bagi role + category.
 *
 * @param array<string,mixed> $variables
 * @param array<string,mixed> $opts
 * @param array<string,mixed> $context
 */
function mail_send_template_default(string $roleCode, string $categoryCode, array|string $to, array $variables = [], array $opts = [], array $context = []): bool {
    $pdo = Database::pdoMysql();
    $service = new EmailTemplateDeliveryService($pdo);
    $service->sendDefault($roleCode, $categoryCode, $to, $variables, $opts, $context);
    return true;
}
