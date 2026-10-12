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

require_once __DIR__ . '/../includes/init.php';
require_login();
require_once __DIR__ . '/../ajax/_helpers.php';
require_once __DIR__ . '/../classes/Database.php';
require_once __DIR__ . '/../classes/AiChatbotService.php';

$pdo = Database::getInstance('mysql')->getConnection();
$profile = $GLOBALS['profile'] ?? [];
$profile = is_array($profile) ? $profile : [];
$service = new AiChatbotService();
if (!$service->canAccess($profile, $pdo, false)) {
    prestasi_render_page_forbidden('Anda tidak dibenarkan mengakses AI Chatbot prototype.');
}

if (!function_exists('h')) {
    function h($value): string
    {
        return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
    }
}
function ai_chatbot_page_text(string $key, string $fallback): string
{
    $value = function_exists('__') ? __($key) : null;
    return ($value === null || $value === '' || $value === $key) ? $fallback : (string)$value;
}

$chatbotConfig = $service->publicConfig();
$avatarPath = trim((string)$chatbotConfig['character_avatar']);
$avatarUrl = $avatarPath !== '' ? base_url($avatarPath) : base_url('assets/images/no-image.jpg');
$lang = (string)($_SESSION['lang'] ?? 'ms');
$version = (string)($_ENV['APP_ASSET_VER'] ?? date('ymdHis'));
$PAGE_TITLE = ai_chatbot_page_text('aiChatbot_page_title', 'AI Chatbot Prototype');
?>
<!doctype html>
<html lang="<?= h($lang) ?>" data-bs-theme="<?= h($_SESSION['theme.layout'] ?? 'light') ?>">
<head>
  <?php
    $NEED_DATERANGE = false;
    $NEED_VECTORMAP = false;
    $NEED_DATATABLES = false;
    $NEED_SELECT2 = false;
    include __DIR__ . '/../includes/head.php';
  ?>
  <meta name="csrf-token" content="<?= h((string)($_SESSION['csrf_token'] ?? '')) ?>">
  <link href="<?= h(base_url('assets/css/ai-chatbot-widget.css')) ?>?v=<?= h($version) ?>" rel="stylesheet">
</head>
<body data-topbar-color="<?= h($_SESSION['theme.topbar'] ?? 'light') ?>"
      data-menu-color="<?= h($_SESSION['theme.menu'] ?? 'light') ?>"
      data-layout="vertical"
      data-sidebar-size="default"
      class="loading">
<div class="wrapper">
  <?php include __DIR__ . '/../includes/topbar.php'; ?>
  <?php include __DIR__ . '/../includes/sidebar.php'; ?>

  <div class="content-page">
    <div class="content">
      <div class="container-fluid ai-chatbot-prototype">
        <div class="row">
          <div class="col-12">
            <div class="page-title-box d-sm-flex align-items-center justify-content-between">
              <h4 class="mb-sm-0"><i class="ri-robot-2-line me-1"></i><?= h($PAGE_TITLE) ?></h4>
              <div class="page-title-right">
                <ol class="breadcrumb m-0">
                  <li class="breadcrumb-item"><a href="<?= h(base_url('pages/dashboard.php')) ?>">Dashboard</a></li>
                  <li class="breadcrumb-item active"><?= h($PAGE_TITLE) ?></li>
                </ol>
              </div>
            </div>
          </div>
        </div>

        <div class="row g-3">
          <div class="col-xl-7">
            <div class="ai-chatbot-prototype__panel p-4">
              <div class="d-flex align-items-center gap-3 mb-3">
                <img src="<?= h($avatarUrl) ?>" alt="" class="ai-chatbot-avatar" onerror="this.style.display='none'">
                <div>
                  <h5 class="mb-1"><?= h((string)$chatbotConfig['character_name']) ?></h5>
                  <div class="text-muted"><?= h(ai_chatbot_page_text('aiChatbot_phase_label', 'Phase 4 framework integration')) ?></div>
                </div>
              </div>
              <p class="text-muted mb-3">
                <?= h(ai_chatbot_page_text('aiChatbot_intro', 'Widget chatbot kini dirender secara global apabila feature aktif dan pengguna mempunyai akses.')) ?>
              </p>
              <?php if (!empty($chatbotConfig['enabled'])): ?>
                <div class="alert alert-success mb-0">
                  <?= h(ai_chatbot_page_text('aiChatbot_active_status', 'AI Chatbot aktif.')) ?> Provider: <strong><?= h((string)$chatbotConfig['provider']) ?></strong>,
                  model: <strong><?= h((string)$chatbotConfig['model']) ?></strong>.
                </div>
              <?php else: ?>
                <div class="alert alert-warning mb-0">
                  <?= h(ai_chatbot_page_text('aiChatbot_inactive_status', 'AI Chatbot belum aktif. Untuk test, aktifkan AI Chatbot dan pilih provider dalam Tetapan Sistem.')) ?>
                </div>
              <?php endif; ?>
            </div>
          </div>

          <div class="col-xl-5">
            <div class="ai-chatbot-prototype__panel p-4">
              <h5 class="mb-3"><?= h(ai_chatbot_page_text('aiChatbot_current_config', 'Current Prototype Config')) ?></h5>
              <div class="table-responsive">
                <table class="table table-sm align-middle mb-0">
                  <tbody>
                    <tr><th scope="row">Enabled</th><td><?= !empty($chatbotConfig['enabled']) ? 'true' : 'false' ?></td></tr>
                    <tr><th scope="row">Provider</th><td><?= h((string)$chatbotConfig['provider']) ?></td></tr>
                    <tr><th scope="row">Model</th><td><?= h((string)$chatbotConfig['model']) ?></td></tr>
                    <tr><th scope="row">Base URL</th><td><code><?= h((string)$chatbotConfig['base_url']) ?></code></td></tr>
                    <tr><th scope="row">Access</th><td><?= h((string)$chatbotConfig['access_mode']) ?></td></tr>
                    <tr><th scope="row">Allowed Groups</th><td><code><?= h((string)$chatbotConfig['allowed_groups']) ?></code></td></tr>
                    <tr><th scope="row">Rate / Minute</th><td><?= h((string)$chatbotConfig['rate_limit_per_minute']) ?></td></tr>
                    <tr><th scope="row">User Daily Limit</th><td><?= h((string)$chatbotConfig['user_daily_request_limit']) ?></td></tr>
                    <tr><th scope="row">Global Daily Limit</th><td><?= h((string)$chatbotConfig['global_daily_request_limit']) ?></td></tr>
                    <tr><th scope="row">Persist Usage</th><td><?= !empty($chatbotConfig['persist_usage']) ? 'true' : 'false' ?></td></tr>
                    <tr><th scope="row">Store Conversations</th><td><?= !empty($chatbotConfig['store_conversations']) ? 'true' : 'false' ?></td></tr>
                    <tr><th scope="row">Character</th><td><?= h((string)$chatbotConfig['character_name']) ?></td></tr>
                    <tr><th scope="row">Avatar</th><td><code><?= h((string)$chatbotConfig['character_avatar']) ?></code></td></tr>
                  </tbody>
                </table>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
    <?php include __DIR__ . '/../includes/footer.php'; ?>
  </div>
</div>

<?php include __DIR__ . '/../includes/script.php'; ?>
</body>
</html>
