Notifikasi Pertukaran Kata Laluan
=================================

Salam <?= (string)($displayName ?? $loginId ?? '') ?>,

Ini adalah notifikasi bahawa kata laluan akaun anda di <?= (string)($siteTitle ?? 'e-Prestasi') ?> telah berjaya dikemas kini.

Login ID: <?= (string)($loginId ?? '') . PHP_EOL ?>
Masa perubahan: <?= (string)($changedAt ?? '') . PHP_EOL ?>

Jika anda tidak melakukan perubahan ini, sila hubungi pentadbir sistem dengan segera.
