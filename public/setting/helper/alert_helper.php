<?php
/**
 * IQS FRAMEWORK CORE FILE
 *
 * READ ONLY for downstream project programmers.
 * Do not modify this file directly in template or cloned projects.
 * Custom changes must be implemented in project-specific files
 * or approved extension points.
 */if (defined('ALERT_HELPER_INCLUDED')) return;
define('ALERT_HELPER_INCLUDED', true);

if (session_status() === PHP_SESSION_NONE) session_start();

/**
 * ✅ HTML Alert Biasa (Bootstrap)
 */
function show_alert(string $message, string $type = 'info'): string {
    return '
        <div class="alert alert-' . htmlspecialchars($type) . ' alert-dismissible fade show" role="alert">
            ' . htmlspecialchars($message) . '
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>';
}

/**
 * ✅ Toast (popup kecil).
 * ❗ Client-side akan handle terjemahan melalui window.__()
 */
function set_toast(string $message, string $type = 'success'): void {
    $_SESSION['alert'] = [
        'type'     => 'toast',
        'title'    => $message,
        'text'     => '',
        'icon'     => $type,
        'timer'    => 3000,
        'position' => 'top-end',
        'confirm'  => false
    ];
}

/**
 * ✅ SweetAlert Biasa (Makluman tanpa pengesahan)
 *   - Kekal serasi: boleh pass array seperti sebelum ini
 *   - Tambah sokongan flag: is_key / title_is_key / text_is_key
 *     (default: true = anggap KEY)
 */
function set_alert(array $options = []): void {
    $_SESSION['alert'] = array_merge([
        'type'                => 'sweet',
        'title'               => $options['title'] ?? 'config_js_berjaya',
        'text'                => $options['text']  ?? '',
        'icon'                => $options['icon']  ?? 'info',
        'timer'               => $options['timer'] ?? 0,
        'position'            => $options['position'] ?? 'center',
        'confirm'             => $options['confirm'] ?? true,
        'close_on_confirm'    => $options['close_on_confirm'] ?? false,
        'close_fallback_url'  => $options['close_fallback_url'] ?? '',
        // flags (default anggap key supaya tak ulang isu sebelum ni)
        'is_key'              => $options['is_key']              ?? true,
        'title_is_key'        => $options['title_is_key']        ?? ($options['is_key'] ?? true),
        'text_is_key'         => $options['text_is_key']         ?? ($options['is_key'] ?? true),
        'confirmText_is_key'  => $options['confirmText_is_key']  ?? ($options['is_key'] ?? true),
        'cancelText_is_key'   => $options['cancelText_is_key']   ?? ($options['is_key'] ?? true),
    ], $options);
}

/**
 * ✅ SweetAlert Pengesahan (dengan Ya/Batal + redirect)
 *   - Sokong translation key flags seperti di atas
 */
function set_confirm(string $title, string $text, string $redirect, array $options = []): void {
    $_SESSION['alert'] = array_merge([
        'type'               => 'sweet',
        'title'              => $title,
        'text'               => $text,
        'icon'               => 'warning',
        'confirm'            => true,
        'redirect'           => $redirect,
        'confirmText'        => 'config_js_btn_ya_simpan',
        'cancelText'         => 'config_js_btn_cancel',
        'is_key'             => true,
        'title_is_key'       => $options['title_is_key']       ?? true,
        'text_is_key'        => $options['text_is_key']        ?? true,
        'confirmText_is_key' => $options['confirmText_is_key'] ?? true,
        'cancelText_is_key'  => $options['cancelText_is_key']  ?? true,
    ], $options);
}

/**
 * 🧠 Helper: detect “Missing key:” pattern
 */
function _is_missing_key(?string $s): bool {
    if ($s === null) return true;
    return (bool)preg_match('/^\s*Missing\s+key\s*:/i', $s);
}

/**
 * 🧠 Helper: server-side translate if key
 *   Return [string $out, bool $translated] — translated=true jika berjaya translate di server
 */
function _tr(?string $val, bool $is_key): array {
    if (!$is_key || !is_string($val) || $val === '') return [$val ?? '', false];
    if (!function_exists('__')) return [$val, false];
    $out = __($val);
    if ($out === null || $out === '' || $out === $val || _is_missing_key($out)) {
        return [$val, false]; // fallback: biar client translate
    }
    return [$out, true];
}

/**
 * ✅ Papar Skrip SweetAlert (gabungan server translate + client fallback)
 *   – Versi ini buang concat pelik dalam JS, semua logic buat dalam JS sendiri.
 */
function render_alert(): void {
    if (empty($_SESSION['alert'])) return;

    $a = $_SESSION['alert'];
    unset($_SESSION['alert']);

    $type        = $a['type']        ?? 'sweet';
    $titleRaw    = (string)($a['title'] ?? 'config_js_berjaya');
    $textRaw     = (string)($a['text']  ?? '');
    $icon        = (string)($a['icon']  ?? 'info');
    $position    = (string)($a['position'] ?? ($type === 'toast' ? 'top-end' : 'center'));
    $confirm     = array_key_exists('confirm', $a) ? (bool)$a['confirm'] : true;
    $timer       = (int)($a['timer'] ?? 0);
    $isToast     = ($type === 'toast');

    $redirect       = (string)($a['redirect']    ?? '');
    $closeOnConfirm = (bool)($a['close_on_confirm'] ?? false);
    $closeFallbackUrl = (string)($a['close_fallback_url'] ?? '');
    $defaultConfirmTextKey = $redirect !== '' ? 'config_js_btn_ya_simpan' : 'config_js_btn_ok';
    $confirmTextRaw = (string)($a['confirmText'] ?? $defaultConfirmTextKey);
    $cancelTextRaw  = (string)($a['cancelText']  ?? 'config_js_btn_cancel');

    // flags
    $title_is_key       = (bool)($a['title_is_key']       ?? ($a['is_key'] ?? true));
    $text_is_key        = (bool)($a['text_is_key']        ?? ($a['is_key'] ?? true));
    $confirmText_is_key = (bool)($a['confirmText_is_key'] ?? ($a['is_key'] ?? true));
    $cancelText_is_key  = (bool)($a['cancelText_is_key']  ?? ($a['is_key'] ?? true));

    // Server-side translate attempt (use list() for broader PHP compatibility)
    list($titleServer,  $titleTranslated)   = _tr($titleRaw,       $title_is_key);
    list($textServer,   $textTranslated)    = _tr($textRaw,        $text_is_key);
    list($confirmServer,$confirmTranslated) = _tr($confirmTextRaw, $confirmText_is_key);
    list($cancelServer, $cancelTranslated)  = _tr($cancelTextRaw,  $cancelText_is_key);

    // Siapkan payload untuk JS
    $js = [
        'isToast'            => $isToast,
        'icon'               => $icon,
        'position'           => $position,
        'showConfirmButton'  => $confirm,
        'showCancelButton'   => $redirect !== '',
        'timer'              => $timer,
        'redirect'           => $redirect,
        'closeOnConfirm'     => $closeOnConfirm,
        'closeFallbackUrl'   => $closeFallbackUrl,
        // nilai raw (key atau teks asal)
        'titleRaw'           => $titleRaw,
        'textRaw'            => $textRaw,
        'confirmTextRaw'     => $confirmTextRaw,
        'cancelTextRaw'      => $cancelTextRaw,
        // nilai hasil server translate (kalau berjaya)
        'titleServer'        => $titleServer,
        'textServer'         => $textServer,
        'confirmTextServer'  => $confirmServer,
        'cancelTextServer'   => $cancelServer,
        // penanda sama ada sudah diterjemah di server
        'titleTranslated'    => $titleTranslated,
        'textTranslated'     => $textTranslated,
        'confirmTranslated'  => $confirmTranslated,
        'cancelTranslated'   => $cancelTranslated,
    ];

    $cfgJson = json_encode($js, JSON_UNESCAPED_UNICODE);
    echo <<<HTML
<script>
document.addEventListener("DOMContentLoaded", function(){
    if (!window.Swal) { return; }

    const cfg = $cfgJson;

    const __ = (window.__ || function(k){ return k; });
    function isMissing(v){ return /^\s*Missing key\s*:/i.test(String(v||"")); }
    function tClient(v){
        try{
            const out = __(v);
            if (out === v || isMissing(out)) return v;
            return out;
        } catch(e){ return v; }
    }
    function pick(serverVal, translated, raw){
        if (translated) return serverVal;
        return tClient(raw);
    }

    const swalOpts = {
        toast: cfg.isToast,
        icon: cfg.icon,
        title: pick(cfg.titleServer,  cfg.titleTranslated,  cfg.titleRaw),
        text:  pick(cfg.textServer,   cfg.textTranslated,   cfg.textRaw),
        position: cfg.position,
        showConfirmButton: cfg.showConfirmButton,
        showCancelButton:  cfg.showCancelButton,
        confirmButtonText: pick(cfg.confirmTextServer, cfg.confirmTranslated, cfg.confirmTextRaw),
        cancelButtonText:  pick(cfg.cancelTextServer,  cfg.cancelTranslated,  cfg.cancelTextRaw),
    };

    if (cfg.isToast && cfg.timer > 0) {
        swalOpts.timer = cfg.timer;
        swalOpts.timerProgressBar = true;
    }

    // SweetAlert biasa tidak auto-close: pastikan ada tindakan klik untuk tutup.
    if (!swalOpts.showConfirmButton && !swalOpts.showCancelButton) {
        swalOpts.showConfirmButton = true;
    }

    Swal.fire(swalOpts).then((result) => {
        if (result.isConfirmed && cfg.closeOnConfirm) {
            window.open('', '_self');
            window.close();
            setTimeout(function(){
                if (!document.hidden) {
                    window.location.href = cfg.closeFallbackUrl || 'index.php';
                }
            }, 350);
            return;
        }
        if (result.isConfirmed && cfg.redirect) {
            window.location.href = cfg.redirect;
        }
    });
});
</script>
HTML;
}
