            <!-- Tab 2: Pangkalan Data -->
            <div class="tab-pane fade <?= ($_GET['tab'] ?? '') === 'db' ? 'show active' : '' ?>" id="db-tab" role="tabpanel">
              <form method="post" id="form-db-aktif" autocomplete="off" data-no-loader="1" novalidate onsubmit="return window.__tetapanAjaxSubmit(event, this, 'btn-simpan-db');">
                <input type="hidden" name="submit_db" value="1">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>" />
                <div class="card general-settings-card">
                  <div class="card-header general-settings-header-info">
                    <div class="d-flex align-items-center">
                      <div class="general-settings-icon bg-info bg-opacity-10 text-info me-3">
                        <i class="ri-database-2-line fs-5"></i>
                      </div>
                      <div>
                        <h5 class="mb-1 fw-semibold text-info"><?= __('config_tab_db') ?? 'Database' ?></h5>
                        <small class="text-muted"><?= __('config_tab_db_container_sub') ?? 'Manage Sybase runtime selection and view the main MySQL connection details.' ?></small>
                      </div>
                    </div>
                  </div>
                  <div class="card-body">
                <ul class="nav nav-pills general-subtabs" id="dbSubtabNav" role="tablist">
                  <li class="nav-item" role="presentation">
                    <button class="nav-link active" id="db-subtab-sybase-tab" data-bs-toggle="tab" data-bs-target="#db-subtab-sybase" type="button" role="tab" aria-controls="db-subtab-sybase" aria-selected="true">
                      <i class="ri-database-2-line me-1"></i> <?= __('config_tab_db_subtab_sybase') ?? 'Sybase' ?>
                    </button>
                  </li>
                  <li class="nav-item" role="presentation">
                    <button class="nav-link" id="db-subtab-mysql-tab" data-bs-toggle="tab" data-bs-target="#db-subtab-mysql" type="button" role="tab" aria-controls="db-subtab-mysql" aria-selected="false">
                      <i class="ri-server-line me-1"></i> <?= __('config_tab_db_subtab_mysql') ?? 'MySQL' ?>
                    </button>
                  </li>
                  <li class="nav-item" role="presentation">
                    <button class="nav-link" id="db-subtab-additional-tab" data-bs-toggle="tab" data-bs-target="#db-subtab-additional" type="button" role="tab" aria-controls="db-subtab-additional" aria-selected="false">
                      <i class="ri-stack-line me-1"></i> <?= __('config_tab_db_subtab_additional') ?? 'Additional Connections' ?>
                    </button>
                  </li>
                </ul>
                <div class="tab-content">
                  <div class="tab-pane fade show active general-subtab-pane" id="db-subtab-sybase" role="tabpanel" aria-labelledby="db-subtab-sybase-tab">
                <div class="general-settings-note mb-3">
                  <i class="ri-database-2-line me-2"></i><?= __('config_tab_db_sybase_subtab_note') ?? 'Urus pemilihan runtime Sybase, mode operasi, dan ringkasan sambungan aktif dalam satu paparan.' ?>
                </div>
                <div class="row gx-3 gy-0 align-items-start">
                  <div class="col-lg-7">
                    <div class="row gy-0">
                      <div class="col-12">
                        <div class="card db-settings-card">
                      <div class="card-header db-settings-header-warning">
                        <div class="d-flex align-items-center">
                          <div class="db-settings-icon bg-warning bg-opacity-10 text-warning me-3">
                            <i class="ri-database-2-line fs-5"></i>
                          </div>
                          <div>
                            <h5 class="mb-1 fw-semibold text-warning"><?= __('config_tab_db_header') ?? 'Sybase Environment' ?></h5>
                            <small class="text-muted"><?= __('config_tab_db_header_sub') ?? 'Choose the active environment for the staff connection' ?></small>
                          </div>
                        </div>
                      </div>
                      <div class="card-body">
                        <div class="table-responsive db-settings-table dt-standard-shell">
                          <table class="table table-hover align-middle mb-0">
                          <thead class="table-light">
                            <tr>
                                <th class="text-center" style="width:50px">
                                  <i class="ri-radio-button-line text-muted"></i>
                                </th>
                                <th style="width:220px" class="fw-semibold"><?= __('config_tab_db_sybase_sambungan') ?? 'Environment' ?></th>
                                <th class="fw-semibold"><?= __('config_tab_db_sybase_keterangan') ?? 'Keterangan' ?></th>
                            </tr>
                          </thead>
                          <tbody>
                              <tr class="db-option-row <?= ($dbRenderEnvironment === 'production') ? 'table-primary is-selected' : '' ?>" data-db-radio="#sybase_environment_production">
                                <td class="text-center">
                                  <div class="form-check">
                                    <input class="db-radio" type="radio" name="sybase_environment" id="sybase_environment_production"
                                      value="production" <?= ($dbRenderEnvironment === 'production') ? 'checked="checked"' : '' ?>>
                                  </div>
                              </td>
                                <td>
                                  <label class="form-check-label fw-bold cursor-pointer" for="sybase_environment_production">
                                    <?= __('config_tab_db_environment_production') ?? 'Production' ?>
                                  </label>
                                </td>
                                <td>
                                  <span class="badge bg-success-subtle text-success me-2"><i class="ri-checkbox-circle-line"></i></span>
                                  <?= __('config_tab_db_environment_production_desc') ?? 'Use production Sybase staff connection for live system operations.' ?>
                                </td>
                            </tr>
                              <tr class="db-option-row <?= ($dbRenderEnvironment === 'development') ? 'table-primary is-selected' : '' ?>" data-db-radio="#sybase_environment_development">
                              <td class="text-center">
                                  <div class="form-check">
                                    <input class="db-radio" type="radio" name="sybase_environment" id="sybase_environment_development"
                                      value="development" <?= ($dbRenderEnvironment === 'development') ? 'checked="checked"' : '' ?>>
                                  </div>
                              </td>
                                <td>
                                  <label class="form-check-label fw-bold cursor-pointer" for="sybase_environment_development">
                                    <?= __('config_tab_db_environment_development') ?? 'Development' ?>
                                  </label>
                                </td>
                                <td>
                                  <span class="badge bg-info-subtle text-info me-2"><i class="ri-flask-line"></i></span>
                                  <?= __('config_tab_db_environment_development_desc') ?? 'Use development Sybase staff connection for testing and staging work.' ?>
                                </td>
                            </tr>
                          </tbody>
                        </table>
                        </div>
                      </div>
                        </div>
                      </div>

                      <div class="col-12">
                        <div class="card db-settings-card">
                      <div class="card-header db-settings-header-success">
                        <div class="d-flex align-items-center">
                          <div class="db-settings-icon bg-success bg-opacity-10 text-success me-3">
                            <i class="ri-server-line fs-5"></i>
                          </div>
                          <div>
                            <h5 class="mb-1 fw-semibold text-success"><?= __('config_tab_db_mode_header') ?? 'Operational Mode' ?></h5>
                            <small class="text-muted"><?= __('config_tab_db_mode_header_sub') ?? 'Choose which Sybase domains are enabled for the system' ?></small>
                          </div>
                        </div>
                      </div>
                      <div class="card-body">
                        <div class="table-responsive db-settings-table dt-standard-shell">
                          <table class="table table-hover align-middle mb-0">
                          <thead class="table-light">
                            <tr>
                                <th class="text-center" style="width:50px">
                                  <i class="ri-radio-button-line text-muted"></i>
                                </th>
                                <th style="width:220px" class="fw-semibold"><?= __('config_tab_db_mode_column') ?? 'Mode' ?></th>
                                <th class="fw-semibold"><?= __('config_tab_db_mode_desc_column') ?? 'Description' ?></th>
                            </tr>
                          </thead>
                          <tbody>
                            <tr class="db-option-row <?= ($dbRenderOperationalMode === 'staff_only') ? 'table-primary is-selected' : '' ?>" data-db-radio="#sybase_operational_mode_staff_only">
                                <td class="text-center">
                                  <div class="form-check">
                                      <input class="db-radio" type="radio" name="sybase_operational_mode" id="sybase_operational_mode_staff_only"
                                      value="staff_only" <?= ($dbRenderOperationalMode === 'staff_only') ? 'checked="checked"' : '' ?>>
                                  </div>
                                </td>
                                <td>
                                  <label class="form-check-label fw-bold cursor-pointer" for="sybase_operational_mode_staff_only">
                                    <?= __('config_tab_db_mode_staff_only') ?? 'Staff Only' ?>
                                  </label>
                                </td>
                                <td>
                                  <span class="badge bg-secondary-subtle text-secondary me-2"><i class="ri-user-line"></i></span>
                                  <?= __('config_tab_db_mode_staff_only_desc') ?? 'Only staff domain is used. Student connection remains disabled.' ?>
                                </td>
                            </tr>
                            <tr class="db-option-row <?= ($dbRenderOperationalMode === 'staff_student') ? 'table-primary is-selected' : '' ?>" data-db-radio="#sybase_operational_mode_staff_student">
                                <td class="text-center">
                                  <div class="form-check">
                                      <input class="db-radio" type="radio" name="sybase_operational_mode" id="sybase_operational_mode_staff_student"
                                      value="staff_student" <?= ($dbRenderOperationalMode === 'staff_student') ? 'checked="checked"' : '' ?>>
                                  </div>
                                </td>
                                <td>
                                  <label class="form-check-label fw-bold cursor-pointer" for="sybase_operational_mode_staff_student">
                                    <?= __('config_tab_db_mode_staff_student') ?? 'Staff + Student' ?>
                                  </label>
                                </td>
                                <td>
                                  <span class="badge bg-primary-subtle text-primary me-2"><i class="ri-links-line"></i></span>
                                  <?= __('config_tab_db_mode_staff_student_desc') ?? 'Staff domain stays active and student domain is also enabled for future transactions.' ?>
                                </td>
                            </tr>
                          </tbody>
                        </table>
                        </div>
                      </div>
                        </div>
                      </div>
                    </div>
                  </div>

                  <div class="col-lg-5">
                    <div class="card db-settings-card">
                      <div class="card-header db-settings-header-success">
                        <div class="d-flex align-items-center">
                          <div class="db-settings-icon bg-primary bg-opacity-10 text-primary me-3">
                            <i class="ri-links-line fs-5"></i>
                          </div>
                          <div>
                            <h5 class="mb-1 fw-semibold text-primary"><?= __('config_tab_db_runtime_header') ?? 'Current Runtime Summary' ?></h5>
                            <small class="text-muted"><?= __('config_tab_db_runtime_header_sub') ?? 'This summary shows how the current runtime will behave after the settings are saved.' ?></small>
                          </div>
                        </div>
                      </div>
                      <div class="card-body">
                        <div class="table-responsive db-settings-table dt-standard-shell">
                          <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                              <tr>
                                <th style="width:220px" class="fw-semibold"><?= __('config_tab_db_runtime_field') ?? 'Component' ?></th>
                                <th class="fw-semibold"><?= __('config_tab_db_runtime_value') ?? 'Runtime Value' ?></th>
                              </tr>
                            </thead>
                            <tbody>
                              <tr>
                                <td><strong><?= __('config_tab_db_runtime_staff') ?? 'Sybase Staff' ?></strong></td>
                                <td>
                                  <code class="text-primary" id="db-runtime-staff"><?= htmlspecialchars($runtimeStaffBase, ENT_QUOTES, 'UTF-8') ?></code>
                                </td>
                              </tr>
                              <tr>
                                <td><strong><?= __('config_tab_db_runtime_student') ?? 'Sybase Student' ?></strong></td>
                                <td id="db-runtime-student-cell">
                                  <?php if ($dbRenderOperationalMode === 'staff_student'): ?>
                                    <code class="text-primary" id="db-runtime-student"><?= htmlspecialchars($studentRuntimeLabel, ENT_QUOTES, 'UTF-8') ?></code>
                                  <?php else: ?>
                                    <span class="badge bg-secondary-subtle text-secondary" id="db-runtime-student"><?= htmlspecialchars($studentRuntimeLabel, ENT_QUOTES, 'UTF-8') ?></span>
                                  <?php endif; ?>
                                </td>
                              </tr>
                              <tr>
                                <td><strong><?= __('config_tab_db_runtime_environment') ?? 'Environment' ?></strong></td>
                                <td id="db-runtime-environment"><?= $dbRenderEnvironment === 'development' ? __('config_tab_db_environment_development') ?? 'Development' : __('config_tab_db_environment_production') ?? 'Production' ?></td>
                              </tr>
                              <tr>
                                <td><strong><?= __('config_tab_db_runtime_mode') ?? 'Operational Mode' ?></strong></td>
                                <td id="db-runtime-mode"><?= $dbRenderOperationalMode === 'staff_student' ? __('config_tab_db_mode_staff_student') ?? 'Staff + Student' : __('config_tab_db_mode_staff_only') ?? 'Staff Only' ?></td>
                              </tr>
                              <tr>
                                <td><strong><?= __('config_tab_db_mysql') ?? 'MySQL' ?></strong></td>
                                <td><?= __('config_tab_db_mysql_header') ?? 'This connection is always active for the main system.' ?></td>
                              </tr>
                            </tbody>
                          </table>
                        </div>
                      </div>
                    </div>
                  </div>

                    </div>
                  </div>

                  <div class="tab-pane fade general-subtab-pane" id="db-subtab-mysql" role="tabpanel" aria-labelledby="db-subtab-mysql-tab">
                    <div class="general-settings-note mb-3">
                      <i class="ri-server-line me-2"></i><?= __('config_tab_db_mysql_subtab_note') ?? 'Paparan ini menunjukkan sambungan MySQL utama dan pemilihan environment aktif untuk sistem.' ?>
                    </div>
                    <div class="row gx-3 gy-0 align-items-start">
                      <div class="col-lg-7">
                        <div class="card db-settings-card">
                          <div class="card-header db-settings-header-warning">
                            <div class="d-flex align-items-center">
                              <div class="db-settings-icon bg-warning bg-opacity-10 text-warning me-3">
                                <i class="ri-repeat-line fs-5"></i>
                              </div>
                              <div>
                                <h5 class="mb-1 fw-semibold text-warning"><?= __('config_tab_db_mysql_environment_header') ?? 'MySQL Main Environment' ?></h5>
                                <small class="text-muted"><?= __('config_tab_db_mysql_environment_sub') ?? 'Pilih environment aktif untuk sambungan MySQL utama sistem.' ?></small>
                              </div>
                            </div>
                          </div>
                          <div class="card-body">
                            <div class="table-responsive db-settings-table dt-standard-shell">
                              <table class="table table-hover align-middle mb-0">
                                <thead class="table-light">
                                  <tr>
                                    <th class="text-center" style="width:50px">
                                      <i class="ri-radio-button-line text-muted"></i>
                                    </th>
                                    <th style="width:220px" class="fw-semibold"><?= __('config_tab_db_mysql_sambungan') ?? 'Environment' ?></th>
                                    <th class="fw-semibold"><?= __('config_tab_db_mysql_keterangan') ?? 'Description' ?></th>
                                  </tr>
                                </thead>
                                <tbody>
                                  <tr class="db-option-row <?= ($mainMysqlEnvironment === 'production') ? 'table-primary is-selected' : '' ?>" data-db-radio="#main_db_environment_production">
                                    <td class="text-center">
                                      <div class="form-check">
                                        <input class="db-radio" type="radio" name="main_db_environment" id="main_db_environment_production"
                                          value="production" <?= ($mainMysqlEnvironment === 'production') ? 'checked="checked"' : '' ?>>
                                      </div>
                                    </td>
                                    <td>
                                      <label class="form-check-label fw-bold cursor-pointer" for="main_db_environment_production">
                                        <?= __('config_tab_db_environment_production') ?? 'Production' ?>
                                      </label>
                                    </td>
                                    <td>
                                      <span class="badge bg-success-subtle text-success me-2"><i class="ri-checkbox-circle-line"></i></span>
                                      <?= __('config_tab_db_mysql_environment_production_desc') ?? 'Gunakan MySQL utama production untuk operasi live sistem.' ?>
                                    </td>
                                  </tr>
                                  <tr class="db-option-row <?= ($mainMysqlEnvironment === 'development') ? 'table-primary is-selected' : '' ?>" data-db-radio="#main_db_environment_development">
                                    <td class="text-center">
                                      <div class="form-check">
                                        <input class="db-radio" type="radio" name="main_db_environment" id="main_db_environment_development"
                                          value="development" <?= ($mainMysqlEnvironment === 'development') ? 'checked="checked"' : '' ?>>
                                      </div>
                                    </td>
                                    <td>
                                      <label class="form-check-label fw-bold cursor-pointer" for="main_db_environment_development">
                                        <?= __('config_tab_db_environment_development') ?? 'Development' ?>
                                      </label>
                                    </td>
                                    <td>
                                      <span class="badge bg-info-subtle text-info me-2"><i class="ri-flask-line"></i></span>
                                      <?= __('config_tab_db_mysql_environment_development_desc') ?? 'Gunakan MySQL utama development untuk testing dan staging.' ?>
                                    </td>
                                  </tr>
                                </tbody>
                              </table>
                            </div>
                          </div>
                        </div>
                      </div>
                      <div class="col-lg-5">
                        <div class="card db-settings-card">
                          <div class="card-header db-settings-header-success">
                            <div class="d-flex align-items-center">
                              <div class="db-settings-icon bg-success bg-opacity-10 text-success me-3">
                                <i class="ri-server-line fs-5"></i>
                              </div>
                              <div>
                                <h5 class="mb-1 fw-semibold text-success"><?= __('config_tab_db_mysql') ?? 'MySQL (Always Active)' ?></h5>
                                <small class="text-muted"><?= __('config_tab_db_mysql_sub') ?? 'Always active connection' ?></small>
                              </div>
                            </div>
                          </div>
                          <div class="card-body">
                            <div class="db-settings-alert" style="border-color:rgba(16,185,129,.24);background:rgba(16,185,129,.06);">
                              <i class="ri-information-line me-2"></i><?= __('config_tab_db_mysql_header') ?? 'This connection is always active for the main system.' ?>
                            </div>
                            <div class="table-responsive db-settings-table dt-standard-shell">
                              <table class="table table-hover align-middle mb-0">
                                <thead class="table-light">
                                  <tr>
                                    <th style="width:220px" class="fw-semibold"><?= __('config_tab_db_mysql_sambungan') ?? 'Field' ?></th>
                                    <th class="fw-semibold"><?= __('config_tab_db_mysql_keterangan') ?? 'Information' ?></th>
                                  </tr>
                                </thead>
                                <tbody>
                                  <tr>
                                    <td><strong><?= __('config_tab_db_runtime_environment') ?? 'Environment' ?></strong></td>
                                    <td id="db-runtime-mysql-environment"><?= $mainMysqlEnvironment === 'development' ? __('config_tab_db_environment_development') ?? 'Development' : __('config_tab_db_environment_production') ?? 'Production' ?></td>
                                  </tr>
                                  <tr>
                                    <td><strong><?= __('config_tab_db_mysql_resolved_key') ?></strong></td>
                                    <td><code class="text-primary" id="db-runtime-mysql-resolved-key"><?= htmlspecialchars($mysqlActiveResolvedKey, ENT_QUOTES, 'UTF-8') ?></code></td>
                                  </tr>
                                  <tr>
                                    <td><strong><?= __('config_tab_db_mysql_driver') ?? 'Driver' ?></strong></td>
                                    <td><code class="text-primary" id="db-runtime-mysql-driver"><?= htmlspecialchars($mysqlDriver, ENT_QUOTES, 'UTF-8') ?></code></td>
                                  </tr>
                                  <tr>
                                    <td><strong><?= __('config_tab_db_mysql_host') ?? 'Host' ?></strong></td>
                                    <td id="db-runtime-mysql-host"><?= htmlspecialchars($mysqlHost, ENT_QUOTES, 'UTF-8') ?></td>
                                  </tr>
                                  <tr>
                                    <td><strong><?= __('config_tab_db_mysql_database') ?? 'Database' ?></strong></td>
                                    <td id="db-runtime-mysql-database"><?= htmlspecialchars($mysqlDatabase, ENT_QUOTES, 'UTF-8') ?></td>
                                  </tr>
                                  <tr>
                                    <td><strong><?= __('config_tab_db_mysql_user') ?? 'User' ?></strong></td>
                                    <td id="db-runtime-mysql-user"><?= htmlspecialchars($mysqlUser, ENT_QUOTES, 'UTF-8') ?></td>
                                  </tr>
                                  <tr>
                                    <td><strong><?= __('config_tab_db_mysql_production_target') ?></strong></td>
                                    <td id="db-runtime-mysql-prod-target"><?= htmlspecialchars($mysqlProdTargetText, ENT_QUOTES, 'UTF-8') ?><?= $mysqlProdDedicated ? ' <span class="badge bg-success-subtle text-success ms-1">' . h(__('config_tab_db_mysql_dedicated_env')) . '</span>' : ' <span class="badge bg-secondary-subtle text-secondary ms-1">' . h(__('config_tab_db_mysql_fallback')) . '</span>' ?></td>
                                  </tr>
                                  <tr>
                                    <td><strong><?= __('config_tab_db_mysql_development_target') ?></strong></td>
                                    <td id="db-runtime-mysql-dev-target"><?= htmlspecialchars($mysqlDevTargetText, ENT_QUOTES, 'UTF-8') ?><?= $mysqlDevDedicated ? ' <span class="badge bg-success-subtle text-success ms-1">' . h(__('config_tab_db_mysql_dedicated_env')) . '</span>' : ' <span class="badge bg-secondary-subtle text-secondary ms-1">' . h(__('config_tab_db_mysql_fallback')) . '</span>' ?></td>
                                  </tr>
                                  <tr>
                                    <td><strong><?= __('config_tab_db_mysql_diagnostic') ?></strong></td>
                                    <td id="db-runtime-mysql-diagnostic">
                                      <?php if ($mysqlSameTarget): ?>
                                        <span class="badge bg-warning-subtle text-warning"><i class="ri-alert-line me-1"></i><?= __('config_tab_db_mysql_same_target') ?></span>
                                      <?php else: ?>
                                        <span class="badge bg-success-subtle text-success"><i class="ri-checkbox-circle-line me-1"></i><?= __('config_tab_db_mysql_different_target') ?></span>
                                      <?php endif; ?>
                                    </td>
                                  </tr>
                                </tbody>
                              </table>
                            </div>
                          </div>
                        </div>
                      </div>
                    </div>
                  </div>

                  <div class="tab-pane fade general-subtab-pane" id="db-subtab-additional" role="tabpanel" aria-labelledby="db-subtab-additional-tab">
                    <div class="general-settings-note mb-3">
                      <i class="ri-stack-line me-2"></i><?= __('config_tab_db_additional_note') ?? 'Sambungan tambahan diurus berasingan untuk reporting, reference, integration, dan transaksi sokongan tanpa mengganggu 3 database utama sistem.' ?>
                    </div>
                    <div class="card db-settings-card">
                      <div class="card-header db-settings-header-success">
                        <div class="d-flex align-items-center justify-content-between flex-wrap gap-3">
                          <div class="d-flex align-items-center">
                            <div class="db-settings-icon bg-success bg-opacity-10 text-success me-3">
                              <i class="ri-database-2-line fs-5"></i>
                            </div>
                            <div>
                              <h5 class="mb-1 fw-semibold text-success"><?= __('config_tab_db_additional_header') ?? 'Additional Connections Registry' ?></h5>
                              <small class="text-muted"><?= __('config_tab_db_additional_sub') ?? 'Setiap connection di sini optional dan hanya digunakan oleh feature tertentu yang memerlukannya.' ?></small>
                            </div>
                          </div>
                          <div class="d-flex gap-2 flex-wrap db-additional-header-actions">
                            <button type="button" class="btn btn-primary" id="btn-db-additional-refresh" onclick="return window.__tetapanRefreshAdditionalConnections ? window.__tetapanRefreshAdditionalConnections(this) : false;">
                              <i class="ri-refresh-line me-1"></i> <?= __('config_tab_db_additional_refresh') ?? 'Refresh' ?>
                            </button>
                            <button type="button" class="btn btn-success" id="btn-db-additional-create" onclick="return window.__tetapanOpenAdditionalConnectionModal ? window.__tetapanOpenAdditionalConnectionModal() : false;">
                              <i class="ri-add-line me-1"></i> <?= __('config_tab_db_additional_add') ?? 'Add Connection' ?>
                            </button>
                          </div>
                        </div>
                      </div>
                      <div class="card-body">
                        <?php
                          $additionalDiagnosticWarnings = is_array($additionalDiagnostics['warnings'] ?? null)
                            ? $additionalDiagnostics['warnings']
                            : [];
                          $additionalDiagnosticStatus = (string)($additionalDiagnostics['status'] ?? 'healthy');
                          $additionalDiagnosticClass = $additionalDiagnosticStatus === 'attention'
                            ? 'danger'
                            : ($additionalDiagnosticStatus === 'warning' ? 'warning' : 'success');
                        ?>
                        <div class="alert alert-<?= h($additionalDiagnosticClass) ?> mb-3" id="db-additional-diagnostics" role="status">
                          <div class="d-flex justify-content-between align-items-start flex-wrap gap-2">
                            <div>
                              <div class="fw-semibold">
                                <i class="ri-pulse-line me-1"></i> <?= __('config_tab_db_additional_diagnostics_title') ?>
                              </div>
                              <div class="small mt-1" id="db-additional-diagnostics-summary">
                                Runtime <?= h(strtoupper((string)($additionalDiagnostics['runtime_os'] ?? PHP_OS_FAMILY))) ?> ·
                                PDO <?= h(implode(', ', (array)($additionalDiagnostics['available_drivers'] ?? [])) ?: 'none') ?> ·
                                <?= h(sprintf(__('config_tab_db_additional_diagnostics_enabled'), (string)($additionalDiagnostics['enabled_count'] ?? 0))) ?> ·
                                <?= h(sprintf(__('config_tab_db_additional_diagnostics_active_rows'), (string)($additionalDiagnostics['active_env_count'] ?? 0))) ?>
                              </div>
                            </div>
                            <span class="badge bg-<?= h($additionalDiagnosticClass) ?>" id="db-additional-diagnostics-count">
                              <?= h(sprintf(__('config_tab_db_additional_diagnostics_warning_count'), (string)($additionalDiagnostics['warning_count'] ?? 0))) ?>
                            </span>
                          </div>
                          <div class="small mt-2">
                            <?= __('config_tab_db_additional_diagnostics_note') ?>
                          </div>
                          <?php if ($additionalDiagnosticWarnings !== []): ?>
                            <ul class="small mb-0 mt-2 ps-3" id="db-additional-diagnostics-warnings">
                              <?php foreach (array_slice($additionalDiagnosticWarnings, 0, 8) as $diagnosticWarning): ?>
                                <li>
                                  <code><?= h((string)($diagnosticWarning['connection_code'] ?? '-')) ?></code>
                                  — <?= h((string)($diagnosticWarning['message'] ?? '')) ?>
                                </li>
                              <?php endforeach; ?>
                              <?php if (count($additionalDiagnosticWarnings) > 8): ?>
                                <li><?= h(sprintf(__('config_tab_db_additional_diagnostics_more_warnings'), (string)(count($additionalDiagnosticWarnings) - 8))) ?></li>
                              <?php endif; ?>
                            </ul>
                          <?php endif; ?>
                          <?php if ($additionalDiagnosticWarnings === []): ?>
                            <ul class="small mb-0 mt-2 ps-3 d-none" id="db-additional-diagnostics-warnings"></ul>
                          <?php endif; ?>
                        </div>
                        <div class="db-additional-toolbar">
                          <div class="row g-2">
                            <div class="col-md-4">
                              <input type="search" class="form-control" id="db-additional-search" placeholder="<?= __('config_tab_db_additional_search') ?? 'Cari code, nama, jenis, purpose...' ?>">
                            </div>
                            <div class="col-md-3">
                              <select class="form-select" id="db-additional-family-filter">
                                <option value=""><?= __('config_tab_db_additional_filter_all_types') ?? 'Semua jenis database' ?></option>
                                <option value="mysql">MySQL</option>
                                <option value="sybase">Sybase</option>
                                <option value="mssql">MSSQL</option>
                              </select>
                            </div>
                            <div class="col-md-3">
                              <select class="form-select" id="db-additional-status-filter">
                                <option value=""><?= __('config_tab_db_additional_filter_all_status') ?? 'Semua status' ?></option>
                                <option value="enabled"><?= __('config_tab_db_additional_enabled') ?? 'Enabled' ?></option>
                                <option value="disabled"><?= __('config_tab_db_additional_disabled') ?? 'Disabled' ?></option>
                              </select>
                            </div>
                            <div class="col-md-2">
                              <div class="db-additional-counter" id="db-additional-counter">0</div>
                            </div>
                          </div>
                        </div>

                        <div class="table-responsive db-settings-table dt-standard-shell">
                          <table class="table table-hover align-middle mb-0 db-additional-registry-table">
                            <colgroup>
                              <col class="db-additional-col-code">
                              <col class="db-additional-col-name">
                              <col class="db-additional-col-type">
                              <col class="db-additional-col-purpose">
                              <col class="db-additional-col-environment">
                              <col class="db-additional-col-status">
                              <col class="db-additional-col-last-test">
                              <col class="db-additional-col-actions">
                            </colgroup>
                            <thead class="table-light">
                              <tr>
                                <th><?= __('config_tab_db_additional_code') ?? 'Code' ?></th>
                                <th><?= __('config_tab_db_additional_name') ?? 'Name' ?></th>
                                <th><?= __('config_tab_db_additional_type') ?? 'Type' ?></th>
                                <th><?= __('config_tab_db_additional_purpose') ?? 'Purpose' ?></th>
                                <th><?= __('config_tab_db_additional_env') ?? 'Environment' ?></th>
                                <th><?= __('config_tab_db_additional_status') ?? 'Status' ?></th>
                                <th><?= __('config_tab_db_additional_last_test') ?? 'Last Test' ?></th>
                                <th class="text-start"><?= __('config_tab_db_additional_actions') ?? 'Actions' ?></th>
                              </tr>
                            </thead>
                            <tbody id="db-additional-table-body">
                              <tr>
                                <td colspan="8" class="text-center text-muted py-4"><?= __('config_tab_db_additional_loading') ?? 'Memuatkan senarai sambungan tambahan...' ?></td>
                              </tr>
                            </tbody>
                          </table>
                        </div>

                        <div class="db-additional-empty d-none" id="db-additional-empty">
                          <i class="ri-inbox-archive-line"></i>
                          <strong><?= __('config_tab_db_additional_empty_title') ?? 'Belum ada sambungan tambahan.' ?></strong>
                          <span><?= __('config_tab_db_additional_empty_text') ?? 'Tambah connection pertama untuk reporting, reference, atau integration.' ?></span>
                        </div>
                      </div>
                    </div>
                  </div>
                </div>
                  </div>
                </div>

                <div class="db-settings-actions d-flex justify-content-between align-items-center flex-wrap gap-2">
                  <div class="text-muted small">
                    <i class="ri-database-2-line me-1"></i> <?= __('config_tab_db_actions_note') ?? 'Pastikan pilihan Sybase diuji dan disahkan sebelum disimpan.' ?>
                  </div>
                  <button type="submit" class="btn btn-primary px-4" id="btn-simpan-db">
                    <i class="ri-save-3-line me-2"></i> <?= __('config_tab_db_simpan_tetapan_db') ?? 'Simpan Tetapan Pangkalan Data' ?>
                  </button>
                </div>
              </form>

              <div class="modal fade" id="db-additional-modal" tabindex="-1" aria-hidden="true" aria-labelledby="db-additional-modal-title">
                <div class="modal-dialog modal-xl modal-dialog-scrollable">
                  <div class="modal-content">
                    <div class="modal-header">
                      <div class="db-additional-modal-heading">
                        <div class="db-additional-modal-kicker">
                          <i class="ri-database-2-line me-2"></i><?= __('config_tab_db_additional_header') ?? 'Additional Connections Registry' ?>
                        </div>
                        <h5 class="modal-title" id="db-additional-modal-title"><?= __('config_tab_db_additional_modal_add') ?? 'Add Additional Connection' ?></h5>
                        <div class="db-additional-modal-subtitle"><?= __('config_tab_db_additional_modal_sub') ?? 'Perubahan di sini tidak akan mengubah main runtime MySQL dan Sybase sistem.' ?></div>
                      </div>
                      <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                      <ul class="nav nav-tabs db-additional-modal-tabs mb-3" id="db-additional-modal-tabs" role="tablist">
                        <li class="nav-item" role="presentation">
                          <button class="nav-link active" id="tab-additional-connection-tab" data-bs-toggle="tab" data-bs-target="#tab-additional-connection" type="button" role="tab" aria-controls="tab-additional-connection" aria-selected="true">
                            <i class="ri-add-line me-1"></i> <?= __('config_tab_db_additional_modal_add') ?? 'Add Additional Connection' ?>
                          </button>
                        </li>
                        <li class="nav-item" role="presentation">
                          <button class="nav-link" id="tab-runtime-summary-tab" data-bs-toggle="tab" data-bs-target="#tab-runtime-summary" type="button" role="tab" aria-controls="tab-runtime-summary" aria-selected="false">
                            <i class="ri-list-check-2 me-1"></i> <?= __('config_tab_db_runtime_header') ?? 'Current Runtime Summary' ?>
                          </button>
                        </li>
                        <li class="nav-item" role="presentation">
                          <button class="nav-link" id="tab-env-configs-tab" data-bs-toggle="tab" data-bs-target="#tab-env-configs" type="button" role="tab" aria-controls="tab-env-configs" aria-selected="false">
                            <i class="ri-settings-3-line me-1"></i> <?= __('config_tab_db_additional_env_configs') ?? 'Environment Configurations' ?>
                          </button>
                        </li>
                      </ul>

                      <form id="form-db-additional" novalidate>
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                        <input type="hidden" name="form_type" id="db-additional-form-type" value="db_additional_create">
                        <input type="hidden" name="existing_code" id="db-additional-existing-code" value="">
                        <input type="hidden" name="f_registry_revision" id="db-additional-registry-revision" value="">

                        <div class="tab-content" id="db-additional-modal-tabs-content">
                          <div class="tab-pane fade show active" id="tab-additional-connection" role="tabpanel" aria-labelledby="tab-additional-connection-tab">
                            <div class="db-additional-modal-note">
                              <i class="ri-shield-check-line me-2"></i><?= __('config_tab_db_additional_note') ?? 'Sambungan tambahan diurus berasingan untuk reporting, reference, integration, dan transaksi sokongan tanpa mengganggu 3 database utama sistem.' ?>
                            </div>

                            <div class="db-additional-form-section">
                              <div class="db-additional-form-section-title">
                                <i class="ri-settings-3-line"></i>
                                <span><?= __('config_tab_db_additional_modal_add') ?? 'Add Additional Connection' ?></span>
                              </div>
                              <div class="row g-3 mb-3">
                                <div class="col-md-4">
                                  <label class="form-label" for="db-additional-code"><?= __('config_tab_db_additional_code') ?? 'Code' ?></label>
                                  <input type="text" class="form-control" id="db-additional-code" name="f_code" placeholder="dbx_mysql_reporting">
                                </div>
                                <div class="col-md-4">
                                  <label class="form-label" for="db-additional-name"><?= __('config_tab_db_additional_name') ?? 'Name' ?></label>
                                  <input type="text" class="form-control" id="db-additional-name" name="f_name" placeholder="Reporting Database">
                                </div>
                                <div class="col-md-4">
                                  <label class="form-label" for="db-additional-purpose"><?= __('config_tab_db_additional_purpose') ?? 'Purpose' ?></label>
                                  <input type="text" class="form-control" id="db-additional-purpose" name="f_purpose" placeholder="reporting">
                                </div>
                                <div class="col-md-4">
                                  <label class="form-label" for="db-additional-family"><?= __('config_tab_db_additional_type') ?? 'Type' ?></label>
                                  <select class="form-select" id="db-additional-family" name="f_family">
                                    <option value="mysql">MySQL</option>
                                    <option value="sybase">Sybase</option>
                                    <option value="mssql">MSSQL</option>
                                  </select>
                                </div>
                                <div class="col-md-4">
                                  <label class="form-label" for="db-additional-driver-mode"><?= __('config_tab_db_additional_driver_mode') ?? 'Driver Mode' ?></label>
                                  <select class="form-select" id="db-additional-driver-mode" name="f_driver_mode">
                                    <option value="auto">Auto</option>
                                    <option value="dsn">DSN</option>
                                    <option value="dblib">DBLIB</option>
                                    <option value="odbc">ODBC</option>
                                    <option value="sqlsrv">SQLSRV</option>
                                  </select>
                                </div>
                                <div class="col-md-4">
                                  <label class="form-label" for="db-additional-notes"><?= __('config_tab_db_additional_notes') ?? 'Notes' ?></label>
                                  <input type="text" class="form-control" id="db-additional-notes" name="f_notes" placeholder="<?= __('config_tab_db_additional_notes_placeholder') ?? 'Optional notes for admin reference' ?>">
                                </div>
                              </div>
                            </div>
                          </div>

                          <div class="tab-pane fade" id="tab-runtime-summary" role="tabpanel" aria-labelledby="tab-runtime-summary-tab">
                            <div class="db-additional-form-section db-additional-form-section-last">
                              <div class="db-additional-form-section-title">
                                <i class="ri-list-check-2"></i>
                                <span><?= __('config_tab_db_runtime_header') ?? 'Current Runtime Summary' ?></span>
                              </div>
                              <div class="db-additional-info-card">
                                <div class="db-additional-info-item">
                                  <div class="db-additional-info-icon"><i class="ri-flashlight-line"></i></div>
                                  <div class="db-additional-info-content">
                                    <div class="db-additional-info-label"><?= __('config_tab_db_additional_enabled_default') ?? 'Connection enabled' ?></div>
                                    <div class="db-additional-info-value"><?= __('config_tab_db_additional_sub') ?? 'Setiap connection di sini optional dan hanya digunakan oleh feature tertentu yang memerlukannya.' ?></div>
                                  </div>
                                </div>
                                <div class="row g-3 mb-0">
                                  <div class="col-md-4">
                                    <div class="form-check form-switch db-additional-switch">
                                      <input class="form-check-input" type="checkbox" id="db-additional-enabled" name="f_is_enabled" checked>
                                      <label class="form-check-label" for="db-additional-enabled"><?= __('config_tab_db_additional_enabled_default') ?? 'Connection enabled' ?></label>
                                    </div>
                                  </div>
                                  <div class="col-md-4">
                                    <div class="form-check form-switch db-additional-switch">
                                      <input class="form-check-input" type="checkbox" id="db-additional-supports-prod" name="f_supports_prod" checked>
                                      <label class="form-check-label" for="db-additional-supports-prod"><?= __('config_tab_db_additional_supports_prod') ?? 'Supports production' ?></label>
                                    </div>
                                  </div>
                                  <div class="col-md-4">
                                    <div class="form-check form-switch db-additional-switch">
                                      <input class="form-check-input" type="checkbox" id="db-additional-supports-dev" name="f_supports_dev">
                                      <label class="form-check-label" for="db-additional-supports-dev"><?= __('config_tab_db_additional_supports_dev') ?? 'Supports development' ?></label>
                                    </div>
                                  </div>
                                </div>
                              </div>
                            </div>
                          </div>

                          <div class="tab-pane fade" id="tab-env-configs" role="tabpanel" aria-labelledby="tab-env-configs-tab">
                            <div class="db-additional-form-section db-additional-form-section-last">
                              <div class="db-additional-form-section-title">
                                <i class="ri-stack-line"></i>
                                <span><?= __('config_tab_db_additional_env_configs') ?? 'Environment Configurations' ?></span>
                              </div>
                              <div class="db-additional-env-section">
                                <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-3">
                                  <div>
                                    <small class="text-muted"><?= __('config_tab_db_additional_env_configs_sub') ?? 'Tambah satu atau lebih env row ikut driver dan OS yang diperlukan.' ?></small>
                                  </div>
                                  <div class="d-flex flex-wrap gap-2">
                                    <button type="button" class="btn btn-outline-primary btn-sm db-rounded-btn" id="btn-db-additional-preset-windows-odbc">
                                      <i class="ri-windows-line me-1"></i> Windows ODBC
                                    </button>
                                    <button type="button" class="btn btn-outline-primary btn-sm db-rounded-btn" id="btn-db-additional-preset-linux-dblib">
                                      <i class="ri-terminal-box-line me-1"></i> Linux DBLIB
                                    </button>
                                    <button type="button" class="btn btn-primary btn-sm db-rounded-btn" id="btn-db-additional-env-add">
                                      <i class="ri-add-line me-1"></i> <?= __('config_tab_db_additional_add_env_row') ?? 'Add Env Row' ?>
                                    </button>
                                  </div>
                                </div>
                                <div id="db-additional-env-rows" class="db-additional-env-rows"></div>
                              </div>
                            </div>
                          </div>
                        </div>
                      </form>
                    </div>
                    <div class="modal-footer">
                      <button type="button" class="btn btn-secondary db-rounded-btn" data-bs-dismiss="modal"><?= __('config_alert_no') ?? 'Close' ?></button>
                      <button type="button" class="btn btn-success db-rounded-btn" id="btn-db-additional-save" onclick="return window.__tetapanSaveAdditionalConnection ? window.__tetapanSaveAdditionalConnection(this) : false;">
                        <i class="ri-save-3-line me-1"></i> <?= __('config_tab_db_additional_save') ?? 'Save Connection' ?>
                      </button>
                    </div>
                  </div>
                </div>
              </div>

              <div class="modal fade" id="db-additional-view-modal" tabindex="-1" aria-hidden="true" aria-labelledby="db-additional-view-modal-title">
                <div class="modal-dialog modal-xl modal-dialog-scrollable">
                  <div class="modal-content">
                    <div class="modal-header db-additional-view-header">
                      <div class="db-additional-modal-heading">
                        <div class="db-additional-modal-kicker" id="db-additional-view-modal-kicker">
                          <i class="ri-database-2-line me-2"></i><?= __('config_tab_db_additional_header') ?? 'Additional Connections Registry' ?>
                        </div>
                        <h5 class="modal-title" id="db-additional-view-modal-title"><?= __('config_tab_db_additional_inspect_title') ?? 'Additional Connection Details' ?></h5>
                        <div class="db-additional-modal-subtitle" id="db-additional-view-modal-subtitle"><?= __('config_tab_db_additional_modal_sub') ?? 'Changes here will not alter the main MySQL and Sybase runtime.' ?></div>
                      </div>
                      <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                      <div id="db-additional-view-modal-body"></div>
                    </div>
                    <div class="modal-footer">
                      <button type="button" class="btn btn-secondary db-rounded-btn" data-bs-dismiss="modal"><?= __('config_alert_no') ?? 'Close' ?></button>
                    </div>
                  </div>
                </div>
              </div>

              <div class="modal fade" id="db-additional-child-view-modal" tabindex="-1" aria-hidden="true" aria-labelledby="db-additional-child-view-modal-title">
                <div class="modal-dialog modal-xl modal-dialog-scrollable">
                  <div class="modal-content">
                    <div class="modal-header db-additional-view-header">
                      <div class="db-additional-modal-heading">
                        <div class="db-additional-modal-kicker" id="db-additional-child-view-modal-kicker">
                          <i class="ri-file-search-line me-2"></i><?= __('config_tab_db_additional_data_preview_title') ?? 'Data Preview' ?>
                        </div>
                        <h5 class="modal-title" id="db-additional-child-view-modal-title"><?= __('config_tab_db_additional_data_preview_title') ?? 'Data Preview' ?></h5>
                        <div class="db-additional-modal-subtitle" id="db-additional-child-view-modal-subtitle"></div>
                      </div>
                      <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                      <div id="db-additional-child-view-modal-body"></div>
                    </div>
                    <div class="modal-footer">
                      <button type="button" class="btn btn-secondary db-rounded-btn" data-bs-dismiss="modal"><?= __('config_alert_no') ?? 'Close' ?></button>
                    </div>
                  </div>
                </div>
              </div>
            </div>
