<?php
/**
 * IQS FRAMEWORK CORE FILE
 *
 * READ ONLY for downstream project programmers.
 * Do not modify this file directly in template or cloned projects.
 * Custom changes must be implemented in project-specific files
 * or approved extension points.
 */declare(strict_types=1);

header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

$configFile = __DIR__ . '/configuration/db_config.php';
$configs = is_file($configFile) ? (require $configFile) : [];

$availableKeys = [];
foreach (array_keys($configs) as $key) {
    if (str_starts_with((string)$key, 'sybase_')) {
        $availableKeys[] = (string)$key;
    }
}
sort($availableKeys);

$selectedKey = trim((string)($_REQUEST['key'] ?? 'sybase_student_prod_dsn'));
if (!in_array($selectedKey, $availableKeys, true)) {
    $selectedKey = in_array('sybase_student_prod_dsn', $availableKeys, true)
        ? 'sybase_student_prod_dsn'
        : ($availableKeys[0] ?? '');
}

$action = strtolower(trim((string)($_REQUEST['action'] ?? 'connect_only')));
$allowedActions = ['connect_only', 'ping', 'student_lookup'];
if (!in_array($action, $allowedActions, true)) {
    $action = 'connect_only';
}

$query = trim((string)($_REQUEST['q'] ?? '225'));
$result = [
    'ok' => false,
    'steps' => [],
    'error' => null,
    'rows' => [],
];

$mask = static function (?string $value): string {
    $value = trim((string)$value);
    if ($value === '') {
        return '';
    }
    if (strlen($value) <= 4) {
        return str_repeat('*', strlen($value));
    }
    return substr($value, 0, 2) . str_repeat('*', max(0, strlen($value) - 4)) . substr($value, -2);
};

$logFile = __DIR__ . '/log/diag-sybase-odbc.log';
$writeLog = static function (array $payload) use ($logFile): void {
    $dir = dirname($logFile);
    if (!is_dir($dir)) {
        @mkdir($dir, 0777, true);
    }
    @file_put_contents(
        $logFile,
        json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL,
        FILE_APPEND | LOCK_EX
    );
};

$selectedConfig = is_array($configs[$selectedKey] ?? null) ? $configs[$selectedKey] : null;

if ($selectedConfig !== null && isset($_REQUEST['run'])) {
    $startedAt = microtime(true);
    try {
        $options = $selectedConfig['options'] ?? [];
        $driver = strtolower((string)($selectedConfig['driver'] ?? ''));
        $dsn = (string)($selectedConfig['dsn'] ?? '');
        $defaults = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ];
        if ($driver === 'odbc' && str_contains(strtolower($dsn), 'sybase')) {
            $defaults[PDO::ATTR_EMULATE_PREPARES] = true;
            $defaults[PDO::ATTR_CURSOR] = PDO::CURSOR_FWDONLY;
        }
        $options = $options + $defaults;

        $result['steps'][] = 'Building PDO connection';
        $pdo = new PDO(
            $dsn,
            $selectedConfig['user'] ?? null,
            $selectedConfig['pass'] ?? null,
            $options
        );
        $result['steps'][] = 'PDO connection created';

        if ($action === 'connect_only') {
            $result['ok'] = true;
        } elseif ($action === 'ping') {
            $result['steps'][] = 'Executing SELECT 1';
            $row = $pdo->query('select 1 as ok')->fetch();
            $result['rows'] = $row ? [$row] : [];
            $result['ok'] = true;
        } elseif ($action === 'student_lookup') {
            $result['steps'][] = 'Executing student lookup query';
            $sql = "
                SELECT TOP 5
                    matrik,
                    nama,
                    statuskategori
                FROM v210
                WHERE upper(convert(varchar(20), statuskategori)) = 'AKTIF'
                  AND (
                    upper(convert(varchar(50), matrik)) LIKE :q_prefix
                    OR upper(convert(varchar(255), nama)) LIKE :q_name
                  )
                ORDER BY matrik ASC
            ";
            $stmt = $pdo->prepare($sql);
            $stmt->bindValue(':q_prefix', strtoupper($query) . '%');
            $stmt->bindValue(':q_name', '%' . strtoupper($query) . '%');
            $stmt->execute();
            $result['rows'] = $stmt->fetchAll() ?: [];
            $result['ok'] = true;
        }
    } catch (Throwable $e) {
        $result['error'] = [
            'message' => $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine(),
        ];
    }

    $writeLog([
        'timestamp' => date('c'),
        'selected_key' => $selectedKey,
        'action' => $action,
        'query' => $query,
        'ok' => $result['ok'],
        'steps' => $result['steps'],
        'error' => $result['error'],
        'row_count' => count($result['rows']),
        'remote_addr' => (string)($_SERVER['REMOTE_ADDR'] ?? ''),
        'php_version' => PHP_VERSION,
        'pdo_drivers' => PDO::getAvailableDrivers(),
        'duration_ms' => (int)round((microtime(true) - $startedAt) * 1000),
    ]);
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Sybase ODBC Diagnostic</title>
  <style>
    body { font-family: Segoe UI, Arial, sans-serif; margin: 24px; background: #f6f8fb; color: #17202a; }
    .wrap { max-width: 1100px; margin: 0 auto; }
    .card { background: #fff; border: 1px solid #d9e2ec; border-radius: 12px; padding: 20px; margin-bottom: 16px; box-shadow: 0 8px 24px rgba(15, 23, 42, 0.06); }
    h1, h2 { margin-top: 0; }
    label { display: block; font-weight: 600; margin-bottom: 6px; }
    input, select, button { width: 100%; padding: 10px 12px; border-radius: 8px; border: 1px solid #bcccdc; box-sizing: border-box; }
    button { background: #0f62fe; color: #fff; border: 0; cursor: pointer; font-weight: 600; }
    button:hover { background: #0353e9; }
    .grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 14px; }
    .ok { color: #12715b; font-weight: 700; }
    .fail { color: #b42318; font-weight: 700; }
    pre { white-space: pre-wrap; word-break: break-word; background: #0b1220; color: #dce7f3; padding: 16px; border-radius: 10px; overflow: auto; }
    table { width: 100%; border-collapse: collapse; }
    th, td { border: 1px solid #d9e2ec; padding: 8px 10px; text-align: left; vertical-align: top; }
    th { background: #f0f4f8; }
    .hint { color: #52606d; font-size: 14px; }
  </style>
</head>
<body>
<div class="wrap">
  <div class="card">
    <h1>Sybase ODBC Diagnostic</h1>
    <p class="hint">Page ini bebas daripada login aplikasi. Ia baca config terus dari <code>public/configuration/db_config.php</code> dan sesuai untuk test DSN/ODBC pada PC programmer.</p>
  </div>

  <div class="card">
    <form method="get">
      <div class="grid">
        <div>
          <label for="key">Connection Key</label>
          <select id="key" name="key">
            <?php foreach ($availableKeys as $key): ?>
              <option value="<?= htmlspecialchars($key, ENT_QUOTES, 'UTF-8') ?>" <?= $selectedKey === $key ? 'selected' : '' ?>><?= htmlspecialchars($key, ENT_QUOTES, 'UTF-8') ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div>
          <label for="action">Action</label>
          <select id="action" name="action">
            <option value="connect_only" <?= $action === 'connect_only' ? 'selected' : '' ?>>Connect only</option>
            <option value="ping" <?= $action === 'ping' ? 'selected' : '' ?>>SELECT 1</option>
            <option value="student_lookup" <?= $action === 'student_lookup' ? 'selected' : '' ?>>Student lookup v210</option>
          </select>
        </div>
        <div>
          <label for="q">Query</label>
          <input id="q" name="q" value="<?= htmlspecialchars($query, ENT_QUOTES, 'UTF-8') ?>" placeholder="Example: 225">
        </div>
        <div>
          <label>&nbsp;</label>
          <button type="submit" name="run" value="1">Run Diagnostic</button>
        </div>
      </div>
    </form>
  </div>

  <div class="card">
    <h2>Runtime</h2>
    <table>
      <tr><th>PHP Version</th><td><?= htmlspecialchars(PHP_VERSION, ENT_QUOTES, 'UTF-8') ?></td></tr>
      <tr><th>OS</th><td><?= htmlspecialchars(PHP_OS_FAMILY, ENT_QUOTES, 'UTF-8') ?></td></tr>
      <tr><th>Remote Addr</th><td><?= htmlspecialchars((string)($_SERVER['REMOTE_ADDR'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td></tr>
      <tr><th>PDO Drivers</th><td><?= htmlspecialchars(json_encode(PDO::getAvailableDrivers(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), ENT_QUOTES, 'UTF-8') ?></td></tr>
      <tr><th>Selected Key</th><td><?= htmlspecialchars($selectedKey, ENT_QUOTES, 'UTF-8') ?></td></tr>
      <tr><th>Driver</th><td><?= htmlspecialchars((string)($selectedConfig['driver'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td></tr>
      <tr><th>DSN</th><td><?= htmlspecialchars((string)($selectedConfig['dsn'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td></tr>
      <tr><th>User</th><td><?= htmlspecialchars($mask((string)($selectedConfig['user'] ?? '')), ENT_QUOTES, 'UTF-8') ?></td></tr>
      <tr><th>Password</th><td><?= htmlspecialchars($mask((string)($selectedConfig['pass'] ?? '')), ENT_QUOTES, 'UTF-8') ?></td></tr>
      <tr><th>Log File</th><td><code>public/log/diag-sybase-odbc.log</code></td></tr>
    </table>
  </div>

  <?php if (isset($_REQUEST['run'])): ?>
    <div class="card">
      <h2>Result</h2>
      <p class="<?= $result['ok'] ? 'ok' : 'fail' ?>"><?= $result['ok'] ? 'SUCCESS' : 'FAILED' ?></p>
      <h3>Steps</h3>
      <pre><?= htmlspecialchars(json_encode($result['steps'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), ENT_QUOTES, 'UTF-8') ?></pre>
      <?php if ($result['error'] !== null): ?>
        <h3>Error</h3>
        <pre><?= htmlspecialchars(json_encode($result['error'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), ENT_QUOTES, 'UTF-8') ?></pre>
      <?php endif; ?>
      <h3>Rows</h3>
      <pre><?= htmlspecialchars(json_encode($result['rows'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), ENT_QUOTES, 'UTF-8') ?></pre>
    </div>
  <?php endif; ?>
</div>
</body>
</html>
