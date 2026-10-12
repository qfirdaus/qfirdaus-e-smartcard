<?php
declare(strict_types=1);

require_once __DIR__ . '/../public/classes/SystemConfigConstants.php';
require_once __DIR__ . '/../public/includes/theme_palette_helper.php';

function customThemeAssert(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

customThemeAssert(
    in_array('custom', SystemConfigConstants::ALLOWED_THEME_COLORS, true),
    'Pilihan custom tiada dalam whitelist theme.'
);
customThemeAssert(
    preg_match('/^#[0-9A-F]{6}$/', SystemConfigConstants::DEFAULT_THEME_TOPBAR_CUSTOM_SEED) === 1,
    'Default seed topbar tidak sah.'
);
customThemeAssert(
    preg_match('/^#[0-9A-F]{6}$/', SystemConfigConstants::DEFAULT_THEME_SIDEBAR_CUSTOM_SEED) === 1,
    'Default seed sidebar tidak sah.'
);

$paletteSource = file_get_contents(__DIR__ . '/../public/assets/js/theme-palette.js');
$themeCss = file_get_contents(__DIR__ . '/../public/assets/css/app.css');
$systemController = file_get_contents(__DIR__ . '/../public/controllers/TetapanSistemController.php');
$userEndpoint = file_get_contents(__DIR__ . '/../public/setting/save_theme.php');

customThemeAssert(is_string($paletteSource) && str_contains($paletteSource, 'IQSThemePalette'), 'Palette engine tidak tersedia.');
customThemeAssert(is_string($paletteSource) && str_contains($paletteSource, 'luminance'), 'Contrast analysis tidak tersedia.');
customThemeAssert(is_string($themeCss) && str_contains($themeCss, 'html[data-topbar-color=custom]'), 'CSS custom topbar tiada.');
customThemeAssert(is_string($themeCss) && str_contains($themeCss, 'html[data-menu-color=custom]'), 'CSS custom sidebar tiada.');
customThemeAssert(is_string($systemController) && str_contains($systemController, "preg_match('/^#[0-9A-Fa-f]{6}$/'"), 'Validasi hex system theme tiada.');
customThemeAssert(is_string($userEndpoint) && str_contains($userEndpoint, "preg_match('/^#[0-9A-F]{6}$/'"), 'Validasi hex user theme tiada.');

$customPublicPalette = iqs_theme_resolve_public_palette([
    'sidebarColor' => 'custom',
    'sidebarCustomSeed' => '#FFD600',
]);
customThemeAssert($customPublicPalette['accent'] === '#FFD600', 'Seed cerah tidak dikekalkan sebagai accent halaman awam.');
customThemeAssert($customPublicPalette['primary'] !== '#FFD600', 'Seed cerah tidak dilaraskan untuk contrast butang.');
customThemeAssert(iqs_theme_contrast_ratio($customPublicPalette['primary'], '#FFFFFF') >= 4.5, 'Contrast butang custom cerah kurang daripada WCAG AA.');
customThemeAssert(preg_match('/^#[0-9A-F]{6}$/', $customPublicPalette['start']) === 1, 'Gradient custom halaman awam tidak sah.');

$invalidPublicPalette = iqs_theme_resolve_public_palette([
    'sidebarColor' => 'custom',
    'sidebarCustomSeed' => 'url(javascript:alert(1))',
]);
customThemeAssert($invalidPublicPalette === iqs_theme_custom_public_palette('#1E3A5F'), 'Seed custom tidak sah tidak menggunakan fallback selamat.');

$unknownPreset = iqs_theme_resolve_public_palette(['sidebarColor' => 'unknown']);
customThemeAssert($unknownPreset === iqs_theme_public_presets()['light'], 'Preset tidak dikenali tidak kembali kepada light.');

foreach (['index.php', 'forgot-password.php', 'reset-password.php', 'change-password.php'] as $publicPage) {
    $source = file_get_contents(__DIR__ . '/../public/' . $publicPage);
    customThemeAssert(is_string($source) && str_contains($source, 'iqs_theme_resolve_public_palette'), $publicPage . ' tidak menggunakan resolver palette bersama.');
    customThemeAssert(is_string($source) && !str_contains($source, '$themeStyleMap = ['), $publicPage . ' masih mempunyai palette duplicate.');
}

fwrite(STDOUT, "Custom theme regression: OK\n");
