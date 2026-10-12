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

$stafID = trim((string) ($_SESSION['f_stafID'] ?? ''));
$tarafJawatan = trim((string) ($_POST['taraf_jawatan'] ?? ''));
$telPejabat = trim((string) ($_POST['no_tel_pejabat'] ?? ''));
$telBimbit = trim((string) ($_POST['no_tel_bimbit'] ?? ''));
$altEmail = trim((string) ($_POST['alternative_email'] ?? ''));
$emailDipohon = trim((string) ($_POST['email_dipohon'] ?? ''));
$tujuan = trim((string) ($_POST['tujuan'] ?? ''));

if ($stafID === '') {
    jsonErrorResponse((string) __('email_error_invalid_staff'), 401);
}

if ($tarafJawatan === '' || $telPejabat === '' || $telBimbit === '' || $altEmail === '') {
    jsonErrorResponse((string) __('email_error_incomplete_applicant'), 422);
}

try {
    $pdo = Database::getInstance('mysql')->getConnection();
    $stmt = $pdo->prepare(
        "
        INSERT INTO tbl_permohonan_email
            (
                f_stafID,
                f_taraf_jawatan,
                f_tel_pejabat,
                f_tel_bimbit,
                f_email_alternatif,
                f_email_dipohon,
                f_tujuan,
                f_step,
                f_status
            )
        VALUES
            (
                :staf,
                :jawatan,
                :tel_pej,
                :tel_bimbit,
                :email_alt,
                :email_dipohon,
                :tujuan,
                :step,
                'DRAFT'
            )
        "
    );
    $stmt->execute([
        ':staf' => $stafID,
        ':jawatan' => $tarafJawatan,
        ':tel_pej' => $telPejabat,
        ':tel_bimbit' => $telBimbit,
        ':email_alt' => $altEmail,
        ':email_dipohon' => $emailDipohon,
        ':tujuan' => $tujuan,
        ':step' => ($emailDipohon !== '' && $tujuan !== '') ? 2 : 1,
    ]);

    jsonSuccessResponse(['draft_id' => (int) $pdo->lastInsertId()]);
} catch (Throwable $e) {
    error_log('[email-create-draft] ' . $e->getMessage());
    jsonErrorResponse((string) __('email_error_generic'), 500);
}