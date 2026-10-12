(function (window, document) {
  'use strict';

  var HEX_PATTERN = /^#[0-9a-f]{6}$/i;

  function normalizeHex(value, fallback) {
    var hex = String(value || '').trim();
    if (/^#[0-9a-f]{3}$/i.test(hex)) {
      hex = '#' + hex.slice(1).split('').map(function (c) { return c + c; }).join('');
    }
    return HEX_PATTERN.test(hex) ? hex.toUpperCase() : (fallback || null);
  }

  function hexToRgb(hex) {
    var value = normalizeHex(hex, '#4254BA').slice(1);
    return {
      r: parseInt(value.slice(0, 2), 16),
      g: parseInt(value.slice(2, 4), 16),
      b: parseInt(value.slice(4, 6), 16)
    };
  }

  function rgbToHsl(rgb) {
    var r = rgb.r / 255, g = rgb.g / 255, b = rgb.b / 255;
    var max = Math.max(r, g, b), min = Math.min(r, g, b);
    var h = 0, s = 0, l = (max + min) / 2;
    if (max !== min) {
      var d = max - min;
      s = l > 0.5 ? d / (2 - max - min) : d / (max + min);
      if (max === r) h = (g - b) / d + (g < b ? 6 : 0);
      else if (max === g) h = (b - r) / d + 2;
      else h = (r - g) / d + 4;
      h *= 60;
    }
    return { h: Math.round(h), s: Math.round(s * 100), l: Math.round(l * 100) };
  }

  function hsl(h, s, l) {
    return 'hsl(' + h + ' ' + Math.max(0, Math.min(100, s)) + '% ' + Math.max(0, Math.min(100, l)) + '%)';
  }

  function luminance(rgb) {
    var parts = [rgb.r, rgb.g, rgb.b].map(function (value) {
      value /= 255;
      return value <= 0.03928 ? value / 12.92 : Math.pow((value + 0.055) / 1.055, 2.4);
    });
    return 0.2126 * parts[0] + 0.7152 * parts[1] + 0.0722 * parts[2];
  }

  function build(seed, surface) {
    var hex = normalizeHex(seed, '#4254BA');
    var rgb = hexToRgb(hex);
    var color = rgbToHsl(rgb);
    var seedLuminance = luminance(rgb);
    var darkContrast = (seedLuminance + 0.05) / 0.05;
    var lightContrast = 1.05 / (seedLuminance + 0.05);
    var isLight = darkContrast >= lightContrast;
    var saturation = Math.max(28, Math.min(72, color.s));
    var mid = Math.max(28, Math.min(68, color.l));
    var start = Math.min(84, mid + (surface === 'sidebar' ? 10 : 14));
    var end = Math.max(18, mid - (surface === 'sidebar' ? 16 : 13));
    var foreground = isLight ? '#172033' : '#FFFFFF';
    var muted = isLight ? 'rgba(23, 32, 51, 0.76)' : 'rgba(255, 255, 255, 0.84)';
    var overlay = isLight ? 'rgba(23, 32, 51, 0.08)' : 'rgba(255, 255, 255, 0.12)';
    var border = isLight ? 'rgba(23, 32, 51, 0.14)' : 'rgba(255, 255, 255, 0.15)';
    return {
      seed: hex,
      foreground: foreground,
      muted: muted,
      overlay: overlay,
      border: border,
      start: hsl(color.h, Math.min(78, saturation + 5), start),
      mid: hsl(color.h, saturation, mid),
      end: hsl(color.h, Math.max(24, saturation - 5), end),
      accent: hsl(color.h, Math.min(82, saturation + 10), isLight ? Math.max(32, mid - 22) : Math.min(76, mid + 20)),
      shadow: 'hsla(' + color.h + ', ' + saturation + '%, ' + Math.max(10, end - 8) + '%, 0.24)',
      isLight: isLight
    };
  }

  function setVariables(root, prefix, palette) {
    root.style.setProperty('--iqs-' + prefix + '-seed', palette.seed);
    root.style.setProperty('--iqs-' + prefix + '-start', palette.start);
    root.style.setProperty('--iqs-' + prefix + '-mid', palette.mid);
    root.style.setProperty('--iqs-' + prefix + '-end', palette.end);
    root.style.setProperty('--iqs-' + prefix + '-fg', palette.foreground);
    root.style.setProperty('--iqs-' + prefix + '-muted', palette.muted);
    root.style.setProperty('--iqs-' + prefix + '-overlay', palette.overlay);
    root.style.setProperty('--iqs-' + prefix + '-border', palette.border);
    root.style.setProperty('--iqs-' + prefix + '-accent', palette.accent);
    root.style.setProperty('--iqs-' + prefix + '-shadow', palette.shadow);
  }

  function apply(kind, seed, root) {
    var target = root || document.documentElement;
    var normalized = normalizeHex(seed, kind === 'sidebar' ? '#1E3A5F' : '#4254BA');
    setVariables(target, kind, build(normalized, kind));
    target.setAttribute(kind === 'sidebar' ? 'data-custom-sidebar-tone' : 'data-custom-topbar-tone', build(normalized, kind).isLight ? 'light' : 'dark');
    return normalized;
  }

  window.IQSThemePalette = {
    normalizeHex: normalizeHex,
    build: build,
    apply: apply,
    applyTheme: function (settings) {
      settings = settings || {};
      if (settings.topbarColor === 'custom') apply('topbar', settings.topbarCustomSeed);
      if (settings.sidebarColor === 'custom') apply('sidebar', settings.sidebarCustomSeed);
    }
  };
})(window, document);
