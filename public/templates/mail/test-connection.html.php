<?php
// Template: test-connection.html.php
// Vars expected:
// $subject, $siteTitle, $systemName, $organizationName, $supportEmail, $footerNote,
// $senderDisplayName, $fromAddr, $to, $mailHostDisplay, $mailPortDisplay,
// $mailEncryptionDisplay, $testedAt, $logoUrl, $referenceCode

if (!function_exists('e')) {
  function e($v) { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
}

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
$logoUrl = $logoUrl ?? '';
$referenceCode = $referenceCode ?? '';
?>
<!doctype html>
<html lang="ms">
<head>
  <meta charset="utf-8">
  <title><?= e($subject) ?></title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
</head>
<body style="margin:0;padding:0;background:#eef2f7;font-family:Segoe UI,Arial,sans-serif;color:#0f172a;">
  <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background:#eef2f7;padding:28px 12px;">
    <tr>
      <td align="center">
        <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="max-width:720px;background:#ffffff;border-radius:20px;overflow:hidden;box-shadow:0 14px 40px rgba(15,23,42,.14);">
          <tr>
            <td bgcolor="#0f172a" style="background-color:#0f172a;background-image:linear-gradient(135deg,#0f172a 0%,#1d4ed8 55%,#0ea5e9 100%);padding:28px 32px;color:#ffffff;">
              <table role="presentation" width="100%" cellspacing="0" cellpadding="0">
                <tr>
                  <td style="vertical-align:middle;">
                    <?php if ($logoUrl !== ''): ?>
                      <img src="<?= e($logoUrl) ?>" alt="<?= e($siteTitle) ?>" style="max-height:48px;display:block;margin-bottom:16px;">
                    <?php endif; ?>
                    <div style="font-size:13px;font-weight:700;letter-spacing:.12em;text-transform:uppercase;color:#dbeafe;opacity:.92;margin-bottom:8px;">Email Diagnostics</div>
                    <div style="font-size:28px;font-weight:700;line-height:1.2;letter-spacing:-.02em;color:#ffffff;"><?= e($subject) ?></div>
                    <div style="font-size:14px;line-height:1.7;color:#eff6ff;opacity:.96;margin-top:10px;">
                      Template ini mengesahkan bahawa konfigurasi SMTP semasa berjaya menghantar emel daripada sistem <strong><?= e($siteTitle) ?></strong>.
                    </div>
                  </td>
                </tr>
              </table>
            </td>
          </tr>

          <tr>
            <td style="padding:30px 32px 18px;">
              <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="margin-bottom:20px;">
                <tr>
                  <td style="padding:0 0 14px;font-size:16px;line-height:1.7;color:#334155;">
                    Salam sejahtera,
                  </td>
                </tr>
                <tr>
                  <td style="padding:0 0 14px;font-size:15px;line-height:1.75;color:#334155;">
                    YBhg. Datuk/Dato&rsquo;/Prof. Emeritus/Prof./Prof. Madya/Dr./Tuan/Puan,
                  </td>
                </tr>
                <tr>
                  <td style="padding:0;font-size:15px;line-height:1.75;color:#475569;">
                    Emel ujian ini dihantar bagi mengesahkan bahawa tetapan sambungan emel dalam modul konfigurasi sistem berfungsi dengan baik berdasarkan parameter semasa yang telah ditetapkan oleh pentadbir.
                  </td>
                </tr>
              </table>

              <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="border-collapse:separate;border-spacing:0;margin-bottom:22px;border:1px solid #dbe4f0;border-radius:16px;overflow:hidden;">
                <tr>
                  <td colspan="2" style="padding:16px 18px;background:#f8fafc;border-bottom:1px solid #dbe4f0;">
                    <div style="font-size:13px;font-weight:700;letter-spacing:.08em;text-transform:uppercase;color:#64748b;">System Identity</div>
                  </td>
                </tr>
                <tr>
                  <td style="padding:12px 18px;background:#fcfdff;width:220px;border-bottom:1px solid #e5edf5;color:#64748b;font-size:13px;font-weight:600;">System Name</td>
                  <td style="padding:12px 18px;border-bottom:1px solid #e5edf5;font-size:14px;font-weight:600;color:#0f172a;"><?= e($systemName) ?></td>
                </tr>
                <tr>
                  <td style="padding:12px 18px;background:#fcfdff;width:220px;border-bottom:1px solid #e5edf5;color:#64748b;font-size:13px;font-weight:600;">Site Title</td>
                  <td style="padding:12px 18px;border-bottom:1px solid #e5edf5;font-size:14px;color:#334155;"><?= e($siteTitle) ?></td>
                </tr>
                <?php if ($organizationName !== ''): ?>
                <tr>
                  <td style="padding:12px 18px;background:#fcfdff;width:220px;border-bottom:1px solid #e5edf5;color:#64748b;font-size:13px;font-weight:600;">Organization</td>
                  <td style="padding:12px 18px;border-bottom:1px solid #e5edf5;font-size:14px;color:#334155;"><?= e($organizationName) ?></td>
                </tr>
                <?php endif; ?>
                <?php if ($supportEmail !== ''): ?>
                <tr>
                  <td style="padding:12px 18px;background:#fcfdff;width:220px;color:#64748b;font-size:13px;font-weight:600;">Support Email</td>
                  <td style="padding:12px 18px;font-size:14px;color:#334155;"><?= e($supportEmail) ?></td>
                </tr>
                <?php endif; ?>
              </table>

              <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="border-collapse:separate;border-spacing:0;margin-bottom:22px;border:1px solid #dbe4f0;border-radius:16px;overflow:hidden;">
                <tr>
                  <td colspan="2" style="padding:16px 18px;background:#f8fafc;border-bottom:1px solid #dbe4f0;">
                    <div style="font-size:13px;font-weight:700;letter-spacing:.08em;text-transform:uppercase;color:#64748b;">Delivery Details</div>
                  </td>
                </tr>
                <tr>
                  <td style="padding:12px 18px;background:#fcfdff;width:220px;border-bottom:1px solid #e5edf5;color:#64748b;font-size:13px;font-weight:600;">Sender Name</td>
                  <td style="padding:12px 18px;border-bottom:1px solid #e5edf5;font-size:14px;color:#334155;"><?= e($senderDisplayName) ?></td>
                </tr>
                <tr>
                  <td style="padding:12px 18px;background:#fcfdff;width:220px;border-bottom:1px solid #e5edf5;color:#64748b;font-size:13px;font-weight:600;">Sender Address</td>
                  <td style="padding:12px 18px;border-bottom:1px solid #e5edf5;font-size:14px;color:#334155;"><?= e($fromAddr) ?></td>
                </tr>
                <tr>
                  <td style="padding:12px 18px;background:#fcfdff;width:220px;border-bottom:1px solid #e5edf5;color:#64748b;font-size:13px;font-weight:600;">Recipient</td>
                  <td style="padding:12px 18px;border-bottom:1px solid #e5edf5;font-size:14px;color:#334155;"><?= e($to) ?></td>
                </tr>
                <tr>
                  <td style="padding:12px 18px;background:#fcfdff;width:220px;border-bottom:1px solid #e5edf5;color:#64748b;font-size:13px;font-weight:600;">SMTP Host</td>
                  <td style="padding:12px 18px;border-bottom:1px solid #e5edf5;font-size:14px;color:#334155;"><?= e($mailHostDisplay) ?></td>
                </tr>
                <tr>
                  <td style="padding:12px 18px;background:#fcfdff;width:220px;border-bottom:1px solid #e5edf5;color:#64748b;font-size:13px;font-weight:600;">Port</td>
                  <td style="padding:12px 18px;border-bottom:1px solid #e5edf5;font-size:14px;color:#334155;"><?= e($mailPortDisplay) ?></td>
                </tr>
                <tr>
                  <td style="padding:12px 18px;background:#fcfdff;width:220px;border-bottom:1px solid #e5edf5;color:#64748b;font-size:13px;font-weight:600;">Encryption</td>
                  <td style="padding:12px 18px;border-bottom:1px solid #e5edf5;font-size:14px;color:#334155;"><?= e($mailEncryptionDisplay) ?></td>
                </tr>
                <tr>
                  <td style="padding:12px 18px;background:#fcfdff;width:220px;color:#64748b;font-size:13px;font-weight:600;">Test Time</td>
                  <td style="padding:12px 18px;font-size:14px;color:#334155;"><?= e($testedAt) ?></td>
                </tr>
              </table>

              <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="margin-bottom:18px;">
                <tr>
                  <td style="padding:18px 20px;border-radius:16px;background:linear-gradient(180deg,#ecfeff,#f8fafc);border:1px solid #c7f0ff;">
                    <div style="font-size:14px;font-weight:700;color:#0f766e;margin-bottom:8px;">Status</div>
                    <div style="font-size:14px;line-height:1.7;color:#155e75;">
                      Sambungan SMTP semasa berjaya digunakan untuk menghantar emel ujian ini. Jika anda menerima emel ini dengan paparan yang lengkap, konfigurasi asas penghantaran emel sistem sedang berfungsi seperti yang dijangkakan.
                    </div>
                  </td>
                </tr>
              </table>
            </td>
          </tr>

          <tr>
            <td style="padding:0 32px 26px;">
              <div style="height:1px;background:#e5edf5;"></div>
            </td>
          </tr>

          <tr>
            <td style="padding:0 32px 30px;">
              <div style="font-size:13px;line-height:1.8;color:#64748b;">
                <?= nl2br(e($footerNote)) ?>
              </div>
              <?php if ($referenceCode !== ''): ?>
                <div style="margin-top:14px;font-size:11px;letter-spacing:.08em;text-transform:uppercase;color:#94a3b8;">
                  Reference: <?= e($referenceCode) ?>
                </div>
              <?php endif; ?>
            </td>
          </tr>
        </table>
      </td>
    </tr>
  </table>
</body>
</html>
