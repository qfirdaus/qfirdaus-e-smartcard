<?php
$esc = static fn(string $value): string => htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
$topbarCustomSeed = strtoupper((string)($themeSettings['topbarCustomSeed'] ?? SystemConfigConstants::DEFAULT_THEME_TOPBAR_CUSTOM_SEED));
$sidebarCustomSeed = strtoupper((string)($themeSettings['sidebarCustomSeed'] ?? SystemConfigConstants::DEFAULT_THEME_SIDEBAR_CUSTOM_SEED));

$themeSections = [
  [
    'key' => 'layout_mode',
    'current' => $layout,
    'column_class' => 'col-md-4',
    'header_class' => 'theme-settings-header-primary',
    'title_class' => 'text-primary',
    'icon_wrap_class' => 'bg-primary bg-opacity-10 text-primary',
    'icon' => 'ri-layout-line fs-5',
    'title' => __('config_tab_tema_komponen_layout') ?? 'Mod Susun Atur',
    'subtitle' => __('config_tab_tema_komponen_layout_sub') ?? 'Layout mode',
    'panel_id' => 'theme-layout-panel',
    'toggle_id' => 'theme-layout-toggle',
    'options' => [
      [
        'value' => 'light',
        'label' => __('config_tab_tema_pilihan_layout_terang') ?? 'Warna Terang',
        'description' => __('config_tab_tema_desc_layout_light') ?? 'Standard mod terang',
        'description_icon' => 'ri-sun-line',
        'preview_style' => 'width: 32px; height: 32px; background: linear-gradient(135deg, #ffffff 0%, #f8fbff 58%, #eef4ff 100%); border: 2px solid #dbe4f0; border-radius: 6px;'
      ],
      [
        'value' => 'dark',
        'label' => __('config_tab_tema_pilihan_layout_gelap') ?? 'Warna Gelap',
        'description' => __('config_tab_tema_desc_layout_dark') ?? 'Sesuai untuk malam',
        'description_icon' => 'ri-moon-line',
        'preview_style' => 'width: 32px; height: 32px; background: linear-gradient(135deg, #1e1e2d 0%, #2b2e4a 100%); border: 2px solid #343a40; border-radius: 6px;'
      ],
    ],
  ],
  [
    'key' => 'topbar_color',
    'current' => $topbar,
    'column_class' => 'col-md-4',
    'header_class' => 'theme-settings-header-info',
    'title_class' => 'text-info',
    'icon_wrap_class' => 'bg-info bg-opacity-10 text-info',
    'icon' => 'ri-layout-top-line fs-5',
    'title' => __('config_tab_tema_komponen_topbar') ?? 'Warna Topbar',
    'subtitle' => __('config_tab_tema_komponen_topbar_sub') ?? 'Topbar color',
    'panel_id' => 'theme-topbar-panel',
    'toggle_id' => 'theme-topbar-toggle',
    'options' => [
      [
        'value' => 'light',
        'label' => __('config_tab_tema_pilihan_topbar_terang') ?? 'Warna Terang',
        'description' => __('config_tab_tema_desc_topbar_light') ?? 'Sesuai mod terang',
        'description_icon' => 'ri-sun-line',
        'preview_style' => 'width: 32px; height: 32px; background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%); border: 2px solid #dee2e6; border-radius: 6px;'
      ],
      [
        'value' => 'dark',
        'label' => __('config_tab_tema_pilihan_topbar_gelap') ?? 'Warna Gelap',
        'description' => __('config_tab_tema_desc_topbar_dark') ?? 'Sesuai mod gelap',
        'description_icon' => 'ri-moon-line',
        'preview_style' => 'width: 32px; height: 32px; background: linear-gradient(135deg, #343a40 0%, #212529 100%); border: 2px solid #495057; border-radius: 6px;'
      ],
      [
        'value' => 'brand',
        'label' => __('config_tab_tema_pilihan_layout_brand') ?? 'Warna Brand',
        'description' => __('config_tab_tema_desc_topbar_brand') ?? 'Warna rasmi sistem',
        'description_icon' => 'ri-palette-line',
        'preview_style' => 'width: 32px; height: 32px; background: linear-gradient(135deg, #3f51b5 0%, #283593 100%); border: 2px solid #1a237e; border-radius: 6px;'
      ],
      [
        'value' => 'emerald',
        'label' => __('config_tab_tema_pilihan_layout_emerald') ?? 'Emerald',
        'description' => __('config_tab_tema_desc_topbar_emerald') ?? 'Hijau moden yang segar dan profesional',
        'description_icon' => 'ri-leaf-line',
        'preview_style' => 'width: 32px; height: 32px; background: linear-gradient(135deg, #0f766e 0%, #0b5f59 100%); border: 2px solid #094b46; border-radius: 6px;'
      ],
      [
        'value' => 'navy',
        'label' => __('config_tab_tema_pilihan_layout_navy') ?? 'Navy',
        'description' => __('config_tab_tema_desc_topbar_navy') ?? 'Biru korporat gelap yang formal dan stabil',
        'description_icon' => 'ri-anchor-line',
        'preview_style' => 'width: 32px; height: 32px; background: linear-gradient(135deg, #1e3a5f 0%, #12263f 100%); border: 2px solid #0f1f34; border-radius: 6px;'
      ],
      [
        'value' => 'sunset',
        'label' => __('config_tab_tema_pilihan_layout_sunset') ?? 'Sunset',
        'description' => __('config_tab_tema_desc_topbar_sunset') ?? 'Jingga hangat dengan karakter yang lebih menonjol',
        'description_icon' => 'ri-sun-foggy-line',
        'preview_style' => 'width: 32px; height: 32px; background: linear-gradient(135deg, #c2410c 0%, #7c2d12 100%); border: 2px solid #6b250f; border-radius: 6px;'
      ],
      [
        'value' => 'mist',
        'label' => __('config_tab_tema_pilihan_layout_mist') ?? 'Mist',
        'description' => __('config_tab_tema_desc_topbar_mist') ?? 'Gradient lembut berais untuk paparan terang yang lebih kemas dan premium',
        'description_icon' => 'ri-cloudy-line',
        'preview_style' => 'width: 32px; height: 32px; background: linear-gradient(135deg, #eef4ff 0%, #dfe9fb 48%, #cfdbf3 100%); border: 2px solid #c5d2e8; border-radius: 6px;'
      ],
      [
        'value' => 'strawberry',
        'label' => __('config_tab_tema_pilihan_layout_strawberry') ?? 'Strawberry Pink',
        'description' => __('config_tab_tema_desc_topbar_strawberry') ?? 'Pink strawberi lembut dengan karakter mesra dan moden',
        'description_icon' => 'ri-heart-3-line',
        'preview_style' => 'width: 32px; height: 32px; background: linear-gradient(135deg, #f48fb1 0%, #e85d8e 55%, #d94678 100%); border: 2px solid #cf5b83; border-radius: 6px;'
      ],
      [
        'value' => 'matcha',
        'label' => __('config_tab_tema_pilihan_layout_matcha') ?? 'Matcha',
        'description' => __('config_tab_tema_desc_topbar_matcha') ?? 'Hijau matcha lembut yang tenang, segar, dan premium',
        'description_icon' => 'ri-plant-line',
        'preview_style' => 'width: 32px; height: 32px; background: linear-gradient(135deg, #bfd49c 0%, #9db77b 55%, #7b915d 100%); border: 2px solid #6b8150; border-radius: 6px;'
      ],
      [
        'value' => 'custom',
        'label' => __('theme_custom') ?? 'Custom',
        'description' => __('config_theme_custom_description') ?? 'Palette pintar daripada warna pilihan anda',
        'description_icon' => 'ri-color-filter-line',
        'preview_style' => 'width: 32px; height: 32px; background: linear-gradient(135deg, color-mix(in srgb, ' . $topbarCustomSeed . ' 72%, white), ' . $topbarCustomSeed . ' 52%, color-mix(in srgb, ' . $topbarCustomSeed . ' 72%, black)); border: 2px solid ' . $topbarCustomSeed . '; border-radius: 6px;'
      ],
    ],
  ],
  [
    'key' => 'sidebar_color',
    'current' => $sidebar,
    'column_class' => 'col-md-4',
    'header_class' => 'theme-settings-header-success',
    'title_class' => 'text-success',
    'icon_wrap_class' => 'bg-success bg-opacity-10 text-success',
    'icon' => 'ri-layout-left-line fs-5',
    'title' => __('config_tab_tema_komponen_sidebar') ?? 'Warna Sidebar',
    'subtitle' => __('config_tab_tema_komponen_sidebar_sub') ?? 'Sidebar color',
    'panel_id' => 'theme-sidebar-panel',
    'toggle_id' => 'theme-sidebar-toggle',
    'options' => [
      [
        'value' => 'light',
        'label' => __('config_tab_tema_pilihan_sidebar_terang') ?? 'Warna Terang',
        'description' => __('config_tab_tema_desc_sidebar_light') ?? 'Latar putih bersih',
        'description_icon' => 'ri-sun-line',
        'preview_style' => 'width: 32px; height: 32px; background: linear-gradient(135deg, #ffffff 0%, #f8f9fa 100%); border: 2px solid #dee2e6; border-radius: 6px;'
      ],
      [
        'value' => 'dark',
        'label' => __('config_tab_tema_pilihan_sidebar_gelap') ?? 'Warna Gelap',
        'description' => __('config_tab_tema_desc_sidebar_dark') ?? 'Selesa untuk mata',
        'description_icon' => 'ri-moon-line',
        'preview_style' => 'width: 32px; height: 32px; background: linear-gradient(135deg, #2b2e4a 0%, #1a1d2e 100%); border: 2px solid #343a40; border-radius: 6px;'
      ],
      [
        'value' => 'brand',
        'label' => __('config_tab_tema_pilihan_sidebar_brand') ?? 'Warna Brand',
        'description' => __('config_tab_tema_desc_sidebar_brand') ?? 'Warna jenama utama',
        'description_icon' => 'ri-palette-line',
        'preview_style' => 'width: 32px; height: 32px; background: linear-gradient(135deg, #3f51b5 0%, #283593 100%); border: 2px solid #1a237e; border-radius: 6px;'
      ],
      [
        'value' => 'emerald',
        'label' => __('config_tab_tema_pilihan_sidebar_emerald') ?? 'Emerald',
        'description' => __('config_tab_tema_desc_sidebar_emerald') ?? 'Hijau moden yang kemas dan profesional',
        'description_icon' => 'ri-leaf-line',
        'preview_style' => 'width: 32px; height: 32px; background: linear-gradient(135deg, #0f766e 0%, #094b46 100%); border: 2px solid #083c38; border-radius: 6px;'
      ],
      [
        'value' => 'navy',
        'label' => __('config_tab_tema_pilihan_sidebar_navy') ?? 'Navy',
        'description' => __('config_tab_tema_desc_sidebar_navy') ?? 'Biru gelap korporat untuk navigasi formal',
        'description_icon' => 'ri-anchor-line',
        'preview_style' => 'width: 32px; height: 32px; background: linear-gradient(135deg, #1e3a5f 0%, #12263f 100%); border: 2px solid #0f1f34; border-radius: 6px;'
      ],
      [
        'value' => 'sunset',
        'label' => __('config_tab_tema_pilihan_sidebar_sunset') ?? 'Sunset',
        'description' => __('config_tab_tema_desc_sidebar_sunset') ?? 'Tone hangat yang lebih berani dan menonjol',
        'description_icon' => 'ri-sun-foggy-line',
        'preview_style' => 'width: 32px; height: 32px; background: linear-gradient(135deg, #c2410c 0%, #7c2d12 100%); border: 2px solid #6b250f; border-radius: 6px;'
      ],
      [
        'value' => 'mist',
        'label' => __('config_tab_tema_pilihan_sidebar_mist') ?? 'Mist',
        'description' => __('config_tab_tema_desc_sidebar_mist') ?? 'Gradient mist lembut dengan rasa navigasi yang bersih dan premium',
        'description_icon' => 'ri-cloudy-line',
        'preview_style' => 'width: 32px; height: 32px; background: linear-gradient(180deg, #eef4ff 0%, #e2ebfb 48%, #d0dbf1 100%); border: 2px solid #c5d2e8; border-radius: 6px;'
      ],
      [
        'value' => 'strawberry',
        'label' => __('config_tab_tema_pilihan_sidebar_strawberry') ?? 'Strawberry Pink',
        'description' => __('config_tab_tema_desc_sidebar_strawberry') ?? 'Pink ros strawberi yang lebih kaya untuk navigasi yang lembut tetapi menyerlah',
        'description_icon' => 'ri-heart-3-line',
        'preview_style' => 'width: 32px; height: 32px; background: linear-gradient(180deg, #d94b7f 0%, #c23d6f 52%, #a93261 100%); border: 2px solid #972b57; border-radius: 6px;'
      ],
      [
        'value' => 'matcha',
        'label' => __('config_tab_tema_pilihan_sidebar_matcha') ?? 'Matcha',
        'description' => __('config_tab_tema_desc_sidebar_matcha') ?? 'Hijau matcha yang lembut dan matang untuk navigasi yang tenang',
        'description_icon' => 'ri-plant-line',
        'preview_style' => 'width: 32px; height: 32px; background: linear-gradient(180deg, #8ba36b 0%, #758d59 52%, #5f7447 100%); border: 2px solid #4f613b; border-radius: 6px;'
      ],
      [
        'value' => 'custom',
        'label' => __('theme_custom') ?? 'Custom',
        'description' => __('config_theme_custom_description') ?? 'Palette pintar daripada warna pilihan anda',
        'description_icon' => 'ri-color-filter-line',
        'preview_style' => 'width: 32px; height: 32px; background: linear-gradient(180deg, color-mix(in srgb, ' . $sidebarCustomSeed . ' 72%, white), ' . $sidebarCustomSeed . ' 52%, color-mix(in srgb, ' . $sidebarCustomSeed . ' 72%, black)); border: 2px solid ' . $sidebarCustomSeed . '; border-radius: 6px;'
      ],
    ],
  ],
];
?>
            <!-- Tab 3: Tema -->
            <div class="tab-pane fade <?= ($_GET['tab'] ?? '') === 'theme' ? 'show active' : '' ?>" id="theme-tab" role="tabpanel">
              <form method="post" id="form-tema-aktif" data-no-loader="1" novalidate onsubmit="return window.__tetapanAjaxSubmit(event, this, 'btn-simpan-tema');">
                <input type="hidden" name="form_type" value="theme_settings">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>" />
                <div class="row g-3">
                  <?php foreach ($themeSections as $section): ?>
                    <?php
                      $currentOption = null;
                      foreach ($section['options'] as $option) {
                        if ($option['value'] === $section['current']) {
                          $currentOption = $option;
                          break;
                        }
                      }
                      if ($currentOption === null) {
                        $currentOption = $section['options'][0];
                      }
                    ?>
                    <div class="<?= $esc($section['column_class']) ?>">
                      <div class="card theme-settings-card theme-settings-section h-100" data-theme-section="<?= $esc($section['key']) ?>">
                        <button
                          type="button"
                          class="card-header <?= $esc($section['header_class']) ?> theme-settings-toggle"
                          id="<?= $esc($section['toggle_id']) ?>"
                          data-theme-toggle
                          data-theme-target="<?= $esc($section['panel_id']) ?>"
                          onclick="return window.__tetapanToggleThemeSection(this);"
                          aria-expanded="false"
                          aria-controls="<?= $esc($section['panel_id']) ?>">
                          <span class="theme-settings-toggle-main">
                            <span class="d-flex align-items-center flex-grow-1">
                              <span class="theme-settings-icon <?= $esc($section['icon_wrap_class']) ?> me-3">
                                <i class="<?= $esc($section['icon']) ?>"></i>
                              </span>
                              <span class="theme-settings-heading-copy">
                                <span class="theme-settings-heading-title mb-1 fw-semibold <?= $esc($section['title_class']) ?>"><?= $esc($section['title']) ?></span>
                                <span class="theme-settings-heading-subtitle text-muted"><?= $esc($section['subtitle']) ?></span>
                              </span>
                            </span>
                            <span class="theme-settings-summary-wrap">
                              <span class="theme-settings-summary-kicker">Active</span>
                              <span class="theme-settings-summary-chip">
                                <span class="theme-settings-summary-preview" data-theme-summary-preview style="<?= $esc($currentOption['preview_style']) ?>"></span>
                                <span class="theme-settings-summary-value" data-theme-summary-label><?= $esc($currentOption['label']) ?></span>
                              </span>
                            </span>
                            <span class="theme-settings-toggle-caret" aria-hidden="true">
                              <i class="ri-arrow-down-s-line"></i>
                            </span>
                          </span>
                        </button>
                        <div class="card-body theme-settings-panel" id="<?= $esc($section['panel_id']) ?>" hidden>
                          <div class="d-flex flex-column gap-1">
                            <?php foreach ($section['options'] as $option): ?>
                              <?php $isActive = $section['current'] === $option['value']; ?>
                              <label
                                class="theme-option <?= $isActive ? 'active' : '' ?>"
                                for="<?= $esc($section['key'] . '_' . $option['value']) ?>"
                                data-theme-label="<?= $esc($option['label']) ?>"
                                data-theme-preview="<?= $esc($option['preview_style']) ?>">
                                <input
                                  class="form-check-input"
                                  type="radio"
                                  name="<?= $esc($section['key']) ?>"
                                  id="<?= $esc($section['key'] . '_' . $option['value']) ?>"
                                  value="<?= $esc($option['value']) ?>"
                                  <?= $isActive ? 'checked' : '' ?>>
                                <div class="d-flex align-items-center gap-2 flex-grow-1">
                                  <div class="theme-preview" style="<?= $esc($option['preview_style']) ?>"></div>
                                  <div class="flex-grow-1">
                                    <div class="fw-semibold small"><?= $esc($option['label']) ?></div>
                                    <div class="text-muted" style="font-size: 0.75rem;">
                                      <i class="<?= $esc($option['description_icon']) ?> me-1"></i> <?= $esc($option['description']) ?>
                                    </div>
                                  </div>
                                </div>
                              </label>
                            <?php endforeach; ?>
                            <?php if ($section['key'] === 'topbar_color' || $section['key'] === 'sidebar_color'): ?>
                              <?php
                                $isTopbarCustom = $section['key'] === 'topbar_color';
                                $customName = $isTopbarCustom ? 'topbar_custom_seed' : 'sidebar_custom_seed';
                                $customValue = $isTopbarCustom ? $topbarCustomSeed : $sidebarCustomSeed;
                              ?>
                              <div class="theme-custom-editor mt-2 p-3 rounded border" data-custom-editor="<?= $isTopbarCustom ? 'topbar' : 'sidebar' ?>">
                                <label class="form-label small fw-semibold mb-2" for="<?= $esc($customName) ?>_picker">
                                  <?= $esc(__('config_theme_custom_seed_label') ?? 'Warna asas custom') ?>
                                </label>
                                <div class="input-group">
                                  <input type="color" class="form-control form-control-color" id="<?= $esc($customName) ?>_picker" value="<?= $esc($customValue) ?>" aria-label="<?= $esc(__('config_theme_custom_seed_label') ?? 'Warna asas custom') ?>">
                                  <input type="text" class="form-control font-monospace" name="<?= $esc($customName) ?>" value="<?= $esc($customValue) ?>" maxlength="7" pattern="#[0-9A-Fa-f]{6}" data-custom-seed>
                                  <button type="button" class="btn btn-outline-secondary" data-custom-reset data-default-seed="<?= $isTopbarCustom ? '#4254BA' : '#1E3A5F' ?>">
                                    <?= $esc(__('config_theme_custom_reset') ?? 'Reset') ?>
                                  </button>
                                </div>
                                <div class="form-text"><?= $esc(__('config_theme_custom_help') ?? 'Gradient, teks, hover dan contrast dijana secara automatik.') ?></div>
                              </div>
                            <?php endif; ?>
                          </div>
                        </div>
                      </div>
                    </div>
                  <?php endforeach; ?>
                </div>

                <div class="theme-settings-actions d-flex justify-content-between align-items-center flex-wrap gap-2">
                  <div class="text-muted small">
                    <i class="ri-palette-line me-1"></i> <?= __('config_tab_tema_actions_note') ?? 'Simpan hanya selepas kombinasi layout, topbar, dan sidebar benar-benar sesuai.' ?>
                  </div>
                  <button type="submit" class="btn btn-primary px-4" id="btn-simpan-tema">
                    <i class="ri-save-3-line me-2"></i> <?= __('config_tab_db_simpan_tetapan_tema') ?? 'Simpan Tetapan Tema' ?>
                  </button>
                </div>
              </form>
            </div>
            <script>
              (function () {
                var form = document.getElementById('form-tema-aktif');
                if (!form) {
                  return;
                }

                var storageKey = 'tetapan-sistem.theme-sections';
                var sections = Array.prototype.slice.call(form.querySelectorAll('[data-theme-section]'));

                function readState() {
                  try {
                    return JSON.parse(window.sessionStorage.getItem(storageKey) || '{}') || {};
                  } catch (error) {
                    return {};
                  }
                }

                function writeState() {
                  var nextState = {};
                  sections.forEach(function (section) {
                    var key = section.getAttribute('data-theme-section') || '';
                    var toggle = section.querySelector('[data-theme-toggle]');
                    if (key && toggle) {
                      nextState[key] = toggle.getAttribute('aria-expanded') === 'true';
                    }
                  });
                  try {
                    window.sessionStorage.setItem(storageKey, JSON.stringify(nextState));
                  } catch (error) {
                    // ignore
                  }
                }

                function syncSummary(section) {
                  var checkedInput = section.querySelector('input[type="radio"]:checked');
                  var summaryLabel = section.querySelector('[data-theme-summary-label]');
                  var summaryPreview = section.querySelector('[data-theme-summary-preview]');
                  if (!checkedInput || !summaryLabel || !summaryPreview) {
                    return;
                  }

                  var activeOption = checkedInput.closest('.theme-option');
                  if (!activeOption) {
                    return;
                  }

                  summaryLabel.textContent = activeOption.getAttribute('data-theme-label') || checkedInput.value || '';
                  summaryPreview.style.cssText = activeOption.getAttribute('data-theme-preview') || '';

                  section.querySelectorAll('.theme-option').forEach(function (option) {
                    option.classList.toggle('active', option === activeOption);
                  });
                }

                function setExpanded(section, expanded) {
                  var toggle = section.querySelector('[data-theme-toggle]');
                  var panel = section.querySelector('.theme-settings-panel');
                  if (!toggle || !panel) {
                    return false;
                  }

                  section.classList.toggle('is-expanded', !!expanded);
                  toggle.setAttribute('aria-expanded', expanded ? 'true' : 'false');
                  panel.hidden = !expanded;
                  return false;
                }

                window.__tetapanSetThemeSectionExpanded = function (section, expanded) {
                  setExpanded(section, expanded);
                  writeState();
                  return false;
                };

                window.__tetapanToggleThemeSection = function (toggleEl) {
                  var button = toggleEl && toggleEl.closest ? toggleEl.closest('[data-theme-toggle]') : null;
                  if (!button) {
                    return false;
                  }
                  var section = button.closest('[data-theme-section]');
                  if (!section) {
                    return false;
                  }
                  var expanded = button.getAttribute('aria-expanded') === 'true';
                  return window.__tetapanSetThemeSectionExpanded(section, !expanded);
                };

                var storedState = readState();
                sections.forEach(function (section) {
                  var key = section.getAttribute('data-theme-section') || '';
                  setExpanded(section, storedState[key] === true);
                  syncSummary(section);
                  section.querySelectorAll('input[type="radio"]').forEach(function (radio) {
                    radio.addEventListener('change', function () {
                      syncSummary(section);
                      var surface = section.getAttribute('data-theme-section') === 'topbar_color' ? 'topbar' : (section.getAttribute('data-theme-section') === 'sidebar_color' ? 'sidebar' : '');
                      if (surface && radio.value === 'custom') {
                        var seed = section.querySelector('[data-custom-seed]');
                        if (seed && window.IQSThemePalette) {
                          window.IQSThemePalette.apply(surface, seed.value);
                          document.documentElement.setAttribute(surface === 'topbar' ? 'data-topbar-color' : 'data-menu-color', 'custom');
                          document.body.setAttribute(surface === 'topbar' ? 'data-topbar-color' : 'data-menu-color', 'custom');
                        }
                      }
                    });
                  });
                  var editor = section.querySelector('[data-custom-editor]');
                  if (editor) {
                    var picker = editor.querySelector('input[type="color"]');
                    var seedInput = editor.querySelector('[data-custom-seed]');
                    var reset = editor.querySelector('[data-custom-reset]');
                    var surface = editor.getAttribute('data-custom-editor');
                    var applySeed = function (value) {
                      var normalized = window.IQSThemePalette ? window.IQSThemePalette.normalizeHex(value, null) : null;
                      if (!normalized) return;
                      picker.value = normalized;
                      seedInput.value = normalized;
                      window.IQSThemePalette.apply(surface, normalized);
                      var customRadio = section.querySelector('input[type="radio"][value="custom"]');
                      if (customRadio) {
                        customRadio.checked = true;
                        var customOption = customRadio.closest('.theme-option');
                        if (customOption) {
                          var direction = surface === 'topbar' ? '135deg' : '180deg';
                          customOption.setAttribute('data-theme-preview', 'width: 32px; height: 32px; background: linear-gradient(' + direction + ', color-mix(in srgb, ' + normalized + ' 72%, white), ' + normalized + ' 52%, color-mix(in srgb, ' + normalized + ' 72%, black)); border: 2px solid ' + normalized + '; border-radius: 6px;');
                          var preview = customOption.querySelector('.theme-preview');
                          if (preview) preview.style.cssText = customOption.getAttribute('data-theme-preview');
                        }
                      }
                      document.documentElement.setAttribute(surface === 'topbar' ? 'data-topbar-color' : 'data-menu-color', 'custom');
                      document.body.setAttribute(surface === 'topbar' ? 'data-topbar-color' : 'data-menu-color', 'custom');
                      syncSummary(section);
                    };
                    picker.addEventListener('input', function () { applySeed(picker.value); });
                    seedInput.addEventListener('change', function () { applySeed(seedInput.value); });
                    reset.addEventListener('click', function () { applySeed(reset.getAttribute('data-default-seed')); });
                  }
                });

                window.__tetapanSyncThemeSectionUi = function () {
                  sections.forEach(syncSummary);
                };

                writeState();
              })();
            </script>
