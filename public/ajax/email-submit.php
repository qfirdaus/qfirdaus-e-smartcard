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
require_once __DIR__ . '/../classes/Mailer.php';
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
if ($draftID <= 0 || $stafID === '') {
    jsonErrorResponse((string) __('email_error_invalid_draft'), 400);
}

try {
    $pdo = Database::getInstance('mysql')->getConnection();

    $stmt = $pdo->prepare(
        "
        SELECT *
        FROM v_permohonan_email
        WHERE f_permohonanID = :id
          AND f_stafID = :staf
        LIMIT 1
        "
    );
    $stmt->execute([
        ':id' => $draftID,
        ':staf' => $stafID,
    ]);
    $data = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$data) {
        jsonErrorResponse((string) __('email_error_application_not_found'), 404);
    }

    if (trim((string) ($data['f_email_dipohon'] ?? '')) === '' || trim((string) ($data['f_tujuan'] ?? '')) === '') {
        jsonErrorResponse((string) __('email_error_incomplete_application'), 422);
    }

    $year = date('Y');
    $countStmt = $pdo->prepare(
        "
        SELECT COUNT(*)
        FROM tbl_permohonan_email
        WHERE YEAR(f_created_at) = :year
        "
    );
    $countStmt->execute([':year' => $year]);
    $running = (int) $countStmt->fetchColumn() + 1;
    $noPermohonan = 'UPNM.EMAIL.' . $year . '-' . str_pad((string) $running, 3, '0', STR_PAD_LEFT);

    $updateStmt = $pdo->prepare(
        "
        UPDATE tbl_permohonan_email
        SET
            f_no_permohonan = :no,
            f_status = 'SUBMITTED',
            f_step = 3,
            f_tarikh_hantar = NOW()
        WHERE f_permohonanID = :id
          AND f_stafID = :staf
          AND f_status = 'DRAFT'
        "
    );
    $updateStmt->execute([
        ':no' => $noPermohonan,
        ':id' => $draftID,
        ':staf' => $stafID,
    ]);

    if ($updateStmt->rowCount() < 1) {
        jsonErrorResponse('Draf tidak lagi tersedia untuk dihantar.', 409);
    }

    $vars = [
        'no_permohonan' => $noPermohonan,
        'nama' => $data['f_nama'] ?? '',
        'staf_id' => $data['f_stafID'] ?? '',
        'email_dipohon' => $data['f_email_dipohon'] ?? '',
        'tujuan' => $data['f_tujuan'] ?? '',
        'systemName' => 'e-Facility',
        'submittedAt' => date('d/m/Y H:i'),
    ];

    $mailer = Mailer::fromConfig($pdo);
    $picEmail = 'normazlina@upnm.edu.my';

    [$htmlAdmin, $textAdmin] = Mailer::render('permohonan_emel_admin', $vars);
    if (trim($htmlAdmin) === '' && trim($textAdmin) === '') {
        $htmlAdmin = '<p>' . htmlspecialchars((string) __('email_mail_admin_intro'), ENT_QUOTES, 'UTF-8') . '</p>'
            . '<p><strong>' . htmlspecialchars((string) __('email_mail_label_application_no'), ENT_QUOTES, 'UTF-8') . ':</strong> ' . htmlspecialchars($noPermohonan, ENT_QUOTES, 'UTF-8') . '</p>'
            . '<p><strong>' . htmlspecialchars((string) __('email_mail_label_name'), ENT_QUOTES, 'UTF-8') . ':</strong> ' . htmlspecialchars((string) ($vars['nama'] ?? ''), ENT_QUOTES, 'UTF-8') . '</p>'
            . '<p><strong>' . htmlspecialchars((string) __('email_mail_label_requested_email'), ENT_QUOTES, 'UTF-8') . ':</strong> ' . htmlspecialchars((string) ($vars['email_dipohon'] ?? ''), ENT_QUOTES, 'UTF-8') . '</p>'
            . '<p><strong>' . htmlspecialchars((string) __('email_mail_label_purpose'), ENT_QUOTES, 'UTF-8') . ':</strong> ' . nl2br(htmlspecialchars((string) ($vars['tujuan'] ?? ''), ENT_QUOTES, 'UTF-8')) . '</p>';
        $textAdmin = sprintf(
            "%s\n%s: %s\n%s: %s\n%s: %s\n%s: %s",
            (string) __('email_mail_admin_intro'),
            (string) __('email_mail_label_application_no'),
            $noPermohonan,
            (string) __('email_mail_label_name'),
            (string) ($vars['nama'] ?? ''),
            (string) __('email_mail_label_requested_email'),
            (string) ($vars['email_dipohon'] ?? ''),
            (string) __('email_mail_label_purpose'),
            (string) ($vars['tujuan'] ?? '')
        );
    }

    $sendAdmin = $mailer->send(
        $picEmail,
        sprintf((string) __('email_mail_admin_subject'), $noPermohonan),
        $htmlAdmin,
        $textAdmin
    );

    if ($sendAdmin) {
        $stmt = $pdo->prepare('UPDATE tbl_permohonan_email SET f_email_admin_sent = 1 WHERE f_permohonanID = :id');
        $stmt->execute([':id' => $draftID]);
    }

    if (!$sendAdmin) {
        jsonExceptionResponse(
            $mailer->lastFailureAsExternalServiceException(sprintf((string) __('email_submit_admin_mail_failed'), $mailer->getLastError())),
            sprintf((string) __('email_submit_admin_mail_failed'), $mailer->getLastError()),
            ['endpoint' => 'email-submit', 'stage' => 'admin_notification']
        );
    }

    $userEmail = trim((string) ($data['f_email_alternatif'] ?? ''));
    if ($userEmail !== '') {
        [$htmlUser, $textUser] = Mailer::render('permohonan_emel_pemohon', $vars);
        if (trim($htmlUser) === '' && trim($textUser) === '') {
            $htmlUser = '<p>' . htmlspecialchars((string) __('email_mail_user_intro'), ENT_QUOTES, 'UTF-8') . '</p>'
                . '<p><strong>' . htmlspecialchars((string) __('email_mail_label_application_no'), ENT_QUOTES, 'UTF-8') . ':</strong> ' . htmlspecialchars($noPermohonan, ENT_QUOTES, 'UTF-8') . '</p>'
                . '<p><strong>' . htmlspecialchars((string) __('email_mail_label_requested_email'), ENT_QUOTES, 'UTF-8') . ':</strong> ' . htmlspecialchars((string) ($vars['email_dipohon'] ?? ''), ENT_QUOTES, 'UTF-8') . '</p>';
            $textUser = sprintf(
                "%s\n%s: %s\n%s: %s",
                (string) __('email_mail_user_intro'),
                (string) __('email_mail_label_application_no'),
                $noPermohonan,
                (string) __('email_mail_label_requested_email'),
                (string) ($vars['email_dipohon'] ?? '')
            );
        }

        $sendUser = $mailer->send(
            $userEmail,
            sprintf((string) __('email_mail_user_subject'), $noPermohonan),
            $htmlUser,
            $textUser
        );
        if ($sendUser) {
            $stmt = $pdo->prepare('UPDATE tbl_permohonan_email SET f_email_user_sent = 1 WHERE f_permohonanID = :id');
            $stmt->execute([':id' => $draftID]);
        } else {
            FrameworkExceptionHandler::log($mailer->lastFailureAsExternalServiceException('Applicant notification email could not be sent.'), [
                'endpoint' => 'email-submit',
                'stage' => 'applicant_notification',
            ]);
        }
    }

    jsonSuccessResponse(['message' => (string) __('formList_submit_success_text')]);
} catch (ExternalServiceException $e) {
    jsonExceptionResponse($e, (string) __('email_error_generic'), [
        'endpoint' => 'email-submit',
    ]);
} catch (Throwable $e) {
    error_log('[email-submit] ' . $e->getMessage());
    jsonErrorResponse((string) __('email_error_generic'), 500);
}
