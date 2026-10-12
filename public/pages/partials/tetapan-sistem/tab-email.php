            <!-- Tab 1: Emel -->
            <div class="tab-pane fade <?= ($_GET['tab'] ?? '') === 'email' ? 'show active' : '' ?>" id="email-tab" role="tabpanel">
              <form method="POST" id="form-emel-aktif" data-no-loader="1" novalidate onsubmit="return window.__tetapanAjaxSubmit(event, this, 'btn-simpan-emel');">
                <input type="hidden" name="form_type" value="email_settings" />
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>" />
                <div class="row gx-3 gy-0 align-items-stretch email-settings-panels">
                  <!-- Pelayan Emel -->
                  <div class="col-lg-4">
                    <div class="card email-settings-card h-100">
                      <div class="card-header email-settings-header-primary">
                        <div class="d-flex align-items-center">
                          <div class="email-settings-icon bg-primary bg-opacity-10 text-primary me-3">
                            <i class="ri-mail-settings-line fs-5"></i>
                          </div>
                          <div>
                            <h5 class="mb-1 fw-semibold text-primary"><?= __('config_tab_emel_header_setting') ?? 'Konfigurasi Pelayan Emel' ?></h5>
                            <small class="text-muted"><?= __('config_tab_emel_header_setting_sub') ?? 'Server configuration' ?></small>
                          </div>
                        </div>
                      </div>
                      <div class="card-body">
                        <div class="row g-3">
                          <div class="col-12">
                          <label class="form-label fw-semibold">
                            <i class="ri-settings-3-line me-1 text-muted"></i> <?= __('config_tab_emel_driver') ?? 'Mail Driver' ?>
                          </label>
                          <input type="text" name="mail_driver" class="form-control" value="<?= htmlspecialchars($emailSettings['mail_driver'] ?? '', ENT_QUOTES, 'UTF-8') ?>" placeholder="smtp" maxlength="50">
                          </div>
                          <div class="col-12">
                          <label class="form-label fw-semibold">
                            <i class="ri-global-line me-1 text-muted"></i> <?= __('config_tab_emel_host') ?? 'Mail Host' ?>
                          </label>
                          <input type="text" name="mail_host" class="form-control" value="<?= htmlspecialchars($emailSettings['mail_host'] ?? '', ENT_QUOTES, 'UTF-8') ?>" placeholder="smtp.gmail.com" maxlength="255">
                          </div>
                          <div class="col-sm-6">
                          <label class="form-label fw-semibold">
                            <i class="ri-plug-line me-1 text-muted"></i> <?= __('config_tab_emel_port') ?? 'Port' ?>
                          </label>
                          <input type="text" name="mail_port" class="form-control" value="<?= htmlspecialchars($emailSettings['mail_port'] ?? '', ENT_QUOTES, 'UTF-8') ?>" placeholder="587" maxlength="5">
                          </div>
                          <div class="col-sm-6">
                          <label class="form-label fw-semibold">
                            <i class="ri-shield-check-line me-1 text-muted"></i> <?= __('config_tab_emel_encryption') ?? 'Encryption' ?>
                          </label>
                          <select name="mail_encryption" class="form-select">
                            <option value=""><?= __('config_tab_emel_sel_tiada') ?? 'Tiada' ?></option>
                            <option value="tls" <?= ($emailSettings['mail_encryption'] ?? '') === 'tls' ? 'selected' : '' ?>>TLS</option>
                            <option value="ssl" <?= ($emailSettings['mail_encryption'] ?? '') === 'ssl' ? 'selected' : '' ?>>SSL</option>
                          </select>
                          </div>
                          <div class="col-12">
                            <div class="email-settings-note">
                              <i class="ri-information-line me-1"></i> <?= __('config_tab_emel_note_server') ?? 'Gunakan konfigurasi SMTP sebenar yang dibenarkan oleh pelayan.' ?>
                            </div>
                          </div>
                        </div>
                        </div>
                      </div>
                    </div>
                  <!-- Akaun Emel -->
                  <div class="col-lg-8">
                    <div class="card email-settings-card h-100">
                      <div class="card-header email-settings-header-info">
                        <div class="d-flex align-items-center">
                          <div class="email-settings-icon bg-info bg-opacity-10 text-info me-3">
                            <i class="ri-user-settings-line fs-5"></i>
                          </div>
                          <div>
                            <h5 class="mb-1 fw-semibold text-info"><?= __('config_tab_emel_header_emel') ?? 'Butiran Akaun Emel' ?></h5>
                            <small class="text-muted"><?= __('config_tab_emel_header_emel_sub') ?? 'Sender identity and credentials' ?></small>
                          </div>
                        </div>
                      </div>
                      <div class="card-body">
                        <div class="row g-3">
                          <div class="col-md-6">
                          <label class="form-label fw-semibold">
                            <i class="ri-mail-line me-1 text-muted"></i> <?= __('config_tab_emel_account_emel') ?? 'Email Account (Username)' ?>
                          </label>
                          <input type="email" name="mail_username" class="form-control" value="<?= htmlspecialchars($emailSettings['mail_username'] ?? '', ENT_QUOTES, 'UTF-8') ?>" maxlength="255">
                          </div>
                          <div class="col-md-6">
                          <label class="form-label fw-semibold">
                            <i class="ri-lock-password-line me-1 text-muted"></i> <?= __('config_tab_emel_katalaluan_emel') ?? 'Kata Laluan Emel' ?>
                          </label>
                          <input type="password" name="mail_password" class="form-control" placeholder="Biarkan kosong jika tidak mahu tukar" autocomplete="new-password">
                          <small class="text-muted d-block mt-1">
                            <i class="ri-information-line me-1"></i> <?= __('config_tab_emel_password_hint') ?? 'Biarkan kosong untuk mengekalkan kata laluan semasa' ?>
                          </small>
                          </div>
                          <div class="col-md-6">
                          <label class="form-label fw-semibold">
                            <i class="ri-send-plane-line me-1 text-muted"></i> <?= __('config_tab_emel_from') ?? 'Email daripada?' ?>
                          </label>
                          <input type="email" name="mail_from_address" class="form-control" value="<?= htmlspecialchars($emailSettings['mail_from_address'] ?? '', ENT_QUOTES, 'UTF-8') ?>" maxlength="255">
                          </div>
                          <div class="col-md-6">
                          <label class="form-label fw-semibold">
                            <i class="ri-user-line me-1 text-muted"></i> <?= __('config_tab_emel_from_name') ?? 'Nama Pemilik Email' ?>
                          </label>
                          <input type="text" name="mail_from_name" class="form-control" value="<?= htmlspecialchars($emailSettings['mail_from_name'] ?? '', ENT_QUOTES, 'UTF-8') ?>" maxlength="255">
                          </div>
                          <div class="col-12">
                            <div class="email-settings-note">
                              <i class="ri-shield-user-line me-1"></i> <?= __('config_tab_emel_note_sender') ?? 'Pastikan alamat From dan akaun SMTP sepadan dengan polisi pelayan untuk elak mesej ditolak.' ?>
                            </div>
                          </div>
                        </div>
                        </div>
                      </div>
                  </div>
                </div>
                <div class="email-runtime-summary row g-2 mt-2" id="email-runtime-summary">
                  <div class="col-sm-6 col-xl-3">
                    <div class="email-settings-note h-100 mb-0">
                      <div class="text-muted small mb-1"><?= __('config_tab_emel_driver') ?? 'Mail Driver' ?></div>
                      <div class="fw-semibold text-body-emphasis" id="email-runtime-driver"><?= htmlspecialchars((string)($emailSettings['mail_driver'] ?? '-'), ENT_QUOTES, 'UTF-8') ?></div>
                    </div>
                  </div>
                  <div class="col-sm-6 col-xl-3">
                    <div class="email-settings-note h-100 mb-0">
                      <div class="text-muted small mb-1"><?= __('config_tab_emel_host') ?? 'Mail Host' ?></div>
                      <div class="fw-semibold text-body-emphasis text-break" id="email-runtime-host"><?= htmlspecialchars(trim((string)($emailSettings['mail_host'] ?? '')) !== '' ? trim((string)($emailSettings['mail_host'] ?? '')) . ':' . trim((string)($emailSettings['mail_port'] ?? '')) : '-', ENT_QUOTES, 'UTF-8') ?></div>
                    </div>
                  </div>
                  <div class="col-sm-6 col-xl-3">
                    <div class="email-settings-note h-100 mb-0">
                      <div class="text-muted small mb-1"><?= __('config_tab_emel_from') ?? 'Email From' ?></div>
                      <div class="fw-semibold text-body-emphasis text-break" id="email-runtime-sender"><?= htmlspecialchars((string)($emailSettings['mail_from_address'] ?? '-'), ENT_QUOTES, 'UTF-8') ?></div>
                    </div>
                  </div>
                  <div class="col-sm-6 col-xl-3">
                    <div class="email-settings-note h-100 mb-0">
                      <div class="text-muted small mb-1"><?= __('config_tab_emel_encryption') ?? 'Encryption' ?></div>
                      <div class="fw-semibold text-body-emphasis" id="email-runtime-encryption"><?= htmlspecialchars(strtoupper((string)($emailSettings['mail_encryption'] ?? '')) ?: '-', ENT_QUOTES, 'UTF-8') ?></div>
                    </div>
                  </div>
                </div>
                <div class="email-settings-actions d-flex justify-content-between align-items-center flex-wrap gap-2 mt-2">
                  <div class="text-muted small">
                    <i class="ri-mail-settings-line me-1"></i> <?= __('config_tab_emel_actions_note') ?? 'Simpan hanya selepas maklumat SMTP dan akaun emel disahkan betul.' ?>
                  </div>
                  <div class="d-flex justify-content-end gap-2 flex-wrap">
                    <button type="button" class="btn btn-outline-secondary px-4" id="btn-uji-emel" onclick="return window.__tetapanOpenEmailTest(event);">
                      <i class="ri-mail-send-line me-2"></i> <?= __('config_tab_emel_uji_emel') ?? 'Uji Sambungan Emel' ?>
                    </button>
                    <button type="submit" class="btn btn-primary px-4" id="btn-simpan-emel">
                      <i class="ri-save-3-line me-2"></i> <?= __('config_tab_emel_simpan_tetapan_emel') ?? 'Simpan Tetapan Emel' ?>
                    </button>
                  </div>
                </div>
              </form>
            </div>
