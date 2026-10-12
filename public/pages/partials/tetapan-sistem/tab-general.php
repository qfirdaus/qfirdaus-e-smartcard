            <?php
              if (!function_exists('render_general_field_header')) {
                function render_general_field_header(string $iconClass, string $label, string $helpText): string {
                  $iconClass = htmlspecialchars($iconClass, ENT_QUOTES, 'UTF-8');
                  $label = htmlspecialchars($label, ENT_QUOTES, 'UTF-8');
                  $helpText = htmlspecialchars($helpText, ENT_QUOTES, 'UTF-8');

                  return <<<HTML
                    <div class="general-form-heading">
                      <label class="form-label fw-semibold mb-0">
                        <i class="{$iconClass} me-1 text-muted"></i> {$label}
                        <span class="general-form-help general-form-help-inline">
                          <i class="ri-information-line me-1"></i>{$helpText}
                        </span>
                      </label>
                    </div>
                  HTML;
                }
              }
            ?>
            <!-- Tab 0: Umum -->
            <div class="tab-pane fade <?= (($_GET['tab'] ?? '') === 'general' || !isset($_GET['tab'])) ? 'show active' : '' ?>" id="general-tab" role="tabpanel">
              <form method="POST" id="form-general-aktif" action="<?= htmlspecialchars(url_with_param('tab', 'general'), ENT_QUOTES, 'UTF-8') ?>" data-no-loader="1" novalidate onsubmit="return window.__tetapanAjaxSubmit(event, this, 'btn-simpan-general', 'general');">
                <input type="hidden" name="form_type" value="general_settings" />
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>" />
                <div class="card general-settings-card">
                  <div class="card-header general-settings-header-primary">
                    <div class="d-flex align-items-center">
                      <div class="general-settings-icon bg-primary bg-opacity-10 text-primary me-3">
                        <i class="ri-building-line fs-5"></i>
                      </div>
                      <div>
                        <h5 class="mb-1 fw-semibold text-primary"><?= __('config_general_brand_header') ?? 'Identiti Sistem' ?></h5>
                        <small class="text-muted"><?= __('config_general_brand_sub') ?? 'Paparan utama dan metadata sistem' ?></small>
                      </div>
                    </div>
                  </div>
                  <div class="card-body">
                    <ul class="nav nav-pills general-subtabs" role="tablist">
                      <li class="nav-item" role="presentation">
                        <button class="nav-link active" data-bs-toggle="pill" data-bs-target="#general-subtab-site" type="button" role="tab" onclick="return window.__tetapanShowGeneralSubtab('general-subtab-site', this, event);">
                          <i class="ri-window-line me-1"></i><?= __('config_general_subtab_site') ?? 'Site' ?>
                        </button>
                      </li>
                      <li class="nav-item" role="presentation">
                        <button class="nav-link" data-bs-toggle="pill" data-bs-target="#general-subtab-branding" type="button" role="tab" onclick="return window.__tetapanShowGeneralSubtab('general-subtab-branding', this, event);">
                          <i class="ri-image-2-line me-1"></i><?= __('config_general_subtab_branding') ?? 'Branding' ?>
                        </button>
                      </li>
                      <li class="nav-item" role="presentation">
                        <button class="nav-link" data-bs-toggle="pill" data-bs-target="#general-subtab-identity" type="button" role="tab" onclick="return window.__tetapanShowGeneralSubtab('general-subtab-identity', this, event);">
                          <i class="ri-community-line me-1"></i><?= __('config_general_subtab_identity') ?? 'System & Organization' ?>
                        </button>
                      </li>
                      <li class="nav-item" role="presentation">
                        <button class="nav-link" data-bs-toggle="pill" data-bs-target="#general-subtab-mail" type="button" role="tab" onclick="return window.__tetapanShowGeneralSubtab('general-subtab-mail', this, event);">
                          <i class="ri-mail-settings-line me-1"></i><?= __('config_general_subtab_mail') ?? 'Mail' ?>
                        </button>
                      </li>
                      <li class="nav-item" role="presentation">
                        <button class="nav-link" data-bs-toggle="pill" data-bs-target="#general-subtab-limits" type="button" role="tab" onclick="return window.__tetapanShowGeneralSubtab('general-subtab-limits', this, event);">
                          <i class="ri-timer-line me-1"></i><?= __('config_general_subtab_limits') ?? 'Limits' ?>
                        </button>
                      </li>
                    </ul>

                    <div class="tab-content">
                      <div class="tab-pane fade show active general-subtab-pane" id="general-subtab-site" role="tabpanel">
                        <div class="general-settings-note">
                          <i class="ri-route-line me-2"></i><?= __('config_general_subtab_site_note') ?? 'Tetapan site mengawal title, favicon, dan laluan masuk utama sistem.' ?>
                        </div>
                        <div class="general-form-stack">
                          <div class="general-form-group">
                            <?= render_general_field_header('ri-window-line', __('config_general_site_title') ?? 'Site Title', __('config_general_site_title_help') ?? 'Nama utama sistem untuk title browser dan paparan umum.') ?>
                            <input type="text" name="site_title" class="form-control" maxlength="150" value="<?= htmlspecialchars($generalSettings['site.title'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                          </div>
                          <div class="general-form-group">
                            <?= render_general_field_header('ri-image-line', __('config_general_site_favicon') ?? 'Favicon Path', __('config_general_site_favicon_help') ?? 'Path ikon kecil yang dipaparkan pada tab browser.') ?>
                            <input type="text" name="site_favicon" class="form-control" maxlength="255" value="<?= htmlspecialchars($generalSettings['site.favicon'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                          </div>
                          <div class="row g-3">
                            <div class="col-lg-6">
                              <div class="general-form-group h-100">
                                <?= render_general_field_header('ri-route-line', __('config_general_site_default_home') ?? 'Default Home Route', __('config_general_site_default_home_help') ?? 'Laluan halaman utama selepas login dan untuk pautan logo sistem.') ?>
                                <input type="text" name="site_default_home" class="form-control" maxlength="255" value="<?= htmlspecialchars($generalSettings['site.default_home'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                              </div>
                            </div>
                            <div class="col-lg-6">
                              <div class="general-form-group h-100">
                                <?php
                                  $selectedSidebarUserImage = (string)($generalSettings['branding.sidebar_user_image'] ?? 'assets/images/small/small-5.jpg');
                                ?>
                                <?= render_general_field_header('ri-gallery-line', __('config_general_branding_sidebar_user_image') ?? 'Sidebar User Image', __('config_general_branding_sidebar_user_image_help') ?? 'Pilih imej latar kecil yang dipaparkan di bawah logo dalam sidebar.') ?>
                                <select name="branding_sidebar_user_image" id="branding_sidebar_user_image" class="form-select">
                                  <?php foreach (($sidebarSmallImages ?? []) as $imageOption): ?>
                                    <?php
                                      $imagePath = (string)($imageOption['path'] ?? '');
                                      $imageLabel = (string)($imageOption['label'] ?? basename($imagePath));
                                      $isSelected = $imagePath === $selectedSidebarUserImage;
                                    ?>
                                    <option value="<?= htmlspecialchars($imagePath, ENT_QUOTES, 'UTF-8') ?>" <?= $isSelected ? 'selected' : '' ?>>
                                      <?= htmlspecialchars($imageLabel, ENT_QUOTES, 'UTF-8') ?>
                                    </option>
                                  <?php endforeach; ?>
                                </select>
                              </div>
                            </div>
                          </div>
                        </div>
                      </div>

                      <div class="tab-pane fade general-subtab-pane" id="general-subtab-branding" role="tabpanel">
                        <div class="general-settings-note">
                          <i class="ri-palette-line me-2"></i><?= __('config_general_subtab_branding_note') ?? 'Semua path branding merujuk kepada aset visual seperti logo login, topbar, dan sidebar.' ?>
                        </div>
                        <div class="row g-3">
                          <div class="col-lg-6">
                            <div class="general-form-group h-100">
                            <?= render_general_field_header('ri-image-2-line', __('config_general_branding_login_header_logo') ?? 'Login Header Logo', __('config_general_branding_login_header_logo_help') ?? 'Logo bahagian atas halaman login.') ?>
                            <input type="text" name="branding_login_header_logo" class="form-control" maxlength="255" value="<?= htmlspecialchars($generalSettings['branding.login_header_logo'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                            </div>
                          </div>
                          <div class="col-lg-6">
                            <div class="general-form-group h-100">
                            <?= render_general_field_header('ri-image-2-line', __('config_general_branding_login_panel_logo') ?? 'Login Panel Logo', __('config_general_branding_login_panel_logo_help') ?? 'Logo utama dalam panel login.') ?>
                            <input type="text" name="branding_login_panel_logo" class="form-control" maxlength="255" value="<?= htmlspecialchars($generalSettings['branding.login_panel_logo'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                            </div>
                          </div>
                          <div class="col-lg-6">
                            <div class="general-form-group h-100">
                            <?= render_general_field_header('ri-layout-top-line', __('config_general_branding_topbar_logo_light') ?? 'Topbar Logo Light', __('config_general_branding_topbar_logo_light_help') ?? 'Logo untuk topbar mod terang.') ?>
                            <input type="text" name="branding_topbar_logo_light" class="form-control" maxlength="255" value="<?= htmlspecialchars($generalSettings['branding.topbar_logo_light'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                            </div>
                          </div>
                          <div class="col-lg-6">
                            <div class="general-form-group h-100">
                            <?= render_general_field_header('ri-layout-top-line', __('config_general_branding_topbar_logo_dark') ?? 'Topbar Logo Dark', __('config_general_branding_topbar_logo_dark_help') ?? 'Logo untuk topbar mod gelap.') ?>
                            <input type="text" name="branding_topbar_logo_dark" class="form-control" maxlength="255" value="<?= htmlspecialchars($generalSettings['branding.topbar_logo_dark'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                            </div>
                          </div>
                          <div class="col-lg-6">
                            <div class="general-form-group h-100">
                            <?= render_general_field_header('ri-layout-top-line', __('config_general_branding_topbar_logo_sm') ?? 'Topbar Logo Small', __('config_general_branding_topbar_logo_sm_help') ?? 'Versi kecil logo untuk topbar ringkas.') ?>
                            <input type="text" name="branding_topbar_logo_sm" class="form-control" maxlength="255" value="<?= htmlspecialchars($generalSettings['branding.topbar_logo_sm'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                            </div>
                          </div>
                          <div class="col-lg-6">
                            <div class="general-form-group h-100">
                            <?= render_general_field_header('ri-layout-left-line', __('config_general_branding_sidebar_logo') ?? 'Sidebar Logo', __('config_general_branding_sidebar_logo_help') ?? 'Logo yang dipaparkan dalam sidebar sistem.') ?>
                            <input type="text" name="branding_sidebar_logo" class="form-control" maxlength="255" value="<?= htmlspecialchars($generalSettings['branding.sidebar_logo'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                            </div>
                          </div>
                        </div>
                      </div>

                      <div class="tab-pane fade general-subtab-pane" id="general-subtab-identity" role="tabpanel">
                        <div class="general-settings-note">
                          <i class="ri-building-4-line me-2"></i><?= __('config_general_subtab_identity_note') ?? 'Maklumat ini mengawal identiti sistem, organisasi, metadata, dan footer umum.' ?>
                        </div>
                        <div class="row g-3">
                          <div class="col-lg-6">
                            <div class="general-form-group h-100">
                            <?= render_general_field_header('ri-apps-2-line', __('config_general_system_name') ?? 'Nama Sistem', __('config_general_system_name_help') ?? 'Nama rasmi sistem untuk paparan utama aplikasi.') ?>
                            <input type="text" name="system_name" class="form-control" maxlength="150" value="<?= htmlspecialchars($generalSettings['system.name'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                            </div>
                          </div>
                          <div class="col-lg-6">
                            <div class="general-form-group h-100">
                            <?= render_general_field_header('ri-user-star-line', __('config_general_meta_author') ?? 'Meta Author', __('config_general_meta_author_help') ?? 'Nilai meta author dalam head untuk tujuan metadata sistem.') ?>
                            <input type="text" name="system_meta_author" class="form-control" maxlength="150" value="<?= htmlspecialchars($generalSettings['system.meta_author'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                            </div>
                          </div>
                          <div class="col-lg-6">
                            <div class="general-form-group h-100">
                            <?= render_general_field_header('ri-mail-line', __('config_general_support_email') ?? 'Emel Sokongan', __('config_general_support_email_help') ?? 'Alamat emel sokongan utama untuk rujukan pentadbir atau pengguna.') ?>
                            <input type="email" name="system_support" class="form-control" maxlength="255" value="<?= htmlspecialchars($generalSettings['system.support'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                            </div>
                          </div>
                          <div class="col-lg-6">
                            <div class="general-form-group h-100">
                            <?= render_general_field_header('ri-government-line', __('config_general_org_name') ?? 'Nama Organisasi', __('config_general_org_name_help') ?? 'Nama penuh organisasi pemilik sistem.') ?>
                            <input type="text" name="organization_name" class="form-control" maxlength="150" value="<?= htmlspecialchars($generalSettings['organization.name'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                            </div>
                          </div>
                          <div class="col-lg-6">
                            <div class="general-form-group h-100">
                            <?= render_general_field_header('ri-hashtag', __('config_general_org_short') ?? 'Kod Ringkas Organisasi', __('config_general_org_short_help') ?? 'Kod ringkas atau singkatan organisasi.') ?>
                            <input type="text" name="organization_short" class="form-control" maxlength="50" value="<?= htmlspecialchars($generalSettings['organization.short'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                            </div>
                          </div>
                          <div class="col-lg-6">
                            <div class="general-form-group h-100">
                            <?= render_general_field_header('ri-links-line', __('config_general_org_website') ?? 'Laman Web Organisasi', __('config_general_org_website_help') ?? 'URL laman web rasmi organisasi.') ?>
                            <input type="url" name="organization_website" class="form-control" maxlength="255" value="<?= htmlspecialchars($generalSettings['organization.website'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                            </div>
                          </div>
                          <div class="col-12">
                            <div class="general-form-group">
                            <?= render_general_field_header('ri-align-left', __('config_general_footer_text') ?? 'Teks Footer', __('config_general_footer_text_help') ?? 'Teks footer global yang dipaparkan pada bahagian bawah sistem.') ?>
                            <div class="row g-3">
                              <div class="col-lg-6">
                                <label class="form-label small text-muted mb-1"><?= __('config_general_footer_text_ms') ?? 'Teks Footer (BM)' ?></label>
                                <textarea name="footer_text_ms" class="form-control" maxlength="255"><?= htmlspecialchars($generalSettings['footer.text.ms'] ?? '', ENT_QUOTES, 'UTF-8') ?></textarea>
                              </div>
                              <div class="col-lg-6">
                                <label class="form-label small text-muted mb-1"><?= __('config_general_footer_text_en') ?? 'Footer Text (EN)' ?></label>
                                <textarea name="footer_text_en" class="form-control" maxlength="255"><?= htmlspecialchars($generalSettings['footer.text.en'] ?? '', ENT_QUOTES, 'UTF-8') ?></textarea>
                              </div>
                            </div>
                            </div>
                          </div>
                        </div>
                      </div>

                      <div class="tab-pane fade general-subtab-pane" id="general-subtab-mail" role="tabpanel">
                        <div class="general-settings-note">
                          <i class="ri-mail-settings-line me-2"></i><?= __('config_general_subtab_mail_note') ?? 'Gunakan subtab ini untuk identiti umum yang digunakan oleh template emel sistem.' ?>
                        </div>
                        <div class="general-form-stack">
                          <div class="general-form-group">
                            <?= render_general_field_header('ri-at-line', __('config_general_mail_system_name') ?? 'Nama Sistem Emel', __('config_general_mail_system_name_help') ?? 'Nama sistem yang dipaparkan dalam template emel.') ?>
                            <input type="text" name="mail_system_name" class="form-control" maxlength="150" value="<?= htmlspecialchars($generalSettings['mail.system_name'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                          </div>
                          <div class="general-form-group">
                            <?= render_general_field_header('ri-link', __('config_general_mail_action_url') ?? 'Default Action URL', __('config_general_mail_action_url_help') ?? 'Pautan tindakan lalai yang digunakan oleh emel sistem bila berkaitan.') ?>
                            <input type="url" name="mail_default_action_url" class="form-control" maxlength="255" value="<?= htmlspecialchars($generalSettings['mail.default_action_url'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                          </div>
                          <div class="general-form-group">
                            <?= render_general_field_header('ri-file-text-line', __('config_general_mail_footer_note') ?? 'Nota Footer Emel', __('config_general_mail_footer_note_help') ?? 'Nota footer standard yang dipaparkan di hujung emel sistem.') ?>
                            <div class="row g-3">
                              <div class="col-lg-6">
                                <label class="form-label small text-muted mb-1"><?= __('config_general_mail_footer_note_ms') ?? 'Nota Footer Emel (BM)' ?></label>
                                <textarea name="mail_footer_note_ms" class="form-control" maxlength="255"><?= htmlspecialchars($generalSettings['mail.footer_note.ms'] ?? '', ENT_QUOTES, 'UTF-8') ?></textarea>
                              </div>
                              <div class="col-lg-6">
                                <label class="form-label small text-muted mb-1"><?= __('config_general_mail_footer_note_en') ?? 'Mail Footer Note (EN)' ?></label>
                                <textarea name="mail_footer_note_en" class="form-control" maxlength="255"><?= htmlspecialchars($generalSettings['mail.footer_note.en'] ?? '', ENT_QUOTES, 'UTF-8') ?></textarea>
                              </div>
                            </div>
                          </div>
                        </div>
                      </div>

                      <div class="tab-pane fade general-subtab-pane" id="general-subtab-limits" role="tabpanel">
                        <div class="general-settings-note">
                          <i class="ri-timer-line me-2"></i><?= __('config_general_subtab_limits_note') ?? 'Had tingkah laku ini mempengaruhi tempoh sesi aktif dan saiz maksimum muat naik manual.' ?>
                        </div>
                        <div class="general-form-stack">
                          <div class="general-form-group">
                            <?= render_general_field_header('ri-time-line', __('config_general_session_idle_timeout_minutes') ?? 'Idle Timeout (Minutes)', __('config_general_session_idle_timeout_minutes_help') ?? 'Tempoh tiada aktiviti sebelum sistem memaparkan amaran sesi tamat.') ?>
                            <input type="number" name="session_idle_timeout_minutes" class="form-control" min="1" max="240" value="<?= htmlspecialchars($generalSettings['session.idle_timeout_minutes'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                          </div>
                          <div class="general-form-group">
                            <?= render_general_field_header('ri-eye-line', __('config_general_impersonation_timeout_minutes') ?? 'View As Timeout (Minutes)', __('config_general_impersonation_timeout_minutes_help') ?? 'Tempoh sesi View As sebelum sistem memulihkan akaun asal secara automatik.') ?>
                            <input type="number" name="impersonation_timeout_minutes" class="form-control" min="5" max="240" value="<?= htmlspecialchars($generalSettings['impersonation.timeout_minutes'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                          </div>
                          <div class="general-form-group">
                            <?= render_general_field_header('ri-upload-cloud-line', __('config_general_upload_manual_max_mb') ?? 'Manual Upload Max Size (MB)', __('config_general_upload_manual_max_mb_help') ?? 'Had maksimum saiz fail PDF untuk modul manual pengguna.') ?>
                            <input type="number" name="upload_manual_max_mb" class="form-control" min="1" max="100" value="<?= htmlspecialchars($generalSettings['upload.manual_max_mb'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                          </div>
                        </div>
                      </div>
                    </div>
                  </div>
                </div>
                <div class="general-settings-actions d-flex justify-content-between align-items-center flex-wrap gap-2">
                  <div class="text-muted small">
                    <i class="ri-database-2-line me-1"></i> <?= __('config_general_actions_note') ?? 'Perubahan di sini akan override nilai asas settings.php tanpa menulis semula fail asal.' ?>
                  </div>
                  <button type="submit" class="btn btn-primary px-4" id="btn-simpan-general">
                    <i class="ri-save-3-line me-2"></i> <?= __('config_general_save') ?? 'Simpan Tetapan Umum' ?>
                  </button>
                </div>
              </form>
            </div>
