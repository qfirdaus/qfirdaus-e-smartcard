#!/usr/bin/env php
<?php
declare(strict_types=1);

/**
 * Core file protection audit helper.
 *
 * Usage:
 *   php tools/core-file-protection-audit.php
 *   php tools/core-file-protection-audit.php --strict
 */

final class CoreFileProtectionAudit
{
    private const CORE_MARKER = 'IQS FRAMEWORK CORE FILE';
    private const GENERATED_MARKER = 'PROJECT GENERATED FILE';
    private const ACTIVE_PAGES = [
        'access-matrix.php',
        'audit-center.php',
        'dashboard.php',
        'developer-guide.php',
        'kumpulan-pengguna.php',
        'manage-manuals.php',
        'notification-admin.php',
        'notification-templates.php',
        'notifications.php',
        'profile.php',
        'senarai-pengguna.php',
        'soalan-lazim.php',
        'system-cache.php',
        'template-emel.php',
        'template-generator.php',
        'tetapan-sistem.php',
    ];
    private const ACTIVE_CONTROLLERS = [
        'AccessController.php',
        'AuditCenterController.php',
        'DashboardController.php',
        'EmailTemplateController.php',
        'GroupController.php',
        'LoginController.php',
        'LogoutController.php',
        'ManualController.php',
        'ProfileController.php',
        'SidebarController.php',
        'SystemTemplateController.php',
        'SystemCacheMaintenanceController.php',
        'TemplateGeneratorController.php',
        'TetapanSistemController.php',
        'UserListController.php',
    ];

    private string $root;
    private bool $strict = false;
    /** @var string[] */
    private array $errors = [];
    /** @var string[] */
    private array $warnings = [];

    public function __construct(string $root)
    {
        $realRoot = realpath($root);
        if ($realRoot === false || !is_dir($realRoot)) {
            throw new RuntimeException("Invalid project root: {$root}");
        }

        $this->root = rtrim($realRoot, DIRECTORY_SEPARATOR);
    }

    public function run(array $argv): int
    {
        foreach (array_slice($argv, 1) as $option) {
            if ($option === '--strict') {
                $this->strict = true;
                continue;
            }

            if (in_array($option, ['-h', '--help', 'help'], true)) {
                $this->printUsage();
                return 0;
            }

            $this->warnings[] = "Ignored unknown option: {$option}";
        }

        $this->line('Core file protection audit');
        $this->line('Project root: ' . $this->root);
        $this->line('');

        $this->auditCorePages();
        $this->auditActiveControllers();
        $this->auditAjaxEndpoints();
        $this->auditClasses();
        $this->auditPhaseNineCoreFiles();
        $this->auditGeneratedFiles();

        return $this->finish();
    }

    private function printUsage(): void
    {
        $this->line('Core file protection audit');
        $this->line('');
        $this->line('Usage:');
        $this->line('  php tools/core-file-protection-audit.php');
        $this->line('  php tools/core-file-protection-audit.php --strict');
        $this->line('');
        $this->line('Options:');
        $this->line('  --strict    Return non-zero when warnings are found.');
    }

    private function auditCorePages(): void
    {
        $this->line('Protected page scan: ' . count(self::ACTIVE_PAGES) . ' file(s)');

        foreach (self::ACTIVE_PAGES as $page) {
            $path = $this->root . DIRECTORY_SEPARATOR . 'public' . DIRECTORY_SEPARATOR . 'pages' . DIRECTORY_SEPARATOR . $page;
            $relative = 'public/pages/' . $page;
            if (!is_file($path)) {
                $this->errors[] = "Missing active page: {$relative}";
                continue;
            }
            $content = (string)file_get_contents($path);
            if (!str_contains($content, self::CORE_MARKER)) {
                $this->errors[] = "Missing core marker: {$relative}";
                continue;
            }

            if (str_contains($content, self::GENERATED_MARKER)) {
                $this->errors[] = "Core page also has generated marker: {$relative}";
            }
        }
    }

    private function auditActiveControllers(): void
    {
        $this->line('Protected active controller scan: ' . count(self::ACTIVE_CONTROLLERS) . ' file(s)');

        foreach (self::ACTIVE_CONTROLLERS as $controller) {
            $path = $this->root . DIRECTORY_SEPARATOR . 'public' . DIRECTORY_SEPARATOR . 'controllers' . DIRECTORY_SEPARATOR . $controller;
            $relative = 'public/controllers/' . $controller;
            if (!is_file($path)) {
                $this->errors[] = "Missing active controller: {$relative}";
                continue;
            }

            $content = (string)file_get_contents($path);
            if (!str_contains($content, self::CORE_MARKER)) {
                $this->errors[] = "Missing core marker: {$relative}";
                continue;
            }

            if (str_contains($content, self::GENERATED_MARKER)) {
                $this->errors[] = "Active controller also has generated marker: {$relative}";
            }
        }
    }

    private function auditAjaxEndpoints(): void
    {
        $files = $this->glob('public/ajax/*.php');
        $this->line('Protected AJAX scan: ' . count($files) . ' file(s)');

        foreach ($files as $path) {
            $relative = $this->relative($path);
            $content = (string)file_get_contents($path);
            if (!str_contains($content, self::CORE_MARKER) && !str_contains($content, self::GENERATED_MARKER)) {
                $this->errors[] = "Missing core/generated marker: {$relative}";
            }
        }
    }

    private function auditClasses(): void
    {
        $files = $this->glob('public/classes/*.php');
        $this->line('Protected class/service scan: ' . count($files) . ' file(s)');

        foreach ($files as $path) {
            $relative = $this->relative($path);
            $content = (string)file_get_contents($path);
            if (!str_contains($content, self::CORE_MARKER) && !str_contains($content, self::GENERATED_MARKER)) {
                $this->errors[] = "Missing core/generated marker: {$relative}";
            }
        }
    }

    private function auditPhaseNineCoreFiles(): void
    {
        $patterns = [
            'public/includes/*.php',
            'public/setting/**/*.php',
            'public/configuration/*.php',
            'public/*.php',
            'public/lang/*.php',
            'public/lang/core/*.php',
        ];

        $files = [];
        foreach ($patterns as $pattern) {
            foreach ($this->glob($pattern) as $path) {
                $files[$path] = true;
            }
        }

        $paths = array_keys($files);
        sort($paths);
        $this->line('Protected bootstrap/config/lang/root scan: ' . count($paths) . ' file(s)');

        foreach ($paths as $path) {
            $relative = $this->relative($path);
            if (str_starts_with($relative, 'public/lang/custom/')) {
                continue;
            }

            $content = (string)file_get_contents($path);
            if (!str_contains($content, self::CORE_MARKER)) {
                $this->errors[] = "Missing core marker: {$relative}";
            }
        }
    }

    private function auditGeneratedFiles(): void
    {
        $patterns = [
            'public/assets/css/pages/*.css',
        ];

        $checked = 0;
        foreach ($patterns as $pattern) {
            foreach ($this->glob($pattern) as $path) {
                $checked++;
                $content = (string)file_get_contents($path);
                if (str_contains($content, self::CORE_MARKER)) {
                    $this->warnings[] = 'Generated/custom asset has core marker: ' . $this->relative($path);
                }
            }
        }

        $this->line('Generated/custom asset marker scan: ' . $checked . ' file(s)');
    }

    /**
     * @return string[]
     */
    private function glob(string $pattern): array
    {
        $globPattern = $this->root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $pattern);
        if (str_contains($pattern, '**')) {
            return $this->recursiveGlob($globPattern);
        }

        $paths = glob($globPattern);
        if (!is_array($paths)) {
            return [];
        }

        sort($paths);
        return $paths;
    }

    /**
     * @return string[]
     */
    private function recursiveGlob(string $pattern): array
    {
        $parts = explode('**', $pattern, 2);
        $base = rtrim($parts[0], DIRECTORY_SEPARATOR);
        $tail = ltrim($parts[1] ?? '', DIRECTORY_SEPARATOR);
        if (!is_dir($base)) {
            return [];
        }

        $paths = [];
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($base, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST
        );
        foreach ($iterator as $item) {
            if (!$item instanceof SplFileInfo || !$item->isDir()) {
                continue;
            }
            $candidatePattern = $item->getPathname() . DIRECTORY_SEPARATOR . $tail;
            $matches = glob($candidatePattern);
            if (is_array($matches)) {
                array_push($paths, ...$matches);
            }
        }

        $rootMatches = glob($base . DIRECTORY_SEPARATOR . $tail);
        if (is_array($rootMatches)) {
            array_push($paths, ...$rootMatches);
        }

        $paths = array_values(array_unique($paths));
        sort($paths);
        return $paths;
    }

    private function relative(string $path): string
    {
        $relative = str_replace($this->root . DIRECTORY_SEPARATOR, '', $path);
        return str_replace(DIRECTORY_SEPARATOR, '/', $relative);
    }

    private function finish(): int
    {
        $this->line('');

        foreach ($this->warnings as $warning) {
            $this->line('[WARN] ' . $warning);
        }

        foreach ($this->errors as $error) {
            $this->line('[FAIL] ' . $error);
        }

        if ($this->errors !== []) {
            $this->line('');
            $this->line('Result: FAIL');
            return 1;
        }

        if ($this->strict && $this->warnings !== []) {
            $this->line('');
            $this->line('Result: WARN');
            return 1;
        }

        $this->line('Result: PASS');
        return 0;
    }

    private function line(string $message): void
    {
        echo $message, PHP_EOL;
    }
}

try {
    $tool = new CoreFileProtectionAudit(dirname(__DIR__));
    exit($tool->run($argv));
} catch (Throwable $e) {
    fwrite(STDERR, '[ERROR] ' . $e->getMessage() . PHP_EOL);
    exit(1);
}
