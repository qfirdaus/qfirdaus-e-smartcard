<?php
$safeTitle = htmlspecialchars((string)($title ?? ''), ENT_QUOTES, 'UTF-8');
$safePreheader = htmlspecialchars((string)($preheader ?? ''), ENT_QUOTES, 'UTF-8');
$safeSystemName = htmlspecialchars((string)($systemName ?? 'e-Base'), ENT_QUOTES, 'UTF-8');
$safeOrganizationName = htmlspecialchars((string)($organizationName ?? ''), ENT_QUOTES, 'UTF-8');
$safeSupportEmail = htmlspecialchars((string)($supportEmail ?? ''), ENT_QUOTES, 'UTF-8');
$safeFooterNote = htmlspecialchars((string)($footerNote ?? ''), ENT_QUOTES, 'UTF-8');
$displayBrand = $safeOrganizationName !== '' ? $safeOrganizationName : $safeSystemName;
?>
<!doctype html>
<html lang="ms">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= $safeTitle !== '' ? $safeTitle : $safeSystemName ?></title>
</head>
<body style="margin:0;padding:24px;background:#f8fafc;font-family:Arial,sans-serif;color:#0f172a;">
  <div style="display:none;max-height:0;overflow:hidden;opacity:0;color:transparent;">
    <?= $safePreheader ?>
  </div>

  <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="max-width:680px;margin:0 auto;background:#ffffff;border:1px solid #e2e8f0;border-radius:16px;overflow:hidden;">
    <tr>
      <td style="padding:26px 28px 18px;background:linear-gradient(135deg,#e8efff,#f8fbff);border-bottom:1px solid #dbe4f0;">
        <div style="font-size:12px;font-weight:700;letter-spacing:.08em;text-transform:uppercase;color:#4254ba;"><?= $safeSystemName ?></div>
        <h1 style="margin:10px 0 0;font-size:24px;line-height:1.2;color:#0f172a;"><?= $safeTitle !== '' ? $safeTitle : $displayBrand ?></h1>
      </td>
    </tr>
    <tr>
      <td style="padding:28px;line-height:1.7;color:#334155;">
        <?= (string)($contentHtml ?? '') ?>
      </td>
    </tr>
    <tr>
      <td style="padding:18px 28px 24px;background:#f8fafc;border-top:1px solid #e2e8f0;font-size:13px;line-height:1.7;color:#64748b;">
        <div><strong style="color:#334155;"><?= $displayBrand ?></strong></div>
        <?php if ($safeSupportEmail !== ''): ?>
          <div>Sokongan: <a href="mailto:<?= $safeSupportEmail ?>" style="color:#4254ba;text-decoration:none;"><?= $safeSupportEmail ?></a></div>
        <?php endif; ?>
        <?php if ($safeFooterNote !== ''): ?>
          <div style="margin-top:8px;"><?= $safeFooterNote ?></div>
        <?php endif; ?>
      </td>
    </tr>
  </table>
</body>
</html>
