<?php
declare(strict_types=1);

require_once __DIR__ . '/../classes/Database.php';
require_once __DIR__ . '/../classes/User.php';

class __CONTROLLER_CLASS__
{
    public string $lang = 'ms';
    public array $profile = [];
    public array $rows = [];
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
        $this->rows = $this->loadRows();
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
     * @return array<int,array<string,string>>
     */
    protected function loadRows(): array
    {
        return [
            [
                'reference_no' => 'EXP-001',
                'title' => 'Template Generator Audit Trail',
                'status' => 'Completed',
                'updated_at' => '28/03/2026 02:15 PM',
                'description' => 'This expandable row demonstrates how inline record details can appear directly beneath the selected row.',
                'owner' => 'System Administration',
                'notes' => 'Suitable for compact admin pages where users need just enough extra information without navigating away.',
            ],
            [
                'reference_no' => 'EXP-002',
                'title' => 'Access Matrix Validation Record',
                'status' => 'Pending',
                'updated_at' => '28/03/2026 02:35 PM',
                'description' => 'Use this sample to preview expandable summaries, supporting notes, and additional metadata in one place.',
                'owner' => 'Security Team',
                'notes' => 'Developers can later replace this sample JS interaction with backend-driven row details if needed.',
            ],
            [
                'reference_no' => 'EXP-003',
                'title' => 'Generated Module Review Queue',
                'status' => 'Completed',
                'updated_at' => '28/03/2026 03:00 PM',
                'description' => 'Ideal for quick inspection flows where a modal or separate detail page would feel too heavy.',
                'owner' => 'Development Team',
                'notes' => 'This baseline keeps the page compact while still exposing more context on demand.',
            ],
        ];
    }

    // Audit hook placeholder:
    // Use this section for inline detail load or row-level actions when audit is required.
}
