(function () {
  'use strict';

  const pageConfig = window.profilePageConfig || {};
  const CONFIG = pageConfig.config || {};
  const I18N = pageConfig.i18n || {};
  const URLS = pageConfig.urls || {};
  const DT = pageConfig.datatables || {};
  const PERMISSIONS = pageConfig.permissions || {};

  const ProfilePage = {
    loginActivityDT: null,
    auditEventsDT: null,
    lastCopyTime: 0,
    isLoading: false,
    pageLoaderTokens: {},

    copyText: async function (text) {
      if (!text) {
        this.toast(I18N.copy_empty, 'error');
        return;
      }

      const now = Date.now();
      if (now - this.lastCopyTime < (CONFIG.COPY_RATE_LIMIT || 0)) {
        this.toast(I18N.copy_wait, 'warning');
        return;
      }
      this.lastCopyTime = now;

      try {
        if (navigator.clipboard && window.isSecureContext) {
          await navigator.clipboard.writeText(text);
          this.toast(I18N.copied, 'success');
        } else {
          this.fallbackCopy(text);
        }
      } catch (e) {
        console.error('Clipboard API failed:', e);
        this.fallbackCopy(text);
      }
    },

    _escapeHtml: function (str) {
      if (!str) return '';
      return String(str)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#39;');
    },

    fallbackCopy: function (text) {
      const el = document.createElement('textarea');
      el.value = text;
      el.setAttribute('readonly', '');
      el.style.position = 'fixed';
      el.style.left = '-9999px';
      document.body.appendChild(el);
      el.select();

      try {
        document.execCommand('copy');
        this.toast(I18N.copied, 'success');
      } catch (e) {
        console.error('Fallback copy failed:', e);
        this.toast(I18N.copy_failed, 'error');
      } finally {
        document.body.removeChild(el);
      }
    },

    toast: function (msg, type) {
      const toastType = type || 'info';
      let toast = document.querySelector('.toast-lite');
      if (!toast) {
        toast = document.createElement('div');
        toast.className = 'toast-lite';
        toast.setAttribute('aria-live', 'polite');
        toast.setAttribute('aria-atomic', 'true');
        document.body.appendChild(toast);
      }

      toast.textContent = msg;
      toast.className = 'toast-lite toast-' + toastType;
      toast.classList.add('show');

      setTimeout(() => {
        toast.classList.remove('show');
      }, CONFIG.TOAST_DURATION || 2500);
    },

    showMetadataForbiddenAlert: async function () {
      if (window.Swal && typeof Swal.fire === 'function') {
        await Swal.fire({
          icon: 'warning',
          title: I18N.metadata_forbidden_title || 'Paparan Terhad',
          text: I18N.metadata_forbidden_text || 'Metadata jejak audit hanya tersedia untuk semakan Super Admin.',
          confirmButtonText: I18N.swal_ok || 'OK'
        });
        return;
      }
      this.toast(I18N.metadata_forbidden_text || 'Metadata jejak audit hanya tersedia untuk semakan Super Admin.', 'warning');
    },

    showLoading: function (selector) {
      const el = document.querySelector(selector);
      if (el) {
        el.style.display = 'block';
        this.isLoading = true;
      }
    },

    hideLoading: function (selector) {
      const el = document.querySelector(selector);
      if (el) {
        el.style.display = 'none';
        this.isLoading = false;
      }
    },

    showPageLoader: function (key, message) {
      this.hidePageLoader(key);
      this.pageLoaderTokens[key] = message || I18N.loading || I18N.dt_processing || 'Loading...';
    },

    hidePageLoader: function (key) {
      if (!this.pageLoaderTokens[key]) {
        return;
      }
      delete this.pageLoaderTokens[key];
    },

    waitForDataTables: function (maxWait) {
      const waitMs = maxWait || 5000;
      return new Promise((resolve, reject) => {
        const startTime = Date.now();
        const checkInterval = setInterval(() => {
          if (typeof $ !== 'undefined' && $.fn && typeof $.fn.DataTable !== 'undefined') {
            clearInterval(checkInterval);
            resolve();
          } else if (Date.now() - startTime > waitMs) {
            clearInterval(checkInterval);
            reject(new Error(I18N.dt_timeout || 'DataTables failed to load within timeout'));
          }
        }, CONFIG.POLLING_INTERVAL || 100);
      });
    },

    killSession: async function (sessionId) {
      if (!sessionId) {
        this.toast(I18N.kill_error_no_session, 'error');
        return;
      }

      const confirmed = await Swal.fire({
        title: I18N.kill_confirm_title,
        text: I18N.kill_confirm_text,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: I18N.kill_confirm_yes,
        cancelButtonText: I18N.kill_confirm_no,
        confirmButtonColor: '#dc3545',
        cancelButtonColor: '#6c757d'
      });

      if (!confirmed.isConfirmed) return;

      try {
        this.showPageLoader('killSession', I18N.profile_loading || I18N.loading || 'Loading...');

        const response = await fetch(URLS.killSessionAjax, {
          method: 'POST',
          noLoader: true,
          headers: {
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            'X-No-Loader': '1'
          },
          body: JSON.stringify({
            session_id: sessionId,
            csrf_token: pageConfig.csrfToken || ''
          })
        });

        const data = await response.json();

        if (data.success) {
          this.hidePageLoader('killSession');

          if (data.force_logout) {
            const countdown = parseInt(data.countdown || 10, 10);
            let remaining = countdown;
            await Swal.fire({
              icon: 'warning',
              title: I18N.kill_force_title,
              html: '<div id="swal-logout-count">' + this._escapeHtml(I18N.kill_force_text) + ' <strong>' + remaining + '</strong>s</div>',
              showConfirmButton: false,
              allowOutsideClick: false,
              allowEscapeKey: false,
              didOpen: () => {
                const el = document.getElementById('swal-logout-count');
                const timer = setInterval(() => {
                  remaining -= 1;
                  if (el) {
                    const strong = el.querySelector('strong');
                    if (strong) strong.textContent = remaining;
                  }
                  if (remaining <= 0) {
                    clearInterval(timer);
                    window.location.href = URLS.logout;
                  }
                }, 1000);
              }
            });
            return;
          }

          await Swal.fire({
            icon: 'success',
            title: I18N.kill_success_title,
            text: data.message || I18N.kill_success_text,
            confirmButtonText: I18N.swal_ok || 'OK'
          });

          if (this.loginActivityDT && this.loginActivityDT.ajax) {
            this.loginActivityDT.ajax.reload(null, true);
          } else {
            setTimeout(() => this.initLoginActivityTable(), 200);
          }
        } else {
          this.hidePageLoader('killSession');
          await Swal.fire({
            icon: 'error',
            title: I18N.kill_error_title,
            text: data.message || I18N.kill_error_title
          });
        }
      } catch (error) {
        console.error('Kill session error:', error);
        this.hidePageLoader('killSession');
        await Swal.fire({ icon: 'error', title: I18N.kill_error_network });
      }
    },

    initLoginActivityTable: function () {
      if (!$.fn.DataTable) {
        console.warn('DataTables library not loaded yet');
        return;
      }
      if (!$('#loginActivityTable').length) return;
      if (!$('#login-aktiviti-tab').hasClass('active') && !$('#login-aktiviti-tab').hasClass('show')) return;
      if ($.fn.DataTable.isDataTable('#loginActivityTable')) return;

      if (this.loginActivityDT) {
        try {
          this.loginActivityDT.destroy();
        } catch (e) {
          // ignore
        }
        this.loginActivityDT = null;
      }

      this.loginActivityDT = $('#loginActivityTable').DataTable({
        ajax: {
          url: URLS.loginActivityAjax,
          headers: { 'X-No-Loader': '1' },
          dataSrc: 'data'
        },
        columns: [
          { data: null, title: I18N.table_number || 'No.' },
          { data: 'started' },
          { data: 'ip' },
          { data: 'device' },
          { data: 'duration' },
          { data: 'status' },
          { data: 'actions' }
        ],
        order: [[1, 'desc']],
        pageLength: CONFIG.DATATABLES_PAGE_LENGTH,
        lengthMenu: [[5, 10, 25, 50, 100], [5, 10, 25, 50, 100]],
        language: DT.loginActivityLanguage || {},
          responsive: true,
        autoWidth: false,
        stateSave: false,
        processing: false,
        deferRender: true,
        dom: '<"row mb-2"<"col-sm-12 col-md-6 dt-top-left"l><"col-sm-12 col-md-6 d-flex justify-content-md-end dt-top-right"f>>' +
          't' +
          '<"dt-bottom-row mt-2 d-flex justify-content-between align-items-center"<"dt-info-left"i><"dt-paging-right d-flex justify-content-end"p>>',
        columnDefs: [
          { orderable: false, searchable: false, targets: [0] },
          { orderable: true, targets: [1, 3, 4] },
          { orderable: false, targets: [6] },
          { className: 'text-center', targets: [4, 5, 6] },
          { targets: [2, 3, 5, 6], render: function (data) { return data; } }
        ],
        createdRow: function () {
          // allow HTML in some columns
        }
      });

      $('#loginActivityTable').on('preXhr.dt', function () {
        ProfilePage.showPageLoader('loginActivity', I18N.profile_loading || I18N.loading || 'Loading...');
      });

      this.loginActivityDT.on('xhr.dt draw.dt', function () {
        ProfilePage.hidePageLoader('loginActivity');
        try {
          const api = $('#loginActivityTable').DataTable();
          const emptyCell = $('#loginActivityTable tbody td.dataTables_empty');
          if (emptyCell.length) {
            emptyCell.attr('colspan', 7).addClass('text-center');
            return;
          }
          const info = api.page.info();
          api.rows({ page: 'current' }).nodes().each(function (el, i) {
            if ($(el).find('td.dataTables_empty').length) return;
            $(el).find('td').eq(0).html(info.start + i + 1);
          });
        } catch (err) {
          console.error('Numbering update failed:', err);
        }
      });

      $('#loginActivityTable').on('error.dt', function (e, settings, techNote, message) {
        ProfilePage.hidePageLoader('loginActivity');
        console.error('DataTable error:', techNote, message);
        Swal.fire({
          icon: 'error',
          title: I18N.dt_error_title,
          text: message || techNote || I18N.dt_error_message
        });
        if (window.DataTableStandard && typeof window.DataTableStandard.decorate === 'function') {
          window.DataTableStandard.decorate('#loginActivityTable', {
            searchPlaceholder: (DT.loginActivityLanguage && DT.loginActivityLanguage.search) || ''
          });
        }
      });
    },

    initAuditEventsTable: function () {
      if (!$.fn.DataTable) {
        console.warn('DataTables library not loaded yet');
        return;
      }
      if (!$('#auditEventsTable').length) return;
      if (!$('#jejak-audit-tab').hasClass('active') && !$('#jejak-audit-tab').hasClass('show')) return;
      if ($.fn.DataTable.isDataTable('#auditEventsTable')) return;

      if (this.auditEventsDT) {
        try {
          this.auditEventsDT.destroy();
        } catch (e) {
          // ignore
        }
        this.auditEventsDT = null;
      }

      const formatAuditActivityCell = (html) => {
        const temp = document.createElement('div');
        temp.innerHTML = String(html ?? '');
        const fullText = (temp.textContent || temp.innerText || '').replace(/\s+/g, ' ').trim();
        return '<span class="audit-activity-text" data-bs-toggle="tooltip" data-bs-placement="top" data-bs-title="' + this._escapeHtml(fullText) + '">' + String(html ?? '') + '</span>';
      };

      const initAuditActivityTooltips = () => {
        if (!window.bootstrap || !bootstrap.Tooltip) return;
        document.querySelectorAll('#auditEventsTable .audit-activity-text[data-bs-toggle="tooltip"]').forEach((el) => {
          const existing = bootstrap.Tooltip.getInstance(el);
          if (existing) existing.dispose();
          bootstrap.Tooltip.getOrCreateInstance(el, {
            container: 'body',
            trigger: 'hover focus',
            boundary: document.body,
            customClass: 'audit-activity-tooltip'
          });
        });
      };

      this.auditEventsDT = $('#auditEventsTable').DataTable({
        ajax: {
          url: URLS.auditEventsAjax,
          headers: { 'X-No-Loader': '1' },
          dataSrc: 'data'
        },
        columns: [
          { data: null, title: I18N.table_number || 'No.' },
          { data: 'occurred_at' },
          { data: 'user' },
          { data: 'ip' },
          { data: 'activity' },
          { data: 'outcome' },
          { data: 'severity' },
          { data: 'actions' }
        ],
        order: [[1, 'desc']],
        pageLength: CONFIG.DATATABLES_PAGE_LENGTH,
        lengthMenu: [[5, 10, 25, 50, 100], [5, 10, 25, 50, 100]],
        language: DT.auditEventsLanguage || {},
          responsive: true,
        autoWidth: false,
        stateSave: false,
        processing: false,
        deferRender: true,
        dom: '<"row mb-2"<"col-sm-12 col-md-6 dt-top-left"l><"col-sm-12 col-md-6 d-flex justify-content-md-end dt-top-right"f>>' +
          't' +
          '<"dt-bottom-row mt-2 d-flex justify-content-between align-items-center"<"dt-info-left"i><"dt-paging-right d-flex justify-content-end"p>>',
        columnDefs: [
          { orderable: false, searchable: false, targets: [0] },
          { orderable: true, targets: [1, 4, 5, 6] },
          { orderable: false, targets: [7] },
          { className: 'text-center', targets: [0, 5, 6, 7] },
          { className: 'audit-activity-cell', targets: [4] },
          { targets: [4], render: function (data, type) { return type === 'display' ? formatAuditActivityCell(data) : $('<div>').html(data).text(); } },
          { targets: [2, 3, 5, 6, 7], render: function (data) { return data; } }
        ],
        drawCallback: function () {
          initAuditActivityTooltips();
        }
      });

      const updateAuditNumbering = function () {
        try {
          const api = $('#auditEventsTable').DataTable();
          const info = (typeof api.page === 'function' && api.page.info) ? api.page.info() : { start: 0 };
          const nodes = api.rows({ order: 'applied', page: 'current' }).nodes();
          $(nodes).each(function (i, el) {
            $(el).find('td').eq(0).html(info.start + i + 1);
          });
        } catch (err) {
          console.error('Audit numbering failed:', err);
        }
      };

      $('#auditEventsTable').on('preXhr.dt', function () {
        ProfilePage.showPageLoader('auditEvents', I18N.profile_loading || I18N.loading || 'Loading...');
      });

      this.auditEventsDT.on('xhr.dt draw.dt', function () {
        ProfilePage.hidePageLoader('auditEvents');
        updateAuditNumbering();
        initAuditActivityTooltips();
      });

      setTimeout(updateAuditNumbering, 0);
    },

    openAuditMetaModal: function (metaJson, changeSetsJson, eventId, options) {
      const modalOptions = options && typeof options === 'object' ? options : {};
      const allowFullMetadata = !!modalOptions.fullAccess;
      document.querySelectorAll('.audit-meta-modal').forEach((modalEl) => {
        try {
          const instance = window.bootstrap ? bootstrap.Modal.getInstance(modalEl) : null;
          if (instance) instance.dispose();
        } catch (e) {
          // ignore
        }
        try {
          modalEl.remove();
        } catch (e) {
          // ignore
        }
      });
      document.querySelectorAll('.modal-backdrop').forEach((backdrop) => {
        try {
          backdrop.remove();
        } catch (e) {
          // ignore
        }
      });
      document.body.classList.remove('modal-open');
      document.body.style.removeProperty('padding-right');

      try {
        console.debug('openAuditMetaModal called', eventId);
      } catch (e) {
        console.debug('openAuditMetaModal debug failed', e);
      }

      let metaObj = null;
      try { metaObj = metaJson ? JSON.parse(metaJson) : null; } catch (e) { metaObj = null; }
      let csObj = null;
      try { csObj = changeSetsJson ? JSON.parse(changeSetsJson) : null; } catch (e) { csObj = null; }

      const findChangeSets = (obj) => {
        if (!obj || typeof obj !== 'object') return null;
        const tryParse = (v) => {
          if (typeof v === 'string') {
            try { return JSON.parse(v); } catch (e) { return v; }
          }
          return v;
        };

        const looksLikeChangeArray = (a) => {
          if (!Array.isArray(a) || a.length === 0) return false;
          const sample = a[0];
          if (typeof sample !== 'object') return false;
          return ('field' in sample) || ('before' in sample) || ('after' in sample) || ('old' in sample && 'new' in sample);
        };

        const keysToTry = ['change_sets', 'changeSets', 'changes', 'change', 'change_set', 'diff', 'diffs', 'delta', 'deltas', 'changes_json', 'change_sets_json', 'payload', 'data', 'extra', 'details'];
        for (const k of keysToTry) {
          if (k in obj) {
            const v = tryParse(obj[k]);
            if (looksLikeChangeArray(v)) return v;
            if (v && typeof v === 'object' && !Array.isArray(v)) {
              const vals = Object.values(v);
              if (vals.length && (('before' in vals[0]) || ('after' in vals[0]) || ('old' in vals[0] && 'new' in vals[0]))) return v;
            }
            if (Array.isArray(v) && looksLikeChangeArray(v)) return v;
          }
        }

        for (const k of Object.keys(obj)) {
          const v = obj[k];
          if (v && typeof v === 'object') {
            const found = findChangeSets(v);
            if (found) return found;
          }
        }
        return null;
      };

      try {
        if (!csObj && metaJson) {
          const parsedMeta = metaJson ? JSON.parse(metaJson) : null;
          csObj = findChangeSets(parsedMeta) || null;
        }
      } catch (e) {
        // ignore
      }

      const renderChangeSetsTable = (cs) => {
        if (!cs) return '<div class="text-muted">' + this._escapeHtml(I18N.no_changes) + '</div>';
        const rows = [];
        const details = [];

        if (Array.isArray(cs) && cs.length > 0) {
          cs.forEach((ch, idx) => {
            const field = this._escapeHtml(ch.field || ch.key || ('field_' + idx));
            const beforeRaw = ch.before === undefined ? null : ch.before;
            const afterRaw = ch.after === undefined ? null : ch.after;
            const beforeCell = (beforeRaw === null) ? '—' : (typeof beforeRaw === 'object' ? '<span class="old-value text-break">' + this._escapeHtml(JSON.stringify(beforeRaw)) + '</span>' : '<span class="old-value text-break">' + this._escapeHtml(String(beforeRaw)) + '</span>');
            const afterCell = (afterRaw === null) ? '—' : (typeof afterRaw === 'object' ? '<span class="new-value text-break">' + this._escapeHtml(JSON.stringify(afterRaw)) + '</span>' : '<span class="new-value text-break">' + this._escapeHtml(String(afterRaw)) + '</span>');
            rows.push('<tr data-cs-idx="' + idx + '"><td class="align-middle small text-muted"><button class="btn btn-sm btn-link btn-expand-change p-0 me-2" data-idx="' + idx + '"><i class="ri-add-line"></i></button>' + field + '</td><td>' + beforeCell + '</td><td>' + afterCell + '</td></tr>');
            details.push('<tr class="cs-detail-row" data-cs-idx="' + idx + '" style="display:none;"><td colspan="3"><div class="p-2 bg-light rounded"><pre class="mb-0 small" style="white-space:pre-wrap; max-height:220px; overflow:auto;">' + this._escapeHtml(I18N.before_raw_label) + ':\n' + this._escapeHtml(JSON.stringify(beforeRaw, null, 2)) + '\n\n' + this._escapeHtml(I18N.after_raw_label) + ':\n' + this._escapeHtml(JSON.stringify(afterRaw, null, 2)) + '</pre></div></td></tr>');
          });
        } else if (typeof cs === 'object') {
          Object.keys(cs).forEach((k, idx) => {
            const entry = cs[k] || {};
            const beforeRaw = entry.before === undefined ? null : entry.before;
            const afterRaw = entry.after === undefined ? null : entry.after;
            const beforeCell = (beforeRaw === null) ? '—' : (typeof beforeRaw === 'object' ? '<span class="old-value text-break">' + this._escapeHtml(JSON.stringify(beforeRaw)) + '</span>' : '<span class="old-value text-break">' + this._escapeHtml(String(beforeRaw)) + '</span>');
            const afterCell = (afterRaw === null) ? '—' : (typeof afterRaw === 'object' ? '<span class="new-value text-break">' + this._escapeHtml(JSON.stringify(afterRaw)) + '</span>' : '<span class="new-value text-break">' + this._escapeHtml(String(afterRaw)) + '</span>');
            rows.push('<tr data-cs-idx="' + idx + '"><td class="align-middle small text-muted"><button class="btn btn-sm btn-link btn-expand-change p-0 me-2" data-idx="' + idx + '"><i class="ri-add-line"></i></button>' + this._escapeHtml(k) + '</td><td>' + beforeCell + '</td><td>' + afterCell + '</td></tr>');
            details.push('<tr class="cs-detail-row" data-cs-idx="' + idx + '" style="display:none;"><td colspan="3"><div class="p-2 bg-light rounded"><pre class="mb-0 small" style="white-space:pre-wrap; max-height:220px; overflow:auto;">' + this._escapeHtml(I18N.before_raw_label) + ':\n' + this._escapeHtml(JSON.stringify(beforeRaw, null, 2)) + '\n\n' + this._escapeHtml(I18N.after_raw_label) + ':\n' + this._escapeHtml(JSON.stringify(afterRaw, null, 2)) + '</pre></div></td></tr>');
          });
        }

        if (!rows.length) return '<div class="text-muted">' + this._escapeHtml(I18N.no_changes) + '</div>';
        return '<div class="table-responsive"><table class="table table-sm table-bordered audit-changes-table mb-0"><thead><tr><th class="small text-muted">' + this._escapeHtml(I18N.field_label) + '</th><th class="small text-muted">' + this._escapeHtml(I18N.before_label) + '</th><th class="small text-muted">' + this._escapeHtml(I18N.after_label) + '</th></tr></thead><tbody>' + rows.join('') + details.join('') + '</tbody></table></div>';
      };

      const modalId = 'auditMetaDynamic-' + (eventId || Date.now());
      const prettyMeta = metaObj ? JSON.stringify(metaObj, null, 2) : '';
      const prettyCs = csObj ? JSON.stringify(csObj, null, 2) : '';
      const metaFieldCount = metaObj && typeof metaObj === 'object' ? Object.keys(metaObj).length : 0;
      const changeSetCount = Array.isArray(csObj) ? csObj.length : (csObj && typeof csObj === 'object' ? Object.keys(csObj).length : 0);
      const changeFieldCount = (() => {
        if (!csObj) return 0;
        if (Array.isArray(csObj)) return csObj.length;
        if (typeof csObj === 'object') return Object.keys(csObj).length;
        return 0;
      })();

      const modalHtml = `
        <div class="modal fade audit-meta-modal" id="${modalId}" tabindex="-1" aria-hidden="true">
          <div class="modal-dialog modal-xl modal-dialog-centered">
            <div class="modal-content">
              <div class="modal-header">
                <div class="audit-header-main">
                  <div class="audit-header-icon">
                    <i class="ri-file-search-line"></i>
                  </div>
                  <div class="audit-header-copy">
                    <div class="audit-kicker">${this._escapeHtml(I18N.audit_tab_summary)}</div>
                    <h5 class="modal-title mb-0">${this._escapeHtml(I18N.audit_title)}</h5>
                    <div class="audit-subtitle">${this._escapeHtml(I18N.audit_event_id)}: ${this._escapeHtml(String(eventId || '—'))}</div>
                  </div>
                </div>
                <div class="audit-header-stats">
                  <div class="audit-mini-stat">
                    <span class="audit-mini-stat-label">${this._escapeHtml(I18N.audit_stat_meta)}</span>
                    <strong>${this._escapeHtml(String(metaFieldCount))}</strong>
                  </div>
                  <div class="audit-mini-stat">
                    <span class="audit-mini-stat-label">${this._escapeHtml(I18N.audit_stat_sets)}</span>
                    <strong>${this._escapeHtml(String(changeSetCount))}</strong>
                  </div>
                  <div class="audit-mini-stat">
                    <span class="audit-mini-stat-label">${this._escapeHtml(I18N.audit_stat_changes)}</span>
                    <strong>${this._escapeHtml(String(changeFieldCount))}</strong>
                  </div>
                </div>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="${this._escapeHtml(I18N.close_label)}"></button>
              </div>

              <div class="modal-body p-0">
                <div class="row g-0">
                  <div class="col-lg-5 audit-left">
                    <div class="p-4">
                      <div class="audit-title">${this._escapeHtml(I18N.audit_summary_short)}</div>
                      <div class="audit-summary-card audit-summary-shell mb-3">
                        <div class="audit-summary-strip">
                          <span class="audit-chip"><i class="ri-database-2-line"></i> ${this._escapeHtml(I18N.audit_extra_info)}</span>
                          <span class="audit-chip"><i class="ri-loop-left-line"></i> ${this._escapeHtml(I18N.audit_tab_changes)}</span>
                        </div>
                        <div class="audit-summary-note">${this._escapeHtml(I18N.audit_no_info && metaFieldCount === 0 ? I18N.audit_no_info : I18N.audit_summary_short)}</div>
                      </div>
                      <div class="audit-summary-card">
                        <div class="table-responsive">
                          <table class="table table-sm audit-meta-table mb-0">
                            <tbody>
                              ${(() => {
                                try {
                                  if (!metaObj || typeof metaObj !== 'object') return '<tr><td class="text-muted">' + this._escapeHtml(I18N.audit_no_info) + '</td><td>—</td></tr>';
                                  const keys = ['occurred_at', 'module', 'action', 'ip', 'user_agent', 'device', 'browser', 'user_id', 'nopek'];
                                  const present = keys.filter((k) => metaObj[k] !== undefined).concat(Object.keys(metaObj).filter((k) => keys.indexOf(k) === -1));
                                  return present.slice(0, 12).map((k) => '<tr><td>' + this._escapeHtml(k) + '</td><td>' + this._escapeHtml(String(metaObj[k] === undefined || metaObj[k] === null || metaObj[k] === '' ? '—' : (typeof metaObj[k] === 'object' ? JSON.stringify(metaObj[k]) : metaObj[k]))) + '</td></tr>').join('');
                                } catch (e) {
                                  return '<tr><td class="text-muted">' + this._escapeHtml(I18N.data_label) + '</td><td>—</td></tr>';
                                }
                              })()}
                            </tbody>
                          </table>
                        </div>
                      </div>
                    </div>
                  </div>

                  <div class="col-lg-7 audit-right">
                    <div class="p-3 p-lg-4">
                      <ul class="nav audit-tabs mb-3" role="tablist">
                        <li class="nav-item" role="presentation"><button class="nav-link active" id="${modalId}-tab-summary" data-bs-toggle="tab" data-bs-target="#${modalId}-summary" type="button" role="tab">${this._escapeHtml(I18N.audit_tab_summary)}</button></li>
                        <li class="nav-item" role="presentation"><button class="nav-link" id="${modalId}-tab-changes" data-bs-toggle="tab" data-bs-target="#${modalId}-changes" type="button" role="tab">${this._escapeHtml(I18N.audit_tab_changes)}</button></li>
                        ${allowFullMetadata ? '<li class="nav-item" role="presentation"><button class="nav-link" id="' + modalId + '-tab-extra" data-bs-toggle="tab" data-bs-target="#' + modalId + '-extra" type="button" role="tab">' + this._escapeHtml(I18N.audit_tab_extra) + '</button></li>' : ''}
                        ${allowFullMetadata ? '<li class="nav-item" role="presentation"><button class="nav-link" id="' + modalId + '-tab-raw" data-bs-toggle="tab" data-bs-target="#' + modalId + '-raw" type="button" role="tab">' + this._escapeHtml(I18N.audit_tab_raw) + '</button></li>' : ''}
                      </ul>

                      <div class="tab-content">
                        <div class="tab-pane fade show active" id="${modalId}-summary" role="tabpanel">
                          <div class="audit-pane-card">
                            <div class="audit-title">${this._escapeHtml(I18N.audit_primary_changes)}</div>
                            <div class="audit-section-subtitle">${this._escapeHtml(I18N.audit_tab_changes)}</div>
                            <div id="${modalId}-summary-changes">${renderChangeSetsTable(csObj)}</div>
                          </div>
                        </div>

                        <div class="tab-pane fade" id="${modalId}-changes" role="tabpanel">
                          <div class="audit-pane-card">
                            <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-2">
                              <div class="input-group input-group-sm" style="max-width:300px;">
                                <span class="input-group-text"><i class="ri-search-line"></i></span>
                                <input id="${modalId}-changes-search" class="form-control" placeholder="${this._escapeHtml(I18N.audit_search_changes)}">
                              </div>
                            </div>
                            <div id="${modalId}-cs-table">${renderChangeSetsTable(csObj)}</div>
                          </div>
                        </div>

                        ${allowFullMetadata ? '<div class="tab-pane fade" id="' + modalId + '-extra" role="tabpanel">' : ''}
                        ${allowFullMetadata ? `
                          <div class="audit-pane-card">
                            <div class="audit-title">${this._escapeHtml(I18N.audit_extra_info)}</div>
                            <div class="audit-section-subtitle">${this._escapeHtml(I18N.audit_summary_short)}</div>
                            <div class="json-block">${this._escapeHtml(prettyMeta)}</div>
                          </div>
                        </div>
                        ` : ''}

                        ${allowFullMetadata ? '<div class="tab-pane fade" id="' + modalId + '-raw" role="tabpanel">' : ''}
                        ${allowFullMetadata ? `
                          <div class="audit-pane-card">
                            <div class="audit-title">${this._escapeHtml(I18N.audit_raw_data)}</div>
                            <pre class="json-block mb-0">${this._escapeHtml(prettyMeta)}\n\n${this._escapeHtml(I18N.audit_changes_separator || '-- Changes --')}\n\n${this._escapeHtml(prettyCs)}</pre>
                          </div>
                        </div>
                        ` : ''}
                      </div>
                    </div>
                  </div>
                </div>
              </div>

              <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">${this._escapeHtml(I18N.close)}</button>
              </div>
            </div>
          </div>
        </div>
      `;

      const wrapper = document.createElement('div');
      wrapper.innerHTML = modalHtml;
      while (wrapper.firstChild) {
        document.body.appendChild(wrapper.firstChild);
      }
      const createdModal = document.getElementById(modalId);

      try {
        if (createdModal) {
          const toggle = (btnSelector, targetSelector) => {
            createdModal.querySelectorAll(btnSelector).forEach((b) => {
              b.addEventListener('click', (ev) => {
                ev.preventDefault();
                const t = createdModal.querySelector(targetSelector);
                const formatted = createdModal.querySelector(targetSelector.replace('-raw', '-formatted'));
                if (!t) return;
                if (t.style.display === 'none') {
                  t.style.display = '';
                  if (formatted) formatted.style.display = 'none';
                } else {
                  t.style.display = 'none';
                  if (formatted) formatted.style.display = '';
                }
              });
            });
          };
          toggle('.btn-toggle-meta-raw', '#' + modalId + '-meta-raw');
          toggle('.btn-toggle-cs-raw', '#' + modalId + '-cs-raw');

          const searchInput = createdModal.querySelector('#' + modalId + '-changes-search');
          if (searchInput) {
            searchInput.addEventListener('input', function () {
              const q = this.value.trim().toLowerCase();
              const table = createdModal.querySelector('.audit-changes-table');
              if (!table) return;
              const allRows = Array.from(table.tBodies[0].rows).filter((r) => !r.classList.contains('cs-detail-row'));
              allRows.forEach((r) => {
                const text = r.textContent.toLowerCase();
                r.style.display = q === '' || text.indexOf(q) !== -1 ? '' : 'none';
                const idx = r.getAttribute('data-cs-idx');
                const detail = createdModal.querySelector('.cs-detail-row[data-cs-idx="' + idx + '"]');
                if (detail) detail.style.display = r.style.display === 'none' ? 'none' : detail.style.display;
              });
            });
          }

          createdModal.addEventListener('click', (ev) => {
            const btn = ev.target.closest('.btn-expand-change');
            if (!btn) return;
            ev.preventDefault();
            const idx = btn.getAttribute('data-idx');
            const detailRow = createdModal.querySelector('.cs-detail-row[data-cs-idx="' + idx + '"]');
            if (!detailRow) return;
            const icon = btn.querySelector('i');
            if (detailRow.style.display === 'none') {
              detailRow.style.display = '';
              if (icon) {
                icon.classList.remove('ri-add-line');
                icon.classList.add('ri-subtract-line');
              }
            } else {
              detailRow.style.display = 'none';
              if (icon) {
                icon.classList.remove('ri-subtract-line');
                icon.classList.add('ri-add-line');
              }
            }
          });

          createdModal.querySelectorAll('.btn-download-json').forEach((b) => {
            b.addEventListener('click', (e) => {
              e.preventDefault();
              const payload = b.getAttribute('data-json') || '{}';
              try {
                const blob = new Blob([decodeURIComponent(payload)], { type: 'application/json' });
                const url = URL.createObjectURL(blob);
                const a = document.createElement('a');
                a.href = url;
                a.download = (b.getAttribute('data-fname') || 'data') + '.json';
                document.body.appendChild(a);
                a.click();
                a.remove();
                URL.revokeObjectURL(url);
              } catch (err) {
                console.error('Download failed', err);
                this.toast(I18N.audit_download_failed || 'Failed to download JSON file', 'error');
              }
            });
          });

          createdModal.addEventListener('hidden.bs.modal', () => {
            try {
              createdModal.remove();
            } catch (e) {
              // ignore
            }
          });
        }
      } catch (e) {
        console.error('Modal init error', e);
      }

      try {
        const bsModal = new bootstrap.Modal(createdModal, { backdrop: true, keyboard: true, focus: true });
        bsModal.show();
      } catch (e) {
        console.error('Failed to show audit meta modal:', e);
      }
    },

    refreshProfile: async function () {
      if (this.isLoading) return;

      const $btn = $('.btn-refresh-profile');
      const originalHtml = $btn.html();

      $btn.prop('disabled', true);
      $btn.html('<i class="ri-loader-4-line ri-spin"></i>');
      this.showPageLoader('refreshProfile', I18N.profile_loading || I18N.loading || 'Loading...');

      try {
        window.location.reload();
      } catch (e) {
        console.error('Refresh failed:', e);
        this.toast(I18N.refresh_failed, 'error');
        $btn.prop('disabled', false);
        $btn.html(originalHtml);
        this.hidePageLoader('refreshProfile');
      }
    },

    init: function () {
      document.querySelectorAll('.btn-copy-staf, .btn-copy-email').forEach((btn) => {
        btn.addEventListener('click', (e) => {
          e.preventDefault();
          const text = btn.dataset.copyValue;
          if (text) this.copyText(text);
        });
        if (window.DataTableStandard && typeof window.DataTableStandard.decorate === 'function') {
          window.DataTableStandard.decorate('#auditEventsTable', {
            searchPlaceholder: (DT.auditEventsLanguage && DT.auditEventsLanguage.search) || ''
          });
        }
      });

      document.querySelectorAll('.btn-copy-meta-modal').forEach((btn) => {
        btn.addEventListener('click', (e) => {
          e.preventDefault();
          e.stopPropagation();
          const metaJson = btn.dataset.metaJson;
          if (!metaJson) return;
          try {
            const decoded = decodeURIComponent(metaJson);
            this.copyText(decoded);
          } catch (err) {
            this.copyText(metaJson);
          }
        });
      });

      document.addEventListener('click', (e) => {
        if (e.target.closest('.btn-kill-session')) {
          e.preventDefault();
          e.stopPropagation();
          const btn = e.target.closest('.btn-kill-session');
          const sessionId = btn.dataset.sessionId;
          if (sessionId) this.killSession(sessionId);
        }
      });

      document.addEventListener('click', (e) => {
        const btn = e.target.closest('.btn-open-audit-modal');
        if (!btn) return;
        e.preventDefault();
        e.stopPropagation();
        const payload = btn.getAttribute('data-event-payload') || '';
        if (!payload) return;
        try {
          const jsonStr = atob(payload);
          const obj = JSON.parse(jsonStr);
          const metaJson = obj.meta ? JSON.stringify(obj.meta) : '';
          const changeSets = obj.change_sets ? JSON.stringify(obj.change_sets) : '';
          const eventId = obj.id || obj.event_id || '';
          this.openAuditMetaModal(metaJson, changeSets, eventId, {
            fullAccess: !!PERMISSIONS.canViewAuditMetadata
          });
        } catch (err) {
          console.error('Failed to decode audit payload', err);
        }
      });

      document.addEventListener('click', (e) => {
        const btn = e.target.closest('.btn-open-audit-meta');
        if (!btn) return;
        e.preventDefault();
        e.stopPropagation();
        const eventId = btn.getAttribute('data-event-id') || btn.dataset.eventId || '';
        if (!eventId) return;

        fetch(URLS.auditEventMetaAjax + '?event_id=' + encodeURIComponent(eventId), {
          method: 'GET',
          headers: { 'X-Requested-With': 'XMLHttpRequest' }
        }).then(async (r) => {
          const data = await r.json().catch(() => ({}));
          if (!r.ok) {
            const err = new Error((data && data.message) || I18N.metadata_load_fail);
            err.status = r.status;
            err.payload = data;
            throw err;
          }
          return data;
        }).then((data) => {
          const metaJson = data.meta ? JSON.stringify(data.meta) : '';
          const changeSets = data.change_sets ? JSON.stringify(data.change_sets) : '';
          this.openAuditMetaModal(metaJson, changeSets, eventId, {
            fullAccess: !!data.allow_full_metadata
          });
        }).catch((err) => {
          console.error('Failed to load audit meta for event', eventId, err);
          if (err && err.status === 403 && window.Swal && typeof Swal.fire === 'function') {
            Swal.fire({
              icon: 'warning',
              title: I18N.metadata_forbidden_title || 'Akses Ditolak',
              text: (err.payload && err.payload.message) || I18N.metadata_forbidden_text || I18N.metadata_load_fail
            });
            return;
          }
          this.toast((err && err.message) || I18N.metadata_load_fail, 'error');
        });
      });

      document.addEventListener('click', (e) => {
        const copyBtn = e.target.closest('.btn-copy-meta-modal');
        if (!copyBtn) return;
        e.preventDefault();
        e.stopPropagation();
        const meta = copyBtn.getAttribute('data-meta-json') || '';
        if (meta) this.copyText(meta);
      });

      $('a[data-bs-toggle="tab"][href="#login-aktiviti-tab"]').on('shown.bs.tab', () => {
        this.waitForDataTables().then(() => {
          setTimeout(() => this.initLoginActivityTable(), CONFIG.DATATABLES_INIT_DELAY);
        }).catch((e) => {
          console.error('DataTables init failed:', e);
        });
      });

      $('a[data-bs-toggle="tab"][href="#jejak-audit-tab"]').on('shown.bs.tab', () => {
        this.waitForDataTables().then(() => {
          setTimeout(() => this.initAuditEventsTable(), CONFIG.DATATABLES_INIT_DELAY);
        }).catch((e) => {
          console.error('DataTables init failed:', e);
        });
      });

      $(document).ready(async () => {
        try {
          await this.waitForDataTables();
          setTimeout(() => {
            this.initLoginActivityTable();
            this.initAuditEventsTable();
          }, CONFIG.DATATABLES_INIT_DELAY);
        } catch (e) {
          console.error('DataTables initialization failed:', e);
          this.toast(I18N.dt_load_failed, 'error');
        }
      });
    }
  };

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => ProfilePage.init());
  } else {
    ProfilePage.init();
  }

  window.copyText = function (text) { return ProfilePage.copyText(text); };
  window.toast = function (msg, type) { return ProfilePage.toast(msg, type); };
})();
