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
require_once __DIR__ . '/../controllers/SystemCacheMaintenanceController.php';

$pdo = Database::getInstance('mysql')->getConnection();
$cacheProfile = function_exists('userListResolveCurrentProfile') ? userListResolveCurrentProfile($pdo) : [];
$isCacheSuperAdmin = $cacheProfile && function_exists('is_user_super_admin') && is_user_super_admin($cacheProfile, $pdo);
if (!$isCacheSuperAdmin) {
    http_response_code(403);
    header('Content-Type: text/html; charset=utf-8');
    echo '<!doctype html><html lang="' . htmlspecialchars((string)($_SESSION['lang'] ?? 'ms'), ENT_QUOTES, 'UTF-8') . '"><head><meta charset="utf-8"><title>403</title></head><body>';
    echo htmlspecialchars((string)__('systemCache_forbidden'), ENT_QUOTES, 'UTF-8');
    echo '</body></html>';
    exit;
}

if (!function_exists('h')) {
    function h($value): string
    {
        return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
    }
}

function sc_t(string $key, string $fallback): string
{
    $value = __($key);
    return ($value === $key || $value === null || $value === '') ? $fallback : (string)$value;
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$controller = new SystemCacheMaintenanceController();
$locations = $controller->getLocations();
$lang = (string)($_SESSION['lang'] ?? 'ms');
$version = (string)($_ENV['APP_ASSET_VER'] ?? date('ymdHis'));
$PAGE_TITLE = sc_t('systemCache_page_title', 'System Cache');
?>
<!doctype html>
<html lang="<?= h($lang) ?>" data-bs-theme="<?= h($_SESSION['theme.layout'] ?? 'light') ?>">
<head>
  <?php
    $NEED_DATERANGE = false;
    $NEED_VECTORMAP = false;
    $NEED_DATATABLES = true;
    $NEED_SELECT2 = false;
    include __DIR__ . '/../includes/head.php';
  ?>
  <meta name="csrf-token" content="<?= h((string)$_SESSION['csrf_token']) ?>">
  <link href="<?= h(base_url('assets/css/datatables-standard.css')) ?>?v=<?= h($version) ?>" rel="stylesheet">
  <link href="<?= h(base_url('assets/css/pages/system-cache.css')) ?>?v=<?= h($version) ?>" rel="stylesheet">
  <script src="<?= h(base_url('assets/js/helpers/datatables-standard.js')) ?>?v=<?= h($version) ?>"></script>
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
      <div class="container-fluid">
        <div class="row">
          <div class="col-12">
            <div class="page-title-box d-sm-flex align-items-center justify-content-between">
              <h4 class="mb-sm-0"><i class="ri-delete-bin-6-line me-1"></i><?= h(sc_t('systemCache_page_title', 'System Cache')) ?></h4>
              <div class="page-title-right">
                <ol class="breadcrumb m-0">
                  <li class="breadcrumb-item"><a href="<?= h(base_url('pages/dashboard.php')) ?>"><?= h(sc_t('common_dashboard', 'Dashboard')) ?></a></li>
                  <li class="breadcrumb-item active"><?= h(sc_t('systemCache_breadcrumb_active', 'Clear Cache')) ?></li>
                </ol>
              </div>
            </div>
          </div>
        </div>

        <section class="sc-hero">
          <div class="sc-hero-copy">
            <span class="sc-eyebrow"><i class="ri-shield-check-line"></i><?= h(sc_t('systemCache_eyebrow', 'Super Admin Maintenance')) ?></span>
            <h1><?= h(sc_t('systemCache_hero_title', 'Keep system cache healthy and controlled')) ?></h1>
            <p><?= h(sc_t('systemCache_hero_subtitle', 'Review discovered cache locations and perform a controlled cleanup without removing the directory structure.')) ?></p>
          </div>
          <div class="sc-risk-card">
            <i class="ri-alert-line"></i>
            <div><strong><?= h(sc_t('systemCache_risk_title', 'Sensitive operation')) ?></strong><span><?= h(sc_t('systemCache_risk_text', 'Clearing all cache also resets available PHP runtime caches.')) ?></span></div>
          </div>
        </section>

        <div class="card system-cache-table-card">
          <div class="card-body">
            <div class="d-flex flex-wrap justify-content-between align-items-center mb-3 system-cache-actions">
              <div>
                <h5 class="card-title mb-1"><?= h(sc_t('systemCache_table_title', 'Discovered Cache Locations')) ?></h5>
                <p class="text-muted mb-0"><?= h(sc_t('systemCache_table_subtitle', 'Only cache files inside standard project cache folders are listed. Directory structure is preserved.')) ?></p>
              </div>
              <div class="d-flex flex-wrap align-items-center system-cache-actions">
                <span class="sc-selection-count" id="systemCacheSelectionCount" aria-live="polite"><?= h(sc_t('systemCache_selected_none', 'No locations selected')) ?></span>
                <button type="button" class="btn btn-outline-danger" id="btnClearSelected" disabled>
                  <i class="ri-checkbox-multiple-line me-1"></i><?= h(sc_t('systemCache_action_clear_selected', 'Clear Selected')) ?>
                </button>
                <button type="button" class="btn btn-danger" id="btnClearAll" <?= $locations === [] ? 'disabled' : '' ?>>
                  <i class="ri-delete-bin-line me-1"></i><?= h(sc_t('systemCache_action_clear_all', 'Clear All Cache')) ?>
                </button>
              </div>
            </div>

            <div class="table-responsive dt-standard system-cache-table-shell">
              <table id="systemCacheTable" class="table align-middle w-100">
                <thead>
                  <tr>
                    <th class="sc-check-col"><input type="checkbox" class="form-check-input" id="selectAllCache" aria-label="<?= h(sc_t('systemCache_select_all', 'Select all cache locations')) ?>"></th>
                    <th><?= h(sc_t('systemCache_col_location', 'Location')) ?></th>
                    <th><?= h(sc_t('systemCache_col_files', 'Files')) ?></th>
                    <th><?= h(sc_t('systemCache_col_size', 'Size')) ?></th>
                    <th><?= h(sc_t('systemCache_col_modified', 'Last Modified')) ?></th>
                  </tr>
                </thead>
                <tbody>
                <?php foreach ($locations as $location): ?>
                  <tr data-cache-location-id="<?= h((string)$location['id']) ?>">
                    <td><input type="checkbox" class="form-check-input cache-location-check" value="<?= h((string)$location['id']) ?>" aria-label="<?= h(sc_t('systemCache_select_location', 'Select cache location')) ?>"></td>
                    <td><div class="system-cache-location"><span class="sc-location-icon"><i class="ri-folder-6-line"></i></span><div><strong><?= h((string)$location['location']) ?></strong><small><?= h(sc_t('systemCache_location_standard', 'Standard project cache')) ?></small></div></div></td>
                    <td data-cache-files><?= h((string)$location['files']) ?></td>
                    <td data-cache-size data-cache-bytes="<?= h((string)$location['bytes']) ?>"><?= h((string)$location['size']) ?></td>
                    <td data-cache-modified><?= h((string)$location['last_modified']) ?></td>
                  </tr>
                <?php endforeach; ?>
                <?php if ($locations === []): ?>
                  <tr><td colspan="5" class="text-center text-muted py-4"><?= h(sc_t('systemCache_empty', 'No standard cache locations were found.')) ?></td></tr>
                <?php endif; ?>
                </tbody>
              </table>
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
window.SystemCachePageData = <?= json_encode([
  'actionUrl' => base_url('ajax/system-cache-action.php'),
  'text' => [
    'confirmTitle' => sc_t('systemCache_confirm_title', 'Clear System Cache?'),
    'cancel' => sc_t('systemCache_confirm_cancel', 'Cancel'),
    'clear' => sc_t('systemCache_confirm_clear', 'Clear Cache'),
    'close' => sc_t('systemCache_action_close', 'Close'),
    'success' => sc_t('systemCache_success_title', 'Cache Cleared'),
    'partial' => sc_t('systemCache_partial_title', 'Cache partially cleared'),
    'error' => sc_t('config_js_ralat', 'Error'),
    'loading' => sc_t('systemCache_loading', 'Clearing cache...'),
    'note' => sc_t('systemCache_success_note', 'A page refresh is normally sufficient.'),
    'selectedNone' => sc_t('systemCache_selected_none', 'No locations selected'),
    'selectedOne' => sc_t('systemCache_selected_one', '1 location selected'),
    'selectedMany' => sc_t('systemCache_selected_many', '{count} locations selected'),
    'selectedConfirm' => sc_t('systemCache_confirm_selected_text', 'The selected cache locations will be cleared.'),
    'allConfirm' => sc_t('systemCache_confirm_all_text', 'All discovered cache locations, OPcache and APCu will be cleared.'),
    'resultFiles' => sc_t('systemCache_result_files', 'Files removed'),
    'resultSize' => sc_t('systemCache_result_size', 'Size freed'),
    'resultLocations' => sc_t('systemCache_result_locations', 'Locations cleared'),
    'resultErrors' => sc_t('systemCache_result_errors', 'Items not cleared'),
  ],
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
</script>
<script src="<?= h(base_url('assets/js/pages/system-cache.js')) ?>?v=<?= h($version) ?>"></script>
</body>
</html>
