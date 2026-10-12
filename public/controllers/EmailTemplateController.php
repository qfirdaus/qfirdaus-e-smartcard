<?php
/**
 * IQS FRAMEWORK CORE FILE
 *
 * READ ONLY for downstream project programmers.
 * Do not modify this file directly in template or cloned projects.
 * Custom changes must be implemented in project-specific files
 * or approved extension points.
 */
declare(strict_types=1);

require_once __DIR__ . '/../classes/Database.php';
require_once __DIR__ . '/../classes/User.php';
require_once __DIR__ . '/../classes/SystemConfigConstants.php';
require_once __DIR__ . '/../classes/EmailTemplate.php';
require_once __DIR__ . '/../classes/EmailPlaceholder.php';
require_once __DIR__ . '/../setting/helper/audit_helper.php';

final class EmailTemplateController
{
    public string $lang = 'ms';
    public array $profile = [];
    public string $csrf = '';

    /** @var array<int,array<string,mixed>> */
    public array $records = [];

    /** @var array<int,array<string,mixed>> */
    public array $generalPlaceholders = [];

    /** @var array<int,array<string,mixed>> */
    public array $seedPlaceholders = [];

    /** @var array<int,array<string,mixed>> */
    public array $seedTemplates = [];

    /** @var array<string,string> */
    public array $filters = [
        'role_code' => '',
        'category_code' => '',
        'status' => '',
        'search' => '',
    ];

    /** @var array<string,mixed> */
    public array $form = [
        'template_id' => 0,
        'template_code' => '',
        'template_name' => '',
        'role_code' => '',
        'category_code' => '',
        'subject_template' => '',
        'body_html' => '',
        'body_text' => '',
        'status' => 'DRAFT',
        'is_default' => 0,
        'description' => '',
        'notes' => '',
    ];

    public array $fieldErrors = [];
    public ?string $errorMessage = null;
    public ?string $successMessage = null;
    public bool $shouldOpenModal = false;

    /** @var array<string,string> */
    public array $roleOptions = [];

    /** @var array<string,string> */
    public array $categoryOptions = [];

    /** @var array<string,string> */
    public array $statusOptions = [];

    /** @var array<string,int> */
    public array $summary = [
        'total' => 0,
        'active' => 0,
        'draft' => 0,
        'archived' => 0,
    ];

    /** @var array<int,int> */
    public array $usageCounts = [];

    protected PDO $pdoMysql;
    protected EmailTemplate $emailTemplateModel;
    protected EmailPlaceholder $emailPlaceholderModel;

    public function __construct()
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $this->lang = (string)($_SESSION['lang'] ?? SystemConfigConstants::DEFAULT_LANGUAGE);
        $this->pdoMysql = Database::getInstance('mysql')->getConnection();
        $this->profile = $this->loadProfile();
        $this->applyUserTheme();
        $this->ensureCsrfToken();

        $this->emailTemplateModel = new EmailTemplate($this->pdoMysql);
        $this->emailPlaceholderModel = new EmailPlaceholder($this->pdoMysql);
        $this->roleOptions = $this->buildRoleOptions();
        $this->categoryOptions = $this->buildCategoryOptions();
        $this->statusOptions = $this->buildStatusOptions();

        $this->filters = [
            'role_code' => $this->getQueryString('role'),
            'category_code' => $this->getQueryString('category'),
            'status' => strtoupper($this->getQueryString('status')),
            'search' => $this->getQueryString('search'),
        ];

        $this->hydrateFlashMessages();
        $this->seedPlaceholders = $this->loadSeedPlaceholderConfig();
        $this->seedTemplates = $this->loadSeedTemplateConfig();
        $this->loadGeneralPlaceholders();
        $this->handleRequest();
        $this->loadRecords();
        $this->buildSummary();
    }

    protected function loadProfile(): array
    {
        $userModel = new User($this->pdoMysql);
        $fStafID = $_SESSION['f_stafID'] ?? null;

        return $fStafID ? ($userModel->getProfile((string)$fStafID) ?: []) : [];
    }

    protected function applyUserTheme(): void
    {
        $settingJson = $this->profile['f_themeSetting'] ?? '{}';
        $themeSetting = json_decode((string)$settingJson, true);
        if (!is_array($themeSetting)) {
            $themeSetting = [];
        }

        $_SESSION['theme.menu'] = $themeSetting['sidebarColor'] ?? ($_SESSION['theme.menu'] ?? 'light');
        $_SESSION['theme.topbar'] = $themeSetting['topbarColor'] ?? ($_SESSION['theme.topbar'] ?? 'light');
        $_SESSION['theme.layout'] = $themeSetting['layoutMode'] ?? ($_SESSION['theme.layout'] ?? 'light');
    }

    protected function ensureCsrfToken(): void
    {
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(16));
        }

        $this->csrf = (string)$_SESSION['csrf_token'];
    }

    protected function loadRecords(): void
    {
        try {
            $this->records = $this->emailTemplateModel->getAll($this->filters);
            $this->usageCounts = $this->emailTemplateModel->getUsageCounts(array_map(
                static fn (array $record): int => (int)($record['f_templateID'] ?? 0),
                $this->records
            ));
        } catch (Throwable $e) {
            error_log('[EmailTemplateController] loadRecords failed: ' . $e->getMessage());
            $this->records = [];
            $this->usageCounts = [];
            $this->errorMessage = $this->tr('emailTemplate_error_load_records', 'Gagal memuat senarai template emel.');
        }
    }

    protected function handleRequest(): void
    {
        if (strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET')) !== 'POST') {
            return;
        }

        $this->form = $this->collectFormData();
        $this->shouldOpenModal = true;
        $formAction = strtolower(trim((string)($_POST['form_action'] ?? 'save')));
        $allowedActions = ['save', 'archive', 'duplicate', 'seed_templates'];

        if (!in_array($formAction, $allowedActions, true)) {
            $this->errorMessage = $this->tr('emailTemplate_error_invalid_action', 'Tindakan yang diminta tidak sah.');
            return;
        }

        $csrfToken = trim((string)($_POST['csrf_token'] ?? ''));
        if ($csrfToken === '' || !hash_equals($this->csrf, $csrfToken)) {
            $this->errorMessage = $this->tr('emailTemplate_error_invalid_csrf', 'Sesi anda telah tamat. Sila muat semula halaman dan cuba lagi.');
            return;
        }

        if (!in_array($formAction, ['archive', 'duplicate', 'seed_templates'], true)) {
            $this->fieldErrors = $this->validateForm($this->form);
            if ($this->fieldErrors !== []) {
                $this->errorMessage = $this->tr('emailTemplate_error_validation', 'Sila semak semula maklumat template emel yang diisi.');
                return;
            }
        }

        try {
            $templateId = (int)($this->form['template_id'] ?? 0);
            $savePayload = $this->form;
            $savePayload['update_by'] = (string)($_SESSION['f_stafID'] ?? $_SESSION['login_id'] ?? 'system');

            if ($formAction === 'archive') {
                if ($templateId <= 0) {
                    throw new RuntimeException($this->tr('emailTemplate_error_template_not_found', 'Template emel tidak ditemui.'));
                }

                $this->emailTemplateModel->archive($templateId, (string)$savePayload['update_by']);
                $this->auditTemplateAction('ARCHIVE', $templateId, ['action' => 'archive']);
                $this->setFlash('success', $this->tr('emailTemplate_archive_success', 'Template emel berjaya diarkibkan.'));
            } elseif ($formAction === 'duplicate') {
                if ($templateId <= 0) {
                    throw new RuntimeException($this->tr('emailTemplate_error_template_not_found', 'Template emel tidak ditemui.'));
                }

                $newTemplateId = $this->emailTemplateModel->duplicate($templateId, (string)$savePayload['update_by']);
                $this->auditTemplateAction('CREATE', $newTemplateId, [
                    'action' => 'duplicate',
                    'source_template_id' => $templateId,
                ]);
                $this->setFlash('success', $this->tr('emailTemplate_duplicate_success', 'Salinan template emel berjaya dicipta.'));
            } elseif ($formAction === 'seed_templates') {
                $importedCount = $this->importSeedTemplates((string)$savePayload['update_by']);
                $this->setFlash('success', $this->tr(
                    'emailTemplate_seed_success',
                    'Seed template berjaya diimport.'
                ) . ' (' . $importedCount . ')');
            } elseif ($templateId > 0) {
                $this->emailTemplateModel->update($templateId, $savePayload);
                $this->auditTemplateAction('UPDATE', $templateId, ['action' => 'update']);
                $this->setFlash('success', $this->tr('emailTemplate_save_success_update', 'Template emel berjaya dikemaskini.'));
            } else {
                $newTemplateId = $this->emailTemplateModel->create($savePayload);
                $this->auditTemplateAction('CREATE', $newTemplateId, ['action' => 'create']);
                $this->setFlash('success', $this->tr('emailTemplate_save_success_create', 'Template emel berjaya dicipta.'));
            }

            $redirectUrl = $this->buildRedirectUrl();
            header('Location: ' . $redirectUrl);
            exit;
        } catch (Throwable $e) {
            error_log('[EmailTemplateController] handleRequest failed: ' . $e->getMessage());
            $message = trim($e->getMessage());
            if ($message === 'Default email template cannot be archived until another template is set as default.') {
                $message = $this->tr('emailTemplate_error_archive_default_blocked', 'Template default tidak boleh diarkibkan selagi belum ada template lain dijadikan default bagi peranan dan kategori yang sama.');
            } elseif ($message === 'Unable to generate a unique duplicate template code.') {
                $message = $this->tr('emailTemplate_error_duplicate_failed', 'Salinan template tidak berjaya dijana. Sila cuba semula.');
            } else {
                $message = $this->tr('emailTemplate_save_fail', 'Template emel tidak berjaya disimpan.');
            }
            $this->errorMessage = $message;
        }
    }

    protected function loadGeneralPlaceholders(): void
    {
        try {
            $this->generalPlaceholders = $this->emailPlaceholderModel->getActiveGeneral();
        } catch (Throwable $e) {
            error_log('[EmailTemplateController] loadGeneralPlaceholders failed: ' . $e->getMessage());
            $this->generalPlaceholders = [];
        }
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    protected function loadSeedPlaceholderConfig(): array
    {
        $path = __DIR__ . '/../configuration/email_template_placeholders.php';
        if (!is_file($path)) {
            return [];
        }

        $data = require $path;
        return is_array($data) ? array_values(array_filter($data, 'is_array')) : [];
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    protected function loadSeedTemplateConfig(): array
    {
        $path = __DIR__ . '/../configuration/email_template_seeds.php';
        if (!is_file($path)) {
            return [];
        }

        $data = require $path;
        return is_array($data) ? array_values(array_filter($data, 'is_array')) : [];
    }

    protected function getQueryString(string $key, string $default = ''): string
    {
        return trim((string)($_GET[$key] ?? $default));
    }

    /**
     * @return array<string,mixed>
     */
    protected function collectFormData(): array
    {
        return [
            'template_id' => (int)($_POST['template_id'] ?? 0),
            'template_code' => strtoupper(trim((string)($_POST['template_code'] ?? ''))),
            'template_name' => trim((string)($_POST['template_name'] ?? '')),
            'role_code' => strtolower(trim((string)($_POST['role_code'] ?? ''))),
            'category_code' => strtolower(trim((string)($_POST['category_code'] ?? ''))),
            'subject_template' => trim((string)($_POST['subject_template'] ?? '')),
            'body_html' => trim((string)($_POST['body_html'] ?? '')),
            'body_text' => trim((string)($_POST['body_text'] ?? '')),
            'status' => strtoupper(trim((string)($_POST['status'] ?? 'DRAFT'))),
            'is_default' => !empty($_POST['is_default']) ? 1 : 0,
            'description' => trim((string)($_POST['description'] ?? '')),
            'notes' => trim((string)($_POST['notes'] ?? '')),
        ];
    }

    /**
     * @param array<string,mixed> $form
     * @return array<string,string>
     */
    protected function validateForm(array $form): array
    {
        $errors = [];
        $templateId = (int)($form['template_id'] ?? 0);
        $templateCode = trim((string)($form['template_code'] ?? ''));
        $templateName = trim((string)($form['template_name'] ?? ''));
        $roleCode = strtolower(trim((string)($form['role_code'] ?? '')));
        $categoryCode = strtolower(trim((string)($form['category_code'] ?? '')));
        $subjectTemplate = trim((string)($form['subject_template'] ?? ''));
        $bodyHtml = trim((string)($form['body_html'] ?? ''));
        $status = strtoupper(trim((string)($form['status'] ?? '')));

        if ($templateCode === '') {
            $errors['template_code'] = $this->tr('emailTemplate_error_template_code_required', 'Kod template adalah wajib.');
        } elseif (!preg_match('/^[A-Z0-9_\\-]+$/', $templateCode)) {
            $errors['template_code'] = $this->tr('emailTemplate_error_template_code_format', 'Kod template hanya boleh mengandungi huruf besar, nombor, dash, dan underscore.');
        } elseif ($this->emailTemplateModel->existsByCode($templateCode, $templateId > 0 ? $templateId : null)) {
            $errors['template_code'] = $this->tr('emailTemplate_error_template_code_exists', 'Kod template sudah digunakan.');
        }

        if ($templateName === '') {
            $errors['template_name'] = $this->tr('emailTemplate_error_template_name_required', 'Nama template adalah wajib.');
        }

        if ($roleCode === '' || !array_key_exists($roleCode, $this->roleOptions)) {
            $errors['role_code'] = $this->tr('emailTemplate_error_role_required', 'Peranan penerima adalah wajib.');
        }

        if ($categoryCode === '' || !array_key_exists($categoryCode, $this->categoryOptions)) {
            $errors['category_code'] = $this->tr('emailTemplate_error_category_required', 'Kategori emel adalah wajib.');
        }

        if ($subjectTemplate === '') {
            $errors['subject_template'] = $this->tr('emailTemplate_error_subject_required', 'Subjek template adalah wajib.');
        }

        if ($bodyHtml === '') {
            $errors['body_html'] = $this->tr('emailTemplate_error_body_html_required', 'Kandungan emel adalah wajib.');
        }

        if ($status === '' || !array_key_exists($status, $this->statusOptions)) {
            $errors['status'] = $this->tr('emailTemplate_error_status_required', 'Status template tidak sah.');
        }

        return $errors;
    }

    protected function buildRedirectUrl(): string
    {
        $query = array_filter([
            'role' => $this->filters['role_code'] ?? '',
            'category' => $this->filters['category_code'] ?? '',
            'status' => $this->filters['status'] ?? '',
            'search' => $this->filters['search'] ?? '',
        ], static fn ($value): bool => trim((string)$value) !== '');

        $baseUrl = function_exists('base_url')
            ? (string)base_url('pages/template-emel.php')
            : 'template-emel.php';

        return $query === [] ? $baseUrl : ($baseUrl . '?' . http_build_query($query));
    }

    protected function hydrateFlashMessages(): void
    {
        if (!empty($_SESSION['email_template_flash_success'])) {
            $this->successMessage = (string)$_SESSION['email_template_flash_success'];
            unset($_SESSION['email_template_flash_success']);
        }

        if (!empty($_SESSION['email_template_flash_error'])) {
            $this->errorMessage = (string)$_SESSION['email_template_flash_error'];
            unset($_SESSION['email_template_flash_error']);
        }
    }

    protected function setFlash(string $type, string $message): void
    {
        if ($type === 'success') {
            $_SESSION['email_template_flash_success'] = $message;
            return;
        }

        $_SESSION['email_template_flash_error'] = $message;
    }

    protected function buildSummary(): void
    {
        $summary = [
            'total' => 0,
            'active' => 0,
            'draft' => 0,
            'archived' => 0,
        ];

        foreach ($this->records as $record) {
            $summary['total']++;
            $status = strtolower((string)($record['f_status'] ?? ''));
            if (array_key_exists($status, $summary)) {
                $summary[$status]++;
            }
        }

        $this->summary = $summary;
    }

    /**
     * @return array<string,string>
     */
    protected function buildRoleOptions(): array
    {
        $options = [];
        foreach (SystemConfigConstants::ALLOWED_EMAIL_TEMPLATE_ROLES as $roleCode) {
            $options[$roleCode] = $this->tr('emailTemplate_role_' . $roleCode, ucfirst($roleCode));
        }

        return $options;
    }

    protected function importSeedTemplates(string $updateBy): int
    {
        $count = 0;
        foreach ($this->seedTemplates as $seedTemplate) {
            $templateCode = strtoupper(trim((string)($seedTemplate['template_code'] ?? '')));
            if ($templateCode === '' || $this->emailTemplateModel->existsByCode($templateCode)) {
                continue;
            }

            $templateId = $this->emailTemplateModel->create(array_merge($seedTemplate, [
                'update_by' => $updateBy,
            ]));
            if ($templateId > 0) {
                $count++;
                $this->auditTemplateAction('CREATE', $templateId, [
                    'action' => 'seed_import',
                    'seed_template' => true,
                ]);
            }
        }

        return $count;
    }

    /**
     * @param array<string,mixed> $meta
     */
    protected function auditTemplateAction(string $eventType, int $templateId, array $meta = []): void
    {
        if ($templateId <= 0 || !function_exists('audit_event')) {
            return;
        }

        $template = $this->emailTemplateModel->findById($templateId);
        audit_event([
            'event_type' => $eventType,
            'severity' => 'INFO',
            'outcome' => 'SUCCESS',
            'target_type' => 'email_template',
            'target_id' => (string)$templateId,
            'target_label' => (string)($template['f_templateCode'] ?? $templateId),
            'message' => 'Email template action recorded.',
            'actor_label' => trim((string)($_SESSION['user']['f_nama'] ?? $_SESSION['f_nama'] ?? '')),
            'login_id' => trim((string)($_SESSION['f_loginID'] ?? $_SESSION['user']['f_loginID'] ?? '')),
            'meta' => array_merge([
                'module' => 'template-emel',
                'template_code' => (string)($template['f_templateCode'] ?? ''),
                'template_name' => (string)($template['f_templateName'] ?? ''),
            ], $meta),
        ]);
    }

    /**
     * @return array<string,string>
     */
    protected function buildCategoryOptions(): array
    {
        $options = [];
        foreach (SystemConfigConstants::ALLOWED_EMAIL_TEMPLATE_CATEGORIES as $categoryCode) {
            $options[$categoryCode] = $this->tr('emailTemplate_category_' . $categoryCode, ucfirst($categoryCode));
        }

        return $options;
    }

    /**
     * @return array<string,string>
     */
    protected function buildStatusOptions(): array
    {
        $options = [];
        foreach (SystemConfigConstants::ALLOWED_EMAIL_TEMPLATE_STATUSES as $statusCode) {
            $options[$statusCode] = $this->tr('emailTemplate_status_' . strtolower($statusCode), $statusCode);
        }

        return $options;
    }

    protected function tr(string $key, string $fallback): string
    {
        if (!function_exists('__')) {
            return $fallback;
        }

        $value = __($key);
        return ($value === $key || $value === null || $value === '') ? $fallback : (string)$value;
    }
}
