<?php
declare(strict_types=1);

$systemInfoText = static function (string $key, string $fallback): string {
    $value = __($key);
    return is_string($value) && $value !== '' && $value !== $key ? $value : $fallback;
};

$systemInfoRows = static function (array $rows): void {
    foreach ($rows as $row) {
        $setting = (string)($row['setting'] ?? '-');
        $value = (string)($row['value'] ?? '-');
        ?>
        <tr>
          <th scope="row"><?= h($setting) ?></th>
          <td><span class="system-info-value"><?= nl2br(h($value)) ?></span></td>
        </tr>
        <?php
    }
};

$systemInfoStatusLabel = static function (string $status) use ($systemInfoText): string {
    return match ($status) {
        'optimized' => $systemInfoText('config_system_info_status_optimized', 'Optimized'),
        'acceptable' => $systemInfoText('config_system_info_status_acceptable', 'Acceptable'),
        'review' => $systemInfoText('config_system_info_status_review', 'Review'),
        'action' => $systemInfoText('config_system_info_status_action', 'Action Required'),
        default => $systemInfoText('config_system_info_status_unknown', 'Unknown'),
    };
};

$systemInfoFolderStatusLabel = static function (string $status) use ($systemInfoText): string {
    return match ($status) {
        'writable' => $systemInfoText('config_system_info_folder_writable', 'Writable'),
        'protected' => $systemInfoText('config_system_info_folder_protected', 'Protected'),
        'review' => $systemInfoText('config_system_info_folder_review', 'Review'),
        'blocked' => $systemInfoText('config_system_info_folder_blocked', 'Not writable'),
        default => $systemInfoText('config_system_info_folder_missing', 'Missing'),
    };
};
?>

<div class="tab-pane fade <?= ($_GET['tab'] ?? '') === 'system-info' ? 'show active' : '' ?>" id="system-info-tab" role="tabpanel">
  <div class="card system-information-card">
    <div class="card-header system-information-header">
      <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
        <div class="d-flex align-items-center">
          <div class="system-information-icon me-3"><i class="ri-server-line fs-5"></i></div>
          <div>
            <h5 class="mb-1 fw-semibold text-primary"><?= h($systemInfoText('config_system_info_title', 'System Information')) ?></h5>
            <small class="text-muted"><?= h($systemInfoText('config_system_info_subtitle', 'Read-only runtime, configuration and permission diagnostics for administrators.')) ?></small>
          </div>
        </div>
        <span class="badge bg-success-subtle text-success system-information-readonly-badge">
          <i class="ri-eye-line me-1"></i><?= h($systemInfoText('config_system_info_readonly', 'Read only')) ?>
        </span>
      </div>
    </div>

    <div class="card-body">
      <?php if (isset($systemInformation['error'])): ?>
        <div class="alert alert-warning mb-0" role="alert">
          <i class="ri-alert-line me-2"></i><?= h((string)$systemInformation['error']) ?>
        </div>
      <?php elseif ($systemInformation === []): ?>
        <div class="system-information-empty">
          <i class="ri-information-line"></i>
          <span><?= h($systemInfoText('config_system_info_open_note', 'Open this tab directly to collect the current runtime information.')) ?></span>
        </div>
      <?php else: ?>
        <div class="system-information-security-note">
          <i class="ri-shield-check-line"></i>
          <span><?= h($systemInfoText('config_system_info_security_note', 'Sensitive credentials, environment secrets, cookies and session identifiers are never displayed.')) ?></span>
        </div>

        <ul class="nav nav-tabs system-information-subtabs" id="systemInformationSubtabs" role="tablist">
          <li class="nav-item" role="presentation">
            <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#system-information-summary" type="button" role="tab" aria-selected="true" data-system-info-subtab="summary">
              <i class="ri-computer-line me-1"></i><?= h($systemInfoText('config_system_info_subtab_system', 'System Information')) ?>
            </button>
          </li>
          <li class="nav-item" role="presentation">
            <button class="nav-link" data-bs-toggle="tab" data-bs-target="#system-information-php-settings" type="button" role="tab" aria-selected="false" data-system-info-subtab="php-settings">
              <i class="ri-settings-5-line me-1"></i><?= h($systemInfoText('config_system_info_subtab_php_settings', 'PHP Settings')) ?>
            </button>
          </li>
          <li class="nav-item" role="presentation">
            <button class="nav-link" data-bs-toggle="tab" data-bs-target="#system-information-configuration" type="button" role="tab" aria-selected="false" data-system-info-subtab="configuration">
              <i class="ri-file-settings-line me-1"></i><?= h($systemInfoText('config_system_info_subtab_configuration', 'Configuration File')) ?>
            </button>
          </li>
          <li class="nav-item" role="presentation">
            <button class="nav-link" data-bs-toggle="tab" data-bs-target="#system-information-folders" type="button" role="tab" aria-selected="false" data-system-info-subtab="folders">
              <i class="ri-folder-shield-2-line me-1"></i><?= h($systemInfoText('config_system_info_subtab_folders', 'Folder Permissions')) ?>
            </button>
          </li>
          <li class="nav-item" role="presentation">
            <button class="nav-link" data-bs-toggle="tab" data-bs-target="#system-information-php-info" type="button" role="tab" aria-selected="false" data-system-info-subtab="php-info">
              <i class="ri-code-box-line me-1"></i><?= h($systemInfoText('config_system_info_subtab_php_info', 'PHP Information')) ?>
            </button>
          </li>
        </ul>

        <div class="tab-content system-information-subtab-content">
          <div class="tab-pane fade show active" id="system-information-summary" role="tabpanel">
            <div class="table-responsive">
              <table class="table system-information-table mb-0">
                <thead><tr><th><?= h($systemInfoText('config_system_info_setting', 'Setting')) ?></th><th><?= h($systemInfoText('config_system_info_value', 'Value')) ?></th></tr></thead>
                <tbody><?php $systemInfoRows((array)($systemInformation['summary'] ?? [])); ?></tbody>
              </table>
            </div>
          </div>

          <div class="tab-pane fade" id="system-information-php-settings" role="tabpanel">
            <div class="system-information-inline-note system-information-php-profile">
              <i class="ri-speed-up-line"></i>
              <div>
                <strong><?= h($systemInfoText('config_system_info_php_profile', 'Assessment profile')) ?>:</strong>
                <?= h((string)($systemInformation['phpSettingsProfile'] ?? 'Unknown')) ?>.
                <?= h($systemInfoText('config_system_info_php_profile_note', 'Optimized matches the recommended profile; Acceptable is safe with an optional improvement; Review should be checked; Action Required identifies a security or reliability issue.')) ?>
              </div>
            </div>
            <div class="table-responsive">
              <table class="table system-information-table system-information-php-settings-table mb-0">
                <thead><tr><th><?= h($systemInfoText('config_system_info_setting', 'Setting')) ?></th><th><?= h($systemInfoText('config_system_info_value', 'Value')) ?></th><th><?= h($systemInfoText('config_system_info_recommended', 'Recommended')) ?></th><th><?= h($systemInfoText('config_system_info_status', 'Status')) ?></th><th><?= h($systemInfoText('config_system_info_assessment', 'Assessment')) ?></th></tr></thead>
                <tbody>
                  <?php foreach ((array)($systemInformation['phpSettings'] ?? []) as $row): ?>
                    <?php $status = (string)($row['status'] ?? 'neutral'); ?>
                    <tr>
                      <th scope="row"><?= h((string)($row['setting'] ?? '-')) ?></th>
                      <td><?= h((string)($row['value'] ?? '-')) ?></td>
                      <td class="text-muted"><?= h((string)($row['recommended'] ?? '-')) ?></td>
                      <td><span class="system-information-status is-<?= h($status) ?>"><?= h($systemInfoStatusLabel($status)) ?></span></td>
                      <td class="text-muted system-information-php-assessment"><?= h((string)($row['note'] ?? '-')) ?></td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
          </div>

          <div class="tab-pane fade" id="system-information-configuration" role="tabpanel">
            <div class="system-information-inline-note"><i class="ri-lock-password-line"></i><?= h($systemInfoText('config_system_info_config_note', 'This is a sanitised configuration summary. Raw .env and credential files are not read into the page.')) ?></div>
            <div class="table-responsive">
              <table class="table system-information-table mb-0">
                <thead><tr><th><?= h($systemInfoText('config_system_info_setting', 'Setting')) ?></th><th><?= h($systemInfoText('config_system_info_value', 'Value')) ?></th></tr></thead>
                <tbody><?php $systemInfoRows((array)($systemInformation['configuration'] ?? [])); ?></tbody>
              </table>
            </div>
          </div>

          <div class="tab-pane fade" id="system-information-folders" role="tabpanel">
            <div class="table-responsive">
              <table class="table system-information-table system-information-permissions-table mb-0">
                <thead><tr><th><?= h($systemInfoText('config_system_info_folder', 'Folder')) ?></th><th><?= h($systemInfoText('config_system_info_path', 'Path')) ?></th><th><?= h($systemInfoText('config_system_info_current_permission', 'Current')) ?></th><th><?= h($systemInfoText('config_system_info_expected_permission', 'Expected')) ?></th><th><?= h($systemInfoText('config_system_info_owner', 'Owner')) ?></th><th><?= h($systemInfoText('config_system_info_status', 'Status')) ?></th><th><?= h($systemInfoText('config_system_info_note', 'Reason')) ?></th></tr></thead>
                <tbody>
                  <?php foreach ((array)($systemInformation['folders'] ?? []) as $row): ?>
                    <?php $folderStatus = (string)($row['status'] ?? 'missing'); ?>
                    <tr>
                      <th scope="row"><?= h((string)($row['label'] ?? '-')) ?></th>
                      <td><code><?= h((string)($row['path'] ?? '-')) ?></code></td>
                      <td><code><?= h((string)($row['permissions'] ?? '----')) ?></code></td>
                      <td><code><?= h((string)($row['expected_permissions'] ?? '----')) ?></code></td>
                      <td><?= h((string)($row['owner'] ?? '-')) ?></td>
                      <td><span class="system-information-folder-status is-<?= h($folderStatus) ?>"><?= h($systemInfoFolderStatusLabel($folderStatus)) ?></span></td>
                      <td class="text-muted system-information-permission-note"><?= h((string)($row['note'] ?? '-')) ?></td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
          </div>

          <div class="tab-pane fade" id="system-information-php-info" role="tabpanel">
            <div class="system-information-inline-note"><i class="ri-shield-keyhole-line"></i><?= h($systemInfoText('config_system_info_php_note', 'Safe PHP information is shown instead of raw phpinfo() to prevent environment and request-secret exposure.')) ?></div>
            <div class="system-information-section-title"><?= h($systemInfoText('config_system_info_php_general', 'PHP General')) ?></div>
            <div class="table-responsive">
              <table class="table system-information-table mb-0"><tbody><?php $systemInfoRows((array)($systemInformation['phpInformation']['general'] ?? [])); ?></tbody></table>
            </div>
            <div class="system-information-section-title mt-4"><?= h($systemInfoText('config_system_info_opcache', 'OPcache')) ?></div>
            <div class="table-responsive">
              <table class="table system-information-table mb-0"><tbody><?php $systemInfoRows((array)($systemInformation['phpInformation']['opcache'] ?? [])); ?></tbody></table>
            </div>
            <div class="system-information-section-title mt-4"><?= h($systemInfoText('config_system_info_extensions', 'Important Extensions')) ?></div>
            <div class="table-responsive">
              <table class="table system-information-table mb-0">
                <thead><tr><th><?= h($systemInfoText('config_system_info_extension', 'Extension')) ?></th><th><?= h($systemInfoText('config_system_info_status', 'Status')) ?></th><th><?= h($systemInfoText('config_system_info_version', 'Version')) ?></th></tr></thead>
                <tbody>
                  <?php foreach ((array)($systemInformation['phpInformation']['extensions'] ?? []) as $extension): ?>
                    <?php $loaded = !empty($extension['loaded']); ?>
                    <tr>
                      <th scope="row"><?= h((string)($extension['extension'] ?? '-')) ?></th>
                      <td><span class="system-information-status <?= $loaded ? 'is-good' : 'is-warning' ?>"><?= h($loaded ? 'Loaded' : 'Not loaded') ?></span></td>
                      <td><?= h((string)($extension['version'] ?? '-')) ?></td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
          </div>
        </div>

        <div class="system-information-footer-note">
          <i class="ri-time-line"></i>
          <?= h($systemInfoText('config_system_info_generated', 'Generated at')) ?>:
          <time datetime="<?= h((string)($systemInformation['generatedAt'] ?? '')) ?>"><?= h((string)($systemInformation['generatedAt'] ?? '-')) ?></time>
        </div>
      <?php endif; ?>
    </div>
  </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
  var buttons = Array.prototype.slice.call(document.querySelectorAll('[data-system-info-subtab]'));
  if (!buttons.length || typeof bootstrap === 'undefined' || !bootstrap.Tab) return;
  var key = 'tetapan-sistem.system-info-subtab';
  var saved = '';
  try { saved = window.sessionStorage.getItem(key) || ''; } catch (error) {}
  if (saved) {
    var savedButton = document.querySelector('[data-system-info-subtab="' + saved.replace(/[^a-z-]/g, '') + '"]');
    if (savedButton) bootstrap.Tab.getOrCreateInstance(savedButton).show();
  }
  buttons.forEach(function (button) {
    button.addEventListener('shown.bs.tab', function () {
      try { window.sessionStorage.setItem(key, button.getAttribute('data-system-info-subtab') || 'summary'); } catch (error) {}
    });
  });
});
</script>
