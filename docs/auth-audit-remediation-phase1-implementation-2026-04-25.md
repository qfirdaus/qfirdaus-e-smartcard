# Auth Audit Remediation Phase 1 Implementation Plan

Tarikh: 2026-04-25

Dokumen ini ialah pecahan task implementasi sebenar untuk Fasa 1 daripada roadmap:
- [auth-audit-remediation-roadmap-2026-04-25.md](/var/www/app/iqs-framework/docs/auth-audit-remediation-roadmap-2026-04-25.md)

Skop Fasa 1:
- hardening tanpa mengubah behavior login yang sah
- jangan mengganggu flow login manual sedia ada
- jangan mengganggu flow SSO yang sah
- fokus pada entry-point hardening, session bootstrap consistency, CSRF manual login, dan pembersihan trust boundary yang lemah

Status legend:
- `[ ]` belum mula
- `[~]` sedia untuk implementasi
- `[x]` siap dan disahkan

## Sasaran Fasa 1

Hasil yang dikehendaki selepas Fasa 1:
- `index.php` dan `login.php` menggunakan bootstrap session yang konsisten
- auth-state check di `index.php` berasaskan `f_loginID`
- pembersihan cookie berkaitan SSO dibuat server-side
- `HTTP_REFERER` tidak lagi digunakan sebagai trigger utama security flow
- manual login dilindungi oleh CSRF validation sebenar
- response headers pada login entry point lebih konsisten dan defensible
- config-driven URL / asset pada login page lebih terkawal

## Task 1: Satukan Session Bootstrap Di `includes/init.php`

Status:
- `[~]`

Fail terlibat:
- `public/index.php`
- `public/login.php`
- `public/includes/init.php`

Tujuan:
- memastikan hanya satu tempat mengawal session cookie flags, invalid session-cookie cleanup, dan `session_start()`

Perubahan yang dicadangkan:
- semak logic session hardening sedia ada dalam `public/includes/init.php`
- pindahkan tanggungjawab bootstrap session sepenuhnya ke `init.php`
- buang duplicate `ini_set('session.cookie_*', ...)` dari `index.php`
- buang duplicate `ini_set('session.cookie_*', ...)` dari `login.php`
- buang `session_start()` awal dari `index.php`
- buang `session_start()` awal dari `login.php` jika `init.php` sudah cover

Checklist implementasi:
- `[ ]` audit semula urutan include awal dalam `index.php`
- `[ ]` audit semula urutan include awal dalam `login.php`
- `[ ]` pastikan `init.php` boleh menetapkan cookie flags sebelum session dimulakan
- `[ ]` pastikan `init.php` membersihkan malformed session cookie sebelum `session_start()`
- `[ ]` pastikan tiada `headers already sent` selepas ubah bootstrap
- `[ ]` semak semula bahawa CSRF/session flash alert masih berfungsi

Acceptance criteria:
- `index.php` tidak lagi memanggil `session_start()` sebelum `init.php`
- `login.php` tidak lagi duplicate bootstrap session yang sama
- session masih aktif dan stabil untuk flow login berjaya, gagal, dan redirect

Verification:
- buka `index.php` sebagai guest
- buka `index.php` dengan session sedia ada
- hantar manual login request berjaya
- hantar manual login request gagal
- semak tiada warning session/header

## Task 2: Tukar Auth-State Check `index.php` Kepada `f_loginID`

Status:
- `[~]`

Fail terlibat:
- `public/index.php`

Tujuan:
- memastikan semua kategori user yang sah (`STAF`, `PELAJAR`, `UMUM`) menggunakan identity standard yang sama untuk semakan session aktif

Perubahan yang dicadangkan:
- cari semua semakan `$_SESSION['f_stafID']` yang digunakan untuk tentukan sama ada user sudah login
- tukar kepada `$_SESSION['f_loginID']`
- jika perlu, buat helper kecil dalaman untuk semakan auth-state supaya branch jadi konsisten

Checklist implementasi:
- `[ ]` cari semua branch di `index.php` yang menggunakan `f_stafID` untuk detect authenticated user
- `[ ]` tukar branch itu kepada `f_loginID`
- `[ ]` semak branch redirect early untuk user yang sudah login
- `[ ]` semak branch yang bergantung pada session separa wujud
- `[ ]` tetapkan handling untuk partial session rosak:
  - jika `f_loginID` tiada, treat sebagai belum login

Acceptance criteria:
- user `STAF`, `PELAJAR`, dan `UMUM` yang sudah login semua dianggap authenticated melalui `f_loginID`
- user dengan partial/legacy session tidak tersalah redirect sebagai authenticated

Verification:
- uji user staf yang sudah login
- uji user pelajar yang sudah login
- uji user umum yang sudah login
- uji session yang hanya ada `f_stafID` tanpa `f_loginID`

## Task 3: Ganti JS Cookie Clearing Kepada Server-Side Cookie Clearing

Status:
- `[~]`

Fail terlibat:
- `public/index.php`

Tujuan:
- mengelakkan pembersihan state auth/SSO yang bergantung pada JavaScript browser

Perubahan yang dicadangkan:
- cari semua pembersihan cookie `sso_cre` atau seumpamanya yang dibuat melalui `echo '<script>document.cookie=...'`
- gantikan dengan `setcookie()` server-side
- padankan attribute cookie dengan betul:
  - `path`
  - `secure`
  - `samesite`
  - `httponly` jika berkaitan

Checklist implementasi:
- `[ ]` kenal pasti nama cookie yang dibersihkan dari `index.php`
- `[ ]` semak path cookie yang sebenar digunakan
- `[ ]` ganti JS cookie deletion dengan `setcookie(..., time() - 3600, ...)`
- `[ ]` semak bahawa pembersihan berlaku sebelum output/redirect
- `[ ]` pastikan flow SSO sah tidak terputus akibat cookie deletion yang terlalu awal

Acceptance criteria:
- tiada lagi auth/SSO cookie cleanup berasaskan JavaScript dalam `index.php`
- cookie yang patut dibersihkan benar-benar hilang melalui response header server-side

Verification:
- trigger branch yang sebelum ini clear cookie
- semak browser devtools `Set-Cookie`
- semak flow selepas clear cookie masih redirect dengan betul

## Task 4: Keluarkan `HTTP_REFERER` Daripada Security Decision Utama

Status:
- `[~]`

Fail terlibat:
- `public/index.php`

Tujuan:
- menghapuskan trust boundary lemah pada login entry point

Perubahan yang dicadangkan:
- kenal pasti semua branch yang menggunakan `$_SERVER['HTTP_REFERER']`
- tukar supaya referer tidak lagi menentukan autoroute ke SSO
- jika perlu kekalkan, guna hanya untuk:
  - analytics
  - UI hint
  - debug ringan

Checklist implementasi:
- `[ ]` cari semua penggunaan `HTTP_REFERER` dalam `index.php`
- `[ ]` klasifikasikan setiap penggunaan:
  - security decision
  - UX hint
  - debug
- `[ ]` buang penggunaan yang mengubah auth flow secara automatik
- `[ ]` gantikan autoroute SSO kepada trigger yang lebih deterministik jika sudah ada
- `[ ]` pastikan landing biasa ke `index.php` masih berfungsi

Acceptance criteria:
- `HTTP_REFERER` tidak lagi menjadi trigger utama untuk redirect auth/SSO
- login page masih boleh dibuka terus tanpa salah branch

Verification:
- buka `index.php` terus dari address bar
- buka `index.php` melalui navigation dalaman
- uji flow OneID/SSO yang sah
- uji browser yang tidak hantar referer

## Task 5: Aktifkan CSRF Validation Sebenar Untuk Manual Login

Status:
- `[~]`

Fail terlibat:
- `public/login.php`
- `public/index.php` jika form token perlu disemak semula

Tujuan:
- memastikan manual login POST tidak boleh dihantar tanpa token session yang sah

Perubahan yang dicadangkan:
- nyah-comment atau implement semula CSRF validation sebenar di `login.php`
- gunakan `hash_equals()`
- pastikan validation hanya dikenakan pada manual login POST
- jangan rosakkan flow SSO yang tidak menggunakan POST form manual yang sama

Checklist implementasi:
- `[ ]` semak bagaimana token dijana pada login form
- `[ ]` pastikan token berada dalam session sebelum form dirender
- `[ ]` aktifkan validation `csrf_token` di `login.php`
- `[ ]` gunakan compare yang selamat
- `[ ]` tetapkan response yang konsisten bila token tiada / salah
- `[ ]` pastikan branch SSO tidak tersekat secara salah oleh check ini

Acceptance criteria:
- manual login POST tanpa token sah ditolak
- manual login POST dengan token sah masih berfungsi
- flow SSO sah tidak terganggu

Verification:
- submit manual login dengan token sah
- submit manual login tanpa token
- submit manual login dengan token salah
- uji flow SSO selepas CSRF check diaktifkan

## Task 6: Tambah Hardening Headers Pada `index.php`

Status:
- `[~]`

Fail terlibat:
- `public/index.php`

Tujuan:
- menyamakan security posture antara login entry point awam dan processor login

Perubahan yang dicadangkan:
- tambah minimum hardening headers:
  - `Cache-Control: no-store, no-cache, must-revalidate, max-age=0`
  - `Pragma: no-cache`
  - `X-Content-Type-Options: nosniff`
  - `Referrer-Policy`
  - CSP yang serasi dengan asset sebenar
- semak dependency luar yang mungkin perlu diubah untuk membolehkan CSP lebih baik

Checklist implementasi:
- `[ ]` audit asset dan script sebenar yang digunakan `index.php`
- `[ ]` tambah header hardening minimum
- `[ ]` semak sama ada CSP semasa perlu benarkan CDN tertentu
- `[ ]` elakkan CSP terlalu ketat sehingga login page rosak
- `[ ]` semak cache-control pada browser/proxy

Acceptance criteria:
- `index.php` memulangkan header hardening yang munasabah
- page login masih render dan berfungsi
- tiada console error besar akibat CSP yang terlalu ketat

Verification:
- semak response headers melalui devtools
- semak load page biasa
- semak console browser
- uji submit login form

## Task 7: Validate Config-Driven URL Dan Asset Path

Status:
- `[~]`

Fail terlibat:
- `public/index.php`

Tujuan:
- mengurangkan risiko URL/asset injection daripada nilai config

Perubahan yang dicadangkan:
- semak field config yang dirender ke `href` dan `src`
- bezakan:
  - asset local path
  - external website URL
  - email / `mailto:`
- validate setiap jenis berdasarkan allowlist yang sesuai

Checklist implementasi:
- `[ ]` audit semua config value yang dirender ke `href`
- `[ ]` audit semua config value yang dirender ke `src`
- `[ ]` enforce local-path-only untuk logo dan favicon jika itu policy yang dipilih
- `[ ]` validate external website sebelum render link
- `[ ]` validate email sebelum render `mailto:`
- `[ ]` fallback kepada text/plain rendering jika nilai tak sah

Acceptance criteria:
- asset config yang tidak sah tidak dirender sebagai URL aktif
- external URL yang sah masih boleh digunakan jika dibenarkan policy
- page login masih memaparkan branding seperti biasa

Verification:
- uji config dengan nilai sah
- uji config dengan nilai kosong
- uji config dengan URL tidak sah
- semak render HTML akhir

## Task 8: Bersihkan Logging Dan Trust Boundary Ringan

Status:
- `[~]`

Fail terlibat:
- `public/index.php`
- `public/login.php`

Tujuan:
- memastikan hardening Fasa 1 tidak disertai kebocoran data auth yang tidak perlu

Perubahan yang dicadangkan:
- semak log SSO / login agar tidak menyimpan token mentah
- semak bahawa error handling pada login boundary kekal generik kepada user
- kekalkan detail teknikal pada server log sahaja jika perlu

Checklist implementasi:
- `[ ]` audit semua `log_login_event(...)`
- `[ ]` audit semua `error_log(...)` pada auth boundary
- `[ ]` pastikan token SSO mentah tidak ditulis
- `[ ]` pastikan alert kepada user kekal generik
- `[ ]` semak file log bukan berada di lokasi web-accessible

Acceptance criteria:
- tiada token auth mentah direkodkan dalam log aplikasi biasa
- mesej user tidak bocor maklumat teknikal dalaman

Verification:
- trigger beberapa branch login gagal
- trigger branch SSO invalid / expired
- semak fail log berkaitan

## Susunan Implementasi Fasa 1

Urutan yang disyorkan:
1. Task 1: session bootstrap unification
2. Task 2: `f_loginID` auth-state check
3. Task 3: server-side cookie clearing
4. Task 4: keluarkan `HTTP_REFERER` dari security decision
5. Task 5: aktifkan CSRF manual login
6. Task 6: hardening headers pada `index.php`
7. Task 7: validate config-driven URL / asset
8. Task 8: bersihkan logging dan trust boundary ringan

Rasional:
- Task 1 hingga Task 4 menguatkan boundary paling awal
- Task 5 menambah perlindungan request forgery pada manual login
- Task 6 dan Task 7 mengurangkan impak XSS / config misuse
- Task 8 menutup kebocoran sokongan tanpa mengubah flow utama

## Checklist Siap Fasa 1

Semua item berikut perlu `[x]` sebelum Fasa 1 dianggap selesai:
- `[ ]` session bootstrap telah dipusatkan
- `[ ]` `index.php` menggunakan `f_loginID` untuk auth-state
- `[ ]` tiada JS-based auth cookie clearing
- `[ ]` `HTTP_REFERER` tidak lagi digunakan sebagai trigger auth utama
- `[ ]` CSRF validation manual login aktif
- `[ ]` hardening headers `index.php` telah ditambah
- `[ ]` config-driven URL / asset telah divalidasi
- `[ ]` logging auth boundary dibersihkan daripada data sensitif

## Acceptance Criteria Fasa 1

Fasa 1 dianggap lulus jika:
- login manual berjaya masih berfungsi
- login manual gagal masih memaparkan alert yang betul
- flow SSO sah masih berfungsi
- tiada regression ketara pada redirect selepas login
- response headers lebih defensible berbanding sebelum ini
- session behavior lebih konsisten antara `index.php` dan `login.php`
- manual login POST tanpa CSRF token sah tidak dibenarkan

## Cadangan Handover Selepas Fasa 1

Selepas Fasa 1 siap dan disahkan:
- teruskan ke Fasa 2 untuk canonicalization `f_loginID`
- jangan terus sentuh parity SSO guardrail sebelum identity canonicalization stabil
- simpan hasil verification Fasa 1 sebagai baseline regression untuk fasa berikutnya
