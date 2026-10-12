// ========================================
// ✅ JS Alert Helper - Versi e-Prestasi
// ========================================

const __ = window.__ || function (key) { return key; };

/**
 * 🔔 Papar SweetAlert biasa atau toast
 * @param {Object} options - Konfigurasi alert
 */
  function set_alert(options = {}) {
    // Check if Swal is available
    if (typeof Swal === 'undefined') {
      console.warn('[swalert_helper] SweetAlert2 not loaded yet');
      // Try to wait for Swal to load (with retry limit to prevent infinite loop)
      let retryCount = 0;
      const maxRetries = 50; // 5 seconds max (50 * 100ms)
      const checkSwal = setInterval(() => {
        retryCount++;
        if (typeof Swal !== 'undefined') {
          clearInterval(checkSwal);
          // Use setTimeout to avoid recursion stack issues
          setTimeout(() => {
            try {
              set_alert(options);
            } catch (err) {
              console.error('[swalert_helper] Error in retry:', err);
            }
          }, 0);
        } else if (retryCount >= maxRetries) {
          clearInterval(checkSwal);
          console.error('[swalert_helper] Swal not loaded after 5 seconds, giving up');
        }
      }, 100);
      return;
    }

    const {
      type = 'sweet',
      title = 'config_alert_title',
      text = '',
      html = '',
      icon = 'info',
      timer = 3000,
      position = (type === 'toast') ? 'top-end' : 'center',
      confirm = false,
      redirect = '',
      confirmText = 'config_js_btn_ya_simpan',
      cancelText = 'config_js_btn_cancel',
      replace = {},
      customClass = {}
    } = options;

    let titleTranslated = __(title);
    let textTranslated = text ? __(text) : '';
    let htmlTranslated = html ? __(html) : '';

    if (replace && typeof replace === 'object') {
      for (const key in replace) {
        titleTranslated = titleTranslated.replaceAll(key, replace[key]);
        textTranslated  = textTranslated.replaceAll(key, replace[key]);
        htmlTranslated  = htmlTranslated.replaceAll(key, replace[key]);
      }
    }

    const swalOptions = {
      toast: type === 'toast',
      icon,
      title: titleTranslated,
      position,
      showConfirmButton: confirm,
      showCancelButton: redirect !== '',
      confirmButtonText: __(confirmText),
      cancelButtonText: __(cancelText),
      timer: redirect === '' ? timer : undefined,
      timerProgressBar: true
    };

    // Use html if provided, otherwise use text
    if (htmlTranslated) {
      swalOptions.html = htmlTranslated;
    } else if (textTranslated) {
      swalOptions.text = textTranslated;
    }

    // Add custom classes if provided
    if (customClass && typeof customClass === 'object' && Object.keys(customClass).length > 0) {
      swalOptions.customClass = customClass;
    }

    // Add confirmButtonColor if provided
    if (options.confirmButtonColor) {
      swalOptions.confirmButtonColor = options.confirmButtonColor;
    }

    try {
      Swal.fire(swalOptions).then((result) => {
        if (result.isConfirmed && redirect !== '') {
          window.location.href = redirect;
        }
      });
    } catch (err) {
      console.error('[swalert_helper] Error showing alert:', err);
    }
  }


/**
 * ✅ Shortcut: set_toast(message_key, type)
 * Default type: 'success'
 */
function set_toast(message = 'config_js_berjaya', type = 'success') {
  set_alert({
    type: 'toast',
    title: message,
    icon: type,
    timer: 3000,
    position: 'top-end',
    confirm: false
  });
}

/**
 * ✅ Shortcut: set_confirm(title_key, text_key, onConfirm, onCancel)
 */
function set_confirm(title, text, onConfirm = () => {}, onCancel = null) {
  if (typeof Swal === 'undefined') {
    console.warn('[swalert_helper] SweetAlert2 not loaded for set_confirm');
    if (typeof onCancel === 'function') onCancel();
    return;
  }
  try {
    Swal.fire({
      icon: 'warning',
      title: __(title),
      html: __(text),
      position: 'center',
      showCancelButton: true,
      confirmButtonText: __('config_js_btn_ya_simpan'),
      cancelButtonText: __('config_js_btn_cancel'),
    }).then((result) => {
      if (result.isConfirmed) {
        if (typeof onConfirm === 'function') onConfirm();
      } else {
        if (typeof onCancel === 'function') onCancel();
      }
    });
  } catch (err) {
    console.error('[swalert_helper] Error in set_confirm:', err);
    if (typeof onCancel === 'function') onCancel();
  }
}
