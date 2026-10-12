/**
 * Menu refresh utility untuk sidebar menu
 */

const NAV_SELECTORS = [
  '#sidebarMenu', '#appMenu', '.sidebar-menu', 'nav .menu', '.app-menu',
  '#sidebar', '.sidebar', '.sidebar-wrapper', 'aside .menu', 'ul.nav', 'ul.sidebar-menu'
];

const MenuRefresh = {
  async refreshMainMenu() {
    try {
      const j = await GroupUtils.fetchJSONSafe(GroupUtils.apiUrl('menu-list.php', { all: 1, active: 1 }));
      const menus = Array.isArray(j?.menus) ? j.menus : [];
      if (!menus.length) return;

      const orderIdx = new Map();
      menus.forEach((m, i) => {
        const path = GroupUtils.normalizePath(m.path || '');
        if (path) orderIdx.set('path:' + path, i);
        if (m.id != null) orderIdx.set('id:' + String(m.id), i);
      });

      const containers = this.getSidebarContainers();
      containers.forEach(cont => this.reorderMenuContainer(cont, orderIdx));
    } catch (e) {
      console.warn('refreshMainMenu error:', e);
    }
  },
  
  getSidebarContainers() {
    const out = new Set();
    NAV_SELECTORS.forEach(sel => {
      document.querySelectorAll(sel).forEach(el => out.add(el));
    });
    if (!out.size) {
      document.querySelectorAll('aside, nav').forEach(el => {
        if (el.querySelector('ul, .menu, .nav')) out.add(el);
      });
    }
    return Array.from(out);
  },
  
  reorderMenuContainer(cont, orderIdx) {
    let items = Array.from(cont.querySelectorAll(':scope [data-menu-id]'));
    const useById = items.length > 0;

    if (!useById) {
      const anchors = Array.from(cont.querySelectorAll(':scope a[href]'));
      items = anchors
        .filter(a => (a.getAttribute('href') || '').trim() && a.getAttribute('href') !== '#')
        .map(a => ({ anchor: a, el: (a.closest('li') || a) }));
    } else {
      items = items.map(el => ({ anchor: el.matches('a') ? el : (el.querySelector('a[href]') || el), el }));
    }

    const pairs = items.map(obj => {
      const el = obj.el;
      const a = obj.anchor || el.querySelector('a[href]');
      let idx = Number.MAX_SAFE_INTEGER;

      const rawId =
        el.getAttribute('data-menu-id') ||
        el.getAttribute('data-id') ||
        (a && a.getAttribute('data-menu-id')) ||
        '';
      if (rawId) {
        const key = 'id:' + String(rawId).trim();
        if (orderIdx.has(key)) idx = orderIdx.get(key);
      }
      if (idx === Number.MAX_SAFE_INTEGER && a) {
        const file = GroupUtils.normalizePath(a.getAttribute('href') || '');
        const key = 'path:' + file;
        if (orderIdx.has(key)) idx = orderIdx.get(key);
      }
      return { el, idx };
    });

    if (!pairs.some(p => p.idx !== Number.MAX_SAFE_INTEGER)) return;

    pairs.sort((x, y) => x.idx - y.idx);
    const frag = document.createDocumentFragment();
    const parent = cont.matches('ul, ol') ? cont : (cont.querySelector('ul, ol') || cont);
    pairs.forEach(p => frag.appendChild(p.el));
    parent.appendChild(frag);
  }
};

// Export untuk global access
window.MenuRefresh = MenuRefresh;
window.refreshMainMenu = () => MenuRefresh.refreshMainMenu();









