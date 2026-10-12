<?php
declare(strict_types=1);

require_once __DIR__ . '/../classes/Database.php';
require_once __DIR__ . '/../classes/User.php';

class __CONTROLLER_CLASS__
{
    public string $lang = 'ms';
    public array $profile = [];
    public array $overview = [];
    public array $configuration = [];
    public array $history = [];
    public array $configurationRows = [];
    public array $historyRows = [];
    protected PDO $pdoMysql;

    public function __construct()
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $this->lang = $_SESSION['lang'] ?? 'ms';
        $this->pdoMysql = Database::getInstance('mysql')->getConnection();
        $this->profile = $this->loadProfile();
        $this->applyUserTheme();
        $this->overview = $this->loadOverview();
        $this->configuration = $this->loadConfiguration();
        $this->history = $this->loadHistory();
        $this->configurationRows = $this->loadConfigurationRows();
        $this->historyRows = $this->loadHistoryRows();
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

    protected function getQueryString(string $key, string $default = ''): string
    {
        return trim((string)($_GET[$key] ?? $default));
    }

    protected function getQueryInt(string $key, int $default = 0): int
    {
        $value = $_GET[$key] ?? $default;
        return is_numeric($value) ? (int)$value : $default;
    }

    protected function isAjaxRequest(): bool
    {
        $requestedWith = strtolower(trim((string)($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '')));
        $accept = strtolower(trim((string)($_SERVER['HTTP_ACCEPT'] ?? '')));

        return $requestedWith === 'xmlhttprequest' || str_contains($accept, 'application/json');
    }

    protected function jsonSuccess(array $payload = [], int $status = 200): void
    {
        if (!headers_sent()) {
            http_response_code($status);
            header('Content-Type: application/json; charset=UTF-8');
        }

        echo json_encode(array_merge(['success' => true], $payload), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    protected function jsonError(string $message, int $status = 400, array $extra = []): void
    {
        if (!headers_sent()) {
            http_response_code($status);
            header('Content-Type: application/json; charset=UTF-8');
        }

        echo json_encode(array_merge([
            'success' => false,
            'message' => $message,
        ], $extra), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    public function setLang(string $lang): void
    {
        $this->lang = trim($lang) !== '' ? trim($lang) : 'ms';
        $_SESSION['lang'] = $this->lang;
    }

    /**
     * @return array<string,mixed>
     */
    protected function loadOverview(): array
    {
        return [
            'title' => 'Operations Access Control',
            'subtitle' => 'Use this tab shell for modules that need segmented content, mixed widgets, and workflow notes in one page.',
            'status' => 'Active',
            'updated_at' => '28/03/2026 03:35 PM',
            'module_id' => 'TAB-001',
            'reference_no' => 'TM-2026-001',
            'owner' => 'Development Team',
            'category' => 'Management Shell',
            'description' => 'Sample tabbed workspace for modules that need segmented navigation, structured summary content, and secondary tables in the same page.',
            'chips' => ['Template', 'Tabbed', 'Management'],
        ];
    }

    /**
     * @return array<string,mixed>
     */
    protected function loadConfiguration(): array
    {
        return [
            'module_name' => 'Access Control Workspace',
            'layout_mode' => 'Standard',
            'status' => 'active',
            'owner' => 'Development Team',
            'description' => 'This tab demonstrates how developers can place configuration inputs inside a tabbed layout without leaving the same page context.',
            'language' => 'English',
            'visibility' => 'Internal',
        ];
    }

    /**
     * @return array<int,array<string,string>>
     */
    protected function loadHistory(): array
    {
        return [
            [
                'event' => 'Initial tabbed management layout generated',
                'actor' => 'System Template Generator',
                'timestamp' => '28/03/2026 03:40 PM',
                'status' => 'Completed',
            ],
            [
                'event' => 'Configuration sample content seeded into template controller',
                'actor' => 'Development Team',
                'timestamp' => '28/03/2026 03:42 PM',
                'status' => 'Completed',
            ],
            [
                'event' => 'History panel reserved for future module-specific actions',
                'actor' => 'Module Owner',
                'timestamp' => '28/03/2026 03:45 PM',
                'status' => 'Pending Review',
            ],
        ];
    }

    /**
     * @return array<int,array<string,string>>
     */
    protected function loadConfigurationRows(): array
    {
        return [
            [
                'datetime' => '28/03/2026 03:40 PM',
                'setting' => 'Default layout mode set to Standard',
                'owner' => 'Development Team',
                'status' => 'Applied',
            ],
            [
                'datetime' => '28/03/2026 03:52 PM',
                'setting' => 'Visibility scoped to internal management users',
                'owner' => 'System Admin',
                'status' => 'Applied',
            ],
            [
                'datetime' => '28/03/2026 04:06 PM',
                'setting' => 'Language preference placeholder reserved for module override',
                'owner' => 'Module Owner',
                'status' => 'Review',
            ],
        ];
    }

    /**
     * @return array<int,array<string,string>>
     */
    protected function loadHistoryRows(): array
    {
        return [
            [
                'datetime' => '28/03/2026 04:08 PM',
                'actor' => 'System Template Generator',
                'activity' => 'Created sample tabbed-management baseline and seeded language keys.',
                'result' => 'Success',
            ],
            [
                'datetime' => '28/03/2026 04:11 PM',
                'actor' => 'Development Team',
                'activity' => 'Adjusted overview content to fit module-specific navigation pattern.',
                'result' => 'Success',
            ],
            [
                'datetime' => '28/03/2026 04:15 PM',
                'actor' => 'Module Owner',
                'activity' => 'Pending review for workflow-specific actions and tab-level permissions.',
                'result' => 'Pending',
            ],
        ];
    }

    // Audit hook placeholder:
    // Use this section when tab actions need create, update, or workflow audit logging.
}
