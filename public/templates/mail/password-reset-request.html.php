<?php
$safeDisplayName = htmlspecialchars((string)($displayName ?? ''), ENT_QUOTES, 'UTF-8');
$safeLoginId = htmlspecialchars((string)($loginId ?? ''), ENT_QUOTES, 'UTF-8');
$safeResetUrl = htmlspecialchars((string)($resetUrl ?? ''), ENT_QUOTES, 'UTF-8');
$safeExpiresAt = htmlspecialchars((string)($expiresAt ?? ''), ENT_QUOTES, 'UTF-8');
$safeSiteTitle = htmlspecialchars((string)($siteTitle ?? 'e-Prestasi'), ENT_QUOTES, 'UTF-8');
$safeMinutes = (int)($expiresInMinutes ?? 30);
?>
<!doctype html>
<html lang="ms">
<head>
  <meta charset="utf-8">
  <title>Reset Kata Laluan</title>
</head>
<body style="margin:0;padding:24px;background:#f8fafc;font-family:Arial,sans-serif;color:#0f172a;">
  <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="max-width:640px;margin:0 auto;background:#ffffff;border:1px solid #e2e8f0;border-radius:16px;overflow:hidden;">
    <tr>
      <td style="padding:28px 28px 18px;background:linear-gradient(135deg,#e0f2fe,#ecfeff);">
        <div style="font-size:12px;font-weight:700;letter-spacing:.08em;text-transform:uppercase;color:#0369a1;">Keselamatan Akaun</div>
        <h1 style="margin:12px 0 0;font-size:24px;line-height:1.2;">Reset kata laluan anda</h1>
      </td>
    </tr>
    <tr>
      <td style="padding:24px 28px;">
        <p style="margin:0 0 14px;">Salam <?= $safeDisplayName !== '' ? $safeDisplayName : $safeLoginId ?>,</p>
        <p style="margin:0 0 14px;line-height:1.7;">
          Kami menerima permintaan untuk menetapkan semula kata laluan akaun manual anda di <strong><?= $safeSiteTitle ?></strong>.
        </p>
        <p style="margin:0 0 18px;line-height:1.7;">
          Login ID: <strong><?= $safeLoginId ?></strong><br>
          Pautan ini akan tamat dalam <strong><?= $safeMinutes ?> minit</strong>, iaitu sehingga <strong><?= $safeExpiresAt ?></strong>.
        </p>
        <p style="margin:0 0 24px;">
          <a href="<?= $safeResetUrl ?>" style="display:inline-block;padding:13px 18px;border-radius:12px;background:#0284c7;color:#ffffff;text-decoration:none;font-weight:700;">Tetapkan Kata Laluan Baharu</a>
        </p>
        <p style="margin:0 0 12px;line-height:1.7;">
          Jika butang di atas tidak berfungsi, salin pautan berikut ke pelayar anda:
        </p>
        <p style="margin:0 0 18px;line-height:1.7;word-break:break-all;color:#0369a1;"><?= $safeResetUrl ?></p>
        <p style="margin:0;line-height:1.7;color:#475569;">
          Jika anda tidak membuat permintaan ini, abaikan sahaja emel ini. Kata laluan anda tidak akan berubah sehingga anda membuka pautan di atas dan menetapkan kata laluan baharu.
        </p>
      </td>
    </tr>
  </table>
</body>
</html>
