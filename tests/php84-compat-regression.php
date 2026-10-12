<?php
declare(strict_types=1);

function php84CompatibilityAssert(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$initSource = file_get_contents(__DIR__ . '/../public/includes/init.php');
$auditLoggerSource = file_get_contents(__DIR__ . '/../public/classes/AuditLogger.php');
$auditExportSource = file_get_contents(__DIR__ . '/../public/ajax/audit-center-export.php');

php84CompatibilityAssert(is_string($initSource), 'init.php tidak dapat dibaca.');
php84CompatibilityAssert(!str_contains($initSource, 'E_STRICT'), 'Bootstrap masih menggunakan E_STRICT yang deprecated pada PHP 8.4.');
php84CompatibilityAssert(is_string($auditLoggerSource), 'AuditLogger.php tidak dapat dibaca.');
php84CompatibilityAssert(
    str_contains($auditLoggerSource, "str_getcsv(\$inside, ',', \"'\", '')"),
    'AuditLogger tidak menetapkan parameter CSV escape secara eksplisit.'
);
php84CompatibilityAssert(is_string($auditExportSource), 'audit-center-export.php tidak dapat dibaca.');
php84CompatibilityAssert(
    substr_count($auditExportSource, "fputcsv(\$out,") === substr_count($auditExportSource, ", ',', '\"', '')"),
    'Tidak semua panggilan fputcsv menetapkan separator, enclosure, dan escape secara eksplisit.'
);

$stream = fopen('php://temp', 'w+');
php84CompatibilityAssert(is_resource($stream), 'Temporary CSV stream gagal dibuka.');
fputcsv($stream, ['comma,value', 'quote"value', "line\nbreak"], ',', '"', '');
rewind($stream);
$row = fgetcsv($stream, null, ',', '"', '');
fclose($stream);

php84CompatibilityAssert(
    $row === ['comma,value', 'quote"value', "line\nbreak"],
    'CSV round-trip berubah selepas explicit escape configuration.'
);

$capturedErrors = [];
set_error_handler(static function (int $severity, string $message) use (&$capturedErrors): bool {
    if ($severity === E_DEPRECATED) {
        $capturedErrors[] = $message;
        return true;
    }
    return false;
});
str_getcsv("'alpha','beta'", ',', "'", '');
restore_error_handler();

php84CompatibilityAssert($capturedErrors === [], 'CSV compatibility probe menghasilkan E_DEPRECATED.');

fwrite(STDOUT, 'PHP 8.4 compatibility regression: OK' . PHP_EOL);
