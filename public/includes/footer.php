<?php
/**
 * IQS FRAMEWORK CORE FILE
 *
 * READ ONLY for downstream project programmers.
 * Do not modify this file directly in template or cloned projects.
 * Custom changes must be implemented in project-specific files
 * or approved extension points.
 */
/**
 * Footer Component
 * 
 * This file contains:
 * - Footer HTML markup
 * - Theme Settings Offcanvas Panel
 * - Alert/Toast rendering logic
 * - JavaScript helper functions (confirm modals, theme preview)
 * 
 * @package e-Prestasi
 * @version 2.0
 */
// Load constants for theme validation
if (!class_exists('SystemConfigConstants')) {
    require_once __DIR__ . '/../classes/SystemConfigConstants.php';
}

$footerText = trim((string)app_config_localized('footer.text', __('footer_it')));
$organizationName = trim((string)app_config('organization.name', app_config('system.name', 'Base System')));
$organizationShort = trim((string)app_config('organization.short', ''));
$organizationWebsite = trim((string)app_config('organization.website', ''));
$supportEmail = trim((string)app_config('system.support', ''));
$systemVersion = app_current_version();
?>
<!-- Footer Start -->
<footer class="footer"
        data-organization-name="<?= h($organizationName) ?>"
        data-organization-short="<?= h($organizationShort) ?>"
        data-organization-website="<?= h($organizationWebsite) ?>"
        data-support-email="<?= h($supportEmail) ?>"
        data-system-name="<?= h(app_config('system.name', 'Base System')) ?>">
  <div class="container-fluid">
    <div class="row">
      <div class="col-md-6">
        <span id="footer-runtime-year"><?= date('Y') ?></span> © <span id="footer-runtime-text"><?= h($footerText !== '' ? $footerText : (string)__('footer_it')) ?></span> <span class="small text-muted ms-2"><?= h(app_current_version_label()) ?></span>
      </div>
      <div class="col-md-6">
        <div class="text-md-end footer-links d-none d-md-block">
          <a href="javascript:void(0);" id="footer-about-link"><?= h(__('footer_about')) ?></a>
          <a href="<?= base_url('pages/soalan-lazim.php') ?>"><?= h(__('footer_help')) ?></a>
          <a href="javascript:void(0);" id="footer-contact-link"><?= h(__('footer_contact')) ?></a>
        </div>
      </div>
    </div>
  </div>
</footer>

<!-- end Footer -->

<!-- ✅ Theme Settings Offcanvas -->
<div class="offcanvas offcanvas-end" tabindex="-1" id="theme-settings-offcanvas" aria-labelledby="themeSettingsLabel">
  <div class="offcanvas-header border-bottom">
    <h5 class="offcanvas-title" id="themeSettingsLabel"><?= h(__('theme_title')) ?></h5>
    <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="<?= h(__('theme_close')) ?>"></button>
  </div>
  <div class="offcanvas-body p-0">
    <div data-simplebar class="h-100">
      <div class="card mb-0 p-3">

        <!-- ✅ Color Scheme -->
        <h5 class="my-3 fs-16 fw-bold"><?= h(__('theme_color_scheme')) ?></h5>
        <div class="d-flex flex-column gap-2">
          <?php foreach (SystemConfigConstants::ALLOWED_THEME_MODES as $mode): ?>
            <div class="form-check form-switch">
              <input class="form-check-input"
                     type="radio"
                     name="data-bs-theme"
                     id="layout-color-<?= h($mode) ?>"
                     value="<?= h($mode) ?>"
                     <?= (($_SESSION['theme.layout'] ?? 'light') === $mode) ? 'checked' : '' ?>>
              <label class="form-check-label" for="layout-color-<?= h($mode) ?>"><?= h(__('theme_'.$mode)) ?></label>
            </div>
          <?php endforeach; ?>
        </div>
        <!-- ✅ Topbar Colour -->
        <h5 class="my-3 fs-16 fw-bold"><?= h(__('theme_topbar_color')) ?></h5>
        <div class="d-flex flex-column gap-2">
          <?php foreach (SystemConfigConstants::ALLOWED_THEME_COLORS as $topbar): ?>
            <div class="form-check form-switch">
              <input class="form-check-input"
                     type="radio"
                     name="data-topbar-color"
                     id="topbar-color-<?= h($topbar) ?>"
                     value="<?= h($topbar) ?>"
                     <?= (($_SESSION['theme.topbar'] ?? 'light') === $topbar) ? 'checked' : '' ?>>
              <label class="form-check-label" for="topbar-color-<?= h($topbar) ?>"><?= h(__('theme_'.$topbar)) ?></label>
            </div>
          <?php endforeach; ?>
        </div>
        <div class="mt-3 p-2 border rounded" data-user-custom-editor="topbar">
          <label class="form-label small fw-semibold" for="user-topbar-custom-seed"><?= h(__('config_theme_custom_seed_label') ?: 'Custom base colour') ?></label>
          <div class="input-group input-group-sm">
            <input type="color" class="form-control form-control-color" id="user-topbar-custom-picker" value="<?= h($_SESSION['theme.topbar_custom_seed'] ?? SystemConfigConstants::DEFAULT_THEME_TOPBAR_CUSTOM_SEED) ?>">
            <input type="text" class="form-control font-monospace" id="user-topbar-custom-seed" maxlength="7" value="<?= h($_SESSION['theme.topbar_custom_seed'] ?? SystemConfigConstants::DEFAULT_THEME_TOPBAR_CUSTOM_SEED) ?>">
          </div>
          <div class="form-text"><?= h(__('config_theme_custom_help') ?: 'Gradient and contrast are generated automatically.') ?></div>
        </div>

        <!-- ✅ Sidebar Colour -->
        <h5 class="my-3 fs-16 fw-bold"><?= h(__('theme_menu_color')) ?></h5>
        <div class="d-flex flex-column gap-2">
          <?php foreach (SystemConfigConstants::ALLOWED_THEME_COLORS as $menu): ?>
            <div class="form-check form-switch">
              <input class="form-check-input"
                     type="radio"
                     name="data-menu-color"
                     id="leftbar-color-<?= h($menu) ?>"
                     value="<?= h($menu) ?>"
                     <?= (($_SESSION['theme.menu'] ?? 'light') === $menu) ? 'checked' : '' ?>>
              <label class="form-check-label" for="leftbar-color-<?= h($menu) ?>"><?= h(__('theme_'.$menu)) ?></label>
            </div>
          <?php endforeach; ?>
        </div>
        <div class="mt-3 p-2 border rounded" data-user-custom-editor="sidebar">
          <label class="form-label small fw-semibold" for="user-sidebar-custom-seed"><?= h(__('config_theme_custom_seed_label') ?: 'Custom base colour') ?></label>
          <div class="input-group input-group-sm">
            <input type="color" class="form-control form-control-color" id="user-sidebar-custom-picker" value="<?= h($_SESSION['theme.sidebar_custom_seed'] ?? SystemConfigConstants::DEFAULT_THEME_SIDEBAR_CUSTOM_SEED) ?>">
            <input type="text" class="form-control font-monospace" id="user-sidebar-custom-seed" maxlength="7" value="<?= h($_SESSION['theme.sidebar_custom_seed'] ?? SystemConfigConstants::DEFAULT_THEME_SIDEBAR_CUSTOM_SEED) ?>">
          </div>
          <div class="form-text"><?= h(__('config_theme_custom_help') ?: 'Gradient and contrast are generated automatically.') ?></div>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- ✅ Render Alert/Toast -->
<?php
/**
 * Alert Rendering Logic
 * 
 * Handles display of session-based alerts/toasts with special handling
 * for dashboard page to prevent alert carry-over.
 * 
 * @var array $BLOCKED_ALERT_PAGES Pages where success alerts are blocked
 */
    // Centralized page detection for alert blocking
    // Pages where success alerts should be blocked to prevent carry-over
    $BLOCKED_ALERT_PAGES = ['dashboard.php', 'pages/dashboard.php'];
    
    // Determine current page and alert from session
    $__page  = basename(parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?: '');
    $__alert = $_SESSION['alert'] ?? null;

    // Identify success alerts
    $__is_success = is_array($__alert) && (
        (($__alert['icon'] ?? '') === 'success') ||
        (($__alert['type'] ?? '') === 'success')
    );

    // Check if current page should block success alerts
    $__is_dashboard = in_array($__page, $BLOCKED_ALERT_PAGES, true);

    // Render alert only if not blocked
    if (function_exists('render_alert')) {
        if ($__is_dashboard && $__is_success) {
            // Skip & clean up to prevent carry-over to other pages
            unset($_SESSION['alert']);
        } else {
            try {
            render_alert();
            } catch (\Throwable $e) {
                // Log error but don't break page rendering
                error_log("[Footer] Alert render failed: " . $e->getMessage());
            }
        }
    }
?>


<!-- ✅ SweetAlert2 Fallback Loader -->
<script>
/**
 * SweetAlert2 CDN Fallback Loader
 * 
 * Dynamically loads SweetAlert2 from CDN if not already available.
 * Includes security measures (CORS, error handling).
 * 
 * @constant {string} SWEETALERT2_CDN_URL - CDN URL for SweetAlert2
 */
(function() {
  'use strict';
  
  if (typeof window.Swal === 'undefined') {
    var s = document.createElement('script');
    s.src = <?= json_encode(base_url('assets/vendor/sweetalert2/sweetalert2.all.min.js')) ?>;
    s.crossOrigin = "anonymous";
    s.onerror = function() {
      console.error('[Footer] Failed to load SweetAlert2 from local asset');
    };
    s.onload = function() {
      if (typeof window.Swal === 'undefined') {
        console.warn('[Footer] SweetAlert2 script loaded but Swal object not available');
      }
    };
    document.head.appendChild(s);
  }
    })();
</script>

<!-- ✅ Helper JS (swalert helper) -->
<?php if (function_exists('load_js_helper')) load_js_helper('swalert_helper'); ?>

<script>
/**
 * Generic Confirm Modal Helper
 * 
 * Displays a SweetAlert2 confirmation dialog with customizable options.
 * 
 * @param {string} message - The message to display in the alert
 * @param {string} [confirmText] - Text for the confirm button (default: logout alert title)
 * @param {string|null} [redirectUrl] - URL to redirect to on confirm (optional)
 * @param {Function|null} [onConfirm] - Callback function to execute on confirm (optional)
 * @returns {boolean}
 */
function set_confirm(message, confirmText = <?= json_encode(__('logout_alert_title')) ?>, redirectUrl = null, onConfirm = null) {
  // Input validation
  if (typeof message !== 'string' || message.trim() === '') {
    console.error('[Footer] set_confirm: message must be a non-empty string');
    return false;
  }
  
  if (confirmText !== null && typeof confirmText !== 'string') {
    console.error('[Footer] set_confirm: confirmText must be a string or null');
    return false;
  }
  
  if (redirectUrl !== null && typeof redirectUrl !== 'string') {
    console.error('[Footer] set_confirm: redirectUrl must be a string or null');
    return false;
  }
  
  if (onConfirm !== null && typeof onConfirm !== 'function') {
    console.error('[Footer] set_confirm: onConfirm must be a function or null');
    return false;
  }
  
  const run = () => {
    Swal.fire({
      icon: 'question',
      title: <?= json_encode(__('logout_alert_title')) ?>,
      text: message,
      showCancelButton: true,
      confirmButtonText: confirmText,
      cancelButtonText: <?= json_encode(__('logout_alert_no')) ?>,
      reverseButtons: true,
      focusCancel: true,
      background: (document.documentElement.getAttribute('data-bs-theme') === 'dark') ? '#1e1e2d' : '#fff',
    }).then((result) => {
      if (result.isConfirmed) {
        if (typeof onConfirm === 'function') {
          try {
            onConfirm();
          } catch (e) {
            console.error('[Footer] set_confirm: onConfirm callback error:', e);
          }
        } else if (redirectUrl && typeof redirectUrl === 'string') {
          if (window.AppLoader && typeof window.AppLoader.show === 'function') {
            window.AppLoader.show((window.IQS_LOADER_I18N && window.IQS_LOADER_I18N.logout) || confirmText);
          }
          window.location.href = redirectUrl;
        }
      }
    }).catch((error) => {
      console.error('[Footer] set_confirm: SweetAlert error:', error);
    });
  };
  
  // Wait for SweetAlert2 to load if not available
  if (typeof Swal === 'undefined') {
    let retryCount = 0;
    const maxRetries = 100; // 5 seconds max (100 * 50ms)
    const iv = setInterval(() => {
      retryCount++;
      if (typeof Swal !== 'undefined') {
        clearInterval(iv);
        run();
      } else if (retryCount >= maxRetries) {
        clearInterval(iv);
        console.error('[Footer] set_confirm: SweetAlert2 failed to load after 5 seconds');
      }
    }, 50);
  } else {
    run();
  }

  return false;
}

/**
 * Logout Confirmation Wrapper
 * 
 * Displays a confirmation dialog before logging out.
 * 
 * @param {Event|undefined} event
 * @returns {boolean}
 */
function confirmLogout(event) {
  if (event && typeof event.preventDefault === 'function') {
    event.preventDefault();
  }
  if (event && typeof event.stopPropagation === 'function') {
    event.stopPropagation();
  }

  return set_confirm(
    <?= json_encode(__('logout_alert_text')) ?>,
    <?= json_encode(__('logout_alert_yes')) ?>,
    <?= json_encode(base_url('logout.php')) ?>
  );
}

/**
 * Live Theme Preview Handler
 * 
 * Provides real-time theme preview functionality with security validation.
 * Changes are stored in localStorage for preview only (not persisted to DB).
 * 
 * Performance: Debounced updates, cached selectors, optimized DOM queries
 * UX: Visual feedback via toast notifications, session sync
 * Security: Whitelist validation prevents XSS injection via localStorage manipulation.
 * 
 * Note: This is preview only. To save permanently, use Tetapan Sistem page.
 */
(function() {
  'use strict';
  
  // Allowed theme values (whitelist) - sync with SystemConfigConstants
  const ALLOWED_LAYOUT_MODES = <?= json_encode(SystemConfigConstants::ALLOWED_THEME_MODES, JSON_UNESCAPED_UNICODE) ?>;
  const ALLOWED_COLOR_OPTIONS = <?= json_encode(SystemConfigConstants::ALLOWED_THEME_COLORS, JSON_UNESCAPED_UNICODE) ?>;
  const DEFAULT_LAYOUT = '<?= SystemConfigConstants::DEFAULT_THEME_LAYOUT ?>';
  const DEFAULT_COLOR = '<?= SystemConfigConstants::DEFAULT_THEME_TOPBAR ?>';
  
  // Performance: Debounce delay (ms) - prevents excessive DOM updates
  const DEBOUNCE_DELAY = 150;
  
  // Performance: Cache DOM elements
  let cachedElements = {
    documentElement: null,
    body: null,
    topbar: null,
    sidebar: null
  };
  
  // Session values from server (priority over localStorage)
  const SESSION_THEME = {
    layout: <?= json_encode($_SESSION['theme.layout'] ?? null, JSON_UNESCAPED_UNICODE) ?>,
    topbar: <?= json_encode($_SESSION['theme.topbar'] ?? null, JSON_UNESCAPED_UNICODE) ?>,
    menu: <?= json_encode($_SESSION['theme.menu'] ?? null, JSON_UNESCAPED_UNICODE) ?>
  };
  const THEME_TOAST_TITLE = <?= json_encode(__('tema_title_save') ?: __('theme_applied') ?: 'Theme updated', JSON_UNESCAPED_UNICODE) ?>;
  const THEME_VALUE_LABELS = <?= json_encode(array_merge(
    array_combine(
      SystemConfigConstants::ALLOWED_THEME_MODES,
      array_map(static fn($value) => (string)__('theme_' . $value), SystemConfigConstants::ALLOWED_THEME_MODES)
    ),
    array_combine(
      SystemConfigConstants::ALLOWED_THEME_COLORS,
      array_map(static fn($value) => (string)__('theme_' . $value), SystemConfigConstants::ALLOWED_THEME_COLORS)
    )
  ), JSON_UNESCAPED_UNICODE) ?>;
  
  // Debounce timers
  const debounceTimers = {};
  
  /**
   * Debounce function to limit execution frequency
   * 
   * @param {string} key - Unique key for the debounce timer
   * @param {Function} fn - Function to execute
   * @param {number} delay - Delay in milliseconds
   * @returns {void}
   */
  function debounce(key, fn, delay) {
    if (debounceTimers[key]) {
      clearTimeout(debounceTimers[key]);
    }
    debounceTimers[key] = setTimeout(fn, delay);
  }
  
  /**
   * Show toast notification for theme change
   * 
   * @param {string} sectionLabel - Theme section label
   * @param {string} value - Selected theme value
   * @returns {void}
   */
  function showThemeToast(sectionLabel, value) {
    const optionLabel = THEME_VALUE_LABELS[value] || value;
    const detail = sectionLabel ? `${sectionLabel}: ${optionLabel}` : optionLabel;

    // Use SweetAlert2 if available, otherwise fallback to console
    if (typeof Swal !== 'undefined') {
      Swal.fire({
        icon: 'success',
        title: THEME_TOAST_TITLE,
        text: detail,
        toast: true,
        position: 'top-end',
        showConfirmButton: false,
        timer: 1800,
        timerProgressBar: true
      });
    }
  }
  
  /**
   * Validate and sanitize theme value against whitelist
   * 
   * @param {string} value - Theme value to validate
   * @param {string[]} allowedValues - Array of allowed values
   * @param {string|null} defaultValue - Default value if invalid (null = skip)
   * @returns {string|null} - Validated value or null if invalid and no default
   */
  function validateThemeValue(value, allowedValues, defaultValue) {
    if (typeof value !== 'string') return defaultValue;
    const trimmed = value.trim();
    if (trimmed === '') return defaultValue;
    return allowedValues.includes(trimmed) ? trimmed : defaultValue;
  }

  /**
   * Get cached DOM element or cache it
   * 
   * @param {string} key - Element key
   * @param {Function} getter - Function to get element if not cached
   * @returns {HTMLElement|null} - Cached element
   */
  function getCachedElement(key, getter) {
    if (!cachedElements[key]) {
      cachedElements[key] = getter();
    }
    return cachedElements[key];
  }

  /**
   * Apply theme value to DOM element with performance optimization
   * 
   * @param {HTMLElement} element - Target element
   * @param {string} attribute - Attribute name to set
   * @param {string} value - Value to set
   * @returns {void}
   */
  function applyThemeAttribute(element, attribute, value) {
    if (!element || typeof value !== 'string') return;
    try {
      // Only update if value changed (performance optimization)
      const currentValue = element.getAttribute(attribute);
      if (currentValue === value) return;
      
      element.setAttribute(attribute, value);
      
      // Update topbar class if needed
      if (attribute === 'data-topbar-color' && cachedElements.topbar) {
        const topbar = cachedElements.topbar;
        topbar.className = topbar.className
          .split(' ')
          .filter(c => !c.startsWith('topbar-'))
          .join(' ')
          .trim();
        topbar.classList.add('topbar-' + value);
      }
      
      // Update sidebar if needed
      if (attribute === 'data-menu-color' && cachedElements.sidebar) {
        cachedElements.sidebar.setAttribute('data-menu-color', value);
      }
    } catch (e) {
      console.error('[Footer] Failed to apply theme attribute:', e);
    }
  }

  /**
   * Setup theme change listeners with debouncing and visual feedback
   * 
   * @param {string} inputName - Name attribute of radio inputs
   * @param {string[]} allowedValues - Allowed values for validation
   * @param {string} storageKey - localStorage key
   * @param {HTMLElement} targetElement - Target element to apply theme
   * @param {string} attributeName - Attribute name to set
   * @param {string} defaultValue - Default value if invalid
   * @param {string} sectionLabel - Display label for toast notification
   * @returns {void}
   */
  function setupThemeListener(inputName, allowedValues, storageKey, targetElement, attributeName, defaultValue, sectionLabel) {
    // Cache selector query
    const inputs = document.querySelectorAll(`input[name="${inputName}"]`);
    
    inputs.forEach(input => {
      input.addEventListener('change', function(e) {
        const value = validateThemeValue(e.target.value, allowedValues, defaultValue);
        if (!value) return;
        
        // Debounce DOM updates
        debounce(`theme-${inputName}`, function() {
          applyThemeAttribute(targetElement, attributeName, value);
          
          // Save to localStorage
          try {
            localStorage.setItem(storageKey, value);
          } catch (e) {
            console.error(`[Footer] Failed to save ${storageKey} to localStorage:`, e);
          }
          
          // Show visual feedback
          if (sectionLabel) {
            showThemeToast(sectionLabel, value);
          }
        }, DEBOUNCE_DELAY);
    });
  });
  }

  /**
   * Get theme value with priority: Session > localStorage > default
   * 
   * @param {string} sessionKey - Session key (layout/topbar/menu)
   * @param {string} storageKey - localStorage key
   * @param {string[]} allowedValues - Allowed values for validation
   * @param {string} defaultValue - Default value
   * @returns {string} - Theme value
   */
  function getThemeValue(sessionKey, storageKey, allowedValues, defaultValue) {
    // Priority 1: Session (from server)
    if (SESSION_THEME[sessionKey]) {
      const validated = validateThemeValue(SESSION_THEME[sessionKey], allowedValues, null);
      if (validated) return validated;
    }
    
    // Priority 2: localStorage
    try {
      const stored = localStorage.getItem(storageKey);
      if (stored) {
        const validated = validateThemeValue(stored, allowedValues, null);
        if (validated) return validated;
      }
    } catch (e) {
      console.error(`[Footer] Failed to read ${storageKey} from localStorage:`, e);
    }
    
    // Priority 3: Default
    return defaultValue;
  }

  /**
   * Apply theme from session/localStorage on page load
   * 
   * @param {string} sessionKey - Session key (layout/topbar/menu)
   * @param {string} storageKey - localStorage key
   * @param {string[]} allowedValues - Allowed values for validation
   * @param {HTMLElement} targetElement - Target element to apply theme
   * @param {string} attributeName - Attribute name to set
   * @param {string} defaultValue - Default value
   * @returns {void}
   */
  function applyStoredTheme(sessionKey, storageKey, allowedValues, targetElement, attributeName, defaultValue) {
    const value = getThemeValue(sessionKey, storageKey, allowedValues, defaultValue);
    if (value) {
      applyThemeAttribute(targetElement, attributeName, value);
    }
  }

  // Initialize on DOM ready
  document.addEventListener('DOMContentLoaded', function() {
    // Cache DOM elements for performance
    cachedElements.documentElement = document.documentElement;
    cachedElements.body = document.body;
    cachedElements.topbar = document.getElementById('topbar');
    cachedElements.sidebar = document.getElementById('leftside-menu');
    
    // Apply stored themes on page load (with session priority)
    applyStoredTheme('layout', 'theme.layout', ALLOWED_LAYOUT_MODES, cachedElements.documentElement, 'data-bs-theme', DEFAULT_LAYOUT);
    applyStoredTheme('topbar', 'theme.topbar', ALLOWED_COLOR_OPTIONS, cachedElements.body, 'data-topbar-color', DEFAULT_COLOR);
    applyStoredTheme('menu', 'theme.menu', ALLOWED_COLOR_OPTIONS, cachedElements.body, 'data-menu-color', DEFAULT_COLOR);
    
    // Setup theme change listeners with debouncing
    setupThemeListener(
      'data-bs-theme',
      ALLOWED_LAYOUT_MODES,
      'theme.layout',
      cachedElements.documentElement,
      'data-bs-theme',
      DEFAULT_LAYOUT,
      'Layout'
    );

    setupThemeListener(
      'data-topbar-color',
      ALLOWED_COLOR_OPTIONS,
      'theme.topbar',
      cachedElements.body,
      'data-topbar-color',
      DEFAULT_COLOR,
      'Topbar'
    );

    setupThemeListener(
      'data-menu-color',
      ALLOWED_COLOR_OPTIONS,
      'theme.menu',
      cachedElements.body,
      'data-menu-color',
      DEFAULT_COLOR,
      'Sidebar'
    );
  });
})();

/**
 * Footer Links - Tentang Kami & Hubungi Kami
 * 
 * Display SweetAlert2 message when user clicks on "Tentang Kami" or "Hubungi Kami"
 */
document.addEventListener('DOMContentLoaded', function() {
  const aboutLink = document.getElementById('footer-about-link');
  const contactLink = document.getElementById('footer-contact-link');
  const initialRuntimeInfo = {
    orgName: <?= json_encode($organizationName !== '' ? $organizationName : app_config('system.name', 'Base System'), JSON_UNESCAPED_UNICODE) ?>,
    orgShort: <?= json_encode($organizationShort, JSON_UNESCAPED_UNICODE) ?>,
    orgWebsite: <?= json_encode($organizationWebsite, JSON_UNESCAPED_UNICODE) ?>,
    supportEmail: <?= json_encode($supportEmail, JSON_UNESCAPED_UNICODE) ?>,
    systemName: <?= json_encode(app_config('system.name', 'Base System'), JSON_UNESCAPED_UNICODE) ?>
  };
  const fallbackTitle = <?= json_encode(__('footer_content_updating_title') ?: 'Maklumat', JSON_UNESCAPED_UNICODE) ?>;
  const fallbackText = <?= json_encode(__('footer_content_updating') ?: 'Kandungan sedang dikemaskini.', JSON_UNESCAPED_UNICODE) ?>;
  const okText = <?= json_encode(__('footer_content_updating_ok') ?: 'OK', JSON_UNESCAPED_UNICODE) ?>;

  const readRuntimeInfo = function() {
    const footer = document.querySelector('.footer');
    const data = footer ? footer.dataset : {};
    return {
      orgName: String(data.organizationName || initialRuntimeInfo.orgName || initialRuntimeInfo.systemName || '').trim(),
      orgShort: String(data.organizationShort || initialRuntimeInfo.orgShort || '').trim(),
      orgWebsite: String(data.organizationWebsite || initialRuntimeInfo.orgWebsite || '').trim(),
      supportEmail: String(data.supportEmail || initialRuntimeInfo.supportEmail || '').trim()
    };
  };
  
  const buildAboutHtml = function() {
    const info = readRuntimeInfo();
    const lines = [];
    if (info.orgName) {
      lines.push(`<div><strong>${escapeHtml(info.orgName)}</strong>${info.orgShort ? ` <span class="text-muted">(${escapeHtml(info.orgShort)})</span>` : ''}</div>`);
    }
    if (info.orgWebsite && info.orgWebsite !== '#') {
      lines.push(`<div class="mt-2"><a href="${escapeAttribute(info.orgWebsite)}" target="_blank" rel="noopener noreferrer">${escapeHtml(info.orgWebsite)}</a></div>`);
    }
    return lines.join('') || `<div>${escapeHtml(fallbackText)}</div>`;
  };

  const buildContactHtml = function() {
    const info = readRuntimeInfo();
    const lines = [];
    if (info.supportEmail) {
      lines.push(`<div><a href="mailto:${escapeAttribute(info.supportEmail)}">${escapeHtml(info.supportEmail)}</a></div>`);
    }
    if (info.orgWebsite && info.orgWebsite !== '#') {
      lines.push(`<div class="mt-2"><a href="${escapeAttribute(info.orgWebsite)}" target="_blank" rel="noopener noreferrer">${escapeHtml(info.orgWebsite)}</a></div>`);
    }
    return lines.join('') || `<div>${escapeHtml(fallbackText)}</div>`;
  };

  const showInfoMessage = function(title, htmlContent) {
    if (typeof Swal !== 'undefined') {
      Swal.fire({
        icon: 'info',
        title: title,
        html: htmlContent,
        confirmButtonText: okText,
        background: (document.documentElement.getAttribute('data-bs-theme') === 'dark') ? '#1e1e2d' : '#fff',
      });
    } else {
      const fallbackContainer = document.createElement('div');
      fallbackContainer.innerHTML = htmlContent;
      const text = fallbackContainer.textContent || fallbackContainer.innerText || fallbackText;
      alert(text);
    }
  };

  function escapeHtml(value) {
    return String(value ?? '')
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#39;');
  }

  function escapeAttribute(value) {
    return escapeHtml(value).replace(/`/g, '&#96;');
  }
  
  if (aboutLink) {
    aboutLink.addEventListener('click', function(e) {
      e.preventDefault();
      showInfoMessage(aboutLink.textContent.trim() || fallbackTitle, buildAboutHtml());
    });
  }
  
  if (contactLink) {
    contactLink.addEventListener('click', function(e) {
      e.preventDefault();
      showInfoMessage(contactLink.textContent.trim() || fallbackTitle, buildContactHtml());
    });
  }
});
</script>
