# IQS Analytics & Reporting Center Core Blueprint

Date: `2026-06-18`
Project: `IQS-Framework`
Status: Planning documentation only

## Purpose

IQS Analytics & Reporting Center is proposed as a reusable core capability inside IQS-Framework.

The goal is to let downstream systems build, run, export, share, schedule, and eventually analyze reports without rebuilding report modules separately for each project.

This blueprint adapts the earlier high-level reporting idea to the actual structure of the current IQS-Framework core project.

## Current Core Context

The framework already has several foundations that should be reused:

- Main MySQL application database for metadata, users, groups, menus, settings, audit records, and runtime data.
- Sybase staff and Sybase student runtime connections.
- Additional database registry under System Settings > Database.
- Group, module, menu, sidebar, and access matrix governance.
- Centralized request/access enforcement in `public/includes/init.php` and `public/setting/helper/access_helper.php`.
- Central audit writer through `AuditLogger` and `audit_helper.php`.
- Notification framework for future scheduled report delivery.
- AI Chatbot core with provider abstraction, usage persistence, access filtering, and safe context principles.
- ApexCharts, DataTables, Bootstrap-style UI, SweetAlert, and Remix Icon frontend assets.

The reporting center must integrate with these existing systems instead of creating a separate security, database, or UI stack.

## Design Principles

### 1. Metadata First

Reports must be defined as metadata, not raw PHP pages and not unrestricted SQL strings.

The core flow is:

```text
Connection registry
-> Dataset
-> Dataset fields
-> Report definition
-> Runtime query builder
-> Result renderer
-> Export/dashboard/AI/schedule
```

### 2. Dataset As Security Boundary

Reports must not read database tables directly.

Reports read only from approved datasets. A dataset defines:

- Which connection can be used.
- Which table, view, or approved relationship source is allowed.
- Which fields are visible.
- Which fields can be filtered, grouped, sorted, or aggregated.
- Which groups can access the dataset.
- Which fields are sensitive or masked.
- Which business meaning and row meaning should guide report builders and future AI use.

### 3. No User-Supplied SQL In MVP

The first implementation must not allow report designers to save arbitrary SQL.

The framework should store report configuration, then generate SQL at runtime using a controlled query builder.

Raw SQL datasets may be considered later only for Super Admin, read-only connections, strict parameter binding, SQL linting, row limits, audit, and explicit security review.

### 4. Access Follows Existing Governance

Reporting access must align with existing active group/menu governance.

The reporting center should use:

- `tbl_m_group` for group identity.
- Existing module/menu registration for page visibility.
- `is_user_super_admin()` for Super Admin decisions.
- `prestasi_user_can_access_page_path()` or equivalent access helpers where page/menu access matters.
- Active group context from session for group-scoped access.

### 5. Audit Is Mandatory

Report run, export, dataset changes, report changes, access denial, AI report generation, and schedule delivery must be auditable.

Audit must use existing audit helpers where possible. Reporting-specific tables may store operational details, but central audit remains the primary system trail.

### 6. AI Is Not A Database Interface

AI must never execute SQL, write data, bypass dataset access, or inspect database schemas directly.

AI may later generate a report configuration from approved dataset metadata. The framework then validates the configuration and generates SQL.

### 7. Failure Must Stay Local To The Feature

Additional database failure, report query timeout, export failure, or schedule delivery failure must not break login, dashboard, or system bootstrap.

### 8. Dataset Changes Must Be Governed

A dataset is a contract used by reports, widgets, exports, schedules, and future AI reporting.

Dataset changes must support:

- Version tracking.
- Schema hash comparison.
- Schema change detection.
- Dependency analysis before risky changes.
- Audit for scan, preview, field, access, and status changes.

Risky changes include disabling a dataset, archiving a dataset, removing a field, changing a field alias, changing data type, changing source object, changing join metadata, or changing masking/export policy.

## Proposed Core Surfaces

### Pages

Recommended pages:

```text
public/pages/reporting-center.php
public/pages/report-datasets.php
public/pages/report-builder.php
public/pages/report-viewer.php
public/pages/report-dashboard-widgets.php
public/pages/report-schedules.php
```

Initial MVP should create fewer pages:

```text
public/pages/reporting-center.php
public/pages/report-datasets.php
public/pages/report-builder.php
```

### Controllers

Recommended controller layout:

```text
public/controllers/ReportingCenterController.php
public/controllers/ReportDatasetController.php
public/controllers/ReportBuilderController.php
public/controllers/ReportScheduleController.php
```

### Classes And Services

Recommended MVP services:

```text
public/classes/ReportConnectionService.php
public/classes/ReportDatasetRepository.php
public/classes/ReportDatasetService.php
public/classes/ReportDatasetVersionService.php
public/classes/ReportDependencyService.php
public/classes/ReportDefinitionRepository.php
public/classes/ReportDefinitionService.php
public/classes/ReportQueryBuilder.php
public/classes/ReportQueryDialect.php
public/classes/ReportExecutionService.php
public/classes/ReportExportService.php
public/classes/ReportAuditService.php
```

Recommended future services:

```text
public/classes/ReportAiMetadataService.php
public/classes/ReportScheduleService.php
```

### AJAX Endpoints

Recommended endpoint groups:

```text
public/ajax/report-dataset-list.php
public/ajax/report-dataset-save.php
public/ajax/report-dataset-fields.php
public/ajax/report-dataset-inspect.php
public/ajax/report-dataset-preview.php
public/ajax/report-dataset-health.php
public/ajax/report-dataset-dependencies.php
public/ajax/report-builder-save.php
public/ajax/report-builder-preview.php
public/ajax/report-run.php
public/ajax/report-export.php
public/ajax/report-widget-preview.php
public/ajax/report-schedule-save.php
```

Every write endpoint must require login, CSRF validation, access checks, and audit.

## Database Model

All reporting metadata should live in the main MySQL application database.

### Dataset Source Types

Supported source types should be explicit.

```text
table
view
relationship
```

MVP implementation should support `table` and `view` first.

`relationship` should be designed in the schema early but implemented after the initial query builder is stable. Relationship datasets allow low-code joins without exposing SQL to report designers.

Relationship dataset metadata should support:

- Parent source.
- Child source.
- Join type.
- Join condition.
- Source aliases.
- Published fields.
- Dependency and health checks.

Relationship datasets must still be metadata-driven. Browser payloads must not provide raw join SQL.

### Core Tables

#### `tbl_report_dataset`

Stores approved reporting datasets.

Recommended fields:

```text
id
dataset_code
name
description
connection_code
source_type
source_schema
source_name
source_alias
primary_key_hint
business_meaning
row_meaning
schema_hash
last_schema_hash
dataset_version
schema_changed_at
schema_changed_status
last_scan_at
last_scan_status
last_scan_message
last_connection_status
field_count
row_limit_default
row_limit_max
is_cacheable
cache_ttl_seconds
status
created_by
created_at
updated_by
updated_at
```

Notes:

- `connection_code` should map to `mysql_main`, `sybase_staff`, `sybase_student`, or `dbx_*`.
- `source_type` should start with `table` and `view`; `relationship` is future-ready but not required for MVP execution.
- `status` should support `draft`, `active`, `disabled`, and `archived`.
- Dataset code should be stable and machine-safe.
- `schema_hash` should represent the current approved dataset contract.
- `last_schema_hash` can be used to detect changes after scans.
- `dataset_version` should increment when the approved dataset contract changes.
- `schema_changed_status` should support values such as `none`, `detected`, `reviewed`, and `accepted`.
- Health fields should be updated by scan/preview/health checks without exposing connection secrets.

#### `tbl_report_dataset_field`

Stores visible/reportable fields for a dataset.

Recommended fields:

```text
id
dataset_id
source_column
field_alias
field_label_ms
field_label_en
field_description
data_type
semantic_type
business_rule_note
is_visible
is_filterable
is_sortable
is_groupable
is_aggregatable
allowed_operators_json
allowed_aggregates_json
masking_policy
sort_order
status
created_at
updated_at
```

Notes:

- Report definitions should refer to `field_alias`, not raw source column name.
- `masking_policy` should support values such as `none`, `partial`, `hidden`, and `aggregate_only`.
- Field labels should follow the core/custom language direction where UI strings later need translation.
- `field_description` and `business_rule_note` provide business context for report designers and future AI report generation.
- Example values should not be stored by default because samples may contain sensitive data.

#### `tbl_report_dataset_source`

Future-ready table for relationship datasets.

Recommended fields:

```text
id
dataset_id
source_key
connection_code
source_schema
source_name
source_alias
source_type
sort_order
status
created_at
updated_at
```

Notes:

- MVP can skip this table if only single table/view datasets are implemented.
- If introduced early, single-source datasets may also use this table for consistency.

#### `tbl_report_dataset_join`

Future-ready table for relationship dataset joins.

Recommended fields:

```text
id
dataset_id
parent_source_key
child_source_key
join_type
parent_field
child_field
join_order
status
created_at
updated_at
```

Notes:

- `join_type` should be limited to approved values such as `inner`, `left`, and possibly `right` later.
- Join fields must reference approved source metadata, not raw browser-supplied SQL.
- Complex custom join expressions should be deferred until a separate security review.

#### `tbl_report_dataset_access`

Stores group access to datasets.

Recommended fields:

```text
id
dataset_id
group_id
can_view
can_design
can_export
can_manage
created_at
updated_at
```

Notes:

- Super Admin can bypass for administration, but runtime access should still be audited.
- Normal users should see only datasets allowed to their active group.

#### `tbl_report`

Stores saved report definitions.

Recommended fields:

```text
id
report_code
dataset_id
report_name
description
owner_type
owner_id
visibility
status
chart_type
config_json
dataset_version
dataset_schema_hash
created_by
created_at
updated_by
updated_at
```

Notes:

- `config_json` stores columns, filters, sorting, grouping, aggregates, chart options, and default limits.
- `visibility` should support `private`, `group`, and `shared`.
- `status` should support `draft`, `active`, `disabled`, and `archived`.
- `dataset_version` and `dataset_schema_hash` should capture the dataset contract used when the report was created or last validated.
- Reports whose stored hash differs from the current dataset hash should be marked stale or require validation before execution, depending on the severity of the dataset change.

#### `tbl_report_access`

Stores report-level group access when the report is shared.

Recommended fields:

```text
id
report_id
group_id
can_view
can_export
can_edit
created_at
updated_at
```

#### `tbl_report_run_log`

Stores report runtime history for operational review.

Recommended fields:

```text
id
report_id
dataset_id
connection_code
actor_login_id
actor_user_id
active_group_id
run_type
filters_hash
row_count
duration_ms
status
error_code
created_at
```

Notes:

- Do not store raw full result sets in this table.
- Store summarized metadata only.
- Use central `audit_event` for system audit, this table for reporting operations.

#### `tbl_report_export_log`

Stores export details.

Recommended fields:

```text
id
report_id
run_log_id
export_format
actor_login_id
actor_user_id
active_group_id
row_count
status
file_name
file_size_bytes
created_at
```

#### `tbl_report_dataset_scan_log`

Stores dataset scan, preview, and health check history.

Recommended fields:

```text
id
dataset_id
scan_type
connection_code
schema_hash
field_count
status
message
duration_ms
actor_login_id
active_group_id
created_at
```

Notes:

- `scan_type` may include `inspect`, `preview`, `health_check`, and `schema_rescan`.
- Do not store raw preview rows in this table.
- Store only operational metadata and safe error summaries.

### Future Tables

#### `tbl_report_dashboard_widget`

For dashboard widgets based on reports.

#### `tbl_report_ai_synonym`

For AI field synonyms.

#### `tbl_report_schedule`

For scheduled report delivery.

#### `tbl_report_schedule_delivery_log`

For delivery status, retries, and failure audit.

#### `tbl_report_dataset_version`

Optional future table for full dataset version history.

MVP can store only the current `dataset_version`, `schema_hash`, and central audit changes. A full version history table can be introduced when rollback or historical comparison becomes a requirement.

## Runtime Query Builder

The query builder must generate SQL only from approved metadata.

Allowed inputs:

- Dataset ID.
- Field aliases from `tbl_report_dataset_field`.
- Operators from the field allowed operator list.
- Sort/group/aggregate choices allowed by field metadata.
- Bound filter values from request payload.
- Runtime row limit.

Blocked inputs:

- Raw table names from browser payload.
- Raw column names from browser payload.
- Raw SQL expressions from normal users.
- Raw WHERE snippets.
- Raw ORDER BY snippets.

### Dialect Layer

Because IQS supports MySQL, Sybase, MSSQL, ODBC, and DBLIB variants, the report query builder needs a dialect layer.

Minimum dialect methods:

```text
quoteIdentifier(identifier)
limitSelect(sql, limit, offset)
buildDateCast(column, dataType)
buildLikeExpression(column)
supportsOffset()
```

Initial dialect targets:

- MySQL.
- Sybase/MSSQL-style `TOP`.
- Generic fallback for PDO connections where only simple SELECT is supported.

Relationship dataset SQL generation should not be part of the first query builder unless the single-source query builder has already been validated against the target database drivers.

## Dataset Preview And Health

Dataset management should include a safe preview flow.

Super Admin, and later an approved Report Administrator, should be able to:

- Preview dataset metadata.
- Preview up to 20 rows.
- Preview generated field mappings.
- Re-scan dataset schema.
- View dataset health status.

Preview rules:

- Read-only only.
- Super Admin only in Phase 1.
- Capped at 20 rows.
- No export from dataset preview.
- No raw credentials in UI or logs.
- Audit every preview and scan.
- Use existing connection runtime and fail locally to the reporting feature.

Health metadata should help administrators find broken datasets before users encounter failures.

Health status should track:

- Last scan time.
- Last scan status.
- Last connection status.
- Field count.
- Schema hash.
- Schema change status.
- Last safe error summary.

## Dataset Version And Dependency Governance

Dataset version tracking should be part of the first metadata design.

Minimum MVP behavior:

- Compute `schema_hash` from approved dataset source, field aliases, source columns, data types, visibility, capabilities, masking policy, and relationship metadata where applicable.
- Increment `dataset_version` when the approved dataset contract changes.
- Store current dataset version/hash on saved reports.
- Detect when report definitions were created against an older dataset contract.
- Audit schema scan and contract changes.

Dependency analysis should be available before risky dataset changes.

The dependency service should identify:

- Reports using a dataset.
- Dashboard widgets using reports from that dataset.
- Schedules using reports from that dataset.

MVP dependency checks should cover reports once report definitions exist. Widget and schedule checks become active when those features are implemented.

Recommended behavior:

- Do not hard-delete datasets used by reports.
- Prefer `disabled` or `archived` status.
- Block or warn before removing fields used by reports.
- Require confirmation before changing field aliases used by reports.
- Mark affected reports as stale if the dataset contract changes.
- Audit dependency warnings and administrative override decisions.

## Report Execution Rules

Report execution must:

1. Require authenticated session.
2. Resolve current profile and active group.
3. Confirm page/menu access where applicable.
4. Confirm dataset access.
5. Confirm report access.
6. Validate report config against dataset field metadata.
7. Bind all filter values as parameters.
8. Apply row limit.
9. Apply timeout where possible.
10. Execute against resolved PDO connection.
11. Return capped result payload.
12. Log run metadata.
13. Record central audit event.

Default row limits should be conservative:

```text
preview: 100 rows
run: 1000 rows
export: configurable, default 5000 rows
```

Any higher export limit should require explicit admin configuration and audit.

## Export Strategy

### MVP Export

V1 should support:

- CSV
- Print-friendly HTML

### Later Export

V2 or V3 may support:

- XLSX after selecting a PHP spreadsheet library.
- PDF after selecting a supported HTML-to-PDF or PDF generation strategy.

Export requirements:

- Require `can_export`.
- Re-run report or use a short-lived server-side result token, not browser-submitted result rows.
- Log export metadata.
- Avoid storing exported files unless a retention policy is defined.
- If files are stored, they must be outside public web access or protected by authenticated download endpoint.

## Dashboard Widgets

Dashboard widgets should reuse saved reports.

Do not create separate widget SQL.

Widget config should store:

```text
report_id
widget_title
widget_type
chart_type
refresh_interval_seconds
layout_json
status
```

Widget rendering must still enforce report and dataset access for the current active group.

## Scheduled Reports

Scheduled reports should not be part of MVP.

They require:

- CLI-safe runner.
- Cron or task scheduler setup.
- Schedule ownership and group scope.
- Delivery method.
- Retry policy.
- Delivery audit.
- Email/notification integration.
- Export size limits.

Recommended schedule fields:

```text
id
report_id
schedule_type
cron_expression
timezone
export_format
delivery_method
recipient_mode
recipient_config_json
status
last_run_at
next_run_at
created_by
created_at
updated_at
```

Delivery methods should start with:

- Notification Center.
- Email.

## AI Reporting Direction

AI reporting should build on the existing AI Chatbot governance model.

AI should receive only:

- Dataset names.
- Dataset descriptions.
- Dataset business meaning and row meaning.
- Approved field labels.
- Approved field descriptions.
- Approved field synonyms.
- Approved field business rule notes.
- Field capabilities such as filterable, groupable, aggregatable.
- User question.
- Current access context.

AI must not receive:

- DSN, username, password, API keys, session IDs, cookies, CSRF tokens.
- Raw full schema for unapproved datasets.
- Hidden fields.
- Raw unrestricted database rows.
- SQL execution permission.

Expected AI flow:

```text
User asks in Bahasa Melayu
-> AI receives approved dataset metadata only
-> AI returns structured report config JSON
-> Framework validates config against dataset metadata
-> Framework generates SQL
-> Framework runs report
-> Result is shown
```

AI output must be treated as untrusted input.

### AI Business Context Layer

AI synonyms alone are not enough because generic field names such as `status`, `type`, `code`, or `category` can mean different things in different business domains.

Dataset metadata should therefore include business context:

```text
dataset name
dataset description
business meaning
row meaning
field label
field description
field business rule note
field synonyms
```

This context should be used only after access filtering. The AI provider must receive only metadata for datasets and fields visible to the current user/group.

## Security Requirements

### Authentication And CSRF

- All pages must include `init.php`.
- All AJAX endpoints must require authenticated session.
- All mutation endpoints must validate CSRF.
- Preview/run/export endpoints should also validate CSRF because they can expose data.

### Authorization

Authorization must check:

- Current page/menu access.
- Active group.
- Dataset access.
- Report access.
- Export permission.
- Super Admin override only where intended.

### Data Protection

- Dataset fields default to hidden until explicitly enabled.
- Sensitive fields should support masking or aggregate-only exposure.
- Export should be disabled by default for sensitive datasets.
- Data preview during dataset design should be capped and Super Admin only.
- No credentials or secret config should be exposed in UI or audit metadata.

### Query Safety

- No raw SQL from browser.
- No unbound parameters.
- No dynamic identifiers unless they come from stored metadata and are safely quoted.
- No multi-statement execution.
- No update, insert, delete, drop, alter, truncate, or procedure execution.
- Runtime should enforce read-only behavior at application level.

### Audit

Minimum audit events:

```text
REPORT_DATASET_CREATED
REPORT_DATASET_UPDATED
REPORT_DATASET_DISABLED
REPORT_DATASET_PREVIEWED
REPORT_DATASET_SCANNED
REPORT_DATASET_SCHEMA_CHANGED
REPORT_DATASET_DEPENDENCY_WARNING
REPORT_DATASET_FIELD_UPDATED
REPORT_CREATED
REPORT_UPDATED
REPORT_STALE_DATASET_DETECTED
REPORT_RUN
REPORT_EXPORT
REPORT_ACCESS_DENIED
REPORT_AI_CONFIG_GENERATED
REPORT_SCHEDULE_CREATED
REPORT_SCHEDULE_DELIVERED
REPORT_SCHEDULE_FAILED
```

If the current `audit_event.event_type` column is enum-limited in a deployment, the audit helper already has fallback behavior. The original event type should remain in audit metadata.

## UI Direction

The reporting UI should match existing admin surfaces:

- Bootstrap layout.
- DataTables for tabular lists.
- ApexCharts for charts.
- SweetAlert for confirmation and feedback.
- Remix Icon for action buttons.
- Local loading states instead of global full-page loader for normal in-page actions.

Recommended first navigation:

```text
Analytics & Reports
- Reporting Center
- Datasets
- Report Builder
```

The menu entries must be registered through existing group/module/menu governance, not hardcoded in sidebar includes.

## Authorization Model

Phase 1 dataset management should be Super Admin only.

The architecture should still avoid hard-coding reporting administration permanently to Super Admin. A future Report Administrator capability should be possible through existing group/menu governance and dataset/report permissions.

Future Report Administrator capabilities may include:

- Manage datasets assigned to allowed groups.
- Manage reports.
- Manage widgets.
- View dataset health.
- Run dependency checks.

This role should not automatically inherit full System Settings, user management, group management, database credential management, or audit center privileges.

## Development Phases

### Phase 0 - Blueprint And Decisions

Status: Current document.

Deliverables:

- Confirm scope.
- Confirm MVP boundaries.
- Confirm security baseline.
- Confirm schema direction.
- Confirm implementation order.

No runtime code or migration is introduced in this phase.

### Phase 1 - Dataset Engine MVP

Deliverables:

- Migration for dataset, dataset fields, and dataset access tables.
- Dataset version/hash fields.
- Dataset health fields.
- Dataset scan log.
- Dataset repository/service.
- Dataset version service.
- Dataset dependency service skeleton.
- Dataset list page.
- Dataset create/edit UI.
- Connection selection from existing database registry.
- Schema inspect flow using existing connection runtime.
- Dataset preview capped to 20 rows.
- Dataset health/status display.
- Schema hash and schema change detection.
- Field publish/unpublish controls.
- Group access controls.
- Audit dataset create/update/access changes.
- Audit dataset preview, scan, and schema change detection.

Acceptance criteria:

- Super Admin can create a dataset from approved connection/source.
- Normal users cannot see datasets without access.
- Dataset fields are explicit and default safe.
- Dataset preview is capped, read-only, and audited.
- Dataset schema hash is stored and schema changes can be detected.
- No report execution yet except capped design-time preview.

### Phase 2 - Report Builder And Execution MVP

Deliverables:

- Report tables.
- Report builder page.
- Runtime config validator.
- Query builder with MySQL and Sybase/MSSQL basics.
- Report storage of dataset version/hash.
- Dependency checks before risky dataset field changes.
- Report preview and run endpoint.
- Table result renderer.
- CSV export.
- Run/export audit.

Acceptance criteria:

- Report designer can create a report from a dataset without writing SQL.
- User can run only reports they are allowed to access.
- SQL is generated from metadata only.
- Filters are parameter-bound.
- Row limits are enforced.
- CSV export is audited.
- Reports can be marked stale when their dataset contract changes.

### Phase 3 - Charts And Dashboard Widgets

Deliverables:

- Chart configuration on report.
- ApexCharts rendering.
- Widget metadata table.
- Dashboard widget rendering service.
- Access enforcement per widget.
- Dataset dependency analysis includes widgets.

Acceptance criteria:

- A saved report can render as table or chart.
- A widget uses the same report definition.
- Dashboard does not bypass report/dataset access.

### Phase 4 - Export Expansion

Deliverables:

- XLSX support after library decision.
- PDF support after library decision.
- Export retention policy if files are stored.
- Download endpoint if retained files are needed.

Acceptance criteria:

- Export remains access-checked and audited.
- Sensitive datasets can block export or mask fields.
- Export library errors do not break report run.

### Phase 5 - AI Metadata And AI Report Builder

Deliverables:

- AI synonym table.
- AI business context metadata usage.
- AI metadata service.
- AI report config generation flow.
- Validation of AI output against dataset metadata.
- Audit AI report generation.

Acceptance criteria:

- AI produces report config, not SQL.
- Framework validates config before execution.
- AI sees only approved metadata for datasets accessible to current group.

### Phase 6 - Scheduled Reports

Deliverables:

- Schedule table.
- CLI runner.
- Delivery log.
- Notification/email delivery integration.
- Retry/failure handling.
- Schedule management UI.
- Dataset dependency analysis includes schedules.

Acceptance criteria:

- Scheduled report runs are access-scoped to the schedule owner/group.
- Delivery is audited.
- Failed schedules do not block normal application runtime.

### Phase 7 - AI Data Analyst And Advanced Analytics

Deliverables:

- AI result-summary service.
- Trend and comparison helpers.
- Forecasting research/prototype only after governance review.

Acceptance criteria:

- AI analyzes only report results already visible to the user.
- Result payloads sent to AI are capped and redacted where needed.

## MVP Recommendation

The first implementation should include only:

- Dataset Engine.
- Dataset access.
- Dataset preview.
- Dataset health metadata.
- Dataset version/hash tracking.
- Report Builder.
- Report Execution.
- Table view.
- CSV export.
- Run/export audit.

The first implementation should not include:

- AI report builder.
- AI data analyst.
- Scheduled reports.
- XLSX/PDF export.
- Raw SQL report designer.
- Public unauthenticated reporting.
- Global dashboard widgets.

## Implementation Risks

### SQL Dialect Complexity

MySQL, Sybase, MSSQL, ODBC, and DBLIB differ in identifier quoting, limits, date operations, and pagination. Keep V1 SQL simple.

### Data Leakage

Datasets can expose sensitive data if field publishing is too automatic. Default all scanned fields to hidden.

### Performance

Reports can become expensive. Enforce row limits, require indexed filters for large datasets where possible, and log duration.

### Export Size

Large exports can exhaust memory. CSV should stream later if needed. Initial implementation can cap rows strictly.

### Access Confusion

Dataset access and report access are separate. UI must make this clear to administrators.

### Dataset Contract Drift

Database tables, views, or external sources can change after a dataset is published. Version tracking and schema hash checks reduce the risk of silently breaking existing reports.

### Relationship Dataset Complexity

Relationship datasets improve long-term maintainability, but they increase query builder complexity. The schema should be future-ready, while full relationship execution should be deferred until single-source reporting is stable.

## Open Decisions

- Should dataset management be Super Admin only, or allow a Report Admin group?
- What is the default module/menu name in sidebar: `Analytics & Reports` or `Reporting Center`?
- Should V1 allow Sybase datasets, or start with MySQL/additional MySQL only?
- Should relationship dataset schema tables be created in the first migration or deferred until relationship execution starts?
- What is the first production report use case for validation?
- Which PHP library should be approved for XLSX/PDF later?

## Current Recommendation

Proceed in this order:

1. Implement Dataset Engine.
2. Implement dataset preview, health checks, version tracking, and dependency checks.
3. Implement metadata-only Report Builder.
4. Implement audited report run and CSV export.
5. Add chart/dashboard widgets.
6. Add AI metadata and AI report builder.
7. Add scheduled delivery.

This keeps the core reporting platform secure, reusable, and compatible with existing IQS-Framework governance.
