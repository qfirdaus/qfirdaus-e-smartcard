<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/init.php';
require_login();

require_once __DIR__ . '/../controllers/__CONTROLLER_CLASS__.php';

$controllerClass = '__CONTROLLER_CLASS__';
$controller = new $controllerClass();
$lang = $controller->lang ?? ($_SESSION['lang'] ?? 'ms');
$detail = is_array($controller->detail ?? null) ? $controller->detail : [];

if (!function_exists('h')) {
    function h($v): string
    {
        return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('t')) {
    function t(string $key, string $fallback): string
    {
        $value = __($key);
        return ($value === $key || $value === null || $value === '') ? $fallback : (string)$value;
    }
}

$PAGE_TITLE = t('__PAGE_KEY_PREFIX___page_title', '__PAGE_TITLE_MS__');
?>
<!DOCTYPE html>
<html lang="<?= h($lang) ?>">
<head>
  <?php include __DIR__ . '/../includes/head.php'; ?>
  <link href="<?= base_url('assets/css/pages/__PAGE_SLUG__.css') ?>" rel="stylesheet">
</head>
<body data-topbar-color="<?= h($_SESSION['theme.topbar'] ?? 'light') ?>" data-menu-color="<?= h($_SESSION['theme.menu'] ?? 'light') ?>" data-layout="vertical" data-sidebar-size="default">
<div id="wrapper">
  <?php include __DIR__ . '/../includes/topbar.php'; ?>
  <?php include __DIR__ . '/../includes/sidebar.php'; ?>

  <div class="content-page">
    <div class="content">
      <div class="container-fluid">
        <div class="row mb-3">
          <div class="col-12">
            <div class="page-title-box d-flex justify-content-between align-items-center flex-wrap">
              <h4 class="page-title"><i class="__PAGE_ICON__ me-1"></i> <?= h($PAGE_TITLE) ?></h4>
              <div class="page-title-right">
                <ol class="breadcrumb m-0">
                  <li class="breadcrumb-item"><a href="dashboard.php"><i class="ri-home-4-line align-middle me-1"></i> <?= h(__('breadcrumb_home')) ?></a></li>
                  <li class="breadcrumb-item active"><?= h($PAGE_TITLE) ?></li>
                </ol>
              </div>
            </div>
          </div>
        </div>

        <div class="row g-4">
          <div class="col-12">
            <div class="card detail-summary-card">
              <div class="card-body">
                <div class="detail-summary-shell">
                  <div class="detail-summary-copy">
                    <div class="detail-summary-kicker"><?= h(t('__PAGE_KEY_PREFIX___summary_title', 'Summary')) ?></div>
                    <h5 class="detail-summary-name"><?= h((string)($detail['name'] ?? '')) ?></h5>
                    <p class="text-muted mb-0"><?= h((string)($detail['summary'] ?? '')) ?></p>
                  </div>
                  <div class="detail-summary-meta">
                    <div class="detail-summary-item">
                      <span class="detail-summary-label"><?= h(t('__PAGE_KEY_PREFIX___label_code', 'Code')) ?></span>
                      <strong><?= h((string)($detail['code'] ?? '')) ?></strong>
                    </div>
                    <div class="detail-summary-item">
                      <span class="detail-summary-label"><?= h(t('__PAGE_KEY_PREFIX___label_status', 'Status')) ?></span>
                      <span class="detail-status-badge"><?= h((string)($detail['status'] ?? '')) ?></span>
                    </div>
                    <div class="detail-summary-item">
                      <span class="detail-summary-label"><?= h(t('__PAGE_KEY_PREFIX___label_updated_at', 'Last Updated')) ?></span>
                      <strong><?= h((string)($detail['updated_at'] ?? '')) ?></strong>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <div class="col-lg-7">
            <div class="card detail-section-card h-100">
              <div class="card-body">
                <h5 class="card-title mb-3"><?= h(t('__PAGE_KEY_PREFIX___details_title', 'Details')) ?></h5>
                <div class="detail-grid">
                  <div class="detail-item">
                    <div class="detail-label"><?= h(t('__PAGE_KEY_PREFIX___label_name', 'Name')) ?></div>
                    <div class="detail-value"><?= h((string)($detail['name'] ?? '')) ?></div>
                  </div>
                  <div class="detail-item">
                    <div class="detail-label"><?= h(t('__PAGE_KEY_PREFIX___label_code', 'Code')) ?></div>
                    <div class="detail-value"><?= h((string)($detail['code'] ?? '')) ?></div>
                  </div>
                  <div class="detail-item">
                    <div class="detail-label"><?= h(t('__PAGE_KEY_PREFIX___label_category', 'Category')) ?></div>
                    <div class="detail-value"><?= h((string)($detail['category'] ?? '')) ?></div>
                  </div>
                  <div class="detail-item">
                    <div class="detail-label"><?= h(t('__PAGE_KEY_PREFIX___label_owner', 'Owner')) ?></div>
                    <div class="detail-value"><?= h((string)($detail['owner'] ?? '')) ?></div>
                  </div>
                  <div class="detail-item">
                    <div class="detail-label"><?= h(t('__PAGE_KEY_PREFIX___label_status', 'Status')) ?></div>
                    <div class="detail-value"><?= h((string)($detail['status'] ?? '')) ?></div>
                  </div>
                  <div class="detail-item">
                    <div class="detail-label"><?= h(t('__PAGE_KEY_PREFIX___label_updated_at', 'Last Updated')) ?></div>
                    <div class="detail-value"><?= h((string)($detail['updated_at'] ?? '')) ?></div>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <div class="col-lg-5">
            <div class="card detail-section-card h-100">
              <div class="card-body">
                <h5 class="card-title mb-3"><?= h(t('__PAGE_KEY_PREFIX___notes_title', 'Notes')) ?></h5>
                <div class="detail-notes-box"><?= h((string)($detail['notes'] ?? '')) ?></div>
              </div>
            </div>
          </div>

          <div class="col-12">
            <div class="d-flex justify-content-end gap-2">
              <button type="button" class="btn btn-light detail-btn detail-btn-back" onclick="window.history.back();"><?= h(t('__PAGE_KEY_PREFIX___btn_back', 'Back')) ?></button>
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
