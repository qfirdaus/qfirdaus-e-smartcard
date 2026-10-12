<?php
  $authValid = (bool)($authSettings['valid'] ?? false);
  $authWarnings = array_values(array_filter((array)($authSettings['warnings'] ?? []), static fn($item): bool => is_string($item) && trim($item) !== ''));
  $authErrors = array_values(array_filter((array)($authSettings['errors'] ?? []), static fn($item): bool => is_string($item) && trim($item) !== ''));
  $authMaintenance = !empty($authSettings['maintenance_mode']);
  $authCategories = (array)($authSettings['categories'] ?? []);
  $authSso = (array)($authSettings['sso'] ?? []);
  $authSsoEnabled = !empty($authSso['enabled']);
  $authSsoMode = (string)($authSso['mode'] ?? 'MANUAL');
  $authHybrid = (array)($authSso['hybrid'] ?? []);
  $authProvisioning = (array)($authSettings['provisioning'] ?? []);
  $authAutoProvisionStaff = !empty($authProvisioning['staf_sso_enabled']);
  $authAutoProvisionStudent = !empty($authProvisioning['pelajar_sso_enabled']);
  $authDefaultGroupStaffCode = (string)($authProvisioning['default_group_staff_code'] ?? 'ADM-STAF');
  $authDefaultGroupStudentCode = (string)($authProvisioning['default_group_student_code'] ?? 'ADM-STUDENT');
  $authPassword = (array)($authSettings['password'] ?? []);
  $authIntegration = (array)($authSettings['integration'] ?? []);
  $authSsoSiteId = (string)($authIntegration['site_id'] ?? '');
  $authSsoIdpDomain = (string)($authIntegration['idp_domain'] ?? '');
  $authPasswordMinLength = (int)($authPassword['min_length'] ?? 8);
  $authPasswordExpiryDays = (int)($authPassword['expiry_days'] ?? 90);
  $authPasswordHistoryCount = (int)($authPassword['history_count'] ?? 5);
  $authPasswordResetTokenMinutes = (int)($authPassword['reset_token_minutes'] ?? 30);
  $authPasswordRequireUppercase = !empty($authPassword['require_uppercase']);
  $authPasswordRequireLowercase = !empty($authPassword['require_lowercase']);
  $authPasswordRequireNumber = !empty($authPassword['require_number']);
  $authPasswordRequireSymbol = !empty($authPassword['require_symbol']);
  $authPasswordBlockLoginIdVariants = !empty($authPassword['block_loginid_variants']);
  $authLoginSecurity = (array)($authSettings['login_security'] ?? []);
  $authLoginMaxAttempts = (int)($authLoginSecurity['max_attempts'] ?? 3);
  $authLoginLockSeconds = (int)($authLoginSecurity['lock_seconds'] ?? 60);
  $authLoginIdentifierIpMaxAttempts = (int)($authLoginSecurity['identifier_ip_max_attempts'] ?? 5);
  $authLoginIdentifierIpLockSeconds = (int)($authLoginSecurity['identifier_ip_lock_seconds'] ?? 300);
  $authLoginIpMaxAttempts = (int)($authLoginSecurity['ip_max_attempts'] ?? 10);
  $authLoginIpLockSeconds = (int)($authLoginSecurity['ip_lock_seconds'] ?? 300);

  $authStatusLabel = !empty($authErrors)
    ? (__('config_auth_status_invalid') ?? 'Invalid')
    : (!empty($authWarnings)
        ? (__('config_auth_status_warning') ?? 'Valid with Warning')
        : (__('config_auth_status_valid') ?? 'Valid'));
  $authStatusClass = !empty($authErrors)
    ? 'danger'
    : (!empty($authWarnings) ? 'warning' : 'success');

  $effectiveSummary = [
    $authMaintenance
      ? (__('config_auth_summary_maintenance_on') ?? 'Maintenance mode is enabled. Only Super Admin can log in.')
      : (__('config_auth_summary_maintenance_off') ?? 'Maintenance mode is disabled. Normal policy evaluation applies.'),
    !empty($authCategories['staf'])
      ? (__('config_auth_summary_staff_enabled') ?? 'Staff login is enabled.')
      : (__('config_auth_summary_staff_disabled') ?? 'Staff login is disabled.'),
    !empty($authCategories['pelajar'])
      ? (__('config_auth_summary_student_enabled') ?? 'Student login is enabled.')
      : (__('config_auth_summary_student_disabled') ?? 'Student login is disabled.'),
    !empty($authCategories['umum'])
      ? (__('config_auth_summary_public_enabled') ?? 'Public login is enabled.')
      : (__('config_auth_summary_public_disabled') ?? 'Public login is disabled.'),
    $authSsoEnabled
      ? sprintf(
          __('config_auth_summary_sso_enabled') ?? 'SSO is enabled in %s mode.',
          $authSsoMode
        )
      : (__('config_auth_summary_sso_disabled') ?? 'SSO is disabled. All allowed categories use manual login.'),
    $authAutoProvisionStaff
      ? sprintf(
          __('config_auth_summary_staff_auto_provision_enabled') ?? 'Staff SSO auto provision is enabled with default group %s.',
          $authDefaultGroupStaffCode !== '' ? $authDefaultGroupStaffCode : 'ADM-STAF'
        )
      : (__('config_auth_summary_staff_auto_provision_disabled') ?? 'Staff SSO auto provision is disabled.'),
    $authAutoProvisionStudent
      ? sprintf(
          __('config_auth_summary_student_auto_provision_enabled') ?? 'Student SSO auto provision is enabled with default group %s.',
          $authDefaultGroupStudentCode !== '' ? $authDefaultGroupStudentCode : 'ADM-STUDENT'
        )
      : (__('config_auth_summary_student_auto_provision_disabled') ?? 'Student SSO auto provision is disabled.'),
  ];
?>

<div class="tab-pane fade <?= ($_GET['tab'] ?? '') === 'auth' ? 'show active' : '' ?>" id="auth-tab" role="tabpanel">
  <form method="POST"
        id="form-auth-aktif"
        action="<?= htmlspecialchars(url_with_param('tab', 'auth'), ENT_QUOTES, 'UTF-8') ?>"
        data-no-loader="1"
        novalidate
        onsubmit="return window.__tetapanAjaxSubmit(event, this, 'btn-simpan-auth', 'auth');">
    <input type="hidden" name="form_type" value="auth_settings">
    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">

    <div class="card auth-settings-card">
      <div class="card-header auth-settings-header-primary">
        <div class="d-flex align-items-center">
          <div class="auth-settings-icon bg-primary bg-opacity-10 text-primary me-3">
            <i class="ri-shield-keyhole-line fs-5"></i>
          </div>
          <div>
            <h5 class="mb-1 fw-semibold text-primary"><?= __('config_tab_auth') ?? 'Login Policy' ?></h5>
            <small class="text-muted"><?= __('config_tab_auth_intro') ?? 'Control who may log in and which authentication method is allowed for each user category.' ?></small>
          </div>
        </div>
      </div>
      <div class="card-body">
        <ul class="nav nav-pills auth-subtabs" role="tablist">
          <li class="nav-item" role="presentation">
            <button class="nav-link active"
                    data-bs-toggle="pill"
                    data-bs-target="#auth-subtab-overview"
                    type="button"
                    role="tab"
                    onclick="return window.__tetapanShowAuthSubtab('auth-subtab-overview', this, event);">
              <i class="ri-file-chart-line me-1"></i><?= __('config_auth_subtab_overview') ?? 'Policy Overview' ?>
            </button>
          </li>
          <li class="nav-item" role="presentation">
            <button class="nav-link"
                    data-bs-toggle="pill"
                    data-bs-target="#auth-subtab-global"
                    type="button"
                    role="tab"
                    onclick="return window.__tetapanShowAuthSubtab('auth-subtab-global', this, event);">
              <i class="ri-alarm-warning-line me-1"></i><?= __('config_auth_subtab_global') ?? 'Global Access' ?>
            </button>
          </li>
          <li class="nav-item" role="presentation">
            <button class="nav-link"
                    data-bs-toggle="pill"
                    data-bs-target="#auth-subtab-category"
                    type="button"
                    role="tab"
                    onclick="return window.__tetapanShowAuthSubtab('auth-subtab-category', this, event);">
              <i class="ri-group-line me-1"></i><?= __('config_auth_subtab_category') ?? 'Login Category Control' ?>
            </button>
          </li>
          <li class="nav-item" role="presentation">
            <button class="nav-link"
                    data-bs-toggle="pill"
                    data-bs-target="#auth-subtab-password"
                    type="button"
                    role="tab"
                    onclick="return window.__tetapanShowAuthSubtab('auth-subtab-password', this, event);">
              <i class="ri-lock-password-line me-1"></i><?= __('config_auth_subtab_password') ?? 'Password Policy' ?>
            </button>
          </li>
          <li class="nav-item" role="presentation">
            <button class="nav-link"
                    data-bs-toggle="pill"
                    data-bs-target="#auth-subtab-sso"
                    type="button"
                    role="tab"
                    onclick="return window.__tetapanShowAuthSubtab('auth-subtab-sso', this, event);">
              <i class="ri-links-line me-1"></i><?= __('config_auth_subtab_sso') ?? 'SSO Control' ?>
            </button>
          </li>
        </ul>

        <div class="tab-content">
          <div class="tab-pane fade show active auth-subtab-pane" id="auth-subtab-overview" role="tabpanel">
            <div class="auth-overview-hero mb-3">
              <div class="auth-overview-hero-icon">
                <i class="ri-shield-user-line"></i>
              </div>
              <div>
                <div class="fw-semibold mb-1"><?= __('config_auth_overview_title') ?? 'Policy Overview' ?></div>
                <div class="text-muted small"><?= __('config_auth_overview_sub') ?? 'Use this overview to review policy precedence and the evaluated runtime snapshot before saving changes.' ?></div>
              </div>
            </div>

            <div class="row g-3 align-items-stretch">
              <div class="col-xl-8">
                <div class="auth-summary-box auth-summary-box-main">
                  <div class="mb-3">
                    <div class="auth-summary-heading mb-3">
                      <div class="text-uppercase small fw-semibold text-muted mb-1"><?= __('config_auth_summary_effective') ?? 'Effective Summary' ?></div>
                      <div class="fw-semibold text-body-emphasis"><?= __('config_auth_intro_title') ?? 'Policy Evaluation Order' ?></div>
                      <div class="text-muted small mt-1"><?= __('config_auth_overview_sub') ?? 'Use this overview to review policy precedence and the evaluated runtime snapshot before saving changes.' ?></div>
                    </div>
                    <div class="auth-overview-order">
                      <div class="auth-overview-rule">
                        <div class="auth-overview-rule-number">1</div>
                        <div class="auth-overview-rule-copy"><?= __('config_auth_intro_point_maintenance') ?? 'Maintenance mode overrides normal login access for all non-Super Admin users.' ?></div>
                      </div>
                      <div class="auth-overview-rule">
                        <div class="auth-overview-rule-number">2</div>
                        <div class="auth-overview-rule-copy"><?= __('config_auth_intro_point_category') ?? 'Category control decides whether Staff, Student, and Public users may log in.' ?></div>
                      </div>
                      <div class="auth-overview-rule">
                        <div class="auth-overview-rule-number">3</div>
                        <div class="auth-overview-rule-copy"><?= __('config_auth_intro_point_sso') ?? 'SSO settings decide the authentication method only after access is allowed.' ?></div>
                      </div>
                    </div>
                  </div>
                  <ul class="auth-summary-list mb-0" id="auth-summary-effective-list">
                    <?php foreach ($effectiveSummary as $summaryLine): ?>
                      <li><?= htmlspecialchars((string)$summaryLine, ENT_QUOTES, 'UTF-8') ?></li>
                    <?php endforeach; ?>
                  </ul>
                </div>
              </div>
              <div class="col-xl-4">
                <div class="auth-overview-stack">
                  <div class="auth-summary-box">
                    <div class="text-uppercase small fw-semibold text-muted mb-2"><?= __('config_auth_summary_status') ?? 'Configuration Status' ?></div>
                    <div class="d-flex align-items-center gap-2 flex-wrap">
                      <span id="auth-summary-status-badge" class="badge bg-<?= $authStatusClass ?>-subtle text-<?= $authStatusClass ?> px-3 py-2"><?= htmlspecialchars($authStatusLabel, ENT_QUOTES, 'UTF-8') ?></span>
                      <?php if ($authValid): ?>
                        <span id="auth-summary-status-text" class="text-success small fw-semibold"><?= __('config_auth_summary_status_ok') ?? 'Policy snapshot is ready for runtime use.' ?></span>
                      <?php else: ?>
                        <span id="auth-summary-status-text" class="text-danger small fw-semibold"><?= __('config_auth_summary_status_invalid_note') ?? 'Configuration must be corrected before runtime enforcement is enabled.' ?></span>
                      <?php endif; ?>
                    </div>
                  </div>
                  <div class="auth-summary-box auth-summary-box-integration">
                    <div class="text-uppercase small fw-semibold text-muted mb-3"><?= __('config_auth_subtab_sso') ?? 'SSO Control' ?></div>
                    <div class="auth-integration-list">
                      <div class="auth-integration-item">
                        <div class="auth-integration-label"><?= __('config_auth_sso_site_id') ?? 'OneID Site ID' ?></div>
                        <div class="auth-integration-value" id="auth-summary-site-id">
                          <?= htmlspecialchars($authSsoSiteId !== '' ? $authSsoSiteId : (__('config_auth_summary_not_configured') ?? 'Not configured'), ENT_QUOTES, 'UTF-8') ?>
                        </div>
                      </div>
                      <div class="auth-integration-item">
                        <div class="auth-integration-label"><?= __('config_auth_sso_idp_domain') ?? 'OneID IdP Domain' ?></div>
                        <div class="auth-integration-value text-break" id="auth-summary-idp-domain">
                          <?= htmlspecialchars($authSsoIdpDomain !== '' ? $authSsoIdpDomain : (__('config_auth_summary_not_configured') ?? 'Not configured'), ENT_QUOTES, 'UTF-8') ?>
                        </div>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            </div>

            <?php if (!empty($authWarnings)): ?>
              <div class="auth-summary-box auth-summary-box-warning mt-3" id="auth-summary-warning-box">
                <div class="text-uppercase small fw-semibold text-warning-emphasis mb-2"><?= __('config_auth_summary_warnings') ?? 'Warnings' ?></div>
                <ul class="auth-summary-list mb-0" id="auth-summary-warning-list">
                  <?php foreach ($authWarnings as $warning): ?>
                    <li><?= htmlspecialchars($warning, ENT_QUOTES, 'UTF-8') ?></li>
                  <?php endforeach; ?>
                </ul>
              </div>
            <?php else: ?>
              <div class="auth-summary-box auth-summary-box-warning mt-3 d-none" id="auth-summary-warning-box">
                <div class="text-uppercase small fw-semibold text-warning-emphasis mb-2"><?= __('config_auth_summary_warnings') ?? 'Warnings' ?></div>
                <ul class="auth-summary-list mb-0" id="auth-summary-warning-list"></ul>
              </div>
            <?php endif; ?>

            <?php if (!empty($authErrors)): ?>
              <div class="auth-summary-box auth-summary-box-error mt-3">
                <div class="text-uppercase small fw-semibold text-danger mb-2"><?= __('config_auth_summary_errors') ?? 'Errors' ?></div>
                <ul class="auth-summary-list mb-0">
                  <?php foreach ($authErrors as $errorMessage): ?>
                    <li><?= htmlspecialchars($errorMessage, ENT_QUOTES, 'UTF-8') ?></li>
                  <?php endforeach; ?>
                </ul>
              </div>
            <?php endif; ?>
          </div>

          <div class="tab-pane fade auth-subtab-pane" id="auth-subtab-global" role="tabpanel">
            <div class="auth-setting-row">
              <div class="auth-setting-copy">
                <label class="form-label fw-semibold mb-1" for="auth_maintenance_mode"><?= __('config_auth_maintenance_mode') ?? 'Maintenance Mode' ?></label>
                <div class="text-muted small"><?= __('config_auth_maintenance_mode_help') ?? 'When enabled, only Super Admin can log in.' ?></div>
              </div>
              <div class="auth-setting-control">
                <div class="form-check form-switch auth-switch mb-2">
                  <input class="form-check-input" type="checkbox" role="switch" id="auth_maintenance_mode" name="auth_maintenance_mode" value="1" onchange="if(window.__tetapanSyncAuthPolicyUi){window.__tetapanSyncAuthPolicyUi();}else if(window.__tetapanRefreshAuthPolicySummary){window.__tetapanRefreshAuthPolicySummary();}" <?= $authMaintenance ? 'checked' : '' ?>>
                </div>
                <span id="auth-maintenance-state" class="badge bg-<?= $authMaintenance ? 'danger' : 'secondary' ?>-subtle text-<?= $authMaintenance ? 'danger' : 'secondary' ?>">
                  <?= $authMaintenance ? (__('config_auth_enabled') ?? 'Enabled') : (__('config_auth_disabled') ?? 'Disabled') ?>
                </span>
              </div>
            </div>
          </div>

          <div class="tab-pane fade auth-subtab-pane" id="auth-subtab-category" role="tabpanel">
            <?php
              $categoryRows = [
                'staf' => [
                  'id' => 'auth_login_enable_staf',
                  'label' => __('config_auth_login_enable_staf') ?? 'Enable Staff Login',
                  'help' => __('config_auth_login_enable_staf_help') ?? 'Allow users in the Staff category to log in.',
                  'enabled' => !empty($authCategories['staf']),
                ],
                'pelajar' => [
                  'id' => 'auth_login_enable_pelajar',
                  'label' => __('config_auth_login_enable_pelajar') ?? 'Enable Student Login',
                  'help' => __('config_auth_login_enable_pelajar_help') ?? 'Allow users in the Student category to log in.',
                  'enabled' => !empty($authCategories['pelajar']),
                ],
                'umum' => [
                  'id' => 'auth_login_enable_umum',
                  'label' => __('config_auth_login_enable_umum') ?? 'Enable Public Login',
                  'help' => __('config_auth_login_enable_umum_help') ?? 'Allow users in the Public category to log in.',
                  'enabled' => !empty($authCategories['umum']),
                ],
              ];
            ?>
            <div class="auth-setting-list">
              <?php foreach ($categoryRows as $row): ?>
                <div class="auth-setting-row">
                  <div class="auth-setting-copy">
                    <label class="form-label fw-semibold mb-1" for="<?= htmlspecialchars($row['id'], ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($row['label'], ENT_QUOTES, 'UTF-8') ?></label>
                    <div class="text-muted small"><?= htmlspecialchars($row['help'], ENT_QUOTES, 'UTF-8') ?></div>
                  </div>
                  <div class="auth-setting-control">
                    <div class="form-check form-switch auth-switch mb-2">
                      <input class="form-check-input" type="checkbox" role="switch" id="<?= htmlspecialchars($row['id'], ENT_QUOTES, 'UTF-8') ?>" name="<?= htmlspecialchars($row['id'], ENT_QUOTES, 'UTF-8') ?>" value="1" onchange="if(window.__tetapanSyncAuthPolicyUi){window.__tetapanSyncAuthPolicyUi();}else if(window.__tetapanRefreshAuthPolicySummary){window.__tetapanRefreshAuthPolicySummary();}" <?= !empty($row['enabled']) ? 'checked' : '' ?>>
                    </div>
                    <span id="auth-category-state-<?= htmlspecialchars((string)$row['id'], ENT_QUOTES, 'UTF-8') ?>" class="badge bg-<?= !empty($row['enabled']) ? 'success' : 'secondary' ?>-subtle text-<?= !empty($row['enabled']) ? 'success' : 'secondary' ?>">
                      <?= !empty($row['enabled']) ? (__('config_auth_allowed') ?? 'Allowed') : (__('config_auth_blocked') ?? 'Blocked') ?>
                    </span>
                  </div>
                </div>
              <?php endforeach; ?>
            </div>
            <div class="auth-settings-inline-note">
              <i class="ri-information-line me-1"></i><?= __('config_auth_category_note') ?? 'If all categories are disabled, only Super Admin remains able to log in.' ?>
            </div>
          </div>

          <div class="tab-pane fade auth-subtab-pane" id="auth-subtab-password" role="tabpanel">
            <div class="auth-password-workspace">
              <div class="auth-password-nav" role="tablist" aria-orientation="vertical">
                <button class="auth-password-nav-link active" type="button" data-bs-toggle="pill" data-bs-target="#auth-password-section-core" role="tab" aria-selected="true">
                  <span class="auth-password-nav-icon"><i class="ri-key-2-line"></i></span>
                  <span class="auth-password-nav-title"><?= __('config_auth_password_policy_core') ?? 'Password Core Policy' ?></span>
                </button>
                <button class="auth-password-nav-link" type="button" data-bs-toggle="pill" data-bs-target="#auth-password-section-complexity" role="tab" aria-selected="false">
                  <span class="auth-password-nav-icon"><i class="ri-shield-check-line"></i></span>
                  <span class="auth-password-nav-title"><?= __('config_auth_password_complexity') ?? 'Password Complexity Rules' ?></span>
                </button>
                <button class="auth-password-nav-link" type="button" data-bs-toggle="pill" data-bs-target="#auth-password-section-security" role="tab" aria-selected="false">
                  <span class="auth-password-nav-icon"><i class="ri-shield-user-line"></i></span>
                  <span class="auth-password-nav-title"><?= __('config_auth_login_security') ?? 'Login Security Guardrails' ?></span>
                </button>
              </div>

              <div class="tab-content auth-password-content">
                <div class="tab-pane fade show active" id="auth-password-section-core" role="tabpanel">

            <div class="auth-password-section-head mb-3">
              <div>
                <div class="auth-password-section-title"><?= __('config_auth_password_policy_core') ?? 'Password Core Policy' ?></div>
                <div class="text-muted small"><?= __('config_auth_password_policy_core_help') ?? 'Centralize the baseline password policy used by reset and change-password flows.' ?></div>
              </div>
            </div>

            <div class="auth-hybrid-block auth-password-panel mt-0">
              <div class="auth-security-stack">
                <div class="auth-security-group">
                  <div class="auth-security-group-head">
                    <div class="auth-security-group-title">Credential Lifecycle</div>
                    <div class="text-muted small">Define the baseline length and refresh cycle used for password creation and renewal.</div>
                  </div>
                  <div class="row g-3 auth-password-policy-grid">
                    <div class="col-md-6">
                      <div class="auth-form-group h-100">
                        <label class="form-label fw-semibold" for="auth_password_min_length"><?= __('config_auth_password_min_length') ?? 'Minimum Password Length' ?></label>
                        <div class="text-muted small mb-2 auth-password-help"><?= __('config_auth_password_min_length_help') ?? 'Minimum number of characters required for a valid password.' ?></div>
                        <input type="number" class="form-control" id="auth_password_min_length" name="auth_password_min_length" min="8" max="128" step="1" value="<?= htmlspecialchars((string)$authPasswordMinLength, ENT_QUOTES, 'UTF-8') ?>">
                      </div>
                    </div>
                    <div class="col-md-6">
                      <div class="auth-form-group h-100">
                        <label class="form-label fw-semibold" for="auth_password_expiry_days"><?= __('config_auth_password_expiry_days') ?? 'Password Expiry (Days)' ?></label>
                        <div class="text-muted small mb-2 auth-password-help"><?= __('config_auth_password_expiry_days_help') ?? 'Number of days before a password expires and must be updated.' ?></div>
                        <input type="number" class="form-control" id="auth_password_expiry_days" name="auth_password_expiry_days" min="1" max="365" step="1" value="<?= htmlspecialchars((string)$authPasswordExpiryDays, ENT_QUOTES, 'UTF-8') ?>">
                      </div>
                    </div>
                  </div>
                </div>

                <div class="auth-security-group">
                  <div class="auth-security-group-head">
                    <div class="auth-security-group-title">Recovery and Reuse Control</div>
                    <div class="text-muted small">Set how long reset links stay valid and how many previous passwords remain blocked from reuse.</div>
                  </div>
                  <div class="row g-3 auth-password-policy-grid">
                    <div class="col-md-6">
                      <div class="auth-form-group h-100">
                        <label class="form-label fw-semibold" for="auth_password_history_count"><?= __('config_auth_password_history_count') ?? 'Password History Count' ?></label>
                        <div class="text-muted small mb-2 auth-password-help"><?= __('config_auth_password_history_count_help') ?? 'Number of previous passwords that cannot be reused.' ?></div>
                        <input type="number" class="form-control" id="auth_password_history_count" name="auth_password_history_count" min="0" max="24" step="1" value="<?= htmlspecialchars((string)$authPasswordHistoryCount, ENT_QUOTES, 'UTF-8') ?>">
                      </div>
                    </div>
                    <div class="col-md-6">
                      <div class="auth-form-group h-100">
                        <label class="form-label fw-semibold" for="auth_password_reset_token_minutes"><?= __('config_auth_password_reset_token_minutes') ?? 'Reset Link Expiry (Minutes)' ?></label>
                        <div class="text-muted small mb-2 auth-password-help"><?= __('config_auth_password_reset_token_minutes_help') ?? 'Maximum lifetime of a password reset link before it becomes invalid.' ?></div>
                        <input type="number" class="form-control" id="auth_password_reset_token_minutes" name="auth_password_reset_token_minutes" min="5" max="180" step="1" value="<?= htmlspecialchars((string)$authPasswordResetTokenMinutes, ENT_QUOTES, 'UTF-8') ?>">
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            </div>

                </div>

                <div class="tab-pane fade" id="auth-password-section-complexity" role="tabpanel">

            <div class="auth-hybrid-block auth-password-panel mt-0">
              <div class="auth-policy-switch-grid">
                <div class="auth-policy-switch-card">
                  <div class="auth-policy-switch-head">
                    <div>
                      <div class="auth-policy-switch-kicker">Character Rule</div>
                      <label class="form-label fw-semibold mb-1" for="auth_password_require_uppercase"><?= __('config_auth_password_require_uppercase') ?? 'Require Uppercase Letter' ?></label>
                    </div>
                    <div class="form-check form-switch auth-switch mb-0">
                      <input class="form-check-input" type="checkbox" role="switch" id="auth_password_require_uppercase" name="auth_password_require_uppercase" value="1" <?= $authPasswordRequireUppercase ? 'checked' : '' ?>>
                    </div>
                  </div>
                  <div class="text-muted small auth-policy-switch-copy"><?= __('config_auth_password_require_uppercase_help') ?? 'Require at least one uppercase letter in every new password.' ?></div>
                </div>
                <div class="auth-policy-switch-card">
                  <div class="auth-policy-switch-head">
                    <div>
                      <div class="auth-policy-switch-kicker">Character Rule</div>
                      <label class="form-label fw-semibold mb-1" for="auth_password_require_lowercase"><?= __('config_auth_password_require_lowercase') ?? 'Require Lowercase Letter' ?></label>
                    </div>
                    <div class="form-check form-switch auth-switch mb-0">
                      <input class="form-check-input" type="checkbox" role="switch" id="auth_password_require_lowercase" name="auth_password_require_lowercase" value="1" <?= $authPasswordRequireLowercase ? 'checked' : '' ?>>
                    </div>
                  </div>
                  <div class="text-muted small auth-policy-switch-copy"><?= __('config_auth_password_require_lowercase_help') ?? 'Require at least one lowercase letter in every new password.' ?></div>
                </div>
                <div class="auth-policy-switch-card">
                  <div class="auth-policy-switch-head">
                    <div>
                      <div class="auth-policy-switch-kicker">Character Rule</div>
                      <label class="form-label fw-semibold mb-1" for="auth_password_require_number"><?= __('config_auth_password_require_number') ?? 'Require Number' ?></label>
                    </div>
                    <div class="form-check form-switch auth-switch mb-0">
                      <input class="form-check-input" type="checkbox" role="switch" id="auth_password_require_number" name="auth_password_require_number" value="1" <?= $authPasswordRequireNumber ? 'checked' : '' ?>>
                    </div>
                  </div>
                  <div class="text-muted small auth-policy-switch-copy"><?= __('config_auth_password_require_number_help') ?? 'Require at least one numeric digit in every new password.' ?></div>
                </div>
                <div class="auth-policy-switch-card">
                  <div class="auth-policy-switch-head">
                    <div>
                      <div class="auth-policy-switch-kicker">Character Rule</div>
                      <label class="form-label fw-semibold mb-1" for="auth_password_require_symbol"><?= __('config_auth_password_require_symbol') ?? 'Require Symbol' ?></label>
                    </div>
                    <div class="form-check form-switch auth-switch mb-0">
                      <input class="form-check-input" type="checkbox" role="switch" id="auth_password_require_symbol" name="auth_password_require_symbol" value="1" <?= $authPasswordRequireSymbol ? 'checked' : '' ?>>
                    </div>
                  </div>
                  <div class="text-muted small auth-policy-switch-copy"><?= __('config_auth_password_require_symbol_help') ?? 'Require at least one symbol such as ! @ # or % in every new password.' ?></div>
                </div>
                <div class="auth-policy-switch-card auth-policy-switch-card-wide">
                  <div class="auth-policy-switch-head">
                    <div>
                      <div class="auth-policy-switch-kicker">Identity Protection</div>
                      <label class="form-label fw-semibold mb-1" for="auth_password_block_loginid_variants"><?= __('config_auth_password_block_loginid_variants') ?? 'Block Login ID Variants' ?></label>
                    </div>
                    <div class="form-check form-switch auth-switch mb-0">
                      <input class="form-check-input" type="checkbox" role="switch" id="auth_password_block_loginid_variants" name="auth_password_block_loginid_variants" value="1" <?= $authPasswordBlockLoginIdVariants ? 'checked' : '' ?>>
                    </div>
                  </div>
                  <div class="text-muted small auth-policy-switch-copy"><?= __('config_auth_password_block_loginid_variants_help') ?? 'Reject passwords that contain the Login ID or close normalized variants of it.' ?></div>
                </div>
              </div>
            </div>

                </div>

                <div class="tab-pane fade" id="auth-password-section-security" role="tabpanel">

            <div class="auth-hybrid-block auth-password-panel mt-0">
              <div class="auth-security-stack">
                <div class="auth-security-group">
                  <div class="auth-security-group-head">
                    <div class="auth-security-group-title">Identifier Lockout</div>
                    <div class="text-muted small">Primary lockout that follows the Login ID regardless of browser or device.</div>
                  </div>
                  <div class="row g-3 auth-password-policy-grid">
                    <div class="col-md-6">
                      <div class="auth-form-group h-100">
                        <label class="form-label fw-semibold" for="auth_login_max_attempts"><?= __('config_auth_login_max_attempts') ?? 'Maximum Failed Attempts' ?></label>
                        <div class="text-muted small mb-2 auth-password-help"><?= __('config_auth_login_max_attempts_help') ?? 'Number of failed manual-login attempts allowed before the identifier is locked.' ?></div>
                        <input type="number" class="form-control" id="auth_login_max_attempts" name="auth_login_max_attempts" min="1" max="10" step="1" value="<?= htmlspecialchars((string)$authLoginMaxAttempts, ENT_QUOTES, 'UTF-8') ?>">
                      </div>
                    </div>
                    <div class="col-md-6">
                      <div class="auth-form-group h-100">
                        <label class="form-label fw-semibold" for="auth_login_lock_seconds"><?= __('config_auth_login_lock_seconds') ?? 'Lockout Duration (Seconds)' ?></label>
                        <div class="text-muted small mb-2 auth-password-help"><?= __('config_auth_login_lock_seconds_help') ?? 'How long the manual-login lockout remains active after the maximum failed attempts is reached.' ?></div>
                        <input type="number" class="form-control" id="auth_login_lock_seconds" name="auth_login_lock_seconds" min="30" max="3600" step="1" value="<?= htmlspecialchars((string)$authLoginLockSeconds, ENT_QUOTES, 'UTF-8') ?>">
                      </div>
                    </div>
                  </div>
                </div>

                <div class="auth-security-group">
                  <div class="auth-security-group-head">
                    <div class="auth-security-group-title">Identifier + IP Guardrail</div>
                    <div class="text-muted small">Throttle repeated failures for the same Login ID coming from the same network address.</div>
                  </div>
                  <div class="row g-3 auth-password-policy-grid">
                    <div class="col-md-6">
                      <div class="auth-form-group h-100">
                        <label class="form-label fw-semibold" for="auth_login_identifier_ip_max_attempts"><?= __('config_auth_login_identifier_ip_max_attempts') ?? 'Login ID + IP Failed Attempts' ?></label>
                        <div class="text-muted small mb-2 auth-password-help"><?= __('config_auth_login_identifier_ip_max_attempts_help') ?? 'Maximum failed attempts allowed for the same Login ID from the same IP before that pair is throttled.' ?></div>
                        <input type="number" class="form-control" id="auth_login_identifier_ip_max_attempts" name="auth_login_identifier_ip_max_attempts" min="1" max="20" step="1" value="<?= htmlspecialchars((string)$authLoginIdentifierIpMaxAttempts, ENT_QUOTES, 'UTF-8') ?>">
                      </div>
                    </div>
                    <div class="col-md-6">
                      <div class="auth-form-group h-100">
                        <label class="form-label fw-semibold" for="auth_login_identifier_ip_lock_seconds"><?= __('config_auth_login_identifier_ip_lock_seconds') ?? 'Login ID + IP Lockout Duration (Seconds)' ?></label>
                        <div class="text-muted small mb-2 auth-password-help"><?= __('config_auth_login_identifier_ip_lock_seconds_help') ?? 'How long the Login ID and IP pair remains throttled after hitting its failed-attempt limit.' ?></div>
                        <input type="number" class="form-control" id="auth_login_identifier_ip_lock_seconds" name="auth_login_identifier_ip_lock_seconds" min="30" max="3600" step="1" value="<?= htmlspecialchars((string)$authLoginIdentifierIpLockSeconds, ENT_QUOTES, 'UTF-8') ?>">
                      </div>
                    </div>
                  </div>
                </div>

                <div class="auth-security-group">
                  <div class="auth-security-group-head">
                    <div class="auth-security-group-title">Network Guardrail</div>
                    <div class="text-muted small">Throttle abusive behaviour from the same IP across multiple Login IDs.</div>
                  </div>
                  <div class="row g-3 auth-password-policy-grid">
                    <div class="col-md-6">
                      <div class="auth-form-group h-100">
                        <label class="form-label fw-semibold" for="auth_login_ip_max_attempts"><?= __('config_auth_login_ip_max_attempts') ?? 'IP Failed Attempts' ?></label>
                        <div class="text-muted small mb-2 auth-password-help"><?= __('config_auth_login_ip_max_attempts_help') ?? 'Maximum failed attempts allowed from the same IP across accounts before that IP is throttled.' ?></div>
                        <input type="number" class="form-control" id="auth_login_ip_max_attempts" name="auth_login_ip_max_attempts" min="1" max="50" step="1" value="<?= htmlspecialchars((string)$authLoginIpMaxAttempts, ENT_QUOTES, 'UTF-8') ?>">
                      </div>
                    </div>
                    <div class="col-md-6">
                      <div class="auth-form-group h-100">
                        <label class="form-label fw-semibold" for="auth_login_ip_lock_seconds"><?= __('config_auth_login_ip_lock_seconds') ?? 'IP Lockout Duration (Seconds)' ?></label>
                        <div class="text-muted small mb-2 auth-password-help"><?= __('config_auth_login_ip_lock_seconds_help') ?? 'How long the IP remains throttled after hitting its failed-attempt limit.' ?></div>
                        <input type="number" class="form-control" id="auth_login_ip_lock_seconds" name="auth_login_ip_lock_seconds" min="30" max="3600" step="1" value="<?= htmlspecialchars((string)$authLoginIpLockSeconds, ENT_QUOTES, 'UTF-8') ?>">
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            </div>

                </div>
              </div>
            </div>
          </div>

          <div class="tab-pane fade auth-subtab-pane" id="auth-subtab-sso" role="tabpanel">
            <div class="auth-password-workspace auth-sso-workspace">
              <div class="nav nav-pills auth-password-nav auth-sso-nav" role="tablist" aria-orientation="vertical">
                <button class="auth-password-nav-link active" type="button" data-bs-toggle="pill" data-bs-target="#auth-sso-section-access" role="tab" aria-selected="true">
                  <span class="auth-password-nav-icon"><i class="ri-links-line"></i></span>
                  <span class="auth-password-nav-title">SSO Access</span>
                </button>
                <button class="auth-password-nav-link" type="button" data-bs-toggle="pill" data-bs-target="#auth-sso-section-auto-provision" role="tab" aria-selected="false">
                  <span class="auth-password-nav-icon"><i class="ri-user-add-line"></i></span>
                  <span class="auth-password-nav-title"><?= __('config_auth_auto_provision_title') ?? 'SSO Auto Provisioning' ?></span>
                </button>
                <button class="auth-password-nav-link" type="button" data-bs-toggle="pill" data-bs-target="#auth-sso-section-provider" role="tab" aria-selected="false">
                  <span class="auth-password-nav-icon"><i class="ri-server-line"></i></span>
                  <span class="auth-password-nav-title">Identity Provider</span>
                </button>
                <button class="auth-password-nav-link" type="button" data-bs-toggle="pill" data-bs-target="#auth-sso-section-mapping" role="tab" aria-selected="false">
                  <span class="auth-password-nav-icon"><i class="ri-git-branch-line"></i></span>
                  <span class="auth-password-nav-title">Hybrid Mapping</span>
                </button>
              </div>

              <div class="tab-content auth-password-content auth-sso-content">
                <div class="tab-pane fade show active" id="auth-sso-section-access" role="tabpanel">
                  <div class="auth-password-section-head mb-3">
                    <div>
                      <div class="auth-password-section-title">SSO Access</div>
                      <div class="text-muted small">Control whether SSO is available and decide how it is applied across allowed login categories.</div>
                    </div>
                  </div>

                  <div class="auth-security-stack">
                    <div class="auth-security-group">
                      <div class="auth-security-group-head">
                        <div class="auth-security-group-title">Availability</div>
                        <div class="text-muted small">Enable or disable Single Sign-On as an authentication option for this system.</div>
                      </div>
                      <div class="auth-setting-row mb-0">
                        <div class="auth-setting-copy">
                          <label class="form-label fw-semibold mb-1" for="auth_sso_enabled"><?= __('config_auth_sso_enabled') ?? 'Enable SSO' ?></label>
                          <div class="text-muted small"><?= __('config_auth_sso_enabled_help') ?? 'Enable Single Sign-On as an available authentication mechanism.' ?></div>
                        </div>
                        <div class="auth-setting-control">
                          <div class="form-check form-switch auth-switch mb-2">
                            <input class="form-check-input" type="checkbox" role="switch" id="auth_sso_enabled" name="auth_sso_enabled" value="1" onchange="if(window.__tetapanSyncAuthPolicyUi){window.__tetapanSyncAuthPolicyUi();}else if(window.__tetapanRefreshAuthPolicySummary){window.__tetapanRefreshAuthPolicySummary();}" <?= $authSsoEnabled ? 'checked' : '' ?>>
                          </div>
                          <span id="auth-sso-enabled-state" class="badge bg-<?= $authSsoEnabled ? 'success' : 'secondary' ?>-subtle text-<?= $authSsoEnabled ? 'success' : 'secondary' ?>">
                            <?= $authSsoEnabled ? (__('config_auth_enabled') ?? 'Enabled') : (__('config_auth_disabled') ?? 'Disabled') ?>
                          </span>
                        </div>
                      </div>
                    </div>

                    <div class="auth-security-group">
                      <div class="auth-security-group-head">
                        <div class="auth-security-group-title">Mode Strategy</div>
                        <div class="text-muted small">Choose whether SSO is enforced globally or selectively applied by category.</div>
                      </div>
                      <div class="row g-3 auth-password-policy-grid">
                        <div class="col-xl-5">
                          <div class="auth-form-group">
                            <label class="form-label fw-semibold" for="auth_sso_mode"><?= __('config_auth_sso_mode') ?? 'SSO Mode' ?></label>
                            <div class="text-muted small mb-2"><?= __('config_auth_sso_mode_help') ?? 'Choose how authentication method is applied to each allowed user category.' ?></div>
                            <select class="form-select" id="auth_sso_mode" name="auth_sso_mode" onchange="if(window.__tetapanSyncAuthPolicyUi){window.__tetapanSyncAuthPolicyUi();}else if(window.__tetapanRefreshAuthPolicySummary){window.__tetapanRefreshAuthPolicySummary();}">
                              <?php foreach (['ALL', 'MANUAL', 'HYBRID'] as $modeOption): ?>
                                <option value="<?= $modeOption ?>" <?= $authSsoMode === $modeOption ? 'selected' : '' ?>>
                                  <?= htmlspecialchars((string)(__('config_auth_sso_mode_' . strtolower($modeOption)) ?? $modeOption), ENT_QUOTES, 'UTF-8') ?>
                                </option>
                              <?php endforeach; ?>
                            </select>
                          </div>
                        </div>
                        <div class="col-xl-7">
                          <div class="auth-form-group">
                            <label class="form-label fw-semibold"><?= __('config_auth_sso_mode_effective') ?? 'Mode Summary' ?></label>
                            <div class="auth-settings-note mb-0" id="auth-sso-mode-note">
                              <?php if ($authSsoMode === 'ALL'): ?>
                                <i class="ri-information-line me-1"></i><?= __('config_auth_sso_mode_all_note') ?? 'In ALL mode, Staff and Student users must use SSO. Public users may still log in manually.' ?>
                              <?php elseif ($authSsoMode === 'HYBRID'): ?>
                                <i class="ri-information-line me-1"></i><?= __('config_auth_sso_mode_hybrid_note') ?? 'In HYBRID mode, each category follows its own configured login method.' ?>
                              <?php else: ?>
                                <i class="ri-information-line me-1"></i><?= __('config_auth_sso_mode_manual_note') ?? 'In MANUAL mode, all allowed categories use manual login.' ?>
                              <?php endif; ?>
                            </div>
                          </div>
                        </div>
                      </div>
                    </div>

                  </div>
                </div>

                <div class="tab-pane fade" id="auth-sso-section-auto-provision" role="tabpanel">
                  <div class="auth-password-section-head mb-3">
                    <div>
                      <div class="auth-password-section-title"><?= __('config_auth_auto_provision_title') ?? 'SSO Auto Provisioning' ?></div>
                      <div class="text-muted small"><?= __('config_auth_auto_provision_sub') ?? 'Allow first-time Staff and Student users to be created automatically through SSO using the configured default group.' ?></div>
                    </div>
                  </div>

                  <div class="auth-security-stack auth-auto-provision-stack">
                    <div class="row g-3 auth-password-policy-grid auth-auto-provision-grid">
                      <div class="col-xl-6">
                        <div class="auth-security-group auth-auto-provision-card h-100 mb-0">
                          <div class="auth-security-group-head">
                            <div class="auth-security-group-title"><?= __('config_auth_auto_provision_staff_panel') ?? 'Staff Auto Provision' ?></div>
                            <div class="text-muted small"><?= __('config_auth_auto_provision_staff_panel_sub') ?? 'Only applies on first login through SSO. Manual staff login still requires an existing app user record.' ?></div>
                          </div>
                          <div class="auth-setting-row mb-3">
                            <div class="auth-setting-copy">
                              <label class="form-label fw-semibold mb-1" for="auth_auto_provision_staf_sso"><?= __('config_auth_auto_provision_staf_sso') ?? 'Enable Staff SSO Auto Provision' ?></label>
                              <div class="text-muted small"><?= __('config_auth_auto_provision_staf_sso_help') ?? 'Automatically create a Staff app record when a valid SSO user has no existing tbl_m_user record.' ?></div>
                            </div>
                            <div class="auth-setting-control">
                              <div class="form-check form-switch auth-switch mb-2">
                                <input class="form-check-input" type="checkbox" role="switch" id="auth_auto_provision_staf_sso" name="auth_auto_provision_staf_sso" value="1" onchange="if(window.__tetapanSyncAuthPolicyUi){window.__tetapanSyncAuthPolicyUi();}else if(window.__tetapanRefreshAuthPolicySummary){window.__tetapanRefreshAuthPolicySummary();}" <?= $authAutoProvisionStaff ? 'checked' : '' ?>>
                              </div>
                              <span id="auth-auto-provision-state-staff" class="badge bg-<?= $authAutoProvisionStaff ? 'success' : 'secondary' ?>-subtle text-<?= $authAutoProvisionStaff ? 'success' : 'secondary' ?>">
                                <?= $authAutoProvisionStaff ? (__('config_auth_enabled') ?? 'Enabled') : (__('config_auth_disabled') ?? 'Disabled') ?>
                              </span>
                            </div>
                          </div>
                          <div class="auth-form-group">
                            <label class="form-label fw-semibold" for="auth_default_group_staff_code"><?= __('config_auth_default_group_staff_code') ?? 'Default Staff Group Code' ?></label>
                            <div class="text-muted small mb-2"><?= __('config_auth_default_group_staff_code_help') ?? 'Group code assigned to newly auto-provisioned Staff users after successful first-time SSO login.' ?></div>
                            <input type="text" class="form-control text-uppercase" id="auth_default_group_staff_code" name="auth_default_group_staff_code" maxlength="50" value="<?= htmlspecialchars($authDefaultGroupStaffCode, ENT_QUOTES, 'UTF-8') ?>" placeholder="ADM-STAF" oninput="this.value=this.value.toUpperCase(); if(window.__tetapanSyncAuthPolicyUi){window.__tetapanSyncAuthPolicyUi();}else if(window.__tetapanRefreshAuthPolicySummary){window.__tetapanRefreshAuthPolicySummary();}">
                          </div>
                        </div>
                      </div>

                      <div class="col-xl-6">
                        <div class="auth-security-group auth-auto-provision-card h-100 mb-0">
                          <div class="auth-security-group-head">
                            <div class="auth-security-group-title"><?= __('config_auth_auto_provision_student_panel') ?? 'Student Auto Provision' ?></div>
                            <div class="text-muted small"><?= __('config_auth_auto_provision_student_panel_sub') ?? 'Only applies on first login through SSO. Manual student login still requires an existing app user record.' ?></div>
                          </div>
                          <div class="auth-setting-row mb-3">
                            <div class="auth-setting-copy">
                              <label class="form-label fw-semibold mb-1" for="auth_auto_provision_pelajar_sso"><?= __('config_auth_auto_provision_pelajar_sso') ?? 'Enable Student SSO Auto Provision' ?></label>
                              <div class="text-muted small"><?= __('config_auth_auto_provision_pelajar_sso_help') ?? 'Automatically create a Student app record when a valid SSO user has no existing tbl_m_user record.' ?></div>
                            </div>
                            <div class="auth-setting-control">
                              <div class="form-check form-switch auth-switch mb-2">
                                <input class="form-check-input" type="checkbox" role="switch" id="auth_auto_provision_pelajar_sso" name="auth_auto_provision_pelajar_sso" value="1" onchange="if(window.__tetapanSyncAuthPolicyUi){window.__tetapanSyncAuthPolicyUi();}else if(window.__tetapanRefreshAuthPolicySummary){window.__tetapanRefreshAuthPolicySummary();}" <?= $authAutoProvisionStudent ? 'checked' : '' ?>>
                              </div>
                              <span id="auth-auto-provision-state-student" class="badge bg-<?= $authAutoProvisionStudent ? 'success' : 'secondary' ?>-subtle text-<?= $authAutoProvisionStudent ? 'success' : 'secondary' ?>">
                                <?= $authAutoProvisionStudent ? (__('config_auth_enabled') ?? 'Enabled') : (__('config_auth_disabled') ?? 'Disabled') ?>
                              </span>
                            </div>
                          </div>
                          <div class="auth-form-group">
                            <label class="form-label fw-semibold" for="auth_default_group_student_code"><?= __('config_auth_default_group_student_code') ?? 'Default Student Group Code' ?></label>
                            <div class="text-muted small mb-2"><?= __('config_auth_default_group_student_code_help') ?? 'Group code assigned to newly auto-provisioned Student users after successful first-time SSO login.' ?></div>
                            <input type="text" class="form-control text-uppercase" id="auth_default_group_student_code" name="auth_default_group_student_code" maxlength="50" value="<?= htmlspecialchars($authDefaultGroupStudentCode, ENT_QUOTES, 'UTF-8') ?>" placeholder="ADM-STUDENT" oninput="this.value=this.value.toUpperCase(); if(window.__tetapanSyncAuthPolicyUi){window.__tetapanSyncAuthPolicyUi();}else if(window.__tetapanRefreshAuthPolicySummary){window.__tetapanRefreshAuthPolicySummary();}">
                          </div>
                        </div>
                      </div>
                    </div>

                    <div class="auth-settings-inline-note auth-auto-provision-note mb-0">
                      <i class="ri-information-line me-1"></i><?= __('config_auth_auto_provision_note') ?? 'Auto provisioning only applies through SSO. Staff and Student manual login still require an existing tbl_m_user record.' ?>
                    </div>
                  </div>
                </div>

                <div class="tab-pane fade" id="auth-sso-section-provider" role="tabpanel">
                  <div class="auth-password-section-head mb-3">
                    <div>
                      <div class="auth-password-section-title">Identity Provider</div>
                      <div class="text-muted small">Manage the OneID registration details required for redirect and token validation.</div>
                    </div>
                  </div>

                  <div class="auth-security-group">
                    <div class="auth-security-group-head">
                      <div class="auth-security-group-title">OneID Connection Details</div>
                      <div class="text-muted small">These values identify this application and the upstream identity provider used during SSO.</div>
                    </div>
                    <div class="row g-3 auth-password-policy-grid">
                      <div class="col-xl-5">
                        <div class="auth-form-group h-100">
                          <label class="form-label fw-semibold" for="auth_sso_site_id">
                            <?= __('config_auth_sso_site_id') ?? 'OneID Site ID' ?>
                            <span id="auth-sso-site-id-required" class="text-danger<?= $authSsoEnabled ? '' : ' d-none' ?>"> *</span>
                          </label>
                          <div class="text-muted small mb-2"><?= __('config_auth_sso_site_id_help') ?? 'Used for the OneID application registration of this system.' ?></div>
                          <input type="text"
                                  class="form-control"
                                  id="auth_sso_site_id"
                                  name="auth_sso_site_id"
                                  value="<?= htmlspecialchars($authSsoSiteId, ENT_QUOTES, 'UTF-8') ?>"
                                 <?= $authSsoEnabled ? 'required' : '' ?>
                                  placeholder="V8LN57YMGZ">
                        </div>
                      </div>
                      <div class="col-xl-7">
                        <div class="auth-form-group h-100">
                          <label class="form-label fw-semibold" for="auth_sso_idp_domain">
                            <?= __('config_auth_sso_idp_domain') ?? 'OneID IdP Domain' ?>
                            <span id="auth-sso-idp-domain-required" class="text-danger<?= $authSsoEnabled ? '' : ' d-none' ?>"> *</span>
                          </label>
                          <div class="text-muted small mb-2"><?= __('config_auth_sso_idp_domain_help') ?? 'Base URL of the OneID Identity Provider used for SSO redirection and token validation.' ?></div>
                          <input type="text"
                                  class="form-control"
                                  id="auth_sso_idp_domain"
                                  name="auth_sso_idp_domain"
                                  value="<?= htmlspecialchars($authSsoIdpDomain, ENT_QUOTES, 'UTF-8') ?>"
                                 <?= $authSsoEnabled ? 'required' : '' ?>
                                  placeholder="https://oneid.upnm.edu.my/">
                        </div>
                      </div>
                    </div>
                  </div>
                </div>

                <div class="tab-pane fade" id="auth-sso-section-mapping" role="tabpanel">
                  <div class="auth-password-section-head mb-3">
                    <div>
                      <div class="auth-password-section-title">Hybrid Mapping</div>
                      <div class="text-muted small">Define category-specific authentication routing when the selected SSO mode is set to hybrid.</div>
                    </div>
                  </div>

                  <div class="auth-security-group">
                    <div class="auth-security-group-head">
                      <div class="auth-security-group-title"><?= __('config_auth_hybrid_header') ?? 'Hybrid Category Mapping' ?></div>
                      <div class="text-muted small"><?= __('config_auth_hybrid_sub') ?? 'Define the login method for each category when SSO Mode is set to HYBRID.' ?></div>
                    </div>

                    <div class="auth-hybrid-block auth-password-panel <?= $authSsoMode === 'HYBRID' ? '' : 'auth-hybrid-block-muted' ?>" id="auth-hybrid-block">
                      <div class="auth-hybrid-header">
                        <div></div>
                        <span class="badge bg-light text-dark border"><?= __('config_auth_sso_mode_hybrid') ?? 'HYBRID' ?></span>
                      </div>

                      <div class="row g-3">
                        <div class="col-lg-4">
                          <div class="auth-form-group h-100">
                            <label class="form-label fw-semibold" for="auth_sso_hybrid_staf"><?= __('config_auth_sso_hybrid_staf') ?? 'Staff Login Method' ?></label>
                            <div class="text-muted small mb-2"><?= __('config_auth_sso_hybrid_staf_help') ?? 'Choose the login method for Staff users.' ?></div>
                            <select class="form-select" id="auth_sso_hybrid_staf" name="auth_sso_hybrid_staf" onchange="if(window.__tetapanSyncAuthPolicyUi){window.__tetapanSyncAuthPolicyUi();}else if(window.__tetapanRefreshAuthPolicySummary){window.__tetapanRefreshAuthPolicySummary();}">
                              <?php foreach (['SSO', 'MANUAL'] as $hybridOption): ?>
                                <option value="<?= $hybridOption ?>" <?= (($authHybrid['staf'] ?? 'SSO') === $hybridOption) ? 'selected' : '' ?>>
                                  <?= htmlspecialchars((string)(__('config_auth_hybrid_option_' . strtolower($hybridOption)) ?? $hybridOption), ENT_QUOTES, 'UTF-8') ?>
                                </option>
                              <?php endforeach; ?>
                            </select>
                          </div>
                        </div>
                        <div class="col-lg-4">
                          <div class="auth-form-group h-100">
                            <label class="form-label fw-semibold" for="auth_sso_hybrid_pelajar"><?= __('config_auth_sso_hybrid_pelajar') ?? 'Student Login Method' ?></label>
                            <div class="text-muted small mb-2"><?= __('config_auth_sso_hybrid_pelajar_help') ?? 'Choose the login method for Student users.' ?></div>
                            <select class="form-select" id="auth_sso_hybrid_pelajar" name="auth_sso_hybrid_pelajar" onchange="if(window.__tetapanSyncAuthPolicyUi){window.__tetapanSyncAuthPolicyUi();}else if(window.__tetapanRefreshAuthPolicySummary){window.__tetapanRefreshAuthPolicySummary();}">
                              <?php foreach (['SSO', 'MANUAL'] as $hybridOption): ?>
                                <option value="<?= $hybridOption ?>" <?= (($authHybrid['pelajar'] ?? 'SSO') === $hybridOption) ? 'selected' : '' ?>>
                                  <?= htmlspecialchars((string)(__('config_auth_hybrid_option_' . strtolower($hybridOption)) ?? $hybridOption), ENT_QUOTES, 'UTF-8') ?>
                                </option>
                              <?php endforeach; ?>
                            </select>
                          </div>
                        </div>
                        <div class="col-lg-4">
                          <div class="auth-form-group h-100">
                            <label class="form-label fw-semibold" for="auth_sso_hybrid_umum"><?= __('config_auth_sso_hybrid_umum') ?? 'Public Login Method' ?></label>
                            <div class="text-muted small mb-2"><?= __('config_auth_sso_hybrid_umum_help') ?? 'Choose the login method for Public users.' ?></div>
                            <select class="form-select" id="auth_sso_hybrid_umum" name="auth_sso_hybrid_umum" onchange="if(window.__tetapanSyncAuthPolicyUi){window.__tetapanSyncAuthPolicyUi();}else if(window.__tetapanRefreshAuthPolicySummary){window.__tetapanRefreshAuthPolicySummary();}">
                              <?php foreach (['SSO', 'MANUAL'] as $hybridOption): ?>
                                <option value="<?= $hybridOption ?>" <?= (($authHybrid['umum'] ?? 'MANUAL') === $hybridOption) ? 'selected' : '' ?>>
                                  <?= htmlspecialchars((string)(__('config_auth_hybrid_option_' . strtolower($hybridOption)) ?? $hybridOption), ENT_QUOTES, 'UTF-8') ?>
                                </option>
                              <?php endforeach; ?>
                            </select>
                          </div>
                        </div>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <div class="auth-settings-actions d-flex justify-content-between align-items-center flex-wrap gap-2 mt-2">
      <div class="text-muted small">
        <i class="ri-information-line me-1"></i><?= __('config_auth_actions_note') ?? 'Changes here prepare the policy foundation only. Runtime login enforcement will be enabled in the next implementation phase.' ?>
      </div>
            <button type="submit"
              class="btn btn-primary px-4"
              id="btn-simpan-auth">
        <i class="ri-save-3-line me-2"></i><?= __('config_auth_save') ?? 'Save Login Policy' ?>
      </button>
    </div>
  </form>
</div>
