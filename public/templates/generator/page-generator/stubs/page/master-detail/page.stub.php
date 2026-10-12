<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/init.php';
require_login();

require_once __DIR__ . '/../controllers/__CONTROLLER_CLASS__.php';

$controllerClass = '__CONTROLLER_CLASS__';
$controller = new $controllerClass();
$lang = $controller->lang ?? ($_SESSION['lang'] ?? 'ms');
$items = is_array($controller->items ?? null) ? $controller->items : [];
$selectedItem = is_array($controller->selectedItem ?? null) ? $controller->selectedItem : [];

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
            <div class="card master-detail-card">
              <div class="card-body">
                <div class="master-detail-shell">
                  <div class="master-panel">
                    <div class="master-panel-header">
                      <h5 class="card-title mb-1"><?= h(t('__PAGE_KEY_PREFIX___master_title', 'Master List')) ?></h5>
                      <p class="text-muted mb-0"><?= h(t('__PAGE_KEY_PREFIX___master_subtitle', 'Select an item below to update the detail panel without leaving the page.')) ?></p>
                    </div>
                    <div class="master-list" id="masterDetailList">
                      <?php foreach ($items as $index => $item): ?>
                        <?php $isActive = $index === 0; ?>
                        <button type="button"
                                class="master-list-item <?= $isActive ? 'is-active' : '' ?>"
                                data-master-item='<?= h(json_encode($item, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) ?>'>
                          <span class="master-item-top">
                            <span class="master-item-name"><?= h((string)($item['name'] ?? '')) ?></span>
                            <span class="master-item-code"><?= h((string)($item['code'] ?? '')) ?></span>
                          </span>
                          <span class="master-item-summary"><?= h((string)($item['summary'] ?? '')) ?></span>
                        </button>
                      <?php endforeach; ?>
                    </div>
                  </div>

                  <div class="detail-panel" id="masterDetailPanel">
                    <div class="detail-panel-kicker"><?= h(t('__PAGE_KEY_PREFIX___detail_title', 'Detail Panel')) ?></div>
                    <h5 class="detail-panel-name" data-detail-name><?= h((string)($selectedItem['name'] ?? '')) ?></h5>
                    <p class="detail-panel-summary text-muted" data-detail-summary><?= h((string)($selectedItem['summary'] ?? '')) ?></p>

                    <div class="detail-panel-grid">
                      <div class="detail-panel-item">
                        <span class="detail-panel-label"><?= h(t('__PAGE_KEY_PREFIX___label_code', 'Code')) ?></span>
                        <strong data-detail-code><?= h((string)($selectedItem['code'] ?? '')) ?></strong>
                      </div>
                      <div class="detail-panel-item">
                        <span class="detail-panel-label"><?= h(t('__PAGE_KEY_PREFIX___label_status', 'Status')) ?></span>
                        <strong data-detail-status><?= h((string)($selectedItem['status'] ?? '')) ?></strong>
                      </div>
                      <div class="detail-panel-item">
                        <span class="detail-panel-label"><?= h(t('__PAGE_KEY_PREFIX___label_owner', 'Owner')) ?></span>
                        <strong data-detail-owner><?= h((string)($selectedItem['owner'] ?? '')) ?></strong>
                      </div>
                      <div class="detail-panel-item">
                        <span class="detail-panel-label"><?= h(t('__PAGE_KEY_PREFIX___label_updated_at', 'Last Updated')) ?></span>
                        <strong data-detail-updated><?= h((string)($selectedItem['updated_at'] ?? '')) ?></strong>
                      </div>
                    </div>

                    <div class="detail-panel-section">
                      <div class="detail-panel-label"><?= h(t('__PAGE_KEY_PREFIX___label_description', 'Description')) ?></div>
                      <div class="detail-panel-box" data-detail-description><?= h((string)($selectedItem['description'] ?? '')) ?></div>
                    </div>

                    <div class="detail-panel-section">
                      <div class="detail-panel-label"><?= h(t('__PAGE_KEY_PREFIX___label_tags', 'Tags')) ?></div>
                      <div class="detail-panel-tags" data-detail-tags>
                        <?php foreach ((array)($selectedItem['tags'] ?? []) as $tag): ?>
                          <span class="detail-tag-chip"><?= h((string)$tag) ?></span>
                        <?php endforeach; ?>
                      </div>
                    </div>
                  </div>
                </div>
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
<script>
(function () {
  function renderTags(container, tags) {
    if (!container) return;
    container.innerHTML = '';
    (Array.isArray(tags) ? tags : []).forEach(function (tag) {
      var span = document.createElement('span');
      span.className = 'detail-tag-chip';
      span.textContent = String(tag);
      container.appendChild(span);
    });
  }

  document.addEventListener('DOMContentLoaded', function () {
    var items = document.querySelectorAll('#masterDetailList .master-list-item');
    var detailPanel = document.getElementById('masterDetailPanel');
    if (!items.length || !detailPanel) {
      return;
    }

    items.forEach(function (button) {
      button.addEventListener('click', function () {
        items.forEach(function (node) { node.classList.remove('is-active'); });
        button.classList.add('is-active');

        var payload = {};
        try {
          payload = JSON.parse(button.getAttribute('data-master-item') || '{}');
        } catch (error) {
          payload = {};
        }

        detailPanel.querySelector('[data-detail-name]').textContent = payload.name || '-';
        detailPanel.querySelector('[data-detail-summary]').textContent = payload.summary || '-';
        detailPanel.querySelector('[data-detail-code]').textContent = payload.code || '-';
        detailPanel.querySelector('[data-detail-status]').textContent = payload.status || '-';
        detailPanel.querySelector('[data-detail-owner]').textContent = payload.owner || '-';
        detailPanel.querySelector('[data-detail-updated]').textContent = payload.updated_at || '-';
        detailPanel.querySelector('[data-detail-description]').textContent = payload.description || '-';
        renderTags(detailPanel.querySelector('[data-detail-tags]'), payload.tags || []);
      });
    });
  });
})();
</script>
</body>
</html>
