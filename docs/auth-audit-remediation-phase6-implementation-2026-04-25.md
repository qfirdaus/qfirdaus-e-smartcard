# Auth Audit Remediation Phase 6 Implementation Plan

Tarikh: 2026-04-25

Dokumen ini ialah pecahan task implementasi sebenar untuk Fasa 6 daripada roadmap:
- [auth-audit-remediation-roadmap-2026-04-25.md](/var/www/app/iqs-framework/docs/auth-audit-remediation-roadmap-2026-04-25.md)

Skop Fasa 6:
- readiness operasi untuk model auth hardening yang telah diperkukuh
- semakan schema, index, collation, retention, dan tooling sokongan
- memastikan lockout/throttle/auth audit boleh dioperasikan dengan stabil di production

Status legend:
- `[ ]` belum mula
- `[~]` sedia untuk implementasi
- `[x]` siap dan disahkan

## Sasaran Fasa 6

Hasil yang dikehendaki selepas Fasa 6:
- schema guardrail selari dengan canonicalization auth
- index dan unique constraint menyokong lookup yang deterministic
- clear/unlock tooling admin masih berfungsi selepas semua hardening terdahulu
- retention dan housekeeping untuk table auth guardrail lebih terkawal
- operasi production ada asas monitoring yang memadai

## Prinsip Pelaksanaan Fasa 6

Prinsip:
- jangan ubah schema tanpa semak compatibility dengan code yang telah dikemaskan dalam Fasa 1 hingga Fasa 5
- utamakan kestabilan lookup dan operasi admin
- dokumenkan semua andaian schema yang sebelum ini implicit
- jika migration schema diperlukan, buat secara backward-compatible setakat yang mampu

## Task 1: Audit Schema Sebenar `tbl_auth_login_lockout`

Status:
- `[~]`

Artifak terlibat:
- `tbl_auth_login_lockout`
- migration auth sedia ada dalam `docs`
- `public/classes/User.php`

Tujuan:
- memastikan table lockout benar-benar menyokong lookup dan update pattern semasa

Perubahan / semakan yang dicadangkan:
- semak `SHOW CREATE TABLE tbl_auth_login_lockout`
- pastikan kolum yang digunakan model memang wujud
- sahkan key utama / unique key pada `f_loginID`
- semak collation pada `f_loginID`

Checklist implementasi:
- `[ ]` ambil schema sebenar table lockout
- `[ ]` semak kolum yang diakses oleh `User.php`
- `[ ]` semak unique/index pada `f_loginID`
- `[ ]` semak nullable/default values penting
- `[ ]` dokumenkan sebarang mismatch antara code dan schema

Acceptance criteria:
- schema sebenar `tbl_auth_login_lockout` difahami dan didokumenkan
- unique/index untuk lookup per `f_loginID` jelas wujud atau gap direkodkan

Verification:
- banding `SHOW CREATE TABLE` dengan andaian di code
- uji lookup dan update asas pada environment ujian

## Task 2: Audit Schema Sebenar `tbl_auth_login_throttle`

Status:
- `[~]`

Artifak terlibat:
- `tbl_auth_login_throttle`
- migration auth sedia ada dalam `docs`
- `public/classes/User.php`

Tujuan:
- memastikan table throttle menyokong pattern scope `LOGIN_IP` dan `IP`

Perubahan / semakan yang dicadangkan:
- semak `SHOW CREATE TABLE tbl_auth_login_throttle`
- pastikan unique/index pada `(f_scope_type, f_scope_key)` wujud
- semak saiz kolum `f_scope_key` mencukupi untuk canonical login + IP

Checklist implementasi:
- `[ ]` ambil schema sebenar table throttle
- `[ ]` semak unique/index `(f_scope_type, f_scope_key)`
- `[ ]` semak collation `f_scope_key`
- `[ ]` semak panjang kolum untuk scope key gabungan
- `[ ]` dokumenkan mismatch jika ada

Acceptance criteria:
- schema throttle sejajar dengan pattern query/update semasa
- key untuk `LOGIN_IP` dan `IP` jelas disokong oleh index yang sesuai

Verification:
- banding `SHOW CREATE TABLE` dengan penggunaan di `User.php`
- uji insert/update untuk scope `LOGIN_IP` dan `IP`

## Task 3: Selaraskan Collation Dengan Canonicalization Auth

Status:
- `[~]`

Artifak terlibat:
- `tbl_m_user.f_loginID`
- `tbl_auth_login_lockout.f_loginID`
- `tbl_auth_login_throttle.f_scope_key`

Tujuan:
- memastikan canonicalization di application layer tidak bercanggah dengan cara DB membandingkan string

Perubahan / semakan yang dicadangkan:
- semak collation semua kolum identifier utama
- tentukan sama ada comparison aplikasi bergantung pada:
  - case-insensitive DB collation
  - canonical lowercase/normalized identifier
- jika perlu, dokumenkan migration masa depan untuk samakan collation

Checklist implementasi:
- `[ ]` semak collation `tbl_m_user.f_loginID`
- `[ ]` semak collation `tbl_auth_login_lockout.f_loginID`
- `[ ]` semak collation `tbl_auth_login_throttle.f_scope_key`
- `[ ]` banding dengan standard canonicalization Fasa 2
- `[ ]` rekodkan sebarang percanggahan yang perlu diperbetulkan

Acceptance criteria:
- strategy canonicalization auth dan behavior DB comparison tidak bercanggah
- sebarang gap collation direkodkan dengan jelas

Verification:
- uji lookup/guardrail dengan variasi casing yang setara
- semak row yang disentuh di DB

## Task 4: Semak Kesan `TRIM()` Dan Fungsi Pada Prestasi Query

Status:
- `[~]`

Fail / artifak terlibat:
- `public/classes/User.php`
- schema/index auth tables

Tujuan:
- memastikan penggunaan `TRIM()` dalam WHERE clause tidak mengorbankan index usage secara tidak perlu

Perubahan / semakan yang dicadangkan:
- audit query yang menggunakan:
  - `TRIM(f_loginID)`
  - `TRIM(COALESCE(...))`
- tentukan sama ada canonicalization aplikasi sudah cukup untuk membolehkan query lebih index-friendly pada masa depan

Checklist implementasi:
- `[ ]` inventori query auth yang menggunakan fungsi pada kolum indexed
- `[ ]` semak explain plan jika perlu
- `[ ]` tentukan sama ada query boleh dipermudah selepas Fasa 2 selesai
- `[ ]` dokumenkan perubahan schema/query yang patut dibuat kemudian jika diperlukan

Acceptance criteria:
- implikasi prestasi daripada penggunaan fungsi dalam query auth difahami
- sebarang penambahbaikan masa depan direkodkan

Verification:
- jalankan `EXPLAIN` pada query kritikal jika perlu
- banding prestasi kasar sebelum/selepas canonicalization

## Task 5: Semak Tooling Admin Clear/Unlock Selepas Semua Hardening

Status:
- `[~]`

Fail / artifak terlibat:
- `public/ajax/audit-center-action.php`
- `public/ajax/audit-center-panel.php`
- mana-mana UI admin lockout/throttle

Tujuan:
- memastikan operasi support masih boleh unlock/clear state auth dengan betul

Perubahan / semakan yang dicadangkan:
- semak semua action admin untuk:
  - clear `LOGIN_ID`
  - clear `LOGIN_IP`
  - clear `IP`
- pastikan paparan audit center masih tepat selepas `SSO` turut menggunakan guardrail yang sama

Checklist implementasi:
- `[ ]` audit action clear login lockout
- `[ ]` audit action clear throttle
- `[ ]` semak paparan scope type di audit center
- `[ ]` semak sama ada auth method `SSO` dipaparkan dengan bermakna
- `[ ]` semak permission untuk admin clear operation

Acceptance criteria:
- tooling clear/unlock masih berfungsi untuk state baharu
- admin boleh membezakan dan mengurus rekod guardrail dengan betul

Verification:
- cipta state lockout/throttle
- clear melalui tooling admin
- semak state DB selepas clear

## Task 6: Definisikan Retention Dan Housekeeping Strategy

Status:
- `[~]`

Artifak terlibat:
- `tbl_auth_login_lockout`
- `tbl_auth_login_throttle`
- fail log auth
- audit trail berkaitan

Tujuan:
- mengawal pertumbuhan data guardrail dan memastikan operasi kekal stabil

Perubahan / semakan yang dicadangkan:
- tentukan retention policy untuk:
  - row lockout lama
  - row throttle lama
  - fail log login
- tentukan sama ada cleanup dibuat:
  - secara scheduled job
  - secara manual admin
  - secara hybrid

Checklist implementasi:
- `[ ]` tentukan tempoh retention untuk table guardrail
- `[ ]` tentukan tempoh retention untuk file log auth
- `[ ]` tentukan owner operasi cleanup
- `[ ]` dokumentasikan sama ada cleanup memadam atau mengarkib data
- `[ ]` semak kesan compliance / audit jika data dibuang

Acceptance criteria:
- terdapat strategi retention yang jelas untuk data auth guardrail
- pertumbuhan table/log boleh dikawal

Verification:
- semak dokumen operasi dan job/skrip cleanup jika diwujudkan

## Task 7: Sediakan Baseline Monitoring Dan Metrik Operasi

Status:
- `[~]`

Artifak terlibat:
- audit trail
- fail log auth
- dashboard / admin tooling jika ada

Tujuan:
- memberi operasi cara untuk mengesan serangan auth atau regression lebih awal

Perubahan / semakan yang dicadangkan:
- tentukan metrik minimum yang patut diperhatikan:
  - failed manual login count
  - failed SSO count
  - lockout by `LOGIN_ID`
  - throttle by `LOGIN_IP`
  - throttle by `IP`
  - auto-provision failures
  - SSO invalid / expired handoff count

Checklist implementasi:
- `[ ]` senaraikan metrik minimum auth
- `[ ]` tentukan sumber data setiap metrik
- `[ ]` tentukan sama ada cukup dengan audit center atau perlu report tambahan
- `[ ]` definisikan threshold operasi yang patut diperhatikan

Acceptance criteria:
- pasukan operasi mempunyai senarai metrik auth minimum untuk dipantau
- sumber setiap metrik diketahui

Verification:
- padankan metrik dengan event audit/log yang memang wujud

## Task 8: Final Production Readiness Review

Status:
- `[~]`

Artifak terlibat:
- semua dokumen Fasa 1 hingga Fasa 6
- hasil verification manual
- migration/schema notes

Tujuan:
- memastikan semua remedi auth yang dirancang sudah boleh dinilai sebagai satu pakej hardening yang coherent

Perubahan / semakan yang dicadangkan:
- semak semula semua acceptance criteria Fasa 1 hingga Fasa 6
- kumpulkan isu tertunda yang sengaja ditangguh
- tentukan baki risiko yang masih diterima

Checklist implementasi:
- `[ ]` semak status semua fasa
- `[ ]` semak regression matrix manual login
- `[ ]` semak regression matrix SSO
- `[ ]` semak tooling admin clear/unlock
- `[ ]` semak schema/index notes
- `[ ]` dokumentasikan baki risiko yang belum ditutup

Acceptance criteria:
- terdapat keputusan yang jelas sama ada auth boundary sudah cukup keras untuk rollout
- baki risiko yang tinggal didokumenkan dan difahami

Verification:
- lakukan final review berasaskan semua dokumen remedi dan hasil ujian

## Susunan Implementasi Fasa 6

Urutan yang disyorkan:
1. Task 1: audit schema `tbl_auth_login_lockout`
2. Task 2: audit schema `tbl_auth_login_throttle`
3. Task 3: selaraskan collation dengan canonicalization auth
4. Task 4: semak kesan `TRIM()` dan fungsi pada query
5. Task 5: semak tooling admin clear/unlock
6. Task 6: definisikan retention dan housekeeping
7. Task 7: sediakan baseline monitoring dan metrik operasi
8. Task 8: final production readiness review

Rasional:
- schema dan collation perlu difahami dahulu
- selepas itu barulah tooling dan operasi boleh dinilai dengan tepat
- final readiness review dibuat di hujung selepas semua input lengkap

## Checklist Siap Fasa 6

Semua item berikut perlu `[x]` sebelum Fasa 6 dianggap selesai:
- `[ ]` schema `tbl_auth_login_lockout` telah diaudit
- `[ ]` schema `tbl_auth_login_throttle` telah diaudit
- `[ ]` collation identifier auth telah disemak
- `[ ]` implikasi prestasi query auth telah didokumenkan
- `[ ]` tooling admin clear/unlock telah diverify
- `[ ]` retention dan housekeeping strategy telah ditetapkan
- `[ ]` baseline monitoring auth telah didokumenkan
- `[ ]` final production readiness review telah dibuat

## Acceptance Criteria Fasa 6

Fasa 6 dianggap lulus jika:
- schema, index, dan collation tidak bercanggah dengan design auth yang telah dikeraskan
- tooling operasi masih boleh mengurus lockout/throttle dengan baik
- retention dan monitoring untuk auth boundary telah ditentukan
- terdapat keputusan readiness yang jelas untuk deployment / rollout

## Cadangan Penutup Selepas Fasa 6

Selepas Fasa 6 siap dan disahkan:
- gabungkan semua hasil verification ke dalam satu baseline release note auth hardening
- jika perlu, sediakan migration/schema task susulan yang belum dibuat semasa fasa implementasi utama
- gunakan dokumen Fasa 1 hingga Fasa 6 ini sebagai rujukan rasmi semasa code review dan rollout production
