<?php
/**
 * IQS FRAMEWORK CORE FILE
 *
 * READ ONLY for downstream project programmers.
 * Do not modify this file directly in template or cloned projects.
 * Custom changes must be implemented in project-specific files
 * or approved extension points.
 */declare(strict_types=1);

final class DatabaseConnectionFactory
{
    public function make(array $config): PDO
    {
        $options = $config['options'] ?? [];
        $driver = strtolower((string)($config['driver'] ?? ''));
        $dsn = (string)($config['dsn'] ?? '');
        $pdoDriver = $driver !== '' ? $driver : strtolower(strtok($dsn, ':') ?: '');
        $defaults = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ];

        if ($driver === 'mysql' && defined('PDO::MYSQL_ATTR_INIT_COMMAND')) {
            $defaults[PDO::MYSQL_ATTR_INIT_COMMAND] = 'SET NAMES utf8mb4';
        }

        if ($driver === 'odbc' && str_contains(strtolower((string)($config['dsn'] ?? '')), 'sybase')) {
            $defaults[PDO::ATTR_EMULATE_PREPARES] = true;
            $defaults[PDO::ATTR_CURSOR] = PDO::CURSOR_FWDONLY;
        }

        $this->assertPdoDriverAvailable($pdoDriver);

        return new PDO(
            $dsn,
            $config['user'] ?? null,
            $config['pass'] ?? null,
            $options + $defaults
        );
    }

    private function assertPdoDriverAvailable(string $driver): void
    {
        if ($driver === '') {
            return;
        }

        $available = PDO::getAvailableDrivers();
        if (in_array($driver, $available, true)) {
            return;
        }

        $installed = $available !== [] ? implode(', ', $available) : 'none';
        $hasDblib = in_array('dblib', $available, true);
        $hasOdbc = in_array('odbc', $available, true);

        $hint = match ($driver) {
            'sqlsrv' => $hasDblib
                ? 'Runtime ini tiada sqlsrv/pdo_sqlsrv tetapi ada dblib. Untuk Linux FreeTDS, tukar Additional Database driver kepada dblib dan gunakan port MSSQL 1433 jika tiada port khusus.'
                : ($hasOdbc
                    ? 'Runtime ini tiada sqlsrv/pdo_sqlsrv tetapi ada odbc. Gunakan driver odbc dengan DSN yang telah dikonfigurasi, atau enable sqlsrv/pdo_sqlsrv jika run di Windows/native.'
                    : 'Enable Microsoft SQL Server PHP extensions sqlsrv dan pdo_sqlsrv untuk Windows/native, atau enable dblib/odbc untuk Linux.'),
            'odbc' => $hasDblib
                ? 'Runtime ini tiada pdo_odbc tetapi ada dblib. Untuk Linux FreeTDS, gunakan driver dblib atau enable PDO ODBC jika mahu guna DSN ODBC.'
                : 'Install dan enable PDO ODBC extension serta ODBC driver/DSN yang sesuai.',
            'dblib' => $hasOdbc
                ? 'Runtime ini tiada PDO DBLIB/FreeTDS tetapi ada ODBC. Gunakan driver odbc dengan DSN yang sesuai, atau enable pdo_dblib.'
                : 'Install dan enable PDO DBLIB/FreeTDS extension.',
            'mysql' => 'Install dan enable PDO MySQL extension.',
            default => 'Install dan enable PDO driver yang sepadan dengan DSN ini.',
        };

        throw new RuntimeException(sprintf(
            "PDO driver '%s' tidak tersedia dalam PHP runtime. Installed PDO drivers: %s. %s",
            $driver,
            $installed,
            $hint
        ));
    }
}
