<?php
/**
 * IQS FRAMEWORK CORE FILE
 *
 * READ ONLY for downstream project programmers.
 * Do not modify this file directly in template or cloned projects.
 * Custom changes must be implemented in project-specific files
 * or approved extension points.
 */declare(strict_types=1);

/**
 * =====================================================
 * URL Helper (robust + proxy-aware)
 * -----------------------------------------------------
 * - Stabil pada root / subfolder (cth: /staf/e-prestasi)
 * - Auto kesan /pages & /ajax (entry points)
 * - Support HTTPS via reverse proxy (X-Forwarded-Proto/Host)
 * - Disemak untuk elak kehilangan '/' selepas host:port
 * - API:
 *     url_join(), is_absolute_url()
 *     detect_scheme(), detect_host(), detect_base_path()
 *     base_url(), base_path(), current_url(), current_path()
 *     redirect(), asset_url(), with_query()
 *     ajax_path(), ajax_url(), inject_base_meta()
 * =====================================================
 */

/* ---------- Util: Gabung URL/Path tanpa double-slash ---------- */
if (!function_exists('url_join')) {
    /**
     * Join $base dan $path dengan tepat SATU '/' di tengah.
     * Tidak cuba normalisasi query/fragment — sesuai untuk path biasa.
     */
    function url_join(string $base, string $path): string {
        if ($path === '') {
            // Jika path kosong, pulangkan base tanpa trailing '/', kecuali base ialah root '/'
            $trimmed = rtrim($base, '/');
            return ($trimmed === '') ? '/' : $trimmed;
        }
        $base = rtrim($base, '/');
        $path = ltrim($path, '/');
        return $base . '/' . $path;
    }
}

/* ---------- Util: Periksa absolute URL ---------- */
if (!function_exists('is_absolute_url')) {
    function is_absolute_url(string $url): bool {
        return (bool)preg_match('#^https?://#i', $url);
    }
}

/* ---------- Kesan scheme (proxy-aware) ---------- */
if (!function_exists('detect_scheme')) {
    function detect_scheme(): string {
        if (!empty($_SERVER['HTTP_X_FORWARDED_PROTO'])) {
            $forwarded = explode(',', strtolower((string)$_SERVER['HTTP_X_FORWARDED_PROTO']));
            $candidate = trim($forwarded[0] ?? '');
            if ($candidate === 'https' || $candidate === 'http') return $candidate;
        }
        if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') return 'https';
        if (!empty($_SERVER['REQUEST_SCHEME'])) {
            $s = strtolower((string)$_SERVER['REQUEST_SCHEME']);
            return $s === 'https' ? 'https' : 'http';
        }
        return 'http';
    }
}

/* ---------- Kesan host (dengan optional port), proxy-aware ---------- */
if (!function_exists('detect_host')) {
    function detect_host(): string {
        if (!empty($_SERVER['HTTP_X_FORWARDED_HOST'])) {
            // Ambil host pertama jika multiple
            $h  = explode(',', (string)$_SERVER['HTTP_X_FORWARDED_HOST']);
            $hx = trim($h[0] ?? '');
            if ($hx !== '') return $hx;
        }
        if (!empty($_SERVER['HTTP_HOST'])) {
            return (string)$_SERVER['HTTP_HOST'];
        }
        $serverName = $_SERVER['SERVER_NAME'] ?? 'localhost';
        $port       = (int)($_SERVER['SERVER_PORT'] ?? 80);
        $scheme     = detect_scheme();
        $needPort   = ($scheme === 'http' && $port !== 80) || ($scheme === 'https' && $port !== 443);
        return $serverName . ($needPort ? ':' . $port : '');
    }
}

/**
 * ---------- Kesan base path projek ----------
 * Contoh:
 *  - /staf/e-prestasi/pages/tetapan-sistem.php → /staf/e-prestasi
 *  - /pages/tetapan-sistem.php                  → "" (root)
 *  - /ajax/whatever.php                         → "" atau "/staf/e-prestasi" (ikut folder)
 */
if (!function_exists('detect_base_path')) {
    function detect_base_path(): string {
        $scriptName = $_SERVER['SCRIPT_NAME'] ?? '/';
        $scriptName = str_replace('\\', '/', $scriptName);
        $dir        = str_replace('\\', '/', dirname($scriptName));
        $dir        = rtrim($dir, '/');
        // Buang segmen /pages* atau /ajax* di hujung directory
        $basePath = preg_replace('#/(pages|ajax)(/.*)?$#', '', $dir);
        // Normalisasi: '/' → '' (empty = root)
        return ($basePath === '/' ? '' : (string)$basePath);
    }
}

/* ---------- Absolute Base URL (domain + optional subfolder) ---------- */
if (!function_exists('base_url')) {
    /**
     * Beri absolute URL. Pastikan selalu ada '/' selepas host[:port].
     * - Jika $path kosong → pulangkan "scheme://host[/subfolder]" (tanpa slash di hujung)
     * - Jika $path ada → "scheme://host[/subfolder]/$path"
     */
    function base_url(string $path = ''): string {
        $scheme   = detect_scheme();              // http | https
        $host     = detect_host();                // e.g. localhost:8000
        $basePath = detect_base_path();           // '' atau '/subfolder'
        $origin   = $scheme . '://' . $host;      // e.g. http://localhost:8000

        $rootPath = $basePath === '' ? '' : rtrim($basePath, '/');

        if ($path === '') {
            // Pulangkan tanpa trailing slash
            return $origin . $rootPath;
        }

        // Pastikan gabung dengan tepat satu '/'
        $rel = ltrim($path, '/');
        return $origin . $rootPath . '/' . $rel;
    }
}

/* ---------- Relatif dari akar projek (tanpa domain) ---------- */
if (!function_exists('base_path')) {
    /**
     * Pulangkan path relatif projek, bermula dari root ('/').
     * - Jika projek pada subfolder, path akan prefiks dengan subfolder itu.
     * - Menjamin satu '/' antara basePath ↔ $path.
     */
    function base_path(string $path = ''): string {
        $basePath = detect_base_path();                   // '' atau '/subfolder'
        $rootPath = ($basePath === '' ? '' : rtrim($basePath, '/'));
        if ($path === '') return $rootPath === '' ? '/' : $rootPath;  // root atau subfolder tanpa trailing slash
        return $rootPath . '/' . ltrim($path, '/');
    }
}

/* ---------- URL penuh halaman semasa ---------- */
if (!function_exists('current_url')) {
    function current_url(): string {
        $scheme = detect_scheme();
        $host   = detect_host();
        $uri    = $_SERVER['REQUEST_URI'] ?? '/';
        return $scheme . '://' . $host . $uri;
    }
}

/* ---------- Path halaman semasa ---------- */
if (!function_exists('current_path')) {
    function current_path(): string {
        return (string)($_SERVER['REQUEST_URI'] ?? '/');
    }
}

/* ---------- Redirect ---------- */
if (!function_exists('redirect')) {
    function redirect(string $to, int $code = 302): never {
        header('Cache-Control: no-store, no-cache, must-revalidate');
        header('Pragma: no-cache');
        if (is_absolute_url($to)) {
            header('Location: ' . $to, true, $code);
            exit;
        }
        header('Location: ' . base_url($to), true, $code);
        exit;
    }
}

/* ---------- URL asset absolute (relatif kepada /assets) ---------- */
if (!function_exists('asset_url')) {
    function asset_url(string $path): string {
        // Guna path RELATIF (tanpa leading slash) supaya base_url tambah '/' dengan konsisten
        return base_url('assets/' . ltrim($path, '/'));
    }
}

/* ---------- Merge Query String ---------- */
if (!function_exists('with_query')) {
    function with_query(string $url, array $params): string {
        $parts = parse_url($url);
        $query = [];
        if (!empty($parts['query'])) parse_str((string)$parts['query'], $query);
        $query = array_filter(array_merge($query, $params), static fn($v) => $v !== null);
        $qs    = http_build_query($query);

        $scheme = $parts['scheme'] ?? null;
        $host   = $parts['host']   ?? null;
        $port   = isset($parts['port']) ? ':' . $parts['port'] : '';
        $user   = $parts['user']   ?? null;
        $pass   = $parts['pass']   ?? null;
        $auth   = $user ? $user . ($pass ? ':' . $pass : '') . '@' : '';
        $path   = $parts['path']   ?? '';
        $frag   = isset($parts['fragment']) ? '#' . $parts['fragment'] : '';

        if ($scheme && $host) {
            $origin = $scheme . '://' . $auth . $host . $port;
            return $origin . $path . ($qs ? '?' . $qs : '') . $frag;
        }
        // Relatif
        return $path . ($qs ? '?' . $qs : '') . $frag;
    }
}

/* ---------- Convenience: /ajax path & URL ---------- */
if (!function_exists('ajax_path')) {
    function ajax_path(string $file = ''): string {
        return base_path('ajax/' . ltrim($file, '/'));
    }
}
if (!function_exists('ajax_url')) {
    function ajax_url(string $file = ''): string {
        return base_url('ajax/' . ltrim($file, '/'));
    }
}

/* ---------- Inject meta (untuk JS) ---------- */
if (!function_exists('inject_base_meta')) {
    function inject_base_meta(): void {
        $bp = htmlspecialchars(base_path(), ENT_QUOTES, 'UTF-8');
        $bu = htmlspecialchars(base_url(),  ENT_QUOTES, 'UTF-8');
        echo '<meta name="base-path" content="' . $bp . '">' . PHP_EOL;
        echo '<meta name="base-url"  content="' . $bu . '">' . PHP_EOL;
    }
}

/* =====================================================
 * Contoh jangkaan (rujukan ringkas)
 * -----------------------------------------------------
 * Local (root):
 *  - base_url()                      → http://localhost:8000
 *  - asset_url('images/x.png')       → http://localhost:8000/assets/images/x.png
 *
 * Dev (subfolder /staf/e-prestasi):
 *  - base_url()                      → http://localhost:8000/staf/e-prestasi
 *  - asset_url('images/x.png')       → http://localhost:8000/staf/e-prestasi/assets/images/x.png
 *
 * Production (root):
 *  - base_url()                      → https://eprestasi.upnm.edu.my
 *  - asset_url('images/x.png')       → https://eprestasi.upnm.edu.my/assets/images/x.png
 * ===================================================== */
