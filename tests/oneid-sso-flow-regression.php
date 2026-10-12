<?php
/** Dependency-free OneID SSO response and identity regression suite. */
declare(strict_types=1);

require_once __DIR__ . '/../public/includes/sso-flow.php';

$passed = 0;
$failed = 0;

function ssoAssert(bool $condition, string $message): void
{
    global $passed, $failed;
    if ($condition) {
        $passed++;
        fwrite(STDOUT, "[PASS] {$message}\n");
        return;
    }
    $failed++;
    fwrite(STDERR, "[FAIL] {$message}\n");
}

$staff = SSO_BUILD_AUTH_HANDOFF(['u_category' => 2, 'data3' => '1234-56', 'data4' => 'CA12345']);
ssoAssert($staff['identity_valid'] && $staff['resolved_source'] === 'data3' && $staff['resolved_login_id'] === '1234-56', 'Kategori staf memilih data3 walaupun data4 turut diisi.');

$student = SSO_BUILD_AUTH_HANDOFF(['u_category' => 10, 'data3' => '1234-56', 'data4' => 'CA12345']);
ssoAssert($student['identity_valid'] && $student['resolved_source'] === 'data4' && $student['resolved_login_id'] === 'CA12345', 'Kategori pelajar memilih data4 walaupun data3 turut diisi.');

$normalizedStudent = SSO_BUILD_AUTH_HANDOFF(['u_category' => 10, 'data4' => ' ca12345 ']);
ssoAssert($normalizedStudent['identity_valid'] && $normalizedStudent['resolved_login_id'] === 'CA12345', 'Nombor matrik OneID dinormalisasikan kepada huruf besar.');

$normalizedStaff = SSO_BUILD_AUTH_HANDOFF(['u_category' => 2, 'data3' => '1234 - 56']);
ssoAssert($normalizedStaff['identity_valid'] && $normalizedStaff['resolved_login_id'] === '1234-56', 'Ruang di sekitar sengkang ID staf dinormalisasikan.');

$hybridAdminStudent = SSO_BUILD_AUTH_HANDOFF(['u_category' => 11, 'data3' => '1234-56', 'data4' => 'CA12345']);
ssoAssert($hybridAdminStudent['identity_valid'] && $hybridAdminStudent['resolved_source'] === 'data4', 'Kategori Pentadbiran-Pelajar memilih matrik data4.');

$hybridAcademicStudent = SSO_BUILD_AUTH_HANDOFF(['u_category' => 12, 'data3' => '1234-56', 'data4' => 'CA12345']);
ssoAssert($hybridAcademicStudent['identity_valid'] && $hybridAcademicStudent['resolved_source'] === 'data4', 'Kategori Akademik-Pelajar memilih matrik data4.');

$invalidIdentity = SSO_BUILD_AUTH_HANDOFF(['data3' => '123', 'data4' => 'A-123']);
ssoAssert(!$invalidIdentity['identity_valid'], 'Payload tanpa identifier sah ditolak.');

$legacyBoth = SSO_BUILD_AUTH_HANDOFF(['data3' => '1234-56', 'data4' => 'CA12345']);
ssoAssert($legacyBoth['identity_valid'] && $legacyBoth['resolved_source'] === 'data3' && $legacyBoth['identity_resolution'] === 'legacy', 'Payload legacy tanpa kategori mengekalkan keutamaan data3.');

$knownCategoryMissingIdentity = SSO_BUILD_AUTH_HANDOFF(['u_category' => 10, 'data3' => '1234-56', 'data4' => '']);
ssoAssert(!$knownCategoryMissingIdentity['identity_valid'], 'Kategori dikenali ditolak jika identifier kanoniknya tiada.');

$valid = SSO_CLASSIFY_API_RESPONSE(['respond_flag' => '1', 'respond' => '1', 'respond_user_packet' => ['data3' => '1234-56']]);
ssoAssert($valid['status'] === 'valid' && empty($valid['reissued']), 'Respons token sah diterima.');

$invalidToken = SSO_CLASSIFY_API_RESPONSE(['respond_flag' => '1', 'respond' => '0']);
ssoAssert($invalidToken['status'] === 'invalid_token', 'Token ditolak dipetakan kepada invalid_token.');

$invalidSite = SSO_CLASSIFY_API_RESPONSE(['respond_flag' => '1', 'respond' => '0', 'message' => 'Invalid site id']);
ssoAssert($invalidSite['status'] === 'invalid_site', 'Ralat site ID dikenal pasti daripada respons OneID.');

$invalidGeneratedCode = SSO_CLASSIFY_API_RESPONSE(['respond_flag' => '1', 'respond' => '0', 'respond_description' => 'Application credential is invalid.']);
ssoAssert($invalidGeneratedCode['status'] === 'invalid_site', 'Generated atau legacy site credential yang ditolak dikenal pasti melalui respond_description.');

$siteNotAllowed = SSO_CLASSIFY_API_RESPONSE(['respond_flag' => '1', 'respond' => '0', 'respond_description' => 'Token is active and available but Site not allowed to access']);
ssoAssert($siteNotAllowed['status'] === 'invalid_site', 'Site yang tidak dibenarkan tidak disalah lapor sebagai token tidak sah.');

$reissued = SSO_CLASSIFY_API_RESPONSE(['respond_flag' => '2', 'respond_new_token' => 'replacement', 'respond_user_packet' => ['data4' => 'CA12345']]);
ssoAssert($reissued['status'] === 'valid' && $reissued['reissued'] && $reissued['token'] === 'replacement', 'Auto-reissue menghasilkan token gantian yang sah.');

$brokenReissue = SSO_CLASSIFY_API_RESPONSE(['respond_flag' => '2']);
ssoAssert($brokenReissue['status'] === 'invalid_response', 'Auto-reissue tidak lengkap ditolak.');

ssoAssert(SSO_CLASSIFY_API_RESPONSE(null)['status'] === 'invalid_response', 'Respons kosong ditolak.');
ssoAssert(SSO_CLASSIFY_API_RESPONSE(['respond_flag' => '9'])['status'] === 'invalid_response', 'Flag tidak dikenali ditolak.');
ssoAssert(!SSO_VALIDATE_MATRIK('A-123') && !SSO_VALIDATE_STAFID('1234'), 'Format identifier yang salah ditolak.');

$clientSource = file_get_contents(__DIR__ . '/../public/sso_sp_client.php') ?: '';
ssoAssert(str_contains($clientSource, 'CURLOPT_CONNECTTIMEOUT, 5') && str_contains($clientSource, 'CURLOPT_TIMEOUT, 15'), 'Timeout sambungan OneID dilindungi.');
ssoAssert(str_contains($clientSource, 'CURLOPT_SSL_VERIFYHOST, 2') && str_contains($clientSource, 'CURLOPT_SSL_VERIFYPEER, true'), 'Pengesahan TLS OneID diaktifkan.');
ssoAssert(str_contains($clientSource, "function_exists('curl_init')"), 'Ketiadaan sambungan cURL dikendalikan sebagai kegagalan servis.');
ssoAssert(strpos($clientSource, "\$incomingToken =") < strpos($clientSource, "\$cookieToken ="), 'Token callback baharu diproses sebelum cookie lama.');
ssoAssert(!str_contains($clientSource, 'echo "X"'), 'Tiada lagi output X bagi auto-reissue.');

$loginSource = file_get_contents(__DIR__ . '/../public/login.php') ?: '';
ssoAssert(str_contains($loginSource, '$ssoHandoffTtlSeconds = 300'), 'Handoff SSO kekal mempunyai TTL lima minit.');
ssoAssert(str_contains($loginSource, "\$correlationId === ''"), 'Handoff tanpa correlation ID ditolak.');

$controllerSource = file_get_contents(__DIR__ . '/../public/controllers/LoginController.php') ?: '';
ssoAssert(str_contains($controllerSource, "UPPER(LTRIM(RTRIM(CONVERT(VARCHAR(50), matrik))))"), 'Lookup pelajar menormalisasikan matrik daripada sumber.');
ssoAssert(str_contains($controllerSource, "UPPER(LTRIM(RTRIM(CONVERT(VARCHAR(20), statuskategori)))) = 'AKTIF'"), 'Status aktif pelajar tahan terhadap ruang dan perbezaan huruf.');
ssoAssert(str_contains($controllerSource, "LTRIM(RTRIM(CONVERT(VARCHAR(20), kodstatus))) = '1'"), 'Lookup staf tidak lagi menggunakan penukaran integer yang rapuh.');
ssoAssert(str_contains($controllerSource, 'existing_account_disabled') && str_contains($controllerSource, 'existing_account_category_mismatch'), 'Akaun sedia ada yang disabled atau salah kategori dilindungi.');
ssoAssert(strpos($controllerSource, "if (\$source === 'data4')") < strpos($controllerSource, "!empty(\$handoff['data3_valid'])"), 'Kategori provisioning mengutamakan resolved source OneID sebelum flag identifier sampingan.');

$userListSource = file_get_contents(__DIR__ . '/../public/pages/senarai-pengguna.php') ?: '';
ssoAssert(str_contains($userListSource, "\$showSsoIndicator = \$isAutoProvisioned || \$isSsoIdentity"), 'Indikator senarai pengguna meliputi akaun auto provision dan identiti OneID SSO.');
ssoAssert(str_contains($userListSource, 'const showSsoIndicator = isAutoProvisioned || isSsoIdentity'), 'Baris pengguna AJAX mengekalkan indikator OneID SSO.');

$metadataMigration = file_get_contents(__DIR__ . '/../docs/user-sso-metadata-migration-2026-09-03.sql') ?: '';
ssoAssert(str_contains($metadataMigration, 'f_isAutoProvisioned') && str_contains($metadataMigration, 'f_identitySource'), 'Migration metadata pengguna menyediakan kedua-dua kolum indikator SSO.');

fwrite(STDOUT, "\nResult: {$passed} passed, {$failed} failed.\n");
exit($failed === 0 ? 0 : 1);
