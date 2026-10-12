<?php
declare(strict_types=1);

require_once __DIR__ . '/../classes/Database.php';
require_once __DIR__ . '/../classes/User.php';

class __CONTROLLER_CLASS__
{
    public string $lang = 'ms';
    public array $profile = [];
    public array $items = [];
    public array $selectedItem = [];
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
        $this->items = $this->loadItems();
        $this->selectedItem = $this->items[0] ?? [];
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
     * @return array<int,array<string,mixed>>
     */
    protected function loadItems(): array
    {
        return [
            [
                'id' => 1,
                'name' => 'Administrator Group',
                'code' => 'ADM-001',
                'status' => 'Active',
                'owner' => 'System Administration',
                'updated_at' => '28/03/2026 01:15 PM',
                'summary' => 'Primary group used to manage privileged access across internal modules.',
                'description' => 'Use this master-detail layout to browse records quickly and update the detail panel without leaving the page.',
                'tags' => ['Core', 'Internal', 'Security'],
            ],
            [
                'id' => 2,
                'name' => 'Operations Reviewer',
                'code' => 'OPR-014',
                'status' => 'Pending Review',
                'owner' => 'Operations Unit',
                'updated_at' => '28/03/2026 01:40 PM',
                'summary' => 'Sample item showing a secondary state and a different owner context.',
                'description' => 'Good for modules where users need to compare one selected item against a summary panel beneath or beside the list.',
                'tags' => ['Workflow', 'Approval', 'Review'],
            ],
            [
                'id' => 3,
                'name' => 'Template Maintenance',
                'code' => 'TMP-020',
                'status' => 'Active',
                'owner' => 'Development Team',
                'updated_at' => '28/03/2026 02:05 PM',
                'summary' => 'Reference entry for configuration-heavy admin pages with dependent metadata.',
                'description' => 'This sample data helps developers understand how a selected row can drive the content shown in a separate detail section.',
                'tags' => ['Template', 'Config', 'Developer'],
            ],
        ];
    }

    // Audit hook placeholder:
    // Use this section for selection, update, or configuration actions when audit is required.
}
