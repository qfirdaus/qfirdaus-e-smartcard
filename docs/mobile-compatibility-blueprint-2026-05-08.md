# Mobile Compatibility Blueprint

Date: 2026-05-08
Workspace: `/var/www/app\iqs-framework`

## Purpose

Blueprint ini menjadi rujukan untuk menaik taraf IQS-Framework supaya lebih serasi dengan mobile tanpa merombak struktur code web/desktop yang sudah berjalan.

Sasaran utama:

- Sistem boleh dicapai dengan selesa melalui mobile browser.
- Desktop behavior sedia ada kekal stabil.
- Perubahan dibuat secara additive, berfasa, dan mudah rollback.
- Page admin yang heavy-table masih boleh digunakan walaupun bukan pengalaman mobile-native sepenuhnya.

## Scope

In scope:

- Global layout mobile compatibility.
- Topbar, sidebar, page title, card spacing, tabs, modal, forms, DataTables, SweetAlert, and common action buttons.
- Page-by-page mobile pass untuk halaman aktif dalam `public/pages`.
- QA standard untuk viewport mobile/tablet/desktop.
- Developer rule supaya module baru ikut standard mobile.

Out of scope untuk fasa awal:

- Native mobile app.
- Full PWA/offline mode.
- Redesign total UI.
- Rewrite page PHP kepada SPA.
- Convert semua table kepada card view secara besar-besaran.
- Ubah business logic/backend flow.

## Current Audit Summary

IQS-Framework sudah mempunyai asas mobile daripada Bootstrap/admin template:

- `viewport` meta sudah wujud.
- Sidebar mobile behavior wujud melalui template sidenav.
- Topbar menyembunyikan beberapa control pada breakpoint kecil.
- Banyak page menggunakan Bootstrap grid.
- Banyak table sudah dibalut `.table-responsive`.
- Banyak modal sudah menggunakan `modal-dialog-scrollable`.

Namun sistem masih desktop-first pada banyak surface:

- Banyak DataTables menggunakan `responsive: false`.
- Banyak table hardcode `scrollX: false`.
- Banyak CSS menggunakan `white-space: nowrap` dan `flex-wrap: nowrap`.
- Modal besar banyak menggunakan `modal-xl` dan `modal-lg`.
- Tabs panjang boleh wrap dan memakan ruang mobile.
- Toolbar DataTables sering dipaksa dalam satu baris.
- Page admin heavy seperti user, group, settings, audit, and access matrix memerlukan mobile strategy khas.

## Guiding Principles

### 1. Additive First

Perubahan mobile dibuat dengan file tambahan atau override scoped media query.

Do:

- Tambah `mobile-compat.css`.
- Tambah `mobile-compat.js` jika perlu.
- Letak override di bawah breakpoint mobile sahaja.
- Kekalkan markup dan flow sedia ada semampu mungkin.

Do not:

- Edit `app.css` vendor/template secara terus.
- Rewrite DataTable markup tanpa sebab kuat.
- Tukar endpoint/backend untuk isu presentation.
- Buang class desktop sedia ada yang sedang digunakan.

### 2. Desktop Must Remain Stable

Semua perubahan mobile perlu dibungkus dengan breakpoint.

Recommended breakpoints:

```css
@media (max-width: 575.98px) {
  /* phone */
}

@media (max-width: 767.98px) {
  /* phone + small mobile landscape */
}

@media (min-width: 768px) and (max-width: 991.98px) {
  /* tablet */
}
```

### 3. Progressive Enhancement

Mulakan dengan global compatibility layer, kemudian polish page berat.

Jangan terus convert semua table kepada mobile card view. Itu lebih berisiko dan sukar maintain.

### 4. Table Data Remains Inspectable

Admin system biasanya data-heavy. Mobile compatibility bukan bermaksud semua table mesti jadi card.

Table strategy perlu ikut jenis data:

- Simple table: responsive collapse boleh digunakan.
- Data-heavy table: horizontal scroll yang dikemas mungkin lebih sesuai.
- High-use table: optional mobile card view boleh dipertimbangkan.

### 5. Actions Stay Reachable

Di mobile, action button lebih penting daripada column visual.

Rule:

- Action column tidak boleh hilang tanpa alternatif.
- Critical actions perlu kekal mudah ditekan.
- Minimum touch target disasarkan 40px hingga 44px.

## Proposed Files

### `public/assets/css/mobile-compat.css`

Purpose:

- Semua CSS override mobile global.
- Tidak menyentuh `app.css`.
- Dimuatkan selepas `custom.css` dan page CSS supaya boleh override layout mobile.

Initial responsibilities:

- Body/content overflow control.
- Page title stacking.
- Card/body spacing.
- Mobile tabs horizontal scroll.
- DataTables toolbar/pagination stacking.
- Modal mobile sizing.
- Button/action touch target.
- Topbar dropdown mobile width.
- View As banner mobile compact behavior.

### `public/assets/js/mobile-compat.js`

Purpose:

- JS UI-only enhancement untuk mobile behavior yang sukar dibuat dengan CSS sahaja.

Initial responsibilities:

- Add `is-mobile-viewport` class to `<html>` or `<body>`.
- Re-adjust DataTables columns after tab/modal open.
- Keep active horizontal tabs visible.
- Optional: normalize DataTables empty cell colspan where required.
- Optional: close sidebar backdrop cleanly after mobile navigation.

Important:

- JS ini tidak boleh mengubah data, permission, audit, session, or endpoint behavior.
- JS ini hanya presentation/helper.

## Global CSS Standards

### Body and Content

Problem:

- `body { overflow-x: hidden; }` wujud, tetapi element dalam page masih boleh overflow dan tersembunyi.

Recommended:

```css
@media (max-width: 767.98px) {
  .content-page {
    padding-left: 0.5rem;
    padding-right: 0.5rem;
  }

  .content-page .container-fluid {
    padding-left: 0.5rem;
    padding-right: 0.5rem;
  }

  .content-page .card {
    border-radius: 8px;
  }

  .content-page .card > .card-body {
    padding: 0.85rem;
  }
}
```

### Page Title and Actions

Problem:

- `page-title-box` biasanya flex row. Mobile boleh sempit bila actions banyak.

Recommended:

```css
@media (max-width: 767.98px) {
  .page-title-box {
    align-items: stretch !important;
    gap: 0.65rem;
  }

  .page-title-box .page-title {
    line-height: 1.25 !important;
    white-space: normal;
  }

  .page-title-box .page-title-right,
  .page-title-box .btn,
  .page-title-box .btn-group {
    max-width: 100%;
  }
}
```

### Touch Targets

Recommended:

```css
@media (max-width: 767.98px) {
  .btn:not(.btn-sm),
  .form-control,
  .form-select {
    min-height: 40px;
  }

  .btn-icon,
  .table .btn-sm {
    min-width: 36px;
    min-height: 36px;
  }
}
```

## Topbar and Sidebar

### Topbar

Current state:

- Topbar already hides some controls with Bootstrap responsive classes.
- Notification/language/profile dropdowns remain active on mobile.
- View As banner can become too tall on small screens.

Risks:

- Dropdown width overflow.
- User profile dropdown may exceed viewport.
- Impersonation banner can occupy too much vertical space.

Recommended mobile behavior:

- Keep hamburger, notification, language, avatar visible.
- Keep theme/fullscreen hidden on mobile.
- Make dropdowns fit viewport width.
- Compact View As banner.

Recommended CSS:

```css
@media (max-width: 575.98px) {
  .navbar-custom .dropdown .dropdown-menu {
    left: 0.5rem !important;
    right: 0.5rem !important;
    width: auto !important;
    max-width: calc(100vw - 1rem);
  }

  .topbar-notification-menu,
  .profile-dropdown {
    max-height: calc(100vh - 5rem);
    overflow-y: auto;
  }
}
```

### View As Banner

Current state:

- Banner shows target, actor, mode, reason, stop button.

Mobile issue:

- Reason and actor text can wrap too much.

Recommended:

- Keep target and stop button visible.
- Actor/mode shown compact.
- Reason can be truncated to one line.

Suggested mobile CSS:

```css
@media (max-width: 767.98px) {
  .impersonation-banner__content {
    align-items: stretch;
    gap: 0.5rem;
  }

  .impersonation-banner__text {
    min-width: 0;
  }

  .impersonation-banner__reason {
    max-width: 100%;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
  }

  #btnStopImpersonation {
    width: 100%;
  }
}
```

### Sidebar

Current state:

- Template changes sidenav to full mode on viewport under 768px.
- Hamburger toggles sidebar.

Recommended QA:

- Verify sidebar opens and closes at 320, 375, 390, 430.
- Verify backdrop closes sidebar.
- Verify long menu text does not break sidebar.
- Verify submenu collapse works with touch.

No major structural change recommended in early phases.

## DataTables Strategy

### Current Risk

The default helper currently uses:

```js
responsive: false
```

Many pages also use:

```js
scrollX: false
```

This makes wide tables dependent on desktop-like layout.

### Global DataTables Mobile Goals

- Search and length controls stack cleanly.
- Pagination does not overflow.
- Empty state spans the full table.
- Horizontal scroll is visible and contained inside table wrapper.
- Action column remains reachable.
- Desktop DataTables remain unchanged.

### Phase 2A: Safe Horizontal Scroll

This is the lowest-risk first step.

CSS goals:

- Table wrapper can scroll horizontally.
- Body page does not scroll horizontally.
- Header and cells keep readable padding.
- Toolbar stacks on mobile.

Recommended:

```css
@media (max-width: 767.98px) {
  .table-responsive,
  .table-responsive.dt-standard,
  .dt-standard-shell {
    overflow-x: auto;
    -webkit-overflow-scrolling: touch;
  }

  .dt-standard .dataTables_wrapper .row.mb-2 {
    row-gap: 0.5rem;
  }

  .dt-standard .dataTables_wrapper .dt-top-left,
  .dt-standard .dataTables_wrapper .dt-top-right {
    width: 100%;
    justify-content: flex-start !important;
    flex-wrap: wrap !important;
    padding-left: 0.75rem !important;
    padding-right: 0.75rem !important;
  }

  .dt-standard .dataTables_wrapper .dataTables_filter,
  .dt-standard .dataTables_wrapper .dataTables_filter label,
  .dt-standard .dataTables_wrapper .dataTables_filter input {
    width: 100%;
  }

  .dt-standard .dataTables_wrapper .dt-bottom-row {
    flex-direction: column;
    align-items: stretch !important;
  }

  .dt-standard .dataTables_wrapper .dataTables_info,
  .dt-standard .dataTables_wrapper .dataTables_paginate {
    justify-content: center !important;
    white-space: normal !important;
  }
}
```

### Phase 2B: Selective DataTables Responsive Extension

Use only where table columns can collapse safely.

Good candidates:

- Profile login activity.
- Profile audit trail.
- Notification templates.
- Notification admin recent list.
- Manual list.
- Template generator list.

Risky candidates:

- Access matrix.
- Group permission matrix.
- User list with action-heavy columns.
- Database object preview with dynamic columns.

Implementation rule:

- Do not globally enable DataTables responsive for every table.
- Add per-table `responsive: true` only after defining column priority.

Example pattern:

```js
responsive: {
  details: {
    type: 'column',
    target: 'tr'
  }
},
columnDefs: [
  { responsivePriority: 1, targets: actionColumnIndex },
  { responsivePriority: 2, targets: primaryNameColumnIndex },
  { responsivePriority: 3, targets: statusColumnIndex }
]
```

### Phase 2C: Optional Mobile Card View

Use only for high-use tables where horizontal scroll is still painful.

Best candidate:

- `senarai-pengguna.php` user list.

Possible candidate:

- `notifications.php` if notification list grows more complex.

Not recommended initially:

- Access matrix.
- Audit center.
- Database preview.

Card view rule:

- Desktop keeps DataTable.
- Mobile renders rows as compact cards.
- Action buttons remain visible.
- Sorting/search can still use DataTables if card view is generated from DataTables data, or can use a separate AJAX mobile renderer.

This should be a later enhancement, not Phase 1.

## Tabs Strategy

Problem:

- Tabs with many labels wrap into multiple rows.
- Wrapped tabs consume vertical space and can look broken.

Recommended mobile behavior:

- Horizontal scroll tabs.
- No wrapping.
- Active tab remains visible.

CSS:

```css
@media (max-width: 767.98px) {
  .nav.nav-tabs,
  .nav.nav-pills,
  .profile-tabs,
  .general-subtabs {
    flex-wrap: nowrap !important;
    overflow-x: auto;
    overflow-y: hidden;
    -webkit-overflow-scrolling: touch;
    scrollbar-width: thin;
  }

  .nav.nav-tabs .nav-item,
  .nav.nav-pills .nav-item {
    flex: 0 0 auto;
  }

  .nav.nav-tabs .nav-link,
  .nav.nav-pills .nav-link {
    white-space: nowrap;
  }
}
```

Optional JS:

- On tab shown, scroll active tab into view:

```js
document.addEventListener('shown.bs.tab', function (event) {
  if (!window.matchMedia('(max-width: 767.98px)').matches) return;
  event.target.scrollIntoView({ inline: 'center', block: 'nearest', behavior: 'smooth' });
});
```

## Modal Strategy

### Current Risk

Many pages use:

- `modal-xl`
- `modal-lg`
- `modal-dialog-scrollable`
- tabbed modal body
- footer button groups

Mobile issue:

- Large modals can feel clipped.
- Modal footer buttons can overflow.
- Tabs in modal can wrap.
- Long form grids need one-column layout.

### Recommended Mobile Modal Standard

```css
@media (max-width: 767.98px) {
  .modal-dialog,
  .modal-dialog.modal-lg,
  .modal-dialog.modal-xl {
    width: calc(100vw - 1rem);
    max-width: calc(100vw - 1rem);
    margin: 0.5rem auto;
  }

  .modal-content {
    max-height: calc(100vh - 1rem);
    border-radius: 8px;
  }

  .modal-header,
  .modal-footer {
    padding: 0.75rem 0.85rem;
  }

  .modal-body {
    padding: 0.85rem;
    overflow-x: hidden;
  }

  .modal-footer {
    flex-direction: column;
    align-items: stretch;
  }

  .modal-footer .btn {
    width: 100%;
  }
}
```

### Form Layout in Modals

Rule:

- Any modal form grid must collapse to one column below 768px.

Recommended:

```css
@media (max-width: 767.98px) {
  .modal .row > [class*="col-md-"],
  .modal .row > [class*="col-lg-"],
  .modal .row > [class*="col-xl-"] {
    width: 100%;
    flex: 0 0 100%;
    max-width: 100%;
  }
}
```

## SweetAlert Strategy

Current use:

- Confirmation prompts.
- View As reason prompt.
- Kill session confirmation.
- Error/success notices.

Mobile risks:

- Text input too narrow.
- HTML prompt content overflows.
- Buttons horizontal overflow.

Recommended:

```css
@media (max-width: 575.98px) {
  .swal2-popup {
    width: calc(100vw - 1.5rem) !important;
    padding: 1rem !important;
  }

  .swal2-actions {
    width: 100%;
    gap: 0.5rem;
  }

  .swal2-actions .swal2-confirm,
  .swal2-actions .swal2-cancel {
    flex: 1 1 auto;
    min-height: 40px;
  }
}
```

## Page-by-Page Plan

### Low Risk / Quick Win

#### `dashboard.php`

Current:

- Uses grid cards and one resource table.
- Already has responsive grid behavior.

Work:

- Verify KPI/action cards stack.
- Ensure resource table scrolls inside wrapper.
- Ensure topbar/sidebar and footer do not overlap.

Acceptance:

- Dashboard usable at 375px without body horizontal scroll.

#### `notifications.php`

Current:

- Simpler page.
- Filter button group can wrap.

Work:

- Ensure filter buttons wrap or scroll cleanly.
- Ensure notification cards/list stack.

Acceptance:

- Notification filters and list usable on 375px.

#### `profile.php`

Current:

- Has page CSS with responsive profile grid.
- Login Activity and Audit Trail are DataTables.

Work:

- Apply DataTables toolbar mobile standard.
- Ensure profile tabs scroll horizontally.
- Verify audit metadata modal on mobile.

Acceptance:

- All profile tabs usable; no empty row/table layout issue.

### Medium Risk

#### `manage-manuals.php`

Current:

- Table uses nowrap and fixed action behavior.
- Upload modal exists.

Work:

- Apply DataTables mobile toolbar.
- Ensure file upload modal fits mobile.
- Make manual action buttons touch-friendly.

Acceptance:

- Manual upload and table actions usable at 390px.

#### `notification-admin.php`

Current:

- Recent table.
- Large setup modal with tabs and grid.

Work:

- Mobile modal standard.
- Setup modal tabs horizontal scroll.
- Table toolbar stack.
- Composer form one-column below 768px.

Acceptance:

- Admin can compose/publish notification from mobile without clipped footer.

#### `notification-templates.php`

Current:

- Template table and modal.

Work:

- Mobile modal standard.
- Template form one-column.
- Action buttons in table remain reachable.

Acceptance:

- Create/edit template flow usable at 390px.

#### `template-emel.php`

Current:

- Template table and large edit/preview modal.

Work:

- Modal fullscreen-like on mobile.
- Editor/textarea/content preview height capped.
- Footer buttons stacked.

Acceptance:

- User can create/edit/test template without horizontal body scroll.

#### `template-generator.php`

Current:

- Template list and create modal.

Work:

- Table toolbar stack.
- Create modal one-column.
- Generated preview/actions fit mobile.

Acceptance:

- Template creation workflow readable on mobile.

### High Risk

#### `senarai-pengguna.php`

Current:

- Multiple user tabs.
- Multiple DataTables.
- Many `nowrap` and `flex-nowrap` rules.
- Several modals.
- View As workflow.

Risks:

- User table too wide.
- Action column can be hard to reach.
- Tab/action toolbar can overflow.
- Add/edit/role modal can be cramped.
- View As banner/prompt can be tall.

Phase plan:

1. Apply global DataTables mobile toolbar.
2. Make user tabs horizontal scroll.
3. Keep table horizontal scroll in Phase 2A.
4. Ensure action column visible/reachable.
5. Make modals fullscreen-like on mobile.
6. Compact View As banner/prompt.
7. Later optional: mobile card view for user rows.

Acceptance:

- Admin can search user, open actions, start View As, stop View As, and manage user role on 390px.

#### `kumpulan-pengguna.php`

Current:

- Large admin page with group table, module/menu permissions, subgroup management.
- Many modal XL.
- Many nowrap/table rules.

Risks:

- Permission tables are naturally wide.
- Modal tabs/forms are large.
- Button groups can overflow.

Phase plan:

1. Global mobile modal standard.
2. Table toolbar stack.
3. Keep permission matrices horizontally scrollable.
4. Modal tabs horizontal scroll.
5. Form grids one-column.
6. Consider mobile guidance text only if necessary.

Acceptance:

- Admin can open group/menu/module modals and perform basic create/update on 390px.

#### `tetapan-sistem.php`

Current:

- Multiple top tabs and General subtabs.
- Many settings cards.
- Database tab contains preview/probe/schema tables.
- Some dynamic DataTables use `scrollX: true` for object preview.

Risks:

- Tabs/subtabs wrap.
- Form cards may be long.
- DB preview tables are inherently wide.

Phase plan:

1. Top tabs horizontal scroll.
2. General subtabs horizontal scroll.
3. Forms one-column on mobile.
4. Action bars stack.
5. Database preview keeps horizontal scroll.
6. Additional connection modals use mobile modal standard.

Acceptance:

- Admin can change General/Login/Email/Theme/Language settings on mobile.
- Database object preview remains inspectable with contained horizontal scroll.

#### `audit-center.php`

Current:

- Audit panels and metadata modal.
- Data-heavy logs.

Risks:

- Log table width.
- Metadata JSON/details modal.
- Left nav/pills can consume width.

Phase plan:

1. Audit nav becomes horizontal scroll or stacked selector on mobile.
2. Tables remain horizontal scroll in Phase 2A.
3. Metadata modal fullscreen-like.
4. JSON/pre blocks scroll inside container.

Acceptance:

- User can browse audit panels and open metadata on 390px.

#### `access-matrix.php`

Current:

- Dynamic matrix columns based on groups/roles.
- Table is inherently wide.

Risks:

- Not suitable for normal mobile table view.
- Too many dynamic columns for responsive collapse.

Recommended:

- Do not force card view initially.
- Keep contained horizontal scroll.
- Add mobile-specific hint later if needed.
- Future enhancement: filter by group/role before rendering matrix.

Acceptance:

- Matrix does not break page body.
- User can horizontally scroll matrix within table area.

## Implementation Phases

### Phase 1: Global Mobile Compatibility Layer

Goal:

- Make every page basically usable on mobile without changing page logic.

Files:

- Add `public/assets/css/mobile-compat.css`.
- Optionally add `public/assets/js/mobile-compat.js`.
- Include these in global layout.

Scope:

- Content/page spacing.
- Page title/action stacking.
- Topbar dropdown mobile width.
- View As banner compact mobile CSS.
- Tabs horizontal scroll.
- Modal mobile standard.
- Basic DataTables toolbar/pagination stacking.
- Button/touch target improvements.

Smoke test:

- Dashboard.
- Profile.
- Senarai Pengguna.
- Kumpulan Pengguna.
- Tetapan Sistem.

Rollback:

- Remove CSS/JS include.
- No database/backend rollback required.

### Phase 2: DataTables and Tables

Goal:

- Stabilize all table-heavy surfaces on mobile.

Scope:

- DataTables toolbar standard.
- Pagination stack.
- Empty row consistency.
- Contained horizontal scroll.
- Per-table responsive extension only where safe.

Deliverables:

- Update DataTable standard helper if needed.
- Per-page overrides where page has custom DataTable DOM.
- List of table candidates for optional responsive collapse.

Smoke test:

- User table.
- Group table.
- Profile login activity/audit.
- Manual list.
- Notification template list.
- Access matrix.

Rollback:

- Disable responsive extension per table.
- Keep safe CSS table scroll.

### Phase 3: Modal/Form/Tabs

Goal:

- All modals and tabbed workflows fit mobile viewport.

Scope:

- Modal fullscreen-like sizing below 768px.
- Sticky/stacked modal footer.
- Form grid collapse.
- Modal tabs scroll horizontally.
- SweetAlert mobile width.
- Select2/dropdown overflow checks.

Smoke test:

- Add user modal.
- Group/menu access modal.
- Settings forms.
- Notification setup modal.
- Email template modal.
- View As prompt.

Rollback:

- Remove modal-specific mobile CSS.

### Phase 4: Page-Specific Polish

Goal:

- High-risk pages receive targeted improvements.

Order:

1. Dashboard, Notifications, Profile.
2. Senarai Pengguna.
3. Kumpulan Pengguna.
4. Tetapan Sistem.
5. Audit Center.
6. Access Matrix.
7. Notification/Template/Manual pages.

Deliverables per page:

- Known issues list.
- Mobile CSS override if required.
- JS adjustment if required.
- Smoke test result.

### Phase 5: Mobile Developer Standard

Goal:

- New modules/pages follow mobile-compatible rules by default.

Add to developer guidance:

- Required table wrapper.
- Required modal mobile behavior.
- Required tab behavior.
- Required QA viewport list.
- Prohibited patterns without mobile override.

### Phase 6: Optional Advanced Mobile

Only after compatibility is stable:

- PWA manifest.
- App icon.
- Safe-area inset support.
- Installable browser app.
- Offline/unavailable shell.

Do not start Phase 6 before Phase 1-4 are stable.

## Developer Rules for Future Pages

Do:

- Use Bootstrap grid and let fields collapse.
- Use `.table-responsive dt-standard` for DataTables.
- Keep actions reachable on mobile.
- Use `modal-dialog-scrollable` for large modals.
- Use horizontal scroll tabs for more than 3 tabs.
- Test MS and EN text length.
- Test dark and light theme.

Do not:

- Force `white-space: nowrap` without mobile override.
- Force `flex-wrap: nowrap` on toolbars without mobile fallback.
- Put too many action buttons in a row without wrapping/dropdown.
- Depend on hover-only UI.
- Hide critical actions inside responsive child row without alternative.
- Edit vendor/template CSS directly for project-specific mobile fixes.

## QA Matrix

### Viewports

Minimum manual viewport checks:

- 320 x 568
- 375 x 667
- 390 x 844
- 430 x 932
- 768 x 1024
- 1024 x 768
- 1366 x 768

### Browsers

Manual priority:

- Chrome mobile emulation.
- Edge/Chrome desktop responsive mode.
- Real Android Chrome where available.
- iOS Safari where available.

### Theme/Language

Check all critical flows under:

- Light theme.
- Dark theme.
- Bahasa Melayu.
- English.

### Global Acceptance Checklist

Each page passes mobile compatibility only if:

- No body-level horizontal scroll.
- Any horizontal scroll is contained inside table/code/pre wrapper.
- Topbar controls do not overlap.
- Sidebar opens, scrolls, and closes.
- Page title/actions are readable.
- Cards do not overflow viewport.
- Forms are usable without zoom.
- Buttons are tappable.
- Tables can be searched and paginated.
- Action buttons remain reachable.
- Modals fit viewport and can be closed.
- SweetAlert dialogs fit viewport.
- Empty/loading/error states display correctly.

## Page Smoke Test Checklist

### Dashboard

- Open dashboard on 375px.
- Confirm cards stack.
- Confirm resource table scrolls only inside wrapper.
- Confirm no topbar/sidebar overlap.

### Profile

- Open Profile tab.
- Open Login Activity tab.
- Open Audit Trail tab.
- Confirm tabs are usable.
- Confirm no-record table state spans full table.
- Open audit metadata if allowed.

### Senarai Pengguna

- Open staff/student/public tabs.
- Search user.
- Open add user modal.
- Open group/role modal.
- Trigger View As prompt.
- Start View As.
- Confirm mobile View As banner.
- Stop View As.

### Kumpulan Pengguna

- Search group table.
- Open add/edit group modal.
- Open group access.
- Open module access.
- Open menu access.
- Open subgroup management.
- Save one non-destructive update in test environment.

### Tetapan Sistem

- Open every main tab.
- Open General subtabs.
- Edit a safe setting in test environment.
- Open Database additional connection preview/schema modal if available.
- Confirm save action remains visible.

### Audit Center

- Switch audit panels.
- Search/filter where available.
- Open metadata modal.
- Confirm JSON/pre content scrolls inside modal.

### Access Matrix

- Open matrix.
- Scroll horizontally inside table.
- Confirm page body does not horizontally scroll.
- Confirm first/important columns remain readable enough.

### Notification Admin

- Open setup modal.
- Navigate modal tabs.
- Fill safe test content.
- Confirm footer buttons visible.

### Notification Templates

- Open create/edit modal.
- Navigate form.
- Confirm action buttons visible.

### Template Emel

- Open template modal.
- Edit content area.
- Preview/test where safe.
- Confirm modal can close and footer visible.

### Manage Manuals

- Open upload modal.
- Confirm file input area fits.
- Confirm manual action buttons are tappable.

## Risk Register

### Risk: DataTables Responsive Hides Important Actions

Mitigation:

- Do not globally enable responsive collapse.
- Define `responsivePriority` per table.
- Keep action column highest priority.

### Risk: CSS Override Breaks Desktop

Mitigation:

- Scope to mobile media queries.
- Load as additive CSS.
- Test 1366 desktop before merge.

### Risk: Mobile Modal Footer Blocks Content

Mitigation:

- Use stacked footer.
- Keep modal body scrollable.
- Test 320 and 375 widths.

### Risk: Table Horizontal Scroll Feels Poor

Mitigation:

- Phase 2A first.
- Add responsive collapse selectively.
- Add card view only for high-use tables after testing.

### Risk: Access Matrix Cannot Be Truly Mobile-Friendly

Mitigation:

- Treat as data-heavy admin matrix.
- Contain horizontal scroll.
- Later add filter-by-group mobile mode if needed.

## Rollback Plan

Phase 1 rollback:

- Remove `mobile-compat.css` include.
- Remove `mobile-compat.js` include if added.

Phase 2 rollback:

- Revert per-table DataTables option changes.
- Keep global CSS only if harmless.

Phase 3 rollback:

- Remove modal/form CSS overrides.

Phase 4 rollback:

- Revert page-specific mobile overrides only.

No database rollback should be required for mobile compatibility work.

## Implementation Recommendation

Recommended first implementation batch:

1. Add `mobile-compat.css`.
2. Include it globally after `custom.css`.
3. Add CSS for page title, tabs, modal, DataTables toolbar, topbar dropdown, and View As banner.
4. Smoke test five representative pages:
   - `dashboard.php`
   - `profile.php`
   - `senarai-pengguna.php`
   - `kumpulan-pengguna.php`
   - `tetapan-sistem.php`
5. Only then decide if `mobile-compat.js` is needed.

Recommended second batch:

1. Fix DataTables page-by-page.
2. Add selective responsive extension only to safe tables.
3. Keep access matrix and permission matrix as contained horizontal scroll.

Recommended third batch:

1. Modal/form polish for user, group, settings, notification, and template workflows.
2. Add QA notes to developer standard.

## Definition of Done

Mobile compatibility work is done when:

- All active pages pass the global acceptance checklist.
- High-risk pages pass their smoke test.
- Desktop layout remains unchanged in normal 1366px viewport.
- No body-level horizontal scroll exists on mobile.
- All critical admin actions remain reachable.
- QA has been run for MS/EN and light/dark theme.
- Developer rules are documented for future modules.
