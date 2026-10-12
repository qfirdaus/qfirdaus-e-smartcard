<?php
/**
 * IQS FRAMEWORK CORE FILE
 *
 * READ ONLY for downstream project programmers.
 * Do not modify this file directly in template or cloned projects.
 * Custom changes must be implemented in project-specific files
 * or approved extension points.
 */declare(strict_types=1);

require_once __DIR__ . '/TemplateResolverService.php';

final class FileGenerationService
{
    public const ACCESS_MODE_GROUP_MENU = 'group_menu_based';
    public const ACCESS_MODE_SUPER_ADMIN_ONLY = 'super_admin_only';

    private TemplateResolverService $resolver;
    private string $projectRoot;

    public function __construct(?TemplateResolverService $resolver = null, ?string $projectRoot = null)
    {
        $this->resolver = $resolver ?: new TemplateResolverService();
        $configuredRoot = rtrim($projectRoot ?: dirname(__DIR__, 1), '/\\');
        $resolvedRoot = realpath($configuredRoot);
        if ($resolvedRoot === false || !is_dir($resolvedRoot)) {
            throw new RuntimeException('Invalid template generation root.');
        }
        $this->projectRoot = rtrim($resolvedRoot, '/\\');
    }

    /**
     * @param array<string,string> $input
     * @return array<string,mixed>
     */
    public function preview(string $templateKey, array $input): array
    {
        $template = $this->resolver->resolveTemplate($templateKey);
        $normalized = $this->normalizeInput($input);
        $outputPaths = $this->buildOutputPaths($normalized);

        return [
            'template' => $template['key'],
            'template_label' => $template['label'],
            'template_version' => $template['version'],
            'page_slug' => $normalized['page_slug'],
            'page_key_prefix' => $normalized['page_key_prefix'],
            'controller_class' => $normalized['controller_class'],
            'page_icon' => $normalized['page_icon'],
            'access_mode' => $normalized['access_mode'],
            'titles' => [
                'ms' => $normalized['page_title_ms'],
                'en' => $normalized['page_title_en'],
            ],
            'files' => [
                'page' => $outputPaths['page'],
                'controller' => $outputPaths['controller'],
                'css' => $outputPaths['css'],
            ],
            'exists' => [
                'page' => is_file($outputPaths['page']),
                'controller' => is_file($outputPaths['controller']),
                'css' => is_file($outputPaths['css']),
            ],
        ];
    }

    /**
     * @param array<string,string> $input
     * @return array<string,mixed>
     */
    public function generate(string $templateKey, array $input): array
    {
        $filesResult = $this->generateFilesOnly($templateKey, $input);
        $langResult = $this->appendLanguageEntriesForTemplate($templateKey, $input);

        return [
            'template' => $filesResult['template'],
            'template_label' => $filesResult['template_label'],
            'template_version' => $filesResult['template_version'],
            'page_slug' => $filesResult['page_slug'],
            'page_key_prefix' => $filesResult['page_key_prefix'],
            'controller_class' => $filesResult['controller_class'],
            'page_icon' => $filesResult['page_icon'],
            'access_mode' => $filesResult['access_mode'],
            'files_created' => [
                ...$filesResult['files_created'],
                ...$langResult['lang_files_updated'],
            ],
        ];
    }

    /**
     * @param array<string,string> $input
     * @return array<string,mixed>
     */
    public function generateFilesOnly(string $templateKey, array $input): array
    {
        $template = $this->resolver->resolveTemplate($templateKey);
        $normalized = $this->normalizeInput($input);
        $outputPaths = $this->buildOutputPaths($normalized);
        $this->assertOutputDoesNotExist($outputPaths);

        $replacements = [
            '__PAGE_TITLE_MS__' => $normalized['page_title_ms'],
            '__PAGE_TITLE_EN__' => $normalized['page_title_en'],
            '__PAGE_SLUG__' => $normalized['page_slug'],
            '__PAGE_KEY_PREFIX__' => $normalized['page_key_prefix'],
            '__CONTROLLER_CLASS__' => $normalized['controller_class'],
            '__PAGE_ICON__' => $normalized['page_icon'],
        ];

        $pageContent = $this->addGeneratedFileHeader(
            $this->renderStub((string)$template['paths']['page_stub'], $replacements),
            'php'
        );
        $controllerContent = $this->addGeneratedFileHeader(
            $this->renderStub((string)$template['paths']['controller_stub'], $replacements),
            'php'
        );
        $cssContent = $this->addGeneratedFileHeader(
            $this->renderStub((string)$template['paths']['css_stub'], $replacements),
            'css'
        );

        $createdFiles = [];
        try {
            $this->writeFile($outputPaths['page'], $pageContent);
            $createdFiles[] = $outputPaths['page'];
            $this->writeFile($outputPaths['controller'], $controllerContent);
            $createdFiles[] = $outputPaths['controller'];
            $this->writeFile($outputPaths['css'], $cssContent);
            $createdFiles[] = $outputPaths['css'];
        } catch (Throwable $e) {
            $this->rollbackGeneratedFiles($createdFiles);
            throw $e;
        }

        return [
            'template' => $template['key'],
            'template_label' => $template['label'],
            'template_version' => $template['version'],
            'page_slug' => $normalized['page_slug'],
            'page_key_prefix' => $normalized['page_key_prefix'],
            'controller_class' => $normalized['controller_class'],
            'page_icon' => $normalized['page_icon'],
            'access_mode' => $normalized['access_mode'],
            'files_created' => [
                $outputPaths['page'],
                $outputPaths['controller'],
                $outputPaths['css'],
            ],
            'files' => [
                'page' => $outputPaths['page'],
                'controller' => $outputPaths['controller'],
                'css' => $outputPaths['css'],
            ],
        ];
    }

    /**
     * @param array<string,string> $input
     * @return array<string,mixed>
     */
    public function appendLanguageEntriesForTemplate(string $templateKey, array $input): array
    {
        $template = $this->resolver->resolveTemplate($templateKey);
        $normalized = $this->normalizeInput($input);
        $langEntries = $this->buildTemplateLanguageEntries($template['key'], $normalized);
        $this->assertLanguageKeysDoNotExist($langEntries);
        $langFiles = $this->appendLanguageEntries($langEntries, $normalized);

        return [
            'template' => $template['key'],
            'page_slug' => $normalized['page_slug'],
            'page_key_prefix' => $normalized['page_key_prefix'],
            'lang_files_updated' => $langFiles,
        ];
    }

    /**
     * @param array<int,string> $files
     */
    public function rollbackGeneratedFiles(array $files): void
    {
        foreach ($files as $path) {
            $path = trim((string)$path);
            if ($path !== '' && $this->isPathWithinProjectRoot($path) && is_file($path)) {
                @unlink($path);
            }
        }
    }

    public function rollbackLanguageBlocks(string $pageKeyPrefix): void
    {
        $pageKeyPrefix = trim($pageKeyPrefix);
        if ($pageKeyPrefix === '') {
            return;
        }

        foreach ($this->getWritableLanguageFilePaths() as $langPath) {
            $this->removeLanguageBlock($langPath, $pageKeyPrefix);
        }
    }

    /**
     * @param array<string,string> $input
     * @return array<string,string>
     */
    private function normalizeInput(array $input): array
    {
        $pageName = trim((string)($input['page_name'] ?? ''));
        $pageTitleMs = trim((string)($input['page_title_ms'] ?? ''));
        $pageTitleEn = trim((string)($input['page_title_en'] ?? ''));
        $pageIcon = trim((string)($input['page_icon'] ?? ''));
        $accessMode = trim((string)($input['access_mode'] ?? self::ACCESS_MODE_GROUP_MENU));

        if ($pageName === '') {
            throw new InvalidArgumentException('Page name is required.');
        }

        $pageSlug = $this->normalizeSlug($pageName);
        if ($pageSlug === '') {
            throw new InvalidArgumentException('Page name could not be normalized into a valid slug.');
        }

        if ($pageTitleMs === '') {
            $pageTitleMs = $this->humanizeSlug($pageSlug);
        }

        if ($pageTitleEn === '') {
            $pageTitleEn = $pageTitleMs;
        }

        if ($pageIcon === '') {
            $pageIcon = 'ri-file-list-line';
        }

        if (mb_strlen($pageName) > 80 || mb_strlen($pageTitleMs) > 160 || mb_strlen($pageTitleEn) > 160) {
            throw new InvalidArgumentException('Template field exceeds the permitted length.');
        }

        if (!preg_match('/^ri-[a-z0-9-]+$/', $pageIcon) || mb_strlen($pageIcon) > 80) {
            throw new InvalidArgumentException('Invalid page icon.');
        }

        if (!in_array($accessMode, [self::ACCESS_MODE_GROUP_MENU, self::ACCESS_MODE_SUPER_ADMIN_ONLY], true)) {
            throw new InvalidArgumentException('Invalid access mode for generated page.');
        }

        return [
            'page_name' => $pageName,
            'page_slug' => $pageSlug,
            'page_key_prefix' => str_replace('-', '_', $pageSlug),
            'page_title_ms' => $pageTitleMs,
            'page_title_en' => $pageTitleEn,
            'page_icon' => $pageIcon,
            'access_mode' => $accessMode,
            'controller_class' => $this->buildControllerClassName($pageSlug),
        ];
    }

    private function normalizeSlug(string $value): string
    {
        $value = strtolower(trim($value));
        $value = preg_replace('/[^a-z0-9]+/i', '-', $value) ?? '';
        $value = trim($value, '-');
        return $value;
    }

    private function humanizeSlug(string $slug): string
    {
        return ucwords(str_replace('-', ' ', $slug));
    }

    private function buildControllerClassName(string $slug): string
    {
        $parts = array_values(array_filter(explode('-', $slug), static fn($part) => $part !== ''));
        $class = implode('', array_map(static fn($part) => ucfirst(strtolower($part)), $parts));
        if ($class === '') {
            throw new InvalidArgumentException('Controller class name could not be derived from slug.');
        }
        return $class . 'Controller';
    }

    /**
     * @param array<string,string> $normalized
     * @return array<string,string>
     */
    private function buildOutputPaths(array $normalized): array
    {
        return [
            'page' => $this->projectRoot . '/pages/' . $normalized['page_slug'] . '.php',
            'controller' => $this->projectRoot . '/controllers/' . $normalized['controller_class'] . '.php',
            'css' => $this->projectRoot . '/assets/css/pages/' . $normalized['page_slug'] . '.css',
        ];
    }

    /**
     * @param array<string,string> $outputPaths
     */
    private function assertOutputDoesNotExist(array $outputPaths): void
    {
        foreach ($outputPaths as $type => $path) {
            if (is_file($path)) {
                throw new RuntimeException("Target {$type} file already exists: {$path}");
            }
        }
    }

    /**
     * @param array<string,string> $replacements
     */
    private function renderStub(string $stubPath, array $replacements): string
    {
        if (!is_file($stubPath)) {
            throw new RuntimeException("Stub file not found: {$stubPath}");
        }

        $content = (string)file_get_contents($stubPath);
        if ($content === '') {
            throw new RuntimeException("Stub file is empty: {$stubPath}");
        }

        return strtr($content, $replacements);
    }

    private function writeFile(string $path, string $content): void
    {
        $this->assertPathWithinProjectRoot($path);
        $dir = dirname($path);
        if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
            throw new RuntimeException("Failed to create output directory: {$dir}");
        }

        $handle = @fopen($path, 'x+b');
        if ($handle === false) {
            throw new RuntimeException("Generated file already exists or cannot be created: {$path}");
        }

        $written = false;
        try {
            if (!flock($handle, LOCK_EX)) {
                throw new RuntimeException("Failed to lock generated file: {$path}");
            }
            $written = $this->writeAll($handle, $content);
            fflush($handle);
            flock($handle, LOCK_UN);
        } catch (Throwable $e) {
            fclose($handle);
            @unlink($path);
            throw $e;
        } finally {
            if (is_resource($handle)) {
                fclose($handle);
            }
        }

        if (!$written) {
            @unlink($path);
            throw new RuntimeException("Failed to write generated file: {$path}");
        }
    }

    private function addGeneratedFileHeader(string $content, string $type): string
    {
        if (str_contains($content, 'PROJECT GENERATED FILE')) {
            return $content;
        }

        if ($type === 'php') {
            $header = "/**\n"
                . " * PROJECT GENERATED FILE\n"
                . " *\n"
                . " * Safe to customize for this downstream project.\n"
                . " * Generated from an IQS Framework template.\n"
                . " */\n";

            if (str_starts_with($content, "<?php\n")) {
                return "<?php\n" . $header . substr($content, 6);
            }

            if (str_starts_with($content, "<?php\r\n")) {
                return "<?php\r\n" . str_replace("\n", "\r\n", $header) . substr($content, 7);
            }

            return "<?php\n" . $header . preg_replace('/^<\?php\s*/', '', $content);
        }

        if ($type === 'css') {
            return "/*\n"
                . " * PROJECT GENERATED FILE\n"
                . " *\n"
                . " * Safe to customize for this downstream project.\n"
                . " * Generated from an IQS Framework template.\n"
                . " */\n"
                . $content;
        }

        return $content;
    }

    /**
     * @param array<string,array<string,string>> $entriesByLang
     */
    private function assertLanguageKeysDoNotExist(array $entriesByLang): void
    {
        foreach (array_keys($this->getWritableLanguageFilePaths()) as $langCode) {
            $lines = $this->loadMergedLanguageLines($langCode);

            foreach (array_keys($entriesByLang[$langCode] ?? []) as $key) {
                if (array_key_exists((string)$key, $lines)) {
                    throw new RuntimeException("Language key already exists in {$langCode}: {$key}");
                }
            }
        }
    }

    /**
     * @param array<string,array<string,string>> $entriesByLang
     * @param array<string,string> $normalized
     * @return array<int,string>
     */
    private function appendLanguageEntries(array $entriesByLang, array $normalized): array
    {
        $updatedFiles = [];
        foreach ($this->getWritableLanguageFilePaths() as $langCode => $langPath) {
            $entries = $entriesByLang[$langCode] ?? [];
            $this->appendPhpArrayEntries($langPath, $entries, $normalized, $langCode);
            $updatedFiles[] = $langPath;
        }

        return $updatedFiles;
    }

    /**
     * @return array<string,string>
     */
    private function getWritableLanguageFilePaths(): array
    {
        return [
            'ms' => $this->projectRoot . '/lang/custom/ms.php',
            'en' => $this->projectRoot . '/lang/custom/en.php',
        ];
    }

    /**
     * @return array<string,string>
     */
    private function loadMergedLanguageLines(string $langCode): array
    {
        $corePath = $this->projectRoot . '/lang/core/' . $langCode . '.php';
        $customPath = $this->projectRoot . '/lang/custom/' . $langCode . '.php';

        $core = is_file($corePath) ? require $corePath : [];
        $custom = is_file($customPath) ? require $customPath : [];

        return array_replace(
            is_array($core) ? $core : [],
            is_array($custom) ? $custom : []
        );
    }

    /**
     * @param array<string,string> $entries
     * @param array<string,string> $normalized
     */
    private function appendPhpArrayEntries(string $path, array $entries, array $normalized, string $langCode): void
    {
        $this->assertPathWithinProjectRoot($path);
        if (!is_file($path)) {
            $dir = dirname($path);
            if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
                throw new RuntimeException("Failed to create language directory: {$dir}");
            }
            if (file_put_contents($path, "<?php\n\nreturn [\n];\n") === false) {
                throw new RuntimeException("Failed to create language file: {$path}");
            }
        }

        $handle = fopen($path, 'c+b');
        if ($handle === false || !flock($handle, LOCK_EX)) {
            if (is_resource($handle)) {
                fclose($handle);
            }
            throw new RuntimeException("Failed to lock language file: {$path}");
        }

        try {
            rewind($handle);
            $content = stream_get_contents($handle);
            if (!is_string($content) || $content === '') {
                throw new RuntimeException("Language file is empty: {$path}");
            }

        $closingPos = strrpos($content, '];');
        if ($closingPos === false) {
            throw new RuntimeException("Language file has invalid structure: {$path}");
        }

        $blockTitle = $langCode === 'ms'
            ? ($normalized['page_title_ms'] ?: $normalized['page_slug'])
            : ($normalized['page_title_en'] ?: $normalized['page_slug']);
        $commentHeader = "\n// ===== BEGIN Generated Template: {$blockTitle} [{$normalized['page_key_prefix']}] =====\n";
        $commentFooter = "// ===== END Generated Template: {$blockTitle} [{$normalized['page_key_prefix']}] =====\n";

        $entryLines = '';
        foreach ($entries as $key => $value) {
            $entryLines .= "'" . $key . "' => " . var_export($value, true) . ",\n";
        }

        $newContent = substr($content, 0, $closingPos) . $commentHeader . $entryLines . $commentFooter . substr($content, $closingPos);

            rewind($handle);
            if (!ftruncate($handle, 0) || !$this->writeAll($handle, $newContent)) {
                throw new RuntimeException("Failed to update language file: {$path}");
            }
            fflush($handle);
        } finally {
            flock($handle, LOCK_UN);
            fclose($handle);
        }
    }

    private function removeLanguageBlock(string $path, string $pageKeyPrefix): void
    {
        if (!is_file($path)) {
            return;
        }

        $content = (string)file_get_contents($path);
        if ($content === '') {
            return;
        }

        $pattern = "/\\n\\/\\/ ===== BEGIN Generated Template: .* \\[" . preg_quote($pageKeyPrefix, '/') . "\\] =====\\n.*?\\/\\/ ===== END Generated Template: .* \\[" . preg_quote($pageKeyPrefix, '/') . "\\] =====\\n/s";
        $updated = preg_replace($pattern, "\n", $content);
        if ($updated === null || $updated === $content) {
            return;
        }

        $this->atomicReplaceExistingFile($path, $updated);
    }

    private function isPathWithinProjectRoot(string $path): bool
    {
        $normalized = str_replace('\\', '/', $path);
        $root = str_replace('\\', '/', $this->projectRoot) . '/';
        return str_starts_with($normalized, $root) && !str_contains($normalized, '/../');
    }

    private function assertPathWithinProjectRoot(string $path): void
    {
        if (!$this->isPathWithinProjectRoot($path)) {
            throw new RuntimeException('Template output path is outside the permitted project root.');
        }
    }

    private function atomicReplaceExistingFile(string $path, string $content): void
    {
        $this->assertPathWithinProjectRoot($path);
        $handle = fopen($path, 'c+b');
        if ($handle === false || !flock($handle, LOCK_EX)) {
            if (is_resource($handle)) {
                fclose($handle);
            }
            throw new RuntimeException("Failed to lock file: {$path}");
        }
        try {
            rewind($handle);
            if (!ftruncate($handle, 0) || !$this->writeAll($handle, $content)) {
                throw new RuntimeException("Failed to update file: {$path}");
            }
            fflush($handle);
        } finally {
            flock($handle, LOCK_UN);
            fclose($handle);
        }
    }

    /** @param resource $handle */
    private function writeAll($handle, string $content): bool
    {
        $length = strlen($content);
        $offset = 0;
        while ($offset < $length) {
            $written = fwrite($handle, substr($content, $offset));
            if ($written === false || $written === 0) {
                return false;
            }
            $offset += $written;
        }
        return true;
    }

    /**
     * @param array<string,string> $normalized
     * @return array<string,array<string,string>>
     */
    private function buildTemplateLanguageEntries(string $templateKey, array $normalized): array
    {
        $prefix = $normalized['page_key_prefix'];

        $entries = [
            'ms' => [
                $prefix . '_page_title' => $normalized['page_title_ms'],
            ],
            'en' => [
                $prefix . '_page_title' => $normalized['page_title_en'],
            ],
        ];

        if ($templateKey === 'blank') {
            $entries['ms'][$prefix . '_content_placeholder'] = 'Kandungan bermula di sini.';
            $entries['en'][$prefix . '_content_placeholder'] = 'Content starts here.';
            return $entries;
        }

        if ($templateKey === 'datatable-basic') {
            $entries['ms'] += [
                $prefix . '_table_title' => 'Paparan Senarai',
                $prefix . '_table_subtitle' => 'Gunakan jadual yang dijana ini sebagai asas untuk page listing read-only, report, atau lookup yang ringkas.',
                $prefix . '_table_mode' => 'List Only',
                $prefix . '_col_no' => 'No.',
                $prefix . '_col_name' => 'Nama',
                $prefix . '_col_description' => 'Deskripsi',
                $prefix . '_col_status' => 'Status',
                $prefix . '_col_updated_at' => 'Kemaskini Terakhir',
                $prefix . '_status_active' => 'Aktif',
                $prefix . '_status_inactive' => 'Tidak Aktif',
            ];

            $entries['en'] += [
                $prefix . '_table_title' => 'Listing Overview',
                $prefix . '_table_subtitle' => 'Use this generated table as the starting point for read-only listing pages and lightweight search views.',
                $prefix . '_table_mode' => 'List Only',
                $prefix . '_col_no' => 'No.',
                $prefix . '_col_name' => 'Name',
                $prefix . '_col_description' => 'Description',
                $prefix . '_col_status' => 'Status',
                $prefix . '_col_updated_at' => 'Last Updated',
                $prefix . '_status_active' => 'Active',
                $prefix . '_status_inactive' => 'Inactive',
            ];
        }

        if ($templateKey === 'datatable' || $templateKey === 'datatable-crud-modal') {
            $entries['ms'] += [
                $prefix . '_table_title' => 'Senarai Pengurusan',
                $prefix . '_table_subtitle' => 'Gunakan page yang dijana ini sebagai asas untuk modul admin yang memerlukan listing, modal CRUD, dan interaction flow yang kemas.',
                $prefix . '_col_no' => 'No.',
                $prefix . '_col_name' => 'Nama',
                $prefix . '_col_department' => 'Jabatan',
                $prefix . '_col_group' => 'Kumpulan',
                $prefix . '_col_access' => 'Akses',
                $prefix . '_col_actions' => 'Tindakan',
                $prefix . '_access_allowed' => 'Dibenarkan',
                $prefix . '_access_blocked' => 'Disekat',
                $prefix . '_btn_cancel' => 'Batal',
                $prefix . '_btn_close' => 'Tutup',
                $prefix . '_btn_delete' => 'Padam',
                $prefix . '_btn_edit' => 'Edit',
                $prefix . '_btn_view' => 'Lihat',
                $prefix . '_btn_no' => 'Tidak',
                $prefix . '_btn_ok' => 'OK',
                $prefix . '_btn_sample' => 'Add New',
                $prefix . '_btn_save' => 'Simpan',
                $prefix . '_btn_yes' => 'Ya',
                $prefix . '_delete_success_text' => 'diproses sebagai tindakan sample tanpa interaksi backend.',
                $prefix . '_delete_success_title' => 'Sample Delete Complete',
                $prefix . '_delete_text' => 'Ini hanyalah sample frontend. Tiada tindakan backend akan dicetuskan.',
                $prefix . '_delete_title' => 'Padam Rekod Sample?',
                $prefix . '_edit_success_text' => 'Aliran edit sample selesai tanpa menghantar data ke backend.',
                $prefix . '_edit_success_title' => 'Sample Save Complete',
                $prefix . '_field_access' => 'Akses',
                $prefix . '_field_department' => 'Jabatan',
                $prefix . '_field_description' => 'Deskripsi',
                $prefix . '_field_group' => 'Kumpulan',
                $prefix . '_field_name' => 'Nama',
                $prefix . '_field_updated_at' => 'Kemaskini Terakhir',
                $prefix . '_modal_add_title' => 'Tambah Rekod',
                $prefix . '_modal_edit_title' => 'Edit Rekod',
                $prefix . '_modal_view_title' => 'Lihat Rekod',
                $prefix . '_sample_add_success_text' => 'Aliran tambah sample selesai tanpa menghantar data ke backend.',
                $prefix . '_sample_add_success_title' => 'Sample Add Complete',
            ];

            $entries['en'] += [
                $prefix . '_table_title' => 'Management Listing',
                $prefix . '_table_subtitle' => 'Use this generated page as the starting point for admin modules that need listing, modal CRUD, and polished interaction flow.',
                $prefix . '_col_no' => 'No.',
                $prefix . '_col_name' => 'Name',
                $prefix . '_col_department' => 'Department',
                $prefix . '_col_group' => 'Group',
                $prefix . '_col_access' => 'Access',
                $prefix . '_col_actions' => 'Actions',
                $prefix . '_access_allowed' => 'Allowed',
                $prefix . '_access_blocked' => 'Blocked',
                $prefix . '_btn_cancel' => 'Cancel',
                $prefix . '_btn_close' => 'Close',
                $prefix . '_btn_delete' => 'Delete',
                $prefix . '_btn_edit' => 'Edit',
                $prefix . '_btn_view' => 'View',
                $prefix . '_btn_no' => 'No',
                $prefix . '_btn_ok' => 'OK',
                $prefix . '_btn_sample' => 'Add New',
                $prefix . '_btn_save' => 'Save',
                $prefix . '_btn_yes' => 'Yes',
                $prefix . '_delete_success_text' => 'was processed as a sample action without backend interaction.',
                $prefix . '_delete_success_title' => 'Sample Delete Complete',
                $prefix . '_delete_text' => 'This is only a frontend sample. No backend action will be triggered.',
                $prefix . '_delete_title' => 'Delete Sample Record?',
                $prefix . '_edit_success_text' => 'The sample edit flow completed without sending any data to the backend.',
                $prefix . '_edit_success_title' => 'Sample Save Complete',
                $prefix . '_field_access' => 'Access',
                $prefix . '_field_department' => 'Department',
                $prefix . '_field_description' => 'Description',
                $prefix . '_field_group' => 'Group',
                $prefix . '_field_name' => 'Name',
                $prefix . '_field_updated_at' => 'Last Updated',
                $prefix . '_modal_add_title' => 'Add Record',
                $prefix . '_modal_edit_title' => 'Edit Record',
                $prefix . '_modal_view_title' => 'View Record',
                $prefix . '_sample_add_success_text' => 'The sample add flow completed without sending any data to the backend.',
                $prefix . '_sample_add_success_title' => 'Sample Add Complete',
            ];
        }

        if ($templateKey === 'form-basic') {
            $entries['ms'] += [
                $prefix . '_form_title' => 'Maklumat Borang',
                $prefix . '_form_subtitle' => 'Lengkapkan maklumat di bawah dan simpan perubahan.',
                $prefix . '_section_basic' => 'Maklumat Asas',
                $prefix . '_section_settings' => 'Tetapan Tambahan',
                $prefix . '_section_notes' => 'Nota & Lampiran',
                $prefix . '_field_name' => 'Nama',
                $prefix . '_field_email' => 'E-mel',
                $prefix . '_field_code' => 'Kod',
                $prefix . '_field_category' => 'Kategori',
                $prefix . '_field_select_category' => 'Sila pilih kategori',
                $prefix . '_field_display_order' => 'Turutan Paparan',
                $prefix . '_field_effective_date' => 'Tarikh Kuat Kuasa',
                $prefix . '_field_priority' => 'Keutamaan',
                $prefix . '_field_notifications' => 'Aktifkan notifikasi',
                $prefix . '_field_notifications_hint' => 'Hidupkan tetapan ini untuk menghantar notifikasi kemas kini bagi rekod ini.',
                $prefix . '_field_notes' => 'Nota',
                $prefix . '_field_notes_hint' => 'Maksimum 500 aksara.',
                $prefix . '_field_attachment' => 'Lampiran',
                $prefix . '_field_attachment_hint' => 'Format dibenarkan: PDF, JPG, PNG, DOC, DOCX. Saiz maksimum fail: 2MB.',
                $prefix . '_field_status' => 'Status',
                $prefix . '_field_select_status' => 'Sila pilih status',
                $prefix . '_category_general' => 'Umum',
                $prefix . '_category_secondary' => 'Sekunder',
                $prefix . '_category_restricted' => 'Terhad',
                $prefix . '_priority_low' => 'Rendah',
                $prefix . '_priority_medium' => 'Sederhana',
                $prefix . '_priority_high' => 'Tinggi',
                $prefix . '_status_active' => 'Aktif',
                $prefix . '_status_inactive' => 'Tidak Aktif',
                $prefix . '_status_on' => 'On',
                $prefix . '_status_off' => 'Off',
                $prefix . '_btn_save' => 'Simpan',
                $prefix . '_btn_cancel' => 'Batal',
                $prefix . '_btn_ok' => 'OK',
                $prefix . '_validation_name' => 'Sila masukkan sekurang-kurangnya 3 aksara untuk nama.',
                $prefix . '_validation_email' => 'Sila masukkan alamat e-mel yang sah.',
                $prefix . '_validation_category' => 'Sila pilih kategori.',
                $prefix . '_validation_display_order' => 'Turutan paparan mesti nombor positif.',
                $prefix . '_validation_effective_date' => 'Sila pilih tarikh kuat kuasa.',
                $prefix . '_validation_priority' => 'Sila pilih keutamaan.',
                $prefix . '_validation_status' => 'Sila pilih status.',
                $prefix . '_msg_success_title' => 'Sample Save Complete',
                $prefix . '_msg_success' => 'Sample form ini berjaya disimpan tanpa menghantar data ke backend.',
                $prefix . '_msg_validation_error_title' => 'Pengesahan Diperlukan',
                $prefix . '_msg_validation_error' => 'Sila lengkapkan medan wajib dengan betul sebelum simpan.',
                $prefix . '_msg_error' => 'Ralat berlaku semasa memproses borang.',
            ];

            $entries['en'] += [
                $prefix . '_form_title' => 'Form Details',
                $prefix . '_form_subtitle' => 'Complete the information below and save the changes.',
                $prefix . '_section_basic' => 'Basic Information',
                $prefix . '_section_settings' => 'Additional Settings',
                $prefix . '_section_notes' => 'Notes & Attachment',
                $prefix . '_field_name' => 'Name',
                $prefix . '_field_email' => 'Email',
                $prefix . '_field_code' => 'Code',
                $prefix . '_field_category' => 'Category',
                $prefix . '_field_select_category' => 'Please select category',
                $prefix . '_field_display_order' => 'Display Order',
                $prefix . '_field_effective_date' => 'Effective Date',
                $prefix . '_field_priority' => 'Priority',
                $prefix . '_field_notifications' => 'Enable notifications',
                $prefix . '_field_notifications_hint' => 'Turn this on to send update alerts for this record.',
                $prefix . '_field_notes' => 'Notes',
                $prefix . '_field_notes_hint' => 'Maximum 500 characters.',
                $prefix . '_field_attachment' => 'Attachment',
                $prefix . '_field_attachment_hint' => 'Accepted formats: PDF, JPG, PNG, DOC, DOCX. Maximum file size: 2MB.',
                $prefix . '_field_status' => 'Status',
                $prefix . '_field_select_status' => 'Please select status',
                $prefix . '_category_general' => 'General',
                $prefix . '_category_secondary' => 'Secondary',
                $prefix . '_category_restricted' => 'Restricted',
                $prefix . '_priority_low' => 'Low',
                $prefix . '_priority_medium' => 'Medium',
                $prefix . '_priority_high' => 'High',
                $prefix . '_status_active' => 'Active',
                $prefix . '_status_inactive' => 'Inactive',
                $prefix . '_status_on' => 'On',
                $prefix . '_status_off' => 'Off',
                $prefix . '_btn_save' => 'Save',
                $prefix . '_btn_cancel' => 'Cancel',
                $prefix . '_btn_ok' => 'OK',
                $prefix . '_validation_name' => 'Please enter at least 3 characters for name.',
                $prefix . '_validation_email' => 'Please enter a valid email address.',
                $prefix . '_validation_category' => 'Please select a category.',
                $prefix . '_validation_display_order' => 'Display order must be a positive number.',
                $prefix . '_validation_effective_date' => 'Please choose an effective date.',
                $prefix . '_validation_priority' => 'Please choose a priority.',
                $prefix . '_validation_status' => 'Please select a status.',
                $prefix . '_msg_success_title' => 'Sample Save Complete',
                $prefix . '_msg_success' => 'This sample form completed successfully without sending any data to the backend.',
                $prefix . '_msg_validation_error_title' => 'Validation Required',
                $prefix . '_msg_validation_error' => 'Please complete the required fields correctly before saving.',
                $prefix . '_msg_error' => 'An error occurred while processing the form.',
            ];
        }

        if ($templateKey === 'report-filter-table') {
            $entries['ms'] += [
                $prefix . '_filter_title' => 'Kriteria Penapis',
                $prefix . '_filter_subtitle' => 'Gunakan kawalan di bawah untuk menapis jadual keputusan dan menunjukkan aliran carian gaya laporan.',
                $prefix . '_table_title' => 'Jadual Keputusan',
                $prefix . '_table_subtitle' => 'Bahagian keputusan kekal dalam page yang sama supaya developer boleh membina aliran laporan tanpa routing tambahan.',
                $prefix . '_table_mode' => 'Report Search',
                $prefix . '_field_from_date' => 'Tarikh Mula',
                $prefix . '_field_to_date' => 'Tarikh Tamat',
                $prefix . '_field_status' => 'Status',
                $prefix . '_field_category' => 'Kategori',
                $prefix . '_field_keyword' => 'Kata Kunci',
                $prefix . '_field_keyword_placeholder' => 'Masukkan kata kunci laporan atau nombor rujukan',
                $prefix . '_btn_search' => 'Cari',
                $prefix . '_btn_reset' => 'Set Semula',
                $prefix . '_btn_ok' => 'OK',
                $prefix . '_reset_title' => 'Filter Reset',
                $prefix . '_reset_text' => 'Nilai sample filter telah diset semula tanpa menghantar permintaan ke backend.',
                $prefix . '_search_title' => 'Sample Search Complete',
                $prefix . '_search_text' => 'Aliran sample filter ini berjaya tanpa menghantar sebarang permintaan ke backend.',
                $prefix . '_status_all' => 'Semua Status',
                $prefix . '_status_completed' => 'Selesai',
                $prefix . '_status_in_review' => 'Dalam Semakan',
                $prefix . '_category_all' => 'Semua Kategori',
                $prefix . '_category_operational' => 'Operasi',
                $prefix . '_category_analytics' => 'Analitik',
                $prefix . '_category_security' => 'Keselamatan',
                $prefix . '_col_no' => 'No.',
                $prefix . '_col_reference_no' => 'No. Rujukan',
                $prefix . '_col_name' => 'Nama Laporan',
                $prefix . '_col_category' => 'Kategori',
                $prefix . '_col_status' => 'Status',
                $prefix . '_col_updated_at' => 'Kemaskini Terakhir',
            ];

            $entries['en'] += [
                $prefix . '_filter_title' => 'Filter Criteria',
                $prefix . '_filter_subtitle' => 'Use the controls below to refine the result table and demonstrate a report-style search workflow.',
                $prefix . '_table_title' => 'Result Table',
                $prefix . '_table_subtitle' => 'The result section stays on the same page so developers can build reporting workflows without extra routing complexity.',
                $prefix . '_table_mode' => 'Report Search',
                $prefix . '_field_from_date' => 'From Date',
                $prefix . '_field_to_date' => 'To Date',
                $prefix . '_field_status' => 'Status',
                $prefix . '_field_category' => 'Category',
                $prefix . '_field_keyword' => 'Keyword',
                $prefix . '_field_keyword_placeholder' => 'Enter report keyword or reference number',
                $prefix . '_btn_search' => 'Search',
                $prefix . '_btn_reset' => 'Reset',
                $prefix . '_btn_ok' => 'OK',
                $prefix . '_reset_title' => 'Filter Reset',
                $prefix . '_reset_text' => 'Sample filter values were reset without sending a backend request.',
                $prefix . '_search_title' => 'Sample Search Complete',
                $prefix . '_search_text' => 'This sample filter flow completed successfully without sending any request to the backend.',
                $prefix . '_status_all' => 'All Statuses',
                $prefix . '_status_completed' => 'Completed',
                $prefix . '_status_in_review' => 'In Review',
                $prefix . '_category_all' => 'All Categories',
                $prefix . '_category_operational' => 'Operational',
                $prefix . '_category_analytics' => 'Analytics',
                $prefix . '_category_security' => 'Security',
                $prefix . '_col_no' => 'No.',
                $prefix . '_col_reference_no' => 'Reference No.',
                $prefix . '_col_name' => 'Report Name',
                $prefix . '_col_category' => 'Category',
                $prefix . '_col_status' => 'Status',
                $prefix . '_col_updated_at' => 'Last Updated',
            ];
        }

        if ($templateKey === 'detail-view') {
            $entries['ms'] += [
                $prefix . '_summary_title' => 'Ringkasan',
                $prefix . '_details_title' => 'Maklumat Terperinci',
                $prefix . '_notes_title' => 'Nota',
                $prefix . '_label_name' => 'Nama',
                $prefix . '_label_code' => 'Kod',
                $prefix . '_label_status' => 'Status',
                $prefix . '_label_category' => 'Kategori',
                $prefix . '_label_owner' => 'Pemilik',
                $prefix . '_label_updated_at' => 'Kemaskini Terakhir',
                $prefix . '_btn_back' => 'Kembali',
            ];

            $entries['en'] += [
                $prefix . '_summary_title' => 'Summary',
                $prefix . '_details_title' => 'Details',
                $prefix . '_notes_title' => 'Notes',
                $prefix . '_label_name' => 'Name',
                $prefix . '_label_code' => 'Code',
                $prefix . '_label_status' => 'Status',
                $prefix . '_label_category' => 'Category',
                $prefix . '_label_owner' => 'Owner',
                $prefix . '_label_updated_at' => 'Last Updated',
                $prefix . '_btn_back' => 'Back',
            ];
        }

        if ($templateKey === 'master-detail') {
            $entries['ms'] += [
                $prefix . '_master_title' => 'Senarai Utama',
                $prefix . '_master_subtitle' => 'Pilih item di bawah untuk mengemas kini panel detail tanpa meninggalkan page.',
                $prefix . '_detail_title' => 'Panel Detail',
                $prefix . '_label_code' => 'Kod',
                $prefix . '_label_status' => 'Status',
                $prefix . '_label_owner' => 'Pemilik',
                $prefix . '_label_updated_at' => 'Kemaskini Terakhir',
                $prefix . '_label_description' => 'Deskripsi',
                $prefix . '_label_tags' => 'Tag',
            ];

            $entries['en'] += [
                $prefix . '_master_title' => 'Master List',
                $prefix . '_master_subtitle' => 'Select an item below to update the detail panel without leaving the page.',
                $prefix . '_detail_title' => 'Detail Panel',
                $prefix . '_label_code' => 'Code',
                $prefix . '_label_status' => 'Status',
                $prefix . '_label_owner' => 'Owner',
                $prefix . '_label_updated_at' => 'Last Updated',
                $prefix . '_label_description' => 'Description',
                $prefix . '_label_tags' => 'Tags',
            ];
        }

        if ($templateKey === 'datatable-expandable-row') {
            $entries['ms'] += [
                $prefix . '_table_title' => 'Expandable Listing',
                $prefix . '_table_subtitle' => 'Klik action pada row untuk buka maklumat tambahan terus dalam jadual yang sama.',
                $prefix . '_col_no' => 'No.',
                $prefix . '_col_reference_no' => 'No. Rujukan',
                $prefix . '_col_title' => 'Tajuk',
                $prefix . '_col_status' => 'Status',
                $prefix . '_col_updated_at' => 'Kemaskini Terakhir',
                $prefix . '_col_actions' => 'Tindakan',
                $prefix . '_btn_expand' => 'Papar Detail',
                $prefix . '_label_owner' => 'Pemilik',
                $prefix . '_label_updated_at' => 'Kemaskini Terakhir',
                $prefix . '_label_description' => 'Deskripsi',
                $prefix . '_label_notes' => 'Nota',
            ];

            $entries['en'] += [
                $prefix . '_table_title' => 'Expandable Listing',
                $prefix . '_table_subtitle' => 'Click a row action to expand extra information inline without leaving the table.',
                $prefix . '_col_no' => 'No.',
                $prefix . '_col_reference_no' => 'Reference No.',
                $prefix . '_col_title' => 'Title',
                $prefix . '_col_status' => 'Status',
                $prefix . '_col_updated_at' => 'Last Updated',
                $prefix . '_col_actions' => 'Actions',
                $prefix . '_btn_expand' => 'Expand',
                $prefix . '_label_owner' => 'Owner',
                $prefix . '_label_updated_at' => 'Last Updated',
                $prefix . '_label_description' => 'Description',
                $prefix . '_label_notes' => 'Notes',
            ];
        }

        if ($templateKey === 'tabbed-management') {
            $entries['ms'] += [
                $prefix . '_module_kicker' => 'Workspace Bertab',
                $prefix . '_tab_overview' => 'Overview',
                $prefix . '_tab_configuration' => 'Configuration',
                $prefix . '_tab_history' => 'History',
                $prefix . '_overview_title' => 'Ringkasan Overview',
                $prefix . '_overview_quick_title' => 'Nota Pantas',
                $prefix . '_overview_quick_text' => 'Tab ini sesuai untuk ringkasan modul, KPI, dan panduan ringkas sebelum pengguna masuk ke bahagian yang lebih khusus.',
                $prefix . '_configuration_title' => 'Tetapan Konfigurasi',
                $prefix . '_configuration_subtitle' => 'Gunakan tab ini untuk kumpulan tetapan, parameter modul, atau medan konfigurasi pada aras page.',
                $prefix . '_history_title' => 'Sejarah Terkini',
                $prefix . '_history_subtitle' => 'Gunakan bahagian ini untuk log aktiviti, sejarah semakan, atau ringkasan kemajuan aliran kerja.',
                $prefix . '_quick_copy' => 'Salin ID',
                $prefix . '_quick_visit' => 'Buka Modul',
                $prefix . '_label_module_id' => 'ID Modul',
                $prefix . '_label_reference_no' => 'No. Rujukan',
                $prefix . '_label_category' => 'Kategori',
                $prefix . '_label_highlight' => 'Highlight',
                $prefix . '_field_module_name' => 'Nama Modul',
                $prefix . '_field_layout_mode' => 'Mod Susun Atur',
                $prefix . '_field_status' => 'Status',
                $prefix . '_field_owner' => 'Pemilik',
                $prefix . '_field_language' => 'Bahasa',
                $prefix . '_field_visibility' => 'Visibility',
                $prefix . '_field_description' => 'Deskripsi',
                $prefix . '_layout_standard' => 'Standard',
                $prefix . '_layout_compact' => 'Padat',
                $prefix . '_layout_expanded' => 'Expanded',
                $prefix . '_status_active' => 'Aktif',
                $prefix . '_status_inactive' => 'Tidak Aktif',
                $prefix . '_label_updated_at' => 'Kemaskini Terakhir',
                $prefix . '_col_datetime' => 'Tarikh & Masa',
                $prefix . '_col_setting' => 'Tetapan',
                $prefix . '_col_owner' => 'Pemilik',
                $prefix . '_col_status' => 'Status',
                $prefix . '_col_actor' => 'Actor',
                $prefix . '_col_activity' => 'Aktiviti',
                $prefix . '_col_result' => 'Keputusan',
                $prefix . '_btn_save' => 'Simpan',
                $prefix . '_btn_cancel' => 'Batal',
                $prefix . '_btn_ok' => 'OK',
                $prefix . '_save_title' => 'Sample Save Complete',
                $prefix . '_save_text' => 'Konfigurasi sample tab ini berjaya disimpan tanpa menghantar data ke backend.',
                $prefix . '_reset_title' => 'Perubahan Diset Semula',
                $prefix . '_reset_text' => 'Input sample tab telah diset semula tanpa sebarang tindakan backend.',
            ];

            $entries['en'] += [
                $prefix . '_module_kicker' => 'Tabbed Workspace',
                $prefix . '_tab_overview' => 'Overview',
                $prefix . '_tab_configuration' => 'Configuration',
                $prefix . '_tab_history' => 'History',
                $prefix . '_overview_title' => 'Overview Summary',
                $prefix . '_overview_quick_title' => 'Quick Notes',
                $prefix . '_overview_quick_text' => 'This tab is suitable for module summary, KPI highlights, and short guidance before users move into more specific sections.',
                $prefix . '_configuration_title' => 'Configuration Setup',
                $prefix . '_configuration_subtitle' => 'Use this tab for grouped settings, module parameters, or page-level configuration fields.',
                $prefix . '_history_title' => 'Recent History',
                $prefix . '_history_subtitle' => 'Use this section for activity logs, review history, or workflow progress summary.',
                $prefix . '_quick_copy' => 'Copy ID',
                $prefix . '_quick_visit' => 'Open Module',
                $prefix . '_label_module_id' => 'Module ID',
                $prefix . '_label_reference_no' => 'Reference No.',
                $prefix . '_label_category' => 'Category',
                $prefix . '_label_highlight' => 'Highlight',
                $prefix . '_field_module_name' => 'Module Name',
                $prefix . '_field_layout_mode' => 'Layout Mode',
                $prefix . '_field_status' => 'Status',
                $prefix . '_field_owner' => 'Owner',
                $prefix . '_field_language' => 'Language',
                $prefix . '_field_visibility' => 'Visibility',
                $prefix . '_field_description' => 'Description',
                $prefix . '_layout_standard' => 'Standard',
                $prefix . '_layout_compact' => 'Compact',
                $prefix . '_layout_expanded' => 'Expanded',
                $prefix . '_status_active' => 'Active',
                $prefix . '_status_inactive' => 'Inactive',
                $prefix . '_label_updated_at' => 'Last Updated',
                $prefix . '_col_datetime' => 'Date & Time',
                $prefix . '_col_setting' => 'Setting',
                $prefix . '_col_owner' => 'Owner',
                $prefix . '_col_status' => 'Status',
                $prefix . '_col_actor' => 'Actor',
                $prefix . '_col_activity' => 'Activity',
                $prefix . '_col_result' => 'Result',
                $prefix . '_btn_save' => 'Save',
                $prefix . '_btn_cancel' => 'Cancel',
                $prefix . '_btn_ok' => 'OK',
                $prefix . '_save_title' => 'Sample Save Complete',
                $prefix . '_save_text' => 'This tab configuration sample completed successfully without sending data to the backend.',
                $prefix . '_reset_title' => 'Changes Reset',
                $prefix . '_reset_text' => 'Sample tab inputs were reset without any backend action.',
            ];
        }

        return $entries;
    }
}
