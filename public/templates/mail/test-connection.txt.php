<?php
// Template: test-connection.txt.php
// Vars expected:
// $subject, $siteTitle, $systemName, $organizationName, $supportEmail, $footerNote,
// $senderDisplayName, $fromAddr, $to, $mailHostDisplay, $mailPortDisplay,
// $mailEncryptionDisplay, $testedAt, $referenceCode

$subject = $subject ?? 'Ujian Sambungan Emel';
$siteTitle = $siteTitle ?? 'Base System';
$systemName = $systemName ?? $siteTitle;
$organizationName = $organizationName ?? '';
$supportEmail = $supportEmail ?? '';
$footerNote = $footerNote ?? 'Emel ini dijana secara automatik. Sila jangan balas emel ini.';
$senderDisplayName = $senderDisplayName ?? '';
$fromAddr = $fromAddr ?? '';
$to = $to ?? '';
$mailHostDisplay = $mailHostDisplay ?? '-';
$mailPortDisplay = $mailPortDisplay ?? '-';
$mailEncryptionDisplay = $mailEncryptionDisplay ?? 'AUTO';
$testedAt = $testedAt ?? '';
$referenceCode = $referenceCode ?? '';
?>
═══════════════════════════════════════════════════════════════
                     UJIAN SAMBUNGAN EMEL
═══════════════════════════════════════════════════════════════

Salam sejahtera,

YBhg. Datuk/Dato'/Prof. Emeritus/Prof./Prof. Madya/Dr./Tuan/Puan,

Emel ujian ini dihantar bagi mengesahkan bahawa tetapan sambungan emel
dalam modul konfigurasi sistem berfungsi dengan baik berdasarkan parameter
semasa yang telah ditetapkan oleh pentadbir.

───────────────────────────────────────────────────────────────
                      SYSTEM IDENTITY
───────────────────────────────────────────────────────────────

System Name    : <?= $systemName . PHP_EOL ?>
Site Title     : <?= $siteTitle . PHP_EOL ?>
<?php if ($organizationName !== ''): ?>
Organization   : <?= $organizationName . PHP_EOL ?>
<?php endif; ?>
<?php if ($supportEmail !== ''): ?>
Support Email  : <?= $supportEmail . PHP_EOL ?>
<?php endif; ?>

───────────────────────────────────────────────────────────────
                     DELIVERY DETAILS
───────────────────────────────────────────────────────────────

Sender Name    : <?= $senderDisplayName . PHP_EOL ?>
Sender Address : <?= $fromAddr . PHP_EOL ?>
Recipient      : <?= $to . PHP_EOL ?>
SMTP Host      : <?= $mailHostDisplay . PHP_EOL ?>
Port           : <?= $mailPortDisplay . PHP_EOL ?>
Encryption     : <?= $mailEncryptionDisplay . PHP_EOL ?>
Test Time      : <?= $testedAt . PHP_EOL ?>

───────────────────────────────────────────────────────────────

Sambungan SMTP semasa berjaya digunakan untuk menghantar emel
ujian ini. Jika anda menerima emel ini, konfigurasi penghantaran
emel sistem sedang berfungsi seperti yang dijangkakan.

<?= PHP_EOL . $footerNote . PHP_EOL ?>
<?php if ($referenceCode !== ''): ?>
Reference: <?= $referenceCode . PHP_EOL ?>
<?php endif; ?>
