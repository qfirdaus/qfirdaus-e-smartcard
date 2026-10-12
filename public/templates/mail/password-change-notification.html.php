<?php
$safeDisplayName = htmlspecialchars((string)($displayName ?? ''), ENT_QUOTES, 'UTF-8');
$safeLoginId = htmlspecialchars((string)($loginId ?? ''), ENT_QUOTES, 'UTF-8');
$safeChangedAt = htmlspecialchars((string)($changedAt ?? ''), ENT_QUOTES, 'UTF-8');
$safeSiteTitle = htmlspecialchars((string)($siteTitle ?? 'e-Prestasi'), ENT_QUOTES, 'UTF-8');
?>
<!doctype html>
<html lang="ms">
<head>
  <meta charset="utf-8">
  <title>Notifikasi Pertukaran Kata Laluan</title>
</head>
<body style="margin:0;padding:24px;background:#f8fafc;font-family:Arial,sans-serif;color:#0f172a;">
  <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="max-width:640px;margin:0 auto;background:#ffffff;border:1px solid #e2e8f0;border-radius:16px;overflow:hidden;">
    <tr>
      <td style="padding:28px 28px 18px;background:linear-gradient(135deg,#dcfce7,#ecfeff);">
        <div style="font-size:12px;font-weight:700;letter-spacing:.08em;text-transform:uppercase;color:#0f766e;">Notifikasi Keselamatan</div>
        <h1 style="margin:12px 0 0;font-size:24px;line-height:1.2;">Kata laluan anda telah dikemas kini</h1>
      </td>
    </tr>
    <tr>
      <td style="padding:24px 28px;">
        <p style="margin:0 0 14px;">Salam <?= $safeDisplayName !== '' ? $safeDisplayName : $safeLoginId ?>,</p>
        <p style="margin:0 0 14px;line-height:1.7;">
          Ini adalah notifikasi bahawa kata laluan akaun anda di <strong><?= $safeSiteTitle ?></strong> telah berjaya dikemas kini.
        </p>
        <p style="margin:0 0 18px;line-height:1.7;">
          Login ID: <strong><?= $safeLoginId ?></strong><br>
          Masa perubahan: <strong><?= $safeChangedAt ?></strong>
        </p>
        <p style="margin:0;line-height:1.7;color:#475569;">
          Jika anda tidak melakukan perubahan ini, sila hubungi pentadbir sistem dengan segera.
        </p>
      </td>
    </tr>
  </table>
</body>
</html>
