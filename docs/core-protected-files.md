# Core Protected Files Registry

Date: 2026-06-04

This registry lists framework files that should be treated as protected core artifacts in downstream projects.

Programmers may read these files for reference. Direct edits in downstream project clones are not recommended unless approved and documented.

## Active Protected Pages

- `public/pages/access-matrix.php`
- `public/pages/audit-center.php`
- `public/pages/dashboard.php`
- `public/pages/developer-guide.php`
- `public/pages/kumpulan-pengguna.php`
- `public/pages/manage-manuals.php`
- `public/pages/notification-admin.php`
- `public/pages/notification-templates.php`
- `public/pages/notifications.php`
- `public/pages/profile.php`
- `public/pages/senarai-pengguna.php`
- `public/pages/soalan-lazim.php`
- `public/pages/system-cache.php`
- `public/pages/template-emel.php`
- `public/pages/template-generator.php`
- `public/pages/tetapan-sistem.php`

## Active Protected Controllers

- `public/controllers/AccessController.php`
- `public/controllers/AuditCenterController.php`
- `public/controllers/DashboardController.php`
- `public/controllers/EmailTemplateController.php`
- `public/controllers/GroupController.php`
- `public/controllers/LoginController.php`
- `public/controllers/LogoutController.php`
- `public/controllers/ManualController.php`
- `public/controllers/ProfileController.php`
- `public/controllers/SidebarController.php`
- `public/controllers/SystemTemplateController.php`
- `public/controllers/SystemCacheMaintenanceController.php`
- `public/controllers/TemplateGeneratorController.php`
- `public/controllers/TetapanSistemController.php`
- `public/controllers/UserListController.php`

## Protected AJAX Endpoints

- `public/ajax/*.php`

All existing framework AJAX endpoints and shared AJAX helpers are treated as protected core files. Generated/custom project AJAX endpoints should use the `PROJECT GENERATED FILE` header instead.

## Protected Classes And Services

- `public/classes/*.php`

All current framework classes, models, services, runtime configuration helpers, notification services, email template services, database connection services, and template generator services are treated as protected core files. Generated/custom project classes should use the `PROJECT GENERATED FILE` header instead.

## Protected Bootstrap, Runtime, Language, And Root Entry Files

- `public/includes/*.php`
- `public/setting/**/*.php`
- `public/configuration/*.php`
- `public/*.php`
- `public/lang/*.php`
- `public/lang/core/*.php`

`public/lang/custom/*` remains the supported language customization area and is not part of the protected core marker scan.

## Protected Framework Areas

These areas are considered framework-managed unless a generated/custom module explicitly owns the file:

- `public/includes/`
- `public/controllers/`
- `public/classes/`
- `public/ajax/`
- `public/lang/core/`
- `public/configuration/`
- `tools/`
- `updates/`

## Customizable Areas

These areas are intended for downstream project customization where applicable:

- `public/lang/custom/`
- generated project pages
- generated project controllers
- generated project classes
- page-specific generated/custom assets
- project-specific documentation

## Notes

- `public/lang/custom/*` is already protected from overwrite during update distribution.
- Generated project files should use the `PROJECT GENERATED FILE` header described in `docs/core-file-protection-standard-2026-06-04.md`.
- Page/template generator output is marked as `PROJECT GENERATED FILE` so generated project artifacts are not confused with protected core files.
- Run `php tools/core-file-protection-audit.php` to check active page markers before release or update collection.
