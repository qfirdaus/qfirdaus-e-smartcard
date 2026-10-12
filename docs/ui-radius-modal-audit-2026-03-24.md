# UI Radius And Modal Audit

Date: 2026-03-24
Scope: `/var/www/app\e-base\public`
Goal: verify whether the curve/radius standard has been implemented consistently across pages, tables, controls, and modals before `e-base` is used as a publish-ready base system.

## Executive Summary

The radius standard is partially implemented, but it is not yet fully consistent across the system.

Current state:
- Core table baseline is now moving in the right direction through:
  - `assets/css/datatables-standard.css`
  - `assets/js/helpers/datatables-standard.js`
- Major pages already align more closely with the new standard:
  - `dashboard.php`
  - `tetapan-sistem`
  - `profile`
  - `kumpulan-pengguna`
  - `manage-manuals`
  - `senarai-pengguna`
- Modal consistency has improved, but it is still driven by page-level custom CSS rather than a single shared modal standard.

Conclusion:
- The system is visually much closer to a publishable base.
- It is not yet fully standardised for radius/modal treatment.
- The remaining gaps are now concentrated in a smaller number of custom overrides, not everywhere.

## Audit Result

### 1. What is already standardised

These parts are already reasonably aligned:
- shared table shell in `assets/css/datatables-standard.css`
- shared DataTables helper in `assets/js/helpers/datatables-standard.js`
- main page surfaces in `assets/css/app.css`
- `tetapan-sistem` page-specific CSS mostly using `7px/8px`
- `profile.css` mostly using `8px`
- `kumpulan-pengguna.php` accepted as table benchmark

### 2. Remaining inconsistencies

#### A. Global CSS still contains competing radius systems

File:
- `public/assets/css/app.css`

Findings:
- Bootstrap root tokens still expose old radius scale:
  - `--ct-border-radius: 0.15rem`
  - `--ct-border-radius-lg: 0.5rem`
  - `--ct-border-radius-xl: 1rem`
- Neo override layer introduces a second radius scale:
  - `--neo-border-radius: 0.5rem`
  - `.btn { border-radius: 0.5625rem; }`
  - `.modal-content { border-radius: calc(var(--neo-border-radius)); }`
- Some global controls still keep larger values:
  - range slider thumb/track at `1rem`
  - Select2 scrollbar radius at `10px`
  - one old global pill style still at `30px`

Impact:
- Even when page CSS is cleaned up, some native/global Bootstrap-derived components can still feel slightly different from the intended `7px/8px` system.

#### B. Some pages still carry larger custom radius values

Files:
- `public/pages/carian-pelajar.php`
- `public/assets/css/pages/tetapan-sistem.css`

Findings:
- `carian-pelajar.php` still uses:
  - `1rem` on main card
  - `1rem` on SweetAlert popup
  - `.75rem` on confirm button
- `tetapan-sistem.css` still has at least one visible `10px`:
  - `.theme-option { border-radius: 10px; }`

Impact:
- These are small but visible deviations if the goal is a strict enterprise-style baseline.

#### C. Modal styling is still decentralised

Files with significant custom modal CSS:
- `public/pages/senarai-pengguna.php`
- `public/pages/kumpulan-pengguna.php`
- `public/pages/manage-manuals.php`
- `public/includes/topbar.php`
- `public/assets/css/pages/profile.css`

Findings:
- Modals are visually improved, but each module still defines its own:
  - `.modal-content`
  - `.modal-header`
  - `.modal-footer`
  - title spacing
  - footer button styling
  - themed gradients
- This means the system has consistency by repeated manual tuning, not by a single source of truth.

Impact:
- Easy to drift again when future pages are added.
- Harder to maintain as `e-base` becomes the base product for other systems.

#### D. SweetAlert styling is still duplicated

Files:
- `public/pages/senarai-pengguna.php`
- `public/pages/manage-manuals.php`
- `public/pages/carian-pelajar.php`
- `public/pages/kumpulan-pengguna.php`

Findings:
- Each module defines its own popup/title/button style classes.
- Most are now close in feel, but not governed by one shared alert preset.

Impact:
- Users may still notice subtle differences between confirm dialogs across modules.

#### E. Legacy/minified assets still contain old radius values

Files:
- `public/assets/css/app.min.css`
- `public/assets/css/app-rtl.css`
- `public/assets/css/output.css`

Findings:
- These files still contain older radius systems.
- They are not the preferred source of truth, but they remain a maintenance risk if any page or future build path loads them directly.

Impact:
- Publish baseline can drift without anyone noticing.
- Developers cloning `e-base` for another system may accidentally inherit the wrong visual baseline.

## Risk Assessment Before Publish

### Low risk
- Main pages already look substantially improved.
- Table system is now much more coherent than before.
- No major indication that radius inconsistency is causing runtime errors.

### Medium risk
- Future pages may continue copying local modal/SweetAlert CSS instead of using a shared system.
- A page that still loads legacy CSS can silently reintroduce inconsistent corners.

### High-value publish concern
- The system is close to visually professional.
- But for a true reusable base system, the design system layer is still not strict enough.

## Recommended Final Improvements

### Priority 1: Lock one official radius scale

Recommended official scale:
- containers/cards/modals/dropdowns: `8px`
- controls/buttons/inputs/selects: `7px`
- small inner items/chips: `6px`
- pill elements only: `999px`
- circular avatars/status dots: `50%`

Action:
- document this as the official UI radius rule
- stop introducing `10px`, `12px`, `14px`, `1rem` in web UI unless there is a justified exception

### Priority 2: Create a shared modal standard

Recommended:
- move common modal shell styling into shared CSS
- let page-level CSS style only colour/theme accents, not shell structure

Shared modal baseline should own:
- `.modal-content`
- `.modal-header`
- `.modal-footer`
- footer button density
- modal title spacing
- dark mode shell

### Priority 3: Create a shared SweetAlert preset

Recommended:
- one shared SweetAlert visual preset for:
  - popup radius
  - title size
  - confirm button
  - cancel button
  - icon ring/border

This should replace per-page popup classes over time.

### Priority 4: Clean remaining page outliers

Immediate candidates:
1. `public/pages/carian-pelajar.php`
2. `public/assets/css/pages/tetapan-sistem.css`
3. any remaining page-level modal block with bespoke shell radius

### Priority 5: Treat legacy assets as controlled technical debt

Recommended:
- do not use `app.min.css`, `app-rtl.css`, or `output.css` as style reference for future work
- clearly mark `app.css` + page assets as the only design source of truth
- later, regenerate minified assets from the cleaned baseline if needed

## Publish Readiness Verdict

Verdict: **Almost ready, but not fully locked down yet.**

If the goal is:
- “looks good for internal use” -> acceptable
- “publish-ready base system for reuse by future systems” -> one more cleanup pass is recommended

Minimum final pass before publish:
1. unify remaining outlier radius values
2. create shared modal shell standard
3. create shared SweetAlert standard
4. verify legacy asset loading paths

## Recommended Next Action

Best next step:
- perform one final `UI standardisation pass` focused only on:
  - shared modal shell
  - shared SweetAlert preset
  - remaining radius outliers in `carian-pelajar.php` and `tetapan-sistem.css`

This would make `e-base` much safer to use as the parent baseline for future systems.
