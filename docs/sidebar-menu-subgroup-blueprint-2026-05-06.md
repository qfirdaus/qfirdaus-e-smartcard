# Sidebar Menu Subgroup Blueprint

Date: 2026-05-06
Project: IQS Framework

## Objective

Sidebar navigation currently supports two levels only:

```text
Module
  - Menu
  - Menu
```

The target enhancement is an optional dynamic subgroup layer:

```text
Module
  - Direct menu
  - Subgroup
      - Menu
      - Menu
  - Direct menu
```

The subgroup layer must be managed through core framework data and UI. Project programmers should not edit `public/includes/sidebar.php` or hardcode sidebar grouping in project pages.

## Design Rules

- Subgroup is a presentation and organization layer only.
- Access control remains menu based through `tbl_m_group.f_menuAccess`.
- Existing menus remain valid without migration data because `tbl_m_menu.f_subgroupID` is nullable.
- Menus with `f_subgroupID IS NULL` are rendered directly under the module.
- Menus with `f_subgroupID` are rendered inside the matching subgroup under the same module.
- The sidebar must keep working before and after the subgroup schema is deployed.

## Phase Plan

### Phase 1: Schema And Data Layer

- Add `tbl_m_menu_subgroup`.
- Add nullable `tbl_m_menu.f_subgroupID`.
- Update the `Modul` data layer so menu rows can return optional subgroup metadata.
- Keep all queries backward-compatible when the schema has not been created yet.

### Phase 2: Sidebar Rendering

- Update `SidebarController.php` to structure menus into direct menus and subgroup menus.
- Update `sidebar.php` to render subgroup collapses inside a module.
- Ensure active menu opens its module and subgroup automatically.
- Ensure `sidebar-fragment.php` still refreshes the full sidebar correctly.

Phase 2 implementation status: completed.

Implementation notes:

- `sidebar.php` now groups menu rows by `f_subgroupID` at render time.
- Direct menus and subgroup blocks are sorted together by order, so a module can show direct menus before or after subgroup blocks.
- Subgroup blocks render as nested collapsible items using `side-nav-third-level`.
- Active state opens the parent module and the matching subgroup automatically.
- Sidebar collapse JavaScript now closes only sibling panels at the same level, preventing a subgroup toggle from closing its parent module.

### Phase 3: Management UI

- Add subgroup management to `kumpulan-pengguna.php`.
- Add create/edit/delete subgroup endpoints.
- Add optional subgroup selection to menu create/edit flows.
- Update menu list/get/save/create endpoints to read and write `f_subgroupID`.

Phase 3 implementation status: completed.

Implementation notes:

- `kumpulan-pengguna.php` now exposes subgroup management from the menu access modal.
- Menu create/edit now has an optional subgroup selector that reloads based on the selected parent module.
- Subgroup management supports module selection, code, Malay/English names, icon selection, order, status, edit, and delete.
- Subgroup delete is protected when the subgroup is still referenced by menu rows.
- New AJAX endpoints:
  - `public/ajax/menu-subgroup-list.php`
  - `public/ajax/menu-subgroup-save.php`
  - `public/ajax/menu-subgroup-delete.php`
- Existing menu endpoints now carry `f_subgroupID`:
  - `public/ajax/menu-list.php`
  - `public/ajax/menu-get.php`
  - `public/ajax/menu-create.php`
  - `public/ajax/menu-save.php`
  - `public/ajax/group-perms-get.php`
- Cache invalidation clears menu and sidebar navigation caches after subgroup changes.

### Phase 4: Hardening And Documentation

- Add audit logging for subgroup create/update/delete/reorder.
- Add language keys for subgroup UI labels.
- Add cache invalidation after subgroup or menu assignment changes.
- Update README, changelog, and programmer guidance.
- Test legacy menu behavior, subgroup behavior, active state, role switching, and sidebar refresh.

Phase 4 implementation status: completed.

Implementation notes:

- Subgroup create/update/delete actions now emit audit events with `target_type = menu_subgroup`.
- Subgroup update audit records field-level changes for module, code, names, icon, order, and status.
- Subgroup delete is a soft delete through `f_status = 0`; delete is denied if menus still reference the subgroup.
- Denied subgroup delete attempts are logged as `outcome = DENIED` and `severity = WARN`.
- Cache invalidation covers:
  - `menu_list_`
  - `group_perms_`
  - session/sidebar navigation cache via `clearSidebarNavigationCaches()`
- Programmer-facing behavior remains simple:
  - Leave `f_subgroupID` null for a normal module child menu.
  - Set `f_subgroupID` to an active subgroup under the same `f_modulID` when a menu should render inside a subgroup.
  - Access control still uses the existing group menu access list; subgroup does not add a new permission layer.

## Final Test Checklist

1. Legacy module with direct menus only:
   - Menus render exactly as before.
   - Active menu opens the parent module.

2. Module with direct menus and subgroup menus:
   - Direct menus render directly under the module.
   - Subgroup menus render inside the subgroup.
   - Direct menus and subgroup blocks follow configured order.

3. Active menu inside subgroup:
   - Parent module opens automatically.
   - Matching subgroup opens automatically.
   - Sibling subgroup toggles do not close the parent module unexpectedly.

4. Menu management:
   - Add/edit menu can leave subgroup as "No subgroup".
   - Add/edit menu can select a subgroup from the selected module.
   - Changing module reloads subgroup options.

5. Subgroup management:
   - Create subgroup.
   - Edit subgroup.
   - Soft-delete unused subgroup.
   - Delete is blocked when the subgroup is still used by menus.

6. Cache and refresh:
   - Sidebar reflects subgroup changes after refresh/navigation.
   - Group permission menu table reflects subgroup names.

7. Audit:
   - Create/update/delete subgroup appears in audit logs as `menu_subgroup`.
   - Denied delete attempt appears as a warning/denied audit event.

## Phase 1 SQL

Run this SQL once in the main MySQL application database before enabling subgroup management UI.

```sql
CREATE TABLE IF NOT EXISTS `tbl_m_menu_subgroup` (
  `f_subgroupID` INT NOT NULL AUTO_INCREMENT,
  `f_modulID` INT NOT NULL,
  `f_subgroupCode` VARCHAR(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `f_subgroupName_ms` VARCHAR(150) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `f_subgroupName_en` VARCHAR(150) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `f_icon` VARCHAR(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'ri-folder-2-line',
  `f_order` INT NOT NULL DEFAULT 1,
  `f_status` TINYINT(1) NOT NULL DEFAULT 1,
  `f_insertdt` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `f_insertby` VARCHAR(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `f_updatedt` DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  `f_updateby` VARCHAR(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`f_subgroupID`),
  UNIQUE KEY `uq_menu_subgroup_code` (`f_modulID`, `f_subgroupCode`),
  KEY `idx_menu_subgroup_module` (`f_modulID`, `f_status`, `f_order`),
  KEY `idx_menu_subgroup_status` (`f_status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE `tbl_m_menu`
  ADD COLUMN `f_subgroupID` INT NULL AFTER `f_modulID`;

CREATE INDEX `idx_menu_subgroup` ON `tbl_m_menu` (`f_subgroupID`);
CREATE INDEX `idx_menu_module_subgroup_order` ON `tbl_m_menu` (`f_modulID`, `f_subgroupID`, `f_order`);
```

Optional foreign key, only if the existing `tbl_m_menu` and `tbl_m_modul` constraints are already managed consistently in the target environment:

```sql
ALTER TABLE `tbl_m_menu_subgroup`
  ADD CONSTRAINT `fk_menu_subgroup_module`
  FOREIGN KEY (`f_modulID`) REFERENCES `tbl_m_modul` (`f_modulID`)
  ON UPDATE CASCADE
  ON DELETE RESTRICT;

ALTER TABLE `tbl_m_menu`
  ADD CONSTRAINT `fk_menu_subgroup_menu`
  FOREIGN KEY (`f_subgroupID`) REFERENCES `tbl_m_menu_subgroup` (`f_subgroupID`)
  ON UPDATE CASCADE
  ON DELETE SET NULL;
```

## Phase 1 Implementation Note

`public/classes/Modul.php` has been prepared to detect the optional subgroup schema at runtime:

- If `tbl_m_menu.f_subgroupID` or `tbl_m_menu_subgroup` does not exist, menu queries return the same direct-menu structure as before.
- If the schema exists, menu rows include:
  - `f_subgroupID`
  - `subgroupName`
  - `subgroupIcon`
  - `subgroupOrder`
  - `subgroupStatus`

Sidebar rendering is intentionally unchanged in Phase 1. The new metadata will be consumed in Phase 2.

## Phase 2 Implementation Note

`public/includes/sidebar.php` now consumes the Phase 1 subgroup metadata:

- `f_subgroupID` empty or invalid: menu stays as a direct module child.
- `f_subgroupID` with valid `subgroupName`: menu is rendered under that subgroup.
- Subgroup icon uses `subgroupIcon`, validated against the same sidebar icon rules.
- If a menu inside a subgroup is active, the subgroup and parent module are opened on page load.

No new permission layer is introduced in Phase 2. Access remains controlled by `tbl_m_group.f_menuAccess`.
