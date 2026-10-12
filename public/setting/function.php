<?php
/**
 * IQS FRAMEWORK CORE FILE
 *
 * READ ONLY for downstream project programmers.
 * Do not modify this file directly in template or cloned projects.
 * Custom changes must be implemented in project-specific files
 * or approved extension points.
 */// ==================================================
// ✅ Fungsi & Helper Global untuk e-Prestasi
// ==================================================

// Mula sesi jika belum
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!function_exists('request_is_ajax_like_legacy')) {
    function request_is_ajax_like_legacy(): bool {
        $requestedWith = strtolower(trim((string)($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '')));
        if ($requestedWith === 'xmlhttprequest') {
            return true;
        }

        $accept = strtolower(trim((string)($_SERVER['HTTP_ACCEPT'] ?? '')));
        return $accept !== '' && (str_contains($accept, 'application/json') || str_contains($accept, 'text/json'));
    }
}

// Sekat akses jika tiada sesi login (melainkan CLI atau AJAX)
$current = basename($_SERVER['SCRIPT_NAME']);
// Jika page mahu benarkan anonymous AJAX (contoh: `ALLOW_ANON_AJAX` defined), skip redirect
if (!defined('ALLOW_ANON_AJAX') || !ALLOW_ANON_AJAX) {
    if (!in_array($current, ['index.php', 'login.php', 'logout.php'])) {
        // Public accounts authenticate with a login ID and may have no staff ID.
        if (empty($_SESSION['f_loginID']) && empty($_SESSION['f_stafID'])) {
            if (request_is_ajax_like_legacy()) {
                // Untuk AJAX, biar bootstrap/controller tentukan 401 JSON yang lebih tepat.
                return;
            }
            header("Location: ../index.php");
            exit;
        }
    }
}
