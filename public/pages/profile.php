<?php
/**
 * IQS FRAMEWORK CORE FILE
 *
 * READ ONLY for downstream project programmers.
 * Do not modify this file directly in template or cloned projects.
 * Custom changes must be implemented in project-specific files
 * or approved extension points.
 */
// pages/profile.php
declare(strict_types=1);

require_once __DIR__ . '/../includes/init.php';
require_login();

$defaultHome = (string)app_config('site.default_home', 'pages/dashboard.php');

// ==================== CONFIGURATION CONSTANTS ====================
const PROFILE_CONFIG = [
  'LOGIN_ACTIVITY_LIMIT' => 30,
  'AUDIT_EVENTS_LIMIT' => 30,
  'DATATABLES_PAGE_LENGTH' => 10,
  'DATATABLES_INIT_DELAY' => 300,
  'TOAST_DURATION' => 1400,
  'POLLING_INTERVAL' => 100,
  'POLLING_MAX_ATTEMPTS' => 50,
  'COPY_RATE_LIMIT' => 1000
];

// Controller
require_once __DIR__ . '/../controllers/ProfileController.php';

// Error boundary - catch all exceptions
$errorMessage = null;
try {
  $controller   = new ProfileController();
  $controller->handleRequest();
  $lang         = $controller->getLang();
  $version      = (string)($_ENV['APP_ASSET_VER'] ?? date('ymdHis'));
  $profile      = $controller->getCurrentUserProfile();
  $profileView  = $profile; // freeze to avoid include collisions
  $hasActiveSession = $controller->hasActiveSession();
  $activeLanguages = $controller->getActiveLanguages();
} catch (Throwable $e) {
  error_log('[profile.php] Error loading data: ' . $e->getMessage());
  $profile = [];
  $profileView = [];
  $hasActiveSession = false;
  $activeLanguages = ['ms', 'en'];
  $errorMessage = tr('profile_error_load', 'Ralat memuat data profil. Sila cuba lagi atau hubungi pentadbir sistem.');
}

$profileAuditMetaAllowed = false;
try {
  if (!empty($profileView) && function_exists('is_user_super_admin')) {
    $profileAuditMetaAllowed = (bool)is_user_super_admin($profileView, Database::getInstance()->getConnection());
  }
} catch (Throwable $e) {
  $profileAuditMetaAllowed = false;
}

// Close session lock after reading
if (session_status() === PHP_SESSION_ACTIVE) session_write_close();

if (!function_exists('h')) {
  function h($v){ return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
}
function tr(string $key, string $fallback): string {
  $t = __($key);
  return ($t === $key || $t === null || $t === '') ? $fallback : (string)$t;
}

/**
 * Safe DateTime creation dengan error handling
 */
function safeDateTime(?string $dateString): ?DateTime {
  if (empty($dateString)) return null;
  try {
    return new DateTime($dateString);
  } catch (Exception $e) {
    error_log('[profile.php] Invalid date: ' . $dateString . ' - ' . $e->getMessage());
    return null;
  }
}

/**
 * Format duration dengan proper handling
 */
function formatDuration(?int $seconds): string {
  if ($seconds === null || $seconds < 0) {
    return '—';
  }
  
  if ($seconds < 60) {
    return $seconds . tr('profile_duration_seconds_short', 's');
  } elseif ($seconds < 3600) {
    return floor($seconds / 60) . tr('profile_duration_minutes_short', 'm');
  } elseif ($seconds < 86400) {
    $hours = floor($seconds / 3600);
    $minutes = floor(($seconds % 3600) / 60);
    return $hours . tr('profile_duration_hours_short', 'j') . ' ' . $minutes . tr('profile_duration_minutes_short', 'm');
  } else {
    $days = floor($seconds / 86400);
    $hours = floor(($seconds % 86400) / 3600);
    return $days . tr('profile_duration_days_short', 'h') . ' ' . $hours . tr('profile_duration_hours_short', 'j');
  }
}

/**
 * Detect device type dari user agent dengan better parsing
 */
function detectDeviceType(string $userAgent): array {
  $ua = strtolower($userAgent);
  $icon = 'ri-device-line';
  $type = tr('profile_device_unknown', 'Unknown');
  
  // Mobile detection (check first)
  if (preg_match('/ipad/i', $ua)) {
    $icon = 'ri-tablet-line';
    $type = tr('profile_device_ipad', 'iPad');
  } elseif (preg_match('/iphone|ipod/i', $ua)) {
    $icon = 'ri-smartphone-line';
    $type = tr('profile_device_iphone', 'iPhone');
  } elseif (preg_match('/android/i', $ua)) {
    $icon = 'ri-smartphone-line';
    $type = tr('profile_device_android', 'Android');
  } elseif (preg_match('/mobile|blackberry|iemobile|opera mini/i', $ua)) {
    $icon = 'ri-smartphone-line';
    $type = tr('profile_device_mobile', 'Mobile');
  }
  // Desktop OS detection
  elseif (preg_match('/windows/i', $ua)) {
    $icon = 'ri-computer-line';
    $type = tr('profile_device_windows', 'Windows');
  } elseif (preg_match('/macintosh|mac os x/i', $ua)) {
    $icon = 'ri-macbook-line';
    $type = tr('profile_device_macos', 'macOS');
  } elseif (preg_match('/linux/i', $ua)) {
    $icon = 'ri-ubuntu-line';
    $type = tr('profile_device_linux', 'Linux');
  } elseif (preg_match('/chrome os|cros/i', $ua)) {
    $icon = 'ri-computer-line';
    $type = tr('profile_device_chromeos', 'Chrome OS');
  }
  
  return ['icon' => $icon, 'type' => $type];
}

$PAGE_TITLE = tr('profile_title','Profil Pengguna');
?>
<!DOCTYPE html>
<html lang="<?= h($lang) ?>" data-bs-theme="<?= h($_SESSION['theme.layout'] ?? 'light') ?>">
<head>
  <?php
    $NEED_DATERANGE  = false;
    $NEED_VECTORMAP  = false;
    $NEED_DATATABLES = true;
    $NEED_SELECT2    = false;
    include __DIR__ . '/../includes/head.php';
  ?>
  <!-- ✅ Standard DataTables CSS (shared) -->
  <link href="<?= base_url('assets/css/datatables-standard.css') ?>?v=<?= h($version) ?>" rel="stylesheet">
  <link href="<?= base_url('assets/css/pages/profile.css') ?>?v=<?= h($version) ?>" rel="stylesheet">
</head>
<body
  data-topbar-color="<?= h($_SESSION['theme.topbar'] ?? 'light') ?>"
  data-menu-color="<?= h($_SESSION['theme.menu'] ?? $_SESSION['theme.sidebar'] ?? 'dark') ?>"
  data-layout="vertical" data-sidebar-size="default" class="loading profile-page">

<div class="wrapper">
  <?php include __DIR__ . '/../includes/topbar.php'; ?>
  <?php include __DIR__ . '/../includes/sidebar.php'; ?>

  <div class="content-page">
    <div class="content">
      <div class="container-fluid">

        <!-- Title + breadcrumb -->
        <div class="row mb-3">
          <div class="col-12">
            <div class="page-title-box d-flex justify-content-between align-items-center flex-wrap">
              <h4 class="page-title">
                <i class="ri-user-3-line me-1"></i>
                <?= h(tr('profile_title','Profil Pengguna')) ?>
              </h4>
              <div class="page-title-right">
                <ol class="breadcrumb m-0">
                  <li class="breadcrumb-item">
                    <a href="<?= base_url($defaultHome) ?>">
                      <i class="ri-home-4-line align-middle me-1"></i>
                      <?= h(tr('profile_breadcrumb_dashboard','Papan Pemuka')) ?>
                    </a>
                  </li>
                  <li class="breadcrumb-item active">
                    <?= h(tr('profile_breadcrumb','Profil')) ?>
                  </li>
                </ol>
              </div>
            </div>
          </div>
        </div>

        <?php
          $avatarUrl = $profileView['avatar_url'] ?? base_url('assets/images/no-image.jpg');
          $namaPenuh = $profileView['nama_penuh'] ?? '';
          $nickname  = $profileView['nickname']   ?? '';
          $jawatan   = $profileView['jawatan']    ?? '';
          $gred      = $profileView['gred']       ?? '';
          $jabatan   = $profileView['jabatan']    ?? '';
          $stafID    = $profileView['stafID']     ?? '';
          $loginID   = $profileView['loginID']    ?? '';
          $nopek     = $profileView['nopekerja']  ?? '';
          $emel      = $profileView['emel']       ?? '';
          $categoryCandidates = [
            $profileView['categoryUser'] ?? null,
            $profileView['f_categoryUser'] ?? null,
            $_SESSION['f_categoryUser'] ?? null,
            $_SESSION['user']['f_categoryUser'] ?? null,
          ];
          $categoryUser = '';
          foreach ($categoryCandidates as $categoryCandidate) {
            $categoryCandidate = strtoupper(trim((string)$categoryCandidate));
            $normalizedCategory = match ($categoryCandidate) {
              'STUDENT' => 'PELAJAR',
              'PUBLIC' => 'UMUM',
              default => $categoryCandidate,
            };
            if (in_array($normalizedCategory, ['STAF', 'PELAJAR', 'UMUM'], true)) {
              $categoryUser = $normalizedCategory;
              break;
            }
          }
          if ($categoryUser === '') {
            $identityCandidate = trim((string)($loginID !== '' ? $loginID : $stafID));
            if (!empty($_SESSION['student_profile'])) {
              $categoryUser = 'PELAJAR';
            } elseif (trim((string)$nopek) !== '' || preg_match('/^\d{4}-\d{2}$/', $identityCandidate) === 1) {
              $categoryUser = 'STAF';
            } elseif ($identityCandidate !== '' && !str_contains($identityCandidate, '@')) {
              $categoryUser = 'PELAJAR';
            } else {
              $categoryUser = 'UMUM';
            }
          }
          $isStudentProfile = $categoryUser === 'PELAJAR';
          $isPublicProfile = in_array($categoryUser, ['UMUM', 'PUBLIC'], true);
          $isStaffProfile = !$isStudentProfile && !$isPublicProfile;
          $identityValue = ($isStaffProfile || $isStudentProfile) ? trim((string)$stafID) : '';
          $copyIdLabel = $isStudentProfile
            ? tr('profile_btn_copy_no_matrik', 'Salin No. Matrik')
            : tr('profile_btn_copy_no_staf', 'Salin No. Staf');
          $idLabel = $isStudentProfile
            ? tr('profile_no_matrik', 'No. Matrik')
            : tr('profile_no_staf', 'No. Staf');
          $showIdentityStat = $identityValue !== '';
          $showEmployeeStat = $isStaffProfile && trim((string)$nopek) !== '';
          $positionLabel = $isStudentProfile
            ? tr('profile_program', 'Program')
            : tr('profile_jawatan', 'Jawatan');
          $departmentLabel = $isStudentProfile
            ? tr('profile_faculty', 'Fakulti')
            : tr('profile_jabatan', 'Jabatan');
          $emptyPositionLabel = $isStudentProfile
            ? tr('profile_no_program_info', 'Tiada maklumat program')
            : tr('profile_no_job_info', 'Tiada maklumat jawatan');
          $emptyDepartmentLabel = $isStudentProfile
            ? tr('profile_no_faculty_info', 'Tiada maklumat fakulti')
            : tr('profile_no_department_info', 'Tiada maklumat jabatan');
          $selectedLang = $profileView['lang']    ?? ($lang ?? 'ms');
          $jawGred   = trim($jawatan . ($gred ? ' • '.$gred : ''));
          $allowedProfileTabs = ['profil-pengguna', 'login-aktiviti', 'jejak-audit'];
          $requestedProfileTab = trim((string)($_GET['tab'] ?? 'profil-pengguna'));
          $activeProfileTab = in_array($requestedProfileTab, $allowedProfileTabs, true)
            ? $requestedProfileTab
            : 'profil-pengguna';
          $hasProfileIdentity = trim((string)$namaPenuh) !== ''
            || trim((string)$loginID) !== ''
            || trim((string)$stafID) !== ''
            || trim((string)$emel) !== '';
          
          // Check active session status
          $isActive = (bool)$hasActiveSession;
        ?>

        <!-- Profile Tabs -->
        <div class="card border-0 shadow-sm profile-card">
          <!-- Tab Navigasi -->
          <ul class="nav nav-tabs profile-tabs" role="tablist" aria-label="<?= h(tr('profile_tabs_label','Tab profil pengguna')) ?>">
            <li class="nav-item">
              <a id="profil-pengguna-tab-trigger" class="nav-link <?= $activeProfileTab === 'profil-pengguna' ? 'active' : '' ?>" data-bs-toggle="tab" href="#profil-pengguna-tab" role="tab" aria-controls="profil-pengguna-tab" aria-selected="<?= $activeProfileTab === 'profil-pengguna' ? 'true' : 'false' ?>">
                <i class="ri-user-line me-1"></i> <?= h(tr('profile_tab_profil_pengguna','Profil Pengguna')) ?>
              </a>
            </li>
            <li class="nav-item">
              <a id="login-aktiviti-tab-trigger" class="nav-link <?= $activeProfileTab === 'login-aktiviti' ? 'active' : '' ?>" data-bs-toggle="tab" href="#login-aktiviti-tab" role="tab" aria-controls="login-aktiviti-tab" aria-selected="<?= $activeProfileTab === 'login-aktiviti' ? 'true' : 'false' ?>">
                <i class="ri-login-box-line me-1"></i> <?= h(tr('profile_tab_login_aktiviti','Login Aktiviti')) ?>
              </a>
            </li>
            <li class="nav-item">
              <a id="jejak-audit-tab-trigger" class="nav-link <?= $activeProfileTab === 'jejak-audit' ? 'active' : '' ?>" data-bs-toggle="tab" href="#jejak-audit-tab" role="tab" aria-controls="jejak-audit-tab" aria-selected="<?= $activeProfileTab === 'jejak-audit' ? 'true' : 'false' ?>">
                <i class="ri-file-list-3-line me-1"></i> <?= h(tr('profile_tab_jejak_audit','Jejak Audit')) ?>
              </a>
            </li>
          </ul>

          <!-- Kandungan Tab -->
          <div class="tab-content p-4">
            <?php if ($errorMessage): ?>
              <div class="alert alert-danger d-flex align-items-center" role="alert">
                <i class="ri-error-warning-line me-2"></i>
                <div>
                  <?= h($errorMessage) ?>
                </div>
              </div>
            <?php endif; ?>
            
            <?php if (!$hasProfileIdentity && !$errorMessage): ?>
              <div class="alert alert-warning d-flex align-items-center" role="alert">
                <i class="ri-alert-line me-2"></i>
                <div>
                  <?= h(tr(
                    'profile_empty_notice',
                    'Profil tidak dijumpai. Sesi login mungkin tamat atau rekod tiada.'
                  )) ?>
                </div>
              </div>
            <?php endif; ?>

            <!-- Tab 1: Profil Pengguna -->
            <div class="tab-pane fade <?= $activeProfileTab === 'profil-pengguna' ? 'show active' : '' ?>" id="profil-pengguna-tab" role="tabpanel" aria-labelledby="profil-pengguna-tab-trigger">
              <div class="profile-overview-grid">
                <div class="profile-panel">
                  <div class="profile-panel-header">
                    <div>
                      <h5 class="profile-panel-title"><?= h(tr('profile_tab_profil_pengguna','Profil Pengguna')) ?></h5>
                      <p class="profile-panel-subtitle"><?= h(tr('profile_panel_subtitle','Maklumat akaun dan identiti pengguna yang aktif dalam sistem.')) ?></p>
                    </div>
                    <span class="profile-lang-badge">
                      <i class="ri-shield-user-line"></i>
                      <?= h($isActive ? tr('profile_status_active','Aktif') : tr('profile_status_inactive','Tidak Aktif')) ?>
                    </span>
                  </div>
                  <div class="profile-panel-body">
                    <div class="profile-identity-card">
                      <div class="profile-identity-shell">
                        <div class="profile-identity-avatar-wrap position-relative">
                          <img src="<?= h($avatarUrl) ?>"
                               alt="<?= h(tr('profile_avatar_alt','Avatar pengguna')) ?>"
                               class="profile-identity-avatar"
                               onerror="this.onerror=null;this.src='<?= h(base_url('assets/images/no-image.jpg')) ?>';">
                          <span class="status-dot <?= $isActive ? 'status-active' : 'status-inactive' ?>"
                                title="<?= h($isActive ? tr('profile_status_active','Aktif') : tr('profile_status_inactive','Tidak Aktif')) ?>"></span>
                        </div>
                        <div class="profile-identity-main">
                          <div class="profile-identity-eyebrow"><?= h(tr('profile_identity_summary','Ringkasan Identiti')) ?></div>
                          <div class="profile-identity-name"><?= h($namaPenuh !== '' ? $namaPenuh : '—') ?></div>
                          <div class="profile-identity-chips">
                            <?php if ($jawGred !== ''): ?>
                              <span class="chip">
                                <i class="<?= $isStudentProfile ? 'ri-graduation-cap-line' : 'ri-briefcase-2-line' ?>"></i><?= h($jawGred) ?>
                              </span>
                            <?php endif; ?>
                            <?php if ($jabatan !== ''): ?>
                              <span class="chip">
                                <i class="ri-building-2-line"></i><?= h($jabatan) ?>
                              </span>
                            <?php endif; ?>
                          </div>
                        </div>
                        <div class="quick-actions profile-identity-actions">
                          <?php if ($identityValue !== ''): ?>
                            <button class="btn btn-sm btn-copy-staf"
                                    type="button"
                                    aria-label="<?= h($copyIdLabel) ?>"
                                    data-copy-value="<?= h($identityValue) ?>">
                              <i class="ri-file-copy-2-line me-1" aria-hidden="true"></i>
                              <?= h($copyIdLabel) ?>
                            </button>
                          <?php endif; ?>

                          <?php if ($emel !== ''): ?>
                            <button class="btn btn-sm btn-copy-email"
                                    type="button"
                                    aria-label="<?= h(tr('profile_btn_copy_email','Salin Emel')) ?>"
                                    data-copy-value="<?= h($emel) ?>">
                              <i class="ri-clipboard-line me-1" aria-hidden="true"></i>
                              <?= h(tr('profile_btn_copy_email','Salin Emel')) ?>
                            </button>
                          <?php endif; ?>
                        </div>
                      </div>
                    </div>

                    <?php if ($showIdentityStat || $showEmployeeStat): ?>
                      <div class="profile-stat-row">
                        <?php if ($showIdentityStat): ?>
                          <div class="profile-stat-card">
                            <div class="profile-stat-label">
                              <i class="ri-account-box-line"></i>
                              <?= h($idLabel) ?>
                            </div>
                            <div class="profile-stat-value"><?= h($identityValue) ?></div>
                          </div>
                        <?php endif; ?>
                        <?php if ($showEmployeeStat): ?>
                          <div class="profile-stat-card">
                            <div class="profile-stat-label">
                              <i class="ri-fingerprint-line"></i>
                              <?= h(tr('profile_no_pekerja','No. Pekerja')) ?>
                            </div>
                            <div class="profile-stat-value"><?= h($nopek) ?></div>
                          </div>
                        <?php endif; ?>
                      </div>
                    <?php endif; ?>

                    <div class="profile-detail-list">
                      <div class="profile-detail-item">
                        <div class="profile-detail-icon"><i class="<?= $isStudentProfile ? 'ri-graduation-cap-line' : 'ri-briefcase-4-line' ?>"></i></div>
                        <div>
                          <div class="profile-detail-label"><?= h($positionLabel) ?></div>
                          <div class="profile-detail-value"><?= h($jawatan !== '' ? $jawatan : $emptyPositionLabel) ?></div>
                        </div>
                      </div>
                      <div class="profile-detail-item">
                        <div class="profile-detail-icon"><i class="ri-building-line"></i></div>
                        <div>
                          <div class="profile-detail-label"><?= h($departmentLabel) ?></div>
                          <div class="profile-detail-value"><?= h($jabatan !== '' ? $jabatan : $emptyDepartmentLabel) ?></div>
                        </div>
                      </div>
                      <div class="profile-detail-item">
                        <div class="profile-detail-icon"><i class="ri-mail-line"></i></div>
                        <div>
                          <div class="profile-detail-label"><?= h(tr('profile_emel','Emel')) ?></div>
                          <div class="profile-detail-value">
                            <?php if ($emel !== ''): ?>
                              <a href="mailto:<?= h($emel) ?>" class="profile-email-link"><?= h($emel) ?></a>
                            <?php else: ?>
                              —
                            <?php endif; ?>
                          </div>
                        </div>
                      </div>
                    </div>
                  </div>
                </div>

                <div class="profile-lang-panel">
                  <div class="card border-0 profile-lang-card h-100">
                    <div class="card-header py-3 px-4">
                      <div class="d-flex align-items-center justify-content-between gap-3 flex-wrap">
                        <h5 class="mb-0 fw-semibold text-primary">
                          <i class="ri-translate-2 me-2"></i><?= h(tr('profile_lang_card_title','Pilihan Bahasa')) ?>
                        </h5>
                        <span class="profile-lang-badge">
                          <i class="ri-global-line"></i>
                          <?= h(strtoupper((string)$selectedLang)) ?>
                        </span>
                      </div>
                    </div>
                    <div class="card-body p-4">
                      <div class="mb-3">
                        <div class="profile-panel-subtitle mt-0">
                          <?= h(tr('profile_lang_session_note','Pilih bahasa paparan utama untuk akaun anda. Perubahan akan digunakan pada sesi seterusnya di seluruh modul yang menyokong pelbagai bahasa.')) ?>
                        </div>
                      </div>
                      <form method="post" class="d-flex flex-column gap-3">
                        <input type="hidden" name="profile_action" value="update_language">
                        <input type="hidden" name="csrf_token" value="<?= h($_SESSION['csrf_token'] ?? '') ?>">
                        <div>
                          <label for="f_lang" class="form-label fw-semibold"><?= h(tr('profile_lang_label','Bahasa Pilihan')) ?></label>
                          <select name="f_lang" id="f_lang" class="form-select form-select-lg">
                            <?php foreach ($activeLanguages as $languageCode): ?>
                              <?php
                                $languageLabel = match ($languageCode) {
                                  'ms' => tr('profile_lang_option_ms', 'Bahasa Melayu'),
                                  'en' => tr('profile_lang_option_en', 'English'),
                                  default => strtoupper($languageCode),
                                };
                              ?>
                              <option value="<?= h($languageCode) ?>" <?= $selectedLang === $languageCode ? 'selected' : '' ?>>
                                <?= h($languageLabel) ?>
                              </option>
                            <?php endforeach; ?>
                          </select>
                        </div>
                        <div class="profile-detail-item">
                          <div class="profile-detail-icon"><i class="ri-information-line"></i></div>
                          <div>
                            <div class="profile-detail-label"><?= h(tr('profile_note_label', 'Nota')) ?></div>
                            <div class="profile-detail-value fw-normal">
                              <?= h(tr('profile_lang_help','Bahasa ini akan digunakan untuk akaun anda selagi bahasa tersebut masih aktif dalam sistem.')) ?>
                            </div>
                          </div>
                        </div>
                        <div class="d-flex justify-content-end pt-1">
                          <button type="submit" class="btn btn-primary px-4 py-2">
                            <i class="ri-save-3-line me-2"></i><?= h(tr('profile_lang_save_btn','Simpan Bahasa')) ?>
                          </button>
                        </div>
                      </form>
                    </div>
                  </div>
                </div>
              </div>
            </div>

            <!-- Tab 2: Login Aktiviti -->
            <div class="tab-pane fade <?= $activeProfileTab === 'login-aktiviti' ? 'show active' : '' ?>" id="login-aktiviti-tab" role="tabpanel" aria-labelledby="login-aktiviti-tab-trigger">
              <div class="profile-section-heading">
                <div class="profile-section-icon"><i class="ri-shield-keyhole-line"></i></div>
                <div>
                  <h5><?= h(tr('profile_login_activity_heading', 'Keselamatan Sesi')) ?></h5>
                  <p><?= h(tr('profile_login_activity_intro', 'Semak sejarah akses, peranti yang digunakan dan tamatkan sesi aktif yang tidak lagi dikenali.')) ?></p>
                </div>
              </div>
              <div id="loginActivityLoading" class="skeleton-loader" style="display: none;">
                <div class="skeleton-row"></div>
                <div class="skeleton-row"></div>
                <div class="skeleton-row"></div>
              </div>
              
              <div class="table-responsive dt-standard">
                <table id="loginActivityTable" class="table table-bordered align-middle mb-0">
                  <thead>
                    <tr>
                      <th class="profile-table-col-no text-center"><?= h(tr('profile_table_number', 'No.')) ?></th>
                      <th class="profile-table-col-date"><?= h(tr('profile_login_date','Tarikh & Masa')) ?></th>
                      <th class="profile-table-col-ip"><?= h(tr('profile_login_ip','Alamat IP')) ?></th>
                      <th><?= h(tr('profile_login_device','Peranti')) ?></th>
                      <th class="profile-table-col-duration text-center"><?= h(tr('profile_login_duration','Tempoh')) ?></th>
                      <th class="profile-table-col-status text-center"><?= h(tr('profile_login_status','Status')) ?></th>
                      <th style="width: 100px;" class="text-center"><?= h(tr('profile_login_actions','Tindakan')) ?></th>
                    </tr>
                  </thead>
                  <tbody>
                    <!-- Data populated via AJAX (profile-login-activity.php) to avoid initial flicker -->
                  </tbody>
                </table>
                <!-- Reuse report-style AJAX loader (positioned inside table container) -->
                <div id="loginAjaxLoader" class="table-loader d-none">
                  <div class="text-center">
                    <div class="spinner-border text-primary mb-2" role="status" style="width: 3rem; height: 3rem;" aria-label="<?= h(tr('profile_loading_aria', 'Loading')) ?>">
                      <span class="visually-hidden"><?= h(tr('profile_loading_label','Loading...')) ?></span>
                    </div>
                    <div class="text-muted"><?= h(tr('profile_loading','Memuatkan…')) ?></div>
                  </div>
                </div>
              </div>
            </div>

            <!-- Tab 3: Jejak Audit -->
            <div class="tab-pane fade <?= $activeProfileTab === 'jejak-audit' ? 'show active' : '' ?>" id="jejak-audit-tab" role="tabpanel" aria-labelledby="jejak-audit-tab-trigger">
              <div class="profile-section-heading">
                <div class="profile-section-icon"><i class="ri-history-line"></i></div>
                <div>
                  <h5><?= h(tr('profile_audit_heading', 'Rekod Aktiviti Akaun')) ?></h5>
                  <p><?= h(tr('profile_audit_intro', 'Jejaki aktiviti penting yang direkodkan untuk akaun ini serta keputusan setiap tindakan.')) ?></p>
                </div>
              </div>
              <div id="auditEventsLoading" class="skeleton-loader" style="display: none;">
                <div class="skeleton-row"></div>
                <div class="skeleton-row"></div>
                <div class="skeleton-row"></div>
              </div>

              <div class="table-responsive dt-standard">
                <table id="auditEventsTable" class="table table-bordered align-middle mb-0">
                  <thead>
                    <tr>
                      <th class="profile-table-col-no text-center"><?= h(tr('profile_table_number', 'No.')) ?></th>
                      <th class="profile-table-col-date"><?= h(tr('profile_audit_date','Tarikh & Masa')) ?></th>
                        <th class="profile-table-col-user"><?= h(tr('profile_audit_user','Pengguna')) ?></th>
                      <th class="profile-table-col-ip"><?= h(tr('profile_audit_ip','Alamat IP')) ?></th>
                      <th class="profile-table-col-activity"><?= h(tr('profile_audit_activity','Aktiviti')) ?></th>
                      <th class="text-center"><?= h(tr('profile_audit_outcome','Keputusan')) ?></th>
                      <th class="text-center"><?= h(tr('profile_audit_severity','Keparahan')) ?></th>
                      <th class="text-center" style="width:120px;"><?= h(tr('profile_audit_actions','Tindakan')) ?></th>
                    </tr>
                  </thead>
                  <tbody>
                    <!-- Populated via AJAX: ajax/profile-audit-events.php -->
                  </tbody>
                </table>
              </div>
            </div>
          </div>
        </div>
        <!-- /Profile Card with Tabs -->

      </div>
    </div>
    <?php include __DIR__ . '/../includes/footer.php'; ?>
  </div>
</div>

<?php include __DIR__ . '/../includes/script.php'; ?>
<script>
  window.profilePageConfig = <?= json_encode([
      'config' => PROFILE_CONFIG,
      'permissions' => [
          'canViewAuditMetadata' => $profileAuditMetaAllowed,
      ],
      'csrfToken' => $_SESSION['csrf_token'] ?? '',
      'urls' => [
          'killSessionAjax' => base_url('ajax/profile-kill-session.php'),
          'loginActivityAjax' => base_url('ajax/profile-login-activity.php'),
          'auditEventsAjax' => base_url('ajax/profile-audit-events.php'),
          'auditEventMetaAjax' => base_url('ajax/profile-audit-event-meta.php'),
          'logout' => base_url('logout.php'),
      ],
      'i18n' => [
          'copy_empty' => tr('profile_copy_empty', 'Tiada teks untuk disalin'),
          'copy_wait' => tr('profile_copy_wait', 'Sila tunggu sebentar sebelum menyalin lagi'),
          'copy_failed' => tr('profile_copy_failed', 'Gagal menyalin teks'),
          'copied' => tr('profile_js_copied', 'Disalin'),
          'loading' => tr('profile_loading', 'Memuatkan…'),
          'table_number' => tr('profile_table_number', 'No.'),
          'dt_timeout' => tr('profile_datatables_timeout', 'DataTables failed to load within timeout'),
          'refresh_failed' => tr('profile_refresh_failed', 'Ralat memuat semula data'),
          'dt_load_failed' => tr('profile_dt_load_failed', 'Ralat memuat jadual data'),
          'dt_error_title' => tr('profile_dt_error', 'Ralat memuat data'),
          'dt_error_message' => tr('profile_dt_error_msg', 'Gagal dapatkan data.'),
          'audit_title' => tr('profile_audit_title', 'Jejak Audit'),
          'audit_event_id' => tr('profile_audit_event_id', 'Event ID'),
          'audit_summary_short' => tr('profile_audit_summary_short', 'Maklumat Ringkas'),
          'audit_no_info' => tr('profile_audit_no_info', 'Tiada maklumat'),
          'data_label' => tr('profile_data_label', 'Data'),
          'audit_tab_summary' => tr('profile_audit_tab_summary', 'Ringkasan'),
          'audit_tab_changes' => tr('profile_audit_tab_changes', 'Perubahan'),
          'audit_tab_extra' => tr('profile_audit_tab_extra', 'Maklumat Tambahan'),
          'audit_tab_raw' => tr('profile_audit_tab_raw', 'Data Mentah'),
          'audit_primary_changes' => tr('profile_audit_primary_changes', 'Perubahan Utama'),
          'audit_search_changes' => tr('profile_audit_search_changes', 'Cari perubahan...'),
          'audit_extra_info' => tr('profile_audit_extra_info', 'Maklumat Tambahan'),
          'audit_raw_data' => tr('profile_audit_raw_data', 'Data Mentah'),
          'close' => tr('profile_audit_modal_close', 'Tutup'),
          'close_label' => tr('profile_close_label', 'Close'),
          'no_changes' => tr('profile_audit_no_changes', 'Tiada perubahan'),
          'field_label' => tr('profile_audit_field', 'Medan'),
          'before_label' => tr('profile_before_label', 'Sebelum'),
          'after_label' => tr('profile_after_label', 'Selepas'),
          'before_raw_label' => tr('profile_before_raw_label', 'Before'),
          'after_raw_label' => tr('profile_after_raw_label', 'After'),
          'metadata_load_fail' => tr('profile_load_metadata_failed', 'Gagal muat metadata acara'),
          'metadata_forbidden_title' => tr('profile_metadata_forbidden_title', 'Akses Ditolak'),
          'metadata_forbidden_text' => tr('profile_metadata_forbidden_text', 'Hanya Super Admin dibenarkan melihat metadata audit.'),
          'swal_ok' => tr('profile_swal_ok', 'OK'),
          'audit_changes_separator' => tr('profile_audit_changes_separator', '-- Changes --'),
          'audit_download_failed' => tr('profile_audit_download_failed', 'Gagal memuat turun fail JSON'),
          'audit_stat_meta' => tr('profile_audit_stat_meta', 'Meta'),
          'audit_stat_sets' => tr('profile_audit_stat_sets', 'Set'),
          'audit_stat_changes' => tr('profile_audit_stat_changes', 'Beza'),
          'kill_error_no_session' => tr('profile_login_kill_error_no_session', 'ID sesi tidak sah'),
          'kill_confirm_title' => tr('profile_login_kill_confirm_title', 'Tamatkan Sesi?'),
          'kill_confirm_text' => tr('profile_login_kill_confirm_text', 'Anda pasti mahu tamatkan sesi ini? Pengguna akan dipaksa log keluar.'),
          'kill_confirm_yes' => tr('profile_login_kill_confirm_yes', 'Ya, Tamatkan'),
          'kill_confirm_no' => tr('profile_login_kill_confirm_no', 'Batal'),
          'kill_force_title' => tr('profile_login_kill_force_title', 'Sesi anda akan ditamatkan'),
          'kill_force_text' => tr('profile_login_kill_force_text', 'Anda akan dilog keluar dalam'),
          'kill_success_title' => tr('profile_login_kill_success', 'Sesi berjaya ditamatkan'),
          'kill_success_text' => tr('profile_login_kill_success_text', 'Sesi telah ditamatkan'),
          'kill_error_title' => tr('profile_login_kill_error', 'Gagal tamatkan sesi'),
          'kill_error_network' => tr('profile_login_kill_error_network', 'Ralat rangkaian. Sila cuba lagi.'),
      ],
      'datatables' => [
          'loginActivityLanguage' => [
              'lengthMenu' => tr('profile_dt_show', 'Papar') . ' _MENU_ ' . tr('profile_dt_records', 'rekod'),
              'search' => tr('profile_dt_search', 'Cari') . ':',
              'emptyTable' => tr('profile_dt_no_records', 'Tiada rekod ditemui'),
              'zeroRecords' => tr('profile_dt_no_records', 'Tiada rekod ditemui'),
              'info' => tr('profile_dt_info', 'Paparan _START_ hingga _END_ daripada _TOTAL_ rekod'),
              'infoEmpty' => tr('profile_dt_info_empty', 'Paparan 0 hingga 0 daripada 0 rekod'),
              'infoFiltered' => '(' . tr('profile_dt_filtered', 'ditapis daripada _MAX_ jumlah rekod') . ')',
              'paginate' => [
                  'previous' => tr('profile_dt_previous', 'Sebelum'),
                  'next' => tr('profile_dt_next', 'Seterusnya'),
              ],
          ],
          'auditEventsLanguage' => [
              'lengthMenu' => tr('profile_dt_show', 'Papar') . ' _MENU_ ' . tr('profile_dt_records', 'rekod'),
              'search' => tr('profile_dt_search', 'Cari') . ':',
              'emptyTable' => tr('profile_dt_no_records', 'Tiada rekod ditemui'),
              'zeroRecords' => tr('profile_dt_no_records', 'Tiada rekod ditemui'),
              'info' => tr('profile_dt_info', 'Paparan _START_ hingga _END_ daripada _TOTAL_ rekod'),
              'infoEmpty' => tr('profile_dt_info_empty', 'Paparan 0 hingga 0 daripada 0 rekod'),
              'infoFiltered' => '(' . tr('profile_dt_filtered', 'ditapis daripada _MAX_ jumlah rekod') . ')',
              'paginate' => [
                  'previous' => tr('profile_dt_previous', 'Sebelum'),
                  'next' => tr('profile_dt_next', 'Seterusnya'),
              ],
          ],
      ],
  ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>;
</script>
<script src="<?= base_url('assets/js/helpers/datatables-standard.js') ?>?v=<?= h($version) ?>"></script>
<script src="<?= base_url('assets/js/pages/profile.js') ?>?v=<?= h($version) ?>"></script>
<div class="toast-lite" aria-live="polite" aria-atomic="true"></div>
</body>
</html>
