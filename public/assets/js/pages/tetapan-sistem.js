(function () {
  'use strict';

  function getTetapanTranslator() {
    return window.__ || function (key) { return key; };
  }

  function showTetapanSystemError(message) {
    const __ = getTetapanTranslator();
    const text = message || __('config_js_module_not_ready') || 'Modul tetapan sistem belum siap dimuatkan. Sila cuba semula.';
    if (window.Swal && typeof window.Swal.fire === 'function') {
      window.Swal.fire({
        icon: 'error',
        title: __('config_js_system_error_title') || 'Ralat Sistem',
        text: text,
        confirmButtonText: __('config_js_btn_ok') || 'OK'
      });
    } else {
      window.alert(text);
    }
  }

  const tetapanLoaderTokens = {};

  function showTetapanLoader(key, message) {
    hideTetapanLoader(key);
    const text = message || (getTetapanTranslator()('config_js_btn_loading_save') || 'Saving...');
    tetapanLoaderTokens[key] = text;
  }

  function hideTetapanLoader(key) {
    if (!tetapanLoaderTokens[key]) {
      return;
    }
    delete tetapanLoaderTokens[key];
  }

  function rememberActiveTab(tabSelector) {
    if (!tabSelector) {
      return;
    }
    try {
      window.localStorage.setItem('lastActiveTab', tabSelector);
    } catch (storageError) {
      // ignore storage errors
    }
  }

  function activateTab(tabSelector) {
    if (!tabSelector) {
      return;
    }

    const trigger = document.querySelector('a[data-bs-toggle="tab"][href="' + tabSelector + '"]');
    if (!trigger) {
      return;
    }

    rememberActiveTab(tabSelector);
    if (window.bootstrap && window.bootstrap.Tab) {
      window.bootstrap.Tab.getOrCreateInstance(trigger).show();
      return;
    }

    trigger.classList.add('active');
  }

  function fallbackSetButtonLoading(button, loading) {
    const __ = getTetapanTranslator();
    if (!button) {
      return;
    }
    if (loading) {
      button.disabled = true;
      if (!button.dataset.originalHtml) {
        button.dataset.originalHtml = button.innerHTML;
      }
      button.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> ' + ((__('config_js_btn_loading_save') || 'Saving...'));
      return;
    }
    button.disabled = false;
    if (button.dataset.originalHtml) {
      button.innerHTML = button.dataset.originalHtml;
      delete button.dataset.originalHtml;
    }
  }

  function fallbackSubmitAjax(form, button) {
    const __ = getTetapanTranslator();
    if (!form) {
      showTetapanSystemError();
      return false;
    }

    if (typeof form.checkValidity === 'function' && !form.checkValidity()) {
      if (typeof form.reportValidity === 'function') {
        form.reportValidity();
      }
      return false;
    }

    fallbackSetButtonLoading(button, true);
    showTetapanLoader('fallbackSubmit', __('config_js_saving_changes') || __('config_js_btn_loading_save') || 'Saving...');

    const formData = new FormData(form);
    formData.set('ajax', '1');

    const csrfMeta = document.querySelector('meta[name="csrf-token"]');
    const csrfToken = csrfMeta ? csrfMeta.getAttribute('content') : '';

    fetch(form.getAttribute('action') || window.location.href, {
      method: 'POST',
      body: formData,
      noLoader: true,
      headers: Object.assign({
        'X-Requested-With': 'XMLHttpRequest',
        'Accept': 'application/json',
        'X-No-Loader': '1'
      }, csrfToken ? { 'X-CSRF-Token': csrfToken } : {})
    })
      .then(function (response) {
        return response.json().catch(function () {
          throw new Error(__('config_js_invalid_server_response') || 'Respons pelayan tidak sah.');
        });
      })
      .then(function (payload) {
        if (!payload || payload.success !== true) {
          showTetapanSystemError((payload && payload.message) || __('config_js_save_failed') || 'Gagal menyimpan tetapan.');
          return;
        }

        if (typeof window.__tetapanApplyPayloadUiSync === 'function') {
          window.__tetapanApplyPayloadUiSync(payload, form);
        } else if (payload.tab === 'db' && typeof window.__tetapanSyncDatabaseRuntimeUi === 'function') {
          window.__tetapanSyncDatabaseRuntimeUi();
        }
        if (payload.tab === 'db' && typeof window.__tetapanForceRuntimeSummaryFromDbForm === 'function') {
          window.__tetapanForceRuntimeSummaryFromDbForm(form);
        }

        if (window.Swal && typeof window.Swal.fire === 'function') {
          window.Swal.fire({
            icon: 'success',
            title: payload.title || __('config_js_berjaya') || 'Berjaya',
            text: payload.message || __('config_js_save_success_default') || 'Tetapan berjaya disimpan.',
            confirmButtonText: __('config_js_btn_ok') || 'OK'
          });
        }
      })
      .catch(function (error) {
        showTetapanSystemError((error && error.message) || __('config_js_save_system_error') || 'Ralat sistem semasa menyimpan tetapan.');
      })
      .finally(function () {
        fallbackSetButtonLoading(button, false);
        hideTetapanLoader('fallbackSubmit');
      });

    return true;
  }

  function fallbackEmailTest() {
    const __ = getTetapanTranslator();
    const form = document.getElementById('form-emel-aktif');
    const btnUji = document.getElementById('btn-uji-emel');
    if (!form || !btnUji) {
      showTetapanSystemError();
      return false;
    }

    const config = window.tetapanSistemConfig || {};
    const baseUrl = typeof config.baseUrl === 'string' ? config.baseUrl : '';
    const mailFrom = form.querySelector('input[name="mail_from_address"]')
      ? form.querySelector('input[name="mail_from_address"]').value
      : '';
    const mailUsername = form.querySelector('input[name="mail_username"]')
      ? form.querySelector('input[name="mail_username"]').value
      : '';
    const defaultEmail = mailFrom || mailUsername || '';

    if (!(window.Swal && typeof window.Swal.fire === 'function')) {
      showTetapanSystemError();
      return false;
    }

    window.Swal.fire({
      title: __('config_js_input_uji_emel'),
      input: 'email',
      inputLabel: __('config_js_label_uji_emel'),
      inputValue: defaultEmail,
      inputPlaceholder: __('config_js_placeholder_uji_emel'),
      showCancelButton: true,
      confirmButtonText: __('config_js_uji_emel_btn'),
      cancelButtonText: __('config_alert_no'),
      preConfirm: function (email) {
        if (!email) {
          window.Swal.showValidationMessage(__('config_js_valid_emel_kosong'));
          return false;
        }
        const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        if (!emailRegex.test(email)) {
          window.Swal.showValidationMessage(__('config_js_valid_email_full'));
          return false;
        }
        return email;
      }
    }).then(function (result) {
      if (!result.isConfirmed) {
        return;
      }

      const formData = new FormData(form);
      formData.append('uji_email', result.value);
      const csrfMeta = document.querySelector('meta[name="csrf-token"]');
      const csrfToken = csrfMeta ? csrfMeta.getAttribute('content') : '';
      formData.append('csrf_token', csrfToken);

      btnUji.disabled = true;
      if (!btnUji.dataset.originalHtml) {
        btnUji.dataset.originalHtml = btnUji.innerHTML;
      }
      btnUji.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> ' + (__('config_js_uji_emel_btn_loading') || 'Testing...');
      showTetapanLoader('fallbackEmailTest', __('config_js_uji_emel_btn_loading') || 'Testing...');

      fetch(baseUrl + 'ajax/uji-emel.php', {
        method: 'POST',
        body: formData,
        noLoader: true,
        headers: Object.assign({
          'X-No-Loader': '1'
        }, csrfToken ? { 'X-CSRF-Token': csrfToken } : {})
      })
        .then(function (res) { return res.json(); })
        .then(function (data) {
          if (data && data.success) {
            window.Swal.fire({
              icon: 'success',
              title: __('config_js_berjaya') || 'Berjaya',
              html: data.message || __('config_js_emel_berjaya') || 'Emel berjaya dihantar.'
            });
            return;
          }

          window.Swal.fire({
            icon: 'error',
            title: __('config_js_ralat') || 'Ralat',
            text: (data && data.message) || __('config_js_emel_gagal') || 'Gagal hantar emel.'
          });
        })
        .catch(function () {
          window.Swal.fire({
            icon: 'error',
            title: __('config_js_ralat') || 'Ralat',
            text: __('config_js_ralat_sistem') || 'Ralat sistem semasa menguji sambungan.'
          });
        })
        .finally(function () {
          btnUji.disabled = false;
          btnUji.innerHTML = btnUji.dataset.originalHtml || '<i class="ri-mail-send-line me-1"></i> ' + (__('config_js_uji_emel_btn_default') || 'Uji Sambungan Emel');
          hideTetapanLoader('fallbackEmailTest');
        });
    });

    return false;
  }

  window.__tetapanSubmitFormWithValidation = function (form, button) {
    if (typeof window.__tetapanSubmitFormWithValidationImpl === 'function') {
      return window.__tetapanSubmitFormWithValidationImpl(form, button);
    }
    return fallbackSubmitAjax(form, button);
  };

  window.__tetapanHandleEmailTest = function () {
    if (typeof window.__tetapanHandleEmailTestImpl === 'function') {
      return window.__tetapanHandleEmailTestImpl();
    }
    return fallbackEmailTest();
  };

  function initTetapanSistemPage() {
    const config = window.tetapanSistemConfig || {};
    const __ = window.__ || function (key) { return key; };
    const baseUrl = typeof config.baseUrl === 'string' ? config.baseUrl : '';
    const csrfToken = typeof config.csrfToken === 'string' ? config.csrfToken : '';
    const initialDbSelection = config.initialDbSelection || {};
    let additionalConnections = Array.isArray(config.additionalConnections) ? config.additionalConnections.slice() : [];
    let additionalDiagnostics = config.additionalDiagnostics && typeof config.additionalDiagnostics === 'object'
      ? config.additionalDiagnostics
      : {};
    const pageUiHelper = window.PageUiHelper || {};
    const formRuntimeState = new WeakMap();
    const buildAssetUrl = function (assetPath) {
      const cleanPath = String(assetPath || '').trim().replace(/^\/+/, '');
      if (!cleanPath) {
        return '';
      }
      if (/^(https?:)?\/\//i.test(String(assetPath || '').trim()) || /^data:/i.test(String(assetPath || '').trim())) {
        return String(assetPath || '').trim();
      }
      return baseUrl + cleanPath + (cleanPath.indexOf('?') === -1 ? ('?v=' + Date.now()) : ('&v=' + Date.now()));
    };

    const setStorageValue = function (key, value) {
      try {
        window.localStorage.setItem(key, value);
      } catch (storageError) {
        // ignore blocked storage
      }
    };

    const setImageSrc = function (selector, assetPath) {
      if (!assetPath) {
        return;
      }
      var nextUrl = buildAssetUrl(assetPath);
      if (!nextUrl) {
        return;
      }
      document.querySelectorAll(selector).forEach(function (image) {
        image.setAttribute('src', nextUrl);
      });
    };

    const setAnchorHref = function (selector, path) {
      if (!path) {
        return;
      }
      var cleanPath = String(path || '').trim();
      var nextUrl = /^(https?:)?\/\//i.test(cleanPath) || /^data:/i.test(cleanPath)
        ? cleanPath
        : baseUrl + cleanPath.replace(/^\/+/, '');
      if (!nextUrl) {
        return;
      }
      document.querySelectorAll(selector).forEach(function (anchor) {
        anchor.setAttribute('href', nextUrl);
      });
    };

    const setLinkHrefWithCacheBust = function (selector, assetPath) {
      if (!assetPath) {
        return;
      }
      var nextUrl = buildAssetUrl(assetPath);
      if (!nextUrl) {
        return;
      }
      var target = document.querySelector(selector);
      if (!target) {
        target = document.createElement('link');
        target.setAttribute('rel', 'icon');
        target.setAttribute('type', 'image/x-icon');
        document.head.appendChild(target);
      }
      target.setAttribute('href', nextUrl);
    };

    const updateTextContent = function (selector, value) {
      if (value == null || value === '') {
        return;
      }
      document.querySelectorAll(selector).forEach(function (node) {
        node.textContent = String(value);
      });
    };

    const runtimeTranslationState = {
      valueToKey: null
    };

    const normalizeRuntimeText = function (value) {
      return String(value == null ? '' : value).replace(/\s+/g, ' ').trim();
    };

    const getRuntimeTranslationKey = function (value) {
      var normalized = normalizeRuntimeText(value);
      if (!normalized) {
        return '';
      }
      if (!runtimeTranslationState.valueToKey) {
        runtimeTranslationState.valueToKey = {};
        var bundles = window.__translationBundles || {};
        Object.keys(bundles).forEach(function (langCode) {
          var bundle = bundles[langCode] || {};
          Object.keys(bundle).forEach(function (key) {
            var text = normalizeRuntimeText(bundle[key]);
            if (!text || text.length > 500) {
              return;
            }
            if (!Object.prototype.hasOwnProperty.call(runtimeTranslationState.valueToKey, text)) {
              runtimeTranslationState.valueToKey[text] = key;
            }
          });
        });
      }
      return runtimeTranslationState.valueToKey[normalized] || '';
    };

    const translateRuntimeValue = function (value) {
      var key = getRuntimeTranslationKey(value);
      if (!key) {
        return null;
      }
      var translated = __(key);
      return translated && translated !== key ? translated : null;
    };

    const setRuntimeLanguage = function (languageCode) {
      var lang = String(languageCode || '').trim();
      if (!lang) {
        return false;
      }
      if (typeof window.__setRuntimeLanguage === 'function' && window.__setRuntimeLanguage(lang)) {
        return true;
      }
      var bundles = window.__translationBundles || {};
      if (!bundles[lang]) {
        return false;
      }
      window.__currentLang = lang;
      window.__translations = bundles[lang] || {};
      document.documentElement.setAttribute('lang', lang);
      return true;
    };

    const shouldSkipRuntimeTranslationNode = function (node) {
      var parent = node && node.parentElement;
      if (!parent) {
        return true;
      }
      var tagName = parent.tagName ? parent.tagName.toUpperCase() : '';
      if (tagName === 'SCRIPT' || tagName === 'STYLE' || tagName === 'TEXTAREA' || tagName === 'NOSCRIPT') {
        return true;
      }
      return !!parent.closest('[data-i18n-skip="1"], .swal2-container');
    };

    const applyRuntimeTranslations = function (languageCode) {
      if (languageCode) {
        setRuntimeLanguage(languageCode);
      }

      var roots = Array.from(document.querySelectorAll('.content-page, .navbar-custom, .topbar, .footer'));
      if (!roots.length) {
        roots = [document.body];
      }

      document.querySelectorAll('[data-i18n]').forEach(function (node) {
        var key = node.getAttribute('data-i18n') || '';
        if (key) {
          node.textContent = __(key);
        }
      });

      ['placeholder', 'title', 'aria-label', 'alt'].forEach(function (attributeName) {
        roots.forEach(function (root) {
          if (!root) {
            return;
          }
          root.querySelectorAll('[' + attributeName + ']').forEach(function (node) {
            var translated = translateRuntimeValue(node.getAttribute(attributeName));
            if (translated) {
              node.setAttribute(attributeName, translated);
            }
          });
        });
      });

      roots.forEach(function (root) {
        if (!root) {
          return;
        }
        var walker = document.createTreeWalker(root, NodeFilter.SHOW_TEXT, {
          acceptNode: function (node) {
            if (shouldSkipRuntimeTranslationNode(node)) {
              return NodeFilter.FILTER_REJECT;
            }
            return normalizeRuntimeText(node.nodeValue) ? NodeFilter.FILTER_ACCEPT : NodeFilter.FILTER_SKIP;
          }
        });
        var textNodes = [];
        var current = walker.nextNode();
        while (current) {
          textNodes.push(current);
          current = walker.nextNode();
        }
        textNodes.forEach(function (node) {
          var translated = translateRuntimeValue(node.nodeValue);
          if (!translated) {
            return;
          }
          var leading = String(node.nodeValue || '').match(/^\s*/);
          var trailing = String(node.nodeValue || '').match(/\s*$/);
          node.nodeValue = (leading ? leading[0] : '') + translated + (trailing ? trailing[0] : '');
        });
      });

      if (typeof window.__tetapanRefreshAuthPolicySummary === 'function') {
        window.__tetapanRefreshAuthPolicySummary();
      }
      if (typeof window.__tetapanSyncThemeSectionUi === 'function') {
        window.__tetapanSyncThemeSectionUi();
      }
      if (typeof window.__tetapanSyncDatabaseRuntimeUi === 'function') {
        window.__tetapanSyncDatabaseRuntimeUi();
      }
      if (typeof window.__tetapanRenderAdditionalConnectionsTable === 'function') {
        window.__tetapanRenderAdditionalConnectionsTable();
      }
    };

    window.__tetapanApplyRuntimeTranslations = applyRuntimeTranslations;

    const normalizeServerBoolean = function (value) {
      if (typeof value === 'boolean') {
        return value;
      }
      if (typeof value === 'number') {
        return value === 1;
      }
      return ['1', 'true', 'on', 'yes'].indexOf(String(value == null ? '' : value).trim().toLowerCase()) !== -1;
    };

    const applyGeneralSettings = function (generalSettings, form) {
      if (!generalSettings) {
        return;
      }

      if (form) {
        var generalFieldMap = {
          'site.title': 'site_title',
          'site.favicon': 'site_favicon',
          'site.default_home': 'site_default_home',
          'system.name': 'system_name',
          'system.meta_author': 'system_meta_author',
          'system.support': 'system_support',
          'branding.topbar_logo_light': 'branding_topbar_logo_light',
          'branding.topbar_logo_dark': 'branding_topbar_logo_dark',
          'branding.topbar_logo_sm': 'branding_topbar_logo_sm',
          'branding.sidebar_logo': 'branding_sidebar_logo',
          'branding.sidebar_user_image': 'branding_sidebar_user_image',
          'session.idle_timeout_minutes': 'session_idle_timeout_minutes',
          'impersonation.timeout_minutes': 'impersonation_timeout_minutes',
          'upload.manual_max_mb': 'upload_manual_max_mb',
          'organization.name': 'organization_name',
          'organization.short': 'organization_short',
          'organization.website': 'organization_website',
          'footer.text.ms': 'footer_text_ms',
          'footer.text.en': 'footer_text_en',
          'mail.system_name': 'mail_system_name',
          'mail.default_action_url': 'mail_default_action_url',
          'mail.footer_note.ms': 'mail_footer_note_ms',
          'mail.footer_note.en': 'mail_footer_note_en'
        };
        Object.keys(generalFieldMap).forEach(function (key) {
          setFormFieldValue(form, generalFieldMap[key], generalSettings[key]);
        });
      }

      var siteTitle = String(generalSettings['site.title'] || '').trim();
      if (siteTitle) {
        var pageTitle = document.querySelector('.page-title');
        var pageTitleText = pageTitle ? String(pageTitle.textContent || '').trim() : '';
        document.title = pageTitleText && pageTitleText !== siteTitle
          ? pageTitleText + ' | ' + siteTitle
          : siteTitle;
      }

      setLinkHrefWithCacheBust('link[rel="icon"], link[rel="shortcut icon"]', generalSettings['site.favicon']);
      setAnchorHref('.logo-topbar a, #leftside-menu a.logo', generalSettings['site.default_home']);
      setImageSrc('.logo-topbar .logo-light .logo-lg img', generalSettings['branding.topbar_logo_light']);
      setImageSrc('.logo-topbar .logo-dark .logo-lg img', generalSettings['branding.topbar_logo_dark']);
      setImageSrc('.logo-topbar .logo-light .logo-sm img, .logo-topbar .logo-dark .logo-sm img', generalSettings['branding.topbar_logo_sm']);
      setImageSrc('#leftside-menu a.logo .logo-lg img, #leftside-menu a.logo .logo-sm img', generalSettings['branding.sidebar_logo']);

      var sidebarUserImage = generalSettings['branding.sidebar_user_image'];
      if (sidebarUserImage) {
        document.querySelectorAll('.leftbar-user').forEach(function (leftbarUser) {
          leftbarUser.style.backgroundImage = 'url("' + buildAssetUrl(sidebarUserImage) + '")';
        });
      }

      var htmlLang = document.documentElement.getAttribute('lang') || 'ms';
      var footerText = String(generalSettings['footer.text.' + htmlLang] || generalSettings['footer.text.ms'] || generalSettings['footer.text.en'] || '').trim();
      updateTextContent('#footer-runtime-text', footerText);
      updateTextContent('[data-runtime-system-name]', generalSettings['system.name']);
      updateTextContent('[data-runtime-organization-name]', generalSettings['organization.name']);

      var footer = document.querySelector('.footer');
      if (footer) {
        [
          ['organizationName', generalSettings['organization.name']],
          ['organizationShort', generalSettings['organization.short']],
          ['organizationWebsite', generalSettings['organization.website']],
          ['supportEmail', generalSettings['system.support']],
          ['systemName', generalSettings['system.name']]
        ].forEach(function (entry) {
          if (entry[1] != null) {
            footer.dataset[entry[0]] = String(entry[1] || '');
          }
        });
      }
    };

    const setFormFieldValue = function (form, name, value) {
      if (!form || !name) {
        return;
      }
      form.querySelectorAll('[name="' + name + '"]').forEach(function (field) {
        if (field.type === 'checkbox' || field.type === 'radio') {
          field.checked = field.type === 'radio'
            ? String(field.value) === String(value)
            : !!value;
          return;
        }
        field.value = value == null ? '' : String(value);
      });
    };

    const applyAuthStatusFromServer = function (authSettings) {
      var warnings = Array.isArray(authSettings && authSettings.warnings) ? authSettings.warnings : [];
      var errors = Array.isArray(authSettings && authSettings.errors) ? authSettings.errors : [];
      window.__tetapanAuthServerState = {
        valid: !!(authSettings && authSettings.valid),
        warnings: warnings.filter(function (item) { return String(item || '').trim() !== ''; }),
        errors: errors.filter(function (item) { return String(item || '').trim() !== ''; })
      };
    };

    const applySavedAuthSettings = function (authSettings, form) {
      if (!authSettings || !form) {
        return;
      }

      var categories = authSettings.categories || {};
      var provisioning = authSettings.provisioning || {};
      var password = authSettings.password || {};
      var loginSecurity = authSettings.login_security || {};
      var sso = authSettings.sso || {};
      var hybrid = sso.hybrid || {};
      var integration = authSettings.integration || {};
      var valueOr = function (value, fallback) {
        return value == null || value === '' ? fallback : value;
      };

      var values = {
        auth_maintenance_mode: normalizeServerBoolean(authSettings.maintenance_mode),
        auth_login_enable_staf: normalizeServerBoolean(categories.staf),
        auth_login_enable_pelajar: normalizeServerBoolean(categories.pelajar),
        auth_login_enable_umum: normalizeServerBoolean(categories.umum),
        auth_auto_provision_staf_sso: normalizeServerBoolean(provisioning.staf_sso_enabled),
        auth_auto_provision_pelajar_sso: normalizeServerBoolean(provisioning.pelajar_sso_enabled),
        auth_default_group_staff_code: valueOr(provisioning.default_group_staff_code, 'ADM-STAF'),
        auth_default_group_student_code: valueOr(provisioning.default_group_student_code, 'ADM-STUDENT'),
        auth_password_min_length: valueOr(password.min_length, 8),
        auth_password_expiry_days: valueOr(password.expiry_days, 90),
        auth_password_history_count: valueOr(password.history_count, 5),
        auth_password_reset_token_minutes: valueOr(password.reset_token_minutes, 30),
        auth_password_require_uppercase: normalizeServerBoolean(password.require_uppercase),
        auth_password_require_lowercase: normalizeServerBoolean(password.require_lowercase),
        auth_password_require_number: normalizeServerBoolean(password.require_number),
        auth_password_require_symbol: normalizeServerBoolean(password.require_symbol),
        auth_password_block_loginid_variants: normalizeServerBoolean(password.block_loginid_variants),
        auth_login_max_attempts: valueOr(loginSecurity.max_attempts, 3),
        auth_login_lock_seconds: valueOr(loginSecurity.lock_seconds, 60),
        auth_login_identifier_ip_max_attempts: valueOr(loginSecurity.identifier_ip_max_attempts, 5),
        auth_login_identifier_ip_lock_seconds: valueOr(loginSecurity.identifier_ip_lock_seconds, 300),
        auth_login_ip_max_attempts: valueOr(loginSecurity.ip_max_attempts, 10),
        auth_login_ip_lock_seconds: valueOr(loginSecurity.ip_lock_seconds, 300),
        auth_sso_enabled: normalizeServerBoolean(sso.enabled),
        auth_sso_mode: valueOr(sso.mode, 'MANUAL'),
        auth_sso_site_id: valueOr(integration.site_id, ''),
        auth_sso_idp_domain: valueOr(integration.idp_domain, ''),
        auth_sso_hybrid_staf: valueOr(hybrid.staf, 'SSO'),
        auth_sso_hybrid_pelajar: valueOr(hybrid.pelajar, 'SSO'),
        auth_sso_hybrid_umum: valueOr(hybrid.umum, 'MANUAL')
      };

      Object.keys(values).forEach(function (name) {
        setFormFieldValue(form, name, values[name]);
      });

      applyAuthStatusFromServer(authSettings);
      if (typeof window.__tetapanRefreshAuthPolicySummary === 'function') {
        window.__tetapanRefreshAuthPolicySummary();
      }
    };

    const getFormState = function (form) {
      if (!formRuntimeState.has(form)) {
        formRuntimeState.set(form, {
          snapshot: '',
          pending: false,
          lastState: 'idle'
        });
      }
      return formRuntimeState.get(form);
    };

    const serializeFormState = function (form) {
      const entries = [];
      const formData = new FormData(form);
      formData.forEach(function (value, key) {
        if (key === 'csrf_token' || key === 'ajax') {
          return;
        }
        if (value instanceof File) {
          entries.push([key, value.name || '']);
          return;
        }
        entries.push([key, String(value)]);
      });
      entries.sort(function (a, b) {
        if (a[0] === b[0]) {
          return a[1].localeCompare(b[1]);
        }
        return a[0].localeCompare(b[0]);
      });
      return JSON.stringify(entries);
    };

    const captureFormSnapshot = function (form) {
      const state = getFormState(form);
      state.snapshot = serializeFormState(form);
      return state.snapshot;
    };

    const getSaveFeedbackHost = function (form, button) {
      var existing = form.querySelector('[data-settings-save-feedback="1"]');
      if (existing) {
        return existing;
      }

      var actions = (button && button.closest('.general-settings-actions, .email-settings-actions, .auth-settings-actions, .db-settings-actions, .theme-settings-actions, .lang-settings-actions'))
        || form.querySelector('.general-settings-actions, .email-settings-actions, .auth-settings-actions, .db-settings-actions, .theme-settings-actions, .lang-settings-actions');
      if (!actions) {
        return null;
      }

      var feedback = document.createElement('div');
      feedback.className = 'tetapan-save-feedback d-inline-flex align-items-center gap-2 small ms-auto me-2';
      feedback.setAttribute('data-settings-save-feedback', '1');
      feedback.setAttribute('aria-live', 'polite');
      feedback.innerHTML = ''
        + '<span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle" data-save-feedback-badge>' + escapeHtml(__('config_js_save_feedback_idle_badge') || 'Not saved') + '</span>'
        + '<span class="text-muted" data-save-feedback-text>' + escapeHtml(__('config_js_save_feedback_idle_text') || 'No new changes to save.') + '</span>';

      var buttonGroup = button && button.parentElement && button.parentElement !== actions && button.parentElement.classList.contains('d-flex')
        ? button.parentElement
        : null;

      if (buttonGroup && buttonGroup.parentElement === actions) {
        actions.insertBefore(feedback, buttonGroup);
      } else if (button && button.parentElement === actions) {
        actions.insertBefore(feedback, button);
      } else {
        actions.appendChild(feedback);
      }

      return feedback;
    };

    const setSaveFeedbackState = function (form, button, tone, message) {
      var host = getSaveFeedbackHost(form, button);
      if (!host) {
        return;
      }

      var badge = host.querySelector('[data-save-feedback-badge]');
      var text = host.querySelector('[data-save-feedback-text]');
      var badgeMap = {
        idle: {
          badge: __('config_js_save_feedback_idle_badge') || 'Not saved',
          className: 'badge bg-secondary-subtle text-secondary border border-secondary-subtle',
          textClass: 'text-muted'
        },
        dirty: {
          badge: __('config_js_save_feedback_dirty_badge') || 'Changed',
          className: 'badge bg-warning-subtle text-warning border border-warning-subtle',
          textClass: 'text-warning-emphasis'
        },
        saving: {
          badge: __('config_js_save_feedback_saving_badge') || 'Saving',
          className: 'badge bg-primary-subtle text-primary border border-primary-subtle',
          textClass: 'text-primary-emphasis'
        },
        success: {
          badge: __('config_js_save_feedback_success_badge') || 'Saved',
          className: 'badge bg-success-subtle text-success border border-success-subtle',
          textClass: 'text-success-emphasis'
        },
        warning: {
          badge: __('config_js_save_feedback_warning_badge') || 'Warning',
          className: 'badge bg-warning-subtle text-warning border border-warning-subtle',
          textClass: 'text-warning-emphasis'
        },
        error: {
          badge: __('config_js_save_feedback_error_badge') || 'Error',
          className: 'badge bg-danger-subtle text-danger border border-danger-subtle',
          textClass: 'text-danger-emphasis'
        }
      };

      var next = badgeMap[tone] || badgeMap.idle;
      if (badge) {
        badge.className = next.className;
        badge.textContent = next.badge;
      }
      if (text) {
        text.className = next.textClass;
        text.textContent = message || '';
      }

      getFormState(form).lastState = tone;
    };

    const refreshDirtyIndicator = function (form, button) {
      if (!form) {
        return;
      }

      var state = getFormState(form);
      if (state.pending) {
        return;
      }

      var dirty = state.snapshot !== serializeFormState(form);
      if (dirty) {
        setSaveFeedbackState(form, button, 'dirty', __('config_js_save_feedback_dirty_text') || 'Changes have not been saved.');
        return;
      }

      if (state.lastState === 'success') {
        setSaveFeedbackState(form, button, 'success', __('config_js_save_feedback_success_text') || 'The latest changes have been saved.');
        return;
      }

      if (state.lastState === 'warning') {
        setSaveFeedbackState(form, button, 'warning', __('config_js_save_feedback_warning_text') || 'Settings were saved, but there are warnings to review.');
        return;
      }

      setSaveFeedbackState(form, button, 'idle', __('config_js_save_feedback_idle_text') || 'No new changes to save.');
    };

    const refreshEmailRuntimeSummary = function (emailSettings) {
      if (!emailSettings) {
        return;
      }

      var host = String(emailSettings.mail_host || '').trim();
      var port = String(emailSettings.mail_port || '').trim();
      var summary = {
        'email-runtime-driver': String(emailSettings.mail_driver || '').trim() || '-',
        'email-runtime-host': host ? (host + (port ? ':' + port : '')) : '-',
        'email-runtime-sender': String(emailSettings.mail_from_address || '').trim() || '-',
        'email-runtime-encryption': String(emailSettings.mail_encryption || '').trim().toUpperCase() || '-'
      };

      Object.keys(summary).forEach(function (id) {
        var node = document.getElementById(id);
        if (node) {
          node.textContent = summary[id];
        }
      });
    };

    const syncEmailFormState = function (form, emailSettings) {
      if (!form || !emailSettings) {
        return;
      }

      ['mail_driver', 'mail_host', 'mail_port', 'mail_username', 'mail_encryption', 'mail_from_address', 'mail_from_name'].forEach(function (name) {
        var field = form.querySelector('[name="' + name + '"]');
        if (!field) {
          return;
        }
        field.value = emailSettings[name] || '';
        clearFieldValidationState(field);
        field.classList.remove('is-valid');
      });

      var passwordField = form.querySelector('[name="mail_password"]');
      if (passwordField) {
        passwordField.value = '';
        clearFieldValidationState(passwordField);
        passwordField.classList.remove('is-valid');
      }

      refreshEmailRuntimeSummary(emailSettings);
    };

    const syncThemeFormState = function (form, themeSettings) {
      if (!form || !themeSettings) {
        return;
      }

      var mapping = {
        layoutMode: 'layout_mode',
        topbarColor: 'topbar_color',
        sidebarColor: 'sidebar_color',
        topbarCustomSeed: 'topbar_custom_seed',
        sidebarCustomSeed: 'sidebar_custom_seed'
      };

      Object.keys(mapping).forEach(function (key) {
        var inputName = mapping[key];
        var expectedValue = String(themeSettings[key] || '');
        if (!expectedValue) {
          return;
        }
        var isSeed = key === 'topbarCustomSeed' || key === 'sidebarCustomSeed';
        var target = isSeed
          ? form.querySelector('input[name="' + inputName + '"]')
          : form.querySelector('input[name="' + inputName + '"][value="' + expectedValue + '"]');
        if (target) {
          if (isSeed) target.value = expectedValue;
          else target.checked = true;
        }
      });

      if (typeof window.__tetapanSyncThemeSectionUi === 'function') {
        window.__tetapanSyncThemeSectionUi();
      }
    };

    const syncLanguageSelectionUi = function (form) {
      if (!form) {
        return;
      }

      var languageCheckboxes = Array.from(form.querySelectorAll('input[name="languages[]"]'));
      var defaultRadios = Array.from(form.querySelectorAll('input[name="default_language"]'));
      var activeCheckboxes = languageCheckboxes.filter(function (input) {
        return input.checked;
      });

      defaultRadios.forEach(function (radio) {
        var relatedCheckbox = form.querySelector('#lang_' + radio.value);
        var isActive = !!(relatedCheckbox && relatedCheckbox.checked);
        radio.disabled = !isActive;
        if (!isActive) {
          radio.checked = false;
        }

        var row = radio.closest('tr');
        if (!row) {
          return;
        }

        row.classList.toggle('table-success', isActive);
        row.classList.toggle('language-row-active', isActive);

        var badgeHost = row.querySelector('td:nth-child(4) .d-flex.align-items-center');
        if (!badgeHost) {
          return;
        }

        badgeHost.querySelectorAll('.js-language-active-badge, .js-language-default-badge').forEach(function (badge) {
          badge.remove();
        });

        if (isActive) {
          var activeBadge = document.createElement('span');
          activeBadge.className = 'badge bg-success-subtle text-success border border-success-subtle js-language-active-badge';
          activeBadge.innerHTML = '<i class="ri-checkbox-circle-fill me-1"></i> ' + ((__('config_tab_bahasa_status_aktif')) || 'Aktif');
          badgeHost.appendChild(activeBadge);
        }
      });

      var selectedDefault = form.querySelector('input[name="default_language"]:checked');
      if (!selectedDefault && activeCheckboxes.length > 0) {
        var fallbackRadio = form.querySelector('#default_lang_' + activeCheckboxes[0].value);
        if (fallbackRadio && !fallbackRadio.disabled) {
          fallbackRadio.checked = true;
          selectedDefault = fallbackRadio;
        }
      }

      if (selectedDefault) {
        var selectedRow = selectedDefault.closest('tr');
        var selectedBadgeHost = selectedRow ? selectedRow.querySelector('td:nth-child(4) .d-flex.align-items-center') : null;
        if (selectedBadgeHost) {
          var defaultBadge = document.createElement('span');
          defaultBadge.className = 'badge bg-primary-subtle text-primary border border-primary-subtle ms-2 js-language-default-badge';
          defaultBadge.innerHTML = '<i class="ri-star-fill me-1"></i> ' + ((__('config_tab_bahasa_default')) || 'Bahasa Lalai');
          selectedBadgeHost.appendChild(defaultBadge);
        }
        document.documentElement.lang = selectedDefault.value;
      }
    };

    const refreshLanguageRuntimeSummary = function (languageData) {
      if (!languageData) {
        return;
      }
      var active = Array.isArray(languageData.active) ? languageData.active : [];
      var defaultLanguage = String(languageData.default || '').trim();
      var activeNode = document.getElementById('lang-runtime-active');
      var defaultNode = document.getElementById('lang-runtime-default');

      if (activeNode) {
        activeNode.textContent = active.length
          ? active.map(function (code) { return String(code || '').toUpperCase(); }).join(', ')
          : '-';
      }
      if (defaultNode) {
        defaultNode.textContent = defaultLanguage ? defaultLanguage.toUpperCase() : '-';
      }
      if (defaultLanguage) {
        document.documentElement.lang = defaultLanguage;
      }
    };

    const syncLanguageFormState = function (form, languageData) {
      if (!form || !languageData) {
        return;
      }

      var active = Array.isArray(languageData.active) ? languageData.active : [];
      var defaultLanguage = String(languageData.default || '');

      form.querySelectorAll('input[name="languages[]"]').forEach(function (checkbox) {
        checkbox.checked = active.indexOf(checkbox.value) !== -1;
      });
      form.querySelectorAll('input[name="default_language"]').forEach(function (radio) {
        radio.checked = defaultLanguage !== '' && radio.value === defaultLanguage;
      });

      if (defaultLanguage) {
        setRuntimeLanguage(defaultLanguage);
      }
      syncLanguageSelectionUi(form);
      refreshLanguageRuntimeSummary(languageData);
      if (defaultLanguage) {
        applyRuntimeTranslations(defaultLanguage);
      }
    };

    const syncAiChatbotFormState = function (form, settings) {
      if (!form || !settings) {
        return;
      }

      var mapping = {
        enabled: 'ai_chatbot_enabled',
        access_mode: 'ai_chatbot_access_mode',
        allowed_groups: 'ai_chatbot_allowed_groups',
        provider: 'ai_chatbot_provider',
        model: 'ai_chatbot_model',
        base_url: 'ai_chatbot_base_url',
        timeout_seconds: 'ai_chatbot_timeout_seconds',
        max_input_chars: 'ai_chatbot_max_input_chars',
        max_output_tokens: 'ai_chatbot_max_output_tokens',
        rate_limit_per_minute: 'ai_chatbot_rate_limit_per_minute',
        user_daily_request_limit: 'ai_chatbot_user_daily_request_limit',
        global_daily_request_limit: 'ai_chatbot_global_daily_request_limit',
        persist_usage: 'ai_chatbot_persist_usage',
        store_conversations: 'ai_chatbot_store_conversations',
        log_message_content: 'ai_chatbot_log_message_content',
        character_name: 'ai_chatbot_character_name',
        character_avatar: 'ai_chatbot_character_avatar',
        welcome_message: 'ai_chatbot_welcome_message',
        app_url: 'ai_chatbot_app_url',
        app_title: 'ai_chatbot_app_title'
      };
      var booleanKeys = ['enabled', 'persist_usage', 'store_conversations', 'log_message_content'];

      Object.keys(mapping).forEach(function (key) {
        var value = booleanKeys.indexOf(key) !== -1 ? normalizeServerBoolean(settings[key]) : settings[key];
        setFormFieldValue(form, mapping[key], value);
      });

      var apiKey = form.querySelector('[name="ai_chatbot_api_key"]');
      if (apiKey) {
        apiKey.value = '';
      }
      var modelSelect = form.querySelector('[name="ai_chatbot_model"]');
      if (modelSelect && window.jQuery && window.jQuery.fn && window.jQuery.fn.select2) {
        window.jQuery(modelSelect).trigger('change.select2');
      }
    };

    const getDbEnvironmentLabel = function (environment) {
      return String(environment || '') === 'development'
        ? (__('config_tab_db_environment_development') || 'Development')
        : (__('config_tab_db_environment_production') || 'Production');
    };

    const getDbModeLabel = function (mode) {
      return String(mode || '') === 'staff_student'
        ? (__('config_tab_db_mode_staff_student') || 'Staff + Student')
        : (__('config_tab_db_mode_staff_only') || 'Staff Only');
    };

    let lastDatabaseRuntime = config.dbRuntime && typeof config.dbRuntime === 'object'
      ? Object.assign({}, config.dbRuntime)
      : null;

    const syncDbOptionRowSelection = function (form) {
      if (!form) {
        return;
      }

      var groups = {};
      Array.from(form.querySelectorAll('.db-option-row[data-db-radio]')).forEach(function (row) {
        var selector = row.getAttribute('data-db-radio');
        var input = selector ? form.querySelector(selector) : null;
        if (!input) {
          return;
        }
        var name = input.getAttribute('name') || '';
        if (!groups[name]) {
          groups[name] = [];
        }
        groups[name].push({ row: row, input: input });
      });

      Object.keys(groups).forEach(function (name) {
        groups[name].forEach(function (item) {
          item.row.classList.toggle('is-selected', !!item.input.checked);
          item.row.classList.toggle('table-primary', !!item.input.checked);
        });
      });
    };

    const syncDatabaseFormState = function (form, runtime) {
      if (!form || !runtime) {
        return;
      }

      lastDatabaseRuntime = runtime;

      var mainMysqlEnvironment = String(runtime.mainMysqlEnvironment || '');
      var environment = String(runtime.dbRenderEnvironment || '');
      var mode = String(runtime.dbRenderOperationalMode || '');

      if (mainMysqlEnvironment) {
        var mysqlRadio = form.querySelector('input[name="main_db_environment"][value="' + mainMysqlEnvironment + '"]');
        if (mysqlRadio) {
          mysqlRadio.checked = true;
        }
      }

      if (environment) {
        var envRadio = form.querySelector('input[name="sybase_environment"][value="' + environment + '"]');
        if (envRadio) {
          envRadio.checked = true;
        }
      }

      if (mode) {
        var modeRadio = form.querySelector('input[name="sybase_operational_mode"][value="' + mode + '"]');
        if (modeRadio) {
          modeRadio.checked = true;
        }
      }

      syncDbOptionRowSelection(form);
    };

    const buildRuntimeFromDatabaseForm = function (form, baseRuntime) {
      if (!form) {
        return baseRuntime || null;
      }

      var runtime = Object.assign({}, baseRuntime || {});
      var mainMysqlInput = form.querySelector('input[name="main_db_environment"]:checked');
      var sybaseEnvironmentInput = form.querySelector('input[name="sybase_environment"]:checked');
      var sybaseModeInput = form.querySelector('input[name="sybase_operational_mode"]:checked');
      var sybaseEnvironment = sybaseEnvironmentInput ? String(sybaseEnvironmentInput.value || '') : String(runtime.dbRenderEnvironment || '');
      var sybaseMode = sybaseModeInput ? String(sybaseModeInput.value || '') : String(runtime.dbRenderOperationalMode || '');
      var mainMysqlEnvironment = mainMysqlInput ? String(mainMysqlInput.value || '') : String(runtime.mainMysqlEnvironment || '');

      if (sybaseEnvironment === 'production' || sybaseEnvironment === 'development') {
        runtime.dbRenderEnvironment = sybaseEnvironment;
        runtime.activeLogical = sybaseEnvironment === 'development' ? 'ehrmdb_dev' : 'ehrmdb';
        runtime.activeBase = sybaseEnvironment === 'development' ? 'sybase_ehrmdb_dev' : 'sybase_ehrmdb';
        runtime.runtimeStaffBase = sybaseEnvironment === 'development' ? 'sybase_staff_dev' : 'sybase_staff_prod';
        runtime.runtimeStudentBase = sybaseEnvironment === 'development' ? 'sybase_student_dev' : 'sybase_student_prod';
      }

      if (sybaseMode === 'staff_only' || sybaseMode === 'staff_student') {
        runtime.dbRenderOperationalMode = sybaseMode;
        runtime.studentRuntimeLabel = sybaseMode === 'staff_student'
          ? (runtime.runtimeStudentBase || (sybaseEnvironment === 'development' ? 'sybase_student_dev' : 'sybase_student_prod'))
          : (additionalLabel ? additionalLabel('config_tab_db_runtime_disabled', 'Disabled') : 'Disabled');
      }

      if (mainMysqlEnvironment === 'production' || mainMysqlEnvironment === 'development') {
        runtime.mainMysqlEnvironment = mainMysqlEnvironment;
        runtime.mysqlActiveResolvedKey = mainMysqlEnvironment === 'development' ? 'mysql_dev' : 'mysql_prod';

        var mysqlTarget = mainMysqlEnvironment === 'development' ? runtime.mysqlDevTarget : runtime.mysqlProdTarget;
        if (mysqlTarget && typeof mysqlTarget === 'object') {
          runtime.mysqlDriver = mysqlTarget.driver || runtime.mysqlDriver || 'mysql';
          runtime.mysqlHost = mysqlTarget.host || runtime.mysqlHost || '-';
          runtime.mysqlDatabase = mysqlTarget.database || runtime.mysqlDatabase || '-';
          runtime.mysqlUser = mysqlTarget.user || runtime.mysqlUser || '-';
        }
      }

      return runtime;
    };

    const writeDatabaseRuntimeDomFromForm = function (form, runtime) {
      runtime = buildRuntimeFromDatabaseForm(form, runtime || lastDatabaseRuntime);
      if (!runtime) {
        return;
      }

      lastDatabaseRuntime = runtime;

      var setText = function (id, value) {
        var el = document.getElementById(id);
        if (el) {
          el.textContent = String(value == null || value === '' ? '-' : value);
        }
      };
      var targetText = function (target) {
        target = target && typeof target === 'object' ? target : {};
        return String(target.host || '-') + ' / ' + String(target.database || '-') + ' / ' + String(target.user || '-');
      };
      var targetHtml = function (target, dedicated) {
        return escapeHtml(targetText(target))
          + (dedicated
            ? ' <span class="badge bg-success-subtle text-success ms-1">dedicated env</span>'
            : ' <span class="badge bg-secondary-subtle text-secondary ms-1">fallback</span>');
      };

      setText('db-runtime-staff', runtime.runtimeStaffBase || '-');
      setText('db-runtime-environment', getDbEnvironmentLabel(runtime.dbRenderEnvironment));
      setText('db-runtime-mode', getDbModeLabel(runtime.dbRenderOperationalMode));
      setText('db-runtime-mysql-environment', getDbEnvironmentLabel(runtime.mainMysqlEnvironment));
      setText('db-runtime-mysql-resolved-key', runtime.mysqlActiveResolvedKey || '-');
      setText('db-runtime-mysql-driver', runtime.mysqlDriver || 'mysql');
      setText('db-runtime-mysql-host', runtime.mysqlHost || '-');
      setText('db-runtime-mysql-database', runtime.mysqlDatabase || '-');
      setText('db-runtime-mysql-user', runtime.mysqlUser || '-');

      var studentCell = document.getElementById('db-runtime-student-cell');
      if (studentCell) {
        var studentLabel = runtime.studentRuntimeLabel || 'Disabled';
        if (runtime.dbRenderOperationalMode === 'staff_student') {
          studentCell.innerHTML = '<code class="text-primary" id="db-runtime-student"></code>';
        } else {
          studentCell.innerHTML = '<span class="badge bg-secondary-subtle text-secondary" id="db-runtime-student"></span>';
        }
        setText('db-runtime-student', studentLabel);
      }

      var prodTargetEl = document.getElementById('db-runtime-mysql-prod-target');
      if (prodTargetEl) {
        prodTargetEl.innerHTML = targetHtml(runtime.mysqlProdTarget, !!runtime.mysqlProdDedicated);
      }
      var devTargetEl = document.getElementById('db-runtime-mysql-dev-target');
      if (devTargetEl) {
        devTargetEl.innerHTML = targetHtml(runtime.mysqlDevTarget, !!runtime.mysqlDevDedicated);
      }
      var diagnosticEl = document.getElementById('db-runtime-mysql-diagnostic');
      if (diagnosticEl) {
        diagnosticEl.innerHTML = runtime.mysqlSameTarget
          ? '<span class="badge bg-warning-subtle text-warning"><i class="ri-alert-line me-1"></i>Production dan development resolve ke target yang sama</span>'
          : '<span class="badge bg-success-subtle text-success"><i class="ri-checkbox-circle-line me-1"></i>Production dan development resolve ke target berbeza</span>';
      }
    };

    const applyPayloadUiSync = function (payload, form) {
      if (!payload || typeof payload !== 'object') {
        return;
      }

      if (payload.tab === 'db' && payload.data && payload.data.dbRuntime) {
        var mergedRuntime = buildRuntimeFromDatabaseForm(form, payload.data.dbRuntime);
        writeDatabaseRuntimeDomFromForm(form, mergedRuntime);
        updateDatabaseRuntimeSummary(mergedRuntime);
        syncDatabaseFormState(form, mergedRuntime);
      } else if (payload.tab === 'db') {
        var formRuntime = buildRuntimeFromDatabaseForm(form, lastDatabaseRuntime);
        if (formRuntime) {
          writeDatabaseRuntimeDomFromForm(form, formRuntime);
          updateDatabaseRuntimeSummary(formRuntime);
          syncDatabaseFormState(form, formRuntime);
        }
      }

      if (payload.tab === 'db' && payload.data && Array.isArray(payload.data.additionalConnections)) {
        additionalConnections = payload.data.additionalConnections.slice();
        renderAdditionalConnectionsTable();
      }
      if (payload.tab === 'db' && payload.data && payload.data.additionalDiagnostics) {
        renderAdditionalDiagnostics(payload.data.additionalDiagnostics);
      }

      if (payload.tab === 'theme' && payload.data && payload.data.themeSettings) {
        applySavedThemeSettings(payload.data.themeSettings);
        syncThemeFormState(form, payload.data.themeSettings);
      }

      if (payload.tab === 'general' && payload.data && payload.data.generalSettings) {
        applyGeneralSettings(payload.data.generalSettings, form);
      }

      if (payload.tab === 'email' && payload.data && payload.data.emailSettings) {
        syncEmailFormState(form, payload.data.emailSettings);
      }

      if (payload.tab === 'lang' && payload.data && payload.data.languageData) {
        syncLanguageFormState(form, payload.data.languageData);
      }

      if (payload.tab === 'auth' && payload.data && payload.data.authSettings) {
        applySavedAuthSettings(payload.data.authSettings, form);
      } else if (payload.tab === 'auth') {
        if (typeof window.__tetapanRefreshAuthPolicySummary === 'function') {
          window.__tetapanRefreshAuthPolicySummary();
        }
      }

      if (payload.tab === 'ai-chatbot' && payload.data && payload.data.aiChatbotSettings) {
        syncAiChatbotFormState(form, payload.data.aiChatbotSettings);
      }
    };
    window.__tetapanApplyPayloadUiSync = applyPayloadUiSync;
    const dbAdditionalTableBody = document.getElementById('db-additional-table-body');
    const dbAdditionalEmpty = document.getElementById('db-additional-empty');
    const dbAdditionalCounter = document.getElementById('db-additional-counter');
    const dbAdditionalSearch = document.getElementById('db-additional-search');
    const dbAdditionalFamilyFilter = document.getElementById('db-additional-family-filter');
    const dbAdditionalStatusFilter = document.getElementById('db-additional-status-filter');
    const dbAdditionalCreateButton = document.getElementById('btn-db-additional-create');
    const dbAdditionalRefreshButton = document.getElementById('btn-db-additional-refresh');
    const dbAdditionalModalEl = document.getElementById('db-additional-modal');
    const dbAdditionalViewModalEl = document.getElementById('db-additional-view-modal');
    const dbAdditionalViewModalTitle = document.getElementById('db-additional-view-modal-title');
    const dbAdditionalViewModalSubtitle = document.getElementById('db-additional-view-modal-subtitle');
    const dbAdditionalViewModalKicker = document.getElementById('db-additional-view-modal-kicker');
    const dbAdditionalViewModalBody = document.getElementById('db-additional-view-modal-body');
    const dbAdditionalChildViewModalEl = document.getElementById('db-additional-child-view-modal');
    const dbAdditionalChildViewModalTitle = document.getElementById('db-additional-child-view-modal-title');
    const dbAdditionalChildViewModalSubtitle = document.getElementById('db-additional-child-view-modal-subtitle');
    const dbAdditionalChildViewModalKicker = document.getElementById('db-additional-child-view-modal-kicker');
    const dbAdditionalChildViewModalBody = document.getElementById('db-additional-child-view-modal-body');
    const dbAdditionalForm = document.getElementById('form-db-additional');
    const dbAdditionalSaveButton = document.getElementById('btn-db-additional-save');
    const dbAdditionalEnvRows = document.getElementById('db-additional-env-rows');
    const dbAdditionalEnvAddButton = document.getElementById('btn-db-additional-env-add');
    const dbAdditionalDiagnostics = document.getElementById('db-additional-diagnostics');
    const dbAdditionalDiagnosticsSummary = document.getElementById('db-additional-diagnostics-summary');
    const dbAdditionalDiagnosticsCount = document.getElementById('db-additional-diagnostics-count');
    const dbAdditionalDiagnosticsWarnings = document.getElementById('db-additional-diagnostics-warnings');

    const escapeHtml = function (value) {
      return String(value == null ? '' : value)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
    };

    const renderAdditionalDiagnostics = function (diagnostics) {
      if (!dbAdditionalDiagnostics || !diagnostics || typeof diagnostics !== 'object') {
        return;
      }

      additionalDiagnostics = diagnostics;
      var status = String(diagnostics.status || 'healthy');
      var tone = status === 'attention' ? 'danger' : (status === 'warning' ? 'warning' : 'success');
      dbAdditionalDiagnostics.classList.remove('alert-success', 'alert-warning', 'alert-danger');
      dbAdditionalDiagnostics.classList.add('alert-' + tone);

      if (dbAdditionalDiagnosticsSummary) {
        var drivers = Array.isArray(diagnostics.available_drivers) && diagnostics.available_drivers.length
          ? diagnostics.available_drivers.join(', ')
          : 'none';
        dbAdditionalDiagnosticsSummary.textContent = [
          'Runtime ' + String(diagnostics.runtime_os || '-').toUpperCase(),
          'PDO ' + drivers,
          additionalLabel('config_tab_db_additional_diagnostics_enabled', '%s enabled').replace('%s', String(diagnostics.enabled_count || 0)),
          additionalLabel('config_tab_db_additional_diagnostics_active_rows', '%s active environment rows').replace('%s', String(diagnostics.active_env_count || 0)),
          String((diagnostics.platform_coverage && diagnostics.platform_coverage.available_count) || 0)
            + '/' + String((diagnostics.platform_coverage && diagnostics.platform_coverage.required_count) || 0)
            + ' platform coverage'
        ].join(' · ');
      }

      if (dbAdditionalDiagnosticsCount) {
        dbAdditionalDiagnosticsCount.classList.remove('bg-success', 'bg-warning', 'bg-danger');
        dbAdditionalDiagnosticsCount.classList.add('bg-' + tone);
        dbAdditionalDiagnosticsCount.textContent = additionalLabel('config_tab_db_additional_diagnostics_warning_count', '%s warning(s)')
          .replace('%s', String(diagnostics.warning_count || 0));
      }

      if (dbAdditionalDiagnosticsWarnings) {
        var warnings = Array.isArray(diagnostics.warnings) ? diagnostics.warnings : [];
        dbAdditionalDiagnosticsWarnings.classList.toggle('d-none', warnings.length === 0);
        dbAdditionalDiagnosticsWarnings.innerHTML = warnings.slice(0, 8).map(function (warning) {
          return '<li><code>' + escapeHtml(warning.connection_code || '-') + '</code> — '
            + escapeHtml(warning.message || '') + '</li>';
        }).join('') + (warnings.length > 8
          ? '<li>' + escapeHtml(additionalLabel('config_tab_db_additional_diagnostics_more_warnings', '%s more warning(s) are not shown.')
              .replace('%s', String(warnings.length - 8))) + '</li>'
          : '');
      }
    };

    const getAdditionalConnectionFilters = function () {
      return {
        search: dbAdditionalSearch ? String(dbAdditionalSearch.value || '').trim().toLowerCase() : '',
        family: dbAdditionalFamilyFilter ? String(dbAdditionalFamilyFilter.value || '').trim().toLowerCase() : '',
        status: dbAdditionalStatusFilter ? String(dbAdditionalStatusFilter.value || '').trim().toLowerCase() : ''
      };
    };

    const flattenEnvSummary = function (envRows) {
      if (!Array.isArray(envRows) || envRows.length === 0) {
        return [];
      }

      return envRows.map(function (row) {
        var environment = String(row.f_environment || '-');
        var osFamily = String(row.f_os_family || 'any');
        var driver = String(row.f_driver || '-');
        return environment + ' / ' + osFamily + ' / ' + driver;
      });
    };

    const getLastTestSummary = function (envRows) {
      if (!Array.isArray(envRows) || envRows.length === 0) {
        return __('config_tab_db_additional_last_test_none') || 'Belum diuji';
      }

      var datedRows = envRows
        .filter(function (row) { return row && row.f_last_tested_at; })
        .sort(function (a, b) {
          return String(b.f_last_tested_at || '').localeCompare(String(a.f_last_tested_at || ''));
        });

      if (!datedRows.length) {
        return __('config_tab_db_additional_last_test_none') || 'Belum diuji';
      }

      var latest = datedRows[0];
      var status = String(latest.f_last_test_status || '').toUpperCase();
      return status + ' · ' + String(latest.f_last_tested_at || '');
    };

    const getFilteredAdditionalConnections = function () {
      var filters = getAdditionalConnectionFilters();

      return additionalConnections.filter(function (item) {
        var family = String(item.f_family || '').toLowerCase();
        var enabled = !!Number(item.f_is_enabled || 0);
        var haystack = [
          item.f_code,
          item.f_name,
          item.f_family,
          item.f_purpose,
          item.f_notes
        ].join(' ').toLowerCase();

        if (filters.search && haystack.indexOf(filters.search) === -1) {
          return false;
        }
        if (filters.family && family !== filters.family) {
          return false;
        }
        if (filters.status === 'enabled' && !enabled) {
          return false;
        }
        if (filters.status === 'disabled' && enabled) {
          return false;
        }
        return true;
      });
    };

    const renderAdditionalConnectionsTable = function () {
      if (!dbAdditionalTableBody) {
        return;
      }

      var items = getFilteredAdditionalConnections();
      if (dbAdditionalCounter) {
        dbAdditionalCounter.textContent = String(items.length);
      }

      if (!items.length) {
        dbAdditionalTableBody.innerHTML = '';
        if (dbAdditionalEmpty) {
          dbAdditionalEmpty.classList.remove('d-none');
        }
        return;
      }

      if (dbAdditionalEmpty) {
        dbAdditionalEmpty.classList.add('d-none');
      }

      dbAdditionalTableBody.innerHTML = items.map(function (item) {
        var code = String(item.f_code || '');
        var envSummary = flattenEnvSummary(item.env_rows || []);
        var enabled = !!Number(item.f_is_enabled || 0);
        var statusBadge = enabled
          ? '<span class="badge bg-success-subtle text-success">' + escapeHtml(__('config_tab_db_additional_enabled') || 'Enabled') + '</span>'
          : '<span class="badge bg-secondary-subtle text-secondary">' + escapeHtml(__('config_tab_db_additional_disabled') || 'Disabled') + '</span>';
        var family = escapeHtml(item.f_family || '-');
        var purpose = escapeHtml(item.f_purpose || '-');
        var name = escapeHtml(item.f_name || code || '-');
        var envHtml = envSummary.length
          ? envSummary.map(function (label) {
              return '<span class="db-additional-pill">' + escapeHtml(label) + '</span>';
            }).join('')
          : '<span class="text-muted small">' + escapeHtml(__('config_tab_db_additional_no_env_rows') || 'No env rows') + '</span>';
        var schemaTitle = __('config_tab_db_additional_schema_title') || 'Schema Preview';
        var inspectTitle = __('config_tab_db_additional_inspect_title') || 'Additional Connection Details';
        var sampleTitle = __('config_tab_db_additional_sample_code') || 'Sample Code';
        var editTitle = __('config_tab_db_additional_edit') || 'Edit';
        var testTitle = __('config_tab_db_additional_test') || 'Test Connection';
        var toggleTitle = enabled
          ? (__('config_tab_db_additional_disable') || 'Disable')
          : (__('config_tab_db_additional_enable') || 'Enable');
        return ''
          + '<tr data-connection-code="' + escapeHtml(code) + '">'
          +   '<td>'
          +     '<div class="db-additional-code">' + escapeHtml(code) + '</div>'
          +     '<div class="db-additional-meta"><span class="badge bg-light text-dark border">' + family + '</span></div>'
          +   '</td>'
          +   '<td>' + name + '</td>'
          +   '<td><span class="badge bg-info-subtle text-info">' + family.toUpperCase() + '</span></td>'
          +   '<td>' + purpose + '</td>'
          +   '<td><div class="db-additional-meta">' + envHtml + '</div></td>'
          +   '<td>' + statusBadge + '</td>'
          +   '<td><div class="db-additional-test-result">' + escapeHtml(getLastTestSummary(item.env_rows || [])) + '</div></td>'
          +   '<td class="text-start">'
          +     '<div class="db-additional-actions">'
          +       '<button type="button" class="btn btn-sm btn-outline-secondary icon-btn" title="' + escapeHtml(schemaTitle) + '" aria-label="' + escapeHtml(schemaTitle) + '" data-db-additional-action="schema" data-code="' + escapeHtml(code) + '"><i class="ri-table-line"></i></button>'
          +       '<button type="button" class="btn btn-sm btn-outline-info icon-btn" title="' + escapeHtml(inspectTitle) + '" aria-label="' + escapeHtml(inspectTitle) + '" data-db-additional-action="inspect" data-code="' + escapeHtml(code) + '"><i class="ri-eye-line"></i></button>'
          +       '<button type="button" class="btn btn-sm btn-outline-dark icon-btn" title="' + escapeHtml(sampleTitle) + '" aria-label="' + escapeHtml(sampleTitle) + '" data-db-additional-action="sample-code" data-code="' + escapeHtml(code) + '"><i class="ri-code-s-slash-line"></i></button>'
          +       '<button type="button" class="btn btn-sm btn-outline-primary icon-btn" title="' + escapeHtml(editTitle) + '" aria-label="' + escapeHtml(editTitle) + '" data-db-additional-action="edit" data-code="' + escapeHtml(code) + '"><i class="ri-edit-line"></i></button>'
          +       '<button type="button" class="btn btn-sm btn-outline-success icon-btn" title="' + escapeHtml(testTitle) + '" aria-label="' + escapeHtml(testTitle) + '" data-db-additional-action="test" data-code="' + escapeHtml(code) + '"><i class="ri-plug-line"></i></button>'
          +       '<button type="button" class="btn btn-sm ' + (enabled ? 'btn-outline-danger' : 'btn-outline-success') + ' icon-btn" title="' + escapeHtml(toggleTitle) + '" aria-label="' + escapeHtml(toggleTitle) + '" data-db-additional-action="toggle" data-code="' + escapeHtml(code) + '" data-enabled="' + (enabled ? '1' : '0') + '"><i class="ri-power-line"></i></button>'
          +     '</div>'
          +   '</td>'
          + '</tr>';
      }).join('');
    };

    window.__tetapanRenderAdditionalConnectionsTable = renderAdditionalConnectionsTable;

    const buildAdditionalViewMetaItem = function (label, content) {
      return '<div class="db-additional-view-meta-item"><div class="db-additional-view-meta-label">' + escapeHtml(label) + '</div><div class="db-additional-view-meta-value">' + content + '</div></div>';
    };

    const additionalLabel = function (key, fallback) {
      var value = __(key);
      return value && value !== key ? value : fallback;
    };

    const getPreferredAdditionalEnvRow = function (connection) {
      var rows = connection && Array.isArray(connection.env_rows) ? connection.env_rows : [];
      if (!rows.length) {
        return null;
      }
      var runtimeOs = String((additionalDiagnostics && additionalDiagnostics.runtime_os) || '').toLowerCase();
      var availableDrivers = Array.isArray(additionalDiagnostics && additionalDiagnostics.available_drivers)
        ? additionalDiagnostics.available_drivers.map(function (driver) { return String(driver).toLowerCase(); })
        : [];
      var candidates = rows.filter(function (row) {
        var rowOs = String(row.f_os_family || 'any').toLowerCase();
        var driver = String(row.f_driver || '').toLowerCase();
        return !!Number(row.f_is_active || 0)
          && (rowOs === runtimeOs || rowOs === 'any')
          && (!availableDrivers.length || availableDrivers.indexOf(driver) !== -1);
      });
      candidates.sort(function (left, right) {
        var leftExact = String(left.f_os_family || 'any').toLowerCase() === runtimeOs ? 0 : 1;
        var rightExact = String(right.f_os_family || 'any').toLowerCase() === runtimeOs ? 0 : 1;
        return leftExact - rightExact;
      });
      return candidates[0] || null;
    };

    const buildAdditionalSampleCodeBlock = function (title, code) {
      var encoded = encodeURIComponent(code);
      return ''
        + '<div class="db-sample-code-card">'
        +   '<div class="db-sample-code-card-header">'
        +     '<h6><i class="ri-code-box-line"></i>' + escapeHtml(title) + '</h6>'
        +     '<button type="button" class="btn btn-sm btn-outline-secondary db-rounded-btn" onclick="return window.__tetapanCopyCodeBlock(\'' + encoded + '\', this)">'
        +       '<i class="ri-file-copy-line me-1"></i> ' + escapeHtml(additionalLabel('config_tab_db_additional_copy', 'Copy'))
        +     '</button>'
        +   '</div>'
        +   '<pre class="db-sample-code-pre"><code>' + escapeHtml(code) + '</code></pre>'
        + '</div>';
    };

    const buildAdditionalSampleCodeTabs = function (samples) {
      var safeSamples = Array.isArray(samples) ? samples.filter(function (sample) {
        return sample && sample.id && sample.title && sample.code;
      }) : [];
      if (!safeSamples.length) return '';

      var nav = safeSamples.map(function (sample, index) {
        return ''
          + '<button type="button" class="db-sample-code-tab' + (index === 0 ? ' is-active' : '') + '" data-sample-tab="' + escapeHtml(sample.id) + '">'
          +   '<i class="' + escapeHtml(sample.icon || 'ri-code-box-line') + '"></i>'
          +   '<span>' + escapeHtml(sample.title) + '</span>'
          + '</button>';
      }).join('');

      var panes = safeSamples.map(function (sample, index) {
        var encoded = encodeURIComponent(sample.code);
        return ''
          + '<div class="db-sample-code-pane' + (index === 0 ? ' is-active' : '') + '" data-sample-pane="' + escapeHtml(sample.id) + '">'
          +   '<div class="db-sample-code-card">'
          +     '<div class="db-sample-code-card-header">'
          +       '<div>'
          +         '<h6><i class="' + escapeHtml(sample.icon || 'ri-code-box-line') + '"></i>' + escapeHtml(sample.title) + '</h6>'
          +         (sample.description ? '<p>' + escapeHtml(sample.description) + '</p>' : '')
          +       '</div>'
          +       '<button type="button" class="btn btn-sm btn-outline-secondary db-rounded-btn" onclick="return window.__tetapanCopyCodeBlock(\'' + encoded + '\', this)">'
          +         '<i class="ri-file-copy-line me-1"></i> ' + escapeHtml(additionalLabel('config_tab_db_additional_copy', 'Copy'))
          +       '</button>'
          +     '</div>'
          +     '<pre class="db-sample-code-pre"><code>' + escapeHtml(sample.code) + '</code></pre>'
          +   '</div>'
          + '</div>';
      }).join('');

      return ''
        + '<div class="db-sample-code-tabs">'
        +   '<div class="db-sample-code-tablist" role="tablist">' + nav + '</div>'
        +   '<div class="db-sample-code-panes">' + panes + '</div>'
        + '</div>';
    };

    const showAdditionalConnectionSampleCode = function (connection) {
      if (!connection) {
        showTetapanSystemError(additionalLabel('config_tab_db_additional_not_found', 'Additional connection not found.'));
        return;
      }

      var code = String(connection.f_code || '');
      var family = String(connection.f_family || '-');
      var purpose = String(connection.f_purpose || '-');
      var envRow = getPreferredAdditionalEnvRow(connection);
      var environment = envRow ? String(envRow.f_environment || 'production') : 'production';
      var databaseName = envRow ? String(envRow.f_database_name || '-') : '-';
      var previewQuery = ['sybase', 'mssql'].indexOf(family.toLowerCase()) !== -1
        ? 'SELECT TOP 10 * FROM nama_table'
        : 'SELECT * FROM nama_table LIMIT 10';
      var dataTablesPageSql = ['sybase', 'mssql'].indexOf(family.toLowerCase()) !== -1
        ? "SELECT id, kod, nama, status FROM nama_table ' . $where . ' ORDER BY nama OFFSET :start ROWS FETCH NEXT :length ROWS ONLY"
        : "SELECT id, kod, nama, status FROM nama_table ' . $where . ' ORDER BY nama LIMIT :length OFFSET :start";
      var serviceSample = "<?php\n"
        + "require_once __DIR__ . '/../classes/Database.php';\n\n"
        + "final class AdditionalDbService\n"
        + "{\n"
        + "    private PDO $pdo;\n\n"
        + "    public function __construct(?PDO $pdo = null)\n"
        + "    {\n"
        + "        $this->pdo = $pdo ?: Database::pdoAdditional('" + code + "', '" + environment + "');\n"
        + "    }\n\n"
        + "    public function healthCheck(): array\n"
        + "    {\n"
        + "        $stmt = $this->pdo->query('SELECT 1 AS ok');\n"
        + "        return $stmt->fetch(PDO::FETCH_ASSOC) ?: [];\n"
        + "    }\n"
        + "}\n";
      var repositorySample = "<?php\n"
        + "require_once __DIR__ . '/../classes/Database.php';\n\n"
        + "final class AdditionalLookupRepository\n"
        + "{\n"
        + "    public function __construct(private readonly PDO $pdo)\n"
        + "    {\n"
        + "    }\n\n"
        + "    public static function make(): self\n"
        + "    {\n"
        + "        return new self(Database::pdoAdditional('" + code + "', '" + environment + "'));\n"
        + "    }\n\n"
        + "    public function findById(int $id): ?array\n"
        + "    {\n"
        + "        $stmt = $this->pdo->prepare('SELECT * FROM nama_table WHERE id = :id');\n"
        + "        $stmt->execute([':id' => $id]);\n"
        + "        $row = $stmt->fetch(PDO::FETCH_ASSOC);\n\n"
        + "        return is_array($row) ? $row : null;\n"
        + "    }\n"
        + "}\n";
      var controllerSample = "<?php\n"
        + "require_once __DIR__ . '/../repositories/AdditionalLookupRepository.php';\n\n"
        + "final class LaporanController\n"
        + "{\n"
        + "    public function detail(int $id): array\n"
        + "    {\n"
        + "        try {\n"
        + "            $record = AdditionalLookupRepository::make()->findById($id);\n"
        + "            return ['success' => true, 'data' => $record];\n"
        + "        } catch (Throwable $e) {\n"
        + "            error_log('[additional-db:" + code + "] ' . $e->getMessage());\n"
        + "            return ['success' => false, 'message' => 'Sumber data tambahan tidak tersedia.'];\n"
        + "        }\n"
        + "    }\n"
        + "}\n";
      var transactionSample = "<?php\n"
        + "require_once __DIR__ . '/../classes/Database.php';\n\n"
        + "$pdo = Database::pdoAdditional('" + code + "', '" + environment + "');\n\n"
        + "$pdo->beginTransaction();\n"
        + "try {\n"
        + "    $stmt = $pdo->prepare('UPDATE nama_table SET status = :status WHERE id = :id');\n"
        + "    $stmt->execute([':status' => 'aktif', ':id' => $id]);\n"
        + "    $pdo->commit();\n"
        + "} catch (Throwable $e) {\n"
        + "    $pdo->rollBack();\n"
        + "    throw $e;\n"
        + "}\n";
      var readOnlySample = "<?php\n"
        + "require_once __DIR__ . '/../classes/Database.php';\n\n"
        + "try {\n"
        + "    $pdo = Database::pdoAdditional('" + code + "', '" + environment + "');\n"
        + "    $stmt = $pdo->prepare('" + previewQuery + "');\n"
        + "    $stmt->execute();\n"
        + "    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);\n"
        + "} catch (Throwable $e) {\n"
        + "    error_log('[additional-db:" + code + "] ' . $e->getMessage());\n"
        + "    throw $e;\n"
        + "}\n";
      var ajaxEndpointSample = "<?php\n"
        + "require_once __DIR__ . '/../../classes/Database.php';\n"
        + "header('Content-Type: application/json');\n\n"
        + "try {\n"
        + "    $pdo = Database::pdoAdditional('" + code + "', '" + environment + "');\n"
        + "    $stmt = $pdo->prepare('SELECT * FROM nama_table WHERE status = :status');\n"
        + "    $stmt->execute([':status' => $_POST['status'] ?? 'aktif']);\n\n"
        + "    echo json_encode([\n"
        + "        'success' => true,\n"
        + "        'data' => $stmt->fetchAll(PDO::FETCH_ASSOC),\n"
        + "    ]);\n"
        + "} catch (Throwable $e) {\n"
        + "    error_log('[additional-db:" + code + "] ' . $e->getMessage());\n"
        + "    http_response_code(500);\n"
        + "    echo json_encode(['success' => false, 'message' => 'Gagal memuatkan data.']);\n"
        + "}\n";
      var dataTablesSample = "<?php\n"
        + "require_once __DIR__ . '/../../classes/Database.php';\n"
        + "header('Content-Type: application/json');\n\n"
        + "$pdo = Database::pdoAdditional('" + code + "', '" + environment + "');\n"
        + "$draw = (int)($_POST['draw'] ?? 1);\n"
        + "$start = max(0, (int)($_POST['start'] ?? 0));\n"
        + "$length = min(100, max(10, (int)($_POST['length'] ?? 10)));\n"
        + "$search = trim((string)($_POST['search']['value'] ?? ''));\n\n"
        + "$where = '';\n"
        + "$params = [];\n"
        + "if ($search !== '') {\n"
        + "    $where = 'WHERE nama LIKE :search OR kod LIKE :search';\n"
        + "    $params[':search'] = '%' . $search . '%';\n"
        + "}\n\n"
        + "$total = (int)$pdo->query('SELECT COUNT(*) FROM nama_table')->fetchColumn();\n"
        + "$countStmt = $pdo->prepare('SELECT COUNT(*) FROM nama_table ' . $where);\n"
        + "$countStmt->execute($params);\n"
        + "$filtered = (int)$countStmt->fetchColumn();\n\n"
        + "$sql = '" + dataTablesPageSql + "';\n"
        + "$stmt = $pdo->prepare($sql);\n"
        + "foreach ($params as $key => $value) {\n"
        + "    $stmt->bindValue($key, $value);\n"
        + "}\n"
        + "$stmt->bindValue(':start', $start, PDO::PARAM_INT);\n"
        + "$stmt->bindValue(':length', $length, PDO::PARAM_INT);\n"
        + "$stmt->execute();\n\n"
        + "echo json_encode([\n"
        + "    'draw' => $draw,\n"
        + "    'recordsTotal' => $total,\n"
        + "    'recordsFiltered' => $filtered,\n"
        + "    'data' => $stmt->fetchAll(PDO::FETCH_ASSOC),\n"
        + "]);\n";
      var dropdownSample = "<?php\n"
        + "require_once __DIR__ . '/../classes/Database.php';\n\n"
        + "function additionalStatusOptions(): array\n"
        + "{\n"
        + "    $pdo = Database::pdoAdditional('" + code + "', '" + environment + "');\n"
        + "    $stmt = $pdo->prepare('SELECT kod, nama FROM ref_status WHERE aktif = :aktif ORDER BY nama');\n"
        + "    $stmt->execute([':aktif' => 1]);\n\n"
        + "    return array_map(static fn (array $row): array => [\n"
        + "        'value' => $row['kod'],\n"
        + "        'label' => $row['nama'],\n"
        + "    ], $stmt->fetchAll(PDO::FETCH_ASSOC));\n"
        + "}\n";
      var insertUpdateSample = "<?php\n"
        + "require_once __DIR__ . '/../classes/Database.php';\n\n"
        + "$pdo = Database::pdoAdditional('" + code + "', '" + environment + "');\n"
        + "$payload = [\n"
        + "    ':id' => (int)$id,\n"
        + "    ':nama' => trim((string)$nama),\n"
        + "    ':status' => $status,\n"
        + "];\n\n"
        + "$stmt = $pdo->prepare('\n"
        + "    UPDATE nama_table\n"
        + "    SET nama = :nama, status = :status, updated_at = CURRENT_TIMESTAMP\n"
        + "    WHERE id = :id\n"
        + "');\n"
        + "$stmt->execute($payload);\n\n"
        + "if ($stmt->rowCount() === 0) {\n"
        + "    $insert = $pdo->prepare('\n"
        + "        INSERT INTO nama_table (id, nama, status, created_at)\n"
        + "        VALUES (:id, :nama, :status, CURRENT_TIMESTAMP)\n"
        + "    ');\n"
        + "    $insert->execute($payload);\n"
        + "}\n";
      var batchSyncSample = "<?php\n"
        + "require_once __DIR__ . '/../classes/Database.php';\n\n"
        + "$external = Database::pdoAdditional('" + code + "', '" + environment + "');\n"
        + "$local = Database::pdo();\n\n"
        + "$rows = $external->query('SELECT kod, nama, updated_at FROM nama_table')->fetchAll(PDO::FETCH_ASSOC);\n"
        + "$upsert = $local->prepare('\n"
        + "    INSERT INTO local_lookup (kod, nama, source_updated_at)\n"
        + "    VALUES (:kod, :nama, :updated_at)\n"
        + "    ON DUPLICATE KEY UPDATE nama = VALUES(nama), source_updated_at = VALUES(source_updated_at)\n"
        + "');\n\n"
        + "$local->beginTransaction();\n"
        + "try {\n"
        + "    foreach ($rows as $row) {\n"
        + "        $upsert->execute([\n"
        + "            ':kod' => $row['kod'],\n"
        + "            ':nama' => $row['nama'],\n"
        + "            ':updated_at' => $row['updated_at'],\n"
        + "        ]);\n"
        + "    }\n"
        + "    $local->commit();\n"
        + "} catch (Throwable $e) {\n"
        + "    $local->rollBack();\n"
        + "    throw $e;\n"
        + "}\n";
      var samples = [
        {
          id: 'service',
          title: additionalLabel('config_tab_db_additional_sample_service', 'Service'),
          icon: 'ri-service-line',
          description: 'Wrapper kecil untuk connection supaya controller tidak pegang detail database.',
          code: serviceSample
        },
        {
          id: 'repository',
          title: additionalLabel('config_tab_db_additional_sample_repository', 'Repository'),
          icon: 'ri-database-2-line',
          description: 'Pattern yang disyorkan untuk query prepared statement.',
          code: repositorySample
        },
        {
          id: 'controller',
          title: additionalLabel('config_tab_db_additional_sample_controller', 'Controller'),
          icon: 'ri-layout-2-line',
          description: 'Controller panggil repository dan handle error pada boundary feature.',
          code: controllerSample
        },
        {
          id: 'transaction',
          title: additionalLabel('config_tab_db_additional_sample_transaction', 'Transaction'),
          icon: 'ri-loop-left-line',
          description: 'Gunakan untuk operasi tulis yang perlu commit atau rollback.',
          code: transactionSample
        },
        {
          id: 'readonly',
          title: additionalLabel('config_tab_db_additional_sample_readonly', 'Read-only'),
          icon: 'ri-search-line',
          description: 'Contoh query laporan/lookup dengan error logging.',
          code: readOnlySample
        },
        {
          id: 'ajax',
          title: additionalLabel('config_tab_db_additional_sample_ajax', 'Ajax Endpoint'),
          icon: 'ri-exchange-box-line',
          description: 'Endpoint JSON standard untuk page yang fetch data tanpa reload.',
          code: ajaxEndpointSample
        },
        {
          id: 'datatables',
          title: additionalLabel('config_tab_db_additional_sample_datatables', 'DataTables'),
          icon: 'ri-table-2',
          description: 'Server-side response shape untuk listing DataTables.',
          code: dataTablesSample
        },
        {
          id: 'dropdown',
          title: additionalLabel('config_tab_db_additional_sample_dropdown', 'Dropdown'),
          icon: 'ri-list-check',
          description: 'Lookup option untuk select/filter daripada database tambahan.',
          code: dropdownSample
        },
        {
          id: 'insert-update',
          title: additionalLabel('config_tab_db_additional_sample_insert_update', 'Insert Update'),
          icon: 'ri-edit-box-line',
          description: 'Pattern upsert ringkas dengan prepared statement dan rowCount.',
          code: insertUpdateSample
        },
        {
          id: 'batch-sync',
          title: additionalLabel('config_tab_db_additional_sample_batch_sync', 'Batch Sync'),
          icon: 'ri-loop-right-line',
          description: 'Sync data daripada DB tambahan ke local DB dalam transaction.',
          code: batchSyncSample
        }
      ];

      var html = ''
        + '<div class="db-additional-view-shell db-sample-code-shell">'
        +   '<div class="db-additional-view-meta">'
        +     buildAdditionalViewMetaItem(additionalLabel('config_tab_db_additional_code', 'Code'), '<code>' + escapeHtml(code) + '</code>')
        +     buildAdditionalViewMetaItem(additionalLabel('config_tab_db_additional_env', 'Environment'), escapeHtml(environment))
        +     buildAdditionalViewMetaItem(additionalLabel('config_tab_db_additional_family', 'Family'), escapeHtml(family))
        +     buildAdditionalViewMetaItem(additionalLabel('config_tab_db_additional_database', 'Database'), escapeHtml(databaseName))
        +   '</div>'
        +   '<div class="db-additional-view-card">'
        +     '<div class="db-additional-view-card-header">'
        +       '<h6 class="db-additional-view-card-title"><i class="ri-braces-line"></i> ' + escapeHtml(additionalLabel('config_tab_db_additional_sample_code_programmer', 'Sample Code for Programmer')) + '</h6>'
        +     '</div>'
        +     '<div class="db-additional-view-card-body">'
        +       '<div class="db-sample-code-note">' + escapeHtml(additionalLabel('config_tab_db_additional_sample_code_note', 'Use this helper so credentials, environment, driver fallback, and PDO cache are controlled by the system registry. Do not hardcode DSN, host, username, or password in modules.')) + '</div>'
        +       buildAdditionalSampleCodeTabs(samples)
        +     '</div>'
        +   '</div>'
        + '</div>';

      openAdditionalViewModal({
        title: additionalLabel('config_tab_db_additional_sample_code', 'Sample Code'),
        subtitle: code + ' / ' + environment + ' / ' + purpose,
        icon: 'ri-code-s-slash-line',
        variant: 'code',
        html: html
      });
    };

    window.__tetapanCopyCodeBlock = function (encodedCode, buttonEl) {
      var code = decodeURIComponent(String(encodedCode || ''));
      var done = function () {
        if (!buttonEl) return;
        var original = buttonEl.innerHTML;
        buttonEl.innerHTML = '<i class="ri-check-line me-1"></i> ' + escapeHtml(additionalLabel('config_tab_db_additional_copied', 'Copied'));
        setTimeout(function () {
          buttonEl.innerHTML = original;
        }, 1200);
      };

      if (navigator.clipboard && typeof navigator.clipboard.writeText === 'function') {
        navigator.clipboard.writeText(code).then(done).catch(function () {});
      } else {
        var textarea = document.createElement('textarea');
        textarea.value = code;
        textarea.setAttribute('readonly', 'readonly');
        textarea.style.position = 'fixed';
        textarea.style.left = '-9999px';
        document.body.appendChild(textarea);
        textarea.select();
        try { document.execCommand('copy'); done(); } catch (error) {}
        textarea.remove();
      }
      return false;
    };

    document.addEventListener('click', function (event) {
      var tab = event.target && event.target.closest ? event.target.closest('.db-sample-code-tab[data-sample-tab]') : null;
      if (!tab) {
        return;
      }

      var shell = tab.closest('.db-sample-code-tabs');
      if (!shell) {
        return;
      }

      var target = tab.getAttribute('data-sample-tab');
      shell.querySelectorAll('.db-sample-code-tab').forEach(function (button) {
        var active = button === tab;
        button.classList.toggle('is-active', active);
        button.setAttribute('aria-selected', active ? 'true' : 'false');
      });
      shell.querySelectorAll('.db-sample-code-pane').forEach(function (pane) {
        pane.classList.toggle('is-active', pane.getAttribute('data-sample-pane') === target);
      });
    });

    const destroyAdditionalViewDataTables = function (modalEl) {
      if (!(window.jQuery && jQuery.fn && jQuery.fn.DataTable) || !modalEl) {
        return;
      }
      jQuery(modalEl).find('table').each(function () {
        if (jQuery.fn.dataTable.isDataTable(this)) {
          jQuery(this).DataTable().destroy();
        }
      });
    };

    const initAdditionalViewDataTable = function (selector, options) {
      if (!(window.jQuery && jQuery.fn && jQuery.fn.DataTable) || !selector) {
        return;
      }
      var table = document.querySelector(selector);
      if (!table) {
        return;
      }
      if (jQuery.fn.dataTable.isDataTable(table)) {
        jQuery(table).DataTable().destroy();
      }
      var searchPlaceholder = options && options.searchPlaceholder ? options.searchPlaceholder : additionalLabel('config_tab_db_additional_search_short', 'Search');
      var extraOptions = Object.assign({}, options || {});
      delete extraOptions.searchPlaceholder;
      var baseOptions = {
        pageLength: 5,
        lengthChange: false,
        lengthMenu: [5, 10, 25, 50, 100, 200],
        autoWidth: false,
        responsive: false,
        ordering: false,
        order: [],
        language: {
          search: '',
          searchPlaceholder: searchPlaceholder
        },
        dom: '<"row mb-2"<"col-sm-12 col-md-6 dt-top-left"l><"col-sm-12 col-md-6 d-flex justify-content-md-end dt-top-right"f>>'
          + 't'
          + '<"dt-bottom-row mt-2 d-flex justify-content-between align-items-center"<"dt-info-left"i><"dt-paging-right d-flex justify-content-end"p>>',
        drawCallback: function () {
          jQuery('.dataTables_paginate > .pagination').addClass('pagination-rounded');
        }
      };
      var dtOptions = Object.assign(baseOptions, extraOptions);
      if (window.DataTableStandard && typeof window.DataTableStandard.options === 'function') {
        dtOptions = window.DataTableStandard.options(dtOptions);
      }
      var dt = jQuery(table).DataTable(dtOptions);
      if (window.DataTableStandard && typeof window.DataTableStandard.decorate === 'function') {
        window.DataTableStandard.decorate(selector, {
          controlsClass: 'mb-2',
          searchPlaceholder: searchPlaceholder
        });
      }
      var wrapperSelector = selector + '_wrapper';
      jQuery(wrapperSelector + ' .dataTables_length select').addClass('form-select w-auto');
      jQuery(wrapperSelector + ' .dataTables_length label').addClass('mb-0');
      jQuery(wrapperSelector + ' .dataTables_filter input').attr('placeholder', searchPlaceholder);
      jQuery(wrapperSelector + ' .dt-top-left').addClass('d-flex align-items-center gap-2 flex-nowrap');
      jQuery(wrapperSelector + ' .dt-top-right').addClass('align-items-center gap-2 flex-nowrap');
      setTimeout(function () {
        dt.columns.adjust().draw(false);
      }, 80);
    };

    const cleanupAdditionalModalBackdrops = function () {
      var hasShownModal = document.querySelector('.modal.show');
      if (!hasShownModal) {
        document.querySelectorAll('.modal-backdrop').forEach(function (backdrop) {
          backdrop.remove();
        });
        document.body.classList.remove('modal-open');
      }
    };

    const normalizeAdditionalModalStack = function () {
      var parentShown = dbAdditionalViewModalEl && dbAdditionalViewModalEl.classList.contains('show');
      var childShown = dbAdditionalChildViewModalEl && dbAdditionalChildViewModalEl.classList.contains('show');
      var backdrops = Array.prototype.slice.call(document.querySelectorAll('.modal-backdrop.show'));

      backdrops.forEach(function (backdrop) {
        backdrop.classList.remove('db-additional-view-backdrop', 'db-additional-child-view-backdrop');
      });

      if (parentShown && !childShown && backdrops.length > 1) {
        backdrops.slice(0, -1).forEach(function (backdrop) {
          backdrop.remove();
        });
        backdrops = Array.prototype.slice.call(document.querySelectorAll('.modal-backdrop.show'));
      }

      if (parentShown && backdrops.length) {
        backdrops[0].classList.add('db-additional-view-backdrop');
      }
      if (childShown && backdrops.length) {
        backdrops[backdrops.length - 1].classList.add('db-additional-child-view-backdrop');
      }
    };

    const suspendAdditionalParentModal = function () {
      if (!dbAdditionalViewModalEl || !dbAdditionalViewModalEl.classList.contains('show')) {
        return;
      }
      dbAdditionalViewModalEl.classList.add('db-additional-parent-suspended');
      dbAdditionalViewModalEl.setAttribute('aria-hidden', 'true');
    };

    const resumeAdditionalParentModal = function () {
      if (!dbAdditionalViewModalEl) {
        return;
      }
      dbAdditionalViewModalEl.classList.remove('db-additional-parent-suspended');
      if (dbAdditionalViewModalEl.classList.contains('show')) {
        dbAdditionalViewModalEl.removeAttribute('aria-hidden');
        document.body.classList.add('modal-open');
      }
    };

    const openAdditionalViewModal = function (config) {
      if (!dbAdditionalViewModalEl || !dbAdditionalViewModalBody) {
        if (window.Swal && typeof window.Swal.fire === 'function') {
          window.Swal.fire({
            icon: 'info',
            title: config && config.title ? config.title : additionalLabel('config_tab_db_additional_connection', 'Additional Connection'),
            html: config && config.html ? config.html : '',
            width: 760,
            confirmButtonText: __('config_js_btn_ok') || 'OK'
          });
        }
        return;
      }
      if (dbAdditionalViewModalEl.parentElement !== document.body) {
        document.body.appendChild(dbAdditionalViewModalEl);
      }
      cleanupAdditionalModalBackdrops();
      destroyAdditionalViewDataTables(dbAdditionalViewModalEl);
      dbAdditionalViewModalEl.classList.toggle('db-additional-view-modal-pink', config.variant === 'pink');
      dbAdditionalViewModalEl.classList.toggle('db-additional-view-modal-code', config.variant === 'code');
      if (dbAdditionalViewModalTitle) {
        dbAdditionalViewModalTitle.textContent = config.title || additionalLabel('config_tab_db_additional_connection', 'Additional Connection');
      }
      if (dbAdditionalViewModalSubtitle) {
        dbAdditionalViewModalSubtitle.textContent = config.subtitle || '';
      }
      if (dbAdditionalViewModalKicker) {
        dbAdditionalViewModalKicker.innerHTML = '<i class="' + escapeHtml(config.icon || 'ri-database-2-line') + ' me-2"></i>' + escapeHtml(__('config_tab_db_additional_header') || 'Additional Connections Registry');
      }
      dbAdditionalViewModalBody.innerHTML = config.html || '';
      var initTable = function () {
        dbAdditionalViewModalEl.removeEventListener('shown.bs.modal', initTable);
        initAdditionalViewDataTable(config.datatableSelector, config.datatableOptions || {});
      };
      dbAdditionalViewModalEl.addEventListener('shown.bs.modal', initTable);
      dbAdditionalViewModalEl.addEventListener('shown.bs.modal', normalizeAdditionalModalStack, { once: true });
      dbAdditionalViewModalEl.addEventListener('hidden.bs.modal', cleanupAdditionalModalBackdrops, { once: true });
      if (window.bootstrap && window.bootstrap.Modal) {
        window.bootstrap.Modal.getOrCreateInstance(dbAdditionalViewModalEl).show();
        setTimeout(normalizeAdditionalModalStack, 80);
        return;
      }
      dbAdditionalViewModalEl.style.display = 'block';
      dbAdditionalViewModalEl.classList.add('show');
      initAdditionalViewDataTable(config.datatableSelector, config.datatableOptions || {});
      normalizeAdditionalModalStack();
    };

    const openAdditionalChildViewModal = function (config) {
      if (!dbAdditionalChildViewModalEl || !dbAdditionalChildViewModalBody) {
        openAdditionalViewModal(config);
        return;
      }
      if (dbAdditionalChildViewModalEl.parentElement !== document.body) {
        document.body.appendChild(dbAdditionalChildViewModalEl);
      }
      suspendAdditionalParentModal();
      destroyAdditionalViewDataTables(dbAdditionalChildViewModalEl);
      if (dbAdditionalChildViewModalTitle) {
        dbAdditionalChildViewModalTitle.textContent = config.title || additionalLabel('config_tab_db_additional_data_preview_title', 'Data Preview');
      }
      if (dbAdditionalChildViewModalSubtitle) {
        dbAdditionalChildViewModalSubtitle.textContent = config.subtitle || '';
      }
      if (dbAdditionalChildViewModalKicker) {
        dbAdditionalChildViewModalKicker.innerHTML = '<i class="' + escapeHtml(config.icon || 'ri-file-search-line') + ' me-2"></i>' + escapeHtml(config.title || additionalLabel('config_tab_db_additional_data_preview_title', 'Data Preview'));
      }
      dbAdditionalChildViewModalBody.innerHTML = config.html || '';
      var initTable = function () {
        dbAdditionalChildViewModalEl.removeEventListener('shown.bs.modal', initTable);
        initAdditionalViewDataTable(config.datatableSelector, config.datatableOptions || {});
      };
      var keepParentOpen = function () {
        dbAdditionalChildViewModalEl.removeEventListener('hidden.bs.modal', keepParentOpen);
        resumeAdditionalParentModal();
        normalizeAdditionalModalStack();
      };
      dbAdditionalChildViewModalEl.addEventListener('shown.bs.modal', initTable);
      dbAdditionalChildViewModalEl.addEventListener('shown.bs.modal', normalizeAdditionalModalStack, { once: true });
      dbAdditionalChildViewModalEl.addEventListener('hidden.bs.modal', keepParentOpen);
      if (window.bootstrap && window.bootstrap.Modal) {
        window.bootstrap.Modal.getOrCreateInstance(dbAdditionalChildViewModalEl).show();
        setTimeout(normalizeAdditionalModalStack, 80);
        return;
      }
      dbAdditionalChildViewModalEl.style.display = 'block';
      dbAdditionalChildViewModalEl.classList.add('show');
      initAdditionalViewDataTable(config.datatableSelector, config.datatableOptions || {});
      normalizeAdditionalModalStack();
    };

    window.__tetapanTogglePreviewRow = function (buttonEl) {
      var row = buttonEl && buttonEl.closest ? buttonEl.closest('tr') : null;
      if (!row) {
        return false;
      }
      var expanded = row.classList.toggle('is-expanded');
      buttonEl.textContent = expanded ? 'Sembunyi' : 'Papar';
      if (window.jQuery && jQuery.fn && jQuery.fn.DataTable) {
        var table = jQuery(row).closest('table');
        if (table.length && jQuery.fn.dataTable.isDataTable(table[0])) {
          table.DataTable().columns.adjust().draw(false);
        }
      }
      return false;
    };

    const showAdditionalConnectionProbe = function (probe) {
      var value = function (key) {
        return escapeHtml(probe && probe[key] != null && probe[key] !== '' ? probe[key] : '-');
      };
      var summaryItem = buildAdditionalViewMetaItem;

      var html = ''
        + '<div class="db-additional-view-shell">'
        +   '<div class="db-additional-view-meta">'
        +     summaryItem('Code', '<code>' + value('connection_code') + '</code>')
        +     summaryItem('Name', value('connection_name'))
        +     summaryItem(additionalLabel('config_tab_db_additional_env', 'Environment'), value('environment'))
        +     summaryItem(additionalLabel('config_tab_db_additional_database', 'Database'), value('database_name'))
        +   '</div>'
        +   '<div class="db-additional-view-card">'
        +     '<div class="db-additional-view-card-header">'
        +       '<h6 class="db-additional-view-card-title"><i class="ri-list-check-2"></i> Runtime Probe</h6>'
        +     '</div>'
        +     '<div class="db-additional-view-card-body">'
        +       '<div class="db-additional-view-table-wrap">'
        +       '<table id="db-additional-probe-dt" class="table table-sm table-hover align-middle db-additional-view-table">'
        +         '<thead><tr><th>Field</th><th>Value</th></tr></thead>'
        +         '<tbody>'
        +       '<tr><th>Family</th><td>' + value('family') + '</td></tr>'
        +       '<tr><th>Purpose</th><td>' + value('purpose') + '</td></tr>'
        +       '<tr><th>' + escapeHtml(additionalLabel('config_tab_db_additional_os_family', 'OS Family')) + '</th><td>' + value('os_family') + '</td></tr>'
        +       '<tr><th>' + ((__('config_tab_db_additional_configured_driver')) || 'Configured Driver') + '</th><td>' + value('configured_driver') + '</td></tr>'
        +       '<tr><th>' + ((__('config_tab_db_additional_active_driver')) || 'Active Driver') + '</th><td>' + value('active_driver') + '</td></tr>'
        +       '<tr><th>' + escapeHtml(additionalLabel('config_tab_db_mysql_host', 'Host')) + '</th><td>' + value('host') + '</td></tr>'
        +       '<tr><th>' + escapeHtml(additionalLabel('config_tab_emel_port', 'Port')) + '</th><td>' + value('port') + '</td></tr>'
        +       '<tr><th>' + ((__('config_tab_db_additional_current_db')) || 'Current Database') + '</th><td>' + value('current_database') + '</td></tr>'
        +       '<tr><th>' + ((__('config_tab_db_additional_current_user')) || 'Current User') + '</th><td>' + value('current_user') + '</td></tr>'
        +       '<tr><th>' + ((__('config_tab_db_additional_server_time')) || 'Server Time') + '</th><td>' + value('server_time') + '</td></tr>'
        +       '<tr><th>' + ((__('config_tab_db_additional_server_version')) || 'Server Version') + '</th><td>' + value('server_version') + '</td></tr>'
        +       '<tr><th>' + ((__('config_tab_db_additional_ping')) || 'Ping') + '</th><td>' + value('ping') + '</td></tr>'
        +         '</tbody>'
        +       '</table>'
        +       '</div>'
        +     '</div>'
        +   '</div>'
        + '</div>';

      openAdditionalViewModal({
        title: __('config_tab_db_additional_inspect_title') || 'Additional Connection Details',
        subtitle: value('connection_code') + ' / ' + value('environment') + ' / ' + value('active_driver'),
        icon: 'ri-eye-line',
        html: html,
        datatableSelector: '#db-additional-probe-dt',
        datatableOptions: {
          pageLength: 5,
          searchPlaceholder: additionalLabel('config_tab_db_additional_search_short', 'Search'),
          order: [],
          columnDefs: [{ targets: 0, width: '180px' }]
        }
      });
    };

    const showAdditionalConnectionSchemaPreview = function (schemaPreview) {
      if (!(window.Swal && typeof window.Swal.fire === 'function')) {
        return;
      }

      var objects = Array.isArray(schemaPreview && schemaPreview.objects) ? schemaPreview.objects : [];
      var rowsHtml = objects.length
        ? objects.map(function (item) {
            var code = encodeURIComponent(String(schemaPreview.connection_code || ''));
            var objectName = encodeURIComponent(String(item.object_name || ''));
            var environment = encodeURIComponent(String(schemaPreview.environment || 'production'));
            var osFamily = encodeURIComponent(String(schemaPreview.os_family || 'any'));
            var driver = encodeURIComponent(String(schemaPreview.driver || ''));
            return '<tr><td>' + escapeHtml(item.object_name || '-') + '</td><td>' + escapeHtml(item.object_type || '-') + '</td><td class="text-start"><button type="button" class="btn btn-sm btn-outline-primary" onclick="return window.__tetapanDataPreviewAdditionalConnection && window.__tetapanDataPreviewAdditionalConnection(\'' + code + '\', \'' + objectName + '\', \'' + environment + '\', \'' + osFamily + '\', \'' + driver + '\', this)"><i class="ri-file-search-line"></i></button></td></tr>';
          }).join('')
        : '<tr><td colspan="3" class="text-muted text-center py-3">' + ((__('config_tab_db_additional_no_objects')) || 'No objects found.') + '</td></tr>';

      var summaryItem = buildAdditionalViewMetaItem;
      var html = ''
        + '<div class="db-additional-view-shell">'
        + '<div class="db-additional-view-meta">'
        +   summaryItem('Code', '<code>' + escapeHtml(schemaPreview.connection_code || '-') + '</code>')
        +   summaryItem('Family', escapeHtml(schemaPreview.family || '-'))
        +   summaryItem(additionalLabel('config_tab_db_additional_env', 'Environment'), escapeHtml(schemaPreview.environment || '-'))
        +   summaryItem(additionalLabel('config_tab_db_additional_database', 'Database'), escapeHtml(schemaPreview.database_name || '-'))
        + '</div>'
        + '<div class="db-additional-view-card">'
        +   '<div class="db-additional-view-card-header">'
        +     '<h6 class="db-additional-view-card-title"><i class="ri-table-line"></i> ' + escapeHtml(__('config_tab_db_additional_schema_title') || 'Schema Preview') + '</h6>'
        +   '</div>'
        +   '<div class="db-additional-view-card-body">'
        +     '<div class="db-additional-view-table-wrap">'
        +     '<table id="db-additional-schema-dt" class="table table-sm table-hover align-middle db-additional-view-table">'
        +       '<thead><tr><th>' + ((__('config_tab_db_additional_object_name')) || 'Object Name') + '</th><th style="width:140px">' + ((__('config_tab_db_additional_object_type')) || 'Type') + '</th><th class="text-start" style="width:96px">' + ((__('config_tab_db_additional_preview_action')) || 'Preview') + '</th></tr></thead>'
        +       '<tbody>' + rowsHtml + '</tbody>'
        +     '</table>'
        +     '</div>'
        +   '</div>'
        + '</div>'
        + '</div>';

      openAdditionalViewModal({
        title: __('config_tab_db_additional_schema_title') || 'Schema Preview',
        subtitle: String(schemaPreview.connection_code || '-') + ' / ' + String(schemaPreview.environment || '-') + ' / ' + String(schemaPreview.database_name || '-'),
        icon: 'ri-table-line',
        html: html,
        datatableSelector: '#db-additional-schema-dt',
        datatableOptions: {
          pageLength: 5,
          searchPlaceholder: additionalLabel('config_tab_db_additional_search_short', 'Search'),
          columnDefs: [{ targets: 2, orderable: false, searchable: false }]
        }
      });
    };

    window.__tetapanDataPreviewAdditionalConnection = function (encodedCode, encodedObjectName, encodedEnvironment, encodedOsFamily, encodedDriver, buttonEl) {
      var code = decodeURIComponent(String(encodedCode || ''));
      var objectName = decodeURIComponent(String(encodedObjectName || ''));
      var environment = decodeURIComponent(String(encodedEnvironment || 'production'));
      var osFamily = decodeURIComponent(String(encodedOsFamily || 'any'));
      var driver = decodeURIComponent(String(encodedDriver || ''));

      postAdditionalConnectionAction('db_additional_object_preview', {
        connection_code: code,
        object_name: objectName,
        environment: environment,
        os_family: osFamily,
        driver: driver
      }, buttonEl)
        .then(function (payload) {
          if (!payload || payload.success !== true || !payload.data || !payload.data.objectPreview) {
            throw new Error((payload && payload.message) || additionalLabel('config_tab_db_additional_data_preview_failed', 'Failed to load additional connection data preview.'));
          }

          var preview = payload.data.objectPreview;
          var columns = Array.isArray(preview.columns) ? preview.columns : [];
          var rows = Array.isArray(preview.rows) ? preview.rows : [];
          var maskedColumns = Array.isArray(preview.masked_columns) ? preview.masked_columns : [];
          var headerHtml = columns.map(function (column) {
            return '<th>' + escapeHtml(column) + '</th>';
          }).join('') + '<th class="text-start db-preview-toggle-cell">Paparan</th>';
          var bodyHtml = rows.length
            ? rows.map(function (row) {
                return '<tr>' + columns.map(function (column) {
                  var value = row && row[column] != null ? String(row[column]) : '';
                  return '<td class="db-preview-cell">' + escapeHtml(value) + '</td>';
                }).join('') + '<td class="db-preview-toggle-cell"><button type="button" class="db-preview-toggle" onclick="return window.__tetapanTogglePreviewRow(this)">Papar</button></td></tr>';
              }).join('')
            : '<tr><td colspan="' + Math.max(columns.length + 1, 1) + '" class="text-muted text-center py-3">' + ((__('config_tab_db_additional_no_rows')) || 'No rows found.') + '</td></tr>';

          var html = ''
            + '<div class="db-additional-view-shell">'
            + '<div class="db-additional-view-meta">'
            + buildAdditionalViewMetaItem('Code', '<code>' + escapeHtml(preview.connection_code || '-') + '</code>')
            + buildAdditionalViewMetaItem('Object', escapeHtml(preview.object_name || '-'))
            + buildAdditionalViewMetaItem(additionalLabel('config_tab_db_additional_env', 'Environment'), escapeHtml(preview.environment || '-'))
            + buildAdditionalViewMetaItem(additionalLabel('config_tab_db_additional_database', 'Database'), escapeHtml(preview.database_name || '-'))
            + '</div>'
            + (maskedColumns.length || Number(preview.truncated_values || 0) || Number(preview.binary_values || 0)
              ? '<div class="alert alert-warning py-2 small">Preview protection: '
                + escapeHtml(String(maskedColumns.length)) + ' masked column(s), '
                + escapeHtml(String(preview.truncated_values || 0)) + ' truncated value(s), '
                + escapeHtml(String(preview.binary_values || 0)) + ' binary value(s).</div>'
              : '')
            + '<div class="db-additional-view-card">'
            + '<div class="db-additional-view-card-header"><h6 class="db-additional-view-card-title"><i class="ri-file-search-line"></i> ' + escapeHtml(__('config_tab_db_additional_data_preview_title') || 'Data Preview') + '</h6></div>'
            + '<div class="db-additional-view-card-body"><div class="db-additional-view-table-wrap">'
            + '<table id="db-additional-object-preview-dt" class="table table-sm table-hover align-middle db-additional-view-table">'
            + '<thead><tr>' + headerHtml + '</tr></thead>'
            + '<tbody>' + bodyHtml + '</tbody>'
            + '</table></div></div></div>'
            + '</div>';

          openAdditionalChildViewModal({
            title: __('config_tab_db_additional_data_preview_title') || 'Data Preview',
            subtitle: String(preview.object_name || '-') + ' / ' + String(preview.environment || '-') + ' / ' + String(preview.database_name || '-'),
            icon: 'ri-file-search-line',
            variant: 'pink',
            html: html,
            datatableSelector: '#db-additional-object-preview-dt',
            datatableOptions: {
              pageLength: 5,
              scrollX: true,
              searchPlaceholder: additionalLabel('config_tab_db_additional_search_short', 'Search'),
              columnDefs: [{ targets: columns.length, orderable: false, searchable: false, width: '10%' }]
            }
          });
        })
        .catch(function (error) {
          showTetapanSystemError(error && error.message ? error.message : additionalLabel('config_tab_db_additional_data_preview_failed', 'Failed to load additional connection data preview.'));
        });

      return false;
    };

    const ensureAdditionalModalMountedToBody = function () {
      if (!dbAdditionalModalEl) {
        return null;
      }

      if (dbAdditionalModalEl.parentElement !== document.body) {
        document.body.appendChild(dbAdditionalModalEl);
      }

      return dbAdditionalModalEl;
    };

    const getAdditionalModalInstance = function () {
      const mountedModalEl = ensureAdditionalModalMountedToBody();
      if (!mountedModalEl || !(window.bootstrap && window.bootstrap.Modal)) {
        return null;
      }
      return window.bootstrap.Modal.getOrCreateInstance(mountedModalEl);
    };

    ensureAdditionalModalMountedToBody();

    const buildEnvRowMarkup = function (row, index) {
      var safe = Object.assign({
        f_environment: 'production',
        f_os_family: 'any',
        f_driver: 'mysql',
        f_host: '',
        f_port: '',
        f_database_name: '',
        f_dsn_name: '',
        f_username: '',
        f_password_ciphertext: '',
        f_charset: 'utf8mb4',
        f_is_active: true
      }, row || {});

      return ''
        + '<div class="db-additional-env-row" data-env-row>'
        +   '<div class="db-additional-env-row-header">'
        +     '<div>'
        +       '<div class="db-additional-env-row-index">' + escapeHtml(additionalLabel('config_tab_db_additional_env_row', 'Env Row')) + ' ' + (index + 1) + '</div>'
        +       '<div class="db-additional-inline-help">' + escapeHtml(additionalLabel('config_tab_db_additional_env_row_help', 'Each row represents one environment, OS, and driver combination.')) + '</div>'
        +     '</div>'
        +     '<button type="button" class="btn btn-sm btn-outline-danger" data-env-row-remove><i class="ri-delete-bin-line me-1"></i>' + escapeHtml(additionalLabel('config_tab_db_additional_remove', 'Remove')) + '</button>'
        +   '</div>'
        +   '<div class="row g-3">'
        +     '<div class="col-md-3"><label class="form-label">' + escapeHtml(additionalLabel('config_tab_db_additional_env', 'Environment')) + '</label><select class="form-select" data-env-field="f_environment"><option value="production"' + (safe.f_environment === 'production' ? ' selected' : '') + '>' + escapeHtml(getDbEnvironmentLabel('production')) + '</option><option value="development"' + (safe.f_environment === 'development' ? ' selected' : '') + '>' + escapeHtml(getDbEnvironmentLabel('development')) + '</option></select></div>'
        +     '<div class="col-md-3"><label class="form-label">' + escapeHtml(additionalLabel('config_tab_db_additional_os_family', 'OS Family')) + '</label><select class="form-select" data-env-field="f_os_family"><option value="any"' + (safe.f_os_family === 'any' ? ' selected' : '') + '>' + escapeHtml(additionalLabel('config_tab_db_additional_os_any', 'Any')) + '</option><option value="windows"' + (safe.f_os_family === 'windows' ? ' selected' : '') + '>' + escapeHtml(additionalLabel('config_tab_db_additional_os_windows', 'Windows')) + '</option><option value="linux"' + (safe.f_os_family === 'linux' ? ' selected' : '') + '>' + escapeHtml(additionalLabel('config_tab_db_additional_os_linux', 'Linux')) + '</option></select></div>'
        +     '<div class="col-md-3"><label class="form-label">' + escapeHtml(additionalLabel('config_tab_db_mysql_driver', 'Driver')) + '</label><select class="form-select" data-env-field="f_driver"><option value="mysql"' + (safe.f_driver === 'mysql' ? ' selected' : '') + '>mysql</option><option value="odbc"' + (safe.f_driver === 'odbc' ? ' selected' : '') + '>odbc</option><option value="dblib"' + (safe.f_driver === 'dblib' ? ' selected' : '') + '>dblib</option><option value="sqlsrv"' + (safe.f_driver === 'sqlsrv' ? ' selected' : '') + '>sqlsrv</option></select></div>'
        +     '<div class="col-md-3"><label class="form-label">' + escapeHtml(additionalLabel('config_tab_db_additional_active', 'Active')) + '</label><div class="form-check form-switch pt-2"><input class="form-check-input" type="checkbox" data-env-field="f_is_active"' + (safe.f_is_active ? ' checked' : '') + '></div></div>'
        +     '<div class="col-md-4"><label class="form-label">' + escapeHtml(additionalLabel('config_tab_db_mysql_host', 'Host')) + '</label><input type="text" class="form-control" data-env-field="f_host" value="' + escapeHtml(safe.f_host) + '"></div>'
        +     '<div class="col-md-2"><label class="form-label">' + escapeHtml(additionalLabel('config_tab_emel_port', 'Port')) + '</label><input type="text" class="form-control" data-env-field="f_port" value="' + escapeHtml(safe.f_port) + '"></div>'
        +     '<div class="col-md-3"><label class="form-label">' + escapeHtml(additionalLabel('config_tab_db_additional_database', 'Database')) + '</label><input type="text" class="form-control" data-env-field="f_database_name" value="' + escapeHtml(safe.f_database_name) + '"></div>'
        +     '<div class="col-md-3"><label class="form-label">DSN</label><input type="text" class="form-control" data-env-field="f_dsn_name" value="' + escapeHtml(safe.f_dsn_name) + '"></div>'
        +     '<div class="col-md-4"><label class="form-label">' + escapeHtml(additionalLabel('config_tab_db_additional_username', 'Username')) + '</label><input type="text" class="form-control" data-env-field="f_username" value="' + escapeHtml(safe.f_username) + '"></div>'
        +     '<div class="col-md-4"><label class="form-label">' + escapeHtml(additionalLabel('config_tab_db_additional_password', 'Password')) + '</label><input type="password" class="form-control" data-env-field="f_password_ciphertext" value="' + escapeHtml(safe.f_password_ciphertext) + '"></div>'
        +     '<div class="col-md-4"><label class="form-label">' + escapeHtml(additionalLabel('config_tab_db_additional_charset', 'Charset')) + '</label><input type="text" class="form-control" data-env-field="f_charset" value="' + escapeHtml(safe.f_charset) + '"></div>'
        +   '</div>'
        + '</div>';
    };

    const reindexEnvRows = function () {
      if (!dbAdditionalEnvRows) {
        return;
      }
      Array.from(dbAdditionalEnvRows.querySelectorAll('[data-env-row]')).forEach(function (row, index) {
        var label = row.querySelector('.db-additional-env-row-index');
        if (label) {
          label.textContent = additionalLabel('config_tab_db_additional_env_row', 'Env Row') + ' ' + (index + 1);
        }
      });
    };

    const appendEnvRow = function (row) {
      if (!dbAdditionalEnvRows) {
        return;
      }
      var wrapper = document.createElement('div');
      wrapper.innerHTML = buildEnvRowMarkup(row, dbAdditionalEnvRows.querySelectorAll('[data-env-row]').length);
      var child = wrapper.firstElementChild;
      if (child) {
        dbAdditionalEnvRows.appendChild(child);
        reindexEnvRows();
      }
    };

    const appendPlatformPreset = function (osFamily, driver) {
      if (!dbAdditionalEnvRows) {
        return;
      }
      var environment = document.getElementById('db-additional-supports-prod').checked ? 'production' : 'development';
      var rows = serializeAdditionalEnvRows();
      var duplicate = rows.some(function (row) {
        return row.f_environment === environment && row.f_os_family === osFamily && row.f_driver === driver;
      });
      if (duplicate) {
        showTetapanSystemError('Variant ' + environment + ' / ' + osFamily + ' / ' + driver + ' sudah wujud.');
        return;
      }

      var base = rows.find(function (row) { return row.f_environment === environment && row.f_is_active; })
        || rows.find(function (row) { return row.f_environment === environment; })
        || {};
      appendEnvRow(Object.assign({}, base, {
        f_environment: environment,
        f_os_family: osFamily,
        f_driver: driver,
        f_dsn_name: driver === 'odbc' ? '' : String(base.f_dsn_name || ''),
        f_host: driver === 'odbc' ? '' : String(base.f_host || ''),
        f_port: driver === 'odbc' ? '' : String(base.f_port || ''),
        f_database_name: driver === 'odbc' ? '' : String(base.f_database_name || ''),
        f_password_ciphertext: '',
        f_is_active: true
      }));
    };

    const resetAdditionalConnectionForm = function () {
      if (!dbAdditionalForm) {
        return;
      }
      dbAdditionalForm.reset();
      document.getElementById('db-additional-form-type').value = 'db_additional_create';
      document.getElementById('db-additional-existing-code').value = '';
      document.getElementById('db-additional-registry-revision').value = '';
      document.getElementById('db-additional-code').readOnly = false;
      document.getElementById('db-additional-enabled').checked = true;
      document.getElementById('db-additional-supports-prod').checked = true;
      document.getElementById('db-additional-supports-dev').checked = false;
      var titleEl = document.getElementById('db-additional-modal-title');
      if (titleEl) {
        titleEl.textContent = __('config_tab_db_additional_modal_add') || 'Add Additional Connection';
      }
      if (dbAdditionalEnvRows) {
        dbAdditionalEnvRows.innerHTML = '';
      }
      appendEnvRow({ f_environment: 'production', f_os_family: 'any', f_driver: 'mysql', f_charset: 'utf8mb4', f_is_active: true });
    };

    const activateAdditionalModalFirstTab = function () {
      var firstTab = document.getElementById('tab-additional-connection-tab');
      if (!firstTab) {
        return;
      }

      if (window.bootstrap && window.bootstrap.Tab) {
        window.bootstrap.Tab.getOrCreateInstance(firstTab).show();
        return;
      }

      document.querySelectorAll('#db-additional-modal-tabs .nav-link').forEach(function (tab) {
        tab.classList.toggle('active', tab === firstTab);
        tab.setAttribute('aria-selected', tab === firstTab ? 'true' : 'false');
      });
      document.querySelectorAll('#db-additional-modal-tabs-content .tab-pane').forEach(function (pane) {
        var isFirst = pane.id === 'tab-additional-connection';
        pane.classList.toggle('show', isFirst);
        pane.classList.toggle('active', isFirst);
      });
    };

    const openAdditionalConnectionModal = function (connection) {
      if (!dbAdditionalForm) {
        return;
      }

      ensureAdditionalModalMountedToBody();
      resetAdditionalConnectionForm();
      activateAdditionalModalFirstTab();

      if (connection) {
        document.getElementById('db-additional-form-type').value = 'db_additional_update';
        document.getElementById('db-additional-existing-code').value = String(connection.f_code || '');
        document.getElementById('db-additional-registry-revision').value = String(connection.f_registry_revision || '');
        document.getElementById('db-additional-code').value = String(connection.f_code || '');
        document.getElementById('db-additional-code').readOnly = true;
        document.getElementById('db-additional-name').value = String(connection.f_name || '');
        document.getElementById('db-additional-purpose').value = String(connection.f_purpose || '');
        document.getElementById('db-additional-family').value = String(connection.f_family || 'mysql');
        document.getElementById('db-additional-driver-mode').value = String(connection.f_driver_mode || 'auto');
        document.getElementById('db-additional-notes').value = String(connection.f_notes || '');
        document.getElementById('db-additional-enabled').checked = !!Number(connection.f_is_enabled || 0);
        document.getElementById('db-additional-supports-prod').checked = !!Number(connection.f_supports_prod || 0);
        document.getElementById('db-additional-supports-dev').checked = !!Number(connection.f_supports_dev || 0);
        if (dbAdditionalEnvRows) {
          dbAdditionalEnvRows.innerHTML = '';
        }
        (Array.isArray(connection.env_rows) && connection.env_rows.length ? connection.env_rows : []).forEach(function (row) {
          appendEnvRow(row);
        });
        if (!dbAdditionalEnvRows.querySelector('[data-env-row]')) {
          appendEnvRow({ f_environment: 'production', f_os_family: 'any', f_driver: 'mysql', f_charset: 'utf8mb4', f_is_active: true });
        }
        var titleEl = document.getElementById('db-additional-modal-title');
        if (titleEl) {
          titleEl.textContent = __('config_tab_db_additional_modal_edit') || 'Edit Additional Connection';
        }
      }

      var modal = getAdditionalModalInstance();
      if (modal) {
        modal.show();
        return;
      }

      if (dbAdditionalModalEl) {
        dbAdditionalModalEl.style.display = 'block';
        dbAdditionalModalEl.classList.add('show');
        dbAdditionalModalEl.removeAttribute('aria-hidden');
        document.body.classList.add('modal-open');
      }
    };

    window.__tetapanOpenAdditionalConnectionModal = function (code) {
      if (code) {
        var existing = additionalConnections.find(function (item) {
          return String(item.f_code || '') === String(code);
        }) || null;
        openAdditionalConnectionModal(existing);
        return false;
      }

      openAdditionalConnectionModal(null);
      return false;
    };

    const serializeAdditionalEnvRows = function () {
      if (!dbAdditionalEnvRows) {
        return [];
      }

      return Array.from(dbAdditionalEnvRows.querySelectorAll('[data-env-row]')).map(function (row) {
        var payload = {};
        row.querySelectorAll('[data-env-field]').forEach(function (field) {
          var key = field.getAttribute('data-env-field');
          if (!key) {
            return;
          }
          if (field.type === 'checkbox') {
            payload[key] = !!field.checked;
            return;
          }
          payload[key] = String(field.value || '').trim();
        });
        return payload;
      });
    };

    const postAdditionalConnectionAction = function (formType, extraData, button) {
      var payload = new FormData();
      payload.set('ajax', '1');
      payload.set('csrf_token', csrfToken);
      payload.set('form_type', formType);

      Object.keys(extraData || {}).forEach(function (key) {
        payload.set(key, extraData[key]);
      });

      fallbackSetButtonLoading(button, true);

      return fetch(window.location.href, {
        method: 'POST',
        body: payload,
        noLoader: true,
        headers: Object.assign({
          'X-Requested-With': 'XMLHttpRequest',
          'Accept': 'application/json',
          'X-No-Loader': '1'
        }, csrfToken ? { 'X-CSRF-Token': csrfToken } : {})
      }).then(function (response) {
        return response.text().then(function (text) {
          var trimmed = String(text || '').trim();
          if (!trimmed) {
            throw new Error(additionalLabel('config_tab_db_additional_empty_response', 'Empty server response. Please check the server log for data preview.'));
          }

          try {
            return JSON.parse(trimmed);
          } catch (error) {
            throw new Error(__('config_js_invalid_server_response') || 'Respons pelayan tidak sah.');
          }
        });
      }).finally(function () {
        fallbackSetButtonLoading(button, false);
      });
    };

    window.__tetapanRefreshAdditionalConnections = function (buttonEl) {
      postAdditionalConnectionAction('db_additional_list', {}, buttonEl || dbAdditionalRefreshButton)
        .then(function (payload) {
          if (!payload || payload.success !== true) {
            throw new Error((payload && payload.message) || additionalLabel('config_tab_db_additional_refresh_failed', 'Failed to refresh additional connections.'));
          }
          if (payload.data && Array.isArray(payload.data.additionalConnections)) {
            additionalConnections = payload.data.additionalConnections.slice();
            renderAdditionalConnectionsTable();
          }
          if (payload.data && payload.data.additionalDiagnostics) {
            renderAdditionalDiagnostics(payload.data.additionalDiagnostics);
          }
        })
        .catch(function (error) {
          showTetapanSystemError(error && error.message ? error.message : additionalLabel('config_tab_db_additional_refresh_failed', 'Failed to refresh additional connections.'));
        });
      return false;
    };

    window.__tetapanSaveAdditionalConnection = function (buttonEl) {
      if (!dbAdditionalForm) {
        showTetapanSystemError(additionalLabel('config_tab_db_additional_form_missing', 'Additional connection form is not available.'));
        return false;
      }

      var formTypeField = document.getElementById('db-additional-form-type');
      var currentFormType = formTypeField ? String(formTypeField.value || 'db_additional_create') : 'db_additional_create';
      var extraData = {
        f_code: document.getElementById('db-additional-code').value,
        f_name: document.getElementById('db-additional-name').value,
        f_purpose: document.getElementById('db-additional-purpose').value,
        f_family: document.getElementById('db-additional-family').value,
        f_driver_mode: document.getElementById('db-additional-driver-mode').value,
        f_notes: document.getElementById('db-additional-notes').value,
        f_is_enabled: document.getElementById('db-additional-enabled').checked ? '1' : '0',
        f_supports_prod: document.getElementById('db-additional-supports-prod').checked ? '1' : '0',
        f_supports_dev: document.getElementById('db-additional-supports-dev').checked ? '1' : '0',
        existing_code: document.getElementById('db-additional-existing-code').value,
        f_registry_revision: document.getElementById('db-additional-registry-revision').value,
        env_rows: JSON.stringify(serializeAdditionalEnvRows())
      };

      postAdditionalConnectionAction(currentFormType, extraData, buttonEl || dbAdditionalSaveButton)
        .then(function (payload) {
          if (!payload || payload.success !== true) {
            throw new Error((payload && payload.message) || additionalLabel('config_tab_db_additional_save_failed', 'Failed to save additional connection.'));
          }
          applyPayloadUiSync(payload, formDB);
          var modal = getAdditionalModalInstance();
          if (modal) {
            modal.hide();
          }
          if (window.Swal && typeof window.Swal.fire === 'function') {
            window.Swal.fire({
              icon: 'success',
              title: payload.title || __('config_js_berjaya') || 'Success',
              text: payload.message || additionalLabel('config_tab_db_additional_save_success', 'Additional connection saved successfully.'),
              confirmButtonText: __('config_js_btn_ok') || 'OK'
            });
          }
        })
        .catch(function (error) {
          showTetapanSystemError(error && error.message ? error.message : additionalLabel('config_tab_db_additional_save_failed', 'Failed to save additional connection.'));
        });

      return false;
    };
    const cleanupOrphanedBackdrops = function () {
      const hasOpenModal = document.querySelector('.modal.show');
      const hasOpenOffcanvas = document.querySelector('.offcanvas.show');

      if (!hasOpenModal) {
        document.querySelectorAll('.modal-backdrop').forEach(function (backdrop) {
          backdrop.remove();
        });
      }

      if (!hasOpenOffcanvas) {
        document.querySelectorAll('.offcanvas-backdrop').forEach(function (backdrop) {
          backdrop.remove();
        });
      }

      if (!hasOpenModal && !hasOpenOffcanvas) {
        document.body.classList.remove('modal-open');
        document.body.style.removeProperty('overflow');
        document.body.style.removeProperty('padding-right');
      }
    };
    const setButtonLoading = typeof pageUiHelper.setButtonLoading === 'function'
      ? function (button, loading) {
          pageUiHelper.setButtonLoading(button, loading, {
            loadingText: __('config_js_btn_loading_save')
          });
        }
      : function (button, loading) {
          if (!button) {
            return;
          }
          if (loading) {
            button.disabled = true;
            button.dataset.originalHtml = button.innerHTML;
            button.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> ' + __('config_js_btn_loading_save');
            return;
          }
          button.disabled = false;
          if (button.dataset.originalHtml) {
            button.innerHTML = button.dataset.originalHtml;
            delete button.dataset.originalHtml;
        }
      };

    const bindBootstrapTabs = function () {
      if (!window.bootstrap || !window.bootstrap.Tab) {
        return;
      }

      document.querySelectorAll(
        '.container-fluid [data-bs-toggle="tab"], .container-fluid [data-bs-toggle="pill"]'
      ).forEach(function (trigger) {
        trigger.addEventListener('click', function (event) {
          if (trigger.tagName === 'A') {
            event.preventDefault();
          }
          window.bootstrap.Tab.getOrCreateInstance(trigger).show();
        });
      });
    };

    function getFieldLabel(field) {
      if (!field) {
        return __('config_js_field_fallback_label') || 'Ruangan';
      }

      const group = field.closest('.general-form-group, .email-settings-card, .db-settings-card, .theme-settings-card, .lang-settings-card, .form-group, .col-12, .col-sm-6, .col-md-6, .col-lg-6');
      const scopedLabel = group ? group.querySelector('label.form-label') : null;
      const directLabel = scopedLabel || document.querySelector('label[for="' + field.id + '"]');
      const text = directLabel ? directLabel.textContent : (field.getAttribute('aria-label') || field.name || __('config_js_field_fallback_label') || 'Ruangan');
      return String(text || __('config_js_field_fallback_label') || 'Ruangan').replace(/\s+/g, ' ').trim();
    }

    function clearFieldValidationState(field) {
      if (!field) {
        return;
      }

      field.classList.remove('is-invalid');
      const container = field.parentElement;
      if (!container) {
        return;
      }

      container.querySelectorAll('.tetapan-invalid-feedback').forEach(function (feedback) {
        feedback.remove();
      });
    }

    function clearSubtabErrorMarkers(form) {
      if (!form) {
        return;
      }

      form.querySelectorAll('.nav-link.has-validation-error').forEach(function (tab) {
        tab.classList.remove('has-validation-error');
        tab.removeAttribute('data-validation-error');
      });
    }

    function markFieldInvalid(field, message) {
      if (!field) {
        return;
      }

      clearFieldValidationState(field);
      field.classList.add('is-invalid');

      const container = field.parentElement;
      if (!container) {
        return;
      }

      const feedback = document.createElement('div');
      feedback.className = 'invalid-feedback tetapan-invalid-feedback d-block';
      feedback.textContent = message || field.validationMessage || __('config_js_invalid_input') || 'Input tidak sah.';
      container.appendChild(feedback);
    }

    function getFieldSubtabTrigger(field, form) {
      if (!field || !form) {
        return null;
      }

      const pane = field.closest('.tab-pane');
      if (!pane || !pane.id) {
        return null;
      }

      return form.querySelector('[data-bs-target="#' + pane.id + '"]');
    }

    function markSubtabError(field, form) {
      const trigger = getFieldSubtabTrigger(field, form);
      if (!trigger) {
        return;
      }

      trigger.classList.add('has-validation-error');
      trigger.setAttribute('data-validation-error', '1');
    }

    function showValidationAlert(form) {
      if (!form || typeof form.checkValidity !== 'function' || form.checkValidity()) {
        return false;
      }

      const invalidFields = Array.from(form.querySelectorAll(':invalid'));
      if (!invalidFields.length) {
        return false;
      }

      clearSubtabErrorMarkers(form);

      const errorItems = invalidFields.map(function (field) {
        const fieldLabel = getFieldLabel(field);
        const trigger = getFieldSubtabTrigger(field, form);
        const tabLabel = trigger ? String(trigger.textContent || '').replace(/\s+/g, ' ').trim() : '';
        const message = field.validationMessage
          ? (fieldLabel + ': ' + field.validationMessage)
          : (fieldLabel + ': ' + ((__('config_js_invalid_input')) || 'Input tidak sah.'));

        markFieldInvalid(field, message);
        markSubtabError(field, form);

        return {
          tabLabel: tabLabel,
          message: message
        };
      });

      const firstInvalidField = invalidFields[0];
      if (firstInvalidField && typeof firstInvalidField.focus === 'function') {
        firstInvalidField.focus({ preventScroll: false });
      }

      if (window.Swal && typeof window.Swal.fire === 'function') {
        const html = errorItems.map(function (item) {
          const prefix = item.tabLabel ? ('<strong>' + item.tabLabel + ':</strong> ') : '';
          return '<div class="text-start mb-2">' + prefix + item.message + '</div>';
        }).join('');

        window.Swal.fire({
          icon: 'warning',
          title: __('config_general_validation_title') || 'Semakan Diperlukan',
          html: html,
          confirmButtonText: __('config_js_btn_ok') || 'OK'
        });
      } else {
        window.alert(errorItems.map(function (item) {
          return (item.tabLabel ? (item.tabLabel + ': ') : '') + item.message;
        }).join('\n'));
      }

      return true;
    }

    function submitFormDirect(form, button) {
      if (!form) {
        return false;
      }

      var state = getFormState(form);
      if (state.pending) {
        return false;
      }

      if (showValidationAlert(form)) {
        setSaveFeedbackState(form, button, 'error', __('config_js_validation_review_marked') || 'Review the marked inputs before saving.');
        return false;
      }

      if (button) {
        setButtonLoading(button, true);
      }
      state.pending = true;
      setSaveFeedbackState(form, button, 'saving', __('config_js_saving_changes') || 'The system is saving your changes...');
      showTetapanLoader('submitFormDirect', __('config_js_saving_changes') || __('config_js_btn_loading_save') || 'Saving...');

      const formData = new FormData(form);
      formData.set('ajax', '1');
      const requestedTab = formData.get('form_type') === 'auth_settings' ? '#auth-tab' : null;
      if (requestedTab) {
        rememberActiveTab(requestedTab);
      }

      const csrfToken = document.querySelector('meta[name="csrf-token"]')
        ? document.querySelector('meta[name="csrf-token"]').getAttribute('content')
        : '';

      fetch(form.getAttribute('action') || window.location.href, {
        method: 'POST',
        body: formData,
        noLoader: true,
        headers: Object.assign({
          'X-Requested-With': 'XMLHttpRequest',
          'Accept': 'application/json',
          'X-No-Loader': '1'
        }, csrfToken ? { 'X-CSRF-Token': csrfToken } : {})
      })
        .then(function (response) {
          return response.json().catch(function () {
            throw new Error(__('config_js_invalid_server_response') || 'Respons pelayan tidak sah.');
          });
        })
        .then(function (payload) {
          if (!payload || payload.success !== true) {
            const title = payload && payload.title ? payload.title : __('config_js_ralat') || 'Ralat';
            const errors = payload && Array.isArray(payload.errors) ? payload.errors : [];
            const message = payload && payload.message ? payload.message : __('config_js_save_failed') || 'Gagal menyimpan tetapan.';
            const html = errors.length
              ? errors.map(function (item) {
                  return '<div class="text-start mb-2">' + item + '</div>';
                }).join('')
              : '<div class="text-start">' + message + '</div>';

            if (window.Swal && typeof window.Swal.fire === 'function') {
              window.Swal.fire({
                icon: 'error',
                title: title,
                html: html,
                confirmButtonText: __('config_js_btn_ok') || 'OK'
              });
            } else {
              window.alert(message);
            }
            setSaveFeedbackState(form, button, 'error', message);
            return;
          }

          applyPayloadUiSync(payload, form);
          if (payload.tab === 'db' && typeof window.__tetapanForceRuntimeSummaryFromDbForm === 'function') {
            window.__tetapanForceRuntimeSummaryFromDbForm(form);
          }

          if (payload.tab) {
            activateTab('#' + payload.tab + '-tab');
          } else if (requestedTab) {
            activateTab(requestedTab);
          }

          captureFormSnapshot(form);

          var warnings = payload && Array.isArray(payload.warnings) ? payload.warnings : [];
          if (warnings.length > 0) {
            setSaveFeedbackState(form, button, 'warning', payload.message || __('config_js_save_feedback_saved_with_warning') || 'Settings saved with a warning.');
            return;
          }

          setSaveFeedbackState(form, button, 'success', payload.message || __('config_js_save_success_default') || 'Tetapan berjaya disimpan.');
        })
        .catch(function (error) {
          const message = error && error.message ? error.message : __('config_js_save_system_error') || 'Ralat sistem semasa menyimpan tetapan.';
          if (window.Swal && typeof window.Swal.fire === 'function') {
            window.Swal.fire({
              icon: 'error',
              title: __('config_js_system_error_title') || 'Ralat Sistem',
              text: message,
              confirmButtonText: __('config_js_btn_ok') || 'OK'
            });
          } else {
            window.alert(message);
          }
          setSaveFeedbackState(form, button, 'error', message);
        })
        .finally(function () {
          state.pending = false;
          if (button) {
            setButtonLoading(button, false);
          }
          hideTetapanLoader('submitFormDirect');
          refreshDirtyIndicator(form, button);
        });

      return true;
    }

    window.__tetapanSubmitFormWithValidationImpl = function (form, button) {
      return submitFormDirect(form, button);
    };

    window.__tetapanSubmitAuthForm = function (event, form, buttonId) {
      if (event) {
        event.preventDefault();
        event.stopImmediatePropagation();
      }

      const activeForm = typeof form === 'string' ? document.getElementById(form) : form;
      const button = document.getElementById(buttonId || 'btn-simpan-auth');
      if (!activeForm) {
        showTetapanSystemError();
        return false;
      }

      if (showValidationAlert(activeForm)) {
        return false;
      }

      if (!(window.Swal && typeof window.Swal.fire === 'function')) {
        return submitFormDirect(activeForm, button);
      }

      window.Swal.fire({
        icon: 'question',
        title: __('config_tab_auth') || 'Login Policy',
        text: __('config_js_confirm_auth') || 'Are you sure you want to save this login policy?',
        showCancelButton: true,
        confirmButtonText: __('config_js_btn_ya_simpan') || 'Yes, Save',
        cancelButtonText: __('config_alert_no') || 'Cancel'
      }).then(function (result) {
        if (!result.isConfirmed) {
          return;
        }
        submitFormDirect(activeForm, button);
      });

      return false;
    };

    window.__tetapanBeforeLanguageSubmit = function (activeForm) {
      if (!activeForm) {
        return false;
      }
      const checked = activeForm.querySelectorAll('input[name="languages[]"]:checked');
      if (checked.length === 0) {
        Swal.fire({ icon: 'warning', title: __('config_js_tiada_bahasa'), text: __('config_js_pilih_bahasa'), confirmButtonText: __('config_js_btn_ok') });
        return false;
      }
      const defaultLang = activeForm.querySelector('input[name="default_language"]:checked');
      if (!defaultLang) {
        Swal.fire({ icon: 'warning', title: __('config_js_tiada_bahasa_default'), text: __('config_js_pilih_bahasa_default'), confirmButtonText: __('config_js_btn_ok') });
        return false;
      }
      return true;
    };

    function updateDatabaseRuntimeSummary(runtime) {
      if (!runtime) {
        return;
      }
      lastDatabaseRuntime = runtime;

      const mysqlTargetText = function (target) {
        target = target && typeof target === 'object' ? target : {};
        return String(target.host || '-') + ' / ' + String(target.database || '-') + ' / ' + String(target.user || '-');
      };

      const mysqlTargetHtml = function (target, dedicated) {
        return escapeHtml(mysqlTargetText(target))
          + (dedicated
            ? ' <span class="badge bg-success-subtle text-success ms-1">dedicated env</span>'
            : ' <span class="badge bg-secondary-subtle text-secondary ms-1">fallback</span>');
      };

      const staffEl = document.getElementById('db-runtime-staff');
      const studentCell = document.getElementById('db-runtime-student-cell');
      const environmentEl = document.getElementById('db-runtime-environment');
      const modeEl = document.getElementById('db-runtime-mode');
      const mysqlEnvironmentEl = document.getElementById('db-runtime-mysql-environment');
      const mysqlResolvedKeyEl = document.getElementById('db-runtime-mysql-resolved-key');
      const mysqlDriverEl = document.getElementById('db-runtime-mysql-driver');
      const mysqlHostEl = document.getElementById('db-runtime-mysql-host');
      const mysqlDatabaseEl = document.getElementById('db-runtime-mysql-database');
      const mysqlUserEl = document.getElementById('db-runtime-mysql-user');
      const mysqlProdTargetEl = document.getElementById('db-runtime-mysql-prod-target');
      const mysqlDevTargetEl = document.getElementById('db-runtime-mysql-dev-target');
      const mysqlDiagnosticEl = document.getElementById('db-runtime-mysql-diagnostic');

      if (staffEl && typeof runtime.runtimeStaffBase === 'string') {
        staffEl.textContent = runtime.runtimeStaffBase;
      }

      if (studentCell && typeof runtime.studentRuntimeLabel === 'string') {
        if (runtime.dbRenderOperationalMode === 'staff_student') {
          studentCell.innerHTML = '<code class="text-primary" id="db-runtime-student"></code>';
        } else {
          studentCell.innerHTML = '<span class="badge bg-secondary-subtle text-secondary" id="db-runtime-student"></span>';
        }
        const studentEl = document.getElementById('db-runtime-student');
        if (studentEl) {
          studentEl.textContent = runtime.studentRuntimeLabel;
        }
      }

      if (environmentEl) {
        environmentEl.textContent = getDbEnvironmentLabel(runtime.dbRenderEnvironment);
      }

      if (modeEl) {
        modeEl.textContent = getDbModeLabel(runtime.dbRenderOperationalMode);
      }

      if (mysqlEnvironmentEl) {
        mysqlEnvironmentEl.textContent = getDbEnvironmentLabel(runtime.mainMysqlEnvironment);
      }
      if (mysqlResolvedKeyEl) {
        mysqlResolvedKeyEl.textContent = String(runtime.mysqlActiveResolvedKey || '-');
      }
      if (mysqlDriverEl) {
        mysqlDriverEl.textContent = String(runtime.mysqlDriver || '-');
      }
      if (mysqlHostEl) {
        mysqlHostEl.textContent = String(runtime.mysqlHost || '-');
      }
      if (mysqlDatabaseEl) {
        mysqlDatabaseEl.textContent = String(runtime.mysqlDatabase || '-');
      }
      if (mysqlUserEl) {
        mysqlUserEl.textContent = String(runtime.mysqlUser || '-');
      }
      if (mysqlProdTargetEl) {
        mysqlProdTargetEl.innerHTML = mysqlTargetHtml(runtime.mysqlProdTarget, !!runtime.mysqlProdDedicated);
      }
      if (mysqlDevTargetEl) {
        mysqlDevTargetEl.innerHTML = mysqlTargetHtml(runtime.mysqlDevTarget, !!runtime.mysqlDevDedicated);
      }
      if (mysqlDiagnosticEl) {
        mysqlDiagnosticEl.innerHTML = runtime.mysqlSameTarget
          ? '<span class="badge bg-warning-subtle text-warning"><i class="ri-alert-line me-1"></i>Production dan development resolve ke target yang sama</span>'
          : '<span class="badge bg-success-subtle text-success"><i class="ri-checkbox-circle-line me-1"></i>Production dan development resolve ke target berbeza</span>';
      }
    }

    window.__tetapanSyncDatabaseRuntimeUi = function () {
      var form = document.getElementById('form-db-aktif');
      if (form) {
        var runtime = buildRuntimeFromDatabaseForm(form, lastDatabaseRuntime);
        if (runtime) {
          writeDatabaseRuntimeDomFromForm(form, runtime);
          updateDatabaseRuntimeSummary(runtime);
          syncDbOptionRowSelection(form);
          return;
        }
      }

      if (lastDatabaseRuntime) {
        updateDatabaseRuntimeSummary(lastDatabaseRuntime);
        return;
      }

      if (!form) {
        return;
      }

      var environmentInput = form.querySelector('input[name="sybase_environment"]:checked');
      var modeInput = form.querySelector('input[name="sybase_operational_mode"]:checked');
      var mysqlEnvironmentInput = form.querySelector('input[name="main_db_environment"]:checked');
      var environmentEl = document.getElementById('db-runtime-environment');
      var modeEl = document.getElementById('db-runtime-mode');
      var mysqlEnvironmentEl = document.getElementById('db-runtime-mysql-environment');

      if (environmentEl && environmentInput) {
        environmentEl.textContent = getDbEnvironmentLabel(environmentInput.value);
      }
      if (modeEl && modeInput) {
        modeEl.textContent = getDbModeLabel(modeInput.value);
      }
      if (mysqlEnvironmentEl && mysqlEnvironmentInput) {
        mysqlEnvironmentEl.textContent = getDbEnvironmentLabel(mysqlEnvironmentInput.value);
      }
      syncDbOptionRowSelection(form);
    };

    function applySavedThemeSettings(themeSettings) {
      if (!themeSettings) {
        return;
      }

      var layoutMode = String(themeSettings.layoutMode || '').trim();
      var topbarColor = String(themeSettings.topbarColor || '').trim();
      var sidebarColor = String(themeSettings.sidebarColor || '').trim();
      var topbarCustomSeed = String(themeSettings.topbarCustomSeed || '').trim();
      var sidebarCustomSeed = String(themeSettings.sidebarCustomSeed || '').trim();
      var topbar = document.getElementById('topbar');
      var sidebar = document.getElementById('leftside-menu');

      if (layoutMode) {
        document.documentElement.setAttribute('data-bs-theme', layoutMode);
        document.body.setAttribute('data-bs-theme', layoutMode);
        setStorageValue('theme.layout', layoutMode);
        setStorageValue('layout-mode', layoutMode);
        if (typeof window.updateThemeIcon === 'function') {
          window.updateThemeIcon(layoutMode === 'dark');
        } else {
          var themeIcon = document.getElementById('theme-mode-icon');
          if (themeIcon) {
            themeIcon.className = (layoutMode === 'dark') ? 'ri-sun-fill fs-22' : 'ri-moon-fill fs-22';
          }
        }
      }

      if (topbarColor) {
        document.documentElement.setAttribute('data-topbar-color', topbarColor);
        document.body.setAttribute('data-topbar-color', topbarColor);
        setStorageValue('theme.topbar', topbarColor);
        setStorageValue('topbar-color', topbarColor);
        if (topbar) {
          topbar.setAttribute('data-topbar-color', topbarColor);
          topbar.className = topbar.className
            .split(/\s+/)
            .filter(function (className) { return className && className.indexOf('topbar-') !== 0; })
            .join(' ');
          topbar.classList.add('topbar-' + topbarColor);
        }
      }

      if (sidebarColor) {
        document.documentElement.setAttribute('data-menu-color', sidebarColor);
        document.body.setAttribute('data-menu-color', sidebarColor);
        setStorageValue('theme.menu', sidebarColor);
        setStorageValue('sidebar-color', sidebarColor);
        if (sidebar) {
          sidebar.setAttribute('data-menu-color', sidebarColor);
        }
      }

      if (window.IQSThemePalette) {
        window.IQSThemePalette.applyTheme({
          topbarColor: topbarColor,
          sidebarColor: sidebarColor,
          topbarCustomSeed: topbarCustomSeed,
          sidebarCustomSeed: sidebarCustomSeed
        });
      }

      var config = {
        'data-bs-theme': layoutMode,
        'data-topbar-color': topbarColor,
        'data-menu-color': sidebarColor
      };
      Object.keys(config).forEach(function (name) {
        var value = config[name];
        if (!value) {
          return;
        }
        document.querySelectorAll('input[name="' + name + '"]').forEach(function (input) {
          input.checked = input.value === value;
        });
      });

      if (window.config) {
        if (layoutMode) {
          window.config.theme = layoutMode;
        }
        if (topbarColor) {
          window.config.topbar = window.config.topbar || {};
          window.config.topbar.color = topbarColor;
        }
        if (sidebarColor) {
          window.config.menu = window.config.menu || {};
          window.config.menu.color = sidebarColor;
        }
        try {
          window.sessionStorage.setItem('__CONFIG__', JSON.stringify(window.config));
        } catch (storageError) {
          // ignore blocked storage
        }
      }

      if (typeof window.__tetapanSyncThemeSectionUi === 'function') {
        window.__tetapanSyncThemeSectionUi();
      }
    }

    function initThemeSectionInteractions(form) {
      if (!form || form.dataset.themeSectionsInitialized === '1') {
        return;
      }

      const storageKey = 'tetapan-sistem.theme-sections';
      const sections = Array.from(form.querySelectorAll('[data-theme-section]'));
      if (!sections.length) {
        return;
      }

      let storedState = {};
      try {
        storedState = JSON.parse(window.sessionStorage.getItem(storageKey) || '{}') || {};
      } catch (storageError) {
        storedState = {};
      }

      const persistState = function () {
        const nextState = {};
        sections.forEach(function (section) {
          const key = section.getAttribute('data-theme-section') || '';
          const toggle = section.querySelector('[data-theme-toggle]');
          if (key && toggle) {
            nextState[key] = toggle.getAttribute('aria-expanded') === 'true';
          }
        });
        try {
          window.sessionStorage.setItem(storageKey, JSON.stringify(nextState));
        } catch (storageError) {
          // ignore storage errors
        }
      };

      const setExpanded = function (section, expanded) {
        const toggle = section.querySelector('[data-theme-toggle]');
        const panel = section.querySelector('.theme-settings-panel');
        if (!toggle || !panel) {
          return;
        }

        section.classList.toggle('is-expanded', !!expanded);
        toggle.setAttribute('aria-expanded', expanded ? 'true' : 'false');
        panel.hidden = !expanded;
      };

      const syncSectionSummary = function (section) {
        const checkedInput = section.querySelector('input[type="radio"]:checked');
        const summaryLabel = section.querySelector('[data-theme-summary-label]');
        const summaryPreview = section.querySelector('[data-theme-summary-preview]');
        if (!checkedInput || !summaryLabel || !summaryPreview) {
          return;
        }

        const activeOption = checkedInput.closest('.theme-option');
        if (!activeOption) {
          return;
        }

        const nextLabel = activeOption.getAttribute('data-theme-label') || checkedInput.value || '';
        const nextPreview = activeOption.getAttribute('data-theme-preview') || '';

        summaryLabel.textContent = nextLabel;
        summaryPreview.style.cssText = nextPreview;

        section.querySelectorAll('.theme-option').forEach(function (option) {
          option.classList.toggle('active', option === activeOption);
        });
      };

      window.__tetapanSetThemeSectionExpanded = function (section, expanded) {
        if (!section) {
          return;
        }
        setExpanded(section, expanded);
        persistState();
      };

      window.__tetapanToggleThemeSection = function (toggleEl) {
        const button = toggleEl && toggleEl.closest ? toggleEl.closest('[data-theme-toggle]') : null;
        if (!button) {
          return false;
        }
        const section = button.closest('[data-theme-section]');
        if (!section) {
          return false;
        }
        const expanded = button.getAttribute('aria-expanded') === 'true';
        window.__tetapanSetThemeSectionExpanded(section, !expanded);
        return false;
      };

      sections.forEach(function (section) {
        const key = section.getAttribute('data-theme-section') || '';
        const toggle = section.querySelector('[data-theme-toggle]');
        const radios = Array.from(section.querySelectorAll('input[type="radio"]'));
        const shouldExpand = storedState[key] === true;

        setExpanded(section, shouldExpand);
        syncSectionSummary(section);

        radios.forEach(function (radio) {
          radio.addEventListener('change', function () {
            syncSectionSummary(section);
          });
        });
      });

      window.__tetapanSyncThemeSectionUi = function () {
        sections.forEach(syncSectionSummary);
      };

      form.dataset.themeSectionsInitialized = '1';
      persistState();
    }

    function validateField(field) {
      const name = field.name;
      const value = field.value.trim();
      let isValid = true;
      let message = '';

      const existingFeedback = field.parentElement.querySelector('.invalid-feedback');
      if (existingFeedback) {
        existingFeedback.remove();
      }
      field.classList.remove('is-invalid', 'is-valid');

      if (!value) {
        return;
      }

      if (name === 'mail_port') {
        const port = parseInt(value, 10);
        if (isNaN(port) || port < 1 || port > 65535) {
          isValid = false;
          message = __('config_js_valid_port_range');
        }
      }

      if (name === 'mail_host') {
        const domainRegex = /^[a-zA-Z0-9]([a-zA-Z0-9-]{0,61}[a-zA-Z0-9])?(\.[a-zA-Z0-9]([a-zA-Z0-9-]{0,61}[a-zA-Z0-9])?)*$/;
        const ipRegex = /^(\d{1,3}\.){3}\d{1,3}$/;
        if (!domainRegex.test(value) && !ipRegex.test(value)) {
          isValid = false;
          message = __('config_js_valid_host_format');
        }
      }

      if (name === 'mail_username' || name === 'mail_from_address') {
        const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        if (!emailRegex.test(value)) {
          isValid = false;
          message = __('config_js_valid_email_format');
        }
      }

      if (isValid) {
        field.classList.add('is-valid');
      } else {
        field.classList.add('is-invalid');
        const feedback = document.createElement('div');
        feedback.className = 'invalid-feedback';
        feedback.textContent = message;
        field.parentElement.appendChild(feedback);
      }
    }

    function setBadgeState(element, active, activeText, inactiveText, activeClass, inactiveClass) {
      if (!element) {
        return;
      }
      element.className = 'badge bg-' + (active ? activeClass : inactiveClass) + '-subtle text-' + (active ? activeClass : inactiveClass);
      element.textContent = active ? activeText : inactiveText;
    }

    function renderListItems(target, items) {
      if (!target) {
        return;
      }
      target.innerHTML = '';
      (items || []).forEach(function (item) {
        var li = document.createElement('li');
        li.textContent = item;
        target.appendChild(li);
      });
    }

    function initAuthPolicyInteractions() {
      var form = document.getElementById('form-auth-aktif');
      if (!form) {
        return;
      }

      if (form.dataset.authPolicyInitialized === '1' && typeof window.__tetapanRefreshAuthPolicySummary === 'function') {
        window.__tetapanRefreshAuthPolicySummary();
        return;
      }

      var maintenanceInput = document.getElementById('auth_maintenance_mode');
      var staffInput = document.getElementById('auth_login_enable_staf');
      var studentInput = document.getElementById('auth_login_enable_pelajar');
      var publicInput = document.getElementById('auth_login_enable_umum');
      var ssoEnabledInput = document.getElementById('auth_sso_enabled');
      var ssoModeInput = document.getElementById('auth_sso_mode');
      var autoProvisionStaffInput = document.getElementById('auth_auto_provision_staf_sso');
      var autoProvisionStudentInput = document.getElementById('auth_auto_provision_pelajar_sso');
      var defaultGroupStaffInput = document.getElementById('auth_default_group_staff_code');
      var defaultGroupStudentInput = document.getElementById('auth_default_group_student_code');
      var maintenanceBadge = document.getElementById('auth-maintenance-state');
      var staffBadge = document.getElementById('auth-category-state-auth_login_enable_staf');
      var studentBadge = document.getElementById('auth-category-state-auth_login_enable_pelajar');
      var publicBadge = document.getElementById('auth-category-state-auth_login_enable_umum');
      var ssoEnabledBadge = document.getElementById('auth-sso-enabled-state');
      var autoProvisionStaffBadge = document.getElementById('auth-auto-provision-state-staff');
      var autoProvisionStudentBadge = document.getElementById('auth-auto-provision-state-student');
      var ssoSiteIdInput = document.getElementById('auth_sso_site_id');
      var ssoIdpDomainInput = document.getElementById('auth_sso_idp_domain');
      var ssoSiteIdRequiredMark = document.getElementById('auth-sso-site-id-required');
      var ssoIdpDomainRequiredMark = document.getElementById('auth-sso-idp-domain-required');
      var summarySiteId = document.getElementById('auth-summary-site-id');
      var summaryIdpDomain = document.getElementById('auth-summary-idp-domain');
      var modeNote = document.getElementById('auth-sso-mode-note');
      var hybridBlock = document.getElementById('auth-hybrid-block');
      var statusBadge = document.getElementById('auth-summary-status-badge');
      var statusText = document.getElementById('auth-summary-status-text');
      var effectiveList = document.getElementById('auth-summary-effective-list');
      var warningBox = document.getElementById('auth-summary-warning-box');
      var warningList = document.getElementById('auth-summary-warning-list');
      var hasServerError = !!form.querySelector('.auth-summary-box-error');

      function refreshAuthPolicySummary() {
        if (!maintenanceInput || !staffInput || !studentInput || !publicInput || !ssoEnabledInput || !ssoModeInput) {
          return;
        }

        var maintenanceOn = !!maintenanceInput.checked;
        var staffEnabled = !!staffInput.checked;
        var studentEnabled = !!studentInput.checked;
        var publicEnabled = !!publicInput.checked;
        var ssoEnabled = !!ssoEnabledInput.checked;
        var ssoMode = String(ssoModeInput.value || 'MANUAL').toUpperCase();
        var autoProvisionStaff = !!(autoProvisionStaffInput && autoProvisionStaffInput.checked);
        var autoProvisionStudent = !!(autoProvisionStudentInput && autoProvisionStudentInput.checked);
        var defaultStaffGroup = String(defaultGroupStaffInput && defaultGroupStaffInput.value ? defaultGroupStaffInput.value : 'ADM-STAF').trim() || 'ADM-STAF';
        var defaultStudentGroup = String(defaultGroupStudentInput && defaultGroupStudentInput.value ? defaultGroupStudentInput.value : 'ADM-STUDENT').trim() || 'ADM-STUDENT';
        var warnings = [];
        var effectiveSummary = [];
        var serverState = window.__tetapanAuthServerState || null;

        setBadgeState(maintenanceBadge, maintenanceOn, __('config_auth_enabled') || 'Enabled', __('config_auth_disabled') || 'Disabled', 'danger', 'secondary');
        setBadgeState(staffBadge, staffEnabled, __('config_auth_allowed') || 'Allowed', __('config_auth_blocked') || 'Blocked', 'success', 'secondary');
        setBadgeState(studentBadge, studentEnabled, __('config_auth_allowed') || 'Allowed', __('config_auth_blocked') || 'Blocked', 'success', 'secondary');
        setBadgeState(publicBadge, publicEnabled, __('config_auth_allowed') || 'Allowed', __('config_auth_blocked') || 'Blocked', 'success', 'secondary');
        setBadgeState(ssoEnabledBadge, ssoEnabled, __('config_auth_enabled') || 'Enabled', __('config_auth_disabled') || 'Disabled', 'success', 'secondary');
        setBadgeState(autoProvisionStaffBadge, autoProvisionStaff, __('config_auth_enabled') || 'Enabled', __('config_auth_disabled') || 'Disabled', 'success', 'secondary');
        setBadgeState(autoProvisionStudentBadge, autoProvisionStudent, __('config_auth_enabled') || 'Enabled', __('config_auth_disabled') || 'Disabled', 'success', 'secondary');
        if (ssoSiteIdInput) {
          ssoSiteIdInput.required = ssoEnabled;
          ssoSiteIdInput.setAttribute('aria-required', ssoEnabled ? 'true' : 'false');
        }
        if (ssoIdpDomainInput) {
          ssoIdpDomainInput.required = ssoEnabled;
          ssoIdpDomainInput.setAttribute('aria-required', ssoEnabled ? 'true' : 'false');
        }
        if (ssoSiteIdRequiredMark) {
          ssoSiteIdRequiredMark.classList.toggle('d-none', !ssoEnabled);
        }
        if (ssoIdpDomainRequiredMark) {
          ssoIdpDomainRequiredMark.classList.toggle('d-none', !ssoEnabled);
        }
        if (summarySiteId && ssoSiteIdInput) {
          summarySiteId.textContent = String(ssoSiteIdInput.value || '').trim() || (__('config_auth_summary_not_configured') || 'Not configured');
        }
        if (summaryIdpDomain && ssoIdpDomainInput) {
          summaryIdpDomain.textContent = String(ssoIdpDomainInput.value || '').trim() || (__('config_auth_summary_not_configured') || 'Not configured');
        }

        if (modeNote) {
          if (ssoMode === 'ALL') {
            modeNote.innerHTML = '<i class="ri-information-line me-1"></i>' + ((__('config_auth_sso_mode_all_note')) || 'In ALL mode, Staff and Student users must use SSO. Public users may still log in manually.');
          } else if (ssoMode === 'HYBRID') {
            modeNote.innerHTML = '<i class="ri-information-line me-1"></i>' + ((__('config_auth_sso_mode_hybrid_note')) || 'In HYBRID mode, each category follows its own configured login method.');
          } else {
            modeNote.innerHTML = '<i class="ri-information-line me-1"></i>' + ((__('config_auth_sso_mode_manual_note')) || 'In MANUAL mode, all allowed categories use manual login.');
          }
        }

        if (hybridBlock) {
          hybridBlock.classList.toggle('auth-hybrid-block-muted', ssoMode !== 'HYBRID');
        }

        effectiveSummary.push(maintenanceOn
          ? (__('config_auth_summary_maintenance_on') || 'Maintenance mode is enabled. Only Super Admin can log in.')
          : (__('config_auth_summary_maintenance_off') || 'Maintenance mode is disabled. Normal policy evaluation applies.'));
        effectiveSummary.push(staffEnabled
          ? (__('config_auth_summary_staff_enabled') || 'Staff login is enabled.')
          : (__('config_auth_summary_staff_disabled') || 'Staff login is disabled.'));
        effectiveSummary.push(studentEnabled
          ? (__('config_auth_summary_student_enabled') || 'Student login is enabled.')
          : (__('config_auth_summary_student_disabled') || 'Student login is disabled.'));
        effectiveSummary.push(publicEnabled
          ? (__('config_auth_summary_public_enabled') || 'Public login is enabled.')
          : (__('config_auth_summary_public_disabled') || 'Public login is disabled.'));
        effectiveSummary.push(ssoEnabled
          ? ((__('config_auth_summary_sso_enabled') || 'SSO is enabled in %s mode.').replace('%s', ssoMode))
          : (__('config_auth_summary_sso_disabled') || 'SSO is disabled. All allowed categories use manual login.'));
        effectiveSummary.push(autoProvisionStaff
          ? ((__('config_auth_summary_staff_auto_provision_enabled') || 'Staff SSO auto provision is enabled with default group %s.').replace('%s', defaultStaffGroup))
          : (__('config_auth_summary_staff_auto_provision_disabled') || 'Staff SSO auto provision is disabled.'));
        effectiveSummary.push(autoProvisionStudent
          ? ((__('config_auth_summary_student_auto_provision_enabled') || 'Student SSO auto provision is enabled with default group %s.').replace('%s', defaultStudentGroup))
          : (__('config_auth_summary_student_auto_provision_disabled') || 'Student SSO auto provision is disabled.'));

        if (!ssoEnabled && ssoMode !== 'MANUAL') {
          warnings.push((__('config_auth_warning_sso_disabled_mode')) || 'SSO mode is configured but SSO is currently disabled.');
        }
        if (!staffEnabled && !studentEnabled && !publicEnabled) {
          warnings.push((__('config_auth_warning_all_categories_blocked')) || 'All login categories are blocked. Only Super Admin will remain able to log in.');
        }
        if (serverState && Array.isArray(serverState.warnings) && serverState.warnings.length) {
          warnings = serverState.warnings.slice();
        }

        renderListItems(effectiveList, effectiveSummary);
        renderListItems(warningList, warnings);

        if (warningBox) {
          warningBox.classList.toggle('d-none', warnings.length === 0);
        }

        if (!hasServerError) {
          var serverErrors = serverState && Array.isArray(serverState.errors) ? serverState.errors : [];
          var hasWarnings = warnings.length > 0;
          if (statusBadge) {
            var statusClass = serverErrors.length ? 'danger' : (hasWarnings ? 'warning' : 'success');
            statusBadge.className = 'badge bg-' + statusClass + '-subtle text-' + statusClass + ' px-3 py-2';
            statusBadge.textContent = serverErrors.length
              ? (__('config_auth_status_invalid') || 'Invalid')
              : (hasWarnings ? (__('config_auth_status_warning') || 'Valid with Warning') : (__('config_auth_status_valid') || 'Valid'));
          }
          if (statusText) {
            statusText.className = (serverErrors.length ? 'text-danger' : (hasWarnings ? 'text-warning' : 'text-success')) + ' small fw-semibold';
            statusText.textContent = serverErrors.length
              ? serverErrors[0]
              : (hasWarnings
                  ? ((__('config_auth_summary_warnings')) || 'Warnings') + ': ' + warnings[0]
                  : (__('config_auth_summary_status_ok') || 'Policy snapshot is ready for runtime use.'));
          }
        }
      }

      window.__tetapanRefreshAuthPolicySummary = refreshAuthPolicySummary;
      window.__tetapanSyncAuthPolicyUi = refreshAuthPolicySummary;

      function handleAuthPolicyFieldEvent(event) {
        var field = event && event.target ? event.target : null;
        if (!field || !field.name) {
          return;
        }

        if (
          field.name === 'auth_maintenance_mode' ||
          field.name === 'auth_login_enable_staf' ||
          field.name === 'auth_login_enable_pelajar' ||
          field.name === 'auth_login_enable_umum' ||
            field.name === 'auth_sso_enabled' ||
            field.name === 'auth_sso_site_id' ||
            field.name === 'auth_sso_idp_domain' ||
            field.name === 'auth_sso_mode' ||
          field.name === 'auth_sso_hybrid_staf' ||
          field.name === 'auth_sso_hybrid_pelajar' ||
          field.name === 'auth_sso_hybrid_umum' ||
          field.name === 'auth_auto_provision_staf_sso' ||
          field.name === 'auth_auto_provision_pelajar_sso' ||
          field.name === 'auth_default_group_staff_code' ||
          field.name === 'auth_default_group_student_code'
        ) {
          window.__tetapanAuthServerState = null;
          refreshAuthPolicySummary();
        }
      }

      form.addEventListener('change', handleAuthPolicyFieldEvent);
      form.addEventListener('input', handleAuthPolicyFieldEvent);
      form.dataset.authPolicyInitialized = '1';

      refreshAuthPolicySummary();
    }

    document.querySelectorAll('input[name="mail_host"], input[name="mail_port"], input[name="mail_username"], input[name="mail_from_address"]').forEach(function (input) {
      input.addEventListener('blur', function () {
        validateField(this);
      });
      input.addEventListener('input', function () {
        this.classList.remove('is-invalid', 'is-valid');
        clearFieldValidationState(this);
      });
    });

    document.querySelectorAll('#form-general-aktif input, #form-general-aktif textarea, #form-general-aktif select, #form-auth-aktif input, #form-auth-aktif textarea, #form-auth-aktif select, #form-emel-aktif input, #form-emel-aktif textarea, #form-emel-aktif select, #form-db-aktif input, #form-db-aktif textarea, #form-db-aktif select, #form-tema-aktif input, #form-tema-aktif textarea, #form-tema-aktif select, #form-bahasa input, #form-bahasa textarea, #form-bahasa select, #form-ai-chatbot input, #form-ai-chatbot textarea, #form-ai-chatbot select').forEach(function (field) {
      field.addEventListener('input', function () {
        clearFieldValidationState(field);
        const form = field.form;
        if (form) {
          clearSubtabErrorMarkers(form);
        }
      });
      field.addEventListener('change', function () {
        clearFieldValidationState(field);
        const form = field.form;
        if (form) {
          clearSubtabErrorMarkers(form);
        }
      });
    });

    const formGeneral = document.getElementById('form-general-aktif');
    const btnGeneral = document.getElementById('btn-simpan-general');
    if (formGeneral && btnGeneral) {
      captureFormSnapshot(formGeneral);
      refreshDirtyIndicator(formGeneral, btnGeneral);
    }

    const formAuth = document.getElementById('form-auth-aktif');
    const btnAuth = document.getElementById('btn-simpan-auth');
    if (formAuth && btnAuth) {
      captureFormSnapshot(formAuth);
      refreshDirtyIndicator(formAuth, btnAuth);
    }

    initAuthPolicyInteractions();

    const formEmel = document.getElementById('form-emel-aktif');
    const btnEmel = document.getElementById('btn-simpan-emel');
    if (formEmel && btnEmel) {
      captureFormSnapshot(formEmel);
      refreshDirtyIndicator(formEmel, btnEmel);
    }

    window.__tetapanHandleEmailTestImpl = function () {
      const btnUji = document.getElementById('btn-uji-emel');
      if (!btnUji) {
        return;
      }
      const form = document.getElementById('form-emel-aktif');
      const mailFrom = form && form.querySelector('input[name="mail_from_address"]')
        ? form.querySelector('input[name="mail_from_address"]').value
        : '';
      const mailUsername = form && form.querySelector('input[name="mail_username"]')
        ? form.querySelector('input[name="mail_username"]').value
        : '';
      const defaultEmail = mailFrom || mailUsername || '';

      Swal.fire({
        title: __('config_js_input_uji_emel'),
        input: 'email',
        inputLabel: __('config_js_label_uji_emel'),
        inputValue: defaultEmail,
        inputPlaceholder: __('config_js_placeholder_uji_emel'),
        showCancelButton: true,
        confirmButtonText: __('config_js_uji_emel_btn'),
        cancelButtonText: __('config_alert_no'),
        preConfirm: function (email) {
          if (!email) {
            Swal.showValidationMessage(__('config_js_valid_emel_kosong'));
            return false;
          }
          const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
          if (!emailRegex.test(email)) {
            Swal.showValidationMessage(__('config_js_valid_email_full'));
            return false;
          }
          return email;
        }
      }).then(function (result) {
        if (!result.isConfirmed) {
          return;
        }

        const formData = new FormData(form);
        formData.append('uji_email', result.value);
        btnUji.disabled = true;
        btnUji.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> ' + __('config_js_uji_emel_btn_loading');
        showTetapanLoader('emailTest', __('config_js_uji_emel_btn_loading') || 'Testing...');

        const csrfToken = document.querySelector('meta[name="csrf-token"]')
          ? document.querySelector('meta[name="csrf-token"]').getAttribute('content')
          : '';
        formData.append('csrf_token', csrfToken);

        fetch(baseUrl + 'ajax/uji-emel.php', {
          method: 'POST',
          body: formData,
          noLoader: true,
          headers: Object.assign({
            'X-No-Loader': '1'
          }, csrfToken ? { 'X-CSRF-Token': csrfToken } : {})
        })
          .then(function (res) { return res.json(); })
          .then(function (data) {
            if (data.success) {
              const title = __('config_js_berjaya');
              const finalTitle = (title && title !== 'config_js_berjaya') ? title : 'Berjaya';
              Swal.fire({
                icon: 'success',
                title: finalTitle,
                html: data.message || __('config_js_emel_berjaya') || 'Emel berjaya dihantar.'
              });
              return;
            }

            const errorTitle = __('config_js_ralat');
            const finalErrorTitle = (errorTitle && errorTitle !== 'config_js_ralat') ? errorTitle : 'Ralat';
            Swal.fire({
              icon: 'error',
              title: finalErrorTitle,
              text: data.message || __('config_js_emel_gagal') || 'Gagal hantar emel.'
            });
          })
          .catch(function () {
            Swal.fire({ icon: 'error', title: __('config_js_ralat'), text: __('config_js_ralat_sistem') });
          })
          .finally(function () {
            btnUji.disabled = false;
            btnUji.innerHTML = '<i class="ri-mail-send-line me-1"></i> ' + __('config_js_uji_emel_btn_default');
            hideTetapanLoader('emailTest');
          });
      });
    };

    try {
      cleanupOrphanedBackdrops();
      document.addEventListener('hidden.bs.modal', cleanupOrphanedBackdrops);
      document.addEventListener('hidden.bs.offcanvas', cleanupOrphanedBackdrops);
    } catch (error) {
      // optional cleanup should not block page actions
    }

    try {
      bindBootstrapTabs();
    } catch (error) {
      // tab enhancement is optional for save flow
    }

    [
      'form-general-aktif',
      'form-auth-aktif',
      'form-emel-aktif',
      'form-db-aktif',
      'form-tema-aktif',
      'form-bahasa',
      'form-ai-chatbot'
    ].forEach(function (formId) {
      const form = document.getElementById(formId);
      if (form) {
        form.noValidate = true;
      }
    });

    try {
      if (typeof pageUiHelper.persistBootstrapTabs === 'function') {
        pageUiHelper.persistBootstrapTabs({
          storageKey: 'lastActiveTab',
          defaultTab: '#general-tab',
          tabSelector: 'a[data-bs-toggle="tab"]'
        });
      } else if (window.bootstrap && window.bootstrap.Tab) {
        (function () {
          let storedTab = null;
          try {
            storedTab = window.localStorage.getItem('lastActiveTab');
          } catch (storageError) {
            storedTab = null;
          }

          const urlTab = new URLSearchParams(location.search).get('tab');
          const wanted = urlTab
            ? ('#' + urlTab + '-tab')
            : (window.location.hash || storedTab || '#general-tab');
          const el = document.querySelector('a[href="' + wanted + '"]');
          if (el) {
            window.bootstrap.Tab.getOrCreateInstance(el).show();
          }

          document.querySelectorAll('a[data-bs-toggle="tab"]').forEach(function (tab) {
            tab.addEventListener('shown.bs.tab', function (e) {
              try {
                window.localStorage.setItem('lastActiveTab', e.target.getAttribute('href'));
              } catch (storageError) {
                // ignore storage errors
              }
            });
          });
        })();
      }
    } catch (error) {
      // tab persistence is optional for save flow
    }

    const btnUji = document.getElementById('btn-uji-emel');
    if (btnUji) {
      btnUji.addEventListener('click', function (event) {
        event.preventDefault();
        event.stopPropagation();
        window.__tetapanHandleEmailTest();
      });
    }

    const formDB = document.getElementById('form-db-aktif');
    const btnDB = document.getElementById('btn-simpan-db');
    if (formDB && btnDB) {
      const dbOptionRows = Array.from(formDB.querySelectorAll('.db-option-row[data-db-radio]'));

      const syncDbOptionRows = function () {
        syncDbOptionRowSelection(formDB);
      };

      Object.entries(initialDbSelection).forEach(function (entry) {
        const name = entry[0];
        const value = entry[1];
        const radios = Array.from(formDB.querySelectorAll('input[name="' + name + '"]'));
        radios.forEach(function (radio) {
          radio.checked = radio.value === value;
        });
      });

      dbOptionRows.forEach(function (row) {
        row.addEventListener('click', function () {
          const selector = row.getAttribute('data-db-radio');
          const input = selector ? formDB.querySelector(selector) : null;
          if (!input) {
            return;
          }
          if (!input.checked) {
            input.checked = true;
            input.dispatchEvent(new Event('change', { bubbles: true }));
          } else {
            syncDbOptionRows();
          }
        });
      });

      formDB.querySelectorAll('input[name="main_db_environment"], input[name="sybase_environment"], input[name="sybase_operational_mode"]').forEach(function (input) {
        input.addEventListener('change', function () {
          syncDbOptionRows();
          if (typeof window.__tetapanSyncDatabaseRuntimeUi === 'function') {
            window.__tetapanSyncDatabaseRuntimeUi();
          }
          if (typeof window.__tetapanForceRuntimeSummaryFromDbForm === 'function') {
            window.__tetapanForceRuntimeSummaryFromDbForm(formDB);
          }
        });
      });

      syncDbOptionRows();
      if (typeof window.__tetapanSyncDatabaseRuntimeUi === 'function') {
        window.__tetapanSyncDatabaseRuntimeUi();
      }
      captureFormSnapshot(formDB);
      refreshDirtyIndicator(formDB, btnDB);
    }

    if (dbAdditionalEnvAddButton) {
      dbAdditionalEnvAddButton.addEventListener('click', function () {
        appendEnvRow({ f_environment: 'production', f_os_family: 'any', f_driver: 'mysql', f_charset: 'utf8mb4', f_is_active: true });
      });
    }

    if (dbAdditionalEnvRows) {
      dbAdditionalEnvRows.addEventListener('click', function (event) {
        var removeButton = event.target.closest('[data-env-row-remove]');
        if (!removeButton) {
          return;
        }
        event.preventDefault();
        var row = removeButton.closest('[data-env-row]');
        if (!row) {
          return;
        }
        row.remove();
        if (!dbAdditionalEnvRows.querySelector('[data-env-row]')) {
          appendEnvRow({ f_environment: 'production', f_os_family: 'any', f_driver: 'mysql', f_charset: 'utf8mb4', f_is_active: true });
        } else {
          reindexEnvRows();
        }
      });
    }

    var windowsOdbcPresetButton = document.getElementById('btn-db-additional-preset-windows-odbc');
    if (windowsOdbcPresetButton) {
      windowsOdbcPresetButton.addEventListener('click', function () {
        appendPlatformPreset('windows', 'odbc');
      });
    }
    var linuxDblibPresetButton = document.getElementById('btn-db-additional-preset-linux-dblib');
    if (linuxDblibPresetButton) {
      linuxDblibPresetButton.addEventListener('click', function () {
        appendPlatformPreset('linux', 'dblib');
      });
    }

    if (dbAdditionalSearch) {
      dbAdditionalSearch.addEventListener('input', renderAdditionalConnectionsTable);
    }
    if (dbAdditionalFamilyFilter) {
      dbAdditionalFamilyFilter.addEventListener('change', renderAdditionalConnectionsTable);
    }
    if (dbAdditionalStatusFilter) {
      dbAdditionalStatusFilter.addEventListener('change', renderAdditionalConnectionsTable);
    }

    if (dbAdditionalTableBody) {
      dbAdditionalTableBody.addEventListener('click', function (event) {
        var actionButton = event.target.closest('[data-db-additional-action]');
        if (!actionButton) {
          return;
        }

        var action = actionButton.getAttribute('data-db-additional-action') || '';
        var code = actionButton.getAttribute('data-code') || '';
        var connection = additionalConnections.find(function (item) {
          return String(item.f_code || '') === code;
        }) || null;

        if (action === 'edit') {
          openAdditionalConnectionModal(connection);
          return;
        }

        if (action === 'sample-code') {
          showAdditionalConnectionSampleCode(connection);
          return;
        }

        if (action === 'inspect') {
          var inspectEnv = getPreferredAdditionalEnvRow(connection);
          postAdditionalConnectionAction('db_additional_inspect', {
            connection_code: code,
            environment: inspectEnv ? String(inspectEnv.f_environment || 'production') : 'production',
            os_family: inspectEnv ? String(inspectEnv.f_os_family || 'any') : 'any',
            driver: inspectEnv ? String(inspectEnv.f_driver || '') : ''
          }, actionButton)
            .then(function (payload) {
              if (!payload || payload.success !== true || !payload.data || !payload.data.probe) {
                throw new Error((payload && payload.message) || additionalLabel('config_tab_db_additional_inspect_failed', 'Failed to load additional connection details.'));
              }
              showAdditionalConnectionProbe(payload.data.probe);
            })
            .catch(function (error) {
              showTetapanSystemError(error && error.message ? error.message : additionalLabel('config_tab_db_additional_inspect_failed', 'Failed to load additional connection details.'));
            });
          return;
        }

        if (action === 'schema') {
          var schemaEnv = getPreferredAdditionalEnvRow(connection);
          postAdditionalConnectionAction('db_additional_schema_preview', {
            connection_code: code,
            environment: schemaEnv ? String(schemaEnv.f_environment || 'production') : 'production',
            os_family: schemaEnv ? String(schemaEnv.f_os_family || 'any') : 'any',
            driver: schemaEnv ? String(schemaEnv.f_driver || '') : ''
          }, actionButton)
            .then(function (payload) {
              if (!payload || payload.success !== true || !payload.data || !payload.data.schemaPreview) {
                throw new Error((payload && payload.message) || additionalLabel('config_tab_db_additional_schema_failed', 'Failed to load additional connection schema preview.'));
              }
              showAdditionalConnectionSchemaPreview(payload.data.schemaPreview);
            })
            .catch(function (error) {
              showTetapanSystemError(error && error.message ? error.message : additionalLabel('config_tab_db_additional_schema_failed', 'Failed to load additional connection schema preview.'));
            });
          return;
        }

        if (action === 'toggle') {
          var nextEnabled = actionButton.getAttribute('data-enabled') !== '1';
          postAdditionalConnectionAction('db_additional_toggle', {
            connection_code: code,
            enabled: nextEnabled ? '1' : '0'
          }, actionButton)
            .then(function (payload) {
              if (!payload || payload.success !== true) {
                throw new Error((payload && payload.message) || 'Gagal mengemas kini status sambungan tambahan.');
              }
              applyPayloadUiSync(payload, formDB);
            })
            .catch(function (error) {
              showTetapanSystemError(error && error.message ? error.message : 'Gagal mengemas kini status sambungan tambahan.');
            });
          return;
        }

        if (action === 'test') {
          var firstEnv = getPreferredAdditionalEnvRow(connection);
          postAdditionalConnectionAction('db_additional_test', {
            connection_code: code,
            environment: firstEnv ? String(firstEnv.f_environment || 'production') : 'production',
            os_family: firstEnv ? String(firstEnv.f_os_family || 'any') : 'any',
            driver: firstEnv ? String(firstEnv.f_driver || '') : ''
          }, actionButton)
            .then(function (payload) {
              if (!payload || payload.success !== true) {
                throw new Error((payload && payload.message) || additionalLabel('config_tab_db_additional_test_failed', 'Additional connection test failed.'));
              }
              if (window.Swal && typeof window.Swal.fire === 'function') {
                window.Swal.fire({
                  icon: 'success',
                  title: payload.title || __('config_js_berjaya') || 'Success',
                  text: payload.message || additionalLabel('config_tab_db_additional_test_success', 'Additional connection test passed.'),
                  confirmButtonText: __('config_js_btn_ok') || 'OK'
                });
              }
              return postAdditionalConnectionAction('db_additional_list', {}, dbAdditionalRefreshButton || actionButton);
            })
            .then(function (listPayload) {
              if (listPayload && listPayload.success && listPayload.data && Array.isArray(listPayload.data.additionalConnections)) {
                additionalConnections = listPayload.data.additionalConnections.slice();
                renderAdditionalConnectionsTable();
                if (listPayload.data.additionalDiagnostics) {
                  renderAdditionalDiagnostics(listPayload.data.additionalDiagnostics);
                }
              }
            })
            .catch(function (error) {
              window.__tetapanRefreshAdditionalConnections(dbAdditionalRefreshButton || actionButton);
              showTetapanSystemError(error && error.message ? error.message : additionalLabel('config_tab_db_additional_test_failed', 'Additional connection test failed.'));
            });
        }
      });
    }

    renderAdditionalConnectionsTable();
    renderAdditionalDiagnostics(additionalDiagnostics);

    const formBahasa = document.getElementById('form-bahasa');
    const btnBahasa = document.getElementById('btn-simpan-bahasa');
    if (formBahasa && btnBahasa) {
      syncLanguageSelectionUi(formBahasa);
      captureFormSnapshot(formBahasa);
      refreshDirtyIndicator(formBahasa, btnBahasa);
    }

    const formTema = document.getElementById('form-tema-aktif');
    const btnTema = document.getElementById('btn-simpan-tema');
    if (formTema && btnTema) {
      initThemeSectionInteractions(formTema);
      captureFormSnapshot(formTema);
      refreshDirtyIndicator(formTema, btnTema);
    }

    const formAiChatbot = document.getElementById('form-ai-chatbot');
    const btnAiChatbot = document.getElementById('btn-simpan-ai-chatbot');
    if (formAiChatbot && btnAiChatbot) {
      captureFormSnapshot(formAiChatbot);
      refreshDirtyIndicator(formAiChatbot, btnAiChatbot);
    }

    [
      [formGeneral, btnGeneral],
      [formAuth, btnAuth],
      [formEmel, btnEmel],
      [formDB, btnDB],
      [formBahasa, btnBahasa],
      [formTema, btnTema],
      [formAiChatbot, btnAiChatbot]
    ].forEach(function (entry) {
      var form = entry[0];
      var button = entry[1];
      if (!form || !button) {
        return;
      }

      form.addEventListener('input', function () {
        refreshDirtyIndicator(form, button);
      });
      form.addEventListener('change', function () {
        if (form === formBahasa) {
          syncLanguageSelectionUi(formBahasa);
        }
        refreshDirtyIndicator(form, button);
      });
    });
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initTetapanSistemPage);
  } else {
    initTetapanSistemPage();
  }
})();
