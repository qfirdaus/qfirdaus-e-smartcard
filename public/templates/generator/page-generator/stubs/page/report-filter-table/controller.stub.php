<?php
declare(strict_types=1);

require_once __DIR__ . '/../classes/Database.php';
require_once __DIR__ . '/../classes/User.php';

class __CONTROLLER_CLASS__
{
    public string $lang = 'ms';
    public array $profile = [];
    public array $filters = [];
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
        $this->filters = $this->loadFilterDefaults();
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

    protected function loadFilterDefaults(): array
    {
        return [
            'from_date' => date('Y-m-01'),
            'to_date' => date('Y-m-d'),
            'status' => 'all',
            'category' => 'all',
            'keyword' => '',
        ];
    }

    /**
     * @return array<int,array<string,string>>
     */
    protected function loadRows(): array
    {
        return [
            [
                'reference_no' => 'RPT-001',
                'name' => 'Monthly Template Generation Summary',
                'category' => 'Operational',
                'status' => 'Completed',
                'updated_at' => '28/03/2026 10:20 AM',
            ],
            [
                'reference_no' => 'RPT-002',
                'name' => 'Developer Activity Snapshot',
                'category' => 'Analytics',
                'status' => 'In Review',
                'updated_at' => '28/03/2026 11:00 AM',
            ],
            [
                'reference_no' => 'RPT-003',
                'name' => 'Daily Access Matrix Verification',
                'category' => 'Security',
                'status' => 'Completed',
                'updated_at' => '28/03/2026 11:30 AM',
            ],
        ];
    }

    // Audit hook placeholder:
    // Use this section for search/export/report actions when audit is required.
}
