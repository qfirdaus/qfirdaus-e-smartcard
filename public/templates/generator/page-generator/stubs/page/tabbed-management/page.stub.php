<?php
declare(strict_types=1);

$NEED_DATATABLES = true;

require_once __DIR__ . '/../includes/init.php';
require_login();

require_once __DIR__ . '/../controllers/__CONTROLLER_CLASS__.php';

$controllerClass = '__CONTROLLER_CLASS__';
$controller = new $controllerClass();
$lang = $controller->lang ?? ($_SESSION['lang'] ?? 'ms');
$overview = is_array($controller->overview ?? null) ? $controller->overview : [];
$configuration = is_array($controller->configuration ?? null) ? $controller->configuration : [];
$configurationRows = is_array($controller->configurationRows ?? null) ? $controller->configurationRows : [];
$historyRows = is_array($controller->historyRows ?? null) ? $controller->historyRows : [];
$version = (string)($_ENV['APP_ASSET_VER'] ?? date('ymdHis'));

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
<html lang="<?= h($lang) ?>" data-bs-theme="<?= h($_SESSION['theme.layout'] ?? 'light') ?>">
<head>
  <?php include __DIR__ . '/../includes/head.php'; ?>
  <link href="<?= base_url('assets/css/datatables-standard.css') ?>?v=<?= h($version) ?>" rel="stylesheet">
  <link href="<?= base_url('assets/css/pages/__PAGE_SLUG__.css') ?>?v=<?= h($version) ?>" rel="stylesheet">
</head>
<body data-topbar-color="<?= h($_SESSION['theme.topbar'] ?? 'light') ?>" data-menu-color="<?= h($_SESSION['theme.menu'] ?? 'light') ?>" data-layout="vertical" data-sidebar-size="default" class="loading">
<div class="wrapper">
  <?php include __DIR__ . '/../includes/topbar.php'; ?>
  <?php include __DIR__ . '/../includes/sidebar.php'; ?>

  <div class="content-page">
    <div class="content">
      <div class="container-fluid">
        <div class="row mb-3">
          <div class="col-12">
            <div class="page-title-box d-flex justify-content-between align-items-center flex-wrap">
              <h4 class="page-title">
                <i class="__PAGE_ICON__ me-1"></i>
                <?= h($PAGE_TITLE) ?>
              </h4>
              <div class="page-title-right">
                <ol class="breadcrumb m-0">
                  <li class="breadcrumb-item">
                    <a href="dashboard.php">
                      <i class="ri-home-4-line align-middle me-1"></i>
                      <?= h(__('breadcrumb_home')) ?>
                    </a>
                  </li>
                  <li class="breadcrumb-item active"><?= h($PAGE_TITLE) ?></li>
                </ol>
              </div>
            </div>
          </div>
        </div>

        <div class="card border-0 shadow-sm profile-card">
          <ul class="nav nav-tabs profile-tabs" role="tablist" aria-label="<?= h(t('__PAGE_KEY_PREFIX___module_kicker', 'Tabbed Workspace')) ?>">
            <li class="nav-item">
              <a class="nav-link active" data-bs-toggle="tab" href="#overview-tab-pane" role="tab">
                <i class="ri-layout-grid-line me-1"></i> <?= h(t('__PAGE_KEY_PREFIX___tab_overview', 'Overview')) ?>
              </a>
            </li>
            <li class="nav-item">
              <a class="nav-link" data-bs-toggle="tab" href="#configuration-tab-pane" role="tab">
                <i class="ri-settings-3-line me-1"></i> <?= h(t('__PAGE_KEY_PREFIX___tab_configuration', 'Configuration')) ?>
              </a>
            </li>
            <li class="nav-item">
              <a class="nav-link" data-bs-toggle="tab" href="#history-tab-pane" role="tab">
                <i class="ri-file-list-3-line me-1"></i> <?= h(t('__PAGE_KEY_PREFIX___tab_history', 'History')) ?>
              </a>
            </li>
          </ul>

          <div class="tab-content p-4">
            <div class="tab-pane fade show active" id="overview-tab-pane" role="tabpanel">
              <div class="profile-overview-grid">
                <div class="profile-panel">
                  <div class="profile-panel-header">
                    <div>
                      <h5 class="profile-panel-title"><?= h(t('__PAGE_KEY_PREFIX___overview_title', 'Overview Summary')) ?></h5>
                      <p class="profile-panel-subtitle"><?= h((string)($overview['subtitle'] ?? '')) ?></p>
                    </div>
                    <span class="profile-lang-badge">
                      <i class="ri-shield-user-line"></i>
                      <?= h((string)($overview['status'] ?? t('__PAGE_KEY_PREFIX___status_active', 'Active'))) ?>
                    </span>
                  </div>
                  <div class="profile-panel-body">
                    <div class="profile-identity-card">
                      <div class="profile-identity-shell profile-identity-shell--no-avatar">
                        <div class="profile-identity-main">
                          <div class="profile-identity-eyebrow"><?= h(t('__PAGE_KEY_PREFIX___module_kicker', 'Tabbed Workspace')) ?></div>
                          <div class="profile-identity-name"><?= h((string)($overview['title'] ?? $PAGE_TITLE)) ?></div>
                          <div class="profile-identity-meta"><?= h((string)($overview['description'] ?? '')) ?></div>
                          <div class="profile-identity-chips">
                            <span class="chip"><i class="ri-price-tag-3-line"></i><?= h((string)($overview['category'] ?? '—')) ?></span>
                            <span class="chip"><i class="ri-user-settings-line"></i><?= h((string)($overview['owner'] ?? '—')) ?></span>
                          </div>
                        </div>
                      </div>
                    </div>

                    <div class="profile-stat-row">
                      <div class="profile-stat-card">
                        <div class="profile-stat-label">
                          <i class="ri-hashtag"></i><?= h(t('__PAGE_KEY_PREFIX___label_module_id', 'Module ID')) ?>
                        </div>
                        <div class="profile-stat-value"><?= h((string)($overview['module_id'] ?? '—')) ?></div>
                      </div>
                      <div class="profile-stat-card">
                        <div class="profile-stat-label">
                          <i class="ri-fingerprint-line"></i><?= h(t('__PAGE_KEY_PREFIX___label_reference_no', 'Reference No.')) ?>
                        </div>
                        <div class="profile-stat-value"><?= h((string)($overview['reference_no'] ?? '—')) ?></div>
                      </div>
                    </div>

                    <div class="profile-detail-list">
                      <div class="profile-detail-item">
                        <div class="profile-detail-icon"><i class="ri-user-settings-line"></i></div>
                        <div>
                          <div class="profile-detail-label"><?= h(t('__PAGE_KEY_PREFIX___field_owner', 'Owner')) ?></div>
                          <div class="profile-detail-value"><?= h((string)($overview['owner'] ?? '—')) ?></div>
                        </div>
                      </div>
                      <div class="profile-detail-item">
                        <div class="profile-detail-icon"><i class="ri-price-tag-3-line"></i></div>
                        <div>
                          <div class="profile-detail-label"><?= h(t('__PAGE_KEY_PREFIX___label_category', 'Category')) ?></div>
                          <div class="profile-detail-value"><?= h((string)($overview['category'] ?? '—')) ?></div>
                        </div>
                      </div>
                      <div class="profile-detail-item">
                        <div class="profile-detail-icon"><i class="ri-time-line"></i></div>
                        <div>
                          <div class="profile-detail-label"><?= h(t('__PAGE_KEY_PREFIX___label_updated_at', 'Last Updated')) ?></div>
                          <div class="profile-detail-value"><?= h((string)($overview['updated_at'] ?? '—')) ?></div>
                        </div>
                      </div>
                    </div>
                  </div>
                </div>

                <div class="profile-lang-panel">
                  <div class="card border-0 profile-lang-card h-100">
                    <div class="card-header py-3 px-4">
                      <div class="d-flex align-items-center justify-content-between gap-3 flex-wrap">
                        <h5 class="mb-0 fw-semibold text-primary">
                          <i class="ri-layout-grid-fill me-2"></i><?= h(t('__PAGE_KEY_PREFIX___overview_quick_title', 'Quick Notes')) ?>
                        </h5>
                        <span class="profile-lang-badge">
                          <i class="ri-global-line"></i><?= h(strtoupper((string)$lang)) ?>
                        </span>
                      </div>
                    </div>
                    <div class="card-body p-4">
                      <div class="mb-3">
                        <div class="profile-panel-subtitle mt-0">
                          <?= h(t('__PAGE_KEY_PREFIX___overview_quick_text', 'This tab is suitable for module summary, KPI highlights, and short guidance before users move into more specific sections.')) ?>
                        </div>
                      </div>
                      <div class="profile-detail-list">
                        <?php foreach ((array)($overview['chips'] ?? []) as $chip): ?>
                          <div class="profile-detail-item">
                            <div class="profile-detail-icon"><i class="ri-checkbox-circle-line"></i></div>
                            <div>
                              <div class="profile-detail-label"><?= h(t('__PAGE_KEY_PREFIX___label_highlight', 'Highlight')) ?></div>
                              <div class="profile-detail-value"><?= h((string)$chip) ?></div>
                            </div>
                          </div>
                        <?php endforeach; ?>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            </div>

            <div class="tab-pane fade" id="configuration-tab-pane" role="tabpanel">
              <div class="profile-overview-grid profile-overview-grid--single">
                <div class="profile-panel">
                  <div class="profile-panel-header">
                    <div>
                      <h5 class="profile-panel-title"><?= h(t('__PAGE_KEY_PREFIX___configuration_title', 'Configuration Setup')) ?></h5>
                      <p class="profile-panel-subtitle"><?= h(t('__PAGE_KEY_PREFIX___configuration_subtitle', 'Use this tab for grouped settings, module parameters, or page-level configuration fields.')) ?></p>
                    </div>
                    <span class="profile-lang-badge">
                      <i class="ri-settings-3-line"></i><?= h(t('__PAGE_KEY_PREFIX___tab_configuration', 'Configuration')) ?>
                    </span>
                  </div>
                  <div class="profile-panel-body">
                    <form id="tabbedManagementForm" class="row g-3" novalidate>
                      <div class="col-md-6">
                        <label class="form-label fw-semibold"><?= h(t('__PAGE_KEY_PREFIX___field_module_name', 'Module Name')) ?></label>
                        <input type="text" class="form-control" name="module_name" value="<?= h((string)($configuration['module_name'] ?? '')) ?>">
                      </div>
                      <div class="col-md-6">
                        <label class="form-label fw-semibold"><?= h(t('__PAGE_KEY_PREFIX___field_layout_mode', 'Layout Mode')) ?></label>
                        <?php $layoutMode = (string)($configuration['layout_mode'] ?? 'Standard'); ?>
                        <select class="form-select" name="layout_mode">
                          <option value="Standard" <?= $layoutMode === 'Standard' ? 'selected' : '' ?>><?= h(t('__PAGE_KEY_PREFIX___layout_standard', 'Standard')) ?></option>
                          <option value="Compact" <?= $layoutMode === 'Compact' ? 'selected' : '' ?>><?= h(t('__PAGE_KEY_PREFIX___layout_compact', 'Compact')) ?></option>
                          <option value="Expanded" <?= $layoutMode === 'Expanded' ? 'selected' : '' ?>><?= h(t('__PAGE_KEY_PREFIX___layout_expanded', 'Expanded')) ?></option>
                        </select>
                      </div>
                      <div class="col-md-6">
                        <label class="form-label fw-semibold"><?= h(t('__PAGE_KEY_PREFIX___field_status', 'Status')) ?></label>
                        <?php $configStatus = (string)($configuration['status'] ?? 'active'); ?>
                        <select class="form-select" name="status">
                          <option value="active" <?= $configStatus === 'active' ? 'selected' : '' ?>><?= h(t('__PAGE_KEY_PREFIX___status_active', 'Active')) ?></option>
                          <option value="inactive" <?= $configStatus === 'inactive' ? 'selected' : '' ?>><?= h(t('__PAGE_KEY_PREFIX___status_inactive', 'Inactive')) ?></option>
                        </select>
                      </div>
                      <div class="col-md-6">
                        <label class="form-label fw-semibold"><?= h(t('__PAGE_KEY_PREFIX___field_owner', 'Owner')) ?></label>
                        <input type="text" class="form-control" name="owner" value="<?= h((string)($configuration['owner'] ?? '')) ?>">
                      </div>
                      <div class="col-md-6">
                        <label class="form-label fw-semibold"><?= h(t('__PAGE_KEY_PREFIX___field_language', 'Language')) ?></label>
                        <input type="text" class="form-control" name="language" value="<?= h((string)($configuration['language'] ?? '')) ?>">
                      </div>
                      <div class="col-md-6">
                        <label class="form-label fw-semibold"><?= h(t('__PAGE_KEY_PREFIX___field_visibility', 'Visibility')) ?></label>
                        <input type="text" class="form-control" name="visibility" value="<?= h((string)($configuration['visibility'] ?? '')) ?>">
                      </div>
                      <div class="col-12">
                        <label class="form-label fw-semibold"><?= h(t('__PAGE_KEY_PREFIX___field_description', 'Description')) ?></label>
                        <textarea class="form-control" name="description" rows="4"><?= h((string)($configuration['description'] ?? '')) ?></textarea>
                      </div>
                      <div class="col-12 d-flex justify-content-end pt-1">
                        <button type="button" class="btn btn-light tab-action-btn me-2" id="btnTabCancel">
                          <i class="ri-close-line me-1"></i><?= h(t('__PAGE_KEY_PREFIX___btn_cancel', 'Cancel')) ?>
                        </button>
                        <button type="button" class="btn btn-success tab-action-btn" id="btnTabSave">
                          <i class="ri-save-3-line me-1"></i><?= h(t('__PAGE_KEY_PREFIX___btn_save', 'Save')) ?>
                        </button>
                      </div>
                    </form>
                  </div>
                </div>
              </div>
            </div>

            <div class="tab-pane fade" id="history-tab-pane" role="tabpanel">
              <div class="table-responsive dt-standard">
                <table class="table table-bordered align-middle mb-0" id="historyTable">
                  <thead>
                    <tr>
                      <th class="profile-table-col-no text-center">No.</th>
                      <th class="profile-table-col-date"><?= h(t('__PAGE_KEY_PREFIX___col_datetime', 'Date & Time')) ?></th>
                      <th><?= h(t('__PAGE_KEY_PREFIX___col_actor', 'Actor')) ?></th>
                      <th><?= h(t('__PAGE_KEY_PREFIX___col_activity', 'Activity')) ?></th>
                      <th class="text-center"><?= h(t('__PAGE_KEY_PREFIX___col_result', 'Result')) ?></th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php foreach ($historyRows as $row): ?>
                      <tr>
                        <td class="text-center"></td>
                        <td><?= h((string)($row['datetime'] ?? '')) ?></td>
                        <td><?= h((string)($row['actor'] ?? '')) ?></td>
                        <td><?= h((string)($row['activity'] ?? '')) ?></td>
                        <td class="text-center"><?= h((string)($row['result'] ?? '')) ?></td>
                      </tr>
                    <?php endforeach; ?>
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
<script src="<?= base_url('assets/js/helpers/datatables-standard.js') ?>?v=<?= h($version) ?>"></script>
<script>
(function () {
  function initSimpleTable(selector) {
    if (!window.jQuery || !jQuery.fn || !jQuery.fn.DataTable) {
      return null;
    }

    var table = jQuery(selector).DataTable({
      pageLength: 10,
      lengthChange: true,
      lengthMenu: [10, 25, 50, 100],
      ordering: true,
      order: [[1, 'desc']],
      autoWidth: false,
      scrollX: false,
      dom:
        '<"row mb-2"<"col-sm-12 col-md-6 dt-top-left"l><"col-sm-12 col-md-6 d-flex justify-content-md-end dt-top-right"f>>' +
        't' +
        '<"dt-bottom-row mt-2 d-flex justify-content-between align-items-center"<"dt-info-left"i><"dt-paging-right d-flex justify-content-end"p>>',
      language: {
        lengthMenu: <?= json_encode('Show _MENU_ records') ?>,
        search: '',
        info: <?= json_encode('Showing _START_ to _END_ of _TOTAL_ records') ?>,
        infoEmpty: <?= json_encode('Showing 0 to 0 of 0 records') ?>,
        emptyTable: <?= json_encode('No records') ?>,
        paginate: {
          previous: <?= json_encode('Previous') ?>,
          next: <?= json_encode('Next') ?>
        },
        zeroRecords: <?= json_encode('No matching records found') ?>
      },
      columnDefs: [{ targets: 0, orderable: false, searchable: false, width: 56 }],
      rowCallback: function (row, data, displayIndex) {
        var info = this.api().page.info();
        jQuery('td:eq(0)', row).text(info.start + displayIndex + 1);
      },
      initComplete: function () {
        if (window.DataTableStandard && typeof window.DataTableStandard.decorate === 'function') {
          window.DataTableStandard.decorate(selector, { searchPlaceholder: <?= json_encode('Search') ?> });
        }
      }
    });

    table.on('order.dt search.dt draw.dt', function () {
      var info = table.page.info();
      table.column(0, { search: 'applied', order: 'applied', page: 'current' }).nodes().each(function (cell, index) {
        cell.textContent = info.start + index + 1;
      });
    }).draw();

    return table;
  }

  document.addEventListener('DOMContentLoaded', function () {
    initSimpleTable('#historyTable');

    var saveButton = document.getElementById('btnTabSave');
    var cancelButton = document.getElementById('btnTabCancel');
    var form = document.getElementById('tabbedManagementForm');

    if (saveButton) {
      saveButton.addEventListener('click', function () {
        if (window.Swal) {
          Swal.fire({
            icon: 'success',
            title: <?= json_encode(t('__PAGE_KEY_PREFIX___save_title', 'Sample Save Complete')) ?>,
            text: <?= json_encode(t('__PAGE_KEY_PREFIX___save_text', 'This tab configuration sample completed successfully without sending data to the backend.')) ?>,
            confirmButtonText: <?= json_encode(t('__PAGE_KEY_PREFIX___btn_ok', 'OK')) ?>
          });
        }
      });
    }

    if (cancelButton && form) {
      cancelButton.addEventListener('click', function () {
        form.reset();
        if (window.Swal) {
          Swal.fire({
            icon: 'info',
            title: <?= json_encode(t('__PAGE_KEY_PREFIX___reset_title', 'Changes Reset')) ?>,
            text: <?= json_encode(t('__PAGE_KEY_PREFIX___reset_text', 'Sample tab inputs were reset without any backend action.')) ?>,
            confirmButtonText: <?= json_encode(t('__PAGE_KEY_PREFIX___btn_ok', 'OK')) ?>
          });
        }
      });
    }
  });
})();
</script>
</body>
</html>
