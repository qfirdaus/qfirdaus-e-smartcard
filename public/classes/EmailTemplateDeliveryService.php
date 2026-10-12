<?php
/**
 * IQS FRAMEWORK CORE FILE
 *
 * READ ONLY for downstream project programmers.
 * Do not modify this file directly in template or cloned projects.
 * Custom changes must be implemented in project-specific files
 * or approved extension points.
 */declare(strict_types=1);

require_once __DIR__ . '/EmailTemplate.php';
require_once __DIR__ . '/EmailPlaceholder.php';
require_once __DIR__ . '/EmailTemplateRenderService.php';
require_once __DIR__ . '/Mailer.php';
require_once __DIR__ . '/ExternalServiceException.php';
require_once __DIR__ . '/../setting/helper/audit_helper.php';

final class EmailTemplateDeliveryService
{
    private PDO $pdo;
    private EmailTemplate $emailTemplateModel;
    private EmailPlaceholder $emailPlaceholderModel;
    private EmailTemplateRenderService $renderService;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
        $this->emailTemplateModel = new EmailTemplate($pdo);
        $this->emailPlaceholderModel = new EmailPlaceholder($pdo);
        $this->renderService = new EmailTemplateRenderService($this->emailPlaceholderModel);
    }

    /**
     * @param array<string,mixed> $variables
     * @param array<string,mixed> $context
     * @return array<string,mixed>
     */
    public function previewByCode(string $templateCode, array $variables = [], array $context = []): array
    {
        $template = $this->getActiveTemplateByCode($templateCode);
        return $this->renderTemplate($template, $variables, $context);
    }

    /**
     * @param array<string,mixed> $variables
     * @param array<string,mixed> $context
     * @return array<string,mixed>
     */
    public function previewDefault(string $roleCode, string $categoryCode, array $variables = [], array $context = []): array
    {
        $template = $this->getDefaultTemplate($roleCode, $categoryCode);
        return $this->renderTemplate($template, $variables, $context);
    }

    /**
     * @param array<int,string>|string $to
     * @param array<string,mixed> $variables
     * @param array<string,mixed> $opts
     * @param array<string,mixed> $context
     * @return array<string,mixed>
     */
    public function sendByCode(string $templateCode, array|string $to, array $variables = [], array $opts = [], array $context = []): array
    {
        $template = $this->getActiveTemplateByCode($templateCode);
        return $this->sendWithTemplate($template, $to, $variables, $opts, $context);
    }

    /**
     * @param array<int,string>|string $to
     * @param array<string,mixed> $variables
     * @param array<string,mixed> $opts
     * @param array<string,mixed> $context
     * @return array<string,mixed>
     */
    public function sendDefault(string $roleCode, string $categoryCode, array|string $to, array $variables = [], array $opts = [], array $context = []): array
    {
        $template = $this->getDefaultTemplate($roleCode, $categoryCode);
        return $this->sendWithTemplate($template, $to, $variables, $opts, $context);
    }

    /**
     * @param array<string,mixed> $template
     * @param array<string,mixed> $variables
     * @param array<string,mixed> $context
     * @return array<string,mixed>
     */
    public function renderTemplate(array $template, array $variables = [], array $context = []): array
    {
        $mergedContext = $this->buildDeliveryContext([], $variables, $context, $template);
        $rendered = $this->renderService->renderTemplate($template, $variables, $mergedContext);

        return [
            'template' => $template,
            'rendered' => $rendered,
        ];
    }

    /**
     * @param array<string,mixed> $template
     * @param array<int,string>|string $to
     * @param array<string,mixed> $variables
     * @param array<string,mixed> $opts
     * @param array<string,mixed> $context
     * @return array<string,mixed>
     */
    private function sendWithTemplate(array $template, array|string $to, array $variables, array $opts, array $context): array
    {
        $recipients = $this->normalizeRecipients($to);
        if ($recipients === []) {
            throw new RuntimeException('Email recipient is required.');
        }

        $mergedContext = $this->buildDeliveryContext($recipients, $variables, $context, $template);
        $rendered = $this->renderService->renderTemplate($template, $variables, $mergedContext);

        $mailer = Mailer::fromConfig($this->pdo);
        $sent = $mailer->send(
            $recipients,
            (string)($rendered['subject'] ?? ''),
            (string)($rendered['html'] ?? ''),
            (string)($rendered['text'] ?? ''),
            $opts
        );

        if (!$sent) {
            throw $mailer->lastFailureAsExternalServiceException('Email could not be sent.');
        }

        if (function_exists('audit_event')) {
            audit_event([
                'event_type' => 'EMAIL_SENT',
                'severity' => 'INFO',
                'outcome' => 'SUCCESS',
                'target_type' => 'email_template',
                'target_id' => (string)($template['f_templateID'] ?? ''),
                'target_label' => (string)($template['f_templateCode'] ?? ''),
                'message' => 'Email template sent successfully.',
                'actor_label' => trim((string)($_SESSION['user']['f_nama'] ?? $_SESSION['f_nama'] ?? '')),
                'login_id' => trim((string)($_SESSION['f_loginID'] ?? $_SESSION['user']['f_loginID'] ?? '')),
                'meta' => [
                    'usage_action' => 'send',
                    'template_code' => (string)($template['f_templateCode'] ?? ''),
                    'template_name' => (string)($template['f_templateName'] ?? ''),
                    'recipient_count' => count($recipients),
                    'recipients' => $recipients,
                ],
            ]);
        }

        return [
            'success' => true,
            'template' => $template,
            'rendered' => $rendered,
            'recipients' => $recipients,
        ];
    }

    /**
     * @param array<int,string> $recipients
     * @param array<string,mixed> $variables
     * @param array<string,mixed> $context
     * @param array<string,mixed> $template
     * @return array<string,mixed>
     */
    private function buildDeliveryContext(array $recipients, array $variables, array $context, array $template): array
    {
        $primaryRecipient = $recipients[0] ?? '';

        return array_merge([
            'recipient_email' => (string)($context['recipient_email'] ?? $variables['recipient_email'] ?? $primaryRecipient),
            'recipient_name' => (string)($context['recipient_name'] ?? $variables['recipient_name'] ?? ''),
            'recipient_role' => (string)($context['recipient_role'] ?? $variables['recipient_role'] ?? ($template['f_roleCode'] ?? '')),
            'recipient_position' => (string)($context['recipient_position'] ?? $variables['recipient_position'] ?? ''),
            'recipient_department' => (string)($context['recipient_department'] ?? $variables['recipient_department'] ?? ''),
            'sender_name' => (string)($context['sender_name'] ?? $variables['sender_name'] ?? ''),
            'preheader' => (string)($context['preheader'] ?? ''),
            'footer_note' => (string)($context['footer_note'] ?? ''),
        ], $context);
    }

    /**
     * @param array<int,string>|string $to
     * @return array<int,string>
     */
    private function normalizeRecipients(array|string $to): array
    {
        $recipients = [];
        foreach ((array)$to as $recipient) {
            $email = trim((string)$recipient);
            if ($email === '') {
                continue;
            }
            $recipients[] = $email;
        }

        return array_values(array_unique($recipients));
    }

    /**
     * @return array<string,mixed>
     */
    private function getActiveTemplateByCode(string $templateCode): array
    {
        $templateCode = trim($templateCode);
        if ($templateCode === '') {
            throw new RuntimeException('Email template code is required.');
        }

        $template = $this->emailTemplateModel->findByCode($templateCode);
        if (!$template) {
            throw new RuntimeException("Email template [{$templateCode}] was not found.");
        }

        if (strtoupper((string)($template['f_status'] ?? 'DRAFT')) !== 'ACTIVE') {
            throw new RuntimeException("Email template [{$templateCode}] is not active.");
        }

        return $template;
    }

    /**
     * @return array<string,mixed>
     */
    private function getDefaultTemplate(string $roleCode, string $categoryCode): array
    {
        $roleCode = strtolower(trim($roleCode));
        $categoryCode = strtolower(trim($categoryCode));
        if ($roleCode === '' || $categoryCode === '') {
            throw new RuntimeException('Role code and category code are required.');
        }

        $template = $this->emailTemplateModel->findDefault($roleCode, $categoryCode);
        if (!$template) {
            throw new RuntimeException("Default email template for role [{$roleCode}] and category [{$categoryCode}] was not found.");
        }

        return $template;
    }
}
