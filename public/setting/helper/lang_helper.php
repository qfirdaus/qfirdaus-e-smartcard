<?php
/**
 * IQS FRAMEWORK CORE FILE
 *
 * READ ONLY for downstream project programmers.
 * Do not modify this file directly in template or cloned projects.
 * Custom changes must be implemented in project-specific files
 * or approved extension points.
 */
/**
 * Load translations for a language.
 *
 * Load order:
 * 1. public/lang/core/{lang}.php
 * 2. public/lang/custom/{lang}.php
 *
 * Custom keys override core keys. The legacy public/lang/{lang}.php wrapper is
 * kept for direct includes elsewhere until every caller uses this helper.
 */
function lang_lines(string $lang): array {
    static $cache = [];

    $lang = trim($lang) !== '' ? trim($lang) : 'ms';
    if (isset($cache[$lang])) {
        return $cache[$lang];
    }

    $baseDir = __DIR__ . '/../../lang';
    $coreFile = $baseDir . '/core/' . $lang . '.php';
    $customFile = $baseDir . '/custom/' . $lang . '.php';

    $core = is_file($coreFile) ? require $coreFile : [];
    $custom = is_file($customFile) ? require $customFile : [];

    $cache[$lang] = array_replace(
        is_array($core) ? $core : [],
        is_array($custom) ? $custom : []
    );

    return $cache[$lang];
}

/**
 * ✅ Ambil terjemahan berdasarkan key
 * Contoh: __('login.title')
 */
function __($key): string {
    $lines = lang_lines(get_current_lang());
    $text = $lines[$key] ?? $key;

    // Normalize encoding and fix common mojibake sequences
    if (!function_exists('fix_mojibake')) {
        function fix_mojibake(string $s): string {
            if ($s === '') return $s;

            if (function_exists('mb_check_encoding') && function_exists('mb_convert_encoding')) {
                // If not valid UTF-8, assume it's CP1252/ISO-8859-1 bytes and convert
                if (!mb_check_encoding($s, 'UTF-8')) {
                    $s = mb_convert_encoding($s, 'UTF-8', 'CP1252');
                } else {
                    // Try a CP1252 round-trip if it produces a different (likely corrected) string
                    $try = mb_convert_encoding($s, 'UTF-8', 'CP1252');
                    if ($try !== $s) {
                        $s = $try;
                    }
                }
            }

            // Common mojibake replacements that sometimes remain
            $map = [
                'â€“' => '–',
                'â€”' => '—',
                'â€˜' => '‘',
                'â€™' => '’',
                'â€œ' => '“',
                'â€'  => '”',
                'â€¦' => '…',
                'Ã©'  => 'é',
                'Ã¨'  => 'è',
                'Ãà'  => 'à',
                'Ãª'  => 'ê',
                'Ã¶'  => 'ö',
                'Ã¼'  => 'ü',
                'Ã±'  => 'ñ',
                'Ã—'  => '×',
                'â‰¥'  => '≥',
                'â‰¤'  => '≤',
            ];

            return strtr($s, $map);
        }
    }

    return fix_mojibake($text);
}

/**
 * ✅ Semak jika key bahasa wujud
 * Contoh: lang_exists('login.title')
 */
function lang_exists(string $key): bool {
    return array_key_exists($key, lang_lines(get_current_lang()));
}

/**
 * ✅ Return kod bahasa semasa, default 'ms'
 */
function get_current_lang(): string {
    return $_SESSION['lang'] ?? 'ms';
}

/**
 * ✅ Dapatkan semua terjemahan bahasa sekarang
 */
function get_all_lang_lines(): array {
    return lang_lines(get_current_lang());
}

/**
 * Compatibility wrapper used across views: tr(key, fallback)
 * Returns translated string if available, otherwise returns fallback or key.
 */
if (!function_exists('tr')) {
    function tr(string $key, ?string $fallback = null): string {
        $t = __($key);
        if ($t === $key) {
            return $fallback ?? $key;
        }
        return $t;
    }
}
