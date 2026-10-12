<?php
/**
 * IQS FRAMEWORK CORE FILE
 *
 * READ ONLY for downstream project programmers.
 * Do not modify this file directly in template or cloned projects.
 * Custom changes must be implemented in project-specific files
 * or approved extension points.
 */declare(strict_types=1);

require_once __DIR__ . '/../includes/init.php';
require_login();
require_once __DIR__ . '/_helpers.php';
require_once __DIR__ . '/../classes/Database.php';
require_once __DIR__ . '/../classes/EmailPlaceholder.php';
require_once __DIR__ . '/../classes/EmailTemplateRenderService.php';
require_once __DIR__ . '/../classes/Mailer.php';

header('Content-Type: application/json; charset=utf-8');

const EMAIL_TEMPLATE_TEST_MAX_SUBJECT_LENGTH = 255;
const EMAIL_TEMPLATE_TEST_MAX_HTML_LENGTH = 50000;
const EMAIL_TEMPLATE_TEST_MAX_TEXT_LENGTH = 20000;
const EMAIL_TEMPLATE_TEST_MAX_JSON_BYTES = 50000;
const EMAIL_TEMPLATE_TEST_MAX_EMAIL_LENGTH = 254;

function normalizeEmailTemplateJsonInput(string $value): string
{
    $normalized = preg_replace('/^\xEF\xBB\xBF/', '', $value) ?? $value;
    $normalized = str_replace("\xC2\xA0", ' ', $normalized);
    $normalized = str_replace(["\u{2018}", "\u{2019}"], "'", $normalized);
    $normalized = str_replace(["\u{201C}", "\u{201D}"], '"', $normalized);
    $normalized = preg_replace('/,\s*([}\]])/', '$1', $normalized) ?? $normalized;

    return trim($normalized);
}

/**
 * @return array<string,mixed>
 */
function emailTemplateTestSendPayload(): array
{
    $sampleVariables = [];
    $sampleInput = normalizeEmailTemplateJsonInput((string)($_POST['sample_variables'] ?? '{}'));
    if (strlen($sampleInput) > EMAIL_TEMPLATE_TEST_MAX_JSON_BYTES) {
        throw new InvalidArgumentException((string)(__('emailTemplate_error_sample_json_too_large') ?: 'Sample variables JSON terlalu besar.'));
    }
    if ($sampleInput !== '') {
        $decoded = json_decode($sampleInput, true);
        if (json_last_error() !== JSON_ERROR_NONE || !is_array($decoded)) {
            throw new InvalidArgumentException((string)(__('emailTemplate_error_sample_json_invalid') ?: 'Sample variables mesti dalam format JSON yang sah.'));
        }
        $sampleVariables = $decoded;
    }

    $testEmail = trim((string)($_POST['test_email'] ?? ''));
    $subjectTemplate = trim((string)($_POST['subject_template'] ?? ''));
    $bodyHtml = trim((string)($_POST['body_html'] ?? ''));
    $bodyText = trim((string)($_POST['body_text'] ?? ''));

    if (mb_strlen($testEmail) > EMAIL_TEMPLATE_TEST_MAX_EMAIL_LENGTH) {
        throw new InvalidArgumentException((string)(__('emailTemplate_error_test_email_invalid') ?: 'Alamat emel ujian tidak sah.'));
    }
    if (mb_strlen($subjectTemplate) > EMAIL_TEMPLATE_TEST_MAX_SUBJECT_LENGTH) {
        throw new InvalidArgumentException((string)(__('emailTemplate_error_subject_too_long') ?: 'Subjek template terlalu panjang.'));
    }
    if (strlen($bodyHtml) > EMAIL_TEMPLATE_TEST_MAX_HTML_LENGTH) {
        throw new InvalidArgumentException((string)(__('emailTemplate_error_body_html_too_long') ?: 'Kandungan HTML template terlalu panjang.'));
    }
    if (strlen($bodyText) > EMAIL_TEMPLATE_TEST_MAX_TEXT_LENGTH) {
        throw new InvalidArgumentException((string)(__('emailTemplate_error_body_text_too_long') ?: 'Kandungan text template terlalu panjang.'));
    }

    return [
        'test_email' => $testEmail,
        'subject_template' => $subjectTemplate,
        'body_html' => $bodyHtml,
        'body_text' => $bodyText,
        'sample_variables' => $sampleVariables,
    ];
}

try {
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
        jsonErrorResponse('Method not allowed', 405);
    }

    if (!isValidCsrfToken()) {
        jsonErrorResponse((string)(__('userGroup_csrf_invalid') ?: 'CSRF token tidak sah.'), 403);
    }

    if (!checkRateLimit('email_template_test_send', 5, 60)) {
        jsonErrorResponse((string)(__('emailTemplate_error_test_send_rate_limited') ?: 'Terlalu banyak permintaan emel ujian. Sila tunggu sebentar dan cuba lagi.'), 429);
    }

    $pdo = Database::pdoMysql();
    ensureAjaxGroupManagePermission($pdo);

    $payload = emailTemplateTestSendPayload();
    if ($payload['test_email'] === '' || !filter_var($payload['test_email'], FILTER_VALIDATE_EMAIL)) {
        jsonErrorResponse((string)(__('emailTemplate_error_test_email_invalid') ?: 'Alamat emel ujian tidak sah.'), 422);
    }

    if ($payload['subject_template'] === '' || $payload['body_html'] === '') {
        jsonErrorResponse((string)(__('emailTemplate_error_preview_required') ?: 'Subjek dan kandungan HTML diperlukan untuk preview.'), 422);
    }

    $placeholderModel = new EmailPlaceholder($pdo);
    $renderService = new EmailTemplateRenderService($placeholderModel);
    $rendered = $renderService->renderTemplate([
        'subject_template' => $payload['subject_template'],
        'body_html' => $payload['body_html'],
        'body_text' => $payload['body_text'],
    ], (array)$payload['sample_variables'], [
        'recipient_email' => $payload['test_email'],
    ]);

    $mailer = Mailer::fromConfig($pdo);
    $sent = $mailer->send(
        $payload['test_email'],
        (string)($rendered['subject'] ?? ''),
        (string)($rendered['html'] ?? ''),
        (string)($rendered['text'] ?? '')
    );

    if (!$sent) {
        jsonExceptionResponse(
            $mailer->lastFailureAsExternalServiceException((string)(__('emailTemplate_error_test_send_failed') ?: 'Emel ujian tidak berjaya dihantar.')),
            $mailer->getLastError() ?: (string)(__('emailTemplate_error_test_send_failed') ?: 'Emel ujian tidak berjaya dihantar.'),
            ['endpoint' => 'email-template-test-send']
        );
    }

    jsonSuccessResponse([
        'message' => (string)(__('emailTemplate_test_send_success') ?: 'Emel ujian berjaya dihantar.'),
    ]);
} catch (InvalidArgumentException $e) {
    jsonErrorResponse($e->getMessage(), 422);
} catch (ExternalServiceException $e) {
    jsonExceptionResponse($e, (string)(__('emailTemplate_error_test_send_failed') ?: 'Emel ujian tidak berjaya dihantar.'), [
        'endpoint' => 'email-template-test-send',
    ]);
} catch (Throwable $e) {
    error_log('[email-template-test-send] ' . $e->getMessage());
    jsonErrorResponse((string)(__('emailTemplate_error_test_send_failed') ?: 'Emel ujian tidak berjaya dihantar.'), 500);
}
