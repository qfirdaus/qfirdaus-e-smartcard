# e-SmartCard

e-SmartCard ialah projek downstream berasaskan IQS-Framework untuk pembangunan sistem kad pintar UPNM.

README ini hanya mendokumenkan ciri yang wujud dalam kod semasa projek ini.

## Version

- Current version: `1.11.0`
- Release history: [CHANGELOG.md](./CHANGELOG.md)
- Version file: [VERSION](./VERSION)
- Runtime fallback: [public/configuration/settings.php](./public/configuration/settings.php)

## Runtime Baseline

- PHP web runtime: `8.4.26`
- Shared-code compatibility baseline: PHP `8.3.x` sepanjang downstream masih dalam tempoh mixed-runtime
- Host environment: WSL 2 with Ubuntu 24.04
- Web server: Nginx with PHP-FPM
- Runtime model: native WSL; Docker, Docker Compose, and container-specific Apache assets are no longer maintained in this repository
- Main database: MySQL `8.x`
- External database support: Sybase through ODBC/DBLIB, plus additional PDO connections configured from the system UI

## Version 1.11.0 System Information Diagnostics

- Super Admin users can inspect runtime, PHP settings, sanitised configuration, folder permissions, and safe PHP diagnostics from the System Information workspace.
- PHP 8.4 performance settings are assessed against the approved production profile with clear optimized, acceptable, review, and action-required states.
- Folder diagnostics compare current and expected permissions, ownership, protection status, and operational reasons for framework runtime paths.
- Sensitive credentials, environment secrets, cookies, session identifiers, and raw `phpinfo()` output are excluded from the diagnostic views.

## Version 1.10.1 PHP 8.4 Compatibility

- IQS Framework staging runs on PHP `8.4.26`, while shared core changes remain compatible with downstream projects on PHP `8.3.x`.
- Runtime error reporting no longer references the deprecated `E_STRICT` constant separately.
- CSV parsing and export calls use explicit escape parameters to avoid PHP 8.4 deprecation notices.
- Dual-runtime regression coverage protects the PHP 8.3 and PHP 8.4 compatibility baseline.

## Version 1.10.0 Custom Theme Palette

- System administrators and individual users can select custom topbar and sidebar seed colours with live preview and persistent preferences.
- Custom colours generate coordinated gradients, foregrounds, hover states, borders, shadows, and light/dark logo treatment instead of rendering as flat colours.
- Colour input is restricted to normalized hexadecimal values, and automatic contrast selection keeps navigation text readable.
- Login, forgot-password, reset-password, and change-password pages now share one public palette resolver and inherit the system custom sidebar theme.
- Bright public-page themes are adjusted to maintain WCAG AA contrast for white button text, while legacy and invalid values fall back safely.

## Version 1.9.9 Public User Login and Profile Fixes

- Authenticated public users can open protected pages without a staff ID, preventing the login/dashboard redirect loop.
- Account profiles and language preference updates use the session Login ID, with a staff ID fallback for legacy sessions.
- Language updates continue to validate CSRF tokens and active languages; unauthenticated requests remain blocked.

## Version 1.9.8 OneID SSO Identity Indicators

- User Management now displays the OneID SSO indicator for both automatically provisioned accounts and existing Staff or Student accounts verified through OneID.
- Indicator tooltips distinguish accounts created by SSO auto provisioning from existing accounts subsequently linked to OneID authentication.
- The idempotent SSO metadata migration adds `f_isAutoProvisioned` and `f_identitySource`, and safely backfills accounts carrying explicit `SSO-AUTO` evidence.
- Future sync packages include the SSO metadata migration so downstream projects receive the required schema artifact together with the UI change.

## Version 1.9.7 Staff and Student SSO Provisioning Reliability

- Staff and Student auto provisioning now prioritizes the canonical identity selected from the OneID category, preventing Student packets that also contain `data3` from being routed through Staff provisioning.
- Staff IDs and Student matric identifiers are normalized consistently across OneID, local users, and external Sybase lookups; surrounding whitespace and Student identifier case differences no longer cause false record-not-found results.
- Student `AKTIF` and Staff status lookups are more resilient to non-canonical source formatting, while disabled Student mode and unavailable provisioning sources are reported through the correct controlled failure path.
- Active existing accounts can reconcile missing verification and SSO identity-source metadata after successful OneID authentication without changing their category or assigned group.
- Disabled accounts remain blocked, and category conflicts between OneID and the local account are rejected and audited instead of being silently repaired or reprovisioned.
- Dependency-free OneID regression coverage now verifies 31 Staff, Student, source lookup, identity resolution, and security cases.

## Version 1.9.6 OneID SSO Security and Reliability

- OneID callbacks now prioritize a newly returned token over stale browser credentials and convert invalid-token, invalid-site, malformed-response, unavailable-service, and invalid-identity outcomes into controlled local login feedback.
- OneID API requests now enforce certificate and hostname verification with explicit connection and total timeouts.
- The OneID browser cookie now contains only the opaque credential, uses a shorter lifetime, and applies `HttpOnly`, `SameSite=Lax`, and HTTPS-aware `Secure` protection.
- SSO handoffs carry an expiry, one-time nonce, correlation ID, and explicit identity state; OneID staff, student, and hybrid identities are resolved from the canonical `u_category`, with a staff-first fallback for legacy packets that do not provide a category.
- OneID events are logged with a correlation ID without recording raw tokens or the complete identity packet.
- Dependency-free regression coverage verifies response classification, staff/student identifier handling, auto-reissue, timeout and TLS controls, callback precedence, and handoff requirements.

## Version 1.9.5 Settings Synchronization and Account Recovery

- System Settings AJAX saves now invalidate request-local configuration caches before returning authoritative values, preventing toggles and selectors from visually reverting until refresh.
- General, Login Policy, Email, Theme, Language, Database, and AI Chatbot settings now keep their form state, runtime summaries, Select2 controls, and saved/dirty indicators synchronized with normalized server responses.
- Sensitive SMTP passwords and AI provider API keys are excluded from settings-save responses.
- Forgot Password now accepts Login ID, registered email, staff ID, or employee number, rejects ambiguous shared identifiers, and applies one centralized manual-login eligibility policy during both request and token consumption.
- Manual Super Admin accounts retain controlled recovery during maintenance or a disabled category, while SSO-managed, disabled, deleted, unknown-category, and email-less accounts remain ineligible for local password reset.
- Password reset requests now use layered session/IP/identifier throttling, avoid exposing identifiers in redirect URLs, retain the previous link until replacement email delivery succeeds, and complete password changes and token consumption transactionally.
- Regression coverage verifies settings save synchronization, secret redaction, account-recovery policy combinations, and the absence of schema-changing SQL in this release.

## Version 1.9.4 Administration Reliability and Performance

- Theme configuration loaded during login now repairs incomplete browser session settings before `config.js` applies the layout.
- User Management group loading now supports optional and legacy group schemas, avoids repeated metadata queries, and safely quotes SQL aliases across supported MySQL versions.
- Notification Admin and Notification Templates now perform fewer database queries during the initial server-side DataTable load.
- User Management asset versioning and Additional Database preview callbacks no longer emit the related PHP or Firefox console warnings.

## Version 1.9.3 Notification and Administration UI Refinement

- The personal Notifications page now uses high-contrast segmented filters with icons and inline counts, clearer unread emphasis, a professional responsive toolbar, and consistent IQS Framework theme variables.
- Notification Admin publish confirmation now remains above its setup modal.
- Notification Admin, Notification Templates, System Cache, Access Matrix, Developer Guide, and Manual Management now prioritize their main workflows without separate KPI card rows.
- Developer Guide search now spans the full available content width.

## Actual System Features

### Authentication and Session

- Login and logout flow through `LoginController.php` and `LogoutController.php`.
- Session initialization, timeout handling, CSRF helpers, language loading, audit hooks, and runtime bootstrap through `public/includes/init.php`.
- Session cookie and strict-mode settings are applied only before session startup, allowing password renewal and reset pages to reuse the core bootstrap without active-session configuration warnings.
- Role switching for users with more than one available group context through `role-switch.php` and `role-switch-roles.php`.
- Profile workspace with login activity, audit history, active session visibility, and session termination support.
- Login policy configuration from System Settings, including manual login route control and SSO compatibility settings.
- User Management identifies both accounts created automatically through SSO and existing Staff or Student accounts subsequently verified through OneID; the metadata columns are supplied by `docs/user-sso-metadata-migration-2026-09-03.sql`.
- OneID SSO validates callback tokens through the configured identity provider, uses a five-minute one-time application handoff, and reports provider, token, site, response, identity, provisioning, policy, and lockout failures through controlled login feedback.
- OneID transport enforces TLS verification and bounded request timeouts, while browser state is restricted to a short-lived protected opaque credential and application audit records exclude raw tokens.
- Forgot Password supports Login ID, registered email, staff ID, and employee-number lookup for eligible manual accounts, with duplicate-identifier protection and generic public responses.
- Password recovery reuses the active login policy: SSO-managed accounts remain with their identity provider, while manual Super Admin recovery remains available during maintenance and category shutdowns subject to active-account and registered-email checks.
- Reset links are hashed, expiring, single-use, rate limited, preserved until replacement email delivery succeeds, and consumed transactionally with the local password update.

### Dashboard

- Main authenticated landing page at `public/pages/dashboard.php`.
- Displays user context, active role context, and dashboard data prepared by `DashboardController.php`.
- Supports system resource polling through `public/ajax/system-resources.php` where allowed.

### User Management

- User directory page at `public/pages/senarai-pengguna.php`.
- Supports staff, student, and public user management flows.
- Supports Super Admin `View As` impersonation from the user directory for controlled support workflows, with view-only and support-action modes.
- AJAX operations exist for listing rows, adding staff users, adding student users, adding public users, editing users, deleting users, toggling status, resolving staff/student options, and managing extra roles.
- Staff and student add-access modals provide remote Select2 lookup with type-specific search text and consistent professional tabs.
- User logic is handled through `UserListController.php`, `User.php`, and related AJAX endpoints under `public/ajax/user-*.php`.

### Group, Module, Menu, and Sidebar Governance

- Group management page at `public/pages/kumpulan-pengguna.php`.
- Supports group CRUD, group styling, module access, menu access, module/menu ordering, and sidebar refresh without full page reload.
- Uses standardized SweetAlert messaging, faster modal-open sequencing, and earlier success feedback for key group, module, and menu transactions.
- Group, module, menu, subgroup, ordering, and access modals share the same compact tab and modal presentation standard.
- Supports optional sidebar menu subgroups inside parent modules through `tbl_m_menu_subgroup` and `tbl_m_menu.f_subgroupID`.
- Menu subgroups can be created, edited, ordered, assigned to menus, and protected from deletion while menus are still assigned.
- Sidebar rendering remains backward-compatible: modules can still use direct menus without subgroups, while selected modules can group menus under subgroup headings.
- Sidebar users can search accessible menus through a role-aware AJAX search below the active user profile, with module/subgroup context, keyboard navigation, request debouncing, and condensed-sidebar support.
- Sidebar fragment updates are served by `public/ajax/sidebar-fragment.php`.
- Related AJAX endpoints include `group-*`, `module-*`, `menu-*`, `menu-subgroup-*`, `menu-order-item-swap.php`, `modul-list.php`, and role-switch endpoints.
- Access governance logic is supported by `GroupController.php`, `SidebarController.php`, `Group.php`, and `Modul.php`.

### Access Matrix

- Read-only access matrix page at `public/pages/access-matrix.php`.
- Provides visibility of group, module, and menu access configuration.
- Backed by `AccessController.php`.
- Presents searchable access coverage with hierarchy context, filters, local states, and responsive table behavior.

### Audit Center

- Audit workspace at `public/pages/audit-center.php`.
- Supports audit panels for events, requests, sessions, changes, security views, metadata, and export actions.
- Core mutation flows for forms, module creation, notification templates, student sync, manual management, and system template generation record audit events with field-level change details where applicable.
- AJAX endpoints include `audit-center-action.php`, `audit-center-export.php`, `audit-center-meta.php`, and `audit-center-panel.php`.
- Audit services are supported by `AuditCenterController.php`, `AuditLogger.php`, and audit helper functions.
- Tab changes refresh their own panel with a local spinner and stale-request protection instead of invoking the global page loader.

### Notification Framework

- In-app notification list page exists at `public/pages/notifications.php`.
- Notification administration page exists at `public/pages/notification-admin.php`.
- Notification template management page exists at `public/pages/notification-templates.php`.
- Topbar notification dropdown shows unread count, recent notification preview, read actions, and View All navigation.
- Supports admin announcements, direct user notifications, role/group/audience notifications, event-based notifications, and workflow task notifications.
- Notification publishing and workflow logic is handled by `NotificationPublisher.php`, `NotificationService.php`, `NotificationWorkflowService.php`, `NotificationAudienceResolver.php`, `NotificationAdminService.php`, and `NotificationTemplateService.php`.
- AJAX endpoints include `notification-list.php`, `notification-read.php`, `notification-read-all.php`, `notification-action.php`, `notification-admin-list.php`, `notification-admin-publish.php`, `notification-template-list.php`, and `notification-template-action.php`.
- Notification list, publisher, and template workspaces use clear filter controls, tables, local loaders, professional modal tabs, preview states, and multilingual feedback without separate KPI card rows.
- Developer guidance is documented in `docs/notification-developer-standard-2026-05-04.md` and `docs/notification-developer-examples-2026-05-03.md`.

### Profile

- Profile page at `public/pages/profile.php`.
- Shows account profile, login activity, audit event history, audit metadata where authorized, and active session actions.
- Profile identity labels adapt to the authenticated user category, including staff number/position/department for staff and matric number/programme/faculty for students.
- Profile tabs and audit-trail details use a consistent professional layout, local loading feedback, and translated SweetAlert messages.
- AJAX endpoints include `profile-login-activity.php`, `profile-audit-events.php`, `profile-audit-event-meta.php`, and `profile-kill-session.php`.

### System Settings

- System settings page at `public/pages/tetapan-sistem.php`.
- Main tabs currently available:
  - General
  - Login Policy
  - Email
  - Database
  - Theme
  - Language
  - AI Chatbot
- Settings are handled by `TetapanSistemController.php`, `Config.php`, `SystemConfigConstants.php`, and page-specific JavaScript/CSS assets.
- General > Limits includes the `View As Timeout (Minutes)` setting for the Super Admin impersonation workflow.
- Settings sections use standardized navigation, cards, forms, validation feedback, and theme-aware responsive presentation.
- AJAX save responses synchronize normalized form values and runtime summaries immediately without requiring a page refresh.
- Request-local configuration caches are invalidated after saves, and SMTP passwords or AI provider API keys are not returned to the browser in save responses.

### AI Chatbot Core

- Core AI Chatbot page exists at `public/pages/ai-chatbot.php`.
- AI Chatbot Knowledge Manager exists at `public/pages/ai-chatbot-knowledge.php`.
- AI Chatbot Review Dashboard exists at `public/pages/ai-chatbot-review.php`.
- Floating AI Chatbot widget is included through `public/includes/ai-chatbot-widget.php` and uses `public/assets/js/ai-chatbot-widget.js` plus `public/assets/css/ai-chatbot-widget.css`.
- Chat requests are handled by `public/ajax/ai-chatbot-message.php`, with widget event tracking through `public/ajax/ai-chatbot-event.php`.
- Provider integration is handled through `AiChatbotService.php`, `AiChatbotProviderRegistry.php`, and provider classes under `public/classes/AiChatbotProviders/`.
- Runtime settings are managed from System Settings > AI Chatbot and stored in `tbl_m_config` under the `ai_chatbot` group.
- The AI Chatbot settings UI is split into Overview, Provider, Limits, Character, and Storage subtabs.
- Usage/session/message persistence is supported through `tbl_ai_chat_session`, `tbl_ai_chat_message`, and `tbl_ai_chat_usage` when the table script in `docs/ai-chatbot-tables-2026-06-11.sql` has been applied.
- Role-aware answers are guided by safe runtime context, active group context, visible module/menu context, permission-filtered retrieval policy, and governance classification metadata.
- Optional curated FAQ/SOP/manual knowledge retrieval is supported through `tbl_ai_chat_knowledge` when `docs/ai-chatbot-knowledge-tables-2026-06-12.sql` has been applied.
- Manual knowledge can be maintained through the Knowledge Manager with language, visibility, allowed groups, tags, source/version, review dates, status controls, AJAX save/edit/status/delete, SweetAlert feedback, and local loading states.
- PDF-only knowledge source upload is supported when `docs/ai-chatbot-knowledge-pdf-schema-2026-06-13.sql` has been applied; uploaded text PDFs are extracted into draft chunks before activation.
- Knowledge retrieval uses hybrid keyword-ranked matching across active manual items and active processed PDF chunks, while filtering by language, visibility, allowed group, and super-admin scope before content is sent to any AI provider.
- The Review Dashboard uses `tbl_ai_chat_usage` metadata to highlight review queues, no-knowledge candidates, provider failures, outcome/category volume, and provider latency without requiring raw message content.
- The widget presents answer text only; navigation/action suggestion links are not rendered in chatbot responses.
- The chatbot does not execute model-generated SQL, does not expose unrestricted database records, and must ground system-specific answers in approved runtime, visible system, or curated knowledge context.
- Implementation guidance is documented in `docs/ai-chatbot-core-blueprint-2026-06-11.md`, `docs/ai-chatbot-production-runbook-2026-06-11.md`, `docs/ai-chatbot-implementation-readiness-2026-06-13.md`, and `docs/db-inspection-guideline-2026-06-13.md`.
- External provider/API failures are classified through the framework external-service failure pattern documented in `docs/external-service-failure-handling-2026-06-23.md`.

### System Cache Maintenance

- Admin-only system cache maintenance page exists at `public/pages/system-cache.php`.
- Discovers standard project cache locations dynamically from `app/cache`, `public/cache`, and `storage/cache` when those folders exist.
- Displays discovered cache locations with file count, size, and per-location last modified date in the maintenance table.
- Supports clearing selected cache locations or all discovered cache locations while preserving directory structure, `.gitkeep`, `.htaccess`, active sessions, and login tokens.
- Cache clearing is handled through `public/ajax/system-cache-action.php`, uses CSRF validation and admin permission enforcement, resets OPcache/APCu where available, logs the operation through the central audit mechanism, and updates table state in place with a local operation loader.

### Language Architecture

- Core framework translations are stored in `public/lang/core/`.
- Project-specific translations are stored in `public/lang/custom/`.
- Root language files in `public/lang/ms.php` and `public/lang/en.php` are compatibility wrappers that return merged core and custom translations.
- Project custom translations override core translations without requiring changes to existing `__()` calls.
- JavaScript translation bundles and generated page language entries use the same merged language source.
- Generated project language keys are written to `public/lang/custom/` so core language updates do not overwrite project-specific translations.
- Framework/core project translation keys must be maintained in `public/lang/core/`.

### Database Runtime Configuration

The Database tab in System Settings supports:

- Main MySQL environment selection between production and development.
- Dedicated MySQL environment variables using `DB_MYSQL_MAIN_PROD_*` and `DB_MYSQL_MAIN_DEV_*`.
- Legacy MySQL fallback variables using `DB_MYSQL_*`.
- Sybase environment selection between production and development.
- Sybase operational mode selection for staff-only or staff-and-student usage.
- Runtime summary and diagnostics that update through AJAX after saving.
- Additional database connection registry stored in `tbl_m_db_connection` and `tbl_m_db_connection_env`.
- Additional connection actions for create, update, enable/disable, test connection, inspect, schema preview, object preview, and sample code.
- Platform-aware additional database variants identified by `(connection, environment, os_family, driver)` so Linux and Windows settings can coexist in the same shared registry.
- Deterministic resolution that selects the exact runtime OS before an `any` variant and rejects fallback across another OS or environment.
- Windows presets for ODBC/SQLSRV and a Linux DBLIB preset, with runtime health reported separately from cross-platform coverage.
- Exact-tuple test-result updates and optimistic concurrency protection so testing or editing one platform does not overwrite another platform's variant.
- Dual-read credential support for legacy ciphertext and authenticated v2 envelopes. New v2 writes require `DB_ADDITIONAL_CREDENTIAL_KEY`; `DB_ADDITIONAL_CREDENTIAL_KEY_ID` identifies the active key without exposing it.
- Read-only diagnostics and controlled dry-run-default audit/migration tools with sensitive values redacted from output and logs.

Programmers should consume additional database connections through:

```php
$pdo = Database::pdoAdditional('dbx_code');
```

Do not hardcode DSN, username, or password inside page/controller code.

For a shared development registry, store separate platform variants instead of repeatedly changing one shared driver row. Typical MSSQL/Sybase mappings are Linux/DBLIB and Windows/ODBC or Windows/SQLSRV. Install the same v2 credential key securely on every runtime that shares encrypted registry rows; never commit the key to Git.

Operational references:

- [Additional Database upgrade audit and rollout record](./docs/additional-database-upgrade-audit-plan-2026-08-01.md)
- Use the Database tab diagnostics panel for read-only runtime health and platform coverage.
- `php tools/additional-database-platform-audit.php` for read-only platform coverage auditing.
- `php tools/additional-database-platform-migrate.php` for controlled platform migration; dry-run is the default.
- `php tools/additional-database-credential-migrate.php` for controlled credential migration; dry-run is the default.

### Email Configuration and Templates

- Email runtime settings are managed in `tetapan-sistem.php`.
- Email test delivery is handled through `public/ajax/uji-emel.php`.
- Email template management page exists at `public/pages/template-emel.php`.
- Template operations are handled by `EmailTemplateController.php`, `Mailer.php`, `EmailTemplate*.php`, and AJAX endpoints under `public/ajax/email-*` and `public/ajax/email-template-*`.
- Supported UI operations include listing, preview/testing, creating, updating, duplicating, archiving/restoring, deleting, and seeding templates where available.
- The template workspace includes standardized KPI and panel spacing, professional modal tabs, unsaved-change protection, recipient/subject confirmation before test delivery, cancellable preview requests, sandboxed previews, and external-resource guidance.
- SMTP delivery failures are classified through the framework external-service failure pattern documented in `docs/external-service-failure-handling-2026-06-23.md`.

### Template Generator

- Template generator page exists at `public/pages/template-generator.php`.
- Supported by `TemplateGeneratorController.php`, `SystemTemplateController.php`, and classes under `public/classes/SystemTemplate*.php`.
- Used to generate or manage system page/template scaffolding from controlled template definitions.
- Generated page, controller, and CSS files are marked as `PROJECT GENERATED FILE` so downstream programmers can distinguish customizable generated artifacts from protected core files.
- Template generation records audit events and displays a governance checklist for language keys, access registration, and audit hooks before production use.
- Generation results, template inventory, review actions, and destructive confirmations use local loading states and hardened output/path validation.

### Core File Protection

- Active framework pages, controllers, AJAX endpoints, classes/services, bootstrap/includes, setting helpers/constants, configuration, root entry files, and core language files include an `IQS FRAMEWORK CORE FILE` marker to identify read-only protected core files for downstream project programmers.
- `public/lang/custom/*` remains the supported language customization area and is not marked as protected core.
- Protection guidance is documented in `docs/core-file-protection-standard-2026-06-04.md`.
- Protected page inventory and framework-managed areas are listed in `docs/core-protected-files.md`.
- Core marker validation can be run with `php tools/core-file-protection-audit.php`.

### Developer Guide

- Developer guide page exists at `public/pages/developer-guide.php`.
- Provides centralized, copyable sample code for core-safe module development, including page skeletons, AJAX/CSRF, database access, notifications, language keys, menu/access, audit, and email guidance.
- Intended for programmers to consume framework APIs without modifying protected core files.
- Uses categorized navigation, a full-width search workspace, copy feedback, consistent code panels, and responsive multilingual presentation.

### Manual Management

- Manual management page exists at `public/pages/manage-manuals.php`.
- Supports upload, editing, visibility/group synchronization, viewing, status control, and deletion of user manuals by group.
- Related AJAX endpoints include `manual-*`, `manual-delete.php`, and `migrate-manuals.php`.
- Supported by `ManualController.php`.

### FAQ

- FAQ page exists at `public/pages/soalan-lazim.php`.
- Provides static/help content for system users.

### Language, Theme, and UI Assets

- Language settings are handled in System Settings.
- Theme settings are handled in System Settings.
- User personal theme preference is applied before the global fallback theme where available to avoid visible theme switching during navigation.
- Sidebar branding supports the configured sidebar image under the main logo.
- Frontend assets include Bootstrap-style components, DataTables usage, SweetAlert workflows, Remix Icon icons, and page-specific JavaScript/CSS files.
- Application modals are standardized to top-aligned Bootstrap dialogs unless a future page-specific exception is explicitly documented.
- Global full-page loader is now reserved for sidebar navigation transitions, while in-page transactions rely on local loading states and silent background refreshes.
- Tailwind/PostCSS tooling exists in `package.json` for frontend build support, although the main application is PHP-rendered.

### Update Distribution Tooling

- `sync-updates.sh` distributes collected updates to the registered downstream project list, including `e-prestasi` and `upnm30`.
- `sync-updates.sh` and `update-files.sh` support `.sync-update-ignore` so selected files can be excluded from `updates/` and project sync flows.
- `sync-updates.sh` reports results directly in the terminal and no longer creates the unused `sync.log` or `conflict.log` files.
- Its dry-run mode classifies simulated updates, new files, and conflicts in the per-project summary without creating directories, backups, files, or permission changes.
- Collection now produces a release-bound SHA-256 manifest containing base and update hashes; a real sync is refused unless that exact manifest, project scope, and force mode have passed dry-run.
- Preflight blocks unsafe paths, symlink targets, duplicate records, missing sources, modified packages, insufficient disk space, conflicts, and write errors before applying changes.
- Real sync uses atomic temporary-file replacement, complete affected-file backups when requested, per-project rollback data, post-copy hash verification, stale-lock recovery, and explicit `APPLY` confirmation.
- Core file protection docs and `tools/core-file-protection-audit.php` are included in framework update collection.
- `public/lang/custom/*` remains protected from overwrite during update distribution.

## Current Page Inventory

The active page files under `public/pages` are:

- `access-matrix.php`
- `ai-chatbot.php`
- `ai-chatbot-knowledge.php`
- `ai-chatbot-review.php`
- `audit-center.php`
- `dashboard.php`
- `developer-guide.php`
- `kumpulan-pengguna.php`
- `manage-manuals.php`
- `notification-admin.php`
- `notification-templates.php`
- `notifications.php`
- `profile.php`
- `senarai-pengguna.php`
- `soalan-lazim.php`
- `system-cache.php`
- `template-emel.php`
- `template-generator.php`
- `tetapan-sistem.php`

Any module not listed above should not be treated as an active page unless it is added back into `public/pages`.

## Main Controllers

Current controller files under `public/controllers` include:

- `AccessController.php`
- `AuditCenterController.php`
- `DashboardController.php`
- `EmailTemplateController.php`
- `GroupController.php`
- `LoginController.php`
- `LogoutController.php`
- `ManualController.php`
- `ProfileController.php`
- `SidebarController.php`
- `SystemTemplateController.php`
- `SystemCacheMaintenanceController.php`
- `TemplateGeneratorController.php`
- `TetapanSistemController.php`
- `UserListController.php`

Other controller files may exist for legacy or supporting flows, but the list above represents the active system surfaces documented in this README.

## Database Architecture

IQS-Framework uses three database access patterns:

1. Main MySQL application database

   Used for users, groups, modules, menus, system settings, email templates, audit records, manual metadata, and application runtime data.

2. Sybase domain connections

   Used for staff/student source data integration through configured production/development Sybase targets.

3. Additional database connections

   Managed from System Settings > Database > Additional Connections. These are intended for programmer use in new pages, controllers, repositories, services, AJAX endpoints, DataTables feeds, dropdown lookups, insert/update flows, and batch sync processes.

## Directory Structure

```text
e-smartcard-uat/
|-- public/
|   |-- ajax/              # AJAX endpoints
|   |-- assets/            # CSS, JS, images, vendor assets
|   |-- classes/           # Core classes, models, services
|   |-- configuration/     # Runtime configuration files
|   |-- controllers/       # Page controllers
|   |-- includes/          # Bootstrap/init/shared includes
|   |-- pages/             # Authenticated application pages
|   |-- setting/           # Helpers, constants, language/config support
|-- docs/                  # Project documentation assets
|-- tools/                 # CLI maintenance tools
|-- updates/               # Update/deployment support files
|-- .env.example           # Example runtime environment
|-- VERSION                # Application version
|-- CHANGELOG.md           # Release notes
|-- README.md              # This file
```

## Setup

The repository now runs directly in WSL with Nginx and PHP-FPM. The retired Docker image, Compose service, Apache container configuration, and development TLS assets have been removed and are not part of the supported setup.

1. Prepare environment file.

   Copy `.env.example` to `.env` and configure the MySQL and Sybase values required by your environment.

2. Install the required PHP runtime and extensions in WSL.

   The application baseline is PHP `8.3.30` with PHP-FPM. Required extensions are `pdo`, `pdo_mysql`, `mysqli`, `pdo_odbc`, `pdo_dblib`, `gd`, `zip`, and `opcache`. Enable any additional PDO driver required by connections configured through the system UI.

3. Configure the Nginx virtual host.

   Set the document root to `/var/www/app/e-smartcard-uat/public`, route PHP requests to the installed PHP-FPM socket, disable directory listing, and deny access to hidden or sensitive files. Keep the Nginx configuration outside this repository under normal system administration controls.

4. Confirm filesystem permissions.

   The Nginx/PHP-FPM runtime user needs read access to the application and write access only to runtime directories that require it, such as `public/cache`, `public/log`, and approved upload paths.

5. Confirm PHP-FPM and Nginx are running, then validate the runtime.

   ```bash
   php -v
   php -m
   ```

   Confirm the application can read `.env` from the project root and `VERSION` from the same repository. The application resolves `VERSION` directly from the project root when `APP_VERSION_FILE` is not set.

6. Confirm database setup.

   Ensure the required application tables exist before login and administration testing. For additional database connections, ensure `tbl_m_db_connection` and `tbl_m_db_connection_env` exist in every deployment environment.

## Developer Guidelines

- Use controllers for page-specific orchestration.
- Use classes/services/repositories for reusable database and business logic.
- Use AJAX endpoints for background save/list/test operations.
- Use `Database::getInstance('mysql')->getConnection()` for the main application database when following existing patterns.
- Use `Database::pdoSybaseStaff()` or `Database::pdoSybaseStudent()` for configured Sybase domains.
- Use `Database::pdoAdditional('dbx_code')` for additional database connections created from System Settings.
- Use `NotificationPublisher`, `NotificationWorkflowService`, or `NotificationTemplateService` for new notification flows instead of inserting notification rows directly from page code.
- Follow `docs/notification-developer-standard-2026-05-04.md` when adding notifications to new modules so admin, event, and workflow notifications remain consistent across projects.
- Configure sidebar modules, menus, menu access, and optional menu subgroups through `kumpulan-pengguna.php`; project programmers should not hardcode sidebar structure inside `public/includes/sidebar.php`.
- Refer to `docs/sidebar-menu-subgroup-blueprint-2026-05-06.md` before enabling subgroup schema or adding grouped sidebar menus in a project deployment.
- Treat files marked `IQS FRAMEWORK CORE FILE` as read-only in downstream project clones. Use generated/custom project files for project behavior changes.
- Run `php tools/core-file-protection-audit.php --strict` before framework release or update collection.
- Do not hardcode database DSN, username, or password in pages/controllers.
- Keep access checks aligned with group, module, and menu governance.
- Record sensitive administrative changes through the audit helper/logger pattern already used in the system.
- Use `php tools/language-split-tool.php validate` after language or core sync changes to verify core/custom translation health.

## Security Notes

- Serve only the `public` directory through the web server.
- Keep `.env` outside public web access.
- Protect write actions with CSRF validation.
- Validate role/group/menu access before exposing administrative actions.
- Use the `View As` workflow only for support/admin investigation, keep view-only mode as the default, and verify actor/target metadata in audit records after support sessions.
- Avoid logging secrets, passwords, DSNs with credentials, or raw sensitive payloads.
- Review audit logs after changing user, group, module, menu, database, or system configuration behavior.

## Removed or Undocumented Features

This README intentionally excludes features that are not active page/module surfaces in the current IQS-Framework codebase. In particular, removed prototype pages, old project names, and previous domain-specific modules are not documented here unless they are reintroduced into the active application structure.

## Maintainer

- Name: Ts. Norfirdaus Harun
- Email: norfirdaus@upnm.edu.my
