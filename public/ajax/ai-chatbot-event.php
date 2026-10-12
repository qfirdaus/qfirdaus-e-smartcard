<?php
/**
 * IQS FRAMEWORK CORE FILE
 *
 * READ ONLY for downstream project programmers.
 * Do not modify this file directly in template or cloned projects.
 * Custom changes must be implemented in project-specific files
 * or approved extension points.
 */
declare(strict_types=1);

ob_start();
header('Content-Type: application/json; charset=utf-8');

try {
    require_once __DIR__ . '/../includes/init.php';
    require_login();
    require_once __DIR__ . '/_helpers.php';
    require_once __DIR__ . '/../classes/Database.php';
    require_once __DIR__ . '/../classes/AiChatbotService.php';

    $t = static function (string $key, string $fallback): string {
        $value = function_exists('__') ? __($key) : null;
        return ($value === null || $value === '' || $value === $key) ? $fallback : (string)$value;
    };

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        jsonErrorResponse($t('aiChatbot_error_invalid_method', 'Kaedah permintaan tidak sah.'), 405);
    }

    if (!isValidCsrfToken()) {
        jsonErrorResponse((string)(__('userGroup_csrf_invalid') ?: 'CSRF token tidak sah.'), 403);
    }

    $pdo = Database::getInstance('mysql')->getConnection();
    $profile = $GLOBALS['profile'] ?? [];
    $profile = is_array($profile) ? $profile : [];
    $service = new AiChatbotService();
    if (!$service->canAccess($profile, $pdo)) {
        jsonErrorResponse($t('aiChatbot_error_forbidden', 'Anda tidak dibenarkan menggunakan AI Chatbot prototype.'), 403);
    }

    $raw = file_get_contents('php://input');
    $data = json_decode((string)$raw, true);
    if (!is_array($data)) {
        $data = $_POST;
    }

    $action = strtolower(trim((string)($data['action'] ?? '')));
    if ($action !== 'opened') {
        jsonErrorResponse($t('aiChatbot_error_invalid_action', 'Tindakan chatbot tidak sah.'), 422);
    }

    if (function_exists('audit_event')) {
        $publicConfig = $service->publicConfig();
        audit_event([
            'event_type' => 'AI_CHATBOT_OPENED',
            'severity' => 'INFO',
            'outcome' => 'SUCCESS',
            'target_type' => 'ai_chatbot',
            'target_id' => 'widget',
            'target_label' => 'AI Chatbot Widget',
            'message' => 'AI chatbot widget opened',
            'user_id' => $_SESSION['user']['f_userID'] ?? $_SESSION['f_userID'] ?? $_SESSION['f_stafID'] ?? null,
            'actor_label' => $_SESSION['user']['f_nama'] ?? $_SESSION['f_nama'] ?? null,
            'meta' => [
                'provider' => $publicConfig['provider'] ?? null,
                'model' => $publicConfig['model'] ?? null,
                'access_mode' => $publicConfig['access_mode'] ?? null,
            ],
        ]);
    }

    jsonSuccessResponse();
} catch (Throwable $e) {
    error_log('[ai-chatbot-event] ' . $e->getMessage());
    $fallback = function_exists('__') ? __('aiChatbot_error_generic') : null;
    jsonErrorResponse(($fallback && $fallback !== 'aiChatbot_error_generic') ? (string)$fallback : 'Tidak dapat merekod aktiviti chatbot.', 500);
}
