# Sybase Multi-Domain Phase 1 Baseline

## Objective

Freeze the current state of the Sybase multi-domain work before moving into deeper refactors.

This phase does not change runtime behavior. It records the current baseline, affected files, and the verification checklist that must pass before Phase 2 starts.

## Current Architecture State

- MySQL remains the main application database.
- Sybase is now modeled by domain:
  - staff
  - student
- Runtime selection is now based on:
  - environment
    - production
    - development
  - operational mode
    - staff_only
    - staff_student

## Foundations Already Present

- Student production and development connection config exist in:
  - `app/configuration/db_config.php`
- Resolver helpers exist in:
  - `app/includes/functions-db.php`
- Database convenience methods exist in:
  - `app/classes/Database.php`
- Runtime constants and preference handling exist in:
  - `app/includes/init.php`
- Database settings UI has been redesigned in:
  - `app/pages/tetapan-sistem.php`
  - `app/controllers/TetapanSistemController.php`
- Student verification utility exists in:
  - `app/pages/pelajar-test.php`
- Reusable student search endpoint exists in:
  - `app/ajax/user-search-pelajar.php`

## Key Files In Scope

### Core runtime and configuration

- `app/classes/Config.php`
- `app/classes/Database.php`
- `app/classes/SystemConfigConstants.php`
- `app/configuration/db_config.php`
- `app/configuration/config_db_active.json`
- `app/includes/functions-db.php`
- `app/includes/init.php`

### Settings UI and controller

- `app/pages/tetapan-sistem.php`
- `app/controllers/TetapanSistemController.php`
- `app/lang/ms.php`
- `app/lang/en.php`

### Staff flow callers already touched

- `app/controllers/DashboardController.php`
- `app/controllers/UserListController.php`
- `app/ajax/user-search-staf.php`
- `app/ajax/user-list-staf-options.php`
- `app/ajax/user-add.php`

### Student flow foundation

- `app/ajax/user-search-pelajar.php`
- `app/pages/pelajar-test.php`

## What Must Be Verified Before Phase 2

### Runtime combinations

Verify these combinations in the system configuration:

1. Production + Staff Only
2. Production + Staff + Student
3. Development + Staff Only
4. Development + Staff + Student

### Staff flow checks

- Dashboard still loads normally.
- `Senarai Pengguna` still loads staff data.
- Staff sync still works.
- Add user from staff lookup still works.

### Student flow checks

- `pelajar-test.php` is blocked when operational mode is `staff_only`.
- `pelajar-test.php` loads when operational mode is `staff_student`.
- Student search works by:
  - matrik
  - nama
  - fakulti
- Student search works in both production and development environments.

## Known Legacy That Still Exists

These items still exist and are expected at the end of Phase 1:

- `SYBASE_ACTIVE_BASE`
- `Database::pdoSybaseActive()`
- `get_active_sybase_pdo()`
- `config_db_active.json`
- legacy `ehrmdb` and `ehrmdb_dev` compatibility mapping

They are not removed in Phase 1. They will be addressed in later phases.

## Exit Criteria For Phase 1

Phase 1 is considered complete when:

- the verification checklist above passes
- no regression is seen in staff flows
- student test flow works in `staff_student` mode
- no additional architecture changes are introduced before review

## Next Phase

Phase 2 will focus on stabilizing staff flows further by reducing reliance on `pdoSybaseActive()` and binding staff callers more explicitly to the staff resolver.
