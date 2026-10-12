            <!-- Tab 4: Bahasa -->
            <div class="tab-pane fade <?= ($_GET['tab'] ?? '') === 'lang' ? 'show active' : '' ?>" id="lang-tab" role="tabpanel">
              <form id="form-bahasa" method="post" data-no-loader="1" novalidate onsubmit="return window.__tetapanAjaxSubmit(event, this, 'btn-simpan-bahasa', 'language');">
                <input type="hidden" name="form_type" value="update_languages">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>" />
                <div class="row g-3">
                  <div class="col-md-12">
                    <div class="card lang-settings-card">
                      <div class="card-header lang-settings-header-danger">
                        <div class="d-flex align-items-center">
                          <div class="lang-settings-icon bg-danger bg-opacity-10 text-danger me-3">
                            <i class="ri-translate-2 fs-5"></i>
                          </div>
                          <div>
                            <h5 class="mb-1 fw-semibold text-danger"><?= __('config_tab_bahasa_header') ?? 'Bahasa yang Tersedia' ?></h5>
                            <small class="text-muted"><?= __('config_tab_bahasa_header_sub') ?? 'Available languages' ?></small>
                          </div>
                        </div>
                      </div>
                      <div class="card-body">
                        <div class="lang-settings-note">
                          <i class="ri-information-line me-2"></i><?= __('config_tab_bahasa_header_details') ?? 'Tandakan bahasa yang ingin diaktifkan untuk digunakan dalam sistem.' ?>
                        </div>
                        <div class="table-responsive lang-settings-table dt-standard-shell">
                          <table class="table table-hover align-middle mb-0">
                          <thead class="table-light">
                            <tr>
                                <th class="text-center fw-semibold" style="width:5%">
                                  <i class="ri-checkbox-line text-muted"></i>
                                </th>
                                <th class="text-center fw-semibold" style="width:15%"><?= __('config_tab_bahasa_default') ?? 'Bahasa Lalai' ?></th>
                                <th class="text-start fw-semibold" style="width:15%"><?= __('config_tab_bahasa_kodBahasa') ?? 'Kod Bahasa' ?></th>
                                <th class="text-start fw-semibold" style="width:65%"><?= __('config_tab_bahasa_peneranganBahasa') ?? 'Penerangan Bahasa' ?></th>
                            </tr>
                          </thead>
                          <tbody>
                            <?php
                              foreach ($senaraiBahasa as $code):
                                $label = strtoupper($code);
                                $flagFile = 'default.png';
                                  $isActive = in_array($code, $bahasaAktif, true);
                                switch ($code) {
                                  case 'ms': $label .= ' - Bahasa Melayu'; $flagFile = 'malaysia.png'; break;
                                  case 'en': $label .= ' - English';       $flagFile = 'united-kingdom.png'; break;
                                  case 'ta': $label .= ' - à®¤à®®à®¿à®´à¯';         $flagFile = 'india.png'; break;
                                  case 'zh': $label .= ' - ä¸­æ–‡';          $flagFile = 'china.png'; break;
                                }
                            ?>
                              <tr class="<?= $isActive ? 'language-row-active' : '' ?>">
                                <td class="text-center align-middle">
                                  <div class="form-check form-check-success d-inline-flex justify-content-center align-items-center m-0">
                                <input class="form-check-input" type="checkbox"
                                       name="languages[]" id="lang_<?= htmlspecialchars($code, ENT_QUOTES, 'UTF-8') ?>"
                                       value="<?= htmlspecialchars($code, ENT_QUOTES, 'UTF-8') ?>"
                                           <?= $isActive ? 'checked' : '' ?>>
                                  </div>
                              </td>
                                <td class="text-center align-middle">
                                  <div class="form-check d-inline-flex justify-content-center">
                                    <input class="form-check-input js-default-language" type="radio"
                                           name="default_language"
                                           id="default_lang_<?= htmlspecialchars($code, ENT_QUOTES, 'UTF-8') ?>"
                                           value="<?= htmlspecialchars($code, ENT_QUOTES, 'UTF-8') ?>"
                                           <?= $bahasaDefault === $code ? 'checked' : '' ?>
                                           <?= $isActive ? '' : 'disabled' ?>>
                                  </div>
                                </td>
                                <td class="align-middle text-start">
                                  <label class="form-check-label fw-bold d-flex align-items-center gap-2 cursor-pointer"
                                       for="lang_<?= htmlspecialchars($code, ENT_QUOTES, 'UTF-8') ?>">
                                  <img loading="lazy"
                                    src="<?= asset_url('images/flags/' . $flagFile) ?>"
                                    alt="<?= htmlspecialchars($code, ENT_QUOTES, 'UTF-8') ?>"
                                      width="28" height="20" 
                                      class="rounded border border-2 shadow-sm"
                                      style="object-fit: cover;">
                                    <span class="badge bg-primary-subtle text-primary px-2 py-1"><?= strtoupper($code) ?></span>
                                </label>
                              </td>
                                <td class="align-middle text-start">
                                  <div class="d-flex align-items-center">
                                    <span class="me-2"><?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?></span>
                                    <?php if ($isActive): ?>
                                      <span class="badge bg-success-subtle text-success border border-success-subtle js-language-active-badge">
                                        <i class="ri-checkbox-circle-fill me-1"></i> <?= __('config_tab_bahasa_status_aktif') ?? 'Aktif' ?>
                                      </span>
                                    <?php endif; ?>
                                    <?php if ($bahasaDefault === $code): ?>
                                      <span class="badge bg-primary-subtle text-primary border border-primary-subtle ms-2 js-language-default-badge">
                                        <i class="ri-star-fill me-1"></i> <?= __('config_tab_bahasa_default') ?? 'Bahasa Lalai' ?>
                                      </span>
                                    <?php endif; ?>
                                  </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                          </tbody>
                        </table>
                      </div>
                    </div>
                  </div>
                  <div class="col-md-12">
                    <div class="lang-runtime-summary row g-2 mb-2" id="lang-runtime-summary">
                      <div class="col-md-6">
                        <div class="lang-settings-note h-100 mb-0">
                          <div class="text-muted small mb-1"><?= __('config_tab_bahasa_status_aktif') ?? 'Aktif' ?></div>
                          <div class="fw-semibold text-body-emphasis" id="lang-runtime-active"><?= htmlspecialchars(implode(', ', array_map('strtoupper', $bahasaAktif)), ENT_QUOTES, 'UTF-8') ?></div>
                        </div>
                      </div>
                      <div class="col-md-6">
                        <div class="lang-settings-note h-100 mb-0">
                          <div class="text-muted small mb-1"><?= __('config_tab_bahasa_default') ?? 'Bahasa Lalai' ?></div>
                          <div class="fw-semibold text-body-emphasis" id="lang-runtime-default"><?= htmlspecialchars(strtoupper((string)$bahasaDefault), ENT_QUOTES, 'UTF-8') ?></div>
                        </div>
                      </div>
                    </div>
                    <div class="lang-settings-actions d-flex justify-content-between align-items-center flex-wrap gap-2">
                      <div class="text-muted small">
                        <i class="ri-translate-2 me-1"></i> <?= __('config_tab_bahasa_actions_note') ?? 'Pastikan sekurang-kurangnya satu bahasa kekal aktif dan satu bahasa lalai dipilih.' ?>
                      </div>
                      <button type="submit" class="btn btn-primary px-4" id="btn-simpan-bahasa">
                        <i class="ri-save-3-line me-2"></i> <?= __('config_tab_bahasa_simpan_tetapan_bahasa') ?? 'Simpan Tetapan Bahasa' ?>
                      </button>
                    </div>
                  </div>
                </div>
              </div>
              </form>
            </div>
