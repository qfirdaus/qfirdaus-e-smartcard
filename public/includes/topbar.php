<?php
/**
 * IQS FRAMEWORK CORE FILE
 *
 * READ ONLY for downstream project programmers.
 * Do not modify this file directly in template or cloned projects.
 * Custom changes must be implemented in project-specific files
 * or approved extension points.
 */if (session_status() === PHP_SESSION_NONE) session_start();

require_once __DIR__ . '/../classes/Database.php';
require_once __DIR__ . '/../classes/User.php';
require_once __DIR__ . '/../setting/function.php';
require_once __DIR__ . '/../classes/Config.php';
require_once __DIR__ . '/../setting/helper/config_helper.php';
require_once __DIR__ . '/../includes/functions-db.php';

// Helper escape
if (!function_exists('h')) {
  function h($v){ return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
}

// Helper: bina URL semasa + ganti/tambah 1 query param
if (!function_exists('url_with_param')) {
  function url_with_param(string $key, string $val): string {
    $req = $_SERVER['REQUEST_URI'] ?? '/';
    $p   = parse_url($req);
    $path= $p['path'] ?? '/';
    parse_str($p['query'] ?? '', $q);
    $q[$key] = $val;
    $qs = http_build_query($q);
    return $path . ($qs ? '?'.$qs : '');
  }
}

$pdo_mysql  = Database::getInstance('mysql')->getConnection();
$user       = new User($pdo_mysql);
$f_loginID  = $_SESSION['f_loginID'] ?? null;
$f_stafID   = $_SESSION['f_stafID'] ?? null;
$profile    = [];
if ($f_loginID) {
  $profile = $user->getProfileByLoginID((string)$f_loginID) ?: [];
}
if (!$profile && $f_stafID) {
  $profile = $user->getProfile((string)$f_stafID) ?: [];
}
if (!empty($profile['f_loginID']) && empty($_SESSION['f_loginID'])) {
  $_SESSION['f_loginID'] = (string)$profile['f_loginID'];
}

$nama_pengguna     = $profile['f_nama'] ?? ($profile['f_nickname'] ?? 'Pengguna');
$peranan_pengguna  = $profile['f_groupName'] ?? 'Pengguna';
$avatarUrl         = $profile['avatar_url'] ?? $profile['avatar'] ?? base_url('assets/images/no-image.jpg');
$lang              = $_SESSION['lang'] ?? 'ms';

if (empty($_SESSION['csrf_token'])) {
  $_SESSION['csrf_token'] = bin2hex(random_bytes(16));
}
$csrfToken = $_SESSION['csrf_token'];
$isImpersonating = function_exists('impersonation_is_active') && impersonation_is_active();
$impersonationActor = $isImpersonating && function_exists('impersonation_actor') ? impersonation_actor() : [];
$impersonationTarget = $isImpersonating && function_exists('impersonation_target') ? impersonation_target() : [];
$impersonationReason = $isImpersonating ? (string)((impersonation_state()['reason'] ?? '')) : '';
$impersonationMode = $isImpersonating && function_exists('impersonation_mode') ? impersonation_mode() : 'view_only';
$impersonationModeLabel = $impersonationMode === 'support_action'
  ? (string)(__('impersonation_mode_support_action') ?: 'Support Action')
  : (string)(__('impersonation_mode_view_only') ?: 'View Only');

$defaultGroupId = (int)($_SESSION['group_default_id'] ?? ($profile['f_groupID'] ?? 0));
if (!isset($_SESSION['group_default_id']) && $defaultGroupId > 0) {
  $_SESSION['group_default_id'] = $defaultGroupId;
}
if (!isset($_SESSION['group_active_id']) && $defaultGroupId > 0) {
  $_SESSION['group_active_id'] = $defaultGroupId;
}
$defaultGroupId = (int)($_SESSION['group_default_id'] ?? $defaultGroupId);
$activeGroupId = (int)($_SESSION['group_active_id'] ?? $defaultGroupId);

// Fetch active group name for display (do not change user default role)
if ($activeGroupId > 0 && $activeGroupId !== $defaultGroupId) {
  try {
    $stmtAct = $pdo_mysql->prepare("SELECT f_groupName FROM tbl_m_group WHERE f_groupID = :gid LIMIT 1");
    $stmtAct->execute([':gid' => $activeGroupId]);
    $rowAct = $stmtAct->fetch(PDO::FETCH_ASSOC);
    if (!empty($rowAct['f_groupName'])) {
      $peranan_pengguna = (string)$rowAct['f_groupName'];
    }
  } catch (Throwable $e) {
    // keep original label on failure
  }
}

// Allowed roles for role switcher (tbl_ref_access + tbl_m_group)
$allowedRoles = [];
$hasExtraRole = false;
try {
  $userID = (int)($profile['f_userID'] ?? $_SESSION['f_userID'] ?? 0);
  $stafID = (string)($profile['f_stafID'] ?? $_SESSION['f_stafID'] ?? '');
  $stafRaw = trim($stafID);
  $stafNorm = str_replace('-', '', $stafRaw);
  if ($userID > 0 || $stafRaw !== '' || $stafNorm !== '') {
    $stmtHas = $pdo_mysql->prepare("\n      SELECT 1\n      FROM tbl_ref_access a\n      WHERE a.f_status = 1\n        AND (\n          a.f_userID = :uid\n          OR (\n            :uid_zero = 0\n            AND (TRIM(a.f_stafID) = :staf OR REPLACE(TRIM(a.f_stafID), '-', '') = :staf_norm)\n          )\n        )\n      LIMIT 1\n    ");
    $stmtHas->execute([':uid' => $userID, ':uid_zero' => $userID, ':staf' => $stafRaw, ':staf_norm' => $stafNorm]);
    $hasExtraRole = (bool)$stmtHas->fetchColumn();

    $stmtRoles = $pdo_mysql->prepare("\n      SELECT a.f_groupID, g.f_groupKod, g.f_groupName\n      FROM tbl_ref_access a\n      JOIN tbl_m_group g ON g.f_groupID = a.f_groupID\n      WHERE a.f_status = 1\n        AND (\n          a.f_userID = :uid\n          OR (\n            :uid_zero = 0\n            AND (TRIM(a.f_stafID) = :staf OR REPLACE(TRIM(a.f_stafID), '-', '') = :staf_norm)\n          )\n        )\n      ORDER BY g.f_groupName ASC, g.f_groupKod ASC\n    ");
    $stmtRoles->execute([':uid' => $userID, ':uid_zero' => $userID, ':staf' => $stafRaw, ':staf_norm' => $stafNorm]);
    $allowedRoles = $stmtRoles->fetchAll(PDO::FETCH_ASSOC) ?: [];
  }
} catch (Throwable $e) {
  $allowedRoles = [];
  $hasExtraRole = false;
}
if ($isImpersonating) {
  $allowedRoles = [];
  $hasExtraRole = false;
}

// Default role label (from tbl_m_user)
$defaultGroupName = $profile['f_groupName'] ?? 'Pengguna';

// Tetapan bahasa aktif dari DB
$config = new Config($pdo_mysql);
$bahasaAktif = array_values(array_filter($config->getBahasaAktif() ?: []));
if (!$bahasaAktif) $bahasaAktif = ['ms'];

// Bendera & label
$langFlag = [
  'ms' => 'malaysia.png',
  'en' => 'united-kingdom.png',
];
$langLabel = [
  'ms' => 'Bahasa Melayu',
  'en' => 'English',
];

$topbarColor = $_SESSION['theme.topbar'] ?? 'light';
$defaultHome = app_config('site.default_home', 'pages/dashboard.php');
$topbarLogoLight = app_config('branding.topbar_logo_light', 'assets/images/logo.png');
$topbarLogoDark = app_config('branding.topbar_logo_dark', 'assets/images/logo-dark.png');
$topbarLogoSm = app_config('branding.topbar_logo_sm', 'assets/images/logo-sm.png');

$sybaseEnvironment = function_exists('get_sybase_environment') ? get_sybase_environment() : 'unknown';
$sybaseOperationalMode = function_exists('get_sybase_operational_mode') ? get_sybase_operational_mode() : 'unknown';
$sybaseStaffRuntime = function_exists('get_sybase_staff_key') ? get_sybase_staff_key() : (function_exists('get_sybase_staff_base') ? get_sybase_staff_base() : 'unknown');
$sybaseStudentRuntime = (function_exists('is_student_mode_enabled') && is_student_mode_enabled())
  ? (function_exists('get_sybase_student_key') ? get_sybase_student_key() : (function_exists('get_sybase_student_base') ? get_sybase_student_base() : 'enabled'))
  : 'Disabled';
$sybaseEnvironmentLabel = match ((string)$sybaseEnvironment) {
  'production' => 'Production',
  'development' => 'Development',
  default => ucfirst((string)$sybaseEnvironment),
};
$sybaseOperationalModeLabel = match ((string)$sybaseOperationalMode) {
  'staff_only' => 'Staff Only',
  'staff_student' => 'Staff + Student',
  default => ucfirst(str_replace('_', ' ', (string)$sybaseOperationalMode)),
};

// Flash message for role switch success
$roleSwitchFlash = $_SESSION['role_switch_success'] ?? null;
if ($roleSwitchFlash !== null) {
  unset($_SESSION['role_switch_success']);
}
?>

<!-- ========== Development Mode Banner (Overlay) ========== -->
<?php if (function_exists('is_development_mode') && is_development_mode()): ?>
  <div class="dev-mode-tab" id="dev-mode-tab" role="alert" aria-live="polite">
    <div class="dev-mode-tab__inner container-fluid">
      <div class="dev-mode-tab__headline">
        <span class="dev-mode-tab__title-wrap">
          <span class="dev-mode-tab__icon">
            <i class="ri-code-s-slash-line"></i>
          </span>
          <span class="dev-mode-tab__title-block">
            <span class="dev-mode-tab__eyebrow">System Mode</span>
            <span class="dev-mode-tab__title">Development</span>
          </span>
        </span>
        <span class="dev-mode-tab__actions">
          <span class="dev-mode-tab__env"><?= h($sybaseEnvironmentLabel) ?></span>
          <button type="button" class="dev-mode-tab__toggle" id="dev-mode-tab-toggle" aria-expanded="false" aria-controls="dev-mode-tab-details" aria-label="Expand development mode details">
            <span class="dev-mode-tab__toggle-text">+</span>
          </button>
        </span>
      </div>
      <div class="dev-mode-tab__grid" id="dev-mode-tab-details" hidden>
        <div class="dev-mode-tab__item">
          <span class="dev-mode-tab__label">Environment</span>
          <span class="dev-mode-tab__value"><?= h($sybaseEnvironmentLabel) ?></span>
        </div>
        <div class="dev-mode-tab__item">
          <span class="dev-mode-tab__label">Operational Mode</span>
          <span class="dev-mode-tab__value"><?= h($sybaseOperationalModeLabel) ?></span>
        </div>
        <div class="dev-mode-tab__item">
          <span class="dev-mode-tab__label">Sybase Staff</span>
          <span class="dev-mode-tab__value dev-mode-tab__value--mono"><?= h($sybaseStaffRuntime) ?></span>
        </div>
        <div class="dev-mode-tab__item">
          <span class="dev-mode-tab__label">Sybase Student</span>
          <span class="dev-mode-tab__value dev-mode-tab__value--mono"><?= h($sybaseStudentRuntime) ?></span>
        </div>
      </div>
    </div>
  </div>
<?php endif; ?>

<!-- ========== Topbar Start ========== -->
<div id="topbar" class="navbar-custom" data-topbar-color="<?= h($topbarColor) ?>">
  <div class="topbar container-fluid">
    <div class="d-flex align-items-center gap-lg-2 gap-1">
      <!-- Logo -->
      <div class="logo-topbar">
        <a href="<?= h(base_url($defaultHome)) ?>" class="logo-light">
          <span class="logo-lg"><img src="<?= h(base_url($topbarLogoLight)) ?>" alt="logo"></span>
          <span class="logo-sm"><img src="<?= h(base_url($topbarLogoSm)) ?>" alt="small logo"></span>
        </a>
        <a href="<?= h(base_url($defaultHome)) ?>" class="logo-dark">
          <span class="logo-lg"><img src="<?= h(base_url($topbarLogoDark)) ?>" alt="dark logo"></span>
          <span class="logo-sm"><img src="<?= h(base_url($topbarLogoSm)) ?>" alt="small logo"></span>
        </a>
      </div>

      <!-- Sidebar Menu Toggle Button -->
      <button class="button-toggle-menu">
        <i class="ri-menu-2-fill"></i>
      </button>
      
    </div>

    <ul class="topbar-menu d-flex align-items-center gap-3">

      <?php
        $activeLangCount = count($bahasaAktif);
        $currentFlag  = $langFlag[$lang] ?? 'malaysia.png';
        $currentLabel = $langLabel[$lang] ?? strtoupper($lang);
      ?>

      <!-- Language -->
      <?php if ($activeLangCount <= 1): ?>
        <li class="topbar-language">
          <div class="nav-link topbar-language-toggle">
            <img src="<?= h(base_url('assets/images/flags/'.$currentFlag)) ?>" height="16" class="topbar-language-flag" alt="flag">
            <span class="d-none d-lg-inline-block topbar-language-label"><?= h($currentLabel) ?></span>
          </div>
        </li>
      <?php else: ?>
        <li class="dropdown topbar-language">
          <a class="nav-link dropdown-toggle arrow-none topbar-language-toggle" data-bs-toggle="dropdown" href="#" role="button" aria-expanded="false">
            <img src="<?= h(base_url('assets/images/flags/'.$currentFlag)) ?>" height="16" class="topbar-language-flag" alt="flag">
            <span class="d-none d-lg-inline-block topbar-language-label"><?= h($currentLabel) ?></span>
            <i class="ri-arrow-down-s-line d-none d-sm-inline-block align-middle topbar-language-caret"></i>
          </a>
          <div class="dropdown-menu dropdown-menu-end dropdown-menu-animated topbar-language-menu" data-bs-auto-close="outside">
            <?php foreach ($bahasaAktif as $key): if (!isset($langLabel[$key], $langFlag[$key])) continue; ?>
              <?php $href = url_with_param('lang', $key); ?>
              <a href="<?= h($href) ?>"
                 class="dropdown-item topbar-language-item<?= ($lang === $key ? ' active fw-semibold text-primary':'') ?>"
                 data-lang-link="1">
                <img src="<?= h(base_url('assets/images/flags/'.$langFlag[$key])) ?>" height="12" class="topbar-language-item-flag" alt="">
                <span class="topbar-language-item-label"><?= h($langLabel[$key]) ?></span>
                <?= ($lang === $key) ? '<i class="ri-check-line topbar-language-item-check"></i>' : '' ?>
              </a>
            <?php endforeach; ?>
          </div>
        </li>
      <?php endif; ?>

      <!-- Notification -->
      <li class="dropdown notification-list" id="topbarNotificationRoot"
          data-list-url="<?= h(base_url('ajax/notification-list.php')) ?>"
          data-read-url="<?= h(base_url('ajax/notification-read.php')) ?>"
          data-read-all-url="<?= h(base_url('ajax/notification-read-all.php')) ?>"
          data-base-url="<?= h(base_url('')) ?>"
          data-view-all-url="<?= h(base_url('pages/notifications.php')) ?>"
          data-loading-text="<?= h(__('topbar_notification_loading') ?: 'Loading...') ?>"
          data-empty-text="<?= h(__('topbar_notification_empty') ?: 'No notifications.') ?>"
          data-failed-text="<?= h(__('topbar_notification_load_failed') ?: 'Unable to load notifications.') ?>"
          data-action-text="<?= h(__('notification_action_required') ?: 'Action') ?>"
          data-overdue-text="<?= h(__('notification_action_overdue') ?: 'Overdue') ?>">
        <a class="nav-link dropdown-toggle arrow-none topbar-notification-toggle" data-bs-toggle="dropdown" href="#" role="button" aria-expanded="false" id="topbarNotificationToggle" aria-label="<?= h(__('topbar_notification_title') ?: 'Notification') ?>">
          <i class="ri-notification-3-fill"></i>
          <span class="noti-icon-badge d-none" id="topbarNotificationBadge"></span>
        </a>
        <div class="dropdown-menu dropdown-menu-end dropdown-menu-animated dropdown-lg py-0 topbar-notification-menu" data-bs-auto-close="outside">
          <div class="topbar-notification-header">
            <div>
              <h6><?= h(__('topbar_notification_title') ?: 'Notification') ?></h6>
              <span><?= h(__('topbar_notification_latest') ?: 'Latest updates') ?></span>
            </div>
            <a href="#" class="topbar-notification-read-all d-none" id="topbarNotificationReadAll">
              <?= h(__('topbar_notification_mark_all_read') ?: 'Mark All Read') ?>
            </a>
          </div>
          <div class="topbar-notification-list" data-simplebar id="topbarNotificationList">
            <div class="p-3 text-center text-muted small"><?= h(__('topbar_notification_loading') ?: 'Loading...') ?></div>
          </div>
          <a href="<?= h(base_url('pages/notifications.php')) ?>" class="topbar-notification-view-all">
            <?= h(__('topbar_notification_view_all') ?: 'View All') ?>
          </a>
        </div>
      </li>

      <!-- Theme / Fullscreen -->
      <li class="d-none d-sm-inline-block">
        <a class="nav-link" data-bs-toggle="offcanvas" href="#theme-settings-offcanvas" role="button" aria-controls="theme-settings-offcanvas">
          <i class="ri-settings-3-fill fs-22"></i>
        </a>
      </li>
      <li class="d-none d-sm-inline-block"><div class="nav-link" id="light-dark-mode"><i class="ri-moon-fill fs-22" id="theme-mode-icon"></i></div></li>
      <li class="d-none d-md-inline-block"><a class="nav-link" href="#" id="toggle-fullscreen"><i class="ri-fullscreen-line fs-22"></i></a></li>

      <!-- Akaun Pengguna -->
      <li class="dropdown me-md-2">
        <a class="nav-link dropdown-toggle nav-user px-2" data-bs-toggle="dropdown" href="#" role="button" aria-expanded="false">
          <span class="account-user-avatar">
            <img src="<?= h($avatarUrl) ?>" width="32" class="rounded-circle"
                 onerror="this.onerror=null;this.src='<?= h(base_url('assets/images/no-image.jpg')) ?>';" alt="">
          </span>
          <span class="d-lg-flex flex-column gap-1 d-none">
            <h5 class="my-0"><?= h($nama_pengguna) ?></h5>
            <h6 class="my-0 fw-normal" id="topbarCurrentRoleLabel"><?= h($peranan_pengguna) ?></h6>
          </span>
        </a>
        <div class="dropdown-menu dropdown-menu-end dropdown-menu-animated profile-dropdown" data-bs-auto-close="outside">
          <div class="dropdown-header noti-title profile-dropdown-header">
            <h6 class="text-overflow m-0"><?= h(__('topbar_welcome')) ?></h6>
          </div>
          <a href="<?= h(base_url('pages/profile.php')) ?>" class="dropdown-item"><i class="ri-account-circle-fill me-1"></i> Profil Saya</a>
          <?php if ($hasExtraRole): ?>
            <a href="#" class="dropdown-item" id="btnSwitchRole">
              <i class="ri-shuffle-line me-1"></i> <?= h(__('topbar_switch_role') ?? 'Tukar Peranan') ?>
            </a>
          <?php endif; ?>
          <!-- <a href="<?= h(base_url('pages/pages-settings.html')) ?>" class="dropdown-item"><i class="ri-settings-4-fill me-1"></i> Tetapan</a>
          <a href="<?= h(base_url('pages/pages-faq.html')) ?>" class="dropdown-item"><i class="ri-customer-service-2-fill me-1"></i> Sokongan</a> -->
          <!-- <a href="<?= h(base_url('pages/auth-lock-screen.html')) ?>" class="dropdown-item"><i class="ri-lock-password-fill me-1"></i> Kunci Skrin</a> -->

          <!-- Logout -->
          <div class="dropdown-divider"></div>
          <a href="#" class="dropdown-item text-danger" onclick="return confirmLogout(event);" data-no-loader>
            <i class="ri-logout-box-fill me-1"></i> <?= __('topbar_keluar') ?>
          </a>

        </div>
      </li>

    </ul>
  </div>
</div>
<!-- ========== Topbar End ========== -->

<?php if ($isImpersonating): ?>
<div class="impersonation-banner" role="status" aria-live="polite">
  <div class="impersonation-banner__content">
    <div class="impersonation-banner__text">
      <i class="ri-eye-line"></i>
      <span>
        <?= h(__('impersonation_banner_prefix') ?: 'Viewing as') ?>:
        <strong><?= h((string)($impersonationTarget['name'] ?? $nama_pengguna)) ?></strong>
        <small>(<?= h((string)($impersonationTarget['login_id'] ?? $_SESSION['f_loginID'] ?? '')) ?>)</small>
      </span>
      <span class="impersonation-banner__actor">
        <?= h(__('impersonation_banner_actor') ?: 'Actor') ?>:
        <?= h((string)($impersonationActor['name'] ?? $impersonationActor['login_id'] ?? 'Admin')) ?>
      </span>
      <span class="impersonation-banner__mode"><?= h($impersonationModeLabel) ?></span>
      <?php if ($impersonationReason !== ''): ?>
        <span class="impersonation-banner__reason"><?= h($impersonationReason) ?></span>
      <?php endif; ?>
    </div>
    <button type="button" class="btn btn-sm btn-light" id="btnStopImpersonation">
      <i class="ri-logout-box-r-line me-1"></i><?= h(__('impersonation_stop_button') ?: 'Stop View As') ?>
    </button>
  </div>
</div>
<?php endif; ?>

<!-- ========== Role Switcher Modal (Topbar) ========== -->
<div class="modal fade modal-themed" id="switchRoleModal" tabindex="-1" aria-hidden="true" aria-labelledby="switchRoleTitle">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="switchRoleTitle">
          <i class="ri-shuffle-line me-2"></i> <?= h(__('topbar_switch_role_title') ?? 'Tukar Peranan') ?>
        </h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?= h(__('userList_modal_btn_close')) ?>"></button>
      </div>
      <div class="modal-body">
        <form id="switchRoleForm" autocomplete="off">
          <input type="hidden" id="sr_csrf" value="<?= h($csrfToken) ?>">

          <div class="form-section">
            <div class="form-section-title">
              <i class="ri-shield-user-line me-1"></i> <?= h(__('topbar_switch_role_select') ?? 'Pilih Peranan') ?>
            </div>

            <div class="mb-2 text-muted small" id="switchRolePrimary">
              <?= h(__('topbar_switch_role_primary_label') ?? 'Peranan utama') ?>: <strong id="switchRolePrimaryName"><?= h($defaultGroupName) ?></strong>
            </div>
            <?php if (!empty($allowedRoles) || $defaultGroupId > 0): ?>
              <div class="role-list" id="switchRoleList">
                <?php if ($defaultGroupId > 0): ?>
                  <label class="role-item">
                    <input type="radio" name="group_active_id" value="<?= h((string)$defaultGroupId) ?>" <?= ($defaultGroupId === $activeGroupId ? 'checked' : '') ?>>
                    <span class="role-label">
                      <?= h($defaultGroupName) ?> <span class="text-muted">(<?= h(__('topbar_switch_role_primary_tag') ?? 'Peranan Utama') ?>)</span>
                    </span>
                  </label>
                <?php endif; ?>
                <?php foreach ($allowedRoles as $r): 
                  $rid  = (int)($r['f_groupID'] ?? 0);
                  $rkod = (string)($r['f_groupKod'] ?? '');
                  $rnam = (string)($r['f_groupName'] ?? '');
                  if ($rid <= 0 || $rid === $defaultGroupId) continue;
                  $checked = ($rid === $activeGroupId) ? 'checked' : '';
                ?>
                  <label class="role-item">
                    <input type="radio" name="group_active_id" value="<?= h((string)$rid) ?>" <?= $checked ?>>
                    <span class="role-label"><?= h($rnam) ?></span>
                  </label>
                <?php endforeach; ?>
              </div>
              <div class="text-muted d-none" id="switchRoleEmpty"><?= h(__('topbar_switch_role_none') ?? 'Tiada peranan lain yang dibenarkan.') ?></div>
            <?php else: ?>
              <div class="text-muted" id="switchRoleEmpty"><?= h(__('topbar_switch_role_none') ?? 'Tiada peranan lain yang dibenarkan.') ?></div>
              <div class="role-list d-none" id="switchRoleList"></div>
            <?php endif; ?>
          </div>
        </form>
        <div id="switchRoleError" class="modal-error alert alert-danger d-none mt-3"></div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
          <i class="ri-close-line me-1"></i> <?= __('userList_modal_btn_cancel') ?>
        </button>
        <button type="button" class="btn btn-primary" id="switchRoleSaveBtn" <?= (empty($allowedRoles) && $defaultGroupId <= 0) ? 'disabled' : '' ?>>
          <i class="ri-save-3-line me-1"></i> <?= __('userList_modal_btn_save') ?>
        </button>
      </div>
    </div>
  </div>
</div>

<!-- ========== Development Mode Banner CSS ========== -->
<style>
  .impersonation-banner {
    position: sticky;
    top: 70px;
    z-index: 1001;
    border-bottom: 1px solid rgba(180, 83, 9, .22);
    background: linear-gradient(135deg, #fef3c7, #fed7aa);
    color: #7c2d12;
    box-shadow: 0 6px 18px rgba(15, 23, 42, .08);
  }
  .impersonation-banner__content {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: .9rem;
    width: 100%;
    padding: .55rem 1.25rem;
  }
  .impersonation-banner__text {
    display: flex;
    align-items: center;
    flex-wrap: wrap;
    gap: .65rem;
    min-width: 0;
    font-size: .86rem;
  }
  .impersonation-banner__text > i { font-size: 1.1rem; }
  .impersonation-banner__actor,
  .impersonation-banner__mode,
  .impersonation-banner__reason {
    border-left: 1px solid rgba(124, 45, 18, .22);
    padding-left: .65rem;
    opacity: .95;
  }
  .impersonation-banner__mode {
    display: inline-flex;
    align-items: center;
    border: 1px solid rgba(124, 45, 18, .25);
    border-radius: 999px;
    padding: .12rem .55rem;
    font-weight: 800;
    background: rgba(255,255,255,.5);
  }
  .impersonation-banner .btn {
    white-space: nowrap;
    font-weight: 700;
  }
  @media (max-width: 767.98px) {
    .impersonation-banner__content {
      align-items: flex-start;
      flex-direction: column;
      padding: .65rem .9rem;
    }
    .impersonation-banner__actor,
    .impersonation-banner__mode,
    .impersonation-banner__reason {
      border-left: 0;
      padding-left: 0;
    }
  }
  .topbar-language-toggle {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
  }
  .topbar-language-flag {
    flex: 0 0 auto;
    border-radius: 2px;
  }
  .topbar-language-label {
    line-height: 1;
  }
  .topbar-language-caret {
    margin-left: -0.125rem;
  }
  .topbar-language-menu {
    min-width: 12.5rem;
    padding: 0.5rem;
  }
  .topbar-language-item {
    display: flex;
    align-items: center;
    gap: 0.55rem;
    margin: 0.12rem 0.18rem;
    padding: 0.5rem 0.7rem;
    border-radius: 0.55rem;
  }
  .topbar-language-item-flag {
    flex: 0 0 auto;
    width: 18px;
    height: auto;
      background: rgba(241, 245, 249, 0.96);
    }

  .topbar-notification-toggle {
    position: relative;
    display: inline-flex !important;
    align-items: center;
    justify-content: center;
    width: 42px;
    height: 42px;
    padding: 0 !important;
  }
  .topbar-notification-toggle > i {
    font-size: 1.35rem;
    line-height: 1;
  }
  .topbar-notification-toggle .noti-icon-badge {
    position: absolute;
    top: 5px;
    right: 3px;
    min-width: 18px;
    height: 18px;
    padding: 0 5px;
    border-radius: 999px;
    background: #ef4444;
    color: #fff;
    border: 2px solid var(--ct-secondary-bg);
    font-size: 0.62rem;
    font-weight: 800;
    line-height: 14px;
    text-align: center;
    box-shadow: 0 2px 5px rgba(239, 68, 68, 0.25);
  }
  .topbar-notification-menu {
    display: flex;
    flex-direction: column;
    width: 380px;
    max-width: calc(100vw - 1rem);
    max-height: min(520px, calc(100vh - 5rem));
    border: 1px solid rgba(148, 163, 184, 0.18);
    border-radius: 10px;
    background: #fff;
    overflow: hidden;
    box-shadow: 0 14px 34px rgba(15, 23, 42, 0.14);
  }
  .topbar-notification-header {
    flex: 0 0 auto;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 1rem;
    padding: 0.9rem 1rem;
    background: linear-gradient(180deg, rgba(248, 250, 252, 0.98), rgba(255, 255, 255, 0.98));
    border-bottom: 1px solid rgba(148, 163, 184, 0.18);
  }
  .topbar-notification-header h6 {
    margin: 0;
    color: #0f172a;
    font-size: 0.92rem;
    font-weight: 700;
    line-height: 1.15;
  }
  .topbar-notification-header span {
    display: block;
    margin-top: 0.12rem;
    color: #64748b;
    font-size: 0.68rem;
    font-weight: 500;
  }
  .topbar-notification-read-all {
    flex: 0 0 auto;
    color: #2563eb;
    font-size: 0.72rem;
    font-weight: 700;
    text-decoration: none;
    white-space: nowrap;
  }
  .topbar-notification-read-all:hover {
    color: #1d4ed8;
    text-decoration: underline;
  }
  .topbar-notification-list {
    flex: 1 1 auto;
    min-height: 0;
    max-height: none;
    background: #fff;
  }
  .topbar-notification-item {
    padding: 0.62rem 0.95rem !important;
    border-bottom: 1px solid rgba(148, 163, 184, 0.12);
    white-space: normal;
  }
  .topbar-notification-item:last-child {
    border-bottom: 0;
  }
  .topbar-notification-item:hover {
    background: rgba(37, 99, 235, 0.04);
  }
  .topbar-notification-item.is-unread {
    background: rgba(37, 99, 235, 0.055);
  }
  .topbar-notification-icon {
    width: 30px;
    height: 30px;
    flex: 0 0 30px;
    font-size: 0.95rem;
  }
  .topbar-notification-title {
    display: flex;
    align-items: flex-start;
    gap: 0.35rem;
    color: #111827;
    font-size: 0.78rem;
    font-weight: 700;
    line-height: 1.25;
  }
  .topbar-notification-title-text {
    min-width: 0;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
  }
  .topbar-notification-body {
    display: -webkit-box;
    margin-top: 0.14rem;
    max-width: 288px;
    overflow: hidden;
    color: #64748b;
    font-size: 0.7rem;
    line-height: 1.25;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
  }
  .topbar-notification-meta {
    display: flex;
    flex-wrap: wrap;
    gap: 0.32rem;
    margin-top: 0.22rem;
    color: #94a3b8;
    font-size: 0.66rem;
    line-height: 1.2;
  }
  .topbar-notification-status {
    flex: 0 0 auto;
    font-size: 0.58rem;
    line-height: 1.1;
    padding: 0.18rem 0.32rem;
  }
  .topbar-notification-view-all {
    flex: 0 0 auto;
    display: block;
    position: relative;
    z-index: 2;
    padding: 0.7rem 1rem;
    border-top: 1px solid rgba(148, 163, 184, 0.18);
    background: #fff !important;
    color: #2563eb !important;
    font-size: 0.76rem;
    font-weight: 800;
    text-align: center;
    text-decoration: none;
  }
  .topbar-notification-view-all:hover {
    background: rgba(37, 99, 235, 0.04) !important;
    color: #1d4ed8 !important;
  }

  /* Role Switcher Modal - align with themed modals in kumpulan-pengguna */
  #switchRoleModal {
    z-index: 11020 !important;
  }
  .modal-backdrop.show {
    z-index: 11010 !important;
  }
  #switchRoleModal,
  #switchRoleModal .modal-dialog,
  #switchRoleModal .modal-content,
  #switchRoleModal .modal-content::before,
  #switchRoleModal .modal-content::after {
    box-shadow: none !important;
    outline: 0 !important;
    filter: none !important;
  }
  #switchRoleModal .modal-dialog {
    max-width: 700px;
    border: 0 !important;
    background: transparent !important;
  }
  #switchRoleModal .modal-content {
    border: none;
    border-radius: 8px;
    overflow: hidden;
    background: #fff;
  }
  #switchRoleModal .modal-header {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: #fff;
    border-bottom: none;
    padding: 1.1rem 1.35rem;
  }
  #switchRoleModal .modal-header .modal-title {
    color: #fff;
    font-weight: 600;
    font-size: 1.25rem;
    letter-spacing: 0.3px;
    display: flex;
    align-items: center;
    gap: 0.75rem;
  }
  #switchRoleModal .modal-header .modal-title i {
    font-size: 1.5rem;
    opacity: 0.95;
  }
  #switchRoleModal .modal-header .btn-close { filter: brightness(0) invert(1); opacity: 0.9; }
  #switchRoleModal .modal-header .btn-close:hover { opacity: 1; }
  #switchRoleModal .modal-body {
    padding: 1.35rem;
    background: #fff;
  }
  #switchRoleModal .modal-footer {
    padding: 1rem 1.35rem;
    background-color: #f8f9fa;
    border-top: 1px solid rgba(0, 0, 0, 0.08);
    border-radius: 0 0 8px 8px;
  }
  #switchRoleModal .form-section {
    margin-bottom: 1.5rem;
    padding-bottom: 1.25rem;
    border-bottom: 2px solid #e9ecef;
  }
  #switchRoleModal .form-section:last-child {
    border-bottom: none;
    margin-bottom: 0;
    padding-bottom: 0;
  }
  #switchRoleModal .form-section-title {
    font-size: 0.85rem;
    font-weight: 700;
    color: #495057;
    text-transform: uppercase;
    letter-spacing: 1px;
    margin-bottom: 1.25rem;
    padding-bottom: 0.625rem;
    border-bottom: 3px solid #667eea;
    display: flex;
    align-items: center;
  }
  #switchRoleModal .form-section-title i { margin-right: 0.5rem; color: #667eea; }
  #switchRoleModal .modal-footer .btn {
    padding: 0.5rem 1.15rem;
    font-weight: 600;
    border-radius: 8px;
  }
  #switchRoleModal .role-list { display: grid; gap: 0.75rem; }
  #switchRoleModal .role-item {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    padding: 0.75rem 1rem;
    border: 2px solid #e9ecef;
    border-radius: 8px;
    background: #fff;
    cursor: pointer;
    transition: all 0.2s ease;
  }
  #switchRoleModal .role-item:hover {
    border-color: #667eea;
    box-shadow: 0 4px 10px rgba(102, 126, 234, 0.12);
  }
  #switchRoleModal .role-item input[type="radio"] { transform: scale(1.1); }
  #switchRoleModal .role-label { font-weight: 600; color: #212529; }

  /* Development Mode Tab - compact, softer indicator */
  .dev-mode-tab {
    position: fixed;
    top: 0.48rem;
    left: 50%;
    transform: translateX(-50%);
    z-index: 10001;
    min-width: 0;
    width: min(470px, calc(100% - 320px));
    max-width: calc(100% - 320px);
    display: block;
    background:
      linear-gradient(180deg, rgba(255,255,255,0.96) 0%, rgba(248,250,252,0.98) 100%);
    color: #16324f;
    padding: 0;
    border: 1px solid rgba(148, 163, 184, 0.22);
    border-radius: 999px;
    box-shadow: 0 8px 18px rgba(15, 23, 42, 0.08);
    backdrop-filter: blur(12px);
    -webkit-backdrop-filter: blur(12px);
    font-size: 0.75rem;
    letter-spacing: 0.01em;
    pointer-events: none;
    transition: width 0.2s ease, border-radius 0.2s ease, box-shadow 0.2s ease;
  }
  .dev-mode-tab.is-expanded {
    width: min(470px, calc(100% - 320px));
    border-radius: 22px;
    box-shadow: 0 14px 30px rgba(15, 23, 42, 0.12);
  }
  .dev-mode-tab__inner {
    padding: 0.4rem 0.5rem 0.4rem 0.72rem;
  }
  .dev-mode-tab__headline {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.72rem;
    margin-bottom: 0;
  }
  .dev-mode-tab__actions {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    pointer-events: auto;
    flex-shrink: 0;
  }
  .dev-mode-tab__title-wrap {
    display: inline-flex;
    align-items: center;
    gap: 0.58rem;
    pointer-events: none;
    min-width: 0;
  }
  .dev-mode-tab__icon {
    width: 1.62rem;
    height: 1.62rem;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    border-radius: 999px;
    background: linear-gradient(180deg, rgba(245, 158, 11, 0.14), rgba(249, 115, 22, 0.11));
    color: #c2410c;
    box-shadow: inset 0 0 0 1px rgba(245, 158, 11, 0.14);
  }
  .dev-mode-tab__icon i {
    font-size: 0.88rem;
    line-height: 1;
  }
  .dev-mode-tab__title-block {
    display: flex;
    flex-direction: column;
    min-width: 0;
    line-height: 1.05;
  }
  .dev-mode-tab__eyebrow {
    color: #64748b;
    font-size: 0.49rem;
    font-weight: 700;
    letter-spacing: 0.09em;
    text-transform: uppercase;
  }
  .dev-mode-tab__title {
    color: #0f172a;
    font-size: 0.7rem;
    font-weight: 700;
    white-space: nowrap;
  }
  .dev-mode-tab__env {
    display: inline-flex;
    align-items: center;
    padding: 0.15rem 0.46rem;
    border-radius: 999px;
    background: rgba(37, 99, 235, 0.07);
    border: 1px solid rgba(37, 99, 235, 0.1);
    color: #2563eb;
    font-size: 0.57rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.07em;
  }
  .dev-mode-tab__toggle {
    width: 1.85rem;
    height: 1.85rem;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    padding: 0;
    border: 1px solid rgba(148, 163, 184, 0.24);
    border-radius: 999px;
    background: rgba(255,255,255,0.72);
    color: #334155;
    cursor: pointer;
    pointer-events: auto;
    box-shadow: 0 1px 4px rgba(15, 23, 42, 0.06);
    transition: background-color 0.18s ease, border-color 0.18s ease, color 0.18s ease, transform 0.18s ease;
  }
  .dev-mode-tab__toggle:hover {
    background: #ffffff;
    border-color: rgba(37, 99, 235, 0.2);
    color: #1d4ed8;
    transform: translateY(-1px);
  }
  .dev-mode-tab__toggle-text {
    font-size: 0.9rem;
    line-height: 1;
    font-weight: 700;
  }
  .dev-mode-tab__grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 0.42rem;
    margin-top: 0.46rem;
    padding: 0.42rem;
    background: linear-gradient(180deg, rgba(244,247,251,0.94), rgba(238,243,248,0.96));
    border: 1px solid rgba(148, 163, 184, 0.16);
    border-radius: 18px;
    box-shadow: inset 0 1px 0 rgba(255,255,255,0.75);
  }
  .dev-mode-tab__grid[hidden] {
    display: none;
  }
  .dev-mode-tab:not(.is-expanded) {
    width: min(470px, calc(100% - 320px));
  }
  .dev-mode-tab__item {
    display: flex;
    flex-direction: column;
    gap: 0.16rem;
    min-width: 0;
    padding: 0.44rem 0.52rem;
    background: rgba(255,255,255,0.72);
    border: 1px solid rgba(148, 163, 184, 0.14);
    border-radius: 12px;
  }
  .dev-mode-tab__label {
    color: #64748b;
    font-size: 0.5rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.08em;
  }
  .dev-mode-tab__value {
    color: #0f172a;
    font-size: 0.66rem;
    font-weight: 700;
    line-height: 1.25;
    word-break: break-word;
  }
  .dev-mode-tab__value--mono {
    font-family: Consolas, "Courier New", monospace;
    font-size: 0.61rem;
    color: #1e293b;
  }

  @media (max-width: 991.98px) {
    .dev-mode-tab {
      min-width: 0;
      width: min(430px, calc(100% - 220px));
      max-width: calc(100% - 220px);
    }
    .dev-mode-tab.is-expanded {
      width: min(430px, calc(100% - 220px));
    }
    .dev-mode-tab:not(.is-expanded) {
      width: min(430px, calc(100% - 220px));
    }
  }

  @media (max-width: 575.98px) {
    .dev-mode-tab {
      top: 0.35rem;
      width: calc(100% - 16px);
      max-width: calc(100% - 16px);
    }
    .dev-mode-tab__inner {
      padding: 0.32rem 0.38rem 0.32rem 0.5rem;
    }
    .dev-mode-tab__headline {
      gap: 0.4rem;
    }
    .dev-mode-tab__title {
      font-size: 0.66rem;
    }
    .dev-mode-tab__env {
      padding-inline: 0.42rem;
    }
    .dev-mode-tab__grid {
      grid-template-columns: 1fr;
      gap: 0.42rem;
    }
  }
</style>

<!-- ========== Topbar JS (kalis block) ========== -->
<script>
  document.addEventListener('DOMContentLoaded', function () {
    const devModeTab = document.getElementById('dev-mode-tab');
    const devModeToggle = document.getElementById('dev-mode-tab-toggle');
    const devModeDetails = document.getElementById('dev-mode-tab-details');
    const devModeToggleText = devModeToggle?.querySelector('.dev-mode-tab__toggle-text');

    if (!devModeTab || !devModeToggle || !devModeDetails || !devModeToggleText) return;

    const storageKey = 'dev-mode-tab-expanded';
    const setExpanded = (expanded) => {
      devModeTab.classList.toggle('is-expanded', expanded);
      devModeDetails.hidden = !expanded;
      devModeToggle.setAttribute('aria-expanded', expanded ? 'true' : 'false');
      devModeToggle.setAttribute('aria-label', expanded ? 'Minimize development mode details' : 'Expand development mode details');
      devModeToggleText.textContent = expanded ? '-' : '+';
      try {
        localStorage.setItem(storageKey, expanded ? '1' : '0');
      } catch (e) {
        // ignore storage failure
      }
    };

    let expanded = false;
    try {
      expanded = localStorage.getItem(storageKey) === '1';
    } catch (e) {
      expanded = false;
    }
    setExpanded(expanded);

    devModeToggle.addEventListener('click', function () {
      setExpanded(!devModeTab.classList.contains('is-expanded'));
    });
  });

  // Paksa toggle dropdown + auto-tutup dropdown lain dalam #topbar
  document.addEventListener('click', function (e) {
    const toggle = e.target.closest('#topbar [data-bs-toggle="dropdown"]');
      if (!toggle) return;

      e.preventDefault();
      e.stopImmediatePropagation();

      const thisLi = toggle.closest('.dropdown');

      // Tutup SEMUA dropdown lain yang sedang terbuka
      document.querySelectorAll('#topbar .dropdown-menu.show').forEach(menu => {
        const li = menu.closest('.dropdown');
        if (!thisLi || li !== thisLi) {
          const otherT = li?.querySelector('[data-bs-toggle="dropdown"]');
          if (otherT) bootstrap.Dropdown.getOrCreateInstance(otherT).hide();
          else menu.classList.remove('show'); // fallback
        }
      });

      // Toggle dropdown yang diklik
      const dd   = bootstrap.Dropdown.getOrCreateInstance(toggle);
      const menu = thisLi?.querySelector('.dropdown-menu');
      (menu && menu.classList.contains('show')) ? dd.hide() : dd.show();
    }, { capture: true });
</script>

<!-- ========== Role Switcher JS ========== -->
<script>
  (function(){
    let btn = null;
    let modalEl = null;
    let saveBtn = null;
    let errEl = null;
    let modal = null;
    let csrf = '';
    let listEl = null;
    let emptyEl = null;
    let primaryNameEl = null;
    const successTitleTpl = <?= json_encode(__('topbar_switch_role_success_title') ?? 'Peranan {role}', JSON_UNESCAPED_UNICODE) ?>;
    const successTextTpl = <?= json_encode(__('topbar_switch_role_success_text') ?? 'Paparan dan akses sistem telah dikemas kini mengikut pilihan peranan baru iaitu <strong>{role}</strong>.', JSON_UNESCAPED_UNICODE) ?>;
    const fallbackRedirectUrl = <?= json_encode(base_url(app_config('site.default_home', 'pages/dashboard.php')), JSON_UNESCAPED_UNICODE) ?>;

    function showRoleSwitchSuccess(roleName){
      const safeRole = String(roleName || 'peranan').trim() || 'peranan';
      const title = String(successTitleTpl || '').replace('{role}', safeRole);
      const html = String(successTextTpl || '').replace('{role}', safeRole);
      if (window.Swal && typeof Swal.fire === 'function') {
        Swal.fire({
          icon: 'success',
          title: title,
          html: html,
          confirmButtonText: 'OK',
          confirmButtonColor: '#198754'
        });
      }
    }

    function initRoleSwitch(){
      btn = document.getElementById('btnSwitchRole');
      modalEl = document.getElementById('switchRoleModal');
      saveBtn = document.getElementById('switchRoleSaveBtn');
      errEl = document.getElementById('switchRoleError');
      listEl = document.getElementById('switchRoleList');
      emptyEl = document.getElementById('switchRoleEmpty');
      primaryNameEl = document.getElementById('switchRolePrimaryName');
      if (!btn || !modalEl || !saveBtn || !window.bootstrap || !bootstrap.Modal) return;
      csrf = document.getElementById('sr_csrf')?.value || '';
      modal = bootstrap.Modal.getOrCreateInstance(modalEl);

      // Clean up duplicate backdrops and modal state
      function cleanupBackdrops(){
        const backs = document.querySelectorAll('.modal-backdrop');
        if (backs.length <= 1) return;
        for (let i = 0; i < backs.length - 1; i++) {
          backs[i].parentNode && backs[i].parentNode.removeChild(backs[i]);
        }
      }
      function cleanupModalState(){
        cleanupBackdrops();
        const anyOpen = document.querySelectorAll('.modal.show').length > 0;
        if (!anyOpen) {
          document.body.classList.remove('modal-open');
          document.body.style.removeProperty('overflow');
          document.body.style.removeProperty('padding-right');
        }
      }
      modalEl.addEventListener('shown.bs.modal', cleanupBackdrops);
      modalEl.addEventListener('hidden.bs.modal', cleanupModalState);

      btn.addEventListener('click', async function(e){
        e.preventDefault();
        hideErr();
        await loadRoleOptions();
        modal.show();
      });

      saveBtn.addEventListener('click', async function(){
        hideErr();
        const selected = modalEl.querySelector('input[name=\"group_active_id\"]:checked');
      if (!selected) {
        showErr('<?= h(__('topbar_switch_role_err_select') ?? 'Sila pilih peranan.') ?>');
        return;
      }
      const groupID = parseInt(selected.value || '0', 10);
      if (!groupID) {
        showErr('<?= h(__('topbar_switch_role_err_invalid') ?? 'Sila pilih peranan yang sah.') ?>');
        return;
      }

        saveBtn.disabled = true;
        const originalHtml = saveBtn.innerHTML;
      saveBtn.innerHTML = '<i class=\"ri-loader-4-line ri-spin me-1\"></i> <?= h(__('topbar_switch_role_saving') ?? 'Menyimpan...') ?>';
        try {
          const runRoleSwitch = async () => {
            const r = await fetch('<?= h(base_url('ajax/role-switch.php')) ?>', {
              method: 'POST',
              headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-Token': csrf
              },
              body: JSON.stringify({
                groupID,
                currentPath: window.AccessUiSync && typeof window.AccessUiSync.inferCurrentPagePath === 'function'
                  ? window.AccessUiSync.inferCurrentPagePath()
                  : ''
              })
            });
            const text = await r.text();
            let j = null;
            try { j = JSON.parse(text); } catch (e) {}
            if (!r.ok || !j || j.error) {
              throw new Error((j && j.message) || 'Gagal menukar peranan.');
            }
            modal.hide();

            const roleName = String(j.group_name || '').trim();
            if (window.AccessUiSync && typeof window.AccessUiSync.applyAccessState === 'function') {
              const result = await window.AccessUiSync.applyAccessState(j, {
                refreshSidebar: true,
                redirectOnDenied: true,
                onRedirect: ({ redirectUrl }) => {
                  window.location.href = String(redirectUrl || fallbackRedirectUrl || window.location.href);
                }
              });
              if (result && result.redirected) {
                return;
              }
            } else {
              window.location.href = String(j.redirect_url || fallbackRedirectUrl || window.location.href);
              return;
            }
            showRoleSwitchSuccess(roleName || selected.closest('.role-item')?.textContent || 'peranan');
          };

          if (window.AccessUiSync && typeof window.AccessUiSync.runExclusive === 'function') {
            await window.AccessUiSync.runExclusive(runRoleSwitch);
          } else {
            await runRoleSwitch();
          }
        } catch (e) {
          showErr(e.message || 'Gagal menukar peranan.');
        } finally {
          saveBtn.disabled = false;
          saveBtn.innerHTML = originalHtml;
        }
      });
    }

    function showErr(msg){
      if (!errEl) return;
      errEl.textContent = msg || 'Ralat tidak diketahui.';
      errEl.classList.remove('d-none');
    }
    function hideErr(){
      if (!errEl) return;
      errEl.classList.add('d-none');
    }

    async function loadRoleOptions(){
      if (!listEl || !emptyEl) return;
      try {
        const r = await fetch('<?= h(base_url('ajax/role-switch-roles.php')) ?>', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-Token': csrf },
          body: JSON.stringify({ action: 'list' })
        });
        const text = await r.text();
        let j = null;
        try { j = JSON.parse(text); } catch (e) {}
        if (!r.ok || !j || j.error) {
          return;
        }

        const defaultId = parseInt(j.default?.id || 0, 10);
        const defaultName = String(j.default?.name || '').trim();
        const activeId = parseInt(j.active_id || 0, 10);
        const roles = Array.isArray(j.roles) ? j.roles : [];

        if (primaryNameEl && defaultName) {
          primaryNameEl.textContent = defaultName;
        }

        listEl.innerHTML = '';
        const primaryTag = '<?= h(__('topbar_switch_role_primary_tag') ?? 'Peranan Utama') ?>';
        function addRole(id, name, isPrimary, checked){
          const label = document.createElement('label');
          label.className = 'role-item';
          const input = document.createElement('input');
          input.type = 'radio';
          input.name = 'group_active_id';
          input.value = String(id);
          if (checked) input.checked = true;
          const span = document.createElement('span');
          span.className = 'role-label';
          span.textContent = name || '';
          if (isPrimary) {
            const tag = document.createElement('span');
            tag.className = 'text-muted';
            tag.textContent = ` (${primaryTag})`;
            span.appendChild(tag);
          }
          label.appendChild(input);
          label.appendChild(span);
          listEl.appendChild(label);
        }

        if (defaultId > 0) {
          addRole(defaultId, defaultName || '<?= h(__('topbar_switch_role_primary_label') ?? 'Peranan utama') ?>', true, activeId ? activeId === defaultId : true);
        }
        roles.forEach(r => {
          const rid = parseInt(r.id || r.f_groupID || 0, 10);
          const rname = String(r.name || r.f_groupName || '').trim();
          if (!rid || rid === defaultId) return;
          addRole(rid, rname, false, activeId === rid);
        });

        const hasAny = listEl.querySelectorAll('input[name="group_active_id"]').length > 0;
        if (!hasAny) {
          emptyEl.classList.remove('d-none');
          listEl.classList.add('d-none');
        } else {
          emptyEl.classList.add('d-none');
          listEl.classList.remove('d-none');
        }
        saveBtn.disabled = !hasAny;
      } catch (e) {
        // silent fallback to existing server-rendered list
      }
    }

    if (document.readyState === 'loading') {
      document.addEventListener('DOMContentLoaded', initRoleSwitch);
    } else {
      initRoleSwitch();
    }
  })();
</script>

<?php if ($isImpersonating): ?>
<script>
  (function(){
    function stopImpersonation() {
      const button = document.getElementById('btnStopImpersonation');
      if (!button) return;
      const original = button.innerHTML;
      button.disabled = true;
      button.innerHTML = '<i class="ri-loader-4-line ri-spin me-1"></i><?= h(__('impersonation_stopping') ?: 'Stopping...') ?>';
      window.showImpersonationBoxLoader('<?= h(__('impersonation_loading_stop') ?: 'Restoring your account...') ?>');
      const form = new FormData();
      form.set('csrf_token', <?= json_encode($csrfToken, JSON_UNESCAPED_UNICODE) ?>);
      fetch(<?= json_encode(base_url('ajax/impersonation-stop.php'), JSON_UNESCAPED_SLASHES) ?>, {
        method: 'POST',
        body: form,
        credentials: 'same-origin',
        noLoader: true,
        headers: { 'X-Requested-With': 'XMLHttpRequest', 'X-No-Loader': '1' }
      })
        .then(function(response){ return response.json().catch(function(){ return {}; }).then(function(data){ return { response, data }; }); })
        .then(function(result){
          if (!result.response.ok || result.data.success !== true) {
            throw new Error(result.data.message || '<?= h(__('impersonation_stop_failed') ?: 'Unable to stop View As mode.') ?>');
          }
          window.location.href = result.data.redirect || <?= json_encode(base_url('pages/senarai-pengguna.php'), JSON_UNESCAPED_SLASHES) ?>;
        })
        .catch(function(error){
          window.hideImpersonationBoxLoader();
          button.disabled = false;
          button.innerHTML = original;
          if (window.Swal) {
            Swal.fire({ icon: 'error', title: '<?= h(__('userList_error_title') ?: 'Ralat') ?>', text: error.message || '<?= h(__('impersonation_stop_failed') ?: 'Unable to stop View As mode.') ?>' });
          } else {
            alert(error.message || '<?= h(__('impersonation_stop_failed') ?: 'Unable to stop View As mode.') ?>');
          }
        });
    }
    document.addEventListener('click', function(event){
      const button = event.target && event.target.closest ? event.target.closest('#btnStopImpersonation') : null;
      if (!button) return;
      event.preventDefault();
      stopImpersonation();
    });
  })();
</script>
<?php endif; ?>

<?php if (!empty($roleSwitchFlash) && is_array($roleSwitchFlash)): 
  $roleName = trim((string)($roleSwitchFlash['group_name'] ?? ''));
?>
<script>
  (function(){
    const roleName = <?= json_encode($roleName, JSON_UNESCAPED_UNICODE) ?> || 'peranan';
    const titleTpl = <?= json_encode(__('topbar_switch_role_success_title') ?? 'Peranan {role}', JSON_UNESCAPED_UNICODE) ?>;
    const textTpl = <?= json_encode(__('topbar_switch_role_success_text') ?? 'Paparan dan akses sistem telah dikemas kini mengikut pilihan peranan baru iaitu <strong>{role}</strong>.', JSON_UNESCAPED_UNICODE) ?>;
    const title = String(titleTpl).replace('{role}', roleName);
    const html = String(textTpl).replace('{role}', roleName);
    function showMsg(){
      if (window.Swal) {
        Swal.fire({
          icon: 'success',
          title: title,
          html: html,
          confirmButtonText: 'OK',
          confirmButtonColor: '#198754'
        });
      } else {
        alert(title + '\n' + html.replace(/<[^>]+>/g, ''));
      }
    }
    if (document.readyState === 'loading') {
      document.addEventListener('DOMContentLoaded', showMsg);
    } else {
      showMsg();
    }
  })();
</script>
<?php endif; ?>

<!-- ========== TOGGLE BUTTON ========== -->
<script>
  (function(){
    const KEY = 'sidenav-size';                  // simpan state
    const html = document.documentElement;
    const body = document.body;
    const safeStorage = {
      get(k){ try { return localStorage.getItem(k); } catch(e){ return null; } },
      set(k,v){ try { localStorage.setItem(k,v); return true; } catch(e){ return false; } }
    };

    // Apply saved state on load (ikut layout asal)
    document.addEventListener('DOMContentLoaded', () => {
      const saved = safeStorage.get(KEY);
      if (saved === 'condensed' || saved === 'default') {
        html.setAttribute('data-sidenav-size', saved);
        body.setAttribute('data-sidebar-size', saved); // compat
      }
    });

    // Toggle via button template asal
    document.addEventListener('click', function(e){
      const btn = e.target.closest('.button-toggle-menu');
      if (!btn) return;
      e.preventDefault();
      e.stopImmediatePropagation();

      const curr = html.getAttribute('data-sidenav-size') || 'default';
      const next = (curr === 'condensed') ? 'default' : 'condensed';

      html.setAttribute('data-sidenav-size', next);
      body.setAttribute('data-sidebar-size', next); // compat lama
      safeStorage.set(KEY, next);

      // Optional: tutup dropdown yang terbuka supaya UI tak pelik
      document.querySelectorAll('#topbar .dropdown-menu.show').forEach(m => m.classList.remove('show'));

      // Optional: trigger resize untuk chart/table
      window.dispatchEvent(new Event('resize'));
    }, { capture: true });
  })();
</script>
