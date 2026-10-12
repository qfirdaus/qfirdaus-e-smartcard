<?php
declare(strict_types=1);

$GLOBALS['password_reset_test_policy'] = [];

function get_auth_policy_config(): array
{
    return $GLOBALS['password_reset_test_policy'];
}

function prestasi_super_admin_code(): string
{
    return 'ADM-SA';
}

function prestasi_group_code_equals(string $left, string $right): bool
{
    return strtoupper(trim($left)) === strtoupper(trim($right));
}

require_once __DIR__ . '/../public/includes/functions-db.php';

function passwordResetAssert(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function passwordResetPolicy(array $overrides = []): array
{
    return array_replace_recursive([
        'maintenance_mode' => false,
        'categories' => ['staf' => true, 'pelajar' => true, 'umum' => true],
        'sso' => [
            'enabled' => false,
            'mode' => 'MANUAL',
            'hybrid' => ['staf' => 'MANUAL', 'pelajar' => 'MANUAL', 'umum' => 'MANUAL'],
        ],
    ], $overrides);
}

$manualUser = [
    'f_flag' => 1,
    'f_statusID' => 1,
    'f_categoryUser' => 'STAF',
    'f_groupKod' => 'ADM-STAF',
];
$superAdmin = array_replace($manualUser, ['f_groupKod' => 'ADM-SA']);

$GLOBALS['password_reset_test_policy'] = passwordResetPolicy();
passwordResetAssert(password_reset_evaluate_eligibility($manualUser)['eligible'] === true, 'Akaun manual aktif mesti layak.');

$GLOBALS['password_reset_test_policy'] = passwordResetPolicy(['maintenance_mode' => true]);
passwordResetAssert(password_reset_evaluate_eligibility($manualUser)['reason'] === 'password_reset_maintenance_blocked', 'Akaun biasa mesti disekat semasa maintenance.');
passwordResetAssert(password_reset_evaluate_eligibility($superAdmin)['eligible'] === true, 'Super Admin manual mesti boleh pulih semasa maintenance.');

$GLOBALS['password_reset_test_policy'] = passwordResetPolicy(['categories' => ['staf' => false]]);
passwordResetAssert(password_reset_evaluate_eligibility($manualUser)['reason'] === 'password_reset_category_disabled', 'Category switch mesti disemak.');
passwordResetAssert(password_reset_evaluate_eligibility($superAdmin)['eligible'] === true, 'Super Admin manual boleh memintas category switch.');

$GLOBALS['password_reset_test_policy'] = passwordResetPolicy(['sso' => ['enabled' => true, 'mode' => 'ALL']]);
passwordResetAssert(password_reset_evaluate_eligibility($manualUser)['reason'] === 'password_reset_sso_managed', 'Akaun SSO ALL tidak boleh reset password tempatan.');
passwordResetAssert(password_reset_evaluate_eligibility($superAdmin)['reason'] === 'password_reset_sso_managed', 'Super Admin SSO juga mesti menggunakan IdP.');

$GLOBALS['password_reset_test_policy'] = passwordResetPolicy();
passwordResetAssert(password_reset_evaluate_eligibility(array_replace($manualUser, ['f_flag' => 0]))['reason'] === 'password_reset_account_disabled', 'Akaun disabled tidak boleh reset.');
passwordResetAssert(password_reset_evaluate_eligibility(array_replace($manualUser, ['f_categoryUser' => 'CUSTOM']))['reason'] === 'password_reset_unknown_category', 'Kategori tidak dikenali tidak boleh fallback ke UMUM.');

$GLOBALS['password_reset_test_policy'] = [];
passwordResetAssert(password_reset_evaluate_eligibility($manualUser)['reason'] === 'password_reset_policy_unavailable', 'Polisi tidak tersedia mesti fail closed.');

$forgotSource = file_get_contents(__DIR__ . '/../public/forgot-password.php');
$resetSource = file_get_contents(__DIR__ . '/../public/reset-password.php');
$userSource = file_get_contents(__DIR__ . '/../public/classes/User.php');
passwordResetAssert(is_string($forgotSource) && is_string($resetSource) && is_string($userSource), 'Fail flow reset tidak dapat dibaca.');
passwordResetAssert(str_contains($forgotSource, 'resolvePasswordResetCandidate'), 'Forgot-password tidak menggunakan resolver identifier selamat.');
passwordResetAssert(!str_contains($forgotSource, "'&ref='"), 'Login ID masih dimasukkan ke URL redirect.');
passwordResetAssert(str_contains($forgotSource, 'invalidatePasswordResetTokenByHash'), 'Token baharu tidak dibatalkan apabila emel gagal.');
passwordResetAssert(str_contains($resetSource, 'completePasswordReset'), 'Reset akhir tidak menggunakan transaksi lengkap.');
passwordResetAssert(!preg_match('/\bALTER\s+TABLE\b/i', $forgotSource . $resetSource . $userSource), 'Perubahan schema database dikesan.');

fwrite(STDOUT, "Password reset flow regression: OK\n");
