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
require_once __DIR__ . '/../classes/TemplateRegistryService.php';
require_once __DIR__ . '/../classes/TemplateResolverService.php';
require_once __DIR__ . '/../classes/FileGenerationService.php';

final class TemplateGeneratorController
{
    public string $lang = 'ms';
    public array $profile = [];
    public array $templates = [];
    public array $form = [
        'page_name' => '',
        'page_title_ms' => '',
        'page_title_en' => '',
        'page_icon' => 'ri-file-list-line',
        'template_key' => 'blank',
    ];
    public ?array $previewResult = null;
    public ?array $generationResult = null;
    public ?string $errorMessage = null;
    public ?string $successMessage = null;

    private PDO $pdoMysql;
    private TemplateRegistryService $registryService;
    private TemplateResolverService $resolverService;
    private FileGenerationService $fileGenerationService;

    public function __construct()
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $this->lang = $_SESSION['lang'] ?? 'ms';
        $this->pdoMysql = Database::getInstance('mysql')->getConnection();

        $userModel = new User($this->pdoMysql);
        $fStafID = $_SESSION['f_stafID'] ?? null;
        $this->profile = $fStafID ? ($userModel->getProfile((string)$fStafID) ?? []) : [];

        $settingJson = $this->profile['f_themeSetting'] ?? '{}';
        $themeSetting = json_decode((string)$settingJson, true);
        if (!is_array($themeSetting)) {
            $themeSetting = [];
        }

        $_SESSION['theme.menu'] = $themeSetting['sidebarColor'] ?? ($_SESSION['theme.menu'] ?? 'light');
        $_SESSION['theme.topbar'] = $themeSetting['topbarColor'] ?? ($_SESSION['theme.topbar'] ?? 'light');
        $_SESSION['theme.layout'] = $themeSetting['layoutMode'] ?? ($_SESSION['theme.layout'] ?? 'light');

        $this->registryService = new TemplateRegistryService();
        $this->resolverService = new TemplateResolverService($this->registryService);
        $this->fileGenerationService = new FileGenerationService($this->resolverService);
        $this->templates = $this->registryService->getAllTemplates();

        $this->handleRequest();
    }

    private function handleRequest(): void
    {
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
            return;
        }

        $this->form = [
            'page_name' => trim((string)($_POST['page_name'] ?? '')),
            'page_title_ms' => trim((string)($_POST['page_title_ms'] ?? '')),
            'page_title_en' => trim((string)($_POST['page_title_en'] ?? '')),
            'page_icon' => trim((string)($_POST['page_icon'] ?? 'ri-file-list-line')),
            'template_key' => trim((string)($_POST['template_key'] ?? 'blank')),
        ];

        $csrf = (string)($_POST['csrf_token'] ?? '');
        $sessionCsrf = (string)($_SESSION['csrf_token'] ?? '');
        if ($csrf === '' || $sessionCsrf === '' || !hash_equals($sessionCsrf, $csrf)) {
            $this->errorMessage = (string)__('pageTemplateGenerator_error_csrf');
            return;
        }

        $action = trim((string)($_POST['generator_action'] ?? 'preview'));

        try {
            if ($action === 'generate') {
                $this->generationResult = $this->fileGenerationService->generate($this->form['template_key'], $this->form);
                $this->successMessage = (string)__('pageTemplateGenerator_success_generate');
                $this->previewResult = $this->fileGenerationService->preview($this->form['template_key'], $this->form);
                return;
            }

            $this->previewResult = $this->fileGenerationService->preview($this->form['template_key'], $this->form);
        } catch (Throwable $e) {
            $this->errorMessage = $e->getMessage();
        }
    }
}
