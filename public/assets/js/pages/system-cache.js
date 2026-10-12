(function () {
  'use strict';
  document.addEventListener('DOMContentLoaded', function () {
    const data = window.SystemCachePageData || {};
    const text = data.text || {};
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';
    const selectAll = document.getElementById('selectAllCache');
    const checks = Array.from(document.querySelectorAll('.cache-location-check'));
    const selectedButton = document.getElementById('btnClearSelected');
    const allButton = document.getElementById('btnClearAll');
    let running = false;

    if (window.jQuery && jQuery.fn.DataTable && checks.length) {
      jQuery('#systemCacheTable').DataTable({
        pageLength: 10,
        lengthMenu: [10, 25, 50, 100],
        order: [[1, 'asc']],
        autoWidth: false,
        responsive: false,
        dom: '<"row mb-2"<"col-sm-12 col-md-6 dt-top-left"l><"col-sm-12 col-md-6 d-flex justify-content-md-end dt-top-right"f>>' +
          't' +
          '<"dt-bottom-row mt-2 d-flex justify-content-between align-items-center"<"dt-info-left"i><"dt-paging-right d-flex justify-content-end"p>>',
        columnDefs: [{ targets: 0, orderable: false, searchable: false }],
        language: window.DataTableStandard?.language?.() || {}
      });
      window.DataTableStandard?.decorate?.('#systemCacheTable');
    }

    function selectedChecks() { return checks.filter((check) => check.checked); }
    function updateSelection() {
      const count = selectedChecks().length;
      if (selectedButton) selectedButton.disabled = running || count === 0;
      if (allButton) allButton.disabled = running || checks.length === 0;
      if (selectAll) { selectAll.checked = checks.length > 0 && count === checks.length; selectAll.indeterminate = count > 0 && count < checks.length; selectAll.disabled = running; }
      const counter = document.getElementById('systemCacheSelectionCount');
      if (counter) counter.textContent = count === 0 ? text.selectedNone : (count === 1 ? text.selectedOne : String(text.selectedMany || '').replace('{count}', String(count)));
    }
    selectAll?.addEventListener('change', () => { checks.forEach((check) => { check.checked = selectAll.checked; }); updateSelection(); });
    checks.forEach((check) => check.addEventListener('change', updateSelection));
    selectedButton?.addEventListener('click', () => { const ids = selectedChecks().map((check) => check.value); if (ids.length) confirmAndClear('selected', ids); });
    allButton?.addEventListener('click', () => confirmAndClear('all', []));
    updateSelection();

    async function confirmAndClear(scope, ids) {
      if (running) return;
      const confirmed = window.Swal ? (await Swal.fire({ icon: 'warning', title: text.confirmTitle, text: scope === 'all' ? text.allConfirm : text.selectedConfirm, showCancelButton: true, confirmButtonText: text.clear, cancelButtonText: text.cancel, confirmButtonColor: '#dc3545', focusCancel: true })).isConfirmed : window.confirm(text.confirmTitle);
      if (!confirmed) return;
      running = true; updateSelection();
      const loader = showLoader(text.loading);
      let loaderVisible = true;
      const form = new FormData();
      form.set('action', 'clear'); form.set('scope', scope); form.set('csrf_token', csrfToken);
      ids.forEach((id) => form.append('locations[]', id));
      try {
        const response = await fetch(data.actionUrl, { method: 'POST', headers: { 'X-CSRF-Token': csrfToken, Accept: 'application/json' }, body: form });
        const payload = await response.json();
        if (!response.ok || payload.error || payload.success === false) throw new Error(payload.message || text.error);
        const result = payload.result || {};
        applyResult(result);
        hideLoader(loader); loaderVisible = false;
        await waitForLoaderExit();
        await showResult(result);
      } catch (error) {
        if (loaderVisible) { hideLoader(loader); loaderVisible = false; await waitForLoaderExit(); }
        await showAlert('error', text.error, error?.message || text.error);
      } finally {
        if (loaderVisible) hideLoader(loader);
        running = false; updateSelection();
      }
    }

    function applyResult(result) {
      (Array.isArray(result.locations_cleared) ? result.locations_cleared : []).forEach((location) => {
        const row = document.querySelector('tr[data-cache-location-id="' + cssEscape(location.id || '') + '"]');
        if (!row) return;
        const check = row.querySelector('.cache-location-check'); if (check) check.checked = false;
        const files = row.querySelector('[data-cache-files]'); if (files) files.textContent = '0';
        const size = row.querySelector('[data-cache-size]'); if (size) { size.textContent = '0 B'; size.dataset.cacheBytes = '0'; }
        const modified = row.querySelector('[data-cache-modified]'); if (modified) modified.textContent = '-';
      });
    }

    function showResult(result) {
      const errors = Array.isArray(result.errors) ? result.errors : [];
      const partial = errors.length > 0 || (result.opcache?.available && !result.opcache?.success) || (result.apcu?.available && !result.apcu?.success);
      const locations = Array.isArray(result.locations_cleared) ? result.locations_cleared.length : 0;
      const rows = [[text.resultFiles, result.files_removed || 0], [text.resultSize, result.freed_size || '0 B'], [text.resultLocations, locations]].map((row) => '<div class="sc-result-item"><span>' + escapeHtml(row[0]) + '</span><strong>' + escapeHtml(row[1]) + '</strong></div>').join('');
      const warning = errors.length ? '<div class="sc-result-warning"><strong>' + escapeHtml(text.resultErrors) + ': ' + errors.length + '</strong></div>' : '';
      if (window.Swal) return Swal.fire({ icon: partial ? 'warning' : 'success', title: partial ? text.partial : text.success, html: '<div class="sc-result-grid">' + rows + '</div>' + warning + '<p class="sc-result-note">' + escapeHtml(text.note) + '</p>', confirmButtonText: text.close, customClass: { popup: 'sc-result-popup' } });
      return showAlert(partial ? 'warning' : 'success', partial ? text.partial : text.success, text.note);
    }
    function showAlert(icon, title, message) { return window.Swal ? Swal.fire({ icon, title, text: message }) : Promise.resolve(window.alert(title + '\n\n' + message)); }
    function showLoader(message) { if (window.AppLoader?.show) return window.AppLoader.show(message, { timeout: 60000 }); if (window.IQSLoader?.show) return window.IQSLoader.show(message, { timeout: 60000 }); return null; }
    function hideLoader(token) { if (window.AppLoader?.hide) window.AppLoader.hide(token); else if (window.IQSLoader?.hide) window.IQSLoader.hide(token); }
    function waitForLoaderExit() {
      if (!document.getElementById('iqs-box-loader')) return Promise.resolve();
      return new Promise((resolve) => {
        let observer = null;
        const timeout = window.setTimeout(() => { observer?.disconnect(); resolve(); }, 650);
        observer = new MutationObserver(() => {
          if (document.getElementById('iqs-box-loader')) return;
          window.clearTimeout(timeout); observer.disconnect(); resolve();
        });
        observer.observe(document.body, { childList: true });
      });
    }
    function cssEscape(value) { return window.CSS?.escape ? window.CSS.escape(String(value)) : String(value).replace(/"/g, '\\"'); }
    function escapeHtml(value) { return String(value ?? '').replace(/[&<>'"]/g, (character) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', "'": '&#039;', '"': '&quot;' }[character])); }
  });
})();
