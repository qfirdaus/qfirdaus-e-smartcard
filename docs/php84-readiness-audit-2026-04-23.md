# PHP 8.4 Readiness Audit

> Superseded for current planning by [`php84-upgrade-audit-2026-10-07.md`](./php84-upgrade-audit-2026-10-07.md), which validates release 1.10.0 against the installed PHP 8.4.26 runtime and the current WSL/Nginx/PHP-FPM platform.

> Historical note (2026-07-12): this audit was written while the project used a Docker/Apache runtime. The active project runtime is now WSL 2 Ubuntu with Nginx and PHP-FPM. Docker-specific rollout recommendations below are retained only as historical audit context; use the native WSL staging runtime for current validation.

Date: 2026-04-23

Scope:
- Repository audit for upgrade readiness from PHP 8.3.30 to PHP 8.4
- Workspace only: `/var/www/app\iqs-framework`
- Focused on app-owned code under `public/` and current runtime alignment
- Vendor bundles were not treated as app code for remediation, but they are noted where relevant

Current baseline:
- Server runtime: PHP 8.3.30
- Docker runtime: `php:8.3.30-apache`
- Database target unchanged for this audit: MySQL 8.0.41

Official references:
- PHP 8.4 migration guide: https://www.php.net/manual/en/migration84.php
- PHP 8.4 backward incompatible changes: https://www.php.net/manual/en/migration84.incompatible.php
- PHP supported versions: https://www.php.net/supported-versions.php

## Verdict

Overall readiness for PHP 8.4: `Good, with low-to-moderate upgrade risk`

The repository is in a materially better state after the PHP 8.3.30 alignment work:
- typed-property nullability issue in `Database.php` was already corrected
- auth and mail flows were hardened
- audit outcome normalization was fixed

No high-confidence PHP 8.4 blocker was found in app-owned code during this audit.

## Repo Findings

### 1. No clear app-owned blocker found for major PHP 8.4 migration changes

The following patterns were checked in app-owned code and no risky usage was found:
- `get_class()` without arguments
- `get_parent_class()` without arguments
- `php_uname()` with dynamic or invalid mode
- legacy `Serializable` reliance in app-owned classes
- `utf8_encode()` / `utf8_decode()`
- `create_function()`
- deprecated `preg_replace('/.../e')`

Impact:
- This reduces the chance of direct PHP 8.4 migration failures from known language/runtime changes.

### 2. `exit()` / `die()` usage exists, but current app usage looks safe

Relevant app-owned occurrences found:
- `public/ajax/manual-view.php`
- `public/pages/manage-manuals.php`

PHP 8.4 changes `exit()` behavior to act more like a function. In this repo, the app-owned calls found are string literals and straightforward termination paths, so they are low risk.

Impact:
- No immediate code change required
- Still worth smoke-testing the manual-view and manuals-management flows after upgrade

### 3. Mail flow is a staging-critical area, but code is now in acceptable shape

Relevant files:
- `public/classes/Mailer.php`
- `public/forgot-password.php`
- `public/reset-password.php`
- `public/change-password.php`

Current assessment:
- mail failures are now easier to trace
- development logging and SMTP debug behavior are more reliable than before
- auth flows no longer silently hide some important mail failures in the same way as earlier

Residual risk:
- PHP 8.4 itself is not the main concern here; SMTP/auth/provider behavior remains the more likely operational failure point

Impact:
- treat mail flow as a mandatory staging test area during PHP 8.4 rollout

### 4. Bundled vendor libraries should be treated as compatibility watchpoints

Relevant vendor areas observed:
- `public/assets/vendor/PHPMailer`
- `public/assets/vendor/tcpdf`

Assessment:
- no immediate app-owned incompatibility was identified from these bundles during this audit
- however, PDF generation, SMTP transport, and lower-level extension interactions should still be validated on PHP 8.4

Impact:
- do not assume vendor-heavy flows are safe purely because syntax is valid
- prioritize smoke tests for mail, PDF, and remote integrations

### 5. cURL and integration-heavy paths remain operational-risk items, not migration-red flags

Relevant app-owned areas:
- `public/sso_sp_client.php`
- `public/setting/helper/distance_helper.php`
- `public/diag-oneid.php`

Assessment:
- these use cURL in normal ways
- no PHP 8.4-specific blocker was identified from the patterns found
- SSO and API integrations still need end-to-end verification after runtime change

Impact:
- low code-level migration risk
- medium operational verification priority

## Areas Reviewed

The audit specifically reviewed or sampled:
- auth entry and enforcement flows
- forgot/reset/change password flows
- mailer implementation
- database connection layer
- audit logger
- selected integration paths using cURL
- app-owned usage of patterns commonly affected by migration changes

## Recommended Upgrade Path

### Phase A: Keep production on PHP 8.3.30 for now

Reason:
- current production and Docker are aligned
- recent stabilization work still needs normal regression confidence

### Phase B: Prepare a staging target on PHP 8.4

Recommended runtime target:
- Docker test image: `php:8.4-apache` or a pinned `8.4.x-apache` tag once selected

### Phase C: Run focused smoke tests on PHP 8.4

Minimum staging checklist:
- login/logout
- SSO login flow
- forgot password for manual account
- forgot password for restricted/SSO-managed account
- reset password with valid token
- forced password change flow
- email template test send
- dashboard load
- manual document view/download
- any PDF generation path in normal business use

### Phase D: Review logs under PHP 8.4

Check for:
- fatal errors
- `TypeError`
- `ValueError`
- `E_DEPRECATED`
- mail transport failures
- integration regressions around SSO or external APIs

## Recommended Code/Platform Actions

1. Do not upgrade production server PHP immediately.
2. Keep current production on PHP 8.3.30 while staging PHP 8.4.
3. Upgrade staging Docker/runtime to PHP 8.4 first.
4. Run the smoke checklist above before touching production.
5. Only after staging is clean, plan the production PHP 8.4 cutover.

## Final Assessment

This repository appears ready enough to begin a controlled staging migration from PHP 8.3.30 to PHP 8.4.

There is no clear evidence from app-owned code that PHP 8.4 should be blocked at this time.

The main remaining risks are operational:
- SMTP/mail transport behavior
- SSO/integration verification
- vendor-backed flows such as PDF generation

That means the next correct move is not more broad code refactoring. The next correct move is a PHP 8.4 staging pass with focused smoke testing.
