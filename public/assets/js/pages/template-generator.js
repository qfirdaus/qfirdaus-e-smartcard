(function () {
    function safeJsonParse(value) {
        try {
            return JSON.parse(value);
        } catch (error) {
            return null;
        }
    }

    document.addEventListener('DOMContentLoaded', function () {
        if (window.jQuery && jQuery.fn && jQuery.fn.DataTable && !jQuery.fn.dataTable.isDataTable('#templateGeneratorDT')) {
            var templateTable = jQuery('#templateGeneratorDT').DataTable({
                autoWidth: false,
                pageLength: 10,
                lengthMenu: [10, 25, 50, 100],
                ordering: true,
                responsive: false,
                order: [[1, 'asc']],
                columnDefs: [
                    { targets: [0, 6], orderable: false }
                ],
                language: (window.DataTableStandard && typeof window.DataTableStandard.language === 'function')
                    ? window.DataTableStandard.language()
                    : {},
                initComplete: function () {
                    jQuery('#templateGeneratorDT thead th.col-bil, #templateGeneratorDT thead th.col-actions')
                        .removeClass('sorting sorting_asc sorting_desc')
                        .addClass('sorting_disabled')
                        .attr('aria-sort', 'none');
                }
            });

            templateTable.on('order.dt search.dt draw.dt', function () {
                var info = templateTable.page.info();
                templateTable.column(0, { search: 'applied', order: 'applied', page: 'current' }).nodes().each(function (cell, index) {
                    cell.textContent = info.start + index + 1;
                });
                jQuery('#templateGeneratorDT thead th.col-bil, #templateGeneratorDT thead th.col-actions')
                    .removeClass('sorting sorting_asc sorting_desc')
                    .addClass('sorting_disabled')
                    .attr('aria-sort', 'none');
            }).draw();

            if (window.DataTableStandard && typeof window.DataTableStandard.decorate === 'function') {
                window.DataTableStandard.decorate('#templateGeneratorDT', {
                    controlsClass: 'mb-3'
                });
            }
        }

        var createModalEl = document.getElementById('createTemplateModal');
        var detailModalEl = document.getElementById('templateDetailModal');
        var createForm = document.getElementById('templateGeneratorForm');
        var createModal = createModalEl && window.bootstrap ? bootstrap.Modal.getOrCreateInstance(createModalEl) : null;
        var accessTabTriggerEl = document.getElementById('template-generator-access-tab');

        function showAccessModeTabIfNeeded() {
            if (!accessTabTriggerEl || !window.bootstrap) {
                return;
            }

            var hasAccessModeError = !!document.querySelector('#template-generator-access-pane .text-danger');
            if (!hasAccessModeError) {
                return;
            }

            bootstrap.Tab.getOrCreateInstance(accessTabTriggerEl).show();
        }

        if (createForm) {
            createForm.addEventListener('submit', function (event) {
                if (!createForm.checkValidity()) {
                    event.preventDefault();
                    event.stopPropagation();
                    createForm.reportValidity();
                }
            });
        }

        if (detailModalEl) {
            detailModalEl.addEventListener('show.bs.modal', function (event) {
                var trigger = event.relatedTarget;
                var payload = trigger ? safeJsonParse(trigger.getAttribute('data-template') || '{}') : null;
                if (!payload) {
                    return;
                }

                var mappings = {
                    '[data-detail-template-name]': payload.template_name || '-',
                    '[data-detail-template-type]': payload.template_type || '-',
                    '[data-detail-page-slug]': payload.page_slug || '-',
                    '[data-detail-controller-class]': payload.controller_class || '-',
                    '[data-detail-access-mode]': ((window.TemplateGeneratorPageData || {}).accessModeLabels || {})[payload.access_mode] || payload.access_mode || '-',
                    '[data-detail-status]': payload.status || '-',
                    '[data-detail-updated-at]': payload.updated_at || '-',
                    '[data-detail-update-by]': payload.update_by || '-',
                    '[data-detail-page-path]': payload.page_path || '-',
                    '[data-detail-controller-path]': payload.controller_path || '-',
                    '[data-detail-css-path]': payload.css_path || '-'
                };

                Object.keys(mappings).forEach(function (selector) {
                    var node = detailModalEl.querySelector(selector);
                    if (node) {
                        node.textContent = mappings[selector];
                    }
                });

                detailModalEl.dataset.templatePayload = JSON.stringify(payload);
            });

            detailModalEl.querySelectorAll('[data-copy-detail]').forEach(function (button) {
                button.addEventListener('click', function () {
                    var payload = safeJsonParse(detailModalEl.dataset.templatePayload || '{}') || {};
                    var value = payload[button.getAttribute('data-copy-detail')] || '';
                    var pageData = window.TemplateGeneratorPageData || {};
                    if (!value || !navigator.clipboard) {
                        if (window.Swal) Swal.fire({ icon: 'error', text: pageData.copyFailed || 'Unable to copy path' });
                        return;
                    }
                    navigator.clipboard.writeText(value).then(function () {
                        button.classList.add('is-copied');
                        setTimeout(function () { button.classList.remove('is-copied'); }, 1200);
                        if (window.Swal) Swal.fire({ toast: true, position: 'top-end', timer: 1400, showConfirmButton: false, icon: 'success', title: pageData.copySuccess || 'Path copied' });
                    }).catch(function () {
                        if (window.Swal) Swal.fire({ icon: 'error', text: pageData.copyFailed || 'Unable to copy path' });
                    });
                });
            });
        }

        var previewIcon = document.querySelector('[data-preview-icon]');
        document.querySelectorAll('input[name="page_icon"]').forEach(function (radio) {
            radio.addEventListener('change', function () {
                if (!previewIcon || !radio.checked) {
                    return;
                }
                previewIcon.className = radio.value;
            });
        });

        document.querySelectorAll('[data-preview-toggle="meta"]').forEach(function (button) {
            button.addEventListener('click', function () {
                var section = document.querySelector('[data-preview-section="meta"]');
                if (!section) {
                    return;
                }

                var isHidden = section.classList.contains('d-none');
                section.classList.toggle('d-none', !isHidden);
                button.setAttribute('aria-expanded', isHidden ? 'true' : 'false');
                var pageData = window.TemplateGeneratorPageData || {};
                var showLabel = pageData.previewToggleShow || 'Show Preview Meta';
                var hideLabel = pageData.previewToggleHide || 'Hide Preview Meta';
                button.innerHTML = isHidden
                    ? '<i class="ri-eye-line me-1"></i>' + hideLabel
                    : '<i class="ri-eye-off-line me-1"></i>' + showLabel;
            });
        });

        var pageData = window.TemplateGeneratorPageData || {};
        if (pageData.shouldOpenCreateModal && createModal) {
            createModal.show();
            showAccessModeTabIfNeeded();
        }

        if (pageData.hasSuccess && window.Swal) {
            var fileList = Array.isArray(pageData.successFiles) && pageData.successFiles.length
                ? '<ul class="mt-2 mb-0 ps-3">' + pageData.successFiles.map(function (file) {
                    return '<li class="small">' + String(file)
                        .replace(/&/g, '&amp;')
                        .replace(/</g, '&lt;')
                        .replace(/>/g, '&gt;')
                        .replace(/"/g, '&quot;')
                        .replace(/'/g, '&#039;') + '</li>';
                }).join('') + '</ul>'
                : '';

            Swal.fire({
                icon: 'success',
                title: pageData.successTitle || 'Success',
                html: '<div class="text-start"><div>' + (pageData.successText || '') + '</div>' + fileList + '</div>',
                confirmButtonText: pageData.closeLabel || 'OK',
                customClass: {
                    popup: 'swal2-template-popup',
                    confirmButton: 'swal2-template-confirm'
                }
            });
        }
    });
})();
