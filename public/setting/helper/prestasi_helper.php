<?php
/**
 * IQS FRAMEWORK CORE FILE
 *
 * READ ONLY for downstream project programmers.
 * Do not modify this file directly in template or cloned projects.
 * Custom changes must be implemented in project-specific files
 * or approved extension points.
 *//**
 * Prestasi Helper Functions
 * 
 * Centralized helper functions for the prestasi (performance appraisal) module.
 * These functions are used to format data, calculate averages, and render table rows.
 * 
 * @package Prestasi
 * @version 2.0
 */
declare(strict_types=1);

if (defined('PRESTASI_HELPER_INCLUDED')) return;
define('PRESTASI_HELPER_INCLUDED', true);

// Load constants if not already loaded
if (!defined('PRESTASI_DECIMAL_PLACES')) {
  require_once __DIR__ . '/../constants/prestasi_constants.php';
}

/**
 * Format markah (score) to 2 decimal places
 * 
 * Returns empty string if value is null, empty, zero, or not numeric.
 * 
 * @param mixed $v Value to format (can be string, int, float, or null)
 * @return string Formatted value with 2 decimal places, or empty string
 */
function prestasi_fmt2($v): string {
  if ($v === null || $v === '' || !is_numeric($v)) return '';
  $n = (float)$v;
  if ($n == 0.0) return '';
  return number_format($n, PRESTASI_DECIMAL_PLACES, '.', '');
}

/**
 * Convert month number (1-12) to translated text
 * 
 * Uses translation keys in format 'prestasi_month_01', 'prestasi_month_02', etc.
 * Falls back to numeric value if translation not found.
 * 
 * @param int|string $mm Month number (1-12) or zero-padded string ('01'-'12')
 * @return string Translated month name or original value if translation not available
 */
function prestasi_month_to_text($mm): string {
  $mm = str_pad((string)$mm, PRESTASI_MONTH_PAD_LENGTH, '0', STR_PAD_LEFT);
  $key = 'prestasi_month_' . $mm;
  $val = __($key);
  return ($val === $key || $val === '') ? $mm : $val;
}

/**
 * Normalize staff ID by removing leading zeros from the numeric part
 * 
 * Example: '0123-45' -> '123', '0001-00' -> '1'
 * Used for comparing staff IDs to determine if PPP and PPK are the same person.
 * 
 * @param string $v Staff ID in format 'XXXX-XX'
 * @return string Normalized ID (leading zeros removed)
 */
function prestasi_norm_id(string $v): string {
  $left = explode('-', $v)[0] ?? '';
  return ltrim($left, '0');
}

/**
 * Calculate average (purata) score based on PPP/PPK marks
 * 
 * Logic:
 * - If same person (PPP == PPK): use whichever mark exists (PPP takes priority)
 * - If different persons: use average of both marks, or single mark if only one exists
 * - Fallback to stored 'f_purata' value if no marks available
 * 
 * @param array $row Data row containing 'f_purata' as fallback
 * @param bool $samePerson Whether PPP and PPK are the same person
 * @param float|null $pppMark PPP mark (0-100) or null
 * @param float|null $ppkMark PPK mark (0-100) or null
 * @return string Formatted average score with 2 decimal places, or empty string
 */
function prestasi_calculate_purata(array $row, bool $samePerson, ?float $pppMark, ?float $ppkMark): string {
  if ($samePerson) {
    if ($pppMark !== null) return number_format($pppMark, PRESTASI_DECIMAL_PLACES, '.', '');
    if ($ppkMark !== null) return number_format($ppkMark, PRESTASI_DECIMAL_PLACES, '.', '');
  } else {
    if ($pppMark !== null && $ppkMark !== null) {
      return number_format((($pppMark + $ppkMark) / 2), PRESTASI_DECIMAL_PLACES, '.', '');
    }
    if ($pppMark !== null) return number_format($pppMark, PRESTASI_DECIMAL_PLACES, '.', '');
    if ($ppkMark !== null) return number_format($ppkMark, PRESTASI_DECIMAL_PLACES, '.', '');
  }
  
  // Fallback to stored purata
  if (is_numeric($row['f_purata'] ?? '')) {
    return number_format((float)$row['f_purata'], PRESTASI_DECIMAL_PLACES, '.', '');
  }
  return '';
}

/**
 * Render single table row untuk prestasi list
 * @param array $row Data row
 * @param string $tahunDipilih Tahun yang dipilih
 * @param int $rowIndex Index untuk numbering (1-based)
 * @param string|null $userRole User role untuk determine edit permission (optional)
 * @return string HTML untuk <tr>...</tr>
 */
function prestasi_render_row(array $row, string $tahunDipilih, int $rowIndex, ?string $userRole = null): string {
  $nama   = $row['nama'] ?? '';
  $nopek  = $row['nopekerja'] ?? '';
  $label  = trim($nopek) !== '' ? "{$nama} ({$nopek})" : $nama;

  $pppId  = (string)($row['f_ppp'] ?? '');
  $ppkId  = (string)($row['f_ppk'] ?? '');
  $pppRaw = isset($row['f_mark_ppp']) ? trim((string)$row['f_mark_ppp']) : '';
  $ppkRaw = isset($row['f_mark_ppk']) ? trim((string)$row['f_mark_ppk']) : '';

  $hasPPP = ($pppRaw !== '' && is_numeric($pppRaw) && (float)$pppRaw != 0.0);
  $hasPPK = ($ppkRaw !== '' && is_numeric($ppkRaw) && (float)$ppkRaw != 0.0);

  $pppMark = $hasPPP ? (float)$pppRaw : null;
  $ppkMark = $hasPPK ? (float)$ppkRaw : null;

  $pppDisp = prestasi_fmt2($pppRaw);
  $ppkDisp = prestasi_fmt2($ppkRaw);

  $samePerson = ($pppId !== '' && $ppkId !== '' && prestasi_norm_id($pppId) === prestasi_norm_id($ppkId));

  $bulan   = str_pad((string)($row['bulan_gaji'] ?? ''), 2, '0', STR_PAD_LEFT);
  $bulanT  = $bulan ? ($bulan . ' - ' . prestasi_month_to_text($bulan)) : '';

  $taraf   = trim((string)($row['taraf_text'] ?? ''));
  $jaw     = trim((string)($row['jawatan'] ?? ''));
  $tipNama = trim($taraf . ($taraf && $jaw ? ' — ' : '') . $jaw);

  $purata = prestasi_calculate_purata($row, $samePerson, $pppMark, $ppkMark);

  $isComplete = $samePerson ? $hasPPP : ($hasPPP && $hasPPK);
  $rowClass   = $isComplete ? '' : 'row-incomplete';

  $statusHtml = $isComplete
    ? '<span class="badge bg-success-subtle text-success border border-success-subtle">' . h(__('prestasi_badge_complete')) . '</span>'
    : '<span class="badge bg-danger-subtle text-danger border border-danger-subtle">' . h(__('prestasi_badge_incomplete')) . '</span>';

  $showRemPPP = $showRemPPK = false;
  if (!$isComplete) {
    if ($samePerson) {
      $showRemPPP = (!$hasPPP && $pppId !== '');
    } else {
      $showRemPPP = (!$hasPPP && $pppId !== '');
      $showRemPPK = (!$hasPPK && $ppkId !== '');
    }
  }

  $pppCellHtml = h($pppDisp);
  if ($pppDisp === '') {
    if ($samePerson) {
      // Jika PPP dan PPK sama, PPP tunjuk badge dengan tooltip "penilaian 1 peringkat"
      $pppCellHtml =
        '<span class="badge bg-info-subtle text-info border border-info-subtle" ' .
        'data-bs-toggle="tooltip" data-bs-custom-class="tt-nowrap" ' .
        'title="' . h(__('prestasi_tt_same_person_single_tier')) . '">' .
        h(__('prestasi_badge_not_filled')) . '</span>';
    } else {
      // Jika berbeza, tunjuk badge "belum isi" dengan tooltip "penilaian dua peringkat"
      $pppCellHtml =
        '<span class="badge bg-info-subtle text-info border border-info-subtle" ' .
        'data-bs-toggle="tooltip" data-bs-custom-class="tt-nowrap" ' .
        'title="' . h(__('prestasi_tt_ppp_not_filled_two_tier')) . '">' .
        h(__('prestasi_badge_not_filled')) . '</span>';
    }
  }

  $ppkCellHtml = h($ppkDisp);
  if ($ppkDisp === '') {
    if ($samePerson) {
      // Jika PPP dan PPK sama, PPK tunjuk badge khas dengan tooltip yang sama dengan PPP
      $ppkCellHtml =
        '<span class="badge bg-warning-subtle text-warning border border-warning-subtle" ' .
        'data-bs-toggle="tooltip" data-bs-custom-class="tt-nowrap" ' .
        'title="' . h(__('prestasi_tt_same_person_single_tier')) . '">' .
        h(__('prestasi_badge_same_person_empty')) . '</span>';
    } else {
      // Jika berbeza, tunjuk badge "belum isi" seperti biasa
      $ppkCellHtml =
        '<span class="badge bg-info-subtle text-info border border-info-subtle" ' .
        'data-bs-toggle="tooltip" data-bs-custom-class="tt-nowrap" ' .
        'title="' . h(__('prestasi_tt_ppk_not_filled_two_tier')) . '">' .
        h(__('prestasi_badge_not_filled')) . '</span>';
    }
  }

  $hasId = !empty($row['f_penilaiID']);
  
  // Sentiasa benarkan kemaskini (server-side simpanan masih akan sahkan semula)
  $canEdit = true;

  // Fallback labels for PPP/PPK names if not provided in data
  $pppNamaPenuh = trim((string)($row['ppp_nama_penuh'] ?? ''));
  $ppkNamaPenuh = trim((string)($row['ppk_nama_penuh'] ?? ''));
  if ($pppNamaPenuh === '' && $pppId !== '') {
    if (function_exists('getStafLabelById')) {
      $tmp = getStafLabelById($pppId);
      if ($tmp !== null && trim((string)$tmp) !== '') $pppNamaPenuh = trim((string)$tmp);
    }
    if ($pppNamaPenuh === '') $pppNamaPenuh = $pppId;
  }
  if ($ppkNamaPenuh === '' && $ppkId !== '') {
    if (function_exists('getStafLabelById')) {
      $tmp = getStafLabelById($ppkId);
      if ($tmp !== null && trim((string)$tmp) !== '') $ppkNamaPenuh = trim((string)$tmp);
    }
    if ($ppkNamaPenuh === '') $ppkNamaPenuh = $ppkId;
  }

  // Labels for modal/tooltips: ensure number is shown if name lacks it
  $pppLabel = $pppNamaPenuh;
  if ($pppLabel !== '' && $pppId !== '' && stripos($pppLabel, $pppId) === false) {
    $pppLabel .= ' (' . $pppId . ')';
  }
  $ppkLabel = $ppkNamaPenuh;
  if ($ppkLabel !== '' && $ppkId !== '' && stripos($ppkLabel, $ppkId) === false) {
    $ppkLabel .= ' (' . $ppkId . ')';
  }

  ob_start();
  ?>
<tr class="<?= $rowClass ?>"
    data-penilaiid="<?= h($row['f_penilaiID'] ?? '') ?>"
    data-stafid="<?= h($row['f_stafID'] ?? '') ?>"
    data-tahun="<?= h($row['f_tahun'] ?? $tahunDipilih) ?>"
    data-kodjbt="<?= h($row['f_kodjbt'] ?? '') ?>"
    data-kodjwt="<?= h($row['kdjwt'] ?? $row['f_kodjwt'] ?? '') ?>"
    data-nama="<?= h($nama) ?>"
    data-nopek="<?= h($row['f_nopekerja_raw'] ?? $nopek) ?>"
    data-jawatan="<?= h($row['jawatan'] ?? '') ?>"
    data-gred="<?= h($row['gred'] ?? '') ?>"
    data-ppp="<?= h($pppId) ?>"
    data-ppk="<?= h($ppkId) ?>"
    data-same-person="<?= $samePerson ? '1' : '0' ?>"
    data-ppp-nama="<?= h($pppLabel) ?>"
    data-ppk-nama="<?= h($ppkLabel) ?>"
    data-ppp-email="<?= h($row['ppp_email'] ?? '') ?>"
    data-ppk-email="<?= h($row['ppk_email'] ?? '') ?>"
    data-mppp="<?= h($pppDisp) ?>"
    data-mppk="<?= h($ppkDisp) ?>"
    data-purata="<?= h($purata) ?>">
  <td class="text-center"><?= $rowIndex ?></td>
  <td>
    <?= h($label) ?>
    <?php if ($tipNama): ?>
      <i class="ri-information-line icon-tip" data-bs-toggle="tooltip" data-bs-custom-class="tt-nowrap" title="<?= h($tipNama) ?>"></i>
    <?php endif; ?>
  </td>
  <td class="text-center">
    <?= $pppCellHtml ?>
    <?php if (!empty($row['ppp_nama_penuh'])): ?>
      <i class="ri-information-line icon-tip" data-bs-toggle="tooltip" data-bs-custom-class="tt-nowrap" title="<?= h($row['ppp_nama_penuh']) ?>"></i>
    <?php endif; ?>
  </td>
  <td class="text-center">
    <?= $ppkCellHtml ?>
    <?php if (!empty($row['ppk_nama_penuh'])): ?>
      <i class="ri-information-line icon-tip" data-bs-toggle="tooltip" data-bs-custom-class="tt-nowrap" title="<?= h($row['ppk_nama_penuh']) ?>"></i>
    <?php endif; ?>
  </td>
  <td class="text-center">
    <?php if ($purata === ''): ?>
      <span class="badge bg-info-subtle text-info border border-info-subtle"><?= h(__('prestasi_badge_tiada')) ?></span>
    <?php else: ?>
      <?= h($purata) ?>
    <?php endif; ?>
  </td>
  <td><?= h($bulanT) ?></td>
  <td class="text-center"><?= $statusHtml ?></td>
  <td>
    <div class="action-gap">
      <?php if ($canEdit): ?>
        <button type="button" class="btn btn-icon btn-edit" data-bs-toggle="tooltip" title="<?= h(__('prestasi_tt_edit')) ?>">
          <i class="ri-edit-2-line"></i>
        </button>
      <?php endif; ?>

      <?php if ($showRemPPP): ?>
        <button type="button" class="btn btn-icon btn-reminder"
          data-role="PPP"
          data-email="<?= h($row['ppp_email'] ?? '') ?>"
          data-targetnama="<?= h($row['ppp_nama_penuh'] ?? '') ?>"
          data-bs-toggle="tooltip" title="<?= h(__('prestasi_tt_reminder_ppp')) ?>">
          <i class="ri-mail-send-line"></i>
        </button>
      <?php endif; ?>

      <?php if ($showRemPPK): ?>
        <button type="button" class="btn btn-icon btn-reminder"
          data-role="PPK"
          data-email="<?= h($row['ppk_email'] ?? '') ?>"
          data-targetnama="<?= h($row['ppk_nama_penuh'] ?? '') ?>"
          data-bs-toggle="tooltip" title="<?= h(__('prestasi_tt_reminder_ppk')) ?>">
          <i class="ri-mail-send-line"></i>
        </button>
      <?php endif; ?>
    </div>
  </td>
</tr>
<?php
  return (string)ob_get_clean();
}

/**
 * Render multiple rows untuk prestasi list
 * @param array $senarai Array of data rows
 * @param string $tahunDipilih Tahun yang dipilih
 * @param string|null $userRole User role untuk determine edit permission (optional)
 * @return string HTML untuk semua <tr>...</tr>
 */
function prestasi_render_rows(array $senarai, string $tahunDipilih, ?string $userRole = null): string {
  $html = '';
  $i = 1;
  foreach ($senarai as $row) {
    $html .= prestasi_render_row($row, $tahunDipilih, $i++, $userRole);
  }
  return $html;
}
