<?php
/**
 * IQS FRAMEWORK CORE FILE
 *
 * READ ONLY for downstream project programmers.
 */
declare(strict_types=1);

require_once __DIR__ . '/../includes/init.php';
require_login();
require_once __DIR__ . '/../controllers/AccessController.php';

if (!function_exists('h')) {
    function h(mixed $value): string
    {
        return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
    }
}
if (!function_exists('am')) {
    function am(string $suffix, string $fallback): string
    {
        $key = 'access_matrix_' . $suffix;
        $value = __($key);
        return is_string($value) && $value !== '' && $value !== $key ? $value : $fallback;
    }
}

$loadError = false;
$matrix = ['roles' => [], 'modules' => [], 'totals' => []];
try {
    $controller = new AccessController();
    $lang = $controller->lang;
    $matrix = $controller->getMatrix();
} catch (Throwable $exception) {
    $lang = in_array((string)($_SESSION['lang'] ?? 'ms'), ['ms', 'en'], true) ? (string)$_SESSION['lang'] : 'ms';
    $loadError = true;
    error_log('[access-matrix.php] Load error: ' . $exception->getMessage());
}

$roles = $matrix['roles'] ?? [];
$modules = $matrix['modules'] ?? [];
$totals = array_replace(
    ['roles' => 0, 'modules' => 0, 'menus' => 0, 'permissions' => 0],
    is_array($matrix['totals'] ?? null) ? $matrix['totals'] : []
);
$assetVersion = (string)($_ENV['APP_ASSET_VER'] ?? filemtime(__DIR__ . '/../assets/css/pages/access-matrix.css'));
$PAGE_TITLE = am('title', 'Access Matrix');
?>
<!DOCTYPE html>
<html lang="<?= h($lang) ?>" data-bs-theme="<?= h($_SESSION['theme.layout'] ?? 'light') ?>">
<head>
  <?php include __DIR__ . '/../includes/head.php'; ?>
  <meta name="referrer" content="no-referrer">
  <link href="<?= base_url('assets/css/pages/access-matrix.css') ?>?v=<?= h($assetVersion) ?>" rel="stylesheet">
</head>
<body
  data-topbar-color="<?= h($_SESSION['theme.topbar'] ?? 'light') ?>"
  data-menu-color="<?= h($_SESSION['theme.menu'] ?? 'light') ?>"
  data-layout="vertical"
  data-sidebar-size="default"
  class="loading">

<div class="wrapper">
  <?php include __DIR__ . '/../includes/topbar.php'; ?>
  <?php include __DIR__ . '/../includes/sidebar.php'; ?>

  <div class="content-page">
    <div class="content">
      <div class="container-fluid access-matrix-page">
        <div class="row mb-3">
          <div class="col-12">
            <div class="page-title-box d-flex justify-content-between align-items-center flex-wrap">
              <h4 class="page-title"><i class="ri-shield-keyhole-line me-1"></i> <?= h(am('title', 'Access Matrix')) ?></h4>
              <div class="page-title-right">
                <ol class="breadcrumb m-0">
                  <li class="breadcrumb-item"><a href="<?= h(base_path('pages/dashboard.php')) ?>"><?= h(am('breadcrumb_home', 'Dashboard')) ?></a></li>
                  <li class="breadcrumb-item active"><?= h(am('title', 'Access Matrix')) ?></li>
                </ol>
              </div>
            </div>
          </div>
        </div>

        <section class="access-matrix-hero">
          <div>
            <span class="access-matrix-eyebrow"><i class="ri-lock-2-line"></i> <?= h(am('eyebrow', 'Super Admin Access Review')) ?></span>
            <h1><?= h(am('hero_title', 'Understand access across every system menu')) ?></h1>
            <p><?= h(am('hero_text', 'Review the complete menu catalogue and compare group permissions from one controlled workspace.')) ?></p>
          </div>
          <div class="access-matrix-hero__notice">
            <i class="ri-eye-line"></i>
            <div><strong><?= h(am('readonly_title', 'Read-only view')) ?></strong><span><?= h(am('readonly_text', 'Access changes are managed from User Groups.')) ?></span></div>
          </div>
        </section>

        <section class="access-matrix-card">
          <header class="access-matrix-card__header">
            <div>
              <span class="access-matrix-eyebrow"><?= h(am('workspace_eyebrow', 'Permission Explorer')) ?></span>
              <h2><?= h(am('workspace_title', 'System Access Matrix')) ?></h2>
              <p><?= h(am('workspace_text', 'Search and filter locally without reloading the page.')) ?></p>
            </div>
            <div class="access-matrix-legend" aria-label="<?= h(am('legend', 'Legend')) ?>">
              <span><i class="ri-checkbox-circle-fill is-allowed"></i> <?= h(am('has_access', 'Has access')) ?></span>
              <span><i class="ri-close-circle-line is-denied"></i> <?= h(am('no_access', 'No access')) ?></span>
            </div>
          </header>

          <div class="access-matrix-filters" id="access-matrix-filters">
            <label class="access-matrix-field access-matrix-field--search">
              <span><?= h(am('search_label', 'Search menu or path')) ?></span>
              <span class="access-matrix-input"><i class="ri-search-line"></i><input type="search" id="matrix-search" placeholder="<?= h(am('search_placeholder', 'Type a menu name or path...')) ?>" autocomplete="off"></span>
            </label>
            <label class="access-matrix-field">
              <span><?= h(am('module_filter', 'Module')) ?></span>
              <select id="matrix-module" class="form-select">
                <option value=""><?= h(am('all_modules', 'All modules')) ?></option>
                <?php foreach ($modules as $module): ?><option value="<?= h((string)$module['id']) ?>"><?= h($module['nama']) ?></option><?php endforeach; ?>
              </select>
            </label>
            <label class="access-matrix-field">
              <span><?= h(am('group_filter', 'User group')) ?></span>
              <select id="matrix-role" class="form-select">
                <option value=""><?= h(am('all_groups', 'All groups')) ?></option>
                <?php foreach ($roles as $role): ?><option value="<?= h((string)$role['id']) ?>"><?= h($role['nama']) ?></option><?php endforeach; ?>
              </select>
            </label>
            <label class="access-matrix-field">
              <span><?= h(am('status_filter', 'Access status')) ?></span>
              <select id="matrix-status" class="form-select">
                <option value="all"><?= h(am('all_statuses', 'All statuses')) ?></option>
                <option value="allowed"><?= h(am('has_access', 'Has access')) ?></option>
                <option value="denied"><?= h(am('no_access', 'No access')) ?></option>
              </select>
            </label>
            <button type="button" class="btn access-matrix-reset" id="matrix-reset"><i class="ri-refresh-line"></i> <?= h(am('reset', 'Reset')) ?></button>
          </div>

          <div class="access-matrix-results" aria-live="polite">
            <span id="matrix-result-count"><?= h(sprintf(am('result_count', '%d menus displayed'), (int)$totals['menus'])) ?></span>
            <span><?= h(am('scroll_hint', 'Scroll horizontally to compare groups')) ?> <i class="ri-arrow-right-line"></i></span>
          </div>

          <?php if ($loadError): ?>
            <div class="access-matrix-empty"><i class="ri-error-warning-line"></i><h3><?= h(am('error_title', 'Unable to load access matrix')) ?></h3><p><?= h(am('error_text', 'Please refresh the page or try again later.')) ?></p></div>
          <?php elseif (!$modules || !$roles): ?>
            <div class="access-matrix-empty"><i class="ri-inbox-2-line"></i><h3><?= h(am('empty_title', 'No access data available')) ?></h3><p><?= h(am('empty_text', 'No modules, menus or user groups are currently available.')) ?></p></div>
          <?php else: ?>
            <div class="access-matrix-table-wrap" tabindex="0" aria-label="<?= h(am('table_label', 'System access matrix table')) ?>">
              <table class="access-matrix-table" id="access-matrix-table">
                <thead><tr>
                  <th class="matrix-menu-col"><?= h(am('menu_column', 'Menu')) ?></th>
                  <?php foreach ($roles as $role): ?>
                    <th class="matrix-role-col" data-role-column="<?= h((string)$role['id']) ?>" title="<?= h($role['nama']) ?>"><span><?= h($role['nama']) ?></span><small><?= h($role['kod']) ?></small></th>
                  <?php endforeach; ?>
                </tr></thead>
                <tbody>
                <?php foreach ($modules as $module): ?>
                  <tr class="matrix-module-row" data-module-heading="<?= h((string)$module['id']) ?>"><td colspan="<?= 1 + count($roles) ?>"><i class="ri-layout-grid-line"></i><span><?= h($module['nama']) ?></span><small><?= h(sprintf(am('module_menu_count', '%d menus'), count($module['menus']))) ?></small></td></tr>
                  <?php foreach ($module['menus'] as $menu):
                    $anyAllowed = in_array(true, $menu['perms'], true); ?>
                    <tr class="matrix-menu-row" data-module="<?= h((string)$module['id']) ?>" data-search="<?= h(strtolower($menu['nama'] . ' ' . $menu['path'])) ?>" data-any-allowed="<?= $anyAllowed ? '1' : '0' ?>">
                      <td class="matrix-menu-col"><strong><?= h($menu['nama']) ?></strong><code class="matrix-menu-path"><?= h($menu['path'] !== '' ? $menu['path'] : '—') ?></code></td>
                      <?php foreach ($roles as $role): $allowed = !empty($menu['perms'][(int)$role['id']]); ?>
                        <td class="matrix-role-col" data-role-column="<?= h((string)$role['id']) ?>" data-access="<?= $allowed ? 'allowed' : 'denied' ?>">
                          <span class="matrix-access <?= $allowed ? 'is-allowed' : 'is-denied' ?>" title="<?= h($allowed ? am('has_access', 'Has access') : am('no_access', 'No access')) ?>">
                            <i class="<?= $allowed ? 'ri-checkbox-circle-fill' : 'ri-close-circle-line' ?>"></i><span class="visually-hidden"><?= h($allowed ? am('has_access', 'Has access') : am('no_access', 'No access')) ?></span>
                          </span>
                        </td>
                      <?php endforeach; ?>
                    </tr>
                  <?php endforeach; ?>
                <?php endforeach; ?>
                </tbody>
              </table>
            </div>
            <div class="access-matrix-empty access-matrix-empty--filtered d-none" id="matrix-filter-empty"><i class="ri-filter-off-line"></i><h3><?= h(am('filter_empty_title', 'No matching menus')) ?></h3><p><?= h(am('filter_empty_text', 'Adjust or reset the current filters.')) ?></p></div>
          <?php endif; ?>
        </section>
      </div>
    </div>
    <?php include __DIR__ . '/../includes/footer.php'; ?>
  </div>
</div>

<?php include __DIR__ . '/../includes/script.php'; ?>
<script>
(function () {
  'use strict';
  const table = document.getElementById('access-matrix-table');
  if (!table) return;
  const search = document.getElementById('matrix-search');
  const moduleFilter = document.getElementById('matrix-module');
  const roleFilter = document.getElementById('matrix-role');
  const statusFilter = document.getElementById('matrix-status');
  const reset = document.getElementById('matrix-reset');
  const resultCount = document.getElementById('matrix-result-count');
  const empty = document.getElementById('matrix-filter-empty');
  const resultTemplate = <?= json_encode(am('result_count', '%d menus displayed'), JSON_UNESCAPED_UNICODE) ?>;

  function applyFilters() {
    const term = (search.value || '').trim().toLocaleLowerCase();
    const moduleId = moduleFilter.value;
    const roleId = roleFilter.value;
    const status = statusFilter.value;
    let visibleCount = 0;
    const visibleModules = new Set();

    table.querySelectorAll('.matrix-role-col').forEach(function (column) {
      column.hidden = roleId !== '' && column.dataset.roleColumn !== roleId;
    });

    table.querySelectorAll('.matrix-menu-row').forEach(function (row) {
      let statusMatch = true;
      if (status !== 'all') {
        if (roleId) {
          const cell = row.querySelector('[data-role-column="' + roleId + '"]');
          statusMatch = !!cell && cell.dataset.access === status;
        } else {
          const anyAllowed = row.dataset.anyAllowed === '1';
          statusMatch = status === 'allowed' ? anyAllowed : !anyAllowed;
        }
      }
      const visible = (!term || row.dataset.search.includes(term)) && (!moduleId || row.dataset.module === moduleId) && statusMatch;
      row.hidden = !visible;
      if (visible) {
        visibleCount++;
        visibleModules.add(row.dataset.module);
      }
    });

    table.querySelectorAll('.matrix-module-row').forEach(function (row) {
      row.hidden = !visibleModules.has(row.dataset.moduleHeading);
    });
    resultCount.textContent = resultTemplate.replace('%d', String(visibleCount));
    empty.classList.toggle('d-none', visibleCount !== 0);
    table.parentElement.classList.toggle('d-none', visibleCount === 0);
  }

  [search, moduleFilter, roleFilter, statusFilter].forEach(function (control) {
    control.addEventListener(control === search ? 'input' : 'change', applyFilters);
  });
  reset.addEventListener('click', function () {
    search.value = '';
    moduleFilter.value = '';
    roleFilter.value = '';
    statusFilter.value = 'all';
    applyFilters();
    search.focus();
  });
})();
</script>
</body>
</html>
