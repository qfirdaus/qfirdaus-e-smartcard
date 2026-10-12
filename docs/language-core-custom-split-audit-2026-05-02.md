# Language Core/Custom Split Audit

Date: 2026-05-02  
Scope: Phase 1 audit and implementation roadmap for separating framework-owned language files from project-owned language overrides.

## Objective

The framework is shared across multiple downstream systems. Current translation files are stored directly under:

```text
public/lang/ms.php
public/lang/en.php
```

This creates a deployment risk because project developers add custom translations into the same files that the core framework update process can later overwrite.

The target architecture is:

- core translations remain framework-owned and safe to update,
- project translations remain project-owned and protected from framework sync,
- project translations can override core translations,
- the existing `__()` usage stays backward compatible,
- deployment scripts avoid overwriting project custom language files.

## Phase Plan

### Phase 1: Audit and Baseline

Status: in progress in this document.

Deliverables:

- identify all current translation entry points,
- identify all direct language-file reads,
- identify deployment paths that can overwrite language files,
- define required implementation surface for later phases.

### Phase 2: Folder Structure and Central Loader

Status: completed.

Implemented structure:

```text
public/lang/
  core/
    ms.php
    en.php
  custom/
    ms.php
    en.php
```

The loader should read `core/{lang}.php` first and then `custom/{lang}.php`, with custom keys overriding core keys.

Backward compatibility target:

```php
__('login_title')
lang_exists('login_title')
get_all_lang_lines()
tr('login_title', 'Fallback')
```

All existing calls should continue to work.

### Phase 3: Runtime Integration

Status: completed.

Update all code paths that currently read `public/lang/{lang}.php` directly so they use the same merged language source:

- PHP helper translation,
- bootstrap JS translation whitelist,
- system settings full JS translation bundle,
- template/page generator language writes.

### Phase 4: Deployment Protection

Status: completed.

Update sync/update scripts so framework updates can include `public/lang/core/*` but must not overwrite `public/lang/custom/*`.

Deployment scripts should explicitly exclude custom language files from update collection and sync.

### Phase 5: Migration and Validation

Status: completed.

Add a safe migration and validation workflow:

- move current core files to `public/lang/core/`,
- create project-level `public/lang/custom/` files,
- detect missing keys across active languages,
- detect duplicate override keys,
- detect custom keys that no longer exist in core,
- produce a report suitable for multi-project rollout.

## Current Baseline

### Language Files

Current language files:

```text
public/lang/ms.php
public/lang/en.php
```

Current key counts:

```text
ms_keys=1920
en_keys=1920
missing_en_from_ms=0
missing_ms_from_en=0
```

This means the existing Malay and English language files are balanced at the key level.

### Translation Usage

Current application usage found:

```text
106 files contain calls to __()
1819 total __() call occurrences
```

This confirms backward compatibility is mandatory. Requiring developers to change existing `__()` calls would create too much regression risk.

### Primary PHP Loader

File:

```text
public/setting/helper/lang_helper.php
```

Current behavior:

```php
$file = __DIR__ . '/../../lang/' . $lang . '.php';
```

Functions affected:

- `__($key)`
- `lang_exists($key)`
- `get_all_lang_lines()`
- `tr($key, $fallback)`

Risk:

The helper currently supports only one language file per language. There is no concept of framework core language and project override language.

### Bootstrap JS Translation Loader

File:

```text
public/includes/init.php
```

Current behavior:

```php
$langFile = __DIR__ . "/../lang/{$lang}.php";
$all = file_exists($langFile) ? (require $langFile) : [];
```

Risk:

Even if `__()` is updated, JS translations loaded during bootstrap would still bypass the new merged loader unless this code is updated.

### System Settings JS Translation Bundle

File:

```text
public/pages/tetapan-sistem.php
```

Current behavior:

```php
$langFileForJs = __DIR__ . "/../lang/{$lang}.php";
$bundleFile = __DIR__ . "/../lang/{$translationLangCode}.php";
```

Risk:

The system settings page builds a full runtime translation bundle for active languages. It currently reads language files directly and would not see custom overrides after a helper-only change.

### Template/Page Generator Language Writes

File:

```text
public/classes/FileGenerationService.php
```

Current behavior:

```php
private function getLanguageFilePaths(): array
{
    return [
        'ms' => $this->projectRoot . '/lang/ms.php',
        'en' => $this->projectRoot . '/lang/en.php',
    ];
}
```

Risk:

Generated template/page language entries are appended directly to the current language files. In the new model, generated project-specific keys should be written to custom language files, not core files.

### Active Language Configuration

Files:

```text
public/classes/Config.php
public/controllers/TetapanSistemController.php
public/includes/init.php
```

Current behavior:

- supported language constants are defined in `SystemConfigConstants`,
- active/default languages are persisted in `tbl_m_config`,
- user preference may update `$_SESSION['lang']` and `tbl_m_user.f_lang`.

Risk:

The core/custom split should not change how current language selection works. It should only change where the translation lines are loaded from.

### Deployment Scripts

Files:

```text
update-files.sh
sync-updates.sh
```

Current `update-files.sh` behavior:

```bash
find public -type f -newer "$MARKER" \
! -path "public/cache/*" \
! -path "public/log/*" \
! -path "public/uploads/*"
```

Current `sync-updates.sh` behavior:

```bash
mapfile -t FILES < <(find updates -type f)
```

Risk:

Both scripts can include and distribute language files. A core update can overwrite downstream project translations if project custom files are not explicitly excluded.

## Recommended Architecture

### Folder Layout

```text
public/lang/
  core/
    ms.php
    en.php
  custom/
    ms.php
    en.php
```

Optional examples for project setup:

```text
public/lang/custom/ms.example.php
public/lang/custom/en.example.php
```

### Load Order

For each language:

1. load core file,
2. load custom file if it exists,
3. merge custom over core,
4. return key name when translation is missing, matching current behavior.

Expected result:

```php
$lines = array_replace($coreLines, $customLines);
```

### Missing File Policy

Core file missing:

- should return an empty core array and log only if needed,
- should not crash public pages unless a future strict validation mode is added.

Custom file missing:

- should be treated as normal,
- should return an empty custom array.

### Encoding/Mojibake Handling

Current `fix_mojibake()` behavior should remain unless separately refactored. The language split does not require changing encoding behavior.

## Required Future Changes

### Phase 2 Required Changes

- Created `public/lang/core/`.
- Copied existing `public/lang/ms.php` and `public/lang/en.php` to core files.
- Created empty custom language files.
- Updated `public/setting/helper/lang_helper.php` to expose a central merged language loader.
- Kept `public/lang/ms.php` and `public/lang/en.php` as compatibility wrappers for direct includes until Phase 3 removes direct file-read bypasses.

### Phase 3 Required Changes

- Updated `public/includes/init.php` to use the central merged loader for `$translations_js`.
- Updated `public/pages/tetapan-sistem.php` to use the central merged loader for JS bundles.
- Updated `public/classes/FileGenerationService.php` so generated/project-specific translation keys are written to `public/lang/custom/{lang}.php`.
- Updated `public/classes/FileGenerationService.php` collision checks to read merged core+custom keys before writing new generated keys.
- Updated `public/classes/FileGenerationService.php` rollback behavior to remove generated language blocks from custom files only.

### Phase 4 Required Changes

- Updated `update-files.sh` to exclude `public/lang/custom/*`.
- Updated `sync-updates.sh` to skip `public/lang/custom/*` during git-based collect.
- Updated `sync-updates.sh` git-based collect to expand untracked public directories into individual files, so new core language directories can be collected without copying project custom language files.
- Updated `sync-updates.sh` git-based collect to include `tools/language-split-tool.php`, `VERSION`, `README.md`, and `CHANGELOG.md` as core release artifacts.
- Updated `sync-updates.sh` to exclude `updates/public/lang/custom/*` from the sync file list.
- Updated `sync-updates.sh` to skip `public/lang/custom/*` inside the per-project sync loop as a second safety check.
- Updated `update-files.sh` to collect `tools/language-split-tool.php`, `VERSION`, `README.md`, and `CHANGELOG.md` when those files are newer than the marker.
- Decide whether custom language files are ignored by Git or shipped as example placeholders.

### Phase 5 Required Changes

- Added validation/migration tooling at `tools/language-split-tool.php`.
- Added `validate` command to report core/custom counts, merged counts, override counts, custom-only counts, key parity, and duplicate literal keys.
- Added `migrate` command to create `public/lang/core/`, `public/lang/custom/`, compatibility wrappers, and optional backups for legacy single-file projects.
- Added `migrate --dry-run` mode for rollout preview.
- Validation report can be run before automated deployment.

Tool usage:

```bash
php tools/language-split-tool.php validate
php tools/language-split-tool.php validate --strict
php tools/language-split-tool.php migrate --dry-run
php tools/language-split-tool.php migrate
```

Current validation result after Phase 5:

```text
Languages: en, ms
en: core=1920 custom=0 merged=1920 overrides=0 custom_only=0
ms: core=1920 custom=0 merged=1920 overrides=0 custom_only=0
Result: 0 error(s), 7 warning(s)
```

Warnings found by the tool:

```text
public/lang/core/en.php: duplicate literal key 'dashboard_title' appears 2 times.
public/lang/core/en.php: duplicate literal key 'dashboard_breadcrumb' appears 2 times.
public/lang/core/ms.php: duplicate literal key 'dashboard_title' appears 2 times.
public/lang/core/ms.php: duplicate literal key 'dashboard_breadcrumb' appears 2 times.
public/lang/core/ms.php: duplicate literal key 'userGroup_undo_info' appears 2 times.
public/lang/core/ms.php: duplicate literal key 'userGroup_dt_info' appears 2 times.
public/lang/core/ms.php: duplicate literal key 'userGroup_dt_info_filtered' appears 2 times.
```

These are pre-existing duplicate language keys surfaced by validation. They are not fixed as part of the split architecture work.

## Acceptance Criteria

### Phase 1

- Documentation exists in `docs/`.
- Current translation entry points are identified.
- Direct file-read bypasses are identified.
- Deployment overwrite risk is identified.
- No runtime code changes are made.

### Phase 2

- Existing `__()` calls continue to work.
- Core/custom structure exists.
- Custom translation overrides core translation.
- Missing keys still return the key name.

### Phase 3

- PHP views, AJAX endpoints, bootstrap JS translations, and system settings JS bundles all use merged language data.
- Template/page generator writes new project language entries to custom language files.

### Phase 4

- Core update sync does not copy or overwrite `public/lang/custom/*`.
- Dry run makes custom language exclusions visible enough to verify.

### Phase 5

- Migration can be run safely before rollout.
- Missing and extra translation keys can be reported.
- Multi-project deployment can be validated before overwrite-prone sync.

## Phase 1 Conclusion

The requested solution is feasible in this project.

The implementation must be broader than only changing `__()`. At minimum, the following code paths must be coordinated:

- PHP translation helper,
- bootstrap JS translation setup,
- system settings translation bundle,
- template/page generator language writer,
- update/sync deployment scripts.

The highest-risk files are `public/lang/ms.php` and `public/lang/en.php` because they are currently both runtime translation sources and deployment artifacts. Splitting them into framework-owned `core` files and project-owned `custom` files is the correct production-level direction for this multi-project framework.
