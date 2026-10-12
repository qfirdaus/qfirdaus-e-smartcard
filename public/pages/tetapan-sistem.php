<?php
/**
 * IQS FRAMEWORK CORE FILE
 *
 * READ ONLY for downstream project programmers.
 * Do not modify this file directly in template or cloned projects.
 * Custom changes must be implemented in project-specific files
 * or approved extension points.
 */
// pages/tetapan-sistem.php
declare(strict_types=1);

require_once __DIR__ . '/../includes/init.php';
require_login();

/* ================= Authorization Check ================= */
// Akses halaman dikawal melalui menu & kumpulan pengguna (tiada semakan role di page)

/**
 * Ã¢Å¡Â Ã¯Â¸Â JANGAN tutup session sebelum controller proses POST.
 * Jika nak lepaskan lock, buat HANYA untuk GET:
 *
 * if ($_SERVER['REQUEST_METHOD'] === 'GET' && session_status() === PHP_SESSION_ACTIVE) session_write_close();
 */

/* ================= CSRF Protection ================= */
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrfToken = $_SESSION['csrf_token'];

$PAGE_TITLE = (string)(__('config_system') ?? 'Konfigurasi Sistem');

/* ================= Controller & data ================= */
require_once __DIR__ . '/../controllers/TetapanSistemController.php';
require_once __DIR__ . '/../classes/SystemConfigConstants.php';
$controller = new TetapanSistemController();
$controller->authorizePageAccess();
$controller->handleRequest(); // Handle POST requests

$lang     = $controller->lang;
$profile  = $controller->profile;
$pageAssetVersion = (string)($_ENV['APP_ASSET_VER'] ?? date('ymdHis'));
$activeSystemTab = trim((string)($_GET['tab'] ?? 'general'));
$viewData = $controller->getPageViewData(
  $activeSystemTab === 'lang',
  $activeSystemTab === 'system-info'
);

$dbAktif = is_array($viewData['dbAktif'] ?? null) ? $viewData['dbAktif'] : [];
$mysqlInfo = is_array($viewData['mysqlInfo'] ?? null) ? $viewData['mysqlInfo'] : [];
$emailSettings = is_array($viewData['emailSettings'] ?? null) ? $viewData['emailSettings'] : [];
$generalSettings = is_array($viewData['generalSettings'] ?? null) ? $viewData['generalSettings'] : [];
$authSettings = is_array($viewData['authSettings'] ?? null) ? $viewData['authSettings'] : [];
$languageData = is_array($viewData['languageData'] ?? null) ? $viewData['languageData'] : [];
$dbRuntime = is_array($viewData['dbRuntime'] ?? null) ? $viewData['dbRuntime'] : [];
$additionalConnections = is_array($viewData['additionalConnections'] ?? null) ? $viewData['additionalConnections'] : [];
$additionalDiagnostics = is_array($viewData['additionalDiagnostics'] ?? null) ? $viewData['additionalDiagnostics'] : [];
$themeSettings = is_array($viewData['themeSettings'] ?? null) ? $viewData['themeSettings'] : [];
$aiChatbotSettings = is_array($viewData['aiChatbotSettings'] ?? null) ? $viewData['aiChatbotSettings'] : [];
$sidebarSmallImages = is_array($viewData['sidebarSmallImages'] ?? null) ? $viewData['sidebarSmallImages'] : [];
$systemInformation = is_array($viewData['systemInformation'] ?? null) ? $viewData['systemInformation'] : [];
$systemVersion = app_current_version();

$senaraiBahasa = $languageData['list']   ?? [];
$bahasaAktif   = $languageData['active'] ?? [];
$bahasaDefault = $languageData['default'] ?? ($bahasaAktif[0] ?? SystemConfigConstants::DEFAULT_LANGUAGE);

$dbRenderEnvironment = (string)($dbRuntime['dbRenderEnvironment'] ?? SystemConfigConstants::DEFAULT_SYBASE_ENVIRONMENT);
$dbRenderOperationalMode = (string)($dbRuntime['dbRenderOperationalMode'] ?? SystemConfigConstants::DEFAULT_SYBASE_OPERATIONAL_MODE);
$activeLogical = (string)($dbRuntime['activeLogical'] ?? 'ehrmdb');
$activeBase = (string)($dbRuntime['activeBase'] ?? 'sybase_ehrmdb');
$runtimeStaffBase = (string)($dbRuntime['runtimeStaffBase'] ?? 'sybase_staff_prod');
$runtimeStudentBase = (string)($dbRuntime['runtimeStudentBase'] ?? 'sybase_student_prod');
$studentRuntimeLabel = (string)($dbRuntime['studentRuntimeLabel'] ?? (__('config_tab_db_runtime_disabled') ?? 'Disabled'));
$mainMysqlEnvironment = (string)($dbRuntime['mainMysqlEnvironment'] ?? SystemConfigConstants::DEFAULT_MAIN_DB_ENVIRONMENT);
$mysqlDriver = (string)($dbRuntime['mysqlDriver'] ?? 'mysql');
$mysqlDsn = (string)($dbRuntime['mysqlDsn'] ?? '');
$mysqlUser = (string)($dbRuntime['mysqlUser'] ?? '-');
$mysqlHost = (string)($dbRuntime['mysqlHost'] ?? '-');
$mysqlDatabase = (string)($dbRuntime['mysqlDatabase'] ?? '-');
$mysqlActiveResolvedKey = (string)($dbRuntime['mysqlActiveResolvedKey'] ?? '-');
$mysqlProdTarget = is_array($dbRuntime['mysqlProdTarget'] ?? null) ? $dbRuntime['mysqlProdTarget'] : [];
$mysqlDevTarget = is_array($dbRuntime['mysqlDevTarget'] ?? null) ? $dbRuntime['mysqlDevTarget'] : [];
$mysqlProdTargetText = trim((string)($mysqlProdTarget['host'] ?? '-')) . ' / ' . trim((string)($mysqlProdTarget['database'] ?? '-')) . ' / ' . trim((string)($mysqlProdTarget['user'] ?? '-'));
$mysqlDevTargetText = trim((string)($mysqlDevTarget['host'] ?? '-')) . ' / ' . trim((string)($mysqlDevTarget['database'] ?? '-')) . ' / ' . trim((string)($mysqlDevTarget['user'] ?? '-'));
$mysqlSameTarget = (bool)($dbRuntime['mysqlSameTarget'] ?? false);
$mysqlProdDedicated = (bool)($dbRuntime['mysqlProdDedicated'] ?? false);
$mysqlDevDedicated = (bool)($dbRuntime['mysqlDevDedicated'] ?? false);
$topbar = (string)($themeSettings['topbarColor'] ?? SystemConfigConstants::DEFAULT_THEME_TOPBAR);
$sidebar = (string)($themeSettings['sidebarColor'] ?? SystemConfigConstants::DEFAULT_THEME_SIDEBAR);
$layout = (string)($themeSettings['layoutMode'] ?? SystemConfigConstants::DEFAULT_THEME_LAYOUT);

$tetapanSistemJsKeys = [
  'config_alert_no',
  'config_js_berjaya',
  'config_js_btn_loading_save',
  'config_js_btn_ok',
  'config_js_btn_ya_simpan',
  'config_js_btn_ya_teruskan',
  'config_tab_auth',
  'config_auth_summary_warnings',
  'config_auth_summary_status_ok',
  'config_auth_summary_status_invalid_note',
  'config_auth_status_valid',
  'config_auth_status_warning',
  'config_auth_status_invalid',
  'config_auth_summary_maintenance_on',
  'config_auth_summary_maintenance_off',
  'config_auth_summary_staff_enabled',
  'config_auth_summary_staff_disabled',
  'config_auth_summary_student_enabled',
  'config_auth_summary_student_disabled',
  'config_auth_summary_public_enabled',
  'config_auth_summary_public_disabled',
  'config_auth_summary_sso_enabled',
  'config_auth_summary_sso_disabled',
  'config_auth_summary_staff_auto_provision_enabled',
  'config_auth_summary_staff_auto_provision_disabled',
  'config_auth_summary_student_auto_provision_enabled',
  'config_auth_summary_student_auto_provision_disabled',
  'config_auth_warning_sso_disabled_mode',
  'config_auth_warning_all_categories_blocked',
  'config_auth_warning_staff_auto_provision_group_missing',
  'config_auth_warning_student_auto_provision_group_missing',
  'config_auth_warning_staff_auto_provision_category_disabled',
  'config_auth_warning_student_auto_provision_category_disabled',
  'config_auth_warning_staff_auto_provision_route_manual',
  'config_auth_warning_student_auto_provision_route_manual',
  'config_auth_sso_mode_all_note',
  'config_auth_sso_mode_manual_note',
  'config_auth_sso_mode_hybrid_note',
  'config_auth_enabled',
  'config_auth_disabled',
  'config_auth_allowed',
  'config_auth_blocked',
  'config_auth_sso_mode_all',
  'config_auth_sso_mode_manual',
  'config_auth_sso_mode_hybrid',
  'config_js_confirm_bahasa',
  'config_js_confirm_db',
  'config_js_confirm_emel',
  'config_js_confirm_general',
  'config_js_confirm_auth',
  'config_js_confirm_tema',
  'config_js_confirm_uji_emel',
  'config_js_emel_berjaya',
  'config_js_emel_gagal',
  'config_js_input_uji_emel',
  'config_js_label_uji_emel',
  'config_js_pilih_bahasa',
  'config_js_pilih_bahasa_default',
  'config_js_placeholder_uji_emel',
  'config_js_ralat',
  'config_js_ralat_sistem',
  'config_js_tiada_bahasa',
  'config_js_tiada_bahasa_default',
  'config_js_uji_emel_btn',
  'config_js_uji_emel_btn_default',
  'config_js_uji_emel_btn_loading',
  'config_js_valid_email_format',
  'config_js_valid_email_full',
  'config_js_valid_emel_kosong',
  'config_js_invalid_input',
  'config_js_field_fallback_label',
  'config_js_invalid_server_response',
  'config_js_module_not_ready',
  'config_js_save_failed',
  'config_js_save_success_default',
  'config_js_save_system_error',
  'config_js_system_error_title',
  'config_js_valid_host_format',
  'config_js_valid_port_range',
];

$langMapForJs = function_exists('lang_lines') ? lang_lines((string)$lang) : [];
if (is_array($langMapForJs)) {
  $translations_js = array_merge(
    $translations_js ?? [],
    array_intersect_key($langMapForJs, array_flip($tetapanSistemJsKeys))
  );
}

$translationBundlesJs = [];
$translationLangCodes = array_values(array_unique(array_filter(array_merge(
  [$lang, (string)$bahasaDefault],
  array_map('strval', $senaraiBahasa),
  array_map('strval', $bahasaAktif),
  ['ms', 'en']
), static function ($code): bool {
  return preg_match('/^[a-z]{2}(?:-[A-Z]{2})?$/', (string)$code) === 1;
})));

$normaliseTranslationMapForJs = static function (array $map): array {
  $normalised = [];
  foreach ($map as $key => $value) {
    if (is_scalar($value) || $value === null) {
      $normalised[(string)$key] = (string)$value;
    }
  }
  return $normalised;
};

foreach ($translationLangCodes as $translationLangCode) {
  $bundleMap = function_exists('lang_lines') ? lang_lines((string)$translationLangCode) : [];
  if (is_array($bundleMap) && $bundleMap !== []) {
    // Runtime JS halaman ini hanya menggunakan namespace config_*.
    $configBundle = array_filter(
      $bundleMap,
      static fn($key): bool => str_starts_with((string)$key, 'config_'),
      ARRAY_FILTER_USE_KEY
    );
    $translationBundlesJs[$translationLangCode] = $normaliseTranslationMapForJs($configBundle);
  }
}

if (isset($translationBundlesJs[$lang])) {
  $translations_js = $translationBundlesJs[$lang];
}

?>
<!DOCTYPE html>
<html lang="<?= htmlspecialchars($lang, ENT_QUOTES, 'UTF-8') ?>" data-bs-theme="<?= htmlspecialchars($_SESSION['theme.layout'] ?? 'light', ENT_QUOTES, 'UTF-8') ?>">

<head>
  <?php
    // Matikan plugin berat untuk page ni (kalau head.php guna flags)
    $NEED_DATERANGE  = false;
    $NEED_VECTORMAP  = false;
    $NEED_DATATABLES = false;
    $NEED_SELECT2    = false;
    include __DIR__ . '/../includes/head.php';
  ?>
  <meta name="csrf-token" content="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">

  <link rel="stylesheet" href="<?= asset_url('css/datatables-standard.css') ?>?v=<?= urlencode($pageAssetVersion) ?>">
  <link rel="stylesheet" href="<?= base_url('assets/vendor/select2/css/select2.min.css') ?>?v=<?= urlencode($pageAssetVersion) ?>">
  <link rel="stylesheet" href="<?= asset_url('css/pages/tetapan-sistem.css') ?>?v=<?= urlencode($pageAssetVersion) ?>">

  <!-- Translation map (senyap) -->
  <script>
    window.__currentLang = <?= json_encode($lang, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;
    window.__translationBundles = <?= json_encode($translationBundlesJs, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;
    window.__translations = (window.__translationBundles && window.__translationBundles[window.__currentLang])
      ? window.__translationBundles[window.__currentLang]
      : <?= json_encode($translations_js ?? [], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;
    window.__setRuntimeLanguage = function (lang) {
      var nextLang = String(lang || '').trim();
      if (!nextLang || !window.__translationBundles || !window.__translationBundles[nextLang]) {
        return false;
      }
      window.__currentLang = nextLang;
      window.__translations = window.__translationBundles[nextLang] || {};
      document.documentElement.setAttribute('lang', nextLang);
      return true;
    };
    window.__ = function (key) {
      var dict = window.__translations || {};
      if (Object.prototype.hasOwnProperty.call(dict, key)) {
        var val = dict[key];
        return (val && val !== '') ? val : key;
      }
      return key;
    };
  </script>
</head>

<body id="body-layout" class="system-settings-page"
      data-topbar-color="<?= htmlspecialchars($_SESSION['theme.topbar'] ?? 'light', ENT_QUOTES, 'UTF-8') ?>"
      data-menu-color="<?= htmlspecialchars($_SESSION['theme.menu']   ?? 'light', ENT_QUOTES, 'UTF-8') ?>"
      data-layout="vertical"
      data-sidebar-size="default">

  <div class="wrapper">
    <?php include __DIR__ . '/../includes/topbar.php'; ?>
    <?php include __DIR__ . '/../includes/sidebar.php'; ?>

    <div class="content-page">
      <div class="content">
        <div class="container-fluid">

          <!-- Tajuk & Breadcrumb -->
          <div class="row mb-3">
            <div class="col-12">
              <div class="page-title-box d-flex justify-content-between align-items-center flex-wrap">
                <div class="system-settings-title-main">
                  <h4 class="page-title"><i class="ri-settings-3-line"></i> <?= h(__('config_system')) ?></h4>
                  <p class="system-settings-subtitle"><?= h(__('config_page_intro')) ?></p>
                </div>
                <div class="page-title-right">
                  <span class="system-version-badge"><i class="ri-git-branch-line"></i><?= h(app_current_version_label()) ?></span>
                  <ol class="breadcrumb m-0">
                    <li class="breadcrumb-item"><a href="dashboard.php"><i class="ri-home-4-line align-middle me-1"></i> <?= __('breadcrumb_home') ?? 'Home' ?></a></li>
                    <li class="breadcrumb-item active">
                      <i class="ri-settings-3-line align-middle me-1"></i> <?= __('config_system') ?? 'Konfigurasi Sistem' ?>
                    </li>
                  </ol>
                </div>
              </div>
            </div>
          </div>

          <section class="system-settings-workspace">
          <div class="system-settings-notice">
            <i class="ri-information-line"></i>
            <span><?= h(__('config_page_global_notice')) ?></span>
          </div>

          <!-- Tab Navigasi -->
          <div class="system-settings-tabs-wrap">
            <ul class="nav nav-tabs" id="systemSettingsTabs" role="tablist">
              <li class="nav-item">
                <a class="nav-link <?= (($_GET['tab'] ?? '') === 'general' || !isset($_GET['tab'])) ? 'active' : '' ?>" data-bs-toggle="tab" href="#general-tab" role="tab" aria-selected="<?= (($_GET['tab'] ?? '') === 'general' || !isset($_GET['tab'])) ? 'true' : 'false' ?>">
                  <i class="ri-settings-3-line me-1"></i> <?= __('config_tab_general') ?? 'Umum' ?>
                </a>
              </li>
              <li class="nav-item">
                <a class="nav-link <?= ($_GET['tab'] ?? '') === 'auth' ? 'active' : '' ?>" data-bs-toggle="tab" href="#auth-tab" role="tab" aria-selected="<?= ($_GET['tab'] ?? '') === 'auth' ? 'true' : 'false' ?>">
                  <i class="ri-shield-keyhole-line me-1"></i> <?= __('config_tab_auth') ?? 'Login Policy' ?>
                </a>
              </li>
              <li class="nav-item">
                <a class="nav-link <?= ($_GET['tab'] ?? '') === 'email' ? 'active' : '' ?>" data-bs-toggle="tab" href="#email-tab" role="tab" aria-selected="<?= ($_GET['tab'] ?? '') === 'email' ? 'true' : 'false' ?>">
                  <i class="ri-mail-settings-line me-1"></i> <?= __('config_tab_emel') ?? 'Emel' ?>
                </a>
              </li>
              <li class="nav-item">
                <a class="nav-link <?= ($_GET['tab'] ?? '') === 'db' ? 'active' : '' ?>" data-bs-toggle="tab" href="#db-tab" role="tab" aria-selected="<?= ($_GET['tab'] ?? '') === 'db' ? 'true' : 'false' ?>">
                  <i class="ri-database-2-line me-1"></i> <?= __('config_tab_db') ?? 'Pangkalan Data' ?>
                </a>
              </li>
              <li class="nav-item">
                <a class="nav-link <?= ($_GET['tab'] ?? '') === 'theme' ? 'active' : '' ?>" data-bs-toggle="tab" href="#theme-tab" role="tab" aria-selected="<?= ($_GET['tab'] ?? '') === 'theme' ? 'true' : 'false' ?>">
                  <i class="ri-palette-line me-1"></i> <?= __('config_tab_tema') ?? 'Tema' ?>
                </a>
              </li>
              <li class="nav-item">
                <a class="nav-link <?= ($_GET['tab'] ?? '') === 'lang' ? 'active' : '' ?>" data-bs-toggle="tab" href="#lang-tab" role="tab" aria-selected="<?= ($_GET['tab'] ?? '') === 'lang' ? 'true' : 'false' ?>">
                  <i class="ri-translate-2 me-1"></i> <?= __('config_tab_bahasa') ?? 'Bahasa' ?>
                </a>
              </li>
              <li class="nav-item">
                <a class="nav-link <?= ($_GET['tab'] ?? '') === 'ai-chatbot' ? 'active' : '' ?>" data-bs-toggle="tab" href="#ai-chatbot-tab" role="tab" aria-selected="<?= ($_GET['tab'] ?? '') === 'ai-chatbot' ? 'true' : 'false' ?>">
                  <i class="ri-chat-3-line me-1"></i> <?= __('config_tab_ai_chatbot') ?? 'AI Chatbot' ?>
                </a>
              </li>
              <li class="nav-item">
                <a class="nav-link <?= ($_GET['tab'] ?? '') === 'system-info' ? 'active' : '' ?>" href="<?= h(url_with_param('tab', 'system-info')) ?>" role="tab" aria-controls="system-info-tab" aria-selected="<?= ($_GET['tab'] ?? '') === 'system-info' ? 'true' : 'false' ?>">
                  <i class="ri-information-line me-1"></i> <?= __('config_tab_system_info') ?? 'System Information' ?>
                </a>
              </li>
            </ul>
          </div>

          <!-- Kandungan Tab -->
          <div class="tab-content system-settings-tab-content">

            <?php include __DIR__ . '/partials/tetapan-sistem/tab-general.php'; ?>

            <?php include __DIR__ . '/partials/tetapan-sistem/tab-login-policy.php'; ?>

            <?php include __DIR__ . '/partials/tetapan-sistem/tab-email.php'; ?>

            <?php include __DIR__ . '/partials/tetapan-sistem/tab-database.php'; ?>

            <?php include __DIR__ . '/partials/tetapan-sistem/tab-theme.php'; ?>

            <?php include __DIR__ . '/partials/tetapan-sistem/tab-language.php'; ?>

            <?php include __DIR__ . '/partials/tetapan-sistem/tab-ai-chatbot.php'; ?>

            <?php include __DIR__ . '/partials/tetapan-sistem/tab-system-information.php'; ?>

          </div><!-- /tab-content -->
          </section>
        </div>
      </div>

      <?php include __DIR__ . '/../includes/footer.php'; ?>
    </div>
  </div>

  <?php
    // Flags JS vendor - hanya yang perlu
    $NEED_JQUERY     = true;
    $NEED_SWEETALERT = true;
    $NEED_DT_JS      = true;
    $NEED_SELECT2_JS = false;
    include __DIR__ . '/../includes/script.php';
  ?>
  <script>
    (function () {
      var script = document.createElement('script');
      script.src = <?= json_encode(base_url('assets/vendor/select2/js/select2.full.min.js') . '?v=' . time(), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>;
      script.onload = function () {
        window.__select2ScriptLoaded = true;
        if (typeof window.__initAiChatbotModelSelect2 === 'function') {
          window.__initAiChatbotModelSelect2();
        }
      };
      document.head.appendChild(script);
    })();
  </script>


  <script>
    window.__tetapanShowGeneralSubtab = function (paneId, trigger, event) {
      if (event) {
        event.preventDefault();
        event.stopImmediatePropagation();
      }

      var pane = document.getElementById(paneId);
      if (!pane) {
        return false;
      }

      var nav = trigger ? trigger.closest('.general-subtabs') : document.querySelector('.general-subtabs');
      if (nav) {
        nav.querySelectorAll('.nav-link').forEach(function (item) {
          item.classList.remove('active');
          item.setAttribute('aria-selected', 'false');
        });
      }

      var container = pane.parentElement;
      if (container && container.classList.contains('tab-content')) {
        container.querySelectorAll(':scope > .tab-pane').forEach(function (item) {
          item.classList.remove('show', 'active');
        });
      }

      if (trigger) {
        trigger.classList.add('active');
        trigger.setAttribute('aria-selected', 'true');
      }

      pane.classList.add('show', 'active');

      try {
        window.sessionStorage.setItem('tetapan-sistem.general-subtab', paneId);
      } catch (storageError) {
        // ignore
      }

      return false;
    };

    window.__tetapanShowAuthSubtab = function (paneId, trigger, event) {
      if (event) {
        event.preventDefault();
        event.stopImmediatePropagation();
      }

      var pane = document.getElementById(paneId);
      if (!pane) {
        return false;
      }

      var nav = trigger ? trigger.closest('.auth-subtabs') : document.querySelector('.auth-subtabs');
      if (nav) {
        nav.querySelectorAll('.nav-link').forEach(function (item) {
          item.classList.remove('active');
          item.setAttribute('aria-selected', 'false');
        });
      }

      var container = pane.parentElement;
      if (container && container.classList.contains('tab-content')) {
        container.querySelectorAll(':scope > .tab-pane').forEach(function (item) {
          item.classList.remove('show', 'active');
        });
      }

      if (trigger) {
        trigger.classList.add('active');
        trigger.setAttribute('aria-selected', 'true');
      }

      pane.classList.add('show', 'active');

      if (typeof window.__tetapanSyncAuthPolicyUi === 'function') {
        window.__tetapanSyncAuthPolicyUi();
      } else if (typeof window.__tetapanRefreshAuthPolicySummary === 'function') {
        window.__tetapanRefreshAuthPolicySummary();
      }

      try {
        window.sessionStorage.setItem('tetapan-sistem.auth-subtab', paneId);
      } catch (storageError) {
        // ignore
      }

      return false;
    };

    window.__tetapanSyncAuthPolicyUi = function () {
      var __ = window.__ || function (key) { return key; };
      var maintenanceInput = document.getElementById('auth_maintenance_mode');
      var staffInput = document.getElementById('auth_login_enable_staf');
      var studentInput = document.getElementById('auth_login_enable_pelajar');
      var publicInput = document.getElementById('auth_login_enable_umum');
      var ssoEnabledInput = document.getElementById('auth_sso_enabled');
      var ssoModeInput = document.getElementById('auth_sso_mode');
      var staffAutoProvisionInput = document.getElementById('auth_auto_provision_staf_sso');
      var studentAutoProvisionInput = document.getElementById('auth_auto_provision_pelajar_sso');
      var staffDefaultGroupInput = document.getElementById('auth_default_group_staff_code');
      var studentDefaultGroupInput = document.getElementById('auth_default_group_student_code');
      var staffHybridInput = document.getElementById('auth_sso_hybrid_staf');
      var studentHybridInput = document.getElementById('auth_sso_hybrid_pelajar');

      if (!maintenanceInput || !staffInput || !studentInput || !publicInput || !ssoEnabledInput || !ssoModeInput || !staffAutoProvisionInput || !studentAutoProvisionInput || !staffDefaultGroupInput || !studentDefaultGroupInput) {
        return;
      }

      var maintenanceOn = !!maintenanceInput.checked;
      var staffEnabled = !!staffInput.checked;
      var studentEnabled = !!studentInput.checked;
      var publicEnabled = !!publicInput.checked;
      var ssoEnabled = !!ssoEnabledInput.checked;
      var ssoMode = String(ssoModeInput.value || 'MANUAL').toUpperCase();
      var staffAutoProvision = !!staffAutoProvisionInput.checked;
      var studentAutoProvision = !!studentAutoProvisionInput.checked;
      var staffDefaultGroup = String((staffDefaultGroupInput.value || '').trim()).toUpperCase();
      var studentDefaultGroup = String((studentDefaultGroupInput.value || '').trim()).toUpperCase();
      var staffHybridMode = staffHybridInput ? String(staffHybridInput.value || 'SSO').toUpperCase() : 'SSO';
      var studentHybridMode = studentHybridInput ? String(studentHybridInput.value || 'SSO').toUpperCase() : 'SSO';
      var warnings = [];
      var staffLoginMethod = 'MANUAL';
      var studentLoginMethod = 'MANUAL';

      if (ssoEnabled) {
        if (ssoMode === 'ALL') {
          staffLoginMethod = 'SSO';
          studentLoginMethod = 'SSO';
        } else if (ssoMode === 'HYBRID') {
          staffLoginMethod = staffHybridMode === 'SSO' ? 'SSO' : 'MANUAL';
          studentLoginMethod = studentHybridMode === 'SSO' ? 'SSO' : 'MANUAL';
        }
      }

      function setBadgeState(element, active, activeText, inactiveText, activeClass, inactiveClass) {
        if (!element) {
          return;
        }
        element.className = 'badge bg-' + (active ? activeClass : inactiveClass) + '-subtle text-' + (active ? activeClass : inactiveClass);
        element.textContent = active ? activeText : inactiveText;
      }

      function renderListItems(target, items) {
        if (!target) {
          return;
        }
        target.innerHTML = '';
        (items || []).forEach(function (item) {
          var li = document.createElement('li');
          li.textContent = item;
          target.appendChild(li);
        });
      }

      setBadgeState(document.getElementById('auth-maintenance-state'), maintenanceOn, __('config_auth_enabled') || 'Enabled', __('config_auth_disabled') || 'Disabled', 'danger', 'secondary');
      setBadgeState(document.getElementById('auth-category-state-auth_login_enable_staf'), staffEnabled, __('config_auth_allowed') || 'Allowed', __('config_auth_blocked') || 'Blocked', 'success', 'secondary');
      setBadgeState(document.getElementById('auth-category-state-auth_login_enable_pelajar'), studentEnabled, __('config_auth_allowed') || 'Allowed', __('config_auth_blocked') || 'Blocked', 'success', 'secondary');
      setBadgeState(document.getElementById('auth-category-state-auth_login_enable_umum'), publicEnabled, __('config_auth_allowed') || 'Allowed', __('config_auth_blocked') || 'Blocked', 'success', 'secondary');
      setBadgeState(document.getElementById('auth-sso-enabled-state'), ssoEnabled, __('config_auth_enabled') || 'Enabled', __('config_auth_disabled') || 'Disabled', 'success', 'secondary');
      setBadgeState(document.getElementById('auth-auto-provision-state-staff'), staffAutoProvision, __('config_auth_enabled') || 'Enabled', __('config_auth_disabled') || 'Disabled', 'success', 'secondary');
      setBadgeState(document.getElementById('auth-auto-provision-state-student'), studentAutoProvision, __('config_auth_enabled') || 'Enabled', __('config_auth_disabled') || 'Disabled', 'success', 'secondary');

      var modeNote = document.getElementById('auth-sso-mode-note');
      if (modeNote) {
        if (ssoMode === 'ALL') {
          modeNote.innerHTML = '<i class="ri-information-line me-1"></i>' + ((__('config_auth_sso_mode_all_note')) || 'In ALL mode, Staff and Student users must use SSO. Public users may still log in manually.');
        } else if (ssoMode === 'HYBRID') {
          modeNote.innerHTML = '<i class="ri-information-line me-1"></i>' + ((__('config_auth_sso_mode_hybrid_note')) || 'In HYBRID mode, each category follows its own configured login method.');
        } else {
          modeNote.innerHTML = '<i class="ri-information-line me-1"></i>' + ((__('config_auth_sso_mode_manual_note')) || 'In MANUAL mode, all allowed categories use manual login.');
        }
      }

      var hybridBlock = document.getElementById('auth-hybrid-block');
      if (hybridBlock) {
        hybridBlock.classList.toggle('auth-hybrid-block-muted', ssoMode !== 'HYBRID');
      }

      var effectiveSummary = [
        maintenanceOn
          ? (__('config_auth_summary_maintenance_on') || 'Maintenance mode is enabled. Only Super Admin can log in.')
          : (__('config_auth_summary_maintenance_off') || 'Maintenance mode is disabled. Normal policy evaluation applies.'),
        staffEnabled
          ? (__('config_auth_summary_staff_enabled') || 'Staff login is enabled.')
          : (__('config_auth_summary_staff_disabled') || 'Staff login is disabled.'),
        studentEnabled
          ? (__('config_auth_summary_student_enabled') || 'Student login is enabled.')
          : (__('config_auth_summary_student_disabled') || 'Student login is disabled.'),
        publicEnabled
          ? (__('config_auth_summary_public_enabled') || 'Public login is enabled.')
          : (__('config_auth_summary_public_disabled') || 'Public login is disabled.'),
        ssoEnabled
          ? ((__('config_auth_summary_sso_enabled') || 'SSO is enabled in %s mode.').replace('%s', ssoMode))
          : (__('config_auth_summary_sso_disabled') || 'SSO is disabled. All allowed categories use manual login.'),
        staffAutoProvision
          ? ((__('config_auth_summary_staff_auto_provision_enabled') || 'Staff SSO auto provision is enabled with default group %s.').replace('%s', staffDefaultGroup || 'ADM-STAF'))
          : (__('config_auth_summary_staff_auto_provision_disabled') || 'Staff SSO auto provision is disabled.'),
        studentAutoProvision
          ? ((__('config_auth_summary_student_auto_provision_enabled') || 'Student SSO auto provision is enabled with default group %s.').replace('%s', studentDefaultGroup || 'ADM-STUDENT'))
          : (__('config_auth_summary_student_auto_provision_disabled') || 'Student SSO auto provision is disabled.')
      ];

      if (!ssoEnabled && ssoMode !== 'MANUAL') {
        warnings.push((__('config_auth_warning_sso_disabled_mode')) || 'SSO mode is configured but SSO is currently disabled.');
      }
      if (!staffEnabled && !studentEnabled && !publicEnabled) {
        warnings.push((__('config_auth_warning_all_categories_blocked')) || 'All login categories are blocked. Only Super Admin will remain able to log in.');
      }
      if (staffAutoProvision && !staffDefaultGroup) {
        warnings.push((__('config_auth_warning_staff_auto_provision_group_missing')) || 'Staff SSO auto provision is enabled but the default staff group code is empty.');
      }
      if (studentAutoProvision && !studentDefaultGroup) {
        warnings.push((__('config_auth_warning_student_auto_provision_group_missing')) || 'Student SSO auto provision is enabled but the default student group code is empty.');
      }
      if (staffAutoProvision && !staffEnabled) {
        warnings.push((__('config_auth_warning_staff_auto_provision_category_disabled')) || 'Staff SSO auto provision is enabled while staff login is disabled.');
      }
      if (studentAutoProvision && !studentEnabled) {
        warnings.push((__('config_auth_warning_student_auto_provision_category_disabled')) || 'Student SSO auto provision is enabled while student login is disabled.');
      }
      if (staffAutoProvision && staffLoginMethod !== 'SSO') {
        warnings.push((__('config_auth_warning_staff_auto_provision_route_manual')) || 'Staff SSO auto provision is enabled but the current staff login route is not SSO.');
      }
      if (studentAutoProvision && studentLoginMethod !== 'SSO') {
        warnings.push((__('config_auth_warning_student_auto_provision_route_manual')) || 'Student SSO auto provision is enabled but the current student login route is not SSO.');
      }

      renderListItems(document.getElementById('auth-summary-effective-list'), effectiveSummary);
      renderListItems(document.getElementById('auth-summary-warning-list'), warnings);

      var warningBox = document.getElementById('auth-summary-warning-box');
      if (warningBox) {
        warningBox.classList.toggle('d-none', warnings.length === 0);
      }

      var hasServerError = !!document.querySelector('#form-auth-aktif .auth-summary-box-error');
      var statusBadge = document.getElementById('auth-summary-status-badge');
      var statusText = document.getElementById('auth-summary-status-text');
      if (!hasServerError) {
        var hasWarnings = warnings.length > 0;
        if (statusBadge) {
          statusBadge.className = 'badge bg-' + (hasWarnings ? 'warning' : 'success') + '-subtle text-' + (hasWarnings ? 'warning' : 'success') + ' px-3 py-2';
          statusBadge.textContent = hasWarnings
            ? (__('config_auth_status_warning') || 'Valid with Warning')
            : (__('config_auth_status_valid') || 'Valid');
        }
        if (statusText) {
          statusText.className = (hasWarnings ? 'text-warning' : 'text-success') + ' small fw-semibold';
          statusText.textContent = hasWarnings
            ? ((__('config_auth_summary_warnings')) || 'Warnings') + ': ' + warnings[0]
            : (__('config_auth_summary_status_ok') || 'Policy snapshot is ready for runtime use.');
        }
      }
    };

    if (document.readyState === 'loading') {
      document.addEventListener('DOMContentLoaded', function () {
        if (typeof window.__tetapanSyncAuthPolicyUi === 'function') {
          window.__tetapanSyncAuthPolicyUi();
        }
      });
    } else if (typeof window.__tetapanSyncAuthPolicyUi === 'function') {
      window.__tetapanSyncAuthPolicyUi();
    }

    window.__tetapanForceRuntimeSummaryFromDbForm = window.__tetapanForceRuntimeSummaryFromDbForm || function (form) {
      var targetForm = typeof form === 'string' ? document.getElementById(form) : form;
      if (!targetForm) return;

      var checkedValue = function (name) {
        var input = targetForm.querySelector('input[name="' + name + '"]:checked');
        return input ? String(input.value || '') : '';
      };
      var setText = function (id, value) {
        var el = document.getElementById(id);
        if (el) el.textContent = String(value || '-');
      };

      var sybaseEnvironment = checkedValue('sybase_environment');
      var sybaseMode = checkedValue('sybase_operational_mode');
      var mysqlEnvironment = checkedValue('main_db_environment');
      var sybaseIsDev = sybaseEnvironment === 'development';
      var mysqlIsDev = mysqlEnvironment === 'development';
      var staffBase = sybaseIsDev ? 'sybase_staff_dev' : 'sybase_staff_prod';
      var studentBase = sybaseIsDev ? 'sybase_student_dev' : 'sybase_student_prod';
      var envLabel = sybaseIsDev ? 'Development' : 'Production';
      var mysqlEnvLabel = mysqlIsDev ? 'Development' : 'Production';
      var modeLabel = sybaseMode === 'staff_student' ? 'Staff + Student' : 'Staff Only';

      setText('db-runtime-staff', staffBase);
      setText('db-runtime-environment', envLabel);
      setText('db-runtime-mode', modeLabel);
      setText('db-runtime-mysql-environment', mysqlEnvLabel);
      setText('db-runtime-mysql-resolved-key', mysqlIsDev ? 'mysql_dev' : 'mysql_prod');

      var studentCell = document.getElementById('db-runtime-student-cell');
      if (studentCell) {
        if (sybaseMode === 'staff_student') {
          studentCell.innerHTML = '<code class="text-primary" id="db-runtime-student"></code>';
          setText('db-runtime-student', studentBase);
        } else {
          studentCell.innerHTML = '<span class="badge bg-secondary-subtle text-secondary" id="db-runtime-student"></span>';
          setText('db-runtime-student', 'Disabled');
        }
      }
    };

    window.__tetapanAjaxSubmit = function (event, form, buttonId, guardName) {
      function showInlineError(message) {
        if (window.Swal && typeof window.Swal.fire === 'function') {
          window.Swal.fire({
            icon: 'error',
            title: (window.__ && window.__('config_js_system_error_title')) || 'Ralat Sistem',
            text: message || ((window.__ && window.__('config_js_module_not_ready')) || 'Modul tetapan sistem belum siap dimuatkan. Sila cuba semula.'),
            confirmButtonText: (window.__ && window.__('config_js_btn_ok')) || 'OK'
          });
        }
      }

      function inlineSetButtonLoading(button, loading) {
        if (!button) {
          return;
        }

        if (loading) {
          button.disabled = true;
          if (!button.dataset.originalHtml) {
            button.dataset.originalHtml = button.innerHTML;
          }
          button.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> ' + (((window.__ && window.__('config_js_btn_loading_save')) || 'Saving...'));
          return;
        }

        button.disabled = false;
        if (button.dataset.originalHtml) {
          button.innerHTML = button.dataset.originalHtml;
          delete button.dataset.originalHtml;
        }
      }

      function inlineSetPageLoading(key, loading, message) {
        window.__tetapanInlineLoaderTokens = window.__tetapanInlineLoaderTokens || {};
        if (loading) {
          window.__tetapanInlineLoaderTokens[key] = message || (((window.__ && window.__('config_js_btn_loading_save')) || 'Saving...'));
          return;
        }

        delete window.__tetapanInlineLoaderTokens[key];
      }

      function inlineLanguageGuard(activeForm) {
        if (!activeForm) {
          return false;
        }
        var checked = activeForm.querySelectorAll('input[name="languages[]"]:checked');
        if (checked.length === 0) {
          if (window.Swal && typeof window.Swal.fire === 'function') {
            window.Swal.fire({
              icon: 'warning',
              title: (window.__ && window.__('config_js_tiada_bahasa')) || 'No Language Selected',
              text: (window.__ && window.__('config_js_pilih_bahasa')) || 'Please select at least one language.',
              confirmButtonText: (window.__ && window.__('config_js_btn_ok')) || 'OK'
            });
          }
          return false;
        }

        var defaultLang = activeForm.querySelector('input[name="default_language"]:checked');
        if (!defaultLang) {
          if (window.Swal && typeof window.Swal.fire === 'function') {
            window.Swal.fire({
              icon: 'warning',
              title: (window.__ && window.__('config_js_tiada_bahasa_default')) || 'No Default Language Selected',
              text: (window.__ && window.__('config_js_pilih_bahasa_default')) || 'Please select one default language from the active languages list.',
              confirmButtonText: (window.__ && window.__('config_js_btn_ok')) || 'OK'
            });
          }
          return false;
        }

        return true;
      }

      function inlineFallbackAjaxSubmit(targetForm, button) {
        if (!targetForm) {
          showInlineError();
          return false;
        }

        if (typeof targetForm.checkValidity === 'function' && !targetForm.checkValidity()) {
          if (typeof targetForm.reportValidity === 'function') {
            targetForm.reportValidity();
          }
          return false;
        }

        inlineSetButtonLoading(button, true);
        inlineSetPageLoading('fallbackAjaxSubmit', true, (((window.__ && window.__('config_js_saving_changes')) || (window.__ && window.__('config_js_btn_loading_save')) || 'Saving...')));

        var formData = new FormData(targetForm);
        formData.set('ajax', '1');

        var csrfMeta = document.querySelector('meta[name="csrf-token"]');
        var csrfToken = csrfMeta ? csrfMeta.getAttribute('content') : '';

        fetch(targetForm.getAttribute('action') || window.location.href, {
          method: 'POST',
          body: formData,
          noLoader: true,
          headers: Object.assign({
            'X-Requested-With': 'XMLHttpRequest',
            'Accept': 'application/json',
            'X-No-Loader': '1'
          }, csrfToken ? { 'X-CSRF-Token': csrfToken } : {})
          })
            .then(function (response) {
              if (!response.ok) {
                console.warn('[tetapan-sistem] ajax submit non-ok response', {
                  status: response.status,
                  url: targetForm.getAttribute('action') || window.location.href
                });
              }
              return response.json().catch(function () {
                throw new Error(((window.__ && window.__('config_js_invalid_server_response')) || 'Respons pelayan tidak sah.'));
              });
          })
          .then(function (payload) {
            if (!payload || payload.success !== true) {
              showInlineError((payload && payload.message) || (((window.__ && window.__('config_js_save_failed')) || 'Gagal menyimpan tetapan.')));
              return;
            }

            if (typeof window.__tetapanApplyPayloadUiSync === 'function') {
              window.__tetapanApplyPayloadUiSync(payload, targetForm);
            } else if (payload.tab === 'db' && typeof window.__tetapanSyncDatabaseRuntimeUi === 'function') {
              window.__tetapanSyncDatabaseRuntimeUi();
            }
            if (payload.tab === 'db' && typeof window.__tetapanForceRuntimeSummaryFromDbForm === 'function') {
              window.__tetapanForceRuntimeSummaryFromDbForm(targetForm);
            }

            if (window.Swal && typeof window.Swal.fire === 'function') {
              window.Swal.fire({
                icon: 'success',
                title: payload.title || (((window.__ && window.__('config_js_berjaya')) || 'Berjaya')),
                text: payload.message || (((window.__ && window.__('config_js_save_success_default')) || 'Tetapan berjaya disimpan.')),
                confirmButtonText: (window.__ && window.__('config_js_btn_ok')) || 'OK'
              });
            }
            })
            .catch(function (error) {
              console.warn('[tetapan-sistem] ajax submit failed', error);
              showInlineError((error && error.message) || (((window.__ && window.__('config_js_save_system_error')) || 'Ralat sistem semasa menyimpan tetapan.')));
            })
          .finally(function () {
            inlineSetButtonLoading(button, false);
            inlineSetPageLoading('fallbackAjaxSubmit', false);
          });

        return false;
      }

      if (event) {
        event.preventDefault();
        event.stopImmediatePropagation();
      }

      var targetForm = typeof form === 'string' ? document.getElementById(form) : form;
      if (!targetForm) {
        return false;
      }

      if (guardName === 'language' && typeof window.__tetapanBeforeLanguageSubmit === 'function') {
        if (window.__tetapanBeforeLanguageSubmit(targetForm) === false) {
          return false;
        }
      } else if (guardName === 'language' && inlineLanguageGuard(targetForm) === false) {
        return false;
      }

      var button = buttonId ? document.getElementById(buttonId) : null;
      var submitHandler = (typeof window.__tetapanSubmitFormWithValidation === 'function')
        ? window.__tetapanSubmitFormWithValidation
        : inlineFallbackAjaxSubmit;

      if (guardName === 'auth' && window.Swal && typeof window.Swal.fire === 'function') {
        window.Swal.fire({
          icon: 'question',
          title: (window.__ && window.__('config_tab_auth')) || 'Login Policy',
          text: (window.__ && window.__('config_js_confirm_auth')) || 'Are you sure you want to save this login policy?',
          showCancelButton: true,
          confirmButtonText: (window.__ && window.__('config_js_btn_ya_simpan')) || 'Yes, Save',
          cancelButtonText: (window.__ && window.__('config_alert_no')) || 'Cancel'
        }).then(function (result) {
          if (!result.isConfirmed) {
            return;
          }
          submitHandler(targetForm, button);
        });
        return false;
      }

      if (typeof window.__tetapanSubmitFormWithValidation === 'function') {
        submitHandler(targetForm, button);
        return false;
      }

      return submitHandler(targetForm, button);
    };

    window.__tetapanOpenEmailTest = function (event) {
      function showInlineError(message) {
        if (window.Swal && typeof window.Swal.fire === 'function') {
          window.Swal.fire({
            icon: 'error',
            title: (window.__ && window.__('config_js_system_error_title')) || 'Ralat Sistem',
            text: message || ((window.__ && window.__('config_js_module_not_ready')) || 'Modul tetapan sistem belum siap dimuatkan. Sila cuba semula.'),
            confirmButtonText: (window.__ && window.__('config_js_btn_ok')) || 'OK'
          });
        }
      }

      function inlineEmailTest() {
        var form = document.getElementById('form-emel-aktif');
        var btnUji = document.getElementById('btn-uji-emel');
        if (!form || !btnUji || !(window.Swal && typeof window.Swal.fire === 'function')) {
          showInlineError();
          return false;
        }

        var baseUrl = (window.tetapanSistemConfig && window.tetapanSistemConfig.baseUrl) || '';
        var mailFrom = form.querySelector('input[name="mail_from_address"]') ? form.querySelector('input[name="mail_from_address"]').value : '';
        var mailUsername = form.querySelector('input[name="mail_username"]') ? form.querySelector('input[name="mail_username"]').value : '';
        var defaultEmail = mailFrom || mailUsername || '';

        window.Swal.fire({
          title: (window.__ && window.__('config_js_input_uji_emel')) || 'Enter Test Email',
          input: 'email',
          inputLabel: (window.__ && window.__('config_js_label_uji_emel')) || 'Email address for test delivery',
          inputValue: defaultEmail,
          inputPlaceholder: (window.__ && window.__('config_js_placeholder_uji_emel')) || 'e.g.: apps_email@upnm.edu.my',
          showCancelButton: true,
          confirmButtonText: (window.__ && window.__('config_js_uji_emel_btn')) || 'Test Now',
          cancelButtonText: (window.__ && window.__('config_alert_no')) || 'Cancel',
          preConfirm: function (email) {
            if (!email) {
              window.Swal.showValidationMessage((window.__ && window.__('config_js_valid_emel_kosong')) || 'Email address cannot be empty');
              return false;
            }
            var emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            if (!emailRegex.test(email)) {
              window.Swal.showValidationMessage((window.__ && window.__('config_js_valid_email_full')) || 'Invalid email format. Please enter a valid email address.');
              return false;
            }
            return email;
          }
        }).then(function (result) {
          if (!result.isConfirmed) {
            return;
          }

          var formData = new FormData(form);
          formData.append('uji_email', result.value);
          var csrfMeta = document.querySelector('meta[name="csrf-token"]');
          var csrfToken = csrfMeta ? csrfMeta.getAttribute('content') : '';
          formData.append('csrf_token', csrfToken);

          btnUji.disabled = true;
          if (!btnUji.dataset.originalHtml) {
            btnUji.dataset.originalHtml = btnUji.innerHTML;
          }
          btnUji.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> ' + (((window.__ && window.__('config_js_uji_emel_btn_loading')) || 'Testing...'));
          window.__tetapanInlineEmailLoaderToken = (((window.__ && window.__('config_js_uji_emel_btn_loading')) || 'Testing...'));

            fetch(baseUrl + 'ajax/uji-emel.php', {
              method: 'POST',
              body: formData,
              noLoader: true,
              headers: Object.assign({
                'X-No-Loader': '1'
              }, csrfToken ? { 'X-CSRF-Token': csrfToken } : {})
            })
              .then(function (res) {
                if (!res.ok) {
                  console.warn('[tetapan-sistem] uji-emel non-ok response', {
                    status: res.status,
                    url: baseUrl + 'ajax/uji-emel.php'
                  });
                }
                return res.json();
              })
            .then(function (data) {
              if (data && data.success) {
                window.Swal.fire({
                  icon: 'success',
                  title: ((window.__ && window.__('config_js_berjaya')) || 'Berjaya'),
                  html: data.message || ((window.__ && window.__('config_js_emel_berjaya')) || 'Emel berjaya dihantar.')
                });
                return;
              }

              window.Swal.fire({
                icon: 'error',
                title: ((window.__ && window.__('config_js_ralat')) || 'Ralat'),
                text: (data && data.message) || ((window.__ && window.__('config_js_emel_gagal')) || 'Gagal hantar emel.')
              });
            })
              .catch(function (error) {
                console.warn('[tetapan-sistem] uji-emel request failed', error);
                window.Swal.fire({
                  icon: 'error',
                  title: ((window.__ && window.__('config_js_ralat')) || 'Ralat'),
                text: ((window.__ && window.__('config_js_ralat_sistem')) || 'Ralat sistem semasa menguji sambungan.')
              });
            })
            .finally(function () {
              btnUji.disabled = false;
              btnUji.innerHTML = btnUji.dataset.originalHtml || '<i class="ri-mail-send-line me-1"></i> ' + (((window.__ && window.__('config_js_uji_emel_btn_default')) || 'Uji Sambungan Emel'));
              window.__tetapanInlineEmailLoaderToken = null;
            });
        });

        return false;
      }

      if (event) {
        event.preventDefault();
        event.stopImmediatePropagation();
      }

      if (typeof window.__tetapanHandleEmailTest === 'function') {
        window.__tetapanHandleEmailTest();
        return false;
      }

      return inlineEmailTest();
    };

    window.tetapanSistemConfig = {
      baseUrl: <?= json_encode(rtrim(base_url(), '/') . '/', JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>,
      csrfToken: <?= json_encode($csrfToken, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>,
      initialDbSelection: {
        main_db_environment: <?= json_encode($mainMysqlEnvironment, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>,
        sybase_environment: <?= json_encode($dbRenderEnvironment, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>,
        sybase_operational_mode: <?= json_encode($dbRenderOperationalMode, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>
      },
      dbRuntime: <?= json_encode($dbRuntime, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE) ?>,
      additionalConnections: <?= json_encode($additionalConnections, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>,
      additionalDiagnostics: <?= json_encode($additionalDiagnostics, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>
    };
  </script>
  <script>
    window.__tetapanUseExternalAdditionalModule = true;
    (function () {
      'use strict';

      // Additional Connections diurus oleh assets/js/pages/tetapan-sistem.js.
      // Kekalkan blok legacy ini sebagai fallback deployment lama sahaja.
      if (window.__tetapanUseExternalAdditionalModule) {
        return;
      }

      var cfg = window.tetapanSistemConfig || {};
      var baseUrl = typeof cfg.baseUrl === 'string' ? cfg.baseUrl : '';
      var csrfToken = typeof cfg.csrfToken === 'string' ? cfg.csrfToken : '';
      var store = Array.isArray(cfg.additionalConnections) ? cfg.additionalConnections.slice() : [];

      function escapeHtml(value) {
        return String(value == null ? '' : value)
          .replace(/&/g, '&amp;')
          .replace(/</g, '&lt;')
          .replace(/>/g, '&gt;')
          .replace(/"/g, '&quot;')
          .replace(/'/g, '&#039;');
      }

      function getEl(id) {
        return document.getElementById(id);
      }

      function getModal() {
        var el = getEl('db-additional-modal');
        if (!el) {
          return null;
        }
        if (window.bootstrap && window.bootstrap.Modal) {
          return window.bootstrap.Modal.getOrCreateInstance(el);
        }
        return null;
      }

      function ensureModalMountedToBody() {
        var el = getEl('db-additional-modal');
        if (!el) {
          return null;
        }
        if (el.parentElement !== document.body) {
          document.body.appendChild(el);
        }
        return el;
      }

      function showModal() {
        var el = ensureModalMountedToBody();
        if (!el) {
          return;
        }
        var modal = getModal();
        if (modal) {
          modal.show();
          return;
        }
        el.style.display = 'block';
        el.classList.add('show');
        el.removeAttribute('aria-hidden');
        document.body.classList.add('modal-open');
        if (!document.querySelector('[data-db-additional-fallback-backdrop="1"]')) {
          var backdrop = document.createElement('div');
          backdrop.className = 'modal-backdrop fade show';
          backdrop.setAttribute('data-db-additional-fallback-backdrop', '1');
          backdrop.addEventListener('click', function () {
            hideModal();
          });
          document.body.appendChild(backdrop);
        }
      }

      function hideModal() {
        var el = ensureModalMountedToBody();
        if (!el) {
          return;
        }
        var modal = getModal();
        if (modal) {
          modal.hide();
          return;
        }
        el.style.display = 'none';
        el.classList.remove('show');
        el.setAttribute('aria-hidden', 'true');
        document.body.classList.remove('modal-open');
        document.querySelectorAll('[data-db-additional-fallback-backdrop="1"]').forEach(function (backdrop) {
          backdrop.remove();
        });
      }

      function alertError(message) {
        if (window.Swal && typeof window.Swal.fire === 'function') {
          window.Swal.fire({
            icon: 'error',
            title: 'Ralat',
            text: message || 'Tindakan gagal diproses.',
            confirmButtonText: 'OK'
          });
          return;
        }
        window.alert(message || 'Tindakan gagal diproses.');
      }

      function alertSuccess(message) {
        if (window.Swal && typeof window.Swal.fire === 'function') {
          window.Swal.fire({
            icon: 'success',
            title: 'Berjaya',
            text: message || 'Tindakan berjaya diproses.',
            confirmButtonText: 'OK'
          });
          return;
        }
      }

      function envSummary(envRows) {
        if (!Array.isArray(envRows) || !envRows.length) {
          return '<span class="text-muted small">No env rows</span>';
        }
        return envRows.map(function (row) {
          return '<span class="db-additional-pill">'
            + escapeHtml(String(row.f_environment || '-'))
            + ' / '
            + escapeHtml(String(row.f_os_family || 'any'))
            + ' / '
            + escapeHtml(String(row.f_driver || '-'))
            + '</span>';
        }).join('');
      }

      function lastTestSummary(envRows) {
        if (!Array.isArray(envRows) || !envRows.length) {
          return 'Belum diuji';
        }
        var rows = envRows.filter(function (row) { return row && row.f_last_tested_at; });
        if (!rows.length) {
          return 'Belum diuji';
        }
        rows.sort(function (a, b) {
          return String(b.f_last_tested_at || '').localeCompare(String(a.f_last_tested_at || ''));
        });
        return String(rows[0].f_last_test_status || '') + ' · ' + String(rows[0].f_last_tested_at || '');
      }

      function filteredConnections() {
        var searchEl = getEl('db-additional-search');
        var familyEl = getEl('db-additional-family-filter');
        var statusEl = getEl('db-additional-status-filter');
        var search = searchEl ? String(searchEl.value || '').trim().toLowerCase() : '';
        var family = familyEl ? String(familyEl.value || '').trim().toLowerCase() : '';
        var status = statusEl ? String(statusEl.value || '').trim().toLowerCase() : '';

        return store.filter(function (item) {
          var haystack = [
            item.f_code,
            item.f_name,
            item.f_family,
            item.f_purpose,
            item.f_notes
          ].join(' ').toLowerCase();
          var enabled = !!Number(item.f_is_enabled || 0);
          if (search && haystack.indexOf(search) === -1) {
            return false;
          }
          if (family && String(item.f_family || '').toLowerCase() !== family) {
            return false;
          }
          if (status === 'enabled' && !enabled) {
            return false;
          }
          if (status === 'disabled' && enabled) {
            return false;
          }
          return true;
        });
      }

      function renderTable() {
        var body = getEl('db-additional-table-body');
        var empty = getEl('db-additional-empty');
        var counter = getEl('db-additional-counter');
        if (!body) {
          return;
        }

        var rows = filteredConnections();
        if (counter) {
          counter.textContent = String(rows.length);
        }

        if (!rows.length) {
          body.innerHTML = '';
          if (empty) {
            empty.classList.remove('d-none');
          }
          return;
        }

        if (empty) {
          empty.classList.add('d-none');
        }

        body.innerHTML = rows.map(function (item) {
          var code = String(item.f_code || '');
          var enabled = !!Number(item.f_is_enabled || 0);
          return ''
            + '<tr data-connection-code="' + escapeHtml(code) + '">'
            + '<td><div class="db-additional-code">' + escapeHtml(code) + '</div></td>'
            + '<td>' + escapeHtml(String(item.f_name || code || '-')) + '</td>'
            + '<td><span class="badge bg-info-subtle text-info">' + escapeHtml(String(item.f_family || '-').toUpperCase()) + '</span></td>'
            + '<td>' + escapeHtml(String(item.f_purpose || '-')) + '</td>'
            + '<td><div class="db-additional-meta">' + envSummary(item.env_rows || []) + '</div></td>'
            + '<td>' + (enabled
                ? '<span class="badge bg-success-subtle text-success">Enabled</span>'
                : '<span class="badge bg-secondary-subtle text-secondary">Disabled</span>') + '</td>'
            + '<td><div class="db-additional-test-result">' + escapeHtml(lastTestSummary(item.env_rows || [])) + '</div></td>'
            + '<td class="text-start"><div class="db-additional-actions">'
            + '<button type="button" class="btn btn-sm btn-outline-secondary icon-btn" title="Schema Preview" aria-label="Schema Preview" onclick="return window.__tetapanSchemaPreviewAdditionalConnection(\'' + escapeHtml(code) + '\', this)"><i class="ri-table-line"></i></button>'
            + '<button type="button" class="btn btn-sm btn-outline-info icon-btn" title="Butiran Sambungan Tambahan" aria-label="Butiran Sambungan Tambahan" onclick="return window.__tetapanInspectAdditionalConnection(\'' + escapeHtml(code) + '\', this)"><i class="ri-eye-line"></i></button>'
            + '<button type="button" class="btn btn-sm btn-outline-dark icon-btn" title="Sample Code" aria-label="Sample Code" onclick="return window.__tetapanSampleCodeAdditionalConnection(\'' + escapeHtml(code) + '\')"><i class="ri-code-s-slash-line"></i></button>'
            + '<button type="button" class="btn btn-sm btn-outline-primary icon-btn" title="Edit" aria-label="Edit" onclick="return window.__tetapanOpenAdditionalConnectionModal(\'' + escapeHtml(code) + '\')"><i class="ri-edit-line"></i></button>'
            + '<button type="button" class="btn btn-sm btn-outline-success icon-btn" title="Test Connection" aria-label="Test Connection" onclick="return window.__tetapanTestAdditionalConnection(\'' + escapeHtml(code) + '\', this)"><i class="ri-plug-line"></i></button>'
            + '<button type="button" class="btn btn-sm ' + (enabled ? 'btn-outline-danger' : 'btn-outline-success') + ' icon-btn" title="' + (enabled ? 'Disable' : 'Enable') + '" aria-label="' + (enabled ? 'Disable' : 'Enable') + '" data-db-additional-action="toggle" data-enabled="' + (enabled ? '1' : '0') + '" onclick="return window.__tetapanToggleAdditionalConnection(\'' + escapeHtml(code) + '\', ' + (enabled ? 'false' : 'true') + ', this)"><i class="ri-power-line"></i></button>'
            + '</div></td>'
            + '</tr>';
        }).join('');
      }

      function parseEnvExtraJson(value) {
        if (!value) return {};
        if (typeof value === 'object') return value;
        try {
          var decoded = JSON.parse(String(value || '{}'));
          return decoded && typeof decoded === 'object' ? decoded : {};
        } catch (e) {
          return {};
        }
      }

      function extraBool(extra, keys, defaultValue) {
        for (var i = 0; i < keys.length; i++) {
          if (Object.prototype.hasOwnProperty.call(extra, keys[i])) {
            var value = extra[keys[i]];
            if (typeof value === 'boolean') return value;
            return ['1', 'true', 'yes', 'on'].indexOf(String(value || '').toLowerCase()) !== -1;
          }
        }
        return !!defaultValue;
      }

      function buildEnvRow(row, index) {
        var safe = Object.assign({
          f_environment: 'production',
          f_os_family: 'any',
          f_driver: 'mysql',
          f_host: '',
          f_port: '',
          f_database_name: '',
          f_dsn_name: '',
          f_username: '',
          f_password_ciphertext: '',
          f_charset: 'utf8mb4',
          f_extra_json: null,
          f_is_active: true
        }, row || {});
        var extra = parseEnvExtraJson(safe.f_extra_json);
        var trustServerCertificate = extraBool(extra, ['trust_server_certificate', 'TrustServerCertificate'], false);
        var encryptConnection = extraBool(extra, ['encrypt', 'Encrypt'], false);

        return ''
          + '<div class="db-additional-env-row" data-env-row>'
          + '<div class="db-additional-env-row-header">'
          + '<div><div class="db-additional-env-row-index">Env Row ' + (index + 1) + '</div><div class="db-additional-inline-help">Satu row untuk satu kombinasi environment, OS, dan driver.</div></div>'
          + '<button type="button" class="btn btn-sm btn-outline-danger" onclick="this.closest(\'[data-env-row]\').remove(); return false;"><i class="ri-delete-bin-line me-1"></i>Remove</button>'
          + '</div>'
          + '<div class="row g-3">'
          + '<div class="col-md-3"><label class="form-label">Environment</label><select class="form-select" data-env-field="f_environment"><option value="production"' + (safe.f_environment === 'production' ? ' selected' : '') + '>Production</option><option value="development"' + (safe.f_environment === 'development' ? ' selected' : '') + '>Development</option></select></div>'
          + '<div class="col-md-3"><label class="form-label">OS Family</label><select class="form-select" data-env-field="f_os_family"><option value="any"' + (safe.f_os_family === 'any' ? ' selected' : '') + '>Any</option><option value="windows"' + (safe.f_os_family === 'windows' ? ' selected' : '') + '>Windows</option><option value="linux"' + (safe.f_os_family === 'linux' ? ' selected' : '') + '>Linux</option></select></div>'
          + '<div class="col-md-3"><label class="form-label">Driver</label><select class="form-select" data-env-field="f_driver"><option value="mysql"' + (safe.f_driver === 'mysql' ? ' selected' : '') + '>mysql</option><option value="odbc"' + (safe.f_driver === 'odbc' ? ' selected' : '') + '>odbc</option><option value="dblib"' + (safe.f_driver === 'dblib' ? ' selected' : '') + '>dblib</option><option value="sqlsrv"' + (safe.f_driver === 'sqlsrv' ? ' selected' : '') + '>sqlsrv</option></select></div>'
          + '<div class="col-md-3"><label class="form-label">Active</label><div class="form-check form-switch pt-2"><input class="form-check-input" type="checkbox" data-env-field="f_is_active"' + (safe.f_is_active ? ' checked' : '') + '></div></div>'
          + '<div class="col-md-4"><label class="form-label">Host</label><input type="text" class="form-control" data-env-field="f_host" value="' + escapeHtml(safe.f_host) + '"></div>'
          + '<div class="col-md-2"><label class="form-label">Port</label><input type="text" class="form-control" data-env-field="f_port" value="' + escapeHtml(safe.f_port) + '"></div>'
          + '<div class="col-md-3"><label class="form-label">Database</label><input type="text" class="form-control" data-env-field="f_database_name" value="' + escapeHtml(safe.f_database_name) + '"></div>'
          + '<div class="col-md-3"><label class="form-label">DSN</label><input type="text" class="form-control" data-env-field="f_dsn_name" value="' + escapeHtml(safe.f_dsn_name) + '"></div>'
          + '<div class="col-md-4"><label class="form-label">Username</label><input type="text" class="form-control" data-env-field="f_username" value="' + escapeHtml(safe.f_username) + '"></div>'
          + '<div class="col-md-4"><label class="form-label">Password</label><input type="password" class="form-control" data-env-field="f_password_ciphertext" value="' + escapeHtml(safe.f_password_ciphertext) + '"></div>'
          + '<div class="col-md-4"><label class="form-label">Charset</label><input type="text" class="form-control" data-env-field="f_charset" value="' + escapeHtml(safe.f_charset) + '"></div>'
          + '<div class="col-md-6"><div class="form-check form-switch"><input class="form-check-input" type="checkbox" data-env-extra="encrypt"' + (encryptConnection ? ' checked' : '') + '><label class="form-check-label">Encrypt connection</label><div class="db-additional-inline-help">Untuk SQL Server driver moden. Biarkan off jika guna dblib/FreeTDS biasa.</div></div></div>'
          + '<div class="col-md-6"><div class="form-check form-switch"><input class="form-check-input" type="checkbox" data-env-extra="trust_server_certificate"' + (trustServerCertificate ? ' checked' : '') + '><label class="form-check-label">Trust server certificate</label><div class="db-additional-inline-help">Tick jika SQL Server guna self-signed/internal certificate dan ODBC Driver 18 menolak certificate chain.</div></div></div>'
          + '</div>'
          + '</div>';
      }

      function appendEnvRow(row) {
        var host = getEl('db-additional-env-rows');
        if (!host) {
          return;
        }
        var wrapper = document.createElement('div');
        wrapper.innerHTML = buildEnvRow(row, host.querySelectorAll('[data-env-row]').length);
        if (wrapper.firstElementChild) {
          host.appendChild(wrapper.firstElementChild);
        }
      }

      function resetForm() {
        var form = getEl('form-db-additional');
        if (form) {
          form.reset();
        }
        if (getEl('db-additional-form-type')) getEl('db-additional-form-type').value = 'db_additional_create';
        if (getEl('db-additional-existing-code')) getEl('db-additional-existing-code').value = '';
        if (getEl('db-additional-code')) {
          getEl('db-additional-code').readOnly = false;
          getEl('db-additional-code').value = '';
        }
        if (getEl('db-additional-enabled')) getEl('db-additional-enabled').checked = true;
        if (getEl('db-additional-supports-prod')) getEl('db-additional-supports-prod').checked = true;
        if (getEl('db-additional-supports-dev')) getEl('db-additional-supports-dev').checked = false;
        if (getEl('db-additional-modal-title')) {
          getEl('db-additional-modal-title').textContent = 'Add Additional Connection';
        }
        var rowsHost = getEl('db-additional-env-rows');
        if (rowsHost) {
          rowsHost.innerHTML = '';
        }
        appendEnvRow();
      }

      function activateAdditionalModalFirstTab() {
        var firstTab = getEl('tab-additional-connection-tab');
        if (!firstTab) {
          return;
        }
        if (window.bootstrap && window.bootstrap.Tab) {
          window.bootstrap.Tab.getOrCreateInstance(firstTab).show();
          return;
        }
        document.querySelectorAll('#db-additional-modal-tabs .nav-link').forEach(function (tab) {
          tab.classList.toggle('active', tab === firstTab);
          tab.setAttribute('aria-selected', tab === firstTab ? 'true' : 'false');
        });
        document.querySelectorAll('#db-additional-modal-tabs-content .tab-pane').forEach(function (pane) {
          var isFirst = pane.id === 'tab-additional-connection';
          pane.classList.toggle('show', isFirst);
          pane.classList.toggle('active', isFirst);
        });
      }

      function serializeEnvRows() {
        var host = getEl('db-additional-env-rows');
        if (!host) {
          return [];
        }
        return Array.prototype.map.call(host.querySelectorAll('[data-env-row]'), function (row) {
          var payload = {};
          Array.prototype.forEach.call(row.querySelectorAll('[data-env-field]'), function (field) {
            var key = field.getAttribute('data-env-field');
            if (!key) {
              return;
            }
            if (field.type === 'checkbox') {
              payload[key] = !!field.checked;
              return;
            }
            payload[key] = String(field.value || '').trim();
          });
          var extra = {};
          Array.prototype.forEach.call(row.querySelectorAll('[data-env-extra]'), function (field) {
            var key = field.getAttribute('data-env-extra');
            if (!key) return;
            extra[key] = field.type === 'checkbox' ? !!field.checked : String(field.value || '').trim();
          });
          payload.f_extra_json = JSON.stringify(extra);
          return payload;
        });
      }

      function postAction(formType, data, buttonEl) {
        var fd = new FormData();
        fd.set('ajax', '1');
        fd.set('csrf_token', csrfToken);
        fd.set('form_type', formType);
        Object.keys(data || {}).forEach(function (key) {
          fd.set(key, data[key]);
        });
        if (buttonEl) {
          buttonEl.disabled = true;
        }
        return fetch(window.location.href, {
          method: 'POST',
          body: fd,
          headers: {
            'X-Requested-With': 'XMLHttpRequest',
            'Accept': 'application/json',
            'X-CSRF-Token': csrfToken,
            'X-No-Loader': '1'
          }
        }).then(function (res) {
          return res.text().then(function (text) {
            var trimmed = String(text || '').trim();
            if (!trimmed) {
              throw new Error('Respons pelayan kosong. Sila semak log server untuk data preview.');
            }
            try {
              return JSON.parse(trimmed);
            } catch (error) {
              throw new Error('Respons pelayan tidak sah.');
            }
          });
        }).finally(function () {
          if (buttonEl) {
            buttonEl.disabled = false;
          }
        });
      }

      window.__tetapanOpenAdditionalConnectionModal = function (code) {
        resetForm();
        activateAdditionalModalFirstTab();
        if (code) {
          var found = store.find(function (item) { return String(item.f_code || '') === String(code); }) || null;
          if (found) {
            if (getEl('db-additional-form-type')) getEl('db-additional-form-type').value = 'db_additional_update';
            if (getEl('db-additional-existing-code')) getEl('db-additional-existing-code').value = String(found.f_code || '');
            if (getEl('db-additional-code')) {
              getEl('db-additional-code').value = String(found.f_code || '');
              getEl('db-additional-code').readOnly = true;
            }
            if (getEl('db-additional-name')) getEl('db-additional-name').value = String(found.f_name || '');
            if (getEl('db-additional-purpose')) getEl('db-additional-purpose').value = String(found.f_purpose || '');
            if (getEl('db-additional-family')) getEl('db-additional-family').value = String(found.f_family || 'mysql');
            if (getEl('db-additional-driver-mode')) getEl('db-additional-driver-mode').value = String(found.f_driver_mode || 'auto');
            if (getEl('db-additional-notes')) getEl('db-additional-notes').value = String(found.f_notes || '');
            if (getEl('db-additional-enabled')) getEl('db-additional-enabled').checked = !!Number(found.f_is_enabled || 0);
            if (getEl('db-additional-supports-prod')) getEl('db-additional-supports-prod').checked = !!Number(found.f_supports_prod || 0);
            if (getEl('db-additional-supports-dev')) getEl('db-additional-supports-dev').checked = !!Number(found.f_supports_dev || 0);
            var rowsHost = getEl('db-additional-env-rows');
            if (rowsHost) rowsHost.innerHTML = '';
            (Array.isArray(found.env_rows) && found.env_rows.length ? found.env_rows : []).forEach(function (row) {
              appendEnvRow(row);
            });
            if (rowsHost && !rowsHost.querySelector('[data-env-row]')) {
              appendEnvRow();
            }
            if (getEl('db-additional-modal-title')) {
              getEl('db-additional-modal-title').textContent = 'Edit Additional Connection';
            }
          }
        }
        showModal();
        return false;
      };

      window.__tetapanRefreshAdditionalConnections = function (buttonEl) {
        postAction('db_additional_list', {}, buttonEl)
          .then(function (payload) {
            if (!payload || payload.success !== true) {
              throw new Error((payload && payload.message) || 'Gagal memuat semula sambungan tambahan.');
            }
            if (payload.data && Array.isArray(payload.data.additionalConnections)) {
              store = payload.data.additionalConnections.slice();
              window.tetapanSistemConfig.additionalConnections = store.slice();
              renderTable();
            }
          })
          .catch(function (error) {
            alertError(error && error.message ? error.message : 'Gagal memuat semula sambungan tambahan.');
          });
        return false;
      };

      window.__tetapanSaveAdditionalConnection = function (buttonEl) {
        var formType = getEl('db-additional-form-type') ? String(getEl('db-additional-form-type').value || 'db_additional_create') : 'db_additional_create';
        var payload = {
          f_code: getEl('db-additional-code') ? getEl('db-additional-code').value : '',
          f_name: getEl('db-additional-name') ? getEl('db-additional-name').value : '',
          f_purpose: getEl('db-additional-purpose') ? getEl('db-additional-purpose').value : '',
          f_family: getEl('db-additional-family') ? getEl('db-additional-family').value : '',
          f_driver_mode: getEl('db-additional-driver-mode') ? getEl('db-additional-driver-mode').value : '',
          f_notes: getEl('db-additional-notes') ? getEl('db-additional-notes').value : '',
          f_is_enabled: getEl('db-additional-enabled') && getEl('db-additional-enabled').checked ? '1' : '0',
          f_supports_prod: getEl('db-additional-supports-prod') && getEl('db-additional-supports-prod').checked ? '1' : '0',
          f_supports_dev: getEl('db-additional-supports-dev') && getEl('db-additional-supports-dev').checked ? '1' : '0',
          existing_code: getEl('db-additional-existing-code') ? getEl('db-additional-existing-code').value : '',
          env_rows: JSON.stringify(serializeEnvRows())
        };

        postAction(formType, payload, buttonEl)
          .then(function (response) {
            if (!response || response.success !== true) {
              throw new Error((response && response.message) || 'Gagal menyimpan sambungan tambahan.');
            }
            if (response.data && Array.isArray(response.data.additionalConnections)) {
              store = response.data.additionalConnections.slice();
              window.tetapanSistemConfig.additionalConnections = store.slice();
              renderTable();
            }
            hideModal();
            alertSuccess(response.message || 'Sambungan tambahan berjaya disimpan.');
          })
          .catch(function (error) {
            alertError(error && error.message ? error.message : 'Gagal menyimpan sambungan tambahan.');
          });
        return false;
      };

      window.__tetapanToggleAdditionalConnection = function (code, enabled, buttonEl) {
        postAction('db_additional_toggle', {
          connection_code: code,
          enabled: enabled ? '1' : '0'
        }, buttonEl)
          .then(function (response) {
            if (!response || response.success !== true) {
              throw new Error((response && response.message) || 'Gagal mengemas kini status sambungan tambahan.');
            }
            if (response.data && Array.isArray(response.data.additionalConnections)) {
              store = response.data.additionalConnections.slice();
              window.tetapanSistemConfig.additionalConnections = store.slice();
              renderTable();
            }
          })
          .catch(function (error) {
            alertError(error && error.message ? error.message : 'Gagal mengemas kini status sambungan tambahan.');
          });
        return false;
      };

      window.__tetapanTestAdditionalConnection = function (code, buttonEl) {
        var found = store.find(function (item) { return String(item.f_code || '') === String(code); }) || null;
        var firstEnv = found && Array.isArray(found.env_rows) && found.env_rows.length
          ? (found.env_rows.find(function (row) { return !!Number(row.f_is_active || 0); }) || found.env_rows[0])
          : null;
        postAction('db_additional_test', {
          connection_code: code,
          environment: firstEnv ? String(firstEnv.f_environment || 'production') : 'production',
          os_family: firstEnv ? String(firstEnv.f_os_family || 'any') : 'any',
          driver: firstEnv ? String(firstEnv.f_driver || '') : ''
        }, buttonEl)
          .then(function (response) {
            if (!response || response.success !== true) {
              throw new Error((response && response.message) || 'Ujian sambungan tambahan gagal.');
            }
            alertSuccess(response.message || 'Ujian sambungan tambahan berjaya.');
            return window.__tetapanRefreshAdditionalConnections();
          })
          .catch(function (error) {
            alertError(error && error.message ? error.message : 'Ujian sambungan tambahan gagal.');
          });
        return false;
      };

      function buildAdditionalViewMetaItem(label, content) {
        return '<div class="db-additional-view-meta-item"><div class="db-additional-view-meta-label">' + escapeHtml(label) + '</div><div class="db-additional-view-meta-value">' + content + '</div></div>';
      }

      function initAdditionalViewDataTable(selector, options) {
        if (!(window.jQuery && jQuery.fn && jQuery.fn.DataTable) || !selector) {
          return;
        }
        var table = document.querySelector(selector);
        if (!table) {
          return;
        }
        if (jQuery.fn.dataTable.isDataTable(table)) {
          jQuery(table).DataTable().destroy();
        }
        var searchPlaceholder = options && options.searchPlaceholder ? options.searchPlaceholder : 'Search';
        var extraOptions = Object.assign({}, options || {});
        delete extraOptions.searchPlaceholder;
        var baseOptions = {
          pageLength: 5,
          lengthChange: false,
          lengthMenu: [5, 10, 25, 50, 100, 200],
          autoWidth: false,
          responsive: false,
          ordering: false,
          order: [],
          language: {
            search: '',
            searchPlaceholder: searchPlaceholder
          },
          dom: '<"row mb-2"<"col-sm-12 col-md-6 dt-top-left"l><"col-sm-12 col-md-6 d-flex justify-content-md-end dt-top-right"f>>'
            + 't'
            + '<"dt-bottom-row mt-2 d-flex justify-content-between align-items-center"<"dt-info-left"i><"dt-paging-right d-flex justify-content-end"p>>',
          drawCallback: function () {
            jQuery('.dataTables_paginate > .pagination').addClass('pagination-rounded');
          }
        };
        var dtOptions = Object.assign(baseOptions, extraOptions);
        if (window.DataTableStandard && typeof window.DataTableStandard.options === 'function') {
          dtOptions = window.DataTableStandard.options(dtOptions);
        }
        var dt = jQuery(table).DataTable(dtOptions);
        if (window.DataTableStandard && typeof window.DataTableStandard.decorate === 'function') {
          window.DataTableStandard.decorate(selector, {
            controlsClass: 'mb-2',
            searchPlaceholder: searchPlaceholder
          });
        }
        var wrapperSelector = selector + '_wrapper';
        jQuery(wrapperSelector + ' .dataTables_length select').addClass('form-select w-auto');
        jQuery(wrapperSelector + ' .dataTables_length label').addClass('mb-0');
        jQuery(wrapperSelector + ' .dataTables_filter input').attr('placeholder', searchPlaceholder);
        jQuery(wrapperSelector + ' .dt-top-left').addClass('d-flex align-items-center gap-2 flex-nowrap');
        jQuery(wrapperSelector + ' .dt-top-right').addClass('align-items-center gap-2 flex-nowrap');
        setTimeout(function () {
          dt.columns.adjust().draw(false);
        }, 80);
      }

      function cleanupAdditionalModalBackdrops() {
        var hasShownModal = document.querySelector('.modal.show');
        if (!hasShownModal) {
          document.querySelectorAll('.modal-backdrop').forEach(function (backdrop) {
            backdrop.remove();
          });
          document.body.classList.remove('modal-open');
        }
      }

      function normalizeAdditionalModalStack() {
        var parentModalEl = getEl('db-additional-view-modal');
        var childModalEl = getEl('db-additional-child-view-modal');
        var parentShown = parentModalEl && parentModalEl.classList.contains('show');
        var childShown = childModalEl && childModalEl.classList.contains('show');
        var backdrops = Array.prototype.slice.call(document.querySelectorAll('.modal-backdrop.show'));

        backdrops.forEach(function (backdrop) {
          backdrop.classList.remove('db-additional-view-backdrop', 'db-additional-child-view-backdrop');
        });

        if (parentShown && !childShown && backdrops.length > 1) {
          backdrops.slice(0, -1).forEach(function (backdrop) {
            backdrop.remove();
          });
          backdrops = Array.prototype.slice.call(document.querySelectorAll('.modal-backdrop.show'));
        }

        if (parentShown && backdrops.length) {
          backdrops[0].classList.add('db-additional-view-backdrop');
        }
        if (childShown && backdrops.length) {
          backdrops[backdrops.length - 1].classList.add('db-additional-child-view-backdrop');
        }
      }

      function suspendAdditionalParentModal() {
        var parentModalEl = getEl('db-additional-view-modal');
        if (!parentModalEl || !parentModalEl.classList.contains('show')) {
          return;
        }
        parentModalEl.classList.add('db-additional-parent-suspended');
        parentModalEl.setAttribute('aria-hidden', 'true');
      }

      function resumeAdditionalParentModal() {
        var parentModalEl = getEl('db-additional-view-modal');
        if (!parentModalEl) {
          return;
        }
        parentModalEl.classList.remove('db-additional-parent-suspended');
        if (parentModalEl.classList.contains('show')) {
          parentModalEl.removeAttribute('aria-hidden');
          document.body.classList.add('modal-open');
        }
      }

      function openAdditionalViewModal(config) {
        var modalEl = getEl('db-additional-view-modal');
        var titleEl = getEl('db-additional-view-modal-title');
        var subtitleEl = getEl('db-additional-view-modal-subtitle');
        var kickerEl = getEl('db-additional-view-modal-kicker');
        var bodyEl = getEl('db-additional-view-modal-body');
        if (!modalEl || !bodyEl) {
          return false;
        }
        if (modalEl.parentElement !== document.body) {
          document.body.appendChild(modalEl);
        }
        cleanupAdditionalModalBackdrops();
        modalEl.classList.toggle('db-additional-view-modal-pink', config.variant === 'pink');
        modalEl.classList.toggle('db-additional-view-modal-code', config.variant === 'code');
        if (window.jQuery && jQuery.fn && jQuery.fn.DataTable) {
          jQuery(modalEl).find('table').each(function () {
            if (jQuery.fn.dataTable.isDataTable(this)) {
              jQuery(this).DataTable().destroy();
            }
          });
        }
        if (titleEl) titleEl.textContent = config.title || 'Additional Connection';
        if (subtitleEl) subtitleEl.textContent = config.subtitle || '';
        if (kickerEl) {
          kickerEl.innerHTML = '<i class="' + escapeHtml(config.icon || 'ri-database-2-line') + ' me-2"></i>Additional Connections Registry';
        }
        bodyEl.innerHTML = config.html || '';
        var initTable = function () {
          modalEl.removeEventListener('shown.bs.modal', initTable);
          initAdditionalViewDataTable(config.datatableSelector, config.datatableOptions || {});
        };
        modalEl.addEventListener('shown.bs.modal', initTable);
        modalEl.addEventListener('shown.bs.modal', normalizeAdditionalModalStack, { once: true });
        modalEl.addEventListener('hidden.bs.modal', cleanupAdditionalModalBackdrops, { once: true });
        if (window.bootstrap && window.bootstrap.Modal) {
          window.bootstrap.Modal.getOrCreateInstance(modalEl).show();
          setTimeout(normalizeAdditionalModalStack, 80);
        } else {
          modalEl.style.display = 'block';
          modalEl.classList.add('show');
          initAdditionalViewDataTable(config.datatableSelector, config.datatableOptions || {});
          normalizeAdditionalModalStack();
        }
        return false;
      }

      function openAdditionalChildViewModal(config) {
        var modalEl = getEl('db-additional-child-view-modal');
        var parentModalEl = getEl('db-additional-view-modal');
        var titleEl = getEl('db-additional-child-view-modal-title');
        var subtitleEl = getEl('db-additional-child-view-modal-subtitle');
        var kickerEl = getEl('db-additional-child-view-modal-kicker');
        var bodyEl = getEl('db-additional-child-view-modal-body');
        if (!modalEl || !bodyEl) {
          return openAdditionalViewModal(config);
        }
        if (modalEl.parentElement !== document.body) {
          document.body.appendChild(modalEl);
        }
        suspendAdditionalParentModal();
        if (window.jQuery && jQuery.fn && jQuery.fn.DataTable) {
          jQuery(modalEl).find('table').each(function () {
            if (jQuery.fn.dataTable.isDataTable(this)) {
              jQuery(this).DataTable().destroy();
            }
          });
        }
        if (titleEl) titleEl.textContent = config.title || 'Data Preview';
        if (subtitleEl) subtitleEl.textContent = config.subtitle || '';
        if (kickerEl) {
          kickerEl.innerHTML = '<i class="' + escapeHtml(config.icon || 'ri-file-search-line') + ' me-2"></i>' + escapeHtml(config.title || 'Data Preview');
        }
        bodyEl.innerHTML = config.html || '';
        var initTable = function () {
          modalEl.removeEventListener('shown.bs.modal', initTable);
          initAdditionalViewDataTable(config.datatableSelector, config.datatableOptions || {});
        };
        var keepParentOpen = function () {
          modalEl.removeEventListener('hidden.bs.modal', keepParentOpen);
          resumeAdditionalParentModal();
          normalizeAdditionalModalStack();
        };
        modalEl.addEventListener('shown.bs.modal', initTable);
        modalEl.addEventListener('shown.bs.modal', normalizeAdditionalModalStack, { once: true });
        modalEl.addEventListener('hidden.bs.modal', keepParentOpen);
        if (window.bootstrap && window.bootstrap.Modal) {
          window.bootstrap.Modal.getOrCreateInstance(modalEl).show();
          setTimeout(normalizeAdditionalModalStack, 80);
        } else {
          modalEl.style.display = 'block';
          modalEl.classList.add('show');
          initAdditionalViewDataTable(config.datatableSelector, config.datatableOptions || {});
          normalizeAdditionalModalStack();
        }
        return false;
      }

      window.__tetapanTogglePreviewRow = function (buttonEl) {
        var row = buttonEl && buttonEl.closest ? buttonEl.closest('tr') : null;
        if (!row) return false;
        var expanded = row.classList.toggle('is-expanded');
        buttonEl.textContent = expanded ? 'Sembunyi' : 'Papar';
        if (window.jQuery && jQuery.fn && jQuery.fn.DataTable) {
          var table = jQuery(row).closest('table');
          if (table.length && jQuery.fn.dataTable.isDataTable(table[0])) {
            table.DataTable().columns.adjust().draw(false);
          }
        }
        return false;
      };

      function getPreferredAdditionalEnvRow(connection) {
        var rows = connection && Array.isArray(connection.env_rows) ? connection.env_rows : [];
        if (!rows.length) {
          return null;
        }
        return rows.find(function (row) { return !!Number(row.f_is_active || 0); }) || rows[0];
      }

      function buildAdditionalSampleCodeBlock(title, code) {
        var encoded = encodeURIComponent(code);
        return ''
          + '<div class="db-sample-code-card">'
          + '<div class="db-sample-code-card-header">'
          + '<h6><i class="ri-code-box-line"></i>' + escapeHtml(title) + '</h6>'
          + '<button type="button" class="btn btn-sm btn-outline-secondary db-rounded-btn" onclick="return window.__tetapanCopyCodeBlock(\'' + encoded + '\', this)">'
          + '<i class="ri-file-copy-line me-1"></i> Copy'
          + '</button>'
          + '</div>'
          + '<pre class="db-sample-code-pre"><code>' + escapeHtml(code) + '</code></pre>'
          + '</div>';
      }

      function buildAdditionalSampleCodeTabs(samples) {
        var safeSamples = Array.isArray(samples) ? samples.filter(function (sample) {
          return sample && sample.id && sample.title && sample.code;
        }) : [];
        if (!safeSamples.length) return '';

        var nav = safeSamples.map(function (sample, index) {
          return ''
            + '<button type="button" class="db-sample-code-tab' + (index === 0 ? ' is-active' : '') + '" data-sample-tab="' + escapeHtml(sample.id) + '" aria-selected="' + (index === 0 ? 'true' : 'false') + '">'
            + '<i class="' + escapeHtml(sample.icon || 'ri-code-box-line') + '"></i>'
            + '<span>' + escapeHtml(sample.title) + '</span>'
            + '</button>';
        }).join('');

        var panes = safeSamples.map(function (sample, index) {
          var encoded = encodeURIComponent(sample.code);
          return ''
            + '<div class="db-sample-code-pane' + (index === 0 ? ' is-active' : '') + '" data-sample-pane="' + escapeHtml(sample.id) + '">'
            + '<div class="db-sample-code-card">'
            + '<div class="db-sample-code-card-header">'
            + '<div>'
            + '<h6><i class="' + escapeHtml(sample.icon || 'ri-code-box-line') + '"></i>' + escapeHtml(sample.title) + '</h6>'
            + (sample.description ? '<p>' + escapeHtml(sample.description) + '</p>' : '')
            + '</div>'
            + '<button type="button" class="btn btn-sm btn-outline-secondary db-rounded-btn" onclick="return window.__tetapanCopyCodeBlock(\'' + encoded + '\', this)">'
            + '<i class="ri-file-copy-line me-1"></i> Copy'
            + '</button>'
            + '</div>'
            + '<pre class="db-sample-code-pre"><code>' + escapeHtml(sample.code) + '</code></pre>'
            + '</div>'
            + '</div>';
        }).join('');

        return ''
          + '<div class="db-sample-code-tabs">'
          + '<div class="db-sample-code-tablist" role="tablist">' + nav + '</div>'
          + '<div class="db-sample-code-panes">' + panes + '</div>'
          + '</div>';
      }

      window.__tetapanCopyCodeBlock = function (encodedCode, buttonEl) {
        var code = decodeURIComponent(String(encodedCode || ''));
        var done = function () {
          if (!buttonEl) return;
          var original = buttonEl.innerHTML;
          buttonEl.innerHTML = '<i class="ri-check-line me-1"></i> Copied';
          setTimeout(function () {
            buttonEl.innerHTML = original;
          }, 1200);
        };

        if (navigator.clipboard && typeof navigator.clipboard.writeText === 'function') {
          navigator.clipboard.writeText(code).then(done).catch(function () {});
        } else {
          var textarea = document.createElement('textarea');
          textarea.value = code;
          textarea.setAttribute('readonly', 'readonly');
          textarea.style.position = 'fixed';
          textarea.style.left = '-9999px';
          document.body.appendChild(textarea);
          textarea.select();
          try { document.execCommand('copy'); done(); } catch (error) {}
          textarea.remove();
        }
        return false;
      };

      document.addEventListener('click', function (event) {
        var tab = event.target && event.target.closest ? event.target.closest('.db-sample-code-tab[data-sample-tab]') : null;
        if (!tab) return;

        var shell = tab.closest('.db-sample-code-tabs');
        if (!shell) return;

        var target = tab.getAttribute('data-sample-tab');
        shell.querySelectorAll('.db-sample-code-tab').forEach(function (button) {
          var active = button === tab;
          button.classList.toggle('is-active', active);
          button.setAttribute('aria-selected', active ? 'true' : 'false');
        });
        shell.querySelectorAll('.db-sample-code-pane').forEach(function (pane) {
          pane.classList.toggle('is-active', pane.getAttribute('data-sample-pane') === target);
        });
      });

      window.__tetapanSampleCodeAdditionalConnection = function (code) {
        var connection = store.find(function (item) { return String(item.f_code || '') === String(code); }) || null;
        if (!connection) {
          alertError('Sambungan tambahan tidak ditemui.');
          return false;
        }

        var envRow = getPreferredAdditionalEnvRow(connection);
        var environment = envRow ? String(envRow.f_environment || 'production') : 'production';
        var family = String(connection.f_family || '-');
        var purpose = String(connection.f_purpose || '-');
        var databaseName = envRow ? String(envRow.f_database_name || '-') : '-';
        var previewQuery = ['sybase', 'mssql'].indexOf(family.toLowerCase()) !== -1
          ? 'SELECT TOP 10 * FROM nama_table'
          : 'SELECT * FROM nama_table LIMIT 10';
        var dataTablesPageSql = ['sybase', 'mssql'].indexOf(family.toLowerCase()) !== -1
          ? "SELECT id, kod, nama, status FROM nama_table ' . $where . ' ORDER BY nama OFFSET :start ROWS FETCH NEXT :length ROWS ONLY"
          : "SELECT id, kod, nama, status FROM nama_table ' . $where . ' ORDER BY nama LIMIT :length OFFSET :start";
        var serviceSample = "<" + "?php\n"
          + "require_once __DIR__ . '/../classes/Database.php';\n\n"
          + "final class AdditionalDbService\n"
          + "{\n"
          + "    private PDO $pdo;\n\n"
          + "    public function __construct(?PDO $pdo = null)\n"
          + "    {\n"
          + "        $this->pdo = $pdo ?: Database::pdoAdditional('" + code + "', '" + environment + "');\n"
          + "    }\n\n"
          + "    public function healthCheck(): array\n"
          + "    {\n"
          + "        $stmt = $this->pdo->query('SELECT 1 AS ok');\n"
          + "        return $stmt->fetch(PDO::FETCH_ASSOC) ?: [];\n"
          + "    }\n"
          + "}\n";
        var repositorySample = "<" + "?php\n"
          + "require_once __DIR__ . '/../classes/Database.php';\n\n"
          + "final class AdditionalLookupRepository\n"
          + "{\n"
          + "    public function __construct(private readonly PDO $pdo)\n"
          + "    {\n"
          + "    }\n\n"
          + "    public static function make(): self\n"
          + "    {\n"
          + "        return new self(Database::pdoAdditional('" + code + "', '" + environment + "'));\n"
          + "    }\n\n"
          + "    public function findById(int $id): ?array\n"
          + "    {\n"
          + "        $stmt = $this->pdo->prepare('SELECT * FROM nama_table WHERE id = :id');\n"
          + "        $stmt->execute([':id' => $id]);\n"
          + "        $row = $stmt->fetch(PDO::FETCH_ASSOC);\n\n"
          + "        return is_array($row) ? $row : null;\n"
          + "    }\n"
          + "}\n";
        var controllerSample = "<" + "?php\n"
          + "require_once __DIR__ . '/../repositories/AdditionalLookupRepository.php';\n\n"
          + "final class LaporanController\n"
          + "{\n"
          + "    public function detail(int $id): array\n"
          + "    {\n"
          + "        try {\n"
          + "            $record = AdditionalLookupRepository::make()->findById($id);\n"
          + "            return ['success' => true, 'data' => $record];\n"
          + "        } catch (Throwable $e) {\n"
          + "            error_log('[additional-db:" + code + "] ' . $e->getMessage());\n"
          + "            return ['success' => false, 'message' => 'Sumber data tambahan tidak tersedia.'];\n"
          + "        }\n"
          + "    }\n"
          + "}\n";
        // audit-scan-sample-code: begin
        // The following SQL write statements are rendered as developer examples only.
        var transactionSample = "<" + "?php\n"
          + "require_once __DIR__ . '/../classes/Database.php';\n\n"
          + "$pdo = Database::pdoAdditional('" + code + "', '" + environment + "');\n\n"
          + "$pdo->beginTransaction();\n"
          + "try {\n"
          + "    $stmt = $pdo->prepare('UPDATE nama_table SET status = :status WHERE id = :id');\n"
          + "    $stmt->execute([':status' => 'aktif', ':id' => $id]);\n"
          + "    $pdo->commit();\n"
          + "} catch (Throwable $e) {\n"
          + "    $pdo->rollBack();\n"
          + "    throw $e;\n"
          + "}\n";
        var readOnlySample = "<" + "?php\n"
          + "require_once __DIR__ . '/../classes/Database.php';\n\n"
          + "try {\n"
          + "    $pdo = Database::pdoAdditional('" + code + "', '" + environment + "');\n"
          + "    $stmt = $pdo->prepare('" + previewQuery + "');\n"
          + "    $stmt->execute();\n"
          + "    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);\n"
          + "} catch (Throwable $e) {\n"
          + "    error_log('[additional-db:" + code + "] ' . $e->getMessage());\n"
          + "    throw $e;\n"
          + "}\n";
        var ajaxEndpointSample = "<" + "?php\n"
          + "require_once __DIR__ . '/../../classes/Database.php';\n"
          + "header('Content-Type: application/json');\n\n"
          + "try {\n"
          + "    $pdo = Database::pdoAdditional('" + code + "', '" + environment + "');\n"
          + "    $stmt = $pdo->prepare('SELECT * FROM nama_table WHERE status = :status');\n"
          + "    $stmt->execute([':status' => $_POST['status'] ?? 'aktif']);\n\n"
          + "    echo json_encode([\n"
          + "        'success' => true,\n"
          + "        'data' => $stmt->fetchAll(PDO::FETCH_ASSOC),\n"
          + "    ]);\n"
          + "} catch (Throwable $e) {\n"
          + "    error_log('[additional-db:" + code + "] ' . $e->getMessage());\n"
          + "    http_response_code(500);\n"
          + "    echo json_encode(['success' => false, 'message' => 'Gagal memuatkan data.']);\n"
          + "}\n";
        var dataTablesSample = "<" + "?php\n"
          + "require_once __DIR__ . '/../../classes/Database.php';\n"
          + "header('Content-Type: application/json');\n\n"
          + "$pdo = Database::pdoAdditional('" + code + "', '" + environment + "');\n"
          + "$draw = (int)($_POST['draw'] ?? 1);\n"
          + "$start = max(0, (int)($_POST['start'] ?? 0));\n"
          + "$length = min(100, max(10, (int)($_POST['length'] ?? 10)));\n"
          + "$search = trim((string)($_POST['search']['value'] ?? ''));\n\n"
          + "$where = '';\n"
          + "$params = [];\n"
          + "if ($search !== '') {\n"
          + "    $where = 'WHERE nama LIKE :search OR kod LIKE :search';\n"
          + "    $params[':search'] = '%' . $search . '%';\n"
          + "}\n\n"
          + "$total = (int)$pdo->query('SELECT COUNT(*) FROM nama_table')->fetchColumn();\n"
          + "$countStmt = $pdo->prepare('SELECT COUNT(*) FROM nama_table ' . $where);\n"
          + "$countStmt->execute($params);\n"
          + "$filtered = (int)$countStmt->fetchColumn();\n\n"
          + "$sql = '" + dataTablesPageSql + "';\n"
          + "$stmt = $pdo->prepare($sql);\n"
          + "foreach ($params as $key => $value) {\n"
          + "    $stmt->bindValue($key, $value);\n"
          + "}\n"
          + "$stmt->bindValue(':start', $start, PDO::PARAM_INT);\n"
          + "$stmt->bindValue(':length', $length, PDO::PARAM_INT);\n"
          + "$stmt->execute();\n\n"
          + "echo json_encode([\n"
          + "    'draw' => $draw,\n"
          + "    'recordsTotal' => $total,\n"
          + "    'recordsFiltered' => $filtered,\n"
          + "    'data' => $stmt->fetchAll(PDO::FETCH_ASSOC),\n"
          + "]);\n";
        var dropdownSample = "<" + "?php\n"
          + "require_once __DIR__ . '/../classes/Database.php';\n\n"
          + "function additionalStatusOptions(): array\n"
          + "{\n"
          + "    $pdo = Database::pdoAdditional('" + code + "', '" + environment + "');\n"
          + "    $stmt = $pdo->prepare('SELECT kod, nama FROM ref_status WHERE aktif = :aktif ORDER BY nama');\n"
          + "    $stmt->execute([':aktif' => 1]);\n\n"
          + "    return array_map(static fn (array $row): array => [\n"
          + "        'value' => $row['kod'],\n"
          + "        'label' => $row['nama'],\n"
          + "    ], $stmt->fetchAll(PDO::FETCH_ASSOC));\n"
          + "}\n";
        var insertUpdateSample = "<" + "?php\n"
          + "require_once __DIR__ . '/../classes/Database.php';\n\n"
          + "$pdo = Database::pdoAdditional('" + code + "', '" + environment + "');\n"
          + "$payload = [\n"
          + "    ':id' => (int)$id,\n"
          + "    ':nama' => trim((string)$nama),\n"
          + "    ':status' => $status,\n"
          + "];\n\n"
          + "$stmt = $pdo->prepare('\n"
          + "    UPDATE nama_table\n"
          + "    SET nama = :nama, status = :status, updated_at = CURRENT_TIMESTAMP\n"
          + "    WHERE id = :id\n"
          + "');\n"
          + "$stmt->execute($payload);\n\n"
          + "if ($stmt->rowCount() === 0) {\n"
          + "    $insert = $pdo->prepare('\n"
          + "        INSERT INTO nama_table (id, nama, status, created_at)\n"
          + "        VALUES (:id, :nama, :status, CURRENT_TIMESTAMP)\n"
          + "    ');\n"
          + "    $insert->execute($payload);\n"
          + "}\n";
        var batchSyncSample = "<" + "?php\n"
          + "require_once __DIR__ . '/../classes/Database.php';\n\n"
          + "$external = Database::pdoAdditional('" + code + "', '" + environment + "');\n"
          + "$local = Database::pdo();\n\n"
          + "$rows = $external->query('SELECT kod, nama, updated_at FROM nama_table')->fetchAll(PDO::FETCH_ASSOC);\n"
          + "$upsert = $local->prepare('\n"
          + "    INSERT INTO local_lookup (kod, nama, source_updated_at)\n"
          + "    VALUES (:kod, :nama, :updated_at)\n"
          + "    ON DUPLICATE KEY UPDATE nama = VALUES(nama), source_updated_at = VALUES(source_updated_at)\n"
          + "');\n\n"
          + "$local->beginTransaction();\n"
          + "try {\n"
          + "    foreach ($rows as $row) {\n"
          + "        $upsert->execute([\n"
          + "            ':kod' => $row['kod'],\n"
          + "            ':nama' => $row['nama'],\n"
          + "            ':updated_at' => $row['updated_at'],\n"
          + "        ]);\n"
          + "    }\n"
          + "    $local->commit();\n"
          + "} catch (Throwable $e) {\n"
          + "    $local->rollBack();\n"
          + "    throw $e;\n"
          + "}\n";
        // audit-scan-sample-code: end
        var samples = [
          {
            id: 'service',
            title: 'Service',
            icon: 'ri-service-line',
            description: 'Wrapper kecil untuk connection supaya controller tidak pegang detail database.',
            code: serviceSample
          },
          {
            id: 'repository',
            title: 'Repository',
            icon: 'ri-database-2-line',
            description: 'Pattern yang disyorkan untuk query prepared statement.',
            code: repositorySample
          },
          {
            id: 'controller',
            title: 'Controller',
            icon: 'ri-layout-2-line',
            description: 'Controller panggil repository dan handle error pada boundary feature.',
            code: controllerSample
          },
          {
            id: 'transaction',
            title: 'Transaction',
            icon: 'ri-loop-left-line',
            description: 'Gunakan untuk operasi tulis yang perlu commit atau rollback.',
            code: transactionSample
          },
          {
            id: 'readonly',
            title: 'Read-only',
            icon: 'ri-search-line',
            description: 'Contoh query laporan/lookup dengan error logging.',
            code: readOnlySample
          },
          {
            id: 'ajax',
            title: 'Ajax Endpoint',
            icon: 'ri-exchange-box-line',
            description: 'Endpoint JSON standard untuk page yang fetch data tanpa reload.',
            code: ajaxEndpointSample
          },
          {
            id: 'datatables',
            title: 'DataTables',
            icon: 'ri-table-2',
            description: 'Server-side response shape untuk listing DataTables.',
            code: dataTablesSample
          },
          {
            id: 'dropdown',
            title: 'Dropdown',
            icon: 'ri-list-check',
            description: 'Lookup option untuk select/filter daripada database tambahan.',
            code: dropdownSample
          },
          {
            id: 'insert-update',
            title: 'Insert Update',
            icon: 'ri-edit-box-line',
            description: 'Pattern upsert ringkas dengan prepared statement dan rowCount.',
            code: insertUpdateSample
          },
          {
            id: 'batch-sync',
            title: 'Batch Sync',
            icon: 'ri-loop-right-line',
            description: 'Sync data daripada DB tambahan ke local DB dalam transaction.',
            code: batchSyncSample
          }
        ];

        var html = ''
          + '<div class="db-additional-view-shell db-sample-code-shell">'
          + '<div class="db-additional-view-meta">'
          + buildAdditionalViewMetaItem('Code', '<code>' + escapeHtml(code) + '</code>')
          + buildAdditionalViewMetaItem('Environment', escapeHtml(environment))
          + buildAdditionalViewMetaItem('Family', escapeHtml(family))
          + buildAdditionalViewMetaItem('Database', escapeHtml(databaseName))
          + '</div>'
          + '<div class="db-additional-view-card">'
          + '<div class="db-additional-view-card-header"><h6 class="db-additional-view-card-title"><i class="ri-braces-line"></i> Sample Code Untuk Programmer</h6></div>'
          + '<div class="db-additional-view-card-body">'
          + '<div class="db-sample-code-note">Gunakan helper ini supaya credential, environment, driver fallback, dan cache PDO dikawal oleh registry sistem. Jangan hardcode DSN, host, username, atau password dalam module.</div>'
          + buildAdditionalSampleCodeTabs(samples)
          + '</div></div></div>';

        openAdditionalViewModal({
          title: 'Sample Code',
          subtitle: code + ' / ' + environment + ' / ' + purpose,
          icon: 'ri-code-s-slash-line',
          variant: 'code',
          html: html
        });
        return false;
      };

      window.__tetapanInspectAdditionalConnection = function (code, buttonEl) {
        var found = store.find(function (item) { return String(item.f_code || '') === String(code); }) || null;
        var inspectEnv = found && Array.isArray(found.env_rows) && found.env_rows.length
          ? (found.env_rows.find(function (row) { return !!Number(row.f_is_active || 0); }) || found.env_rows[0])
          : null;
        postAction('db_additional_inspect', {
          connection_code: code,
          environment: inspectEnv ? String(inspectEnv.f_environment || 'production') : 'production',
          os_family: inspectEnv ? String(inspectEnv.f_os_family || 'any') : 'any',
          driver: inspectEnv ? String(inspectEnv.f_driver || '') : ''
        }, buttonEl)
          .then(function (response) {
            if (!response || response.success !== true || !response.data || !response.data.probe) {
              throw new Error((response && response.message) || 'Gagal memuatkan butiran sambungan tambahan.');
            }

            var probe = response.data.probe || {};
            var value = function (key) {
              return escapeHtml(probe && probe[key] != null && probe[key] !== '' ? probe[key] : '-');
            };
            var html = ''
              + '<div class="db-additional-view-shell">'
              + '<div class="db-additional-view-meta">'
              + buildAdditionalViewMetaItem('Code', '<code>' + value('connection_code') + '</code>')
              + buildAdditionalViewMetaItem('Name', value('connection_name'))
              + buildAdditionalViewMetaItem('Environment', value('environment'))
              + buildAdditionalViewMetaItem('Database', value('database_name'))
              + '</div>'
              + '<div class="db-additional-view-card">'
              + '<div class="db-additional-view-card-header"><h6 class="db-additional-view-card-title"><i class="ri-list-check-2"></i> Runtime Probe</h6></div>'
              + '<div class="db-additional-view-card-body"><div class="db-additional-view-table-wrap">'
              + '<table id="db-additional-probe-dt" class="table table-sm table-hover align-middle db-additional-view-table">'
              + '<thead><tr><th>Field</th><th>Value</th></tr></thead><tbody>'
              + '<tr><th>Family</th><td>' + value('family') + '</td></tr>'
              + '<tr><th>Purpose</th><td>' + value('purpose') + '</td></tr>'
              + '<tr><th>OS Family</th><td>' + value('os_family') + '</td></tr>'
              + '<tr><th>Configured Driver</th><td>' + value('configured_driver') + '</td></tr>'
              + '<tr><th>Active Driver</th><td>' + value('active_driver') + '</td></tr>'
              + '<tr><th>Host</th><td>' + value('host') + '</td></tr>'
              + '<tr><th>Port</th><td>' + value('port') + '</td></tr>'
              + '<tr><th>Current DB</th><td>' + value('current_database') + '</td></tr>'
              + '<tr><th>Current User</th><td>' + value('current_user') + '</td></tr>'
              + '<tr><th>Server Time</th><td>' + value('server_time') + '</td></tr>'
              + '<tr><th>Server Version</th><td>' + value('server_version') + '</td></tr>'
              + '<tr><th>Ping</th><td>' + value('ping') + '</td></tr>'
              + '</tbody></table></div>'
              + '</div></div>'
              + '</div>';

            openAdditionalViewModal({
              title: 'Butiran Sambungan Tambahan',
              subtitle: value('connection_code') + ' / ' + value('environment') + ' / ' + value('active_driver'),
              icon: 'ri-eye-line',
              html: html,
              datatableSelector: '#db-additional-probe-dt',
              datatableOptions: {
                pageLength: 5,
                searchPlaceholder: 'Search',
                order: [],
                columnDefs: [{ targets: 0, width: '180px' }]
              }
            });
          })
          .catch(function (error) {
            alertError(error && error.message ? error.message : 'Gagal memuatkan butiran sambungan tambahan.');
          });
        return false;
      };

      window.__tetapanSchemaPreviewAdditionalConnection = function (code, buttonEl) {
        var found = store.find(function (item) { return String(item.f_code || '') === String(code); }) || null;
        var schemaEnv = found && Array.isArray(found.env_rows) && found.env_rows.length
          ? (found.env_rows.find(function (row) { return !!Number(row.f_is_active || 0); }) || found.env_rows[0])
          : null;
        postAction('db_additional_schema_preview', {
          connection_code: code,
          environment: schemaEnv ? String(schemaEnv.f_environment || 'production') : 'production',
          os_family: schemaEnv ? String(schemaEnv.f_os_family || 'any') : 'any',
          driver: schemaEnv ? String(schemaEnv.f_driver || '') : ''
        }, buttonEl)
          .then(function (response) {
            if (!response || response.success !== true || !response.data || !response.data.schemaPreview) {
              throw new Error((response && response.message) || 'Gagal memuatkan schema preview sambungan tambahan.');
            }

            var preview = response.data.schemaPreview || {};
            var objects = Array.isArray(preview.objects) ? preview.objects : [];
            var rowsHtml = objects.length
              ? objects.map(function (item) {
                  var code = encodeURIComponent(String(preview.connection_code || ''));
                  var objectName = encodeURIComponent(String(item.object_name || ''));
                  var environment = encodeURIComponent(String(preview.environment || 'production'));
                  var osFamily = encodeURIComponent(String(preview.os_family || 'any'));
                  var driver = encodeURIComponent(String(preview.driver || ''));
                  return '<tr><td>' + escapeHtml(item.object_name || '-') + '</td><td>' + escapeHtml(item.object_type || '-') + '</td><td class="text-start"><button type="button" class="btn btn-sm btn-outline-primary" onclick="return window.__tetapanDataPreviewAdditionalConnection(\'' + code + '\', \'' + objectName + '\', \'' + environment + '\', \'' + osFamily + '\', \'' + driver + '\', this)"><i class="ri-file-search-line"></i></button></td></tr>';
                }).join('')
              : '<tr><td colspan="3" class="text-muted text-center py-3">Tiada objek ditemui.</td></tr>';

            var html = ''
              + '<div class="db-additional-view-shell">'
              + '<div class="db-additional-view-meta">'
              + buildAdditionalViewMetaItem('Code', '<code>' + escapeHtml(preview.connection_code || '-') + '</code>')
              + buildAdditionalViewMetaItem('Family', escapeHtml(preview.family || '-'))
              + buildAdditionalViewMetaItem('Environment', escapeHtml(preview.environment || '-'))
              + buildAdditionalViewMetaItem('Database', escapeHtml(preview.database_name || '-'))
              + '</div>'
              + '<div class="db-additional-view-card">'
              + '<div class="db-additional-view-card-header"><h6 class="db-additional-view-card-title"><i class="ri-table-line"></i> Schema Preview</h6></div>'
              + '<div class="db-additional-view-card-body"><div class="db-additional-view-table-wrap">'
              + '<table id="db-additional-schema-dt" class="table table-sm table-hover align-middle db-additional-view-table">'
              + '<thead><tr><th>Object Name</th><th style="width:140px">Type</th><th class="text-start" style="width:96px">Preview</th></tr></thead>'
              + '<tbody>' + rowsHtml + '</tbody>'
              + '</table></div>'
              + '</div></div>'
              + '</div>';

            openAdditionalViewModal({
              title: 'Schema Preview',
              subtitle: String(preview.connection_code || '-') + ' / ' + String(preview.environment || '-') + ' / ' + String(preview.database_name || '-'),
              icon: 'ri-table-line',
              html: html,
              datatableSelector: '#db-additional-schema-dt',
              datatableOptions: {
                pageLength: 5,
                searchPlaceholder: 'Search',
                columnDefs: [{ targets: 2, orderable: false, searchable: false }]
              }
            });
          })
          .catch(function (error) {
            alertError(error && error.message ? error.message : 'Gagal memuatkan schema preview sambungan tambahan.');
          });
        return false;
      };

      window.__tetapanDataPreviewAdditionalConnection = function (encodedCode, encodedObjectName, encodedEnvironment, encodedOsFamily, encodedDriver, buttonEl) {
        var code = decodeURIComponent(String(encodedCode || ''));
        var objectName = decodeURIComponent(String(encodedObjectName || ''));
        var environment = decodeURIComponent(String(encodedEnvironment || 'production'));
        var osFamily = decodeURIComponent(String(encodedOsFamily || 'any'));
        var driver = decodeURIComponent(String(encodedDriver || ''));

        postAction('db_additional_object_preview', {
          connection_code: code,
          object_name: objectName,
          environment: environment,
          os_family: osFamily,
          driver: driver
        }, buttonEl)
          .then(function (response) {
            if (!response || response.success !== true || !response.data || !response.data.objectPreview) {
              throw new Error((response && response.message) || 'Gagal memuatkan data preview sambungan tambahan.');
            }

            var preview = response.data.objectPreview || {};
            var columns = Array.isArray(preview.columns) ? preview.columns : [];
            var rows = Array.isArray(preview.rows) ? preview.rows : [];
            var headerHtml = columns.map(function (column) {
              return '<th>' + escapeHtml(column) + '</th>';
            }).join('') + '<th class="text-start db-preview-toggle-cell">Paparan</th>';
            var bodyHtml = rows.length
              ? rows.map(function (row) {
                return '<tr>' + columns.map(function (column) {
                    var value = row && row[column] != null ? String(row[column]) : '';
                    return '<td class="db-preview-cell">' + escapeHtml(value) + '</td>';
                  }).join('') + '<td class="db-preview-toggle-cell"><button type="button" class="db-preview-toggle" onclick="return window.__tetapanTogglePreviewRow(this)">Papar</button></td></tr>';
                }).join('')
              : '<tr><td colspan="' + Math.max(columns.length + 1, 1) + '" class="text-muted text-center py-3">Tiada rekod ditemui.</td></tr>';

            var html = ''
              + '<div class="db-additional-view-shell">'
              + '<div class="db-additional-view-meta">'
              + buildAdditionalViewMetaItem('Code', '<code>' + escapeHtml(preview.connection_code || '-') + '</code>')
              + buildAdditionalViewMetaItem('Object', escapeHtml(preview.object_name || '-'))
              + buildAdditionalViewMetaItem('Environment', escapeHtml(preview.environment || '-'))
              + buildAdditionalViewMetaItem('Database', escapeHtml(preview.database_name || '-'))
              + '</div>'
              + '<div class="db-additional-view-card">'
              + '<div class="db-additional-view-card-header"><h6 class="db-additional-view-card-title"><i class="ri-file-search-line"></i> Data Preview</h6></div>'
              + '<div class="db-additional-view-card-body"><div class="db-additional-view-table-wrap">'
              + '<table id="db-additional-object-preview-dt" class="table table-sm table-hover align-middle db-additional-view-table">'
              + '<thead><tr>' + headerHtml + '</tr></thead>'
              + '<tbody>' + bodyHtml + '</tbody>'
              + '</table></div></div></div>'
              + '</div>';

            openAdditionalChildViewModal({
              title: 'Data Preview',
              subtitle: String(preview.object_name || '-') + ' / ' + String(preview.environment || '-') + ' / ' + String(preview.database_name || '-'),
              icon: 'ri-file-search-line',
              variant: 'pink',
              html: html,
              datatableSelector: '#db-additional-object-preview-dt',
              datatableOptions: {
                pageLength: 5,
                scrollX: true,
                searchPlaceholder: 'Search',
                columnDefs: [{ targets: columns.length, orderable: false, searchable: false, width: '10%' }]
              }
            });
          })
          .catch(function (error) {
            alertError(error && error.message ? error.message : 'Gagal memuatkan data preview sambungan tambahan.');
          });
        return false;
      };

      document.addEventListener('DOMContentLoaded', function () {
        ensureModalMountedToBody();
        var search = getEl('db-additional-search');
        var family = getEl('db-additional-family-filter');
        var status = getEl('db-additional-status-filter');
        var envAdd = getEl('btn-db-additional-env-add');
        if (search) search.addEventListener('input', renderTable);
        if (family) family.addEventListener('change', renderTable);
        if (status) status.addEventListener('change', renderTable);
        if (envAdd) {
          envAdd.addEventListener('click', function (event) {
            event.preventDefault();
            appendEnvRow();
          });
        }
        document.querySelectorAll('#db-additional-modal [data-bs-dismiss="modal"]').forEach(function (button) {
          button.addEventListener('click', function (event) {
            if (window.bootstrap && window.bootstrap.Modal) {
              return;
            }
            event.preventDefault();
            hideModal();
          });
        });
        renderTable();
      });
    })();
  </script>
  <script src="<?= asset_url('js/helpers/page-ui-helper.js') ?>?v=<?= urlencode($pageAssetVersion) ?>"></script>
  <script src="<?= asset_url('js/helpers/datatables-standard.js') ?>?v=<?= urlencode($pageAssetVersion) ?>"></script>
  <script src="<?= asset_url('js/pages/tetapan-sistem.js') ?>?v=<?= urlencode($pageAssetVersion) ?>"></script>
  <script>
    (function () {
      'use strict';

      function showManualTab(trigger) {
        var selector = trigger.getAttribute('data-bs-target') || trigger.getAttribute('href');
        if (!selector || selector.charAt(0) !== '#') {
          return;
        }

        var targetPane = document.querySelector(selector);
        if (!targetPane) {
          return;
        }

        var nav = trigger.closest('.nav');
        if (nav) {
          nav.querySelectorAll('[data-bs-toggle="tab"], [data-bs-toggle="pill"]').forEach(function (item) {
            item.classList.remove('active');
            item.setAttribute('aria-selected', 'false');
          });
        }

        var paneContainer = targetPane.parentElement;
        if (paneContainer && paneContainer.classList.contains('tab-content')) {
          paneContainer.querySelectorAll(':scope > .tab-pane').forEach(function (pane) {
            pane.classList.remove('show', 'active');
          });
        }

        trigger.classList.add('active');
        trigger.setAttribute('aria-selected', 'true');
        targetPane.classList.add('show', 'active');
      }

      function bindTabFallback() {
        document.addEventListener('click', function (event) {
          var trigger = event.target.closest('[data-bs-toggle="tab"], [data-bs-toggle="pill"]');
          if (!trigger) {
            return;
          }

          event.preventDefault();

          if (window.bootstrap && window.bootstrap.Tab) {
            window.bootstrap.Tab.getOrCreateInstance(trigger).show();
          }

          showManualTab(trigger);
        });
      }

      function initGeneralSubtabs() {
        var nav = document.querySelector('.general-subtabs');
        if (!nav) {
          return;
        }

        var wanted = null;
        try {
          wanted = window.sessionStorage.getItem('tetapan-sistem.general-subtab');
        } catch (storageError) {
          wanted = null;
        }

        if (!wanted) {
          var activeTrigger = nav.querySelector('.nav-link.active');
          wanted = activeTrigger
            ? String((activeTrigger.getAttribute('data-bs-target') || '').replace(/^#/, ''))
            : 'general-subtab-site';
        }

        var trigger = nav.querySelector('[data-bs-target="#' + wanted + '"]');
        if (trigger) {
          window.__tetapanShowGeneralSubtab(wanted, trigger);
        }
      }

      if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function () {
          bindTabFallback();
          initGeneralSubtabs();
        });
      } else {
        bindTabFallback();
        initGeneralSubtabs();
      }
    })();
  </script>

  <script>
    (function () {
      try {
        var storedAuthSubtab = window.sessionStorage.getItem('tetapan-sistem.auth-subtab');
        if (!storedAuthSubtab) {
          return;
        }

        var pane = document.getElementById(storedAuthSubtab);
        var trigger = document.querySelector('.auth-subtabs [data-bs-target="#' + storedAuthSubtab + '"]');
        if (pane && trigger) {
          window.__tetapanShowAuthSubtab(storedAuthSubtab, trigger);
        }
      } catch (storageError) {
        // ignore
      }
    })();
  </script>
</body>
</html>
