<?php
/**
 * IQS FRAMEWORK CORE FILE
 *
 * READ ONLY for downstream project programmers.
 * Do not modify this file directly in template or cloned projects.
 * Custom changes must be implemented in project-specific files
 * or approved extension points.
 */$avatarUrl = $avatarUrl ?? ($profileView['avatar_url'] ?? base_url('assets/images/no-image.jpg'));
$namaPenuh = $namaPenuh ?? ($profileView['nama_penuh'] ?? '');
$jawatan = $jawatan ?? ($profileView['jawatan'] ?? '');
$gred = $gred ?? ($profileView['gred'] ?? '');
$jabatan = $jabatan ?? ($profileView['jabatan'] ?? '');
$stafID = $stafID ?? ($profileView['stafID'] ?? '');
$emel = $emel ?? ($profileView['emel'] ?? '');
$jawGred = $jawGred ?? trim($jawatan . ($gred ? ' • ' . $gred : ''));
$isActive = (bool)($isActive ?? false);
$categoryUser = strtoupper(trim((string)(
  $profileView['categoryUser']
  ?? $profileView['f_categoryUser']
  ?? $_SESSION['f_categoryUser']
  ?? $_SESSION['user']['f_categoryUser']
  ?? $_SESSION['auth_type']
  ?? ''
)));
$isStudentProfile = in_array($categoryUser, ['PELAJAR', 'STUDENT'], true) || !empty($_SESSION['student_profile']);
$copyIdLabel = $isStudentProfile
  ? h(tr('profile_btn_copy_no_matrik', 'Salin No. Matrik'))
  : h(tr('profile_btn_copy_no_staf', 'Salin No. Staf'));
?>
<div class="profile-hero">
  <div class="profile-hero-shell">
    <div class="profile-identity-avatar-wrap position-relative">
      <img src="<?= h($avatarUrl) ?>"
           alt="<?= h(tr('profile_avatar_alt', 'Avatar pengguna')) ?>"
           class="profile-identity-avatar"
           onerror="this.onerror=null;this.src='<?= h(base_url('assets/images/no-image.jpg')) ?>';">
      <span class="status-dot <?= $isActive ? 'status-active' : 'status-inactive' ?>"
            title="<?= h($isActive ? tr('profile_status_active', 'Aktif') : tr('profile_status_inactive', 'Tidak Aktif')) ?>"></span>
    </div>
    <div class="profile-hero-main">
      <div class="profile-hero-eyebrow"><?= h($isStudentProfile ? tr('profile_student_card_label', 'Profil Pelajar') : tr('profile_user_card_label', 'Profil Pengguna')) ?></div>
      <div class="profile-hero-heading">
        <span class="display-name"><?= h($namaPenuh !== '' ? $namaPenuh : '—') ?></span>
      </div>
      <div class="subline">
        <?php if ($jawGred !== ''): ?>
          <span class="chip"><i class="ri-briefcase-2-line"></i><?= h($jawGred) ?></span>
        <?php endif; ?>
        <?php if ($jabatan !== ''): ?>
          <span class="chip"><i class="ri-building-2-line"></i><?= h($jabatan) ?></span>
        <?php endif; ?>
      </div>
    </div>
    <div class="quick-actions profile-hero-actions">
      <?php if ($stafID !== ''): ?>
        <button class="btn btn-sm btn-copy-staf"
                type="button"
                aria-label="<?= $copyIdLabel ?>"
                data-copy-value="<?= h($stafID) ?>">
          <i class="ri-file-copy-2-line me-1" aria-hidden="true"></i>
          <?= $copyIdLabel ?>
        </button>
      <?php endif; ?>
      <?php if ($emel !== ''): ?>
        <button class="btn btn-sm btn-copy-email"
                type="button"
                aria-label="<?= h(tr('profile_btn_copy_email', 'Salin Emel')) ?>"
                data-copy-value="<?= h($emel) ?>">
          <i class="ri-clipboard-line me-1" aria-hidden="true"></i>
          <?= h(tr('profile_btn_copy_email', 'Salin Emel')) ?>
        </button>
      <?php endif; ?>
    </div>
  </div>
</div>
