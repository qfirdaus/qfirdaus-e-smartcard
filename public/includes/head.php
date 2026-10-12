<head>
<!--
  IQS FRAMEWORK CORE FILE
  READ ONLY for downstream project programmers.
  Do not modify this file directly in template or cloned projects.
-->
<?php
  // Core init & settings
  require_once __DIR__ . '/init.php';
  $defaultHome = trim((string)app_config('site.default_home', 'pages/dashboard.php'));
  $metaAuthor = trim((string)app_config('system.meta_author', 'Base System'));
  $siteTitle = trim((string)app_config('site.title', 'Base System'));
  $faviconPath = trim((string)app_config('site.favicon', 'assets/images/favicon.ico'));
  $pageTitle = isset($PAGE_TITLE) ? trim((string)$PAGE_TITLE) : '';
  $metaDescription = isset($PAGE_META_DESCRIPTION) ? trim((string)$PAGE_META_DESCRIPTION) : '';
  $browserTitle = $pageTitle !== '' ? ($pageTitle . ' | ' . $siteTitle) : $siteTitle;
  if ($metaDescription === '') {
    $metaDescription = $pageTitle !== '' ? ($pageTitle . ' | ' . $siteTitle) : $siteTitle;
  }

  // Theme defaults
  $_SESSION['theme.layout'] = $_SESSION['theme.layout'] ?? 'light';
  $_SESSION['theme.topbar'] = $_SESSION['theme.topbar'] ?? 'light';
  $_SESSION['theme.menu']   = $_SESSION['theme.menu']   ?? 'light';

  // Per-page asset flags (fallback jika page tak set)
  $NEED_DATERANGE   = $NEED_DATERANGE   ?? false;
  $NEED_VECTORMAP   = $NEED_VECTORMAP   ?? false;
  $NEED_DATATABLES  = $NEED_DATATABLES  ?? false;
  $NEED_SELECT2     = $NEED_SELECT2     ?? false;
  $INCLUDE_I18N_PRESTASI = $INCLUDE_I18N_PRESTASI ?? false;
?>
  <meta charset="utf-8" />
  <title><?= htmlspecialchars($browserTitle, ENT_QUOTES, 'UTF-8') ?></title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta content="<?= htmlspecialchars($metaDescription, ENT_QUOTES, 'UTF-8') ?>" name="description" />
  <meta content="<?= htmlspecialchars($metaAuthor, ENT_QUOTES, 'UTF-8') ?>" name="author" />

  <!-- ⬇️ Tambah SATU blok ni di sini -->
  <?php if (function_exists('inject_base_meta')) {
        inject_base_meta();
     } else { // fallback kalau helper tak tersedia
        $bp = htmlspecialchars(base_path(), ENT_QUOTES, 'UTF-8');
        $bu = htmlspecialchars(base_url(),  ENT_QUOTES, 'UTF-8'); ?>
        <meta name="base-path" content="<?= $bp ?>">
        <meta name="base-url"  content="<?= $bu ?>">
  <?php } ?>
  <!-- ⬆️ Tamat meta base-path/base-url -->

  <!-- Canonical & favicon -->
  <link rel="canonical" href="<?= base_url($defaultHome) ?>" />
  <link rel="icon" type="image/x-icon" href="<?= h(base_url($faviconPath !== '' ? $faviconPath : 'assets/images/favicon.ico')) ?>" />

  <script src="<?= base_url('assets/js/theme-palette.js') ?>?v=<?= time(); ?>"></script>
  <script>
    (function () {
      var settings = <?= json_encode([
        'topbarColor' => $_SESSION['theme.topbar'] ?? 'light',
        'sidebarColor' => $_SESSION['theme.menu'] ?? 'light',
        'topbarCustomSeed' => $_SESSION['theme.topbar_custom_seed'] ?? SystemConfigConstants::DEFAULT_THEME_TOPBAR_CUSTOM_SEED,
        'sidebarCustomSeed' => $_SESSION['theme.sidebar_custom_seed'] ?? SystemConfigConstants::DEFAULT_THEME_SIDEBAR_CUSTOM_SEED,
      ], JSON_UNESCAPED_SLASHES) ?>;
      document.documentElement.setAttribute('data-topbar-color', settings.topbarColor);
      document.documentElement.setAttribute('data-menu-color', settings.sidebarColor);
      window.IQSThemePalette.applyTheme(settings);
      window.IQS_THEME_SETTINGS = settings;
    })();
  </script>

  <!-- App CSS (asas) -->
  <link rel="stylesheet" href="<?= base_url('assets/css/icons.min.css') ?>?v=<?= time(); ?>" />
  <link id="app-style" rel="stylesheet" href="<?= base_url('assets/css/app.css') ?>?v=<?= time(); ?>" />
  <link rel="stylesheet" href="<?= base_url('assets/css/custom.css') ?>?v=<?= time(); ?>" />
  <link rel="stylesheet" href="<?= base_url('assets/css/iqs-loader.css') ?>?v=<?= time(); ?>" />

  <!-- Plugin CSS (ikut keperluan page) -->
  <?php if ($NEED_DATERANGE): ?>
    <link rel="stylesheet" href="<?= base_url('assets/vendor/daterangepicker/daterangepicker.css') ?>" />
  <?php endif; ?>

  <?php if ($NEED_VECTORMAP): ?>
    <link rel="stylesheet" href="<?= base_url('assets/vendor/admin-resources/jquery.vectormap/jquery-jvectormap-1.2.2.css') ?>" />
  <?php endif; ?>

  <?php if ($NEED_DATATABLES): ?>
    <link href="<?= base_url('assets/vendor/datatables.net-bs5/css/dataTables.bootstrap5.min.css') ?>" rel="stylesheet" />
    <link href="<?= base_url('assets/vendor/datatables.net-responsive-bs5/css/responsive.bootstrap5.min.css') ?>" rel="stylesheet" />
    <link href="<?= base_url('assets/vendor/datatables.net-fixedcolumns-bs5/css/fixedColumns.bootstrap5.min.css') ?>" rel="stylesheet" />
    <link href="<?= base_url('assets/vendor/datatables.net-fixedheader-bs5/css/fixedHeader.bootstrap5.min.css') ?>" rel="stylesheet" />
    <link href="<?= base_url('assets/vendor/datatables.net-buttons-bs5/css/buttons.bootstrap5.min.css') ?>" rel="stylesheet" />
    <link href="<?= base_url('assets/vendor/datatables.net-select-bs5/css/select.bootstrap5.min.css') ?>" rel="stylesheet" />
  <?php endif; ?>

  <?php if ($NEED_SELECT2): ?>
    <link rel="stylesheet" href="<?= base_url('assets/vendor/select2/css/select2.min.css') ?>?v=<?= time(); ?>">
  <?php endif; ?>

  <!-- i18n: fungsi senyap (tiada console.warn) -->
  <script>
    (function () {
      var w = window;
      // Map terjemahan dari PHP (jika ada)
      w.__translations = <?= json_encode($translations_js ?? [], JSON_UNESCAPED_UNICODE) ?>;
      // Helper i18n senyap
      w.__ = function (key, fallback) {
        var dict = w.__translations || {};
        if (Object.prototype.hasOwnProperty.call(dict, key)) return dict[key] || '';
        return (fallback !== undefined ? fallback : (key || ''));
      };
    })();
  </script>

  <!-- i18n khusus Prestasi (opsyenal – hanya jika page minta) -->
  <?php if (!isset($INCLUDE_I18N_PRESTASI)) { $INCLUDE_I18N_PRESTASI = true; } ?>
  <?php if (!empty($INCLUDE_I18N_PRESTASI)): ?>
  <script>
    window.I18N_PRESTASI = {
      table_empty:             "<?= h(__('prestasi_js_table_empty')) ?>",
      year_selected_title:     "<?= h(__('prestasi_js_year_selected_title')) ?>",
      year_selected_text:      "<?= h(__('prestasi_js_year_selected_text')) ?>",
      ok:                      "<?= h(__('prestasi_js_ok')) ?>",
      cancel:                  "<?= h(__('prestasi_js_cancel')) ?>",
      saving:                  "<?= h(__('prestasi_js_saving')) ?>",
      success_title:           "<?= h(__('prestasi_js_success_title')) ?>",
      error_title:             "<?= h(__('prestasi_js_error_title')) ?>",
      non_json_prefix:         "<?= h(__('prestasi_js_non_json_prefix')) ?>",
      reminder_confirm_title:  "<?= h(__('prestasi_js_reminder_confirm_title')) ?>",
      email_missing_title:     "<?= h(__('prestasi_js_email_missing_title')) ?>",
      email_missing_text:      "<?= h(__('prestasi_js_email_missing_text')) ?>",
      reminder_sent_title:     "<?= h(__('prestasi_js_reminder_sent_title')) ?>",
      reminder_sent_text:      "<?= h(__('prestasi_js_reminder_sent_text')) ?>",
      reminder_failed_default: "<?= h(__('prestasi_js_reminder_failed_default')) ?>",
      server_error_prefix:     "<?= h(__('prestasi_js_server_error_prefix')) ?>",
      reminder_intro:          "<?= h(__('prestasi_js_reminder_intro')) ?>",
      reminder_footer:         "<?= h(__('prestasi_js_reminder_footer')) ?>",
      reminder_btn_send:       "<?= h(__('prestasi_js_reminder_btn_send')) ?>"
    };
  </script>
  <?php endif; ?>





  <!-- Safe Storage Shim: prevent errors when storage is blocked -->
  <script>
    (function(){
      function makeSafeStorage(){
        let store = {};
        return {
          getItem: (k)=> (Object.prototype.hasOwnProperty.call(store, k) ? store[k] : null),
          setItem: (k,v)=>{ store[k] = String(v); },
          removeItem: (k)=>{ delete store[k]; },
          clear: ()=>{ store = {}; },
          key: (i)=> Object.keys(store)[i] || null,
          get length(){ return Object.keys(store).length; }
        };
      }
      function safeStorageAccessor(type){
        try {
          const s = window[type];
          const testKey = '__storage_test__';
          s.setItem(testKey, '1');
          s.removeItem(testKey);
          return s;
        } catch(e) {
          try {
            const safe = makeSafeStorage();
            Object.defineProperty(window, type, { value: safe, writable: false, configurable: true });
            return safe;
          } catch(_) {
            return makeSafeStorage();
          }
        }
      }
      window.safeStorage = window.safeStorage || {
        local: safeStorageAccessor('localStorage'),
        session: safeStorageAccessor('sessionStorage'),
        get: (k)=> { try { return window.localStorage.getItem(k); } catch(e){ return null; } },
        set: (k,v)=> { try { window.localStorage.setItem(k,v); return true; } catch(e){ return false; } }
      };
    })();
  </script>

  <!-- Prefill theme ke <html> dan sync __CONFIG__ sebelum config.js (elak global->personal flicker) -->
  <script>
    (function () {
      const serverTheme = {
        layout: <?= json_encode($_SESSION['theme.layout'] ?? 'light', JSON_UNESCAPED_UNICODE) ?>,
        menu: <?= json_encode($_SESSION['theme.menu'] ?? 'light', JSON_UNESCAPED_UNICODE) ?>,
        topbar: <?= json_encode($_SESSION['theme.topbar'] ?? 'light', JSON_UNESCAPED_UNICODE) ?>
      };

      document.documentElement.setAttribute('data-bs-theme', serverTheme.layout);
      document.documentElement.setAttribute('data-menu-color', serverTheme.menu);
      document.documentElement.setAttribute('data-topbar-color', serverTheme.topbar);
      document.documentElement.setAttribute('data-sidenav-user', 'true');

      try {
        const raw = window.sessionStorage.getItem('__CONFIG__');
        const cfg = raw ? JSON.parse(raw) : {};

        cfg.nav = cfg.nav || 'vertical';
        cfg.layout = Object.assign({ mode: 'fluid', position: 'fixed' }, cfg.layout || {});
        cfg.theme = serverTheme.layout;
        cfg.topbar = Object.assign({ color: 'light' }, cfg.topbar || {}, { color: serverTheme.topbar });
        cfg.menu = Object.assign({ color: 'light' }, cfg.menu || {}, { color: serverTheme.menu });
        cfg.sidenav = Object.assign({ size: 'default', user: true }, cfg.sidenav || {}, { user: true });
        window.sessionStorage.setItem('__CONFIG__', JSON.stringify(cfg));
      } catch (e) {
        try { window.sessionStorage.removeItem('__CONFIG__'); } catch (_) {}
      }
    })();
  </script>

  <!-- Config Script -->
  <script src="<?= base_url('assets/js/config.js') ?>" defer></script>
</head>
