(function () {
    function normalizeJsonInput(value) {
        return String(value || '')
            .replace(/^\uFEFF/, '')
            .replace(/\u00A0/g, ' ')
            .replace(/[\u2018\u2019]/g, '\'')
            .replace(/[\u201C\u201D]/g, '"')
            .replace(/,\s*([}\]])/g, '$1')
            .trim();
    }

    function safeJsonParse(value) {
        try {
            return JSON.parse(normalizeJsonInput(value));
        } catch (error) {
            return null;
        }
    }

    function setFieldValue(form, field, value) {
        var input = form.querySelector('[data-field="' + field + '"]');
        if (!input) {
            return;
        }

        if (input.type === 'checkbox') {
            input.checked = !!Number(value) || value === true;
            return;
        }

        input.value = value == null ? '' : String(value);
    }

    function resetForm(form) {
        form.reset();
        setFieldValue(form, 'template_id', 0);
        setFieldValue(form, 'status', 'DRAFT');
        setFieldValue(form, 'is_default', 0);
        form.classList.remove('was-validated');
        clearFieldErrors(form);
    }

    function fillForm(form, payload) {
        Object.keys(payload).forEach(function (key) {
            setFieldValue(form, key, payload[key]);
        });
    }

    function clearFieldErrors(form) {
        if (!form) {
            return;
        }

        form.querySelectorAll('.is-invalid').forEach(function (field) {
            field.classList.remove('is-invalid');
        });

        form.querySelectorAll('.invalid-feedback[data-dynamic-error="1"]').forEach(function (node) {
            node.remove();
        });
    }

    function applyFieldErrors(form, fieldErrors) {
        clearFieldErrors(form);
        if (!form || !fieldErrors || typeof fieldErrors !== 'object') {
            return;
        }

        Object.keys(fieldErrors).forEach(function (fieldName) {
            var input = form.querySelector('[name="' + fieldName + '"]');
            if (!input) {
                return;
            }
            input.classList.add('is-invalid');

            var existing = input.parentNode ? input.parentNode.querySelector('.invalid-feedback') : null;
            if (existing) {
                existing.textContent = String(fieldErrors[fieldName] || '');
                return;
            }

            var feedback = document.createElement('div');
            feedback.className = 'invalid-feedback';
            feedback.setAttribute('data-dynamic-error', '1');
            feedback.textContent = String(fieldErrors[fieldName] || '');
            input.insertAdjacentElement('afterend', feedback);
        });
    }

    function insertAtCursor(field, text) {
        if (!field) {
            return;
        }

        var start = field.selectionStart || 0;
        var end = field.selectionEnd || 0;
        var currentValue = field.value || '';
        field.value = currentValue.slice(0, start) + text + currentValue.slice(end);
        field.focus();
        var nextPosition = start + text.length;
        field.setSelectionRange(nextPosition, nextPosition);
        field.dispatchEvent(new Event('input', { bubbles: true }));
    }

    function setButtonLoading(button, loading, fallbackLabel) {
        if (!button) {
            return;
        }

        var pageData = window.EmailTemplatePageData || {};

        if (loading) {
            button.disabled = true;
            if (!button.dataset.originalHtml) {
                button.dataset.originalHtml = button.innerHTML;
            }
            button.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>' + (fallbackLabel || pageData.loadingProcessingText || '');
            return;
        }

        button.disabled = false;
        if (button.dataset.originalHtml) {
            button.innerHTML = button.dataset.originalHtml;
            delete button.dataset.originalHtml;
        }
    }

    function setInlineButtonLoading(button) {
        if (!button) {
            return;
        }
        if (!button.dataset.originalHtml) {
            button.dataset.originalHtml = button.innerHTML;
        }
        button.disabled = true;
        button.innerHTML = '<span class="spinner-border spinner-border-sm"></span>';
    }

    function restoreInlineButton(button) {
        if (!button) {
            return;
        }
        button.disabled = false;
        if (button.dataset.originalHtml) {
            button.innerHTML = button.dataset.originalHtml;
            delete button.dataset.originalHtml;
        }
    }

    function syncSampleVariablesField(field, fallbackJson) {
        if (!field) {
            return {};
        }

        var normalized = normalizeJsonInput(field.value);
        if (!normalized) {
            normalized = normalizeJsonInput(fallbackJson || '{}') || '{}';
        }

        var decoded = safeJsonParse(normalized);
        if (!decoded || typeof decoded !== 'object' || Array.isArray(decoded)) {
            var fallbackNormalized = normalizeJsonInput(fallbackJson || '{}') || '{}';
            decoded = safeJsonParse(fallbackNormalized);
        }

        if (!decoded || typeof decoded !== 'object' || Array.isArray(decoded)) {
            decoded = {};
        }

        field.value = JSON.stringify(decoded, null, 2);
        return decoded;
    }

    function buildBadgeHtml(items, className, emptyLabel) {
        if (!Array.isArray(items) || !items.length) {
            return '<span class="et-preview-badge is-empty">' + String(emptyLabel || '-') + '</span>';
        }

        return items.map(function (item) {
            var text = String(item == null ? '' : item)
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#039;');
            return '<span class="et-preview-badge ' + (className || '') + '">' + text + '</span>';
        }).join('');
    }

    function escapeHtml(value) {
        return String(value == null ? '' : value)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function extractPlaceholders(content) {
        var value = String(content || '');
        if (!value) {
            return [];
        }

        var matches = value.match(/\{\{\s*([a-zA-Z0-9_]+)\s*\}\}/g) || [];
        var found = {};
        matches.forEach(function (match) {
            var normalized = String(match || '').replace(/[{}]/g, '').trim();
            if (!normalized) {
                return;
            }
            found[normalized] = true;
        });

        return Object.keys(found).sort();
    }

    function showAlert(icon, title, text) {
        if (window.Swal && typeof window.Swal.fire === 'function') {
            return window.Swal.fire({
                icon: icon,
                title: title,
                text: text,
                confirmButtonText: ((window.EmailTemplatePageData || {}).confirmButtonText) || ((window.EmailTemplatePageData || {}).swalOkText) || '',
                allowOutsideClick: false,
                allowEscapeKey: false,
                customClass: {
                    container: 'et-swal-container'
                }
            });
        }

        window.alert((title ? title + '\n' : '') + (text || ''));
        return Promise.resolve();
    }

    document.addEventListener('DOMContentLoaded', function () {
        document.body.classList.remove('loading');

        var pageData = window.EmailTemplatePageData || {};
        var modalEl = document.getElementById('emailTemplateModal');
        var form = document.getElementById('emailTemplateForm');
        var modal = modalEl && window.bootstrap && window.bootstrap.Modal ? window.bootstrap.Modal.getOrCreateInstance(modalEl) : null;
        var titleNode = modalEl ? modalEl.querySelector('[data-modal-title]') : null;
        var submitNode = modalEl ? modalEl.querySelector('[data-submit-label]') : null;
        var saveButton = modalEl ? modalEl.querySelector('[data-template-save-button]') : null;
        var sampleVariablesField = document.getElementById('emailTemplateSampleVariables');
        var testEmailField = document.getElementById('emailTemplateTestEmail');
        var previewSubject = document.getElementById('emailTemplatePreviewSubject');
        var previewUsed = document.getElementById('emailTemplatePreviewUsed');
        var previewMissing = document.getElementById('emailTemplatePreviewMissing');
        var previewInvalid = document.getElementById('emailTemplatePreviewInvalid');
        var previewText = document.getElementById('emailTemplatePreviewText');
        var previewFrame = document.getElementById('emailTemplatePreviewFrame');
        var developerUsed = document.getElementById('emailTemplateDeveloperUsed');
        var developerDefault = document.getElementById('emailTemplateDeveloperDefault');
        var developerProgrammer = document.getElementById('emailTemplateDeveloperProgrammer');
        var developerSnippet = document.getElementById('emailTemplateDeveloperSnippet');
        var copySnippetButton = document.getElementById('btnEmailTemplateCopySnippet');
        var previewButton = document.getElementById('btnEmailTemplatePreview');
        var testSendButton = document.getElementById('btnEmailTemplateTestSend');
        var previewToggleButtons = Array.prototype.slice.call(document.querySelectorAll('[data-preview-toggle]'));
        var previewAccordionTargets = Array.prototype.slice.call(document.querySelectorAll('#emailTemplatePreviewAccordion .accordion-collapse'));
        var filterToggleButton = document.querySelector('[data-filter-toggle]');
        var filterPanel = document.querySelector('[data-filter-panel]');
        var modalTabButtons = Array.prototype.slice.call(document.querySelectorAll('[data-template-tab]'));
        var modalTabPanes = Array.prototype.slice.call(document.querySelectorAll('[data-tab-pane]'));
        var summaryNodes = document.querySelectorAll('[data-summary-value]');
        var emptyStateContainer = document.getElementById('emailTemplateEmptyStateContainer');
        var tableEl = document.getElementById('emailTemplateDT');
        var tableBody = tableEl ? tableEl.querySelector('tbody') : null;
        var unsavedIndicator = document.getElementById('emailTemplateUnsaved');
        var activeField = null;
        var templateTable = null;
        var formDirty = false;
        var allowModalClose = false;
        var previewController = null;
        var previewSequence = 0;

        function setFormDirty(isDirty) {
            formDirty = !!isDirty;
            if (unsavedIndicator) {
                unsavedIndicator.classList.toggle('is-visible', formDirty);
                unsavedIndicator.setAttribute('aria-hidden', formDirty ? 'false' : 'true');
            }
        }

        function confirmDiscardChanges() {
            if (window.Swal && typeof window.Swal.fire === 'function') {
                return window.Swal.fire({
                    icon: 'warning',
                    title: pageData.unsavedConfirmTitle || '',
                    text: pageData.unsavedConfirmText || '',
                    showCancelButton: true,
                    confirmButtonText: pageData.unsavedConfirmDiscard || '',
                    cancelButtonText: pageData.commonCancel || '',
                    confirmButtonColor: '#dc3545',
                    reverseButtons: true
                }).then(function (result) {
                    return !!result.isConfirmed;
                });
            }

            return Promise.resolve(window.confirm(pageData.unsavedConfirmText || ''));
        }

        function showModalSafe() {
            if (modal && typeof modal.show === 'function') {
                modal.show();
                return;
            }

            if (modalEl) {
                modalEl.classList.add('show');
                modalEl.style.display = 'block';
                modalEl.removeAttribute('aria-hidden');
                document.body.classList.add('modal-open');
            }
        }

        function hideModalSafe() {
            if (modal && typeof modal.hide === 'function') {
                modal.hide();
                return;
            }

            if (modalEl) {
                modalEl.classList.remove('show');
                modalEl.style.display = 'none';
                modalEl.setAttribute('aria-hidden', 'true');
                document.body.classList.remove('modal-open');
            }
        }

        function activateModalTab(tabName) {
            modalTabButtons.forEach(function (button) {
                var isActive = button.getAttribute('data-template-tab') === tabName;
                button.classList.toggle('is-active', isActive);
                button.setAttribute('aria-selected', isActive ? 'true' : 'false');
            });

            modalTabPanes.forEach(function (pane) {
                var isActive = pane.getAttribute('data-tab-pane') === tabName;
                pane.classList.toggle('is-active', isActive);
            });
        }

        function bindPreviewCollapse(button) {
            var targetId = button.getAttribute('data-preview-toggle') || '';
            var target = targetId ? document.getElementById(targetId) : null;
            if (!target || !window.bootstrap || !window.bootstrap.Collapse) {
                return;
            }

            var collapse = window.bootstrap.Collapse.getOrCreateInstance(target, { toggle: false });

            target.addEventListener('shown.bs.collapse', function () {
                button.classList.remove('collapsed');
                button.setAttribute('aria-expanded', 'true');
            });

            target.addEventListener('hidden.bs.collapse', function () {
                button.classList.add('collapsed');
                button.setAttribute('aria-expanded', 'false');
            });

            button.addEventListener('click', function () {
                collapse.toggle();
            });
        }

        function setPreviewAccordionState(resultOpen, htmlOpen) {
            var resultTarget = document.getElementById('emailTemplatePreviewResultCollapse');
            var htmlTarget = document.getElementById('emailTemplatePreviewHtmlCollapse');

            function setCollapseState(target, shouldOpen) {
                if (!target) {
                    return;
                }

                if (window.bootstrap && window.bootstrap.Collapse) {
                    var instance = window.bootstrap.Collapse.getOrCreateInstance(target, { toggle: false });
                    if (shouldOpen) {
                        instance.show();
                    } else {
                        instance.hide();
                    }
                    return;
                }

                target.classList.toggle('show', !!shouldOpen);
            }

            setCollapseState(resultTarget, !!resultOpen);
            setCollapseState(htmlTarget, !!htmlOpen);
        }

        function setFilterPanelState(isOpen) {
            if (!filterPanel || !filterToggleButton) {
                return;
            }

            filterPanel.classList.toggle('d-none', !isOpen);
            filterToggleButton.classList.toggle('is-active', isOpen);
            filterToggleButton.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
            filterToggleButton.setAttribute('data-filter-toggle', isOpen ? 'open' : 'closed');
        }

        function getFormPayload() {
            var normalizedVariables = syncSampleVariablesField(sampleVariablesField, pageData.defaultSampleVariablesJson);
            var formData = new FormData();
            formData.append('csrf_token', document.querySelector('meta[name="csrf-token"]') ? document.querySelector('meta[name="csrf-token"]').getAttribute('content') : '');
            formData.append('subject_template', form ? (form.querySelector('[data-field="subject_template"]') || {}).value || '' : '');
            formData.append('body_html', form ? (form.querySelector('[data-field="body_html"]') || {}).value || '' : '');
            formData.append('body_text', form ? (form.querySelector('[data-field="body_text"]') || {}).value || '' : '');
            formData.append('sample_variables', JSON.stringify(normalizedVariables || {}, null, 2));
            return formData;
        }

        function getUsedTemplatePlaceholders() {
            if (!form) {
                return [];
            }

            var subjectField = form.querySelector('[data-field="subject_template"]');
            var bodyHtmlField = form.querySelector('[data-field="body_html"]');
            var bodyTextField = form.querySelector('[data-field="body_text"]');

            return extractPlaceholders(
                String((subjectField && subjectField.value) || '') + '\n' +
                String((bodyHtmlField && bodyHtmlField.value) || '') + '\n' +
                String((bodyTextField && bodyTextField.value) || '')
            );
        }

        function buildDynamicSampleVariables(preserveExisting) {
            var defaultSamples = safeJsonParse(pageData.defaultSampleVariablesJson || '{}');
            var existingSamples = safeJsonParse(sampleVariablesField ? sampleVariablesField.value : '{}');
            var generalDocs = pageData.generalPlaceholderDocs || {};
            var usedPlaceholders = getUsedTemplatePlaceholders();
            var result = {};

            if (!defaultSamples || typeof defaultSamples !== 'object' || Array.isArray(defaultSamples)) {
                defaultSamples = {};
            }
            if (!existingSamples || typeof existingSamples !== 'object' || Array.isArray(existingSamples)) {
                existingSamples = {};
            }

            usedPlaceholders.forEach(function (key) {
                if (preserveExisting && Object.prototype.hasOwnProperty.call(existingSamples, key)) {
                    result[key] = existingSamples[key];
                    return;
                }

                if (Object.prototype.hasOwnProperty.call(defaultSamples, key)) {
                    result[key] = defaultSamples[key];
                    return;
                }

                if (generalDocs[key] && generalDocs[key].sample) {
                    result[key] = generalDocs[key].sample;
                    return;
                }

                result[key] = 'Sample ' + key;
            });

            return result;
        }

        function syncDynamicSampleVariablesField(preserveExisting) {
            if (!sampleVariablesField) {
                return {};
            }

            var variables = buildDynamicSampleVariables(!!preserveExisting);
            sampleVariablesField.value = JSON.stringify(variables, null, 2);
            return variables;
        }

        function resetPreviewState() {
            renderPreview({});
            previewAccordionTargets.forEach(function (target) {
                if (window.bootstrap && window.bootstrap.Collapse) {
                    window.bootstrap.Collapse.getOrCreateInstance(target, { toggle: false }).hide();
                } else {
                    target.classList.remove('show');
                }
            });
            previewToggleButtons.forEach(function (button) {
                button.classList.add('collapsed');
                button.setAttribute('aria-expanded', 'false');
            });
        }

        function resetModalTransientState() {
            activeField = null;
            resetPreviewState();
            if (sampleVariablesField) {
                sampleVariablesField.value = '{}';
            }
            if (testEmailField) {
                testEmailField.value = pageData.defaultTestEmail || '';
            }
            if (submitNode) {
                setButtonLoading(submitNode, false);
                submitNode.disabled = false;
            }
            setFormDirty(false);
        }

        function focusFirstInvalidField() {
            if (!form) {
                return;
            }
            var invalidField = form.querySelector('.is-invalid');
            if (invalidField && typeof invalidField.focus === 'function') {
                invalidField.focus();
            }
        }

        function submitTemplateForm() {
            if (!form) {
                return Promise.resolve();
            }

            if (!form.checkValidity()) {
                activateModalTab('editor');
                form.reportValidity();
                return Promise.resolve();
            }

            clearFieldErrors(form);
            setButtonLoading(submitNode, true, pageData.loadingProcessingText || '');

            return requestTemplateAction(new FormData(form))
                .then(function (payload) {
                    refreshTableUi(payload.table || {});
                    setFormDirty(false);
                    allowModalClose = true;
                    hideModalSafe();
                    return showAlert('success', pageData.flashSuccessTitle || '', payload.message || '');
                })
                .catch(function (error) {
                    applyFieldErrors(form, error.fieldErrors || {});
                    activateModalTab('editor');
                    focusFirstInvalidField();
                    return showAlert('error', pageData.flashErrorTitle || '', error.message || pageData.networkErrorText);
                })
                .finally(function () {
                    setButtonLoading(submitNode, false);
                });
        }

        function renderPreview(preview) {
            if (previewSubject) {
                previewSubject.textContent = preview && preview.subject ? preview.subject : (pageData.previewEmptySubjectText || '');
            }
            if (previewUsed) {
                previewUsed.innerHTML = buildBadgeHtml(preview ? preview.used_placeholders : [], '', pageData.previewEmptyUsedText || '');
            }
            if (previewMissing) {
                previewMissing.innerHTML = buildBadgeHtml(preview ? preview.missing_placeholders : [], 'is-missing', pageData.previewEmptyMissingText || '');
            }
            if (previewInvalid) {
                previewInvalid.innerHTML = buildBadgeHtml(preview ? preview.invalid_placeholders : [], 'is-invalid', pageData.previewEmptyInvalidText || '');
            }
            if (previewText) {
                previewText.textContent = preview && preview.text ? preview.text : (pageData.previewEmptyTextText || 'Klik Preview Render untuk melihat output text template.');
            }
            if (previewFrame) {
                previewFrame.srcdoc = preview && preview.html ? preview.html : '';
            }
        }

        function renderDeveloperGuide() {
            if (!form) {
                return;
            }

            var pageDefaults = Array.isArray(pageData.defaultPlaceholders) ? pageData.defaultPlaceholders : [];
            var generalDocs = pageData.generalPlaceholderDocs || {};
            var defaultMap = {};
            pageDefaults.forEach(function (item) {
                if (!item || !item.key) {
                    return;
                }
                defaultMap[String(item.key)] = item;
            });

            var templateCodeField = form.querySelector('[data-field="template_code"]');
            var subjectField = form.querySelector('[data-field="subject_template"]');
            var bodyHtmlField = form.querySelector('[data-field="body_html"]');
            var bodyTextField = form.querySelector('[data-field="body_text"]');
            var templateCode = String((templateCodeField && templateCodeField.value) || 'TEMPLATE_CODE').trim() || 'TEMPLATE_CODE';
            var usedPlaceholders = getUsedTemplatePlaceholders();

            var programmerPlaceholders = usedPlaceholders.filter(function (key) {
                return !Object.prototype.hasOwnProperty.call(defaultMap, key);
            });

            var defaultUsed = usedPlaceholders.filter(function (key) {
                return Object.prototype.hasOwnProperty.call(defaultMap, key);
            });

            if (developerUsed) {
                if (!usedPlaceholders.length) {
                    developerUsed.innerHTML = '<span class="et-preview-badge is-empty">' + escapeHtml(pageData.developerNoPlaceholdersText || '-') + '</span>';
                } else {
                    developerUsed.innerHTML = usedPlaceholders.map(function (key) {
                        var badgeClass = 'is-programmer';
                        var badgeText = pageData.developerProgrammerBadgeText || 'Programmer';
                        if (Object.prototype.hasOwnProperty.call(defaultMap, key)) {
                            badgeClass = 'is-default';
                            badgeText = pageData.developerDefaultBadgeText || 'Default';
                        } else if (Object.prototype.hasOwnProperty.call(generalDocs, key)) {
                            badgeClass = 'is-general';
                            badgeText = pageData.developerGeneralBadgeText || 'General';
                        }
                        return '<span class="et-preview-badge ' + badgeClass + '">' + escapeHtml('{{' + key + '}}') + ' - ' + escapeHtml(badgeText) + '</span>';
                    }).join('');
                }
            }

            if (developerDefault) {
                developerDefault.innerHTML = pageDefaults.map(function (item) {
                    return '<span class="et-preview-badge is-default">' + escapeHtml('{{' + item.key + '}}') + '</span>';
                }).join('');
            }

            if (developerProgrammer) {
                if (!programmerPlaceholders.length) {
                    developerProgrammer.innerHTML = '<span class="et-preview-badge is-empty">' + escapeHtml(pageData.developerNoProgrammerValuesText || '-') + '</span>';
                } else {
                    developerProgrammer.innerHTML = programmerPlaceholders.map(function (key) {
                        return '<span class="et-preview-badge is-programmer">' + escapeHtml('{{' + key + '}}') + '</span>';
                    }).join('');
                }
            }

            if (developerSnippet) {
                var variablesLines = programmerPlaceholders.map(function (key) {
                    return "    '" + key + "' => $" + key + " ?? '',";
                });
                var contextLines = defaultUsed.map(function (key) {
                    return "    '" + key + "' => $" + key + " ?? '',";
                });
                if (!contextLines.length) {
                    contextLines = [
                        "    'recipient_name' => $recipientName ?? '',",
                        "    'recipient_email' => $recipientEmail ?? '',",
                        "    'recipient_role' => $recipientRole ?? '',"
                    ];
                }

                var snippet = [
                    "$templateCode = '" + templateCode.replace(/'/g, "\\'") + "';",
                    '',
                    '$template = $emailTemplateModel->findByCode($templateCode);',
                    'if (!$template) {',
                    "    throw new RuntimeException('Email template not found: ' . $templateCode);",
                    '}',
                    '',
                    '$variables = [',
                    variablesLines.length ? variablesLines.join('\n') : '    // Tiada placeholder custom wajib dihantar.',
                    '];',
                    '',
                    '$context = [',
                    contextLines.join('\n'),
                    '];',
                    '',
                    '$rendered = $emailTemplateRenderService->renderTemplate($template, $variables, $context);',
                    '',
                    '$mailer->send(',
                    "    $context['recipient_email'] ?? '',",
                    "    $rendered['subject'],",
                    "    $rendered['html'],",
                    "    $rendered['text']",
                    ');'
                ].join('\n');

                developerSnippet.textContent = snippet;
            }
        }

        function applyCreateMode() {
            if (!form) {
                return;
            }
            resetForm(form);
            resetModalTransientState();
            setFieldValue(form, 'form_action', 'save');
            activateModalTab('editor');
            if (titleNode) {
                titleNode.textContent = pageData.modalCreateTitle || 'Tambah Template Emel';
            }
            if (submitNode) {
                submitNode.textContent = pageData.submitCreateLabel || 'Simpan Template';
                submitNode.disabled = false;
            }
            renderDeveloperGuide();
            syncDynamicSampleVariablesField(false);
            setFormDirty(false);
        }

        function applyEditMode(payload) {
            if (!form) {
                return;
            }
            resetForm(form);
            resetModalTransientState();
            setFieldValue(form, 'form_action', 'save');
            fillForm(form, payload);
            activateModalTab('editor');
            if (titleNode) {
                titleNode.textContent = pageData.modalEditTitle || 'Kemaskini Template Emel';
            }
            if (submitNode) {
                submitNode.textContent = pageData.submitEditLabel || 'Kemaskini Template';
                submitNode.disabled = false;
            }
            renderDeveloperGuide();
            syncDynamicSampleVariablesField(true);
            setFormDirty(false);
        }

        function attachCurrentFilters(formData) {
            var filters = pageData.filters || {};
            formData.append('filter_role', String(filters.role || ''));
            formData.append('filter_category', String(filters.category || ''));
            formData.append('filter_status', String(filters.status || ''));
            formData.append('filter_search', String(filters.search || ''));
            return formData;
        }

        function parseRowsHtml(rowsHtml) {
            var tbody = document.createElement('tbody');
            tbody.innerHTML = String(rowsHtml || '').trim();
            return Array.prototype.slice.call(tbody.querySelectorAll('tr'));
        }

        function refreshSummary(summary) {
            if (!summary || typeof summary !== 'object') {
                return;
            }
            summaryNodes.forEach(function (node) {
                var key = node.getAttribute('data-summary-value') || '';
                if (!key) {
                    return;
                }
                node.textContent = String(summary[key] != null ? summary[key] : 0);
            });
        }

        function refreshTableUi(tablePayload) {
            if (!tablePayload || !tableEl || !tableBody) {
                return;
            }

            var rows = parseRowsHtml(tablePayload.rows_html || '');

            if (templateTable) {
                templateTable.clear();
                if (rows.length) {
                    templateTable.rows.add(rows);
                }
                templateTable.draw(false);
            } else {
                tableBody.innerHTML = tablePayload.rows_html || '';
            }

            if (emptyStateContainer) {
                emptyStateContainer.innerHTML = tablePayload.empty_html || '';
            }

            refreshSummary(tablePayload.summary || {});

            if (templateTable) {
                templateTable.columns.adjust().draw(false);
            }
        }

        function requestTemplateAction(formData) {
            attachCurrentFilters(formData);
            return fetch(pageData.actionUrl || '', {
                method: 'POST',
                body: formData,
                noLoader: true,
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-No-Loader': '1',
                    'Accept': 'application/json'
                }
            }).then(function (response) {
                return response.json().catch(function () {
                    throw new Error(pageData.networkErrorText || 'Ralat rangkaian semasa memproses permintaan.');
                }).then(function (payload) {
                    if (!response.ok || !payload || payload.success !== true) {
                        var error = new Error((payload && payload.message) || pageData.networkErrorText || 'Ralat rangkaian semasa memproses permintaan.');
                        if (payload && payload.field_errors) {
                            error.fieldErrors = payload.field_errors;
                        }
                        throw error;
                    }
                    return payload;
                });
            });
        }

        function buildActionFormData(formEl) {
            var formData = new FormData(formEl);
            var metaCsrf = document.querySelector('meta[name="csrf-token"]');
            if (!formData.get('csrf_token') && metaCsrf) {
                formData.append('csrf_token', metaCsrf.getAttribute('content') || '');
            }
            return formData;
        }

        function submitActionFormAjax(formEl) {
            var button = formEl ? formEl.querySelector('[data-template-action-button]') : null;
            if (!formEl || formEl.dataset.submitting === '1') {
                return Promise.resolve();
            }

            formEl.dataset.submitting = '1';
            setInlineButtonLoading(button);

            return requestTemplateAction(buildActionFormData(formEl))
                .then(function (payload) {
                    refreshTableUi(payload.table || {});
                    return showAlert('success', pageData.flashSuccessTitle || '', payload.message || '');
                })
                .catch(function (error) {
                    return showAlert('error', pageData.flashErrorTitle || '', error.message || pageData.networkErrorText);
                })
                .finally(function () {
                    delete formEl.dataset.submitting;
                    restoreInlineButton(button);
                });
        }

        function showActionConfirm(options) {
            if (!window.Swal || typeof window.Swal.fire !== 'function') {
                return Promise.resolve({ isConfirmed: window.confirm(options.text || options.title || '') });
            }

            return window.Swal.fire({
                icon: options.icon || 'warning',
                title: options.title || '',
                text: options.text || '',
                showCancelButton: true,
                confirmButtonText: options.confirmButtonText || pageData.confirmButtonText || '',
                cancelButtonText: options.cancelButtonText || pageData.cancelButtonText || '',
                reverseButtons: true,
                allowOutsideClick: false,
                allowEscapeKey: false,
                customClass: {
                    container: 'et-swal-container'
                }
            });
        }

        function confirmArchive(formEl) {
            var templateName = formEl.getAttribute('data-template-name') || '';
            var templateCode = formEl.getAttribute('data-template-code') || '';
            var metaText = templateName && templateCode
                ? templateName + ' (' + templateCode + ')'
                : (templateName || templateCode || '');
            var text = metaText ? ((pageData.archiveConfirmText || '') + '\n' + metaText) : (pageData.archiveConfirmText || '');

            return showActionConfirm({
                icon: 'warning',
                title: pageData.archiveConfirmTitle || '',
                text: text,
                confirmButtonText: pageData.archiveConfirmButtonText || pageData.confirmButtonText || '',
                cancelButtonText: pageData.cancelButtonText || ''
            }).then(function (result) {
                if (result && result.isConfirmed) {
                    return submitActionFormAjax(formEl);
                }
                return Promise.resolve();
            });
        }

        function confirmDelete(formEl) {
            var templateName = formEl.getAttribute('data-template-name') || '';
            var templateCode = formEl.getAttribute('data-template-code') || '';
            var metaText = templateName && templateCode
                ? templateName + ' (' + templateCode + ')'
                : (templateName || templateCode || '');
            var text = metaText ? ((pageData.deleteConfirmText || '') + '\n' + metaText) : (pageData.deleteConfirmText || '');

            return showActionConfirm({
                icon: 'warning',
                title: pageData.deleteConfirmTitle || '',
                text: text,
                confirmButtonText: pageData.deleteConfirmButtonText || pageData.confirmButtonText || '',
                cancelButtonText: pageData.cancelButtonText || ''
            }).then(function (result) {
                if (result && result.isConfirmed) {
                    return submitActionFormAjax(formEl);
                }
                return Promise.resolve();
            });
        }

        if (window.jQuery && jQuery.fn && jQuery.fn.DataTable && tableEl && !jQuery.fn.dataTable.isDataTable('#emailTemplateDT')) {
            templateTable = jQuery('#emailTemplateDT').DataTable({
                autoWidth: false,
                pageLength: 10,
                lengthMenu: [10, 25, 50, 100],
                ordering: true,
                responsive: false,
                order: [[6, 'desc']],
                columnDefs: [{ targets: [0, 7], orderable: false }],
                language: (window.DataTableStandard && typeof window.DataTableStandard.language === 'function')
                    ? window.DataTableStandard.language()
                    : {},
                initComplete: function () {
                    jQuery('#emailTemplateDT thead th.col-bil, #emailTemplateDT thead th.col-actions')
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
            }).draw();

            if (window.DataTableStandard && typeof window.DataTableStandard.decorate === 'function') {
                window.DataTableStandard.decorate('#emailTemplateDT', { controlsClass: 'mb-3' });
            }
        } else if (window.jQuery && jQuery.fn && jQuery.fn.DataTable && tableEl && jQuery.fn.dataTable.isDataTable('#emailTemplateDT')) {
            templateTable = jQuery('#emailTemplateDT').DataTable();
        }

        document.addEventListener('click', function (event) {
            var createTrigger = event.target.closest('[data-create-template]');
            if (createTrigger) {
                event.preventDefault();
                applyCreateMode();
                showModalSafe();
                return;
            }

            var editTrigger = event.target.closest('[data-edit-template]');
            if (editTrigger) {
                event.preventDefault();
                var payload = safeJsonParse(editTrigger.getAttribute('data-edit-template') || '{}');
                if (!payload) {
                    showAlert('error', pageData.flashErrorTitle || '', pageData.networkErrorText || 'Ralat rangkaian semasa memproses permintaan.');
                    return;
                }
                applyEditMode(payload);
                showModalSafe();
                return;
            }

            var actionButton = event.target.closest('[data-template-action-button]');
            if (actionButton) {
                var actionForm = actionButton.closest('[data-template-action-form]');
                if (actionForm) {
                    event.preventDefault();
                    event.stopPropagation();

                    var actionType = actionForm.getAttribute('data-template-action-form') || '';
                    if (actionType === 'archive') {
                        confirmArchive(actionForm);
                        return;
                    }

                    if (actionType === 'delete') {
                        confirmDelete(actionForm);
                        return;
                    }

                    submitActionFormAjax(actionForm);
                    return;
                }
            }

            var placeholderTrigger = event.target.closest('[data-insert-placeholder]');
            if (placeholderTrigger) {
                var placeholder = placeholderTrigger.getAttribute('data-insert-placeholder') || '';
                if (!placeholder) {
                    return;
                }

                if (!activeField || !activeField.matches('[data-placeholder-target], input[type="text"]')) {
                    activeField = form ? (form.querySelector('[data-field="body_html"]') || form.querySelector('[data-field="subject_template"]')) : null;
                }

                insertAtCursor(activeField, placeholder);
            }
        });

        document.querySelectorAll('[data-create-template]').forEach(function (button) {
            button.addEventListener('click', function (event) {
                event.preventDefault();
                applyCreateMode();
                showModalSafe();
            });
        });

        document.querySelectorAll('[data-edit-template]').forEach(function (button) {
            button.addEventListener('click', function (event) {
                event.preventDefault();
                var payload = safeJsonParse(button.getAttribute('data-edit-template') || '{}');
                if (!payload) {
                    showAlert('error', pageData.flashErrorTitle || '', pageData.networkErrorText || 'Ralat rangkaian semasa memproses permintaan.');
                    return;
                }
                applyEditMode(payload);
                showModalSafe();
            });
        });

        document.addEventListener('submit', function (event) {
            var actionForm = event.target.closest('[data-template-action-form]');
            if (!actionForm) {
                return;
            }

            event.preventDefault();
            event.stopPropagation();

            var actionType = actionForm.getAttribute('data-template-action-form') || '';
            if (actionType === 'archive') {
                confirmArchive(actionForm);
                return;
            }

            if (actionType === 'delete') {
                confirmDelete(actionForm);
                return;
            }

            submitActionFormAjax(actionForm);
        });

        if (form) {
            form.querySelectorAll('input, textarea').forEach(function (field) {
                field.addEventListener('focus', function () {
                    activeField = field;
                });
                field.addEventListener('input', function () {
                    renderDeveloperGuide();
                    if (field !== sampleVariablesField && field.matches('[data-field="subject_template"], [data-field="body_html"], [data-field="body_text"]')) {
                        syncDynamicSampleVariablesField(true);
                    }
                });
            });

            var codeField = form.querySelector('[data-field="template_code"]');
            if (codeField) {
                codeField.addEventListener('input', function () {
                    codeField.value = String(codeField.value || '')
                        .toUpperCase()
                        .replace(/\s+/g, '_')
                        .replace(/[^A-Z0-9_-]/g, '');
                    renderDeveloperGuide();
                });
            }

            form.addEventListener('submit', function (event) {
                event.preventDefault();
                event.stopPropagation();
                submitTemplateForm();
            });
        }

        if (saveButton) {
            saveButton.addEventListener('click', function (event) {
                event.preventDefault();
                submitTemplateForm();
            });
        }

        modalTabButtons.forEach(function (button) {
            button.addEventListener('click', function () {
                activateModalTab(button.getAttribute('data-template-tab') || 'editor');
            });
        });

        if (filterToggleButton && filterPanel) {
            filterToggleButton.addEventListener('click', function () {
                setFilterPanelState(filterPanel.classList.contains('d-none'));
            });
        }

        previewToggleButtons.forEach(bindPreviewCollapse);

        if (copySnippetButton) {
            copySnippetButton.addEventListener('click', function () {
                var snippetText = developerSnippet ? String(developerSnippet.textContent || '') : '';
                if (!snippetText) {
                    return;
                }

                if (navigator.clipboard && navigator.clipboard.writeText) {
                    navigator.clipboard.writeText(snippetText).then(function () {
                        showAlert('success', pageData.flashSuccessTitle || '', pageData.developerSnippetCopiedText || '');
                    });
                    return;
                }

                showAlert('success', pageData.flashSuccessTitle || '', pageData.developerSnippetCopiedText || '');
            });
        }

        if (modalEl) {
            modalEl.addEventListener('hide.bs.modal', function (event) {
                if (!formDirty || allowModalClose) {
                    return;
                }

                event.preventDefault();
                confirmDiscardChanges().then(function (confirmed) {
                    if (!confirmed) {
                        return;
                    }
                    allowModalClose = true;
                    setFormDirty(false);
                    hideModalSafe();
                });
            });

            modalEl.addEventListener('hidden.bs.modal', function () {
                if (form) {
                    resetForm(form);
                }
                resetModalTransientState();
                activateModalTab('editor');
                allowModalClose = false;
            });
        }

        if (form) {
            ['input', 'change'].forEach(function (eventName) {
                form.addEventListener(eventName, function (event) {
                    if (event.target && event.target.matches('[data-field]')) {
                        setFormDirty(true);
                    }
                });
            });
        }

        window.addEventListener('beforeunload', function (event) {
            if (!formDirty) {
                return;
            }
            event.preventDefault();
            event.returnValue = '';
        });

        if (previewButton) {
            previewButton.addEventListener('click', function () {
                try {
                    syncSampleVariablesField(sampleVariablesField, pageData.defaultSampleVariablesJson);
                } catch (error) {
                    showAlert('error', pageData.previewFailedTitle || 'Preview Gagal', error.message || pageData.invalidJsonText);
                    return;
                }

                if (previewController) {
                    previewController.abort();
                }
                previewController = typeof AbortController !== 'undefined' ? new AbortController() : null;
                var currentPreviewSequence = ++previewSequence;
                setButtonLoading(previewButton, true, pageData.loadingPreviewText || '');
                fetch(pageData.previewUrl || '', {
                    method: 'POST',
                    body: getFormPayload(),
                    noLoader: true,
                    signal: previewController ? previewController.signal : undefined,
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-No-Loader': '1',
                        'Accept': 'application/json'
                    }
                })
                    .then(function (response) {
                        return response.json().catch(function () {
                            throw new Error(pageData.networkErrorText || 'Ralat rangkaian semasa memproses permintaan.');
                        });
                    })
                    .then(function (payload) {
                        if (currentPreviewSequence !== previewSequence) {
                            return;
                        }
                        if (!payload || payload.success !== true) {
                            throw new Error((payload && payload.message) || (pageData.previewFailedTitle || 'Preview Gagal'));
                        }
                        renderPreview(payload.preview || {});
                        activateModalTab('preview');
                        setPreviewAccordionState(true, false);
                    })
                    .catch(function (error) {
                        if (error && error.name === 'AbortError') {
                            return;
                        }
                        showAlert('error', pageData.previewFailedTitle || 'Preview Gagal', error.message || pageData.networkErrorText);
                    })
                    .finally(function () {
                        if (currentPreviewSequence === previewSequence) {
                            setButtonLoading(previewButton, false);
                            previewController = null;
                        }
                    });
            });
        }

        if (testSendButton) {
            testSendButton.addEventListener('click', function () {
                try {
                    syncSampleVariablesField(sampleVariablesField, pageData.defaultSampleVariablesJson);
                } catch (error) {
                    showAlert('error', pageData.testSendFailedTitle || 'Emel Ujian Gagal', error.message || pageData.invalidJsonText);
                    return;
                }

                var emailValue = testEmailField ? String(testEmailField.value || '').trim() : '';
                if (!emailValue) {
                    showAlert('error', pageData.testSendFailedTitle || '', pageData.testEmailRequiredText || '');
                    return;
                }

                var formData = getFormPayload();
                formData.append('test_email', emailValue);
                var subjectField = form ? form.querySelector('[data-field="subject_template"]') : null;
                var subjectValue = subjectField ? String(subjectField.value || '').trim() : '-';
                var confirmation;

                if (window.Swal && typeof window.Swal.fire === 'function') {
                    confirmation = window.Swal.fire({
                        icon: 'question',
                        title: pageData.testSendConfirmTitle || '',
                        text: pageData.testSendConfirmText || '',
                        html: '<p class="mb-3">' + escapeHtml(pageData.testSendConfirmText || '') + '</p>' +
                            '<div class="text-start rounded border p-3">' +
                            '<small class="d-block text-muted mb-1">' + escapeHtml(pageData.testSendRecipientLabel || '') + '</small>' +
                            '<strong class="d-block mb-3">' + escapeHtml(emailValue) + '</strong>' +
                            '<small class="d-block text-muted mb-1">' + escapeHtml(pageData.testSendSubjectLabel || '') + '</small>' +
                            '<strong class="d-block text-break">' + escapeHtml(subjectValue || '-') + '</strong></div>',
                        showCancelButton: true,
                        confirmButtonText: pageData.testSendConfirmButton || '',
                        cancelButtonText: pageData.commonCancel || '',
                        reverseButtons: true
                    }).then(function (result) {
                        return !!result.isConfirmed;
                    });
                } else {
                    confirmation = Promise.resolve(window.confirm(pageData.testSendConfirmText || ''));
                }

                confirmation.then(function (confirmed) {
                    if (!confirmed) {
                        return;
                    }

                    setButtonLoading(testSendButton, true, pageData.loadingSendingText || '');
                    return fetch(pageData.testSendUrl || '', {
                    method: 'POST',
                    body: formData,
                    noLoader: true,
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-No-Loader': '1',
                        'Accept': 'application/json'
                    }
                    })
                    .then(function (response) {
                        return response.json().catch(function () {
                            throw new Error(pageData.networkErrorText || 'Ralat rangkaian semasa memproses permintaan.');
                        });
                    })
                    .then(function (payload) {
                        if (!payload || payload.success !== true) {
                            throw new Error((payload && payload.message) || (pageData.testSendFailedTitle || 'Emel Ujian Gagal'));
                        }
                        showAlert('success', pageData.testSendSuccessTitle || 'Emel Ujian Berjaya', payload.message || 'Emel ujian berjaya dihantar.');
                    })
                    .catch(function (error) {
                        showAlert('error', pageData.testSendFailedTitle || 'Emel Ujian Gagal', error.message || pageData.networkErrorText);
                    })
                    .finally(function () {
                        setButtonLoading(testSendButton, false);
                    });
                });
            });
        }

        if (pageData.shouldOpenModal) {
            try {
                syncDynamicSampleVariablesField(true);
            } catch (error) {
                if (sampleVariablesField) {
                    sampleVariablesField.value = '{}';
                }
            }
            resetPreviewState();
            activateModalTab('editor');
            showModalSafe();
            renderDeveloperGuide();
            if (pageData.modalErrorMessage) {
                showAlert('error', pageData.flashErrorTitle || '', pageData.modalErrorMessage);
            }
            setTimeout(focusFirstInvalidField, 120);
        }

        renderDeveloperGuide();
        syncDynamicSampleVariablesField(true);

        if (pageData.flashSuccessMessage) {
            showAlert('success', pageData.flashSuccessTitle || '', pageData.flashSuccessMessage);
        } else if (pageData.flashErrorMessage) {
            showAlert('error', pageData.flashErrorTitle || '', pageData.flashErrorMessage);
        }
    });
})();
