# Changelog

All notable changes to this project will be documented in this file.

This changelog follows a release-style summary based on major project milestones and significant git history, using release dates without time stamps.

## [Unreleased]

## [1.11.0] - 2026-10-08

### Added
- Added a Super Admin System Information workspace with runtime, PHP settings, sanitised configuration, folder-permission, and safe PHP diagnostic views.
- Added environment-aware PHP 8.4 performance assessments with explicit optimized, acceptable, review, and action-required states.
- Added current-versus-expected folder permissions, ownership, protection status, and operational reasons for framework runtime paths.

### Changed
- Changed project release metadata to version `1.11.0`.
- Changed PHP setting recommendations to use the approved performance profile, including a 600-second realpath cache TTL and 30-second OPcache revalidation frequency.
- Changed diagnostic tables to preserve values on one line with responsive horizontal scrolling.

### Security
- Prevented System Information diagnostics from exposing credentials, environment secrets, cookies, session identifiers, and raw `phpinfo()` output.
- Restricted System Information collection and display to authenticated Super Admin users.

## [1.10.1] - 2026-10-07

### Added
- Added dual-runtime PHP 8.3/8.4 compatibility regression coverage.
- Added audited PHP 8.4.26 pool, Nginx cutover, runtime probe, rollback tooling, and staging execution documentation.

### Changed
- Changed project release metadata to version `1.10.1`.
- Changed the IQS Framework staging web runtime baseline to PHP `8.4.26` while retaining PHP `8.3.x` compatibility for shared downstream code.
- Changed CSV parsing and export calls to provide explicit separator, enclosure, and escape parameters.

### Fixed
- Fixed PHP 8.4 deprecation notices caused by separately excluding `E_STRICT` from runtime error reporting.
- Fixed PHP 8.4 deprecation notices caused by relying on the default CSV escape parameter.

## [1.10.0] - 2026-10-07

### Added
- Added custom topbar and sidebar seed colours for system defaults and individual user preferences, with live preview and persistence through the existing theme settings flows.
- Added deterministic palette generation for gradients, foregrounds, hover states, borders, shadows, and dynamic light/dark logo treatment.
- Added shared public-page palette resolution for login, forgot-password, reset-password, and change-password screens.
- Added regression coverage for custom theme whitelisting, hexadecimal validation, public-page integration, safe fallbacks, and bright-colour contrast.

### Changed
- Changed project release metadata to version `1.10.0`.
- Changed duplicated public authentication palettes to use one shared resolver.
- Changed custom colour foreground selection to compare light and dark contrast rather than relying on a fixed luminance threshold.

### Fixed
- Fixed session theme updates accepting unvalidated theme values.
- Fixed bright custom colours producing insufficient contrast on public-page primary actions by enforcing a WCAG AA contrast target.

## [1.9.9] - 2026-10-02

### Changed
- Changed project release metadata to version `1.9.9`.

### Fixed
- Fixed authenticated public users being redirected repeatedly between the login page and dashboard because session guards required a staff ID.
- Fixed public users being unable to save their language preference because the profile controller rejected sessions without a staff ID.
- Fixed public account profiles showing empty identity details by looking up the session Login ID, with a staff ID fallback for legacy sessions.

## [1.9.8] - 2026-09-03

### Added
- Added an idempotent user SSO metadata migration for `f_isAutoProvisioned` and `f_identitySource`, including evidence-based backfill for accounts previously created by `SSO-AUTO`.

### Changed
- Changed project release metadata to version `1.9.8`.

### Fixed
- Fixed User Management hiding the OneID SSO indicator for existing Staff or Student accounts that were SSO-verified but were not originally auto-provisioned.

## [1.9.7] - 2026-09-02

### Added
- Added SSO reconciliation for active existing Staff and Student accounts so a valid OneID login can complete missing verification metadata and record `SSO` as the identity source without silently changing the account category or group.
- Added regression coverage for normalized Staff IDs and Student matric identifiers, resilient external-source status matching, protected existing accounts, and canonical provisioning-category resolution.

### Changed
- Changed project release metadata to version `1.9.7`.
- Changed Staff and Student provisioning-category resolution to prioritize the canonical OneID `resolved_source` before considering secondary identifier-validity flags.
- Changed Staff and Student source lookups to normalize identifiers and tolerate surrounding source-data whitespace and letter-case differences.
- Changed unavailable Student operational mode or external provisioning connections to produce explicit source-unavailable handling instead of being reported as a missing person record.

### Fixed
- Fixed Student auto provisioning being misclassified as Staff provisioning when a valid Student OneID packet also contained a valid Staff identifier in `data3`.
- Fixed active Student records being missed when matric identifiers or `statuskategori` values in `v210` contained case differences or surrounding whitespace.
- Fixed Staff source queries relying on a fragile integer conversion of `kodstatus`, which could fail on non-canonical source values.
- Fixed existing active SSO accounts with incomplete verification metadata being rejected before a valid OneID login could reconcile them.
- Fixed case differences in existing local identifiers causing the provisioning flow to overlook the matching account.
- Fixed valid OneID hybrid identity packets being rejected as invalid whenever both `data3` and `data4` were populated; identity selection now follows OneID `u_category` (`2/3` staff via `data3`, `10/11/12` student or hybrid via `data4`) with the historical staff-first fallback for category-less legacy packets.
- Fixed OneID generated or legacy application credential failures returned through `respond_description` being mislabeled as invalid tokens instead of site credential or site-access errors.

### Security
- Prevented SSO reconciliation from automatically reactivating disabled accounts or changing an existing account category or group when OneID and local identity data disagree.

## [1.9.6] - 2026-08-13

### Added
- Added dependency-free OneID SSO flow helpers for deterministic identity validation and API response classification.
- Added localized Malay and English feedback for invalid OneID tokens, rejected site configuration, malformed provider responses, and unavailable SSO service conditions.
- Added correlation IDs and structured OneID lifecycle logging without raw tokens or complete identity payloads.
- Added OneID SSO regression coverage for staff and student identifiers, ambiguous or invalid identity packets, rejected tokens and sites, auto-reissue, malformed responses, callback precedence, TLS, timeouts, and handoff requirements.

### Changed
- Changed project release metadata to version `1.9.6`.
- Changed OneID callback processing so a fresh `new_sso_cre` token always takes precedence over an existing browser cookie.
- Changed every OneID verification outcome to terminate through a successful handoff or controlled local failure instead of redirecting repeatedly, rendering a blank response, or printing the vendor `X` marker.
- Changed OneID API calls to use a five-second connection timeout, a fifteen-second total timeout, certificate verification, and hostname verification.
- Changed the OneID browser cookie to retain only the opaque credential for one hour with `HttpOnly`, `SameSite=Lax`, and HTTPS-aware `Secure` attributes.
- Changed SSO handoffs to include an explicit expiry, correlation ID, identity validity state, and identity-conflict state in addition to the existing nonce and consumption marker.
- Changed identity resolution to reject packets containing both a valid staff ID and a valid student matric identifier instead of silently preferring the staff identity.

### Fixed
- Fixed invalid existing and callback tokens being able to finish without a redirect or user-facing result.
- Fixed incomplete auto-reissue responses displaying a raw `X` response.
- Fixed stale OneID cookies taking precedence over a newly returned callback token.
- Fixed unreachable, empty, malformed, or unsupported OneID API responses falling through generic vendor behavior without actionable local feedback.

### Security
- Enabled TLS peer and hostname verification for communication with the OneID API.
- Removed the complete OneID identity packet from the browser cookie and reduced its lifetime from thirty days to one hour.
- Prevented raw OneID credentials and complete provider packets from being written by the new SSO lifecycle audit logging.
- Required SSO application handoffs to carry a valid correlation ID and rejected ambiguous staff/student identities.

## [1.9.5] - 2026-08-11

### Added
- Added centralized password-reset eligibility evaluation shared by forgot-password requests and reset-token consumption, with explicit reason codes for disabled, deleted, unknown-category, maintenance-blocked, category-disabled, SSO-managed, policy-unavailable, ambiguous, and email-missing accounts.
- Added forgot-password lookup by Login ID, registered email, staff ID, or employee number with deterministic match priority and duplicate-identifier rejection.
- Added password-reset regression coverage for manual accounts, Super Admin maintenance/category recovery, SSO restrictions, disabled and unknown-category accounts, unavailable policy handling, safe token lifecycle behavior, and schema-change protection.
- Added System Settings save-sync regression coverage for request-local cache invalidation, boolean normalization, AI Chatbot synchronization, dirty-state tracking, and secret redaction.

### Changed
- Changed project release metadata to version `1.9.5`.
- Changed manual Super Admin password recovery to remain available during maintenance mode or a disabled login category while retaining active-account, known-category, registered-email, and manual-login requirements.
- Changed password-reset eligibility to fail closed when authentication policy cannot be loaded and to reject unknown user categories instead of silently treating them as public users.
- Changed forgot-password throttling to use hashed session, IP, and account-identifier scopes, with APCu-backed cross-session counters when APCu is available.
- Changed replacement reset-token handling so existing usable links remain valid until the replacement email is delivered successfully.
- Changed password reset completion to update the local password, consume the active token, and invalidate remaining tokens in one database transaction using the existing schema.
- Changed Forgot Password guidance to document every supported account identifier and shared-email behavior.

### Fixed
- Fixed System Settings toggles and selectors visually reverting to pre-save values because AJAX responses reread stale request-local configuration overrides.
- Fixed incomplete post-save synchronization for General and AI Chatbot forms, including normalized selector, checkbox, Select2, runtime-summary, and saved/dirty states.
- Fixed string boolean values such as `"0"` being unsafe for direct JavaScript truthiness checks during Login Policy synchronization.
- Fixed eligible reset requests invalidating a previous working link before confirming replacement email delivery.
- Fixed duplicate email addresses potentially selecting the newest matching account instead of requiring an unambiguous identifier.
- Fixed forgot-password redirects exposing submitted account identifiers in browser history, access logs, or referrer data.
- Fixed password-update and reset-token consumption being separate operations that could leave partially completed recovery state.

### Security
- Excluded SMTP passwords and AI provider API keys from System Settings AJAX save responses.
- Kept SSO-managed accounts, including Super Admin accounts, on Identity Provider recovery instead of writing local passwords.
- Added granular security audit metadata for password-reset eligibility outcomes without exposing those internal reason codes in generic public responses.
- Invalidated newly created reset tokens when email rendering or delivery fails while retaining the preceding valid token.

## [1.9.4] - 2026-08-04

### Changed
- Reduced the initial Notification Admin and Notification Templates DataTable workload by removing unused summary queries, reusing the total count when no filters are active, and avoiding an unnecessary audience join during notification counts.
- Changed group-table schema inspection to load available columns once per request with a safe fallback for legacy schemas.
- Changed project release metadata to version `1.9.4`.

### Fixed
- Fixed `config.js` failing on login or dashboard load when an incomplete legacy `sessionStorage.__CONFIG__` value did not contain the required layout, navigation, menu, topbar, or sidenav structure.
- Fixed User Management group-list requests returning HTTP 500 because the unquoted `mod` SQL alias conflicted with MySQL syntax.
- Fixed User Management asset links emitting an undefined `$version` warning.
- Fixed Firefox reporting unreachable JavaScript after return statements in the Additional Database inspect, schema-preview, and data-preview callbacks.

## [1.9.3] - 2026-08-04

### Changed
- Redesigned the personal Notifications workspace with higher-contrast segmented filters, filter icons, inline counts, improved search and action styling, clearer unread emphasis, and responsive light/dark presentation.
- Changed the Developer Guide search card to use the full available content width after removing its summary metrics.
- Simplified Notification Admin, Notification Templates, System Cache, Access Matrix, Developer Guide, and Manual Management so their primary workflows follow the page hero without separate KPI card rows.
- Changed project release metadata to version `1.9.3`.

### Fixed
- Fixed active notification filter labels becoming unreadable because the page stylesheet referenced Bootstrap `--bs-*` colour variables instead of the IQS Framework `--ct-*` theme variables.
- Fixed the Notification Admin publish confirmation SweetAlert rendering behind the setup modal by assigning the alert container a higher stacking layer.

### Removed
- Removed KPI card markup and KPI-only server-side calculations or client-side update logic from Notification Admin, Notification Templates, System Cache, Access Matrix, Developer Guide, and Manual Management.

## [1.9.2] - 2026-08-03

### Added
- Added reusable translated busy-button feedback with a spinner, action text, double-click protection, and state restoration for group, menu, module, and subgroup save operations.
- Added authoritative access, user-count, and deletion-eligibility data to group create/update and permission-save responses for immediate in-place table updates.
- Added a localized secondary status message and the system logo to the global transaction loader.

### Changed
- Redesigned the global transaction loader as a compact, responsive, logo-led status card with a restrained progress treatment, light/dark themes, and reduced-motion support.
- Changed User Group table redraws to preserve colour, group-action, module-access, and menu-access column alignment after all in-place transactions.
- Changed group action buttons to use deterministic flex spacing so server-rendered and dynamically inserted rows remain visually identical.
- Changed group colour indicators from narrow vertical bars to clear square swatches aligned with category badges.
- Changed the Menu, Module, and Group toolbar buttons to share the same neutral visual treatment.
- Changed module modal initialization so create/edit modes and save-button states reset reliably whenever the modal is opened or closed.
- Changed the documented runtime from Docker/Apache to WSL 2 Ubuntu with Nginx and PHP-FPM.
- Updated database inspection guidance to use the native WSL PHP runtime.
- Updated setup guidance to make the native WSL runtime the only maintained local deployment path.
- Changed `sync-updates.sh` to report sync results in the terminal without initializing unused log files.
- Changed project release metadata to version `1.9.2`.

### Fixed
- Fixed Group and Module delete requests failing CSRF validation when the web server normalized request-header casing.
- Fixed the Group delete button remaining hidden after all module/menu access was removed until the page was manually refreshed.
- Fixed newly created or refreshed group rows displaying misaligned module, menu, and edit action icons.
- Fixed Group and Module save buttons remaining stuck on `Saving...` after asynchronous transactions or modal reuse.
- Fixed Module create reopening in edit mode after a previous module edit.
- Fixed menu deletion displaying a misleading second Undo message even though no restore feature exists.

### Removed
- Removed the obsolete menu-delete Undo wrapper, styling, and Malay/English translation strings.
- Removed the retired Dockerfile, Compose service, Apache/PHP container configuration, Docker ignore rules, and development TLS key material.
- Removed unused `sync.log` and `conflict.log` initialization and references from the update sync workflow.

## [1.9.1] - 2026-08-02

### Added
- Added professional page-specific presentation assets for Access Matrix, Developer Guide, Manual Management, Notifications, Notification Admin, Notification Templates, System Cache, and Email Template management.
- Added AJAX-backed notification administration and notification-template listing endpoints with local table refresh behavior.
- Added controlled manual deletion, manual storage structure, group synchronization improvements, and safer manual viewing/upload handling.
- Added email-template unsaved-change protection, test-recipient and subject confirmation, cancellable preview requests, sandbox/referrer safeguards, and external-resource guidance.
- Added expanded Malay and English translation coverage for redesigned administration pages, modal actions, validation, loading, empty states, SweetAlert messages, audit details, and notification workflows.

### Changed
- Redesigned the login, Profile, User Management, User Group, System Settings, Template Generator, System Cache, Audit Center, Access Matrix, Developer Guide, Manual Management, Notifications, Notification Admin, Notification Templates, and Email Template experiences with consistent page headers, themed KPI cards, spacing, panels, tabs, modals, DataTables, and responsive states.
- Changed staff and student add-access flows to retain remote Select2 search while using the correct type-specific labels and placeholders.
- Changed Audit Center tab switching and supported administration transactions to use local button, panel, spinner, or table loading states instead of blocking global loaders.
- Changed cache maintenance to present discovered locations, runtime-cache status, operation safeguards, consistent KPI/table spacing, and in-place refresh behavior.
- Changed access, group, menu, module, manual, notification, profile, system-template, and system-setting operations to return more consistent AJAX responses and audit context.
- Changed Template Generator file creation and generation result handling to provide clearer governance, validation, and audit information.
- Changed Email Template asset versioning to use stable application/file metadata instead of generating a new cache key on every request.
- Changed project release metadata to version `1.9.1`.

### Fixed
- Fixed staff and student Select2 searches failing to display lookup results after the User Management redesign.
- Fixed the student selector displaying the staff placeholder.
- Fixed inconsistent or overlapping card gaps, DataTable control spacing, modal text contrast, preview positioning, oversized radii, and inconsistent tab styling across redesigned pages.
- Fixed System Cache operations remaining in a loading state and corrected table/KPI layout inconsistencies.
- Fixed Audit Center tab changes invoking an unnecessary global loader.
- Fixed untranslated or hard-coded labels and feedback messages across the affected multilingual administration workflows.
- Fixed potentially stale email preview responses replacing newer results.
- Fixed `sync-updates.sh` dry-run summaries always reporting zero and replaced timestamp-only conflict detection with release-bound three-way SHA-256 manifests, blocking preflight, dry-run approval, transactional replacement, rollback data, and post-copy verification.

### Security
- Standardized authentication, authorization, CSRF validation, request-method enforcement, input validation, and safe JSON failure handling across affected administrative AJAX endpoints.
- Reduced technical error disclosure in Email Template management while retaining server-side diagnostic logging.
- Hardened manual file handling, system-template path/output validation, cache target resolution, notification operations, profile session actions, and audit export/detail requests.

## [1.9.0] - 2026-08-01

### Added
- Added platform-aware Additional Database variants so Linux DBLIB and Windows ODBC/SQLSRV configurations can coexist in a shared registry.
- Added deterministic runtime resolution, platform coverage diagnostics, UI presets, exact-tuple test tracking, and optimistic concurrency protection for registry edits.
- Added authenticated v2 credential envelopes with legacy/v2 dual-read compatibility and environment-managed key identification.
- Added read-only diagnostics, credential smoke testing, platform auditing, and dry-run-default controlled migration tools with backup and rollback safeguards.
- Added Windows/Linux regression coverage for Sybase, MSSQL, and MySQL resolution paths.

### Changed
- Changed Additional Database test, inspect, schema preview, object preview, and sample operations to resolve an exact environment/OS/driver tuple without crossing platform or environment boundaries.
- Changed diagnostics to distinguish current runtime health from full platform coverage and to expose standardized redacted error categories through audit metadata.
- Changed connection-test persistence to update only the exact selected variant.
- Changed project release metadata to version `1.9.0`.

### Fixed
- Fixed shared Additional Database driver settings requiring manual DBLIB/ODBC switching between Linux and Windows development runtimes.
- Fixed variant selection and form replacement behavior that could choose, overwrite, or remove a different platform's configuration.
- Fixed sensitive credential, DSN, username, host, and connection-detail exposure in diagnostics, previews, and failure logs.

### Security
- Added authenticated credential encryption, explicit key configuration checks, redacted exception/log handling, bounded inspection queries, and safe failure categories without changing the downstream PDO exception contract.

## [1.8.5] - 2026-07-31

### Added
- Added role-aware AJAX menu search below the sidebar user profile, limited to menus available to the active role and current operational mode.
- Added menu search result context for modules and optional subgroups, plus request debouncing, stale-request cancellation, loading/empty/error states, result limits, and keyboard navigation.
- Added condensed-sidebar search behavior that replaces the full search field with an icon and restores focus after reopening the sidebar.
- Added Malay and English core translations for sidebar search and student-specific profile labels.

### Changed
- Changed profile identity presentation to use staff number, position, and department labels for staff while using matric number, programme, and faculty labels for students.
- Changed student profile fallback detection to use normalized profile/session category values and identity characteristics when explicit category data is unavailable.
- Changed student profile programme presentation to use an education-specific icon while preserving the existing staff presentation.
- Changed project release metadata to lock the application version at `1.8.5`.

### Fixed
- Fixed student profiles showing staff-oriented labels and copy actions even when the displayed values represented student data.
- Fixed active-session warnings on forced password renewal, forgot-password, and reset-password pages by applying session INI settings only before session startup.

## [1.8.4] - 2026-06-23

### Added
- Added framework-level external service exception hierarchy for distinguishing provider/API failures from internal application failures.
- Added `ExternalHttpClient` and `ExternalHttpResponse` for reusable outbound HTTP integration handling with provider-aware exception mapping.
- Added shared AJAX `jsonExceptionResponse()` handling so `ExternalServiceException` responses no longer default to HTTP 500.
- Added standardized `[ExternalService]` logging with redaction for common API key, token, authorization, and password values.
- Added external service failure handling documentation and Developer Guide examples for future integrations.

### Changed
- Changed AI Chatbot provider calls for OpenAI-compatible providers, Gemini, Anthropic, and Ollama to use the shared external HTTP client.
- Changed AI Chatbot model fetching and message handling so provider auth, rate limit, timeout, unavailable, and invalid-response failures return service-level statuses instead of hardcoded HTTP 500.
- Changed SMTP/email delivery handling so HTTP-facing flows can classify SMTP authentication, timeout, connection, TLS/SSL, and delivery failures as external service failures.
- Changed email submit and email template test-send flows to use external-service response handling for SMTP failures.
- Changed project release metadata to lock the application version at `1.8.4`.

### Fixed
- Fixed misleading Internal Server Error reporting for AI provider failures that are caused by upstream provider/API conditions.
- Fixed misleading Internal Server Error reporting for SMTP delivery failures in email test and submit flows.

## [1.8.3] - 2026-06-14

### Added
- Added AI Chatbot Knowledge Manager at `public/pages/ai-chatbot-knowledge.php` for maintaining manual curated knowledge with language, visibility, allowed groups, review metadata, status controls, AJAX save/edit/status/delete flows, SweetAlert feedback, local loading states, and compact DataTables presentation.
- Added PDF knowledge source support with `tbl_ai_chat_knowledge_source`, `tbl_ai_chat_knowledge_chunk`, PDF-only upload validation, text extraction, draft chunk generation, source/chunk activation controls, and filtered retrieval from active PDF chunks.
- Added AI Chatbot Review Dashboard at `public/pages/ai-chatbot-review.php` for metadata-only governance review of no-knowledge questions, review queues, provider failures, outcomes, categories, and latency.
- Added optional conversation persistence through `AiChatbotConversationRepository` for chatbot sessions and messages, with raw message content controlled by storage settings.
- Added controlled chatbot implementation and database documentation, including PDF schema, core knowledge seed, implementation readiness notes, and Docker-based read-only DB inspection guidance.

### Changed
- Changed AI Chatbot knowledge retrieval to hybrid keyword-ranked matching across manual knowledge and active PDF chunks while preserving language, visibility, group, and super-admin filters before provider prompt construction.
- Changed AI Chatbot widget responses to show answer text only, removing suggested navigation action links from the user-facing chat bubble.
- Changed AI Chatbot usage persistence to degrade safely when optional usage tables are not installed.
- Changed System Settings > AI Chatbot links to expose Knowledge Manager and Review Dashboard entry points.
- Changed Knowledge Manager UI to use modal-based create/edit, SweetAlert feedback above modals, no full-page refresh for supported actions, icon actions with tooltips/loaders, compact cards, and standardized two-line row clamping.
- Changed project release metadata to lock the application version at `1.8.3`.

### Fixed
- Fixed chatbot knowledge activation controls so manual and PDF knowledge cannot be activated when language, visibility, allowed groups, extraction, or chunk readiness requirements are not satisfied.
- Fixed PDF chunk retrieval so chunks are sent to the provider only when both source and chunk rows are active and extraction is processed.
- Fixed duplicate PDO named placeholders in knowledge retrieval queries by generating unique term placeholders.
- Fixed Knowledge Manager SweetAlert stacking so alerts appear above the modal.
- Fixed Knowledge Manager modal behavior so successful saves keep the modal open until the user explicitly closes it.

## [1.8.2] - 2026-06-12

### Added
- Added role-aware AI Chatbot answer boundaries for system-focused assistance and restricted administrator workflows.
- Added safe runtime context for chatbot requests, including sanitized page path/title, app title, active group context, language, and chatbot access mode.
- Added read-only visible system context through `AiChatbotSystemContext`, capped by active group module/menu access.
- Added optional curated AI Chatbot knowledge base support through `tbl_ai_chat_knowledge` and `docs/ai-chatbot-knowledge-tables-2026-06-12.sql`.
- Added visibility-filtered knowledge retrieval through `AiChatbotKnowledgeContext`.
- Added permission-filtered retrieval policy for system-specific questions so answers must be grounded in approved context.
- Added `AiChatbotQuestionClassifier` for review-safe governance metadata across system help, navigation help, access help, troubleshooting, sensitive blocked, and unknown questions.
- Added production runbook monitoring SQL for AI Chatbot governance review loops.

### Changed
- Changed AI Chatbot prompt construction to use approved runtime, visible system, curated knowledge, retrieval policy, and classification context.
- Changed AI Chatbot usage metadata to include context source, knowledge item count, grounded-answer requirement, question category, risk, review reason, and blocked-detail flags.
- Changed AI Chatbot documentation to cover phases for role-aware answer boundaries, safe runtime context, read-only system context, curated knowledge, permission-filtered retrieval, and governance review.
- Changed project release metadata to lock the application version at `1.8.2`.

## [1.8.1] - 2026-06-11

### Added
- Added the core AI Chatbot module with a floating widget, dedicated page, AJAX message endpoint, provider registry, and provider service layer.
- Added AI Chatbot provider support for local/free-first testing through Ollama and compatible provider abstractions for future hosted APIs.
- Added AI Chatbot runtime configuration under System Settings > AI Chatbot, stored in `tbl_m_config` using the `ai_chatbot` group.
- Added AI Chatbot database script documentation for chat sessions, messages, and usage tracking tables.
- Added AI Chatbot implementation blueprint and production runbook documentation.

### Changed
- Changed AI Chatbot runtime settings to use `tbl_m_config` only, removing AI Chatbot setting fallback from `.env`.
- Changed System Settings to include an AI Chatbot tab after Language with subtabs for Overview, Provider, Limits, Character, and Storage.
- Changed AI Chatbot widget positioning and panel presentation to avoid footer overlap and improve visual separation.
- Changed project release metadata to lock the application version at `1.8.1`.

### Fixed
- Fixed missing AI Chatbot tab icon by switching to a Remix Icon class available in the project asset set.

## [1.8.0] - 2026-06-06

### Added
- Added audit trail coverage for core mutation flows including form save, module creation, notification template actions, student sync, manual management, and system template generation.
- Added field-level audit change-set logging for supported create, update, sync, upload, duplicate, archive, restore, and delete flows.
- Added topbar notification language keys and core fallback translations identified during the public-page language audit.
- Added dedicated global loader translation keys for loading, saving, submitting, navigation, and logout states.
- Added a governance checklist to the system template generator for language keys, audit hooks, and access control.

### Changed
- Changed framework translation updates to use `public/lang/core/*.php` for core project keys.
- Changed application modal markup so dialogs are top-aligned consistently across audited public pages.
- Changed module creation in `kumpulan-pengguna.php` so the legacy inline POST write path no longer bypasses the audited AJAX endpoint.
- Changed additional database sample-code blocks so SQL write examples are explicitly marked as sample-only for audit scanning.
- Changed project release metadata to lock the application version at `1.8.0`.

### Fixed
- Fixed missing core translation coverage found across active public pages during the language-key audit.
- Fixed audit blind spots on critical page actions by adding non-blocking audit events and change details.

## [1.7.9] - 2026-06-04

### Added
- Added admin-only System Cache maintenance page at `public/pages/system-cache.php` for discovering and clearing standard project cache locations.
- Added dynamic cache discovery for `app/cache`, `public/cache`, and `storage/cache`, including file count, total size, and last-modified summary.
- Added cache-clearing AJAX endpoint with CSRF validation, admin permission enforcement, OPcache/APCu best-effort clearing, and audit logging.
- Added `upnm30` to the registered downstream project list for `sync-updates.sh`.
- Added core file protection standard and protected files registry documentation for downstream project governance.
- Added `tools/core-file-protection-audit.php` to validate protected core markers before release or update collection.

### Changed
- Changed System Cache clearing feedback to use the existing global loader and update the page in place without forcing a page refresh.
- Changed active framework pages to include a protected core read-only header for downstream programmers.
- Changed active framework controllers to include a protected core read-only header for downstream programmers.
- Changed framework AJAX endpoints and shared AJAX helpers to include a protected core read-only header for downstream programmers.
- Changed framework classes and services to include a protected core read-only header for downstream programmers.
- Changed bootstrap/includes, setting helpers/constants, configuration, root public entry files, root language wrappers, and core language files to include a protected core marker for downstream programmers.
- Changed page/template generator output so generated page, controller, and CSS files are marked as project-generated and safe to customize.
- Changed update collection scripts to include core file protection docs and audit tooling in framework update packages.
- Changed developer guidance and sync-ignore notes to include final core protection release checks for downstream programmers.
- Changed Developer Guide references and handover checklist to point programmers to core protection standards, protected file registry, and audit validation.
- Changed project release metadata to lock the application version at `1.7.9`.

### Fixed
- Preserved cache directory structure, `.gitkeep`, `.htaccess`, and active user sessions during cache-clearing operations.

## [1.7.8] - 2026-05-20

### Added
- Added `.sync-update-ignore` support to `sync-updates.sh` and `update-files.sh` so selected update files can be excluded from `updates/` collection and downstream sync distribution.
- Added `e-prestasi` to the registered downstream project list for `sync-updates.sh`.

### Changed
- Changed global loader behavior so the full-page loader is now reserved for sidebar navigation, while in-page actions rely on local loading states.
- Changed `kumpulan-pengguna.php` group, menu, and module flows to use standardized SweetAlert handling, faster modal-open sequencing, and earlier success feedback after confirmed server writes.
- Changed project release metadata to lock the application version at `1.7.8`.

### Fixed
- Fixed `kumpulan-pengguna.php` table refresh behavior so add/edit/delete menu actions no longer create malformed or ghost rows during in-place updates.
- Fixed group delete-button visibility so the main table now follows the same eligibility rules as the backend, including protected groups and assigned-user checks.
- Fixed `kumpulan-pengguna.php` Module Access column so the action button remains available for manageable groups instead of appearing unintentionally disabled.
- Fixed post-transaction feedback timing across group and module management flows so success alerts are shown without waiting for heavier sidebar or table refresh work to finish.

## [1.7.7] - 2026-05-08

### Added
- Added Super Admin `View As` impersonation from `senarai-pengguna.php`, including start/stop AJAX endpoints, a reason prompt, view-only mode, support-action mode, and a topbar banner with manual stop.
- Added impersonation access-policy registration for start and stop endpoints.
- Added system-configured `View As Timeout (Minutes)` under System Settings > General > Limits, replacing the previous environment-based timeout setting.
- Added View As SOP documentation and SQL support files for `ATTEMPT` audit outcome and audit user-id normalization.

### Changed
- Changed impersonation audit ownership so request-level `user_id` and `login_id` are bound to the real actor while the effective target is preserved in impersonation metadata.
- Changed support-action write audits to use the neutral `ATTEMPT` outcome.
- Changed audit logging normalization so `audit_event.user_id` and `audit_request.user_id` store staff number (`f_nopekerja`) instead of the MySQL user primary key (`f_userID`) where possible.
- Changed project release metadata to lock the application version at `1.7.7`.

### Fixed
- Fixed View As logout, stop, timeout, and view-only write-block flows so the actor session is restored consistently.
- Fixed Profile Login Activity empty-table display so the no-records message spans the full table instead of wrapping inside the `No.` column.

## [1.7.6] - 2026-05-06

### Added
- Added sidebar menu subgroup blueprint documentation with Phase 1 SQL for optional module-level menu grouping.
- Added subgroup management endpoints and UI in `kumpulan-pengguna.php` for optional module-level menu subgroups.
- Added audit logging for menu subgroup create, update, delete, and denied delete attempts.
- Added subgroup-aware menu ordering support so grouped menu sections can be positioned alongside direct module menus.

### Changed
- Changed `Modul.php` menu loading to expose optional subgroup metadata when the subgroup schema exists, while remaining backward-compatible with the current two-level sidebar schema.
- Changed sidebar rendering to support optional nested menu subgroups inside parent modules while preserving direct module menu behavior.
- Changed menu create/edit/list/get/group-permission flows to read and write optional `f_subgroupID` values.
- Changed `kumpulan-pengguna.php` Menu Access UI to show subgroup context in a dedicated column with compact badges and cleaner status/action layout.
- Changed Menu Subgroup Management UI to use auto-populated ordering, direct icon picking, protected delete visibility, and a cleaner management form.
- Changed sidebar subgroup blueprint documentation to include final implementation notes and test checklist.
- Changed project release metadata to lock the application version at `1.7.6`.

### Fixed
- Fixed child menu edit save flow so the parent Menu Access modal waits until it is fully rendered before rebuilding its DataTable, preventing oversized/wrapped rows after save on slower deployments.
- Fixed menu subgroup delete behavior so delete actions are hidden or blocked when menus are already assigned to the subgroup.
- Fixed sidebar subgroup arrow alignment and direct sidebar item spacing for Dashboard, User Manual, and Logout entries.

## [1.7.5] - 2026-05-06

### Added
- Added Phase 1 in-app notification topbar, notification page, AJAX read/list endpoints, and core language keys.
- Added Phase 2 notification publisher and audience resolver for universal event-based notifications.
- Added Phase 3 workflow notification support with task publishing helper, action status endpoint, overdue filter metadata, and source completion/expiry helpers.
- Added notification developer examples for announcements, direct events, workflow tasks, dedupe behavior, and source completion helpers.
- Added `tools/notification-seed-test.php` to seed Phase 1-3 notification test records.
- Added Phase 4 notification admin composer page and AJAX publish endpoint for admin-managed in-app notifications.
- Added notification template management page, CRUD endpoint, and DB-backed `publishFromTemplate()` rendering.
- Added notification developer standard documentation and sample-code modal support for common programmer integration flows.
- Added topbar notification dropdown integration with unread count, compact 5-item preview, read actions, and View All navigation.

### Changed
- Changed notification admin and template management pages to use full-width workspaces and DataTables-style listing behavior.
- Changed notification setup/template modals to use tabbed professional layouts, tooltip-based field guidance, icon selection, date-time inputs, and preview surfaces.
- Changed tabbed modals across administration pages to open top aligned, while non-tabbed modals remain centered unless the page explicitly requires all modals top aligned.
- Changed `kumpulan-pengguna.php` so all modals open top aligned for a more consistent management workflow.
- Changed sidebar theme loading so a user's personal theme can be applied earlier and avoids visible fallback to the global theme on navigation.
- Changed sidebar rendering to support the configured branding image beneath the logo.
- Changed project release metadata to lock the application version at `1.7.5`.

### Fixed
- Fixed notification admin audience value display so stored audience values are shown with clearer descriptions where possible.
- Fixed notification admin and template table cell alignment so multi-line rows render top aligned.
- Fixed notification template action buttons for edit, duplicate, archive, and delete flows, including delete protection for templates currently in use.
- Fixed topbar notification badge overlap and cleaned up the notification submenu header/presentation.

## [1.7.4] - 2026-05-02

### Added
- Added core/custom language folder structure under `public/lang/core/` and `public/lang/custom/`.
- Added compatibility wrappers for `public/lang/ms.php` and `public/lang/en.php` so direct legacy includes still return merged translations.
- Added `tools/language-split-tool.php` for validating language parity, duplicate keys, custom overrides, and legacy language migration.
- Added language core/custom split audit documentation in `docs/language-core-custom-split-audit-2026-05-02.md`.

### Changed
- Changed the global language helper to load core translations first and project custom translations second.
- Changed bootstrap and System Settings JavaScript translation loading to use merged core/custom language data.
- Changed generated page language entries so project-specific keys are written to `public/lang/custom/`.
- Changed update and sync scripts to protect `public/lang/custom/*` while still distributing core language files, release metadata, and language tooling.
- Changed project release metadata to lock the application version at `1.7.4`.

### Fixed
- Fixed duplicate core translation keys reported by the language validation tool.

## [1.7.3] - 2026-05-01

### Added
- Added explicit `.env` sections for main MySQL production and development runtime targets while keeping legacy `DB_MYSQL_*` values as fallback compatibility configuration.
- Added MySQL runtime diagnostics in `tetapan-sistem.php`, including resolved key, production/development targets, dedicated/fallback indicators, and same-target warning.
- Added tabbed Additional Connections sample-code guidance for programmers, covering Service, Repository, Controller, Transaction, Read-only, Ajax Endpoint, DataTables, Dropdown, Insert Update, and Batch Sync patterns.

### Changed
- Changed main MySQL bootstrap handling so `MAIN_DB_ENVIRONMENT` can be resolved earlier and MySQL runtime caches are cleared when production/development settings change.
- Changed database settings AJAX behavior so MySQL and Sybase runtime summaries update in place after save instead of requiring a full page refresh.
- Changed Additional Connections sample-code modal layout to use compact tabs with per-example copy actions.
- Changed legacy MySQL `.env` comments to clearly mark `DB_MYSQL_*` as temporary fallback values.

### Fixed
- Fixed stale MySQL runtime information in the Database settings UI after switching between production and development.
- Fixed stale Sybase runtime summary values after changing Sybase environment or operational mode.
- Fixed database settings UI sync paths so fallback AJAX handlers also refresh runtime summary values.

## [1.7.2] - 2026-04-23

### Added
- Added runtime baseline documentation in `README.md` for `PHP 8.3.30`, Docker `php:8.3.30-apache`, and `MySQL 8.0.41`.
- Added PHP 8.4 readiness audit documentation in `docs/php84-readiness-audit-2026-04-23.md`.

### Changed
- Changed Docker runtime from `php:8.2-apache` to `php:8.3.30-apache` to align container runtime with the current server baseline.
- Changed landing-page messaging in `public/index.php` so the public entry screen positions IQS Framework as the shared core platform for downstream systems.
- Changed `forgot-password.php` user feedback so restricted account flows return a generic SweetAlert review message without exposing policy-specific detail.
- Changed password-change notification flows in `reset-password.php` and `change-password.php` to use explicit mailer send-state handling and clearer logging.
- Changed project release metadata to lock the application version at `1.7.2`.

### Fixed
- Fixed `public/classes/Database.php` typed connection handling so instance cleanup no longer conflicts with nullable PDO lifecycle usage under the current PHP baseline.
- Fixed `public/pages/template-emel.php` save behavior so create and update actions can complete through AJAX without forcing a full page refresh.
- Fixed `public/request-unavailable.php` redirect timing to wait 5 seconds before redirect.
- Fixed forgot-password flow observability so mail, eligibility, and token-creation states are easier to trace during development.
- Fixed audit logging so incompatible `audit_event.outcome` values are normalized before insert, preventing schema truncation warnings.
- Fixed mailer environment detection and fallback logging so SMTP failures are easier to diagnose in development.

### Security
- Hardened forgot-password handling to keep restricted-account responses generic for anonymous users while preserving internal debug visibility in development.

## [1.7.1] - 2026-04-22

### Added
- Added AJAX-based module creation for `kumpulan-pengguna` through `public/ajax/module-create.php` so module changes can complete without a full page reload.

### Changed
- Changed `kumpulan-pengguna` group, menu, module, and reorder flows to use a shared silent sidebar sync path instead of mixed reload and reorder-only refresh behavior.
- Changed `kumpulan-pengguna` table updates so group create, update, delete, and access changes refresh the group listing in place while keeping the current page context.
- Changed `kumpulan-pengguna` module save behavior to remove the button spinner loader and keep sidebar updates running in the background after save.

### Fixed
- Fixed stale sidebar state after saving active-group access, menu, and module changes from `kumpulan-pengguna`.
- Fixed inconsistent sidemenu behavior where some updates required `location.reload()` before access, icon, or structure changes became visible.

## [1.7.0] - 2026-04-11

### Added
- Added BDR distance notification issue handling for `too_close`, `too_far`, `outside_allowed_region`, and `state_mismatch`, including badge/filter support and localized labels.
- Added dynamic bulk email issue selection so administrators can send notifications only for issue categories that exist in the current BDR distance result set.
- Added persistent BDR notification exclusions per staff, address hash, and issue type through `tbl_bdr_email_notification_exclusion`.
- Added SSO-only self-confirmation flow for eligible BDR notification issues through `pages/bdr-notification-confirm.php`, with declaration text, token expiry handling, staff matching, and audit-friendly confirmation metadata.
- Added bulk email job item support for self-confirm action content through `f_selfConfirmHtml` and `f_selfConfirmText`.
- Added BDR email template placeholders for `{{primary_action_html}}`, `{{primary_action_text}}`, and `{{guidance_text}}`.
- Added multi-site BDR distance support for `upnm_kampus` and `hat_mizan`, including site tabs, site-aware office labels, office addresses, office coordinates, cache scoping, export context, email context, and self-confirm exclusions.
- Added `tbl_bdr_staff_site` as the local MySQL BDR staff identity and site assignment snapshot, enabling manual HAT Mizan staff assignment where the Sybase staff view has no official site indicator.
- Added HAT Mizan staff mapping SQL scripts and multi-site migration documentation for production rollout.
- Added `public/log/.htaccess` to block direct browser access to project log files on Apache deployments.

### Changed
- Updated BDR bulk and individual notification email content to use point-form reasons and a single primary action link per address.
- Updated BDR notification email introduction text to adapt to the detected issue category, with a combined introduction for records containing multiple issues.
- Updated BDR self-confirmation page flow so unauthenticated users review token details before OneID login, declaration is required before confirmation, and used links show a completed/expired state.
- Updated BDR distance API single-record lookup to read from the local MySQL distance cache instead of requiring a live Sybase lookup during the API request.
- Updated BDR distance API to support `site`, validate invalid site values, default omitted site values to `upnm_kampus`, return site coordinates, hydrate staff identity from `tbl_bdr_staff_site`, and return `not_yet_calculated` when staff exists but no distance cache record exists yet.
- Updated BDR distance refresh behavior so recalculated rows can rehydrate table status, issue labels, notification state, and exclusion state without requiring a manual page refresh.
- Updated BDR distance page switching to load site data through AJAX instead of full page refresh.
- Updated BDR distance cache lookup, notification exclusions, bulk email jobs, and self-confirm token handling to include `f_siteCode` so Kampus UPNM and HAT Mizan records remain separated.
- Updated BDR email placeholders and message text to use site-aware destination labels instead of hard-coded UPNM wording.
- Updated temporary BDR/API/access/SSO/tetapan debug logs to be disabled by default and gated behind explicit environment flags.
- Updated SweetAlert behavior so standard modal alerts require a user click to close, while toast alerts continue to auto-close.
- Updated URL helper and login form link generation to handle both subfolder deployment and production root-domain deployment cleanly.

### Fixed
- Fixed stale logout alert behavior on repeated SSO login attempts after logout.
- Fixed AJAX/access-denied responses so JSON-like requests receive JSON instead of an HTML error response.
- Fixed duplicate-slash URL generation for production root deployment.
- Fixed SSO SP client redirects so production root-domain SSO handoff exits immediately after redirect and uses proxy-aware current URL matching.
- Fixed BDR API staff identity gaps by reading department, department code, position, email, and staff name from the local staff-site snapshot instead of relying on the distance cache table alone.
- Fixed BDR distance cache collisions for identical addresses shared by multiple staff by keeping site/staff context explicit in cache and hydration flows.

## [1.6.0] - 2026-04-08

### Added
- Expanded the System Template Generator creation modal into a tabbed flow with separate sections for template form input, page icon selection, and access mode selection.
- Increased generated page icon choices to 48 selectable icons.
- Added protected-account policy support for the special account `0530-09`, including protected badge display and stricter delete/edit governance.
- Added policy-driven SSO auto provisioning for Staff and Student identities, including configurable default group assignment and first-login application record creation.
- Added auto-provision visibility in `senarai-pengguna.php` so SSO-created accounts can be identified directly from the user listing.

### Changed
- Redesigned the System Template Generator modal to be more compact, centered, and viewport-friendly, with internal panel scrolling instead of full modal scrolling.
- Simplified `Page Name` guidance to clarify that users should enter the page name only, without `.php`.
- Refined multiple administrative UX surfaces, including audit metadata visibility, sidebar toggle behavior, modal presentation, and protected-account interaction handling.
- Extended Login Policy configuration with a dedicated `SSO Auto Provisioning` section for Staff and Student controls.
- Refined login flow handling so Staff and Student first-access behavior is distinguished more clearly between SSO provisioning, manual login readiness, and Public-user access rules.

### Fixed
- Restored DataTables behavior in `senarai-pengguna.php` after earlier initialization regressions.
- Stabilized sidebar collapsible menu behavior so parent menus can open and close reliably without unwanted page refreshes.
- Reduced generic login failures by mapping SSO auto-provision edge cases to clearer outcomes, including invalid default group configuration and unavailable source identity data.

### Security
- Hardened request-level access control for pages, AJAX endpoints, and actions using centralized access policy handling.
- Restricted audit metadata visibility to Super Admin on profile audit flows.
- Kept Public-user access strictly dependent on existing `tbl_m_user` records while limiting Staff and Student automatic record creation to SSO-only first access.

## [1.5.0] - 2026-04-07

### Added
- Added centralized access governance with support for public, super-admin-only, custom-guard, and group-menu-based request policies.
- Added current system module documentation coverage in the project README.

### Changed
- Refined administrative UX across access-protected pages, modals, audit views, and generated page workflows.

### Security
- Hardened direct URL access so page authorization is enforced server-side instead of relying only on menu visibility.
- Introduced safer handling for unavailable or unauthorized destinations with neutral user-facing messaging.

## [1.4.0] - 2026-04-03

### Added
- Added full email template management, including template listing, preview, test send, seed generation, and delivery integration.
- Expanded system template generation capabilities with DB-backed template records, modal-based generation flows, and template variants.

### Changed
- Improved generated page scaffolding with cleaner management flow and access mode support for generated pages.

## [1.3.0] - 2026-03-31

### Added
- Added login policy management and stronger OneID SSO integration flow.
- Added password lifecycle enforcement, password history controls, reset/change flows, and database-backed throttling and lockout controls.
- Added Audit Center enhancements covering events, requests, sessions, changes, security insights, and advanced filtering.

### Changed
- Refined login UX and strengthened session termination handling across admin-facing flows.
- Improved system settings behavior for OneID SSO configuration and related authentication settings.

### Security
- Hardened authentication, session control, and SSO handoff behavior.

## [1.2.0] - 2026-03-22

### Added
- Added DB-backed general system settings with language-aware runtime fallback for footer, mail notes, and shared layout content.
- Added role-based manual management and better user manual access handling.
- Added safer runtime configuration support for system-wide operational settings.

### Changed
- Migrated the external runtime architecture from a legacy single-active Sybase model to a clearer staff-and-student multi-domain runtime model.
- Moved database secrets and sensitive runtime values to environment-driven configuration for safer deployment.
- Rewrote core project documentation around architecture, features, setup, security, and deployment.

### Fixed
- Cleaned up legacy configuration, unused helpers, demo remnants, and orphaned assets.

## [1.1.0] - 2026-03-20

### Added
- Added richer profile management features, including audit trail display, audit modal improvements, device/duration helpers, and better login activity visibility.
- Added stricter group/module management flows, including module reorder support, strict delete behavior, and in-modal access refinements.
- Added layout and application configuration centralization for branding, mail, behavior, and manual access surfaces.

### Changed
- Standardized page headers, profile localization, dashboard login display, topbar language handling, and group access UI behavior.
- Extended session idle timeout handling and refined multiple administrative UI surfaces.

## [1.0.0] - 2026-02-07

### Added
- Established the locked base platform with core login, layout, session handling, theme support, translation support, user foundation, and administrative scaffolding.
- Added early user, group, role, and access-management capabilities.

### Changed
- Migrated identity handling from `f_groupKod` toward `f_groupID`-based access flow.
- Introduced hardening work for group management UI gating and non-blocking audit coverage during base platform stabilization.

### Security
- Locked the hardened base platform as the first stable baseline for subsequent feature work.

## [Pre-1.0 Foundation] - 2025-07-14

### Added
- Established the original project structure, login foundation, layout framework, theme settings, translation groundwork, and early administrative pages.
- Added early system settings, database configuration, email configuration, dashboard loading improvements, group/menu access work, and initial user management capabilities.

### Changed
- Iteratively refined UI layout, dashboard loading behavior, sidebar/menu behavior, and AJAX-based page interactions during the early build phase.
