# Refactor Roadmap

Tarikh: 2026-03-23

## Objektif

Dokumen ini merumuskan audit struktur semasa codebase `e-base` dan mencadangkan roadmap refactor berfasa yang praktikal. Fokus utama ialah:

- kurangkan page monolith
- seragamkan contract AJAX
- kurangkan inline CSS/JS
- centralize source of truth untuk data dan UI state
- kurangkan regression risk semasa perubahan seterusnya

## Ringkasan Audit

Corak kelemahan yang paling jelas:

1. Banyak page besar mencampurkan rendering, business logic, state orchestration, CSS, dan JavaScript dalam satu fail.
2. Endpoint AJAX menggunakan pattern response, logging, dan error handling yang tidak seragam.
3. Ada drift antara server-rendered HTML dan data/behaviour yang dibina semula di AJAX atau JavaScript.
4. Pattern UI berulang, terutamanya DataTables, modals, empty states, loaders, dan action buttons.
5. Translation coverage masih bergantung pada fallback literal yang bertaburan dalam page.

## Status Audit Kritikal

### Dokumen Berkaitan

Roadmap implementasi berfasa untuk model pengguna, authentication, authorization, dan persediaan SSO direkodkan dalam:

- `docs/user-auth-roadmap-2026-03-25.md`

### Modul 1: `senarai-pengguna`

Status: selesai pass kritikal pertama.

Skop yang telah disiapkan:

- `public/ajax/user-list-rows.php`
- `public/ajax/user-set-group.php`
- `public/ajax/user-delete.php`
- `public/ajax/user-add.php`
- `public/pages/senarai-pengguna.php`

Hasil utama:

- contract `user-list-rows` dijadikan `rows` sahaja
- policy action button diselaraskan antara server dan client
- self-delete guard diketatkan
- bootstrap/output hygiene AJAX diseragamkan melalui helper bersama
- endpoint utama modul pengguna distruktur semula supaya flow lebih jelas

### Modul 2: `tetapan-sistem`

Status: audit kritikal selesai, refactor besar belum dimulakan.

Dapatan utama:

- `public/pages/tetapan-sistem.php` masih memegang micro-cache helper sendiri
- view masih kira runtime DB state sendiri walaupun normalization yang sama sudah wujud di controller
- view masih instantiate `Config` lebih daripada sekali
- tab Theme masih bypass controller dan ambil data terus dari model
- page masih terlalu berat dari segi inline CSS dan inline JavaScript

Impak utama:

- source of truth untuk runtime/config state masih berpecah antara page dan controller
- bug seperti render `unknown` mudah berulang apabila ada perubahan kecil pada urutan variable
- page sukar diuji dan mahal untuk dipecahkan kemudian jika dibiarkan lebih lama

## Prioriti Fasa

### Fasa 1: Critical Stabilization

Sasaran:

- hapuskan contract drift dan duplicate source of truth
- ketatkan guard untuk tindakan sensitif
- seragamkan response helper untuk endpoint AJAX kritikal

Fail utama:

- `public/ajax/user-list-rows.php`
- `public/pages/senarai-pengguna.php`
- `public/ajax/user-delete.php`
- `public/pages/tetapan-sistem.php`
- `public/controllers/TetapanSistemController.php`

### Fasa 2: Shared UI and AJAX Infrastructure

Sasaran:

- wujudkan preset DataTables bersama
- wujudkan helper response JSON yang seragam
- keluarkan inline JS/CSS yang paling berat ke asset khusus page

Fail utama:

- `public/ajax/_helpers.php`
- `public/assets/css/app.css`
- `public/pages/senarai-pengguna.php`
- `public/pages/kumpulan-pengguna.php`
- `public/pages/profile.php`
- `public/pages/carian-pelajar.php`

### Fasa 3: Page Decomposition

Sasaran:

- pecahkan page besar kepada partial view, page service, dan JS/CSS khusus
- kecilkan surface area setiap fail

Fail utama:

- `public/pages/senarai-pengguna.php`
- `public/pages/tetapan-sistem.php`
- `public/pages/profile.php`
- `public/pages/kumpulan-pengguna.php`

### Fasa 4: Translation and Quality Guardrails

Sasaran:

- audit dan tutup missing translation key
- kurangkan fallback literal
- tambah lint/smoke checks minimum

Fail utama:

- `public/lang/en.php`
- `public/lang/ms.php`
- `public/pages/*`
- `public/includes/*`

## Roadmap Mengikut Fail

### `public/pages/senarai-pengguna.php`

Masalah semasa:

- terlalu besar dan memegang terlalu banyak tanggungjawab
- gabung HTML, modals, DataTable setup, fetch retry, DOM mutation, dan access logic
- ada duplicate rendering path antara server row dan AJAX row builder

Cadangan refactor:

1. Pecahkan kepada partial:
   - `_user_table.php`
   - `_user_modals.php`
   - `_user_filters.php`
2. Pindahkan JavaScript ke:
   - `public/assets/js/pages/senarai-pengguna.js`
3. Wujudkan satu `row action policy` helper bersama untuk:
   - edit
   - set group
   - delete
   - self-target restriction
4. Pastikan server dan client berkongsi source of truth yang sama untuk permissions/actions.

Hasil dijangka:

- lebih mudah kawal regression
- kurangkan drift antara HTML server dan JS builder

### `public/ajax/user-list-rows.php`

Masalah semasa:

- terlalu banyak custom buffering/error handling di dalam endpoint
- masih bina HTML action button sendiri
- contract tidak selari sepenuhnya dengan `senarai-pengguna.php`
- endpoint ini mudah drift setiap kali logic UI di page berubah

Cadangan refactor:

1. Tukar endpoint ini jadi data-first:
   - pulangkan `rows` berstruktur sahaja
   - elakkan `htmlRows` sebagai contract utama
2. Gunakan helper response standard:
   - `success`
   - `message`
   - `data`
   - `errors`
3. Satukan rule self-delete dan visibility action button ke satu helper shared.
4. Kurangkan custom global handlers dalam fail ini dan pindahkan ke helper bootstrap AJAX.

Hasil dijangka:

- contract lebih stabil
- kurang risiko UI drift
- lebih mudah diuji

### `public/ajax/user-delete.php`

Masalah semasa:

- sudah ada guard self-delete, tetapi pattern validation/error response masih boleh diseragamkan dengan endpoint lain

Cadangan refactor:

1. Standardize error envelope.
2. Gunakan helper audit/log/response bersama.
3. Samakan localization untuk semua message success/failure.

### `public/pages/tetapan-sistem.php`

Masalah semasa:

- view masih memegang logic config dan runtime state yang sepatutnya duduk dalam controller/service
- ada lebih daripada satu instantiation `Config`
- helper cache dan derivation state masih berada dalam page
- page sangat berat dari segi inline CSS/JS

Cadangan refactor:

1. Alih semua derivation runtime config ke controller/service:
   - DB render state
   - runtime labels
   - default selections
2. View hanya consume satu DTO/view-model.
3. Pecahkan page kepada partial:
   - `_general_settings.php`
   - `_email_settings.php`
   - `_database_settings.php`
   - `_theme_settings.php`
   - `_language_settings.php`
4. Pindahkan JS page ke `public/assets/js/pages/tetapan-sistem.js`.
5. Pindahkan CSS page ke `public/assets/css/pages/tetapan-sistem.css`.

Hasil dijangka:

- source of truth jadi jelas
- risiko bug seperti `unknown` render state berkurang
- fail lebih mudah diselenggara

### `public/controllers/TetapanSistemController.php`

Masalah semasa:

- controller sudah ada banyak method yang betul, tetapi view masih bypass sebahagian tanggungjawabnya

Cadangan refactor:

1. Tambah method tunggal seperti `getViewModel(): array`.
2. Kumpulkan semua settings page data dalam satu structure:
   - general
   - email
   - language
   - db runtime selection
   - mysql info
3. Centralize normalization untuk environment/mode di controller atau service khusus.

### `public/pages/profile.php`

Masalah semasa:

- inline CSS/JS masih besar
- tab `Login Activity` dan `Audit Trail` ada UI logic yang boleh dikongsi dengan page lain

Cadangan refactor:

1. Extract DataTable preset untuk log/audit table.
2. Pindahkan tooltip, truncate, modal/session code ke JS file khusus page.
3. Standardize empty/loading/error state untuk activity tables.

### `public/pages/kumpulan-pengguna.php`

Masalah semasa:

- masih heavy dari segi inline JS
- modal handling, icon preview, loading state, dan DataTable config terlalu rapat dengan markup

Cadangan refactor:

1. Pindahkan script ke `public/assets/js/pages/kumpulan-pengguna.js`.
2. Extract modal state machine untuk create/edit modal.
3. Reuse shared DataTable preset dan shared button-loading helper.

### `public/pages/carian-pelajar.php`

Masalah semasa:

- page lebih kecil, tetapi boleh cepat drift dari standard table/system messages jika dibiarkan custom

Cadangan refactor:

1. Guna shared table preset.
2. Centralize empty state message dan translation key.
3. Standardize search result rendering dan no-result state.

### `public/includes/topbar.php`

Masalah semasa:

- ada logic UI state khas seperti development mode panel yang berpotensi membesar tanpa kawalan
- include ini mudah jadi tempat terkumpul behaviour global

Cadangan refactor:

1. Hadkan include kepada rendering shell global sahaja.
2. Pindahkan behaviour interaktif development mode ke JS global khusus.
3. Pastikan semua runtime info datang dari helper tunggal yang normalized.

### `public/includes/sidebar.php`

Masalah semasa:

- layout dan spacing kini banyak bergantung pada CSS global dan token dalam `app.css`
- bila sidebar diubah, impak terus terkena topbar dan content layout

Cadangan refactor:

1. Wujudkan design tokens yang jelas untuk:
   - width
   - compact font size
   - icon size
   - spacing
2. Dokumentasikan dependencies layout sidebar-topbar-content.

### `public/assets/css/app.css`

Masalah semasa:

- menjadi tempat terkumpul override global, component styling, dan layout tokens
- sukar beza antara token, vendor-derived styles, dan custom overrides

Cadangan refactor:

1. Asingkan layer:
   - tokens
   - layout
   - shared components
   - page overrides
2. Kurangkan page-specific CSS dalam file global.

### `public/lang/en.php` dan `public/lang/ms.php`

Masalah semasa:

- translation coverage belum cukup disiplin
- fallback literal masih banyak dalam view

Cadangan refactor:

1. Audit semua key aktif mengikut page.
2. Kurangkan fallback literal dalam page untuk UI yang sudah stabil.
3. Wujudkan naming convention per-module:
   - `userList_*`
   - `profile_*`
   - `systemConfig_*`
   - `group_*`

### `public/ajax/_helpers.php`

Masalah semasa:

- helper sudah wujud, tetapi belum nampak menjadi contract wajib bagi semua endpoint

Cadangan refactor:

1. Jadikan helper ini satu-satunya pintu untuk JSON success/error response.
2. Tambah utility bersama untuk:
   - input validation failure
   - unauthorized/forbidden
   - exception-safe failure
   - audit metadata envelope

## Audit Paling Kritikal Untuk Dimulakan

1. `public/ajax/user-list-rows.php`
   - kritikal kerana ada drift dengan `senarai-pengguna.php`
   - tindakan sensitif seperti delete boleh tidak konsisten selepas reload AJAX

2. `public/pages/tetapan-sistem.php`
   - kritikal kerana page ini sudah terbukti mengalami source-of-truth bug pada render DB state
   - view masih terlalu banyak pegang runtime logic

3. `public/pages/senarai-pengguna.php`
   - kritikal kerana surface area sangat besar dan menjadi pusat operasi user management
   - sebarang perubahan kecil mudah memberi kesan sampingan

## Cadangan Tindakan Seterusnya

Urutan kerja paling bernilai:

1. Stabilize `user-list-rows.php` agar ikut policy action yang sama dengan page.
2. Extract view-model untuk `tetapan-sistem.php`.
3. Mulakan decomposition `senarai-pengguna.php` kepada partial + JS page file.
4. Wujudkan shared DataTable preset.
5. Buat translation coverage pass mengikut modul.

## Nota

Roadmap ini sengaja disusun untuk memberi pulangan paling cepat terhadap kestabilan dan maintainability tanpa memaksa rewrite besar-besaran.

## Status Audit Kritikal

### Modul `senarai-pengguna`

Status semasa: `completed (critical pass)`

Fail yang telah diaudit dan dibaiki:

- `public/ajax/user-list-rows.php`
- `public/ajax/user-set-group.php`
- `public/ajax/user-delete.php`
- `public/ajax/user-add.php`
- `public/pages/senarai-pengguna.php`

Penambahbaikan yang telah disiapkan:

1. `user-list-rows.php`
   - contract telah ditukar kepada `rows` sahaja
   - path `html` untuk reload table telah dibuang
   - policy action button kini selari dengan page utama
   - response/error handling telah diseragamkan dengan helper AJAX bersama

2. `senarai-pengguna.php`
   - reload table kini consume `rows` sahaja
   - row builder hormat flag server seperti `can_edit_group` dan `can_delete_user`
   - dependency pada fallback HTML untuk endpoint `user-list-rows.php` telah dibuang

3. `user-set-group.php`
   - bootstrap dan response kini guna helper bersama
   - unauthorized/rate-limit/error contract telah diseragamkan
   - struktur dalaman telah dipecah kepada helper untuk:
     - payload parsing
     - schema lookup
     - user/group context resolution
     - noop detection
     - update statement build
     - update execution
     - audit logging

4. `user-delete.php`
   - bootstrap dan response kini guna helper bersama
   - self-delete guard kekal aktif dan lebih mudah diaudit
   - struktur dalaman telah dipendekkan untuk:
     - payload validation
     - target user lookup
     - self-delete evaluation
     - audit payload build
     - audit fallback logging
     - cache clearing

5. `user-add.php`
   - bootstrap dan response kini guna helper bersama
   - rate-limit, CSRF, duplicate check, dan Sybase lookup telah diseragamkan
   - struktur dalaman telah dipecah kepada helper untuk:
     - payload parsing
     - group resolution
     - duplicate check
     - Sybase lookup
     - insert execution
     - audit payload build
     - audit logging
     - cache clearing

Kesan langsung:

- surface area regression untuk modul user management telah berkurang
- contract AJAX lebih konsisten
- output hygiene lebih baik
- flow page `senarai-pengguna` kini kurang bergantung pada HTML response yang sukar diaudit

Residual work yang masih relevan:

- kurangkan `error_log` debug mentah pada endpoint yang sudah diaudit
- pindahkan closure/helper dalaman endpoint ke shared service/helper file jika modul ini ingin distandardkan lebih jauh
- tambah smoke test minimum untuk:
  - add user
  - set group
  - delete user
  - reload rows

### Modul `tetapan-sistem`

Status semasa: `completed (critical pass + page decomposition)`

Fail yang telah diaudit dan dibaiki:

- `public/controllers/TetapanSistemController.php`
- `public/pages/tetapan-sistem.php`
- `public/pages/partials/tetapan-sistem/tab-general.php`
- `public/pages/partials/tetapan-sistem/tab-email.php`
- `public/pages/partials/tetapan-sistem/tab-database.php`
- `public/pages/partials/tetapan-sistem/tab-theme.php`
- `public/pages/partials/tetapan-sistem/tab-language.php`
- `public/assets/js/pages/tetapan-sistem.js`
- `public/assets/css/pages/tetapan-sistem.css`
- `public/assets/js/helpers/page-ui-helper.js`

Penambahbaikan yang telah disiapkan:

1. `TetapanSistemController.php`
   - controller kini jadi source of truth untuk read-path page
   - DB runtime view-model telah dipusatkan di controller
   - theme read state telah dipusatkan di controller
   - orchestration cache telah dipindahkan keluar dari view
   - invalidation cache untuk `email`, `lang`, `theme`, dan `db-runtime` telah dilengkapkan

2. `tetapan-sistem.php`
   - page utama kini hanya bertindak sebagai shell + consumer kepada `$viewData`
   - direct model access dan duplicate derivation runtime state telah dibuang
   - bug render `unknown` untuk DB runtime jadi lebih sukar berulang

3. Partial view
   - semua tab telah dipisahkan ke partial khusus
   - struktur page kini lebih modular dan lebih rendah risiko untuk perubahan seterusnya

4. Asset extraction
   - inline JavaScript telah dipindahkan ke `public/assets/js/pages/tetapan-sistem.js`
   - inline CSS telah dipindahkan ke `public/assets/css/pages/tetapan-sistem.css`
   - helper UI yang berpotensi dikongsi telah dipindahkan ke `public/assets/js/helpers/page-ui-helper.js`

Kesan langsung:

- source of truth untuk DB runtime dan theme read-path kini lebih jelas
- page utama telah jauh berkurang dari sudut coupling dan duplication
- bug yang datang daripada state drift antara controller dan view telah dikurangkan dengan ketara

Residual work yang masih relevan:

- audit sama ada sebahagian CSS/JS `tetapan-sistem` patut dinaik taraf lagi ke shared UI layer
- tambah smoke test minimum untuk:
  - save general
  - save email
  - save database
  - save theme
  - save language

### Modul `profile`

Status semasa: `completed (critical pass + asset extraction)`

Fail yang telah diaudit dan dibaiki:

- `public/controllers/ProfileController.php`
- `public/pages/profile.php`
- `public/assets/js/pages/profile.js`
- `public/assets/css/pages/profile.css`

Penambahbaikan yang telah disiapkan:

1. Initial data load
   - duplicate initial fetch untuk `Login Activity` dan `Audit Trail` telah dibuang
   - source of truth untuk dua jadual itu kini datang daripada AJAX/DataTables sahaja
   - `hasActiveSession()` telah ditambah pada controller untuk kekalkan indicator status tanpa preload dataset besar

2. Audit modal styling
   - CSS modal audit yang dahulu ditanam di dalam string JavaScript telah dikeluarkan
   - modal audit kini guna stylesheet page biasa, bukan inject `<style>` semasa runtime

3. Asset extraction
   - JavaScript page-specific telah dipindahkan ke `public/assets/js/pages/profile.js`
   - CSS page-specific telah dipindahkan ke `public/assets/css/pages/profile.css`
   - `profile.php` kini hanya inject config ringkas dan load asset page

Kesan langsung:

- initial page load lebih ringan dan lebih konsisten
- modal audit lebih mudah diselenggara
- `profile.php` telah berkurang ketara dari sudut inline CSS/JS

Residual work yang masih relevan:

- semak sama ada DataTables preset untuk `profile` boleh dinaikkan ke shared helper yang turut digunakan oleh page log/audit lain
- pertimbangkan partial decomposition untuk tab profil jika perubahan UI modul ini dijangka bertambah lagi

### Roadmap Tambahan: User/Auth/SSO

Roadmap implementasi berfasa untuk pengurusan pengguna, authentication, authorization, dan integrasi OneID direkodkan dalam dokumen berasingan supaya tidak bercampur dengan roadmap refactor UI/AJAX ini:

- `docs/user-auth-roadmap-2026-03-25.md`

Dokumen tersebut menjadi rujukan utama untuk:

- sokongan `STAFF`, `STUDENT`, dan `PUBLIC`
- local login semasa development
- configuration policy auth/access
- persediaan sync external identity
- integrasi OneID pada fasa akhir

### Roadmap Tambahan: Page Template Generator

Cadangan seni bina dan pelan implementasi untuk ciri `Page Template Generator` direkodkan dalam dokumen berasingan:

- `docs/page-template-generator-roadmap-2026-03-27.md`

Dokumen tersebut menjadi rujukan utama untuk:

- reka bentuk template registry
- baseline generation untuk page/controller/js/css/lang
- strategi feature injection
- safe file generation
- pelan rollout berfasa untuk generator dalaman `e-Base`
