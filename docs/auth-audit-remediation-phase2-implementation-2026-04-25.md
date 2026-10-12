# Auth Audit Remediation Phase 2 Implementation Plan

Tarikh: 2026-04-25

Dokumen ini ialah pecahan task implementasi sebenar untuk Fasa 2 daripada roadmap:
- [auth-audit-remediation-roadmap-2026-04-25.md](/var/www/app/iqs-framework/docs/auth-audit-remediation-roadmap-2026-04-25.md)

Skop Fasa 2:
- identity canonicalization untuk auth boundary
- standardkan penggunaan `f_loginID` di semua lapisan auth
- bezakan normalization auth daripada output escaping HTML
- sediakan asas yang stabil sebelum parity guardrail `MANUAL` + `SSO` dibuat dalam Fasa 3

Status legend:
- `[ ]` belum mula
- `[~]` sedia untuk implementasi
- `[x]` siap dan disahkan

## Sasaran Fasa 2

Hasil yang dikehendaki selepas Fasa 2:
- semua operasi auth utama menggunakan canonical `f_loginID`
- auth lookup, lockout, throttle, dan audit merujuk identifier yang sama
- `sanitize_string()` tidak lagi digunakan pada laluan auth identifier
- sistem lebih konsisten untuk `STAF`, `PELAJAR`, dan `UMUM`

## Standard Canonicalization Yang Dicadangkan

Sebelum implementasi, tetapkan standard ini:
- trim ruang di pangkal dan hujung
- jangan HTML-encode identifier auth
- kekalkan bentuk data asal yang selamat untuk DB lookup
- untuk emel `UMUM`, pertimbang lowercase sebagai canonical form jika sistem memang menganggap email case-insensitive
- untuk `STAF` dan `PELAJAR`, kekalkan policy casing yang konsisten dan didokumenkan

Nota:
- keputusan muktamad tentang lowercase penuh perlu disahkan terhadap data sebenar `tbl_m_user.f_loginID`
- jika sistem hari ini secara praktikal case-insensitive, lebih baik canonicalization dibuat secara eksplisit daripada bergantung pada collation DB semata-mata

## Task 1: Definisikan Satu Helper Canonical Auth Identifier

Status:
- `[~]`

Fail terlibat:
- `public/login.php`
- `public/controllers/LoginController.php`
- `public/classes/User.php`
- helper auth shared jika sesuai diwujudkan

Tujuan:
- menyediakan satu fungsi normalization yang menjadi source of truth untuk semua auth boundary

Perubahan yang dicadangkan:
- perkenalkan helper seperti konsep:
  - `normalize_auth_identifier(string $loginID): string`
- helper ini mesti ringan, deterministic, dan tidak bergantung pada HTML context

Checklist implementasi:
- `[ ]` tentukan lokasi helper yang sesuai untuk dikongsi
- `[ ]` definisikan behavior canonicalization yang jelas
- `[ ]` dokumentasikan rules untuk:
  - trim
  - casing
  - empty result handling
- `[ ]` elakkan sebarang output escaping dalam helper ini
- `[ ]` pastikan helper boleh digunakan oleh `login.php`, `LoginController.php`, dan `User.php`

Acceptance criteria:
- terdapat satu fungsi canonicalization auth yang menjadi standard tunggal
- helper itu tidak mengubah identifier kepada bentuk HTML-escaped

Verification:
- uji helper dengan input kosong
- uji helper dengan ruang di pangkal/hujung
- uji helper dengan email
- uji helper dengan identifier staf
- uji helper dengan identifier pelajar

## Task 2: Hentikan `sanitize_string()` Pada Auth Path

Status:
- `[~]`

Fail terlibat:
- `public/login.php`

Tujuan:
- memisahkan auth normalization daripada output escaping

Perubahan yang dicadangkan:
- berhenti menggunakan `sanitize_string()` untuk `f_loginID`
- gunakan canonical auth helper baharu
- biarkan escaping berlaku hanya apabila nilai hendak dirender ke HTML

Checklist implementasi:
- `[ ]` cari semua penggunaan `sanitize_string()` untuk auth identifier di `login.php`
- `[ ]` gantikan dengan helper canonicalization auth
- `[ ]` semak branch manual login
- `[ ]` semak branch SSO handoff yang mengisi `f_loginID`
- `[ ]` pastikan `f_password` handling tidak berubah

Acceptance criteria:
- tiada lagi `htmlspecialchars()` digunakan untuk normalize `f_loginID` pada auth path
- manual login dan SSO masih menggunakan identifier yang sah

Verification:
- submit manual login biasa
- submit login dengan identifier yang mempunyai ruang hujung
- submit SSO handoff dengan identifier sah

## Task 3: Standardkan Canonicalization Sebelum Guardrail Lookup

Status:
- `[~]`

Fail terlibat:
- `public/login.php`
- `public/classes/User.php`

Tujuan:
- memastikan satu identifier hanya menghasilkan satu state guardrail

Perubahan yang dicadangkan:
- gunakan canonical identifier sebelum:
  - `getLoginLockoutState()`
  - `recordFailedLoginAttempt()`
  - `clearLoginLockout()`
  - pembinaan scope `LOGIN_IP`
- semak sama ada guardrail model sendiri juga patut normalize sekali lagi secara defensif

Checklist implementasi:
- `[ ]` canonicalize `f_loginID` seawal mungkin di `login.php`
- `[ ]` guna nilai itu untuk semua operasi lockout
- `[ ]` guna nilai itu untuk semua operasi throttle `LOGIN_IP`
- `[ ]` pertimbang defensive canonicalization sekali lagi di dalam `User.php`
- `[ ]` semak tidak ada mix antara raw identifier dan canonical identifier

Acceptance criteria:
- lookup guardrail dan write guardrail menggunakan identifier canonical yang sama
- failed attempts untuk identifier yang sama masuk ke footprint guardrail yang sama

Verification:
- gagal login beberapa kali menggunakan variasi casing/spacing yang setara
- semak row lockout/throttle yang terhasil

## Task 4: Standardkan Canonicalization Dalam `LoginController->authenticate()`

Status:
- `[~]`

Fail terlibat:
- `public/controllers/LoginController.php`

Tujuan:
- memastikan controller auth tidak lagi bergantung pada raw identifier yang mungkin berbeza dengan guardrail layer

Perubahan yang dicadangkan:
- canonicalize `loginID` di pintu masuk `authenticate()`
- gunakan identifier canonical itu untuk:
  - empty check
  - `findByLoginID()`
  - audit login fail
  - branch unknown user
- pastikan session finalization tetap menggunakan `resolvedLoginID` authoritative dari DB jika ada

Checklist implementasi:
- `[ ]` canonicalize `$loginID` seawal mungkin dalam `authenticate()`
- `[ ]` gunakan canonical `$loginID` pada `findByLoginID()`
- `[ ]` gunakan canonical `$loginID` pada `auditLoginFail()`
- `[ ]` semak branch SSO auto-provision supaya comparison juga konsisten
- `[ ]` kekalkan resolved identity dari DB sebagai session source of truth selepas login berjaya

Acceptance criteria:
- controller auth menerima dan menggunakan identifier canonical yang konsisten dengan guardrail layer
- session finalization masih mengambil identity authoritative dari DB row

Verification:
- uji manual login berjaya
- uji manual login gagal
- uji SSO resolve path

## Task 5: Standardkan Canonicalization Dalam `User::findByLoginID()`

Status:
- `[~]`

Fail terlibat:
- `public/classes/User.php`

Tujuan:
- memastikan model lookup auth menggunakan standard identifier yang sama seperti controller dan guardrail

Perubahan yang dicadangkan:
- canonicalize `$loginID` pada pintu masuk `findByLoginID()`
- dokumentasikan expectation method ini menerima canonical identifier
- kekalkan prepared statement

Checklist implementasi:
- `[ ]` tambah canonicalization pada awal `findByLoginID()`
- `[ ]` semak method lain yang bergantung kepada `findByLoginID()`
- `[ ]` pastikan lookup user kosong tetap return `null`
- `[ ]` semak compatibility dengan `attemptSsoAutoProvision()`

Acceptance criteria:
- `findByLoginID()` konsisten dengan standard identifier baharu
- prepared statement kekal digunakan

Verification:
- uji lookup untuk identifier biasa
- uji lookup untuk identifier dengan ruang hujung/pangkal

## Task 6: Standardkan Scope Builder `LOGIN_IP`

Status:
- `[~]`

Fail terlibat:
- `public/login.php`

Tujuan:
- memastikan `LOGIN_IP` scope dibina daripada canonical identifier yang sama dengan auth lookup

Perubahan yang dicadangkan:
- ubah `login_build_identifier_ip_scope()` supaya menggunakan canonical auth identifier
- elakkan normalization tambahan yang bercanggah dengan helper canonical utama

Checklist implementasi:
- `[ ]` semak behavior semasa `strtolower(trim($loginID))`
- `[ ]` ganti dengan helper canonical auth jika sesuai
- `[ ]` tentukan sama ada lowercase masih diperlukan atau helper sudah mengurusnya
- `[ ]` pastikan `scope_key` konsisten dengan lookup auth yang sebenar

Acceptance criteria:
- `LOGIN_IP` scope tidak lagi dibina dengan standard berbeza daripada auth lookup
- satu identifier canonical hanya menghasilkan satu `LOGIN_IP` scope

Verification:
- uji beberapa failed attempts dari IP sama untuk identifier yang sama
- semak `scope_key` dalam `tbl_auth_login_throttle`

## Task 7: Semak Audit Dan Logging Menggunakan Canonical Identifier

Status:
- `[~]`

Fail terlibat:
- `public/login.php`
- `public/controllers/LoginController.php`

Tujuan:
- memastikan audit dan log boleh dipercayai sebagai rekod bagi identity yang sama

Perubahan yang dicadangkan:
- gunakan canonical `f_loginID` untuk semua event auth yang belum authenticated
- apabila login berjaya, kekalkan resolved `f_loginID` daripada DB untuk audit success
- semak `log_login_event(...)`
- semak `audit_login_guardrail_event(...)`
- semak helper audit dalaman controller

Checklist implementasi:
- `[ ]` standardkan identifier pada login fail audit
- `[ ]` standardkan identifier pada guardrail audit
- `[ ]` standardkan identifier pada log file auth biasa
- `[ ]` semak tidak berlaku campuran raw/canonical/resolved identifier tanpa sebab
- `[ ]` kekalkan user-facing output tanpa expose identifier mentah yang tak perlu

Acceptance criteria:
- audit dan log auth menunjukkan identifier yang konsisten
- tidak ada mismatch jelas antara row guardrail dan event audit

Verification:
- trigger login fail beberapa kali
- trigger guardrail lock
- trigger login berjaya
- banding log, audit, dan row DB

## Task 8: Semak Implikasi DB Collation Dan Index Secara Ringan

Status:
- `[~]`

Fail / artifak terlibat:
- `tbl_m_user`
- `tbl_auth_login_lockout`
- `tbl_auth_login_throttle`
- dokumen schema jika perlu

Tujuan:
- memastikan canonicalization yang dipilih tidak bercanggah dengan cara DB menyimpan dan mencari identifier

Perubahan yang dicadangkan:
- semak collation bagi `f_loginID` di `tbl_m_user`
- semak collation dan index pada table lockout/throttle
- dokumenkan jika canonicalization aplikasi bergantung atau tidak bergantung pada collation DB

Checklist implementasi:
- `[ ]` semak `tbl_m_user.f_loginID`
- `[ ]` semak `tbl_auth_login_lockout.f_loginID`
- `[ ]` semak `tbl_auth_login_throttle.f_scope_key`
- `[ ]` semak unique/index berkaitan
- `[ ]` dokumenkan sebarang mismatch yang perlu ditangani pada Fasa 6 jika belum hendak diubah sekarang

Acceptance criteria:
- tiada percanggahan jelas antara canonicalization app dan behavior collation DB
- isu schema yang belum disentuh direkodkan untuk Fasa 6

Verification:
- banding hasil lookup DB dengan canonicalization yang dipilih
- semak create table/index sebenar

## Susunan Implementasi Fasa 2

Urutan yang disyorkan:
1. Task 1: helper canonical auth identifier
2. Task 2: hentikan `sanitize_string()` pada auth path
3. Task 3: standardkan canonicalization sebelum guardrail lookup
4. Task 4: standardkan `authenticate()`
5. Task 5: standardkan `findByLoginID()`
6. Task 6: standardkan `LOGIN_IP` scope builder
7. Task 7: standardkan audit/log identifier
8. Task 8: semak implikasi DB collation dan index

Rasional:
- helper tunggal perlu wujud dahulu
- selepas itu barulah semua lapisan boleh dipindah ke standard yang sama
- semakan DB di hujung fasa memberi peluang mengesahkan bahawa standard aplikasi selari dengan schema semasa

## Checklist Siap Fasa 2

Semua item berikut perlu `[x]` sebelum Fasa 2 dianggap selesai:
- `[ ]` helper canonical auth identifier diwujudkan dan digunakan
- `[ ]` `sanitize_string()` tidak lagi digunakan pada auth identifier path
- `[ ]` guardrail lookup dan write menggunakan canonical identifier
- `[ ]` `LoginController->authenticate()` menggunakan canonical identifier
- `[ ]` `User::findByLoginID()` menggunakan canonical identifier
- `[ ]` `LOGIN_IP` scope dibina daripada canonical identifier yang sama
- `[ ]` audit/log auth menggunakan identifier konsisten
- `[ ]` implikasi collation/index direkodkan

## Acceptance Criteria Fasa 2

Fasa 2 dianggap lulus jika:
- login manual berjaya masih berfungsi
- login SSO sah masih berfungsi
- failed attempts untuk identifier yang sama tidak terpecah kepada footprint guardrail berlainan kerana normalization yang tidak konsisten
- audit auth lebih konsisten antara login fail, lockout, dan login success
- tiada lagi HTML-escaping pada auth identifier sebelum auth diproses

## Cadangan Handover Selepas Fasa 2

Selepas Fasa 2 siap dan disahkan:
- teruskan ke Fasa 3 untuk parity guardrail `MANUAL` + `SSO`
- gunakan hasil verification Fasa 2 sebagai baseline untuk uji kesan guardrail pada flow SSO
