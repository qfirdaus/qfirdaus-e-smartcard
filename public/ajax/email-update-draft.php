<?php
/**
 * IQS FRAMEWORK CORE FILE
 *
 * READ ONLY for downstream project programmers.
 * Do not modify this file directly in template or cloned projects.
 * Custom changes must be implemented in project-specific files
 * or approved extension points.
 */declare(strict_types=1);

ob_start();

require_once __DIR__ . '/../includes/init.php';
require_once __DIR__ . '/_helpers.php';
require_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonErrorResponse((string) __('email_error_invalid_method'), 405);
}

if (!isValidCsrfToken()) {
    jsonErrorResponse((string) __('email_error_invalid_csrf'), 419);
}

$draftID = (int) ($_POST['draft_id'] ?? 0);
$stafID = trim((string) ($_SESSION['f_stafID'] ?? ''));
$tarafJawatan = trim((string) ($_POST['taraf_jawatan'] ?? ''));
$telPejabat = trim((string) ($_POST['no_tel_pejabat'] ?? ''));
$telBimbit = trim((string) ($_POST['no_tel_bimbit'] ?? ''));
$altEmail = trim((string) ($_POST['alternative_email'] ?? ''));
$emailDipohon = trim((string) ($_POST['email_dipohon'] ?? ''));
$tujuan = trim((string) ($_POST['tujuan'] ?? ''));

if ($draftID <= 0 || $stafID === '') {
    jsonErrorResponse((string) __('email_error_invalid_draft'), 400);
}

if ($tarafJawatan === '' || $telPejabat === '' || $telBimbit === '' || $altEmail === '') {
    jsonErrorResponse((string) __('email_error_incomplete_applicant'), 422);
}

try {
    $pdo = Database::getInstance('mysql')->getConnection();
    $exists = $pdo->prepare(
        "
        SELECT f_permohonanID
        FROM tbl_permohonan_email
        WHERE f_permohonanID = :id
          AND f_stafID = :staf
          AND f_status = 'DRAFT'
        LIMIT 1
        "
    );
    $exists->execute([
        ':id' => $draftID,
        ':staf' => $stafID,
    ]);

    if (!$exists->fetchColumn()) {
        jsonErrorResponse((string) __('email_error_draft_not_found'), 404);
    }

    $stmt = $pdo->prepare(
        "
        UPDATE tbl_permohonan_email
        SET
            f_taraf_jawatan = :jawatan,
            f_tel_pejabat = :tel_pej,
            f_tel_bimbit = :tel_bimbit,
            f_email_alternatif = :email_alt,
            f_email_dipohon = :email_dipohon,
            f_tujuan = :tujuan,
            f_step = :step
        WHERE f_permohonanID = :id
          AND f_stafID = :staf
        "
    );
    $stmt->execute([
        ':jawatan' => $tarafJawatan,
        ':tel_pej' => $telPejabat,
        ':tel_bimbit' => $telBimbit,
        ':email_alt' => $altEmail,
        ':email_dipohon' => $emailDipohon,
        ':tujuan' => $tujuan,
        ':step' => ($emailDipohon !== '' && $tujuan !== '') ? 2 : 1,
        ':id' => $draftID,
        ':staf' => $stafID,
    ]);

    jsonSuccessResponse();
} catch (Throwable $e) {
    error_log('[email-update-draft] ' . $e->getMessage());
    jsonErrorResponse((string) __('email_error_generic'), 500);
}