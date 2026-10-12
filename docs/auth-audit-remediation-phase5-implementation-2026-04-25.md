# Auth Audit Remediation Phase 5 Implementation Plan

Tarikh: 2026-04-25

Dokumen ini ialah pecahan task implementasi sebenar untuk Fasa 5 daripada roadmap:
- [auth-audit-remediation-roadmap-2026-04-25.md](/var/www/app/iqs-framework/docs/auth-audit-remediation-roadmap-2026-04-25.md)

Skop Fasa 5:
- mengetatkan kontrak `SSO`
- mengurangkan risiko replay dan state abuse pada `sso_auth_handoff`
- memastikan `SSO` hanya diterima daripada source yang telah divalidasi
- mengekalkan UX login `SSO` yang sah

Status legend:
- `[ ]` belum mula
- `[~]` sedia untuk implementasi
- `[x]` siap dan disahkan

## Sasaran Fasa 5

Hasil yang dikehendaki selepas Fasa 5:
- `sso_auth_handoff` mempunyai kontrak penggunaan yang lebih ketat
- handoff hanya boleh digunakan dalam window masa yang sah
- handoff tidak mudah di-replay
- `authenticate()` tidak menerima `SSO` path tanpa source state yang authoritative
- auto-provision kekal boleh digunakan, tetapi di bawah syarat yang lebih jelas dan diaudit

## Prinsip Pelaksanaan Fasa 5

Fasa ini perlu menjaga dua perkara serentak:
- security boundary `SSO`
- kestabilan UX / business flow OneID yang sedia ada

Prinsip:
- jangan ubah flow `SSO` berjaya jika handoff sah
- tambah assertion dan validation, bukan ubah business journey tanpa sebab
- anggap semua handoff sebagai pre-auth state yang mesti dimusnahkan dengan cepat selepas digunakan
- apa yang disimpan dalam session handoff mesti minimum dan jelas
- token / handoff secret tidak boleh direkodkan ke log aplikasi biasa

## Task 1: Definisikan Kontrak Struktur `sso_auth_handoff`

Status:
- `[~]`

Fail / artifak terlibat:
- `public/sso_sp_client.php`
- `public/login.php`
- `public/controllers/LoginController.php`
- dokumen auth/SSO dalaman jika ada

Tujuan:
- menetapkan bentuk data handoff yang minimum, jelas, dan boleh divalidasi dengan konsisten

Perubahan yang dicadangkan:
- dokumenkan field yang dibenarkan dalam `$_SESSION['sso_auth_handoff']`
- tentukan field wajib, contoh:
  - `valid_token`
  - `resolved_login_id`
  - `resolved_source`
  - `issued_at`
  - `return_path` jika sah diperlukan
- tentukan field optional dan retention period

Checklist implementasi:
- `[ ]` inventori field semasa dalam `sso_auth_handoff`
- `[ ]` tandakan field wajib vs optional
- `[ ]` buang field yang tidak diperlukan untuk auth continuation
- `[ ]` dokumentasikan source setiap field
- `[ ]` pastikan semua consumer membaca kontrak yang sama

Acceptance criteria:
- struktur `sso_auth_handoff` didokumenkan dengan jelas
- semua field yang tinggal mempunyai tujuan yang jelas

Verification:
- semak producer dan consumer handoff
- semak session dump ujian bagi flow `SSO`

## Task 2: Kuatkuasakan One-Time Use Untuk Handoff

Status:
- `[~]`

Fail terlibat:
- `public/login.php`
- `public/sso_sp_client.php`
- helper SSO berkaitan

Tujuan:
- mengurangkan risiko replay terhadap handoff yang pernah sah

Perubahan yang dicadangkan:
- pastikan handoff dimusnahkan sebaik sahaja berjaya digunakan atau dinilai invalid
- pertimbang flag consumed atau nonce semantics jika perlu
- elakkan handoff kekal hidup lebih lama daripada yang perlu

Checklist implementasi:
- `[ ]` semak semua tempat `clear_sso_auth_handoff()` dipanggil
- `[ ]` pastikan handoff dibersihkan selepas login success
- `[ ]` pastikan handoff dibersihkan selepas invalid / expired
- `[ ]` pertimbang consumed marker sebelum redirect akhir jika flow memerlukannya
- `[ ]` semak tidak ada branch yang meninggalkan handoff hidup selepas digunakan

Acceptance criteria:
- handoff sah tidak boleh digunakan semula dengan mudah selepas consumption
- handoff invalid/expired tidak kekal dalam session

Verification:
- gunakan satu handoff sah sekali
- cuba reuse handoff yang sama
- uji handoff invalid/expired dan semak state session

## Task 3: Semak Dan Kuatkan TTL / Replay Resistance

Status:
- `[~]`

Fail terlibat:
- `public/login.php`
- `public/sso_sp_client.php`

Tujuan:
- memastikan handoff sah hanya boleh digunakan dalam tempoh yang sempit dan munasabah

Perubahan yang dicadangkan:
- semak TTL semasa `sso_auth_handoff`
- tentukan sama ada 300 saat masih sesuai
- jika perlu, tambah metadata masa seperti:
  - `issued_at`
  - `validated_at`
  - `consumed_at`
- pertimbang nonce / request id binding jika sistem menyokongnya

Checklist implementasi:
- `[ ]` audit TTL semasa dan rationale
- `[ ]` pastikan source masa konsisten
- `[ ]` semak sama ada timestamp yang disimpan cukup untuk validate freshness
- `[ ]` tentukan sama ada nonce / request binding perlu untuk fasa ini
- `[ ]` dokumentasikan tradeoff antara keselamatan dan UX SSO

Acceptance criteria:
- handoff mempunyai window penggunaan yang jelas
- replay selepas tempoh sah tidak lagi diterima

Verification:
- uji handoff segera selepas issuance
- uji handoff selepas tamat TTL
- uji reuse dalam tetingkap masa yang tidak sepatutnya dibenarkan

## Task 4: Ketatkan Assertion Pada `LoginController->authenticate()` Untuk `SSO`

Status:
- `[~]`

Fail terlibat:
- `public/controllers/LoginController.php`
- `public/login.php`

Tujuan:
- memastikan controller auth tidak menerima `SSO` path secara longgar hanya kerana password kosong

Perubahan yang dicadangkan:
- semak boundary masuk `authenticate()`
- pastikan `SSO` path hanya dianggap sah jika dipanggil dari flow yang telah memvalidasi handoff
- tambah assert defensif agar password kosong sahaja tidak cukup untuk dianggap `SSO`

Checklist implementasi:
- `[ ]` audit bagaimana `attemptedMethod` ditentukan sekarang
- `[ ]` tentukan signal authoritative bahawa request ini benar-benar `SSO`
- `[ ]` tambah assertion defensif di orchestration layer atau controller
- `[ ]` pastikan assertion tidak memecahkan flow auto-provision yang sah
- `[ ]` semak behavior jika password kosong dihantar di manual path

Acceptance criteria:
- `SSO` path tidak lagi semata-mata disimpulkan daripada password kosong
- controller hanya menerima `SSO` auth bila source state memang sah

Verification:
- uji manual request dengan password kosong
- uji handoff `SSO` sah
- uji attempt memanggil `authenticate()` dalam keadaan `SSO` yang tidak lengkap

## Task 5: Kaji Semula Auto-Provision Sebagai Boundary Sensitif

Status:
- `[~]`

Fail terlibat:
- `public/controllers/LoginController.php`

Tujuan:
- memastikan auto-provision kekal berguna tetapi tidak menjadi oracle atau laluan penyalahgunaan

Perubahan yang dicadangkan:
- semak semula syarat `attemptSsoAutoProvision()`
- pastikan ia hanya berjalan selepas:
  - handoff sah
  - source category sah
  - route policy membenarkan
- semak maklumat yang didedahkan apabila auto-provision gagal

Checklist implementasi:
- `[ ]` audit semua precondition dalam `attemptSsoAutoProvision()`
- `[ ]` semak comparison `resolved_login_id`
- `[ ]` semak source resolution `STAF` / `PELAJAR`
- `[ ]` semak branch `source_unavailable`, `default_group_invalid`, `not_provisioned`
- `[ ]` tentukan mesej mana patut kekal backend-only
- `[ ]` pastikan transaction rollback dan audit masih lengkap

Acceptance criteria:
- auto-provision hanya berjalan dalam context `SSO` yang sah
- kegagalan auto-provision tidak mendedahkan maklumat berlebihan kepada pihak luar

Verification:
- uji auto-provision berjaya
- uji source record tiada
- uji default group invalid
- uji source unavailable

## Task 6: Pastikan Handoff Tidak Menyimpan Data Berlebihan

Status:
- `[~]`

Fail / artifak terlibat:
- `public/sso_sp_client.php`
- `public/login.php`

Tujuan:
- mengecilkan impak jika session pre-auth ini terdedah atau tersealah guna

Perubahan yang dicadangkan:
- simpan hanya data minimum yang perlu untuk menyambung auth
- buang token mentah atau data sensitif yang tidak diperlukan selepas validation
- gunakan `resolved_login_id` dan metadata minimum sahaja jika mencukupi

Checklist implementasi:
- `[ ]` audit semua field session yang diisi oleh producer handoff
- `[ ]` tandakan field yang hanya diperlukan seketika
- `[ ]` buang field sensitif selepas validation awal jika tidak lagi perlu
- `[ ]` pastikan consumer tidak bergantung pada field yang patut dibuang

Acceptance criteria:
- `sso_auth_handoff` tidak menyimpan lebih data daripada yang perlu
- tiada token mentah disimpan lebih lama daripada perlu

Verification:
- semak session handoff sebelum dan selepas validation
- banding field yang tinggal dengan kontrak baru

## Task 7: Semak Logging Dan Audit Berkaitan `SSO` Handoff

Status:
- `[~]`

Fail terlibat:
- `public/login.php`
- `public/controllers/LoginController.php`
- `public/sso_sp_client.php`

Tujuan:
- memastikan security tightening ini tidak disertai kebocoran token atau data SSO sensitif dalam log

Perubahan yang dicadangkan:
- audit semua `error_log`, `log_login_event`, dan audit event yang menyentuh `SSO`
- pastikan token/handoff secret tidak direkodkan
- cukupkan metadata untuk forensik tanpa mendedahkan rahsia

Checklist implementasi:
- `[ ]` cari semua log berkaitan `SSO`
- `[ ]` semak sama ada token mentah pernah dicatat
- `[ ]` mask atau buang data sensitif dari log
- `[ ]` kekalkan metadata seperti source, reason code, request id jika berguna

Acceptance criteria:
- tiada token SSO mentah dalam log aplikasi biasa
- audit masih cukup kaya untuk troubleshooting

Verification:
- trigger beberapa branch `SSO`
- semak fail log dan audit trail

## Task 8: Sediakan Matrix Verification Untuk Handoff Hardening

Status:
- `[~]`

Fail / artifak terlibat:
- dokumen QA auth/SSO

Tujuan:
- memastikan tightening ini benar-benar menjaga flow yang sah dan menolak flow yang tidak sah

Perubahan yang dicadangkan:
- sediakan matrix ujian minimum bagi:
  - handoff sah pertama kali
  - handoff sah diulang semula
  - handoff expired
  - handoff invalid
  - handoff dengan source mismatch
  - handoff auto-provision berjaya
  - handoff auto-provision gagal

Checklist implementasi:
- `[ ]` senaraikan semua test case replay/freshness
- `[ ]` senaraikan expected result UI
- `[ ]` senaraikan expected result audit/session cleanup
- `[ ]` tentukan langkah manual untuk reproduce case penting

Acceptance criteria:
- semua test case kritikal handoff mempunyai expected result yang jelas
- replay resistance dan cleanup boleh diverify

Verification:
- jalankan matrix ujian selepas implementasi

## Susunan Implementasi Fasa 5

Urutan yang disyorkan:
1. Task 1: definisikan kontrak struktur `sso_auth_handoff`
2. Task 2: kuatkuasakan one-time use
3. Task 3: semak dan kuatkan TTL / replay resistance
4. Task 4: ketatkan assertion pada `authenticate()` untuk `SSO`
5. Task 5: kaji semula auto-provision sebagai boundary sensitif
6. Task 6: pastikan handoff tidak menyimpan data berlebihan
7. Task 7: semak logging dan audit `SSO`
8. Task 8: sediakan matrix verification

Rasional:
- kontrak handoff perlu difahami dahulu
- selepas itu barulah replay resistance dan assertion auth boleh dipasang dengan selamat
- auto-provision dan logging ditapis selepas boundary asas diketatkan

## Checklist Siap Fasa 5

Semua item berikut perlu `[x]` sebelum Fasa 5 dianggap selesai:
- `[ ]` kontrak `sso_auth_handoff` telah didokumenkan dan diselaraskan
- `[ ]` handoff telah menjadi one-time use atau setara
- `[ ]` TTL / replay resistance telah disemak dan dikuatkan
- `[ ]` `authenticate()` tidak lagi menerima `SSO` hanya kerana password kosong
- `[ ]` auto-provision telah disemak sebagai boundary sensitif
- `[ ]` handoff tidak menyimpan data berlebihan
- `[ ]` logging dan audit `SSO` telah dibersihkan
- `[ ]` matrix verification handoff tersedia dan lulus

## Acceptance Criteria Fasa 5

Fasa 5 dianggap lulus jika:
- flow `SSO` sah masih berjaya
- handoff invalid atau expired ditolak secara konsisten
- handoff yang telah digunakan tidak boleh digunakan semula dengan mudah
- auto-provision hanya berjalan dalam context `SSO` yang sah
- log auth/SSO tidak mendedahkan token sensitif

## Cadangan Handover Selepas Fasa 5

Selepas Fasa 5 siap dan disahkan:
- teruskan ke Fasa 6 untuk menyemak schema, index, readiness operasi, dan tooling sokongan
- gunakan hasil verification Fasa 5 sebagai baseline untuk acceptance production-hardening auth boundary
