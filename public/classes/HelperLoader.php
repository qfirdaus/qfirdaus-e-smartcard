<?php
/**
 * IQS FRAMEWORK CORE FILE
 *
 * READ ONLY for downstream project programmers.
 * Do not modify this file directly in template or cloned projects.
 * Custom changes must be implemented in project-specific files
 * or approved extension points.
 */declare(strict_types=1);

/**
 * ✅ Autoload semua helper dalam satu folder (optional: rekursif)
 * Penggunaan:
 *   $loader = new HelperLoader(__DIR__ . '/helpers');
 *   $loader->loadAll();                     // default: *_helper.php, non-recursive
 *   $files = $loader->loadAll('*_helper.php', true); // recursive + dapat senarai file loaded
 */
class HelperLoader
{
    private string $directory;

    public function __construct(string $directory)
    {
        if (!is_dir($directory)) {
            throw new InvalidArgumentException("❌ Direktori tidak sah: $directory");
        }
        $real = realpath($directory);
        if ($real === false) {
            throw new InvalidArgumentException("❌ Gagal resolve path: $directory");
        }
        $this->directory = rtrim($real, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
    }

    /**
     * 🔁 Load semua helper ikut pola (default: *_helper.php)
     * @param string $pattern  Pola fail (glob-style). Contoh: '*_helper.php'
     * @param bool   $recursive Jika true, masuk subfolder
     * @return string[] Senarai fail yang diload (absolute paths)
     */
    public function loadAll(string $pattern = '*_helper.php', bool $recursive = false): array
    {
        $loaded = [];

        if ($recursive) {
            // Rekursif: guna RecursiveDirectoryIterator + RegexIterator
            $it = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($this->directory, FilesystemIterator::SKIP_DOTS)
            );
            $regex = self::globToRegex($pattern);
            foreach ($it as $fileInfo) {
                /** @var SplFileInfo $fileInfo */
                if ($fileInfo->isFile() && preg_match($regex, $fileInfo->getFilename())) {
                    $path = $fileInfo->getRealPath();
                    if ($path && pathinfo($path, PATHINFO_EXTENSION) === 'php') {
                        require_once $path;
                        $loaded[] = $path;
                    }
                }
            }
        } else {
            // Non-recursive: glob pada root dir
            $files = glob($this->directory . $pattern) ?: [];
            // Pastikan order konsisten
            sort($files, SORT_FLAG_CASE | SORT_STRING);

            foreach ($files as $file) {
                if (is_file($file) && pathinfo($file, PATHINFO_EXTENSION) === 'php') {
                    $path = realpath($file) ?: $file;
                    require_once $path;
                    $loaded[] = $path;
                }
            }
        }

        return $loaded;
    }

    /** Tukar pola glob ringkas kepada regex (untuk rekursif) */
    private static function globToRegex(string $glob): string
    {
        // Escape regex, tukar wildcard glob → regex
        $escaped = preg_quote($glob, '/');
        $escaped = str_replace(['\*', '\?'], ['.*', '.'], $escaped);
        return '/^' . $escaped . '$/i';
    }
}
