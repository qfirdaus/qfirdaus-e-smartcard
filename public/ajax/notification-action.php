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

    if (!checkRateLimit('notification_action', 30, 60)) {
        jsonErrorResponse((string)(__('notification_rate_limited') ?: 'Terlalu banyak permintaan. Sila cuba sebentar lagi.'), 429);
    }

    $raw = file_get_contents('php://input');
    $data = json_decode((string)$raw, true);
    if (!is_array($data)) {
        $data = [];
    }

    $notificationId = (int)($data['notification_id'] ?? 0);
    $status = strtolower(trim((string)($data['status'] ?? 'completed')));
    if ($notificationId <= 0 || !in_array($status, ['completed', 'cancelled', 'expired'], true)) {
        jsonErrorResponse((string)(__('notification_action_invalid') ?: 'Tindakan notifikasi tidak sah.'), 422);
    }

    $service = new NotificationService(Database::getInstance('mysql')->getConnection());
    $actor = $service->resolveCurrentActor();

    $ok = match ($status) {
        'cancelled' => $service->markActionCancelled($notificationId, $actor),
        'expired' => $service->markActionExpired($notificationId, $actor),
        default => $service->markActionCompleted($notificationId, $actor),
    };

    if (!$ok) {
        jsonErrorResponse((string)(__('notification_action_failed') ?: 'Gagal mengemaskini tindakan notifikasi.'), 404);
    }

    jsonSuccessResponse([
        'message' => (string)(__('notification_action_success') ?: 'Status tindakan notifikasi telah dikemaskini.'),
        'notification_id' => $notificationId,
        'action_status' => $status,
        'unread' => $service->countUnread($actor),
    ]);
} catch (Throwable $e) {
    error_log('[notification-action] ' . $e->getMessage());
    jsonErrorResponse((string)(__('notification_action_failed') ?: 'Gagal mengemaskini tindakan notifikasi.'), 500);
}
