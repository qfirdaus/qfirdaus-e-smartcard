// ============================================
// ✅ Safe storage helpers (avoid errors when storage is blocked)
// ============================================
const safeStorage = (typeof window !== 'undefined' && window.safeStorage) ? window.safeStorage : {
  get(key) {
    try {
      if (typeof localStorage === 'undefined') return null;
      return localStorage.getItem(key);
    } catch (e) {
      return null;
    }
  },
  set(key, value) {
    try {
      if (typeof localStorage === 'undefined') return false;
      localStorage.setItem(key, value);
      return true;
    } catch (e) {
      return false;
    }
  }
};

let themeSaveRequestId = 0;

// ============================================
// ✅ Apply Theme to UI
// ============================================
function applyThemeSetting() {
  const serverTopbarColor = document.body.getAttribute('data-topbar-color')
    || document.documentElement.getAttribute('data-topbar-color')
    || '';
  const serverSidebarColor = document.body.getAttribute('data-menu-color')
    || document.documentElement.getAttribute('data-menu-color')
    || '';
  const serverLayoutMode = document.documentElement.getAttribute('data-bs-theme')
    || document.body.getAttribute('data-bs-theme')
    || '';

  const topbarColor = serverTopbarColor
    || safeStorage.get('topbar-color')
    || safeStorage.get('theme.topbar')
    || 'light';
  const sidebarColor = serverSidebarColor
    || safeStorage.get('sidebar-color')
    || safeStorage.get('theme.menu')
    || 'light';
  const layoutMode = serverLayoutMode
    || safeStorage.get('layout-mode')
    || safeStorage.get('theme.layout')
    || 'light';

  // ✅ Apply to DOM immediately
  document.documentElement.setAttribute('data-bs-theme', layoutMode);
  document.body.setAttribute('data-bs-theme', layoutMode);
  document.documentElement.setAttribute('data-topbar-color', topbarColor);
  document.body.setAttribute('data-topbar-color', topbarColor);
  document.documentElement.setAttribute('data-menu-color', sidebarColor);
  document.body.setAttribute('data-menu-color', sidebarColor);

  const topbarCustomSeed = (window.IQS_THEME_SETTINGS && window.IQS_THEME_SETTINGS.topbarCustomSeed)
    || safeStorage.get('theme.topbarCustomSeed')
    || '#4254BA';
  const sidebarCustomSeed = (window.IQS_THEME_SETTINGS && window.IQS_THEME_SETTINGS.sidebarCustomSeed)
    || safeStorage.get('theme.sidebarCustomSeed')
    || '#1E3A5F';
  if (window.IQSThemePalette) {
    window.IQSThemePalette.applyTheme({
      topbarColor: topbarColor,
      sidebarColor: sidebarColor,
      topbarCustomSeed: topbarCustomSeed,
      sidebarCustomSeed: sidebarCustomSeed
    });
  }
  safeStorage.set('theme.topbarCustomSeed', topbarCustomSeed);
  safeStorage.set('theme.sidebarCustomSeed', sidebarCustomSeed);

  // ✅ Update topbar
  const topbar = document.getElementById('topbar');
  if (topbar) {
    topbar.className = topbar.className
      .split(' ')
      .filter(c => !c.startsWith('topbar-'))
      .join(' ')
      .trim();
    topbar.classList.add('topbar-' + topbarColor);
  }

  // ✅ Update sidebar
  const sidebar = document.getElementById('leftside-menu');
  if (sidebar) {
    sidebar.setAttribute('data-menu-color', sidebarColor);
  }

  // ✅ Update theme icon if exists
  if (typeof updateThemeIcon === 'function') {
    updateThemeIcon(layoutMode === 'dark');
  }

  // ✅ Keep legacy/new localStorage aliases aligned to the server-rendered theme.
  // This prevents old global preview values from repainting the sidebar after reload.
  safeStorage.set('topbar-color', topbarColor);
  safeStorage.set('sidebar-color', sidebarColor);
  safeStorage.set('layout-mode', layoutMode);
  safeStorage.set('theme.topbar', topbarColor);
  safeStorage.set('theme.menu', sidebarColor);
  safeStorage.set('theme.layout', layoutMode);

  // ✅ Sync radio buttons in offcanvas if open
  const config = {
    'data-bs-theme': layoutMode,
    'data-topbar-color': topbarColor,
    'data-menu-color': sidebarColor
  };
  Object.entries(config).forEach(([key, val]) => {
    const input = document.querySelector(`input[name="${key}"][value="${val}"]`);
    if (input) {
      input.checked = true;
    }
  });
}

// ============================================
// ✅ Helper: Get CSRF Token
// ============================================
function getCSRFToken() {
  // Check window variable (set in page)
  if (typeof window.csrfToken !== 'undefined' && window.csrfToken) {
    return window.csrfToken;
  }
  
  // Check meta tag
  const metaTag = document.querySelector('meta[name="csrf-token"]');
  if (metaTag && metaTag.content) {
    return metaTag.content;
  }
  
  // Check localStorage (fallback, less secure)
  const stored = safeStorage.get('csrf_token');
  if (stored) return stored;
  
  return '';
}

// ============================================
// ✅ Save to Server
// ============================================
function saveThemeSettingToServer(callback = null) {
  const requestId = ++themeSaveRequestId;
  const setting = {
    sidebarColor: document.body.getAttribute('data-menu-color') || safeStorage.get('sidebar-color') || 'dark',
    topbarColor: document.body.getAttribute('data-topbar-color') || safeStorage.get('topbar-color') || 'light',
    layoutMode: document.documentElement.getAttribute('data-bs-theme') || safeStorage.get('layout-mode') || 'light',
    topbarCustomSeed: safeStorage.get('theme.topbarCustomSeed') || '#4254BA',
    sidebarCustomSeed: safeStorage.get('theme.sidebarCustomSeed') || '#1E3A5F'
  };

  // ✅ Add CSRF token to request
  const csrfToken = getCSRFToken();
  if (csrfToken) {
    setting.csrf_token = csrfToken;
  }

  // ✅ Use window.BASE_URL or fallback to meta tag
  let baseUrl = window.BASE_URL;
  if (!baseUrl) {
    const metaBaseUrl = document.querySelector('meta[name="base-url"]');
    if (metaBaseUrl && metaBaseUrl.content) {
      baseUrl = metaBaseUrl.content;
    } else {
      // Fallback: construct from current location
      baseUrl = window.location.origin + window.location.pathname.split('/').slice(0, -1).join('/');
      if (!baseUrl.endsWith('/app')) {
        baseUrl = baseUrl.replace(/\/app$/, '') + '/app';
      }
    }
  }

  if (!baseUrl) {
    if (typeof callback === 'function') callback(false);
    return;
  }

  // Ensure baseUrl ends with /
  if (!baseUrl.endsWith('/')) {
    baseUrl += '/';
  }

  const url = baseUrl + 'setting/save_theme.php';

  fetch(url, {
    method: 'POST',
    noLoader: true,
    headers: { 
      'Content-Type': 'application/json',
      'X-No-Loader': '1',
      // Also send in header for compatibility
      ...(csrfToken ? { 'X-CSRF-Token': csrfToken } : {})
    },
    body: JSON.stringify(setting)
  })
    .then(res => {
      // Check if response is OK
      if (!res.ok) {
        return res.text().then(text => {
          try {
            return JSON.parse(text);
          } catch {
            throw new Error(text || 'Network error: ' + res.status);
          }
        });
      }
      return res.json();
    })
    .then(data => {
      if (data.success) {
        // ✅ Apply theme immediately after successful save
        applyThemeSetting();
        if (typeof callback === 'function') callback(true);
      } else {
        // Show user-friendly error
        if (typeof Swal !== 'undefined') {
          Swal.fire({
            icon: 'error',
            title: 'Gagal Simpan Tema',
            text: data.message || 'Ralat tidak diketahui. Sila cuba lagi.',
            timer: 3000
          });
        } else {
          alert('Gagal simpan tema: ' + (data.message || 'Ralat tidak diketahui'));
        }
        if (typeof callback === 'function') callback(false);
      }
    })
    .catch(err => {
      // Show user-friendly error
      if (typeof Swal !== 'undefined') {
        Swal.fire({
          icon: 'error',
          title: 'Ralat Rangkaian',
          text: 'Tidak dapat menyambung ke server. Sila semak sambungan internet anda.',
          timer: 3000
        });
      } else {
        alert('Ralat rangkaian: ' + err.message);
      }
      if (typeof callback === 'function') callback(false);
    });
}

// ============================================
// ✅ Update Local + Save to Server
// ============================================
function updateThemeSetting(key, value) {
  safeStorage.set(key, value);
  if (key === 'sidebar-color') {
    safeStorage.set('theme.menu', value);
    document.documentElement.setAttribute('data-menu-color', value);
    document.body.setAttribute('data-menu-color', value);
  }
  if (key === 'topbar-color') {
    safeStorage.set('theme.topbar', value);
    document.documentElement.setAttribute('data-topbar-color', value);
    document.body.setAttribute('data-topbar-color', value);
  }
  if (key === 'layout-mode') {
    safeStorage.set('theme.layout', value);
    document.documentElement.setAttribute('data-bs-theme', value);
    document.body.setAttribute('data-bs-theme', value);
  }
  // ✅ Apply theme immediately for instant visual feedback
  applyThemeSetting();
  // ✅ Then save to server
  saveThemeSettingToServer();
}

function updateCustomThemeSeed(surface, value, persist) {
  if (!window.IQSThemePalette) return false;
  const normalized = window.IQSThemePalette.normalizeHex(value, null);
  if (!normalized) return false;
  const isTopbar = surface === 'topbar';
  safeStorage.set(isTopbar ? 'theme.topbarCustomSeed' : 'theme.sidebarCustomSeed', normalized);
  window.IQS_THEME_SETTINGS = window.IQS_THEME_SETTINGS || {};
  window.IQS_THEME_SETTINGS[isTopbar ? 'topbarCustomSeed' : 'sidebarCustomSeed'] = normalized;
  window.IQSThemePalette.apply(surface, normalized);
  updateThemeSetting(isTopbar ? 'topbar-color' : 'sidebar-color', 'custom');
  if (persist === false) return true;
  return true;
}

window.updateCustomThemeSeed = updateCustomThemeSeed;

// ============================================
// ✅ Sync UI Radio Button
// ============================================
function syncThemeSettingUI() {
  const config = {
    'data-bs-theme': document.documentElement.getAttribute('data-bs-theme')
      || safeStorage.get('layout-mode')
      || safeStorage.get('theme.layout')
      || 'light',
    'data-topbar-color': document.body.getAttribute('data-topbar-color')
      || safeStorage.get('topbar-color')
      || safeStorage.get('theme.topbar')
      || 'light',
    'data-menu-color': document.body.getAttribute('data-menu-color')
      || safeStorage.get('sidebar-color')
      || safeStorage.get('theme.menu')
      || 'light'
  };

  Object.entries(config).forEach(([key, val]) => {
    document.querySelectorAll(`input[name="${key}"]`).forEach(radio => {
      radio.checked = (radio.value === val);
    });
  });
}

// ============================================
// ✅ Init on DOM Ready
// ============================================
document.addEventListener('DOMContentLoaded', function () {
  applyThemeSetting();
  syncThemeSettingUI();

  // Manual Save Button (optional)
  const btn = document.getElementById('btnSaveTheme');
  if (btn) {
    btn.addEventListener('click', () => {
      saveThemeSettingToServer(success => {
        alert(success ? "Tema disimpan." : "Gagal simpan tema.");
      });
    });
  }
});
