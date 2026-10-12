<?php
declare(strict_types=1);

/**
 * Shared palette resolver for unauthenticated login and password pages.
 * Only normalized colour values are returned; raw CSS is never accepted.
 */
function iqs_theme_normalize_hex(mixed $value, string $fallback = '#64748B'): string
{
    $hex = strtoupper(trim((string)$value));
    return preg_match('/^#[0-9A-F]{6}$/', $hex) === 1 ? $hex : $fallback;
}

/** @return array{0:int,1:int,2:int} */
function iqs_theme_hex_to_rgb(string $hex): array
{
    $hex = ltrim(iqs_theme_normalize_hex($hex), '#');
    return [hexdec(substr($hex, 0, 2)), hexdec(substr($hex, 2, 2)), hexdec(substr($hex, 4, 2))];
}

/** @param array{0:int,1:int,2:int} $rgb */
function iqs_theme_rgb_to_hex(array $rgb): string
{
    return sprintf('#%02X%02X%02X', ...array_map(static fn(int $value): int => max(0, min(255, $value)), $rgb));
}

function iqs_theme_mix(string $hex, string $target, float $amount): string
{
    $sourceRgb = iqs_theme_hex_to_rgb($hex);
    $targetRgb = iqs_theme_hex_to_rgb($target);
    $mixed = [];
    foreach ([0, 1, 2] as $index) {
        $mixed[] = (int)round($sourceRgb[$index] + (($targetRgb[$index] - $sourceRgb[$index]) * $amount));
    }
    return iqs_theme_rgb_to_hex($mixed);
}

function iqs_theme_relative_luminance(string $hex): float
{
    $channels = array_map(static function (int $value): float {
        $channel = $value / 255;
        return $channel <= 0.03928 ? $channel / 12.92 : (($channel + 0.055) / 1.055) ** 2.4;
    }, iqs_theme_hex_to_rgb($hex));
    return (0.2126 * $channels[0]) + (0.7152 * $channels[1]) + (0.0722 * $channels[2]);
}

function iqs_theme_contrast_ratio(string $first, string $second): float
{
    $firstLuminance = iqs_theme_relative_luminance($first);
    $secondLuminance = iqs_theme_relative_luminance($second);
    $lighter = max($firstLuminance, $secondLuminance);
    $darker = min($firstLuminance, $secondLuminance);
    return ($lighter + 0.05) / ($darker + 0.05);
}

/** @return array{start:string,end:string,primary:string,primaryStrong:string,accent:string,primaryRgb:string,accentRgb:string} */
function iqs_theme_custom_public_palette(string $seed): array
{
    $seed = iqs_theme_normalize_hex($seed, '#1E3A5F');
    $isLight = iqs_theme_contrast_ratio($seed, '#FFFFFF') < 4.5;
    $primary = $seed;
    if ($isLight) {
        for ($attempt = 0; $attempt < 8 && iqs_theme_contrast_ratio($primary, '#FFFFFF') < 4.5; $attempt++) {
            $primary = iqs_theme_mix($primary, '#000000', 0.12);
        }
    }
    $primaryStrong = iqs_theme_mix($primary, '#000000', 0.24);
    $accent = $isLight ? $seed : iqs_theme_mix($seed, '#FFFFFF', 0.34);
    $surfaceSeed = $isLight ? $primary : $seed;
    $start = iqs_theme_mix($surfaceSeed, '#FFFFFF', $isLight ? 0.06 : 0.24);
    $end = iqs_theme_mix($surfaceSeed, '#000000', $isLight ? 0.16 : 0.22);
    $primaryRgb = implode(', ', iqs_theme_hex_to_rgb($primary));
    $accentRgb = implode(', ', iqs_theme_hex_to_rgb($accent));

    return compact('start', 'end', 'primary', 'primaryStrong', 'accent', 'primaryRgb', 'accentRgb');
}

/** @return array<string,array{start:string,end:string,primary:string,primaryStrong:string,accent:string,primaryRgb:string,accentRgb:string}> */
function iqs_theme_public_presets(): array
{
    return [
        'light' => ['start' => '#6F86A3', 'end' => '#8EA2BB', 'primary' => '#64748B', 'primaryStrong' => '#475569', 'accent' => '#94A3B8', 'primaryRgb' => '100, 116, 139', 'accentRgb' => '148, 163, 184'],
        'dark' => ['start' => '#111827', 'end' => '#1F2937', 'primary' => '#374151', 'primaryStrong' => '#111827', 'accent' => '#6B7280', 'primaryRgb' => '55, 65, 81', 'accentRgb' => '107, 114, 128'],
        'brand' => ['start' => '#0B4FD6', 'end' => '#0F9DB1', 'primary' => '#0F4FD6', 'primaryStrong' => '#0B3CAA', 'accent' => '#0F9DB1', 'primaryRgb' => '15, 79, 214', 'accentRgb' => '15, 157, 177'],
        'emerald' => ['start' => '#0F766E', 'end' => '#34D399', 'primary' => '#10B981', 'primaryStrong' => '#0F766E', 'accent' => '#6EE7B7', 'primaryRgb' => '16, 185, 129', 'accentRgb' => '110, 231, 183'],
        'navy' => ['start' => '#0C1B32', 'end' => '#173B6B', 'primary' => '#1D4ED8', 'primaryStrong' => '#0C1B32', 'accent' => '#60A5FA', 'primaryRgb' => '29, 78, 216', 'accentRgb' => '96, 165, 250'],
        'sunset' => ['start' => '#B45309', 'end' => '#F97316', 'primary' => '#EA580C', 'primaryStrong' => '#B45309', 'accent' => '#FB923C', 'primaryRgb' => '234, 88, 12', 'accentRgb' => '251, 146, 60'],
        'mist' => ['start' => '#475569', 'end' => '#64748B', 'primary' => '#64748B', 'primaryStrong' => '#475569', 'accent' => '#94A3B8', 'primaryRgb' => '100, 116, 139', 'accentRgb' => '148, 163, 184'],
        'strawberry' => ['start' => '#BE185D', 'end' => '#F43F5E', 'primary' => '#E11D48', 'primaryStrong' => '#BE185D', 'accent' => '#FB7185', 'primaryRgb' => '225, 29, 72', 'accentRgb' => '251, 113, 133'],
        'matcha' => ['start' => '#3F6212', 'end' => '#65A30D', 'primary' => '#65A30D', 'primaryStrong' => '#3F6212', 'accent' => '#A3E635', 'primaryRgb' => '101, 163, 13', 'accentRgb' => '163, 230, 53'],
    ];
}

/** @return array{start:string,end:string,primary:string,primaryStrong:string,accent:string,primaryRgb:string,accentRgb:string} */
function iqs_theme_resolve_public_palette(array $settings, ?string $theme = null): array
{
    $selected = strtolower(trim((string)($theme ?? ($settings['sidebarColor'] ?? 'light'))));
    if ($selected === 'custom') {
        return iqs_theme_custom_public_palette((string)($settings['sidebarCustomSeed'] ?? '#1E3A5F'));
    }

    $presets = iqs_theme_public_presets();
    return $presets[$selected] ?? $presets['light'];
}
