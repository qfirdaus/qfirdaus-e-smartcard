# Auth Audit Remediation Phase 3 Implementation Plan

Tarikh: 2026-04-25

Dokumen ini ialah pecahan task implementasi sebenar untuk Fasa 3 daripada roadmap:
- [auth-audit-remediation-roadmap-2026-04-25.md](/var/www/app/iqs-framework/docs/auth-audit-remediation-roadmap-2026-04-25.md)

Skop Fasa 3:
- parity guardrail untuk `MANUAL` dan `SSO`
- memastikan flow `SSO` tidak lagi bypass lapisan `lockout` dan `throttle`
- kekalkan UX login sah semasa sambil menambah consistency pada anti-brute-force / anti-abuse control

Status legend:
- `[ ]` belum mula
- `[~]` sedia untuk implementasi
- `[x]` siap dan disahkan

## Sasaran Fasa 3

Hasil yang dikehendaki selepas Fasa 3:
- semua auth attempt yang sah dari sudut routing melalui guardrail asas
- flow `SSO` dan `MANUAL` sama-sama tertakluk pada lapisan:
  - `LOGIN_ID`
  - `LOGIN_IP`
  - `IP`
- failed `SSO` attempt direkodkan
- successful `SSO` login membersihkan state guardrail yang berkaitan
- audit event guardrail membezakan `SSO` dan `MANUAL`

## Prinsip Pelaksanaan Fasa 3

Fasa ini sensitif kerana ia menyentuh flow `SSO`, jadi pelaksanaan patut ikut prinsip berikut:
- jangan ubah definisi kejayaan login `SSO` yang sah
- jangan ubah source-of-truth auth handoff yang telah validated
- tambah enforcement pada orchestration layer dahulu, bukan deep di controller jika tidak perlu
- jika threshold `SSO` perlu berbeza, gunakan config berasingan dan bukan bypass total
- semua change mesti diuji pada flow `SSO` berjaya, gagal, invalid, dan expired

## Task 1: Definisikan Polisi Guardrail Untuk `SSO`

Status:
- `[~]`

Fail / artifak terlibat:
- `public/login.php`
- config auth security jika perlu diperluas
- dokumen auth policy jika ada

Tujuan:
- menetapkan dengan jelas sama ada `SSO` menggunakan threshold guardrail yang sama atau threshold tersendiri

Perubahan yang dicadangkan:
- pilih satu daripada dua pendekatan:
  - gunakan threshold yang sama seperti `MANUAL`
  - gunakan threshold berasingan khusus untuk `SSO`
- walau apa pun pilihan, `SSO` tidak lagi boleh bypass guardrail sepenuhnya

Checklist implementasi:
- `[ ]` tentukan sama ada threshold `SSO` sama atau tersendiri
- `[ ]` jika tersendiri, tambah config yang jelas untuk:
  - `sso_max_attempts`
  - `sso_lock_seconds`
  - `sso_identifier_ip_max_attempts`
  - `sso_identifier_ip_lock_seconds`
  - `sso_ip_max_attempts`
  - `sso_ip_lock_seconds`
- `[ ]` dokumentasikan fallback jika config `SSO` tidak wujud
- `[ ]` pastikan default awal selamat dan tidak terlalu agresif

Acceptance criteria:
- terdapat polisi guardrail yang jelas untuk `SSO`
- tiada lagi ambiguity sama ada `SSO` patut dibatasi atau tidak

Verification:
- semak config auth security yang terhasil
- pastikan nilai default boleh digunakan tanpa regression

## Task 2: Samakan Pre-Auth Guardrail Check Untuk `SSO`

Status:
- `[~]`

Fail terlibat:
- `public/login.php`

Tujuan:
- memastikan `SSO` attempt melalui semakan pre-auth yang sama seperti `MANUAL`

Perubahan yang dicadangkan:
- ubah blok guardrail sebelum `authenticate()` supaya tidak lagi dibalut `if (!$isSsoAttempt)` secara penuh
- untuk `SSO`, tetap semak:
  - lockout per `LOGIN_ID`
  - throttle per `LOGIN_IP`
  - throttle per `IP`
- guna auth method yang betul dalam audit event

Checklist implementasi:
- `[ ]` kenal pasti blok semasa yang hanya berjalan untuk `MANUAL`
- `[ ]` refactor supaya guardrail boleh dipakai untuk kedua-dua method
- `[ ]` pastikan `f_loginID` canonical telah tersedia sebelum semakan
- `[ ]` tentukan nilai attempts remaining yang dipaparkan untuk `SSO`
- `[ ]` audit event guardrail perlu simpan `auth_method = SSO` bila branch itu digunakan

Acceptance criteria:
- sebelum `authenticate()` dipanggil, flow `SSO` juga tertakluk pada state guardrail yang aktif
- `SSO` request yang melepasi threshold akan dihalang sama seperti `MANUAL`

Verification:
- simulasi `SSO` attempt ketika row lockout aktif
- simulasi `SSO` attempt ketika `LOGIN_IP` throttle aktif
- simulasi `SSO` attempt ketika `IP` throttle aktif

## Task 3: Rekod Failed `SSO` Attempt Ke Model Lockout

Status:
- `[~]`

Fail terlibat:
- `public/login.php`
- `public/classes/User.php`

Tujuan:
- memastikan kegagalan `SSO` benar-benar meningkatkan state guardrail

Perubahan yang dicadangkan:
- pada branch login gagal untuk `SSO`, panggil:
  - `recordFailedLoginAttempt(...)`
  - `recordFailedLoginThrottle('LOGIN_IP', ...)`
  - `recordFailedLoginThrottle('IP', ...)`
- kekalkan auth method dalam audit sebagai `SSO`

Checklist implementasi:
- `[ ]` kenal pasti branch `if ($isSsoAttempt)` dalam gagal login
- `[ ]` ubah supaya failed `SSO` tidak hanya set alert dan redirect
- `[ ]` rekod failed `LOGIN_ID` lockout untuk `SSO`
- `[ ]` rekod failed `LOGIN_IP` throttle untuk `SSO`
- `[ ]` rekod failed `IP` throttle untuk `SSO`
- `[ ]` pastikan mesej kepada user kekal sesuai dan tidak bocor maklumat dalaman

Acceptance criteria:
- failed `SSO` attempt meninggalkan kesan pada state guardrail
- guardrail counter meningkat untuk laluan `SSO`

Verification:
- cuba beberapa `SSO` failure berturut-turut
- semak row pada `tbl_auth_login_lockout`
- semak row pada `tbl_auth_login_throttle`

## Task 4: Clear Guardrail State Pada Successful `SSO` Login

Status:
- `[~]`

Fail terlibat:
- `public/login.php`

Tujuan:
- menyamakan behavior success path antara `MANUAL` dan `SSO`

Perubahan yang dicadangkan:
- ubah branch berjaya login supaya clear state guardrail untuk `SSO` juga
- kekalkan canonical identifier yang sama untuk clear operation

Checklist implementasi:
- `[ ]` kenal pasti blok clear guardrail selepas `$loginOk`
- `[ ]` buang restriction yang hanya membenarkan clear semasa `MANUAL`
- `[ ]` clear `LOGIN_ID` state untuk `SSO`
- `[ ]` clear `LOGIN_IP` state untuk `SSO`
- `[ ]` clear `IP` state untuk `SSO`
- `[ ]` pastikan clear hanya berlaku selepas auth success sebenar

Acceptance criteria:
- successful `SSO` login membersihkan state guardrail yang berkaitan
- tiada stale lockout kekal selepas SSO login sah

Verification:
- jana failed attempts `SSO`
- login `SSO` berjaya sebelum had lock penuh
- semak row guardrail dibersihkan

## Task 5: Standardkan Audit Guardrail Mengikut Auth Method

Status:
- `[~]`

Fail terlibat:
- `public/login.php`
- mana-mana helper audit berkaitan

Tujuan:
- memastikan incident response boleh membezakan lockout / throttle yang datang dari `SSO` atau `MANUAL`

Perubahan yang dicadangkan:
- pastikan `audit_login_guardrail_event(...)` menerima method sebenar untuk semua branch
- semak file log auth biasa juga membezakan event `SSO` vs `MANUAL`

Checklist implementasi:
- `[ ]` semak semua panggilan `audit_login_guardrail_event(...)`
- `[ ]` ganti hardcoded `MANUAL` dengan auth method dinamik di branch yang sesuai
- `[ ]` semak `log_login_event(...)` jika perlu tambah status yang lebih jelas untuk `SSO`
- `[ ]` pastikan taxonomy event audit tidak mengelirukan

Acceptance criteria:
- audit guardrail boleh membezakan antara `SSO` dan `MANUAL`
- log incident auth lebih berguna untuk analisis serangan

Verification:
- trigger lockout manual
- trigger lockout SSO
- semak audit trail dan log auth

## Task 6: Jaga UX Dan Alert Supaya Tidak Bocor Berlebihan

Status:
- `[~]`

Fail terlibat:
- `public/login.php`

Tujuan:
- menambah enforcement tanpa mengubah user-facing flow menjadi terlalu bising atau terlalu spesifik

Perubahan yang dicadangkan:
- kekalkan alert lockout yang sedia ada, tetapi pastikan ia sesuai untuk `SSO`
- semak mesej selepas guardrail tercetus untuk `SSO`
- elakkan mesej yang terlalu membezakan antara:
  - user tidak wujud
  - user wujud tetapi throttled
  - token handoff sah tetapi account belum sedia

Checklist implementasi:
- `[ ]` semak reuse alert `login_locked_title` dan `login_locked_msg` untuk `SSO`
- `[ ]` pastikan wait time boleh dipaparkan dengan betul untuk `SSO`
- `[ ]` semak lockout message tidak menjadi policy oracle yang terlalu kuat
- `[ ]` pastikan redirect selepas guardrail masih konsisten

Acceptance criteria:
- enforcement guardrail baharu tidak menyebabkan UX `SSO` menjadi mengelirukan
- user masih menerima mesej yang munasabah apabila cuba lagi terlalu cepat

Verification:
- uji `SSO` failure biasa
- uji `SSO` yang sudah locked
- semak mesej yang dipaparkan

## Task 7: Tambah Regression Verification Untuk Flow `SSO`

Status:
- `[~]`

Fail / artifak terlibat:
- dokumen ujian manual / checklist QA
- `public/login.php`
- `public/controllers/LoginController.php`

Tujuan:
- memastikan penambahan guardrail tidak merosakkan flow `SSO` yang sah

Perubahan yang dicadangkan:
- sediakan matrix verification minimum untuk flow berikut:
  - handoff valid + login success
  - handoff valid + user not provisioned
  - handoff expired
  - handoff invalid
  - repeated failed `SSO` attempts hingga lock
  - successful `SSO` selepas failed attempts belum capai hard lock

Checklist implementasi:
- `[ ]` dokumentasikan test cases minimum
- `[ ]` bezakan expected result untuk `SSO` vs `MANUAL`
- `[ ]` pastikan autoprovision path turut diuji
- `[ ]` pastikan self-confirm SSO path jika ada turut diuji

Acceptance criteria:
- semua test case kritikal `SSO` mempunyai expected result yang jelas
- tiada regression ketara pada flow `SSO` berjaya

Verification:
- jalankan matrix ujian minimum selepas implementasi

## Task 8: Semak Kesan Kepada Admin Clear/Unlock Tooling

Status:
- `[~]`

Fail / artifak terlibat:
- `public/ajax/audit-center-action.php`
- mana-mana UI admin berkaitan lockout clear

Tujuan:
- memastikan tooling pentadbir untuk clear lockout/throttle masih relevan selepas `SSO` turut menggunakan guardrail yang sama

Perubahan yang dicadangkan:
- semak sama ada clear action sedia ada cukup kerana model yang sama akan digunakan
- semak audit center paparan scope `LOGIN_IP`, `IP`, dan `LOGIN_ID`
- semak dokumentasi operasi supaya admin faham lockout kini juga terpakai pada `SSO`

Checklist implementasi:
- `[ ]` semak clear action untuk `LOGIN_ID`
- `[ ]` semak clear action untuk `LOGIN_IP`
- `[ ]` semak clear action untuk `IP`
- `[ ]` semak UI audit center untuk rekod auth method `SSO`
- `[ ]` kemas kini nota operasi jika perlu

Acceptance criteria:
- admin masih boleh clear lockout/throttle seperti biasa
- rekod `SSO` tidak menyebabkan tooling sedia ada gagal atau mengelirukan

Verification:
- cipta state guardrail daripada `SSO`
- clear melalui tooling admin
- semak state DB selepas clear

## Susunan Implementasi Fasa 3

Urutan yang disyorkan:
1. Task 1: definisikan polisi guardrail `SSO`
2. Task 2: samakan pre-auth guardrail check
3. Task 3: rekod failed `SSO` attempt
4. Task 4: clear guardrail state pada successful `SSO`
5. Task 5: standardkan audit guardrail ikut auth method
6. Task 6: jaga UX dan alert
7. Task 7: tambah regression verification `SSO`
8. Task 8: semak tooling admin clear/unlock

Rasional:
- polisi guardrail perlu ditetapkan dahulu
- enforcement pre-auth dan failed/success write perlu datang sebelum audit/UX refinement
- verification dan tooling review dibuat selepas flow utama siap

## Checklist Siap Fasa 3

Semua item berikut perlu `[x]` sebelum Fasa 3 dianggap selesai:
- `[ ]` polisi guardrail `SSO` ditetapkan
- `[ ]` pre-auth guardrail digunakan untuk `SSO`
- `[ ]` failed `SSO` attempt direkod pada model lockout/throttle
- `[ ]` successful `SSO` login membersihkan state guardrail
- `[ ]` audit guardrail membezakan `SSO` dan `MANUAL`
- `[ ]` UX alert `SSO` selepas hardening kekal munasabah
- `[ ]` regression verification `SSO` tersedia dan lulus
- `[ ]` tooling admin clear/unlock kekal berfungsi

## Acceptance Criteria Fasa 3

Fasa 3 dianggap lulus jika:
- flow `SSO` tidak lagi bypass lapisan lockout/throttle
- login `SSO` sah masih berjaya seperti biasa
- repeated failed `SSO` attempts boleh menyebabkan guardrail tercetus
- successful `SSO` login membersihkan state guardrail yang sesuai
- audit trail boleh membezakan sama ada lockout berpunca daripada `SSO` atau `MANUAL`

## Cadangan Handover Selepas Fasa 3

Selepas Fasa 3 siap dan disahkan:
- teruskan ke Fasa 4 untuk policy simplification dan pengurangan enumeration
- gunakan hasil audit/verification Fasa 3 untuk memutuskan sama ada threshold `SSO` perlu dituning semula
