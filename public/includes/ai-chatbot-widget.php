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

if (defined('AI_CHATBOT_WIDGET_RENDERED')) {
    return;
}
define('AI_CHATBOT_WIDGET_RENDERED', true);

require_once __DIR__ . '/../classes/Database.php';
require_once __DIR__ . '/../classes/AiChatbotService.php';

if (!function_exists('ai_chatbot_h')) {
    function ai_chatbot_h($value): string
    {
        return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('ai_chatbot_text')) {
    function ai_chatbot_text(string $key, string $fallback): string
    {
        $value = function_exists('__') ? __($key) : null;
        return ($value === null || $value === '' || $value === $key) ? $fallback : (string)$value;
    }
}

try {
    $aiChatbotService = new AiChatbotService();
    if (!$aiChatbotService->isEnabled()) {
        return;
    }

    $aiChatbotPdo = Database::getInstance('mysql')->getConnection();
    $aiChatbotProfile = $GLOBALS['profile'] ?? [];
    $aiChatbotProfile = is_array($aiChatbotProfile) ? $aiChatbotProfile : [];
    if (!$aiChatbotService->canAccess($aiChatbotProfile, $aiChatbotPdo)) {
        return;
    }

    $aiChatbotConfig = $aiChatbotService->publicConfig();
    $aiChatbotVersion = (string)($_ENV['APP_ASSET_VER'] ?? date('ymdHis'));
    $aiChatbotAvatarPath = trim((string)($aiChatbotConfig['character_avatar'] ?? ''));
    $aiChatbotAvatarUrl = $aiChatbotAvatarPath !== '' ? base_url($aiChatbotAvatarPath) : base_url('assets/images/no-image.jpg');
} catch (Throwable $e) {
    error_log('[ai-chatbot-widget] ' . $e->getMessage());
    return;
}
?>
<link href="<?= ai_chatbot_h(base_url('assets/css/ai-chatbot-widget.css')) ?>?v=<?= ai_chatbot_h($aiChatbotVersion) ?>" rel="stylesheet">

<button type="button"
        class="ai-chatbot-launcher"
        data-ai-chatbot-launcher
        aria-label="<?= ai_chatbot_h(ai_chatbot_text('aiChatbot_open_label', 'Open AI Chatbot')) ?>"
        aria-expanded="false">
  <img src="<?= ai_chatbot_h($aiChatbotAvatarUrl) ?>" alt="" onerror="this.style.display='none'; this.nextElementSibling.style.display='inline-block';">
  <i class="ri-robot-2-line" style="display:none;font-size:1.5rem"></i>
</button>

<section class="ai-chatbot-panel" data-ai-chatbot-panel aria-label="<?= ai_chatbot_h(ai_chatbot_text('aiChatbot_panel_label', 'AI Chatbot panel')) ?>">
  <div class="ai-chatbot-panel__header">
    <img src="<?= ai_chatbot_h($aiChatbotAvatarUrl) ?>" alt="" class="ai-chatbot-avatar" onerror="this.style.display='none'">
    <div class="ai-chatbot-panel__title">
      <span class="ai-chatbot-panel__name"><?= ai_chatbot_h((string)$aiChatbotConfig['character_name']) ?></span>
      <span class="ai-chatbot-panel__status" data-ai-chatbot-status><?= ai_chatbot_h(ai_chatbot_text('aiChatbot_status_ready', 'Prototype aktif')) ?></span>
    </div>
    <button type="button" class="ai-chatbot-panel__close" data-ai-chatbot-close aria-label="<?= ai_chatbot_h(ai_chatbot_text('aiChatbot_close_label', 'Close AI Chatbot')) ?>">
      <i class="ri-close-line"></i>
    </button>
  </div>
  <div class="ai-chatbot-panel__messages" data-ai-chatbot-messages></div>
  <form class="ai-chatbot-panel__form" data-ai-chatbot-form>
    <textarea class="form-control ai-chatbot-panel__input"
              data-ai-chatbot-input
              rows="1"
              maxlength="<?= ai_chatbot_h((string)$aiChatbotService->maxInputChars()) ?>"
              placeholder="<?= ai_chatbot_h(ai_chatbot_text('aiChatbot_input_placeholder', 'Tulis mesej...')) ?>"></textarea>
    <button type="submit" class="btn btn-primary ai-chatbot-panel__send" data-ai-chatbot-send aria-label="<?= ai_chatbot_h(ai_chatbot_text('aiChatbot_send_label', 'Send')) ?>">
      <i class="ri-send-plane-2-line"></i>
    </button>
  </form>
</section>

<script>
window.IQS_AI_CHATBOT = {
  enabled: true,
  endpoint: <?= json_encode(base_url('ajax/ai-chatbot-message.php'), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>,
  eventEndpoint: <?= json_encode(base_url('ajax/ai-chatbot-event.php'), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>,
  csrf: <?= json_encode((string)($_SESSION['csrf_token'] ?? ''), JSON_UNESCAPED_UNICODE) ?>,
  welcomeMessage: <?= json_encode((string)$aiChatbotConfig['welcome_message'], JSON_UNESCAPED_UNICODE) ?>,
  i18n: {
    ready: <?= json_encode(ai_chatbot_text('aiChatbot_status_ready', 'Prototype aktif'), JSON_UNESCAPED_UNICODE) ?>,
    busy: <?= json_encode(ai_chatbot_text('aiChatbot_status_busy', 'Sedang menjawab...'), JSON_UNESCAPED_UNICODE) ?>,
    disabled: <?= json_encode(ai_chatbot_text('aiChatbot_status_disabled', 'Belum aktif'), JSON_UNESCAPED_UNICODE) ?>,
    genericError: <?= json_encode(ai_chatbot_text('aiChatbot_error_generic', 'AI Chatbot tidak dapat menjawab buat masa ini.'), JSON_UNESCAPED_UNICODE) ?>
  }
};
</script>
<script src="<?= ai_chatbot_h(base_url('assets/js/ai-chatbot-widget.js')) ?>?v=<?= ai_chatbot_h($aiChatbotVersion) ?>"></script>
