Reset Kata Laluan Akaun
=======================

Salam <?= (string)($displayName ?? $loginId ?? '') ?>,

Kami menerima permintaan untuk menetapkan semula kata laluan akaun manual anda di <?= (string)($siteTitle ?? 'e-Prestasi') ?>.

Login ID: <?= (string)($loginId ?? '') . PHP_EOL ?>
Pautan ini akan tamat dalam <?= (int)($expiresInMinutes ?? 30) ?> minit, iaitu sehingga <?= (string)($expiresAt ?? '') . PHP_EOL ?>

Buka pautan berikut untuk menetapkan kata laluan baharu:
<?= (string)($resetUrl ?? '') . PHP_EOL ?>

Jika anda tidak membuat permintaan ini, abaikan sahaja emel ini. Kata laluan anda tidak akan berubah sehingga anda membuka pautan di atas dan menetapkan kata laluan baharu.
