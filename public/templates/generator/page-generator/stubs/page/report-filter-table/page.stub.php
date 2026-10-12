<?php
declare(strict_types=1);

$NEED_DATATABLES = true;

require_once __DIR__ . '/../includes/init.php';
require_login();

require_once __DIR__ . '/../controllers/__CONTROLLER_CLASS__.php';

$controllerClass = '__CONTROLLER_CLASS__';
$controller = new $controllerClass();
$lang = $controller->lang ?? ($_SESSION['lang'] ?? 'ms');
$filters = is_array($controller->filters ?? null) ? $controller->filters : [];
$rows = is_array($controller->rows ?? null) ? $controller->rows : [];

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
$version = (string)($_ENV['APP_ASSET_VER'] ?? date('ymdHis'));
?>
<!DOCTYPE html>
<html lang="<?= h($lang) ?>">
<head>
  <?php include __DIR__ . '/../includes/head.php'; ?>
  <link href="<?= base_url('assets/css/datatables-standard.css') ?>?v=<?= h($version) ?>" rel="stylesheet">
  <link href="<?= base_url('assets/css/pages/__PAGE_SLUG__.css') ?>?v=<?= h($version) ?>" rel="stylesheet">
  <script src="<?= base_url('assets/js/helpers/datatables-standard.js') ?>?v=<?= h($version) ?>"></script>
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
            <div class="card report-filter-card">
              <div class="card-body">
                <div class="report-filter-header">
                  <div>
                    <h5 class="card-title mb-1"><?= h(t('__PAGE_KEY_PREFIX___filter_title', 'Filter Criteria')) ?></h5>
                    <p class="text-muted mb-0"><?= h(t('__PAGE_KEY_PREFIX___filter_subtitle', 'Use the controls below to refine the result table and demonstrate a report-style search workflow.')) ?></p>
                  </div>
                  <div class="report-filter-badge">
                    <i class="ri-filter-3-line"></i>
                    <span><?= h(t('__PAGE_KEY_PREFIX___table_mode', 'Report Search')) ?></span>
                  </div>
                </div>

                <form class="row g-3 report-filter-form" id="reportFilterForm" novalidate>
                  <div class="col-md-3">
                    <label class="form-label"><?= h(t('__PAGE_KEY_PREFIX___field_from_date', 'From Date')) ?></label>
                    <input type="date" class="form-control" name="from_date" value="<?= h((string)($filters['from_date'] ?? '')) ?>">
                  </div>
                  <div class="col-md-3">
                    <label class="form-label"><?= h(t('__PAGE_KEY_PREFIX___field_to_date', 'To Date')) ?></label>
                    <input type="date" class="form-control" name="to_date" value="<?= h((string)($filters['to_date'] ?? '')) ?>">
                  </div>
                  <div class="col-md-3">
                    <label class="form-label"><?= h(t('__PAGE_KEY_PREFIX___field_status', 'Status')) ?></label>
                    <select class="form-select" name="status">
                      <option value="all"><?= h(t('__PAGE_KEY_PREFIX___status_all', 'All Statuses')) ?></option>
                      <option value="completed" <?= (($filters['status'] ?? '') === 'completed') ? 'selected' : '' ?>><?= h(t('__PAGE_KEY_PREFIX___status_completed', 'Completed')) ?></option>
                      <option value="in_review" <?= (($filters['status'] ?? '') === 'in_review') ? 'selected' : '' ?>><?= h(t('__PAGE_KEY_PREFIX___status_in_review', 'In Review')) ?></option>
                    </select>
                  </div>
                  <div class="col-md-3">
                    <label class="form-label"><?= h(t('__PAGE_KEY_PREFIX___field_category', 'Category')) ?></label>
                    <select class="form-select" name="category">
                      <option value="all"><?= h(t('__PAGE_KEY_PREFIX___category_all', 'All Categories')) ?></option>
                      <option value="operational" <?= (($filters['category'] ?? '') === 'operational') ? 'selected' : '' ?>><?= h(t('__PAGE_KEY_PREFIX___category_operational', 'Operational')) ?></option>
                      <option value="analytics" <?= (($filters['category'] ?? '') === 'analytics') ? 'selected' : '' ?>><?= h(t('__PAGE_KEY_PREFIX___category_analytics', 'Analytics')) ?></option>
                      <option value="security" <?= (($filters['category'] ?? '') === 'security') ? 'selected' : '' ?>><?= h(t('__PAGE_KEY_PREFIX___category_security', 'Security')) ?></option>
                    </select>
                  </div>
                  <div class="col-lg-9">
                    <label class="form-label"><?= h(t('__PAGE_KEY_PREFIX___field_keyword', 'Keyword')) ?></label>
                    <input type="text" class="form-control" name="keyword" value="<?= h((string)($filters['keyword'] ?? '')) ?>" placeholder="<?= h(t('__PAGE_KEY_PREFIX___field_keyword_placeholder', 'Enter report keyword or reference number')) ?>">
                  </div>
                  <div class="col-lg-3">
                    <label class="form-label d-block">&nbsp;</label>
                    <div class="d-flex justify-content-lg-end gap-2 flex-wrap report-filter-actions">
                      <button type="button" class="btn btn-light report-btn report-btn-reset" id="btnReportReset"><?= h(t('__PAGE_KEY_PREFIX___btn_reset', 'Reset')) ?></button>
                      <button type="button" class="btn btn-primary report-btn report-btn-search" id="btnReportSearch"><?= h(t('__PAGE_KEY_PREFIX___btn_search', 'Search')) ?></button>
                    </div>
                  </div>
                </form>
              </div>
            </div>
          </div>

          <div class="col-12">
            <div class="card report-table-card">
              <div class="card-body">
                <div class="report-table-header">
                  <div>
                    <h5 class="card-title mb-1"><?= h(t('__PAGE_KEY_PREFIX___table_title', 'Result Table')) ?></h5>
                    <p class="text-muted mb-0"><?= h(t('__PAGE_KEY_PREFIX___table_subtitle', 'The result section stays on the same page so developers can build reporting workflows without extra routing complexity.')) ?></p>
                  </div>
                </div>
                <div class="table-responsive dt-standard">
                  <table class="table table-bordered align-middle w-100" id="reportDT">
                    <thead>
                      <tr>
                        <th class="col-no"><?= h(t('__PAGE_KEY_PREFIX___col_no', 'No.')) ?></th>
                        <th class="col-reference"><?= h(t('__PAGE_KEY_PREFIX___col_reference_no', 'Reference No.')) ?></th>
                        <th class="col-name"><?= h(t('__PAGE_KEY_PREFIX___col_name', 'Report Name')) ?></th>
                        <th class="col-category"><?= h(t('__PAGE_KEY_PREFIX___col_category', 'Category')) ?></th>
                        <th class="col-status"><?= h(t('__PAGE_KEY_PREFIX___col_status', 'Status')) ?></th>
                        <th class="col-updated"><?= h(t('__PAGE_KEY_PREFIX___col_updated_at', 'Last Updated')) ?></th>
                      </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($rows as $row): ?>
                      <?php $statusClass = strtolower((string)($row['status'] ?? 'completed')) === 'completed' ? 'is-completed' : 'is-pending'; ?>
                      <tr>
                        <td class="col-no"></td>
                        <td class="col-reference"><span class="truncate-1line" data-bs-toggle="tooltip" data-bs-custom-class="template-tooltip" title="<?= h((string)($row['reference_no'] ?? '')) ?>"><?= h((string)($row['reference_no'] ?? '')) ?></span></td>
                        <td class="col-name"><span class="truncate-1line" data-bs-toggle="tooltip" data-bs-custom-class="template-tooltip" title="<?= h((string)($row['name'] ?? '')) ?>"><?= h((string)($row['name'] ?? '')) ?></span></td>
                        <td class="col-category"><span class="truncate-1line" data-bs-toggle="tooltip" data-bs-custom-class="template-tooltip" title="<?= h((string)($row['category'] ?? '')) ?>"><?= h((string)($row['category'] ?? '')) ?></span></td>
                        <td class="col-status"><span class="report-status-chip <?= h($statusClass) ?>" data-bs-toggle="tooltip" data-bs-custom-class="template-tooltip" title="<?= h((string)($row['status'] ?? '')) ?>"><?= h((string)($row['status'] ?? '')) ?></span></td>
                        <td class="col-updated"><span class="truncate-1line" data-bs-toggle="tooltip" data-bs-custom-class="template-tooltip" title="<?= h((string)($row['updated_at'] ?? '')) ?>"><?= h((string)($row['updated_at'] ?? '')) ?></span></td>
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
    </div>
    <?php include __DIR__ . '/../includes/footer.php'; ?>
  </div>
</div>

<?php include __DIR__ . '/../includes/script.php'; ?>
<script>
(function () {
  document.addEventListener('DOMContentLoaded', function () {
    if (!window.jQuery || !jQuery.fn || !jQuery.fn.DataTable) {
      return;
    }

    var dt = jQuery('#reportDT').DataTable({
      pageLength: 10,
      lengthChange: true,
      lengthMenu: [10, 25, 50, 100],
      ordering: true,
      order: [[1, 'asc']],
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
          window.DataTableStandard.decorate('#reportDT', {
            searchPlaceholder: <?= json_encode('Search') ?>
          });
        }
      }
    });

    dt.on('order.dt search.dt draw.dt', function () {
      var info = dt.page.info();
      dt.column(0, { search: 'applied', order: 'applied', page: 'current' }).nodes().each(function (cell, index) {
        cell.textContent = info.start + index + 1;
      });
    }).draw();

    if (window.bootstrap) {
      document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach(function (element) {
        new bootstrap.Tooltip(element);
      });
    }

    var filterForm = document.getElementById('reportFilterForm');
    var resetButton = document.getElementById('btnReportReset');
    var searchButton = document.getElementById('btnReportSearch');

    if (resetButton && filterForm) {
      resetButton.addEventListener('click', function () {
        filterForm.reset();
        if (window.Swal) {
          Swal.fire({
            icon: 'info',
            title: <?= json_encode(t('__PAGE_KEY_PREFIX___reset_title', 'Filter Reset')) ?>,
            text: <?= json_encode(t('__PAGE_KEY_PREFIX___reset_text', 'Sample filter values were reset without sending a backend request.')) ?>,
            confirmButtonText: <?= json_encode(t('__PAGE_KEY_PREFIX___btn_ok', 'OK')) ?>
          });
        }
      });
    }

    if (searchButton) {
      searchButton.addEventListener('click', function () {
        if (window.Swal) {
          Swal.fire({
            icon: 'success',
            title: <?= json_encode(t('__PAGE_KEY_PREFIX___search_title', 'Sample Search Complete')) ?>,
            text: <?= json_encode(t('__PAGE_KEY_PREFIX___search_text', 'This sample filter flow completed successfully without sending any request to the backend.')) ?>,
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
