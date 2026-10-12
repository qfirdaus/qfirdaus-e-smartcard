/**
 * Menu Access Management untuk kumpulan-pengguna.php
 * Handle modal akses menu dengan DataTable dan editor
 */

const MenuAccess = {
  // DOM elements
  modalEl: null,
  subEl: null,
  loadEl: null,
  errEl: null,
  cntEl: null,
  editModalEl: null,
  editErrorEl: null,
  subgroupModalEl: null,
  subgroupRows: [],
  
  // Translations
  T: null,
  restoreParentMenuModal: false,
  restoreParentAfterSubgroupModal: false,
  pendingParentRestoreAfterSave: false,
  currentRows: [],

  formatText(template, replacements = {}) {
    return String(template || '').replace(/\{(\w+)\}/g, (_, key) => String(replacements[key] ?? ''));
  },

  cleanupModalArtifacts() {
    try {
      document.body.classList.remove('modal-open');
      document.body.style.removeProperty('padding-right');
      document.body.style.removeProperty('overflow');
      document.querySelectorAll('.modal-backdrop').forEach(el => el.remove());
    } catch (e) { /* silent */ }
  },

  waitForModalShown(modalEl, modalInstance) {
    return new Promise((resolve) => {
      if (!modalEl || !modalInstance) {
        resolve();
        return;
      }
      if (modalEl.classList.contains('show')) {
        requestAnimationFrame(() => resolve());
        return;
      }
      const done = () => requestAnimationFrame(() => resolve());
      modalEl.addEventListener('shown.bs.modal', done, { once: true });
      modalInstance.show();
    });
  },

  adjustMenuDataTable() {
    try {
      const dt = GroupState.getMenuDataTable();
      if (dt && typeof dt.columns === 'function') {
        dt.columns.adjust().draw(false);
      }
    } catch (e) { /* silent */ }
  },

  attachMultiSelectToggle(selectEl) {
    if (!selectEl || selectEl.dataset.multiToggleBound === '1') return;
    selectEl.dataset.multiToggleBound = '1';
    selectEl.addEventListener('mousedown', function(e) {
      const opt = e.target && e.target.tagName === 'OPTION' ? e.target : null;
      if (!opt) return;
      e.preventDefault();
      opt.selected = !opt.selected;
      // Fire change so dependent menu list refreshes
      selectEl.dispatchEvent(new Event('change', { bubbles: true }));
    });
  },
  
  // Helper untuk query edit modal
  $ME(sel) {
    return this.editModalEl ? this.editModalEl.querySelector(sel) : null;
  },
  
  init(translations) {
    this.T = translations;
    this.modalEl = document.getElementById('aksesMenuModal');
    this.subEl = document.getElementById('aksesMenuSub');
    this.loadEl = document.getElementById('menuLoading');
    this.errEl = document.getElementById('menuError');
    this.cntEl = document.getElementById('menuContent');
    this.editModalEl = document.getElementById('menuEditModal');
    this.editErrorEl = document.getElementById('menuEditError');
    this.subgroupModalEl = document.getElementById('menuSubgroupModal');
    this.editModalEl?.addEventListener('hidden.bs.modal', () => {
      this.cleanupModalArtifacts();
      if (this.pendingParentRestoreAfterSave) {
        return;
      }
      if (this.restoreParentMenuModal && this.modalEl) {
        const parentModal = GroupUtils.getModal(this.modalEl);
        if (parentModal) {
          parentModal.show();
        }
      }
      this.restoreParentMenuModal = false;
    });
    this.subgroupModalEl?.addEventListener('hidden.bs.modal', () => {
      this.cleanupModalArtifacts();
      if (this.restoreParentAfterSubgroupModal && this.modalEl) {
        const parentModal = GroupUtils.getModal(this.modalEl);
        if (parentModal) {
          parentModal.show();
        }
      }
      this.restoreParentAfterSubgroupModal = false;
    });

    const colorPicker = document.getElementById('gc_color_picker');
    const colorInput = document.getElementById('gc_color');
    const categoryInput = document.getElementById('gc_categoryUser');
    const codeInput = document.getElementById('gc_groupKod');
    const nameInput = document.getElementById('gc_groupName');
    if (colorPicker && colorInput) {
      const syncColor = (v) => { colorInput.value = (v || '').trim(); };
      syncColor(colorPicker.value || '#50a4c1');
      colorPicker.addEventListener('input', () => syncColor(colorPicker.value));
    }

    const syncGroupPreview = () => {
      const previewCode = document.getElementById('gc_previewCode');
      const previewName = document.getElementById('gc_previewName');
      const previewCategory = document.getElementById('gc_previewCategory');
      const colorValue = (colorInput?.value || colorPicker?.value || '#50a4c1').trim() || '#50a4c1';
      const codeValue = (codeInput?.value || '').trim() || 'ADM-XX';
      const nameValue = (nameInput?.value || '').trim() || 'Nama Kumpulan';
      const categoryValue = (categoryInput?.value || 'STAF').trim() || 'STAF';

      if (previewCode) previewCode.textContent = codeValue;
      if (previewName) {
        previewName.textContent = nameValue;
        previewName.style.backgroundColor = colorValue;
      }
      if (previewCategory) {
        previewCategory.textContent = categoryValue;
        previewCategory.setAttribute('data-category', categoryValue);
      }
    };
    this.syncGroupPreview = syncGroupPreview;
    colorPicker?.addEventListener('input', syncGroupPreview);
    colorInput?.addEventListener('input', syncGroupPreview);
    categoryInput?.addEventListener('change', syncGroupPreview);
    codeInput?.addEventListener('input', syncGroupPreview);
    nameInput?.addEventListener('input', syncGroupPreview);
    syncGroupPreview();

    this.attachMultiSelectToggle(document.getElementById('gc_moduls'));
    this.attachMultiSelectToggle(document.getElementById('gc_menus'));
    
    // View menu button handler - removed to avoid conflict with main file handler
    // Handler is now in main file (kumpulan-pengguna.php)
    
    // Save button handler
    document.getElementById('menuEditSaveBtn')?.addEventListener('click', () => {
      this.handleSave();
    });
    document.getElementById('em_modulID')?.addEventListener('change', (e) => {
      this.populateSubgroups(e.target.value, 0);
    });
    document.getElementById('menuSubgroupSaveBtn')?.addEventListener('click', () => {
      this.saveSubgroup();
    });
    document.getElementById('menuSubgroupResetBtn')?.addEventListener('click', () => {
      this.resetSubgroupForm();
    });
    document.getElementById('sg_modulID')?.addEventListener('change', () => {
      this.loadSubgroupsForManager();
    });
    document.getElementById('sg_iconPicker')?.addEventListener('click', (e) => {
      const btn = e.target.closest('.subgroup-icon-option[data-icon]');
      if (!btn) return;
      this.setSubgroupIcon(btn.getAttribute('data-icon') || 'ri-folder-2-line');
    });
    document.querySelector('#menuSubgroupTable tbody')?.addEventListener('click', (e) => {
      const tr = e.target.closest('tr[data-index]');
      if (!tr) return;
      const row = this.subgroupRows[parseInt(tr.getAttribute('data-index') || '-1', 10)];
      if (!row) return;
      if (e.target.closest('.sg-edit')) this.resetSubgroupForm(row);
      if (e.target.closest('.sg-delete-blocked')) this.showSubgroupInUseAlert(row);
      if (e.target.closest('.sg-delete')) this.deleteSubgroup(row);
    });
    // Group create modal save handler (global page)
    document.getElementById('groupCreateSaveBtn')?.addEventListener('click', async (e) => {
      e.preventDefault();
      const actionButton = e.currentTarget;
      const errEl = document.getElementById('groupCreateError');
      if (errEl) errEl.classList.add('d-none');
      const groupID = parseInt(document.getElementById('gc_groupID')?.value || '0', 10) || 0;
      const payload = {
        csrf_token: GroupUtils.getCSRF(),
        groupID,
        groupKod: (document.getElementById('gc_groupKod')?.value || '').trim(),
        groupName: (document.getElementById('gc_groupName')?.value || '').trim(),
        categoryUser: (document.getElementById('gc_categoryUser')?.value || '').trim(),
        priority: parseInt(document.getElementById('gc_priority')?.value || '0', 10) || 0,
        mod: parseInt(document.getElementById('gc_mod')?.value || '0', 10) || 0,
        color: (document.getElementById('gc_color')?.value || '').trim(),
        modulAccess: Array.from(document.getElementById('gc_moduls')?.selectedOptions || []).map(o => o.value).filter(Boolean),
        menuAccess: Array.from(document.getElementById('gc_menus')?.selectedOptions || []).map(o => o.value).filter(Boolean)
      };
      if (!payload.groupKod || !payload.groupName || !payload.categoryUser) {
        if (errEl) { errEl.textContent = this.T.err_group_code_name_required || 'Sila isi Kod & Nama Kumpulan.'; errEl.classList.remove('d-none'); }
        return;
      }

      // Export to global so page scripts can call populateCreateModal()
      try { window.MenuAccess = MenuAccess; } catch (e) { /* ignore */ }
      GroupUtils.showLoader('menuAction', this.T.loading || this.T.btn_save || 'Loading...');
      GroupUtils.setButtonBusy(actionButton, true, this.T.saving || 'Saving...');
      try {
        const resp = await fetch(GroupUtils.apiUrl('group-create.php'), {
          method: 'POST',
          noLoader: true,
          headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': GroupUtils.getCSRF(), 'X-No-Loader': '1' },
          body: JSON.stringify(payload)
        });
        const j = await resp.json();
        if (!j || j.error) throw new Error(j && j.message ? j.message : (this.T.err_save_menu || 'Gagal simpan'));

        const modalEl = document.getElementById('groupCreateModal');
        const modal = bootstrap.Modal.getInstance(modalEl) || new bootstrap.Modal(modalEl);
        const savedGroup = Object.assign({}, j.group || {}, {
          groupID: j.group?.id ?? payload.groupID,
          groupKod: payload.groupKod,
          groupName: payload.groupName,
          categoryUser: payload.categoryUser,
          color: payload.color,
          priority: payload.priority,
          mod: payload.mod,
          modulAccess: payload.modulAccess,
          menuAccess: payload.menuAccess
        });

        modal.hide();
        this.resetGroupCreateForm();
        const successAlert = GroupUtils.fireAlert({
          icon: 'success',
          title: this.T.done || 'Berjaya',
          text: groupID > 0
            ? (this.T.group_update_success || 'Kumpulan berjaya dikemaskini.')
            : (this.T.group_create_success || 'Kumpulan berjaya ditambah.'),
          confirmButtonText: this.T.btn_ok || 'OK'
        });
        await this.refreshGroupTableRow(savedGroup.groupID || savedGroup.id || payload.groupID, savedGroup);
        await this.syncSidebarForGroup(savedGroup.groupID || savedGroup.id || 0);
        await successAlert;

      } catch (err) {
        if (errEl) { errEl.textContent = err.message || this.T.error_network || 'Ralat rangkaian'; errEl.classList.remove('d-none'); }
      } finally {
        GroupUtils.setButtonBusy(actionButton, false);
        GroupUtils.hideLoader('menuAction');
      }
    });

    // Edit group metadata (reuse create modal)
    document.addEventListener('click', async (e) => {
      const btn = e.target.closest('.btn-edit-group-meta');
      if (!btn) return;
      e.preventDefault();

      GroupUtils.setButtonBusy(document.getElementById('groupCreateSaveBtn'), false);

      const modalEl = document.getElementById('groupCreateModal');
      const modal = bootstrap.Modal.getInstance(modalEl) || new bootstrap.Modal(modalEl);
      if (modalEl) {
        modalEl.classList.remove('modal-add-accent');
        modalEl.classList.add('modal-child-accent');
      }
      const titleEl = document.getElementById('groupCreateTitle');
      if (titleEl) titleEl.innerHTML = '<i class="ri-pencil-line"></i> <span>' + String(this.T.modal_group_edit_title || '') + '</span>';
      const saveTxt = document.getElementById('groupCreateSaveBtnText');
      if (saveTxt) saveTxt.textContent = String(this.T.btn_update || '');

      const gid = btn.getAttribute('data-group-id') || '';
      const kod = btn.getAttribute('data-group-kod') || '';
      const nama = btn.getAttribute('data-group-nama') || '';
      const categoryUser = btn.getAttribute('data-group-category') || 'STAF';
      const prio = btn.getAttribute('data-group-priority') || '0';
      const mod = btn.getAttribute('data-group-mod') || '0';
      const color = btn.getAttribute('data-group-color') || '#50a4c1';

      if (document.getElementById('gc_groupID')) document.getElementById('gc_groupID').value = gid;
      if (document.getElementById('gc_groupKod')) document.getElementById('gc_groupKod').value = kod;
      if (document.getElementById('gc_groupName')) document.getElementById('gc_groupName').value = nama;
      if (document.getElementById('gc_categoryUser')) document.getElementById('gc_categoryUser').value = categoryUser;
      if (document.getElementById('gc_priority')) document.getElementById('gc_priority').value = prio;
      if (document.getElementById('gc_mod')) document.getElementById('gc_mod').value = mod;
      if (document.getElementById('gc_color_picker')) document.getElementById('gc_color_picker').value = color;
      if (document.getElementById('gc_color')) document.getElementById('gc_color').value = color;
      if (typeof this.syncGroupPreview === 'function') this.syncGroupPreview();

      modal.show();

      try {
        if (window.MenuAccess && typeof window.MenuAccess.populateCreateModal === 'function') {
          await window.MenuAccess.populateCreateModal();
        }
        // Prefill existing module/menu selections for edit mode.
        const j = await GroupUtils.fetchJSONSafe(GroupUtils.apiUrl('group-perms-get.php', { groupID: gid }));
        const toList = (v) => Array.isArray(v) ? v.map(String) : String(v || '').split(',').map(s => s.trim()).filter(Boolean);
        const modulIDs = toList(j.modulIDs ?? j.f_modulAccess ?? []);
        const menuIDs = toList(j.menuIDs ?? j.f_menuAccess ?? []);
        const selMod = document.getElementById('gc_moduls');
        if (selMod) {
          Array.from(selMod.options).forEach(o => { o.selected = modulIDs.includes(String(o.value)); });
          await this.populateMenusForModules(menuIDs);
        }
      } catch (_) {}
    });

    // Delete group handler
    document.addEventListener('click', async (e) => {
      const btn = e.target.closest('.btn-delete-group');
      if (!btn) return;
      e.preventDefault();

      const gid = parseInt(btn.getAttribute('data-group-id') || '0', 10) || 0;
      const gnam = btn.getAttribute('data-group-nama') || this.T.btn_group_label || 'Kumpulan';
      if (gid <= 0) return;

      const ask = await GroupUtils.fireAlert({
        icon: 'warning',
        title: this.T.confirm_title || 'Pengesahan',
        text: (this.T.confirm_delete_group_text || 'Padam kumpulan "{name}"?').replace('{name}', gnam),
        showCancelButton: true,
        confirmButtonText: this.T.confirm_yes_delete || 'Ya, Padam',
        cancelButtonText: this.T.confirm_cancel || 'Batal',
      });
      if (!ask.isConfirmed) return;

      GroupUtils.showLoader('menuAction', this.T.loading || this.T.confirm_yes_delete || 'Loading...');
      try {
        const resp = await fetch(GroupUtils.apiUrl('group-delete.php'), {
          method: 'POST',
          noLoader: true,
          headers: {
            'Content-Type': 'application/json',
            'X-CSRF-Token': GroupUtils.getCSRF(),
            'Accept': 'application/json',
            'X-No-Loader': '1'
          },
          body: JSON.stringify({ groupID: gid })
        });
        const j = await resp.json();
        if (!resp.ok || !j || j.error) {
          const msg = (j && j.message) ? j.message : (this.T.delete_group_fail || 'Gagal memadam kumpulan.');
          await GroupUtils.fireAlert({
            icon: 'error',
            title: this.T.not_allowed_title || 'Tidak Dibenarkan',
            text: msg,
          });
          return;
        }

        this.removeGroupTableRow(gid);
        const successAlert = GroupUtils.fireAlert({
          icon: 'success',
          title: this.T.done || 'Berjaya',
          text: this.T.delete_group_success || 'Kumpulan berjaya dipadam.',
          confirmButtonText: this.T.btn_ok || 'OK'
        });
        await this.syncSidebarForGroup(gid);
        await successAlert;
      } catch (err) {
        await GroupUtils.fireAlert({
          icon: 'error',
          title: this.T.not_allowed_title || 'Tidak Dibenarkan',
          text: err && err.message ? err.message : (this.T.delete_group_network_fail || 'Ralat rangkaian semasa memadam kumpulan.'),
        });
      } finally {
        GroupUtils.hideLoader('menuAction');
      }
    });
    // Ensure modal UI matches current mode when shown
    if (this.editModalEl) {
      this.editModalEl.addEventListener('show.bs.modal', () => {
        const mode = this.editModalEl.dataset.mode || 'edit';
        this.updateEditModalUI(mode);
      });
    }
  },

  getGroupTableApi() {
    if (!window.jQuery || !jQuery.fn || !jQuery.fn.dataTable || !jQuery.fn.dataTable.isDataTable('#groupTable')) {
      return null;
    }
    return jQuery('#groupTable').DataTable();
  },

  escapeAttr(value) {
    return String(value ?? '')
      .replace(/&/g, '&amp;')
      .replace(/"/g, '&quot;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;');
  },

  canManageGroups() {
    return !!window.GroupPageRuntime?.canManageGroups;
  },

  findGroupTableRow(groupId) {
    const table = this.getGroupTableApi();
    const targetGroupId = parseInt(groupId || '0', 10) || 0;
    if (!table || targetGroupId <= 0) return null;

    let matchedRow = null;
    table.rows().every(function () {
      const node = this.node();
      let rowGroupId = parseInt(node?.getAttribute?.('data-group-id') || '0', 10) || 0;

      if (rowGroupId <= 0) {
        const data = this.data();
        if (Array.isArray(data)) {
          const actionHtml = String((data[5] || '') + (data[6] || '') + (data[7] || ''));
          const match = actionHtml.match(/data-group-id=["']?(\d+)/);
          rowGroupId = match ? (parseInt(match[1], 10) || 0) : 0;
        }
      }

      if (rowGroupId === targetGroupId) {
        matchedRow = this;
        return false;
      }
      return true;
    });

    return matchedRow;
  },

  normalizeGroupRecord(group = {}) {
    const modulAccess = Array.isArray(group.modulAccess)
      ? group.modulAccess
      : String(group.modulAccess ?? group.f_modulAccess ?? '')
          .split(',')
          .map((value) => String(value).trim())
          .filter(Boolean);
    const menuAccess = Array.isArray(group.menuAccess)
      ? group.menuAccess
      : String(group.menuAccess ?? group.f_menuAccess ?? '')
          .split(',')
          .map((value) => String(value).trim())
          .filter(Boolean);

    return {
      id: parseInt(group.id ?? group.groupID ?? group.f_groupID ?? '0', 10) || 0,
      kod: String(group.kod ?? group.groupKod ?? group.f_groupKod ?? '').trim(),
      nama: String(group.nama ?? group.groupName ?? group.f_groupName ?? '').trim(),
      categoryUser: String(group.categoryUser ?? group.f_categoryUser ?? 'STAF').trim() || 'STAF',
      color: String(group.color ?? group.f_color ?? '').trim(),
      priority: String(group.priority ?? group.f_priority ?? '0').trim() || '0',
      mod: String(group.mod ?? group.f_mod ?? '0').trim() || '0',
      userCount: parseInt(group.userCount ?? group.user_count ?? '0', 10) || 0,
      canDelete: typeof (group.canDelete ?? group.can_delete) === 'boolean'
        ? Boolean(group.canDelete ?? group.can_delete)
        : null,
      modulAccess,
      menuAccess
    };
  },

  extractGroupRecordFromRow(groupId) {
    const targetGroupId = parseInt(groupId || '0', 10) || 0;
    if (targetGroupId <= 0) return null;

    const row = this.findGroupTableRow(targetGroupId);
    if (!row) return null;

    const node = row.node();
    const data = row.data();
    const getText = (index) => {
      if (Array.isArray(data) && data[index] !== undefined) {
        const tmp = document.createElement('div');
        tmp.innerHTML = String(data[index] ?? '');
        return (tmp.textContent || '').trim();
      }
      const cell = node?.children?.[index];
      return (cell?.textContent || '').trim();
    };
    const editBtn = node?.querySelector?.('.btn-edit-group-meta');
    const groupPermBtn = node?.querySelector?.('.view-group-perms');
    const colorBar = node?.querySelector?.('.group-color-bar');
    const categoryChip = node?.querySelector?.('.group-table-category-chip');

    return {
      groupID: targetGroupId,
      groupKod: editBtn?.getAttribute('data-group-kod') || groupPermBtn?.getAttribute('data-group-kod') || getText(1),
      groupName: editBtn?.getAttribute('data-group-nama') || groupPermBtn?.getAttribute('data-group-nama') || getText(2),
      categoryUser: editBtn?.getAttribute('data-group-category') || categoryChip?.getAttribute('data-category') || getText(3) || 'STAF',
      color: editBtn?.getAttribute('data-group-color') || colorBar?.getAttribute('title') || '',
      priority: editBtn?.getAttribute('data-group-priority') || '0',
      mod: editBtn?.getAttribute('data-group-mod') || '0',
      canDelete: !!node?.querySelector?.('.btn-delete-group')
    };
  },

  mergeGroupRecord(group = {}) {
    const record = this.normalizeGroupRecord(group);
    const existing = this.extractGroupRecordFromRow(record.id);
    return Object.assign({}, existing || {}, group, {
      groupID: record.id || group.groupID || group.id,
      groupKod: group.groupKod ?? group.kod ?? existing?.groupKod,
      groupName: group.groupName ?? group.nama ?? existing?.groupName,
      categoryUser: group.categoryUser ?? group.f_categoryUser ?? existing?.categoryUser,
      color: group.color ?? group.f_color ?? existing?.color,
      priority: group.priority ?? group.f_priority ?? existing?.priority,
      mod: group.mod ?? group.f_mod ?? existing?.mod,
      userCount: group.userCount ?? group.user_count ?? existing?.userCount,
      canDelete: typeof (group.canDelete ?? group.can_delete) === 'boolean'
        ? Boolean(group.canDelete ?? group.can_delete)
        : existing?.canDelete,
      modulAccess: group.modulAccess ?? group.f_modulAccess ?? record.modulAccess,
      menuAccess: group.menuAccess ?? group.f_menuAccess ?? record.menuAccess
    });
  },

  buildGroupRowData(group = {}, index = 1) {
    const record = this.normalizeGroupRecord(group);
    const hasAccess = record.modulAccess.length > 0 || record.menuAccess.length > 0;
    const canOpenModuleAccess = record.id > 0;
    const hasCompleteIdentity = record.id > 0 && (record.kod !== '' || record.nama !== '');
    const canDeleteGroup = typeof record.canDelete === 'boolean'
      ? record.canDelete
      : (hasCompleteIdentity && !hasAccess);
    const barColor = record.color || '#94a3b8';
    const esc = (value) => GroupUtils.esc(String(value ?? ''));
    const escAttr = (value) => this.escapeAttr(value);
    const manageButtons = this.canManageGroups()
      ? [
          '<button type="button" class="btn btn-sm btn-outline-warning icon-btn btn-edit-group-meta" ' +
            'data-group-id="' + escAttr(record.id) + '" ' +
            'data-group-kod="' + escAttr(record.kod) + '" ' +
            'data-group-nama="' + escAttr(record.nama) + '" ' +
            'data-group-category="' + escAttr(record.categoryUser) + '" ' +
            'data-group-priority="' + escAttr(record.priority) + '" ' +
            'data-group-mod="' + escAttr(record.mod) + '" ' +
            'data-group-color="' + escAttr(record.color) + '" ' +
            'title="' + escAttr(this.T.userGroup_edit_group || 'Kemaskini kumpulan') + '">' +
            '<i class="ri-pencil-line"></i></button>'
        ]
      : [];

    if (this.canManageGroups() && canDeleteGroup) {
      manageButtons.push(
        '<button type="button" class="btn btn-sm btn-outline-danger icon-btn btn-delete-group" ' +
          'data-group-id="' + escAttr(record.id) + '" ' +
          'data-group-kod="' + escAttr(record.kod) + '" ' +
          'data-group-nama="' + escAttr(record.nama) + '" ' +
          'title="' + escAttr(this.T.userGroup_delete_group || 'Padam kumpulan') + '">' +
          '<i class="ri-delete-bin-line"></i></button>'
      );
    }

    return [
      String(index),
      esc(record.kod),
      esc(record.nama),
      '<span class="group-create-preview-chip group-category-chip group-table-category-chip" data-category="' + escAttr(record.categoryUser) + '">' +
        esc(record.categoryUser) +
      '</span>',
      '<div class="group-color-cell"><span class="group-color-bar" style="background-color: ' + escAttr(barColor) + ';" title="' + escAttr(barColor) + '"></span></div>',
      '<div class="group-access-actions d-inline-flex align-items-center gap-2"><button type="button" class="btn btn-sm btn-outline-secondary icon-btn view-group-perms" ' +
        'data-group-id="' + escAttr(record.id) + '" ' +
        'data-group-kod="' + escAttr(record.kod) + '" ' +
        'data-group-nama="' + escAttr(record.nama) + '" ' +
        'title="' + escAttr(this.T.userGroup_col_group_access || 'Akses kumpulan') + '">' +
        '<i class="ri-user-settings-line"></i></button>' +
        manageButtons.join('') + '</div>',
      canOpenModuleAccess
        ? '<button type="button" class="btn btn-sm btn-outline-primary icon-btn view-access" ' +
            'data-group-id="' + escAttr(record.id) + '" ' +
            'data-group-kod="' + escAttr(record.kod) + '" ' +
            'data-group-nama="' + escAttr(record.nama) + '" ' +
            'title="' + escAttr(this.T.userGroup_col_module_access || 'Akses modul') + '">' +
            '<i class="ri-links-line"></i></button>'
        : '<span class="text-muted"><i class="ri-link-unlink-m"></i></span>',
      '<button type="button" class="btn btn-sm btn-outline-success icon-btn view-menu" ' +
        'data-group-id="' + escAttr(record.id) + '" ' +
        'data-group-kod="' + escAttr(record.kod) + '" ' +
        'data-group-nama="' + escAttr(record.nama) + '" ' +
        'title="' + escAttr(this.T.userGroup_col_menu_access || 'Akses menu') + '">' +
        '<i class="ri-menu-2-line"></i></button>'
    ];
  },

  reindexGroupTable() {
    const table = this.getGroupTableApi();
    if (!table) return;
    table.rows({ order: 'applied', search: 'applied' }).every(function (rowIdx) {
      const rowData = this.data();
      if (Array.isArray(rowData)) {
        rowData[0] = String(rowIdx + 1);
        this.data(rowData, false);
      }
    });
    table.draw(false);
    if (typeof table.columns === 'function') {
      table.columns.adjust();
    }
  },

  upsertGroupTableRow(group = {}) {
    const table = this.getGroupTableApi();
    const record = this.normalizeGroupRecord(this.mergeGroupRecord(group));
    if (!table || record.id <= 0) return false;
    const hasCompleteIdentity = record.kod !== '' || record.nama !== '';

    const existingRow = this.findGroupTableRow(record.id);
    if (existingRow) {
      existingRow.data(this.buildGroupRowData(record));
      const node = existingRow.node();
      if (node) node.setAttribute('data-group-id', String(record.id));
    } else {
      if (!hasCompleteIdentity) return false;
      const node = table.row.add(this.buildGroupRowData(record)).draw(false).node();
      if (node) node.setAttribute('data-group-id', String(record.id));
    }
    this.reindexGroupTable();
    return true;
  },

  async fetchGroupRecord(groupId) {
    const targetGroupId = parseInt(groupId || '0', 10) || 0;
    if (targetGroupId <= 0) return null;
    const j = await GroupUtils.fetchJSONSafe(GroupUtils.apiUrl('group-list.php', {
      groupID: targetGroupId,
      scope: 'all'
    }));
    if (!j || j.error) {
      throw new Error((j && j.message) || this.T.error_load || this.T.error_network || 'Gagal memuat kumpulan.');
    }
    return j.group || (Array.isArray(j.groups) ? j.groups[0] : null);
  },

  async refreshGroupTableRow(groupId, fallbackGroup = {}) {
    const targetGroupId = parseInt(groupId || fallbackGroup.groupID || fallbackGroup.id || '0', 10) || 0;
    if (targetGroupId <= 0) return false;
    try {
      const group = await this.fetchGroupRecord(targetGroupId);
      if (group) {
        return this.upsertGroupTableRow(group);
      }
    } catch (err) {
      console.warn('Group row refresh failed, using local fallback:', err);
    }

    const existing = this.extractGroupRecordFromRow(targetGroupId) || {};
    const mergedFallback = Object.assign({}, existing, fallbackGroup, { groupID: targetGroupId });
    const hasIdentity = String(mergedFallback.groupKod || mergedFallback.kod || '').trim() !== ''
      || String(mergedFallback.groupName || mergedFallback.nama || '').trim() !== '';

    if (!hasIdentity) {
      return false;
    }

    return this.upsertGroupTableRow(mergedFallback);
  },

  async refreshVisibleGroupTableRows() {
    const table = this.getGroupTableApi();
    if (!table) return false;

    const ids = [];
    table.rows().nodes().toArray().forEach((node) => {
      const id = parseInt(node?.getAttribute?.('data-group-id') || '0', 10) || 0;
      if (id > 0 && !ids.includes(id)) ids.push(id);
    });
    for (const id of ids) {
      await this.refreshGroupTableRow(id);
    }
    return ids.length > 0;
  },

  removeGroupTableRow(groupId) {
    const targetGroupId = parseInt(groupId || '0', 10) || 0;
    if (targetGroupId <= 0) return false;
    const row = this.findGroupTableRow(targetGroupId);
    if (!row) return false;
    row.remove().draw(false);
    this.reindexGroupTable();
    return true;
  },

  resetGroupCreateForm() {
    if (document.getElementById('gc_groupID')) document.getElementById('gc_groupID').value = '';
    if (document.getElementById('gc_groupKod')) document.getElementById('gc_groupKod').value = '';
    if (document.getElementById('gc_groupName')) document.getElementById('gc_groupName').value = '';
    if (document.getElementById('gc_categoryUser')) document.getElementById('gc_categoryUser').value = 'STAF';
    if (document.getElementById('gc_priority')) document.getElementById('gc_priority').value = '0';
    if (document.getElementById('gc_mod')) document.getElementById('gc_mod').value = '0';
    if (document.getElementById('gc_color_picker')) document.getElementById('gc_color_picker').value = '#50a4c1';
    if (document.getElementById('gc_color')) document.getElementById('gc_color').value = '#50a4c1';
    try {
      const modSel = document.getElementById('gc_moduls');
      const menuSel = document.getElementById('gc_menus');
      if (modSel) Array.from(modSel.options).forEach((opt) => { opt.selected = false; });
      if (menuSel) Array.from(menuSel.options).forEach((opt) => { opt.selected = false; });
    } catch (_) { /* ignore */ }
    if (typeof this.syncGroupPreview === 'function') {
      this.syncGroupPreview();
    }
  },

  async syncSidebarForGroup(groupId) {
    if (window.AccessUiSync && typeof window.AccessUiSync.syncSidebarForGroup === 'function') {
      return window.AccessUiSync.syncSidebarForGroup(groupId, { redirectOnDenied: false }).catch(console.warn);
    }
    if (window.SidebarSync && typeof window.SidebarSync.refreshCurrentSidebar === 'function') {
      return window.SidebarSync.refreshCurrentSidebar().catch(console.warn);
    }
    return Promise.resolve(false);
  },

  async syncSidebarAfterNavigationChange() {
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

  updateEditModalUI(mode) {
    try {
      if (this.editModalEl) {
        this.editModalEl.classList.toggle('modal-add-accent', mode === 'create');
        this.editModalEl.classList.toggle('modal-child-accent', mode !== 'create');
      }
      const titleEl = document.getElementById('menuEditTitleText');
      const saveBtnEl = document.getElementById('menuEditSaveBtn');
      const buttonLabel = mode === 'create'
        ? String(this.T.btn_save || '')
        : String(this.T.btn_update || '');
      if (titleEl) {
        if (mode === 'create') titleEl.textContent = titleEl.dataset.titleCreate || String(this.T.modal_add_menu_title || '');
        else titleEl.textContent = titleEl.dataset.titleEdit || String(this.T.modal_edit_menu_title || '');
      }
      if (saveBtnEl) {
        saveBtnEl.innerHTML = '<i class="ri-save-3-line me-1"></i> <span id="menuEditSaveBtnText">' + String(buttonLabel) + '</span>';
        saveBtnEl.setAttribute('aria-label', buttonLabel);
        saveBtnEl.setAttribute('title', buttonLabel);
      }
    } catch (e) { /* silent */ }
  },
  
  showLoading() {
    GroupUtils.showLoader('menuAccess', this.T.loading || this.T.loading_menu || 'Loading...');
    this.loadEl?.classList.remove('d-none');
    this.errEl?.classList.add('d-none');
    this.cntEl?.classList.add('d-none');
    this.cntEl.innerHTML = '';
  },
  
  showError(msg) {
    GroupUtils.hideLoader('menuAccess');
    GroupUtils.hideLoader('menuAction');
    this.loadEl?.classList.add('d-none');
    if (this.errEl) {
      this.errEl.textContent = msg || this.T.error_unknown;
      this.errEl.classList.remove('d-none');
    }
  },
  
  showContent(html) {
    GroupUtils.hideLoader('menuAccess');
    this.loadEl?.classList.add('d-none');
    this.errEl?.classList.add('d-none');
    if (this.cntEl) {
      if (html != null) {
        // Use safe assign to avoid executing injected scripts or on* handlers.
        try {
          if (window.DOMPurify && typeof DOMPurify.sanitize === 'function') {
            this.cntEl.innerHTML = DOMPurify.sanitize(html);
          } else {
            const doc = new DOMParser().parseFromString('<div>' + html + '</div>', 'text/html');
            doc.querySelectorAll('script').forEach(s => s.remove());
            doc.querySelectorAll('*').forEach(n => {
              Array.from(n.attributes).forEach(a => {
                if (/^on/i.test(a.name)) n.removeAttribute(a.name);
                if ((a.name === 'src' || a.name === 'href') && /^javascript:/i.test(a.value)) n.removeAttribute(a.name);
              });
            });
            this.cntEl.innerHTML = doc.body.firstChild ? doc.body.firstChild.innerHTML : '';
          }
        } catch (e) {
          this.cntEl.innerHTML = html;
        }
      }
      this.cntEl.classList.remove('d-none');
    }
  },
  
  // Parse menu helper
  parseMenu(me) {
    const id = parseInt(me.id ?? me.f_menuID, 10);
    const modulID = parseInt(me.modulID ?? me.f_modulID, 10);
    const hasFlag = Object.prototype.hasOwnProperty.call(me, 'flag') ||
                    Object.prototype.hasOwnProperty.call(me, 'f_flag') ||
                    Object.prototype.hasOwnProperty.call(me, 'active') ||
                    Object.prototype.hasOwnProperty.call(me, 'is_active');
    const rawFlag = hasFlag ? (me.flag ?? me.f_flag ?? me.active ?? me.is_active) : 1;
    const __asOn = (v) => v === 1 || v === '1' || v === true || v === 'true' || v === 'on';
    
    return {
      id,
      modulID,
      name: String(me.nama || me.menuName || me.kod || '-'),
      path: String(me.path || me.f_path || ''),
      subgroupID: parseInt(me.subgroupID ?? me.f_subgroupID ?? 0, 10) || 0,
      subgroupName: String(me.subgroupName || me.subgroup_name || ''),
      domain: String(me.domain || me.f_domain || 'SHARED'),
      showStaffOnly: parseInt(me.show_staff_only ?? me.f_show_staff_only ?? 1, 10) === 1 ? 1 : 0,
      flag: __asOn(rawFlag) ? 1 : 0
    };
  },

  async loadGroupPermissionState(groupID) {
    const gid = parseInt(groupID || '0', 10);
    if (!gid) {
      return {
        modulIDs: [],
        menuIDs: [],
        menusByModul: {},
        modulesMap: {}
      };
    }

    const j = await GroupUtils.fetchJSONSafe(GroupUtils.apiUrl('group-perms-get.php', { groupID: gid }));
    if (!j || j.error) {
      throw new Error((j && j.message) || this.T.error_load_access || this.T.error_load || 'Gagal memuat akses kumpulan.');
    }

    const toNumArray = (v) => {
      if (Array.isArray(v)) return v.map(x => parseInt(x, 10)).filter(Number.isFinite);
      if (typeof v === 'string') return v.split(',').map(s => parseInt(String(s).trim(), 10)).filter(Number.isFinite);
      if (typeof v === 'number') return [v];
      return [];
    };

    const modulIDs = toNumArray(j.modulIDs ?? j.f_modulAccess ?? j.modul_access);
    const menuIDs = toNumArray(j.menuIDs ?? j.f_menuAccess ?? j.menu_access);
    const menusByModul = j.menusByModul || {};
    const modulesMap = Object.fromEntries(
      (Array.isArray(j.modules) ? j.modules : []).map((m) => {
        const id = parseInt(m.id ?? m.f_modulID, 10);
        const name = String(m.nama || m.modulName || ('Modul ' + (m.id || m.f_modulID)));
        return [id, name];
      }).filter(([id]) => Number.isFinite(id))
    );

    GroupState.setGroupID(gid);
    GroupState.setModulIDs(modulIDs);
    GroupState.setMenuIDs(menuIDs);
    GroupState.setMenusByModul(menusByModul);
    GroupState.setModulesRaw(
      Object.keys(modulesMap).map((id) => ({ id: parseInt(id, 10), nama: modulesMap[id] }))
    );

    return { modulIDs, menuIDs, menusByModul, modulesMap };
  },

  getCurrentAccessibleMenuIds() {
    return this.currentRows
      .map((row) => parseInt(row.menuID, 10))
      .filter(Number.isFinite);
  },

  isGroupMenuEnabled(menuID) {
    const currentMenuIDs = GroupState.getMenuIDs().map((id) => parseInt(id, 10)).filter(Number.isFinite);
    if (!currentMenuIDs.length) {
      return true;
    }
    return currentMenuIDs.includes(parseInt(menuID, 10));
  },

  async saveGroupMenuAccess() {
    const groupID = parseInt(GroupState.getMenuGroupID() || '0', 10) || 0;
    if (!groupID) {
      throw new Error(this.T.group_invalid_id || this.T.group_not_found || 'Kumpulan tidak sah.');
    }

    const payload = {
      csrf_token: GroupUtils.getCSRF(),
      groupID,
      modulIDs: GroupState.getModulIDs(),
      menuIDs: GroupState.getMenuIDs()
    };

    const j = await GroupUtils.fetchJSONSafe(GroupUtils.apiUrl('group-perms-save.php'), {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': GroupUtils.getCSRF() },
      body: JSON.stringify(payload)
    });

    if (!j || j.error) {
      throw new Error((j && j.message) || this.T.error_save || 'Gagal menyimpan akses menu kumpulan.');
    }

    const savedGroup = Object.assign({}, j.group || {}, {
      groupID,
      groupKod: j.group?.kod || GroupState.getLastMenuBtn()?.getAttribute('data-group-kod') || '',
      groupName: j.group?.nama || GroupState.getLastMenuBtn()?.getAttribute('data-group-nama') || '',
      modulAccess: j.group?.modulAccess ?? GroupState.getModulIDs(),
      menuAccess: j.group?.menuAccess ?? GroupState.getMenuIDs(),
    });
    if (!this.upsertGroupTableRow(savedGroup)) {
      await this.refreshGroupTableRow(groupID, savedGroup);
    }
    await this.syncSidebarForGroup(groupID);

    return j;
  },
  
  // Fetch all menus
  async fetchAllMenusStrict() {
    try {
      const j = await GroupUtils.fetchJSONSafe(GroupUtils.apiUrl('menu-list.php', { all: 1 }));
      const raw = Array.isArray(j?.menus) ? j.menus : (Array.isArray(j?.data) ? j.data : []);
      if (raw.length) {
        return raw.map(m => this.parseMenu(m)).filter(x => Number.isInteger(x.id) && Number.isInteger(x.modulID));
      }
    } catch (_) { /* fallback */ }
    
    // Fallback: loop setiap modul
    let modulIDs = [];
    try {
      const ml = await GroupUtils.fetchJSONSafe(GroupUtils.apiUrl('modul-list.php'));
      const arr = Array.isArray(ml?.moduls) ? ml.moduls : (Array.isArray(ml) ? ml : []);
      modulIDs = arr.map(m => parseInt(m.id ?? m.f_modulID, 10)).filter(Number.isInteger);
    } catch (_) {}
    
    const all = [];
    for (const mid of modulIDs) {
      try {
        const j = await GroupUtils.fetchJSONSafe(GroupUtils.apiUrl('menu-list.php', { modulID: mid }));
        const raw = Array.isArray(j?.menus) ? j.menus : (Array.isArray(j?.data) ? j.data : j);
        (raw || []).forEach(r => all.push(this.parseMenu(r)));
      } catch (_) {}
    }
    return all.filter(x => Number.isInteger(x.id) && Number.isInteger(x.modulID));
  },

  // Populate module/menu selects in the group create modal
  async populateCreateModal() {
    try {
      // populate modules
      const errEl = document.getElementById('groupCreateError');
      if (errEl) errEl.classList.add('d-none');
      const selMod = document.getElementById('gc_moduls');
      if (selMod) {
        selMod.innerHTML = '<option value="">' + GroupUtils.esc(this.T.loading_short || 'Memuat…') + '</option>';
      }

      let ml;
      const url = GroupUtils.apiUrl('modul-list.php');
      // small timeout wrapper so a hung request surfaces to user
      const fetchWithTimeout = (p, ms = 7000) => Promise.race([
        p,
        new Promise((_, rej) => setTimeout(() => rej(new Error('timeout')), ms))
      ]);
      try {
        ml = await fetchWithTimeout(GroupUtils.fetchJSONSafe(url), 7000);
      } catch (e) {
        console.error('modul-list fetch failed', e, url);
        if (errEl) { errEl.textContent = this.formatText(this.T.load_modules_fail || 'Gagal memuat modul dari: {url} — {error}', { url, error: e.message || (this.T.error || 'Ralat') }); errEl.classList.remove('d-none'); }
        if (selMod) selMod.innerHTML = '';
        return;
      }

      const arr = Array.isArray(ml?.moduls) ? ml.moduls : (Array.isArray(ml) ? ml : []);
      if (!arr.length) {
        if (errEl) { errEl.textContent = this.T.no_modules_found || 'Tiada modul ditemui.'; errEl.classList.remove('d-none'); }
        if (selMod) selMod.innerHTML = '';
        return;
      }

      if (selMod) {
        selMod.innerHTML = '';
        arr.forEach(function(m) {
          var idVal = (m.id !== undefined && m.id !== null) ? m.id : (m.f_modulID || '');
          var id = String(idVal);
          var name = String(m.nama || m.modulName || id);
          var opt = document.createElement('option');
          opt.value = id; opt.textContent = name; selMod.appendChild(opt);
        });
        // attach change listener to populate menus on selection
        try {
          if (typeof selMod.removeEventListener === 'function') selMod.removeEventListener('change', MenuAccess.populateMenusForModules);
        } catch (e) { /* ignore */ }
        selMod.addEventListener('change', function() { MenuAccess.populateMenusForModules().catch(function(){/*ignore*/}); });
        this.attachMultiSelectToggle(selMod);
      }
      // initial populate menus (none selected → empty)
      await MenuAccess.populateMenusForModules();
    } catch (e) {
      // ignore populate errors
      console.warn('populateCreateModal error', e);
    }
  },

  async populateMenusForModules(preselectedMenuIds = []) {
    try {
      const selMod = document.getElementById('gc_moduls');
      const selMenu = document.getElementById('gc_menus');
      if (!selMenu) return;
      selMenu.innerHTML = '';
      this.attachMultiSelectToggle(selMenu);
      const selected = Array.from(selMod?.selectedOptions || []).map(o => o.value).filter(Boolean);
      if (!selected.length) return;
      const seen = new Set();
      for (const mid of selected) {
        try {
          const j = await GroupUtils.fetchJSONSafe(GroupUtils.apiUrl('menu-list.php', { modulID: mid }));
          const raw = Array.isArray(j?.menus) ? j.menus : (Array.isArray(j?.data) ? j.data : (Array.isArray(j) ? j : []));
          for (const m of raw) {
            const id = String(m.id ?? m.f_menuID ?? m.f_menuID);
            if (!id || seen.has(id)) continue;
            seen.add(id);
            const name = String(m.nama || m.menuName || m.f_path || ('Menu ' + id));
            const opt = document.createElement('option'); opt.value = id; opt.textContent = name; selMenu.appendChild(opt);
          }
        } catch (e) { /* ignore per-module errors */ }
      }
      if (preselectedMenuIds && preselectedMenuIds.length) {
        const selectedSet = new Set(preselectedMenuIds.map(String));
        Array.from(selMenu.options).forEach(o => { o.selected = selectedSet.has(String(o.value)); });
      }
    } catch (e) {
      console.warn('populateMenusForModules error', e);
    }
  },
  
  buildMenuTable(rows) {
    this.currentRows = Array.isArray(rows) ? rows.slice() : [];
    const domainBadge = (domain) => {
      const safeDomain = String(domain || 'SHARED').toUpperCase();
      return '<span class="badge rounded-pill menu-domain-badge" data-domain="' + GroupUtils.esc(safeDomain) + '">' + GroupUtils.esc(safeDomain) + '</span>';
    };
    const staffOnlyBadge = (showStaffOnly) => {
      const isShown = parseInt(showStaffOnly ?? 1, 10) === 1;
      const label = isShown
        ? GroupUtils.esc(this.T.menu_staff_only_show_full || this.T.menu_staff_only_show || '')
        : GroupUtils.esc(this.T.menu_staff_only_hide_full || this.T.menu_staff_only_hide || '');
      const cls = isShown
        ? 'bg-success-subtle text-success-emphasis border-success-subtle'
        : 'bg-danger-subtle text-danger-emphasis border-danger-subtle';
      return '<span class="badge rounded-pill border ' + cls + '">' + label + '</span>';
    };
    const subgroupBadge = (name) => {
      const safeName = String(name || '').trim();
      if (!safeName) return '<span class="text-muted small">-</span>';
      return '<span class="badge rounded-pill border bg-secondary-subtle text-secondary-emphasis border-secondary-subtle"><i class="ri-folder-2-line me-1"></i>' + GroupUtils.esc(safeName) + '</span>';
    };
    const html =
      '<div class="d-flex justify-content-end gap-2 mb-3">' +
      '<button type="button" class="btn btn-sm btn-outline-primary" id="menuSubgroupManageBtn"><i class="ri-folder-settings-line me-1"></i>' + GroupUtils.esc(this.T.subgroup_manage || 'Subgroup') + '</button>' +
      '<button type="button" class="btn btn-sm btn-primary" id="menuAddInsideBtn"><i class="ri-add-line me-1"></i>' + GroupUtils.esc(this.T.btn_add_menu || this.T.btn_menu_label || 'Menu') + '</button>' +
      '</div>' +
      '<table class="table table-striped table-bordered align-middle w-100" id="menuDT">' +
      '<thead class="table-light"><tr>' +
      '<th style="width:17%" class="text-start">' + GroupUtils.esc(this.T.field_modul || '') + '</th>' +
      '<th style="width:24%" class="text-start">' + GroupUtils.esc(this.T.col_menu || '') + '</th>' +
      '<th style="width:15%" class="text-start">' + GroupUtils.esc(this.T.field_subgroup || this.T.subgroup_manage || 'Subgroup') + '</th>' +
      '<th style="width:23%" class="text-start">' + GroupUtils.esc(this.T.col_visibility || '') + '</th>' +
      '<th class="text-center col-status" style="width:12%">' + GroupUtils.esc(this.T.col_status || '') + '</th>' +
      '<th class="text-center col-actions" style="width:9%">' + GroupUtils.esc(this.T.col_actions || '') + '</th>' +
      '</tr></thead><tbody></tbody>' +
      '</table>';

    this.showContent(html);

    const tbody = this.cntEl.querySelector('#menuDT tbody');
    if (rows.length) {
      let lastModulID = null;
      tbody.innerHTML = rows.map(r => {
        const onId = 'flag_on_' + GroupUtils.esc(r.menuID);
        const offId = 'flag_off_' + GroupUtils.esc(r.menuID);
        const isOn = (parseInt(r.flag, 10) === 1);
        const showModulName = lastModulID !== r.modulID;
        const modulCellHtml = showModulName ? GroupUtils.esc(r.modulName) : '&nbsp;';
        const modulCellClass = showModulName ? 'fw-semibold align-top' : 'align-top';
        const pathTooltip = r.path
          ? '<button type="button" class="btn btn-link btn-sm p-0 ms-1 align-baseline menu-path-info" data-bs-toggle="tooltip" data-bs-placement="top" data-bs-custom-class="menu-path-tooltip" title="' + GroupUtils.esc(r.path) + '" aria-label="' + GroupUtils.esc(this.T.menu_path_info || 'Lihat path menu') + '"><i class="ri-information-line text-muted"></i></button>'
          : '';
        lastModulID = r.modulID;
        return '' +
          '<tr data-modul-id="' + GroupUtils.esc(r.modulID) + '" data-menu-id="' + GroupUtils.esc(r.menuID) + '">' +
          '<td class="' + modulCellClass + ' text-start">' + modulCellHtml + '</td>' +
          '<td class="text-start"><div class="fw-semibold d-inline-flex align-items-start">' + GroupUtils.esc(r.menuName) + pathTooltip + '</div></td>' +
          '<td class="text-start">' + subgroupBadge(r.subgroupName) + '</td>' +
          '<td class="text-start"><div class="d-flex flex-wrap gap-1">' +
              domainBadge(r.domain) +
              staffOnlyBadge(r.showStaffOnly) +
            '</div>' +
          '</td>' +
          '<td class="text-center col-status"><div class="menu-status-toggle">' +
          '<input type="radio" class="btn-check menu-flag" name="flag-' + GroupUtils.esc(r.menuID) + '" id="' + onId + '" value="1" ' + (isOn ? 'checked' : '') + '>' +
          '<label class="btn btn-outline-success btn-sm" for="' + onId + '">' + GroupUtils.esc(this.T.status_on || 'ON') + '</label>' +
          '<input type="radio" class="btn-check menu-flag" name="flag-' + GroupUtils.esc(r.menuID) + '" id="' + offId + '" value="0" ' + (!isOn ? 'checked' : '') + '>' +
          '<label class="btn btn-outline-secondary btn-sm" for="' + offId + '">' + GroupUtils.esc(this.T.status_off || 'OFF') + '</label>' +
          '</div>' +
          '</td>' +
          '<td class="text-center col-actions"><div class="menu-action-group">' +
          '<button class="btn btn-sm btn-outline-secondary icon-btn btn-edit-menu" title="' + GroupUtils.esc(this.T.edit || 'Edit') + '" aria-label="' + GroupUtils.esc(this.T.edit || 'Edit') + '"><i class="ri-pencil-line"></i></button> ' +
          '<button class="btn btn-sm btn-outline-danger icon-btn btn-del-menu" title="' + GroupUtils.esc(this.T.delete || 'Padam') + '" aria-label="' + GroupUtils.esc(this.T.delete || 'Padam') + '"><i class="ri-delete-bin-line"></i></button>' +
          '</div>' +
          '</td>' +
          '</tr>';
      }).join('');
    } else {
      tbody.innerHTML = '';
    }

    const dt = GroupState.getMenuDataTable();
    if (dt) {
      try { dt.destroy(); } catch (e) {}
      GroupState.setMenuDataTable(null);
    }

    if (GroupUtils.hasDataTable()) {
      const table = jQuery('#menuDT').DataTable({
        pageLength: 10,
        lengthChange: false,
        ordering: false,
        autoWidth: false,
        columnDefs: [
          { targets: 0, className: 'text-start align-top' },
          { targets: 1, className: 'text-start align-top' },
          { targets: 2, className: 'text-start align-top' },
          { targets: 3, orderable: false, searchable: false, className: 'text-start align-top' },
          { targets: 4, orderable: false, searchable: false, className: 'text-center align-top' },
          { targets: 5, orderable: false, searchable: false, className: 'text-center align-top' }
        ],
        dom: 'rt' + '<"dt-bottom-row mt-2 d-flex justify-content-between align-items-center"<"dt-info-left"i><"dt-paging-right d-flex justify-content-end"p>>',
        language: {
          emptyTable: this.T.no_records || 'Tiada rekod'
        }
      });
      GroupState.setMenuDataTable(table);
      requestAnimationFrame(() => this.adjustMenuDataTable());
      jQuery(this.modalEl)
        .off('shown.bs.modal.menuTableAdjust')
        .on('shown.bs.modal.menuTableAdjust', () => this.adjustMenuDataTable());
    }

    try {
      document.querySelectorAll('#menuDT [data-bs-toggle="tooltip"]').forEach((el) => {
        try {
          const existing = bootstrap.Tooltip.getInstance(el);
          if (existing) existing.dispose();
          new bootstrap.Tooltip(el, {
            html: false,
            container: '#aksesMenuModal',
            trigger: 'hover focus'
          });
        } catch (_) { /* ignore */ }
      });
    } catch (_) { /* ignore */ }

    this.cntEl.querySelector('#menuAddInsideBtn')?.addEventListener('click', () => this.handleAddMenu());
    this.cntEl.querySelector('#menuSubgroupManageBtn')?.addEventListener('click', () => this.openSubgroupManager());

    // Event handlers
    jQuery('#menuDT').off('click', '.btn-edit-menu').on('click', '.btn-edit-menu', (e) => {
      e.preventDefault();
      const tr = e.currentTarget.closest('tr');
      if (!tr) return;
      this.openEditMenu(tr.getAttribute('data-menu-id'));
    });
    
    jQuery('#menuDT').off('click', '.btn-del-menu').on('click', '.btn-del-menu', (e) => {
      e.preventDefault();
      const tr = e.currentTarget.closest('tr');
      if (!tr) return;
      this.deleteMenu(tr.getAttribute('data-menu-id'), tr);
    });

    jQuery('#menuDT').off('change', '.menu-flag').on('change', '.menu-flag', async function () {
      const input = this;
      const tr = input.closest('tr');
      if (!tr) return;
      const menuId = tr.getAttribute('data-menu-id');
      const shouldEnable = input.value === '1';
      const previousMenuIDs = GroupState.getMenuIDs().map((id) => parseInt(id, 10)).filter(Number.isFinite);
      const allAccessibleMenuIds = MenuAccess.getCurrentAccessibleMenuIds();
      const nextSet = new Set(previousMenuIDs.length ? previousMenuIDs : allAccessibleMenuIds);

      if (shouldEnable) {
        nextSet.add(parseInt(menuId, 10));
      } else {
        nextSet.delete(parseInt(menuId, 10));
      }

      GroupState.setMenuIDs(Array.from(nextSet).sort((a, b) => a - b));
      try {
        await MenuAccess.saveGroupMenuAccess();
      } catch (e) {
        GroupState.setMenuIDs(previousMenuIDs);
        const name = 'flag-' + menuId;
        MenuAccess.cntEl.querySelectorAll('input[name="' + name + '"]').forEach(el => {
          if (el !== input) el.checked = !input.checked;
        });
        MenuAccess.showError(e.message || MenuAccess.T.error_network || 'Ralat rangkaian');
        setTimeout(() => { MenuAccess.errEl.classList.add('d-none'); }, 2500);
      }
    });
  },
  
  async openMenuEditor(groupID) {
    this.showLoading();
    try {
      const { modulIDs, menuIDs, menusByModul, modulesMap } = await this.loadGroupPermissionState(groupID);
      const rows = [];

      modulIDs.forEach((modulID) => {
        const menuList = Array.isArray(menusByModul[modulID]) ? menusByModul[modulID] : [];
        menuList.forEach((m) => {
          const menuId = parseInt(m.id ?? m.f_menuID, 10);
          const hasExplicitMenuFilter = Array.isArray(menuIDs) && menuIDs.length > 0;
          const enabledForGroup = hasExplicitMenuFilter ? menuIDs.includes(menuId) : true;
          rows.push({
            modulID,
            modulName: modulesMap[modulID] || ('Modul ' + modulID),
            menuID: menuId,
            menuName: String(m.nama || m.menuName || m.kod || '-'),
            path: String(m.path || m.f_path || ''),
            domain: String(m.domain || m.f_domain || 'SHARED'),
            subgroupID: parseInt(m.subgroupID ?? m.f_subgroupID ?? 0, 10) || 0,
            subgroupName: String(m.subgroupName || m.subgroup_name || ''),
            showStaffOnly: parseInt(m.showStaffOnly ?? m.show_staff_only ?? m.f_show_staff_only ?? 1, 10) === 1 ? 1 : 0,
            flag: enabledForGroup ? 1 : 0
          });
        });
      });

      rows.sort((a, b) => (a.modulID - b.modulID) || ((a.subgroupID || 0) - (b.subgroupID || 0)) || String(a.menuName).localeCompare(String(b.menuName)));
      this.buildMenuTable(rows);
    } catch (e) {
      this.showError(e.message || this.T.error_network);
    }
  },
  
  handleAddMenu() {
    const modal = GroupUtils.getModal(this.editModalEl);
    if (!modal) return;
    const parentModal = GroupUtils.getModal(this.modalEl);

    const gidFromCtx = GroupState.getMenuGroupID();
    const gidFromHidden = (() => {
      const el = document.getElementById('em_groupID');
      const v = el ? parseInt((el.value || '0'), 10) : 0;
      return Number.isFinite(v) && v > 0 ? v : null;
    })();
    const gidFromBtn = (() => {
      const btn = GroupState.getLastMenuBtn() || document.querySelector('.view-menu[data-group-id]');
      const v = btn ? parseInt(btn.getAttribute('data-group-id') || '0', 10) : 0;
      return Number.isFinite(v) && v > 0 ? v : null;
    })();

    const resolvedGroupID = gidFromCtx || gidFromHidden || gidFromBtn || null;

      if (!resolvedGroupID) {
        if (window.Swal && typeof Swal.fire === 'function') {
          GroupUtils.fireAlert({
            icon: 'warning',
            title: this.T.info_title || 'Makluman',
            text: this.T.info_select_group_first || 'Sila pilih kumpulan dahulu melalui butang Akses Menu.',
            confirmButtonText: this.T.btn_ok || 'OK'
          });
        } else {
          alert(this.T.info_select_group_first || 'Sila pilih kumpulan dahulu melalui butang Akses Menu.');
        }
        return;
      }

    const hidEl = document.getElementById('em_groupID');
    const infoEl = document.getElementById('em_groupInfo');
    const infoWrapEl = document.getElementById('em_groupInfoWrap');
    if (hidEl) hidEl.value = String(resolvedGroupID);
    if (infoEl) {
      const src = GroupState.getLastMenuBtn() || document.querySelector('.view-menu[data-group-kod]');
      const gkod = src?.getAttribute('data-group-kod') || '';
      const gnam = src?.getAttribute('data-group-nama') || '';
      infoEl.textContent = (gkod + (gnam ? ' — ' + gnam : '')).trim();
    }
    if (infoWrapEl) infoWrapEl.classList.toggle('d-none', !(infoEl && String(infoEl.textContent || '').trim() !== ''));

    this.editErrorEl.classList.add('d-none');
    this.$ME('#em_menuID').value = '';
    this.$ME('#em_path').value = '';
    this.$ME('#em_name_ms').value = '';
    this.$ME('#em_name_en').value = '';
    this.$ME('#em_domain').value = 'SHARED';
    this.$ME('#em_show_staff_only_yes').checked = true;
    this.$ME('#em_flag_on').checked = true;

    this.editModalEl.dataset.mode = 'create';
    this.updateEditModalUI('create');
    if (parentModal && this.modalEl?.classList.contains('show')) {
      this.restoreParentMenuModal = true;
      parentModal.hide();
    } else {
      this.restoreParentMenuModal = false;
    }
    modal.show();
    this.populateModuls(null)
      .then(() => this.populateSubgroups(this.$ME('#em_modulID')?.value || '', 0))
      .catch((e) => {
        this.editErrorEl.textContent = e.message || this.T.error_network;
        this.editErrorEl.classList.remove('d-none');
      });
  },
  
  async openEditMenu(menuID) {
    const modal = GroupUtils.getModal(this.editModalEl);
    if (!modal) return;
    const parentModal = GroupUtils.getModal(this.modalEl);
    this.editErrorEl.classList.add('d-none');
    this.editModalEl.dataset.mode = 'edit';
    this.updateEditModalUI('edit');
    this.$ME('#em_menuID').value = '';
    this.$ME('#em_path').value = '';
    this.$ME('#em_name_ms').value = '';
    this.$ME('#em_name_en').value = '';
    this.$ME('#em_domain').value = 'SHARED';
    if (parentModal && this.modalEl?.classList.contains('show')) {
      this.restoreParentMenuModal = true;
      parentModal.hide();
    } else {
      this.restoreParentMenuModal = false;
    }
    modal.show();
    try {
      const j = await GroupUtils.fetchJSONSafe(GroupUtils.apiUrl('menu-get.php', { menuID }));
      if (!j || j.error) {
        this.showError((j && j.message) || this.T.error_get_menu);
        return;
      }
      this.$ME('#em_menuID').value = j.menu.f_menuID;
      this.$ME('#em_path').value = j.menu.f_path || '';
      this.$ME('#em_name_ms').value = j.menu.f_menuName_ms || '';
      this.$ME('#em_name_en').value = j.menu.f_menuName_en || '';
      this.$ME('#em_domain').value = j.menu.f_domain || 'SHARED';
      (parseInt(j.menu.f_show_staff_only ?? 1, 10) === 1 ? (this.$ME('#em_show_staff_only_yes').checked = true) : (this.$ME('#em_show_staff_only_no').checked = true));
      (parseInt(j.menu.f_flag ?? 0, 10) === 1 ? (this.$ME('#em_flag_on').checked = true) : (this.$ME('#em_flag_off').checked = true));
      await this.populateModuls(j.menu.f_modulID);
      await this.populateSubgroups(j.menu.f_modulID, j.menu.f_subgroupID || 0);
    } catch (e) {
      this.showError(e.message || this.T.error_network);
    }
  },
  
  async handleSave() {
    const modal = GroupUtils.getModal(this.editModalEl);
    const saveButton = document.getElementById('menuEditSaveBtn');
    const mode = this.editModalEl.dataset.mode || 'edit';
    this.editErrorEl.classList.add('d-none');

    const gidFromCtx = GroupState.getMenuGroupID();
    const gidFromHidden = Number.parseInt(document.getElementById('em_groupID')?.value || '0', 10) || 0;
    const groupID = gidFromCtx || gidFromHidden || 0;

    const payload = {
      groupID,
      menuID: parseInt((this.$ME('#em_menuID')?.value || '0'), 10),
      modulID: parseInt((this.$ME('#em_modulID')?.value || '0'), 10),
      subgroupID: parseInt((this.$ME('#em_subgroupID')?.value || '0'), 10) || 0,
      path: (this.$ME('#em_path')?.value || '').trim(),
      name_ms: this.$ME('#em_name_ms')?.value || '',
      name_en: this.$ME('#em_name_en')?.value || '',
      domain: this.$ME('#em_domain')?.value || 'SHARED',
      show_staff_only: this.$ME('#em_show_staff_only_yes')?.checked ? 1 : 0,
      flag: this.$ME('#em_flag_on')?.checked ? 1 : 0,
      position: document.getElementById('em_position')?.value || 'bottom'
    };

    if (!payload.path) {
      this.editErrorEl.textContent = this.T.err_path_required;
      this.editErrorEl.classList.remove('d-none');
      return;
    }
    if (payload.modulID <= 0) {
      this.editErrorEl.textContent = this.T.err_modul_required;
      this.editErrorEl.classList.remove('d-none');
      return;
    }
    if (!groupID) {
      this.editErrorEl.textContent = this.T.err_group_modul_path_required || 'Sila pilih Kumpulan, Modul dan isi Path.';
      this.editErrorEl.classList.remove('d-none');
      return;
    }

    try {
      const target = (mode === 'create') ? 'menu-create.php' : 'menu-save.php';
      GroupUtils.showLoader('menuAction', this.T.loading || this.T.btn_save || 'Loading...');
      GroupUtils.setButtonBusy(saveButton, true, this.T.saving || 'Saving...');
      const j = await GroupUtils.fetchJSONSafe(GroupUtils.apiUrl(target, { groupID }), {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': GroupUtils.getCSRF() },
        body: JSON.stringify(payload)
      });

      if (!j || j.error) {
        this.editErrorEl.textContent = (j && j.message) || (mode === 'create' ? this.T.err_add_menu : this.T.err_save_menu);
        this.editErrorEl.classList.remove('d-none');
        return;
      }
      const shouldRestoreParent = this.restoreParentMenuModal && this.modalEl;
      this.pendingParentRestoreAfterSave = !!shouldRestoreParent;
      this.restoreParentMenuModal = false;
      if (modal && this.editModalEl) {
        await new Promise((resolve) => {
          const onceHidden = () => resolve();
          this.editModalEl.addEventListener('hidden.bs.modal', onceHidden, { once: true });
          modal.hide();
        });
      } else {
        this.cleanupModalArtifacts();
      }
      if (GroupState && typeof GroupState.setMenuGroupID === 'function') {
        GroupState.setMenuGroupID(String(groupID));
      }
      if (document.getElementById('em_groupID')) {
        document.getElementById('em_groupID').value = String(groupID);
      }
      const successAlert = GroupUtils.fireAlert({
        icon: 'success',
        title: mode === 'create' ? (this.T.menu_save_success_create || 'Menu berjaya ditambah') : (this.T.menu_save_success_update || 'Menu berjaya dikemaskini'),
        confirmButtonText: this.T.btn_ok || 'OK'
      });
      await this.refreshGroupTableRow(groupID, {
        groupID,
        groupKod: GroupState.getLastMenuBtn()?.getAttribute('data-group-kod') || '',
        groupName: GroupState.getLastMenuBtn()?.getAttribute('data-group-nama') || '',
        modulAccess: GroupState.getModulIDs(),
        menuAccess: GroupState.getMenuIDs(),
      });
      this.syncSidebarAfterNavigationChange();
      if (shouldRestoreParent) {
        const parentModal = GroupUtils.getModal(this.modalEl);
        if (parentModal) {
          await this.waitForModalShown(this.modalEl, parentModal);
        }
      }
      this.pendingParentRestoreAfterSave = false;
      await this.openMenuEditor(groupID);
      this.adjustMenuDataTable();
      await successAlert;
    } catch (e) {
      this.pendingParentRestoreAfterSave = false;
      this.editErrorEl.textContent = e.message || this.T.error_network;
      this.editErrorEl.classList.remove('d-none');
    } finally {
      GroupUtils.setButtonBusy(saveButton, false);
      GroupUtils.hideLoader('menuAction');
    }
  },
  
  async deleteMenu(menuID, tr) {
    const menuName = (() => {
      try {
        if (!tr) return '';
        const el = tr.querySelector('td:nth-child(2) .fw-semibold');
        return (el?.textContent || '').trim();
      } catch (_) { return ''; }
    })();
    const prettyName = menuName || `ID ${menuID}`;

    async function askConfirm() {
      if (window.Swal && typeof Swal.fire === 'function') {
        const escapedName = GroupUtils.esc(prettyName);
        const confirmTitle = MenuAccess.formatText(MenuAccess.T.confirm_delete_menu_title || 'Padam menu "{name}"?', { name: escapedName });
        const confirmIntro = MenuAccess.formatText(MenuAccess.T.confirm_delete_menu_intro || 'Menu <strong>{name}</strong> akan <u>dipadam</u>.', { name: escapedName });
        const confirmCleanup = MenuAccess.T.confirm_delete_menu_cleanup || 'Menu ini juga akan dibersihkan daripada <em>semua kumpulan</em> yang rujuk ID ini.';
        const confirmIrreversible = MenuAccess.T.confirm_delete_menu_irreversible || 'Tindakan ini tidak boleh diundur.';
        const res = await (window.GroupSwal ? GroupSwal.fire({
          icon: 'warning',
          title: confirmTitle,
          html: `
            <div class="text-start">
              <p class="mb-2">${confirmIntro}</p>
              <ul class="mb-0">
                <li>${confirmCleanup}</li>
                <li>${confirmIrreversible}</li>
              </ul>
            </div>
          `,
          showCancelButton: true,
          confirmButtonText: MenuAccess.T.confirm_yes || 'Ya, padam',
          cancelButtonText: MenuAccess.T.confirm_cancel || 'Batal',
          reverseButtons: true,
          focusCancel: true
        }) : Swal.fire({
          icon: 'warning',
          title: confirmTitle,
          html: `
            <div class="text-start">
              <p class="mb-2">${confirmIntro}</p>
              <ul class="mb-0">
                <li>${confirmCleanup}</li>
                <li>${confirmIrreversible}</li>
              </ul>
            </div>
          `,
          showCancelButton: true,
          confirmButtonText: MenuAccess.T.confirm_yes || 'Ya, padam',
          cancelButtonText: MenuAccess.T.confirm_cancel || 'Batal',
          reverseButtons: true,
          focusCancel: true
        }));
        return res.isConfirmed;
      }
      if (window.swal && typeof window.swal === 'function') {
        return await new Promise(resolve => {
          window.swal({
            title: MenuAccess.formatText(MenuAccess.T.confirm_delete_menu_title || 'Padam menu "{name}"?', { name: prettyName }),
            text: MenuAccess.T.confirm_delete_menu_cleanup || 'Menu ini juga akan dibersihkan daripada semua kumpulan.',
            icon: 'warning',
            buttons: [MenuAccess.T.confirm_cancel || 'Batal', MenuAccess.T.confirm_yes || 'Ya, padam'],
            dangerMode: true
          }).then(val => resolve(!!val));
        });
      }
      return confirm(MenuAccess.formatText(MenuAccess.T.confirm_delete_menu_fallback || 'Padam menu "{name}"? Menu ini juga akan dibersihkan daripada semua kumpulan.', { name: prettyName }));
    }

    const ok = await askConfirm();
    if (!ok) return;

    let delBtn;
    if (tr) {
      delBtn = tr.querySelector('.btn-del-menu');
      if (delBtn) delBtn.disabled = true;
    }

    GroupUtils.showLoader('menuAction', MenuAccess.T.loading || MenuAccess.T.confirm_yes || 'Loading...');
    try {
      const payload = {
        csrf_token: GroupUtils.getCSRF(),
        menuID: Number(menuID),
        groupID: Number(GroupState.getMenuGroupID() || 0),
        hard: 1
      };

      const j = await GroupUtils.fetchJSONSafe(GroupUtils.apiUrl('menu-delete.php'), {
        method: 'POST',
        credentials: 'same-origin',
        headers: {
          'Content-Type': 'application/json',
          'X-CSRF-Token': GroupUtils.getCSRF(),
          'X-Requested-With': 'XMLHttpRequest'
        },
        body: JSON.stringify(payload)
      });

      if (!j || j.error) throw new Error((j && j.message) || MenuAccess.T.delete_fail || 'Gagal memadam.');

      const dt = GroupState.getMenuDataTable();
      if (window.jQuery && dt && tr) {
        dt.row(jQuery(tr)).remove().draw(false);
      } else if (tr && tr.parentNode) {
        tr.parentNode.removeChild(tr);
      } else {
        this.openMenuEditor(GroupState.getMenuGroupID());
      }

      const successAlert = GroupUtils.fireAlert({
        icon: 'success',
        title: MenuAccess.T.deleted_title || 'Dipadam',
        text: MenuAccess.formatText(MenuAccess.T.delete_menu_cleanup_success || 'Menu "{name}" dibersihkan dari semua kumpulan.', { name: prettyName }),
        confirmButtonText: MenuAccess.T.btn_ok || 'OK'
      });
      await this.refreshGroupTableRow(GroupState.getMenuGroupID(), {
        groupID: GroupState.getMenuGroupID(),
        groupKod: GroupState.getLastMenuBtn()?.getAttribute('data-group-kod') || '',
        groupName: GroupState.getLastMenuBtn()?.getAttribute('data-group-nama') || '',
        modulAccess: GroupState.getModulIDs(),
        menuAccess: GroupState.getMenuIDs().filter((id) => parseInt(id, 10) !== parseInt(menuID, 10)),
      });
      if (
        window.ModuleAccess
        && typeof window.ModuleAccess.reloadCurrentAccess === 'function'
      ) {
        const accessGroupId = parseInt(window.ModuleAccess.currentGroup?.id || '0', 10) || 0;
        const menuGroupId = parseInt(GroupState.getMenuGroupID() || '0', 10) || 0;
        if (accessGroupId > 0 && accessGroupId === menuGroupId) {
          await window.ModuleAccess.reloadCurrentAccess();
        }
      }
      await this.refreshVisibleGroupTableRows();
      this.syncSidebarAfterNavigationChange();
      await successAlert;
    } catch (e) {
      if (window.Swal && Swal.fire) {
        (window.GroupSwal ? GroupSwal.fire({
          icon: 'error',
          title: MenuAccess.T.delete_failed_title || 'Gagal',
          text: e.message || MenuAccess.T.error_network || 'Ralat rangkaian',
          confirmButtonText: MenuAccess.T.btn_ok || 'OK'
        }) : Swal.fire({
          icon: 'error',
          title: MenuAccess.T.delete_failed_title || 'Gagal',
          text: e.message || MenuAccess.T.error_network || 'Ralat rangkaian',
          confirmButtonText: MenuAccess.T.btn_ok || 'OK'
        }));
      } else {
        alert(e.message || MenuAccess.T.error_network || 'Ralat rangkaian');
      }
    } finally {
      if (delBtn) delBtn.disabled = false;
      GroupUtils.hideLoader('menuAction');
    }
  },
  
  async populateModuls(selected) {
    const sel = this.$ME('#em_modulID');
    if (!sel) return;
    sel.innerHTML = '<option value="">' + GroupUtils.esc(this.T.loading_modules || 'Memuatkan modul...') + '</option>';
    let options = [];
    const normalizeModules = (rows) => (Array.isArray(rows) ? rows : []).map(m => ({
      id: parseInt(m.id ?? m.f_modulID, 10),
      nama: String(m.nama || m.modulName || m.f_modulName_ms || m.f_modulName_en || ('Modul ' + (m.id || m.f_modulID)))
    })).filter(x => Number.isInteger(x.id) && x.id > 0);
    try {
      const j = await GroupUtils.fetchJSONSafe(GroupUtils.apiUrl('modul-list.php'));
      const arr = Array.isArray(j?.moduls) ? j.moduls : (Array.isArray(j) ? j : []);
      options = normalizeModules(arr);
    } catch (e) {
      console.warn('populateModuls AJAX failed, using embedded module options', e);
    }
    if (!options.length) {
      options = normalizeModules(window.GroupModuleOptions || []);
    }
    sel.innerHTML = '';
    options.forEach(m => {
      const opt = document.createElement('option');
      opt.value = String(m.id);
      opt.textContent = m.nama;
      if (selected && parseInt(selected, 10) === m.id) opt.selected = true;
      sel.appendChild(opt);
    });
    if (!options.length) {
      const opt = document.createElement('option');
      opt.value = '';
      opt.textContent = this.T.no_modules_found || 'Tiada modul ditemui.';
      sel.appendChild(opt);
    }
  },

  async populateSubgroups(modulID, selected) {
    const sel = this.$ME('#em_subgroupID');
    if (!sel) return;
    sel.innerHTML = '<option value="0">' + GroupUtils.esc(this.T.subgroup_none || 'Tiada subgroup') + '</option>';
    const mid = parseInt(modulID || '0', 10) || 0;
    if (!mid) return;
    try {
      const j = await GroupUtils.fetchJSONSafe(GroupUtils.apiUrl('menu-subgroup-list.php', { modulID: mid, active: 1 }));
      const rows = Array.isArray(j?.subgroups) ? j.subgroups : [];
      rows.forEach((sg) => {
        const opt = document.createElement('option');
        opt.value = String(sg.id);
        opt.textContent = sg.name || sg.name_ms || ('Subgroup ' + sg.id);
        if (parseInt(selected || '0', 10) === parseInt(sg.id, 10)) opt.selected = true;
        sel.appendChild(opt);
      });
    } catch (e) {
      console.warn('populateSubgroups failed', e);
    }
  },

  async populateModuleSelect(selectId, selected) {
    const sel = document.getElementById(selectId);
    if (!sel) return;
    sel.innerHTML = '<option value="">' + GroupUtils.esc(this.T.loading_modules || 'Memuatkan modul...') + '</option>';
    let options = [];
    const normalizeModules = (rows) => (Array.isArray(rows) ? rows : []).map(m => ({
      id: parseInt(m.id ?? m.f_modulID, 10),
      nama: String(m.nama || m.modulName || m.f_modulName_ms || m.f_modulName_en || ('Modul ' + (m.id || m.f_modulID)))
    })).filter(x => Number.isInteger(x.id) && x.id > 0);
    try {
      const j = await GroupUtils.fetchJSONSafe(GroupUtils.apiUrl('modul-list.php'));
      options = normalizeModules(Array.isArray(j?.moduls) ? j.moduls : (Array.isArray(j) ? j : []));
    } catch (e) {
      options = normalizeModules(window.GroupModuleOptions || []);
    }
    sel.innerHTML = '';
    options.forEach(m => {
      const opt = document.createElement('option');
      opt.value = String(m.id);
      opt.textContent = m.nama;
      if (selected && parseInt(selected, 10) === m.id) opt.selected = true;
      sel.appendChild(opt);
    });
  },

  setSubgroupIcon(icon) {
    const value = String(icon || 'ri-folder-2-line').trim() || 'ri-folder-2-line';
    const input = document.getElementById('sg_icon');
    if (input) input.value = value;
    document.querySelectorAll('#sg_iconPicker .subgroup-icon-option').forEach((btn) => {
      btn.classList.toggle('active', btn.getAttribute('data-icon') === value);
    });
  },

  setSubgroupOrderPreview(value) {
    const numeric = parseInt(value || '0', 10) || 0;
    const input = document.getElementById('sg_order');
    const preview = document.getElementById('sg_orderPreview');
    if (input) input.value = String(numeric);
    if (preview) preview.textContent = numeric > 0 ? ('#' + numeric) : 'Auto';
  },

  resetSubgroupForm(row) {
    const data = row || {};
    const setValue = (id, value) => {
      const el = document.getElementById(id);
      if (el) el.value = value == null ? '' : String(value);
    };
    setValue('sg_subgroupID', data.id || 0);
    setValue('sg_code', data.code || '');
    setValue('sg_name_ms', data.name_ms || '');
    setValue('sg_name_en', data.name_en || '');
    this.setSubgroupIcon(data.icon || 'ri-folder-2-line');
    this.setSubgroupOrderPreview(data.id ? (data.sortOrder || 1) : 0);
    setValue('sg_status', data.status == null ? 1 : data.status);
    if (data.modulID) setValue('sg_modulID', data.modulID);
    document.getElementById('menuSubgroupError')?.classList.add('d-none');
  },

  async showSubgroupAlert(options = {}) {
    return GroupUtils.fireAlert(Object.assign({
      confirmButtonText: this.T.ok || 'OK'
    }, options));
  },

  async showSubgroupInUseAlert(row) {
    const name = String(row?.name || row?.name_ms || this.T.field_subgroup || 'Subgroup');
    const count = parseInt(row?.menuCount || '0', 10) || 0;
    await this.showSubgroupAlert({
      icon: 'info',
      title: this.T.not_allowed_title || 'Tidak Dibenarkan',
      html: '<div class="text-start">' +
        '<p class="mb-2">' + GroupUtils.esc(this.T.subgroup_in_use || 'Subgroup ini sedang digunakan oleh menu. Pindahkan menu dahulu sebelum padam.') + '</p>' +
        '<div class="small text-muted">' + GroupUtils.esc(name + (count > 0 ? (' · ' + count + ' menu') : '')) + '</div>' +
        '</div>'
    });
  },

  async confirmSubgroupDelete(row) {
    const name = String(row?.name || row?.name_ms || this.T.field_subgroup || 'Subgroup');
    const title = MenuAccess.formatText(this.T.subgroup_confirm_delete_title || this.T.subgroup_confirm_delete || 'Padam subgroup "{name}"?', { name });
    const text = MenuAccess.formatText(this.T.subgroup_confirm_delete_text || 'Subgroup "{name}" akan dipadam jika tiada menu yang menggunakan subgroup ini.', { name });
    const result = await GroupUtils.fireAlert({
      icon: 'warning',
      title,
      text,
      showCancelButton: true,
      confirmButtonText: this.T.confirm_yes_delete || this.T.confirm_yes || 'Ya, Padam',
      cancelButtonText: this.T.confirm_cancel || 'Batal',
      reverseButtons: true,
      focusCancel: true
    });
    return !!(result && result.isConfirmed);
  },

  async openSubgroupManager() {
    if (!this.subgroupModalEl) return;
    const parentModal = GroupUtils.getModal(this.modalEl);
    await this.populateModuleSelect('sg_modulID', this.$ME('#em_modulID')?.value || '');
    this.resetSubgroupForm();
    await this.loadSubgroupsForManager();
    const modal = GroupUtils.getModal(this.subgroupModalEl);
    if (!modal) return;
    if (parentModal && this.modalEl?.classList.contains('show')) {
      this.restoreParentAfterSubgroupModal = true;
      await new Promise((resolve) => {
        this.modalEl.addEventListener('hidden.bs.modal', resolve, { once: true });
        parentModal.hide();
      });
    } else {
      this.restoreParentAfterSubgroupModal = false;
    }
    modal.show();
  },

  async loadSubgroupsForManager() {
    const tableBody = document.querySelector('#menuSubgroupTable tbody');
    if (!tableBody) return;
    tableBody.innerHTML = '<tr><td colspan="4" class="text-center text-muted py-4">' + GroupUtils.esc(this.T.loading || 'Loading') + '...</td></tr>';
    const modulID = parseInt(document.getElementById('sg_modulID')?.value || '0', 10) || 0;
    GroupUtils.showLoader('menuAction', this.T.loading || 'Loading...');
    try {
      const j = await GroupUtils.fetchJSONSafe(GroupUtils.apiUrl('menu-subgroup-list.php', { modulID, active: 1 }));
      const rows = Array.isArray(j?.subgroups) ? j.subgroups : [];
      this.subgroupRows = rows;
      const editingID = parseInt(document.getElementById('sg_subgroupID')?.value || '0', 10) || 0;
      if (!editingID) {
        this.setSubgroupOrderPreview(0);
      }
      if (!rows.length) {
        tableBody.innerHTML = '<tr><td colspan="4" class="text-center text-muted py-4">' + GroupUtils.esc(this.T.no_records || 'Tiada rekod') + '</td></tr>';
        return;
      }
      tableBody.innerHTML = rows.map((r, idx) => {
        const safeName = GroupUtils.esc(r.name || r.name_ms || '-');
        const menuCount = parseInt(r.menuCount || '0', 10) || 0;
        const deleteBtn = menuCount > 0
          ? ' <button type="button" class="btn btn-sm btn-outline-secondary sg-delete-blocked" title="' + GroupUtils.esc(this.T.subgroup_in_use || 'Subgroup ini sedang digunakan oleh menu.') + '"><i class="ri-lock-line"></i></button>'
          : ' <button type="button" class="btn btn-sm btn-outline-danger sg-delete" title="' + GroupUtils.esc(this.T.delete || 'Delete') + '"><i class="ri-delete-bin-line"></i></button>';
        return '<tr data-index="' + idx + '">' +
          '<td class="align-top">' + GroupUtils.esc(r.modulName || ('Modul ' + r.modulID)) + '</td>' +
          '<td class="align-top"><div class="fw-semibold"><i class="' + GroupUtils.esc(r.icon || 'ri-folder-2-line') + ' me-1"></i>' + safeName + '</div><div class="small text-muted">' + GroupUtils.esc(r.code || '-') + (menuCount > 0 ? ' · ' + GroupUtils.esc(menuCount + ' menu') : '') + '</div></td>' +
          '<td class="text-center align-top">' + GroupUtils.esc(r.sortOrder || 1) + '</td>' +
          '<td class="text-center align-top"><button type="button" class="btn btn-sm btn-outline-secondary sg-edit"><i class="ri-pencil-line"></i></button>' + deleteBtn + '</td>' +
          '</tr>';
      }).join('');
    } catch (e) {
      tableBody.innerHTML = '<tr><td colspan="4" class="text-center text-danger py-4">' + GroupUtils.esc(e.message || this.T.subgroup_load_fail || 'Gagal memuat subgroup') + '</td></tr>';
    } finally {
      GroupUtils.hideLoader('menuAction');
    }
  },

  async saveSubgroup() {
    const errEl = document.getElementById('menuSubgroupError');
    if (errEl) errEl.classList.add('d-none');
    const payload = {
      subgroupID: parseInt(document.getElementById('sg_subgroupID')?.value || '0', 10) || 0,
      modulID: parseInt(document.getElementById('sg_modulID')?.value || '0', 10) || 0,
      code: (document.getElementById('sg_code')?.value || '').trim(),
      name_ms: (document.getElementById('sg_name_ms')?.value || '').trim(),
      name_en: (document.getElementById('sg_name_en')?.value || '').trim(),
      icon: document.getElementById('sg_icon')?.value || 'ri-folder-2-line',
      order: parseInt(document.getElementById('sg_order')?.value || '0', 10) || 0,
      status: parseInt(document.getElementById('sg_status')?.value || '1', 10) === 1 ? 1 : 0
    };
    if (!payload.modulID || !payload.name_ms) {
      await this.showSubgroupAlert({
        icon: 'error',
        title: this.T.error || 'Ralat',
        text: this.T.subgroup_required || 'Sila pilih modul dan isi nama subgroup.'
      });
      return;
    }
    GroupUtils.showLoader('menuAction', this.T.loading || this.T.btn_save || 'Loading...');
    const saveButton = document.getElementById('menuSubgroupSaveBtn');
    GroupUtils.setButtonBusy(saveButton, true, this.T.saving || 'Saving...');
    try {
      const j = await GroupUtils.fetchJSONSafe(GroupUtils.apiUrl('menu-subgroup-save.php'), {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': GroupUtils.getCSRF() },
        body: JSON.stringify(payload)
      });
      if (!j || j.error) throw new Error((j && j.message) || this.T.error_save || 'Gagal menyimpan.');
      this.resetSubgroupForm({ modulID: payload.modulID });
      await this.loadSubgroupsForManager();
      await this.populateSubgroups(this.$ME('#em_modulID')?.value || '', this.$ME('#em_subgroupID')?.value || 0);
      this.syncSidebarAfterNavigationChange();
      await this.showSubgroupAlert({
        icon: 'success',
        title: this.T.success_title || 'Berjaya',
        text: (j && j.message) || this.T.subgroup_save_success || 'Subgroup berjaya disimpan.'
      });
    } catch (e) {
      if (errEl) errEl.classList.add('d-none');
      await this.showSubgroupAlert({
        icon: 'error',
        title: this.T.error || 'Ralat',
        text: e.message || this.T.error_network
      });
    } finally {
      GroupUtils.setButtonBusy(saveButton, false);
      GroupUtils.hideLoader('menuAction');
    }
  },

  async deleteSubgroup(row) {
    if (!row || !row.id) return;
    if ((parseInt(row.menuCount || '0', 10) || 0) > 0) {
      await this.showSubgroupInUseAlert(row);
      return;
    }
    const ok = await this.confirmSubgroupDelete(row);
    if (!ok) return;
    GroupUtils.showLoader('menuAction', this.T.loading || this.T.confirm_yes_delete || 'Loading...');
    try {
      const j = await GroupUtils.fetchJSONSafe(GroupUtils.apiUrl('menu-subgroup-delete.php'), {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': GroupUtils.getCSRF() },
        body: JSON.stringify({ subgroupID: row.id })
      });
      if (!j || j.error) throw new Error((j && j.message) || this.T.delete_fail || 'Gagal memadam.');
      await this.loadSubgroupsForManager();
      await this.populateSubgroups(this.$ME('#em_modulID')?.value || '', 0);
      this.syncSidebarAfterNavigationChange();
      await this.showSubgroupAlert({
        icon: 'success',
        title: this.T.deleted_title || this.T.success_title || 'Dipadam',
        text: (j && j.message) || this.T.subgroup_delete_success || 'Subgroup berjaya dipadam.'
      });
    } catch (e) {
      const errEl = document.getElementById('menuSubgroupError');
      if (errEl) errEl.classList.add('d-none');
      await this.showSubgroupAlert({
        icon: 'error',
        title: this.T.delete_failed_title || this.T.error || 'Gagal',
        text: e.message || this.T.error_network
      });
    } finally {
      GroupUtils.hideLoader('menuAction');
    }
  },
  
  openMenuFromBtn(btn) {
    const el = document.getElementById('aksesMenuModal');
    const gid = btn.getAttribute('data-group-id');
    const gkod = btn.getAttribute('data-group-kod') || '';
    const gnam = btn.getAttribute('data-group-nama') || '';

    GroupState.setLastMenuBtn(btn);
    GroupState.setMenuGroupID(gid);
    if (this.subEl) this.subEl.textContent = gkod + (gnam ? ' — ' + gnam : '');

    const hidEl = document.getElementById('em_groupID');
    const infoEl = document.getElementById('em_groupInfo');
    const infoWrapEl = document.getElementById('em_groupInfoWrap');
    if (hidEl) hidEl.value = String(GroupState.getMenuGroupID() || '');
    if (infoEl) infoEl.textContent = (gkod + (gnam ? ' — ' + gnam : '')).trim();
    if (infoWrapEl) infoWrapEl.classList.toggle('d-none', !(infoEl && String(infoEl.textContent || '').trim() !== ''));

    if (window.bootstrap?.Modal && el) {
      GroupUtils.ensureInBody(el);
      window.bootstrap.Modal.getOrCreateInstance(el, { backdrop: true, focus: true, keyboard: true }).show();
    } else if (el) {
      el.style.display = 'block';
      el.classList.add('show');
      el.removeAttribute('aria-hidden');
      el.setAttribute('aria-modal', 'true');
      el.setAttribute('role', 'dialog');
    } else {
      console.error('aksesMenuModal tidak ditemui');
      return;
    }

    this.openMenuEditor(gid);
  }
};

window.MenuAccess = MenuAccess;
