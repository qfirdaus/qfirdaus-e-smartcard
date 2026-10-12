<?php
/**
 * IQS FRAMEWORK CORE FILE
 *
 * READ ONLY for downstream project programmers.
 * Do not modify this file directly in template or cloned projects.
 * Custom changes must be implemented in project-specific files
 * or approved extension points.
 */declare(strict_types=1);

ob_start();
header('Content-Type: application/json; charset=utf-8');

try {
    require_once __DIR__ . '/../includes/init.php';
    require_login();
    require_once __DIR__ . '/_helpers.php';
    require_once __DIR__ . '/../classes/Database.php';
    require_once __DIR__ . '/../classes/NotificationService.php';

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        jsonErrorResponse((string)(__('notification_invalid_method') ?: 'Kaedah permintaan tidak sah.'), 405);
    }

    if (!isValidCsrfToken()) {
        jsonErrorResponse((string)(__('userGroup_csrf_invalid') ?: 'CSRF token tidak sah.'), 403);
    }

    if (!checkRateLimit('notification_read_all', 10, 60)) {
        jsonErrorResponse((string)(__('notification_rate_limited') ?: 'Terlalu banyak permintaan. Sila cuba sebentar lagi.'), 429);
    }

    $service = new NotificationService(Database::getInstance('mysql')->getConnection());
    $actor = $service->resolveCurrentActor();
    $updated = $service->markAllAsRead($actor);

    jsonSuccessResponse([
        'updated' => $updated,
        'unread' => $service->countUnread($actor),
        'message' => (string)(__('topbar_notification_read_all_success') ?: 'All notifications marked as read.'),
    ]);
} catch (Throwable $e) {
    error_log('[notification-read-all] ' . $e->getMessage());
    jsonErrorResponse((string)(__('topbar_notification_read_all_failed') ?: 'Gagal menanda semua notifikasi sebagai dibaca.'), 500);
}
