<?php
declare(strict_types=1);

$NEED_DATATABLES = true;

require_once __DIR__ . '/../includes/init.php';
require_login();

require_once __DIR__ . '/../controllers/__CONTROLLER_CLASS__.php';

$controllerClass = '__CONTROLLER_CLASS__';
$controller = new $controllerClass();
$lang = $controller->lang ?? ($_SESSION['lang'] ?? 'ms');
$rows = is_array($controller->rows ?? null) ? $controller->rows : [];
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

        <div class="row">
          <div class="col-12">
            <div class="card expandable-datatable-card">
              <div class="card-body">
                <div class="expandable-intro">
                  <div>
                    <h5 class="card-title mb-1"><?= h(t('__PAGE_KEY_PREFIX___table_title', 'Expandable Listing')) ?></h5>
                    <p class="text-muted mb-0"><?= h(t('__PAGE_KEY_PREFIX___table_subtitle', 'Click a row action to expand extra information inline without leaving the table.')) ?></p>
                  </div>
                </div>
                <div class="table-responsive dt-standard">
                  <table class="table table-bordered align-middle w-100" id="expandableDT">
                    <thead>
                      <tr>
                        <th class="col-no"><?= h(t('__PAGE_KEY_PREFIX___col_no', 'No.')) ?></th>
                        <th class="col-reference"><?= h(t('__PAGE_KEY_PREFIX___col_reference_no', 'Reference No.')) ?></th>
                        <th class="col-title"><?= h(t('__PAGE_KEY_PREFIX___col_title', 'Title')) ?></th>
                        <th class="col-status"><?= h(t('__PAGE_KEY_PREFIX___col_status', 'Status')) ?></th>
                        <th class="col-updated"><?= h(t('__PAGE_KEY_PREFIX___col_updated_at', 'Last Updated')) ?></th>
                        <th class="col-actions"><?= h(t('__PAGE_KEY_PREFIX___col_actions', 'Actions')) ?></th>
                      </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($rows as $index => $row): ?>
                      <tr class="expandable-row" data-row='<?= h(json_encode($row, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) ?>'>
                        <td class="col-no"></td>
                        <td class="col-reference"><span class="truncate-1line" data-bs-toggle="tooltip" data-bs-custom-class="template-tooltip" title="<?= h((string)($row['reference_no'] ?? '')) ?>"><?= h((string)($row['reference_no'] ?? '')) ?></span></td>
                        <td class="col-title"><span class="truncate-1line" data-bs-toggle="tooltip" data-bs-custom-class="template-tooltip" title="<?= h((string)($row['title'] ?? '')) ?>"><?= h((string)($row['title'] ?? '')) ?></span></td>
                        <td class="col-status"><span class="expandable-status-chip <?= strtolower((string)($row['status'] ?? 'completed')) === 'completed' ? 'is-completed' : 'is-pending' ?>"><?= h((string)($row['status'] ?? '')) ?></span></td>
                        <td class="col-updated"><span class="truncate-1line" data-bs-toggle="tooltip" data-bs-custom-class="template-tooltip" title="<?= h((string)($row['updated_at'] ?? '')) ?>"><?= h((string)($row['updated_at'] ?? '')) ?></span></td>
                        <td class="col-actions">
                          <button type="button" class="btn btn-outline-primary btn-sm js-expand-row" title="<?= h(t('__PAGE_KEY_PREFIX___btn_expand', 'Expand')) ?>">
                            <i class="ri-arrow-down-s-line"></i>
                          </button>
                        </td>
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
  function buildDetailMarkup(data) {
    return '' +
      '<div class="expandable-detail-grid">' +
        '<div><span class="expandable-detail-label"><?= h(t('__PAGE_KEY_PREFIX___label_owner', 'Owner')) ?></span><div class="expandable-detail-value">' + (data.owner || '-') + '</div></div>' +
        '<div><span class="expandable-detail-label"><?= h(t('__PAGE_KEY_PREFIX___label_updated_at', 'Last Updated')) ?></span><div class="expandable-detail-value">' + (data.updated_at || '-') + '</div></div>' +
        '<div class="expandable-detail-span"><span class="expandable-detail-label"><?= h(t('__PAGE_KEY_PREFIX___label_description', 'Description')) ?></span><div class="expandable-detail-value">' + (data.description || '-') + '</div></div>' +
        '<div class="expandable-detail-span"><span class="expandable-detail-label"><?= h(t('__PAGE_KEY_PREFIX___label_notes', 'Notes')) ?></span><div class="expandable-detail-value">' + (data.notes || '-') + '</div></div>' +
      '</div>';
  }

  document.addEventListener('DOMContentLoaded', function () {
    if (!window.jQuery || !jQuery.fn || !jQuery.fn.DataTable) {
      return;
    }

    var table = jQuery('#expandableDT').DataTable({
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
        { targets: 0, orderable: false, searchable: false, width: 56 },
        { targets: 5, orderable: false, searchable: false, width: 90 }
      ],
      rowCallback: function (row, data, displayIndex) {
        var info = this.api().page.info();
        jQuery('td:eq(0)', row).text(info.start + displayIndex + 1);
      },
      initComplete: function () {
        if (window.DataTableStandard && typeof window.DataTableStandard.decorate === 'function') {
          window.DataTableStandard.decorate('#expandableDT', {
            searchPlaceholder: <?= json_encode('Search') ?>
          });
        }
      }
    });

    table.on('order.dt search.dt draw.dt', function () {
      var info = table.page.info();
      table.column(0, { search: 'applied', order: 'applied', page: 'current' }).nodes().each(function (cell, index) {
        cell.textContent = info.start + index + 1;
      });
    }).draw();

    if (window.bootstrap) {
      document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach(function (element) {
        new bootstrap.Tooltip(element);
      });
    }

    jQuery('#expandableDT tbody').on('click', '.js-expand-row', function () {
      var tr = jQuery(this).closest('tr');
      var row = table.row(tr);
      var button = jQuery(this);
      var icon = button.find('i');

      if (row.child.isShown()) {
        row.child.hide();
        tr.removeClass('is-expanded');
        icon.attr('class', 'ri-arrow-down-s-line');
        return;
      }

      table.rows().every(function () {
        if (this.child.isShown()) {
          jQuery(this.node()).removeClass('is-expanded').find('.js-expand-row i').attr('class', 'ri-arrow-down-s-line');
          this.child.hide();
        }
      });

      var payload = {};
      try {
        payload = JSON.parse(tr.attr('data-row') || '{}');
      } catch (error) {
        payload = {};
      }

      row.child('<div class="expandable-detail-shell">' + buildDetailMarkup(payload) + '</div>').show();
      tr.addClass('is-expanded');
      icon.attr('class', 'ri-arrow-up-s-line');
    });
  });
})();
</script>
</body>
</html>
