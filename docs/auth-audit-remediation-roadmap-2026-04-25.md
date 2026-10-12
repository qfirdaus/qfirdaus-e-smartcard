# Auth Audit Remediation Roadmap

Tarikh: 2026-04-25

Skop audit ini merangkumi laluan auth berikut:
- `public/index.php`
- `public/login.php`
- `public/controllers/LoginController.php`
- `public/classes/User.php`

Matlamat dokumen ini:
- mendokumenkan hasil audit keselamatan auth boundary
- memecahkan remedi kepada fasa yang selamat dan rendah risiko
- menyediakan checklist status per fasa
- menyediakan acceptance criteria supaya setiap fasa boleh diverify sebelum bergerak ke fasa seterusnya

Status legend:
- `[ ]` belum mula
- `[~]` dalam perancangan / untuk verification
- `[x]` siap dan disahkan

## Ringkasan Dapatan Audit

Risiko utama yang dikenal pasti:
- `index.php` memulakan session terlalu awal dan tidak konsisten dengan hardening dalam `includes/init.php`
- `index.php` masih membuat semakan auth-state berasaskan `f_stafID` dan bukan identity standard `f_loginID`
- `index.php` bergantung pada `HTTP_REFERER` untuk sebahagian keputusan flow SSO
- `index.php` membersihkan cookie berkaitan SSO melalui JavaScript output, bukan server-side
- `login.php` menggunakan `sanitize_string()` berasaskan `htmlspecialchars()` untuk auth identifier
- guardrail `lockout` dan `throttle` hanya dikuatkuasakan pada login `MANUAL`, bukan `SSO`
- `LoginController->authenticate()` menggunakan category guessing untuk unknown account melalui bentuk `loginID`
- canonicalization identifier tidak cukup konsisten antara auth lookup, guardrail, audit, dan scope `LOGIN_IP`
- tiada high-confidence SQL injection ditemui dalam `LoginController.php` atau model lockout/throttle di `User.php`, tetapi auth integrity dan brute-force consistency perlu diperkukuh

## Prinsip Pelaksanaan

Semua remedi dicadangkan dengan prinsip berikut:
- jangan rosakkan flow login berjaya sedia ada
- jangan ganggu flow SSO yang sah
- buat perubahan yang tidak mengubah business outcome dahulu
- pusatkan standard identity kepada `tbl_m_user.f_loginID`
- pastikan semua guardrail dan audit menggunakan canonical identifier yang sama
- bezakan dengan jelas antara input normalization untuk auth dan output escaping untuk HTML

## Fasa 1: Hardening Tanpa Ubah Behavior

Objektif:
- menguatkan entry point auth tanpa mengubah policy login dan tanpa menukar business outcome berjaya/gagal login

Status:
- `[~]` belum dilaksanakan, sedia untuk verification

Fail terlibat:
- `public/index.php`
- `public/login.php`
- `public/includes/init.php`

Checklist:
- `[ ]` pusatkan session bootstrap ke `includes/init.php`
- `[ ]` buang `session_start()` awal dari `index.php`
- `[ ]` samakan semakan auth-state di `index.php` kepada `f_loginID`
- `[ ]` ganti pembersihan cookie SSO berasaskan JavaScript kepada `setcookie()` server-side
- `[ ]` hentikan `HTTP_REFERER` sebagai trigger utama auto-route SSO
- `[ ]` tambah hardening headers minimum pada `index.php`
- `[ ]` aktifkan semula CSRF validation untuk manual login di `login.php`
- `[ ]` semak dan validate semua config-driven `href` / `src` di login entry page
- `[ ]` kekalkan no-cache policy pada login boundary
- `[ ]` semak log/audit supaya tiada token sensitif atau data auth mentah bocor

Acceptance criteria:
- `index.php` tidak lagi memulakan session sebelum `includes/init.php`
- semua user category yang sudah login (`STAF`, `PELAJAR`, `UMUM`) dikenali melalui `f_loginID`
- tiada cookie auth/SSO dibersihkan melalui echoed JavaScript
- auto-route auth tidak lagi bergantung utama pada `HTTP_REFERER`
- manual login request tanpa CSRF token sah ditolak
- login page memulangkan hardening headers yang konsisten
- flow login manual dan SSO yang sah masih berfungsi seperti sebelum ini

Verification cadangan:
- buka `index.php` dengan session kosong, session sah, dan session cookie rosak
- uji login manual normal
- uji redirect alert selepas login gagal
- uji entry SSO yang sah
- semak response headers di browser devtools

## Fasa 2: Identity Canonicalization

Objektif:
- mewujudkan satu standard identifier auth yang konsisten untuk lookup, guardrail, dan audit

Status:
- `[~]` belum dilaksanakan, sedia untuk verification

Fail terlibat:
- `public/login.php`
- `public/controllers/LoginController.php`
- `public/classes/User.php`

Checklist:
- `[ ]` perkenalkan fungsi canonicalization tunggal untuk auth identifier
- `[ ]` gantikan `sanitize_string()` pada auth path dengan normalizer khusus auth
- `[ ]` hentikan penggunaan `htmlspecialchars()` sebagai normalization auth input
- `[ ]` gunakan canonical `f_loginID` sebelum:
  - auth lookup
  - lockout lookup
  - throttle lookup
  - audit/logging
  - pembinaan scope `LOGIN_IP`
- `[ ]` standardkan policy casing untuk `f_loginID`
- `[ ]` semak kesan canonicalization terhadap `UMUM` yang menggunakan emel
- `[ ]` semak kesan canonicalization terhadap `STAF` dan `PELAJAR`

Acceptance criteria:
- satu identifier login menghasilkan satu footprint guardrail yang konsisten
- lookup auth dan rekod lockout/throttle menggunakan canonical form yang sama
- tiada lagi auth identifier yang di-HTML-encode sebelum auth dijalankan
- `STAF`, `PELAJAR`, dan `UMUM` masih boleh login menggunakan identifier masing-masing

Verification cadangan:
- uji login dengan variasi ruang kosong hujung/pangkal
- uji email login dengan variasi huruf besar/kecil jika sistem menganggapnya case-insensitive
- uji bahawa failed attempt untuk identifier yang sama masuk ke row guardrail yang sama

## Fasa 3: Guardrail Parity Manual + SSO

Objektif:
- memastikan `MANUAL` dan `SSO` tertakluk kepada lapisan perlindungan brute-force / abuse yang setara

Status:
- `[~]` belum dilaksanakan, sedia untuk verification

Fail terlibat:
- `public/login.php`
- `public/classes/User.php`
- `public/controllers/LoginController.php`

Checklist:
- `[ ]` kenakan semakan guardrail kepada flow `SSO` juga
- `[ ]` tentukan sama ada threshold `SSO` sama atau berasingan daripada `MANUAL`
- `[ ]` rekod failed SSO attempt ke `tbl_auth_login_lockout`
- `[ ]` rekod failed SSO attempt ke `tbl_auth_login_throttle` untuk scope `LOGIN_IP`
- `[ ]` rekod failed SSO attempt ke `tbl_auth_login_throttle` untuk scope `IP`
- `[ ]` clear guardrail state selepas successful SSO login
- `[ ]` pastikan audit event membezakan `MANUAL` dan `SSO`
- `[ ]` pastikan guardrail message user kekal terkawal dan tidak bocor maklumat berlebihan

Acceptance criteria:
- SSO path tidak lagi bypass lockout/throttle
- successful SSO login membersihkan state guardrail yang berkaitan
- failed SSO attempt meningkatkan counter guardrail dengan cara yang dijangka
- user yang sah masih boleh login SSO tanpa perubahan UX besar selain mesej guardrail jika had dicapai

Verification cadangan:
- simulasi beberapa percubaan SSO gagal berturut-turut
- semak row pada `tbl_auth_login_lockout`
- semak row pada `tbl_auth_login_throttle`
- uji successful SSO login selepas threshold belum penuh

## Fasa 4: Policy Simplification Dan Enumeration Reduction

Objektif:
- mengurangkan ambiguity pada keputusan auth dan mengecilkan enumeration surface untuk unknown account

Status:
- `[~]` belum dilaksanakan, sedia untuk verification

Fail terlibat:
- `public/controllers/LoginController.php`
- `public/login.php`

Checklist:
- `[ ]` hentikan penggunaan `normalizeLoginCategory(null, $loginID)` sebagai policy gate untuk unknown account
- `[ ]` hadkan `normalizeLoginCategory()` kepada helper sokongan sahaja
- `[ ]` untuk unknown manual account, gunakan response lebih generik
- `[ ]` untuk SSO, gunakan hanya source authoritative daripada handoff yang telah disahkan
- `[ ]` semak semula exception seperti:
  - `SSO_FIRST_LOGIN_REQUIRED`
  - `MANUAL_ACCOUNT_NOT_READY`
  - `SSO_ACCOUNT_NOT_PROVISIONED`
- `[ ]` asingkan internal audit reason daripada user-facing response
- `[ ]` semak semula branch account-state supaya tidak menjadi policy oracle yang terlalu kuat

Acceptance criteria:
- keputusan auth untuk unknown account tidak lagi bergantung pada bentuk identifier yang dihantar
- mesej kepada user unauthenticated menjadi lebih seragam
- audit backend masih menyimpan sebab sebenar untuk troubleshooting
- flow account sah yang sedia ada tidak berubah

Verification cadangan:
- cuba login manual dengan identifier yang tidak wujud untuk pattern `STAF`, `PELAJAR`, dan emel
- cuba SSO handoff dengan identity yang tidak provisioned
- pastikan mesej user lebih generik tetapi audit masih lengkap

## Fasa 5: SSO Contract Tightening

Objektif:
- memperkukuh boundary SSO tanpa mengubah UX utama untuk user yang sah

Status:
- `[~]` belum dilaksanakan, sedia untuk verification

Fail terlibat:
- `public/login.php`
- `public/controllers/LoginController.php`
- `public/sso_sp_client.php`
- mana-mana helper SSO berkaitan

Checklist:
- `[ ]` semak semula kontrak `sso_auth_handoff`
- `[ ]` pastikan handoff hanya boleh digunakan untuk identity yang telah divalidasi
- `[ ]` pertimbang one-time use semantics untuk handoff
- `[ ]` pertimbang replay protection tambahan
- `[ ]` pastikan handoff invalid / expired sentiasa dibersihkan
- `[ ]` semak auto-provision supaya tidak menjadi oracle kepada pihak luar
- `[ ]` pastikan audit tidak menyimpan token SSO mentah
- `[ ]` semak assert defensif pada branch `authenticate()` untuk memastikan flow SSO hanya datang dari source yang dibenarkan

Acceptance criteria:
- SSO handoff tidak boleh dipakai semula tanpa valid state baharu
- handoff invalid/expired tidak meninggalkan partial auth state
- successful SSO login sah masih berfungsi
- auto-provision hanya berlaku di bawah syarat yang jelas dan diaudit

Verification cadangan:
- uji handoff valid
- uji handoff expired
- uji handoff invalid
- uji replay pada handoff lama
- uji scenario auto-provision yang sah dan yang patut gagal

## Fasa 6: Schema, Index Dan Readiness Operasi

Objektif:
- memastikan model lockout/throttle kekal deterministik, pantas, dan maintainable di production

Status:
- `[~]` belum dilaksanakan, sedia untuk verification

Fail / artifak terlibat:
- `public/classes/User.php`
- schema `tbl_auth_login_lockout`
- schema `tbl_auth_login_throttle`
- migration atau docs schema berkaitan

Checklist:
- `[ ]` sahkan unique/index untuk `tbl_auth_login_lockout.f_loginID`
- `[ ]` sahkan unique/index untuk `tbl_auth_login_throttle(f_scope_type, f_scope_key)`
- `[ ]` semak collation table selari dengan canonicalization identifier yang dipilih
- `[ ]` semak kesan `TRIM()` terhadap penggunaan index dan prestasi
- `[ ]` semak retention / cleanup strategy untuk rekod guardrail lama
- `[ ]` semak audit center / admin tooling untuk clear lockout masih berfungsi
- `[ ]` pertimbang metrik operasi untuk:
  - lockout hit rate
  - throttle hit rate
  - failed SSO rate
  - failed manual rate

Acceptance criteria:
- lookup guardrail menggunakan key/index yang sesuai
- canonical identifier strategy selari dengan collation dan constraint DB
- operasi clear lockout dan throttle kekal berfungsi
- growth table guardrail boleh dikawal

Verification cadangan:
- semak schema sebenar dengan `SHOW CREATE TABLE`
- uji load ringan / concurrent failed attempts
- semak audit center action untuk unlock/clear

## Checklist Granular Per Fail

### `public/index.php`

Checklist:
- `[ ]` pindahkan semua session bootstrap kepada `includes/init.php`
- `[ ]` tukar semua check login-state kepada `f_loginID`
- `[ ]` ganti JS cookie clearing kepada server-side cookie clearing
- `[ ]` keluarkan `HTTP_REFERER` dari security decision utama
- `[ ]` tambah header hardening minimum
- `[ ]` validate semua config-driven URLs dan asset paths

### `public/login.php`

Checklist:
- `[ ]` aktifkan CSRF validation sebenar untuk manual login
- `[ ]` perkenalkan normalizer khusus auth dan hentikan `sanitize_string()` pada auth path
- `[ ]` semak `login_client_ip()` dan trust boundary untuk forwarded headers
- `[ ]` samakan guardrail enforcement untuk `MANUAL` dan `SSO`
- `[ ]` rekod failed SSO attempt pada model lockout/throttle
- `[ ]` clear guardrail state pada successful SSO login
- `[ ]` pastikan redirect selepas login kekal melalui sanitizer yang ketat

### `public/controllers/LoginController.php`

Checklist:
- `[ ]` ketatkan pembezaan `MANUAL` vs `SSO` di pintu masuk `authenticate()`
- `[ ]` hentikan category guessing sebagai asas policy untuk unknown account
- `[ ]` kekalkan `password_verify()` untuk manual path
- `[ ]` pastikan session finalization kekal selepas identity/policy sah
- `[ ]` downgrade `normalizeLoginCategory()` kepada helper sokongan, bukan policy gate unknown account
- `[ ]` semak semula `attemptSsoAutoProvision()` supaya bergantung hanya pada source authoritative
- `[ ]` standardkan audit event kepada canonical `f_loginID`

### `public/classes/User.php`

Checklist:
- `[ ]` samakan canonicalization dalam `findByLoginID()`
- `[ ]` samakan canonicalization dalam `getLoginLockoutState()`
- `[ ]` samakan canonicalization dalam `recordFailedLoginAttempt()`
- `[ ]` samakan canonicalization dalam `getLoginThrottleState()` untuk scope berkaitan
- `[ ]` samakan canonicalization dalam `recordFailedLoginThrottle()`
- `[ ]` semak keserasian clear methods dengan strategy baharu
- `[ ]` semak andaian schema/index sebenar untuk lockout/throttle tables

## Pelan Verification Yang Dicadangkan

Urutan verification yang disyorkan:
1. verify Fasa 1 dahulu kerana ia paling rendah risiko
2. verify Fasa 2 sebelum sentuh enforcement SSO
3. verify Fasa 3 dengan data ujian terkawal
4. verify Fasa 4 selepas guardrail parity stabil
5. verify Fasa 5 pada environment ujian yang boleh meniru handoff SSO sebenar
6. verify Fasa 6 sebelum mengisytiharkan hardening auth ini stabil untuk production

Kriteria untuk bergerak ke fasa seterusnya:
- semua checklist kritikal fasa semasa siap
- acceptance criteria fasa semasa lulus
- tiada regression pada login manual
- tiada regression pada login SSO

## Cadangan Urutan Pelaksanaan Sebenar

Urutan yang paling selamat:
1. Fasa 1
2. Fasa 2
3. Fasa 3
4. Fasa 4
5. Fasa 5
6. Fasa 6

Rasional:
- Fasa 1 menguatkan boundary tanpa ubah policy
- Fasa 2 menyelesaikan identity consistency dahulu
- Fasa 3 baru menyamakan guardrail untuk SSO
- Fasa 4 mengurangkan auth ambiguity selepas enforcement stabil
- Fasa 5 mengetatkan kontrak SSO
- Fasa 6 memastikan schema dan operasi support design baharu

## Nota Akhir

Dokumen ini direka untuk dijadikan baseline remedi audit. Ia sesuai digunakan untuk:
- tracking pelaksanaan
- verification per fasa
- rujukan semasa code review
- penyediaan migration atau change plan seterusnya

Dokumen ini belum menukar code aplikasi. Ia ialah pelan remedi rasmi berasaskan dapatan audit semasa.
