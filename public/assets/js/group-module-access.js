/**
 * Module Access Management untuk kumpulan-pengguna.php
 * Handle modal akses modul dengan reorder functionality
 */

const ModuleAccess = {
  // DOM elements
  modalEl: null,
  subEl: null,
  loadEl: null,
  errEl: null,
  cntEl: null,
  searchEl: null,
  currentGroup: null,
  
  // Translations
  T: null,
  dragState: null,
  dragDelegationBound: false,
  
  init(translations) {
    this.T = translations;
    this.modalEl = document.getElementById('aksesModal');
    this.subEl = document.getElementById('aksesModalSub');
    this.loadEl = document.getElementById('aksesLoading');
    this.errEl = document.getElementById('aksesError');
    this.cntEl = document.getElementById('aksesContent');
    this.searchEl = document.getElementById('aksesSearch');
    
    // Auto-refresh menu bila modal ditutup jika ada perubahan order
    this.modalEl?.addEventListener('hidden.bs.modal', () => {
      if (!GroupState.isMenuOrderDirty()) return;
      GroupState.setMenuOrderDirty(false);
      this.syncSidebarAfterNavigationChange();
    });
    
    // Search functionality
    if (this.searchEl) {
      this.searchEl.addEventListener('input', () => {
        const q = (this.searchEl.value || '').toLowerCase().trim();
        const acc = this.cntEl.querySelector('#modulAccordion');
        if (!acc) return;
        acc.querySelectorAll('.accordion-item').forEach(it => {
          const txt = it.textContent.toLowerCase();
          it.style.display = (q === '' || txt.indexOf(q) !== -1) ? '' : 'none';
        });
      });
    }
    
    // Reorder buttons (global delegation)
    document.addEventListener('click', async (e) => {
      const btn = e.target.closest('#aksesContent .btn-move-up, #aksesContent .btn-move-down');
      if (!btn) return;
      e.preventDefault();
      await this.handleReorder(btn);
    }, { capture: true });

    document.addEventListener('click', async (e) => {
      const btn = e.target.closest('#aksesContent .btn-edit-module');
      if (!btn) return;
      e.preventDefault();
      e.stopPropagation();
      this.handleEditModule(btn);
    }, { capture: true });

    document.addEventListener('click', async (e) => {
      const btn = e.target.closest('#aksesContent .btn-delete-module');
      if (!btn) return;
      e.preventDefault();
      e.stopPropagation();
      await this.handleDeleteModule(btn);
    }, { capture: true });

    this.setupModuleDragDelegation();
    
    // View access button handler - delegated to main file
  },

  syncSidebarAfterNavigationChange() {
    if (window.AccessUiSync && typeof window.AccessUiSync.syncNavigationSilently === 'function') {
      return window.AccessUiSync.syncNavigationSilently({ redirectOnDenied: false }).catch(console.warn);
    }
    if (window.SidebarSync && typeof window.SidebarSync.refreshCurrentSidebar === 'function') {
      return window.SidebarSync.refreshCurrentSidebar().catch(console.warn);
    }
    if (window.MenuRefresh && typeof window.MenuRefresh.refreshMainMenu === 'function') {
      return window.MenuRefresh.refreshMainMenu().catch(console.warn);
    }
    return Promise.resolve(false);
  },
  
  showLoading() {
    GroupUtils.showLoader('moduleAccess', this.T.loading || this.T.loading_access || 'Loading...');
    this.loadEl?.classList.remove('d-none');
    this.errEl?.classList.add('d-none');
    this.cntEl?.classList.add('d-none');
    this.cntEl.innerHTML = '';
    if (this.searchEl) this.searchEl.value = '';
  },
  
  showError(msg) {
    GroupUtils.hideLoader('moduleAccess');
    GroupUtils.hideLoader('moduleAction');
    this.loadEl?.classList.add('d-none');
    if (this.errEl) {
      this.errEl.textContent = msg || this.T.error_unknown;
      this.errEl.classList.remove('d-none');
    }
  },
  
  showContent(html) {
    GroupUtils.hideLoader('moduleAccess');
    this.loadEl?.classList.add('d-none');
    this.errEl?.classList.add('d-none');
    if (this.cntEl) {
      this.cntEl.innerHTML = html;
      this.cntEl.classList.remove('d-none');
    }
  },
  
  renderAccess(data) {
    const modules = Array.isArray(data.modules) ? data.modules : [];
    const totals = data.totals || {};
    const modulCt = totals.modulCt ?? modules.length;
    const menuCt = totals.menuCt ?? (modules.reduce((n, m) => n + (m.menus ? m.menus.length : 0), 0));

    let html = '';
    html += '<div class="d-flex justify-content-between align-items-center mb-2">';
    html += '<div><span class="badge bg-primary-subtle text-primary modul-badge">' + modulCt + ' ' + GroupUtils.esc(this.T.label_module) + '</span> ';
    html += '<span class="badge bg-success-subtle text-success modul-badge">' + menuCt + ' ' + GroupUtils.esc(this.T.label_menu) + '</span></div>';
    html += '</div>';

    if (!modules.length) return html + '<div class="text-muted">' + GroupUtils.esc(this.T.no_records) + '</div>';

    html += '<div class="module-reorder-note"><i class="ri-draggable me-1"></i> ' + GroupUtils.esc(this.T.module_reorder_note || '') + '</div>';
    html += '<div class="accordion module-ordering" id="modulAccordion">';
    modules.forEach((m, i) => {
      const mid = 'mod' + i;
      const menus = Array.isArray(m.menus) ? m.menus : [];
      const moduleID = GroupUtils.esc(m.id || m.f_modulID || '');
      const moduleName = GroupUtils.esc(m.nama || m.modulName || this.T.modul_fallback);
      const moduleNameMs = GroupUtils.esc(m.modulNameMs || '');
      const moduleNameEn = GroupUtils.esc(m.modulNameEn || '');
      const moduleIcon = GroupUtils.esc(m.icon || 'ri-folder-fill');
      const moduleOrder = GroupUtils.esc(m.order || '');
      const canDelete = !!m.canDelete;
      html += '<div class="accordion-item" data-module-id="' + GroupUtils.esc(m.id || m.f_modulID || '') + '">';
      html += '<h2 class="accordion-header" id="h_' + mid + '">';
      html += '<button class="accordion-button collapsed acc-toggle" type="button" data-target="#c_' + mid + '" aria-expanded="false" aria-controls="c_' + mid + '">';
      html += '<span class="module-drag-handle" title="' + GroupUtils.esc(this.T.module_drag_label || '') + '" aria-label="' + GroupUtils.esc(this.T.module_drag_label || '') + '"><i class="ri-draggable"></i></span>';
      html += '<div class="module-title-row">';
      html += '<div class="module-title-main"><strong>' + moduleName + '</strong> <span class="badge bg-secondary-subtle text-secondary modul-badge ms-2">' + menus.length + ' ' + GroupUtils.esc(this.T.label_menu) + '</span></div>';
      html += '<span class="module-inline-actions">';
      html += '<span class="module-edit-chip btn-edit-module" role="button" tabindex="0" data-module-id="' + moduleID + '" data-module-name="' + moduleName + '" data-module-ms="' + moduleNameMs + '" data-module-en="' + moduleNameEn + '" data-module-icon="' + moduleIcon + '" data-module-order="' + moduleOrder + '" title="' + GroupUtils.esc(this.T.module_edit_label || this.T.edit || '') + '" aria-label="' + GroupUtils.esc(this.T.module_edit_label || this.T.edit || '') + '">';
      html += '<i class="ri-edit-2-line"></i><span class="module-edit-text">' + GroupUtils.esc(this.T.module_edit_label || this.T.edit || '') + '</span>';
      html += '</span>';
      if (canDelete) {
        html += '<span class="module-delete-chip btn-delete-module" role="button" tabindex="0" data-module-id="' + moduleID + '" data-module-name="' + moduleName + '" title="' + GroupUtils.esc(this.T.module_delete_label || this.T.delete || '') + '" aria-label="' + GroupUtils.esc(this.T.module_delete_label || this.T.delete || '') + '">';
        html += '<i class="ri-delete-bin-6-line"></i><span class="module-delete-text">' + GroupUtils.esc(this.T.module_delete_label || this.T.delete || '') + '</span>';
        html += '</span>';
      }
      html += '</span>';
      html += '</div>';
      html += '</button>';
      html += '</h2>';
      html += '<div id="c_' + mid + '" class="accordion-collapse collapse" aria-labelledby="h_' + mid + '">';
      html += '<div class="accordion-body" data-modul-id="' + moduleID + '">';
      if (!menus.length) {
        html += '<div class="text-muted small">' + GroupUtils.esc(this.T.no_records) + '</div>';
      } else {
        html += '<div class="row fw-semibold text-body-secondary mb-2"><div class="col">' + GroupUtils.esc(this.T.col_menu) + '</div><div class="col-auto">' + GroupUtils.esc(this.T.col_reorder) + '</div></div>';
        this.buildMenuOrderItems(menus).forEach(item => {
          if (item.type === 'subgroup') {
            const sg = item.subgroup || {};
            html += '<div class="menu-row subgroup-order-row" data-order-type="subgroup" data-subgroup-id="' + GroupUtils.esc(sg.id) + '">';
            html += '<div class="subgroup-order-title">';
            html += '<div class="fw-semibold"><i class="' + GroupUtils.esc(sg.icon || 'ri-folder-2-line') + ' me-1"></i>' + GroupUtils.esc(sg.name || ('Subgroup ' + sg.id)) + '</div>';
            html += '<div class="menu-path">' + GroupUtils.esc((sg.menus || []).length + ' ' + (this.T.label_menu || 'menu')) + '</div>';
            html += '</div>';
            html += '<div class="btn-group reorder-group" role="group" aria-label="' + GroupUtils.esc(this.T.reorder_label || this.T.col_reorder || '') + '">';
            html += '<button type="button" class="btn btn-outline-primary btn-sm btn-move-up" title="' + GroupUtils.esc(this.T.move_up) + '"><i class="ri-arrow-up-line"></i></button>';
            html += '<button type="button" class="btn btn-outline-primary btn-sm btn-move-down" title="' + GroupUtils.esc(this.T.move_down) + '"><i class="ri-arrow-down-line"></i></button>';
            html += '</div>';
            html += '</div>';
            (sg.menus || []).forEach(me => {
              html += this.renderMenuOrderRow(me, sg.id, true);
            });
            return;
          }

          html += this.renderMenuOrderRow(item.menu, 0, false);
        });
      }
      html += '</div>';
      html += '</div>';
      html += '</div>';
    });
    html += '</div>';
    return html;
  },

  buildMenuOrderItems(menus) {
    const direct = [];
    const subgroups = new Map();
    (Array.isArray(menus) ? menus : []).forEach((me, index) => {
      const subgroupID = parseInt(me.subgroupID ?? me.f_subgroupID ?? 0, 10) || 0;
      const menuOrder = parseInt(me.order ?? me.menuOrder ?? index + 1, 10) || index + 1;
      if (subgroupID <= 0) {
        direct.push({ type: 'menu', menu: me, order: menuOrder, sequence: index });
        return;
      }

      if (!subgroups.has(subgroupID)) {
        const subgroupOrder = parseInt(me.subgroupOrder ?? menuOrder, 10) || menuOrder;
        subgroups.set(subgroupID, {
          type: 'subgroup',
          subgroup: {
            id: subgroupID,
            name: String(me.subgroupName || ('Subgroup ' + subgroupID)),
            icon: String(me.subgroupIcon || 'ri-folder-2-line'),
            order: subgroupOrder,
            menus: []
          },
          order: subgroupOrder,
          sequence: index
        });
      }
      subgroups.get(subgroupID).subgroup.menus.push(me);
    });

    return direct.concat(Array.from(subgroups.values())).sort((a, b) => {
      const orderCompare = (a.order || 99999) - (b.order || 99999);
      return orderCompare !== 0 ? orderCompare : ((a.sequence || 0) - (b.sequence || 0));
    });
  },

  renderMenuOrderRow(me, parentSubgroupID, isChild) {
    if (!me) return '';
    const id = me.id ?? me.f_menuID;
    let html = '<div class="menu-row' + (isChild ? ' subgroup-menu-child' : '') + '" data-order-type="menu" data-menu-id="' + GroupUtils.esc(id) + '" data-parent-subgroup="' + GroupUtils.esc(parentSubgroupID || 0) + '">';
    html += '<div>';
    html += '<div>' + (isChild ? '<i class="ri-corner-down-right-line me-1 text-muted"></i>' : '') + GroupUtils.esc(me.nama || me.menuName || me.kod || '-') + '</div>';
    if (me.path || me.f_path) html += '<div class="menu-path">' + GroupUtils.esc(me.path || me.f_path) + '</div>';
    html += '</div>';
    html += '<div class="btn-group reorder-group" role="group" aria-label="' + GroupUtils.esc(this.T.reorder_label || this.T.col_reorder || '') + '">';
    html += '<button type="button" class="btn btn-outline-primary btn-sm btn-move-up" title="' + GroupUtils.esc(this.T.move_up) + '"><i class="ri-arrow-up-line"></i></button>';
    html += '<button type="button" class="btn btn-outline-primary btn-sm btn-move-down" title="' + GroupUtils.esc(this.T.move_down) + '"><i class="ri-arrow-down-line"></i></button>';
    html += '</div>';
    html += '</div>';
    return html;
  },

  handleEditModule(btn) {
    if (!btn || typeof window.openModuleFormModal !== 'function') return;
    window.openModuleFormModal({
      mode: 'edit',
      moduleID: parseInt(btn.getAttribute('data-module-id') || '0', 10),
      modulNameMs: btn.getAttribute('data-module-ms') || '',
      modulNameEn: btn.getAttribute('data-module-en') || '',
      icon: btn.getAttribute('data-module-icon') || 'ri-folder-fill',
      order: btn.getAttribute('data-module-order') || '',
    });
  },
  
  wireAccordionToggles(container) {
    const root = container || this.cntEl;
    if (!root) return;
    root.querySelectorAll('.accordion-collapse').forEach(el => {
      const M = window.bootstrap && bootstrap.Collapse ? bootstrap.Collapse : null;
      if (!M) return;
      M.getOrCreateInstance(el, { toggle: false });
    });
    root.querySelectorAll('.acc-toggle').forEach(btn => {
      btn.addEventListener('click', function (e) {
        if (e.target.closest('.module-drag-handle') || e.target.closest('.btn-delete-module')) return;
        e.preventDefault();
        const sel = this.getAttribute('data-target');
        const panel = root.querySelector(sel);
        if (!panel) return;
        const M = window.bootstrap && bootstrap.Collapse ? bootstrap.Collapse : null;
        if (!M) return;
        const inst = M.getOrCreateInstance(panel, { toggle: false });
        if (panel.classList.contains('show')) {
          inst.hide();
          this.classList.add('collapsed');
          this.setAttribute('aria-expanded', 'false');
        } else {
          inst.show();
          this.classList.remove('collapsed');
          this.setAttribute('aria-expanded', 'true');
        }
      });
    });
  },

  setupModuleDragDelegation() {
    if (this.dragDelegationBound) return;
    this.dragDelegationBound = true;

    document.addEventListener('pointerdown', (e) => {
      const handle = e.target.closest('#aksesContent .module-drag-handle');
      if (!handle || e.button !== 0) return;
      const item = handle.closest('.accordion-item[data-module-id]');
      const container = this.cntEl ? this.cntEl.querySelector('#modulAccordion') : null;
      if (!item || !container) return;

      this.dragState = {
        pointerId: e.pointerId,
        handle,
        item,
        container,
        moved: false,
        startOrder: this.getModuleOrder()
      };

      item.classList.add('is-dragging');
      container.classList.add('is-reordering');
      document.body.classList.add('module-dragging');
      handle.setPointerCapture?.(e.pointerId);
      e.preventDefault();
    });

    document.addEventListener('pointermove', (e) => {
      if (!this.dragState || this.dragState.pointerId !== e.pointerId) return;
      const { item, container } = this.dragState;
      if (!item || !container) return;

      const target = document.elementFromPoint(e.clientX, e.clientY)?.closest('#aksesContent .accordion-item[data-module-id]');
      if (!target || target === item || target.parentElement !== container) return;

      this.dragState.moved = true;
      this.clearModuleDropHints(container);
      const rect = target.getBoundingClientRect();
      const insertBefore = e.clientY < (rect.top + rect.height / 2);
      target.classList.add(insertBefore ? 'drop-before' : 'drop-after');
      if (insertBefore) {
        container.insertBefore(item, target);
      } else {
        container.insertBefore(item, target.nextElementSibling);
      }
      e.preventDefault();
    });

    const finishPointerDrag = async (e) => {
      if (!this.dragState || this.dragState.pointerId !== e.pointerId) return;

      const {
        container,
        handle,
        moved,
        startOrder,
      } = this.dragState;

      handle?.releasePointerCapture?.(e.pointerId);
      this.finishModuleDrag(container);

      const oldOrder = Array.isArray(startOrder) ? startOrder : [];
      const newOrder = this.getModuleOrder();
      this.dragState = null;

      if (!moved || !newOrder.length || JSON.stringify(newOrder) === JSON.stringify(oldOrder)) {
        return;
      }

      await this.saveModuleOrder(newOrder, oldOrder);
    };

    document.addEventListener('pointerup', finishPointerDrag);
    document.addEventListener('pointercancel', finishPointerDrag);
  },

  getModuleOrder() {
    const acc = this.cntEl ? this.cntEl.querySelector('#modulAccordion') : null;
    if (!acc) return [];
    return Array.from(acc.querySelectorAll('.accordion-item[data-module-id]'))
      .map(el => parseInt(el.getAttribute('data-module-id') || '0', 10))
      .filter(id => id > 0);
  },

  clearModuleDropHints(container) {
    container.querySelectorAll('.accordion-item.drop-before, .accordion-item.drop-after').forEach(el => {
      el.classList.remove('drop-before', 'drop-after');
    });
  },

  finishModuleDrag(container) {
    if (!container) {
      document.body.classList.remove('module-dragging');
      return;
    }
    this.clearModuleDropHints(container);
    container.querySelectorAll('.accordion-item.is-dragging').forEach(el => {
      el.classList.remove('is-dragging');
    });
    container.classList.remove('is-reordering');
    document.body.classList.remove('module-dragging');
  },

  restoreModuleOrder(order) {
    const acc = this.cntEl ? this.cntEl.querySelector('#modulAccordion') : null;
    if (!acc || !Array.isArray(order) || !order.length) return;
    const map = new Map();
    Array.from(acc.querySelectorAll('.accordion-item[data-module-id]')).forEach(item => {
      map.set(parseInt(item.getAttribute('data-module-id') || '0', 10), item);
    });
    order.forEach(id => {
      const item = map.get(id);
      if (item) acc.appendChild(item);
    });
  },

  async saveModuleOrder(newOrder, oldOrder) {
    const acc = this.cntEl ? this.cntEl.querySelector('#modulAccordion') : null;
    if (!acc) return;
    acc.classList.add('is-saving');
    try {
      const resp = await GroupUtils.fetchJSONSafe(GroupUtils.apiUrl('module-reorder.php'), {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': GroupUtils.getCSRF() },
        body: JSON.stringify({ orderedIDs: newOrder })
      });
      if (!resp || resp.error) {
        this.restoreModuleOrder(oldOrder);
        this.showError((resp && resp.message) || this.T.error_reorder);
        setTimeout(() => this.errEl?.classList.add('d-none'), 2500);
        return;
      }
      GroupState.setMenuOrderDirty(false);
      this.syncSidebarAfterNavigationChange();
    } catch (e) {
      this.restoreModuleOrder(oldOrder);
      this.showError(e.message || this.T.error_network);
      setTimeout(() => this.errEl?.classList.add('d-none'), 2500);
    } finally {
      acc.classList.remove('is-saving');
    }
  },

  async handleDeleteModule(btn) {
    if (!btn || btn.disabled) return;
    const moduleID = parseInt(btn.getAttribute('data-module-id') || '0', 10);
    const moduleName = btn.getAttribute('data-module-name') || ('Modul ' + moduleID);
    if (moduleID <= 0) return;

    let confirmed = false;
    if (window.Swal && typeof Swal.fire === 'function') {
      const ask = await GroupUtils.fireAlert({
        icon: 'warning',
        title: this.T.delete_module_confirm_title || '',
        text: (this.T.delete_module_confirm_text || '').replace('{name}', moduleName),
        showCancelButton: true,
        confirmButtonText: this.T.confirm_yes_delete || '',
        cancelButtonText: this.T.btn_cancel || ''
      });
      confirmed = !!(ask && ask.isConfirmed);
    } else {
      confirmed = window.confirm((this.T.delete_module_confirm_fallback || '').replace('{name}', moduleName));
    }
    if (!confirmed) return;

    btn.disabled = true;
    GroupUtils.showLoader('moduleAction', this.T.loading || this.T.delete_module_label || 'Loading...');
    try {
      const resp = await GroupUtils.fetchJSONSafe(GroupUtils.apiUrl('module-delete.php'), {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': GroupUtils.getCSRF() },
        body: JSON.stringify({ moduleID })
      });
      if (!resp || resp.error) {
        this.showError((resp && resp.message) || this.T.delete_module_fail);
        setTimeout(() => this.errEl?.classList.add('d-none'), 3000);
        return;
      }

      const successAlert = GroupUtils.fireAlert({
        icon: 'success',
        title: this.T.success_title || '',
        text: resp.message || this.T.delete_module_success,
        confirmButtonText: this.T.btn_ok || ''
      });
      await this.reloadCurrentAccess();
      if (window.MenuAccess && typeof window.MenuAccess.refreshVisibleGroupTableRows === 'function') {
        await window.MenuAccess.refreshVisibleGroupTableRows();
      }
      this.syncSidebarAfterNavigationChange();
      await successAlert;
    } catch (e) {
      this.showError(e.message || this.T.delete_module_network_fail);
      setTimeout(() => this.errEl?.classList.add('d-none'), 3000);
    } finally {
      btn.disabled = false;
      GroupUtils.hideLoader('moduleAction');
    }
  },
  
  refreshReorderButtons(bodyEl) {
    const refreshRows = (rows) => {
      rows.forEach((r, idx) => {
        const up = r.querySelector('.btn-move-up');
        const dn = r.querySelector('.btn-move-down');
        if (up) up.disabled = (idx === 0);
        if (dn) dn.disabled = (idx === rows.length - 1);
      });
    };

    refreshRows(this.getTopLevelOrderRows(bodyEl));

    const menuGroups = new Map();
    bodyEl.querySelectorAll('.menu-row[data-menu-id]').forEach((row) => {
      const key = row.getAttribute('data-parent-subgroup') || '0';
      if (key === '0') return;
      if (!menuGroups.has(key)) menuGroups.set(key, []);
      menuGroups.get(key).push(row);
    });
    menuGroups.forEach(refreshRows);
  },

  isTopLevelOrderRow(row) {
    if (!row || !row.classList || !row.classList.contains('menu-row')) return false;
    if (row.classList.contains('subgroup-order-row')) return true;
    return row.hasAttribute('data-menu-id') && (row.getAttribute('data-parent-subgroup') || '0') === '0';
  },

  getTopLevelOrderRows(bodyEl) {
    return Array.from(bodyEl.querySelectorAll('.menu-row')).filter(row => this.isTopLevelOrderRow(row));
  },

  getOrderItemBlock(row) {
    if (!row) return [];
    const block = [row];
    if (!row.classList.contains('subgroup-order-row')) {
      return block;
    }

    let cursor = row.nextElementSibling;
    while (cursor && cursor.classList.contains('menu-row') && !this.isTopLevelOrderRow(cursor)) {
      block.push(cursor);
      cursor = cursor.nextElementSibling;
    }
    return block;
  },

  moveOrderItemBlock(row, sibling, direction) {
    if (!row || !sibling || row.parentNode !== sibling.parentNode) return;
    const parent = row.parentNode;
    const rowBlock = this.getOrderItemBlock(row);
    const siblingBlock = this.getOrderItemBlock(sibling);
    const marker = document.createComment('module-order-marker');

    parent.insertBefore(marker, rowBlock[0]);
    rowBlock.forEach(node => parent.removeChild(node));

    if (direction === 'up') {
      parent.insertBefore(rowBlock[0], siblingBlock[0]);
      for (let i = 1; i < rowBlock.length; i++) {
        parent.insertBefore(rowBlock[i], siblingBlock[0]);
      }
    } else {
      const afterSibling = siblingBlock[siblingBlock.length - 1].nextElementSibling;
      rowBlock.forEach(node => parent.insertBefore(node, afterSibling));
    }

    return {
      revert: () => {
        rowBlock.forEach(node => {
          if (node.parentNode === parent) {
            parent.removeChild(node);
          }
        });
        rowBlock.forEach(node => parent.insertBefore(node, marker));
        marker.remove();
      },
      cleanup: () => {
        if (marker.parentNode) {
          marker.remove();
        }
      }
    };
  },

  findReorderSibling(row, direction) {
    if (!row) return null;
    const parentSubgroup = row.getAttribute('data-parent-subgroup') || '0';
    const topLevel = this.isTopLevelOrderRow(row);
    let sibling = direction === 'up' ? row.previousElementSibling : row.nextElementSibling;
    while (sibling) {
      if (topLevel && this.isTopLevelOrderRow(sibling)) return sibling;
      if (!topLevel && sibling.classList.contains('menu-row') && sibling.hasAttribute('data-menu-id') && (sibling.getAttribute('data-parent-subgroup') || '0') === parentSubgroup) {
        return sibling;
      }
      sibling = direction === 'up' ? sibling.previousElementSibling : sibling.nextElementSibling;
    }
    return null;
  },
  
  async handleReorder(btn) {
    const row = btn.closest('.menu-row');
    const body = btn.closest('.accordion-body');
    if (!row || !body) return;

    const direction = btn.classList.contains('btn-move-up') ? 'up' : 'down';
    const sibling = this.findReorderSibling(row, direction);
    if (!sibling) return;

    const topLevel = this.isTopLevelOrderRow(row);
    let blockMove = null;
    let revert = null;
    if (topLevel) {
      blockMove = this.moveOrderItemBlock(row, sibling, direction);
      revert = blockMove?.revert || null;
    } else if (direction === 'up') {
      row.parentNode.insertBefore(row, sibling);
      revert = () => sibling.parentNode.insertBefore(sibling, row);
    } else {
      sibling.parentNode.insertBefore(sibling, row);
      revert = () => row.parentNode.insertBefore(row, sibling);
    }

    const cleanup = () => {
      if (blockMove && typeof blockMove.cleanup === 'function') {
        blockMove.cleanup();
      }
    };

    if (topLevel) {
      await this.saveTopLevelOrderSwap(body, row, sibling, revert, cleanup);
      return;
    }

    await this.saveSwap(body, row, sibling, revert);
  },

  async saveTopLevelOrderSwap(bodyEl, rowA, rowB, revert, cleanup) {
    const modulID = bodyEl.getAttribute('data-modul-id');
    const payload = {
      modulID,
      aType: rowA.classList.contains('subgroup-order-row') ? 'subgroup' : 'menu',
      aID: rowA.classList.contains('subgroup-order-row') ? rowA.getAttribute('data-subgroup-id') : rowA.getAttribute('data-menu-id'),
      bType: rowB.classList.contains('subgroup-order-row') ? 'subgroup' : 'menu',
      bID: rowB.classList.contains('subgroup-order-row') ? rowB.getAttribute('data-subgroup-id') : rowB.getAttribute('data-menu-id')
    };

    rowA.classList.add('saving');
    rowB.classList.add('saving');
    try {
      const resp = await GroupUtils.fetchJSONSafe(GroupUtils.apiUrl('menu-order-item-swap.php'), {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': GroupUtils.getCSRF() },
        body: JSON.stringify(payload)
      });
      if (!resp || resp.error) {
        if (typeof revert === 'function') revert();
        this.showError((resp && resp.message) || this.T.error_reorder);
        setTimeout(() => this.errEl?.classList.add('d-none'), 2500);
        return;
      }
      if (typeof cleanup === 'function') cleanup();
      this.refreshReorderButtons(bodyEl);
      this.syncSidebarAfterNavigationChange();
    } catch (e) {
      if (typeof revert === 'function') revert();
      this.showError(e.message || this.T.error_network);
      setTimeout(() => this.errEl?.classList.add('d-none'), 2500);
    } finally {
      rowA.classList.remove('saving');
      rowB.classList.remove('saving');
    }
  },

  async handleLegacyReorder(btn) {
    const row = btn.closest('.menu-row');
    const body = btn.closest('.accordion-body');
    if (!row || !body) return;

    if (btn.classList.contains('btn-move-up')) {
      let prev = row.previousElementSibling;
      while (prev && !prev.classList.contains('menu-row')) prev = prev.previousElementSibling;
      if (!prev) return;
      row.parentNode.insertBefore(row, prev);
      await this.saveSwap(body, row, prev, () => {
        prev.parentNode.insertBefore(prev, row);
      });
    } else {
      let next = row.nextElementSibling;
      while (next && !next.classList.contains('menu-row')) next = next.nextElementSibling;
      if (!next) return;
      next.parentNode.insertBefore(next, row);
      await this.saveSwap(body, row, next, () => {
        row.parentNode.insertBefore(row, next);
      });
    }
  },

  async saveSubgroupSwap(bodyEl, rowA, rowB, revert) {
    const modulID = bodyEl.getAttribute('data-modul-id');
    const aID = rowA.getAttribute('data-subgroup-id');
    const bID = rowB.getAttribute('data-subgroup-id');
    rowA.classList.add('saving');
    rowB.classList.add('saving');
    try {
      const resp = await GroupUtils.fetchJSONSafe(GroupUtils.apiUrl('menu-subgroup-swap.php'), {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': GroupUtils.getCSRF() },
        body: JSON.stringify({ modulID, aID, bID })
      });
      if (!resp || resp.error) {
        if (typeof revert === 'function') revert();
        this.showError((resp && resp.message) || this.T.error_reorder);
        setTimeout(() => this.errEl?.classList.add('d-none'), 2500);
        return;
      }
      this.refreshReorderButtons(bodyEl);
      this.syncSidebarAfterNavigationChange();
    } catch (e) {
      if (typeof revert === 'function') revert();
      this.showError(e.message || this.T.error_network);
      setTimeout(() => this.errEl?.classList.add('d-none'), 2500);
    } finally {
      rowA.classList.remove('saving');
      rowB.classList.remove('saving');
    }
  },

  async saveSwap(bodyEl, rowA, rowB, revert) {
    const modulID = bodyEl.getAttribute('data-modul-id');
    const aID = rowA.getAttribute('data-menu-id');
    const bID = rowB.getAttribute('data-menu-id');
    rowA.classList.add('saving');
    rowB.classList.add('saving');
    try {
      const resp = await GroupUtils.fetchJSONSafe(GroupUtils.apiUrl('menu-swap.php'), {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': GroupUtils.getCSRF() },
        body: JSON.stringify({ modulID, aID, bID })
      });
      if (!resp || resp.error) {
        if (typeof revert === 'function') revert();
        this.showError((resp && resp.message) || this.T.error_reorder);
        setTimeout(() => this.errEl?.classList.add('d-none'), 2500);
        return;
      }
      this.refreshReorderButtons(bodyEl);
      GroupState.setMenuOrderDirty(true);
      this.syncSidebarAfterNavigationChange();
      GroupState.setMenuOrderDirty(false);
    } catch (e) {
      if (typeof revert === 'function') revert();
      this.showError(e.message || this.T.error_network);
      setTimeout(() => this.errEl?.classList.add('d-none'), 2500);
    } finally {
      rowA.classList.remove('saving');
      rowB.classList.remove('saving');
    }
  },

  async loadAccessData() {
    if (!this.currentGroup || !this.currentGroup.id) return;
    this.showLoading();
    const j = await GroupUtils.fetchJSONSafe(GroupUtils.apiUrl('group-access.php', { groupID: this.currentGroup.id }));
    if (!j || j.error) {
      this.showError((j && j.message) || this.T.error_load_access);
      return;
    }
    this.showContent(this.renderAccess(j));
    this.wireAccordionToggles(this.cntEl);
    this.cntEl.querySelectorAll('.accordion-body[data-modul-id]').forEach(body => {
      this.refreshReorderButtons(body);
    });
    this.dragState = null;
  },

  async reloadCurrentAccess() {
    try {
      await this.loadAccessData();
    } catch (e) {
      this.showError(e.message || this.T.error_network);
    }
  },

  async openAccess(btn) {
    const modal = GroupUtils.getModal(this.modalEl);
    if (!modal) return;
    const gid = btn.getAttribute('data-group-id');
    const gkod = btn.getAttribute('data-group-kod') || '';
    const gnam = btn.getAttribute('data-group-nama') || '';
    this.currentGroup = { id: gid, kod: gkod, nama: gnam };
    if (this.subEl) this.subEl.textContent = gkod + (gnam ? ' — ' + gnam : '');
    modal.show();
    try {
      await this.loadAccessData();
    } catch (e) {
      this.showError(e.message || this.T.error_network);
    }
  }
};

window.ModuleAccess = ModuleAccess;
