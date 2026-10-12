# Core File Protection Standard

Date: 2026-06-04

## Purpose

IQS Framework is the core system used as the base for downstream projects. Core files must stay stable so framework updates can be distributed without being mixed with project-specific changes.

This standard defines how protected core files, generated project files, and custom project files should be marked.

## File Categories

### Protected Core Files

Protected core files are maintained by the framework maintainers only.

Programmers working on downstream projects may read these files for reference, but should not modify them directly in a project clone.

Examples:

- `public/pages/*.php` pages listed in the active framework page inventory
- framework controllers under `public/controllers/`
- framework services and models under `public/classes/`
- framework AJAX endpoints under `public/ajax/`
- bootstrap/includes under `public/includes/`
- core translations under `public/lang/core/`

### Project Generated Files

Project generated files are created from framework templates for downstream project features. These files are safe to customize inside the downstream project.

Generated files should use a separate header so they are not confused with protected core files.

### Project Custom Files

Project custom files are maintained by the downstream project team. They should live in project-specific extension areas where possible, such as:

- `public/lang/custom/`
- generated/custom project pages
- generated/custom project controllers
- generated/custom project classes
- page-specific project assets

## Protected Core Header

Use this header for protected core PHP files:

```php
/**
 * IQS FRAMEWORK CORE FILE
 *
 * READ ONLY for downstream project programmers.
 * Do not modify this file directly in template or cloned projects.
 * Custom changes must be implemented in project-specific files
 * or approved extension points.
 */
```

Place the header immediately after `<?php` and before `declare(strict_types=1);` where applicable.

## Generated Project Header

Use this header for files created by the page/template generator:

```php
/**
 * PROJECT GENERATED FILE
 *
 * Safe to customize for this downstream project.
 * Generated from an IQS Framework template.
 */
```

## Rules

1. Do not edit protected core files directly in downstream projects.
2. Add new project-specific behavior through generated/custom files or documented extension points.
3. Keep project language overrides in `public/lang/custom/`.
4. Keep framework language keys in `public/lang/core/`.
5. If a core behavior must change, update it in the framework source and distribute it through the normal update/sync process.
6. If a downstream project needs an exception, document the reason before changing a protected core file.

## Audit Tool

Run the protection audit before release or update collection:

```bash
php tools/core-file-protection-audit.php
php tools/core-file-protection-audit.php --strict
```

The tool checks active core pages, active core controllers, current framework AJAX endpoints, current framework classes/services, bootstrap/includes, setting helpers/constants, configuration, root entry files, and core language files for protection markers and reports marker conflicts.

## Release Checklist

Before collecting or syncing framework updates:

1. Run `php tools/core-file-protection-audit.php --strict`.
2. Run `php tools/language-split-tool.php validate` when language files changed.
3. Run `bash -n sync-updates.sh` and `bash -n update-files.sh` when update scripts changed.
4. Confirm `public/lang/custom/*` remains project-specific and is not added to update packages.
5. Confirm generated project files use `PROJECT GENERATED FILE`, not `IQS FRAMEWORK CORE FILE`.
6. Document any approved downstream exception before editing a protected core file.

## Current Phase

Phase 1-3 applied the protected core header to active framework pages and recorded a protected-file registry.

Phase 4-5 updated the page/template generator so generated project files receive the `PROJECT GENERATED FILE` marker and added the protection audit tool.

Phase 6 applied the protected core header to active framework controllers.

Phase 7 applied the protected core header to current framework AJAX endpoints and shared AJAX helpers.

Phase 8 applied the protected core header to current framework classes and services.

Phase 9 applied the protected core marker to bootstrap/includes, setting helpers/constants, configuration, root public entry files, root language wrappers, and core language files. `public/lang/custom/*` remains customizable.

Phase 10 finalized downstream governance guidance, release checklist coverage, and developer guide references.
