<?php
declare(strict_types=1);

$NEED_DATATABLES = true;

require_once __DIR__ . '/../includes/init.php';
require_login();

require_once __DIR__ . '/../controllers/__CONTROLLER_CLASS__.php';

$controllerClass = '__CONTROLLER_CLASS__';
$controller = new $controllerClass();
$lang = $controller->lang ?? ($_SESSION['lang'] ?? 'ms');
$rows = $controller->rows ?? [];

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
      <div class="container-fluid">
        <div class="row mb-3">
          <div class="col-12">
            <div class="page-title-box d-flex justify-content-between align-items-center flex-wrap">
              <h4 class="page-title"><i class="__PAGE_ICON__ me-1"></i> <?= h($PAGE_TITLE) ?></h4>
              <div class="page-title-right">
                <ol class="breadcrumb m-0">
                  <li class="breadcrumb-item">
                    <a href="dashboard.php">
                      <i class="ri-home-4-line align-middle me-1"></i> <?= h(__('breadcrumb_home')) ?>
                    </a>
                  </li>
                  <li class="breadcrumb-item active"><?= h($PAGE_TITLE) ?></li>
                </ol>
              </div>
            </div>
          </div>
        </div>

        <div class="row">
          <div class="col-12">
            <div class="card basic-datatable-card">
              <div class="card-body">
                <div class="basic-datatable-intro">
                  <div>
                    <h5 class="card-title mb-1"><?= h(t('__PAGE_KEY_PREFIX___table_title', 'Listing Overview')) ?></h5>
                    <p class="text-muted mb-0"><?= h(t('__PAGE_KEY_PREFIX___table_subtitle', 'Use this generated table as the starting point for read-only listing pages and lightweight search views.')) ?></p>
                  </div>
                  <div class="basic-datatable-chip">
                    <i class="ri-table-line"></i>
                    <span><?= h(t('__PAGE_KEY_PREFIX___table_mode', 'List Only')) ?></span>
                  </div>
                </div>

                <div class="table-responsive dt-standard">
                  <table class="table table-bordered align-middle w-100" id="basicDT">
                    <thead>
                      <tr>
                        <th class="col-bil"><?= h(t('__PAGE_KEY_PREFIX___col_no', 'No.')) ?></th>
                        <th class="col-name"><?= h(t('__PAGE_KEY_PREFIX___col_name', 'Name')) ?></th>
                        <th class="col-description"><?= h(t('__PAGE_KEY_PREFIX___col_description', 'Description')) ?></th>
                        <th class="col-status"><?= h(t('__PAGE_KEY_PREFIX___col_status', 'Status')) ?></th>
                        <th class="col-updated"><?= h(t('__PAGE_KEY_PREFIX___col_updated_at', 'Last Updated')) ?></th>
                      </tr>
                    </thead>
                    <tbody>
                      <?php foreach ($rows as $index => $row): ?>
                        <?php
                          $status = strtolower((string)($row['status'] ?? 'active'));
                          $statusLabel = $status === 'inactive'
                            ? t('__PAGE_KEY_PREFIX___status_inactive', 'Inactive')
                            : t('__PAGE_KEY_PREFIX___status_active', 'Active');
                          $statusClass = $status === 'inactive' ? 'is-inactive' : 'is-active';
                        ?>
                        <tr>
                          <td class="col-bil"></td>
                          <td class="col-name">
                            <?php $nameLine = trim((string)($row['name'] ?? '') . ' | ' . (string)($row['code'] ?? '')); ?>
                            <span class="truncate-1line" data-bs-toggle="tooltip" data-bs-custom-class="template-tooltip" title="<?= h($nameLine) ?>"><?= h($nameLine) ?></span>
                          </td>
                          <td class="col-description"><span class="truncate-1line" data-bs-toggle="tooltip" data-bs-custom-class="template-tooltip" title="<?= h((string)($row['description'] ?? '')) ?>"><?= h((string)($row['description'] ?? '')) ?></span></td>
                          <td class="col-status"><span class="basic-status-chip <?= h($statusClass) ?>" data-bs-toggle="tooltip" data-bs-custom-class="template-tooltip" title="<?= h($statusLabel) ?>"><?= h($statusLabel) ?></span></td>
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
(function(){
  document.addEventListener('DOMContentLoaded', function(){
    if (!window.jQuery || !jQuery.fn || !jQuery.fn.DataTable) {
      return;
    }

    const dt = jQuery('#basicDT').DataTable({
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
      columnDefs: [
        { targets: 0, orderable: false, searchable: false, width: 56 }
      ],
      rowCallback: function(row, data, displayIndex){
        const api = this.api();
        const info = api.page.info();
        jQuery('td:eq(0)', row).text(info.start + displayIndex + 1);
      },
      initComplete: function() {
        if (window.DataTableStandard && typeof window.DataTableStandard.decorate === 'function') {
          window.DataTableStandard.decorate('#basicDT', {
            searchPlaceholder: <?= json_encode('Search') ?>
          });
        }
      }
    });

    dt.on('order.dt search.dt draw.dt', function () {
      const info = dt.page.info();
      dt.column(0, { search: 'applied', order: 'applied', page: 'current' }).nodes().each(function (cell, index) {
        cell.textContent = info.start + index + 1;
      });
    }).draw();

    if (window.bootstrap) {
      document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach(function (element) {
        new bootstrap.Tooltip(element);
      });
    }
  });
})();
</script>
</body>
</html>
