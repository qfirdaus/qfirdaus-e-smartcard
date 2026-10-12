<?php
/**
 * Read-only platform-variant audit for Additional Database registry.
 *
 * Usage:
 *   php tools/additional-database-platform-audit.php
 *   php tools/additional-database-platform-audit.php --code=dbx_sybase_asramadb
 *   php tools/additional-database-platform-audit.php --json
 */
declare(strict_types=1);

$configs = require __DIR__ . '/../public/configuration/db_config.php';
$options = getopt('', ['code:', 'json']);
$codeFilter = strtolower(trim((string)($options['code'] ?? '')));
$asJson = isset($options['json']);

$appEnv = strtolower(trim((string)($_ENV['APP_ENV'] ?? getenv('APP_ENV') ?: '')));
$mainEnv = strtolower(trim((string)($_ENV['MAIN_DB_ENVIRONMENT'] ?? getenv('MAIN_DB_ENVIRONMENT') ?: '')));
if ($appEnv !== 'development' || $mainEnv !== 'development') {
    fwrite(STDERR, "Audit ditolak: APP_ENV dan MAIN_DB_ENVIRONMENT mesti development.\n");
    exit(1);
}

try {
    $config = $configs['mysql_dev'] ?? null;
    if (!is_array($config)) {
        throw new RuntimeException('Konfigurasi mysql_dev tidak tersedia.');
    }
    $pdo = new PDO(
        (string)$config['dsn'],
        $config['user'] ?? null,
        $config['pass'] ?? null,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
    );

    $sql = "SELECT c.f_code, c.f_family, c.f_driver_mode, c.f_supports_prod, c.f_supports_dev,
                   e.f_environment, e.f_os_family, e.f_driver, e.f_is_active,
                   CASE WHEN e.f_dsn_name IS NULL OR e.f_dsn_name = '' THEN 0 ELSE 1 END AS f_has_dsn,
                   CASE WHEN e.f_host IS NULL OR e.f_host = '' THEN 0 ELSE 1 END AS f_has_host,
                   CASE WHEN e.f_password_ciphertext IS NULL OR e.f_password_ciphertext = '' THEN 'none'
                        WHEN e.f_password_ciphertext LIKE 'v2:%' THEN 'v2' ELSE 'legacy' END AS f_credential_format
            FROM tbl_m_db_connection c
            LEFT JOIN tbl_m_db_connection_env e ON e.f_connection_code = c.f_code
            WHERE c.f_category = 'additional'";
    $params = [];
    if ($codeFilter !== '') {
        $sql .= ' AND c.f_code = :code';
        $params[':code'] = $codeFilter;
    }
    $sql .= ' ORDER BY c.f_code, e.f_environment, e.f_os_family, e.f_driver';
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll();

    $connections = [];
    foreach ($rows as $row) {
        $code = (string)$row['f_code'];
        $connections[$code]['code'] = $code;
        $connections[$code]['family'] = (string)$row['f_family'];
        $connections[$code]['driver_mode'] = (string)$row['f_driver_mode'];
        $connections[$code]['supports'] = [
            'production' => !empty($row['f_supports_prod']),
            'development' => !empty($row['f_supports_dev']),
        ];
        if ($row['f_environment'] !== null) {
            $connections[$code]['variants'][] = [
                'environment' => (string)$row['f_environment'],
                'os_family' => (string)$row['f_os_family'],
                'driver' => (string)$row['f_driver'],
                'active' => !empty($row['f_is_active']),
                'has_dsn' => !empty($row['f_has_dsn']),
                'has_host' => !empty($row['f_has_host']),
                'credential_format' => (string)$row['f_credential_format'],
            ];
        }
    }

    $report = [];
    foreach ($connections as $connection) {
        $issues = [];
        $variants = $connection['variants'] ?? [];
        if (($connection['driver_mode'] ?? 'auto') !== 'auto') {
            $issues[] = 'global_forced_driver_mode';
        }
        foreach ($variants as $variant) {
            if ($variant['active'] && $variant['os_family'] === 'any' && in_array($variant['driver'], ['odbc', 'dblib', 'sqlsrv'], true)) {
                $issues[] = 'platform_specific_driver_scoped_as_any';
            }
            if ($variant['active'] && $variant['driver'] === 'odbc' && !$variant['has_dsn']) {
                $issues[] = 'odbc_dsn_missing';
            }
        }
        foreach (['production', 'development'] as $environment) {
            if (empty($connection['supports'][$environment])) {
                continue;
            }
            foreach (['windows', 'linux'] as $osFamily) {
                $covered = array_filter($variants, static fn(array $variant): bool =>
                    $variant['active']
                    && $variant['environment'] === $environment
                    && in_array($variant['os_family'], [$osFamily, 'any'], true)
                );
                if ($covered === []) {
                    $issues[] = "missing_{$environment}_{$osFamily}_variant";
                }
            }
        }

        $recommendation = [];
        if ($connection['family'] === 'sybase') {
            $recommendation[] = 'Windows: OS=windows, driver=odbc, DSN wajib disahkan pada runtime Windows.';
            $recommendation[] = 'Linux: OS=linux, driver=dblib, host/port/database wajib disahkan pada runtime Linux.';
        } elseif ($connection['family'] === 'mssql') {
            $recommendation[] = 'Windows: OS=windows, driver=sqlsrv atau odbc.';
            $recommendation[] = 'Linux: OS=linux, driver=odbc atau dblib.';
        }
        $report[] = $connection + [
            'issues' => array_values(array_unique($issues)),
            'recommendation' => $recommendation,
        ];
    }

    if ($asJson) {
        fwrite(STDOUT, json_encode(['read_only' => true, 'connections' => $report], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");
        exit(0);
    }

    fwrite(STDOUT, "Additional Database platform audit (READ-ONLY)\n\n");
    foreach ($report as $entry) {
        printf("%s | family=%s | mode=%s\n", $entry['code'], $entry['family'], $entry['driver_mode']);
        foreach ($entry['variants'] ?? [] as $variant) {
            printf("  - %s / %s / %s / %s / credential=%s\n",
                $variant['environment'], $variant['os_family'], $variant['driver'],
                $variant['active'] ? 'active' : 'inactive', $variant['credential_format']);
        }
        printf("  Issues: %s\n", $entry['issues'] === [] ? 'none' : implode(', ', $entry['issues']));
        foreach ($entry['recommendation'] as $recommendation) {
            fwrite(STDOUT, "  Recommendation: {$recommendation}\n");
        }
        fwrite(STDOUT, "\n");
    }
    fwrite(STDOUT, "Result: audit complete; no database row changed.\n");
} catch (Throwable $error) {
    fwrite(STDERR, '[FAIL] ' . $error->getMessage() . "\n");
    exit(1);
}
