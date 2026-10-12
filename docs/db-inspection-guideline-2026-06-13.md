# Database Inspection Guideline

Updated: 2026-07-12

## Purpose

Standardize read-only database inspection for the IQS Framework workspace running natively in WSL 2 Ubuntu with Nginx and PHP-FPM.

## Runtime

- Project root: `/var/www/app/iqs-framework`
- Application public root: `/var/www/app/iqs-framework/public`
- CLI runtime: PHP installed in WSL
- Web runtime: PHP-FPM behind Nginx
- Environment file: `/var/www/app/iqs-framework/.env`

Run inspection commands from the project root:

```bash
cd /var/www/app/iqs-framework
php -v
php -m
```

The PHP CLI and PHP-FPM installations can load different configuration files. Before relying on a CLI inspection result, compare their PHP versions and enabled database extensions where necessary.

## Required Safety Rules

Database inspection must be read-only unless a separate task explicitly authorizes a mutation.

Allowed operations include:

- checking connection availability;
- listing schemas, tables, columns, indexes, and constraints;
- running bounded `SELECT` queries;
- using `EXPLAIN` for a read-only query;
- reading non-sensitive aggregate or diagnostic data required for the task.

Do not run:

- `INSERT`, `UPDATE`, `DELETE`, `REPLACE`, or `MERGE`;
- `CREATE`, `ALTER`, `DROP`, `TRUNCATE`, or migrations;
- stored procedures with unknown side effects;
- unrestricted exports of credentials, tokens, personal data, or message content;
- broad queries without a justified filter or row limit.

## Application-Accurate Bootstrap

Prefer the repository's existing configuration loader so `.env` parsing and database environment selection match the application:

```bash
php <<'PHP'
<?php
require __DIR__ . '/public/configuration/db_config.php';

$host = db_env('DB_MYSQL_MAIN_DEV_HOST');
$port = db_env('DB_MYSQL_MAIN_DEV_PORT', '3306');
$name = db_env('DB_MYSQL_MAIN_DEV_NAME');
$user = db_env('DB_MYSQL_MAIN_DEV_USER');
$pass = db_env('DB_MYSQL_MAIN_DEV_PASS');

$pdo = new PDO(
    "mysql:host={$host};port={$port};dbname={$name};charset=utf8mb4",
    $user,
    $pass,
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
);

$row = $pdo->query('SELECT DATABASE() AS database_name, VERSION() AS server_version')
    ->fetch(PDO::FETCH_ASSOC);

echo json_encode($row, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), PHP_EOL;
PHP
```

Do not print connection credentials or include `.env` values in logs, patches, screenshots, or reports.

## Bounded Read-Only Query Pattern

Use prepared statements and an explicit row limit:

```php
$stmt = $pdo->prepare(
    'SELECT f_id, f_status FROM example_table WHERE f_status = :status ORDER BY f_id DESC LIMIT 25'
);
$stmt->execute(['status' => 1]);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
```

Select only the columns required for the task. Avoid `SELECT *` when inspecting tables containing personal or sensitive information.

## Extension Checks

For MySQL inspections:

```bash
php --ri pdo_mysql
```

For ODBC or DBLIB connections:

```bash
php --ri PDO_ODBC
php --ri pdo_dblib
```

If an extension exists in PHP-FPM but not PHP CLI, do not change global PHP configuration as part of a repository task. Report the runtime mismatch and request separate authorization for any system-level change.

## Reporting

Report:

- which logical connection or environment was inspected;
- whether the check used PHP CLI or the web runtime;
- query purpose and bounded scope;
- schema or aggregate findings needed for the task;
- missing extensions or runtime mismatches.

Never report passwords, full DSNs containing credentials, authentication tokens, private message content, or unrelated personal records.
