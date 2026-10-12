/**
 * Sidebar refresh helper untuk kumpulan-pengguna.php
 * Rebuild sidebar tanpa full page reload selepas access role aktif berubah.
 */

const SidebarSync = {
  activeRefreshPromise: null,

  normalizePath(input) {
    const raw = String(input || '').replace(/\\/g, '/').toLowerCase();
    const match = raw.match(/\b(pages|ajax|actions)\/[^?#]+$/);
    if (match) {
      return match[0].replace(/^\//, '');
    }
    const trimmed = raw.replace(/^\/+/, '');
    return trimmed;
  },

  buildPathVariants(input, defaultToPages = false) {
    const normalized = this.normalizePath(input);
    if (!normalized) return [];

    const seeds = [normalized];
    if (defaultToPages && !/^(pages|ajax|actions)\//.test(normalized)) {
      seeds.push('pages/' + normalized.replace(/^\/+/, ''));
    }

    const variants = [];
    seeds.forEach((seed) => {
      const base = String(seed || '').replace(/\/+$/, '');
      if (!base) return;

      variants.push(base);
      if (base.endsWith('/index.php')) {
        variants.push(base.slice(0, -10));
      } else if (!base.endsWith('.php')) {
        variants.push(base + '/index.php');
      }
    });

    return [...new Set(variants.filter(Boolean))];
  },

  buildAjaxUrl(file, params) {
    const baseUrl = String(window.BASE_URL || '').replace(/\/+$/, '');
    const url = new URL((baseUrl || window.location.origin) + '/ajax/' + file, window.location.origin);
    if (params && typeof params === 'object') {
      Object.entries(params).forEach(([key, value]) => {
        url.searchParams.set(key, String(value));
      });
    }
    url.searchParams.set('_', Date.now());
    return url.toString();
  },

  async fetchJson(url, options) {
    const requestOptions = Object.assign({
      credentials: 'same-origin',
      headers: {
        'Accept': 'application/json',
        'X-Requested-With': 'XMLHttpRequest'
      }
    }, options || {});
    requestOptions.headers = Object.assign({
      'Accept': 'application/json',
      'X-Requested-With': 'XMLHttpRequest'
    }, (options && options.headers) || {});

    const response = await fetch(url, requestOptions);
    const text = await response.text();
    let payload = null;
    try {
      payload = JSON.parse(text);
    } catch (e) {
      throw new Error('Server did not return JSON.');
    }
    if (!response.ok) {
      throw new Error((payload && payload.message) || 'Request failed.');
    }
    return payload;
  },

  async refreshCurrentSidebar() {
    if (this.activeRefreshPromise) {
      return this.activeRefreshPromise;
    }

    this.activeRefreshPromise = this._refreshCurrentSidebar();
    try {
      return await this.activeRefreshPromise;
    } finally {
      this.activeRefreshPromise = null;
    }
  },

  async _refreshCurrentSidebar() {
    const currentSidebar = document.getElementById('leftside-menu');
    if (!currentSidebar) return false;

    const currentFile = this.normalizePath(window.location.pathname || '');
    const payload = await this.fetchJson(this.buildAjaxUrl('sidebar-fragment.php', { currentFile }));
    if (!payload || payload.error) {
      throw new Error((payload && payload.message) || 'Failed to refresh sidebar.');
    }

    return this.applySidebarState(payload);
  },

  applySidebarState(state = {}) {
    const ui = state && typeof state === 'object' && state.ui && typeof state.ui === 'object'
      ? state.ui
      : state;
    const sidebarHtml = ui?.sidebar?.html ?? state.html ?? null;
    if (!sidebarHtml) {
      throw new Error('Sidebar fragment is invalid.');
    }

    const currentSidebar = document.getElementById('leftside-menu');
    if (!currentSidebar) return false;

    const doc = new DOMParser().parseFromString(sidebarHtml, 'text/html');
    const nextSidebar = doc.getElementById('leftside-menu');
    if (!nextSidebar) {
      throw new Error('Sidebar fragment is invalid.');
    }

    currentSidebar.replaceWith(nextSidebar);
    this.initSimpleBar(nextSidebar);
    this.initLeftSidebar(nextSidebar);

    if (window.GroupPageRuntime && Object.prototype.hasOwnProperty.call(window.GroupPageRuntime, 'activeGroupId')) {
      window.GroupPageRuntime.activeGroupId = parseInt(ui.activeGroupId || state.activeGroupId || state.active_group_id || window.GroupPageRuntime.activeGroupId || '0', 10) || 0;
    }

    return true;
  },

  initSimpleBar(sidebarEl) {
    if (!sidebarEl || typeof window.SimpleBar !== 'function') return;
    const container = sidebarEl.querySelector('[data-simplebar]');
    if (!container) return;

    try {
      if (container.SimpleBar && typeof container.SimpleBar.unMount === 'function') {
        container.SimpleBar.unMount();
      }
    } catch (e) {
      console.warn('SimpleBar cleanup failed:', e);
    }

    try {
      new window.SimpleBar(container);
    } catch (e) {
      console.warn('SimpleBar init failed:', e);
    }
  },

  initLeftSidebar(sidebarEl) {
    if (!sidebarEl) return;
    this.bindSidebarCollapseFix(sidebarEl);
    this.activateCurrentLink(sidebarEl);
  },

  bindSidebarCollapseFix(sidebarEl) {
    if (!sidebarEl || sidebarEl.dataset.sidebarCollapseBound === '1') {
      return;
    }
    sidebarEl.dataset.sidebarCollapseBound = '1';

    const syncToggleState = (toggleEl, panelEl, isExpanded) => {
      if (!toggleEl || !panelEl) return;
      toggleEl.setAttribute('aria-expanded', isExpanded ? 'true' : 'false');
      toggleEl.classList.toggle('collapsed', !isExpanded);
      panelEl.classList.toggle('show', !!isExpanded);
    };

    sidebarEl.addEventListener('click', (event) => {
      const toggleEl = event.target.closest(".side-nav li [data-sidebar-toggle='true']");
      if (!toggleEl || !sidebarEl.contains(toggleEl)) {
        return;
      }

      const targetSelector = toggleEl.getAttribute('data-sidebar-target') || '';
      if (!targetSelector || !targetSelector.startsWith('#')) {
        return;
      }

      const panelEl = sidebarEl.querySelector(targetSelector);
      if (!panelEl) {
        return;
      }

      event.preventDefault();
      event.stopPropagation();

      const isExpanded = panelEl.classList.contains('show');
      if (isExpanded) {
        syncToggleState(toggleEl, panelEl, false);
        return;
      }

      const toggleLevel = toggleEl.getAttribute('data-sidebar-level') || 'module';
      const scopeSelector = toggleLevel === 'subgroup'
        ? '.side-nav-second-level'
        : '.side-nav';
      const containingList = toggleEl.closest(scopeSelector) || sidebarEl;
      const openPanels = containingList.querySelectorAll(':scope > li > .collapse.show');

      openPanels.forEach((openPanelEl) => {
        if (openPanelEl === panelEl) return;
        const openToggleEl = sidebarEl.querySelector(".side-nav li [data-sidebar-toggle='true'][data-sidebar-target='#" + openPanelEl.id + "']");
        syncToggleState(openToggleEl, openPanelEl, false);
      });

      syncToggleState(toggleEl, panelEl, true);
    }, true);
  },

  activateCurrentLink(sidebarEl) {
    const pagePathVariants = this.buildPathVariants(window.location.pathname || '', false);
    sidebarEl.querySelectorAll('.side-nav a').forEach((link) => {
      let linkPathVariants = [];
      try {
        const url = new URL(link.href, window.location.origin);
        linkPathVariants = this.buildPathVariants(url.pathname || '', false);
      } catch (err) {
        return;
      }

      const isMatch = linkPathVariants.some((variant) => pagePathVariants.includes(variant));
      if (!isMatch) return;

      link.classList.add('active');
      link.parentElement?.classList.add('menuitem-active');

      const firstCollapse = link.closest('.collapse');
      if (firstCollapse) {
        firstCollapse.classList.add('show');
        firstCollapse.parentElement?.classList.add('menuitem-active');
      }

      const nestedCollapse = firstCollapse?.parentElement?.closest('.collapse');
      if (nestedCollapse) {
        nestedCollapse.classList.add('show');
        nestedCollapse.parentElement?.classList.add('menuitem-active');
      }
    });

    window.setTimeout(() => {
      const activatedItem = sidebarEl.querySelector('li.menuitem-active .active');
      const simplebarContent = sidebarEl.querySelector('.simplebar-content-wrapper');
      if (!activatedItem || !simplebarContent) return;
      const offset = activatedItem.offsetTop - 300;
      if (offset > 100) {
        simplebarContent.scrollTop = offset;
      }
    }, 120);
  }
};

window.SidebarSync = SidebarSync;
