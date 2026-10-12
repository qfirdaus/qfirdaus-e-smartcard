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
            <div class="card">
              <div class="card-body">
                <div class="table-responsive dt-standard">
                  <table class="table table-bordered align-middle w-100" id="userDT">
                    <thead>
                      <tr>
                        <th class="col-bil"><?= h(t('__PAGE_KEY_PREFIX___col_no', 'No.')) ?></th>
                        <th class="col-nama"><?= h(t('__PAGE_KEY_PREFIX___col_name', 'Name')) ?></th>
                        <th class="col-jabatan"><?= h(t('__PAGE_KEY_PREFIX___col_department', 'Department')) ?></th>
                        <th class="col-group"><?= h(t('__PAGE_KEY_PREFIX___col_group', 'Group')) ?></th>
                        <th class="col-akses"><?= h(t('__PAGE_KEY_PREFIX___col_access', 'Access')) ?></th>
                        <th class="col-actions"><?= h(t('__PAGE_KEY_PREFIX___col_actions', 'Actions')) ?></th>
                      </tr>
                    </thead>
                    <tbody>
                      <?php foreach ($rows as $index => $row): ?>
                        <?php
                          $rowJson = json_encode($row, JSON_UNESCAPED_UNICODE | JSON_HEX_APOS | JSON_HEX_QUOT);
                          $name = (string)($row['name'] ?? '');
                          $identifier = (string)($row['identifier'] ?? '');
                          $department = (string)($row['department'] ?? '');
                          $groupName = (string)($row['group_name'] ?? '');
                          $access = (int)($row['access_flag'] ?? 1);
                          $accessLabel = $access === 1
                            ? t('__PAGE_KEY_PREFIX___access_allowed', 'Allowed')
                            : t('__PAGE_KEY_PREFIX___access_blocked', 'Blocked');
                          $accessClass = $access === 1 ? 'is-allowed' : 'is-blocked';
                        ?>
                        <tr data-row-index="<?= h((string)$index) ?>">
                          <td class="col-bil"></td>
                          <td class="col-nama">
                            <span class="truncate-1line" title="<?= h(trim($name . ($identifier !== '' ? ' (' . $identifier . ')' : ''))) ?>">
                              <?= h($name) ?><?php if ($identifier !== ''): ?> (<?= h($identifier) ?>)<?php endif; ?>
                            </span>
                          </td>
                          <td class="col-jabatan"><span class="truncate-1line" title="<?= h($department) ?>"><?= h($department) ?></span></td>
                          <td class="col-group">
                            <span class="cell-inline">
                              <span class="group-chip" title="<?= h($groupName) ?>"><?= h($groupName) ?></span>
                            </span>
                          </td>
                          <td class="col-akses">
                            <span class="access-chip <?= h($accessClass) ?>"><?= h($accessLabel) ?></span>
                          </td>
                          <td class="col-actions">
                            <button type="button" class="btn btn-outline-primary btn-sm js-edit-row" data-row='<?= h((string)$rowJson) ?>' title="<?= h(t('__PAGE_KEY_PREFIX___btn_edit', 'Edit')) ?>">
                              <i class="ri-pencil-line"></i>
                            </button>
                            <button type="button" class="btn btn-outline-danger btn-sm js-delete-row" data-row='<?= h((string)$rowJson) ?>' title="<?= h(t('__PAGE_KEY_PREFIX___btn_delete', 'Delete')) ?>">
                              <i class="ri-delete-bin-line"></i>
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
<div class="modal fade sample-modal sample-modal--add" id="sampleAddModal" tabindex="-1" aria-hidden="true" aria-labelledby="sampleAddModalTitle">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="sampleAddModalTitle">
          <i class="ri-add-circle-line"></i> <?= h(t('__PAGE_KEY_PREFIX___modal_add_title', 'Add Record')) ?>
        </h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?= h(t('__PAGE_KEY_PREFIX___btn_close', 'Close')) ?>"></button>
      </div>
      <div class="modal-body">
        <form class="sample-form-shell" id="sampleAddForm">
          <div>
            <label for="sampleAddName" class="form-label"><?= h(t('__PAGE_KEY_PREFIX___field_name', 'Name')) ?></label>
            <input type="text" class="form-control" id="sampleAddName" value="">
          </div>
          <div>
            <label for="sampleAddDepartment" class="form-label"><?= h(t('__PAGE_KEY_PREFIX___field_department', 'Department')) ?></label>
            <input type="text" class="form-control" id="sampleAddDepartment" value="">
          </div>
          <div>
            <label for="sampleAddGroup" class="form-label"><?= h(t('__PAGE_KEY_PREFIX___field_group', 'Group')) ?></label>
            <input type="text" class="form-control" id="sampleAddGroup" value="">
          </div>
          <div>
            <label for="sampleAddAccess" class="form-label"><?= h(t('__PAGE_KEY_PREFIX___field_access', 'Access')) ?></label>
            <select class="form-select" id="sampleAddAccess">
              <option value="1"><?= h(t('__PAGE_KEY_PREFIX___access_allowed', 'Allowed')) ?></option>
              <option value="0"><?= h(t('__PAGE_KEY_PREFIX___access_blocked', 'Blocked')) ?></option>
            </select>
          </div>
        </form>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><?= h(t('__PAGE_KEY_PREFIX___btn_cancel', 'Cancel')) ?></button>
        <button type="button" class="btn btn-success" id="sampleAddSaveBtn"><?= h(t('__PAGE_KEY_PREFIX___btn_save', 'Save')) ?></button>
      </div>
    </div>
  </div>
</div>

<div class="modal fade sample-modal sample-modal--edit" id="sampleEditModal" tabindex="-1" aria-hidden="true" aria-labelledby="sampleEditModalTitle">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="sampleEditModalTitle">
          <i class="ri-pencil-line"></i> <?= h(t('__PAGE_KEY_PREFIX___modal_edit_title', 'Edit Record')) ?>
        </h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?= h(t('__PAGE_KEY_PREFIX___btn_close', 'Close')) ?>"></button>
      </div>
      <div class="modal-body">
        <form class="sample-form-shell" id="sampleEditForm">
          <input type="hidden" id="sampleEditId" value="">
          <div>
            <label for="sampleEditName" class="form-label"><?= h(t('__PAGE_KEY_PREFIX___field_name', 'Name')) ?></label>
            <input type="text" class="form-control" id="sampleEditName" value="">
          </div>
          <div>
            <label for="sampleEditDepartment" class="form-label"><?= h(t('__PAGE_KEY_PREFIX___field_department', 'Department')) ?></label>
            <input type="text" class="form-control" id="sampleEditDepartment" value="">
          </div>
          <div>
            <label for="sampleEditGroup" class="form-label"><?= h(t('__PAGE_KEY_PREFIX___field_group', 'Group')) ?></label>
            <input type="text" class="form-control" id="sampleEditGroup" value="">
          </div>
          <div>
            <label for="sampleEditAccess" class="form-label"><?= h(t('__PAGE_KEY_PREFIX___field_access', 'Access')) ?></label>
            <select class="form-select" id="sampleEditAccess">
              <option value="1"><?= h(t('__PAGE_KEY_PREFIX___access_allowed', 'Allowed')) ?></option>
              <option value="0"><?= h(t('__PAGE_KEY_PREFIX___access_blocked', 'Blocked')) ?></option>
            </select>
          </div>
        </form>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><?= h(t('__PAGE_KEY_PREFIX___btn_cancel', 'Cancel')) ?></button>
        <button type="button" class="btn btn-primary" id="sampleEditSaveBtn"><?= h(t('__PAGE_KEY_PREFIX___btn_save', 'Save')) ?></button>
      </div>
    </div>
  </div>
</div>

<script>
(function(){
  document.addEventListener('DOMContentLoaded', function(){
    if (!window.jQuery || !jQuery.fn || !jQuery.fn.DataTable) {
      return;
    }

    const addModalEl = document.getElementById('sampleAddModal');
    const editModalEl = document.getElementById('sampleEditModal');
    const addModal = (window.bootstrap && addModalEl) ? new bootstrap.Modal(addModalEl) : null;
    const editModal = (window.bootstrap && editModalEl) ? new bootstrap.Modal(editModalEl) : null;

    const dt = jQuery('#userDT').DataTable({
      pageLength: 10,
      lengthChange: true,
      lengthMenu: [10, 25, 50, 100, 200],
      ordering: true,
      order: [[1,'asc']],
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
        { targets: 0, orderable:false, searchable:false, width: 56 },
        { targets: 5, orderable:false, searchable:false, width: 110 }
      ],
      rowCallback: function(row, data, displayIndex){
        const api = this.api();
        const info = api.page.info();
        jQuery('td:eq(0)', row).text(info.start + displayIndex + 1);
      },
      initComplete: function() {
        setupTableControls();
      }
    });

    function setupTableControls() {
      if (window.DataTableStandard && typeof window.DataTableStandard.decorate === 'function') {
        window.DataTableStandard.decorate('#userDT', {
          searchPlaceholder: <?= json_encode('Search') ?>
        });
      }

      jQuery('#userDT_length select').addClass('form-select w-auto');
      jQuery('#userDT_length label').addClass('mb-0');

      const $topRight = jQuery('#userDT_wrapper .dt-top-right').addClass('align-items-center gap-2 flex-nowrap');
      const $filter = jQuery('#userDT_filter');

      if (!document.getElementById('btnSampleAction')) {
        $topRight.append(
          '<button type="button" id="btnSampleAction" class="btn btn-primary sync-groups-btn">' +
            '<i class="ri-add-line"></i><span><?= h(t('__PAGE_KEY_PREFIX___btn_sample', 'Add New')) ?></span>' +
          '</button>'
        );
      }
    }

    function parseRowPayload(btn) {
      try {
        return JSON.parse(btn.getAttribute('data-row') || '{}');
      } catch (err) {
        return {};
      }
    }

    function accessLabel(flag) {
      return String(flag) === '1'
        ? <?= json_encode(t('__PAGE_KEY_PREFIX___access_allowed', 'Allowed')) ?>
        : <?= json_encode(t('__PAGE_KEY_PREFIX___access_blocked', 'Blocked')) ?>;
    }

    function openAddModal() {
      if (!addModal) return;
      const addForm = document.getElementById('sampleAddForm');
      if (addForm) {
        addForm.reset();
      }
      addModal.show();
    }

    function openEditModal(data) {
      if (!editModal) return;
      document.getElementById('sampleEditId').value = data.id || '';
      document.getElementById('sampleEditName').value = data.name || '';
      document.getElementById('sampleEditDepartment').value = data.department || '';
      document.getElementById('sampleEditGroup').value = data.group_name || '';
      document.getElementById('sampleEditAccess').value = String(data.access_flag ?? 1);
      editModal.show();
    }

    jQuery(document).on('click', '#btnSampleAction', function(){
      openAddModal();
    });

    jQuery(document).on('click', '.js-edit-row', function(){
      openEditModal(parseRowPayload(this));
    });

    jQuery(document).on('click', '.js-delete-row', function(){
      const data = parseRowPayload(this);
      if (!window.Swal) return;
      Swal.fire({
        icon: 'warning',
        title: <?= json_encode(t('__PAGE_KEY_PREFIX___delete_title', 'Delete Sample Record?')) ?>,
        text: <?= json_encode(t('__PAGE_KEY_PREFIX___delete_text', 'This is only a frontend sample. No backend action will be triggered.')) ?>,
        showCancelButton: true,
        confirmButtonText: <?= json_encode(t('__PAGE_KEY_PREFIX___btn_yes', 'Yes')) ?>,
        cancelButtonText: <?= json_encode(t('__PAGE_KEY_PREFIX___btn_no', 'No')) ?>
      }).then(function(result){
        if (!result.isConfirmed) return;
        Swal.fire({
          icon: 'success',
          title: <?= json_encode(t('__PAGE_KEY_PREFIX___delete_success_title', 'Sample Delete Complete')) ?>,
          text: (data.name || 'Record') + ' ' + <?= json_encode(t('__PAGE_KEY_PREFIX___delete_success_text', 'was processed as a sample action without backend interaction.')) ?>,
          confirmButtonText: <?= json_encode(t('__PAGE_KEY_PREFIX___btn_ok', 'OK')) ?>
        });
      });
    });

    jQuery('#sampleEditSaveBtn').on('click', function(){
      if (!window.Swal) return;
      if (editModal) {
        editModal.hide();
      }
      Swal.fire({
        icon: 'success',
        title: <?= json_encode(t('__PAGE_KEY_PREFIX___edit_success_title', 'Sample Save Complete')) ?>,
        text: <?= json_encode(t('__PAGE_KEY_PREFIX___edit_success_text', 'The sample edit flow completed without sending any data to the backend.')) ?>,
        confirmButtonText: <?= json_encode(t('__PAGE_KEY_PREFIX___btn_ok', 'OK')) ?>
      });
    });

    jQuery('#sampleAddSaveBtn').on('click', function(){
      if (!window.Swal) return;
      if (addModal) {
        addModal.hide();
      }
      Swal.fire({
        icon: 'success',
        title: <?= json_encode(t('__PAGE_KEY_PREFIX___sample_add_success_title', 'Sample Add Complete')) ?>,
        text: <?= json_encode(t('__PAGE_KEY_PREFIX___sample_add_success_text', 'The sample add flow completed without sending any data to the backend.')) ?>,
        confirmButtonText: <?= json_encode(t('__PAGE_KEY_PREFIX___btn_ok', 'OK')) ?>
      });
    });
  });
})();
</script>
</body>
</html>
