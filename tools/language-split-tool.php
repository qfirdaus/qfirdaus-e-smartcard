#!/usr/bin/env php
<?php
declare(strict_types=1);

/**
 * Language core/custom split validation and migration helper.
 *
 * Usage:
 *   php tools/language-split-tool.php validate
 *   php tools/language-split-tool.php validate --strict
 *   php tools/language-split-tool.php migrate
 *   php tools/language-split-tool.php migrate --dry-run
 */

final class LanguageSplitTool
{
    private string $root;
    private string $langDir;
    private bool $strict = false;
    private bool $dryRun = false;
    private bool $backup = true;
    private array $errors = [];
    private array $warnings = [];

    public function __construct(string $root)
    {
        $realRoot = realpath($root);
        if ($realRoot === false || !is_dir($realRoot)) {
            throw new RuntimeException("Invalid project root: {$root}");
        }

        $this->root = rtrim($realRoot, DIRECTORY_SEPARATOR);
        $this->langDir = $this->root . DIRECTORY_SEPARATOR . 'public' . DIRECTORY_SEPARATOR . 'lang';
    }

    public function run(array $argv): int
    {
        $command = $argv[1] ?? 'validate';
        $options = array_slice($argv, 2);
        $this->parseOptions($options);

        if (in_array($command, ['-h', '--help', 'help'], true)) {
            $this->printUsage();
            return 0;
        }

        if ($command === 'validate') {
            return $this->validate();
        }

        if ($command === 'migrate') {
            return $this->migrate();
        }

        $this->error("Unknown command: {$command}");
        $this->printUsage();
        return 2;
    }

    private function parseOptions(array $options): void
    {
        foreach ($options as $option) {
            if ($option === '--strict') {
                $this->strict = true;
                continue;
            }

            if ($option === '--dry-run') {
                $this->dryRun = true;
                continue;
            }

            if ($option === '--no-backup') {
                $this->backup = false;
                continue;
            }

            $this->warning("Ignored unknown option: {$option}");
        }
    }

    private function printUsage(): void
    {
        $this->line('Language split tool');
        $this->line('');
        $this->line('Commands:');
        $this->line('  validate          Validate core/custom language structure and key parity.');
        $this->line('  migrate           Create core/custom structure from legacy public/lang/*.php files.');
        $this->line('');
        $this->line('Options:');
        $this->line('  --strict          Return non-zero when validation warnings are found.');
        $this->line('  --dry-run         Preview migration actions without writing files.');
        $this->line('  --no-backup       Do not create backups when migrate replaces legacy root files.');
    }

    private function validate(): int
    {
        $this->line('Language split validation');
        $this->line('Project root: ' . $this->root);

        if (!is_dir($this->langDir)) {
            $this->error('Missing language directory: public/lang');
            return $this->finish();
        }

        $languages = $this->discoverLanguages();
        if ($languages === []) {
            $this->error('No language files found.');
            return $this->finish();
        }

        $this->line('');
        $this->line('Languages: ' . implode(', ', $languages));

        $data = [];
        foreach ($languages as $lang) {
            $corePath = $this->path('public/lang/core/' . $lang . '.php');
            $customPath = $this->path('public/lang/custom/' . $lang . '.php');
            $legacyPath = $this->path('public/lang/' . $lang . '.php');

            $core = $this->loadArrayFile($corePath);
            $custom = $this->loadArrayFile($customPath, true);
            $legacy = $this->loadArrayFile($legacyPath, true);
            $merged = array_replace($core, $custom);

            if (!is_file($corePath)) {
                $this->warning("{$lang}: missing core file public/lang/core/{$lang}.php");
            }

            if (!is_file($customPath)) {
                $this->warning("{$lang}: missing custom file public/lang/custom/{$lang}.php");
            }

            if (is_file($legacyPath) && $legacy !== [] && $merged !== [] && $legacy !== $merged) {
                $legacyKeys = array_keys($legacy);
                $mergedKeys = array_keys($merged);
                if ($legacyKeys !== $mergedKeys) {
                    $this->warning("{$lang}: legacy wrapper/public file key set differs from merged core+custom.");
                }
            }

            foreach ([$corePath, $customPath, $legacyPath] as $path) {
                if (is_file($path)) {
                    $duplicates = $this->detectDuplicateLiteralKeys($path);
                    foreach ($duplicates as $key => $count) {
                        $this->warning($this->relative($path) . ": duplicate literal key '{$key}' appears {$count} times.");
                    }
                }
            }

            $overrideKeys = array_intersect_key($custom, $core);
            $customOnlyKeys = array_diff_key($custom, $core);

            $data[$lang] = [
                'core' => $core,
                'custom' => $custom,
                'merged' => $merged,
                'override_count' => count($overrideKeys),
                'custom_only_count' => count($customOnlyKeys),
            ];

            $this->line(sprintf(
                '%s: core=%d custom=%d merged=%d overrides=%d custom_only=%d',
                $lang,
                count($core),
                count($custom),
                count($merged),
                count($overrideKeys),
                count($customOnlyKeys)
            ));
        }

        $this->validateParity($data, 'core');
        $this->validateParity($data, 'merged');

        return $this->finish();
    }

    private function migrate(): int
    {
        $this->line('Language split migration');
        $this->line('Project root: ' . $this->root);
        if ($this->dryRun) {
            $this->line('Mode: dry-run');
        }

        if (!is_dir($this->langDir)) {
            $this->error('Missing language directory: public/lang');
            return $this->finish();
        }

        $legacyFiles = $this->discoverLegacyRootLanguageFiles();
        if ($legacyFiles === []) {
            $this->warning('No legacy public/lang/*.php files found.');
            return $this->finish();
        }

        $this->ensureDirectory($this->path('public/lang/core'));
        $this->ensureDirectory($this->path('public/lang/custom'));

        $backupDir = null;
        if ($this->backup && !$this->dryRun) {
            $backupDir = $this->path('backups/language-split-' . date('Ymd-His'));
            $this->ensureDirectory($backupDir);
        }

        foreach ($legacyFiles as $lang => $legacyPath) {
            $corePath = $this->path('public/lang/core/' . $lang . '.php');
            $customPath = $this->path('public/lang/custom/' . $lang . '.php');
            $wrapperPath = $this->path('public/lang/' . $lang . '.php');

            if (!is_file($corePath)) {
                $this->copyFile($legacyPath, $corePath);
            } else {
                $this->line("SKIP core exists: public/lang/core/{$lang}.php");
            }

            if (!is_file($customPath)) {
                $this->writeFile($customPath, $this->emptyCustomFile($lang));
            } else {
                $this->line("SKIP custom exists: public/lang/custom/{$lang}.php");
            }

            if ($backupDir !== null && is_file($wrapperPath)) {
                $this->copyFile($wrapperPath, $backupDir . DIRECTORY_SEPARATOR . $lang . '.php');
            }

            $wrapperContent = $this->wrapperFile($lang);
            $currentWrapper = is_file($wrapperPath) ? (string)file_get_contents($wrapperPath) : '';
            if ($currentWrapper !== $wrapperContent) {
                $this->writeFile($wrapperPath, $wrapperContent);
            } else {
                $this->line("SKIP wrapper already current: public/lang/{$lang}.php");
            }
        }

        return $this->finish();
    }

    private function validateParity(array $data, string $bucket): void
    {
        $languages = array_keys($data);
        $baseLang = in_array('ms', $languages, true) ? 'ms' : $languages[0];
        $base = $data[$baseLang][$bucket] ?? [];

        foreach ($data as $lang => $sets) {
            if ($lang === $baseLang) {
                continue;
            }

            $target = $sets[$bucket] ?? [];
            $missing = array_diff_key($base, $target);
            $extra = array_diff_key($target, $base);

            if ($missing !== []) {
                $this->warning(sprintf(
                    '%s parity: %s is missing %d key(s) from base %s. Sample: %s',
                    $bucket,
                    $lang,
                    count($missing),
                    $baseLang,
                    implode(', ', array_slice(array_keys($missing), 0, 8))
                ));
            }

            if ($extra !== []) {
                $this->warning(sprintf(
                    '%s parity: %s has %d extra key(s) not in base %s. Sample: %s',
                    $bucket,
                    $lang,
                    count($extra),
                    $baseLang,
                    implode(', ', array_slice(array_keys($extra), 0, 8))
                ));
            }
        }
    }

    private function discoverLanguages(): array
    {
        $langs = [];
        foreach ([
            $this->path('public/lang/core'),
            $this->path('public/lang/custom'),
            $this->path('public/lang'),
        ] as $dir) {
            if (!is_dir($dir)) {
                continue;
            }

            foreach (glob($dir . DIRECTORY_SEPARATOR . '*.php') ?: [] as $path) {
                $name = basename($path, '.php');
                if (preg_match('/^[a-z]{2}(?:-[A-Z]{2})?$/', $name) === 1) {
                    $langs[$name] = true;
                }
            }
        }

        $result = array_keys($langs);
        sort($result, SORT_STRING);
        return $result;
    }

    /**
     * @return array<string,string>
     */
    private function discoverLegacyRootLanguageFiles(): array
    {
        $files = [];
        foreach (glob($this->path('public/lang') . DIRECTORY_SEPARATOR . '*.php') ?: [] as $path) {
            $lang = basename($path, '.php');
            if (preg_match('/^[a-z]{2}(?:-[A-Z]{2})?$/', $lang) === 1) {
                $files[$lang] = $path;
            }
        }
        ksort($files, SORT_STRING);
        return $files;
    }

    /**
     * @return array<string,string>
     */
    private function loadArrayFile(string $path, bool $allowMissing = false): array
    {
        if (!is_file($path)) {
            if (!$allowMissing) {
                $this->warning('Missing file: ' . $this->relative($path));
            }
            return [];
        }

        try {
            $loaded = require $path;
        } catch (Throwable $e) {
            $this->error('Failed loading ' . $this->relative($path) . ': ' . $e->getMessage());
            return [];
        }

        if (!is_array($loaded)) {
            $this->warning('File does not return an array: ' . $this->relative($path));
            return [];
        }

        return $loaded;
    }

    /**
     * @return array<string,int>
     */
    private function detectDuplicateLiteralKeys(string $path): array
    {
        $content = (string)file_get_contents($path);
        if ($content === '') {
            return [];
        }

        $keys = [];
        foreach ([
            "/'((?:\\\\'|[^'])*)'\\s*=>/",
            '/"((?:\\\\"|[^"])*)"\\s*=>/',
        ] as $pattern) {
            if (preg_match_all($pattern, $content, $matches)) {
                foreach ($matches[1] as $rawKey) {
                    $key = stripcslashes((string)$rawKey);
                    $keys[$key] = ($keys[$key] ?? 0) + 1;
                }
            }
        }

        return array_filter($keys, static fn(int $count): bool => $count > 1);
    }

    private function emptyCustomFile(string $lang): string
    {
        $label = $lang === 'ms' ? 'Malay' : ($lang === 'en' ? 'English' : strtoupper($lang));
        return "<?php\n\nreturn [\n    // Project-specific {$label} translations.\n    // Keys in this file override public/lang/core/{$lang}.php.\n];\n";
    }

    private function wrapperFile(string $lang): string
    {
        return "<?php\n\n"
            . "\$coreFile = __DIR__ . '/core/{$lang}.php';\n"
            . "\$customFile = __DIR__ . '/custom/{$lang}.php';\n\n"
            . "\$core = is_file(\$coreFile) ? require \$coreFile : [];\n"
            . "\$custom = is_file(\$customFile) ? require \$customFile : [];\n\n"
            . "return array_replace(\n"
            . "    is_array(\$core) ? \$core : [],\n"
            . "    is_array(\$custom) ? \$custom : []\n"
            . ");\n";
    }

    private function ensureDirectory(string $path): void
    {
        if ($this->dryRun) {
            $this->line('DRY mkdir: ' . $this->relative($path));
            return;
        }

        if (!is_dir($path) && !mkdir($path, 0777, true) && !is_dir($path)) {
            $this->error('Failed creating directory: ' . $this->relative($path));
        }
    }

    private function copyFile(string $source, string $target): void
    {
        if ($this->dryRun) {
            $this->line('DRY copy: ' . $this->relative($source) . ' -> ' . $this->relative($target));
            return;
        }

        $this->ensureDirectory(dirname($target));
        if (!copy($source, $target)) {
            $this->error('Failed copying ' . $this->relative($source) . ' -> ' . $this->relative($target));
            return;
        }

        $this->line('COPIED: ' . $this->relative($source) . ' -> ' . $this->relative($target));
    }

    private function writeFile(string $path, string $content): void
    {
        if ($this->dryRun) {
            $this->line('DRY write: ' . $this->relative($path));
            return;
        }

        $this->ensureDirectory(dirname($path));
        if (file_put_contents($path, $content) === false) {
            $this->error('Failed writing file: ' . $this->relative($path));
            return;
        }

        $this->line('WROTE: ' . $this->relative($path));
    }

    private function finish(): int
    {
        $this->line('');

        foreach ($this->warnings as $warning) {
            $this->line('WARNING: ' . $warning);
        }

        foreach ($this->errors as $error) {
            $this->line('ERROR: ' . $error);
        }

        $this->line(sprintf(
            'Result: %d error(s), %d warning(s)',
            count($this->errors),
            count($this->warnings)
        ));

        if ($this->errors !== []) {
            return 1;
        }

        if ($this->strict && $this->warnings !== []) {
            return 1;
        }

        return 0;
    }

    private function path(string $relative): string
    {
        return $this->root . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $relative);
    }

    private function relative(string $path): string
    {
        $normalRoot = rtrim(str_replace('\\', '/', $this->root), '/');
        $normalPath = str_replace('\\', '/', $path);
        if (str_starts_with($normalPath, $normalRoot . '/')) {
            return substr($normalPath, strlen($normalRoot) + 1);
        }
        return $path;
    }

    private function line(string $message): void
    {
        echo $message . PHP_EOL;
    }

    private function warning(string $message): void
    {
        $this->warnings[] = $message;
    }

    private function error(string $message): void
    {
        $this->errors[] = $message;
    }
}

try {
    $tool = new LanguageSplitTool(dirname(__DIR__));
    exit($tool->run($argv));
} catch (Throwable $e) {
    fwrite(STDERR, 'ERROR: ' . $e->getMessage() . PHP_EOL);
    exit(1);
}
