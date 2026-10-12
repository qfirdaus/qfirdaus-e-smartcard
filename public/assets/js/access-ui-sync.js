/**
 * Shared access UI sync contract.
 * Standardizes current-page inference, active-role updates, sidebar refresh,
 * and the redirect-vs-stay decision after access state changes.
 */

const AccessUiSync = {
  operationQueue: Promise.resolve(),
  activeOperations: 0,
  isExecutingExclusive: false,

  normalizeState(state = {}) {
    const ui = state && typeof state === 'object' && state.ui && typeof state.ui === 'object'
      ? state.ui
      : state;

    return {
      raw: state,
      ui,
      activeGroupId: ui.activeGroupId ?? state.activeGroupId ?? state.active_group_id ?? 0,
      roleName: ui.role?.name ?? ui.roleName ?? state.roleName ?? state.group_name ?? '',
      currentPagePath: ui.currentPage?.path ?? state.currentPagePath ?? state.current_page ?? '',
      currentPageAllowed: ui.currentPage?.allowed ?? state.currentPageAllowed ?? state.current_page_allowed,
      redirectUrl: ui.currentPage?.redirectUrl ?? state.redirectUrl ?? state.redirect_url ?? '',
      sidebarHtml: ui.sidebar?.html ?? state.html ?? null,
    };
  },

  setBusyState(isBusy) {
    const root = document.documentElement;
    if (!root) return;
    root.dataset.accessUiSyncBusy = isBusy ? 'true' : 'false';
  },

  isBusy() {
    return this.activeOperations > 0;
  },

  async runExclusive(task) {
    if (this.isExecutingExclusive) {
      return task();
    }

    const execute = async () => {
      this.isExecutingExclusive = true;
      this.activeOperations += 1;
      this.setBusyState(true);
      try {
        return await task();
      } finally {
        this.isExecutingExclusive = false;
        this.activeOperations = Math.max(0, this.activeOperations - 1);
        this.setBusyState(this.activeOperations > 0);
      }
    };

    const queued = this.operationQueue.then(execute, execute);
    this.operationQueue = queued.catch(() => undefined);
    return queued;
  },

  inferCurrentPagePath() {
    if (window.SidebarSync && typeof window.SidebarSync.normalizePath === 'function') {
      const normalized = window.SidebarSync.normalizePath(window.location.pathname || '');
      if (normalized) {
        return normalized;
      }
    }

    const raw = String(window.location.pathname || '').replace(/\\/g, '/').toLowerCase();
    const match = raw.match(/\bpages\/[^/?#]+$/);
    if (match) return match[0];
    const file = raw.split('/').filter(Boolean).pop() || '';
    return file ? ('pages/' + file) : '';
  },

  getActiveGroupId() {
    return parseInt(window.GroupPageRuntime?.activeGroupId || '0', 10) || 0;
  },

  isActiveGroup(groupId) {
    const activeGroupId = this.getActiveGroupId();
    const targetGroupId = parseInt(groupId || '0', 10) || 0;
    if (activeGroupId <= 0 || targetGroupId <= 0) {
      return false;
    }
    return activeGroupId === targetGroupId;
  },

  setActiveGroupId(groupId) {
    const nextGroupId = parseInt(groupId || '0', 10) || 0;
    if (window.GroupPageRuntime && Object.prototype.hasOwnProperty.call(window.GroupPageRuntime, 'activeGroupId')) {
      window.GroupPageRuntime.activeGroupId = nextGroupId;
    }
    return nextGroupId;
  },

  updateTopbarRoleLabel(roleName) {
    const label = String(roleName || '').trim();
    if (!label) return;
    const roleLabelEl = document.getElementById('topbarCurrentRoleLabel');
    if (roleLabelEl) {
      roleLabelEl.textContent = label;
    }
  },

  async refreshSidebar() {
    if (!window.SidebarSync || typeof window.SidebarSync.refreshCurrentSidebar !== 'function') {
      return false;
    }
    return window.SidebarSync.refreshCurrentSidebar();
  },

  async syncSidebarSilently(options = {}) {
    const settings = Object.assign({
      state: null,
      redirectOnDenied: false,
      fallbackMenuRefresh: true,
    }, options || {});

    try {
      if (settings.state) {
        return await this.applyAccessState(settings.state, {
          refreshSidebar: true,
          redirectOnDenied: !!settings.redirectOnDenied,
        });
      }
      return await this.runExclusive(() => this.refreshSidebar());
    } catch (err) {
      if (
        settings.fallbackMenuRefresh &&
        window.MenuRefresh &&
        typeof window.MenuRefresh.refreshMainMenu === 'function'
      ) {
        await window.MenuRefresh.refreshMainMenu().catch(console.warn);
      }
      throw err;
    }
  },

  async syncNavigationSilently(options = {}) {
    const settings = Object.assign({
      redirectOnDenied: false,
      fallbackMenuRefresh: true,
    }, options || {});

    if (window.AccessUiSync && typeof this.syncSidebarSilently === 'function') {
      return this.syncSidebarSilently(settings);
    }
    if (window.SidebarSync && typeof window.SidebarSync.refreshCurrentSidebar === 'function') {
      return window.SidebarSync.refreshCurrentSidebar();
    }
    if (
      settings.fallbackMenuRefresh &&
      window.MenuRefresh &&
      typeof window.MenuRefresh.refreshMainMenu === 'function'
    ) {
      return window.MenuRefresh.refreshMainMenu();
    }
    return false;
  },

  async syncSidebarForGroup(groupId, options = {}) {
    const activeGroupId = this.getActiveGroupId();
    const targetGroupId = parseInt(groupId || '0', 10) || 0;

    if (activeGroupId > 0 && targetGroupId > 0 && activeGroupId !== targetGroupId) {
      return false;
    }

    return this.syncSidebarSilently(options);
  },

  async applyAccessState(state = {}, options = {}) {
    return this.runExclusive(async () => {
      const settings = Object.assign({
        refreshSidebar: true,
        redirectOnDenied: true,
        onRedirect: null,
      }, options || {});

      const normalized = this.normalizeState(state);

      const activeGroupId = this.setActiveGroupId(normalized.activeGroupId);
      const roleName = String(normalized.roleName || '').trim();
      this.updateTopbarRoleLabel(roleName);

      const currentPageAllowed = normalized.currentPageAllowed;
      const redirectUrl = String(normalized.redirectUrl || '').trim();

      if (currentPageAllowed === false && settings.redirectOnDenied) {
        if (typeof settings.onRedirect === 'function') {
          settings.onRedirect({ activeGroupId, roleName, redirectUrl });
        } else if (redirectUrl) {
          window.location.href = redirectUrl;
        }
        return { redirected: true, activeGroupId, roleName };
      }

      if (settings.refreshSidebar) {
        if (normalized.sidebarHtml && window.SidebarSync && typeof window.SidebarSync.applySidebarState === 'function') {
          window.SidebarSync.applySidebarState(normalized.ui);
        } else {
          await this.refreshSidebar();
        }
      }

      return { redirected: false, activeGroupId, roleName };
    });
  }
};

window.AccessUiSync = AccessUiSync;
